<section class="policy-client-picker" data-policy-client-picker @if($deferredClient ?? false) data-deferred-client @endif data-search-url="{{ route('api.clients.search') }}" data-create-url="{{ route('api.clients.quick-store') }}" data-context-url="{{ url('/clients') }}">
    <h2 class="workspace-section-title">Contraente</h2>
    <input type="hidden" name="client_id" value="{{ old('client_id', $client->id) }}">
    <div class="form-row">
        <x-form-group label="Cerca cliente" name="client_id" required><input id="field-client-search" type="search" class="form-in" autocomplete="off" placeholder="Nome, codice fiscale, partita IVA o targa" value="{{ $client->name }}" data-client-search><div data-client-results class="client-picker-results" aria-label="Risultati ricerca cliente"></div></x-form-group>
        <div class="insurance-form-actions">@can('quickCreate', \App\Models\Client::class)<button type="button" class="btn btn-g" data-new-client aria-expanded="false">Nuovo cliente</button>@endcan</div>
    </div>
    <p data-client-selected class="u-text-meta" role="status">{{ $client->exists ? 'Cliente selezionato: '.$client->name : 'Seleziona un cliente oppure creane uno.' }}</p>
    <button type="button" class="btn btn-g btn-sm u-mt-sm" data-keep-client hidden>Mantieni il cliente selezionato</button>
    @can('quickCreate', \App\Models\Client::class)
    <div data-quick-client data-fiscal-check data-fiscal-picker data-check-url="{{ route('api.clients.check-fiscal-identifiers') }}" hidden>
        <div class="form-row">
            <x-form-group label="Tipo cliente" name="quick_client_type"><select class="form-sel" data-quick-field="client_type"><option value="person">Persona</option><option value="company">Azienda</option></select></x-form-group>
            <x-form-group label="Nome e cognome / Ragione sociale" name="quick_name"><input class="form-in" data-quick-field="name" maxlength="255"></x-form-group>
            <x-form-group label="Codice fiscale" name="quick_tax_code"><input id="field-quick-tax-code" class="form-in" data-quick-field="tax_code" data-fiscal-field="tax_code" maxlength="20" autocomplete="off" spellcheck="false"></x-form-group>
            <x-form-group label="Partita IVA" name="quick_vat_number"><input id="field-quick-vat-number" class="form-in" data-quick-field="vat_number" data-fiscal-field="vat_number" maxlength="20" autocomplete="off" spellcheck="false"></x-form-group>
            <x-form-group label="Email" name="quick_email"><input type="email" class="form-in" data-quick-field="email" maxlength="255"></x-form-group>
            <x-form-group label="Telefono" name="quick_phone"><input type="tel" class="form-in" data-quick-field="phone" maxlength="50"></x-form-group>
            @if($deferredClient ?? false)
                @foreach(['address'=>['Indirizzo',255], 'city'=>['Città',100], 'postal_code'=>['CAP',10], 'province'=>['Provincia',5]] as $key=>$details)
                    <div class="form-g"><label class="form-lbl" for="quick-{{ $key }}">{{ $details[0] }}</label><input id="quick-{{ $key }}" class="form-in" data-quick-field="{{ $key }}" maxlength="{{ $details[1] }}"></div>
                @endforeach
            @endif
        </div>
        @include('clients.partials.fiscal-feedback')
        <p data-quick-error role="alert" class="invalid-feedback"></p>
        @if($deferredClient ?? false)
            <p class="u-text-meta">Il nuovo cliente sarà creato insieme alla polizza, dopo la conferma finale.</p>
            <script type="application/json" data-contract-old-client>{!! json_encode(old('new_client', []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @else <button type="button" class="btn btn-p" data-save-client>Crea e seleziona cliente</button> @endif
    </div>
    @endcan
</section>
