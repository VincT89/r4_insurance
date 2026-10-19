<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSupplierAgencyRequest;
use App\Models\{SupplierAgency, SupplierCompany};
use App\Services\SupplierAgencyRegistry;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupplierAgencyController extends Controller
{
    public function __construct(private SupplierAgencyRegistry $registry) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierAgency::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,inactive'],
            'supplier_company_id' => ['nullable', 'integer', 'exists:supplier_companies,id'], 'unassigned' => ['nullable', 'in:1'],
        ]);
        $query = SupplierAgency::query()->with(['companies', 'parentAgency'])
            ->orderBy('name')->orderBy('id');
        if (filled($filters['search'] ?? null)) {
            $search = '%'.mb_strtolower(trim($filters['search'])).'%';
            $query->where(function ($matches) use ($search) {
                foreach (['name', 'company_name', 'agency_code', 'vat_number', 'tax_code', 'email', 'phone', 'mobile', 'reference_person', 'city'] as $field) {
                    $matches->orWhereRaw("LOWER({$field}) LIKE ?", [$search]);
                }
                $matches->orWhereHas('companies', fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$search]));
            });
        }
        if (filled($filters['status'] ?? null)) $query->where('status', $filters['status']);
        if (filled($filters['supplier_company_id'] ?? null)) $query->whereHas('companies', fn ($q) => $q->where('supplier_companies.id', $filters['supplier_company_id']));
        if ($filters['unassigned'] ?? null) $query->whereDoesntHave('companies');
        return view('suppliers.index', [
            'suppliers' => $query->paginate(\App\Support\TableListing::perPage(20))->withQueryString(),
            'companies' => SupplierCompany::orderBy('name')->orderBy('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', SupplierAgency::class);
        $values = $request->validate([
            'supplier_company_id' => ['nullable', 'integer', 'exists:supplier_companies,id'],
            'parent_agency_id' => ['nullable', 'integer', 'exists:supplier_agencies,id'],
        ]);
        if ($values['parent_agency_id'] ?? null) {
            $parent = SupplierAgency::findOrFail($values['parent_agency_id']);
            if ($parent->parent_agency_id || $parent->companies()->doesntExist()
                || (isset($values['supplier_company_id']) && ! $parent->companies()->whereKey($values['supplier_company_id'])->exists())) {
                throw ValidationException::withMessages(['parent_agency_id' => 'Seleziona un’agenzia principale con una compagnia di appartenenza.']);
            }
            $values['supplier_company_id'] = $parent->supplier_company_id;
        }
        return view('suppliers.form', $this->formData(new SupplierAgency($values + ['status' => 'active'])));
    }

    public function store(SaveSupplierAgencyRequest $request): RedirectResponse
    {
        $supplier = $this->registry->save(new SupplierAgency, $request->validated());
        return redirect()->route('suppliers.show', $supplier)->with('success', 'Agenzia aggiunta ai fornitori.');
    }

    public function show(SupplierAgency $supplier): View
    {
        $this->authorize('view', $supplier);
        $supplier->load(['companies', 'parentAgency', 'subagencies' => fn ($q) => $q->orderBy('name')->orderBy('id')]);
        return view('suppliers.show', compact('supplier'));
    }

    public function edit(SupplierAgency $supplier): View
    {
        $this->authorize('update', $supplier);
        return view('suppliers.form', $this->formData($supplier));
    }

    public function update(SaveSupplierAgencyRequest $request, SupplierAgency $supplier): RedirectResponse
    {
        $this->registry->save($supplier, $request->validated());
        return redirect()->route('suppliers.show', $supplier)->with('success', 'Anagrafica agenzia aggiornata.');
    }

    public function destroy(SupplierAgency $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);
        $this->registry->delete($supplier);
        return redirect()->route('suppliers.index')->with('success', 'Agenzia eliminata dall’anagrafica fornitori.');
    }

    private function formData(SupplierAgency $supplier): array
    {
        return [
            'supplier' => $supplier,
            'selectedCompanies' => $supplier->exists ? $supplier->companies()->pluck('supplier_companies.id')->all() : ($supplier->parent_agency_id ? SupplierAgency::findOrFail($supplier->parent_agency_id)->companies()->pluck('supplier_companies.id')->all() : array_filter([$supplier->supplier_company_id])),
            'companies' => SupplierCompany::orderBy('name')->orderBy('id')->get(),
            'parentAgencies' => SupplierAgency::with('companies')->whereNull('parent_agency_id')->whereHas('companies')
                ->when($supplier->exists, fn ($q) => $q->where('id', '!=', $supplier->id))->orderBy('name')->orderBy('id')->get(),
            'hasSubagencies' => $supplier->exists && $supplier->subagencies()->exists(),
        ];
    }
}
