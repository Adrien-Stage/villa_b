<?php

use App\Models\Account;
use App\Models\CashRegisterSession;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\NightAudit;
use App\Services\LedgerService;
use App\Services\NightAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function cloture(): NightAuditService
{
    return app(NightAuditService::class);
}

/** Une journée d'exploitation : un produit et son encaissement. */
function journeeType(string $date = '2026-06-15'): void
{
    app(LedgerService::class)->post(
        Journal::SALES,
        Carbon::parse($date),
        'Nuitée',
        [
            ['account' => Account::CLIENTS, 'debit' => 1_192_500],
            ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1_000_000],
            ['account' => Account::VAT_COLLECTED, 'credit' => 192_500],
        ]
    );

    app(LedgerService::class)->post(
        Journal::CASH,
        Carbon::parse($date),
        'Encaissement',
        [
            ['account' => Account::CASH, 'debit' => 1_192_500],
            ['account' => Account::CLIENTS, 'credit' => 1_192_500],
        ]
    );
}

// ── Constat ─────────────────────────────────────────────────────────────────

test('la clôture fige le chiffre d’affaires et la trésorerie', function () {
    journeeType();

    $audit = cloture()->run(Carbon::parse('2026-06-15'));

    expect($audit->revenue_accommodation)->toBe(1_000_000);
    expect($audit->revenue_total)->toBe(1_000_000);
    expect($audit->cash_collected)->toBe(1_192_500);
    expect($audit->closed_at)->not->toBeNull();
});

test('le constat reste figé même si le grand livre change ensuite', function () {
    journeeType();
    $audit = cloture()->run(Carbon::parse('2026-06-15'));
    $constat = $audit->revenue_total;

    // Une correction datée d'aujourd'hui modifie le grand livre…
    app(LedgerService::class)->post(
        Journal::SALES,
        now(),
        'Régularisation',
        [
            ['account' => Account::CLIENTS, 'debit' => 500_000],
            ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 500_000],
        ]
    );

    // …mais le constat du 15 juin, lui, ne bouge pas.
    expect($audit->fresh()->revenue_total)->toBe($constat);
});

// ── Le gel ──────────────────────────────────────────────────────────────────

test('une journée clôturée n’accepte plus d’écriture', function () {
    journeeType();
    cloture()->run(Carbon::parse('2026-06-15'));

    app(LedgerService::class)->post(
        Journal::MISC,
        Carbon::parse('2026-06-15'),
        'Opération retardataire',
        [
            ['account' => Account::CASH, 'debit' => 100_000],
            ['account' => Account::REVENUE_SHOP, 'credit' => 100_000],
        ]
    );
})->throws(RuntimeException::class, 'clôturée');

test('une correction reste possible à une date ouverte', function () {
    journeeType();
    cloture()->run(Carbon::parse('2026-06-15'));

    $correction = app(LedgerService::class)->post(
        Journal::MISC,
        Carbon::parse('2026-06-16'),
        'Régularisation du 15',
        [
            ['account' => Account::CASH, 'debit' => 100_000],
            ['account' => Account::REVENUE_SHOP, 'credit' => 100_000],
        ]
    );

    expect($correction->entry_date->toDateString())->toBe('2026-06-16');
});

test('une journée ne se clôture pas deux fois', function () {
    journeeType();
    cloture()->run(Carbon::parse('2026-06-15'));
    cloture()->run(Carbon::parse('2026-06-15'));
})->throws(RuntimeException::class, 'déjà clôturée');

test('on ne clôture pas une journée à venir', function () {
    cloture()->run(now()->addDay());
})->throws(RuntimeException::class, 'à venir');

// ── Caisses ─────────────────────────────────────────────────────────────────

test('l’écart de caisse du jour est constaté', function () {
    journeeType();

    CashRegisterSession::create([
        'user_id'                    => \App\Models\User::create([
            'name' => 'Caissier', 'email' => 'c@test.cm', 'password' => bcrypt('x'),
            'role' => 'cashier', 'is_active' => true,
        ])->id,
        'module'                     => 'reception',
        'opened_at'                  => Carbon::parse('2026-06-15 08:00'),
        'closed_at'                  => Carbon::parse('2026-06-15 20:00'),
        'opening_amount'             => 100_000,
        'theoretical_closing_amount' => 1_292_500,
        'actual_closing_amount'      => 1_290_000,
        'discrepancy_amount'         => -2_500,
    ]);

    $audit = cloture()->run(Carbon::parse('2026-06-15'));

    expect($audit->registers_closed)->toBe(1);
    expect($audit->cash_discrepancy)->toBe(-2_500);
    expect($audit->hasDiscrepancy())->toBeTrue();
});

test('une caisse restée ouverte est signalée', function () {
    journeeType();

    CashRegisterSession::create([
        'user_id'        => \App\Models\User::create([
            'name' => 'Caissier', 'email' => 'c2@test.cm', 'password' => bcrypt('x'),
            'role' => 'cashier', 'is_active' => true,
        ])->id,
        'module'         => 'reception',
        'opened_at'      => Carbon::parse('2026-06-15 08:00'),
        'opening_amount' => 100_000,
    ]);

    $audit = cloture()->run(Carbon::parse('2026-06-15'));

    expect($audit->registers_left_open)->toBe(1);
    expect($audit->hasOpenRegisters())->toBeTrue();
});

// ── Comptabilisation intégrée ───────────────────────────────────────────────

test('la clôture comptabilise les opérations non encore portées', function () {
    Expense::create([
        'occurred_at'    => Carbon::parse('2026-06-15'),
        'category'       => Expense::CATEGORY_WATER,
        'label'          => 'Camwater',
        'amount'         => 200_000,
        'payment_method' => 'cash',
    ]);

    $audit = cloture()->run(Carbon::parse('2026-06-15'));

    // La dépense n'était pas comptabilisée : la clôture s'en est chargée.
    expect($audit->entries_posted)->toBe(1);
});

// ── Journées oubliées ───────────────────────────────────────────────────────

test('les journées non clôturées sont listées', function () {
    $hier = now()->copy()->subDay()->startOfDay();
    cloture()->run($hier);

    $enAttente = cloture()->pendingDays(lookbackDays: 5);
    $dates = collect($enAttente)->map(fn ($d) => $d->toDateString());

    // Hier est clôturée, les jours d'avant ne le sont pas.
    expect($dates)->not->toContain($hier->toDateString());
    expect($dates)->toContain(now()->copy()->subDays(3)->toDateString());

    // Aujourd'hui n'est jamais réclamée : la journée n'est pas terminée.
    expect($dates)->not->toContain(now()->toDateString());
});

// ── Verrouillage des périodes échues ────────────────────────────────────────

test('la commande signale les périodes en retard sans les verrouiller', function () {
    $exercice = FiscalYear::openYear(now()->year - 1);
    $janvier = $exercice->periods()->orderBy('starts_on')->first();

    $this->artisan('ledger:lock-periods')
        ->expectsOutputToContain('au-delà du délai')
        ->assertSuccessful();

    // Sans --force, rien n'est verrouillé : le geste reste humain.
    expect($janvier->fresh()->isLocked())->toBeFalse();
});

test('la commande verrouille avec --force', function () {
    $exercice = FiscalYear::openYear(now()->year - 1);
    $janvier = $exercice->periods()->orderBy('starts_on')->first();

    $this->artisan('ledger:lock-periods', ['--force' => true])->assertSuccessful();

    expect($janvier->fresh()->isLocked())->toBeTrue();
});

// ── Commande de clôture ─────────────────────────────────────────────────────

test('la commande clôture une journée', function () {
    journeeType();

    $this->artisan('night-audit:run', ['--date' => '2026-06-15'])->assertSuccessful();

    expect(NightAudit::isClosed(Carbon::parse('2026-06-15')))->toBeTrue();
});

test('la commande échoue proprement sur une journée déjà close', function () {
    journeeType();
    cloture()->run(Carbon::parse('2026-06-15'));

    $this->artisan('night-audit:run', ['--date' => '2026-06-15'])->assertFailed();
});
