@extends('layouts.hotel')

@section('title', 'Nouvelle réservation')

@section('content')

<div class="max-w-2xl mx-auto">

    {{-- En-tête --}}
    <div class="mb-6">
        <a href="{{ route('bookings.index') }}"
           class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour aux réservations
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">Nouvelle réservation</h1>
        <p class="text-sm text-primary/50 mt-0.5">Étape 1 — Sélection du client</p>
    </div>

    {{-- Indicateur d'étapes --}}
    <div class="flex items-center gap-3 mb-8">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-semibold">1</div>
            <span class="text-xs font-medium text-primary">Client</span>
        </div>
        <div class="flex-1 h-px bg-secondary/20"></div>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-secondary/20 text-primary/40 flex items-center justify-center text-xs font-semibold">2</div>
            <span class="text-xs text-primary/40">Chambre & dates</span>
        </div>
        <div class="flex-1 h-px bg-secondary/20"></div>
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-secondary/20 text-primary/40 flex items-center justify-center text-xs font-semibold">3</div>
            <span class="text-xs text-primary/40">Confirmation</span>
        </div>
    </div>

    {{-- Client déjà sélectionné --}}
    @if($customer)
        <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center">
                    <span class="text-white text-sm font-semibold">
                        {{ strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)) }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-primary">{{ $customer->full_name }}</p>
                    <p class="text-xs text-primary/50">
                        {{ $customer->loyalty_level }} · {{ number_format($customer->loyalty_points) }} pts
                    </p>
                </div>
            </div>
            <a href="{{ route('bookings.create') }}" class="text-xs text-primary/50 hover:text-primary">Changer</a>
        </div>

        {{-- Étape 2 : Dates et personnes --}}
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="font-heading font-semibold text-primary mb-5">Dates et personnes</h2>
            <form method="POST" action="{{ route('bookings.store') }}">
                @csrf
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                @if($booker)
                    <input type="hidden" name="booker_id" value="{{ $booker->id }}">
                @endif

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Arrivée *
                        </label>
                        <input type="date" name="check_in"
                               min="{{ now()->format('Y-m-d') }}"
                               value="{{ old('check_in') }}"
                               required
                               class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        @error('check_in')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Départ *
                        </label>
                        <input type="date" name="check_out"
                               value="{{ old('check_out') }}"
                               required
                               class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        @error('check_out')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Adultes *
                        </label>
                        <input type="number" name="adults" value="{{ old('adults', 1) }}"
                               min="1" required
                               class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Enfants
                        </label>
                        <input type="number" name="children" value="{{ old('children', 0) }}"
                               min="0"
                               class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    </div>
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                        Origine
                    </label>
                    <select name="source"
                            class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        <option value="direct">Direct</option>
                        <option value="phone">Téléphone</option>
                        <option value="email">Email</option>
                        <option value="walk_in">Walk-in</option>
                        <option value="ota_bookingcom">Booking.com</option>
                    </select>
                </div>

                <button type="submit"
                        class="w-full py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors flex items-center justify-center gap-2">
                    Voir les chambres disponibles
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        </div>

    {{-- Pas encore de client sélectionné --}}
    @else

        {{-- Formulaire Étape 1 : Clients et Mandataire --}}
        <div class="bg-white rounded-xl shadow-sm p-6 mb-4" x-data="{ isBooker: '{{ old('is_booker', '') }}', showCustomer: {{ old('is_booker') ? 'true' : 'false' }} }">
            <h2 class="font-heading font-semibold text-primary mb-4">Informations de Réservation</h2>

            @if($errors->any())
                <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                    Veuillez vérifier les informations saisies.
                </div>
            @endif

            <form method="POST" action="{{ route('bookings.store') }}">
                @csrf
                <input type="hidden" name="step" value="1">

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-primary mb-2">Qui effectue cette réservation ?</label>
                    <select name="is_booker" x-model="isBooker" @change="showCustomer = (isBooker === 'self')" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary mb-4">
                        <option value="">Sélectionner...</option>
                        <option value="self">Le client final lui-même</option>
                        <option value="other">Une tierce personne (Mandataire / Organisateur)</option>
                    </select>
                </div>

                <div x-show="isBooker === 'other'" style="display: none;" class="mb-6 p-4 bg-gray-50 border border-secondary/20 rounded-xl">
                    <h3 class="text-sm font-semibold text-primary mb-3">Le mandataire (Celui qui réserve)</h3>
                    <x-customer-search :customers="$customers" name="booker_id" :value="old('booker_id')" :allow-creation="true" creation-label="Créer un mandataire">
                        {{-- Mode création Booker (Slot) --}}
                        <input type="hidden" name="new_booker" value="1" x-bind:disabled="!isCreatingNew">
                        <div class="flex justify-between items-center bg-blue-50 text-blue-800 p-3 rounded-lg border border-blue-100 mb-4">
                            <div class="flex items-center"><i data-lucide="user-plus" class="w-4 h-4 mr-2"></i><span class="font-medium">Nouveau mandataire</span></div>
                            <button type="button" @click="cancelCreatingNew()" class="text-sm font-medium hover:underline">Annuler</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Prénom *</label>
                                <input type="text" name="booker_first_name" value="{{ old('booker_first_name') }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('booker_first_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Nom *</label>
                                <input type="text" name="booker_last_name" value="{{ old('booker_last_name') }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('booker_last_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Téléphone *</label>
                                <input type="text" name="booker_phone" value="{{ old('booker_phone') }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('booker_phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Email</label>
                                <input type="email" name="booker_email" value="{{ old('booker_email') }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('booker_email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Type CNI/Pass</label>
                                <select name="booker_id_document_type" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                    <option value="CNI" {{ old('booker_id_document_type') == 'CNI' ? 'selected' : '' }}>CNI</option>
                                    <option value="Passeport" {{ old('booker_id_document_type') == 'Passeport' ? 'selected' : '' }}>Passeport</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Numéro CNI/Pass *</label>
                                <input type="text" name="booker_id_document_number" value="{{ old('booker_id_document_number') }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('booker_id_document_number')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </x-customer-search>
                    
                    <div class="mt-4 text-right" x-show="!showCustomer">
                        <button type="button" @click="showCustomer = true" class="px-4 py-2 bg-secondary text-white text-sm font-medium rounded-lg hover:bg-secondary/90 transition-colors">
                            Suivant : Client séjournant <i data-lucide="arrow-right" class="w-4 h-4 inline"></i>
                        </button>
                    </div>
                </div>

                <div x-show="showCustomer" style="display: none;" class="mb-6 pt-4 border-t border-secondary/20">
                    <h3 class="text-sm font-semibold text-primary mb-3">Le client qui va séjourner</h3>
                    <x-customer-search :customers="$customers" name="customer_id" :value="old('customer_id')" :allow-creation="true">
                        {{-- Mode création (Slot) --}}
                        <input type="hidden" name="new_customer" value="1" x-bind:disabled="!isCreatingNew">
                        <div class="flex justify-between items-center bg-blue-50 text-blue-800 p-3 rounded-lg border border-blue-100 mb-4">
                            <div class="flex items-center">
                                <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i>
                                <span class="font-medium">Nouveau client final</span>
                            </div>
                            <button type="button" @click="cancelCreatingNew()" class="text-sm font-medium hover:underline">Annuler</button>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Prénom *</label>
                                <input type="text" name="first_name" value="{{ old('first_name') }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('first_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Nom *</label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('last_name')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('email')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Téléphone</label>
                                <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('phone')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-5">
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Type document</label>
                                <select name="id_document_type" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                    <option value="">Sélectionner...</option>
                                    <option value="CNI" {{ old('id_document_type') == 'CNI' ? 'selected' : '' }}>CNI</option>
                                    <option value="Passeport" {{ old('id_document_type') == 'Passeport' ? 'selected' : '' }}>Passeport</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-primary/50 mb-1.5">Numéro document</label>
                                <input type="text" name="id_document_number" value="{{ old('id_document_number') }}" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary" x-bind:disabled="!isCreatingNew">
                                @error('id_document_number')<p class="text-xs text-red-500 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </x-customer-search>
                </div>

                <button type="submit" x-show="showCustomer" style="display: none;" class="w-full py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors flex items-center justify-center gap-2 mt-4">
                    Continuer la réservation
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    @endif

</div>

@endsection