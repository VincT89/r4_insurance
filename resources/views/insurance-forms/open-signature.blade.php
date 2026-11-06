<div class="signature-choice">
    <div><strong>{{ $document->title }}</strong>
        <span>{{ $document->package->policy_number ? 'Polizza '.$document->package->policy_number : 'Documenti del cliente' }}@if($period = $document->package->policy_period) · {{ $period }}@endif · Fascicolo #{{ $document->insurance_form_package_id }}</span>
    </div>
    <a class="btn {{ ($primaryAction ?? false) ? 'btn-p' : 'btn-g' }} btn-sm" href="{{ $document->signatureActionUrl() }}">{{ $document->signatureActionLabel() }}</a>
</div>
