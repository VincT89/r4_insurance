<nav class="client-section-nav client-policy-nav" aria-label="Tipologie di polizza">
    <a href="{{ route('clients.show', [$client, 'tab' => 'policies']) }}" @if(!request('category')) aria-current="page" @endif>Tutte le polizze</a>
    @foreach($policyCategories as $policyCategory)
        <a href="{{ route('clients.show', [$client, 'tab' => 'policies', 'category' => $policyCategory]) }}" @if(request('category') === $policyCategory) aria-current="page" @endif>{{ $policyCategory }}</a>
    @endforeach
</nav>
@if(request('category') && $policySubcategories->isNotEmpty())
    <nav class="client-section-nav client-policy-nav" aria-label="Sottorami del ramo selezionato"><a href="{{ route('clients.show', [$client, 'tab' => 'policies', 'category' => request('category')]) }}" @if(!request('subcategory')) aria-current="page" @endif>Tutti i sottorami</a>@foreach($policySubcategories as $subcategory)<a href="{{ route('clients.show', [$client, 'tab' => 'policies', 'category' => request('category'), 'subcategory' => $subcategory]) }}" @if(request('subcategory') === $subcategory) aria-current="page" @endif>{{ $subcategory }}</a>@endforeach</nav>
@endif
<x-panel title="Polizze del cliente">
    @if($policies->isNotEmpty())
        <div class="client-table-scroll policy-list-scroll" role="region" aria-label="Polizze del cliente" tabindex="0">
            <table class="t-table client-policy-table">
                <thead><tr><th>Polizza e bene assicurato</th><th>Compagnia e agenzia</th><th>Scadenza</th><th>Stato</th><th>Azioni</th></tr></thead>
                <tbody>
                @foreach($policies as $policy)
                    <tr>
                        <td data-label="Polizza"><a class="policy-number" href="{{ route('clients.policies.show', [$client, $policy]) }}">{{ $policy->number }}</a><div>{{ $policy->category }}@if($policy->subcategory) / {{ $policy->subcategory }}@endif</div>@if($policy->product)<div class="u-text-meta">{{ $policy->product }}</div>@endif<div>{{ $policy->vehicle?->plate ?? $policy->asset?->name ?? $policy->insured_subject ?? 'Bene assicurato non indicato' }}</div></td>
                        <td data-label="Compagnia e agenzia">{{ $policy->company }}<div class="u-text-meta">{{ $policy->supplierAgency?->name ?: 'Agenzia non collegata' }}</div></td>
                        <td data-label="Scadenza" data-export-type="date"><x-status-text :alert="$policy->is_expired">{{ $policy->expires_on->format('d/m/Y') }}</x-status-text><div class="u-text-meta">Dal {{ $policy->starts_on->format('d/m/Y') }}</div></td>
                        <td data-label="Stato"><x-policy-status :policy="$policy" /></td>
                        <td data-label="Azioni"><div class="policy-row-actions"><a href="{{ route('clients.policies.show', [$client, $policy]) }}">Apri polizza</a><x-policy-contract-link :policy="$policy" />@can('updateRegistry',$client)@can('ledger.manage')@if($policy->has_payable_titles)<a href="{{ route('clients.policies.show', [$client, $policy, 'tab'=>'payments']).'#payment-entry' }}">Registra pagamento</a>@endif@endcan
                        @endcan</div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="policy-empty">{{ request('category') ? 'Nessuna polizza corrisponde al ramo e al sottoramo selezionati.' : 'Nessuna polizza inserita. Usa Nuova polizza per iniziare.' }}</p>
    @endif
</x-panel>
