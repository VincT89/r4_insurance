<div class="client-documents-workspace">
    @include('insurance-forms.open-signatures',['historyUrl'=>route('clients.forms.index',$client)])
    <x-panel title="Documenti">
        @can('updateRegistry', $client)
            <x-slot:headerActions>
                <a href="{{ route('clients.documents.create',$client) }}" class="btn {{ $openSignatures->isEmpty() ? 'btn-p' : 'btn-g' }} btn-sm">{{ $openSignatures->isEmpty() ? 'Carica documento' : 'Carica altro documento' }}</a>
            </x-slot:headerActions>
        @endcan
        <nav class="client-section-nav document-views" aria-label="Viste dell’archivio documenti">
            <a href="{{ route('clients.show', [$client,'tab'=>'documents']) }}" @if(request('document_view','current')==='current') aria-current="page" @endif>Documenti correnti</a>
            <a href="{{ route('clients.show', [$client,'tab'=>'documents','document_view'=>'history']) }}" @if(request('document_view')==='history') aria-current="page" @endif>Tutte le versioni</a>
            @can('viewMaterials', $client)<a href="{{ route('clients.materials.index',$client) }}">Altri materiali</a>@endcan
        </nav>
        @if($documents->isEmpty())<p class="document-list-empty">Nessun documento caricato.</p>
        @else @include('clients.documents.table') @endif
    </x-panel>
    <x-panel title="Documenti da ricevere">
        @can('updateRegistry', $client)
            <x-slot:headerActions><a class="btn btn-g btn-sm" href="{{ route('clients.document-requests.create',$client) }}">Richiedi documento</a></x-slot:headerActions>
        @endcan
        @if($documentRequests->isEmpty())<p class="document-list-empty">Nessun documento richiesto.</p>
        @else @include('clients.documents.requests-table') @endif
    </x-panel>
    <x-panel title="Moduli e firme del cliente" padded>
        @if($openSignatures->isEmpty())
            <x-slot:headerActions><a class="document-history-link" href="{{ route('clients.forms.index',$client) }}">Storico moduli</a></x-slot:headerActions>
        @endif
        @include('insurance-forms.status',['formPolicy'=>null])
    </x-panel>
</div>
