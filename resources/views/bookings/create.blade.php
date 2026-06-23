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
            <form method="POST" action="{{ route('bookings.store') }}" x-data="bookingCalendar('{{ old('check_in', request('check_in')) }}', '{{ old('check_out', request('check_out')) }}')">
                @csrf
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                @if($booker)
                    <input type="hidden" name="booker_id" value="{{ $booker->id }}">
                @endif

                {{-- Hidden Inputs for Laravel validation & submission --}}
                <input type="hidden" name="check_in" :value="checkInDate ? formatDbDate(checkInDate) : ''" required>
                <input type="hidden" name="check_out" :value="checkOutDate ? formatDbDate(checkOutDate) : ''" required>

                {{-- Visual range indicators --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Arrivée *
                        </label>
                        <div class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-gray-50 text-primary flex items-center justify-between h-[38px] cursor-default">
                            <span x-text="formatDisplayDate(checkInDate)" :class="!checkInDate ? 'text-primary/30' : ''"></span>
                            <i data-lucide="calendar" class="w-4 h-4 text-primary/30"></i>
                        </div>
                        @error('check_in')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Départ *
                        </label>
                        <div class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg bg-gray-50 text-primary flex items-center justify-between h-[38px] cursor-default">
                            <span x-text="formatDisplayDate(checkOutDate)" :class="!checkOutDate ? 'text-primary/30' : ''"></span>
                            <i data-lucide="calendar" class="w-4 h-4 text-primary/30"></i>
                        </div>
                        @error('check_out')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Light Range Calendar Widget --}}
                <div class="bg-white border border-secondary/20 rounded-xl p-4 mb-4 select-none">
                    {{-- Calendar Header --}}
                    <div class="flex items-center justify-between mb-4 px-2">
                        <button type="button" @click="prevMonth()" class="p-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors focus:outline-none">
                            <i data-lucide="chevron-left" class="w-4 h-4"></i>
                        </button>
                        <h3 class="font-heading font-bold text-slate-800 text-sm" x-text="monthLabel"></h3>
                        <button type="button" @click="nextMonth()" class="p-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors focus:outline-none">
                            <i data-lucide="chevron-right" class="w-4 h-4"></i>
                        </button>
                    </div>

                    {{-- Calendar Weekdays --}}
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        <template x-for="dayName in ['L', 'M', 'M', 'J', 'V', 'S', 'D']">
                            <span class="text-[10px] font-bold text-slate-500 uppercase py-1" x-text="dayName"></span>
                        </template>
                    </div>

                    {{-- Calendar Days Grid --}}
                    <div class="grid grid-cols-7 gap-y-1 text-center">
                        <template x-for="day in gridDays" :key="day.date.getTime()">
                            <div class="relative py-0.5 flex items-center justify-center w-full"
                                 :class="{
                                     'bg-secondary/20 rounded-l-full': isInRange(day.date) && (day.date.getDay() === 1),
                                     'bg-secondary/20 rounded-r-full': isInRange(day.date) && (day.date.getDay() === 0),
                                     'bg-secondary/20': isInRange(day.date) && day.date.getDay() !== 1 && day.date.getDay() !== 0,
                                     'bg-gradient-to-r from-transparent to-secondary/20 rounded-l-full': isStart(day.date) && checkOutDate,
                                     'bg-gradient-to-l from-transparent to-secondary/20 rounded-r-full': isEnd(day.date)
                                 }">
                                <button type="button"
                                        @click="selectDay(day)"
                                        @mouseenter="if(checkInDate && !checkOutDate) hoverDate = day.date"
                                        :disabled="day.isDisabled"
                                        class="w-8 h-8 flex flex-col items-center justify-center text-xs font-semibold rounded-full transition-all relative focus:outline-none"
                                        :class="{
                                            'bg-primary text-white font-bold shadow-sm z-10': isStart(day.date) || isEnd(day.date),
                                            'text-primary font-semibold': isInRange(day.date) && !isStart(day.date) && !isEnd(day.date),
                                            'text-slate-800 hover:bg-slate-200': day.isCurrentMonth && !day.isDisabled && !isStart(day.date) && !isEnd(day.date) && !isInRange(day.date),
                                            'text-slate-400 hover:bg-slate-100': !day.isCurrentMonth && !day.isDisabled && !isStart(day.date) && !isEnd(day.date) && !isInRange(day.date),
                                            'text-slate-300 cursor-not-allowed opacity-40': day.isDisabled
                                        }">
                                    <span x-text="day.dayNum"></span>
                                    <span x-show="isStart(day.date) || isEnd(day.date)" class="w-1 h-1 bg-white rounded-full absolute bottom-1"></span>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Adultes *
                        </label>
                        <input type="number" name="adults" value="{{ old('adults', request('adults', 1)) }}"
                               min="1" required
                               class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                            Enfants
                        </label>
                        <input type="number" name="children" value="{{ old('children', request('children', 0)) }}"
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

                <div class="flex gap-3">
                    <a href="{{ route('bookings.create') }}"
                       class="flex-1 py-2.5 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Précédent
                    </a>
                    <button type="submit"
                            class="flex-1 py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors flex items-center justify-center gap-2">
                        Voir les chambres disponibles
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
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

                <div class="flex gap-3 mt-4" x-show="showCustomer" style="display: none;">
                    <button type="button" x-show="isBooker === 'other'" @click="showCustomer = false" class="flex-1 py-2.5 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Précédent
                    </button>
                    <button type="submit" :class="isBooker === 'other' ? 'flex-1' : 'w-full'" class="py-2.5 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors flex items-center justify-center gap-2">
                        Continuer la réservation
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>
        </div>
    @endif

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bookingCalendar', (initialCheckIn = '', initialCheckOut = '') => {
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        return {
            today,
            currentMonth: today.getMonth(),
            currentYear: today.getFullYear(),
            checkInDate: initialCheckIn ? new Date(initialCheckIn) : null,
            checkOutDate: initialCheckOut ? new Date(initialCheckOut) : null,
            hoverDate: null,
            monthNames: [
                'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
            ],
            
            init() {
                if (this.checkInDate) {
                    this.currentMonth = this.checkInDate.getMonth();
                    this.currentYear = this.checkInDate.getFullYear();
                }
            },

            get monthLabel() {
                return this.monthNames[this.currentMonth] + ' ' + this.currentYear;
            },

            prevMonth() {
                if (this.currentMonth === 0) {
                    this.currentMonth = 11;
                    this.currentYear--;
                } else {
                    this.currentMonth--;
                }
            },

            nextMonth() {
                if (this.currentMonth === 11) {
                    this.currentMonth = 0;
                    this.currentYear++;
                } else {
                    this.currentMonth++;
                }
            },

            get gridDays() {
                const days = [];
                const firstDay = new Date(this.currentYear, this.currentMonth, 1);
                
                let startDayOfWeek = firstDay.getDay(); // 0 = Sunday, 1 = Monday
                if (startDayOfWeek === 0) startDayOfWeek = 7;
                const paddingDaysCount = startDayOfWeek - 1;

                const prevMonthYear = this.currentMonth === 0 ? this.currentYear - 1 : this.currentYear;
                const prevMonth = this.currentMonth === 0 ? 11 : this.currentMonth - 1;
                const daysInPrevMonth = new Date(prevMonthYear, prevMonth + 1, 0).getDate();

                for (let i = paddingDaysCount - 1; i >= 0; i--) {
                    const d = new Date(prevMonthYear, prevMonth, daysInPrevMonth - i);
                    days.push({
                        date: d,
                        dayNum: d.getDate(),
                        isCurrentMonth: false,
                        isDisabled: d < this.today
                    });
                }

                const daysInMonth = new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
                for (let i = 1; i <= daysInMonth; i++) {
                    const d = new Date(this.currentYear, this.currentMonth, i);
                    days.push({
                        date: d,
                        dayNum: i,
                        isCurrentMonth: true,
                        isDisabled: d < this.today
                    });
                }

                const nextMonthYear = this.currentMonth === 11 ? this.currentYear + 1 : this.currentYear;
                const nextMonth = this.currentMonth === 11 ? 0 : this.currentMonth + 1;
                const remaining = 42 - days.length;
                for (let i = 1; i <= remaining; i++) {
                    const d = new Date(nextMonthYear, nextMonth, i);
                    days.push({
                        date: d,
                        dayNum: i,
                        isCurrentMonth: false,
                        isDisabled: d < this.today
                    });
                }

                return days;
            },

            selectDay(day) {
                if (day.isDisabled) return;
                const clickedDate = day.date;

                if (!this.checkInDate || (this.checkInDate && this.checkOutDate)) {
                    this.checkInDate = clickedDate;
                    this.checkOutDate = null;
                } else if (this.checkInDate && !this.checkOutDate) {
                    if (clickedDate < this.checkInDate) {
                        this.checkInDate = clickedDate;
                    } else if (clickedDate.getTime() === this.checkInDate.getTime()) {
                        // Clicked same date: do nothing or keep it
                    } else {
                        this.checkOutDate = clickedDate;
                    }
                }
            },

            isStart(date) {
                return this.checkInDate && date.getTime() === this.checkInDate.getTime();
            },

            isEnd(date) {
                return this.checkOutDate && date.getTime() === this.checkOutDate.getTime();
            },

            isInRange(date) {
                if (this.checkInDate && this.checkOutDate) {
                    return date > this.checkInDate && date < this.checkOutDate;
                }
                if (this.checkInDate && !this.checkOutDate && this.hoverDate) {
                    return date > this.checkInDate && date <= this.hoverDate;
                }
                return false;
            },

            formatDbDate(date) {
                if (!date) return '';
                const y = date.getFullYear();
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const d = String(date.getDate()).padStart(2, '0');
                return `${y}-${m}-${d}`;
            },

            formatDisplayDate(date) {
                if (!date) return 'Sélectionner...';
                const d = String(date.getDate()).padStart(2, '0');
                const m = String(date.getMonth() + 1).padStart(2, '0');
                const y = date.getFullYear();
                return `${d}/${m}/${y}`;
            }
        };
    });
});
</script>
@endpush

@endsection