<x-app-layout title="Modifica anagrafica">
    <x-page-header>
        <x-slot:title>Modifica anagrafica</x-slot:title>
        <x-slot:actions><a href="{{ route('clients.show', $client) }}" class="btn btn-g">Scheda cliente</a></x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('clients.update', $client) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            @include('clients.partials.registry-fields')
                @include('clients.partials.management-fields')
            @include('clients.partials.upload-files')
            <div class="modal-ft u-section-sep">
                <a href="{{ route('clients.show', $client) }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Aggiorna cliente</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
