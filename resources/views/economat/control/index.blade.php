@extends('layouts.hotel')

@section('title', 'Contrôle général des stocks & Food Cost — Économat')

@section('content')
<div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading flex items-center gap-2">
            <i data-lucide="pie-chart" class="w-7 h-7 text-primary"></i>
            <span>Contrôle général des stocks & Food Cost</span>
        </h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            Tableau de bord de pilotage consolidé : Magasin central (Économat) et Garde-manger (Restaurant). Valorisation au CUMP, ratios matières, flux de marchandises et détection des seuils de réapprovisionnement.
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 shrink-0">
        @droit('economat.control.suggestions.voir')
            <a href="{{ route('economat.control.suggestions.index') }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                <span>Propositions d'achat</span>
                @if($report['alerts']['total'] > 0)
                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-amber-400 text-amber-950 font-bold text-[10px]">
                        {{ $report['alerts']['total'] }}
                    </span>
                @endif
            </a>
        @enddroit

        @droit('economat.control.variances.voir')
            <a href="{{ route('economat.control.variances.index', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-secondary/30 text-primary text-xs font-medium rounded-lg hover:bg-surface-light transition-colors shadow-sm">
                <i data-lucide="git-compare" class="w-4 h-4 text-primary/60"></i>
                <span>Audit des écarts</span>
            </a>
        @enddroit

        @droit('economat.control.voir')
            <a href="{{ route('economat.control.print', ['start_date' => $startDate, 'end_date' => $endDate]) }}" target="_blank"
                class="inline-flex items-center gap-2 px-3.5 py-2 bg-white border border-secondary/30 text-primary text-xs font-medium rounded-lg hover:bg-surface-light transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4 text-primary/60"></i>
                <span>Imprimer le rapport</span>
            </a>
        @enddroit
    </div>
</div>

@include('economat.partials.flash')

{{-- Barre de filtrage par période --}}
<div class="bg-white border border-secondary/20 rounded-xl p-4 mb-6 shadow-sm">
    <form method="GET" action="{{ route('economat.control.index') }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <i data-lucide="calendar" class="w-4 h-4 text-primary/50"></i>
            <span class="text-xs font-semibold uppercase tracking-wider text-primary/70">Période d'analyse :</span>
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
                Actualiser
            </button>
            @if(request()->has('start_date') || request()->has('end_date'))
                <a href="{{ route('economat.control.index') }}" class="text-xs text-primary/50 hover:text-primary underline">
                    Réinitialiser
                </a>
            @endif
        </div>
    </form>
</div>

