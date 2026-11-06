@php
    $package = $document->package;
    $latest = $document->latestSubmission;
    $status = $latest?->status;
    $state = ['received'=>'Da controllare','validated'=>'Firma controllata','rejected'=>'Da correggere'][$status] ?? 'Da firmare';
    $editing = (int)old('signature_document') === $document->id && old('signature_origin') === 'policy';
@endphp
<article class="signature-row" id="policy-signature-{{ $document->id }}" data-signature-destination aria-labelledby="signature-title-{{ $document->id }}">
    <div class="signature-description">
        <h4 id="signature-title-{{ $document->id }}">{{ $document->title }}</h4>
        <p><strong>{{ $state }}</strong> · <a href="{{ route('clients.forms.show',[$client,$package]).'#form-document-'.$document->id }}">Fascicolo #{{ $package->id }}</a> · {{ $package->created_at->format('d/m/Y') }}</p>
        @if($previous)<p>{{ $package->policy_period ?? 'Periodo precedente' }}</p>@endif
        @if($status === 'rejected' && $latest->review_note)<p class="signature-reason">{{ $latest->review_note }}</p>@endif
        @if($latest)
            <p>{{ $latest->signer_name }}{{ $latest->signer_role ? ' · '.$latest->signer_role : '' }} · Firma del {{ $latest->signed_on->format('d/m/Y') }} · Copia {{ $latest->version }}</p>
        @endif
        <div class="signature-links">
            @if($document->generated)<a href="{{ $document->generated->openUrl() }}" target="_blank" rel="noopener">Apri modulo originale</a>@endif
            @foreach($latest?->files ?? [] as $file)
                <a href="{{ $file->openUrl() }}" @if($file->canPreview()) target="_blank" rel="noopener" @endif>{{ $latest->files->count() === 1 ? 'Apri copia ricevuta' : $file->original_name }}</a>
            @endforeach
        </div>
    </div>
    @can('updateRegistry',$client)
        @if(!$status || $status === 'rejected')
            @if($document->generated)<a class="btn btn-p" href="{{ route('attachments.sign',$document->generated) }}">Firma sul tablet</a>@endif
            <details class="signature-task" data-signature-next @if($editing && old('signature_action')==='upload') open @endif>
                <summary class="btn btn-g">{{ $status === 'rejected' ? 'Carica copia corretta' : 'Carica copia firmata' }}</summary>
                @include('insurance-forms.signature-upload',['origin'=>'policy'])
            </details>
        @elseif($status === 'received')
            <details class="signature-task" data-signature-next @if($editing && old('signature_action')==='review') open @endif>
                <summary class="btn btn-g">{{ $document->signatureActionLabel() }}</summary>
                @include('insurance-forms.signature-review',['origin'=>'policy'])
            </details>
        @endif
    @endcan
</article>
