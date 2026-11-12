<div data-contract-new-vehicle hidden>
    <label class="contract-confirm"><input type="checkbox" data-create-contract-vehicle> Salva questo veicolo nella scheda cliente e collegalo alla polizza</label>
    <div class="form-row" data-contract-vehicle-fields hidden>
        @foreach(['plate'=>['Targa',20], 'make'=>['Marca',100], 'model'=>['Modello',100], 'vin'=>['Telaio',30]] as $key=>$details)
            <x-form-group :name="'new_vehicle.'.$key" :label="$details[0]" :required="$key==='plate'"><input class="form-in" data-contract-vehicle-field="{{ $key }}" name="new_vehicle[{{ $key }}]" maxlength="{{ $details[1] }}" value="{{ old('new_vehicle.'.$key) }}" disabled></x-form-group>
        @endforeach
        <x-form-group name="new_vehicle.registered_on" label="Prima immatricolazione"><input class="form-in" data-contract-vehicle-field="registered_on" type="date" name="new_vehicle[registered_on]" max="{{ today()->toDateString() }}" value="{{ old('new_vehicle.registered_on') }}" disabled></x-form-group>
    </div>
    <p class="u-text-meta">Il veicolo viene salvato alla conferma della pratica. Una targa già presente per questo cliente viene collegata senza duplicarla.</p>
</div>
