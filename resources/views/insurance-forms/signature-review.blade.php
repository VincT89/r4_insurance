@php($retryReview = (int)old('signature_document') === $document->id && old('signature_action') === 'review' && old('signature_origin') === $origin)
<form method="POST" class="signature-form" action="{{ route('clients.forms.review',[$client,$package,$latest]) }}">
    @csrf
    <input type="hidden" name="signature_origin" value="{{ $origin }}"><input type="hidden" name="signature_action" value="review"><input type="hidden" name="signature_document" value="{{ $document->id }}">
    @if($retryReview && $errors->any())<p class="invalid-feedback" role="alert">{{ $errors->first() }}</p>@endif
    <label class="insurance-checkbox"><input type="checkbox" name="confirmed" value="1" required> Ho aperto le copie e controllato firmatario, data e firme richieste.</label>
    <x-form-group label="Nota del controllo" :name="'review-note-'.$document->id">
        <textarea id="field-review-note-{{ $document->id }}" class="form-ta" name="review_note" maxlength="2000" rows="2" placeholder="Se chiedi una correzione, indica cosa manca.">{{ $retryReview && is_string(old('review_note')) ? old('review_note') : '' }}</textarea>
    </x-form-group>
    <div class="signature-buttons">
        <button type="submit" class="btn btn-p" name="outcome" value="validated">Conferma controllo</button>
        <button type="submit" class="btn btn-g" name="outcome" value="rejected">Richiedi correzione</button>
    </div>
</form>
