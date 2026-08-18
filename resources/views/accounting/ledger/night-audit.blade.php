@extends('layouts.hotel')

@section('title', 'Clôture journalière')

@php
    $fcfa = fn ($c) => number_format($c / 100, 0, ',', ' ');
@endphp

@section('content')
<div class="mb-4">
    <h1 class="text-2xl font-semibold text-primary font-heading">Clôture journalière</h1>
    <p class="text-sm text-primary/60 mt-1">Comptabiliser, constater, figer la journée</p>
</div>

@include('accounting.ledger.partials.nav')

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
@endif

{{-- Journées oubliées --}}
@if(count($enAttente) > 0)
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
        <p class="text-sm font-semibold text-amber-800 flex items-center gap-2">
            <i data-lucide="calendar-x" class="w-4 h-4"></i>
            {{ count($enAttente) }} journée{{ count($enAttente) > 1 ? 's' : '' }} non clôturée{{ count($enAttente) > 1 ? 's' : '' }}
        </p>
        <p class="text-xs text-amber-700/85 mt-1">
            Une journée oubliée laisse son chiffre d'affaires non constaté :
            {{ collect($enAttente)->take(8)->map(fn ($d) => $d->format('d/m'))->implode(', ') }}@if(count($enAttente) > 8), …@endif
        </p>
    </div>
@endif

{{-- Aperçu et clôture --}}
<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-secondary/15 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-primary">Journée du {{ $apercu['date']->format('d/m/Y') }}</h2>
        <form method="GET" class="flex gap-2">
            <input type="date" name="date" value="{{ $jour->toDateString() }}" max="{{ now()->toDateString() }}"
                   class="rounded-lg border border-secondary/25 bg-white text-xs p-2">
            <button type="submit" class="px-3 py-2 border border-secondary/30 text-primary text-xs font-semibold rounded-lg hover:bg-accent/20 transition-colors">
                Voir
            </button>
        </form>
    </div>

    <div class="p-5">
        @if($apercu['already_closed'])
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-green-50 border border-green-200 mb-4">
                <i data-lucide="check-circle-2" class="w-5 h-5 text-green-600 shrink-0"></i>
                <p class="text-sm text-green-800">
                    <span class="font-semibold">Journée déjà clôturée.</span>
                    Toute correction passe désormais par une écriture datée d'aujourd'hui.
                </p>
            </div>
        @elseif($apercu['is_future'])
            <div class="flex items-center gap-3 px-4 py-3 rounded-lg bg-accent/20 border border-secondary/20">
                <i data-lucide="clock" class="w-5 h-5 text-primary/40 shrink-0"></i>
                <p class="text-sm text-primary/70">
                    Cette journée n'est pas terminée : on ne clôture jamais une journée en cours.
                </p>
            </div>
        @endif

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="rounded-xl border border-secondary/20 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Hébergement</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($apercu['revenue']['accommodation']) }}</p>
            </div>
            <div class="rounded-xl border border-secondary/20 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Restauration</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($apercu['revenue']['restaurant']) }}</p>
            </div>
            <div class="rounded-xl border border-secondary/20 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Boutique</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($apercu['revenue']['shop']) }}</p>
            </div>
            <div class="rounded-xl border border-primary/20 bg-accent/15 p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/50">Chiffre d'affaires</p>
                <p class="text-base font-heading font-bold text-primary mt-0.5">{{ $fcfa($apercu['revenue']['total']) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4 text-[11px]">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-accent/25 text-primary/70">
                <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
                Trésorerie encaissée : <strong>{{ $fcfa($apercu['treasury']) }} F</strong>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg {{ $apercu['cash_discrepancy'] != 0 ? 'bg-amber-50 text-amber-800' : 'bg-accent/25 text-primary/70' }}">
                <i data-lucide="scale" class="w-3.5 h-3.5"></i>
                Écart de caisse : <strong>{{ $fcfa($apercu['cash_discrepancy']) }} F</strong>
                ({{ $apercu['registers_closed'] }} caisse{{ $apercu['registers_closed'] > 1 ? 's' : '' }})
            </span>
            @if($apercu['registers_open'] > 0)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-800">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                    {{ $apercu['registers_open'] }} caisse(s) encore ouverte(s)
                </span>
            @endif
        </div>

        @unless($apercu['already_closed'] || $apercu['is_future'])
            @if($apercu['registers_open'] > 0)
                <p class="mb-3 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-[11px] text-amber-800">
                    Des caisses de cette journée n'ont pas été fermées : leur écart restera inconnu.
                    Mieux vaut les faire clôturer avant de figer la journée.
                </p>
            @endif

            <form method="POST" action="{{ route('accounting.ledger.night_audit.run') }}"
                  class="flex flex-col sm:flex-row gap-2"
                  onsubmit="return confirm('Clôturer la journée du {{ $apercu['date']->format('d/m/Y') }} ? Aucune écriture ne pourra plus y être ajoutée.');">
                @csrf
                <input type="hidden" name="date" value="{{ $apercu['date']->toDateString() }}">
                <input type="text" name="notes" maxlength="500" placeholder="Commentaire (facultatif)"
                       class="w-full rounded-lg border border-secondary/25 bg-white text-sm p-2.5">
                <button type="submit" class="px-5 py-2.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm shrink-0">
                    Clôturer la journée
                </button>
            </form>
        @endunless
    </div>
</div>

{{-- Historique --}}
<div class="bg-white rounded-xl border border-secondary/20 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-secondary/15">
        <h2 class="text-sm font-semibold text-primary">Historique des clôtures</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-accent/20 text-primary/45 uppercase tracking-widest text-[10px]">
                <tr>
                    <th class="px-4 py-2.5 text-left font-semibold">Journée</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Hébergement</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Resto</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Boutique</th>
                    <th class="px-3 py-2.5 text-right font-semibold">CA</th>
                    <th class="px-3 py-2.5 text-right font-semibold">Écart caisse</th>
                    <th class="px-4 py-2.5 text-left font-semibold">Clôturée par</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($historique as $a)
                    <tr class="hover:bg-accent/10">
                        <td class="px-4 py-2 whitespace-nowrap font-medium text-primary">{{ $a->audit_date->format('d/m/Y') }}</td>
                        <td class="px-3 py-2 text-right text-primary/70 whitespace-nowrap">{{ $fcfa($a->revenue_accommodation) }}</td>
                        <td class="px-3 py-2 text-right text-primary/70 whitespace-nowrap">{{ $fcfa($a->revenue_restaurant) }}</td>
                        <td class="px-3 py-2 text-right text-primary/70 whitespace-nowrap">{{ $fcfa($a->revenue_shop) }}</td>
                        <td class="px-3 py-2 text-right font-semibold text-primary whitespace-nowrap">{{ $fcfa($a->revenue_total) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap {{ $a->hasDiscrepancy() ? 'text-amber-700 font-semibold' : 'text-primary/40' }}">
                            {{ $fcfa($a->cash_discrepancy) }}
                        </td>
                        <td class="px-4 py-2 text-primary/60">
                            {{ $a->closedBy?->name ?? 'Automatique' }}
                            <span class="text-[10px] text-primary/35 block">{{ $a->closed_at->format('d/m H:i') }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-primary/50">Aucune clôture enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($historique->hasPages())
    <div class="mt-4">{{ $historique->links() }}</div>
@endif
@endsection
