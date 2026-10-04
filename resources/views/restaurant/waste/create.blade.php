@extends('layouts.hotel')

@section('title', 'Déclarer une perte / Déchet — Cuisine')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div>
            <a href="{{ route('restaurant.waste.index') }}" class="inline-flex items-center gap-1.5 text-xs text-primary/60 hover:text-primary mb-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Retour aux pertes & déchets
            </a>
            <h1 class="font-heading text-2xl font-semibold text-primary">Déclarer une perte ou un déchet</h1>
            <p class="text-sm text-primary/50 mt-0.5">Sortie de matière non facturée : casse, aliment avarié, plat brûlé, repas du personnel...</p>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            <p class="font-semibold mb-1">Veuillez corriger les erreurs suivantes :</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('restaurant.waste.store') }}" class="bg-white rounded-xl shadow-sm border border-secondary/15 p-6 space-y-5">
        @csrf

        {{-- Sélection de l'ingrédient --}}
        <div>
            <label for="restaurant_pantry_item_id" class="block text-xs font-semibold text-primary mb-1.5">
                Ingrédient ou préparation concernée <span class="text-red-500">*</span>
            </label>
            <select name="restaurant_pantry_item_id" id="restaurant_pantry_item_id" required
                class="w-full text-sm rounded-lg border-secondary/25 focus:border-primary focus:ring-primary"
                onchange="updateItemDetails()">
                <option value="">-- Sélectionnez un article du garde-manger --</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}"
                        data-unit="{{ $item->unit }}"
                        data-stock="{{ (float) $item->current_stock }}"
                        data-cost="{{ (float) $item->average_cost / 100 }}"
                        @selected(old('restaurant_pantry_item_id') == $item->id)>
                        @if(app(\App\Services\RestaurantContext::class)->vueEnsemble(auth()->user()) && $item->pointOfSale){{ $item->pointOfSale->name }} — @endif{{ $item->name }} (Stock actuel: {{ rtrim(rtrim(number_format((float) $item->current_stock, 3, ',', ' '), '0'), ',') }} {{ $item->unit }} — Coût moy: {{ number_format((float) $item->average_cost / 100, 2, ',', ' ') }} FCFA/{{ $item->unit }})
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Quantité mise au rebut --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="quantity" class="block text-xs font-semibold text-primary mb-1.5">
                    Quantité mise au rebut <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="number" step="0.001" min="0.001" name="quantity" id="quantity"
                        value="{{ old('quantity') }}" required
                        placeholder="Ex: 2.5"
                        oninput="calculateValuation()"
                        class="w-full text-sm rounded-lg border-secondary/25 focus:border-primary focus:ring-primary pr-14">
                    <span id="unit-badge" class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-primary/50 font-medium">
                        unité
                    </span>
                </div>
            </div>

            {{-- Aperçu de la valorisation --}}
            <div class="bg-secondary/5 rounded-lg p-3 border border-secondary/15 flex flex-col justify-center">
                <span class="text-xs text-primary/60 font-medium">Impact valorisé estimé :</span>
                <span id="estimated-cost" class="text-lg font-heading font-semibold text-red-600 mt-0.5">
                    0 FCFA
                </span>
                <span id="stock-after-hint" class="text-[11px] text-primary/40 mt-0.5">
                    Stock après déduction : —
                </span>
            </div>
        </div>

        {{-- Motif & Département --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="reason" class="block text-xs font-semibold text-primary mb-1.5">
                    Motif normalisé <span class="text-red-500">*</span>
                </label>
                <select name="reason" id="reason" required class="w-full text-sm rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                    <option value="">-- Choisir un motif --</option>
                    @foreach($reasonLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('reason', 'spoilage') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="department" class="block text-xs font-semibold text-primary mb-1.5">
                    Atelier / Département <span class="text-red-500">*</span>
                </label>
                <select name="department" id="department" required class="w-full text-sm rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                    @foreach($departmentLabels as $val => $lbl)
                        <option value="{{ $val }}" @selected(old('department', 'cuisine') === $val)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Responsable et Date --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="responsible_person" class="block text-xs font-semibold text-primary mb-1.5">
                    Personne responsable / Déclarant
                </label>
                <input type="text" name="responsible_person" id="responsible_person"
                    value="{{ old('responsible_person', auth()->user()->name) }}"
                    placeholder="Ex: Chef de cuisine, Commis, Chef de rang..."
                    class="w-full text-sm rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
            </div>

            <div>
                <label for="occurred_at" class="block text-xs font-semibold text-primary mb-1.5">
                    Date et heure du constat
                </label>
                <input type="datetime-local" name="occurred_at" id="occurred_at"
                    value="{{ old('occurred_at', now()->format('Y-m-d\TH:i')) }}"
                    class="w-full text-sm rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
            </div>
        </div>

        {{-- Notes / Justification --}}
        <div>
            <label for="notes" class="block text-xs font-semibold text-primary mb-1.5">
                Précisions / Justification circonstanciée
            </label>
            <textarea name="notes" id="notes" rows="3"
                placeholder="Circonstances détaillées (ex: rupture de la chaîne du froid suite à coupure, sur-portionnage lors du buffet, etc.)"
                class="w-full text-sm rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">{{ old('notes') }}</textarea>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-end gap-3 pt-4 border-t border-secondary/15">
            <a href="{{ route('restaurant.waste.index') }}" class="px-4 py-2 border border-secondary/25 text-primary text-xs font-semibold rounded-lg hover:bg-secondary/10">
                Annuler
            </a>
            <button type="submit" class="px-5 py-2.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 shadow-sm transition-colors flex items-center gap-2">
                <i data-lucide="check" class="w-4 h-4"></i> Valider et déduire du stock
            </button>
        </div>
    </form>
</div>

<script>
function updateItemDetails() {
    const select = document.getElementById('restaurant_pantry_item_id');
    const opt = select.options[select.selectedIndex];
    const unitBadge = document.getElementById('unit-badge');

    if (opt && opt.dataset.unit) {
        unitBadge.textContent = opt.dataset.unit;
    } else {
        unitBadge.textContent = 'unité';
    }

    calculateValuation();
}

function calculateValuation() {
    const select = document.getElementById('restaurant_pantry_item_id');
    const opt = select.options[select.selectedIndex];
    const qtyInput = document.getElementById('quantity');
    const costElem = document.getElementById('estimated-cost');
    const stockAfterElem = document.getElementById('stock-after-hint');

    const qty = parseFloat(qtyInput.value) || 0;

    if (!opt || !opt.dataset.cost || qty <= 0) {
        costElem.textContent = '0 FCFA';
        stockAfterElem.textContent = 'Stock après déduction : —';
        return;
    }

    const unitCost = parseFloat(opt.dataset.cost) || 0;
    const currentStock = parseFloat(opt.dataset.stock) || 0;
    const unit = opt.dataset.unit || '';

    const totalVal = Math.round(qty * unitCost);
    const nextStock = (currentStock - qty).toFixed(3);

    costElem.textContent = new Intl.NumberFormat('fr-FR').format(totalVal) + ' FCFA';
    stockAfterElem.textContent = 'Stock après déduction : ' + nextStock + ' ' + unit;

    if (currentStock - qty < 0) {
        stockAfterElem.className = 'text-[11px] text-red-600 font-semibold mt-0.5';
    } else {
        stockAfterElem.className = 'text-[11px] text-primary/40 mt-0.5';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateItemDetails();
});
</script>
@endsection
