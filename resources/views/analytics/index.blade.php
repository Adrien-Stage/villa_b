@extends('layouts.hotel')

@section('title', 'Tour de contrôle (Analytiques)')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    <!-- En-tête et Filtres -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="font-heading text-2xl font-semibold text-primary">Tour de contrôle</h1>
            <p class="text-sm text-primary/50 mt-1">Supervision globale des performances de l'hôtel</p>
        </div>

        <div class="flex items-center gap-3">
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" @click.away="open = false" 
                        class="flex items-center gap-2 px-4 py-1.5 text-xs font-medium rounded-md bg-white border border-secondary/20 shadow-sm text-primary hover:bg-accent/20 transition-colors">
                    <i data-lucide="printer" class="w-4 h-4 text-primary/70"></i>
                    Imprimer le rapport
                    <i data-lucide="chevron-down" class="w-3 h-3 ml-1 opacity-50"></i>
                </button>
                <div x-show="open" x-transition style="display: none;" 
                     class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-secondary/20 py-1 z-50">
                    <a href="{{ route('analytics.print', ['period' => $period, 'department' => 'all']) }}" target="_blank"
                       class="block px-4 py-2 text-xs text-primary hover:bg-accent/20">Rapport Global</a>
                    <a href="{{ route('analytics.print', ['period' => $period, 'department' => 'hotel']) }}" target="_blank"
                       class="block px-4 py-2 text-xs text-primary hover:bg-accent/20">Rapport Hébergement</a>
                    <a href="{{ route('analytics.print', ['period' => $period, 'department' => 'restaurant']) }}" target="_blank"
                       class="block px-4 py-2 text-xs text-primary hover:bg-accent/20">Rapport Restaurant</a>
                    <a href="{{ route('analytics.print', ['period' => $period, 'department' => 'shop']) }}" target="_blank"
                       class="block px-4 py-2 text-xs text-primary hover:bg-accent/20">Rapport Boutique</a>
                </div>
            </div>

            <div class="flex bg-white rounded-lg p-1 border border-secondary/20 shadow-sm">
                @php
                    $periods = [
                        'today' => 'Aujourd\'hui',
                        'week'  => 'Cette semaine',
                        'month' => 'Ce mois',
                        'year'  => 'Cette année'
                    ];
                @endphp
                @foreach($periods as $key => $label)
                    <a href="{{ route('analytics.index', ['period' => $key]) }}"
                       class="px-4 py-1.5 text-xs font-medium rounded-md transition-colors
                       {{ $period === $key ? 'bg-primary text-white shadow' : 'text-primary/60 hover:text-primary hover:bg-accent/20' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- KPIs Principaux -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Chiffre d'Affaires Global -->
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-gradient-to-br from-green-100 to-transparent opacity-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-xs font-medium text-primary/50 uppercase tracking-wider">CA Global</p>
                    <h3 class="text-2xl font-bold text-primary mt-1">{{ number_format($totalRevenue / 100, 0, ',', ' ') }} <span class="text-sm font-normal text-primary/50">FCFA</span></h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-green-50 flex items-center justify-center text-green-600">
                    <i data-lucide="trending-up" class="w-5 h-5"></i>
                </div>
            </div>
            <p class="text-xs text-primary/40 mt-4 flex items-center gap-1">
                <i data-lucide="calendar" class="w-3 h-3"></i> Période : {{ strtolower($periods[$period] ?? '') }}
            </p>
        </div>

        <!-- Revenus Hôtel -->
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-gradient-to-br from-blue-100 to-transparent opacity-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-xs font-medium text-primary/50 uppercase tracking-wider">Hébergement</p>
                    <h3 class="text-2xl font-bold text-primary mt-1">{{ number_format($hotelRevenue / 100, 0, ',', ' ') }}</h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                    <i data-lucide="bed-double" class="w-5 h-5"></i>
                </div>
            </div>
            @php $hotelPercent = $totalRevenue > 0 ? ($hotelRevenue / $totalRevenue) * 100 : 0; @endphp
            <div class="mt-4 flex items-center gap-2">
                <div class="flex-1 h-1.5 bg-blue-50 rounded-full overflow-hidden">
                    <div class="h-full bg-blue-500 rounded-full" style="width: {{ $hotelPercent }}%"></div>
                </div>
                <span class="text-xs font-medium text-blue-600 w-9 text-right">{{ number_format($hotelPercent, 1) }}%</span>
            </div>
        </div>

        <!-- Revenus Restaurant -->
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-gradient-to-br from-orange-100 to-transparent opacity-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-xs font-medium text-primary/50 uppercase tracking-wider">Restaurant</p>
                    <h3 class="text-2xl font-bold text-primary mt-1">{{ number_format($restaurantRevenue / 100, 0, ',', ' ') }}</h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-orange-50 flex items-center justify-center text-orange-600">
                    <i data-lucide="utensils" class="w-5 h-5"></i>
                </div>
            </div>
            @php $restPercent = $totalRevenue > 0 ? ($restaurantRevenue / $totalRevenue) * 100 : 0; @endphp
            <div class="mt-4 flex items-center gap-2">
                <div class="flex-1 h-1.5 bg-orange-50 rounded-full overflow-hidden">
                    <div class="h-full bg-orange-500 rounded-full" style="width: {{ $restPercent }}%"></div>
                </div>
                <span class="text-xs font-medium text-orange-600 w-9 text-right">{{ number_format($restPercent, 1) }}%</span>
            </div>
        </div>

        <!-- Revenus Boutique -->
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-24 h-24 bg-gradient-to-br from-purple-100 to-transparent opacity-50 rounded-bl-full -mr-8 -mt-8 transition-transform group-hover:scale-110"></div>
            <div class="flex justify-between items-start relative z-10">
                <div>
                    <p class="text-xs font-medium text-primary/50 uppercase tracking-wider">Boutique</p>
                    <h3 class="text-2xl font-bold text-primary mt-1">{{ number_format($shopRevenue / 100, 0, ',', ' ') }}</h3>
                </div>
                <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center text-purple-600">
                    <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                </div>
            </div>
            @php $shopPercent = $totalRevenue > 0 ? ($shopRevenue / $totalRevenue) * 100 : 0; @endphp
            <div class="mt-4 flex items-center gap-2">
                <div class="flex-1 h-1.5 bg-purple-50 rounded-full overflow-hidden">
                    <div class="h-full bg-purple-500 rounded-full" style="width: {{ $shopPercent }}%"></div>
                </div>
                <span class="text-xs font-medium text-purple-600 w-9 text-right">{{ number_format($shopPercent, 1) }}%</span>
            </div>
        </div>
    </div>

    <!-- Graphiques -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Chart: CA par secteur (Line chart) -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-secondary/10 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading font-semibold text-primary">Évolution des revenus</h2>
                <button class="text-primary/40 hover:text-primary transition-colors">
                    <i data-lucide="download" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="relative h-72 w-full">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Chart: Répartition (Doughnut) -->
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading font-semibold text-primary">Répartition globale</h2>
            </div>
            <div class="relative h-56 w-full flex justify-center">
                <canvas id="distributionChart"></canvas>
            </div>
            
            <div class="mt-6 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>
                        <span class="text-primary/70">Hébergement</span>
                    </div>
                    <span class="font-medium text-primary">{{ number_format($hotelPercent, 1) }}%</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#f97316]"></span>
                        <span class="text-primary/70">Restaurant</span>
                    </div>
                    <span class="font-medium text-primary">{{ number_format($restPercent, 1) }}%</span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#a855f7]"></span>
                        <span class="text-primary/70">Boutique</span>
                    </div>
                    <span class="font-medium text-primary">{{ number_format($shopPercent, 1) }}%</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Autres stats (Réservations etc) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-accent/30 flex items-center justify-center text-primary">
                <i data-lucide="book-open-check" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="text-xs text-primary/50 uppercase tracking-wider font-medium">Nouvelles Réservations</p>
                <p class="text-xl font-bold text-primary mt-0.5">{{ $bookingsCount }}</p>
            </div>
        </div>
        <!-- Espace pour d'autres KPIs futurs (taux d'occupation etc) -->
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 flex items-center justify-center opacity-50 border-dashed">
            <p class="text-xs text-primary/40 text-center">Indicateur Taux d'Occupation<br>(À venir)</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 flex items-center justify-center opacity-50 border-dashed">
            <p class="text-xs text-primary/40 text-center">Indicateur Coût d'Acquisition<br>(À venir)</p>
        </div>
    </div>

</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // Variables du Backend
    const labels = @json($chartLabels);
    const dataHotel = @json($chartHotel);
    const dataRestaurant = @json($chartRestaurant);
    const dataShop = @json($chartShop);

    // Configuration globale Chart.js
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#8A7B6B'; // text-primary/50

    // Graphique Linéaire (Évolution)
    const ctxRevenue = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctxRevenue, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Hébergement (FCFA)',
                    data: dataHotel,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#3b82f6'
                },
                {
                    label: 'Restaurant (FCFA)',
                    data: dataRestaurant,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.05)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 2,
                    pointBackgroundColor: '#f97316'
                },
                {
                    label: 'Boutique (FCFA)',
                    data: dataShop,
                    borderColor: '#a855f7',
                    backgroundColor: 'rgba(168, 85, 247, 0.05)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 2,
                    pointBackgroundColor: '#a855f7'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        font: { size: 11 }
                    }
                },
                tooltip: {
                    backgroundColor: '#372c24', // bg-primary
                    titleColor: '#c4a882', // text-secondary
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('fr-FR').format(context.parsed.y);
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false, drawBorder: false }
                },
                y: {
                    grid: { color: 'rgba(138, 123, 107, 0.1)', drawBorder: false },
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('fr-FR', { notation: "compact", compactDisplay: "short" }).format(value);
                        }
                    }
                }
            }
        }
    });

    // Graphique Doughnut (Répartition)
    const ctxDist = document.getElementById('distributionChart').getContext('2d');
    const hotelPercent = {{ number_format($hotelPercent, 1, '.', '') }};
    const restPercent = {{ number_format($restPercent, 1, '.', '') }};
    const shopPercent = {{ number_format($shopPercent, 1, '.', '') }};
    
    // Si tout est 0, afficher un graphique vide gris
    const hasData = hotelPercent > 0 || restPercent > 0 || shopPercent > 0;
    
    new Chart(ctxDist, {
        type: 'doughnut',
        data: {
            labels: ['Hébergement', 'Restaurant', 'Boutique'],
            datasets: [{
                data: hasData ? [hotelPercent, restPercent, shopPercent] : [1],
                backgroundColor: hasData ? ['#3b82f6', '#f97316', '#a855f7'] : ['#e5e7eb'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: hasData,
                    backgroundColor: '#372c24',
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + context.parsed + '%';
                        }
                    }
                }
            }
        }
    });
});
</script>
@endpush
@endsection
