@extends('layouts.hotel')

@section('title', 'Paramètres de l\'établissement')

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Paramètres</h1>
        <p class="text-sm text-primary/60 mt-1">Configurez les règles et préférences de votre établissement.</p>
    </div>

    <div class="flex items-center gap-2 shrink-0">
        @if(in_array($tab, ['general', 'hebergement', 'taxes', 'housekeeping', 'restaurant', 'shop']))
            <a href="{{ route('settings.export', ['tab' => $tab]) }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm"
               title="Exporter les réglages de cet onglet en CSV">
                <i data-lucide="download" class="w-4 h-4 text-secondary"></i>
                <span>Exporter CSV</span>
            </a>
            <button type="button" onclick="document.getElementById('modal-import-settings-{{ $tab }}').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm"
                    title="Importer des réglages depuis un CSV">
                <i data-lucide="upload" class="w-4 h-4 text-secondary"></i>
                <span>Importer CSV</span>
            </button>
        @elseif($tab === 'services')
            <a href="{{ route('settings.services.export') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm">
                <i data-lucide="download" class="w-4 h-4 text-secondary"></i>
                <span>Exporter CSV</span>
            </a>
            <button type="button" onclick="document.getElementById('modal-import-services').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm">
                <i data-lucide="upload" class="w-4 h-4 text-secondary"></i>
                <span>Importer CSV</span>
            </button>
        @elseif($tab === 'partners')
            <a href="{{ route('settings.partners.export') }}"
               class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm">
                <i data-lucide="download" class="w-4 h-4 text-secondary"></i>
                <span>Exporter CSV</span>
            </a>
            <button type="button" onclick="document.getElementById('modal-import-partners').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm">
                <i data-lucide="upload" class="w-4 h-4 text-secondary"></i>
                <span>Importer CSV</span>
            </button>
        @endif
    </div>
</div>

