<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BudgetLine;
use App\Models\ManagementAlert;
use App\Models\RestaurantCustomerOrder;
use App\Models\Room;
use App\Models\Tenant;
use App\Support\TenantModules;
use Carbon\CarbonImmutable;

/**
 * Les indicateurs du contrôle de gestion sur un mois, face au mois précédent
 * et au budget : hébergement (occupation, prix moyen, RevPAR, canaux,
 * annulations), restauration (chiffre d'affaires, couverts, revenu par
 * couvert, coût matière, pertes), boutique, trésorerie et budget.
 *
 * Les indicateurs d'hébergement se lisent sur les séjours eux-mêmes, nuit par
 * nuit, et non sur les encaissements : un séjour payé d'avance ne fait pas un
 * mois plein, un séjour payé au départ ne fait pas un mois vide.
 *
 * Montants en centimes FCFA.
 */
class TableauDeBordGestion
{
    public function __construct(
        private readonly AccountingService $caisse,
        private readonly RestaurantStockService $cuisine,
        private readonly BudgetDeGestion $budget,
    ) {
    }

    /** @return array<string, mixed> */
    public function pour(CarbonImmutable $mois): array
    {
        $debut = $mois->startOfMonth()->startOfDay();
        // Le mois en cours s'arrête à aujourd'hui ; le précédent se compare
        // sur le même nombre de jours, sinon un mois commencé perdrait
        // toujours face à un mois plein.
        $fin = $debut->isSameMonth(CarbonImmutable::now()) ? CarbonImmutable::now()->endOfDay() : $debut->endOfMonth();
        $jours = (int) $debut->diffInDays($fin->startOfDay()) + 1;
        $avantDebut = $debut->subMonthNoOverflow()->startOfMonth();
        $avantFin = $avantDebut->addDays(min($jours, $avantDebut->daysInMonth) - 1)->endOfDay();

        $hebergement = $this->hebergement($debut, $fin);
        $hebergementAvant = $this->hebergement($avantDebut, $avantFin);

        $restaurant = TenantModules::has('restaurant') ? $this->restaurant($debut, $fin) : null;
        $restaurantAvant = $restaurant !== null ? $this->restaurant($avantDebut, $avantFin) : null;

        $recettes = $this->caisse->recettes($debut, $fin);
        $recettesAvant = $this->caisse->recettes($avantDebut, $avantFin);
        $depenses = $this->caisse->depenses($debut, $fin)['total'];
        $depensesAvant = $this->caisse->depenses($avantDebut, $avantFin)['total'];

        $budget = $this->budget->existe($debut->year)
            ? $this->budget->mois($debut->year, $debut->month, $fin->isSameDay($debut->endOfMonth()) ? null : $fin)
            : null;

        return [
            'debut' => $debut,
            'fin' => $fin,
            'avant_debut' => $avantDebut,
            'avant_fin' => $avantFin,
            'hebergement' => $hebergement,
            'hebergement_avant' => $hebergementAvant,
            'restaurant' => $restaurant,
            'restaurant_avant' => $restaurantAvant,
            'tresorerie' => [
                'encaissements' => $recettes['total'],
                'encaissements_avant' => $recettesAvant['total'],
                'par_moyen' => $recettes['by_method'],
                'par_activite' => ['hebergement' => $recettes['hebergement'], 'restaurant' => $recettes['restaurant'], 'boutique' => $recettes['boutique']],
                'boutique_avant' => $recettesAvant['boutique'],
                'depenses' => $depenses,
                'depenses_avant' => $depensesAvant,
                'creances' => (int) ($this->caisse->creances()['total'] ?? 0),
            ],
            'budget' => $budget,
            'alertes' => ManagementAlert::query()->where('period', $debut->format('Y-m'))->latest('updated_at')->get(),
            'seuils' => self::seuils(),
        ];
    }

