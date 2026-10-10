<?php

namespace App\Services;

use App\Models\PointOfSale;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\StockCut;
use App\Models\StockCutLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Conditionnement;
use Illuminate\Support\Facades\DB;

/**
 * Découpe d'un article de l'économat vers le garde-manger d'un restaurant.
 *
 * La quantité prise sort de l'économat au coût moyen. Elle se répartit en
 * portions, chacune pesée dans l'unité de l'article ; la valeur prise se
 * partage au poids entre toutes les portions, carcasses comprises. Ce qui
 * n'est pas réparti (freinte) ne reçoit rien : son coût se reporte sur les
 * portions gardées.
 */
class StockCutService
{
    /** Unités convertibles entre elles, ramenées à la plus petite de leur famille. */
    private const FAMILLES = [
        'kg' => ['masse', 1000.0], 'g' => ['masse', 1.0],
        'l' => ['volume', 1000.0], 'litre' => ['volume', 1000.0], 'cl' => ['volume', 10.0], 'ml' => ['volume', 1.0],
    ];

    public function __construct(private StockService $stock, private RestaurantStockService $cuisine)
    {
    }

    /**
     * @param  array{point_of_sale_id: int, quantity: float|string, notes?: ?string,
     *               lines: list<array{pantry_item_id?: ?int, nom?: ?string, quantity: float|string}>}  $data
     */
    public function decouper(StockItem $item, array $data, User $user): StockCut
    {
        $quantite = round((float) $data['quantity'], 3);
        if ($quantite <= 0) {
            throw new \InvalidArgumentException('Indiquez la quantité prise.');
        }

        $restaurant = PointOfSale::query()->restaurants()->find((int) $data['point_of_sale_id']);
        if ($restaurant === null || !$restaurant->offre(PointOfSale::SERVICE_STOCK)) {
            throw new \InvalidArgumentException("Ce restaurant n'a pas de garde-manger.");
        }

        $lignes = array_values(array_filter($data['lines'] ?? [], fn ($l) => (float) ($l['quantity'] ?? 0) > 0));
        if ($lignes === []) {
            throw new \InvalidArgumentException('Indiquez au moins une portion et sa quantité.');
        }

        $reparti = round(array_sum(array_map(fn ($l) => (float) $l['quantity'], $lignes)), 3);
        if ($reparti > $quantite + Conditionnement::EPSILON) {
            throw new \InvalidArgumentException(
                'Les portions font ' . Conditionnement::libelle($reparti, $item->unit)
                . ', plus que les ' . Conditionnement::libelle($quantite, $item->unit) . ' pris.'
            );
        }

        return DB::transaction(function () use ($item, $quantite, $restaurant, $lignes, $reparti, $data, $user) {
            // Les portions d'abord : une portion introuvable ou d'une unité
            // incompatible arrête tout avant que l'économat ne bouge.
            $portions = [];
            foreach ($lignes as $ligne) {
                $article = $this->portion($ligne, $restaurant, $item);
                $portions[] = [$article, round((float) $ligne['quantity'], 3), $this->conversion($item->unit, $article->unit, $article->name)];
            }

            $decoupe = StockCut::create([
                'stock_item_id'    => $item->id,
                'point_of_sale_id' => $restaurant->id,
                'quantity'         => $quantite,
                'unit_cost'        => 0,
                'total_value'      => 0,
                'notes'            => trim((string) ($data['notes'] ?? '')) ?: null,
                'cut_by'           => $user->id,
                'tenant_id'        => $item->tenant_id ?? $user->tenant_id,
            ]);

            $sortie = $this->stock->recordOut(
                $item,
                $quantite,
                StockMovement::SOURCE_CUT,
                $decoupe->id,
                "Découpe {$decoupe->number} — {$restaurant->name}"
            );

            $valeur = (int) round($quantite * (int) $sortie->unit_cost);
            $decoupe->update(['unit_cost' => (int) $sortie->unit_cost, 'total_value' => $valeur]);

            // Au poids : chaque portion reçoit sa part de la valeur prise ; la
            // dernière prend l'arrondi, pour que le total tombe juste.
            $distribue = 0;
            foreach ($portions as $i => [$article, $poids, $conversion]) {
                $part = $i === count($portions) - 1
                    ? $valeur - $distribue
                    : (int) round($valeur * $poids / $reparti);
                $distribue += $part;

                $quantiteCuisine = round($poids * $conversion, 3);
                $coutUnitaire = $quantiteCuisine > 0 ? $part / $quantiteCuisine : 0.0;

                StockCutLine::create([
                    'stock_cut_id'              => $decoupe->id,
                    'restaurant_pantry_item_id' => $article->id,
                    'label'                     => $article->name,
                    'quantity'                  => $poids,
                    'pantry_quantity'           => $quantiteCuisine,
                    'value'                     => $part,
                    'unit_cost'                 => round($coutUnitaire, 4),
                ]);

                // Un transfert interne : la charge naîtra à la consommation.
                $this->cuisine->recordMovement(
                    item: $article,
                    type: RestaurantPantryMovement::TYPE_IN,
                    quantity: $quantiteCuisine,
                    reason: RestaurantPantryMovement::REASON_TRANSFER_IN,
                    unitCost: $coutUnitaire,
                    notes: "Découpe {$decoupe->number} — " . Conditionnement::libelle($poids, $item->unit) . " de {$item->name}",
                );
            }

            return $decoupe->load('lines', 'restaurant', 'item');
        });
    }