<x-csv-import-errors />

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

        {{-- Hébergement : un seul onglet regroupant horaires et règles de séjour,
             délais de remise en vente et packs. Les réglages restent stockés sous
             deux clés distinctes (« reception » et « hebergement »), lues ailleurs
             dans l'application — seul l'affichage est unifié. --}}
        @role('manager', 'reception')
            <a href="{{ route('settings.index', ['tab' => 'hebergement']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'hebergement' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="bed-double" class="w-4 h-4"></i>
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

        @role('manager')
            <a href="{{ route('settings.index', ['tab' => 'partners']) }}"
                class="flex items-center gap-2 px-4 pb-3 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                      {{ $tab === 'partners' ? 'border-primary text-primary' : 'border-transparent text-primary/40 hover:text-primary/70' }}">
                <i data-lucide="handshake" class="w-4 h-4"></i>
                Partenaires
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
                        <input type="text" value="{{ $tenant?->name ?? 'Établissement' }}" disabled class="w-full rounded-lg border border-secondary/20 bg-gray-50 text-sm p-2.5 text-primary/60">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1">Devise principale</label>
                        <input type="text" value="{{ $tenant?->currency ?? 'XAF' }}" disabled class="w-full rounded-lg border border-secondary/20 bg-gray-50 text-sm p-2.5 text-primary/60">
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
        {{-- Section 1 de l'onglet Hébergement : horaires et règles de séjour.
             Le formulaire poste sur ?tab=reception pour que ces valeurs restent
             sous la clé « reception », d'où les lisent BookingController,
             RoomType et RoomAvailabilityService. --}}
        @if($tab === 'hebergement' && $user->hasAnyRole(['manager', 'reception']))
            <form method="POST" action="{{ route('settings.update', ['tab' => 'reception']) }}" class="max-w-3xl">
                @csrf

                {{-- Deux boutons pour un même formulaire : cette section est
                     suivie des délais de remise en vente puis des packs, si
                     bien qu'un unique bouton en fin de section se retrouve au
                     milieu de la page. Celui du haut évite de remonter après
                     une modification faite en tête. --}}
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-primary">Horaires et règles de séjour</h2>
                    <button type="submit" class="shrink-0 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        Enregistrer
                    </button>
                </div>

                <div class="space-y-6">
                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold text-primary mb-1">Horaires d'arrivée et de départ</h3>
                            <p class="text-xs text-primary/60">Le client doit libérer sa chambre au plus tard à l'heure limite de sortie (Check-out) le jour de la fin de son séjour (J + nombre de nuits).</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Heure limite de sortie (Check-out)</label>
                                <input type="time" name="settings[check_out_time]" value="{{ $tenantSettings['reception']['check_out_time'] ?? \App\Services\RoomAvailabilityService::DEFAULT_CHECK_OUT_TIME }}" class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Heure d'arrivée (Check-in)</label>
                                <input type="time" name="settings[check_in_time]" value="{{ $tenantSettings['reception']['check_in_time'] ?? \App\Services\RoomAvailabilityService::DEFAULT_CHECK_IN_TIME }}" class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                        </div>
                        @php
                            $dispo       = app(\App\Services\RoomAvailabilityService::class);
                            $pretA       = \Illuminate\Support\Carbon::parse($dispo->checkOutTime())->addMinutes($dispo->defaultDelayMinutes());
                            $rotationOk  = $dispo->canTurnOverSameDay(null);
                        @endphp
                        <div class="mt-4 px-3 py-2.5 rounded-lg text-xs border {{ $rotationOk ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
                            <span class="font-semibold">Rotation le jour même :</span>
                            @if($rotationOk)
                                possible. Départ à {{ $dispo->checkOutTime() }}, chambre prête à {{ $pretA->format('H:i') }}
                                après {{ $dispo->defaultDelayMinutes() }} min de ménage, arrivée à {{ $dispo->checkInTime() }}.
                            @else
                                impossible. Avec {{ $dispo->defaultDelayMinutes() }} min de ménage après un départ à
                                {{ $dispo->checkOutTime() }}, la chambre n'est prête qu'à {{ $pretA->format('H:i') }},
                                soit après l'heure d'arrivée ({{ $dispo->checkInTime() }}). Une chambre libérée un jour
                                ne pourra être reprise que le lendemain.
                            @endif
                            <span class="block mt-1 text-[10px] opacity-80">Le délai de ménage se règle dans l'onglet Hébergement, et peut différer par type de chambre.</span>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-2">Tarification & Réductions</h3>
                        <p class="text-xs text-primary/60 mb-4">Définissez le pourcentage de réduction maximum autorisé et l'acompte minimum pour confirmer une réservation.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Pourcentage de réduction max (%)</label>
                                <input type="number" name="settings[max_discount_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['max_discount_percentage'] ?? '10' }}" class="w-full rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Acompte minimum requis (%)</label>
                                <input type="number" name="settings[min_deposit_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['min_deposit_percentage'] ?? '30' }}" class="w-full rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                                <p class="text-[10px] text-primary/50 mt-1">Exigé pour confirmer.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Surcharge capacité dépassée (%)</label>
                                <input type="number" name="settings[capacity_surcharge_percentage]" min="0" max="100" value="{{ $tenantSettings['reception']['capacity_surcharge_percentage'] ?? '10' }}" class="w-full rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                                <p class="text-[10px] text-primary/50 mt-1">Taux si capacité de base dépassée.</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
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
                                    <input type="number" value="10000" class="w-full rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5 pr-12">
                                    <span class="absolute right-3 top-2.5 text-xs text-primary/40 font-medium">FCFA</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Valeur d'un point en réduction</label>
                                <div class="relative">
                                    <input type="number" value="500" class="w-full rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5 pr-12">
                                    <span class="absolute right-3 top-2.5 text-xs text-primary/40 font-medium">FCFA</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-2">Politique d'annulation</h3>
                        <textarea name="settings[cancellation_policy]" rows="3" placeholder="Saisissez les règles d'annulation..." class="w-full rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">{{ $tenantSettings['reception']['cancellation_policy'] ?? '' }}</textarea>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        Enregistrer
                    </button>
                </div>
            </form>
        @endif

        {{-- ONGLET: TAXES (Réception & Manager) --}}
        @if($tab === 'taxes' && $user->hasAnyRole(['manager', 'reception']))
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
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
                                <input type="number" value="30" class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Nettoyage à fond</label>
                                <input type="number" value="60" class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Inspection</label>
                                <input type="number" value="10" class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5">
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Ouverture</label>
                                <input type="time" value="07:00" class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Fermeture</label>
                                <input type="time" value="23:30" class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5">
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
                <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
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
                                {{-- overflow-x-auto : sur mobile le tableau défile au lieu de pousser la page --}}
                                <div class="overflow-x-auto -mx-1 px-1">
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
                                </div>
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
                                        class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                        <template x-for="(label, key) in categories" :key="key">
                                            <option :value="key" x-text="label"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-primary/70 mb-1">Nom *</label>
                                    <input type="text" name="name" x-model="form.name" required maxlength="140"
                                        placeholder="Ex : Excursion lac Barombi, Massage relaxant 60 min..."
                                        class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5 text-primary placeholder-primary/30">
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-primary/70 mb-1">Prix (FCFA) *</label>
                                        <input type="number" name="price" x-model="form.price" min="0" required
                                            class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-primary/70 mb-1">Durée (min)</label>
                                        <input type="number" name="duration_minutes" x-model="form.duration_minutes" min="0" max="1440"
                                            class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-primary/70 mb-1">Ordre</label>
                                        <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                                            class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-medium text-primary/70 mb-1">Description (optionnel)</label>
                                    <textarea name="description" x-model="form.description" rows="2" maxlength="500"
                                        class="w-full rounded-lg border border-secondary/20 bg-white text-sm p-2.5 text-primary"></textarea>
                                </div>

                                <label class="inline-flex items-center gap-2 text-xs text-primary/70">
                                    {{-- Champ caché piloté par Alpine : une case à cocher liée en x-model
                                         peut soumettre une valeur vide (→ null → inactif). On envoie donc
                                         une valeur déterministe 1/0, la case n'étant qu'un contrôle visuel. --}}
                                    <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                                    <input type="checkbox" x-model="form.is_active" class="rounded border-secondary/30 text-primary">
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

        {{-- Sections 2 et 3 du même onglet Hébergement : délais de remise en
             vente puis packs. Réservées au manager — la réception n'a accès
             qu'aux horaires et règles de séjour ci-dessus. --}}
        @if($tab === 'hebergement' && $user->hasRole('manager'))
            <hr class="border-secondary/15 my-10">

            @php
                $hebergement   = $tenantSettings['hebergement'] ?? [];
                $globalDelay   = $hebergement['cleaning_delay_minutes'] ?? \App\Services\RoomAvailabilityService::DEFAULT_DELAY_MINUTES;
                $delayByType   = (array) ($hebergement['cleaning_delay_by_type'] ?? []);
            @endphp

            <form method="POST" action="{{ route('settings.update', ['tab' => 'hebergement']) }}" class="max-w-3xl mb-10">
                @csrf
                <h2 class="text-lg font-semibold text-primary mb-1">Remise en vente après départ</h2>
                <p class="text-sm text-primary/60 mb-5 max-w-2xl">
                    Temps nécessaire à l'équipe de ménage pour remettre une chambre en état. Pendant ce délai, la chambre
                    reste visible sur le site de réservation avec la mention de l'heure à laquelle elle sera prête,
                    au lieu de disparaître de l'offre et de faire perdre la réservation.
                </p>

                <div class="space-y-6">
                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-1">Délai par défaut</h3>
                        <p class="text-xs text-primary/60 mb-4">Appliqué à tout type de chambre sans réglage propre.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-primary/70 mb-1">Durée (minutes)</label>
                                <input type="number" name="settings[cleaning_delay_minutes]" min="0" max="1440" step="5"
                                       value="{{ $globalDelay }}"
                                       class="w-full rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2.5">
                                <p class="text-[10px] text-primary/50 mt-1">120 minutes = 2 h.</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 rounded-xl border border-secondary/20">
                        <h3 class="text-sm font-semibold text-primary mb-1">Délai par type de chambre</h3>
                        <p class="text-xs text-primary/60 mb-4">
                            Une suite présidentielle ne se remet pas en état aussi vite qu'une chambre économique.
                            Laissez vide pour appliquer le délai par défaut.
                        </p>

                        @if($roomTypes->isEmpty())
                            <p class="text-xs text-primary/50 italic">Aucun type de chambre enregistré pour le moment.</p>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($roomTypes as $type)
                                    <div class="flex items-center gap-3 bg-white rounded-lg border border-secondary/20 p-2.5">
                                        <span class="flex-1 min-w-0 text-xs font-medium text-primary truncate" title="{{ $type->name }}">
                                            {{ $type->name }}
                                        </span>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <input type="number" name="settings[cleaning_delay_by_type][{{ $type->id }}]"
                                                   min="0" max="1440" step="5"
                                                   value="{{ $delayByType[$type->id] ?? '' }}"
                                                   placeholder="{{ $globalDelay }}"
                                                   class="w-20 rounded-lg border border-secondary/20 bg-white focus:ring-primary focus:border-primary text-sm p-2 text-right">
                                            <span class="text-[10px] text-primary/50">min</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        Enregistrer les délais
                    </button>
                </div>
            </form>

            <hr class="border-secondary/15 mb-10">

            @php
                // Charge utile de l'éditeur préparée ici : un tableau multi-ligne
                // passé directement à @json dans un attribut casse le parseur Blade.
                $packPayloads = $roomPackages->mapWithKeys(fn ($p) => [$p->id => [
                    'id'                  => $p->id,
                    'name'                => $p->name,
                    'code'                => $p->code,
                    'description'         => $p->description,
                    'meals'               => $p->meals ?? [],
                    'service_item_ids'    => array_map('intval', $p->service_item_ids ?? []),
                    'pricing_mode'        => $p->pricing_mode,
                    'price'               => (int) ($p->price / 100),
                    'room_discount_type'  => $p->room_discount_type,
                    'room_discount_value' => $p->room_discount_type === 'amount'
                                                ? (int) ($p->room_discount_value / 100)
                                                : (int) $p->room_discount_value,
                    'room_type_ids'       => array_map('intval', $p->room_type_ids ?? []),
                    'sort_order'          => (int) $p->sort_order,
                    'is_active'           => (bool) $p->is_active,
                ]])->all();

                $packServiceOptions = $serviceItemsFlat->map(fn ($s) => [
                    'id'    => $s->id,
                    'name'  => $s->name,
                    'group' => $s->categoryLabel(),
                    'price' => (int) ($s->price / 100),
                ])->values()->all();

                $packRoomTypeOptions = $roomTypes->map(fn ($t) => [
                    'id'   => $t->id,
                    'name' => $t->name,
                ])->values()->all();
            @endphp

            <div x-data="packCatalog({{ Js::from($mealServices) }}, {{ Js::from($packServiceOptions) }}, {{ Js::from($packRoomTypeOptions) }}, {{ Js::from($packPricingModes) }})">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Packs d'hébergement</h2>
                        <p class="text-sm text-primary/60 mt-1 max-w-2xl">
                            Formules proposées au client au moment de la réservation : demi-pension, pension complète,
                            séjour affaires avec blanchisserie… Chaque pack regroupe des repas et des prestations à un
                            tarif forfaitaire, et peut s'accompagner d'une remise sur la nuitée.
                        </p>
                    </div>
                    <button type="button" @click="openCreate()"
                        class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Nouveau pack
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

                @if($roomPackages->isEmpty())
                    <div class="border border-dashed border-secondary/30 rounded-xl px-6 py-12 text-center">
                        <i data-lucide="bed-double" class="w-8 h-8 mx-auto text-primary/20 mb-3"></i>
                        <p class="text-sm text-primary/50">Aucun pack configuré.</p>
                        <button type="button" @click="openCreate()" class="mt-2 text-xs font-medium text-primary hover:underline">
                            Créer le premier
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        @foreach($roomPackages as $package)
                            <div class="border border-secondary/20 rounded-xl overflow-hidden {{ $package->is_active ? 'bg-white' : 'bg-gray-50/60' }}">
                                <div class="px-5 py-3 border-b border-secondary/20 flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-primary truncate">
                                            {{ $package->name }}
                                            @if($package->code)
                                                <span class="ml-1 text-[10px] font-mono text-primary/40">{{ $package->code }}</span>
                                            @endif
                                        </p>
                                        <p class="text-[11px] text-primary/40">{{ $package->pricingModeLabel() }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-sm font-bold text-primary">{{ number_format($package->price / 100, 0, ',', ' ') }} F</p>
                                        @unless($package->is_active)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-700 border border-red-200">Inactif</span>
                                        @endunless
                                    </div>
                                </div>

                                <div class="px-5 py-3">
                                    @php $contents = $package->contentLabels(); @endphp
                                    @if(empty($contents))
                                        <p class="text-xs text-primary/30">Pack vide — aucun repas ni prestation.</p>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($contents as $label)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-accent/30 text-primary border border-secondary/20">{{ $label }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <p class="text-[11px] text-primary/40 mt-2">
                                        @if(empty($package->room_type_ids))
                                            Proposé sur tous les types de chambre
                                        @else
                                            {{ count($package->room_type_ids) }} type(s) de chambre concerné(s)
                                        @endif
                                    </p>
                                </div>

                                <div class="px-5 py-2.5 bg-gray-50/70 border-t border-secondary/20 flex justify-end gap-2">
                                    <button type="button" @click="openEdit(@json($packPayloads[$package->id]))"
                                        class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <form method="POST" action="{{ route('settings.packages.destroy', $package) }}"
                                        onsubmit="return confirm('Supprimer le pack « {{ $package->name }} » ? Les séjours déjà vendus conservent leur montant.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Modal création / édition --}}
                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    style="display: none; background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
                    <div class="absolute inset-0" @click="open = false"></div>
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl relative z-10 flex flex-col max-h-[90vh]">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
                            <h3 class="font-heading font-semibold text-primary"
                                x-text="editing ? 'Modifier le pack' : 'Nouveau pack d\'hébergement'"></h3>
                            <button type="button" @click="open = false" class="text-primary/30 hover:text-primary transition-colors">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                            @csrf
                            <template x-if="editing">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div class="px-6 py-5 space-y-6 flex-1 overflow-y-auto min-h-0 bg-gray-50/40">

                                {{-- Identité --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                                        <i data-lucide="tag" class="w-3.5 h-3.5"></i>
                                        Identité
                                    </h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Nom du pack <span class="text-red-500">*</span></label>
                                            <input type="text" name="name" x-model="form.name" @input="applyAutoCode()" required maxlength="140"
                                                placeholder="Ex : Demi-pension, Pension complète, Séjour affaires"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors placeholder-primary/30">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Code</label>
                                            <input type="text" name="code" x-model="form.code" @input="autoCode = false" maxlength="30" placeholder="DP"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors placeholder-primary/30 font-mono">
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Description</label>
                                        <textarea name="description" x-model="form.description" rows="2" maxlength="500"
                                            placeholder="Ce que le client obtient, en une phrase..."
                                            class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors placeholder-primary/30"></textarea>
                                    </div>
                                </section>

                                {{-- Composition --}}
                                <section class="bg-accent/10 border border-secondary/30 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/60 mb-1">
                                        <i data-lucide="utensils" class="w-3.5 h-3.5"></i>
                                        Composition du pack
                                    </h4>
                                    <p class="text-[11px] text-primary/40 mb-4">Ce que la formule comprend, facturé forfaitairement.</p>

                                    {{-- Repas --}}
                                    <div class="bg-white border border-secondary/20 rounded-lg p-3.5">
                                        <p class="text-xs font-semibold text-primary mb-2.5">Repas inclus</p>
                                        {{-- Cases rendues côté serveur avec une valeur littérale.
                                             Associer « :value » à « x-model » ne fonctionne pas :
                                             x-model s'approprie la propriété value de la case et
                                             écrase la liaison, si bien que chaque case partait avec
                                             une valeur vide — d'où « The selected meals.0 is invalid »
                                             au moment de créer le pack. --}}
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                            @foreach($mealServices as $mealKey => $mealLabel)
                                                <label class="flex items-center gap-2.5 border border-secondary/20 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-accent/10 transition-colors"
                                                    :class="form.meals.includes(@js($mealKey)) ? 'bg-accent/20 border-secondary/40' : ''">
                                                    <input type="checkbox" value="{{ $mealKey }}" x-model="form.meals"
                                                        class="w-4 h-4 rounded border-secondary/40 text-primary">
                                                    <span class="text-xs text-primary/80">{{ $mealLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <template x-for="meal in form.meals" :key="'meal-' + meal">
                                            <input type="hidden" name="meals[]" :value="meal">
                                        </template>
                                    </div>

                                    {{-- Prestations --}}
                                    <div class="bg-white border border-secondary/20 rounded-lg p-3.5 mt-3">
                                        <div class="flex items-baseline justify-between mb-2.5">
                                            <p class="text-xs font-semibold text-primary">Prestations incluses</p>
                                            <span class="text-[10px] text-primary/40"
                                                x-show="form.service_item_ids.length > 0"
                                                x-text="form.service_item_ids.length + ' sélectionnée' + (form.service_item_ids.length > 1 ? 's' : '')"></span>
                                        </div>

                                        <template x-if="services.length === 0">
                                            <p class="text-[11px] text-primary/50 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5">
                                                Aucune prestation au catalogue. Ajoutez-en dans l'onglet Prestations (blanchisserie, spa…).
                                            </p>
                                        </template>

                                        <div x-show="services.length > 0" class="border border-secondary/30 rounded-lg max-h-40 overflow-y-auto divide-y divide-secondary/10">
                                            @foreach($serviceItemsFlat as $svc)
                                                <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-accent/20 cursor-pointer transition-colors"
                                                    :class="form.service_item_ids.includes({{ (int) $svc->id }}) ? 'bg-accent/20' : ''">
                                                    <input type="checkbox" value="{{ (int) $svc->id }}" x-model.number="form.service_item_ids"
                                                        class="w-4 h-4 rounded border-secondary/40 text-primary shrink-0">
                                                    <span class="flex-1 min-w-0">
                                                        <span class="block text-sm text-primary truncate">{{ $svc->name }}</span>
                                                        <span class="block text-[10px] text-primary/40">{{ $svc->categoryLabel() }}</span>
                                                    </span>
                                                    <span class="text-xs text-primary/50 shrink-0">
                                                        {{ number_format((int) ($svc->price / 100), 0, ',', ' ') }} F
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <template x-for="id in form.service_item_ids" :key="'svc-' + id">
                                            <input type="hidden" name="service_item_ids[]" :value="id">
                                        </template>
                                    </div>
                                </section>

                                {{-- Tarification --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                                        <i data-lucide="coins" class="w-3.5 h-3.5"></i>
                                        Tarification
                                    </h4>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Mode de facturation <span class="text-red-500">*</span></label>
                                            {{-- Options rendues côté serveur : elles viennent d'une
                                                 constante PHP, rien ne justifie de les fabriquer en
                                                 JavaScript. Générées par x-for, un JavaScript en
                                                 défaut laissait un select vide, donc un envoi sans
                                                 mode de facturation — refusé par la validation avec
                                                 « Le champ pricing mode sélectionné n'est pas
                                                 valide », sans que la cause soit visible à l'écran. --}}
                                            <select name="pricing_mode" x-model="form.pricing_mode" required
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                @foreach($packPricingModes as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Prix du pack <span class="text-red-500">*</span></label>
                                            <div class="relative">
                                                <input type="number" name="price" x-model="form.price" min="0" required
                                                    class="w-full px-3 py-2.5 pr-14 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                <span class="absolute right-3 top-2.5 text-xs font-medium text-primary/40">FCFA</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Aperçu du coût réel d'un séjour type, pour éviter les erreurs de mode --}}
                                    <p class="text-[11px] text-primary/50 mt-3 bg-gray-50 border border-secondary/20 rounded-lg px-3 py-2">
                                        Pour un séjour de 3 nuits à 2 personnes, ce pack serait facturé
                                        <strong class="text-primary" x-text="new Intl.NumberFormat('fr-FR').format(previewAmount(3, 2)) + ' FCFA'"></strong>.
                                    </p>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t border-secondary/20">
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Remise sur l'hébergement</label>
                                            <select name="room_discount_type" x-model="form.room_discount_type"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                <option value="none">Aucune</option>
                                                <option value="percent">Pourcentage</option>
                                                <option value="amount">Montant par nuitée</option>
                                            </select>
                                        </div>
                                        <div x-show="form.room_discount_type !== 'none'" style="display:none;">
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5"
                                                x-text="form.room_discount_type === 'percent' ? 'Pourcentage' : 'Montant par nuitée'"></label>
                                            <div class="relative">
                                                <input type="number" name="room_discount_value" x-model="form.room_discount_value" min="0"
                                                    :max="form.room_discount_type === 'percent' ? 100 : 10000000"
                                                    class="w-full px-3 py-2.5 pr-14 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                <span class="absolute right-3 top-2.5 text-xs font-medium text-primary/40"
                                                    x-text="form.room_discount_type === 'percent' ? '%' : 'FCFA'"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-primary/40 mt-2">
                                        Geste commercial sur la nuitée elle-même, en plus de la formule. Il se cumule avec une éventuelle remise partenaire.
                                    </p>
                                </section>

                                {{-- Portée --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                                        <i data-lucide="door-open" class="w-3.5 h-3.5"></i>
                                        Chambres concernées
                                    </h4>

                                    <template x-if="roomTypes.length === 0">
                                        <p class="text-[11px] text-primary/50">Aucun type de chambre enregistré.</p>
                                    </template>

                                    <div x-show="roomTypes.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($roomTypes as $rt)
                                            <label class="flex items-center gap-2.5 border border-secondary/20 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-accent/10 transition-colors"
                                                :class="form.room_type_ids.includes({{ (int) $rt->id }}) ? 'bg-accent/20 border-secondary/40' : ''">
                                                <input type="checkbox" value="{{ (int) $rt->id }}" x-model.number="form.room_type_ids"
                                                    class="w-4 h-4 rounded border-secondary/40 text-primary">
                                                <span class="text-xs text-primary/80 truncate">{{ $rt->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <template x-for="id in form.room_type_ids" :key="'rt-' + id">
                                        <input type="hidden" name="room_type_ids[]" :value="id">
                                    </template>
                                    <p class="text-[11px] text-primary/40 mt-2">
                                        Ne rien cocher revient à proposer le pack sur <strong>tous</strong> les types de chambre.
                                    </p>
                                </section>

                                {{-- Affichage --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Ordre d'affichage</label>
                                            <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                        </div>
                                        <label class="flex items-center gap-2.5 px-3 py-2.5 border border-secondary/30 rounded-lg cursor-pointer hover:bg-accent/10 transition-colors">
                                            <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                                            <input type="checkbox" x-model="form.is_active" class="w-4 h-4 rounded border-secondary/40 text-primary">
                                            <span class="text-xs text-primary/80">Pack proposé à la réservation</span>
                                        </label>
                                    </div>
                                </section>
                            </div>

                            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                                <button type="button" @click="open = false"
                                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                                    <span x-text="editing ? 'Enregistrer' : 'Créer'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- ONGLET: PARTENAIRES (Uniquement Manager) --}}
        @if($tab === 'partners' && $user->hasRole('manager'))
            @php
                // Charge utile de l'éditeur préparée ici : un tableau multi-ligne
                // passé directement à @json dans un attribut casse le parseur Blade.
                $partnerPayloads = $partnerOrganizations->mapWithKeys(fn ($o) => [$o->id => [
                    'id'                          => $o->id,
                    'name'                        => $o->name,
                    'code'                        => $o->code,
                    'type'                        => $o->type,
                    'contact_name'                => $o->contact_name,
                    'contact_email'               => $o->contact_email,
                    'contact_phone'               => $o->contact_phone,
                    'valid_from'                  => $o->valid_from?->format('Y-m-d'),
                    'valid_until'                 => $o->valid_until?->format('Y-m-d'),
                    'is_active'                   => (bool) $o->is_active,
                    'room_discount_type'          => $o->room_discount_type,
                    // Un montant est stocké en centimes mais se saisit en FCFA.
                    'room_discount_value'         => $o->room_discount_type === 'amount'
                                                        ? (int) ($o->room_discount_value / 100)
                                                        : (int) $o->room_discount_value,
                    'restaurant_discount_percent' => (int) $o->restaurant_discount_percent,
                    'shop_discount_percent'       => (int) $o->shop_discount_percent,
                    'free_service_item_ids'       => array_map('intval', $o->free_service_item_ids ?? []),
                    'late_checkout'               => (bool) $o->late_checkout,
                    'early_checkin'               => (bool) $o->early_checkin,
                    'notes'                       => $o->notes,
                ]])->all();

                $partnerServiceOptions = $serviceItemsFlat->map(fn ($s) => [
                    'id'    => $s->id,
                    'name'  => $s->name,
                    'group' => $s->categoryLabel(),
                    'price' => (int) ($s->price / 100),
                ])->values()->all();
            @endphp

            <div x-data="partnerCatalog({{ Js::from($partnerTypes) }}, {{ Js::from($partnerServiceOptions) }})">
                <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-primary">Organisations partenaires</h2>
                        <p class="text-sm text-primary/60 mt-1 max-w-2xl">
                            Entreprises, ONG, ambassades ou agences avec lesquelles vous avez une convention.
                            Les privilèges définis ici sont appliqués automatiquement dès qu'un client déclaré
                            membre effectue une réservation.
                        </p>
                    </div>
                    <button type="button" @click="openCreate()"
                        class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Nouvelle organisation
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

                @if($partnerOrganizations->isEmpty())
                    <div class="border border-dashed border-secondary/30 rounded-xl px-6 py-12 text-center">
                        <i data-lucide="handshake" class="w-8 h-8 mx-auto text-primary/20 mb-3"></i>
                        <p class="text-sm text-primary/50">Aucune organisation partenaire enregistrée.</p>
                        <button type="button" @click="openCreate()" class="mt-2 text-xs font-medium text-primary hover:underline">
                            Créer la première
                        </button>
                    </div>
                @else
                    <div class="border border-secondary/20 rounded-xl overflow-hidden">
                        <table class="min-w-full divide-y divide-secondary/10">
                            <thead class="bg-gray-50/70">
                                <tr>
                                    <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Organisation</th>
                                    <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Privilèges</th>
                                    <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Convention</th>
                                    <th class="px-5 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-primary/50">Membres</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-secondary/10">
                                @foreach($partnerOrganizations as $organization)
                                    @php
                                        $privileges = $organization->privilegeLabels();
                                        $expired    = !$organization->isValidOn();
                                    @endphp
                                    <tr class="{{ $expired ? 'bg-gray-50/50' : '' }}">
                                        <td class="px-5 py-3">
                                            <p class="text-sm font-medium text-primary">
                                                {{ $organization->name }}
                                                @if($organization->code)
                                                    <span class="ml-1 text-[10px] font-mono text-primary/40">{{ $organization->code }}</span>
                                                @endif
                                            </p>
                                            <p class="text-[11px] text-primary/40">{{ $organization->typeLabel() }}</p>
                                        </td>
                                        <td class="px-5 py-3">
                                            @if(empty($privileges))
                                                <span class="text-xs text-primary/30">Aucun privilège défini</span>
                                            @else
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($privileges as $privilege)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-accent/30 text-primary border border-secondary/20">{{ $privilege }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap text-[11px] text-primary/60">
                                            @if($organization->valid_from || $organization->valid_until)
                                                {{ $organization->valid_from?->format('d/m/Y') ?? '…' }}
                                                &rarr;
                                                {{ $organization->valid_until?->format('d/m/Y') ?? '…' }}
                                            @else
                                                <span class="text-primary/30">Sans échéance</span>
                                            @endif
                                            @if($expired)
                                                <span class="block mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-700 border border-red-200">
                                                    {{ $organization->is_active ? 'Hors période' : 'Désactivée' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-sm text-primary/70 whitespace-nowrap">
                                            {{ $organization->customers()->count() }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" @click="openEdit(@json($partnerPayloads[$organization->id]))"
                                                    class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                                </button>
                                                <form method="POST" action="{{ route('settings.partners.destroy', $organization) }}"
                                                    onsubmit="return confirm('Supprimer « {{ $organization->name }} » ? Les clients rattachés ne perdront pas leur historique, mais ne bénéficieront plus des privilèges.');">
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
                    </div>
                @endif

                {{-- Modal création / édition --}}
                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    style="display: none; background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
                    <div class="absolute inset-0" @click="open = false"></div>
                    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl relative z-10 flex flex-col max-h-[90vh]">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
                            <h3 class="font-heading font-semibold text-primary"
                                x-text="editing ? 'Modifier l\'organisation' : 'Nouvelle organisation partenaire'"></h3>
                            <button type="button" @click="open = false" class="text-primary/30 hover:text-primary transition-colors">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                            @csrf
                            <template x-if="editing">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            {{-- Note : le plugin @tailwindcss/forms n'est pas chargé dans
                                 app.css, donc le preflight met border-width à 0. Les champs
                                 doivent porter la classe `border` explicitement, sinon la
                                 couleur de bordure seule reste invisible. --}}
                            <div class="px-6 py-5 space-y-6 flex-1 overflow-y-auto min-h-0 bg-gray-50/40">

                                {{-- ── Identité ── --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                                        <i data-lucide="building-2" class="w-3.5 h-3.5"></i>
                                        Identité
                                    </h4>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Nom de l'organisation <span class="text-red-500">*</span></label>
                                            <input type="text" name="name" x-model="form.name" @input="applyAutoCode()" required maxlength="160"
                                                placeholder="Ex : Total Energies Cameroun"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors placeholder-primary/30">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Code</label>
                                            <input type="text" name="code" x-model="form.code" @input="autoCode = false" maxlength="30" placeholder="TEC"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors placeholder-primary/30 font-mono">
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <label class="block text-xs font-medium text-primary/70 mb-1.5">Type <span class="text-red-500">*</span></label>
                                        <select name="type" x-model="form.type" required
                                            class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                            <template x-for="(label, key) in types" :key="key">
                                                <option :value="key" x-text="label"></option>
                                            </template>
                                        </select>
                                    </div>
                                </section>

                                {{-- ── Contact ── --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                                        <i data-lucide="user-round" class="w-3.5 h-3.5"></i>
                                        Interlocuteur
                                    </h4>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Nom</label>
                                            <input type="text" name="contact_name" x-model="form.contact_name" maxlength="120"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Email</label>
                                            <input type="email" name="contact_email" x-model="form.contact_email" maxlength="150"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Téléphone</label>
                                            <input type="text" name="contact_phone" x-model="form.contact_phone" maxlength="30"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                        </div>
                                    </div>
                                </section>

                                {{-- ── Validité ── --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                                        <i data-lucide="calendar-range" class="w-3.5 h-3.5"></i>
                                        Validité de la convention
                                    </h4>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Du</label>
                                            <input type="date" name="valid_from" x-model="form.valid_from"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-primary/70 mb-1.5">Au</label>
                                            <input type="date" name="valid_until" x-model="form.valid_until"
                                                class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                        </div>
                                    </div>
                                    <p class="text-[11px] text-primary/40 mt-2">
                                        Laisser vide pour une convention sans échéance. Hors de cette période, les privilèges cessent de s'appliquer.
                                    </p>

                                    <label class="mt-4 flex items-center gap-2.5 px-3 py-2.5 border border-secondary/30 rounded-lg cursor-pointer hover:bg-accent/10 transition-colors">
                                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                                        <input type="checkbox" x-model="form.is_active" class="w-4 h-4 rounded border-secondary/40 text-primary">
                                        <span class="text-xs text-primary/80">Convention active</span>
                                    </label>
                                </section>

                                {{-- ── Privilèges ── --}}
                                <section class="bg-accent/10 border border-secondary/30 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/60 mb-1">
                                        <i data-lucide="gift" class="w-3.5 h-3.5"></i>
                                        Privilèges accordés aux membres
                                    </h4>
                                    <p class="text-[11px] text-primary/40 mb-4">
                                        Appliqués automatiquement dès qu'un client rattaché à cette organisation réserve.
                                    </p>

                                    {{-- Hébergement --}}
                                    <div class="bg-white border border-secondary/20 rounded-lg p-3.5">
                                        <p class="text-xs font-semibold text-primary mb-2.5">Hébergement</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-primary/70 mb-1.5">Type de remise</label>
                                                <select name="room_discount_type" x-model="form.room_discount_type"
                                                    class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                    <option value="none">Aucune</option>
                                                    <option value="percent">Pourcentage</option>
                                                    <option value="amount">Montant par nuitée</option>
                                                </select>
                                            </div>
                                            <div x-show="form.room_discount_type !== 'none'" style="display:none;">
                                                <label class="block text-xs font-medium text-primary/70 mb-1.5"
                                                    x-text="form.room_discount_type === 'percent' ? 'Pourcentage' : 'Montant par nuitée'"></label>
                                                <div class="relative">
                                                    <input type="number" name="room_discount_value" x-model="form.room_discount_value" min="0"
                                                        :max="form.room_discount_type === 'percent' ? 100 : 10000000"
                                                        class="w-full px-3 py-2.5 pr-14 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                    <span class="absolute right-3 top-2.5 text-xs font-medium text-primary/40"
                                                        x-text="form.room_discount_type === 'percent' ? '%' : 'FCFA'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Autres pôles --}}
                                    <div class="bg-white border border-secondary/20 rounded-lg p-3.5 mt-3">
                                        <p class="text-xs font-semibold text-primary mb-2.5">Restauration et boutique</p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs font-medium text-primary/70 mb-1.5">Remise restaurant</label>
                                                <div class="relative">
                                                    <input type="number" name="restaurant_discount_percent" x-model="form.restaurant_discount_percent" min="0" max="100"
                                                        class="w-full px-3 py-2.5 pr-9 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                    <span class="absolute right-3 top-2.5 text-xs font-medium text-primary/40">%</span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-primary/70 mb-1.5">Remise boutique</label>
                                                <div class="relative">
                                                    <input type="number" name="shop_discount_percent" x-model="form.shop_discount_percent" min="0" max="100"
                                                        class="w-full px-3 py-2.5 pr-9 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors">
                                                    <span class="absolute right-3 top-2.5 text-xs font-medium text-primary/40">%</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Prestations offertes --}}
                                    <div class="bg-white border border-secondary/20 rounded-lg p-3.5 mt-3">
                                        <div class="flex items-baseline justify-between mb-2.5">
                                            <p class="text-xs font-semibold text-primary">Prestations offertes</p>
                                            <span class="text-[10px] text-primary/40"
                                                x-show="form.free_service_item_ids.length > 0"
                                                x-text="form.free_service_item_ids.length + ' sélectionnée' + (form.free_service_item_ids.length > 1 ? 's' : '')"></span>
                                        </div>

                                        <template x-if="services.length === 0">
                                            <p class="text-[11px] text-primary/50 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5">
                                                Aucune prestation au catalogue. Ajoutez-en dans l'onglet Prestations pour pouvoir en offrir.
                                            </p>
                                        </template>

                                        <div x-show="services.length > 0" class="border border-secondary/30 rounded-lg max-h-44 overflow-y-auto divide-y divide-secondary/10">
                                            @foreach($serviceItemsFlat as $svc)
                                                @php $svcId = (int) $svc->id; @endphp
                                                <label class="flex items-center gap-3 px-3 py-2.5 hover:bg-accent/20 cursor-pointer transition-colors"
                                                    :class="form.free_service_item_ids.includes({{ $svcId }}) ? 'bg-accent/20' : ''">
                                                    {{-- Valeur littérale, rendue côté serveur : associée à
                                                         x-model, une liaison « :value » est écrasée et la case
                                                         partait vide. --}}
                                                    <input type="checkbox" value="{{ $svcId }}" x-model.number="form.free_service_item_ids"
                                                        class="w-4 h-4 rounded border-secondary/40 text-primary shrink-0">
                                                    <span class="flex-1 min-w-0">
                                                        <span class="block text-sm text-primary truncate">{{ $svc->name }}</span>
                                                        <span class="block text-[10px] text-primary/40">{{ $svc->categoryLabel() }}</span>
                                                    </span>
                                                    <span class="text-xs shrink-0"
                                                        :class="form.free_service_item_ids.includes({{ $svcId }}) ? 'text-green-700 font-semibold' : 'text-primary/50'"
                                                        x-text="form.free_service_item_ids.includes({{ $svcId }}) ? 'Offert' : '{{ number_format((int) ($svc->price / 100), 0, ',', ' ') }} F'"></span>
                                                </label>
                                            @endforeach
                                        </div>

                                        <template x-for="id in form.free_service_item_ids" :key="'hidden-' + id">
                                            <input type="hidden" name="free_service_item_ids[]" :value="id">
                                        </template>
                                    </div>

                                    {{-- Arrangements horaires --}}
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                                        <label class="flex items-center gap-2.5 bg-white border border-secondary/20 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-accent/10 transition-colors">
                                            <input type="hidden" name="late_checkout" :value="form.late_checkout ? 1 : 0">
                                            <input type="checkbox" x-model="form.late_checkout" class="w-4 h-4 rounded border-secondary/40 text-primary">
                                            <span class="text-xs text-primary/80">Départ tardif sans frais</span>
                                        </label>
                                        <label class="flex items-center gap-2.5 bg-white border border-secondary/20 rounded-lg px-3 py-2.5 cursor-pointer hover:bg-accent/10 transition-colors">
                                            <input type="hidden" name="early_checkin" :value="form.early_checkin ? 1 : 0">
                                            <input type="checkbox" x-model="form.early_checkin" class="w-4 h-4 rounded border-secondary/40 text-primary">
                                            <span class="text-xs text-primary/80">Arrivée anticipée sans frais</span>
                                        </label>
                                    </div>
                                </section>

                                {{-- ── Notes ── --}}
                                <section class="bg-white border border-secondary/20 rounded-xl p-4">
                                    <h4 class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                                        <i data-lucide="notebook-pen" class="w-3.5 h-3.5"></i>
                                        Notes internes
                                    </h4>
                                    <textarea name="notes" x-model="form.notes" rows="2" maxlength="2000"
                                        placeholder="Référence de la convention, conditions particulières..."
                                        class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-secondary transition-colors placeholder-primary/30"></textarea>
                                </section>
                            </div>

                            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                                <button type="button" @click="open = false"
                                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                                <button type="submit"
                                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                                    <span x-text="editing ? 'Enregistrer' : 'Créer'"></span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

@if(in_array($tab, ['general', 'hebergement', 'taxes', 'housekeeping', 'restaurant', 'shop']))
    <x-csv-import-modal
        id="modal-import-settings-{{ $tab }}"
        title="Importer les paramètres (CSV)"
        :action="route('settings.import', ['tab' => $tab])"
        :template="route('settings.export', ['tab' => $tab, 'template' => 1])"
        structure="cle_parametre;valeur;type;description"
        submit-label="Importer les paramètres">
        <li><strong>cle_parametre</strong> doit appartenir à la liste autorisée pour cet onglet.</li>
        <li><strong>type</strong> autorisés : <em>string, integer, decimal, boolean, json</em>.</li>
        <li>Les valeurs modifiées mettront à jour la configuration immédiatement après validation.</li>
    </x-csv-import-modal>
@endif

@if($tab === 'services')
    <x-csv-import-modal
        id="modal-import-services"
        title="Importer des prestations (CSV)"
        :action="route('settings.services.import')"
        :template="route('settings.services.export', ['template' => 1])"
        structure="categorie;nom;description;prix_fcfa;duree_minutes;actif"
        submit-label="Importer les prestations">
        <li><strong>nom</strong> obligatoire. L'upsert s'effectue sur le couple <em>(categorie, nom)</em>.</li>
        <li><strong>prix_fcfa</strong> en FCFA entiers.</li>
    </x-csv-import-modal>
@endif

@if($tab === 'partners')
    <x-csv-import-modal
        id="modal-import-partners"
        title="Importer des organisations partenaires (CSV)"
        :action="route('settings.partners.import')"
        :template="route('settings.partners.export', ['template' => 1])"
        structure="nom;code;type;nom_contact;email_contact;telephone_contact;remise_chambre_type;remise_chambre_valeur;remise_restaurant_pct;remise_boutique_pct;depart_tardif;arrivee_anticipee;date_debut;date_fin;actif;notes"
        submit-label="Importer les partenaires">
        <li><strong>nom</strong> obligatoire. <strong>code</strong> unique recommandé (clé d'upsert).</li>
        <li><strong>remise_chambre_type</strong> : <em>none, percent, amount</em>.</li>
    </x-csv-import-modal>
@endif

@if($tab === 'hebergement')
    <x-csv-import-modal
        id="modal-import-packages"
        title="Importer des packs d'hébergement (CSV)"
        :action="route('settings.packages.import')"
        :template="route('settings.packages.export', ['template' => 1])"
        structure="nom;code;description;mode_tarification;prix_fcfa;repas;remise_chambre_type;remise_chambre_valeur;types_chambres;prestations_incluses;actif"
        submit-label="Importer les packs">
        <li><strong>nom</strong> obligatoire. <strong>code</strong> unique recommandé (clé d'upsert).</li>
        <li><strong>repas</strong> séparés par « \| » (ex. <em>breakfast\|dinner</em>).</li>
    </x-csv-import-modal>
@endif

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

    function packCatalog(meals, services, roomTypes, pricingModes) {
        const storeUrl = @js(route('settings.packages.store'));
        const baseUrl = @js(url('/settings/packages'));

        return {
            meals,
            services,
            roomTypes,
            pricingModes,
            open: false,
            editing: false,
            formAction: storeUrl,
            form: {},
            autoCode: true,

            /**
             * Le formulaire doit avoir sa forme complète AVANT qu'Alpine ne lie
             * les cases à cocher.
             *
             * Parti d'un objet vide, le tableau visé par x-model vaut undefined
             * au moment de la liaison : Alpine écrit alors une chaîne vide dans
             * l'attribut value de la case, écrasant celle rendue par le serveur.
             * Toutes les cases se retrouvaient avec la même valeur vide — d'où
             * plusieurs cases qui se cochaient ensemble, et une création refusée
             * sur « The selected meals.0 is invalid ».
             *
             * init() s'exécute avant la liaison des enfants : les tableaux
             * existent, Alpine laisse les valeurs tranquilles.
             */
            init() {
                this.form = this.blank();
            },

            blank() {
                return {
                    id: null,
                    name: '',
                    code: '',
                    description: '',
                    meals: [],
                    service_item_ids: [],
                    pricing_mode: 'per_person_night',
                    price: 0,
                    room_discount_type: 'none',
                    room_discount_value: 0,
                    // Toutes les chambres cochées d'entrée : un pack s'adresse
                    // par défaut à tout le parc, et on décoche ce qu'on exclut.
                    // Plus lisible que l'ancien « rien de coché vaut tout ».
                    room_type_ids: roomTypes.map((t) => t.id),
                    sort_order: 0,
                    is_active: true,
                };
            },

            // Le code suit le nom tant qu'il n'a pas été édité à la main.
            applyAutoCode() { if (this.autoCode) this.form.code = window.suggestCode(this.form.name || ''); },

            // Aperçu du montant réellement facturé : le mode de tarification
            // est la source d'erreur la plus fréquente à la saisie.
            previewAmount(nights, occupants) {
                const price = parseInt(this.form.price) || 0;
                if (this.form.pricing_mode === 'per_person_night') return price * nights * occupants;
                if (this.form.pricing_mode === 'per_room_night') return price * nights;
                return price;
            },

            openCreate() {
                this.form = this.blank();
                this.autoCode = true;
                this.editing = false;
                this.formAction = storeUrl;
                this.open = true;
            },

            openEdit(pack) {
                // Un pack existant sans aucun type enregistré s'applique à tout
                // le parc : on rouvre donc avec toutes les cases cochées, sinon
                // il paraîtrait ne concerner aucune chambre.
                const typesDuPack = (pack.room_type_ids ?? []).length > 0
                    ? pack.room_type_ids
                    : roomTypes.map((t) => t.id);

                this.form = {
                    ...this.blank(),
                    ...pack,
                    code: pack.code ?? '',
                    description: pack.description ?? '',
                    meals: pack.meals ?? [],
                    service_item_ids: pack.service_item_ids ?? [],
                    room_type_ids: typesDuPack,
                };
                this.autoCode = false;
                this.editing = true;
                this.formAction = `${baseUrl}/${pack.id}`;
                this.open = true;
            },
        };
    }

    function partnerCatalog(types, services) {
        const storeUrl = @js(route('settings.partners.store'));
        const baseUrl = @js(url('/settings/partners'));

        return {
            types,
            services,
            open: false,
            editing: false,
            formAction: storeUrl,
            form: {},
            autoCode: true,

            /**
             * Le formulaire doit avoir sa forme complète AVANT qu'Alpine ne lie
             * les cases à cocher.
             *
             * Parti d'un objet vide, le tableau visé par x-model vaut undefined
             * au moment de la liaison : Alpine écrit alors une chaîne vide dans
             * l'attribut value de la case, écrasant celle rendue par le serveur.
             * Toutes les cases se retrouvaient avec la même valeur vide — d'où
             * plusieurs cases qui se cochaient ensemble, et une création refusée
             * sur « The selected meals.0 is invalid ».
             *
             * init() s'exécute avant la liaison des enfants : les tableaux
             * existent, Alpine laisse les valeurs tranquilles.
             */
            init() {
                this.form = this.blank();
            },

            blank() {
                return {
                    id: null,
                    name: '',
                    code: '',
                    type: Object.keys(this.types)[0],
                    contact_name: '',
                    contact_email: '',
                    contact_phone: '',
                    valid_from: '',
                    valid_until: '',
                    is_active: true,
                    room_discount_type: 'none',
                    room_discount_value: 0,
                    restaurant_discount_percent: 0,
                    shop_discount_percent: 0,
                    free_service_item_ids: [],
                    late_checkout: false,
                    early_checkin: false,
                    notes: '',
                };
            },

            // Le code suit le nom tant qu'il n'a pas été édité à la main.
            applyAutoCode() { if (this.autoCode) this.form.code = window.suggestCode(this.form.name || ''); },

            openCreate() {
                this.form = this.blank();
                this.autoCode = true;
                this.editing = false;
                this.formAction = storeUrl;
                this.open = true;
            },

            openEdit(organization) {
                // Les champs date et texte doivent être des chaînes, pas null,
                // sinon Alpine affiche "null" dans les inputs.
                this.form = {
                    ...this.blank(),
                    ...organization,
                    code: organization.code ?? '',
                    contact_name: organization.contact_name ?? '',
                    contact_email: organization.contact_email ?? '',
                    contact_phone: organization.contact_phone ?? '',
                    valid_from: organization.valid_from ?? '',
                    valid_until: organization.valid_until ?? '',
                    notes: organization.notes ?? '',
                    free_service_item_ids: organization.free_service_item_ids ?? [],
                };
                this.autoCode = false;
                this.editing = true;
                this.formAction = `${baseUrl}/${organization.id}`;
                this.open = true;
            },
        };
    }
</script>
@endpush
@endsection
