<x-app-layout title="Preventivi">
    <x-page-header><x-slot:title>Preventivi</x-slot:title><x-slot:meta>{{ $case ? $case->subject.' · '.$case->client->name : 'Riepilogo delle offerte ricevute e delle risposte attese.' }}</x-slot:meta><x-slot:actions>@can('create',\App\Models\Client::class)<a class="btn btn-p" href="{{ route('quotes.offers.upload', $case ? ['case'=>$case->id] : []) }}">Carica offerte</a>@endcan<a class="btn btn-g" href="{{ route('quotes.index') }}">Pratiche preventivo</a></x-slot:actions></x-page-header>
    @if(($filters['client']??null) && !$case)<p>Elenco limitato al cliente selezionato. <a href="{{ route('quotes.offers') }}">Mostra tutti i preventivi</a></p>@endif
    <form method="GET" action="{{ route('quotes.offers') }}" class="client-search-form" aria-label="Filtra offerte" data-list-filters>
        @if($case)<input type="hidden" name="case" value="{{ $case->id }}">@endif
        @if($filters['client']??null)<input type="hidden" name="client" value="{{ $filters['client'] }}">@endif
        <x-form-group name="search" label="Cliente, veicolo, pratica o compagnia"><input id="field-search" class="form-in" name="search" value="{{ $filters['search']??'' }}" maxlength="255"></x-form-group>
        <x-form-group name="verification" label="Verifica dei dati"><select id="field-verification" class="form-sel" name="verification"><option value="">Tutti</option>@foreach(['pending'=>'Da verificare','verified'=>'Verificati','missing'=>'PDF mancante'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['verification']??'')===$key)>{{ $label }}</option>@endforeach</select></x-form-group>
        <x-form-group name="company" label="Compagnia"><select id="field-company" class="form-sel" name="company"><option value="">Tutte</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company']??'')==$company->id)>{{ $company->name }}</option>@endforeach</select></x-form-group>
        <div class="search-form-actions"><button class="btn btn-p">Applica filtri</button><a class="btn btn-g" href="{{ route('quotes.offers', \Illuminate\Support\Arr::only($filters,['client','case'])) }}">Azzera filtri</a></div>
        <details class="search-more offer-filter-options"><summary class="btn btn-g">Altri filtri</summary><div class="search-more-fields"></div></details>
    </form>
    <form method="GET" action="{{ route('quotes.compare') }}" data-quote-offer-selection>
        <div class="quote-list-toolbar">
            <p data-quote-selection-status aria-live="polite">Seleziona da due a sei offerte della stessa pratica.</p>
            <button class="btn btn-p" type="submit" data-quote-compare>Confronta offerte</button>
            <p data-quote-selection-error role="alert" class="u-alert-error" hidden></p>
        </div>
        <table class="quote-offers-table"><caption class="sr-only">Riepilogo dei preventivi</caption><thead><tr><th scope="col" aria-label="Selezione"></th><th scope="col">Cliente e pratica</th><th scope="col">Compagnia e agenzia</th><th scope="col">Premio lordo</th><th scope="col">Copertura e validità</th><th scope="col">Verifica</th></tr></thead><tbody>
        @forelse($offers as $offer)
            @php($cells=$comparison->cells($offer))
            <tr>
                <td class="quote-select-cell"><input type="checkbox" name="quotes[]" value="{{ $offer->id }}" data-case="{{ $offer->insurance_quote_case_id }}" aria-label="Seleziona {{ $offer->company->name }} tramite {{ $offer->agency?->name ?: 'rapporto diretto' }}, {{ $offer->quoteCase->subject }}" @disabled(!$offer->offer_documents_count)></td>
                <td class="quote-client-cell"><a href="{{ route('clients.show',$offer->quoteCase->client) }}">{{ $offer->quoteCase->client->name }}</a><small>{{ $offer->quoteCase->vehicle_plate ?: $offer->quoteCase->branch->name }}</small><a class="quote-small-link" href="{{ route('quotes.show',$offer->insurance_quote_case_id) }}">{{ $offer->quoteCase->subject }}</a></td>
                <td data-label="Compagnia e agenzia"><strong>{{ $offer->company->name }}</strong><small>{{ $offer->agency?->name ?: 'Rapporto diretto' }}</small></td>
                <td data-label="Premio lordo"><strong class="quote-money">{{ $cells['premium']['display'] }}</strong><small>Totale del periodo</small>@if($cells['premium']['uncertain'])<small class="quote-warning">Importo da chiarire</small>@endif</td>
                <td data-label="Copertura e validità">{{ $cells['starts_on']['display'] }}<small>al {{ $cells['expires_on']['display'] }}</small><small>Valida fino al {{ $cells['valid_until']['display'] }}</small>@if($offer->valid_until?->lt(today()))<small class="quote-warning">Offerta scaduta</small>@endif</td>
                <td data-label="Verifica"><strong>{{ !$offer->offer_documents_count ? 'PDF mancante' : ($offer->comparisonReview?->verified_at ? 'Verificata' : 'Da verificare') }}</strong><small>{{ \App\Models\InsuranceQuote::STATUSES[$offer->status] }}</small>@if((int)$offer->quoteCase->preferred_quote_id===$offer->id)<small>Preferita dall’operatore</small>@endif
                    @if($offer->offer_documents_count)<a class="quote-small-link" href="{{ route('quotes.compare',['quotes'=>[$offer->id]]) }}">Dati e PDF</a>@else<a class="quote-small-link" href="{{ route('quotes.offers.upload',['case'=>$offer->insurance_quote_case_id]) }}">Carica PDF</a>@endif</td>
            </tr>
        @empty<tr><td colspan="6">Nessun preventivo corrisponde ai filtri. Le offerte restano collegate alle pratiche preventivo.</td></tr>@endforelse
        </tbody></table>
    </form>
    <div class="quote-list-footer"><span>{{ $offers->total() }} {{ $offers->total() === 1 ? 'preventivo' : 'preventivi' }} · Pagina {{ $offers->currentPage() }} di {{ $offers->lastPage() }}</span>@can('system.admin')<a href="{{ route('ai.index') }}">AI e consumi</a>@endcan</div>
    {{ $offers->links() }}
</x-app-layout>
