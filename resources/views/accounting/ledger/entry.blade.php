@extends('layouts.hotel')

@section('title', 'Écriture ' . $entry->id)

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<a href="{{ route('accounting.ledger.journals') }}" class="text-xs text-primary/50 hover:text-primary flex items-center gap-1 mb-4">
    <i data-lucide="arrow-left" class="w-3 h-3"></i> Retour aux journaux
</a>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-4">
    <div class="px-5 py-4 border-b border-secondary/15 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-lg font-semibold text-primary font-heading">{{ $entry->label }}</h1>
            <p class="text-xs text-primary/55 mt-1">
                {{ $entry->entry_date->format('d/m/Y') }}
                · journal <span class="font-mono">{{ $entry->journal?->code }}</span>
                @if($entry->reference) · pièce <span class="font-mono">{{ $entry->reference }}</span> @endif
                · {{ $entry->period?->label() }}
            </p>
        </div>
        <div class="flex flex-wrap gap-1.5 shrink-0">
            @if($entry->isReversed())
                <span class="inline-flex items-center px-2 py-1 rounded-lg bg-red-50 text-red-700 text-[11px] font-semibold">Extournée</span>
            @elseif($entry->isReversal())
                <span class="inline-flex items-center px-2 py-1 rounded-lg bg-amber-50 text-amber-700 text-[11px] font-semibold">Contre-passation</span>
            @endif
            @if($entry->period?->isLocked())
                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-accent/40 text-primary/70 text-[11px] font-semibold">
                    <i data-lucide="lock" class="w-3 h-3"></i> Période verrouillée
                </span>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-5 py-2.5 text-left font-semibold">Compte</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Libellé</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Tiers</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Centre</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Débit</th>
                    <th class="px-5 py-2.5 text-right font-semibold">Crédit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @foreach($entry->lines as $l)
                    <tr class="hover:bg-accent/10">
                        <td class="px-5 py-2 font-mono text-primary whitespace-nowrap">
                            <a href="{{ route('accounting.ledger.general', ['compte' => $l->account_code]) }}" class="hover:underline">{{ $l->account_code }}</a>
                        </td>
                        <td class="px-3 py-2 text-primary/70">{{ $l->label ?: '—' }}</td>
                        <td class="px-3 py-2 text-primary/60">{{ $l->auxiliary?->full_name ?? $l->auxiliary?->name ?? '—' }}</td>
                        <td class="px-3 py-2 text-primary/60">
                            {{ $l->analytic_center ? (\App\Models\JournalEntryLine::CENTERS[$l->analytic_center] ?? $l->analytic_center) : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right text-primary/80 whitespace-nowrap">{{ $l->debit ? $fcfa($l->debit) : '' }}</td>
                        <td class="px-5 py-2 text-right text-primary/80 whitespace-nowrap">{{ $l->credit ? $fcfa($l->credit) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-accent/20 font-semibold text-primary">
                <tr>
                    <td colspan="4" class="px-5 py-2.5 text-right uppercase tracking-widest text-[10px]">Totaux</td>
                    <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($entry->totalDebit()) }}</td>
                    <td class="px-5 py-2.5 text-right whitespace-nowrap">{{ $fcfa($entry->totalCredit()) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

{{-- Chaînage des extournes --}}
@if($entry->reverses || $entry->reversedBy)
    <div class="mb-4 rounded-xl border border-secondary/20 bg-white p-4 text-xs text-primary/70">
        @if($entry->reverses)
            Cette écriture contre-passe
            <a href="{{ route('accounting.ledger.entry', $entry->reverses) }}" class="font-semibold text-primary hover:underline">« {{ $entry->reverses->label }} »</a>
            du {{ $entry->reverses->entry_date->format('d/m/Y') }}.
        @endif
        @if($entry->reversedBy)
            Cette écriture a été contre-passée par
            <a href="{{ route('accounting.ledger.entry', $entry->reversedBy) }}" class="font-semibold text-primary hover:underline">« {{ $entry->reversedBy->label }} »</a>
            du {{ $entry->reversedBy->entry_date->format('d/m/Y') }}.
        @endif
    </div>
@endif

{{-- Contre-passation --}}
@unless($entry->isReversed() || $entry->isReversal())
    <div class="rounded-xl border border-secondary/20 bg-white p-4">
        <h2 class="text-sm font-semibold text-primary mb-1">Contre-passer cette écriture</h2>
        <p class="text-xs text-primary/60 mb-3">
            Une écriture validée ne se modifie ni ne se supprime. La correction produit une écriture inverse,
            datée d'aujourd'hui — antidater rouvrirait une période close.
        </p>
        <form method="POST" action="{{ route('accounting.ledger.entry.reverse', $entry) }}" class="flex flex-col sm:flex-row gap-2">
            @csrf
            <input type="text" name="reason" required minlength="5" maxlength="255"
                   placeholder="Motif de la correction (obligatoire)"
                   class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
            <button type="submit" class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shrink-0">
                Contre-passer
            </button>
        </form>
        @error('reason')<p class="text-xs text-red-500 mt-1.5">{{ $message }}</p>@enderror
    </div>
@endunless
@endsection
