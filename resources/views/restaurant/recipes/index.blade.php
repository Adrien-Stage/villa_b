@extends('layouts.hotel')

@section('title', 'Fiches techniques')

@php
    // Catalogue des ingrédients passé à l'éditeur : il chiffre la fiche en direct,
    // sans aller-retour serveur.
    $pantryCatalog = $pantryItems->map(fn ($item) => [
        'id' => $item->id,
        'name' => $item->name,
        'unit' => $item->unit,
        'cost' => (float) $item->average_cost,
        'stock' => (float) $item->current_stock,
        'prepared' => (bool) $item->is_prepared,
        'category' => $item->category?->name,
    ])->values();
@endphp

@section('content')
<div class="mb-6 flex items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold text-primary font-heading">Fiches techniques</h1>
        <p class="text-sm text-primary/60 mt-1 max-w-3xl">
            La fiche technique décrit ce qu'il faut pour produire un plat. Vendre 5 ndolé sort automatiquement
            les quantités correspondantes du garde-manger, et le coût matière suit le prix réel de vos achats.
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="{{ route('restaurant.recipes.export', ['format' => 'csv']) }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm"
           title="Exporter les fiches à plat, au seul format que l'import sait relire">
            <i data-lucide="file-text" class="w-4 h-4 text-secondary"></i>
            <span>CSV (ré-importable)</span>
        </a>
        <a href="{{ route('restaurant.recipes.export') }}"
           class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm"
           title="Classeur complet : carte & rentabilité, mercuriale, et une fiche par préparation et par plat">
            <i data-lucide="file-spreadsheet" class="w-4 h-4 text-secondary"></i>
            <span>Exporter (Excel)</span>
        </a>
        @if($canManage)
            <button type="button" onclick="document.getElementById('modal-import-recipes').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 border border-secondary/25 bg-white text-primary text-xs font-semibold rounded-lg hover:bg-slate-50 hover:border-secondary/50 transition-colors shadow-sm"
                    title="Importer des fiches techniques depuis un fichier Excel ou CSV">
                <i data-lucide="upload" class="w-4 h-4 text-secondary"></i>
                <span>Importer</span>
            </button>
            <button type="button" onclick="openRecipeEditor()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-surface-dark transition-colors shadow-sm">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Nouvelle fiche
            </button>
        @endif
    </div>
</div>

<x-csv-import-errors />

@if(session('success'))
    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Plats du menu sans fiche : leur vente ne bouge pas le stock --}}
@if($unfichedItems->isNotEmpty())
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4">
        <div class="flex items-start gap-3">
            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 mt-0.5 shrink-0"></i>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-amber-900">
                    {{ $unfichedItems->count() }} plat(s) du menu n'ont pas de fiche technique
                </p>
                <p class="text-xs text-amber-800/80 mt-0.5">
                    Leur vente ne décrémente rien dans le garde-manger et leur marge reste inconnue :
                    {{ $unfichedItems->take(6)->pluck('name')->implode(', ') }}{{ $unfichedItems->count() > 6 ? '…' : '' }}
                </p>
            </div>
        </div>
    </div>
@endif

{{-- Les fiches de plats --}}
<section class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-secondary/20 bg-gray-50/50 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
            <i data-lucide="chef-hat" class="w-4 h-4 text-primary/50"></i>
            Plats ({{ $dishes->count() }})
        </h2>
        <span class="text-[11px] text-primary/40">Coût matière, food cost et marge recalculés à chaque achat</span>
    </div>

    @if($dishes->isEmpty())
        <div class="px-5 py-10 text-center text-primary/40">
            <i data-lucide="book-open" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
            <p class="text-sm">Aucune fiche technique pour l'instant.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Plat</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Ingrédients</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Coût matière</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Prix de vente</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Food cost</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Marge</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Réalisable</th>
                        @if($canManage)
                            <th class="px-4 py-3"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($dishes as $recipe)
                        @php
                            $unitCost = $recipe->unitCost();
                            $foodCost = $recipe->foodCostPercent();
                            $margin = $recipe->margin();
                            $portions = $recipe->menuItem ? $stockService->availablePortions($recipe->menuItem) : null;
                        @endphp
                        <tr class="{{ $recipe->is_active ? '' : 'opacity-60' }}">
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold text-primary">{{ $recipe->name }}</p>
                                <p class="text-[11px] text-primary/40 mt-0.5">
                                    {{ $recipe->menuItem?->name ?? 'Plat supprimé' }}
                                    @if((float) $recipe->yield_quantity != 1)
                                        · rendement {{ rtrim(rtrim(number_format((float) $recipe->yield_quantity, 2, ',', ' '), '0'), ',') }} portions
                                    @endif
                                    @unless($recipe->is_active)
                                        · <span class="text-red-600 font-medium">fiche inactive, stock non déduit</span>
                                    @endunless
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-primary/60">
                                {{ $recipe->lines->count() }} ligne(s)
                                @if($recipe->lines->isNotEmpty())
                                    <span class="block text-[11px] text-primary/40 truncate max-w-xs">
                                        {{ $recipe->lines->take(3)->map(fn ($l) => $l->item?->name)->filter()->implode(', ') }}{{ $recipe->lines->count() > 3 ? '…' : '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-primary whitespace-nowrap">
                                {{ number_format($unitCost / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-primary/70 whitespace-nowrap">
                                {{ $recipe->menuItem ? number_format($recipe->menuItem->price / 100, 0, ',', ' ') . ' FCFA' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($foodCost === null)
                                    <span class="text-xs text-primary/30">—</span>
                                @else
                                    {{-- Au-delà de 35 % le plat mange la marge ; au-delà de 45 % il la détruit. --}}
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                        {{ $foodCost > 45 ? 'bg-red-50 text-red-700 border border-red-200' : ($foodCost > 35 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200') }}">
                                        {{ number_format($foodCost, 1, ',', ' ') }} %
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap {{ $margin !== null && $margin < 0 ? 'text-red-600 font-semibold' : 'text-primary' }}">
                                {{ $margin === null ? '—' : number_format($margin / 100, 0, ',', ' ') . ' FCFA' }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @if($portions === null)
                                    <span class="text-xs text-primary/30">—</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold
                                        {{ $portions <= 0 ? 'bg-red-50 text-red-700 border border-red-200' : ($portions < 5 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-gray-100 text-primary/70') }}">
                                        {{ $portions }} portion(s)
                                    </span>
                                @endif
                            </td>
                            @if($canManage)
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @php
                                            // Charge utile préparée ici : un @json avec tableau inline
                                            // multi-clé casse le parseur Blade selon la version.
                                            $recipePayload = [
                                                'id' => $recipe->id,
                                                'name' => $recipe->name,
                                                'type' => $recipe->type,
                                                'restaurant_menu_item_id' => $recipe->restaurant_menu_item_id,
                                                'produces_pantry_item_id' => $recipe->produces_pantry_item_id,
                                                'yield_quantity' => $recipe->yield_quantity,
                                                'notes' => $recipe->notes,
                                                'is_active' => $recipe->is_active,
                                                'lines' => $recipe->lines->map(fn ($l) => [
                                                    'restaurant_pantry_item_id' => $l->restaurant_pantry_item_id,
                                                    'quantity' => (float) $l->quantity,
                                                    'waste_percent' => (float) $l->waste_percent,
                                                    'notes' => $l->notes,
                                                ])->values(),
                                            ];
                                        @endphp
                                        <button type="button" onclick="openRecipeEditor(@json($recipePayload))"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <form method="POST" action="{{ route('restaurant.recipes.destroy', $recipe) }}"
                                            onsubmit="return confirm('Supprimer la fiche « {{ $recipe->name }} » ? Le plat ne décrémentera plus le stock.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- Les préparations de base --}}
<section class="bg-white rounded-xl shadow-sm border border-secondary/20 overflow-hidden">
    <div class="px-5 py-4 border-b border-secondary/20 bg-gray-50/50">
        <h2 class="text-sm font-semibold text-primary flex items-center gap-2">
            <i data-lucide="cooking-pot" class="w-4 h-4 text-primary/50"></i>
            Préparations de base ({{ $preparations->count() }})
        </h2>
        <p class="text-[11px] text-primary/40 mt-1">
            Une sauce ou un fond préparé en batch. On le produit une fois, il entre en stock à son coût de revient,
            et les plats le consomment comme un ingrédient ordinaire.
        </p>
    </div>

    @if($preparations->isEmpty())
        <div class="px-5 py-10 text-center text-primary/40">
            <i data-lucide="cooking-pot" class="w-10 h-10 mx-auto mb-3 opacity-30"></i>
            <p class="text-sm">Aucune préparation de base.</p>
            @if($canManage)
                <p class="text-xs mt-1">
                    Créez d'abord un article « fabriqué » dans le garde-manger, puis sa fiche ici.
                </p>
            @endif
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-secondary/10">
                <thead class="bg-accent/20">
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Préparation</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-widest text-primary/50">Rendement</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Coût du batch</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Coût unitaire</th>
                        <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-widest text-primary/50">Stock</th>
                        @if($canManage)
                            <th class="px-4 py-3"></th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-secondary/10">
                    @foreach($preparations as $recipe)
                        @php $produced = $recipe->producedItem; @endphp
                        <tr class="{{ $recipe->is_active ? '' : 'opacity-60' }}">
                            <td class="px-4 py-3">
                                <p class="text-sm font-semibold text-primary">{{ $recipe->name }}</p>
                                <p class="text-[11px] text-primary/40 mt-0.5">
                                    {{ $recipe->lines->count() }} ingrédient(s) → {{ $produced?->name ?? 'article supprimé' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-xs text-primary/60 whitespace-nowrap">
                                {{ rtrim(rtrim(number_format((float) $recipe->yield_quantity, 3, ',', ' '), '0'), ',') }} {{ $produced?->unit }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm text-primary/70 whitespace-nowrap">
                                {{ number_format($recipe->totalCost() / 100, 0, ',', ' ') }} FCFA
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold text-primary whitespace-nowrap">
                                {{ number_format($recipe->unitCost() / 100, 2, ',', ' ') }} FCFA / {{ $produced?->unit }}
                            </td>
                            <td class="px-4 py-3 text-right text-sm whitespace-nowrap {{ $produced && $produced->isLowStock() ? 'text-red-600 font-semibold' : 'text-primary/70' }}">
                                {{ $produced ? rtrim(rtrim(number_format((float) $produced->current_stock, 3, ',', ' '), '0'), ',') . ' ' . $produced->unit : '—' }}
                            </td>
                            @if($canManage)
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <form method="POST" action="{{ route('restaurant.recipes.produce', $recipe) }}" class="flex items-center gap-1">
                                            @csrf
                                            <input type="number" name="batches" value="1" min="0.1" step="0.1"
                                                class="w-16 px-2 py-1 text-xs border border-secondary/30 rounded-lg text-primary outline-none focus:border-secondary"
                                                title="Nombre de batchs à produire">
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-primary text-white text-xs font-medium hover:bg-surface-dark transition-colors">
                                                <i data-lucide="cooking-pot" class="w-3.5 h-3.5"></i>
                                                Produire
                                            </button>
                                        </form>
                                        @php
                                            $recipePayload = [
                                                'id' => $recipe->id,
                                                'name' => $recipe->name,
                                                'type' => $recipe->type,
                                                'restaurant_menu_item_id' => $recipe->restaurant_menu_item_id,
                                                'produces_pantry_item_id' => $recipe->produces_pantry_item_id,
                                                'yield_quantity' => $recipe->yield_quantity,
                                                'notes' => $recipe->notes,
                                                'is_active' => $recipe->is_active,
                                                'lines' => $recipe->lines->map(fn ($l) => [
                                                    'restaurant_pantry_item_id' => $l->restaurant_pantry_item_id,
                                                    'quantity' => (float) $l->quantity,
                                                    'waste_percent' => (float) $l->waste_percent,
                                                    'notes' => $l->notes,
                                                ])->values(),
                                            ];
                                        @endphp
                                        <button type="button" onclick="openRecipeEditor(@json($recipePayload))"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-primary/60 hover:text-primary hover:bg-accent/20">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <form method="POST" action="{{ route('restaurant.recipes.destroy', $recipe) }}"
                                            onsubmit="return confirm('Supprimer la fiche « {{ $recipe->name }} » ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@if($canManage)
    {{-- Éditeur de fiche : le coût se calcule pendant la saisie --}}
    <div id="recipe-editor" x-data="recipeEditor(@js($pantryCatalog))" x-show="open" style="display: none;"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        :style="'background: rgba(15,2,1,0.5); backdrop-filter: blur(4px);'">
        <div class="absolute inset-0" @click="open = false"></div>

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl relative z-10 flex flex-col max-h-[92vh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-secondary/20 shrink-0">
                <div>
                    <h3 class="font-heading font-semibold text-primary"
                        x-text="editing ? 'Modifier la fiche technique' : 'Nouvelle fiche technique'"></h3>
                    <p class="text-xs text-primary/50 mt-0.5">
                        Les quantités sont exprimées dans l'unité de stock de chaque ingrédient.
                    </p>
                </div>
                <button type="button" @click="open = false" class="text-primary/30 hover:text-primary transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" :action="formAction" class="flex flex-col flex-1 min-h-0 overflow-hidden">
                @csrf
                <template x-if="editing">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="px-6 py-5 space-y-5 flex-1 overflow-y-auto min-h-0">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1">Nature de la fiche *</label>
                            <select name="type" x-model="form.type" required
                                class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                <option value="dish">Plat du menu</option>
                                <option value="prep">Préparation de base</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1">Nom de la fiche *</label>
                            <input type="text" name="name" x-model="form.name" required maxlength="140"
                                placeholder="Ex : Ndolé aux crevettes"
                                class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary placeholder-primary/30">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div x-show="form.type === 'dish'">
                            <label class="block text-xs font-medium text-primary/70 mb-1">Plat du menu *</label>
                            <select name="restaurant_menu_item_id" x-model="form.restaurant_menu_item_id"
                                class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                <option value="">— Choisir —</option>
                                @foreach($availableMenuItems as $menuItem)
                                    <option value="{{ $menuItem->id }}">{{ $menuItem->name }} ({{ number_format($menuItem->price / 100, 0, ',', ' ') }} FCFA)</option>
                                @endforeach
                                {{-- Le plat déjà rattaché à la fiche en cours d'édition --}}
                                @foreach($dishes as $dish)
                                    @if($dish->menuItem)
                                        <option value="{{ $dish->menuItem->id }}"
                                            x-show="editing && String(form.restaurant_menu_item_id) === '{{ $dish->menuItem->id }}'">
                                            {{ $dish->menuItem->name }} ({{ number_format($dish->menuItem->price / 100, 0, ',', ' ') }} FCFA)
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div x-show="form.type === 'prep'" style="display: none;">
                            <label class="block text-xs font-medium text-primary/70 mb-1">Article fabriqué *</label>
                            <select name="produces_pantry_item_id" x-model="form.produces_pantry_item_id"
                                class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                                <option value="">— Choisir —</option>
                                @foreach($availablePreparedItems as $prepared)
                                    <option value="{{ $prepared->id }}">{{ $prepared->name }} ({{ $prepared->unit }})</option>
                                @endforeach
                                @foreach($preparations as $prep)
                                    @if($prep->producedItem)
                                        <option value="{{ $prep->producedItem->id }}"
                                            x-show="editing && String(form.produces_pantry_item_id) === '{{ $prep->producedItem->id }}'">
                                            {{ $prep->producedItem->name }} ({{ $prep->producedItem->unit }})
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            <p class="text-[11px] text-primary/40 mt-1">
                                L'article doit être marqué « fabriqué en cuisine » dans le garde-manger.
                            </p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-primary/70 mb-1">
                                <span x-show="form.type === 'dish'">Portions produites par la fiche *</span>
                                <span x-show="form.type === 'prep'" style="display: none;">Quantité produite par batch *</span>
                            </label>
                            <input type="number" name="yield_quantity" x-model="form.yield_quantity" min="0.001" step="0.001" required
                                class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary">
                            <p class="text-[11px] text-primary/40 mt-1">
                                <span x-show="form.type === 'dish'">Les quantités saisies ci-dessous valent pour ce nombre de portions.</span>
                                <span x-show="form.type === 'prep'" style="display: none;">Dans l'unité de l'article fabriqué. Ex : 5 000 g de sauce ndolé.</span>
                            </p>
                        </div>
                    </div>

                    {{-- Les ingrédients --}}
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                            <label class="block text-xs font-semibold uppercase tracking-widest text-primary/50">Ingrédients</label>
                            <button type="button" @click="addLine()"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-secondary/20 text-xs font-medium text-primary hover:bg-accent/20">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                Ajouter un ingrédient
                            </button>
                        </div>

                        <div class="border border-secondary/20 rounded-xl overflow-hidden">
                            <div class="hidden md:grid md:grid-cols-12 gap-2 px-3 py-2 bg-gray-50 border-b border-secondary/20 text-[11px] font-semibold uppercase tracking-widest text-primary/40">
                                <div class="col-span-4">Ingrédient</div>
                                <div class="col-span-2 text-right">Quantité</div>
                                <div class="col-span-2 text-right" title="Perte au parage ou à la cuisson">Perte %</div>
                                <div class="col-span-2 text-right">Sortie du stock</div>
                                <div class="col-span-1 text-right">Coût</div>
                                <div class="col-span-1"></div>
                            </div>

                            <template x-for="(line, index) in form.lines" :key="index">
                                <div class="block space-y-1 md:space-y-0 md:grid md:grid-cols-12 gap-2 px-3 py-2 items-center border-b border-secondary/10 last:border-0">
                                    <div class="col-span-4">
                                        <select :name="`lines[${index}][restaurant_pantry_item_id]`" x-model="line.restaurant_pantry_item_id" required
                                            class="w-full rounded-lg border-secondary/20 bg-white text-xs p-2 text-primary">
                                            <option value="">— Choisir —</option>
                                            <template x-for="item in catalog" :key="item.id">
                                                <option :value="item.id"
                                                    x-text="item.name + (item.prepared ? ' (préparation)' : '') + ' · ' + item.unit"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="relative">
                                            <input type="number" :name="`lines[${index}][quantity]`" x-model="line.quantity"
                                                min="0.001" step="0.001" required
                                                class="w-full rounded-lg border-secondary/20 bg-white text-xs p-2 pr-8 text-primary text-right">
                                            <span class="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-primary/40"
                                                x-text="unitOf(line)"></span>
                                        </div>
                                    </div>
                                    <div class="col-span-2">
                                        <input type="number" :name="`lines[${index}][waste_percent]`" x-model="line.waste_percent"
                                            min="0" max="99" step="0.1"
                                            class="w-full rounded-lg border-secondary/20 bg-white text-xs p-2 text-primary text-right">
                                    </div>
                                    <div class="col-span-2 text-right text-xs text-primary/60" x-text="grossOf(line)"></div>
                                    <div class="col-span-1 text-right text-xs font-semibold text-primary" x-text="costOf(line)"></div>
                                    <div class="col-span-1 flex justify-end">
                                        {{-- SVG inline (et non un icône Lucide) : les lignes sont créées
                                             dynamiquement par Alpine, or Lucide ne convertit que les icônes
                                             présentes au chargement — un <i data-lucide> resterait invisible. --}}
                                        <button type="button" @click="removeLine(index)"
                                            title="Retirer cet ingrédient" aria-label="Retirer cet ingrédient"
                                            class="h-7 w-7 inline-flex items-center justify-center rounded-lg border border-secondary/20 text-red-600 hover:bg-red-50 hover:border-red-200 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 6 6 18M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div x-show="form.lines.length === 0" class="px-3 py-6 text-center text-xs text-primary/40">
                                Aucun ingrédient. La vente de ce plat ne décrémentera rien.
                            </div>
                        </div>
                    </div>

                    {{-- Le chiffrage, en direct --}}
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-secondary/20 bg-gray-50 px-4 py-3">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Coût total</p>
                            <p class="text-sm font-bold text-primary mt-1" x-text="format(totalCost) + ' FCFA'"></p>
                        </div>
                        <div class="rounded-xl border border-secondary/20 bg-gray-50 px-4 py-3">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">
                                <span x-show="form.type === 'dish'">Coût / portion</span>
                                <span x-show="form.type === 'prep'" style="display: none;">Coût / unité</span>
                            </p>
                            <p class="text-sm font-bold text-primary mt-1" x-text="format(unitCost) + ' FCFA'"></p>
                        </div>
                        <div class="rounded-xl border border-secondary/20 bg-gray-50 px-4 py-3" x-show="form.type === 'dish'">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Food cost</p>
                            <p class="text-sm font-bold mt-1"
                                :class="foodCost === null ? 'text-primary/30' : (foodCost > 45 ? 'text-red-600' : (foodCost > 35 ? 'text-amber-600' : 'text-green-600'))"
                                x-text="foodCost === null ? '—' : foodCost.toFixed(1).replace('.', ',') + ' %'"></p>
                        </div>
                        <div class="rounded-xl border border-secondary/20 bg-gray-50 px-4 py-3" x-show="form.type === 'dish'">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-primary/40">Marge / portion</p>
                            <p class="text-sm font-bold mt-1"
                                :class="margin === null ? 'text-primary/30' : (margin < 0 ? 'text-red-600' : 'text-primary')"
                                x-text="margin === null ? '—' : format(margin) + ' FCFA'"></p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-primary/70 mb-1">Notes de préparation (optionnel)</label>
                        <textarea name="notes" x-model="form.notes" rows="2" maxlength="2000"
                            placeholder="Temps de cuisson, tour de main, dressage..."
                            class="w-full rounded-lg border-secondary/20 bg-white text-sm p-2.5 text-primary placeholder-primary/30"></textarea>
                    </div>

                    <label class="inline-flex items-center gap-2 text-xs text-primary/70">
                        {{-- Valeur déterministe 1/0 : une case liée en x-model peut soumettre
                             une valeur vide (→ null → inactif). La case reste le contrôle visuel. --}}
                        <input type="hidden" name="is_active" :value="form.is_active ? 1 : 0">
                        <input type="checkbox" x-model="form.is_active" class="rounded border-secondary/30 text-primary">
                        Fiche active (la vente du plat déduit le stock)
                    </label>
                </div>

                <div class="px-6 py-4 border-t border-secondary/20 flex justify-end gap-3 shrink-0 bg-gray-50 rounded-b-2xl">
                    <button type="button" @click="open = false"
                        class="px-4 py-2 text-sm text-primary/60 hover:text-primary transition-colors">Annuler</button>
                    <button type="submit"
                        class="px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-surface-dark transition-colors">
                        <span x-text="editing ? 'Enregistrer la fiche' : 'Créer la fiche'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif

@if($canManage)
    <x-csv-import-modal
        id="modal-import-recipes"
        title="Importer des fiches techniques (Excel / CSV)"
        :action="route('restaurant.recipes.import')"
        :template="route('restaurant.recipes.export', ['template' => 1])"
        structure="nom_fiche;type;plat_menu;article_produit;rendement;notes_fiche;ingredient;quantite;perte_pct;notes_ingredient"
        submit-label="Importer les fiches">
        <li><strong>nom_fiche</strong> obligatoire (ex. <em>Ndolé aux crevettes</em> ou <em>Sauce ndolè</em>)</li>
        <li><strong>type</strong> = <em>plat</em> ou <em>preparation</em>. Pour un plat, <strong>plat_menu</strong> doit correspondre à un plat de la carte existant.</li>
        <li><strong>ingredient</strong> doit correspondre au nom d'un article présent dans le garde-manger.</li>
    </x-csv-import-modal>
@endif
@endsection

@push('scripts')
<script>
    // Ouvre l'éditeur depuis les boutons de la page (hors portée Alpine du composant).
    function openRecipeEditor(recipe) {
        window.dispatchEvent(new CustomEvent('recipe-editor:open', { detail: recipe ?? null }));
    }

    document.addEventListener('alpine:init', () => {
        Alpine.data('recipeEditor', (catalog) => ({
            catalog,
            open: false,
            editing: false,
            formAction: @js(route('restaurant.recipes.store')),
            baseUrl: @js(url('/restaurant/recipes')),
            form: {},

            init() {
                this.form = this.blank();

                window.addEventListener('recipe-editor:open', (event) => {
                    const recipe = event.detail;

                    if (recipe) {
                        this.form = {
                            type: recipe.type,
                            name: recipe.name ?? '',
                            restaurant_menu_item_id: recipe.restaurant_menu_item_id ?? '',
                            produces_pantry_item_id: recipe.produces_pantry_item_id ?? '',
                            yield_quantity: parseFloat(recipe.yield_quantity) || 1,
                            notes: recipe.notes ?? '',
                            is_active: !!recipe.is_active,
                            lines: (recipe.lines ?? []).map((line) => ({
                                restaurant_pantry_item_id: String(line.restaurant_pantry_item_id),
                                quantity: line.quantity,
                                waste_percent: line.waste_percent ?? 0,
                                notes: line.notes ?? '',
                            })),
                        };
                        this.editing = true;
                        this.formAction = `${this.baseUrl}/${recipe.id}`;
                    } else {
                        this.form = this.blank();
                        this.editing = false;
                        this.formAction = @js(route('restaurant.recipes.store'));
                    }

                    this.open = true;
                    this.$nextTick(() => window.lucide?.createIcons());
                });
            },

            blank() {
                return {
                    type: 'dish',
                    name: '',
                    restaurant_menu_item_id: '',
                    produces_pantry_item_id: '',
                    yield_quantity: 1,
                    notes: '',
                    is_active: true,
                    lines: [],
                };
            },

            addLine() {
                this.form.lines.push({
                    restaurant_pantry_item_id: '',
                    quantity: 0,
                    waste_percent: 0,
                    notes: '',
                });
                this.$nextTick(() => window.lucide?.createIcons());
            },

            removeLine(index) {
                this.form.lines.splice(index, 1);
            },

            itemOf(line) {
                return this.catalog.find((item) => String(item.id) === String(line.restaurant_pantry_item_id)) ?? null;
            },

            unitOf(line) {
                return this.itemOf(line)?.unit ?? '';
            },

            // La perte au parage majore la quantité réellement sortie du stock.
            grossQuantity(line) {
                const net = parseFloat(line.quantity) || 0;
                const waste = parseFloat(line.waste_percent) || 0;

                if (waste <= 0 || waste >= 100) return net;

                return net / (1 - waste / 100);
            },

            grossOf(line) {
                const item = this.itemOf(line);
                if (!item) return '—';

                const gross = this.grossQuantity(line);

                return `${gross.toLocaleString('fr-FR', { maximumFractionDigits: 3 })} ${item.unit}`;
            },

            lineCost(line) {
                const item = this.itemOf(line);
                if (!item) return 0;

                return this.grossQuantity(line) * item.cost;
            },

            costOf(line) {
                const item = this.itemOf(line);
                if (!item) return '—';

                if (item.cost <= 0) return 'coût ?';

                return this.format(this.lineCost(line));
            },

            get totalCost() {
                return this.form.lines.reduce((sum, line) => sum + this.lineCost(line), 0);
            },

            get yieldValue() {
                const value = parseFloat(this.form.yield_quantity) || 0;

                return value > 0 ? value : 1;
            },

            get unitCost() {
                return this.totalCost / this.yieldValue;
            },

            // Prix de vente des plats, pour chiffrer le food cost pendant la saisie.
            menuPrices: @js(
                $availableMenuItems
                    ->concat($dishes->pluck('menuItem')->filter())
                    ->mapWithKeys(fn ($item) => [(string) $item->id => (int) $item->price])
            ),

            get sellingPrice() {
                if (this.form.type !== 'dish' || !this.form.restaurant_menu_item_id) return null;

                return this.menuPrices[String(this.form.restaurant_menu_item_id)] ?? null;
            },

            get foodCost() {
                const price = this.sellingPrice;
                if (!price || price <= 0) return null;

                return this.unitCost / price * 100;
            },

            get margin() {
                const price = this.sellingPrice;
                if (!price || price <= 0) return null;

                return price - this.unitCost;
            },

            // Les montants sont en centimes FCFA : on affiche en FCFA.
            format(amountInCents) {
                return (amountInCents / 100).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
            },
        }));
    });
</script>
@endpush
