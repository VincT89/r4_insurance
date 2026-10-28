<?php

namespace App\Http\Controllers;

use App\Models\{InsuranceBranch, InsuranceSubbranch, QuoteRecipientGroup, SupplierCompany};
use App\Services\AuditLogService;
use App\Support\TableListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuoteRecipientGroupController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('system.admin');
        $filters = $request->validate(['search'=>['nullable','string','max:150'], 'branch'=>['nullable','integer','exists:insurance_branches,id'], 'cu'=>['nullable','integer','between:1,18'], 'status'=>['nullable','in:active,inactive']]);
        $groups = QuoteRecipientGroup::with(['companies','branch','subbranch'])->when($filters['search'] ?? null, fn($q,$value) => $q->where('name','like','%'.$value.'%'))
            ->when($filters['branch'] ?? null, fn($q,$value) => $q->where('insurance_branch_id',$value))
            ->when($filters['cu'] ?? null, fn($q,$value) => $q->where('cu_from','<=',$value)->where('cu_to','>=',$value))
            ->when($filters['status'] ?? null, fn($q,$value) => $q->where('status',$value))
            ->orderBy('cu_from')->orderBy('name')->orderBy('id')->paginate(TableListing::perPage(20))->withQueryString();
        return view('quote-groups.index', compact('groups','filters') + ['branches'=>InsuranceBranch::orderBy('name')->get()]);
    }

    public function create()
    {
        return $this->form(new QuoteRecipientGroup(['status'=>'active']));
    }

    public function edit(QuoteRecipientGroup $quoteGroup)
    {
        return $this->form($quoteGroup);
    }

    private function form(QuoteRecipientGroup $group)
    {
        $this->authorize('system.admin');
        return view('quote-groups.form', ['group'=>$group, 'branches'=>InsuranceBranch::with('subbranches')->orderBy('name')->get(), 'companies'=>SupplierCompany::orderBy('name')->get(), 'selected'=>$group->exists ? $group->companies()->pluck('supplier_companies.id')->all() : []]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new QuoteRecipientGroup);
    }

    public function update(Request $request, QuoteRecipientGroup $quoteGroup)
    {
        return $this->save($request, $quoteGroup);
    }

    private function save(Request $request, QuoteRecipientGroup $group)
    {
        $this->authorize('system.admin');
        $data = $request->validate([
            'name'=>['required','string','max:150',Rule::unique('quote_recipient_groups')->ignore($group->id)],
            'insurance_branch_id'=>['required','integer','exists:insurance_branches,id'],
            'insurance_subbranch_id'=>['nullable','integer',Rule::exists('insurance_subbranches','id')->where('insurance_branch_id',$request->input('insurance_branch_id'))],
            'cu_from'=>['nullable','required_with:cu_to','integer','between:1,18'], 'cu_to'=>['nullable','required_with:cu_from','integer','between:1,18','gte:cu_from'],
            'status'=>['required','in:active,inactive'], 'company_ids'=>['required','array','list','min:1','max:100'],
            'company_ids.*'=>['required','integer','distinct',Rule::exists('supplier_companies','id')],
        ]);
        DB::transaction(function () use ($data, $group) {
            $branch = InsuranceBranch::whereKey($data['insurance_branch_id'])->lockForUpdate()->firstOrFail();
            $subbranch = !empty($data['insurance_subbranch_id']) ? InsuranceSubbranch::whereKey($data['insurance_subbranch_id'])->lockForUpdate()->firstOrFail() : null;
            if ($data['status']==='active' && ($branch->status!=='active' || ($subbranch && $subbranch->status!=='active'))) throw \Illuminate\Validation\ValidationException::withMessages(['insurance_branch_id'=>'Per un gruppo attivo scegli un ramo e un sottoramo attivi.']);
            if ($group->exists) $group = QuoteRecipientGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            $old = $group->exists ? $group->only(['name','insurance_branch_id','insurance_subbranch_id','cu_from','cu_to','status']) + ['company_ids'=>$group->companies()->pluck('supplier_companies.id')->all()] : null;
            if ($data['status']==='active' && !SupplierCompany::whereIn('id',$data['company_ids'])->where('status','active')->exists()) throw \Illuminate\Validation\ValidationException::withMessages(['company_ids'=>'Seleziona almeno una compagnia attiva.']);
            $group->fill(\Illuminate\Support\Arr::except($data,['company_ids']))->save();
            $group->companies()->sync($data['company_ids']);
            app(AuditLogService::class)->log('quote_group_updated', $group, oldValues:$old, newValues:$data, description:'Configurato il gruppo preventivi '.$group->name.' ('.$group->class_label.').');
        });
        return to_route('quote-groups.index')->with('success','Gruppo salvato. Le richieste già aperte conservano le compagnie selezionate in precedenza.');
    }
}
