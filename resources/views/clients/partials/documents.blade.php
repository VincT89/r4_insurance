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
    @include('clients.documents.requests-table')
</x-panel>
<x-panel title="Moduli e firme del cliente" padded>@include('insurance-forms.status', ['formPolicy'=>null])</x-panel>
