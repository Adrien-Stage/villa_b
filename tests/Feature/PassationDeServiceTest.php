<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\ShiftAssignment;
use App\Models\ShiftHandover;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\CashClosurePolicy;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Passation de service à la réception : à 19 h, le réceptionniste du jour
 * compte son tiroir et le remet avec ses consignes. Celui du soir — prévu au
 * planning, ou un collègue s'il est empêché — voit tout ce que le quart a fait,
 * recompte le tiroir et ouvre sa caisse avec ce montant.
 */

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'accounting', 'comptabilite']);
    $this->travelTo(CarbonImmutable::parse('2026-10-16 07:05'));

    $reception = Department::where('code', 'REC')->sole();
    $this->jour = passeur('Marie Jour', 'reception');
    $this->soir = passeur('Joël Soir', 'reception');
    $this->remplacant = passeur('Paul Remplaçant', 'reception');
    $this->chef = passeur('Ange Chef', 'reception_chief');
    $this->comptable = passeur('Rose Comptable', 'accountant');

    // Le planning attend Joël au quart de nuit.
    ShiftAssignment::create(['user_id' => $this->soir->id, 'work_shift_id' => WorkShift::where('name', 'Nuit')->value('id'),
        'department_id' => $reception->id, 'date' => '2026-10-16']);

    // Le quart du jour : fond de 50 000, un encaissement de 30 000 en espèces,
    // une sortie de 2 000, une arrivée.
    $this->actingAs($this->jour)->post(route('bookings.cash_register.open.store'), ['opening_amount' => 50000]);
    $this->sessionJour = CashRegisterSession::where('user_id', $this->jour->id)->sole();
    $room = Room::where('number', '101')->first();
    $this->sejour = Booking::create([
        'customer_id' => Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Njoya'])->id, 'room_id' => $room->id,
        'booking_number' => 'VB-2026-000777', 'status' => BookingStatus::CHECKED_IN,
        'check_in' => '2026-10-16', 'check_out' => '2026-10-18', 'actual_check_in' => now()->addHours(3), 'checked_in_by' => $this->jour->id,
        'adults_count' => 1, 'total_nights' => 2, 'price_per_night' => 4500000, 'total_room_amount' => 9000000,
        'total_amount' => 9000000, 'paid_amount' => 3000000, 'balance_due' => 6000000, 'source' => 'direct',
    ]);
    Payment::create(['booking_id' => $this->sejour->id, 'customer_id' => $this->sejour->customer_id, 'amount' => 3000000, 'currency' => 'XAF',
        'method' => 'cash', 'status' => 'completed', 'reference' => 'PAY-2026-000001', 'paid_at' => now()->addHours(3),
        'processed_by' => $this->jour->id, 'cash_register_session_id' => $this->sessionJour->id]);
    CashRegisterDisbursement::create(['cash_register_session_id' => $this->sessionJour->id, 'user_id' => $this->jour->id,
        'amount' => 200000, 'reason' => 'Timbres fiscaux']);

    $this->travelTo(CarbonImmutable::parse('2026-10-16 19:05'));
});

function passeur(string $nom, string $role): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function remettreLeTiroir($test, int $compte = 78000, string $consignes = 'Le client de la 101 attend un taxi à 22 h.')
{
    return $test->actingAs($test->jour)->post(route('bookings.handovers.store'), ['handed_amount' => $compte, 'instructions' => $consignes]);
}

test("en fin de quart, le réceptionniste voit son quart, compte et remet le tiroir avec ses consignes", function () {
    $this->actingAs($this->jour)->get(route('bookings.handovers.create'))->assertOk()
        ->assertSee('Joël Soir')
        ->assertSee('VB-2026-000777')
        ->assertSee('Timbres fiscaux')
        ->assertSee('78 000 FCFA');

    remettreLeTiroir($this)->assertRedirect()->assertSessionHas('success');

    $passation = ShiftHandover::sole();
    expect($passation->status)->toBe(ShiftHandover::STATUS_PENDING)
        ->and($passation->handed_amount)->toBe(7800000)
        ->and($passation->planned_user_id)->toBe($this->soir->id)
        ->and($this->sessionJour->fresh()->status)->toBe(CashClosurePolicy::STATUS_PENDING_REVIEW)
        ->and($this->sessionJour->fresh()->discrepancy_amount)->toBe(0)
        ->and($this->soir->notifications()->first()?->data['title'])->toBe('Passation de service à reprendre');
});

