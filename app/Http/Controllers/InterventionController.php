<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Intervention;
use App\Notifications\InterventionDeclaree;
use App\Services\InterventionTrace;
use App\Services\Notifier;
use App\Services\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Interventions de l'administrateur dans l'exploitation.
 *
 * L'administrateur déclare un motif, une durée et les services concernés ;
 * le moteur de droits lui ouvre alors l'écriture dans ces services, et
 * seulement eux, jusqu'à la fin. Le manager est prévenu, chaque action est
 * marquée au journal, la trace part à la console.
 */
class InterventionController extends Controller
{
    public function __construct(private readonly InterventionTrace $trace) {}

    public function index(): View
    {
        // Une intervention arrivée au bout de sa durée est close ici aussi :
        // l'écran ne doit pas la montrer « en cours ».
        $this->trace->rattraper();

        return view('administration.interventions', [
            'enCours' => Intervention::enCoursPour(Auth::user()),
            'interventions' => Intervention::with('user')->latest('id')->paginate(20),
            'perimetres' => Intervention::PERIMETRES,
            'durees' => Intervention::DUREES,
        ]);
    }

    public function store(Request $request, Notifier $notifier): RedirectResponse
    {
        $admin = Auth::user();

        abort_unless($admin->isAdmin(), 403, "Seul l'administrateur déclare une intervention.");

        $valide = $request->validate([
            'motif' => ['required', 'string', 'min:10', 'max:500'],
            'duree' => ['required', 'integer', Rule::in(Intervention::DUREES)],
            'perimetres' => ['required', 'array', 'min:1'],
            'perimetres.*' => [Rule::in(array_keys(Intervention::PERIMETRES))],
        ], [
            'motif.min' => 'Décrivez le motif en quelques mots : il part au manager et à la console.',
            'perimetres.required' => 'Choisissez au moins un service.',
        ]);

        if (Intervention::enCoursPour($admin)) {
            return back()->with('error', 'Une intervention est déjà en cours : terminez-la avant d\'en ouvrir une autre.');
        }

        $intervention = Intervention::create([
            'user_id' => $admin->id,
            'motif' => $valide['motif'],
            'perimetres' => array_values(array_unique($valide['perimetres'])),
            'debut' => now(),
            'fin_prevue' => now()->addMinutes((int) $valide['duree']),
        ]);

        app(PermissionResolver::class)->forget($admin);

        AuditLog::record($admin->id, 'intervention_debut',
            "Intervention #{$intervention->id} ouverte jusqu'à {$intervention->fin_prevue->format('H:i')} ("
                .implode(', ', $intervention->libellesPerimetres()).") : {$intervention->motif}",
            'security', ['intervention_id' => $intervention->id]);

        $notifier->toRoles(['manager'], new InterventionDeclaree($intervention), $admin->id);

        $transmise = $this->trace->transmettre($intervention);

        return redirect()->route('interventions.index')->with('success',
            "Intervention ouverte jusqu'à {$intervention->fin_prevue->format('H:i')}."
            .($transmise ? '' : ' La console est injoignable : la trace partira dès que possible, marquée tardive.'));
    }

    public function terminer(Intervention $intervention, Notifier $notifier): RedirectResponse
    {
        $admin = Auth::user();

        abort_unless($admin->isAdmin() && $intervention->user_id === $admin->id, 403,
            "Seul l'administrateur qui l'a ouverte termine une intervention.");

        if (! $intervention->estEnCours()) {
            return back()->with('error', 'Cette intervention est déjà close.');
        }

        $intervention->clore(Intervention::TERMINEE);
        app(PermissionResolver::class)->forget($admin);

        AuditLog::record($admin->id, 'intervention_fin',
            "Intervention #{$intervention->id} terminée par l'administrateur", 'security',
            ['intervention_id' => $intervention->id, 'cloture' => Intervention::TERMINEE]);

        $notifier->toRoles(['manager'], new InterventionDeclaree($intervention, ouverture: false), $admin->id);

        $this->trace->transmettre($intervention);

        return redirect()->route('interventions.index')->with('success', 'Intervention terminée.');
    }
}
