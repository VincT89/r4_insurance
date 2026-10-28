<?php

namespace App\Services;

use App\Models\{Attachment, Client, ClientDocumentRequest, InsurancePolicy, InsuranceTitle, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use App\Support\Money;

class DeadlineReminders
{
    public const TYPE = 'insurance_deadline';

    public function pending(Builder $query, User $user): void
    {
        $query->where('type', self::TYPE);
        if ($user->status !== 'active' || !$user->can('viewAny', Client::class)) {
            $query->whereRaw('1=0');
            return;
        }

        /** Resolve current sources as a set; paid deadlines disappear immediately without scanning JSON per notification. */
        $clients = Client::current()->visibleTo($user)->select('id');
        $sources = [
            'title' => InsuranceTitle::visibleTo($user)->whereNull('cancelled_at')->whereColumn('collected_cents', '<', 'amount_cents'),
            'policy' => InsurancePolicy::whereIn('client_id', clone $clients)->whereIn('status', ['active', 'renewed', 'expired']),
            'document' => Attachment::whereIn('attachable_id', clone $clients)->where('attachable_type', (new Client)->getMorphClass())->whereNotNull('document_category')->whereDoesntHave('newerVersion'),
            'request' => ClientDocumentRequest::whereIn('client_id', clone $clients)->whereDoesntHave('documents')->whereDoesntHave('linkedDocuments'),
        ];
        $pending=null;
        foreach ($sources as $kind=>$source) {
            $table=$source->getModel()->getTable();
            $date=in_array($kind,['policy','document'],true)?'expires_on':'due_on';
            $ids=DB::table('insurance_deadline_reminders as reminder')
                ->joinSub($source->select($table.'.id',$table.'.'.$date),'source',fn($join)=>$join->on('source.id','=','reminder.source_id'))
                ->where('reminder.user_id',$user->id)->where('reminder.kind',$kind)->whereNotNull('reminder.notification_id')
                ->whereDate('source.'.$date,'=',DB::raw('date(reminder.due_on)'))->select('reminder.notification_id');
            $pending=$pending?$pending->unionAll($ids):$ids;
        }
        /** Keep the UNION in a derived table so MariaDB materializes it once, rather than once per notification. */
        $query->whereIn('notifications.id',DB::query()->fromSub($pending,'pending_deadlines')->select('notification_id'));
    }

    public function reopenTitle(InsuranceTitle $title): void
    {
        if ($title->cancelled_at || $title->balance_cents <= 0) return;
        /** Reuse the original reminder after a reversal; retain its history and avoid duplicate notifications. */
        DatabaseNotification::where('notifiable_type', (new User)->getMorphClass())->where('type', self::TYPE)
            ->where('data->kind', 'title')->where('data->source_id', $title->id)->where('data->due_on', $title->due_on->toDateString())
            ->update(['read_at' => null]);
    }

    public function refresh(User $user): void
    {
        if (Cache::add('deadline-reminders:'.$user->id, true, now()->addMinutes(15))) {
            try { $this->forUser($user); }
            catch (\Throwable $e) { Cache::forget('deadline-reminders:'.$user->id); throw $e; }
        }
    }

    public function forUser(User $user): int
    {
        if ($user->status !== 'active' || !$user->can('viewAny', Client::class)) return 0;
        $visible = Client::current()->visibleTo($user)->select('id');
        $policies = InsurancePolicy::whereIn('client_id', clone $visible)->where(fn ($query) => $query->whereIn('status', ['active', 'renewed'])
            ->orWhere(fn ($query) => $query->where('status', 'expired')->whereDate('expires_on', '<=', today())))->with('client:id,name');
        $documents = Attachment::where('attachable_type', (new Client)->getMorphClass())->whereIn('attachable_id', clone $visible)->whereNotNull('document_category')->whereDoesntHave('newerVersion');
        $requests = ClientDocumentRequest::whereIn('client_id', clone $visible)->whereDoesntHave('documents')->whereDoesntHave('linkedDocuments')->with('client:id,name');
        $titles = InsuranceTitle::visibleTo($user)->whereNull('cancelled_at')->whereColumn('collected_cents', '<', 'amount_cents')->with('policy.client');
        $created = 0;
        foreach (['policy'=>$policies, 'document'=>$documents, 'request'=>$requests, 'title'=>$titles] as $kind => $query) {
            $table = $query->getModel()->getTable();
            $dateColumn = in_array($kind, ['policy', 'document'], true) ? 'expires_on' : 'due_on';
            /** Recover missed and overdue deadlines without loading ten years of already notified history. */
            $query->whereDate($dateColumn, '<=', today()->addDays(15))->whereNotExists(fn ($known) => $known->selectRaw('1')->from('insurance_deadline_reminders')
                ->where('user_id', $user->id)->where('kind', $kind)->whereColumn('source_id', $table.'.id')
                ->whereDate('due_on', '=', DB::raw('date('.$table.'.'.$dateColumn.')')));
            $query->chunkById(250, function ($records) use ($user,$kind,$dateColumn,&$created) {
                $names=$kind==='document' ? Client::whereIn('id',$records->pluck('attachable_id'))->pluck('name','id') : collect();
                $created+=DB::transaction(function () use ($user,$kind,$dateColumn,$records,$names) {
                    /** Serialize batches for the same user so simultaneous page and scheduler requests cannot duplicate reminders. */
                    $current=User::whereKey($user->id)->lockForUpdate()->first();
                    if (!$current || $current->status!=='active' || !$current->can('viewAny',Client::class)) return 0;
                    $known=DB::table('insurance_deadline_reminders')->where('user_id',$user->id)->where('kind',$kind)
                        ->whereIn('source_id',$records->modelKeys())->get()->mapWithKeys(fn($row)=>[$row->source_id.'|'.substr($row->due_on,0,10)=>true]);
                    $markers=[]; $notifications=[]; $now=now();
                    foreach ($records as $record) {
                        $due=$record->{$dateColumn}->toDateString();
                        if (isset($known[$record->id.'|'.$due])) continue;
                        $item=match ($kind) {
                            'policy'=>['client_id'=>$record->client_id,'client'=>$record->client->name,'name'=>'Scadenza contratto '.$record->number,'url'=>route('clients.policies.show',[$record->client_id,$record->id],false)],
                            'title'=>['client_id'=>$record->policy->client_id,'client'=>$record->policy->client->name,'name'=>'Rata della polizza '.$record->policy->number.' ('.$record->payment_frequency.')','url'=>route('titles.show',$record,false)],
                            'document'=>['client_id'=>$record->attachable_id,'client'=>$names[$record->attachable_id]??'','name'=>Attachment::DOCUMENT_CATEGORIES[$record->document_category]??'Documento','url'=>route('clients.show',[$record->attachable_id,'tab'=>'documents'],false)],
                            'request'=>['client_id'=>$record->client_id,'client'=>$record->client->name,'name'=>$record->label,'url'=>route('clients.show',[$record->client_id,'tab'=>'documents'],false).'#document-request-'.$record->id],
                        };
                        $notificationId=(string)Str::uuid();
                        $markers[]=['user_id'=>$user->id,'kind'=>$kind,'source_id'=>$record->id,'due_on'=>$due,'created_at'=>$now,'notification_id'=>$notificationId];
                        $notifications[]=['id'=>$notificationId,'type'=>self::TYPE,'notifiable_type'=>$user->getMorphClass(),'notifiable_id'=>$user->id,
                            'data'=>json_encode(['type'=>self::TYPE,'title'=>'Scadenza entro 15 giorni','client_id'=>$item['client_id'],
                                'kind'=>$kind,'source_id'=>$record->id,'due_on'=>$due,
                                'message'=>$item['name'].' · '.$item['client'].' · '.$record->{$dateColumn}->format('d/m/Y'),'url'=>$item['url']],JSON_THROW_ON_ERROR),
                            'created_at'=>$now,'updated_at'=>$now,'read_at'=>null];
                    }
                    if ($markers) {
                        DB::table('insurance_deadline_reminders')->insert($markers);
                        DB::table('notifications')->insert($notifications);
                    }
                    return count($notifications);
                },3);
            });
        }
        return $created;
    }

    public function details(Collection $notifications, User $user): array
    {
        $deadlines = $notifications->where('type', self::TYPE);
        $ids = $deadlines->filter(fn ($item) => ($item->data['kind'] ?? null) === 'title')->map(fn ($item) => $item->data['source_id']);
        $titles = $ids->isEmpty() ? collect() : InsuranceTitle::visibleTo($user)->whereIn('id', $ids)->with('policy.client')->get()->keyBy('id');
        $documentIds = $deadlines->filter(fn ($item) => ($item->data['kind'] ?? null) === 'document')->map(fn ($item) => $item->data['source_id']);
        /** Enrich only the displayed files, including older alerts, without resetting their read history. */
        $documents = $documentIds->isEmpty() ? collect() : Attachment::whereIn('id', $documentIds)
            ->where('attachable_type', (new Client)->getMorphClass())
            ->whereIn('attachable_id', Client::current()->visibleTo($user)->select('id'))
            ->whereNotNull('document_category')->whereNotNull('expires_on')->whereDoesntHave('newerVersion')
            ->with('attachable:id,name')->get(['id','attachable_type','attachable_id','original_name','document_category','expires_on'])->keyBy('id');
        $details = [];
        foreach ($deadlines as $notification) {
            if (($notification->data['kind'] ?? null) === 'document') {
                if ($document = $documents->get($notification->data['source_id'])) {
                    $details[$notification->id] = ['title'=>$document->expires_on->lt(today()) ? 'Documento scaduto' : 'Documento in scadenza',
                        'message'=>(Attachment::DOCUMENT_CATEGORIES[$document->document_category] ?? 'Documento').' · '.$document->original_name.' · '.$document->attachable->name.' · '.$document->expires_on->format('d/m/Y')];
                }
                continue;
            }
            if (($notification->data['kind'] ?? null) !== 'title') continue;
            if (!$title = $titles->get($notification->data['source_id'])) continue;
            $details[$notification->id] = ['title'=>$title->due_on->lt(today()) ? 'Rata scaduta da incassare' : 'Rata da incassare',
                'message'=>$title->policy->number.' · '.$title->policy->client->name.' · '.$title->payment_frequency.' · '.$title->due_on->format('d/m/Y').' · Residuo '.Money::format($title->balance_cents)];
        }
        return $details;
    }
}
