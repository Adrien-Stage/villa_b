@extends('layouts.hotel')

@section('title', 'Finaliser la réservation')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-primary font-heading">Finaliser la réservation</h1>
            <p class="text-sm text-primary/60 mt-1">Vérifiez les détails et enregistrez l'acompte obligatoire.</p>
        </div>
        
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-primary/20 text-primary flex items-center justify-center text-sm font-bold">1</div>
            <div class="w-8 h-px bg-primary/20"></div>
            <div class="w-8 h-8 rounded-full bg-primary/20 text-primary flex items-center justify-center text-sm font-bold">2</div>
            <div class="w-8 h-px bg-primary/20"></div>
            <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm font-bold shadow-sm">3</div>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        {{-- Résumé de la réservation --}}
        <div class="lg:col-span-1 space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-secondary/20 p-5">
                <h3 class="font-semibold text-primary mb-4 border-b border-secondary/20 pb-2">Récapitulatif</h3>
                
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-xs text-primary/50 uppercase tracking-wider font-semibold">Chambre</p>
                        <p class="font-medium text-primary">{{ $room->number }} - {{ $room->roomType->name }}</p>
                    </div>
                    
                    <div>
                        <p class="text-xs text-primary/50 uppercase tracking-wider font-semibold">Séjour</p>
                        <p class="font-medium text-primary">Du {{ \Carbon\Carbon::parse($checkIn)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($checkOut)->format('d/m/Y') }}</p>
                        <p class="text-xs text-primary/70">{{ $nights }} nuit(s)</p>
                    </div>
                    
                    <div>
                        <p class="text-xs text-primary/50 uppercase tracking-wider font-semibold">Occupants</p>
                        <p class="font-medium text-primary">{{ $adultsCount }} Adulte(s), {{ $childrenCount }} Enfant(s)</p>
                    </div>

                    @if($bookerId)
                        <div>
                            <p class="text-xs text-primary/50 uppercase tracking-wider font-semibold">Mandataire</p>
                            <p class="font-medium text-primary">Oui (Tierce personne)</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Formulaire de finalisation --}}
        <div class="lg:col-span-2">
            <form method="POST" action="{{ route('bookings.store') }}" class="bg-white rounded-xl shadow-sm border border-secondary/20 p-6">
                @csrf
                <input type="hidden" name="step" value="4">
                <input type="hidden" name="customer_id" value="{{ $customerId }}">
                <input type="hidden" name="booker_id" value="{{ $bookerId }}">
                <input type="hidden" name="room_id" value="{{ $room->id }}">
                <input type="hidden" name="check_in" value="{{ $checkIn }}">
                <input type="hidden" name="check_out" value="{{ $checkOut }}">
                <input type="hidden" name="adults_count" value="{{ $adultsCount }}">
                <input type="hidden" name="children_count" value="{{ $childrenCount }}">
                <input type="hidden" name="source" value="{{ $source }}">
                <input type="hidden" name="notes" value="{{ $notes }}">

                <div x-data="paymentCalc({{ $totalRoomAmount }}, {{ $minDepositPercentage }}, @json(Auth::user()->hasRole('reception')))" class="space-y-6">
                    
                    {{-- Section Tarification --}}
                    <div>
                        <h3 class="font-semibold text-primary mb-3">Tarification du séjour</h3>
                        <div class="p-4 bg-gray-50 rounded-lg border border-secondary/20">
                            <div class="flex items-center justify-between mb-4 pb-4 border-b border-secondary/20">
                                <div>
                                    <p class="text-sm font-medium text-primary">Prix de base ({{ $nights }} nuits x {{ number_format($pricePerNight, 0, ',', ' ') }} FCFA)</p>
                                    <p class="text-xs text-primary/60">Taxes incluses</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-primary">{{ number_format($totalRoomAmount, 0, ',', ' ') }} FCFA</p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-semibold text-primary mb-1">Prix total négocié (TTC) *</label>
                                <p class="text-xs text-primary/60 mb-2" x-show="!isReceptionist">Vous pouvez ajuster le prix total du séjour si un tarif spécial a été accordé.</p>
                                <p class="text-xs text-primary/60 mb-2" x-show="isReceptionist">Le prix négocié est modifiable uniquement via la remise ou par le manager.</p>
                                <div class="relative w-1/2">
                                    <input type="number" name="custom_price" :disabled="isReceptionist || isOfferte" x-model="customPrice" @input="updateCalculations()" min="0" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary pr-12 disabled:bg-gray-100 disabled:text-primary/50">
                                    <span class="absolute right-3 top-2.5 text-xs text-primary/50 font-medium">FCFA</span>
                                </div>
                                <template x-if="isReceptionist">
                                    <input type="hidden" name="custom_price" x-model="customPrice">
                                </template>
                            </div>

                            {{-- Remise Autorisée (Dropdown) --}}
                            <div class="mt-4">
                                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                                    Remise autorisée
                                </label>
                                <select x-model="selectedDiscount" @change="applyDiscount()" :disabled="isOfferte" class="w-1/2 px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary disabled:bg-gray-100">
                                    <option value="0">Aucune remise (0%)</option>
                                    @for($i = 5; $i <= $maxDiscountPercentage; $i += 5)
                                        <option value="{{ $i }}">{{ $i }}%</option>
                                    @endfor
                                    @if($maxDiscountPercentage % 5 !== 0)
                                        <option value="{{ $maxDiscountPercentage }}">{{ $maxDiscountPercentage }}% (Maximum)</option>
                                    @endif
                                </select>
                            </div>

                            {{-- Visual recap when discount is applied --}}
                            <div x-show="selectedDiscount > 0 && !isOfferte" class="mt-4 p-3.5 bg-emerald-50 border border-emerald-200/50 rounded-xl flex items-center justify-between" style="display: none;">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700">
                                        <i data-lucide="percent" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">-<span x-text="selectedDiscount"></span>%</span>
                                            <span class="text-xs text-primary/50 line-through"><span x-text="formatMoney(baseTotal)"></span> FCFA</span>
                                        </div>
                                        <p class="text-sm font-semibold text-emerald-800 mt-0.5">
                                            Nouveau prix : <span class="text-base font-bold text-primary" x-text="formatMoney(customPrice)"></span> FCFA
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right text-xs text-emerald-700 font-medium">
                                    Économie : <span x-text="formatMoney(baseTotal - customPrice)"></span> FCFA
                                </div>
                            </div>

                            {{-- Chambre Offerte Option --}}
                            <div class="mt-4 flex items-center gap-2">
                                <input type="checkbox" id="is_offerte" name="is_offerte" value="1" x-model="isOfferte" @change="toggleOfferte()" class="rounded border-secondary/30 text-primary focus:ring-primary h-4 w-4">
                                <label for="is_offerte" class="text-sm font-medium text-primary">Marquer cette chambre comme "Offerte" (Complimentary)</label>
                            </div>

                            {{-- Motif chambre offerte --}}
                            <div x-show="isOfferte" class="mt-4" style="display: none;">
                                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                                    Motif de la chambre offerte *
                                </label>
                                <textarea name="offerte_reason" :required="isOfferte" placeholder="Veuillez justifier pourquoi cette chambre est offerte (Ex: Invitation direction, geste commercial...)" rows="3" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary resize-none"></textarea>
                            </div>

                            {{-- Alerte manager pour réceptionniste --}}
                            <div x-show="isOfferte && isReceptionist" class="mt-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg flex items-start gap-2" style="display: none;">
                                <i data-lucide="info" class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-600"></i>
                                <div>
                                    <span class="font-bold">Demande d'autorisation :</span> Cette chambre étant offerte par un réceptionniste, une demande d'autorisation sera soumise au manager. La réservation sera enregistrée au statut <strong class="underline">"En attente"</strong>.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section Acompte Obligatoire --}}
                    <div x-show="!isOfferte">
                        <h3 class="font-semibold text-primary mb-3 flex items-center gap-2">
                            Paiement de l'acompte
                            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] uppercase font-bold rounded-full tracking-wider">Obligatoire</span>
                        </h3>
                        <div class="p-4 border border-primary/20 bg-primary/5 rounded-lg">
                            
                            <div class="mb-4 flex items-center justify-between bg-white p-3 rounded shadow-sm border border-secondary/10">
                                <div>
                                    <p class="text-xs font-semibold text-primary/60 uppercase">Acompte minimum exigé selon les paramètres (<span x-text="minPercentage"></span>%)</p>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-primary" x-text="formatMoney(minDeposit) + ' FCFA'"></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-primary mb-1">Montant versé *</label>
                                    <div class="relative">
                                        <input type="number" name="payment_amount" x-model="paymentAmount" @input="updateCalculations()" :min="minDeposit" :max="customPrice" :required="!isOfferte" class="w-full px-3 py-2 text-lg font-bold border border-secondary/30 rounded-lg text-primary outline-none focus:border-primary pr-12">
                                        <span class="absolute right-3 top-3 text-sm text-primary/50 font-medium">FCFA</span>
                                    </div>
                                    <p class="text-xs text-red-500 mt-1" x-show="paymentAmount < minDeposit">Le montant doit être au moins égal à l'acompte minimum.</p>
                                    <p class="text-xs text-red-500 mt-1" x-show="paymentAmount > customPrice">Le montant ne peut pas dépasser le total.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-primary/70 mb-1">Moyen de paiement *</label>
                                    <select name="payment_method" x-model="paymentMethod" :required="!isOfferte" class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                                        <option value="orange_money">Orange Money</option>
                                        <option value="mtn_momo">MTN Mobile Money</option>
                                        <option value="cash">Espèces</option>
                                    </select>
                                </div>

                                <div x-show="paymentMethod !== 'cash'">
                                    <label class="block text-xs font-semibold text-primary/70 mb-1">Référence (Transaction) *</label>
                                    <input type="text" name="payment_reference" x-bind:required="!isOfferte && paymentMethod !== 'cash'" placeholder="N° transaction ou recu..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Solde restant --}}
                    <div class="flex items-center justify-between p-4 bg-gray-100 rounded-lg">
                        <span class="text-sm font-semibold text-primary">Solde restant à l'arrivée :</span>
                        <span class="text-xl font-bold text-primary" x-text="formatMoney(balanceDue) + ' FCFA'"></span>
                    </div>

                    <template x-if="isOfferte">
                        <div>
                            <input type="hidden" name="payment_amount" value="0">
                            <input type="hidden" name="payment_method" value="cash">
                        </div>
                    </template>

                    <div class="pt-4 flex items-center justify-between">
                        <button type="submit" name="action_back" value="1" formnovalidate class="px-6 py-3 bg-white border border-secondary/30 text-primary text-sm font-semibold rounded-lg hover:bg-slate-50 transition-colors shadow-sm">
                            Précédent
                        </button>
                        <button type="submit" :disabled="!isOfferte && (paymentAmount < minDeposit || paymentAmount > customPrice)" class="px-6 py-3 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            Finaliser et Enregistrer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('paymentCalc', (baseTotal, minPct, isReceptionist) => ({
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
            
            init() {
                this.updateCalculations();
                this.paymentAmount = this.minDeposit; // Par défaut, on pré-remplit l'acompte min
                this.updateCalculations();
            },
            
            updateCalculations() {
                let total = parseInt(this.customPrice) || 0;
                
                if (this.isOfferte) {
                    this.minDeposit = 0;
                    this.balanceDue = 0;
                } else {
                    this.minDeposit = Math.ceil(total * (this.minPercentage / 100));
                    let paid = parseInt(this.paymentAmount) || 0;
                    this.balanceDue = Math.max(0, total - paid);
                }
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
            },
            
            formatMoney(amount) {
                return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
            }
        }))
    })
</script>
@endsection
