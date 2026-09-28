@extends('layouts.hotel')

@section('title', 'Réceptionner le bon ' . $order->number . ' — Économat')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="goodsReceiptForm()">
    <div class="flex items-center gap-3">
        <a href="{{ route('economat.orders.show', $order) }}" class="p-2 rounded-lg hover:bg-gray-100 text-primary/60 transition-colors">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <span class="text-xs font-mono font-semibold text-primary/50 uppercase">Réception contradictoire de marchandises</span>
            <h1 class="text-xl font-heading font-semibold text-primary">Bon de commande {{ $order->number }}</h1>
            <p class="text-sm text-primary/60 mt-0.5">Fournisseur : <strong>{{ $order->supplier?->name }}</strong></p>
        </div>
    </div>

    @include('economat.partials.flash')

    <form method="POST" action="{{ route('economat.receipts.store', $order) }}" class="space-y-6">
        @csrf

        {{-- Métadonnées de livraison --}}
        <div class="bg-white border border-secondary/20 rounded-xl p-5 shadow-sm space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">
                        N° Bon de Livraison (BL Fournisseur)
                    </label>
                    <input type="text" name="delivery_note_number" placeholder="Ex: BL-98421" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Date et heure de déchargement</label>
                    <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Magasinier réceptionnaire</label>
                    <input type="text" readonly disabled value="{{ auth()->user()->name }}" class="w-full px-3 py-2 text-sm border border-secondary/20 bg-gray-50 rounded-lg text-primary/70">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-primary uppercase tracking-wider mb-1.5">Observations / Remarques de livraison</label>
                <input type="text" name="notes" placeholder="Ex: Chauffeur M. Talla, camion frigorifique conforme..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-white text-primary focus:outline-none focus:border-primary">
            </div>
        </div>

        {{-- Tableau contradictoire de pointage --}}
        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
            <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-primary">Pointage des articles livrés</h2>
                    <p class="text-xs text-primary/50">Saisissez les quantités déchargées. Les quantités refusées ne modifient pas le stock et font l'objet d'un litige.</p>
                </div>
                <button type="button" @click="receiveAll()" class="px-3 py-1.5 bg-secondary/10 hover:bg-secondary/20 text-primary text-xs font-medium rounded-lg transition-colors">
                    Tout recevoir conforme
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/40 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                        <tr>
                            <th class="px-4 py-2.5 text-left w-1/4">Article</th>
                            <th class="px-3 py-2.5 text-right w-24">Commandé</th>
                            <th class="px-3 py-2.5 text-right w-24">Reste dû</th>
                            <th class="px-3 py-2.5 text-center w-28">Livré (BL)</th>
                            <th class="px-3 py-2.5 text-center w-28 bg-emerald-50/30 text-emerald-800">Accepté</th>
                            <th class="px-3 py-2.5 text-center w-28 bg-rose-50/30 text-rose-800">Refusé / Avarie</th>
                            <th class="px-3 py-2.5 text-left w-44">Motif de refus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($order->lines as $line)
                            @php
                                $outstanding = $line->outstanding();
                            @endphp
                            <tr class="hover:bg-gray-50/40" x-data="{
                                id: {{ $line->id }},
                                outstanding: {{ $outstanding }},
                                delivered: 0,
                                accepted: 0,
                                rejected: 0,
                                unitPrice: {{ $line->unit_price }},
                                onDeliveredChange() {
                                    this.accepted = Math.max(0, Math.min(this.outstanding, this.delivered - this.rejected));
                                },
                                onRejectedChange() {
                                    this.accepted = Math.max(0, Math.min(this.outstanding, this.delivered - this.rejected));
                                }
                            }" x-init="registerRow({{ $line->id }}, outstanding)">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-primary">{{ $line->item?->name ?? '—' }}</div>
                                    <div class="text-xs text-primary/40 font-mono">P.U. : {{ number_format($line->unit_price / 100, 0, ',', ' ') }} F / {{ $line->item?->unit }}</div>
                                </td>
                                <td class="px-3 py-3 text-right font-mono text-primary/70">
                                    {{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-bold {{ $outstanding > 0 ? 'text-amber-700' : 'text-green-600' }}">
                                    {{ rtrim(rtrim(number_format($outstanding, 3, ',', ' '), '0'), ',') }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <input type="number" step="0.001" min="0"
                                        :name="'lines[' + id + '][quantity_delivered]'"
                                        x-model.number="delivered"
                                        @input="onDeliveredChange()"
                                        class="w-24 px-2 py-1.5 text-sm border border-secondary/30 rounded-lg text-right font-mono text-primary focus:outline-none focus:border-primary"
                                        placeholder="0">
                                </td>
                                <td class="px-3 py-3 text-center bg-emerald-50/20">
                                    <input type="number" step="0.001" min="0" :max="outstanding"
                                        :name="'lines[' + id + '][quantity_accepted]'"
                                        x-model.number="accepted"
                                        class="w-24 px-2 py-1.5 text-sm border border-emerald-400 bg-emerald-50/50 rounded-lg text-right font-mono font-bold text-emerald-800 focus:outline-none focus:border-emerald-600"
                                        placeholder="0">
                                </td>
                                <td class="px-3 py-3 text-center bg-rose-50/20">
                                    <input type="number" step="0.001" min="0"
                                        :name="'lines[' + id + '][quantity_rejected]'"
                                        x-model.number="rejected"
                                        @input="onRejectedChange()"
                                        class="w-24 px-2 py-1.5 text-sm border border-rose-300 bg-rose-50/30 rounded-lg text-right font-mono text-rose-800 focus:outline-none focus:border-rose-500"
                                        placeholder="0">
                                </td>
                                <td class="px-3 py-3">
                                    <select :name="'lines[' + id + '][rejection_reason]'" x-show="rejected > 0" class="w-full px-2 py-1 text-xs border border-rose-300 rounded-lg bg-rose-50/40 text-rose-900 focus:outline-none">
                                        <option value="">Sélectionner motif...</option>
                                        @foreach($reasons as $rCode => $rLbl)
                                            <option value="{{ $rCode }}">{{ $rLbl }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" :name="'lines[' + id + '][notes]'" placeholder="Note..." class="w-full mt-1 px-2 py-1 text-xs border border-secondary/20 rounded bg-white text-primary">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('economat.orders.show', $order) }}" class="px-4 py-2 text-sm text-primary/60 hover:text-primary">
                Annuler
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="package-check" class="w-4 h-4"></i> Valider la réception & Entrer en stock
            </button>
        </div>
    </form>
</div>

<script>
function goodsReceiptForm() {
    return {
        rows: {},
        registerRow(id, outstanding) {
            this.rows[id] = outstanding;
        },
        receiveAll() {
            // Remplir automatiquement delivered et accepted à outstanding pour chaque ligne
            Object.keys(this.rows).forEach(id => {
                const elDelivered = document.querySelector(`input[name="lines[${id}][quantity_delivered]"]`);
                const elAccepted = document.querySelector(`input[name="lines[${id}][quantity_accepted]"]`);
                const elRejected = document.querySelector(`input[name="lines[${id}][quantity_rejected]"]`);
                const val = this.rows[id];
                if (elDelivered) { elDelivered.value = val; elDelivered.dispatchEvent(new Event('input')); }
                if (elAccepted) { elAccepted.value = val; elAccepted.dispatchEvent(new Event('input')); }
                if (elRejected) { elRejected.value = 0; elRejected.dispatchEvent(new Event('input')); }
            });
        }
    };
}
</script>
@endsection
