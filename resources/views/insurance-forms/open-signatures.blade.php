@if($openSignatures->isNotEmpty())
    <section class="open-signatures" aria-label="Moduli da firmare o controllare">
        <x-panel title="Moduli da firmare o controllare" padded>
            @if(!empty($historyUrl))
                <x-slot:headerActions><a class="document-history-link" href="{{ $historyUrl }}">Storico moduli</a></x-slot:headerActions>
            @endif
            @foreach($openSignatures->take(3) as $document)
                @include('insurance-forms.open-signature',['primaryAction'=>$openSignatures->count()===1])
            @endforeach
            @if($openSignatures->count()>3)
                <details><summary>{{ $openSignatures->count()===4 ? 'Mostra un altro modulo' : 'Mostra altri '.($openSignatures->count()-3).' moduli' }}</summary>
                    @each('insurance-forms.open-signature',$openSignatures->slice(3),'document')
                </details>
            @endif
        </x-panel>
    </section>
@endif
