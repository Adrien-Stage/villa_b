<?php

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Services\SupplierInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Rapprochement facture / bon de commande / réception : une facture rattachée
 * à un bon ne porte sans motif que ce qui a été reçu et pas encore facturé.
 * Centimes FCFA, comparés en TTC comme le prix du bon.
 */

beforeEach(function () {
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

// Le cache des modules est statique : on le vide pour ne pas fuir vers les tests suivants.
afterEach(fn () => (new ReflectionProperty(\App\Support\TenantModules::class, 'enabled'))->setValue(null, null));

/** Bon de 10 kg de riz à 10 000 F le kg, réceptionné à hauteur de $recu kg. */
function bonRecu(float $recu, ?Supplier $fournisseur = null): PurchaseOrder
{
    $fournisseur ??= Supplier::create(['name' => 'Grossiste', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz ' . uniqid(), 'unit' => 'kg']);

    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_SENT]);
    $ligne = PurchaseOrderLine::create([
        'purchase_order_id' => $bon->id, 'stock_item_id' => $riz->id,
        'quantity_ordered' => 10, 'unit_price' => 1_000_000,
    ]);

    if ($recu > 0) {
        app(GoodsReceiptService::class)->receive($bon, [
            'lines' => [$ligne->id => ['quantity_delivered' => $recu]],
        ], auth()->user());
    }

    return $bon->fresh();
}

function facturerBon(PurchaseOrder $bon, int $ttc, array $extra = []): SupplierInvoice
{
    return app(SupplierInvoiceService::class)->record(array_merge([
        'supplier'          => $bon->supplier_id,
        'purchase_order_id' => $bon->id,
        'number'            => 'FA-' . uniqid(),
        'invoice_date'      => Carbon::parse('2026-06-15'),
        'charge_account'    => '602000',
        'label'             => 'Riz',
        'amount_ttc'        => $ttc,
        'withholding_type'  => null,
    ], $extra));
}

test('une facture qui couvre la valeur reçue passe sans écart', function () {
    $bon = bonRecu(8);

    $facture = facturerBon($bon, 8_000_000);

    expect($facture->reception_variance)->toBe(0)
        ->and($facture->variance_reason)->toBeNull()
        ->and($bon->uninvoicedReceivedAmount())->toBe(0);
});

test('un bon sans réception ne peut pas être facturé', function () {
    $bon = bonRecu(0);

    expect(fn () => facturerBon($bon, 1_000_000))->toThrow(RuntimeException::class, 'aucune réception');
    expect(SupplierInvoice::count())->toBe(0);
});

test('une facture au-delà du reçu non facturé exige un motif', function () {
    $bon = bonRecu(8);
    facturerBon($bon, 8_000_000);

    expect(fn () => facturerBon($bon, 500_000))->toThrow(RuntimeException::class, 'motif');
    expect(SupplierInvoice::count())->toBe(1);
});

test('avec un motif, l’écart est accepté et figé sur la facture', function () {
    $bon = bonRecu(8);

    $facture = facturerBon($bon, 8_500_000, ['variance_reason' => 'Transport facturé à part']);

    expect($facture->reception_variance)->toBe(500_000)
        ->and($facture->variance_reason)->toBe('Transport facturé à part')
        ->and($facture->hasReceptionVariance())->toBeTrue();
});

test('une facture ne se rattache pas au bon d’un autre fournisseur', function () {
    $bon = bonRecu(8);
    $autre = Supplier::create(['name' => 'Concurrent', 'is_active' => true]);

    expect(fn () => facturerBon($bon, 1_000_000, ['supplier' => $autre->id]))
        ->toThrow(RuntimeException::class, 'autre fournisseur');
});

test('une facture sans bon de commande reste libre', function () {
    $facture = app(SupplierInvoiceService::class)->record([
        'supplier'         => Supplier::create(['name' => 'Eneo', 'is_active' => true]),
        'number'           => 'ENEO-06',
        'invoice_date'     => Carbon::parse('2026-06-15'),
        'charge_account'   => '605000',
        'label'            => 'Électricité',
        'amount_ttc'       => 3_000_000,
        'withholding_type' => null,
    ]);

    expect($facture->reception_variance)->toBe(0);
});

test('une réception déjà facturée ne peut plus être annulée', function () {
    $bon = bonRecu(8);
    facturerBon($bon, 8_000_000);
    $reception = GoodsReceipt::where('purchase_order_id', $bon->id)->sole();

    expect(fn () => app(GoodsReceiptService::class)->cancel($reception, auth()->user()))
        ->toThrow(RuntimeException::class, 'déjà facturé');
    expect($reception->fresh()->status)->toBe(GoodsReceipt::STATUS_RECEIVED);
});

test('une réception qui dépasse ce qui est facturé reste annulable', function () {
    $bon = bonRecu(4);
    $ligne = $bon->lines()->first();
    app(GoodsReceiptService::class)->receive($bon, [
        'lines' => [$ligne->id => ['quantity_delivered' => 4]],
    ], auth()->user());
    facturerBon($bon, 4_000_000);

    // Deux réceptions de 4 kg, une seule facturée : l'autre peut s'annuler.
    $seconde = GoodsReceipt::where('purchase_order_id', $bon->id)->latest('id')->first();
    app(GoodsReceiptService::class)->cancel($seconde, auth()->user());

    expect($seconde->fresh()->status)->toBe(GoodsReceipt::STATUS_CANCELLED);
});

test('l’écran de saisie signale le motif manquant sans perdre la saisie', function () {
    $bon = bonRecu(8);

    // Le grand livre est un module activable : sans lui, ses routes répondent 403.
    $modules = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $modules->setValue(null, ['ledger']);

    $comptable = User::factory()->create(['role' => 'accountant']);
    // Les droits viennent des affectations : on rattache le rôle par le pivot.
    $comptable->roles()->attach(\App\Models\Role::where('slug', 'accountant')->value('id'), ['level' => 'write']);

    $this->actingAs($comptable)
        ->from(route('accounting.ledger.suppliers.create'))
        ->post(route('accounting.ledger.suppliers.store'), [
            'supplier_id'       => $bon->supplier_id,
            'purchase_order_id' => $bon->id,
            'number'            => 'FA-HTTP',
            'invoice_date'      => '2026-06-15',
            'charge_account'    => '602000',
            'label'             => 'Riz',
            'amount_ttc'        => 90_000,
        ])
        ->assertSessionHas('error')
        ->assertSessionHasInput('number', 'FA-HTTP');

    expect(SupplierInvoice::count())->toBe(0);
});
