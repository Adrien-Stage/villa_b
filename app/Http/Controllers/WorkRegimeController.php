<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\WorkRegime;
use App\Models\WorkShift;
use App\Services\PlanningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Régimes horaires de l'établissement (Paramètres › Horaires & quarts).
 *
 * Chaque régime décrit soit un horaire fixe (sans quart, avec jours ouvrés),
 * soit un système de quarts / jour-nuit, puis s'applique aux services choisis.
 */
class WorkRegimeController extends Controller
{
    public function __construct(private readonly PlanningService $planning) {}

    public function store(Request $request): RedirectResponse
    {
        $valide = $request->validate([
            'name'              => ['required', 'string', 'max:80'],
            'mode'              => ['required', Rule::in(array_keys(WorkRegime::MODES))],
            'working_days'      => ['nullable', 'array'],
            'working_days.*'    => ['integer', 'between:1,7'],
            'department_ids'    => ['nullable', 'array'],
            'department_ids.*'  => ['integer', 'exists:departments,id'],
            'is_default'        => ['nullable', 'boolean'],
            'starter_name'      => ['nullable', 'string', 'max:60', 'required_with:starter_starts_at,starter_ends_at'],
            'starter_starts_at' => ['nullable', 'date_format:H:i', 'required_with:starter_name,starter_ends_at'],
            'starter_ends_at'   => ['nullable', 'date_format:H:i', 'required_with:starter_name,starter_starts_at', 'different:starter_starts_at'],
        ], [
            'name.required'                  => 'Donnez un nom au régime horaire.',
            'mode.in'                        => 'Choisissez un mode d\'organisation valide.',
            'starter_name.required_with'     => 'Nommez la première plage horaire.',
            'starter_starts_at.required_with' => 'Indiquez l\'heure de début de la plage.',
            'starter_ends_at.required_with'  => 'Indiquez l\'heure de fin de la plage.',
            'starter_ends_at.different'      => 'Une plage horaire ne peut pas finir à l\'heure où elle commence.',
        ]);

        $jours = $valide['mode'] === WorkRegime::MODE_FIXED
            ? $this->normaliserJours($valide['working_days'] ?? [])
            : null;

        if ($valide['mode'] === WorkRegime::MODE_FIXED && $jours === []) {
            throw ValidationException::withMessages([
                'working_days' => 'Choisissez au moins un jour travaillé pour un horaire fixe.',
            ]);
        }

        $estDefaut = $request->boolean('is_default');
        $deptIds = $this->normaliserIds($valide['department_ids'] ?? []);

        $regime = DB::transaction(function () use ($valide, $jours, $estDefaut, $deptIds, $request): WorkRegime {
            if ($estDefaut) {
                WorkRegime::query()->update(['is_default' => false]);
            }

            $regime = WorkRegime::create([
                'name'         => trim($valide['name']),
                'mode'         => $valide['mode'],
                'working_days' => $jours,
                'is_default'   => $estDefaut,
                'is_active'    => true,
                'sort_order'   => (int) WorkRegime::max('sort_order') + 1,
            ]);

            if ($request->filled('starter_name')) {
                WorkShift::create([
                    'work_regime_id' => $regime->id,
                    'name'           => trim((string) $valide['starter_name']),
                    'starts_at'      => $valide['starter_starts_at'],
                    'ends_at'        => $valide['starter_ends_at'],
                    'sort_order'     => (int) WorkShift::max('sort_order') + 1,
                    'is_active'      => true,
                ]);
            }

            if ($deptIds !== []) {
                Department::query()->whereIn('id', $deptIds)->update(['work_regime_id' => $regime->id]);
            }

            return $regime;
        });

        if ($deptIds !== []) {
            foreach (Department::query()->whereIn('id', $deptIds)->get() as $dept) {
                $this->planning->invaliderPreRemplissageFutur(departement: $dept);
            }
        }
        if ($estDefaut) {
            $this->planning->invaliderPreRemplissageFutur(regime: $regime);
        }

        AuditLog::record(
            Auth::id(),
            'work_regime',
            "Régime horaire « {$regime->name} » créé ({$regime->modeLabel()})",
            'settings',
            ['work_regime_id' => $regime->id, 'departments' => $deptIds]
        );

        return $this->retour()->with('success', "Régime horaire « {$regime->name} » ajouté.");
    }

