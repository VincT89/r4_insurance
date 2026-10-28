<div data-quote-vehicle data-client-base="{{ url('/clients') }}">
    <x-form-section title="Veicolo e classe di merito">
        <div class="form-row">
            <x-form-group label="Veicolo del cliente" name="vehicle_id"><select id="field-vehicle-id" name="vehicle_id" class="form-sel"><option value="">Nessun veicolo collegato</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" data-cu="{{ $vehicle->merit_class_cu }}" data-internal="{{ $vehicle->merit_class_internal }}" data-plate="{{ $vehicle->plate }}" @selected((string)old('vehicle_id',request('vehicle_id',$source?->vehicle_id))===(string)$vehicle->id)>{{ implode(' / ',array_filter([$vehicle->plate,$vehicle->make,$vehicle->model])) }}</option>@endforeach</select><p class="u-text-meta">Il veicolo e la targa restano salvati nella pratica, anche dopo eventuali modifiche dell’anagrafica.</p></x-form-group>
            <x-form-group label="Classe CU di riferimento" name="merit_class_cu"><select id="field-quote-cu" class="form-sel" name="merit_class_cu" data-initial-value="{{ old('merit_class_cu',$source?->merit_class_cu) }}"><option value="">Non indicata</option>@for($cu=1;$cu<=18;$cu++)<option value="{{ $cu }}" @selected((string)old('merit_class_cu',$source?->merit_class_cu)===(string)$cu)>{{ $cu }}</option>@endfor</select><p class="u-text-meta">Controlla la classe sull’attestato di rischio.</p></x-form-group>
            <x-form-group label="Classe interna dichiarata" name="merit_class_internal"><input id="field-quote-internal" class="form-in" name="merit_class_internal" maxlength="30" value="{{ old('merit_class_internal',$source?->merit_class_internal) }}"><p class="u-text-meta">Riferita alla compagnia del veicolo o della polizza attuale.</p></x-form-group>
        </div>
        <details class="client-detail-section" data-quick-vehicle><summary class="btn btn-g btn-sm">Aggiungi un veicolo al cliente</summary>
            <p class="u-text-meta u-mt-md">Inserisci i dati oppure leggi il libretto nella sezione documenti. Il veicolo sarà salvato nell’anagrafica prima di aprire la pratica.</p>
            <div class="form-row u-mt-md">
                @foreach(['plate'=>'Targa','make'=>'Marca','model'=>'Modello','vin'=>'Numero di telaio','registered_on'=>'Prima immatricolazione'] as $field=>$label)<div class="form-group"><label for="new-vehicle-{{ $field }}">{{ $label }}</label><input id="new-vehicle-{{ $field }}" class="form-in" type="{{ $field==='registered_on' ? 'date' : 'text' }}" data-quick-vehicle-field="{{ $field }}" @if($field==='registered_on') max="{{ today()->toDateString() }}" @else maxlength="{{ $field==='plate' ? 20 : ($field==='vin' ? 30 : 100) }}" @endif></div>@endforeach
            </div>
            <p class="invalid-feedback" data-quick-vehicle-error role="status"></p>
            <button class="btn btn-p btn-sm" type="button" data-save-vehicle>Salva e seleziona veicolo</button>
        </details>
    </x-form-section>
    <x-form-section title="Gruppo di compagnie per la richiesta">
        <div class="form-row" data-quote-routing>
            <x-form-group label="Gruppo di compagnie" name="quote_recipient_group_id"><select id="field-quote-recipient-group" class="form-sel" name="quote_recipient_group_id"><option value="">Nessun gruppo selezionato</option>@foreach($recipientGroups as $group)<option value="{{ $group->id }}" data-branch="{{ $group->insurance_branch_id }}" data-subbranch="{{ $group->insurance_subbranch_id }}" data-from="{{ $group->cu_from }}" data-to="{{ $group->cu_to }}" data-companies="{{ $group->companies->pluck('name')->join(', ') }}" @selected((string)old('quote_recipient_group_id')===(string)$group->id)>{{ $group->name }}@if($group->cu_from !== null) · {{ $group->class_label }}@endif</option>@endforeach</select><p class="u-text-meta" data-quote-group-description role="status">Scegli il ramo e l’eventuale classe CU per richiamare i gruppi configurati dall’admin.</p></x-form-group>
        </div>
        @can('system.admin')<a href="{{ route('quote-groups.index') }}" class="btn btn-g btn-sm" target="_blank" rel="noopener">Configura gruppi</a>@endcan
    </x-form-section>
</div>
