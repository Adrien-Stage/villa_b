@extends('layouts.pos')

@section('title', 'Mode POS Réception')

@section('content')
<div class="max-w-7xl mx-auto px-3 sm:px-6 py-4" x-data="receptionPosApp(@js($serviceItems), @js($inHouseBookings), {{ $activeSession ? 'false' : 'true' }})">
    
    {{-- Top Section: En-tête POS & Boîte Permanente de Caisse --}}
    <div class="mb-5 grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
        
        {{-- Bannière POS & Navigation --}}
        <div class="lg:col-span-7 bg-white rounded-2xl p-4 sm:p-5 border border-secondary/15 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <i data-lucide="store" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h1 class="text-xl sm:text-2xl font-heading font-bold text-primary flex items-center gap-2">
                            Terminal POS Réception
                        </h1>
                        <p class="text-xs text-secondary mt-0.5">Vente rapide, encaissement au comptoir et débit sur folio en un seul écran</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('reception.pos.history') }}" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-primary bg-secondary/10 hover:bg-secondary/20 rounded-xl transition-all">
                        <i data-lucide="history" class="w-4 h-4"></i>
                        <span class="hidden sm:inline">Historique des ventes</span>
                    </a>
                </div>
            </div>

            <div class="mt-4 pt-3 border-t border-secondary/10 flex flex-wrap items-center justify-between gap-2 text-xs text-primary/70">
                <div class="flex items-center gap-3">
                    <span class="font-medium text-primary flex items-center gap-1">
                        <i data-lucide="user" class="w-3.5 h-3.5 text-secondary"></i>
                        Opérateur : {{ auth()->user()->name }}
                    </span>
                    <span class="text-secondary/40">•</span>
                    <span>{{ $inHouseBookings->count() }} résidents en chambre</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-accent/40 font-medium text-primary">TVA 19,25% comprise</span>
                </div>
            </div>
        </div>

        {{-- BOÎTE PERMANENTE DE CAISSE (Toujours visible à l'écran) --}}
        <div class="lg:col-span-5 bg-white rounded-2xl p-4 sm:p-5 border border-secondary/15 shadow-xs flex flex-col justify-between relative overflow-hidden">
            @if($activeSession)
                @php
                    $posEstTitulaire = (bool) ($cashRegisterContext['estTitulaire'] ?? ((int) $activeSession->user_id === (int) auth()->id()));
                    $posTitulaire = $cashRegisterContext['titulaire'] ?? $activeSession->user;
                    $posTitulaireRole = $cashRegisterContext['titulaireRoleLabel'] ?? null;
                    $posTiers = $cashRegisterContext['tiers'] ?? ['has_activity' => false];
                @endphp
                {{-- Ligne d'état verte si caisse ouverte --}}
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-green-500"></div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5" aria-hidden="true">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                            </span>
                            <span class="text-xs font-bold uppercase tracking-wider text-green-700">Caisse Réception Ouverte</span>
                        </div>
                        <span class="text-[11px] font-medium text-primary/50">
                            Session #{{ $activeSession->id }} • {{ $activeSession->opened_at ? $activeSession->opened_at->format('H:i') : '—' }}
                        </span>
                    </div>

                    @if(!$posEstTitulaire && $posTitulaire)
                        <div role="status" class="mb-2.5 rounded-xl border border-emerald-200 bg-emerald-50/90 px-3 py-2 text-[11px] text-emerald-950">
                            <span class="font-semibold">
                                Caisse ouverte par {{ $posTitulaire->name }}@if($posTitulaireRole) ({{ $posTitulaireRole }})@endif
                            </span>
                            <span class="block text-emerald-800">
                                Vous travaillez dans cette caisse : vos ventes et sorties y sont rattachées et signalées au titulaire.
                            </span>
                        </div>
                    @elseif($posEstTitulaire && ($posTiers['has_activity'] ?? false))
                        <div role="status" class="mb-2.5 rounded-xl border border-indigo-200 bg-indigo-50/90 px-3 py-2 text-[11px] text-indigo-950">
                            <span class="font-semibold">Activité tiers :</span>
                            {{ $posTiers['actors']->map(fn ($a) => $a['name'] . ' (' . $a['count'] . ' op.)')->join(', ') }}
                            <details class="mt-1">
                                <summary class="cursor-pointer select-none font-semibold text-indigo-800 hover:text-indigo-950">
                                    Voir le détail ({{ $posTiers['total_count'] }})
                                </summary>
                                <ul class="mt-1 space-y-0.5 text-[10px] text-primary/80">
                                    @foreach($posTiers['items'] as $item)
                                        <li>
                                            {{ $item['occurred_at']?->format('H:i') ?? '—' }} · <strong>{{ $item['actor_name'] }}</strong> : {{ $item['description'] }}
                                            @if($item['is_financial'] && $item['amount'])
                                                ({{ $item['cash_impact'] < 0 ? '−' : '+' }}{{ number_format(abs((int) $item['amount']) / 100, 0, ',', ' ') }} FCFA)
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-2 bg-accent/20 p-2.5 rounded-xl mb-3">
                        <div>
                            <span class="text-[10px] font-semibold text-primary/50 uppercase tracking-wider block">Fond initial</span>
                            <span class="text-xs sm:text-sm font-bold text-primary">{{ number_format($sessionStats['opening_amount'] / 100, 0, ',', ' ') }} FCFA</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-semibold text-primary/50 uppercase tracking-wider block">Solde Espèces Attendu</span>
                            <span class="text-sm sm:text-base font-bold text-green-700 font-heading">
                                {{ number_format($sessionStats['theoretical_cash'] / 100, 0, ',', ' ') }} FCFA
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Actions Caisse : Sortie (tout opérateur habilité) et Fermeture (titulaire uniquement) --}}
                <div class="flex items-center gap-2 pt-2 border-t border-secondary/10">
                    <button type="button" @click="showDisbursementModal = true" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 transition-colors">
                        <i data-lucide="minus-circle" class="w-3.5 h-3.5" aria-hidden="true"></i>
                        <span>Sortie de caisse</span>
                    </button>

                    @if($posEstTitulaire)
                        <a href="{{ route('bookings.cash_register.close') }}" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 transition-colors">
                            <i data-lucide="lock" class="w-3.5 h-3.5" aria-hidden="true"></i>
                            <span>Fermer la caisse</span>
                        </a>
                    @endif
                </div>

            @else
                {{-- Ligne d'état ambre si caisse fermée --}}
                <div class="absolute top-0 left-0 right-0 h-1.5 bg-amber-500"></div>

                <div class="flex flex-col justify-center h-full">
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-800">Caisse Réception Fermée</span>
                    </div>
                    <p class="text-xs text-primary/70 mb-3">
                        Ouvrez votre caisse de réception pour démarrer les encaissements en espèces et par carte pour la journée.
                    </p>
                    <button type="button" @click="showOpenCaisseModal = true" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-green-600 hover:bg-green-700 shadow-sm transition-colors">
                        <i data-lucide="lock-open" class="w-4 h-4"></i>
                        <span>Ouvrir la caisse de réception</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Messages Flash --}}
    @if(session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl flex items-center gap-2 text-sm">
            <i data-lucide="check-circle" class="w-5 h-5 text-green-600"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl text-sm">
            <div class="flex items-center gap-2 font-bold mb-1">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-red-600"></i>
                Veuillez corriger les erreurs suivantes :
            </div>
            <ul class="list-disc list-inside text-xs space-y-0.5 ml-7">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- FORMULAIRE PRINCIPAL DU TERMINAL POS --}}
    <form action="{{ route('reception.pos.sales.store') }}" method="POST" @submit="handleSubmit($event)">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            {{-- Colonne GAUCHE & CENTRE : Client/Chambre, Catalogue & Prestations --}}
            <div class="lg:col-span-7 space-y-5">
                
                {{-- 1. Sélection Client ou Chambre --}}
                <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-xs">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-secondary/10">
                        <div class="flex items-center gap-2 text-primary font-heading font-semibold text-base">
                            <i data-lucide="user-check" class="w-5 h-5 text-primary"></i>
                            <span>Bénéficiaire de la vente</span>
                        </div>
                        <input type="hidden" name="client_mode" :value="clientMode">
                        <div class="flex bg-accent/30 p-1 rounded-xl">
                            <button type="button"
                                    @click="setClientMode('room')"
                                    :class="clientMode === 'room' ? 'bg-primary text-white shadow-xs font-semibold' : 'text-primary/70 hover:text-primary font-medium'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                                <i data-lucide="bed" class="w-3.5 h-3.5"></i>
                                <span>Chambre (Résident)</span>
                            </button>
                            <button type="button"
                                    @click="setClientMode('customer')"
                                    :class="clientMode === 'customer' ? 'bg-primary text-white shadow-xs font-semibold' : 'text-primary/70 hover:text-primary font-medium'"
                                    class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                                <i data-lucide="user" class="w-3.5 h-3.5"></i>
                                <span>Client de passage</span>
                            </button>
                        </div>
                    </div>

                    {{-- Mode Chambre (Résident) --}}
                    <div x-show="clientMode === 'room'" x-transition>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-2">
                            Sélectionner une chambre actuellement occupée (séjour en cours) *
                        </label>
                        <div class="relative">
                            <select name="booking_id" x-model="selectedBookingId" @change="onBookingChange()"
                                    class="w-full px-3.5 py-2.5 text-sm border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all">
                                <option value="">-- Choisir une chambre / résident --</option>
                                @foreach($inHouseBookings as $b)
                                    <option value="{{ $b->id }}">
                                        Chambre {{ $b->room?->number ?? '—' }} — {{ $b->customer?->first_name }} {{ $b->customer?->last_name }} (Dossier #{{ $b->booking_number }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Fiche Résident sélectionné --}}
                        <div x-show="selectedBooking" x-transition class="mt-3 p-3.5 bg-blue-50/70 border border-blue-100 rounded-xl flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-blue-600 text-white flex items-center justify-center font-bold text-sm">
                                    <span x-text="selectedBooking?.room?.number || '—'"></span>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-blue-950">
                                        <span x-text="selectedBooking?.customer?.first_name + ' ' + selectedBooking?.customer?.last_name"></span>
                                    </p>
                                    <p class="text-[11px] text-blue-700/80">
                                        Chambre <span x-text="selectedBooking?.room?.number"></span> • Statut : Séjour en cours
                                    </p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-green-700 bg-green-100 px-2 py-0.5 rounded-full">
                                <i data-lucide="check" class="w-3 h-3"></i> Débit chambre éligible
                            </span>
                        </div>
                    </div>

                    {{-- Mode Client de passage --}}
                    <div x-show="clientMode === 'customer'" x-transition class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1.5">Nom du client *</label>
                                <input type="text" name="customer_name" x-model="customerName"
                                       class="w-full px-3.5 py-2 text-sm border border-secondary/30 rounded-xl text-primary outline-none focus:border-primary transition-all"
                                       placeholder="Ex: Paul Biya, Client Walk-in...">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1.5">Téléphone (optionnel)</label>
                                <input type="text" name="customer_phone" x-model="customerPhone"
                                       class="w-full px-3.5 py-2 text-sm border border-secondary/30 rounded-xl text-primary outline-none focus:border-primary transition-all"
                                       placeholder="Ex: 699 00 00 00">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. Catalogue des prestations & Ajout au panier --}}
                <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-xs">
                    <div class="flex flex-wrap items-center justify-between gap-3 pb-3 mb-4 border-b border-secondary/10">
                        <div class="flex items-center gap-2 text-primary font-heading font-semibold text-base">
                            <i data-lucide="layers" class="w-5 h-5 text-primary"></i>
                            <span>Catalogue des prestations</span>
                        </div>

                        {{-- Bouton d'ajout libre --}}
                        <button type="button" @click="showCustomItemModal = true" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-primary bg-accent/40 hover:bg-accent/60 border border-secondary/20 transition-all">
                            <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                            <span>+ Prestation libre / Divers</span>
                        </button>
                    </div>

                    {{-- Filtres de catégories --}}
                    <div class="flex gap-1.5 overflow-x-auto pb-3 mb-3 scrollbar-thin">
                        <button type="button" @click="activeCategory = 'all'"
                                :class="activeCategory === 'all' ? 'bg-primary text-white' : 'bg-accent/30 text-primary/70 hover:bg-accent/50'"
                                class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all">
                            Tout afficher ({{ count($serviceItems) }})
                        </button>
                        <button type="button" @click="activeCategory = 'breakfast'"
                                :class="activeCategory === 'breakfast' ? 'bg-primary text-white' : 'bg-accent/30 text-primary/70 hover:bg-accent/50'"
                                class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all">
                            Petit-déjeuner
                        </button>
                        <button type="button" @click="activeCategory = 'laundry'"
                                :class="activeCategory === 'laundry' ? 'bg-primary text-white' : 'bg-accent/30 text-primary/70 hover:bg-accent/50'"
                                class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all">
                            Blanchisserie
                        </button>
                        <button type="button" @click="activeCategory = 'spa'"
                                :class="activeCategory === 'spa' ? 'bg-primary text-white' : 'bg-accent/30 text-primary/70 hover:bg-accent/50'"
                                class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all">
                            Spa & Bien-être
                        </button>
                        <button type="button" @click="activeCategory = 'activity'"
                                :class="activeCategory === 'activity' ? 'bg-primary text-white' : 'bg-accent/30 text-primary/70 hover:bg-accent/50'"
                                class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all">
                            Activités
                        </button>
                        <button type="button" @click="activeCategory = 'minibar'"
                                :class="activeCategory === 'minibar' ? 'bg-primary text-white' : 'bg-accent/30 text-primary/70 hover:bg-accent/50'"
                                class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition-all">
                            Minibar & Boissons
                        </button>
                    </div>

                    {{-- Grille des prestations --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-80 overflow-y-auto pr-1">
                        <template x-for="item in filteredServiceItems" :key="item.id">
                            <div @click="addItemFromCatalog(item)"
                                 class="p-3 rounded-xl border border-secondary/15 hover:border-primary/50 hover:bg-accent/15 cursor-pointer transition-all flex flex-col justify-between group">
                                <div>
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md bg-secondary/10 text-primary/70"
                                              x-text="getCategoryLabel(item.category)"></span>
                                        <span x-show="item.duration_minutes" class="text-[10px] text-primary/40" x-text="item.duration_minutes + ' min'"></span>
                                    </div>
                                    <p class="text-xs font-bold text-primary line-clamp-2 group-hover:text-primary transition-colors" x-text="item.name"></p>
                                </div>
                                <div class="mt-3 pt-2 border-t border-secondary/10 flex items-center justify-between">
                                    <span class="text-xs font-extrabold text-primary font-heading" x-text="formatPrice(item.price)"></span>
                                    <span class="w-6 h-6 rounded-lg bg-primary/10 group-hover:bg-primary group-hover:text-white flex items-center justify-center text-primary text-xs transition-colors">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    </span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>

            {{-- Colonne DROITE : Le Panier & Règlement --}}
            <div class="lg:col-span-5">
                <div class="bg-white rounded-2xl p-5 border border-secondary/15 shadow-sm sticky top-20">
                    
                    {{-- En-tête Panier --}}
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-secondary/10">
                        <div class="flex items-center gap-2 text-primary font-heading font-semibold text-base">
                            <i data-lucide="shopping-cart" class="w-5 h-5 text-primary"></i>
                            <span>Panier en cours</span>
                        </div>
                        <span class="text-xs font-bold bg-primary/10 text-primary px-2.5 py-0.5 rounded-full" x-text="cart.length + ' article(s)'"></span>
                    </div>

                    {{-- Contenu du panier --}}
                    <div class="space-y-2.5 mb-4 max-h-64 overflow-y-auto pr-1">
                        <template x-if="cart.length === 0">
                            <div class="py-8 text-center text-primary/40">
                                <i data-lucide="shopping-bag" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                <p class="text-xs font-medium">Votre panier est vide</p>
                                <p class="text-[11px] mt-0.5">Cliquez sur une prestation pour l'ajouter</p>
                            </div>
                        </template>

                        <template x-for="(line, index) in cart" :key="index">
                            <div class="p-2.5 bg-accent/20 rounded-xl border border-secondary/10 flex items-center justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-primary truncate" x-text="line.name"></p>
                                    <p class="text-[10px] text-primary/60" x-text="formatPrice(line.unit_price) + ' × ' + line.quantity"></p>
                                </div>

                                {{-- Sélecteur de quantité --}}
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="decrementQty(index)" class="w-6 h-6 rounded-md bg-white border border-secondary/20 hover:bg-secondary/10 text-primary flex items-center justify-center text-xs font-bold">
                                        -
                                    </button>
                                    <span class="w-6 text-center text-xs font-bold text-primary" x-text="line.quantity"></span>
                                    <button type="button" @click="incrementQty(index)" class="w-6 h-6 rounded-md bg-white border border-secondary/20 hover:bg-secondary/10 text-primary flex items-center justify-center text-xs font-bold">
                                        +
                                    </button>
                                </div>

                                <div class="text-right w-20">
                                    <p class="text-xs font-extrabold text-primary" x-text="formatPrice(line.unit_price * line.quantity)"></p>
                                </div>

                                <button type="button" @click="removeLine(index)" class="text-red-400 hover:text-red-600 p-1 transition-colors" title="Supprimer">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>

                                {{-- Champs cachés pour le formulaire --}}
                                <input type="hidden" :name="'items[' + index + '][service_item_id]'" :value="line.service_item_id || ''">
                                <input type="hidden" :name="'items[' + index + '][category]'" :value="line.category || 'other'">
                                <input type="hidden" :name="'items[' + index + '][name]'" :value="line.name">
                                <input type="hidden" :name="'items[' + index + '][quantity]'" :value="line.quantity">
                                <input type="hidden" :name="'items[' + index + '][unit_price]'" :value="line.unit_price / 100">
                            </div>
                        </template>
                    </div>

                    {{-- Totaux & Décomposition --}}
                    <div class="border-t border-secondary/10 pt-3 space-y-2 mb-4">
                        <div class="flex justify-between text-xs text-primary/70">
                            <span>Sous-total HT</span>
                            <span class="font-medium" x-text="formatPrice(subtotalHt)">0 FCFA</span>
                        </div>
                        <div class="flex justify-between text-xs text-primary/70">
                            <span>TVA (19,25% comprise)</span>
                            <span class="font-medium" x-text="formatPrice(taxAmount)">0 FCFA</span>
                        </div>
                        <div class="flex justify-between items-baseline pt-2 border-t-2 border-dashed border-secondary/15">
                            <span class="text-sm font-bold text-primary">TOTAL À ENCAISSER</span>
                            <span class="text-2xl font-black font-heading text-primary" x-text="formatPrice(totalTtc)">0 FCFA</span>
                        </div>
                    </div>

                    {{-- Mode de règlement --}}
                    <div class="mb-4">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1.5">
                            Mode de règlement *
                        </label>
                        <select name="payment_method" x-model="paymentMethod" required
                                class="w-full px-3 py-2 text-xs font-semibold border border-secondary/30 rounded-xl bg-white text-primary outline-none focus:border-primary transition-all">
                            <option value="cash" selected>💵 Espèces (Cash tiroir-caisse)</option>
                            <option value="room_charge" :disabled="clientMode !== 'room' || !selectedBookingId">
                                🛏️ Débiter sur la chambre (Folio séjour)
                            </option>
                            <option value="card">💳 Carte bancaire</option>
                            <option value="mobile_money">📱 Mobile Money (Orange / MTN)</option>
                            <option value="bank_transfer">🏦 Virement bancaire</option>
                            <option value="other">Autre moyen de paiement</option>
                        </select>

                        <div x-show="paymentMethod === 'room_charge'" x-transition class="mt-2 p-2.5 bg-blue-50 border border-blue-100 rounded-lg text-[11px] text-blue-900 flex items-start gap-2">
                            <i data-lucide="info" class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5"></i>
                            <span>Le montant sera inscrit sur la note du client et facturé lors du check-out final.</span>
                        </div>
                    </div>

                    {{-- Notes additionnelles --}}
                    <div class="mb-4">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1">
                            Notes / Précisions (optionnel)
                        </label>
                        <input type="text" name="notes" class="w-full px-3 py-1.5 text-xs border border-secondary/20 rounded-lg text-primary outline-none focus:border-primary transition-all" placeholder="Ex: Servi en terrasse, linge express...">
                    </div>

                    {{-- Bouton de validation --}}
                    <div>
                        <button type="submit"
                                :disabled="cart.length === 0"
                                :class="cart.length === 0 ? 'opacity-50 cursor-not-allowed bg-secondary/50 text-white' : (paymentMethod === 'room_charge' ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-md' : 'bg-green-600 hover:bg-green-700 text-white shadow-md')"
                                class="w-full py-3 px-4 rounded-xl font-heading font-bold text-sm flex items-center justify-center gap-2 transition-all">
                            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                            <span x-text="paymentMethod === 'room_charge' ? 'Valider et Débiter la chambre' : 'Encaisser ' + formatPrice(totalTtc)"></span>
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </form>

    @unless($activeSession)
    {{-- MODALE POPUP D'OUVERTURE DE CAISSE (Invitations au démarrage du service) --}}
    <div x-show="showOpenCaisseModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-2xl border border-secondary/20" @click.away="showOpenCaisseModal = false">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl bg-green-100 text-green-700 mx-auto flex items-center justify-center mb-3">
                    <i data-lucide="lock-open" class="w-8 h-8"></i>
                </div>
                <h3 class="text-2xl font-heading font-bold text-primary">Ouverture de Caisse Réception</h3>
                <p class="text-xs text-primary/70 mt-1">
                    Démarrage du service du jour • Déclarez le fond de caisse initial disponible dans le tiroir pour commencer les opérations.
                </p>
            </div>

            <form action="{{ route('bookings.cash_register.open.store') }}" method="POST">
                @csrf
                
                {{-- Suggestions de fond de caisse rapide --}}
                <div class="mb-4">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/50 mb-2">Montants usuels suggérés</label>
                    <div class="grid grid-cols-4 gap-2">
                        <button type="button" @click="openingAmountInput = 0" class="py-2 text-xs font-bold rounded-xl border border-secondary/20 hover:bg-accent/30 text-primary">0 FCFA</button>
                        <button type="button" @click="openingAmountInput = 25000" class="py-2 text-xs font-bold rounded-xl border border-secondary/20 hover:bg-accent/30 text-primary">25 000</button>
                        <button type="button" @click="openingAmountInput = 50000" class="py-2 text-xs font-bold rounded-xl border border-secondary/20 hover:bg-accent/30 text-primary">50 000</button>
                        <button type="button" @click="openingAmountInput = 100000" class="py-2 text-xs font-bold rounded-xl border border-secondary/20 hover:bg-accent/30 text-primary">100 000</button>
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1.5">Fond de caisse initial (FCFA) *</label>
                    <input type="number" name="opening_amount" x-model.number="openingAmountInput" required min="0" step="100"
                           class="w-full px-4 py-3.5 text-2xl font-black font-heading text-center border-2 border-secondary/30 rounded-2xl text-primary outline-none focus:border-green-600 focus:ring-2 focus:ring-green-100 transition-all">
                    <p class="text-[11px] text-primary/50 text-center mt-1.5">Comptez physiquement la monnaie présente dans le tiroir-caisse</p>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-end gap-2.5 pt-3 border-t border-secondary/10">
                    <a href="{{ route('dashboard') }}" class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-xs font-semibold text-primary/70 hover:text-primary transition-colors text-center">
                        Quitter le mode POS
                    </a>
                    <button type="button" @click="showOpenCaisseModal = false" class="w-full sm:w-auto px-4 py-2.5 rounded-xl text-xs font-semibold text-primary bg-secondary/10 hover:bg-secondary/20 transition-colors">
                        Continuer en consultation
                    </button>
                    <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-xl text-xs font-bold text-white bg-green-600 hover:bg-green-700 shadow-md transition-all">
                        Ouvrir la caisse et commencer
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endunless

    {{-- MODALE 2 : Prestation libre / Divers --}}
    <div x-show="showCustomItemModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-secondary/20" @click.away="showCustomItemModal = false">
            <h3 class="text-lg font-heading font-bold text-primary mb-4 flex items-center gap-2">
                <i data-lucide="plus-circle" class="w-5 h-5 text-primary"></i>
                Ajouter une prestation libre
            </h3>
            <div class="space-y-3 mb-5">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1">Désignation *</label>
                    <input type="text" x-model="customItem.name" placeholder="Ex: Navette aéroport, repassage..." class="w-full px-3.5 py-2 text-sm border border-secondary/30 rounded-xl">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1">Prix unitaire (FCFA) *</label>
                        <input type="number" x-model.number="customItem.price" min="0" step="100" class="w-full px-3.5 py-2 text-sm border border-secondary/30 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1">Catégorie</label>
                        <select x-model="customItem.category" class="w-full px-3.5 py-2 text-sm border border-secondary/30 rounded-xl bg-white">
                            <option value="other">Autre / Divers</option>
                            <option value="breakfast">Petit-déjeuner</option>
                            <option value="laundry">Blanchisserie</option>
                            <option value="spa">Spa</option>
                            <option value="activity">Activité</option>
                            <option value="minibar">Minibar</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-end gap-2">
                <button type="button" @click="showCustomItemModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-primary/70 hover:text-primary">Annuler</button>
                <button type="button" @click="addCustomItem()" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-primary hover:bg-surface-dark transition-all">Ajouter au panier</button>
            </div>
        </div>
    </div>

    {{-- MODALE 3 : Décaissement rapide (Sortie d'argent) --}}
    <div x-show="showDisbursementModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-secondary/20" @click.away="showDisbursementModal = false">
            <h3 class="text-lg font-heading font-bold text-primary mb-2 flex items-center gap-2">
                <i data-lucide="minus-circle" class="w-5 h-5 text-amber-600"></i>
                Enregistrer une sortie de caisse
            </h3>
            <p class="text-xs text-primary/60 mb-4">Cette somme sera immédiatement déduite du solde théorique de votre tiroir-caisse.</p>
            <form action="{{ route('bookings.cash_register.disbursements.store') }}" method="POST">
                @csrf
                <div class="space-y-3 mb-5">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1">Motif de la sortie *</label>
                        <input type="text" name="reason" required placeholder="Ex: Achat fournitures, taxi client..." class="w-full px-3.5 py-2 text-sm border border-secondary/30 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-primary/60 mb-1">Montant en espèces (FCFA) *</label>
                        <input type="number" name="amount" required min="1" step="1" placeholder="Ex: 5000" class="w-full px-3.5 py-2 text-sm border border-secondary/30 rounded-xl">
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showDisbursementModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-primary/70 hover:text-primary">Annuler</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 transition-all">Valider le décaissement</button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
function receptionPosApp(serviceItems, bookings, shouldPromptOpenCaisse) {
    return {
        serviceItems: serviceItems,
        bookings: bookings,
        clientMode: 'room',
        selectedBookingId: '',
        selectedBooking: null,
        customerName: '',
        customerPhone: '',
        activeCategory: 'all',
        paymentMethod: 'cash',
        cart: [],
        showCustomItemModal: false,
        showDisbursementModal: false,
        showOpenCaisseModal: shouldPromptOpenCaisse,
        openingAmountInput: 50000,
        customItem: { name: '', price: 0, category: 'other' },

        setClientMode(mode) {
            this.clientMode = mode;
            if (mode === 'customer' && this.paymentMethod === 'room_charge') {
                this.paymentMethod = 'cash';
            }
        },

        onBookingChange() {
            if (!this.selectedBookingId) {
                this.selectedBooking = null;
                return;
            }
            this.selectedBooking = this.bookings.find(b => b.id == this.selectedBookingId) || null;
            if (this.selectedBooking) {
                this.paymentMethod = 'room_charge'; // suggestion par défaut pour résident
            }
        },

        get filteredServiceItems() {
            if (this.activeCategory === 'all') return this.serviceItems;
            return this.serviceItems.filter(item => item.category === this.activeCategory);
        },

        addItemFromCatalog(item) {
            const existing = this.cart.find(line => line.service_item_id === item.id);
            if (existing) {
                existing.quantity++;
            } else {
                this.cart.push({
                    service_item_id: item.id,
                    name: item.name,
                    category: item.category,
                    unit_price: item.price, // en centimes
                    quantity: 1,
                });
            }
        },

        addCustomItem() {
            if (!this.customItem.name || this.customItem.price <= 0) {
                alert('Veuillez renseigner une désignation et un prix.');
                return;
            }
            this.cart.push({
                service_item_id: null,
                name: this.customItem.name,
                category: this.customItem.category,
                unit_price: Math.round(this.customItem.price * 100),
                quantity: 1,
            });
            this.customItem = { name: '', price: 0, category: 'other' };
            this.showCustomItemModal = false;
        },

        incrementQty(index) {
            this.cart[index].quantity++;
        },

        decrementQty(index) {
            if (this.cart[index].quantity > 1) {
                this.cart[index].quantity--;
            } else {
                this.removeLine(index);
            }
        },

        removeLine(index) {
            this.cart.splice(index, 1);
        },

        get totalTtc() {
            return this.cart.reduce((sum, line) => sum + (line.unit_price * line.quantity), 0);
        },

        get subtotalHt() {
            return Math.round(this.totalTtc / 1.1925);
        },

        get taxAmount() {
            return this.totalTtc - this.subtotalHt;
        },

        formatPrice(val) {
            const fcfa = Math.round(val / 100);
            return new Intl.NumberFormat('fr-FR').format(fcfa) + ' FCFA';
        },

        getCategoryLabel(category) {
            const map = {
                'breakfast': 'Petit-déj',
                'laundry': 'Blanchisserie',
                'spa': 'Spa',
                'activity': 'Activité',
                'minibar': 'Minibar',
                'housekeeping': 'Housekeeping',
                'other': 'Autre'
            };
            return map[category] || 'Autre';
        },

        handleSubmit(e) {
            if (this.cart.length === 0) {
                e.preventDefault();
                alert('Veuillez ajouter au moins une prestation au panier.');
                return;
            }
            if (this.clientMode === 'room' && !this.selectedBookingId) {
                e.preventDefault();
                alert('Veuillez sélectionner une chambre occupée.');
                return;
            }
            if (this.clientMode === 'customer' && !this.customerName.trim()) {
                e.preventDefault();
                alert('Veuillez saisir le nom du client.');
                return;
            }
        }
    };
}
</script>
@endpush
@endsection
