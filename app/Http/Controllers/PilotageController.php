<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\ForecastLine;
use App\Models\Tenant;
use App\Services\BudgetDeGestion;
use App\Services\PermissionResolver;
use App\Services\PrevisionDeGestion;
use App\Services\TableauDeBordGestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

/**
 * Pilotage : le tableau de bord du contrôle de gestion, le budget de l'année,
 * les prévisions d'atterrissage (Forecast) et les seuils des alertes de dérive.
 * Le contrôleur de gestion et le responsable financier l'établissent ;
 * la direction et le RAF le valident et le consultent.
 */
class PilotageController extends Controller
{
    public function __construct(
        private readonly TableauDeBordGestion $tableau,
        private readonly BudgetDeGestion $budget,
        private readonly PrevisionDeGestion $prevision,
    ) {
    }

    public function index(Request $request): View
    {
        $mois = $this->mois($request->query('mois'));

        return view('pilotage.index', [
            'tableau' => $this->tableau->pour($mois),
            'mois' => $mois,
            'peutRegler' => app(PermissionResolver::class)->allows(Auth::user(), 'pilotage.seuils.modifier'),
        ]);
    }

    public function budget(Request $request): View
    {
        $annee = $this->annee($request->query('annee'));
        // Point de départ d'un nouveau budget : le réalisé de l'année d'avant,
        // proposé dans la grille sans rien enregistrer.
        $propose = $request->query('depuis') === 'realise' ? $this->budget->depuisLeRealise($annee - 1) : null;

        return view('pilotage.budget', [
            'grille' => $this->budget->grille($annee, $propose),
            'annee' => $annee,
            'propose' => $propose !== null,
            'peutModifier' => app(PermissionResolver::class)->allows(Auth::user(), 'pilotage.budget.modifier'),
            'peutValider' => app(PermissionResolver::class)->allows(Auth::user(), 'pilotage.budget.valider'),
        ]);
    }

    public function updateBudget(Request $request, int $annee): RedirectResponse
    {
        $annee = $this->annee((string) $annee);
        $request->validate([
            'budget' => ['required', 'array'],
            'budget.*' => ['array'],
            'budget.*.*' => ['array'],
            'budget.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
        ], [
            'budget.*.*.*.min' => 'Un montant de budget ne peut pas être négatif.',
            'budget.*.*.*.numeric' => 'Un montant de budget est un nombre, en FCFA.',
        ]);

        $saisie = [];
        foreach (BudgetLine::centres() as $centre => $libelle) {
            foreach (array_keys(BudgetLine::NATURES) as $nature) {
                foreach (range(1, 12) as $m) {
                    $saisie[$centre][$nature][$m] = $request->input("budget.{$centre}.{$nature}.{$m}", 0);
                }
            }
        }

        try {
            $this->budget->enregistrer($annee, $saisie, Auth::user());
        } catch (RuntimeException $e) {
            return redirect()->route('pilotage.budget.index', ['annee' => $annee])
                ->withErrors(['budget' => $e->getMessage()]);
        }

        return redirect()->route('pilotage.budget.index', ['annee' => $annee])->with('success', "Budget {$annee} enregistré.");
    }

    public function validateBudget(Request $request, int $annee): RedirectResponse
    {
        $annee = $this->annee((string) $annee);
        try {
            $this->budget->valider($annee, Auth::user());
        } catch (RuntimeException $e) {
            return redirect()->route('pilotage.budget.index', ['annee' => $annee])
                ->withErrors(['budget' => $e->getMessage()]);
        }

        return redirect()->route('pilotage.budget.index', ['annee' => $annee])
            ->with('success', "Budget {$annee} validé formellement par le RAF.");
    }

