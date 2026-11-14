@extends('layouts.hotel')

@section('title', 'Carte du bar')

@section('content')
<h1 class="mb-4 text-2xl font-semibold text-primary font-heading">Carte et fiches techniques du bar</h1>
@include('restaurant.bar._nav')

@if(!$store)
    @include('restaurant.bar._depot-unavailable')
@else
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm text-primary/70">
        <p>Associez les boissons de la carte du restaurant aux articles de l’économat. Les ingrédients doivent être livrés au dépôt Bar.</p>
        @if($focused)
            <a class="font-medium text-primary underline" href="{{ route('restaurant.bar.menu.index') }}">Voir toutes les boissons</a>
        @else
            @droit('restaurant.menus.voir')
                <a class="font-medium text-primary underline" href="{{ route('restaurant.menus.index', ['type' => 'drink']) }}">Ouvrir la carte du restaurant</a>
            @enddroit
        @endif
    </div>

    <div class="space-y-3">
        @forelse($drinks as $drink)
            @php
                $recipe = $drink->barDrink;
                $editingThis = (int) old('edited_item') === $drink->id;
                $format = $editingThis ? old('format', 'bottle') : ($recipe?->format ?? 'bottle');
                $rows = $editingThis ? old('ingredients', []) : ($recipe?->ingredients ?? []);
                $usesRecipe = in_array($format, ['cocktail', 'mocktail'], true);
                $singleItem = $editingThis ? old('stock_item_id') : ($recipe?->ingredients[0]['stock_item_id'] ?? null);
            @endphp
            <details @if($editingThis || $focused) open @endif class="rounded-xl border border-secondary/20 bg-white p-4 text-primary">
                <summary class="cursor-pointer font-semibold">
                    {{ $drink->name }}
                    <span class="ml-2 text-sm font-normal text-primary/70">{{ number_format($drink->price / 100, 0, ',', ' ') }} FCFA · {{ $recipe ? \App\Models\RestaurantBarDrink::FORMATS[$recipe->format] : 'Suivi de stock à configurer' }} @unless($drink->is_active) · Hors carte @endunless</span>
                </summary>
                @if($recipe)
                    <div class="mt-3 flex items-center justify-between border-b border-secondary/10 pb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider text-primary/70">Fiche technique du cocktail / boisson</span>
                        <a href="{{ route('restaurant.bar.menu.print', $drink) }}" target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-secondary/25 hover:border-secondary/50 text-primary text-xs font-medium rounded-lg transition-colors shadow-sm">
                            <i data-lucide="printer" class="w-3.5 h-3.5 text-secondary"></i>
                            <span>Imprimer la fiche bar</span>
                        </a>
                    </div>
                    <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                        @foreach($recipe->ingredients as $ingredient)
                            @php $stockItem = $items[$ingredient['stock_item_id']] ?? null; @endphp
                            <li>{{ $ingredient['quantity'] }} {{ $ingredient['measure'] === 'ml' ? 'mL' : $stockItem?->unit }} · {{ $stockItem?->name ?? 'Article inactif ou supprimé' }}</li>
                        @endforeach
                    </ul>
                    @if($recipe->instructions)
                        <p class="mt-2 whitespace-pre-line text-sm text-primary/70">{{ $recipe->instructions }}</p>
                    @endif
                @endif

                @droit('restaurant.bar.configure')
                    <form method="POST" action="{{ route('restaurant.bar.configure', $drink) }}" class="mt-4 space-y-3 border-t border-secondary/20 pt-4"
                        x-data="{ format: @js($format), rows: {{ max(1, is_array($rows) ? count($rows) : 0) }}, recipe() { return ['cocktail', 'mocktail'].includes(this.format); } }">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="edited_item" value="{{ $drink->id }}">
                        <label class="grid max-w-md gap-1 text-sm">Format de vente
                            <select name="format" x-model="format" class="rounded-lg border-secondary/30">
                                @foreach(\App\Models\RestaurantBarDrink::FORMATS as $value => $label)
                                    <option value="{{ $value }}" @selected($format === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label x-show="!recipe()" @if($usesRecipe) style="display: none" @endif class="grid max-w-md gap-1 text-sm">Article stocké à la bouteille
                            <select name="stock_item_id" :disabled="recipe()" class="rounded-lg border-secondary/30">
                                <option value="">Choisir un article</option>
                                @foreach($items as $stockItem)
                                    @if(\App\Services\RestaurantBarStock::canOpen($stockItem))
                                        <option value="{{ $stockItem->id }}" @selected($singleItem == $stockItem->id)>{{ $stockItem->name }} ({{ $stockItem->unit }})</option>
                                    @endif
                                @endforeach
                            </select>
                        </label>

                        <fieldset x-show="recipe()" @unless($usesRecipe) style="display: none" @endunless class="space-y-2">
                            <legend class="mb-2 text-sm font-semibold">Ingrédients pour une boisson</legend>
                            @for($index = 0; $index < max(1, is_array($rows) ? count($rows) : 0); $index++)
                                @include('restaurant.bar._ingredient', ['ingredient' => is_array($rows[$index] ?? null) ? $rows[$index] : [], 'index' => $index])
                            @endfor
                            <template x-for="index in Math.max(0, rows - {{ max(1, is_array($rows) ? count($rows) : 0) }})" :key="index">
                                @include('restaurant.bar._ingredient', ['ingredient' => [], 'index' => null, 'startingRows' => max(1, is_array($rows) ? count($rows) : 0)])
                            </template>
                            <button type="button" @click="rows++" x-show="rows < 30" class="text-sm font-semibold underline">Ajouter un ingrédient</button>
                            <p class="text-xs text-primary/70">Pour une bouteille ouverte, saisissez le volume en mL. L’unité de stock convient, par exemple, à un fruit à la pièce ou à un sirop stocké en litres.</p>
                        </fieldset>
                        <label class="grid gap-1 text-sm">Instructions de préparation
                            <textarea name="instructions" rows="2" maxlength="2000" class="rounded-lg border-secondary/30">{{ $editingThis ? old('instructions') : $recipe?->instructions }}</textarea>
                        </label>
                        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white">Enregistrer la fiche</button>
                    </form>
                @enddroit
            </details>
        @empty
            <p class="rounded-xl bg-white p-4 text-sm text-primary/70">{{ $focused ? 'Cette boisson ne figure plus dans la carte du restaurant.' : 'Ajoutez une boisson à la carte du restaurant pour préparer sa fiche ici.' }}</p>
        @endforelse
        {{ $drinks->links() }}
    </div>
@endif
@endsection
