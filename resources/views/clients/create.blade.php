<x-app-layout title="Nuovo cliente">
    <x-page-header>
        <x-slot:title>Nuovo cliente</x-slot:title>
        <x-slot:actions><a href="{{ route('clients.index') }}" class="btn btn-g">Anagrafica clienti</a></x-slot:actions>
    </x-page-header>

    <x-panel padded>
        <form action="{{ route('clients.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <p class="u-text-sm u-text-muted u-mb-md">I campi con * sono obbligatori.</p>
            @include('clients.partials.registry-fields', ['client' => null])
                @include('clients.partials.management-fields', ['client' => null])
            @include('clients.partials.upload-files')
            <div class="modal-ft u-section-sep">
                <a href="{{ route('clients.index') }}" class="btn btn-g">Annulla</a>
                <button type="submit" class="btn btn-p">Salva cliente</button>
            </div>
        </form>
    </x-panel>
</x-app-layout>
