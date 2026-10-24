<?php

namespace App\Services;

use App\Models\BudgetLine;
use App\Models\ManagementAlert;
use App\Notifications\AlerteDeGestion;
use App\Support\TenantModules;
use Carbon\CarbonImmutable;

/**
 * Les dérives de gestion du mois en cours, constatées chaque matin
 * (pilotage:alertes) :
 *  - charges d'un centre au-dessus du budget, ramené aux jours écoulés ;
 *  - recettes d'un centre en retard sur ce budget ;
 *  - coût matière de la restauration au-dessus du seuil ;
 *  - marge d'un centre en baisse de plusieurs points sur le mois précédent.
 *
 * Les seuils sont ceux du contrôle de gestion (TableauDeBordGestion::seuils).
 * Une dérive n'est signalée qu'une fois par mois et par centre : les jours
 * suivants, sa valeur est mise à jour sans nouvelle notification. Elle part
 * au contrôleur de gestion, au responsable financier et à la direction.
 */
class AlertesDeGestion
{
    /** Avant le 8 du mois, un prorata de budget varie trop pour fonder une alerte. */
    private const PREMIER_JOUR_UTILE = 8;

    public const DESTINATAIRES = ['controller', 'finance_manager', 'manager'];

    public function __construct(
        private readonly BudgetDeGestion $budget,
        private readonly RealiseDeGestion $realise,
        private readonly TableauDeBordGestion $tableau,
        private readonly Notifier $notifier,
    ) {
    }

    /** @return list<ManagementAlert> les alertes nouvelles du jour */
    public function constater(?CarbonImmutable $jour = null): array
    {
        $jour = ($jour ?? CarbonImmutable::now())->startOfDay();
        $periode = $jour->format('Y-m');
        $seuils = TableauDeBordGestion::seuils();
        $nouvelles = [];

        if ($jour->day >= self::PREMIER_JOUR_UTILE && $this->budget->existe($jour->year)) {
            foreach ($this->budget->mois($jour->year, $jour->month, $jour) as $centre => $natures) {
                $charges = $natures[BudgetLine::NATURE_COST];
                if ($charges['budget'] > 0 && $charges['ecart']['pct'] !== null && $charges['ecart']['pct'] > $seuils['tolerance_budget']) {
                    $nouvelles[] = $this->signaler(ManagementAlert::RULE_COST_OVERRUN, $centre, $periode, $charges['realise'], $charges['budget'],
                        TableauDeBordGestion::centre($centre) . " : charges à " . $this->fcfa($charges['realise']) . " pour un budget de "
                        . $this->fcfa($charges['budget']) . " à ce jour (+" . $this->pct($charges['ecart']['pct']) . ").");
                }

                $recettes = $natures[BudgetLine::NATURE_REVENUE];
                if ($recettes['budget'] > 0 && $recettes['ecart']['pct'] !== null && -$recettes['ecart']['pct'] > $seuils['tolerance_budget']) {
                    $nouvelles[] = $this->signaler(ManagementAlert::RULE_REVENUE_SHORTFALL, $centre, $periode, $recettes['realise'], $recettes['budget'],
                        TableauDeBordGestion::centre($centre) . " : recettes à " . $this->fcfa($recettes['realise']) . " pour un budget de "
                        . $this->fcfa($recettes['budget']) . " à ce jour (" . $this->pct($recettes['ecart']['pct']) . ").");
                }
            }
        }

        if (TenantModules::has('restaurant')) {
            $restaurant = $this->tableau->restaurant($jour->startOfMonth(), $jour->endOfDay());
            if ($restaurant['chiffre_affaires'] > 0 && $restaurant['cout_matiere'] > $seuils['seuil_cout_matiere']) {
                $nouvelles[] = $this->signaler(ManagementAlert::RULE_FOOD_COST, 'restaurant', $periode, (int) round($restaurant['cout_matiere'] * 10), $seuils['seuil_cout_matiere'] * 10,
                    "Restauration : coût matière à " . $this->pct($restaurant['cout_matiere']) . " du chiffre d'affaires depuis le début du mois (seuil : {$seuils['seuil_cout_matiere']} %).");
            }
        }

        if ($jour->day >= self::PREMIER_JOUR_UTILE) {
            $courant = $this->realise->periode($jour->startOfMonth(), $jour->endOfDay());
            $precedent = $this->realise->periode($jour->subMonthNoOverflow()->startOfMonth(), $jour->subMonthNoOverflow()->endOfMonth());
            foreach ($courant as $centre => $natures) {
                $taux = $this->tauxDeMarge($natures);
                $tauxAvant = $this->tauxDeMarge($precedent[$centre]);
                if ($centre !== BudgetLine::CENTER_STRUCTURE && $taux !== null && $tauxAvant !== null && $tauxAvant - $taux > $seuils['baisse_marge']) {
                    $nouvelles[] = $this->signaler(ManagementAlert::RULE_MARGIN_DROP, $centre, $periode, (int) round($taux * 10), (int) round($tauxAvant * 10),
                        TableauDeBordGestion::centre($centre) . " : marge à " . $this->pct($taux) . " des produits depuis le début du mois, contre " . $this->pct($tauxAvant) . " le mois dernier.");
                }
            }
        }

        return array_values(array_filter($nouvelles));
    }

    /** @param array<string, int> $natures */
    private function tauxDeMarge(array $natures): ?float
    {
        $produits = $natures[BudgetLine::NATURE_REVENUE];
        // Un centre sans produit (l'économat) n'a pas de marge à suivre.
        if ($produits <= 0 || $natures[BudgetLine::NATURE_COST] <= 0) {
            return null;
        }

        return round(($produits - $natures[BudgetLine::NATURE_COST]) * 100 / $produits, 1);
    }

    /** Nouvelle dérive : enregistrée et notifiée ; déjà connue ce mois-ci : mise à jour, sans notification. */
    private function signaler(string $regle, ?string $centre, string $periode, int $valeur, int $reference, string $message): ?ManagementAlert
    {
        $alerte = ManagementAlert::query()->firstOrNew(['rule' => $regle, 'center' => $centre, 'period' => $periode]);
        $nouvelle = ! $alerte->exists;
        $alerte->fill(['value' => $valeur, 'reference' => $reference, 'message' => $message])->save();

        if (! $nouvelle) {
            return null;
        }

        $this->notifier->toRoles(self::DESTINATAIRES, new AlerteDeGestion($alerte));

        return $alerte;
    }

    private function pct(float $valeur): string
    {
        return str_replace('.', ',', (string) $valeur) . ' %';
    }

    private function fcfa(int $centimes): string
    {
        return number_format($centimes / 100, 0, ',', ' ') . ' FCFA';
    }
}
