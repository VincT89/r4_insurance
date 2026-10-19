<nav class="client-section-nav client-policy-nav" aria-label="Tipologie di polizza">
    <a href="{{ route('clients.show', [$client, 'tab' => 'policies']) }}" @if(!request('category')) aria-current="page" @endif>Tutte le polizze</a>
    @foreach($policyCategories as $policyCategory)
        <a href="{{ route('clients.show', [$client, 'tab' => 'policies', 'category' => $policyCategory]) }}" @if(request('category') === $policyCategory) aria-current="page" @endif>{{ $policyCategory }}</a>
    @endforeach
</nav>
@if(request('category') && $policySubcategories->isNotEmpty())
    <nav class="client-section-nav client-policy-nav" aria-label="Sottorami del ramo selezionato"><a href="{{ route('clients.show', [$client, 'tab' => 'policies', 'category' => request('category')]) }}" @if(!request('subcategory')) aria-current="page" @endif>Tutti i sottorami</a>@foreach($policySubcategories as $subcategory)<a href="{{ route('clients.show', [$client, 'tab' => 'policies', 'category' => request('category'), 'subcategory' => $subcategory]) }}" @if(request('subcategory') === $subcategory) aria-current="page" @endif>{{ $subcategory }}</a>@endforeach</nav>
@endif
@forelse($policies->groupBy('category') as $category => $group)
    <x-panel :title="$category">
        @foreach($group->groupBy(fn($policy) => $policy->subcategory ?: 'Sottoramo da specificare') as $subcategory => $subgroup)
        <h2 class="sec-lbl client-subcategory-heading">{{ $subcategory }}</h2>
        <div class="client-table-scroll" role="region" aria-label="Polizze {{ $category }}" tabindex="0">
            <table class="t-table">
                <thead><tr><th>Polizza</th><th>Ramo</th><th>Sottoramo</th><th>Nome del prodotto</th><th>Compagnia</th><th>Agenzia</th><th>Bene o soggetto assicurato</th><th>Decorrenza</th><th>Scadenza</th><th>Stato</th></tr></thead>
                <tbody>
                    @foreach($subgroup as $policy)
                        <tr>
                            <td><a class="u-nowrap" href="{{ route('clients.policies.show', [$client, $policy]) }}">{{ $policy->number }}</a></td>
                            <td>{{ $policy->category }}</td>
                            <td>{{ $policy->subcategory ?: 'Non specificato' }}</td>
                            <td>{{ $policy->product ?: 'Non indicato' }}</td>
                            <td>{{ $policy->company }}</td>
                            <td>{{ $policy->supplierAgency?->name ?: 'Non collegata' }}</td>
                            <td>{{ $policy->vehicle?->plate ?? $policy->asset?->name ?? $policy->insured_subject ?? 'Non indicato' }}</td>
                            <td>{{ $policy->starts_on->format('d/m/Y') }}</td>
                            <td><x-status-text :alert="$policy->is_expired">{{ $policy->expires_on->format('d/m/Y') }}</x-status-text></td>
                            <td><x-policy-status :policy="$policy" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach
    </x-panel>
@empty
    <x-panel :title="request('category', 'Polizze')" padded><p>{{ request('category') ? 'Nessuna polizza di questa tipologia per il cliente.' : 'Nessuna polizza inserita. Usa Nuova polizza per iniziare.' }}</p></x-panel>
@endforelse
