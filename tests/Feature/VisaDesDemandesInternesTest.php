<?php

use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\User;
use App\Notifications\StockRequisitionSubmitted;
use App\Notifications\StockRequisitionUpdated;
use App\Support\PointOfSaleCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * Le visa du chef de service : la demande d'un membre d'un service passe par
 * son chef avant d'arriver chez l'économe, qui la valide puis la livre. Le
 * chef qui demande lui-même porte déjà le visa.
 */

beforeEach(function () {
    RoleCatalog::sync();
    PointOfSaleCatalog::sync();
    activerModules(['economat', 'restaurant']);

    $this->javel = StockItem::create(['name' => 'Eau de Javel 5L', 'unit' => 'bidon', 'current_stock' => 30, 'average_cost' => 200000, 'is_active' => true]);
});

function personneDuService(string $role, string $nom): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function demanderPour($test, string $service, float $quantite = 4): StockRequisition
{
    $test->post(route('economat.requisitions.store'), [
        'department' => $service,
        'purpose'    => 'Besoin du service',
        'lines'      => [['stock_item_id' => $test->javel->id, 'quantity' => $quantite]],
    ])->assertSessionHasNoErrors();

    return StockRequisition::latest('id')->first();
}

test("la demande d'un membre attend le visa de son chef ; le chef est prévenu, pas l'économe", function () {
    Notification::fake();
    $chef = personneDuService('reception_chief', 'Chef réception');
    $econome = personneDuService('econome', 'Économe');
    $receptionniste = personneDuService('reception', 'Réceptionniste');

    $this->actingAs($receptionniste)->get(route('economat.requisitions.create'))->assertOk()
        ->assertSee('Votre chef de service visera');
    $demande = demanderPour($this, 'hebergement');

    expect($demande->status)->toBe(StockRequisition::STATUS_AWAITING_ENDORSEMENT)
        ->and($demande->endorsed_by)->toBeNull();
    Notification::assertSentTo($chef, StockRequisitionSubmitted::class);
    Notification::assertNotSentTo($econome, StockRequisitionSubmitted::class);

    // L'économe ne la valide pas tant qu'elle n'est pas visée.
    $this->actingAs($econome)->post(route('economat.requisitions.approve', $demande))
        ->assertSessionHas('error', "Cette demande attend encore le visa du chef de service : elle n'est pas arrivée à l'économat.");
    expect($demande->fresh()->status)->toBe(StockRequisition::STATUS_AWAITING_ENDORSEMENT);
});

test("visée par le chef, la demande part à l'économat, qui la valide puis la livre", function () {
    Notification::fake();
    $chef = personneDuService('reception_chief', 'Chef réception');
    $econome = personneDuService('econome', 'Économe');
    $receptionniste = personneDuService('reception', 'Réceptionniste');

    $this->actingAs($receptionniste);
    $demande = demanderPour($this, 'hebergement');

    $this->actingAs($chef)->get(route('economat.requisitions.show', $demande))->assertOk()->assertSee('Viser la demande');
    $this->post(route('economat.requisitions.endorse', $demande), ['decision' => 'viser', 'notes' => 'Urgent pour le desk'])
        ->assertSessionHas('success');

    $demande->refresh();
    expect($demande->status)->toBe(StockRequisition::STATUS_PENDING)
        ->and($demande->endorsed_by)->toBe($chef->id)
        ->and($demande->endorsement_notes)->toBe('Urgent pour le desk');
    Notification::assertSentTo($econome, StockRequisitionSubmitted::class);
    Notification::assertSentTo($receptionniste, StockRequisitionUpdated::class);

    // Un second visa ne repasse pas.
    $this->post(route('economat.requisitions.endorse', $demande), ['decision' => 'viser'])->assertForbidden();

    $this->actingAs($econome)->post(route('economat.requisitions.approve', $demande))->assertSessionHas('success');
    $this->post(route('economat.requisitions.deliver', $demande))->assertSessionHas('success');

    expect($demande->fresh()->status)->toBe(StockRequisition::STATUS_DELIVERED)
        ->and((float) $this->javel->fresh()->current_stock)->toBe(26.0);

    $this->get(route('economat.requisitions.print', $demande))->assertOk()
        ->assertSee('Le Chef de service')->assertSee('Chef réception');
});

