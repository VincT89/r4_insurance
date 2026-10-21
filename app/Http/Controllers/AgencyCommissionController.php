<?php

namespace App\Http\Controllers;

use App\Models\{AgencyCommissionRate, AgencyCommissionSchedule, InsuranceBranch, SupplierAgency};
use App\Services\AgencyCommissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgencyCommissionController extends Controller
{
    public function index(Request $request, SupplierAgency $supplier)
    {
        $this->authorize('view',$supplier);
        return to_route('suppliers.show', [
            ...$request->only(['company','branch','subbranch','on','state','basis','page','_table_all']),
            'supplier'=>$supplier, 'tab'=>'commissions',
        ]);
    }

    public function create(SupplierAgency $supplier) { $this->authorize('system.admin'); return $this->form($supplier,new AgencyCommissionSchedule); }
    public function edit(SupplierAgency $supplier, AgencyCommissionSchedule $schedule)
    {
        $this->authorize('system.admin'); $this->belongs($supplier,$schedule);
        abort_unless($schedule->editable(),409);
        return $this->form($supplier,$schedule);
    }
    private function belongs(SupplierAgency $supplier, AgencyCommissionSchedule $schedule): void { abort_unless($schedule->supplier_agency_id === $supplier->id,404); }

    private function form(SupplierAgency $supplier, AgencyCommissionSchedule $schedule)
    {
        $schedule->load('rates');
        $selected = $schedule->rates->keyBy('scope_key');
        $branches = InsuranceBranch::where(fn($q)=>$q->where('status','active')->orWhereIn('id',$schedule->rates->pluck('insurance_branch_id')))
            ->with(['subbranches'=>fn($q)=>$q->where(fn($q)=>$q->where('status','active')->orWhereIn('id',$schedule->rates->pluck('insurance_subbranch_id')->filter()))])->orderBy('name')->get();
        $entries = []; $groups = [];
        foreach ($branches as $branch) {
            $group = ['branch'=>$branch,'indices'=>[]];
            foreach ([null,...$branch->subbranches->all()] as $sub) {
                $key = $branch->id.':'.($sub?->id??0); $rate = $selected->get($key);
                $group['indices'][] = count($entries);
                $entries[] = ['branch_id'=>$branch->id,'subbranch_id'=>$sub?->id,'selected'=>(bool)$rate,'label'=>($sub?->name??'Ramo senza sottoramo specificato').(($sub?->status??$branch->status)==='inactive' ? ' (inattivo, già configurato)' : ''),'basis'=>$rate?->basis??'','basis_note'=>$rate?->basis_note??'','issue_rate'=>AgencyCommissionRate::percent($rate?->issue_rate_units),'renewal_rate'=>AgencyCommissionRate::percent($rate?->renewal_rate_units),'quietance_rate'=>AgencyCommissionRate::percent($rate?->quietance_rate_units),'accrual_event'=>$rate?->accrual_event,'collection_mode'=>$rate?->collection_mode];
            }
            $groups[] = $group;
        }
        $unavailable = $schedule->rates->reject(fn($rate)=>collect($entries)->contains(fn($entry)=>$entry['branch_id']===$rate->insurance_branch_id && $entry['subbranch_id']===$rate->insurance_subbranch_id));
        return view('suppliers.commissions.form',compact('supplier','schedule','entries','groups','unavailable')+['companies'=>$supplier->companies()->where('status','active')->get()]);
    }

    private function values(Request $request, bool $revision): array
    {
        $data = $request->validate(['form_complete'=>['accepted'],'supplier_company_id'=>['required','integer'],'valid_from'=>['required','date_format:Y-m-d'],'valid_to'=>['nullable','date_format:Y-m-d','after_or_equal:valid_from'],'accrual_event'=>['nullable',Rule::in(array_keys(AgencyCommissionSchedule::ACCRUAL_EVENTS))],'collection_mode'=>['nullable',Rule::in(array_keys(AgencyCommissionSchedule::COLLECTION_MODES))],'mandate_reference'=>['nullable','string','max:150'],'notes'=>['nullable','string','max:3000'],'revision_reason'=>$revision ? ['required','string','min:10','max:1000'] : ['nullable','string','max:1000'],'entries'=>['required','array','max:80'],'entries.*'=>['array:branch_id,subbranch_id,selected,basis,basis_note,issue_rate,renewal_rate,quietance_rate,accrual_event,collection_mode'],'entries.*.branch_id'=>['required','integer'],'entries.*.subbranch_id'=>['nullable','integer'],'entries.*.selected'=>['nullable','boolean'],'entries.*.basis'=>['nullable',Rule::in(array_keys(AgencyCommissionRate::BASES))],'entries.*.basis_note'=>['nullable','string','max:500'],'entries.*.issue_rate'=>['nullable','string','max:15'],'entries.*.renewal_rate'=>['nullable','string','max:15'],'entries.*.quietance_rate'=>['nullable','string','max:15'],'entries.*.accrual_event'=>['nullable',Rule::in(array_keys(AgencyCommissionSchedule::ACCRUAL_EVENTS))],'entries.*.collection_mode'=>['nullable',Rule::in(array_keys(AgencyCommissionSchedule::COLLECTION_MODES))]],['form_complete.accepted'=>'Il modulo ricevuto è incompleto. Ricarica la pagina e seleziona meno voci per ciascuna configurazione.','entries.required'=>'Seleziona almeno un ramo o sottoramo.','entries.max'=>'Seleziona al massimo 80 voci per configurazione.']);
        return $data;
    }

    public function store(Request $request, SupplierAgency $supplier, AgencyCommissionRegistry $registry)
    {
        $this->authorize('system.admin');
        $schedule = $registry->save($supplier,$request->user(),$this->values($request,false));
        return to_route('suppliers.commissions.show',[$supplier,$schedule])->with('success','Condizioni provvigionali salvate.');
    }
    public function update(Request $request, SupplierAgency $supplier, AgencyCommissionSchedule $schedule, AgencyCommissionRegistry $registry)
    {
        $this->authorize('system.admin'); $this->belongs($supplier,$schedule);
        $next = $registry->save($supplier,$request->user(),$this->values($request,true),$schedule);
        return to_route('suppliers.commissions.show',[$supplier,$next])->with('success','Nuova versione salvata. La precedente rimane nello storico.');
    }
    public function show(SupplierAgency $supplier, AgencyCommissionSchedule $schedule)
    {
        $this->authorize('view',$supplier); $this->belongs($supplier,$schedule);
        $schedule->load(['company','author','previous','replacement','rates.branch','rates.subbranch']);
        return view('suppliers.commissions.show',compact('supplier','schedule'));
    }
    public function void(Request $request, SupplierAgency $supplier, AgencyCommissionSchedule $schedule, AgencyCommissionRegistry $registry)
    {
        $this->authorize('system.admin'); $this->belongs($supplier,$schedule);
        $data = $request->validate(['reason'=>['required','string','min:10','max:1000'],'confirmed'=>['accepted']]);
        $registry->void($supplier,$schedule,$request->user(),$data['reason']);
        return to_route('suppliers.commissions.show',[$supplier,$schedule])->with('success','Configurazione annullata e conservata nello storico.');
    }
}
