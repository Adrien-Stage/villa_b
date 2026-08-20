<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // La tour de contrôle vit derrière le module analytics (cache statique).
    $prop = new \ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['analytics']);
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Encaissement daté à la main : c'est la date qui porte tout l'intérêt du
 * filtre, pas le montant.
 */
function encaissement(string $date, int $montant): Payment
{
    return Payment::create([
        'amount'    => $montant,
        'currency'  => 'XAF',
        'method'    => 'cash',
        'status'    => 'completed',
        'reference' => 'PAY-' . $date . '-' . $montant,
        'paid_at'   => Carbon::parse($date),
    ]);
}

test('le filtre annuel sert les chiffres du millésime demandé', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

    $manager = User::factory()->create(['role' => 'manager']);

    encaissement('2023-03-08', 500000);
    encaissement('2023-11-02', 250000);
    encaissement('2026-02-19', 900000);

    $this->actingAs($manager);

    // Une année révolue : seuls ses encaissements, et le tout jusqu'en décembre.
    $response = $this->get(route('analytics.index', ['period' => 'year', 'year' => 2023]));

    $response->assertStatus(200);
    expect($response->viewData('year'))->toBe(2023);
    expect($response->viewData('hotelRevenue'))->toBe(750000);
    expect($response->viewData('periodLabel'))->toBe("l'année 2023");
    expect($response->viewData('startDate')->toDateString())->toBe('2023-01-01');
    expect($response->viewData('endDate')->toDateString())->toBe('2023-12-31');
    expect($response->viewData('chartLabels'))->toHaveCount(12);

    // Les recettes retombent sur leur mois : mars et novembre, rien ailleurs.
    $courbe = $response->viewData('chartHotel');
    expect($courbe[2])->toEqual(5000);
    expect($courbe[10])->toEqual(2500);
    expect(array_sum($courbe))->toEqual(7500);
});

test("l'année en cours s'arrête à aujourd'hui", function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

    $manager = User::factory()->create(['role' => 'manager']);
    encaissement('2026-02-19', 900000);

    $this->actingAs($manager);

    $response = $this->get(route('analytics.index', ['period' => 'year']));

    $response->assertStatus(200);
    expect($response->viewData('year'))->toBe(2026);
    expect($response->viewData('hotelRevenue'))->toBe(900000);
    expect($response->viewData('periodLabel'))->toBe('cette année');
    expect($response->viewData('endDate')->toDateString())->toBe('2026-05-14');
    // Cinq mois écoulés : le graphe ne prolonge pas l'année.
    expect($response->viewData('chartLabels'))->toHaveCount(5);

});

test('le filtre propose chaque millésime depuis la première recette', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

    $manager = User::factory()->create(['role' => 'manager']);
    encaissement('2023-03-08', 500000);

    $this->actingAs($manager);

    $response = $this->get(route('analytics.index'));

    expect($response->viewData('annees'))->toBe([2026, 2025, 2024, 2023]);

});

test('une année fantaisiste dans l\'URL retombe sur l\'année en cours', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

    $manager = User::factory()->create(['role' => 'manager']);
    encaissement('2026-02-19', 900000);

    $this->actingAs($manager);

    $response = $this->get(route('analytics.index', ['period' => 'year', 'year' => 1998]));

    $response->assertStatus(200);
    expect($response->viewData('year'))->toBe(2026);
    expect($response->viewData('hotelRevenue'))->toBe(900000);

});

test('le rapport imprimable suit le millésime choisi', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-14 10:00:00'));

    $manager = User::factory()->create(['role' => 'manager']);
    encaissement('2023-03-08', 500000);
    encaissement('2026-02-19', 900000);

    $this->actingAs($manager);

    $response = $this->get(route('analytics.print', ['period' => 'year', 'year' => 2023, 'department' => 'all']));

    $response->assertStatus(200);
    expect($response->viewData('year'))->toBe(2023);
    expect($response->viewData('hotelRevenue'))->toBe(500000);
    expect($response->viewData('startDate')->toDateString())->toBe('2023-01-01');
    expect($response->viewData('endDate')->toDateString())->toBe('2023-12-31');

});
