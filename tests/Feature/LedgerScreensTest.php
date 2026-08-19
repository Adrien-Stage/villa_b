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
    // firstOrCreate : un test peut appeler ce helper plusieurs fois, il ne
    // doit pas buter sur l'unicité de l'adresse.
    $user = User::firstOrCreate(
        ['email' => 'comptable@test.cm'],
        [
            'name'      => 'Comptable',
            'password'  => bcrypt('secret'),
            'role'      => 'accountant',
            'is_active' => true,
        ]
    );

    // roles:sync a livré le catalogue ; on rattache par le pivot.
    $role = Role::where('slug', 'accountant')->first();
    if ($role && !$user->roles()->where('roles.id', $role->id)->exists()) {
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
        'accounting.ledger.night_audit',
    ] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('le module s’ouvre même sans aucune écriture', function () {
    $this->actingAs(comptable());

    $this->get(route('accounting.ledger.index'))->assertOk();
    $this->get(route('accounting.ledger.balance'))->assertOk();
    $this->get(route('accounting.ledger.general'))->assertOk();
    $this->get(route('accounting.ledger.night_audit'))->assertOk();
});

test('la clôture d’une journée se déclenche depuis l’écran', function () {
    ecritureType(Carbon::parse('2026-06-15'));

    $this->actingAs(comptable())
        ->post(route('accounting.ledger.night_audit.run'), ['date' => '2026-06-15'])
        ->assertRedirect();

    expect(\App\Models\NightAudit::isClosed(Carbon::parse('2026-06-15')))->toBeTrue();
});

