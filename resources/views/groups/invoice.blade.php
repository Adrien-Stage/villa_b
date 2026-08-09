@extends('layouts.hotel')

@section('title', 'Facture groupe ' . $groupBooking->group_code)

@section('content')

{{-- En-tête page --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('groups.show', $groupBooking) }}"
           class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour au dossier groupe
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">
            Facture groupe — {{ $groupBooking->group_code }}
        </h1>
    </div>
    <button onclick="window.print()"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors no-print">
        <i data-lucide="printer" class="w-4 h-4"></i>
        Imprimer
    </button>
</div>

{{-- Corps de la facture --}}
<div id="invoice-print" class="bg-white rounded-xl shadow-sm overflow-hidden max-w-4xl">

    {{-- En-tête établissement + groupe --}}
    <div class="px-8 py-6 border-b border-secondary/10">
        <div class="flex items-start justify-between">

            {{-- Établissement --}}
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-full overflow-hidden border border-secondary/20 flex-shrink-0">
                        <img src="{{ asset('images/logo.png') }}" alt="{{ $tenant->name }}"
                             class="w-full h-full object-cover">
                    </div>
                    <div>
                        <h2 class="font-heading text-xl font-bold text-primary">{{ $tenant->name }}</h2>
                        <p class="text-xs text-primary/50">Établissement hôtelier</p>
                    </div>
                </div>
                @if($tenant->address)
                    <p class="text-xs text-primary/50">{{ $tenant->address }}</p>
                @endif
                @if($tenant->email)
                    <p class="text-xs text-primary/50">{{ $tenant->email }}</p>
                @endif
                @if($tenant->phone)
                    <p class="text-xs text-primary/50">{{ $tenant->phone }}</p>
                @endif
            </div>

            {{-- Infos dossier --}}
            <div class="text-right">
                <p class="font-heading text-2xl font-bold text-primary">{{ $groupBooking->group_code }}</p>
                <p class="text-xs text-primary/50 mt-1">
                    Émis le {{ now()->locale('fr')->isoFormat('D MMMM YYYY') }}
                </p>
                @php
                    $statusColors = [
                        'completed' => 'bg-gray-50 text-gray-600 border-gray-200',
                        'in_house'  => 'bg-green-50 text-green-700 border-green-200',
                        'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
                    ];
                    $sc = $statusColors[$groupBooking->status] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
                @endphp
                <span class="inline-block mt-2 px-3 py-1 text-xs font-semibold rounded-full border {{ $sc }}">
                    {{ $totals['balance_due'] <= 0 ? 'Soldée' : 'Solde dû' }}
                </span>
            </div>
        </div>
    </div>

    {{-- Infos contact + séjour --}}
    <div class="px-5 sm:px-8 py-5 border-b border-secondary/10 grid grid-cols-1 sm:grid-cols-3 print:grid-cols-3 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Facturé à</p>
            <p class="text-sm font-medium text-primary">{{ $groupBooking->contactCustomer?->full_name ?? '—' }}</p>
            @if($groupBooking->contactCustomer?->email)
                <p class="text-xs text-primary/50">{{ $groupBooking->contactCustomer->email }}</p>
            @endif
            @if($groupBooking->contactCustomer?->phone)
                <p class="text-xs text-primary/50">{{ $groupBooking->contactCustomer->phone }}</p>
            @endif
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Groupe</p>
            <p class="text-sm font-medium text-primary">{{ $groupBooking->group_name }}</p>
            <p class="text-xs text-primary/50 capitalize">
                {{ ['family' => 'Famille', 'corporate' => 'Corporate', 'wedding' => 'Mariage', 'tour_group' => 'Tour groupe'][$groupBooking->event_type] ?? $groupBooking->event_type }}
            </p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Séjour</p>
            <p class="text-xs text-primary/70">
                {{ $groupBooking->start_date->locale('fr')->isoFormat('D MMM YYYY') }}
                → {{ $groupBooking->end_date->locale('fr')->isoFormat('D MMM YYYY') }}
            </p>
            <p class="text-xs text-primary/70">
                {{ $groupBooking->start_date->diffInDays($groupBooking->end_date) }} nuits
                · {{ $totals['rooms'] }} chambre{{ $totals['rooms'] > 1 ? 's' : '' }}
            </p>
        </div>
    </div>

    {{-- Détail par chambre --}}
    <div class="px-8 py-4">
        <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-4">Détail par chambre</p>

        @foreach($groupBooking->bookings->where('status', '!=', 'cancelled') as $booking)
            <div class="mb-6 border border-secondary/15 rounded-lg overflow-hidden">

                {{-- En-tête chambre --}}
                <div class="bg-accent/20 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-7 h-7 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                            <span class="text-white text-[10px] font-semibold">
                                {{ strtoupper(substr($booking->customer->first_name, 0, 1) . substr($booking->customer->last_name, 0, 1)) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-primary">
                                Chambre {{ $booking->room->number }} — {{ $booking->room->roomType->name }}
                            </p>
                            <p class="text-[10px] text-primary/50">
                                {{ $booking->customer->full_name }}
                                · {{ $booking->adults_count }} adulte{{ $booking->adults_count > 1 ? 's' : '' }}
                                @if($booking->children_count > 0)
                                    + {{ $booking->children_count }} enfant{{ $booking->children_count > 1 ? 's' : '' }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <p class="text-xs font-mono text-primary/50">{{ $booking->booking_number }}</p>
                </div>

                {{-- Lignes folio de cette chambre --}}
                <div class="divide-y divide-secondary/10">
                    @foreach($booking->folioItems->where('type', '!=', 'payment') as $item)
                        <div class="grid grid-cols-12 gap-4 px-4 py-2.5 items-center">
                            <div class="col-span-6">
                                <p class="text-xs text-primary">
                                    {{-- Nettoie l'affichage des prestations groupe --}}
                                    {{ str_replace(" (groupe {$groupBooking->group_code})", '', $item->description) }}
                                </p>
                                @if($item->is_complimentary)
                                    <span class="text-[10px] text-green-600">Offert</span>
                                @endif
                            </div>
                            <div class="col-span-2 text-xs text-primary/60 text-center">
                                {{ $item->quantity }}
                            </div>
                            <div class="col-span-2 text-xs text-primary/60 text-right">
                                {{ $item->is_complimentary ? '—' : number_format($item->unit_price / 100, 0, ',', ' ') . ' F' }}
                            </div>
                            <div class="col-span-2 text-xs font-medium text-primary text-right">
                                {{ $item->is_complimentary ? 'Offert' : number_format($item->total_price / 100, 0, ',', ' ') . ' F' }}
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Sous-total chambre --}}
                <div class="bg-accent/10 px-4 py-2 flex justify-between items-center border-t border-secondary/10">
                    <span class="text-xs text-primary/50">
                        Sous-total chambre
                        @if($booking->balance_due > 0)
                            · <span class="text-red-500">Solde dû : {{ number_format($booking->balance_due / 100, 0, ',', ' ') }} FCFA</span>
                        @else
                            · <span class="text-green-600">Soldée</span>
                        @endif
                    </span>
                    <span class="text-sm font-semibold text-primary">
                        {{ number_format($booking->total_amount / 100, 0, ',', ' ') }} FCFA
                    </span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Totaux globaux --}}
    <div class="px-8 py-5 border-t-2 border-secondary/20 bg-accent/10">
        <div class="ml-auto w-72 space-y-2">
            <div class="flex justify-between text-xs text-primary/60">
                <span>Sous-total HT ({{ $totals['rooms'] }} chambres)</span>
                <span>{{ number_format($totals['subtotal'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-xs text-primary/60">
                <span>TVA (19,25%)</span>
                <span>{{ number_format($totals['tax'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm font-semibold text-primary pt-2 border-t border-secondary/20">
                <span>Total TTC groupe</span>
                <span>{{ number_format($totals['total'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-xs text-primary/60">
                <span>Total payé</span>
                <span class="text-green-600">{{ number_format($totals['paid'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm font-semibold pt-1
                        {{ $totals['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                <span>Solde dû</span>
                <span>{{ number_format($totals['balance_due'] / 100, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>
    </div>

    {{-- Mentions légales --}}
    <div class="px-8 py-4 border-t border-secondary/10">
        <p class="text-xs text-primary/40 text-center">
            TVA 19,25% incluse — République du Cameroun
        </p>
        <p class="text-xs text-primary/30 text-center mt-1">
            {{ $tenant->name }} · {{ $tenant->address }} · Merci de votre confiance
        </p>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #invoice-print, #invoice-print * { visibility: visible; }
    #invoice-print {
        position: absolute;
        top: 0; left: 0;
        width: 100%;
        box-shadow: none !important;
        border-radius: 0 !important;
        max-width: 100% !important;
    }
    .no-print { display: none !important; }
}
</style>

@endsection