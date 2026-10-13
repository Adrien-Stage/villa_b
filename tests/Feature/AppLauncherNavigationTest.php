<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    activerModules([
        'economat', 'comptabilite', 'accounting', 'ledger', 'hebergement',
        'reservations', 'clients', 'analytics', 'restaurant', 'shop', 'discussions',
    ]);
});

test('la page d’accueil après connexion affiche le lanceur d’applications avec les tuiles autorisées', function () {
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $response = $this->actingAs($manager)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Espace de Travail')
        ->assertSee('Hôtel')
        ->assertSee('Restaurant')
        ->assertSee('Économat')
        ->assertSee('Comptabilité')
        ->assertSee('Gestion')
        ->assertSee('Boutique')
        ->assertSee('Planning')
        ->assertSee('Activité & KPI')
        ->assertSee(route('rooms.index'))
        ->assertSee(route('restaurant.orders.index'))
        ->assertSee(route('economat.index'))
        ->assertSee(route('accounting.index'))
        ->assertSee(route('dashboard.overview'));
});

test('dans un groupe dense comme le restaurant, la barre latérale n’affiche que les éléments du restaurant', function () {
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $response = $this->actingAs($manager)->get(route('restaurant.orders.index'));

    $response->assertOk();
    $html = $response->getContent();

    // La barre latérale est active et affiche le bouton de retour aux applications
    expect($html)->toContain('Applications')
        ->and($html)->toContain(route('dashboard'))
        // Les liens du restaurant sont présents
        ->and($html)->toContain('Commandes')
        ->and($html)->toContain('Petits-déjeuners')
        // Les autres modules denses ne polluent pas la barre latérale du restaurant
        ->and($html)->not->toContain('>Hôtel<')
        ->and($html)->not->toContain('>Économat<');
});

test('dans un groupe dense comme l’économat, la barre latérale n’affiche que les éléments de l’économat', function () {
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $response = $this->actingAs($manager)->get(route('economat.index'));

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('Applications')
        ->and($html)->toContain('Vue d\'ensemble')
        ->and($html)->toContain('Articles')
        ->and($html)->not->toContain('>Restaurant<')
        ->and($html)->not->toContain('>Hôtel<');
});

test('dans un groupe léger comme la gestion, le système d’onglets horizontaux est affiché sans sidebar', function () {
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $response = $this->actingAs($manager)->get(route('customers.index'));

    $response->assertOk();
    $html = $response->getContent();

    // Les onglets horizontaux sont rendus
    expect($html)->toContain('Clients')
        ->and($html)->toContain('Utilisateurs')
        ->and($html)->toContain(route('customers.index'))
        ->and($html)->toContain(route('users.index'));
});

test('dans la boutique, le système d’onglets horizontaux affiche articles, commandes et compta', function () {
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $response = $this->actingAs($manager)->get(route('shop.products.index'));

    $response->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('Articles')
        ->and($html)->toContain('Commandes')
        ->and($html)->toContain('Compta Boutique');
});

test('l’écran activité & KPI est accessible directement via la route dashboard.overview', function () {
    $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    $response = $this->actingAs($manager)->get(route('dashboard.overview'));

    $response->assertOk()
        ->assertSee('Tableau de bord')
        ->assertSee('Occupation en temps réel')
        ->assertSee('Retour aux applications');
});
