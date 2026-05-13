@extends('layouts.hotel')

@section('title', 'Impression Rapport')

@section('content')

{{-- En-tête --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('analytics.index', ['period' => $period]) }}"
            class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour au tableau de bord
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">
            Rapport Analytique
        </h1>
        <p class="text-sm text-primary/50 mt-0.5">
            Période : {{ $startDate->locale('fr')->isoFormat('D MMM YYYY') }} au {{ $endDate->locale('fr')->isoFormat('D MMM YYYY') }}
        </p>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-2">
        <button onclick="window.print()"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="printer" class="w-4 h-4"></i>
            Imprimer
        </button>
    </div>
</div>

{{-- Corps du rapport --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden max-w-4xl" id="report-print">

    {{-- En-tête du rapport (Style Facture) --}}
    <div class="px-8 py-6 border-b border-secondary/10">
        <div class="flex items-start justify-between">

            {{-- Infos établissement --}}
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-full overflow-hidden border border-secondary/20 flex-shrink-0">
                        <img src="{{ asset('images/logo.png') }}"
                            alt="Villa Boutanga"
                            class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h2 class="font-heading text-xl font-bold text-primary">Villa Boutanga</h2>
                        <p class="text-xs text-primary/50">Établissement hôtelier</p>
                    </div>
                </div>
                <p class="text-xs text-primary/50">Bafoussam, Cameroun</p>
            </div>

            <div class="text-right">
                <h3 class="text-lg font-heading font-bold text-primary uppercase tracking-wider">
                    @if($department === 'all')
                        Rapport Global
                    @elseif($department === 'hotel')
                        Rapport Hébergement
                    @elseif($department === 'restaurant')
                        Rapport Restaurant
                    @elseif($department === 'shop')
                        Rapport Boutique
                    @endif
                </h3>
                <p class="text-xs font-semibold text-primary/50 mt-2 uppercase tracking-widest">Période</p>
                <p class="text-sm text-primary font-medium">
                    Du {{ $startDate->format('d/m/Y') }} au {{ $endDate->format('d/m/Y') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Résumé Financier --}}
    <div class="px-8 py-6 border-b border-secondary/10 grid grid-cols-2 lg:grid-cols-4 gap-6">
        @if($department === 'all' || $department === 'hotel')
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Hébergement</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($hotelRevenue / 100, 0, ',', ' ') }} <span class="text-xs font-normal">FCFA</span></p>
            <p class="text-xs text-primary/50 mt-1">{{ $bookingsCount }} Réservation(s)</p>
        </div>
        @endif

        @if($department === 'all' || $department === 'restaurant')
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Restaurant</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($restaurantRevenue / 100, 0, ',', ' ') }} <span class="text-xs font-normal">FCFA</span></p>
            <p class="text-xs text-primary/50 mt-1">{{ $restaurantOrdersCount }} Commande(s)</p>
        </div>
        @endif

        @if($department === 'all' || $department === 'shop')
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Boutique</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($shopRevenue / 100, 0, ',', ' ') }} <span class="text-xs font-normal">FCFA</span></p>
            <p class="text-xs text-primary/50 mt-1">{{ $shopOrdersCount }} Vente(s)</p>
        </div>
        @endif

        @if($department === 'all')
        <div class="lg:border-l lg:border-secondary/20 lg:pl-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">CA Total</p>
            <p class="text-2xl font-bold text-primary">{{ number_format($totalRevenue / 100, 0, ',', ' ') }} <span class="text-xs font-normal">FCFA</span></p>
        </div>
        @endif
    </div>

    @if($department === 'all')
    {{-- Répartition --}}
    <div class="px-8 py-4">
        <div class="grid grid-cols-12 gap-4 py-2 border-b border-secondary/20 mb-1">
            <div class="col-span-6 text-xs font-semibold uppercase tracking-widest text-primary/40">Département</div>
            <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">Montant (FCFA)</div>
            <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">Part (%)</div>
        </div>

        <div class="grid grid-cols-12 gap-4 py-3 border-b border-secondary/10 items-center">
            <div class="col-span-6 text-sm text-primary font-medium">Hébergement</div>
            <div class="col-span-3 text-sm text-primary/70 text-right">{{ number_format($hotelRevenue / 100, 0, ',', ' ') }}</div>
            <div class="col-span-3 text-sm font-medium text-primary text-right">{{ $totalRevenue > 0 ? number_format(($hotelRevenue / $totalRevenue) * 100, 1) : 0 }}%</div>
        </div>
        <div class="grid grid-cols-12 gap-4 py-3 border-b border-secondary/10 items-center">
            <div class="col-span-6 text-sm text-primary font-medium">Restaurant</div>
            <div class="col-span-3 text-sm text-primary/70 text-right">{{ number_format($restaurantRevenue / 100, 0, ',', ' ') }}</div>
            <div class="col-span-3 text-sm font-medium text-primary text-right">{{ $totalRevenue > 0 ? number_format(($restaurantRevenue / $totalRevenue) * 100, 1) : 0 }}%</div>
        </div>
        <div class="grid grid-cols-12 gap-4 py-3 border-b border-secondary/10 items-center">
            <div class="col-span-6 text-sm text-primary font-medium">Boutique</div>
            <div class="col-span-3 text-sm text-primary/70 text-right">{{ number_format($shopRevenue / 100, 0, ',', ' ') }}</div>
            <div class="col-span-3 text-sm font-medium text-primary text-right">{{ $totalRevenue > 0 ? number_format(($shopRevenue / $totalRevenue) * 100, 1) : 0 }}%</div>
        </div>

        {{-- Total --}}
        <div class="grid grid-cols-12 gap-4 py-4 items-center bg-accent/5 rounded-lg mt-2 px-2">
            <div class="col-span-6 text-sm font-bold text-primary">TOTAL GÉNÉRAL</div>
            <div class="col-span-3 text-sm font-bold text-primary text-right">{{ number_format($totalRevenue / 100, 0, ',', ' ') }}</div>
            <div class="col-span-3 text-sm font-bold text-primary text-right">100%</div>
        </div>
    </div>
    @endif

    {{-- Pied de page du rapport --}}
    <div class="px-8 py-4 border-t border-secondary/10 bg-accent/5 mt-6">
        <p class="text-xs text-primary/40 text-center">
            Document généré automatiquement par le système analytique Villa Boutanga. Usage interne uniquement.
        </p>
        <p class="text-[10px] text-primary/30 text-center mt-1">
            Généré le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->name }}
        </p>
    </div>
</div>

{{-- Style impression --}}
<style>
    @media print {
        body * {
            visibility: hidden;
        }

        #report-print,
        #report-print * {
            visibility: visible;
        }

        #report-print {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
    }
</style>

@push('scripts')
<script>
    window.onload = function() {
        // Optionnel : Lancement automatique de l'impression
        setTimeout(() => { window.print(); }, 500);
    };
</script>
@endpush

@endsection
