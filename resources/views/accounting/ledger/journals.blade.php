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

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Date</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Jnl</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Pièce</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Libellé</th>
                    <th class="px-3 py-2.5 text-center font-semibold">Lignes</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Montant</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($ecritures as $e)
                    <tr class="hover:bg-accent/10 {{ $e->isReversed() ? 'opacity-60' : '' }}">
                        <td class="px-4 py-2 whitespace-nowrap text-primary/70">{{ $e->entry_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex px-1.5 py-0.5 rounded bg-accent/40 text-primary text-[10px] font-semibold">{{ $e->journal?->code }}</span>
                        </td>
                        <td class="px-3 py-2 font-mono text-primary/60">{{ $e->reference ?: '—' }}</td>
                        <td class="px-3 py-2 text-primary">
                            <a href="{{ route('accounting.ledger.entry', $e) }}" class="hover:underline">{{ $e->label }}</a>
                            @if($e->isReversed())
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-red-50 text-red-700 text-[10px] font-semibold">extournée</span>
                            @elseif($e->isReversal())
                                <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-semibold">extourne</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center text-primary/50">{{ $e->lines->count() }}</td>
                        <td class="px-4 py-2 text-right font-medium text-primary whitespace-nowrap">{{ $fcfa($e->totalDebit()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-primary/50">Aucune écriture sur cette période.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($ecritures->hasPages())
    <div class="mt-4">{{ $ecritures->links() }}</div>
@endif
@endsection
