@extends('layouts.hotel')

@section('title', 'Tableau de bord')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
        <h1 class="font-heading text-2xl sm:text-3xl font-bold text-primary">Tableau de bord</h1>
        <p class="text-xs sm:text-sm text-[#8a7a6a] mt-0.5 font-medium">
            {{ ucfirst(\Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}
        </p>
    </div>
    <div class="flex items-center gap-3">
        {{-- Widget Météo --}}
        <div class="hidden sm:flex items-center gap-3 bg-white/90 px-3.5 py-1.5 rounded-2xl border border-secondary/15 shadow-sm">
            <div class="text-amber-500 flex items-center justify-center">
                <i data-lucide="sun" class="w-6 h-6 fill-amber-400 text-amber-500"></i>
            </div>
            <div>
                <div class="flex items-center gap-1.5 leading-none">
                    <span class="text-xs font-bold text-primary">Yaoundé</span>
                    <span class="text-xs font-bold text-primary">28°C</span>
                </div>
                <p class="text-[10px] text-primary/60 font-medium mt-0.5">Ensoleillé</p>
            </div>
        </div>

        {{-- Session pill --}}
        <div class="flex items-center gap-2 bg-white/90 px-4 py-2 rounded-2xl border border-secondary/15 shadow-sm text-xs font-bold uppercase tracking-wider text-primary/70">
            <span>SESSION : {{ strtoupper(Auth::user()->name) }} ({{ strtoupper(Auth::user()->role) }})</span>
            <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-primary/40"></i>
        </div>
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

{{-- PANNEAU D'ACTIONS RAPIDES --}}
<div class="bg-white rounded-2xl shadow-sm border border-secondary/15 p-4 mb-6">
    <h2 class="text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-3 flex items-center gap-1.5">
        <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-500 fill-amber-500"></i>
        Actions Rapides
    </h2>
    <div class="flex flex-wrap items-center gap-2.5">
        @if($isManager || Auth::user()->hasAnyRole(['reception']))
            <a href="{{ route('reception.pos.index') }}" class="flex items-center gap-2 px-4 py-2.5 bg-[#8D4925] hover:bg-[#733a1c] text-white rounded-xl text-xs font-semibold shadow-sm hover:shadow transition-all">
                <i data-lucide="store" class="w-4 h-4"></i> Mode POS Réception
            </a>
            <a href="{{ route('bookings.create') }}" class="flex items-center gap-2 px-4 py-2.5 bg-[#2A160D] hover:bg-[#1a0e08] text-white rounded-xl text-xs font-semibold shadow-sm hover:shadow transition-all">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Nouvelle Réservation
            </a>
            <a href="{{ route('customers.create') }}" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-secondary/25 text-primary hover:bg-accent/15 rounded-xl text-xs font-semibold transition-all shadow-sm">
                <i data-lucide="user-plus" class="w-4 h-4 text-primary/60"></i> Nouveau Client
            </a>
            <a href="{{ route('agenda.index') }}" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-secondary/25 text-primary hover:bg-accent/15 rounded-xl text-xs font-semibold transition-all shadow-sm">
                <i data-lucide="calendar" class="w-4 h-4 text-primary/60"></i> Planning
            </a>
        @endif

        @if($isManager || Auth::user()->hasAnyRole(['restaurant_chief', 'restaurant_staff']))
            <a href="{{ route('restaurant.orders.index') }}" class="flex items-center gap-2 px-4 py-2.5 bg-[#EA580C] hover:bg-[#c2410c] text-white rounded-xl text-xs font-semibold shadow-sm hover:shadow transition-all">
                <i data-lucide="utensils" class="w-4 h-4"></i> Gérer les commandes (Restaurant)
            </a>
        @endif

        @if($isManager || Auth::user()->hasAnyRole(['housekeeping_leader', 'housekeeping', 'housekeeping_staff']))
            <a href="{{ route('housekeeping.index') }}" class="flex items-center gap-2 px-4 py-2.5 bg-[#059669] hover:bg-[#047857] text-white rounded-xl text-xs font-semibold shadow-sm hover:shadow transition-all">
                <i data-lucide="sparkles" class="w-4 h-4"></i> Accéder au Housekeeping
            </a>
        @endif

        @if(!$isManager && Auth::user()->hasAnyRole(['shop_manager', 'shop_cashier']))
            @if(!($panels['shop_active_session'] ?? false))
                <a href="{{ route('shop.cash_register.open') }}" class="flex items-center gap-2 px-4 py-2.5 bg-green-600 text-white rounded-xl text-xs font-semibold hover:bg-green-700 transition shadow-sm">
                    <i data-lucide="lock-open" class="w-4 h-4"></i> Ouvrir ma caisse
                </a>
            @else
                <a href="{{ route('shop.orders.create') }}" class="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-xs font-semibold hover:bg-[#4a2a14] transition shadow-sm">
                    <i data-lucide="shopping-cart" class="w-4 h-4"></i> Nouvelle Vente Boutique
                </a>
                <a href="{{ route('shop.cash_register.close') }}" class="flex items-center gap-2 px-4 py-2.5 bg-red-50 text-red-700 border border-red-200 rounded-xl text-xs font-semibold hover:bg-red-100 transition shadow-sm">
                    <i data-lucide="lock" class="w-4 h-4"></i> Fermer ma caisse
                </a>
            @endif
        @endif
    </div>
</div>

@if($isManager)
    {{-- ============================================================ --}}
    {{-- EXECUTIVE MANAGER DASHBOARD (PIXEL-FOR-PIXEL DESIGN)       --}}
    {{-- ============================================================ --}}

    {{-- LIGNE DE 6 CARTES CHIFFRES CLÉS --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3.5 mb-6">
        @foreach($cards as $card)
            <div class="bg-white rounded-2xl p-4 border border-secondary/15 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-primary/50 truncate">{{ $card['label'] }}</span>
                        <div class="w-9 h-9 rounded-xl {{ $card['icon_bg'] ?? 'bg-accent/30 text-primary/70' }} flex items-center justify-center flex-shrink-0">
                            <i data-lucide="{{ $card['icon'] ?? 'sparkles' }}" class="w-4 h-4"></i>
                        </div>
                    </div>
                    <div class="font-heading text-2xl font-bold text-primary truncate leading-tight">
                        {{ $card['value'] }}
                    </div>
                    @if(isset($card['subtitle_raw']))
                        <p class="text-[11px] text-primary/45 mt-0.5 truncate">{!! $card['subtitle_raw'] !!}</p>
                    @else
                        <p class="text-[11px] text-primary/45 mt-0.5 truncate">{{ $card['subtitle'] ?? '' }}</p>
                    @endif
                </div>

                @if(isset($card['href']))
                    <a href="{{ $card['href'] }}" class="text-xs font-semibold {{ $card['link_color'] ?? 'text-secondary hover:text-primary' }} flex items-center gap-1 mt-3 pt-2 border-t border-secondary/10 transition-colors">
                        {{ $card['link_text'] ?? 'Voir la liste →' }}
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- LIGNE DU MILIEU (3 CARTES ÉQUILIBRÉES) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        
        {{-- CARTE 1 : OCCUPATION EN TEMPS RÉEL --}}
        <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-secondary/15">
                <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                    <i data-lucide="pie-chart" class="w-4 h-4 text-primary/70"></i>
                    Occupation en temps réel
                </h2>
                <a href="{{ route('rooms.index') }}" class="text-xs font-semibold text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Voir plus <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            {{-- Donut Chart SVG --}}
            <div class="py-4 flex items-center justify-center">
                @php
                    $tot = max(1, $statsHotel['rooms_total']);
                    $dispCount = $statsHotel['rooms_available'];
                    $occCount = $statsHotel['rooms_occupied'];
                    $cleanCount = $statsHotel['rooms_cleaning'];
                    $maintCount = $statsHotel['rooms_maintenance'];

                    $r = 58;
                    $circ = 2 * pi() * $r; // ~364.4

                    $occDash = ($occCount / $tot) * $circ;
                    $dispDash = ($dispCount / $tot) * $circ;
                    $cleanDash = ($cleanCount / $tot) * $circ;
                    $maintDash = ($maintCount / $tot) * $circ;
                @endphp
                <div class="relative w-36 h-36 flex items-center justify-center">
                    <svg viewBox="0 0 160 160" class="w-36 h-36 transform -rotate-90">
                        <circle cx="80" cy="80" r="58" stroke="#F1ECE5" stroke-width="14" fill="transparent" />
                        @if($dispDash > 0)
                            <circle cx="80" cy="80" r="58" stroke="#10B981" stroke-width="14"
                                    stroke-dasharray="{{ $dispDash }} {{ $circ - $dispDash }}"
                                    stroke-dashoffset="0"
                                    fill="transparent" />
                        @endif
                        @if($occDash > 0)
                            <circle cx="80" cy="80" r="58" stroke="#3B82F6" stroke-width="14"
                                    stroke-dasharray="{{ $occDash }} {{ $circ - $occDash }}"
                                    stroke-dashoffset="-{{ $dispDash }}"
                                    fill="transparent" />
                        @endif
                        @if($cleanDash > 0)
                            <circle cx="80" cy="80" r="58" stroke="#F59E0B" stroke-width="14"
                                    stroke-dasharray="{{ $cleanDash }} {{ $circ - $cleanDash }}"
                                    stroke-dashoffset="-{{ $dispDash + $occDash }}"
                                    fill="transparent" />
                        @endif
                        @if($maintDash > 0)
                            <circle cx="80" cy="80" r="58" stroke="#EF4444" stroke-width="14"
                                    stroke-dasharray="{{ $maintDash }} {{ $circ - $maintDash }}"
                                    stroke-dashoffset="-{{ $dispDash + $occDash + $cleanDash }}"
                                    fill="transparent" />
                        @endif
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                        <span class="text-2xl font-bold font-heading text-primary leading-none">{{ $occupancyRate }}%</span>
                        <span class="text-[11px] font-semibold text-primary/70 mt-1 leading-none">{{ $occCount }} / {{ $tot }}</span>
                        <span class="text-[9px] text-primary/45 uppercase tracking-wider mt-0.5">chambres occupées</span>
                    </div>
                </div>
            </div>

            {{-- Légende --}}
            <div class="space-y-2 pt-2 text-xs">
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Disponibles
                    </span>
                    <span class="font-bold">{{ $dispCount }}</span>
                </div>
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Occupées
                    </span>
                    <span class="font-bold">{{ $occCount }}</span>
                </div>
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> En nettoyage
                    </span>
                    <span class="font-bold">{{ $cleanCount }}</span>
                </div>
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Maintenance
                    </span>
                    <span class="font-bold">{{ $maintCount }}</span>
                </div>
                <div class="pt-2 border-t border-secondary/15 flex items-center justify-between text-[11px] font-bold text-primary/60 uppercase tracking-wider">
                    <span>Capacité Max</span>
                    <span class="text-sm font-heading font-bold text-primary">{{ $tot }}</span>
                </div>
            </div>
        </div>

        {{-- CARTE 2 : ARRIVÉES & DÉPARTS DU JOUR (AVEC ONGLETS) --}}
        <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-sm flex flex-col justify-between" x-data="{ tab: 'arrivals' }">
            <div class="flex items-center justify-between pb-3 border-b border-secondary/15">
                <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                    <i data-lucide="bell" class="w-4 h-4 text-primary/70"></i>
                    Arrivées & Départs du jour
                </h2>
                <a href="{{ route('bookings.index') }}" class="text-xs font-semibold text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Tout voir <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            @php
                $arrivals = $panels['reservations']['arrivalsToday'] ?? collect();
                $departures = $panels['reservations']['departuresToday'] ?? collect();
            @endphp
            <div class="flex items-center gap-4 border-b border-secondary/15 my-3">
                <button type="button" @click="tab = 'arrivals'"
                        :class="tab === 'arrivals' ? 'border-amber-800 text-amber-900 font-bold' : 'border-transparent text-primary/50 hover:text-primary font-medium'"
                        class="pb-2 text-xs flex items-center gap-1.5 border-b-2 transition-all">
                    <i data-lucide="calendar-arrow-down" class="w-3.5 h-3.5"></i>
                    Arrivées ({{ $arrivals->count() }})
                </button>
                <button type="button" @click="tab = 'departures'"
                        :class="tab === 'departures' ? 'border-amber-800 text-amber-900 font-bold' : 'border-transparent text-primary/50 hover:text-primary font-medium'"
                        class="pb-2 text-xs flex items-center gap-1.5 border-b-2 transition-all">
                    <i data-lucide="calendar-arrow-up" class="w-3.5 h-3.5"></i>
                    Départs ({{ $departures->count() }})
                </button>
            </div>

            <div class="flex-1 flex flex-col justify-center">
                {{-- LISTE DES ARRIVÉES --}}
                <div x-show="tab === 'arrivals'" class="space-y-3">
                    @forelse($arrivals as $booking)
                        @php
                            $words = explode(' ', trim($booking->customer->full_name ?? 'Client'));
                            $initials = count($words) >= 2 
                                ? mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1)
                                : mb_substr($booking->customer->full_name ?? 'CL', 0, 2);
                            $initials = mb_strtoupper($initials);
                        @endphp
                        <div class="rounded-2xl border border-emerald-200/80 bg-[#ECFDF5]/50 p-4 shadow-sm flex flex-col justify-between gap-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-sm shadow-sm flex-shrink-0">
                                        {{ $initials }}
                                    </div>
                                    <div>
                                        <h3 class="font-heading font-bold text-emerald-950 text-sm truncate">{{ $booking->customer->full_name }}</h3>
                                        <p class="text-xs text-emerald-700/80 font-medium">Chambre {{ $booking->room->number ?? '—' }} ({{ $booking->room->roomType->name ?? '—' }})</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 uppercase tracking-wider">
                                    Arrivée
                                </span>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-emerald-200/40">
                                <div class="flex items-center gap-3 text-xs text-emerald-800/70 font-medium">
                                    <span class="flex items-center gap-1">
                                        <i data-lucide="user" class="w-3.5 h-3.5"></i>
                                        {{ $booking->adults_count ?? 1 }} pers.
                                    </span>
                                    @if(!empty($booking->reference))
                                        <span class="font-mono text-[11px] text-emerald-700/60">{{ $booking->reference }}</span>
                                    @endif
                                </div>

                                @if($booking->status->value === 'confirmed')
                                    <form method="POST" action="{{ route('bookings.checkIn', $booking) }}">
                                        @csrf
                                        <button type="submit" class="px-3.5 py-2 bg-[#059669] hover:bg-[#047857] text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                                            <i data-lucide="log-in" class="w-3.5 h-3.5"></i> Faire le Check-in
                                        </button>
                                    </form>
                                @else
                                    <span class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-100 text-emerald-800">
                                        Déjà installé
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-primary/40">
                            <i data-lucide="calendar-check" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                            <p class="text-xs font-semibold">Aucune arrivée prévue aujourd'hui</p>
                        </div>
                    @endforelse
                </div>

                {{-- LISTE DES DÉPARTS --}}
                <div x-show="tab === 'departures'" style="display: none;" class="space-y-3">
                    @forelse($departures as $booking)
                        @php
                            $words = explode(' ', trim($booking->customer->full_name ?? 'Client'));
                            $initials = count($words) >= 2 
                                ? mb_substr($words[0], 0, 1) . mb_substr(end($words), 0, 1)
                                : mb_substr($booking->customer->full_name ?? 'CL', 0, 2);
                            $initials = mb_strtoupper($initials);
                        @endphp
                        <div class="rounded-2xl border border-rose-200/80 bg-rose-50/50 p-4 shadow-sm flex flex-col justify-between gap-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-11 h-11 rounded-2xl bg-rose-100 text-rose-800 font-bold flex items-center justify-center text-sm shadow-sm flex-shrink-0">
                                        {{ $initials }}
                                    </div>
                                    <div>
                                        <h3 class="font-heading font-bold text-rose-950 text-sm truncate">{{ $booking->customer->full_name }}</h3>
                                        <p class="text-xs text-rose-700/80 font-medium">Chambre {{ $booking->room->number ?? '—' }} ({{ $booking->room->roomType->name ?? '—' }})</p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 uppercase tracking-wider">
                                    Départ
                                </span>
                            </div>

                            <div class="flex items-center justify-between pt-2 border-t border-rose-200/40">
                                <div>
                                    @if($booking->balance_due > 0)
                                        <span class="text-xs font-bold text-red-600 bg-red-100 px-2.5 py-1 rounded-lg">Solde: {{ number_format($booking->balance_due / 100, 0, ',', ' ') }} FCFA</span>
                                    @else
                                        <span class="text-xs font-bold text-green-700 bg-green-100 px-2.5 py-1 rounded-lg">Solde réglé</span>
                                    @endif
                                </div>

                                @if($booking->status->value === 'checked_in')
                                    <form method="POST" action="{{ route('bookings.checkOut', $booking) }}">
                                        @csrf
                                        <button type="submit" class="px-3.5 py-2 bg-[#EA580C] hover:bg-[#c2410c] text-white rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5">
                                            <i data-lucide="log-out" class="w-3.5 h-3.5"></i> Faire le Check-out
                                        </button>
                                    </form>
                                @else
                                    <span class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-rose-100 text-rose-800">
                                        Départ terminé
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-primary/40">
                            <i data-lucide="calendar-check" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                            <p class="text-xs font-semibold">Aucun départ prévu aujourd'hui</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- CARTE 3 : DISPONIBILITÉ DES CHAMBRES (JAUGE CIRCULAIRE) --}}
        <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between pb-3 border-b border-secondary/15">
                <h2 class="font-heading font-semibold text-primary text-sm flex items-center gap-2">
                    <i data-lucide="bar-chart-2" class="w-4 h-4 text-primary/70"></i>
                    Disponibilité des chambres
                </h2>
                <a href="{{ route('rooms.index') }}" class="text-xs font-semibold text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Voir plus <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            {{-- Jauge semi-circulaire SVG --}}
            @php
                $gaugeAvailable = $statsHotel['rooms_available'];
                $gaugeOccupied = $statsHotel['rooms_occupied'];
                $gaugeMaintenance = $statsHotel['rooms_maintenance'];
                $gaugeTotal = max(1, $statsHotel['rooms_total']);

                $semiCirc = pi() * 60; // ~188.5
                $semiDispDash = ($gaugeAvailable / $gaugeTotal) * $semiCirc;
                $semiOccDash = ($gaugeOccupied / $gaugeTotal) * $semiCirc;
                $semiMaintDash = ($gaugeMaintenance / $gaugeTotal) * $semiCirc;
            @endphp
            <div class="py-3 flex flex-col items-center justify-center">
                <div class="relative w-48 h-28 flex items-center justify-center overflow-hidden">
                    <svg viewBox="0 0 160 95" class="w-48 h-28">
                        <path d="M 20,85 A 60,60 0 0,1 140,85" fill="none" stroke="#F1ECE5" stroke-width="14" stroke-linecap="round" />
                        <path d="M 20,85 A 60,60 0 0,1 140,85" fill="none" stroke="#10B981" stroke-width="14"
                              stroke-dasharray="{{ $semiDispDash }} {{ $semiCirc }}"
                              stroke-dashoffset="0"
                              stroke-linecap="round" />
                        @if($semiOccDash > 0)
                            <path d="M 20,85 A 60,60 0 0,1 140,85" fill="none" stroke="#3B82F6" stroke-width="14"
                                  stroke-dasharray="{{ $semiOccDash }} {{ $semiCirc }}"
                                  stroke-dashoffset="-{{ $semiDispDash }}" />
                        @endif
                        @if($semiMaintDash > 0)
                            <path d="M 20,85 A 60,60 0 0,1 140,85" fill="none" stroke="#EF4444" stroke-width="14"
                                  stroke-dasharray="{{ $semiMaintDash }} {{ $semiCirc }}"
                                  stroke-dashoffset="-{{ $semiDispDash + $semiOccDash }}"
                                  stroke-linecap="round" />
                        @endif
                    </svg>
                    <div class="absolute bottom-1 inset-x-0 flex flex-col items-center justify-center text-center pointer-events-none">
                        <span class="text-3xl font-bold font-heading text-primary leading-none">{{ $gaugeAvailable }}</span>
                        <span class="text-[10px] text-primary/50 font-medium mt-0.5">chambres disponibles</span>
                    </div>
                </div>
            </div>

            {{-- Légende --}}
            <div class="space-y-2 pt-2 text-xs">
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Disponibles
                    </span>
                    <span class="font-bold">{{ $gaugeAvailable }}</span>
                </div>
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span> Occupées
                    </span>
                    <span class="font-bold">{{ $gaugeOccupied }}</span>
                </div>
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> En nettoyage
                    </span>
                    <span class="font-bold">{{ $statsHotel['rooms_cleaning'] }}</span>
                </div>
                <div class="flex items-center justify-between text-primary">
                    <span class="flex items-center gap-2 text-primary/70">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Maintenance
                    </span>
                    <span class="font-bold">{{ $gaugeMaintenance }}</span>
                </div>
            </div>
        </div>

    </div>

    {{-- LIGNE DU BAS (2 COLONNES) --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        
        {{-- COLONNE GAUCHE : DERNIÈRES COMMANDES RESTAURANT --}}
        <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-sm">
            <div class="flex items-center justify-between pb-4 border-b border-secondary/15">
                <h2 class="font-heading font-semibold text-primary text-base flex items-center gap-2">
                    <i data-lucide="utensils" class="w-4 h-4 text-orange-500"></i>
                    Dernières commandes
                </h2>
                <a href="{{ route('restaurant.orders.index') }}" class="text-xs font-semibold text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Cuisine <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            @php
                $latestOrders = $panels['restaurant_latest_orders'] ?? collect();
            @endphp

            <div class="divide-y divide-secondary/10">
                @forelse($latestOrders->take(4) as $idx => $order)
                    @php
                        $itemCount = $order->items->count();
                        $firstItemImage = $order->items->first()?->menuItem?->image_path;
                        $dishImg = $firstItemImage ? asset('storage/' . $firstItemImage) : asset('images/dishes/dish' . (($idx % 4) + 1) . '.png');
                        $statusUpper = strtoupper($order->status);
                        $payUpper = strtoupper($order->payment_status ?? 'unpaid');
                    @endphp
                    <div class="py-3.5 flex items-center justify-between gap-3 hover:bg-accent/5 px-2 rounded-xl transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            {{-- Vignette plat circulaire --}}
                            <div class="w-11 h-11 rounded-full overflow-hidden flex-shrink-0 border border-secondary/20 shadow-sm bg-accent/20">
                                <img src="{{ $dishImg }}" alt="Plat" class="w-full h-full object-cover">
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-primary truncate">
                                        {{ !str_starts_with(strtolower($order->table_number), 'table') && !str_starts_with(strtolower($order->table_number), 'terrasse') ? 'Table ' : '' }}{{ $order->table_number }} · Cmd #{{ $order->id }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <span class="text-[11px] text-primary/50 font-medium">
                                        {{ $itemCount }} {{ $itemCount > 1 ? 'articles' : 'article' }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $order->status === 'served' ? 'bg-emerald-100 text-emerald-800' : ($order->status === 'preparing' ? 'bg-blue-100 text-blue-800' : ($order->status === 'ready' ? 'bg-amber-100 text-amber-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                        {{ $statusUpper }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $order->payment_status === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $payUpper }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 flex-shrink-0">
                            <span class="text-xs text-primary/40 font-medium hidden sm:inline">
                                {{ $order->created_at ? $order->created_at->format('H:i') : '' }}
                            </span>
                            <span class="font-bold text-sm text-primary">
                                {{ number_format($order->total_amount / 100, 0, ',', ' ') }} FCFA
                            </span>
                            <a href="{{ route('restaurant.orders.show', $order) }}" class="p-1.5 text-secondary hover:text-primary hover:bg-secondary/10 rounded-lg transition-colors" title="Détail de la commande">
                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-primary/40">
                        <i data-lucide="utensils" class="w-10 h-10 mx-auto mb-2 opacity-30"></i>
                        <p class="text-xs font-semibold">Aucune commande enregistrée</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- COLONNE DROITE : STATISTIQUES DU JOUR & ÉVOLUTION DU CA --}}
        <div class="space-y-4">
            {{-- En-tête statistiques du jour --}}
            <div class="flex items-center justify-between">
                <h2 class="font-heading font-semibold text-primary text-base flex items-center gap-2">
                    <i data-lucide="layers" class="w-4 h-4 text-amber-600"></i>
                    Statistiques du jour
                </h2>
                <a href="{{ route('analytics.index') }}" class="text-xs font-semibold text-secondary hover:text-primary transition-colors flex items-center gap-1">
                    Voir rapports <i data-lucide="arrow-right" class="w-3 h-3"></i>
                </a>
            </div>

            {{-- 3 Mini-Stat Cards --}}
            <div class="grid grid-cols-3 gap-3">
                {{-- Articles vendus --}}
                <div class="bg-white rounded-2xl p-3.5 border border-secondary/15 shadow-sm flex items-start gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="package" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold text-primary/60 truncate">Articles vendus</p>
                        <p class="font-heading text-xl font-bold text-primary leading-tight mt-0.5 truncate">
                            {{ $panels['items_sold_today'] ?? 0 }}
                        </p>
                        <p class="text-[10px] text-primary/40 mt-0.5 truncate">Aujourd'hui</p>
                    </div>
                </div>

                {{-- Articles sous seuil --}}
                <div class="bg-white rounded-2xl p-3.5 border border-secondary/15 shadow-sm flex items-start gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="package-minus" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold text-primary/60 truncate">Articles sous seuil</p>
                        <p class="font-heading text-xl font-bold text-primary leading-tight mt-0.5 truncate">
                            {{ $panels['sous_seuil'] ?? 0 }}
                        </p>
                        <p class="text-[10px] text-primary/40 mt-0.5 truncate">
                            {{ ($panels['rupture'] ?? 0) > 0 ? "dont " . $panels['rupture'] . " en rupture" : "Aucune rupture" }}
                        </p>
                    </div>
                </div>

                {{-- Valeur du stock --}}
                <div class="bg-white rounded-2xl p-3.5 border border-secondary/15 shadow-sm flex items-start gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="warehouse" class="w-4 h-4"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold text-primary/60 truncate">Valeur du stock</p>
                        <p class="font-heading text-base font-bold text-primary leading-tight mt-0.5 truncate">
                            {{ number_format(($panels['stock_value'] ?? 0) / 100, 0, ',', ' ') }} FCFA
                        </p>
                        <p class="text-[10px] text-primary/40 mt-0.5 truncate">
                            {{ $panels['stock_articles_count'] ?? 0 }} article(s) actifs
                        </p>
                    </div>
                </div>
            </div>

            {{-- Évolution du CA (Hôtel + Restaurant) SVG Area Chart --}}
            @php
                $hourly = $panels['hourly_revenue'] ?? [
                    '00h' => 0, '03h' => 0, '06h' => 0, '09h' => 0,
                    '12h' => 73980, '15h' => 0, '18h' => 0, '21h' => 0,
                ];
                $hourlyKeys = array_keys($hourly);
                $hourlyVals = array_values($hourly);
                $maxVal = max(1, max($hourlyVals));
                $scaleMax = 400000;
                if ($maxVal > 400000) {
                    $scaleMax = ceil($maxVal / 100000) * 100000;
                }

                $chartW = 600;
                $chartH = 200;
                $padLeft = 45;
                $padRight = 25;
                $padTop = 20;
                $padBottom = 35;
                $plotW = $chartW - $padLeft - $padRight; // 530
                $plotH = $chartH - $padTop - $padBottom; // 145

                $pts = [];
                $n = count($hourlyVals);
                for ($i = 0; $i < $n; $i++) {
                    $px = $padLeft + ($i * ($plotW / ($n - 1)));
                    $py = ($padTop + $plotH) - (($hourlyVals[$i] / $scaleMax) * $plotH);
                    $pts[] = ['x' => $px, 'y' => $py, 'val' => $hourlyVals[$i], 'label' => $hourlyKeys[$i]];
                }

                $pathD = 'M ' . $pts[0]['x'] . ',' . $pts[0]['y'];
                for ($i = 1; $i < $n; $i++) {
                    $prev = $pts[$i - 1];
                    $curr = $pts[$i];
                    $cpx1 = $prev['x'] + ($curr['x'] - $prev['x']) / 2;
                    $cpy1 = $prev['y'];
                    $cpx2 = $prev['x'] + ($curr['x'] - $prev['x']) / 2;
                    $cpy2 = $curr['y'];
                    $pathD .= ' C ' . $cpx1 . ',' . $cpy1 . ' ' . $cpx2 . ',' . $cpy2 . ' ' . $curr['x'] . ',' . $curr['y'];
                }
                $areaD = $pathD . ' L ' . $pts[$n - 1]['x'] . ',' . ($padTop + $plotH) . ' L ' . $pts[0]['x'] . ',' . ($padTop + $plotH) . ' Z';

                $ticks = [
                    ['val' => '400K', 'y' => $padTop],
                    ['val' => '300K', 'y' => $padTop + ($plotH * 0.25)],
                    ['val' => '200K', 'y' => $padTop + ($plotH * 0.50)],
                    ['val' => '100K', 'y' => $padTop + ($plotH * 0.75)],
                    ['val' => '0',    'y' => $padTop + $plotH],
                ];
            @endphp
            <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-heading font-semibold text-primary text-xs flex items-center gap-1.5">
                        Évolution du CA (Hôtel + Restaurant)
                    </h3>
                    <div class="flex items-center gap-1 text-[11px] font-semibold text-primary/70 bg-accent/20 px-2.5 py-1 rounded-lg">
                        <span>Aujourd'hui</span>
                        <i data-lucide="chevron-down" class="w-3 h-3 text-primary/50"></i>
                    </div>
                </div>

                <div class="w-full overflow-hidden">
                    <svg viewBox="0 0 600 200" class="w-full h-auto">
                        <defs>
                            <linearGradient id="revenueGradient" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#8D4925" stop-opacity="0.28" />
                                <stop offset="100%" stop-color="#8D4925" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>

                        @foreach($ticks as $tick)
                            <line x1="{{ $padLeft }}" y1="{{ $tick['y'] }}" x2="{{ $chartW - $padRight }}" y2="{{ $tick['y'] }}" stroke="#F1ECE5" stroke-width="1" stroke-dasharray="3 3" />
                            <text x="{{ $padLeft - 8 }}" y="{{ $tick['y'] + 3 }}" font-size="9" fill="#9CA3AF" text-anchor="end" font-family="sans-serif">{{ $tick['val'] }}</text>
                        @endforeach

                        <path d="{{ $areaD }}" fill="url(#revenueGradient)" />
                        <path d="{{ $pathD }}" fill="none" stroke="#8D4925" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

                        @foreach($pts as $pt)
                            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="3.5" fill="#8D4925" stroke="#FFFFFF" stroke-width="1.5" />
                            <text x="{{ $pt['x'] }}" y="{{ $chartH - 12 }}" font-size="9" fill="#9CA3AF" text-anchor="middle" font-family="sans-serif">{{ $pt['label'] }}</text>
                        @endforeach

                        @php
                            $highest = $pts[0];
                            foreach($pts as $p) {
                                if ($p['val'] > $highest['val']) {
                                    $highest = $p;
                                }
                            }
                        @endphp
                        @if($highest['val'] > 0)
                            <line x1="{{ $highest['x'] }}" y1="{{ $highest['y'] }}" x2="{{ $highest['x'] }}" y2="{{ $padTop + $plotH }}" stroke="#8D4925" stroke-width="1" stroke-dasharray="2 2" opacity="0.6" />
                            <circle cx="{{ $highest['x'] }}" cy="{{ $highest['y'] }}" r="5" fill="#8D4925" stroke="#FFFFFF" stroke-width="2" />
                            <g transform="translate({{ $highest['x'] }}, {{ max(16, $highest['y'] - 16) }})">
                                <rect x="-42" y="-14" width="84" height="18" rx="6" fill="#2A160D" opacity="0.9" />
                                <text x="0" y="-2" font-size="8.5" font-weight="bold" fill="#FFFFFF" text-anchor="middle" font-family="sans-serif">
                                    {{ number_format($highest['val'], 0, ',', ' ') }} FCFA
                                </text>
                            </g>
                        @endif
                    </svg>
                </div>
            </div>
        </div>

    </div>

@else
    {{-- ============================================================ --}}
    {{-- AFFICHAGE POUR LES AUTRES RÔLES (RÉCEPTION, ÉCONOME, ETC.) --}}
    {{-- ============================================================ --}}

    {{-- CHIFFRES CLÉS DU RÔLE --}}
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

            @if(!empty($panels['economat_alerts']) && count($panels['economat_alerts']) > 0)
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-orange-200">
                    <div class="flex items-center px-5 py-3 border-b border-orange-100 bg-orange-50/50">
                        <h2 class="font-heading font-semibold text-orange-800 text-xs flex items-center gap-2">
                            <i data-lucide="warehouse" class="w-3.5 h-3.5 text-orange-600"></i>
                            À réapprovisionner (Économat)
                        </h2>
                    </div>
                    <div class="divide-y divide-orange-100/50">
                        @foreach($panels['economat_alerts'] as $article)
                            <a href="{{ route('economat.items.index') }}" class="flex items-center justify-between p-3 hover:bg-orange-50/40 transition-colors">
                                <p class="font-medium text-gray-900 text-xs">{{ $article->name }}</p>
                                @if($article->isOutOfStock())
                                    <span class="text-[9px] font-bold bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 uppercase">Rupture</span>
                                @else
                                    <span class="text-[9px] font-bold bg-orange-100 text-orange-800 px-1.5 py-0.5 rounded border border-orange-200 uppercase">Reste {{ rtrim(rtrim(number_format((float) $article->current_stock, 2, ',', ' '), '0'), ',') }} {{ $article->unit }}</span>
                                @endif
                            </a>
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
@endif

@admin
<script>
function testPopup() {
    showAccessDeniedPopup('Ceci est un test du popup d\'acces refuse. Le systeme fonctionne !');
}
</script>
@endadmin
@endsection
