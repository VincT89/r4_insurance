<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveInsurancePolicyRequest;
use App\Models\{Client, InsurancePolicy};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InsurancePolicyController extends Controller
{
    public function create(Request $request, Client $client): View
    {
        $this->authorize('updateRegistry', $client);
        $data = $request->validate(['category' => ['nullable', 'string', 'max:100'], 'subcategory' => ['nullable', 'string', 'max:100']]);
        return $this->form($client, new InsurancePolicy(['status' => 'active', 'tacit_renewal' => true, 'category' => $data['category'] ?? null, 'subcategory' => $data['subcategory'] ?? null]));
    }

    public function store(SaveInsurancePolicyRequest $request, Client $client): RedirectResponse
    {
        $stored = [];
        try {
            $policy = DB::transaction(function () use ($request, $client, &$stored) {
                $values = $request->safe()->except(['client_id','documents','entry_mode','insurance_quote_id','existing_reason']);
                $values['tacit_renewal'] ??= true;
                if($request->input('entry_mode')==='new') $policy=app(\App\Services\InsuranceQuoteWorkflow::class)->createPolicy($client,$values,$request->integer('insurance_quote_id'));
                else {
                    $policy = $client->policies()->create(app(\App\Services\PolicyProviders::class)->values(app(\App\Services\InsuranceCatalog::class)->policyValues($values)));
                    app(\App\Services\InsuranceLedger::class)->log($policy,'policy_recorded','registrata polizza già stipulata: '.$request->input('existing_reason'));
                }
                if(in_array($policy->status,InsurancePolicy::CLOSED_STATUSES,true))$policy->forceFill(['closed_at'=>now()])->save();
                foreach ($request->file('documents', []) as $file) $stored[] = app(\App\Services\ClientDocumentRegistry::class)->upload($client, $file, ['document_category'=>'policy', 'insurance_policy_id'=>$policy->id]);
                return $policy;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $document) \Illuminate\Support\Facades\Storage::disk($document->disk)->delete($document->path);
            throw $e;
        }
        return redirect()->route('clients.policies.show', [$client, $policy])->with('success', 'Polizza aggiunta al cliente.');
    }

    public function show(Client $client, InsurancePolicy $policy): View
    {
        $this->authorize('view', $client);
        abort_unless((int) $policy->client_id === (int) $client->id, 404);
        /** Keep display aggregates outside the persisted attributes checked by the work fingerprint. */
        $hasTitles = $policy->titles()->exists();
        $commissionSummary = auth()->user()->can('system.admin')
            ? app(\App\Domain\Core\Queries\InsuranceCommissionQuery::class)->summary($client, $policy)
            : null;
        $policy->load(['workClosedBy', 'vehicle', 'asset', 'supplierCompany', 'supplierAgency', 'importEntry', 'documents' => fn ($q) => $q->whereDoesntHave('newerVersion')->with(['uploader', 'newerVersion', 'validator'])->withExists('checklistRequests')]);
        $corrections = $policy->corrections()->with(['requester', 'resolver'])->latest()->paginate(10, ['*'], 'corrections_page')->withQueryString();
        $changes = \App\Models\InsurancePolicyChange::where(fn($q)=>$q->where('source_policy_id',$policy->id)->orWhere('result_policy_id',$policy->id))->with(['sourcePolicy','resultPolicy','author'])->latest('id')->paginate(\App\Support\TableListing::perPage(10),['*'],'changes_page')->withQueryString();
        $accountingReview = in_array($policy->status, InsurancePolicy::CLOSED_STATUSES, true)
            ? $policy->titles()->whereNull('cancelled_at')->whereColumn('collected_cents','<','amount_cents')
                ->selectRaw('COUNT(*) as open_titles, COALESCE(SUM(amount_cents-collected_cents),0) as balance_cents')->toBase()->first()
            : null;
        return view('clients.policies.show', compact('client', 'policy', 'corrections', 'accountingReview', 'commissionSummary', 'changes', 'hasTitles'));
    }

    public function edit(Client $client, InsurancePolicy $policy): View
    {
        $this->authorize('updateRegistry', $client);
        abort_unless($policy->editableBy(auth()->user()),403);
        abort_unless((int) $policy->client_id === (int) $client->id, 404);
        return $this->form($client, $policy);
    }

    public function update(SaveInsurancePolicyRequest $request, Client $client, InsurancePolicy $policy): RedirectResponse
    {
        DB::transaction(function () use ($request, $policy) {
            \App\Models\InsuranceBranch::orderBy('id')->lockForUpdate()->get();
            \App\Models\SupplierCompany::orderBy('id')->lockForUpdate()->get();
            $locked = InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->editableBy(auth()->user()),403);
            if (!hash_equals($locked->editFingerprint(), $request->string('policy_revision')->toString())) {
                throw ValidationException::withMessages(['policy_revision' => 'La polizza è stata aggiornata dopo l’apertura del modulo. Le tue modifiche non sono state salvate.']);
            }
            if ($locked->work_closed_at && auth()->user()->canManageSystem() && mb_strlen(trim((string) $request->input('correction_reason'))) < 10) {
                throw ValidationException::withMessages(['correction_reason' => 'Indica il motivo della correzione dopo la fine lavorazione (almeno 10 caratteri).']);
            }
            $data = app(\App\Services\PolicyProviders::class)->values(app(\App\Services\InsuranceCatalog::class)->policyValues($request->safe()->except(['correction_reason', 'policy_revision']), $locked), $locked);
            if (($data['category'] !== $locked->category || ($data['subcategory'] ?? null) !== $locked->subcategory)
                && \App\Models\ClientDocumentRequest::where('insurance_policy_id', $locked->id)->whereNotNull('insurance_branch_id')->exists()) {
                throw ValidationException::withMessages(['category' => 'La polizza ha una checklist documentale collegata al ramo. Conserva la classificazione per mantenere coerenti richieste e documenti.']);
            }
            if (array_key_exists('vehicle_id', $data) && (int) $data['vehicle_id'] !== (int) $locked->vehicle_id
                && $locked->documents()->whereNotNull('vehicle_id')->exists()) {
                throw ValidationException::withMessages(['vehicle_id' => 'La polizza ha documenti collegati al veicolo. Aggiorna prima i loro collegamenti.']);
            }
            if($locked->titles()->exists() || $locked->insurance_quote_id || $locked->changes()->exists() || $locked->incomingChanges()->exists()) {
                $candidate=clone $locked; $candidate->fill($data);
                foreach(['starts_on','expires_on','premium','payment_frequency','installment_premium','supplier_company_id','supplier_agency_id','insurance_branch_id','insurance_subbranch_id','category','subcategory'] as $field) if($candidate->isDirty($field)) throw ValidationException::withMessages([$field=>'La polizza ha titoli o un preventivo approvato: conserva i dati contrattuali. Per il periodo successivo usa Rinnova.']);
            }
            $locked->fill($data);
            if (($locked->changes()->exists() || $locked->incomingChanges()->exists()) && $locked->isDirty('number')) throw ValidationException::withMessages(['number'=>'Il numero è collegato allo storico contrattuale. Per cambiarlo usa Sostituisci polizza.']);
            if ($locked->getOriginal('status')==='replaced' && $locked->changes()->where('kind','replacement')->exists() && $locked->isDirty('status')) throw ValidationException::withMessages(['status'=>'La polizza ha una sostitutiva collegata. Conserva lo stato Sostituita.']);
            if(in_array($locked->status,InsurancePolicy::CLOSED_STATUSES,true) && !$locked->closed_at)$locked->closed_at=now();
            $locked->save();
            if ($locked->work_closed_at && auth()->user()->canManageSystem()) app(\App\Services\InsuranceLedger::class)->log($locked, 'policy_corrected', 'correzione admin dopo la fine lavorazione: '.$request->input('correction_reason'));
        });
        return redirect()->route('clients.policies.show', [$client, $policy])->with('success', 'Polizza aggiornata.');
    }

    public function destroy(Client $client, InsurancePolicy $policy): RedirectResponse
    {
        $this->authorize('system.admin');
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $policy->client_id === (int) $client->id, 404);
        DB::transaction(function () use ($policy) {
            $locked = InsurancePolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();
            if ($locked->claims()->exists() || $locked->formPackages()->exists() || $locked->coverageChecks()->exists()) throw ValidationException::withMessages(['policy'=>'La polizza contiene sinistri, moduli o controlli RCA da conservare. Impostala come cessata.']);
            if ($locked->work_closed_at || $locked->corrections()->exists()) throw ValidationException::withMessages(['policy' => 'La polizza ha una lavorazione chiusa o segnalazioni registrate. Conservala nello storico.']);
            if($locked->titles()->exists() || $locked->insurance_quote_id) throw ValidationException::withMessages(['policy'=>'La polizza ha titoli o un preventivo approvato. Conservala nello storico.']);
            if ($locked->documents()->exists()) {
                throw ValidationException::withMessages(['policy' => 'La polizza contiene documenti. Impostala come cessata per conservarne lo storico.']);
            }
            if ($locked->consents()->exists()) {
                throw ValidationException::withMessages(['policy' => 'La polizza è collegata allo storico dei consensi. Impostala come cessata per conservarla.']);
            }
            if (\App\Models\ClientDocumentRequest::where('insurance_policy_id', $locked->id)->exists()) throw ValidationException::withMessages(['policy' => 'La polizza ha richieste documentali collegate. Conservala come cessata.']);
            if ($locked->importEntry()->exists()) throw ValidationException::withMessages(['policy' => 'La polizza ha un riferimento all’importazione Excel. Impostala come cessata per conservarne lo storico.']);
            if (DB::table('agency_communication_policies')->where('insurance_policy_id', $locked->id)->exists()) throw ValidationException::withMessages(['policy' => 'La polizza è collegata a una comunicazione. Impostala come cessata per conservarne lo storico.']);
            $locked->delete();
        });
        return redirect()->route('clients.show', [$client, 'tab' => 'policies'])->with('success', 'Polizza eliminata.');
    }

    public function form(Client $client, InsurancePolicy $policy, bool $globalCreate = false): View
    {
        request()->validate(['entry_mode' => ['nullable', 'in:new,existing'], 'quote_id' => ['nullable', 'integer', 'min:1']]);
        $existingEntry = !$policy->exists && (request('entry_mode') ?? old('entry_mode')) === 'existing';
        if ($existingEntry) $this->authorize('system.admin');
        $approvedQuotes=\App\Models\InsuranceQuote::where('status','approved')->whereDate('valid_until','>=',today())->whereDoesntHave('policy')->whereDoesntHave('policyChange')->whereHas('quoteCase',fn($q)=>$q->where('purpose','new')->where('status','approved')->visibleTo(auth()->user())->when($client->exists,fn($q)=>$q->where('client_id',$client->id)))->with(['quoteCase.client','company'])->orderByDesc('id')->get()->filter(fn($quote)=>auth()->user()->can('updateRegistry',$quote->quoteCase->client));
        $selectedQuote=null;
        if(!$policy->exists && ($quoteId=request()->integer('quote_id') ?: (int)old('insurance_quote_id'))) {
            $selectedQuote=$approvedQuotes->firstWhere('id',$quoteId);abort_unless($selectedQuote,404);
            $client=$selectedQuote->quoteCase->client;$this->authorize('updateRegistry',$client);
            $policy->fill(app(\App\Services\InsuranceQuoteWorkflow::class)->policyValues($selectedQuote));
        }
        abort_if($existingEntry && $selectedQuote, 422);
        if (!$policy->exists && !$selectedQuote && !$existingEntry) {
            return view('policies.start', compact('client', 'approvedQuotes'));
        }
        $vehicles = $client->vehicles()->orderBy('plate')->get();
        $assets = $client->assets()->orderBy('name')->get();
        $subcategories = $client->policies()->whereNotNull('subcategory')->get(['category', 'subcategory'])->groupBy('category')->map(fn ($rows) => $rows->pluck('subcategory')->unique()->values());
        $categories = collect(InsurancePolicy::CATEGORY_SUGGESTIONS)->merge($client->policies()->distinct()->pluck('category'))->unique();
        $catalog = \App\Models\InsuranceBranch::with(['subbranches' => fn ($q) => $q->where('status', 'active')])->where('status', 'active')->orderBy('name')->get();
        $usesCatalog = \App\Models\InsuranceBranch::exists();
        if ($usesCatalog) {
            $categories = $catalog->pluck('name');
            $subcategories = $catalog->mapWithKeys(fn ($branch) => [$branch->name => $branch->subbranches->pluck('name')->values()->all()]);
            if ($policy->exists && filled($policy->category)) {
                $categories = $categories->push($policy->category)->unique()->values();
                if (filled($policy->subcategory)) $subcategories[$policy->category] = collect($subcategories[$policy->category] ?? [])->push($policy->subcategory)->unique()->values()->all();
            }
        }
        $companies = \App\Models\SupplierCompany::with(['agencies' => fn ($q) => $q->where(fn ($q) => $q->where('supplier_agencies.status', 'active')->orWhere('supplier_agencies.id', $policy->supplier_agency_id))])->where('status', 'active')->orWhere('id', $policy->supplier_company_id)->orderBy('name')->get();
        $agenciesByCompany = $companies->mapWithKeys(fn ($company) => [\App\Services\InsuranceCatalog::normalize($company->name) => $company->agencies->map(fn ($agency) => ['id' => $agency->id, 'name' => $agency->name])->values()->all()]);
        if ($policy->supplier_company_id && $policy->company && ($originalCompany = $companies->firstWhere('id', $policy->supplier_company_id))) $agenciesByCompany[\App\Services\InsuranceCatalog::normalize($policy->company)] = $originalCompany->agencies->map(fn ($agency) => ['id' => $agency->id, 'name' => $agency->name])->values()->all();
        return view('clients.policies.form', compact('client', 'policy', 'vehicles', 'assets', 'categories', 'subcategories', 'usesCatalog', 'companies', 'agenciesByCompany', 'globalCreate','approvedQuotes','selectedQuote'));
    }
}
