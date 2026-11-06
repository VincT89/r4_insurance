@php($otherFilters=filled($filters['company']??null) || filled($filters['status']??null) || $filters['operation']!=='all' || filled(request('_table_filters')))
<x-app-layout title="Comunicazioni alle agenzie">
    <x-page-header><x-slot:title>Comunicazioni alle agenzie</x-slot:title></x-page-header>
    <div class="communication-workspace">
    @include('agency-communications.navigation')
    <details class="communication-search" id="communication-search" @if($errors->any()) open @endif><summary>Filtra elenco</summary>
    <form id="communication-operation-filters" method="GET" action="{{ route('agency-communications.index') }}" x-data="{period:@js($filters['period'])}" class="client-search-form communication-filters" data-compact-filter-results aria-label="Filtra operazioni da comunicare">
        <div class="communication-filter-primary">
            <x-form-group label="Agenzia" name="agency"><select id="field-agency" name="agency" class="form-sel"><option value="">Tutte le agenzie</option>@foreach($options['agencies'] as $agencyOption)<option value="{{ $agencyOption->id }}" @selected((string)($filters['agency']??'')===(string)$agencyOption->id)>{{ $agencyOption->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Periodo" name="period"><select id="field-period" name="period" class="form-sel" x-model="period">@foreach(['day'=>'Giornata','15'=>'Ultimi 15 giorni','30'=>'Ultimi 30 giorni','month'=>'Mese','custom'=>'Intervallo personalizzato'] as $key=>$label)<option value="{{ $key }}" @selected($filters['period']===(string)$key)>{{ $label }}</option>@endforeach</select></x-form-group>
            <div x-show="period==='day'"><x-form-group label="Giornata" name="date"><input id="field-date" class="form-in" type="date" name="date" value="{{ $filters['date'] }}" max="{{ today()->toDateString() }}" :disabled="period!=='day'"></x-form-group></div>
            <div x-show="period==='month'" x-cloak><x-form-group label="Mese" name="month"><input id="field-month" class="form-in" type="month" name="month" value="{{ $filters['month'] }}" :disabled="period!=='month'"></x-form-group></div>
            <x-form-group label="Cliente, polizza o appendice" name="search"><input id="field-search" type="search" class="form-in" name="search" value="{{ $filters['search']??'' }}" maxlength="255"></x-form-group>
        </div>
        <div class="communication-filter-dates" x-show="period==='custom'" x-cloak>
            <x-form-group label="Dal" name="from"><input id="field-from" class="form-in" type="date" name="from" value="{{ $filters['from']??'' }}" max="{{ today()->toDateString() }}" :disabled="period!=='custom'" :required="period==='custom'"></x-form-group>
            <x-form-group label="Al" name="to"><input id="field-to" class="form-in" type="date" name="to" value="{{ $filters['to']??'' }}" max="{{ today()->toDateString() }}" :disabled="period!=='custom'" :required="period==='custom'"></x-form-group>
        </div>
        <div class="collection-filter-controls">
            <details class="search-more" @if($otherFilters) open @endif><summary class="btn btn-g">Altri filtri{{ $otherFilters ? ' (attivi)' : '' }}</summary>
                <div class="search-more-fields">
                    <x-form-group label="Operazione" name="operation"><select id="field-operation" name="operation" class="form-sel">@foreach(\App\Services\AgencyCommunicationOperations::LABELS as $value=>$label)<option value="{{ $value }}" @selected($filters['operation']===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
                    <x-form-group label="Compagnia" name="company"><select id="field-company" name="company" class="form-sel"><option value="">Tutte le compagnie</option>@foreach($options['companies'] as $company)<option value="{{ $company }}" @selected(($filters['company']??'')===$company)>{{ $company }}</option>@endforeach</select></x-form-group>
                    <x-form-group label="Stato polizza" name="status"><select id="field-status" name="status" class="form-sel"><option value="">Tutti gli stati</option>@foreach(\App\Domain\Core\Queries\PolicyPortfolioQuery::STATES as $value=>$label)<option value="{{ $value }}" @selected(($filters['status']??'')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
                </div>
                <div data-column-filters></div>
            </details>
            <div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('agency-communications.index') }}">Azzera filtri</a></div>
        </div>
    </form>
    </details>
    @unless($mailReady)<p class="communication-mail-notice">Puoi preparare l’email. @can('system.admin')Per inviarla, completa la casella nelle <a href="{{ route('email-settings.edit') }}">Impostazioni email</a>.@else Per inviarla, un amministratore deve configurare la casella di posta.@endcan</p>@endunless
    <x-panel title="Polizze e variazioni da comunicare">
        <x-slot:headerActions><p class="list-results">{{ $range }}</p></x-slot:headerActions>
        @include('agency-communications.batches',['batchRoute'=>'agency-communications.review','batchSingular'=>'operazione da comunicare','batchPlural'=>'operazioni da comunicare'])
    </x-panel>
    <details class="communication-manual-selection" @if(request('page') || filled(request('_table_filters'))) open @endif><summary>Scegli singole operazioni</summary>
    <section data-communication-selection data-selection-user="{{ auth()->id() }}" data-selection-scope="{{ $selectionScope }}" data-selection-field="operations">
        <x-panel title="Polizze e variazioni">
            <x-slot:headerActions><p class="list-results" data-table-page-summary>{{ number_format($policies->total(),0,',','.') }} {{ $policies->total()===1 ? 'operazione' : 'operazioni' }} / {{ $range }}@if($policies->hasPages()) / Pagina {{ $policies->currentPage() }} di {{ $policies->lastPage() }}@endif</p></x-slot:headerActions>
            @include('agency-communications.selection-actions')
            <div class="client-table-scroll" role="region" aria-label="Operazioni da comunicare alle agenzie" tabindex="0">
                <table class="t-table" data-list-details="agency-operations" data-details-label="Altri dati" data-filter-form="communication-operation-filters" data-table-query="{{ json_encode($filters) }}"><thead><tr><th>Seleziona</th><th data-list-detail>Inserita il</th><th data-list-detail>Ultimo rinnovo</th><th>Operazione</th><th>Agenzia</th><th>Contraente</th><th>Polizza</th><th data-list-detail>Riferimento appendice</th><th data-list-detail>Compagnia</th><th>Premio dell’operazione</th><th data-list-detail>Stato polizza</th><th>Comunicazione</th></tr></thead><tbody>
                    @forelse($policies as $policy)
                        @php($agency = $policy->supplierAgency)
                        @php($change = $policy->communicationChange)
                        @php($delivery = $change ? $changeDeliveries->get($change->id) : $deliveries->get($policy->id))
                        @php($eligible = $agency && in_array($policy->status,['active','renewed']) && !$policy->expires_on->lt(today()) && (!$change || !$change->expires_on->lt(today())))
                        <tr data-list-label="{{ $policy->number }} · {{ $policy->client->name }}"><td>
                            @if($eligible)
                                <input type="checkbox" value="{{ $change ? 'change-'.$change->id : $policy->id }}" data-communication-item data-agency="{{ $agency->id }}" aria-label="{{ $change ? 'Includi appendice '.$change->reference.' della polizza '.$policy->number : 'Includi polizza '.$policy->number }}" @disabled($delivery || $agency->status !== 'active')>
                            @endif
                        </td><td>{{ ($change?->created_at ?? $policy->created_at)->format('d/m/Y') }}</td><td>{{ $change ? 'Non pertinente' : ($policy->last_renewed_at?->format('d/m/Y') ?: 'Non registrato') }}</td><td>{{ $change ? 'Aggiunta di coperture' : 'Polizza / rinnovo' }}</td><td>@if($agency)<a href="{{ route('suppliers.show',$agency) }}">{{ $agency->name }}</a>@else Agenzia da assegnare @endif</td><td>{{ $policy->client->name }}</td><td><a href="{{ route('clients.policies.show',[$policy->client_id,$policy]) }}">{{ $policy->number }}</a></td><td>@if($change)<a href="{{ route('clients.policy-changes.show',[$policy->client_id,$change]) }}">{{ $change->reference }}</a>@else Non pertinente @endif</td><td>{{ $policy->company }}</td><td>{{ $change ? \App\Support\Money::format($change->premium_cents) : ($policy->premium === null ? 'Non indicato' : \App\Support\Money::format(\App\Support\Money::cents((string)$policy->premium))) }}</td><td><x-policy-status :policy="$policy" /></td><td>
                            @if($delivery)<a href="{{ route('agency-communications.show',$delivery->id) }}">{{ \App\Models\AgencyCommunication::STATUSES[$delivery->status] ?? 'Controlla invio' }}</a>
                            @elseif(!$agency) Assegna l’agenzia
                            @elseif($agency->status !== 'active') Agenzia non attiva
                            @elseif(!filter_var($agency->email,FILTER_VALIDATE_EMAIL))<a href="{{ route('suppliers.edit',$agency) }}">Completa email agenzia</a>
                            @elseif(!$eligible) <span class="status-alert">Non inviabile</span>
                            @else {{ $policy->ad_horas ? 'Ad horas: gestire subito' : 'Da inviare' }} @endif
                        </td></tr>
                    @empty<tr><td colspan="12">Nessuna operazione nel periodo con i filtri scelti.</td></tr>@endforelse
                </tbody></table>
            </div>{{ $policies->links() }}
            @include('agency-communications.selection-groups',['reviewRoute'=>'agency-communications.review'])
        </x-panel>
    </section>
    </details>
    </div>
</x-app-layout>
