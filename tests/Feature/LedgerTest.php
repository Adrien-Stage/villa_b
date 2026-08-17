<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function ledger(): LedgerService
{
    return app(LedgerService::class);
}

function jour(string $date = '2026-06-15'): Carbon
{
    return Carbon::parse($date);
}

/** Écriture type : une nuitée à 11 925 F TTC, TVA 19,25 % extraite. */
function lignesNuitee(): array
{
    return [
        ['account' => Account::CLIENTS,       'debit'  => 1_192_500],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1_000_000],
        ['account' => Account::VAT_COLLECTED, 'credit' => 192_500],
    ];
}

// ── L'équilibre ─────────────────────────────────────────────────────────────

test('une écriture équilibrée est enregistrée', function () {
    $entry = ledger()->post(Journal::SALES, jour(), 'Nuitée', lignesNuitee());

    expect($entry->lines)->toHaveCount(3);
    expect($entry->totalDebit())->toBe(1_192_500);
    expect($entry->totalCredit())->toBe(1_192_500);
    expect($entry->isBalanced())->toBeTrue();
    expect($entry->isPosted())->toBeTrue();
});

test('une écriture déséquilibrée est refusée', function () {
    ledger()->post(Journal::SALES, jour(), 'Bancale', [
        ['account' => Account::CLIENTS, 'debit'  => 1_192_500],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1_000_000],
    ]);
})->throws(RuntimeException::class, 'Écriture déséquilibrée');

test('une ligne ne peut pas porter à la fois un débit et un crédit', function () {
    ledger()->post(Journal::SALES, jour(), 'Ambiguë', [
        ['account' => Account::CLIENTS, 'debit' => 1000, 'credit' => 1000],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1000],
    ]);
})->throws(RuntimeException::class, 'jamais aux deux');

test('un montant négatif est refusé', function () {
    ledger()->post(Journal::SALES, jour(), 'Négative', [
        ['account' => Account::CLIENTS, 'debit' => -1000],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => -1000],
    ]);
})->throws(RuntimeException::class, 'ne peut pas être négatif');

test('une écriture vide est refusée', function () {
    ledger()->post(Journal::SALES, jour(), 'Vide', []);
})->throws(RuntimeException::class, 'ne peut pas être vide');

// ── L'intégrité du plan de comptes ──────────────────────────────────────────

test('un compte inconnu est refusé', function () {
    ledger()->post(Journal::SALES, jour(), 'Compte fantôme', [
        ['account' => '999999', 'debit' => 1000],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1000],
    ]);
})->throws(RuntimeException::class, 'inconnu au plan de comptes');

test('un compte de regroupement ne reçoit pas d’écriture directe', function () {
    // « 41 » totalise les comptes clients : il n'est pas imputable.
    ledger()->post(Journal::SALES, jour(), 'Sur regroupement', [
        ['account' => '41', 'debit' => 1000],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1000],
    ]);
})->throws(RuntimeException::class, 'compte de regroupement');

// ── L'idempotence ───────────────────────────────────────────────────────────

test('rejouer la même opération ne crée pas de seconde écriture', function () {
    $customer = Customer::create(['first_name' => 'Awa', 'last_name' => 'Njoya']);

    $premier = ledger()->post(Journal::SALES, jour(), 'Nuitée', lignesNuitee(), $customer, 'checkout');
    $second  = ledger()->post(Journal::SALES, jour(), 'Nuitée', lignesNuitee(), $customer, 'checkout');

    expect($second->id)->toBe($premier->id);
    expect(JournalEntry::count())->toBe(1);
});

test('deux schémas distincts sur la même opération cohabitent', function () {
    $customer = Customer::create(['first_name' => 'Awa', 'last_name' => 'Njoya']);

    ledger()->post(Journal::SALES, jour(), 'Facture', lignesNuitee(), $customer, 'checkout');
    ledger()->post(Journal::CASH, jour(), 'Encaissement', [
        ['account' => Account::CASH,    'debit'  => 1_192_500],
        ['account' => Account::CLIENTS, 'credit' => 1_192_500],
    ], $customer, 'payment');

    expect(JournalEntry::count())->toBe(2);
});

// ── L'auxiliaire ────────────────────────────────────────────────────────────

test('la granularité par tiers vit sur la ligne, pas dans le plan de comptes', function () {
    $customer = Customer::create(['first_name' => 'Awa', 'last_name' => 'Njoya']);

    $entry = ledger()->post(Journal::SALES, jour(), 'Nuitée', [
        ['account' => Account::CLIENTS, 'debit' => 1_192_500, 'auxiliary' => $customer],
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1_000_000, 'center' => 'hebergement'],
        ['account' => Account::VAT_COLLECTED, 'credit' => 192_500],
    ]);

    $ligneClient = $entry->lines->firstWhere('account_code', Account::CLIENTS);

    expect($ligneClient->auxiliary_type)->toBe(Customer::class);
    expect($ligneClient->auxiliary_id)->toBe($customer->id);
    expect($ligneClient->auxiliary->last_name)->toBe('Njoya');

    // Aucun compte 411xxx individuel n'a été créé.
    expect(Account::where('code', 'like', '411%')->count())->toBe(1);
});

// ── L'irréversibilité (Article 22) ──────────────────────────────────────────

