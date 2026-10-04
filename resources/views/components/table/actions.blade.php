{{--
    Actions d'une ligne. En boutons quand le tableau a la place, dans un menu
    ⋮ sinon (prop « inline » du tableau). Le contenu — des <x-table.action> —
    est rendu dans les deux présentations ; une seule est visible.
--}}
@aware(['inline' => '5xl'])
@props(['label' => 'Actions'])

@php
    // Classes écrites en entier : Tailwind ne voit que ce qui est écrit.
    $presentations = [
        'always' => ['flex', 'hidden'],
        'never' => ['hidden', 'inline-block'],
        'md' => ['hidden @md:flex', 'inline-block @md:hidden'],
        'lg' => ['hidden @lg:flex', 'inline-block @lg:hidden'],
        'xl' => ['hidden @xl:flex', 'inline-block @xl:hidden'],
        '2xl' => ['hidden @2xl:flex', 'inline-block @2xl:hidden'],
        '3xl' => ['hidden @3xl:flex', 'inline-block @3xl:hidden'],
        '4xl' => ['hidden @4xl:flex', 'inline-block @4xl:hidden'],
        '5xl' => ['hidden @5xl:flex', 'inline-block @5xl:hidden'],
        '6xl' => ['hidden @6xl:flex', 'inline-block @6xl:hidden'],
        '7xl' => ['hidden @7xl:flex', 'inline-block @7xl:hidden'],
    ];
    [$enLigne, $enMenu] = $presentations[$inline] ?? $presentations['5xl'];
@endphp

<td {{ $attributes->class(['w-px whitespace-nowrap px-4 py-2 text-right align-middle']) }}>
    <div class="dt-inline {{ $enLigne }} items-center justify-end gap-2">
        {{ $slot }}
    </div>

    <div class="{{ $enMenu }}" x-data="menuLigne" @keydown.escape.window="ouvert && fermer()">
        <button type="button" x-ref="bouton" @click="basculer()"
            aria-haspopup="menu" :aria-expanded="ouvert.toString()" aria-label="{{ $label }}" title="{{ $label }}"
            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-primary/60 transition-colors hover:bg-accent/30 hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
            <i data-lucide="ellipsis-vertical" class="h-4 w-4" aria-hidden="true"></i>
        </button>
        {{-- Le menu sort du tableau : ni le défilement horizontal ni la carte ne le coupent. --}}
        <template x-teleport="body">
            <div x-ref="menu" x-show="ouvert" x-cloak role="menu" aria-label="{{ $label }}"
                :style="{ top: haut + 'px', left: gauche + 'px' }"
                @click.outside="if (! $refs.bouton.contains($event.target)) fermer(false)"
                @keydown.arrow-down.prevent="deplacer(1)" @keydown.arrow-up.prevent="deplacer(-1)"
                @keydown.home.prevent="deplacer(0, true)" @keydown.end.prevent="deplacer(-1, true)"
                @keydown.tab="fermer(false)" @click="fermerApresChoix($event)"
                class="dt-menu fixed z-50 min-w-48 overflow-hidden rounded-xl border border-secondary/20 bg-white py-1 text-left shadow-lg">
                {{ $slot }}
            </div>
        </template>
    </div>
</td>
