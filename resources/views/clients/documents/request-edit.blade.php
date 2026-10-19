<x-app-layout title="Modifica richiesta documentale">
    <x-page-header><x-slot:title>Modifica richiesta documentale</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab'=>'documents']) }}">{{ $client->name }}</a></x-slot:actions></x-page-header>
    <x-panel padded>
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
</x-app-layout>
