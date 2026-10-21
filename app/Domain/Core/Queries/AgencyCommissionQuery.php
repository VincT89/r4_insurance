<?php

namespace App\Domain\Core\Queries;

use App\Models\{AgencyCommissionRate, SupplierAgency};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgencyCommissionQuery
{
    public function filters(Request $request): array
    {
        return $request->validate([
            'company'=>['nullable','integer'], 'branch'=>['nullable','integer'],
            'subbranch'=>['nullable','integer'], 'on'=>['nullable','date_format:Y-m-d'],
            'state'=>['nullable','in:current,history,voided,all'],
            'basis'=>['nullable',Rule::in(array_keys(AgencyCommissionRate::BASES))],
        ]);
    }

    public function forAgency(SupplierAgency $supplier, array $filters): Builder
    {
        $query = AgencyCommissionRate::with(['schedule.company','branch','subbranch'])
            ->whereHas('schedule', function ($query) use ($supplier, $filters) {
                $query->where('supplier_agency_id', $supplier->id);
                $state = $filters['state'] ?? 'current';
                if ($state === 'current') $query->current();
                elseif ($state === 'history') $query->whereNotNull('superseded_at');
                elseif ($state === 'voided') $query->whereNotNull('voided_at');
                if ($filters['company'] ?? null) $query->where('supplier_company_id', $filters['company']);
                if ($day = $filters['on'] ?? null) {
                    $query->whereDate('valid_from', '<=', $day)
                        ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $day));
                }
            });
        foreach (['branch'=>'insurance_branch_id','subbranch'=>'insurance_subbranch_id','basis'=>'basis'] as $filter=>$column) {
            if ($filters[$filter] ?? null) $query->where($column, $filters[$filter]);
        }
        return $query->orderByDesc('agency_commission_schedule_id')
            ->orderBy('insurance_branch_id')->orderBy('insurance_subbranch_id')->orderBy('id');
    }
}
