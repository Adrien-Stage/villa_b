@extends('layouts.hotel')

@section('title', 'Journaux')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Journaux</h1>
    <p class="text-sm text-primary/60 mt-1">{{ $periode['label'] }}</p>
</div>

@include('accounting.ledger.partials.nav')

<div class="flex flex-wrap gap-1.5 mb-4">
    <a href="{{ route('accounting.ledger.journals') }}"
       class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ !$journal ? 'bg-primary text-white' : 'bg-white border border-secondary/25 text-primary/60 hover:text-primary' }}">
        Tous
    </a>
    @foreach($journaux as $j)
        <a href="{{ route('accounting.ledger.journals', ['journal' => $j->id]) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $journal?->id === $j->id ? 'bg-primary text-white' : 'bg-white border border-secondary/25 text-primary/60 hover:text-primary' }}"
           title="{{ $j->label }}">
            <span class="font-mono">{{ $j->code }}</span>
        </a>
    @endforeach
</div>

<x-table :rows="$ecritures" empty="Aucune écriture sur cette période." empty-icon="book-open" caption="Écritures du journal">
    <x-slot:head>
        <x-table.col>Date</x-table.col>
        <x-table.col>Jnl</x-table.col>
        <x-table.col hide="lg">Pièce</x-table.col>
        <x-table.col>Libellé</x-table.col>
        <x-table.col align="center" hide="xl">Lignes</x-table.col>
        <x-table.col align="right">Montant</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($ecritures as $e)
        <x-table.row :href="route('accounting.ledger.entry', $e)" :muted="$e->isReversed()">
            <x-table.cell nowrap class="text-primary/70">{{ $e->entry_date->format('d/m/Y') }}</x-table.cell>
            <x-table.cell><span class="inline-flex rounded bg-accent/40 px-1.5 py-0.5 text-[10px] font-semibold text-primary">{{ $e->journal?->code }}</span></x-table.cell>
            <x-table.cell hide="lg" class="font-mono text-primary/60">{{ $e->reference ?: '—' }}</x-table.cell>
            <x-table.cell>
                <a href="{{ route('accounting.ledger.entry', $e) }}" class="text-primary hover:underline">{{ $e->label }}</a>
                @if($e->isReversed())
                    <span class="ml-1 inline-flex rounded bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold text-red-700">extournée</span>
                @elseif($e->isReversal())
                    <span class="ml-1 inline-flex rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">extourne</span>
                @endif
            </x-table.cell>
            <x-table.cell align="center" hide="xl" class="text-primary/50">{{ $e->lines->count() }}</x-table.cell>
            <x-table.cell align="right" nowrap class="font-medium">{{ $fcfa($e->totalDebit()) }}</x-table.cell>
            <x-table.actions :label="'Actions pour l\'écriture '.$e->label">
                <x-table.action :href="route('accounting.ledger.entry', $e)" icon="eye">Ouvrir</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
@endsection
