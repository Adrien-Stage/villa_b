<?php

use App\Enums\BookingStatus;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingDraft;
use App\Models\CashRegisterActivity;
use App\Models\CashRegisterDisbursement;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Department;
use App\Models\GroupBooking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ReceptionSale;
use App\Models\Role;
use App\Models\Room;
use App\Models\ServiceItem;
use App\Models\ShiftAssignment;
use App\Models\ShiftHandover;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\CashRegisterThirdPartyActivity;
use App\Services\CashRegisterCircuit;
use App\Support\CashClosurePolicy;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'accounting', 'comptabilite']);

    $this->tenant = Tenant::first();
    $this->tenant->update([
        'settings' => [
            'reception' => [
                'max_discount_percentage' => 15,
                'min_deposit_percentage' => 30,
            ],
        ],
    ]);

    $this->room = Room::orderBy('number')->first();
    $this->secondRoom = Room::orderBy('number')->skip(1)->first() ?? $this->room;
    $this->room->roomType->update(['base_price' => 5000000]); // 50 000 FCFA
    $this->secondRoom->roomType->update(['base_price' => 5000000]);

    $this->receptionist = creerAgent('Amina Réception', 'reception');
    $this->colleague = creerAgent('Boris Réception', 'reception');
    $this->chief = creerAgent('Claude ChefRéception', 'reception_chief');
    $this->manager = creerAgent('Diana Manager', 'manager');
    $this->comptable = creerAgent('Éric Comptable', 'accountant');

    // Réceptionniste ouvre la caisse réception avec 10 000 FCFA
    $this->actingAs($this->receptionist)
        ->post(route('bookings.cash_register.open.store'), ['opening_amount' => '10000'])
        ->assertRedirect(route('bookings.index'));

    $this->session = CashRegisterSession::where('user_id', $this->receptionist->id)
        ->where('module', 'reception')
        ->whereNull('closed_at')
        ->sole();
});

function creerAgent(string $name, string $roleSlug): User
{
    $user = User::factory()->create([
        'name' => $name,
        'role' => $roleSlug,
        'is_active' => true,
    ]);

    if ($roleId = Role::where('slug', $roleSlug)->value('id')) {
        $user->roles()->sync([$roleId]);
    }

    return $user->fresh('roles');
}

test("le chef de réception voit la caisse ouverte par le réceptionniste sans blocage caisse fermée ni bouton ouvrir/clôturer", function () {
    $customer = Customer::factory()->create();
    $booking = Booking::create([
        'room_id' => $this->room->id,
        'customer_id' => $customer->id,
        'booking_number' => 'BK-CHIEF-001',
        'status' => BookingStatus::CHECKED_IN,
        'check_in' => now()->toDateString(),
        'check_out' => now()->addDays(2)->toDateString(),
        'adults_count' => 1,
        'total_nights' => 2,
        'price_per_night' => 5000000,
        'total_room_amount' => 10000000,
        'total_amount' => 10000000,
        'deposit_amount' => 3000000,
        'paid_amount' => 3000000,
        'balance_due' => 7000000,
        'source' => 'direct',
    ]);

    $editableBooking = Booking::create([
        'room_id' => $this->secondRoom->id,
        'customer_id' => $customer->id,
        'booking_number' => 'BK-CHIEF-002',
        'status' => BookingStatus::CONFIRMED,
        'check_in' => now()->addDays(5)->toDateString(),
        'check_out' => now()->addDays(7)->toDateString(),
        'adults_count' => 1,
        'total_nights' => 2,
        'price_per_night' => 5000000,
        'total_room_amount' => 10000000,
        'total_amount' => 10000000,
        'deposit_amount' => 3000000,
        'paid_amount' => 3000000,
        'balance_due' => 7000000,
        'source' => 'direct',
    ]);

    $this->actingAs($this->chief);

    // Pages principales de réception
    foreach ([
        route('bookings.index') => true,
        route('bookings.show', $booking) => true,
        route('bookings.create') => false,
        route('bookings.edit', $editableBooking) => false,
        route('groups.index') => true,
    ] as $url => $hasBoolProp) {
        $response = $this->get($url)->assertOk();
        if ($hasBoolProp) {
            $response->assertViewHas('isCashRegisterOpen', true);
        }
        $response->assertViewHas('cashRegisterContext', fn (array $ctx) => ($ctx['isOpen'] ?? false) === true && ($ctx['estTitulaire'] ?? true) === false);
        $text = texteAffiche($response->getContent());
        expect($text)
            ->toContain('Caisse ouverte par Amina Réception')
            ->not->toContain('Caisse fermée')
            ->not->toContain('Caisse de réception fermée')
            ->not->toContain('Ouvrir ma caisse')
            ->not->toContain('Ouvrir la caisse');
    }

    // Index des caisses : identifie le titulaire sans proposer d'ouvrir un doublon ni de fermer la caisse du collègue
    $indexText = texteAffiche($this->get(route('bookings.cash_register.index'))->assertOk()->getContent());
    expect($indexText)
        ->toContain('Caisse active : Amina Réception')
        ->not->toContain('Ouvrir une caisse')
        ->not->toContain('Fermer ma caisse')
        ->not->toContain('Remettre le tiroir');

    // Mode POS Réception : caisse prête et sans bouton « Fermer la caisse »
    $posText = texteAffiche($this->get(route('reception.pos.index'))->assertOk()->getContent());
    expect($posText)
        ->toContain('Caisse ouverte par Amina Réception')
        ->not->toContain('Caisse fermée')
        ->not->toContain('Fermer la caisse');

    // Brouillon de réservation : accessible sans bannière caisse fermée
    $draft = BookingDraft::create([
        'created_by' => $this->chief->id,
        'tenant_id' => $this->tenant->id,
        'current_step' => 3,
        'customer_id' => $customer->id,
        'room_id' => $this->secondRoom->id,
        'check_in' => now()->addDays(4)->toDateString(),
        'check_out' => now()->addDays(6)->toDateString(),
        'adults' => 1,
    ]);
    $draftText = texteAffiche($this->get(route('bookings.drafts.resume', $draft->token))->assertOk()->getContent());
    expect($draftText)
        ->toContain('Caisse ouverte par Amina Réception')
        ->not->toContain('Caisse fermée');
    $this->get(route('bookings.drafts.continue', $draft->token))->assertOk();
});

