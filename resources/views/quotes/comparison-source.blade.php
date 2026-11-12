<aside class="quote-comparison-source" data-comparison-source @if(!$source) hidden @endif aria-label="Fonte del dato nel PDF">
    @if($source)
        <div class="quote-source-heading"><div><h2>{{ $source['evidence']['document_name'] }}</h2><p>{{ $source['cell']['label'] }} · Pagina {{ $source['evidence']['page'] }}</p></div><a href="{{ route('quotes.compare',['quotes'=>$ids]) }}" data-close-quote-source>Chiudi fonte</a></div>
        <blockquote>{{ $source['evidence']['quote'] }}</blockquote>
        @if($source['evidence']['coverage_name'])<p class="u-text-meta">Nome nel documento: {{ $source['evidence']['coverage_name'] }}</p>@endif
        <p class="u-text-meta">Il PDF riporta: {{ $comparison->display($source['evidence']['value'],$source['cell']['type']) }}. Nel confronto: {{ $source['cell']['display'] }}.</p>
        <x-pdf-reader :source="$source['evidence']['download_url']" :page="$source['evidence']['page']" />
        <a href="{{ $source['evidence']['url'] }}" target="_blank" rel="noopener">Apri PDF in una nuova scheda</a>
        @if($source['editable'] && auth()->user()->can('updateRegistry',$case->client))@include('quotes.comparison-edit',['offer'=>$source['offer'],'cell'=>$source['cell']])@endif
    @endif
</aside>
