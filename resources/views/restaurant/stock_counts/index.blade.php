@extends('layouts.hotel')

@section('title', 'Inventaires')

@section('content')
@include('restaurant.partials.service-absent', ['service' => \App\Models\PointOfSale::SERVICE_STOCK])
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Inventaires</h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            Le stock affiché par le système est théorique : il découle des fiches techniques. L'inventaire physique
            le confronte au réel. L'écart mesure ce qui part en gaspillage, en sur-portionnage ou en vol.
        </p>
    </div>

    @if($canManage && !$openCount && !$vueEnsemble)
        <form method="POST" action="{{ route('restaurant.stock_counts.store') }}" class="shrink-0">
            @csrf
            <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                Ouvrir un inventaire
            </button>
        </form>
    @endif
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($openCount)
    <a href="{{ route('restaurant.stock_counts.show', $openCount) }}"
        class="mb-6 block rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 hover:bg-amber-100/60 transition-colors">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <i data-lucide="clipboard-list" class="w-4 h-4 text-amber-600 mt-0.5 shrink-0"></i>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-900">Inventaire {{ $openCount->reference }} en cours</p>
                    <p class="text-xs text-amber-800/80 mt-0.5">
                        Ouvert {{ $openCount->created_at->locale('fr')->diffForHumans() }}
                        @if($openCount->openedBy) par {{ $openCount->openedBy->name }} @endif
                        · saisis les quantités comptées puis clôture.
                    </p>
                </div>
            </div>
            <i data-lucide="arrow-right" class="w-4 h-4 text-amber-600 shrink-0"></i>
        </div>
    </a>
@endif

<x-table :rows="$counts" empty="Aucun inventaire réalisé." empty-icon="clipboard-list" caption="Inventaires du garde-manger">
    <x-slot:head>
        <x-table.col>Référence</x-table.col>
        <x-table.col>État</x-table.col>
        <x-table.col align="right" hide="lg">Lignes</x-table.col>
        <x-table.col align="right">Écart valorisé</x-table.col>
        <x-table.col hide="xl">Clôturé par</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($counts as $count)
        <x-table.row :href="route('restaurant.stock_counts.show', $count)">
            <x-table.cell>
                <a href="{{ route('restaurant.stock_counts.show', $count) }}" class="text-sm font-semibold text-primary hover:underline">{{ $count->reference }}</a>
                @if($vueEnsemble && $count->pointOfSale)
                    <p class="text-[11px] font-semibold text-primary/45">{{ $count->pointOfSale->name }}</p>
                @endif
                <p class="text-[11px] text-primary/45">{{ $count->created_at->locale('fr')->isoFormat('D MMM YYYY, HH:mm') }}</p>
            </x-table.cell>
            <x-table.cell nowrap>
                @if($count->isClosed())
                    <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700">Clôturé</span>
                @else
                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700">En cours</span>
                @endif
            </x-table.cell>
            <x-table.cell align="right" hide="lg" class="text-primary/60">{{ $count->lines_count }}</x-table.cell>
            <x-table.cell align="right" nowrap>
                @if($count->isClosed())
                    <span class="text-sm font-semibold {{ $count->variance_value < 0 ? 'text-red-600' : ($count->variance_value > 0 ? 'text-amber-600' : 'text-green-600') }}">{{ $count->variance_value > 0 ? '+' : '' }}{{ number_format($count->variance_value / 100, 0, ',', ' ') }} FCFA</span>
                @else
                    <span class="text-xs text-primary/30">—</span>
                @endif
            </x-table.cell>
            <x-table.cell hide="xl" class="text-xs text-primary/60">{{ $count->closedBy?->name ?? '—' }}</x-table.cell>
            <x-table.actions :label="'Actions pour l\'inventaire '.$count->reference">
                <x-table.action :href="route('restaurant.stock_counts.show', $count)" icon="{{ $count->isClosed() ? 'eye' : 'clipboard-pen' }}">{{ $count->isClosed() ? 'Ouvrir' : 'Saisir le comptage' }}</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
@endsection
