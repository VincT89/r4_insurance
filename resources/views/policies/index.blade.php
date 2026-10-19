<x-app-layout title="Polizze">
    <x-page-header><x-slot:title>Polizze</x-slot:title><x-slot:actions>@can('create', \App\Models\Client::class)<a href="{{ route('policies.create') }}" class="btn btn-p">Nuova polizza</a>@endcan</x-slot:actions></x-page-header>
    @include('policies.filters', ['action'=>route('policies.index')])
    <x-panel>
        <div class="client-table-scroll" role="region" aria-label="Portafoglio polizze" tabindex="0"><table class="t-table"><thead><tr><th>Polizza</th><th>Cliente</th><th>Ramo</th><th>Sottoramo</th><th>Compagnia</th><th>Agenzia</th><th>Scadenza</th><th>Stato</th><th>Pagamento</th><th>Metodo di pagamento</th><th>Premio di polizza</th></tr></thead><tbody>
            @forelse($policies as $policy)
                <tr><td><a class="u-nowrap" href="{{ route('clients.policies.show', [$policy->client_id,$policy]) }}">{{ $policy->number }}</a>@if($policy->product)<div class="u-text-meta">{{ $policy->product }}</div>@endif</td><td><a href="{{ route('clients.show', $policy->client) }}">{{ $policy->client->name }}</a></td><td>{{ $policy->category }}</td><td>{{ $policy->subcategory ?: 'Non specificato' }}</td><td>{{ $policy->company }}</td><td>{{ $policy->supplierAgency?->name ?: 'Non assegnata' }}</td><td><x-status-text :alert="$policy->is_expired">{{ $policy->expires_on->format('d/m/Y') }}</x-status-text></td><td><x-policy-status :policy="$policy" /></td><td><x-badge :status="$policy->payment_status ?? 'unspecified'" :label="$policy->payment_status_label" /></td><td>{{ $policy->payment_method ?: 'Non indicato' }}</td><td>{{ $policy->premium !== null ? number_format((float)$policy->premium,2,',','.').' €' : 'Non indicato' }}</td></tr>
            @empty<tr><td colspan="11" class="u-empty-state">Nessuna polizza corrisponde ai filtri.</td></tr>@endforelse
        </tbody></table></div>
        {{ $policies->links() }}
    </x-panel>
</x-app-layout>
