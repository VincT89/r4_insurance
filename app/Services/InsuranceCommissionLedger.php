<?php

namespace App\Services;

use App\Models\{AgencyCommissionRate, InsuranceCommission, InsuranceCommissionMovement, InsurancePolicy, InsuranceTitle};
use App\Support\CommissionMath;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

class InsuranceCommissionLedger
{
    private function fail(string $message): never { throw ValidationException::withMessages(['commission'=>$message]); }

    private function locked(InsuranceTitle $title, callable $operation): mixed
    {
        return DB::transaction(function () use ($title, $operation) {
            /** Use the same policy, title, commission lock order as collections and reversals. */
            InsurancePolicy::whereKey($title->insurance_policy_id)->lockForUpdate()->firstOrFail();
            $title = InsuranceTitle::whereKey($title->id)->lockForUpdate()->firstOrFail();
            $commission = InsuranceCommission::where('insurance_title_id', $title->id)->lockForUpdate()->first();
            return $operation($title, $commission);
        });
    }

    private function checkVersion(?InsuranceCommission $commission, int $version): void
    {
        if (($commission?->version ?? -1) !== $version) $this->fail('I dati sono cambiati. Ricarica la scheda e controlla gli importi prima di confermare.');
    }

    private function movement(InsuranceCommission $commission, string $kind, int $amount, string $date, string $reason, array $extra = []): InsuranceCommissionMovement
    {
        return $commission->movements()->create($extra + ['kind'=>$kind, 'amount_cents'=>$amount, 'effective_on'=>$date, 'reason'=>$reason, 'created_by'=>auth()->id(), 'created_at'=>now()]);
    }

    private function log(InsuranceTitle $title, string $action, string $description, array $values, array $before = []): void
    {
        app(InsuranceLedger::class)->log($title->policy, $action, $description, ['insurance_title_id'=>$title->id] + $values, $before);
    }

    public function sync(InsuranceTitle $title, ?string $eventOn = null, string $reason = 'Aggiornamento del titolo'): InsuranceCommission
    {
        return $this->locked($title, fn ($title, $commission) => $this->synchronize($title, $commission, $eventOn ?? today()->toDateString(), $reason));
    }

