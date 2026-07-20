@extends('layouts.hotel')

@section('title', 'Compte de résultat')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ') . ' FCFA';
    $p = $resultat['produits'];
    $ch = $resultat['charges'];
    $activiteLabels = ['hebergement' => 'Ventes hébergement', 'restaurant' => 'Ventes restauration', 'boutique' => 'Ventes boutique'];
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Compte de résultat</h1>
        <p class="text-sm text-primary/60 mt-1">Produits − Charges = Résultat net, sur la période (logique de caisse).</p>
    </div>
    @include('accounting.partials.period')
</div>

@include('accounting.partials.nav')

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Produits --}}
    <section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/15 bg-green-50/50">
            <h2 class="text-sm font-semibold text-green-800 uppercase tracking-widest text-[11px]">Produits (recettes encaissées)</h2>
        </div>
        <div class="divide-y divide-secondary/10">
            @foreach($activiteLabels as $key => $label)
                <div class="flex items-center justify-between px-5 py-3">
                    <span class="text-sm text-primary/75">{{ $label }}</span>
                    <span class="text-sm font-medium text-primary tabular-nums">{{ $fcfa($p[$key]) }}</span>
                </div>
            @endforeach
        </div>
        <div class="flex items-center justify-between px-5 py-3 border-t-2 border-secondary/15 bg-gray-50/50">
            <span class="text-sm font-semibold text-primary">Total Produits</span>
            <span class="text-sm font-bold text-green-700 tabular-nums">{{ $fcfa($p['total']) }}</span>
        </div>
    </section>

    {{-- Charges --}}
    <section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-secondary/15 bg-red-50/50">
            <h2 class="text-sm font-semibold text-red-800 uppercase tracking-widest text-[11px]">Charges (dépenses décaissées)</h2>
        </div>
        @if(empty($ch['by_category']))
            <p class="px-5 py-8 text-sm text-primary/40 text-center">Aucune dépense saisie sur la période.<br><a href="{{ route('accounting.expenses', ['month' => $period['month']]) }}" class="text-primary hover:underline text-xs">Saisir une dépense →</a></p>
        @else
            <div class="divide-y divide-secondary/10">
                @foreach($ch['by_category'] as $cat => $amount)
                    <div class="flex items-center justify-between px-5 py-3">
                        <span class="text-sm text-primary/75">{{ $categories[$cat] ?? ucfirst($cat) }}</span>
                        <span class="text-sm font-medium text-primary tabular-nums">{{ $fcfa($amount) }}</span>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="flex items-center justify-between px-5 py-3 border-t-2 border-secondary/15 bg-gray-50/50">
            <span class="text-sm font-semibold text-primary">Total Charges</span>
            <span class="text-sm font-bold text-red-600 tabular-nums">{{ $fcfa($ch['total']) }}</span>
        </div>
    </section>
</div>

{{-- Résultat net --}}
<div class="mt-6 bg-white rounded-xl border-2 {{ $resultat['resultat_net'] < 0 ? 'border-red-300' : 'border-green-300' }} shadow-sm px-6 py-5 flex items-center justify-between">
    <div>
        <p class="text-sm font-semibold text-primary uppercase tracking-widest text-[11px]">Résultat net — {{ ucfirst($period['label']) }}</p>
        <p class="text-xs text-primary/50 mt-0.5">{{ $resultat['resultat_net'] < 0 ? 'Déficitaire sur la période' : 'Bénéficiaire sur la période' }}</p>
    </div>
    <p class="text-3xl font-heading font-black {{ $resultat['resultat_net'] < 0 ? 'text-red-600' : 'text-green-600' }}">
        {{ $resultat['resultat_net'] > 0 ? '+' : '' }}{{ number_format($resultat['resultat_net'] / 100, 0, ',', ' ') }} <span class="text-lg">FCFA</span>
    </p>
</div>

<p class="text-[11px] text-primary/40 mt-4 max-w-3xl">
    Ce compte de résultat suit la <b>logique de caisse</b> : les produits sont les sommes réellement encaissées, les charges les sommes réellement décaissées. Le coût matière (resto) et le coût d'achat (boutique) n'y figurent pas — ils relèveront du suivi de marge et du futur module Inventaire.
</p>
@endsection
