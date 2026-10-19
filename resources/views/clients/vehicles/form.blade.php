<x-app-layout :title="$vehicle->exists ? 'Modifica veicolo' : 'Aggiungi veicolo'">
    <x-page-header><x-slot:title>{{ $vehicle->exists ? 'Modifica veicolo' : 'Aggiungi veicolo' }}</x-slot:title><x-slot:actions><a href="{{ route('clients.show', [$client, 'tab' => 'vehicles']) }}" class="btn btn-g">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <form method="POST" action="{{ $vehicle->exists ? route('clients.vehicles.update', [$client, $vehicle]) : route('clients.vehicles.store', $client) }}">
            @csrf
            @if($vehicle->exists) @method('PATCH') @endif
            <x-form-section title="Dati del veicolo">
            <div class="form-row">
                @foreach(['plate' => ['Targa', 20], 'make' => ['Marca', 100], 'model' => ['Modello', 100], 'vin' => ['Numero di telaio', 30]] as $field => [$label, $length])
                    <x-form-group :label="$label" :name="$field" :required="$field === 'plate'"><input id="field-{{ $field }}" name="{{ $field }}" value="{{ old($field, $vehicle->$field) }}" class="form-in" maxlength="{{ $length }}" @required($field === 'plate')></x-form-group>
                @endforeach
                <x-form-group label="Prima immatricolazione" name="registered_on"><input id="field-registered-on" type="date" name="registered_on" value="{{ old('registered_on', $vehicle->registered_on?->toDateString()) }}" class="form-in" max="{{ today()->toDateString() }}"></x-form-group>
            </div>
            </x-form-section><x-form-section title="Note"><x-form-group label="Note sul veicolo" name="notes"><textarea id="field-notes" name="notes" rows="4" maxlength="10000" class="form-ta">{{ old('notes', $vehicle->notes) }}</textarea></x-form-group></x-form-section>
            <div class="insurance-form-actions"><a href="{{ route('clients.show', [$client, 'tab' => 'vehicles']) }}" class="btn btn-g">Annulla</a><button type="submit" class="btn btn-p">Salva veicolo</button></div>
        </form>
    </x-panel>
    @if($vehicle->exists)@can('system.admin')<x-delete-modal action="{{ route('clients.vehicles.destroy', [$client, $vehicle]) }}" title="Elimina veicolo" message="Il veicolo può essere eliminato solo se non è collegato a polizze o documenti."><button type="button" class="btn btn-g btn-danger-outline">Elimina veicolo</button></x-delete-modal>@endcan @endif
</x-app-layout>