    private function synchronize(InsuranceTitle $title, ?InsuranceCommission $commission, string $eventOn, string $reason): InsuranceCommission
    {
        $commission ??= InsuranceCommission::create(['insurance_title_id'=>$title->id]);
        if ($title->cancelled_at) {
            if ($commission->expected_cents > 0) $this->movement($commission, 'forecast', -$commission->expected_cents, $title->cancelled_at->toDateString(), 'Storno previsione: '.$title->cancellation_reason);
            if ($commission->accrued_cents > 0) $this->movement($commission, 'accrual', -$commission->accrued_cents, $title->cancelled_at->toDateString(), 'Storno maturazione: '.$title->cancellation_reason);
            $before = $commission->only(['expected_cents', 'accrued_cents', 'settled_cents']);
            $commission->fill(['expected_cents'=>0, 'accrued_cents'=>0, 'incomplete_reason'=>null]);
            if ($commission->isDirty()) {
                $commission->version++; $commission->save();
                $this->log($title, 'commission_adjusted', 'stornata la provvigione del titolo annullato; le liquidazioni restano da riconciliare.', $commission->only(['expected_cents', 'accrued_cents', 'settled_cents']), $before);
            }
            return $commission;
        }
        $initial = !$commission->locked_at;
        if ($initial && !$this->freeze($title, $commission)) return $commission;
        $rule = $commission->rule_snapshot;
        if ($initial) {
            $this->movement($commission, 'forecast', $commission->expected_cents, $title->coverage_from->toDateString(), 'Previsione sulla rata; condizioni #'.$rule['schedule_id'].'.');
            if ($rule['accrual_event'] === 'title') {
                $this->accrualDelta($title, $commission, $commission->expected_cents, $title->created_at->toDateString(), 'Maturazione all’emissione del titolo.');
            } elseif ($rule['accrual_event'] === 'collection') {
                /** Rebuild actual collection and reversal dates when an older title is first calculated. */
                $events = [];
                foreach ($title->collections()->orderBy('id')->get() as $payment) {
                    $events[] = ['date'=>$payment->received_on->toDateString(), 'order'=>0, 'id'=>$payment->id, 'amount'=>$payment->amount_cents];
                    if ($payment->voided_at) $events[] = ['date'=>$payment->voided_at->toDateString(), 'order'=>1, 'id'=>$payment->id, 'amount'=>-$payment->amount_cents];
                }
                usort($events, fn ($a, $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);
                $paid = 0;
                foreach ($events as $event) {
                    $paid += $event['amount'];
                    $target = $this->collectionTarget($commission, min($title->amount_cents, max(0, $paid)), $title->amount_cents);
                    $this->accrualDelta($title, $commission, $target, $event['date'], ($event['amount'] < 0 ? 'Storno' : 'Incasso').' #'.$event['id'].' del premio.');
                }
            }
            $this->log($title, 'commission_calculated', 'calcolata la provvigione del titolo con condizioni conservate nello storico.', ['insurance_commission_id'=>$commission->id, 'rule'=>$rule, 'base_cents'=>$commission->base_cents, 'expected_cents'=>$commission->expected_cents]);
        } elseif ($rule['accrual_event'] === 'collection') {
            $target = $this->collectionTarget($commission, min($title->amount_cents, max(0, $title->collected_cents)), $title->amount_cents);
            if ($target > $commission->accrued_cents && $rule['collection_mode'] === 'full') {
                $eventOn = $title->collections()->whereNull('voided_at')->max('received_on') ?? $eventOn;
            }
            $this->accrualDelta($title, $commission, $target, $eventOn, $reason);
        }
        return $commission;
    }

    private function incomplete(InsuranceCommission $commission, string $reason): bool
    {
        $commission->fill(['incomplete_reason'=>$reason, 'expected_cents'=>null]);
        if ($commission->isDirty()) { $commission->version++; $commission->save(); }
        return false;
    }

    private function freeze(InsuranceTitle $title, InsuranceCommission $commission): bool
    {
        $policy = $title->policy;
        if (!$policy->supplier_agency_id || !$policy->supplier_company_id || !$policy->insurance_branch_id) return $this->incomplete($commission, 'Completa agenzia, compagnia e ramo della polizza.');
        $rates = AgencyCommissionRate::with('schedule')->where('insurance_branch_id', $policy->insurance_branch_id)
            ->where('insurance_subbranch_id', $policy->insurance_subbranch_id)->whereHas('schedule', fn ($q) => $q->current()
                ->where('supplier_agency_id', $policy->supplier_agency_id)->where('supplier_company_id', $policy->supplier_company_id)
                ->whereDate('valid_from', '<=', $title->coverage_from)->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $title->coverage_from)))
            ->get();
        if ($rates->count() !== 1) return $this->incomplete($commission, $rates->isEmpty() ? 'Mancano condizioni per questo mandato, ramo/sottoramo e data di inizio copertura della rata.' : 'Più condizioni corrispondono alla rata: correggi la sovrapposizione.');
        $rate = $rates->sole();
        $units = match ($title->kind) { 'emission'=>$rate->issue_rate_units, 'renewal'=>$rate->renewal_rate_units, 'quietance'=>$rate->quietance_rate_units, default=>null };
        $event = $rate->accrual_event ?? $rate->schedule->accrual_event;
        $mode = $rate->collection_mode ?? $rate->schedule->collection_mode;
        $commission->agency_commission_rate_id = $rate->id;
        if (!$rate->basis || $units === null) return $this->incomplete($commission, 'Definisci base e aliquota per '.mb_strtolower(InsuranceTitle::KINDS[$title->kind] ?? 'questo titolo').'.');
        if (!$event || ($event === 'collection' && !$mode)) return $this->incomplete($commission, 'Definisci la maturazione e, se all’incasso, il trattamento dei pagamenti parziali.');
        $base = $rate->basis === 'gross_premium' ? $title->amount_cents : $commission->base_cents;
        if ($base === null || ($rate->basis !== 'gross_premium' && !$commission->base_reference)) return $this->incomplete($commission, 'Inserisci la base provvigionale della singola rata e il riferimento al documento del mandante.');
        if ($rate->basis === 'net_premium' && $base > $title->amount_cents) return $this->incomplete($commission, 'Il premio imponibile della rata non può superare il premio totale.');
        if ($base > 999999999999) return $this->incomplete($commission, 'L’importo supera il limite ammesso.');
        $commission->fill(['base_cents'=>$base, 'expected_cents'=>CommissionMath::percentage($base, $units), 'locked_at'=>now(), 'incomplete_reason'=>null,
            'rule_snapshot'=>['schedule_id'=>$rate->schedule->id, 'rate_id'=>$rate->id, 'mandate_reference'=>$rate->schedule->mandate_reference, 'basis'=>$rate->basis, 'basis_note'=>$rate->basis_note, 'rate_units'=>$units, 'kind'=>$title->kind, 'accrual_event'=>$event, 'collection_mode'=>$mode, 'condition_date'=>$title->coverage_from->toDateString()]]);
        $commission->version++; $commission->save();
        return true;
    }

    private function collectionTarget(InsuranceCommission $commission, int $paid, int $total): int
    {
        return $commission->rule_snapshot['collection_mode'] === 'full'
            ? ($paid === $total ? $commission->expected_cents : 0)
            : CommissionMath::proportion($commission->expected_cents, $paid, $total);
    }

    private function accrualDelta(InsuranceTitle $title, InsuranceCommission $commission, int $target, string $date, string $reason): void
    {
        $delta = $target - $commission->accrued_cents;
        if (!$delta) return;
        $this->movement($commission, 'accrual', $delta, $date, $reason);
        $before = $commission->accrued_cents;
        $commission->accrued_cents = $target; $commission->version++; $commission->save();
        $this->log($title, 'commission_accrued', $reason, ['insurance_commission_id'=>$commission->id, 'accrued_cents'=>$target, 'delta_cents'=>$delta], ['accrued_cents'=>$before]);
    }

    public function prepare(InsuranceTitle $title, array $data): InsuranceCommission
    {
        Gate::authorize('system.admin');
        return $this->locked($title, function ($title, $commission) use ($data) {
            $this->checkVersion($commission, $data['version']);
            if ($commission?->locked_at) $this->fail('La provvigione usa già condizioni storicizzate. Una nuova aliquota non modifica i movimenti precedenti.');
            $commission ??= InsuranceCommission::create(['insurance_title_id'=>$title->id]);
            if (array_key_exists('base_cents', $data)) {
                $commission->fill(['base_cents'=>$data['base_cents'], 'base_reference'=>$data['base_reference']]);
                $commission->version++; $commission->save();
                $this->log($title, 'commission_base_recorded', 'registrata la base provvigionale della rata.', $commission->only(['id', 'base_cents', 'base_reference']));
            }
            return $this->synchronize($title, $commission, today()->toDateString(), 'Calcolo richiesto dall’amministratore.');
        });
    }

    public function record(InsuranceTitle $title, string $kind, array $data): InsuranceCommissionMovement
    {
        Gate::authorize('system.admin');
        return $this->locked($title, function ($title, $commission) use ($kind, $data) {
            $fingerprint = hash('sha256', json_encode([$title->id, auth()->id(), $kind, $data['amount_cents'], $data['effective_on'], $data['reference'], $data['reason']], JSON_THROW_ON_ERROR));
            if ($existing = InsuranceCommissionMovement::where('idempotency_key', $data['idempotency_key'])->first()) {
                if (!hash_equals($existing->request_fingerprint, $fingerprint)) $this->fail('Questa richiesta è già stata usata con dati diversi. Riapri il modulo.');
                return $existing;
            }
            $this->checkVersion($commission, $data['version']);
            if (!$commission?->locked_at || $title->cancelled_at) $this->fail('Completa le condizioni di una rata non annullata prima di registrare movimenti.');
            $amount = $data['amount_cents'];
            if ($kind === 'accrual') {
                if ($commission->rule_snapshot['accrual_event'] !== 'confirmation') $this->fail('La maturazione di questa rata segue gli eventi automatici del mandato.');
                $limit = $commission->expected_cents - $commission->accrued_cents;
            } elseif ($kind === 'settlement') {
                $asOf = (int)$commission->movements()->whereDate('effective_on', '<=', $data['effective_on'])->selectRaw("COALESCE(SUM(CASE WHEN kind='accrual' THEN amount_cents WHEN kind='settlement' THEN -amount_cents ELSE 0 END),0) as balance")->value('balance');
                $limit = min($commission->accrued_cents - $commission->settled_cents, $asOf);
            } else $this->fail('Tipo di movimento non valido.');
            if ($amount < 1 || $amount > $limit) $this->fail('L’importo supera la disponibilità alla data indicata. Controlla maturazioni e liquidazioni già registrate.');
            $movement = $this->movement($commission, $kind, $amount, $data['effective_on'], $data['reason'], ['reference'=>$data['reference'], 'idempotency_key'=>$data['idempotency_key'], 'request_fingerprint'=>$fingerprint]);
            $column = $kind === 'accrual' ? 'accrued_cents' : 'settled_cents';
            $before = $commission->$column;
            $commission->$column += $amount; $commission->version++; $commission->save();
            $this->log($title, $kind === 'accrual' ? 'commission_confirmed' : 'commission_settled', $kind === 'accrual' ? 'registrata la conferma di maturazione del mandante.' : 'registrata la liquidazione della provvigione.', ['movement_id'=>$movement->id, $column=>$commission->$column, 'reference'=>$data['reference'], 'effective_on'=>$data['effective_on']], [$column=>$before]);
            return $movement;
        });
    }

    public function reverse(InsuranceTitle $title, InsuranceCommissionMovement $movement, array $data): void
    {
        Gate::authorize('system.admin');
        $this->locked($title, function ($title, $commission) use ($movement, $data) {
            abort_unless($commission && $movement->insurance_commission_id === $commission->id, 404);
            $this->checkVersion($commission, $data['version']);
            $movement = $commission->movements()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if ($movement->amount_cents <= 0 || $movement->reversal()->exists() || !$movement->idempotency_key) $this->fail('Questo movimento non può essere stornato manualmente.');
            if ($data['effective_on'] < $movement->effective_on->toDateString()) $this->fail('La data dello storno non può precedere il movimento originale.');
            $column = $movement->kind === 'accrual' ? 'accrued_cents' : 'settled_cents';
            if ($commission->$column < $movement->amount_cents) $this->fail('Lo storno supera il saldo corrente. Controlla i movimenti collegati.');
            $reverse = $this->movement($commission, $movement->kind, -$movement->amount_cents, $data['effective_on'], $data['reason'], ['reversal_of_id'=>$movement->id, 'reference'=>$movement->reference]);
            $before = $commission->$column;
            $commission->$column -= $movement->amount_cents; $commission->version++; $commission->save();
            $this->log($title, 'commission_reversed', 'stornato il movimento provvigionale #'.$movement->id.'. '.$data['reason'], ['movement_id'=>$reverse->id, 'reversal_of_id'=>$movement->id, $column=>$commission->$column], [$column=>$before]);
        });
    }
}
