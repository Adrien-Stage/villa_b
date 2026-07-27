<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;

/**
 * Fiche technique d'un type de chambre : calcule le coût variable par nuitée
 * occupée et la marge sur une chambre louée (marge de contribution).
 *
 * Approche « cost per occupied room » : on somme les postes variables
 * (électricité, eau, consommables, blanchisserie, ménage…) ramenés à une
 * nuitée, puis on les compare au prix réellement pratiqué (ADR).
 *
 * Tous les montants sont en centimes FCFA.
 */
class RoomCostingService
{
    /**
     * Fiche complète d'un type de chambre : hypothèses, lignes groupées par
     * catégorie, coût variable, prix de référence et marges.
     */
    public function sheetFor(RoomType $roomType): array
    {
        $sheet = RoomCostSheet::firstOrNew(['room_type_id' => $roomType->id]);

        // Occupants de référence : valeur de la fiche, sinon capacité de base.
        $occupants = $sheet->reference_occupants ?: max(1, (int) $roomType->base_capacity);
        // Durée moyenne de séjour : valeur de la fiche, sinon mesurée sur les
        // réservations réelles, avec repli à 1 nuit.
        $avgNights = $sheet->avg_length_of_stay
            ? (float) $sheet->avg_length_of_stay
            : $this->realizedAverageNights($roomType);

        $items = RoomCostItem::with('stockItem')
            ->where('room_type_id', $roomType->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        // Regroupement par catégorie avec sous-total, dans l'ordre des catégories.
        $groups = [];
        $variableCost = 0.0;
        foreach (RoomCostItem::CATEGORIES as $key => $label) {
            $lines = $items->where('category', $key)->map(function (RoomCostItem $item) use ($occupants, $avgNights) {
                $perNight = $item->costPerNight($occupants, $avgNights);
                return [
                    'id'          => $item->id,
                    'category'    => $item->category,
                    'label'       => $item->label,
                    'basis'       => $item->basis,
                    'basis_label' => $item->basisLabel(),
                    'quantity'    => (float) $item->quantity,
                    'unit_cost'   => $item->effectiveUnitCost(),
                    // Prix saisi (en FCFA) pour pré-remplir le formulaire d'édition,
                    // distinct du coût effectif qui peut venir de l'économat.
                    'raw_unit_cost_fcfa' => (int) ($item->unit_cost / 100),
                    'linked'      => (bool) $item->stock_item_id,
                    'stock_item_id' => $item->stock_item_id,
                    'stock_name'  => $item->stockItem?->name,
                    'notes'       => $item->notes,
                    'per_night'   => $perNight,
                ];
            })->values();

            if ($lines->isEmpty()) {
                continue;
            }

            $subtotal = $lines->sum('per_night');
            $variableCost += $subtotal;

            $groups[] = [
                'key'      => $key,
                'label'    => $label,
                'subtotal' => $subtotal,
                'lines'    => $lines->all(),
            ];
        }

        $basePrice   = (int) $roomType->base_price;
        $realizedAdr = $this->realizedAdr($roomType);
        // Prix de référence pour la marge : l'ADR réellement encaissé prime ;
        // sans historique de vente, on retombe sur le prix de base configuré.
        $referencePrice = $realizedAdr ?? $basePrice;

        $fixedCost = (int) $sheet->fixed_cost_per_night;

        $contributionMargin = $referencePrice - $variableCost;
        $netMargin          = $contributionMargin - $fixedCost;

        // Sans aucun poste saisi, le coût variable vaut zéro : afficher « 100 %
        // de marge » serait faussement rassurant. On signale donc une fiche non
        // configurée et on n'expose aucun pourcentage tant qu'elle l'est.
        $isConfigured = $items->isNotEmpty();
        $canComputeRatios = $isConfigured && $referencePrice > 0;

        return [
            'room_type'   => $roomType,
            'is_configured' => $isConfigured,
            'assumptions' => [
                'reference_occupants'  => $occupants,
                'avg_length_of_stay'   => round($avgNights, 2),
                'avg_nights_is_manual' => $sheet->avg_length_of_stay !== null,
                'fixed_cost_per_night' => $fixedCost,
                'notes'                => $sheet->notes,
            ],
            'groups'         => $groups,
            'variable_cost'  => $variableCost,
            'fixed_cost'     => $fixedCost,
            'total_cost'     => $variableCost + $fixedCost,
            'base_price'     => $basePrice,
            'realized_adr'   => $realizedAdr,
            'reference_price' => $referencePrice,
            'reference_is_realized' => $realizedAdr !== null,
            'contribution_margin' => $contributionMargin,
            'contribution_pct'    => $canComputeRatios ? round($contributionMargin / $referencePrice * 100, 1) : null,
            'cost_ratio'          => $canComputeRatios ? round($variableCost / $referencePrice * 100, 1) : null,
            'net_margin'          => $netMargin,
            'net_margin_pct'      => $canComputeRatios ? round($netMargin / $referencePrice * 100, 1) : null,
        ];
    }

    /**
     * Résumé léger pour la liste des types de chambre : coût variable, prix de
     * référence et marge, sans le détail des lignes.
     */
    public function summaryFor(RoomType $roomType): array
    {
        $full = $this->sheetFor($roomType);

        return [
            'is_configured'       => $full['is_configured'],
            'variable_cost'       => $full['variable_cost'],
            'reference_price'     => $full['reference_price'],
            'reference_is_realized' => $full['reference_is_realized'],
            'contribution_margin' => $full['contribution_margin'],
            'contribution_pct'    => $full['contribution_pct'],
            'line_count'          => collect($full['groups'])->sum(fn ($g) => count($g['lines'])),
        ];
    }

    /**
     * Prix moyen réellement pratiqué par nuitée (ADR) pour ce type de chambre,
     * sur les réservations non annulées. Null si aucune vente.
     */
    private function realizedAdr(RoomType $roomType): ?int
    {
        $avg = Booking::query()
            ->whereHas('room', fn ($q) => $q->where('room_type_id', $roomType->id))
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            ->where('price_per_night', '>', 0)
            ->avg('price_per_night');

        return $avg ? (int) round($avg) : null;
    }

    /**
     * Durée moyenne de séjour mesurée sur les réservations de ce type, repli
     * à 1 nuit si l'historique est vide.
     */
    private function realizedAverageNights(RoomType $roomType): float
    {
        $avg = Booking::query()
            ->whereHas('room', fn ($q) => $q->where('room_type_id', $roomType->id))
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            ->where('total_nights', '>', 0)
            ->avg('total_nights');

        return $avg ? max(1.0, (float) $avg) : 1.0;
    }
}
