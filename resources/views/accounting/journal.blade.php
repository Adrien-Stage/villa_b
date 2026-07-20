@extends('layouts.hotel')

@section('title', 'Cahier recettes & dépenses')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
    $sourceMeta = [
        'hebergement' => ['Hébergement', 'bg-blue-50 text-blue-700 border-blue-200'],
        'restaurant'  => ['Restauration', 'bg-amber-50 text-amber-700 border-amber-200'],
        'boutique'    => ['Boutique', 'bg-violet-50 text-violet-700 border-violet-200'],
        'depense'     => ['Dépense', 'bg-red-50 text-red-600 border-red-200'],
    ];
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Cahier des recettes &amp; dépenses</h1>
        <p class="text-sm text-primary/60 mt-1">Le journal chronologique de tous les mouvements d'argent de la période.</p>
    </div>
    @include('accounting.partials.period')
</div>

@include('accounting.partials.nav')

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-secondary/10">
            <thead class="bg-accent/20">
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">N°</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Date</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Libellé</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Recette</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Dépense</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Solde</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @php $solde = 0; @endphp
                @forelse($entries as $i => $e)
                    @php $solde += $e['recette'] - $e['depense']; [$srcLabel, $srcChip] = $sourceMeta[$e['source']] ?? ['—', 'bg-gray-100 text-gray-600 border-gray-200']; @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5 text-xs text-primary/40 tabular-nums">{{ $i + 1 }}</td>
                        <td class="px-4 py-2.5 text-sm text-primary/70 whitespace-nowrap">{{ $e['date']->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold border {{ $srcChip }} mr-2">{{ $srcLabel }}</span>
                            <span class="text-sm text-primary">{{ $e['libelle'] }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right text-sm font-medium text-green-700 tabular-nums">{{ $e['recette'] ? $fcfa($e['recette']) : '' }}</td>
                        <td class="px-4 py-2.5 text-right text-sm font-medium text-red-600 tabular-nums">{{ $e['depense'] ? $fcfa($e['depense']) : '' }}</td>
                        <td class="px-4 py-2.5 text-right text-sm font-semibold {{ $solde < 0 ? 'text-red-600' : 'text-primary' }} tabular-nums">{{ $fcfa($solde) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-primary/40">
                        <i data-lucide="book-open" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
                        <p class="text-sm">Aucun mouvement sur cette période.</p>
                    </td></tr>
                @endforelse
            </tbody>
            @if($entries->isNotEmpty())
                <tfoot class="bg-gray-50/70 border-t-2 border-secondary/15">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-sm font-semibold text-primary">Totaux — {{ ucfirst($period['label']) }}</td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-green-700 tabular-nums">{{ $fcfa($totalRecettes) }}</td>
                        <td class="px-4 py-3 text-right text-sm font-bold text-red-600 tabular-nums">{{ $fcfa($totalDepenses) }}</td>
                        <td class="px-4 py-3 text-right text-sm font-bold {{ ($totalRecettes - $totalDepenses) < 0 ? 'text-red-600' : 'text-primary' }} tabular-nums">{{ $fcfa($totalRecettes - $totalDepenses) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
