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
    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        @if($orders->isEmpty())
            <div class="p-12 text-center text-primary/40">
                <i data-lucide="clipboard-list" class="w-12 h-12 text-primary/20 mx-auto mb-3"></i>
                <p class="text-sm font-medium text-primary/60">
                    {{ !empty($filtres) ? 'Aucun bon de commande ne correspond aux filtres sélectionnés.' : 'Aucun bon de commande enregistré.' }}
                </p>
                @if(!empty($filtres))
                    <div class="mt-3">
                        <a href="{{ route('economat.orders.index') }}" class="text-xs text-primary underline">
                            Réinitialiser les filtres
                        </a>
                    </div>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-surface-light border-b border-secondary/20 text-left text-primary/60 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-4">N° de Bon</th>
                            <th class="py-3 px-4">Fournisseur</th>
                            <th class="py-3 px-4">Date Émission</th>
                            <th class="py-3 px-4 text-center">Articles</th>
                            <th class="py-3 px-4 text-right">Montant Total</th>
                            <th class="py-3 px-4">Statut</th>
                            <th class="py-3 px-4">Signataire</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($orders as $order)
                            <tr class="hover:bg-accent/5 transition-colors">
                                {{-- N° de Bon --}}
                                <td class="py-3 px-4 font-mono font-bold text-primary whitespace-nowrap">
                                    <a href="{{ route('economat.orders.show', $order) }}" class="hover:underline flex items-center gap-1.5">
                                        <i data-lucide="file-text" class="w-3.5 h-3.5 text-primary/50"></i>
                                        <span>{{ $order->number }}</span>
                                    </a>
                                </td>

                                {{-- Fournisseur --}}
                                <td class="py-3 px-4">
                                    <div class="font-medium text-primary">{{ $order->supplier?->name ?? 'Fournisseur non spécifié' }}</div>
                                    @if($order->supplier)
                                        <div class="text-[10px] text-primary/50 flex items-center gap-2 mt-0.5">
                                            @if($order->supplier->code)<span class="font-mono bg-gray-100 px-1 rounded">{{ $order->supplier->code }}</span>@endif
                                            @if($order->supplier->phone)<span><i data-lucide="phone" class="w-2.5 h-2.5 inline text-primary/40"></i> {{ $order->supplier->phone }}</span>@endif
                                        </div>
                                    @endif
                                </td>

                                {{-- Date émission --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="text-primary/70">{{ $order->created_at->format('d/m/Y') }}</span>
                                    <span class="text-[10px] text-primary/40 block">{{ $order->created_at->format('H:i') }}</span>
                                </td>

                                {{-- Nombre d'articles --}}
                                <td class="py-3 px-4 text-center font-mono font-medium text-primary/70">
                                    {{ $order->lines_count }}
                                </td>

                                {{-- Montant Total --}}
                                <td class="py-3 px-4 text-right font-mono font-bold text-primary whitespace-nowrap">
                                    {{ number_format($order->total_amount / 100, 0, ',', ' ') }} <span class="text-[10px] font-normal text-primary/50">FCFA</span>
                                </td>

                                {{-- Statut --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ $statusStyles[$order->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ $order->statusLabel() }}
                                    </span>
                                </td>

                                {{-- Signataire --}}
                                <td class="py-3 px-4 whitespace-nowrap">
                                    @if($order->issuer_signature)
                                        <span class="inline-flex items-center gap-1 text-[11px] text-blue-900 bg-blue-50/80 px-2 py-0.5 rounded border border-blue-200/50">
                                            <i data-lucide="pen-tool" class="w-3 h-3 text-blue-700"></i>
                                            <strong class="font-signature text-sm leading-none">{{ $order->issuer_signature }}</strong>
                                        </span>
                                    @else
                                        <span class="text-primary/50 text-[11px]">{{ $order->createdBy?->name ?? '—' }}</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <a href="{{ route('economat.orders.print', $order) }}" target="_blank" rel="noopener"
                                            class="p-1.5 bg-white border border-secondary/20 text-primary/70 hover:text-primary hover:border-primary/50 rounded-lg transition-colors shadow-xs"
                                            title="Imprimer le bon de commande">
                                            <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                                        </a>

                                        <a href="{{ route('economat.orders.show', $order) }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-surface-light border border-secondary/25 text-primary text-[11px] font-medium rounded-lg hover:bg-secondary/15 transition-colors">
                                            <span>Détails</span>
                                            <i data-lucide="chevron-right" class="w-3 h-3"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="p-4 border-t border-secondary/20 bg-surface-light/50">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
