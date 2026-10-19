<?php

namespace App\Http\Controllers;

use App\Domain\Core\Queries\PolicyPortfolioQuery;
use App\Support\TableListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InsuranceReportController extends Controller
{
    public function __invoke(Request $request, PolicyPortfolioQuery $query)
    {
        $this->authorize('system.admin');
        $filters = $query->filters($request);
        $filters['month'] = $filters['month'] ?? today()->format('Y-m');
        $filters['date_basis'] = $filters['date_basis'] ?? 'expires_on';
        $reportMonth = $filters['month'];
        $group = $request->validate(['group' => ['nullable', 'in:company,agency,category,subcategory,responsible,status']])['group'] ?? 'agency';
        $policies = $query->forUser($request->user(), $filters);
        $totals = (clone $policies)->selectRaw("COUNT(*) as policies, COUNT(DISTINCT client_id) as clients, COALESCE(SUM(premium), 0) as premiums, COUNT(premium) as priced, SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid")->first();
        $base = DB::query()->fromSub((clone $policies)->select('insurance_policies.*'), 'p')
            ->join('clients as c', 'c.id', '=', 'p.client_id')
            ->leftJoin('supplier_agencies as a', 'a.id', '=', 'p.supplier_agency_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.commercial_user_id');
        $today = today()->toDateString();
        $effectiveStatus = "CASE WHEN p.status IN ('active','renewed') AND p.expires_on < '$today' THEN 'Scaduta' WHEN p.status IN ('active','renewed') AND (CASE WHEN p.status='renewed' THEN COALESCE(p.coverage_origin_on,p.starts_on) ELSE p.starts_on END) > '$today' THEN 'Decorrenza futura' WHEN p.status = 'active' THEN 'In vigore' WHEN p.status = 'renewed' THEN 'Rinnovata' WHEN p.status = 'expired' THEN 'Scaduta' WHEN p.status = 'suspended' THEN 'Sospesa' WHEN p.status = 'replaced' THEN 'Sostituita' WHEN p.status = 'cancelled' THEN 'Annullata' WHEN p.status = 'ceased' THEN 'Cessata' ELSE 'Da verificare' END";
        $dimension = match ($group) {
            'agency' => "COALESCE(a.name, 'Non assegnata')", 'category' => 'p.category',
            'subcategory' => "COALESCE(NULLIF(p.subcategory, ''), 'Non specificato')",
            'responsible' => "COALESCE(u.name, 'Non assegnato')", 'status' => $effectiveStatus,
            default => 'p.company',
        };
        $groupFields = match ($group) { 'agency' => [$dimension, 'a.id'], 'responsible' => [$dimension, 'u.id'], 'subcategory' => [$dimension, 'p.category'], default => [$dimension] };
        $rows = $base->selectRaw("$dimension as label, COUNT(*) as policies, COUNT(DISTINCT p.client_id) as clients, COALESCE(SUM(p.premium), 0) as premiums, COUNT(p.premium) as priced, SUM(CASE WHEN p.payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid");
        if ($group === 'subcategory') $rows->addSelect('p.category as parent_category');
        $rows = $rows->groupByRaw(implode(', ', $groupFields))->orderByDesc('policies')->orderBy('label')->paginate(TableListing::perPage(25))->withQueryString();
        return view('reports.index', compact('filters', 'group', 'rows', 'totals', 'reportMonth') + $query->options($request->user()));
    }
}
