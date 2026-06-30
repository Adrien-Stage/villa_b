<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    /**
     * Roles managed by hotel manager (no admin, no manager peer management).
     */
    private const MANAGEABLE_ROLE_SLUGS = [
        'reception',
        'housekeeping_leader',
        'housekeeping_staff',
        'restaurant_chief',
        'restaurant_staff',
        'cashier',
        'accountant',
        'maintenance',
    ];

    public function index(Request $request): View
    {
        $manager = Auth::user();

        $roles = Role::query()
            ->whereIn('slug', self::MANAGEABLE_ROLE_SLUGS)
            ->orderBy('name')
            ->get();

        $query = User::query()
            
            ->where('id', '!=', $manager->id)
            ->whereNotIn('role', ['admin', 'manager'])
            ->with('roles');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $stats = [
            'total' => User::query()
                ->whereNotIn('role', ['admin', 'manager'])
                ->count(),
            'active' => User::query()
                ->whereNotIn('role', ['admin', 'manager'])
                ->where('is_active', true)
                ->count(),
            'inactive' => User::query()
                ->whereNotIn('role', ['admin', 'manager'])
                ->where('is_active', false)
                ->count(),
        ];

        $staffUsers = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', compact('staffUsers', 'roles', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::MANAGEABLE_ROLE_SLUGS)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active', true),
            'password' => Hash::make($validated['password']),
        ]);

        $this->syncUserRole($user, $validated['role']);

        \App\Models\AuditLog::record(
            $manager->id,
            'user_management',
            "Création de l'utilisateur : {$user->name} ({$user->email}) avec le rôle {$user->role}",
            'users',
            ['target_user_id' => $user->id, 'role' => $user->role]
        );

        return redirect()
            ->route('users.index', $this->resolveViewMode($request))
            ->with('success', 'Membre du staff cree avec succes.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageableByCurrentManager($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(self::MANAGEABLE_ROLE_SLUGS)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'is_active' => $request->boolean('is_active'),
        ];

        if (!empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);
        $this->syncUserRole($user, $validated['role']);

        \App\Models\AuditLog::record(
            Auth::id(),
            'user_management',
            "Modification de l'utilisateur : {$user->name} ({$user->email})",
            'users',
            ['target_user_id' => $user->id]
        );

        return redirect()
            ->route('users.index', $this->resolveViewMode($request))
            ->with('success', 'Profil staff mis a jour avec succes.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $this->ensureManageableByCurrentManager($user);

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $statusStr = $user->is_active ? 'réactivé' : 'désactivé';

        \App\Models\AuditLog::record(
            Auth::id(),
            'user_management',
            "Le compte de l'utilisateur {$user->name} ({$user->email}) a été {$statusStr}",
            'users',
            ['target_user_id' => $user->id, 'is_active' => $user->is_active]
        );

        $message = $user->is_active
            ? 'Compte staff reactive avec succes.'
            : 'Compte staff desactive avec succes.';

        return redirect()
            ->route('users.index', $this->resolveViewMode(request()))
            ->with('success', $message);
    }

    private function syncUserRole(User $user, string $roleSlug): void
    {
        $role = Role::where('slug', $roleSlug)->first();

        if (!$role) {
            return;
        }

        $user->roles()->sync([$role->id]);
    }

    private function ensureManageableByCurrentManager(User $user): void
    {
        $manager = Auth::user();

        

        if (in_array($user->role, ['admin', 'manager'], true)) {
            abort(403, 'Ce profil ne peut pas etre gere par un manager.');
        }
    }

    private function resolveViewMode(Request $request): array
    {
        $view = $request->input('view');

        if (in_array($view, ['list', 'cards'], true)) {
            return ['view' => $view];
        }

        return [];
    }
}
