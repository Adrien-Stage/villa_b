@extends('layouts.hotel')

@section('title', 'Tableau de bord Boutique')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-primary">Vue d'ensemble Boutique</h1>
            <p class="text-secondary mt-1">Performances et actions rapides</p>
        </div>
        
        <div class="flex gap-3">
            @if(!$hasActiveSession)
                <a href="{{ route('shop.cash_register.open') }}" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors shadow-sm inline-flex items-center">
                    <i data-lucide="lock-open" class="w-4 h-4 mr-2"></i> Ouvrir ma caisse
                </a>
            @else
                <a href="{{ route('shop.orders.create') }}" class="bg-primary hover:bg-surface-dark text-white px-5 py-2.5 rounded-lg text-sm font-medium transition-colors shadow-sm inline-flex items-center">
                    <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Créer une vente
                </a>
                {{-- Celui qui a ouvert sa caisse la ferme lui-même --}}
                <a href="{{ route('shop.cash_register.close') }}" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors shadow-sm inline-flex items-center text-center" title="Fermer la caisse">
                    <i data-lucide="lock" class="w-4 h-4"></i>
                </a>
            @endif
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <!-- Chiffre d'affaires Aujourd'hui -->
        <div class="bg-white rounded-xl p-6 border border-secondary/10 shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i data-lucide="trending-up" class="w-16 h-16 text-primary"></i>
            </div>
            <h3 class="text-xs font-bold uppercase tracking-widest text-primary/50 mb-2">Revenus via Boutique (Aujourd'hui)</h3>
            <div class="flex items-baseline gap-3">
                <span class="text-3xl font-heading font-black text-primary">{{ number_format($revenueToday / 100, 0, ',', ' ') }} <span class="text-lg">FCFA</span></span>
            </div>
            
            @php
                $diff = $revenueToday - $revenueYesterday;
                $percent = $revenueYesterday > 0 ? ($diff / $revenueYesterday) * 100 : 0;
            @endphp
            
            <div class="mt-4 text-xs font-medium flex items-center gap-1">
                @if($diff > 0)
                    <span class="text-green-600 bg-green-50 px-2 py-0.5 rounded-full"><i data-lucide="arrow-up-right" class="w-3 h-3 inline"></i> +{{ number_format($percent, 1) }}%</span>
                    <span class="text-secondary/70">par rapport à hier</span>
                @elseif($diff < 0)
                    <span class="text-red-500 bg-red-50 px-2 py-0.5 rounded-full"><i data-lucide="arrow-down-right" class="w-3 h-3 inline"></i> {{ number_format($percent, 1) }}%</span>
                    <span class="text-secondary/70">par rapport à hier</span>
                @else
                    <span class="text-secondary/70">Même niveau qu'hier</span>
                @endif
            </div>
        </div>

        <!-- Commandes -->
        <div class="bg-white rounded-xl p-6 border border-secondary/10 shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i data-lucide="shopping-bag" class="w-16 h-16 text-primary"></i>
            </div>
            <h3 class="text-xs font-bold uppercase tracking-widest text-primary/50 mb-2">Commandes validées</h3>
            <div class="flex items-baseline gap-3">
                <span class="text-3xl font-heading font-black text-primary">{{ $ordersCountToday }}</span>
            </div>
            <div class="mt-4 text-xs text-secondary/70 flex items-center">
                <i data-lucide="calendar" class="w-3.5 h-3.5 mr-1"></i> Aujourd'hui
            </div>
        </div>

        <!-- Articles vendus -->
        <div class="bg-white rounded-xl p-6 border border-secondary/10 shadow-sm relative overflow-hidden group">
            <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
                <i data-lucide="package" class="w-16 h-16 text-primary"></i>
            </div>
            <h3 class="text-xs font-bold uppercase tracking-widest text-primary/50 mb-2">Articles vendus</h3>
            <div class="flex items-baseline gap-3">
                <span class="text-3xl font-heading font-black text-primary">{{ $itemsSoldToday }}</span>
            </div>
            <div class="mt-4 text-xs text-secondary/70 flex items-center">
                <i data-lucide="calendar" class="w-3.5 h-3.5 mr-1"></i> Aujourd'hui
            </div>
        </div>
    </div>

    <!-- 2 Cols : Top Ventes et Alertes Stock -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Top Produits -->
        <div class="bg-white rounded-xl border border-secondary/10 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-secondary/10 flex justify-between items-center bg-gray-50/50">
                <h3 class="font-heading font-semibold text-primary flex items-center gap-2">
                    <i data-lucide="star" class="w-4 h-4 text-yellow-500 fill-yellow-500"></i> Meilleurs ventes du mois
                </h3>
            </div>
            <div class="p-6">
                @if($topProducts->count() > 0)
                    <div class="space-y-4">
                        @foreach($topProducts as $index => $item)
                            <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                                <div class="flex items-center gap-4">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm
                                        {{ $index === 0 ? 'bg-yellow-100 text-yellow-700' : ($index === 1 ? 'bg-gray-200 text-gray-700' : 'bg-orange-100 text-orange-700') }}">
                                        #{{ $index + 1 }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-primary text-sm">{{ $item->product->name ?? 'Produit inconnu' }}</p>
                                        <p class="text-xs text-secondary">{{ $item->product->category->name ?? 'Catégorie' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="inline-block bg-primary/10 text-primary px-3 py-1 rounded-md text-sm font-bold">
                                        {{ $item->total_quantity }} <span class="font-normal text-xs opacity-70">vendus</span>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-secondary/50">
                        <i data-lucide="bar-chart-2" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
                        <p class="text-sm">Pas assez de données pour ce mois.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Alertes de stocks -->
        <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-red-100 flex justify-between items-center bg-red-50/30">
                <h3 class="font-heading font-semibold text-red-800 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i> Articles presque épuisés
                </h3>
            </div>
            <div class="p-6">
                @if($lowStockProducts->count() > 0)
                    <div class="space-y-3">
                        @foreach($lowStockProducts as $product)
                            <div class="flex items-center justify-between border-b border-gray-100 last:border-0 pb-3 last:pb-0">
                                <div>
                                    <p class="font-medium text-gray-900 text-sm">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $product->category->name ?? '' }}</p>
                                </div>
                                <div>
                                    @if($product->stock_quantity <= 0)
                                        <span class="text-xs font-bold bg-red-100 text-red-700 px-2.5 py-1 rounded-sm border border-red-200">
                                            Rupture (0)
                                        </span>
                                    @else
                                        <span class="text-xs font-bold bg-orange-100 text-orange-800 px-2.5 py-1 rounded-sm border border-orange-200">
                                            Reste: {{ $product->stock_quantity }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-green-600/70">
                        <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-3 opacity-50"></i>
                        <p class="text-sm">Tous vos stocks sont à un niveau correct.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
