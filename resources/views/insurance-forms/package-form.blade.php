@php
    $reminder = $reminder ?? null;
    $restoring = old('template_selection_present') && (int)old('policy') === (int)$policy?->id && (int)old('change') === (int)$change?->id;
    $ids = $templates->map(fn ($template) => (string)$template->id)->values()->all();
    $choices = $restoring ? (array)old('templates', []) : ($savedSelection ?? $templates->filter(fn($template)=>$template->required && $template->applies($policy,$client))->pluck('id')->all());
    $selected = array_values(array_intersect($ids, array_map('strval', array_filter($choices,'is_scalar'))));
    $signers = $templates->filter(fn ($template) => $template->needsSigner())->map(fn ($template) => (string)$template->id)->values()->all();
    $roles = $templates->filter(fn ($template) => str_contains(\App\Support\FormTemplateContent::allText($template->body),'{{qualifica_firmatario}}'))->map(fn ($template) => (string)$template->id)->values()->all();
    $places = $templates->filter(fn ($template) => $template->needsPlace())->map(fn ($template) => (string)$template->id)->values()->all();
    $reason = $change?->kind ?? ($policy ? 'initial' : 'client');
@endphp
<form method="POST" action="{{ route('clients.forms.store',$client) }}" class="form-package-picker"
      x-data="{ chosen: @js($selected), allIds: @js($ids), signers: @js($signers), roles: @js($roles), places: @js($places), busy:false, moduleSearch:'',
          get hasSigner() { return this.chosen.some(id => this.signers.includes(id)); },
          get hasRole() { return this.hasSigner && (@js($client->client_type === 'company') || this.chosen.some(id => this.roles.includes(id))); },
          get hasPlace() { return this.chosen.some(id => this.places.includes(id)); } }"
      @submit="if (busy) $event.preventDefault(); else busy = true">
    @csrf
    <input type="hidden" name="policy" value="{{ $policy?->id }}">
    <input type="hidden" name="change" value="{{ $change?->id }}">
    <input type="hidden" name="context_hash" value="{{ $contextHash }}">
    <input type="hidden" name="request_key" value="{{ $reminder ?: Str::uuid() }}">
    <input type="hidden" name="template_selection_present" value="1">
    @if($reminder)
        <input type="hidden" name="reminder" value="{{ $reminder }}">
        <input type="hidden" name="reason" value="{{ $reason }}">
        <input type="hidden" name="confirmed" value="1">
    @endif
    <p class="module-help">{{ $policy ? 'I dati del cliente e della polizza vengono inseriti automaticamente nei moduli selezionati.' : 'I dati del cliente vengono inseriti automaticamente. Per includere i dati di un contratto, prepara i moduli dalla relativa polizza.' }}</p>
    <fieldset class="module-choices">
        <legend>Moduli da generare</legend>
        @if(count($ids)>1)
            <div class="module-selection-tools">
                <button type="button" class="btn btn-g btn-sm" @click="chosen = [...allIds]" :disabled="chosen.length === allIds.length">Seleziona tutti</button>
                <button type="button" class="btn btn-g btn-sm" @click="chosen = []" :disabled="!chosen.length">Deseleziona tutti</button>
            </div>
        @endif
        <label class="module-search">Cerca un modulo<input type="search" class="form-in" x-model="moduleSearch" placeholder="Nome del modulo" aria-label="Cerca un modulo"></label>
        @forelse($templates as $template)
            <div class="module-choice" x-show="!moduleSearch || @js(mb_strtolower($template->title)).includes(moduleSearch.toLocaleLowerCase('it'))">
                <label><input type="checkbox" name="templates[]" value="{{ $template->id }}" x-model="chosen" @checked(in_array((string)$template->id,$selected,true))>
                    <span><strong>{{ $template->title }}</strong><small>{{ $template->signature_required ? 'Firma da raccogliere' : 'Senza firma' }} / {{ $template->applies($policy,$client) ? 'Consigliato per questo fascicolo' : 'Altro modulo disponibile' }}@if($template->branch) / {{ $template->branch->name }}@endif@if($template->client_type && $template->client_type!=='all') / {{ \App\Models\InsuranceFormTemplate::CLIENT_TYPES[$template->client_type] }}@endif</small></span>
                </label>
                <a class="module-preview" href="{{ route('form-templates.preview',[$template,'viewer'=>1]) }}" target="_blank" rel="noopener" aria-label="Anteprima del modello {{ $template->title }}">Anteprima modello</a>
            </div>
        @empty
            <p>Non ci sono modelli utilizzabili. Crea o approva un modello per renderlo disponibile.</p>
            @can('forms.manage')<a href="{{ route('form-templates.index') }}">Configura modelli</a>@endcan
        @endforelse
        @error('templates')<p role="alert" class="invalid-feedback">{{ $message }}</p>@enderror
        @error('forms')<p role="alert" class="invalid-feedback">{{ $message }}</p>@enderror
    </fieldset>
    @if($templates->isNotEmpty())
        <div class="module-signer" x-show="hasSigner || hasPlace" x-cloak>
            <h3>Dati per i moduli selezionati</h3>
            <div class="form-row">
                <div x-show="hasSigner"><x-form-group label="Nome del firmatario" name="signer_name" required>
                    <input class="form-in" name="signer_name" maxlength="150" :required="hasSigner" :disabled="!hasSigner" value="{{ $safeOld('signer_name',$client->client_type === 'person' ? $client->name : $client->reference_person) }}">
                </x-form-group></div>
                <div x-show="hasRole"><x-form-group label="Qualifica del firmatario" name="signer_role" :required="$client->client_type === 'company'">
                    <input class="form-in" name="signer_role" maxlength="150" :disabled="!hasRole" :required="hasRole && @js($client->client_type === 'company')" value="{{ $safeOld('signer_role',$client->client_type === 'company' ? $client->reference_role : '') }}">
                </x-form-group></div>
                <div x-show="hasPlace"><x-form-group label="Luogo" name="place" required>
                    <input class="form-in" name="place" maxlength="150" :required="hasPlace" :disabled="!hasPlace" value="{{ $safeOld('place') }}">
                </x-form-group></div>
            </div>
        </div>
        <details class="module-settings" @if(!$reminder || $errors->hasAny(['document_date','reason','note'])) open @endif @invalid.capture="$el.open = true">
            <summary>Data e note di preparazione</summary>
            <div class="form-row">
                @unless($reminder)
                    <x-form-group label="Motivo" name="reason" required>
                        <select class="form-sel" name="reason" required>@foreach(\App\Models\InsuranceFormPackage::REASONS as $key=>$label)
                            @if(($policy && $key!=='client' && ($change ? in_array($key,[$change->kind,'renewal'],true) : !in_array($key,['replacement','addition'],true))) || (!$policy && $key==='client'))
                                <option value="{{ $key }}" @selected($safeOld('reason',$reason)===$key)>{{ $label }}</option>
                            @endif
                        @endforeach</select>
                    </x-form-group>
                @endunless
                <x-form-group label="Data di preparazione" name="document_date" required><input class="form-in" name="document_date" type="date" value="{{ $safeOld('document_date',today()->toDateString()) }}" required></x-form-group>
            </div>
            <x-form-group label="Note sulla preparazione" name="note"><textarea class="form-ta" name="note" rows="2" maxlength="2000">{{ $safeOld('note',$change?->description ?? '') }}</textarea></x-form-group>
        </details>
        @unless($reminder)
            <label class="email-review-confirm"><input type="checkbox" name="confirmed" value="1" required> Ho controllato i dati e i moduli selezionati.</label>
        @endunless
    @endif
    <div class="module-actions">
        <span role="status" x-text="chosen.length === 1 ? '1 modulo selezionato' : chosen.length + ' moduli selezionati'">{{ count($selected) }} moduli selezionati</span>
        <div>
            @if($reminder)
                <button class="btn btn-g" type="submit" formaction="{{ route('clients.forms.reminder-decision',[$client,'later']) }}" formnovalidate :disabled="busy">Più tardi</button>
            @else<a class="btn btn-g" href="{{ route('clients.forms.index',[$client,'policy'=>$policy?->id]) }}">Annulla</a>@endif
            <button class="btn btn-p" type="submit" @if($reminder) x-show="chosen.length" @endif :disabled="busy || !chosen.length">Genera moduli PDF</button>
            @if($reminder)
                <button class="btn btn-p" type="submit" formaction="{{ route('clients.forms.reminder-decision',[$client,'none']) }}" formnovalidate x-show="!chosen.length" x-cloak :disabled="busy || chosen.length > 0">Nessun modulo da generare</button>
            @endif
        </div>
    </div>
</form>
