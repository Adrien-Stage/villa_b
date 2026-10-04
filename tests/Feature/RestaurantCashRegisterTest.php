<?php

/**
 * Caisse du restaurant : une par restaurant, une session par personne.
 *
 * Le restaurant encaissait sans caisse — ni fond, ni comptage, ni contrôle.
 * Il suit désormais le même circuit que la réception et la boutique :
 * ouverture, encaissements rattachés à la session, comptage par son
 * titulaire, contresignature de la comptabilité.
 */

use App\Models\CashRegisterSession;
use App\Models\PointOfSale;
use App\Models\RestaurantCustomerOrder;
use App\Models\Role;
use App\Models\User;
use App\Support\CashClosurePolicy;
use App\Support\PointOfSaleCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['restaurant', 'comptabilite', 'accounting']);
    $this->seed(\Database\Seeders\TenantSeeder::class);
    RoleCatalog::sync();
    PointOfSaleCatalog::sync();
});

function personneDuRestaurant(string $role, string $nom = 'Ines Biya'): User
{
    $user = User::factory()->create(['role' => $role, 'name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user;
}

function noteImpayee(int $montant = 1500000): RestaurantCustomerOrder
{
    return RestaurantCustomerOrder::create([
        'table_number' => 'T' . random_int(1, 99), 'customer_name' => 'Client de passage',
        'status' => 'served', 'total_amount' => $montant, 'payment_status' => 'unpaid',
    ]);
}

test("sans caisse ouverte, le caissier n'encaisse pas", function () {
    $note = noteImpayee();

    $this->actingAs(personneDuRestaurant('cashier'))
        ->post(route('restaurant.billing.paid', $note), ['payment_method' => 'cash'])
        ->assertSessionHasErrors('cash_register');

    expect($note->fresh()->payment_status)->toBe('unpaid');
});

test("ouverte, la caisse reçoit les encaissements et en tient le solde", function () {
    $caissier = personneDuRestaurant('cashier');
    $note = noteImpayee(1500000);

    $this->actingAs($caissier)->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 20000])
        ->assertRedirect(route('restaurant.billing.index'));

    $session = CashRegisterSession::where('module', 'restaurant')->sole();
    expect($session->pointOfSale->slug)->toBe('restaurant');

    $this->post(route('restaurant.billing.paid', $note), ['payment_method' => 'cash'])->assertSessionHasNoErrors();

    expect($note->fresh()->cash_register_session_id)->toBe($session->id)
        ->and($note->fresh()->point_of_sale_id)->toBe($session->point_of_sale_id)
        // Fond de 20 000 F plus la note de 15 000 F, en centimes.
        ->and($session->fresh()->theoreticalBalance())->toBe(2000000 + 1500000);
});

test("le comptage arrête l'encaissement ; la comptabilité contresigne et clôt", function () {
    $caissier = personneDuRestaurant('cashier');
    $this->actingAs($caissier)->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 20000]);
    $this->post(route('restaurant.billing.paid', noteImpayee(1500000)), ['payment_method' => 'cash']);

    $this->post(route('restaurant.cash_register.close.store'), ['actual_closing_amount' => 34000])->assertRedirect();

    $session = CashRegisterSession::where('module', 'restaurant')->sole();
    expect($session->status)->toBe(CashClosurePolicy::STATUS_PENDING_REVIEW)
        ->and($session->discrepancy_amount)->toBe(-100000);

    // Comptée, la caisse n'encaisse plus.
    $suivante = noteImpayee();
    $this->post(route('restaurant.billing.paid', $suivante), ['payment_method' => 'cash'])->assertSessionHasErrors('cash_register');

    $comptable = personneDuRestaurant('accountant', 'Marie Abena');
    $this->actingAs($comptable)->get(route('accounting.cash_reviews'))->assertOk()->assertSee('Restaurant');
    $this->post(route('accounting.cash_reviews.store', $session))->assertRedirect();

    expect($session->fresh()->closed_at)->not->toBeNull()
        ->and($session->fresh()->witness_id)->toBe($comptable->id);
});

test("un tiroir n'a qu'une session ouverte : chacun compte ce qu'il a encaissé", function () {
    $this->actingAs(personneDuRestaurant('cashier', 'Ines Biya'))
        ->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 10000]);

    $this->actingAs(personneDuRestaurant('cashier', 'Paul Ndongo'))
        ->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 10000])
        ->assertSessionHasErrors('cash_register');

    expect(session('errors')->first('cash_register'))->toContain('Ines Biya');

    expect(CashRegisterSession::where('module', 'restaurant')->count())->toBe(1);
});

