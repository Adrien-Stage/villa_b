{{-- Onglets internes du module comptabilité. --}}
@php
    $tabs = [
        'accounting.index'            => ['Tableau de bord', 'layout-dashboard'],
        'accounting.journal'          => ['Recettes & dépenses', 'book-open'],
        'accounting.expenses'         => ['Dépenses', 'receipt'],
        'accounting.receivables'      => ['Créances', 'hand-coins'],
        'accounting.cash'             => ['Caisse', 'calculator'],
        'accounting.income_statement' => ['Compte de résultat', 'scale'],
    ];
    $month = $period['month'] ?? request('month');
@endphp
<nav class="flex flex-wrap gap-1.5 mb-6 border-b border-secondary/15 pb-3">
    @foreach($tabs as $route => [$label, $icon])
        @php $active = request()->routeIs($route); @endphp
        <a href="{{ route($route, $month ? ['month' => $month] : []) }}"
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                {{ $active ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary hover:bg-accent/20' }}">
            <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5"></i>
            {{ $label }}
        </a>
    @endforeach

    {{-- Bascule vers la comptabilité générale : un autre référentiel, pas un
         onglet de plus — d'où la séparation visuelle. Le module est activable
         par établissement : sans lui, la caisse se suffit à elle-même et le
         lien n'a pas lieu d'être. --}}
    @module('ledger')
        <span class="hidden sm:block w-px bg-secondary/20 mx-1 self-stretch"></span>
        <a href="{{ route('accounting.ledger.index') }}"
            class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium border border-secondary/30 text-primary/70 hover:text-primary hover:bg-accent/20 transition-colors">
            <i data-lucide="book-marked" class="w-3.5 h-3.5"></i>
            Comptabilité générale
        </a>
    @endmodule
</nav>
