<?php

namespace App\Http\Controllers;

use App\Domain\Core\Queries\InsuranceCommissionQuery;
use App\Models\{Client, InsuranceBranch, InsuranceCommission, InsuranceCommissionMovement, InsurancePolicy, InsuranceTitle, SupplierAgency, SupplierCompany};
use App\Services\InsuranceCommissionLedger;
use App\Support\{Money, TableListing};
use Illuminate\Http\Request;
use Illuminate\Validation\{Rule, ValidationException};

class InsuranceCommissionController extends Controller
{
    private const STATE_SQL = "CASE WHEN v.settled_cents > v.accrued_cents THEN 'reconcile' WHEN t.cancelled_at IS NOT NULL THEN 'cancelled' WHEN v.locked_at IS NULL THEN 'incomplete' WHEN v.expected_cents=0 THEN 'accrued' WHEN v.settled_cents=v.expected_cents THEN 'settled' WHEN v.accrued_cents=v.expected_cents THEN 'accrued' WHEN v.accrued_cents>0 THEN 'partial' ELSE 'expected' END";

    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'mode'=>['nullable', 'in:positions,movements'], 'group'=>['nullable', 'in:detail,agency,company,branch'],
            'period'=>['nullable', 'in:month,range,all'], 'month'=>['nullable', 'date_format:Y-m'],
            'from'=>['nullable', 'required_if:period,range', 'date_format:Y-m-d'], 'to'=>['nullable', 'required_if:period,range', 'date_format:Y-m-d', 'after_or_equal:from'],
            'search'=>['nullable', 'string', 'max:200'], 'agency'=>['nullable', 'integer', 'exists:supplier_agencies,id'],
            'company'=>['nullable', 'integer', 'exists:supplier_companies,id'], 'branch'=>['nullable', 'integer', 'exists:insurance_branches,id'],
            'subbranch'=>['nullable', 'integer', 'exists:insurance_subbranches,id'], 'state'=>['nullable', Rule::in(array_keys(InsuranceCommission::STATES))],
            'kind'=>['nullable', Rule::in(array_keys(InsuranceCommissionMovement::KINDS))],
            'client'=>['nullable', 'integer', 'exists:clients,id'],
            'policy'=>['nullable', 'integer', Rule::exists('insurance_policies','id')->when($request->filled('client'), fn ($rule) => $rule->where('client_id',$request->integer('client')))],
        ]);
        $filters['mode'] ??= 'positions'; $filters['group'] ??= 'detail'; $filters['period'] ??= 'month'; $filters['month'] ??= today()->format('Y-m');
        return $filters;
    }

    private function query(array $filters)
    {
        $query = app(InsuranceCommissionQuery::class)->positions();
        if ($filters['mode'] === 'movements') $query->join('insurance_commission_movements as m', 'm.insurance_commission_id', '=', 'v.id');
        foreach (['client'=>'p.client_id', 'policy'=>'p.id', 'agency'=>'p.supplier_agency_id', 'company'=>'p.supplier_company_id', 'branch'=>'p.insurance_branch_id', 'subbranch'=>'p.insurance_subbranch_id'] as $key=>$column) {
            if (filled($filters[$key] ?? null)) $query->where($column, $filters[$key]);
        }
        if ($search = $filters['search'] ?? null) $query->where(fn ($q) => $q->where('p.number', 'like', '%'.$search.'%')->orWhere('c.name', 'like', '%'.$search.'%'));
        if ($filters['state'] ?? null) $query->whereRaw('('.self::STATE_SQL.') = ?', [$filters['state']]);
        if ($filters['mode'] === 'movements' && ($filters['kind'] ?? null)) $query->where('m.kind', $filters['kind']);
        $column = $filters['mode'] === 'positions' ? 't.coverage_from' : 'm.effective_on';
        if ($filters['period'] === 'month') {
            $start = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $filters['month']);
            $query->where($column, '>=', $start->toDateString())->where($column, '<', $start->copy()->addMonth()->toDateString());
        } elseif ($filters['period'] === 'range') $query->where($column, '>=', $filters['from'])->where($column, '<', \Illuminate\Support\Carbon::parse($filters['to'])->addDay()->toDateString());
        return $query;
    }

    private function totalsSql(string $mode): string
    {
        return $mode === 'positions'
            ? InsuranceCommissionQuery::balancesSql()
            : "COUNT(*) as rows_count, 0 as incomplete, COALESCE(SUM(CASE WHEN m.kind='forecast' THEN m.amount_cents ELSE 0 END),0) as expected, COALESCE(SUM(CASE WHEN m.kind='accrual' THEN m.amount_cents ELSE 0 END),0) as accrued, COALESCE(SUM(CASE WHEN m.kind='settlement' THEN m.amount_cents ELSE 0 END),0) as settled, 0 as payable, 0 as reconcile";
    }

    public function index(Request $request)
    {
        $this->authorize('system.admin');
        $filters = $this->filters($request); $base = $this->query($filters);
        $totals = (clone $base)->selectRaw($this->totalsSql($filters['mode']))->first();
        if ($filters['group'] !== 'detail') {
            [$label, $id] = match ($filters['group']) {
                'agency'=>["COALESCE(a.name,'Agenzia non assegnata')", 'p.supplier_agency_id'],
                'company'=>['p.company', 'p.supplier_company_id'],
                'branch'=>['p.category', 'p.insurance_branch_id'],
            };
            $rows = $base->selectRaw($label.' as label, '.$id.' as group_id, '.$this->totalsSql($filters['mode']))->groupByRaw($label.', '.$id)->orderBy('label')->orderBy('group_id');
        } else {
            $rows = $base->select(['t.id as title_id', 'p.number', 'p.company', 'p.category', 'p.subcategory', 'p.client_id', 'p.id as policy_id', 'c.name as client_name', 'a.name as agency_name', 't.coverage_from', 't.kind as title_kind', 'v.expected_cents', 'v.accrued_cents', 'v.settled_cents', 'v.incomplete_reason'])
                ->selectRaw(self::STATE_SQL.' as state');
            if ($filters['mode'] === 'movements') $rows->addSelect(['m.id as movement_id', 'm.effective_on', 'm.kind', 'm.amount_cents', 'm.reference', 'm.reason', 'm.reversal_of_id'])->orderByDesc('m.effective_on')->orderByDesc('m.id');
            else $rows->orderBy('t.coverage_from')->orderBy('t.id');
        }
        $rows = $rows->paginate(TableListing::perPage(25))->withQueryString();
        return view('commissions.index', compact('filters', 'totals', 'rows') + [
            'agencies'=>SupplierAgency::orderBy('name')->get(['id', 'name']), 'companies'=>SupplierCompany::orderBy('name')->get(['id', 'name']),
            'branches'=>InsuranceBranch::with('subbranches')->orderBy('name')->get(),
            'contextClient'=>filled($filters['client'] ?? null) ? Client::findOrFail($filters['client']) : null,
            'contextPolicy'=>filled($filters['policy'] ?? null) ? InsurancePolicy::findOrFail($filters['policy']) : null,
        ]);
    }

    public function calculate(Request $request, InsuranceCommissionLedger $ledger)
    {
        $this->authorize('system.admin'); $request->validate(['confirmed'=>['accepted']]);
        $filters = $this->filters($request); $filters['mode'] = 'positions';
        $ids = $this->query($filters)->whereNull('t.cancelled_at')->whereNull('v.locked_at')->orderBy('t.id')->limit(501)->pluck('t.id');
        if ($ids->count() > 500) throw ValidationException::withMessages(['commission'=>'Seleziona un periodo o un’agenzia con al massimo 500 rate da calcolare.']);
        $ready = 0;
        foreach (InsuranceTitle::whereIn('id', $ids)->orderBy('id')->cursor() as $title) if ($ledger->sync($title)->locked_at) $ready++;
        return to_route('commissions.index', $filters)->with('success', 'Calcolo completato: '.$ready.' rate configurate; '.($ids->count()-$ready).' richiedono dati o condizioni.');
    }

    public function show(InsuranceTitle $title)
    {
        $this->authorize('system.admin');
        $title->load(['policy.client', 'policy.supplierAgency', 'commission.rate.schedule']); $commission = $title->commission;
        $movements = InsuranceCommissionMovement::with(['author', 'reversal'])->where('insurance_commission_id', $commission?->id ?? 0)->orderByDesc('effective_on')->orderByDesc('id')->paginate(TableListing::perPage(25))->withQueryString();
        return view('commissions.show', compact('title', 'commission', 'movements'));
    }

    public function prepare(Request $request, InsuranceTitle $title, InsuranceCommissionLedger $ledger)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['version'=>['required', 'integer', 'min:-1'], 'base_amount'=>['nullable', 'regex:/\A\d{1,10}([.,]\d{1,2})?\z/'], 'base_reference'=>['nullable', 'required_with:base_amount', 'string', 'max:500']]);
        if (isset($data['base_amount'])) $data['base_cents'] = Money::cents($data['base_amount']);
        $commission = $ledger->prepare($title, $data);
        return to_route('commissions.show', $title)->with($commission->locked_at ? 'success' : 'warning', $commission->locked_at ? 'Provvigione calcolata. Le condizioni applicate sono conservate nello storico.' : $commission->incomplete_reason);
    }

    public function record(Request $request, InsuranceTitle $title, string $kind, InsuranceCommissionLedger $ledger)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['version'=>['required', 'integer', 'min:0'], 'amount'=>['required', 'regex:/\A\d{1,10}([.,]\d{1,2})?\z/'], 'effective_on'=>['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference'=>['required', 'string', 'max:150'], 'reason'=>['required', 'string', 'min:5', 'max:1000'], 'idempotency_key'=>['required', 'uuid'], 'confirmed'=>['accepted']]);
        $data['amount_cents'] = Money::cents($data['amount']);
        $ledger->record($title, $kind, $data);
        return to_route('commissions.show', $title)->with('success', $kind === 'settlement' ? 'Liquidazione registrata.' : 'Conferma di maturazione registrata.');
    }

    public function reverse(Request $request, InsuranceTitle $title, InsuranceCommissionMovement $movement, InsuranceCommissionLedger $ledger)
    {
        $this->authorize('system.admin');
        $data = $request->validate(['version'=>['required', 'integer', 'min:0'], 'effective_on'=>['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'reason'=>['required', 'string', 'min:10', 'max:1000'], 'confirmed'=>['accepted']]);
        $ledger->reverse($title, $movement, $data);
        return to_route('commissions.show', $title)->with('success', 'Storno registrato; il movimento originale resta nello storico.');
    }
}
