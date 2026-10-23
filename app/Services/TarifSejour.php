<?php

namespace App\Services;

use App\Models\RoomRate;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Le prix d'un séjour d'après la grille tarifaire (Hôtel › Tarifs), nuit par
 * nuit.
 *
 * Chaque nuit prend le prix de base du type de chambre, ou celui du tarif par
 * critère qui s'y applique : la plus haute priorité saisie l'emporte, puis
 * l'ordre événement, saison, jours de la semaine, long séjour, dernière
 * minute (RoomRate::TYPES). Une seule règle par nuit : elles ne se cumulent
 * pas. La surcharge de capacité vient ensuite, comme sur le prix de base.
 *
 * Le séjour se facture au tarif moyen de ses nuits (Booking::price_per_night).
 * Ce total sert de tarif de référence : la réception le négocie dans la
 * limite de remise de l'établissement, partenaires et packs s'en déduisent.
 *
 * Montants en centimes, chaque nuit arrondie au franc.
 */
class TarifSejour
{
    /**
     * @return array{
     *     total: int,
     *     par_nuit: int,
     *     base: int,
     *     ajuste: bool,
     *     nuits: list<array{date: string, prix: int, tarif: ?string, type: ?string}>
     * }
     */
    public function calculer(
        RoomType $type,
        CarbonInterface $arrivee,
        CarbonInterface $depart,
        int $adultes = 1,
        int $enfants = 0,
        ?CarbonInterface $reserveLe = null,
    ): array {
        $arrivee = CarbonImmutable::parse($arrivee)->startOfDay();
        $depart = CarbonImmutable::parse($depart)->startOfDay();
        $nuits = max(0, (int) $arrivee->diffInDays($depart));
        $avance = max(0, (int) CarbonImmutable::parse($reserveLe ?? now())->startOfDay()->diffInDays($arrivee, false));

        $base = $this->auFranc($type->avecSurchargeDeCapacite((int) $type->base_price, $adultes, $enfants));
        $regles = $type->relationLoaded('rates') ? $type->rates->where('is_active', true) : $type->rates()->actives()->get();

        $detail = [];
        $total = 0;
        $ajuste = false;
        for ($i = 0; $i < $nuits; $i++) {
            $jour = $arrivee->addDays($i);
            $regle = $this->regleDuJour($regles, $jour, $nuits, $avance);
            $prix = $regle
                ? $this->auFranc($type->avecSurchargeDeCapacite($regle->calculatePrice((int) $type->base_price), $adultes, $enfants))
                : $base;

            $ajuste = $ajuste || $regle !== null;
            $total += $prix;
            $detail[] = ['date' => $jour->toDateString(), 'prix' => $prix, 'tarif' => $regle?->name, 'type' => $regle?->rate_type];
        }

        return [
            'total' => $total,
            'par_nuit' => $nuits > 0 ? $this->auFranc((int) round($total / $nuits)) : $base,
            'base' => $base,
            'ajuste' => $ajuste,
            'nuits' => $detail,
        ];
    }

    /** @param iterable<RoomRate> $regles */
    public function regleDuJour(iterable $regles, CarbonInterface $jour, int $nuits, int $avance): ?RoomRate
    {
        $retenue = null;
        foreach ($regles as $regle) {
            if (! $regle->isApplicable($jour, $nuits, $avance)) {
                continue;
            }
            if ($retenue === null
                || $regle->priority > $retenue->priority
                || ($regle->priority === $retenue->priority && $regle->rang() > $retenue->rang())) {
                $retenue = $regle;
            }
        }

        return $retenue;
    }

    /**
     * Les tarifs appliqués au séjour, regroupés : « 2 nuits Haute saison,
     * 1 nuit au prix de base ».
     *
     * @param array{nuits: list<array{tarif: ?string}>} $calcul
     */
    public function resume(array $calcul): string
    {
        $groupes = [];
        foreach ($calcul['nuits'] as $nuit) {
            $cle = $nuit['tarif'] ?? 'au prix de base';
            $groupes[$cle] = ($groupes[$cle] ?? 0) + 1;
        }

        return implode(', ', array_map(
            fn (string $tarif, int $n) => $n . ' nuit' . ($n > 1 ? 's' : '') . ' ' . ($tarif === 'au prix de base' ? $tarif : '« ' . $tarif . ' »'),
            array_keys($groupes),
            $groupes,
        ));
    }

    private function auFranc(int $centimes): int
    {
        return (int) (round($centimes / 100) * 100);
    }
}
