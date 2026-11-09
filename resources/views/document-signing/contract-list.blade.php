<ul class="tablet-contract-files">
@foreach($policy->contracts as $contract)
    <li>
        <div class="contract-name"><a href="{{ $contract->openUrl() }}" target="_blank" rel="noopener">{{ $contract->original_name }}</a>
            @if($signature=$contract->latestTabletSignature)
                <p class="u-text-meta">Firma raccolta il {{ $signature->signed_at->format('d/m/Y H:i') }} · {{ $signature->signer_name }}</p>
            @endif
        </div>
        @if($signature?->signedFile)<a class="btn btn-g" href="{{ $signature->signedFile->openUrl() }}" target="_blank" rel="noopener">Apri copia con firma</a>@endif
        @can('updateRegistry',$client)
            @if($contract->mime_type === 'application/pdf')<a class="btn {{ $signature ? 'btn-g' : 'btn-p' }}" href="{{ route('station-signing.create',$contract) }}">{{ $signature ? 'Firma nuova copia' : 'Firma sul tablet' }}</a>@endif
        @endcan
        @if($signature)
            <details class="contract-signature-history"><summary>Storico delle firme</summary>
                @foreach($contract->tabletSignatures()->with(['signedFile','author'])->latest('id')->get() as $version)
                    <p>{{ $version->signed_at->format('d/m/Y H:i') }} · {{ $version->signer_name }} · {{ $version->author?->name }} · <a href="{{ $version->signedFile->openUrl() }}" target="_blank" rel="noopener">Apri copia</a></p>
                @endforeach
            </details>
        @endif
    </li>
@endforeach
</ul>
