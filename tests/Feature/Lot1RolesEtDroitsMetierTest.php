<?php

use App\Models\Department;
use App\Models\PermissionGrant;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\DepartmentRoles;
use App\Support\DutySegregation;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Lot 1 : Rôles et droits métier (Validation des critères d'acceptation)
| Constats audités : S6, S7, S8, S9, Matrice cible §2
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules([
        'hebergement', 'reservations', 'clients', 'restaurant', 'shop',
        'housekeeping', 'economat', 'accounting', 'comptabilite', 'ledger',
        'analytics', 'technique', 'discussions', 'achats', 'pilotage',
    ]);
});

function creerActeur(string $role, array $attributs = []): User
{
    $user = User::factory()->create(['is_active' => true] + $attributs);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/*
|--------------------------------------------------------------------------
| 1. Fusion du rôle 'quality_auditor' dans 'controller'
|--------------------------------------------------------------------------
*/

test("Lot 1.1 : quality_auditor est retiré et non assignable dans RoleCatalog", function () {
    $quality = RoleCatalog::find('quality_auditor');

    expect($quality)->not->toBeNull()
        ->and($quality['statut'])->toBe(RoleCatalog::RETIRE)
        ->and($quality['is_assignable'])->toBeFalse()
        ->and(PermissionCatalog::forRole('quality_auditor'))->toBe([]);
});

test("Lot 1.2 : le département Qualité confère le rôle controller et non quality_auditor", function () {
    $dept = new Department(['code' => 'QLT']);
    $assignableRoles = collect([
        (object) ['slug' => 'controller'],
        (object) ['slug' => 'quality_auditor'],
    ]);
    $matching = $dept->resolveMatchingRoles($assignableRoles);

    expect($matching['roles'])->toContain('controller')
        ->and($matching['roles'])->not->toContain('quality_auditor');
});

test("Lot 1.3 : la migration fusion_quality_auditor_dans_controller réaffecte les utilisateurs", function () {
    // Créer un utilisateur portant l'ancien rôle quality_auditor en base
    $qualityRole = Role::where('slug', 'quality_auditor')->first();
    $controllerRole = Role::where('slug', 'controller')->first();

    $user = User::factory()->create(['is_active' => true]);
    DB::table('role_user')->insert([
        'user_id' => $user->id,
        'role_id' => $qualityRole->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($user->hasRole('quality_auditor'))->toBeTrue()
        ->and($user->hasRole('controller'))->toBeFalse();

    // Exécuter la migration
    $migration = require database_path('migrations/2026_11_05_180000_fusion_quality_auditor_dans_controller.php');
    $migration->up();

    $user = $user->fresh();
    expect($user->hasRole('controller'))->toBeTrue()
        ->and($user->hasRole('quality_auditor'))->toBeFalse();
});

test("Lot 1.4 : le contrôleur de gestion consulte l'exploitation au titre de l'audit qualité", function () {
    $controleur = creerActeur('controller');
    $resolver = app(PermissionResolver::class);

    $droitsAuditQualite = [
        'restaurant.billing.voir',
        'shop.orders.voir',
        'shop.products.voir',
        'economat.items.voir',
        'housekeeping.voir',
        'bookings.voir',
        'restaurant.menus.voir',
        'settings.services.export',
        'settings.packages.export',
    ];

    foreach ($droitsAuditQualite as $droit) {
        expect($resolver->allows($controleur, $droit))->toBeTrue("Le contrôleur doit pouvoir auditer {$droit}");
    }
});

/*
|--------------------------------------------------------------------------
| 2. Fin de l'omnipotence de l'administrateur (Constat S6)
|--------------------------------------------------------------------------
*/

test("Lot 1.5 : l'administrateur ne détient aucun acte exclusif métier dans le catalogue (Constat S6)", function () {
    $droitsAdmin = PermissionCatalog::forRole('admin');

    foreach (PermissionCatalog::ACTES_EXCLUSIFS as $exclusif) {
        expect($droitsAdmin)->not->toContain($exclusif, "L'administrateur ne doit pas détenir {$exclusif}");
    }

    // Actes exclusifs critiques vérifiés un par un
    expect($droitsAdmin)->not->toContain('economat.items.adjust')
        ->and($droitsAdmin)->not->toContain('economat.items.opening')
        ->and($droitsAdmin)->not->toContain('economat.items.modifier')
        ->and($droitsAdmin)->not->toContain('economat.items.supprimer')
        ->and($droitsAdmin)->not->toContain('economat.suppliers.modifier')
        ->and($droitsAdmin)->not->toContain('economat.suppliers.supprimer')
        ->and($droitsAdmin)->not->toContain('economat.stock_counts.close')
        ->and($droitsAdmin)->not->toContain('accounting.ledger.entry.reverse');
});

test("Lot 1.6 : même sous intervention déclarée, l'administrateur ne peut exécuter aucun acte exclusif", function () {
    $admin = creerActeur('admin');
    \App\Models\Intervention::create([
        'user_id' => $admin->id,
        'motif' => 'Maintenance générale',
        'perimetres' => ['economat', 'restaurant', 'comptabilite'],
        'debut' => now(),
        'fin_prevue' => now()->addHour(),
    ]);

    $resolver = app(PermissionResolver::class);

    // Actes de maintenance / réparation couverts par l'intervention
    expect($resolver->allows($admin, 'economat.items.creer'))->toBeTrue();

    // Mais les actes exclusifs restent strictement fermés
    expect($resolver->allows($admin, 'economat.items.adjust'))->toBeFalse()
        ->and($resolver->allows($admin, 'economat.items.opening'))->toBeFalse()
        ->and($resolver->allows($admin, 'economat.items.modifier'))->toBeFalse()
        ->and($resolver->allows($admin, 'economat.items.supprimer'))->toBeFalse()
        ->and($resolver->allows($admin, 'economat.suppliers.modifier'))->toBeFalse()
        ->and($resolver->allows($admin, 'economat.stock_counts.close'))->toBeFalse()
        ->and($resolver->allows($admin, 'accounting.ledger.entry.reverse'))->toBeFalse();
});

test("Lot 1.7 : l'administrateur ne consulte pas les écarts et variances exclusifs au contrôle", function () {
    $admin = creerActeur('admin');
    $resolver = app(PermissionResolver::class);

    expect($resolver->allows($admin, 'economat.control.voir'))->toBeFalse()
        ->and($resolver->allows($admin, 'economat.control.variances.voir'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 3. Matrice Achats vs Contrôleur de gestion (Constat S7)
|--------------------------------------------------------------------------
*/

test("Lot 1.8 : le responsable des achats crée les articles et fournisseurs mais ne les modifie ni ne les supprime (Constat S7)", function () {
    $acheteur = creerActeur('purchasing_manager');
    $resolver = app(PermissionResolver::class);

    // Création autorisée
    expect($resolver->allows($acheteur, 'economat.items.creer'))->toBeTrue()
        ->and($resolver->allows($acheteur, 'economat.suppliers.creer'))->toBeTrue();

    // Modification et suppression STRICTEMENT REFUSÉES
    expect($resolver->allows($acheteur, 'economat.items.modifier'))->toBeFalse()
        ->and($resolver->allows($acheteur, 'economat.items.supprimer'))->toBeFalse()
        ->and($resolver->allows($acheteur, 'economat.suppliers.modifier'))->toBeFalse()
        ->and($resolver->allows($acheteur, 'economat.suppliers.supprimer'))->toBeFalse();

    // Ajustement de stock STRICTEMENT REFUSÉ
    expect($resolver->allows($acheteur, 'economat.items.adjust'))->toBeFalse()
        ->and($resolver->allows($acheteur, 'economat.items.opening'))->toBeFalse();
});

test("Lot 1.9 : le contrôleur de gestion est seul habilité à modifier/supprimer articles, fournisseurs et ajuster le stock", function () {
    $controleur = creerActeur('controller');
    $resolver = app(PermissionResolver::class);

    expect($resolver->allows($controleur, 'economat.items.modifier'))->toBeTrue()
        ->and($resolver->allows($controleur, 'economat.items.supprimer'))->toBeTrue()
        ->and($resolver->allows($controleur, 'economat.suppliers.modifier'))->toBeTrue()
        ->and($resolver->allows($controleur, 'economat.suppliers.supprimer'))->toBeTrue()
        ->and($resolver->allows($controleur, 'economat.items.adjust'))->toBeTrue()
        ->and($resolver->allows($controleur, 'economat.items.opening'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 4. Isolation des bons de commande (Magasinier / Économe)
|--------------------------------------------------------------------------
*/

test("Lot 1.10 : l'économe et le magasinier n'ont aucune visibilité sur les bons de commande", function () {
    $econome = creerActeur('econome');
    $magasinier = creerActeur('storekeeper');
    $resolver = app(PermissionResolver::class);

    // Consultation des BC interdite
    expect($resolver->allows($econome, 'economat.orders.voir'))->toBeFalse()
        ->and($resolver->allows($magasinier, 'economat.orders.voir'))->toBeFalse();

    // Export des BC interdit
    expect($resolver->allows($econome, 'economat.orders.export'))->toBeFalse()
        ->and($resolver->allows($magasinier, 'economat.orders.export'))->toBeFalse();

    // Le responsable des achats et le contrôleur de gestion ont la visibilité
    $acheteur = creerActeur('purchasing_manager');
    $controleur = creerActeur('controller');

    expect($resolver->allows($acheteur, 'economat.orders.voir'))->toBeTrue()
        ->and($resolver->allows($acheteur, 'economat.orders.export'))->toBeTrue()
        ->and($resolver->allows($controleur, 'economat.orders.voir'))->toBeTrue()
        ->and($resolver->allows($controleur, 'economat.orders.export'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 5. Non-dérogabilité des interdictions fermes (Constat S9)
|--------------------------------------------------------------------------
*/

test("Lot 1.11 : une exception individuelle ALLOW ne peut pas écraser un refus ERP posé sur un rôle (Constat S9)", function () {
    $acheteur = creerActeur('purchasing_manager');

    // Refus posé par la console ERP sur le rôle purchasing_manager
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE,
        'subject_id'   => 'purchasing_manager',
        'permission'   => 'economat.items.creer',
        'effect'       => PermissionGrant::EFFET_DENY,
        'origin'       => PermissionGrant::ORIGINE_ERP,
        'reason'       => 'Verrouillage centralisé ERP',
    ]);

    // Tentative d'exception nominative ALLOW posée au niveau de l'établissement
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER,
        'subject_id'   => (string) $acheteur->id,
        'permission'   => 'economat.items.creer',
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'origin'       => PermissionGrant::ORIGINE_ETABLISSEMENT,
        'reason'       => 'Dérogation locale directeur',
    ]);

    // Le refus ERP doit l'emporter sur la dérogation locale
    expect(app(PermissionResolver::class)->allows($acheteur, 'economat.items.creer'))->toBeFalse();
});

test("Lot 1.12 : une exception individuelle ALLOW ne peut pas écraser un refus sur un acte non dérogeable", function () {
    $acheteur = creerActeur('purchasing_manager');

    // Refus posé sur le rôle pour un acte exclusif / non dérogeable (modification d'article)
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE,
        'subject_id'   => 'purchasing_manager',
        'permission'   => 'economat.items.modifier',
        'effect'       => PermissionGrant::EFFET_DENY,
        'origin'       => PermissionGrant::ORIGINE_ETABLISSEMENT,
        'reason'       => 'Interdiction stricte des achats de modifier le catalogue',
    ]);

    // Tentative d'exception nominative ALLOW
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER,
        'subject_id'   => (string) $acheteur->id,
        'permission'   => 'economat.items.modifier',
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'origin'       => PermissionGrant::ORIGINE_ETABLISSEMENT,
        'reason'       => 'Dérogation locale exceptionnelle',
    ]);

    // L'acte étant non dérogeable, le refus l'emporte toujours
    expect(app(PermissionResolver::class)->allows($acheteur, 'economat.items.modifier'))->toBeFalse();
});
