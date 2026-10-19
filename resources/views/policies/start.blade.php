<x-app-layout title="Nuova polizza">
    <x-page-header>
        <x-slot:title>Nuova polizza</x-slot:title>
        <x-slot:actions><a class="btn btn-g" href="{{ $client->exists ? route('clients.show', $client) : route('policies.index') }}">{{ $client->exists ? $client->name : 'Polizze' }}</a></x-slot:actions>
    </x-page-header>
    <div class="policy-start">
        <x-panel title="Parti dal preventivo approvato" padded>
            <p class="policy-start-intro">Seleziona la proposta accettata dal cliente: il modulo della polizza riprenderà compagnia, prodotto, date, frazionamento e premio.</p>
            @if($approvedQuotes->isNotEmpty())
                <form method="GET" action="{{ route('policies.create') }}">
                    @if($client->exists)<input type="hidden" name="client_id" value="{{ $client->id }}">@endif
                    <x-form-group label="Proposta approvata" name="quote_id" required>
                        <select id="field-quote-id" name="quote_id" class="form-sel" required>
                            <option value="">Seleziona il cliente e la proposta</option>
                            @foreach($approvedQuotes as $proposal)
                                <option value="{{ $proposal->id }}">{{ $proposal->quoteCase->client->name }} / {{ $proposal->company->name }} / {{ $proposal->reference }} / {{ \App\Support\Money::format($proposal->premium_cents) }}</option>
                            @endforeach
                        </select>
                    </x-form-group>
                    <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.create', $client->exists ? ['client_id'=>$client->id] : []) }}">Richiedi un altro preventivo</a><button type="submit" class="btn btn-p">Compila la polizza</button></div>
                </form>
            @else
                <div class="policy-start-empty">
                    <p>Non ci sono proposte approvate e ancora valide{{ $client->exists ? ' per questo cliente' : '' }} da trasformare in polizza.</p>
                    <p>Puoi richiedere un preventivo oppure completare l’approvazione di una proposta ricevuta, allegando la prova del cliente.</p>
                    <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.index') }}">Consulta i preventivi</a><a class="btn btn-p" href="{{ route('quotes.create', $client->exists ? ['client_id'=>$client->id] : []) }}">Richiedi preventivo</a></div>
                </div>
            @endif
        </x-panel>
        @can('system.admin')
            <x-panel title="Hai una polizza già stipulata?" padded>
                <p>Registra il contratto esistente indicando il motivo dell’inserimento. Questa funzione è riservata all’admin.</p>
                <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('policies.create', array_filter(['entry_mode'=>'existing', 'client_id'=>$client->id])) }}">Registra polizza già stipulata</a></div>
            </x-panel>
        @endcan
    </div>
</x-app-layout>