    public function forecast(Request $request): View
    {
        $annee = $this->annee($request->query('annee'));
        $depuis = $request->query('depuis');
        $propose = match ($depuis) {
            'budget' => $this->prevision->depuisLeBudget($annee),
            'atterrissage' => $this->prevision->depuisLeRealiseEtBudget($annee),
            default => null,
        };

        return view('pilotage.forecast', [
            'grille' => $this->prevision->grille($annee, $propose),
            'annee' => $annee,
            'propose' => $propose !== null,
            'modePropose' => $depuis,
            'peutModifier' => app(PermissionResolver::class)->allows(Auth::user(), 'pilotage.forecast.modifier'),
            'peutValider' => app(PermissionResolver::class)->allows(Auth::user(), 'pilotage.forecast.valider'),
        ]);
    }

    public function updateForecast(Request $request, int $annee): RedirectResponse
    {
        $annee = $this->annee((string) $annee);
        $request->validate([
            'forecast' => ['required', 'array'],
            'forecast.*' => ['array'],
            'forecast.*.*' => ['array'],
            'forecast.*.*.*' => ['nullable', 'numeric', 'min:0', 'max:100000000000'],
        ], [
            'forecast.*.*.*.min' => 'Un montant de prévision ne peut pas être négatif.',
            'forecast.*.*.*.numeric' => 'Un montant de prévision est un nombre, en FCFA.',
        ]);

        $saisie = [];
        foreach (ForecastLine::centres() as $centre => $libelle) {
            foreach (array_keys(ForecastLine::NATURES) as $nature) {
                foreach (range(1, 12) as $m) {
                    $saisie[$centre][$nature][$m] = $request->input("forecast.{$centre}.{$nature}.{$m}", 0);
                }
            }
        }

        $forcer = $request->boolean('forcer', false);
        try {
            $this->prevision->enregistrer($annee, $saisie, Auth::user(), $forcer);
        } catch (RuntimeException $e) {
            return redirect()->route('pilotage.forecast.index', ['annee' => $annee])
                ->withErrors(['forecast' => $e->getMessage()]);
        }

        return redirect()->route('pilotage.forecast.index', ['annee' => $annee])
            ->with('success', "Prévision (Forecast) {$annee} enregistrée avec succès.");
    }

    public function validateForecast(Request $request, int $annee): RedirectResponse
    {
        $annee = $this->annee((string) $annee);
        try {
            $this->prevision->valider($annee, Auth::user());
        } catch (RuntimeException $e) {
            return redirect()->route('pilotage.forecast.index', ['annee' => $annee])
                ->withErrors(['forecast' => $e->getMessage()]);
        }

        return redirect()->route('pilotage.forecast.index', ['annee' => $annee])
            ->with('success', "Prévision (Forecast) {$annee} validée formellement par le RAF.");
    }

    public function updateSeuils(Request $request): RedirectResponse
    {
        $seuils = $request->validate([
            'tolerance_budget' => ['required', 'integer', 'min:0', 'max:100'],
            'seuil_cout_matiere' => ['required', 'integer', 'min:1', 'max:100'],
            'baisse_marge' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $tenant = Tenant::firstOrFail();
        $reglages = $tenant->settings ?? [];
        $reglages['pilotage'] = array_map('intval', $seuils);
        $tenant->settings = $reglages;
        $tenant->save();

        AuditLog::record(Auth::id(), 'pilotage', "Seuils d'alerte de gestion : tolérance budget {$seuils['tolerance_budget']} %, coût matière {$seuils['seuil_cout_matiere']} %, baisse de marge {$seuils['baisse_marge']} points", 'pilotage');

        return redirect()->route('pilotage.index', ['mois' => $request->input('mois')])->with('success', "Seuils d'alerte enregistrés.");
    }

    private function mois(?string $valeur): CarbonImmutable
    {
        if ($valeur && preg_match('/^\d{4}-\d{2}$/', $valeur)) {
            $mois = CarbonImmutable::createFromFormat('Y-m-d', $valeur . '-01')->startOfMonth();
            if ($mois->lessThanOrEqualTo(CarbonImmutable::now()->startOfMonth())) {
                return $mois;
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    private function annee(?string $valeur): int
    {
        $annee = (int) ($valeur ?: CarbonImmutable::now()->year);

        return max(2000, min(CarbonImmutable::now()->year + 5, $annee));
    }
}
