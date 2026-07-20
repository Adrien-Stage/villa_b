@extends('layouts.hotel')

@section('title', 'Caisse')

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $moduleLabels = ['reception' => 'Hébergement', 'shop' => 'Boutique', 'restaurant' => 'Restauration'];
@endphp

@section('content')
<div class="mb-4 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Caisse</h1>
        <p class="text-sm text-primary/60 mt-1">Sessions de caisse et rapprochement (théorique vs réel) sur la période.</p>
    </div>
    @include('accounting.partials.period')
</div>

@include('accounting.partials.nav')

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Sessions</p>
        <p class="text-2xl font-heading font-bold text-primary mt-1">{{ $caisse['sessions']->count() }}</p>
    </div>
    <div class="bg-white rounded-xl border border-secondary/20 p-5 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Sorties de caisse</p>
        <p class="text-2xl font-heading font-bold text-primary mt-1">{{ number_format($caisse['total_disbursements'] / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span></p>
    </div>
    <div class="bg-white rounded-xl border {{ $caisse['total_discrepancy'] < 0 ? 'border-red-200' : 'border-secondary/20' }} p-5 shadow-sm">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Écart cumulé (réel − théorique)</p>
        <p class="text-2xl font-heading font-bold mt-1 {{ $caisse['total_discrepancy'] < 0 ? 'text-red-600' : ($caisse['total_discrepancy'] > 0 ? 'text-amber-600' : 'text-green-600') }}">
            {{ $caisse['total_discrepancy'] > 0 ? '+' : '' }}{{ number_format($caisse['total_discrepancy'] / 100, 0, ',', ' ') }} <span class="text-sm">FCFA</span>
        </p>
    </div>
</div>

<section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-secondary/15 bg-gray-50/50"><h2 class="text-sm font-semibold text-primary">Sessions de caisse</h2></div>
    @if($caisse['sessions']->isEmpty())
        <p class="px-5 py-8 text-sm text-primary/40 text-center">Aucune session sur cette période.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Module</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Ouverte par</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">État</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Fond départ</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Théorique</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Réel</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Écart</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($caisse['sessions'] as $s)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-2.5 text-sm text-primary/80">{{ $moduleLabels[$s->module] ?? ucfirst($s->module) }}</td>
                            <td class="px-4 py-2.5">
                                <p class="text-sm text-primary">{{ $s->user->name ?? '—' }}</p>
                                <p class="text-[11px] text-primary/40">{{ $s->opened_at?->format('d/m/Y H:i') }}</p>
                            </td>
                            <td class="px-4 py-2.5">
                                @if($s->closed_at)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600">Clôturée</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-green-50 text-green-700 border border-green-200">En cours</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-sm text-primary/60 tabular-nums">{{ $fcfa($s->opening_amount) }}</td>
                            @if($s->closed_at)
                                <td class="px-4 py-2.5 text-right text-sm text-primary/70 tabular-nums">{{ $fcfa($s->theoretical_closing_amount) }}</td>
                                <td class="px-4 py-2.5 text-right text-sm font-semibold text-primary tabular-nums">{{ $fcfa($s->actual_closing_amount) }}</td>
                                <td class="px-4 py-2.5 text-right text-sm font-semibold tabular-nums {{ (int) $s->discrepancy_amount < 0 ? 'text-red-600' : ((int) $s->discrepancy_amount > 0 ? 'text-amber-600' : 'text-green-600') }}">
                                    {{ (int) $s->discrepancy_amount > 0 ? '+' : '' }}{{ number_format((int) $s->discrepancy_amount / 100, 0, ',', ' ') }}
                                </td>
                            @else
                                <td colspan="3" class="px-4 py-2.5 text-center text-xs text-primary/40 italic">En attente de clôture</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@if($caisse['disbursements']->isNotEmpty())
    <section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/15 bg-gray-50/50"><h2 class="text-sm font-semibold text-primary">Sorties de caisse</h2></div>
        @foreach($caisse['disbursements'] as $d)
            <div class="flex items-center justify-between px-5 py-2.5 border-b border-secondary/10 last:border-0">
                <span class="text-sm text-primary/75">{{ $d->reason ?: 'Sortie' }} <span class="text-[11px] text-primary/40">· {{ $d->user->name ?? '' }}</span></span>
                <span class="text-sm font-semibold text-red-600 tabular-nums">{{ $fcfa($d->amount) }}</span>
            </div>
        @endforeach
    </section>
@endif
@endsection