test("seul le titulaire peut ouvrir un second tiroir, compter/fermer, remettre en passation ou mettre en pause à la déconnexion", function () {
    $this->actingAs($this->chief);

    // Ouverture bloquée car une caisse réception est déjà active
    $this->get(route('bookings.cash_register.open'))
        ->assertRedirect(route('bookings.index'))
        ->assertSessionHas('info');
    $this->post(route('bookings.cash_register.open.store'), ['opening_amount' => '25000'])
        ->assertRedirect(route('bookings.index'))
        ->assertSessionHas('info');
    expect(CashRegisterSession::count())->toBe(1);

    // Comptage / fermeture : interdit au tiers (404 sur l'écran et le POST)
    $this->get(route('bookings.cash_register.close'))->assertStatus(404);
    $this->post(route('bookings.cash_register.close.store'), ['actual_closing_amount' => '10000'])->assertStatus(404);

    // Remise en passation : refusée au tiers
    $this->get(route('bookings.handovers.create'))
        ->assertRedirect(route('bookings.cash_register.index'))
        ->assertSessionHas('error');
    $this->post(route('bookings.handovers.store'), ['handed_amount' => 10000, 'instructions' => 'Test'])
        ->assertRedirect(route('bookings.cash_register.index'))
        ->assertSessionHas('error');

    // Déconnexion du chef de réception : ne met PAS la caisse du réceptionniste en pause
    $this->post(route('logout'))->assertRedirect('/');
    expect($this->session->fresh()->status)->toBe('open');
    expect($this->session->fresh()->closed_at)->toBeNull();
});

