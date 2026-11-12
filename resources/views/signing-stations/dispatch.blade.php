<x-app-layout title="Invia al tablet">
    <x-page-header><x-slot:title>Invia al tablet</x-slot:title><x-slot:meta>{{ $client->name }}@if($policy) · Polizza {{ $policy->number }}@endif</x-slot:meta>
        <x-slot:actions><a class="btn btn-g" href="{{ $returnUrl }}">Torna alla pratica</a></x-slot:actions>
    </x-page-header>
    @php($available = $stations->where('pending_requests_count',0))
    @if($stations->isEmpty())
        <p>Collega prima una postazione di firma. Sul tablet apri {{ config('document_signing.station_public_url') }}.</p>
        <a class="btn btn-p" href="{{ route('signing-stations.index') }}">Collega un tablet</a>
        <a class="btn btn-g" href="{{ route('attachments.sign',$source) }}">Firma su questo dispositivo</a>
    @else
    <x-panel padded>
        <form method="post" action="{{ route('station-signing.store',$source) }}">
            @csrf
            <input type="hidden" name="attachment_selection_present" value="1">
            <div class="form-row">
                <x-form-group label="Postazione" name="signing_station_id" required>
                    <select class="form-sel" name="signing_station_id" required><option value="">Scegli il tablet</option>
                        @foreach($stations as $station)<option value="{{ $station->id }}" @disabled($station->pending_requests_count) @selected(old('signing_station_id',$available->count()===1 ? $available->first()->id : null)===$station->id)>{{ $station->name }}{{ $station->pending_requests_count ? ' · occupata' : '' }}</option>@endforeach
                    </select>
                </x-form-group>
                <div><p>Il cliente vedrà solo i documenti selezionati. La richiesta dura {{ config('document_signing.station_request_minutes') }} minuti.</p><a href="{{ route('signing-stations.index') }}">Gestisci postazioni</a></div>
            </div>
            <fieldset class="station-document-choices"><legend>Documenti da firmare</legend>
                @foreach($candidates as $candidate)
                    <label class="signing-confirm"><input type="checkbox" name="attachment_ids[]" value="{{ $candidate->id }}" @checked(in_array($candidate->id,(array)old('attachment_ids',old('attachment_selection_present') ? [] : $selected)))><span>{{ $candidate->original_name }}</span></label>
                @endforeach
                @error('attachment_ids')<p class="invalid-feedback">{{ $message }}</p>@enderror
                @foreach($errors->get('attachment_ids.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
            </fieldset>
            <div class="form-row">
                <x-form-group label="Nome e cognome del firmatario" name="signer_name" required><input class="form-in" name="signer_name" value="{{ old('signer_name',$signer) }}" maxlength="150" required></x-form-group>
                @if($client->client_type==='company' || filled($role))<x-form-group label="Qualifica del firmatario" name="signer_role" :required="$client->client_type==='company'"><input class="form-in" name="signer_role" value="{{ old('signer_role',$role) }}" maxlength="150" @required($client->client_type==='company')></x-form-group>@endif
            </div>
            @if($needsNote)<x-form-group label="Motivo della nuova copia" name="note" required><textarea class="form-ta" name="note" minlength="10" maxlength="2000" required>{{ old('note') }}</textarea></x-form-group>@endif
            <label class="signing-confirm"><input type="checkbox" name="identity_confirmed" value="1" required><span>Ho verificato l’identità della persona presente e i dati del firmatario indicati sopra.</span></label>
            @error('identity_confirmed')<p class="invalid-feedback">{{ $message }}</p>@enderror
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('attachments.sign',$source) }}">Firma su questo dispositivo</a><button class="btn btn-p" @disabled($available->isEmpty())>Invia alla firma</button></div>
        </form>
    </x-panel>
    @endif
</x-app-layout>
