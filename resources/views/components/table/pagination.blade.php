{{-- Pagination des tableaux de liste (x-table). --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
        <p class="text-xs text-primary/60">
            @if (method_exists($paginator, 'total'))
                <span class="font-semibold text-primary">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
                sur <span class="font-semibold text-primary">{{ $paginator->total() }}</span>
            @else
                Page <span class="font-semibold text-primary">{{ $paginator->currentPage() }}</span>
            @endif
        </p>

        @php
            $case = 'inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
        @endphp
        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="{{ $case }} cursor-not-allowed text-primary/25" aria-disabled="true" aria-label="Page précédente">
                        <i data-lucide="chevron-left" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $case }} text-primary/70 hover:bg-accent/30 hover:text-primary" aria-label="Page précédente">
                        <i data-lucide="chevron-left" class="h-4 w-4" aria-hidden="true"></i>
                    </a>
                @endif
            </li>

            @isset($elements)
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="{{ $case }} text-primary/40" aria-hidden="true">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            <li>
                                @if ($page == $paginator->currentPage())
                                    <span class="{{ $case }} bg-primary text-white" aria-current="page">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}" class="{{ $case }} text-primary/70 hover:bg-accent/30 hover:text-primary" aria-label="Page {{ $page }}">{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            @endisset

            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $case }} text-primary/70 hover:bg-accent/30 hover:text-primary" aria-label="Page suivante">
                        <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                    </a>
                @else
                    <span class="{{ $case }} cursor-not-allowed text-primary/25" aria-disabled="true" aria-label="Page suivante">
                        <i data-lucide="chevron-right" class="h-4 w-4" aria-hidden="true"></i>
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
