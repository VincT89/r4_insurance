<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClientVehicleRequest;
use App\Models\{Client, ClientVehicle, SupplierAgency, SupplierCompany};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientVehicleController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Client::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'client' => ['nullable', 'string', 'max:255'],
            'plate' => ['nullable', 'string', 'max:20'],
            'merit_class_cu' => ['nullable', 'integer', 'between:1,18'],
            'company_id' => ['nullable', 'integer', 'exists:supplier_companies,id'],
            'agency_id' => ['nullable', 'integer', 'exists:supplier_agencies,id'],
            'expires_from' => ['nullable', 'date_format:Y-m-d'],
            'expires_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('expires_from') ? ['after_or_equal:expires_from'] : [])],
        ], ['expires_to.after_or_equal' => 'La scadenza finale deve essere uguale o successiva a quella iniziale.']);
        $policies = function ($query) use ($filters) {
            $query->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('supplier_company_id', $id))
                ->when($filters['agency_id'] ?? null, fn ($q, $id) => $q->where('supplier_agency_id', $id))
                ->when($filters['expires_from'] ?? null, fn ($q, $date) => $q->whereDate('expires_on', '>=', $date))
                ->when($filters['expires_to'] ?? null, fn ($q, $date) => $q->whereDate('expires_on', '<=', $date));
        };
        $hasPolicyFilters = collect($filters)->only(['company_id', 'agency_id', 'expires_from', 'expires_to'])->filter()->isNotEmpty();
        $vehicles = ClientVehicle::query()
            ->whereHas('client', function ($query) use ($request, $filters) {
                $query->current()->visibleTo($request->user());
                if (filled($filters['client'] ?? null)) {
                    $name = '%'.mb_strtolower(trim($filters['client'])).'%';
                    $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$name])->orWhereRaw('LOWER(company_name) LIKE ?', [$name]));
                }
            })
            ->when(filled($filters['plate'] ?? null), fn ($q) => $q->where('plate', 'like', '%'.strtoupper(preg_replace('/\s+/', '', $filters['plate'])).'%'))
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters) {
                $term = trim($filters['search']);
                $plate = '%'.strtoupper(preg_replace('/\s+/', '', $term)).'%';
                $name = '%'.mb_strtolower($term).'%';
                $query->where(function ($match) use ($plate, $name) {
                    $match->where('plate', 'like', $plate)->orWhereHas('client', fn ($client) =>
                        $client->where(fn ($identity) => $identity->whereRaw('LOWER(name) LIKE ?', [$name])->orWhereRaw('LOWER(company_name) LIKE ?', [$name]))
                    );
                });
            })
            ->when($filters['merit_class_cu'] ?? null, fn ($q, $cu) => $q->where('merit_class_cu', $cu))
            /** Tutti i filtri assicurativi devono corrispondere alla stessa polizza del veicolo. */
            ->when($hasPolicyFilters, fn ($q) => $q->whereHas('policies', $policies))
            ->with(['client:id,name', 'meritClassCompany:id,name', 'policies' => function ($query) use ($policies) {
                $policies($query);
                $query->with('supplierAgency:id,name')->orderByDesc('starts_on')->orderByDesc('id');
            }])
            ->orderBy('plate')->orderBy('id')->paginate(\App\Support\TableListing::perPage(20))->withQueryString();
        $companies = SupplierCompany::orderBy('name')->get(['id', 'name']);
        $agencies = SupplierAgency::orderBy('name')->get(['id', 'name']);

        return view('clients.vehicles.index', compact('vehicles', 'companies', 'agencies', 'hasPolicyFilters'));
    }

    public function create(Client $client): View
    {
        $this->authorize('updateRegistry', $client);
        return view('clients.vehicles.form', ['client' => $client, 'vehicle' => new ClientVehicle]);
    }

    public function store(SaveClientVehicleRequest $request, Client $client)
    {
        $vehicle = $this->save($request, $client, new ClientVehicle);
        if ($request->expectsJson()) return response()->json($vehicle->only(['id','plate','make','model','merit_class_cu','merit_class_internal','merit_class_company_id']), 201);
        return redirect()->route('clients.show', [$client, 'tab' => 'vehicles'])->with('success', 'Veicolo aggiunto al cliente.');
    }

    public function edit(Client $client, ClientVehicle $vehicle): View
    {
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $vehicle->client_id === (int) $client->id, 404);
        return view('clients.vehicles.form', compact('client', 'vehicle'));
    }

    public function update(SaveClientVehicleRequest $request, Client $client, ClientVehicle $vehicle): RedirectResponse
    {
        $this->save($request, $client, $vehicle);
        return redirect()->route('clients.show', [$client, 'tab' => 'vehicles'])->with('success', 'Veicolo aggiornato.');
    }

    public function destroy(Client $client, ClientVehicle $vehicle): RedirectResponse
    {
        $this->authorize('system.admin');
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $vehicle->client_id === (int) $client->id, 404);
        DB::transaction(function () use ($vehicle) {
            $locked = ClientVehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            if ($locked->policies()->exists() || $locked->documents()->exists() || $locked->quoteCases()->exists()) {
                throw ValidationException::withMessages(['vehicle' => 'Il veicolo è collegato a polizze, preventivi o documenti e deve essere conservato.']);
            }
            $locked->delete();
        });
        return redirect()->route('clients.show', [$client, 'tab' => 'vehicles'])->with('success', 'Veicolo eliminato.');
    }

    private function save(SaveClientVehicleRequest $request, Client $client, ClientVehicle $vehicle): ClientVehicle
    {
        $stored = [];
        $files = $request->file('registration_files', []);
        if (collect($files)->sum(fn($file) => $file->getSize()) > 20 * 1024 * 1024) throw ValidationException::withMessages(['registration_files' => 'I libretti non possono superare 20 MB complessivi.']);
        try {
            return DB::transaction(function () use ($request, $client, $vehicle, $files, &$stored) {
                /** Serialize registry additions with quote creation so the same client cannot acquire duplicate plates. */
                Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
                if ($client->vehicles()->where('plate', $request->validated('plate'))->when($vehicle->exists, fn($q) => $q->whereKeyNot($vehicle->id))->exists()) throw ValidationException::withMessages(['plate' => 'La targa è già presente nella scheda di questo cliente.']);
                if ($vehicle->exists) $vehicle = $client->vehicles()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
                $vehicle->fill(\Illuminate\Support\Arr::except($request->validated(), ['registration_files']));
                $vehicle->client()->associate($client);
                $vehicle->save();
                foreach ($files as $file) $stored[] = app(\App\Services\ClientDocumentRegistry::class)->upload($client, $file, ['vehicle_id'=>$vehicle->id, 'document_category'=>'vehicle']);
                return $vehicle;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $document) Storage::disk($document->disk)->delete($document->path);
            throw $exception;
        }
    }
}
