<?php

use App\Editions\Catalogue;
use App\Mail\PurchaseOrderMail;
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
 *   l'économat (ou une alerte de stock) demande ;
 *   le contrôleur de gestion approuve, en ajustant les quantités — le
 *   responsable financier à sa place ;
 *   le responsable des achats tire le bon et l'envoie, sans prix ; la demande
 *   part aux archives ;
 *   l'économat réceptionne.
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
        'economat.purchase_requests.convert', 'economat.orders.send', 'economat.orders.transmit', 'economat.orders.cancel',
        'economat.control.suggestions.creer', 'economat.receipts.voir', 'editions.voir')
        ->not->toContain('economat.orders.receive', 'economat.receipts.creer', 'economat.purchase_requests.approve',
            'economat.purchase_requests.reject', 'economat.orders.creer', 'economat.items.voir');

    // L'économat réceptionne, gère le stock et demande ; il ne commande plus.
    $econome = $droits('econome');
    expect($econome)->toContain('economat.orders.receive', 'economat.receipts.creer', 'economat.purchase_requests.creer',
        'economat.stock_counts.creer', 'economat.requisitions.approve', 'economat.suppliers.voir')
        ->not->toContain('economat.orders.send', 'economat.orders.transmit', 'economat.suppliers.creer', 'economat.purchase_requests.convert');

    // La dépense : le contrôleur de gestion, le responsable financier à sa place.
    expect(PermissionCatalog::roles('economat.purchase_requests.approve'))->toContain('controller', 'finance_manager')
        ->not->toContain('manager', 'econome', 'purchasing_manager');

    // Le rôle est proposé à l'affectation.
    expect(Role::where('slug', 'purchasing_manager')->value('is_assignable'))->toBeTrue();
});

test("le circuit complet : approbation ajustée, bon tiré des quantités approuvées, envoi et archivage", function () {
    Notification::fake();

    $fournisseur = Supplier::create(['name' => 'Grossiste Nord', 'phone' => '690000000', 'is_active' => true]);
    $riz = articleAchats('Riz', $fournisseur);
    $huile = articleAchats('Huile', $fournisseur);

    $econome = membreAchats('econome');
    $controleur = membreAchats('controller');
    $acheteur = membreAchats('purchasing_manager');

    // 1. L'économat demande : le contrôleur est averti.
    $this->actingAs($econome)->post(route('economat.purchase_requests.store'), [
        'department' => 'economat', 'priority' => 'normal',
        'lines' => [
            ['stock_item_id' => $riz->id, 'quantity_requested' => 10],
            ['stock_item_id' => $huile->id, 'quantity_requested' => 5],
        ],
    ])->assertRedirect();
    $demande = PurchaseRequest::with('lines')->latest('id')->firstOrFail();
    Notification::assertSentTo($controleur, PurchaseRequestUpdated::class, fn ($n) => $n->etape === PurchaseRequestUpdated::SOUMISE);

    // L'économe ne tire plus de bon d'une demande.
    refuseAuxAchats($this->actingAs($econome)->post(route('economat.purchase_requests.convert', $demande)));

    // 2. Le contrôleur approuve : 10 kg de riz ramenés à 8, l'huile écartée.
    [$ligneRiz, $ligneHuile] = [$demande->lines->firstWhere('stock_item_id', $riz->id), $demande->lines->firstWhere('stock_item_id', $huile->id)];
    $this->actingAs($controleur)->get(route('economat.purchase_requests.show', $demande))
        ->assertOk()->assertSee('Approuver aux quantités retenues')->assertSee('quantities[' . $ligneRiz->id . ']', false);
    $this->actingAs($controleur)->post(route('economat.purchase_requests.approve', $demande), [
        'notes' => 'Stock de sécurité suffisant pour l\'huile',
        'quantities' => [$ligneRiz->id => 8, $ligneHuile->id => 0],
    ])->assertRedirect();

    $demande->refresh()->load('lines');
    expect($demande->status)->toBe(PurchaseRequest::STATUS_APPROVED)
        ->and((float) $demande->lines->firstWhere('id', $ligneRiz->id)->quantity_approved)->toBe(8.0)
        ->and((float) $demande->lines->firstWhere('id', $ligneRiz->id)->quantity_requested)->toBe(10.0)
        ->and($demande->aEteAjustee())->toBeTrue()
        // 8 kg à 2 500 F : le total suit les quantités approuvées.
        ->and($demande->total_estimated_amount)->toBe(2000000);

    // L'économat apprend les ajustements ; les achats, qu'il faut commander.
    Notification::assertSentTo($econome, PurchaseRequestUpdated::class, function ($n) use ($econome) {
        $message = $n->toArray($econome)['message'];

        return $n->etape === PurchaseRequestUpdated::APPROUVEE && str_contains($message, 'Riz 10 → 8 kg') && str_contains($message, 'Huile 5 → 0 kg');
    });
    Notification::assertSentTo($acheteur, PurchaseRequestUpdated::class, fn ($n) => $n->etape === PurchaseRequestUpdated::A_COMMANDER);

    // 3. Le responsable des achats tire le bon : le riz seul, à 8 kg.
    $this->actingAs($acheteur)->post(route('economat.purchase_requests.convert', $demande))->assertRedirect();
    $bon = PurchaseOrder::with('lines')->where('purchase_request_id', $demande->id)->sole();
    expect($bon->lines)->toHaveCount(1)
        ->and((float) $bon->lines->first()->quantity_ordered)->toBe(8.0)
        ->and($demande->fresh()->archived_at)->toBeNull();

    // L'économe ne l'envoie pas ; le responsable des achats le transmet.
    refuseAuxAchats($this->actingAs($econome)->post(route('economat.orders.transmit', $bon), ['moyen' => 'telephone']));
    $this->actingAs($acheteur)->post(route('economat.orders.transmit', $bon), ['moyen' => 'telephone'])->assertRedirect();
    expect($bon->fresh()->status)->toBe(PurchaseOrder::STATUS_SENT);

    // 4. La demande commandée part aux archives.
    $demande->refresh();
    expect($demande->archived_at)->not->toBeNull()
        ->and($demande->statusLabel())->toBe('Commandée');
    $this->actingAs($econome)->get(route('economat.purchase_requests.index'))->assertOk()->assertDontSee($demande->number);
    $this->actingAs($econome)->get(route('economat.purchase_requests.index', ['vue' => 'archives']))->assertOk()->assertSee($demande->number);

    // L'archive s'imprime : demandée, approuvée, commandée, avec ses signataires.
    $impression = texteAffiche($this->actingAs($econome)->get(route('economat.purchase_requests.print', $demande))->assertOk()->getContent());
    expect($impression)->toContain($demande->number)->toContain('Commandée')->toContain('10 kg')->toContain('8 kg')->toContain($bon->number);

    // 5. L'économat réceptionne ; l'acheteur, non.
    $this->actingAs($econome)->get(route('economat.receipts.create', $bon))->assertOk();
    refuseAuxAchats($this->actingAs($acheteur)->get(route('economat.receipts.create', $bon)));
});

