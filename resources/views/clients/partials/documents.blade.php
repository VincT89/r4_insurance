<x-panel title="Documenti">
    <x-slot:headerActions>
        <div class="commercial-actions">
            @can('updateRegistry', $client)<a href="{{ route('clients.document-requests.create', $client) }}" class="btn btn-g btn-sm">Documenti da ricevere</a>@endcan
            @can('updateRegistry', $client)<a href="{{ route('clients.documents.create', $client) }}" class="btn btn-p btn-sm">Carica documento</a>@endcan
            @can('viewMaterials', $client)<a href="{{ route('clients.materials.index', $client) }}" class="btn btn-g btn-sm">Altri materiali</a>@endcan
        </div>
    </x-slot:headerActions>
    <nav class="client-section-nav" aria-label="Versioni dei documenti"><a href="{{ route('clients.show', [$client, 'tab'=>'documents']) }}" @if(request('document_view', 'current')==='current') aria-current="page" @endif>Documenti correnti</a><a href="{{ route('clients.show', [$client, 'tab'=>'documents', 'document_view'=>'history']) }}" @if(request('document_view')==='history') aria-current="page" @endif>Tutte le versioni</a></nav>
    @include('clients.documents.table')
</x-panel>
<x-panel title="Lista dei documenti da ricevere">
    <div class="client-table-scroll" role="region" aria-label="Richieste documentali" tabindex="0"><table class="t-table"><thead><tr><th>Documento</th><th>Collegamento</th><th>Da ricevere entro</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
        @forelse($documentRequests as $item)
            <tr id="document-request-{{ $item->id }}">
                <td>{{ $item->label }}<div class="u-text-meta">{{ \App\Models\Attachment::DOCUMENT_CATEGORIES[$item->document_category] }}@if($item->notes)<br>{{ $item->notes }}@endif</div></td>
                <td>{{ $item->insurancePolicy ? 'Polizza '.$item->insurancePolicy->number : 'Anagrafica cliente' }}@if($item->branch)<div class="u-text-meta">{{ $item->branch->name }}</div>@endif</td>
                <td><x-status-text :alert="$item->documents->isEmpty() && $item->due_on?->lt(today())">{{ $item->due_on?->format('d/m/Y') ?? 'Non indicato' }}</x-status-text></td><td><x-status-text :alert="$item->state_label === 'Documento scaduto' || ($item->documents->isEmpty() && $item->due_on?->lt(today()))">{{ $item->state_label }}</x-status-text></td>
                <td>@can('updateRegistry', $client)
                    <a class="btn btn-g btn-sm" href="{{ route('clients.document-requests.edit', [$client, $item]) }}">Modifica richiesta</a>
                    @if($item->documents->isEmpty())
                        <a class="btn btn-p btn-sm" href="{{ route('clients.documents.create', [$client, 'request'=>$item->id]) }}">Carica file</a>
                        @can('system.admin')<x-delete-modal action="{{ route('clients.document-requests.destroy', [$client, $item]) }}" title="Rimuovi richiesta" message="Rimuovere questa richiesta senza file ricevuti?"><button class="btn btn-g btn-sm" type="button">Rimuovi richiesta</button></x-delete-modal>@endcan
                    @else
                        @php($current = $item->documents->sortByDesc('version_number')->first())
                        <a class="btn btn-g btn-sm" href="{{ route('clients.documents.edit', [$client, $current]) }}">Apri documento</a>
                    @endif
                @endcan</td>
            </tr>
        @empty<tr><td colspan="5">Nessun documento richiesto. Puoi aggiungere una voce o applicare una checklist configurata.</td></tr>@endforelse
    </tbody></table></div>
</x-panel>
