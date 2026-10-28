<div data-registration-reader data-ocr-base="{{ asset('vendor/ocr').'/' }}">
    <x-form-group label="Libretto del veicolo" :name="$registrationField"><input id="field-{{ str_replace('_','-',$registrationField) }}" class="form-in" type="file" name="{{ $registrationField }}[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" data-registration-files><p class="u-text-meta">PDF o fotografie, anche fronte e retro separati. Massimo 10 MB per file. I dati letti richiedono la tua conferma; la classe CU si ricava dall’attestato di rischio.</p></x-form-group>
    @foreach($errors->get($registrationField.'.*') as $messages)@foreach($messages as $message)<p class="invalid-feedback">{{ $message }}</p>@endforeach @endforeach
    <button type="button" class="btn btn-g btn-sm u-mt-sm" data-read-registration>Leggi dati del libretto</button>
    <p class="u-text-meta u-mt-sm" role="status" aria-live="polite" data-registration-status></p>
    <div class="registration-review" data-registration-review hidden></div>
</div>
