@extends('layouts.hotel')

@section('title', 'Réservations')

@section('content')
<div x-data="{ showOpenRegisterModal: @json(!$isCashRegisterOpen) }">

{{-- En-tête --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Réservations</h1>
        <p class="text-sm text-primary/50 mt-0.5">{{ $stats['all'] }} réservation{{ $stats['all'] > 1 ? 's' : '' }} au total</p>
    </div>
    @role('reception', 'manager')
        @if($isCashRegisterOpen)
            <a href="{{ route('bookings.create') }}"
               class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Nouvelle réservation
            </a>
        @else
            <a href="{{ route('bookings.cash_register.open') }}"
               class="flex items-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-medium rounded-lg hover:bg-amber-700 transition-colors">
                <i data-lucide="unlock" class="w-4 h-4"></i>
                Ouvrir la caisse
            </a>
        @endif
    @endrole
</div>

@php
    $tab = request('tab', 'active');
    $viewMode = request('view', 'list');
@endphp

{{-- Onglets principales --}}
<div class="flex items-center gap-2 border-b border-secondary/20 mb-5">
    <a href="{{ route('bookings.index', array_merge(request()->except(['tab', 'page', 'status']), ['tab' => 'active'])) }}"
       class="px-4 py-3 text-sm font-medium transition-colors {{ ($tab ?? 'active') === 'active' ? 'border-b-2 border-primary text-primary' : 'text-primary/60 hover:text-primary' }}">
        Réservations
    </a>
    <a href="{{ route('bookings.index', array_merge(request()->except(['tab', 'page', 'status']), ['tab' => 'archive', 'status' => request('status', 'all')])) }}"
       class="px-4 py-3 text-sm font-medium transition-colors {{ ($tab ?? 'active') === 'archive' ? 'border-b-2 border-primary text-primary' : 'text-primary/60 hover:text-primary' }}">
        Archive
    </a>
</div>

{{-- Badges stats --}}
<div class="grid grid-cols-5 gap-3 mb-5">
    @php
        $statCards = [
            ['key' => 'arriving',   'label' => 'Arrivées aujourd\'hui', 'icon' => 'log-in',     'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50'],
            ['key' => 'departing',  'label' => 'Départs aujourd\'hui',  'icon' => 'log-out',    'color' => 'text-orange-500',  'bg' => 'bg-orange-50'],
            ['key' => 'checked_in', 'label' => 'En séjour',             'icon' => 'hotel',      'color' => 'text-blue-600',    'bg' => 'bg-blue-50'],
            ['key' => 'confirmed',  'label' => 'Confirmées',            'icon' => 'check-circle','color' => 'text-green-600',  'bg' => 'bg-green-50'],
            ['key' => 'pending',    'label' => 'En attente',            'icon' => 'clock',      'color' => 'text-yellow-600',  'bg' => 'bg-yellow-50'],
        ];
    @endphp
    @foreach($statCards as $card)
        <div class="bg-white rounded-xl p-4 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg {{ $card['bg'] }} flex items-center justify-center flex-shrink-0">
                <i data-lucide="{{ $card['icon'] }}" class="w-4 h-4 {{ $card['color'] }}"></i>
            </div>
            <div>
                <p class="text-lg font-heading font-semibold text-primary leading-none">{{ $stats[$card['key']] }}</p>
                <p class="text-xs text-primary/50 mt-0.5">{{ $card['label'] }}</p>
            </div>
        </div>
    @endforeach
</div>

{{-- Barre outils --}}
<div class="flex flex-col gap-4 mb-5 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex items-center gap-2 flex-wrap">
        @foreach($statusFilters as $value => $label)
            <a href="{{ route('bookings.index', array_merge(request()->except(['status', 'page']), ['tab' => $tab, 'status' => $value, 'view' => $viewMode])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                      {{ $status === $value ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex items-center gap-3 justify-between w-full lg:w-auto">
        <form method="GET" action="{{ route('bookings.index') }}" class="relative flex-1">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="text"
                   id="search-input"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="N° réservation, client..."
                   autocomplete="off"
                   class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-full max-w-[360px] transition-all">
            <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
        </form>

        @if($tab === 'active')
            <div class="inline-flex rounded-lg border border-secondary/30 bg-white p-0.5">
                <a href="{{ route('bookings.index', array_merge(request()->except(['view', 'page']), ['tab' => 'active', 'view' => 'list'])) }}"
                   title="Vue liste"
                   class="inline-flex items-center justify-center h-9 w-9 rounded-md {{ $viewMode === 'list' ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary' }}">
                    <i data-lucide="list" class="w-4 h-4"></i>
                </a>
                <a href="{{ route('bookings.index', array_merge(request()->except(['view', 'page']), ['tab' => 'active', 'view' => 'calendar'])) }}"
                   title="Vue calendrier"
                   class="inline-flex items-center justify-center h-9 w-9 rounded-md {{ $viewMode === 'calendar' ? 'bg-primary text-white' : 'text-primary/60 hover:text-primary' }}">
                    <i data-lucide="calendar" class="w-4 h-4"></i>
                </a>
            </div>
        @endif
    </div>
</div>

{{-- Table --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
        @if($tab === 'active' && $viewMode === 'calendar')
        <script>
            window.calendarBookingsData = @json($calendarBookings ?? []);
        </script>
        <div x-data="bookingCalendar(window.calendarBookingsData)" class="flex flex-col flex-1 bg-white">
            <!-- Calendar Header -->
            <div class="flex flex-col space-y-4 p-5 md:flex-row md:items-center md:justify-between md:space-y-0 border-b border-slate-100 bg-white">
                
                <!-- Left: Month Navigation -->
                <div class="flex items-center gap-3">
                    <button type="button" @click="prevMonth()" class="inline-flex items-center justify-center h-8 w-8 text-slate-400 hover:text-slate-700 hover:bg-slate-50 border border-slate-200/50 rounded-lg transition-colors cursor-pointer">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </button>
                    <h2 class="text-lg font-semibold text-slate-800 tracking-tight select-none w-44 text-center" x-text="currentMonthLabel"></h2>
                    <button type="button" @click="nextMonth()" class="inline-flex items-center justify-center h-8 w-8 text-slate-400 hover:text-slate-700 hover:bg-slate-50 border border-slate-200/50 rounded-lg transition-colors cursor-pointer">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </button>
                </div>

                <!-- Center: View Toggle Pill -->
                <div class="inline-flex bg-slate-100 rounded-full p-0.5 border border-slate-200/50 shadow-inner select-none">
                    <button type="button" class="px-3 py-1 text-[11px] font-semibold text-slate-400 hover:text-slate-600 transition cursor-pointer">Jour</button>
                    <button type="button" class="px-3 py-1 text-[11px] font-semibold text-slate-400 hover:text-slate-600 transition cursor-pointer">Semaine</button>
                    <button type="button" class="px-4.5 py-1 text-[11px] font-bold text-slate-900 bg-white rounded-full shadow-xs border border-slate-200/40">Mois</button>
                </div>

                <!-- Right: Search Bar & Actions -->
                <div class="flex items-center gap-3">
                    <div class="relative w-full max-w-[200px]">
                        <input type="text" 
                               x-model="searchQuery" 
                               placeholder="Rechercher..." 
                               class="pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg bg-white text-slate-800 placeholder-slate-400 outline-none focus:border-slate-300 w-full transition-all">
                        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    </div>
                    <button type="button" @click="goToToday()" class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 bg-slate-50 hover:bg-slate-100 border border-slate-200/80 rounded-lg transition-colors cursor-pointer">
                        Aujourd'hui
                    </button>
                </div>
            </div>

            <!-- Calendar Grid Container -->
            <div class="flex flex-col gap-6 p-5 bg-white">
                <div class="w-full flex flex-col">
                    
                    <!-- Week Days Header -->
                    <div class="grid grid-cols-7 border-b border-slate-200 bg-[#FAFBFF] rounded-t-xl overflow-hidden">
                        <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                            <span class="hidden sm:inline">Lundi</span>
                            <span class="sm:hidden">Lun</span>
                        </div>
                        <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                            <span class="hidden sm:inline">Mardi</span>
                            <span class="sm:hidden">Mar</span>
                        </div>
                        <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                            <span class="hidden sm:inline">Mercredi</span>
                            <span class="sm:hidden">Mer</span>
                        </div>
                        <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                            <span class="hidden sm:inline">Jeudi</span>
                            <span class="sm:hidden">Jeu</span>
                        </div>
                        <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-500">
                            <span class="hidden sm:inline">Vendredi</span>
                            <span class="sm:hidden">Ven</span>
                        </div>
                        <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-400">
                            <span class="hidden sm:inline">Samedi</span>
                            <span class="sm:hidden">Sam</span>
                        </div>
                        <div class="py-3 text-center text-[11px] font-semibold tracking-wider uppercase text-slate-400">
                            <span class="hidden sm:inline">Dimanche</span>
                            <span class="sm:hidden">Dim</span>
                        </div>
                    </div>

                    <!-- Single Responsive Grid -->
                    <div class="grid grid-cols-7 border-l border-t border-slate-200 mt-3 shadow-xs rounded-xl overflow-hidden bg-white">
                        <template x-for="day in days" :key="day.key">
                            <div @click="selectDay(day.iso)"
                                 :class="[
                                     day.isCurrentMonth ? 'bg-white' : 'bg-[#F1F5F9] text-slate-400',
                                     selectedIso === day.iso ? 'bg-indigo-50/20' : 'hover:bg-[#F8F9FF]'
                                 ]"
                                 class="relative flex flex-col min-h-[90px] sm:min-h-[110px] border-r border-b border-slate-200 p-1.5 sm:p-2.5 cursor-pointer transition-colors group">
                                
                                <header class="flex items-center justify-between mb-1">
                                    <span :class="[
                                              day.isToday ? 'bg-[#4F46E5] text-white font-semibold flex items-center justify-center rounded-full w-6 h-6 sm:w-7 sm:h-7 text-xs sm:text-sm shadow-sm' : 
                                              ((!day.isCurrentMonth || day.isWeekend) ? 'text-slate-400 text-xs sm:text-sm font-medium' : 'text-slate-700 text-xs sm:text-sm font-medium')
                                          ]"
                                          x-text="day.number"></span>
                                    
                                    <span class="hidden sm:inline-block text-[9px] font-bold text-slate-400 bg-slate-100 border border-slate-200/50 rounded-full px-1.5 py-0.5"
                                          x-show="day.bookings.length"
                                          x-text="day.bookings.length"></span>
                                </header>

                                <!-- Bookings List inside Day Cell (Desktop) -->
                                <div class="hidden sm:block space-y-1 overflow-y-auto max-h-[75px] pr-0.5 mt-1">
                                    <template x-for="booking in day.bookings.slice(0, 3)" :key="booking.id">
                                        <a :href="booking.url"
                                           @click.stop
                                           :class="[
                                               booking.status === 'pending' ? 'bg-[#FFFBEB] text-[#D97706]' : '',
                                               booking.status === 'confirmed' ? 'bg-[#EFF6FF] text-[#1D4ED8]' : '',
                                               booking.status === 'checked_in' ? 'bg-[#F0FDF4] text-[#16A34A]' : '',
                                               booking.status === 'checked_out' ? 'bg-[#F5F3FF] text-[#7C3AED]' : '',
                                               booking.status === 'completed' || booking.status === 'cancelled' || booking.status === 'no_show' ? 'bg-[#FEF2F2] text-[#DC2626]' : ''
                                           ]"
                                           class="px-2 py-0.5 rounded text-[11px] font-medium leading-relaxed block truncate hover:brightness-95 transition-all"
                                           :title="'Ch. ' + booking.room_number + ' — ' + booking.customer">
                                            <span x-text="'Ch. ' + booking.room_number + ' — ' + booking.customer"></span>
                                        </a>
                                    </template>
                                    <template x-if="day.bookings.length > 3">
                                        <div class="text-[9px] font-semibold text-slate-400 pl-1 mt-0.5">
                                            +<span x-text="day.bookings.length - 3"></span> de plus
                                        </div>
                                    </template>
                                </div>

                                <!-- Dots indicator on Mobile -->
                                <div class="flex flex-wrap items-center justify-center gap-0.5 mt-1 h-3 sm:hidden">
                                    <template x-for="b in day.bookings.slice(0, 3)" :key="b.id">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                              :class="[
                                                  b.status === 'pending' ? 'bg-amber-500' : '',
                                                  b.status === 'confirmed' ? 'bg-blue-500' : '',
                                                  b.status === 'checked_in' ? 'bg-emerald-500' : '',
                                                  b.status === 'checked_out' ? 'bg-purple-500' : '',
                                                  b.status === 'completed' || b.status === 'cancelled' || b.status === 'no_show' ? 'bg-rose-500' : ''
                                              ]"></span>
                                    </template>
                                    <template x-if="day.bookings.length > 3">
                                        <span class="text-[8px] text-slate-400 font-bold leading-none">+</span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Details Panel -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs">
                    <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4 text-slate-500"></i>
                        Détails du jour : <span class="capitalize font-semibold text-slate-800" x-text="selectedDayLabel"></span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                        <template x-for="booking in selectedEvents" :key="booking.id">
                            <a :href="booking.url" class="block rounded-xl border border-slate-200/60 hover:border-slate-300 p-4 transition-all bg-white hover:bg-slate-50/50 shadow-2xs group">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-mono font-bold text-slate-400" x-text="booking.booking_number"></span>
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                                          :class="[
                                              booking.status === 'pending' ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' : '',
                                              booking.status === 'confirmed' ? 'bg-blue-50 text-blue-700 border border-blue-200' : '',
                                              booking.status === 'checked_in' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : '',
                                              booking.status === 'checked_out' ? 'bg-purple-50 text-purple-700 border border-purple-200' : '',
                                              booking.status === 'completed' || booking.status === 'cancelled' || booking.status === 'no_show' ? 'bg-pink-50 text-pink-700 border border-pink-200' : ''
                                          ]"
                                          x-text="booking.status === 'pending' ? 'En attente' : (booking.status === 'confirmed' ? 'Confirmé' : (booking.status === 'checked_in' ? 'En séjour' : (booking.status === 'checked_out' ? 'Checkout' : 'Terminé')))"></span>
                                </div>
                                <div class="text-xs font-bold text-slate-800 group-hover:text-slate-900 transition-colors" x-text="booking.customer"></div>
                                
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-2">
                                    <i data-lucide="door-closed" class="w-3.5 h-3.5"></i>
                                    <span class="font-medium" x-text="'Chambre ' + booking.room_number"></span>
                                </div>
                                
                                <div class="text-[10px] text-slate-400 mt-2.5 pt-2 border-t border-slate-100 flex items-center gap-1">
                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                    <span x-text="'Du ' + formatShortDate(booking.check_in) + ' au ' + formatShortDate(booking.check_out)"></span>
                                </div>
                            </a>
                        </template>

                        <template x-if="selectedEvents.length === 0">
                            <div class="col-span-full text-center py-8 text-slate-400 text-xs">
                                <i data-lucide="calendar" class="w-8 h-8 mx-auto mb-2 opacity-30"></i>
                                Aucune réservation ce jour
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    @else
        @if($bookings->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-primary/30">
                <i data-lucide="calendar" class="w-10 h-10 mb-3 opacity-40"></i>
                <p class="text-sm">Aucune réservation trouvée</p>
            </div>
        @else
            {{-- En-tête --}}
            <div class="grid grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 bg-accent/20">
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">N° Réservation</div>
                <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Client</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Chambre</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Période</div>
                <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40">Montant</div>
                <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40">Statut</div>
                <div class="col-span-1"></div>
            </div>

            @foreach($bookings as $booking)
                @php
                    $statusColors = [
                        'pending'      => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                        'confirmed'    => 'bg-blue-50 text-blue-700 border-blue-200',
                        'checked_in'   => 'bg-green-50 text-green-700 border-green-200',
                        'checked_out'  => 'bg-purple-50 text-purple-700 border-purple-200',
                        'completed'    => 'bg-gray-50 text-gray-600 border-gray-200',
                        'cancelled'    => 'bg-red-50 text-red-600 border-red-200',
                        'no_show'      => 'bg-red-50 text-red-600 border-red-200',
                    ];
                    $sc = $statusColors[$booking->status->value] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
                @endphp
                <a href="{{ route('bookings.show', $booking) }}"
                   class="grid grid-cols-12 gap-4 px-5 py-3.5 border-b border-secondary/10 hover:bg-accent/10 transition-colors items-center cursor-pointer">

                    <div class="col-span-2">
                        <span class="text-sm font-mono font-medium text-primary">{{ $booking->booking_number }}</span>
                    </div>

                    <div class="col-span-3 flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-[10px] font-semibold">
                                {{ strtoupper(substr($booking->customer->first_name, 0, 1) . substr($booking->customer->last_name, 0, 1)) }}
                            </span>
                        </div>
                        <span class="text-sm text-primary truncate">{{ $booking->customer->full_name }}</span>
                    </div>

                    <div class="col-span-2">
                        <p class="text-sm text-primary">Chambre {{ $booking->room->number }}</p>
                        <p class="text-xs text-primary/40">{{ $booking->room->roomType->name }}</p>
                    </div>

                    <div class="col-span-2">
                        <p class="text-xs text-primary">
                            {{ $booking->check_in->locale('fr')->isoFormat('D MMM') }}
                            → {{ $booking->check_out->locale('fr')->isoFormat('D MMM') }}
                        </p>
                        <p class="text-xs text-primary/40">{{ $booking->total_nights }} nuit{{ $booking->total_nights > 1 ? 's' : '' }}</p>
                    </div>

                    <div class="col-span-1">
                        <p class="text-xs font-medium text-primary">
                            {{ number_format($booking->total_amount / 100, 0, ',', ' ') }}
                        </p>
                        <p class="text-[10px] text-primary/40">FCFA</p>
                    </div>

                    <div class="col-span-1">
                        <span class="px-2 py-0.5 text-xs font-medium rounded-full border {{ $sc }}">
                            {{ $booking->status->label() }}
                        </span>
                    </div>

                    <div class="col-span-1 flex justify-end">
                        <i data-lucide="chevron-right" class="w-4 h-4 text-primary/30"></i>
                    </div>
                </a>
            @endforeach
        @endif
    @endif
</div>

{{-- Pagination --}}
@if($bookings->hasPages())
    <div class="mt-4">{{ $bookings->links() }}</div>
@endif

<script>
let searchTimer;
document.getElementById('search-input').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => this.closest('form').submit(), 400);
});
</script>

