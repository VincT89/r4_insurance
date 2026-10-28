<?php

namespace App\Services;

use App\Models\{AgencyCommunication, Client, InsurancePolicy, InsurancePolicyChange, SupplierAgency, User};
use App\Support\Money;
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

    public function lockedPolicies(AgencyCommunication $message, User $user): Collection
    {
        $reviewed = collect($message->payload['policies']);
        /** Lock clients before contracts, as in the registry workflow; normal eager reads could retain an older MySQL snapshot. */
        $clients = Client::current()->visibleTo($user)->whereIn('id', $reviewed->pluck('client_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $policies = InsurancePolicy::whereIn('id', $reviewed->pluck('id'))->whereIn('client_id', $clients->keys())->orderBy('id')->lockForUpdate()->get();
        if ($policies->count() !== $reviewed->count()) throw ValidationException::withMessages(['email'=>'Alcune polizze o anagrafiche sono cambiate o non sono più disponibili. Riapri il controllo.']);
        foreach ($policies as $policy) $policy->setRelation('client', $clients->get($policy->client_id));
        return $policies;
    }

    public function existingDeliveries(Collection $policies): Collection
    {
        $keys = $policies->filter(fn ($p) => $p->supplierAgency?->email)->mapWithKeys(fn ($p) => [$this->deliveryKey($p, $p->supplierAgency->email) => $p->id]);
        if ($keys->isEmpty()) return collect();
        return DB::table('agency_communication_policies as p')->join('agency_communications as c', 'c.id', '=', 'p.agency_communication_id')
            ->whereIn('p.delivery_key', $keys->keys())->get(['p.insurance_policy_id','c.id','c.status','c.sent_at'])->keyBy('insurance_policy_id');
    }

    public function changeDeliveryKey(InsurancePolicyChange $change): string
    {
        /** An appendix is one immutable operation: changing a contact address or a contract field does not authorize another delivery. */
        return hash('sha256', 'change|'.$change->id.'|'.$change->resultPolicy->supplier_agency_id);
    }

    public function existingChangeDeliveries(Collection $changes): Collection
    {
        $keys = $changes->map(fn ($change) => $this->changeDeliveryKey($change));
        if ($keys->isEmpty()) return collect();
        return DB::table('agency_communication_changes as p')->join('agency_communications as c', 'c.id', '=', 'p.agency_communication_id')
            ->whereIn('p.delivery_key', $keys)->get(['p.insurance_policy_change_id','c.id','c.status','c.sent_at'])->keyBy('insurance_policy_change_id');
    }

    private function referencedPolicies(Collection $policies, Collection $changes): Collection
    {
        return $policies->concat($changes->map(fn ($change) => $change->resultPolicy))->unique('id')->values();
    }

    public function prepare(User $user, SupplierAgency $agency, Collection $policies, array $filters, string $range, ?Collection $changes = null): AgencyCommunication
    {
        $changes ??= collect();
        $allPolicies = $this->referencedPolicies($policies, $changes);
        $this->validateSelection($agency, $allPolicies, false);
        $this->validateChanges($changes);
        if ($policies->count() + $changes->count() > 500) throw ValidationException::withMessages(['policies'=>'Seleziona al massimo 500 operazioni per agenzia.']);
        [$payload, $fingerprint] = $this->snapshot($agency, $policies, $range, $changes);
        return DB::transaction(function () use ($user, $agency, $allPolicies, $changes, $filters, $payload, $fingerprint) {
            $communication = AgencyCommunication::create(['supplier_agency_id'=>$agency->id, 'user_id'=>$user->id, 'status'=>'review',
                'payload'=>$payload + ['filters'=>$filters], 'fingerprint'=>$fingerprint, 'review_expires_at'=>now()->addMinutes(30)]);
            foreach ($allPolicies as $policy) DB::table('agency_communication_policies')->insert(['agency_communication_id'=>$communication->id,
                'insurance_policy_id'=>$policy->id, 'policy_fingerprint'=>$this->policyFingerprint($policy)]);
            foreach ($changes as $change) DB::table('agency_communication_changes')->insert(['agency_communication_id'=>$communication->id, 'insurance_policy_change_id'=>$change->id]);
            $this->audit('agency_email_review',$communication,'Preparato il controllo di '.count($payload['selection_keys']).' operazioni per '.$agency->name.'. Nessun messaggio inviato.');
            return $communication;
        });
    }

    private function validateSelection(SupplierAgency $agency, Collection $policies, bool $requireRecipient = true): void
    {
        if ($agency->status !== 'active') throw ValidationException::withMessages(['agency'=>'Seleziona un’agenzia attiva.']);
        /** Review can precede contact configuration; the send path always requires a valid recipient and a fresh fingerprint. */
        if ($requireRecipient && !filter_var($agency->email, FILTER_VALIDATE_EMAIL)) throw ValidationException::withMessages(['agency'=>'Completa l’indirizzo email dell’agenzia e prepara un nuovo riepilogo prima di inviare.']);
        if ($policies->isEmpty() || $policies->contains(fn ($p) => (int)$p->supplier_agency_id !== $agency->id || !$p->client || !in_array($p->status,['active','renewed'],true) || $p->expires_on->lt(today()))) {
            throw ValidationException::withMessages(['policies'=>'Seleziona soltanto polizze attive o rinnovate, non scadute, della stessa agenzia.']);
        }
    }

    private function validateChanges(Collection $changes): void
    {
        if ($changes->contains(fn ($change) => $change->kind !== 'addition' || $change->source_policy_id !== $change->result_policy_id
            || !$change->resultPolicy || $change->expires_on->lt(today()))) {
            throw ValidationException::withMessages(['policies'=>'Seleziona soltanto aggiunte di coperture non scadute della polizza indicata.']);
        }
    }

    private function snapshot(SupplierAgency $agency, Collection $policies, string $range, ?Collection $changes = null): array
    {
        $changes ??= collect();
        $allPolicies = $this->referencedPolicies($policies, $changes);
        $text = fn ($value) => trim(preg_replace('/[\r\n\t]+/u', ' ', (string)$value));
        $rows = $allPolicies->sortBy('number')->map(fn ($p) => [
            'id'=>$p->id, 'client_id'=>$p->client_id, 'number'=>$text($p->number), 'client'=>$text($p->client->name),
            'company'=>$text($p->company), 'branch'=>$text($p->category), 'subbranch'=>$text($p->subcategory),
            'starts_on'=>$p->starts_on->format('d/m/Y'), 'expires_on'=>$p->expires_on->format('d/m/Y'),
            'last_renewed_at'=>$p->last_renewed_at?->format('d/m/Y'),
            'premium'=>$p->premium === null ? 'Non indicato' : Money::format(Money::cents((string) $p->premium)),
            'frequency'=>$text($p->payment_frequency ?: 'Non indicato'), 'payment_method'=>$text($p->payment_method ?: 'Non indicato'), 'ad_horas'=>(bool)$p->ad_horas,
        ])->values()->all();
        $changeRows = $changes->sortBy('id')->map(fn ($change) => [
            'id'=>$change->id, 'policy_id'=>$change->result_policy_id, 'client_id'=>$change->resultPolicy->client_id,
            'number'=>$text($change->resultPolicy->number), 'client'=>$text($change->resultPolicy->client->name), 'company'=>$text($change->resultPolicy->company),
            'reference'=>$text($change->reference), 'description'=>$text($change->description),
            'recorded_at'=>$change->created_at->format('d/m/Y H:i'), 'starts_on'=>$change->effective_on->format('d/m/Y'), 'expires_on'=>$change->expires_on->format('d/m/Y'),
            'premium_cents'=>$change->premium_cents, 'premium'=>Money::format($change->premium_cents), 'ad_horas'=>(bool) $change->resultPolicy->ad_horas,
        ])->values()->all();
        $policyIds = $policies->sortBy('id')->pluck('id')->values()->all();
        $lines = ['Buongiorno,', '', 'riepilogo delle polizze e delle aggiunte di coperture registrate nel periodo '.$range.':', ''];
        foreach (array_filter($rows, fn ($row) => in_array($row['id'], $policyIds, true)) as $row) {
            $lines[] = ($row['ad_horas'] ? 'AD HORAS - ' : '').$row['number'].' | '.$row['client'].' | '.$row['company'];
            $lines[] = 'Ramo: '.$row['branch'].($row['subbranch'] ? ' / '.$row['subbranch'] : '').'; decorrenza: '.$row['starts_on'].'; scadenza: '.$row['expires_on'].'.';
            if ($row['last_renewed_at']) $lines[] = 'Rinnovo registrato il '.$row['last_renewed_at'].'.';
            $lines[] = 'Premio della polizza: '.$row['premium'].'.';
            $lines[] = 'Frazionamento: '.$row['frequency'].'. Metodo di pagamento indicato in polizza: '.$row['payment_method'].'.';
            $lines[] = '';
        }
        foreach ($changeRows as $row) {
            $lines[] = ($row['ad_horas'] ? 'AD HORAS - ' : '').'Aggiunta di coperture '.$row['reference'].' | Polizza '.$row['number'].' | '.$row['client'].' | '.$row['company'];
            $lines[] = 'Registrata il '.$row['recorded_at'].'; decorrenza: '.$row['starts_on'].'; scadenza: '.$row['expires_on'].'.';
            $lines[] = 'Coperture concordate: '.$row['description'];
            $lines[] = 'Premio aggiuntivo concordato: '.$row['premium'].'. Il premio del contratto di base resta distinto.';
            $lines[] = '';
        }
        $lines[] = 'Il riepilogo non attesta un incasso o l’emissione di una quietanza.';
        $lines[] = ''; $lines[] = config('branding.wordmark');
        $sender = $this->configuration->sender();
        $recipient = ['address'=>$agency->email, 'name'=>$agency->name];
        $payload = ['from'=>$sender, 'recipient'=>$recipient, 'range'=>$range, 'policies'=>$rows, 'policy_ids'=>$policyIds, 'changes'=>$changeRows,
            'selection_keys'=>array_merge(array_map('strval',$policyIds), array_map(fn ($row) => 'change-'.$row['id'], $changeRows)),
            'subject'=>($allPolicies->contains('ad_horas', true) ? 'Ad horas - ' : '').'Riepilogo polizze e variazioni '.$range.' - '.config('branding.wordmark'), 'body'=>implode("\n",$lines)];
        $fingerprint = hash('sha256', json_encode([$payload, $allPolicies->sortBy('id')->map(fn ($p) => $this->policyFingerprint($p))->values()->all(),
            $changes->sortBy('id')->map(fn ($change) => $change->getAttributes())->values()->all()], JSON_THROW_ON_ERROR));
        return [$payload, $fingerprint];
    }

    public function send(AgencyCommunication $communication, User $user, ?int $attachmentRevision = null): AgencyCommunication
    {
        $configuration = $this->configuration->transport();
        $files = []; $deliveryPayload = [];
        try {
            $claimed = DB::transaction(function () use ($communication, $user, $attachmentRevision, &$files, &$deliveryPayload) {
                $message = AgencyCommunication::whereKey($communication->id)->lockForUpdate()->firstOrFail();
                abort_unless((int)$message->user_id === $user->id && $user->status === 'active' && $user->canManageSystem(), 403);
                if ($message->status === 'sent') return false;
                if ($message->status !== 'review') throw ValidationException::withMessages(['email'=>'Questo riepilogo è già stato confermato. Controlla il suo esito nello storico.']);
                if ($message->review_expires_at->isPast()) throw ValidationException::withMessages(['email'=>'Il controllo è scaduto: seleziona nuovamente le polizze e riapri il riepilogo.']);
                $documents = app(AgencyCommunicationDocuments::class);
                $documents->checkRevision($message, $attachmentRevision);
                /** Verify private copies before claiming SMTP delivery; missing files must not produce an uncertain send. */
                $files = $documents->checkedFiles($message);
                $deliveryPayload = $message->deliveryPayload();
                $policies = $this->lockedPolicies($message, $user);
                $agency = SupplierAgency::whereKey($message->supplier_agency_id)->lockForUpdate()->first();
                if (!$agency) throw ValidationException::withMessages(['email'=>'L’agenzia non è più disponibile.']);
                if(($message->payload['kind']??null)==='collections') return app(AgencyCollectionEmailWorkflow::class)->claim($message,$user,$agency,$policies);
                $ids = array_column($message->payload['policies'], 'id');
                $this->validateSelection($agency,$policies);
                $changeIds = array_column($message->payload['changes'] ?? [], 'id');
                $changes = InsurancePolicyChange::whereIn('id', $changeIds)->whereIn('result_policy_id', $ids)->orderBy('id')->lockForUpdate()->get();
                if ($changes->count() !== count($changeIds)) throw ValidationException::withMessages(['email'=>'Alcune variazioni non sono più disponibili. Riapri il riepilogo.']);
                foreach ($changes as $change) $change->setRelation('resultPolicy', $policies->firstWhere('id', $change->result_policy_id));
                $this->validateChanges($changes);
                $selectedPolicies = $policies->whereIn('id', $message->payload['policy_ids'] ?? $ids);
                [, $fingerprint] = $this->snapshot($agency, $selectedPolicies, $message->payload['range'], $changes);
                if (!hash_equals($message->fingerprint,$fingerprint)) throw ValidationException::withMessages(['email'=>'Polizze, cliente, destinatario o mittente sono cambiati dopo il controllo. Prepara un nuovo riepilogo.']);
                /** The unique claim is committed before SMTP; repeated clicks and overlapping selections cannot send the same policy version twice. */
                foreach ($selectedPolicies as $policy) {
                    $key = $this->deliveryKey($policy, $agency->email);
                    if (DB::table('agency_communication_policies')->where('delivery_key',$key)->exists()) throw ValidationException::withMessages(['policies'=>'Una delle polizze è già stata inviata o ha un invio in corso. Controlla lo storico e aggiorna la selezione.']);
                    DB::table('agency_communication_policies')->where('agency_communication_id',$message->id)->where('insurance_policy_id',$policy->id)->update(['delivery_key'=>$key]);
                }
                foreach ($changes as $change) {
                    $key = $this->changeDeliveryKey($change);
                    if (DB::table('agency_communication_changes')->where('delivery_key', $key)->exists()) throw ValidationException::withMessages(['policies'=>'Una delle appendici è già stata inviata o ha un invio in corso. Controlla lo storico.']);
                    DB::table('agency_communication_changes')->where('agency_communication_id', $message->id)->where('insurance_policy_change_id', $change->id)->update(['delivery_key'=>$key]);
                }
                $message->update(['status'=>'sending','reviewed_at'=>now(),'sending_at'=>now()]);
                $this->audit('agency_email_sending',$message,'Confermato l’invio di '.($selectedPolicies->count() + $changes->count()).' operazioni a '.$agency->name.'.');
                return true;
            }, 3);
        } catch (QueryException $exception) {
            if (in_array((string)$exception->getCode(), ['23000','23505'], true)) throw ValidationException::withMessages(['policies'=>'Invio già prenotato per una delle polizze. Aggiorna la selezione.']);
            throw $exception;
        }
        if (!$claimed) return $communication->fresh();
        $message = $communication->fresh();
        $previousAbort = ignore_user_abort(true);
        try {
            try { $messageId = $this->transport->send($deliveryPayload + ['_attachments'=>$files], $configuration); }
            catch (\Throwable $exception) {
                /** SMTP may accept DATA before a connection failure; no automatic retry can safely infer non-delivery. */
                $message->update(['status'=>'uncertain','error_code'=>'smtp_unconfirmed']);
                $this->audit('agency_email_uncertain',$message,'Il server di posta non ha confermato l’esito. Ripetizione automatica bloccata.');
                return $message;
            }
            $message->update(['status'=>'sent','sent_at'=>now(),'message_id'=>mb_substr($messageId,0,255),'error_code'=>null]);
            $this->audit('agency_email_sent',$message,'Email cumulativa accettata dal server di posta per '.$message->payload['recipient']['name'].': '.count($message->payload['policy_ids'] ?? $message->payload['policies']).' polizze, '.count($message->payload['changes'] ?? []).' aggiunte di coperture.');
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
            if (!$sent) DB::table('agency_communication_changes')->where('agency_communication_id',$message->id)->update(['delivery_key'=>null]);
            if (!$sent && ($message->payload['kind']??null)==='collections') DB::table('agency_communication_collections')->where('agency_communication_id',$message->id)->update(['delivery_key'=>null]);
            $this->audit('agency_email_resolved',$message,($sent ? 'Invio confermato' : 'Mancato invio confermato').' dopo verifica manuale: '.$note);
        });
    }
}
