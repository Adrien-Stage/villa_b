@extends('layouts.hotel')

@section('title', 'Tableau de bord')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Tableau de bord</h1>
        <p class="text-sm text-[#8a7a6a] mt-0.5">
            {{ ucfirst(\Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}
        </p>
    </div>
    <div class="text-xs font-semibold text-primary/50 uppercase tracking-widest bg-white px-4 py-2 rounded-xl shadow-sm border border-secondary/15">
        Session : {{ Auth::user()->name }} ({{ Auth::user()->role }})
    </div>
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

{{-- PANNEAU D'ACTIONS RAPIDES PAR RÔLE --}}
<div class="bg-white rounded-xl shadow-sm border border-secondary/15 p-5 mb-6">
    <h2 class="text-xs font-bold uppercase tracking-widest text-primary/50 mb-4 flex items-center gap-2">
        <i data-lucide="zap" class="w-4 h-4 text-yellow-500 fill-yellow-500"></i>
        Actions Rapides
    </h2>
    <div class="flex flex-wrap gap-3">
        @role('manager', 'reception')
            <a href="{{ route('bookings.create') }}" class="flex items-center gap-2 px-5 py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-surface-dark transition-all shadow-sm hover:shadow-md">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Nouvelle Réservation
            </a>
            <a href="{{ route('customers.create') }}" class="flex items-center gap-2 px-5 py-3 bg-white border border-secondary/30 text-primary rounded-xl text-sm font-semibold hover:bg-accent/10 transition-all">
                <i data-lucide="user-plus" class="w-4 h-4 text-primary/60"></i> Nouveau Client
            </a>
            <a href="{{ route('bookings.index') }}" class="flex items-center gap-2 px-5 py-3 bg-white border border-secondary/30 text-primary rounded-xl text-sm font-semibold hover:bg-accent/10 transition-all">
                <i data-lucide="calendar-check" class="w-4 h-4 text-primary/60"></i> Planning
            </a>
        @endrole

        @role('housekeeping_leader', 'housekeeping', 'manager')
            <a href="{{ route('housekeeping.index') }}" class="flex items-center gap-2 px-5 py-3 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 transition-all shadow-sm hover:shadow-md">
                <i data-lucide="spray-can" class="w-4 h-4"></i> Accéder au Housekeeping
            </a>
        @endrole

        @role('restaurant_chief', 'restaurant_staff', 'manager')
            <a href="{{ route('restaurant.orders.index') }}" class="flex items-center gap-2 px-5 py-3 bg-orange-600 text-white rounded-xl text-sm font-semibold hover:bg-orange-700 transition-all shadow-sm hover:shadow-md">
                <i data-lucide="utensils" class="w-4 h-4"></i> Gérer les commandes (Restaurant)
            </a>
        @endrole

        @role('shop_manager', 'shop_cashier')
            @if(!($panels['shop_active_session'] ?? false))
                <a href="{{ route('shop.cash_register.open') }}" class="flex items-center gap-2 px-5 py-3 bg-green-600 text-white rounded-xl text-sm font-semibold hover:bg-green-700 transition-all shadow-sm hover:shadow-md">
                    <i data-lucide="lock-open" class="w-4 h-4"></i> Ouvrir ma caisse
                </a>
            @else
                <a href="{{ route('shop.orders.create') }}" class="flex items-center gap-2 px-5 py-3 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-[#4a2a14] transition-all shadow-sm hover:shadow-md">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i> Nouvelle Vente Boutique
                </a>
                @role('shop_manager')
                <a href="{{ route('shop.cash_register.close') }}" class="flex items-center gap-2 px-5 py-3 bg-red-50 text-red-700 border border-red-200 rounded-xl text-sm font-semibold hover:bg-red-100 transition-all shadow-sm hover:shadow-md">
                    <i data-lucide="lock" class="w-4 h-4"></i> Fermer ma caisse
                </a>
                @endrole
            @endif
        @endrole
    </div>
</div>

{{-- CHIFFRES CLÉS --}}
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

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    {{-- COLONNE PRINCIPALE (ARRIVÉES / DÉPARTS / COMMANDES) --}}
    <div class="xl:col-span-2 space-y-6">
        
        {{-- PANNEAU DES RÉSERVATIONS (HOTEL) --}}
        @if(!empty($panels['reservations']))
            @php
                $arrivalsToday = $panels['reservations']['arrivalsToday'] ?? collect();
                $departuresToday = $panels['reservations']['departuresToday'] ?? collect();
            @endphp
            
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
                <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                    <h2 class="font-heading font-semibold text-primary text-lg flex items-center gap-2">
                        <i data-lucide="concierge-bell" class="w-5 h-5 text-primary/60"></i>
                        Arrivées & Départs du jour
                    </h2>
                    <a href="{{ route('bookings.index') }}"
                        class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1 font-semibold">
                        Gérer tout
                        <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>

                @if($arrivalsToday->isEmpty() && $departuresToday->isEmpty())
                    <div class="py-16 text-center text-primary/35">
                        <i data-lucide="check-circle" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
                        <p class="text-base font-semibold">Rien à signaler</p>
                        <p class="text-sm">Aucune arrivée ni départ aujourd'hui.</p>
                    </div>
                @else
                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- CARTES ARRIVÉES --}}
                        @foreach($arrivalsToday as $booking)
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm flex flex-col relative overflow-hidden group">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-heading font-bold text-emerald-900 text-base truncate">{{ $booking->customer->full_name }}</h3>
                                        <p class="text-xs text-emerald-700/80 font-medium">Chambre {{ $booking->room->number }} ({{ $booking->room->roomType->name }})</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-200 text-emerald-800 uppercase tracking-widest">Arrivée</span>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-2">
                                    <span class="text-xs text-emerald-700/60 font-semibold">{{ $booking->adults_count }} pers.</span>
                                    @if($booking->status->value === 'confirmed')
                                        <form method="POST" action="{{ route('bookings.checkIn', $booking) }}">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition shadow-sm flex items-center gap-1">
                                                <i data-lucide="log-in" class="w-3.5 h-3.5"></i> Faire le Check-in
                                            </button>
                                        </form>
                                    @else
                                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700">Déjà installé</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        {{-- CARTES DÉPARTS --}}
                        @foreach($departuresToday as $booking)
                            <div class="rounded-xl border border-orange-200 bg-orange-50/50 p-4 shadow-sm flex flex-col relative overflow-hidden group">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h3 class="font-heading font-bold text-orange-900 text-base truncate">{{ $booking->customer->full_name }}</h3>
                                        <p class="text-xs text-orange-700/80 font-medium">Chambre {{ $booking->room->number }} ({{ $booking->room->roomType->name }})</p>
                                    </div>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-orange-200 text-orange-800 uppercase tracking-widest">Départ</span>
                                </div>
                                <div class="mt-auto flex items-center justify-between pt-2">
                                    @if($booking->balance_due > 0)
                                        <span class="text-xs font-bold text-red-600 bg-red-100 px-2 py-1 rounded">Solde: {{ number_format($booking->balance_due / 100, 0, ',', ' ') }} FCFA</span>
                                    @else
                                        <span class="text-xs font-bold text-green-600 bg-green-100 px-2 py-1 rounded">Solde réglé</span>
                                    @endif

                                    @if($booking->status->value === 'checked_in')
                                        <form method="POST" action="{{ route('bookings.checkOut', $booking) }}">
                                            @csrf
                                            <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg text-xs font-semibold hover:bg-orange-600 transition shadow-sm flex items-center gap-1">
                                                <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Faire le Check-out
                                            </button>
                                        </form>
                                    @else
                                        <span class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-orange-100 text-orange-700">Départ terminé</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- PANNEAU RESTAURANT --}}
        @if(!empty($panels['restaurant_latest_orders']))
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
                <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                    <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                        <i data-lucide="utensils" class="w-4 h-4 text-orange-500"></i>
                        Dernières commandes
                    </h2>
                    <a href="{{ route('restaurant.orders.index') }}"
                        class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1">
                        Cuisine <i data-lucide="chevron-right" class="w-3 h-3"></i>
                    </a>
                </div>
                <div class="divide-y divide-secondary/10">
                    @foreach($panels['restaurant_latest_orders'] as $order)
                        <div class="px-5 py-4 flex items-center justify-between gap-4 hover:bg-accent/5 transition-colors">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold font-heading">
                                    {{ $order->table_number }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-primary">Table {{ $order->table_number }} · Cmd #{{ $order->id }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $order->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : ($order->status === 'preparing' ? 'bg-blue-100 text-blue-700' : 'bg-green-100 text-green-700') }}">
                                            {{ strtoupper($order->status) }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                            {{ strtoupper($order->payment_status ?? 'unpaid') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="text-sm font-bold text-primary">
                                    {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
                                </p>
                                <a href="{{ route('restaurant.orders.show', $order) }}" class="p-2 text-secondary hover:text-primary hover:bg-secondary/10 rounded-lg transition-colors">
                                    <i data-lucide="eye" class="w-4 h-4"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    {{-- COLONNE SECONDAIRE (ALERTES, STOCKS, STATUTS) --}}
    <div class="space-y-6">

        {{-- ALERTES HOUSEKEEPING / MAINTENANCE --}}
        @if(!empty($panels['rooms_attention']))
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-red-200">
                <div class="flex items-center justify-between px-5 py-4 border-b border-red-200 bg-red-50/50">
                    <h2 class="font-heading font-semibold text-red-800 text-sm flex items-center gap-2">
                        <i data-lucide="alert-octagon" class="w-4 h-4 text-red-600"></i>
                        Vigilance Chambres
                    </h2>
                </div>
                <div class="divide-y divide-red-100/50 bg-white">
                    @forelse($panels['rooms_attention'] as $room)
                        <div class="px-5 py-3 flex items-center justify-between hover:bg-red-50/30 transition-colors">
                            <div>
                                <p class="text-sm font-bold text-gray-900">Chambre {{ $room->number }}</p>
                                <p class="text-[10px] text-gray-500 mt-0.5">{{ $room->roomType?->name ?? '—' }}</p>
                            </div>
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full {{ $room->status->value === 'maintenance' || $room->status->value === 'out_of_order' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' }}">
                                {{ strtoupper($room->status->value ?? (string) $room->status) }}
                            </span>
                        </div>
                    @empty
                        <div class="px-5 py-4 text-center text-xs text-gray-400">Aucune alerte technique.</div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- JAUGE STATUTS CHAMBRES --}}
        @if(!empty($panels['rooms_status']))
            @php
                $s = $panels['rooms_status'];
                $rows = [
                    ['label' => 'Disponibles', 'count' => $s['rooms_available'], 'color' => 'bg-emerald-500'],
                    ['label' => 'Occupées', 'count' => $s['rooms_occupied'], 'color' => 'bg-blue-500'],
                    ['label' => 'En nettoyage', 'count' => $s['rooms_cleaning'], 'color' => 'bg-yellow-500'],
                    ['label' => 'Maintenance', 'count' => $s['rooms_maintenance'], 'color' => 'bg-red-500'],
                ];
            @endphp
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15 p-5">
                <h2 class="font-heading font-semibold text-primary text-sm mb-4">Occupation en temps réel</h2>
                <div class="space-y-4">
                    @foreach($rows as $row)
                        <div>
                            <div class="flex justify-between items-end mb-1">
                                <span class="text-xs font-semibold text-primary/70">{{ $row['label'] }}</span>
                                <span class="text-xs font-bold text-primary">{{ $row['count'] }}</span>
                            </div>
                            <div class="w-full h-2 bg-accent/30 rounded-full overflow-hidden">
                                @if($s['rooms_total'] > 0)
                                    <div class="h-full {{ $row['color'] }} rounded-full" style="width: {{ ($row['count'] / $s['rooms_total']) * 100 }}%"></div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <div class="pt-4 border-t border-secondary/15 flex justify-between items-center">
                        <span class="text-xs font-semibold text-primary/50 uppercase tracking-widest">Capacité Max</span>
                        <span class="text-base font-bold text-primary">{{ $s['rooms_total'] }}</span>
                    </div>
                </div>
            </div>
        @endif

        {{-- TOP BOUTIQUE & STOCKS --}}
        @if(!empty($panels['shop_top_products']) && count($panels['shop_top_products']) > 0)
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-secondary/15">
                <div class="flex items-center justify-between px-5 py-3 border-b border-secondary/20 bg-accent/5">
                    <h2 class="font-heading font-semibold text-primary text-xs flex items-center gap-2">
                        <i data-lucide="star" class="w-3.5 h-3.5 text-yellow-500 fill-yellow-500"></i>
                        Top Ventes Boutique
                    </h2>
                </div>
                <div class="divide-y divide-secondary/10 px-2">
                    @foreach($panels['shop_top_products'] as $index => $item)
                        <div class="flex items-center justify-between p-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold text-primary/30">#{{ $index + 1 }}</span>
                                <span class="font-medium text-primary text-xs truncate max-w-[120px]">{{ $item->product->name ?? 'Inconnu' }}</span>
                            </div>
                            <span class="text-xs font-bold bg-primary/10 text-primary px-2 py-0.5 rounded">
                                {{ $item->total_quantity }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(!empty($panels['shop_low_stock']) && count($panels['shop_low_stock']) > 0)
            <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-orange-200">
                <div class="flex items-center px-5 py-3 border-b border-orange-100 bg-orange-50/50">
                    <h2 class="font-heading font-semibold text-orange-800 text-xs flex items-center gap-2">
                        <i data-lucide="package-minus" class="w-3.5 h-3.5 text-orange-600"></i>
                        Stocks Faibles (Boutique)
                    </h2>
                </div>
                <div class="divide-y divide-orange-100/50">
                    @foreach($panels['shop_low_stock'] as $product)
                        <div class="flex items-center justify-between p-3">
                            <p class="font-medium text-gray-900 text-xs">{{ $product->name }}</p>
                            @if($product->stock_quantity <= 0)
                                <span class="text-[9px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 uppercase">Rupture</span>
                            @else
                                <span class="text-[9px] font-bold bg-orange-100 text-orange-800 px-1.5 py-0.5 rounded border border-orange-200 uppercase">Reste {{ $product->stock_quantity }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>

@admin
<script>
function testPopup() {
    showAccessDeniedPopup('Ceci est un test du popup d\'acces refuse. Le systeme fonctionne !');
}
</script>
@endadmin
@endsection

