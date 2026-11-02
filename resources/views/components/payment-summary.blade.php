@props(['summary', 'filters' => [], 'filtered' => false])
<x-panel title="Situazione dei titoli" class="payment-summary">
    <div class="payment-summary-values">
        @foreach(['overdue'=>['Sospese di cassa','Scaduti e non pagati'], 'due_soon'=>['Titoli in giacenza','Da incassare da oggi entro 15 giorni'], 'future'=>['Titoli futuri','Oltre 15 giorni, entro la fine del contratto']] as $state=>[$label,$hint])
            <a data-title-summary="{{ $state }}" data-keep-table-filters @class(['payment-summary-value','is-alert'=>$state === 'overdue' && $summary[$state]['count'] > 0]) href="{{ route('titles.index', array_replace(\Illuminate\Support\Arr::except($filters,['page','_table_all']), ['state'=>$state])) }}">
                <span>{{ $label }}</span>
                <strong data-title-summary-balance>{{ \App\Support\Money::format($summary[$state]['balance_cents']) }}</strong>
                <span data-title-summary-count>{{ $summary[$state]['count'] }} {{ $summary[$state]['count'] === 1 ? 'titolo' : 'titoli' }}</span>
                <small>{{ $hint }}</small>
            </a>
        @endforeach
    </div>
    <p class="collection-summary-note">{{ $filtered ? 'Importi corrispondenti ai filtri.' : 'Importi ancora da incassare.' }} Ogni titolo compare in un solo riepilogo. Incassati e annullati esclusi; i titoli futuri oltre la fine del contratto restano nello storico.</p>
</x-panel>
