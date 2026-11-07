<x-app-layout title="Polizze">
    <div class="policy-register-page" data-policy-register data-list-user="{{ auth()->id() }}" data-policy-list-url="{{ route('policies.index') }}">
        <header class="policy-page-heading">
            <div><h1>Polizze</h1><p>Cerca una polizza e scegli l’operazione.</p></div>
            @can('create', \App\Models\Client::class)<a href="{{ route('policies.create') }}" class="btn btn-p">Nuova polizza</a>@endcan
        </header>
        @include('policies.register-filters')
        <section class="policy-register panel" aria-label="Elenco polizze">
            <div class="policy-register-caption">
                <p data-table-page-summary>{{ $policies->total() }} {{ $policies->total() === 1 ? 'polizza' : 'polizze' }}@if($policies->hasPages()) · Pagina {{ $policies->currentPage() }} di {{ $policies->lastPage() }}@endif</p>
                <div data-policy-export-actions></div>
            </div>
            <div class="client-table-scroll" role="region" aria-label="Portafoglio polizze" tabindex="0">
                <table class="t-table policy-register-table" data-list-details data-details-label="Altri dati" data-filter-form="policies-filters">
                    <thead><tr><th>Polizza</th><th>Cliente</th><th>Compagnia</th><th>Scadenza</th><th>Da incassare</th><th>Azioni</th><th data-list-detail>Ramo</th><th data-list-detail>Sottoramo</th><th data-list-detail>Agenzia</th><th data-list-detail>Stato</th><th data-list-detail>Pagamento</th><th data-list-detail>Metodo di pagamento</th><th data-list-detail>Premio di polizza</th><th data-list-detail>Da rimborsare</th></tr></thead>
                    <tbody>
                    @forelse($policies as $policy)
                        <tr data-list-label="{{ $policy->number }}" id="policy-row-{{ $policy->id }}">
                            <td><a class="policy-register-number" data-policy-list-link href="{{ route('clients.policies.show', [$policy->client_id,$policy]) }}">{{ $policy->number }}</a>@if($policy->vehicle)<div class="policy-register-meta">{{ $policy->vehicle->plate }}</div>@elseif($policy->product)<div class="policy-register-meta">{{ $policy->product }}</div>@endif</td>
                            <td><a href="{{ route('clients.show',$policy->client) }}">{{ $policy->client->name }}</a></td>
                            <td>{{ $policy->company }}</td>
                            <td><x-status-text :alert="$policy->is_expired">{{ $policy->expires_on->format('d/m/Y') }}</x-status-text><span @class(['policy-register-state','is-alert'=>$policy->is_expired,'is-review'=>$policy->status==='review']) data-table-export-ignore>{{ $policy->status_label }}</span></td>
                            <td class="policy-register-money">{{ $policy->titles_exists ? \App\Support\Money::format((int)$policy->receipt_due_cents) : 'Nessun titolo' }}@if($policy->refund_due_cents>0)<small data-table-export-ignore>Da rimborsare {{ \App\Support\Money::format((int)$policy->refund_due_cents) }}</small>@endif</td>
                            <td><div class="policy-register-actions">
                                @can('ledger.manage')@can('updateRegistry',$policy->client)
                                    @if($policy->receipt_due_cents>0)<a class="btn btn-p btn-sm" data-policy-list-link aria-label="Incassa per la polizza {{ $policy->number }}" href="{{ route('clients.policies.show',[$policy->client_id,$policy,'tab'=>'payments']).'#payment-entry' }}">Incassa</a>
                                    @elseif($policy->refund_due_cents>0)<a class="btn btn-p btn-sm" data-policy-list-link href="{{ route('clients.policies.show',[$policy->client_id,$policy,'tab'=>'payments']).'#payment-entry' }}">Registra rimborso</a>@endif
                                @endcan @endcan
                                <a class="policy-register-open" data-policy-list-link aria-label="Apri polizza {{ $policy->number }}" href="{{ route('clients.policies.show',[$policy->client_id,$policy]) }}">Apri</a>
                                <x-policy-contract-link :policy="$policy" />
                            </div></td>
                            <td>{{ $policy->category }}</td><td>{{ $policy->subcategory ?: 'Non specificato' }}</td><td>{{ $policy->supplierAgency?->name ?: 'Non assegnata' }}</td>
                            <td>{{ $policy->status_label }}</td><td>{{ $policy->payment_status_label }}</td><td>{{ $policy->payment_method ?: 'Non indicato' }}</td>
                            <td>{{ $policy->premium !== null ? number_format((float)$policy->premium,2,',','.').' €' : 'Non indicato' }}</td><td>{{ \App\Support\Money::format((int)$policy->refund_due_cents) }}</td>
                        </tr>
                    @empty<tr><td colspan="14" class="policy-register-empty">Nessuna polizza corrisponde ai filtri. Puoi cambiare la ricerca o <a href="{{ route('policies.index') }}">mostrare tutte le polizze</a>.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            {{ $policies->links() }}
        </section>
    </div>
</x-app-layout>
