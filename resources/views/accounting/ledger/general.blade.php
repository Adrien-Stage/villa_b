@extends('layouts.hotel')

@section('title', 'Grand livre')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Grand livre</h1>
    <p class="text-sm text-primary/60 mt-1">{{ $periode['label'] }}</p>
</div>

@include('accounting.ledger.partials.nav')

@if($comptes->isEmpty())
    <div class="bg-white rounded-xl border border-secondary/20 p-8 text-center">
        <i data-lucide="book-open" class="w-8 h-8 mx-auto mb-3 text-primary/25"></i>
        <p class="text-sm text-primary/70">Aucun compte mouvementé sur cette période.</p>
    </div>
@else
    <form method="GET" class="mb-4">
        <label class="block text-xs font-medium text-primary/70 mb-1.5">Compte à consulter</label>
        <div class="flex flex-col sm:flex-row gap-2">
            <select name="compte" onchange="this.form.submit()"
                    class="w-full sm:max-w-md rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                @foreach($comptes as $c)
                    <option value="{{ $c->code }}" @selected($compte && $compte->code === $c->code)>
                        {{ $c->code }} — {{ $c->label }}
                    </option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shrink-0">
                Afficher
            </button>
        </div>
    </form>

    @if($compte && $detail)
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Solde d'entrée</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa(abs($detail['opening'])) }}
                    <span class="text-[10px] font-normal text-primary/40">{{ $detail['opening'] >= 0 ? 'D' : 'C' }}</span>
                </p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Total débit</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($detail['debit']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Total crédit</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($detail['credit']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Solde de sortie</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa(abs($detail['closing'])) }}
                    <span class="text-[10px] font-normal text-primary/40">{{ $detail['closing'] >= 0 ? 'D' : 'C' }}</span>
                </p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-secondary/15">
                <h2 class="text-sm font-semibold text-primary font-mono">{{ $compte->code }}</h2>
                <p class="text-xs text-primary/55">{{ $compte->label }}
                    @if($compte->is_collective)
                        <span class="ml-1 inline-flex px-1.5 py-0.5 rounded bg-accent/40 text-[10px] font-semibold">collectif</span>
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                        <tr>
                            <th class="px-4 py-2.5 text-left font-semibold">Date</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Jnl</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Libellé</th>
                            <th class="px-3 py-2.5 text-left font-semibold">Tiers</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Débit</th>
                            <th class="px-3 py-2.5 text-right font-semibold">Crédit</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Solde</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-secondary/10">
                        <tr class="bg-accent/10 text-primary/60">
                            <td colspan="6" class="px-4 py-1.5 text-right italic">Solde d'entrée</td>
                            <td class="px-4 py-1.5 text-right font-semibold whitespace-nowrap">{{ $fcfa(abs($detail['opening'])) }}</td>
                        </tr>
                        @forelse($detail['lines'] as $l)
                            <tr class="hover:bg-accent/10">
                                <td class="px-4 py-2 whitespace-nowrap text-primary/70">{{ $l['date']->format('d/m/Y') }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex px-1.5 py-0.5 rounded bg-accent/40 text-primary text-[10px] font-semibold">{{ $l['journal'] }}</span>
                                </td>
                                <td class="px-3 py-2 text-primary">
                                    <a href="{{ route('accounting.ledger.entry', $l['entry_id']) }}" class="hover:underline">{{ $l['label'] }}</a>
                                </td>
                                <td class="px-3 py-2 text-primary/60">
                                    {{ $l['auxiliary']->full_name ?? $l['auxiliary']->name ?? '—' }}
                                </td>
                                <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $l['debit'] ? $fcfa($l['debit']) : '' }}</td>
                                <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $l['credit'] ? $fcfa($l['credit']) : '' }}</td>
                                <td class="px-4 py-2 text-right font-medium text-primary whitespace-nowrap">{{ $fcfa(abs($l['balance'])) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-primary/50">Aucun mouvement sur la période.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-accent/20 font-semibold text-primary">
                        <tr>
                            <td colspan="4" class="px-4 py-2.5 text-right uppercase tracking-widest text-[10px]">Totaux</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($detail['debit']) }}</td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">{{ $fcfa($detail['credit']) }}</td>
                            <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                {{ $fcfa(abs($detail['closing'])) }}
                                <span class="text-[10px] font-normal text-primary/50">{{ $detail['closing'] >= 0 ? 'D' : 'C' }}</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection
