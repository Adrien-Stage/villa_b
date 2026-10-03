<?php

use App\Support\PermissionCatalog;
use App\Support\RepriseDesRoles;
use App\Support\RoleCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Nettoyage des droits hérités.
 *
 * Les droits d'une personne ne viennent plus que de ses affectations (rôle,
 * éventuellement tenu en lecture seule), des exceptions posées sur un rôle ou
 * sur elle, et d'une intervention déclarée. Ce qui restait des systèmes
 * précédents disparaît :
 *
 *   - la colonne users.role, que les affectations ont remplacée : un compte
 *     qui n'existait encore que par elle reçoit l'affectation correspondante ;
 *   - les restrictions de module posées par l'ancienne console
 *     (user_module_permissions), que rien n'écrit plus : chacune devient une
 *     exception nominative de l'établissement — un refus par droit du
 *     service —, visible et modifiable sur la fiche de la personne ;
 *   - les modules par département (department_module) : un département
 *     range le personnel, il ne donne aucun droit ;
 *   - le rôle hérité « housekeeping », repris par housekeeping_staff.
 */
return new class extends Migration
{
    /**
     * Services des droits, figés à la date de la migration : c'est ainsi que
     * le moteur rattachait un droit à une restriction de module.
     */
    private const SERVICES = [
        'rooms' => 'hebergement', 'bookings' => 'hebergement', 'groups' => 'hebergement',
        'customers' => 'hebergement', 'reception' => 'hebergement', 'agenda' => 'hebergement',
        'housekeeping' => 'housekeeping',
        'restaurant' => 'restaurant',
        'economat' => 'economat',
        'shop' => 'boutique',
        'settings' => 'parametres',
    ];

    /** Noms d'un même service dans l'ancienne console. */
    private const ALIAS = [
        'boutique' => ['boutique', 'shop'],
        'hebergement' => ['hebergement', 'reservations', 'clients'],
        'parametres' => ['parametres', 'it'],
    ];

    public function up(): void
    {
        RoleCatalog::sync();

        $this->affecterLesComptesSansAffectation();
        $this->reprendreLeRoleHousekeeping();
        $this->convertirLesRestrictionsDeModule();

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['role']);
            });
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        Schema::dropIfExists('user_module_permissions');
        Schema::dropIfExists('department_module');
    }

    /**
     * Un compte qui n'existait que par sa colonne reçoit l'affectation
     * correspondante. La reprise de la phase 3 l'a déjà fait partout ; ce
     * filet couvre un compte créé depuis par un outil qui écrivait encore la
     * colonne.
     */
    private function affecterLesComptesSansAffectation(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            return;
        }

        $roles = DB::table('roles')->pluck('id', 'slug');

        $comptes = DB::table('users')
            ->whereNotNull('role')->where('role', '!=', '')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('role_user')->whereColumn('role_user.user_id', 'users.id'))
            ->get(['id', 'role']);

        foreach ($comptes as $compte) {
            $slug = RepriseDesRoles::EQUIVALENTS[$compte->role] ?? $compte->role;

            if (! isset($roles[$slug])) {
                Log::warning("Nettoyage des droits : le compte {$compte->id} n'avait que le rôle inconnu « {$compte->role} » ; il n'en détient plus aucun.");

                continue;
            }

            DB::table('role_user')->insertOrIgnore([
                'user_id' => $compte->id, 'role_id' => $roles[$slug], 'level' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** Le rôle hérité « housekeeping » : ses titulaires et ses exceptions passent à housekeeping_staff. */
    private function reprendreLeRoleHousekeeping(): void
    {
        $ancien = DB::table('roles')->where('slug', 'housekeeping')->value('id');
        $nouveau = DB::table('roles')->where('slug', 'housekeeping_staff')->value('id');

        if ($ancien === null || $nouveau === null) {
            return;
        }

        foreach (DB::table('role_user')->where('role_id', $ancien)->get(['user_id', 'level']) as $affectation) {
            DB::table('role_user')->insertOrIgnore([
                'user_id' => $affectation->user_id, 'role_id' => $nouveau, 'level' => $affectation->level,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (DB::table('permission_grants')->where('subject_type', 'role')->where('subject_id', 'housekeeping')->get() as $exception) {
            $dejaPose = DB::table('permission_grants')->where('subject_type', 'role')->where('subject_id', 'housekeeping_staff')
                ->where('permission', $exception->permission)->where('origin', $exception->origin)->exists();

            if (! $dejaPose) {
                DB::table('permission_grants')->where('id', $exception->id)->update(['subject_id' => 'housekeeping_staff']);
            }
        }

        DB::table('permission_grants')->where('subject_type', 'role')->where('subject_id', 'housekeeping')->delete();
        DB::table('role_user')->where('role_id', $ancien)->delete();
        DB::table('roles')->where('id', $ancien)->delete();
    }

    /**
     * Chaque restriction de module devient une exception nominative : un refus
     * par droit du service — tous pour une exclusion, les écritures pour une
     * lecture seule. La personne garde exactement ce qu'elle pouvait faire.
     */
    private function convertirLesRestrictionsDeModule(): void
    {
        if (! Schema::hasTable('user_module_permissions')) {
            return;
        }

        $restrictions = DB::table('user_module_permissions')
            ->whereIn('access_level', ['none', 'read'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'user_module_permissions.user_id'))
            ->get(['user_id', 'module_key', 'access_level']);

        foreach ($restrictions as $restriction) {
            foreach (array_keys(PermissionCatalog::all()) as $droit) {
                $service = self::SERVICES[explode('.', $droit)[0]] ?? null;

                if ($service === null || ! in_array($restriction->module_key, self::ALIAS[$service] ?? [$service], true)) {
                    continue;
                }

                if ($restriction->access_level === 'read' && PermissionCatalog::estLecture($droit)) {
                    continue;
                }

                DB::table('permission_grants')->insertOrIgnore([
                    'subject_type' => 'user',
                    'subject_id' => (string) $restriction->user_id,
                    'permission' => $droit,
                    'effect' => 'deny',
                    'origin' => 'etablissement',
                    'reason' => $restriction->access_level === 'none'
                        ? "Reprise de l'ancienne console : service {$restriction->module_key} exclu"
                        : "Reprise de l'ancienne console : service {$restriction->module_key} en lecture seule",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Les tables reviennent vides : les restrictions converties restent des
     * exceptions nominatives, et un département ne donne plus de droits. La
     * colonne reprend le premier rôle affecté.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 30)->nullable()->after('email');
                $table->index(['role']);
            });

            foreach (DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->orderBy('role_user.id')->get(['role_user.user_id', 'roles.slug']) as $affectation) {
                DB::table('users')->where('id', $affectation->user_id)->whereNull('role')->update(['role' => $affectation->slug]);
            }
        }

        if (! Schema::hasTable('department_module')) {
            Schema::create('department_module', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
                $table->string('module_key', 50);
                $table->string('default_level', 10)->default('write');
                $table->timestamps();
                $table->unique(['department_id', 'module_key'], 'dept_module_unique');
            });
        }

        if (! Schema::hasTable('user_module_permissions')) {
            Schema::create('user_module_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('module_key', 50);
                $table->string('access_level', 15)->default('inherit');
                $table->timestamps();
                $table->unique(['user_id', 'module_key'], 'user_module_unique');
                $table->index('user_id', 'user_module_user_idx');
            });
        }
    }
};
