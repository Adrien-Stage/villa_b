@extends('layouts.hotel')

@section('title', 'Buffet')

@php
    $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA';
    $libellesModes = ['cash' => 'Espèces', 'mobile_money' => 'Mobile money', 'card' => 'Carte', 'room_charge' => 'Sur le séjour', 'other' => 'Autre'];
@endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('restaurant.buffets.index') }}" class="text-xs text-primary/50 hover:text-primary">&larr; Buffets</a>
        <h1 class="font-heading text-2xl font-semibold text-primary">
            Buffet {{ mb_strtolower($buffet->libelleRepas()) }} du {{ $buffet->service_date->format('d/m/Y') }}
        </h1>
        <p class="text-sm text-primary/50 mt-0.5">
            {{ $buffet->pointOfSale?->name }} · adulte {{ $fcfa($buffet->adult_price) }}
            @if($buffet->child_price > 0) · enfant {{ $fcfa($buffet->child_price) }} @endif
            · {{ $buffet->estOuvert() ? 'ouvert' : 'clos' }}
        </p>
    </div>
    @if($buffet->estOuvert())
        @droit('restaurant.buffets.close')
            <form method="POST" action="{{ route('restaurant.buffets.close', $buffet) }}" onsubmit="return confirm('Clore ce buffet ? Il n\'enregistrera plus d\'entrées.')">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-secondary/25 bg-white px-4 py-2 text-xs font-semibold text-primary hover:bg-accent/20">
                    <i data-lucide="lock" class="w-3.5 h-3.5"></i> Clore le buffet
                </button>
            </form>
        @enddroit
    @endif
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

<div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-[11px] uppercase tracking-wider text-primary/50">Adultes</p>
        <p class="mt-1 text-xl font-semibold text-primary">{{ $buffet->entries->sum('adults') }}</p>
    </div>
    <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-[11px] uppercase tracking-wider text-primary/50">Enfants</p>
        <p class="mt-1 text-xl font-semibold text-primary">{{ $buffet->entries->sum('children') }}</p>
    </div>
    <div class="col-span-2 rounded-xl bg-white p-4 shadow-sm">
        <p class="text-[11px] uppercase tracking-wider text-primary/50">Total</p>
        <p class="mt-1 text-xl font-semibold text-primary">{{ $fcfa($buffet->entries->sum('amount')) }}</p>
        <p class="mt-1 text-[11px] text-primary/50">
            @foreach($parMode as $mode => $ligne)
                {{ $libellesModes[$mode] ?? $mode }} : {{ $fcfa($ligne['montant']) }}@if(! $loop->last) · @endif
            @endforeach
        </p>
    </div>
</div>

@if($buffet->estOuvert())
    @droit('restaurant.buffets.entries.creer')
        <form method="POST" action="{{ route('restaurant.buffets.entries.store', $buffet) }}"
            x-data="{ mode: @js(old('payment_method', 'cash')), adultes: {{ (int) old('adults', 1) }}, enfants: {{ (int) old('children', 0) }} }"
            class="mb-6 rounded-xl border border-secondary/20 bg-white p-5 shadow-sm">
            @csrf
            <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-primary/60">Enregistrer une entrée</h2>
            @if(! $caisse)
                <p class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    Votre caisse n'est pas ouverte : seules les entrées reportées sur un séjour peuvent être enregistrées.
                </p>
            @endif
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="block">
                    <span class="text-xs text-primary/60">Adultes</span>
                    <input type="number" name="adults" min="0" max="500" x-model.number="adultes" required
                        class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <label class="block">
                    <span class="text-xs text-primary/60">Enfants</span>
                    <input type="number" name="children" min="0" max="500" x-model.number="enfants"
                        class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <label class="block">
                    <span class="text-xs text-primary/60">Paiement</span>
                    <select name="payment_method" x-model="mode" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                        @foreach($paymentMethods as $methode)
                            <option value="{{ $methode }}">{{ $libellesModes[$methode] ?? $methode }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block" x-show="mode === 'room_charge'" x-cloak>
                    <span class="text-xs text-primary/60">Résident</span>
                    <select name="booking_id" class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                        <option value="">Choisir…</option>
                        @foreach($residents as $sejour)
                            <option value="{{ $sejour->id }}" @selected((int) old('booking_id') === $sejour->id)>
                                Ch. {{ $sejour->room?->number ?? '—' }} — {{ $sejour->customer?->full_name ?? 'Client' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="flex flex-col justify-end">
                    <p class="mb-1 text-right text-xs text-primary/60">
                        À payer : <strong class="text-primary" x-text="((adultes || 0) * {{ (int) $buffet->adult_price }} + (enfants || 0) * {{ (int) $buffet->child_price }}) / 100 + ' FCFA'"></strong>
                    </p>
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">Enregistrer</button>
                </div>
            </div>
        </form>
    @enddroit
@endif

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-xs">
        <thead class="bg-accent/30 text-[11px] uppercase tracking-wider text-primary/50">
            <tr>
                <th class="px-4 py-2.5">Heure</th>
                <th class="px-4 py-2.5 text-right">Adultes</th>
                <th class="px-4 py-2.5 text-right">Enfants</th>
                <th class="px-4 py-2.5 text-right">Montant</th>
                <th class="px-4 py-2.5">Paiement</th>
                <th class="px-4 py-2.5">Par</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-secondary/10">
            @forelse($buffet->entries as $entree)
                <tr>
                    <td class="px-4 py-2.5 text-primary/70">{{ $entree->created_at->format('H:i') }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $entree->adults }}</td>
                    <td class="px-4 py-2.5 text-right">{{ $entree->children }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-primary">{{ $fcfa($entree->amount) }}</td>
                    <td class="px-4 py-2.5 text-primary/70">
                        {{ $libellesModes[$entree->payment_method] ?? $entree->payment_method }}
                        @if($entree->booking) · ch. {{ $entree->booking->room?->number }} @endif
                    </td>
                    <td class="px-4 py-2.5 text-primary/60">{{ $entree->recordedBy?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-primary/50">Aucune entrée pour l'instant.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
