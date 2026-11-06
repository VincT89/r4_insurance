@php
    $day=today()->toDateString();
    $ranges=['today'=>[$day,$day],'yesterday'=>[today()->subDay()->toDateString(),today()->subDay()->toDateString()],
        'last15'=>[today()->subDays(14)->toDateString(),$day],'month'=>[today()->startOfMonth()->toDateString(),$day]];
    $selectedView=$filters['scope']==='period' ? (array_search([$filters['from'],$filters['to']],$ranges,true) ?: 'custom') : $filters['scope'];
    $otherFilters=filled($filters['company']??null) || !empty($filters['unassigned']) || filled(request('_table_filters'));
    $heading=match($filters['scope']) {'pending'=>'Incassi da completare','attention'=>'Esiti da verificare',default=>'Incassi'};
    $rangeLabel=$filters['scope']==='period' && $filters['from']===$filters['to'] ? \Illuminate\Support\Carbon::parse($filters['from'])->format('j/n/y') : $range;
@endphp
<x-app-layout title="Comunicazioni alle agenzie">
    <x-page-header><x-slot:title>Comunicazioni alle agenzie</x-slot:title></x-page-header>
    <div class="communication-workspace">
        @include('agency-communications.navigation')
        <details class="communication-search" id="communication-search" @if($errors->any()) open @endif><summary>Filtra elenco</summary>
        <form id="communication-collection-filters" method="GET" action="{{ route('agency-communications.collections') }}" class="client-search-form communication-filters" data-compact-filter-results aria-label="Filtra incassi da comunicare" x-data="{view:@js($selectedView)}">
            <div class="communication-filter-primary">
                <x-form-group label="Agenzia" name="agency"><select name="agency" class="form-sel"><option value="">Tutte le agenzie</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected(($filters['agency']??'')==$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Mostra" name="view"><select name="view" class="form-sel" x-model="view">@foreach(['today'=>'Oggi','yesterday'=>'Ieri','last15'=>'Ultimi 15 giorni','month'=>'Questo mese','custom'=>'Intervallo personalizzato','pending'=>'Da completare / tutte le date','attention'=>'Esiti da verificare / tutte le date'] as $value=>$label)<option value="{{ $value }}" @selected($selectedView===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
                <x-form-group label="Cliente o polizza" name="search"><input type="search" class="form-in" name="search" maxlength="255" value="{{ $filters['search']??'' }}"></x-form-group>
            </div>
            <div class="communication-filter-dates" x-show="view==='custom'" @if($selectedView!=='custom') x-cloak @endif>
                @foreach(['from'=>'Incassati dal','to'=>'Al'] as $key=>$label)<x-form-group :label="$label" :name="$key"><input class="form-in" type="date" name="{{ $key }}" value="{{ $filters[$key]??$day }}" max="{{ $day }}" :disabled="view!=='custom'" :required="view==='custom'" @disabled($selectedView!=='custom')></x-form-group>@endforeach
            </div>
            <div class="collection-filter-controls">
                <details class="search-more" @if($otherFilters) open @endif>
                    <summary class="btn btn-g">Altri filtri{{ $otherFilters ? ' (attivi)' : '' }}</summary>
                    <div class="search-more-fields">
                        <x-form-group label="Compagnia" name="company"><select name="company" class="form-sel"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company']??'')==$company->id)>{{ $company->name }}</option>@endforeach</select></x-form-group>
                        <x-form-group label="Collegamento agenzia" name="unassigned"><select name="unassigned" class="form-sel"><option value="">Tutti</option><option value="1" @selected($filters['unassigned']??false)>Solo agenzia da assegnare</option></select></x-form-group>
                    </div>
                    <div data-column-filters></div>
                </details>
                <div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('agency-communications.collections') }}">Azzera filtri</a></div>
            </div>
        </form>
        </details>
        @unless($mailReady)<p class="communication-mail-notice">Puoi preparare l’email. @can('system.admin')Per inviarla, completa la casella nelle <a href="{{ route('email-settings.edit') }}">Impostazioni email</a>.@else Per inviarla, un amministratore deve configurare la casella di posta.@endcan</p>@endunless
        <x-panel title="Incassi da comunicare">
            <x-slot:headerActions><p class="list-results">{{ $rangeLabel }}</p></x-slot:headerActions>
            @include('agency-communications.batches',['batchRoute'=>'agency-communications.collections-review','batchSingular'=>'incasso da comunicare','batchPlural'=>'incassi da comunicare'])
        </x-panel>
        <details class="communication-manual-selection" @if($filters['scope']==='attention' || request('page') || filled(request('_table_filters'))) open @endif><summary>Scegli singoli incassi</summary>
        <section data-communication-selection data-selection-user="{{ auth()->id() }}" data-selection-scope="{{ $selectionScope }}" data-selection-field="collections">
            <x-panel :title="$heading">
                <x-slot:headerActions><p class="list-results" data-table-page-summary>{{ number_format($payments->total(),0,',','.') }} {{ $payments->total()===1 ? 'incasso' : 'incassi' }} / {{ $rangeLabel }}@if($payments->hasPages()) / Pagina {{ $payments->currentPage() }} di {{ $payments->lastPage() }}@endif</p></x-slot:headerActions>
                @include('agency-communications.selection-actions')
                <div class="client-table-scroll" role="region" aria-label="Incassi da comunicare alle agenzie" tabindex="0">
                    <table class="t-table" data-list-details="agency-payments" data-details-label="Altri dati" data-filter-form="communication-collection-filters" data-table-query="{{ json_encode($filters+['view'=>'']) }}">
                        <thead><tr><th>Seleziona</th><th>Data</th><th>Agenzia</th><th>Contraente</th><th>Polizza / titolo</th><th data-list-detail>Compagnia</th><th data-list-detail>Frazionamento</th><th>Importo</th><th data-list-detail>Metodo pagamento</th><th data-list-detail>Destinatario pagamento</th><th>Comunicazione</th></tr></thead>
                        <tbody>@forelse($payments as $payment)
                            @php($agency=$payment->title->contract->supplierAgency)
                            @php($delivery=$deliveries->get($payment->id))
                            <tr data-list-label="{{ $payment->title->contract->number }} / Incasso #{{ $payment->id }}">
                                <td>@if($agency)<input type="checkbox" value="{{ $payment->id }}" data-communication-item data-agency="{{ $agency->id }}" aria-label="Includi incasso {{ $payment->id }}" @disabled($delivery || $agency->status!=='active')>@endif</td>
                                <td>{{ $payment->received_on->format('d/m/Y') }}</td>
                                <td>@if($agency)<a href="{{ route('suppliers.show',$agency) }}">{{ $agency->name }}</a>@else Agenzia da assegnare @endif</td>
                                <td>{{ $payment->title->policy->client->name }}</td>
                                <td><a href="{{ route('titles.show',$payment->insurance_title_id) }}">{{ $payment->title->contract->number }} / #{{ $payment->insurance_title_id }}</a></td>
                                <td>{{ $payment->title->contract->company }}</td><td>{{ $payment->title->payment_frequency }}</td>
                                <td>{{ \App\Support\Money::format($payment->direction==='refund' ? -$payment->amount_cents : $payment->amount_cents) }}</td>
                                <td>{{ $payment->payment_method }}</td><td>{{ \App\Models\InsuranceCollection::RECIPIENTS[$payment->recipient] }}</td>
                                <td>@if($delivery)<a href="{{ route('agency-communications.show',$delivery->id) }}">{{ \App\Models\AgencyCommunication::STATUSES[$delivery->status] }}</a>
                                    @elseif(!$agency) Assegna l’agenzia
                                    @elseif($agency->status!=='active') Agenzia non attiva
                                    @elseif(!filter_var($agency->email,FILTER_VALIDATE_EMAIL))<a href="{{ route('suppliers.edit',$agency) }}">Completa email agenzia</a>
                                    @else {{ $payment->title->contract->ad_horas ? 'Ad horas' : 'Da comunicare' }} @endif
                                </td>
                            </tr>
                        @empty<tr><td colspan="11">Nessun incasso corrisponde ai filtri.</td></tr>@endforelse</tbody>
                    </table>
                </div>
                {{ $payments->links() }}
                @include('agency-communications.selection-groups',['reviewRoute'=>'agency-communications.collections-review','reviewAgencies'=>$agencies])
            </x-panel>
        </section>
        </details>
    </div>
</x-app-layout>
