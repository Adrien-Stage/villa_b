<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WorkShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Les quarts de l'hôtel (Paramètres › Quarts) : la direction les définit
 * pour tout l'établissement ; les chefs de service y placent leur personnel.
 */
class WorkShiftController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $quart = WorkShift::create($this->valider($request) + [
            'sort_order' => (int) WorkShift::max('sort_order') + 1,
            'is_active' => true,
        ]);

        $this->journal("Quart « {$quart->name} » créé : {$quart->horaire()}", $quart);

        return $this->retour()->with('success', "Quart « {$quart->name} » ajouté.");
    }

    public function update(Request $request, WorkShift $quart): RedirectResponse
    {
        $quart->update($this->valider($request) + ['is_active' => $request->boolean('is_active')]);

        $this->journal("Quart « {$quart->name} » modifié : {$quart->horaire()}" . ($quart->is_active ? '' : ' (hors service)'), $quart);

        return $this->retour()->with('success', "Quart « {$quart->name} » enregistré.");
    }

    public function destroy(WorkShift $quart): RedirectResponse
    {
        // Un quart qui a servi reste dans l'historique des plannings.
        if ($quart->assignments()->exists()) {
            return $this->retour()->withErrors(['quart' => "Le quart « {$quart->name} » figure dans des plannings : mettez-le hors service plutôt que de le supprimer."]);
        }

        $quart->delete();
        $this->journal("Quart « {$quart->name} » supprimé", $quart);

        return $this->retour()->with('success', "Quart « {$quart->name} » supprimé.");
    }

    /** @return array{name: string, starts_at: string, ends_at: string} */
    private function valider(Request $request): array
    {
        $valide = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
        ], [
            'ends_at.different' => 'Un quart ne peut pas finir à l\'heure où il commence.',
        ]);

        return ['name' => trim($valide['name']), 'starts_at' => $valide['starts_at'], 'ends_at' => $valide['ends_at']];
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
