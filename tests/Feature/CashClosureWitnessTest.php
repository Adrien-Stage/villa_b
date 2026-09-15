<?php

/**
 * Comptage contradictoire : quand l'établissement l'exige, la caisse est
 * comptée par son titulaire mais close par un tiers.
 *
 * L'enjeu tient en une phrase : compter soi-même l'argent qu'on a encaissé,
 * sans témoin, prive l'écart de caisse de sa valeur probante.
 */

use App\Models\CashRegisterSession;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CashClosurePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Règle la politique de clôture de l'établissement. */
function politiqueClotureCaisse(string $temoin, array $modules = ['reception', 'shop']): void
{
    $tenant = Tenant::firstOrFail();
    $tenant->settings = array_merge($tenant->settings ?? [], [
        'caisse' => [
            'closure_witness' => $temoin,
            'closure_witness_modules' => $modules,
        ],
    ]);
    $tenant->save();
}

function caisseOuverte(User $agent, int $fond = 100000): CashRegisterSession
{
    return CashRegisterSession::create([
        'user_id' => $agent->id,
        'module' => 'reception',
        'opening_amount' => $fond,
        'opened_at' => now(),
    ]);
}

function declarerComptage(int $compteEnFcfa = 900)
{
    return test()->post(route('bookings.cash_register.close.store'), [
        'actual_closing_amount' => (string) $compteEnFcfa,
        'closing_notes' => 'Fin de service',
    ]);
}

test('le comptage déclaré ne clôt pas la caisse quand un témoin est exigé', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_ACCOUNTANT);

    $agent = User::factory()->create(['role' => 'reception']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900)->assertRedirect();

    $session->refresh();

    expect($session->status)->toBe(CashClosurePolicy::STATUS_PENDING_REVIEW)
        ->and($session->closed_at)->toBeNull()
        // L'écart est déjà calculé, mais pas encore constaté : il attend le tiers.
        ->and($session->theoretical_closing_amount)->toBe(100000)
        ->and($session->actual_closing_amount)->toBe(90000)
        ->and($session->discrepancy_amount)->toBe(-10000)
        ->and($session->witness_id)->toBeNull();
});

test('une caisse comptée n\'encaisse plus', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_ACCOUNTANT);

    $agent = User::factory()->create(['role' => 'reception']);
    caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage();

    // Un décaissement après déclaration ferait diverger le tiroir du comptage
    // que le tiers s'apprête à contresigner.
    $this->post(route('bookings.cash_register.disbursements.store'), [
        'amount' => 500,
        'reason' => 'Achat tardif',
    ])->assertNotFound();
});

test("l'agent ne peut ni recompter ni rouvrir sa caisse une fois déclarée", function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_ACCOUNTANT);

    $agent = User::factory()->create(['role' => 'reception']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900);

    // Reprendre le comptage reviendrait à corriger sa copie avant correction.
    $this->get(route('bookings.cash_register.close'))->assertNotFound();
    $this->post(route('cash_register.resume'), ['session_id' => $session->id])
        ->assertNotFound();

    expect($session->refresh()->status)->toBe(CashClosurePolicy::STATUS_PENDING_REVIEW);
});

test('le comptable contresigne et la caisse est close', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_ACCOUNTANT);

    $agent = User::factory()->create(['role' => 'reception']);
    $comptable = User::factory()->create(['role' => 'accountant']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900);

    $this->actingAs($comptable);
    $this->get(route('accounting.cash_reviews'))->assertStatus(200)->assertSee($agent->name);

    $this->post(route('accounting.cash_reviews.store', $session), [
        'witness_notes' => 'Recomptage effectué en présence de l\'agent.',
    ])->assertRedirect();

    $session->refresh();

    expect($session->status)->toBe('closed')
        ->and($session->closed_at)->not->toBeNull()
        ->and($session->witness_id)->toBe($comptable->id)
        ->and($session->witnessed_at)->not->toBeNull()
        // Le contrôleur ne retouche pas les montants : l'écart survit au contrôle.
        ->and($session->discrepancy_amount)->toBe(-10000);
});

test('le déclarant ne peut pas contresigner son propre comptage', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_MANAGER);

    // Un manager qui tient lui-même la caisse : son rôle l'habiliterait, mais
    // se contrôler soi-même ne ferait que déplacer le problème.
    $manager = User::factory()->create(['role' => 'manager']);
    $session = caisseOuverte($manager);

    $this->actingAs($manager);
    declarerComptage(900);

    $this->post(route('accounting.cash_reviews.store', $session))->assertStatus(403);

    expect($session->refresh()->closed_at)->toBeNull();
});

test('un rôle non habilité ne contresigne pas', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_ACCOUNTANT);

    $agent = User::factory()->create(['role' => 'reception']);
    $collegue = User::factory()->create(['role' => 'reception']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900);

    $this->actingAs($collegue);
    // Le middleware de rôle redirige une navigation ordinaire et ne répond
    // 403 qu'à une requête XHR : on se place dans ce second cas.
    $this->post(route('accounting.cash_reviews.store', $session), [],
        ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);

    expect($session->refresh()->closed_at)->toBeNull();
});

test('la règle ne vaut que pour les caisses désignées', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    // La boutique est soumise au contrôle, la réception non.
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_ACCOUNTANT, ['shop']);

    $agent = User::factory()->create(['role' => 'reception']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900)->assertRedirect();

    expect($session->refresh()->closed_at)->not->toBeNull()
        ->and($session->status)->toBe('closed');
});

test('une caisse déjà close ne se contresigne pas deux fois', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    politiqueClotureCaisse(CashClosurePolicy::WITNESS_ACCOUNTANT);

    $agent = User::factory()->create(['role' => 'reception']);
    $comptable = User::factory()->create(['role' => 'accountant']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900);

    $this->actingAs($comptable);
    $this->post(route('accounting.cash_reviews.store', $session))->assertRedirect();
    $this->post(route('accounting.cash_reviews.store', $session))
        ->assertSessionHasErrors('session');

    expect($session->refresh()->witness_id)->toBe($comptable->id);
});