{{-- 4 Métriques Clés --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    {{-- Valorisation totale --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm relative overflow-hidden">
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Stock Total Consolidé</p>
            <span class="p-1.5 rounded-lg bg-primary/5 text-primary">
                <i data-lucide="wallet" class="w-4 h-4"></i>
            </span>
        </div>
        <p class="text-2xl font-bold font-mono text-primary mt-2">
            {{ number_format($report['valuation']['total_value'] / 100, 0, ',', ' ') }} <span class="text-xs font-sans font-normal text-primary/50">FCFA</span>
        </p>
        <div class="mt-3 pt-2.5 border-t border-secondary/15 flex items-center justify-between text-xs text-primary/60">
            <span>Économat : <strong>{{ number_format($report['valuation']['economat_value'] / 100, 0, ',', ' ') }}</strong></span>
            <span>Restaurant : <strong>{{ number_format($report['valuation']['pantry_value'] / 100, 0, ',', ' ') }}</strong></span>
        </div>
    </div>

    {{-- Food Cost % --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm relative overflow-hidden">
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Ratio Food Cost</p>
            <span class="p-1.5 rounded-lg {{ $report['food_cost_percent'] > 35 ? 'bg-red-50 text-red-600' : ($report['food_cost_percent'] > 30 ? 'bg-amber-50 text-amber-600' : 'bg-green-50 text-green-600') }}">
                <i data-lucide="utensils" class="w-4 h-4"></i>
            </span>
        </div>
        <div class="flex items-baseline gap-2 mt-2">
            <p class="text-2xl font-bold font-mono {{ $report['food_cost_percent'] > 35 ? 'text-red-700' : ($report['food_cost_percent'] > 30 ? 'text-amber-700' : 'text-green-700') }}">
                {{ number_format($report['food_cost_percent'], 1) }}%
            </p>
            <span class="text-xs text-primary/50">du CA Resto</span>
        </div>
        <div class="mt-3 pt-2.5 border-t border-secondary/15 flex items-center justify-between text-xs">
            <span class="text-primary/60">CA : {{ number_format($report['restaurant_revenue'] / 100, 0, ',', ' ') }} FCFA</span>
            <span class="font-medium {{ $report['food_cost_percent'] > 35 ? 'text-red-600' : ($report['food_cost_percent'] > 30 ? 'text-amber-600' : 'text-green-600') }}">
                {{ $report['food_cost_percent'] > 35 ? 'Critique' : ($report['food_cost_percent'] > 30 ? 'Vigilance' : 'Optimal') }}
            </span>
        </div>
    </div>

    {{-- Flux Marchandises Période --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm relative overflow-hidden">
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Achats & Livraisons</p>
            <span class="p-1.5 rounded-lg bg-blue-50 text-blue-600">
                <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
            </span>
        </div>
        <p class="text-2xl font-bold font-mono text-primary mt-2">
            {{ number_format($report['total_purchases_received'] / 100, 0, ',', ' ') }} <span class="text-xs font-sans font-normal text-primary/50">FCFA</span>
        </p>
        <div class="mt-3 pt-2.5 border-t border-secondary/15 flex items-center justify-between text-xs text-primary/60">
            <span>Entrées BL : <strong>{{ number_format($report['total_purchases_received'] / 100, 0, ',', ' ') }}</strong></span>
            <span>Sorties livrées : <strong>{{ number_format($report['total_requisitions_delivered'] / 100, 0, ',', ' ') }}</strong></span>
        </div>
    </div>

    {{-- Alertes Stock Minimum --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm relative overflow-hidden">
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Articles sous seuil</p>
            <span class="p-1.5 rounded-lg {{ $report['alerts']['total'] > 0 ? 'bg-amber-100 text-amber-700' : 'bg-green-50 text-green-600' }}">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            </span>
        </div>
        <div class="flex items-baseline gap-2 mt-2">
            <p class="text-2xl font-bold font-mono {{ $report['alerts']['total'] > 0 ? 'text-amber-800' : 'text-green-700' }}">
                {{ $report['alerts']['total'] }}
            </p>
            <span class="text-xs text-primary/50">références</span>
        </div>
        <div class="mt-3 pt-2.5 border-t border-secondary/15 flex items-center justify-between text-xs text-primary/60">
            <span>Magasin : <strong>{{ $report['alerts']['economat']->count() }}</strong></span>
            <span>Cuisine : <strong>{{ $report['alerts']['pantry']->count() }}</strong></span>
        </div>
    </div>
</div>

{{-- Section Décomposition Food Cost & Pertes --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    {{-- Calcul détaillé du Food Cost --}}
    <div class="lg:col-span-2 bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
        <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2 mb-4">
            <i data-lucide="layers" class="w-4 h-4 text-primary/70"></i>
            <span>Décomposition du coût matière (Food Cost Restaurant)</span>
        </h2>

        <div class="space-y-3">
            <div class="flex items-center justify-between py-2 border-b border-secondary/15 text-sm">
                <span class="text-primary/70">Chiffre d'Affaires Restaurant HT</span>
                <span class="font-bold font-mono text-primary">{{ number_format($report['restaurant_revenue'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>

            <div class="flex items-center justify-between py-2 border-b border-secondary/15 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    <span class="text-primary/70">Coût théorique des fiches techniques (ventes)</span>
                </div>
                <span class="font-mono text-primary">{{ number_format($report['theoretical_food_cost'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>

            <div class="flex items-center justify-between py-2 border-b border-secondary/15 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span class="text-primary/70">Déchets, avaries & coulages cuisine déclarés</span>
                </div>
                <span class="font-mono text-amber-700">+{{ number_format($report['total_waste_value'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>

            <div class="flex items-center justify-between py-2 border-b border-secondary/15 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <span class="text-primary/70">Manquants constatés aux inventaires cuisine</span>
                </div>
                <span class="font-mono text-red-700">+{{ number_format($report['variances']['restaurant']['loss_value'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>

            <div class="flex items-center justify-between py-2.5 bg-surface-light px-3 rounded-lg text-sm font-semibold border border-secondary/20">
                <span class="text-primary font-bold">Coût matière réel total consommé</span>
                <span class="font-mono text-primary text-base">{{ number_format($report['real_kitchen_cost'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>

        <div class="mt-4 p-3 rounded-lg bg-blue-50/70 border border-blue-200 text-xs text-blue-900 flex items-start gap-2">
            <i data-lucide="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
            <div>
                <strong>Formule de calcul :</strong> Ratio Food Cost = (Coût théorique + Pertes & avaries + Pertes d'inventaire) / Chiffre d'Affaires × 100.
                Le ratio recommandé en hôtellerie-restauration se situe entre <strong>28% et 32%</strong>.
            </div>
        </div>
    </div>

    {{-- Synthèse des écarts d'inventaire --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm flex flex-col justify-between">
        <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2 mb-4">
                <i data-lucide="git-compare" class="w-4 h-4 text-primary/70"></i>
                <span>Écarts d'inventaire constatés</span>
            </h2>

            <div class="space-y-3">
                <div class="p-3 rounded-lg bg-red-50 border border-red-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-red-700">Pertes & Manquants</p>
                    <p class="text-lg font-bold font-mono text-red-800 mt-1">
                        −{{ number_format($report['variances']['total_loss_value'] / 100, 0, ',', ' ') }} FCFA
                    </p>
                    <div class="flex justify-between text-xs text-red-600/80 mt-1">
                        <span>Économat : −{{ number_format($report['variances']['economat']['loss_value'] / 100, 0, ',', ' ') }}</span>
                        <span>Resto : −{{ number_format($report['variances']['restaurant']['loss_value'] / 100, 0, ',', ' ') }}</span>
                    </div>
                </div>

                <div class="p-3 rounded-lg bg-green-50 border border-green-200">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-green-700">Surplus constatés</p>
                    <p class="text-lg font-bold font-mono text-green-800 mt-1">
                        +{{ number_format($report['variances']['total_surplus_value'] / 100, 0, ',', ' ') }} FCFA
                    </p>
                    <div class="flex justify-between text-xs text-green-600/80 mt-1">
                        <span>Économat : +{{ number_format($report['variances']['economat']['surplus_value'] / 100, 0, ',', ' ') }}</span>
                        <span>Resto : +{{ number_format($report['variances']['restaurant']['surplus_value'] / 100, 0, ',', ' ') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-secondary/15">
            <a href="{{ route('economat.control.variances.index', ['start_date' => $startDate, 'end_date' => $endDate]) }}"
                class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 bg-secondary/10 hover:bg-secondary/20 text-primary text-xs font-medium rounded-lg transition-colors">
                <span>Détail complet des écarts par article</span>
                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </div>
</div>

{{-- Valorisation par catégorie & Articles sous seuil critique --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Répartition de la valeur de stock --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
        <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2 mb-4">
            <i data-lucide="boxes" class="w-4 h-4 text-primary/70"></i>
            <span>Valorisation par catégorie de stock</span>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-secondary/20 text-left text-primary/50 uppercase tracking-wider">
                        <th class="py-2">Catégorie</th>
                        <th class="py-2 text-center">Articles</th>
                        <th class="py-2 text-right">Valeur CUMP</th>
                        <th class="py-2 text-right">% Stock</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @php $totalVal = max(1, $report['valuation']['total_value']); @endphp
                    @forelse($report['valuation']['categories'] as $cat)
                        @php $pct = round(($cat['value'] / $totalVal) * 100, 1); @endphp
                        <tr>
                            <td class="py-2.5 font-medium text-primary flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full {{ ($cat['type'] ?? '') === 'restaurant' ? 'bg-amber-500' : 'bg-primary' }}"></span>
                                <span>{{ $cat['name'] }}</span>
                            </td>
                            <td class="py-2.5 text-center text-primary/60 font-mono">{{ $cat['count'] }}</td>
                            <td class="py-2.5 text-right font-mono font-bold text-primary">
                                {{ number_format($cat['value'] / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="py-2.5 text-right font-mono text-primary/60">
                                {{ $pct }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-primary/40">Aucune donnée de valorisation.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Articles prioritaires à réapprovisionner --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-primary flex items-center gap-2">
                    <i data-lucide="alert-octagon" class="w-4 h-4 text-amber-600"></i>
                    <span>Articles sous seuil de sécurité (Économat)</span>
                </h2>
                @if($report['alerts']['economat']->isNotEmpty())
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900">
                        {{ $report['alerts']['economat']->count() }} alerte(s)
                    </span>
                @endif
            </div>

            @if($report['alerts']['economat']->isEmpty())
                <div class="py-8 text-center text-primary/40">
                    <i data-lucide="check-circle" class="w-10 h-10 text-green-500 mx-auto mb-2 opacity-60"></i>
                    <p class="text-xs">Tous les stocks du magasin central sont au-dessus du seuil minimum.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-secondary/20 text-left text-primary/50 uppercase tracking-wider">
                                <th class="py-2">Réf. / Article</th>
                                <th class="py-2 text-center">Stock Actuel</th>
                                <th class="py-2 text-center">Stock Min</th>
                                <th class="py-2 text-right">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-secondary/10">
                            @foreach($report['alerts']['economat']->take(6) as $item)
                                <tr>
                                    <td class="py-2">
                                        <div class="font-medium text-primary">{{ $item->name }}</div>
                                        <div class="text-[10px] font-mono text-primary/40">{{ $item->reference }}</div>
                                    </td>
                                    <td class="py-2 text-center font-mono font-bold {{ $item->current_stock <= 0 ? 'text-red-600' : 'text-amber-700' }}">
                                        {{ (float) $item->current_stock }} {{ $item->unit }}
                                    </td>
                                    <td class="py-2 text-center font-mono text-primary/50">
                                        {{ (float) $item->min_stock }} {{ $item->unit }}
                                    </td>
                                    <td class="py-2 text-right">
                                        @if($item->current_stock <= 0)
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">
                                                Rupture
                                            </span>
                                        @else
                                            <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                                Stock bas
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        @droit('economat.control.suggestions.voir')
            <div class="mt-4 pt-4 border-t border-secondary/15">
                <a href="{{ route('economat.control.suggestions.index') }}"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 bg-primary hover:bg-surface-dark text-white text-xs font-medium rounded-lg transition-colors shadow-sm">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i>
                    <span>Générer les commandes de réapprovisionnement</span>
                </a>
            </div>
        @enddroit
    </div>
</div>
@endsection
