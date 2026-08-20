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

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Date</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Référence</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Fournisseur</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Nature</th>
                    <th class="px-3 py-2.5 text-right font-semibold">TTC</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Retenue</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Net à payer</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($factures as $facture)
                    <tr class="hover:bg-accent/10">
                        <td class="px-4 py-2 whitespace-nowrap text-primary/70">{{ $facture->invoice_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('accounting.ledger.suppliers.show', $facture) }}"
                               class="font-mono font-medium text-primary hover:underline">{{ $facture->number }}</a>
                            @if($facture->purchaseOrder)
                                <span class="block text-[10px] text-primary/40">{{ $facture->purchaseOrder->number }}</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-primary">{{ $facture->supplier?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-primary/60">{{ $facture->chargeLabel() }}</td>
                        <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $fcfa($facture->amount_ttc) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if($facture->hasWithholding())
                                <span class="text-amber-700 font-medium">{{ $fcfa($facture->withholding_amount) }}</span>
                                <span class="block text-[10px] text-primary/40">{{ rtrim(rtrim(number_format($facture->withholdingRate(), 2, ',', ''), '0'), ',') }} %</span>
                            @else
                                <span class="text-primary/25">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-right font-semibold text-primary whitespace-nowrap">{{ $fcfa($facture->net_payable) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-primary/50">
                            <i data-lucide="truck" class="w-8 h-8 mx-auto mb-3 text-primary/20"></i>
                            Aucune facture fournisseur enregistrée.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($factures->hasPages())
    <div class="mt-4">{{ $factures->links() }}</div>
@endif
@endsection
