@props(['summary', 'filters' => [], 'filtered' => false])
<x-panel title="Situazione delle rate" class="payment-summary">
    <div class="payment-summary-values">
        @foreach(['future'=>['Rate future','Nessun incasso, scadenza successiva a oggi'], 'partial'=>['Parzialmente incassate','Residuo delle rate non ancora scadute'], 'due_today'=>['In scadenza oggi','Da incassare entro oggi'], 'overdue'=>['Rate scadute','Anche con incassi parziali']] as $state=>[$label,$hint])
            <a @class(['payment-summary-value','is-alert'=>$state !== 'future' && $summary[$state]['count'] > 0]) href="{{ route('titles.index', array_replace(\Illuminate\Support\Arr::except($filters,['page','_table_all']), ['state'=>$state])) }}">
                <span>{{ $label }}</span>
                <strong>{{ \App\Support\Money::format($summary[$state]['balance_cents']) }}</strong>
                <span>{{ $summary[$state]['count'] }} {{ $summary[$state]['count'] === 1 ? 'rata' : 'rate' }}</span>
                <small>{{ $hint }}</small>
            </a>
        @endforeach
    </div>
    <p class="collection-summary-note">{{ $filtered ? 'Residui dei filtri selezionati.' : 'Importi residui da incassare.' }} Ogni rata compare in un solo gruppo; incassate e annullate sono escluse.</p>
</x-panel>
