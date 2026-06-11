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

                <div x-data="paymentCalc({{ $totalRoomAmount }}, {{ $minDepositPercentage }})" class="space-y-6">
                    
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
                                <p class="text-xs text-primary/60 mb-2">Vous pouvez ajuster le prix total du séjour si un tarif spécial a été accordé.</p>
                                <div class="relative w-1/2">
                                    <input type="number" name="custom_price" x-model="customPrice" @input="updateCalculations()" min="1" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary pr-12">
                                    <span class="absolute right-3 top-2.5 text-xs text-primary/50 font-medium">FCFA</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Section Acompte Obligatoire --}}
                    <div>
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
                                        <input type="number" name="payment_amount" x-model="paymentAmount" @input="updateCalculations()" :min="minDeposit" :max="customPrice" required class="w-full px-3 py-2 text-lg font-bold border border-secondary/30 rounded-lg text-primary outline-none focus:border-primary pr-12">
                                        <span class="absolute right-3 top-3 text-sm text-primary/50 font-medium">FCFA</span>
                                    </div>
                                    <p class="text-xs text-red-500 mt-1" x-show="paymentAmount < minDeposit">Le montant doit être au moins égal à l'acompte minimum.</p>
                                    <p class="text-xs text-red-500 mt-1" x-show="paymentAmount > customPrice">Le montant ne peut pas dépasser le total.</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-primary/70 mb-1">Moyen de paiement *</label>
                                    <select name="payment_method" x-model="paymentMethod" required class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                                        <option value="orange_money">Orange Money</option>
                                        <option value="mtn_momo">MTN Mobile Money</option>
                                        <option value="cash">Espèces</option>
                                    </select>
                                </div>

                                <div x-show="paymentMethod !== 'cash'">
                                    <label class="block text-xs font-semibold text-primary/70 mb-1">Référence (Transaction) *</label>
                                    <input type="text" name="payment_reference" x-bind:required="paymentMethod !== 'cash'" placeholder="N° transaction ou recu..." class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Solde restant --}}
                    <div class="flex items-center justify-between p-4 bg-gray-100 rounded-lg">
                        <span class="text-sm font-semibold text-primary">Solde restant à l'arrivée :</span>
                        <span class="text-xl font-bold text-primary" x-text="formatMoney(balanceDue) + ' FCFA'"></span>
                    </div>

                    <div class="pt-4 flex justify-end">
                        <button type="submit" :disabled="paymentAmount < minDeposit || paymentAmount > customPrice" class="px-6 py-3 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
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
        Alpine.data('paymentCalc', (baseTotal, minPct) => ({
            customPrice: baseTotal,
            minPercentage: minPct,
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
                this.minDeposit = Math.ceil(total * (this.minPercentage / 100));
                
                let paid = parseInt(this.paymentAmount) || 0;
                this.balanceDue = Math.max(0, total - paid);
            },
            
            formatMoney(amount) {
                return amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
            }
        }))
    })
</script>
@endsection