    public function update(Request $request, WorkRegime $regime): RedirectResponse
    {
        $valide = $request->validate([
            'name'             => ['required', 'string', 'max:80'],
            'mode'             => ['required', Rule::in(array_keys(WorkRegime::MODES))],
            'working_days'     => ['nullable', 'array'],
            'working_days.*'   => ['integer', 'between:1,7'],
            'department_ids'   => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
            'is_active'        => ['nullable', 'boolean'],
            'is_default'       => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Le nom du régime horaire est obligatoire.',
            'mode.in'       => 'Choisissez un mode d\'organisation valide.',
        ]);

        $estActif = $request->has('is_active') ? $request->boolean('is_active') : $regime->is_active;
        $estDefaut = $request->has('is_default') ? $request->boolean('is_default') : $regime->is_default;

        if ($estDefaut && ! $estActif) {
            return $this->retour()->withErrors(['regime' => 'Le régime par défaut doit rester actif.']);
        }

        if ($regime->is_default && ! $estDefaut) {
            return $this->retour()->withErrors(['regime' => 'Désignez d\'abord un autre régime par défaut avant de retirer celui-ci.']);
        }

        if ($valide['mode'] === WorkRegime::MODE_FIXED) {
            if ($request->boolean('working_days_present') || $request->has('working_days')) {
                $jours = $this->normaliserJours($valide['working_days'] ?? []);
                if ($jours === []) {
                    throw ValidationException::withMessages([
                        'working_days' => 'Choisissez au moins un jour travaillé pour un horaire fixe.',
                    ]);
                }
            } else {
                $jours = $regime->joursTravailles() ?: [1, 2, 3, 4, 5];
            }
        } else {
            $jours = null;
        }

        $modeAvant = $regime->mode;
        $joursAvant = $regime->joursTravailles();
        $actifAvant = $regime->is_active;
        $defautAvant = $regime->is_default;

        $syncDepts = $request->boolean('department_ids_present') || $request->has('department_ids');
        $nouveauxDepts = $syncDepts ? $this->normaliserIds($valide['department_ids'] ?? []) : [];
        $anciensDepts = $regime->departments()->pluck('id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($regime, $valide, $jours, $estDefaut, $estActif, $syncDepts, $nouveauxDepts) {
            if ($estDefaut && ! $regime->is_default) {
                WorkRegime::query()->where('id', '!=', $regime->id)->update(['is_default' => false]);
            }

            $regime->update([
                'name'         => trim($valide['name']),
                'mode'         => $valide['mode'],
                'working_days' => $jours,
                'is_default'   => $estDefaut,
                'is_active'    => $estActif,
            ]);

            if ($syncDepts) {
                Department::query()
                    ->where('work_regime_id', $regime->id)
                    ->when($nouveauxDepts !== [], fn ($q) => $q->whereNotIn('id', $nouveauxDepts))
                    ->update(['work_regime_id' => null]);

                if ($nouveauxDepts !== []) {
                    Department::query()
                        ->whereIn('id', $nouveauxDepts)
                        ->update(['work_regime_id' => $regime->id]);
                }
            }
        });

        $regime->refresh();

        $parametresModifies = $modeAvant !== $regime->mode
            || $joursAvant !== $regime->joursTravailles()
            || $actifAvant !== $regime->is_active
            || $defautAvant !== $regime->is_default;

        if ($parametresModifies) {
            $this->planning->invaliderPreRemplissageFutur(regime: $regime);
        }

        if ($syncDepts) {
            $deptsTouches = array_values(array_unique(array_merge(
                array_diff($anciensDepts, $nouveauxDepts),
                array_diff($nouveauxDepts, $anciensDepts)
            )));

            if ($deptsTouches !== []) {
                foreach (Department::query()->whereIn('id', $deptsTouches)->get() as $dept) {
                    $this->planning->invaliderPreRemplissageFutur(departement: $dept);
                }
            }
        }

        AuditLog::record(
            Auth::id(),
            'work_regime',
            "Régime horaire « {$regime->name} » modifié ({$regime->modeLabel()})" . ($regime->is_active ? '' : ' (hors service)'),
            'settings',
            ['work_regime_id' => $regime->id]
        );

        return $this->retour()->with('success', "Régime horaire « {$regime->name} » enregistré.");
    }

    public function destroy(WorkRegime $regime): RedirectResponse
    {
        if ($regime->is_default) {
            return $this->retour()->withErrors([
                'regime' => "Le régime par défaut « {$regime->name} » ne peut pas être supprimé.",
            ]);
        }

        if ($regime->shifts()->whereHas('assignments')->exists()) {
            return $this->retour()->withErrors([
                'regime' => "Des plannings utilisent les plages de « {$regime->name} » : mettez-le hors service plutôt que de le supprimer.",
            ]);
        }

        $depts = $regime->departments()->get();
        $users = $regime->users()->get();
        $nom = $regime->name;
        $id = $regime->id;

        $regime->delete();

        foreach ($depts as $dept) {
            $this->planning->invaliderPreRemplissageFutur(departement: $dept);
        }
        foreach ($users as $user) {
            $this->planning->invaliderPreRemplissageFutur(user: $user);
        }

        AuditLog::record(Auth::id(), 'work_regime', "Régime horaire « {$nom} » supprimé", 'settings', ['work_regime_id' => $id]);

        return $this->retour()->with('success', "Régime horaire « {$nom} » supprimé.");
    }

    /** @return list<int> */
    private function normaliserJours(array $jours): array
    {
        $propres = array_values(array_unique(array_filter(
            array_map('intval', $jours),
            static fn (int $j): bool => $j >= 1 && $j <= 7
        )));
        sort($propres);

        return $propres;
    }

    /** @return list<int> */
    private function normaliserIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function retour(): RedirectResponse
    {
        return redirect()->route('settings.index', ['tab' => 'quarts']);
    }
}
