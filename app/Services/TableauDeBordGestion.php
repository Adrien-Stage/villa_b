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
        private readonly ?PrevisionDeGestion $prevision = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function pour(CarbonImmutable $mois): array
    {
        $debut = $mois->startOfMonth()->startOfDay();
        // Le mois en cours s'arrête à aujourd'hui ; le précédent et l'année N-1
        // se comparent sur le même nombre exact de jours, sinon un mois commencé
        // perdrait toujours face à une période pleine.
        $fin = $debut->isSameMonth(CarbonImmutable::now()) ? CarbonImmutable::now()->endOfDay() : $debut->endOfMonth();
        $jours = (int) $debut->diffInDays($fin->startOfDay()) + 1;

        // Période précédente M-1 (mois précédent)
        $avantDebut = $debut->subMonthNoOverflow()->startOfMonth();
        $avantFin = $avantDebut->addDays(min($jours, $avantDebut->daysInMonth) - 1)->endOfDay();

        // Période précédente N-1 (même mois de l'année précédente, avec gestion bissextile)
        $n1Debut = $debut->subYearNoOverflow()->startOfMonth();
        $n1Jours = min($jours, $n1Debut->daysInMonth);
        $n1Fin = $n1Debut->addDays($n1Jours - 1)->endOfDay();

        $hebergement = $this->hebergement($debut, $fin);
        $hebergementAvant = $this->hebergement($avantDebut, $avantFin);
        $hebergementN1 = $this->hebergement($n1Debut, $n1Fin);

        $restaurant = TenantModules::has('restaurant') ? $this->restaurant($debut, $fin) : null;
        $restaurantAvant = $restaurant !== null ? $this->restaurant($avantDebut, $avantFin) : null;
        $restaurantN1 = $restaurant !== null ? $this->restaurant($n1Debut, $n1Fin) : null;

        $recettes = $this->caisse->recettes($debut, $fin);
        $recettesAvant = $this->caisse->recettes($avantDebut, $avantFin);
        $recettesN1 = $this->caisse->recettes($n1Debut, $n1Fin);

        $depenses = $this->caisse->depenses($debut, $fin)['total'];
        $depensesAvant = $this->caisse->depenses($avantDebut, $avantFin)['total'];
        $depensesN1 = $this->caisse->depenses($n1Debut, $n1Fin)['total'];

        $budget = $this->budget->existe($debut->year)
            ? $this->budget->mois($debut->year, $debut->month, $fin->isSameDay($debut->endOfMonth()) ? null : $fin)
            : null;

        $prevService = $this->prevision ?? app(PrevisionDeGestion::class);
        $forecast = $prevService->existe($debut->year)
            ? $prevService->mois($debut->year, $debut->month, $fin->isSameDay($debut->endOfMonth()) ? null : $fin)
            : null;

        $rapprochement = $this->rapprochement($debut, $fin, $hebergement, $restaurant, $recettes);

        return [
            'debut' => $debut,
            'fin' => $fin,
            'avant_debut' => $avantDebut,
            'avant_fin' => $avantFin,
            'n1_debut' => $n1Debut,
            'n1_fin' => $n1Fin,
            'hebergement' => $hebergement,
            'hebergement_avant' => $hebergementAvant,
            'hebergement_n1' => $hebergementN1,
            'restaurant' => $restaurant,
            'restaurant_avant' => $restaurantAvant,
            'restaurant_n1' => $restaurantN1,
            'tresorerie' => [
                'encaissements' => $recettes['total'],
                'encaissements_avant' => $recettesAvant['total'],
                'encaissements_n1' => $recettesN1['total'],
                'par_moyen' => $recettes['by_method'],
                'par_activite' => ['hebergement' => $recettes['hebergement'], 'restaurant' => $recettes['restaurant'], 'boutique' => $recettes['boutique']],
                'boutique_avant' => $recettesAvant['boutique'],
                'boutique_n1' => $recettesN1['boutique'],
                'depenses' => $depenses,
                'depenses_avant' => $depensesAvant,
                'depenses_n1' => $depensesN1,
                'creances' => (int) ($this->caisse->creances()['total'] ?? 0),
            ],
            'budget' => $budget,
            'forecast' => $forecast,
            'rapprochement' => $rapprochement,
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

    /**
     * Rapprochement d'intégrité financière :
     * Exploitation (ventes brutes PMS/Resto/Boutique) vs Encaissements Caisse vs Grand Livre.
     *
     * @return array<string, mixed>
     */
    public function rapprochement(CarbonImmutable $debut, CarbonImmutable $fin, array $hebergement, ?array $restaurant, array $recettes): array
    {
        $caHebergement = (int) ($hebergement['revenu'] ?? 0);
        $caRestaurant = (int) ($restaurant['chiffre_affaires'] ?? 0);
        $caBoutique = (int) ($recettes['boutique'] ?? 0);
        $totalExploitation = $caHebergement + $caRestaurant + $caBoutique;

        $totalCaisse = (int) ($recettes['total'] ?? 0);
        $ecartCaisse = $totalExploitation - $totalCaisse;

        $caCompta = null;
        $ecartCompta = null;
        if (TenantModules::has('ledger')) {
            $produits = app(AnalyticReportService::class)->resultByCenter($debut, $fin)['totals']['revenue'] ?? null;
            if ($produits !== null) {
                $caCompta = (int) $produits;
                $ecartCompta = $totalExploitation - $caCompta;
            }
        }

        return [
            'ca_hebergement'     => $caHebergement,
            'ca_restaurant'      => $caRestaurant,
            'ca_boutique'        => $caBoutique,
            'ca_exploitation'    => $totalExploitation,
            'encaissements'      => $totalCaisse,
            'ecart_caisse'       => $ecartCaisse,
            'ca_comptabilise'    => $caCompta,
            'ecart_comptabilite' => $ecartCompta,
            'conforme'           => $ecartCompta === null || abs($ecartCompta) < 100,
        ];
    }

    /** Libellé d'un centre du budget. */
    public static function centre(?string $centre): string
    {
        return BudgetLine::centres()[$centre] ?? 'Tout l’hôtel';
    }
}
