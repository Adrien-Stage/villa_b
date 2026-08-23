@extends('layouts.hotel')

@section('title', 'Choisir une chambre')

@section('content')

<div class="max-w-3xl mx-auto" x-data="roomSelector({
    checkInTime: '{{ $checkInTime }}',
    checkInDate: '{{ $checkIn }}',
    checkInDateFormatted: '{{ \Carbon\Carbon::parse($checkIn)->locale('fr')->isoFormat('D MMMM YYYY') }}',
    storeUrl: '{{ route('bookings.store') }}',
    csrfToken: '{{ csrf_token() }}',
    customerId: '{{ $customer->id }}',
    bookerId: '{{ $bookerId ?? '' }}',
    checkIn: '{{ $checkIn }}',
    checkOut: '{{ $checkOut }}',
    adults: '{{ $adults }}',
    children: '{{ $children }}',
    source: '{{ $source }}',
})">

    {{-- En-tête --}}
    <div class="mb-6">
        <a :href="'{{ route('bookings.create') }}?customer_id=' + customerId + '&booker_id=' + bookerId + '&check_in=' + checkIn + '&check_out=' + checkOut + '&check_in_time=' + encodeURIComponent(checkInTime) + '&adults=' + adults + '&children=' + children + '&source=' + encodeURIComponent(source)"
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
                <span>{{ \Carbon\Carbon::parse($checkIn)->locale('fr')->isoFormat('D MMM') }}</span>
                <span class="text-xs font-semibold text-primary/70 bg-slate-100 px-1.5 py-0.5 rounded" x-text="checkInTime"></span>
                <span>→ {{ \Carbon\Carbon::parse($checkOut)->locale('fr')->isoFormat('D MMM YYYY') }}</span>
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
                <a :href="'{{ route('bookings.create') }}?customer_id=' + customerId + '&booker_id=' + bookerId + '&check_in=' + checkIn + '&check_out=' + checkOut + '&check_in_time=' + encodeURIComponent(checkInTime) + '&adults=' + adults + '&children=' + children + '&source=' + encodeURIComponent(source)"
                   class="inline-flex items-center gap-1.5 text-xs text-secondary hover:text-primary transition-colors">
                    <i data-lucide="arrow-left" class="w-3 h-3"></i>
                    Modifier les critères de recherche
                </a>
            </div>
        </div>
    @else
        <div x-data="{ activeCategory: 'all' }">
            {{-- Filtres par catégorie et actions rapides accordéon --}}
            <div class="flex items-center justify-between gap-3 flex-wrap mb-5">
                {{-- Boutons de filtre --}}
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" 
                            @click="activeCategory = 'all'; $dispatch('expand-all-categories')"
                            :class="activeCategory === 'all' ? 'bg-primary text-white border-transparent' : 'bg-white text-primary/60 hover:text-primary border-secondary/30'"
                            class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all shadow-xs cursor-pointer border flex items-center gap-2">
                        Toutes les catégories
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                              :class="activeCategory === 'all' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                            {{ $availableRooms->flatten(1)->count() }}
                        </span>
                    </button>
                    @foreach($roomTypes as $type)
                        @php $rooms = $availableRooms[$type->id] ?? collect(); @endphp
                        @if($rooms->isEmpty()) @continue @endif
                        <button type="button" 
                                @click="activeCategory = '{{ $type->id }}'; $dispatch('expand-category-{{ $type->id }}')"
                                :class="activeCategory === '{{ $type->id }}' ? 'bg-primary text-white border-transparent' : 'bg-white text-primary/60 hover:text-primary border-secondary/30'"
                                class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all shadow-xs cursor-pointer border flex items-center gap-2">
                            {{ $type->name }}
                            <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold"
                                  :class="activeCategory === '{{ $type->id }}' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                                {{ $rooms->count() }}
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- Actions rapides Déplier / Replier tout --}}
                <div class="flex items-center gap-2 text-xs font-medium">
                    <button type="button"
                            @click="$dispatch('expand-all-categories')"
                            class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-primary/70 hover:text-primary transition-colors flex items-center gap-1.5">
                        <i data-lucide="chevrons-down" class="w-3.5 h-3.5"></i>
                        Tout déplier
                    </button>
                    <button type="button"
                            @click="$dispatch('collapse-all-categories')"
                            class="px-2.5 py-1 rounded bg-slate-100 hover:bg-slate-200 text-primary/70 hover:text-primary transition-colors flex items-center gap-1.5">
                        <i data-lucide="chevrons-up" class="w-3.5 h-3.5"></i>
                        Tout replier
                    </button>
                </div>
            </div>

            <div class="space-y-4">
                @foreach($roomTypes as $type)
                    @php $rooms = $availableRooms[$type->id] ?? collect(); @endphp
                    @if($rooms->isEmpty()) @continue @endif

                    <div x-data="{ isOpen: true }"
                         @expand-all-categories.window="isOpen = true"
                         @collapse-all-categories.window="isOpen = false"
                         @expand-category-{{ $type->id }}.window="isOpen = true"
                         x-show="activeCategory === 'all' || activeCategory === '{{ $type->id }}'"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform scale-[0.98]"
                         x-transition:enter-end="opacity-100 transform scale-100"
                         class="bg-white rounded-xl shadow-sm border border-secondary/15 overflow-hidden transition-all duration-200">
                        
                        {{-- En-tête interactif de la catégorie (Accordéon) --}}
                        <div @click="isOpen = !isOpen"
                             class="px-5 py-4 flex items-center justify-between cursor-pointer select-none bg-white hover:bg-slate-50/80 transition-colors border-b border-transparent group"
                             :class="isOpen ? 'border-secondary/10' : ''"
                             role="button"
                             :aria-expanded="isOpen">
                            
                            <div class="flex items-center gap-3.5">
                                {{-- Chevron animé interactif --}}
                                <div class="w-8 h-8 rounded-lg bg-slate-100 group-hover:bg-slate-200 flex items-center justify-center text-primary/60 transition-all duration-300 flex-shrink-0"
                                     :class="isOpen ? 'rotate-180 bg-primary/10 text-primary group-hover:bg-primary/20' : 'rotate-0'">
                                    <i data-lucide="chevron-down" class="w-4 h-4 transition-transform"></i>
                                </div>

                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-heading font-semibold text-primary text-base group-hover:text-secondary transition-colors">
                                            {{ $type->name }}
                                        </h3>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700">
                                            {{ $rooms->count() }} chambre{{ $rooms->count() > 1 ? 's' : '' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-primary/50 mt-0.5 flex items-center gap-2">
                                        <span>{{ $type->max_capacity }} pers. max</span>
                                        @if($type->size_sqm)
                                            <span>&bull;</span>
                                            <span>{{ $type->size_sqm }} m²</span>
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-right">
                                <div>
                                    @php
                                        $price = $type->getCalculatedPricePerNight($adults, $children) / 100;
                                    @endphp
                                    <span class="font-heading font-semibold text-primary text-base">
                                        {{ number_format($price, 0, ',', ' ') }} FCFA
                                    </span>
                                    <span class="text-xs text-primary/50 block">/nuit</span>
                                </div>
                            </div>
                        </div>

                        {{-- Contenu dépliable fluide : Chambres disponibles --}}
                        <div x-show="isOpen"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2"
                             class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 bg-slate-50/40 border-t border-slate-100">
                            @foreach($rooms as $room)
                                @php
                                    $roomData = [
                                        'id' => $room->id,
                                        'number' => $room->number,
                                        'floor' => $room->floor,
                                        'viewType' => $room->view_type,
                                        'typeName' => $type->name,
                                        'isOccupied' => $room->is_currently_occupied,
                                        'status' => $room->status->value,
                                        'isBeingCleaned' => $room->is_being_cleaned,
                                        'cleaningReadyTime' => $room->cleaning_ready_time,
                                        'cleaningMinutesRemaining' => $room->cleaning_minutes_remaining,
                                        'cleaningLabel' => $room->cleaning_label,
                                        'currentCheckoutFormatted' => $room->current_checkout_formatted,
                                        'currentCheckoutTime' => $room->current_checkout_time,
                                        'currentReadyTime' => $room->current_ready_time,
                                        'cleaningDelay' => $room->cleaning_delay_minutes,
                                        'hasSameDayDeparture' => $room->has_same_day_departure,
                                        'sameDayCheckoutTime' => $room->same_day_checkout_time,
                                        'sameDayReadyTime' => $room->same_day_ready_time,
                                        'hasCleaningConflict' => $room->has_cleaning_conflict,
                                        'hasRotationConflict' => $room->has_rotation_conflict,
                                        'hasAnyConflict' => $room->has_any_conflict,
                                        'effectiveReadyTime' => $room->effective_ready_time,
                                        'conflictType' => $room->conflict_type,
                                    ];
                                @endphp
                                <div class="border border-secondary/20 rounded-xl p-3.5 bg-white hover:border-primary/50 hover:shadow-sm transition-all text-left flex flex-col justify-between group/card relative cursor-pointer"
                                     @click="selectRoom(@js($roomData))">
                                    
                                    <div>
                                        <div class="flex items-center justify-between gap-2 mb-1.5">
                                            <div class="flex items-center gap-2">
                                                <span class="font-heading font-bold text-primary text-base group-hover/card:text-secondary transition-colors">
                                                    Chambre {{ $room->number }}
                                                </span>
                                                @if($room->floor)
                                                    <span class="text-[11px] text-primary/50 font-normal">Ét. {{ $room->floor }}</span>
                                                @endif
                                            </div>

                                            {{-- Badge de statut actuel --}}
                                            @if($room->is_currently_occupied)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                    Occupée
                                                </span>
                                            @elseif($room->is_being_cleaned)
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-yellow-50 text-yellow-800 border border-yellow-200"
                                                      title="{{ $room->cleaning_label ?? 'Nettoyage en cours' }}">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-yellow-500 animate-pulse"></span>
                                                    En nettoyage
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    Disponible
                                                </span>
                                            @endif
                                        </div>

                                        @if($room->view_type)
                                            <p class="text-xs text-primary/50 capitalize mb-2">Vue {{ $room->view_type }}</p>
                                        @endif

                                        {{-- 1. Détail si chambre actuellement en nettoyage --}}
                                        @if($room->is_being_cleaned)
                                            <div class="mt-2 p-2 rounded-lg {{ $room->has_cleaning_conflict ? 'bg-amber-50/95 border border-amber-200 text-amber-950' : 'bg-yellow-50/80 border border-yellow-200 text-yellow-950' }} text-[11px]">
                                                <div class="flex items-center justify-between gap-1 font-semibold {{ $room->has_cleaning_conflict ? 'text-amber-800' : 'text-yellow-800' }} mb-0.5">
                                                    <span class="flex items-center gap-1">
                                                        <i data-lucide="{{ $room->has_cleaning_conflict ? 'alert-triangle' : 'sparkles' }}" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                                        <span>{{ $room->has_cleaning_conflict ? 'Nettoyage en cours (Conflit)' : 'Ménage en cours' }}</span>
                                                    </span>
                                                    <span class="font-mono text-xs font-bold">{{ $room->cleaning_ready_time }}</span>
                                                </div>
                                                <p class="text-[10px] leading-tight text-slate-600">
                                                    Prête à <strong class="text-slate-800">{{ $room->cleaning_ready_time }}</strong> (durée : {{ $room->cleaning_delay_minutes }} min)
                                                    @if($room->cleaning_minutes_remaining > 0)
                                                        &bull; {{ $room->cleaning_label }}
                                                    @endif
                                                </p>
                                            </div>
                                        @endif

                                        {{-- 2. Détail d'occupation en cours si occupée --}}
                                        @if($room->is_currently_occupied && $room->current_checkout_formatted)
                                            <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] text-slate-600 space-y-0.5 bg-slate-50/70 rounded p-1.5">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-slate-500">Départ occupant :</span>
                                                    <span class="font-medium text-slate-700">{{ $room->current_checkout_formatted }} à {{ $room->current_checkout_time }}</span>
                                                </div>
                                                <div class="flex items-center justify-between">
                                                    <span class="text-slate-500">Prête post-ménage :</span>
                                                    <span class="font-semibold text-slate-800">{{ $room->current_ready_time }} <span class="text-[9px] font-normal text-slate-400">(+{{ $room->cleaning_delay_minutes }}m)</span></span>
                                                </div>
                                            </div>
                                        @endif

                                        {{-- 3. Indicateur de rotation le jour d'arrivée --}}
                                        @if($room->has_same_day_departure)
                                            <div class="mt-2 p-2 rounded-lg {{ $room->has_rotation_conflict ? 'bg-amber-50/90 border border-amber-200 text-amber-900' : 'bg-blue-50/70 border border-blue-200 text-blue-900' }} text-[11px]">
                                                <div class="flex items-center gap-1.5 font-semibold {{ $room->has_rotation_conflict ? 'text-amber-800' : 'text-blue-800' }} mb-0.5">
                                                    <i data-lucide="{{ $room->has_rotation_conflict ? 'alert-triangle' : 'refresh-cw' }}" class="w-3.5 h-3.5 flex-shrink-0"></i>
                                                    <span>{{ $room->has_rotation_conflict ? 'Conflit de rotation' : 'Rotation le jour d\'arrivée' }}</span>
                                                </div>
                                                <p class="text-[10px] leading-tight text-slate-600">
                                                    Départ précédent à {{ $room->same_day_checkout_time }} &bull; Prête à <strong class="text-slate-800">{{ $room->same_day_ready_time }}</strong> (+{{ $room->cleaning_delay_minutes }}m)
                                                </p>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs text-primary font-medium group-hover/card:text-secondary">
                                        <span>Sélectionner cette chambre</span>
                                        <i data-lucide="arrow-right" class="w-4 h-4 text-primary/30 group-hover/card:text-secondary group-hover/card:translate-x-0.5 transition-all"></i>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 flex justify-start">
        <a :href="'{{ route('bookings.create') }}?customer_id=' + customerId + '&booker_id=' + bookerId + '&check_in=' + checkIn + '&check_out=' + checkOut + '&check_in_time=' + encodeURIComponent(checkInTime) + '&adults=' + adults + '&children=' + children + '&source=' + encodeURIComponent(source)"
           class="px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2 shadow-xs">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Précédent
        </a>
    </div>

    {{-- Formulaire unique de soumission d'étape --}}
    <form id="step-submission-form" method="POST" action="{{ route('bookings.store') }}" class="hidden">
        @csrf
        <input type="hidden" name="step" value="3">
        <input type="hidden" name="customer_id" :value="customerId">
        <input type="hidden" name="booker_id" :value="bookerId">
        <input type="hidden" name="room_id" :value="selectedRoom ? selectedRoom.id : ''">
        <input type="hidden" name="check_in" :value="checkIn">
        <input type="hidden" name="check_out" :value="checkOut">
        <input type="hidden" name="check_in_time" :value="checkInTime">
        <input type="hidden" name="adults_count" :value="adults">
        <input type="hidden" name="children_count" :value="children">
        <input type="hidden" name="source" :value="source">
        <input type="hidden" name="draft_token" value="{{ $draftToken ?? '' }}">
    </form>


    {{-- Modal interactif : Détection de conflit Housekeeping / Rotation & Ajustement de l'heure de check-in --}}
    <div x-show="isModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
         style="display: none;">
        
        <div @click.away="isModalOpen = false"
             x-show="isModalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="bg-white rounded-2xl shadow-xl max-w-lg w-full overflow-hidden border border-secondary/20">
            
            {{-- En-tête du modal --}}
            <div class="px-6 py-4 border-b border-secondary/10 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-heading font-semibold text-primary text-base"
                            x-text="getModalTitle()">
                        </h3>
                        <p class="text-xs text-primary/50" x-show="selectedRoom">
                            Chambre <span class="font-bold text-primary" x-text="selectedRoom ? selectedRoom.number : ''"></span> &bull; <span x-text="selectedRoom ? selectedRoom.typeName : ''"></span>
                        </p>
                    </div>
                </div>
                <button type="button" @click="isModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            {{-- Corps du modal --}}
            <div class="p-6 space-y-5">
                
                {{-- Bloc Explicatif & Alerte --}}
                <div class="p-4 rounded-xl border leading-relaxed text-xs space-y-2"
                     :class="isTimeConflicting() ? 'bg-amber-50/90 border-amber-200/90 text-amber-950' : 'bg-blue-50/70 border-blue-200 text-blue-950'">
                    <div class="flex items-center gap-2 font-semibold" :class="isTimeConflicting() ? 'text-amber-800' : 'text-blue-800'">
                        <i data-lucide="alert-triangle" class="w-4 h-4 flex-shrink-0"></i>
                        <span x-text="isTimeConflicting() ? 'Avertissement : Conflit d\'horaire avec le ménage' : 'Disponibilité post-ménage'"></span>
                    </div>

                    {{-- Explication selon le type de situation --}}
                    <template x-if="selectedRoom && selectedRoom.conflictType === 'cleaning_in_progress'">
                        <p>
                            Cette chambre est actuellement <strong>en cours de nettoyage</strong> par l'équipe housekeeping.
                        </p>
                    </template>

                    <template x-if="selectedRoom && selectedRoom.conflictType === 'same_day_rotation'">
                        <p>
                            Le client précédent quitte cette chambre le <strong x-text="checkInDateFormatted"></strong> à <strong x-text="selectedRoom.sameDayCheckoutTime"></strong>.
                        </p>
                    </template>

                    <div class="pt-1 flex items-center justify-between text-[11px] border-t" :class="isTimeConflicting() ? 'border-amber-200' : 'border-blue-200'">
                        <span>Délai de ménage (housekeeping) :</span>
                        <span class="font-semibold">+<span x-text="selectedRoom ? selectedRoom.cleaningDelay : 120"></span> minutes</span>
                    </div>

                    <div class="flex items-center justify-between text-xs font-bold pt-0.5">
                        <span>Chambre disponible et prête à partir de :</span>
                        <span class="text-sm px-2 py-0.5 rounded bg-white border font-mono font-bold" 
                              :class="isTimeConflicting() ? 'border-amber-300 text-amber-900' : 'border-blue-300 text-blue-900'" 
                              x-text="selectedRoom ? selectedRoom.effectiveReadyTime : ''"></span>
                    </div>
                </div>

                {{-- Champ interactif d'ajustement de l'heure de check-in --}}
                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-4 space-y-3">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-1">
                            Heure d'arrivée (Check-in) du client *
                        </label>
                        <p class="text-[11px] text-slate-500 mb-2">
                            Ajustez l'horaire prévu pour garantir que le ménage sera terminé avant l'arrivée du client.
                        </p>
                        <div class="flex items-center gap-2">
                            <input type="time"
                                   x-model="checkInTime"
                                   required
                                   class="flex-1 px-3 py-2 text-sm font-semibold rounded-lg border border-slate-300 bg-white text-slate-900 outline-none focus:border-secondary focus:ring-1 focus:ring-secondary">
                            
                            <button type="button"
                                    @click="adjustToReadyTime()"
                                    class="px-3 py-2 bg-secondary/15 hover:bg-secondary/25 text-primary text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5 flex-shrink-0">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                Régler sur <span x-text="selectedRoom ? selectedRoom.effectiveReadyTime : ''"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Diagnostic en direct sur l'heure saisie --}}
                    <template x-if="isTimeConflicting()">
                        <div class="flex items-start gap-1.5 text-xs text-red-600 font-medium">
                            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
                            <span>
                                Attention : L'heure saisie (<span x-text="checkInTime"></span>) est antérieure à la fin du ménage (<span x-text="selectedRoom ? selectedRoom.effectiveReadyTime : ''"></span>). Le client risque de devoir patienter.
                            </span>
                        </div>
                    </template>

                    <template x-if="!isTimeConflicting()">
                        <div class="flex items-center gap-1.5 text-xs text-emerald-700 font-medium">
                            <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
                            <span>Horaire conforme : La chambre sera prête avant l'arrivée du client.</span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Pied du modal --}}
            <div class="px-6 py-4 border-t border-secondary/10 bg-slate-50/50 flex items-center justify-between gap-3">
                <button type="button"
                        @click="isModalOpen = false"
                        class="px-4 py-2.5 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-slate-100 transition-colors">
                    Annuler
                </button>
                <button type="button"
                        @click="confirmModalAndSubmit()"
                        class="px-5 py-2.5 bg-primary hover:bg-surface-dark text-white text-sm font-semibold rounded-lg transition-colors flex items-center gap-2 shadow-sm">
                    <span>Valider et continuer</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('roomSelector', (config) => ({
        checkInTime: config.checkInTime || '14:00',
        checkInDate: config.checkInDate,
        checkInDateFormatted: config.checkInDateFormatted,
        storeUrl: config.storeUrl,
        csrfToken: config.csrfToken,
        customerId: config.customerId,
        bookerId: config.bookerId,
        checkIn: config.checkIn,
        checkOut: config.checkOut,
        adults: config.adults,
        children: config.children,
        source: config.source,
        
        selectedRoom: null,
        isModalOpen: false,

        init() {
            this.$watch('isModalOpen', (val) => {
                if (val && window.lucide) {
                    this.$nextTick(() => window.lucide.createIcons());
                }
            });
        },

        selectRoom(room) {
            this.selectedRoom = room;
            // Ouvrir le modal d'ajustement si conflit ou ménage en cours / rotation
            if (room.hasAnyConflict || room.hasSameDayDeparture || room.isBeingCleaned) {
                this.isModalOpen = true;
                if (window.lucide) {
                    this.$nextTick(() => window.lucide.createIcons());
                }
            } else {
                this.submitDirect(room.id);
            }
        },

        getModalTitle() {
            if (!this.selectedRoom) return 'Ajustement du Check-in';
            if (this.selectedRoom.conflictType === 'cleaning_in_progress') {
                return 'Ménage en cours sur cette chambre';
            }
            if (this.selectedRoom.conflictType === 'same_day_rotation') {
                return 'Rotation le jour d\'arrivée';
            }
            return 'Disponibilité & Heure d\'arrivée';
        },

        isTimeConflicting() {
            if (!this.selectedRoom || !this.selectedRoom.effectiveReadyTime) return false;
            return this.checkInTime < this.selectedRoom.effectiveReadyTime;
        },

        adjustToReadyTime() {
            if (this.selectedRoom && this.selectedRoom.effectiveReadyTime) {
                this.checkInTime = this.selectedRoom.effectiveReadyTime;
            }
        },

        confirmModalAndSubmit() {
            const form = document.getElementById('step-submission-form');
            if (form) {
                if (this.selectedRoom && this.selectedRoom.id) {
                    const roomInput = form.querySelector('input[name="room_id"]');
                    if (roomInput) roomInput.value = this.selectedRoom.id;
                }
                const timeInput = form.querySelector('input[name="check_in_time"]');
                if (timeInput) timeInput.value = this.checkInTime;
                form.submit();
            }
        },

        submitDirect(roomId) {
            this.selectedRoom = { id: roomId };
            const form = document.getElementById('step-submission-form');
            if (form) {
                const roomInput = form.querySelector('input[name="room_id"]');
                if (roomInput) roomInput.value = roomId;
                const timeInput = form.querySelector('input[name="check_in_time"]');
                if (timeInput) timeInput.value = this.checkInTime;
                form.submit();
            }
        }
    }));
});
</script>
@endpush

@endsection