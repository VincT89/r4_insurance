<x-app-layout title="Scadenze">
    <x-page-header><x-slot:title>Scadenze</x-slot:title><x-slot:actions>@can('system.admin')<a class="btn btn-g" href="{{ route('agency-communications.collections',['scope'=>'pending']) }}">Incassi da comunicare</a>@endcan<a class="btn btn-g" href="{{ route('claims.index',['reminder'=>'all']) }}">Attività sinistri</a></x-slot:actions></x-page-header>
    <form id="deadline-filters" method="GET" action="{{ route('deadlines.index') }}" class="client-search-form register-filters" aria-label="Filtra scadenze" data-compact-filter-results x-data="{period: @js((string)$period)}">
        <x-form-group label="Cerca cliente o elemento" name="search"><input class="form-in" type="search" id="field-search" name="search" maxlength="255" value="{{ request('search') }}"></x-form-group>
        <x-form-group label="Tipo" name="kind"><select class="form-sel" id="field-kind" name="kind"><option value="">Tutti</option>@foreach(['title'=>'Titoli','policy'=>'Polizze','document'=>'Documenti','request'=>'Documenti da ricevere'] as $value=>$label)<option value="{{ $value }}" @selected(request('kind')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Periodo" name="period"><select class="form-sel" id="field-period" name="period" x-model="period">@foreach(['all'=>'Tutte le scadenze','overdue'=>'Già scadute','7'=>'Prossimi 7 giorni','15'=>'Prossimi 15 giorni','30'=>'Prossimi 30 giorni','45'=>'Prossimi 45 giorni','60'=>'Prossimi 60 giorni','custom'=>'Intervallo personalizzato'] as $value=>$label)<option value="{{ $value }}" @selected((string)$period===(string)$value)>{{ $label }}</option>@endforeach</select></x-form-group>
        @foreach(['from'=>'Dal','to'=>'Al'] as $key=>$label)<div class="form-g" data-form-group data-field-id="field-{{ $key }}" x-show="period==='custom'" x-cloak><label class="form-lbl" data-form-label for="field-{{ $key }}">{{ $label }}</label><input class="form-in" id="field-{{ $key }}" type="date" name="{{ $key }}" value="{{ request($key) }}" :disabled="period!=='custom'">@error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach
        <details class="search-more">
            <summary class="btn btn-g">Altri filtri{{ collect(request()->only(['agency','category','company','responsible','payment_frequency','_table_filters']))->filter()->isNotEmpty() ? ' (attivi)' : '' }}</summary>
            <div class="search-more-fields">
                <x-form-group label="Agenzia" name="agency"><select class="form-sel" name="agency"><option value="">Tutte</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)request('agency')===(string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Ramo" name="category"><select class="form-sel" id="field-category" name="category"><option value="">Tutti</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Compagnia" name="company"><select class="form-sel" id="field-company" name="company"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company }}" @selected(request('company')===$company)>{{ $company }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Responsabile cliente" name="responsible"><select class="form-sel" id="field-responsible" name="responsible"><option value="">Tutti</option>@foreach($responsibles as $person)<option value="{{ $person->id }}" @selected((string)request('responsible')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Frazionamento" name="payment_frequency"><select class="form-sel" name="payment_frequency"><option value="">Tutti</option>@foreach(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES as $frequency)<option value="{{ $frequency }}" @selected(request('payment_frequency')===$frequency)>{{ $frequency }}</option>@endforeach</select></x-form-group>
            </div>
            <div data-column-filters></div>
        </details>
        <div class="search-form-actions"><button class="btn btn-p" type="submit">Applica filtri</button>@if(request()->except(['page','_table_all']))<a class="btn btn-g" href="{{ route('deadlines.index') }}">Azzera filtri</a>@endif</div>
    </form>
    @php
        $staleCheck = auth()->user()->can('system.admin') && (!$lastCheck || \Illuminate\Support\Carbon::parse($lastCheck)->lt(now()->subMinutes(15)));
    @endphp
    <details class="list-summary deadline-help">
        <summary>{{ $staleCheck ? 'Avvisi automatici da verificare' : 'Come funzionano gli avvisi' }}</summary>
        <p>Gli avvisi iniziano 15 giorni prima della scadenza. Saldando un titolo si chiude l’avviso di pagamento. Un eventuale storno lo riapre. La scadenza del contratto resta distinta da quella del titolo.</p>
        @can('system.admin')
            <p>{{ $lastCheck ? 'Ultimo controllo notifiche del '.$lastCheck->format('d/m/Y').' alle '.$lastCheck->format('H:i').'.' : 'Il controllo automatico è da attivare.' }} Gli avvisi vengono aggiornati anche durante l’uso del gestionale.</p>
        @endcan
    </details>
    <x-panel>
        <p class="list-results" data-table-page-summary>{{ $deadlines->total() }} {{ $deadlines->total() === 1 ? 'scadenza' : 'scadenze' }}@if($deadlines->hasPages()) · Pagina {{ $deadlines->currentPage() }} di {{ $deadlines->lastPage() }}@endif</p>
        <div class="client-table-scroll" role="region" aria-label="Scadenze accessibili" tabindex="0">
            <table class="t-table deadline-table" data-list-details data-filter-form="deadline-filters">
                <thead><tr><th>Elemento</th><th>Cliente</th><th>Data</th><th>Tipo</th><th data-list-detail>Ramo</th><th data-list-detail>Compagnia</th><th data-list-detail>Agenzia</th><th data-list-detail>Frazionamento</th><th>Residuo</th><th data-list-detail>Responsabile cliente</th><th>Situazione</th></tr></thead>
                <tbody>
                @forelse($deadlines as $item)
                    @php
                        $date = \Illuminate\Support\Carbon::parse($item->due_date);
                        $policyReview = $item->kind === 'policy' && $item->review_status === 'review';
                        $overdue = $date->lt(today()) || ($item->kind === 'policy' && $item->review_status === 'expired');
                        $refund = $item->direction === 'refund';
                        $status = match(true) {
                            $policyReview => 'Stato polizza da verificare',
                            $overdue && $item->kind === 'title' => $refund ? 'Rimborso scaduto' : 'Sospeso di cassa',
                            $overdue => 'Scaduta',
                            $date->isToday() => 'Scade oggi',
                            $date->gt(today()->addDays(15)) => 'Futura',
                            default => 'In scadenza',
                        };
                        $url = match($item->kind) {
                            'title' => route('titles.show', $item->source_id),
                            'policy' => route('clients.policies.show', [$item->client_id, $item->source_id]),
                            'document' => route('attachments.download', $item->source_id),
                            default => route('clients.show', [$item->client_id, 'tab'=>'documents']).'#document-request-'.$item->source_id,
                        };
                    @endphp
                    <tr>
                        <td><a href="{{ $url }}" @class(['deadline-policy-reference'=>in_array($item->kind,['title','policy'])])>{{ $item->title }}</a>@if($item->kind==='title')<div class="u-text-meta">Titolo #{{ $item->source_id }}</div>@elseif($item->kind==='document')<div class="u-text-meta">{{ $item->review_status==='validated' ? 'Validato' : 'Da validare' }}</div>@endif</td>
                        <td><a href="{{ route('clients.show', [$item->client_id,'tab'=>'deadlines']) }}">{{ $item->client_name }}</a></td>
                        <td><x-status-text :alert="!$policyReview && $overdue">{{ $date->format('d/m/Y') }}</x-status-text></td>
                        <td>{{ ['title'=>$refund ? 'Rimborso da effettuare' : 'Titolo da incassare','policy'=>'Scadenza contratto','document'=>'Documento','request'=>'Documento da ricevere'][$item->kind] }}</td>
                        <td>{{ $item->category ?: 'Non indicato' }}</td><td>{{ $item->company ?: 'Non indicata' }}</td><td>{{ $item->agency_name ?: 'Non collegata' }}</td>
                        <td>{{ $item->payment_frequency ?: 'Non indicato' }}</td><td>@if($item->balance_cents !== null)<x-status-text :alert="$overdue">{{ \App\Support\Money::format($item->balance_cents) }}</x-status-text>@else Non previsto @endif</td>
                        <td>{{ $item->responsible_name ?: 'Non assegnato' }}</td><td @class(['client-overdue'=>!$policyReview && $overdue])>{{ $status }}</td>
                    </tr>
                @empty
                    <tr><td colspan="11">Nessuna scadenza per questi filtri.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $deadlines->links() }}
    </x-panel>
</x-app-layout>