test('clôturer deux fois la même journée est refusé avec un message', function () {
    ecritureType(Carbon::parse('2026-06-15'));

    $this->actingAs(comptable())->post(route('accounting.ledger.night_audit.run'), ['date' => '2026-06-15']);

    $this->actingAs(comptable())
        ->post(route('accounting.ledger.night_audit.run'), ['date' => '2026-06-15'])
        ->assertSessionHas('error');
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

// ── Comptabilité auxiliaire ─────────────────────────────────────────────────

test('l’écran des tiers s’ouvre sur le compte clients', function () {
    ecritureType();

    $this->actingAs(comptable())
        ->get(route('accounting.ledger.auxiliary'))
        ->assertOk()
        ->assertSee(Account::CLIENTS);
});

test('la balance âgée s’affiche', function () {
    ecritureType();

    $this->actingAs(comptable())
        ->get(route('accounting.ledger.aged'))
        ->assertOk()
        ->assertSee('Balance âgée');
});

test('le grand livre d’un tiers refuse un type d’auxiliaire inconnu', function () {
    ecritureType();

    // Le type vient de l'URL : il ne doit jamais servir à instancier
    // n'importe quelle classe de l'application.
    $this->actingAs(comptable())
        ->get(route('accounting.ledger.auxiliary.ledger', [
            'compte' => Account::CLIENTS,
            'type'   => \App\Models\User::class,
            'id'     => 1,
        ]))
        ->assertForbidden();
});

test('un lettrage à une seule ligne est rejeté par la validation', function () {
    $entry = ecritureType();
    $ligne = $entry->lines->firstWhere('account_code', Account::CLIENTS);

    $this->actingAs(comptable())
        ->from(route('accounting.ledger.auxiliary'))
        ->post(route('accounting.ledger.reconcile'), ['lines' => [$ligne->id]])
        ->assertSessionHasErrors('lines');
});

test('le lettrage automatique signale l’absence de rapprochement', function () {
    ecritureType();

    $this->actingAs(comptable())
        ->from(route('accounting.ledger.auxiliary'))
        ->post(route('accounting.ledger.reconcile.auto'), ['compte' => Account::CLIENTS])
        ->assertSessionHas('error');
});

// ── Fournisseurs et retenues à la source ────────────────────────────────────

test('les écrans fournisseurs s’ouvrent', function () {
    $this->actingAs(comptable())->get(route('accounting.ledger.suppliers'))->assertOk();
    $this->actingAs(comptable())->get(route('accounting.ledger.suppliers.create'))->assertOk();
    $this->actingAs(comptable())->get(route('accounting.ledger.withholding'))->assertOk();
});

test('la saisie d’une facture fournisseur produit son écriture', function () {
    $fournisseur = \App\Models\Supplier::create([
        'name' => 'Établissements Mbarga', 'email' => 'mbarga@test.cm', 'is_active' => true,
    ]);

    $this->actingAs(comptable())->post(route('accounting.ledger.suppliers.store'), [
        'supplier_id'      => $fournisseur->id,
        'number'           => 'FA-2026-0142',
        'invoice_date'     => '2026-06-15',
        'charge_account'   => '601000',
        'label'            => 'Approvisionnement économat',
        // Saisie en francs.
        'amount_ttc'       => 11_925,
        'withholding_type' => \App\Models\SupplierInvoice::WITHHOLDING_SERVICES,
    ])->assertRedirect(route('accounting.ledger.suppliers'));

    $facture = \App\Models\SupplierInvoice::first();

    expect($facture->amount_ttc)->toBe(1_192_500);
    expect($facture->isPosted())->toBeTrue();
    expect($facture->net_payable)->toBeLessThan($facture->amount_ttc);
});

test('une référence déjà saisie pour ce fournisseur est refusée', function () {
    $fournisseur = \App\Models\Supplier::create([
        'name' => 'Fournisseur', 'email' => 'f@test.cm', 'is_active' => true,
    ]);

    $payload = [
        'supplier_id'    => $fournisseur->id,
        'number'         => 'FA-DOUBLON',
        'invoice_date'   => '2026-06-15',
        'charge_account' => '601000',
        'label'          => 'Achat',
        'amount_ttc'     => 5_000,
    ];

    $this->actingAs(comptable())->post(route('accounting.ledger.suppliers.store'), $payload)->assertRedirect();

    // La double saisie doublerait la charge et la dette : elle s'arrête ici.
    $this->actingAs(comptable())
        ->from(route('accounting.ledger.suppliers.create'))
        ->post(route('accounting.ledger.suppliers.store'), $payload)
        ->assertSessionHas('error');

    expect(\App\Models\SupplierInvoice::count())->toBe(1);
});

// ── Analytique (classe 9) ───────────────────────────────────────────────────

test('les écrans analytiques s’ouvrent', function () {
    ecritureType();

    $this->actingAs(comptable())->get(route('accounting.ledger.analytic'))->assertOk();
    $this->actingAs(comptable())->get(route('accounting.ledger.analytic.margins'))->assertOk();
});

test('le reflet analytique se produit depuis l’écran', function () {
    ecritureType(Carbon::parse('2026-06-15'));

    // Une charge, sinon il n'y a rien à refléter.
    app(LedgerService::class)->post(
        Journal::PURCHASES,
        Carbon::parse('2026-06-15'),
        'Achat',
        [
            ['account' => '601000', 'debit' => 400_000, 'center' => 'restaurant'],
            ['account' => Account::SUPPLIERS, 'credit' => 400_000],
        ]
    );

    $this->actingAs(comptable())
        ->from(route('accounting.ledger.analytic'))
        ->post(route('accounting.ledger.analytic.mirror'), [
            'from' => '2026-06-01',
            'to'   => '2026-06-30',
        ])
        ->assertSessionHas('success');

    $reflet = \App\Models\JournalEntry::where('schema', 'like', 'analytic_mirror%')->with('lines')->first();

    expect($reflet)->not->toBeNull();
    expect($reflet->isBalanced())->toBeTrue();
});

test('refléter deux fois la même période est refusé', function () {
    app(LedgerService::class)->post(
        Journal::PURCHASES,
        Carbon::parse('2026-06-15'),
        'Achat',
        [
            ['account' => '601000', 'debit' => 400_000],
            ['account' => Account::SUPPLIERS, 'credit' => 400_000],
        ]
    );

    $payload = ['from' => '2026-06-01', 'to' => '2026-06-30'];

    $this->actingAs(comptable())->post(route('accounting.ledger.analytic.mirror'), $payload);

    $this->actingAs(comptable())
        ->from(route('accounting.ledger.analytic'))
        ->post(route('accounting.ledger.analytic.mirror'), $payload)
        ->assertSessionHas('error');
});

// ── Navigation ──────────────────────────────────────────────────────────────

test('la comptabilité de caisse renvoie vers la comptabilité générale', function () {
    $this->actingAs(comptable())
        ->get(route('accounting.index'))
        ->assertOk()
        ->assertSee(route('accounting.ledger.index'));
});
