<?php

namespace App\Services;

use App\Models\{Client, InboundEmail, User};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

class InboundEmailAssociation
{
    public function save(InboundEmail $email, User $actor, array $values): void
    {
        Gate::forUser($actor)->authorize('communications.manage');
        DB::transaction(function () use ($email, $actor, $values) {
            $client = $case = $policy = null;
            if ($values['target'] !== 'unassigned') {
                $client = Client::current()->visibleTo($actor)->whereKey($values['client_id'])->lockForUpdate()->first();
                if (!$client) throw ValidationException::withMessages(['client_id'=>'Scegli un cliente corrente e accessibile.']);
                Gate::forUser($actor)->authorize('updateRegistry', $client);
                if ($values['target'] === 'quote') {
                    $case = $client->quoteCases()->whereKey($values['insurance_quote_case_id'])->lockForUpdate()->first();
                    if (!$case) throw ValidationException::withMessages(['insurance_quote_case_id'=>'La pratica non appartiene al cliente scelto.']);
                }
                if ($values['target'] === 'policy') {
                    $policy = $client->policies()->whereKey($values['insurance_policy_id'])->lockForUpdate()->first();
                    if (!$policy) throw ValidationException::withMessages(['insurance_policy_id'=>'La polizza non appartiene al cliente scelto.']);
                }
            }
            $locked = InboundEmail::whereKey($email->id)->lockForUpdate()->firstOrFail();
            if ($locked->version !== (int) $values['version']) throw ValidationException::withMessages(['association'=>'L’associazione è cambiata nel frattempo. Riapri l’email prima di salvare.']);
            $fields = ['status','client_id','insurance_quote_case_id','insurance_policy_id','document_kind','version'];
            $before = $locked->only($fields);
            /** Si collega il messaggio originale; non si approvano offerte e non si registrano incassi. */
            $locked->update(['status'=>$client ? 'assigned' : 'unassigned', 'client_id'=>$client?->id, 'insurance_quote_case_id'=>$case?->id,
                'insurance_policy_id'=>$policy?->id, 'document_kind'=>$values['document_kind'], 'association_note'=>$values['association_note'] ?? null,
                'assigned_by'=>$actor->id, 'assigned_at'=>now(), 'version'=>$locked->version + 1]);
            app(AuditLogService::class)->log('inbound_email_associated', $locked, oldValues:$before, newValues:$locked->only($fields),
                description:'Aggiornata l’associazione dell’email '.$locked->public_id.'. Originale e allegati conservati.', userId:$actor->id);
        }, 3);
    }
}
