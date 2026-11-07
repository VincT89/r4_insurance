<x-app-layout title="Anagrafica clienti">
    <x-page-header>
        <x-slot:title>Anagrafica clienti</x-slot:title>
        <x-slot:actions><a href="{{ route('vehicles.index') }}" class="btn btn-g">Parco veicoli</a>@can('create', App\Models\Client::class)<a href="{{ route('clients.create') }}" class="btn btn-p">Nuovo cliente</a>@endcan</x-slot:actions>
    </x-page-header>
    <form method="GET" action="{{ route('clients.index') }}" class="client-search-form">
        <x-form-group label="Cerca cliente" name="search"><input id="field-search" type="search" name="search" value="{{ request('search') }}" placeholder="Nome, contatti, codice fiscale, partita IVA, targa o polizza" class="form-in" maxlength="255"></x-form-group>
        @include('clients.partials.type-filter')
        <button type="submit" class="btn btn-p">Cerca</button>
        @if(request('search') || request('client_type') || request('import_review'))<a href="{{ route('clients.index') }}" class="btn btn-g">Azzera ricerca</a>@endif
    </form>
    <x-panel class="client-list">
        <div class="client-table-scroll" role="region" aria-label="Elenco clienti" tabindex="0">
        <table class="t-table" data-list-details data-details-label="Altri dati">
            <thead><tr><th>Contraente</th><th>Contatti</th><th>Polizze</th><th>Azioni</th><th data-list-detail>Stato</th><th data-list-detail>Codice fiscale</th><th data-list-detail>Partita IVA</th><th data-list-detail>Operatore</th></tr></thead>
            <tbody>
            @forelse($clients as $client)
                <tr data-list-label="{{ $client->name }}">
                    <td class="name-col"><a href="{{ route('clients.show',$client) }}">{{ $client->name }}</a><div class="u-text-meta">{{ $client->client_type_label }} · {{ $client->status_label }}</div>@if($client->company_name && $client->company_name !== $client->name)<div class="u-text-meta">{{ $client->company_name }}</div>@endif</td>
                    <td><span class="client-contact-line">{{ $client->email ?: 'Email non indicata' }}</span>@if($client->mobile || $client->phone)<span class="client-contact-line u-text-meta">{{ $client->mobile ?: $client->phone }}</span>@endif</td>
                    <td>{{ $client->policies_count }}</td>
                    <td class="t-actions"><a href="{{ route('clients.show',$client) }}" aria-label="Apri cliente {{ $client->name }}">Apri</a>@can('updateRegistry',$client)<a href="{{ route('clients.edit',$client) }}" aria-label="Modifica anagrafica {{ $client->name }}">Modifica</a>@endcan</td>
                    <td>{{ $client->status_label }}</td><td>{{ $client->tax_code ?: 'Non indicato' }}</td><td>{{ $client->vat_number ?: 'Non indicata' }}</td><td>{{ $client->commercialUser?->name ?? 'Non assegnato' }}</td>
                </tr>
            @empty<tr><td colspan="8" class="u-empty-state">Nessun cliente trovato. Modifica la ricerca per riprovare.</td></tr>@endforelse
            </tbody>
        </table>
        </div>
        {{ $clients->links() }}
    </x-panel>
</x-app-layout>
