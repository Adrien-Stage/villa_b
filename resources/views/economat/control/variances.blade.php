@extends('layouts.hotel')

@section('title', 'Audit des écarts d\'inventaire — Économat')

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
            <i data-lucide="git-compare" class="w-7 h-7 text-primary"></i>
            <span>Audit & Justification des écarts d'inventaire</span>
        </h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            Rapprochement contradictoire entre stocks théoriques et comptages physiques réels des inventaires clôturés (Magasin Central et Garde-manger Cuisine).
        </p>
    </div>

    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('economat.control.print', ['start_date' => $startDate, 'end_date' => $endDate]) }}" target="_blank"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-secondary/30 text-primary text-xs font-medium rounded-lg hover:bg-surface-light transition-colors shadow-sm">
            <i data-lucide="printer" class="w-4 h-4 text-primary/60"></i>
            <span>Imprimer le rapport</span>
        </a>
    </div>
</div>

@include('economat.partials.flash')

{{-- Filtrage par période --}}
<div class="bg-white border border-secondary/20 rounded-xl p-4 mb-6 shadow-sm">
    <form method="GET" action="{{ route('economat.control.variances.index') }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <i data-lucide="calendar" class="w-4 h-4 text-primary/50"></i>
            <span class="text-xs font-semibold uppercase tracking-wider text-primary/70">Période des inventaires :</span>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label for="start_date" class="text-xs text-primary/60">Du</label>
                <input type="date" id="start_date" name="start_date" value="{{ $startDate }}"
                    class="rounded-lg border-secondary/30 text-xs text-primary py-1.5 px-2.5 focus:border-primary focus:ring-primary">
            </div>
            <div class="flex items-center gap-2">
                <label for="end_date" class="text-xs text-primary/60">Au</label>
                <input type="date" id="end_date" name="end_date" value="{{ $endDate }}"
                    class="rounded-lg border-secondary/30 text-xs text-primary py-1.5 px-2.5 focus:border-primary focus:ring-primary">
            </div>
            <button type="submit"
                class="px-3.5 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors">
                Filtrer
            </button>
            @if(request()->has('start_date') || request()->has('end_date'))
                <a href="{{ route('economat.control.variances.index') }}" class="text-xs text-primary/50 hover:text-primary underline">
                    Réinitialiser
                </a>
            @endif
        </div>
    </form>
</div>

