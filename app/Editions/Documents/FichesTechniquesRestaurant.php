<?php

namespace App\Editions\Documents;

use App\Editions\Edition;
use App\Editions\Filtre;
use App\Models\PointOfSale;
use App\Models\RestaurantBarDrink;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantRecipe;
use App\Models\StockItem;
use App\Models\User;
use App\Services\RestaurantContext;
use App\Support\Document\Colonne;
use App\Support\Document\Document;

/**
 * Registre de synthèse de l'ensemble des fiches techniques du restaurant :
 * plats de cuisine, préparations de base et boissons / cocktails du bar.
 */
class FichesTechniquesRestaurant extends Edition
{
    public function cle(): string
    {
        return 'fiches-techniques-restaurant';
    }

    public function famille(): string
    {
        return self::RESTAURATION;
    }

    public function titre(): string
    {
        return 'Fiches techniques de restauration';
    }

    public function description(): string
    {
        return "Registre centralisé de l'ensemble des fiches techniques de l'établissement : plats de cuisine, préparations de base et boissons & cocktails du bar, avec leurs ratios de coût et de vente.";
    }

    public function module(): ?string
    {
        return 'restaurant';
    }

    public function droits(): array
    {
        return [
            'restaurant.recipes.voir',
            'restaurant.bar.voir',
            'restaurant.menus.voir',
            'controller',
            'manager',
        ];
    }

    public function filtres(): array
    {
        return [
            Filtre::choix('restaurant', 'Restaurant', fn (User $u) => app(RestaurantContext::class)->accessibles($u)->pluck('name', 'id')
                ->mapWithKeys(fn ($nom, $id) => [(string) $id => $nom])->all(), 'Tous les restaurants'),
            Filtre::choix('type', 'Rayon / Type', fn () => [
                'dishes' => 'Cuisine — Plats du menu',
                'preps' => 'Cuisine — Préparations de base',
                'drinks' => 'Bar — Boissons & Cocktails',
            ], 'Tous les rayons (Cuisine & Bar)'),
        ];
    }

    public function document(array $valeurs, User $user): Document
    {
        $restoId = $valeurs['restaurant'] !== '' ? (int) $valeurs['restaurant'] : null;
        $typeFiltre = $valeurs['type'] !== '' ? (string) $valeurs['type'] : null;

        $lignes = collect();

        // 1. Fiches de cuisine (Plats & Préparations)
        if ($typeFiltre === null || in_array($typeFiltre, ['dishes', 'preps'], true)) {
            $recipesQuery = RestaurantRecipe::query()
                ->visiblesPour($user)
                ->with(['lines.item', 'menuItem', 'producedItem', 'pointOfSale:id,name'])
                ->orderBy('type')
                ->orderBy('name');

            if ($restoId) {
                $recipesQuery->where('point_of_sale_id', $restoId);
            }

            if ($typeFiltre === 'dishes') {
                $recipesQuery->where('type', RestaurantRecipe::TYPE_DISH);
            } elseif ($typeFiltre === 'preps') {
                $recipesQuery->where('type', RestaurantRecipe::TYPE_PREP);
            }

            foreach ($recipesQuery->get() as $recipe) {
                $unitCost = (int) round($recipe->unitCost());
                $sellingPrice = (int) ($recipe->menuItem?->price ?? 0);
                $foodCost = $recipe->foodCostPercent();
                $margin = $recipe->margin() !== null ? (int) round($recipe->margin()) : null;

                $ingredientsResume = $recipe->lines->map(function ($line) {
                    $nom = $line->item?->name ?? 'Ingrédient';
                    $qte = (float) $line->quantity;
                    $unite = $line->item?->unit ?? '';
                    return "{$nom} ({$qte} {$unite})";
                })->implode(', ');

                $typeLabel = $recipe->type === RestaurantRecipe::TYPE_DISH ? 'Cuisine — Plat' : 'Cuisine — Préparation';
                $rendementLabel = $recipe->yield() . ' ' . ($recipe->type === RestaurantRecipe::TYPE_DISH ? 'portion(s)' : ($recipe->producedItem?->unit ?? 'portion(s)'));

                $lignes->push([
                    'restaurant' => $recipe->pointOfSale?->name ?? 'Général',
                    'rayon' => $typeLabel,
                    'nom' => $recipe->name,
                    'rendement' => $rendementLabel,
                    'cout_matiere' => $unitCost,
                    'prix_vente' => $sellingPrice > 0 ? $sellingPrice : null,
                    'food_cost' => $foodCost !== null ? round($foodCost, 1) . ' %' : '—',
                    'marge' => $margin,
                    'composition' => $ingredientsResume ?: '—',
                ]);
            }
        }

        // 2. Boissons & Cocktails du Bar
        if ($typeFiltre === null || $typeFiltre === 'drinks') {
            $barDrinksQuery = RestaurantMenuItem::query()
                ->visiblesPour($user)
                ->where('type', 'drink')
                ->has('barDrink')
                ->with(['barDrink', 'pointOfSale:id,name'])
                ->orderBy('name');

            if ($restoId) {
                $barDrinksQuery->where('point_of_sale_id', $restoId);
            }

            $stockItems = StockItem::query()->active()->pluck('name', 'id');

            foreach ($barDrinksQuery->get() as $menuItem) {
                $barDrink = $menuItem->barDrink;
                if (!$barDrink) {
                    continue;
                }

                $formatLabel = RestaurantBarDrink::FORMATS[$barDrink->format] ?? $barDrink->format;
                $sellingPrice = (int) $menuItem->price;

                $ingredientsList = collect($barDrink->ingredients ?? [])->map(function ($ing) use ($stockItems) {
                    $stockName = $stockItems[$ing['stock_item_id'] ?? null] ?? 'Article';
                    $qte = $ing['quantity'] ?? '';
                    $unite = ($ing['measure'] ?? '') === 'ml' ? 'mL' : '';
                    return "{$stockName} ({$qte} {$unite})";
                })->filter()->implode(', ');

                $lignes->push([
                    'restaurant' => $menuItem->pointOfSale?->name ?? 'Général',
                    'rayon' => 'Bar — ' . $formatLabel,
                    'nom' => $menuItem->name,
                    'rendement' => '1 verre / unité',
                    'cout_matiere' => null, // Coût bar variable selon bouteille ouverte
                    'prix_vente' => $sellingPrice > 0 ? $sellingPrice : null,
                    'food_cost' => '—',
                    'marge' => null,
                    'composition' => $ingredientsList ?: ($barDrink->instructions ?? '—'),
                ]);
            }
        }

        return $this->base($valeurs, $user, 'Situation au ' . now()->format('d/m/Y à H:i'))
            ->colonnes([
                Colonne::texte('restaurant', 'Restaurant'),
                Colonne::texte('rayon', 'Rayon / Type'),
                Colonne::texte('nom', 'Article / Recette'),
                Colonne::texte('rendement', 'Portion / Unité'),
                Colonne::montant('cout_matiere', 'Coût matière', false),
                Colonne::montant('prix_vente', 'Prix de vente', false),
                Colonne::texte('food_cost', 'Food cost'),
                Colonne::montant('marge', 'Marge brute', false),
                Colonne::texte('composition', 'Ingrédients & Doses'),
            ])
            ->lignes($lignes)
            ->note($lignes->count() . ' fiche(s) technique(s) au catalogue.');
    }
}
