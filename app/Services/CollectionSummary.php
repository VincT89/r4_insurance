<?php

namespace App\Services;

use App\Models\{InsuranceCollection, InsuranceTitle};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CollectionSummary
{
    public static function key(?int $company, ?int $agency, string $name): string
    {
        return json_encode([$company,$agency,$name], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    }

    public function forQuery(Builder $query): Collection
    {
        /** Aggregate the same authorized, filtered movements before pagination; never regroup by the current policy's mandate. */
        $payments = (clone $query)->whereNull('voided_at')->select(['insurance_title_id','direction','amount_cents','recipient']);
        $company = DB::connection()->getQueryGrammar()->wrap('titles.contract_snapshot->company');
        $company = "COALESCE(NULLIF($company, ''), 'Compagnia non indicata')";
        $attributed = DB::query()->fromSub($payments,'payments')
            ->join('insurance_titles as titles','titles.id','=','payments.insurance_title_id')
            ->leftJoin('supplier_agencies as agencies','agencies.id','=','titles.supplier_agency_id')
            ->select(['titles.supplier_company_id as company_id','titles.supplier_agency_id as agency_id','agencies.name as agency','payments.direction','payments.amount_cents','payments.recipient'])
            ->selectRaw($company.' as company');
        /** Group named columns from a derived table so MySQL strict grouping also accepts JSON snapshot fields. */
        return DB::query()->fromSub($attributed,'payments')->select(['company_id','agency_id','agency','company'])
            ->selectRaw(InsuranceCollection::totalsSql('payments'))
            ->selectRaw("SUM(CASE WHEN payments.direction='receipt' THEN 1 ELSE 0 END) as receipt_count, SUM(CASE WHEN payments.direction='refund' THEN 1 ELSE 0 END) as refund_count")
            ->groupBy('company_id','agency_id','agency','company')
            ->orderBy('company')->orderBy('agency')->get()->map(function ($row) {
                $row->agency ??= 'Rapporto diretto';
                $row->key = self::key($row->company_id,$row->agency_id,$row->company);
                return $row;
            });
    }

    public function agencyCompanies(Collection $agencies): array
    {
        $links = $agencies->load('companies:id')->mapWithKeys(fn($agency)=>[$agency->id=>$agency->companies->pluck('id')->map(fn($id)=>(string)$id)->all()])->all();
        /** Historical mandates stay selectable even after the agency's current company relationships change. */
        foreach (InsuranceTitle::visibleTo(auth()->user())->whereNotNull('supplier_agency_id')->whereNotNull('supplier_company_id')->select(['supplier_agency_id','supplier_company_id'])->distinct()->get() as $title) {
            $links[$title->supplier_agency_id][] = (string)$title->supplier_company_id;
        }
        return array_map(fn($ids)=>array_values(array_unique($ids)),$links);
    }
}
