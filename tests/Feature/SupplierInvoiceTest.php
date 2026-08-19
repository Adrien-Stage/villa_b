<?php

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\TaxRate;
use App\Models\Tenant;
use App\Services\SupplierInvoiceService;
use App\Services\TaxationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function fournisseur(string $nom = 'Établissements Mbarga'): Supplier
{
    return Supplier::create([
        'name'      => $nom,
        'email'     => str()->slug($nom) . '@test.cm',
        'is_active' => true,
    ]);
}

/** Active la TVA au taux standard pour l'établissement. */
function activerTva(): void
{
    Tenant::query()->firstOrCreate(
        ['id' => 1],
        ['name' => 'Test', 'slug' => 'test']
    );

    Tenant::query()->update(['settings' => ['taxes' => ['vat_enabled' => true]]]);
}

function saisirFacture(array $overrides = []): SupplierInvoice
{
    return app(SupplierInvoiceService::class)->record(array_merge([
        'supplier'         => fournisseur(),
        'number'           => 'FA-001',
        'invoice_date'     => Carbon::parse('2026-06-15'),
        'charge_account'   => '601000',
        'label'            => 'Approvisionnement économat',
        'amount_ttc'       => 1_192_500,
        'withholding_type' => null,
    ], $overrides));
}

// ── Décomposition ───────────────────────────────────────────────────────────

test('la retenue s’assied sur le hors taxes, jamais sur le TTC', function () {
    activerTva();

    $decomposition = app(SupplierInvoiceService::class)
        ->decompose(1_192_500, SupplierInvoice::WITHHOLDING_SERVICES);

    // 1 192 500 TTC à 19,25 % => 1 000 000 HT.
    expect($decomposition['ht'])->toBe(1_000_000);
    expect($decomposition['vat'])->toBe(192_500);

    // 5 % de 1 000 000, pas de 1 192 500.
    expect($decomposition['withholding']['amount'])->toBe(50_000);
    expect($decomposition['net_payable'])->toBe(1_142_500);
});

test('sans retenue, le net à payer vaut le TTC', function () {
    $decomposition = app(SupplierInvoiceService::class)->decompose(500_000, null);

    expect($decomposition['withholding']['amount'])->toBe(0);
    expect($decomposition['net_payable'])->toBe(500_000);
});

test('les trois natures de retenue appliquent leurs taux', function () {
    $service = app(SupplierInvoiceService::class);

    expect($service->decompose(1_000_000, SupplierInvoice::WITHHOLDING_SERVICES)['withholding']['amount'])->toBe(50_000);
    expect($service->decompose(1_000_000, SupplierInvoice::WITHHOLDING_FEES)['withholding']['amount'])->toBe(100_000);
    expect($service->decompose(1_000_000, SupplierInvoice::WITHHOLDING_INTELLECTUAL)['withholding']['amount'])->toBe(150_000);
});

test('un taux surchargé dans les réglages remplace le défaut', function () {
    Tenant::query()->firstOrCreate(['id' => 1], ['name' => 'Test', 'slug' => 'test']);
    Tenant::query()->update(['settings' => ['taxes' => ['withholding_rates' => ['services' => 220]]]]);

    // Les taux ne sont pas confirmés pour le Cameroun : ils doivent rester
    // pilotables sans redéploiement.
    expect(app(TaxationService::class)->withholdingRate('services'))->toBe(220);
});

// ── Écriture produite ───────────────────────────────────────────────────────

test('la facture produit une écriture équilibrée avec la retenue au crédit', function () {
    activerTva();

    $facture = saisirFacture(['withholding_type' => SupplierInvoice::WITHHOLDING_SERVICES]);

    $ecriture = JournalEntry::where('source_type', SupplierInvoice::class)
        ->where('source_id', $facture->id)
        ->with('lines')
        ->first();

    expect($ecriture)->not->toBeNull();
    expect($ecriture->isBalanced())->toBeTrue();

    $parCompte = $ecriture->lines->keyBy('account_code');

    expect($parCompte['601000']->debit)->toBe(1_000_000);
    expect($parCompte[Account::VAT_DEDUCTIBLE]->debit)->toBe(192_500);
    expect($parCompte[Account::WITHHOLDING]->credit)->toBe(50_000);

    // Le fournisseur est crédité du NET : la dette naît déjà nette de ce qui
    // sera reversé à l'État.
    expect($parCompte[Account::SUPPLIERS]->credit)->toBe(1_142_500);
});

test('le fournisseur est porté en auxiliaire du compte collectif', function () {
    $fou = fournisseur();
    $facture = saisirFacture(['supplier' => $fou]);

    $ligne = JournalEntry::where('source_id', $facture->id)
        ->where('source_type', SupplierInvoice::class)
        ->first()
        ->lines
        ->firstWhere('account_code', Account::SUPPLIERS);

    expect($ligne->auxiliary_type)->toBe(Supplier::class);
    expect((int) $ligne->auxiliary_id)->toBe($fou->id);
});

test('sans retenue, aucune ligne 442100 n’est écrite', function () {
    $facture = saisirFacture();

    $comptes = JournalEntry::where('source_id', $facture->id)
        ->where('source_type', SupplierInvoice::class)
        ->first()
        ->lines
        ->pluck('account_code');

    expect($comptes)->not->toContain(Account::WITHHOLDING);
});

test('la comptabilisation est idempotente', function () {
    $facture = saisirFacture();

    $rejeu = app(\App\Services\LedgerPostingService::class)->postSupplierInvoice($facture);

    expect($rejeu)->toBeNull();
    expect(JournalEntry::where('source_type', SupplierInvoice::class)->count())->toBe(1);
});

// ── État des retenues ───────────────────────────────────────────────────────

test('l’état des retenues ventile par nature', function () {
    activerTva();

    $a = fournisseur('Fournisseur A');
    $b = fournisseur('Fournisseur B');

    saisirFacture(['supplier' => $a, 'number' => 'FA-100', 'withholding_type' => SupplierInvoice::WITHHOLDING_SERVICES]);
    saisirFacture(['supplier' => $b, 'number' => 'FA-200', 'withholding_type' => SupplierInvoice::WITHHOLDING_FEES]);
    saisirFacture(['supplier' => $a, 'number' => 'FA-300']); // sans retenue

    $etat = app(SupplierInvoiceService::class)->withholdingStatement(
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );

    expect($etat['lines'])->toHaveCount(2);
    expect($etat['byType'])->toHaveKeys(['services', 'fees']);
    expect($etat['byType']['services']['amount'])->toBe(50_000);
    expect($etat['byType']['fees']['amount'])->toBe(100_000);
    expect($etat['total'])->toBe(150_000);
});

test('l’état ne retient que les factures de la période', function () {
    activerTva();

    saisirFacture(['number' => 'FA-JUIN', 'invoice_date' => Carbon::parse('2026-06-15'), 'withholding_type' => SupplierInvoice::WITHHOLDING_SERVICES]);
    saisirFacture(['number' => 'FA-JUIL', 'invoice_date' => Carbon::parse('2026-07-15'), 'withholding_type' => SupplierInvoice::WITHHOLDING_SERVICES]);

    $etat = app(SupplierInvoiceService::class)->withholdingStatement(
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-30'),
    );

    expect($etat['lines'])->toHaveCount(1);
    expect($etat['lines']->first()->number)->toBe('FA-JUIN');
});

test('un montant nul est refusé', function () {
    expect(fn () => saisirFacture(['amount_ttc' => 0]))
        ->toThrow(RuntimeException::class);
});
