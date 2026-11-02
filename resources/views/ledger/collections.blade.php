<x-app-layout title="Contabilità">
    <x-page-header>
        <x-slot:title>Contabilità{{ $contextPolicy ? ' / '.$contextPolicy->number : ($contextClient ? ' / '.$contextClient->name : '') }}</x-slot:title>
        <x-slot:actions>
            @can('system.admin')<a class="btn btn-g" href="{{ route('agency-communications.collections', $contextPolicy ? ['scope'=>'pending','agency'=>$contextPolicy->supplier_agency_id,'search'=>$contextPolicy->number] : []) }}">Comunica alle agenzie</a>@endcan
            <a class="btn btn-g" href="{{ route('titles.index',$contextParameters) }}">Titoli</a>
            @if($contextPolicy)<a class="btn btn-g" href="{{ route('clients.policies.show',[$contextClient,$contextPolicy,'tab'=>'payments']) }}">Scheda polizza</a>@elseif($contextClient)<a class="btn btn-g" href="{{ route('clients.show',$contextClient) }}">Scheda cliente</a>@endif
            @if($contextParameters)<a class="btn btn-g" href="{{ route('collections.index') }}">Tutti i movimenti</a>@endif
        </x-slot:actions>
    </x-page-header>
    @include('ledger.collection-filters')
    <section data-collection-workspace aria-label="Risultati contabilità">
        <x-panel class="collection-summary" aria-label="Riepilogo contabilità">
            <dl class="collection-summary-values">
                <div><dt>Incassi</dt><dd data-collection-total="receipts">{{ \App\Support\Money::format((int)$totals->receipts) }}</dd></div>
                <div><dt>Rimborsi</dt><dd data-collection-total="refunds">{{ \App\Support\Money::format((int)$totals->refunds) }}</dd></div>
                <div><dt>Saldo incassi meno rimborsi</dt><dd data-collection-total="total">{{ \App\Support\Money::format((int)$totals->total) }}</dd></div>
            </dl>
            <details class="collection-cash-details"><summary>Ripartizione per chi ha gestito il pagamento</summary>
                <dl><div><dt>Saldo della nostra agenzia</dt><dd data-collection-total="ours">{{ \App\Support\Money::format((int)$totals->ours) }}</dd></div><div><dt>Saldo compagnie e altre agenzie</dt><dd data-collection-total="direct">{{ \App\Support\Money::format((int)$totals->total-(int)$totals->ours) }}</dd></div></dl>
            </details>
            <p class="collection-summary-note">Totali di tutti i movimenti filtrati, in base alla data effettiva del pagamento. Gli storni sono esclusi.</p>
        </x-panel>
        <x-panel title="Riepilogo per compagnia e agenzia">
            <div data-collection-actions></div>
            <div class="client-table-scroll" role="region" aria-label="Totali per compagnia e agenzia" tabindex="0">
                <table class="collection-group-table"><thead><tr><th>Compagnia / agenzia</th><th>Incassi</th><th>Rimborsi</th><th>Saldo</th><th>Stampa</th></tr></thead>
                    <tbody data-collection-groups>
                        @forelse($groups as $group)
                            <tr><th scope="row">{{ $group->company }}<small>{{ $group->agency }}</small></th>
                                <td>{{ \App\Support\Money::format((int)$group->receipts) }}<small>{{ $group->receipt_count }} {{ (int)$group->receipt_count===1 ? 'incasso' : 'incassi' }}</small></td>
                                <td>{{ \App\Support\Money::format((int)$group->refunds) }}<small>{{ $group->refund_count }} {{ (int)$group->refund_count===1 ? 'rimborso' : 'rimborsi' }}</small></td>
                                <td>{{ \App\Support\Money::format((int)$group->total) }}</td>
                                <td><button type="button" class="btn btn-g btn-sm" data-collection-export="pdf" data-collection-group="{{ $group->key }}" aria-label="Stampa {{ $group->company }} / {{ $group->agency }}">Stampa / PDF</button></td>
                            </tr>
                        @empty<tr><td colspan="5">Nessun movimento valido corrisponde ai filtri.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </x-panel>
        <x-panel title="Movimenti" x-data="{accountingDetails:false}">
            <label class="collection-detail-toggle"><input type="checkbox" x-model="accountingDetails"> Mostra consulente, riferimenti e documenti</label>
            <div class="client-table-scroll" role="region" aria-label="Movimenti di contabilità" tabindex="0">
                <table class="t-table collection-movements" :class="{'show-accounting-details':accountingDetails}" data-collection-table data-filter-form="ledger-filters" data-table-query="{{ json_encode($filters) }}">
                    <thead><tr><th>Data</th><th>Contraente</th><th class="collection-extra">Consulente</th><th>Polizza / titolo</th><th>Compagnia</th><th>Agenzia</th><th>Importo</th><th>Tipo pagamento</th><th class="collection-extra">Gestito da</th><th>Stato</th><th class="collection-extra">Tipo documento</th><th class="collection-extra">Riferimento</th><th class="collection-extra">Documenti</th></tr></thead>
                    <tbody>
                        @forelse($collections as $collection)
                            @php
                                $title = $collection->title;
                                $contract = $title->contract;
                                $companyName = $contract->company ?: 'Compagnia non indicata';
                                $agencyName = $title->supplierAgency?->name ?: 'Rapporto diretto';
                            @endphp
                            <tr data-collection-cents="{{ $collection->amount_cents }}" data-collection-direction="{{ $collection->direction }}" data-collection-recipient="{{ $collection->recipient }}" data-collection-voided="{{ $collection->voided_at ? 1 : 0 }}"
                                data-collection-group="{{ \App\Services\CollectionSummary::key($title->supplier_company_id,$title->supplier_agency_id,$companyName) }}" data-collection-company="{{ $companyName }}" data-collection-agency="{{ $agencyName }}">
                                <td>{{ $collection->received_on->format('d/m/Y') }}</td>
                                <td>{{ $title->policy->client->name }}</td>
                                <td class="collection-extra">{{ $collection->consultant?->name ?: 'Non indicato' }}</td>
                                <td><a href="{{ route('titles.show',$collection->insurance_title_id) }}">{{ $contract->number }} / #{{ $collection->insurance_title_id }}</a></td>
                                <td>{{ $companyName }}</td><td>{{ $agencyName }}</td>
                                <td class="collection-amount">{{ \App\Support\Money::format($collection->direction === 'refund' ? -$collection->amount_cents : $collection->amount_cents) }}</td>
                                <td>{{ $collection->payment_method }}</td>
                                <td class="collection-extra">{{ \App\Models\InsuranceCollection::RECIPIENTS[$collection->recipient] }}</td>
                                <td>{{ $collection->voided_at ? 'Stornato' : ($collection->direction === 'refund' ? 'Rimborso registrato' : 'Incasso registrato') }}</td>
                                <td class="collection-extra">{{ \App\Models\InsuranceCollection::DOCUMENT_TYPES[$collection->document_type] }}</td>
                                <td class="collection-extra">{{ $collection->reference ?: 'Non indicato' }}</td>
                                <td class="collection-extra">@foreach($collection->documents as $document)<a href="{{ route('attachments.preview',$document) }}">{{ $document->original_name }}</a><br>@endforeach</td>
                            </tr>
                        @empty<tr><td colspan="13">Nessun movimento corrisponde ai filtri.</td></tr>@endforelse
                    </tbody>
                    <tfoot><tr><td colspan="13"><div class="collection-table-total"><strong>Saldo dei filtri, esclusi storni</strong><strong data-collection-total="total">{{ \App\Support\Money::format((int)$totals->total) }}</strong></div></td></tr></tfoot>
                </table>
            </div>
            {{ $collections->links() }}
        </x-panel>
    </section>
</x-app-layout>
