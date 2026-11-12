<x-app-layout title="Carica offerte">
    <x-page-header><x-slot:title>Carica offerte</x-slot:title><x-slot:actions><a class="btn btn-g" href="{{ route('quotes.offers') }}">Riepilogo preventivi</a></x-slot:actions></x-page-header>
    @unless($case)
        <form method="GET" class="client-search-form"><x-form-group name="search" label="Cerca cliente o pratica"><input id="field-search" class="form-in" name="search" value="{{ request('search') }}" maxlength="255"></x-form-group><button class="btn btn-g">Cerca</button></form>
        <form method="GET" class="quote-upload-case"><x-form-group name="case" label="Pratica preventivo" required><select id="field-case" name="case" class="form-sel" required><option value="">Seleziona la pratica</option>@foreach($cases as $item)<option value="{{ $item->id }}">{{ $item->client->name }} · {{ $item->subject }}{{ $item->vehicle_plate ? ' · '.$item->vehicle_plate : '' }}</option>@endforeach</select></x-form-group><button class="btn btn-p">Continua</button></form>
        @if($cases->isEmpty())<p>Nessuna pratica aperta corrisponde alla ricerca. <a href="{{ route('quotes.create') }}">Nuovo preventivo</a></p>@endif
    @else
        <p class="u-mb-md">{{ $case->client->name }} · {{ $case->subject }}{{ $case->vehicle_plate ? ' · '.$case->vehicle_plate : '' }}</p>
        <p class="u-text-meta u-mb-md">Carica ogni PDF sotto la compagnia e l’agenzia corrette. Puoi selezionare insieme preventivo e allegati della stessa offerta. Massimo 10 MB per PDF, 50 MB complessivi.</p>
        <form method="POST" enctype="multipart/form-data" action="{{ route('quotes.offers.store',$case) }}" data-quote-upload>@csrf
            @forelse($case->quotes->whereIn('status',['requested','received']) as $offer)
                <fieldset class="quote-upload-offer"><legend>{{ $offer->company->name }} · {{ $offer->agency?->name ?: 'Rapporto diretto' }}</legend><label for="offer-files-{{ $offer->id }}">PDF dell’offerta</label><input id="offer-files-{{ $offer->id }}" class="form-in" type="file" name="files[{{ $offer->id }}][]" accept="application/pdf,.pdf" multiple>@if($offer->documents->where('document_category','quote')->count())<p class="u-text-meta">I documenti già presenti saranno conservati. I nuovi PDF richiederanno una nuova verifica dei dati.</p>@endif</fieldset>
            @empty<p>Nessuna proposta modificabile. Registra il destinatario nella pratica prima di associare i PDF.</p>@endforelse
            <div class="insurance-form-actions"><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Apri pratica e destinatari</a>@if($case->quotes->whereIn('status',['requested','received'])->isNotEmpty())<button class="btn btn-p">Carica e confronta</button>@endif</div>
        </form>
    @endunless
</x-app-layout>
