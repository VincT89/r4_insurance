<x-app-layout title="Possibili duplicati">
    <x-page-header><x-slot:title>Possibili duplicati di {{ $client->name }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('clients.show', [$client, 'tab' => 'registry']) }}">Torna all’anagrafica</a></x-slot:actions></x-page-header>
    <x-panel padded>
        <p class="u-mb-md">Un nome o un recapito in comune può appartenere a persone diverse. Verifica l’identità prima di unificare. Sono mostrate solo le anagrafiche che puoi consultare.</p>
        @forelse($candidates as $candidate)
            <section class="client-detail-section"><h2 class="sec-lbl"><a href="{{ route('clients.show', [$candidate['client'], 'tab' => 'registry']) }}">{{ $candidate['client']->name }}</a></h2><p>{{ implode(', ', $candidate['reasons']) }}.</p>
                <p>{{ $candidate['client']->client_type_label }} / {{ $candidate['client']->tax_code ?: $candidate['client']->vat_number ?: 'Identificativo non indicato' }}</p>
                @can('system.admin')<a class="btn btn-g" href="{{ route('clients.merge.preview', [$client, 'source' => $candidate['client']->id]) }}">Confronta e unifica</a>@endcan
            </section>
        @empty <p>Nessuna corrispondenza trovata nelle anagrafiche consultabili.</p> @endforelse
    </x-panel>
</x-app-layout>
