<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Le budget de l'année face à son réalisé : par centre, par nature et par
 * mois, avec l'écart.
 *
 * Un écart se lit toujours dans le sens de l'intérêt de l'hôtel : des
 * recettes au-dessus du budget, des charges en dessous, sont favorables ;
 * l'inverse est défavorable.
 *
 * Montants en centimes FCFA.
 */
class BudgetDeGestion
{
    public function __construct(private readonly RealiseDeGestion $realise)
    {
    }

    /**
     * @return array{
     *     annee: int, source: string, existe: bool,
     *     centres: list<array{centre: string, libelle: string, lignes: array<string, array<string, mixed>>}>,
     *     resultat: array{budget: array<int, int>, realise: array<int, int>, total_budget: int, total_realise: int},
     * }
     */
    public function grille(int $annee, ?array $budgetSaisi = null): array
    {
        $lignes = BudgetLine::query()->where('year', $annee)->get()->keyBy(fn (BudgetLine $l) => $l->center . '|' . $l->nature);
        $realise = $this->realise->parMois($annee);
        $moisEcoules = $this->moisEcoules($annee);

        $centres = [];
        $resultatBudget = array_fill(1, 12, 0);
        $resultatRealise = array_fill(1, 12, 0);

        foreach (BudgetLine::centres() as $centre => $libelle) {
            $parNature = [];
            foreach (BudgetLine::NATURES as $nature => $libelleNature) {
                $budget = $budgetSaisi[$centre][$nature] ?? ($lignes->get($centre . '|' . $nature)?->montants() ?? array_fill(1, 12, 0));
                $reel = $realise[$centre][$nature];
                $signe = $nature === BudgetLine::NATURE_REVENUE ? 1 : -1;

                foreach (range(1, 12) as $m) {
                    $resultatBudget[$m] += $signe * $budget[$m];
                    $resultatRealise[$m] += $signe * $reel[$m];
                }

                // L'écart de l'année ne porte que sur les mois écoulés : un
                // budget de décembre n'est pas « en retard » en mars.
                $budgetAuJour = array_sum(array_slice($budget, 0, $moisEcoules, true));
                $realiseAuJour = array_sum(array_slice($reel, 0, $moisEcoules, true));

                $parNature[$nature] = [
                    'libelle' => $libelleNature,
                    'budget' => $budget,
                    'realise' => $reel,
                    'total_budget' => array_sum($budget),
                    'total_realise' => array_sum($reel),
                    'budget_au_jour' => $budgetAuJour,
                    'realise_au_jour' => $realiseAuJour,
                    'ecart' => $this->ecart($nature, $budgetAuJour, $realiseAuJour),
                ];
            }
            $centres[] = ['centre' => $centre, 'libelle' => $libelle, 'lignes' => $parNature];
        }

        return [
            'annee' => $annee,
            'source' => $this->realise->source($annee),
            'existe' => $lignes->isNotEmpty(),
            'mois_ecoules' => $moisEcoules,
            'centres' => $centres,
            'resultat' => [
                'budget' => $resultatBudget,
                'realise' => $resultatRealise,
                'total_budget' => array_sum($resultatBudget),
                'total_realise' => array_sum($resultatRealise),
            ],
        ];
    }

    /**
     * Écart d'une nature : montant (réalisé − budget), pourcentage du budget,
     * et son sens pour l'hôtel.
     *
     * @return array{montant: int, pct: float|null, favorable: bool|null}
     */
    public function ecart(string $nature, int $budget, int $realise): array
    {
        $montant = $realise - $budget;

        return [
            'montant' => $montant,
            'pct' => $budget > 0 ? round($montant * 100 / $budget, 1) : null,
            'favorable' => $montant === 0 ? null : ($nature === BudgetLine::NATURE_REVENUE ? $montant > 0 : $montant < 0),
        ];
    }

    /**
     * Enregistre le budget saisi en FCFA : centre => nature => mois => montant.
     */
    public function enregistrer(int $annee, array $saisie, User $par): void
    {
        DB::transaction(function () use ($annee, $saisie, $par) {
            foreach (BudgetLine::centres() as $centre => $libelle) {
                foreach (array_keys(BudgetLine::NATURES) as $nature) {
                    $valeurs = ['updated_by' => $par->id];
                    foreach (range(1, 12) as $m) {
                        $valeurs['m' . $m] = max(0, (int) round(((float) ($saisie[$centre][$nature][$m] ?? 0)) * 100));
                    }
                    BudgetLine::updateOrCreate(['year' => $annee, 'center' => $centre, 'nature' => $nature], $valeurs);
                }
            }
        });

        $produits = (int) BudgetLine::where('year', $annee)->where('nature', BudgetLine::NATURE_REVENUE)->get()->sum(fn ($l) => array_sum($l->montants()));
        $charges = (int) BudgetLine::where('year', $annee)->where('nature', BudgetLine::NATURE_COST)->get()->sum(fn ($l) => array_sum($l->montants()));

        AuditLog::record($par->id, 'budget', "Budget {$annee} enregistré : produits " . number_format($produits / 100, 0, ',', ' ')
            . ' FCFA, charges ' . number_format($charges / 100, 0, ',', ' ') . ' FCFA', 'pilotage', ['annee' => $annee]);
    }

    /**
     * Le réalisé de l'année précédente, en FCFA, comme point de départ d'un
     * nouveau budget.
     *
     * @return array<string, array<string, array<int, int>>>
     */
    public function depuisLeRealise(int $anneeReference): array
    {
        $propose = [];
        foreach ($this->realise->parMois($anneeReference) as $centre => $natures) {
            foreach ($natures as $nature => $mois) {
                $propose[$centre][$nature] = array_map(fn (int $centimes) => $centimes, $mois);
            }
        }

        return $propose;
    }

    /**
     * Budget et réalisé d'un mois, jusqu'au jour donné : le budget est ramené
     * au prorata des jours écoulés.
     *
     * @return array<string, array<string, array{budget: int, realise: int, ecart: array{montant: int, pct: float|null, favorable: bool|null}}>>
     */
    public function mois(int $annee, int $mois, ?CarbonImmutable $jusquAu = null): array
    {
        $debut = CarbonImmutable::create($annee, $mois, 1)->startOfDay();
        $fin = $jusquAu?->endOfDay() ?? $debut->endOfMonth();
        $part = ((int) $debut->diffInDays($fin->startOfDay()) + 1) / $debut->daysInMonth;

        $lignes = BudgetLine::query()->where('year', $annee)->get()->keyBy(fn (BudgetLine $l) => $l->center . '|' . $l->nature);
        $realise = $this->realise->periode($debut, $fin);

        $resultat = [];
        foreach (array_keys(BudgetLine::centres()) as $centre) {
            foreach (array_keys(BudgetLine::NATURES) as $nature) {
                $budget = (int) round(($lignes->get($centre . '|' . $nature)?->mois($mois) ?? 0) * $part);
                $reel = $realise[$centre][$nature];
                $resultat[$centre][$nature] = ['budget' => $budget, 'realise' => $reel, 'ecart' => $this->ecart($nature, $budget, $reel)];
            }
        }

        return $resultat;
    }

    public function existe(int $annee): bool
    {
        return BudgetLine::query()->where('year', $annee)->exists();
    }

    private function moisEcoules(int $annee): int
    {
        $aujourdhui = CarbonImmutable::now();

        return match (true) {
            $annee < $aujourdhui->year => 12,
            $annee > $aujourdhui->year => 0,
            default => $aujourdhui->month,
        };
    }
}
