<?php

/**
 * L'économat a son département dès la création de l'établissement, et un
 * dépôt de service peut appartenir à l'économat.
 */

use App\Models\Role;
use App\Models\ServiceStore;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat']);
});

test("un établissement neuf a son département Économat", function () {
    $economat = DB::table('departments')->where('slug', 'economat')->first();

    expect($economat)->not->toBeNull()
        ->and($economat->name)->toBe('Économat')
        ->and((bool) $economat->is_active)->toBeTrue();
});

test("la migration ne double pas un département Économat déjà créé depuis la console", function () {
    $migration = require database_path('migrations/2026_10_30_160000_departement_economat.php');

    // Rejouée : rien de plus.
    $migration->up();
    expect(DB::table('departments')->where('slug', 'economat')->count())->toBe(1);

    // Créé par la console sous un autre identifiant : on le reconnaît à son nom.
    DB::table('departments')->where('slug', 'economat')->delete();
    DB::table('departments')->insert(['name' => 'ÉCONOMAT', 'slug' => 'magasin_central', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $migration->up();

    expect(DB::table('departments')->whereRaw('LOWER(name) IN (?, ?)', ['économat', 'economat'])->orWhere('name', 'ÉCONOMAT')->count())->toBe(1)
        ->and(DB::table('departments')->where('slug', 'economat')->exists())->toBeFalse();
});

test("un dépôt de service peut appartenir à l'économat", function () {
    $econome = User::factory()->create(['is_active' => true]);
    $econome->roles()->sync(Role::where('slug', 'econome')->pluck('id'));

    $this->actingAs($econome)->get(route('economat.stores.index'))->assertOk()
        ->assertSee('Économat (Magasin central)');

    $this->actingAs($econome)->post(route('economat.stores.store'), ['name' => 'Réserve du sous-sol', 'department' => 'economat'])
        ->assertSessionHasNoErrors();

    $depot = ServiceStore::where('name', 'Réserve du sous-sol')->sole();
    expect($depot->department)->toBe('economat')
        ->and($depot->departmentLabel())->toBe('Économat (Magasin central)');
});
