<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Rôles qu'un manager peut attribuer : lus depuis la table (drapeau
     * is_assignable). Tout rôle ajouté au référentiel apparaît automatiquement
     * dans le formulaire — plus de liste codée en dur.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles()
    {
        return Role::assignable()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function index(Request $request): View
    {
        $manager = Auth::user();

        $assignableRoles = $this->assignableRoles();
        // Regroupées par module pour l'affichage en cartes du formulaire.
        $rolesByModule = $assignableRoles->groupBy('module');

        $departments = Department::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        $deptMap = [];
        foreach ($departments as $dept) {
            $resolved = $dept->resolveMatchingRoles($assignableRoles);
            $deptMap[$dept->id] = [
                'id'     => $dept->id,
                'name'   => $dept->name,
                'code'   => $dept->code,
                'roles'  => $resolved['roles'],
                'levels' => $resolved['levels'],
            ];
        }

        $query = User::query()
            ->where('id', '!=', $manager->id)
            ->whereNotIn('role', ['admin', 'manager'])
            ->with(['roles', 'department']);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('role')) {
            // Filtre sur l'un des rôles (nouveau système pivot ou colonne).
            $query->havingRole([$request->role]);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $stats = [
            'total' => User::whereNotIn('role', ['admin', 'manager'])->count(),
            'active' => User::whereNotIn('role', ['admin', 'manager'])->where('is_active', true)->count(),
            'inactive' => User::whereNotIn('role', ['admin', 'manager'])->where('is_active', false)->count(),
        ];

        $staffUsers = $query->latest('id')->paginate(15)->withQueryString();

        return view('users.index', [
            'staffUsers' => $staffUsers,
            'departments' => $departments,
            'deptMap' => $deptMap,
            'roles' => $assignableRoles,
            'rolesByModule' => $rolesByModule,
            'moduleLabels' => Role::MODULES,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = Auth::user();

        $validated = $this->validatePayload($request);
        [$roleSlugs, $levels] = $this->extractRoles($validated);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            // La colonne role garde le rôle principal (1er sélectionné), pour
            // les consommateurs mono-rôle ; l'accès complet vit dans le pivot.
            'role' => $roleSlugs[0],
            'is_active' => $request->boolean('is_active', true),
            'password' => Hash::make($validated['password']),
        ]);

        $this->syncUserRoles($user, $roleSlugs, $levels);

        AuditLog::record($manager->id, 'user_management',
            "Création de l'utilisateur {$user->name} ({$user->email}) — rôles : ".implode(', ', $roleSlugs),
            'users', ['target_user_id' => $user->id, 'roles' => $roleSlugs]);

        return redirect()
            ->route('users.index', $this->resolveViewMode($request))
            ->with('success', 'Membre du staff créé avec succès.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageableByCurrentManager($user);

        $validated = $this->validatePayload($request, $user);
        [$roleSlugs, $levels] = $this->extractRoles($validated);

        $payload = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'role' => $roleSlugs[0],
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);
        $this->syncUserRoles($user, $roleSlugs, $levels);

        AuditLog::record(Auth::id(), 'user_management',
            "Modification de l'utilisateur {$user->name} ({$user->email}) — rôles : ".implode(', ', $roleSlugs),
            'users', ['target_user_id' => $user->id, 'roles' => $roleSlugs]);

        return redirect()
            ->route('users.index', $this->resolveViewMode($request))
            ->with('success', 'Profil staff mis à jour avec succès.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->ensureManageableByCurrentManager($user);

        $user->update(['is_active' => ! $user->is_active]);
        $statusStr = $user->is_active ? 'réactivé' : 'désactivé';

        AuditLog::record(Auth::id(), 'user_management',
            "Le compte de {$user->name} ({$user->email}) a été {$statusStr}",
            'users', ['target_user_id' => $user->id, 'is_active' => $user->is_active]);

        return redirect()
            ->route('users.index', $this->resolveViewMode(request()))
            ->with('success', $user->is_active ? 'Compte staff réactivé.' : 'Compte staff désactivé.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function validatePayload(Request $request, ?User $user = null): array
    {
        $assignableSlugs = $this->assignableRoles()->pluck('slug')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in($assignableSlugs)],
            // Niveau par rôle : lecture ou lecture/écriture.
            'levels' => ['nullable', 'array'],
            'levels.*' => [Rule::in(['read', 'write'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'roles.required' => 'Sélectionnez au moins un rôle.',
            'roles.*.in' => 'Un des rôles sélectionnés n\'est pas autorisé.',
        ]);
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, string>}
     */
    private function extractRoles(array $validated): array
    {
        $slugs = array_values(array_unique($validated['roles']));
        $levels = $validated['levels'] ?? [];

        return [$slugs, $levels];
    }

    /**
     * Synchronise les rôles de l'utilisateur avec leur niveau d'accès. Un
     * niveau absent vaut « write » (comportement historique, non restrictif).
     */
    private function syncUserRoles(User $user, array $slugs, array $levels): void
    {
        $roles = Role::whereIn('slug', $slugs)->get();

        $pivot = [];
        foreach ($roles as $role) {
            $level = $levels[$role->slug] ?? 'write';
            $pivot[$role->id] = ['level' => $level === 'read' ? 'read' : 'write'];
        }

        $user->roles()->sync($pivot);
    }

    private function ensureManageableByCurrentManager(User $user): void
    {
        if (in_array($user->role, ['admin', 'manager'], true)) {
            abort(403, 'Ce profil ne peut pas être géré par un manager.');
        }
    }

    private function resolveViewMode(Request $request): array
    {
        $view = $request->input('view');

        return in_array($view, ['list', 'cards'], true) ? ['view' => $view] : [];
    }
}
