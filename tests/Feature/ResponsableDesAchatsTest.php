<?php

use App\Editions\Catalogue;
use App\Mail\PurchaseOrderMail;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseRequestUpdated;
use App\Notifications\StockItemBelowThreshold;
use App\Services\PermissionResolver;
use App\Services\PurchaseRequestService;
use App\Support\DutySegregation;
use App\Support\Navigation;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * Le circuit d'achat avec un responsable des achats :
 *   l'économat (ou une alerte de stock) exprime le besoin, envoyé au
 *   responsable des achats ;
 *   le responsable des achats le vérifie et peut en modifier les quantités —
 *   le demandeur est averti de chaque changement ;
 *   il tire le bon et le transmet au fournisseur, ce qui ouvre un bon de
 *   réception en attente ; la demande part aux archives ;
 *   l'économat remplit ce bon à la livraison, partielle ou complète.
 * Le contrôle de gestion et le responsable financier voient tout le circuit.
 */

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function membreAchats(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Un refus du droit : l'écran renvoie en arrière avec le motif. */
function refuseAuxAchats(\Illuminate\Testing\TestResponse $reponse): void
{
    $reponse->assertRedirect()->assertSessionHas('access_denied_popup', true);
}

function articleAchats(string $nom, ?Supplier $fournisseur = null, float $stock = 2, float $seuil = 5): StockItem
{
    return StockItem::create([
        'name' => $nom, 'unit' => 'kg', 'current_stock' => $stock, 'min_stock' => $seuil,
        'average_cost' => 250000, 'last_purchase_price' => 250000, 'is_active' => true,
        'supplier_id' => $fournisseur?->id,
    ]);
}

test('le responsable des achats commande, ne réceptionne pas et ne décide pas de la dépense', function () {
    $droits = fn (string $role) => collect(PermissionCatalog::forRole($role));

    $acheteur = $droits('purchasing_manager');
    expect($acheteur)->toContain('achats.voir', 'economat.suppliers.creer', 'economat.suppliers.modifier',
        'economat.purchase_requests.approve', 'economat.purchase_requests.reject', 'economat.purchase_requests.modifier',
        'economat.purchase_requests.convert', 'economat.orders.send', 'economat.orders.transmit', 'economat.orders.cancel',
        'economat.control.suggestions.creer', 'economat.receipts.voir', 'editions.voir')
        ->not->toContain('economat.orders.receive', 'economat.receipts.creer', 'economat.orders.creer', 'economat.items.voir');

    // L'économat réceptionne, gère le stock et demande ; il ne commande plus.
    $econome = $droits('econome');
    expect($econome)->toContain('economat.orders.receive', 'economat.receipts.creer', 'economat.purchase_requests.creer',
        'economat.stock_counts.creer', 'economat.requisitions.approve', 'economat.suppliers.voir')
        ->not->toContain('economat.orders.send', 'economat.orders.transmit', 'economat.suppliers.creer', 'economat.purchase_requests.convert');

    // La vérification : le responsable des achats ; l'économe, qui demande, non.
    expect(PermissionCatalog::roles('economat.purchase_requests.approve'))->toContain('purchasing_manager')
        ->not->toContain('econome', 'storekeeper');

    // Le contrôle de gestion et le responsable financier suivent tout le circuit.
    foreach (['controller', 'finance_manager'] as $role) {
        expect($droits($role))->toContain('achats.voir', 'economat.purchase_requests.voir', 'economat.orders.voir', 'economat.receipts.voir');
    }

    // Le rôle est proposé à l'affectation.
    expect(Role::where('slug', 'purchasing_manager')->value('is_assignable'))->toBeTrue();
});

test("le circuit complet : vérification ajustée par les achats, bon transmis, bon de réception en attente, livraisons", function () {
    Notification::fake();

    $fournisseur = Supplier::create(['name' => 'Grossiste Nord', 'phone' => '690000000', 'is_active' => true]);
    $riz = articleAchats('Riz', $fournisseur);
    $huile = articleAchats('Huile', $fournisseur);

    $econome = membreAchats('econome');
    $acheteur = membreAchats('purchasing_manager');

    // 1. L'économat exprime le besoin, sans département à choisir : il part au responsable des achats.
    $this->actingAs($econome)->get(route('economat.purchase_requests.create'))->assertOk()
        ->assertDontSee('name="department"', false)->assertSee('Retirer cet article');
    $this->actingAs($econome)->post(route('economat.purchase_requests.store'), [
        'supplier_id' => $fournisseur->id,
        'priority' => 'normal',
        'lines' => [
            ['stock_item_id' => $riz->id, 'quantity_requested' => 10],
            ['stock_item_id' => $huile->id, 'quantity_requested' => 5],
        ],
    ])->assertRedirect()->assertSessionHas('success');
    $demande = PurchaseRequest::with('lines')->latest('id')->firstOrFail();
    expect($demande->department)->toBe('economat');
    Notification::assertSentTo($acheteur, PurchaseRequestUpdated::class, fn ($n) => $n->etape === PurchaseRequestUpdated::SOUMISE);

    // L'économe ne tire pas de bon d'une demande.
    refuseAuxAchats($this->actingAs($econome)->post(route('economat.purchase_requests.convert', $demande)));

    // 2. Le responsable des achats vérifie : 10 kg de riz ramenés à 8, l'huile écartée.
    [$ligneRiz, $ligneHuile] = [$demande->lines->firstWhere('stock_item_id', $riz->id), $demande->lines->firstWhere('stock_item_id', $huile->id)];
    $this->actingAs($acheteur)->get(route('economat.purchase_requests.show', $demande))
        ->assertOk()->assertSee('Valider aux quantités retenues')->assertSee('quantities[' . $ligneRiz->id . ']', false);
    $this->actingAs($acheteur)->post(route('economat.purchase_requests.approve', $demande), [
        'notes' => 'Stock de sécurité suffisant pour l\'huile',
        'quantities' => [$ligneRiz->id => 8, $ligneHuile->id => 0],
    ])->assertRedirect();

    $demande->refresh()->load('lines');
    expect($demande->status)->toBe(PurchaseRequest::STATUS_APPROVED)
        ->and((float) $demande->lines->firstWhere('id', $ligneRiz->id)->quantity_approved)->toBe(8.0)
        ->and((float) $demande->lines->firstWhere('id', $ligneRiz->id)->quantity_requested)->toBe(10.0)
        ->and($demande->aEteAjustee())->toBeTrue()
        // 8 kg à 2 500 F : le total suit les quantités retenues.
        ->and($demande->total_estimated_amount)->toBe(2000000);

    // Le demandeur apprend les changements apportés à sa demande.
    Notification::assertSentTo($econome, PurchaseRequestUpdated::class, function ($n) use ($econome) {
        $message = $n->toArray($econome)['message'];

        return $n->etape === PurchaseRequestUpdated::APPROUVEE && str_contains($message, 'Riz 10 → 8 kg') && str_contains($message, 'Huile 5 → 0 kg');
    });

    // 3. Il tire le bon : le riz seul, à 8 kg.
    $this->actingAs($acheteur)->post(route('economat.purchase_requests.convert', $demande))->assertRedirect();
    $bon = PurchaseOrder::with('lines')->where('purchase_request_id', $demande->id)->sole();
    expect($bon->lines)->toHaveCount(1)
        ->and((float) $bon->lines->first()->quantity_ordered)->toBe(8.0)
        ->and(GoodsReceipt::query()->count())->toBe(0);

    // 4. Il le transmet au fournisseur : un bon de réception « en attente » s'ouvre.
    refuseAuxAchats($this->actingAs($econome)->post(route('economat.orders.transmit', $bon), ['moyen' => 'telephone']));
    $this->actingAs($acheteur)->post(route('economat.orders.transmit', $bon), ['moyen' => 'telephone'])->assertRedirect();
    expect($bon->fresh()->status)->toBe(PurchaseOrder::STATUS_SENT);

    $attente = GoodsReceipt::query()->where('purchase_order_id', $bon->id)->sole();
    expect($attente->status)->toBe(GoodsReceipt::STATUS_PENDING)
        ->and($attente->received_at)->toBeNull()
        ->and((float) $attente->lines()->sole()->quantity_ordered)->toBe(8.0);

    // La demande commandée part aux archives.
    $demande->refresh();
    expect($demande->archived_at)->not->toBeNull()->and($demande->statusLabel())->toBe('Commandée');
    $this->actingAs($econome)->get(route('economat.purchase_requests.index'))->assertOk()->assertDontSee($demande->number);
    $this->actingAs($econome)->get(route('economat.purchase_requests.index', ['vue' => 'archives']))->assertOk()->assertSee($demande->number);
    $impression = texteAffiche($this->actingAs($econome)->get(route('economat.purchase_requests.print', $demande))->assertOk()->getContent());
    expect($impression)->toContain($demande->number)->toContain('Commandée')->toContain('10 kg')->toContain('8 kg')->toContain($bon->number);

    // Tous suivent la livraison attendue ; seul l'économat la réceptionne.
    foreach (['purchasing_manager', 'controller', 'finance_manager'] as $role) {
        $this->actingAs(membreAchats($role))->get(route('economat.receipts.index'))->assertOk()->assertSee($attente->number)->assertSee('En attente de livraison');
        $this->actingAs(membreAchats($role))->get(route('economat.receipts.show', $attente))->assertOk()->assertSee('Livraison attendue');
    }
    refuseAuxAchats($this->actingAs($acheteur)->get(route('economat.receipts.create', $bon)));

    // 5. Première livraison, partielle : 5 kg. Le reste reste attendu sous un nouveau bon.
    $ligneBon = $bon->lines->first();
    $this->actingAs($econome)->get(route('economat.receipts.create', $bon))->assertOk()
        ->assertSee($attente->number)->assertSee('Livraison partielle')->assertSee('Livraison complète');
    $this->actingAs($econome)->post(route('economat.receipts.store', $bon), ['received_at' => now()->subMinute()->format('Y-m-d H:i'), 
        'delivery_note_number' => 'BL-1', 'livraison' => GoodsReceipt::DELIVERY_PARTIAL,
        'lines' => [$ligneBon->id => ['quantity_delivered' => 5]],
    ])->assertRedirect()->assertSessionHas('success');

    $attente->refresh();
    expect($attente->status)->toBe(GoodsReceipt::STATUS_RECEIVED)
        ->and($attente->delivery_type)->toBe(GoodsReceipt::DELIVERY_PARTIAL)
        ->and($attente->delivery_note_number)->toBe('BL-1')
        ->and($bon->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED)
        ->and((float) $riz->fresh()->current_stock)->toBe(7.0);
    $reste = GoodsReceipt::query()->enAttente()->where('purchase_order_id', $bon->id)->sole();
    expect((float) $reste->lines()->sole()->quantity_ordered)->toBe(3.0);

    // 6. Seconde livraison, déclarée complète avec 2 kg : le bon est soldé, le manquant n'est plus attendu.
    $this->actingAs($econome)->post(route('economat.receipts.store', $bon), ['received_at' => now()->subMinute()->format('Y-m-d H:i'), 
        'livraison' => GoodsReceipt::DELIVERY_COMPLETE,
        'lines' => [$ligneBon->id => ['quantity_delivered' => 2]],
    ])->assertRedirect()->assertSessionHas('success');

    expect($reste->fresh()->status)->toBe(GoodsReceipt::STATUS_RECEIVED)
        ->and($reste->fresh()->delivery_type)->toBe(GoodsReceipt::DELIVERY_COMPLETE)
        ->and($bon->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED)
        ->and(GoodsReceipt::query()->enAttente()->count())->toBe(0)
        ->and((float) $riz->fresh()->current_stock)->toBe(9.0);
});

test("la demande modifiée par le responsable des achats avertit son demandeur de chaque changement", function () {
    Notification::fake();
    $riz = articleAchats('Riz');
    $huile = articleAchats('Huile');
    $sucre = articleAchats('Sucre');
    $econome = membreAchats('econome');
    $acheteur = membreAchats('purchasing_manager');

    $demande = app(PurchaseRequestService::class)->create([
        'lines' => [
            ['stock_item_id' => $riz->id, 'quantity_requested' => 50],
            ['stock_item_id' => $huile->id, 'quantity_requested' => 20],
        ],
    ], $econome);
    $ligneRiz = $demande->lines->firstWhere('stock_item_id', $riz->id);

    $this->actingAs($acheteur)->get(route('economat.purchase_requests.edit', $demande))->assertOk()
        ->assertDontSee('name="department"', false)->assertSee('Retirer cet article');
    $this->actingAs($acheteur)->put(route('economat.purchase_requests.update', $demande), [
        'supplier_id' => Supplier::create(['name' => 'Grossiste Sud', 'is_active' => true])->id,
        'priority' => 'normal',
        'lines' => [
            ['id' => $ligneRiz->id, 'stock_item_id' => $riz->id, 'quantity_requested' => 40],
            ['stock_item_id' => $sucre->id, 'quantity_requested' => 10],
        ],
    ])->assertRedirect()->assertSessionHas('success');

    expect($demande->fresh()->department)->toBe('economat');
    Notification::assertSentTo($econome, PurchaseRequestUpdated::class, function ($n) use ($econome) {
        $message = $n->toArray($econome)['message'];

        return $n->etape === PurchaseRequestUpdated::MODIFIEE
            && str_contains($message, 'Riz 50 → 40 kg') && str_contains($message, 'Huile : article retiré') && str_contains($message, 'Sucre : article ajouté (10 kg)');
    });
});

test("un chef de service choisit encore son département ; la demande du responsable des achats part à son responsable financier", function () {
    Notification::fake();
    $article = articleAchats('Savon');

    $this->actingAs(membreAchats('housekeeping_leader'))->get(route('economat.purchase_requests.create'))->assertOk()
        ->assertSee('name="department"', false);

    $acheteur = membreAchats('purchasing_manager');
    $financier = membreAchats('finance_manager');
    app(PurchaseRequestService::class)->create([
        'lines' => [['stock_item_id' => $article->id, 'quantity_requested' => 3]],
    ], $acheteur);

    Notification::assertNotSentTo($acheteur, PurchaseRequestUpdated::class);
    Notification::assertSentTo($financier, PurchaseRequestUpdated::class, fn ($n) => $n->etape === PurchaseRequestUpdated::SOUMISE);
});

test("annuler le bon de commande transmis annule son bon de réception en attente", function () {
    $fournisseur = Supplier::create(['name' => 'Marché', 'phone' => '690000001', 'is_active' => true]);
    $article = articleAchats('Tomates', $fournisseur);
    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $bon->lines()->create(['stock_item_id' => $article->id, 'quantity_ordered' => 6, 'unit_price' => 50000]);
    $acheteur = membreAchats('purchasing_manager');

    $this->actingAs($acheteur)->post(route('economat.orders.transmit', $bon), ['moyen' => 'main_propre'])->assertRedirect();
    $attente = GoodsReceipt::query()->enAttente()->where('purchase_order_id', $bon->id)->sole();

    $this->actingAs($acheteur)->post(route('economat.orders.cancel', $bon))->assertRedirect();

    expect($attente->fresh()->status)->toBe(GoodsReceipt::STATUS_CANCELLED)
        ->and(GoodsReceipt::query()->enAttente()->count())->toBe(0);
});

test("le bon envoyé au fournisseur ne porte jamais de prix", function () {
    $fournisseur = Supplier::create(['name' => 'Grossiste Sud', 'email' => 'commandes@grossiste.test', 'is_active' => true]);
    $article = articleAchats('Sucre', $fournisseur);
    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $bon->lines()->create(['stock_item_id' => $article->id, 'quantity_ordered' => 12, 'unit_price' => 3456700]);
    $bon->recalculateTotal();

    // La version « sans prix » est celle du fournisseur ; la version chiffrée reste interne.
    $impression = $this->actingAs(membreAchats('purchasing_manager'))->get(route('economat.orders.print', [$bon, 'prix' => 0]))->assertOk();
    $texte = texteAffiche($impression->getContent());
    expect($texte)->toContain('Sucre')->toContain('12 kg')
        ->not->toContain('34 567')->not->toContain('414 804')->not->toContain('FCFA');

    $email = (new PurchaseOrderMail($bon->fresh(['lines.item', 'supplier'])))->render();
    expect(texteAffiche($email))->toContain('Sucre')->toContain('12 kg')
        ->not->toContain('34 567')->not->toContain('414 804');
});

test("le responsable financier peut valider une demande ; l'économe, qui demande, non", function () {
    $article = articleAchats('Farine');
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'economat',
        'lines' => [['stock_item_id' => $article->id, 'quantity_requested' => 4]],
    ], membreAchats('econome'));

    refuseAuxAchats($this->actingAs(membreAchats('econome'))->post(route('economat.purchase_requests.approve', $demande)));
    expect($demande->fresh()->status)->toBe(PurchaseRequest::STATUS_PENDING);

    $this->actingAs(membreAchats('finance_manager'))->post(route('economat.purchase_requests.approve', $demande))->assertRedirect();
    expect($demande->fresh()->status)->toBe(PurchaseRequest::STATUS_APPROVED)
        // Sans ajustement, la quantité approuvée est celle demandée.
        ->and((float) $demande->fresh()->lines->first()->quantity_approved)->toBe(4.0);
});

test("une approbation où tout est ramené à zéro est refusée : c'est un refus, avec son motif", function () {
    $article = articleAchats('Sel');
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'economat',
        'lines' => [['stock_item_id' => $article->id, 'quantity_requested' => 4]],
    ], membreAchats('econome'));

    $this->actingAs(membreAchats('controller'))->post(route('economat.purchase_requests.approve', $demande), [
        'quantities' => [$demande->lines->first()->id => 0],
    ])->assertSessionHas('error');

    expect($demande->fresh()->status)->toBe(PurchaseRequest::STATUS_PENDING);
});

test('les tuiles : Achats et Éditions pour le responsable des achats, sans l\'Économat', function () {
    $tuiles = fn (string $role) => collect(Navigation::getLauncherGroups(membreAchats($role)))->pluck('id');

    expect($tuiles('purchasing_manager'))->toContain('achats', 'editions')->not->toContain('economat');
    expect($tuiles('econome'))->toContain('economat', 'editions')->not->toContain('achats');
    foreach (['controller', 'finance_manager', 'manager'] as $role) {
        expect($tuiles($role))->toContain('achats');
    }

    // Ses éditions : l'archive du circuit d'achat.
    $acheteur = membreAchats('purchasing_manager');
    $editions = app(Catalogue::class)->pour($acheteur)->map->cle();
    expect($editions)->toContain('demandes-achat', 'bons-commande', 'bons-reception', 'fournisseurs');

    $fournisseur = Supplier::create(['name' => 'Laiterie du Plateau', 'phone' => '677000000', 'is_active' => true]);
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'economat',
        'lines' => [['stock_item_id' => articleAchats('Yaourt', $fournisseur)->id, 'quantity_requested' => 30]],
    ], membreAchats('econome'));

    $this->actingAs($acheteur)->get(route('editions.index'))->assertOk()->assertSee("Registre des demandes d'achat")->assertSee('Répertoire des fournisseurs');
    $this->actingAs($acheteur)->get(route('editions.show', 'demandes-achat'))->assertOk()->assertSee($demande->number);
    $this->actingAs($acheteur)->get(route('editions.show', 'fournisseurs'))->assertOk()->assertSee('Laiterie du Plateau');
    // « Retrouver une pièce » retrouve la demande.
    $this->actingAs($acheteur)->get(route('editions.index', ['q' => $demande->number]))->assertOk()
        ->assertSee(route('economat.purchase_requests.print', $demande), false);
});

test("le tableau des achats montre à chacun ce qu'il doit faire", function () {
    $article = articleAchats('Lait', stock: 1, seuil: 6);
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'economat',
        'lines' => [['stock_item_id' => $article->id, 'quantity_requested' => 12]],
    ], membreAchats('econome'));

    $acheteur = membreAchats('purchasing_manager');
    $this->actingAs($acheteur)->get(route('achats.index'))
        ->assertOk()->assertSee('Demandes à vérifier')->assertSee($demande->number)->assertSee('Votre vérification est attendue')
        ->assertSee('Alertes de stock')->assertSee('Lait');

    // Le contrôle de gestion suit le circuit.
    $this->actingAs(membreAchats('controller'))->get(route('achats.index'))->assertOk()->assertSee($demande->number);
    refuseAuxAchats($this->actingAs($acheteur)->get(route('economat.index')));

    refuseAuxAchats($this->actingAs(membreAchats('econome'))->get(route('achats.index')));
});

test("le fichier des fournisseurs : le responsable des achats le tient, l'économe le consulte", function () {
    refuseAuxAchats($this->actingAs(membreAchats('econome'))->post(route('economat.suppliers.store'), ['name' => 'Nouveau']));
    $this->actingAs(membreAchats('econome'))->get(route('economat.suppliers.index'))->assertOk()->assertDontSee('Nouveau fournisseur');

    $this->actingAs(membreAchats('purchasing_manager'))->get(route('economat.suppliers.index'))->assertOk()->assertSee('Nouveau fournisseur');
});

test("l'alerte de stock mène le responsable des achats au réapprovisionnement", function () {
    $article = articleAchats('Beurre');
    $alerte = new StockItemBelowThreshold($article);

    expect($alerte->toArray(membreAchats('purchasing_manager'))['url'])->toBe(route('economat.control.suggestions.index'))
        ->and($alerte->toArray(membreAchats('econome'))['url'])->toBe(route('economat.items.show', $article));
});

test('commander et réceptionner, ou engager et payer : des cumuls interdits', function () {
    expect(DutySegregation::conflictsFor(['purchasing_manager', 'econome']))->not->toBeEmpty()
        ->and(DutySegregation::conflictsFor(['purchasing_manager', 'storekeeper']))->not->toBeEmpty()
        ->and(DutySegregation::conflictsFor(['purchasing_manager', 'accountant']))->not->toBeEmpty()
        ->and(DutySegregation::conflictsFor(['purchasing_manager', 'controller']))->not->toBeEmpty()
        // Le responsable financier tient aussi les livres (il inclut le comptable).
        ->and(DutySegregation::conflictsFor(['purchasing_manager', 'finance_manager']))->not->toBeEmpty();
});
