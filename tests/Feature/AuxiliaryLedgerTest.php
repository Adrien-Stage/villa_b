<?php

use App\Models\Account;
use App\Models\Customer;
use App\Models\Journal;
use App\Models\JournalEntryLine;
use App\Services\AuxiliaryLedgerService;
use App\Services\LedgerService;
use App\Services\ReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** Un client, support des écritures auxiliaires. */
function clientTiers(string $prenom = 'Société', string $nom = 'Ndzana'): Customer
{
    return Customer::create([
        'first_name' => $prenom,
        'last_name'  => $nom,
        'email'      => str()->slug("{$prenom} {$nom}") . '@test.cm',
        'phone'      => '+237690000000',
    ]);
}

/** Facture le client : 411000 au débit, produit au crédit. */
function facture(Customer $client, int $montant, ?Carbon $date = null): \App\Models\JournalEntry
{
    return app(LedgerService::class)->post(
        Journal::SALES,
        $date ?? Carbon::parse('2026-06-01'),
        'Facture séjour',
        [
            ['account' => Account::CLIENTS, 'debit' => $montant, 'auxiliary' => $client],
            ['account' => Account::REVENUE_ACCOMMODATION, 'credit' => $montant],
        ]
    );
}

/** Encaisse le client : 411000 au crédit, caisse au débit. */
function reglement(Customer $client, int $montant, ?Carbon $date = null): \App\Models\JournalEntry
{
    return app(LedgerService::class)->post(
        Journal::CASH,
        $date ?? Carbon::parse('2026-06-20'),
        'Règlement client',
        [
            ['account' => Account::CASH, 'debit' => $montant],
            ['account' => Account::CLIENTS, 'credit' => $montant, 'auxiliary' => $client],
        ]
    );
}

// ── Grand livre auxiliaire ──────────────────────────────────────────────────

test('le compte collectif se ventile par tiers', function () {
    $a = clientTiers('Client', 'A');
    $b = clientTiers('Client', 'B');

    facture($a, 500_000);
    facture($b, 300_000);
    reglement($a, 200_000);

    $tiers = app(AuxiliaryLedgerService::class)->balances(Account::CLIENTS);

    expect($tiers)->toHaveCount(2);
    expect($tiers->firstWhere('label', 'Client A')['balance'])->toBe(300_000);
    expect($tiers->firstWhere('label', 'Client B')['balance'])->toBe(300_000);
});

test('le grand livre d’un tiers tient un solde progressif', function () {
    $client = clientTiers();

    facture($client, 500_000, Carbon::parse('2026-06-01'));
    reglement($client, 200_000, Carbon::parse('2026-06-10'));

    $detail = app(AuxiliaryLedgerService::class)
        ->ledger(Account::CLIENTS, Customer::class, $client->id);

    expect($detail['lines'])->toHaveCount(2);
    expect($detail['lines'][0]['balance'])->toBe(500_000);
    expect($detail['lines'][1]['balance'])->toBe(300_000);
    expect($detail['balance'])->toBe(300_000);
});

// ── Lettrage ────────────────────────────────────────────────────────────────

test('un règlement exact se lettre automatiquement', function () {
    $client = clientTiers();

    facture($client, 500_000);
    reglement($client, 500_000);

    $lettres = app(ReconciliationService::class)->autoReconcile(Account::CLIENTS);

    expect($lettres)->toBe(1);
    expect(JournalEntryLine::whereNotNull('reconciliation_code')->count())->toBe(2);
});

test('un règlement partiel reste ouvert', function () {
    $client = clientTiers();

    facture($client, 500_000);
    reglement($client, 200_000);

    app(ReconciliationService::class)->autoReconcile(Account::CLIENTS);

    // Ni paire exacte ni solde nul : le reliquat doit rester visible.
    expect(JournalEntryLine::whereNotNull('reconciliation_code')->count())->toBe(0);

    $ouverts = app(AuxiliaryLedgerService::class)->balances(Account::CLIENTS);
    expect($ouverts->first()['balance'])->toBe(300_000);
});

test('plusieurs règlements qui soldent le compte se lettrent en bloc', function () {
    $client = clientTiers();

    facture($client, 500_000);
    reglement($client, 200_000);
    reglement($client, 300_000);

    $lettres = app(ReconciliationService::class)->autoReconcile(Account::CLIENTS);

    expect($lettres)->toBe(1);
    expect(JournalEntryLine::whereNull('reconciliation_code')
        ->where('account_code', Account::CLIENTS)->count())->toBe(0);
});

test('un lettrage déséquilibré est refusé', function () {
    $client = clientTiers();

    $f = facture($client, 500_000);
    $r = reglement($client, 200_000);

    $lignes = [
        $f->lines->firstWhere('account_code', Account::CLIENTS)->id,
        $r->lines->firstWhere('account_code', Account::CLIENTS)->id,
    ];

    expect(fn () => app(ReconciliationService::class)->reconcile($lignes))
        ->toThrow(RuntimeException::class, 'déséquilibré');
});

test('un lettrage ne mélange pas deux tiers', function () {
    $a = clientTiers('Client', 'A');
    $b = clientTiers('Client', 'B');

    $f = facture($a, 500_000);
    $r = reglement($b, 500_000);

    $lignes = [
        $f->lines->firstWhere('account_code', Account::CLIENTS)->id,
        $r->lines->firstWhere('account_code', Account::CLIENTS)->id,
    ];

    expect(fn () => app(ReconciliationService::class)->reconcile($lignes))
        ->toThrow(RuntimeException::class, 'même tiers');
});

test('le délettrage rouvre les postes', function () {
    $client = clientTiers();

    facture($client, 500_000);
    reglement($client, 500_000);

    $service = app(ReconciliationService::class);
    $service->autoReconcile(Account::CLIENTS);

    $lettre = JournalEntryLine::whereNotNull('reconciliation_code')->value('reconciliation_code');
    $rouvertes = $service->unreconcile($lettre);

    expect($rouvertes)->toBe(2);
    expect(JournalEntryLine::whereNotNull('reconciliation_code')->count())->toBe(0);

    // Les lignes sont redevenues lettrables : le lettrage auto les retrouve.
    expect($service->autoReconcile(Account::CLIENTS))->toBe(1);
});

// ── Balance âgée ────────────────────────────────────────────────────────────

test('la balance âgée ventile les impayés par ancienneté', function () {
    $recent = clientTiers('Client', 'Recent');
    $vieux  = clientTiers('Client', 'Ancien');

    $arrete = Carbon::parse('2026-06-30');

    facture($recent, 100_000, $arrete->copy()->subDays(10));
    facture($vieux, 400_000, $arrete->copy()->subDays(120));

    $balance = app(AuxiliaryLedgerService::class)->agedBalance(Account::CLIENTS, $arrete);

    expect($balance['totals']['current'])->toBe(100_000);
    expect($balance['totals']['d90'])->toBe(400_000);
    expect($balance['totals']['total'])->toBe(500_000);
});

test('une créance lettrée sort de la balance âgée', function () {
    $client = clientTiers();
    $arrete = Carbon::parse('2026-06-30');

    facture($client, 500_000, $arrete->copy()->subDays(100));
    reglement($client, 500_000, $arrete->copy()->subDays(5));

    app(ReconciliationService::class)->autoReconcile(Account::CLIENTS);

    $balance = app(AuxiliaryLedgerService::class)->agedBalance(Account::CLIENTS, $arrete);

    expect($balance['rows'])->toBeEmpty();
    expect($balance['totals']['total'])->toBe(0);
});
