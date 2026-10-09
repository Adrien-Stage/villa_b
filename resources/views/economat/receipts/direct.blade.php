@extends('layouts.hotel')

@section('title', 'Réception directe — Économat')

@section('content')
@php
    $articlesJson = $items->map(fn ($i) => [
        'id'    => $i->id,
        'name'  => $i->name,
        'unit'  => $i->unit ?: 'pièce',
        // Dernier prix payé, sinon coût moyen : une proposition, modifiable.
        'price' => (int) round(($i->last_purchase_price ?: $i->average_cost) / 100),
    ])->values();

    // Après une erreur de validation, le formulaire revient tel qu'il était saisi.
    $lignesSaisies = collect(old('lines', []))->values()->map(fn ($l) => [
        'nouveau'   => empty($l['stock_item_id']) && !empty($l['nouvel_article']['name'] ?? null),
        'itemId'    => (int) ($l['stock_item_id'] ?? 0) ?: '',
        'name'      => $l['nouvel_article']['name'] ?? '',
        'unit'      => $l['nouvel_article']['unit'] ?? '',
        'category'  => $l['nouvel_article']['stock_category_id'] ?? '',
        'delivered' => $l['quantity_delivered'] ?? '',
        'rejected'  => $l['quantity_rejected'] ?? '',
        'reason'    => $l['rejection_reason'] ?? '',
        'price'     => $l['unit_price'] ?? '',
        'notes'     => $l['notes'] ?? '',
    ]);
    $nouveauFournisseur = !old('supplier_id') && old('nouveau_fournisseur.name');
    $champ = 'w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary';
@endphp

