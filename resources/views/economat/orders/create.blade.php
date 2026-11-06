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
        'unit'          => $i->shortUnit(),
        // Dernier prix payé : repère pour un article hors catalogue du fournisseur.
        'price'         => (int) round(($i->last_purchase_price ?: $i->average_cost) / 100), // en FCFA
        'supplier_id'   => $i->supplier_id ? (int) $i->supplier_id : null,
        // Le prix de l'article chez chaque fournisseur, en FCFA.
        'tarifs'        => (object) $i->tarifsFcfa(),
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
     x-data="orderReplenishmentForm({{ Js::from($itemsJson) }}, {{ Js::from($suppliersJson) }}, {{ (int) ($selectedSupplierId ?? 0) }}, {{ (int) ($selectedItemId ?? 0) }})">

    {{-- Fil d'Ariane --}}
    <div class="flex items-center justify-between gap-4 mb-4">
        <a href="{{ route('economat.orders.index') }}" class="inline-flex items-center gap-1.5 text-xs text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
            <span>Retour aux bons de commande</span>
        </a>

        <span class="text-xs text-primary/40 font-mono">Économat & Approvisionnements</span>
    </div>

    <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="shopping-cart" class="w-6 h-6 text-primary"></i>
                <span>Émission d'un Bon de Commande Fournisseur</span>
            </h1>
            <p class="text-xs text-primary/60 mt-1">
                Choisissez le fournisseur selon l'article grâce au comparateur de prix ou sélectionnez-le directement pour charger son catalogue.
            </p>
        </div>

        <button type="button" @click="ouvrirComparateur()"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-accent/30 hover:bg-accent/40 text-primary border border-secondary/30 rounded-xl text-xs font-bold transition-all shadow-2xs self-start md:self-auto">
            <i data-lucide="git-compare" class="w-4 h-4 text-primary"></i>
            <span>Comparer les fournisseurs par article</span>
        </button>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.orders.store') }}">
        @csrf

        {{-- Cadre Fournisseur & Paramètres du bon --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-5 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-2">
                    <i data-lucide="truck" class="w-4 h-4 text-primary/60"></i>
                    <span>Fournisseur & Informations Générales</span>
                </h2>

                <button type="button" @click="ouvrirComparateur()"
                    class="text-[11px] font-semibold text-primary hover:underline inline-flex items-center gap-1">
                    <i data-lucide="search" class="w-3 h-3"></i>
                    <span>Trouver le meilleur fournisseur pour un article</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                {{-- Choix Fournisseur (Direct ou via Comparateur) --}}
                <div class="md:col-span-6">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-semibold text-primary/80">
                            Fournisseur du bon de commande <span class="text-red-500">*</span>
                        </label>
                        <template x-if="supplierId">
                            <button type="button" @click="supplierId = ''; onSupplierChange()"
                                class="text-[10px] text-primary/40 hover:text-red-600 underline">
                                Réinitialiser le choix
                            </button>
                        </template>
                    </div>

                    <div class="flex gap-2">
                        <select name="supplier_id" x-model.number="supplierId" @change="onSupplierChange()" required
                            class="flex-1 px-3 py-2.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary font-medium">
                            <option value="">Sélectionnez un fournisseur…</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">
                                    {{ $supplier->name }} @if($supplier->code)({{ $supplier->code }})@endif — {{ $supplier->stock_items_count }} produit(s)
                                </option>
                            @endforeach
                        </select>

                        <button type="button" @click="ouvrirComparateur()"
                            class="px-3 py-2.5 bg-surface-light border border-secondary/30 rounded-lg text-primary hover:bg-secondary/15 transition-colors text-xs font-semibold flex items-center gap-1.5 shrink-0"
                            title="Comparer les fournisseurs en fonction d'un article">
                            <i data-lucide="git-compare" class="w-4 h-4 text-primary"></i>
                            <span class="hidden sm:inline">Comparer</span>
                        </button>
                    </div>

                    {{-- Fiche rapide du fournisseur sélectionné --}}
                    <template x-if="currentSupplier">
                        <div class="mt-2.5 p-3 bg-surface-light/70 rounded-lg border border-secondary/15 text-[11px] text-primary/80">
                            <div class="flex items-center justify-between font-semibold text-primary">
                                <span class="text-xs" x-text="currentSupplier.name"></span>
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

                {{-- Signataire automatique (Responsable Achats) --}}
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

        {{-- Sélection rapide depuis le Catalogue Produits du Fournisseur (si fournisseur choisi) --}}
        <template x-if="supplierId && supplierItems.length > 0">
            <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-5 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-primary flex items-center gap-1.5">
                            <i data-lucide="boxes" class="w-4 h-4 text-primary/60"></i>
                            <span>Articles au catalogue de <span x-text="currentSupplier ? currentSupplier.name : ''"></span></span>
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
                            <div class="flex items-center gap-1 shrink-0">
                                <button type="button" @click="ouvrirComparateur(item.id)"
                                    class="p-1.5 rounded-md bg-white border border-secondary/30 hover:bg-secondary/10 text-primary/60 hover:text-primary transition-colors"
                                    title="Comparer les prix d'autres fournisseurs pour cet article">
                                    <i data-lucide="git-compare" class="w-3.5 h-3.5"></i>
                                </button>
                                <button type="button" @click="addFromCatalog(item)"
                                    class="p-1.5 rounded-md bg-white border border-secondary/30 hover:bg-primary hover:text-white text-primary/70 transition-colors shadow-2xs"
                                    title="Ajouter à la commande">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        {{-- Lignes du Bon de Commande --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-5 shadow-sm flex flex-col">
            <div class="px-5 py-3.5 border-b border-secondary/20 bg-surface-light flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-primary">Articles commandés</h2>
                    <p class="text-[11px] text-primary/50">Lignes incluses dans le bon de commande adressé au fournisseur</p>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" @click="ouvrirComparateur()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-accent/30 text-primary border border-secondary/30 text-xs font-semibold rounded-lg hover:bg-accent/50 transition-colors">
                        <i data-lucide="git-compare" class="w-3.5 h-3.5"></i>
                        <span>Comparer par article</span>
                    </button>

                    <button type="button" @click="addLine()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Ajouter une ligne</span>
                    </button>
                </div>
            </div>

            <div x-ref="linesBody" class="p-5 max-h-[500px] overflow-y-auto">
                {{-- Invite guidée si aucun fournisseur sélectionné --}}
                <template x-if="!supplierId">
                    <div class="p-4 mb-4 bg-blue-50/80 border border-blue-200/80 rounded-xl text-xs text-blue-900 flex items-start justify-between gap-3">
                        <div class="flex items-start gap-2.5">
                            <i data-lucide="git-compare" class="w-5 h-5 text-blue-700 shrink-0 mt-0.5"></i>
                            <div>
                                <strong class="font-bold">Sélectionnez le fournisseur en fonction de vos articles</strong>
                                <p class="text-[11px] text-blue-800/80 mt-0.5">
                                    Ajoutez un article ou cliquez sur le comparateur : l'application vous présentera la liste des fournisseurs qui livrent cet article avec leurs prix livrés récents pour retenir la meilleure offre.
                                </p>
                            </div>
                        </div>
                        <button type="button" @click="ouvrirComparateur()"
                            class="shrink-0 px-3 py-1.5 bg-blue-900 text-white rounded-lg text-xs font-semibold hover:bg-blue-950 transition-colors shadow-2xs">
                            Ouvrir le comparateur
                        </button>
                    </div>
                </template>

                {{-- Fournisseur sans articles au catalogue --}}
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
                <div>
                    <template x-if="lines.length === 0">
                        <div class="text-center py-8 text-primary/40 space-y-2">
                            <i data-lucide="shopping-bag" class="w-10 h-10 mx-auto opacity-30"></i>
                            <p class="text-xs font-medium">Aucun article sélectionné pour ce bon de commande.</p>
                            <div class="flex items-center justify-center gap-3 pt-1">
                                <button type="button" @click="ouvrirComparateur()" class="text-xs font-semibold text-primary underline hover:text-surface-dark">
                                    Choisir via le comparateur
                                </button>
                                <span>ou</span>
                                <button type="button" @click="addLine()" class="text-xs font-semibold text-primary underline hover:text-surface-dark">
                                    Ajouter un article manuellement
                                </button>
                            </div>
                        </div>
                    </template>

                    <div class="space-y-3">
                        <template x-for="(line, idx) in lines" :key="line.key">
                            <div class="grid grid-cols-12 gap-2.5 items-center p-3.5 rounded-xl border border-secondary/20 bg-surface-light/30">
                                {{-- Sélection Article --}}
                                <div class="col-span-12 md:col-span-5">
                                    <div class="flex items-center justify-between mb-0.5">
                                        <label class="block text-[10px] font-semibold text-primary/60 uppercase">Article / Service</label>
                                        <template x-if="line.itemId">
                                            <button type="button" @click="ouvrirComparateurPourLigne(line, idx)"
                                                class="text-[10px] font-semibold text-primary/70 hover:text-primary flex items-center gap-1 underline"
                                                title="Comparer les prix des autres fournisseurs pour cet article">
                                                <svg class="w-3 h-3 stroke-current fill-none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><path d="M11 18H8a2 2 0 0 1-2-2V9"/>
                                                </svg>
                                                <span>Comparer les fournisseurs</span>
                                            </button>
                                        </template>
                                    </div>
                                    <x-article-picker source="articlesProposes" model="line.itemId" name-bind="`lines[${idx}][stock_item_id]`" size="sm" required
                                        on-select="onItemChange(line, idx)" aria-label="Article"
                                        detail="it => (it.duFournisseur ? 'Catalogue du fournisseur · ' : (it.category_name ? it.category_name + ' · ' : '')) + it.unit + (it.reference ? ' · ' + it.reference : '')" />
                                </div>

                                {{-- Unité de mesure connue --}}
                                <div class="col-span-3 md:col-span-2 text-center">
                                    <label class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Unité</label>
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
                                    <label class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">P.U. (FCFA)</label>
                                    <div class="relative">
                                        <input type="number" min="0" :name="`lines[${idx}][unit_price]`" x-model.number="line.price"
                                            placeholder="P.U." required
                                            class="w-full px-2 py-1.5 pr-6 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary text-right font-mono font-semibold">
                                        <span class="absolute right-2 top-1.5 text-[10px] text-primary/40 font-mono">F</span>
                                    </div>
                                </div>

                                {{-- Bouton Supprimer la ligne --}}
                                <div class="col-span-1 flex flex-col items-center justify-end">
                                    <span class="block text-[10px] font-semibold text-transparent mb-0.5 select-none uppercase hidden md:block">&nbsp;</span>
                                    <button type="button" @click="removeLine(idx)"
                                        class="p-2 rounded-lg text-red-600 hover:text-white hover:bg-red-600 bg-red-50 border border-red-200 transition-all shadow-2xs flex items-center justify-center shrink-0 cursor-pointer"
                                        title="Supprimer cette ligne">
                                        <svg class="w-4 h-4 stroke-current fill-none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2m-6 5v6m4-6v6"/>
                                        </svg>
                                        <span class="sr-only">Supprimer</span>
                                    </button>
                                </div>

                                {{-- Ligne récapitulative sous-total, stock et actions --}}
                                <div class="col-span-12 pt-1 border-t border-secondary/10 flex justify-between items-center text-[10px] text-primary/60">
                                    <div class="flex items-center gap-2">
                                        <span>
                                            Stock actuel : <strong class="font-mono text-primary" x-text="line.currentStock ?? '—'"></strong>
                                            <span x-text="line.unit"></span>
                                        </span>
                                        <template x-if="line.itemId">
                                            <button type="button" @click="ouvrirComparateurPourLigne(line, idx)" class="text-primary hover:underline font-semibold flex items-center gap-1 ml-2">
                                                <svg class="w-3 h-3 stroke-current fill-none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <circle cx="18" cy="18" r="3"/><circle cx="6" cy="6" r="3"/><path d="M13 6h3a2 2 0 0 1 2 2v7"/><path d="M11 18H8a2 2 0 0 1-2-2V9"/>
                                                </svg>
                                                <span>Comparer les prix fournisseurs</span>
                                            </button>
                                        </template>
                                        <button type="button" @click="removeLine(idx)" class="text-red-600 hover:text-red-800 hover:underline font-semibold flex items-center gap-1 ml-4 border-l border-secondary/20 pl-3">
                                            <svg class="w-3 h-3 stroke-current fill-none" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M3 6h18m-2 0v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6m3 0V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>
                                            </svg>
                                            <span>Supprimer la ligne</span>
                                        </button>
                                    </div>
                                    <span>
                                        Total ligne : <strong class="font-mono text-primary text-xs" x-text="formatMoney((parseFloat(line.qty) || 0) * (parseInt(line.price) || 0)) + ' FCFA'"></strong>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Pied du tableau avec Total Général Valorisé --}}
            <div class="px-5 py-3.5 border-t border-secondary/20 bg-surface-light flex flex-col sm:flex-row sm:items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-3">
                    <button type="button" @click="addLine()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary/70 hover:text-primary transition-colors">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Ajouter une ligne</span>
                    </button>
                    <div class="text-xs text-primary/60">
                        Articles : <strong class="font-mono text-primary font-bold" x-text="lines.length"></strong>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold uppercase text-primary/70">Montant total estimé :</span>
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

    {{-- Modal Comparateur de Fournisseurs --}}
    <div x-show="showComparatorModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
         @keydown.escape.window="fermerComparateur()">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full p-6 space-y-4 max-h-[90vh] flex flex-col border border-secondary/20"
             @click.outside="fermerComparateur()">

            {{-- En-tête du modal --}}
            <div class="flex items-start justify-between gap-3 shrink-0 pb-3 border-b border-secondary/15">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="p-1.5 bg-primary/10 rounded-lg text-primary">
                            <i data-lucide="git-compare" class="w-4 h-4"></i>
                        </span>
                        <h3 class="text-base font-bold text-primary font-heading">Comparateur de Prix Fournisseurs</h3>
                    </div>
                    <p class="text-xs text-primary/60 mt-1">
                        Comparez les prix unitaires constatés aux livraisons et tarifs catalogue pour retenir le meilleur fournisseur.
                    </p>
                </div>
                <button type="button" @click="fermerComparateur()" class="text-primary/40 hover:text-primary p-1 rounded-lg hover:bg-secondary/10 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Sélecteur d'article en cours d'évaluation --}}
            <div class="shrink-0 bg-surface-light p-3.5 rounded-xl border border-secondary/15">
                <label class="block text-[11px] font-semibold text-primary/70 uppercase mb-1">Article évalué</label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                    <div class="min-w-0 flex-1">
                        <select x-model.number="comparatorItemId" @change="chargerComparateur(comparatorItemId)"
                                class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary font-semibold outline-none focus:border-primary">
                            <option value="">Sélectionnez un article à comparer…</option>
                            <template x-for="item in allItems" :key="'comp-item-' + item.id">
                                <option :value="item.id" x-text="item.name + ' (' + item.unit + ') — Cat: ' + item.category_name"></option>
                            </template>
                        </select>
                    </div>
                    <template x-if="comparatorItem">
                        <div class="flex items-center gap-2 text-xs text-primary/70 shrink-0 font-mono">
                            <span class="px-2 py-1 bg-white rounded border border-secondary/20 text-[11px]">
                                Stock : <strong class="text-primary" x-text="comparatorItem.current_stock ?? 0"></strong> <span x-text="comparatorItem.unit"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Filtre de période optionnel --}}
            <fieldset class="rounded-xl border border-secondary/20 p-3 shrink-0">
                <legend class="px-1 text-[10px] font-bold uppercase tracking-wider text-primary/50">Historique sur une période</legend>
                <div class="flex items-end gap-2">
                    <label class="flex-1 text-[11px] text-primary/70">Du
                        <input type="date" x-model="comparatorPeriod.du" class="mt-0.5 w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    </label>
                    <label class="flex-1 text-[11px] text-primary/70">Au
                        <input type="date" x-model="comparatorPeriod.au" class="mt-0.5 w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    </label>
                    <button type="button" @click="chargerComparateur(comparatorItemId)"
                            class="px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shrink-0">
                        Comparer
                    </button>
                </div>
            </fieldset>

            {{-- Contenu des comparaisons (liste scrollable) --}}
            <div class="flex-1 overflow-y-auto space-y-2.5 min-h-[180px] pr-1">
                <div x-show="comparatorLoading" class="py-12 text-center text-primary/50 flex flex-col items-center justify-center gap-2">
                    <div class="w-6 h-6 border-2 border-primary/20 border-t-primary rounded-full animate-spin"></div>
                    <span class="text-xs">Chargement des données de comparaison…</span>
                </div>

                <template x-if="!comparatorLoading && !comparatorItemId">
                    <div class="py-10 text-center text-primary/40">
                        <i data-lucide="package-search" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                        <p class="text-xs">Sélectionnez un article ci-dessus pour comparer les fournisseurs.</p>
                    </div>
                </template>

                <template x-if="!comparatorLoading && comparatorItemId && comparatorSuppliers.length === 0">
                    <div class="p-6 text-center bg-amber-50/70 border border-amber-200/60 rounded-xl text-amber-900">
                        <i data-lucide="info" class="w-6 h-6 text-amber-700 mx-auto mb-2"></i>
                        <p class="text-xs font-semibold">Aucun fournisseur n'a encore livré cet article.</p>
                        <p class="text-[11px] text-amber-800/80 mt-1">Vous pouvez choisir un fournisseur dans la liste déroulante ; son prix sera fixé au moment de la commande.</p>
                    </div>
                </template>

                <template x-for="f in (!comparatorLoading ? comparatorSuppliers : [])" :key="'f-' + f.id">
                    <div class="p-3.5 rounded-xl border transition-all"
                         :class="supplierId === f.id ? 'border-primary bg-primary/5 shadow-2xs' : 'border-secondary/20 hover:border-secondary/40 bg-white'">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sm text-primary" x-text="f.nom"></span>
                                    <span x-show="supplierId === f.id" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary text-white">Fournisseur actuel</span>
                                    <span x-show="f.moinsCher" class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-sky-300 bg-sky-50 text-sky-800">Moins cher</span>
                                    <span x-show="f.habituel" class="text-[10px] font-semibold px-2 py-0.5 rounded-full border border-emerald-300 bg-emerald-50 text-emerald-800">Habituel</span>
                                </div>

                                {{-- Prix affichés --}}
                                <div class="mt-1.5 text-xs text-primary/80 flex items-center gap-3 flex-wrap">
                                    <template x-if="f.derniere">
                                        <span>
                                            Dernière livraison : <strong class="font-mono text-primary font-bold" x-text="formatMoney(f.derniere.prix / 100) + ' FCFA'"></strong>
                                            <span class="text-primary/50 text-[11px]" x-text="'le ' + f.derniere.date"></span>
                                        </span>
                                    </template>
                                    <template x-if="!f.derniere">
                                        <span class="text-primary/50 text-[11px]" x-text="f.tarif ? 'Tarif catalogue : ' + formatMoney(f.tarif / 100) + ' FCFA' : 'Pas de prix d\'achat antérieur'"></span>
                                    </template>
                                    <template x-if="f.tarif && f.derniere">
                                        <span class="text-primary/50 text-[11px] font-mono" x-text="'(Tarif catalogue : ' + formatMoney(f.tarif / 100) + ' F)'"></span>
                                    </template>
                                </div>

                                {{-- Détail période --}}
                                <template x-if="f.periode">
                                    <p class="text-[11px] text-primary/60 mt-1 font-mono"
                                       x-text="f.periode.livraisons === 0 ? 'Aucune livraison sur cette période' : f.periode.livraisons + ' livraison(s) : de ' + formatMoney(f.periode.min / 100) + ' à ' + formatMoney(f.periode.max / 100) + ' F (dernière à ' + formatMoney(f.periode.dernier / 100) + ' F)'"></p>
                                </template>
                            </div>

                            <button type="button" @click="choisirFournisseurDepuisComparateur(f)"
                                    class="shrink-0 px-3.5 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-2xs flex items-center gap-1.5">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                <span x-text="supplierId === f.id ? 'Fournisseur retenu' : 'Choisir ce fournisseur'"></span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Pied du modal --}}
            <div class="shrink-0 pt-3 border-t border-secondary/15 flex items-center justify-end">
                <button type="button" @click="fermerComparateur()"
                        class="px-4 py-2 text-xs font-semibold text-primary/60 hover:text-primary transition-colors">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function orderReplenishmentForm(allItems, allSuppliers, initialSupplierId, initialItemId) {
        const baseUrl = @js(url('/economat/bons/articles'));

        return {
            allItems,
            allSuppliers,
            supplierId: initialSupplierId || '',
            lines: [],
            nextKey: 1,

            // État du modal Comparateur
            showComparatorModal: false,
            comparatorItemId: initialItemId || '',
            comparatorItem: null,
            comparatorLoading: false,
            comparatorSuppliers: [],
            comparatorPeriod: { du: '', au: '' },
            comparatorLineIdx: null,

            get currentSupplier() {
                return this.allSuppliers.find(s => s.id === this.supplierId) || null;
            },

            get supplierItems() {
                if (!this.supplierId) return [];
                // Le catalogue du fournisseur : les articles qui ont un prix chez lui.
                return this.allItems
                    .filter(i => i.tarifs && i.tarifs[this.supplierId])
                    .map(i => ({ ...i, price: i.tarifs[this.supplierId] }));
            },

            // Si un fournisseur est choisi : articles du fournisseur d'abord, puis le reste.
            // Sinon : tous les articles de l'économat disponibles immédiatement.
            get articlesProposes() {
                if (!this.supplierId) {
                    return this.allItems;
                }
                const siens = this.supplierItems.map(i => ({ ...i, duFournisseur: true }));
                const ids = new Set(siens.map(i => i.id));
                return [...siens, ...this.allItems.filter(i => !ids.has(i.id))];
            },

            onSupplierChange() {
                if (this.supplierId) {
                    // Met à jour les prix des lignes existantes pour ce fournisseur
                    this.lines.forEach(l => {
                        const item = this.allItems.find(i => i.id === l.itemId);
                        if (item) {
                            l.price = this.prixChezLeFournisseur(item);
                        }
                    });
                    if (this.lines.length === 0 && this.supplierItems.length > 0) {
                        this.addFromCatalog(this.supplierItems[0]);
                    }
                }
            },

            addFromCatalog(item) {
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
                    price: this.prixChezLeFournisseur(item),
                    qty: 1,
                    currentStock: item.current_stock,
                });
                this.scrollLinesToBottom();
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
                this.scrollLinesToBottom();
            },

            removeLine(idx) {
                this.lines.splice(idx, 1);
            },

            // Le tarif du fournisseur du bon, sinon le dernier prix payé.
            prixChezLeFournisseur(item) {
                if (!item) return 0;
                if (this.supplierId && item.tarifs && item.tarifs[this.supplierId]) {
                    return item.tarifs[this.supplierId];
                }
                return item.price || 0;
            },

            onItemChange(line, idx) {
                const item = this.allItems.find(i => i.id === line.itemId);
                if (item) {
                    line.name = item.name;
                    line.unit = item.unit;
                    line.currentStock = item.current_stock;
                    line.price = this.prixChezLeFournisseur(item);

                    // Si aucun fournisseur n'a encore été choisi, ouvrir le comparateur pour cet article
                    if (!this.supplierId) {
                        this.ouvrirComparateurPourLigne(line, idx);
                    }
                } else {
                    line.name = '';
                    line.unit = '';
                    line.price = 0;
                    line.currentStock = null;
                }
            },

            ouvrirComparateur(itemId = null, lineIdx = null) {
                this.comparatorLineIdx = lineIdx;
                this.comparatorPeriod = { du: '', au: '' };
                this.comparatorSuppliers = [];
                const targetId = itemId || (lineIdx !== null && this.lines[lineIdx] ? this.lines[lineIdx].itemId : null) || this.comparatorItemId || (this.allItems[0] ? this.allItems[0].id : null);
                this.comparatorItemId = targetId ? parseInt(targetId) : '';
                this.showComparatorModal = true;
                if (this.comparatorItemId) {
                    this.chargerComparateur(this.comparatorItemId);
                }
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                });
            },

            ouvrirComparateurPourLigne(line, idx) {
                if (!line.itemId) return;
                this.ouvrirComparateur(line.itemId, idx);
            },

            fermerComparateur() {
                this.showComparatorModal = false;
                this.comparatorLineIdx = null;
            },

            async chargerComparateur(itemId) {
                if (!itemId) {
                    this.comparatorItem = null;
                    this.comparatorSuppliers = [];
                    return;
                }
                this.comparatorItem = this.allItems.find(i => i.id === parseInt(itemId)) || null;
                this.comparatorLoading = true;
                const params = new URLSearchParams();
                if (this.comparatorPeriod.du) params.set('du', this.comparatorPeriod.du);
                if (this.comparatorPeriod.au) params.set('au', this.comparatorPeriod.au);

                try {
                    const res = await fetch(`${baseUrl}/${itemId}/fournisseurs?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    });
                    if (res.ok) {
                        const data = await res.json();
                        this.comparatorSuppliers = data.fournisseurs || [];
                    }
                } catch (err) {
                    console.error("Erreur chargement comparateur:", err);
                } finally {
                    this.comparatorLoading = false;
                    this.$nextTick(() => {
                        if (window.lucide) window.lucide.createIcons();
                    });
                }
            },

            choisirFournisseurDepuisComparateur(f) {
                if (!f) return;
                this.supplierId = f.id;

                // Prix unitaire en FCFA (dernière livraison ou tarif, converti depuis centimes si besoin)
                const prixCentimes = f.derniere ? f.derniere.prix : (f.tarif || 0);
                const prixFcfa = prixCentimes > 0 ? Math.round(prixCentimes / 100) : (this.comparatorItem?.price || 0);

                // Cas 1 : ouvert pour une ligne précise
                if (this.comparatorLineIdx !== null && this.lines[this.comparatorLineIdx]) {
                    const line = this.lines[this.comparatorLineIdx];
                    line.price = prixFcfa;
                } else if (this.comparatorItem) {
                    // Cas 2 : ouvert depuis le haut de page / sélecteur d'article
                    const existing = this.lines.find(l => l.itemId === this.comparatorItem.id);
                    if (existing) {
                        existing.price = prixFcfa;
                        existing.qty = (parseFloat(existing.qty) || 0) + 1;
                    } else {
                        this.lines.push({
                            key: this.nextKey++,
                            itemId: this.comparatorItem.id,
                            name: this.comparatorItem.name,
                            unit: this.comparatorItem.unit,
                            price: prixFcfa,
                            qty: 1,
                            currentStock: this.comparatorItem.current_stock,
                        });
                    }
                }

                // Met à jour les prix des autres lignes déjà présentes selon ce nouveau fournisseur
                this.lines.forEach(l => {
                    const item = this.allItems.find(i => i.id === l.itemId);
                    if (item && l.itemId !== (this.comparatorItem ? this.comparatorItem.id : null)) {
                        l.price = this.prixChezLeFournisseur(item);
                    }
                });

                this.fermerComparateur();
                this.scrollLinesToBottom();
            },

            scrollLinesToBottom() {
                this.$nextTick(() => {
                    if (window.lucide) window.lucide.createIcons();
                    if (this.$refs.linesBody) {
                        this.$refs.linesBody.scrollTop = this.$refs.linesBody.scrollHeight;
                    }
                });
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
                } else if (initialItemId) {
                    this.ouvrirComparateur(initialItemId);
                }
            }
        };
    }
</script>
@endpush
