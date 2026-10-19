<?php

namespace App\Http\Controllers;

use App\Http\Requests\{QuickStoreClientRequest, StoreClientRequest, UpdateClientRequest};
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function index(\Illuminate\Http\Request $request, \App\Domain\Core\Queries\ClientQuery $clientQuery): View
    {
        $this->authorize('viewAny', Client::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'client_type' => ['nullable', 'in:person,company,unclassified'],
            'import_review' => ['nullable', 'in:1'],
        ]);
        $clients = $clientQuery->forIndex($filters)->paginate(\App\Support\TableListing::perPage(20))->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        $this->authorize('create', Client::class);
        return view('clients.create', ['commercialUsers' => $this->commercialUsers()]);
    }

    public function store(StoreClientRequest $request, \App\Actions\Clients\CreateClientAction $action): RedirectResponse
    {
        $client = null;
        try {
            $client = $this->withDocuments(function () use ($action, $request, &$client) { return $client = $action->execute($request->safe()->except('documents')); }, $request->file('documents', []));
        } catch (\Throwable $e) { if ($client?->logo_path) Storage::disk('public')->delete($client->logo_path); throw $e; }

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente creato correttamente.');
    }

    public function show(Request $request, Client $client, \App\Domain\Core\Queries\ClientDeadlineQuery $deadlineQuery): View
    {
        $this->authorize('view', $client);
        $filters = $request->validate([
            'tab' => ['nullable', 'in:policies,registry,vehicles,documents,deadlines,network,assets,history,consents'],
            'category' => ['nullable', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'period' => ['nullable', 'in:all,overdue,7,15,30,45,60,custom'],
            'document_view' => ['nullable', 'in:current,history'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $tab = $filters['tab'] ?? 'policies';
        $client->load('commercialUser:id,name');
        $policies = $tab === 'policies' ? $client->policies()->with(['vehicle', 'asset', 'supplierAgency'])->orderBy('category')->orderBy('subcategory')->orderBy('expires_on')->get() : collect();
        $policyCategories = (\App\Models\InsuranceBranch::exists() ? collect() : collect(\App\Models\InsurancePolicy::CATEGORY_SUGGESTIONS))->merge($policies->pluck('category'))->unique()->values();
        if (filled($filters['category'] ?? null)) {
            $policies = $policies->where('category', $filters['category']);
        }
        $policySubcategories = filled($filters['category'] ?? null) ? $policies->pluck('subcategory')->filter()->unique()->values() : collect();
        if (filled($filters['subcategory'] ?? null)) $policies = $policies->where('subcategory', $filters['subcategory']);
        $vehicles = $tab === 'vehicles' ? $client->vehicles()->withCount('policies')->orderBy('plate')->get() : collect();
        $documents = $tab === 'documents' ? $client->attachments()->whereNull('client_material_category')
            ->when(($filters['document_view'] ?? 'current') === 'current', fn ($q) => $q->whereDoesntHave('newerVersion'))
            ->with(['uploader', 'insurancePolicy.vehicle', 'vehicle', 'newerVersion', 'validator'])->latest()->get() : collect();
        $documentRequests = $tab === 'documents' ? $client->documentRequests()->with(['documents', 'insurancePolicy', 'branch', 'requester'])->orderBy('due_on')->get() : collect();
        $deadlines = $tab === 'deadlines' ? $deadlineQuery->forClient($client, $filters) : collect();
        $contacts = $tab === 'network' ? $client->contacts()->orderBy('name')->get() : collect();
        $relationships = $tab === 'network' ? $client->relationships()->with(['client', 'relatedClient'])->get()
            ->filter(fn ($relation) => !$relation->other($client)->merged_into_id && $request->user()->can('view', $relation->other($client))) : collect();
        $households = $tab === 'network' ? $client->households()->with('members')->get()
            ->filter(fn ($household) => $household->members->every(fn ($member) => !$member->merged_into_id && $request->user()->can('view', $member))) : collect();
        $assets = $tab === 'assets' ? $client->assets()->with('policies')->orderBy('name')->get() : collect();
        $interactions = $tab === 'history' ? $client->interactions()->with('author')->orderByDesc('event_at')->orderByDesc('id')->paginate(\App\Support\TableListing::perPage(15))->withQueryString() : collect();
        $consents = $tab === 'consents' ? $client->consents()->with(['author', 'attachment', 'insurancePolicy'])->orderByDesc('event_at')->orderByDesc('id')->paginate(\App\Support\TableListing::perPage(15))->withQueryString() : collect();
        return view('clients.show', compact('client', 'tab', 'policies', 'policyCategories', 'policySubcategories', 'vehicles', 'documents', 'documentRequests', 'deadlines', 'contacts', 'relationships', 'households', 'assets', 'interactions', 'consents'));
    }

    public function destroy(Client $client, \App\Domain\Core\Actions\DeleteClientAction $action): RedirectResponse
    {
        $this->authorize('delete', $client);
        $action->execute($client);
        return redirect()->route('clients.index')->with('success', 'Cliente eliminato correttamente.');
    }

    public function edit(Client $client): View
    {
        $this->authorize('updateRegistry', $client);
        return view('clients.edit', ['client' => $client, 'commercialUsers' => $this->commercialUsers($client)]);
    }

    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $data = $request->safe()->except('documents');
        if (($data['client_type'] ?? null) === 'company' && $client->households()->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['client_type' => 'Il cliente è inserito in un nucleo familiare. Rimuovi prima quel collegamento per convertirlo in azienda.']);
        }
        
        $oldLogo = $client->logo_path;
        $logo = $request->file('logo');
        unset($data['logo']);

        if ($logo) {
            $data['logo_path'] = $logo->store('clients/logos', 'public');
        }

        try {
            $this->withDocuments(function () use ($client, $data) { $client->update($data); return $client; }, $request->file('documents', []));
        } catch (\Throwable $e) { if (isset($data['logo_path'])) Storage::disk('public')->delete($data['logo_path']); throw $e; }

        if (isset($data['logo_path']) && $oldLogo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return redirect()->route('clients.show', $client)
            ->with('success', 'Cliente aggiornato correttamente.');
    }

    private function withDocuments(callable $save, array $files): Client
    {
        $stored = [];
        try { return \Illuminate\Support\Facades\DB::transaction(function () use ($save, $files, &$stored) {
            $client = $save();
            foreach ($files as $file) $stored[] = app(\App\Services\ClientDocumentRegistry::class)->upload($client, $file, ['document_category'=>'other']);
            return $client;
        }); } catch (\Throwable $e) { foreach ($stored as $document) Storage::disk($document->disk)->delete($document->path); throw $e; }
    }

    public function search(Request $request, \App\Domain\Core\Queries\ClientQuery $clientQuery): JsonResponse
    {
        $this->authorize('lookup', Client::class);

        $filters = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $search = trim($filters['q'] ?? '');

        if (strlen($search) < 1) {
            return response()->json([]);
        }

        $clients = $clientQuery->forSearch($search)
            ->limit(10)
            ->get(['id', 'name', 'company_name', 'email', 'vat_number', 'tax_code']);

        return response()->json($clients)->header('Cache-Control', 'no-store, private');
    }

    public function quickStore(QuickStoreClientRequest $request, \App\Actions\Clients\CreateClientAction $action): JsonResponse
    {
        $this->authorize('quickCreate', Client::class);
        $client = $action->execute($request->validated());

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'company_name' => $client->company_name,
            'email' => $client->email,
            'vat_number' => $client->vat_number,
            'phone' => $client->phone,
        ], 201);
    }

    private function commercialUsers(?Client $client = null): \Illuminate\Support\Collection
    {
        if (! auth()->user()->canManageSystem()) {
            return collect();
        }

        return User::query()->where(function ($query) use ($client) {
            $query->where(fn ($active) => $active->where('role', UserRole::Operator)->where('status', 'active'));
            if ($client?->commercial_user_id) {
                $query->orWhere('id', $client->commercial_user_id);
            }
        })->orderBy('name')->get(['id', 'name', 'role', 'status']);
    }
}
