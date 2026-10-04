@extends('layouts.hotel')

@section('title', 'Inventaires physiques — Économat')

@section('content')
<div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Inventaires physiques</h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            Comptage physique contradictoire du magasin central (Économat). Rapprochement entre le stock théorique issu des entrées/sorties et le stock réel constaté en rayon.
        </p>
        @php
            $calendrier = \App\Support\InventorySchedule::current();
            $prochainInventaire = $calendrier->next(now());
        @endphp
        <p class="text-xs text-primary/55 mt-2 inline-flex items-center gap-1.5">
            <i data-lucide="calendar-days" class="w-3.5 h-3.5"></i>
            @if($prochainInventaire)
                Inventaire général : {{ lcfirst($calendrier->describe()) }} — prochain le
                <strong>{{ $prochainInventaire->locale('fr')->isoFormat('dddd D MMMM YYYY') }}</strong>.
            @else
                Aucun inventaire général planifié.
            @endif
            @if(\App\Support\SettingsTabs::peutRegler(auth()->user(), 'inventaire'))
                <a href="{{ route('settings.index', ['tab' => 'inventaire']) }}" class="underline">Régler le calendrier</a>
            @endif
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 shrink-0">
    @droit('economat.count_sheets.voir')
        <a href="{{ route('economat.count_sheets.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2 border border-secondary/30 text-primary text-sm font-medium rounded-lg hover:bg-accent/20 transition-colors">
            <i data-lucide="printer" class="w-4 h-4"></i>
            Fiches de comptage
        </a>
    @enddroit
    @droit('economat.stock_counts.creer')
        @if(!$openCount)
            <a href="{{ route('economat.stock_counts.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm shrink-0">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                Nouvel inventaire
            </a>
        @endif
    @enddroit
    </div>
</div>

@include('economat.partials.flash')

