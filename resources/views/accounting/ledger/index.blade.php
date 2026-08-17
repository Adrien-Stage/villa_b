@extends('layouts.hotel')

@section('title', 'Comptabilité générale')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ') . ' FCFA';
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Comptabilité générale</h1>
    <p class="text-sm text-primary/60 mt-1">SYSCOHADA révisé — {{ $periode['label'] }}</p>
</div>

@include('accounting.ledger.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

@if(!$exercice)
    <div class="bg-white rounded-xl border border-secondary/20 p-6 text-center">
        <i data-lucide="calendar-plus" class="w-8 h-8 mx-auto mb-3 text-primary/30"></i>
        <p class="text-sm text-primary/70 mb-1">Aucun exercice comptable n'est ouvert.</p>
        <p class="text-xs text-primary/50 mb-4">L'exercice s'ouvrira automatiquement à la première écriture, ou vous pouvez l'ouvrir dès maintenant.</p>
        <a href="{{ route('accounting.ledger.periods') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
            Ouvrir un exercice
        </a>
    </div>
@else

    {{-- Contrôle de cohérence : sur un grand livre sain, débits = crédits. --}}
    <div class="mb-6 rounded-xl border px-4 py-3 flex flex-wrap items-center gap-3
        {{ $totaux['balanced'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }}">
        <i data-lucide="{{ $totaux['balanced'] ? 'check-circle-2' : 'alert-triangle' }}"
           class="w-5 h-5 shrink-0 {{ $totaux['balanced'] ? 'text-green-600' : 'text-red-600' }}"></i>
        <div class="min-w-0">
            <p class="text-sm font-semibold {{ $totaux['balanced'] ? 'text-green-800' : 'text-red-800' }}">
                {{ $totaux['balanced'] ? 'Grand livre équilibré' : 'Grand livre déséquilibré' }}
            </p>
            <p class="text-xs {{ $totaux['balanced'] ? 'text-green-700/80' : 'text-red-700/80' }}">
                {{ $fcfa($totaux['debit']) }} au débit — {{ $fcfa($totaux['credit']) }} au crédit
                @unless($totaux['balanced'])
                    · écart de {{ $fcfa(abs($totaux['debit'] - $totaux['credit'])) }}
                @endunless
            </p>
        </div>
    </div>

    {{-- Périodes dont le délai de verrouillage est dépassé (Article 22). --}}
    @if($enRetard->isNotEmpty())
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
            <p class="text-sm font-semibold text-amber-800 flex items-center gap-2">
                <i data-lucide="clock-alert" class="w-4 h-4"></i>
                {{ $enRetard->count() }} période{{ $enRetard->count() > 1 ? 's' : '' }} à verrouiller
            </p>
            <p class="text-xs text-amber-700/85 mt-1">
                L'Article 22 de l'Acte Uniforme impose de rendre les écritures irréversibles au plus tard un mois
                après la fin de la période :
                {{ $enRetard->map(fn ($p) => $p->label())->implode(', ') }}.
            </p>
            <a href="{{ route('accounting.ledger.periods') }}" class="inline-flex items-center gap-1.5 mt-2 text-xs font-semibold text-amber-900 hover:underline">
                Gérer les périodes <i data-lucide="arrow-right" class="w-3 h-3"></i>
            </a>
        </div>
    @endif

    {{-- Soldes par classe --}}
    @php
        $parClasse = $balance->groupBy('class');
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        @forelse($parClasse as $classe => $lignes)
            <div class="bg-white rounded-xl border border-secondary/20 p-4 shadow-sm">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">
                    Classe {{ $classe }} — {{ \App\Models\Account::CLASS_LABELS[$classe] ?? '' }}
                </p>
                <p class="text-xl font-heading font-bold text-primary mt-1">
                    {{ number_format(abs($lignes->sum('balance')) / 100, 0, ',', ' ') }}
                    <span class="text-xs font-normal text-primary/50">FCFA</span>
                </p>
                <p class="text-[11px] text-primary/45 mt-0.5">{{ $lignes->count() }} compte{{ $lignes->count() > 1 ? 's' : '' }} mouvementé{{ $lignes->count() > 1 ? 's' : '' }}</p>
            </div>
        @empty
            <div class="sm:col-span-2 lg:col-span-3 bg-white rounded-xl border border-secondary/20 p-6 text-center">
                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-3 text-primary/25"></i>
                <p class="text-sm text-primary/70">Aucune écriture sur cet exercice.</p>
                <p class="text-xs text-primary/50 mt-1">
                    Commencez par reprendre les à-nouveaux de l'exercice précédent.
                </p>
                <a href="{{ route('accounting.ledger.opening') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors">
                    <i data-lucide="import" class="w-3.5 h-3.5"></i> Reprendre les à-nouveaux
                </a>
            </div>
        @endforelse
    </div>

    {{-- Dernières écritures --}}
    @if($dernieres->isNotEmpty())
        <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-secondary/15 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-primary">Dernières écritures</h2>
                <a href="{{ route('accounting.ledger.journals') }}" class="text-xs text-primary/50 hover:text-primary">Tout voir</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                        <tr>
                            <th class="px-5 py-2 text-left font-semibold">Date</th>
                            <th class="px-3 py-2 text-left font-semibold">Journal</th>
                            <th class="px-3 py-2 text-left font-semibold">Libellé</th>
                            <th class="px-5 py-2 text-right font-semibold">Montant</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        @foreach($dernieres as $e)
                            <tr class="hover:bg-accent/10">
                                <td class="px-5 py-2 whitespace-nowrap text-primary/70">{{ $e->entry_date->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex px-1.5 py-0.5 rounded bg-accent/40 text-primary text-[10px] font-semibold">{{ $e->journal?->code }}</span>
                                </td>
                                <td class="px-3 py-2 text-primary">
                                    <a href="{{ route('accounting.ledger.entry', $e) }}" class="hover:underline">{{ $e->label }}</a>
                                    @if($e->isReversed())
                                        <span class="ml-1 text-[10px] text-red-600">extournée</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2 text-right font-medium text-primary whitespace-nowrap">{{ $fcfa($e->totalDebit()) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection
