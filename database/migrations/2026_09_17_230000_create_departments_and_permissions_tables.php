<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Structure organisationnelle hôtelière avancée :
     * 1. Référentiel des Départements (departments)
     * 2. Association Départements <-> Modules par défaut (department_module)
     * 3. Rattachement hiérarchique des employés (users.department_id)
     * 4. Matrice de surcharges granulaires par utilisateur (user_module_permissions)
     */
    public function up(): void
    {
        // 1. Table des Départements
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('slug', 50)->unique();
                $table->string('code', 20)->nullable();
                $table->text('description')->nullable();
                $table->string('icon', 50)->default('briefcase');
                $table->string('accent', 30)->default('indigo');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. Table pivot Départements <-> Modules par défaut
        if (!Schema::hasTable('department_module')) {
            Schema::create('department_module', function (Blueprint $table) {
                $table->id();
                $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
                $table->string('module_key', 50);
                $table->string('default_level', 10)->default('write'); // 'write' ou 'read'
                $table->timestamps();

                $table->unique(['department_id', 'module_key'], 'dept_module_unique');
            });
        }

        // 3. Altération de la table users (department_id)
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('departments')
                    ->onDelete('set null');
                
                $table->index('department_id', 'users_department_id_index');
            });
        }

        // 4. Table des surcharges de permissions par utilisateur et par module
        if (!Schema::hasTable('user_module_permissions')) {
            Schema::create('user_module_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('module_key', 50);
                $table->string('access_level', 15)->default('inherit'); // inherit, write, read, none
                $table->timestamps();

                $table->unique(['user_id', 'module_key'], 'user_module_unique');
                $table->index('user_id', 'user_module_user_idx');
            });
        }

        // 5. Initialisation des 8 Départements standards et de leurs modules
        $this->seedInitialDepartments();
    }

    private function seedInitialDepartments(): void
    {
        $now = now();
        $departments = [
            [
                'code'        => 'DIR',
                'slug'        => 'direction_generale',
                'name'        => 'Direction Générale',
                'description' => 'Supervise l’ensemble de l’établissement, arrête les choix stratégiques et pilote la rentabilité globale.',
                'icon'        => 'briefcase',
                'accent'      => 'indigo',
                'sort_order'  => 1,
                'modules'     => ['analytics', 'clients', 'parametres', 'utilisateurs', 'discussions', 'ai', 'website', 'grc'],
            ],
            [
                'code'        => 'REC',
                'slug'        => 'reception_front_office',
                'name'        => 'Réception / Front Office',
                'description' => 'Gère l’accueil, les réservations individuelles et groupes, l’enregistrement, le départ des clients et la caisse réception.',
                'icon'        => 'calendar-check',
                'accent'      => 'sky',
                'sort_order'  => 2,
                'modules'     => ['reservations', 'hebergement', 'clients', 'comptabilite', 'website', 'discussions', 'ai'],
            ],
            [
                'code'        => 'HSK',
                'slug'        => 'housekeeping_hebergement',
                'name'        => 'Hébergement / Housekeeping',
                'description' => 'Nettoyage des chambres, entretien des espaces communs, gestion du linge, des fiches techniques et inspection des étages.',
                'icon'        => 'sparkles',
                'accent'      => 'teal',
                'sort_order'  => 3,
                'modules'     => ['housekeeping', 'hebergement', 'economat', 'discussions'],
            ],
            [
                'code'        => 'FNB',
                'slug'        => 'restauration_fb',
                'name'        => 'Restauration (Food & Beverage - F&B)',
                'description' => 'Service en salle (restaurant, bar, banquets, room service) et production culinaire (cuisine, garde-manger, fiches recettes).',
                'icon'        => 'utensils',
                'accent'      => 'amber',
                'sort_order'  => 4,
                'modules'     => ['restaurant', 'portail', 'economat', 'discussions'],
            ],
            [
                'code'        => 'RH',
                'slug'        => 'ressources_humaines',
                'name'        => 'Ressources Humaines (RH)',
                'description' => 'Recrutement, gestion des contrats, paie, suivi des dossiers employés, compétences, formation et relations sociales.',
                'icon'        => 'users',
                'accent'      => 'purple',
                'sort_order'  => 5,
                'modules'     => ['utilisateurs', 'grc', 'discussions'],
            ],
            [
                'code'        => 'FIN',
                'slug'        => 'comptabilite_finance',
                'name'        => 'Comptabilité et Finance',
                'description' => 'Comptabilité générale, grand livre SYSCOHADA, contrôle de gestion, audit interne, trésorerie et suivi des caisses.',
                'icon'        => 'calculator',
                'accent'      => 'emerald',
                'sort_order'  => 6,
                'modules'     => ['comptabilite', 'ledger', 'economat', 'analytics', 'grc'],
            ],
            [
                'code'        => 'IT',
                'slug'        => 'informatique_it',
                'name'        => 'Informatique / IT',
                'description' => 'Systèmes de réservation (PMS), réseau, parc informatique, site web, passerelles API et support technique interne.',
                'icon'        => 'laptop',
                'accent'      => 'blue',
                'sort_order'  => 7,
                'modules'     => ['parametres', 'api', 'pwa', 'ai', 'website', 'discussions'],
            ],
            [
                'code'        => 'QLT',
                'slug'        => 'qualite_controle',
                'name'        => 'Qualité / Contrôle Qualité',
                'description' => 'Standards de service hôtelier, audits d’hygiène et de conformité, enquêtes de satisfaction clients et plans d\'action.',
                'icon'        => 'award',
                'accent'      => 'rose',
                'sort_order'  => 8,
                'modules'     => ['grc', 'clients', 'housekeeping', 'restaurant', 'discussions'],
            ],
            [
                'code'        => 'BTQ',
                'slug'        => 'boutique_commerce',
                'name'        => 'Boutique & Commerce',
                'description' => 'Point de vente, catalogue d\'articles cadeaux / souvenirs, réassort économat et encaissement boutique.',
                'icon'        => 'store',
                'accent'      => 'orange',
                'sort_order'  => 9,
                'modules'     => ['shop', 'economat', 'discussions'],
            ],
        ];

        foreach ($departments as $data) {
            $modules = $data['modules'];
            unset($data['modules']);

            $existing = DB::table('departments')->where('slug', $data['slug'])->first();
            if (!$existing) {
                $deptId = DB::table('departments')->insertGetId(array_merge($data, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            } else {
                $deptId = $existing->id;
                DB::table('departments')->where('id', $deptId)->update(array_merge($data, ['updated_at' => $now]));
            }

            foreach ($modules as $modKey) {
                $alreadyAttached = DB::table('department_module')
                    ->where('department_id', $deptId)
                    ->where('module_key', $modKey)
                    ->exists();

                if (!$alreadyAttached) {
                    DB::table('department_module')->insert([
                        'department_id' => $deptId,
                        'module_key'    => $modKey,
                        'default_level' => 'write',
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                }
            }
        }

        // Rétrocompatibilité : affecter les comptes existants sans département
        $deptIdsBySlug = DB::table('departments')->pluck('id', 'slug')->toArray();

        $roleToDept = [
            'admin'               => $deptIdsBySlug['direction_generale'] ?? null,
            'manager'             => $deptIdsBySlug['direction_generale'] ?? null,
            'reception'           => $deptIdsBySlug['reception_front_office'] ?? null,
            'cashier'             => $deptIdsBySlug['reception_front_office'] ?? null,
            'housekeeping_leader' => $deptIdsBySlug['housekeeping_hebergement'] ?? null,
            'housekeeping_staff'  => $deptIdsBySlug['housekeeping_hebergement'] ?? null,
            'housekeeping'        => $deptIdsBySlug['housekeeping_hebergement'] ?? null,
            'restaurant_chief'    => $deptIdsBySlug['restauration_fb'] ?? null,
            'restaurant_staff'    => $deptIdsBySlug['restauration_fb'] ?? null,
            'restaurant_cook'     => $deptIdsBySlug['restauration_fb'] ?? null,
            'accountant'          => $deptIdsBySlug['comptabilite_finance'] ?? null,
            'controller'          => $deptIdsBySlug['comptabilite_finance'] ?? null,
            'shop_manager'        => $deptIdsBySlug['boutique_commerce'] ?? null,
            'shop_cashier'        => $deptIdsBySlug['boutique_commerce'] ?? null,
            'econome'             => $deptIdsBySlug['comptabilite_finance'] ?? null,
        ];

        foreach ($roleToDept as $role => $deptId) {
            if ($deptId) {
                DB::table('users')
                    ->whereNull('department_id')
                    ->where('role', $role)
                    ->update(['department_id' => $deptId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_permissions');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }

        Schema::dropIfExists('department_module');
        Schema::dropIfExists('departments');
    }
};
