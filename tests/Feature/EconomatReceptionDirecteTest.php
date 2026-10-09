<?php

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseOrderUpdated;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * Une marchandise doit pouvoir entrer au magasin même quand le circuit
 * d'achat n'a pas été suivi jusqu'au bout : un fournisseur sans email reçoit
 * son bon de la main à la main, et ce qui arrive sans bon de commande est
 * reçu directement, sous un bon de régularisation.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat', 'accounting']);
});

function personnelEconomat(string $role, string $nom): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Un bon en brouillon chez un fournisseur joint par téléphone. */
function bonSansEmail(): PurchaseOrder
{
    $fournisseur = Supplier::create(['name' => 'Marché Mokolo', 'phone' => '+237 699 00 00 00', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz parfumé', 'unit' => 'kg', 'current_stock' => 0]);
    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id]);
    PurchaseOrderLine::create(['purchase_order_id' => $bon->id, 'stock_item_id' => $riz->id, 'quantity_ordered' => 25, 'unit_price' => 80000]);
    $bon->recalculateTotal();

    return $bon->fresh();
}

test("un bon transmis sans email se réceptionne comme un bon envoyé", function () {
    $bon = bonSansEmail();
    $econome = personnelEconomat('econome', 'Économe');
    $this->actingAs($econome);

    $this->get(route('economat.orders.show', $bon))->assertOk()
        ->assertSee('Marquer comme transmis')
        ->assertDontSee('Envoyer au fournisseur');

    // L'email reste impossible : le fournisseur n'en a pas.
    $this->post(route('economat.orders.send', $bon))->assertSessionHas('error');

    $this->post(route('economat.orders.transmit', $bon), ['moyen' => 'inconnu'])->assertSessionHasErrors('moyen');
    $this->post(route('economat.orders.transmit', $bon), ['moyen' => 'telephone'])->assertSessionHas('success');

    $bon->refresh();
    expect($bon->status)->toBe(PurchaseOrder::STATUS_SENT)
        ->and($bon->transmission)->toBe('telephone')
        ->and($bon->sent_to_email)->toBeNull()
        ->and($bon->canBeReceived())->toBeTrue();

    $this->get(route('economat.orders.show', $bon))->assertSee('dicté par téléphone')->assertSee('Réceptionner la livraison');
    $this->get(route('economat.receipts.create', $bon))->assertOk();

    // Une seconde fois : le bon n'est plus en brouillon.
    $this->post(route('economat.orders.transmit', $bon), ['moyen' => 'main_propre'])->assertSessionHas('error');
    expect($bon->fresh()->transmission)->toBe('telephone');

    // Le magasinier pointe les livraisons, il ne transmet pas les bons.
    $autre = bonSansEmail();
    $this->actingAs(personnelEconomat('storekeeper', 'Magasinier'))
        ->post(route('economat.orders.transmit', $autre), ['moyen' => 'telephone'])
        ->assertSessionHas('access_denied_popup', true);
    expect($autre->fresh()->status)->toBe(PurchaseOrder::STATUS_DRAFT);
});

test("dans une application vide, la réception directe crée fournisseur, articles et bon de régularisation", function () {
    Notification::fake();
    $comptable = personnelEconomat('accountant', 'Comptable');
    $linge = StockCategory::create(['name' => 'Linge']);
    $econome = personnelEconomat('econome', 'Économe');
    $this->actingAs($econome);

    $this->get(route('economat.receipts.direct.create'))->assertOk()
        ->assertSee('Réception directe, sans bon de commande')
        ->assertSee('Nouveau fournisseur');

    $reponse = $this->post(route('economat.receipts.direct.store'), [
        'nouveau_fournisseur'  => ['name' => 'Quincaillerie du Centre', 'phone' => '+237 677 11 22 33'],
        'motif'                => 'achat_comptant',
        'delivery_note_number' => 'TICKET-552',
        'notes'                => 'Payé en espèces',
        'lines'                => [
            ['nouvel_article' => ['name' => 'Drap blanc 2 places', 'unit' => 'pièce', 'stock_category_id' => $linge->id],
                'quantity_delivered' => 20, 'quantity_rejected' => 2, 'rejection_reason' => 'damaged', 'unit_price' => 8000],
            ['nouvel_article' => ['name' => 'Ampoule LED 9W', 'unit' => 'pièce'], 'quantity_delivered' => 10, 'unit_price' => 1500],
        ],
    ]);

    $recu = GoodsReceipt::sole();
    $reponse->assertRedirect(route('economat.receipts.show', $recu));

    $fournisseur = Supplier::where('name', 'Quincaillerie du Centre')->sole();
    $drap = StockItem::where('name', 'Drap blanc 2 places')->sole();
    $ampoule = StockItem::where('name', 'Ampoule LED 9W')->sole();
    $bon = $recu->purchaseOrder;

    // Ce qui est gardé : 18 draps à 8 000 et 10 ampoules à 1 500.
    expect($fournisseur->phone)->toBe('+237 677 11 22 33')
        ->and($drap->stock_category_id)->toBe($linge->id)
        ->and($drap->supplier_id)->toBe($fournisseur->id)
        ->and((float) $drap->current_stock)->toBe(18.0)
        ->and((int) $drap->average_cost)->toBe(800000)
        ->and((float) $ampoule->current_stock)->toBe(10.0)
        ->and($recu->total_amount)->toBe(18 * 800000 + 10 * 150000)
        ->and($recu->delivery_note_number)->toBe('TICKET-552')
        ->and((float) $recu->lines->firstWhere('stock_item_id', $drap->id)->quantity_rejected)->toBe(2.0)
        ->and($bon->isRegularisation())->toBeTrue()
        ->and($bon->status)->toBe(PurchaseOrder::STATUS_RECEIVED)
        ->and($bon->total_amount)->toBe($recu->total_amount)
        ->and($bon->notes)->toContain('Achat au comptant')
        // La facture du fournisseur se rapproche du bon de régularisation.
        ->and($bon->uninvoicedReceivedAmount())->toBe($recu->total_amount)
        ->and(StockMovement::where('source_type', StockMovement::SOURCE_GOODS_RECEIPT)->where('source_id', $recu->id)->count())->toBe(2)
        ->and(AuditLog::where('event_type', 'reception_directe')->exists())->toBeTrue();

    Notification::assertSentTo($comptable, PurchaseOrderUpdated::class);

    $this->get(route('economat.receipts.show', $recu))->assertOk()->assertSee('Réception directe, sans commande préalable');
    $this->get(route('economat.orders.print', $bon))->assertOk()->assertSee('Bon de Commande de Régularisation');
    $this->get(route('economat.receipts.index'))->assertOk()->assertSee('Réception directe');
});

test("un article déjà au magasin reçoit la marchandise au coût moyen", function () {
    $fournisseur = Supplier::create(['name' => 'Brasserie', 'is_active' => true]);
    $biere = StockItem::create(['name' => 'Casier de bière', 'unit' => 'casier', 'current_stock' => 0]);
    app(\App\Services\StockService::class)->recordOpening($biere, 10, 800000);

    $this->actingAs(personnelEconomat('econome', 'Économe'))
        ->post(route('economat.receipts.direct.store'), [
            'supplier_id' => $fournisseur->id,
            'motif'       => 'livraison_imprevue',
            'lines'       => [['stock_item_id' => $biere->id, 'quantity_delivered' => 10, 'unit_price' => 10000]],
        ])->assertSessionHasNoErrors();

    $biere->refresh();
    expect((float) $biere->current_stock)->toBe(20.0)
        ->and((int) $biere->average_cost)->toBe(900000)
        ->and($biere->supplier_id)->toBe($fournisseur->id)
        ->and(Supplier::count())->toBe(1);
});

test("une réception directe incohérente n'enregistre rien", function () {
    Supplier::create(['name' => 'Quincaillerie du Centre', 'is_active' => true]);
    $this->actingAs(personnelEconomat('econome', 'Économe'));

    // Tout refusé : rien n'est gardé, rien ne s'enregistre.
    $this->post(route('economat.receipts.direct.store'), [
        'nouveau_fournisseur' => ['name' => 'Nouveau grossiste'],
        'motif'               => 'urgence',
        'lines'               => [['nouvel_article' => ['name' => 'Savon', 'unit' => 'litre'], 'quantity_delivered' => 5, 'quantity_rejected' => 5, 'unit_price' => 500]],
    ])->assertSessionHas('error');

    // Un fournisseur qui existe déjà se choisit dans la liste.
    $this->post(route('economat.receipts.direct.store'), [
        'nouveau_fournisseur' => ['name' => 'quincaillerie du centre'],
        'motif'               => 'urgence',
        'lines'               => [['nouvel_article' => ['name' => 'Savon', 'unit' => 'litre'], 'quantity_delivered' => 5, 'unit_price' => 500]],
    ])->assertSessionHas('error');

    // Sans prix, le stock serait valorisé à zéro.
    $this->post(route('economat.receipts.direct.store'), [
        'nouveau_fournisseur' => ['name' => 'Nouveau grossiste'],
        'motif'               => 'urgence',
        'lines'               => [['nouvel_article' => ['name' => 'Savon', 'unit' => 'litre'], 'quantity_delivered' => 5, 'unit_price' => 0]],
    ])->assertSessionHasErrors('lines.0.unit_price');

    expect(GoodsReceipt::count())->toBe(0)
        ->and(PurchaseOrder::count())->toBe(0)
        ->and(StockItem::count())->toBe(0)
        ->and(Supplier::count())->toBe(1);

    // Recevoir sans commande engage une dépense : le magasinier ne le fait pas.
    $this->actingAs(personnelEconomat('storekeeper', 'Magasinier'))
        ->get(route('economat.receipts.direct.create'))
        ->assertSessionHas('access_denied_popup', true);
});

test("annuler une réception directe sort la marchandise et annule son bon de régularisation", function () {
    $econome = personnelEconomat('econome', 'Économe');
    $this->actingAs($econome)->post(route('economat.receipts.direct.store'), [
        'nouveau_fournisseur' => ['name' => 'Marché central'],
        'motif'               => 'achat_comptant',
        'lines'               => [['nouvel_article' => ['name' => 'Tomates', 'unit' => 'kg'], 'quantity_delivered' => 12, 'unit_price' => 600]],
    ]);

    $recu = GoodsReceipt::sole();
    $this->post(route('economat.receipts.cancel', $recu))->assertSessionHas('success');

    expect((float) StockItem::where('name', 'Tomates')->sole()->current_stock)->toBe(0.0)
        ->and($recu->fresh()->status)->toBe(GoodsReceipt::STATUS_CANCELLED)
        ->and($recu->purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_CANCELLED);
});
