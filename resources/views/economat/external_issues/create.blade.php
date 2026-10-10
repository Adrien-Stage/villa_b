@extends('layouts.hotel')

@section('title', 'Nouvelle sortie hors établissement — Économat')

@section('content')
@include('economat.external_issues.partials.police-signature')
@php
    $articlesJson = $articles->map(fn ($a) => [
        'id'    => $a->id,
        'name'  => $a->name . ($a->reference ? ' (' . $a->reference . ')' : ''),
        'unit'  => $a->unit,
        'stock' => (float) $a->current_stock,
        'cump'  => (int) $a->average_cost,
        // Sorti en cartons ou en paquets : on peut saisir dans chacun.
        'unites' => $a->unitesDeSaisie(),
        'decompose' => $a->stockDecompose(),
    ])->values();
    $lignesSaisies = collect(old('lines', []))->values()->map(fn ($l) => [
        'itemId' => (int) ($l['stock_item_id'] ?? 0) ?: '',
        'qty'    => $l['quantity'] ?? '',
        'packaging' => $l['packaging'] ?? '',
        'notes'  => $l['notes'] ?? '',
    ]);
    $champ = 'w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary';
@endphp

<div class="max-w-4xl mx-auto space-y-6"
     x-data="sortieExterne({{ Js::from($articlesJson) }}, {{ Js::from($lignesSaisies) }}, @js(old('reason', '')), @js($avecRetour))">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.external_issues.index') }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors" aria-label="Retour aux sorties">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <span class="text-xs font-mono font-semibold text-primary/50 uppercase">Bon de sortie hors établissement</span>
            <h1 class="text-xl font-heading font-semibold text-primary">Sortie de matériel qui ne sert pas l'établissement</h1>
            <p class="text-sm text-primary/60 mt-0.5">En validant, vous sortez ces articles du stock. Le bon est signé au nom de la personne qui emporte le matériel.</p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.external_issues.store') }}" class="space-y-6">
        @csrf

        {{-- La personne qui vient chercher le matériel --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
                <i data-lucide="user-round" class="w-4 h-4 text-primary/60"></i> Qui emporte le matériel
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="beneficiaire" class="block text-xs font-semibold text-primary/80 mb-1.5">Nom et prénom <span class="text-red-500">*</span></label>
                    <input id="beneficiaire" type="text" name="beneficiary_name" value="{{ old('beneficiary_name') }}" required maxlength="160" x-model="nom"
                           placeholder="Ex : Jean-Paul Mbarga" class="{{ $champ }}">
                </div>
                <div>
                    <label for="structure" class="block text-xs font-semibold text-primary/80 mb-1.5">Structure ou qualité</label>
                    <input id="structure" type="text" name="beneficiary_organisation" value="{{ old('beneficiary_organisation') }}" maxlength="160"
                           placeholder="Ex : Atelier Froid Service, chauffeur du propriétaire" class="{{ $champ }}">
                </div>
                <div>
                    <label for="telephone" class="block text-xs font-semibold text-primary/80 mb-1.5">Téléphone</label>
                    <input id="telephone" type="text" name="beneficiary_phone" value="{{ old('beneficiary_phone') }}" maxlength="40" placeholder="+237 6…" class="{{ $champ }}">
                </div>
                <div>
                    <label for="piece" class="block text-xs font-semibold text-primary/80 mb-1.5">Pièce d'identité (type et numéro)</label>
                    <input id="piece" type="text" name="beneficiary_id_document" value="{{ old('beneficiary_id_document') }}" maxlength="80" placeholder="Ex : CNI 112233445" class="{{ $champ }}">
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-lg border border-dashed border-secondary/30 bg-gray-50 px-4 py-2" x-show="nom.trim() !== ''" x-cloak>
                <span class="text-[11px] uppercase tracking-wider text-primary/50">Signature sur le bon</span>
                <span class="font-signature text-3xl text-blue-900 leading-none" x-text="signature()"></span>
            </div>
        </div>

        {{-- Pourquoi --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="motif" class="block text-xs font-semibold text-primary/80 mb-1.5">Motif <span class="text-red-500">*</span></label>
                    <select id="motif" name="reason" required x-model="motif" class="{{ $champ }}">
                        <option value="">Choisir…</option>
                        @foreach($motifs as $cle => $libelle)
                            <option value="{{ $cle }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sortie-le" class="block text-xs font-semibold text-primary/80 mb-1.5">Sortie le</label>
                    <input id="sortie-le" type="datetime-local" name="issued_at" value="{{ old('issued_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}" class="{{ $champ }}">
                </div>
                <div x-show="avecRetour.includes(motif)" x-cloak>
                    <label for="retour" class="block text-xs font-semibold text-primary/80 mb-1.5">Retour prévu le</label>
                    <input id="retour" type="date" name="expected_return_at" value="{{ old('expected_return_at') }}" min="{{ today()->toDateString() }}" class="{{ $champ }}">
                </div>
            </div>
            <div>
                <label for="observations" class="block text-xs font-semibold text-primary/80 mb-1.5">Observations</label>
                <textarea id="observations" name="notes" rows="2" maxlength="1000" placeholder="Ex : climatiseur de la chambre 204 envoyé pour recharge de gaz" class="{{ $champ }}">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- Articles --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-primary">Articles qui sortent</h2>
                    <p class="text-xs text-primary/50">Seuls les articles en stock sont proposés ; la quantité ne dépasse pas le stock.</p>
                </div>
                <button type="button" @click="ajouter()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter un article
                </button>
            </div>
            <div class="p-5 space-y-3">
                <template x-for="(ligne, idx) in lignes" :key="ligne.key">
                    <div class="grid grid-cols-12 gap-2.5 items-end p-3 rounded-lg border border-secondary/20 bg-surface-light/20">
                        <div class="col-span-12 md:col-span-5">
                            <label :for="'article-' + ligne.key" class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Article</label>
                            <select :id="'article-' + ligne.key" :name="`lines[${idx}][stock_item_id]`" x-model.number="ligne.itemId" @change="ligne.packaging = article(ligne)?.unit || ''" required
                                    class="w-full px-2.5 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                                <option value="">Choisir un article…</option>
                                <template x-for="a in articles" :key="a.id">
                                    <option :value="a.id" x-text="a.name" :selected="a.id === ligne.itemId"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-span-4 md:col-span-2">
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
                        <div class="col-span-8 md:col-span-4">
                            <label :for="'note-' + ligne.key" class="block text-[10px] font-semibold text-primary/60 mb-0.5 uppercase">Note</label>
                            <input :id="'note-' + ligne.key" type="text" :name="`lines[${idx}][notes]`" x-model="ligne.notes" maxlength="255" placeholder="N° de série, état…"
                                   class="w-full px-2 py-1.5 text-xs border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                        </div>
                        <div class="col-span-12 md:col-span-1 flex justify-end">
                            <button type="button" @click="lignes.splice(idx, 1)" class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50" aria-label="Retirer la ligne">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                        <p class="col-span-12 text-[11px] text-primary/55" x-show="article(ligne)">
                            En stock : <strong class="font-mono" x-text="article(ligne)?.decompose || (nombre(article(ligne)?.stock) + ' ' + (article(ligne)?.unit ?? ''))"></strong>
                            · valeur de la sortie : <strong class="font-mono" x-text="montant(valeur(ligne)) + ' F'"></strong>
                        </p>
                    </div>
                </template>
                <p class="text-xs text-primary/40 text-center py-4" x-show="lignes.length === 0">Aucun article. Cliquez sur « Ajouter un article ».</p>
            </div>
            <div class="px-5 py-3 border-t border-secondary/15 bg-surface-light flex justify-end gap-3 text-sm">
                <span class="text-primary/60">Valeur totale (coût moyen)</span>
                <strong class="font-mono text-primary" x-text="montant(total) + ' FCFA'"></strong>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <a href="{{ route('economat.external_issues.index') }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</a>
            <button type="submit" :disabled="lignes.length === 0"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark shadow-sm disabled:opacity-50">
                <i data-lucide="check" class="w-4 h-4"></i> Valider la sortie
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function sortieExterne(articles, lignesSaisies, motif, avecRetour) {
        return {
            articles, motif, avecRetour,
            nom: @js(old('beneficiary_name', '')),
            lignes: [],
            cle: 1,
            init() {
                lignesSaisies.forEach(l => this.lignes.push({ key: this.cle++, ...l }));
                if (this.lignes.length === 0) this.ajouter();
            },
            ajouter() {
                this.lignes.push({ key: this.cle++, itemId: '', qty: '', packaging: '', notes: '' });
                this.$nextTick(() => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
            },
            article(ligne) { return this.articles.find(a => a.id === ligne.itemId) || null; },
            // Unités de l'article dans l'unité choisie : 200 pour un carton de 200 pièces.
            facteur(ligne) { const u = (this.article(ligne)?.unites || []).find(u => u.nom === ligne.packaging); return u ? Number(u.facteur) : 1; },
            valeur(ligne) { const a = this.article(ligne); return a ? (parseFloat(ligne.qty) || 0) * this.facteur(ligne) * a.cump / 100 : 0; },
            get total() { return this.lignes.reduce((s, l) => s + this.valeur(l), 0); },
            // Même règle que le serveur : le premier nom, sans civilité, chaque partie en capitale (« Jean-Paul »).
            signature() {
                const parts = this.nom.trim().replace(/^(m\.|mr\.|dr\.|mme\.|mlle\.)\s+/i, '').split(/\s+/).filter(Boolean);
                if (!parts.length) return '';
                return parts[0].toLocaleLowerCase('fr').replace(/(^|[^\p{L}])(\p{L})/gu, (m, avant, lettre) => avant + lettre.toLocaleUpperCase('fr'));
            },
            nombre(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(v ?? 0); },
            montant(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(v)); },
        };
    }
</script>
@endpush

