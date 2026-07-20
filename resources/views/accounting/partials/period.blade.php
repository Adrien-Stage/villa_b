{{-- Sélecteur de période réutilisable : navigation mois par mois (le mois courant
     par défaut). $period vient du contrôleur. --}}
@php
    $month = $period['month'] ?? null;
    $prev = $month ? \Carbon\Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m') : null;
    $next = $month ? \Carbon\Carbon::createFromFormat('Y-m', $month)->addMonth()->format('Y-m') : null;
@endphp
<div class="flex items-center gap-2">
    @if($prev)
        <a href="{{ url()->current() }}?month={{ $prev }}"
            class="h-9 w-9 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20"
            aria-label="Mois précédent">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
    @endif

    <form method="GET" action="{{ url()->current() }}" class="relative">
        <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"
            class="h-9 pl-3 pr-2 text-sm border border-secondary/25 rounded-lg bg-white text-primary outline-none focus:border-secondary">
    </form>

    @if($next)
        <a href="{{ url()->current() }}?month={{ $next }}"
            class="h-9 w-9 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20"
            aria-label="Mois suivant">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
    @endif

    <span class="ml-1 text-sm font-medium text-primary/70 capitalize">{{ $period['label'] }}</span>
</div>
