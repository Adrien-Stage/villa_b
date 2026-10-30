@extends('layouts.hotel')

@section('title', 'Approvisionner ' . $depot->name . ' — Économat')

@section('content')
@php
    $articlesJson = $articles->map(fn ($a) => [
        'id'    => $a->id,
        'name'  => $a->name . ($a->reference ? ' (' . $a->reference . ')' : ''),
        'unit'  => $a->shortUnit(),
        'stock' => (float) $a->current_stock,
        'cump'  => (int) $a->average_cost,
        // Transféré en cartons ou en paquets : on peut saisir dans chacun.
        'unites' => $a->unitesDeSaisie(),
        'decompose' => $a->stockDecompose(),
    ])->values();
    $lignesSaisies = collect(old('lines', []))->values()->map(fn ($l) => [
        'itemId' => (int) ($l['stock_item_id'] ?? 0) ?: '',
        'qty'    => $l['quantity'] ?? '',
        'packaging' => $l['packaging'] ?? '',
    ]);
    $champ = 'w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary';
@endphp

<div class="max-w-4xl mx-auto space-y-6"
     x-data="bonDeTransfert({{ Js::from($articlesJson) }}, {{ Js::from($lignesSaisies) }})">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.stores.show', $depot) }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors" aria-label="Retour au dépôt">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <span class="text-xs font-mono font-semibold text-primary/50 uppercase">Bon de transfert</span>
            <h1 class="text-xl font-heading font-semibold text-primary">Approvisionner le dépôt {{ $depot->name }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">
                Les articles quittent le magasin central et entrent au dépôt, au coût moyen. Ils restent du stock de l'hôtel :
                rien ne passe en charge maintenant ; l'inventaire du dépôt dira ce qui a été consommé.
            </p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.transfers.store', $depot) }}" class="space-y-6">
        @csrf

        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm flex flex-col">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between gap-2 shrink-0">
                <div>
                    <h2 class="text-sm font-semibold text-primary">Articles transférés</h2>
                    <p class="text-xs text-primary/50">Seuls les articles en stock au magasin central sont proposés ; la quantité ne dépasse pas le stock.</p>
                </div>
                <button type="button" @click="ajouter()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un article
                </button>
            </div>
            <div x-ref="lignesBody" class="p-5 space-y-3 max-h-[500px] overflow-y-auto">
                <template x-for="(ligne, idx) in lignes" :key="ligne.key">
                    <div class="grid grid-cols-12 gap-2.5 items-end p-3 rounded-lg border border-secondary/20 bg-surface-light/20">
                        <div class="col-span-12 md:col-span-7">
                            <label :for="'article-' + ligne.key" class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Article</label>
                            <x-article-picker source="articles" model="ligne.itemId" name-bind="`lines[${idx}][stock_item_id]`" id-bind="'article-' + ligne.key" size="sm" required
                                on-select="ligne.packaging = article(ligne)?.unit || ''"
                                detail="a => (a.decompose || (a.stock + ' ' + a.unit)) + ' en stock'" />
                        </div>
                        <div class="col-span-10 md:col-span-4">
                            <label :for="'qte-' + ligne.key" class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Quantité</label>
                            <input :id="'qte-' + ligne.key" type="number" step="0.001" min="0.001" :max="article(ligne) ? article(ligne).stock / facteur(ligne) : null" :name="`lines[${idx}][quantity]`" x-model="ligne.qty" required
                                   class="w-full px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary text-right font-mono font-bold">
                            <template x-if="(article(ligne)?.unites || []).length > 1">
                                <select :name="`lines[${idx}][packaging]`" x-model="ligne.packaging" :aria-label="'Unité de la ligne ' + (idx + 1)"
                                        class="mt-1 w-full px-2 py-1 text-[11px] border border-secondary/30 rounded-lg bg-white text-primary">
                                    <template x-for="u in article(ligne).unites" :key="u.nom">
                                        <option :value="u.nom" x-text="u.nom" :selected="u.nom === ligne.packaging"></option>
                                    </template>
                                </select>
                            </template>
                        </div>
                        <div class="col-span-2 md:col-span-1 flex justify-end">
                            <button type="button" @click="lignes.splice(idx, 1)" class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50" :aria-label="'Retirer la ligne ' + (idx + 1)">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <p class="col-span-12 text-[11px] text-primary/55" x-show="article(ligne)">
                            Au magasin central : <strong class="font-mono" x-text="article(ligne)?.decompose || (nombre(article(ligne)?.stock) + ' ' + (article(ligne)?.unit ?? ''))"></strong>
                            · valeur transférée : <strong class="font-mono" x-text="montant(valeur(ligne)) + ' F'"></strong>
                        </p>
                    </div>
                </template>
                <p class="text-xs text-primary/40 text-center py-4" x-show="lignes.length === 0">Aucun article. Cliquez sur « Ajouter un article ».</p>
            </div>
            <div class="px-5 py-3 border-t border-secondary/15 bg-surface-light flex items-center justify-between gap-3 text-sm shrink-0">
                <button type="button" @click="ajouter()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary/70 hover:text-primary transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un article
                </button>
                <div class="flex items-center gap-3">
                    <span class="text-primary/60">Valeur transférée (coût moyen)</span>
                    <strong class="font-mono text-primary" x-text="montant(total) + ' FCFA'"></strong>
                </div>
            </div>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm">
            <label for="observations" class="block text-xs font-semibold text-primary/80 mb-1.5">Observations</label>
            <textarea id="observations" name="notes" rows="2" maxlength="1000" placeholder="Ex : réassort hebdomadaire de la réserve" class="{{ $champ }}">{{ old('notes') }}</textarea>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('economat.stores.show', $depot) }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</a>
            <button type="submit" :disabled="lignes.length === 0"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark shadow-sm disabled:opacity-50">
                <i data-lucide="arrow-right-left" class="w-4 h-4"></i> Transférer au dépôt
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function bonDeTransfert(articles, lignesSaisies) {
        return {
            articles,
            lignes: [],
            cle: 1,
            init() {
                lignesSaisies.forEach(l => this.lignes.push({ key: this.cle++, ...l }));
                if (this.lignes.length === 0) this.ajouter();
            },
            ajouter() {
                this.lignes.push({ key: this.cle++, itemId: '', qty: '', packaging: '' });
                this.$nextTick(() => {
                    if (window.refreshLucideIcons) window.refreshLucideIcons();
                    if (this.$refs.lignesBody) {
                        this.$refs.lignesBody.scrollTop = this.$refs.lignesBody.scrollHeight;
                    }
                });
            },
            article(ligne) { return this.articles.find(a => a.id === ligne.itemId) || null; },
            // Unités de l'article dans l'unité choisie : 200 pour un carton de 200 pièces.
            facteur(ligne) { const u = (this.article(ligne)?.unites || []).find(u => u.nom === ligne.packaging); return u ? Number(u.facteur) : 1; },
            valeur(ligne) { const a = this.article(ligne); return a ? (parseFloat(ligne.qty) || 0) * this.facteur(ligne) * a.cump / 100 : 0; },
            get total() { return this.lignes.reduce((s, l) => s + this.valeur(l), 0); },
            nombre(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(v ?? 0); },
            montant(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(v)); },
        };
    }
</script>
@endpush