test("deux restaurants, deux caisses : chacun la sienne", function () {
    PointOfSale::create(['code' => 'KOT', 'slug' => 'kotibe', 'name' => 'Kotibe', 'kind' => PointOfSale::KIND_RESTAURATION, 'series_prefix' => 'KOT-', 'is_active' => true, 'sort_order' => 4]);
    $kotibe = PointOfSale::where('slug', 'kotibe')->first();
    $restaurant = PointOfSale::where('slug', 'restaurant')->first();

    // Affectée aux deux restaurants, elle dit quelle caisse elle tient.
    $ines = personneDuRestaurant('cashier', 'Ines Biya');
    $ines->restaurants()->sync([$restaurant->id, $kotibe->id]);

    $this->actingAs($ines)
        ->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 10000])
        ->assertSessionHasErrors('point_of_sale_id');

    $this->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 10000, 'point_of_sale_id' => $kotibe->id])
        ->assertSessionHasNoErrors();

    // Affecté au seul restaurant d'origine, il n'ouvre que sa caisse.
    $paul = personneDuRestaurant('cashier', 'Paul Ndongo');
    $paul->restaurants()->sync([$restaurant->id]);
    app(\App\Services\RestaurantContext::class)->oublier();

    $this->actingAs($paul)
        ->post(route('restaurant.cash_register.open.store'), [
            'opening_amount' => 10000, 'point_of_sale_id' => $kotibe->id,
        ])->assertSessionHasNoErrors();

    expect(CashRegisterSession::where('module', 'restaurant')->pluck('point_of_sale_id')->sort()->values()->all())
        ->toBe(PointOfSale::whereIn('slug', ['restaurant', 'kotibe'])->pluck('id')->sort()->values()->all());
});

test("un comptage en attente bloque la session suivante de la même personne", function () {
    $caissier = personneDuRestaurant('cashier');
    $this->actingAs($caissier)->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 10000]);
    $this->post(route('restaurant.cash_register.close.store'), ['actual_closing_amount' => 10000]);

    $this->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 10000])
        ->assertSessionHasErrors('cash_register');

    expect(session('errors')->first('cash_register'))->toContain('attend le contrôle');
});

test("un encaissement ne s'annule que dans sa propre caisse, tant qu'elle n'est pas comptée", function () {
    $caissier = personneDuRestaurant('cashier');
    $note = noteImpayee();
    $this->actingAs($caissier)->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 0]);
    $this->post(route('restaurant.billing.paid', $note), ['payment_method' => 'cash']);

    $this->post(route('restaurant.billing.unpaid', $note))->assertSessionHasNoErrors();
    expect($note->fresh()->payment_status)->toBe('unpaid');

    $this->post(route('restaurant.billing.paid', $note), ['payment_method' => 'cash']);
    $this->post(route('restaurant.cash_register.close.store'), ['actual_closing_amount' => 15000]);

    $this->post(route('restaurant.billing.unpaid', $note))->assertSessionHasErrors('cash_register');
    expect($note->fresh()->payment_status)->toBe('paid');
});

test("la facturation sur la chambre n'a pas besoin de caisse", function () {
    $this->seed(\Database\Seeders\RoomTypeSeeder::class);
    $note = noteImpayee();

    $this->actingAs(personneDuRestaurant('cashier'))
        ->post(route('restaurant.billing.paid', $note), ['payment_method' => 'room_charge'])
        // Sans séjour choisi, la règle du séjour répond — pas celle de la caisse.
        ->assertSessionHasErrors('booking_id')
        ->assertSessionDoesntHaveErrors('cash_register');
});

test("le responsable de restaurant tient la caisse s'il le faut ; la direction, non", function () {
    $this->actingAs(personneDuRestaurant('restaurant_manager', 'Ange Ondoa'))
        ->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 0])
        ->assertSessionHasNoErrors();

    $this->actingAs(personneDuRestaurant('manager', 'Marie Directrice'))
        ->post(route('restaurant.cash_register.open.store'), ['opening_amount' => 0]);

    expect(CashRegisterSession::where('module', 'restaurant')->count())->toBe(1);
});

test('la réception et la boutique comptent par le même circuit', function () {
    expect(CashClosurePolicy::modules())->toBe(['reception', 'shop', 'restaurant'])
        ->and(\App\Services\CashRegisterCircuit::libelle('restaurant'))->toBe('Restaurant')
        ->and(\App\Services\CashRegisterCircuit::routeDeComptage('restaurant'))->toBe('restaurant.cash_register.close');
});
