@extends('layouts.hotel')

@section('title', 'Nouveau bon de commande — Économat')

@section('content')
<style>
@import url('https://fonts.googleapis.com/css2?family=Qwigley&display=swap');
@font-face {
    font-family: 'Qwigley';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('/fonts/Qwigley-Regular.woff2') format('woff2'),
         url('/fonts/Qwigley-Regular.ttf') format('truetype');
}
.font-signature {
    font-family: 'Qwigley', cursive, 'Brush Script MT', sans-serif;
}
</style>

@php
    $itemsJson = $items->map(fn ($i) => [
        'id'            => $i->id,
        'name'          => $i->name,
        'reference'     => $i->reference,
        'unit'          => $i->unit ?: 'pièce',
        'price'         => (int) round(($i->last_purchase_price ?: $i->average_cost) / 100), // en FCFA
        'supplier_id'   => $i->supplier_id ? (int) $i->supplier_id : null,
        'category_name' => $i->category?->name ?? 'Général',
        'current_stock' => (float) $i->current_stock,
    ])->values();

    $suppliersJson = $suppliers->map(fn ($s) => [
        'id'           => $s->id,
        'name'         => $s->name,
        'code'         => $s->code,
        'contact_name' => $s->contact_name,
        'email'        => $s->email,
        'phone'        => $s->phone,
        'address'      => $s->address,
        'can_email'    => $s->canReceiveOrdersByEmail(),
        'items_count'  => (int) $s->stock_items_count,
    ])->values();
@endphp

