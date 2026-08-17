@extends('layouts.hotel')

@section('title', 'Balance générale')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Balance générale</h1>
    <p class="text-sm text-primary/60 mt-1">{{ $periode['label'] }}</p>
</div>

@include('accounting.ledger.partials.nav')

{{-- Filtre par classe --}}
<div class="flex flex-wrap gap-1.5 mb-4">
    <a href="{{ route('accounting.ledger.balance') }}"
       class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $classe === null ? 'bg-primary text-white' : 'bg-white border border-secondary/25 text-primary/60 hover:text-primary' }}">
        Toutes les classes
    </a>
    @foreach(\App\Models\Account::CLASS_LABELS as $num => $libelle)
        <a href="{{ route('accounting.ledger.balance', ['classe' => $num]) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $classe === $num ? 'bg-primary text-white' : 'bg-white border border-secondary/25 text-primary/60 hover:text-primary' }}"
           title="{{ $libelle }}">
            {{ $num }}
        </a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Compte</th>
                    <th class="px-3 py-2.5 text-left font-semibold">Intitulé</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Débit</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Crédit</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Solde</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($balance as $ligne)
                    <tr class="hover:bg-accent/10">
                        <td class="px-4 py-2 font-mono text-primary whitespace-nowrap">
                            <a href="{{ route('accounting.ledger.general', ['compte' => $ligne['code']]) }}" class="hover:underline">
                                {{ $ligne['code'] }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-primary/75">{{ $ligne['label'] }}</td>
                        <td class="px-3 py-2 text-right text-primary/70 whitespace-nowrap">{{ $ligne['debit'] ? $fcfa($ligne['debit']) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-primary/70 whitespace-nowrap">{{ $ligne['credit'] ? $fcfa($ligne['credit']) : '—' }}</td>
                        <td class="px-4 py-2 text-right font-semibold whitespace-nowrap {{ $ligne['balance'] >= 0 ? 'text-primary' : 'text-secondary' }}">
                            {{ $fcfa(abs($ligne['balance'])) }}
                            <span class="text-[10px] font-normal text-primary/40">{{ $ligne['balance'] >= 0 ? 'D' : 'C' }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-primary/50">
                            Aucun compte mouvementé sur cette période.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if($balance->isNotEmpty())
                <tfoot class="bg-accent/20 font-semibold text-primary">
                    <tr>
                        <td colspan="2" class="px-4 py-2.5 text-right uppercase tracking-widest text-[10px]">Totaux</td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($totaux['debit']) }}</td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($totaux['credit']) }}</td>
                        <td class="px-4 py-2.5 text-right whitespace-nowrap">
                            @if($totaux['balanced'])
                                <span class="inline-flex items-center gap-1 text-green-700">
                                    <i data-lucide="check" class="w-3.5 h-3.5"></i> équilibrée
                                </span>
                            @else
                                <span class="text-red-600">écart {{ $fcfa(abs($totaux['debit'] - $totaux['credit'])) }}</span>
                            @endif
                        </td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

@if($classe !== null && $balance->isNotEmpty())
    <p class="mt-3 text-[11px] text-primary/45">
        Balance filtrée sur la classe {{ $classe }} : les totaux ci-dessus ne portent que sur les comptes affichés
        et ne s'équilibrent donc pas nécessairement.
    </p>
@endif
@endsection