test("refusée au visa, avec un motif, la demande n'arrive jamais à l'économat", function () {
    Notification::fake();
    $chef = personneDuService('housekeeping_leader', 'Gouvernante');
    $valet = personneDuService('housekeeping_staff', 'Valet');

    $this->actingAs($valet);
    $demande = demanderPour($this, 'housekeeping');
    expect($demande->status)->toBe(StockRequisition::STATUS_AWAITING_ENDORSEMENT);

    $this->actingAs($chef)->post(route('economat.requisitions.endorse', $demande), ['decision' => 'refuser'])
        ->assertSessionHasErrors('notes');
    $this->post(route('economat.requisitions.endorse', $demande), ['decision' => 'refuser', 'notes' => 'Stock d’étage suffisant'])
        ->assertSessionHas('success');

    $demande->refresh();
    expect($demande->status)->toBe(StockRequisition::STATUS_REJECTED)
        ->and($demande->refuseeAuVisa())->toBeTrue()
        ->and($demande->reviewed_by)->toBeNull()
        ->and(StockRequisition::pending()->count())->toBe(0);
    Notification::assertSentTo($valet, StockRequisitionUpdated::class,
        fn (StockRequisitionUpdated $n) => str_contains($n->toArray($valet)['message'], 'Stock d’étage suffisant'));
});

test('le chef qui demande pour son service porte déjà le visa', function () {
    Notification::fake();
    $econome = personneDuService('econome', 'Économe');
    $chef = personneDuService('housekeeping_leader', 'Gouvernante');

    $this->actingAs($chef);
    $demande = demanderPour($this, 'housekeeping');

    expect($demande->status)->toBe(StockRequisition::STATUS_PENDING)
        ->and($demande->endorsed_by)->toBe($chef->id);
    Notification::assertSentTo($econome, StockRequisitionSubmitted::class);

    // L'économe aussi : sa demande va droit au magasin.
    $this->actingAs($econome);
    expect(demanderPour($this, 'autre')->status)->toBe(StockRequisition::STATUS_PENDING);
});

test("seul le chef du service, ou la direction, vise ; jamais le demandeur", function () {
    $receptionniste = personneDuService('reception', 'Réceptionniste');
    $gouvernante = personneDuService('housekeeping_leader', 'Gouvernante');
    $directrice = personneDuService('manager', 'Directrice');

    $this->actingAs($receptionniste);
    $demande = demanderPour($this, 'hebergement');

    // Le demandeur ne vise pas sa propre demande.
    $this->post(route('economat.requisitions.endorse', $demande), ['decision' => 'viser'])
        ->assertSessionHas('access_denied_popup', true);

    // Le chef d'un autre service ne la voit ni ne la vise.
    $this->actingAs($gouvernante);
    $this->get(route('economat.requisitions.show', $demande))->assertForbidden();
    $this->post(route('economat.requisitions.endorse', $demande), ['decision' => 'viser'])->assertForbidden();

    // La direction vise quand le chef est absent ; ici, l'hôtel n'a pas de chef de réception.
    $this->actingAs($directrice)->post(route('economat.requisitions.endorse', $demande), ['decision' => 'viser'])
        ->assertSessionHas('success');
    expect($demande->fresh()->endorsed_by)->toBe($directrice->id);
});

test("sans chef dans le service, la direction est prévenue de la demande à viser", function () {
    Notification::fake();
    $directrice = personneDuService('manager', 'Directrice');
    $vendeuse = personneDuService('shop_cashier', 'Vendeuse');

    $this->actingAs($vendeuse);
    demanderPour($this, 'boutique');

    Notification::assertSentTo($directrice, StockRequisitionSubmitted::class);
});

test("le chef voit les demandes de son service ; chacun ne demande que pour le sien", function () {
    $chef = personneDuService('reception_chief', 'Chef réception');
    $receptionniste = personneDuService('reception', 'Réceptionniste');
    $valet = personneDuService('housekeeping_staff', 'Valet');

    $this->actingAs($receptionniste);
    $demande = demanderPour($this, 'hebergement');

    // Un valet ne demande pas au nom de la réception.
    $this->actingAs($valet)->post(route('economat.requisitions.store'), [
        'department' => 'hebergement',
        'lines'      => [['stock_item_id' => $this->javel->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('department');
    $this->get(route('economat.requisitions.index'))->assertOk()->assertDontSee($demande->number);

    $this->actingAs($chef)->get(route('economat.requisitions.index'))->assertOk()
        ->assertSee($demande->number)
        ->assertSee('À viser');
});

test('au restaurant, le chef cuisinier vise la demande du cuisinier', function () {
    $chef = personneDuService('restaurant_chief', 'Chef cuisinier');
    $cuisinier = personneDuService('restaurant_cook', 'Cuisinier');

    $this->actingAs($cuisinier);
    $demande = demanderPour($this, 'restaurant');
    expect($demande->status)->toBe(StockRequisition::STATUS_AWAITING_ENDORSEMENT);

    $this->actingAs($chef)->post(route('economat.requisitions.endorse', $demande), ['decision' => 'viser'])
        ->assertSessionHas('success');
    expect($demande->fresh()->status)->toBe(StockRequisition::STATUS_PENDING);
});
