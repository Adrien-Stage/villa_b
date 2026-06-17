@extends('layouts.hotel')

@section('title', $booking->booking_number)

@section('content')

{{-- En-tête --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('bookings.index') }}"
            class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour aux réservations
        </a>
        <div class="flex items-center gap-3">
            <h1 class="font-heading text-2xl font-semibold text-primary font-mono">
                {{ $booking->booking_number }}
            </h1>
            @php
            $statusColors = [
            'pending' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
            'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
            'checked_in' => 'bg-green-50 text-green-700 border-green-200',
            'checked_out' => 'bg-purple-50 text-purple-700 border-purple-200',
            'completed' => 'bg-gray-50 text-gray-600 border-gray-200',
            'cancelled' => 'bg-red-50 text-red-600 border-red-200',
            ];
            $sc = $statusColors[$booking->status->value] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
            @endphp
            <span class="px-3 py-1 text-xs font-semibold rounded-full border {{ $sc }}">
                {{ $booking->status->label() }}
            </span>
        </div>
    </div>

    {{-- Actions selon statut --}}
    <div class="flex items-center gap-2">
        @if($booking->status->value === 'confirmed')
        @role('reception', 'manager')
        @if($booking->checkin_code)
            <button type="button" onclick="document.getElementById('modal-checkin-otp').classList.remove('hidden')"
                class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                <i data-lucide="log-in" class="w-4 h-4"></i>
                Check-in
            </button>
        @else
            <form method="POST" action="{{ route('bookings.checkIn', $booking) }}" class="expect-popup">
                @csrf
                <button type="submit"
                    class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    Check-in
                </button>
            </form>
        @endif
        @endrole
        @endif

        @if($booking->status->value === 'checked_in')
        @role('reception', 'manager')
        <form method="POST" action="{{ route('bookings.checkOut', $booking) }}" class="expect-popup">
            @csrf
            <button type="submit"
                onclick="return confirm('Confirmer le check-out ?')"
                class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                Check-out
            </button>
        </form>
        @endrole
        @endif

        {{-- Ajoute après le bloc des boutons d'action en haut --}}
        @if($booking->status->value === 'completed' && $booking->invoice)
        @role('manager', 'reception', 'cashier')
        <a href="{{ route('invoices.show', $booking->invoice) }}"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            Voir la facture
        </a>
        @endrole
        @endif

        @if(in_array($booking->status->value, ['pending', 'confirmed']))
        @role('reception', 'manager')
        <form method="POST" action="{{ route('bookings.cancel', $booking) }}" class="expect-popup">
            @csrf
            <button type="submit"
                onclick="return confirm('Annuler cette réservation ?')"
                class="flex items-center gap-2 px-4 py-2 bg-white border border-red-200 text-red-600 text-sm font-medium rounded-lg hover:bg-red-50 transition-colors">
                <i data-lucide="x" class="w-4 h-4"></i>
                Annuler
            </button>
        </form>
        @endrole
        @endif

        @if($booking->isEditable())
        @role('reception', 'manager')
        <a href="{{ route('bookings.edit', $booking) }}"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="pencil" class="w-4 h-4"></i>
            Modifier
        </a>
        @endrole
        @endif
    </div>
</div>

{{-- Messages --}}
@if(session('success'))
<div class="mb-5 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    {{ session('success') }}
</div>
@endif
@if($errors->any())
<div class="mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

    {{-- Minuteur Intelligent (Si Checked In) --}}
    @if($booking->status->value === 'checked_in' && $booking->actual_check_in && !$booking->actual_check_out)
    <div class="mb-5 bg-gradient-to-r from-blue-900 to-blue-800 rounded-xl shadow-lg p-5 text-white flex items-center justify-between" 
         x-data="bookingTimer('{{ $booking->actual_check_in->toIso8601String() }}', '{{ $booking->check_out->copy()->setTime(12, 0, 0)->toIso8601String() }}')">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                <i data-lucide="clock" class="w-6 h-6 text-blue-100"></i>
            </div>
            <div>
                <h3 class="font-heading font-semibold text-blue-50 text-sm mb-1">Minuteur de séjour en cours</h3>
                <p class="text-xs text-blue-200">
                    Client présent depuis : <strong x-text="timeSpent">Calcul...</strong>
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-xs text-blue-200 uppercase tracking-wider font-semibold mb-1" x-text="isOverstay ? 'Temps de dépassement' : 'Temps restant estimé'"></p>
            <div class="flex items-baseline justify-end gap-2">
                <span x-show="isOverstay" class="flex h-3 w-3 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                </span>
                <p class="text-2xl font-bold font-mono" :class="isOverstay ? 'text-red-300' : 'text-white'" x-text="timeLeft"></p>
            </div>
            <p class="text-[10px] text-blue-300 mt-1" x-show="isOverstay">Départ initialement prévu à 12h00</p>
            <p class="text-[10px] text-blue-300 mt-1" x-show="!isOverstay">Fin prévue le {{ $booking->check_out->format('d/m/Y') }} à 12h00</p>
        </div>
    </div>
    @endif

<div class="grid grid-cols-3 gap-5">

    {{-- Colonne gauche : Infos + Client --}}
    <div class="space-y-4">

        {{-- Infos réservation --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Détails du séjour</h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Chambre</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $booking->room->number }} — {{ $booking->room->roomType->name }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Arrivée</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $booking->check_in->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Départ</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $booking->check_out->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Durée</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $booking->total_nights }} nuit{{ $booking->total_nights > 1 ? 's' : '' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Personnes</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $booking->adults_count }} adulte{{ $booking->adults_count > 1 ? 's' : '' }}
                        @if($booking->children_count > 0)
                        + {{ $booking->children_count }} enfant{{ $booking->children_count > 1 ? 's' : '' }}
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Source</dt>
                    <dd class="text-xs font-medium text-primary capitalize">{{ $booking->source }}</dd>
                </div>
                @if($booking->deposit_amount > 0)
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Acompte versé</dt>
                    <dd class="text-xs font-semibold text-green-600">
                        {{ number_format($booking->deposit_amount / 100, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                @endif
                @if($booking->actual_check_in)
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Check-in réel</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $booking->actual_check_in->locale('fr')->isoFormat('D MMM, HH:mm') }}
                    </dd>
                </div>
                @endif
                @if($booking->actual_check_out)
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Check-out réel</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $booking->actual_check_out->locale('fr')->isoFormat('D MMM, HH:mm') }}
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Client --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Client</h2>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center">
                    <span class="text-white text-sm font-semibold">
                        {{ strtoupper(substr($booking->customer->first_name, 0, 1) . substr($booking->customer->last_name, 0, 1)) }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-primary">{{ $booking->customer->full_name }}</p>
                    <p class="text-xs text-primary/50 capitalize">{{ $booking->customer->loyalty_level }}
                        · {{ number_format($booking->customer->loyalty_points) }} pts</p>
                </div>
            </div>
            @if($booking->customer->email)
            <p class="text-xs text-primary/60 flex items-center gap-1.5 mb-1">
                <i data-lucide="mail" class="w-3 h-3"></i>
                {{ $booking->customer->email }}
            </p>
            @endif
            @if($booking->customer->phone)
            <p class="text-xs text-primary/60 flex items-center gap-1.5">
                <i data-lucide="phone" class="w-3 h-3"></i>
                {{ $booking->customer->phone }}
            </p>
            @endif
            <a href="{{ route('customers.show', $booking->customer) }}"
                class="inline-flex items-center gap-1 mt-3 text-xs text-secondary hover:text-primary transition-colors">
                Voir la fiche client
                <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>

        {{-- Mandataire --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Mandataire (Payeur)</h2>
            @if($booking->booker)
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-secondary flex items-center justify-center">
                    <span class="text-white text-sm font-semibold">
                        {{ strtoupper(substr($booking->booker->first_name, 0, 1) . substr($booking->booker->last_name, 0, 1)) }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-primary">{{ $booking->booker->full_name }}</p>
                    <p class="text-xs text-primary/50 capitalize">Mandataire tiers</p>
                </div>
            </div>
            @if($booking->booker->email)
            <p class="text-xs text-primary/60 flex items-center gap-1.5 mb-1">
                <i data-lucide="mail" class="w-3 h-3"></i>
                {{ $booking->booker->email }}
            </p>
            @endif
            @if($booking->booker->phone)
            <p class="text-xs text-primary/60 flex items-center gap-1.5 mb-1">
                <i data-lucide="phone" class="w-3 h-3"></i>
                {{ $booking->booker->phone }}
            </p>
            @endif
            @if($booking->booker->id_document_number)
            <p class="text-xs text-primary/60 flex items-center gap-1.5 mb-1">
                <i data-lucide="file-digit" class="w-3 h-3"></i>
                {{ $booking->booker->id_document_type ?? 'Document' }} : {{ $booking->booker->id_document_number }}
            </p>
            @endif
            <a href="{{ route('customers.show', $booking->booker) }}"
                class="inline-flex items-center gap-1 mt-3 text-xs text-secondary hover:text-primary transition-colors">
                Voir la fiche mandataire
                <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
            @else
            <p class="text-xs text-primary/60 italic flex items-center gap-1.5">
                <i data-lucide="user" class="w-3.5 h-3.5 text-primary/40"></i>
                Le client final lui-même
            </p>
            @endif
        </div>

        {{-- Notes --}}
        @if($booking->notes)
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-2">Notes client</h2>
            <p class="text-xs text-primary/70 leading-relaxed">{{ $booking->notes }}</p>
        </div>
        @endif
    </div>

    {{-- Colonne centrale : Folio --}}
    <div class="col-span-2 space-y-4">

        {{-- Folio --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Folio du séjour</h2>
                @if($booking->status->value === 'checked_in')
                <button onclick="document.getElementById('modal-folio').classList.remove('hidden')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Ajouter prestation
                </button>
                @endif
            </div>

            @if($booking->folioItems->isEmpty())
            <div class="flex flex-col items-center justify-center py-10 text-primary/30">
                <i data-lucide="receipt" class="w-8 h-8 mb-2 opacity-40"></i>
                <p class="text-xs">Aucune prestation enregistrée</p>
            </div>
            @else
            {{-- En-tête folio --}}
            <div class="grid grid-cols-12 gap-4 px-5 py-2 bg-accent/20 border-b border-secondary/10">
                <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40">Type</div>
                <div class="col-span-5 text-xs font-semibold uppercase tracking-widest text-primary/40">Description</div>
                <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40">Qté</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">P.U.</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">Total</div>
                <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40">Actions</div>
            </div>

            @foreach($booking->folioItems as $item)
            @php
            $typeIcons = [
            'room' => 'hotel',
            'restaurant' => 'utensils',
            'activity' => 'map-pin',
            'spa' => 'sparkles',
            'minibar' => 'wine',
            'laundry' => 'shirt',
            'discount' => 'tag',
            'payment' => 'credit-card',
            'other' => 'package',
            ];
            @endphp
            <div class="grid grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 items-center">

                {{-- Icône type --}}
                <div class="col-span-1">
                    <i data-lucide="{{ $typeIcons[$item->type] ?? 'package' }}" class="w-4 h-4 text-primary/30"></i>
                </div>

                {{-- Description --}}
                <div class="col-span-5 min-w-0">
                    <p class="text-xs text-primary truncate">{{ $item->description }}</p>
                    <div class="flex items-center gap-2 mt-0.5">
                        @if($item->is_complimentary)
                        <span class="text-[10px] text-green-600 font-medium">Offert</span>
                        @endif
                        @if($item->notes)
                        <span class="text-[10px] text-primary/40 italic truncate">{{ $item->notes }}</span>
                        @endif
                    </div>
                </div>

                {{-- Quantité --}}
                <div class="col-span-1 text-xs text-primary/70">
                    {{ $item->quantity }}
                </div>

                {{-- Prix unitaire --}}
                <div class="col-span-2 text-xs text-primary/70">
                    {{ $item->is_complimentary ? '—' : number_format($item->unit_price / 100, 0, ',', ' ') . ' F' }}
                </div>

                {{-- Total --}}
                <div class="col-span-2 text-xs font-medium text-primary text-right">
                    {{ $item->formattedPrice() }}
                </div>

                {{-- Action --}}
                <div class="col-span-1 flex justify-end">
                    @if($booking->status->value === 'checked_in' && $item->type !== 'room')
                    <form method="POST"
                        action="{{ route('bookings.folio.remove', [$booking, $item]) }}"
                        onsubmit="return confirm('Retirer cette prestation ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-1 text-primary/20 hover:text-red-500 transition-colors">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                    @endif
                </div>

            </div>
            @endforeach

            {{-- Totaux --}}
            <div class="px-5 py-4 space-y-2 border-t border-secondary/20 bg-accent/10">
                <div class="flex justify-between text-xs text-primary/60">
                    <span>{{ $booking->tax_amount > 0 ? 'Sous-total HT' : 'Sous-total' }}</span>
                    <span>{{ number_format(($booking->total_room_amount + $booking->extras_amount - $booking->discount_amount) / 100, 0, ',', ' ') }} FCFA</span>
                </div>
                @if($booking->tax_amount > 0)
                <div class="flex justify-between text-xs text-primary/60">
                    <span>TVA (19,25%)</span>
                    <span>{{ number_format($booking->tax_amount / 100, 0, ',', ' ') }} FCFA</span>
                </div>
                @endif
                @if($booking->discount_amount > 0)
                <div class="flex justify-between text-xs text-green-600">
                    <span>Remises</span>
                    <span>-{{ number_format($booking->discount_amount / 100, 0, ',', ' ') }} FCFA</span>
                </div>
                @endif
                <div class="flex justify-between text-sm font-semibold text-primary pt-2 border-t border-secondary/20">
                    <span>{{ $booking->tax_amount > 0 ? 'Total TTC' : 'Total' }}</span>
                    <span>{{ number_format($booking->total_amount / 100, 0, ',', ' ') }} FCFA</span>
                </div>
                @if($booking->deposit_amount > 0)
                <div class="flex justify-between text-xs text-primary/60">
                    <span>Acompte à la réservation</span>
                    <span class="text-green-600">-{{ number_format($booking->deposit_amount / 100, 0, ',', ' ') }} FCFA</span>
                </div>
                @if($booking->paid_amount - $booking->deposit_amount > 0)
                <div class="flex justify-between text-xs text-primary/60">
                    <span>Paiements complémentaires</span>
                    <span class="text-green-600">-{{ number_format(($booking->paid_amount - $booking->deposit_amount) / 100, 0, ',', ' ') }} FCFA</span>
                </div>
                @endif
                @else
                <div class="flex justify-between text-xs text-primary/60">
                    <span>Payé</span>
                    <span>{{ number_format($booking->paid_amount / 100, 0, ',', ' ') }} FCFA</span>
                </div>
                @endif
                <div class="flex justify-between text-sm font-semibold {{ $booking->balance_due > 0 ? 'text-red-600' : 'text-green-600' }} pt-1">
                    <span>Solde dû</span>
                    <span>{{ number_format($booking->balance_due / 100, 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
            @endif
        </div>

        {{-- Paiements --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Paiements</h2>
                @if(in_array($booking->status->value, ['confirmed', 'checked_in']) && $booking->balance_due > 0)
                <button onclick="document.getElementById('modal-payment').classList.remove('hidden')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Encaisser
                </button>
                @endif
            </div>

            @if($booking->payments->isEmpty())
            <div class="flex flex-col items-center justify-center py-8 text-primary/30">
                <i data-lucide="credit-card" class="w-7 h-7 mb-2 opacity-40"></i>
                <p class="text-xs">Aucun paiement enregistré</p>
            </div>
            @else
            <div class="divide-y divide-secondary/10">
                @foreach($booking->payments as $payment)
                <div class="flex items-center justify-between px-5 py-3">
                    <div>
                        <p class="text-xs font-medium text-primary capitalize">{{ $payment->method }}</p>
                        <p class="text-[10px] text-primary/40">{{ $payment->paid_at?->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}</p>
                    </div>
                    <span class="text-sm font-semibold {{ $payment->amount > 0 ? 'text-green-600' : 'text-red-500' }}">
                        {{ $payment->formattedAmount() }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal : Ajouter prestation au folio --}}
<div id="modal-folio" class="hidden fixed inset-0 z-50 flex items-center justify-center"
    style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <h3 class="font-heading font-semibold text-primary">Ajouter une prestation</h3>
            <button onclick="document.getElementById('modal-folio').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('bookings.folio.add', $booking) }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Type *</label>
                <select name="type" required
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    <option value="restaurant">Restaurant</option>
                    <option value="activity">Activité</option>
                    <option value="spa">Spa</option>
                    <option value="minibar">Minibar</option>
                    <option value="laundry">Blanchisserie</option>
                    <option value="discount">Remise</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Description *</label>
                <input type="text" name="description" required
                    placeholder="Ex: Dîner gastronomique, Excursion lac Barombi..."
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary placeholder-primary/30">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Quantité *</label>
                    <input type="number" name="quantity" value="1" min="0.5" step="0.5" required
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Prix unitaire (FCFA)</label>
                    <input type="number" name="unit_price" value="0" min="0"
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_complimentary" value="1" id="complimentary"
                    class="w-4 h-4 rounded border-secondary/30 text-primary">
                <label for="complimentary" class="text-xs text-primary/70">
                    Prestation offerte (montant à 0, mais tracée dans l'historique)
                </label>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Notes</label>
                <input type="text" name="notes"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
            </div>
            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button" onclick="document.getElementById('modal-folio').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Paiement --}}
<div id="modal-payment" class="hidden fixed inset-0 z-50 flex items-center justify-center"
    style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <h3 class="font-heading font-semibold text-primary">Encaisser un paiement</h3>
            <button onclick="document.getElementById('modal-payment').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('bookings.payment.add', $booking) }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            @php $consumedBalance = $booking->getConsumedBalance(); @endphp
            {{-- Solde affiché --}}
            <div class="bg-accent/30 rounded-lg px-4 py-3 flex justify-between items-center">
                <span class="text-xs text-primary/60">Solde consommé (réel)</span>
                <span class="text-lg font-heading font-semibold text-primary">
                    {{ number_format($consumedBalance / 100, 0, ',', ' ') }} FCFA
                </span>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Montant (FCFA) *
                </label>
                <input type="number"
                    name="amount"
                    value="{{ (int) ceil($consumedBalance / 100) }}"
                    min="1"
                    required
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Mode de paiement *
                </label>
                <select name="method" required
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    <option value="cash">Espèces</option>
                    <option value="orange_money">Orange Money</option>
                    <option value="mtn_momo">MTN MoMo</option>
                    <option value="bank_transfer">Virement bancaire</option>
                    <option value="stripe">Carte bancaire</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Notes</label>
                <input type="text" name="notes"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
            </div>

            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button"
                    onclick="document.getElementById('modal-payment').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                    Annuler
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Encaisser
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Check-in OTP (Validation automatique) --}}
@if($booking->checkin_code && $booking->status->value === 'confirmed')
<div id="modal-checkin-otp"
     class="hidden fixed inset-0 z-50 flex items-center justify-center"
     style="background: rgba(15,2,1,0.6); backdrop-filter: blur(6px);"
     x-data="otpCheckin('{{ route('bookings.checkIn', $booking) }}', '{{ csrf_token() }}', {{ $booking->checkin_attempts >= 3 ? 'true' : 'false' }})"
     x-on:keydown.escape.window="closeModal()">

    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden transform transition-all"
         :class="shake ? 'animate-shake' : ''">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full flex items-center justify-center"
                     :class="locked ? 'bg-red-100' : 'bg-primary/10'">
                    <i :data-lucide="locked ? 'lock' : 'shield-check'" class="w-4 h-4"
                       :class="locked ? 'text-red-600' : 'text-primary'"></i>
                </div>
                <h3 class="font-heading font-semibold text-primary text-sm">Code de sécurité Check-in</h3>
            </div>
            <button @click="closeModal()"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-6">

            {{-- Message Bloqué --}}
            <template x-if="locked">
                <div class="text-center py-4">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="lock" class="w-8 h-8 text-red-500"></i>
                    </div>
                    <h4 class="text-base font-bold text-red-700 mb-2">Compte verrouillé</h4>
                    <p class="text-sm text-red-600/80">Nombre maximum de tentatives atteint.<br>Veuillez contacter le manager pour débloquer.</p>
                </div>
            </template>

            {{-- Saisie OTP --}}
            <template x-if="!locked">
                <div>
                    <p class="text-sm text-primary/60 text-center mb-6">
                        Saisissez le code à 6 chiffres communiqué lors de la réservation.
                    </p>

                    {{-- 6 inputs individuels --}}
                    <div class="flex items-center justify-center gap-2 mb-4">
                        <template x-for="(digit, index) in digits" :key="index">
                            <input type="text"
                                   inputmode="numeric"
                                   maxlength="1"
                                   :id="'otp-' + index"
                                   :value="digit"
                                   @input="handleInput($event, index)"
                                   @keydown="handleKeydown($event, index)"
                                   @paste="handlePaste($event)"
                                   @focus="$event.target.select()"
                                   :disabled="verifying"
                                   class="w-12 h-14 text-center text-2xl font-mono font-bold border-2 rounded-xl outline-none transition-all duration-200"
                                   :class="errorState
                                       ? 'border-red-400 bg-red-50 text-red-600'
                                       : successState
                                           ? 'border-green-400 bg-green-50 text-green-700'
                                           : (digit !== ''
                                               ? 'border-primary/40 bg-primary/5 text-primary'
                                               : 'border-secondary/30 bg-white text-primary focus:border-primary focus:ring-2 focus:ring-primary/20')">
                        </template>
                    </div>

                    {{-- Loader --}}
                    <div x-show="verifying" class="flex items-center justify-center gap-2 text-primary/60 text-sm py-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/>
                            <path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round" class="opacity-75"/>
                        </svg>
                        Vérification en cours...
                    </div>

                    {{-- Message d'erreur --}}
                    <div x-show="errorMessage" x-transition class="text-center mt-2">
                        <p class="text-xs font-semibold text-red-600" x-text="errorMessage"></p>
                    </div>

                    {{-- Message de succès --}}
                    <div x-show="successState" x-transition class="text-center mt-2">
                        <p class="text-xs font-semibold text-green-600">✓ Code validé ! Redirection...</p>
                    </div>

                    {{-- Indicateur de tentatives --}}
                    <div x-show="remaining !== null && remaining < 3 && !successState" class="flex items-center justify-center gap-1.5 mt-4">
                        <template x-for="i in 3">
                            <div class="w-2 h-2 rounded-full transition-colors duration-300"
                                 :class="i <= remaining ? 'bg-orange-400' : 'bg-red-300'"></div>
                        </template>
                        <span class="text-[10px] text-primary/50 ml-1" x-text="remaining + ' tentative(s) restante(s)'"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-3 border-t border-secondary/10 bg-gray-50/80 rounded-b-2xl">
            <button @click="closeModal()"
                class="w-full py-2 text-sm text-primary/50 hover:text-primary transition-colors font-medium">
                Annuler
            </button>
        </div>
    </div>
</div>

<style>
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
        20%, 40%, 60%, 80% { transform: translateX(4px); }
    }
    .animate-shake { animation: shake 0.5s ease-in-out; }
</style>
@endif

{{-- Modal : Succès de Réservation (Affiche le code généré) --}}
@if(session('checkin_code'))
<div id="modal-success-code" class="fixed inset-0 z-[60] flex items-center justify-center"
    style="background: rgba(15,2,1,0.7); backdrop-filter: blur(8px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden transform scale-100 transition-all">
        <div class="bg-green-600 p-6 text-center">
            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                <i data-lucide="check" class="w-8 h-8 text-green-600"></i>
            </div>
            <h2 class="text-xl font-heading font-bold text-white">Réservation Confirmée !</h2>
            <p class="text-green-100 text-sm mt-1">L'acompte a bien été enregistré.</p>
        </div>
        
        <div class="p-8 text-center">
            <h3 class="text-sm font-semibold text-primary/70 uppercase tracking-wider mb-2">Code de sécurité Check-in</h3>
            <p class="text-xs text-primary/50 mb-6">Veuillez communiquer ce code unique au client ou au mandataire. Ce code sera exigé lors de la remise des clés.</p>
            
            <div class="bg-gray-100 rounded-xl p-4 mb-8 inline-block shadow-inner border border-gray-200">
                <span class="text-5xl font-mono tracking-widest font-black text-primary">{{ session('checkin_code') }}</span>
            </div>
            
            <button onclick="document.getElementById('modal-success-code').remove()" class="w-full py-3 bg-primary text-white font-semibold rounded-xl hover:bg-surface-dark transition-all shadow-md">
                J'ai copié le code, fermer
            </button>
        </div>
    </div>
</div>
@endif

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bookingTimer', (checkInStr, checkOutStr) => ({
            timeSpent: 'Calcul...',
            timeLeft: 'Calcul...',
            isOverstay: false,
            interval: null,

            init() {
                this.updateTimer();
                this.interval = setInterval(() => this.updateTimer(), 1000);
            },

            destroy() {
                if (this.interval) clearInterval(this.interval);
            },

            updateTimer() {
                const now = new Date();
                const checkInDate = new Date(checkInStr);
                const checkOutDate = new Date(checkOutStr);

                // --- 1. Calcul du temps passé ---
                let diffSpent = now - checkInDate;
                if (diffSpent < 0) diffSpent = 0; // Au cas où
                this.timeSpent = this.formatDuration(diffSpent);

                // --- 2. Calcul du temps restant ou dépassé ---
                let diffLeft = checkOutDate - now;
                if (diffLeft < 0) {
                    this.isOverstay = true;
                    this.timeLeft = "+ " + this.formatDuration(Math.abs(diffLeft));
                } else {
                    this.isOverstay = false;
                    this.timeLeft = this.formatDuration(diffLeft);
                }
            },

            formatDuration(ms) {
                const seconds = Math.floor((ms / 1000) % 60);
                const minutes = Math.floor((ms / 1000 / 60) % 60);
                const hours = Math.floor((ms / (1000 * 60 * 60)) % 24);
                const days = Math.floor(ms / (1000 * 60 * 60 * 24));

                let parts = [];
                if (days > 0) parts.push(`${days}j`);
                if (hours > 0 || days > 0) parts.push(`${hours}h`);
                parts.push(`${minutes}m`);
                parts.push(`${seconds}s`);

                return parts.join(' ');
            }
        }));

        // ===== OTP Check-in (validation automatique) =====
        Alpine.data('otpCheckin', (url, csrfToken, initiallyLocked) => ({
            digits: ['', '', '', '', '', ''],
            verifying: false,
            errorState: false,
            successState: false,
            errorMessage: '',
            shake: false,
            locked: initiallyLocked,
            remaining: null,

            closeModal() {
                document.getElementById('modal-checkin-otp').classList.add('hidden');
                this.resetInputs();
            },

            resetInputs() {
                this.digits = ['', '', '', '', '', ''];
                this.errorState = false;
                this.errorMessage = '';
                this.successState = false;
            },

            handleInput(event, index) {
                const value = event.target.value.replace(/\D/g, '');
                this.digits[index] = value ? value.charAt(0) : '';
                event.target.value = this.digits[index];

                // Clear error state when user starts typing
                if (this.errorState) {
                    this.errorState = false;
                    this.errorMessage = '';
                }

                // Auto-focus next
                if (value && index < 5) {
                    this.$nextTick(() => {
                        const next = document.getElementById('otp-' + (index + 1));
                        if (next) next.focus();
                    });
                }

                // Check if all 6 digits are entered
                const code = this.digits.join('');
                if (code.length === 6) {
                    this.$nextTick(() => this.submitCode(code));
                }
            },

            handleKeydown(event, index) {
                if (event.key === 'Backspace') {
                    if (this.digits[index] === '' && index > 0) {
                        event.preventDefault();
                        this.digits[index - 1] = '';
                        this.$nextTick(() => {
                            const prev = document.getElementById('otp-' + (index - 1));
                            if (prev) prev.focus();
                        });
                    } else {
                        this.digits[index] = '';
                    }
                }
                if (event.key === 'ArrowLeft' && index > 0) {
                    event.preventDefault();
                    document.getElementById('otp-' + (index - 1))?.focus();
                }
                if (event.key === 'ArrowRight' && index < 5) {
                    event.preventDefault();
                    document.getElementById('otp-' + (index + 1))?.focus();
                }
            },

            handlePaste(event) {
                event.preventDefault();
                const pasted = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                if (!pasted) return;

                for (let i = 0; i < 6; i++) {
                    this.digits[i] = pasted[i] || '';
                }
                // Focus last filled or the next empty
                this.$nextTick(() => {
                    const focusIndex = Math.min(pasted.length, 5);
                    document.getElementById('otp-' + focusIndex)?.focus();

                    if (pasted.length === 6) {
                        this.submitCode(pasted);
                    }
                });
            },

            async submitCode(code) {
                this.verifying = true;
                this.errorMessage = '';
                this.errorState = false;

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ checkin_code: code }),
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        // Succès
                        this.verifying = false;
                        this.successState = true;
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        // Erreur
                        this.verifying = false;
                        this.errorState = true;
                        this.errorMessage = data.message || 'Code invalide.';
                        this.shake = true;

                        if (data.remaining !== undefined) {
                            this.remaining = data.remaining;
                        }
                        if (data.locked) {
                            this.locked = true;
                        }

                        setTimeout(() => {
                            this.shake = false;
                            if (!this.locked) {
                                this.$nextTick(() => document.getElementById('otp-0')?.focus());
                            }
                        }, 600);
                    }
                } catch (err) {
                    this.verifying = false;
                    this.errorState = true;
                    this.errorMessage = 'Erreur de connexion. Réessayez.';
                    this.shake = true;
                    setTimeout(() => {
                        this.shake = false;
                        this.$nextTick(() => document.getElementById('otp-0')?.focus());
                    }, 600);
                }
            }
        }));
    });
</script>
@endpush

@endsection