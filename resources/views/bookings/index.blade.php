@extends('layouts.hotel')

@section('title', 'Réservations')

@section('content')

{{-- En-tête --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Réservations</h1>
        <p class="text-sm text-primary/50 mt-0.5">{{ $stats['all'] }} réservation{{ $stats['all'] > 1 ? 's' : '' }} au total</p>
    </div>
    @role('reception', 'manager')
    <a href="{{ route('bookings.create') }}"
       class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Nouvelle réservation
    </a>
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
        <div x-data="bookingCalendar(@json($calendarBookings ?? []))" class="flex flex-col flex-1 bg-white">
            <!-- Calendar Header -->
            <div class="flex flex-col space-y-4 p-5 md:flex-row md:items-center md:justify-between md:space-y-0 border-b border-secondary/15 bg-accent/5">
                <div class="flex items-center gap-4">
                    <!-- Today Badge Sheet -->
                    <div class="hidden w-16 flex-col items-center justify-center rounded-xl border border-secondary/20 bg-accent/15 p-1 md:flex">
                        <h1 class="p-0.5 text-[10px] font-bold uppercase text-primary/60 tracking-wider">
                            {{ now()->locale('fr')->isoFormat('MMM') }}
                        </h1>
                        <div class="flex w-full items-center justify-center rounded-lg border border-secondary/15 bg-white p-0.5 text-base font-extrabold text-primary shadow-xs">
                            <span>{{ now()->locale('fr')->isoFormat('D') }}</span>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <h2 class="text-lg font-bold text-primary leading-tight" x-text="currentMonthLabel"></h2>
                        <p class="text-xs text-primary/50 font-medium" x-text="currentMonthRange"></p>
                    </div>
                </div>

                <div class="flex items-center gap-3 justify-between md:justify-end">
                    <!-- Navigation Buttons -->
                    <div class="inline-flex rounded-lg border border-secondary/25 shadow-xs bg-white p-0.5 overflow-hidden">
                        <button type="button"
                                @click="prevMonth()"
                                class="inline-flex items-center justify-center h-8 w-8 text-primary/70 hover:text-primary hover:bg-accent/10 rounded-md transition-colors"
                                aria-label="Mois précédent">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </button>
                        <button type="button"
                                @click="goToToday()"
                                class="px-3 h-8 text-xs font-semibold text-primary/70 hover:text-primary hover:bg-accent/10 rounded-md transition-colors border-x border-secondary/15">
                            Aujourd'hui
                        </button>
                        <button type="button"
                                @click="nextMonth()"
                                class="inline-flex items-center justify-center h-8 w-8 text-primary/70 hover:text-primary hover:bg-accent/10 rounded-md transition-colors"
                                aria-label="Mois suivant">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </button>
                    </div>

                    @role('reception', 'manager')
                    <a href="{{ route('bookings.create') }}"
                       class="flex items-center justify-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-xs">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        <span>Nouveau</span>
                    </a>
                    @endrole
                </div>
            </div>

            <!-- Calendar Grid -->
            <div class="flex flex-col gap-6 p-5">
                <!-- Grille Principale -->
                <div class="w-full flex flex-col">
                    <!-- Week Days Header -->
                    <div class="grid grid-cols-7 border-b border-secondary/15 pb-2 text-center text-xs font-bold uppercase tracking-wider text-primary/50 leading-6">
                        <div class="py-1">Sun</div>
                        <div class="py-1">Mon</div>
                        <div class="py-1">Tue</div>
                        <div class="py-1">Wed</div>
                        <div class="py-1">Thu</div>
                        <div class="py-1">Fri</div>
                        <div class="py-1">Sat</div>
                    </div>

                    <!-- Desktop Grid (MD and up) -->
                    <div class="hidden md:grid md:grid-cols-7 border-l border-t border-secondary/15 bg-white mt-2 shadow-xs">
                        <template x-for="day in days" :key="day.key">
                            <div @click="selectDay(day.iso)"
                                 :class="[
                                     day.isCurrentMonth ? 'bg-white' : 'bg-accent/5 text-primary/30',
                                     selectedIso === day.iso ? 'ring-2 ring-primary ring-inset bg-accent/5' : 'hover:bg-accent/5'
                                 ]"
                                 class="relative flex flex-col min-h-[110px] border-r border-b border-secondary/15 p-2 cursor-pointer transition-colors group">
                                
                                <header class="flex items-center justify-between mb-1.5">
                                    <span :class="[
                                              day.isToday ? 'bg-dark text-white font-extrabold flex items-center justify-center rounded-full w-7 h-7 text-xs' : 'text-xs font-semibold text-primary/75'
                                          ]"
                                          x-text="day.number"></span>
                                    <span class="text-[9px] font-bold text-primary/45 uppercase"
                                          x-show="day.bookings.length"
                                          x-text="day.bookings.length + (day.bookings.length > 1 ? ' rés.' : ' ré.')"></span>
                                </header>

                                <!-- Bookings List inside Day Cell -->
                                <div class="space-y-1 overflow-y-auto max-h-[80px] pr-0.5">
                                    <template x-for="booking in day.bookings.slice(0, 3)" :key="booking.id">
                                        <a :href="booking.url"
                                           @click.stop
                                           :class="[
                                               booking.type === 'in' ? 'bg-emerald-50 text-emerald-800 border-l-4 border-emerald-500 rounded-r-md' : '',
                                               booking.type === 'out' ? 'bg-rose-50 text-rose-800 border-r-4 border-rose-500 rounded-l-md' : '',
                                               booking.type === 'stay' ? 'bg-blue-50 text-blue-800 rounded-none' : '',
                                               booking.type === 'single' ? 'bg-purple-50 text-purple-800 border-x-4 border-purple-500 rounded-md' : ''
                                           ]"
                                           class="px-2 py-0.5 text-[10px] font-semibold leading-tight block truncate shadow-2xs hover:brightness-95 transition-all"
                                           :title="'Ch. ' + booking.room_number + ' — ' + booking.customer + ' (' + booking.booking_number + ')'">
                                            <span x-text="'Ch. ' + booking.room_number + ' — ' + booking.customer"></span>
                                        </a>
                                    </template>
                                    <template x-if="day.bookings.length > 3">
                                        <div class="text-[9px] font-bold text-primary/40 pl-1 mt-0.5">
                                            +<span x-text="day.bookings.length - 3"></span> de plus
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Mobile Grid (Below MD) -->
                    <div class="grid grid-cols-7 border-l border-t border-secondary/15 bg-white mt-2 shadow-xs md:hidden">
                        <template x-for="day in days" :key="'mob-' + day.key">
                            <button type="button"
                                    @click="selectDay(day.iso)"
                                    :class="[
                                        day.isCurrentMonth ? 'bg-white' : 'bg-accent/5 text-primary/30',
                                        selectedIso === day.iso ? 'ring-2 ring-primary ring-inset bg-accent/5' : 'hover:bg-accent/5'
                                    ]"
                                    class="flex flex-col items-center justify-between min-h-[60px] border-r border-b border-secondary/15 p-2 cursor-pointer transition-colors">
                                <span :class="[
                                          day.isToday ? 'bg-dark text-white font-extrabold flex items-center justify-center rounded-full w-7 h-7 text-xs' : 'text-xs font-semibold text-primary/75'
                                      ]"
                                      x-text="day.number"></span>
                                
                                <!-- Dots indicator on Mobile -->
                                <div class="flex items-center justify-center gap-0.5 mt-1 h-2">
                                    <template x-for="b in day.bookings.slice(0, 3)" :key="b.id">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                              :class="[
                                                  b.type === 'in' ? 'bg-emerald-500' : '',
                                                  b.type === 'out' ? 'bg-rose-500' : '',
                                                  b.type === 'stay' ? 'bg-blue-500' : '',
                                                  b.type === 'single' ? 'bg-purple-500' : ''
                                              ]"></span>
                                    </template>
                                    <template x-if="day.bookings.length > 3">
                                        <span class="text-[8px] text-primary/45 font-extrabold leading-none">+</span>
                                    </template>
                                </div>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Details Panel (Placed below calendar) -->
                <div class="rounded-2xl border border-secondary/15 bg-white p-5 shadow-xs">
                    <h3 class="text-sm font-bold text-primary flex items-center gap-1.5">
                        <i data-lucide="calendar" class="w-4 h-4 text-primary/60"></i>
                        Détails du jour : <span class="capitalize font-semibold text-primary/75" x-text="selectedDayLabel"></span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                        <template x-for="booking in selectedEvents" :key="booking.id">
                            <a :href="booking.url" class="block rounded-xl border border-secondary/15 hover:border-primary/30 p-3.5 transition-all bg-accent/5 hover:bg-accent/10 group">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-mono font-bold text-primary/60" x-text="booking.booking_number"></span>
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                                          :class="[
                                              booking.type === 'in' ? 'bg-emerald-100 text-emerald-800' : '',
                                              booking.type === 'out' ? 'bg-rose-100 text-rose-800' : '',
                                              booking.type === 'stay' ? 'bg-blue-100 text-blue-800' : '',
                                              booking.type === 'single' ? 'bg-purple-100 text-purple-800' : ''
                                          ]"
                                          x-text="booking.type === 'in' ? 'Arrivée' : (booking.type === 'out' ? 'Départ' : (booking.type === 'stay' ? 'Séjour' : 'Séjour unique'))"></span>
                                </div>
                                <div class="text-xs font-extrabold text-primary group-hover:text-surface-dark transition-colors" x-text="booking.customer"></div>
                                
                                <div class="flex items-center gap-1.5 text-xs text-primary/60 mt-2">
                                    <i data-lucide="door-closed" class="w-3.5 h-3.5"></i>
                                    <span class="font-medium" x-text="'Chambre ' + booking.room_number"></span>
                                </div>
                                
                                <div class="text-[10px] text-primary/45 mt-2 pt-2 border-t border-secondary/10 flex items-center gap-1">
                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                    <span x-text="'Du ' + formatShortDate(booking.check_in) + ' au ' + formatShortDate(booking.check_out)"></span>
                                </div>
                            </a>
                        </template>

                        <template x-if="selectedEvents.length === 0">
                            <div class="col-span-full text-center py-8 text-primary/35 text-xs">
                                <i data-lucide="calendar" class="w-8 h-8 mx-auto mb-2 opacity-45"></i>
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
    document.addEventListener('alpine:init', () => {
        Alpine.data('bookingCalendar', (events) => ({
            year: new Date().getFullYear(),
            month: new Date().getMonth(),
            events: events || [],
            selectedIso: (new Date()).toISOString().slice(0,10),

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
            },

            get currentMonthLabel() {
                const date = new Date(this.year, this.month, 1);
                const monthName = date.toLocaleString('fr-FR', { month: 'long' });
                return monthName.charAt(0).toUpperCase() + monthName.slice(1) + ' ' + this.year;
            },

            get currentMonthRange() {
                const firstDay = new Date(this.year, this.month, 1);
                const lastDay = new Date(this.year, this.month + 1, 0);
                
                const formatOptions = { day: 'numeric', month: 'short', year: 'numeric' };
                const startStr = firstDay.toLocaleDateString('fr-FR', formatOptions);
                const endStr = lastDay.toLocaleDateString('fr-FR', formatOptions);
                
                return `${startStr} — ${endStr}`;
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
                    return dayIso >= booking.check_in && dayIso <= booking.check_out;
                }).map(booking => {
                    let type = 'stay';
                    if (dayIso === booking.check_in && dayIso === booking.check_out) {
                        type = 'single';
                    } else if (dayIso === booking.check_in) {
                        type = 'in';
                    } else if (dayIso === booking.check_out) {
                        type = 'out';
                    }
                    return { ...booking, type };
                });
            },

            get days() {
                const firstOfMonth = new Date(this.year, this.month, 1);
                const startOffset = firstOfMonth.getDay();
                const daysInMonth = new Date(this.year, this.month + 1, 0).getDate();
                const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
                const days = [];

                for (let i = 0; i < totalCells; i++) {
                    const date = new Date(this.year, this.month, 1 - startOffset + i);
                    
                    const yearStr = date.getFullYear();
                    const monthStr = String(date.getMonth() + 1).padStart(2, '0');
                    const dateStr = String(date.getDate()).padStart(2, '0');
                    const iso = `${yearStr}-${monthStr}-${dateStr}`;

                    days.push({
                        key: iso + '-' + i,
                        iso,
                        date,
                        number: date.getDate(),
                        bookings: this.getBookingsForDay(iso),
                        isCurrentMonth: date.getMonth() === this.month,
                        isToday: this.isToday(date),
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
    });
</script>
@endif

@endsection