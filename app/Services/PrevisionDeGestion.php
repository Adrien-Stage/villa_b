<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\ForecastLine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * La prévision de gestion (Forecast / Atterrissage) face au réalisé et au budget.
 *
 * Distincte du budget initial d'engagement :
 * - Le budget fige les objectifs de début d'exercice.
 * - La prévision actualise la trajectoire au fil de l'eau (atterrissage) :
 *   mois réels écoulés + mois révisés/budgétés pour le reste de l'exercice.
 *
 * Montants en centimes FCFA.
 */
class PrevisionDeGestion
{
    public function __construct(private readonly RealiseDeGestion $realise)
    {
    }

    /**
     * Grille de prévision pour l'année.
     *
     * @return array{
     *     annee: int, source: string, existe: bool, valide: bool,
     *     mois_ecoules: int,
     *     centres: list<array{centre: string, libelle: string, lignes: array<string, array<string, mixed>>}>,
     *     resultat: array{forecast: array<int, int>, realise: array<int, int>, total_forecast: int, total_realise: int},
     * }
     */
    public function grille(int $annee, ?array $forecastSaisi = null): array
    {
        $lignes = ForecastLine::query()->where('year', $annee)->get()->keyBy(fn (ForecastLine $l) => $l->center . '|' . $l->nature);
        $realise = $this->realise->parMois($annee);
        $moisEcoules = $this->moisEcoules($annee);

        $centres = [];
        $resultatForecast = array_fill(1, 12, 0);
        $resultatRealise = array_fill(1, 12, 0);

        foreach (ForecastLine::centres() as $centre => $libelle) {
            $parNature = [];
            foreach (ForecastLine::NATURES as $nature => $libelleNature) {
                $forecast = $forecastSaisi[$centre][$nature] ?? ($lignes->get($centre . '|' . $nature)?->montants() ?? array_fill(1, 12, 0));
                $reel = $realise[$centre][$nature];
                $signe = $nature === ForecastLine::NATURE_REVENUE ? 1 : -1;

                foreach (range(1, 12) as $m) {
                    $resultatForecast[$m] += $signe * $forecast[$m];
                    $resultatRealise[$m] += $signe * $reel[$m];
                }

                $forecastAuJour = array_sum(array_slice($forecast, 0, $moisEcoules, true));
                $realiseAuJour = array_sum(array_slice($reel, 0, $moisEcoules, true));

                $parNature[$nature] = [
                    'libelle' => $libelleNature,
                    'forecast' => $forecast,
                    'realise' => $reel,
                    'total_forecast' => array_sum($forecast),
                    'total_realise' => array_sum($reel),
                    'forecast_au_jour' => $forecastAuJour,
                    'realise_au_jour' => $realiseAuJour,
                    'ecart' => $this->ecart($nature, $forecastAuJour, $realiseAuJour),
                ];
            }
            $centres[] = ['centre' => $centre, 'libelle' => $libelle, 'lignes' => $parNature];
        }

        $estValide = $lignes->isNotEmpty() && $lignes->every(fn (ForecastLine $l) => $l->status === ForecastLine::STATUS_VALIDATED);

        return [
            'annee' => $annee,
            'source' => $this->realise->source($annee),
            'existe' => $lignes->isNotEmpty(),
            'valide' => $estValide,
            'mois_ecoules' => $moisEcoules,
            'centres' => $centres,
            'resultat' => [
                'forecast' => $resultatForecast,
                'realise' => $resultatRealise,
                'total_forecast' => array_sum($resultatForecast),
                'total_realise' => array_sum($resultatRealise),
            ],
        ];
    }

    /**
     * Écart d'une nature : montant (réalisé − forecast), pourcentage du forecast,
     * et son sens pour l'hôtel.
     *
     * @return array{montant: int, pct: float|null, favorable: bool|null}
     */
    public function ecart(string $nature, int $forecast, int $realise): array
    {
        $montant = $realise - $forecast;

        return [
            'montant' => $montant,
            'pct' => $forecast > 0 ? round($montant * 100 / $forecast, 1) : null,
            'favorable' => $montant === 0 ? null : ($nature === ForecastLine::NATURE_REVENUE ? $montant > 0 : $montant < 0),
        ];
    }

