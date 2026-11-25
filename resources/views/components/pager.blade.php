@props(['paginator'])
@if($paginator->hasPages())
<nav class="pager">
    @if($paginator->onFirstPage())<span class="btn btn-small" aria-disabled="true">&laquo;</span>
    @else<a class="btn btn-small" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo;</a>@endif
    <span class="muted small">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a class="btn btn-small" href="{{ $paginator->nextPageUrl() }}" rel="next">&raquo;</a>
    @else<span class="btn btn-small" aria-disabled="true">&raquo;</span>@endif
</nav>
@endif
