@php
    $latest = $document->latestSubmission;
    $tokens = $package->snapshot['tokens'] ?? [];
    $recorded = fn ($key) => ($tokens[$key] ?? '________________') !== '________________' ? $tokens[$key] : '';
    $signer = $latest?->signer_name ?: ($recorded('{{firmatario}}') ?: ($client->client_type === 'company' ? $client->reference_person : $client->name));
    $role = $latest?->signer_role ?: ($recorded('{{qualifica_firmatario}}') ?: ($client->client_type === 'company' ? $client->reference_role : ''));
    $showRole = $client->client_type === 'company' || filled($role);
    $reason = $latest?->status === 'rejected' && filled($latest->review_note) ? 'Nuova copia per la correzione richiesta. '.Str::limit($latest->review_note,1900) : '';
    $retry = (int)old('signature_document') === $document->id && old('signature_action') === 'upload' && old('signature_origin') === $origin;
    $field = fn ($key,$default='') => $retry && is_scalar($value=old($key,$default)) ? $value : $default;
    $prefix = 'signed-'.$document->id;
@endphp
<form method="POST" enctype="multipart/form-data" class="signature-form" action="{{ route('clients.forms.submit',[$client,$package,$document]) }}">
    @csrf
    <input type="hidden" name="signature_origin" value="{{ $origin }}"><input type="hidden" name="signature_action" value="upload"><input type="hidden" name="signature_document" value="{{ $document->id }}">
    <input type="hidden" name="request_key" value="{{ Str::uuid() }}"><input type="hidden" name="previous_submission" value="{{ $latest?->id ?? 0 }}">
    @if($retry && $errors->any())<div class="invalid-feedback" role="alert">{{ $errors->first() }} Seleziona nuovamente il file.</div>@endif
    <x-form-group label="Copia firmata" :name="$prefix.'-files'" required>
        <input id="field-{{ $prefix }}-files" class="form-in" type="file" name="files[]" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="{{ $prefix }}-limits" multiple required>
        <p id="{{ $prefix }}-limits" class="u-text-meta">PDF, JPG o PNG. Fino a 10 file, 10 MB ciascuno e 50 MB complessivi.</p>
    </x-form-group>
    <div class="form-row">
        <x-form-group label="Firmatario" :name="$prefix.'-name'" required><input id="field-{{ $prefix }}-name" class="form-in" name="signer_name" value="{{ $field('signer_name',$signer) }}" maxlength="150" required></x-form-group>
        @if($showRole)
            <x-form-group label="Qualifica" :name="$prefix.'-role'" :required="$client->client_type==='company'"><input id="field-{{ $prefix }}-role" class="form-in" name="signer_role" maxlength="150" value="{{ $field('signer_role',$role) }}" @required($client->client_type==='company')></x-form-group>
        @endif
        <x-form-group label="Data della firma" :name="$prefix.'-date'" required><input id="field-{{ $prefix }}-date" class="form-in" name="signed_on" type="date" max="{{ today()->toDateString() }}" value="{{ $field('signed_on',$latest?->signed_on?->toDateString() ?? today()->toDateString()) }}" required></x-form-group>
    </div>
    @if($latest)
        <x-form-group label="Motivo della nuova copia" :name="$prefix.'-note'" required><textarea id="field-{{ $prefix }}-note" class="form-ta" name="note" rows="2" minlength="10" maxlength="2000" required>{{ $field('note',$reason) }}</textarea></x-form-group>
    @endif
    @if(!$showRole || !$latest)
        <details class="signature-note" @if($retry && (old('signer_role') || old('note'))) open @endif>
            <summary>{{ $showRole ? 'Aggiungi una nota' : ($latest ? 'Qualifica del firmatario' : 'Qualifica e note') }}</summary>
            @if(!$showRole)
                <x-form-group label="Qualifica" :name="$prefix.'-role'"><input id="field-{{ $prefix }}-role" class="form-in" name="signer_role" maxlength="150" value="{{ $field('signer_role') }}"></x-form-group>
            @endif
            @if(!$latest)
                <x-form-group label="Nota" :name="$prefix.'-note'"><textarea id="field-{{ $prefix }}-note" class="form-ta" name="note" rows="2" maxlength="2000">{{ $field('note') }}</textarea></x-form-group>
            @endif
        </details>
    @endif
    <button type="submit" class="btn btn-p">Salva copia firmata</button>
</form>
