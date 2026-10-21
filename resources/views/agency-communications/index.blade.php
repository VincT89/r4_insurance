<x-app-layout title="Comunicazioni alle agenzie">
    <x-page-header><x-slot:title>Comunicazioni alle agenzie</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('agency-communications.history') }}">Storico invii</a><a class="btn btn-g" href="{{ route('email-settings.edit') }}">Impostazioni email</a></x-slot:actions></x-page-header>
    @include('agency-communications.navigation')
    <form method="GET" x-data="{period:@js($filters['period'])}" class="client-search-form filter-grid">
        <div class="form-row">
            <x-form-group label="Periodo dell’operazione" name="period"><select id="field-period" name="period" class="form-sel" x-model="period">@foreach(['day'=>'Giornata','15'=>'Ultimi 15 giorni','30'=>'Ultimi 30 giorni','month'=>'Mese','custom'=>'Intervallo personalizzato'] as $key=>$label)<option value="{{ $key }}" @selected($filters['period']===(string)$key)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Operazione" name="operation"><select id="field-operation" name="operation" class="form-sel">@foreach(\App\Services\AgencyCommunicationOperations::LABELS as $value=>$label)<option value="{{ $value }}" @selected($filters['operation']===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <div x-show="period==='day'"><x-form-group label="Giornata" name="date"><input id="field-date" class="form-in" type="date" name="date" value="{{ $filters['date'] }}" max="{{ today()->toDateString() }}" :disabled="period!=='day'"></x-form-group></div>
            <div x-show="period==='month'" x-cloak><x-form-group label="Mese" name="month"><input id="field-month" class="form-in" type="month" name="month" value="{{ $filters['month'] }}" :disabled="period!=='month'"></x-form-group></div>
            <div x-show="period==='custom'" x-cloak><x-form-group label="Dal" name="from"><input id="field-from" class="form-in" type="date" name="from" value="{{ $filters['from'] ?? '' }}" max="{{ today()->toDateString() }}" :disabled="period!=='custom'" :required="period==='custom'"></x-form-group></div>
            <div x-show="period==='custom'" x-cloak><x-form-group label="Al" name="to"><input id="field-to" class="form-in" type="date" name="to" value="{{ $filters['to'] ?? '' }}" max="{{ today()->toDateString() }}" :disabled="period!=='custom'" :required="period==='custom'"></x-form-group></div>
            <x-form-group label="Agenzia" name="agency"><select id="field-agency" name="agency" class="form-sel"><option value="">Tutte le agenzie</option>@foreach($options['agencies'] as $agencyOption)<option value="{{ $agencyOption->id }}" @selected((string)($filters['agency'] ?? '')===(string)$agencyOption->id)>{{ $agencyOption->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Compagnia" name="company"><select id="field-company" name="company" class="form-sel"><option value="">Tutte le compagnie</option>@foreach($options['companies'] as $company)<option value="{{ $company }}" @selected(($filters['company'] ?? '')===$company)>{{ $company }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Stato" name="status"><select id="field-status" name="status" class="form-sel"><option value="">Tutti gli stati</option>@foreach(\App\Domain\Core\Queries\PolicyPortfolioQuery::STATES as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Cerca cliente, polizza o appendice" name="search"><input id="field-search" class="form-in" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="255"></x-form-group>
        </div><div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('agency-communications.index') }}">Azzera filtri</a></div>
    </form>
    <p class="u-mb-md">{{ \App\Services\AgencyCommunicationOperations::LABELS[$filters['operation']] }}: {{ $range }}</p>
    <p class="u-text-meta u-mb-md">Seleziona polizze e aggiunte di coperture per agenzia, controlla premi e allegati, poi conferma un’unica email. Le appendici compaiono nella giornata in cui sono state registrate, anche per polizze precedenti.</p>
    @unless($mailReady)<p class="u-mb-md">Per inviare, completa la casella in <a href="{{ route('email-settings.edit') }}">Impostazioni email</a>. Puoi già preparare e controllare la selezione.</p>@endunless
    <section data-communication-selection data-selection-user="{{ auth()->id() }}" data-selection-scope="{{ $selectionScope }}" data-selection-field="operations">
        @include('agency-communications.selection-actions', ['reviewRoute'=>'agency-communications.review'])
        <x-panel title="Polizze e variazioni del periodo">
            <p class="communication-pagination-summary" data-table-page-summary>{{ number_format($policies->total(), 0, ',', '.') }} {{ $policies->total() === 1 ? 'operazione' : 'operazioni' }} · Pagina {{ $policies->currentPage() }} di {{ $policies->lastPage() }}</p>
            <div class="client-table-scroll" role="region" aria-label="Operazioni da comunicare alle agenzie" tabindex="0">
                <table class="t-table"><thead><tr><th>Seleziona</th><th>Inserita il</th><th>Ultimo rinnovo</th><th>Operazione</th><th>Agenzia</th><th>Contraente</th><th>Polizza</th><th>Riferimento appendice</th><th>Compagnia</th><th>Premio dell’operazione</th><th>Stato polizza</th><th>Comunicazione</th></tr></thead><tbody>
                    @forelse($policies as $policy)
                        @php($agency = $policy->supplierAgency)
                        @php($change = $policy->communicationChange)
                        @php($delivery = $change ? $changeDeliveries->get($change->id) : $deliveries->get($policy->id))
                        @php($eligible = $agency && in_array($policy->status,['active','renewed']) && !$policy->expires_on->lt(today()) && (!$change || !$change->expires_on->lt(today())))
                        <tr><td>
                            @if($eligible)
                                <input type="checkbox" value="{{ $change ? 'change-'.$change->id : $policy->id }}" data-communication-item data-agency="{{ $agency->id }}" aria-label="{{ $change ? 'Includi appendice '.$change->reference.' della polizza '.$policy->number : 'Includi polizza '.$policy->number }}" @disabled($delivery || $agency->status !== 'active' || !filter_var($agency->email, FILTER_VALIDATE_EMAIL))>
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
        </x-panel>
    </section>
</x-app-layout>
