<x-app-layout title="Storico invii">
    <x-page-header><x-slot:title>Storico invii</x-slot:title><x-slot:actions><a class="btn btn-p" href="{{ route('agency-communications.index') }}">Prepara email</a></x-slot:actions></x-page-header>
    <div class="communication-workspace">
    @include('agency-communications.navigation')
    <form method="GET" class="client-search-form communication-filters"><div class="communication-filter-primary">
        <x-form-group name="month" label="Mese"><input id="field-month" class="form-in" name="month" type="month" value="{{ $filters['month'] }}" required></x-form-group>
        <x-form-group name="agency" label="Agenzia"><select id="field-agency" class="form-sel" name="agency"><option value="">Tutte</option>@foreach($agencies as $agency)<option value="{{ $agency->id }}" @selected((string)($filters['agency'] ?? '')===(string)$agency->id)>{{ $agency->name }}</option>@endforeach</select></x-form-group>
        <x-form-group name="state" label="Esito"><select id="field-state" class="form-sel" name="state"><option value="">Tutti</option>@foreach(\App\Models\AgencyCommunication::STATUSES as $value=>$label)<option value="{{ $value }}" @selected(($filters['state'] ?? '')===$value)>{{ $label }}</option>@endforeach</select></x-form-group>
    </div><div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('agency-communications.history') }}">Azzera filtri</a></div></form>
    <x-panel><div class="client-table-scroll" role="region" aria-label="Storico comunicazioni alle agenzie" tabindex="0"><table class="t-table" data-list-details><thead><tr><th>Preparata il</th><th>Agenzia</th><th>Contenuto</th><th>Esito</th><th data-list-detail>Destinatario</th><th data-list-detail>Operatore</th><th>Dettaglio</th></tr></thead><tbody>
        @forelse($messages as $message)
            @php
                $payload=$message->payload;
                $policyCount=count($payload['policy_ids']??$payload['policies']??[]);
                $changeCount=count($payload['changes']??[]);
                $collectionCount=count($payload['collections']??[]);
            @endphp
            <tr data-list-label="{{ $payload['recipient']['name'] }} / {{ $message->created_at->format('d/m/Y H:i') }}">
                <td>{{ $message->created_at->format('d/m/Y H:i') }}</td><td>{{ $payload['recipient']['name'] }}</td>
                <td>@if(($payload['kind']??null)==='collections'){{ $collectionCount }} {{ $collectionCount===1 ? 'incasso' : 'incassi' }}@else @if($policyCount){{ $policyCount }} {{ $policyCount===1 ? 'polizza' : 'polizze' }}@endif @if($policyCount && $changeCount) / @endif @if($changeCount){{ $changeCount }} {{ $changeCount===1 ? 'appendice' : 'appendici' }}@endif @endif</td>
                <td>{{ $message->status_label }}</td><td>{{ $payload['recipient']['address'] }}</td><td>{{ $message->user?->name ?: 'Utente non più presente' }}</td><td><a href="{{ route('agency-communications.show',$message) }}">Apri riepilogo</a></td>
            </tr>
        @empty<tr><td colspan="7">Nessuna comunicazione nel periodo selezionato.</td></tr>@endforelse
    </tbody></table></div>{{ $messages->links() }}</x-panel>
    </div>
</x-app-layout>
