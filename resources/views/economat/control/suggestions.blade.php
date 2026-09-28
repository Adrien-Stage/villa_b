@extends('layouts.hotel')

@section('title', 'Propositions de réapprovisionnement — Économat')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <a href="{{ route('economat.control.index') }}" class="text-xs text-primary/60 hover:text-primary flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Retour au Contrôle des stocks</span>
            </a>
        </div>
        <h1 class="text-2xl font-semibold text-primary font-heading flex items-center gap-2">
            <i data-lucide="shopping-cart" class="w-7 h-7 text-primary"></i>
            <span>Propositions automatiques de commande</span>
        </h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            Calcul automatique des besoins basé sur les seuils d'alerte et de réapprovisionnement du magasin central. Sélectionnez les lignes souhaitées pour créer en un clic une demande d'achat interne.
        </p>
    </div>
</div>

@include('economat.partials.flash')

@if(empty($suggestions))
    <div class="bg-white border border-secondary/20 rounded-xl p-12 text-center shadow-sm">
        <div class="w-16 h-16 bg-green-50 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
            <i data-lucide="check-circle" class="w-8 h-8"></i>
        </div>
        <h2 class="text-lg font-bold text-primary">Stocks à niveau optimal</h2>
        <p class="text-sm text-primary/60 max-w-md mx-auto mt-1">
            Aucun article du magasin central n'est actuellement sous son seuil de sécurité. Aucune commande de réapprovisionnement n'est requise pour le moment.
        </p>
        <div class="mt-6">
            <a href="{{ route('economat.control.index') }}" class="px-4 py-2 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors">
                Retour au tableau de bord
            </a>
        </div>
    </div>
