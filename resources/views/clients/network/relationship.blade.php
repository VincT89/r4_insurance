<x-app-layout title="Relazione tra clienti">
    <x-page-header><x-slot:title>Relazione tra clienti</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab' => 'network']) }}">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <form method="POST" action="{{ $relationship->exists ? route('clients.relationships.update', [$client, $relationship]) : route('clients.relationships.store', $client) }}">
            @csrf @if($relationship->exists) @method('PATCH') @endif
            <x-form-section title="Clienti e relazione">
            <p class="u-text-meta u-mb-md">Collega due anagrafiche già presenti. Il collegamento sarà visibile nelle schede di entrambi, agli utenti che possono consultarle.</p>
            <div class="form-row">
                <x-form-group label="Cliente collegato" name="related_client_id" required><select id="field-related-client-id" name="related_client_id" class="form-sel" required><option value="">Seleziona</option>@foreach($candidates as $candidate)<option value="{{ $candidate->id }}" @selected((string)old('related_client_id', $relationship->exists ? $relationship->other($client)->id : '') === (string)$candidate->id)>{{ $candidate->name }} / {{ $candidate->client_type_label }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Tipo di relazione" name="relationship_type" required><select id="field-relationship-type" name="relationship_type" class="form-sel" required><option value="">Seleziona</option>@foreach(\App\Models\ClientRelationship::TYPES as $key => $label)<option value="{{ $key }}" @selected(old('relationship_type', $relationship->relationship_type) === $key)>{{ $label }}</option>@endforeach</select></x-form-group>
                <x-form-group :label="'Ruolo di '.$client->name" name="client_role"><input id="field-client-role" name="client_role" class="form-in" maxlength="150" value="{{ old('client_role', $relationship->exists ? $relationship->roleFor($client) : '') }}"></x-form-group>
                <x-form-group label="Ruolo del cliente collegato" name="related_role"><input id="field-related-role" name="related_role" class="form-in" maxlength="150" value="{{ old('related_role', $relationship->exists ? $relationship->roleFor($relationship->other($client)) : '') }}"></x-form-group>
            </div>
            <x-form-group label="Note sulla relazione" name="notes"><textarea id="field-notes" name="notes" class="form-ta" maxlength="10000" rows="3">{{ old('notes', $relationship->notes) }}</textarea></x-form-group>
            </x-form-section>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab' => 'network']) }}">Annulla</a><button class="btn btn-p" type="submit">Salva relazione</button></div>
        </form>
    </x-panel>
</x-app-layout>
