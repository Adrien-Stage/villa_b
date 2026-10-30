@extends('layouts.hotel')

@section('title', 'Modifier ' . $request->number . ' — Économat')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="purchaseRequestEditForm({{ Js::from($items->map(fn ($it) => [
    'id' => $it->id, 'name' => $it->name, 'reference' => $it->reference, 'unit' => $it->shortUnit(),
    'stock' => (float) $it->current_stock,
    // Le prix de l'article chez chaque fournisseur : la demande prend celui du fournisseur choisi.
    'tarifs' => (object) $it->tarifsFcfa(),
])->values()) }}, {{ Js::from($request->lines->map(fn ($l) => [
    'id' => $l->id,
    'stock_item_id' => $l->stock_item_id,
    'unit' => $l->item?->shortUnit() ?? '',
    'current_stock' => (float) ($l->item?->current_stock ?? 0),
    'quantity' => (float) $l->quantity_requested,
    'unit_price' => (float) ($l->estimated_unit_price / 100),
    'tarifs' => (object) ($l->item?->tarifsFcfa() ?? []),
    'prix_saisi' => (float) ($l->estimated_unit_price / 100),
    'notes' => $l->notes ?? '',
])->values()) }}, {{ Js::from((string) old('supplier_id', $request->supplier_id)) }})">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.purchase_requests.show', $request) }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Modifier la demande d'achat {{ $request->number }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">Ajustez les quantités, articles ou informations de la demande.</p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.purchase_requests.update', $request) }}" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- Paramètres généraux --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
            <div class="grid grid-cols-1 {{ $choisitDepartement ? 'md:grid-cols-4' : 'md:grid-cols-3' }} gap-4">
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Fournisseur *</label>
                    <select name="supplier_id" required x-model="supplierId" @change="appliquerFournisseur()" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                        <option value="">Sélectionner un fournisseur...</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $request->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($choisitDepartement)
                    <div>
                        <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Département demandeur *</label>
                        <select name="department" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                            @foreach($departments as $key => $lbl)
                                <option value="{{ $key }}" @selected(old('department', $request->department) === $key)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Degré d'urgence *</label>
                    <select name="priority" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                        <option value="low" @selected(old('priority', $request->priority) === 'low')>Basse (Anticipation)</option>
                        <option value="normal" @selected(old('priority', $request->priority) === 'normal')>Normale</option>
                        <option value="urgent" @selected(old('priority', $request->priority) === 'urgent')>Urgente (Rupture imminente)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Demandeur d'origine</label>
                    <input type="text" readonly disabled value="{{ $request->requestedBy?->name ?? 'Demandeur' }}" class="w-full px-3 py-2 text-sm border border-secondary/20 bg-gray-50 rounded-lg text-primary/70">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Motif / Justification du besoin</label>
                <textarea name="purpose" rows="2" placeholder="Ex: Réapprovisionnement hebdomadaire cuisine..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">{{ old('purpose', $request->purpose) }}</textarea>
            </div>
        </div>

        {{-- Lignes d'articles --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm flex flex-col">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between shrink-0">
                <div>
                    <h2 class="text-sm font-semibold text-primary">Articles demandés</h2>
                    <p class="text-xs text-primary/50">Modifiez les quantités voulues ou ajustez la sélection d'articles. Chaque article prend le prix du fournisseur de la demande à sa dernière livraison.</p>
                </div>
                <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                </button>
            </div>

            <div x-ref="linesBody" class="overflow-x-auto max-h-[500px] overflow-y-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/95 sticky top-0 z-10 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10 backdrop-blur-sm">
                        <tr>
                            <th class="px-4 py-2.5 text-left w-1/3">Article</th>
                            <th class="px-4 py-2.5 text-right w-24">Stock actuel</th>
                            <th class="px-3 py-2.5 text-center w-16">Unité</th>
                            <th class="px-4 py-2.5 text-right w-28">Quantité voulue</th>
                            <th class="px-4 py-2.5 text-right w-36">Prix du fournisseur (F)</th>
                            <th class="px-4 py-2.5 text-right w-32">Total Estimé (F)</th>
                            <th class="px-4 py-2.5 text-center w-20"><span class="sr-only">Retirer</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        <template x-for="(line, index) in lines" :key="line.uid">
                            <tr class="hover:bg-gray-50/40">
                                <td class="px-4 py-2.5">
                                    <input type="hidden" :name="'lines[' + index + '][id]'" :value="line.id || ''">
                                    <x-article-picker source="catalogue" model="line.stock_item_id" name-bind="'lines[' + index + '][stock_item_id]'" size="sm" required
                                        on-select="onItemChange(line, $event.detail.article)" aria-label="Article" />
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-xs text-primary/60">
                                    <span x-text="line.current_stock !== null ? formatNumber(line.current_stock) : '—'"></span>
                                </td>
                                <td class="px-3 py-2.5 text-center font-mono font-bold text-xs text-primary/70">
                                    <span x-text="line.unit || '—'"></span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <input type="number" step="0.001" min="0.001" :name="'lines[' + index + '][quantity_requested]'" x-model="line.quantity" required class="w-24 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary" placeholder="0">
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono">
                                    <template x-if="!line.hors_catalogue">
                                        <div class="inline-flex flex-col items-end">
                                            <input type="hidden" :name="'lines[' + index + '][estimated_unit_price]'" :value="line.unit_price">
                                            <span class="font-bold text-primary" x-text="formatMoney(line.unit_price) + ' F'"></span>
                                            <span class="text-[10px] text-primary/50" x-show="line.stock_item_id && supplierId">Tarif du fournisseur</span>
                                        </div>
                                    </template>
                                    {{-- Absent du catalogue du fournisseur : le prix se saisit, et la demande le signale. --}}
                                    <template x-if="line.hors_catalogue">
                                        <div class="inline-flex flex-col items-end gap-0.5">
                                            <input type="number" min="1" step="1" :name="'lines[' + index + '][estimated_unit_price]'" x-model="line.prix_saisi"
                                                   @input="line.unit_price = parseFloat(line.prix_saisi) || 0" required
                                                   :aria-label="'Prix estimé de la ligne ' + (index + 1)"
                                                   class="w-24 px-2 py-1.5 text-sm border border-amber-300 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary" placeholder="Prix">
                                            <span class="text-[10px] text-amber-700 bg-amber-50 px-1 rounded">Hors catalogue de ce fournisseur</span>
                                        </div>
                                    </template>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-bold text-primary">
                                    <span x-text="formatMoney(lineTotal(line))"></span> F
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button type="button" @click="removeLine(index)"
                                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
                                            :aria-label="'Retirer la ligne ' + (index + 1)" title="Retirer cet article">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                        <span class="sr-only sm:not-sr-only">Retirer</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3 border-t border-secondary/15 bg-gray-50/70 flex items-center justify-between text-sm shrink-0">
                <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary/70 hover:text-primary transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                </button>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-primary/60">Montant estimé total de la demande :</span>
                    <span class="font-mono text-base font-bold text-primary" x-text="formatMoney(grandTotal()) + ' FCFA'"></span>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('economat.purchase_requests.show', $request) }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">
                Annuler
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="save" class="w-4 h-4"></i> Enregistrer les modifications
            </button>
        </div>
    </form>
</div>

<script>
function purchaseRequestEditForm(catalogue, initialLines, fournisseurInitial) {
    return {
        supplierId: fournisseurInitial || '',
        init() { this.appliquerFournisseur(); },
        catalogue,
        prochain: (initialLines || []).length + 1,
        lines: initialLines && initialLines.length > 0
            ? initialLines.map((l, i) => ({ ...l, uid: i, hors_catalogue: false }))
            : [{ uid: 0, id: null, stock_item_id: '', unit: '', current_stock: null, quantity: 1, unit_price: 0, tarifs: {}, prix_saisi: '', hors_catalogue: false, notes: '' }],
        nouvelleLigne() {
            return { uid: this.prochain++, id: null, stock_item_id: '', unit: '', current_stock: null, quantity: 1, unit_price: 0, tarifs: {}, prix_saisi: '', hors_catalogue: false, notes: '' };
        },
        addLine() {
            this.lines.push(this.nouvelleLigne());
            this.$nextTick(() => {
                window.refreshLucideIcons?.();
                if (this.$refs.linesBody) {
                    this.$refs.linesBody.scrollTop = this.$refs.linesBody.scrollHeight;
                }
            });
        },
        // Chaque ligne s'efface ; la dernière laisse une ligne vide à remplir.
        removeLine(idx) {
            this.lines.splice(idx, 1);
            if (this.lines.length === 0) {
                this.lines.push(this.nouvelleLigne());
            }
        },
        // Le prix d'une ligne : le tarif du fournisseur choisi, sinon un prix saisi.
        prixLigne(line) {
            const tarif = line.tarifs ? line.tarifs[this.supplierId] : undefined;
            if (line.stock_item_id && this.supplierId && tarif) {
                line.unit_price = tarif;
                line.hors_catalogue = false;
            } else {
                line.hors_catalogue = !!(line.stock_item_id && this.supplierId);
                line.unit_price = parseFloat(line.prix_saisi) || 0;
            }
        },
        appliquerFournisseur() {
            this.lines.forEach(l => this.prixLigne(l));
        },
        onItemChange(line, article) {
            if (article) {
                line.unit = article.unit || '';
                line.current_stock = parseFloat(article.stock || 0);
                line.tarifs = article.tarifs || {};
            } else {
                line.unit = '';
                line.current_stock = null;
                line.tarifs = {};
            }
            line.prix_saisi = '';
            this.prixLigne(line);
        },
        lineTotal(line) {
            const q = parseFloat(line.quantity) || 0;
            const p = parseFloat(line.unit_price) || 0;
            return Math.round(q * p);
        },
        grandTotal() {
            return this.lines.reduce((sum, l) => sum + this.lineTotal(l), 0);
        },
        formatMoney(amount) {
            return new Intl.NumberFormat('fr-FR').format(amount || 0);
        },
        formatNumber(num) {
            if (num === null || num === undefined) return '—';
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(num);
        }
    };
}
</script>
@endsection
