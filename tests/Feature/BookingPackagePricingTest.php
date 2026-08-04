<?php

use App\Models\Customer;
use App\Models\Room;
use App\Models\RoomPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Répercussion d'une formule sur le montant affiché à la dernière étape.
 *
 * Le serveur facturait déjà la formule et déduisait sa remise, mais l'écran
 * de confirmation n'en montrait rien : le bloc des formules vivait dans son
 * propre périmètre Alpine, isolé du calcul des totaux. Choisir une formule ne
 * produisait donc aucun signal — et le réceptionniste découvrait l'écart au
 * moment où l'acompte minimum était refusé.
 *
 * Ces tests portent sur ce que la vue transmet au calcul : c'est là que se
 * jouait la rupture.
 */

function pricingSetup(): array
{
    test()->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);

    $user = User::factory()->create(['role' => 'manager', 'is_active' => true]);

    \App\Models\CashRegisterSession::create([
        'user_id'        => $user->id,
        'module'         => 'reception',
        'opening_amount' => 5000000,
        'opened_at'      => now(),
    ]);

    test()->actingAs($user);

    $customer = Customer::create(['first_name' => 'Aminatou', 'last_name' => 'Njoya']);

    // Chambre 101 : 45 000 FCFA la nuit.
    return [$user, Room::where('number', '101')->first(), $customer];
}

/** Charge l'écran de confirmation pour un séjour de 2 nuits. */
function ecranConfirmation(Room $room, Customer $customer): string
{
    return test()->post(route('bookings.store'), [
        'step'           => '3',
        'room_id'        => $room->id,
        'customer_id'    => $customer->id,
        'check_in'       => now()->addDay()->format('Y-m-d'),
        'check_out'      => now()->addDays(3)->format('Y-m-d'),
        'adults_count'   => 2,
        'children_count' => 0,
        'source'         => 'direct',
    ])->assertOk()->getContent();
}

test('le bloc des formules ne vit plus dans un périmètre isolé', function () {
    [, $room, $customer] = pricingSetup();

    RoomPackage::create([
        'name' => 'Demi-pension', 'pricing_mode' => 'per_room_night', 'price' => 1500000,
        'room_discount_type' => 'none', 'room_discount_value' => 0,
        'meals' => ['breakfast'], 'service_item_ids' => [], 'room_type_ids' => [],
        'is_active' => true, 'sort_order' => 0,
    ]);

    $html = ecranConfirmation($room, $customer);

    // C'était la cause : un x-data imbriqué qui redéclarait « packs » et
    // « selected », coupant la sélection du calcul des totaux.
    expect($html)->not->toContain('x-data="{ packs:')
        ->and($html)->toContain('x-model="selectedPackage"');
});

test('la sélection déclenche le recalcul', function () {
    [, $room, $customer] = pricingSetup();

    RoomPackage::create([
        'name' => 'Demi-pension', 'pricing_mode' => 'per_room_night', 'price' => 1500000,
        'room_discount_type' => 'none', 'room_discount_value' => 0,
        'meals' => ['breakfast'], 'service_item_ids' => [], 'room_type_ids' => [],
        'is_active' => true, 'sort_order' => 0,
    ]);

    $html = ecranConfirmation($room, $customer);

    // Sans ce déclencheur sur les boutons radio, la formule serait bien dans
    // le bon périmètre mais rien ne rafraîchirait le total.
    expect(substr_count($html, '@change="updateCalculations()"'))->toBeGreaterThanOrEqual(2);
});

test('le détail du montant dû est affiché', function () {
    [, $room, $customer] = pricingSetup();

    RoomPackage::create([
        'name' => 'welcome', 'pricing_mode' => 'per_room_night', 'price' => 4000000,
        'room_discount_type' => 'percent', 'room_discount_value' => 10,
        'meals' => ['breakfast'], 'service_item_ids' => [], 'room_type_ids' => [],
        'is_active' => true, 'sort_order' => 0,
    ]);

    $html = ecranConfirmation($room, $customer);

    expect($html)->toContain('Hébergement négocié')
        ->and($html)->toContain('Total dû pour le séjour')
        ->and($html)->toContain('Remise incluse dans la formule');
});

test('la formule transmet de quoi recalculer sa remise sur le prix négocié', function () {
    [, $room, $customer] = pricingSetup();

    RoomPackage::create([
        'name' => 'welcome', 'pricing_mode' => 'per_room_night', 'price' => 4000000,
        'room_discount_type' => 'percent', 'room_discount_value' => 10,
        'meals' => ['breakfast'], 'service_item_ids' => [], 'room_type_ids' => [],
        'is_active' => true, 'sort_order' => 0,
    ]);

    $html = ecranConfirmation($room, $customer);

    // Sans le type et la valeur bruts, un pourcentage resterait assis sur le
    // tarif de base et divergerait du serveur dès que la réception négocie.
    // @js() écrit le JSON avec des séquences d'échappement unicode. On les
    // décode pour raisonner sur les valeurs plutôt que sur leur encodage.
    $json = preg_replace_callback(
        '/\\\\u([0-9a-fA-F]{4})/',
        fn ($m) => mb_chr(hexdec($m[1])),
        $html
    );

    expect($json)->toContain('"discount_type":"percent"')
        ->and($json)->toContain('"amount":80000')        // 40 000 × 2 nuits
        ->and($json)->toContain('"discount_value":10');
});

test('l\'acompte et le plafond de paiement suivent le total, formule comprise', function () {
    [, $room, $customer] = pricingSetup();

    RoomPackage::create([
        'name' => 'welcome', 'pricing_mode' => 'per_room_night', 'price' => 4000000,
        'room_discount_type' => 'none', 'room_discount_value' => 0,
        'meals' => ['breakfast'], 'service_item_ids' => [], 'room_type_ids' => [],
        'is_active' => true, 'sort_order' => 0,
    ]);

    $html = ecranConfirmation($room, $customer);

    // Borner le versement au seul prix négocié empêcherait de régler
    // l'intégralité d'un séjour comprenant une formule.
    expect($html)->toContain(':max="netTotal"')
        ->and($html)->toContain('paymentAmount > netTotal')
        ->and($html)->not->toContain('paymentAmount > customPrice');
});

test('le composant reçoit les formules, la remise partenaire et les nuitées', function () {
    [, $room, $customer] = pricingSetup();

    $html = ecranConfirmation($room, $customer);

    // La signature doit porter les trois : sans les nuitées, une remise « au
    // montant par nuitée » serait comptée une seule fois.
    expect($html)->toMatch('/paymentCalc\([^)]*,[^)]*,[^)]*,[^)]*,[^)]*,\s*2\)/');
});
