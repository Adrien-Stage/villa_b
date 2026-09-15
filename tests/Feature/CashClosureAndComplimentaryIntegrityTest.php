<?php

/**
 * Deux garanties d'intégrité, l'une sur l'écart de caisse, l'autre sur les
 * séjours offerts. Elles portent toutes deux sur des pièces qui mettent une
 * personne en cause : elles doivent rester hors de portée de cette personne.
 */

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── L'écart de caisse ────────────────────────────────────────────────────────

test('le solde théorique est recalculé et non repris du formulaire', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

    $receptionniste = User::factory()->create(['role' => 'reception']);
    $session = CashRegisterSession::create([
        'user_id'        => $receptionniste->id,
        'module'         => 'reception',
        'opening_amount' => 100000,   // 1 000 FCFA
        'opened_at'      => now(),
    ]);

    Payment::create([
        'cash_register_session_id' => $session->id,
        'amount'    => 50000,
        'currency'  => 'XAF',
        'method'    => 'cash',
        'status'    => 'completed',
        'reference' => 'PAY-TEST-000001',
        'paid_at'   => now(),
    ]);
    CashRegisterDisbursement::create([
        'cash_register_session_id' => $session->id,
        'user_id' => $receptionniste->id,
        'amount'  => 20000,
        'reason'  => 'Achat de fournitures',
    ]);

    // Théorique attendu : 100 000 + 50 000 − 20 000 = 130 000
    expect($session->theoreticalBalance())->toBe(130000);

    $this->actingAs($receptionniste);

    // L'agent compte 1 100 FCFA en tiroir et tente de faire passer un
    // théorique égal à son comptage, pour afficher un écart nul.
    $this->post(route('bookings.cash_register.close.store'), [
        'actual_closing_amount'      => 1100,
        'theoretical_closing_amount' => 110000,
    ])->assertRedirect();

    $session->refresh();

    expect($session->theoretical_closing_amount)->toBe(130000)
        ->and($session->actual_closing_amount)->toBe(110000)
        // Le manque de 200 FCFA apparaît malgré la valeur soumise.
        ->and($session->discrepancy_amount)->toBe(-20000);
});

test('une clôture sincère ne fait apparaître aucun écart', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

    $receptionniste = User::factory()->create(['role' => 'reception']);
    $session = CashRegisterSession::create([
        'user_id'        => $receptionniste->id,
        'module'         => 'reception',
        'opening_amount' => 75000,
        'opened_at'      => now(),
    ]);

    $this->actingAs($receptionniste);
    $this->post(route('bookings.cash_register.close.store'), [
        'actual_closing_amount' => 750,
    ])->assertRedirect();

    $session->refresh();

    expect($session->discrepancy_amount)->toBe(0)
        ->and($session->closed_at)->not->toBeNull();
});

// ── Les séjours offerts ──────────────────────────────────────────────────────

/** Un séjour offert en attente de validation, créé par la réception. */
function sejourOffert(User $auteur): Booking
{
    // Pas de fabrique pour ces deux modèles : on les crée comme le font les
    // autres suites (voir BookingDateAvailabilityTest).
    $type = RoomType::create([
        'code' => strtoupper(substr(md5(uniqid('', true)), 0, 6)),
        'name' => 'Chambre Offerte',
        'base_capacity' => 2, 'max_capacity' => 2,
        'base_price' => 4000000, // 40 000 FCFA la nuitée
        'is_active' => true,
    ]);
    $room = Room::create([
        'room_type_id' => $type->id,
        'number'       => (string) random_int(1000, 9999),
        'is_active'    => true,
    ]);

    return Booking::create([
        'room_id'              => $room->id,
        'customer_id'          => Customer::create(['first_name' => 'Client', 'last_name' => 'Offert'])->id,
        'booking_number'       => 'BK-OFFERT-01',
        'status'               => BookingStatus::PENDING,
        'is_complimentary'     => true,
        'complimentary_reason' => 'Geste commercial après incident de climatisation',
        'complimentary_value'  => 4000000 * 2,
        'check_in'             => today(),
        'check_out'            => today()->addDays(2),
        'adults_count'         => 1,
        'children_count'       => 0,
        'total_nights'         => 2,
        'price_per_night'      => 0,
        'total_room_amount'    => 0,
        'total_amount'         => 0,
        'balance_due'          => 0,
        'created_by'           => $auteur->id,
    ]);
}

test("la validation d'un séjour offert n'exige plus de caisse ouverte", function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

    $receptionniste = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);
    $booking = sejourOffert($receptionniste);

    // Aucune session de caisse n'est ouverte pour ce manager.
    expect(CashRegisterSession::where('user_id', $manager->id)->whereNull('closed_at')->exists())
        ->toBeFalse();

    $this->actingAs($manager);
    $this->post(route('bookings.approve', $booking))->assertRedirect();

    expect($booking->refresh()->status)->toBe(BookingStatus::CONFIRMED);
});

test('la validation inscrit au journal qui a offert, à qui et pour quel montant', function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

    $receptionniste = User::factory()->create(['role' => 'reception']);
    $manager = User::factory()->create(['role' => 'manager']);
    $booking = sejourOffert($receptionniste);

    $this->actingAs($manager);
    $this->post(route('bookings.approve', $booking))->assertRedirect();

    $booking->refresh();
    expect($booking->approved_by)->toBe($manager->id)
        ->and($booking->approved_at)->not->toBeNull();

    $trace = AuditLog::where('event_type', 'complimentary_booking')->latest('id')->first();

    expect($trace)->not->toBeNull()
        ->and($trace->user_id)->toBe($manager->id)
        ->and($trace->payload['booking_id'])->toBe($booking->id)
        ->and($trace->payload['nights'])->toBe(2)
        // Le manque à gagner, que le montant du séjour (zéro) ne dit pas.
        ->and($trace->payload['value_cents'])->toBe(8000000)
        ->and($trace->payload['requested_by'])->toBe($receptionniste->id)
        ->and($trace->payload['customer_type'])->toBe('particulier')
        ->and($trace->action)->toContain('Geste commercial');
});

test("seul un manager peut valider un séjour offert", function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);

    $receptionniste = User::factory()->create(['role' => 'reception']);
    $booking = sejourOffert($receptionniste);

    $this->actingAs($receptionniste);
    $this->post(route('bookings.approve', $booking))->assertStatus(403);

    expect($booking->refresh()->status)->toBe(BookingStatus::PENDING);
});
