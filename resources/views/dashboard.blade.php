@extends('layouts.hotel')

@section('title', 'Tableau de bord')

@section('content')
<div class="mb-6">
    <h1 class="font-heading text-2xl font-semibold text-primary">Tableau de bord</h1>
    <p class="text-sm text-[#8a7a6a] mt-0.5">
        {{ ucfirst(\Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}
    </p>
</div>

@admin
<div class="mb-6 flex flex-wrap gap-2">
    <a href="/admin"
       class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
        <i data-lucide="settings" class="w-4 h-4"></i>
        Administration
    </a>
    <button onclick="testPopup()"
       class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
        Tester le popup d'acces refuse
    </button>
    <a href="{{ route('test-popup') }}"
       class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 text-white text-sm font-medium rounded-lg hover:bg-orange-700 transition-colors">
        <i data-lucide="external-link" class="w-4 h-4"></i>
        Tester URL directe
    </a>
</div>
@endadmin

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($cards as $card)
        <a href="{{ $card['href'] ?? '#' }}"
           class="group bg-white rounded-xl shadow-sm border border-secondary/15 p-4 hover:bg-accent/10 transition-colors">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-widest text-primary/45">{{ $card['label'] }}</p>
                    <p class="font-heading text-2xl font-semibold text-primary mt-1 truncate">{{ $card['value'] }}</p>
                    @if(isset($card['subtitle_raw']))
                        <p class="text-xs text-primary/45 mt-1 truncate">{!! $card['subtitle_raw'] !!}</p>
                    @else
                        <p class="text-xs text-primary/45 mt-1 truncate">{{ $card['subtitle'] ?? '' }}</p>
                    @endif
                </div>
                <div class="h-10 w-10 rounded-xl bg-accent/30 border border-secondary/15 flex items-center justify-center text-primary/70 group-hover:bg-accent/40 flex-shrink-0">
                    <i data-lucide="{{ $card['icon'] ?? 'sparkles' }}" class="w-5 h-5"></i>
                </div>
            </div>
        </a>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    @if(!empty($panels['reservations']))
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Reservations du jour</h2>
                <a href="{{ route('bookings.index') }}"
                    class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Voir tout
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                </a>
            </div>

            @php
                $arrivalsToday = $panels['reservations']['arrivalsToday'] ?? collect();
                $departuresToday = $panels['reservations']['departuresToday'] ?? collect();
            @endphp

            @if($arrivalsToday->isEmpty() && $departuresToday->isEmpty())
                <div class="py-16 text-center text-primary/35">
                    <i data-lucide="calendar" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                    <p class="text-sm">Aucune reservation aujourd'hui</p>
                </div>
            @else
                <div class="divide-y divide-secondary/10">
                    @foreach($arrivalsToday as $booking)
                        <div class="flex items-center gap-4 px-5 py-3 hover:bg-accent/20 transition-colors">
                            <span class="w-16 text-center text-xs font-medium px-2 py-1 rounded-full bg-emerald-50 text-emerald-600">Arrivee</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-primary truncate">{{ $booking->customer->full_name }}</p>
                                <p class="text-xs text-primary/50">Chambre {{ $booking->room->number }} — {{ $booking->room->roomType->name }}</p>
                            </div>
                            <p class="text-xs text-primary/50 flex-shrink-0">{{ $booking->adults_count }} pers.</p>
                            <span class="text-xs font-medium px-2 py-1 rounded-full {{ $booking->status->value === 'confirmed' ? 'bg-blue-50 text-blue-600' : 'bg-yellow-50 text-yellow-600' }}">
                                {{ $booking->status->label() }}
                            </span>
                        </div>
                    @endforeach
                    @foreach($departuresToday as $booking)
                        <div class="flex items-center gap-4 px-5 py-3 hover:bg-accent/20 transition-colors">
                            <span class="w-16 text-center text-xs font-medium px-2 py-1 rounded-full bg-orange-50 text-orange-500">Depart</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-primary truncate">{{ $booking->customer->full_name }}</p>
                                <p class="text-xs text-primary/50">Chambre {{ $booking->room->number }} — {{ $booking->room->roomType->name }}</p>
                            </div>
                            <p class="text-xs text-primary/50 flex-shrink-0">{{ $booking->adults_count }} pers.</p>
                            <span class="text-xs font-medium px-2 py-1 rounded-full bg-green-50 text-green-600">
                                {{ $booking->status->label() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if(!empty($panels['rooms_status']))
        @php
            $s = $panels['rooms_status'];
            $rows = [
                ['label' => 'Disponibles', 'count' => $s['rooms_available'], 'dot' => 'bg-green-400', 'bar' => 'bg-green-400'],
                ['label' => 'Occupees', 'count' => $s['rooms_occupied'], 'dot' => 'bg-blue-400', 'bar' => 'bg-blue-400'],
                ['label' => 'En nettoyage', 'count' => $s['rooms_cleaning'], 'dot' => 'bg-yellow-400', 'bar' => 'bg-yellow-400'],
                ['label' => 'Maintenance', 'count' => $s['rooms_maintenance'], 'dot' => 'bg-orange-400', 'bar' => 'bg-orange-400'],
            ];
        @endphp
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Statut chambres</h2>
                <a href="{{ route('rooms.index') }}"
                    class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Gerer
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                </a>
            </div>
            <div class="px-5 py-4 space-y-3">
                @foreach($rows as $row)
                    <div class="flex items-center gap-3">
                        <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $row['dot'] }}"></span>
                        <span class="flex-1 text-xs text-primary/60">{{ $row['label'] }}</span>
                        <div class="w-20 h-1 bg-accent/40 rounded-full overflow-hidden">
                            @if($s['rooms_total'] > 0)
                                <div class="h-full {{ $row['bar'] }} rounded-full" style="width: {{ ($row['count'] / $s['rooms_total']) * 100 }}%"></div>
                            @endif
                        </div>
                        <span class="w-4 text-right text-xs font-semibold text-primary">{{ $row['count'] }}</span>
                    </div>
                @endforeach
                <div class="pt-3 border-t border-secondary/20 flex justify-between items-center">
                    <span class="text-xs text-primary/50">Total chambres</span>
                    <span class="text-sm font-semibold text-primary">{{ $s['rooms_total'] }}</span>
                </div>
            </div>
        </div>
    @endif

    @if(!empty($panels['rooms_attention']))
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">A surveiller</h2>
                <a href="{{ route('housekeeping.index') }}"
                    class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Housekeeping
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                </a>
            </div>
            <div class="divide-y divide-secondary/10">
                @foreach($panels['rooms_attention'] as $room)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-primary">Chambre {{ $room->number }}</p>
                            <p class="text-xs text-primary/45 mt-0.5">{{ $room->roomType?->name ?? '—' }}</p>
                        </div>
                        <span class="text-xs font-semibold px-2 py-1 rounded-full bg-accent/30 text-primary border border-secondary/15">
                            {{ strtoupper($room->status->value ?? (string) $room->status) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($panels['restaurant_latest_orders']))
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Dernieres commandes</h2>
                <a href="{{ route('restaurant.orders.index') }}"
                    class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Restaurant
                    <i data-lucide="chevron-right" class="w-3 h-3"></i>
                </a>
            </div>
            <div class="divide-y divide-secondary/10">
                @foreach($panels['restaurant_latest_orders'] as $order)
                    <div class="px-5 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-primary truncate">Commande #{{ $order->id }} · Table {{ $order->table_number }}</p>
                            <p class="text-xs text-primary/45 mt-0.5">
                                {{ strtoupper($order->status) }} · {{ strtoupper($order->payment_status ?? 'unpaid') }}
                            </p>
                        </div>
                        <p class="text-sm font-semibold text-primary flex-shrink-0">
                            {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- PANELS BOUTIQUE --}}
    @if(isset($panels['shop_top_products']) || isset($panels['shop_low_stock']))
        <!-- Actions Rapides Boutique (Pleine largeur sur sa ligne) -->
        <div class="col-span-full mb-2 mt-4">
            <div class="flex items-center justify-between">
                <h2 class="font-heading font-semibold text-primary text-sm uppercase tracking-widest opacity-60">Actions Rapides Boutique</h2>
                <div class="flex gap-2">
                    @role('shop_manager','shop_cashier')
                    @if(!($panels['shop_active_session'] ?? false))
                        <a href="{{ route('shop.cash_register.open') }}" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm inline-flex items-center">
                            <i data-lucide="lock-open" class="w-4 h-4 mr-2"></i> Ouvrir ma caisse
                        </a>
                    @else
                        <a href="{{ route('shop.orders.create') }}" class="bg-primary hover:bg-[#4a2a14] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm inline-flex items-center">
                            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Nouvelle commande
                        </a>
                        <a href="{{ route('shop.cash_register.close') }}" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm inline-flex items-center" title="Fermer la caisse">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </a>
                    @endif
                    @else
                        <span class="text-xs text-primary/40 italic">Consultation uniquement</span>
                    @endrole
                </div>
            </div>
        </div>
        
        <!-- Top Produits Boutique -->
        @if(!empty($panels['shop_top_products']) && count($panels['shop_top_products']) > 0)
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15 lg:col-span-2">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                    <i data-lucide="star" class="w-4 h-4 text-yellow-500 fill-yellow-500"></i>
                    Meilleurs ventes de la boutique (Mois)
                </h2>
                <a href="{{ route('shop.products.index') }}"
                    class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Inventaire <i data-lucide="chevron-right" class="w-3 h-3"></i>
                </a>
            </div>
            <div class="divide-y divide-secondary/10 px-2 py-2">
                @foreach($panels['shop_top_products'] as $index => $item)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-accent/10 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs
                                {{ $index === 0 ? 'bg-yellow-100 text-yellow-700' : ($index === 1 ? 'bg-gray-200 text-gray-700' : 'bg-orange-100 text-orange-700') }}">
                                #{{ $index + 1 }}
                            </div>
                            <div>
                                <p class="font-medium text-primary text-sm">{{ $item->product->name ?? 'Produit inconnu' }}</p>
                                <p class="text-[10px] text-primary/50">{{ $item->product->category->name ?? 'Catégorie' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-block bg-primary/10 text-primary px-2.5 py-1 rounded text-xs font-bold">
                                {{ $item->total_quantity }} <span class="font-normal opacity-70">vendus</span>
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Alertes de stocks Boutique -->
        @if(!empty($panels['shop_low_stock']) && count($panels['shop_low_stock']) > 0)
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-red-200 lg:col-span-1">
            <div class="flex items-center justify-between px-5 py-4 border-b border-red-100 bg-red-50/30">
                <h2 class="font-heading font-semibold text-red-800 text-sm flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-4 h-4 text-red-500"></i>
                    Boutique: Stocks critiques
                </h2>
            </div>
            <div class="divide-y divide-gray-100 p-2">
                @foreach($panels['shop_low_stock'] as $product)
                    <div class="flex items-center justify-between p-3">
                        <div>
                            <p class="font-medium text-gray-900 text-sm">{{ $product->name }}</p>
                            <p class="text-[10px] text-gray-500">{{ $product->category->name ?? '' }}</p>
                        </div>
                        <div>
                            @if($product->stock_quantity <= 0)
                                <span class="text-[10px] font-bold bg-red-100 text-red-700 px-2 py-0.5 rounded border border-red-200">
                                    Rupture
                                </span>
                            @else
                                <span class="text-[10px] font-bold bg-orange-100 text-orange-800 px-2 py-0.5 rounded border border-orange-200">
                                    Il reste {{ $product->stock_quantity }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    @endif
</div>

@admin
<script>
function testPopup() {
    showAccessDeniedPopup('Ceci est un test du popup d\'acces refuse. Le systeme fonctionne !');
}
</script>
@endadmin
@endsection

