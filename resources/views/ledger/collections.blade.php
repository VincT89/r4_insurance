<x-app-layout title="Contabilità">
    <x-page-header>
        <x-slot:title>Contabilità{{ $contextPolicy ? ' / '.$contextPolicy->number : ($contextClient ? ' / '.$contextClient->name : '') }}</x-slot:title>
        <x-slot:actions>
            <a class="btn btn-g" href="{{ route('allowances.index',$contextParameters+$ledgerPeriodParameters) }}">Abbuoni</a>
            @can('system.admin')<a class="btn btn-g" href="{{ route('agency-communications.collections', $contextPolicy ? ['scope'=>'pending','agency'=>$contextPolicy->supplier_agency_id,'search'=>$contextPolicy->number] : []) }}">Comunica alle agenzie</a>@endcan
            <a class="btn btn-g" href="{{ route('titles.index',$contextParameters) }}">Titoli</a>
            @if($contextPolicy)<a class="btn btn-g" href="{{ route('clients.policies.show',[$contextClient,$contextPolicy,'tab'=>'payments']) }}">Scheda polizza</a>@elseif($contextClient)<a class="btn btn-g" href="{{ route('clients.show',$contextClient) }}">Scheda cliente</a>@endif
            @if($contextParameters)<a class="btn btn-g" href="{{ route('collections.index') }}">Tutti i movimenti</a>@endif
        </x-slot:actions>
    </x-page-header>
    @include('ledger.collection-filters')
    <section data-collection-workspace aria-label="Risultati contabilità">
        <x-panel class="collection-summary collection-overview" aria-label="Riepilogo contabilità">
            <dl class="collection-summary-values">
                <div><dt>Incassi</dt><dd data-collection-total="receipts">{{ \App\Support\Money::format((int)$totals->receipts) }}</dd></div>
                <div><dt>Rimborsi</dt><dd data-collection-total="refunds">{{ \App\Support\Money::format((int)$totals->refunds) }}</dd></div>
                <div><dt>Saldo<span class="sr-only"> incassi meno rimborsi</span></dt><dd data-collection-total="total">{{ \App\Support\Money::format((int)$totals->total) }}</dd></div>
            </dl>
            <p class="collection-summary-note">Totali al netto degli storni.</p>
            <details class="collection-cash-details collection-provider-summary"><summary>Riepilogo per compagnia e agenzia</summary>
            <div class="client-table-scroll" role="region" aria-label="Totali per compagnia e agenzia" tabindex="0">
                <table class="collection-group-table"><thead><tr><th>Compagnia / agenzia</th><th>Incassi</th><th>Rimborsi</th><th>Saldo</th><th>Stampa</th></tr></thead>
                    <tbody data-collection-groups>
                        @forelse($groups as $group)
                            <tr><th scope="row">{{ $group->company }}<small>{{ $group->agency }}</small></th>
                                <td data-label="Incassi">{{ \App\Support\Money::format((int)$group->receipts) }}<small>{{ $group->receipt_count }} {{ (int)$group->receipt_count===1 ? 'incasso' : 'incassi' }}</small></td>
                                <td data-label="Rimborsi">{{ \App\Support\Money::format((int)$group->refunds) }}<small>{{ $group->refund_count }} {{ (int)$group->refund_count===1 ? 'rimborso' : 'rimborsi' }}</small></td>
                                <td data-label="Saldo">{{ \App\Support\Money::format((int)$group->total) }}</td>
                                <td><button type="button" class="btn btn-g btn-sm" data-collection-export="pdf" data-collection-group="{{ $group->key }}" aria-label="Stampa {{ $group->company }} / {{ $group->agency }}">Stampa / PDF</button></td>
                            </tr>
                        @empty<tr><td colspan="5">Nessun movimento valido corrisponde ai filtri.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
            <details class="collection-cash-details"><summary>Ripartizione per chi ha gestito il pagamento</summary>
                <dl><div><dt>Saldo della nostra agenzia</dt><dd data-collection-total="ours">{{ \App\Support\Money::format((int)$totals->ours) }}</dd></div><div><dt>Saldo compagnie e altre agenzie</dt><dd data-collection-total="direct">{{ \App\Support\Money::format((int)$totals->total-(int)$totals->ours) }}</dd></div></dl>
            </details>
            </details>
        </x-panel>
        <x-panel title="Movimenti" id="movimenti">
            <x-slot:headerActions><p class="list-results" data-table-page-summary>{{ $collections->total() }} {{ $collections->total() === 1 ? 'movimento' : 'movimenti' }}@if($collections->hasPages()) · Pagina {{ $collections->currentPage() }} di {{ $collections->lastPage() }}@endif</p></x-slot:headerActions>
            <div class="collection-list-tools">
                <div data-collection-actions></div>
            </div>
            <div class="client-table-scroll" role="region" aria-label="Movimenti di contabilità" tabindex="0">
                <table class="t-table collection-movements" data-list-details="collections" data-collection-table data-filter-form="ledger-filters" data-table-query="{{ json_encode($filters) }}">
                    <thead><tr><th>Contraente</th><th>Polizza / titolo</th><th>Data</th><th>Importo</th><th>Tipo pagamento</th><th data-list-detail>Compagnia</th><th data-list-detail>Agenzia</th><th data-list-detail>Stato</th><th data-list-detail>Gestito da</th><th data-list-detail>Consulente</th><th data-list-detail>Tipo documento</th><th data-list-detail>Riferimento</th><th data-list-detail>Documenti</th></tr></thead>
                    <tbody>
                        @forelse($collections as $collection)
                            @php
                                $title = $collection->title;
                                $contract = $title->contract;
                                $companyName = $contract->company ?: 'Compagnia non indicata';
                                $agencyName = $title->supplierAgency?->name ?: 'Rapporto diretto';
                                $refund = $collection->direction === 'refund';
                                $movementLabel = $refund ? 'Rimborso' : 'Incasso';
                            @endphp
                            <tr data-list-label="{{ $movementLabel }} di {{ $title->policy->client->name }}, {{ $contract->number }}, movimento {{ $collection->id }}" data-collection-cents="{{ $collection->amount_cents }}" data-collection-direction="{{ $collection->direction }}" data-collection-recipient="{{ $collection->recipient }}" data-collection-voided="{{ $collection->voided_at ? 1 : 0 }}"
                                data-collection-group="{{ \App\Services\CollectionSummary::key($title->supplier_company_id,$title->supplier_agency_id,$companyName) }}" data-collection-company="{{ $companyName }}" data-collection-agency="{{ $agencyName }}">
                                <td><a href="{{ route('clients.show', $title->policy->client) }}">{{ $title->policy->client->name }}</a></td>
                                <td><a href="{{ route('clients.policies.show', [$title->policy->client_id, $title->policy, 'tab'=>'payments']) }}">{{ $contract->number }}</a><div><a href="{{ route('titles.show', $title) }}#movimenti-del-titolo">Titolo #{{ $title->id }}</a></div></td>
                                <td>{{ $collection->received_on->format('d/m/Y') }}</td>
                                <td class="collection-amount">{{ \App\Support\Money::format($refund ? -$collection->amount_cents : $collection->amount_cents) }}<small data-table-export-ignore>{{ $movementLabel }}{{ $collection->voided_at ? ' stornato' : '' }}</small></td>
                                <td>{{ $collection->payment_method }}</td>
                                <td>{{ $companyName }}</td><td>{{ $agencyName }}</td>
                                <td>{{ $collection->voided_at ? 'Stornato' : $movementLabel.' registrato' }}</td>
                                <td>{{ \App\Models\InsuranceCollection::RECIPIENTS[$collection->recipient] }}</td>
                                <td>{{ $collection->consultant?->name ?: 'Non indicato' }}</td>
                                <td>{{ \App\Models\InsuranceCollection::DOCUMENT_TYPES[$collection->document_type] }}</td>
                                <td>{{ $collection->reference ?: 'Non indicato' }}</td>
                                <td>@forelse($collection->documents as $document)<a href="{{ $document->openUrl() }}">{{ $document->original_name }}</a><br>@empty<span data-table-export-ignore>Nessun allegato</span>@endforelse</td>
                            </tr>
                        @empty<tr><td colspan="13"><p>Nel periodo scelto non risultano incassi o rimborsi con questi filtri.</p><a class="btn btn-g u-mt-sm" href="{{ route('titles.index',['state'=>'open']) }}">Trova il titolo da incassare</a></td></tr>@endforelse
                    </tbody>
                    <tfoot><tr><td colspan="13"><div class="collection-table-total"><strong>Saldo dei filtri, esclusi storni</strong><strong data-collection-total="total">{{ \App\Support\Money::format((int)$totals->total) }}</strong></div></td></tr></tfoot>
                </table>
            </div>
            {{ $collections->links() }}
        </x-panel>
    </section>
</x-app-layout>
