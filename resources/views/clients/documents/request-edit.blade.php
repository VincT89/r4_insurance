<x-app-layout title="Modifica richiesta documentale">
    <x-page-header><x-slot:title>Modifica richiesta documentale</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab'=>'documents']) }}">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel title="Documenti della richiesta" padded>
        <p class="u-mb-md">{{ $documentRequest->label }} · <x-status-text :alert="in_array($documentRequest->state_label, ['Documento scaduto', 'Documento sostituito: aggiorna collegamento'])">{{ $documentRequest->state_label }}</x-status-text></p>
        @foreach($documentRequest->currentDocuments() as $linkedFile)
            <div class="document-checklist-file">
                <div><a href="{{ $linkedFile->downloadUrl() }}">{{ $linkedFile->original_name }}</a><p class="u-text-meta">Versione {{ $linkedFile->version_number }} · {{ $linkedFile->review_status === 'validated' ? 'Validato' : 'Da validare' }}</p>
                    @if($linkedFile->document_category === 'identity' || $linkedFile->expires_on)<p><x-status-text :alert="$linkedFile->expires_on?->lt(today())">{{ $linkedFile->validity_label }}@if($linkedFile->expires_on && !$linkedFile->expires_on->lt(today())) · scadenza {{ $linkedFile->expires_on->format('d/m/Y') }}@endif</x-status-text></p>@endif
                    @if($linkedFile->newerVersion)<p class="u-text-danger">File sostituito: seleziona esplicitamente la versione aggiornata tra i documenti disponibili.</p>@endif
                </div>
                <a class="btn btn-g btn-sm" href="{{ route('clients.documents.edit', [$client, $linkedFile]) }}">Apri e verifica</a>
            </div>
        @endforeach
        <div class="insurance-form-actions"><a class="btn btn-p" href="{{ route('clients.documents.create', [$client, 'request'=>$documentRequest->id]) }}">Carica file multipli</a>
            @if($documentRequest->insurancePolicy)<a class="btn btn-g" href="{{ route('clients.policies.show', [$client, $documentRequest->insurancePolicy]) }}#policy-documentation">Torna alla polizza</a>@endif
        </div>
    </x-panel>
    @if($availableDocuments->isNotEmpty())
        <x-panel title="Usa documenti già presenti" padded>
            <p class="u-text-meta u-mb-md">Seleziona i file pertinenti. Il collegamento conserva la loro validazione e permette di seguirne scadenza e versioni.</p>
            <form method="POST" action="{{ route('clients.document-requests.link', [$client, $documentRequest]) }}">@csrf
                <div class="document-checklist-options">@foreach($availableDocuments as $availableFile)
                    <label><input type="checkbox" name="documents[]" value="{{ $availableFile->id }}"><span>{{ $availableFile->original_name }}<span class="u-text-meta">{{ $availableFile->insurance_policy_id ? 'Polizza '.$documentRequest->insurancePolicy?->number : 'Anagrafica cliente' }} · {{ $availableFile->review_status === 'validated' ? 'Validato' : 'Da validare' }}</span>@if($availableFile->expires_on || $availableFile->document_category === 'identity')<x-status-text :alert="$availableFile->expires_on?->lt(today())">{{ $availableFile->validity_label }}</x-status-text>@endif</span><a href="{{ $availableFile->downloadUrl() }}">Apri file</a></label>
                @endforeach</div>
                @error('documents')<p class="invalid-feedback">{{ $message }}</p>@enderror
                <label class="insurance-checkbox u-mt-md"><input type="checkbox" name="confirmed" value="1" required> Ho controllato che i file selezionati rispondano a questa richiesta.</label>
                <div class="insurance-form-actions"><button class="btn btn-p">Collega documenti selezionati</button></div>
            </form>
        </x-panel>
    @endif
    <x-panel title="Richiesta e termine" padded>
        <p class="u-text-meta u-mb-md">{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$documentRequest->document_category] }} · {{ $documentRequest->insurancePolicy ? 'Polizza '.$documentRequest->insurancePolicy->number : 'Anagrafica cliente' }} · {{ $documentRequest->state_label }}</p>
        <form method="POST" action="{{ route('clients.document-requests.update', [$client, $documentRequest]) }}">@csrf @method('PATCH')
            <div class="form-row">
                <x-form-group label="Documento richiesto" name="label" required><input class="form-in" id="field-label" name="label" maxlength="255" value="{{ old('label', $documentRequest->label) }}" required></x-form-group>
                <x-form-group label="Da ricevere entro" name="due_on"><input class="form-in" id="field-due-on" name="due_on" type="date" value="{{ old('due_on', $documentRequest->due_on?->toDateString()) }}"></x-form-group>
            </div>
            <x-form-group label="Note" name="notes"><textarea class="form-ta" id="field-notes" name="notes" maxlength="2000" rows="3">{{ old('notes', $documentRequest->notes) }}</textarea></x-form-group>
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab'=>'documents']) }}">Annulla</a><button class="btn btn-p" type="submit">Salva richiesta</button></div>
        </form>
    </x-panel>
    @can('system.admin')@if($documentRequest->documents->isEmpty() && $documentRequest->linkedDocuments->isEmpty())
        <x-delete-modal action="{{ route('clients.document-requests.destroy', [$client, $documentRequest]) }}" title="Rimuovi richiesta" message="Rimuovere questa richiesta senza file ricevuti?"><button class="btn btn-g btn-danger-outline">Rimuovi richiesta</button></x-delete-modal>
    @endif@endcan
</x-app-layout>
