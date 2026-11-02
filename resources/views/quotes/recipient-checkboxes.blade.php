@php
    $emailMode = $emailMode ?? false;
    $selectedRecipients = array_values(array_filter((array) old('recipient_keys', old('recipient_selection_present') ? [] : ($selectedKeys ?? [])), 'is_string'));
    $unavailableRecipients = collect($unavailableKeys ?? []);
    $selectableKeys = [];
@endphp
<div data-quote-recipients>
    <input type="hidden" name="recipient_selection_present" value="1">
    @if(isset($groups) && $groups->isNotEmpty())
        <div class="form-row">
            <x-form-group label="Richiama un gruppo" name="recipient-group-choice"><select id="field-recipient-group-choice" class="form-sel" data-recipient-group><option value="">Seleziona un gruppo, se ti serve</option>@foreach($groups as $recipientGroup)<option value="{{ $recipientGroup->id }}" data-recipients="{{ json_encode($recipientGroup->recipientKeys()) }}">{{ $recipientGroup->name }} — {{ $recipientGroup->class_label }}</option>@endforeach</select></x-form-group>
            <div class="insurance-filter-actions"><button type="button" class="btn btn-g" data-apply-recipient-group>Carica destinatari del gruppo</button></div>
        </div>
        <p class="u-text-meta u-mb-md">Il pulsante sostituisce la selezione attuale con i destinatari del gruppo. Puoi poi aggiungere o togliere chi vuoi.</p>
    @endif
    <x-form-group label="Cerca compagnia o agenzia" name="recipient-search"><input id="field-recipient-search" class="form-in" type="search" data-recipient-search autocomplete="off"></x-form-group>
    <p class="u-text-meta u-mt-sm u-mb-md">Spunta la compagnia per contattarla direttamente, oppure le singole agenzie. Ogni casella corrisponde a un destinatario distinto.</p>
    @error('recipient_keys')<p class="invalid-feedback" role="alert">{{ $message }}</p>@enderror
    @foreach($errors->get('recipient_keys.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
    <div class="quote-recipient-list">
    @forelse($companies as $company)
        <fieldset class="quote-recipient-company" data-recipient-company data-search="{{ $company->name.' '.$company->agencies->pluck('name')->join(' ') }}">
            <legend>{{ $company->name }}</legend>
            @php
                $targets = collect([['key'=>\App\Services\QuoteRecipients::key($company->id),'label'=>'Direttamente alla compagnia','email'=>$company->email,'active'=>$company->status==='active']]);
                foreach ($company->agencies as $agency) $targets->push(['key'=>\App\Services\QuoteRecipients::key($company->id,$agency->id),'label'=>'Tramite '.$agency->name,'email'=>$agency->email,'active'=>$company->status==='active' && $agency->status==='active']);
            @endphp
            @foreach($targets as $target)
                @php
                    $reason = !$target['active'] ? 'Anagrafica inattiva' : ($unavailableRecipients->contains($target['key']) ? 'Già richiesta o da verificare nello storico' : (!filter_var($target['email'],FILTER_VALIDATE_EMAIL) ? 'Email da completare nell’anagrafica' : null));
                    $disabled = $emailMode && $reason !== null;
                    if (!$disabled) $selectableKeys[] = $target['key'];
                @endphp
                <label class="quote-recipient-option"><input type="checkbox" name="recipient_keys[]" value="{{ $target['key'] }}" @disabled($disabled) @checked(!$disabled && in_array($target['key'],$selectedRecipients,true))><span><span>{{ $target['label'] }}</span><small>{{ $reason ?: $target['email'] }}</small></span></label>
            @endforeach
        </fieldset>
    @empty<p>Nessuna compagnia disponibile. Completa prima le anagrafiche delle compagnie e delle relative agenzie.</p>@endforelse
    </div>
    @if(array_diff($selectedRecipients,$selectableKeys))<p class="u-mt-md">Alcuni destinatari del gruppo o della selezione precedente non sono disponibili. Controlla le caselle selezionate prima di proseguire.</p>@endif
    <p class="u-text-meta u-mt-md" data-recipient-count role="status" aria-live="polite"></p>
    <p class="u-text-meta" data-recipient-group-result role="status" aria-live="polite"></p>
    <p hidden data-recipient-empty>Nessuna compagnia o agenzia corrisponde alla ricerca. Le selezioni già effettuate restano salvate.</p>
</div>
