<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use App\Support\StaffDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Comptes du personnel, vus depuis la console d'orchestration.
 *
 * La console liste le personnel et tient les comptes administrateurs — le
 * service informatique de l'hôtel. Elle ne crée aucun autre compte :
 * l'administrateur les crée dans l'application. Personne, dans
 * l'établissement, n'accorde ainsi un niveau égal au sien.
 *
 * Tout passe par cette API, plus aucune écriture directe dans la base : c'est
 * l'application qui sait ce qu'un compte doit porter (affectation, colonne
 * héritée, séparation des tâches).
 */
class StaffAccountController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['comptes' => StaffDirectory::comptes()]);
    }

    public function storeAdmin(Request $request): JsonResponse
    {
        $valide = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'auteur' => ['nullable', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($valide['email']));

        if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
            return response()->json([
                'message' => 'Cette adresse est déjà celle d\'un compte de l\'établissement.',
                'errors' => ['email' => ['Cette adresse est déjà celle d\'un compte de l\'établissement.']],
            ], 422);
        }

        $admin = DB::transaction(function () use ($valide, $email): User {
            $admin = User::create([
                'name' => $valide['name'],
                'email' => $email,
                'phone' => $valide['phone'] ?? null,
                'is_active' => true,
                'password' => Hash::make($valide['password']),
            ]);

            $admin->roles()->sync([$this->roleAdministrateur()->id => ['level' => null]]);

            return $admin;
        });

        AuditLog::record(null, 'admin_account_console',
            "Compte administrateur {$admin->name} <{$admin->email}> créé depuis la console",
            'security', ['user_id' => $admin->id, 'auteur' => $valide['auteur'] ?? null]);

        return response()->json(['id' => $admin->id], 201);
    }

    /**
     * Modifie un compte administrateur : identité, mot de passe, activation.
     * Un autre compte se gère dans l'application, par l'administrateur.
     */
    public function updateAdmin(Request $request, User $user): JsonResponse
    {
        if (! $user->hasRole(RoleCatalog::ADMIN)) {
            return response()->json([
                'message' => "Ce compte n'est pas un administrateur : il se gère dans l'application.",
            ], 422);
        }

        $valide = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
            'auteur' => ['nullable', 'string', 'max:255'],
        ]);

        $changements = [];

        if (array_key_exists('name', $valide)) {
            $user->name = $valide['name'];
            $changements[] = 'identité';
        }
        if ($request->has('phone')) {
            $user->phone = $valide['phone'] ?? null;
        }
        if (! empty($valide['password'])) {
            $user->password = Hash::make($valide['password']);
            $changements[] = 'mot de passe';
        }
        if (array_key_exists('actif', $valide)) {
            $user->is_active = (bool) $valide['actif'];
            $changements[] = $user->is_active ? 'réactivé' : 'désactivé';
        }

        $user->save();

        AuditLog::record(null, 'admin_account_console',
            "Compte administrateur {$user->name} <{$user->email}> modifié depuis la console"
                .($changements === [] ? '' : ' : '.implode(', ', $changements)),
            'security', ['user_id' => $user->id, 'auteur' => $valide['auteur'] ?? null]);

        return response()->json(['id' => $user->id, 'actif' => (bool) $user->is_active]);
    }

    /**
     * Le rôle d'administrateur en base. Un établissement dont les rôles n'ont
     * pas encore été synchronisés le reçoit du référentiel.
     */
    private function roleAdministrateur(): Role
    {
        return Role::firstOrCreate(
            ['slug' => RoleCatalog::ADMIN],
            RoleCatalog::enregistrement(RoleCatalog::ADMIN) ?? ['name' => 'Administrateur']
        );
    }
}
