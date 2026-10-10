<?php

namespace App\Models\Concerns;

use App\Support\Conditionnement;

/**
 * Une ligne saisie dans un conditionnement de l'article (cartons, paquets) :
 * ses quantités restent dans l'unité de l'article, et se relisent dans le
 * conditionnement saisi — « 10 cartons (2 000 pièces) ».
 *
 * Le modèle porte packaging_name et une relation item vers StockItem.
 */
trait SaisiEnConditionnement
{
    public function enConditionnement(float $quantite): string
    {
        $unite = (string) ($this->item?->unit ?? '');
        $base = Conditionnement::libelle($quantite, $unite);

        $niveau = $this->packaging_name
            ? $this->item?->packagings?->firstWhere('name', $this->packaging_name)
            : null;
        if ($niveau === null) {
            return $base;
        }

        return Conditionnement::libelle(round($quantite / (float) $niveau->factor, 3), $niveau->name) . " ({$base})";
    }
}
