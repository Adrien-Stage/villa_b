@extends('layouts.hotel')

@section('title', $groupBooking->group_code)

@section('content')

{{-- En-tête --}}
<div class="flex items-start justify-between mb-6">
    <div>
        <a href="{{ route('groups.index') }}"
            class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour aux groupes
        </a>
        <div class="flex items-center gap-3">
            <h1 class="font-heading text-2xl font-semibold text-primary font-mono">
                {{ $groupBooking->group_code }}
            </h1>
            @php
            $statusColors = [
            'pending' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
            'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
            'in_house' => 'bg-green-50 text-green-700 border-green-200',
            'completed' => 'bg-gray-50 text-gray-600 border-gray-200',
            'cancelled' => 'bg-red-50 text-red-600 border-red-200',
            ];
            $sc = $statusColors[$groupBooking->status] ?? 'bg-secondary/10 text-primary/60';
            @endphp
            <span class="px-3 py-1 text-xs font-semibold rounded-full border {{ $sc }}">
                {{ ucfirst($groupBooking->status) }}
            </span>
        </div>
        <p class="text-sm text-primary/50 mt-1">{{ $groupBooking->group_name }}</p>
    </div>

    {{-- Actions globales --}}
    <div class="flex items-center gap-2">
        @role('reception', 'manager')
        @if($groupBooking->status === 'confirmed')
        <form method="POST" action="{{ route('groups.checkInAll', $groupBooking) }}">
            @csrf
            <button type="submit"
                onclick="return confirm('Effectuer le check-in pour toutes les chambres ?')"
                class="flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                <i data-lucide="log-in" class="w-4 h-4"></i>
                Check-in groupe
            </button>
        </form>
        @endif

        @if(!in_array($groupBooking->status, ['completed', 'cancelled']))
        <a href="{{ route('groups.edit', $groupBooking) }}"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="pencil" class="w-4 h-4"></i>
            Modifier
        </a>
        @endif

        @if($groupBooking->status === 'in_house')
        <form method="POST" action="{{ route('groups.checkOutAll', $groupBooking) }}">
            @csrf
            <button type="submit"
                onclick="return confirm('Effectuer le check-out pour toutes les chambres ?')"
                class="flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                <i data-lucide="log-out" class="w-4 h-4"></i>
                Check-out groupe
            </button>
        </form>
        @endif

        @if($groupBooking->status === 'checkout')
        <a href="{{ route('groups.invoice', $groupBooking) }}"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="file-text" class="w-4 h-4"></i>
            Facture groupe
        </a>
        @endif
        @endrole
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
    {{ $errors->first() }}
</div>
@endif

