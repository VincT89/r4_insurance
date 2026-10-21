<x-app-layout title="Scadenze">
    <x-page-header><x-slot:title>Scadenze</x-slot:title><x-slot:actions>@can('system.admin')<a class="btn btn-g" href="{{ route('agency-communications.collections',['scope'=>'pending']) }}">Incassi da comunicare</a>@endcan<a class="btn btn-g" href="{{ route('claims.index',['reminder'=>'all']) }}">Attività sinistri</a></x-slot:actions></x-page-header>
    <div class="deadline-notice">
        <p>Avvisi interni da 15 giorni prima della scadenza. Le rate restano da incassare per il solo residuo: al saldo l’avviso si chiude, in caso di storno si riapre.</p>
        <p class="u-text-meta">La scadenza del contratto e quella del pagamento sono distinte: incassare una rata non rinnova automaticamente la polizza.</p>
        @can('system.admin')
            @if(!$lastCheck || \Illuminate\Support\Carbon::parse($lastCheck)->lt(now()->subMinutes(15)))
                <p class="u-text-meta">Controllo periodico in background {{ $lastCheck ? 'non aggiornato: ultimo controllo '.$lastCheck->format('d/m/Y H:i') : 'da attivare sul server' }}. Gli avvisi vengono aggiornati anche durante l’uso del gestionale.</p>
            @else
                <p class="u-text-meta">Ultimo controllo notifiche: {{ $lastCheck->format('d/m/Y H:i') }}.</p>
            @endif
        @endcan
    </div>
    <form method="GET" action="{{ route('deadlines.index') }}" class="client-search-form" x-data="{period: @js((string)$period)}">
        <x-form-group label="Cerca cliente o elemento" name="search"><input class="form-in" type="search" id="field-search" name="search" maxlength="255" value="{{ request('search') }}"></x-form-group>
        <x-form-group label="Tipo" name="kind"><select class="form-sel" id="field-kind" name="kind"><option value="">Tutti</option>@foreach(['title'=>'Rate e quietanze','policy'=>'Polizze','document'=>'Documenti','request'=>'Documenti da ricevere'] as $value=>$label)<option value="{{ $value }}" @selected(request('kind')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Periodo" name="period"><select class="form-sel" id="field-period" name="period" x-model="period">@foreach(['all'=>'Tutte le scadenze','overdue'=>'Già scadute','7'=>'Prossimi 7 giorni','15'=>'Prossimi 15 giorni','30'=>'Prossimi 30 giorni','45'=>'Prossimi 45 giorni','60'=>'Prossimi 60 giorni','custom'=>'Intervallo personalizzato'] as $value=>$label)<option value="{{ $value }}" @selected((string)$period===(string)$value)>{{ $label }}</option>@endforeach</select></x-form-group>
        @foreach(['from'=>'Dal','to'=>'Al'] as $key=>$label)<div class="form-g" data-form-group data-field-id="field-{{ $key }}" x-show="period==='custom'" x-cloak><label class="form-lbl" data-form-label for="field-{{ $key }}">{{ $label }}</label><input class="form-in" id="field-{{ $key }}" type="date" name="{{ $key }}" value="{{ request($key) }}" :disabled="period!=='custom'">@error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>@endforeach
        <x-form-group label="Agenzia" name="agency"><select class="form-sel" name="agency"><option value="">Tutte</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)request('agency')===(string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
        <div class="search-form-actions"><button class="btn btn-p" type="submit">Mostra scadenze</button><a class="btn btn-g" href="{{ route('deadlines.index') }}">Azzera filtri</a></div>
        <details class="search-more" @if(collect(request()->only(['category','company','responsible','payment_frequency']))->filter()->isNotEmpty()) open @endif><summary class="btn btn-g">Altri filtri</summary><div class="search-more-fields">
        <x-form-group label="Ramo" name="category"><select class="form-sel" id="field-category" name="category"><option value="">Tutti</option>@foreach($categories as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $category }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Compagnia" name="company"><select class="form-sel" id="field-company" name="company"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company }}" @selected(request('company')===$company)>{{ $company }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Responsabile cliente" name="responsible"><select class="form-sel" id="field-responsible" name="responsible"><option value="">Tutti</option>@foreach($responsibles as $person)<option value="{{ $person->id }}" @selected((string)request('responsible')===(string)$person->id)>{{ $person->name }}</option>@endforeach</select></x-form-group>
        <x-form-group label="Frazionamento" name="payment_frequency"><select class="form-sel" name="payment_frequency"><option value="">Tutti</option>@foreach(\App\Models\InsurancePolicy::PAYMENT_FREQUENCIES as $frequency)<option value="{{ $frequency }}" @selected(request('payment_frequency')===$frequency)>{{ $frequency }}</option>@endforeach</select></x-form-group>
        </div></details>
    </form>
    <div class="deadline-quick-actions u-mb-md"><a class="btn btn-g" href="{{ route('deadlines.index',['period'=>'15']) }}">Prossimi 15 giorni</a><a class="btn btn-g" href="{{ route('deadlines.index',['period'=>'overdue']) }}">Già scadute</a><a class="btn btn-g" href="{{ route('deadlines.index',['period'=>'all','kind'=>'title']) }}">Tutte le rate da incassare</a></div>
    <x-panel>
        <div class="client-table-scroll" role="region" aria-label="Scadenze accessibili" tabindex="0"><table class="t-table deadline-table"><thead><tr><th>Data</th><th>Cliente</th><th>Tipo</th><th>Elemento</th><th>Ramo</th><th>Compagnia</th><th>Agenzia</th><th>Frazionamento</th><th>Residuo da incassare</th><th>Responsabile cliente</th><th>Situazione</th></tr></thead><tbody>
            @forelse($deadlines as $item)
                @php($date = \Illuminate\Support\Carbon::parse($item->due_date))
                @php($policyReview = $item->kind === 'policy' && $item->review_status === 'review')
                @php($url = match($item->kind) { 'title'=>route('titles.show',$item->source_id), 'policy'=>route('clients.policies.show', [$item->client_id,$item->source_id]), 'document'=>route('attachments.download',$item->source_id), default=>route('clients.show',[$item->client_id,'tab'=>'documents']).'#document-request-'.$item->source_id })
                <tr>
                    <td><x-status-text :alert="!$policyReview && ($date->lt(today()) || ($item->kind === 'policy' && $item->review_status === 'expired'))">{{ $date->format('d/m/Y') }}</x-status-text></td>
                    <td><a href="{{ route('clients.show', [$item->client_id,'tab'=>'deadlines']) }}">{{ $item->client_name }}</a></td>
                    <td>{{ ['title'=>'Rata da incassare','policy'=>'Scadenza contratto','document'=>'Documento','request'=>'Documento da ricevere'][$item->kind] }}</td>
                    <td><a href="{{ $url }}" @class(['deadline-policy-reference'=>in_array($item->kind,['title','policy'])])>{{ $item->title }}</a>@if($item->kind==='document')<div class="u-text-meta">{{ $item->review_status==='validated' ? 'Validato' : 'Da validare' }}</div>@endif</td>
                    <td>{{ $item->category ?: 'Non indicato' }}</td><td>{{ $item->company ?: 'Non indicata' }}</td><td>{{ $item->agency_name ?: 'Non collegata' }}</td>
                    <td>{{ $item->payment_frequency ?: 'Non indicato' }}</td><td>@if($item->balance_cents !== null)<x-status-text alert>{{ \App\Support\Money::format($item->balance_cents) }}</x-status-text>@else Non previsto @endif</td>
                    <td>{{ $item->responsible_name ?: 'Non assegnato' }}</td><td class="{{ !$policyReview && ($date->lt(today()) || ($item->kind === 'policy' && $item->review_status === 'expired')) ? 'client-overdue' : '' }}">{{ $policyReview ? 'Stato polizza da verificare' : (($date->lt(today()) || ($item->kind === 'policy' && $item->review_status === 'expired')) ? 'Scaduta' : ($date->isToday() ? 'Scade oggi' : 'In scadenza')) }}</td>
                </tr>
            @empty<tr><td colspan="11">Nessuna scadenza per questi filtri.</td></tr>@endforelse
        </tbody></table></div>
        {{ $deadlines->links() }}
    </x-panel>
</x-app-layout>
