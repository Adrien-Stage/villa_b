@extends('layouts.hotel')

@section('title', 'Finaliser la réservation')

@php
    // Le libellé d'origine se lit en clair : « walk_in » n'est pas une phrase.
    $sourceLabels = [
        'direct'         => 'Direct',
        'phone'          => 'Téléphone',
        'email'          => 'Email',
        'walk_in'        => 'Walk-in',
        'ota_bookingcom' => 'Booking.com',
        'website'        => 'Site web',
    ];
    $sourceKey   = $source ?: 'direct';
    $sourceLabel = $sourceLabels[$sourceKey] ?? ucfirst(str_replace('_', ' ', $sourceKey));

    $moyensPaiement = [
        'orange_money' => ['Orange Money', 'smartphone'],
        'mtn_momo'     => ['MTN MoMo', 'smartphone'],
        'cash'         => ['Espèces', 'banknote'],
    ];
@endphp

@section('content')
<div class="max-w-6xl mx-auto pb-12">

    {{-- ── En-tête & fil d'étapes ──────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-primary font-heading">Finaliser la réservation</h1>
            <p class="text-sm text-primary/60 mt-1">Étape 3 — Confirmation et paiement d'acompte</p>
        </div>

        <div class="flex items-center gap-2.5">
            @foreach([['Client', true], ['Chambre & dates', true], ['Confirmation', false]] as $i => [$libelle, $fait])
                @if($i > 0)
                    <div class="w-6 h-px bg-primary/15"></div>
                @endif
                <div class="flex items-center gap-2">
                    @if($fait)
                        <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        </div>
                        <span class="text-xs font-medium text-primary/50 hidden sm:inline">{{ $libelle }}</span>
                    @else
                        <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-semibold shadow-sm">3</div>
                        <span class="text-xs font-semibold text-primary hidden sm:inline">{{ $libelle }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl flex items-start gap-2.5">
            <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 flex-shrink-0"></i>
            <ul class="space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Le formulaire enveloppe toute la grille : le récapitulatif de gauche
         affiche des montants vivants, il doit donc partager le périmètre Alpine
         des champs de droite qui les font varier. --}}
    <form method="POST" action="{{ route('bookings.store') }}"
          x-data="paymentCalc({{ $totalRoomAmount }}, {{ $minDepositPercentage }}, @json(Auth::user()->hasRole('reception')), @js($roomPackages ?? []), {{ (int) ($partnerRoomDiscount ?? 0) }}, {{ (int) $nights }})">
        @csrf
        <input type="hidden" name="step" value="4">
        <input type="hidden" name="customer_id" value="{{ $customerId }}">
        <input type="hidden" name="booker_id" value="{{ $bookerId }}">
        <input type="hidden" name="room_id" value="{{ $room->id }}">
        <input type="hidden" name="check_in" value="{{ $checkIn }}">
        <input type="hidden" name="check_out" value="{{ $checkOut }}">
        <input type="hidden" name="check_in_time" value="{{ $checkInTime }}">
        <input type="hidden" name="adults_count" value="{{ $adultsCount }}">
        <input type="hidden" name="children_count" value="{{ $childrenCount }}">
        <input type="hidden" name="source" value="{{ $source }}">
        <input type="hidden" name="notes" value="{{ $notes }}">
        <input type="hidden" name="draft_token" value="{{ $draftToken ?? '' }}">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

            {{-- ── Colonne de gauche : le séjour, et ce qu'on lui accorde ──
                 Trois cartes courtes face aux deux longues de droite : les
                 colonnes s'achèvent à la même hauteur. C'est ce qui règle le
                 vide que laissait un unique encadré en regard d'un long
                 formulaire. --}}
            <div class="lg:col-span-4 space-y-5">

                {{-- Récapitulatif --}}
                <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm overflow-hidden">
                    <header class="px-5 py-3.5 border-b border-secondary/15 flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-accent/40 text-primary flex items-center justify-center flex-shrink-0">
                            <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                        </span>
                        <h2 class="font-heading font-semibold text-primary text-sm">Récapitulatif</h2>
                    </header>

                    <div class="p-5 space-y-3.5">
                        <div class="flex items-start gap-3">
                            <i data-lucide="bed-double" class="w-4 h-4 text-primary/30 mt-0.5 flex-shrink-0"></i>
                            <div class="min-w-0">
                                <p class="text-[10px] text-primary/50 uppercase tracking-wider font-semibold">Hébergement</p>
                                <p class="text-sm font-medium text-primary">Chambre {{ $room->number }} — {{ $room->roomType->name }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <i data-lucide="calendar-days" class="w-4 h-4 text-primary/30 mt-0.5 flex-shrink-0"></i>
                            <div class="min-w-0">
                                <p class="text-[10px] text-primary/50 uppercase tracking-wider font-semibold">Séjour</p>
                                <p class="text-sm font-medium text-primary">
                                    Du {{ \Carbon\Carbon::parse($checkIn)->format('d/m/Y') }}@if(!empty($checkInTime)) à {{ $checkInTime }}@endif
                                </p>
                                <p class="text-sm font-medium text-primary">
                                    au {{ \Carbon\Carbon::parse($checkOut)->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-primary/50 mt-0.5">{{ $nights }} nuit{{ $nights > 1 ? 's' : '' }}</p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <i data-lucide="users" class="w-4 h-4 text-primary/30 mt-0.5 flex-shrink-0"></i>
                            <div class="min-w-0">
                                <p class="text-[10px] text-primary/50 uppercase tracking-wider font-semibold">Occupants</p>
                                <p class="text-sm font-medium text-primary">
                                    {{ $adultsCount }} adulte{{ $adultsCount > 1 ? 's' : '' }}@if($childrenCount > 0), {{ $childrenCount }} enfant{{ $childrenCount > 1 ? 's' : '' }}@endif
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <i data-lucide="route" class="w-4 h-4 text-primary/30 mt-0.5 flex-shrink-0"></i>
                            <div class="min-w-0">
                                <p class="text-[10px] text-primary/50 uppercase tracking-wider font-semibold">Origine</p>
                                <p class="text-sm font-medium text-primary">{{ $sourceLabel }}</p>
                            </div>
                        </div>

                        @if($bookerId)
                            <div class="flex items-start gap-3">
                                <i data-lucide="user-check" class="w-4 h-4 text-primary/30 mt-0.5 flex-shrink-0"></i>
                                <div class="min-w-0">
                                    <p class="text-[10px] text-primary/50 uppercase tracking-wider font-semibold">Mandataire</p>
                                    <p class="text-sm font-medium text-primary">Réservé par une tierce personne</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Remise et Offre --}}
                <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm overflow-hidden flex flex-col">
                    <header class="px-5 py-3.5 border-b border-secondary/15 flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-accent/40 text-primary flex items-center justify-center flex-shrink-0">
                            <i data-lucide="percent" class="w-4 h-4"></i>
                        </span>
                        <h2 class="font-heading font-semibold text-primary text-sm">Remise et Offre</h2>
                    </header>

                    <div class="p-5 flex flex-col flex-1 gap-4">
                        <div>
                            <label for="remise" class="block text-[10px] font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                                Remise autorisée
                            </label>
                            <select id="remise" x-model="selectedDiscount" @change="applyDiscount()" :disabled="isOfferte"
                                    class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-xl text-primary outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition disabled:bg-slate-100 disabled:text-primary/40 disabled:cursor-not-allowed">
                                <option value="0">Aucune remise (0 %)</option>
                                @for($i = 5; $i <= $maxDiscountPercentage; $i += 5)
                                    <option value="{{ $i }}">{{ $i }} %</option>
                                @endfor
                                @if($maxDiscountPercentage % 5 !== 0)
                                    <option value="{{ $maxDiscountPercentage }}">{{ $maxDiscountPercentage }} % (maximum)</option>
                                @endif
                            </select>
                        </div>

                        {{-- Effet de la remise, rendu visible tout de suite. --}}
                        <div x-show="selectedDiscount > 0 && !isOfferte" style="display:none;"
                             class="px-3.5 py-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">−<span x-text="selectedDiscount"></span> %</span>
                                <span class="text-xs text-primary/40 line-through tabular-nums"><span x-text="formatMoney(baseTotal)"></span> FCFA</span>
                            </div>
                            <p class="text-sm font-semibold text-emerald-900 mt-1.5 tabular-nums">
                                Nouveau prix : <span x-text="formatMoney(customPrice)"></span> FCFA
                            </p>
                            <p class="text-[11px] text-emerald-700 mt-0.5 tabular-nums">
                                Économie client : <span x-text="formatMoney(baseTotal - customPrice)"></span> FCFA
                            </p>
                        </div>

                        <label for="is_offerte"
                               class="flex items-start gap-2.5 px-3.5 py-3 rounded-xl border cursor-pointer transition-colors"
                               :class="isOfferte ? 'border-amber-300 bg-amber-50' : 'border-secondary/25 hover:bg-accent/10'">
                            <input type="checkbox" id="is_offerte" name="is_offerte" value="1"
                                   x-model="isOfferte" @change="toggleOfferte()"
                                   class="mt-0.5 rounded border-secondary/30 text-primary focus:ring-primary h-4 w-4">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-primary">Chambre offerte (complimentary)</span>
                                <span class="block text-[11px] text-primary/50 mt-0.5">Le séjour n'est ni facturé ni encaissé.</span>
                            </span>
                        </label>

                        <div x-show="isOfferte" style="display:none;">
                            <label for="offerte_reason" class="block text-[10px] font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                                Motif de la chambre offerte *
                            </label>
                            <textarea id="offerte_reason" name="offerte_reason" :required="isOfferte" rows="3"
                                      placeholder="Invitation direction, geste commercial…"
                                      class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-xl text-primary outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition resize-none"></textarea>
                        </div>

                        <div x-show="isOfferte && isReceptionist" style="display:none;"
                             class="px-3.5 py-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl flex items-start gap-2">
                            <i data-lucide="info" class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-600"></i>
                            <p>
                                <strong>Demande d'autorisation :</strong> une chambre offerte par un réceptionniste
                                part au manager. La réservation sera enregistrée « <strong>En attente</strong> ».
                            </p>
                        </div>

                        {{-- Pied de carte : il occupe le creux quand aucune remise
                             n'est appliquée, et rappelle ce qui est tracé. --}}
                        <p class="mt-auto pt-3 border-t border-secondary/10 text-[11px] text-primary/40 flex items-start gap-1.5">
                            <i data-lucide="shield-check" class="w-3.5 h-3.5 flex-shrink-0 mt-px"></i>
                            <span>Remises et gestes commerciaux sont journalisés au nom de {{ Auth::user()->name }}.</span>
                        </p>
                    </div>
                </section>

                {{-- Décompte vivant : chaque geste du formulaire se lit ici. --}}
                <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm overflow-hidden"
                         :class="isOfferte ? 'ring-1 ring-amber-300' : ''">
                    <header class="px-5 py-3.5 border-b border-secondary/15 flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-accent/40 text-primary flex items-center justify-center flex-shrink-0">
                            <i data-lucide="receipt-text" class="w-4 h-4"></i>
                        </span>
                        <h2 class="font-heading font-semibold text-primary text-sm">Décompte du séjour</h2>
                    </header>

                    <div class="p-5">
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-primary/60">Hébergement négocié</span>
                                <span class="font-medium text-primary tabular-nums" x-text="formatMoney(parseInt(customPrice) || 0) + ' FCFA'"></span>
                            </div>

                            <div class="flex items-center justify-between gap-3" x-show="packageAmount > 0" style="display:none;">
                                <span class="text-primary/60 min-w-0 truncate">
                                    Formule <span class="text-primary/40" x-text="selectedPackageName"></span>
                                </span>
                                <span class="font-medium text-primary tabular-nums flex-shrink-0" x-text="'+ ' + formatMoney(packageAmount) + ' FCFA'"></span>
                            </div>

                            <div class="flex items-center justify-between gap-3" x-show="packageDiscount > 0" style="display:none;">
                                <span class="text-emerald-700">Remise incluse dans la formule</span>
                                <span class="font-medium text-emerald-700 tabular-nums" x-text="'− ' + formatMoney(packageDiscount) + ' FCFA'"></span>
                            </div>

                            <div class="flex items-center justify-between gap-3" x-show="partnerDiscount > 0" style="display:none;">
                                <span class="text-emerald-700">Remise partenaire</span>
                                <span class="font-medium text-emerald-700 tabular-nums" x-text="'− ' + formatMoney(partnerDiscount) + ' FCFA'"></span>
                            </div>
                        </div>

                        <div class="mt-3 pt-3 border-t border-secondary/20">
                            <p class="text-sm font-semibold text-primary">Total dû pour le séjour</p>
                            <p class="text-2xl font-bold text-primary font-heading tabular-nums mt-0.5" x-text="formatMoney(netTotal) + ' FCFA'"></p>
                        </div>

                        {{-- Progression du règlement : l'acompte se voit avant d'être saisi. --}}
                        <div class="mt-4" x-show="!isOfferte" style="display:none;">
                            <div class="h-1.5 w-full rounded-full bg-secondary/15 overflow-hidden">
                                <div class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                                     :style="`width: ${netTotal > 0 ? Math.min(100, Math.round(((parseInt(paymentAmount) || 0) / netTotal) * 100)) : 0}%`"></div>
                            </div>
                            <div class="mt-2.5 space-y-1.5 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-primary/60">Acompte versé</span>
                                    <span class="font-semibold text-emerald-700 tabular-nums" x-text="formatMoney(parseInt(paymentAmount) || 0) + ' FCFA'"></span>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-primary/60">Solde à l'arrivée</span>
                                    <span class="font-semibold text-primary tabular-nums" x-text="formatMoney(balanceDue) + ' FCFA'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 px-3 py-2.5 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-2"
                             x-show="isOfferte" style="display:none;">
                            <i data-lucide="gift" class="w-4 h-4 text-amber-600 mt-0.5 flex-shrink-0"></i>
                            <p class="text-xs text-amber-800 leading-relaxed">
                                Séjour <strong>offert</strong> — aucun montant n'est facturé ni encaissé.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            {{-- ── Colonne principale ──────────────────────────────────────────── --}}
            <div class="lg:col-span-8 space-y-5">

                {{-- Tarification du séjour --}}
                <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm overflow-hidden">
                    <header class="px-5 py-3.5 border-b border-secondary/15 flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-accent/40 text-primary flex items-center justify-center flex-shrink-0">
                            <i data-lucide="banknote" class="w-4 h-4"></i>
                        </span>
                        <h2 class="font-heading font-semibold text-primary text-sm">Tarification du séjour</h2>
                    </header>

                    <div class="p-5 space-y-5">

                        {{-- Tarif de référence --}}
                        <div class="flex items-center justify-between gap-4 px-4 py-3 rounded-xl bg-slate-50 border border-secondary/15">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-primary">
                                    Prix de base — {{ $nights }} nuit{{ $nights > 1 ? 's' : '' }} × {{ number_format($pricePerNight, 0, ',', ' ') }} FCFA
                                </p>
                                <p class="text-xs text-primary/50 mt-0.5">Taxes incluses</p>
                            </div>
                            <p class="font-semibold text-primary tabular-nums flex-shrink-0">{{ number_format($totalRoomAmount, 0, ',', ' ') }} FCFA</p>
                        </div>

                        {{-- Prix négocié --}}
                        <div>
                            <label for="custom_price" class="block text-sm font-semibold text-primary mb-1">Prix total négocié (TTC) *</label>
                            <p class="text-xs text-primary/60 mb-2" x-show="!isReceptionist">
                                Ajustable si un tarif spécial a été accordé.
                            </p>
                            <p class="text-xs text-primary/60 mb-2" x-show="isReceptionist" style="display:none;">
                                Modifiable uniquement via la remise autorisée, ou par le manager.
                            </p>
                            {{-- « readonly » et non « disabled » : un champ désactivé n'est pas
                                 envoyé, et le serveur exige custom_price — y compris pour une
                                 chambre offerte, où il vaut alors zéro. --}}
                            <div class="relative max-w-xs">
                                <input type="number" id="custom_price" name="custom_price"
                                       x-model="customPrice" @input="updateCalculations()"
                                       :readonly="isReceptionist || isOfferte"
                                       :class="(isReceptionist || isOfferte) ? 'bg-slate-100 text-primary/50 cursor-not-allowed' : 'bg-white'"
                                       min="0" required
                                       class="w-full pl-3 pr-14 py-2.5 text-sm font-semibold border border-secondary/30 rounded-xl text-primary outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition tabular-nums">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-primary/40 font-medium pointer-events-none">FCFA</span>
                            </div>
                        </div>

                        {{-- Formules d'hébergement, facturées en supplément de la nuitée. --}}
                        @if(!empty($roomPackages))
                            <div class="pt-4 border-t border-secondary/15">
                                <p class="text-sm font-semibold text-primary">Formule d'hébergement</p>
                                <p class="text-xs text-primary/60 mt-0.5 mb-3">Facturée en supplément de la nuitée pour ce séjour.</p>

                                <div class="space-y-2">
                                    <label class="flex items-center gap-3 border rounded-xl px-3.5 py-3 cursor-pointer transition-colors"
                                           :class="selectedPackage === '' ? 'border-secondary bg-accent/20 ring-1 ring-secondary/30' : 'border-secondary/25 hover:bg-accent/10'">
                                        <input type="radio" name="room_package_id" value="" x-model="selectedPackage" @change="syncDeposit()" class="w-4 h-4 text-primary">
                                        <span class="text-sm text-primary">Aucune formule — chambre seule</span>
                                    </label>

                                    <template x-for="pack in packs" :key="pack.id">
                                        <label class="flex items-start gap-3 border rounded-xl px-3.5 py-3 cursor-pointer transition-colors"
                                               :class="selectedPackage == pack.id ? 'border-secondary bg-accent/20 ring-1 ring-secondary/30' : 'border-secondary/25 hover:bg-accent/10'">
                                            <input type="radio" name="room_package_id" :value="pack.id" x-model="selectedPackage" @change="syncDeposit()" class="mt-0.5 w-4 h-4 text-primary">
                                            <span class="flex-1 min-w-0">
                                                <span class="flex items-baseline justify-between gap-3">
                                                    <span class="text-sm font-medium text-primary" x-text="pack.name"></span>
                                                    <span class="text-sm font-bold text-primary shrink-0 tabular-nums"
                                                          x-text="'+ ' + new Intl.NumberFormat('fr-FR').format(pack.amount) + ' FCFA'"></span>
                                                </span>
                                                <span class="block text-[11px] text-primary/40" x-text="pack.mode"></span>
                                                <span class="flex flex-wrap gap-1 mt-1.5">
                                                    <template x-for="content in pack.contents" :key="content">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-white text-primary border border-secondary/20"
                                                              x-text="content"></span>
                                                    </template>
                                                </span>
                                                <span class="block text-[11px] text-emerald-700 font-medium mt-1"
                                                      x-show="pack.room_discount > 0"
                                                      x-text="'Inclut une remise de ' + new Intl.NumberFormat('fr-FR').format(pack.room_discount) + ' FCFA sur la nuitée'"></span>
                                            </span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        @endif

                        {{-- Convention partenaire : la remise s'applique sur le prix
                             négocié et apparaît en ligne distincte sur le folio. --}}
                        @if($partnerOrganization)
                            <div class="pt-4 border-t border-secondary/15">
                                {{-- applyPartner vit dans le composant principal : dans son
                                     propre x-data, décocher la convention ne touchait ni le
                                     total ni l'acompte affichés, alors que le serveur, lui,
                                     cessait bien d'appliquer la remise. --}}
                                <div class="p-3.5 rounded-xl border border-secondary/25 bg-accent/20">
                                    <label class="flex items-start gap-2.5 cursor-pointer">
                                        <input type="hidden" name="apply_partner_privileges" :value="applyPartner ? 1 : 0">
                                        <input type="checkbox" x-model="applyPartner" @change="syncDeposit()"
                                               class="mt-0.5 rounded border-secondary/30 text-primary focus:ring-primary">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-primary">
                                                <i data-lucide="handshake" class="w-3.5 h-3.5 inline -mt-0.5"></i>
                                                Membre de « {{ $partnerOrganization->name }} »
                                            </span>
                                            <span class="block text-xs text-primary/60 mt-0.5">
                                                @if($partnerRoomDiscount > 0)
                                                    Remise partenaire de <strong>{{ number_format($partnerRoomDiscount, 0, ',', ' ') }} FCFA</strong>
                                                    ({{ $partnerOrganization->roomDiscountLabel() }}) déduite du prix négocié.
                                                @else
                                                    Aucune remise sur l'hébergement, mais les autres privilèges s'appliquent au séjour.
                                                @endif
                                            </span>
                                            @if(count($partnerOrganization->privilegeLabels()))
                                                <span class="flex flex-wrap gap-1 mt-1.5">
                                                    @foreach($partnerOrganization->privilegeLabels() as $privilege)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-white text-primary border border-secondary/20">{{ $privilege }}</span>
                                                    @endforeach
                                                </span>
                                            @endif
                                            <span class="block text-[10px] text-primary/40 mt-1.5">
                                                Décochez si ce séjour est privé et ne relève pas de la convention.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- Paiement de l'acompte --}}
                <section class="rounded-2xl border shadow-sm overflow-hidden flex flex-col transition-colors"
                         :class="isOfferte ? 'border-amber-300 bg-amber-50/40' : 'border-primary/25 bg-white'">
                    <header class="px-5 py-3.5 border-b flex items-center gap-2.5"
                            :class="isOfferte ? 'border-amber-200' : 'border-secondary/15'">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                              :class="isOfferte ? 'bg-amber-100 text-amber-700' : 'bg-primary/10 text-primary'">
                            <i data-lucide="wallet" class="w-4 h-4"></i>
                        </span>
                        <h2 class="font-heading font-semibold text-primary text-sm">Paiement de l'acompte</h2>
                        <span class="ml-auto px-2 py-0.5 text-[10px] uppercase font-bold rounded-full tracking-wider"
                              :class="isOfferte ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'"
                              x-text="isOfferte ? 'Sans objet' : 'Obligatoire'"></span>
                    </header>

                    <div class="p-5 flex flex-col flex-1 gap-4">

                        {{-- État « chambre offerte » : la carte garde sa place et son
                             sens, au lieu de disparaître et de laisser un trou. --}}
                        <div x-show="isOfferte" style="display:none;" class="flex-1 flex flex-col items-center justify-center text-center py-4">
                            <span class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center mb-3">
                                <i data-lucide="gift" class="w-6 h-6"></i>
                            </span>
                            <p class="text-sm font-semibold text-primary">Aucun acompte à encaisser</p>
                            <p class="text-xs text-primary/50 mt-1 max-w-[16rem]">
                                Ce séjour est offert. Renseignez le motif dans la carte « Remise et Offre ».
                            </p>
                        </div>

                        <div x-show="!isOfferte" class="space-y-4">
                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl bg-slate-50 border border-secondary/15">
                                <p class="text-[11px] font-semibold text-primary/60 uppercase tracking-wide">
                                    Minimum exigé (<span x-text="minPercentage"></span> %)
                                </p>
                                <p class="text-sm font-bold text-primary tabular-nums" x-text="formatMoney(minDeposit) + ' FCFA'"></p>
                            </div>

                            <div>
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <label for="payment_amount" class="text-sm font-semibold text-primary">Montant versé *</label>
                                    {{-- Raccourcis : à la réception, on encaisse presque
                                         toujours l'un de ces trois montants. --}}
                                    <div class="flex items-center gap-1">
                                        <button type="button" @click="setPayment('min')"
                                                class="px-2 py-1 text-[10px] font-semibold rounded-lg border border-secondary/25 text-primary/70 hover:bg-accent/20 hover:text-primary transition-colors">Minimum</button>
                                        <button type="button" @click="setPayment('half')"
                                                class="px-2 py-1 text-[10px] font-semibold rounded-lg border border-secondary/25 text-primary/70 hover:bg-accent/20 hover:text-primary transition-colors">50 %</button>
                                        <button type="button" @click="setPayment('full')"
                                                class="px-2 py-1 text-[10px] font-semibold rounded-lg border border-secondary/25 text-primary/70 hover:bg-accent/20 hover:text-primary transition-colors">Total</button>
                                    </div>
                                </div>
                                <div class="relative">
                                    {{-- Borné au total dû, formule comprise : borner au seul
                                         prix négocié empêcherait de régler l'intégralité
                                         d'un séjour avec formule. --}}
                                    <input type="number" id="payment_amount" name="payment_amount"
                                           x-model="paymentAmount" @input="updateCalculations()"
                                           :min="minDeposit" :max="netTotal" :required="!isOfferte"
                                           class="w-full pl-3 pr-14 py-2.5 text-lg font-bold border border-secondary/30 rounded-xl text-primary outline-none focus:border-primary focus:ring-2 focus:ring-primary/15 transition tabular-nums">
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-primary/40 font-medium pointer-events-none">FCFA</span>
                                </div>
                                <p class="text-xs text-red-600 mt-1 flex items-center gap-1" x-show="paymentAmount < minDeposit" style="display:none;">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                    Au moins l'acompte minimum est requis.
                                </p>
                                <p class="text-xs text-red-600 mt-1 flex items-center gap-1" x-show="paymentAmount > netTotal" style="display:none;">
                                    <i data-lucide="alert-circle" class="w-3 h-3"></i>
                                    Le montant dépasse le total dû.
                                </p>
                            </div>

                            <div>
                                <p class="block text-[10px] font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Moyen de paiement *</p>
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach($moyensPaiement as $valeur => [$libelle, $icone])
                                        <label class="flex flex-col items-center gap-1 px-2 py-2.5 rounded-xl border cursor-pointer transition-colors text-center"
                                               :class="paymentMethod === '{{ $valeur }}' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-secondary/25 hover:bg-accent/10'">
                                            <input type="radio" name="payment_method" value="{{ $valeur }}" x-model="paymentMethod" class="sr-only">
                                            <i data-lucide="{{ $icone }}" class="w-4 h-4 text-primary/60"></i>
                                            <span class="text-[11px] font-medium text-primary leading-tight">{{ $libelle }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <div x-show="paymentMethod !== 'cash'" style="display:none;">
                                <label for="payment_reference" class="block text-[10px] font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                                    Référence de transaction *
                                </label>
                                <input type="text" id="payment_reference" name="payment_reference"
                                       :required="!isOfferte && paymentMethod !== 'cash'"
                                       placeholder="N° de transaction ou de reçu…"
                                       class="w-full px-3 py-2.5 text-sm border border-secondary/30 rounded-xl text-primary outline-none focus:border-secondary focus:ring-2 focus:ring-secondary/20 transition">
                            </div>

                            <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl bg-slate-100">
                                <span class="text-xs font-semibold text-primary/70">Solde restant à l'arrivée</span>
                                <span class="text-base font-bold text-primary tabular-nums" x-text="formatMoney(balanceDue) + ' FCFA'"></span>
                            </div>
                        </div>

                        {{-- Pied collé en bas : les deux cartes se terminent au même
                             niveau, l'action reste au même endroit dans tous les cas. --}}
                        <div class="mt-auto pt-4">
                            <button type="submit"
                                    :disabled="!isOfferte && (paymentAmount < minDeposit || paymentAmount > netTotal)"
                                    class="w-full px-5 py-3 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500/40 transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                                <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                                <span x-text="isOfferte ? 'Confirmer la réservation offerte' : 'Confirmer et Payer l\'acompte'"></span>
                            </button>
                        </div>
                    </div>
                </section>

            </div>
        </div>

        <div class="mt-5 flex items-center justify-between gap-3">
            <button type="submit" name="action_back" value="1" formnovalidate
                    class="px-4 py-2.5 bg-white border border-secondary/30 text-primary text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors shadow-sm flex items-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                Précédent
            </button>
            <p class="text-[11px] text-primary/40 text-right">
                La réservation et l'encaissement sont enregistrés ensemble.
            </p>
        </div>
    </form>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('paymentCalc', (baseTotal, minPct, isReceptionist, packs, partnerDiscount, nights) => ({
            baseTotal: baseTotal,
            customPrice: baseTotal,
            minPercentage: minPct,
            isReceptionist: isReceptionist,
            selectedDiscount: 0,
            isOfferte: false,
            previousPrice: baseTotal,
            minDeposit: 0,
            paymentAmount: 0,
            balanceDue: 0,
            paymentMethod: 'orange_money',

            // Formules d'hébergement : le montant dû n'est plus le seul prix
            // négocié, il faut y ajouter la formule et en déduire les remises —
            // exactement ce que fait BookingController::store à l'enregistrement.
            packs: packs || [],
            nights: nights || 1,
            selectedPackage: '',
            packageAmount: 0,
            packageDiscount: 0,
            // La convention se décoche pour un séjour privé : on garde le barème
            // d'origine à part, pour pouvoir le réappliquer.
            partnerDiscountBase: partnerDiscount || 0,
            applyPartner: true,
            partnerDiscount: partnerDiscount || 0,
            selectedPackageName: '',
            netTotal: baseTotal,

            init() {
                this.updateCalculations();
                this.paymentAmount = this.minDeposit; // Par défaut, on pré-remplit l'acompte min
                this.updateCalculations();
            },

            /** Formule retenue, ou null si le client reste en chambre seule. */
            currentPack() {
                if (this.selectedPackage === '' || this.selectedPackage === null) return null;

                return this.packs.find((p) => String(p.id) === String(this.selectedPackage)) || null;
            },

            updateCalculations() {
                const prix = parseInt(this.customPrice) || 0;
                const pack = this.currentPack();

                // Une chambre offerte ne facture ni nuitée ni formule.
                if (this.isOfferte || !pack) {
                    this.packageAmount = 0;
                    this.packageDiscount = 0;
                    this.selectedPackageName = '';
                } else {
                    this.packageAmount = pack.amount || 0;
                    this.selectedPackageName = pack.name || '';

                    // Recalculée sur le prix négocié, et non sur le tarif de base :
                    // un pourcentage suivrait sinon une assiette que la réception
                    // vient peut-être de modifier, et divergerait du serveur.
                    if (pack.discount_type === 'percent') {
                        this.packageDiscount = Math.round(prix * Math.min(100, pack.discount_value || 0) / 100);
                    } else if (pack.discount_type === 'amount') {
                        this.packageDiscount = (pack.discount_value || 0) * Math.max(1, this.nights);
                    } else {
                        this.packageDiscount = 0;
                    }
                    this.packageDiscount = Math.max(0, Math.min(this.packageDiscount, prix));
                }

                // Convention écartée ou séjour offert : plus de remise partenaire.
                this.partnerDiscount = (this.isOfferte || !this.applyPartner) ? 0 : this.partnerDiscountBase;

                const remises = this.isOfferte ? 0 : (this.partnerDiscount + this.packageDiscount);
                this.netTotal = this.isOfferte ? 0 : Math.max(0, prix + this.packageAmount - remises);

                if (this.isOfferte) {
                    this.minDeposit = 0;
                    this.balanceDue = 0;
                } else {
                    // L'acompte porte sur le montant réellement dû : l'asseoir sur
                    // le brut ferait payer au client une part qu'il ne doit pas.
                    this.minDeposit = Math.ceil(this.netTotal * (this.minPercentage / 100));
                    let paid = parseInt(this.paymentAmount) || 0;
                    this.balanceDue = Math.max(0, this.netTotal - paid);
                }
            },

            /**
             * Recalcule, puis remonte l'acompte au minimum s'il est passé
             * dessous. Réservé aux gestes discrets — formule, convention : sur
             * une saisie au clavier, corriger le montant à chaque frappe
             * empêcherait d'écrire quoi que ce soit.
             */
            syncDeposit() {
                this.updateCalculations();

                if (!this.isOfferte && (parseInt(this.paymentAmount) || 0) < this.minDeposit) {
                    this.paymentAmount = this.minDeposit;
                    this.updateCalculations();
                }
            },

            /** Raccourcis d'encaissement de la réception. */
            setPayment(kind) {
                if (this.isOfferte) return;

                if (kind === 'min') {
                    this.paymentAmount = this.minDeposit;
                } else if (kind === 'half') {
                    this.paymentAmount = Math.max(this.minDeposit, Math.ceil(this.netTotal / 2));
                } else {
                    this.paymentAmount = this.netTotal;
                }

                this.updateCalculations();
            },

            applyDiscount() {
                let discountPct = parseInt(this.selectedDiscount) || 0;
                if (this.isOfferte) return;
                this.customPrice = Math.round(this.baseTotal * (1 - discountPct / 100));
                this.updateCalculations();
                if (this.paymentAmount < this.minDeposit) {
                    this.paymentAmount = this.minDeposit;
                    this.updateCalculations();
                }
            },

            toggleOfferte() {
                if (this.isOfferte) {
                    this.previousPrice = this.customPrice;
                    this.customPrice = 0;
                    this.paymentAmount = 0;
                } else {
                    this.customPrice = this.previousPrice || this.baseTotal;
                    this.paymentAmount = this.minDeposit;
                }
                this.updateCalculations();

                if (!this.isOfferte) {
                    this.paymentAmount = this.minDeposit;
                    this.updateCalculations();
                }
            },

            formatMoney(amount) {
                return (parseInt(amount) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
            }
        }))
    })
</script>
@endsection
