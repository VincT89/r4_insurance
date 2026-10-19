<?php

namespace App\Services;

use App\Domain\Core\Queries\PolicyPortfolioQuery;
use App\Models\{AgencyCommunication, InsurancePolicy, SupplierAgency, User};
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AgencyEmailWorkflow
{
    public function __construct(private AgencyMailConfiguration $configuration, private AgencyEmailTransport $transport) {}

    private function audit(string $action, AgencyCommunication $message, string $description): void
    {
        /** The existing audit registry uses numeric entity IDs; the public message reference belongs in its description. */
        $subject = $message->agency ?? auth()->user();
        app(AuditLogService::class)->log($action,$subject,description:$description.' Riferimento: '.$message->id.'.');
    }

    public function policyFingerprint(InsurancePolicy $policy): string
    {
        $attributes = $policy->getAttributes(); unset($attributes['updated_at']); ksort($attributes);
        $client = $policy->client->getAttributes(); unset($client['updated_at']); ksort($client);
        return hash('sha256', json_encode([$attributes, $client], JSON_THROW_ON_ERROR));
    }

    public function deliveryKey(InsurancePolicy $policy, string $recipient): string
    {
        return hash('sha256', $policy->id.'|'.$this->policyFingerprint($policy).'|'.strtolower($recipient));
    }

    public function existingDeliveries(Collection $policies): Collection
    {
        $keys = $policies->filter(fn ($p) => $p->supplierAgency?->email)->mapWithKeys(fn ($p) => [$this->deliveryKey($p, $p->supplierAgency->email) => $p->id]);
        if ($keys->isEmpty()) return collect();
        return DB::table('agency_communication_policies as p')->join('agency_communications as c', 'c.id', '=', 'p.agency_communication_id')
            ->whereIn('p.delivery_key', $keys->keys())->get(['p.insurance_policy_id','c.id','c.status','c.sent_at'])->keyBy('insurance_policy_id');
    }

    public function prepare(User $user, SupplierAgency $agency, Collection $policies, array $filters, string $range): AgencyCommunication
    {
        $this->validateSelection($agency, $policies);
        [$payload, $fingerprint] = $this->snapshot($agency, $policies, $range);
        return DB::transaction(function () use ($user, $agency, $policies, $filters, $payload, $fingerprint) {
            $communication = AgencyCommunication::create(['supplier_agency_id'=>$agency->id, 'user_id'=>$user->id, 'status'=>'review',
                'payload'=>$payload + ['filters'=>$filters], 'fingerprint'=>$fingerprint, 'review_expires_at'=>now()->addMinutes(30)]);
            foreach ($policies as $policy) DB::table('agency_communication_policies')->insert(['agency_communication_id'=>$communication->id,
                'insurance_policy_id'=>$policy->id, 'policy_fingerprint'=>$this->policyFingerprint($policy)]);
            $this->audit('agency_email_review',$communication,'Preparato il controllo di '.$policies->count().' polizze per '.$agency->name.'. Nessun messaggio inviato.');
            return $communication;
        });
    }

    private function validateSelection(SupplierAgency $agency, Collection $policies): void
    {
        if ($agency->status !== 'active' || !filter_var($agency->email, FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['agency'=>'Completa l’indirizzo email di un’agenzia attiva prima di continuare.']);
        if ($policies->isEmpty() || $policies->contains(fn ($p) => (int)$p->supplier_agency_id !== $agency->id || !$p->client || !in_array($p->status,['active','renewed'],true) || $p->expires_on->lt(today()))) {
            throw ValidationException::withMessages(['policies'=>'Seleziona soltanto polizze attive o rinnovate, non scadute, della stessa agenzia.']);
        }
    }

    private function snapshot(SupplierAgency $agency, Collection $policies, string $range): array
    {
        $text = fn ($value) => trim(preg_replace('/[\r\n\t]+/u', ' ', (string)$value));
        $rows = $policies->sortBy('number')->map(fn ($p) => [
            'id'=>$p->id, 'client_id'=>$p->client_id, 'number'=>$text($p->number), 'client'=>$text($p->client->name),
            'company'=>$text($p->company), 'branch'=>$text($p->category), 'subbranch'=>$text($p->subcategory),
            'starts_on'=>$p->starts_on->format('d/m/Y'), 'expires_on'=>$p->expires_on->format('d/m/Y'),
            'frequency'=>$text($p->payment_frequency ?: 'Non indicato'), 'payment_method'=>$text($p->payment_method ?: 'Non indicato'), 'ad_horas'=>(bool)$p->ad_horas,
        ])->values()->all();
        $lines = ['Buongiorno,', '', 'riepilogo delle polizze inserite nel periodo '.$range.':', ''];
        foreach ($rows as $row) {
            $lines[] = ($row['ad_horas'] ? 'AD HORAS - ' : '').$row['number'].' | '.$row['client'].' | '.$row['company'];
            $lines[] = 'Ramo: '.$row['branch'].($row['subbranch'] ? ' / '.$row['subbranch'] : '').'; decorrenza: '.$row['starts_on'].'; scadenza: '.$row['expires_on'].'.';
            $lines[] = 'Frazionamento: '.$row['frequency'].'. Metodo di pagamento indicato in polizza: '.$row['payment_method'].'.';
            $lines[] = '';
        }
        $lines[] = 'Il riepilogo non attesta un incasso o l’emissione di una quietanza.';
        $lines[] = ''; $lines[] = config('branding.wordmark');
        $sender = $this->configuration->sender();
        $recipient = ['address'=>$agency->email, 'name'=>$agency->name];
        $payload = ['from'=>$sender, 'recipient'=>$recipient, 'range'=>$range, 'policies'=>$rows,
            'subject'=>($policies->contains('ad_horas', true) ? 'Ad horas - ' : '').'Riepilogo polizze '.$range.' - '.config('branding.wordmark'), 'body'=>implode("\n",$lines)];
        $fingerprint = hash('sha256', json_encode([$payload, $policies->sortBy('id')->map(fn ($p) => $this->policyFingerprint($p))->values()->all()], JSON_THROW_ON_ERROR));
        return [$payload, $fingerprint];
    }

    public function send(AgencyCommunication $communication, User $user): AgencyCommunication
    {
        $configuration = $this->configuration->transport();
        try {
            $claimed = DB::transaction(function () use ($communication, $user) {
                $message = AgencyCommunication::whereKey($communication->id)->lockForUpdate()->firstOrFail();
                abort_unless((int)$message->user_id === $user->id, 403);
                if ($message->status === 'sent') return false;
                if ($message->status !== 'review') throw ValidationException::withMessages(['email'=>'Questo riepilogo è già stato confermato. Controlla il suo esito nello storico.']);
                if ($message->review_expires_at->isPast()) throw ValidationException::withMessages(['email'=>'Il controllo è scaduto: seleziona nuovamente le polizze e riapri il riepilogo.']);
                $agency = SupplierAgency::whereKey($message->supplier_agency_id)->lockForUpdate()->first();
                if (!$agency) throw ValidationException::withMessages(['email'=>'L’agenzia non è più disponibile.']);
                if(($message->payload['kind']??null)==='collections') return app(AgencyCollectionEmailWorkflow::class)->claim($message,$user,$agency);
                $ids = array_column($message->payload['policies'], 'id');
                $policies = app(PolicyPortfolioQuery::class)->forUser($user, [])->whereIn('id',$ids)->whereDoesntHave('importEntry')->with('client')->orderBy('id')->lockForUpdate()->get();
                if ($policies->count() !== count($ids)) throw ValidationException::withMessages(['email'=>'Alcune polizze non sono più disponibili. Riapri la selezione.']);
                $this->validateSelection($agency,$policies);
                [, $fingerprint] = $this->snapshot($agency, $policies, $message->payload['range']);
                if (!hash_equals($message->fingerprint,$fingerprint)) throw ValidationException::withMessages(['email'=>'Polizze, cliente, destinatario o mittente sono cambiati dopo il controllo. Prepara un nuovo riepilogo.']);
                /** The unique claim is committed before SMTP; repeated clicks and overlapping selections cannot send the same policy version twice. */
                foreach ($policies as $policy) {
                    $key = $this->deliveryKey($policy, $agency->email);
                    if (DB::table('agency_communication_policies')->where('delivery_key',$key)->exists()) throw ValidationException::withMessages(['policies'=>'Una delle polizze è già stata inviata o ha un invio in corso. Controlla lo storico e aggiorna la selezione.']);
                    DB::table('agency_communication_policies')->where('agency_communication_id',$message->id)->where('insurance_policy_id',$policy->id)->update(['delivery_key'=>$key]);
                }
                $message->update(['status'=>'sending','reviewed_at'=>now(),'sending_at'=>now()]);
                $this->audit('agency_email_sending',$message,'Confermato l’invio di '.count($ids).' polizze a '.$agency->name.'.');
                return true;
            });
        } catch (QueryException $exception) {
            if (in_array((string)$exception->getCode(), ['23000','23505'], true)) throw ValidationException::withMessages(['policies'=>'Invio già prenotato per una delle polizze. Aggiorna la selezione.']);
            throw $exception;
        }
        if (!$claimed) return $communication->fresh();
        $message = $communication->fresh();
        $previousAbort = ignore_user_abort(true);
        try {
            try { $messageId = $this->transport->send($message->payload, $configuration); }
            catch (\Throwable $exception) {
                /** SMTP may accept DATA before a connection failure; no automatic retry can safely infer non-delivery. */
                $message->update(['status'=>'uncertain','error_code'=>'smtp_unconfirmed']);
                $this->audit('agency_email_uncertain',$message,'Il server di posta non ha confermato l’esito. Ripetizione automatica bloccata.');
                return $message;
            }
            $message->update(['status'=>'sent','sent_at'=>now(),'message_id'=>mb_substr($messageId,0,255),'error_code'=>null]);
            $this->audit('agency_email_sent',$message,'Email cumulativa accettata dal server di posta per '.$message->payload['recipient']['name'].': '.count($message->payload['policies']).' polizze.');
            return $message;
        } finally { ignore_user_abort((bool)$previousAbort); }
    }

    public function resolve(AgencyCommunication $communication, bool $sent, string $note): void
    {
        DB::transaction(function () use ($communication, $sent, $note) {
            $message = AgencyCommunication::whereKey($communication->id)->lockForUpdate()->firstOrFail();
            if (!$message->canResolve()) throw ValidationException::withMessages(['email'=>'L’esito non richiede una verifica manuale.']);
            $message->update(['status'=>$sent ? 'sent' : 'failed', 'sent_at'=>$sent ? now() : null, 'error_code'=>$sent ? 'manually_confirmed' : 'manually_not_sent']);
            if (!$sent) DB::table('agency_communication_policies')->where('agency_communication_id',$message->id)->update(['delivery_key'=>null]);
            if (!$sent && ($message->payload['kind']??null)==='collections') DB::table('agency_communication_collections')->where('agency_communication_id',$message->id)->update(['delivery_key'=>null]);
            $this->audit('agency_email_resolved',$message,($sent ? 'Invio confermato' : 'Mancato invio confermato').' dopo verifica manuale: '.$note);
        });
    }
}
