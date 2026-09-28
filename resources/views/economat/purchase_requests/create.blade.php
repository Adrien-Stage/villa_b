@extends('layouts.hotel')

@section('title', 'Nouvelle demande d\'achat — Économat')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="purchaseRequestForm()">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.purchase_requests.index') }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-xl font-heading font-semibold text-primary">Exprimer un besoin d'approvisionnement</h1>
            <p class="text-sm text-primary/60 mt-0.5">Saisissez les articles souhaités pour validation avant commande fournisseur.</p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.purchase_requests.store') }}" class="space-y-6">
        @csrf

        {{-- Paramètres généraux --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Département demandeur *</label>
                    <select name="department" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                        @foreach($departments as $key => $lbl)
                            <option value="{{ $key }}" @selected(old('department', 'cuisine') === $key)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Degré d'urgence *</label>
                    <select name="priority" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
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
                <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Motif / Justification du besoin</label>
                <textarea name="purpose" rows="2" placeholder="Ex: Réapprovisionnement hebdomadaire cuisine + banquet samedi soir..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">{{ old('purpose') }}</textarea>
            </div>
        </div>

        {{-- Lignes d'articles --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-primary">Articles demandés</h2>
                    <p class="text-xs text-primary/50">Sélectionnez les articles du magasin et la quantité requise.</p>
                </div>
                <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/40 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                        <tr>
                            <th class="px-4 py-2.5 text-left w-1/3">Article</th>
                            <th class="px-4 py-2.5 text-center w-28">Stock actuel</th>
                            <th class="px-4 py-2.5 text-right w-32">Quantité voulue</th>
                            <th class="px-4 py-2.5 text-right w-36">Prix Est. unitaire (F)</th>
                            <th class="px-4 py-2.5 text-right w-36">Total Estimé (F)</th>
                            <th class="px-4 py-2.5 text-center w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        <template x-for="(line, index) in lines" :key="index">
                            <tr class="hover:bg-gray-50/40">
                                <td class="px-4 py-2.5">
                                    <select :name="'lines[' + index + '][stock_item_id]'" x-model="line.stock_item_id" @change="onItemChange(line)" required class="w-full px-2.5 py-1.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                                        <option value="">Sélectionner un article...</option>
                                        @foreach($items as $it)
                                            <option value="{{ $it->id }}"
                                                data-unit="{{ $it->unit }}"
                                                data-stock="{{ $it->current_stock }}"
                                                data-price="{{ $it->last_purchase_price > 0 ? $it->last_purchase_price / 100 : $it->average_cost / 100 }}">
                                                {{ $it->name }} ({{ $it->unit }})
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2.5 text-center font-mono text-xs text-primary/60">
                                    <span x-text="line.current_stock !== null ? line.current_stock + ' ' + (line.unit || '') : '—'"></span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <input type="number" step="0.001" min="0.001" :name="'lines[' + index + '][quantity_requested]'" x-model="line.quantity" required class="w-24 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary" placeholder="0">
                                        <span class="text-xs text-primary/40 font-mono" x-text="line.unit"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <input type="number" min="0" :name="'lines[' + index + '][estimated_unit_price]'" x-model="line.unit_price" class="w-28 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary" placeholder="P.U.">
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono font-bold text-primary">
                                    <span x-text="formatMoney(lineTotal(line))"></span> F
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    <button type="button" @click="removeLine(index)" class="text-red-500 hover:text-red-700 p-1 rounded transition-colors" x-show="lines.length > 1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="bg-gray-50/70 border-t border-secondary/15 font-semibold text-primary">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right">Montant estimé total de la demande :</td>
                            <td class="px-4 py-3 text-right font-mono text-base font-bold text-primary">
                                <span x-text="formatMoney(grandTotal())"></span> FCFA
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
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
    </form>
</div>

<script>
function purchaseRequestForm() {
    return {
        lines: [
            { stock_item_id: '', unit: '', current_stock: null, quantity: 1, unit_price: 0, notes: '' }
        ],
        addLine() {
            this.lines.push({ stock_item_id: '', unit: '', current_stock: null, quantity: 1, unit_price: 0, notes: '' });
            this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
        },
        removeLine(idx) {
            if (this.lines.length > 1) {
                this.lines.splice(idx, 1);
            }
        },
        onItemChange(line) {
            const select = event.target;
            const opt = select.options[select.selectedIndex];
            if (opt && opt.value) {
                line.unit = opt.getAttribute('data-unit') || '';
                line.current_stock = parseFloat(opt.getAttribute('data-stock') || 0);
                line.unit_price = Math.round(parseFloat(opt.getAttribute('data-price') || 0));
            } else {
                line.unit = '';
                line.current_stock = null;
                line.unit_price = 0;
            }
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
        }
    };
}
</script>
@endsection
