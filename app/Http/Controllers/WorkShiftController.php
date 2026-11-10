<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WorkRegime;
use App\Models\WorkShift;
use App\Services\PlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Plages horaires et quarts de l'hôtel (Paramètres › Horaires & quarts) :
 * la direction définit chaque plage au sein d'un régime horaire ; les chefs
 * de service y placent leur personnel dans le planning.
 */
class WorkShiftController extends Controller
{
    public function __construct(private readonly PlanningService $planning) {}

    public function store(Request $request): RedirectResponse
    {
        $valide = $this->valider($request);
        $regimeId = $valide['work_regime_id'] ?? WorkRegime::defaut()?->id;

        $quart = WorkShift::create([
            'work_regime_id' => $regimeId,
            'name'           => $valide['name'],
            'starts_at'      => $valide['starts_at'],
            'ends_at'        => $valide['ends_at'],
            'sort_order'     => (int) WorkShift::max('sort_order') + 1,
            'is_active'      => true,
        ]);

        $quart->load('regime');
        if ($quart->regime?->estFixe()) {
            $this->planning->invaliderPreRemplissageFutur(regime: $quart->regime);
        }

        $this->journal("Plage horaire « {$quart->name} » créée : {$quart->horaire()}", $quart);

        return $this->retour()->with('success', "Quart « {$quart->name} » ajouté.");
    }

    public function update(Request $request, WorkShift $quart): RedirectResponse
    {
        $valide = $this->valider($request);
        $ancienRegime = $quart->regime;
        $etaitActif = $quart->is_active;
        $horaireAvant = $quart->horaire();

        $nouveauRegimeId = array_key_exists('work_regime_id', $valide) && $valide['work_regime_id'] !== null
            ? $valide['work_regime_id']
            : $quart->work_regime_id;

        $quart->update([
            'work_regime_id' => $nouveauRegimeId,
            'name'           => $valide['name'],
            'starts_at'      => $valide['starts_at'],
            'ends_at'        => $valide['ends_at'],
            'is_active'      => $request->boolean('is_active'),
        ]);

        $quart->refresh()->load('regime');

        if ($ancienRegime?->estFixe() && ($ancienRegime->id !== $quart->work_regime_id || $etaitActif !== $quart->is_active)) {
            $this->planning->invaliderPreRemplissageFutur(regime: $ancienRegime);
        }
        if ($quart->regime?->estFixe() && ($etaitActif !== $quart->is_active || $horaireAvant !== $quart->horaire() || $ancienRegime?->id !== $quart->work_regime_id)) {
            $this->planning->invaliderPreRemplissageFutur(regime: $quart->regime);
        }

        $this->journal("Plage horaire « {$quart->name} » modifiée : {$quart->horaire()}" . ($quart->is_active ? '' : ' (hors service)'), $quart);

        return $this->retour()->with('success', "Quart « {$quart->name} » enregistré.");
    }

    public function destroy(WorkShift $quart): RedirectResponse
    {
        // Un quart qui a servi reste dans l'historique des plannings.
        if ($quart->assignments()->exists()) {
            return $this->retour()->withErrors(['quart' => "Le quart « {$quart->name} » figure dans des plannings : mettez-le hors service plutôt que de le supprimer."]);
        }

        $regime = $quart->regime;
        $nom = $quart->name;

        $quart->delete();

        if ($regime?->estFixe()) {
            $this->planning->invaliderPreRemplissageFutur(regime: $regime);
        }

        $this->journal("Plage horaire « {$nom} » supprimée", $quart);

        return $this->retour()->with('success', "Quart « {$nom} » supprimé.");
    }

    /** @return array{work_regime_id: int|null, name: string, starts_at: string, ends_at: string} */
    private function valider(Request $request): array
    {
        $valide = $request->validate([
            'work_regime_id' => ['nullable', 'integer', 'exists:work_regimes,id'],
            'name'           => ['required', 'string', 'max:60'],
            'starts_at'      => ['required', 'date_format:H:i'],
            'ends_at'        => ['required', 'date_format:H:i', 'different:starts_at'],
        ], [
            'ends_at.different' => 'Un quart ne peut pas finir à l\'heure où il commence.',
        ]);

        return [
            'work_regime_id' => isset($valide['work_regime_id']) ? (int) $valide['work_regime_id'] : null,
            'name'           => trim($valide['name']),
            'starts_at'      => $valide['starts_at'],
            'ends_at'        => $valide['ends_at'],
        ];
    }

    private function journal(string $action, WorkShift $quart): void
    {
        AuditLog::record(Auth::id(), 'work_shift', $action, 'settings', ['work_shift_id' => $quart->id]);
    }

    private function retour(): RedirectResponse
    {
        return redirect()->route('settings.index', ['tab' => 'quarts']);
    }
}
