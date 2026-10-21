<?php

namespace App\Services;

use App\Models\{InsurancePolicy, InsurancePolicyChange};
use App\Support\TableListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AgencyCommunicationOperations
{
    public const LABELS = ['all'=>'Tutte le operazioni', 'created'=>'Solo inserimenti', 'renewed'=>'Solo rinnovi', 'addition'=>'Solo aggiunte di coperture'];

    public function page(Builder $policies, Builder $changes): LengthAwarePaginator
    {
        $policyRows = (clone $policies)->select(['id as record_id', 'id as policy_id', 'supplier_agency_id', 'ad_horas'])
            ->selectRaw('0 as operation_sort')->toBase();
        $changeRows = (clone $changes)->join('insurance_policies as communication_contract', 'communication_contract.id', '=', 'insurance_policy_changes.result_policy_id')
            ->select(['insurance_policy_changes.id as record_id', 'communication_contract.id as policy_id', 'communication_contract.supplier_agency_id', 'communication_contract.ad_horas'])
            ->selectRaw('1 as operation_sort')->toBase();
        /** Paginate the combined result in SQL so an old contract with several new appendices contributes one row per actual operation. */
        $page = DB::query()->fromSub($policyRows->unionAll($changeRows), 'communication_operations')
            ->orderBy('supplier_agency_id')->orderByDesc('ad_horas')->orderBy('policy_id')->orderBy('operation_sort')->orderBy('record_id')
            ->paginate(TableListing::perPage(25))->withQueryString();
        $contracts = InsurancePolicy::whereKey($page->getCollection()->pluck('policy_id')->unique())->with(['client','supplierAgency'])->get()->keyBy('id');
        $appendices = InsurancePolicyChange::whereKey($page->getCollection()->where('operation_sort', 1)->pluck('record_id'))->get()->keyBy('id');
        $page->setCollection($page->getCollection()->map(function ($row) use ($contracts, $appendices) {
            $policy = clone $contracts->get($row->policy_id);
            $change = (int) $row->operation_sort === 1 ? $appendices->get($row->record_id) : null;
            if ($change) $change->setRelation('resultPolicy', $contracts->get($row->policy_id));
            $policy->setRelation('communicationChange', $change);
            return $policy;
        }));

        return $page;
    }
}
