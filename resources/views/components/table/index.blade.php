{{--
    Tableau de liste, le même dans toute l'application.

    <x-table :rows="$commandes" empty="Aucune commande." empty-icon="receipt">
        <x-slot:head>
            <x-table.col>Table</x-table.col>
            <x-table.col align="right">Montant</x-table.col>
            <x-table.col hide="lg">Serveur</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($commandes as $commande)
            <x-table.row>
                <x-table.cell>…</x-table.cell>
                <x-table.actions>
                    <x-table.action :href="route('…')" icon="eye">Ouvrir</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>

    - rows : la page (paginateur) ou la collection affichée. Elle décide de
      l'état vide et, pour un paginateur, de la pagination en pied.
    - inline : largeur du tableau à partir de laquelle les actions d'une ligne
      s'affichent en boutons ; en dessous, elles passent dans le menu ⋮.
      « always », « never », ou une taille de conteneur (md … 7xl).
    - Emplacements facultatifs : toolbar (au-dessus), foot (pied du tableau,
      totaux).
--}}
@props([
    'rows' => null,
    'empty' => 'Aucun élément.',
    'emptyIcon' => 'inbox',
    'inline' => '5xl',
    'caption' => null,
])

@php
    $estVide = $rows !== null && count($rows) === 0;
    $paginateur = $rows instanceof \Illuminate\Contracts\Pagination\Paginator ? $rows : null;
@endphp

<div {{ $attributes->class(['@container overflow-hidden rounded-xl border border-secondary/15 bg-white shadow-sm']) }}>
    @isset($toolbar)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-secondary/10 px-4 py-3">
            {{ $toolbar }}
        </div>
    @endisset

    @if($estVide)
        <div class="flex flex-col items-center justify-center gap-2 px-4 py-14 text-center text-primary/45">
            <i data-lucide="{{ $emptyIcon }}" class="h-9 w-9 opacity-50" aria-hidden="true"></i>
            <p class="text-sm">{{ $empty }}</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                @if($caption)
                    <caption class="sr-only">{{ $caption }}</caption>
                @endif
                <thead class="border-b border-secondary/15 bg-accent/20">
                    <tr>{{ $head }}</tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    {{ $slot }}
                </tbody>
                @isset($foot)
                    <tfoot class="border-t-2 border-secondary/20 bg-accent/10 font-semibold text-primary">
                        {{ $foot }}
                    </tfoot>
                @endisset
            </table>
        </div>
    @endif

    @if($paginateur && $paginateur->hasPages())
        <div class="border-t border-secondary/10">
            {{ $paginateur->onEachSide(1)->links('components.table.pagination') }}
        </div>
    @endif
</div>
