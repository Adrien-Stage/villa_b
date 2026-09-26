@extends('layouts.hotel')

@section('title', 'Attestation d\'annulation ' . ($cancellation?->cancellation_number ?? $booking->booking_number))

@section('content')
<style>
@page {
    /* Élimine URL, titre de page, date et numéros de page injectés par le navigateur */
    size: A4 portrait;
    margin: 0mm;
}

@media print {
    html, body {
        background: #ffffff !important;
        background-color: #ffffff !important;
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

    #cancellation-receipt-print {
        margin: 0 auto !important;
        padding: 10mm 14mm !important;
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
        max-width: 100% !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
}
</style>

<div class="max-w-3xl mx-auto pb-12 print:pb-0 print:max-w-none print:w-full">

    {{-- Actions supérieures (masquées impérativement à l'impression) --}}
    <div class="no-print print:hidden flex items-center justify-between gap-4 mb-5">
        <a href="{{ route('bookings.show', $booking) }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-primary/70 hover:text-primary transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Retour à la réservation</span>
        </a>

        <div class="flex items-center gap-3">
            <button onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="printer" class="w-4 h-4"></i>
                <span>Imprimer l'attestation</span>
            </button>
        </div>
    </div>

    {{-- Fiche imprimable (parfaitement calibrée pour 1 seule page A4) --}}
    <div id="cancellation-receipt-print" class="bg-white rounded-2xl border border-secondary/20 shadow-sm p-6 sm:p-8 print:border-none print:shadow-none print:p-0 text-primary">
        
        {{-- En-tête établissement & titre --}}
        <div class="flex items-start justify-between border-b border-secondary/20 pb-4 mb-4">
            <div class="flex items-center gap-3">
                @if(!empty($tenant?->settings['logo']))
                    <div class="w-11 h-11 rounded-xl overflow-hidden border border-secondary/20 flex-shrink-0">
                        <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="{{ $tenant->name }}" class="w-full h-full object-cover">
                    </div>
                @endif
                <div>
                    <h1 class="text-xl font-bold font-heading tracking-tight text-primary leading-tight">
                        {{ $tenant?->name ?? 'HÔTEL & RÉSIDENCE' }}
                    </h1>
                    <p class="text-xs text-primary/60 mt-0.5">
                        {{ $tenant?->city ?? 'Douala' }}, {{ $tenant?->country ?? 'Cameroun' }}
                    </p>
                    @if(!empty($tenant?->settings['general']['mail_from_address']))
                        <p class="text-[11px] text-primary/50">{{ $tenant->settings['general']['mail_from_address'] }}</p>
                    @endif
                </div>
            </div>

            <div class="text-right">
                <span class="inline-block px-2.5 py-0.5 bg-red-100 text-red-800 text-[11px] font-bold uppercase tracking-wider rounded-md mb-1">
                    Réservation Annulée
                </span>
                <p class="font-mono text-sm font-bold text-primary">
                    {{ $cancellation?->cancellation_number ?? 'CAN-ANNULATION' }}
                </p>
                <p class="text-[11px] text-primary/50">
                    Date : {{ $cancellation?->cancelled_at?->locale('fr')->isoFormat('D MMMM YYYY à HH:mm') ?? now()->locale('fr')->isoFormat('D MMMM YYYY à HH:mm') }}
                </p>
            </div>
        </div>

        {{-- Titre officiel de l'acte --}}
        <div class="text-center my-3">
            <h2 class="text-base font-bold font-heading uppercase tracking-wide text-primary">
                Attestation d'Annulation de Séjour
            </h2>
            <p class="text-[11px] text-primary/60 mt-0.5">
                Document officiel constatant l'annulation et le décompte financier conformément aux conditions hôtelières.
            </p>
        </div>

        {{-- Deux colonnes compactes : Réservation d'origine & Client --}}
        <div class="grid grid-cols-2 print:grid-cols-2 gap-4 bg-gray-50/80 rounded-xl p-3.5 border border-secondary/15 mb-3.5 text-xs">
            <div>
                <h3 class="font-semibold text-primary uppercase tracking-wider text-[10px] text-primary/50 mb-1.5">
                    Réservation d'Origine
                </h3>
                <div class="space-y-1">
                    <div class="flex justify-between">
                        <span class="text-primary/60">Numéro de séjour :</span>
                        <span class="font-mono font-bold text-primary">{{ $booking->booking_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-primary/60">Chambre :</span>
                        <span class="font-semibold text-primary">Chambre {{ $booking->room?->number }} ({{ $booking->room?->roomType?->name }})</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-primary/60">Période prévue :</span>
                        <span class="text-primary font-medium">
                            Du {{ $booking->check_in?->format('d/m/Y') }} au {{ $booking->check_out?->format('d/m/Y') }} ({{ $booking->total_nights }} n.)
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-primary/60">Occupants :</span>
                        <span class="text-primary">{{ $booking->adults_count }} adulte(s) {{ $booking->children_count > 0 ? ', ' . $booking->children_count . ' enfant(s)' : '' }}</span>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold text-primary uppercase tracking-wider text-[10px] text-primary/50 mb-1.5">
                    Client & Enregistrement
                </h3>
                <div class="space-y-1">
                    <div class="flex justify-between">
                        <span class="text-primary/60">Nom du client :</span>
                        <span class="font-bold text-primary">{{ $booking->customer?->full_name }}</span>
                    </div>
                    @if($booking->customer?->phone)
                    <div class="flex justify-between">
                        <span class="text-primary/60">Téléphone :</span>
                        <span class="text-primary">{{ $booking->customer->phone }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-primary/60">Annulé par :</span>
                        <span class="text-primary font-medium">{{ $cancellation?->cancelledBy?->name ?? 'Personnel réception' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-primary/60">Motif notifié :</span>
                        <span class="text-primary font-semibold">{{ $cancellation?->reasonLabel() ?? 'Demande du client' }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($cancellation?->reason_description)
            <div class="bg-amber-50/60 border border-amber-200/80 rounded-xl p-2.5 mb-3 text-xs text-amber-900">
                <span class="font-bold block mb-0.5 text-[11px]">Précision sur le motif :</span>
                <p class="italic text-amber-950/80 text-[11px] leading-snug">{{ $cancellation->reason_description }}</p>
            </div>
        @endif

        {{-- Politique d'annulation contractuelle --}}
        <div class="border border-secondary/20 rounded-xl p-3 mb-3.5 bg-white text-xs">
            <div class="flex items-center justify-between mb-1">
                <span class="font-bold text-primary text-[11px]">Politique contractuelle :</span>
                <span class="font-mono text-[10px] text-primary/60 font-semibold bg-gray-100 px-2 py-0.5 rounded">{{ $booking->cancellation_policy_snapshot['code'] ?? ($booking->cancellationPolicy?->code ?? 'FLEX') }}</span>
            </div>
            <p class="text-primary/70 leading-relaxed text-[11px]">
                {{ $booking->cancellation_policy_snapshot['summary'] ?? ($booking->cancellationPolicy?->summaryText() ?? 'Conditions d\'annulation standard de l\'établissement.') }}
            </p>
            @if($booking->free_cancel_until)
                <p class="text-[10px] text-primary/50 mt-1">
                    Échéance d'annulation sans frais : <strong>{{ $booking->free_cancel_until->locale('fr')->isoFormat('D MMMM YYYY à HH:mm') }}</strong>
                </p>
            @endif
        </div>

        {{-- Tableau du décompte financier --}}
        <div class="border border-secondary/20 rounded-xl overflow-hidden mb-4">
            <div class="bg-primary/5 px-4 py-2 border-b border-secondary/20">
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-primary">
                    Bilan et Décompte Financier de l'Annulation
                </h3>
            </div>
            <table class="w-full text-xs text-left">
                <tbody class="divide-y divide-secondary/10">
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2 text-primary/70">Montant total prévu du séjour</td>
                        <td class="px-4 py-2 text-right font-medium text-primary">
                            {{ number_format($booking->total_amount / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2 text-primary/70">Total des sommes encaissées (acompte / prépaiement)</td>
                        <td class="px-4 py-2 text-right font-semibold text-primary">
                            {{ number_format(($cancellation?->deposit_paid ?? $booking->paid_amount) / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    <tr class="bg-red-50/30">
                        <td class="px-4 py-2 text-red-900 font-medium">
                            Frais d'annulation / Pénalité retenue
                            @if($cancellation?->penalty_waived)
                                <span class="ml-2 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                    Exonéré (Geste commercial)
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-bold text-red-700">
                            {{ number_format(($cancellation?->deposit_retained ?? $cancellation?->penalty_amount ?? 0) / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    <tr class="bg-emerald-50/40">
                        <td class="px-4 py-2 text-emerald-900 font-bold">
                            Montant restitué au client
                            @if($cancellation?->refund_method)
                                <span class="block text-[10px] font-normal text-emerald-700 mt-0.5">
                                    Mode de règlement : {{ $cancellation->refundMethodLabel() }}
                                    @if($cancellation->refund_status === 'completed') (Effectué)
                                    @elseif($cancellation->refund_status === 'pending') (En attente d'exécution)
                                    @endif
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-bold text-emerald-800 text-sm">
                            {{ number_format(($cancellation?->refund_amount ?? 0) / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Signatures --}}
        <div class="grid grid-cols-2 gap-8 pt-4 border-t border-secondary/20 text-xs">
            <div class="text-center">
                <p class="font-semibold text-primary/70 mb-9">Le Client / Donneur d'ordre</p>
                <div class="w-36 mx-auto border-b border-primary/30"></div>
                <p class="text-[10px] text-primary/40 mt-1">Signature</p>
            </div>
            <div class="text-center">
                <p class="font-semibold text-primary/70 mb-9">Pour l'Établissement & Réception</p>
                <div class="w-36 mx-auto border-b border-primary/30"></div>
                <p class="text-[10px] text-primary/40 mt-1">Cachet et Signature</p>
            </div>
        </div>

    </div>
</div>
@endsection