<div class="grid grid-cols-3 gap-5">

    {{-- Colonne gauche : Infos groupe --}}
    <div class="space-y-4">

        {{-- Infos générales --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Informations</h2>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Type</dt>
                    <dd class="text-xs font-medium text-primary capitalize">
                        {{ ['family' => 'Famille', 'corporate' => 'Corporate', 'wedding' => 'Mariage', 'tour_group' => 'Tour groupe'][$groupBooking->event_type] ?? $groupBooking->event_type }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Arrivée</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $groupBooking->start_date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Départ</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $groupBooking->end_date->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Durée</dt>
                    <dd class="text-xs font-medium text-primary">
                        {{ $groupBooking->start_date->diffInDays($groupBooking->end_date) }} nuit(s)
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Contact --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Contact principal</h2>
            @if($groupBooking->contactCustomer)
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center">
                    <span class="text-white text-sm font-semibold">
                        {{ strtoupper(substr($groupBooking->contactCustomer->first_name, 0, 1) . substr($groupBooking->contactCustomer->last_name, 0, 1)) }}
                    </span>
                </div>
                <div>
                    <p class="text-sm font-medium text-primary">{{ $groupBooking->contactCustomer->full_name }}</p>
                    <p class="text-xs text-primary/50">{{ $groupBooking->contactCustomer->phone ?? $groupBooking->contactCustomer->email ?? '—' }}</p>
                </div>
            </div>
            <a href="{{ route('customers.show', $groupBooking->contactCustomer) }}"
                class="text-xs text-secondary hover:text-primary transition-colors flex items-center gap-1">
                Voir la fiche client
                <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
            @endif
        </div>

        {{-- Totaux financiers --}}
        <div class="bg-white rounded-xl shadow-sm p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-heading font-semibold text-primary text-sm">Résumé financier</h2>
                @if(in_array($groupBooking->status, ['confirmed', 'in_house']) && $totals['balance_due'] > 0)
                <button onclick="document.getElementById('modal-group-payment').classList.remove('hidden')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="credit-card" class="w-3.5 h-3.5"></i>
                    Encaisser
                </button>
                @endif
            </div>
            <dl class="space-y-2">
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Chambres</dt>
                    <dd class="text-xs font-medium text-primary">{{ $totals['rooms'] }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Total nuits</dt>
                    <dd class="text-xs font-medium text-primary">{{ $totals['nights'] }}</dd>
                </div>
                <div class="flex justify-between pt-2 border-t border-secondary/10">
                    <dt class="text-xs text-primary/50">Total TTC</dt>
                    <dd class="text-xs font-semibold text-primary">
                        {{ number_format($totals['total'] / 100, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Payé</dt>
                    <dd class="text-xs font-medium text-green-600">
                        {{ number_format($totals['paid'] / 100, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-xs text-primary/50">Solde dû</dt>
                    <dd class="text-xs font-semibold {{ $totals['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ number_format($totals['balance_due'] / 100, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
            </dl>
        </div>

        @if($groupBooking->notes)
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-2">Notes</h2>
            <p class="text-xs text-primary/70 leading-relaxed">{{ $groupBooking->notes }}</p>
        </div>
        @endif
    </div>

    {{-- Colonne droite : Chambres du groupe --}}
    <div class="col-span-2 space-y-4">

        {{-- Liste des chambres --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">
                    Chambres du groupe ({{ $groupBooking->bookings->count() }})
                </h2>
                @if(in_array($groupBooking->status, ['pending', 'confirmed']) && $roomTypes->isNotEmpty())
                <button onclick="document.getElementById('modal-add-room').classList.remove('hidden')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Ajouter une chambre
                </button>
                @endif
            </div>

            @if($groupBooking->bookings->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 text-primary/30">
                <i data-lucide="door-open" class="w-8 h-8 mb-2 opacity-40"></i>
                <p class="text-sm">Aucune chambre ajoutée</p>
                <p class="text-xs mt-1">Cliquez sur "Ajouter une chambre" pour commencer</p>
            </div>
            @else
            {{-- En-tête --}}
            <div class="grid grid-cols-12 gap-4 px-5 py-2 bg-accent/20 border-b border-secondary/10">
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Chambre</div>
                <div class="col-span-3 text-xs font-semibold uppercase tracking-widest text-primary/40">Client</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Personnes</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Montant</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Statut</div>
                <div class="col-span-1"></div>
            </div>

            @foreach($groupBooking->bookings as $booking)
            @php
            $statusColors = [
            'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
            'checked_in' => 'bg-green-50 text-green-700 border-green-200',
            'completed' => 'bg-gray-50 text-gray-600 border-gray-200',
            'cancelled' => 'bg-red-50 text-red-600 border-red-200',
            ];
            $bsc = $statusColors[$booking->status->value] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
            @endphp
            <div class="grid grid-cols-12 gap-4 px-5 py-3.5 border-b border-secondary/10 hover:bg-accent/10 transition-colors items-center">
                <div class="col-span-2">
                    <p class="text-sm font-semibold text-primary">{{ $booking->room->number }}</p>
                    <p class="text-xs text-primary/40">{{ $booking->room->roomType->name }}</p>
                </div>
                <div class="col-span-3">
                    <p class="text-xs font-medium text-primary truncate">{{ $booking->customer->full_name }}</p>
                </div>
                <div class="col-span-2 text-xs text-primary/70">
                    {{ $booking->adults_count }} adulte{{ $booking->adults_count > 1 ? 's' : '' }}
                    @if($booking->children_count > 0)
                    + {{ $booking->children_count }} enf.
                    @endif
                </div>
                <div class="col-span-2">
                    <p class="text-xs font-medium text-primary">
                        {{ number_format($booking->total_amount / 100, 0, ',', ' ') }} F
                    </p>
                    @if($booking->balance_due > 0)
                    <p class="text-xs text-red-500">
                        Solde : {{ number_format($booking->balance_due / 100, 0, ',', ' ') }} F
                    </p>
                    @else
                    <p class="text-xs text-green-600">Soldé</p>
                    @endif
                </div>
                <div class="col-span-2">
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full border {{ $bsc }}">
                        {{ $booking->status->label() }}
                    </span>
                </div>
                <div class="col-span-1 flex items-center justify-end gap-1">
                    <a href="{{ route('bookings.show', $booking) }}"
                        class="p-1.5 text-primary/30 hover:text-primary transition-colors"
                        title="Voir la réservation">
                        <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                    </a>
                    @if($booking->isEditable())
                    <form method="POST"
                        action="{{ route('groups.removeRoom', [$groupBooking, $booking]) }}"
                        onsubmit="return confirm('Retirer cette chambre du groupe ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="p-1.5 text-primary/20 hover:text-red-500 transition-colors">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
            @endif
        </div>

        {{-- Prestations du groupe --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-secondary/20">
                <h2 class="font-heading font-semibold text-primary text-sm">Prestations du groupe</h2>
                @if($groupBooking->status === 'in_house')
                <button onclick="document.getElementById('modal-group-folio').classList.remove('hidden')"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    Ajouter
                </button>
                @endif
            </div>

            @php
            // Récupère toutes les prestations de groupe (hors hébergement)
            // On déduplique par description pour n'afficher qu'une ligne par prestation de groupe
            $groupFolioItems = \App\Models\FolioItem::whereIn('booking_id', $groupBooking->bookings->pluck('id'))
            ->whereNotIn('type', ['room', 'payment'])
            ->where('notes', 'like', '%(groupe ' . $groupBooking->group_code . ')%')
            ->orWhere(function($q) use ($groupBooking) {
            $q->whereIn('booking_id', $groupBooking->bookings->pluck('id'))
            ->where('description', 'like', '%(groupe ' . $groupBooking->group_code . ')%');
            })
            ->get()
            ->groupBy('description');
            @endphp

            @if($groupFolioItems->isEmpty())
            <div class="flex flex-col items-center justify-center py-8 text-primary/30">
                <i data-lucide="receipt" class="w-7 h-7 mb-2 opacity-40"></i>
                <p class="text-xs">Aucune prestation de groupe enregistrée</p>
            </div>
            @else
            <div class="grid grid-cols-12 gap-4 px-5 py-2 bg-accent/20 border-b border-secondary/10">
                <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40">Type</div>
                <div class="col-span-5 text-xs font-semibold uppercase tracking-widest text-primary/40">Description</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">Chambres</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40">P.U.</div>
                <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">Total groupe</div>
            </div>

            @php
            $typeIcons = [
            'restaurant' => 'utensils',
            'activity' => 'map-pin',
            'spa' => 'sparkles',
            'minibar' => 'wine',
            'laundry' => 'shirt',
            'discount' => 'tag',
            'other' => 'package',
            ];
            @endphp

            @foreach($groupFolioItems as $description => $items)
            @php
            $firstItem = $items->first();
            $totalAmount = $items->sum('total_price');
            $roomCount = $items->count();
            @endphp
            <div class="grid grid-cols-12 gap-4 px-5 py-3 border-b border-secondary/10 items-center">
                <div class="col-span-1">
                    <i data-lucide="{{ $typeIcons[$firstItem->type] ?? 'package' }}" class="w-4 h-4 text-primary/30"></i>
                </div>
                <div class="col-span-5">
                    <p class="text-xs text-primary">
                        {{-- Retire la mention "(groupe GRP-...)" de l'affichage --}}
                        {{ str_replace(" (groupe {$groupBooking->group_code})", '', $description) }}
                    </p>
                    @if($firstItem->is_complimentary)
                    <span class="text-[10px] text-green-600 font-medium">Offert</span>
                    @endif
                </div>
                <div class="col-span-2 text-xs text-primary/70">
                    {{ $roomCount }} chambre{{ $roomCount > 1 ? 's' : '' }}
                </div>
                <div class="col-span-2 text-xs text-primary/70">
                    {{ $firstItem->is_complimentary ? '—' : number_format($firstItem->unit_price / 100, 0, ',', ' ') . ' F' }}
                </div>
                <div class="col-span-2 text-xs font-medium text-primary text-right">
                    {{ $firstItem->is_complimentary ? 'Offert' : number_format($totalAmount / 100, 0, ',', ' ') . ' FCFA' }}
                </div>
            </div>
            @endforeach
            @endif
        </div>
    </div>
</div>

{{-- Modal : Ajouter chambre au groupe --}}
<div id="modal-add-room" class="hidden fixed inset-0 z-50 flex items-center justify-center"
    style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh] flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0 shrink-0">
            <h3 class="font-heading font-semibold text-primary">Ajouter une chambre</h3>
            <button onclick="document.getElementById('modal-add-room').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('groups.addRoom', $groupBooking) }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf

            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            {{-- Info période --}}
            <div class="bg-accent/20 rounded-lg px-4 py-3 flex items-center justify-between text-xs text-primary/60">
                <span class="flex items-center gap-1.5">
                    <i data-lucide="calendar" class="w-3.5 h-3.5"></i>
                    {{ $groupBooking->start_date->locale('fr')->isoFormat('D MMM') }}
                    → {{ $groupBooking->end_date->locale('fr')->isoFormat('D MMM YYYY') }}
                </span>
                <span>{{ $groupBooking->start_date->diffInDays($groupBooking->end_date) }} nuit(s)</span>
            </div>

            {{-- Sélection chambre --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Chambre *
                </label>
                <select name="room_id" required
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                    <option value="">Sélectionner une chambre...</option>
                    @foreach($roomTypes as $type)
                    @php $rooms = $availableRooms[$type->id] ?? collect(); @endphp
                    @if($rooms->isNotEmpty())
                    <optgroup label="{{ $type->name }} — {{ number_format($type->base_price / 100, 0, ',', ' ') }} FCFA/nuit">
                        @foreach($rooms as $room)
                        <option value="{{ $room->id }}">
                            Chambre {{ $room->number }}
                            @if($room->floor) — Étage {{ $room->floor }}@endif
                            @if($room->view_type) — Vue {{ $room->view_type }}@endif
                        </option>
                        @endforeach
                    </optgroup>
                    @endif
                    @endforeach
                </select>
            </div>

            {{-- Client pour cette chambre --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Client de cette chambre *
                </label>
                <x-customer-search 
                    :customers="$customers" 
                    name="customer_id" 
                    :value="$groupBooking->contact_customer_id ?? ''" 
                    placeholder="Chercher le client pour cette chambre..." 
                    :allow-creation="true"
                >
                    <input type="hidden" name="new_customer" x-bind:disabled="!isCreatingNew" value="1">
                    
                    <div class="flex justify-between items-center bg-blue-50 text-blue-800 p-3 rounded-lg border border-blue-100 mb-4 mt-2">
                        <div class="flex items-center">
                            <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i>
                            <span class="font-medium text-sm">Nouveau client</span>
                        </div>
                        <button type="button" @click="cancelCreatingNew()" class="text-xs font-medium hover:underline">Annuler</button>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Prénom *</label>
                            <input type="text" name="first_name" x-model="customerFirstName" x-bind:required="isCreatingNew" x-bind:disabled="!isCreatingNew"
                                   class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Nom *</label>
                            <input type="text" name="last_name" x-model="customerName" x-bind:required="isCreatingNew" x-bind:disabled="!isCreatingNew"
                                   class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Email</label>
                            <input type="email" name="email" x-bind:disabled="!isCreatingNew"
                                   class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Téléphone</label>
                            <input type="text" name="phone" x-model="customerPhone" x-bind:disabled="!isCreatingNew"
                                   class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-2">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Nationalité</label>
                            <input type="text" name="nationality" placeholder="CM" maxlength="5" x-bind:disabled="!isCreatingNew"
                                   class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Type document</label>
                            <select name="id_document_type" x-bind:disabled="!isCreatingNew"
                                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                                <option value="">Sélectionner...</option>
                                <option value="passport">Passeport</option>
                                <option value="id_card">Carte d'identité</option>
                                <option value="driver_license">Permis de conduire</option>
                            </select>
                        </div>
                    </div>
                </x-customer-search>
            </div>

            {{-- Personnes --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Adultes *</label>
                    <input type="number" name="adults_count" value="1" min="1" required
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Enfants</label>
                    <input type="number" name="children_count" value="0" min="0"
                        class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Notes</label>
                <input type="text" name="notes"
                    placeholder="Besoins spécifiques pour cette chambre..."
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary placeholder-primary/30">
            </div>

            </div>

            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button"
                    onclick="document.getElementById('modal-add-room').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                    Annuler
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Ajouter la chambre
                </button>
            </div>
        </form>
    </div>
</div>


{{-- Modal : Prestation de groupe --}}
<div id="modal-group-folio" class="hidden fixed inset-0 z-50 flex items-center justify-center"
    style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <div>
                <h3 class="font-heading font-semibold text-primary">Prestation de groupe</h3>
                <p class="text-xs text-primary/50 mt-0.5">
                    Sera ajoutée aux {{ $groupBooking->bookings->where('status', 'checked_in')->count() }} chambres en séjour
                </p>
            </div>
            <button onclick="document.getElementById('modal-group-folio').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <form method="POST" action="{{ route('groups.folio.add', $groupBooking) }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
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
                    <option value="other">Autre</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Description *</label>
                <input type="text" name="description" required
                    placeholder="Ex: Visite musée Bandjoun, Dîner de gala..."
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

            {{-- Mode de répartition — c'est la clé de cette fonctionnalité --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-2">
                    Mode de facturation *
                </label>
                <div class="space-y-2">
                    <label class="flex items-start gap-3 p-3 border border-secondary/20 rounded-lg cursor-pointer hover:bg-accent/10 transition-colors">
                        <input type="radio" name="split_mode" value="per_room" checked class="mt-0.5">
                        <div>
                            <p class="text-xs font-medium text-primary">Par chambre</p>
                            <p class="text-[10px] text-primary/50">
                                Chaque chambre paie le même montant.
                                Ex: transfert aéroport 10 000 F → chaque chambre = 10 000 F
                            </p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 border border-secondary/20 rounded-lg cursor-pointer hover:bg-accent/10 transition-colors">
                        <input type="radio" name="split_mode" value="per_person" class="mt-0.5">
                        <div>
                            <p class="text-xs font-medium text-primary">Par personne</p>
                            <p class="text-[10px] text-primary/50">
                                Multiplié par le nombre d'occupants de chaque chambre.
                                Ex: visite musée 5 000 F/pers → chambre 2 adultes = 10 000 F
                            </p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 border border-secondary/20 rounded-lg cursor-pointer hover:bg-accent/10 transition-colors">
                        <input type="radio" name="split_mode" value="global" class="mt-0.5">
                        <div>
                            <p class="text-xs font-medium text-primary">Montant global réparti</p>
                            <p class="text-[10px] text-primary/50">
                                Le montant total est divisé équitablement entre les chambres.
                                Ex: décoration salle 60 000 F, 3 chambres → chaque chambre = 20 000 F
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_complimentary" value="1" id="group-complimentary"
                    class="w-4 h-4 rounded border-secondary/30">
                <label for="group-complimentary" class="text-xs text-primary/70">
                    Prestation offerte (tracée dans l'historique, montant à 0)
                </label>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Notes</label>
                <input type="text" name="notes"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
            </div>

            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button"
                    onclick="document.getElementById('modal-group-folio').classList.add('hidden')"
                    class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">
                    Annuler
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    Appliquer à toutes les chambres
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Modal : Paiement groupe --}}
<div id="modal-group-payment" class="hidden fixed inset-0 z-50 flex items-center justify-center"
    style="background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
            <div>
                <h3 class="font-heading font-semibold text-primary">Paiement groupe</h3>
                <p class="text-xs text-primary/50 mt-0.5">Réparti sur toutes les chambres</p>
            </div>
            <button onclick="document.getElementById('modal-group-payment').classList.add('hidden')"
                class="text-primary/30 hover:text-primary transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('groups.payment.add', $groupBooking) }}" class="flex flex-col flex-1 min-h-0 overflow-hidden">
            @csrf
            <div class="px-6 py-5 space-y-4 flex-1 overflow-y-auto min-h-0">
            @php $consumedBalance = $groupBooking->getConsumedBalance(); @endphp
            {{-- Solde total --}}
            <div class="bg-accent/20 rounded-lg px-4 py-3 flex justify-between items-center">
                <span class="text-xs text-primary/60">Solde consommé (réel)</span>
                <span class="text-lg font-heading font-semibold text-primary">
                    {{ number_format($consumedBalance / 100, 0, ',', ' ') }} FCFA
                </span>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">
                    Montant (FCFA) *
                </label>
                <input type="number" name="amount"
                    value="{{ (int) ceil($consumedBalance / 100) }}"
                    min="1" required
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

            {{-- Mode de répartition --}}
            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-2">
                    Répartition entre les chambres
                </label>
                <div class="space-y-2">
                    <label class="flex items-start gap-3 p-3 border border-secondary/20 rounded-lg cursor-pointer hover:bg-accent/10 transition-colors">
                        <input type="radio" name="distribution" value="proportional" checked class="mt-0.5">
                        <div>
                            <p class="text-xs font-medium text-primary">Proportionnelle</p>
                            <p class="text-[10px] text-primary/50">
                                Chaque chambre paie selon son solde relatif au total
                            </p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 border border-secondary/20 rounded-lg cursor-pointer hover:bg-accent/10 transition-colors">
                        <input type="radio" name="distribution" value="equal" class="mt-0.5">
                        <div>
                            <p class="text-xs font-medium text-primary">Égale</p>
                            <p class="text-[10px] text-primary/50">
                                Montant divisé équitablement entre toutes les chambres
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50 mb-1.5">Notes</label>
                <input type="text" name="notes"
                    class="w-full px-3 py-2 text-sm border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary">
            </div>

            </div>
            <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                <button type="button"
                    onclick="document.getElementById('modal-group-payment').classList.add('hidden')"
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

@endsection