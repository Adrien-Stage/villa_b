@extends('layouts.hotel')

@section('title', 'Facture ' . $invoice->invoice_number)

@section('content')

{{-- En-tête --}}
<div class="flex items-center justify-between mb-6">
    <div>
        <a href="{{ route('bookings.show', $invoice->booking) }}"
            class="text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-2">
            <i data-lucide="arrow-left" class="w-3 h-3"></i>
            Retour à la réservation
        </a>
        <h1 class="font-heading text-2xl font-semibold text-primary">
            Facture {{ $invoice->invoice_number }}
        </h1>
        <p class="text-sm text-primary/50 mt-0.5">
            Émise le {{ $invoice->invoice_date->locale('fr')->isoFormat('D MMMM YYYY') }}
        </p>
    </div>

    {{-- Actions --}}
    <div class="flex items-center gap-2">
        <button onclick="window.print()"
            class="flex items-center gap-2 px-4 py-2 bg-white border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="printer" class="w-4 h-4"></i>
            Imprimer
        </button>
    </div>
</div>

{{-- Corps de la facture --}}
<div class="bg-white rounded-xl shadow-sm overflow-hidden max-w-3xl" id="invoice-print">

    {{-- En-tête facture --}}
    <div class="px-8 py-6 border-b border-secondary/10">
        <div class="flex items-start justify-between">

            {{-- Infos établissement depuis le tenant --}}
            <div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-12 h-12 rounded-full overflow-hidden border border-secondary/20 flex-shrink-0">
                        <img src="{{ asset('images/logo.png') }}"
                            alt="{{ $tenant->name }}"
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

            {{-- Numéro et statut ... --}}
        </div>
    </div>
    {{-- Infos client + séjour --}}
    <div class="px-8 py-5 border-b border-secondary/10 grid grid-cols-2 gap-6">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Facturé à</p>
            <p class="text-sm font-medium text-primary">{{ $invoice->customer->full_name }}</p>
            @if($invoice->customer->email)
            <p class="text-xs text-primary/50">{{ $invoice->customer->email }}</p>
            @endif
            @if($invoice->customer->phone)
            <p class="text-xs text-primary/50">{{ $invoice->customer->phone }}</p>
            @endif
            @if($invoice->customer->nationality)
            <p class="text-xs text-primary/50">{{ $invoice->customer->nationality }}</p>
            @endif
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-primary/40 mb-2">Détails du séjour</p>
            <p class="text-xs text-primary/70">
                Chambre {{ $invoice->booking->room->number }}
                — {{ $invoice->booking->room->roomType->name }}
            </p>
            <p class="text-xs text-primary/70">
                Du {{ $invoice->booking->check_in->locale('fr')->isoFormat('D MMM YYYY') }}
                au {{ $invoice->booking->check_out->locale('fr')->isoFormat('D MMM YYYY') }}
            </p>
            <p class="text-xs text-primary/70">
                {{ $invoice->booking->total_nights }} nuit{{ $invoice->booking->total_nights > 1 ? 's' : '' }}
                · {{ $invoice->booking->adults_count }} adulte{{ $invoice->booking->adults_count > 1 ? 's' : '' }}
            </p>
        </div>
    </div>

    {{-- Lignes de facturation --}}
    <div class="px-8 py-4">

        {{-- En-tête tableau --}}
        <div class="grid grid-cols-12 gap-4 py-2 border-b border-secondary/20 mb-1">
            <div class="{{ $invoice->tax_amount > 0 ? 'col-span-6' : 'col-span-7' }} text-xs font-semibold uppercase tracking-widest text-primary/40">Description</div>
            <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40 text-center">Qté</div>
            <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">{{ $invoice->tax_amount > 0 ? 'P.U. HT' : 'P.U.' }}</div>
            @if($invoice->tax_amount > 0)
            <div class="col-span-1 text-xs font-semibold uppercase tracking-widest text-primary/40 text-center">TVA</div>
            @endif
            <div class="col-span-2 text-xs font-semibold uppercase tracking-widest text-primary/40 text-right">{{ $invoice->tax_amount > 0 ? 'Total TTC' : 'Total' }}</div>
        </div>

        {{-- Lignes --}}
        @foreach($invoice->items as $item)
        <div class="grid grid-cols-12 gap-4 py-3 border-b border-secondary/10 items-center">
            <div class="{{ $invoice->tax_amount > 0 ? 'col-span-6' : 'col-span-7' }}">
                <p class="text-sm text-primary">{{ $item->description }}</p>
                <p class="text-xs text-primary/40 capitalize">{{ $item->category }}</p>
            </div>
            <div class="col-span-1 text-xs text-primary/70 text-center">
                {{ $item->quantity }}
            </div>
            <div class="col-span-2 text-xs text-primary/70 text-right">
                {{ number_format($item->unit_price / 100, 0, ',', ' ') }} F
            </div>
            @if($invoice->tax_amount > 0)
            <div class="col-span-1 text-xs text-primary/70 text-center">
                {{ $item->tax_rate }}%
            </div>
            @endif
            <div class="col-span-2 text-sm font-medium text-primary text-right">
                {{ number_format($item->total_price / 100, 0, ',', ' ') }} F
            </div>
        </div>
        @endforeach
    </div>

    {{-- Totaux --}}
    <div class="px-8 py-5 border-t border-secondary/20 bg-accent/10">
        <div class="ml-auto w-64 space-y-2">
            <div class="flex justify-between text-xs text-primary/60">
                <span>{{ $invoice->tax_amount > 0 ? 'Sous-total HT' : 'Sous-total' }}</span>
                <span>{{ number_format($invoice->subtotal / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            @if($invoice->tax_amount > 0)
            <div class="flex justify-between text-xs text-primary/60">
                <span>TVA (19,25%)</span>
                <span>{{ number_format($invoice->tax_amount / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            @endif
            <div class="flex justify-between text-sm font-semibold text-primary pt-2 border-t border-secondary/20">
                <span>{{ $invoice->tax_amount > 0 ? 'Total TTC' : 'Total' }}</span>
                <span>{{ number_format($invoice->total_amount / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-xs text-primary/60">
                <span>Montant payé</span>
                <span>{{ number_format($invoice->paid_amount / 100, 0, ',', ' ') }} FCFA</span>
            </div>
            <div class="flex justify-between text-sm font-semibold pt-1
                        {{ $invoice->balance_due > 0 ? 'text-red-600' : 'text-green-600' }}">
                <span>Solde dû</span>
                <span>{{ number_format($invoice->balance_due / 100, 0, ',', ' ') }} FCFA</span>
            </div>
        </div>
    </div>

    {{-- Mentions légales --}}
    <div class="px-8 py-4 border-t border-secondary/10 bg-accent/5">
        <p class="text-xs text-primary/40 text-center">
            {{ $invoice->legal_notes ?? 'TVA 19,25% incluse — République du Cameroun' }}
        </p>
        <p class="text-xs text-primary/30 text-center mt-1">
            Villa Boutanga · Bafoussam, Cameroun · Merci de votre confiance
        </p>
    </div>
</div>

{{-- Style impression --}}
<style>
    @media print {

        /* Cache tout sauf la facture */
        body * {
            visibility: hidden;
        }

        #invoice-print,
        #invoice-print * {
            visibility: visible;
        }

        #invoice-print {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: none !important;
            border-radius: 0 !important;
        }
    }
</style>

@endsection