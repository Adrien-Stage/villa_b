<?php

/**
 * Nettoyage des droits hérités (migration 2026_10_03_150000).
 *
 * Personne ne perd ni ne gagne un droit : un compte qui n'existait que par
 * la colonne users.role reçoit l'affectation correspondante, et chaque
 * restriction de module de l'ancienne console devient une exception
 * nominative, visible et modifiable. Les tables et la colonne disparaissent.
 */

use App\Models\PermissionGrant;
use App\Models\User;
use App\Services\PermissionResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['restaurant', 'economat']);
});

/** Remet en place ce que la migration supprime, comme sur une base d'avant. */
function baseDAvantLeNettoyage(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('role', 30)->nullable();
        $table->index(['role']);
    });
    Schema::create('user_module_permissions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->string('module_key', 50);
        $table->string('access_level', 15)->default('inherit');
        $table->timestamps();
    });
    Schema::create('department_module', function (Blueprint $table) {
        $table->id();
        $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
        $table->string('module_key', 50);
        $table->string('default_level', 10)->default('write');
        $table->timestamps();
    });
}

function nettoyer(): void
{
    (require database_path('migrations/2026_10_03_150000_nettoyage_des_droits_herites.php'))->up();
    app(PermissionResolver::class)->forget();
}

test("un compte qui n'existait que par sa colonne reçoit l'affectation correspondante", function () {
    baseDAvantLeNettoyage();
    $id = DB::table('users')->insertGetId([
        'name' => 'Ancien serveur', 'email' => 'ancien@hotel.test', 'password' => 'x', 'role' => 'restaurant_staff',
        'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    nettoyer();

    $ancien = User::find($id);
    expect(Schema::hasColumn('users', 'role'))->toBeFalse()
        ->and($ancien->rolesDetenus())->toBe(['restaurant_staff'])
        ->and($ancien->role)->toBe('restaurant_staff');
});

test("une restriction de l'ancienne console devient une exception nominative", function () {
    baseDAvantLeNettoyage();
    $chef = User::factory()->create(['role' => 'restaurant_chief']);
    $serveur = User::factory()->create(['role' => 'restaurant_staff']);
    DB::table('user_module_permissions')->insert([
        ['user_id' => $chef->id, 'module_key' => 'restaurant', 'access_level' => 'read', 'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $serveur->id, 'module_key' => 'restaurant', 'access_level' => 'none', 'created_at' => now(), 'updated_at' => now()],
    ]);

    nettoyer();

    $droits = app(PermissionResolver::class);
    expect(Schema::hasTable('user_module_permissions'))->toBeFalse()
        ->and(Schema::hasTable('department_module'))->toBeFalse()
        // Lecture seule au restaurant : il consulte, il n'écrit plus ; ailleurs, rien ne change.
        ->and($droits->allows($chef->fresh(), 'restaurant.menus.voir'))->toBeTrue()
        ->and($droits->allows($chef->fresh(), 'restaurant.menus.items.creer'))->toBeFalse()
        ->and($droits->allows($chef->fresh(), 'economat.requisitions.creer'))->toBeTrue()
        // Exclu du restaurant : plus rien.
        ->and($droits->allows($serveur->fresh(), 'restaurant.orders.voir'))->toBeFalse();

    $exception = PermissionGrant::where('subject_type', 'user')->where('subject_id', (string) $chef->id)
        ->where('permission', 'restaurant.menus.items.creer')->sole();
    expect($exception->effect)->toBe('deny')
        ->and($exception->origin)->toBe(PermissionGrant::ORIGINE_ETABLISSEMENT)
        ->and($exception->reason)->toContain('lecture seule');
});
