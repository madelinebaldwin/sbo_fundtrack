@if ($paginator->hasPages())
    <nav class="fundtrack-pagination" role="navigation" aria-label="Pagination Navigation">
        @if (! $paginator->onFirstPage())
            <div class="fundtrack-pagination__previous">
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            </div>
        @endif

        <div class="fundtrack-pagination__pages" aria-label="Pages">
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="fundtrack-pagination__ellipsis" aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="fundtrack-pagination__current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        <div class="fundtrack-pagination__next">
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