@if($tab === 'active' && $viewMode === 'calendar')
<script>
    function initBookingCalendar() {
        if (window.bookingCalendarInitialized) return;
        window.bookingCalendarInitialized = true;
        
        Alpine.data('bookingCalendar', (events) => ({
            year: new Date().getFullYear(),
            month: new Date().getMonth(),
            events: events || [],
            selectedIso: (new Date()).toISOString().slice(0,10),
            searchQuery: '',

            init() {
                this.$nextTick(() => {
                    if (window.refreshLucideIcons) window.refreshLucideIcons();
                });
                this.$watch('month', () => {
                    this.$nextTick(() => {
                        if (window.refreshLucideIcons) window.refreshLucideIcons();
                    });
                });
                this.$watch('selectedIso', () => {
                    this.$nextTick(() => {
                        if (window.refreshLucideIcons) window.refreshLucideIcons();
                    });
                });
                this.$watch('searchQuery', () => {
                    this.$nextTick(() => {
                        if (window.refreshLucideIcons) window.refreshLucideIcons();
                    });
                });
            },

            get currentMonthLabel() {
                const date = new Date(this.year, this.month, 1);
                const monthName = date.toLocaleString('fr-FR', { month: 'long' });
                return monthName.charAt(0).toUpperCase() + monthName.slice(1) + ' - ' + this.year;
            },

            get selectedDayLabel() {
                if (!this.selectedIso) return '';
                const date = this.dateFromIso(this.selectedIso);
                return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            },

            isoFromDate(date) {
                return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            },

            dateFromIso(iso) {
                const [year, month, day] = iso.split('-').map(Number);
                return new Date(year, month - 1, day);
            },

            formatShortDate(iso) {
                if (!iso) return '';
                const [year, month, day] = iso.split('-').map(Number);
                const date = new Date(year, month - 1, day);
                return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
            },

            isToday(date) {
                const today = new Date();
                return date.getDate() === today.getDate() &&
                       date.getMonth() === today.getMonth() &&
                       date.getFullYear() === today.getFullYear();
            },

            getBookingsForDay(dayIso) {
                return this.events.filter(booking => {
                    const dateMatch = dayIso >= booking.check_in && dayIso <= booking.check_out;
                    if (!dateMatch) return false;
                    
                    if (this.searchQuery && this.searchQuery.trim() !== '') {
                        const q = this.searchQuery.toLowerCase().trim();
                        const customerMatch = booking.customer.toLowerCase().includes(q);
                        const numMatch = booking.booking_number.toLowerCase().includes(q);
                        const roomMatch = String(booking.room_number).includes(q);
                        return customerMatch || numMatch || roomMatch;
                    }
                    return true;
                });
            },

            get days() {
                const firstOfMonth = new Date(this.year, this.month, 1);
                // Shift first day of week to Monday
                let startOffset = firstOfMonth.getDay() - 1;
                if (startOffset < 0) {
                    startOffset = 6;
                }
                const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
                const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
                const days = [];

                for (let i = 0; i < totalCells; i++) {
                    const date = new Date(this.year, this.month, 1 - startOffset + i);
                    
                    const yearStr = date.getFullYear();
                    const monthStr = String(date.getMonth() + 1).padStart(2, '0');
                    const dateStr = String(date.getDate()).padStart(2, '0');
                    const iso = `${yearStr}-${monthStr}-${dateStr}`;
                    const isWeekend = date.getDay() === 0 || date.getDay() === 6;

                    days.push({
                        key: iso + '-' + i,
                        iso,
                        date,
                        number: date.getDate(),
                        bookings: this.getBookingsForDay(iso),
                        isCurrentMonth: date.getMonth() === this.month,
                        isToday: this.isToday(date),
                        isWeekend: isWeekend,
                    });
                }

                return days;
            },

            get selectedEvents() {
                return this.getBookingsForDay(this.selectedIso);
            },

            selectDay(iso) {
                this.selectedIso = iso;
            },

            prevMonth() {
                if (this.month === 0) {
                    this.year -= 1;
                    this.month = 11;
                } else {
                    this.month -= 1;
                }
            },

            nextMonth() {
                if (this.month === 11) {
                    this.year += 1;
                    this.month = 0;
                } else {
                    this.month += 1;
                }
            },

            goToToday() {
                const t = new Date();
                this.year = t.getFullYear();
                this.month = t.getMonth();
                this.selectedIso = this.isoFromDate(t);
            },
        }));
    }

    if (window.Alpine) {
        initBookingCalendar();
    } else {
        document.addEventListener('alpine:init', initBookingCalendar);
    }
