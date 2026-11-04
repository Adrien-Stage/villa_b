@props(['items' => null])

@php
    $trail = \App\Support\Breadcrumbs::generate($items);
@endphp

@if(\App\Support\Breadcrumbs::shouldShow() && count($trail) > 1)
<nav aria-label="Fil d'Ariane" class="breadcrumbs-widget mb-4 flex items-center text-xs text-primary/60 print:hidden overflow-x-auto whitespace-nowrap py-1">
    <ol class="inline-flex items-center gap-1.5 list-none p-0 m-0" itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach($trail as $index => $crumb)
            <li class="inline-flex items-center gap-1.5" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                @if($index > 0)
                    <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-primary/30 flex-shrink-0" aria-hidden="true"></i>
                @endif

                @if(!empty($crumb['url']))
                    <a href="{{ $crumb['url'] }}"
                       itemprop="item"
                       class="inline-flex items-center gap-1 text-primary/65 hover:text-primary transition-colors py-0.5 px-1.5 rounded hover:bg-black/5"
                       title="{{ $crumb['label'] }}">
                        @if(!empty($crumb['icon']))
                            <i data-lucide="{{ $crumb['icon'] }}" class="w-3.5 h-3.5 text-primary/50 flex-shrink-0" aria-hidden="true"></i>
                        @endif
                        <span itemprop="name" class="font-medium">{{ $crumb['label'] }}</span>
                    </a>
                @else
                    <span class="inline-flex items-center gap-1 text-primary font-semibold py-0.5 px-2 bg-primary/5 rounded border border-primary/10 max-w-[280px] sm:max-w-md truncate shadow-2xs"
                          aria-current="page"
                          title="{{ $crumb['label'] }}">
                        @if(!empty($crumb['icon']))
                            <i data-lucide="{{ $crumb['icon'] }}" class="w-3.5 h-3.5 text-primary flex-shrink-0" aria-hidden="true"></i>
                        @endif
                        <span itemprop="name" class="truncate">{{ $crumb['label'] }}</span>
                    </span>
                @endif
                <meta itemprop="position" content="{{ $index + 1 }}" />
            </li>
        @endforeach
    </ol>
</nav>
@endif
