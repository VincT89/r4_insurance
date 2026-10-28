<p class="u-text-meta u-mb-md">Tutti gli allegati sono facoltativi. Puoi selezionare più file per ogni gruppo: fino a 10 documenti e 20 MB complessivi, massimo 10 MB per file. Identità e libretto anche in JPG, PNG o WEBP.</p>
@foreach(['identity_files','vehicle_files','files','identity_file_details','document_ids'] as $errorKey)
    @foreach($errors->get($errorKey.'.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
@endforeach
<div data-document-expiry data-document-category="identity" data-details-name="identity_file_details" data-expiry-key="quote-identity" data-today="{{ today()->toDateString() }}" data-ocr-base="{{ asset('vendor/ocr').'/' }}">
    <x-form-group label="Documento d’identità del richiedente" name="identity_files"><input id="field-identity-files" class="form-in" type="file" name="identity_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"><p class="u-text-meta">Carta d’identità elettronica, cartacea o patente, anche fronte e retro separati. Dopo la selezione puoi leggere e confermare la scadenza di ogni documento.</p></x-form-group>
    <div data-file-details></div>
</div>
<div class="form-row u-mt-md">
    @if($readRegistration ?? false)@include('clients.vehicles.registration-reader',['registrationField'=>'vehicle_files'])@else<x-form-group label="Libretto del veicolo" name="vehicle_files"><input id="field-vehicle-files" class="form-in" type="file" name="vehicle_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"></x-form-group>@endif
    <x-form-group label="Altri documenti per la richiesta" name="files"><input id="field-files" class="form-in" type="file" name="files[]" multiple accept="application/pdf,.pdf"><p class="u-text-meta">Documenti aziendali, dell’immobile, polizza attuale o altre informazioni utili alla compagnia.</p></x-form-group>
</div>
