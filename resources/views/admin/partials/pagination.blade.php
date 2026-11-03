@if ($paginator->hasPages())
  <nav class="pagination" aria-label="Pagination">
    @if ($paginator->onFirstPage())
      <span class="btn btn-secondaire mini" aria-disabled="true">{!! __('pagination.previous') !!}</span>
    @else
      <a class="btn btn-secondaire mini" href="{{ $paginator->previousPageUrl() }}" rel="prev">{!! __('pagination.previous') !!}</a>
    @endif
    <span class="note">Page {{ $paginator->currentPage() }} sur {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())
      <a class="btn btn-secondaire mini" href="{{ $paginator->nextPageUrl() }}" rel="next">{!! __('pagination.next') !!}</a>
    @else
      <span class="btn btn-secondaire mini" aria-disabled="true">{!! __('pagination.next') !!}</span>
    @endif
  </nav>
@endif
