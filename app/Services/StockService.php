<?php

namespace App\Services;

use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Notifications\StockItemBelowThreshold;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Moteur unique des mouvements de stock de l'économat.
 *
 * Toute variation passe par ce service : c'est lui qui journalise le mouvement,
 * met à jour le stock courant et recalcule le coût moyen pondéré. Le concentrer
 * ici évite que chaque contrôleur réinvente — et fasse diverger — cette logique.
 *
 * Montants en centimes FCFA, quantités en décimal.
 */
class StockService
{
    /**
     * Entrée en stock (réception fournisseur, retour, correction positive).
     *
     * Recalcule le coût moyen pondéré : nouvelle valeur = (valeur existante +
     * valeur reçue) / quantité totale. C'est la moyenne pondérée classique,
     * qui lisse les variations de prix d'achat successives.
     */
    public function recordIn(
        StockItem $item,
        float $quantity,
        int $unitCost,
        string $sourceType = StockMovement::SOURCE_MANUAL,
        ?int $sourceId = null,
        ?string $reason = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité entrée doit être positive.');
        }

        return DB::transaction(function () use ($item, $quantity, $unitCost, $sourceType, $sourceId, $reason) {
            // Verrou pessimiste : deux réceptions simultanées du même article ne
            // doivent pas se baser sur le même stock de départ.
            $item = StockItem::lockForUpdate()->find($item->id);
            $this->ensureStoreNotFrozen($sourceType);

            $currentQty   = (float) $item->current_stock;
            $currentValue = $currentQty * $item->average_cost;
            $incomingValue = $quantity * $unitCost;
            $newQty       = $currentQty + $quantity;

            $newAverage = $newQty > 0
                ? (int) round(($currentValue + $incomingValue) / $newQty)
                : $unitCost;

            $item->update([
                'current_stock'       => $newQty,
                'average_cost'        => $newAverage,
                'last_purchase_price' => $unitCost,
            ]);

            return $this->log($item, StockMovement::TYPE_IN, $quantity, $unitCost, $sourceType, $sourceId, $reason);
        });
    }

    /**
     * Reprise du stock initial : la marchandise déjà en magasin au démarrage
     * du module, avec la valeur qu'elle avait.
     *
     * Elle n'est permise que sur un article sans aucun mouvement : c'est le
     * point de départ de son historique, pas une correction. Une fois le stock
     * vivant, un écart se traite par ajustement ou inventaire.
     *
     * Le coût est exigé : un stock repris à zéro fausserait le CUMP de toutes
     * les entrées suivantes. Au grand livre, cette valeur entre par les
     * à-nouveaux du comptable, pas par le night audit.
     */
    public function recordOpening(StockItem $item, float $quantity, int $unitCost, ?string $reason = null): StockMovement
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité reprise doit être positive.');
        }

        if ($unitCost <= 0) {
            throw new \InvalidArgumentException('Le coût unitaire du stock repris est obligatoire.');
        }

        return DB::transaction(function () use ($item, $quantity, $unitCost, $reason) {
            $item = StockItem::lockForUpdate()->find($item->id);
            $this->ensureStoreNotFrozen(StockMovement::SOURCE_OPENING);

            if ($item->movements()->exists()) {
                throw new \RuntimeException(
                    "« {$item->name} » a déjà des mouvements : son stock se corrige par ajustement ou inventaire, pas par une reprise."
                );
            }

            $item->update([
                'current_stock'       => $quantity,
                'average_cost'        => $unitCost,
                'last_purchase_price' => $item->last_purchase_price ?: $unitCost,
            ]);

            return $this->log(
                $item,
                StockMovement::TYPE_IN,
                $quantity,
                $unitCost,
                StockMovement::SOURCE_OPENING,
                null,
                $reason ?? 'Reprise du stock initial'
            );
        });
    }

    /**
     * Sortie de stock (livraison à un département, perte, correction négative).
     * La sortie est valorisée au coût moyen courant, jamais au dernier prix.
     */
    public function recordOut(
        StockItem $item,
        float $quantity,
        string $sourceType = StockMovement::SOURCE_MANUAL,
        ?int $sourceId = null,
        ?string $reason = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité sortie doit être positive.');
        }

        // Renseigné dans la transaction, consommé après : l'alerte ne part
        // qu'une fois le déstockage réellement acquis en base.
        $crossedThreshold = null;

        $movement = DB::transaction(function () use ($item, $quantity, $sourceType, $sourceId, $reason, &$crossedThreshold) {
            $item = StockItem::lockForUpdate()->find($item->id);
            $this->ensureStoreNotFrozen($sourceType);

            // On ne sort jamais plus que ce qui est présent : un stock négatif
            // n'a pas de sens physique et fausserait la valorisation.
            if ((float) $item->current_stock < $quantity) {
                throw new \RuntimeException(
                    "Stock insuffisant pour « {$item->name} » : "
                    . "{$item->current_stock} {$item->unit} disponible(s), {$quantity} demandé(s)."
                );
            }

            $wasAboveThreshold = !$item->isBelowThreshold();
            $newQty = (float) $item->current_stock - $quantity;
            $item->update(['current_stock' => $newQty]);

            // Seul le franchissement déclenche l'alerte : sans ça, chaque sortie
            // sur un article déjà bas renotifierait l'économe pour rien.
            if ($wasAboveThreshold && $item->isBelowThreshold()) {
                $crossedThreshold = $item;
            }

            return $this->log($item, StockMovement::TYPE_OUT, -$quantity, $item->average_cost, $sourceType, $sourceId, $reason);
        });

        if ($crossedThreshold) {
            // afterCommit : ce service peut être appelé depuis une transaction
            // englobante (livraison d'une demande). On n'alerte pas sur un
            // déstockage qui finirait par être annulé.
            DB::afterCommit(fn () => app(Notifier::class)
                ->toRoles(['econome', 'manager'], new StockItemBelowThreshold($crossedThreshold)));
        }

        return $movement;
    }

    /**
     * Contre-passation d'une entrée (annulation de réception).
     *
     * La sortie se fait au coût de l'entrée annulée, pas au CUMP : on retire
     * du stock exactement la valeur qu'on y avait ajoutée, et le CUMP revient
     * à ce qu'il aurait été sans cette réception. Sortir au CUMP laisserait
     * le prix du lot annulé dilué dans le coût des articles restants.
     */
    public function reverseIn(
        StockItem $item,
        float $quantity,
        int $unitCost,
        string $sourceType,
        ?int $sourceId = null,
        ?string $reason = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité contre-passée doit être positive.');
        }

        return DB::transaction(function () use ($item, $quantity, $unitCost, $sourceType, $sourceId, $reason) {
            $item = StockItem::lockForUpdate()->find($item->id);
            $this->ensureStoreNotFrozen($sourceType);

            // Ce qui a déjà été consommé ne peut pas être rendu au fournisseur.
            if ((float) $item->current_stock < $quantity) {
                throw new \RuntimeException(
                    "Stock insuffisant pour contre-passer « {$item->name} » : "
                    . "{$item->current_stock} {$item->unit} disponible(s), {$quantity} à retirer. "
                    . 'Une partie a déjà été servie.'
                );
            }

            $currentQty = (float) $item->current_stock;
            $remainingQty = $currentQty - $quantity;
            $remainingValue = $currentQty * $item->average_cost - $quantity * $unitCost;

            // Si les sorties intermédiaires ont été valorisées plus haut que le
            // lot annulé, la valeur restante peut devenir négative : un coût
            // négatif n'a pas de sens, on le borne à zéro.
            $newAverage = $remainingQty > 0
                ? max(0, (int) round($remainingValue / $remainingQty))
                : $item->average_cost;

            $item->update([
                'current_stock' => $remainingQty,
                'average_cost'  => $newAverage,
            ]);

            return $this->log($item, StockMovement::TYPE_OUT, -$quantity, $unitCost, $sourceType, $sourceId, $reason);
        });
    }

    /**
     * Contre-passation d'une sortie (annulation d'un bon de sortie) : la
     * marchandise revient au coût auquel elle était sortie. Le CUMP se
     * recalcule comme pour une entrée, mais le dernier prix d'achat ne bouge
     * pas : ce retour n'est pas un achat.
     */
    public function reverseOut(
        StockItem $item,
        float $quantity,
        int $unitCost,
        string $sourceType,
        ?int $sourceId = null,
        ?string $reason = null
    ): StockMovement {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité contre-passée doit être positive.');
        }

        return DB::transaction(function () use ($item, $quantity, $unitCost, $sourceType, $sourceId, $reason) {
            $item = StockItem::lockForUpdate()->find($item->id);
            $this->ensureStoreNotFrozen($sourceType);

            $courant = (float) $item->current_stock;
            $nouveau = $courant + $quantity;

            $item->update([
                'current_stock' => $nouveau,
                'average_cost'  => $nouveau > 0
                    ? (int) round(($courant * $item->average_cost + $quantity * $unitCost) / $nouveau)
                    : $unitCost,
            ]);

            return $this->log($item, StockMovement::TYPE_IN, $quantity, $unitCost, $sourceType, $sourceId, $reason);
        });
    }

    /**
     * Ajustement d'inventaire : fixe le stock à une quantité constatée. Sert à
     * caler la base sur un comptage physique. Positif ou négatif selon l'écart.
     */
    public function adjust(
        StockItem $item,
        float $countedQuantity,
        ?string $reason = null,
        string $sourceType = StockMovement::SOURCE_MANUAL,
        ?int $sourceId = null
    ): ?StockMovement {
        if ($countedQuantity < 0) {
            throw new \InvalidArgumentException('La quantité constatée ne peut pas être négative.');
        }

        $crossedThreshold = null;

        $movement = DB::transaction(function () use ($item, $countedQuantity, $reason, $sourceType, $sourceId, &$crossedThreshold) {
            $item = StockItem::lockForUpdate()->find($item->id);
            $this->ensureStoreNotFrozen($sourceType);
            $delta = $countedQuantity - (float) $item->current_stock;

            if (abs($delta) < 0.0005) {
                return null;   // Rien à corriger.
            }

            $wasAboveThreshold = !$item->isBelowThreshold();
            $item->update(['current_stock' => $countedQuantity]);

            // Un comptage physique révèle souvent un manque (casse, perte) :
            // c'est aussi un moment où l'économe doit être prévenu.
            if ($wasAboveThreshold && $item->isBelowThreshold()) {
                $crossedThreshold = $item;
            }

            return $this->log(
                $item,
                StockMovement::TYPE_ADJUSTMENT,
                $delta,
                $item->average_cost,
                $sourceType,
                $sourceId,
                $reason ?? 'Ajustement d\'inventaire'
            );
        });

        if ($crossedThreshold) {
            // afterCommit : ce service peut être appelé depuis une transaction
            // englobante (livraison d'une demande). On n'alerte pas sur un
            // déstockage qui finirait par être annulé.
            DB::afterCommit(fn () => app(Notifier::class)
                ->toRoles(['econome', 'manager'], new StockItemBelowThreshold($crossedThreshold)));
        }

        return $movement;
    }

    /**
     * Inventaire en cours : le magasin est gelé. Le théorique a été relevé à
     * l'ouverture ; un mouvement passé pendant le comptage fausserait l'écart
     * que la clôture va appliquer. Seule la clôture elle-même peut écrire.
     *
     * Vérifié sous le verrou de l'article : un inventaire ouvert pendant un
     * mouvement attend la fin de celui-ci pour relever son théorique.
     */
    private function ensureStoreNotFrozen(string $sourceType): void
    {
        if ($sourceType === StockMovement::SOURCE_STOCK_COUNT) {
            return;
        }

        $inventaire = StockCount::inProgress();

        if ($inventaire !== null) {
            throw new \RuntimeException(
                "Inventaire {$inventaire->reference} en cours : aucun mouvement de stock n'est permis "
                . "avant sa clôture ou son annulation."
            );
        }
    }

    private function log(
        StockItem $item,
        string $type,
        float $signedQuantity,
        int $unitCost,
        string $sourceType,
        ?int $sourceId,
        ?string $reason
    ): StockMovement {
        return StockMovement::create([
            'stock_item_id' => $item->id,
            'type'          => $type,
            'quantity'      => $signedQuantity,
            // Le stock initial du mouvement : pour un ajustement, le stock
            // avant comptage ; le stock après est le stock compté.
            'stock_before'  => round((float) $item->current_stock - $signedQuantity, 3),
            'stock_after'   => $item->current_stock,
            'unit_cost'     => $unitCost,
            'stock_account' => $item->stockAccount(),
            'source_type'   => $sourceType,
            'source_id'     => $sourceId,
            'reason'        => $reason,
            'user_id'       => Auth::id(),
            'occurred_at'   => now(),
            'tenant_id'     => $item->tenant_id,
        ]);
    }
}
