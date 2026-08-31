{{--
    Navigation entre fiches de réservation.

    Permet d'enchaîner les dossiers sans repasser par la liste : la réception
    traite rarement une réservation isolée, elle parcourt une journée d'arrivées.
    Les filtres de la liste voyagent dans l'URL ($context), sinon « suivant »
    sortirait du sous-ensemble que l'utilisateur consultait.
--}}
@php
    $ctx      = $navigation['context'];
    $prev     = $navigation['prev'];
    $next     = $navigation['next'];
    $siblings = $navigation['siblings'];

    $lien = fn($b) => route('bookings.show', array_merge([$b], $ctx));
@endphp

{{-- Maj + flèches, et non Alt + flèches : Alt + ← / → sont les raccourcis
     Précédent / Suivant du navigateur, que l'on ne détourne pas. --}}
<div class="flex items-center gap-1 rounded-lg border border-secondary/20 bg-white p-1"
     x-data="bookingSwitcher()"
     @keydown.window="raccourci($event)">

    {{-- Précédent --}}
    @if($prev)
        <a href="{{ $lien($prev) }}" x-ref="prev"
           title="Réservation précédente — {{ $prev->booking_number }} (Maj + ←)"
           class="flex items-center justify-center w-8 h-8 rounded-md text-primary/60 hover:bg-secondary/10 hover:text-primary transition-colors">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
        </a>
    @else
        <span class="flex items-center justify-center w-8 h-8 rounded-md text-primary/20 cursor-not-allowed"
              title="Première réservation de la liste">
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

        {{-- Pas de x-transition ici : la transition par défaut laissait le
             panneau en opacité 0 après un clic rapide, panneau ouvert mais
             invisible. Le reste de l'application ouvre ses listes de la même
             manière, sans animation. --}}
        {{-- Ancré à gauche sur petit écran : aligné à droite, le panneau sortait
             de l'écran par la gauche, le bouton étant lui-même près du bord. --}}
        <div x-show="open" style="display:none;"
             class="absolute left-0 sm:left-auto sm:right-0 z-30 mt-1 w-72 sm:w-80 rounded-lg border border-secondary/20 bg-white shadow-lg">
            <div class="p-2 border-b border-secondary/10">
                <input type="text" x-model="search" x-ref="search" autocomplete="off"
                       placeholder="Filtrer (n° ou client)…"
                       class="w-full px-2.5 py-1.5 text-xs border border-gray-200 rounded-md focus:ring-2 focus:ring-primary focus:border-transparent">
            </div>
            <ul class="max-h-72 overflow-auto py-1">
                @foreach($siblings as $s)
                    <li data-libelle="{{ Str::lower($s->booking_number . ' ' . optional($s->customer)->full_name) }}"
                        x-show="matches($el)">
                        <a href="{{ $lien($s) }}"
                           class="flex items-center justify-between gap-3 px-3 py-2 text-xs hover:bg-secondary/10 transition-colors
                                  {{ $s->id === $booking->id ? 'bg-secondary/15' : '' }}">
                            <span class="min-w-0">
                                <span class="block font-mono font-medium text-primary">{{ $s->booking_number }}</span>
                                <span class="block truncate text-primary/50">
                                    {{ optional($s->customer)->full_name ?: 'Client inconnu' }}
                                </span>
                            </span>
                            <span class="shrink-0 text-primary/40 font-mono">
                                {{ $s->check_in?->format('d/m') }}
                            </span>
                        </a>
                    </li>
                @endforeach
                <li x-show="!hasResults()" style="display:none;"
                    class="px-3 py-3 text-xs text-primary/40 text-center">
                    Aucune réservation dans cette sélection.
                </li>
            </ul>
            <div class="px-3 py-2 border-t border-secondary/10">
                <a href="{{ route('bookings.index', $ctx) }}"
                   class="text-xs text-primary/60 hover:text-primary transition-colors">
                    Voir la liste complète →
                </a>
            </div>
        </div>
    </div>

    {{-- Suivant --}}
    @if($next)
        <a href="{{ $lien($next) }}" x-ref="next"
           title="Réservation suivante — {{ $next->booking_number }} (Maj + →)"
           class="flex items-center justify-center w-8 h-8 rounded-md text-primary/60 hover:bg-secondary/10 hover:text-primary transition-colors">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </a>
    @else
        <span class="flex items-center justify-center w-8 h-8 rounded-md text-primary/20 cursor-not-allowed"
              title="Dernière réservation de la liste">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
        </span>
    @endif
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingSwitcher', () => ({
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
