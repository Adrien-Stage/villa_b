<?php

/**
 * Workflow interne des banquets à la réception (accueil sur place & téléphone),
 * vérification de disponibilité en temps réel, rattachement CRM / séjour,
 * encaissement en caisse Réception (dont tiroir partagé avec le chef de réception)
 * et traçabilité explicite des origines dans les registres d'encaissements.
 */

use App\Editions\Registres;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\CashRegisterActivity;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PointOfSale;
use App\Models\ReceptionSale;
use App\Models\RestaurantBanquet;
use App\Models\RestaurantBanquetPayment;
use App\Models\RestaurantCustomerOrder;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ShopOrder;
use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\BanquetNotification;
use App\Notifications\CashRegisterThirdPartyActivity;
use App\Services\AccountingService;
use App\Services\PassationService;
use App\Support\Navigation;
use App\Support\PointOfSaleCatalog;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([TenantSeeder::class]);
    RoleCatalog::sync();
    PointOfSaleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'restaurant', 'shop', 'accounting', 'comptabilite']);

    $this->tenant = Tenant::first();
    $this->origine = PointOfSale::where('slug', 'restaurant')->sole();
    $this->kotibe = PointOfSale::create([
        'code' => 'KOT',
        'slug' => 'kotibe',
        'name' => 'Kotibe',
        'kind' => PointOfSale::KIND_RESTAURATION,
        'series_prefix' => 'KOT-',
        'service_modes' => ['carte', 'buffet'],
        'services' => ['salle', 'cuisine', 'bar', 'stock'],
        'is_active' => true,
        'sort_order' => 4,
    ]);

    $this->salleKotibe = Space::create([
        'name' => 'Salle Kotibe',
        'slug' => 'salle-kotibe',
        'capacity' => 80,
        'point_of_sale_id' => $this->kotibe->id,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $this->sallePoly = Space::create([
        'name' => 'Grande Salle Polyvalente',
        'slug' => 'grande-salle-poly',
        'capacity' => 200,
        'point_of_sale_id' => null,
        'is_active' => true,
        'sort_order' => 2,
    ]);

    $creer = function (string $role, array $restaurants = [], ?string $name = null): User {
        $user = User::factory()->create([
            'name' => $name ?? $role,
            'role' => $role,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $user->roles()->sync(Role::where('slug', $role)->pluck('id'));
        if ($restaurants !== []) {
            $user->restaurants()->sync(collect($restaurants)->pluck('id'));
        }

        return $user->fresh('roles');
    };

    $this->receptionniste = $creer('reception', [], 'Amina Réception');
    $this->chefReception = $creer('reception_chief', [], 'Claude ChefRéception');
    $this->respKotibe = $creer('restaurant_manager', [$this->kotibe], 'Hélène RespKotibe');
    $this->chefKotibe = $creer('restaurant_chief', [$this->kotibe], 'Marc ChefKotibe');
    $this->respOrigine = $creer('restaurant_manager', [$this->origine], 'Paul RespOrigine');
    $this->comptable = $creer('accountant', [], 'Éric Comptable');
});

test("la réception et le chef de réception accèdent aux banquets dans le groupe Hôtel sans casser la sidebar", function () {
    $this->actingAs($this->receptionniste);
    expect(Navigation::getActiveGroup('restaurant.banquets.index')['id'])->toBe('hotel')
        ->and(Navigation::getActiveGroup('restaurant.banquets.print')['id'])->toBe('hotel');

    $this->actingAs($this->chefReception);
    expect(Navigation::getActiveGroup('restaurant.banquets.show')['id'])->toBe('hotel');

    $this->actingAs($this->respKotibe);
    expect(Navigation::getActiveGroup('restaurant.banquets.index')['id'])->toBe('restaurant');

    $this->actingAs($this->receptionniste);
    $this->get(route('bookings.index'))
        ->assertOk()
        ->assertSee(route('restaurant.banquets.index'), false);
    $this->get(route('agenda.index'))
        ->assertOk()
        ->assertSee(route('restaurant.banquets.index'), false);

    $html = $this->get(route('restaurant.banquets.index'))->assertOk()->getContent();
    expect($html)
        ->toContain('Vérificateur de disponibilité des salles')
        ->toContain(route('restaurant.banquets.store'));
});

test("le vérificateur de disponibilité distingue libre, occupée (avec chevauchement horaire) et capacité insuffisante", function () {
    $dateCible = today()->addDays(5)->toDateString();

    // Créneau déjà réservé de 12:00 à 16:00 dans la Salle Kotibe (80 places)
    RestaurantBanquet::create([
        'point_of_sale_id' => $this->kotibe->id,
        'reference' => 'BQT-CHECK-001',
        'title' => 'Déjeuner d\'affaires',
        'client_name' => 'Cabinet Delta',
        'event_date' => $dateCible,
        'start_time' => '12:00',
        'end_time' => '16:00',
        'space_id' => $this->salleKotibe->id,
        'covers' => 40,
        'price_per_cover' => 1500000,
        'total_amount' => 60000000,
        'deposit_required' => 15000000,
        'status' => RestaurantBanquet::CONFIRME,
        'source' => RestaurantBanquet::SOURCE_TELEPHONE,
    ]);

    $this->actingAs($this->receptionniste);

    // 1. Créneau chevauchant (13:00-17:00) pour 120 couverts :
    //    Salle Kotibe occupée ; Grande Salle Polyvalente (200 places) libre.
    $this->get(route('restaurant.banquets.index', [
        'check_date' => $dateCible,
        'check_start_time' => '13:00',
        'check_end_time' => '17:00',
        'check_covers' => 120,
    ]))
        ->assertOk()
        ->assertSee('Occupée')
        ->assertSee('BQT-CHECK-001')
        ->assertSee('Libre')
        ->assertSee('Créer un devis sur cette salle');

    // 2. Créneau non chevauchant (18:00-22:00) pour 120 couverts :
    //    Salle Kotibe libre en horaire mais capacité 80 < 120 -> Capacité insuffisante ; Grande Salle libre.
    $this->get(route('restaurant.banquets.index', [
        'check_date' => $dateCible,
        'check_start_time' => '18:00',
        'check_end_time' => '22:00',
        'check_covers' => 120,
    ]))
        ->assertOk()
        ->assertSee('Capacité insuffisante')
        ->assertSee('Demande de 120 couverts supérieure à la capacité (80 places).')
        ->assertSee('Libre');

    // 3. Créneau non chevauchant (18:00-22:00) pour 50 couverts sur Kotibe : libre !
    $this->get(route('restaurant.banquets.index', [
        'check_date' => $dateCible,
        'check_start_time' => '18:00',
        'check_end_time' => '22:00',
        'check_covers' => 50,
        'check_pos_id' => $this->kotibe->id,
    ]))
        ->assertOk()
        ->assertDontSee('Occupée')
        ->assertDontSee('Capacité insuffisante')
        ->assertSee('Salle Kotibe')
        ->assertSee('data-prefill=', false);
});

test("le devis banquet par téléphone crée une fiche CRM liée, valide date/capacité/chevauchement et apparaît dans la fiche client", function () {
    Notification::fake();
    $dateFuture = today()->addWeek()->toDateString();

    $this->actingAs($this->receptionniste);

    // Date passée refusée
    $this->post(route('restaurant.banquets.store'), [
        'point_of_sale_id' => $this->kotibe->id,
        'title' => 'Dîner passé',
        'client_name' => 'Jean Test',
        'event_date' => today()->subDay()->toDateString(),
        'covers' => 20,
        'price_per_cover' => 10000,
    ])->assertSessionHasErrors('event_date');

    // Capacité dépassée refusée
    $this->post(route('restaurant.banquets.store'), [
        'point_of_sale_id' => $this->kotibe->id,
        'space_id' => $this->salleKotibe->id,
        'title' => 'Trop grand',
        'client_name' => 'Jean Test',
        'event_date' => $dateFuture,
        'covers' => 95,
        'price_per_cover' => 10000,
    ])->assertSessionHasErrors('covers');

    // Création par téléphone avec fiche CRM rapide
    $this->post(route('restaurant.banquets.store'), [
        'point_of_sale_id' => $this->kotibe->id,
        'space_id' => $this->salleKotibe->id,
        'title' => 'Cocktail Fondation Mboa',
        'source' => RestaurantBanquet::SOURCE_TELEPHONE,
        'create_customer' => 1,
        'quick_first_name' => 'Clarisse',
        'quick_last_name' => 'Ndongo',
        'client_phone' => '+237 699 00 11 22',
        'client_email' => 'clarisse@fondation.cm',
        'event_date' => $dateFuture,
        'start_time' => '15:00',
        'end_time' => '19:00',
        'covers' => 60,
        'price_per_cover' => 15000,
        'extras_amount' => 100000,
        'deposit_required' => 300000,
        'menu' => 'Buffet prestige + jus locaux',
    ])->assertSessionHasNoErrors();

    $banquet = RestaurantBanquet::where('title', 'Cocktail Fondation Mboa')->sole();
    $customer = Customer::where('first_name', 'Clarisse')->where('last_name', 'Ndongo')->sole();

    expect($banquet->customer_id)->toBe($customer->id)
        ->and($banquet->source)->toBe(RestaurantBanquet::SOURCE_TELEPHONE)
        ->and($banquet->status)->toBe(RestaurantBanquet::DEVIS)
        ->and($banquet->total_amount)->toBe(100000000) // 60 * 15000 + 100000 = 1 000 000 FCFA
        ->and($banquet->deposit_required)->toBe(30000000)
        ->and($customer->tenant_id)->toBe($this->tenant->id);

    // Onglet CRM "Banquets & Événements" + fiche imprimable
    $this->get(route('customers.show', ['customer' => $customer, 'tab' => 'banquets']))
        ->assertOk()
        ->assertSee('Banquets & Événements', false)
        ->assertSee($banquet->reference)
        ->assertSee('Cocktail Fondation Mboa')
        ->assertSee('Téléphone');

    $this->get(route('restaurant.banquets.print', $banquet))
        ->assertOk()
        ->assertSee($banquet->reference)
        ->assertSee('Cocktail Fondation Mboa')
        ->assertSee('Clarisse Ndongo')
        ->assertSee('Salle Kotibe');

    // Notifications envoyées à l'équipe du restaurant Kotibe uniquement (pas à Origine)
    Notification::assertSentTo([$this->respKotibe, $this->chefKotibe], BanquetNotification::class);
    Notification::assertNotSentTo([$this->respOrigine, $this->receptionniste], BanquetNotification::class);

    // Chevauchement bloqué sur le même créneau (16:00-20:00) dans Salle Kotibe
    $this->post(route('restaurant.banquets.store'), [
        'point_of_sale_id' => $this->kotibe->id,
        'space_id' => $this->salleKotibe->id,
        'title' => 'Autre réception',
        'client_name' => 'Marc Autre',
        'event_date' => $dateFuture,
        'start_time' => '16:00',
        'end_time' => '20:00',
        'covers' => 30,
        'price_per_cover' => 10000,
    ])->assertSessionHasErrors('space_id');
});

test("la réception et le chef de réception encaissent l'acompte dans la caisse Réception partagée, confirment le banquet et alimentent passation & clôture", function () {
    Notification::fake();

    // Séjour d'un client résident lié au banquet
    $type = RoomType::create(['name' => 'Suite', 'code' => 'STE', 'base_capacity' => 2, 'max_capacity' => 2, 'base_price' => 4500000]);
    $room = Room::create(['number' => '301', 'room_type_id' => $type->id, 'floor' => 3, 'status' => 'occupied']);
    $resident = Customer::factory()->create(['first_name' => 'Hervé', 'last_name' => 'Kamga', 'tenant_id' => $this->tenant->id]);
    $sejour = Booking::create([
        'room_id' => $room->id,
        'customer_id' => $resident->id,
        'booking_number' => 'BK-RES-BANQ',
        'status' => BookingStatus::CHECKED_IN,
        'check_in' => today()->toDateString(),
        'check_out' => today()->addDays(4)->toDateString(),
        'adults_count' => 1,
        'total_nights' => 4,
        'price_per_night' => 4500000,
        'total_room_amount' => 18000000,
        'total_amount' => 18000000,
        'paid_amount' => 0,
        'balance_due' => 18000000,
    ]);

    // 1. Le réceptionniste ouvre sa caisse Réception avec 20 000 FCFA
    $this->actingAs($this->receptionniste)
        ->post(route('bookings.cash_register.open.store'), ['opening_amount' => '20000'])
        ->assertRedirect(route('bookings.index'));

    $session = CashRegisterSession::where('user_id', $this->receptionniste->id)
        ->where('module', 'reception')
        ->whereNull('closed_at')
        ->sole();

    // 2. Le chef de réception crée le devis sur place dans la caisse partagée du réceptionniste
    $this->actingAs($this->chefReception);
    $this->post(route('restaurant.banquets.store'), [
        'point_of_sale_id' => $this->kotibe->id,
        'space_id' => $this->sallePoly->id,
        'booking_id' => $sejour->id,
        'title' => 'Dîner Gala Kamga',
        'source' => RestaurantBanquet::SOURCE_SUR_PLACE,
        'event_date' => today()->addDays(2)->toDateString(),
        'covers' => 100,
        'price_per_cover' => 20000, // 2 000 000 FCFA
        'deposit_required' => 500000,
    ])->assertSessionHasNoErrors();

    $banquet = RestaurantBanquet::where('title', 'Dîner Gala Kamga')->sole();
    expect($banquet->customer_id)->toBe($resident->id)
        ->and($banquet->booking_id)->toBe($sejour->id);

    // 3. Le chef de réception encaisse l'acompte de 500 000 FCFA en espèces avec confirm_now = 1
    $this->post(route('restaurant.banquets.payments.store', $banquet), [
        'amount' => 500000,
        'payment_method' => 'cash',
        'confirm_now' => 1,
        'notes' => 'Acompte comptoir réception',
    ])->assertSessionHasNoErrors();

    $banquet->refresh();
    $payment = $banquet->payments()->sole();

    expect($banquet->status)->toBe(RestaurantBanquet::CONFIRME)
        ->and($banquet->confirmed_at)->not->toBeNull()
        ->and($payment->reference)->toStartWith('BQT-PAY-')
        ->and($payment->kind)->toBe(RestaurantBanquetPayment::ACOMPTE)
        ->and($payment->cash_register_session_id)->toBe($session->id)
        ->and($payment->paid_by)->toBe($this->chefReception->id)
        ->and($session->fresh()->theoreticalBalance())->toBe(2000000 + 50000000); // 20 000 + 500 000 FCFA

    // 4. Traçabilité tiers pour le réceptionniste titulaire + passation + écran de clôture
    $activities = CashRegisterActivity::where('cash_register_session_id', $session->id)->pluck('action')->all();
    expect($activities)->toContain(CashRegisterActivity::ACTION_BANQUET_CREATE, CashRegisterActivity::ACTION_BANQUET_PAYMENT);

    Notification::assertSentTo($this->receptionniste, CashRegisterThirdPartyActivity::class);
    Notification::assertSentTo([$this->respKotibe, $this->chefKotibe], BanquetNotification::class, function (BanquetNotification $n) {
        return $n->event === BanquetNotification::EVENT_CONFIRMED;
    });

    $rapport = app(PassationService::class)->rapport($session);
    expect($rapport['banquetPaiements'])->toHaveCount(1)
        ->and($rapport['totalEncaisse'])->toBe(50000000)
        ->and($rapport['especes'])->toBe(50000000)
        ->and($rapport['theorique'])->toBe(52000000);

    $this->actingAs($this->receptionniste);
    $this->get(route('bookings.cash_register.close'))
        ->assertOk()
        ->assertSee('Encaissements Banquets Espèces')
        ->assertSee('500 000 FCFA')
        ->assertSee('520 000 FCFA');
});

test("les registres et l'édition imprimable journal-encaissements détaillent clairement l'origine de chaque encaissement (banquets, acomptes, check-in, nuitées, check-out, POS, annulation)", function () {
    $jour = CarbonImmutable::parse('2026-11-10');
    $this->travelTo($jour->setTime(14, 0));

    $type = RoomType::create(['name' => 'Standard', 'code' => 'STD', 'base_capacity' => 2, 'max_capacity' => 2, 'base_price' => 3000000]);
    $ch1 = Room::create(['number' => '101', 'room_type_id' => $type->id, 'floor' => 1, 'status' => 'occupied']);
    $ch2 = Room::create(['number' => '102', 'room_type_id' => $type->id, 'floor' => 1, 'status' => 'occupied']);

    $clientHotel = Customer::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Tchoupo', 'tenant_id' => $this->tenant->id]);
    $sejourA = Booking::create([
        'room_id' => $ch1->id, 'customer_id' => $clientHotel->id, 'booking_number' => 'BK-ORG-01',
        'status' => BookingStatus::CHECKED_IN, 'check_in' => '2026-11-08', 'check_out' => '2026-11-11',
        'actual_check_in' => '2026-11-08 14:00:00',
        'adults_count' => 1, 'total_nights' => 3, 'price_per_night' => 3000000,
        'total_room_amount' => 9000000, 'total_amount' => 9000000, 'paid_amount' => 9000000, 'balance_due' => 0,
    ]);
    $sejourB = Booking::create([
        'room_id' => $ch2->id, 'customer_id' => $clientHotel->id, 'booking_number' => 'BK-ORG-02',
        'status' => BookingStatus::CHECKED_OUT, 'check_in' => '2026-11-09', 'check_out' => '2026-11-10',
        'actual_check_in' => '2026-11-09 13:00:00', 'actual_check_out' => '2026-11-10 11:30:00',
        'adults_count' => 1, 'total_nights' => 1, 'price_per_night' => 3000000,
        'total_room_amount' => 3000000, 'total_amount' => 3000000, 'paid_amount' => 3000000, 'balance_due' => 0,
    ]);

    $receptionSession = CashRegisterSession::create([
        'user_id' => $this->receptionniste->id,
        'module' => 'reception',
        'status' => 'open',
        'opening_amount' => 0,
        'opened_at' => $jour->setTime(7, 0),
    ]);

    // 1. Acompte réservation
    Payment::create([
        'booking_id' => $sejourA->id, 'customer_id' => $clientHotel->id,
        'payment_origin' => Payment::ORIGIN_ACOMPTE_RESERVATION,
        'amount' => 1000000, 'method' => 'cash', 'status' => 'completed',
        'reference' => 'PAY-ACC-1', 'paid_at' => $jour->setTime(8, 0), 'processed_by' => $this->receptionniste->id,
    ]);
    // 2. Check-in
    Payment::create([
        'booking_id' => $sejourA->id, 'customer_id' => $clientHotel->id,
        'payment_origin' => Payment::ORIGIN_CHECK_IN,
        'amount' => 2000000, 'method' => 'cash', 'status' => 'completed',
        'reference' => 'PAY-CIN-1', 'paid_at' => $jour->setTime(9, 0), 'processed_by' => $this->receptionniste->id,
    ]);
    // 3. Séjour / Nuitées
    Payment::create([
        'booking_id' => $sejourA->id, 'customer_id' => $clientHotel->id,
        'payment_origin' => Payment::ORIGIN_SEJOUR_NUITEES,
        'amount' => 3000000, 'method' => 'orange_money', 'status' => 'completed',
        'reference' => 'PAY-SEJ-1', 'paid_at' => $jour->setTime(10, 0), 'processed_by' => $this->receptionniste->id,
    ]);
    // 4. Check-out / Solde départ
    Payment::create([
        'booking_id' => $sejourB->id, 'customer_id' => $clientHotel->id,
        'payment_origin' => Payment::ORIGIN_CHECK_OUT,
        'amount' => 3000000, 'method' => 'cash', 'status' => 'completed',
        'reference' => 'PAY-OUT-1', 'paid_at' => $jour->setTime(11, 30), 'processed_by' => $this->receptionniste->id,
    ]);
    // 5. Facture complémentaire
    $factCompl = Invoice::create([
        'kind' => Invoice::KIND_COMPLEMENTAIRE, 'booking_id' => $sejourB->id, 'customer_id' => $clientHotel->id,
        'invoice_number' => 'F-ORG-99', 'invoice_date' => $jour->toDateString(),
        'subtotal' => 500000, 'tax_amount' => 0, 'total_amount' => 500000, 'paid_amount' => 500000, 'balance_due' => 0, 'status' => 'paid',
    ]);
    Payment::create([
        'booking_id' => $sejourB->id, 'customer_id' => $clientHotel->id, 'invoice_id' => $factCompl->id,
        'payment_origin' => Payment::ORIGIN_FACTURE_COMPLEMENTAIRE,
        'amount' => 500000, 'method' => 'cash', 'status' => 'completed',
        'reference' => 'PAY-FCO-1', 'paid_at' => $jour->setTime(12, 0), 'processed_by' => $this->receptionniste->id,
    ]);
    // 6. POS Réception lié à un Payment
    $posPayment = Payment::create([
        'customer_id' => $clientHotel->id,
        'payment_origin' => Payment::ORIGIN_POS_RECEPTION,
        'amount' => 400000, 'method' => 'cash', 'status' => 'completed',
        'reference' => 'PAY-POS-1', 'paid_at' => $jour->setTime(12, 15), 'processed_by' => $this->receptionniste->id,
    ]);
    ReceptionSale::create([
        'sale_number' => 'POS-ORG-1', 'customer_id' => $clientHotel->id, 'user_id' => $this->receptionniste->id,
        'payment_id' => $posPayment->id, 'cash_register_session_id' => $receptionSession->id,
        'subtotal' => 400000, 'total_amount' => 400000, 'payment_method' => 'cash', 'payment_status' => 'paid',
        'paid_at' => $jour->setTime(12, 15),
    ]);
    // 7. Remboursement annulation
    Payment::create([
        'booking_id' => $sejourA->id, 'customer_id' => $clientHotel->id,
        'payment_origin' => Payment::ORIGIN_REMBOURSEMENT_ANNULATION,
        'amount' => -500000, 'method' => 'cash', 'status' => 'completed',
        'reference' => 'PAY-RMB-1', 'refund_reason' => 'Annulation anticipée', 'paid_at' => $jour->setTime(12, 30), 'processed_by' => $this->receptionniste->id,
    ]);
    // 8. Banquet (Acompte encaissé en Caisse Réception + Solde encaissé au restaurant)
    $banquet = RestaurantBanquet::create([
        'point_of_sale_id' => $this->kotibe->id, 'space_id' => $this->salleKotibe->id,
        'reference' => 'BQT-2026-901', 'title' => 'Mariage Biya', 'client_name' => 'Famille Biya',
        'event_date' => $jour->toDateString(), 'covers' => 80, 'price_per_cover' => 1000000,
        'total_amount' => 80000000, 'deposit_required' => 25000000, 'status' => RestaurantBanquet::CONFIRME,
        'source' => RestaurantBanquet::SOURCE_SUR_PLACE,
    ]);
    RestaurantBanquetPayment::create([
        'restaurant_banquet_id' => $banquet->id, 'reference' => 'BQT-PAY-2026-9001',
        'kind' => RestaurantBanquetPayment::ACOMPTE, 'amount' => 25000000, 'payment_method' => 'cash',
        'cash_register_session_id' => $receptionSession->id, 'paid_by' => $this->receptionniste->id,
        'paid_at' => $jour->setTime(13, 0),
    ]);
    RestaurantBanquetPayment::create([
        'restaurant_banquet_id' => $banquet->id, 'reference' => 'BQT-PAY-2026-9002',
        'kind' => RestaurantBanquetPayment::SOLDE, 'amount' => 55000000, 'payment_method' => 'transfer',
        'paid_by' => $this->respKotibe->id, 'paid_at' => $jour->setTime(13, 30),
    ]);
    // 9. Restaurant note de table + Boutique comptoir
    RestaurantCustomerOrder::create([
        'point_of_sale_id' => $this->kotibe->id, 'table_number' => '12', 'status' => 'served',
        'payment_status' => 'paid', 'payment_method' => 'cash', 'total_amount' => 1500000, 'amount_paid' => 1500000,
        'placed_at' => $jour->setTime(13, 40), 'paid_at' => $jour->setTime(13, 45), 'paid_by' => $this->respKotibe->id,
    ]);
    ShopOrder::create([
        'order_number' => 'BTQ-ORG-1', 'total_items' => 1, 'subtotal' => 700000, 'tax_amount' => 0, 'total_amount' => 700000,
        'payment_status' => 'paid', 'payment_method' => 'cash', 'paid_at' => $jour->setTime(13, 50), 'created_by' => $this->receptionniste->id,
    ]);

    $lignes = app(Registres::class)->encaissements($jour, $jour);
    $details = $lignes->pluck('detail')->all();

    expect($details)->toContain(
        'Acompte réservation · Ch. 101',
        'Check-in · Ch. 101',
        'Séjour / Nuitées · Ch. 101',
        'Check-out / Solde départ · Ch. 102',
        'Facture complémentaire · Ch. 102',
        'POS Réception — Vente comptoir #POS-ORG-1',
        'Remboursement annulation · Ch. 101',
        'Banquet — Acompte — Mariage Biya (Salle Kotibe) [Caisse Réception]',
        'Banquet — Solde — Mariage Biya (Salle Kotibe)',
        'Restaurant — Note de table · Table 12',
        'Boutique — Vente comptoir'
    );

    // Le journal des encaissements imprimable (écran + print) restitue chaque origine
    $this->actingAs($this->comptable);
    $this->get(route('editions.show', ['edition' => 'journal-encaissements', 'du' => $jour->toDateString(), 'au' => $jour->toDateString()]))
        ->assertOk()
        ->assertSee('Objet / Nature')
        ->assertSee('Banquet — Acompte — Mariage Biya (Salle Kotibe) [Caisse Réception]')
        ->assertSee('Check-in · Ch. 101')
        ->assertSee('Check-out / Solde départ · Ch. 102');

    $this->get(route('editions.print', ['edition' => 'journal-encaissements', 'du' => $jour->toDateString(), 'au' => $jour->toDateString()]))
        ->assertOk()
        ->assertSee('Banquet — Solde — Mariage Biya (Salle Kotibe)')
        ->assertSee('Acompte réservation · Ch. 101')
        ->assertSee('Facture complémentaire · Ch. 102');

    // Le cahier recettes & dépenses indique également l'origine de chaque règlement d'hébergement
    $journalLibelles = app(AccountingService::class)
        ->journal($jour->startOfDay(), $jour->endOfDay())
        ->pluck('libelle')
        ->all();

    expect($journalLibelles)->toContain(
        'Hébergement (Acompte réservation) — Séjour BK-ORG-01',
        'Hébergement (Check-in) — Séjour BK-ORG-01',
        'Hébergement (Séjour / Nuitées) — Séjour BK-ORG-01',
        'Hébergement (Check-out / Solde départ) — Séjour BK-ORG-02',
        'Hébergement (Facture complémentaire) — Séjour BK-ORG-02'
    );
});
