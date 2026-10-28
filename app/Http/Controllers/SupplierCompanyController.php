<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSupplierCompanyRequest;
use App\Models\{SupplierAgency, SupplierCompany};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupplierCompanyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierCompany::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'in:active,inactive']]);
        $query = SupplierCompany::query()->orderBy('name')->orderBy('id');
        if (filled($filters['search'] ?? null)) {
            $search = '%'.mb_strtolower(trim($filters['search'])).'%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$search])
                ->orWhereRaw('LOWER(reference_person) LIKE ?', [$search])->orWhereRaw('LOWER(email) LIKE ?', [$search]));
        }
        if (filled($filters['status'] ?? null)) $query->where('status', $filters['status']);
        return view('supplier-companies.index', [
            'companies' => $query->paginate(\App\Support\TableListing::perPage(20))->withQueryString(),
            'unassignedCount' => SupplierAgency::whereDoesntHave('companies')->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SupplierCompany::class);
        return view('supplier-companies.form', ['company' => new SupplierCompany(['status' => 'active'])]);
    }

    public function store(SaveSupplierCompanyRequest $request): RedirectResponse
    {
        $company = DB::transaction(fn () => SupplierCompany::create($request->validated()));
        return redirect()->route('supplier-companies.show', $company)->with('success', 'Compagnia aggiunta ai fornitori.');
    }

    public function show(SupplierCompany $company): View
    {
        $this->authorize('view', $company);
        $agencies = $company->agencies()->whereNull('parent_agency_id')
            ->with(['subagencies' => fn ($q) => $q->whereHas('companies', fn ($c) => $c->where('supplier_companies.id', $company->id))->orderBy('name')->orderBy('id')])
            ->orderBy('supplier_agencies.name')->orderBy('supplier_agencies.id')->paginate(\App\Support\TableListing::perPage(20))->withQueryString();
        return view('supplier-companies.show', compact('company', 'agencies'));
    }

    public function edit(SupplierCompany $company): View
    {
        $this->authorize('update', $company);
        return view('supplier-companies.form', compact('company'));
    }

    public function update(SaveSupplierCompanyRequest $request, SupplierCompany $company): RedirectResponse
    {
        DB::transaction(fn () => $company->update($request->validated()));
        return redirect()->route('supplier-companies.show', $company)->with('success', 'Compagnia aggiornata.');
    }

    public function destroy(SupplierCompany $company): RedirectResponse
    {
        $this->authorize('delete', $company);
        DB::transaction(function () use ($company) {
            $company = SupplierCompany::whereKey($company->id)->lockForUpdate()->firstOrFail();
            if (\App\Models\InsuranceQuoteCase::whereJsonContains('recipient_group_snapshot->company_ids',$company->id)->exists()) throw ValidationException::withMessages(['company'=>'La compagnia è conservata in un gruppo di una richiesta di preventivo. Impostala come inattiva per mantenere lo storico.']);
            if (\App\Models\QuoteRecipientGroup::whereHas('companies',fn($q)=>$q->where('supplier_companies.id',$company->id))->exists() || \App\Models\ClientVehicle::where('merit_class_company_id',$company->id)->exists()) throw ValidationException::withMessages(['company'=>'La compagnia è collegata a gruppi preventivi o classi di merito. Impostala come inattiva per conservare i riferimenti.']);
            if (\App\Models\AgencyCommissionSchedule::where('supplier_company_id',$company->id)->exists()) throw ValidationException::withMessages(['company'=>'La compagnia ha condizioni provvigionali da conservare. Puoi impostarla come inattiva.']);
            if (\App\Models\InsuranceQuoteEmail::where('supplier_company_id',$company->id)->exists()) throw ValidationException::withMessages(['company'=>'La compagnia è collegata allo storico delle richieste di preventivo. Impostala come inattiva per conservarlo.']);
            if ($company->agencies()->exists() || $company->policies()->exists() || \App\Models\InsuranceQuote::where('supplier_company_id',$company->id)->exists()) {
                throw ValidationException::withMessages(['company' => 'La compagnia ha agenzie o polizze collegate e non può essere eliminata. Puoi impostarla come inattiva.']);
            }
            $company->delete();
        });
        return redirect()->route('supplier-companies.index')->with('success', 'Compagnia eliminata.');
    }
}
