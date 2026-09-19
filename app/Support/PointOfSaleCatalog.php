<?php

namespace App\Support;

use App\Models\PointOfSale;

/**
 * Points de vente livrés avec l'application.
 *
 * Trois seulement : ceux qui existaient déjà en dur — la réception, le
 * restaurant, la boutique. Ils sont posés pour que rien ne change, pas pour
 * décrire un établissement. Les suivants — un second restaurant, un mini-bar,
 * une activité banquet — s'ajoutent depuis l'écran, sans code.
 */
class PointOfSaleCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'code' => 'H', 'slug' => 'hotel', 'name' => 'Hôtel',
                'kind' => PointOfSale::KIND_HEBERGEMENT, 'series_prefix' => 'H-',
                'is_active' => true, 'sort_order' => 1,
            ],
            [
                'code' => 'RES', 'slug' => 'restaurant', 'name' => 'Restaurant',
                'kind' => PointOfSale::KIND_RESTAURATION, 'series_prefix' => 'RES-',
                'is_active' => true, 'sort_order' => 2,
            ],
            [
                'code' => 'BTQ', 'slug' => 'boutique', 'name' => 'Boutique',
                'kind' => PointOfSale::KIND_BOUTIQUE, 'series_prefix' => 'BTQ-',
                'is_active' => true, 'sort_order' => 3,
            ],
        ];
    }

    /**
     * Crée ce qui manque, met à jour ce qui existe, ne supprime rien.
     *
     * Un point de vente ajouté par l'établissement ne doit pas disparaître
     * parce qu'il n'est pas livré d'origine.
     *
     * @return array{created: int, updated: int}
     */
    public static function sync(): array
    {
        $created = 0;
        $updated = 0;

        foreach (self::all() as $definition) {
            $existant = PointOfSale::where('slug', $definition['slug'])->first();

            if ($existant) {
                // Le nom appartient au client : « Restaurant » peut être devenu
                // « Kotibe ». On ne le réécrit pas.
                $existant->fill(array_diff_key($definition, ['name' => null]))->save();
                $updated++;

                continue;
            }

            PointOfSale::create($definition);
            $created++;
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
