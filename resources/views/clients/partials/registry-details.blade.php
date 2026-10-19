@foreach([
    'Identità del contraente' => ['title' => 'Titolo', 'name' => 'Contraente', 'company_name' => 'Denominazione aziendale', 'tax_code' => 'Codice fiscale', 'vat_number' => 'Partita IVA'],
    'Contatti' => ['phone' => 'Telefono', 'mobile' => 'Cellulare', 'fax' => 'FAX', 'email' => 'E-Mail'],
    'Indirizzo' => ['address' => 'Indirizzo', 'city' => 'Città', 'province' => 'Provincia', 'postal_code' => 'CAP', 'country' => 'Paese', 'country_code' => 'Codice Stato'],
    'Referente' => ['reference_person' => 'Referente', 'reference_email' => 'E-Mail referente', 'reference_role' => 'Ruolo referente', 'reference_phone' => 'Telefono referente'],
    'Riferimenti in agenzia' => ['target' => 'Target', 'office' => 'Ufficio', 'consultant' => 'Consulente', 'collaborator' => 'Collaboratore', 'operator' => 'Operatore'],
] as $section => $fields)
    <section class="client-detail-section">
        <h2 class="sec-lbl">{{ $section }}</h2>
        <dl class="client-registry-details">
            @foreach($fields as $field => $label)
                <div><dt>{{ $label }}</dt><dd>{{ $client->$field ?: 'Non indicato' }}</dd></div>
            @endforeach
            @if($loop->first)
                <div><dt>Tipo cliente</dt><dd>{{ $client->client_type_label }}</dd></div>
                @if($client->client_type !== 'company')
                    <div><dt>Data di nascita</dt><dd>{{ $client->birth_date?->format('d/m/Y') ?? 'Non indicata' }}</dd></div>
                    <div><dt>Luogo di nascita</dt><dd>{{ $client->birth_place ?: 'Non indicato' }}</dd></div>
                @endif
            @endif
        </dl>
    </section>
@endforeach
<section class="client-detail-section"><h2 class="sec-lbl">Preferenze di contatto</h2><dl class="client-registry-details"><div><dt>Canale preferito</dt><dd>{{ \App\Models\Client::CONTACT_CHANNELS[$client->preferred_contact_channel] ?? 'Non indicato' }}</dd></div><div><dt>Fascia oraria preferita</dt><dd>{{ $client->preferred_contact_time ?: 'Non indicata' }}</dd></div><div><dt>Indicazioni</dt><dd class="u-whitespace-pre-wrap">{{ $client->contact_preferences ?: 'Non indicate' }}</dd></div></dl></section>
<section class="client-detail-section">
<h2 class="sec-lbl">Gestione del cliente</h2>
<dl class="client-registry-details">
    <div><dt>Operatore di riferimento</dt><dd>{{ $client->commercialUser?->name ?? 'Non assegnato' }}</dd></div>
    <div><dt>Stato cliente</dt><dd>{{ $client->status_label }}</dd></div>
</dl>
</section>
@if($client->salient_notes)<section class="client-detail-section"><h2 class="sec-lbl">Notizie salienti</h2><p class="u-whitespace-pre-wrap">{{ $client->salient_notes }}</p></section>@endif
@include('clients.partials.import-record')
