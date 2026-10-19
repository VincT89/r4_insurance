<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveClientVehicleRequest;
use App\Models\{Client, ClientVehicle};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientVehicleController extends Controller
{
    public function create(Client $client): View
    {
        $this->authorize('updateRegistry', $client);
        return view('clients.vehicles.form', ['client' => $client, 'vehicle' => new ClientVehicle]);
    }

    public function store(SaveClientVehicleRequest $request, Client $client): RedirectResponse
    {
        DB::transaction(fn () => $client->vehicles()->create($request->validated()));
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
        DB::transaction(fn () => $vehicle->update($request->validated()));
        return redirect()->route('clients.show', [$client, 'tab' => 'vehicles'])->with('success', 'Veicolo aggiornato.');
    }

    public function destroy(Client $client, ClientVehicle $vehicle): RedirectResponse
    {
        $this->authorize('system.admin');
        $this->authorize('updateRegistry', $client);
        abort_unless((int) $vehicle->client_id === (int) $client->id, 404);
        DB::transaction(function () use ($vehicle) {
            $locked = ClientVehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            if ($locked->policies()->exists() || $locked->documents()->exists()) {
                throw ValidationException::withMessages(['vehicle' => 'Il veicolo è collegato a polizze o documenti e deve essere conservato.']);
            }
            $locked->delete();
        });
        return redirect()->route('clients.show', [$client, 'tab' => 'vehicles'])->with('success', 'Veicolo eliminato.');
    }
}
