@extends('layouts.hotel')

@section('title', 'Dépôt Bar')

@section('content')
<h1 class="mb-4 text-2xl font-semibold text-primary font-heading">Dépôt Bar</h1>
@include('restaurant.bar._nav')

@if(!$store)
    @include('restaurant.bar._depot-unavailable')
@else
    @php
        $openable = $stocks->filter(fn ($stock) => $stock->current_stock >= 1
            && $stock->item?->is_active && \App\Services\RestaurantBarStock::canOpen($stock->item));
        $missing = $shortages->groupBy('movement.stock_item_id')->map(fn ($rows) => $rows->sum('volume'));
    @endphp
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-primary/70">{{ $store->name }} · {{ $restaurant->name }}</p>
        @module('economat')
            <div class="flex flex-wrap gap-3">
                @droit('economat.requisitions.creer')
                    <a class="text-sm font-medium text-primary underline" href="{{ route('economat.requisitions.create', ['depot' => $store->id]) }}">Demander un approvisionnement</a>
                @enddroit
                @droit('economat.stores.voir')
                    <a class="text-sm font-medium text-primary underline" href="{{ route('economat.stores.show', $store) }}">Historique et inventaires du dépôt</a>
                @enddroit
            </div>
        @endmodule
    </div>

    @droit('restaurant.bar.open_bottle')
        <section class="mb-5 rounded-xl border border-secondary/20 bg-white p-4">
            <h2 class="mb-3 font-semibold text-primary">Ouvrir une bouteille</h2>
            @if($openable->isEmpty())
                <p class="text-sm text-primary/70">Aucune bouteille fermée disponible. Faites approvisionner le dépôt Bar depuis l’économat.</p>
            @else
                <form method="POST" action="{{ route('restaurant.bar.open_bottle') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="hidden" name="opening_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                    <label class="grid gap-1 text-sm text-primary">
                        Article
                        <select name="stock_item_id" required class="rounded-lg border-secondary/30">
                            <option value="">Choisir une bouteille</option>
                            @foreach($openable as $stock)
                                <option value="{{ $stock->stock_item_id }}" @selected(old('stock_item_id') == $stock->stock_item_id)>{{ $stock->item->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm text-primary">
                        Contenance indiquée (mL)
                        <input type="number" name="capacity_ml" min="1" max="100000" placeholder="750" value="{{ old('capacity_ml') }}" required class="w-40 rounded-lg border-secondary/30">
                    </label>
                    <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Ouvrir la bouteille</button>
                </form>
            @endif
        </section>
    @enddroit

    @if($shortages->isNotEmpty())
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <h2 class="font-semibold">Prélèvements à attribuer</h2>
            <p class="mt-1">Après avoir identifié la bouteille utilisée, ouvrez sa fiche pour y rattacher la quantité prélevée.</p>
            <ul class="mt-2 list-disc pl-5">
                @foreach($shortages as $row)
                    <li>{{ $row['movement']->item?->name }} · {{ $row['volume'] }} mL · commande nº {{ $row['movement']->source_id }} · {{ $row['movement']->details['sale_label'] }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="mb-5 overflow-x-auto rounded-xl border border-secondary/20 bg-white">
        <h2 class="px-4 py-3 font-semibold text-primary">Articles en stock</h2>
        <table class="w-full text-left text-sm text-primary">
            <thead class="bg-primary/5">
                <tr><th class="p-3">Article</th><th class="p-3">Stock fermé ou à l’unité</th><th class="p-3">Volume ouvert</th><th class="p-3">À attribuer</th></tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($stocks as $stock)
                    <tr>
                        <td class="p-3 font-medium">{{ $stock->item?->name }}</td>
                        <td class="p-3">{{ (float) $stock->current_stock }} {{ $stock->item?->unit }}</td>
                        <td class="p-3">{{ $volumes[$stock->stock_item_id] ?? 0 }} mL</td>
                        <td class="p-3">{{ ($missing[$stock->stock_item_id] ?? 0) ? '−'.$missing[$stock->stock_item_id].' mL' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-primary/60">Le dépôt n’a encore reçu aucun article.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="overflow-x-auto rounded-xl border border-secondary/20 bg-white">
        <h2 class="px-4 pt-3 font-semibold text-primary">Bouteilles ouvertes et terminées</h2>
        <p class="px-4 pb-3 pt-1 text-sm text-primary/70">Étiquetez chaque bouteille. Pour une même référence, utilisez d’abord la bouteille disponible ouverte la première, comme indiqué sur le ticket.</p>
        <table class="w-full text-left text-sm text-primary">
            <thead class="bg-primary/5">
                <tr><th class="p-3">Bouteille</th><th class="p-3">Ouverte le</th><th class="p-3">Volume restant</th><th class="p-3">Verres complets</th></tr>
            </thead>
            <tbody class="divide-y divide-secondary/10">
                @forelse($bottles as $bottle)
                    <tr>
                        <td class="p-3 font-medium"><a class="underline" href="{{ route('restaurant.bar.bottles.show', $bottle) }}">Bouteille nº {{ $bottle->id }} · {{ $bottle->item->name }}</a></td>
                        <td class="p-3">{{ $bottle->created_at->format('d/m/Y H:i') }}</td>
                        <td class="p-3">{{ $bottle->remaining_ml }} / {{ $bottle->capacity_ml }} mL</td>
                        <td class="p-3">{{ intdiv($bottle->remaining_ml, \App\Models\RestaurantBarDrink::GLASS_ML) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-primary/60">Aucune bouteille ouverte.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $bottles->links() }}</div>
    </section>
@endif
@endsection
