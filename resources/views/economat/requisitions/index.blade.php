@extends('layouts.hotel')

@section('title', 'Bons de réquisition & Demandes des services — Économat')

@section('content')
@php
    $statusStyles = [
        'pending'   => 'bg-blue-50 text-blue-700 border border-blue-200',
        'approved'  => 'bg-indigo-50 text-indigo-700 border border-indigo-200',
        'rejected'  => 'bg-red-50 text-red-700 border border-red-200',
        'delivered' => 'bg-green-50 text-green-700 border border-green-200',
        'cancelled' => 'bg-gray-100 text-gray-600 border border-gray-200',
    ];
@endphp

<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-heading font-semibold text-primary flex items-center gap-2">
                <i data-lucide="inbox" class="w-7 h-7 text-primary"></i>
                <span>{{ $isKeeper ? 'Bons de réquisition & Demandes des services' : 'Mes demandes à l\'économat' }}</span>
            </h1>
            <p class="text-sm text-primary/60 mt-1 max-w-3xl">
                {{ $isKeeper ? 'Traçabilité, arbitrage et livraison des bons d\'approvisionnement émis par l\'hébergement, le housekeeping, la restauration, la boutique et la comptabilité.' : 'Sollicitez des marchandises, fournitures de bureau et consommables auprès du magasin central.' }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @droit('economat.requisitions.export')
                <x-barre-export route="economat.requisitions.export" />
            @enddroit

            @droit('economat.requisitions.creer')
                <a href="{{ route('economat.requisitions.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                    <i data-lucide="plus" class="w-4 h-4"></i>
                    <span>Nouveau bon</span>
                </a>
            @enddroit
        </div>
    </div>

    @include('economat.partials.flash')

    {{-- 4 Cartes KPI Synthèse des Bons --}}
    @if(isset($stats))
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/50">Total des bons</p>
                <p class="text-2xl font-bold font-mono text-primary mt-1">{{ $stats['total'] }}</p>
                <p class="text-xs text-primary/45 mt-1">Bons émis sur le périmètre</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-blue-600">En attente d'arbitrage</p>
                <p class="text-2xl font-bold font-mono text-blue-700 mt-1">{{ $stats['pending'] }}</p>
                <p class="text-xs text-blue-600/70 mt-1">À valider par l'économat</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Validés (à servir)</p>
                <p class="text-2xl font-bold font-mono text-indigo-700 mt-1">{{ $stats['approved'] }}</p>
                <p class="text-xs text-indigo-600/70 mt-1">Prêts pour livraison physique</p>
            </div>

            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-green-600">Livrés / Servis</p>
                <p class="text-2xl font-bold font-mono text-green-700 mt-1">{{ $stats['delivered'] }}</p>
                <p class="text-xs text-green-600/70 mt-1">Déstockés et transférés</p>
            </div>
        </div>
    @endif

    {{-- Filtres avancés --}}
    @include('economat.requisitions.partials.filtres')

    {{-- Tableau des Bons --}}
    <x-table :rows="$requisitions" :empty="$filtres ? 'Aucun bon de réquisition ne correspond aux filtres sélectionnés.' : 'Aucun bon de réquisition enregistré.'" empty-icon="inbox" caption="Bons de réquisition">
        @if($filtres)
            <x-slot:emptyActions>
                <a href="{{ route('economat.requisitions.index') }}" class="text-primary underline">Réinitialiser les filtres</a>
            </x-slot:emptyActions>
        @endif
        <x-slot:head>
            <x-table.col>N° de bon</x-table.col>
            <x-table.col>Service demandeur</x-table.col>
            <x-table.col hide="xl">Demandeur</x-table.col>
            <x-table.col hide="lg">Émission</x-table.col>
            <x-table.col align="right" hide="2xl">Articles</x-table.col>
            <x-table.col>Statut</x-table.col>
            <x-table.col hide="3xl">Motif</x-table.col>
            <x-table.col actions />
        </x-slot:head>

        @foreach($requisitions as $req)
            <x-table.row :href="route('economat.requisitions.show', $req)">
                <x-table.cell nowrap>
                    <a href="{{ route('economat.requisitions.show', $req) }}" class="font-mono font-bold text-primary hover:underline">{{ $req->number }}</a>
                </x-table.cell>
                <x-table.cell class="font-medium">{{ $req->departmentLabel() }}</x-table.cell>
                <x-table.cell hide="xl" class="text-primary/70">{{ $req->requestedBy?->name ?? '—' }}</x-table.cell>
                <x-table.cell hide="lg" nowrap class="font-mono text-primary/60">
                    {{ $req->created_at->format('d/m/Y') }}
                    <span class="block text-[10px] text-primary/45">{{ $req->created_at->format('H:i') }}</span>
                </x-table.cell>
                <x-table.cell align="right" hide="2xl" class="font-mono font-bold">{{ $req->lines->count() }}</x-table.cell>
                <x-table.cell nowrap>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusStyles[$req->status] ?? 'bg-gray-100' }}">{{ $req->statusLabel() }}</span>
                </x-table.cell>
                <x-table.cell hide="3xl" class="max-w-xs truncate text-primary/60" title="{{ $req->purpose }}">{{ $req->purpose ?: '—' }}</x-table.cell>
                <x-table.actions :label="'Actions pour le bon '.$req->number">
                    <x-table.action :href="route('economat.requisitions.show', $req)" icon="eye">Détails</x-table.action>
                    <x-table.action :href="route('economat.requisitions.print', $req)" icon="printer" target="_blank">Imprimer</x-table.action>
                </x-table.actions>
            </x-table.row>
        @endforeach
    </x-table>
</div>
@endsection