test('une période verrouillée n’accepte plus d’écriture', function () {
    $entry = ledger()->post(Journal::SALES, jour(), 'Nuitée', lignesNuitee());
    ledger()->lockPeriod($entry->period);

    ledger()->post(Journal::SALES, jour(), 'Trop tard', lignesNuitee());
})->throws(RuntimeException::class, 'verrouillée');

test('la contre-passation inverse les sens et chaîne les deux écritures', function () {
    $entry = ledger()->post(Journal::SALES, jour(), 'Nuitée', lignesNuitee());

    $extourne = ledger()->reverse($entry, jour('2026-07-02'), 'erreur de saisie');

    expect($extourne->lines)->toHaveCount(3);
    expect($extourne->totalDebit())->toBe(1_192_500);

    // Le client était au débit, il passe au crédit.
    $ligneClient = $extourne->lines->firstWhere('account_code', Account::CLIENTS);
    expect($ligneClient->credit)->toBe(1_192_500);
    expect($ligneClient->debit)->toBe(0);

    expect($extourne->reverses_id)->toBe($entry->id);
    expect($entry->fresh()->reversed_by_id)->toBe($extourne->id);

    // Les deux écritures s'annulent.
    expect($entry->totalDebit() - $extourne->totalDebit())->toBe(0);
});

test('une écriture déjà contre-passée ne l’est pas deux fois', function () {
    $entry = ledger()->post(Journal::SALES, jour(), 'Nuitée', lignesNuitee());
    ledger()->reverse($entry);

    ledger()->reverse($entry->fresh());
})->throws(RuntimeException::class, 'déjà été contre-passée');

test('une contre-passation ne s’extourne pas à son tour', function () {
    $entry = ledger()->post(Journal::SALES, jour(), 'Nuitée', lignesNuitee());
    $extourne = ledger()->reverse($entry);

    ledger()->reverse($extourne);
})->throws(RuntimeException::class, "ne s'extourne pas");

test('une période verrouillée peut être corrigée depuis une période ouverte', function () {
    $entry = ledger()->post(Journal::SALES, jour('2026-06-15'), 'Nuitée', lignesNuitee());
    ledger()->lockPeriod($entry->period);

    // L'extourne est datée de juillet, période encore ouverte.
    $extourne = ledger()->reverse($entry, jour('2026-07-02'));

    expect($extourne->entry_date->format('Y-m'))->toBe('2026-07');
    expect($extourne->period->isLocked())->toBeFalse();
});

// ── Exercices et périodes ───────────────────────────────────────────────────

test('l’exercice et ses douze périodes s’ouvrent à la première écriture', function () {
    expect(FiscalYear::count())->toBe(0);

    ledger()->post(Journal::SALES, jour('2026-03-10'), 'Nuitée', lignesNuitee());

    expect(FiscalYear::count())->toBe(1);
    expect(FiscalPeriod::count())->toBe(12);
    expect(FiscalYear::first()->starts_on->toDateString())->toBe('2026-01-01');
});

test('le délai de verrouillage court un mois après la fin de période', function () {
    $year = FiscalYear::openYear(2026);
    $janvier = $year->periods()->orderBy('starts_on')->first();

    expect($janvier->lockDeadline()->toDateString())->toBe('2026-02-28');
});

// ── Reprise des à-nouveaux ──────────────────────────────────────────────────

test('les à-nouveaux reportent les soldes de bilan', function () {
    $year = FiscalYear::openYear(2026);

    $entry = ledger()->postOpeningBalance($year, [
        ['account' => Account::BANK,    'debit'  => 5_000_000],
        ['account' => Account::CLIENTS, 'debit'  => 2_000_000],
        ['account' => '101000',         'credit' => 7_000_000],
    ]);

    expect($entry->isBalanced())->toBeTrue();
    expect($entry->entry_date->toDateString())->toBe('2026-01-01');
    expect($year->fresh()->hasOpeningBalance())->toBeTrue();
});

test('les à-nouveaux ne se reprennent qu’une fois', function () {
    $year = FiscalYear::openYear(2026);

    $lignes = [
        ['account' => Account::BANK, 'debit'  => 1_000_000],
        ['account' => '101000',      'credit' => 1_000_000],
    ];

    ledger()->postOpeningBalance($year, $lignes);
    ledger()->postOpeningBalance($year->fresh(), $lignes);
})->throws(RuntimeException::class, 'déjà été repris');

test('un compte de gestion ne se reprend pas en à-nouveaux', function () {
    $year = FiscalYear::openYear(2026);

    // 706000 est un produit : il repart à zéro chaque exercice.
    ledger()->postOpeningBalance($year, [
        ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => 1_000_000],
        ['account' => Account::BANK,                  'debit'  => 1_000_000],
    ]);
})->throws(RuntimeException::class, 'compte de gestion');

// ── Plan de comptes ─────────────────────────────────────────────────────────

test('la migration de données a livré le plan de comptes et les journaux', function () {
    expect(Account::count())->toBeGreaterThan(50);
    expect(Account::where('code', Account::CLIENTS)->first()->is_collective)->toBeTrue();
    expect(Account::where('code', '41')->first()->is_postable)->toBeFalse();
    expect(Journal::count())->toBe(5);
    expect(Journal::byCode(Journal::SALES)->label)->toBe('Journal des ventes');
});
