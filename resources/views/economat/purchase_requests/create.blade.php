@extends('layouts.hotel')

@section('title', 'Nouvelle demande d\'achat — Économat')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="purchaseRequestForm({{ Js::from($items->map(fn ($it) => [
    'id' => $it->id, 'name' => $it->name, 'reference' => $it->reference, 'unit' => $it->shortUnit(),
    'stock' => (float) $it->current_stock,
    // Une estimation : le dernier prix payé. Le fournisseur et son prix se choisissent aux achats.
    'prix' => (int) round(((int) $it->last_purchase_price) / 100),
])->values()) }})">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.purchase_requests.index') }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors" aria-label="Retour aux demandes d'achat">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Exprimer un besoin d'approvisionnement</h1>
            <p class="text-sm text-primary/60 mt-0.5">Saisissez les articles et les quantités voulues. Le responsable des achats vérifie la demande, puis choisit le fournisseur de chaque article en la passant en bons de commande.</p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.purchase_requests.store') }}" class="space-y-6">
        @csrf

        <div class="space-y-6 min-w-0">
            {{-- Paramètres généraux --}}
            <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
                <div class="grid grid-cols-1 {{ $choisitDepartement ? 'md:grid-cols-3' : 'md:grid-cols-2' }} gap-4">
                    @if($choisitDepartement)
                        <div>
                            <label for="demande-departement" class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Département demandeur *</label>
                            <select id="demande-departement" name="department" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                                @foreach($departments as $key => $lbl)
                                    <option value="{{ $key }}" @selected(old('department', 'cuisine') === $key)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label for="demande-urgence" class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Degré d'urgence *</label>
                        <select id="demande-urgence" name="priority" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                            <option value="low" @selected(old('priority') === 'low')>Basse (Anticipation)</option>
                            <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normale</option>
                            <option value="urgent" @selected(old('priority') === 'urgent')>Urgente (Rupture imminente)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Demandeur</label>
                        <input type="text" readonly disabled value="{{ auth()->user()->name }}" class="w-full px-3 py-2 text-sm border border-secondary/20 bg-gray-50 rounded-lg text-primary/70">
                    </div>
                </div>

                <div>
                    <label for="demande-motif" class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Motif / Justification du besoin</label>
                    <textarea id="demande-motif" name="purpose" rows="2" placeholder="Ex: Réapprovisionnement hebdomadaire cuisine + banquet samedi soir..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">{{ old('purpose') }}</textarea>
                </div>
            </div>

            {{-- Lignes d'articles --}}
            <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm flex flex-col">
                <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between shrink-0">
                    <div>
                        <h2 class="text-sm font-semibold text-primary">Articles demandés</h2>
                        <p class="text-xs text-primary/50">Le prix affiché est une estimation : le dernier prix payé pour l'article.</p>
                    </div>
                    <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                    </button>
                </div>

                <div x-ref="linesBody" class="overflow-x-auto max-h-[560px] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50/95 sticky top-0 z-10 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10 backdrop-blur-sm">
                            <tr>
                                <th class="px-3 py-2.5 text-left min-w-[12rem]">Article</th>
                                <th class="px-3 py-2.5 text-right w-16">Stock</th>
                                <th class="px-3 py-2.5 text-right w-24">Quantité</th>
                                <th class="px-3 py-2.5 text-right w-32">Prix estimé (F)</th>
                                <th class="px-3 py-2.5 text-right w-24">Total (F)</th>
                                <th class="px-2 py-2.5 text-center w-10"><span class="sr-only">Retirer</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-secondary/10">
                            <template x-for="(line, index) in lines" :key="line.uid">
                                <tr class="hover:bg-gray-50/40">
                                    <td class="px-3 py-2.5">
                                        <x-article-picker source="catalogue" model="line.stock_item_id" name-bind="'lines[' + index + '][stock_item_id]'" size="sm" required
                                            on-select="onItemChange(line, $event.detail.article)" aria-label="Article" />
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-xs text-primary/60 whitespace-nowrap">
                                        <span x-text="line.current_stock !== null ? formatNumber(line.current_stock) + ' ' + line.unit : '—'"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right">
                                        <input type="number" step="0.001" min="0.001" :name="'lines[' + index + '][quantity_requested]'" x-model="line.quantity" required
                                               :aria-label="'Quantité de la ligne ' + (index + 1)"
                                               class="w-20 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary" placeholder="0">
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono text-primary/80 whitespace-nowrap">
                                        <span x-text="line.stock_item_id ? (line.unit_price > 0 ? formatMoney(line.unit_price) + ' F' : 'Jamais acheté') : '—'"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-mono font-bold text-primary whitespace-nowrap">
                                        <span x-text="formatMoney(lineTotal(line))"></span> F
                                    </td>
                                    <td class="px-2 py-2.5 text-center">
                                        <button type="button" @click="removeLine(index)"
                                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 hover:text-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-300"
                                                :aria-label="'Retirer la ligne ' + (index + 1)" title="Retirer cet article">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                            <span class="sr-only">Retirer</span>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-3 border-t border-secondary/15 bg-gray-50/70 space-y-2 text-sm shrink-0">
                    <div class="flex items-center justify-between">
                        <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary/70 hover:text-primary transition-colors">
                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                        </button>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-primary/60">Montant estimé :</span>
                            <span class="font-mono text-base font-bold text-primary" x-text="formatMoney(grandTotal()) + ' FCFA'"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('economat.purchase_requests.index') }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">
                    Annuler
                </a>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="send" class="w-4 h-4"></i> Soumettre la demande d'achat
                </button>
            </div>
        </div>

    </form>
</div>

<script>
function purchaseRequestForm(catalogue) {
    const ligneVide = (uid) => ({ uid, stock_item_id: '', unit: '', current_stock: null, quantity: 1, unit_price: 0, notes: '' });

    return {
        catalogue,
        prochain: 1,
        lines: [ligneVide(0)],
        nouvelleLigne() {
            return ligneVide(this.prochain++);
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
        onItemChange(line, article) {
            line.unit = article ? (article.unit || '') : '';
            line.current_stock = article ? parseFloat(article.stock || 0) : null;
            line.unit_price = article ? (parseFloat(article.prix) || 0) : 0;
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
            return new Intl.NumberFormat('fr-FR').format(Math.round(amount || 0));
        },
        formatNumber(num) {
            if (num === null || num === undefined) return '—';
            return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 }).format(num);
        }
    };
}
</script>
@endsection