{{-- Métriques globales --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="bg-white rounded-xl border border-secondary/20 p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/45">Inventaires clôturés</p>
        <p class="text-xl font-bold text-primary mt-1">{{ $stats['closed_counts'] }} <span class="text-xs text-primary/40 font-normal">/ {{ $stats['total_counts'] }}</span></p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-red-600">Pertes constatées</p>
        <p class="text-xl font-bold text-red-700 mt-1 font-mono">−{{ number_format($stats['total_losses'] / 100, 0, ',', ' ') }} <span class="text-xs text-red-600/70 font-sans">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-green-600">Excédents constatés</p>
        <p class="text-xl font-bold text-green-700 mt-1 font-mono">+{{ number_format($stats['total_surplus'] / 100, 0, ',', ' ') }} <span class="text-xs text-green-600/70 font-sans">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-4">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary/45">Écart net total</p>
        @php $net = $stats['net_variance']; @endphp
        <p class="text-xl font-bold font-mono mt-1 {{ $net < 0 ? 'text-red-700' : ($net > 0 ? 'text-green-700' : 'text-primary') }}">
            {{ $net > 0 ? '+' : ($net < 0 ? '−' : '') }}{{ number_format(abs($net) / 100, 0, ',', ' ') }} <span class="text-xs font-sans text-primary/40">FCFA</span>
        </p>
    </div>
</div>

{{-- Alerte inventaire en cours --}}
@if($openCount)
    <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50/80 p-4 sm:p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <div class="p-2 rounded-lg bg-amber-100 text-amber-700 shrink-0">
                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-amber-950 font-mono text-sm">{{ $openCount->reference }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-900">En cours de comptage</span>
                    </div>
                    <p class="text-xs text-amber-900/70 mt-1">
                        Périmètre : <strong>{{ $openCount->category?->name ?? 'Tout le magasin central' }}</strong> · 
                        Ouvert le {{ $openCount->created_at->format('d/m/Y à H:i') }} par {{ $openCount->openedBy?->name ?? '—' }}
                        · {{ $openCount->lines()->whereNotNull('counted_quantity')->count() }} / {{ $openCount->lines()->count() }} articles comptés ({{ $openCount->progressPercentage() }}%)
                    </p>
                </div>
            </div>
            <a href="{{ route('economat.stock_counts.show', $openCount) }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-medium rounded-lg transition-colors shrink-0">
                <span>Poursuivre la saisie</span>
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
        </div>
    </div>
@endif

{{-- Filtres --}}
<div class="bg-white border border-secondary/20 rounded-xl p-4 mb-4">
    <form method="GET" action="{{ route('economat.stock_counts.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-semibold text-primary/60 mb-1">Statut</label>
            <select name="status" class="w-full text-xs border border-secondary/30 rounded-lg px-2.5 py-2 bg-white text-primary outline-none focus:border-primary">
                <option value="">Tous les statuts</option>
                <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>En cours</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Clôturé</option>
                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Annulé</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-primary/60 mb-1">Catégorie</label>
            <select name="category" class="w-full text-xs border border-secondary/30 rounded-lg px-2.5 py-2 bg-white text-primary outline-none focus:border-primary">
                <option value="">Toutes les catégories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-primary/60 mb-1">Du</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full text-xs border border-secondary/30 rounded-lg px-2.5 py-1.5 bg-white text-primary outline-none focus:border-primary">
        </div>
        <div class="flex gap-2">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-primary/60 mb-1">Au</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full text-xs border border-secondary/30 rounded-lg px-2.5 py-1.5 bg-white text-primary outline-none focus:border-primary">
            </div>
            <button type="submit" class="px-3 py-2 bg-primary text-white rounded-lg hover:bg-surface-dark text-xs self-end">
                <i data-lucide="filter" class="w-3.5 h-3.5"></i>
            </button>
            @if(request()->hasAny(['status', 'category', 'date_from', 'date_to']))
                <a href="{{ route('economat.stock_counts.index') }}" class="px-2.5 py-2 border border-secondary/30 text-primary/60 hover:text-primary rounded-lg text-xs self-end" title="Effacer les filtres">
                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                </a>
            @endif
        </div>
    </form>
</div>

{{-- Tableau des inventaires --}}
@php
    $badgeStyles = [
        'draft'     => 'bg-amber-50 text-amber-700 border-amber-200',
        'closed'    => 'bg-green-50 text-green-700 border-green-200',
        'cancelled' => 'bg-gray-100 text-gray-600 border-gray-200',
    ];
@endphp
<x-table :rows="$counts" empty="Aucun inventaire physique enregistré. Ouvrez une feuille d'inventaire pour lancer le premier comptage contradictoire." empty-icon="clipboard-check" caption="Inventaires du magasin central">
    <x-slot:head>
        <x-table.col>Réf.</x-table.col>
        <x-table.col hide="lg">Date</x-table.col>
        <x-table.col hide="xl">Périmètre</x-table.col>
        <x-table.col>Statut</x-table.col>
        <x-table.col align="right" hide="2xl">Valeur théorique</x-table.col>
        <x-table.col align="right" hide="2xl">Valeur constatée</x-table.col>
        <x-table.col align="right">Écart valorisé</x-table.col>
        <x-table.col hide="3xl">Clôturé par</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($counts as $count)
        @php $var = (int) $count->variance_value; @endphp
        <x-table.row :href="route('economat.stock_counts.show', $count)">
            <x-table.cell nowrap>
                <a href="{{ route('economat.stock_counts.show', $count) }}" class="font-mono font-medium text-primary hover:underline">{{ $count->reference }}</a>
            </x-table.cell>
            <x-table.cell hide="lg" nowrap class="text-primary/70">{{ $count->count_date->format('d/m/Y') }}</x-table.cell>
            <x-table.cell hide="xl" nowrap class="text-primary/80">
                {{ $count->category?->name ?? 'Tout le magasin' }}
                <span class="block text-xs text-primary/45">({{ $count->lines_count }} articles)</span>
            </x-table.cell>
            <x-table.cell nowrap>
                <span class="inline-flex items-center rounded border px-2 py-0.5 text-xs font-semibold {{ $badgeStyles[$count->status] ?? 'bg-gray-100' }}">{{ $count->statusLabel() }}</span>
            </x-table.cell>
            <x-table.cell align="right" hide="2xl" nowrap class="font-mono text-xs text-primary/70">{{ number_format($count->total_theoretical_value / 100, 0, ',', ' ') }} F</x-table.cell>
            <x-table.cell align="right" hide="2xl" nowrap class="font-mono text-xs">
                @if($count->isClosed())
                    <span class="font-medium text-primary">{{ number_format($count->total_counted_value / 100, 0, ',', ' ') }} F</span>
                @else
                    <span class="text-primary/40">—</span>
                @endif
            </x-table.cell>
            <x-table.cell align="right" nowrap class="font-mono text-xs">
                @if($count->isClosed())
                    <span class="font-bold {{ $var < 0 ? 'text-red-600' : ($var > 0 ? 'text-green-600' : 'text-primary/60') }}">{{ $var > 0 ? '+' : ($var < 0 ? '−' : '') }}{{ number_format(abs($var) / 100, 0, ',', ' ') }} F</span>
                @else
                    <span class="text-primary/40">—</span>
                @endif
            </x-table.cell>
            <x-table.cell hide="3xl" nowrap class="text-xs text-primary/60">
                @if($count->closedBy)
                    {{ $count->closedBy->name }}
                    <span class="block text-[10px] text-primary/45">{{ $count->closed_at?->format('d/m/Y H:i') }}</span>
                @else
                    <span class="text-primary/30">—</span>
                @endif
            </x-table.cell>
            <x-table.actions :label="'Actions pour l\'inventaire '.$count->reference">
                <x-table.action :href="route('economat.stock_counts.show', $count)" icon="eye">Consulter la feuille</x-table.action>
                @if($count->isClosed())
                    <x-table.action :href="route('economat.stock_counts.report', $count)" icon="printer" target="_blank">Procès-verbal</x-table.action>
                @endif
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
@endsection
