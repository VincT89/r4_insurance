@php
    $client = $client ?? null;
    $typeValue = old('client_type', $client ? ($client->client_type ?? '') : 'person');
    $registryValue = function (string $field, $default = '') use ($client) {
        $value = old($field, $client?->$field ?? $default);
        if ($value instanceof \DateTimeInterface) return $value->format('Y-m-d');
        return is_scalar($value) ? $value : '';
    };
@endphp
<div x-data="{ clientType: @js($typeValue) }">
    <x-form-section title="Identità del contraente">
    <div class="form-row">
        <x-form-group label="Tipo cliente" name="client_type" required>
            <select id="field-client-type" name="client_type" class="form-sel" x-model="clientType" required>
                <option value="">Seleziona</option>
                <option value="person" @selected($typeValue === 'person')>Persona fisica</option>
                <option value="company" @selected($typeValue === 'company')>Azienda</option>
            </select>
        </x-form-group>
        <x-form-group label="Titolo" name="title">
            <input id="field-title" name="title" class="form-in" maxlength="50" value="{{ $registryValue('title') }}">
        </x-form-group>
    </div>
    <div class="form-row full">
        <x-form-group label="Contraente: nome e cognome o ragione sociale" name="name" required>
            <input id="field-name" name="name" class="form-in" value="{{ $registryValue('name') }}" maxlength="255" required>
        </x-form-group>
    </div>
    <div class="form-row">
        @foreach(['tax_code' => 'Codice fiscale', 'vat_number' => 'Partita IVA'] as $field => $label)
            <x-form-group :label="$label" :name="$field">
                <input id="field-{{ Str::slug($field) }}" name="{{ $field }}" class="form-in" value="{{ $registryValue($field) }}" maxlength="20">
            </x-form-group>
        @endforeach
    </div>
    <fieldset class="permission-fieldset" x-show="clientType !== 'company'" :disabled="clientType === 'company'">
        <div class="form-row">
            <x-form-group label="Data di nascita" name="birth_date">
                <input id="field-birth-date" type="date" name="birth_date" class="form-in" value="{{ $registryValue('birth_date') }}" max="{{ today()->toDateString() }}">
            </x-form-group>
            <x-form-group label="Luogo di nascita" name="birth_place">
                <input id="field-birth-place" name="birth_place" class="form-in" value="{{ $registryValue('birth_place') }}" maxlength="255">
            </x-form-group>
        </div>
    </fieldset>
    <div x-show="clientType === 'company'" class="form-row full">
        <x-form-group label="Denominazione aziendale, se diversa dal contraente" name="company_name">
            <input id="field-company-name" name="company_name" class="form-in" value="{{ $registryValue('company_name') }}" maxlength="255">
        </x-form-group>
    </div>
    </x-form-section>
    @foreach([
        'Contatti' => ['phone' => ['Telefono', 'tel', 50], 'mobile' => ['Cellulare', 'tel', 50], 'fax' => ['FAX', 'tel', 50], 'email' => ['E-Mail', 'email', 255]],
        'Indirizzo' => ['address' => ['Indirizzo', 'text', 255], 'city' => ['Città', 'text', 100], 'province' => ['Provincia', 'text', 5], 'postal_code' => ['CAP', 'text', 10], 'country' => ['Paese', 'text', 100], 'country_code' => ['Codice Stato', 'text', 2]],
        'Referente' => ['reference_person' => ['Referente', 'text', 255], 'reference_email' => ['E-Mail referente', 'email', 255], 'reference_role' => ['Ruolo referente', 'text', 255], 'reference_phone' => ['Telefono referente', 'tel', 50]],
        'Riferimenti in agenzia' => ['target' => ['Target', 'text', 255], 'office' => ['Ufficio', 'text', 255], 'consultant' => ['Consulente', 'text', 255], 'collaborator' => ['Collaboratore', 'text', 255], 'operator' => ['Operatore', 'text', 255]],
    ] as $section => $fields)
        <x-form-section :title="$section">
        <div class="form-row">
            @foreach($fields as $field => [$label, $type, $length])
                <x-form-group :label="$label" :name="$field">
                    <input id="field-{{ Str::slug($field) }}" type="{{ $type }}" name="{{ $field }}" class="form-in @error($field) is-invalid @enderror"
                           value="{{ $registryValue($field) }}" maxlength="{{ $length }}">
                </x-form-group>
            @endforeach
        </div>
        </x-form-section>
    @endforeach
    @include('clients.partials.commercial-field')
    <x-form-section title="Preferenze di contatto">
    <p class="u-text-meta u-mb-md">Preferenze organizzative del cliente. Le dichiarazioni di consenso si registrano separatamente nella sezione Consensi.</p>
    <div class="form-row">
        <x-form-group label="Canale preferito" name="preferred_contact_channel"><select id="field-preferred-contact-channel" name="preferred_contact_channel" class="form-sel"><option value="">Non indicato</option>@foreach(\App\Models\Client::CONTACT_CHANNELS as $value => $label)<option value="{{ $value }}" @selected($registryValue('preferred_contact_channel') === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Fascia oraria preferita" name="preferred_contact_time"><input id="field-preferred-contact-time" name="preferred_contact_time" class="form-in" maxlength="255" value="{{ $registryValue('preferred_contact_time') }}"></x-form-group>
        <x-form-group label="Indicazioni per il contatto" name="contact_preferences"><textarea id="field-contact-preferences" name="contact_preferences" class="form-ta" rows="3" maxlength="10000">{{ $registryValue('contact_preferences') }}</textarea></x-form-group>
    </div>
    </x-form-section>
    <x-form-section title="Note">
    <div class="form-row full">
        <x-form-group label="Notizie salienti" name="salient_notes">
            <textarea id="field-salient-notes" name="salient_notes" class="form-ta" rows="4" maxlength="10000">{{ $registryValue('salient_notes') }}</textarea>
        </x-form-group>
        <x-form-group label="Note commerciali" name="commercial_notes">
            <textarea id="field-commercial-notes" name="commercial_notes" class="form-ta" rows="3" maxlength="10000">{{ $registryValue('commercial_notes') }}</textarea>
        </x-form-group>
    </div>
    </x-form-section>
</div>
