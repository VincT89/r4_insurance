@php
    $label = \App\Support\ClientRegistryFields::SECTIONS[$section];
    $tab = \App\Support\ClientRegistryFields::TABS[$section];
@endphp
<x-app-layout :title="$label">
    <x-page-header><x-slot:title>{{ $record->exists ? 'Modifica' : 'Aggiungi' }}: {{ $label }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab' => $tab]) }}">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
        @if($section === 'consents')
            <p class="u-mb-md">Registra una dichiarazione già documentata. Il modulo di invio elettronico riguarda i documenti contrattuali e non autorizza messaggi promozionali. Per una revoca o una rettifica aggiungi una nuova registrazione: lo storico rimane consultabile.</p>
            <p class="u-text-meta u-mb-md">Indica il modulo o l’informativa effettivamente utilizzati e collega la prova. Puoi caricarla prima nella sezione Documenti. La preferenza di contatto del cliente non costituisce un consenso.</p>
        @endif
        <form method="POST" action="{{ $record->exists ? route('clients.records.update', [$client, $section, $record->id]) : route('clients.records.store', [$client, $section]) }}"
            x-data="{ kind: @js(old('kind', $record->kind ?? '')), scope: @js(old('scope', $record->scope ?? '')), assetType: @js(old('asset_type', $record->asset_type ?? '')) }">
            @csrf
            @if($record->exists) @method('PATCH') @endif
            <p class="u-text-meta u-mb-md">I campi con * sono obbligatori.</p>
            <x-form-section :title="$label">
            <div class="form-row">
                @foreach($fields as $field => [$fieldLabel, $type, $required, $limit])
                    @php
                        $value = old($field, $record->$field);
                        if ($value instanceof \DateTimeInterface) $value = $value->format('Y-m-d\TH:i');
                        if (!is_scalar($value)) $value = '';
                    @endphp
                    <div @if($section === 'consents' && $field === 'purpose') x-show="kind === 'other'" @endif>
                        <x-form-group :label="$fieldLabel" :name="$field" :required="$required || ($field === 'signer_role' && $client->client_type === 'company')">
                            @if($type === 'select')
                                <select id="field-{{ Str::slug($field) }}" name="{{ $field }}" class="form-sel" @required($required)
                                    @if(in_array($field, ['kind', 'scope'])) x-model="{{ $field }}" @elseif($field === 'asset_type') x-model="assetType" @endif>
                                    <option value="">Seleziona</option>
                                    @foreach($limit as $option => $text)<option value="{{ $option }}" @selected((string)$value === (string)$option)>{{ $text }}</option>@endforeach
                                </select>
                            @elseif($type === 'textarea')
                                <textarea id="field-{{ Str::slug($field) }}" name="{{ $field }}" class="form-ta" rows="3" maxlength="{{ $limit }}" @required($required)>{{ $value }}</textarea>
                            @else
                                <input id="field-{{ Str::slug($field) }}" type="{{ $type }}" name="{{ $field }}" class="form-in" value="{{ $value }}" @if($limit) maxlength="{{ $limit }}" @endif @required($required || ($field === 'signer_role' && $client->client_type === 'company'))
                                    @if($field === 'purpose') :disabled="kind !== 'other'" :required="kind === 'other'" @endif
                                    @if($field === 'address') :required="assetType === 'property'" @endif>
                            @endif
                        </x-form-group>
                    </div>
                @endforeach
                @if($section === 'consents')
                    <div x-show="scope === 'policy'">
                        <x-form-group label="Polizza interessata" name="insurance_policy_id">
                            <select id="field-insurance-policy-id" name="insurance_policy_id" class="form-sel" :disabled="scope !== 'policy'" :required="scope === 'policy'">
                                <option value="">Seleziona la polizza</option>
                                @foreach($policies as $policy)<option value="{{ $policy->id }}" @selected((string)old('insurance_policy_id') === (string)$policy->id)>{{ $policy->company }} / {{ $policy->category }} / {{ $policy->number }}</option>@endforeach
                            </select>
                        </x-form-group>
                    </div>
                    <x-form-group label="Documento di prova" name="attachment_id">
                        <select id="field-attachment-id" name="attachment_id" class="form-sel"><option value="">Indico il riferimento alla prova conservata</option>@foreach($documents as $document)<option value="{{ $document->id }}" @selected((string)old('attachment_id') === (string)$document->id)>{{ $document->original_name }}</option>@endforeach</select>
                    </x-form-group>
                @endif
            </div>
            </x-form-section>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab' => $tab]) }}">Annulla</a><button type="submit" class="btn btn-p">Salva registrazione</button></div>
        </form>
    </x-panel>
</x-app-layout>