</script>
@endif

    {{-- Modal Caisse Fermée --}}
    <div x-show="showOpenRegisterModal" 
         class="fixed inset-0 z-50 overflow-y-auto" 
         style="display: none;"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        {{-- Overlay backdrop --}}
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showOpenRegisterModal = false"></div>

        {{-- Modal card wrapper --}}
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                
                {{-- Header/Icon section --}}
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-amber-50 sm:mx-0 sm:h-10 sm:w-10 border border-amber-100">
                            <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-heading font-semibold leading-6 text-primary" id="modal-title">
                                Caisse de réception fermée
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-primary/70 leading-relaxed">
                                    Votre caisse est actuellement fermée. Vous devez l'ouvrir afin de pouvoir enregistrer de nouvelles réservations et traiter les paiements.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- Action buttons --}}
                <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-2 border-t border-slate-100">
                    <a href="{{ route('bookings.cash_register.open') }}" 
                       class="inline-flex w-full justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-amber-700 transition-all sm:ml-3 sm:w-auto">
                        Ouvrir la caisse
                    </a>
                    <button type="button" 
                            @click="showOpenRegisterModal = false"
                            class="mt-3 inline-flex w-full justify-center rounded-lg bg-white border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition-all sm:mt-0 sm:w-auto">
                        Consulter uniquement
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection