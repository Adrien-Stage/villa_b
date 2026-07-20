@extends('layouts.hotel')

@section('title', 'Créances')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ') . ' FCFA';
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Créances</h1>
    <p class="text-sm text-primary/60 mt-1">Ce qu'on nous doit aujourd'hui — soldes de séjours et commandes impayées (instantané).</p>
</div>

@include('accounting.partials.nav')

<div class="bg-white rounded-xl border {{ $creances['total'] > 0 ? 'border-amber-200' : 'border-secondary/20' }} shadow-sm p-5 mb-6 flex items-center justify-between">
    <span class="text-sm font-semibold text-primary/70 uppercase tracking-widest text-[11px]">Total à percevoir</span>
    <span class="text-2xl font-heading font-bold {{ $creances['total'] > 0 ? 'text-amber-600' : 'text-primary' }}">{{ $fcfa($creances['total']) }}</span>
</div>

{{-- Séjours --}}
<section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-secondary/15 bg-gray-50/50 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-primary flex items-center gap-2"><i data-lucide="bed-double" class="w-4 h-4 text-primary/50"></i> Séjours — soldes dus</h2>
        <span class="text-sm font-semibold text-primary">{{ $fcfa($creances['sejours']['total']) }}</span>
    </div>
    @if($creances['sejours']['items']->isEmpty())
        <p class="px-5 py-6 text-sm text-primary/40 text-center">Aucun solde de séjour en attente.</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/10">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Séjour</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Client</th>
                        <th class="px-4 py-2.5 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Statut</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Total</th>
                        <th class="px-4 py-2.5 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Solde dû</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($creances['sejours']['items'] as $b)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-2.5 text-sm font-medium text-primary">{{ $b->booking_number ?? '#' . $b->id }}</td>
                            <td class="px-4 py-2.5 text-sm text-primary/70">{{ trim(($b->customer->first_name ?? '') . ' ' . ($b->customer->last_name ?? '')) ?: '—' }}</td>
                            <td class="px-4 py-2.5"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600">{{ ucfirst(str_replace('_', ' ', $b->status->value ?? $b->status)) }}</span></td>
                            <td class="px-4 py-2.5 text-right text-sm text-primary/60 tabular-nums">{{ $fcfa((int) $b->total_amount) }}</td>
                            <td class="px-4 py-2.5 text-right text-sm font-semibold text-amber-600 tabular-nums">{{ $fcfa((int) $b->balance_due) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Commandes resto impayées --}}
    <section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/15 bg-gray-50/50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2"><i data-lucide="utensils" class="w-4 h-4 text-primary/50"></i> Restaurant — impayés</h2>
            <span class="text-sm font-semibold text-primary">{{ $fcfa($creances['restaurant']['total']) }}</span>
        </div>
        @forelse($creances['restaurant']['items'] as $o)
            <div class="flex items-center justify-between px-5 py-2.5 border-b border-secondary/10 last:border-0">
                <span class="text-sm text-primary/75">Commande #{{ $o->id }}@if($o->table_number) · Table {{ $o->table_number }}@endif</span>
                <span class="text-sm font-semibold text-amber-600 tabular-nums">{{ $fcfa((int) $o->total_amount) }}</span>
            </div>
        @empty
            <p class="px-5 py-6 text-sm text-primary/40 text-center">Aucune commande impayée.</p>
        @endforelse
    </section>

    {{-- Commandes boutique impayées --}}
    <section class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-secondary/15 bg-gray-50/50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-primary flex items-center gap-2"><i data-lucide="shopping-bag" class="w-4 h-4 text-primary/50"></i> Boutique — impayés</h2>
            <span class="text-sm font-semibold text-primary">{{ $fcfa($creances['boutique']['total']) }}</span>
        </div>
        @forelse($creances['boutique']['items'] as $o)
            <div class="flex items-center justify-between px-5 py-2.5 border-b border-secondary/10 last:border-0">
                <span class="text-sm text-primary/75">{{ $o->order_number ?: ('Commande #' . $o->id) }}@if($o->customer_name) · {{ $o->customer_name }}@endif</span>
                <span class="text-sm font-semibold text-amber-600 tabular-nums">{{ $fcfa((int) $o->total_amount) }}</span>
            </div>
        @empty
            <p class="px-5 py-6 text-sm text-primary/40 text-center">Aucune commande impayée.</p>
        @endforelse
    </section>
</div>
@endsection
