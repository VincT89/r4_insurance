<x-form-section title="Allegati del cliente">
    <x-form-group label="Carica allegati" name="documents"><input id="field-documents" type="file" class="form-in" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"><p class="u-text-meta">PDF e immagini: fino a 10 file, massimo 10 MB ciascuno. Per la lettura della scadenza del documento d’identità usa Carica documento nella scheda cliente.</p></x-form-group>
    @foreach($errors->get('documents.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
</x-form-section>
