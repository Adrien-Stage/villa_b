<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\DutySegregation;
use App\Support\RoleCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    /**
     * Rôles que la personne connectée peut attribuer : lus depuis la table
     * (drapeau is_assignable). Tout rôle ajouté au référentiel apparaît
     * automatiquement dans le formulaire — plus de liste codée en dur.
     *
     * L'administrateur crée tous les comptes, managers compris. Personne,
     * dans l'établissement, n'attribue le rôle d'administrateur : ces comptes
     * se créent depuis la console d'orchestration.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles()
    {
        return Role::query()
            ->where(fn ($q) => $q->where('is_assignable', true)
                ->when(Auth::user()?->isAdmin(), fn ($q) => $q->orWhere('slug', RoleCatalog::MANAGER)))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Comptes que la personne connectée ne gère pas : les administrateurs
     * pour tous, les managers pour qui n'est pas administrateur.
     *
     * @return list<string>
     */
    private function rolesHorsDePortee(): array
    {
        return Auth::user()?->isAdmin()
            ? [RoleCatalog::ADMIN]
            : [RoleCatalog::ADMIN, RoleCatalog::MANAGER];
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
                // Le formulaire demande alors le restaurant d'affectation.
                'restauration' => $dept->estLaRestauration(),
            ];
        }

        $horsDePortee = $this->rolesHorsDePortee();

        $query = User::query()
            ->where('id', '!=', $manager->id)
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', $horsDePortee))
            // Le département borne la liste quand la matrice le demande : un
            // chef de service n'a pas à consulter le dossier de ceux qu'il
            // n'encadre pas.
            ->tap(fn ($q) => \App\Support\DepartmentScoping::apply(
                $q, Auth::user(), 'users.voir', 'department_id', 'id'
            ))
            ->with(['roles', 'department', 'restaurants:id']);

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
            'total' => User::whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', $horsDePortee))->count(),
            'active' => User::whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', $horsDePortee))->where('is_active', true)->count(),
            'inactive' => User::whereDoesntHave('roles', fn ($q) => $q->whereIn('slug', $horsDePortee))->where('is_active', false)->count(),
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

    /**
     * Fiche d'un membre du personnel : ses rôles, son périmètre, ses
     * exceptions, et ce que le moteur de droits lui accorde réellement.
     */
    public function show(User $user): View
    {
        // La portée de users.voir borne aussi la fiche : un chef de service ne
        // consulte pas le dossier de ceux qu'il n'encadre pas.
        $visible = User::query()->whereKey($user->id)
            ->tap(fn ($q) => \App\Support\DepartmentScoping::apply($q, Auth::user(), 'users.voir', 'department_id', 'id'))
            ->exists();
        abort_unless($visible, 404);

        $user->load(['roles', 'department']);
        $resolveur = app(\App\Services\PermissionResolver::class);

        $droitsParModule = [];
        foreach (array_keys(\App\Support\PermissionCatalog::all()) as $droit) {
            if ($resolveur->allows($user, $droit)) {
                $droitsParModule[explode('.', $droit)[0]][] = $droit;
            }
        }
        ksort($droitsParModule);

        return view('users.show', [
            'membre' => $user,
            'droitsParModule' => $droitsParModule,
            'ecritures' => array_flip(\App\Support\PermissionCatalog::ecritures()),
            'portees' => collect(\App\Support\PermissionScope::DROITS_BORNES)
                ->mapWithKeys(fn ($d) => [$d => \App\Support\PermissionScope::libelle($resolveur->scopeFor($user, $d))])->all(),
            'exceptions' => \App\Models\PermissionGrant::query()->enVigueur()
                ->where('subject_type', \App\Models\PermissionGrant::SUJET_USER)
                ->where('subject_id', (string) $user->id)->orderBy('permission')->get(),
            'cumuls' => DutySegregation::conflictsFor($user->rolesDetenus()),
            'catalogue' => array_keys(\App\Support\PermissionCatalog::all()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $manager = Auth::user();

        $validated = $this->validatePayload($request);
        [$roleSlugs, $levels] = $this->extractRoles($validated);

        if ($refus = $this->refuserCumulIncompatible($request, $roleSlugs)) {
            return $refus;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'password' => Hash::make($validated['password']),
        ]);

        $this->syncUserRoles($user, $roleSlugs, $levels);
        $this->syncRestaurants($user, $validated, $roleSlugs);

        AuditLog::record($manager->id, 'user_management',
            "Création de l'utilisateur {$user->name} ({$user->email}) — rôles : ".implode(', ', $roleSlugs),
            'users', ['target_user_id' => $user->id, 'roles' => $roleSlugs, 'restaurants' => $user->restaurants()->pluck('points_of_sale.id')->all()]);

        return redirect()
            ->route('users.index', $this->resolveViewMode($request))
            ->with('success', 'Membre du staff créé avec succès.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageableByCurrentManager($user);

        $validated = $this->validatePayload($request, $user);
        [$roleSlugs, $levels] = $this->extractRoles($validated);

        if ($refus = $this->refuserCumulIncompatible($request, $roleSlugs)) {
            return $refus;
        }

        $payload = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'department_id' => $validated['department_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = Hash::make($validated['password']);
        }

        $user->update($payload);
        $this->syncUserRoles($user, $roleSlugs, $levels);
        $this->syncRestaurants($user->fresh(), $validated, $roleSlugs);

        AuditLog::record(Auth::id(), 'user_management',
            "Modification de l'utilisateur {$user->name} ({$user->email}) — rôles : ".implode(', ', $roleSlugs),
            'users', ['target_user_id' => $user->id, 'roles' => $roleSlugs, 'restaurants' => $user->restaurants()->pluck('points_of_sale.id')->all()]);

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

    /**
     * Restaurant où travaille la personne.
     *
     * Un membre du département Restauration est affecté au restaurant choisi
     * dans le formulaire. Choisir un restaurant où il travaille déjà ne
     * touche pas aux autres — une personne peut appartenir à plusieurs
     * équipes, composées depuis Paramètres › Restaurant ; en choisir un autre
     * l'y mute.
     *
     * Hors de ce département, rien ne change, sauf dans un hôtel d'un seul
     * restaurant : son personnel y est rattaché d'office, pour qu'un second
     * restaurant ouvert plus tard ne le laisse pas sans équipe.
     *
     * @param  list<string>  $roleSlugs
     */
    private function syncRestaurants(User $user, array $validated, array $roleSlugs): void
    {
        $contexte = app(\App\Services\RestaurantContext::class);

        if (! empty($validated['restaurant_id']) && $user->department?->estLaRestauration()) {
            $choisi = (int) $validated['restaurant_id'];

            if (! $user->restaurants()->where('points_of_sale.id', $choisi)->exists()) {
                $user->restaurants()->sync([$choisi]);
            }
        } elseif (! $contexte->plusieurs() && array_intersect($roleSlugs, \App\Services\RestaurantContext::ROLES_DU_RESTAURANT) !== []
            && ($unique = $contexte->restaurants()->first())) {
            $user->restaurants()->syncWithoutDetaching([$unique->id]);
        }

        $contexte->oublier();
    }

    private function validatePayload(Request $request, ?User $user = null): array
    {
        $assignableSlugs = $this->assignableRoles()->pluck('slug')->all();

        $validated = $request->validate([
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
            // Restaurant où travaille un membre du département Restauration.
            'restaurant_id' => ['nullable', 'integer', Rule::exists('points_of_sale', 'id')
                ->where('kind', \App\Models\PointOfSale::KIND_RESTAURATION)->where('is_active', true)],
            // Dérogation à la séparation des tâches : un établissement de six
            // personnes ne peut pas toujours séparer quatre fonctions. Elle se
            // demande explicitement et se motive.
            'derogation' => ['nullable', 'boolean'],
            'derogation_motif' => ['nullable', 'string', 'max:255', 'required_if:derogation,1'],
        ], [
            'roles.required' => 'Sélectionnez au moins un rôle.',
            'roles.*.in' => 'Un des rôles sélectionnés n\'est pas autorisé.',
            'derogation_motif.required_if' => 'Indiquez pourquoi ce cumul est accordé malgré tout.',
            'restaurant_id.exists' => 'Ce restaurant n\'existe pas ou est fermé.',
        ]);

        // Le département Restauration travaille dans un restaurant précis :
        // le formulaire le demande, le serveur l'exige.
        $departement = isset($validated['department_id']) ? Department::find($validated['department_id']) : null;
        if ($departement?->estLaRestauration() && empty($validated['restaurant_id'])
            && \App\Support\TenantModules::has('restaurant')
            && app(\App\Services\RestaurantContext::class)->restaurants()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'restaurant_id' => 'Choisissez le restaurant où travaillera ce membre de la restauration.',
            ]);
        }

        return $validated;
    }

    /**
     * Refuse un cumul de rôles qui casse la séparation des tâches, sauf
     * dérogation motivée.
     *
     * Quatre fonctions doivent rester dans des mains différentes : autoriser,
     * détenir, enregistrer, contrôler. Qui en cumule deux sur un même cycle
     * peut commettre un acte et le dissimuler. Le cas relevé sur un compte
     * réel : économe, comptable et auditeur qualité sur une seule personne,
     * soit la détention du stock, sa comptabilisation et le contrôle des deux.
     *
     * Le refus n'est pas absolu. Un établissement de six personnes ne peut pas
     * séparer quatre fonctions : la dérogation existe, elle se demande
     * explicitement, elle exige un motif, et elle est tracée.
     */
    private function refuserCumulIncompatible(Request $request, array $roleSlugs): ?RedirectResponse
    {
        $conflits = DutySegregation::conflictsFor($roleSlugs);

        if ($conflits === []) {
            return null;
        }

        if ($request->boolean('derogation')) {
            AuditLog::record(
                Auth::id(),
                'duty_segregation_override',
                'Dérogation à la séparation des tâches — cumul : '
                    . implode(' ; ', array_map(
                        static fn (array $c): string => implode(' × ', $c['roles']),
                        $conflits
                    ))
                    . ' — motif : ' . $request->string('derogation_motif'),
                'security',
                ['roles' => $roleSlugs, 'conflits' => $conflits]
            );

            return null;
        }

        $messages = array_map(
            static fn (array $c): string => implode(' × ', $c['roles']) . ' — ' . $c['motif'],
            $conflits
        );

        return redirect()->back()
            ->withInput()
            ->withErrors(['roles' => $messages])
            ->with('duty_segregation_conflits', $messages);
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
        // Dans l'ordre de la sélection : le premier rôle choisi est le rôle
        // principal.
        $roles = Role::whereIn('slug', $slugs)->get()
            ->sortBy(fn (Role $role) => array_search($role->slug, $slugs, true));

        $pivot = [];
        foreach ($roles as $role) {
            $level = $levels[$role->slug] ?? 'write';
            $pivot[$role->id] = ['level' => $level === 'read' ? 'read' : 'write'];
        }

        $user->roles()->sync($pivot);
    }

    /**
     * Un administrateur se gère depuis la console d'orchestration ; un
     * manager, par l'administrateur seulement.
     */
    private function ensureManageableByCurrentManager(User $user): void
    {
        if ($user->hasRole(RoleCatalog::ADMIN)) {
            abort(403, 'Un compte administrateur se gère depuis la console d\'orchestration.');
        }

        if ($user->hasRole(RoleCatalog::MANAGER) && ! Auth::user()?->isAdmin()) {
            abort(403, 'Ce profil ne peut pas être géré par un manager.');
        }
    }

    private function resolveViewMode(Request $request): array
    {
        $view = $request->input('view');

        return in_array($view, ['list', 'cards'], true) ? ['view' => $view] : [];
    }
}
