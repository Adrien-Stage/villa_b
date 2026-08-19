@extends('layouts.hotel')

@section('title', 'Marges par type de chambre')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Marges par type de chambre</h1>
    <p class="text-sm text-primary/60 mt-1">{{ $periode['label'] }}</p>
</div>

@include('accounting.ledger.partials.nav')

<a href="{{ route('accounting.ledger.analytic', ['from' => $periode['from']->toDateString(), 'to' => $periode['to']->toDateString()]) }}"
   class="inline-flex items-center gap-1.5 mb-4 text-xs font-medium text-primary/60 hover:text-primary transition-colors">
    <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
    Retour à l'analytique
</a>

<div class="px-4 py-3 mb-5 rounded-xl bg-accent/20 border border-secondary/20 text-[11px] text-primary/70 leading-relaxed">
    <strong>Marge de contribution.</strong> Ce que chaque nuitée laisse une fois payé ce qu'elle a
    coûté à servir — linge, produits d'accueil, ménage. Elle ne couvre pas le loyer ni les salaires
    fixes : elle dit ce qui reste pour les payer.
    <span class="block mt-1">
        Ces chiffres viennent des fiches de coût déjà tenues, pas d'un second calcul : deux sources
        pour la même marge finiraient par diverger.
    </span>
</div>

@if($revpar['revpar'] !== null)
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">RevPAR</p>
            <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($revpar['revpar']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Prix moyen réalisé</p>
            <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $revpar['adr'] !== null ? $fcfa($revpar['adr']) : '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Occupation</p>
            <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $revpar['occupancy'] !== null ? $revpar['occupancy'] . ' %' : '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-secondary/20 p-3.5">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Nuitées vendues</p>
            <p class="text-base font-heading font-bold text-primary mt-0.5">{{ number_format($revpar['sold'], 0, ',', ' ') }}</p>
        </div>
    </div>
@endif

<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Type de chambre</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Prix de référence</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Coût variable</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Marge</th>
                    <th class="px-4 py-2.5 text-right font-semibold">Taux</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($marges as $ligne)
                    @php $s = $ligne['summary']; @endphp
                    <tr class="hover:bg-accent/10">
                        <td class="px-4 py-2 text-primary font-medium">
                            {{ $ligne['type']->name }}
                            <span class="block text-[10px] text-primary/40">{{ $s['line_count'] }} poste(s) de coût</span>
                        </td>
                        <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">
                            {{ $fcfa($s['reference_price']) }}
                            @if($s['reference_is_realized'])
                                <span class="block text-[10px] text-primary/40">réalisé</span>
                            @else
                                <span class="block text-[10px] text-primary/40">tarif</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right text-primary/75 whitespace-nowrap">{{ $fcfa($s['variable_cost']) }}</td>
                        <td class="px-3 py-2 text-right font-semibold whitespace-nowrap {{ $s['contribution_margin'] < 0 ? 'text-red-600' : 'text-primary' }}">
                            {{ $fcfa($s['contribution_margin']) }}
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap {{ $s['contribution_margin'] < 0 ? 'text-red-600' : 'text-primary/60' }}">
                            {{ $s['contribution_pct'] !== null ? $s['contribution_pct'] . ' %' : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-primary/50">
                            <i data-lucide="bed-double" class="w-8 h-8 mx-auto mb-3 text-primary/20"></i>
                            Aucune fiche de coût renseignée.
                            <a href="{{ route('rooms.cost_sheets.index') }}" class="text-primary hover:underline">En créer une</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
