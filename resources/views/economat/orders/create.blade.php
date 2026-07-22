@extends('layouts.hotel')

@section('title', 'Nouveau bon de commande')

@section('content')
<div class="max-w-4xl mx-auto"
     x-data="orderForm({{ Js::from($items->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->unit, 'price' => (int) ($i->last_purchase_price / 100), 'supplier_id' => $i->supplier_id])->values()) }})">
    <a href="{{ route('economat.orders.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/50 hover:text-primary mb-4">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux bons
    </a>

    <h1 class="text-xl font-heading font-semibold text-primary mb-6">Nouveau bon de commande</h1>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.orders.store') }}">
        @csrf
        <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-1">
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Fournisseur <span class="text-red-500">*</span></label>
                    <select name="supplier_id" x-model="supplierId" required class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                        <option value="">Sélectionner…</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @if(!$supplier->canReceiveOrdersByEmail()) data-noemail="1" @endif>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Livraison souhaitée</label>
                    <input type="date" name="expected_at" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-medium text-primary/70 mb-1.5">Note au fournisseur</label>
                    <input type="text" name="notes" maxlength="1000" class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                </div>
            </div>
        </div>

        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-secondary/20 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-primary">Articles commandés</h2>
                <button type="button" @click="addLine()" class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i> Ajouter une ligne
                </button>
            </div>
            <div class="p-5 space-y-3">
                <template x-if="lines.length === 0">
                    <p class="text-sm text-primary/40 text-center py-4">Aucun article. Cliquez sur « Ajouter une ligne ».</p>
                </template>
                <template x-for="(line, idx) in lines" :key="line.key">
                    <div class="grid grid-cols-12 gap-2 items-center">
                        <div class="col-span-6">
                            <select :name="`lines[${idx}][stock_item_id]`" x-model.number="line.itemId" @change="onItemChange(line)" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary">
                                <option value="">Article…</option>
                                <template x-for="it in items" :key="it.id"><option :value="it.id" x-text="it.name"></option></template>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <input type="number" step="0.001" min="0.001" :name="`lines[${idx}][quantity]`" x-model.number="line.qty" placeholder="Qté" required class="w-full px-2 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary text-right">
                        </div>
                        <div class="col-span-3">
                            <div class="relative">
                                <input type="number" min="0" :name="`lines[${idx}][unit_price]`" x-model.number="line.price" placeholder="P.U." required class="w-full px-2 py-2 pr-8 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary text-right">
                                <span class="absolute right-2 top-2 text-[10px] text-primary/40">F</span>
                            </div>
                        </div>
                        <div class="col-span-1 text-right">
                            <button type="button" @click="removeLine(idx)" class="text-red-500 hover:text-red-700"><i data-lucide="x" class="w-4 h-4"></i></button>
                        </div>
                    </div>
                </template>
            </div>
            <div class="px-5 py-3 border-t border-secondary/20 bg-gray-50 flex justify-between items-center">
                <span class="text-xs text-primary/50">Total estimé</span>
                <span class="text-lg font-bold text-primary" x-text="new Intl.NumberFormat('fr-FR').format(total) + ' FCFA'"></span>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('economat.orders.index') }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">Annuler</a>
            <button type="submit" :disabled="lines.length === 0 || !supplierId" class="px-5 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark disabled:opacity-50 disabled:cursor-not-allowed">
                Créer le bon
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function orderForm(items) {
        return {
            items,
            supplierId: '',
            lines: [],
            nextKey: 1,
            addLine() { this.lines.push({ key: this.nextKey++, itemId: '', qty: 1, price: 0 }); },
            removeLine(idx) { this.lines.splice(idx, 1); },
            onItemChange(line) {
                // Pré-remplit le prix avec le dernier prix d'achat connu, gain de saisie.
                const it = this.items.find(i => i.id === line.itemId);
                if (it && (!line.price || line.price === 0)) line.price = it.price;
            },
            get total() {
                return this.lines.reduce((s, l) => s + (parseFloat(l.qty) || 0) * (parseInt(l.price) || 0), 0);
            },
            init() { this.addLine(); },
        };
    }
</script>
@endpush
