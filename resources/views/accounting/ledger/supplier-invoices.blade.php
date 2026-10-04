@extends('layouts.hotel')

@section('title', 'Factures fournisseurs')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Factures fournisseurs</h1>
        <p class="text-sm text-primary/60 mt-1">Dettes reçues et retenues prélevées</p>
    </div>
    <a href="{{ route('accounting.ledger.suppliers.create') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shrink-0">
        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
        Saisir une facture
    </a>
</div>

@include('accounting.ledger.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

<div class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Total facturé TTC</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($totaux['ttc']) }}</p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Retenues prélevées</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($totaux['retenues']) }}</p>
        <a href="{{ route('accounting.ledger.withholding') }}" class="text-[10px] text-primary/50 hover:text-primary hover:underline">Voir l'état déclaratif</a>
    </div>
</div>

<form method="GET" class="flex flex-col sm:flex-row gap-2 mb-4">
    <select name="fournisseur" onchange="this.form.submit()"
            class="flex-1 sm:max-w-xs rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
        <option value="">Tous les fournisseurs</option>
        @foreach($fournisseurs as $f)
            <option value="{{ $f->id }}" @selected(request('fournisseur') == $f->id)>{{ $f->name }}</option>
        @endforeach
    </select>
    <label class="inline-flex items-center gap-2 px-3 py-2.5 rounded-lg border border-secondary/25 bg-white text-xs text-primary/70 cursor-pointer">
        <input type="checkbox" name="retenues" value="1" onchange="this.form.submit()" @checked(request()->boolean('retenues'))
               class="rounded border-secondary/40 text-primary focus:ring-primary/30">
        Avec retenue seulement
    </label>
</form>

<x-table :rows="$factures" empty="Aucune facture fournisseur enregistrée." empty-icon="truck" caption="Factures fournisseurs">
    <x-slot:head>
        <x-table.col hide="md">Date</x-table.col>
        <x-table.col>Référence</x-table.col>
        <x-table.col>Fournisseur</x-table.col>
        <x-table.col hide="xl">Nature</x-table.col>
        <x-table.col align="right" hide="lg">TTC</x-table.col>
        <x-table.col align="right" hide="2xl">Retenue</x-table.col>
        <x-table.col align="right">Net à payer</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($factures as $facture)
        <x-table.row :href="route('accounting.ledger.suppliers.show', $facture)">
            <x-table.cell hide="md" nowrap class="text-primary/70">{{ $facture->invoice_date->format('d/m/Y') }}</x-table.cell>
            <x-table.cell>
                <a href="{{ route('accounting.ledger.suppliers.show', $facture) }}" class="font-mono font-medium text-primary hover:underline">{{ $facture->number }}</a>
                @if($facture->purchaseOrder)
                    <span class="block text-[10px] text-primary/45">{{ $facture->purchaseOrder->number }}</span>
                @endif
                @if($facture->hasReceptionVariance())
                    <span class="mt-0.5 inline-block rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700" title="{{ $facture->variance_reason }}">Écart +{{ $fcfa($facture->reception_variance) }}</span>
                @endif
            </x-table.cell>
            <x-table.cell>{{ $facture->supplier?->name ?? '—' }}</x-table.cell>
            <x-table.cell hide="xl" class="text-primary/60">{{ $facture->chargeLabel() }}</x-table.cell>
            <x-table.cell align="right" hide="lg" nowrap class="text-primary/75">{{ $fcfa($facture->amount_ttc) }}</x-table.cell>
            <x-table.cell align="right" hide="2xl" nowrap>
                @if($facture->hasWithholding())
                    <span class="font-medium text-amber-700">{{ $fcfa($facture->withholding_amount) }}</span>
                    <span class="block text-[10px] text-primary/45">{{ rtrim(rtrim(number_format($facture->withholdingRate(), 2, ',', ''), '0'), ',') }} %</span>
                @else
                    <span class="text-primary/25">—</span>
                @endif
            </x-table.cell>
            <x-table.cell align="right" nowrap class="font-semibold">{{ $fcfa($facture->net_payable) }}</x-table.cell>
            <x-table.actions :label="'Actions pour la facture '.$facture->number">
                <x-table.action :href="route('accounting.ledger.suppliers.show', $facture)" icon="eye">Ouvrir</x-table.action>
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
@endsection
