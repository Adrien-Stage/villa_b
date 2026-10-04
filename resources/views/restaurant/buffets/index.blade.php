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
            {{ $restaurant?->name ?? 'Ce restaurant' }} ne sert pas au buffet. La direction peut activer ce mode dans la fiche du restaurant.
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

<div class="overflow-x-auto rounded-xl bg-white shadow-sm">
    <table class="w-full text-left text-xs">
        <thead class="bg-accent/30 text-[11px] uppercase tracking-wider text-primary/50">
            <tr>
                <th class="px-4 py-2.5">Jour</th>
                <th class="px-4 py-2.5">Repas</th>
                @if($vueEnsemble)<th class="px-4 py-2.5">Restaurant</th>@endif
                <th class="px-4 py-2.5 text-right">Adultes</th>
                <th class="px-4 py-2.5 text-right">Enfants</th>
                <th class="px-4 py-2.5 text-right">Total</th>
                <th class="px-4 py-2.5">Statut</th>
                <th class="px-4 py-2.5"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-secondary/10">
            @forelse($services as $service)
                <tr>
                    <td class="px-4 py-2.5 font-semibold text-primary">{{ $service->service_date->format('d/m/Y') }}</td>
                    <td class="px-4 py-2.5 text-primary/70">{{ $service->libelleRepas() }}</td>
                    @if($vueEnsemble)<td class="px-4 py-2.5 text-primary/70">{{ $service->pointOfSale?->name }}</td>@endif
                    <td class="px-4 py-2.5 text-right">{{ (int) $service->total_adultes }}</td>
                    <td class="px-4 py-2.5 text-right">{{ (int) $service->total_enfants }}</td>
                    <td class="px-4 py-2.5 text-right font-semibold text-primary">{{ $fcfa($service->total_encaisse) }}</td>
                    <td class="px-4 py-2.5">
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $service->estOuvert() ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ $service->estOuvert() ? 'Ouvert' : 'Clos' }}
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-right">
                        <a href="{{ route('restaurant.buffets.show', $service) }}" class="font-semibold text-primary hover:underline">Ouvrir</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-10 text-center text-primary/50">Aucun buffet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $services->links() }}</div>
@endsection
