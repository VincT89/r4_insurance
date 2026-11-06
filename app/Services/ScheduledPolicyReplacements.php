<?php

namespace App\Services;

use App\Models\{Client, InsurancePolicy, InsurancePolicyChange, InsuranceTitle, PolicyReplacementSchedule};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;

class ScheduledPolicyReplacements
{
    public function record(InsurancePolicyChange $change): void
    {
        if ($change->kind === 'replacement' && $change->effective_on->gt(today())) {
            PolicyReplacementSchedule::create(['insurance_policy_change_id'=>$change->id, 'effective_on'=>$change->effective_on]);
        }
    }

    public function activateDue(): int
    {
        $activated = 0;
        foreach (PolicyReplacementSchedule::where('state','pending')->where('effective_on','<',today()->addDay()->toDateString())->orderBy('id')->limit(100)->get() as $schedule) {
            $activated += (int) $this->activate($schedule);
        }
        return $activated;
    }

    private function lockedPolicies(PolicyReplacementSchedule $schedule): array
    {
        $change = $schedule->change;
        abort_unless($change->kind === 'replacement' && $change->source_policy_id !== $change->result_policy_id, 409);
        $client = Client::whereKey($change->sourcePolicy->client_id)->lockForUpdate()->firstOrFail();
        $policies = $client->policies()->whereIn('id',[$change->source_policy_id,$change->result_policy_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_unless($policies->count() === 2, 409);
        return [$change, $policies[$change->source_policy_id], $policies[$change->result_policy_id]];
    }

    public function activate(PolicyReplacementSchedule $schedule): bool
    {
        return DB::transaction(function () use ($schedule) {
            [$change, $source, $result] = $this->lockedPolicies($schedule);
            $schedule = PolicyReplacementSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            if ($schedule->state !== 'pending' || $schedule->effective_on->gt(today())) return false;
            $titles = $source->titles()->whereIn('id',$change->snapshot['cancel_titles'] ?? [])->orderBy('id')->lockForUpdate()->get();
            /** Apply only the unpaid remainder authorized in the immutable contract event; intervening payments remain intact. */
            foreach ($titles as $title) {
                if ($title->cancelled_at || $title->balance_cents < 1) continue;
                $amount = $title->balance_cents;
                $title->update(['cancelled_residual_cents'=>$amount, 'cancelled_at'=>now(), 'cancellation_reason'=>$change->snapshot['cancellation_reason']]);
                app(InsuranceCommissionLedger::class)->sync($title);
                $this->log($source,'title_residual_cancelled','Annullato il residuo del titolo #'.$title->id.' alla decorrenza della sostituzione.', $change, ['insurance_title_id'=>$title->id,'cancelled_residual_cents'=>$amount]);
            }
            $source->forceFill(['status'=>'replaced', 'closed_at'=>$change->effective_on])->save();
            app(InsuranceLedger::class)->refresh($source);
            $schedule->update(['state'=>'active', 'activated_at'=>now()]);
            $this->log($source,'policy_contract_changed','Sostituzione entrata in vigore dal '.$change->effective_on->format('d/m/Y').'; nuova polizza '.$result->number.'.', $change);
            return true;
        });
    }

    public function cancel(PolicyReplacementSchedule $schedule, string $reason): void
    {
        Gate::authorize('ledger.manage');
        Gate::authorize('updateRegistry', $schedule->change->sourcePolicy->client);
        DB::transaction(function () use ($schedule, $reason) {
            [$change, $source, $result] = $this->lockedPolicies($schedule);
            $schedule = PolicyReplacementSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            if ($schedule->state === 'cancelled') return;
            if ($schedule->state !== 'pending' || !$schedule->effective_on->gt(today())) throw ValidationException::withMessages(['schedule'=>'La sostituzione è già entrata in vigore. Conserva lo storico.']);
            $titles = InsuranceTitle::where(fn($q)=>$q->where('insurance_policy_id',$result->id)->orWhere('insurance_policy_change_id',$change->id))->orderBy('id')->lockForUpdate()->get();
            if ($titles->contains(fn($title)=>$title->collected_cents > 0 || $title->allowance_cents > 0)) throw ValidationException::withMessages(['schedule'=>'La nuova polizza ha pagamenti o abbuoni registrati. Storna prima i movimenti errati, conservando lo storico.']);
            foreach ($titles as $title) if (!$title->cancelled_at) app(InsuranceLedger::class)->cancel($title,$reason);
            $result->forceFill(['status'=>'cancelled','closed_at'=>now()])->save();
            $schedule->update(['state'=>'cancelled','cancelled_at'=>now(),'cancelled_by'=>auth()->id(),'cancellation_reason'=>$reason]);
            PolicyFormReminder::clear($result);
            $this->log($source,'policy_contract_changed','Programmazione della sostituzione annullata: '.$reason, $change);
        });
    }

    private function log(InsurancePolicy $policy, string $action, string $description, InsurancePolicyChange $change, array $values = []): void
    {
        $policy->client->auditLogs()->create(['user_id'=>auth()->id(),'action'=>$action,'description'=>'Polizza '.$policy->number.': '.$description,
            'new_values'=>['insurance_policy_id'=>$policy->id,'policy_change_id'=>$change->id,'result_policy_id'=>$change->result_policy_id]+$values,'created_at'=>now()]);
    }
}
