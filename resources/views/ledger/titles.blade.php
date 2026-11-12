<x-app-layout title="Titoli"><x-page-header><x-slot:title>Titoli{{ $contextPolicy ? ' / '.$contextPolicy->number : ($contextClient ? ' / '.$contextClient->name : '') }}</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('collections.index',$contextParameters+['month'=>'']) }}">Contabilità</a>@if($contextPolicy)<a class="btn btn-g" href="{{ route('clients.policies.show',[$contextClient,$contextPolicy,'tab'=>'payments']) }}">Scheda polizza</a>@elseif($contextClient)<a class="btn btn-g" href="{{ route('clients.show',$contextClient) }}">Scheda cliente</a>@else<a class="btn btn-g" href="{{ route('policies.index') }}">Polizze</a>@endif @if($contextParameters)<a class="btn btn-g" href="{{ route('titles.index') }}">Tutti i titoli</a>@endif</x-slot:actions></x-page-header>
@include('ledger.filters',['isCollections'=>false])
@if(($filters['direction'] ?? '') !== 'refund')<details class="list-summary"><summary>Totali dei titoli filtrati</summary><x-payment-summary :summary="$paymentSummary" :filters="$filters" :filtered="true" /></details>@endif
<x-panel>
    <p class="list-results" data-table-page-summary>{{ $titles->total() }} {{ $titles->total() === 1 ? 'titolo' : 'titoli' }} · Pagina {{ $titles->currentPage() }} di {{ $titles->lastPage() }}</p>
    <div class="client-table-scroll" role="region" aria-label="Titoli" tabindex="0">
        <table class="t-table" data-title-table data-list-details data-filter-form="ledger-filters">
            <thead><tr><th>Titolo</th><th>Polizza</th><th>Contraente</th><th data-list-detail>Agenzia</th><th data-list-detail>Compagnia</th><th>Scadenza</th><th data-list-detail>Scadenza +15 giorni</th><th data-list-detail>Periodo</th><th data-list-detail>Frazionamento</th><th data-list-detail>Importo</th><th data-list-detail>Abbuoni</th><th>Residuo</th><th data-list-detail>Metodo previsto</th><th>Stato</th></tr></thead>
            <tbody>
            @forelse($titles as $title)
                <tr data-title-summary-group="{{ $title->summary_state }}" data-title-balance-cents="{{ $title->balance_cents }}">
                    <td>
                        <a href="{{ route('titles.show',$title) }}">{{ \App\Models\InsuranceTitle::KINDS[$title->kind] }} {{ $title->reference ?: '#'.$title->id }}</a>
                        @can('ledger.manage')
                            @can('updateRegistry',$title->policy->client)
                                @if(!$title->cancelled_at && $title->balance_cents > 0)
                                    <div><a class="btn btn-g btn-sm" href="{{ route('titles.show',$title) }}#payment-entry" aria-label="{{ $title->direction==='refund' ? 'Registra rimborso per' : 'Incassa' }} {{ $title->reference ?: 'titolo '.$title->id }}">{{ $title->direction==='refund' ? 'Registra rimborso' : 'Incassa' }}</a></div>
                                @endif
                            @endcan
                        @endcan
                    </td>
                    <td><a href="{{ route('clients.policies.show',[$title->policy->client_id,$title->policy]) }}">{{ $title->contract->number }}</a></td>
                    <td><a href="{{ route('clients.show',$title->policy->client) }}">{{ $title->policy->client->name }}</a></td>
                    <td>{{ $title->contract->supplierAgency?->name ?: 'Rapporto diretto' }}</td><td>{{ $title->contract->company }}</td>
                    <td><x-status-text :alert="$title->state === 'overdue'">{{ $title->due_on->format('d/m/Y') }}</x-status-text></td>
                    <td>{{ $title->follow_up_on->format('d/m/Y') }}</td><td>{{ $title->coverage_from->format('d/m/Y') }} – {{ $title->coverage_to->format('d/m/Y') }}</td>
                    <td>{{ $title->payment_frequency }}</td><td>{{ \App\Support\Money::format($title->amount_cents) }}</td>
                    <td>{{ \App\Support\Money::format($title->allowance_cents) }}</td>
                    <td><x-status-text :alert="in_array($title->state, ['due_today', 'partial', 'overdue'], true)">{{ \App\Support\Money::format($title->balance_cents) }}</x-status-text></td>
                    <td>{{ $title->payment_method }}</td><td><x-title-status :title="$title" /></td>
                </tr>
            @empty
                <tr><td colspan="14">Nessun titolo corrisponde ai filtri. I titoli si creano dalla scheda della polizza.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $titles->links() }}
</x-panel></x-app-layout>
