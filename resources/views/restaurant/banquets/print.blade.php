@extends('layouts.hotel')

@section('title', 'Fiche Banquet ' . $banquet->reference)

@section('content')
@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $titreDocument = match ($banquet->status) {
        'devis' => 'DEVIS DE BANQUET / RÉSERVATION DE SALLE',
        'confirme', 'realise' => 'CONFIRMATION DE BANQUET & REÇU D\'ACOMPTE',
        'solde' => 'FACTURE & REÇU DE BANQUET SOLDÉ',
        'annule' => 'AVIS D\'ANNULATION DE BANQUET',
        default => 'FICHE DE BANQUET',
    };
@endphp

<style>
@page {
    size: A4 portrait;
    margin: 0mm;
}
@media print {
    html, body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
        min-height: 0 !important;
        overflow: visible !important;
    }
    .no-print,
    header,
    aside,
    nav,
    button,
    #ai-assistant-wrapper,
    #system-toast-container {
        display: none !important;
    }
    #banquet-print-sheet {
        margin: 0 auto !important;
        padding: 12mm 16mm !important;
        -webkit-box-decoration-break: clone;
        box-decoration-break: clone;
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<div class="max-w-4xl mx-auto pb-12 print:pb-0 print:max-w-none print:w-full">
    <div class="no-print print:hidden flex items-center justify-between gap-4 mb-5">
        <a href="{{ route('restaurant.banquets.show', $banquet) }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-primary/70 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Retour à la fiche banquet</span>
        </a>

        <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm cursor-pointer">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Imprimer le document</span>
        </button>
    </div>

    <div id="banquet-print-sheet" class="bg-white rounded-2xl border border-secondary/20 shadow-sm p-6 sm:p-8 print:border-none print:shadow-none print:p-0 text-primary">
        {{-- En-tête établissement & référence --}}
        <div class="flex items-start justify-between border-b border-secondary/20 pb-4 mb-5">
            <div class="flex items-center gap-3">
                @if(!empty($tenant?->settings['logo']))
                    <div class="w-12 h-12 rounded-xl overflow-hidden border border-secondary/20 flex-shrink-0">
                        <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="{{ $tenant->name }}" class="w-full h-full object-cover">
                    </div>
                @endif
                <div>
                    <h1 class="text-xl font-bold font-heading tracking-tight text-primary leading-tight">
                        {{ $tenant?->name ?? 'HÔTEL & RÉSIDENCE' }}
                    </h1>
                    <p class="text-xs text-primary/60 mt-0.5">
                        {{ $tenant?->city ?? '' }}{{ !empty($tenant?->country) ? ', ' . $tenant->country : '' }}
                    </p>
                    @if($banquet->pointOfSale)
                        <p class="text-xs font-medium text-primary/80 mt-0.5">Point de vente : {{ $banquet->pointOfSale->name }}</p>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <span class="inline-block rounded-full border border-secondary/30 bg-secondary/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wide text-primary">
                    {{ $banquet->libelleStatut() }}
                </span>
                <p class="text-base font-bold font-mono text-primary mt-1.5">{{ $banquet->reference }}</p>
                <p class="text-[11px] text-primary/55">Édité le {{ now()->format('d/m/Y à H:i') }}</p>
            </div>
        </div>

        <h2 class="text-center text-sm font-bold tracking-wider uppercase text-primary border-b border-secondary/15 pb-2 mb-5">
            {{ $titreDocument }}
        </h2>

        {{-- Informations Client & Événement --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5 text-xs">
            <div class="rounded-lg border border-secondary/15 bg-secondary/5 p-3.5 space-y-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary/60 mb-1.5">Client / Organisateur</p>
                <p><span class="font-semibold">Nom :</span> {{ $banquet->client_name }}</p>
                @if($banquet->client_phone)
                    <p><span class="font-semibold">Téléphone :</span> {{ $banquet->client_phone }}</p>
                @endif
                @if($banquet->client_email)
                    <p><span class="font-semibold">Email :</span> {{ $banquet->client_email }}</p>
                @endif
                <p><span class="font-semibold">Origine demande :</span> {{ $banquet->libelleSource() }}</p>
                @if($banquet->booking)
                    <p><span class="font-semibold">Séjour lié :</span> {{ $banquet->booking->booking_number }}@if($banquet->booking->room) (Chambre {{ $banquet->booking->room->number }})@endif</p>
                @endif
            </div>

            <div class="rounded-lg border border-secondary/15 bg-secondary/5 p-3.5 space-y-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary/60 mb-1.5">Détails de l'événement</p>
                <p><span class="font-semibold">Événement :</span> {{ $banquet->title }}</p>
                <p><span class="font-semibold">Date :</span> {{ $banquet->event_date->format('d/m/Y') }}</p>
                @if($banquet->start_time)
                    <p><span class="font-semibold">Horaires :</span> {{ $banquet->start_time }}@if($banquet->end_time) – {{ $banquet->end_time }}@endif</p>
                @endif
                <p><span class="font-semibold">Salle :</span> {{ $banquet->space?->name ?? 'À préciser' }}@if($banquet->space?->capacity) ({{ $banquet->space->capacity }} places)@endif</p>
                <p><span class="font-semibold">Effectif :</span> {{ $banquet->covers }} couvert(s)</p>
            </div>
        </div>

        {{-- Décompte financier --}}
        <div class="mb-5 overflow-hidden rounded-lg border border-secondary/20">
            <table class="w-full text-left text-xs">
                <thead class="bg-secondary/10 text-[10px] uppercase tracking-wider text-primary/70 border-b border-secondary/20">
                    <tr>
                        <th scope="col" class="px-3.5 py-2.5">Désignation</th>
                        <th scope="col" class="px-3.5 py-2.5 text-right">Quantité</th>
                        <th scope="col" class="px-3.5 py-2.5 text-right">Prix unitaire</th>
                        <th scope="col" class="px-3.5 py-2.5 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    <tr>
                        <td class="px-3.5 py-2.5 font-medium">Forfait banquet / Menu convenu</td>
                        <td class="px-3.5 py-2.5 text-right tabular-nums">{{ $banquet->covers }}</td>
                        <td class="px-3.5 py-2.5 text-right tabular-nums">{{ $fcfa($banquet->price_per_cover) }}</td>
                        <td class="px-3.5 py-2.5 text-right tabular-nums font-semibold">{{ $fcfa($banquet->covers * $banquet->price_per_cover) }}</td>
                    </tr>
                    @if($banquet->extras_amount > 0)
                        <tr>
                            <td class="px-3.5 py-2.5 font-medium">Suppléments (salle, équipements, prestations additionnelles)</td>
                            <td class="px-3.5 py-2.5 text-right tabular-nums">1</td>
                            <td class="px-3.5 py-2.5 text-right tabular-nums">{{ $fcfa($banquet->extras_amount) }}</td>
                            <td class="px-3.5 py-2.5 text-right tabular-nums font-semibold">{{ $fcfa($banquet->extras_amount) }}</td>
                        </tr>
                    @endif
                </tbody>
                <tfoot class="border-t border-secondary/25 bg-secondary/5 text-xs">
                    <tr>
                        <td colspan="3" class="px-3.5 py-2 text-right font-semibold">Total TTC :</td>
                        <td class="px-3.5 py-2 text-right font-bold tabular-nums">{{ $fcfa($banquet->total_amount) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-3.5 py-1.5 text-right text-primary/70">Acompte requis pour confirmation :</td>
                        <td class="px-3.5 py-1.5 text-right tabular-nums">{{ $fcfa($banquet->deposit_required) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-3.5 py-1.5 text-right text-emerald-700 font-semibold">Total encaissé :</td>
                        <td class="px-3.5 py-1.5 text-right text-emerald-700 font-bold tabular-nums">{{ $fcfa($encaisse) }}</td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-3.5 py-2 text-right font-bold">Reste à régler :</td>
                        <td class="px-3.5 py-2 text-right font-bold tabular-nums">{{ $fcfa($resteDu) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($banquet->menu)
            <div class="mb-4 rounded-lg border border-secondary/15 p-3.5 text-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary/60 mb-1">Menu & Prestations détaillées</p>
                <p class="whitespace-pre-line text-primary/85">{{ $banquet->menu }}</p>
            </div>
        @endif

        @if($banquet->notes)
            <div class="mb-4 rounded-lg border border-secondary/15 p-3.5 text-xs">
                <p class="text-[10px] font-bold uppercase tracking-wider text-primary/60 mb-1">Remarques / Consignes</p>
                <p class="whitespace-pre-line text-primary/80">{{ $banquet->notes }}</p>
            </div>
        @endif

        {{-- Détail des règlements / reçus --}}
        <div class="mb-6">
            <p class="text-[10px] font-bold uppercase tracking-wider text-primary/60 mb-2">Historique des règlements encaissés</p>
            @if($banquet->payments->isEmpty())
                <p class="text-xs text-primary/50 italic">Aucun règlement enregistré à ce jour.</p>
            @else
                <div class="overflow-hidden rounded-lg border border-secondary/15">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-secondary/5 text-[10px] uppercase tracking-wider text-primary/70 border-b border-secondary/15">
                            <tr>
                                <th scope="col" class="px-3 py-2">Reçu</th>
                                <th scope="col" class="px-3 py-2">Date</th>
                                <th scope="col" class="px-3 py-2">Nature</th>
                                <th scope="col" class="px-3 py-2">Mode · Caisse</th>
                                <th scope="col" class="px-3 py-2">Encaissé par</th>
                                <th scope="col" class="px-3 py-2 text-right">Montant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-secondary/10">
                            @foreach($banquet->payments as $reglement)
                                <tr>
                                    <td class="px-3 py-2 font-mono font-semibold">{{ $reglement->receiptReference() }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $reglement->paid_at->format('d/m/Y H:i') }}</td>
                                    <td class="px-3 py-2">{{ $reglement->libelleKind() }}</td>
                                    <td class="px-3 py-2">
                                        {{ $reglement->methodLabel() }}
                                        @if($reglement->cashRegisterSession)
                                            · {{ $reglement->cashRegisterSession->module === 'reception' ? 'Réception' : 'Restaurant' }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">{{ $reglement->paidBy?->name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $fcfa($reglement->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Signatures --}}
        <div class="grid grid-cols-2 gap-8 pt-4 border-t border-secondary/20 text-xs text-primary/75">
            <div>
                <p class="font-semibold mb-10">Pour l'établissement ({{ $banquet->createdBy?->name ?? 'Réception / Direction' }})</p>
                <div class="border-b border-dashed border-secondary/40 w-48"></div>
            </div>
            <div class="text-right">
                <p class="font-semibold mb-10">Bon pour accord — Le Client</p>
                <div class="border-b border-dashed border-secondary/40 w-48 ml-auto"></div>
            </div>
        </div>
    </div>
</div>
@endsection
