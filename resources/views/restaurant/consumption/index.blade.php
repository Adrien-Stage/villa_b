@extends('layouts.hotel')

@section('title', 'Rapprochement & Consommation Restaurant')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Rapprochement Matière & Food Cost</h1>
        <p class="text-sm text-primary/50 mt-0.5">Confrontation des entrées magasin, de la consommation théorique (recettes POS & PDJ) et des pertes</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="{{ route('restaurant.waste.index') }}" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Fiches de pertes
        </a>
        <a href="{{ route('restaurant.pantry.index') }}" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="warehouse" class="w-3.5 h-3.5"></i> Garde-manger
        </a>
    </div>
</div>

{{-- Filtre de période --}}
<div class="bg-white rounded-xl shadow-sm border border-secondary/15 p-4 mb-6">
    <form method="GET" action="{{ route('restaurant.consumption.index') }}" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Du</label>
            <input type="date" name="start_date" value="{{ $startDate }}" class="text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Au</label>
            <input type="date" name="end_date" value="{{ $endDate }}" class="text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
        </div>
        <button type="submit" class="py-2 px-4 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity">
            Actualiser
        </button>
    </form>
</div>

{{-- KPI Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15">
        <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Chiffre d'Affaires</p>
        <p class="text-xl font-heading font-semibold text-primary mt-1">
            {{ number_format($report['total_revenue'] / 100, 0, ',', ' ') }} <span class="text-xs font-normal text-primary/60">FCFA</span>
        </p>
        <p class="text-[11px] text-primary/40 mt-0.5">{{ $report['orders_count'] }} commande(s)</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15">
        <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Sorties Ventes & PDJ</p>
        <p class="text-xl font-heading font-semibold text-blue-600 mt-1">
            {{ number_format($report['theoretical_sales_cost'] / 100, 0, ',', ' ') }} <span class="text-xs font-normal text-primary/60">FCFA</span>
        </p>
        <p class="text-[11px] text-primary/40 mt-0.5">Consommation théorique recettes</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15">
        <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Gaspillage & Pertes</p>
        <p class="text-xl font-heading font-semibold text-red-600 mt-1">
            {{ number_format($report['waste_cost'] / 100, 0, ',', ' ') }} <span class="text-xs font-normal text-primary/60">FCFA</span>
        </p>
        <p class="text-[11px] text-primary/40 mt-0.5">Déchets, avariés, repas perso</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15">
        <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Écarts d'Inventaire</p>
        <p class="text-xl font-heading font-semibold {{ $report['inventory_adjustment_cost'] < 0 ? 'text-red-600' : 'text-primary' }} mt-1">
            {{ number_format($report['inventory_adjustment_cost'] / 100, 0, ',', ' ') }} <span class="text-xs font-normal text-primary/60">FCFA</span>
        </p>
        <p class="text-[11px] text-primary/40 mt-0.5">Ajustements physiques</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15 {{ $report['food_cost_ratio'] > 35 ? 'border-amber-300 bg-amber-50/20' : '' }}">
        <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Food Cost Réel</p>
        <p class="text-2xl font-heading font-semibold {{ $report['food_cost_ratio'] > 35 ? 'text-amber-600' : 'text-primary' }} mt-1">
            {{ number_format($report['food_cost_ratio'], 1, ',', ' ') }} %
        </p>
        <p class="text-[11px] text-primary/40 mt-0.5">Coût matière / Chiffre d'Affaires</p>
    </div>
</div>

{{-- Tableau de rapprochement détaillé par ingrédient --}}
<div class="bg-white rounded-xl shadow-sm border border-secondary/15 overflow-hidden">
    <div class="p-4 border-b border-secondary/15 flex items-center justify-between">
        <div>
            <h2 class="font-heading text-sm font-semibold text-primary">Détail des flux par ingrédient</h2>
            <p class="text-xs text-primary/50 mt-0.5">Période du {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="bg-secondary/10 text-primary font-semibold border-b border-secondary/20">
                <tr>
                    <th class="py-3 px-4">Article</th>
                    <th class="py-3 px-4 text-right">Entrées Économat</th>
                    <th class="py-3 px-4 text-right">Consommation Théorique (Ventes & PDJ)</th>
                    <th class="py-3 px-4 text-right">Pertes Déclarées</th>
                    <th class="py-3 px-4 text-right">Ajustements Inv.</th>
                    <th class="py-3 px-4 text-right">Stock Actuel</th>
                    <th class="py-3 px-4 text-right">Valeur Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($report['items'] as $row)
                    @php $item = $row['item']; @endphp
                    <tr class="hover:bg-accent/5 transition-colors">
                        <td class="py-3 px-4">
                            <span class="font-semibold text-primary">{{ $item->name }}</span>
                            @if($item->category)
                                <div class="text-[10px] text-primary/50">{{ $item->category->name }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            @if($row['in_transfers_qty'] > 0)
                                <span class="font-medium text-emerald-600">+{{ rtrim(rtrim(number_format($row['in_transfers_qty'], 3, ',', ' '), '0'), ',') }} {{ $item->unit }}</span>
                                <div class="text-[10px] text-primary/40">{{ number_format($row['in_transfers_cost'] / 100, 0, ',', ' ') }} FCFA</div>
                            @else
                                <span class="text-primary/30">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            @if($row['theoretical_sales_qty'] > 0)
                                <span class="font-medium text-blue-600">-{{ rtrim(rtrim(number_format($row['theoretical_sales_qty'], 3, ',', ' '), '0'), ',') }} {{ $item->unit }}</span>
                                <div class="text-[10px] text-primary/40">{{ number_format($row['theoretical_sales_cost'] / 100, 0, ',', ' ') }} FCFA</div>
                            @else
                                <span class="text-primary/30">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            @if($row['waste_qty'] > 0)
                                <span class="font-semibold text-red-600">-{{ rtrim(rtrim(number_format($row['waste_qty'], 3, ',', ' '), '0'), ',') }} {{ $item->unit }}</span>
                                <div class="text-[10px] text-red-600/70">{{ number_format($row['waste_cost'] / 100, 0, ',', ' ') }} FCFA</div>
                            @else
                                <span class="text-primary/30">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right">
                            @if(abs($row['adjustment_cost']) > 0)
                                <span class="font-medium {{ $row['adjustment_cost'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">
                                    {{ $row['adjustment_cost'] < 0 ? '-' : '+' }}{{ number_format(abs($row['adjustment_cost']) / 100, 0, ',', ' ') }} FCFA
                                </span>
                            @else
                                <span class="text-primary/30">—</span>
                            @endif
                        </td>
                        <td class="py-3 px-4 text-right font-medium text-primary">
                            {{ rtrim(rtrim(number_format((float) $item->current_stock, 3, ',', ' '), '0'), ',') }} {{ $item->unit }}
                        </td>
                        <td class="py-3 px-4 text-right font-semibold text-primary">
                            {{ number_format($item->stockValue() / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-primary/40">
                            Aucun mouvement enregistré sur cette période.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
