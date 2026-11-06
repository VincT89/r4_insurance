@php
    $emailMode = $emailMode ?? false;
    $restoring = session()->hasOldInput('recipient_selection_present');
    $selectedRecipients = array_filter((array) old('recipient_keys', $restoring ? [] : ($selectedKeys ?? [])), 'is_string');
    $unavailableRecipients = collect($unavailableKeys ?? []);
    $savedGroup = $emailMode ? ($draft ? ($draft->payload['recipient_group'] ?? null) : $case->recipient_group_snapshot) : null;
    $groupId = $restoring ? old('recipient_group_choice') : ($emailMode ? ($draft ? ($savedGroup['id'] ?? null) : $case->quote_recipient_group_id) : null);
    $initialGroup = $savedGroup && !$restoring ? ($draft ? $savedGroup['recipient_keys'] : $case->suggestedRecipientKeys()) : null;
    $showAgencies = $companies->sum(fn ($company) => $company->agencies->count()) <= 8;
    $availableSelections = [];
@endphp
<div data-quote-recipients data-max-recipients="{{ \App\Services\QuoteRecipients::MAX_RECIPIENTS }}"
     @if($initialGroup !== null) data-initial-recipients="{{ json_encode($initialGroup) }}" @endif>
    <input type="hidden" name="recipient_selection_present" value="1">
    @if($emailMode)
        @if($groups->isNotEmpty())
            <div class="recipient-group-choice">
                <x-form-group label="Gruppo di destinatari" name="recipient_group_choice">
                    <select id="field-recipient-group-choice" name="recipient_group_choice" class="form-sel" data-recipient-group>
                        <option value="">Scelta manuale</option>
                        @foreach($groups as $recipientGroup)
                            <option value="{{ $recipientGroup->id }}" data-recipients="{{ json_encode($recipientGroup->recipientKeys()) }}" @selected(is_scalar($groupId) && (string)$groupId === (string)$recipientGroup->id)>{{ $recipientGroup->name }} · {{ $recipientGroup->class_label }}</option>
                        @endforeach
                    </select>
                </x-form-group>
                <p class="u-text-meta">Il gruppo spunta i suoi destinatari. Cambiando gruppo mantieni le modifiche fatte a mano.</p>
            </div>
        @elseif($case->purpose === 'addition')
            <p class="u-text-meta u-mb-md">Per l’aggiunta di coperture scegli tra la compagnia della polizza e le sue agenzie.</p>
        @else
            <p class="u-text-meta u-mb-md">Nessun gruppo compatibile con questa richiesta. Puoi scegliere i destinatari qui sotto.</p>
        @endif
        @if($initialGroup !== null && !$groups->contains('id', $groupId))
            <p class="u-text-meta u-mb-md">Spunte riprese dal gruppo iniziale «{{ $savedGroup['name'] }}».</p>
        @endif
        @if($groups->isEmpty())@error('recipient_group_choice')<p class="invalid-feedback">{{ $message }}</p>@enderror @endif
    @endif
    <div class="recipient-search">
        <x-form-group label="Cerca compagnia o agenzia" name="recipient-search">
            <input id="field-recipient-search" class="form-in" type="search" data-recipient-search autocomplete="off">
        </x-form-group>
        <button type="button" class="btn btn-g" data-recipient-selected-only aria-pressed="false">Solo selezionati</button>
    </div>
    <p class="u-text-meta recipient-selection-help">Spunta la compagnia per l’invio diretto o un’agenzia sotto il suo nome. Puoi scegliere entrambe.</p>
    @error('recipient_keys')<p class="invalid-feedback" role="alert">{{ $message }}</p>@enderror
    @foreach($errors->get('recipient_keys.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
    <p class="invalid-feedback" role="alert" tabindex="-1" data-recipient-error hidden></p>
    <p class="u-text-meta" role="status" data-recipient-group-result></p>
    <div class="quote-recipient-list">
        @forelse($companies as $company)
            @php
                $directKey = \App\Services\QuoteRecipients::key($company->id);
                $agencyKeys = $company->agencies->mapWithKeys(fn ($agency) => [$agency->id => \App\Services\QuoteRecipients::key($company->id, $agency->id)]);
                $expand = $showAgencies || array_intersect($selectedRecipients, $agencyKeys->all());
                $targets = collect([[$company, $directKey]])->concat($company->agencies->map(fn ($agency) => [$agency, $agencyKeys[$agency->id]]));
                $reasons = [];
                foreach ($targets as [$target, $key]) {
                    $reasons[$key] = $company->status !== 'active' || $target->status !== 'active' ? 'Anagrafica inattiva'
                        : ($unavailableRecipients->contains($key) ? 'Già richiesto o da verificare nello storico'
                        : (!filter_var($target->email, FILTER_VALIDATE_EMAIL) ? 'Email da completare nell’anagrafica' : null));
                    if (!$emailMode || !$reasons[$key]) $availableSelections[] = $key;
                }
            @endphp
            <fieldset class="quote-recipient-company" data-recipient-company="{{ $company->name }}">
                <legend>{{ $company->name }}</legend>
                @include('quotes.recipient-option', ['key'=>$directKey, 'target'=>$company, 'direct'=>true])
                @if($company->agencies->isNotEmpty())
                    <details class="recipient-agencies" @if($expand) open @endif>
                        <summary>Agenzie collegate ({{ $company->agencies->count() }})</summary>
                        @foreach($company->agencies as $agency)
                            @include('quotes.recipient-option', ['key'=>$agencyKeys[$agency->id], 'target'=>$agency, 'direct'=>false])
                        @endforeach
                    </details>
                @endif
            </fieldset>
        @empty
            <p>Nessuna compagnia disponibile per questa richiesta.</p>
        @endforelse
    </div>
    @if(array_diff($selectedRecipients, $availableSelections))
        <p class="u-text-meta">Alcuni destinatari della selezione precedente non sono disponibili. Controlla le spunte.</p>
    @endif
    <p hidden data-recipient-empty>Nessun destinatario corrisponde al filtro. Le spunte sono conservate.</p>
    @unless($emailMode)<p data-recipient-count role="status" class="u-text-meta u-mt-md"></p>@endunless
</div>
