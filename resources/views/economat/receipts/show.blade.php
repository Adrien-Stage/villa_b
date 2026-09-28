@extends('layouts.hotel')

@section('title', $receipt->number . ' — Bon de réception')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('economat.receipts.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Retour aux réceptions
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('economat.receipts.print', $receipt) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4"></i> Imprimer le Bordereau (BR)
            </a>
            @if(\Illuminate\Support\Facades\Route::has('accounting.supplier-invoices.create'))
                <a href="{{ route('accounting.supplier-invoices.create', ['bon' => $receipt->purchase_order_id]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="file-text" class="w-4 h-4"></i> Établir la facture fournisseur
                </a>
            @endif
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Synthèse --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <span class="text-xs font-mono font-semibold text-primary/50 uppercase">Bordereau officiel de réception</span>
                <h1 class="text-2xl font-heading font-bold text-primary font-mono mt-0.5">{{ $receipt->number }}</h1>
                <p class="text-sm text-primary/80 mt-1">
                    Fournisseur : <strong>{{ $receipt->supplier?->name }}</strong>
                    @if($receipt->delivery_note_number)
                        · N° BL Livreur : <strong class="font-mono">{{ $receipt->delivery_note_number }}</strong>
                    @endif
                </p>
                <p class="text-xs text-primary/50 mt-1">
                    Bon de commande lié :
                    <a href="{{ route('economat.orders.show', $receipt->purchaseOrder) }}" class="font-mono text-primary font-bold hover:underline">
                        {{ $receipt->purchaseOrder?->number }}
                    </a>
                    · Réceptionné le {{ $receipt->received_at->format('d/m/Y à H:i') }} par {{ $receipt->receivedBy?->name ?? 'Magasinier' }}
                </p>
            </div>
            <div class="text-right">
                <span class="text-xs text-primary/50 uppercase font-semibold">Valeur admise en stock</span>
                <div class="text-2xl font-bold font-mono text-emerald-700 mt-0.5">
                    {{ number_format($receipt->total_amount / 100, 0, ',', ' ') }} FCFA
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold mt-1 bg-emerald-50 text-emerald-800 border border-emerald-200">
                    {{ $receipt->statusLabel() }}
                </span>
            </div>
        </div>

        @if($receipt->notes)
            <div class="p-3 bg-gray-50 rounded-lg text-xs text-primary/70 border border-secondary/10">
                <strong>Observations :</strong> {{ $receipt->notes }}
            </div>
        @endif

        @if($receipt->hasRejections())
            <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-lg text-xs text-rose-800 space-y-1">
                <div class="font-bold flex items-center gap-1.5">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600"></i>
                    Marchandises refusées / Litiges constatés :
                </div>
                <div>
                    {{ rtrim(rtrim(number_format($receipt->totalRejectedQuantity(), 3, ',', ' '), '0'), ',') }} unités au total ont été refusées pour non-conformité et ne sont pas entrées en stock.
                </div>
            </div>
        @endif
    </div>

    {{-- Lignes du bon de réception --}}
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3.5 bg-gray-50/80 border-b border-secondary/15">
            <h2 class="text-sm font-semibold text-primary">Articles réceptionnés & contrôle contradictoire</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50/40 text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                    <tr>
                        <th class="px-5 py-3 text-left">Article</th>
                        <th class="px-3 py-3 text-right">Commandé</th>
                        <th class="px-3 py-3 text-right">Livré (BL)</th>
                        <th class="px-3 py-3 text-right text-emerald-800">Accepté (Stock)</th>
                        <th class="px-3 py-3 text-right text-rose-800">Refusé</th>
                        <th class="px-3 py-3 text-right">P.U.</th>
                        <th class="px-5 py-3 text-right">Montant admis</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($receipt->lines as $line)
                        <tr class="hover:bg-gray-50/40">
                            <td class="px-5 py-3">
                                <div class="font-medium text-primary">{{ $line->item?->name ?? '—' }}</div>
                                <div class="text-xs text-primary/40">({{ $line->item?->unit }})</div>
                                @if($line->quantity_rejected > 0)
                                    <div class="text-xs text-rose-700 mt-0.5">
                                        Refus : <strong>{{ $line->rejectionReasonLabel() }}</strong>
                                        @if($line->notes) — {{ $line->notes }}@endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-primary/60">
                                {{ rtrim(rtrim(number_format($line->quantity_ordered, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-primary/70">
                                {{ rtrim(rtrim(number_format($line->quantity_delivered, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-bold text-emerald-700">
                                {{ rtrim(rtrim(number_format($line->quantity_accepted, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono font-bold {{ $line->quantity_rejected > 0 ? 'text-rose-600' : 'text-primary/40' }}">
                                {{ rtrim(rtrim(number_format($line->quantity_rejected, 3, ',', ' '), '0'), ',') }}
                            </td>
                            <td class="px-3 py-3 text-right font-mono text-primary/70">
                                {{ number_format($line->unit_cost / 100, 0, ',', ' ') }}
                            </td>
                            <td class="px-5 py-3 text-right font-mono font-semibold text-primary">
                                {{ number_format($line->total_cost / 100, 0, ',', ' ') }} F
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50/80 border-t border-secondary/15 font-semibold text-primary">
                    <tr>
                        <td colspan="6" class="px-5 py-3 text-right">Valeur totale acceptée (TTC) :</td>
                        <td class="px-5 py-3 text-right font-mono text-base font-bold text-emerald-700">
                            {{ number_format($receipt->total_amount / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
