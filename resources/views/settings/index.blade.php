@extends('layouts.hotel')

@section('title', 'Paramètres de l\'établissement')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-primary font-heading">Paramètres</h1>
    <p class="text-sm text-primary/60 mt-1">Configurez les règles et préférences de votre département.</p>
</div>

@if(session('success'))
    <div class="mb-6 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4"></i>
        {{ session('success') }}
    </div>
@endif

<div class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden">
    
    {{-- Onglets de navigation dynamique selon le rôle --}}
    <div class="flex overflow-x-auto border-b border-secondary/20 bg-gray-50/50 px-4 pt-4 hide-scrollbar">
        
        @role('manager')
            <a href="{{ route('settings.index', ['tab' => 'general']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'general' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="settings" class="w-4 h-4"></i>
                Général
            </a>
        @endrole

        @role('manager', 'reception')
            <a href="{{ route('settings.index', ['tab' => 'reception']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'reception' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="concierge-bell" class="w-4 h-4"></i>
                Hébergement
            </a>
            <a href="{{ route('settings.index', ['tab' => 'taxes']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'taxes' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="calculator" class="w-4 h-4"></i>
                Taxes & Tarifs
            </a>
        @endrole

        @role('manager', 'housekeeping_leader')
            <a href="{{ route('settings.index', ['tab' => 'housekeeping']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'housekeeping' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                Housekeeping
            </a>
        @endrole

        @role('manager', 'restaurant_chief')
            <a href="{{ route('settings.index', ['tab' => 'restaurant']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'restaurant' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="utensils" class="w-4 h-4"></i>
                Restaurant
            </a>
        @endrole

        @role('manager', 'shop_manager')
            <a href="{{ route('settings.index', ['tab' => 'shop']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'shop' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="store" class="w-4 h-4"></i>
                Boutique
            </a>
        @endrole

        @role('manager')
            <a href="{{ route('settings.index', ['tab' => 'services']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'services' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="concierge-bell" class="w-4 h-4"></i>
                Prestations
            </a>
        @endrole
    </div>

    {{-- Contenu des onglets --}}
    <div class="p-6">
        
        {{-- ONGLET: GÉNÉRAL (Uniquement Manager) --}}
        @if($tab === 'general' && $user->hasRole('manager'))
            <div class="max-w-3xl">
                <h2 class="text-lg font-semibold text-primary mb-4">Informations Générales de l'établissement</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1">Nom de l'établissement</label>
                        <input type="text" value="{{ $tenant?->name ?? 'Établissement' }}" disabled class="w-full rounded-lg border-secondary/20 bg-gray-50 text-sm p-2.5 text-primary/60">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1">Devise principale</label>
                        <input type="text" value="{{ $tenant?->currency ?? 'XAF' }}" disabled class="w-full rounded-lg border-secondary/20 bg-gray-50 text-sm p-2.5 text-primary/60">
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.update', ['tab' => 'general']) }}" enctype="multipart/form-data"
                      class="mt-6"
                      x-data="{
                          fileName: '',
                          preview: @js(!empty($tenantSettings['logo'] ?? null) ? asset('storage/' . $tenantSettings['logo']) : null),
                          onFileSelected(event) {
                              const file = event.target.files[0];
                              if (!file) { this.fileName = ''; return; }
                              this.fileName = file.name;
                              const reader = new FileReader();
                              reader.onload = (e) => { this.preview = e.target.result; };
                              reader.readAsDataURL(file);
                          }
                      }">
                    @csrf
                    <label class="block text-xs font-medium text-primary/70 mb-1">Logo de l'établissement</label>

                    @error('logo')
                        <p class="mb-2 text-xs text-red-500">{{ $message }}</p>
                    @enderror

                    <div class="mb-3 flex items-center gap-3" x-show="preview" x-cloak>
                        <img :src="preview" alt="Aperçu du logo" class="w-14 h-14 rounded-full object-cover border border-secondary/20">
                        <span class="text-xs text-primary/50" x-text="fileName ? 'Nouveau logo (aperçu)' : 'Logo actuel'"></span>
                    </div>

                    <label class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-secondary/20 border-dashed rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer">
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/gif" class="hidden"
                               @change="onFileSelected($event)">
                        <div class="space-y-1 text-center">
                            <i data-lucide="image" class="mx-auto h-8 w-8 text-primary/40"></i>
                            <div class="flex text-sm text-primary/60 justify-center">
                                <span x-text="fileName || 'Télécharger un fichier'"></span>
                            </div>
                            <p class="text-xs text-primary/40">PNG, JPG, GIF jusqu'à 2MB</p>
                        </div>
                    </label>

                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors shadow-sm cursor-pointer">
                            Enregistrer le logo
                        </button>
                    </div>
                </form>
            </div>
        @endif

        {{-- ONGLET: HÉBERGEMENT (Réception & Manager) --}}
        @if($tab === 'reception' && $user->hasAnyRole(['manager', 'reception']))
            <form method="POST" action="{{ route('settings.update', ['tab' => 'reception']) }}" class="max-w-3xl">
                @csrf
                <h2 class="text-lg font-semibold text-primary mb-4">Paramètres Hébergement & Réception</h2>
                <div class="space-y-6">
                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-primary mb-1">Règle de Check-out</h3>
                            <p class="text-xs text-primary/60">Le client doit libérer sa chambre au plus tard à l'heure limite de sortie (Check-out) le jour de la fin de son séjour (J + nombre de nuits).</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Heure limite de sortie (Check-out)</label>
                                <input type="time" name="settings[check_out_time]" value="{{ $tenantSettings['reception']['check_out_time'] ?? '12:00' }}" class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-2">Tarification & Réductions</h3>
                        <p class="text-xs text-primary/60 mb-4">Définissez le pourcentage de réduction maximum autorisé et l'acompte minimum pour confirmer une réservation.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Pourcentage de réduction max (%)</label>
                                <input type="number" name="settings[max_discount_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['max_discount_percentage'] ?? '10' }}" class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Acompte minimum requis (%)</label>
                                <input type="number" name="settings[min_deposit_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['min_deposit_percentage'] ?? '30' }}" class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                                <p class="text-[10px] text-primary/50 mt-1">Exigé pour confirmer.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Surcharge capacité dépassée (%)</label>
                                <input type="number" name="settings[capacity_surcharge_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['capacity_surcharge_percentage'] ?? '10' }}" class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                                <p class="text-[10px] text-primary/50 mt-1">Taux si capacité de base dépassée.</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-sm font-semibold text-primary">Programme de Fidélisation</h3>
                                <p class="text-xs text-primary/60">Récompensez vos clients réguliers avec un système de points.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer" checked>
                                <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-secondary/20 pt-4 mt-2">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Montant dépensé pour 1 point</label>
                                <div class="relative">
                                    <input type="number" value="10000" class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5 pr-12">
                                    <span class="absolute right-3 top-2.5 text-xs text-primary/40 font-medium">FCFA</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Valeur d'un point en réduction</label>
                                <div class="relative">
                                    <input type="number" value="500" class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5 pr-12">
                                    <span class="absolute right-3 top-2.5 text-xs text-primary/40 font-medium">FCFA</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-2">Politique d'annulation</h3>
                        <textarea name="settings[cancellation_policy]" rows="3" placeholder="Saisissez les règles d'annulation..." class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">{{ $tenantSettings['reception']['cancellation_policy'] ?? '' }}</textarea>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors shadow-sm">
                        Enregistrer
                    </button>
                </div>
            </form>
        @endif

        {{-- ONGLET: TAXES (Réception & Manager) --}}
        @if($tab === 'taxes' && $user->hasAnyRole(['manager', 'reception']))
            <div class="max-w-3xl">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-primary">Taxes et Tarifs</h2>
                    <button type="button" class="px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary-dark transition-colors">
                        <i data-lucide="plus" class="w-3.5 h-3.5 inline-block mr-1"></i> Nouvelle Taxe
                    </button>
                </div>
                
                <div class="bg-gray-50 rounded-xl border border-secondary/20 p-6 text-center text-primary/60">
                    <i data-lucide="calculator" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                    <p class="text-sm">Aucune taxe configurée pour le moment.</p>
                </div>
            </div>
        @endif

        {{-- ONGLET: HOUSEKEEPING (Leader & Manager) --}}
        @if($tab === 'housekeeping' && $user->hasAnyRole(['manager', 'housekeeping_leader']))
            <div class="max-w-3xl">
                <h2 class="text-lg font-semibold text-primary mb-4">Paramètres Housekeeping</h2>
                <div class="space-y-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">Passage Automatique "Sale"</h3>
                            <p class="text-xs text-primary/60">Passer automatiquement une chambre au statut "Sale" lors du Check-out.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" value="" class="sr-only peer" checked>
                            <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-4">Temps alloué par tâche (Minutes)</h3>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Nettoyage standard</label>
                                <input type="number" value="30" class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Nettoyage à fond</label>
                                <input type="number" value="60" class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Inspection</label>
                                <input type="number" value="10" class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <button type="button" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors shadow-sm">
                        Enregistrer
                    </button>
                </div>
            </div>
        @endif

        {{-- ONGLET: RESTAURANT (Chief & Manager) --}}
        @if($tab === 'restaurant' && $user->hasAnyRole(['manager', 'restaurant_chief']))
            <div class="max-w-3xl">
                <h2 class="text-lg font-semibold text-primary mb-4">Paramètres du Restaurant</h2>
                <div class="space-y-6">
                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-4">Horaires d'ouverture</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Ouverture</label>
                                <input type="time" value="07:00" class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Fermeture</label>
                                <input type="time" value="23:30" class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <div>
                            <h3 class="text-sm font-semibold text-primary">Tickets cuisine</h3>
                            <p class="text-xs text-primary/60">Impression automatique en cuisine lors d'une commande.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" value="" class="sr-only peer">
                            <div class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <button type="button" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors shadow-sm">
                        Enregistrer
                    </button>
                </div>
            </div>
        @endif

        {{-- ONGLET: BOUTIQUE (Shop Manager & Manager) --}}
        @if($tab === 'shop' && $user->hasAnyRole(['manager', 'shop_manager']))
            <div class="max-w-3xl">
                <h2 class="text-lg font-semibold text-primary mb-4">Paramètres de la Boutique</h2>
                <div class="space-y-6">
                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-4">Alertes de Stock</h3>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1">Seuil d'alerte global (Rupture de stock imminente)</label>
                            <input type="number" value="5" class="w-full md:w-1/2 rounded-lg border-secondary/20 bg-white text-sm p-2.5">
                            <p class="text-[10px] text-primary/50 mt-1">Vous recevrez une alerte sur le tableau de bord quand le stock d'un produit atteint ce seuil.</p>
                        </div>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <button type="button" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors shadow-sm">
                        Enregistrer
                    </button>
                </div>
            </div>
        @endif

        {{-- ONGLET: PRESTATIONS (Manager) --}}
        @if($tab === 'services' && $user->hasRole('manager'))
            <div x-data="serviceCatalog({{ Js::from(\App\Models\ServiceItem::CATEGORIES) }})">
                <div class="flex items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Catalogue des prestations</h2>
                        <p class="text-sm text-primary/60 mt-1 max-w-2xl">
                            Ces prestations sont proposées à la réception lors de l'ajout d'une ligne au folio d'un séjour.
                            Les plats du restaurant, eux, se gèrent dans le menu du restaurant.
                        </p>
                    </div>
                    <button type="button" @click="openCreate()"
                        class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Nouvelle prestation
                    </button>
                </div>

                @if($errors->any())
                    <div class="mb-6 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-6">
                    @foreach($serviceCategories as $categoryKey => $categoryLabel)
                        @php $categoryItems = $serviceItems[$categoryKey] ?? collect(); @endphp
                        <div class="border border-secondary/20 rounded-xl overflow-hidden">
                            <div class="flex items-center justify-between px-5 py-3 bg-gray-50/70 border-b border-secondary/20">
                                <h3 class="text-sm font-semibold text-primary flex items-center gap-2">
                                    <i data-lucide="{{ [
                                        'activity' => 'mountain-snow',
                                        'spa' => 'flower-2',
                                        'housekeeping' => 'sparkles',
                                        'laundry' => 'shirt',
                                        'minibar' => 'wine',
                                        'other' => 'ellipsis',
                                    ][$categoryKey] ?? 'tag' }}" class="w-4 h-4 text-primary/50"></i>
                                    {{ $categoryLabel }}
                                </h3>
                                <span class="text-xs text-primary/40">{{ $categoryItems->count() }} prestation(s)</span>
                            </div>

                            @if($categoryItems->isEmpty())
                                <div class="px-5 py-6 text-center">
                                    <p class="text-xs text-primary/40">Aucune prestation dans cette catégorie.</p>
                                    <button type="button" @click="openCreate('{{ $categoryKey }}')"
                                        class="mt-2 text-xs font-medium text-primary hover:underline">
                                        Ajouter la première
                                    </button>
                                </div>
                            @else
                                <table class="min-w-full divide-y divide-secondary/10">
                                    <tbody class="divide-y divide-secondary/10">
                                        @foreach($categoryItems as $service)
                                            @php
                                                // Charge utile de l'éditeur, calculée ici : un tableau multi-ligne
                                                // passé directement à @json dans un attribut casse le parseur Blade.
                                                $editPayload = [
                                                    'id' => $service->id,
                                                    'category' => $service->category,
                                                    'name' => $service->name,
                                                    'description' => $service->description,
                                                    'price' => $service->priceInFcfa(),
                                                    'duration_minutes' => $service->duration_minutes,
                                                    'sort_order' => $service->sort_order,
                                                    'is_active' => $service->is_active,
                                                ];
                                            @endphp
                                            <tr class="{{ $service->is_active ? '' : 'opacity-60' }}">
                                                <td class="px-5 py-3">
                                                    <p class="text-sm font-medium text-primary">{{ $service->name }}</p>
                                                    @if($service->description)
                                                        <p class="text-xs text-primary/45 mt-0.5">{{ $service->description }}</p>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3 text-xs text-primary/60 whitespace-nowrap">
                                                    @if($service->duration_minutes)
                                                        <span class="inline-flex items-center gap-1">
                                                            <i data-lucide="clock" class="w-3 h-3"></i>
                                                            {{ $service->duration_minutes }} min
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-5 py-3 text-right text-sm font-semibold text-primary whitespace-nowrap">
                                                    {{ number_format($service->price / 100, 0, ',', ' ') }} FCFA
                                                </td>
                                                <td class="px-5 py-3 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $service->is_active ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                                        {{ $service->is_active ? 'Actif' : 'Inactif' }}
                                                    </span>
                                                </td>
                                                <td class="px-5 py-3">
                                                    <div class="flex justify-end gap-2">
                                                        <button type="button" @click="openEdit(@json($editPayload))"
                                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                                        </button>
                                                        <form method="POST" action="{{ route('settings.services.destroy', $service) }}"
                                                            onsubmit="return confirm('Supprimer « {{ $service->name }} » du catalogue ?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit"
                                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50">
                                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Modal création / édition --}}
                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    style="display: none; background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
                    <div class="absolute inset-0" @click="open = false"></div>
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg relative z-10 flex flex-col max-h-[90vh]">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
                            <h3 class="font-heading font-semibold text-primary"
                                x-text="editing ? 'Modifier la prestation' : 'Nouvelle prestation'"></h3>
                            <button type="button" @click="open = false" class="text-primary/30 hover:text-primary transition-colors">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                            @csrf
                            <template x-if="editing">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
                                <div>
                                    <label class="block text-xs font-medium text-primary/70 mb-1">Catégorie *</label>
                                    <select name="category" x-model="form.category" required
                                        class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                        <template x-for="(label, key) in categories" :key="key">
                                            <option :value="key" x-text="label"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-primary/70 mb-1">Nom *</label>
                                    <input type="text" name="name" x-model="form.name" required maxlength="140"
                                        placeholder="Ex : Excursion lac Barombi, Massage relaxant 60 min..."
                                        class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary placeholder-primary/30">
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-primary/70 mb-1">Prix (FCFA) *</label>
                                        <input type="number" name="price" x-model="form.price" min="0" required
                                            class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-primary/70 mb-1">Durée (min)</label>
                                        <input type="number" name="duration_minutes" x-model="form.duration_minutes" min="0" max="1440"
                                            class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-primary/70 mb-1">Ordre</label>
                                        <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                                            class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-primary/70 mb-1">Description (optionnel)</label>
                                    <textarea name="description" x-model="form.description" rows="2" maxlength="500"
                                        class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary"></textarea>
                                </div>

                                <label class="inline-flex items-center gap-2 text-xs text-primary/70">
                                    <input type="checkbox" name="is_active" value="1" x-model="form.is_active">
                                    Actif (proposé à la réception)
                                </label>
                            </div>

                            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                                <button type="button" @click="open = false"
                                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                                    <span x-text="editing ? 'Enregistrer' : 'Ajouter'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
    function serviceCatalog(categories) {
        const storeUrl = @js(route('settings.services.store'));
        const baseUrl = @js(url('/settings/services'));

        return {
            categories,
            open: false,
            editing: false,
            formAction: storeUrl,
            form: {},

            blank(category) {
                return {
                    id: null,
                    category: category || Object.keys(this.categories)[0],
                    name: '',
                    description: '',
                    price: 0,
                    duration_minutes: '',
                    sort_order: 0,
                    is_active: true,
                };
            },

            openCreate(category) {
                this.form = this.blank(category);
                this.editing = false;
                this.formAction = storeUrl;
                this.open = true;
            },

            openEdit(service) {
                this.form = {
                    ...service,
                    description: service.description ?? '',
                    duration_minutes: service.duration_minutes ?? '',
                };
                this.editing = true;
                this.formAction = `${baseUrl}/${service.id}`;
                this.open = true;
            },
        };
    }
</script>
@endpush
@endsection