test("le réceptionniste du soir lit la passation, recompte et ouvre sa caisse avec le tiroir", function () {
    remettreLeTiroir($this);
    $passation = ShiftHandover::sole();

    $this->actingAs($this->soir);
    // Ouvrir sa caisse le mène d'abord à la passation.
    $this->get(route('bookings.cash_register.open'))->assertRedirect(route('bookings.handovers.take', $passation));
    $this->post(route('bookings.cash_register.open.store'), ['opening_amount' => 0])->assertRedirect(route('bookings.handovers.take', $passation));
    $this->get(route('bookings.index'))->assertSee('a remis le tiroir de la réception');

    $this->get(route('bookings.handovers.take', $passation))->assertOk()
        ->assertSee('Le client de la 101 attend un taxi à 22 h.')
        ->assertSee('VB-2026-000777')
        ->assertSee('Prendre le service');

    // Sans avoir confirmé sa lecture, rien ne s'ouvre.
    $this->post(route('bookings.handovers.take.store', $passation), ['received_amount' => 78000])->assertSessionHasErrors('lu');
    expect(CashRegisterSession::where('user_id', $this->soir->id)->exists())->toBeFalse();

    $this->post(route('bookings.handovers.take.store', $passation), ['received_amount' => 78000, 'lu' => '1'])
        ->assertRedirect(route('bookings.cash_register.index'))->assertSessionHas('success');

    $sessionSoir = CashRegisterSession::where('user_id', $this->soir->id)->sole();
    expect($sessionSoir->opening_amount)->toBe(7800000)
        ->and($passation->fresh()->status)->toBe(ShiftHandover::STATUS_TAKEN)
        ->and($passation->fresh()->to_session_id)->toBe($sessionSoir->id)
        ->and($passation->fresh()->discrepancy_amount)->toBe(0)
        ->and($this->jour->notifications()->first()?->data['title'])->toBe('Passation reprise');

    // Repris : plus de passation en attente, la caisse du soir encaisse.
    $this->get(route('bookings.handovers.take', $passation))->assertRedirect(route('bookings.handovers.show', $passation));
});

test("si le réceptionniste prévu est empêché, un collègue reprend à sa place, et un écart reste inscrit", function () {
    remettreLeTiroir($this);
    $passation = ShiftHandover::sole();

    $this->actingAs($this->remplacant)->get(route('bookings.handovers.take', $passation))->assertOk()
        ->assertSee('Le planning attendait');
    $this->post(route('bookings.handovers.take.store', $passation), ['received_amount' => 77000, 'lu' => '1'])
        ->assertSessionHas('success');

    $passation->refresh();
    expect($passation->to_user_id)->toBe($this->remplacant->id)
        ->and($passation->reprisHorsPlanning())->toBeTrue()
        ->and($passation->discrepancy_amount)->toBe(-100000)
        ->and($this->chef->notifications()->get()->pluck('data.title'))->toContain('Passation reprise avec un écart');

    $this->actingAs($this->chef)->get(route('bookings.handovers.show', $passation))->assertOk()
        ->assertSee('Repris à la place de Joël Soir')
        ->assertSee('-1 000 FCFA');

    // Le prévu arrive ensuite : la passation est déjà reprise.
    $this->actingAs($this->soir)->post(route('bookings.handovers.take.store', $passation), ['received_amount' => 78000, 'lu' => '1'])
        ->assertSessionHas('error');
});

test("on ne reprend pas sa propre passation", function () {
    remettreLeTiroir($this);

    $this->actingAs($this->jour)->post(route('bookings.handovers.take.store', ShiftHandover::sole()), ['received_amount' => 78000, 'lu' => '1'])
        ->assertSessionHas('error');
    expect(ShiftHandover::sole()->isPending())->toBeTrue();
});

test("la comptabilité voit la passation en contrôlant la caisse du quart", function () {
    remettreLeTiroir($this);
    $passation = ShiftHandover::sole();
    $this->actingAs($this->soir)->post(route('bookings.handovers.take.store', $passation), ['received_amount' => 78000, 'lu' => '1']);

    $this->actingAs($this->comptable)->get(route('accounting.cash_reviews'))->assertOk()
        ->assertSee('Remis en passation')
        ->assertSee('repris par Joël Soir');
    $this->get(route('bookings.handovers.index'))->assertOk()->assertSee('Marie Jour')->assertSee('Joël Soir');
});