<div class="max-w-5xl mx-auto space-y-6"
     x-data="receptionDirecte({{ Js::from($articlesJson) }}, {{ Js::from($lignesSaisies) }}, {{ $nouveauFournisseur ? 'true' : 'false' }})">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.receipts.index') }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors" aria-label="Retour aux bons d'entrée">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <span class="text-xs font-mono font-semibold text-primary/50 uppercase">Bon d'entrée en stock (BR)</span>
            <h1 class="text-xl font-heading font-semibold text-primary">Réception directe, sans bon de commande</h1>
            <p class="text-sm text-primary/60 mt-0.5 max-w-3xl">
                Pour une marchandise déjà arrivée : achat au comptant, livraison imprévue, urgence.
                Un bon de commande de régularisation est établi pour ce qui est gardé. C'est lui qui recevra la facture du fournisseur.
            </p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.receipts.direct.store') }}" class="space-y-6">
        @csrf

        {{-- Fournisseur et livraison --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                    <i data-lucide="truck" class="w-4 h-4 text-primary/60"></i> Fournisseur et livraison
                </h2>
                @if($peutCreerFournisseur)
                    <div class="inline-flex rounded-lg border border-secondary/30 p-0.5 text-xs" role="group" aria-label="Fournisseur">
                        <button type="button" @click="nouveauFournisseur = false" :aria-pressed="!nouveauFournisseur"
                            :class="!nouveauFournisseur ? 'bg-primary text-white' : 'text-primary/70 hover:bg-gray-50'" class="px-3 py-1 rounded-md transition-colors">
                            Fournisseur connu
                        </button>
                        <button type="button" @click="nouveauFournisseur = true" :aria-pressed="nouveauFournisseur"
                            :class="nouveauFournisseur ? 'bg-primary text-white' : 'text-primary/70 hover:bg-gray-50'" class="px-3 py-1 rounded-md transition-colors">
                            Nouveau fournisseur
                        </button>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <template x-if="!nouveauFournisseur">
                    <div class="md:col-span-2">
                        <label for="fournisseur" class="block text-xs font-semibold text-primary/80 mb-1.5">Fournisseur <span class="text-red-500">*</span></label>
                        <select id="fournisseur" name="supplier_id" required class="{{ $champ }}">
                            <option value="">Choisir un fournisseur…</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id') === $supplier->id)>{{ $supplier->name }}@if($supplier->phone) — {{ $supplier->phone }}@endif</option>
                            @endforeach
                        </select>
                        @if($suppliers->isEmpty())
                            <p class="text-[11px] text-amber-700 mt-1">Aucun fournisseur enregistré. @if($peutCreerFournisseur)Choisissez « Nouveau fournisseur ».@endif</p>
                        @endif
                    </div>
                </template>
                <template x-if="nouveauFournisseur">
                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="nouveau-fournisseur" class="block text-xs font-semibold text-primary/80 mb-1.5">Nom du fournisseur <span class="text-red-500">*</span></label>
                            <input id="nouveau-fournisseur" type="text" name="nouveau_fournisseur[name]" value="{{ old('nouveau_fournisseur.name') }}" required maxlength="160" placeholder="Ex : Marché Mokolo — Mme Ngo" class="{{ $champ }}">
                        </div>
                        <div>
                            <label for="nouveau-fournisseur-tel" class="block text-xs font-semibold text-primary/80 mb-1.5">Téléphone</label>
                            <input id="nouveau-fournisseur-tel" type="text" name="nouveau_fournisseur[phone]" value="{{ old('nouveau_fournisseur.phone') }}" maxlength="30" placeholder="+237 6…" class="{{ $champ }}">
                        </div>
                        <p class="md:col-span-2 text-[11px] text-primary/50 -mt-2">Sa fiche se complète ensuite dans Fournisseurs (email, conditions de paiement…).</p>
                    </div>
                </template>

                <div>
                    <label for="motif" class="block text-xs font-semibold text-primary/80 mb-1.5">Pourquoi sans bon de commande ? <span class="text-red-500">*</span></label>
                    <select id="motif" name="motif" required class="{{ $champ }}">
                        <option value="">Choisir…</option>
                        @foreach($motifs as $code => $libelle)
                            <option value="{{ $code }}" @selected(old('motif') === $code)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="bl" class="block text-xs font-semibold text-primary/80 mb-1.5">N° du bon de livraison, du ticket ou de la facture</label>
                    <input id="bl" type="text" name="delivery_note_number" value="{{ old('delivery_note_number') }}" maxlength="80" placeholder="Ex : BL-98421" class="{{ $champ }} font-mono">
                </div>
                <div>
                    <label for="recu-le" class="block text-xs font-semibold text-primary/80 mb-1.5">Reçu le</label>
                    <input id="recu-le" type="datetime-local" name="received_at" value="{{ old('received_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="{{ $champ }}">
                </div>
                <div>
                    <label for="observations" class="block text-xs font-semibold text-primary/80 mb-1.5">Observations</label>
                    <input id="observations" type="text" name="notes" value="{{ old('notes') }}" maxlength="1000" placeholder="Ex : acheté par M. Talla, payé en espèces" class="{{ $champ }}">
                </div>
            </div>
        </div>

        {{-- Articles reçus --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-primary">Articles reçus</h2>
                    <p class="text-xs text-primary/50">
                        Ce qui est refusé n'entre pas en stock.
                        @if($peutCreerArticle) Un article absent du magasin se crée ici, avec son unité. @endif
                    </p>
                </div>
                <button type="button" @click="ajouter()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un article
                </button>
            </div>

            <div class="p-5 space-y-3">
                <template x-if="lignes.length === 0">
                    <p class="text-xs text-primary/40 text-center py-6">Aucun article. Cliquez sur « Ajouter un article ».</p>
                </template>

                <template x-for="(ligne, idx) in lignes" :key="ligne.key">
                    <div class="p-3 rounded-lg border border-secondary/20 bg-surface-light/20 space-y-2.5">
                        <div class="grid grid-cols-12 gap-2.5 items-end">
                            {{-- Article --}}
                            <div class="col-span-12 md:col-span-6">
                                <div class="flex items-center justify-between mb-0.5">
                                    <label :for="'article-' + ligne.key" class="text-[10px] font-semibold text-primary/60 uppercase">Article</label>
                                    @if($peutCreerArticle)
                                        <button type="button" @click="ligne.nouveau = !ligne.nouveau" class="text-[11px] text-primary/70 underline hover:text-primary"
                                            x-text="ligne.nouveau ? 'Choisir un article existant' : 'Nouvel article'"></button>
                                    @endif
                                </div>
                                <template x-if="!ligne.nouveau">
                                    <select :id="'article-' + ligne.key" :name="`lines[${idx}][stock_item_id]`" x-model.number="ligne.itemId" @change="choisir(ligne)" required
                                        class="w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                        <option value="">Choisir un article…</option>
                                        <template x-for="a in articles" :key="a.id">
                                            <option :value="a.id" x-text="a.name + ' (' + a.unit + ')'" :selected="a.id === ligne.itemId"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="ligne.nouveau">
                                    <div class="grid grid-cols-6 gap-2">
                                        <input :id="'article-' + ligne.key" type="text" :name="`lines[${idx}][nouvel_article][name]`" x-model="ligne.name" required maxlength="160" placeholder="Nom de l'article"
                                            class="col-span-6 sm:col-span-3 px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                        <input type="text" :name="`lines[${idx}][nouvel_article][unit]`" x-model="ligne.unit" maxlength="20" placeholder="Unité (kg, pièce…)" aria-label="Unité"
                                            class="col-span-3 sm:col-span-1 px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                        <select :name="`lines[${idx}][nouvel_article][stock_category_id]`" x-model="ligne.category" aria-label="Catégorie"
                                            class="col-span-3 sm:col-span-2 px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                            <option value="">Sans catégorie</option>
                                            @foreach($categories as $categorie)
                                                <option value="{{ $categorie->id }}">{{ $categorie->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </template>
                            </div>

                            <div class="col-span-4 md:col-span-2">
                                <label :for="'livre-' + ligne.key" class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Livré</label>
                                <input :id="'livre-' + ligne.key" type="number" step="0.001" min="0.001" :name="`lines[${idx}][quantity_delivered]`" x-model="ligne.delivered" required
                                    class="w-full px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary text-right font-mono font-bold">
                            </div>
                            <div class="col-span-4 md:col-span-2">
                                <label :for="'refuse-' + ligne.key" class="block text-[10px] font-semibold text-rose-700/80 mb-0.5 uppercase">Refusé</label>
                                <input :id="'refuse-' + ligne.key" type="number" step="0.001" min="0" :name="`lines[${idx}][quantity_rejected]`" x-model="ligne.rejected" placeholder="0"
                                    class="w-full px-2 py-1.5 text-xs border border-rose-300 bg-rose-50/30 rounded-lg text-rose-800 outline-none focus:border-rose-500 text-right font-mono">
                            </div>
                            <div class="col-span-4 md:col-span-2">
                                <label :for="'prix-' + ligne.key" class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">P.U. (FCFA)</label>
                                <input :id="'prix-' + ligne.key" type="number" step="1" min="1" :name="`lines[${idx}][unit_price]`" x-model="ligne.price" required
                                    class="w-full px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary text-right font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-12 gap-2.5 items-center">
                            <div class="col-span-12 md:col-span-4" x-show="parseFloat(ligne.rejected) > 0">
                                <select :name="`lines[${idx}][rejection_reason]`" x-model="ligne.reason" aria-label="Motif du refus"
                                    class="w-full px-2 py-1 text-xs border border-rose-300 rounded-lg bg-rose-50/40 text-rose-900 focus:outline-none">
                                    <option value="">Motif du refus…</option>
                                    @foreach($reasons as $code => $libelle)
                                        <option value="{{ $code }}">{{ $libelle }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-12" :class="parseFloat(ligne.rejected) > 0 ? 'md:col-span-4' : 'md:col-span-8'">
                                <input type="text" :name="`lines[${idx}][notes]`" x-model="ligne.notes" maxlength="255" placeholder="Note sur la ligne…" aria-label="Note sur la ligne"
                                    class="w-full px-2 py-1 text-xs border border-secondary/20 rounded bg-white text-primary">
                            </div>
                            <div class="col-span-12 md:col-span-4 flex items-center justify-end gap-3 text-[11px] text-primary/60">
                                <span>Gardé : <strong class="font-mono text-emerald-700" x-text="formatQte(garde(ligne)) + ' ' + uniteDe(ligne)"></strong></span>
                                <span>Total : <strong class="font-mono text-primary" x-text="formatMontant(garde(ligne) * (parseFloat(ligne.price) || 0)) + ' F'"></strong></span>
                                <button type="button" @click="retirer(idx)" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50 transition-colors" title="Retirer la ligne" aria-label="Retirer la ligne">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="px-5 py-3.5 border-t border-secondary/20 bg-surface-light flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-primary/60">Articles : <strong class="font-mono text-primary" x-text="lignes.length"></strong></span>
                <span class="flex items-center gap-3">
                    <span class="text-xs font-semibold uppercase text-primary/70">Valeur entrée en stock</span>
                    <span class="text-xl font-bold font-mono text-primary" x-text="formatMontant(total) + ' FCFA'"></span>
                </span>
            </div>
        </div>

        {{-- Engagement et signature --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-xs text-primary/60 max-w-xl">
                En validant, vous certifiez la réception de ces marchandises. Ce qui est gardé entre en stock tout de suite, au prix indiqué.
                La direction et la comptabilité sont prévenues : la facture du fournisseur se rapprochera du bon de régularisation.
            </p>
            <div class="px-5 py-2.5 bg-gray-50 border border-dashed border-secondary/30 rounded-xl text-center shrink-0 min-w-[200px]">
                <div class="text-[10px] uppercase font-semibold text-primary/40 tracking-wider">Réceptionné par</div>
                <div class="text-sm font-medium text-primary py-1">{{ auth()->user()->name }}</div>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('economat.receipts.index') }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</a>
            <button type="submit" :disabled="lignes.length === 0"
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <i data-lucide="package-check" class="w-4 h-4"></i> Valider la réception
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function receptionDirecte(articles, lignesSaisies, nouveauFournisseur) {
        return {
            articles,
            nouveauFournisseur,
            lignes: [],
            prochaineCle: 1,

            init() {
                lignesSaisies.forEach(l => this.lignes.push({ key: this.prochaineCle++, ...l }));
                if (this.lignes.length === 0) {
                    this.ajouter();
                } else {
                    this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
                }
            },

            ajouter() {
                this.lignes.push({
                    key: this.prochaineCle++,
                    // Magasin vide : on part d'un nouvel article plutôt que d'une liste sans choix.
                    nouveau: this.articles.length === 0,
                    itemId: '', name: '', unit: '', category: '',
                    delivered: '', rejected: '', reason: '', price: '', notes: '',
                });
                this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
            },

            retirer(idx) {
                this.lignes.splice(idx, 1);
            },

            choisir(ligne) {
                const article = this.articles.find(a => a.id === ligne.itemId);
                if (article && !ligne.price && article.price > 0) {
                    ligne.price = article.price;
                }
            },

            uniteDe(ligne) {
                if (ligne.nouveau) return ligne.unit || 'pièce';
                return this.articles.find(a => a.id === ligne.itemId)?.unit || '';
            },

            garde(ligne) {
                return Math.max(0, (parseFloat(ligne.delivered) || 0) - (parseFloat(ligne.rejected) || 0));
            },

            get total() {
                return this.lignes.reduce((somme, l) => somme + this.garde(l) * (parseFloat(l.price) || 0), 0);
            },

            formatQte(valeur) {
                return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(valeur);
            },

            formatMontant(valeur) {
                return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(valeur));
            },
        };
    }
</script>
@endpush
