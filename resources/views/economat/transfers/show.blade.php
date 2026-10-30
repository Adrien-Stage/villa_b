@extends('layouts.hotel')

@section('title', 'Bon ' . $transfert->number . ' — Transfert vers un dépôt')

@section('content')
@include('economat.external_issues.partials.police-signature')
@php
    $fcfa = fn (int $v) => number_format($v / 100, 0, ',', ' ');
    $qte = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
@endphp

<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('economat.stores.show', $transfert->depot) }}" class="inline-flex items-center gap-1.5 text-sm text-primary/60 hover:text-primary">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Dépôt {{ $transfert->depot?->name }}
        </a>
        <a href="{{ route('economat.transfers.print', $transfert) }}" target="_blank"
           class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-white border border-secondary/30 rounded-lg text-primary text-xs font-semibold hover:bg-gray-50 shadow-sm">
            <i data-lucide="printer" class="w-4 h-4"></i> Imprimer le bon de transfert
        </a>
    </div>

    @include('economat.partials.flash')

    <div class="bg-white border border-secondary/20 rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-heading font-bold text-primary font-mono">{{ $transfert->number }}</h1>
                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold {{ $transfert->isCancelled() ? 'border-gray-200 bg-gray-100 text-gray-600' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">{{ $transfert->statusLabel() }}</span>
                </div>
                <p class="text-sm font-semibold text-primary/80 mt-1">Magasin central → dépôt {{ $transfert->depot?->name ?? '—' }}</p>
                <p class="text-xs text-primary/50 mt-0.5">
                    Transféré le {{ $transfert->transferred_at->format('d/m/Y à H:i') }} par <strong>{{ $transfert->transferredBy?->name ?? '—' }}</strong>
                </p>
                @if($transfert->notes)
                    <p class="mt-3 rounded-lg border border-secondary/15 bg-surface-light px-3 py-2 text-xs text-primary/80">{{ $transfert->notes }}</p>
                @endif
            </div>
            <div class="text-right sm:border-l sm:border-secondary/15 sm:pl-6">
                <div class="text-xs uppercase tracking-wider text-primary/50 font-semibold">Valeur transférée</div>
                <div class="text-2xl font-mono font-bold text-primary mt-0.5">{{ $fcfa((int) $transfert->total_value) }} <span class="text-sm font-sans font-normal text-primary/60">FCFA</span></div>
                <div class="text-[11px] text-primary/45">au coût moyen du magasin central</div>
            </div>
        </div>

        @if($transfert->isCancelled())
            <div class="mt-4 pt-3 border-t border-secondary/15 text-xs text-primary/70">
                Annulé par <strong>{{ $transfert->cancelledBy?->name ?? '—' }}</strong> le {{ $transfert->cancelled_at?->format('d/m/Y à H:i') }}
                — <em>« {{ $transfert->cancellation_reason }} »</em>. La marchandise est revenue au magasin central.
            </div>
        @endif
    </div>

    <div class="bg-white border border-secondary/20 rounded-xl overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-secondary/15 bg-gray-50/70">
            <h2 class="text-sm font-semibold text-primary">Articles transférés ({{ $transfert->lines->count() }})</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-[11px] font-semibold uppercase text-primary/50 border-b border-secondary/10">
                    <tr>
                        <th class="px-4 py-2.5 text-left">Article</th>
                        <th class="px-3 py-2.5 text-center">Unité</th>
                        <th class="px-4 py-2.5 text-right">Quantité</th>
                        <th class="px-4 py-2.5 text-right">Coût unitaire</th>
                        <th class="px-4 py-2.5 text-right">Valeur</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($transfert->lines as $ligne)
                        <tr>
                            <td class="px-4 py-2.5 font-medium text-primary">{{ $ligne->item?->name ?? '—' }}</td>
                            <td class="px-3 py-2.5 text-center font-mono font-bold text-xs text-primary/70">{{ $ligne->item?->shortUnit() ?? $ligne->item?->unit }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">
                                {{ $qte($ligne->quantity) }}
                                @if($ligne->packaging_name)<span class="block text-[10px] font-sans text-primary/55">{{ $ligne->enConditionnement((float) $ligne->quantity) }}</span>@endif
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-primary/70">{{ $fcfa((int) $ligne->unit_cost) }} F</td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold">{{ $fcfa((int) $ligne->total_cost) }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if(!$transfert->isCancelled())
        @droit('economat.transfers.cancel')
            <details class="bg-white border border-red-200 rounded-xl p-4">
                <summary class="cursor-pointer text-xs font-semibold text-red-700">Annuler ce transfert (la marchandise revient au magasin central)</summary>
                <form method="POST" action="{{ route('economat.transfers.cancel', $transfert) }}" class="mt-3 space-y-2">
                    @csrf
                    <p class="text-[11px] text-primary/55">Le dépôt doit encore avoir toute la marchandise transférée : ce qu'il a consommé ne revient pas.</p>
                    <label for="motif-annulation" class="block text-xs text-primary/70">Motif de l'annulation</label>
                    <textarea id="motif-annulation" name="cancellation_reason" rows="2" required maxlength="500"
                              class="w-full px-3 py-2 text-xs border border-secondary/30 rounded-lg text-primary outline-none focus:border-primary">{{ old('cancellation_reason') }}</textarea>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700">Confirmer l'annulation</button>
                </form>
            </details>
        @enddroit
    @endif
</div>
@endsection
