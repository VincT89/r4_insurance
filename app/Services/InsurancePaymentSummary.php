<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class InsurancePaymentSummary
{
    public function forQuery(Builder $query): array
    {
        $withinContract = \App\Models\InsuranceTitle::query()->withinContract()->select('insurance_titles.id');
        /** Mutually exclusive due-date groups match the operational filters and retain unpaid history. */
        $rows = (clone $query)->where('direction','receipt')->inPaymentState('open')->reorder()->selectRaw(
            "CASE WHEN due_on < ? THEN 'overdue' WHEN insurance_titles.id NOT IN (".$withinContract->toSql().") THEN 'beyond_contract' WHEN due_on < ? THEN 'due_soon' ELSE 'future' END AS payment_group,
            COUNT(*) AS quantity, SUM(amount_cents - collected_cents) AS residual_cents",
            [today()->toDateString(), ...$withinContract->getBindings(), today()->addDays(16)->toDateString()]
        )->groupBy('payment_group')->get()->keyBy('payment_group');

        $summary = [];
        foreach (['overdue', 'due_soon', 'future'] as $state) {
            $summary[$state] = ['count'=>(int) ($rows[$state]->quantity ?? 0), 'balance_cents'=>(int) ($rows[$state]->residual_cents ?? 0)];
        }
        return $summary;
    }
}
