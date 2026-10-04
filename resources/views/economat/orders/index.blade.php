@extends('layouts.hotel')

@section('title', 'Bons de commande fournisseurs — Économat')

@section('content')
@php
    $statusStyles = [
        'draft'              => 'bg-gray-100 text-gray-700 border border-gray-200',
        'sent'               => 'bg-blue-50 text-blue-700 border border-blue-200',
        'partially_received' => 'bg-amber-50 text-amber-700 border border-amber-200',
        'received'           => 'bg-green-50 text-green-700 border border-green-200',
        'cancelled'          => 'bg-red-50 text-red-700 border border-red-200',
    ];
@endphp

<div class="max-w-7xl mx-auto">
    {{-- En-tête & Barre d'actions --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="clipboard-list" class="w-7 h-7 text-primary"></i>
                <span>Bons de commande fournisseurs</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                Gestion des approvisionnements, engagements de dépenses, suivi des livraisons et traçabilité des signatures.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @droit('economat.orders.export')
                <x-barre-export route="economat.orders.export" />
            @enddroit

            @droit('economat.orders.creer')
                <a href="{{ route('economat.orders.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Nouveau bon</span>
                </a>
            @enddroit
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- Cartes KPI Synthèse des Commandes --}}
    @if(isset($stats))
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Total commandes</p>
                <p class="text-2xl font-bold font-mono text-primary mt-1">{{ $stats['total'] }}</p>
                <p class="text-xs text-primary/45 mt-1">Bons enregistrés</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">Brouillons</p>
                <p class="text-2xl font-bold font-mono text-gray-700 mt-1">{{ $stats['draft'] }}</p>
                <p class="text-xs text-gray-500 mt-1">À envoyer au fournisseur</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-blue-600">Envoyés</p>
                <p class="text-2xl font-bold font-mono text-blue-700 mt-1">{{ $stats['sent'] }}</p>
                <p class="text-xs text-blue-600/70 mt-1">En attente de livraison</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-green-600">Réceptionnés</p>
                <p class="text-2xl font-bold font-mono text-green-700 mt-1">{{ $stats['received'] }}</p>
                <p class="text-xs text-green-600/70 mt-1">Livrés en magasin</p>
            </div>

            <div class="col-span-2 lg:col-span-1 bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/60">Total engagé</p>
                <p class="text-xl font-bold font-mono text-primary mt-1 truncate">
                    {{ number_format($stats['total_amount'] / 100, 0, ',', ' ') }} <span class="text-xs font-normal">F</span>
                </p>
                <p class="text-xs text-primary/45 mt-1">Commandes actives</p>
            </div>
        </div>
    @endif

    {{-- Filtres avancés --}}
    <div class="bg-white border border-secondary/20 rounded-xl p-4 mb-6 shadow-sm">
        <form method="GET" action="{{ route('economat.orders.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            {{-- Fournisseur --}}
            <div class="lg:col-span-3">
                <label for="filter-fournisseur" class="block text-[11px] font-semibold text-primary/70 mb-1 uppercase tracking-wider">
                    Fournisseur
                </label>
                <select name="fournisseur" id="filter-fournisseur" class="w-full text-xs px-3 py-2 border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    <option value="">Tous les fournisseurs</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected(request('fournisseur') == $supplier->id || request('supplier_id') == $supplier->id)>
                            {{ $supplier->name }} @if($supplier->code)({{ $supplier->code }})@endif
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Statut --}}
            <div class="lg:col-span-2">
                <label for="filter-statut" class="block text-[11px] font-semibold text-primary/70 mb-1 uppercase tracking-wider">
                    Statut
                </label>
                <select name="statut" id="filter-statut" class="w-full text-xs px-3 py-2 border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    <option value="">Tous les statuts</option>
                    @foreach(\App\Models\PurchaseOrder::STATUSES as $val => $lbl)
                        <option value="{{ $val }}" @selected(request('statut') == $val)>
                            {{ $lbl }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Date début --}}
            <div class="lg:col-span-2">
                <label for="filter-du" class="block text-[11px] font-semibold text-primary/70 mb-1 uppercase tracking-wider">
                    Du
                </label>
                <input type="date" name="du" id="filter-du" value="{{ request('du') }}"
                    class="w-full text-xs px-3 py-2 border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
            </div>

            {{-- Date fin --}}
            <div class="lg:col-span-2">
                <label for="filter-au" class="block text-[11px] font-semibold text-primary/70 mb-1 uppercase tracking-wider">
                    Au
                </label>
                <input type="date" name="au" id="filter-au" value="{{ request('au') }}"
                    class="w-full text-xs px-3 py-2 border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
            </div>

            {{-- Recherche texte --}}
            <div class="lg:col-span-2">
                <label for="filter-recherche" class="block text-[11px] font-semibold text-primary/70 mb-1 uppercase tracking-wider">
                    Recherche
                </label>
                <div class="relative">
                    <input type="text" name="recherche" id="filter-recherche" value="{{ request('recherche') }}"
                        placeholder="N° de bon, note..."
                        class="w-full text-xs px-3 py-2 pr-8 border border-secondary/30 rounded-lg bg-white text-primary outline-none focus:border-primary">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-primary/40 absolute right-2.5 top-2.5"></i>
                </div>
            </div>

            {{-- Boutons --}}
            <div class="lg:col-span-1 flex items-center gap-1.5">
                <button type="submit" class="flex-1 py-2 px-3 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors flex items-center justify-center gap-1" title="Filtrer">
                    <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                </button>
                @if(!empty($filtres))
                    <a href="{{ route('economat.orders.index') }}" class="py-2 px-2.5 bg-gray-100 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-200 transition-colors" title="Réinitialiser">
                        <i data-lucide="x" class="w-3.5 h-3.5"></i>
                    </a>
                @endif
            </div>
        </form>

        {{-- Synthèse des filtres actifs --}}
        @if(!empty($filtres))
            <div class="mt-3 pt-3 border-t border-secondary/15 flex flex-wrap items-center gap-2 text-xs text-primary/70">
                <span class="font-medium text-primary/50 text-[11px] uppercase">Filtres appliqués :</span>
                @foreach($filtres as $nom => $val)
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-secondary/15 text-primary text-[11px] font-medium">
                        <strong>{{ $nom }} :</strong> {{ $val }}
                    </span>
                @endforeach
                <a href="{{ route('economat.orders.index') }}" class="text-[11px] text-primary/60 underline hover:text-primary ml-1">
                    Effacer tous les filtres
                </a>
            </div>
        @endif
    </div>

    {{-- Tableau des Bons de Commande --}}
    <x-table :rows="$orders" :empty="! empty($filtres) ? 'Aucun bon de commande ne correspond aux filtres sélectionnés.' : 'Aucun bon de commande enregistré.'" empty-icon="clipboard-list" caption="Bons de commande">
        @if(! empty($filtres))
            <x-slot:emptyActions>
                <a href="{{ route('economat.orders.index') }}" class="text-primary underline">Réinitialiser les filtres</a>
            </x-slot:emptyActions>
        @endif
        <x-slot:head>
            <x-table.col>N° de bon</x-table.col>
            <x-table.col>Fournisseur</x-table.col>
            <x-table.col hide="lg">Émission</x-table.col>
            <x-table.col align="right" hide="2xl">Articles</x-table.col>
            <x-table.col align="right">Montant</x-table.col>
            <x-table.col>Statut</x-table.col>
            <x-table.col hide="3xl">Signataire</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($orders as $order)
            <x-table.row :href="route('economat.orders.show', $order)">
                <x-table.cell nowrap>
                    <a href="{{ route('economat.orders.show', $order) }}" class="flex items-center gap-1.5 font-mono font-bold text-primary hover:underline">
                        <i data-lucide="file-text" class="h-3.5 w-3.5 text-primary/50" aria-hidden="true"></i><span>{{ $order->number }}</span>
                    </a>
                </x-table.cell>
                <x-table.cell>
                    <div class="font-medium text-primary">{{ $order->supplier?->name ?? 'Fournisseur non spécifié' }}</div>
                    @if($order->supplier)
                        <div class="mt-0.5 flex items-center gap-2 text-[10px] text-primary/50">
                            @if($order->supplier->code)<span class="rounded bg-gray-100 px-1 font-mono">{{ $order->supplier->code }}</span>@endif
                            @if($order->supplier->phone)<span><i data-lucide="phone" class="inline h-2.5 w-2.5 text-primary/40" aria-hidden="true"></i> {{ $order->supplier->phone }}</span>@endif
                        </div>
                    @endif
                </x-table.cell>
                <x-table.cell hide="lg" nowrap>
                    <span class="text-primary/70">{{ $order->created_at->format('d/m/Y') }}</span>
                    <span class="block text-[10px] text-primary/45">{{ $order->created_at->format('H:i') }}</span>
                </x-table.cell>
                <x-table.cell align="right" hide="2xl" class="font-mono font-medium text-primary/70">{{ $order->lines_count }}</x-table.cell>
                <x-table.cell align="right" nowrap class="font-mono font-bold">{{ number_format($order->total_amount / 100, 0, ',', ' ') }} <span class="text-[10px] font-normal text-primary/50">FCFA</span></x-table.cell>
                <x-table.cell nowrap>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $statusStyles[$order->status] ?? 'bg-gray-100 text-gray-700' }}">{{ $order->statusLabel() }}</span>
                </x-table.cell>
                <x-table.cell hide="3xl" nowrap>
                    @if($order->issuer_signature)
                        <span class="inline-flex items-center gap-1 rounded border border-blue-200/50 bg-blue-50/80 px-2 py-0.5 text-[11px] text-blue-900">
                            <i data-lucide="pen-tool" class="h-3 w-3 text-blue-700" aria-hidden="true"></i>
                            <strong class="font-signature text-sm leading-none">{{ $order->issuer_signature }}</strong>
                        </span>
                    @else
                        <span class="text-[11px] text-primary/50">{{ $order->createdBy?->name ?? '—' }}</span>
                    @endif
                </x-table.cell>
                <x-table.actions :label="'Actions pour le bon '.$order->number">
                    <x-table.action :href="route('economat.orders.show', $order)" icon="eye">Détails</x-table.action>
                    <x-table.action :href="route('economat.orders.print', $order)" icon="printer" target="_blank">Imprimer</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection
