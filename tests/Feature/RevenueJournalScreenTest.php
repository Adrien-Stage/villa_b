<?php

/**
 * Écran du journal des encaissements.
 *
 * Le service existait sans que personne puisse l'ouvrir : la ventilation par
 * point de vente restait invisible, donc inutile.
 */

use App\Models\PointOfSale;
use App\Models\User;
use App\Support\PointOfSaleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['comptabilite', 'accounting']);
    PointOfSaleCatalog::sync();
});

function encaisser(string $slug, int $montant, string $mode): void
{
    DB::table('payments')->insert([
        'point_of_sale_id' => PointOfSale::where('slug', $slug)->value('id'),
        'amount'           => $montant,
        'method'           => $mode,
        'reference'        => 'PAY-' . random_int(1, 999999),
        'status'           => 'completed',
        'currency'         => 'XAF',
        'paid_at'          => now(),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);
}

test("le comptable ouvre le journal et y lit la ventilation", function () {
    encaisser('hotel', 73_200_00, 'cash');
    encaisser('restaurant', 17_500_00, 'orange_money');

    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('accounting.revenue_journal'))
        ->assertOk()
        ->assertSee('Hôtel')
        ->assertSee('Restaurant')
        ->assertSee('Espèces')
        ->assertSee('Orange Money')
        ->assertSee('90 700 FCFA');   // le total, en francs
});

test("les recettes sans point de vente sont signalées, pas tues", function () {
    DB::table('payments')->insert([
        'point_of_sale_id' => null, 'amount' => 9_000_00, 'method' => 'cash',
        'reference' => 'PAY-X', 'status' => 'completed', 'currency' => 'XAF',
        'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('accounting.revenue_journal'))
        ->assertSee('Non rattaché')
        ->assertSee('recettes sans point de vente');
});

test("une période sans mouvement le dit plutôt que d'afficher un tableau vide", function () {
    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('accounting.revenue_journal', ['from' => '2020-01-01', 'to' => '2020-01-02']))
        ->assertOk()
        ->assertSee('Aucun encaissement');
});

test("seuls les modes rencontrés font des colonnes", function () {
    encaisser('hotel', 1_000_00, 'cash');

    $reponse = $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('accounting.revenue_journal'));

    // Quinze colonnes vides ne se lisent pas.
    $reponse->assertSee('Espèces')->assertDontSee('MTN Mobile Money');
});

test("le contrôleur de gestion consulte le journal", function () {
    encaisser('hotel', 1_000_00, 'cash');

    $this->actingAs(User::factory()->create(['role' => 'controller']))
        ->get(route('accounting.revenue_journal'))->assertOk();
});

test("un rôle sans la comptabilité n'y accède pas", function () {
    expect($this->actingAs(User::factory()->create(['role' => 'housekeeping_staff']))
        ->get(route('accounting.revenue_journal'))->status())->not->toBe(200);
});

test("l'onglet figure dans la navigation du module", function () {
    encaisser('hotel', 1_000_00, 'cash');

    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('accounting.cash'))
        ->assertSee('Journal des encaissements');
});
