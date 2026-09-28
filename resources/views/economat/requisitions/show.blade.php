@extends('layouts.hotel')

@section('title', 'Bon ' . $requisition->number . ' — Économat')

@section('content')
@php
    $statusStyles = [
        'pending'   => 'bg-blue-50 text-blue-700 border border-blue-200',
        'approved'  => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        'rejected'  => 'bg-red-50 text-red-700 border border-red-200',
        'delivered' => 'bg-green-50 text-green-700 border border-green-200',
        'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200',
    ];
@endphp

<style>
@import url('https://fonts.googleapis.com/css2?family=Qwigley&display=swap');
@font-face {
    font-family: 'Qwigley';
    font-style: normal;
    font-weight: 400;
    font-display: swap;
    src: url('/fonts/Qwigley-Regular.woff2') format('woff2'),
         url('/fonts/Qwigley-Regular.ttf') format('truetype');
}
.font-signature {
    font-family: 'Qwigley', cursive, 'Brush Script MT', sans-serif;
}
</style>

<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between gap-4 mb-4">
        <a href="{{ route('economat.requisitions.index') }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Retour à la liste des bons</span>
        </a>

        <div class="flex items-center gap-2">
            <a href="{{ route('economat.requisitions.print', $requisition) }}" target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-secondary/30 rounded-lg text-primary text-xs font-semibold hover:bg-surface-light shadow-sm transition-colors">
                <i data-lucide="printer" class="w-4 h-4 text-primary/70"></i>
                <span>Imprimer le bon</span>
            </a>
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- En-tête du Bon --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-6 mb-4 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-heading font-bold text-primary font-mono">{{ $requisition->number }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $statusStyles[$requisition->status] ?? 'bg-gray-100' }}">
                        {{ $requisition->statusLabel() }}
                    </span>
                </div>
                <p class="text-sm font-semibold text-primary/80 mt-1">
                    Service : <span class="text-primary">{{ $requisition->departmentLabel() }}</span>
                </p>
                <p class="text-xs text-primary/50 mt-0.5">
                    Émis par <strong>{{ $requisition->requestedBy?->name ?? '—' }}</strong> le {{ $requisition->created_at->format('d/m/Y à H:i') }}
                </p>
                @if($requisition->purpose)
                    <div class="mt-3 p-2.5 bg-surface-light rounded-lg border border-secondary/15 text-xs text-primary/80">
                        <span class="font-semibold text-primary/60 uppercase text-[10px] block">Motif / Justification :</span>
                        {{ $requisition->purpose }}
                    </div>
                @endif
            </div>

            {{-- Signature manuscrite de l'émetteur --}}
            <div class="sm:self-start bg-slate-50/80 border border-slate-200/80 rounded-xl p-3.5 min-w-[210px] text-center shadow-xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block">Signature du Demandeur</span>
                <div class="font-signature text-3xl text-blue-900 py-1 select-none transform -rotate-3 inline-block">
                    {{ $requisition->requesterSignature() }}
                </div>
                <div class="text-[10px] font-medium text-slate-700">
                    {{ $requisition->requestedBy?->name ?? '—' }}
                </div>
                <div class="text-[9px] text-slate-400 font-mono mt-0.5">
                    Signé numériquement le {{ $requisition->created_at->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>

        @if($requisition->reviewedBy)
            <div class="mt-4 pt-3 border-t border-secondary/15 text-xs text-primary/70 flex items-center gap-2">
                <i data-lucide="shield-check" class="w-4 h-4 text-primary/50"></i>
                <span>
                    {{ $requisition->status === 'rejected' ? 'Refusée' : 'Validée' }} par <strong>{{ $requisition->reviewedBy->name }}</strong> le {{ $requisition->reviewed_at?->format('d/m/Y à H:i') }}
                    @if($requisition->review_notes) — <em>« {{ $requisition->review_notes }} »</em>@endif
                </span>
            </div>
        @endif

        @if($requisition->status === 'delivered')
            <div class="mt-3 pt-3 border-t border-secondary/15 flex items-center justify-between text-xs">
                <span class="text-green-700 font-medium inline-flex items-center gap-1.5">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    @if($requisition->department === 'restaurant')
                        Livré et transféré automatiquement dans le stock garde-manger restaurant.
                    @elseif($requisition->department === 'boutique')
                        Livré et stock boutique incrémenté.
                    @else
                        Livré au département {{ $requisition->departmentLabel() }}.
                    @endif
                </span>
                @if($requisition->delivered_at)
                    <span class="text-primary/40 font-mono">le {{ $requisition->delivered_at->format('d/m/Y à H:i') }}</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Articles demandés et formulaire de livraison --}}
    <form method="POST" action="{{ $isKeeper && $requisition->canBeDelivered() ? route('economat.requisitions.deliver', $requisition) : '#' }}">
        @if($isKeeper && $requisition->canBeDelivered()) @csrf @endif

        <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden mb-4 shadow-sm">
            <div class="px-5 py-3 border-b border-secondary/20 bg-surface-light/60 flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-primary">Articles du bon de réquisition</h2>
                <span class="text-xs font-mono text-primary/50">{{ $requisition->lines->count() }} référence(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-surface-light border-b border-secondary/20 text-left text-primary/60 uppercase tracking-wider font-semibold">
                            <th class="px-4 py-3">Article & Référence</th>
                            <th class="px-4 py-3">Catégorie</th>
                            <th class="px-4 py-3 text-center">Unité</th>
                            <th class="px-4 py-3 text-right">Stock Actuel</th>
                            <th class="px-4 py-3 text-right">Demandé</th>
                            @if($requisition->status === 'delivered')
                                <th class="px-4 py-3 text-right">Servi</th>
                            @elseif($isKeeper && $requisition->canBeDelivered())
                                <th class="px-4 py-3 text-right">À servir</th>
                            @endif
                            <th class="px-4 py-3 text-right">P.U. CUMP</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @php
                            $totalValuation = 0;
                        @endphp
                        @foreach($requisition->lines as $line)
                            @php
                                $item = $line->item;
                                $serviceable = $line->isServiceable();
                                $unitCost = (int) ($item?->average_cost ?? 0);
                                $lineCost = $requisition->status === 'delivered' ? $line->totalIssuedCost() : $line->totalRequestedCost();
                                $totalValuation += $lineCost;
                            @endphp
                            <tr class="hover:bg-surface-light/40 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-primary">{{ $item?->name ?? 'Article non répertorié' }}</div>
                                    <div class="text-[10px] font-mono text-primary/40">{{ $item?->reference ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-primary/60">
                                    {{ $item?->category?->name ?? 'Général' }}
                                </td>
                                <td class="px-4 py-3 text-center text-primary/60">
                                    {{ $item?->unit ?? 'u' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-mono {{ $serviceable ? 'text-primary/70' : 'text-red-600 font-bold' }}">
                                        {{ rtrim(rtrim(number_format($item?->current_stock ?? 0, 3, ',', ' '), '0'), ',') }}
                                    </span>
                                    @unless($serviceable)
                                        <span class="block text-[10px] text-red-500 font-sans">stock insuffisant</span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-primary">
                                    {{ rtrim(rtrim(number_format($line->quantity_requested, 3, ',', ' '), '0'), ',') }}
                                </td>
                                @if($requisition->status === 'delivered')
                                    <td class="px-4 py-3 text-right font-mono font-bold text-green-700">
                                        {{ rtrim(rtrim(number_format($line->quantity_issued, 3, ',', ' '), '0'), ',') }}
                                    </td>
                                @elseif($isKeeper && $requisition->canBeDelivered())
                                    <td class="px-4 py-3 text-right">
                                        <input type="number" step="0.001" min="0" max="{{ $item?->current_stock ?? 0 }}"
                                            name="issued[{{ $line->id }}]"
                                            value="{{ min((float) $line->quantity_requested, (float) ($item?->current_stock ?? 0)) }}"
                                            class="w-24 px-2 py-1 text-xs border border-secondary/30 rounded-lg bg-white text-primary font-mono text-right font-bold focus:border-primary focus:ring-primary">
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-right font-mono text-primary/60">
                                    {{ number_format($unitCost / 100, 0, ',', ' ') }} FCFA
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-primary">
                                    {{ number_format($lineCost / 100, 0, ',', ' ') }} FCFA
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-surface-light border-t border-secondary/20">
                        <tr class="font-bold text-primary">
                            <td colspan="4" class="px-4 py-2.5 text-right uppercase text-[11px]">Total valorisé estimé :</td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                {{ rtrim(rtrim(number_format($requisition->lines->sum('quantity_requested'), 3, ',', ' '), '0'), ',') }}
                            </td>
                            @if($requisition->status === 'delivered' || ($isKeeper && $requisition->canBeDelivered()))
                                <td class="px-4 py-2.5 text-right font-mono text-green-700">
                                    {{ $requisition->status === 'delivered' ? rtrim(rtrim(number_format($requisition->lines->sum('quantity_issued'), 3, ',', ' '), '0'), ',') : '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-2.5 text-right font-mono">—</td>
                            <td class="px-4 py-2.5 text-right font-mono text-sm text-primary">
                                {{ number_format($totalValuation / 100, 0, ',', ' ') }} FCFA
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if($isKeeper && $requisition->canBeDelivered())
                <div class="px-5 py-3 border-t border-secondary/20 bg-amber-50/70 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <p class="text-xs text-amber-900">
                        <i data-lucide="info" class="w-3.5 h-3.5 inline mr-1 text-amber-700"></i>
                        La livraison physique déstocke automatiquement les quantités servies et les transfère au service.
                    </p>
                    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        <i data-lucide="truck" class="w-4 h-4"></i>
                        <span>Confirmer la livraison et déstocker</span>
                    </button>
                </div>
            @endif
        </div>
    </form>

    {{-- Actions de validation par l'économe / manager --}}
    @if($isKeeper && $requisition->canBeReviewed())
        <div class="bg-white border border-secondary/20 rounded-xl p-5 mb-4 shadow-sm" x-data="{ mode: null }">
            <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-3">Arbitrage du Bon de Réquisition</h2>
            <div class="flex gap-3 mb-3">
                <button type="button" @click="mode = 'approve'"
                    class="flex-1 py-2 px-4 rounded-lg border text-xs font-semibold transition-colors flex items-center justify-center gap-1.5"
                    :class="mode === 'approve' ? 'bg-green-600 text-white border-green-600' : 'border-secondary/30 text-primary hover:bg-green-50'">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Valider la demande</span>
                </button>
                <button type="button" @click="mode = 'reject'"
                    class="flex-1 py-2 px-4 rounded-lg border text-xs font-semibold transition-colors flex items-center justify-center gap-1.5"
                    :class="mode === 'reject' ? 'bg-red-600 text-white border-red-600' : 'border-secondary/30 text-primary hover:bg-red-50'">
                    <i data-lucide="x" class="w-4 h-4"></i>
                    <span>Refuser la demande</span>
                </button>
            </div>
            <form x-show="mode" x-cloak method="POST" :action="mode === 'approve' ? '{{ route('economat.requisitions.approve', $requisition) }}' : '{{ route('economat.requisitions.reject', $requisition) }}'">
                @csrf
                <textarea name="review_notes" rows="2" placeholder="Motif ou remarque (facultatif)..."
                    class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg text-primary outline-none focus:border-primary mb-3"></textarea>
                <button type="submit" class="w-full py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <span x-text="mode === 'approve' ? 'Confirmer la validation du bon' : 'Confirmer le refus du bon'"></span>
                </button>
            </form>
        </div>
    @endif

    {{-- Annulation par le demandeur --}}
    @if($requisition->canBeCancelled())
        <form method="POST" action="{{ route('economat.requisitions.cancel', $requisition) }}" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce bon de réquisition ?');" class="mt-3 text-right">
            @csrf
            <button type="submit" class="text-xs text-red-500 hover:text-red-700 hover:underline">
                Annuler ce bon de réquisition
            </button>
        </form>
    @endif
</div>
@endsection
