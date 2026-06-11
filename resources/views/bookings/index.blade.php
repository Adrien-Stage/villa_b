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
<div class="flex items-center justify-between gap-4 mb-5">
    {{-- Filtres statut --}}
    <div class="flex items-center gap-2 flex-wrap">
        @foreach($statusFilters as $value => $label)
            <a href="{{ route('bookings.index', array_merge(request()->except(['status', 'page']), ['tab' => $tab ?? 'active', 'status' => $value])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                      {{ request('status', 'all') === $value
                          ? 'bg-primary text-white'
                          : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Recherche --}}
    <form method="GET" action="{{ route('bookings.index') }}" class="relative">
        <input type="hidden" name="tab" value="{{ $tab ?? 'active' }}">
        <input type="hidden" name="status" value="{{ request('status', 'all') }}">
        <input type="text"
               id="search-input"
               name="search"
               value="{{ request('search') }}"
               placeholder="N° réservation, client..."
               autocomplete="off"
               class="pl-9 pr-4 py-2 text-xs border border-secondary/30 rounded-lg bg-white text-primary placeholder-primary/30 outline-none focus:border-secondary w-60 transition-all">
        <i data-lucide="search" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-primary/30"></i>
    </form>
</div>

{{-- Table --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
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

@endsection