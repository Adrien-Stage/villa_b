<?php

use App\Models\Account;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\Role;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** Comptable connecté — le rôle qui pilote le module. */
function comptable(): User
{
    $user = User::create([
        'name'      => 'Comptable',
        'email'     => 'comptable@test.cm',
        'password'  => bcrypt('secret'),
        'role'      => 'accountant',
        'is_active' => true,
    ]);

    // roles:sync a livré le catalogue ; on rattache par le pivot.
    $role = Role::where('slug', 'accountant')->first();
    if ($role) {
        $user->roles()->attach($role->id, ['level' => 'write']);
    }

    return $user;
}

function ecritureType(?Carbon $date = null): \App\Models\JournalEntry
{
    return app(LedgerService::class)->post(
        Journal::SALES,
        $date ?? Carbon::parse('2026-06-15'),
        'Nuitée chambre 12',
        [
            ['account' => Account::CLIENTS, 'debit' => 1_192_500],
            ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1_000_000, 'center' => 'hebergement'],
            ['account' => Account::VAT_COLLECTED, 'credit' => 192_500],
        ]
    );
}

// ── Accès ───────────────────────────────────────────────────────────────────

test('le module est fermé aux visiteurs non connectés', function () {
    $this->get(route('accounting.ledger.index'))->assertRedirect(route('login'));
});

test('un réceptionniste n’accède pas à la comptabilité générale', function () {
    $user = User::create([
        'name' => 'Réception', 'email' => 'rec@test.cm', 'password' => bcrypt('x'),
        'role' => 'reception', 'is_active' => true,
    ]);

    // EnsureRoleAccess redirige avec un message plutôt que de renvoyer 403 :
    // le 403 est réservé aux requêtes AJAX, qui affichent le popup.
    $this->actingAs($user)
        ->get(route('accounting.ledger.index'))
        ->assertRedirect()
        ->assertSessionHas('access_denied_popup', true);

    $this->actingAs($user)
        ->getJson(route('accounting.ledger.index'))
        ->assertForbidden();
});

// ── Les écrans répondent ────────────────────────────────────────────────────

test('tous les écrans du grand livre s’ouvrent', function () {
    ecritureType();
    $this->actingAs(comptable());

    foreach ([
        'accounting.ledger.index',
        'accounting.ledger.balance',
        'accounting.ledger.general',
        'accounting.ledger.journals',
        'accounting.ledger.accounts',
        'accounting.ledger.periods',
        'accounting.ledger.opening',
    ] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('le module s’ouvre même sans aucune écriture', function () {
    $this->actingAs(comptable());

    $this->get(route('accounting.ledger.index'))->assertOk();
    $this->get(route('accounting.ledger.balance'))->assertOk();
    $this->get(route('accounting.ledger.general'))->assertOk();
});

// ── Balance ─────────────────────────────────────────────────────────────────

test('la balance affiche les comptes mouvementés et s’équilibre', function () {
    ecritureType();

    $response = $this->actingAs(comptable())
        ->get(route('accounting.ledger.balance', ['from' => '2026-01-01', 'to' => '2026-12-31']));

    $response->assertOk()
        ->assertSee(Account::CLIENTS)
        ->assertSee(Account::REVENUE_ACCOMMODATION)
        ->assertSee('équilibrée');

    $totaux = $response->viewData('totaux');
    expect($totaux['debit'])->toBe($totaux['credit']);
});

// ── Grand livre ─────────────────────────────────────────────────────────────

test('le grand livre d’un compte présente son solde progressif', function () {
    ecritureType();

    $response = $this->actingAs(comptable())->get(route('accounting.ledger.general', [
        'compte' => Account::CLIENTS,
        'from'   => '2026-01-01',
        'to'     => '2026-12-31',
    ]));

    $response->assertOk();

    $detail = $response->viewData('detail');
    expect($detail['opening'])->toBe(0);
    expect($detail['debit'])->toBe(1_192_500);
    expect($detail['closing'])->toBe(1_192_500);
    expect($detail['lines'])->toHaveCount(1);
});

// ── Contre-passation depuis l'écran ─────────────────────────────────────────

test('une écriture se contre-passe depuis sa fiche, avec un motif', function () {
    $entry = ecritureType();

    $this->actingAs(comptable())
        ->post(route('accounting.ledger.entry.reverse', $entry), ['reason' => 'Erreur de chambre'])
        ->assertRedirect();

    expect($entry->fresh()->isReversed())->toBeTrue();
});

test('une contre-passation sans motif est refusée', function () {
    $entry = ecritureType();

    $this->actingAs(comptable())
        ->post(route('accounting.ledger.entry.reverse', $entry), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($entry->fresh()->isReversed())->toBeFalse();
});

// ── Périodes ────────────────────────────────────────────────────────────────

test('une période se verrouille depuis l’écran des périodes', function () {
    $entry = ecritureType();
    $periode = $entry->period;

    $this->actingAs(comptable())
        ->post(route('accounting.ledger.periods.lock', $periode))
        ->assertRedirect();

    expect($periode->fresh()->isLocked())->toBeTrue();
});

test('un exercice s’ouvre avec ses douze périodes', function () {
    $this->actingAs(comptable())
        ->post(route('accounting.ledger.years.open'), ['year' => 2027])
        ->assertRedirect();

    $exercice = FiscalYear::where('label', 'Exercice 2027')->first();
    expect($exercice)->not->toBeNull();
    expect($exercice->periods()->count())->toBe(12);
});

// ── À-nouveaux ──────────────────────────────────────────────────────────────

test('la reprise des à-nouveaux enregistre une écriture équilibrée', function () {
    $exercice = FiscalYear::openYear(2026);

    $this->actingAs(comptable())->post(route('accounting.ledger.opening.store'), [
        'fiscal_year_id' => $exercice->id,
        'lines' => [
            ['account' => Account::BANK,    'debit' => 50_000, 'credit' => 0],
            ['account' => '101000',         'debit' => 0,      'credit' => 50_000],
        ],
    ])->assertRedirect(route('accounting.ledger.balance'));

    expect($exercice->fresh()->hasOpeningBalance())->toBeTrue();

    // Saisie en francs, stockage en centimes.
    $ligne = \App\Models\JournalEntryLine::where('account_code', Account::BANK)->first();
    expect($ligne->debit)->toBe(5_000_000);
});

test('une balance d’ouverture déséquilibrée est refusée', function () {
    $exercice = FiscalYear::openYear(2026);

    $this->actingAs(comptable())->post(route('accounting.ledger.opening.store'), [
        'fiscal_year_id' => $exercice->id,
        'lines' => [
            ['account' => Account::BANK, 'debit' => 50_000, 'credit' => 0],
            ['account' => '101000',      'debit' => 0,      'credit' => 30_000],
        ],
    ])->assertSessionHas('error');

    expect($exercice->fresh()->hasOpeningBalance())->toBeFalse();
});

// ── Navigation ──────────────────────────────────────────────────────────────

test('la comptabilité de caisse renvoie vers la comptabilité générale', function () {
    $this->actingAs(comptable())
        ->get(route('accounting.index'))
        ->assertOk()
        ->assertSee(route('accounting.ledger.index'));
});
