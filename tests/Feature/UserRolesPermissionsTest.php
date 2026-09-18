<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantModules;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedRolesAndModules(): void
{
    test()->seed(RoleSeeder::class);
    // Active les modules dont dépendent les routes testées.
    $prop = new ReflectionProperty(TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['restaurant', 'shop', 'housekeeping']);
}

test('le rôle économe est proposé automatiquement à la création', function () {
    seedRolesAndModules();
    $this->actingAs(User::factory()->create(['role' => 'manager']));

    $this->get(route('users.index'))
        ->assertOk()
        ->assertSee('Économe')       // libellé du rôle
        ->assertSee('Économat');     // libellé du module
});

test('tout nouveau rôle assignable apparaît sans toucher au code', function () {
    seedRolesAndModules();
    // Un rôle inventé après coup, marqué assignable.
    Role::create(['name' => 'Jardinier', 'slug' => 'gardener', 'module' => 'hebergement', 'icon' => 'trees', 'is_assignable' => true, 'sort_order' => 90]);

    $this->actingAs(User::factory()->create(['role' => 'manager']));
    $this->get(route('users.index'))->assertOk()->assertSee('Jardinier');
});

test('un utilisateur est créé avec plusieurs modules et des niveaux distincts', function () {
    seedRolesAndModules();
    $this->actingAs(User::factory()->create(['role' => 'manager']));

    $this->post(route('users.store'), [
        'name' => 'Poly Valent',
        'email' => 'poly@example.com',
        'roles' => ['reception', 'restaurant_staff', 'shop_cashier'],
        'levels' => ['reception' => 'write', 'restaurant_staff' => 'read', 'shop_cashier' => 'read'],
        'password' => 'motdepasse1',
        'password_confirmation' => 'motdepasse1',
        'is_active' => 1,
    ])->assertRedirect();

    $user = User::where('email', 'poly@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->roles)->toHaveCount(3)
        // Hébergement en écriture, restaurant et boutique en lecture seule.
        ->and($user->canWrite('hebergement'))->toBeTrue()
        ->and($user->moduleLevel('restaurant'))->toBe('read')
        ->and($user->canWrite('restaurant'))->toBeFalse()
        ->and($user->moduleLevel('boutique'))->toBe('read');
});

test('un accès en lecture seule bloque une écriture mais autorise la lecture', function () {
    seedRolesAndModules();

    // Gérant boutique, mais en LECTURE SEULE sur le module boutique.
    $reader = User::factory()->create(['role' => 'shop_manager']);
    $reader->roles()->sync([
        Role::where('slug', 'shop_manager')->value('id') => ['level' => 'read'],
    ]);
    $this->actingAs($reader);

    // Lecture : autorisée.
    $this->get(route('shop.products.index'))->assertOk();

    // Écriture : bloquée par le middleware module.access (erreur module_access).
    $this->post(route('shop.products.import'), [])
        ->assertRedirect()
        ->assertSessionHasErrors('module_access');

    // Le même rôle en écriture franchit la barrière : il échoue seulement sur
    // la validation du fichier (csv_file), pas sur l'accès au module.
    $writer = User::factory()->create(['role' => 'shop_manager']);
    $writer->roles()->sync([
        Role::where('slug', 'shop_manager')->value('id') => ['level' => 'write'],
    ]);
    $this->actingAs($writer)
        ->post(route('shop.products.import'), [])
        ->assertSessionHasErrors('csv_file')
        ->assertSessionMissing('access_denied_popup');
});

test('un compte existant sans niveau pivot n’est pas restreint (écriture par défaut)', function () {
    seedRolesAndModules();

    // Rattachement sans niveau (level null), comme les comptes d'avant.
    $legacy = User::factory()->create(['role' => 'restaurant_chief']);
    $legacy->roles()->sync([Role::where('slug', 'restaurant_chief')->value('id')]);

    expect($legacy->fresh()->canWrite('restaurant'))->toBeTrue();
});

test('le manager n’est jamais restreint par le niveau de module', function () {
    seedRolesAndModules();
    $manager = User::factory()->create(['role' => 'manager']);

    expect($manager->hasAnyRole(['manager']))->toBeTrue();
    // Le middleware exempte explicitement la direction : accès restaurant en écriture.
    $this->actingAs($manager)->get(route('restaurant.menus.index'))->assertOk();
});

test('un utilisateur peut être affecté à un département lors de sa création', function () {
    seedRolesAndModules();
    $dept = Department::create([
        'name' => 'Réception Test',
        'code' => 'REC',
        'slug' => 'reception_test',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create(['role' => 'manager']));

    $this->post(route('users.store'), [
        'name' => 'Paul Mbarga',
        'email' => 'paul.mbarga@example.com',
        'department_id' => $dept->id,
        'roles' => ['reception'],
        'levels' => ['reception' => 'write'],
        'password' => 'motdepasse1',
        'password_confirmation' => 'motdepasse1',
        'is_active' => 1,
    ])->assertRedirect();

    $user = User::where('email', 'paul.mbarga@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->department_id)->toBe($dept->id)
        ->and($user->department->name)->toBe('Réception Test');
});

test('un employé existant peut être réaffecté à un département', function () {
    seedRolesAndModules();
    $dept = Department::create([
        'name' => 'Housekeeping Test',
        'code' => 'HSK',
        'slug' => 'housekeeping_test',
        'is_active' => true,
    ]);

    $staff = User::factory()->create(['role' => 'reception', 'department_id' => null]);
    $this->actingAs(User::factory()->create(['role' => 'manager']));

    $this->put(route('users.update', $staff), [
        'name' => $staff->name,
        'email' => $staff->email,
        'department_id' => $dept->id,
        'roles' => ['reception'],
        'levels' => ['reception' => 'write'],
        'is_active' => 1,
    ])->assertRedirect();

    expect($staff->fresh()->department_id)->toBe($dept->id);
});

test('le filtre par département restreint la liste des employés affichés', function () {
    seedRolesAndModules();
    $deptA = Department::create(['name' => 'Département A', 'code' => 'DA', 'slug' => 'dept_a', 'is_active' => true]);
    $deptB = Department::create(['name' => 'Département B', 'code' => 'DB', 'slug' => 'dept_b', 'is_active' => true]);

    $userA = User::factory()->create(['name' => 'Employé A', 'role' => 'reception', 'department_id' => $deptA->id]);
    $userB = User::factory()->create(['name' => 'Employé B', 'role' => 'reception', 'department_id' => $deptB->id]);

    $manager = User::factory()->create(['role' => 'manager']);
    $this->actingAs($manager);

    $reponse = $this->get(route('users.index', ['department_id' => $deptA->id]));
    $reponse->assertOk()
        ->assertSee('Employé A')
        ->assertDontSee('Employé B');
});

test('la résolution des rôles d’un département pré-sélectionne tous les rôles pour la Direction et les rôles métiers pour les autres départements', function () {
    seedRolesAndModules();
    \App\Support\RoleCatalog::sync();

    $assignableRoles = Role::assignable()->get();

    $deptDir = Department::where('code', 'DIR')->first() ?: Department::create(['name' => 'Direction Générale', 'code' => 'DIR', 'slug' => 'direction_generale', 'is_active' => true]);
    $resDir = $deptDir->resolveMatchingRoles($assignableRoles);
    expect($resDir['roles'])->toHaveCount($assignableRoles->count())
        ->and($resDir['levels'])->toHaveKey('reception', 'write')
        ->and($resDir['levels'])->toHaveKey('restaurant_chief', 'write');

    $deptRec = Department::where('code', 'REC')->first() ?: Department::create(['name' => 'Réception', 'code' => 'REC', 'slug' => 'reception_front_office', 'is_active' => true]);
    $resRec = $deptRec->resolveMatchingRoles($assignableRoles);
    expect($resRec['roles'])->toContain('reception', 'cashier')
        ->and($resRec['roles'])->not->toContain('restaurant_chief');

    $deptBtq = Department::where('code', 'BTQ')->first() ?: Department::create(['name' => 'Boutique', 'code' => 'BTQ', 'slug' => 'boutique_commerce', 'is_active' => true]);
    $resBtq = $deptBtq->resolveMatchingRoles($assignableRoles);
    expect($resBtq['roles'])->toContain('shop_manager', 'shop_cashier');
});

