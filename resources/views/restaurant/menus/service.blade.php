@extends('layouts.hotel')

@section('title', 'Prise de commande')

@section('content')
{{--
    Écran de salle : le serveur compose la commande au contact du client, sur
    tablette ou sur téléphone. Tout tient donc au pouce — vignettes larges,
    quantités réglables sans clavier, panier accessible en permanence.
--}}
<div x-data="priseDeCommande()" class="pb-24 lg:pb-0">

    {{-- En-tête --}}
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="font-heading text-xl sm:text-2xl font-semibold text-primary flex items-center gap-2">
                <i data-lucide="utensils" class="w-5 h-5 text-secondary"></i>
                Prise de commande
            </h1>
            <p class="text-sm text-primary/50 mt-0.5">Composez la commande, puis transmettez-la en cuisine.</p>
        </div>

        <a href="{{ route('restaurant.orders.index') }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-secondary/25 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="receipt" class="w-4 h-4 text-secondary"></i>
            Mes commandes
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] xl:grid-cols-[1fr_400px] gap-4 lg:gap-6 items-start">

        {{-- ── Carte ─────────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-secondary/15 shadow-sm p-4 sm:p-5">

            <label for="recherche-plat" class="block text-xs font-semibold text-primary/60 mb-2">Rechercher un plat</label>
            <div class="relative">
                <input id="recherche-plat" type="search" x-model="recherche"
                       placeholder="Ex. poulet DG"
                       autocomplete="off"
                       class="w-full px-4 py-3 text-sm rounded-xl border border-secondary/25 bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary transition-colors">
                <button type="button" x-show="recherche" @click="recherche = ''"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-primary/30 hover:text-primary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            {{-- Filtres par catégorie : défilement horizontal plutôt que retour
                 à la ligne, pour ne pas repousser la carte hors de l'écran. --}}
            <div class="mt-4 -mx-1 px-1 flex items-center gap-2 overflow-x-auto pb-1">
                <button type="button" @click="categorie = 'toutes'"
                        :class="categorie === 'toutes' ? 'bg-accent/40 border-secondary text-primary' : 'bg-white border-secondary/25 text-primary/60'"
                        class="flex-shrink-0 px-4 py-1.5 rounded-full border text-xs font-semibold transition-colors">
                    Tous
                </button>
                @foreach($categories as $categorie)
                    <button type="button" @click="categorie = '{{ $categorie->id }}'"
                            :class="categorie === '{{ $categorie->id }}' ? 'bg-accent/40 border-secondary text-primary' : 'bg-white border-secondary/25 text-primary/60'"
                            class="flex-shrink-0 px-4 py-1.5 rounded-full border text-xs font-semibold transition-colors">
                        {{ $categorie->name }}
                    </button>
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t border-secondary/15">
                @if($items->isEmpty())
                    <div class="flex flex-col items-center justify-center py-16 text-primary/35">
                        <i data-lucide="utensils-crossed" class="w-10 h-10 mb-3 opacity-40"></i>
                        <p class="text-sm">Aucun plat actif sur la carte</p>
                    </div>
                @else
                    {{-- Deux colonnes dès le téléphone : une vignette pleine largeur
                         obligerait à faire défiler toute la carte pour trois plats.
                         Le retour à deux colonnes en « lg » n'est pas une erreur :
                         c'est là que le panier s'installe à droite et ampute la
                         carte de 360 px. --}}
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 sm:gap-4">
                        @foreach($items as $item)
                            <div x-show="estVisible({{ $item->id }})"
                                 data-plat="{{ $item->id }}"
                                 data-nom="{{ mb_strtolower($item->name) }}"
                                 data-categorie="{{ $item->restaurant_menu_category_id ?? 'sans' }}"
                                 class="flex flex-col rounded-2xl border border-secondary/15 bg-white overflow-hidden hover:border-secondary/40 transition-colors">

                                <div class="relative aspect-[4/3] bg-gradient-to-br from-accent/70 to-secondary/60">
                                    @if($item->image_path)
                                        <img src="{{ asset('storage/' . $item->image_path) }}"
                                             alt="{{ $item->name }}" loading="lazy"
                                             class="absolute inset-0 h-full w-full object-cover">
                                    @else
                                        <div class="absolute inset-0 flex items-center justify-center text-white/70">
                                            <i data-lucide="utensils" class="w-8 h-8"></i>
                                        </div>
                                    @endif

                                    {{-- Ce qui est déjà au panier se voit sur la vignette :
                                         au coup d'œil, le serveur sait ce qu'il a saisi. --}}
                                    <span x-show="quantite({{ $item->id }}) > 0"
                                          class="absolute top-2 right-2 h-6 min-w-6 px-1.5 inline-flex items-center justify-center rounded-full bg-primary text-white text-[11px] font-bold shadow"
                                          x-text="quantite({{ $item->id }})"></span>
                                </div>

                                <div class="flex flex-1 flex-col p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-secondary truncate">
                                        {{ $item->category?->name ?? 'Autres' }}
                                    </p>
                                    <p class="mt-0.5 text-sm font-semibold text-primary leading-snug line-clamp-2">{{ $item->name }}</p>
                                    <p class="mt-1 text-[11px] text-primary/45 truncate">{{ $item->mealServicesLabel() }}</p>

                                    <div class="mt-auto pt-3 flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-primary">
                                            {{ number_format($item->price / 100, 0, ',', ' ') }} <span class="text-[11px] font-normal text-primary/50">FCFA</span>
                                        </span>
                                        {{-- Cible tactile de 40 px : on l'actionne debout, à la table. --}}
                                        <button type="button"
                                                @click="ajouter({{ $item->id }}, @js($item->name), {{ $item->price }})"
                                                aria-label="Ajouter {{ $item->name }}"
                                                class="h-10 w-10 inline-flex items-center justify-center rounded-xl bg-primary text-white hover:opacity-90 active:scale-95 transition">
                                            <i data-lucide="plus" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p x-show="aucunResultat" class="py-12 text-center text-sm text-primary/40">
                        Aucun plat ne correspond à cette recherche.
                    </p>
                @endif
            </div>
        </div>

        {{-- ── Commande en cours ─────────────────────────────────────────────
             Sur grand écran, un panneau collé à droite. Sur téléphone, un
             tiroir qui monte du bas : le pouce y accède sans quitter la carte.
        --}}
        <aside :class="panierOuvert ? 'flex' : 'hidden lg:flex'"
               class="fixed inset-x-0 bottom-0 z-40 max-h-[85vh] flex-col rounded-t-3xl border border-secondary/15 bg-white shadow-2xl
                      lg:static lg:z-auto lg:max-h-[calc(100vh-11rem)] lg:rounded-2xl lg:shadow-sm lg:sticky lg:top-0">

            <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-3 border-b border-secondary/15">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Résumé</p>
                    <p class="font-heading text-base font-semibold text-primary">Commande en cours</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-accent/40 text-primary text-xs font-semibold">
                        <i data-lucide="shopping-cart" class="w-3.5 h-3.5"></i>
                        <span x-text="nbArticles"></span>
                    </span>
                    <button type="button" @click="panierOuvert = false" class="lg:hidden h-8 w-8 inline-flex items-center justify-center rounded-lg text-primary/50 hover:bg-accent/20">
                        <i data-lucide="chevron-down" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>

            <form method="POST" action="{{ route('restaurant.orders.store') }}" class="flex flex-col min-h-0 flex-1" @submit="preparerEnvoi($event)">
                @csrf
                <input type="hidden" name="items_json" x-ref="itemsJson">

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                    {{-- Panier --}}
                    <template x-if="lignes.length === 0">
                        <div class="flex flex-col items-center justify-center py-10 text-center text-primary/35">
                            <i data-lucide="shopping-cart" class="w-8 h-8 mb-2 opacity-40"></i>
                            <p class="text-sm">La commande est vide.</p>
                            <p class="text-sm">Ajoutez un plat pour commencer.</p>
                        </div>
                    </template>

                    <template x-for="ligne in lignes" :key="ligne.id">
                        <div class="flex items-start gap-3 pb-3 border-b border-secondary/10 last:border-0">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-primary leading-snug" x-text="ligne.nom"></p>
                                <p class="text-xs text-primary/45 mt-0.5" x-text="formatFcfa(ligne.prix) + ' × ' + ligne.qte"></p>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <button type="button" @click="retirer(ligne.id)" aria-label="Retirer un"
                                        class="h-9 w-9 inline-flex items-center justify-center rounded-lg border border-secondary/25 text-primary/70 hover:bg-accent/20 active:scale-95 transition">
                                    <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                </button>
                                <span class="w-6 text-center text-sm font-semibold text-primary" x-text="ligne.qte"></span>
                                <button type="button" @click="ajouter(ligne.id, ligne.nom, ligne.prix)" aria-label="Ajouter un"
                                        class="h-9 w-9 inline-flex items-center justify-center rounded-lg border border-secondary/25 text-primary/70 hover:bg-accent/20 active:scale-95 transition">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                            <p class="w-20 text-right text-sm font-semibold text-primary flex-shrink-0" x-text="formatFcfa(ligne.prix * ligne.qte)"></p>
                        </div>
                    </template>

                    {{-- Table et client --}}
                    <div class="pt-1 space-y-3">
                        <div>
                            <label for="table_number" class="block text-xs font-semibold text-primary/60 mb-1.5">Table <span class="text-red-500">*</span></label>
                            <input id="table_number" name="table_number" type="text" inputmode="numeric" maxlength="10" required
                                   x-model="table"
                                   placeholder="12"
                                   class="w-full px-3 py-2.5 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary">
                        </div>

                        {{-- Nom, téléphone et note : repliés par défaut, ils
                             n'encombrent pas le geste courant. --}}
                        <button type="button" @click="detailsOuverts = !detailsOuverts"
                                class="w-full flex items-center justify-between text-xs font-semibold text-primary/60 hover:text-primary">
                            <span>Client et note (facultatif)</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 transition-transform" :class="detailsOuverts && 'rotate-180'"></i>
                        </button>

                        <div x-show="detailsOuverts" x-transition class="space-y-3">
                            <input name="customer_name" type="text" maxlength="120" placeholder="Nom du client"
                                   class="w-full px-3 py-2.5 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary">
                            <input name="customer_phone" type="tel" maxlength="30" placeholder="Téléphone"
                                   class="w-full px-3 py-2.5 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary">
                            <textarea name="notes" rows="2" maxlength="2000" placeholder="Sans piment, cuisson à point…"
                                      class="w-full px-3 py-2.5 text-sm rounded-xl border border-secondary/25 outline-none focus:border-secondary"></textarea>
                        </div>
                    </div>
                </div>

                {{-- Pied : total et transmission --}}
                <div class="border-t border-secondary/15 px-5 py-4 space-y-3 bg-white lg:rounded-b-2xl">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-primary/60">Total commande</span>
                        <span class="font-heading text-lg font-semibold text-primary" x-text="formatFcfa(total)"></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="vider()" :disabled="lignes.length === 0"
                                class="flex-1 px-4 py-3 text-sm font-semibold rounded-xl border border-secondary/25 text-primary/70 hover:bg-accent/20 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            Annuler
                        </button>
                        <button type="submit" :disabled="lignes.length === 0 || !table.trim() || envoiEnCours"
                                class="flex-[1.4] px-4 py-3 text-sm font-semibold rounded-xl bg-primary text-white hover:opacity-95 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            <span x-text="envoiEnCours ? 'Envoi…' : 'Envoyer en cuisine'"></span>
                        </button>
                    </div>
                </div>
            </form>
        </aside>
    </div>

    {{-- Barre d'accès au panier, téléphone uniquement. --}}
    <button type="button" @click="panierOuvert = true"
            x-show="!panierOuvert"
            class="lg:hidden fixed inset-x-4 bottom-4 z-30 flex items-center justify-between gap-3 px-5 py-3.5 rounded-2xl bg-primary text-white shadow-xl">
        <span class="inline-flex items-center gap-2 text-sm font-semibold">
            <i data-lucide="shopping-cart" class="w-4 h-4"></i>
            <span x-text="nbArticles + (nbArticles > 1 ? ' articles' : ' article')"></span>
        </span>
        <span class="font-heading text-base font-semibold" x-text="formatFcfa(total)"></span>
    </button>

    {{-- Voile derrière le tiroir, pour isoler la saisie du panier. --}}
    <div x-show="panierOuvert" @click="panierOuvert = false"
         class="lg:hidden fixed inset-0 z-30 bg-black/40"></div>
</div>

<script>
    function initPriseDeCommande() {
        if (window.priseDeCommandeInitialisee) return;
        window.priseDeCommandeInitialisee = true;

        Alpine.data('priseDeCommande', () => ({
            lignes: [],
            recherche: '',
            categorie: 'toutes',
            table: '',
            panierOuvert: false,
            detailsOuverts: false,
            envoiEnCours: false,

            init() {
                const redessiner = () => this.$nextTick(() => {
                    if (window.refreshLucideIcons) window.refreshLucideIcons();
                });

                redessiner();
                ['lignes', 'recherche', 'categorie', 'panierOuvert', 'detailsOuverts']
                    .forEach((champ) => this.$watch(champ, redessiner));
            },

            // ── Carte ────────────────────────────────────────────────────────

            /** Le filtre porte sur le nom du plat et sur sa catégorie. */
            estVisible(id) {
                const vignette = document.querySelector(`[data-plat="${id}"]`);
                if (!vignette) return true;

                if (this.categorie !== 'toutes' && vignette.dataset.categorie !== this.categorie) {
                    return false;
                }

                const q = this.recherche.trim().toLowerCase();

                return q === '' || vignette.dataset.nom.includes(q);
            },

            get aucunResultat() {
                const vignettes = document.querySelectorAll('[data-plat]');

                return vignettes.length > 0
                    && ![...vignettes].some((v) => this.estVisible(Number(v.dataset.plat)));
            },

            // ── Panier ───────────────────────────────────────────────────────

            quantite(id) {
                const ligne = this.lignes.find((l) => l.id === id);

                return ligne ? ligne.qte : 0;
            },

            ajouter(id, nom, prix) {
                const ligne = this.lignes.find((l) => l.id === id);

                // 99 est la limite acceptée à l'enregistrement : la refuser ici
                // évite une commande rejetée après coup, devant le client.
                if (ligne) {
                    ligne.qte = Math.min(99, ligne.qte + 1);
                } else {
                    this.lignes.push({ id, nom, prix, qte: 1 });
                }
            },

            retirer(id) {
                const ligne = this.lignes.find((l) => l.id === id);
                if (!ligne) return;

                ligne.qte -= 1;
                if (ligne.qte <= 0) {
                    this.lignes = this.lignes.filter((l) => l.id !== id);
                }
            },

            vider() {
                this.lignes = [];
                this.panierOuvert = false;
            },

            get nbArticles() {
                return this.lignes.reduce((somme, l) => somme + l.qte, 0);
            },

            get total() {
                return this.lignes.reduce((somme, l) => somme + l.prix * l.qte, 0);
            },

            /** Montants stockés en centimes : l'affichage est en FCFA entiers. */
            formatFcfa(centimes) {
                return new Intl.NumberFormat('fr-FR').format(Math.round(centimes / 100)) + ' FCFA';
            },

            // ── Envoi ────────────────────────────────────────────────────────

            preparerEnvoi(evenement) {
                if (this.lignes.length === 0 || !this.table.trim()) {
                    evenement.preventDefault();
                    return;
                }

                this.$refs.itemsJson.value = JSON.stringify(
                    this.lignes.map((l) => ({ id: l.id, qty: l.qte }))
                );

                // Un double appui sur une tablette enverrait la commande deux fois.
                this.envoiEnCours = true;
            },
        }));
    }

    if (window.Alpine) {
        initPriseDeCommande();
    } else {
        document.addEventListener('alpine:init', initPriseDeCommande);
    }
</script>
@endsection
