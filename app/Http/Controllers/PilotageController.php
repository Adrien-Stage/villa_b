<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BudgetLine;
use App\Models\Tenant;
use App\Services\BudgetDeGestion;
use App\Services\PermissionResolver;
use App\Services\TableauDeBordGestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Pilotage : le tableau de bord du contrôle de gestion, le budget de l'année
 * et les seuils des alertes de dérive. Le contrôleur de gestion et le
 * responsable financier l'établissent ; la direction le consulte.
 */
class PilotageController extends Controller
{
    public function __construct(
        private readonly TableauDeBordGestion $tableau,
        private readonly BudgetDeGestion $budget,
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

        $this->budget->enregistrer($annee, $saisie, Auth::user());

        return redirect()->route('pilotage.budget.index', ['annee' => $annee])->with('success', "Budget {$annee} enregistré.");
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
