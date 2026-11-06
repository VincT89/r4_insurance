<x-app-layout title="Anteprima del modello">
    <x-page-header>
        <x-slot:title>{{ $title }}</x-slot:title>
        <x-slot:meta>Anteprima del modello · I campi tra parentesi quadre vengono compilati alla generazione del fascicolo.</x-slot:meta>
        <x-slot:actions><a class="btn btn-g" href="{{ $source }}" download="anteprima-modello.pdf">Scarica PDF</a></x-slot:actions>
    </x-page-header>
    <x-pdf-reader :source="$source" />
</x-app-layout>
