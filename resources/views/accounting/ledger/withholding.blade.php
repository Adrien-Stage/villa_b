@extends('layouts.hotel')

@section('title', 'État des retenues à la source')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Retenues à la source</h1>
    <p class="text-sm text-primary/60 mt-1">Du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }}</p>
</div>

@include('accounting.ledger.partials.nav')

@unless($confirme)
    <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-[11px] text-amber-900 leading-relaxed">
        <strong>Taux non confirmés pour le Cameroun.</strong> Cet état reflète fidèlement ce qui a été
        retenu, mais les taux appliqués proviennent d'un document de référence béninois et attendent
        la validation du cabinet. À vérifier avant tout dépôt de déclaration.
    </div>
@endunless

<div class="px-4 py-3 mb-5 rounded-xl bg-accent/20 border border-secondary/20 text-[11px] text-primary/70 leading-relaxed">
    <strong>Ce que vous devez reverser.</strong> Chaque somme retenue sur un fournisseur est une dette
    envers l'État, portée au crédit du compte <span class="font-mono">442100</span>. Cet état en donne
    la ventilation par nature — chaque taux se déclare séparément — et le détail par bénéficiaire,
    que l'administration réclame.
</div>

<form method="GET" class="flex flex-col sm:flex-row gap-2 mb-4">
    <div>
        <label class="block text-xs font-medium text-primary/70 mb-1.5">Du</label>
        <input type="date" name="from" value="{{ $from->toDateString() }}"
               class="rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
    </div>
    <div>
        <label class="block text-xs font-medium text-primary/70 mb-1.5">Au</label>
        <input type="date" name="to" value="{{ $to->toDateString() }}"
               class="rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
    </div>
    <div class="flex items-end">
        <button type="submit" class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
            Afficher
        </button>
    </div>
</form>

<div class="grid grid-cols-2 gap-3 mb-4">
    <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Base retenue (HT)</p>
        <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($etat['base']) }}</p>
    </div>
    <div class="bg-white rounded-xl border {{ $etat['total'] > 0 ? 'border-amber-300 bg-amber-50/60' : 'border-secondary/20' }} p-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Total à reverser</p>
        <p class="text-base font-heading font-bold {{ $etat['total'] > 0 ? 'text-amber-700' : 'text-primary' }} mt-0.5">{{ $fcfa($etat['total']) }}</p>
    </div>
</div>

@if($etat['byType'] !== [])
    <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-3 border-b border-secondary/15">
            <h2 class="text-sm font-semibold text-primary">Ventilation par nature</h2>
        </div>
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Nature</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Taux</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Factures</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Base HT</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Retenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @foreach($etat['byType'] as $ligne)
                    <tr class="hover:bg-accent/10">
                        <td class="px-4 py-2 text-primary font-medium">{{ $ligne['label'] }}</td>
                        <td class="px-3 py-2 text-right text-primary/60">{{ rtrim(rtrim(number_format($ligne['rate'] / 100, 2, ',', ''), '0'), ',') }} %</td>
                        <td class="px-3 py-2 text-right text-primary/60">{{ $ligne['count'] }}</td>
                        <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $fcfa($ligne['base']) }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-primary whitespace-nowrap">{{ $fcfa($ligne['amount']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-accent/20 font-semibold text-primary">
                <tr>
                    <td colspan="3" class="px-4 py-2.5 uppercase tracking-widest text-[10px]">Totaux</td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($etat['base']) }}</td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">{{ $fcfa($etat['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-secondary/15">
        <h2 class="text-sm font-semibold text-primary">Détail par bénéficiaire</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Date</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Facture</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Fournisseur</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Nature</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Base HT</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Taux</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Retenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($etat['lines'] as $facture)
                    <tr class="hover:bg-accent/10">
                        <td class="px-4 py-2 whitespace-nowrap text-primary/70">{{ $facture->invoice_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            <a href="{{ route('accounting.ledger.suppliers.show', $facture) }}"
                               class="font-mono text-primary hover:underline">{{ $facture->number }}</a>
                        </td>
                        <td class="px-3 py-2 text-primary">{{ $facture->supplier?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-primary/60">{{ $facture->withholdingLabel() }}</td>
                        <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $fcfa($facture->amount_ht) }}</td>
                        <td class="px-3 py-2 text-right text-primary/60">{{ rtrim(rtrim(number_format($facture->withholdingRate(), 2, ',', ''), '0'), ',') }} %</td>
                        <td class="px-4 py-2 text-right font-semibold text-primary whitespace-nowrap">{{ $fcfa($facture->withholding_amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-primary/50">
                            <i data-lucide="receipt" class="w-8 h-8 mx-auto mb-3 text-primary/20"></i>
                            Aucune retenue sur cette période.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
