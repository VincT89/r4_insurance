<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\{InsuranceCollection, User};
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class CollectionCommunicationReminders
{
    public const TYPE = 'insurance_collection_communication';
    public const REVERSAL_TYPE = 'insurance_communicated_collection_voided';

    public function pending(Builder $query, User $user): void
    {
        $query->whereIn('type', [self::TYPE, self::REVERSAL_TYPE]);
        if ($user->status !== 'active' || !$user->can('system.admin')) {
            $query->whereRaw('1=0');
            return;
        }
        $status = app(CollectionCommunicationStatus::class);
        $query->where(fn ($query) => $query->where('type', self::REVERSAL_TYPE)
            ->orWhereIn('data->agency_id', $status->pending($status->payments($user))->select(DB::raw('COALESCE(communication_policy.supplier_agency_id, 0)'))));
    }

    public function forUser(User $user): int
    {
        if ($user->status !== 'active' || !$user->can('system.admin')) return 0;
        return DB::transaction(function () use ($user) {
            /** Serialize the scan and its publication, so a slower tab cannot overwrite a more recent notification. */
            $current = User::whereKey($user->id)->lockForUpdate()->first();
            if (!$current || $current->status !== 'active' || !$current->can('system.admin')) return 0;
            return $this->refreshPending($current);
        }, 3);
    }

    private function refreshPending(User $user): int
    {
        $status = app(CollectionCommunicationStatus::class);
        $query = $status->withDeliveryState($status->pending($status->payments($user))->select([
            'insurance_collections.id','insurance_collections.amount_cents','communication_policy.supplier_agency_id',
            'communication_agency.name as agency_name','communication_agency.email as agency_email','communication_agency.status as agency_status',
        ]))->orderBy('insurance_collections.id')->toBase();
        $groups = [];
        $stream=null;
        if (DB::connection()->getDriverName()==='mysql') {
            /** One unbuffered scan avoids rescanning the whole backlog per page and keeps memory bounded. */
            $configuration=DB::connection()->getConfig();
            unset($configuration['name']);
            $configuration['options'][\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY]=false;
            $stream=DB::build($configuration);
        }
        try { foreach (($stream ? $stream->cursor($query->toSql(),$query->getBindings()) : $query->cursor()) as $payment) {
            $payment->id=(int)$payment->id; $payment->amount_cents=(int)$payment->amount_cents;
            $agency = (int) $payment->supplier_agency_id;
            if (!isset($groups[$agency])) $groups[$agency] = ['name'=>$payment->agency_name ?: 'Agenzia da assegnare', 'count'=>0, 'amount'=>0, 'uncertain'=>0, 'blocked'=>0, 'digest'=>hash_init('sha256')];
            $group = &$groups[$agency];
            $group['count']++;
            $group['amount'] += $payment->amount_cents;
            $group['uncertain'] += in_array($payment->communication_state, ['sending', 'uncertain'], true) ? 1 : 0;
            $group['blocked'] += !$agency || $payment->agency_status !== 'active' || !filter_var($payment->agency_email, FILTER_VALIDATE_EMAIL) ? 1 : 0;
            hash_update($group['digest'], json_encode([$payment->id, $payment->amount_cents, $payment->communication_state, $payment->agency_email, $payment->agency_status], JSON_THROW_ON_ERROR));
            unset($group);
        } } finally { if ($stream) DB::purge($stream->getName()); }
        $created = 0;
        foreach ($groups as $agency => $group) {
            $fingerprint = hash_final($group['digest']);
            $id = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, self::TYPE.'|'.$user->id.'|'.$agency);
            $data = ['type'=>self::TYPE, 'agency_id'=>$agency, 'fingerprint'=>$fingerprint,
                'title'=>$group['uncertain'] ? 'Incassi: controlla l’esito degli invii' : 'Incassi da comunicare',
                'message'=>$group['name'].' · '.$group['count'].' incassi · '.Money::format($group['amount'])
                    .($group['uncertain'] ? '. Verifica gli invii in corso o con esito incerto prima di riprovare.' : ($group['blocked'] ? '. Completa l’agenzia e i suoi recapiti.' : '. Controlla il riepilogo prima dell’invio.')),
                'url'=>route('agency-communications.collections', ['scope'=>'pending', ...($agency ? ['agency'=>$agency] : ['unassigned'=>1])], false)];
            $created += DB::transaction(function () use ($user, $id, $data, $fingerprint) {
                $notification = $user->notifications()->firstOrCreate(['id'=>$id], ['type'=>self::TYPE, 'data'=>$data]);
                if ($notification->wasRecentlyCreated) return 1;
                if (($notification->data['fingerprint'] ?? null) !== $fingerprint) $notification->update(['data'=>$data, 'read_at'=>null]);
                return 0;
            });
        }
        return $created;
    }

    public function recordReversal(InsuranceCollection $payment): void
    {
        /** Retain a warning for every affected agency, including a previous agency changed after the original send. */
        $messages = DB::table('agency_communication_collections as delivery')->join('agency_communications as communication', 'communication.id', '=', 'delivery.agency_communication_id')
            ->where('delivery.insurance_collection_id', $payment->id)->whereNotNull('delivery.delivery_key')
            ->whereIn('communication.status', ['sending', 'sent', 'uncertain'])->get(['communication.id']);
        if ($messages->isEmpty()) return;
        foreach (User::where('status', 'active')->where('role', UserRole::Admin->value)->get() as $admin) {
            foreach ($messages as $message) $admin->notifications()->firstOrCreate([
                'id'=>(string) Uuid::uuid5(Uuid::NAMESPACE_URL, self::REVERSAL_TYPE.'|'.$admin->id.'|'.$payment->id.'|'.$message->id),
            ], ['type'=>self::REVERSAL_TYPE, 'data'=>['type'=>self::REVERSAL_TYPE, 'title'=>'Storno dopo una comunicazione',
                'message'=>'Incasso #'.$payment->id.' stornato: controlla il riepilogo già trasmesso e concorda la rettifica con l’agenzia.',
                'url'=>route('agency-communications.show', $message->id, false)]]);
        }
    }
}
