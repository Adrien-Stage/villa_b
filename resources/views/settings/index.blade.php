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
                        <input type="text" value="Villa Boutanga" class="w-full rounded-lg border-secondary/20 bg-gray-50 focus:ring-primary focus:border-primary text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1">Devise principale</label>
                        <select class="w-full rounded-lg border-secondary/20 bg-gray-50 focus:ring-primary focus:border-primary text-sm p-2.5">
                            <option>FCFA (XAF)</option>
                            <option>EUR (€)</option>
                            <option>USD ($)</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="block text-xs font-medium text-primary/70 mb-1">Logo de l'établissement</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-secondary/20 border-dashed rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer">
                        <div class="space-y-1 text-center">
                            <i data-lucide="image" class="mx-auto h-8 w-8 text-primary/40"></i>
                            <div class="flex text-sm text-primary/60">
                                <span>Télécharger un fichier</span>
                                <p class="pl-1">ou glisser-déposer</p>
                            </div>
                            <p class="text-xs text-primary/40">PNG, JPG, GIF jusqu'à 2MB</p>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="button" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-primary-dark transition-colors shadow-sm">
                        Enregistrer les modifications
                    </button>
                </div>
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
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Pourcentage de réduction max (%)</label>
                                <input type="number" name="settings[max_discount_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['max_discount_percentage'] ?? '10' }}" class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Acompte minimum requis (%)</label>
                                <input type="number" name="settings[min_deposit_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['min_deposit_percentage'] ?? '30' }}" class="w-full rounded-lg border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                                <p class="text-[10px] text-primary/50 mt-1">Sera exigé lors de la confirmation de réservation.</p>
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

    </div>
</div>
@endsection