    /**
     * Hébergement : nuitées vendues et revenu d'hébergement nuit par nuit,
     * canaux de vente, annulations des arrivées de la période.
     *
     * @return array<string, mixed>
     */
    public function hebergement(CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $finExclue = $fin->startOfDay()->addDay();
        $jours = (int) $debut->diffInDays($finExclue);
        $disponibles = Room::query()->sellable()->count() * $jours;

        $sejours = Booking::query()
            ->whereNotIn('status', [BookingStatus::CANCELLED->value, BookingStatus::NO_SHOW->value])
            ->where('check_in', '<', $finExclue)
            ->where('check_out', '>', $debut)
            ->get(['id', 'check_in', 'check_out', 'price_per_night', 'source', 'is_complimentary']);

        $vendues = 0;
        $revenu = 0;
        $canaux = [];
        foreach ($sejours as $sejour) {
            $nuits = max(0, (int) $sejour->check_in->copy()->startOfDay()->max($debut)->diffInDays($sejour->check_out->copy()->startOfDay()->min($finExclue)));
            $vendues += $nuits;
            if ($sejour->is_complimentary) {
                continue;
            }
            $montant = $nuits * (int) $sejour->price_per_night;
            $revenu += $montant;
            $canal = $sejour->sourceLabel();
            $canaux[$canal] = ($canaux[$canal] ?? 0) + $montant;
        }
        arsort($canaux);

        // Les arrivées prévues sur la période, et la part qui n'a pas eu lieu.
        $arrivees = Booking::query()->where('check_in', '>=', $debut)->where('check_in', '<', $finExclue)
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $totalArrivees = (int) $arrivees->sum();
        $annulees = (int) ($arrivees[BookingStatus::CANCELLED->value] ?? 0);
        $absents = (int) ($arrivees[BookingStatus::NO_SHOW->value] ?? 0);

        return [
            'disponibles' => $disponibles,
            'vendues' => $vendues,
            'revenu' => $revenu,
            'occupation' => $disponibles > 0 ? round($vendues * 100 / $disponibles, 1) : null,
            'adr' => $vendues > 0 ? intdiv($revenu, $vendues) : null,
            'revpar' => $disponibles > 0 ? intdiv($revenu, $disponibles) : null,
            'canaux' => $canaux,
            'arrivees' => $totalArrivees,
            'taux_annulation' => $totalArrivees > 0 ? round($annulees * 100 / $totalArrivees, 1) : null,
            'taux_absence' => $totalArrivees > 0 ? round($absents * 100 / $totalArrivees, 1) : null,
        ];
    }

    /**
     * Restauration : chiffre d'affaires, coût matière et pertes (rapport de
     * consommation), couverts et revenu par couvert.
     *
     * @return array<string, mixed>
     */
    public function restaurant(CarbonImmutable $debut, CarbonImmutable $fin): array
    {
        $rapport = $this->cuisine->getKitchenConsumptionReport(
            startDate: $debut->toMutable(),
            endDate: $fin->toMutable(),
            tenantId: Tenant::first()?->id,
        );

        // Le revenu par couvert ne se calcule que sur les commandes qui
        // portent leurs couverts : mêler les autres le ferait baisser à tort.
        $avecCouverts = RestaurantCustomerOrder::query()
            ->whereNotNull('covers')->where('covers', '>', 0)
            ->where('status', '!=', RestaurantCustomerOrder::STATUS_CANCELED)
            ->whereBetween('placed_at', [$debut, $fin])
            ->selectRaw('sum(covers) as couverts, sum(total_amount) as montant')
            ->first();
        $couverts = (int) ($avecCouverts->couverts ?? 0);

        return [
            'chiffre_affaires' => (int) $rapport['total_revenue'],
            'commandes' => (int) $rapport['orders_count'],
            'cout_matiere' => (float) $rapport['food_cost_ratio'],
            'pertes' => (int) $rapport['waste_cost'],
            'couverts' => $couverts,
            'revenu_par_couvert' => $couverts > 0 ? intdiv((int) $avecCouverts->montant, $couverts) : null,
        ];
    }

    /**
     * Seuils d'alerte réglés par le contrôle de gestion (Pilotage).
     *
     * @return array{tolerance_budget: int, seuil_cout_matiere: int, baisse_marge: int}
     */
    public static function seuils(): array
    {
        $reglages = Tenant::first()?->settings['pilotage'] ?? [];

        return [
            'tolerance_budget' => (int) ($reglages['tolerance_budget'] ?? 10),
            'seuil_cout_matiere' => (int) ($reglages['seuil_cout_matiere'] ?? 35),
            'baisse_marge' => (int) ($reglages['baisse_marge'] ?? 5),
        ];
    }

    /** Variation en % d'une valeur sur la précédente, ou null s'il n'y a pas de base. */
    public static function variation(int|float|null $valeur, int|float|null $avant): ?float
    {
        if ($valeur === null || $avant === null || (float) $avant === 0.0) {
            return null;
        }

        return round(($valeur - $avant) * 100 / abs($avant), 1);
    }

    /** Libellé d'un centre du budget. */
    public static function centre(?string $centre): string
    {
        return BudgetLine::centres()[$centre] ?? 'Tout l’hôtel';
    }
}
