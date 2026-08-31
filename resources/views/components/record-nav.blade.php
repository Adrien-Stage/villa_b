{{--
    Navigation entre fiches d'une même liste.

    Permet d'enchaîner les dossiers sans repasser par la liste : la réception
    traite rarement une fiche isolée, elle parcourt une journée d'arrivées.
    Les filtres de la liste voyagent dans l'URL ($navigation['context']), sinon
    « suivant » sortirait du sous-ensemble que l'utilisateur consultait.

    Volontairement générique — rien ici ne connaît les réservations : les routes
    et les libellés sont des paramètres, pour que les groupes, les clients ou
    tout autre écran de détail puissent réutiliser le même parcours.

    Usage :
        <x-record-nav :navigation="$navigation" :current="$booking" />

    Le tableau $navigation est celui que renvoie le contrôleur :
    prev, next, position, total, siblings, context.
--}}
@props([
    'navigation',
    'current',
    'showRoute' => 'bookings.show',
    'listRoute' => 'bookings.index',
    'label'     => 'Réservation',
    'numberKey' => 'booking_number',
    'dateKey'   => 'check_in',
])

@php
    $ctx      = $navigation['context'] ?? [];
    $prev     = $navigation['prev'] ?? null;
    $next     = $navigation['next'] ?? null;
    $siblings = $navigation['siblings'] ?? collect();

    $lien = fn($m) => route($showRoute, array_merge([$m], $ctx));

    // Le nom affiché sous le numéro : le client quand la fiche en porte un.
    $nomDe = function ($m) {
        $c = $m->customer ?? null;
        return $c?->full_name ?: null;
    };
@endphp

