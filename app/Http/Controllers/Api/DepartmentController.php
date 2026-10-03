<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Départements de l'établissement, tenus depuis la console d'orchestration.
 *
 * Ils passaient par des écritures SQL directes de la console dans cette base :
 * elles contournaient l'application, qui ne pouvait ni valider ni tracer. Ils
 * passent désormais par ici.
 *
 * Un département range le personnel ; il ne donne aucun droit (les rôles le
 * font). Les modules qu'une ancienne console enverrait encore sont ignorés.
 */
class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        $effectifs = DB::table('users')->whereNotNull('department_id')
            ->selectRaw('department_id, count(*) as effectif')
            ->groupBy('department_id')->pluck('effectif', 'department_id');

        return response()->json([
            'departements' => Department::query()->orderBy('sort_order')->orderBy('name')->get()
                ->map(static fn (Department $d): array => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'slug' => $d->slug,
                    'code' => $d->code,
                    'description' => $d->description,
                    'icon' => $d->icon,
                    'accent' => $d->accent,
                    'sort_order' => $d->sort_order,
                    'is_active' => $d->is_active,
                    'users_count' => (int) ($effectifs[$d->id] ?? 0),
                ])->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $valide = $this->valider($request);

        $slug = $valide['slug'] ?? '';
        if ($slug === '') {
            $slug = Str::slug($valide['name'], '_');
        }

        if (Department::where('slug', $slug)->exists()) {
            return response()->json([
                'message' => 'Un département porte déjà cet identifiant.',
                'errors' => ['slug' => ['Un département porte déjà cet identifiant.']],
            ], 422);
        }

        $departement = DB::transaction(function () use ($valide, $slug): Department {
            $departement = Department::create([
                'name' => $valide['name'],
                'slug' => $slug,
                'code' => ($valide['code'] ?? '') !== ''
                    ? $valide['code']
                    : (strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $slug), 0, 4)) ?: 'DEPT'),
                'description' => $valide['description'] ?? null,
                'icon' => $valide['icon'] ?? 'briefcase',
                'accent' => $valide['accent'] ?? 'indigo',
                'sort_order' => (int) ($valide['sort_order'] ?? 0),
                'is_active' => $valide['is_active'] ?? true,
            ]);

            return $departement;
        });

        AuditLog::record(null, 'department_console',
            "Département {$departement->name} créé depuis la console", 'users',
            ['department_id' => $departement->id, 'auteur' => $valide['auteur'] ?? null]);

        return response()->json(['id' => $departement->id], 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $valide = $this->valider($request, $department);

        DB::transaction(function () use ($valide, $department): void {
            $department->fill(array_filter([
                'name' => $valide['name'],
                'slug' => ($valide['slug'] ?? '') !== '' ? $valide['slug'] : null,
                'code' => ($valide['code'] ?? '') !== '' ? $valide['code'] : null,
                'icon' => $valide['icon'] ?? null,
                'accent' => $valide['accent'] ?? null,
                'sort_order' => $valide['sort_order'] ?? null,
                'is_active' => $valide['is_active'] ?? null,
            ], static fn ($v) => $v !== null));

            if (array_key_exists('description', $valide)) {
                $department->description = $valide['description'];
            }

            $department->save();
        });

        AuditLog::record(null, 'department_console',
            "Département {$department->name} modifié depuis la console", 'users',
            ['department_id' => $department->id, 'auteur' => $valide['auteur'] ?? null]);

        return response()->json(['id' => $department->id]);
    }

    /** Les employés rattachés sont détachés, pas supprimés. */
    public function destroy(Request $request, Department $department): JsonResponse
    {
        $nom = $department->name;
        $id = $department->id;

        DB::transaction(function () use ($department): void {
            DB::table('users')->where('department_id', $department->id)
                ->update(['department_id' => null, 'updated_at' => now()]);
            $department->delete();
        });

        AuditLog::record(null, 'department_console',
            "Département {$nom} supprimé depuis la console", 'users',
            ['department_id' => $id, 'auteur' => $request->input('auteur')]);

        return response()->json(['supprime' => $id]);
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_-]*$/',
                Rule::unique('departments', 'slug')->ignore($department?->id)],
            'code' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'accent' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'auteur' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
