<x-app-layout title="Confronto offerte">
    @php($canManage=auth()->user()->can('updateRegistry',$case->client))
    <x-page-header><x-slot:title>Confronto offerte</x-slot:title><x-slot:meta>{{ $case->client->name }} · {{ $case->vehicle_plate ?: $case->branch->name }} · {{ $case->subject }}</x-slot:meta><x-slot:actions><a class="btn btn-g" href="{{ route('quotes.offers',['case'=>$case->id]) }}">Riepilogo offerte</a><a class="btn btn-g" href="{{ route('quotes.show',$case) }}">Apri pratica</a>@if($canManage && $case->status==='open')<a class="btn btn-g" href="{{ route('quotes.offers.upload',['case'=>$case->id]) }}">Carica offerte</a>@endif</x-slot:actions></x-page-header>
    <section data-quote-comparison>
        <div class="quote-comparison-toolbar"><label><input type="checkbox" data-quote-differences> Mostra solo differenze</label><p @class(['quote-warning'=>$periodIncomplete||$periodDifferent])>{{ $periodIncomplete ? 'Periodo non completo: verifica le date.' : ($periodDifferent ? 'Periodi diversi: confronta i premi sulla durata indicata.' : 'Stessa pratica e stesso periodo di copertura') }}</p></div>
        @if(count($ids)>2)<div class="quote-mobile-selectors"><label>Prima offerta<select class="form-sel" data-mobile-offer="0">@foreach($columns as $index=>$column)<option value="{{ $index }}">{{ $column['offer']->company->name }} / {{ $column['offer']->agency?->name ?: 'Diretta' }}</option>@endforeach</select></label><label>Seconda offerta<select class="form-sel" data-mobile-offer="1">@foreach($columns as $index=>$column)<option value="{{ $index }}" @selected($index===1)>{{ $column['offer']->company->name }} / {{ $column['offer']->agency?->name ?: 'Diretta' }}</option>@endforeach</select></label></div>@endif
        <div @class(['quote-comparison-workspace','has-source'=>(bool)$source]) data-comparison-workspace>
            <div class="quote-comparison-table-wrap"><table class="quote-comparison-table" data-offer-count="{{ count($ids) }}"><caption class="sr-only">Garanzie e condizioni delle offerte selezionate</caption><thead><tr><th scope="col">Dato da confrontare</th>
                @foreach($columns as $index=>$column)@php($offer=$column['offer'])<th scope="col" data-offer-column="{{ $index }}"><strong>{{ $offer->company->name }}</strong><small>{{ $offer->agency?->name ?: 'Rapporto diretto' }}</small><small>{{ $offer->reference ?: 'Riferimento da indicare' }}</small>
                    @foreach($offer->documents->where('document_category','quote') as $document)<a class="quote-small-link" href="{{ $document->openUrl() }}" target="_blank" rel="noopener">{{ $document->original_name }}</a>@endforeach
                    @if($column['editable'] && $canManage && $aiReady)
                        <form class="quote-ai-read" data-quote-ai-read hidden>
                            @csrf
                            <label><input type="checkbox" name="ai_processing" required> Consento l’invio dei PDF a OpenAI per la lettura.</label>
                            @foreach($offer->documents->where('document_category','quote') as $document)
                                <input type="hidden" data-ai-document value="{{ $document->id }}" data-url="{{ route('quotes.comparison.read',$offer) }}">
                            @endforeach
                            <button type="submit" class="btn btn-g btn-sm">Leggi PDF con AI</button>
                            <p data-ai-status role="status" aria-live="polite"></p>
                        </form>
                        <noscript><p>La lettura AI richiede JavaScript. Puoi consultare i PDF e correggere i dati manualmente.</p></noscript>
                    @endif
                    @foreach($column['warnings'] as $warning)<p class="quote-warning quote-offer-warning">{{ $warning }}</p>@endforeach
                    @if($offer->valid_until?->lt(today()))<p class="quote-warning">Offerta scaduta</p>@endif
                </th>@endforeach
            </tr></thead>
            @foreach($rows as $group=>$groupRows)<tbody data-comparison-group><tr class="quote-comparison-group"><th colspan="{{ count($ids)+1 }}">{{ $group }}</th></tr>
                @foreach($groupRows as $row)<tr data-comparison-row data-different="{{ $row['different']?'true':'false' }}"><th scope="row">{{ $row['label'] }}</th>
                    @foreach($row['cells'] as $index=>$cell)@php($offer=$columns[$index]['offer'])<td data-offer-column="{{ $index }}" data-offer-label="{{ $offer->company->name }} / {{ $offer->agency?->name ?: 'Diretta' }}">
                        @if($canManage && $columns[$index]['editable'])<details class="quote-cell-editor"><summary aria-label="Correggi {{ $cell['label'] }} per {{ $offer->company->name }}">{{ $cell['display'] }}</summary>@include('quotes.comparison-edit')</details>@else<span>{{ $cell['display'] }}</span>@endif
                        @if($cell['uncertain'])<small class="quote-warning">Da chiarire</small>@endif
                        @if($cell['corrected'])<small>Confermato dall’operatore</small>@elseif($cell['manual'])<small>Dato registrato nella proposta</small>@endif
                        @foreach($cell['sources'] as $evidence)<a class="quote-source-link" data-quote-source href="{{ route('quotes.compare',['quotes'=>$ids,'source_quote'=>$offer->id,'field'=>$cell['key'],'document'=>$evidence['document_id'],'page'=>$evidence['page']]) }}" aria-label="Fonte: {{ $evidence['document_name'] }}, pagina {{ $evidence['page'] }}, {{ $cell['label'] }}">p. {{ $evidence['page'] }}@if(count($cell['sources'])>1) · {{ $evidence['document_name'] }}@endif</a>@endforeach
                        @if(!$cell['sources'] && !$cell['manual'])<small>Nei documenti disponibili</small>@endif
                    </td>@endforeach
                </tr>@endforeach
            </tbody>@endforeach
            <tbody><tr class="quote-verification-row"><th scope="row">Controllo e preferenza</th>
                @foreach($columns as $index=>$column)@php($offer=$column['offer'])<td data-offer-column="{{ $index }}" data-offer-label="{{ $offer->company->name }} / {{ $offer->agency?->name ?: 'Diretta' }}"><p>{{ $offer->comparisonReview?->verified_at ? 'Dati verificati' : 'Dati da verificare' }}</p>
                    @if($offer->comparisonReview?->verified_at)<small>{{ $offer->comparisonReview->verifier?->name ?: 'Operatore non più presente' }} · {{ $offer->comparisonReview->verified_at->format('d/m/Y H:i') }}</small>@endif
                    @if($offer->comparisonReview?->verification_note)<p class="quote-verification-note">{{ $offer->comparisonReview->verification_note }}</p>@endif
                    @if($canManage && $column['editable'])
                        <form method="POST" action="{{ route('quotes.comparison.update',[$offer,'verify']) }}" class="quote-verify-form">@csrf<input type="hidden" name="revision" value="{{ $offer->comparisonReview?->revision??0 }}">@foreach($ids as $id)<input type="hidden" name="comparison_ids[]" value="{{ $id }}">@endforeach<label>Note di verifica<textarea class="form-ta" name="verification_note" rows="2" maxlength="2000">{{ $offer->comparisonReview?->verification_note }}</textarea></label><label><input type="checkbox" name="confirmed" value="1" required> Ho verificato i dati e il rischio nei PDF.</label><button class="btn btn-g btn-sm">Conferma dati</button></form>
                        <details class="quote-add-coverage"><summary>Aggiungi garanzia</summary><form method="POST" action="{{ route('quotes.comparison.update',[$offer,'coverage']) }}">@csrf<input type="hidden" name="revision" value="{{ $offer->comparisonReview?->revision??0 }}">@foreach($ids as $id)<input type="hidden" name="comparison_ids[]" value="{{ $id }}">@endforeach<label>Garanzia<select class="form-sel" name="code">@foreach(\App\Services\AiQuoteSchema::COVERAGES as $code=>$name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</select></label><label>Nome nel documento<input class="form-in" name="name" maxlength="150" required></label><button class="btn btn-g btn-sm">Aggiungi</button></form></details>
                    @endif
                    @if($canManage && $case->status==='open' && !in_array($offer->status,['rejected','approved','converted'],true))
                        <form method="POST" action="{{ route('quotes.comparison.update',[$offer,'prefer']) }}" class="quote-prefer-form">
                            @csrf
                            @foreach($ids as $id)
                                <input type="hidden" name="comparison_ids[]" value="{{ $id }}">
                            @endforeach
                            <input type="hidden" name="selected" value="{{ (int)$case->preferred_quote_id===$offer->id ? 0 : 1 }}">
                            <button @class(['btn','btn-sm','btn-p'=>(int)$case->preferred_quote_id===$offer->id,'btn-g'=>(int)$case->preferred_quote_id!==$offer->id])>{{ (int)$case->preferred_quote_id===$offer->id ? 'Rimuovi preferenza' : 'Segna come preferita' }}</button>
                        </form>
                    @endif
                    @if((int)$case->preferred_quote_id===$offer->id)<p class="quote-preferred">Preferita dall’operatore</p>@endif
                </td>@endforeach
            </tr></tbody></table></div>
            @include('quotes.comparison-source')
        </div>
        <p data-quote-comparison-status role="status" aria-live="polite"></p>
        <p class="quote-comparison-explanation">Le righe evidenziate contengono differenze. “Non indicata” non significa “esclusa”. I premi si riferiscono al periodo riportato: non vengono trasformati in un prezzo annuale.</p>
        <p class="u-text-meta">La preferenza dell’operatore è interna. L’approvazione del cliente e la stipula restano nel flusso della pratica.</p>
        @can('system.admin')<p class="u-mt-md"><a href="{{ route('ai.index') }}">Consumi delle letture in AI e consumi</a></p>@endcan
    </section>
</x-app-layout>
