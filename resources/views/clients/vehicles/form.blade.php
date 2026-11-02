<x-app-layout :title="$vehicle->exists ? 'Modifica veicolo' : 'Aggiungi veicolo'">
    <x-page-header><x-slot:title>{{ $vehicle->exists ? 'Modifica veicolo' : 'Aggiungi veicolo' }}</x-slot:title><x-slot:actions><a href="{{ route('clients.show', [$client, 'tab' => 'vehicles']) }}" class="btn btn-g">Veicoli di {{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <form method="POST" enctype="multipart/form-data" action="{{ $vehicle->exists ? route('clients.vehicles.update', [$client, $vehicle]) : route('clients.vehicles.store', $client) }}">
            @csrf
            @if($vehicle->exists) @method('PATCH') @endif
            <p class="u-text-meta u-mb-md">Cliente: <strong>{{ $client->name }}</strong>. È obbligatoria solo la targa. Puoi completare gli altri dati in seguito.</p>
            <x-form-section title="Dati del veicolo">
                <div class="form-row">
                    @foreach(['plate' => ['Targa', 20], 'make' => ['Marca', 100], 'model' => ['Modello', 100]] as $field => [$label, $length])
                        <x-form-group :label="$label" :name="$field" :required="$field === 'plate'"><input id="field-{{ $field }}" name="{{ $field }}" value="{{ old($field, $vehicle->$field) }}" class="form-in" maxlength="{{ $length }}" @required($field === 'plate')></x-form-group>
                    @endforeach
                    <x-form-group label="Classe di merito CU" name="merit_class_cu"><select id="field-merit-class-cu" class="form-sel" name="merit_class_cu"><option value="">Non indicata</option>@for($cu = 1; $cu <= 18; $cu++)<option value="{{ $cu }}" @selected((string) old('merit_class_cu', $vehicle->merit_class_cu) === (string) $cu)>{{ $cu }}</option>@endfor</select><p class="u-text-meta">La classe si riferisce a questo veicolo. Puoi trovarla nell’attestato di rischio.</p></x-form-group>
                </div>
            </x-form-section>
            <details class="vehicle-extra-fields" @if($errors->hasAny(['vin', 'registered_on', 'merit_class_internal', 'merit_class_company_id', 'notes']) || old('vin', $vehicle->vin) || old('registered_on', $vehicle->registered_on) || old('merit_class_internal', $vehicle->merit_class_internal) || old('merit_class_company_id', $vehicle->merit_class_company_id) || old('notes', $vehicle->notes)) open @endif>
                <summary>Altri dati del veicolo e classe della compagnia</summary>
                <div class="form-row u-mt-md">
                    <x-form-group label="Numero di telaio" name="vin"><input id="field-vin" class="form-in" name="vin" maxlength="30" value="{{ old('vin', $vehicle->vin) }}"></x-form-group>
                    <x-form-group label="Prima immatricolazione" name="registered_on"><input id="field-registered-on" type="date" name="registered_on" value="{{ old('registered_on', $vehicle->registered_on?->toDateString()) }}" class="form-in" max="{{ today()->toDateString() }}"></x-form-group>
                    <x-form-group label="Classe della compagnia" name="merit_class_internal"><input id="field-merit-class-internal" class="form-in" name="merit_class_internal" maxlength="30" value="{{ old('merit_class_internal', $vehicle->merit_class_internal) }}"></x-form-group>
                    <x-form-group label="Compagnia di riferimento" name="merit_class_company_id"><select id="field-merit-class-company-id" class="form-sel" name="merit_class_company_id"><option value="">Seleziona se inserisci la classe della compagnia</option>@foreach(\App\Models\SupplierCompany::orderBy('name')->get() as $classCompany)<option value="{{ $classCompany->id }}" @selected((string) old('merit_class_company_id', $vehicle->merit_class_company_id) === (string) $classCompany->id)>{{ $classCompany->name }}</option>@endforeach</select></x-form-group>
                </div>
                <x-form-group label="Note sul veicolo" name="notes"><textarea id="field-notes" name="notes" rows="3" maxlength="10000" class="form-ta">{{ old('notes', $vehicle->notes) }}</textarea></x-form-group>
            </details>
            <x-form-section title="Libretto del veicolo">
                @include('clients.vehicles.registration-upload', ['registrationField' => 'registration_files'])
                @if($vehicle->exists && $vehicle->documents->isNotEmpty())
                    <p class="u-text-meta u-mt-md">Documenti già caricati:</p>
                    <ul>@foreach($vehicle->documents as $document)<li><a href="{{ $document->downloadUrl() }}">{{ $document->original_name }}</a></li>@endforeach</ul>
                @endif
            </x-form-section>
            <div class="insurance-form-actions"><a href="{{ route('clients.show', [$client, 'tab' => 'vehicles']) }}" class="btn btn-g">Annulla</a><button type="submit" class="btn btn-p">Salva veicolo</button></div>
        </form>
    </x-panel>
    @if($vehicle->exists)@can('system.admin')<x-delete-modal action="{{ route('clients.vehicles.destroy', [$client, $vehicle]) }}" title="Elimina veicolo" message="Il veicolo può essere eliminato solo se non è collegato a polizze, preventivi o documenti."><button type="button" class="btn btn-g btn-danger-outline">Elimina veicolo</button></x-delete-modal>@endcan @endif
</x-app-layout>
