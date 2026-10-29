@extends('layouts.hotel')

@section('title', 'Dépôt technique')

@section('content')
@php
    $qte = fn ($q) => rtrim(rtrim(number_format(abs((float) $q), 3, ',', ' '), '0'), ',');
@endphp
<div class="max-w-5xl mx-auto">
    <div class="mb-6">
        <h1 class="text-xl font-heading font-semibold text-primary">Dépôt technique</h1>
        <p class="text-sm text-primary/60 mt-0.5">
            Les pièces du service, livrées par l'économat sur demande. Chaque intervention y prélève ce qu'elle emploie.
        </p>
    </div>

    @include('economat.partials.flash')

    @if($depots->isEmpty())
        <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center text-sm text-primary/55">
            Aucun dépôt pour le service technique. L'économe le crée dans Économat › Dépôts de service, pour le service « Technique / Maintenance ».
        </div>
    @endif

    @foreach($depots as $depot)
        @php($lignes = $stocks->get($depot->id, collect()))
        <section class="mb-8" aria-labelledby="depot-{{ $depot->id }}">
            <div class="flex items-center justify-between mb-2">
                <h2 id="depot-{{ $depot->id }}" class="font-heading font-semibold text-primary">{{ $depot->name }}</h2>
                <p class="text-sm text-primary/70">{{ number_format($lignes->sum(fn ($s) => $s->stockValue()) / 100, 0, ',', ' ') }} FCFA en stock</p>
            </div>
            <x-table :rows="$lignes" empty="Ce dépôt est vide." empty-icon="package" caption="Stock du dépôt">
                <x-slot:head>
                    <x-table.col>Pièce</x-table.col>
                    <x-table.col hide="md">Catégorie</x-table.col>
                    <x-table.col align="right">Stock</x-table.col>
                    <x-table.col align="center">Unité</x-table.col>
                    <x-table.col align="right" hide="sm">Valeur (FCFA)</x-table.col>
                </x-slot:head>
                @foreach($lignes as $stock)
                    <x-table.row :muted="(float) $stock->current_stock <= 0">
                        <x-table.cell class="text-sm">{{ $stock->item->name }} <span class="block text-[11px] text-primary/45">{{ $stock->item->reference }}</span></x-table.cell>
                        <x-table.cell hide="md" class="text-xs text-primary/60">{{ $stock->item->category?->name ?? '—' }}</x-table.cell>
                        <x-table.cell align="right" nowrap class="font-medium font-mono">{{ $qte($stock->current_stock) }}</x-table.cell>
                        <x-table.cell align="center" nowrap class="font-mono text-xs font-bold text-primary/70">{{ \App\Models\StockUnit::symbolFor($stock->item->unit) }}</x-table.cell>
                        <x-table.cell align="right" hide="sm" class="text-xs">{{ number_format($stock->stockValue() / 100, 0, ',', ' ') }}</x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </section>
    @endforeach

    @if($sorties->isNotEmpty())
        <section aria-labelledby="titre-sorties">
            <h2 id="titre-sorties" class="font-heading font-semibold text-primary mb-2">Dernières pièces employées</h2>
            <x-table :rows="$sorties" caption="Pièces employées">
                <x-slot:head>
                    <x-table.col>Le</x-table.col>
                    <x-table.col>Pièce</x-table.col>
                    <x-table.col align="right">Quantité</x-table.col>
                    <x-table.col align="center">Unité</x-table.col>
                    <x-table.col>Intervention</x-table.col>
                </x-slot:head>
                @foreach($sorties as $sortie)
                    <x-table.row>
                        <x-table.cell nowrap class="text-xs">{{ $sortie->occurred_at?->format('d/m H:i') }}</x-table.cell>
                        <x-table.cell class="text-sm">{{ $sortie->item?->name ?? '—' }}</x-table.cell>
                        <x-table.cell align="right" nowrap class="font-mono font-medium">{{ $qte($sortie->quantity) }}</x-table.cell>
                        <x-table.cell align="center" nowrap class="font-mono text-xs font-bold text-primary/70">{{ \App\Models\StockUnit::symbolFor($sortie->item?->unit) }}</x-table.cell>
                        <x-table.cell class="text-xs">
                            @if(isset($demandes[$sortie->source_id]))
                                <a href="{{ route('technique.demandes.show', $sortie->source_id) }}" class="text-primary hover:underline">{{ $demandes[$sortie->source_id] }}</a>
                            @endif
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </section>
    @endif
</div>
@endsection
