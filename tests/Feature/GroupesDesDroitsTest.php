<?php

/**
 * La matrice des droits se range par service responsable, pas par préfixe
 * technique : les fournisseurs et les bons de commande relèvent des achats,
 * même si leurs routes vivent sous /economat.
 */

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionMatrix;
use App\Support\PermissionCatalog;
use App\Support\PermissionLabels;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
});

test("chaque droit du catalogue a son groupe et son nom ; seul le droit de développement sort de la matrice", function () {
    $droits = array_keys(PermissionCatalog::all());
    $sansGroupe = array_values(array_filter($droits, fn (string $d) => PermissionLabels::groupe($d) === 'autre'));
    $horsMatrice = array_values(array_filter($droits, fn (string $d) => PermissionLabels::groupe($d) === null));
    $sansNom = array_values(array_filter($droits, fn (string $d) => PermissionLabels::groupe($d) !== null && ! PermissionLabels::estNomme($d)));

    expect($sansGroupe)->toBe([])
        ->and($horsMatrice)->toBe(['test-popup.voir'])
        ->and($sansNom)->toBe([]);

    // Le classement reprend chaque droit de la matrice une fois, dans l'ordre des groupes.
    $classement = PermissionLabels::classement($droits);
    expect(count($classement))->toBe(count($droits) - 1)
        ->and(array_values(array_unique(array_column($classement, 'groupe'))))
        ->toBe(array_values(array_intersect(array_keys(PermissionLabels::GROUPES), array_column($classement, 'groupe'))));
});

test("les achats regroupent fournisseurs, bons de commande, demandes, propositions et tarifs ; le magasin garde les bons d'entrée", function () {
    $matrice = PermissionLabels::matrice(array_keys(PermissionCatalog::all()));

    expect($matrice['achats'])->toContain(
        'achats.voir', 'economat.suppliers.creer', 'economat.orders.send', 'economat.purchase_requests.creer',
        'economat.control.suggestions.creer', 'economat.items.suppliers.link'
    )
        ->and($matrice['economat'])->toContain('economat.receipts.creer', 'economat.items.creer', 'economat.control.voir')
        ->and($matrice['economat'])->not->toContain('economat.suppliers.creer', 'economat.orders.send')
        ->and($matrice['caisse_reception'])->toContain('bookings.cash_register.voir', 'reception.pos.voir', 'invoices.voir')
        ->and($matrice['hebergement'])->toContain('bookings.creer', 'rooms.voir')->not->toContain('bookings.cash_register.voir')
        ->and($matrice['offre'])->toContain('settings.services.creer', 'settings.partners.creer')
        ->and($matrice['comptabilite'])->toContain('rooms.cost_sheets.voir')
        ->and($matrice['administration'])->toContain('settings.modifier', 'users.creer', 'droits.modifier');

    // Le restaurant : la salle avant la caisse, la caisse avant la carte, la configuration à la fin.
    $restaurant = $matrice['restaurant'];
    expect(array_search('restaurant.orders.voir', $restaurant))->toBeLessThan(array_search('restaurant.cash_register.voir', $restaurant))
        ->and(array_search('restaurant.cash_register.voir', $restaurant))->toBeLessThan(array_search('restaurant.menus.voir', $restaurant))
        ->and(end($restaurant))->toStartWith('restaurant.restaurants');
});

test("l'écran Rôles & droits et l'API de la console montrent les groupes par service", function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->roles()->sync(Role::where('slug', 'admin')->pluck('id'));

    $this->actingAs($admin)->get(route('droits.index'))->assertOk()
        ->assertSeeInOrder(['Hébergement', 'Caisse &amp; facturation réception', 'Achats', 'Économat'], false)
        ->assertSee('Fournisseurs — Créer')->assertDontSee('test-popup.voir');

    $classement = app(PermissionMatrix::class)->donnees()['classement'];
    expect($classement['economat.suppliers.creer'])->toMatchArray([
        'groupe' => 'achats', 'groupe_libelle' => 'Achats', 'ecran' => 'Fournisseurs', 'action' => 'Créer',
    ]);
});