    /** La portion désignée dans le garde-manger du restaurant, ou créée pour l'occasion. */
    private function portion(array $ligne, PointOfSale $restaurant, StockItem $item): RestaurantPantryItem
    {
        if (!empty($ligne['pantry_item_id'])) {
            $article = RestaurantPantryItem::query()->duRestaurant($restaurant)->find((int) $ligne['pantry_item_id']);
            if ($article === null) {
                throw new \InvalidArgumentException("Une portion n'appartient pas au garde-manger de « {$restaurant->name} ».");
            }

            return $article;
        }

        $nom = trim((string) ($ligne['nom'] ?? ''));
        if ($nom === '') {
            throw new \InvalidArgumentException('Chaque portion doit avoir un nom ou un article du garde-manger.');
        }

        // Une portion du même nom existe déjà dans ce garde-manger : on la reprend.
        $existant = RestaurantPantryItem::query()->duRestaurant($restaurant)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($nom)])->first();
        if ($existant !== null) {
            return $existant;
        }

        return RestaurantPantryItem::create([
            'point_of_sale_id'              => $restaurant->id,
            'restaurant_pantry_category_id' => RestaurantPantryCategory::query()->value('id'),
            'name'                          => $nom,
            'unit'                          => $item->unit,
            'purchase_unit'                 => $item->unit,
            'purchase_conversion'           => 1.0,
            'current_stock'                 => 0,
            'min_stock'                     => 0,
            'cost_price'                    => 0,
            'average_cost'                  => 0,
            'is_prepared'                   => false,
            'is_active'                     => true,
        ]);
    }

    /** Unités du garde-manger dans une unité de l'article : 1 000 g dans 1 kg. */
    private function conversion(string $depuis, string $vers, string $portion): float
    {
        $a = mb_strtolower(trim($depuis));
        $b = mb_strtolower(trim($vers));
        if ($a === $b) {
            return 1.0;
        }

        [$familleA, $valeurA] = self::FAMILLES[$a] ?? [null, null];
        [$familleB, $valeurB] = self::FAMILLES[$b] ?? [null, null];
        if ($familleA !== null && $familleA === $familleB) {
            return $valeurA / $valeurB;
        }

        throw new \InvalidArgumentException(
            "« {$portion} » est suivi en {$vers} au garde-manger : on ne peut pas y verser des {$depuis}."
        );
    }
}
