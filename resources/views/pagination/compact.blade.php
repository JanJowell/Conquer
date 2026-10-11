@if ($paginator->hasPages())
    <nav aria-label="Pagination" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm font-medium text-slate-600">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
            @if ($paginator->onFirstPage())
                <span aria-label="Previous page" aria-disabled="true" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-300">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 4 7 12l8 8" />
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 4 7 12l8 8" />
                    </svg>
                </a>
            @endif

            <div class="hidden flex-wrap items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="inline-flex h-10 min-w-10 items-center justify-center px-2 text-sm text-slate-500" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span aria-current="page" aria-label="Page {{ $page }}" class="inline-flex h-10 min-w-10 items-center justify-center rounded-full bg-indigo-600 px-3 text-sm font-semibold text-white">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" aria-label="Go to page {{ $page }}" class="inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            <span class="inline-flex h-10 min-w-24 items-center justify-center rounded-full bg-slate-50 px-4 text-sm font-semibold sm:hidden" aria-current="page">
                <span class="text-indigo-700">{{ $paginator->currentPage() }}</span>
                <span class="ml-1 text-slate-700">/ {{ $paginator->lastPage() }}</span>
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-700 transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 4 8 8-8 8" />
                    </svg>
                </a>
            @else
                <span aria-label="Next page" aria-disabled="true" class="inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-300">
                    <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m9 4 8 8-8 8" />
                    </svg>
                </span>
            @endif
        </div>
    </nav>
@endif
