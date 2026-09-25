@extends('layouts.hotel')

@section('title', 'Récapitulatif de la réservation ' . $booking->booking_number)

@php
    $codeNotifier = app(\App\Services\CheckinCodeNotifier::class);
    $codeRecipient = $codeNotifier->recipient($booking, $booking->code_recipient);
    $resolvedType = $codeNotifier->resolvedType($booking, $booking->code_recipient);
    $codeRecipientLabel = \App\Services\CheckinCodeNotifier::RECIPIENTS[$resolvedType] ?? 'Client';
    $codeRecipientEmail = trim((string) $codeRecipient?->email) ?: null;
    $checkinCode = $booking->checkin_code ?? session('checkin_code');
@endphp

@section('content')
<div class="max-w-6xl mx-auto pb-12"
     x-data="{
         showCodeModal: false,
         copied: false,
         copyCode(code) {
             if (!code) return;
             navigator.clipboard.writeText(code).then(() => {
                 this.copied = true;
                 setTimeout(() => this.copied = false, 2500);
             });
         }
     }">

    {{-- ── En-tête & fil d'étapes (Étape 4 : Récapitulatif) ─────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 no-print">
        <div>
            <h1 class="text-2xl font-semibold text-primary font-heading">Récapitulatif de la réservation</h1>
            <p class="text-sm text-primary/60 mt-1">Étape 4 — Réservation enregistrée et synthèse du séjour</p>
        </div>

        <div class="flex items-center gap-2.5">
            @foreach([['Client', true], ['Chambre & dates', true], ['Confirmation & Paiement', true], ['Récapitulatif', false]] as $i => [$libelle, $fait])
                @if($i > 0)
                    <div class="w-6 h-px bg-primary/15"></div>
                @endif
                <div class="flex items-center gap-2">
                    @if($fait)
                        <div class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-sm">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        </div>
                        <span class="text-xs font-medium text-primary/50 hidden sm:inline">{{ $libelle }}</span>
                    @else
                        <div class="w-7 h-7 rounded-full bg-primary text-white flex items-center justify-center text-xs font-semibold shadow-sm">4</div>
                        <span class="text-xs font-semibold text-primary hidden sm:inline">{{ $libelle }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Notification de succès si redirection avec message --}}
    @if(session('success'))
        <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl p-4 flex items-start gap-3 shadow-sm no-print">
            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0 mt-0.5">
                <i data-lucide="check-circle-2" class="w-5 h-5"></i>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-sm text-emerald-900">Réservation créée et acompte enregistré avec succès !</h3>
                <p class="text-xs text-emerald-700 mt-0.5">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    {{-- ── Bandeau d'en-tête de la réservation ────────────────────────────── --}}
    <div class="bg-white rounded-2xl border border-secondary/20 shadow-sm p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-xs uppercase tracking-widest font-semibold text-primary/50">Dossier de réservation</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $booking->status->color() }}">
                        {{ $booking->status->label() }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-surface-dark/10 text-primary">
                        Source : {{ $booking->sourceLabel() }}
                    </span>
                </div>
                <h2 class="text-2xl font-bold font-heading text-primary flex items-center gap-2">
                    {{ $booking->booking_number }}
                </h2>
                <p class="text-xs text-primary/60">
                    Enregistrée le {{ $booking->created_at->format('d/m/Y à H:i') }}
                    @if($booking->creator)
                        par <span class="font-medium text-primary">{{ $booking->creator->name }}</span>
                    @endif
                </p>
            </div>

            <div class="flex items-center gap-2.5 flex-wrap no-print">
                <button type="button" onclick="window.print()"
                        class="px-4 py-2 bg-accent/30 hover:bg-accent/50 text-primary rounded-xl font-medium text-xs flex items-center gap-2 transition-all shadow-sm">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Imprimer le récapitulatif
                </button>
                <a href="{{ route('bookings.show', $booking) }}"
                   class="px-4 py-2 bg-primary hover:bg-surface-dark text-white rounded-xl font-medium text-xs flex items-center gap-2 transition-all shadow-sm">
                    <i data-lucide="folder-open" class="w-4 h-4"></i>
                    Dossier complet
                </a>
            </div>
        </div>
    </div>

    {{-- ── Grille principale de synthèse ──────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- Colonne Principale (8 colonnes) --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Carte 1 : Séjour & Hébergement --}}
            <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm overflow-hidden">
                <header class="px-6 py-4 border-b border-secondary/15 bg-accent/10 flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-accent/40 text-primary flex items-center justify-center flex-shrink-0">
                        <i data-lucide="bed-double" class="w-4 h-4"></i>
                    </span>
                    <h3 class="font-heading font-semibold text-primary text-sm">Hébergement & Séjour</h3>
                </header>

                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Chambre assignée</p>
                        <p class="text-base font-bold text-primary">
                            Chambre {{ $booking->room->number }}
                        </p>
                        <p class="text-xs text-primary/70">
                            Type : {{ $booking->room->roomType->name }} (Étage {{ $booking->room->floor ?? 'RDC' }})
                        </p>
                    </div>

                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Durée du séjour</p>
                        <p class="text-base font-bold text-primary">
                            {{ $booking->total_nights }} nuit{{ $booking->total_nights > 1 ? 's' : '' }}
                        </p>
                        <p class="text-xs text-primary/70">
                            {{ $booking->adults_count }} adulte{{ $booking->adults_count > 1 ? 's' : '' }}
                            @if($booking->children_count > 0)
                                , {{ $booking->children_count }} enfant{{ $booking->children_count > 1 ? 's' : '' }}
                            @endif
                        </p>
                    </div>

                    <div class="space-y-1 border-t sm:border-t-0 pt-3 sm:pt-0 border-secondary/10">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Arrivée prévue (Check-in)</p>
                        <p class="text-sm font-semibold text-primary flex items-center gap-1.5">
                            <i data-lucide="calendar" class="w-4 h-4 text-emerald-600"></i>
                            {{ $booking->check_in->format('d/m/Y') }}
                            @if(!empty($booking->check_in_time))
                                <span class="text-xs font-normal text-primary/70">à {{ $booking->check_in_time }}</span>
                            @endif
                        </p>
                    </div>

                    <div class="space-y-1 border-t sm:border-t-0 pt-3 sm:pt-0 border-secondary/10">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Départ prévu (Check-out)</p>
                        <p class="text-sm font-semibold text-primary flex items-center gap-1.5">
                            <i data-lucide="calendar" class="w-4 h-4 text-amber-600"></i>
                            {{ $booking->check_out->format('d/m/Y') }}
                            <span class="text-xs font-normal text-primary/70">à 12:00</span>
                        </p>
                    </div>

                    <div class="sm:col-span-2 pt-3 border-t border-secondary/10 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-primary/60">Formule sélectionnée :</span>
                            <span class="font-semibold text-primary">{{ $booking->roomPackage?->name ?? 'Hébergement seul (Standard)' }}</span>
                        </div>
                        @if($booking->notes)
                            <div class="bg-accent/10 rounded-xl p-3 border border-secondary/15 text-xs text-primary/80">
                                <span class="font-semibold text-primary block mb-0.5">Notes & demandes particulières :</span>
                                {{ $booking->notes }}
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Carte 2 : Client & Mandataire --}}
            <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm overflow-hidden">
                <header class="px-6 py-4 border-b border-secondary/15 bg-accent/10 flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-accent/40 text-primary flex items-center justify-center flex-shrink-0">
                        <i data-lucide="user-check" class="w-4 h-4"></i>
                    </span>
                    <h3 class="font-heading font-semibold text-primary text-sm">Informations Client & Réservation</h3>
                </header>

                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="space-y-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Client principal (occupant)</p>
                        <p class="text-base font-bold text-primary">
                            {{ $booking->customer->full_name }}
                        </p>
                        <p class="text-xs text-primary/70 flex items-center gap-1">
                            <i data-lucide="phone" class="w-3.5 h-3.5 text-primary/40"></i>
                            {{ $booking->customer->phone ?? 'Aucun téléphone renseigné' }}
                        </p>
                        @if($booking->customer->email)
                            <p class="text-xs text-primary/70 flex items-center gap-1">
                                <i data-lucide="mail" class="w-3.5 h-3.5 text-primary/40"></i>
                                {{ $booking->customer->email }}
                            </p>
                        @endif
                    </div>

                    <div class="space-y-1 border-t sm:border-t-0 pt-3 sm:pt-0 border-secondary/10">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Identité & Documents</p>
                        @if($booking->customer->id_number)
                            <p class="text-xs text-primary font-medium">
                                {{ strtoupper($booking->customer->id_type ?? 'Pièce') }} : {{ $booking->customer->id_number }}
                            </p>
                        @else
                            <p class="text-xs text-primary/50 italic">Pièce d'identité non renseignée</p>
                        @endif
                        @if($booking->customer->nationality)
                            <p class="text-xs text-primary/70">Nationalité : {{ $booking->customer->nationality }}</p>
                        @endif
                    </div>

                    @if($booking->booker)
                        <div class="sm:col-span-2 pt-3 border-t border-secondary/10 bg-accent/5 p-3 rounded-xl border border-secondary/15">
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/60">Réservation effectuée par un mandataire</p>
                            <p class="text-sm font-semibold text-primary mt-1">
                                {{ $booking->booker->full_name }}
                                <span class="text-xs font-normal text-primary/60">— Mandataire / Organisateur</span>
                            </p>
                            <div class="mt-1 flex flex-wrap gap-4 text-xs text-primary/70">
                                @if($booking->booker->phone)
                                    <span class="flex items-center gap-1"><i data-lucide="phone" class="w-3 h-3 text-primary/40"></i> {{ $booking->booker->phone }}</span>
                                @endif
                                @if($booking->booker->email)
                                    <span class="flex items-center gap-1"><i data-lucide="mail" class="w-3 h-3 text-primary/40"></i> {{ $booking->booker->email }}</span>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($booking->partnerOrganization)
                        <div class="sm:col-span-2 pt-3 border-t border-secondary/10">
                            <div class="flex items-center gap-2">
                                <i data-lucide="building-2" class="w-4 h-4 text-primary/60"></i>
                                <span class="text-xs font-medium text-primary">
                                    Convention partenaire : <span class="font-semibold">{{ $booking->partnerOrganization->name }}</span>
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Carte 3 : Règlement & Détail Financier --}}
            <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm overflow-hidden">
                <header class="px-6 py-4 border-b border-secondary/15 bg-accent/10 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg bg-accent/40 text-primary flex items-center justify-center flex-shrink-0">
                            <i data-lucide="receipt" class="w-4 h-4"></i>
                        </span>
                        <h3 class="font-heading font-semibold text-primary text-sm">Décompte Financier & Règlements</h3>
                    </div>
                    <span class="text-xs text-primary/60 font-medium">Devise : FCFA</span>
                </header>

                <div class="p-6 space-y-4">
                    {{-- Lignes de tarification --}}
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between py-1 border-b border-secondary/10">
                            <span class="text-primary/70">
                                Tarif hébergement ({{ $booking->total_nights }} nuit{{ $booking->total_nights > 1 ? 's' : '' }} × {{ number_format($booking->price_per_night, 0, ',', ' ') }} FCFA)
                            </span>
                            <span class="font-semibold text-primary">{{ number_format($booking->total_room_amount, 0, ',', ' ') }} FCFA</span>
                        </div>

                        @if($booking->package_amount > 0)
                            <div class="flex justify-between py-1 border-b border-secondary/10">
                                <span class="text-primary/70">Formule / Prestations associées</span>
                                <span class="font-semibold text-primary">+ {{ number_format($booking->package_amount, 0, ',', ' ') }} FCFA</span>
                            </div>
                        @endif

                        @if($booking->tax_amount > 0)
                            <div class="flex justify-between py-1 border-b border-secondary/10">
                                <span class="text-primary/70">Taxe de séjour</span>
                                <span class="font-semibold text-primary">+ {{ number_format($booking->tax_amount, 0, ',', ' ') }} FCFA</span>
                            </div>
                        @endif

                        @if($booking->discount_amount > 0)
                            <div class="flex justify-between py-1 border-b border-secondary/10 text-emerald-700">
                                <span>Remise appliquée</span>
                                <span class="font-semibold">- {{ number_format($booking->discount_amount, 0, ',', ' ') }} FCFA</span>
                            </div>
                        @endif

                        <div class="flex justify-between py-2 text-sm font-bold text-primary bg-accent/20 px-3 rounded-xl mt-2">
                            <span>Montant Total du Séjour (TTC)</span>
                            <span>{{ number_format($booking->total_amount, 0, ',', ' ') }} FCFA</span>
                        </div>
                    </div>

                    {{-- Historique des paiements --}}
                    <div class="pt-4 border-t border-secondary/15">
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-primary/50 mb-3">
                            Paiements & Acomptes enregistrés
                        </h4>

                        @if($booking->payments->isNotEmpty())
                            <div class="divide-y divide-secondary/10 border border-secondary/15 rounded-xl overflow-hidden">
                                @foreach($booking->payments as $payment)
                                    <div class="p-3 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800">
                                                    {{ $payment->methodLabel() }}
                                                </span>
                                                <span class="text-primary/60">{{ $payment->created_at->format('d/m/Y H:i') }}</span>
                                            </div>
                                            @if($payment->reference)
                                                <p class="text-primary/50 text-[11px]">Réf transaction : {{ $payment->reference }}</p>
                                            @endif
                                            @if($payment->processedBy)
                                                <p class="text-primary/50 text-[11px]">Encaissé par : {{ $payment->processedBy->name }}</p>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <span class="text-sm font-bold text-emerald-700">
                                                {{ number_format($payment->amount, 0, ',', ' ') }} FCFA
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-primary/50 italic py-2">Aucun paiement encaissé pour le moment.</p>
                        @endif
                    </div>

                    {{-- Résumé Règlement & Solde --}}
                    <div class="grid grid-cols-2 gap-4 pt-3 border-t border-secondary/15">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3.5">
                            <span class="text-[10px] uppercase font-bold text-emerald-700 tracking-wider block">Acompte / Total versé</span>
                            <span class="text-lg font-bold text-emerald-900 mt-0.5 block">
                                {{ number_format($booking->paid_amount, 0, ',', ' ') }} FCFA
                            </span>
                        </div>

                        <div class="@if($booking->balance_due > 0) bg-amber-50 border border-amber-200 @else bg-gray-50 border border-gray-200 @endif rounded-xl p-3.5">
                            <span class="text-[10px] uppercase font-bold @if($booking->balance_due > 0) text-amber-700 @else text-gray-500 @endif tracking-wider block">
                                Solde restant dû
                            </span>
                            <span class="text-lg font-bold @if($booking->balance_due > 0) text-amber-900 @else text-gray-700 @endif mt-0.5 block">
                                {{ number_format($booking->balance_due, 0, ',', ' ') }} FCFA
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Colonne Latérale Droite (4 colonnes) --}}
        <div class="lg:col-span-4 space-y-6">

            {{-- Carte Sécurité & Code Check-in --}}
            <section class="bg-white rounded-2xl border-2 border-emerald-500/30 shadow-md overflow-hidden"
                     x-data="envoiCodeCheckin('{{ route('bookings.checkin_code.send', $booking) }}', '{{ csrf_token() }}')">
                <header class="bg-emerald-600 px-5 py-4 text-white flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i data-lucide="shield-check" class="w-5 h-5 text-white"></i>
                        <h3 class="font-heading font-semibold text-sm">Code de Check-in</h3>
                    </div>
                    <span class="text-[10px] uppercase tracking-wider bg-white/20 px-2 py-0.5 rounded-full font-bold">Sécurisé</span>
                </header>

                <div class="p-5 text-center space-y-4">
                    <p class="text-xs text-primary/60 text-left">
                        Ce code unique est indispensable lors de la remise des clés et de l'enregistrement de l'arrivée du client.
                    </p>

                    @if($checkinCode)
                        <div class="bg-gray-50 border-2 border-primary/15 rounded-xl p-4 shadow-inner relative group">
                            <span class="text-3xl font-mono tracking-widest font-black text-primary select-all">
                                {{ $checkinCode }}
                            </span>
                            <button type="button" @click="copyCode('{{ $checkinCode }}')"
                                    class="mt-2 text-xs font-medium text-primary/70 hover:text-primary flex items-center justify-center gap-1.5 mx-auto transition-colors">
                                <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                <span x-text="copied ? 'Code copié dans le presse-papier !' : 'Copier le code'"></span>
                            </button>
                        </div>
                    @else
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-800">
                            Aucun code généré pour ce statut de réservation.
                        </div>
                    @endif

                    {{-- Destinataire du code --}}
                    <div class="rounded-xl border border-secondary/25 bg-accent/10 px-4 py-3 text-left">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/50">Destinataire désigné</p>
                        <p class="text-sm font-medium text-primary mt-0.5">
                            {{ $codeRecipientLabel }}
                            <span class="text-primary/60 font-normal">— {{ $codeRecipient?->full_name ?? 'Inconnu' }}</span>
                        </p>
                        @if($codeRecipientEmail)
                            <p class="text-xs text-primary/60 mt-0.5 truncate">{{ $codeRecipientEmail }}</p>
                        @else
                            <p class="text-xs text-amber-700 mt-0.5">
                                Aucune adresse email enregistrée.
                            </p>
                        @endif
                    </div>

                    {{-- Actions d'envoi et d'affichage du modal --}}
                    <div class="space-y-2 pt-2 no-print">
                        @if($codeRecipientEmail)
                            <button type="button" @click="envoyer()" :disabled="envoi"
                                    class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl transition-all shadow-sm disabled:opacity-50 flex items-center justify-center gap-2">
                                <i data-lucide="mail" class="w-4 h-4"></i>
                                <span x-text="envoi ? 'Envoi en cours…' : (dejaEnvoye ? 'Renvoyer le code par email' : 'Envoyer le code par email')"></span>
                            </button>

                            <p x-show="message" x-cloak style="display:none;"
                               class="text-xs rounded-lg px-3 py-2 text-left"
                               :class="succes ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                               x-text="message"></p>
                        @endif

                        <button type="button" @click="showCodeModal = true"
                                class="w-full py-2.5 bg-accent/40 hover:bg-accent/60 text-primary text-xs font-semibold rounded-xl transition-all flex items-center justify-center gap-2">
                            <i data-lucide="maximize-2" class="w-4 h-4"></i>
                            Afficher le popup du code généré
                        </button>
                    </div>
                </div>
            </section>

            {{-- Carte Actions & Poursuite --}}
            <section class="bg-white rounded-2xl border border-secondary/20 shadow-sm p-5 space-y-3 no-print">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-primary/50 mb-2">Actions rapides</h4>

                <a href="{{ route('bookings.show', $booking) }}"
                   class="w-full py-3 bg-primary hover:bg-surface-dark text-white text-xs font-semibold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2">
                    <i data-lucide="folder-check" class="w-4 h-4"></i>
                    Accéder au dossier complet
                </a>

                <button type="button" onclick="window.print()"
                        class="w-full py-2.5 bg-accent/25 hover:bg-accent/40 text-primary text-xs font-semibold rounded-xl transition-all flex items-center justify-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    Imprimer ce récapitulatif
                </button>

                <div class="pt-2 border-t border-secondary/15 flex flex-col gap-2">
                    <a href="{{ route('bookings.create') }}"
                       class="text-xs font-medium text-primary/70 hover:text-primary flex items-center gap-1.5 py-1 transition-colors">
                        <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i>
                        Nouvelle réservation
                    </a>
                    <a href="{{ route('bookings.index') }}"
                       class="text-xs font-medium text-primary/70 hover:text-primary flex items-center gap-1.5 py-1 transition-colors">
                        <i data-lucide="list" class="w-3.5 h-3.5"></i>
                        Liste de toutes les réservations
                    </a>
                </div>
            </section>
        </div>
    </div>

    {{-- ── Modal du code généré (Ouvert sur demande via showCodeModal) ─────── --}}
    <div x-show="showCodeModal" x-cloak style="display: none;"
         class="fixed inset-0 z-[70] flex items-center justify-center no-print"
         style="background: rgba(15,2,1,0.7); backdrop-filter: blur(8px);">
        <div @click.away="showCodeModal = false"
             class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden transform transition-all">
            <div class="bg-green-600 p-6 text-center">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-lg">
                    <i data-lucide="check" class="w-8 h-8 text-green-600"></i>
                </div>
                <h2 class="text-xl font-heading font-bold text-white">Réservation Confirmée !</h2>
                <p class="text-green-100 text-sm mt-1">{{ $booking->booking_number }} — Acompte enregistré</p>
            </div>

            <div class="p-8 text-center" x-data="envoiCodeCheckin('{{ route('bookings.checkin_code.send', $booking) }}', '{{ csrf_token() }}')">
                <h3 class="text-sm font-semibold text-primary/70 uppercase tracking-wider mb-2">Code de sécurité Check-in</h3>
                <p class="text-xs text-primary/50 mb-6">
                    Veuillez communiquer ce code unique au client ou au mandataire. Ce code sera exigé lors de la remise des clés.
                </p>

                <div class="bg-gray-100 rounded-xl p-4 mb-6 inline-block shadow-inner border border-gray-200">
                    <span class="text-5xl font-mono tracking-widest font-black text-primary">{{ $checkinCode ?? '—' }}</span>
                </div>

                <div class="mb-6 rounded-xl border border-secondary/25 bg-accent/10 px-4 py-3 text-left">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/50">Destinataire du code</p>
                    <p class="text-sm font-medium text-primary mt-0.5">
                        {{ $codeRecipientLabel }}
                        <span class="text-primary/50 font-normal">— {{ $codeRecipient?->full_name ?? 'Inconnu' }}</span>
                    </p>
                    @if($codeRecipientEmail)
                        <p class="text-xs text-primary/60 mt-0.5 truncate">{{ $codeRecipientEmail }}</p>
                    @else
                        <p class="text-xs text-amber-700 mt-0.5">
                            Aucune adresse enregistrée — communiquez le code de vive voix.
                        </p>
                    @endif
                </div>

                @if($codeRecipientEmail)
                    <button type="button" @click="envoyer()" :disabled="envoi"
                            class="w-full py-3 mb-3 bg-emerald-600 text-white font-semibold rounded-xl hover:bg-emerald-700 transition-all shadow-md disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <i data-lucide="mail" class="w-4 h-4"></i>
                        <span x-text="envoi ? 'Envoi en cours…' : (dejaEnvoye ? 'Renvoyer le code par email' : 'Envoyer le code par email')"></span>
                    </button>

                    <p x-show="message" x-cloak style="display:none;"
                       class="mb-3 text-xs rounded-lg px-3 py-2 text-left"
                       :class="succes ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'"
                       x-text="message"></p>
                @endif

                <button type="button" @click="showCodeModal = false"
                        class="w-full py-3 bg-primary text-white font-semibold rounded-xl hover:bg-surface-dark transition-all shadow-md">
                    Fermer la fenêtre
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ── Styles d'impression ─────────────────────────────────────────────────── --}}
<style>
@media print {
    .no-print,
    header,
    nav,
    aside,
    #mobile-sidebar,
    button {
        display: none !important;
    }

    body, html, main {
        background: white !important;
        color: black !important;
        height: auto !important;
        overflow: visible !important;
    }

    .shadow-sm, .shadow-md, .shadow-2xl {
        box-shadow: none !important;
    }

    .border-secondary\/20, .border-secondary\/15 {
        border-color: #ddd !important;
    }
}
</style>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        if (!Alpine.data('envoiCodeCheckin')) {
            Alpine.data('envoiCodeCheckin', (url, csrfToken) => ({
                envoi: false,
                dejaEnvoye: false,
                succes: false,
                message: '',

                async envoyer() {
                    this.envoi = true;
                    this.message = '';

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            this.succes = false;
                            this.message = data.message || 'Erreur lors de l’envoi.';
                            return;
                        }

                        this.succes = true;
                        this.dejaEnvoye = true;
                        this.message = data.message || 'Code envoyé avec succès !';
                    } catch (e) {
                        this.succes = false;
                        this.message = 'Erreur réseau, veuillez réessayer.';
                    } finally {
                        this.envoi = false;
                    }
                }
            }));
        }
    });
</script>
@endpush
@endsection
