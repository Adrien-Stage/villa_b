<?php

/**
 * Comptes et départements tenus depuis la console d'orchestration.
 *
 * La console ne crée que les comptes administrateurs : le service
 * informatique de l'hôtel, qui crée ensuite tous les autres. Plus aucune
 * écriture directe dans la base : tout passe par l'application.
 */

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['orchestration.secret' => 'jeton-console', 'reporting.secret' => 'jeton-reporting']);
    RoleCatalog::sync();
});

function console(): array
{
    return ['Authorization' => 'Bearer jeton-console'];
}

function membreDuPersonnel(string $nom, array $roles, array $attributs = []): User
{
    $user = User::factory()->create(['name' => $nom, 'role' => $roles[0]] + $attributs);
    $user->roles()->sync(Role::whereIn('slug', $roles)->pluck('id'));

    return $user;
}

test('sans le jeton de la console, rien ne passe — pas même celui du reporting', function () {
    $this->getJson('/api/comptes')->assertStatus(401);
    $this->getJson('/api/comptes', ['Authorization' => 'Bearer jeton-reporting'])->assertStatus(401);
    $this->postJson('/api/comptes/administrateurs', [], ['Authorization' => 'Bearer jeton-reporting'])->assertStatus(401);
    $this->getJson('/api/departements', ['Authorization' => 'Bearer jeton-reporting'])->assertStatus(401);
});

test("sans secret d'orchestration, le canal des comptes reste fermé", function () {
    // Pas de repli sur le jeton de reporting : le module GRC le détient.
    config(['orchestration.secret' => '']);

    $this->getJson('/api/comptes', ['Authorization' => 'Bearer jeton-reporting'])->assertStatus(503);
});

test('la console lit le personnel et ses rôles réels', function () {
    membreDuPersonnel('Jean Mvondo', ['reception']);
    // Colonne périmée : l'affectation fait foi.
    $chef = membreDuPersonnel('Ange Ondoa', ['reception_chief']);
    $chef->update(['role' => 'reception']);

    $comptes = collect($this->getJson('/api/comptes', console())->assertOk()->json('comptes'))->keyBy('name');

    expect($comptes['Ange Ondoa']['roles'])->toBe(['reception_chief'])
        ->and($comptes['Jean Mvondo']['actif'])->toBeTrue();
});

test("la console crée un administrateur, et lui seul", function () {
    $this->postJson('/api/comptes/administrateurs', [
        'name' => 'Paul Essomba', 'email' => 'Paul@Hotel.test', 'password' => 'motdepasse-solide',
        'auteur' => 'Ada <ada@wetchah.test>',
    ], console())->assertCreated();

    $admin = User::where('email', 'paul@hotel.test')->firstOrFail();

    expect($admin->rolesDetenus())->toBe(['admin'])
        ->and($admin->role)->toBe('admin')
        ->and($admin->isAdmin())->toBeTrue()
        ->and(Hash::check('motdepasse-solide', $admin->password))->toBeTrue()
        ->and(AuditLog::where('event_type', 'admin_account_console')->exists())->toBeTrue();
});

test('un mot de passe de moins de huit caractères est refusé', function () {
    $this->postJson('/api/comptes/administrateurs', [
        'name' => 'Paul Essomba', 'email' => 'paul@hotel.test', 'password' => 'court',
    ], console())->assertStatus(422);

    expect(User::count())->toBe(0);
});

test("une adresse déjà prise l'est quelle que soit la casse", function () {
    membreDuPersonnel('Jean Mvondo', ['reception'], ['email' => 'jean@hotel.test']);

    $this->postJson('/api/comptes/administrateurs', [
        'name' => 'Jean bis', 'email' => 'JEAN@hotel.test', 'password' => 'motdepasse-solide',
    ], console())->assertStatus(422)->assertJsonValidationErrors('email');
});

test("la console réinitialise et désactive un administrateur", function () {
    $admin = membreDuPersonnel('Paul Essomba', ['admin']);

    $this->patchJson("/api/comptes/administrateurs/{$admin->id}", [
        'password' => 'nouveau-secret-1', 'actif' => false,
    ], console())->assertOk()->assertJson(['actif' => false]);

    expect(Hash::check('nouveau-secret-1', $admin->fresh()->password))->toBeTrue()
        ->and($admin->fresh()->is_active)->toBeFalse();
});

test("la console ne touche à aucun autre compte que ceux d'administrateur", function () {
    $manager = membreDuPersonnel('Marie Abena', ['manager']);
    $ancien = $manager->password;

    $this->patchJson("/api/comptes/administrateurs/{$manager->id}", ['password' => 'nouveau-secret-1'], console())
        ->assertStatus(422);

    expect($manager->fresh()->password)->toBe($ancien);
});

test('la console crée un département avec ses modules', function () {
    $this->postJson('/api/departements', [
        'name' => 'Salle et bar', 'modules' => ['restaurant' => 'write', 'shop' => 'read'],
    ], console())->assertCreated();

    $departement = Department::where('slug', 'salle_et_bar')->firstOrFail();

    expect($departement->code)->toBe('SALL')
        ->and($departement->defaultModules())->toBe(['restaurant' => 'write', 'shop' => 'read']);
});

test('modifier un département remplace ses modules', function () {
    $departement = Department::create(['name' => 'Cuisine', 'slug' => 'cuisine_test']);
    DB::table('department_module')->insert([
        'department_id' => $departement->id, 'module_key' => 'shop', 'default_level' => 'write',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->putJson("/api/departements/{$departement->id}", [
        'name' => 'Cuisine centrale', 'modules' => ['restaurant' => 'write'],
    ], console())->assertOk();

    expect($departement->fresh()->name)->toBe('Cuisine centrale')
        ->and($departement->fresh()->defaultModules())->toBe(['restaurant' => 'write']);
});

test("supprimer un département détache ses employés sans les supprimer", function () {
    $departement = Department::create(['name' => 'Lingerie', 'slug' => 'lingerie_test']);
    $employe = membreDuPersonnel('Rose Ngo', ['housekeeping_staff'], ['department_id' => $departement->id]);

    $this->deleteJson("/api/departements/{$departement->id}", [], console())->assertOk();

    expect(Department::find($departement->id))->toBeNull()
        ->and($employe->fresh())->not->toBeNull()
        ->and($employe->fresh()->department_id)->toBeNull();
});

test('deux départements ne partagent pas un identifiant', function () {
    Department::create(['name' => 'Cuisine', 'slug' => 'cuisine_test']);

    $this->postJson('/api/departements', ['name' => 'Autre', 'slug' => 'cuisine_test'], console())
        ->assertStatus(422);
});

test('la liste des départements donne modules et effectifs', function () {
    $departement = Department::create(['name' => 'Lingerie', 'slug' => 'lingerie_test']);
    membreDuPersonnel('Rose Ngo', ['housekeeping_staff'], ['department_id' => $departement->id]);

    $liste = collect($this->getJson('/api/departements', console())->assertOk()->json('departements'))
        ->keyBy('slug');

    expect($liste['lingerie_test']['users_count'])->toBe(1);
});
