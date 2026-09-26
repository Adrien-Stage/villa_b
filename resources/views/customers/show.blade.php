@extends('layouts.hotel')

@section('title', $customer->full_name . ' — Factures & Profil')

@section('content')

{{-- Style d'impression dédié pour le relevé des factures --}}
<style>
    @media print {
        @page {
            margin: 0mm !important;
            size: auto;
        }
        body {
            background: white !important;
            color: #1a1a1a !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .no-print {
            display: none !important;
        }
        .print-only {
            display: block !important;
        }
        .print-container {
            padding: 15mm 20mm !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            box-shadow: none !important;
            border: none !important;
        }
        .tab-content {
            display: none !important;
        }
        #tab-content-invoices {
            display: block !important;
        }
    }
    @media screen {
        .print-only {
            display: none !important;
        }
    }
</style>

{{-- Retour --}}
<a href="{{ route('customers.index') }}"
   class="no-print text-xs text-primary/50 hover:text-primary transition-colors flex items-center gap-1 mb-5">
    <i data-lucide="arrow-left" class="w-3 h-3"></i>
    Retour aux clients
</a>

{{-- Messages d'alerte --}}
@if(session('success'))
<div class="no-print mb-5 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg flex items-center gap-2">
    <i data-lucide="check-circle" class="w-4 h-4"></i>
    {{ session('success') }}
</div>
@endif
@if(isset($errors) && $errors->any())
<div class="no-print mb-5 px-4 py-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg">
    <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- En-tête imprimable (uniquement pour l'impression de relevé) --}}
<div class="print-only print-container mb-6 border-b border-gray-300 pb-5">
    <div class="flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold font-heading text-primary uppercase tracking-wide">
                {{ \App\Models\Tenant::current()?->name ?? config('app.name', 'Hôtel & Spa') }}
            </h1>
            <p class="text-xs text-gray-500 mt-0.5">Relevé officiel des factures et consommations client</p>
        </div>
        <div class="text-right text-xs text-gray-600">
            <p class="font-semibold text-primary">Édité le {{ now()->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm') }}</p>
            <p class="text-[11px] text-gray-400">Établissement certifié</p>
        </div>
    </div>
    <div class="mt-4 p-3 bg-gray-50 rounded-lg grid grid-cols-2 text-xs">
        <div>
            <p><span class="font-semibold text-gray-700">Client :</span> {{ $customer->full_name }}</p>
            @if($customer->phone)<p><span class="font-semibold text-gray-700">Téléphone :</span> {{ $customer->phone }}</p>@endif
            @if($customer->email)<p><span class="font-semibold text-gray-700">Email :</span> {{ $customer->email }}</p>@endif
        </div>
        <div class="text-right">
            @if($customer->partnerOrganization)
                <p><span class="font-semibold text-gray-700">Convention :</span> {{ $customer->partnerOrganization->name }}</p>
            @endif
            <p><span class="font-semibold text-gray-700">Niveau fidélité :</span> <span class="capitalize">{{ $customer->loyalty_level }}</span> ({{ number_format($customer->loyalty_points) }} pts)</p>
            @if(!empty($billingData['filters']['start_date']) || !empty($billingData['filters']['end_date']))
                <p class="text-primary font-semibold mt-1">
                    Période : {{ $billingData['filters']['start_date'] ? \Carbon\Carbon::parse($billingData['filters']['start_date'])->format('d/m/Y') : 'Début' }}
                    au {{ $billingData['filters']['end_date'] ? \Carbon\Carbon::parse($billingData['filters']['end_date'])->format('d/m/Y') : 'Fin' }}
                </p>
            @else
                <p class="text-gray-500 mt-1">Période : Historique global</p>
            @endif
        </div>
    </div>
</div>

{{-- En-tête fiche client --}}
<div class="no-print bg-white rounded-xl shadow-sm p-6 mb-5 border border-secondary/10">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">

        {{-- Identité --}}
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-primary flex items-center justify-center flex-shrink-0 text-white font-heading font-semibold text-xl shadow-inner">
                {{ strtoupper(substr($customer->first_name, 0, 1) . substr($customer->last_name, 0, 1)) }}
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h1 class="font-heading text-2xl font-semibold text-primary">{{ $customer->full_name }}</h1>
                    @if($customer->is_vip)
                        <span class="flex items-center gap-1 px-2.5 py-0.5 bg-yellow-50 text-yellow-700 border border-yellow-200 rounded-full text-xs font-medium">
                            <i data-lucide="star" class="w-3 h-3 text-yellow-600 fill-yellow-500"></i> VIP
                        </span>
                    @endif
                    @if($customer->is_blacklisted)
                        <span class="flex items-center gap-1 px-2.5 py-0.5 bg-red-50 text-red-700 border border-red-200 rounded-full text-xs font-medium">
                            <i data-lucide="ban" class="w-3 h-3"></i> Blacklisté
                        </span>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-primary/60">
                    @if($customer->email)
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="mail" class="w-3.5 h-3.5 opacity-60"></i>
                            {{ $customer->email }}
                        </span>
                    @endif
                    @if($customer->phone)
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="phone" class="w-3.5 h-3.5 opacity-60"></i>
                            {{ $customer->phone }}
                        </span>
                    @endif
                    @if($customer->country)
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5 opacity-60"></i>
                            {{ \App\Support\Countries::name($customer->country) }}
                        </span>
                    @endif
                    @if($customer->nationality)
                        <span class="flex items-center gap-1.5">
                            <i data-lucide="globe" class="w-3.5 h-3.5 opacity-60"></i>
                            {{ $customer->nationality }}
                        </span>
                    @endif
                    @if($customer->partnerOrganization)
                        <span class="flex items-center gap-1.5 px-2 py-0.5 bg-secondary/10 rounded-md text-xs font-medium text-primary">
                            <i data-lucide="handshake" class="w-3 h-3 text-primary"></i>
                            {{ $customer->partnerOrganization->name }}
                            @unless($customer->partnerOrganization->isValidOn())
                                <span class="text-[10px] text-red-500 font-semibold">(convention expirée)</span>
                            @endunless
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Badges et Boutons d'action --}}
        @php
            $levelConfig = [
                'platinum' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'icon' => 'award'],
                'gold'     => ['bg' => 'bg-yellow-50', 'text' => 'text-yellow-700', 'border' => 'border-yellow-200', 'icon' => 'medal'],
                'silver'   => ['bg' => 'bg-gray-50',   'text' => 'text-gray-600',   'border' => 'border-gray-200',   'icon' => 'shield'],
                'bronze'   => ['bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'icon' => 'shield'],
            ];
            $lc = $levelConfig[$customer->loyalty_level] ?? $levelConfig['bronze'];
        @endphp
        <div class="flex flex-col sm:flex-row md:flex-col items-start md:items-end gap-2.5">
            <div class="inline-flex items-center gap-2 px-4 py-2 {{ $lc['bg'] }} {{ $lc['text'] }} border {{ $lc['border'] }} rounded-xl">
                <i data-lucide="{{ $lc['icon'] }}" class="w-4 h-4"></i>
                <div>
                    <p class="text-[10px] uppercase font-bold tracking-wider leading-none">{{ $customer->loyalty_level }}</p>
                    <p class="text-base font-heading font-bold leading-tight mt-0.5">
                        {{ number_format($customer->loyalty_points) }}
                        <span class="text-xs font-normal">pts</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                {{-- Bouton raccourci vers les factures --}}
                <button type="button" onclick="switchTab('invoices')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm cursor-pointer">
                    <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                    Factures & Pièces
                </button>

                @role('reception', 'manager')
                <a href="{{ route('customers.edit', $customer) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors shadow-sm">
                    <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                    Modifier le profil
                </a>
                @endrole
            </div>
        </div>
    </div>
</div>

{{-- Métriques globales du client --}}
<div class="no-print grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10 text-center">
        <p class="text-2xl font-heading font-bold text-primary">{{ $customer->bookings->count() }}</p>
        <p class="text-xs text-primary/50 mt-1">Réservations</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10 text-center">
        <p class="text-2xl font-heading font-bold text-primary">{{ $customer->total_nights_stayed }}</p>
        <p class="text-xs text-primary/50 mt-1">Nuits séjournées</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10 text-center">
        <p class="text-2xl font-heading font-bold text-primary">
            {{ number_format($customer->total_spent / 100, 0, ',', ' ') }}
        </p>
        <p class="text-xs text-primary/50 mt-1">FCFA dépensés</p>
    </div>
    <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10 text-center">
        <p class="text-2xl font-heading font-bold text-primary">{{ number_format($customer->loyalty_points) }}</p>
        <p class="text-xs text-primary/50 mt-1">Points disponibles</p>
    </div>
</div>

{{-- Système d'onglets ergonomique --}}
<div class="no-print bg-white rounded-xl shadow-sm mb-5 p-1.5 border border-secondary/20 flex flex-wrap items-center gap-1.5">
    <button type="button" id="tab-btn-bookings" onclick="switchTab('bookings')"
            class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold transition-all cursor-pointer">
        <i data-lucide="bed-double" class="w-4 h-4"></i>
        <span>Séjours & Réservations</span>
        <span class="tab-badge px-2 py-0.5 rounded-full text-[11px] font-bold">
            {{ $customer->bookings->count() }}
        </span>
    </button>

    <button type="button" id="tab-btn-invoices" onclick="switchTab('invoices')"
            class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold transition-all cursor-pointer">
        <i data-lucide="receipt" class="w-4 h-4"></i>
        <span>Toutes les Factures & Consommations</span>
        <span class="tab-badge px-2 py-0.5 rounded-full text-[11px] font-bold">
            {{ $billingData['totals']['count'] }}
        </span>
    </button>

    <button type="button" id="tab-btn-loyalty" onclick="switchTab('loyalty')"
            class="tab-btn flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-semibold transition-all cursor-pointer">
        <i data-lucide="gift" class="w-4 h-4"></i>
        <span>Programme Fidélité</span>
        <span class="tab-badge px-2 py-0.5 rounded-full text-[11px] font-bold">
            {{ number_format($customer->loyalty_points) }} pts
        </span>
    </button>
</div>

{{-- ========================================================================= --}}
{{-- CONTENU ONGLET 1 : SÉJOURS & RÉSERVATIONS                                  --}}
{{-- ========================================================================= --}}
<div id="tab-content-bookings" class="tab-content {{ $currentTab === 'bookings' ? '' : 'hidden' }}">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Historique des réservations (2/3) --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-secondary/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/10 flex items-center justify-between">
                <div>
                    <h2 class="font-heading font-semibold text-primary text-sm">Historique des séjours</h2>
                    <p class="text-xs text-primary/50">Réservations et dossiers d'hébergement rattachés</p>
                </div>
                <span class="text-xs font-medium text-primary/60">{{ $customer->bookings->count() }} séjour(s)</span>
            </div>

            @if($customer->bookings->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-primary/30">
                    <i data-lucide="calendar" class="w-10 h-10 mb-2 opacity-40"></i>
                    <p class="text-sm font-medium">Aucun séjour enregistré</p>
                </div>
            @else
                <div class="divide-y divide-secondary/10">
                    @foreach($customer->bookings as $booking)
                        @php
                            $statusColors = [
                                'completed'   => 'bg-gray-50 text-gray-700 border-gray-200',
                                'confirmed'   => 'bg-blue-50 text-blue-700 border-blue-200',
                                'checked_in'  => 'bg-green-50 text-green-700 border-green-200',
                                'checked_out' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'cancelled'   => 'bg-red-50 text-red-600 border-red-200',
                                'pending'     => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                            ];
                            $sc = $statusColors[$booking->status->value] ?? 'bg-secondary/10 text-primary/60 border-secondary/20';
                        @endphp
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 px-5 py-3.5 hover:bg-accent/10 transition-colors">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('bookings.show', $booking) }}"
                                       class="text-sm font-semibold text-primary hover:text-accent-dark transition-colors">
                                        {{ $booking->booking_number }}
                                    </a>
                                    <span class="px-2 py-0.5 text-xs font-medium rounded-full border {{ $sc }}">
                                        {{ $booking->status->label() }}
                                    </span>
                                </div>
                                <p class="text-xs text-primary/50 mt-0.5">
                                    Chambre {{ $booking->room?->number ?? 'N/A' }} —
                                    {{ $booking->room?->roomType?->name ?? 'Type non spécifié' }}
                                </p>
                            </div>
                            <div class="text-left sm:text-right flex-shrink-0 text-xs">
                                <p class="font-medium text-primary">
                                    {{ $booking->check_in->locale('fr')->isoFormat('D MMM') }}
                                    → {{ $booking->check_out->locale('fr')->isoFormat('D MMM YYYY') }}
                                </p>
                                <p class="text-primary/40">{{ $booking->total_nights }} nuit{{ $booking->total_nights > 1 ? 's' : '' }}</p>
                            </div>
                            <div class="flex items-center gap-2 justify-end flex-shrink-0 w-full sm:w-auto">
                                <div class="text-right">
                                    <p class="text-xs font-bold text-primary">
                                        {{ number_format($booking->total_amount / 100, 0, ',', ' ') }} FCFA
                                    </p>
                                    @if($booking->balance_due > 0)
                                        <p class="text-[10px] text-red-600 font-medium">Solde dû : {{ number_format($booking->balance_due / 100, 0, ',', ' ') }} FCFA</p>
                                    @else
                                        <p class="text-[10px] text-green-600 font-medium">Réglé</p>
                                    @endif
                                </div>
                                <a href="{{ route('bookings.show', $booking) }}"
                                   class="p-1.5 text-primary/60 hover:text-primary hover:bg-secondary/10 rounded-lg transition-colors"
                                   title="Voir la réservation">
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Synthèse rapide séjour (1/3) --}}
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5 flex flex-col justify-between">
            <div>
                <h3 class="font-heading font-semibold text-primary text-sm mb-3">Synthèse des séjours</h3>
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between items-center py-2 border-b border-secondary/10">
                        <span class="text-primary/60">Total réservations</span>
                        <span class="font-semibold text-primary">{{ $customer->bookings->count() }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-secondary/10">
                        <span class="text-primary/60">Nuits cumulées</span>
                        <span class="font-semibold text-primary">{{ $customer->total_nights_stayed }} nuits</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-secondary/10">
                        <span class="text-primary/60">Séjours confirmés / actifs</span>
                        <span class="font-semibold text-emerald-600">
                            {{ $customer->bookings->whereIn('status.value', ['confirmed', 'checked_in'])->count() }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-secondary/10">
                        <span class="text-primary/60">Séjours terminés</span>
                        <span class="font-semibold text-primary">
                            {{ $customer->bookings->where('status.value', 'completed')->count() }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-6 p-3.5 bg-accent/10 rounded-xl border border-accent/20">
                <p class="text-xs font-semibold text-primary mb-1">Accéder aux factures</p>
                <p class="text-[11px] text-primary/60 mb-3">
                    Consultez l'ensemble des factures d'hébergement, restaurant, boutique et POS réception.
                </p>
                <button type="button" onclick="switchTab('invoices')"
                        class="w-full py-2 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors flex items-center justify-center gap-1.5 cursor-pointer">
                    <i data-lucide="receipt" class="w-3.5 h-3.5"></i>
                    Voir les factures du client
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- CONTENU ONGLET 2 : TOUTES LES FACTURES & CONSOMMATIONS (MULTI-SERVICES)    --}}
{{-- ========================================================================= --}}
<div id="tab-content-invoices" class="tab-content {{ $currentTab === 'invoices' ? '' : 'hidden' }}">

    {{-- Cartes financières récapitulatives --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-primary/60">Total Facturé (TTC)</span>
                <span class="p-1.5 rounded-lg bg-indigo-50 text-indigo-700">
                    <i data-lucide="file-text" class="w-3.5 h-3.5"></i>
                </span>
            </div>
            <p class="text-xl font-heading font-bold text-primary">
                {{ number_format($billingData['totals']['total_invoiced'] / 100, 0, ',', ' ') }}
                <span class="text-xs font-normal text-primary/60">FCFA</span>
            </p>
        </div>

        <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-emerald-700">Total Réglé</span>
                <span class="p-1.5 rounded-lg bg-emerald-50 text-emerald-700">
                    <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                </span>
            </div>
            <p class="text-xl font-heading font-bold text-emerald-700">
                {{ number_format($billingData['totals']['total_paid'] / 100, 0, ',', ' ') }}
                <span class="text-xs font-normal text-emerald-600/70">FCFA</span>
            </p>
        </div>

        <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium {{ $billingData['totals']['total_balance_due'] > 0 ? 'text-red-700' : 'text-primary/60' }}">
                    Reste à payer
                </span>
                <span class="p-1.5 rounded-lg {{ $billingData['totals']['total_balance_due'] > 0 ? 'bg-red-50 text-red-700' : 'bg-gray-50 text-gray-500' }}">
                    <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                </span>
            </div>
            <p class="text-xl font-heading font-bold {{ $billingData['totals']['total_balance_due'] > 0 ? 'text-red-600' : 'text-primary' }}">
                {{ number_format($billingData['totals']['total_balance_due'] / 100, 0, ',', ' ') }}
                <span class="text-xs font-normal opacity-70">FCFA</span>
            </p>
        </div>

        <div class="bg-white rounded-xl p-4 shadow-sm border border-secondary/10">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-medium text-primary/60">Documents émis</span>
                <span class="p-1.5 rounded-lg bg-accent/20 text-primary">
                    <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                </span>
            </div>
            <p class="text-xl font-heading font-bold text-primary">
                {{ $billingData['totals']['count'] }}
                <span class="text-xs font-normal text-primary/60">pièce(s)</span>
            </p>
        </div>
    </div>

    {{-- Formulaire de recherche et filtres par période --}}
    <div class="no-print bg-white rounded-xl shadow-sm border border-secondary/10 p-5 mb-5">
        <form method="GET" action="{{ route('customers.show', $customer) }}" id="invoicesFilterForm" class="space-y-4">
            <input type="hidden" name="tab" value="invoices">
            <input type="hidden" name="period" id="periodInput" value="{{ $billingData['filters']['period'] }}">

            {{-- Ligne 1 : Raccourcis de période (Presets) --}}
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-secondary/10 pb-3">
                <div class="flex items-center gap-1.5 text-xs text-primary/60">
                    <i data-lucide="calendar-range" class="w-3.5 h-3.5"></i>
                    <span class="font-medium">Périodes rapides :</span>
                </div>
                <div class="flex flex-wrap items-center gap-1">
                    <button type="button" onclick="setPreset('today')"
                            class="px-2.5 py-1 text-xs rounded-md transition-colors {{ ($billingData['filters']['period'] === 'today') ? 'bg-primary text-white font-semibold' : 'bg-secondary/10 text-primary/70 hover:bg-secondary/20' }}">
                        Aujourd'hui
                    </button>
                    <button type="button" onclick="setPreset('this_month')"
                            class="px-2.5 py-1 text-xs rounded-md transition-colors {{ ($billingData['filters']['period'] === 'this_month') ? 'bg-primary text-white font-semibold' : 'bg-secondary/10 text-primary/70 hover:bg-secondary/20' }}">
                        Ce mois-ci
                    </button>
                    <button type="button" onclick="setPreset('last_month')"
                            class="px-2.5 py-1 text-xs rounded-md transition-colors {{ ($billingData['filters']['period'] === 'last_month') ? 'bg-primary text-white font-semibold' : 'bg-secondary/10 text-primary/70 hover:bg-secondary/20' }}">
                        Mois dernier
                    </button>
                    <button type="button" onclick="setPreset('this_year')"
                            class="px-2.5 py-1 text-xs rounded-md transition-colors {{ ($billingData['filters']['period'] === 'this_year') ? 'bg-primary text-white font-semibold' : 'bg-secondary/10 text-primary/70 hover:bg-secondary/20' }}">
                        Cette année
                    </button>
                    <button type="button" onclick="setPreset('all')"
                            class="px-2.5 py-1 text-xs rounded-md transition-colors {{ (empty($billingData['filters']['period']) || $billingData['filters']['period'] === 'all') && empty($billingData['filters']['start_date']) ? 'bg-primary text-white font-semibold' : 'bg-secondary/10 text-primary/70 hover:bg-secondary/20' }}">
                        Tout l'historique
                    </button>
                </div>
            </div>

            {{-- Ligne 2 : Filtres de dates, service, statut et recherche --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
                {{-- Date début --}}
                <div>
                    <label for="start_date" class="block text-[11px] font-medium text-primary/70 mb-1">Date début</label>
                    <input type="date" name="start_date" id="start_date"
                           value="{{ $billingData['filters']['start_date'] }}"
                           class="w-full text-xs px-2.5 py-2 border border-secondary/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary bg-white">
                </div>

                {{-- Date fin --}}
                <div>
                    <label for="end_date" class="block text-[11px] font-medium text-primary/70 mb-1">Date fin</label>
                    <input type="date" name="end_date" id="end_date"
                           value="{{ $billingData['filters']['end_date'] }}"
                           class="w-full text-xs px-2.5 py-2 border border-secondary/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary bg-white">
                </div>

                {{-- Domaine / Service --}}
                <div>
                    <label for="service" class="block text-[11px] font-medium text-primary/70 mb-1">Service consommé</label>
                    <select name="service" id="service"
                            class="w-full text-xs px-2.5 py-2 border border-secondary/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary bg-white">
                        <option value="all" {{ $billingData['filters']['service'] === 'all' ? 'selected' : '' }}>
                            Tous les services
                        </option>
                        <option value="accommodation" {{ $billingData['filters']['service'] === 'accommodation' ? 'selected' : '' }}>
                            🏨 Hébergement ({{ $billingData['totals']['counts_by_service']['accommodation'] ?? 0 }})
                        </option>
                        <option value="restaurant" {{ $billingData['filters']['service'] === 'restaurant' ? 'selected' : '' }}>
                            🍽️ Restaurant & Bar ({{ $billingData['totals']['counts_by_service']['restaurant'] ?? 0 }})
                        </option>
                        <option value="shop" {{ $billingData['filters']['service'] === 'shop' ? 'selected' : '' }}>
                            🛍️ Boutique ({{ $billingData['totals']['counts_by_service']['shop'] ?? 0 }})
                        </option>
                        <option value="reception" {{ $billingData['filters']['service'] === 'reception' ? 'selected' : '' }}>
                            🏷️ POS Réception ({{ $billingData['totals']['counts_by_service']['reception'] ?? 0 }})
                        </option>
                        <option value="group" {{ $billingData['filters']['service'] === 'group' ? 'selected' : '' }}>
                            👥 Réservations Groupe ({{ $billingData['totals']['counts_by_service']['group'] ?? 0 }})
                        </option>
                    </select>
                </div>

                {{-- Statut paiement --}}
                <div>
                    <label for="status" class="block text-[11px] font-medium text-primary/70 mb-1">Statut règlement</label>
                    <select name="status" id="status"
                            class="w-full text-xs px-2.5 py-2 border border-secondary/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary bg-white">
                        <option value="all" {{ $billingData['filters']['status'] === 'all' ? 'selected' : '' }}>Tous les statuts</option>
                        <option value="paid" {{ $billingData['filters']['status'] === 'paid' ? 'selected' : '' }}>Payé / Réglé</option>
                        <option value="unpaid" {{ $billingData['filters']['status'] === 'unpaid' ? 'selected' : '' }}>Reste à payer / Impayé</option>
                        <option value="partial" {{ $billingData['filters']['status'] === 'partial' ? 'selected' : '' }}>Partiel</option>
                    </select>
                </div>

                {{-- Recherche textuelle --}}
                <div>
                    <label for="search" class="block text-[11px] font-medium text-primary/70 mb-1">Recherche n° pièce</label>
                    <input type="text" name="search" id="search"
                           value="{{ $billingData['filters']['search'] }}"
                           placeholder="Ex: F-2026, CMD..."
                           class="w-full text-xs px-2.5 py-2 border border-secondary/20 rounded-lg focus:outline-none focus:ring-1 focus:ring-primary bg-white">
                </div>

                {{-- Boutons d'action --}}
                <div class="flex items-center gap-1.5">
                    <button type="submit"
                            class="flex-1 px-3 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors flex items-center justify-center gap-1 cursor-pointer">
                        <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                        Filtrer
                    </button>
                    <a href="{{ route('customers.show', ['customer' => $customer, 'tab' => 'invoices']) }}"
                       class="px-2.5 py-2 bg-secondary/10 hover:bg-secondary/20 text-primary text-xs rounded-lg transition-colors"
                       title="Réinitialiser tous les filtres">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                    </a>
                    <button type="button" onclick="window.print()"
                            class="px-2.5 py-2 bg-white border border-secondary/30 text-primary hover:bg-accent/20 text-xs rounded-lg transition-colors cursor-pointer"
                            title="Imprimer le relevé filtré">
                        <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>

    {{-- Tableau des factures & pièces comptables --}}
    <div class="bg-white rounded-xl shadow-sm border border-secondary/10 overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/10 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-heading font-semibold text-primary text-sm">
                    Toutes les factures et consommations du client
                </h2>
                <p class="text-xs text-primary/50">
                    Agrégation multi-services : Hébergement, Restaurant, Boutique, POS Réception
                </p>
            </div>
            <div class="text-xs font-medium text-primary/70 flex items-center gap-2">
                <span>{{ $billingData['totals']['count'] }} pièce(s) trouvée(s)</span>
            </div>
        </div>

        @if($billingData['documents']->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-primary/40 text-center px-4">
                <div class="w-14 h-14 rounded-full bg-secondary/10 flex items-center justify-center mb-3">
                    <i data-lucide="receipt" class="w-7 h-7 opacity-50"></i>
                </div>
                <p class="text-sm font-semibold text-primary/70">Aucune facture ou pièce trouvée pour cette période</p>
                <p class="text-xs text-primary/50 max-w-sm mt-1 mb-4">
                    Aucun document n'a été émis pour les filtres sélectionnés (service, dates ou statut).
                </p>
                <a href="{{ route('customers.show', ['customer' => $customer, 'tab' => 'invoices']) }}"
                   class="no-print inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="rotate-ccw" class="w-3 h-3"></i>
                    Réinitialiser les filtres
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-secondary/5 text-primary/70 border-b border-secondary/10 uppercase tracking-wider text-[10px]">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Date & Heure</th>
                            <th class="px-4 py-3 font-semibold">Service</th>
                            <th class="px-4 py-3 font-semibold">Réf. Document</th>
                            <th class="px-4 py-3 font-semibold">Prestations / Détails</th>
                            <th class="px-4 py-3 font-semibold text-right">Montant TTC</th>
                            <th class="px-4 py-3 font-semibold text-right">Réglé</th>
                            <th class="px-4 py-3 font-semibold text-right">Reste à payer</th>
                            <th class="px-4 py-3 font-semibold text-center">Statut</th>
                            <th class="no-print px-4 py-3 font-semibold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($billingData['documents'] as $doc)
                            <tr class="hover:bg-accent/5 transition-colors">
                                {{-- Date --}}
                                <td class="px-4 py-3 whitespace-nowrap text-primary/70">
                                    <p class="font-medium text-primary">
                                        {{ $doc->date->locale('fr')->isoFormat('D MMM YYYY') }}
                                    </p>
                                    <p class="text-[10px] text-primary/40">
                                        {{ $doc->date->format('H:i') }}
                                    </p>
                                </td>

                                {{-- Domaine / Service --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {{ $doc->service_badge_class }}">
                                        <i data-lucide="{{ $doc->service_icon }}" class="w-3 h-3"></i>
                                        {{ $doc->service_label }}
                                    </span>
                                </td>

                                {{-- Référence & Type --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="font-bold text-primary font-mono text-xs">
                                        {{ $doc->reference }}
                                    </p>
                                    <p class="text-[10px] text-primary/50">
                                        {{ $doc->document_type }}
                                    </p>
                                </td>

                                {{-- Détails / Prestations --}}
                                <td class="px-4 py-3">
                                    <p class="font-medium text-primary">
                                        {{ $doc->description }}
                                    </p>
                                    <p class="text-[10px] text-primary/40">
                                        Mode : {{ $doc->payment_method ?? 'N/A' }}
                                    </p>
                                </td>

                                {{-- Montant TTC --}}
                                <td class="px-4 py-3 whitespace-nowrap text-right font-semibold text-primary">
                                    {{ number_format($doc->total_amount / 100, 0, ',', ' ') }} FCFA
                                </td>

                                {{-- Réglé --}}
                                <td class="px-4 py-3 whitespace-nowrap text-right font-medium text-emerald-700">
                                    {{ number_format($doc->paid_amount / 100, 0, ',', ' ') }} FCFA
                                </td>

                                {{-- Solde dû --}}
                                <td class="px-4 py-3 whitespace-nowrap text-right">
                                    @if($doc->balance_due > 0)
                                        <span class="font-bold text-red-600">
                                            {{ number_format($doc->balance_due / 100, 0, ',', ' ') }} FCFA
                                        </span>
                                    @else
                                        <span class="font-semibold text-emerald-600">0 FCFA</span>
                                    @endif
                                </td>

                                {{-- Statut --}}
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $doc->status_badge_class }}">
                                        {{ $doc->status_label }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="no-print px-4 py-3 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        @if(!empty($doc->url_show))
                                            <a href="{{ $doc->url_show }}"
                                               class="p-1.5 text-primary/70 hover:text-primary hover:bg-accent/20 rounded-md transition-colors"
                                               title="Consulter les détails">
                                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                            </a>
                                        @endif
                                        @if(!empty($doc->url_print))
                                            <a href="{{ $doc->url_print }}" target="_blank"
                                               class="p-1.5 text-primary/70 hover:text-primary hover:bg-secondary/10 rounded-md transition-colors"
                                               title="Imprimer le document ou reçu">
                                                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    {{-- Ligne de pied de tableau pour totaux --}}
                    <tfoot class="bg-secondary/5 border-t-2 border-secondary/20 text-xs font-bold text-primary">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right">
                                TOTAL SÉLECTION ({{ $billingData['totals']['count'] }} pièces) :
                            </td>
                            <td class="px-4 py-3 text-right font-heading">
                                {{ number_format($billingData['totals']['total_invoiced'] / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-4 py-3 text-right font-heading text-emerald-700">
                                {{ number_format($billingData['totals']['total_paid'] / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-4 py-3 text-right font-heading {{ $billingData['totals']['total_balance_due'] > 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                {{ number_format($billingData['totals']['total_balance_due'] / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Pagination --}}
            @if($billingData['documents']->hasPages())
                <div class="no-print px-5 py-3 border-t border-secondary/10">
                    {{ $billingData['documents']->withQueryString()->links() }}
                </div>
            @endif
        @endif
    </div>

</div>

{{-- ========================================================================= --}}
{{-- CONTENU ONGLET 3 : PROGRAMME FIDÉLITÉ                                     --}}
{{-- ========================================================================= --}}
<div id="tab-content-loyalty" class="tab-content {{ $currentTab === 'loyalty' ? '' : 'hidden' }}">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Carte progression et statut --}}
        <div class="bg-white rounded-xl shadow-sm border border-secondary/10 p-5">
            <h2 class="font-heading font-semibold text-primary text-sm mb-4">Progression du statut</h2>

            @php
                $nextLevels = [
                    'bronze'   => ['next' => 'Silver',   'threshold' => 5000000,   'current_threshold' => 0],
                    'silver'   => ['next' => 'Gold',     'threshold' => 20000000,  'current_threshold' => 5000000],
                    'gold'     => ['next' => 'Platinum', 'threshold' => 50000000,  'current_threshold' => 20000000],
                    'platinum' => ['next' => null,        'threshold' => 50000000,  'current_threshold' => 50000000],
                ];
                $nl = $nextLevels[$customer->loyalty_level] ?? $nextLevels['bronze'];
                $progress = $nl['next']
                    ? min(100, round((($customer->total_spent - $nl['current_threshold']) / ($nl['threshold'] - $nl['current_threshold'])) * 100))
                    : 100;
            @endphp

            <div class="mb-4">
                <div class="flex justify-between items-center mb-1.5 text-xs">
                    <span class="font-medium text-primary/70">Niveau actuel</span>
                    <span class="capitalize font-bold text-primary">{{ $customer->loyalty_level }}</span>
                </div>
                @if($nl['next'])
                    <div class="flex justify-between items-center mb-1 text-[11px] text-primary/50">
                        <span>Vers {{ $nl['next'] }}</span>
                        <span>{{ $progress }}%</span>
                    </div>
                    <div class="h-2 bg-secondary/10 rounded-full overflow-hidden mb-1.5">
                        <div class="h-full bg-primary rounded-full transition-all" style="width: {{ $progress }}%"></div>
                    </div>
                    <p class="text-[11px] text-primary/50">
                        Plus que {{ number_format(max(0, $nl['threshold'] - $customer->total_spent) / 100, 0, ',', ' ') }} FCFA pour atteindre le palier {{ $nl['next'] }}.
                    </p>
                @else
                    <div class="p-3 bg-purple-50 border border-purple-200 rounded-lg text-xs text-purple-700 font-medium">
                        Félicitations ! Vous avez atteint le palier maximal Platinum.
                    </div>
                @endif
            </div>

            <div class="pt-4 border-t border-secondary/10 space-y-2 text-xs">
                <div class="flex justify-between items-center">
                    <span class="text-primary/60">Solde de points :</span>
                    <span class="font-bold text-primary text-sm">{{ number_format($customer->loyalty_points) }} pts</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-primary/60">Dépenses cumulées :</span>
                    <span class="font-semibold text-primary">{{ number_format($customer->total_spent / 100, 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
        </div>

        {{-- Historique des mouvements de points (2/3) --}}
        <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-secondary/10 overflow-hidden">
            <div class="px-5 py-4 border-b border-secondary/10 flex items-center justify-between">
                <div>
                    <h2 class="font-heading font-semibold text-primary text-sm">Historique des transactions de fidélité</h2>
                    <p class="text-xs text-primary/50">Points cumulés et déduits lors des séjours</p>
                </div>
            </div>

            @if($customer->loyaltyTransactions->isEmpty())
                <div class="flex flex-col items-center justify-center py-14 text-primary/30">
                    <i data-lucide="gift" class="w-8 h-8 mb-2 opacity-40"></i>
                    <p class="text-xs">Aucune transaction de fidélité enregistrée</p>
                </div>
            @else
                <div class="divide-y divide-secondary/10">
                    @foreach($customer->loyaltyTransactions as $tx)
                        <div class="flex items-center justify-between px-5 py-3 hover:bg-accent/5 transition-colors">
                            <div>
                                <p class="text-xs font-semibold text-primary">{{ $tx->description ?? $tx->type }}</p>
                                <p class="text-[10px] text-primary/40">
                                    {{ $tx->created_at->locale('fr')->isoFormat('D MMMM YYYY [à] HH:mm') }}
                                </p>
                            </div>
                            <span class="text-sm font-bold {{ $tx->points > 0 ? 'text-green-600' : 'text-red-500' }}">
                                {{ $tx->points > 0 ? '+' : '' }}{{ number_format($tx->points) }} pts
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>

{{-- Script pour la navigation par onglets et les raccourcis de date --}}
<script>
    function switchTab(tabId) {
        // Cacher tous les contenus d'onglets
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });

        // Afficher le contenu actif
        const activeContent = document.getElementById('tab-content-' + tabId);
        if (activeContent) {
            activeContent.classList.remove('hidden');
        }

        // Mettre à jour l'apparence des boutons d'onglets
        const tabs = ['bookings', 'invoices', 'loyalty'];
        tabs.forEach(t => {
            const btn = document.getElementById('tab-btn-' + t);
            if (!btn) return;
            const badge = btn.querySelector('.tab-badge');

            if (t === tabId) {
                btn.className = 'tab-btn flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-bold bg-primary text-white shadow-sm transition-all cursor-pointer';
                if (badge) badge.className = 'tab-badge px-2 py-0.5 rounded-full text-[11px] font-bold bg-white text-primary';
            } else {
                btn.className = 'tab-btn flex items-center gap-2 px-4 py-2.5 rounded-lg text-xs font-medium text-primary/70 hover:bg-secondary/10 transition-all cursor-pointer';
                if (badge) badge.className = 'tab-badge px-2 py-0.5 rounded-full text-[11px] font-bold bg-secondary/10 text-primary/70';
            }
        });

        // Mettre à jour l'URL sans rechargement de page si supporté
        if (window.history.pushState) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabId);
            window.history.replaceState(null, '', url.toString());
        }

        // Ré-initialiser les icônes Lucide si nécessaire
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    // Gestion des raccourcis de date
    function setPreset(preset) {
        const today = new Date();
        const startInput = document.getElementById('start_date');
        const endInput = document.getElementById('end_date');
        const periodInput = document.getElementById('periodInput');
        const form = document.getElementById('invoicesFilterForm');

        if (periodInput) {
            periodInput.value = preset;
        }

        function formatDate(d) {
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        if (preset === 'today') {
            startInput.value = formatDate(today);
            endInput.value = formatDate(today);
        } else if (preset === 'this_month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            startInput.value = formatDate(firstDay);
            endInput.value = formatDate(lastDay);
        } else if (preset === 'last_month') {
            const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
            startInput.value = formatDate(firstDay);
            endInput.value = formatDate(lastDay);
        } else if (preset === 'this_year') {
            const firstDay = new Date(today.getFullYear(), 0, 1);
            const lastDay = new Date(today.getFullYear(), 11, 31);
            startInput.value = formatDate(firstDay);
            endInput.value = formatDate(lastDay);
        } else if (preset === 'all') {
            startInput.value = '';
            endInput.value = '';
        }

        if (form) {
            form.submit();
        }
    }

    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        const activeTab = '{{ $currentTab }}' || 'bookings';
        switchTab(activeTab);
    });
</script>

@endsection