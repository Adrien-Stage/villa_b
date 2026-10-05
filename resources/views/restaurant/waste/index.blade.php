@extends('layouts.hotel')

@section('title', 'Pertes & Gaspillage — Restaurant')

@section('content')
@include('restaurant.partials.service-absent', ['service' => \App\Models\PointOfSale::SERVICE_STOCK])
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Pertes, Gaspillage & Déchets</h1>
        <p class="text-sm text-primary/50 mt-0.5">Comptabilité matière et traçabilité des sorties non vendues (cuisine, bar, restaurant)</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="{{ route('restaurant.consumption.index') }}" class="inline-flex items-center gap-2 px-3 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="pie-chart" class="w-3.5 h-3.5"></i> Analyse Consommation
        </a>
        @can('creer', \App\Models\RestaurantWasteLog::class)
        <a href="{{ route('restaurant.waste.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
            <i data-lucide="plus" class="w-3.5 h-3.5"></i> Déclarer une perte
        </a>
        @else
            @if(app(\App\Services\PermissionResolver::class)->allows(auth()->user(), 'restaurant.waste.creer'))
            <a href="{{ route('restaurant.waste.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-95 transition-opacity">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Déclarer une perte
            </a>
            @endif
        @endcan
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <p class="font-semibold mb-1">Erreur :</p>
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- KPI Cards --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Total Pertes (Période)</p>
            <p class="text-2xl font-heading font-semibold text-red-600 mt-1">
                {{ number_format($totalValuationCentimes / 100, 0, ',', ' ') }} <span class="text-sm font-normal text-primary/60">FCFA</span>
            </p>
        </div>
        <div class="w-10 h-10 rounded-full bg-red-50 text-red-600 flex items-center justify-center">
            <i data-lucide="trash-2" class="w-5 h-5"></i>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Fiches Déclarées</p>
            <p class="text-2xl font-heading font-semibold text-primary mt-1">{{ $totalDeclarations }}</p>
        </div>
        <div class="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
            <i data-lucide="file-text" class="w-5 h-5"></i>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-4 border border-secondary/15">
        <p class="text-xs uppercase tracking-wider text-primary/50 font-semibold mb-2">Principaux Motifs</p>
        <div class="space-y-1 text-xs">
            @forelse($reasonsBreakdown->take(3) as $r)
                <div class="flex items-center justify-between text-primary/70">
                    <span>{{ $reasonLabels[$r->reason] ?? $r->reason }} ({{ $r->count }})</span>
                    <span class="font-semibold text-primary">{{ number_format($r->total_val / 100, 0, ',', ' ') }} FCFA</span>
                </div>
            @empty
                <p class="text-primary/40 italic">Aucune perte enregistrée</p>
            @endforelse
        </div>
    </div>
</div>

{{-- Filtres --}}
<div class="bg-white rounded-xl shadow-sm border border-secondary/15 p-4 mb-6">
    <form method="GET" action="{{ route('restaurant.waste.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Du</label>
            <input type="date" name="start_date" value="{{ $startDate }}" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Au</label>
            <input type="date" name="end_date" value="{{ $endDate }}" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Motif</label>
            <select name="reason" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                <option value="">Tous les motifs</option>
                @foreach($reasonLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(request('reason') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Atelier / Rayon</label>
            <select name="department" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                <option value="">Tous les ateliers</option>
                @foreach($departmentLabels as $val => $lbl)
                    <option value="{{ $val }}" @selected(request('department') === $val)>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-primary/60 mb-1">Ingrédient</label>
            <select name="item_id" class="w-full text-xs rounded-lg border-secondary/25 focus:border-primary focus:ring-primary">
                <option value="">Tous les articles</option>
                @foreach($activeItems as $item)
                    <option value="{{ $item->id }}" @selected(request('item_id') == $item->id)>{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="w-full py-2 px-3 bg-primary text-white text-xs font-semibold rounded-lg hover:opacity-90 transition-opacity">
                Filtrer
            </button>
            <a href="{{ route('restaurant.waste.index') }}" class="py-2 px-3 border border-secondary/25 bg-secondary/10 text-primary text-xs rounded-lg hover:bg-secondary/20" title="Réinitialiser">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
            </a>
        </div>
    </form>
</div>

{{-- Tableau des déclarations --}}
<x-table :rows="$logs" empty="Aucune perte ou déchet enregistré sur la période sélectionnée." empty-icon="inbox" caption="Pertes et déchets">
    <x-slot:head>
        <x-table.col>Date &amp; réf.</x-table.col>
        <x-table.col>Article</x-table.col>
        <x-table.col align="right">Quantité</x-table.col>
        <x-table.col align="right" hide="2xl">Coût unitaire</x-table.col>
        <x-table.col align="right">Valeur</x-table.col>
        <x-table.col hide="lg">Motif &amp; atelier</x-table.col>
        <x-table.col hide="xl">Responsable</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($logs as $log)
        <x-table.row :href="route('restaurant.waste.show', $log)">
            <x-table.cell nowrap>
                <a href="{{ route('restaurant.waste.show', $log) }}" class="font-mono text-sm font-semibold text-primary hover:underline">{{ $log->reference }}</a>
                <div class="text-[11px] text-primary/50">{{ $log->occurred_at?->format('d/m/Y H:i') }}</div>
            </x-table.cell>
            <x-table.cell>
                <span class="font-medium text-primary">{{ $log->item?->name ?? 'Article inconnu' }}</span>
                @if($log->item?->category)
                    <div class="text-[10px] text-primary/50">{{ $log->item->category->name }}</div>
                @endif
            </x-table.cell>
            <x-table.cell align="right" nowrap class="font-medium text-red-600">-{{ rtrim(rtrim(number_format((float) $log->quantity, 3, ',', ' '), '0'), ',') }} {{ $log->item?->unit }}</x-table.cell>
            <x-table.cell align="right" hide="2xl" nowrap class="text-primary/70">{{ number_format($log->unitCostFcfa(), 2, ',', ' ') }} FCFA</x-table.cell>
            <x-table.cell align="right" nowrap class="font-semibold text-red-600">{{ $log->formattedTotalCost() }}</x-table.cell>
            <x-table.cell hide="lg">
                <span class="inline-flex items-center rounded bg-red-100 px-2 py-0.5 text-[11px] font-medium text-red-800">{{ $log->reasonLabel() }}</span>
                <div class="mt-0.5 text-[10px] text-primary/50">{{ $log->departmentLabel() }}</div>
            </x-table.cell>
            <x-table.cell hide="xl" class="text-primary/70">
                <div>{{ $log->responsible_person ?? 'Non spécifié' }}</div>
                <div class="text-[10px] text-primary/45">Saisi par {{ $log->recordedBy?->name ?? 'Système' }}</div>
            </x-table.cell>
            <x-table.actions :label="'Actions pour la perte '.$log->reference">
                <x-table.action :href="route('restaurant.waste.show', $log)" icon="eye">Détail</x-table.action>
                <x-table.action :href="route('restaurant.waste.print', $log)" icon="printer" target="_blank">Imprimer le PV</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
@endsection
