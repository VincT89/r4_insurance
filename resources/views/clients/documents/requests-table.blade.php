<div class="client-table-scroll" role="region" aria-label="Richieste documentali" tabindex="0">
    <table class="t-table"><thead><tr><th>Documento</th><th>Collegamento</th><th>Da ricevere entro</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
        @forelse($documentRequests as $item)
            @php
                $requestFiles = $item->currentDocuments();
                $overdueRequest = $requestFiles->isEmpty() && $item->due_on?->lt(today());
            @endphp
            <tr id="document-request-{{ $item->id }}">
                <td>{{ $item->label }}@if($item->label !== \App\Models\Attachment::DOCUMENT_CATEGORIES[$item->document_category])<div class="u-text-meta">{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$item->document_category] }}</div>@endif
                    @if($requestFiles->isNotEmpty())
                        <details class="document-request-files"><summary>File collegati ({{ $requestFiles->count() }})</summary>
                            @foreach($requestFiles as $requestFile)
                                <div><a href="{{ $requestFile->downloadUrl() }}">{{ $requestFile->original_name }}</a> · versione {{ $requestFile->version_number }}</div>
                            @endforeach
                        </details>
                    @endif
                </td>
                <td>{{ $item->insurancePolicy ? 'Polizza '.$item->insurancePolicy->number : 'Anagrafica cliente' }}</td>
                <td><x-status-text :alert="$overdueRequest">{{ $item->due_on?->format('d/m/Y') ?? 'Non indicato' }}</x-status-text></td>
                <td><x-status-text :alert="$overdueRequest || in_array($item->state_label, ['Documento scaduto', 'Documento sostituito: aggiorna collegamento'])">{{ $item->state_label }}</x-status-text></td>
                <td>@can('updateRegistry', $client)<a class="btn btn-g btn-sm" href="{{ route('clients.document-requests.edit', [$client, $item]) }}">Gestisci documenti</a>@endcan</td>
            </tr>
        @empty<tr><td colspan="5">Nessun documento richiesto. Puoi aggiungere una voce o applicare una checklist configurata.</td></tr>@endforelse
    </tbody></table>
</div>