{{-- Maj + flèches, et non Alt + flèches : Alt + ← / → sont les raccourcis
     Précédent / Suivant du navigateur, que l'on ne détourne pas. --}}
<div class="flex items-center gap-1 rounded-lg border border-secondary/20 bg-white p-1"
     x-data="recordNav()"
     @keydown.window="raccourci($event)">

    {{-- Précédent --}}
    @if($prev)
        <a href="{{ $lien($prev) }}" x-ref="prev"
           title="{{ $label }} précédente — {{ $prev->{$numberKey} }} (Maj + ←)"
           class="flex items-center justify-center w-8 h-8 rounded-md text-primary/60 hover:bg-secondary/10 hover:text-primary transition-colors">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
    @else
        <span class="flex items-center justify-center w-8 h-8 rounded-md text-primary/20 cursor-not-allowed"
              title="Première fiche de la liste">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </span>
    @endif

    {{-- Sélecteur rapide.
         La fermeture au clic extérieur est portée par ce conteneur, pas par le
         bouton : sur le bouton, cliquer dans le champ de recherche compte comme
         un clic « ailleurs » et referme le panneau avant toute frappe. --}}
    <div class="relative" @click.away="open = false" @keydown.escape="open = false">
        <button type="button" @click="open = !open"
                class="flex items-center gap-2 px-2.5 h-8 rounded-md text-xs font-medium text-primary/70 hover:bg-secondary/10 hover:text-primary transition-colors">
            <span class="font-mono">{{ $navigation['position'] }}</span>
            <span class="text-primary/30">/</span>
            <span class="font-mono">{{ $navigation['total'] }}</span>
            <i data-lucide="chevrons-up-down" class="w-3.5 h-3.5"></i>
        </button>

        {{-- z-50 comme les autres menus déroulants du gabarit : la barre
             latérale est en z-40, un panneau en dessous passe derrière elle.
             Pas de x-transition : la transition par défaut laissait le panneau
             ouvert mais en opacité 0 après un clic rapide.
             Largeur bornée au viewport : à 320 px fixes, le panneau sortait de
             l'écran sur mobile, le bouton étant collé au bord droit. --}}
        <div x-show="open" style="display:none;"
             class="absolute right-0 z-50 mt-1 w-[min(20rem,calc(100vw-5rem))] rounded-lg border border-secondary/20 bg-white shadow-lg">
            <div class="p-2 border-b border-secondary/10">
                <input type="text" x-model="search" x-ref="search" autocomplete="off"
                       placeholder="Filtrer (n° ou client)…"
                       class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <ul class="max-h-72 overflow-auto py-1">
                @foreach($siblings as $s)
                    <li data-libelle="{{ Str::lower($s->{$numberKey} . ' ' . $nomDe($s)) }}"
                        x-show="matches($el)">
                        <a href="{{ $lien($s) }}"
                           class="flex items-center justify-between gap-3 px-3 py-2 text-xs hover:bg-secondary/10 transition-colors
                                  {{ $s->id === $current->id ? 'bg-secondary/15' : '' }}">
                            <span class="min-w-0">
                                <span class="block font-mono font-medium text-primary">{{ $s->{$numberKey} }}</span>
                                @if($nomDe($s))
                                    <span class="block truncate text-primary/50">{{ $nomDe($s) }}</span>
                                @endif
                            </span>
                            @if($dateKey && $s->{$dateKey})
                                <span class="shrink-0 text-primary/40 font-mono">
                                    {{ $s->{$dateKey}->format('d/m') }}
                                </span>
                            @endif
                        </a>
                    </li>
                @endforeach
                <li x-show="!hasResults()" style="display:none;"
                    class="px-3 py-3 text-xs text-primary/40 text-center">
                    Aucun résultat dans cette sélection.
                </li>
            </ul>
            <div class="px-3 py-2 border-t border-secondary/10">
                <a href="{{ route($listRoute, $ctx) }}"
                   class="text-xs text-primary/60 hover:text-primary transition-colors">
                    Voir la liste complète →
                </a>
            </div>
        </div>
    </div>

    {{-- Suivant --}}
    @if($next)
        <a href="{{ $lien($next) }}" x-ref="next"
           title="{{ $label }} suivante — {{ $next->{$numberKey} }} (Maj + →)"
           class="flex items-center justify-center w-8 h-8 rounded-md text-primary/60 hover:bg-secondary/10 hover:text-primary transition-colors">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
    @else
        <span class="flex items-center justify-center w-8 h-8 rounded-md text-primary/20 cursor-not-allowed"
              title="Dernière fiche de la liste">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </span>
    @endif
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('recordNav', () => ({
        open: false,
        search: '',

        init() {
            // Les icônes du panneau sont injectées après coup : sans ce
            // rafraîchissement, la liste s'ouvre avec des emplacements vides.
            this.$watch('open', (ouvert) => {
                if (ouvert) {
                    this.$nextTick(() => {
                        this.$refs.search?.focus();
                        if (window.refreshLucideIcons) window.refreshLucideIcons();
                    });
                }
            });
        },

        matches(el) {
            if (this.search === '') return true;
            return (el.dataset.libelle || '').includes(this.search.toLowerCase());
        },

        hasResults() {
            if (this.search === '') return true;
            return [...this.$el.querySelectorAll('li[data-libelle]')]
                .some(li => this.matches(li));
        },

        /**
         * Maj + ← / → passe d'une fiche à l'autre.
         *
         * L'écoute est posée sur window : elle doit donc s'effacer dès que
         * l'utilisateur saisit du texte, sinon Maj + flèche — qui sélectionne
         * des caractères — ferait quitter la page en pleine frappe.
         */
        raccourci(e) {
            if (!e.shiftKey || e.altKey || e.ctrlKey || e.metaKey) return;

            const cible = e.target;
            if (cible && (cible.isContentEditable
                || (cible.matches && cible.matches('input, textarea, select')))) return;

            if (e.key === 'ArrowLeft')  { e.preventDefault(); this.goPrev(); }
            if (e.key === 'ArrowRight') { e.preventDefault(); this.goNext(); }
        },

        goPrev() { this.$refs.prev?.click(); },
        goNext() { this.$refs.next?.click(); },
    }));
});
</script>
@endonce
