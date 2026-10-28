<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClientVehicleRequest;
use App\Models\{Client, ClientVehicle};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientVehicleController extends Controller
{
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
