@extends('layouts.hotel')

@section('title', 'Service Petits-déjeuners')

@section('content')
<div class="space-y-6" x-data="breakfastManager(@js($adultPrice), @js($ageBrackets))">

    {{-- En-tête de page & Sélection de date --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold">
                    <i data-lucide="coffee" class="w-5 h-5"></i>
                </span>
                <h1 class="font-heading text-2xl font-semibold text-primary">Service Petits-déjeuners</h1>
            </div>
            <p class="text-sm text-primary/50 mt-1">
                Pointage des résidents, contrôle des droits inclus et facturation des extras en direct ou sur chambre.
            </p>
        </div>

        <div class="flex items-center gap-3">
            {{-- Horaires de service --}}
            <div class="hidden md:flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-800 border border-amber-200 text-xs font-medium">
                <i data-lucide="clock" class="w-3.5 h-3.5 text-amber-600"></i>
                <span>Service : {{ $serviceHours['start'] }} – {{ $serviceHours['end'] }}</span>
            </div>

            {{-- Date sélecteur --}}
            <form method="GET" action="{{ route('restaurant.breakfast.index') }}" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                       class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-secondary/30 bg-white text-primary outline-none focus:border-primary shadow-xs">
                @if($date !== now()->toDateString())
                    <a href="{{ route('restaurant.breakfast.index') }}"
                       class="px-2.5 py-1.5 text-xs font-medium rounded-lg bg-slate-100 hover:bg-slate-200 text-primary transition">
                        Aujourd'hui
                    </a>
                @endif
            </form>
        </div>
    </div>

    {{-- Cartes de synthèse / KPIs du jour --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
        <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-primary/50">Attendus</span>
                <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="users" class="w-4 h-4"></i>
                </span>
            </div>
            <p class="text-2xl font-bold font-heading text-primary mt-2 tabular-nums">
                {{ $stats['total_expected'] }}
            </p>
            <p class="text-[11px] text-primary/50 mt-0.5">
                {{ $stats['total_expected_adults'] }} adulte(s), {{ $stats['total_expected_children'] }} enfant(s)
            </p>
        </div>

        <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Servis</span>
                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i data-lucide="check-circle" class="w-4 h-4"></i>
                </span>
            </div>
            <p class="text-2xl font-bold font-heading text-emerald-700 mt-2 tabular-nums">
                {{ $stats['total_served'] }}
            </p>
            <p class="text-[11px] text-emerald-600/70 mt-0.5">
                {{ $stats['total_served_adults'] }} adulte(s), {{ $stats['total_served_children'] }} enfant(s)
            </p>
        </div>

        <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">En attente</span>
                <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i data-lucide="hourglass" class="w-4 h-4"></i>
                </span>
            </div>
            <p class="text-2xl font-bold font-heading text-amber-700 mt-2 tabular-nums">
                {{ $stats['total_pending'] }}
            </p>
            <p class="text-[11px] text-amber-600/70 mt-0.5">
                Personnes à accueillir ce matin
            </p>
        </div>

        <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-xs">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold uppercase tracking-wider text-primary/50">Chambres</span>
                <span class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                    <i data-lucide="door-closed" class="w-4 h-4"></i>
                </span>
            </div>
            <p class="text-2xl font-bold font-heading text-primary mt-2 tabular-nums">
                {{ $stats['rooms_served_count'] }} <span class="text-sm font-normal text-primary/40">/ {{ $stats['rooms_count'] }}</span>
            </p>
            <p class="text-[11px] text-primary/50 mt-0.5">
                {{ $stats['rooms_count'] > 0 ? round(($stats['rooms_served_count'] / $stats['rooms_count']) * 100) : 0 }}% de progression
            </p>
        </div>
    </div>

    {{-- Filtres & Recherche --}}
    <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        {{-- Onglets de filtrage rapide --}}
        <div class="flex items-center gap-1.5 w-full sm:w-auto">
            <a href="{{ route('restaurant.breakfast.index', ['date' => $date, 'status' => 'all', 'q' => $search]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $statusFilter === 'all' ? 'bg-primary text-white' : 'bg-slate-100 text-primary/70 hover:bg-slate-200' }}">
                Toutes ({{ $stats['rooms_count'] }})
            </a>
            <a href="{{ route('restaurant.breakfast.index', ['date' => $date, 'status' => 'pending', 'q' => $search]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $statusFilter === 'pending' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' }}">
                En attente ({{ $stats['rooms_count'] - $stats['rooms_served_count'] }})
            </a>
            <a href="{{ route('restaurant.breakfast.index', ['date' => $date, 'status' => 'consumed', 'q' => $search]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $statusFilter === 'consumed' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}">
                Servies ({{ $stats['rooms_served_count'] }})
            </a>
        </div>

        {{-- Champ de recherche --}}
        <form method="GET" action="{{ route('restaurant.breakfast.index') }}" class="w-full sm:w-72 relative">
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="status" value="{{ $statusFilter }}">
            <input type="text" name="q" value="{{ $search }}" placeholder="N° de chambre ou nom client..."
                   class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-secondary/30 bg-white text-primary outline-none focus:border-primary">
            <i data-lucide="search" class="w-3.5 h-3.5 text-primary/40 absolute left-2.5 top-2.5"></i>
        </form>
    </div>

    {{-- Liste des chambres (Breakfast List) --}}
    @if($entitlements->isEmpty())
        <div class="bg-white rounded-xl border border-secondary/20 p-12 text-center text-primary/40">
            <i data-lucide="coffee" class="w-10 h-10 mx-auto mb-2 text-primary/20"></i>
            <p class="text-sm font-medium">Aucun petit-déjeuner trouvé pour ces critères.</p>
            <p class="text-xs text-primary/30 mt-1">Sélectionnez une autre date ou réinitialisez les filtres.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($entitlements as $e)
                @php
                    $booking = $e->booking;
                    $room = $e->room;
                    $customer = $booking?->customer;
                    $childrenCount = (int) ($booking?->children_count ?? 0);
                    $childrenSummary = !empty($booking?->children_ages)
                        ? app(\App\Services\BreakfastPricingService::class)->formatChildrenSummary($booking->children_ages)
                        : '';
                    $isConsumed = $e->isConsumed();
                @endphp

                <div class="bg-white rounded-2xl border transition-all duration-200 overflow-hidden flex flex-col justify-between shadow-xs {{ $isConsumed ? 'border-secondary/20 opacity-80 bg-slate-50/50' : 'border-secondary/30 hover:border-primary/40 hover:shadow-md' }}">
                    <div>
                        {{-- En-tête de la carte : Chambre + Statut --}}
                        <div class="p-4 border-b border-secondary/15 flex items-center justify-between {{ $isConsumed ? 'bg-slate-100/60' : 'bg-amber-50/30' }}">
                            <div class="flex items-center gap-2.5">
                                <span class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-sm {{ $isConsumed ? 'bg-slate-200 text-slate-700' : 'bg-primary text-white shadow-xs' }}">
                                    {{ $room?->number ?? '—' }}
                                </span>
                                <div>
                                    <h3 class="font-heading font-semibold text-primary text-sm">
                                        Chambre {{ $room?->number }}
                                    </h3>
                                    <p class="text-[11px] text-primary/50">
                                        {{ $room?->roomType?->name ?? 'Standard' }}
                                    </p>
                                </div>
                            </div>

                            @if($isConsumed)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                    <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                    Servi
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-800 border border-amber-200 animate-pulse">
                                    <i data-lucide="clock" class="w-3 h-3"></i>
                                    En attente
                                </span>
                            @endif
                        </div>

                        {{-- Corps : Client & Droits inclus --}}
                        <div class="p-4 space-y-3">
                            <div>
                                <p class="text-xs font-semibold text-primary flex items-center gap-1.5">
                                    <i data-lucide="user" class="w-3.5 h-3.5 text-primary/40"></i>
                                    {{ $customer?->full_name ?? 'Client' }}
                                </p>
                                <p class="text-[11px] text-primary/50 ml-5">
                                    Séjour du {{ \Carbon\Carbon::parse($booking?->check_in)->format('d/m') }} au {{ \Carbon\Carbon::parse($booking?->check_out)->format('d/m/Y') }}
                                </p>
                            </div>

                            {{-- Quota inclus --}}
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-secondary/15 space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-primary/70 font-medium">Inclus dans la chambre :</span>
                                    <span class="font-bold text-primary tabular-nums">
                                        {{ $e->adults_included }} adulte{{ $e->adults_included > 1 ? 's' : '' }}
                                        @if($e->children_included > 0)
                                            + {{ $e->children_included }} enfant{{ $e->children_included > 1 ? 's' : '' }} (prépayé)
                                        @endif
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] text-primary/50">
                                    <span>Occupants réels :</span>
                                    <span>
                                        {{ $booking?->adults_count ?? 1 }} adulte(s)
                                        @if($childrenCount > 0)
                                            , {{ $childrenCount }} enfant(s) {{ $childrenSummary ? '(' . $childrenSummary . ')' : '' }}
                                        @endif
                                    </span>
                                </div>
                            </div>

                            {{-- Détail du pointage effectué si déjà consommé --}}
                            @if($isConsumed)
                                <div class="text-[11px] text-emerald-800 bg-emerald-50/70 border border-emerald-200/60 rounded-lg p-2 flex items-start gap-1.5">
                                    <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 text-emerald-600 shrink-0"></i>
                                    <div>
                                        <p class="font-semibold">Servi : {{ $e->adults_consumed }} adulte(s), {{ $e->children_consumed }} enfant(s)</p>
                                        <p class="text-[10px] text-emerald-700/80">
                                            Pointé à {{ $e->consumed_at ? $e->consumed_at->format('H:i') : '—' }}
                                            {{ $e->server ? 'par ' . $e->server->name : '' }}
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Pied de carte : Action de pointage --}}
                    <div class="p-4 pt-0">
                        @if(!$isConsumed)
                            <button type="button"
                                    @click="openServeModal(@js($e), @js($booking), @js($room), @js($customer))"
                                    class="w-full py-2.5 bg-primary hover:bg-surface-dark text-white text-xs font-semibold rounded-xl transition flex items-center justify-center gap-1.5 shadow-xs">
                                <i data-lucide="check-circle" class="w-4 h-4"></i>
                                <span>Pointer le service</span>
                            </button>
                        @else
                            <button type="button"
                                    @click="openServeModal(@js($e), @js($booking), @js($room), @js($customer))"
                                    class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-primary/70 text-xs font-medium rounded-xl transition flex items-center justify-center gap-1.5">
                                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                                <span>Re-pointer / Corriger</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Modal interactif de pointage rapide (Inclus vs Extra) --}}
    <div x-show="showModal" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        
        <div @click.away="showModal = false"
             class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-secondary/20 animate-scale-up">
            
            <header class="px-6 py-4 border-b border-secondary/15 flex items-center justify-between bg-slate-50">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-primary text-white font-bold text-xs flex items-center justify-center">
                        <span x-text="currentRoom ? currentRoom.number : '—'"></span>
                    </span>
                    <div>
                        <h3 class="font-heading font-semibold text-primary text-sm">
                            Pointage Petit-déjeuner — Ch. <span x-text="currentRoom ? currentRoom.number : ''"></span>
                        </h3>
                        <p class="text-[11px] text-primary/50" x-text="currentCustomer ? currentCustomer.full_name : ''"></p>
                    </div>
                </div>
                <button type="button" @click="showModal = false" class="text-primary/40 hover:text-primary">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </header>

            <form :action="serveUrl" method="POST" class="p-6 space-y-5">
                @csrf

                {{-- Rappel des droits inclus --}}
                <div class="p-3 bg-amber-50/60 border border-amber-200/70 rounded-xl flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 text-amber-900">
                        <i data-lucide="coffee" class="w-4 h-4 text-amber-600"></i>
                        <span>Droits inclus pour cette chambre :</span>
                    </div>
                    <span class="font-bold text-amber-900 tabular-nums">
                        <span x-text="currentEntitlement ? currentEntitlement.adults_included : 0"></span> A
                        <span x-text="currentEntitlement && currentEntitlement.children_included > 0 ? (' + ' + currentEntitlement.children_included + ' E (prépayé)') : ''"></span>
                    </span>
                </div>

                {{-- Saisie des personnes servies --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-primary mb-1">Adultes servis *</label>
                        <div class="flex items-center gap-1.5">
                            <input type="number" name="adults_served" x-model.number="adultsServed"
                                   min="0" max="10" required
                                   class="w-full px-3 py-2 text-base font-bold text-center border border-secondary/30 rounded-xl outline-none focus:border-primary tabular-nums">
                            <div class="flex flex-col gap-1">
                                <button type="button" @click="adultsServed++" class="w-7 h-5 bg-slate-100 hover:bg-slate-200 rounded text-xs font-bold leading-none">+</button>
                                <button type="button" @click="if(adultsServed > 0) adultsServed--" class="w-7 h-5 bg-slate-100 hover:bg-slate-200 rounded text-xs font-bold leading-none">−</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-primary mb-1">Enfants servis *</label>
                        <div class="flex items-center gap-1.5">
                            <input type="number" name="children_served" x-model.number="childrenServed"
                                   min="0" max="10" required
                                   class="w-full px-3 py-2 text-base font-bold text-center border border-secondary/30 rounded-xl outline-none focus:border-primary tabular-nums">
                            <div class="flex flex-col gap-1">
                                <button type="button" @click="childrenServed++" class="w-7 h-5 bg-slate-100 hover:bg-slate-200 rounded text-xs font-bold leading-none">+</button>
                                <button type="button" @click="if(childrenServed > 0) childrenServed--" class="w-7 h-5 bg-slate-100 hover:bg-slate-200 rounded text-xs font-bold leading-none">−</button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Décomposition instantanée Couvert vs Extra --}}
                <div class="p-4 bg-slate-50 border border-secondary/20 rounded-xl space-y-2 text-xs">
                    <div class="flex items-center justify-between text-primary/70">
                        <span>Couverts par la chambre :</span>
                        <span class="font-semibold text-emerald-700 tabular-nums">
                            <span x-text="coveredAdults"></span> adulte(s), <span x-text="coveredChildren"></span> enfant(s) (0 FCFA)
                        </span>
                    </div>

                    <div class="flex items-center justify-between font-semibold pt-2 border-t border-secondary/15"
                         :class="extraAmount > 0 ? 'text-amber-800' : 'text-primary/50'">
                        <span>Surplus / Extras :</span>
                        <span class="tabular-nums" x-text="extraAmount > 0 ? (new Intl.NumberFormat('fr-FR').format(extraAmount) + ' FCFA (' + extraAdults + 'A, ' + extraChildren + 'E)') : 'Aucun surplus'"></span>
                    </div>
                </div>

                {{-- Choix du mode de règlement si Extra --}}
                <div x-show="extraAmount > 0" class="space-y-2 pt-2 border-t border-secondary/15">
                    <label class="block text-xs font-bold text-primary">Règlement du surplus (<span x-text="new Intl.NumberFormat('fr-FR').format(extraAmount) + ' FCFA'"></span>) :</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition text-xs"
                               :class="settlementMethod === 'room_charge' ? 'border-primary bg-primary/5 font-semibold text-primary' : 'border-secondary/25 hover:bg-slate-50 text-primary/70'">
                            <input type="radio" name="settlement_method" value="room_charge" x-model="settlementMethod" class="sr-only">
                            <i data-lucide="bed" class="w-4 h-4"></i>
                            <span>Débiter sur la chambre</span>
                        </label>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition text-xs"
                               :class="settlementMethod === 'cash' ? 'border-primary bg-primary/5 font-semibold text-primary' : 'border-secondary/25 hover:bg-slate-50 text-primary/70'">
                            <input type="radio" name="settlement_method" value="cash" x-model="settlementMethod" class="sr-only">
                            <i data-lucide="banknote" class="w-4 h-4"></i>
                            <span>Paiement restaurant</span>
                        </label>
                    </div>
                </div>

                {{-- Notes éventuelles --}}
                <div>
                    <label class="block text-[11px] text-primary/60 mb-1">Notes staff (optionnel)</label>
                    <input type="text" name="notes" placeholder="Ex: Invité supplémentaire, table terrasse..."
                           class="w-full px-3 py-1.5 text-xs rounded-lg border border-secondary/25 outline-none focus:border-primary">
                </div>

                {{-- Boutons d'action --}}
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-secondary/15">
                    <button type="button" @click="showModal = false"
                            class="px-4 py-2 text-xs font-semibold text-primary/60 hover:text-primary transition">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 bg-primary hover:bg-surface-dark text-white text-xs font-semibold rounded-xl transition flex items-center gap-2 shadow-xs">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span x-text="extraAmount > 0 ? ('Valider le service (' + new Intl.NumberFormat('fr-FR').format(extraAmount) + ' FCFA)') : 'Valider le service'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('breakfastManager', (adultPrice, ageBrackets) => ({
        adultPrice: parseInt(adultPrice) || 5000,
        ageBrackets: ageBrackets || [],
        showModal: false,
        currentEntitlement: null,
        currentBooking: null,
        currentRoom: null,
        currentCustomer: null,
        serveUrl: '',

        adultsServed: 1,
        childrenServed: 0,
        settlementMethod: 'room_charge',

        openServeModal(entitlement, booking, room, customer) {
            this.currentEntitlement = entitlement;
            this.currentBooking = booking;
            this.currentRoom = room;
            this.currentCustomer = customer;
            this.serveUrl = `/restaurant/breakfast/${entitlement.id}/serve`;

            // Pré-remplir les valeurs : si déjà consommé, reprendre ce qui a été servi, sinon reprendre les inclus
            if (entitlement.status === 'consumed') {
                this.adultsServed = entitlement.adults_consumed;
                this.childrenServed = entitlement.children_consumed;
            } else {
                this.adultsServed = entitlement.adults_included;
                this.childrenServed = entitlement.children_included;
            }

            this.settlementMethod = 'room_charge';
            this.showModal = true;

            this.$nextTick(() => {
                if (window.lucide) window.lucide.createIcons();
            });
        },

        get coveredAdults() {
            if (!this.currentEntitlement) return 0;
            return Math.min(this.adultsServed, this.currentEntitlement.adults_included);
        },

        get extraAdults() {
            if (!this.currentEntitlement) return 0;
            return Math.max(0, this.adultsServed - this.currentEntitlement.adults_included);
        },

        get coveredChildren() {
            if (!this.currentEntitlement) return 0;
            return Math.min(this.childrenServed, this.currentEntitlement.children_included);
        },

        get extraChildren() {
            if (!this.currentEntitlement) return 0;
            return Math.max(0, this.childrenServed - this.currentEntitlement.children_included);
        },

        get extraAmount() {
            let total = this.extraAdults * this.adultPrice;

            if (this.extraChildren > 0) {
                const bookingChildrenAges = (this.currentBooking && this.currentBooking.children_ages) ? this.currentBooking.children_ages : [];
                const bracketMap = {};
                this.ageBrackets.forEach(b => { bracketMap[b.id] = b.price; });

                for (let k = 0; k < this.extraChildren; k++) {
                    const bracketId = bookingChildrenAges[this.coveredChildren + k] || null;
                    const childPrice = (bracketId && bracketMap[bracketId] !== undefined)
                        ? bracketMap[bracketId]
                        : (this.ageBrackets[1]?.price || 2500);
                    total += childPrice;
                }
            }

            return total;
        }
    }));
});
</script>
@endpush
@endsection
