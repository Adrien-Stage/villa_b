<?php

namespace App\Services;

use App\Models\BudgetLine;
use App\Models\JournalEntry;
use App\Support\TenantModules;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Le réalisé d'une période, par centre et par nature, dans la maille du budget.
 *
 * Deux sources, et la meilleure disponible l'emporte :
 *  - le grand livre analytique (module « ledger ») dès qu'il porte des
 *    écritures validées sur l'année : produits et charges en droits constatés,
 *    chaque ligne rattachée à son centre, le non-ventilé en structure ;
 *  - sinon la comptabilité de caisse : recettes encaissées par activité, et
 *    les dépenses saisies, qui ne disent pas leur centre, en structure.
 *
 * La source retenue est toujours affichée : un écart lu sur des encaissements
 * ne se compare pas à un écart lu sur des droits constatés.
 *
 * Montants en centimes FCFA, positifs (un produit comme une charge).
 */
class RealiseDeGestion
{
    public const SOURCE_GRAND_LIVRE = 'grand_livre';
    public const SOURCE_CAISSE = 'caisse';

    public const SOURCES = [
        self::SOURCE_GRAND_LIVRE => 'grand livre analytique (droits constatés)',
        self::SOURCE_CAISSE => 'comptabilité de caisse (encaissements et dépenses)',
    ];

    public function __construct(
        private readonly AnalyticReportService $analytique,
        private readonly AccountingService $caisse,
    ) {
    }

    public function source(int $annee): string
    {
        $grandLivre = TenantModules::has('ledger') && JournalEntry::query()
            ->whereNotNull('posted_at')
            ->whereYear('entry_date', $annee)
            ->exists();

        return $grandLivre ? self::SOURCE_GRAND_LIVRE : self::SOURCE_CAISSE;
    }

    /**
     * Réalisé de chaque mois de l'année (les mois à venir restent à zéro).
     *
     * @return array<string, array<string, array<int, int>>> centre => nature => mois => montant
     */
    public function parMois(int $annee): array
    {
        $source = $this->source($annee);
        $resultat = $this->vide();

        foreach (range(1, 12) as $mois) {
            $debut = CarbonImmutable::create($annee, $mois, 1)->startOfDay();
            if ($debut->isFuture()) {
                break;
            }
            foreach ($this->periode($debut, $debut->endOfMonth(), $source) as $centre => $natures) {
                foreach ($natures as $nature => $montant) {
                    $resultat[$centre][$nature][$mois] = $montant;
                }
            }
        }

        return $resultat;
    }

    /**
     * Réalisé d'une période quelconque (un mois en cours, par exemple).
     *
     * @return array<string, array<string, int>> centre => nature => montant
     */
    public function periode(CarbonInterface $debut, CarbonInterface $fin, ?string $source = null): array
    {
        $source ??= $this->source((int) $debut->year);
        $resultat = [];
        foreach (array_keys(BudgetLine::centres()) as $centre) {
            $resultat[$centre] = [BudgetLine::NATURE_REVENUE => 0, BudgetLine::NATURE_COST => 0];
        }

        if ($source === self::SOURCE_GRAND_LIVRE) {
            $analyse = $this->analytique->resultByCenter($debut, $fin);
            foreach ($analyse['rows'] as $ligne) {
                $resultat[$ligne['center']] = [BudgetLine::NATURE_REVENUE => (int) $ligne['revenue'], BudgetLine::NATURE_COST => (int) $ligne['cost']];
            }
            $resultat[BudgetLine::CENTER_STRUCTURE] = [
                BudgetLine::NATURE_REVENUE => (int) $analyse['unassigned']['revenue'],
                BudgetLine::NATURE_COST => (int) $analyse['unassigned']['cost'],
            ];

            return $resultat;
        }

        $recettes = $this->caisse->recettes($debut, $fin);
        $resultat['hebergement'][BudgetLine::NATURE_REVENUE] = (int) $recettes['hebergement'];
        $resultat['restaurant'][BudgetLine::NATURE_REVENUE] = (int) $recettes['restaurant'];
        $resultat['boutique'][BudgetLine::NATURE_REVENUE] = (int) $recettes['boutique'];
        $resultat[BudgetLine::CENTER_STRUCTURE][BudgetLine::NATURE_COST] = (int) $this->caisse->depenses($debut, $fin)['total'];

        return $resultat;
    }

    /** @return array<string, array<string, array<int, int>>> */
    private function vide(): array
    {
        $douze = array_fill(1, 12, 0);
        $vide = [];
        foreach (array_keys(BudgetLine::centres()) as $centre) {
            $vide[$centre] = [BudgetLine::NATURE_REVENUE => $douze, BudgetLine::NATURE_COST => $douze];
        }

        return $vide;
    }
}
