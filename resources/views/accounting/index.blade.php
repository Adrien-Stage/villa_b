@extends('layouts.hotel')

@section('title', 'Comptabilité')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ') . ' FCFA';
    $methodLabels = [
        'cash' => 'Espèces', 'orange_money' => 'Orange Money', 'mtn_momo' => 'MTN MoMo',
        'bank_transfer' => 'Virement', 'check' => 'Chèque', 'stripe' => 'Carte', 'autre' => 'Autre',
    ];
    $activites = [
        'hebergement' => ['Hébergement', 'bed-double', 'bg-blue-50 text-blue-700 border-blue-200'],
        'restaurant'  => ['Restauration', 'utensils', 'bg-amber-50 text-amber-700 border-amber-200'],
        'boutique'    => ['Boutique', 'shopping-bag', 'bg-violet-50 text-violet-700 border-violet-200'],
    ];
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Comptabilité</h1>
        <p class="text-sm text-primary/60 mt-1">Comptabilité de caisse — hébergement, restauration et boutique consolidés.</p>
    </div>
    @include('accounting.partials.period')
</div>

@include('accounting.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif

{{-- KPI --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Recettes encaissées</p>
        <p class="text-2xl font-heading font-bold text-primary mt-1">{{ number_format($recettes['total'] / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Dépenses</p>
        <p class="text-2xl font-heading font-bold text-primary mt-1">{{ number_format($resultat['charges']['total'] / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border {{ $resultat['resultat_net'] < 0 ? 'border-red-200' : 'border-green-200' }} p-5 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Résultat net</p>
        <p class="text-2xl font-heading font-bold mt-1 {{ $resultat['resultat_net'] < 0 ? 'text-red-600' : 'text-green-600' }}">
            {{ $resultat['resultat_net'] > 0 ? '+' : '' }}{{ number_format($resultat['resultat_net'] / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span>
        </p>
    </div>
    <a href="{{ route('accounting.receivables') }}" class="bg-white rounded-xl border {{ $creances['total'] > 0 ? 'border-amber-200' : 'border-secondary/20' }} p-5 shadow-sm hover:shadow-md transition-shadow">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Créances (à percevoir)</p>
        <p class="text-2xl font-heading font-bold mt-1 {{ $creances['total'] > 0 ? 'text-amber-600' : 'text-primary' }}">{{ number_format($creances['total'] / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span></p>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Recettes par activité --}}
    <section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/15 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-primary">Recettes par activité</h2>
        </div>
        <div class="p-5 space-y-4">
            @php $maxAct = max(1, $recettes['hebergement'], $recettes['restaurant'], $recettes['boutique']); @endphp
            @foreach($activites as $key => [$label, $icon, $chip])
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-sm text-primary/80 flex items-center gap-2">
                            <i data-lucide="{{ $icon }}" class="w-4 h-4 text-primary/50"></i> {{ $label }}
                        </span>
                        <span class="text-sm font-semibold text-primary">{{ $fcfa($recettes[$key]) }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-secondary/10 overflow-hidden">
                        <div class="h-full bg-primary/70 rounded-full" style="width: {{ round(max(0, $recettes[$key]) / $maxAct * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Recettes par moyen de paiement + caisse --}}
    <section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/15 bg-gray-50/50">
            <h2 class="text-sm font-semibold text-primary">Encaissements par moyen de paiement</h2>
        </div>
        <div class="p-5">
            @forelse($recettes['by_method'] as $method => $amount)
                <div class="flex items-center justify-between py-2 border-b border-secondary/10 last:border-0">
                    <span class="text-sm text-primary/75">{{ $methodLabels[$method] ?? ucfirst($method) }}</span>
                    <span class="text-sm font-semibold text-primary">{{ $fcfa($amount) }}</span>
                </div>
            @empty
                <p class="text-sm text-primary/40 py-4 text-center">Aucun encaissement sur la période.</p>
            @endforelse
        </div>
        <a href="{{ route('accounting.cash', ['month' => $period['month']]) }}"
            class="flex items-center justify-between px-5 py-3 border-t border-secondary/15 bg-gray-50/50 hover:bg-accent/10 transition-colors">
            <span class="text-sm text-primary/70">
                Caisse — {{ $caisse['sessions']->count() }} session(s)
                @if($caisse['total_discrepancy'] != 0)
                    · écart <span class="{{ $caisse['total_discrepancy'] < 0 ? 'text-red-600' : 'text-amber-600' }} font-medium">{{ $fcfa($caisse['total_discrepancy']) }}</span>
                @endif
            </span>
            <i data-lucide="arrow-right" class="w-4 h-4 text-primary/40"></i>
        </a>
    </section>
</div>

<p class="text-[11px] text-primary/40 mt-6 max-w-3xl">
    Logique de <b>caisse</b> : seuls les montants réellement encaissés/décaissés sont comptés. Les commandes réglées « à la chambre » sont rattachées au séjour (pas de double comptage). Le coût matière et les marges sont des indicateurs de gestion, hors résultat de caisse.
</p>
@endsection
