@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="Paginazione" data-page-name="{{ $paginator->getPageName() }}">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span class="page-item disabled">
                <span class="page-link">←</span>
            </span>
        @else
            <a class="page-item" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Pagina precedente">
                <span class="page-link">←</span>
            </a>
        @endif

        {{-- Pages --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-item disabled"><span class="page-link">{{ $element }}</span></span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></span>
                    @else
                        <a class="page-item" href="{{ $url }}"><span class="page-link">{{ $page }}</span></a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a class="page-item" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Pagina successiva">
                <span class="page-link">→</span>
            </a>
        @else
            <span class="page-item disabled">
                <span class="page-link">→</span>
            </span>
        @endif
    </nav>
@endif
