{{--
    Menu d'actions ⋮ : un bouton qui ouvre la liste des actions d'un objet.
    Le contenu — des <x-table.action> — y prend l'aspect d'entrées de menu.
    Sert aux lignes de tableau (x-table.actions) comme aux cartes et aux
    fiches.

    - label : nom accessible du bouton et du menu (« Actions pour … »)
--}}
@props(['label' => 'Actions'])

<div {{ $attributes }} x-data="menuLigne" @keydown.escape.window="ouvert && fermer()">
    <button type="button" x-ref="bouton" @click="basculer()"
        aria-haspopup="menu" :aria-expanded="ouvert.toString()" aria-label="{{ $label }}" title="{{ $label }}"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-primary/60 transition-colors hover:bg-accent/30 hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary">
        <i data-lucide="ellipsis-vertical" class="h-4 w-4" aria-hidden="true"></i>
    </button>
    {{-- Le menu sort de son conteneur : ni un défilement ni une carte ne le coupent. --}}
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
