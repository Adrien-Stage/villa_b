@extends('layouts.hotel')

@section('title', 'Choisir une chambre')

@section('content')

<div class="max-w-3xl mx-auto">

    {{-- En-tête --}}
    <div class="mb-6">
        <a href="{{ route('bookings.create', ['customer_id' => $customer->id, 'booker_id' => $bookerId, 'check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => $children, 'source' => $source]) }}"
           class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">Choisir une chambre</h1>
        <p class="text-sm text-primary/50 mt-0.5">Étape 2 — Sélection de la chambre</p>
    </div>

    {{-- Indicateur d'étapes --}}
    <div class="flex items-center gap-3 mb-8">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-green-500 text-white flex items-center justify-center text-xs">
                <i data-lucide="check" class="w-3.5 h-3.5"></i>
            </div>
            <span class="text-xs font-medium text-primary/50">Client</span>
        </div>
        <div class="flex-1 h-px bg-primary/20"></div>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-semibold">2</div>
            <span class="text-xs font-medium text-primary">Chambre & dates</span>
        </div>
        <div class="flex-1 h-px bg-secondary/20"></div>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-secondary/20 text-primary/40 flex items-center justify-center text-xs font-semibold">3</div>
            <span class="text-xs text-primary/40">Confirmation</span>
        </div>
    </div>

    {{-- Récap de la sélection --}}
    <div class="bg-white rounded-xl shadow-sm p-4 mb-5 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 text-sm text-primary">
                <i data-lucide="user" class="w-4 h-4 text-primary/40"></i>
                {{ $customer->full_name }}
            </div>
            <div class="w-px h-4 bg-secondary/30"></div>
            <div class="flex items-center gap-2 text-sm text-primary">
                <i data-lucide="calendar" class="w-4 h-4 text-primary/40"></i>
                {{ \Carbon\Carbon::parse($checkIn)->locale('fr')->isoFormat('D MMM') }}
                → {{ \Carbon\Carbon::parse($checkOut)->locale('fr')->isoFormat('D MMM YYYY') }}
            </div>
            <div class="w-px h-4 bg-secondary/30"></div>
            <div class="flex items-center gap-2 text-sm text-primary">
                <i data-lucide="users" class="w-4 h-4 text-primary/40"></i>
                {{ $adults }} adulte{{ $adults > 1 ? 's' : '' }}
                @if($children > 0), {{ $children }} enfant{{ $children > 1 ? 's' : '' }}@endif
            </div>
        </div>
        @php
            $nights = \Carbon\Carbon::parse($checkIn)->diffInDays(\Carbon\Carbon::parse($checkOut));
        @endphp
        <span class="text-xs text-primary/50">{{ $nights }} nuit{{ $nights > 1 ? 's' : '' }}</span>
    </div>

    {{-- Chambres disponibles groupées par type --}}
    @if($roomTypes->isEmpty())
        <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <i data-lucide="search-x" class="w-10 h-10 text-primary/20 mx-auto mb-3"></i>
            @php
                $totalPeople = $adults + $children;
            @endphp
            @if($totalPeople > $maxCapacityLimit)
                <p class="text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200/50 rounded-lg p-3 inline-block max-w-md mx-auto">
                    Le nombre de personnes ({{ $totalPeople }} occupants) dépasse la capacité maximale de nos chambres (Maximum {{ $maxCapacityLimit }} personnes par chambre).
                </p>
                <p class="text-xs text-primary/50 mt-2">Veuillez réduire le nombre d'occupants ou effectuer des réservations séparées.</p>
            @else
                <p class="text-sm text-primary/50">Aucune chambre disponible pour cette période.</p>
            @endif
            <div class="mt-4">
                <a href="{{ route('bookings.create', ['customer_id' => $customer->id, 'booker_id' => $bookerId, 'check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => $children, 'source' => $source]) }}"
                   class="inline-flex items-center gap-1.5 text-xs text-secondary hover:text-primary transition-colors">
                    <i data-lucide="arrow-left" class="w-3 h-3"></i>
                    Modifier les critères de recherche
                </a>
            </div>
        </div>
    @else
        <div x-data="{ activeCategory: 'all' }">
            {{-- Filtres par catégorie --}}
            <div class="flex items-center gap-2 flex-wrap mb-6">
                <button type="button" 
                        @click="activeCategory = 'all'"
                        :class="activeCategory === 'all' ? 'bg-primary text-white border-transparent' : 'bg-white text-primary/60 hover:text-primary border-secondary/30'"
                        class="px-4 py-2 rounded-full text-xs font-semibold transition-all shadow-xs cursor-pointer border flex items-center gap-2">
                    Toutes les catégories
                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white">
                        {{ $availableRooms->flatten(1)->count() }}
                    </span>
                </button>
                @foreach($roomTypes as $type)
                    @php $rooms = $availableRooms[$type->id] ?? collect(); @endphp
                    @if($rooms->isEmpty()) @continue @endif
                    <button type="button" 
                            @click="activeCategory = '{{ $type->id }}'"
                            :class="activeCategory === '{{ $type->id }}' ? 'bg-primary text-white border-transparent' : 'bg-white text-primary/60 hover:text-primary border-secondary/30'"
                            class="px-4 py-2 rounded-full text-xs font-semibold transition-all shadow-xs cursor-pointer border flex items-center gap-2">
                        {{ $type->name }}
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 group-hover:bg-slate-200 transition-colors">
                            {{ $rooms->count() }}
                        </span>
                    </button>
                @endforeach
            </div>

            <div class="space-y-4">
                @foreach($roomTypes as $type)
                    @php $rooms = $availableRooms[$type->id] ?? collect(); @endphp
                    @if($rooms->isEmpty()) @continue @endif

                    <div x-show="activeCategory === 'all' || activeCategory === '{{ $type->id }}'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform scale-[0.98]"
                         x-transition:enter-end="opacity-100 transform scale-100"
                         class="bg-white rounded-xl shadow-sm overflow-hidden">
                    {{-- En-tête type --}}
                    <div class="px-5 py-4 border-b border-secondary/10 flex items-center justify-between">
                        <div>
                            <h3 class="font-heading font-semibold text-primary">{{ $type->name }}</h3>
                            <p class="text-xs text-primary/50 mt-0.5">
                                {{ $type->max_capacity }} pers. max
                                @if($type->size_sqm) · {{ $type->size_sqm }} m² @endif
                            </p>
                        </div>
                        <div class="text-right">
                            @php
                                $totalPeople = $adults + ($children ?? 0);
                                $isSurcharged = $totalPeople > $type->base_capacity;
                                $pricePerNight = $type->getCalculatedPricePerNight($adults, $children ?? 0) / 100;
                            @endphp
                            <p class="text-lg font-heading font-semibold text-primary">
                                {{ number_format($pricePerNight, 0, ',', ' ') }}
                                <span class="text-xs font-normal text-primary/50">FCFA/nuit</span>
                            </p>
                            @if($isSurcharged)
                                <span class="inline-block text-[9px] font-semibold text-amber-700 bg-amber-50 border border-amber-200/50 px-1.5 py-0.5 rounded-full mt-0.5">
                                    + Surcharge occupants (capacité > {{ $type->base_capacity }} pers.)
                                </span>
                            @endif
                            <p class="text-xs text-primary/50 mt-1">
                                Total : {{ number_format($pricePerNight * $nights, 0, ',', ' ') }} FCFA
                            </p>
                        </div>
                    </div>

                    {{-- Équipements --}}
                    @if($type->amenities)
                        <div class="px-5 py-2 border-b border-secondary/10 flex items-center gap-2 flex-wrap">
                            @foreach($type->amenities as $amenity)
                                <span class="flex items-center gap-1 text-xs text-primary/50">
                                    <i data-lucide="check" class="w-3 h-3 text-green-500"></i>
                                    {{ $amenity }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Chambres disponibles --}}
                    <div class="p-4 grid grid-cols-3 gap-3">
                        @foreach($rooms as $room)
                            <form method="POST" action="{{ route('bookings.store') }}">
                                @csrf
                                <input type="hidden" name="step" value="3">
                                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                                @if($bookerId)
                                    <input type="hidden" name="booker_id" value="{{ $bookerId }}">
                                @endif
                                <input type="hidden" name="room_id" value="{{ $room->id }}">
                                <input type="hidden" name="check_in" value="{{ $checkIn }}">
                                <input type="hidden" name="check_out" value="{{ $checkOut }}">
                                <input type="hidden" name="adults_count" value="{{ $adults }}">
                                <input type="hidden" name="children_count" value="{{ $children }}">
                                <input type="hidden" name="source" value="{{ $source }}">
                                <button type="submit"
                                        class="w-full p-3 border border-secondary/20 rounded-lg hover:border-primary hover:bg-accent/10 transition-all text-left group">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-heading font-semibold text-primary text-sm group-hover:text-primary">
                                            {{ $room->number }}
                                        </span>
                                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-primary/20 group-hover:text-primary transition-colors"></i>
                                    </div>
                                    @if($room->floor)
                                        <p class="text-xs text-primary/40">Étage {{ $room->floor }}</p>
                                    @endif
                                    @if($room->view_type)
                                        <p class="text-xs text-primary/40 capitalize">Vue {{ $room->view_type }}</p>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        </div>
    @endif

    <div class="mt-6 flex justify-start">
        <a href="{{ route('bookings.create', ['customer_id' => $customer->id, 'booker_id' => $bookerId, 'check_in' => $checkIn, 'check_out' => $checkOut, 'adults' => $adults, 'children' => $children, 'source' => $source]) }}"
           class="px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2 shadow-xs">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Précédent
        </a>
    </div>
</div>

@endsection