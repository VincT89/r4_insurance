<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class InsurancePaymentSummary
{
    public function forQuery(Builder $query): array
    {
        /** One aggregate query counts residuals, never full premiums or cancelled instalments. */
        $rows = (clone $query)->inPaymentState('open')->reorder()->selectRaw(
            "CASE WHEN due_on < ? THEN 'overdue' WHEN collected_cents > 0 THEN 'partial' WHEN due_on < ? THEN 'due_today' ELSE 'future' END AS payment_group,
            COUNT(*) AS quantity, SUM(amount_cents - collected_cents) AS residual_cents",
            [today()->toDateString(), today()->addDay()->toDateString()]
        )->groupBy('payment_group')->get()->keyBy('payment_group');

        $summary = [];
        foreach (['future', 'partial', 'due_today', 'overdue'] as $state) {
            $summary[$state] = ['count'=>(int) ($rows[$state]->quantity ?? 0), 'balance_cents'=>(int) ($rows[$state]->residual_cents ?? 0)];
        }
        return $summary;
    }
}
