<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\ShiftAssignment;
use App\Models\ShiftWeek;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\PlanningService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Le planning des quarts, semaine par semaine.
 *
 * Le chef de service y place son personnel sur les quarts de l'hôtel, puis
 * envoie le planning ; la direction voit et planifie tous les services ;
 * chacun consulte ses propres quarts.
 */
class PlanningController extends Controller
{
    public function __construct(private readonly PlanningService $planning) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $lundi = $this->lundiDemande($request);
        $visibles = $this->planning->departementsVisibles($user);

        $commun = [
            'lundi' => $lundi,
            'jours' => PlanningService::jours($lundi),
            'aujourdhui' => CarbonImmutable::today(),
            'mesQuarts' => $this->planning->semaineDe($user, $lundi),
        ];

        // Sans service à suivre : ses propres quarts seulement.
        if ($visibles->isEmpty()) {
            return view('planning.index', $commun + ['departement' => null]);
        }

        $departement = $visibles->firstWhere('id', (int) $request->query('departement'))
            ?? $visibles->firstWhere('id', $user->department_id)
            ?? $visibles->first();

        $affectations = $this->planning->semaine($departement, $lundi);
        $semaine = ShiftWeek::query()->where('department_id', $departement->id)->where('week_start', $lundi->toDateString())->first();
        $peutPlanifier = $this->planning->peutPlanifier($user, $departement, $lundi);
        $prochainLundi = PlanningService::lundi()->addWeek();

        return view('planning.index', $commun + [
            'departement' => $departement,
            'departements' => $visibles,
            'quarts' => $this->planning->quarts(),
            'personnel' => $this->planning->personnel($departement),
            // [date][quart] => affectations
            'grille' => $affectations->groupBy([fn ($a) => $a->jour()->toDateString(), 'work_shift_id']),
            'heures' => $affectations->groupBy('user_id')->map(fn ($quarts) => [
                'quarts' => $quarts->count(),
                'heures' => $quarts->sum(fn ($a) => $a->shift->duree()),
            ]),
            'semaine' => $semaine,
            'modifiee' => $this->planning->modifieeDepuisEnvoi($departement, $lundi, $semaine),
            'peutPlanifier' => $peutPlanifier,
            'enService' => $this->planning->enService([$departement->id]),
            // Le dimanche, tant que la semaine suivante n'est pas envoyée.
            'rappel' => CarbonImmutable::today()->isSunday()
                && $this->planning->departementsPlanifiables($user)->contains('id', $departement->id)
                && ! ShiftWeek::query()->where('department_id', $departement->id)->where('week_start', $prochainLundi->toDateString())->whereNotNull('published_at')->exists()
                    ? $prochainLundi : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'work_shift_id' => ['required', 'integer', 'exists:work_shifts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ], [
            'user_id.required' => 'Choisissez la personne à placer sur ce quart.',
        ]);

        $departement = Department::findOrFail($valide['department_id']);
        $employe = User::findOrFail($valide['user_id']);
        $jour = CarbonImmutable::parse($valide['date']);

        $this->planning->affecter(Auth::user(), $departement, $employe, WorkShift::findOrFail($valide['work_shift_id']), $jour);

        return $this->retour($departement, PlanningService::lundi($jour))
            ->with('success', "{$employe->name} : quart ajouté le " . $jour->locale('fr')->isoFormat('dddd D MMMM') . '.');
    }

    public function destroy(ShiftAssignment $affectation): RedirectResponse
    {
        $affectation->load(['user:id,name', 'department']);
        $this->planning->retirer(Auth::user(), $affectation);

        return $this->retour($affectation->department, PlanningService::lundi($affectation->jour()))
            ->with('success', "{$affectation->user->name} : quart retiré.");
    }

    public function recopier(Request $request): RedirectResponse
    {
        [$departement, $lundi] = $this->departementEtSemaine($request);

        $ajoutes = $this->planning->recopier(Auth::user(), $departement, $lundi);

        return $this->retour($departement, $lundi)->with('success', $ajoutes > 0
            ? "{$ajoutes} quart(s) recopié(s) de la semaine précédente."
            : 'Rien à recopier : la semaine précédente est vide, ou déjà reprise.');
    }

    public function publier(Request $request): RedirectResponse
    {
        [$departement, $lundi] = $this->departementEtSemaine($request);

        $prevenus = $this->planning->publier(Auth::user(), $departement, $lundi);

        AuditLog::record(Auth::id(), 'planning', "Planning de la semaine du {$lundi->format('d/m/Y')} envoyé — {$departement->name}", 'planning', [
            'department_id' => $departement->id,
            'week_start' => $lundi->toDateString(),
            'notified' => $prevenus,
        ]);

        return $this->retour($departement, $lundi)->with('success', $prevenus > 0
            ? "Planning envoyé : {$prevenus} personne(s) prévenue(s) de leurs quarts."
            : 'Planning envoyé. Personne n\'avait de changement à recevoir.');
    }

    /** @return array{0: Department, 1: CarbonImmutable} */
    private function departementEtSemaine(Request $request): array
    {
        $valide = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'semaine' => ['required', 'date_format:Y-m-d'],
        ]);

        return [Department::findOrFail($valide['department_id']), PlanningService::lundi($valide['semaine'])];
    }

    private function lundiDemande(Request $request): CarbonImmutable
    {
        $semaine = $request->query('semaine');

        return is_string($semaine) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $semaine)
            ? PlanningService::lundi($semaine)
            : PlanningService::lundi();
    }

    private function retour(?Department $departement, CarbonImmutable $lundi): RedirectResponse
    {
        return redirect()->route('planning.index', array_filter([
            'semaine' => $lundi->toDateString(),
            'departement' => $departement?->id,
        ]));
    }
}