<div class="max-w-5xl mx-auto"
     x-data="orderReplenishmentForm({{ Js::from($itemsJson) }}, {{ Js::from($suppliersJson) }}, {{ (int) ($selectedSupplierId ?? 0) }})">

    {{-- Fil d'Ariane --}}
    <div class="flex items-center justify-between gap-4 mb-4">
        <a href="{{ route('economat.orders.index') }}" class="inline-flex items-center gap-1.5 text-xs text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Retour aux bons de commande</span>
        </a>

        <span class="text-xs text-primary/40 font-mono">Économat & Approvisionnements</span>
    </div>

    <div class="mb-6">
        <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
            <i data-lucide="shopping-cart" class="w-6 h-6 text-primary"></i>
            <span>Émission d'un Bon de Commande Fournisseur</span>
        </h1>
        <p class="text-xs text-primary/60 mt-1">
            Sélectionnez un fournisseur pour charger son catalogue de produits avec unités de mesure et prix unitaires connus.
        </p>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.orders.store') }}">
        @csrf

        {{-- Cadre Fournisseur Unique & Paramètres du bon --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-5 shadow-sm">
            <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-3 flex items-center gap-2">
                <i data-lucide="truck" class="w-4 h-4 text-primary/60"></i>
                <span>Fournisseur & Informations Générales</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                {{-- Choix Fournisseur (Unique) --}}
                <div class="md:col-span-6">
                    <label class="block text-xs font-semibold text-primary/80 mb-1">
                        Fournisseur <span class="text-red-500">*</span>
                    </label>
                    <select name="supplier_id" x-model.number="supplierId" @change="onSupplierChange()" required
                        class="w-full px-3 py-2.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary font-medium">
                        <option value="">Sélectionnez un fournisseur…</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">
                                {{ $supplier->name }} @if($supplier->code)({{ $supplier->code }})@endif — {{ $supplier->stock_items_count }} produit(s)
                            </option>
                        @endforeach
                    </select>

                    {{-- Fiche rapide du fournisseur sélectionné --}}
                    <template x-if="currentSupplier">
                        <div class="mt-2.5 p-3 bg-surface-light/70 rounded-lg border border-secondary/15 text-[11px] text-primary/80">
                            <div class="flex items-center justify-between font-semibold text-primary">
                                <span x-text="currentSupplier.name"></span>
                                <span class="font-mono text-[10px] text-primary/50" x-text="currentSupplier.code || ''"></span>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mt-1.5 text-primary/60 text-[10px]">
                                <div>
                                    <span class="text-primary/40 block">Contact :</span>
                                    <span x-text="currentSupplier.contact_name || 'Non renseigné'"></span>
                                </div>
                                <div>
                                    <span class="text-primary/40 block">Téléphone :</span>
                                    <span x-text="currentSupplier.phone || 'Non renseigné'"></span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-primary/40 block">Adresse email :</span>
                                    <span x-text="currentSupplier.email || 'Aucun email (envoi manuel requis)'" :class="!currentSupplier.email ? 'text-amber-600 font-medium' : ''"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Date de livraison souhaitée --}}
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-primary/80 mb-1">
                        Date de livraison souhaitée
                    </label>
                    <input type="date" name="expected_at"
                        class="w-full px-3 py-2.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    <span class="text-[10px] text-primary/40 block mt-1">Délai indicatif pour le fournisseur</span>
                </div>

                {{-- Signataire automatique (Économe) --}}
                <div class="md:col-span-3">
                    <label class="block text-xs font-semibold text-primary/80 mb-1">
                        Émetteur & Signature
                    </label>
                    <div class="p-2.5 bg-blue-50/70 border border-blue-200/60 rounded-lg">
                        <div class="text-[10px] font-semibold text-blue-900 truncate">
                            {{ auth()->user()->name }}
                        </div>
                        <div class="font-signature text-2xl text-blue-900 leading-none py-0.5 transform -rotate-2">
                            {{ auth()->user()->signatureName() }}
                        </div>
                        <span class="text-[9px] text-blue-700/60 block font-mono">Signature automatique</span>
                    </div>
                </div>

                {{-- Note au fournisseur --}}
                <div class="col-span-12">
                    <label class="block text-xs font-semibold text-primary/80 mb-1">
                        Instructions / Notes particulières pour la commande
                    </label>
                    <input type="text" name="notes" maxlength="1000"
                        placeholder="Ex : Livraison le matin avant 10h, déchargement magasin central, facture conforme au bon..."
                        class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                </div>
            </div>
        </div>

        {{-- Sélection rapide depuis le Catalogue Produits du Fournisseur --}}
        <template x-if="supplierId && supplierItems.length > 0">
            <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-1.5">
                            <i data-lucide="boxes" class="w-4 h-4 text-primary/60"></i>
                            <span>Articles livrés par <span x-text="currentSupplier ? currentSupplier.name : ''"></span></span>
                        </h2>
                        <p class="text-[11px] text-primary/50">
                            Cliquez sur un article pour l'ajouter directement avec son unité et son prix unitaire connu.
                        </p>
                    </div>
                    <span class="text-xs font-mono bg-secondary/15 px-2 py-0.5 rounded text-primary font-semibold"
                          x-text="supplierItems.length + ' article(s) au catalogue'"></span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5 max-h-56 overflow-y-auto pr-1">
                    <template x-for="item in supplierItems" :key="'cat-' + item.id">
                        <div class="p-2.5 rounded-lg border border-secondary/20 hover:border-primary/50 bg-surface-light/40 flex items-center justify-between transition-colors">
                            <div class="min-w-0 pr-2">
                                <div class="font-medium text-xs text-primary truncate" x-text="item.name"></div>
                                <div class="text-[10px] text-primary/50 flex items-center gap-1.5 mt-0.5">
                                    <span class="bg-white px-1.5 py-0.2 rounded border border-secondary/15 font-mono" x-text="item.unit"></span>
                                    <span class="font-mono font-semibold text-primary/70" x-text="formatMoney(item.price) + ' F'"></span>
                                </div>
                            </div>
                            <button type="button" @click="addFromCatalog(item)"
                                class="shrink-0 p-1.5 rounded-md bg-white border border-secondary/30 hover:bg-primary hover:text-white text-primary/70 transition-colors shadow-2xs"
                                title="Ajouter à la commande">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Lignes du Bon de Commande --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-5 shadow-sm">
            <div class="px-5 py-3.5 border-b border-secondary/20 bg-surface-light flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-primary">Articles commandés</h2>
                    <p class="text-[11px] text-primary/50">Lignes incluses dans le bon de commande adressé au fournisseur</p>
                </div>

                <button type="button" @click="addLine()" :disabled="!supplierId"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Ajouter une ligne</span>
                </button>
            </div>

            <div class="p-5">
                {{-- Alerte si aucun fournisseur sélectionné --}}
                <template x-if="!supplierId">
                    <div class="p-8 text-center text-primary/50">
                        <i data-lucide="truck" class="w-10 h-10 text-primary/20 mx-auto mb-2"></i>
                        <p class="text-xs font-medium">Veuillez d'abord sélectionner un fournisseur ci-dessus pour composer votre bon de commande.</p>
                    </div>
                </template>

                {{-- Fournisseur sans articles --}}
                <template x-if="supplierId && supplierItems.length === 0">
                    <div class="p-4 mb-4 bg-amber-50 border border-amber-200/80 rounded-lg text-xs text-amber-900 flex items-start gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-amber-700 shrink-0 mt-0.5"></i>
                        <div>
                            <strong>Aucun article rattaché à ce fournisseur pour l'instant.</strong>
                            <p class="text-[11px] text-amber-800 mt-0.5">
                                Vous pouvez sélectionner n'importe quel article de l'économat ci-dessous. Il sera automatiquement lié à ce fournisseur pour vos prochaines commandes.
                            </p>
                        </div>
                    </div>
                </template>

                {{-- Tableau des Lignes --}}
                <template x-if="supplierId">
                    <div>
                        <template x-if="lines.length === 0">
                            <p class="text-xs text-primary/40 text-center py-6">
                                Aucun article sélectionné. Choisissez un article dans le catalogue ci-dessus ou cliquez sur « Ajouter une ligne ».
                            </p>
                        </template>

                        <div class="space-y-3">
                            <template x-for="(line, idx) in lines" :key="line.key">
                                <div class="grid grid-cols-12 gap-2.5 items-center p-3 rounded-lg border border-secondary/20 bg-surface-light/20">
                                    {{-- Sélection Article --}}
                                    <div class="col-span-12 md:col-span-5">
                                        <label class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Article / Service</label>
                                        <select :name="`lines[${idx}][stock_item_id]`" x-model.number="line.itemId" @change="onItemChange(line)" required
                                            class="w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary font-medium">
                                            <option value="">Sélectionner un article…</option>
                                            {{-- Priorité 1 : Articles du fournisseur --}}
                                            <template x-if="supplierItems.length > 0">
                                                <optgroup label="Catalogue de ce fournisseur">
                                                    <template x-for="it in supplierItems" :key="'grp-sup-' + it.id">
                                                        <option :value="it.id" x-text="it.name + ' (' + it.unit + ')'"></option>
                                                    </template>
                                                </optgroup>
                                            </template>
                                            {{-- Priorité 2 : Autres articles de l'économat --}}
                                            <optgroup label="Tous les articles de l'économat">
                                                <template x-for="it in allItems" :key="'grp-all-' + it.id">
                                                    <option :value="it.id" x-text="it.name + ' [' + it.category_name + '] (' + it.unit + ')'"></option>
                                                </template>
                                            </optgroup>
                                        </select>
                                    </div>

                                    {{-- Unité de mesure connue --}}
                                    <div class="col-span-3 md:col-span-2 text-center">
                                        <label class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Unité connue</label>
                                        <div class="px-2 py-1.5 bg-white border border-secondary/20 rounded-lg text-xs font-mono font-medium text-primary truncate"
                                             x-text="line.unit || '—'"></div>
                                    </div>

                                    {{-- Quantité commandée --}}
                                    <div class="col-span-4 md:col-span-2">
                                        <label class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Quantité</label>
                                        <input type="number" step="0.001" min="0.001" :name="`lines[${idx}][quantity]`" x-model.number="line.qty"
                                            placeholder="Qté" required
                                            class="w-full px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary text-right font-mono font-bold">
                                    </div>

                                    {{-- Prix Unitaire connu (FCFA) --}}
                                    <div class="col-span-4 md:col-span-2">
                                        <label class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">P.U. Connu (F)</label>
                                        <div class="relative">
                                            <input type="number" min="0" :name="`lines[${idx}][unit_price]`" x-model.number="line.price"
                                                placeholder="P.U." required
                                                class="w-full px-2 py-1.5 pr-6 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary text-right font-mono">
                                            <span class="absolute right-2 top-1.5 text-[10px] text-primary/40 font-mono">F</span>
                                        </div>
                                    </div>

                                    {{-- Sous-total Ligne & Suppression --}}
                                    <div class="col-span-1 text-right flex items-center justify-end gap-1">
                                        <button type="button" @click="removeLine(idx)" class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50 transition-colors" title="Supprimer la ligne">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>

                                    {{-- Ligne récapitulative sous-total --}}
                                    <div class="col-span-12 text-right pt-1 border-t border-secondary/10 flex justify-between items-center text-[10px] text-primary/60">
                                        <span>
                                            Stock actuel en magasin : <strong class="font-mono text-primary" x-text="line.currentStock ?? '—'"></strong>
                                            <span x-text="line.unit"></span>
                                        </span>
                                        <span>
                                            Total ligne : <strong class="font-mono text-primary text-xs" x-text="formatMoney((parseFloat(line.qty) || 0) * (parseInt(line.price) || 0)) + ' FCFA'"></strong>
                                        </span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Pied du tableau avec Total Général Valorisé --}}
            <div class="px-5 py-3.5 border-t border-secondary/20 bg-surface-light flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs text-primary/60">
                    Nombre d'articles : <strong class="font-mono text-primary font-bold" x-text="lines.length"></strong>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold uppercase text-primary/70">Montant total de la commande :</span>
                    <span class="text-xl font-bold font-mono text-primary" x-text="formatMoney(total) + ' FCFA'"></span>
                </div>
            </div>
        </div>

        {{-- Boutons d'action --}}
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('economat.orders.index') }}" class="px-4 py-2 text-xs font-semibold text-primary/60 hover:text-primary transition-colors">
                Annuler
            </a>

            <button type="submit" :disabled="lines.length === 0 || !supplierId"
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <i data-lucide="check" class="w-4 h-4"></i>
                <span>Créer et signer le bon de commande</span>
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function orderReplenishmentForm(allItems, allSuppliers, initialSupplierId) {
        return {
            allItems,
            allSuppliers,
            supplierId: initialSupplierId || '',
            lines: [],
            nextKey: 1,

            get currentSupplier() {
                return this.allSuppliers.find(s => s.id === this.supplierId) || null;
            },

            get supplierItems() {
                if (!this.supplierId) return [];
                return this.allItems.filter(i => i.supplier_id === this.supplierId);
            },

            onSupplierChange() {
                // Si l'utilisateur change de fournisseur, on vide les lignes pour garantir
                // qu'un bon n'est lié qu'à un seul fournisseur
                this.lines = [];
                // Si ce fournisseur a des articles habituels, on peut en précharger un
                if (this.supplierItems.length > 0) {
                    this.addFromCatalog(this.supplierItems[0]);
                } else {
                    this.addLine();
                }
            },

            addFromCatalog(item) {
                // Évite les doublons : si l'article est déjà présent, on incrémente la quantité
                const existing = this.lines.find(l => l.itemId === item.id);
                if (existing) {
                    existing.qty = (parseFloat(existing.qty) || 0) + 1;
                    return;
                }

                this.lines.push({
                    key: this.nextKey++,
                    itemId: item.id,
                    name: item.name,
                    unit: item.unit,
                    price: item.price,
                    qty: 1,
                    currentStock: item.current_stock,
                });
            },

            addLine() {
                this.lines.push({
                    key: this.nextKey++,
                    itemId: '',
                    name: '',
                    unit: '',
                    price: 0,
                    qty: 1,
                    currentStock: null,
                });
            },

            removeLine(idx) {
                this.lines.splice(idx, 1);
            },

            onItemChange(line) {
                const item = this.allItems.find(i => i.id === line.itemId);
                if (item) {
                    line.name = item.name;
                    line.unit = item.unit;
                    line.price = item.price;
                    line.currentStock = item.current_stock;
                } else {
                    line.unit = '';
                    line.price = 0;
                    line.currentStock = null;
                }
            },

            get total() {
                return this.lines.reduce((acc, l) => {
                    const qty = parseFloat(l.qty) || 0;
                    const price = parseInt(l.price) || 0;
                    return acc + (qty * price);
                }, 0);
            },

            formatMoney(amount) {
                return new Intl.NumberFormat('fr-FR').format(amount || 0);
            },

            init() {
                if (this.supplierId) {
                    this.onSupplierChange();
                }
            }
        };
    }
</script>
@endpush
