<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CancellationPolicyService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelCancellationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $receptionist;
    private Room $room;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $managerRole = Role::firstOrCreate(['slug' => 'manager'], ['name' => 'Manager']);
        $receptionRole = Role::firstOrCreate(['slug' => 'reception'], ['name' => 'Réceptionniste']);

        $this->manager = User::factory()->create();
        $this->manager->roles()->attach($managerRole);

        $this->receptionist = User::factory()->create();
        $this->receptionist->roles()->attach($receptionRole);

        Tenant::firstOrCreate(['slug' => 'villa-boutanga'], [
            'name' => 'Hôtel Villa B',
            'country' => 'Cameroun',
            'city' => 'Douala',
        ]);

        $roomType = RoomType::create([
            'name' => 'Standard Deluxe',
            'code' => 'STD_DLX',
            'base_capacity' => 2,
            'base_price' => 5000000, // 50 000 FCFA
            'max_capacity' => 2,
        ]);

        $this->room = Room::create([
            'room_type_id' => $roomType->id,
            'number' => '101',
            'status' => 'clean',
        ]);

        $this->customer = Customer::create([
            'first_name' => 'Paul',
            'last_name' => 'Biya',
            'email' => 'paul@example.com',
            'phone' => '+237699000111',
        ]);
    }

    public function test_default_policies_exist_and_default_is_retrieved()
    {
        $service = app(CancellationPolicyService::class);
        $defaultPolicy = $service->getDefaultPolicy();

        $this->assertNotNull($defaultPolicy);
        $this->assertTrue($defaultPolicy->is_default);
        $this->assertEquals('FLEX_48H', $defaultPolicy->code);
    }

    public function test_booking_creation_automatically_snapshots_cancellation_policy()
    {
        $checkIn = now()->addDays(5)->startOfDay();
        $checkOut = now()->addDays(7)->startOfDay();

        $booking = Booking::create([
            'room_id' => $this->room->id,
            'customer_id' => $this->customer->id,
            'status' => BookingStatus::CONFIRMED,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'adults_count' => 1,
            'total_nights' => 2,
            'price_per_night' => 5000000,
            'total_room_amount' => 10000000,
            'total_amount' => 10000000,
            'deposit_amount' => 3000000,
            'paid_amount' => 3000000,
            'balance_due' => 7000000,
        ]);

        $this->assertNotNull($booking->cancellation_policy_id);
        $this->assertNotNull($booking->cancellation_policy_snapshot);
        $this->assertNotNull($booking->free_cancel_until);

        // 5 days ahead - 2 days before = 3 days ahead at 18:00
        $expectedLimit = $checkIn->copy()->subDays(2)->setTime(18, 0, 0);
        $this->assertEquals($expectedLimit->format('Y-m-d H:i'), $booking->free_cancel_until->format('Y-m-d H:i'));
    }

    public function test_free_cancellation_before_deadline_refunds_all_deposit()
    {
        $this->actingAs($this->manager);

        // Caisse ouverte
        $session = CashRegisterSession::create([
            'user_id' => $this->manager->id,
            'module' => 'reception',
            'opening_amount' => 10000000, // 100 000 FCFA
            'opened_at' => now(),
        ]);

        // Réservation avec arrivée dans 5 jours (donc annulation aujourd'hui = sans frais)
        $booking = Booking::create([
            'room_id' => $this->room->id,
            'customer_id' => $this->customer->id,
            'status' => BookingStatus::CONFIRMED,
            'check_in' => now()->addDays(5)->startOfDay(),
            'check_out' => now()->addDays(7)->startOfDay(),
            'adults_count' => 1,
            'total_nights' => 2,
            'price_per_night' => 5000000,
            'total_room_amount' => 10000000,
            'total_amount' => 10000000,
            'deposit_amount' => 4000000, // 40 000 FCFA
            'paid_amount' => 4000000,
            'balance_due' => 6000000,
        ]);

        // Annulation avec remboursement espèces
        $response = $this->post(route('bookings.cancel', $booking), [
            'reason_code' => 'guest_request',
            'reason_description' => 'Client empêché pour obligations professionnelles',
            'refund_method' => 'cash',
        ]);

        $response->assertRedirect(route('bookings.show', $booking));
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);

        $cancellation = $booking->cancellation;
        $this->assertNotNull($cancellation);
        $this->assertStringStartsWith('CAN-', $cancellation->cancellation_number);
        $this->assertEquals(0, $cancellation->penalty_amount);
        $this->assertEquals(0, $cancellation->deposit_retained);
        $this->assertEquals(4000000, $cancellation->refund_amount);
        $this->assertEquals('completed', $cancellation->refund_status);

        // Vérifier le paiement négatif en caisse
        $this->assertNotNull($cancellation->payment_id);
        $payment = Payment::find($cancellation->payment_id);
        $this->assertEquals(-4000000, $payment->amount);
        $this->assertEquals($session->id, $payment->cash_register_session_id);
    }

    public function test_late_cancellation_applies_penalty_and_creates_folio_item()
    {
        $this->actingAs($this->manager);

        // Caisse ouverte
        CashRegisterSession::create([
            'user_id' => $this->manager->id,
            'module' => 'reception',
            'opening_amount' => 10000000,
            'opened_at' => now(),
        ]);

        // Réservation arrivant DEMAIN (donc délai de 48h dépassé)
        // Politique: 1ère nuit due = 50 000 FCFA
        // Acompte déjà versé = 70 000 FCFA
        // Remboursement attendu = 70 000 - 50 000 = 20 000 FCFA
        $booking = Booking::create([
            'room_id' => $this->room->id,
            'customer_id' => $this->customer->id,
            'status' => BookingStatus::CONFIRMED,
            'check_in' => now()->addDay()->startOfDay(),
            'check_out' => now()->addDays(3)->startOfDay(),
            'adults_count' => 1,
            'total_nights' => 2,
            'price_per_night' => 5000000,
            'total_room_amount' => 10000000,
            'total_amount' => 10000000,
            'deposit_amount' => 7000000, // 70 000 FCFA
            'paid_amount' => 7000000,
            'balance_due' => 3000000,
        ]);

        $response = $this->post(route('bookings.cancel', $booking), [
            'reason_code' => 'schedule_change',
            'reason_description' => 'Annulation tardive la veille',
            'refund_method' => 'cash',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(BookingStatus::CANCELLED, $booking->status);

        $cancellation = $booking->cancellation;
        $this->assertNotNull($cancellation);
        $this->assertEquals(5000000, $cancellation->penalty_amount); // 50 000 FCFA 1ère nuit
        $this->assertEquals(5000000, $cancellation->deposit_retained);
        $this->assertEquals(2000000, $cancellation->refund_amount); // 20 000 FCFA restitué

        // Folio item pour les frais d'annulation retenus
        $penaltyFolio = $booking->folioItems()->where('type', FolioItem::TYPE_PENALTY)->first();
        $this->assertNotNull($penaltyFolio);
        $this->assertEquals(5000000, $penaltyFolio->total_price);
    }

    public function test_manager_can_waive_late_penalty()
    {
        $this->actingAs($this->manager);

        CashRegisterSession::create([
            'user_id' => $this->manager->id,
            'module' => 'reception',
            'opening_amount' => 10000000,
            'opened_at' => now(),
        ]);

        // Réservation arrivant demain avec 50 000 FCFA d'acompte
        $booking = Booking::create([
            'room_id' => $this->room->id,
            'customer_id' => $this->customer->id,
            'status' => BookingStatus::CONFIRMED,
            'check_in' => now()->addDay()->startOfDay(),
            'check_out' => now()->addDays(2)->startOfDay(),
            'adults_count' => 1,
            'total_nights' => 1,
            'price_per_night' => 5000000,
            'total_room_amount' => 5000000,
            'total_amount' => 5000000,
            'deposit_amount' => 5000000,
            'paid_amount' => 5000000,
            'balance_due' => 0,
        ]);

        $response = $this->post(route('bookings.cancel', $booking), [
            'reason_code' => 'medical',
            'reason_description' => 'Urgence hospitalisation client',
            'refund_method' => 'bank_transfer',
            'waive_penalty' => '1',
            'waive_reason' => 'Geste commercial exceptionnel manager',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $cancellation = $booking->cancellation;
        $this->assertTrue($cancellation->penalty_waived);
        $this->assertEquals(0, $cancellation->penalty_amount);
        $this->assertEquals(5000000, $cancellation->refund_amount); // Remboursement total
        $this->assertEquals('pending', $cancellation->refund_status); // Virement en attente
    }

    public function test_manager_can_create_update_and_set_default_cancellation_policy()
    {
        $this->actingAs($this->manager);

        // 1. Create policy
        $response = $this->post(route('settings.cancellation_policies.store'), [
            'code' => 'TEST_STRICT',
            'name' => 'Stricte 14 jours',
            'description' => 'Annulation sous 14 jours avec 50% de frais',
            'penalty_type' => 'percentage',
            'penalty_value' => '50',
            'free_cancel_days_before' => '14',
            'free_cancel_time' => '12:00',
        ]);

        $response->assertRedirect();
        $policy = CancellationPolicy::where('code', 'TEST_STRICT')->first();
        $this->assertNotNull($policy);
        $this->assertEquals(50, $policy->penalty_value);
        $this->assertEquals(14, $policy->free_cancel_days_before);
        $this->assertFalse($policy->is_default);

        // 2. Set as default
        $this->post(route('settings.cancellation_policies.default', $policy))->assertRedirect();
        $policy->refresh();
        $this->assertTrue($policy->is_default);

        // Previous default should not be default anymore
        $oldDefault = CancellationPolicy::where('code', 'FLEX_48H')->first();
        $this->assertFalse($oldDefault->is_default);

        // 3. Update policy
        $this->put(route('settings.cancellation_policies.update', $policy), [
            'code' => 'TEST_STRICT',
            'name' => 'Stricte 10 jours modifiée',
            'penalty_type' => 'percentage',
            'penalty_value' => '75',
            'free_cancel_days_before' => '10',
            'free_cancel_time' => '14:00',
        ])->assertRedirect();

        $policy->refresh();
        $this->assertEquals('Stricte 10 jours modifiée', $policy->name);
        $this->assertEquals(75, $policy->penalty_value);
        $this->assertEquals(10, $policy->free_cancel_days_before);
    }

    public function test_cancellation_receipt_page_renders_correctly()
    {
        $this->actingAs($this->manager);

        CashRegisterSession::create([
            'user_id' => $this->manager->id,
            'module' => 'reception',
            'opening_amount' => 10000000,
            'opened_at' => now(),
        ]);

        $booking = Booking::create([
            'room_id' => $this->room->id,
            'customer_id' => $this->customer->id,
            'status' => BookingStatus::CONFIRMED,
            'check_in' => now()->addDays(5)->startOfDay(),
            'check_out' => now()->addDays(7)->startOfDay(),
            'adults_count' => 1,
            'total_nights' => 2,
            'price_per_night' => 5000000,
            'total_room_amount' => 10000000,
            'total_amount' => 10000000,
            'deposit_amount' => 5000000,
            'paid_amount' => 5000000,
            'balance_due' => 5000000,
        ]);

        $this->post(route('bookings.cancel', $booking), [
            'reason_code' => 'guest_request',
            'refund_method' => 'cash',
        ]);

        $booking->refresh();

        $receiptResponse = $this->get(route('bookings.cancellation_receipt', $booking));
        $receiptResponse->assertStatus(200);
        $receiptResponse->assertSee($booking->booking_number);
        $receiptResponse->assertSee($booking->cancellation->cancellation_number);
        $receiptResponse->assertSee('Attestation d\'Annulation', false);
    }
}