    /**
     * Enregistre la prévision saisie en FCFA (par le contrôleur de gestion).
     */
    public function enregistrer(int $annee, array $saisie, User $par, bool $forcer = false): void
    {
        $dejaValide = ForecastLine::where('year', $annee)->where('status', ForecastLine::STATUS_VALIDATED)->exists();
        if ($dejaValide && !$forcer) {
            throw new RuntimeException("La prévision {$annee} est validée par le RAF et ne peut plus être modifiée sans réouverture.");
        }

        DB::transaction(function () use ($annee, $saisie, $par) {
            foreach (ForecastLine::centres() as $centre => $libelle) {
                foreach (array_keys(ForecastLine::NATURES) as $nature) {
                    $valeurs = [
                        'updated_by' => $par->id,
                        'status' => ForecastLine::STATUS_DRAFT,
                    ];
                    foreach (range(1, 12) as $m) {
                        $valeurs['m' . $m] = max(0, (int) round(((float) ($saisie[$centre][$nature][$m] ?? 0)) * 100));
                    }
                    ForecastLine::updateOrCreate(['year' => $annee, 'center' => $centre, 'nature' => $nature], $valeurs);
                }
            }
        });

        $produits = (int) ForecastLine::where('year', $annee)->where('nature', ForecastLine::NATURE_REVENUE)->get()->sum(fn ($l) => array_sum($l->montants()));
        $charges = (int) ForecastLine::where('year', $annee)->where('nature', ForecastLine::NATURE_COST)->get()->sum(fn ($l) => array_sum($l->montants()));

        AuditLog::record($par->id, 'forecast', "Prévision {$annee} enregistrée : produits " . number_format($produits / 100, 0, ',', ' ')
            . ' FCFA, charges ' . number_format($charges / 100, 0, ',', ' ') . ' FCFA', 'pilotage', ['annee' => $annee]);
    }

    /**
     * Validation formelle de la prévision par le RAF.
     */
    public function valider(int $annee, User $raf): void
    {
        $existe = ForecastLine::where('year', $annee)->exists();
        if (!$existe) {
            throw new RuntimeException("Aucune prévision à valider pour {$annee}.");
        }

        DB::transaction(function () use ($annee, $raf) {
            ForecastLine::where('year', $annee)->update([
                'status' => ForecastLine::STATUS_VALIDATED,
                'validated_by' => $raf->id,
                'validated_at' => now(),
            ]);
        });

        AuditLog::record($raf->id, 'forecast', "Prévision {$annee} validée formellement par le RAF", 'pilotage', ['annee' => $annee]);
    }

    /**
     * Pré-remplissage depuis le budget existant.
     *
     * @return array<string, array<string, array<int, int>>>
     */
    public function depuisLeBudget(int $annee): array
    {
        $lignes = BudgetLine::where('year', $annee)->get()->keyBy(fn (BudgetLine $l) => $l->center . '|' . $l->nature);
        $propose = [];

        foreach (ForecastLine::centres() as $centre => $libelle) {
            foreach (array_keys(ForecastLine::NATURES) as $nature) {
                $propose[$centre][$nature] = $lignes->get($centre . '|' . $nature)?->montants() ?? array_fill(1, 12, 0);
            }
        }

        return $propose;
    }

    /**
     * Atterrissage financier : mois écoulés réels + mois futurs issus du budget.
     *
     * @return array<string, array<string, array<int, int>>>
     */
    public function depuisLeRealiseEtBudget(int $annee): array
    {
        $moisEcoules = $this->moisEcoules($annee);
        $realise = $this->realise->parMois($annee);
        $lignesBudget = BudgetLine::where('year', $annee)->get()->keyBy(fn (BudgetLine $l) => $l->center . '|' . $l->nature);
        $propose = [];

        foreach (ForecastLine::centres() as $centre => $libelle) {
            foreach (array_keys(ForecastLine::NATURES) as $nature) {
                $budgetMois = $lignesBudget->get($centre . '|' . $nature)?->montants() ?? array_fill(1, 12, 0);
                $combinaison = [];
                foreach (range(1, 12) as $m) {
                    if ($m <= $moisEcoules) {
                        // Mois réel écoulé
                        $combinaison[$m] = $realise[$centre][$nature][$m] ?? 0;
                    } else {
                        // Mois restant estimé selon le budget
                        $combinaison[$m] = $budgetMois[$m] ?? 0;
                    }
                }
                $propose[$centre][$nature] = $combinaison;
            }
        }

        return $propose;
    }

    /**
     * Prévision et réalisé d'un mois, jusqu'au jour donné.
     *
     * @return array<string, array<string, array{forecast: int, realise: int, ecart: array{montant: int, pct: float|null, favorable: bool|null}}>>
     */
    public function mois(int $annee, int $mois, ?CarbonImmutable $jusquAu = null): array
    {
        $debut = CarbonImmutable::create($annee, $mois, 1)->startOfDay();
        $fin = $jusquAu?->endOfDay() ?? $debut->endOfMonth();
        $part = ((int) $debut->diffInDays($fin->startOfDay()) + 1) / $debut->daysInMonth;

        $lignes = ForecastLine::query()->where('year', $annee)->get()->keyBy(fn (ForecastLine $l) => $l->center . '|' . $l->nature);
        $realise = $this->realise->periode($debut, $fin);

        $resultat = [];
        foreach (array_keys(ForecastLine::centres()) as $centre) {
            foreach (array_keys(ForecastLine::NATURES) as $nature) {
                $forecast = (int) round(($lignes->get($centre . '|' . $nature)?->mois($mois) ?? 0) * $part);
                $reel = $realise[$centre][$nature];
                $resultat[$centre][$nature] = ['forecast' => $forecast, 'realise' => $reel, 'ecart' => $this->ecart($nature, $forecast, $reel)];
            }
        }

        return $resultat;
    }

    public function existe(int $annee): bool
    {
        return ForecastLine::query()->where('year', $annee)->exists();
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
