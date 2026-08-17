{{-- Onglets de la comptabilité générale. --}}
@php
    $ledgerTabs = [
        'accounting.ledger.index'    => ['Tableau de bord', 'layout-dashboard'],
        'accounting.ledger.balance'  => ['Balance', 'scale'],
        'accounting.ledger.general'  => ['Grand livre', 'book-open'],
        'accounting.ledger.journals' => ['Journaux', 'notebook-pen'],
        'accounting.ledger.accounts' => ['Plan de comptes', 'list-tree'],
        'accounting.ledger.periods'  => ['Périodes', 'lock'],
    ];
@endphp
<nav class="flex flex-wrap gap-1.5 mb-6 border-b border-secondary/15 pb-3">
    <a href="{{ route('accounting.index') }}"
        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium text-primary/50 hover:text-primary hover:bg-accent/20 transition-colors"
        title="Revenir à la comptabilité de caisse">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
        <span class="hidden sm:inline">Caisse</span>
    </a>
    <span class="hidden sm:block w-px bg-secondary/20 mx-1 self-stretch"></span>

    @foreach($ledgerTabs as $route => [$label, $icon])
        @php $active = request()->routeIs($route); @endphp
        <a href="{{ route($route) }}"
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                {{ $active ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary hover:bg-accent/20' }}">
            <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i>
            {{ $label }}
        </a>
    @endforeach
</nav>
