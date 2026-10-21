<?php

namespace App\Http\Controllers;

use App\Models\{InsuranceBranch, InsuranceSubbranch, SupplierCompany};
use App\Services\InsuranceCatalog;
use Illuminate\Http\Request;

class InsuranceCatalogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', SupplierCompany::class);
        $filters = $request->validate(['kind' => ['nullable', 'in:branch,subbranch'], 'branch' => ['nullable', 'integer', 'exists:insurance_branches,id'],
            'status' => ['nullable', 'in:active,inactive'], 'review' => ['nullable', 'in:1'], 'search' => ['nullable', 'string', 'max:100']]);
        $kind = $filters['kind'] ?? 'branch';
        $query = $kind === 'branch' ? InsuranceBranch::withCount('subbranches') : InsuranceSubbranch::with('branch');
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['review'] ?? null, fn ($q) => $q->where('needs_review', true))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->whereRaw('UPPER(name) LIKE ?', ['%'.InsuranceCatalog::normalize($v).'%']));
        if ($kind === 'subbranch' && ($filters['branch'] ?? null)) $query->where('insurance_branch_id', $filters['branch']);
        return view('insurance-catalog.index', ['kind' => $kind, 'records' => $query->orderBy('name')->orderBy('id')->paginate(\App\Support\TableListing::perPage(25))->withQueryString(),
            'branches' => InsuranceBranch::orderBy('name')->get()]);
    }

    public function create(Request $request, string $kind)
    {
        $this->authorize('create', SupplierCompany::class);
        $values = $request->validate(['branch' => ['nullable', 'integer', 'exists:insurance_branches,id']]);
        $record = $kind === 'branch' ? new InsuranceBranch : new InsuranceSubbranch(['insurance_branch_id' => $values['branch'] ?? null]);
        $record->status = 'active';
        return $this->form($record, $kind);
    }

    public function edit(string $kind, int $id)
    {
        $this->authorize('create', SupplierCompany::class);
        return $this->form($this->record($kind, $id), $kind);
    }

    public function store(Request $request, string $kind, InsuranceCatalog $catalog)
    {
        $this->authorize('create', SupplierCompany::class);
        $catalog->save($kind === 'branch' ? new InsuranceBranch : new InsuranceSubbranch, $this->values($request, $kind));
        return redirect()->route('insurance-catalog.index', ['kind' => $kind])->with('success', 'Voce aggiunta al catalogo.');
    }

    public function update(Request $request, string $kind, int $id, InsuranceCatalog $catalog)
    {
        $this->authorize('create', SupplierCompany::class);
        $catalog->save($this->record($kind, $id), $this->values($request, $kind));
        return redirect()->route('insurance-catalog.index', ['kind' => $kind])->with('success', 'Catalogo aggiornato.');
    }

    public function destroy(string $kind, int $id, InsuranceCatalog $catalog)
    {
        $this->authorize('system.admin');
        $catalog->delete($this->record($kind, $id));
        return redirect()->route('insurance-catalog.index', ['kind' => $kind])->with('success', 'Voce eliminata dal catalogo.');
    }

    private function record(string $kind, int $id): InsuranceBranch|InsuranceSubbranch
    {
        return $kind === 'branch' ? InsuranceBranch::findOrFail($id) : InsuranceSubbranch::findOrFail($id);
    }

    private function form(InsuranceBranch|InsuranceSubbranch $record, string $kind)
    {
        return view('insurance-catalog.form', ['record' => $record, 'kind' => $kind, 'branches' => InsuranceBranch::orderBy('name')->get()]);
    }

    private function values(Request $request, string $kind): array
    {
        if ($request->hasAny(['document_checklist_present', 'typed_checklist_present', 'document_categories', 'person_document_categories', 'company_document_categories'])) $this->authorize('system.admin');
        if ($request->boolean('document_checklist_present') && !$request->has('document_categories')) $request->merge(['document_categories' => []]);
        $rules = [];
        foreach (['person_document_categories', 'company_document_categories'] as $field) {
            if ($request->boolean('typed_checklist_present') && !$request->has($field)) $request->merge([$field=>[]]);
            $rules[$field] = ['sometimes', 'array'];
            $rules[$field.'.*'] = ['string', 'distinct', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Attachment::DOCUMENT_CATEGORIES))];
        }
        return $request->validate(['name' => ['required', 'string', 'max:100'], 'status' => ['required', 'in:active,inactive'],
            'needs_review' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:10000'],
            'insurance_branch_id' => $kind === 'subbranch' ? ['required', 'integer', 'exists:insurance_branches,id'] : ['prohibited'],
            'document_categories' => ['sometimes', 'array'], 'document_categories.*' => ['string', 'distinct', \Illuminate\Validation\Rule::in(array_keys(\App\Models\Attachment::DOCUMENT_CATEGORIES))]] + $rules);
    }
}
