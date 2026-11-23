<div class="bg-white/95 border-b border-secondary/20 px-4 lg:px-8 py-2.5 flex items-center gap-2
    justify-between flex-shrink-0 shadow-xs print:hidden">
    <nav aria-label="Rubriques de la tuile {{ $groupe['title'] }}" class="flex min-w-0 items-center gap-1.5 sm:gap-2 overflow-x-auto py-0.5">
        <div aria-hidden="true" class="hidden sm:flex shrink-0 items-center gap-2 pr-3 border-r border-secondary/20 mr-1
            text-primary/70 font-semibold text-xs uppercase tracking-wider">
            <i data-lucide="{{ $groupe['icon'] }}" class="w-4 h-4 text-primary"></i>
            <span>{{ $groupe['title'] }}</span>
        </div>
        @foreach($groupe['links'] as $lien)
            <a href="{{ $lien['url'] }}"
               @if($lien['active']) aria-current="page" @endif
               @if($lien['externe']) target="_blank" rel="noopener" @endif
               class="inline-flex min-h-9 shrink-0 items-center gap-2 px-3.5 rounded-xl text-xs font-semibold
                   focus-visible:outline-2 focus-visible:outline-primary focus-visible:outline-offset-[-2px]
                   {{ $lien['active'] ? 'bg-primary text-white' : 'text-primary/75 hover:text-primary hover:bg-accent/40 bg-secondary/10' }}">
                <i data-lucide="{{ $lien['icon'] }}" class="w-3.5 h-3.5 shrink-0" aria-hidden="true"></i>
                {{ $lien['label'] }}
            </a>
        @endforeach
    </nav>
    <a href="{{ route('dashboard') }}" title="Toutes les applications" aria-label="Toutes les applications"
       class="shrink-0 inline-flex min-h-9 items-center gap-1 px-2 text-xs font-semibold text-primary hover:bg-accent/40 rounded-xl">
        <i data-lucide="layout-grid" class="w-4 h-4" aria-hidden="true"></i>
        <span class="hidden md:inline">Applications</span>
    </a>
</div>

@if(collect($groupe['links'])->contains(fn ($lien) => $lien['route'] === 'bookings.index' && $lien['active']))
    <nav aria-label="Sous-rubriques des réservations" class="bg-white border-b border-secondary/20 px-4 lg:px-8 print:hidden">
        @include('partials.reservation-links', ['modeSidebar' => false])
    </nav>
@endif
