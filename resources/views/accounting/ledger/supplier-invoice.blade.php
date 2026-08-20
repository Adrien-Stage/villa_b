@extends('layouts.hotel')

@section('title', 'Facture ' . $invoice->number)

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading font-mono">{{ $invoice->number }}</h1>
    <p class="text-sm text-primary/60 mt-1">{{ $invoice->supplier?->name }} — {{ $invoice->invoice_date->format('d/m/Y') }}</p>
</div>

@include('accounting.ledger.partials.nav')

<a href="{{ route('accounting.ledger.suppliers') }}"
   class="inline-flex items-center gap-1.5 mb-4 text-xs font-medium text-primary/60 hover:text-primary transition-colors">
    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
    Retour aux factures
</a>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 space-y-4">
        <div class="bg-white rounded-xl border border-secondary/20 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-primary mb-3">Document</h2>
            <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3 text-xs">
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">Fournisseur</dt>
                    <dd class="text-primary mt-0.5">{{ $invoice->supplier?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">Bon de commande</dt>
                    <dd class="text-primary mt-0.5 font-mono">{{ $invoice->purchaseOrder?->number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">Échéance</dt>
                    <dd class="text-primary mt-0.5">{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">Imputation</dt>
                    <dd class="text-primary mt-0.5">{{ $invoice->charge_account }} — {{ $invoice->chargeLabel() }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-[10px] uppercase tracking-widest text-primary/40">Libellé</dt>
                    <dd class="text-primary mt-0.5">{{ $invoice->label }}</dd>
                </div>
                @if($invoice->notes)
                    <div class="sm:col-span-2">
                        <dt class="text-[10px] uppercase tracking-widest text-primary/40">Notes</dt>
                        <dd class="text-primary/70 mt-0.5 whitespace-pre-line">{{ $invoice->notes }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if($ecriture)
            <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-secondary/15 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-primary">Écriture comptable</h2>
                    <a href="{{ route('accounting.ledger.entry', $ecriture) }}"
                       class="text-xs text-primary/60 hover:text-primary hover:underline">Voir au journal</a>
                </div>
                <table class="min-w-full text-xs">
                    <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold">Compte</th>
                            <th class="px-3 py-2 text-left font-semibold">Libellé</th>
                            <th class="px-3 py-2 text-right font-semibold">Débit</th>
                            <th class="px-4 py-2 text-right font-semibold">Crédit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($ecriture->lines as $ligne)
                            <tr>
                                <td class="px-4 py-2 font-mono text-primary">{{ $ligne->account_code }}</td>
                                <td class="px-3 py-2 text-primary/70">{{ $ligne->label }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $ligne->debit ? $fcfa($ligne->debit) : '' }}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">{{ $ligne->credit ? $fcfa($ligne->credit) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                Cette facture n'a pas d'écriture rattachée.
            </div>
        @endif
    </div>

    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-secondary/20 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-primary mb-3">Montants</h2>
            <dl class="space-y-2 text-xs">
                <div class="flex justify-between">
                    <dt class="text-primary/60">Base hors taxes</dt>
                    <dd class="font-mono text-primary">{{ $fcfa($invoice->amount_ht) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-primary/60">TVA récupérable</dt>
                    <dd class="font-mono text-primary">{{ $fcfa($invoice->amount_vat) }}</dd>
                </div>
                <div class="flex justify-between pt-2 border-t border-secondary/15">
                    <dt class="text-primary/70 font-medium">Total TTC</dt>
                    <dd class="font-mono font-semibold text-primary">{{ $fcfa($invoice->amount_ttc) }}</dd>
                </div>
                @if($invoice->hasWithholding())
                    <div class="flex justify-between">
                        <dt class="text-amber-700">
                            Retenue {{ $invoice->withholdingLabel() }}
                            <span class="block text-[10px] text-amber-700/70">{{ rtrim(rtrim(number_format($invoice->withholdingRate(), 2, ',', ''), '0'), ',') }} % du hors taxes</span>
                        </dt>
                        <dd class="font-mono text-amber-700">− {{ $fcfa($invoice->withholding_amount) }}</dd>
                    </div>
                @endif
                <div class="flex justify-between pt-2 border-t border-secondary/15">
                    <dt class="text-primary font-semibold">Net à payer</dt>
                    <dd class="font-mono font-bold text-primary">{{ $fcfa($invoice->net_payable) }}</dd>
                </div>
            </dl>

            @if($invoice->hasWithholding())
                <p class="text-[11px] text-primary/50 mt-4 pt-4 border-t border-secondary/15 leading-relaxed">
                    La retenue est due à l'État, pas gagnée : elle figure au crédit du compte 442100
                    jusqu'à son reversement.
                </p>
            @endif
        </div>
    </div>
</div>
@endsection
