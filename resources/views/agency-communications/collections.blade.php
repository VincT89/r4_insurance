<x-app-layout title="Comunicazioni rate e incassi"><x-page-header><x-slot:title>Comunicazioni rate e incassi</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('collections.index') }}">Registro incassi</a></x-slot:actions></x-page-header>
@include('agency-communications.navigation')
<form class="client-search-form filter-grid" method="GET" x-data="{scope: @js($filters['scope'])}"><div class="form-row">
<x-form-group label="Mostra" name="scope"><select class="form-sel" name="scope" x-model="scope">@foreach(['period'=>'Incassi del periodo','pending'=>'Da completare · tutte le date','attention'=>'Esiti da controllare · tutte le date'] as $value=>$label)<option value="{{ $value }}" @selected($filters['scope']===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
@foreach(['from'=>'Incassati dal','to'=>'Al'] as $key=>$label)<div x-show="scope === 'period'" x-cloak><x-form-group :label="$label" :name="$key"><input class="form-in" type="date" name="{{ $key }}" value="{{ $filters[$key] }}" max="{{ today()->toDateString() }}" :disabled="scope !== 'period'" @disabled($filters['scope']!=='period')></x-form-group></div>@endforeach
@foreach(['agency'=>['Agenzia',$agencies],'company'=>['Compagnia',$companies]] as $key=>[$label,$items])<x-form-group :label="$label" :name="$key"><select name="{{ $key }}" class="form-sel"><option value="">Tutte</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected(($filters[$key]??'')==$item->id)>{{ $item->name }}</option>@endforeach</select></x-form-group>@endforeach
<x-form-group label="Cliente o polizza" name="search"><input class="form-in" name="search" maxlength="255" value="{{ $filters['search']??'' }}"></x-form-group>
<x-form-group label="Collegamento agenzia" name="unassigned"><select name="unassigned" class="form-sel"><option value="">Tutti</option><option value="1" @selected($filters['unassigned']??false)>Solo agenzia da assegnare</option></select></x-form-group>
</div><div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('agency-communications.collections') }}">Oggi</a><a class="btn btn-g" href="{{ route('agency-communications.collections',['from'=>today()->subDays(14)->toDateString(),'to'=>today()->toDateString()]) }}">Ultimi 15 giorni</a><a class="btn btn-g" href="{{ route('agency-communications.collections',['from'=>today()->startOfMonth()->toDateString(),'to'=>today()->toDateString()]) }}">Questo mese</a></div></form>
<div class="communication-followup-actions u-mb-md"><a class="btn btn-g" href="{{ route('agency-communications.collections',['scope'=>'pending']) }}">Tutti gli incassi da completare</a><a class="btn btn-g" href="{{ route('agency-communications.collections',['scope'=>'attention']) }}">Controlla esiti incerti</a></div>
<p class="u-mb-md">{{ $range }}. Seleziona i movimenti, controlla l’email cumulativa e conferma l’invio per ciascuna agenzia. Gli invii incerti restano da controllare e non possono essere ripetuti automaticamente.</p>
@unless($mailReady)<p class="u-mb-md">Per inviare, completa la casella in <a href="{{ route('email-settings.edit') }}">Impostazioni email</a>.</p>@endunless
<section data-communication-selection data-selection-user="{{ auth()->id() }}" data-selection-scope="{{ $selectionScope }}" data-selection-field="collections">
@include('agency-communications.selection-actions', ['reviewRoute'=>'agency-communications.collections-review','reviewAgencies'=>$agencies])
<x-panel :title="$filters['scope']==='period' ? 'Incassi del periodo' : 'Incassi da completare'">
<p class="communication-pagination-summary" data-table-page-summary>{{ number_format($payments->total(),0,',','.') }} incassi · Pagina {{ $payments->currentPage() }} di {{ $payments->lastPage() }}</p>
<div class="client-table-scroll" role="region" aria-label="Incassi da comunicare alle agenzie" tabindex="0"><table class="t-table"><thead><tr><th>Seleziona</th><th>Data</th><th>Agenzia</th><th>Contraente</th><th>Polizza / titolo</th><th>Compagnia</th><th>Frazionamento</th><th>Importo</th><th>Metodo pagamento</th><th>Destinatario pagamento</th><th>Comunicazione</th></tr></thead><tbody>
@forelse($payments as $payment)
@php($agency=$payment->title->policy->supplierAgency)
@php($delivery=$deliveries->get($payment->id))
<tr><td>@if($agency)<input type="checkbox" value="{{ $payment->id }}" data-communication-item data-agency="{{ $agency->id }}" aria-label="Includi incasso {{ $payment->id }}" @disabled($delivery || $agency->status !== 'active' || !filter_var($agency->email,FILTER_VALIDATE_EMAIL))>@endif</td><td>{{ $payment->received_on->format('d/m/Y') }}</td><td>@if($agency)<a href="{{ route('suppliers.show',$agency) }}">{{ $agency->name }}</a>@else Agenzia da assegnare @endif</td><td>{{ $payment->title->policy->client->name }}</td><td><a href="{{ route('titles.show',$payment->insurance_title_id) }}">{{ $payment->title->policy->number }} / #{{ $payment->insurance_title_id }}</a></td><td>{{ $payment->title->policy->company }}</td><td>{{ $payment->title->payment_frequency }}</td><td>{{ \App\Support\Money::format($payment->amount_cents) }}</td><td>{{ $payment->payment_method }}</td><td>{{ \App\Models\InsuranceCollection::RECIPIENTS[$payment->recipient] }}</td><td>
@if($delivery)<a href="{{ route('agency-communications.show',$delivery->id) }}">{{ \App\Models\AgencyCommunication::STATUSES[$delivery->status] }}</a>
@elseif(!$agency) Assegna l’agenzia
@elseif($agency->status !== 'active') Agenzia non attiva
@elseif(!filter_var($agency->email,FILTER_VALIDATE_EMAIL))<a href="{{ route('suppliers.edit',$agency) }}">Completa email agenzia</a>
@else {{ $payment->title->policy->ad_horas ? 'Ad horas' : 'Da comunicare' }} @endif
</td></tr>
@empty<tr><td colspan="11">Nessun incasso da comunicare per questi filtri.</td></tr>@endforelse
</tbody></table></div>{{ $payments->links() }}
</x-panel></section>
</x-app-layout>
