<x-app-layout title="Firma sul tablet">
    <x-page-header><x-slot:title>Firma sul tablet</x-slot:title><x-slot:meta>{{ $signingRequest->client->name }} · {{ $signingRequest->station->name }}</x-slot:meta>
        <x-slot:actions><a class="btn btn-g" href="{{ $returnUrl }}">Torna alla pratica</a></x-slot:actions>
    </x-page-header>
    <section data-station-progress data-status-url="{{ route('station-signing.status',$signingRequest) }}" data-status="{{ $signingRequest->pending() ? 'pending' : ($signingRequest->status==='pending' ? 'expired' : $signingRequest->status) }}" data-completed="{{ $signingRequest->items->whereNotNull('document_signature_id')->count() }}">
        <x-panel title="Stato della richiesta" padded>
            <p role="status" data-station-status>{{ $signingRequest->state_label }}</p>
            <p>{{ $signingRequest->items->whereNotNull('document_signature_id')->count() }} di {{ $signingRequest->items->count() }} documenti firmati.</p>
            @if($signingRequest->pending())<p>Il tablet mostra i documenti uno alla volta. Questa pagina si aggiorna automaticamente. Scadenza della richiesta: {{ $signingRequest->expires_at->format('H:i') }}.</p>@endif
            @if($signingRequest->failure_reason)<p>{{ $signingRequest->failure_reason }}</p>@endif
            <p class="invalid-feedback" data-station-progress-error hidden role="status"></p>
            @if($signingRequest->pending() && (auth()->user()->isAdmin() || auth()->id()===$signingRequest->created_by))
                <form method="post" action="{{ route('station-signing.cancel',$signingRequest) }}">@csrf<button class="btn btn-g">Annulla richiesta</button></form>
            @endif
        </x-panel>
        <x-panel title="Documenti inviati"><div class="client-table-scroll"><table class="t-table"><thead><tr><th>Documento</th><th>Stato</th><th>Copia raccolta</th></tr></thead><tbody>
            @foreach($signingRequest->items as $item)<tr><td>{{ $item->source->original_name }}</td><td>{{ $item->document_signature_id ? 'Firma raccolta' : ($signingRequest->pending() ? 'Da firmare' : 'Firma non raccolta') }}</td>
                <td>@if($item->signature)<a href="{{ $item->signature->signedFile->openUrl() }}" target="_blank" rel="noopener">Apri copia con firma</a>@else<span class="u-text-meta">{{ $signingRequest->pending() ? 'In attesa' : 'Nessuna copia raccolta' }}</span>@endif</td></tr>@endforeach
        </tbody></table></div></x-panel>
    </section>
    <p class="u-text-meta">Le copie dei moduli restano soggette al controllo previsto nel fascicolo. Le firme già raccolte si conservano anche se la richiesta viene annullata.</p>
</x-app-layout>