@else
    @php
        $totalEstimatedCost = array_sum(array_column($suggestions, 'estimated_total_cost'));
        $distinctSuppliers = collect($suggestions)->pluck('supplier.name')->filter()->unique()->count();
    @endphp

    {{-- Métriques d'alerte --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Références à réapprovisionner</p>
            <p class="text-2xl font-bold font-mono text-amber-700 mt-1">{{ count($suggestions) }}</p>
            <p class="text-xs text-primary/50 mt-1">Articles sous le seuil minimum de sécurité</p>
        </div>

        <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Budget estimé total</p>
            <p class="text-2xl font-bold font-mono text-primary mt-1">
                {{ number_format($totalEstimatedCost / 100, 0, ',', ' ') }} <span class="text-xs font-sans font-normal text-primary/50">FCFA</span>
            </p>
            <p class="text-xs text-primary/50 mt-1">Basé sur les derniers prix d'achat / CUMP</p>
        </div>

        <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Fournisseurs concernés</p>
            <p class="text-2xl font-bold font-mono text-primary mt-1">{{ $distinctSuppliers }}</p>
            <p class="text-xs text-primary/50 mt-1">Partenaires identifiés pour ces articles</p>
        </div>
    </div>

    {{-- Formulaire de génération de la Demande d'Achat --}}
    <form action="{{ route('economat.control.suggestions.store') }}" method="POST" id="orderSuggestionsForm">
        @csrf

        <div class="bg-white border border-secondary/20 rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="p-4 bg-surface-light/50 border-b border-secondary/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-primary">
                        <input type="checkbox" id="selectAllCheckbox" checked
                            class="rounded border-secondary/30 text-primary focus:ring-primary h-4 w-4">
                        <span>Tout sélectionner / désélectionner</span>
                    </label>
                    <span id="selectedCountBadge" class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-primary/10 text-primary">
                        {{ count($suggestions) }} sélectionné(s)
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <label for="priority" class="text-xs font-semibold uppercase tracking-wider text-primary/60">Priorité :</label>
                    <select id="priority" name="priority"
                        class="rounded-lg border-secondary/30 text-xs text-primary py-1.5 px-3 focus:border-primary focus:ring-primary">
                        <option value="normal" selected>Normale</option>
                        <option value="urgent">Urgente</option>
                        <option value="low">Faible</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-surface-light border-b border-secondary/20 text-left text-primary/60 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-3 w-10 text-center">#</th>
                            <th class="py-3 px-3">Article & Référence</th>
                            <th class="py-3 px-3">Catégorie</th>
                            <th class="py-3 px-3">Fournisseur</th>
                            <th class="py-3 px-3 text-center">Stock Actuel</th>
                            <th class="py-3 px-3 text-center">Stock Min</th>
                            <th class="py-3 px-3 text-center">Qté Suggérée</th>
                            <th class="py-3 px-3 text-right">P.U. Estimé</th>
                            <th class="py-3 px-3 text-right">Total Estimé</th>
                            <th class="py-3 px-3">Remarques</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($suggestions as $idx => $s)
                            @php
                                $item = $s['item'];
                                $isOutOfStock = $s['current_stock'] <= 0;
                            @endphp
                            <tr class="hover:bg-surface-light/40 transition-colors suggestion-row">
                                <td class="py-3 px-3 text-center">
                                    <input type="checkbox" name="items[{{ $idx }}][item_id]" value="{{ $item->id }}"
                                        checked class="item-checkbox rounded border-secondary/30 text-primary focus:ring-primary h-4 w-4"
                                        data-unit-price="{{ $s['estimated_unit_price'] }}"
                                        data-row-index="{{ $idx }}">
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-bold text-primary">{{ $item->name }}</div>
                                    <div class="text-[10px] font-mono text-primary/50">{{ $item->reference }}</div>
                                </td>
                                <td class="py-3 px-3 text-primary/70">
                                    {{ $item->category?->name ?? 'Général' }}
                                </td>
                                <td class="py-3 px-3 text-primary/70">
                                    {{ $s['supplier']?->name ?? '—' }}
                                </td>
                                <td class="py-3 px-3 text-center font-mono font-bold {{ $isOutOfStock ? 'text-red-600' : 'text-amber-700' }}">
                                    {{ $s['current_stock'] }} {{ $s['unit'] }}
                                </td>
                                <td class="py-3 px-3 text-center font-mono text-primary/50">
                                    {{ $s['min_stock'] }} {{ $s['unit'] }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <input type="number" step="0.01" min="0.01"
                                            name="items[{{ $idx }}][quantity]"
                                            value="{{ $s['suggested_quantity'] }}"
                                            class="item-qty-input w-20 text-center font-mono font-bold rounded-lg border-secondary/30 text-xs py-1 px-1.5 focus:border-primary focus:ring-primary"
                                            data-row-index="{{ $idx }}">
                                        <span class="text-[10px] text-primary/50">{{ $s['unit'] }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-right font-mono text-primary/70">
                                    {{ number_format($s['estimated_unit_price'] / 100, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="py-3 px-3 text-right font-mono font-bold text-primary row-total-cell" id="row-total-{{ $idx }}">
                                    {{ number_format($s['estimated_total_cost'] / 100, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="py-3 px-3">
                                    <input type="text" name="items[{{ $idx }}][notes]"
                                        placeholder="Notes facultatives..."
                                        class="w-full text-xs rounded-lg border-secondary/30 py-1 px-2 text-primary focus:border-primary focus:ring-primary">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-surface-light border-t border-secondary/20 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="text-xs text-primary/70">
                    <span>Total de la sélection : </span>
                    <strong class="font-mono text-sm text-primary" id="selectedTotalCost">
                        {{ number_format($totalEstimatedCost / 100, 0, ',', ' ') }} FCFA
                    </strong>
                </div>

                @droit('economat.control.suggestions.creer')
                    <button type="submit" id="submitBtn"
                        class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-primary hover:bg-surface-dark text-white text-xs font-semibold rounded-lg shadow-sm transition-colors">
                        <i data-lucide="file-check" class="w-4 h-4"></i>
                        <span>Créer la Demande d'Achat interne</span>
                    </button>
                @enddroit
            </div>
        </div>
    </form>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAllCheckbox');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const qtyInputs = document.querySelectorAll('.item-qty-input');
    const badge = document.getElementById('selectedCountBadge');
    const totalEl = document.getElementById('selectedTotalCost');
    const submitBtn = document.getElementById('submitBtn');

    function formatMoney(amountCentimes) {
        const val = Math.round(amountCentimes / 100);
        return val.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " FCFA";
    }

    function updateTotal() {
        let total = 0;
        let count = 0;

        checkboxes.forEach((cb) => {
            const rowIdx = cb.dataset.rowIndex;
            const qtyInput = document.querySelector(`.item-qty-input[data-row-index="${rowIdx}"]`);
            const unitPrice = parseFloat(cb.dataset.unitPrice) || 0;
            const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
            const rowTotal = Math.round(qty * unitPrice);

            const rowTotalCell = document.getElementById(`row-total-${rowIdx}`);
            if (rowTotalCell) {
                rowTotalCell.textContent = formatMoney(rowTotal);
            }

            if (cb.checked && qty > 0) {
                total += rowTotal;
                count++;
            }
        });

        if (badge) {
            badge.textContent = `${count} sélectionné(s)`;
        }
        if (totalEl) {
            totalEl.textContent = formatMoney(total);
        }
        if (submitBtn) {
            submitBtn.disabled = count === 0;
            submitBtn.classList.toggle('opacity-50', count === 0);
            submitBtn.classList.toggle('cursor-not-allowed', count === 0);
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateTotal();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            if (!cb.checked && selectAll) {
                selectAll.checked = false;
            }
            updateTotal();
        });
    });

    qtyInputs.forEach(input => {
        input.addEventListener('input', updateTotal);
    });

    updateTotal();
});
</script>
@endsection
