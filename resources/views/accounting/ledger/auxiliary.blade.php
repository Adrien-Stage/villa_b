@extends('layouts.hotel')

@section('title', 'Comptabilité auxiliaire')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Comptes de tiers</h1>
    <p class="text-sm text-primary/60 mt-1">Le détail derrière les comptes collectifs</p>
</div>

@include('accounting.ledger.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

<div class="px-4 py-3 mb-5 rounded-xl bg-accent/20 border border-secondary/20 text-[11px] text-primary/70 leading-relaxed">
    <strong>Un compte, plusieurs tiers.</strong> Le plan SYSCOHADA ne prévoit pas un compte par client :
    tous passent par le collectif 411000, et c'est l'auxiliaire porté sur la ligne qui dit lequel.
    <span class="block mt-1">
        Le <strong>lettrage</strong> rapproche un règlement de ce qu'il solde. Ce qui reste non lettré est,
        par définition, ce qui reste dû — c'est ce qui alimente la balance âgée.
    </span>
</div>

@if($collectifs->isEmpty())
    <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center">
        <i data-lucide="users" class="w-8 h-8 mx-auto mb-3 text-primary/25"></i>
        <p class="text-sm text-primary/70">Aucun compte collectif dans le plan de comptes.</p>
    </div>
@else
    <div class="flex flex-col sm:flex-row sm:items-end gap-3 mb-4">
        <form method="GET" class="flex-1 flex flex-col sm:flex-row gap-2">
            <div class="flex-1">
                <label class="block text-xs font-medium text-primary/70 mb-1.5">Compte collectif</label>
                <select name="compte" onchange="this.form.submit()"
                        class="w-full sm:max-w-md rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                    @foreach($collectifs as $c)
                        <option value="{{ $c->code }}" @selected($compte && $compte->code === $c->code)>
                            {{ $c->code }} — {{ $c->label }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(!$ouverts)<input type="hidden" name="tout" value="1">@endif
        </form>

        <div class="flex gap-2">
            <a href="{{ route('accounting.ledger.auxiliary', ['compte' => $compte?->code, 'tout' => $ouverts ? 1 : null]) }}"
               class="px-3 py-2.5 rounded-lg border border-secondary/25 bg-white text-xs font-semibold text-primary/70 hover:bg-accent/20 transition-colors whitespace-nowrap">
                {{ $ouverts ? 'Voir tout' : 'Postes ouverts' }}
            </a>

            @if($compte)
                <form method="POST" action="{{ route('accounting.ledger.reconcile.auto') }}"
                      onsubmit="return confirm('Lancer le lettrage automatique sur {{ $compte->code }} ?')">
                    @csrf
                    <input type="hidden" name="compte" value="{{ $compte->code }}">
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors whitespace-nowrap">
                        <i data-lucide="wand-sparkles" class="w-3.5 h-3.5"></i>
                        Lettrage auto
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/15 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-primary font-mono">{{ $compte?->code }}</h2>
                <p class="text-xs text-primary/55">{{ $compte?->label }}</p>
            </div>
            <span class="text-[10px] uppercase tracking-widest text-primary/40 shrink-0">
                {{ $ouverts ? 'Postes ouverts' : 'Tous mouvements' }}
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                    <tr>
                        <th class="px-4 py-2.5 text-left font-semibold">Tiers</th>
                        <th class="px-3 py-2.5 text-left font-semibold">Plus ancien</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Lignes</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Débit</th>
                        <th class="px-3 py-2.5 text-right font-semibold">Crédit</th>
                        <th class="px-4 py-2.5 text-right font-semibold">Solde</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @forelse($tiers as $t)
                        <tr class="hover:bg-accent/10">
                            <td class="px-4 py-2 text-primary">
                                <a href="{{ route('accounting.ledger.auxiliary.ledger', ['compte' => $compte->code, 'type' => $t['auxiliary_type'], 'id' => $t['auxiliary_id']]) }}"
                                   class="font-medium hover:underline">{{ $t['label'] }}</a>
                                <span class="block text-[10px] text-primary/40">{{ class_basename($t['auxiliary_type']) }}</span>
                            </td>
                            <td class="px-3 py-2 text-primary/60 whitespace-nowrap">
                                {{ $t['oldest']?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-primary/60">{{ $t['lines'] }}</td>
                            <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $t['debit'] ? $fcfa($t['debit']) : '' }}</td>
                            <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $t['credit'] ? $fcfa($t['credit']) : '' }}</td>
                            <td class="px-4 py-2 text-right font-semibold text-primary whitespace-nowrap">
                                {{ $fcfa(abs($t['balance'])) }}
                                <span class="text-[10px] font-normal text-primary/40">{{ $t['balance'] >= 0 ? 'D' : 'C' }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-primary/50">
                                {{ $ouverts ? 'Aucun poste ouvert : tous les tiers sont lettrés.' : 'Aucun mouvement sur ce compte.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($tiers->isNotEmpty())
                    <tfoot class="bg-accent/20 font-semibold text-primary">
                        <tr>
                            <td colspan="3" class="px-4 py-2.5 text-right uppercase tracking-widest text-[10px]">Totaux</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($totaux['debit']) }}</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($totaux['credit']) }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                {{ $fcfa(abs($totaux['balance'])) }}
                                <span class="text-[10px] font-normal text-primary/50">{{ $totaux['balance'] >= 0 ? 'D' : 'C' }}</span>
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endif
@endsection