test("les opérations du chef de réception alimentent la caisse du réceptionniste avec traçabilité complète et notification", function () {
    $customer = Customer::factory()->create(['first_name' => 'Koffi', 'last_name' => 'Mensah']);

    $this->actingAs($this->chief);

    // 1. Création de réservation + acompte espèces 15 000 FCFA
    $this->post(route('bookings.store'), [
        'step' => '4',
        'room_id' => $this->room->id,
        'customer_id' => $customer->id,
        'check_in' => now()->toDateString(),
        'check_out' => now()->addDay()->toDateString(),
        'adults_count' => 1,
        'source' => 'direct',
        'custom_price' => '50000',
        'payment_amount' => '15000',
        'payment_method' => 'cash',
    ])->assertRedirect();

    $booking = Booking::latest('id')->first();
    expect($booking)->not->toBeNull();
    $depositPayment = Payment::where('booking_id', $booking->id)->sole();
    expect($depositPayment->cash_register_session_id)->toBe($this->session->id)
        ->and($depositPayment->processed_by)->toBe($this->chief->id)
        ->and($depositPayment->amount)->toBe(1500000);

    // 2. Check-in du séjour
    $this->post(route('bookings.checkIn', $booking), [
        'checkin_code' => $booking->checkin_code,
        'id_document_type' => 'CNI',
        'id_document_number' => 'CM-123456',
    ])->assertRedirect();
    expect($booking->fresh()->status)->toBe(BookingStatus::CHECKED_IN);

    // 3. Ajout d'une prestation au folio (non monétaire en caisse mais tracé)
    $this->post(route('bookings.folio.add', $booking), [
        'type' => 'minibar',
        'description' => 'Eau minérale & jus',
        'quantity' => 2,
        'unit_price' => 2000,
    ])->assertRedirect();

    // 4. Encaissement MTN MoMo 8 000 FCFA (hors espèces)
    $this->post(route('bookings.payment.add', $booking), [
        'amount' => '8000',
        'method' => 'mtn_momo',
    ])->assertRedirect();

    $momoPayment = Payment::where('booking_id', $booking->id)->orderByDesc('id')->first();
    expect($momoPayment->cash_register_session_id)->toBe($this->session->id)
        ->and($momoPayment->processed_by)->toBe($this->chief->id)
        ->and($momoPayment->method)->toBe('mtn_momo')
        ->and($momoPayment->amount)->toBe(800000);

    // 5. Vente POS réception comptant espèces 4 000 FCFA
    $service = ServiceItem::create([
        'name' => 'Petit déjeuner express',
        'category' => 'breakfast',
        'price' => 400000,
        'is_active' => true,
    ]);
    $this->post(route('reception.pos.sales.store'), [
        'client_mode' => 'customer',
        'customer_name' => 'Visiteur POS',
        'payment_method' => 'cash',
        'items' => [[
            'service_item_id' => $service->id,
            'name' => $service->name,
            'category' => $service->category,
            'quantity' => 1,
            'unit_price' => 4000,
        ]],
    ])->assertRedirect();

    $posSale = ReceptionSale::latest('id')->first();
    expect($posSale->cash_register_session_id)->toBe($this->session->id)
        ->and($posSale->user_id)->toBe($this->chief->id)
        ->and($posSale->total_amount)->toBe(400000);

    // 6. Décaissement espèces 3 000 FCFA
    $this->post(route('bookings.cash_register.disbursements.store'), [
        'amount' => '3000',
        'reason' => 'Courses urgentes réception',
    ])->assertRedirect();

    $disbursement = CashRegisterDisbursement::latest('id')->first();
    expect($disbursement->cash_register_session_id)->toBe($this->session->id)
        ->and($disbursement->user_id)->toBe($this->chief->id)
        ->and($disbursement->amount)->toBe(300000);

    // 7. Encaissement d'une facture complémentaire 2 500 FCFA en espèces
    $complementaryInvoice = Invoice::create([
        'kind' => Invoice::KIND_COMPLEMENTAIRE,
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'F-2026-000901',
        'invoice_date' => now()->toDateString(),
        'subtotal' => 250000,
        'tax_amount' => 0,
        'total_amount' => 250000,
        'paid_amount' => 0,
        'balance_due' => 250000,
        'status' => 'sent',
    ]);
    $this->post(route('invoices.payments.store', $complementaryInvoice), [
        'amount' => '2500',
        'method' => 'cash',
    ])->assertRedirect();

    $invoicePayment = Payment::where('invoice_id', $complementaryInvoice->id)->sole();
    expect($invoicePayment->cash_register_session_id)->toBe($this->session->id)
        ->and($invoicePayment->processed_by)->toBe($this->chief->id)
        ->and($invoicePayment->amount)->toBe(250000);

    // 8. Encaissement groupe en espèces 5 000 FCFA
    $group = GroupBooking::create([
        'group_number' => 'GRP-2026-001',
        'group_name' => 'Séminaire Wetchah',
        'contact_customer_id' => $customer->id,
        'event_type' => 'conference',
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(12)->toDateString(),
        'status' => 'confirmed',
        'total_amount' => 10000000,
        'deposit_amount' => 0,
        'paid_amount' => 0,
        'balance_due' => 10000000,
        'created_by' => $this->manager->id,
    ]);
    $groupBookingRoom = Booking::create([
        'room_id' => $this->secondRoom->id,
        'customer_id' => $customer->id,
        'group_booking_id' => $group->id,
        'booking_number' => 'BK-GRP-001',
        'status' => BookingStatus::CHECKED_IN,
        'check_in' => now()->addDays(10)->toDateString(),
        'check_out' => now()->addDays(12)->toDateString(),
        'adults_count' => 1,
        'total_nights' => 2,
        'price_per_night' => 5000000,
        'total_room_amount' => 10000000,
        'total_amount' => 10000000,
        'deposit_amount' => 0,
        'paid_amount' => 0,
        'balance_due' => 10000000,
        'source' => 'direct',
    ]);
    $this->post(route('groups.payment.add', $group), [
        'amount' => '5000',
        'method' => 'cash',
        'distribution' => 'equal',
    ])->assertRedirect();

    $groupPayment = Payment::where('booking_id', $groupBookingRoom->id)->sole();
    expect($groupPayment->cash_register_session_id)->toBe($this->session->id)
        ->and($groupPayment->processed_by)->toBe($this->chief->id)
        ->and($groupPayment->amount)->toBe(500000);

    // 9. Annulation d'une réservation future avec remboursement espèces 6 000 FCFA
    $refundableBooking = Booking::create([
        'room_id' => $this->secondRoom->id,
        'customer_id' => $customer->id,
        'booking_number' => 'BK-REFUND-001',
        'status' => BookingStatus::CONFIRMED,
        'check_in' => now()->addDays(20)->startOfDay(),
        'check_out' => now()->addDays(22)->startOfDay(),
        'adults_count' => 1,
        'total_nights' => 2,
        'price_per_night' => 5000000,
        'total_room_amount' => 10000000,
        'total_amount' => 10000000,
        'deposit_amount' => 600000, // 6 000 FCFA
        'paid_amount' => 600000,
        'balance_due' => 9400000,
        'source' => 'direct',
    ]);
    $this->post(route('bookings.cancel', $refundableBooking), [
        'reason_code' => 'guest_request',
        'reason_description' => 'Empêchement client',
        'refund_method' => 'cash',
    ])->assertRedirect();

    $refundPayment = Payment::where('booking_id', $refundableBooking->id)
        ->where('amount', '<', 0)
        ->sole();
    expect($refundPayment->cash_register_session_id)->toBe($this->session->id)
        ->and($refundPayment->processed_by)->toBe($this->chief->id)
        ->and($refundPayment->amount)->toBe(-600000);

    // Vérification du solde théorique exact :
    // 10 000 (fond) + 15 000 (acompte) + 4 000 (POS) - 3 000 (sortie) + 2 500 (facture) + 5 000 (groupe) - 6 000 (remboursement)
    // = 27 500 FCFA (2 750 000 centimes) — les 8 000 MTN MoMo n'altèrent pas les espèces.
    expect($this->session->fresh()->theoreticalBalance())->toBe(2750000);

    // Traçabilité dédiée (activities + audit log + notification au titulaire)
    $activities = CashRegisterActivity::where('cash_register_session_id', $this->session->id)
        ->where('user_id', $this->chief->id)
        ->get();
    expect($activities->pluck('action')->all())->toContain(
        CashRegisterActivity::ACTION_BOOKING_CREATE,
        CashRegisterActivity::ACTION_BOOKING_CHECKIN,
        CashRegisterActivity::ACTION_FOLIO_ADD,
        CashRegisterActivity::ACTION_PAYMENT,
        CashRegisterActivity::ACTION_POS_SALE,
        CashRegisterActivity::ACTION_DISBURSEMENT,
        CashRegisterActivity::ACTION_INVOICE_PAYMENT,
        CashRegisterActivity::ACTION_GROUP_PAYMENT,
        CashRegisterActivity::ACTION_BOOKING_CANCEL,
    );

    expect(AuditLog::where('event_type', 'cash_register_third_party_activity')
        ->where('user_id', $this->chief->id)
        ->count())->toBe($activities->count());

    $notif = $this->receptionist->notifications()
        ->where('type', CashRegisterThirdPartyActivity::class)
        ->latest('id')
        ->first();
    expect($notif)->not->toBeNull()
        ->and($notif->data['actor_id'])->toBe($this->chief->id)
        ->and($notif->data['actor_name'])->toBe('Claude ChefRéception')
        ->and($notif->data['session_id'])->toBe($this->session->id);

    // 10. Visibilité pour le réceptionniste titulaire (bannière, show, caisses, POS, passation, fermeture, contrôle comptable)
    $this->actingAs($this->receptionist);

    $bookingsIndexText = texteAffiche($this->get(route('bookings.index'))->assertOk()->getContent());
    expect($bookingsIndexText)
        ->toContain('Intervention dans votre caisse')
        ->toContain('Claude ChefRéception')
        ->toContain('8 000 FCFA hors espèces');

    $showText = texteAffiche($this->get(route('bookings.show', $booking))->assertOk()->getContent());
    expect($showText)
        ->toContain('Intervention dans votre caisse')
        ->toContain('par Claude ChefRéception');

    $cashIndexText = texteAffiche($this->get(route('bookings.cash_register.index'))->assertOk()->getContent());
    expect($cashIndexText)
        ->toContain('Intervention tiers')
        ->toContain('Claude ChefRéception');

    $posHolderText = texteAffiche($this->get(route('reception.pos.index'))->assertOk()->getContent());
    expect($posHolderText)
        ->toContain('Activité tiers')
        ->toContain('Claude ChefRéception');

    // Écran de passation : le titulaire voit les interventions du tiers dans son tiroir
    $receptionDept = Department::where('code', 'REC')->first();
    $nightShift = WorkShift::where('name', 'Nuit')->first();
    if ($receptionDept && $nightShift) {
        ShiftAssignment::create([
            'user_id' => $this->colleague->id,
            'work_shift_id' => $nightShift->id,
            'department_id' => $receptionDept->id,
            'date' => now()->toDateString(),
        ]);
    }
    $handoverText = texteAffiche($this->get(route('bookings.handovers.create'))->assertOk()->getContent());
    expect($handoverText)
        ->toContain("Interventions d'un tiers dans cette caisse")
        ->toContain('Claude ChefRéception')
        ->toContain('Intervenant tiers : Claude ChefRéception');

    // Écran de fermeture : le titulaire voit le détail du tiers et le solde théorique exact (27 500 FCFA)
    $closeText = texteAffiche($this->get(route('bookings.cash_register.close'))->assertOk()->getContent());
    expect($closeText)
        ->toContain("Interventions d'un tiers dans votre caisse")
        ->toContain('Claude ChefRéception')
        ->toContain('27 500 FCFA');

    // Le titulaire compte sa caisse -> contrôle comptable voit aussi l'intervention du chef de réception
    $this->post(route('bookings.cash_register.close.store'), [
        'actual_closing_amount' => '27500',
        'closing_notes' => 'Comptage conforme après intervention chef',
    ])->assertRedirect();

    $this->actingAs($this->comptable);
    $reviewText = texteAffiche($this->get(route('accounting.cash_reviews'))->assertOk()->getContent());
    expect($reviewText)
        ->toContain('Amina Réception')
        ->toContain('Claude ChefRéception')
        ->toContain('27 500');
});