test("le bon envoyé au fournisseur ne porte jamais de prix", function () {
    $fournisseur = Supplier::create(['name' => 'Grossiste Sud', 'email' => 'commandes@grossiste.test', 'is_active' => true]);
    $article = articleAchats('Sucre', $fournisseur);
    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $bon->lines()->create(['stock_item_id' => $article->id, 'quantity_ordered' => 12, 'unit_price' => 3456700]);
    $bon->recalculateTotal();

    $impression = $this->actingAs(membreAchats('purchasing_manager'))->get(route('economat.orders.print', $bon))->assertOk();
    $texte = texteAffiche($impression->getContent());
    expect($texte)->toContain('Sucre')->toContain('12 kg')
        ->not->toContain('34 567')->not->toContain('414 804')->not->toContain('FCFA');

    $email = (new PurchaseOrderMail($bon->fresh(['lines.item', 'supplier'])))->render();
    expect(texteAffiche($email))->toContain('Sucre')->toContain('12 kg')
        ->not->toContain('34 567')->not->toContain('414 804');
});

test("le responsable financier approuve à la place du contrôleur ; la direction ne décide plus de la dépense", function () {
    $article = articleAchats('Farine');
    $demande = app(PurchaseRequestService::class)->create([
        'department' => 'economat',
        'lines' => [['stock_item_id' => $article->id, 'quantity_requested' => 4]],
    ], membreAchats('econome'));

    refuseAuxAchats($this->actingAs(membreAchats('manager'))->post(route('economat.purchase_requests.approve', $demande)));
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

    $this->actingAs(membreAchats('controller'))->get(route('achats.index'))
        ->assertOk()->assertSee('Demandes à approuver')->assertSee($demande->number)->assertSee('Votre décision est attendue');

    $acheteur = membreAchats('purchasing_manager');
    $this->actingAs($acheteur)->get(route('achats.index'))
        ->assertOk()->assertSee('Alertes de stock')->assertSee('Lait')->assertSee('Chez le contrôle de gestion');
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
