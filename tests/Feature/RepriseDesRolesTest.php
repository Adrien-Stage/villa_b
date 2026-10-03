<?php

/**
 * Reprise des comptes dans le référentiel hiérarchique.
 *
 * Les affectations font désormais seules foi. La reprise rend explicite ce
 * que la colonne héritée accordait, réaligne la colonne quand la console l'a
 * laissée périmée, et préserve les chefs de cuisine qui tenaient la salle.
 * Elle ne retire aucun droit et peut être rejouée sans effet.
 *
 * La colonne a disparu depuis (nettoyage des droits hérités) : ces tests la
 * remettent en place pour rejouer la reprise telle qu'elle s'est déroulée.
 */

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\RepriseDesRoles;
use App\Support\RoleCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    Schema::table('users', fn (Blueprint $table) => $table->string('role', 30)->nullable());
});

/** Compte dont le rôle ne vit que dans la colonne héritée. */
function compteHerite(string $role): User
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Compte hérité '.$role, 'email' => uniqid($role).'@hotel.test', 'password' => 'x',
        'role' => $role, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::find($id);
}

function affecter(User $user, string $role, ?string $niveau = null): void
{
    $user->roles()->attach(Role::where('slug', $role)->value('id'), ['level' => $niveau]);
}

test("un compte sans affectation reçoit son rôle hérité, en écriture", function () {
    $reception = compteHerite('reception');

    RepriseDesRoles::affecterLesRolesHerites();

    $affectation = $reception->fresh()->roles->first();
    expect($affectation->slug)->toBe('reception')
        ->and($affectation->pivot->level)->toBeNull();
});

test("l'ancien rôle « housekeeping » devient valet ou femme de chambre, aux mêmes droits", function () {
    $ancien = compteHerite('housekeeping');

    RepriseDesRoles::affecterLesRolesHerites();

    expect($ancien->fresh()->rolesDetenus())->toBe(['housekeeping_staff'])
        ->and(app(PermissionResolver::class)->allows($ancien->fresh(), 'housekeeping.clean'))->toBeTrue();
});

test("un rôle inconnu n'est pas inventé", function () {
    $inconnu = compteHerite('astronaute');

    [$affectes, $ignores] = RepriseDesRoles::affecterLesRolesHerites();

    expect($affectes)->toBe(0)
        ->and($ignores)->toBe(['astronaute'])
        ->and($inconnu->fresh()->roles)->toBeEmpty();
});

test("un rôle du référentiel absent de la base est créé pour l'affectation", function () {
    // La synchronisation des rôles passe après les migrations : la reprise
    // ne peut pas compter sur elle.
    Role::where('slug', 'admin')->delete();
    $admin = compteHerite('admin');

    RepriseDesRoles::affecterLesRolesHerites();

    expect($admin->fresh()->rolesDetenus())->toBe(['admin'])
        ->and(Role::where('slug', 'admin')->value('is_assignable'))->toBeFalse();
});

test('la colonne laissée périmée par la console est réalignée sur le rôle principal', function () {
    // Créé réceptionniste, puis passé au housekeeping depuis la console, qui
    // n'a remplacé que les affectations.
    $compte = compteHerite('reception');
    affecter($compte, 'housekeeping_staff');

    expect(RepriseDesRoles::realignerLaColonne())->toBe(1)
        ->and(DB::table('users')->where('id', $compte->id)->value('role'))->toBe('housekeeping_staff');
});

test('le chef de cuisine qui tenait la salle reçoit aussi le rôle de responsable, au même niveau', function () {
    $chef = User::factory()->create(['role' => 'restaurant_chief']);
    $chef->roles()->detach();
    affecter($chef, 'restaurant_chief', 'read');

    expect(RepriseDesRoles::dedoublerLesChefsDeCuisine())->toBe(1);

    $responsable = $chef->fresh()->roles->firstWhere('slug', 'restaurant_manager');
    expect($responsable)->not->toBeNull()
        ->and($responsable->pivot->level)->toBe('read');
});

test("un ancien chef de cuisine ne perd aucun geste de la salle ni de la caisse", function () {
    $chef = User::factory()->create(['role' => 'restaurant_chief']);
    $chef->roles()->detach();
    affecter($chef, 'restaurant_chief');

    RepriseDesRoles::dedoublerLesChefsDeCuisine();
    $chef = $chef->fresh();
    $droits = app(PermissionResolver::class);

    foreach ([
        'restaurant.billing.paid', 'restaurant.billing.voir', 'restaurant.orders.creer', 'restaurant.orders.served',
        'restaurant.orders.status', 'restaurant.orders.reassign', 'restaurant.shifts.open', 'restaurant.breakfast.serve',
        'restaurant.recipes.creer', 'restaurant.pantry.items.creer', 'restaurant.stock_counts.close',
    ] as $droit) {
        expect($droits->allows($chef, $droit))->toBeTrue("{$droit} perdu par l'ancien chef de cuisine");
    }
});

test("le chef de cuisine seul tient la cuisine, plus la salle ni la caisse", function () {
    $chef = User::factory()->create(['role' => 'restaurant_chief']);
    $droits = app(PermissionResolver::class);

    expect($droits->allows($chef, 'restaurant.recipes.creer'))->toBeTrue()
        ->and($droits->allows($chef, 'restaurant.orders.ready'))->toBeTrue()
        ->and($droits->allows($chef, 'restaurant.billing.paid'))->toBeFalse()
        ->and($droits->allows($chef, 'restaurant.orders.creer'))->toBeFalse();
});

test('la reprise rejouée ne trouve plus rien à reprendre', function () {
    compteHerite('reception');
    $chef = User::factory()->create(['role' => 'restaurant_chief']);
    $chef->roles()->detach();
    affecter($chef, 'restaurant_chief');

    RepriseDesRoles::executer();

    expect(RepriseDesRoles::executer())->toBe(['affectes' => 0, 'ignores' => [], 'realignes' => 0, 'dedoubles' => 0]);
});

test("les affectations font foi : la colonne périmée n'accorde plus rien", function () {
    $compte = compteHerite('reception');
    affecter($compte, 'housekeeping_staff');
    $compte = $compte->fresh();

    expect($compte->hasRole('reception'))->toBeFalse()
        ->and($compte->exerce(['reception']))->toBeFalse()
        ->and(app(PermissionResolver::class)->allows($compte, 'bookings.voir'))->toBeFalse()
        ->and(User::havingRole(['reception'])->whereKey($compte->id)->exists())->toBeFalse();
});

test('la migration trace la reprise au journal d\'audit', function () {
    compteHerite('reception');

    (require database_path('migrations/2026_10_01_150000_reprend_les_roles_des_comptes.php'))->up();

    expect(DB::table('audit_logs')->where('event_type', 'roles_reprise')->exists())->toBeTrue();
});
