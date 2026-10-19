<x-app-layout title="Comunicazioni alle agenzie">
    <x-page-header><x-slot:title>Comunicazioni alle agenzie</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('agency-communications.history') }}">Storico invii</a><a class="btn btn-g" href="{{ route('email-settings.edit') }}">Impostazioni email</a></x-slot:actions></x-page-header>
    @include('agency-communications.navigation')
    <form method="GET" x-data="{period:@js($filters['period'])}" class="client-search-form filter-grid">
        <div class="form-row">
            <x-form-group label="Periodo di inserimento" name="period"><select id="field-period" name="period" class="form-sel" x-model="period">@foreach(['day'=>'Giornata','15'=>'Ultimi 15 giorni','30'=>'Ultimi 30 giorni','month'=>'Mese','custom'=>'Intervallo personalizzato'] as $key=>$label)<option value="{{ $key }}" @selected($filters['period']===(string)$key)>{{ $label }}</option>@endforeach</select></x-form-group>
            <div x-show="period==='day'"><x-form-group label="Giornata" name="date"><input id="field-date" class="form-in" type="date" name="date" value="{{ $filters['date'] }}" max="{{ today()->toDateString() }}" :disabled="period!=='day'"></x-form-group></div>
            <div x-show="period==='month'" x-cloak><x-form-group label="Mese" name="month"><input id="field-month" class="form-in" type="month" name="month" value="{{ $filters['month'] }}" :disabled="period!=='month'"></x-form-group></div>
            <div x-show="period==='custom'" x-cloak><x-form-group label="Dal" name="from"><input id="field-from" class="form-in" type="date" name="from" value="{{ $filters['from'] ?? '' }}" max="{{ today()->toDateString() }}" :disabled="period!=='custom'" :required="period==='custom'"></x-form-group></div>
            <div x-show="period==='custom'" x-cloak><x-form-group label="Al" name="to"><input id="field-to" class="form-in" type="date" name="to" value="{{ $filters['to'] ?? '' }}" max="{{ today()->toDateString() }}" :disabled="period!=='custom'" :required="period==='custom'"></x-form-group></div>
            <x-form-group label="Agenzia" name="agency"><select id="field-agency" name="agency" class="form-sel"><option value="">Tutte le agenzie</option>@foreach($options['agencies'] as $agencyOption)<option value="{{ $agencyOption->id }}" @selected((string)($filters['agency'] ?? '')===(string)$agencyOption->id)>{{ $agencyOption->name }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Compagnia" name="company"><select id="field-company" name="company" class="form-sel"><option value="">Tutte le compagnie</option>@foreach($options['companies'] as $company)<option value="{{ $company }}" @selected(($filters['company'] ?? '')===$company)>{{ $company }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Stato" name="status"><select id="field-status" name="status" class="form-sel"><option value="">Tutti gli stati</option>@foreach(\App\Domain\Core\Queries\PolicyPortfolioQuery::STATES as $value=>$label)<option value="{{ $value }}" @selected(($filters['status'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
            <x-form-group label="Cerca cliente o polizza" name="search"><input id="field-search" class="form-in" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="255"></x-form-group>
        </div><div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('agency-communications.index') }}">Azzera filtri</a></div>
    </form>
    <p class="u-mb-md">Polizze inserite: {{ $range }}</p>
    <p class="u-text-meta u-mb-md">Seleziona le polizze per agenzia, controlla il riepilogo e conferma l’invio di un’unica email.</p>
    @unless($mailReady)<p class="u-mb-md">Per inviare, completa la casella in <a href="{{ route('email-settings.edit') }}">Impostazioni email</a>. Puoi già preparare e controllare la selezione.</p>@endunless
    <section data-communication-selection data-selection-user="{{ auth()->id() }}" data-selection-scope="{{ $selectionScope }}" data-selection-field="policies">
        @include('agency-communications.selection-actions', ['reviewRoute'=>'agency-communications.review'])
        <x-panel title="Polizze del periodo">
            <p class="communication-pagination-summary" data-table-page-summary>{{ number_format($policies->total(), 0, ',', '.') }} polizze · Pagina {{ $policies->currentPage() }} di {{ $policies->lastPage() }}</p>
            <div class="client-table-scroll" role="region" aria-label="Polizze da comunicare alle agenzie" tabindex="0">
                <table class="t-table"><thead><tr><th>Seleziona</th><th>Inserita il</th><th>Agenzia</th><th>Contraente</th><th>Polizza</th><th>Compagnia</th><th>Frazionamento</th><th>Stato</th><th>Comunicazione</th></tr></thead><tbody>
                    @forelse($policies as $policy)
                        @php($agency = $policy->supplierAgency)
                        @php($delivery = $deliveries->get($policy->id))
                        <tr><td>
                            @if($agency && in_array($policy->status,['active','renewed']) && !$policy->expires_on->lt(today()))
                                <input type="checkbox" value="{{ $policy->id }}" data-communication-item data-agency="{{ $agency->id }}" aria-label="Includi polizza {{ $policy->number }}" @disabled($delivery || $agency->status !== 'active' || !filter_var($agency->email, FILTER_VALIDATE_EMAIL))>
                            @endif
                        </td><td>{{ $policy->created_at->format('d/m/Y') }}</td><td>@if($agency)<a href="{{ route('suppliers.show',$agency) }}">{{ $agency->name }}</a>@else Agenzia da assegnare @endif</td><td>{{ $policy->client->name }}</td><td><a href="{{ route('clients.policies.show',[$policy->client_id,$policy]) }}">{{ $policy->number }}</a></td><td>{{ $policy->company }}</td><td>{{ $policy->payment_frequency ?: 'Non indicato' }}</td><td><x-policy-status :policy="$policy" /></td><td>
                            @if($delivery)<a href="{{ route('agency-communications.show',$delivery->id) }}">{{ \App\Models\AgencyCommunication::STATUSES[$delivery->status] ?? 'Controlla invio' }}</a>
                            @elseif(!$agency) Assegna l’agenzia
                            @elseif($agency->status !== 'active') Agenzia non attiva
                            @elseif(!filter_var($agency->email,FILTER_VALIDATE_EMAIL))<a href="{{ route('suppliers.edit',$agency) }}">Completa email agenzia</a>
                            @elseif(!in_array($policy->status,['active','renewed']) || $policy->expires_on->lt(today())) Non inviabile
                            @else {{ $policy->ad_horas ? 'Ad horas: gestire subito' : 'Da inviare' }} @endif
                        </td></tr>
                    @empty<tr><td colspan="9">Nessuna polizza inserita nel periodo con i filtri scelti.</td></tr>@endforelse
                </tbody></table>
            </div>{{ $policies->links() }}
        </x-panel>
    </section>
</x-app-layout>
