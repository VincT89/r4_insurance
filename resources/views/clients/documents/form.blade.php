<x-app-layout :title="$document->exists ? 'Modifica dati documento' : 'Carica documento'">
    @php($linksLocked = $document->document_request_id || $document->previous_version_id || ($document->exists && $document->checklistRequests()->exists()))
    <x-page-header><x-slot:title>{{ $document->exists ? 'Modifica dati documento' : 'Carica documento' }}</x-slot:title><x-slot:actions><a href="{{ route('clients.show', [$client, 'tab' => 'documents']) }}" class="btn btn-g">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <form method="POST" enctype="multipart/form-data" data-document-expiry data-today="{{ today()->toDateString() }}" data-ocr-base="{{ asset('vendor/ocr').'/' }}" action="{{ $document->exists ? route('clients.documents.update', [$client, $document]) : route('clients.documents.store', $client) }}">
            @csrf
            @if(!$document->exists && $document->previous_version_id)<input type="hidden" name="previous_version_id" value="{{ $document->previous_version_id }}"><p class="u-mb-md">Nuova versione {{ $document->version_number }}. Il file precedente sarà conservato nello storico; il nuovo file dovrà essere validato.</p>@endif
            @if(!$document->exists && $document->document_request_id)<input type="hidden" name="document_request_id" value="{{ $document->document_request_id }}"><p class="u-text-meta u-mb-md">Questo caricamento risponde a un documento presente nella lista da ricevere.</p>@endif
            @error('previous_version_id')<p class="invalid-feedback">{{ $message }}</p>@enderror @error('document_request_id')<p class="invalid-feedback">{{ $message }}</p>@enderror
            @if($linksLocked)<p class="u-text-meta u-mb-md">Categoria e collegamenti sono conservati per mantenere lo storico della richiesta. Puoi aggiornare la scadenza e la descrizione.</p>
                @foreach(['document_category', 'insurance_policy_id', 'vehicle_id'] as $lockedField)<input type="hidden" name="{{ $lockedField }}" value="{{ $document->$lockedField }}">@endforeach
            @endif
            <x-form-section title="File e validità">
            @if($document->exists)
                @method('PATCH')
                <p class="u-mb-md">File: {{ $document->original_name }}</p>
            @else
                <x-form-group label="File" name="files" required><input id="field-files" type="file" name="files[]" multiple class="form-in" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt" required><p class="u-text-meta">@if($document->previous_version_id)Seleziona un file per sostituire questa versione, massimo 10 MB.@elseFino a 10 file, massimo 10 MB ciascuno. Fronte e retro possono essere file distinti: controlla la scadenza di entrambi.@endif</p></x-form-group>
                @foreach($errors->get('files.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
                @foreach($errors->get('file_details.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
            @endif
            <div class="form-row">
                <x-form-group label="Categoria documento" name="document_category" required><select id="field-document-category" name="document_category" class="form-sel" required @disabled($linksLocked)><option value="">Seleziona</option>@foreach(\App\Models\Attachment::DOCUMENT_CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(($linksLocked ? $document->document_category : old('document_category', $document->document_category)) === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
                @if($document->exists)<x-form-group label="Tipo di documento d’identità" name="identity_type"><select id="field-identity-type" name="identity_type" class="form-sel" data-identity-type><option value="">Non specificato</option>@foreach(\App\Models\Attachment::IDENTITY_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('identity_type', $document->identity_type) === $value)>{{ $label }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Scadenza del documento" name="expires_on"><input id="field-expires-on" type="date" name="expires_on" class="form-in" value="{{ old('expires_on', $document->expires_on?->toDateString()) }}"></x-form-group>@endif
            </div>
            @unless($document->exists)<p class="u-text-meta u-mt-sm">Per ogni file puoi leggere la scadenza da foto o PDF e confermarla, oppure inserirla manualmente. La lettura avviene localmente, sulle prime due pagine.</p><div data-file-details></div>@endunless
            @if($document->exists)<p data-expiry-status role="status" class="document-expiry-status" hidden></p>@endif
            </x-form-section><x-form-section title="Collegamenti e descrizione"><div class="form-row">
                <x-form-group label="Polizza collegata" name="insurance_policy_id"><select id="field-insurance-policy-id" name="insurance_policy_id" class="form-sel" @disabled($linksLocked)><option value="">Documento dell'anagrafica cliente</option>@foreach($policies as $policy)<option value="{{ $policy->id }}" @selected((string) ($linksLocked ? $document->insurance_policy_id : old('insurance_policy_id', $document->insurance_policy_id)) === (string) $policy->id)>{{ $policy->category }} / {{ $policy->company }} / {{ $policy->number }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Veicolo collegato" name="vehicle_id"><select id="field-vehicle-id" name="vehicle_id" class="form-sel" @disabled($linksLocked)><option value="">Nessun collegamento diretto</option>@foreach($vehicles as $vehicle)<option value="{{ $vehicle->id }}" @selected((string) ($linksLocked ? $document->vehicle_id : old('vehicle_id', $document->vehicle_id)) === (string) $vehicle->id)>{{ $vehicle->plate }}</option>@endforeach</select><p class="u-text-meta">Se colleghi una polizza, il suo veicolo è già associato. Usa questo campo per i documenti del veicolo.</p></x-form-group>
            </div>
            <x-form-group label="Descrizione" name="description"><textarea id="field-description" name="description" class="form-ta" rows="3" maxlength="500">{{ old('description', $document->description) }}</textarea></x-form-group>
            </x-form-section>
            <div class="insurance-form-actions"><a href="{{ route('clients.show', [$client, 'tab' => 'documents']) }}" class="btn btn-g">Annulla</a><button type="submit" class="btn btn-p">{{ $document->exists ? 'Salva dati' : 'Carica documento' }}</button></div>
        </form>
    </x-panel>
    @if($document->exists && !$document->newerVersion && $document->review_status !== 'validated')
        <x-panel title="Verifica del documento" padded><p class="u-text-meta">Apri e controlla il file prima di validarlo. La validazione registra operatore e data.</p>
            <form method="POST" action="{{ route('clients.documents.validate', [$client, $document]) }}">@csrf
                <x-form-group label="Nota di verifica" name="validation_note"><textarea class="form-ta" id="field-validation-note" name="validation_note" maxlength="500" rows="2">{{ old('validation_note') }}</textarea></x-form-group>
                <div class="insurance-form-actions"><a class="btn btn-g" href="{{ $document->downloadUrl() }}">Scarica il file</a><button class="btn btn-p" type="submit">Valida documento</button></div>
            </form>
        </x-panel>
    @endif
</x-app-layout>
