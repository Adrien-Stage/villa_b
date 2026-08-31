@extends('layouts.hotel')

@section('title', 'Réservations')

@section('content')
<div x-data="{ showOpenRegisterModal: @json(!$isCashRegisterOpen) }">

{{-- En-tête --}}
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
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
    @role('reception', 'manager')
    <a href="{{ route('bookings.drafts.index') }}"
       class="px-4 py-3 text-sm font-medium transition-colors text-primary/60 hover:text-primary flex items-center gap-1.5">
        <i data-lucide="file-clock" class="w-3.5 h-3.5"></i>
        Brouillons
        @php
            $draftCount = \App\Models\BookingDraft::active()->where('created_by', auth()->id())->count();
        @endphp
        @if($draftCount > 0)
            <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-amber-500 text-white text-[10px] font-bold">
                {{ $draftCount > 9 ? '9+' : $draftCount }}
            </span>
        @endif
    </a>
    @endrole
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
            <a href="{{ route('bookings.index', array_merge(request()->except(['status', 'page']), ['tab' => $tab, 'status' => $value])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors
                      {{ $status === $value ? 'bg-primary text-white' : 'bg-white text-primary/60 hover:text-primary border border-secondary/30' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="flex items-center gap-3 w-full lg:w-auto">
        <form method="GET" action="{{ route('bookings.index') }}" class="relative flex-1">
            <input type="hidden" name="tab" value="{{ $tab }}">
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
    </div>
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
        <div class="hidden md:grid md:grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 bg-accent/20">
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
            {{-- Les filtres courants suivent dans l'URL : la fiche ouverte sait
                 alors dans quelle sélection elle se trouve, et ses flèches
                 « précédent / suivant » parcourent cette même sélection. --}}
            <a href="{{ route('bookings.show', array_merge([$booking], request()->only('tab', 'status', 'search'))) }}"
               class="block space-y-1 md:space-y-0 md:grid md:grid-cols-12 gap-4 px-5 py-3.5 border-b border-secondary/10 hover:bg-accent/10 transition-colors items-center cursor-pointer">

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