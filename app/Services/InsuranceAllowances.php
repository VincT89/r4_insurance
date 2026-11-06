<?php

namespace App\Services;

use App\Models\{Client, InsuranceAllowance, InsuranceAllowanceRecovery, InsurancePolicy, InsuranceTitle, SupplierAgency};
use App\Support\Money;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

class InsuranceAllowances
{
    private function fail(string $message, string $field = 'allowance_amount'): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }

    private function locked(InsuranceTitle $title, callable $operation): mixed
    {
        Gate::authorize('ledger.manage');
        return DB::transaction(function () use ($title, $operation) {
            /** Lo stesso ordine cliente, polizza e titolo evita conflitti con incassi e unificazioni. */
            $client = Client::whereKey($title->policy->client_id)->lockForUpdate()->firstOrFail();
            $policy = InsurancePolicy::whereKey($title->insurance_policy_id)->lockForUpdate()->firstOrFail();
            abort_if($client->merged_into_id || (int)$policy->client_id !== (int)$client->id, 409, 'L’anagrafica è cambiata. Riapri la polizza.');
            Gate::authorize('updateRegistry', $client);
            $title = InsuranceTitle::whereKey($title->id)->lockForUpdate()->firstOrFail();
            $policy->setRelation('client', $client);
            $title->setRelation('policy', $policy);
            return $operation($title);
        });
    }

    public function record(InsuranceTitle $title, array $data): InsuranceAllowance
    {
        return $this->locked($title, function (InsuranceTitle $title) use ($data) {
            $fingerprint = hash('sha256', json_encode([$title->id, auth()->id(), $data], JSON_THROW_ON_ERROR));
            if ($existing = InsuranceAllowance::where('idempotency_key', $data['idempotency_key'])->first()) {
                if (!hash_equals($existing->request_fingerprint, $fingerprint)) $this->fail('Questa richiesta è già stata usata con dati diversi. Riapri il modulo.');
                return $existing;
            }
            if ($title->direction !== 'receipt' || $title->cancelled_at || $title->balance_cents < 1) {
                $this->fail('Registra l’abbuono su un titolo da incassare, ancora aperto.');
            }
            if ($data['expected_balance_cents'] !== $title->balance_cents) {
                $this->fail('Il residuo è cambiato. Riapri il modulo e controlla l’importo da incassare.');
            }
            if ($data['amount_cents'] < 1 || $data['amount_cents'] > $title->balance_cents) {
                $this->fail('L’abbuono deve essere maggiore di zero e non può superare il residuo del titolo.');
            }
            $bearer = $data['borne_by'];
            if (!array_key_exists($bearer, InsuranceAllowance::BEARERS)) $this->fail('Scegli chi sostiene l’abbuono.');
            $agency = $bearer === 'agency' ? SupplierAgency::where('status', 'active')->find($data['supplier_agency_id'] ?? null) : null;
            if ($bearer === 'agency' && !$agency) $this->fail('Scegli un’agenzia attiva che sostenga l’abbuono.');
            if ($bearer !== 'agency' && !empty($data['supplier_agency_id'])) $this->fail('Seleziona un’agenzia soltanto quando sostiene l’abbuono.');
            $before = $title->only(['amount_cents', 'collected_cents', 'allowance_cents']);
            $values = \Illuminate\Support\Arr::only($data, ['amount_cents', 'effective_on', 'borne_by', 'reason', 'reference', 'idempotency_key']);
            $values += [
                'supplier_agency_id'=>$agency?->id,
                'supplier_company_id'=>$bearer === 'company' ? $title->supplier_company_id : null,
                'bearer_name'=>match ($bearer) { 'company'=>$title->contract->company, 'agency'=>$agency->name, default=>'Nostra agenzia' },
                'request_fingerprint'=>$fingerprint, 'created_by'=>auth()->id(),
            ];
            $allowance = $title->allowances()->create($values);
            $title->update(['allowance_cents'=>(int)$title->allowance_cents + $allowance->amount_cents, 'settlement_revision'=>$title->settlement_revision+1]);
            $ledger = app(InsuranceLedger::class);
            $ledger->refresh($title->policy);
            $ledger->log($title->policy, 'allowance_recorded', 'registrato abbuono #'.$allowance->id.' di '.Money::format($allowance->amount_cents).' sul titolo #'.$title->id.', a carico di '.$allowance->bearer_label.'.',
                ['insurance_title_id'=>$title->id, 'insurance_allowance_id'=>$allowance->id, 'allowance_cents'=>$title->allowance_cents] + $allowance->only(['amount_cents','effective_on','borne_by','bearer_name','reason','reference']), $before);
            return $allowance;
        });
    }

    public function void(InsuranceAllowance $allowance, string $reason): void
    {
        $this->locked($allowance->title, function (InsuranceTitle $title) use ($allowance, $reason) {
            $allowance = InsuranceAllowance::whereKey($allowance->id)->lockForUpdate()->firstOrFail();
            if ($allowance->voided_at) return;
            if ($allowance->recovered_cents > 0 || $allowance->recoveries()->whereNull('voided_at')->exists()) {
                $this->fail('L’abbuono ha rientri registrati. Verifica e rettifica i rientri prima di stornarlo.');
            }
            if ($title->cancelled_at) $this->fail('Il titolo è annullato. Verifica la rettifica con l’admin prima di stornare l’abbuono.');
            $before = ['allowance_cents'=>$title->allowance_cents] + $allowance->only(['voided_at','void_reason']);
            $allowance->update(['voided_at'=>now(), 'voided_by'=>auth()->id(), 'void_reason'=>$reason]);
            if ($title->allowance_cents < $allowance->amount_cents) $this->fail('Il totale degli abbuoni non è coerente. Richiedi una verifica all’admin.');
            $title->update(['allowance_cents'=>(int)$title->allowance_cents - $allowance->amount_cents, 'settlement_revision'=>$title->settlement_revision+1]);
            app(DeadlineReminders::class)->reopenTitle($title);
            $ledger = app(InsuranceLedger::class);
            $ledger->refresh($title->policy);
            $ledger->log($title->policy, 'allowance_voided', 'stornato abbuono #'.$allowance->id.' sul titolo #'.$title->id.': '.$reason,
                ['insurance_title_id'=>$title->id, 'insurance_allowance_id'=>$allowance->id, 'allowance_cents'=>$title->allowance_cents] + $allowance->only(['amount_cents','effective_on','borne_by','bearer_name','voided_at','void_reason']), $before);
        });
    }

    public function recordRecovery(InsuranceAllowance $allowance, array $data): InsuranceAllowanceRecovery
    {
        return $this->locked($allowance->title, function (InsuranceTitle $title) use ($allowance, $data) {
            $allowance = InsuranceAllowance::whereKey($allowance->id)->where('insurance_title_id', $title->id)->lockForUpdate()->firstOrFail();
            $fingerprint = hash('sha256', json_encode([$allowance->id, auth()->id(), $data], JSON_THROW_ON_ERROR));
            $checkExisting = function (InsuranceAllowanceRecovery $existing) use ($fingerprint) {
                if (!hash_equals($existing->request_fingerprint, $fingerprint)) $this->fail('Questa richiesta è già stata usata con dati diversi. Riapri l’abbuono.', 'recovery_amount');
                return $existing;
            };
            if ($existing = InsuranceAllowanceRecovery::where('idempotency_key', $data['idempotency_key'])->first()) return $checkExisting($existing);
            if ($allowance->borne_by !== 'agency' || $allowance->voided_at) $this->fail('Registra il rientro su un abbuono attivo a carico di un’altra agenzia.', 'recovery_amount');
            if (!hash_equals($allowance->recoveryFingerprint(), $data['fingerprint'])) $this->fail('Il recupero è cambiato. Ricarica l’abbuono e controlla il residuo.', 'recovery_amount');
            if ($data['amount_cents'] < 1 || $data['amount_cents'] > $allowance->recoverable_cents) $this->fail('Il rientro deve essere maggiore di zero e non può superare l’importo ancora da recuperare.', 'recovery_amount');
            if (!array_key_exists($data['method'], InsuranceAllowanceRecovery::METHODS)) $this->fail('Scegli la modalità del rientro.', 'recovery_method');
            $before = ['recovered_cents'=>$allowance->recovered_cents, 'recoverable_cents'=>$allowance->recoverable_cents];
            $values = \Illuminate\Support\Arr::only($data, ['amount_cents', 'received_on', 'method', 'reference', 'notes']);
            /** La chiave unica protegge anche da invii simultanei riferiti ad abbuoni diversi. */
            $recovery = InsuranceAllowanceRecovery::firstOrCreate(['idempotency_key'=>$data['idempotency_key']], $values + [
                'insurance_allowance_id'=>$allowance->id, 'request_fingerprint'=>$fingerprint, 'created_by'=>auth()->id(),
            ]);
            if (!$recovery->wasRecentlyCreated) return $checkExisting($recovery);
            $allowance->update(['recovered_cents'=>$allowance->recovered_cents + $recovery->amount_cents, 'recovery_revision'=>$allowance->recovery_revision + 1]);
            /** Il rientro regola il credito verso l’agenzia e non il pagamento o la copertura del cliente. */
            app(InsuranceLedger::class)->log($title->policy, 'allowance_recovery_recorded',
                'registrato rientro #'.$recovery->id.' di '.Money::format($recovery->amount_cents).' per l’abbuono #'.$allowance->id.' a carico di '.$allowance->bearer_name.'.',
                ['insurance_title_id'=>$title->id, 'insurance_allowance_id'=>$allowance->id, 'insurance_allowance_recovery_id'=>$recovery->id,
                    'bearer_name'=>$allowance->bearer_name, 'recovered_cents'=>$allowance->recovered_cents, 'recoverable_cents'=>$allowance->recoverable_cents,
                    'recovery_method'=>$recovery->method] + $recovery->only(['amount_cents', 'received_on', 'reference', 'notes']), $before);
            return $recovery;
        });
    }

    public function voidRecovery(InsuranceAllowanceRecovery $recovery, string $reason): void
    {
        $this->locked($recovery->allowance->title, function (InsuranceTitle $title) use ($recovery, $reason) {
            $allowance = InsuranceAllowance::whereKey($recovery->insurance_allowance_id)->where('insurance_title_id', $title->id)->lockForUpdate()->firstOrFail();
            $recovery = InsuranceAllowanceRecovery::whereKey($recovery->id)->where('insurance_allowance_id', $allowance->id)->lockForUpdate()->firstOrFail();
            if ($recovery->voided_at) return;
            if ($allowance->voided_at || $allowance->recovered_cents < $recovery->amount_cents) $this->fail('Il totale dei rientri non è coerente. Richiedi una verifica all’admin.', 'recovery_void_reason');
            $before = ['recovered_cents'=>$allowance->recovered_cents, 'recoverable_cents'=>$allowance->recoverable_cents];
            $recovery->update(['voided_at'=>now(), 'voided_by'=>auth()->id(), 'void_reason'=>$reason]);
            $allowance->update(['recovered_cents'=>$allowance->recovered_cents - $recovery->amount_cents, 'recovery_revision'=>$allowance->recovery_revision + 1]);
            app(InsuranceLedger::class)->log($title->policy, 'allowance_recovery_voided', 'stornato rientro #'.$recovery->id.' dell’abbuono #'.$allowance->id.': '.$reason,
                ['insurance_title_id'=>$title->id, 'insurance_allowance_id'=>$allowance->id, 'insurance_allowance_recovery_id'=>$recovery->id,
                    'bearer_name'=>$allowance->bearer_name, 'recovered_cents'=>$allowance->recovered_cents, 'recoverable_cents'=>$allowance->recoverable_cents]
                    + $recovery->only(['amount_cents', 'received_on', 'voided_at', 'void_reason']), $before);
        });
    }

    public function confirmCoverage(InsuranceTitle $title, string $fingerprint, string $note): void
    {
        $this->locked($title, function (InsuranceTitle $title) use ($fingerprint, $note) {
            if (!$title->affects_coverage || $title->direction !== 'receipt' || $title->cancelled_at || $title->balance_cents !== 0 || $title->allowance_cents < 1) {
                $this->fail('Conferma la copertura solo dopo aver saldato il titolo con abbuono.');
            }
            if (!hash_equals($title->coverageConfirmationFingerprint(), $fingerprint)) $this->fail('I dati del titolo sono cambiati. Riapri la polizza prima di confermare la copertura.');
            if ($title->coverage_confirmation_valid) return;
            $before = $title->only(['coverage_confirmed_at','coverage_confirmed_by','coverage_confirmation_note']);
            $title->update(['coverage_confirmed_at'=>now(), 'coverage_confirmed_by'=>auth()->id(), 'coverage_confirmation_note'=>$note, 'coverage_confirmation_fingerprint'=>$fingerprint]);
            $ledger = app(InsuranceLedger::class);
            $ledger->refresh($title->policy);
            $ledger->log($title->policy, 'allowance_coverage_confirmed', 'confermata separatamente la copertura del titolo #'.$title->id.' saldato con abbuono: '.$note,
                ['insurance_title_id'=>$title->id] + $title->only(['coverage_from','coverage_to','coverage_confirmed_at','coverage_confirmed_by','coverage_confirmation_note']), $before);
        });
    }

    public function revokeCoverage(InsuranceTitle $title, string $fingerprint, string $reason): void
    {
        $this->locked($title, function (InsuranceTitle $title) use ($fingerprint, $reason) {
            if (!$title->coverage_confirmation_valid) $this->fail('La conferma non è più attuale. Riapri il titolo.');
            if (!hash_equals($title->coverageConfirmationFingerprint(), $fingerprint)) $this->fail('I dati del titolo sono cambiati. Riapri il titolo.');
            $before = $title->only(['coverage_confirmed_at','coverage_confirmed_by','coverage_confirmation_note']);
            $title->update(['coverage_confirmed_at'=>null, 'coverage_confirmed_by'=>null, 'coverage_confirmation_note'=>null, 'coverage_confirmation_fingerprint'=>null]);
            $ledger = app(InsuranceLedger::class);
            $ledger->refresh($title->policy);
            $ledger->log($title->policy, 'allowance_coverage_revoked', 'annullata la conferma di copertura del titolo #'.$title->id.': '.$reason,
                ['insurance_title_id'=>$title->id, 'reason'=>$reason], $before);
        });
    }
}
