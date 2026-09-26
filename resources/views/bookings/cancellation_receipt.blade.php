@extends('layouts.hotel')

@section('title', 'Attestation d\'annulation ' . ($cancellation?->cancellation_number ?? $booking->booking_number))

@section('content')
<div class="max-w-3xl mx-auto pb-12">

    {{-- Actions supérieures (masquées à l'impression) --}}
    <div class="no-print flex items-center justify-between gap-4 mb-6">
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

    {{-- Fiche imprimable --}}
    <div class="bg-white rounded-2xl border border-secondary/20 shadow-sm p-8 sm:p-12 print:border-none print:shadow-none print:p-0 text-primary">
        
        {{-- En-tête établissement & titre --}}
        <div class="flex items-start justify-between border-b border-secondary/20 pb-6 mb-6">
            <div>
                <h1 class="text-2xl font-bold font-heading tracking-tight text-primary">
                    {{ $tenant?->name ?? 'HÔTEL & RÉSIDENCE' }}
                </h1>
                <p class="text-xs text-primary/60 mt-1">
                    {{ $tenant?->city ?? 'Douala' }}, {{ $tenant?->country ?? 'Cameroun' }}
                </p>
                @if(!empty($tenant?->settings['general']['mail_from_address']))
                    <p class="text-[11px] text-primary/50">{{ $tenant->settings['general']['mail_from_address'] }}</p>
                @endif
            </div>

            <div class="text-right">
                <span class="inline-block px-3 py-1 bg-red-100 text-red-800 text-xs font-bold uppercase tracking-wider rounded-lg mb-2">
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

        {{-- Titre de l'acte --}}
        <div class="text-center my-6">
            <h2 class="text-lg font-bold font-heading uppercase tracking-wide text-primary">
                Attestation d'Annulation de Séjour
            </h2>
            <p class="text-xs text-primary/60 mt-1">
                Document officiel constatant l'annulation et le décompte financier conformément aux conditions hôtelières.
            </p>
        </div>

        {{-- Deux colonnes : Réservation d'origine & Client --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-gray-50/70 rounded-xl p-5 border border-secondary/15 mb-6 text-xs">
            <div>
                <h3 class="font-semibold text-primary uppercase tracking-wider text-[11px] text-primary/50 mb-2">
                    Réservation d'Origine
                </h3>
                <div class="space-y-1.5">
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
                            Du {{ $booking->check_in?->format('d/m/Y') }} au {{ $booking->check_out?->format('d/m/Y') }} ({{ $booking->total_nights }} nuit(s))
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-primary/60">Occupants :</span>
                        <span class="text-primary">{{ $booking->adults_count }} adulte(s) {{ $booking->children_count > 0 ? ', ' . $booking->children_count . ' enfant(s)' : '' }}</span>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="font-semibold text-primary uppercase tracking-wider text-[11px] text-primary/50 mb-2">
                    Client & Enregistrement
                </h3>
                <div class="space-y-1.5">
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
            <div class="bg-amber-50/60 border border-amber-200/80 rounded-xl p-4 mb-6 text-xs text-amber-900">
                <span class="font-bold block mb-1">Précision sur le motif :</span>
                <p class="italic text-amber-950/80">{{ $cancellation->reason_description }}</p>
            </div>
        @endif

        {{-- Politique d'annulation appliquée --}}
        <div class="border border-secondary/20 rounded-xl p-4 mb-6 bg-white text-xs">
            <div class="flex items-center justify-between mb-2">
                <span class="font-bold text-primary">Politique d'annulation contractuelle :</span>
                <span class="font-mono text-[11px] text-primary/50 font-semibold">{{ $booking->cancellation_policy_snapshot['code'] ?? ($booking->cancellationPolicy?->code ?? 'FLEX') }}</span>
            </div>
            <p class="text-primary/70 leading-relaxed">
                {{ $booking->cancellation_policy_snapshot['summary'] ?? ($booking->cancellationPolicy?->summaryText() ?? 'Conditions d\'annulation standard de l\'établissement.') }}
            </p>
            @if($booking->free_cancel_until)
                <p class="text-[11px] text-primary/50 mt-2">
                    Date limite d'annulation sans frais : <strong>{{ $booking->free_cancel_until->locale('fr')->isoFormat('D MMMM YYYY à HH:mm') }}</strong>
                </p>
            @endif
        </div>

        {{-- Tableau du décompte financier --}}
        <div class="border border-secondary/20 rounded-xl overflow-hidden mb-8">
            <div class="bg-primary/5 px-5 py-3 border-b border-secondary/20">
                <h3 class="text-xs font-bold uppercase tracking-wider text-primary">
                    Bilan et Décompte Financier de l'Annulation
                </h3>
            </div>
            <table class="w-full text-xs text-left">
                <tbody class="divide-y divide-secondary/10">
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3 text-primary/70">Montant total prévu du séjour</td>
                        <td class="px-5 py-3 text-right font-medium text-primary">
                            {{ number_format($booking->total_amount / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3 text-primary/70">Total des sommes encaissées (acompte / prépaiement)</td>
                        <td class="px-5 py-3 text-right font-semibold text-primary">
                            {{ number_format(($cancellation?->deposit_paid ?? $booking->paid_amount) / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    <tr class="bg-red-50/30">
                        <td class="px-5 py-3 text-red-900 font-medium">
                            Frais d'annulation / Pénalité retenue
                            @if($cancellation?->penalty_waived)
                                <span class="ml-2 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full">
                                    Exonéré (Geste commercial)
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right font-bold text-red-700">
                            {{ number_format(($cancellation?->deposit_retained ?? $cancellation?->penalty_amount ?? 0) / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    <tr class="bg-emerald-50/40">
                        <td class="px-5 py-3 text-emerald-900 font-bold">
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
                        <td class="px-5 py-3 text-right font-bold text-emerald-800 text-sm">
                            {{ number_format(($cancellation?->refund_amount ?? 0) / 100, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Signatures --}}
        <div class="grid grid-cols-2 gap-8 pt-6 border-t border-secondary/20 text-xs">
            <div class="text-center">
                <p class="font-semibold text-primary/70 mb-12">Le Client / Donneur d'ordre</p>
                <div class="w-36 mx-auto border-b border-primary/30"></div>
                <p class="text-[10px] text-primary/40 mt-1">Signature</p>
            </div>
            <div class="text-center">
                <p class="font-semibold text-primary/70 mb-12">Pour l'Établissement & Réception</p>
                <div class="w-36 mx-auto border-b border-primary/30"></div>
                <p class="text-[10px] text-primary/40 mt-1">Cachet et Signature</p>
            </div>
        </div>

    </div>
</div>
@endsection
