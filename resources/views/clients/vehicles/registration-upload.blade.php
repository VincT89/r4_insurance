<x-form-group label="Allega libretto" :name="$registrationField">
    <input id="field-{{ str_replace('_', '-', $registrationField) }}" class="form-in" type="file" name="{{ $registrationField }}[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp">
    <p class="u-text-meta">Facoltativo. Puoi selezionare insieme fronte e retro, in PDF o immagini. Fino a 10 file, massimo 10 MB ciascuno e 20 MB totali. Il caricamento conserva i documenti; i dati del veicolo si inseriscono nei campi della scheda.</p>
</x-form-group>
@foreach($errors->get($registrationField.'.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