{{-- Synthèse financière des écarts --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-red-600">Pertes & Manquants cumulés</p>
        <p class="text-2xl font-bold font-mono text-red-700 mt-1">
            −{{ number_format($variances['total_loss_value'] / 100, 0, ',', ' ') }} <span class="text-xs font-sans font-normal text-red-600/70">FCFA</span>
        </p>
        <div class="mt-2 pt-2 border-t border-secondary/15 flex justify-between text-xs text-primary/60">
            <span>Économat : −{{ number_format($variances['economat']['loss_value'] / 100, 0, ',', ' ') }}</span>
            <span>Cuisine : −{{ number_format($variances['restaurant']['loss_value'] / 100, 0, ',', ' ') }}</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-green-600">Surplus constatés cumulés</p>
        <p class="text-2xl font-bold font-mono text-green-700 mt-1">
            +{{ number_format($variances['total_surplus_value'] / 100, 0, ',', ' ') }} <span class="text-xs font-sans font-normal text-green-600/70">FCFA</span>
        </p>
        <div class="mt-2 pt-2 border-t border-secondary/15 flex justify-between text-xs text-primary/60">
            <span>Économat : +{{ number_format($variances['economat']['surplus_value'] / 100, 0, ',', ' ') }}</span>
            <span>Cuisine : +{{ number_format($variances['restaurant']['surplus_value'] / 100, 0, ',', ' ') }}</span>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Impact Net sur les Stocks</p>
        @php $net = $variances['total_variance_value']; @endphp
        <p class="text-2xl font-bold font-mono mt-1 {{ $net < 0 ? 'text-red-700' : ($net > 0 ? 'text-green-700' : 'text-primary') }}">
            {{ $net > 0 ? '+' : ($net < 0 ? '−' : '') }}{{ number_format(abs($net) / 100, 0, ',', ' ') }} <span class="text-xs font-sans font-normal text-primary/50">FCFA</span>
        </p>
        <div class="mt-2 pt-2 border-t border-secondary/15 text-xs text-primary/60">
            <span>Lignes en écart : <strong>{{ $variances['economat']['count'] + $variances['restaurant']['count'] }} article(s)</strong></span>
        </div>
    </div>
</div>

{{-- Section 1 : Écarts Magasin Central (Économat) --}}
<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-6">
    <div class="p-4 bg-surface-light border-b border-secondary/20 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="warehouse" class="w-4 h-4 text-primary"></i>
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary">
                Magasin Central — Écarts d'inventaire Économat
            </h2>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary">
                {{ $variances['economat']['count'] }} ligne(s)
            </span>
        </div>
        <div class="text-xs text-primary/60 font-mono">
            Solde net : <strong>{{ number_format($variances['economat']['variance_value'] / 100, 0, ',', ' ') }} FCFA</strong>
        </div>
    </div>

    @if($variances['economat']['lines']->isEmpty())
        <div class="p-8 text-center text-primary/40">
            <i data-lucide="check" class="w-8 h-8 text-green-500 mx-auto mb-2 opacity-60"></i>
            <p class="text-xs">Aucun écart constaté sur les inventaires du magasin central sur cette période.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-surface-light/60 border-b border-secondary/20 text-left text-primary/60 uppercase tracking-wider font-semibold">
                        <th class="py-2.5 px-3">Inventaire</th>
                        <th class="py-2.5 px-3">Article & Réf.</th>
                        <th class="py-2.5 px-3">Catégorie</th>
                        <th class="py-2.5 px-3 text-center">Théorique</th>
                        <th class="py-2.5 px-3 text-center">Compté</th>
                        <th class="py-2.5 px-3 text-center">Écart Qté</th>
                        <th class="py-2.5 px-3 text-right">P.U.</th>
                        <th class="py-2.5 px-3 text-right">Valeur Écart</th>
                        <th class="py-2.5 px-3">Motif & Justification</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($variances['economat']['lines'] as $line)
                        @php
                            $diffQty = (float) $line->variance_quantity;
                            $diffVal = (int) $line->variance_value;
                        @endphp
                        <tr class="hover:bg-surface-light/40 transition-colors">
                            <td class="py-2.5 px-3">
                                <a href="{{ route('economat.stock_counts.show', $line->stockCount) }}" class="font-mono font-bold text-primary hover:underline">
                                    {{ $line->stockCount->reference }}
                                </a>
                                <div class="text-[10px] text-primary/40">{{ $line->stockCount->closed_at?->format('d/m/Y') }}</div>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-medium text-primary">{{ $line->stockItem?->name ?? 'Article supprimé' }}</div>
                                <div class="text-[10px] font-mono text-primary/40">{{ $line->stockItem?->reference }}</div>
                            </td>
                            <td class="py-2.5 px-3 text-primary/60">
                                {{ $line->stockItem?->category?->name ?? 'Général' }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-mono text-primary/70">
                                {{ (float) $line->theoretical_quantity }} {{ $line->unit }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-mono font-bold text-primary">
                                {{ (float) $line->counted_quantity }} {{ $line->unit }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-mono font-bold {{ $diffQty < 0 ? 'text-red-700' : 'text-green-700' }}">
                                {{ $diffQty > 0 ? '+' : '' }}{{ $diffQty }} {{ $line->unit }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono text-primary/60">
                                {{ number_format($line->unit_cost / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold {{ $diffVal < 0 ? 'text-red-700' : 'text-green-700' }}">
                                {{ $diffVal > 0 ? '+' : '' }}{{ number_format($diffVal / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-2.5 px-3">
                                @if($line->variance_reason)
                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-secondary/15 text-primary">
                                        {{ $line->variance_reason }}
                                    </span>
                                @endif
                                @if($line->notes)
                                    <span class="text-[10px] text-primary/60 block mt-0.5">{{ $line->notes }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Section 2 : Écarts Garde-Manger Restaurant --}}
<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-6">
    <div class="p-4 bg-surface-light border-b border-secondary/20 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i data-lucide="utensils" class="w-4 h-4 text-amber-700"></i>
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary">
                Restaurant — Écarts d'inventaire Garde-Manger
            </h2>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900">
                {{ $variances['restaurant']['count'] }} ligne(s)
            </span>
        </div>
        <div class="text-xs text-primary/60 font-mono">
            Solde net : <strong>{{ number_format($variances['restaurant']['variance_value'] / 100, 0, ',', ' ') }} FCFA</strong>
        </div>
    </div>

    @if($variances['restaurant']['lines']->isEmpty())
        <div class="p-8 text-center text-primary/40">
            <i data-lucide="check" class="w-8 h-8 text-green-500 mx-auto mb-2 opacity-60"></i>
            <p class="text-xs">Aucun écart constaté sur les inventaires du restaurant sur cette période.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="bg-surface-light/60 border-b border-secondary/20 text-left text-primary/60 uppercase tracking-wider font-semibold">
                        <th class="py-2.5 px-3">Inventaire</th>
                        <th class="py-2.5 px-3">Produit / Ingrédient</th>
                        <th class="py-2.5 px-3">Catégorie</th>
                        <th class="py-2.5 px-3 text-center">Théorique</th>
                        <th class="py-2.5 px-3 text-center">Compté</th>
                        <th class="py-2.5 px-3 text-center">Écart Qté</th>
                        <th class="py-2.5 px-3 text-right">P.U.</th>
                        <th class="py-2.5 px-3 text-right">Valeur Écart</th>
                        <th class="py-2.5 px-3">Motif & Justification</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($variances['restaurant']['lines'] as $rLine)
                        @php
                            $rDiffQty = (float) $rLine->variance_quantity;
                            $rDiffVal = (int) $rLine->variance_value;
                        @endphp
                        <tr class="hover:bg-surface-light/40 transition-colors">
                            <td class="py-2.5 px-3">
                                <span class="font-mono font-bold text-primary">
                                    {{ $rLine->stockCount?->reference ?? 'INV-RESTO' }}
                                </span>
                                <div class="text-[10px] text-primary/40">{{ $rLine->stockCount?->closed_at?->format('d/m/Y') }}</div>
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="font-medium text-primary">{{ $rLine->item?->name ?? 'Article' }}</div>
                            </td>
                            <td class="py-2.5 px-3 text-primary/60">
                                {{ $rLine->item?->category?->name ?? 'Garde-manger' }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-mono text-primary/70">
                                {{ (float) $rLine->theoretical_quantity }} {{ $rLine->item?->unit ?? 'u' }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-mono font-bold text-primary">
                                {{ (float) $rLine->counted_quantity }} {{ $rLine->item?->unit ?? 'u' }}
                            </td>
                            <td class="py-2.5 px-3 text-center font-mono font-bold {{ $rDiffQty < 0 ? 'text-red-700' : 'text-green-700' }}">
                                {{ $rDiffQty > 0 ? '+' : '' }}{{ $rDiffQty }} {{ $rLine->item?->unit ?? 'u' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono text-primary/60">
                                {{ number_format($rLine->unit_cost / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-2.5 px-3 text-right font-mono font-bold {{ $rDiffVal < 0 ? 'text-red-700' : 'text-green-700' }}">
                                {{ $rDiffVal > 0 ? '+' : '' }}{{ number_format($rDiffVal / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-2.5 px-3">
                                @if($rLine->notes)
                                    <span class="text-[10px] text-primary/60 block">{{ $rLine->notes }}</span>
                                @else
                                    <span class="text-[10px] text-primary/40">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
