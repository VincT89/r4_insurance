<x-app-layout title="Anagrafica clienti">
    <x-page-header
        
        
    >
    <x-slot:title><strong>Anagrafica clienti</strong></x-slot:title>
        <x-slot:actions>
            @can('create', App\Models\Client::class)
                <a href="{{ route('clients.create') }}" class="btn btn-p">Nuovo cliente</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('clients.index') }}" class="client-search-form">
        <x-form-group label="Cerca cliente" name="search">
            <input id="field-search" type="search" name="search" value="{{ request('search') }}"
                   placeholder="Contraente, contatti, CF / P. IVA, targa o polizza" class="form-in" maxlength="255">
        </x-form-group>
        @include('clients.partials.type-filter')
        <button type="submit" class="btn btn-p btn-sm">Cerca</button>
        @if(request('search') || request('client_type') || request('import_review'))
            <a href="{{ route('clients.index') }}" class="btn btn-g btn-sm">Azzera ricerca</a>
        @endif
    </form>

    <x-panel>
        <table class="t-table">
            <thead>
                <tr>
                    <th>Contraente</th>
                    <th>Email</th>
                    <th>Telefono</th>
                    <th>Stato</th>
                    <th class="t-actions">Azioni</th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $client)
                <tr x-data @click="window.Livewire.navigate('{{ route('clients.show', $client) }}')"
                    @keydown.enter.self.prevent="window.Livewire.navigate('{{ route('clients.show', $client) }}')"
                    @keydown.space.self.prevent="window.Livewire.navigate('{{ route('clients.show', $client) }}')"
                    tabindex="0" role="link" aria-label="Apri cliente {{ $client->name }}"
                    class="u-cursor-pointer hover-bg">
                    <td class="name-col">
                        <a href="{{ route('clients.show', $client) }}" @click.stop>{{ $client->name }}</a>
                        <div class="u-text-meta">{{ $client->client_type_label }} / {{ $client->policies_count }} {{ $client->policies_count === 1 ? 'polizza' : 'polizze' }}</div>
                        @if($client->company_name && $client->company_name !== $client->name)<div class="u-text-meta">{{ $client->company_name }}</div>@endif
                        @if($client->vat_number)<div class="u-text-meta">Partita IVA: {{ $client->vat_number }}</div>@endif
                        <div class="u-text-meta">Operatore: {{ $client->commercialUser?->name ?? 'Non assegnato' }}</div>
                    </td>
                    <td>{{ $client->email }}</td>
                    <td>{{ $client->mobile ?: $client->phone }}</td>
                    <td><x-badge :status="$client->status" :label="$client->status_label" /></td>
                    <td class="t-actions">
                        @can('updateRegistry', $client)
                            <a href="{{ route('clients.edit', $client) }}" class="btn btn-g btn-sm" aria-label="Modifica anagrafica {{ $client->name }}" @click.stop>Modifica</a>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="u-empty-state">Nessun cliente trovato</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $clients->links() }}
    </x-panel>
</x-app-layout>
