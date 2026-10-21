<?php

namespace App\Domain\Core\Queries;

use App\Models\{Client, InsurancePolicy};
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\{DB, Gate};

class InsuranceCommissionQuery
{
    public function positions(): Builder
    {
        return DB::table('insurance_titles as t')
            ->join('insurance_policies as p', 'p.id', '=', 't.insurance_policy_id')
            ->join('clients as c', 'c.id', '=', 'p.client_id')
            ->leftJoin('supplier_agencies as a', 'a.id', '=', 'p.supplier_agency_id')
            ->leftJoin('insurance_commissions as v', 'v.insurance_title_id', '=', 't.id');
    }

    /** Read stored balances once per title; joining movements here would multiply historical amounts. */
    public static function balancesSql(): string
    {
        return "COUNT(*) as rows_count,
            COALESCE(SUM(CASE WHEN t.cancelled_at IS NULL AND v.locked_at IS NULL THEN 1 ELSE 0 END),0) as incomplete,
            COALESCE(SUM(v.expected_cents),0) as expected,
            COALESCE(SUM(v.accrued_cents),0) as accrued,
            COALESCE(SUM(v.settled_cents),0) as settled,
            COALESCE(SUM(CASE WHEN v.accrued_cents > v.settled_cents THEN v.accrued_cents-v.settled_cents ELSE 0 END),0) as payable,
            COALESCE(SUM(CASE WHEN v.settled_cents > v.accrued_cents THEN v.settled_cents-v.accrued_cents ELSE 0 END),0) as reconcile";
    }

    public function summary(Client $client, ?InsurancePolicy $policy = null): object
    {
        Gate::authorize('system.admin');
        Gate::authorize('view', $client);
        abort_if($policy && (int) $policy->client_id !== (int) $client->id, 404);

        return $this->positions()->where('p.client_id', $client->id)
            ->when($policy, fn (Builder $query) => $query->where('p.id', $policy->id))
            ->selectRaw(self::balancesSql())->first();
    }
}
