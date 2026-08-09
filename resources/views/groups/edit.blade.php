@extends('layouts.hotel')

@section('title', 'Modifier ' . $groupBooking->group_code)

@section('content')

<div class="max-w-2xl mx-auto">

    <div class="mb-6">
        <a href="{{ route('groups.show', $groupBooking) }}"
           class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour au dossier
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">
            Modifier {{ $groupBooking->group_code }}
        </h1>
        <p class="text-sm text-primary/50 mt-0.5">{{ $groupBooking->group_name }}</p>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow-sm p-6">
        <form method="POST" action="{{ route('groups.update', $groupBooking) }}">
            @csrf @method('PUT')

            {{-- Contact principal --}}
            <div class="mb-4">
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Contact principal *
                </label>
                <select name="contact_customer_id" required
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}"
                            {{ old('contact_customer_id', $groupBooking->contact_customer_id) == $customer->id ? 'selected' : '' }}>
                            {{ $customer->full_name }}
                            @if($customer->phone) — {{ $customer->phone }}@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Nom du groupe *
                    </label>
                    <input type="text" name="group_name"
                           value="{{ old('group_name', $groupBooking->group_name) }}"
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    @error('group_name')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Type d'événement *
                    </label>
                    <select name="event_type" required
                            class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        <option value="family"     {{ old('event_type', $groupBooking->event_type) === 'family'     ? 'selected' : '' }}>Famille</option>
                        <option value="corporate"  {{ old('event_type', $groupBooking->event_type) === 'corporate'  ? 'selected' : '' }}>Corporate / Séminaire</option>
                        <option value="wedding"    {{ old('event_type', $groupBooking->event_type) === 'wedding'    ? 'selected' : '' }}>Mariage</option>
                        <option value="tour_group" {{ old('event_type', $groupBooking->event_type) === 'tour_group' ? 'selected' : '' }}>Tour groupe</option>
                    </select>
                </div>
            </div>

            {{-- Dates --}}
            @php $hasCheckedIn = $groupBooking->bookings->where('status', 'checked_in')->count() > 0; @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Date d'arrivée *
                    </label>
                    <input type="date" name="start_date"
                           value="{{ old('start_date', $groupBooking->start_date->format('Y-m-d')) }}"
                           {{ $hasCheckedIn ? 'disabled' : '' }}
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary disabled:opacity-50 disabled:cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Date de départ *
                    </label>
                    <input type="date" name="end_date"
                           value="{{ old('end_date', $groupBooking->end_date->format('Y-m-d')) }}"
                           {{ $hasCheckedIn ? 'disabled' : '' }}
                           required
                           class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary disabled:opacity-50 disabled:cursor-not-allowed">
                </div>
            </div>

            @if($hasCheckedIn)
                {{-- Champs cachés pour soumettre les dates inchangées si disabled --}}
                <input type="hidden" name="start_date" value="{{ $groupBooking->start_date->format('Y-m-d') }}">
                <input type="hidden" name="end_date" value="{{ $groupBooking->end_date->format('Y-m-d') }}">
                <div class="mb-4 flex items-center gap-2 text-xs text-orange-600 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 flex-shrink-0"></i>
                    Les dates ne peuvent pas être modifiées car des chambres sont déjà en séjour.
                </div>
            @endif

            <div class="mb-6">
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Notes
                </label>
                <textarea name="notes" rows="3"
                          class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary resize-none">{{ old('notes', $groupBooking->notes) }}</textarea>
            </div>

            <div class="flex items-center justify-between">
                {{-- Bouton annuler le dossier --}}
                @if(!in_array($groupBooking->status, ['completed', 'cancelled', 'in_house']))
                    <form method="POST" action="{{ route('groups.cancel', $groupBooking) }}"
                          onsubmit="return confirm('Annuler le dossier {{ $groupBooking->group_code }} et toutes ses réservations ?')">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-2 px-4 py-2 bg-white border border-red-200 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                            Annuler le dossier
                        </button>
                    </form>
                @else
                    <div></div>
                @endif

                <div class="flex items-center gap-3">
                    <a href="{{ route('groups.show', $groupBooking) }}"
                       class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                        Annuler
                    </a>
                    <button type="submit"
                            class="px-5 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                        Enregistrer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection