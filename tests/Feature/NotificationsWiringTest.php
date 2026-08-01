<?php

use App\Models\DiscussionConversation;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantPantryMovement;
use App\Models\ShopProduct;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\User;
use App\Notifications\DiscussionMessageReceived;
use App\Notifications\PantryItemLowStock;
use App\Notifications\ShopProductLowStock;
use App\Notifications\StockItemBelowThreshold;
use App\Notifications\StockRequisitionSubmitted;
use App\Notifications\StockRequisitionUpdated;
use App\Services\Notifier;
use App\Services\RestaurantStockService;
use App\Services\StockRequisitionService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function notifUser(string $role, bool $active = true): User
{
    return User::factory()->create(['role' => $role, 'is_active' => $active]);
}

/** Les modules sont lus depuis un cache statique ; on l'ouvre pour le test. */
function notifEnableModules(array $modules): void
{
    $prop = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, $modules);
}

// ── Le service d'envoi ────────────────────────────────────────────────────────

test('toRoles ne touche que les porteurs du rôle visé', function () {
    Notification::fake();

    $econome = notifUser('econome');
    $serveur = notifUser('restaurant_staff');

    app(Notifier::class)->toRoles(['econome'], new StockItemBelowThreshold(
        StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'current_stock' => 0, 'min_stock' => 5])
    ));

    Notification::assertSentTo($econome, StockItemBelowThreshold::class);
    Notification::assertNotSentTo($serveur, StockItemBelowThreshold::class);
});

test('un compte désactivé ne reçoit plus rien', function () {
    Notification::fake();

    $parti = notifUser('econome', active: false);

    app(Notifier::class)->toRoles(['econome'], new StockItemBelowThreshold(
        StockItem::create(['name' => 'Savon', 'unit' => 'pièce', 'current_stock' => 0, 'min_stock' => 2])
    ));

    Notification::assertNothingSent();
});

test("l'auteur de l'action ne s'auto-notifie pas", function () {
    Notification::fake();

    $auteur = notifUser('econome');
    $collegue = notifUser('econome');

    app(Notifier::class)->toRoles(
        ['econome'],
        new StockItemBelowThreshold(StockItem::create(['name' => 'Eau', 'unit' => 'L', 'current_stock' => 0, 'min_stock' => 1])),
        $auteur->id
    );

    Notification::assertSentTo($collegue, StockItemBelowThreshold::class);
    Notification::assertNotSentTo($auteur, StockItemBelowThreshold::class);
});

test("une panne d'envoi n'interrompt pas l'action métier", function () {
    Log::spy();

    // Le canal tombe en panne (SMTP, VAPID, réseau…) : la méthode doit rendre
    // la main sans exception, sinon une demande de stock serait perdue.
    Notification::shouldReceive('send')->andThrow(new RuntimeException('canal indisponible'));

    $econome = notifUser('econome');
    $item = StockItem::create(['name' => 'Huile', 'unit' => 'L', 'current_stock' => 0, 'min_stock' => 3]);

    $exception = null;
    try {
        app(Notifier::class)->send($econome, new StockItemBelowThreshold($item));
    } catch (\Throwable $e) {
        $exception = $e;
    }

    expect($exception)->toBeNull();
    Log::shouldHaveReceived('error')->once();
});

// ── Canaux : in-app et push ensemble ──────────────────────────────────────────

test('chaque notification part à la fois en base et en push', function (string $class, array $args) {
    $notification = new $class(...$args);

    expect($notification->via(notifUser('manager')))->toBe(['database', 'webpush']);
})->with([
    'stock bas'        => [StockItemBelowThreshold::class, fn () => [new StockItem(['name' => 'X', 'unit' => 'kg'])]],
    'demande transmise' => [StockRequisitionSubmitted::class, fn () => [new StockRequisition()]],
    'demande traitée'  => [StockRequisitionUpdated::class, fn () => [new StockRequisition()]],
    'boutique'         => [ShopProductLowStock::class, fn () => [new ShopProduct(['name' => 'Y'])]],
    'garde-manger'     => [PantryItemLowStock::class, fn () => [new RestaurantPantryItem(['name' => 'Z', 'unit' => 'kg'])]],
]);

// ── Économat ──────────────────────────────────────────────────────────────────

test("une demande de matériel prévient l'économat", function () {
    Notification::fake();

    $econome = notifUser('econome');
    $chefResto = notifUser('restaurant_chief');
    $this->actingAs($chefResto);

    $item = StockItem::create(['name' => 'Torchons', 'unit' => 'pièce', 'current_stock' => 50]);

    $this->post(route('economat.requisitions.store'), [
        'department' => 'restaurant',
        'lines'      => [['stock_item_id' => $item->id, 'quantity' => 5]],
    ])->assertRedirect();

    Notification::assertSentTo($econome, StockRequisitionSubmitted::class);
    Notification::assertNotSentTo($chefResto, StockRequisitionSubmitted::class);
});

test('la validation de la demande revient au demandeur', function () {
    Notification::fake();

    $econome = notifUser('econome');
    $demandeur = notifUser('housekeeping_leader');

    $item = StockItem::create(['name' => 'Draps', 'unit' => 'pièce', 'current_stock' => 40]);
    $requisition = StockRequisition::create(['department' => 'housekeeping', 'requested_by' => $demandeur->id]);
    $requisition->lines()->create(['stock_item_id' => $item->id, 'quantity_requested' => 10]);

    $this->actingAs($econome)
        ->post(route('economat.requisitions.approve', $requisition))
        ->assertRedirect();

    Notification::assertSentTo($demandeur, StockRequisitionUpdated::class);
});

test("l'article qui passe sous son seuil alerte l'économe, une seule fois", function () {
    Notification::fake();

    $econome = notifUser('econome');
    $this->actingAs($econome);

    $item = StockItem::create([
        'name' => 'Café', 'unit' => 'kg', 'current_stock' => 20, 'min_stock' => 10, 'average_cost' => 100,
    ]);
    $stock = app(StockService::class);

    $stock->recordOut($item, 5);    // 15 kg : encore au-dessus du seuil
    Notification::assertNothingSent();

    $stock->recordOut($item->fresh(), 8);   // 7 kg : franchissement
    Notification::assertSentTo($econome, StockItemBelowThreshold::class);

    // Sortie suivante déjà sous le seuil : pas de second rappel.
    $stock->recordOut($item->fresh(), 2);
    Notification::assertSentToTimes($econome, StockItemBelowThreshold::class, 1);
});

test('la livraison de la demande déclenche aussi l\'alerte de seuil', function () {
    Notification::fake();

    $econome = notifUser('econome');
    $demandeur = notifUser('housekeeping_leader');
    $this->actingAs($econome);

    $item = StockItem::create([
        'name' => 'Éponges', 'unit' => 'pièce', 'current_stock' => 12, 'min_stock' => 10, 'average_cost' => 50,
    ]);
    $requisition = StockRequisition::create([
        'department' => 'housekeeping', 'requested_by' => $demandeur->id, 'status' => StockRequisition::STATUS_APPROVED,
    ]);
    $requisition->lines()->create(['stock_item_id' => $item->id, 'quantity_requested' => 5]);

    app(StockRequisitionService::class)->deliver($requisition->fresh(), [$requisition->lines()->first()->id => 5.0]);

    expect((float) $item->fresh()->current_stock)->toBe(7.0);
    Notification::assertSentTo($econome, StockItemBelowThreshold::class);
});

// ── Garde-manger restaurant ───────────────────────────────────────────────────

test('un ingrédient sous le seuil alerte le chef cuisinier', function () {
    Notification::fake();

    $chef = notifUser('restaurant_chief');
    $this->actingAs($chef);

    $item = RestaurantPantryItem::create([
        'name' => 'Arachide', 'unit' => 'kg', 'current_stock' => 10, 'min_stock' => 4, 'average_cost' => 200,
    ]);

    app(RestaurantStockService::class)->recordMovement(
        $item, RestaurantPantryMovement::TYPE_OUT, 7, 'service du soir'
    );

    Notification::assertSentTo($chef, PantryItemLowStock::class);
});

// ── Boutique ──────────────────────────────────────────────────────────────────

test('une vente qui vide le rayon alerte le gérant boutique', function () {
    Notification::fake();

    notifEnableModules(['shop']);

    $gerant = notifUser('shop_manager');
    $caissier = notifUser('shop_cashier');

    $categorie = \App\Models\ShopCategory::create(['name' => 'Artisanat', 'is_active' => true]);
    $produit = ShopProduct::create([
        'shop_category_id' => $categorie->id,
        'name' => 'Masque Bamiléké', 'price' => 1500000, 'stock_quantity' => 6, 'reorder_level' => 3, 'is_active' => true,
    ]);

    \App\Models\CashRegisterSession::create([
        'user_id' => $caissier->id, 'opening_amount' => 0, 'opened_at' => now(),
    ]);

    $this->actingAs($caissier)
        ->post(route('shop.orders.store'), [
            'customer_name'  => 'Client comptoir',
            'payment_method' => 'cash',
            'items'          => [['product_id' => $produit->id, 'quantity' => 4]],
        ]);

    expect($produit->fresh()->stock_quantity)->toBe(2);
    Notification::assertSentTo($gerant, ShopProductLowStock::class);
});

// ── Discussions internes ──────────────────────────────────────────────────────

test('un message prévient les autres participants, pas son auteur', function () {
    Notification::fake();

    notifEnableModules(['discussions']);

    $auteur = notifUser('reception');
    $collegue = notifUser('manager');

    $conversation = DiscussionConversation::create(['title' => 'Arrivée VIP', 'created_by' => $auteur->id]);
    $conversation->participants()->sync([$auteur->id, $collegue->id]);

    $this->actingAs($auteur)
        ->post(route('discussions.store'), [
            'conversation_id' => $conversation->id,
            'body'            => 'La suite 12 est prête pour 18h.',
        ]);

    Notification::assertSentTo($collegue, DiscussionMessageReceived::class);
    Notification::assertNotSentTo($auteur, DiscussionMessageReceived::class);
});
