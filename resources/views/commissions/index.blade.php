<x-app-layout title="Report provvigioni">
    <x-page-header>
        <x-slot:title>Report provvigioni</x-slot:title>
        <x-slot:actions><a class="btn btn-g" href="{{ route('reports.index') }}">Report polizze</a><a class="btn btn-g" href="{{ route('suppliers.index') }}">Condizioni delle agenzie</a></x-slot:actions>
    </x-page-header>
    @if($contextClient || $contextPolicy)
        <p class="u-mb-md">
            @if($contextClient)Cliente: <a href="{{ route('clients.show', $contextClient) }}">{{ $contextClient->name }}</a>.@endif
            @if($contextPolicy)Polizza: <a href="{{ route('clients.policies.show', [$contextPolicy->client_id, $contextPolicy]) }}">{{ $contextPolicy->number }}</a>.@endif
            <a href="{{ route('commissions.index') }}">Mostra tutti i clienti</a>
        </p>
    @endif
    <nav class="client-section-nav" aria-label="Vista provvigioni">
        @foreach(['positions'=>'Situazione delle rate', 'movements'=>'Movimenti del periodo'] as $mode=>$label)
            <a href="{{ route('commissions.index', array_replace($filters, ['mode'=>$mode, 'kind'=>null])) }}" @if($filters['mode']===$mode) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form class="client-search-form filter-grid" method="GET" action="{{ route('commissions.index') }}" x-data="{period:@js($filters['period'])}">
        <input type="hidden" name="mode" value="{{ $filters['mode'] }}">
        @if($contextClient)<input type="hidden" name="client" value="{{ $contextClient->id }}" data-export-label="Cliente" data-export-value="{{ $contextClient->name }}">@endif
        @if($contextPolicy)<input type="hidden" name="policy" value="{{ $contextPolicy->id }}" data-export-label="Polizza" data-export-value="{{ $contextPolicy->number }}">@endif
        <div class="form-row">
            <x-form-group label="Cliente o numero polizza" name="search"><input class="form-in" type="search" name="search" value="{{ $filters['search'] ?? '' }}"></x-form-group>
            <x-form-group label="Periodo" name="period"><select class="form-sel" name="period" x-model="period"><option value="month">Mese</option><option value="range">Intervallo di date</option><option value="all">Intero storico</option></select></x-form-group>
            <div x-show="period==='month'"><x-form-group label="Mese" name="month"><input class="form-in" type="month" name="month" value="{{ $filters['month'] }}" :disabled="period!=='month'"></x-form-group></div>
            <div x-show="period==='range'" x-cloak><x-form-group label="Dal" name="from"><input class="form-in" type="date" name="from" value="{{ $filters['from'] ?? '' }}" :disabled="period!=='range'" :required="period==='range'"></x-form-group></div>
            <div x-show="period==='range'" x-cloak><x-form-group label="Al" name="to"><input class="form-in" type="date" name="to" value="{{ $filters['to'] ?? '' }}" :disabled="period!=='range'" :required="period==='range'"></x-form-group></div>
            <x-form-group label="Agenzia / mandato" name="agency"><select class="form-sel" name="agency"><option value="">Tutte</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected(($filters['agency'] ?? '')==$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Compagnia" name="company"><select class="form-sel" name="company"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company'] ?? '')==$company->id)>{{ $company->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Raggruppa" name="group"><select class="form-sel" name="group">@foreach(['detail'=>'Dettaglio', 'agency'=>'Per agenzia', 'company'=>'Per compagnia', 'branch'=>'Per ramo'] as $key=>$label)<option value="{{ $key }}" @selected($filters['group']===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
        </div>
        <details class="u-mt-md" @if(filled($filters['branch']??null) || filled($filters['subbranch']??null) || filled($filters['state']??null) || filled($filters['kind']??null)) open @endif>
            <summary class="btn btn-g">Altri filtri</summary>
            <div class="form-row u-mt-md">
                <x-form-group label="Ramo" name="branch"><select class="form-sel" name="branch"><option value="">Tutti</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch']??'')==$branch->id)>{{ $branch->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Sottoramo" name="subbranch"><select class="form-sel" name="subbranch"><option value="">Tutti</option>@foreach($branches as $branch)<optgroup label="{{ $branch->name }}">@foreach($branch->subbranches as $sub)<option value="{{ $sub->id }}" @selected(($filters['subbranch']??'')==$sub->id)>{{ $sub->name }}</option>@endforeach</optgroup>@endforeach</select></x-form-group>
                <x-form-group label="Stato attuale della provvigione" name="state"><select class="form-sel" name="state"><option value="">Tutti</option>@foreach(\App\Models\InsuranceCommission::STATES as $key=>$label)<option value="{{ $key }}" @selected(($filters['state']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                @if($filters['mode']==='movements')
                    <x-form-group label="Tipo di movimento" name="kind"><select class="form-sel" name="kind"><option value="">Tutti</option>@foreach(\App\Models\InsuranceCommissionMovement::KINDS as $key=>$label)<option value="{{ $key }}" @selected(($filters['kind']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
                @endif
            </div>
        </details>
        <div class="search-form-actions"><a class="btn btn-g" href="{{ route('commissions.index', ['mode'=>$filters['mode'], 'client'=>$contextClient?->id, 'policy'=>$contextPolicy?->id, 'period'=>($contextClient || $contextPolicy) ? 'all' : 'month']) }}">Azzera filtri</a><button class="btn btn-p">Applica filtri</button></div>
    </form>
    <x-panel class="collection-summary" aria-label="Riepilogo provvigioni">
        <dl class="collection-summary-values">
            @foreach(['expected'=>'Previste', 'accrued'=>'Maturate', 'settled'=>'Liquidate'] as $key=>$label)
                <div><dt>{{ $label }}{{ $filters['mode']==='movements' ? ' nel periodo' : '' }}</dt><dd>{{ \App\Support\Money::format((int)$totals->$key) }}</dd></div>
            @endforeach
        </dl>
        <p class="collection-summary-note">
            @if($filters['mode']==='positions')
                {{ $filters['period']==='all' ? 'Tutte le rate comprese nei filtri, senza limiti di data' : 'Rate con inizio copertura nel periodo selezionato' }}; saldi aggiornati a oggi. Previste, maturate e liquidate sono fasi dello stesso importo e non si sommano.
                <span class="commission-summary-line">Da liquidare: <x-status-text :alert="$totals->payable > 0">{{ \App\Support\Money::format((int)$totals->payable) }}</x-status-text>. Da riconciliare: <x-status-text :alert="$totals->reconcile > 0">{{ \App\Support\Money::format((int)$totals->reconcile) }}</x-status-text>. Rate da completare: {{ (int)$totals->incomplete }}.</span>
            @else
                Movimenti per data effettiva, al netto degli storni del periodo. Per i residui complessivi apri “Situazione delle rate”.
            @endif
        </p>
    </x-panel>
    @if($filters['mode']==='positions' && $totals->incomplete > 0)
        <x-panel title="Calcoli da completare" padded>
            <p class="u-mb-md">Le rate senza condizioni o base di calcolo non producono importi stimati. Dopo aver configurato il mandato puoi calcolare quelle comprese nei filtri; quelle già calcolate conservano le condizioni originali.</p>
            <form method="POST" action="{{ route('commissions.calculate') }}">
                @csrf
                @foreach($filters as $key=>$value) @if($value !== null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                <label class="email-review-confirm"><input type="checkbox" name="confirmed" value="1" required> Confermo il calcolo delle rate da completare nei filtri selezionati.</label>
                <div class="insurance-form-actions"><button class="btn btn-p">Calcola rate da completare</button></div>
            </form>
        </x-panel>
    @endif
    <x-panel :title="$filters['mode']==='positions' ? 'Situazione provvigioni' : 'Movimenti provvigionali'">
        <div class="client-table-scroll" role="region" aria-label="Report provvigioni" tabindex="0">
            <table class="t-table">
                @if($filters['group']!=='detail')
                    <thead><tr><th>{{ ['agency'=>'Agenzia', 'company'=>'Compagnia', 'branch'=>'Ramo'][$filters['group']] }}</th><th data-export-type="integer">{{ $filters['mode']==='positions' ? 'Rate' : 'Movimenti' }}</th><th data-export-type="currency">Previste</th><th data-export-type="currency">Maturate</th><th data-export-type="currency">Liquidate</th>@if($filters['mode']==='positions')<th data-export-type="currency">Da liquidare</th><th data-export-type="currency">Da riconciliare</th><th data-export-type="integer">Da completare</th>@endif</tr></thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr><td>{{ $row->label }}</td><td>{{ $row->rows_count }}</td><td>{{ \App\Support\Money::format((int)$row->expected) }}</td><td>{{ \App\Support\Money::format((int)$row->accrued) }}</td><td>{{ \App\Support\Money::format((int)$row->settled) }}</td>@if($filters['mode']==='positions')<td><x-status-text :alert="$row->payable > 0">{{ \App\Support\Money::format((int)$row->payable) }}</x-status-text></td><td><x-status-text :alert="$row->reconcile > 0">{{ \App\Support\Money::format((int)$row->reconcile) }}</x-status-text></td><td>{{ (int)$row->incomplete }}</td>@endif</tr>
                        @empty
                            <tr><td colspan="{{ $filters['mode']==='positions' ? 8 : 5 }}">Nessun risultato per questi filtri.</td></tr>
                        @endforelse
                    </tbody>
                @else
                    <thead><tr><th data-export-type="date">{{ $filters['mode']==='positions' ? 'Inizio copertura rata' : 'Data movimento' }}</th><th>Contraente</th><th>Polizza</th><th>Titolo</th><th>Agenzia</th><th>Compagnia</th><th>Ramo</th><th>Sottoramo</th>
                        @if($filters['mode']==='positions')<th data-export-type="currency">Prevista</th><th data-export-type="currency">Maturata</th><th data-export-type="currency">Liquidata</th><th>Stato provvigione</th>
                        @else<th>Movimento</th><th data-export-type="currency">Importo</th><th>Riferimento</th><th>Causale</th>@endif
                        <th>Azioni</th></tr></thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($filters['mode']==='positions' ? $row->coverage_from : $row->effective_on)->format('d/m/Y') }}</td><td>{{ $row->client_name }}</td><td>{{ $row->number }}</td><td>{{ \App\Models\InsuranceTitle::KINDS[$row->title_kind] }} #{{ $row->title_id }}</td><td>{{ $row->agency_name ?: 'Non assegnata' }}</td><td>{{ $row->company }}</td><td>{{ $row->category }}</td><td>{{ $row->subcategory ?: 'Non specificato' }}</td>
                                @if($filters['mode']==='positions')
                                    <td>{{ $row->expected_cents === null ? 'Da completare' : \App\Support\Money::format((int)$row->expected_cents) }}</td><td>{{ \App\Support\Money::format((int)$row->accrued_cents) }}</td><td>{{ \App\Support\Money::format((int)$row->settled_cents) }}</td><td><x-status-text :alert="in_array($row->state,['reconcile','partial','accrued']) && $row->accrued_cents != $row->settled_cents">{{ \App\Models\InsuranceCommission::STATES[$row->state] }}</x-status-text></td>
                                @else
                                    <td>{{ \App\Models\InsuranceCommissionMovement::KINDS[$row->kind] }}{{ $row->amount_cents < 0 ? ' · Storno' : '' }}</td><td><x-status-text :alert="$row->amount_cents < 0">{{ \App\Support\Money::format((int)$row->amount_cents) }}</x-status-text></td><td>{{ $row->reference ?: 'Automatico' }}</td><td>{{ $row->reason }}</td>
                                @endif
                                <td><a class="btn btn-g btn-sm" href="{{ route('commissions.show',$row->title_id) }}">Apri provvigione</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="13">Nessun risultato per questi filtri.</td></tr>
                        @endforelse
                    </tbody>
                @endif
            </table>
        </div>
        {{ $rows->links() }}
    </x-panel>
</x-app-layout>