test("l'isolation des autres rôles reste intacte : un autre réceptionniste ou le manager voient toujours leur propre caisse fermée", function () {
    $customer = Customer::factory()->create();

    foreach ([$this->colleague, $this->manager] as $otherUser) {
        $this->actingAs($otherUser);

        $response = $this->get(route('bookings.index'))->assertOk();
        $response->assertViewHas('isCashRegisterOpen', false);
        expect(texteAffiche($response->getContent()))
            ->toContain('Caisse de réception fermée')
            ->not->toContain('Caisse ouverte par Amina Réception');

        $this->get(route('bookings.create'))->assertRedirect(route('bookings.cash_register.open'));
        $this->post(route('bookings.store'), [
            'step' => '4',
            'room_id' => $this->room->id,
            'customer_id' => $customer->id,
            'check_in' => now()->addDays(1)->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults_count' => 1,
            'custom_price' => '50000',
            'payment_amount' => '15000',
            'payment_method' => 'cash',
        ])->assertRedirect(route('bookings.cash_register.open'));
    }
});

test("le partage s'arrête dès que la caisse du réceptionniste passe en pause ou en attente de contrôle comptable", function () {
    $customer = Customer::factory()->create();

    // Passage en pause (déconnexion du titulaire)
    $this->session->update(['status' => 'paused']);

    $this->actingAs($this->chief);
    $pausedResponse = $this->get(route('bookings.index'))->assertOk();
    $pausedResponse->assertViewHas('isCashRegisterOpen', false);
    expect(texteAffiche($pausedResponse->getContent()))->toContain('Caisse de réception fermée');
    $this->get(route('bookings.create'))->assertRedirect(route('bookings.cash_register.open'));

    // Passage en attente de contrôle comptable (pending_review)
    $this->session->update(['status' => CashClosurePolicy::STATUS_PENDING_REVIEW]);

    $reviewResponse = $this->get(route('bookings.index'))->assertOk();
    $reviewResponse->assertViewHas('isCashRegisterOpen', false);
    $this->post(route('reception.pos.sales.store'), [
        'client_mode' => 'customer',
        'customer_name' => 'Test bloqué',
        'payment_method' => 'cash',
        'items' => [[
            'name' => 'Café',
            'category' => 'beverage',
            'quantity' => 1,
            'unit_price' => 1000,
        ]],
    ])->assertSessionHasErrors('caisse');
});
