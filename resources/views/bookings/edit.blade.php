@extends('layouts.hotel')

@section('title', 'Modifier ' . $booking->booking_number)

@section('content')

<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <a href="{{ route('bookings.show', $booking) }}"
           class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour à la réservation
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">
            Modifier {{ $booking->booking_number }}
        </h1>
        <p class="text-sm text-primary/50 mt-0.5">
            Client : {{ $booking->customer->full_name }}
        </p>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('bookings.update', $booking) }}">
            @csrf @method('PUT')

            {{-- Dates et heure --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Arrivée *
                    </label>
                    <input type="date" name="check_in"
                           value="{{ old('check_in', $booking->check_in->format('Y-m-d')) }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Heure d'arrivée
                    </label>
                    <input type="time" name="check_in_time"
                           value="{{ old('check_in_time', $booking->check_in_time ?? '14:00') }}"
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Départ *
                    </label>
                    <input type="date" name="check_out"
                           value="{{ old('check_out', $booking->check_out->format('Y-m-d')) }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>

            {{-- Chambre --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Chambre *
                </label>
                <select name="room_id" required
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    @foreach($roomTypes as $type)
                        <optgroup label="{{ $type->name }}">
                            @foreach($type->rooms->where('is_active', true) as $room)
                                <option value="{{ $room->id }}"
                                    {{ old('room_id', $booking->room_id) == $room->id ? 'selected' : '' }}>
                                    Chambre {{ $room->number }}
                                    @if($room->floor) — Étage {{ $room->floor }}@endif
                                    @if($room->view_type) — Vue {{ $room->view_type }}@endif
                                    ({{ number_format($type->base_price / 100, 0, ',', ' ') }} FCFA/nuit)
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                @error('room_id')
                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                @enderror
            </div>

            {{-- Personnes --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Adultes *
                    </label>
                    <input type="number" name="adults_count"
                           value="{{ old('adults_count', $booking->adults_count) }}"
                           min="1" required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Enfants
                    </label>
                    <input type="number" name="children_count"
                           value="{{ old('children_count', $booking->children_count) }}"
                           min="0"
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>

            {{-- Source --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Origine
                </label>
                <select name="source"
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    @foreach(['direct' => 'Direct', 'phone' => 'Téléphone', 'email' => 'Email', 'walk_in' => 'Walk-in', 'ota_bookingcom' => 'Booking.com'] as $val => $label)
                        <option value="{{ $val }}" {{ old('source', $booking->source) === $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Notes --}}
            <div class="mb-6">
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Notes client
                </label>
                <textarea name="notes" rows="3"
                          placeholder="Demandes spéciales, allergies, préférences..."
                          class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary resize-none placeholder-primary/30">{{ old('notes', $booking->notes) }}</textarea>
            </div>

            {{-- Récap prix --}}
            <div class="bg-accent/20 rounded-lg p-4 mb-5 text-xs text-primary/60 space-y-1" id="price-recap">
                <p>Modifiez les dates ou la chambre pour recalculer le prix.</p>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('bookings.show', $booking) }}"
                   class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                    Annuler
                </a>
                <button type="submit"
                        class="px-5 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

@endsection