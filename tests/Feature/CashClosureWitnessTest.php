<?php

/**
 * Comptage contradictoire : la caisse est comptée par son titulaire mais
 * close par la comptabilité, toujours.
 *
 * L'enjeu tient en une phrase : compter soi-même l'argent qu'on a encaissé,
 * sans témoin, prive l'écart de caisse de sa valeur probante. Ce n'est plus
 * un réglage : ni l'établissement ni le manager ne peuvent en dispenser.
 */

use App\Models\CashRegisterSession;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CashClosurePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Inscrit dans les paramètres une ancienne politique de clôture. */
function ancienReglageDeCloture(string $temoin, array $modules = ['reception', 'shop']): void
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

test('le comptage déclaré ne clôt jamais la caisse', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

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
    // Un comptable qui tiendrait une caisse, par dérogation : son rôle
    // l'habiliterait, mais se contrôler soi-même ne ferait que déplacer le
    // problème.
    $comptable = User::factory()->create(['role' => 'accountant']);
    $collegue  = User::factory()->create(['role' => 'accountant']);

    expect(CashClosurePolicy::canWitness($comptable, $comptable->id))->toBeFalse()
        ->and(CashClosurePolicy::canWitness($collegue, $comptable->id))->toBeTrue();
});

test('le manager ne contresigne plus les comptages', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

    $agent   = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900);

    // Il supervise les caisses : les faire contrôler par lui ne séparerait rien.
    expect(CashClosurePolicy::canWitness($manager, $agent->id))->toBeFalse();

    $this->actingAs($manager);
    $this->post(route('accounting.cash_reviews.store', $session), [],
        ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);

    expect($session->refresh()->closed_at)->toBeNull();
});

test('le responsable administratif et financier contresigne aussi', function () {
    $raf = User::factory()->create(['role' => 'finance_manager']);

    expect(CashClosurePolicy::canWitness($raf, User::factory()->create(['role' => 'reception'])->id))->toBeTrue();
});

test('un rôle non habilité ne contresigne pas', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

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

test("un ancien réglage de l'établissement ne dispense plus du contrôle", function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    // Paramètres enregistrés avant que la règle ne soit fixée : « personne ».
    ancienReglageDeCloture('aucun');

    $agent = User::factory()->create(['role' => 'reception']);
    $session = caisseOuverte($agent);

    $this->actingAs($agent);
    declarerComptage(900)->assertRedirect();

    expect($session->refresh()->status)->toBe(CashClosurePolicy::STATUS_PENDING_REVIEW)
        ->and($session->closed_at)->toBeNull();
});

test('une caisse déjà close ne se contresigne pas deux fois', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

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
