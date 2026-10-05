@extends('layouts.hotel')

@section('title', 'Buffets')

@php $fcfa = fn ($c) => number_format(((int) $c) / 100, 0, ',', ' ') . ' FCFA'; @endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-primary">Buffets</h1>
        <p class="text-sm text-primary/50 mt-0.5 max-w-3xl">
            Le buffet au forfait : on ouvre un service pour un repas, à un prix d'entrée, et la caisse enregistre les entrées sans note par table.
            La formule buffet au couvert, elle, se vend depuis la carte.
        </p>
    </div>
</div>

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif

@droit('restaurant.buffets.creer')
    @if($vueEnsemble)
        <p class="mb-5 rounded-lg border border-secondary/20 bg-accent/10 px-4 py-2 text-xs text-primary/70">Choisissez un restaurant pour y ouvrir un buffet.</p>
    @elseif(! $peutOuvrirIci)
        <p class="mb-5 rounded-lg border border-secondary/20 bg-accent/10 px-4 py-2 text-xs text-primary/70">
            @if($restaurant?->sert(\App\Models\PointOfSale::MODE_BUFFET))
                {{ $restaurant->name }} n'a pas de salle : le buffet ne s'y sert pas. Ce service s'active dans Paramètres › Restaurant.
            @else
                {{ $restaurant?->name ?? 'Ce restaurant' }} ne sert pas au buffet. La direction peut activer ce mode dans Paramètres › Restaurant.
            @endif
        </p>
    @else
        <form method="POST" action="{{ route('restaurant.buffets.store') }}" class="mb-6 rounded-xl border border-secondary/20 bg-white p-5 shadow-sm">
            @csrf
            <h2 class="mb-3 text-xs font-bold uppercase tracking-wider text-primary/60">Ouvrir un buffet — {{ $restaurant->name }}</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="block">
                    <span class="text-xs text-primary/60">Jour</span>
                    <input type="date" name="service_date" required value="{{ old('service_date', now()->toDateString()) }}"
                        class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <label class="block">
                    <span class="text-xs text-primary/60">Repas</span>
                    <select name="meal_service" required class="mt-1 w-full rounded-lg border border-secondary/30 bg-white px-3 py-2 text-sm outline-none focus:border-secondary">
                        @foreach($repas as $cle => $libelle)
                            <option value="{{ $cle }}" @selected(old('meal_service') === $cle)>{{ $libelle }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs text-primary/60">Prix adulte (FCFA)</span>
                    <input type="number" name="adult_price" required min="0" value="{{ old('adult_price') }}"
                        class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <label class="block">
                    <span class="text-xs text-primary/60">Prix enfant (FCFA)</span>
                    <input type="number" name="child_price" min="0" value="{{ old('child_price', 0) }}"
                        class="mt-1 w-full rounded-lg border border-secondary/30 px-3 py-2 text-sm outline-none focus:border-secondary">
                </label>
                <div class="flex items-end">
                    <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-white hover:opacity-95">Ouvrir le buffet</button>
                </div>
            </div>
        </form>
    @endif
@enddroit

<x-table :rows="$services" empty="Aucun buffet." empty-icon="salad" caption="Services de buffet">
    <x-slot:head>
        <x-table.col>Jour</x-table.col>
        <x-table.col>Repas</x-table.col>
        @if($vueEnsemble)<x-table.col hide="xl">Restaurant</x-table.col>@endif
        <x-table.col align="right" hide="lg">Adultes</x-table.col>
        <x-table.col align="right" hide="lg">Enfants</x-table.col>
        <x-table.col align="right">Total</x-table.col>
        <x-table.col>Statut</x-table.col>
        <x-table.col actions />
    </x-slot:head>

    @foreach($services as $service)
        <x-table.row :href="route('restaurant.buffets.show', $service)">
            <x-table.cell nowrap>
                <a href="{{ route('restaurant.buffets.show', $service) }}" class="font-semibold text-primary hover:underline">{{ $service->service_date->format('d/m/Y') }}</a>
            </x-table.cell>
            <x-table.cell class="text-primary/70">{{ $service->libelleRepas() }}</x-table.cell>
            @if($vueEnsemble)<x-table.cell hide="xl" class="text-primary/70">{{ $service->pointOfSale?->name }}</x-table.cell>@endif
            <x-table.cell align="right" hide="lg">{{ (int) $service->total_adultes }}</x-table.cell>
            <x-table.cell align="right" hide="lg">{{ (int) $service->total_enfants }}</x-table.cell>
            <x-table.cell align="right" nowrap class="font-semibold">{{ $fcfa($service->total_encaisse) }}</x-table.cell>
            <x-table.cell nowrap>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $service->estOuvert() ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $service->estOuvert() ? 'Ouvert' : 'Clos' }}</span>
            </x-table.cell>
            <x-table.actions :label="'Actions pour le buffet du '.$service->service_date->format('d/m/Y')">
                <x-table.action :href="route('restaurant.buffets.show', $service)" icon="{{ $service->estOuvert() ? 'ticket' : 'eye' }}">{{ $service->estOuvert() ? 'Enregistrer les entrées' : 'Ouvrir' }}</x-table.action>
                @if($service->estOuvert())
                    @droit('restaurant.buffets.close')
                        <x-table.action :action="route('restaurant.buffets.close', $service)" icon="lock" tone="danger" confirm="Clore ce buffet ? Il n'enregistrera plus d'entrées.">Clore</x-table.action>
                    @enddroit
                @endif
            </x-table.actions>
        </x-table.row>
    @endforeach
</x-table>
@endsection
