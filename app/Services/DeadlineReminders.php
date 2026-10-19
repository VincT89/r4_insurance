<?php

namespace App\Services;

use App\Models\{Attachment, Client, ClientDocumentRequest, InsurancePolicy, InsuranceTitle, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;

class DeadlineReminders
{
    public const TYPE = 'insurance_deadline';

    public function pending(Builder $query, User $user): void
    {
        $query->where('type', self::TYPE)->whereIn('data->client_id', Client::current()->visibleTo($user)->select('id'));
        if ($user->status !== 'active' || !$user->can('viewAny', Client::class)) {
            $query->whereRaw('1=0');
            return;
        }

        /** Check live records on every dropdown read: the creation cache must never keep a settled deadline visible. */
        $query->where(function (Builder $query) {
            $sources = [
                'title' => InsuranceTitle::whereNull('cancelled_at')->whereColumn('collected_cents', '<', 'amount_cents'),
                'policy' => InsurancePolicy::whereIn('status', ['active', 'renewed', 'expired']),
                'document' => Attachment::where('attachable_type', (new Client)->getMorphClass())->whereNotNull('document_category')->whereDoesntHave('newerVersion'),
                'request' => ClientDocumentRequest::whereDoesntHave('documents'),
            ];
            foreach ($sources as $kind => $source) {
                $table = $source->getModel()->getTable();
                $date = in_array($kind, ['policy', 'document'], true) ? 'expires_on' : 'due_on';
                /** Date casts may persist a midnight time on SQLite; compare calendar dates on both supported databases. */
                $dueOn = DB::raw($source->getQuery()->getGrammar()->wrap('notifications.data->due_on'));
                $query->orWhere(fn (Builder $query) => $query->where('data->kind', $kind)
                    ->whereIn('data->source_id', $source->select($table.'.id')->whereDate($table.'.'.$date, '=', $dueOn)));
            }
        });
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
        $range = [today()->toDateString(), today()->addDays(15)->toDateString()];
        $policies = InsurancePolicy::whereIn('client_id', clone $visible)->whereIn('status', ['active', 'renewed'])->whereDate('expires_on','>=',$range[0])->whereDate('expires_on','<=',$range[1])
            ->with('client:id,name')->get()->map(fn ($p) => ['kind'=>'policy', 'source_id'=>$p->id, 'client_id'=>$p->client_id, 'due_on'=>$p->expires_on->toDateString(), 'name'=>'Polizza '.$p->number, 'client'=>$p->client->name, 'url'=>route('clients.policies.show', [$p->client_id,$p->id], false)]);
        $documents = Attachment::where('attachable_type', (new Client)->getMorphClass())->whereIn('attachable_id', clone $visible)->whereNotNull('document_category')->whereDoesntHave('newerVersion')->whereDate('expires_on','>=',$range[0])->whereDate('expires_on','<=',$range[1])
            ->get()->map(fn ($d) => ['kind'=>'document', 'source_id'=>$d->id, 'client_id'=>$d->attachable_id, 'due_on'=>$d->expires_on->toDateString(), 'name'=>Attachment::DOCUMENT_CATEGORIES[$d->document_category] ?? 'Documento', 'client'=>null, 'url'=>route('clients.show', [$d->attachable_id, 'tab'=>'documents'], false)]);
        $requests = ClientDocumentRequest::whereIn('client_id', clone $visible)->whereDoesntHave('documents')->whereDate('due_on','>=',$range[0])->whereDate('due_on','<=',$range[1])
            ->get()->map(fn ($r) => ['kind'=>'request', 'source_id'=>$r->id, 'client_id'=>$r->client_id, 'due_on'=>$r->due_on->toDateString(), 'name'=>$r->label, 'client'=>null, 'url'=>route('clients.show', [$r->client_id,'tab'=>'documents'], false).'#document-request-'.$r->id]);
        $created = 0;
        $titles=InsuranceTitle::visibleTo($user)->whereNull('cancelled_at')->whereColumn('collected_cents','<','amount_cents')->whereDate('due_on','>=',$range[0])->whereDate('due_on','<=',$range[1])->with('policy.client')->get()->map(fn($t)=>['kind'=>'title','source_id'=>$t->id,'client_id'=>$t->policy->client_id,'due_on'=>$t->due_on->toDateString(),'name'=>'Rata della polizza '.$t->policy->number.' ('.$t->payment_frequency.')','client'=>$t->policy->client->name,'url'=>route('titles.show',$t,false)]);
        foreach ($policies->concat($documents)->concat($requests)->concat($titles) as $item) {
            $created += DB::transaction(function () use ($user, $item) {
                $inserted = DB::table('insurance_deadline_reminders')->insertOrIgnore(['user_id'=>$user->id, 'kind'=>$item['kind'], 'source_id'=>$item['source_id'], 'due_on'=>$item['due_on'], 'created_at'=>now()]);
                if (!$inserted) return 0;
                $date = \Illuminate\Support\Carbon::parse($item['due_on'])->format('d/m/Y');
                $clientName = $item['client'] ?? Client::whereKey($item['client_id'])->value('name');
                $user->notifications()->create(['id'=>(string)Str::uuid(), 'type'=>self::TYPE, 'data'=>[
                    'type'=>self::TYPE, 'title'=>'Scadenza entro 15 giorni', 'client_id'=>$item['client_id'],
                    'kind'=>$item['kind'], 'source_id'=>$item['source_id'], 'due_on'=>$item['due_on'],
                    'message'=>$item['name'].' · '.$clientName.' · '.$date, 'url'=>$item['url'],
                ]]);
                return 1;
            });
        }
        return $created;
    }
}
