<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientHousehold;
use App\Models\ClientRelationship;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientNetworkController extends Controller
{
    public function relationshipForm(Client $client, ?ClientRelationship $relationship = null)
    {
        $this->authorize('updateRegistry', $client);
        if ($relationship?->exists) {
            $this->checkRelationship($client, $relationship);
        }
        $relationship ??= new ClientRelationship;
        $candidates = $this->candidates($client);

        return view('clients.network.relationship', compact('client', 'relationship', 'candidates'));
    }

    public function saveRelationship(Request $request, Client $client, ?ClientRelationship $relationship = null)
    {
        $this->authorize('updateRegistry', $client);
        if ($relationship?->exists) {
            $this->checkRelationship($client, $relationship);
        }
        $data = $request->validate([
            'related_client_id' => ['required', 'integer', 'different:client_id'],
            'relationship_type' => ['required', Rule::in(array_keys(ClientRelationship::TYPES))],
            'client_role' => ['nullable', 'string', 'max:150'], 'related_role' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $other = Client::current()->visibleTo($request->user())->lockForUpdate()->findOrFail($data['related_client_id']);
        $this->authorize('updateRegistry', $other);
        if ($client->is($other)) {
            throw ValidationException::withMessages(['related_client_id' => 'Seleziona un altro cliente.']);
        }
        if ($relationship?->exists && ! $relationship->other($client)->is($other)) {
            throw ValidationException::withMessages(['related_client_id' => 'Per cambiare il cliente collegato, rimuovi questa relazione e creane una nuova.']);
        }
        $data['client_id'] = min($client->id, $other->id);
        $data['related_client_id'] = max($client->id, $other->id);
        if ($client->id > $other->id) {
            [$data['client_role'], $data['related_role']] = [$data['related_role'] ?? null, $data['client_role'] ?? null];
        }
        if (ClientRelationship::where('client_id', $data['client_id'])->where('related_client_id', $data['related_client_id'])->when($relationship?->exists, fn ($q) => $q->whereKeyNot($relationship->id))->exists()) {
            throw ValidationException::withMessages(['related_client_id' => 'Questi clienti sono già collegati. Modifica la relazione esistente.']);
        }
        $before = $relationship?->getAttributes();
        $relationship ??= new ClientRelationship;
        $relationship->fill($data)->save();
        foreach ([$client, $other] as $person) {
            $this->audit($person, 'Relazione tra clienti salvata.', $before, $relationship->getAttributes());
        }

        return $this->back($client);
    }

    public function deleteRelationship(Client $client, ClientRelationship $relationship)
    {
        $this->authorize('system.admin');
        $this->authorize('updateRegistry', $client);
        $this->checkRelationship($client, $relationship);
        $other = $relationship->other($client);
        $before = $relationship->getAttributes();
        $relationship->delete();
        foreach ([$client, $other] as $person) {
            $this->audit($person, 'Relazione tra clienti rimossa.', $before, null);
        }

        return $this->back($client);
    }

    public function householdForm(Client $client, ?ClientHousehold $household = null)
    {
        $this->authorize('updateRegistry', $client);
        if ($household?->exists) {
            $this->checkHousehold($client, $household);
        }
        $household ??= new ClientHousehold;
        $candidates = $this->candidates($client)->prepend($client)->where('client_type', 'person');

        return view('clients.network.household', compact('client', 'household', 'candidates'));
    }

    public function saveHousehold(Request $request, Client $client, ?ClientHousehold $household = null)
    {
        $this->authorize('updateRegistry', $client);
        if ($household?->exists) {
            $this->checkHousehold($client, $household);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:10000'],
            'members' => ['required', 'array', 'min:1', 'max:50'], 'members.*' => ['required', 'integer', 'distinct'],
            'roles' => ['nullable', 'array', 'max:50'], 'roles.*' => ['nullable', 'string', 'max:150'],
        ]);
        $ids = array_map('intval', $data['members']);
        $members = Client::current()->visibleTo($request->user())->whereIn('id', $ids)->where('client_type', 'person')->orderBy('id')->lockForUpdate()->get();
        if (! in_array($client->id, $ids, true) || $members->count() !== count($ids)) {
            throw ValidationException::withMessages(['members' => 'Seleziona persone fisiche accessibili e includi il cliente di questa scheda.']);
        }
        foreach ($members as $member) {
            $this->authorize('updateRegistry', $member);
        }
        $before = $household?->exists ? $household->load('members')->toArray() : null;
        $oldMembers = $household?->exists ? $household->members : collect();
        $household ??= new ClientHousehold;
        $household->fill(['name' => $data['name'], 'notes' => $data['notes'] ?? null])->save();
        $household->members()->sync(collect($ids)->mapWithKeys(fn ($id) => [$id => ['role' => $data['roles'][$id] ?? null]])->all());
        $after = $household->fresh('members')->toArray();
        foreach ($members->merge($oldMembers)->unique('id') as $member) {
            $this->audit($member, 'Nucleo familiare aggiornato.', $before, $after);
        }

        return $this->back($client);
    }

    public function deleteHousehold(Client $client, ClientHousehold $household)
    {
        $this->authorize('system.admin');
        $this->authorize('updateRegistry', $client);
        $this->checkHousehold($client, $household);
        $before = $household->toArray();
        $members = $household->members;
        $household->delete();
        foreach ($members as $member) {
            $this->audit($member, 'Nucleo familiare rimosso.', $before, null);
        }

        return $this->back($client);
    }

    private function checkRelationship(Client $client, ClientRelationship $relationship): void
    {
        abort_unless(in_array($client->id, [$relationship->client_id, $relationship->related_client_id], true), 404);
        $other = Client::query()->when(! request()->isMethodSafe(), fn ($q) => $q->lockForUpdate())->findOrFail($relationship->other($client)->id);
        abort_if($other->merged_into_id, 409);
        $this->authorize('view', $other);
        $this->authorize('updateRegistry', $other);
    }

    private function checkHousehold(Client $client, ClientHousehold $household): void
    {
        $household->load(['members' => fn ($q) => $q->when(! request()->isMethodSafe(), fn ($q) => $q->lockForUpdate())->orderBy('clients.id')]);
        abort_unless($household->members->contains('id', $client->id), 404);
        foreach ($household->members as $member) {
            abort_if($member->merged_into_id, 409);
            $this->authorize('view', $member);
            $this->authorize('updateRegistry', $member);
        }
    }

    private function candidates(Client $client)
    {
        return Client::current()->visibleTo(auth()->user())->whereKeyNot($client->id)->orderBy('name')->get()
            ->filter(fn ($candidate) => auth()->user()->can('updateRegistry', $candidate));
    }

    private function audit(Client $client, string $description, ?array $before, ?array $after): void
    {
        $client->auditLogs()->create(['user_id' => auth()->id(), 'action' => 'updated', 'description' => $description, 'old_values' => $before, 'new_values' => $after, 'created_at' => now()]);
    }

    private function back(Client $client)
    {
        return redirect()->route('clients.show', [$client, 'tab' => 'network'])->with('success', 'Collegamenti aggiornati.');
    }
}
