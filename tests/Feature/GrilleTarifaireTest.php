<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use App\Services\TarifSejour;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * La grille tarifaire des chambres (Hôtel › Tarifs) : chaque nuit prend le
 * prix de base de son type, ou le tarif par critère qui s'y applique — la
 * plus haute priorité, puis événement, saison, jours de la semaine, long
 * séjour, dernière minute. La réservation reprend ce tarif comme référence.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients']);
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00'));
    $this->type = RoomType::create(['name' => 'Standard', 'code' => 'STDG', 'base_capacity' => 2, 'max_capacity' => 3, 'base_price' => 4_000_000, 'is_active' => true]);
});

function tarif(RoomType $type, array $champs): RoomRate
{
    return RoomRate::create($champs + [
        'room_type_id' => $type->id, 'name' => 'Tarif', 'price_adjustment_type' => RoomRate::AJUSTEMENT_POURCENTAGE,
        'is_active' => true, 'priority' => 1,
    ]);
}

function sejour(RoomType $type, string $arrivee, string $depart, int $adultes = 2): array
{
    return app(TarifSejour::class)->calculer($type->fresh(), CarbonImmutable::parse($arrivee), CarbonImmutable::parse($depart), $adultes);
}

function personneAuRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test('sans tarif par critère, chaque nuit est au prix de base', function () {
    $calcul = sejour($this->type, '2026-12-10', '2026-12-13');

    expect($calcul['total'])->toBe(12_000_000)->and($calcul['par_nuit'])->toBe(4_000_000)->and($calcul['ajuste'])->toBeFalse();
});

test('week-end, saison et événement : la priorité, puis la préséance du critère', function () {
    // Vendredi 18 et samedi 19 décembre 2026, puis dimanche 20 : début de saison.
    tarif($this->type, ['name' => 'Week-end', 'rate_type' => RoomRate::TYPE_WEEKLY, 'days_of_week' => [5, 6], 'price_adjustment' => 25]);
    tarif($this->type, ['name' => 'Fêtes', 'rate_type' => RoomRate::TYPE_SEASONAL, 'start_date' => '2026-12-20', 'end_date' => '2027-01-05', 'price_adjustment' => 20]);
    $fespam = tarif($this->type, ['name' => 'FESPAM', 'rate_type' => RoomRate::TYPE_EVENT, 'event_name' => 'FESPAM', 'start_date' => '2026-12-24', 'end_date' => '2026-12-26',
        'price_adjustment_type' => RoomRate::AJUSTEMENT_FIXE, 'price_adjustment' => 7_000_000]);

    $avant = sejour($this->type, '2026-12-18', '2026-12-21');
    expect(array_column($avant['nuits'], 'prix'))->toBe([5_000_000, 5_000_000, 4_800_000])
        ->and(array_column($avant['nuits'], 'tarif'))->toBe(['Week-end', 'Week-end', 'Fêtes'])
        ->and($avant['total'])->toBe(14_800_000);

    // Jeudi 24, vendredi 25, samedi 26 : l'événement l'emporte sur la saison et le week-end.
    $noel = sejour($this->type, '2026-12-24', '2026-12-27');
    expect(array_column($noel['nuits'], 'tarif'))->toBe(['FESPAM', 'FESPAM', 'FESPAM'])->and($noel['total'])->toBe(21_000_000);

    // Une priorité saisie plus haute passe avant l'ordre des critères.
    RoomRate::where('name', 'Week-end')->update(['priority' => 10]);
    $noel = sejour($this->type, '2026-12-24', '2026-12-27');
    expect(array_column($noel['nuits'], 'tarif'))->toBe(['FESPAM', 'Week-end', 'Week-end']);

    // Un tarif inactif ne compte plus.
    $fespam->update(['is_active' => false]);
    expect(sejour($this->type, '2026-12-24', '2026-12-25')['nuits'][0]['tarif'])->toBe('Fêtes');
});

test('long séjour, dernière minute et surcharge de capacité', function () {
    tarif($this->type, ['name' => 'Long séjour', 'rate_type' => RoomRate::TYPE_LONG_STAY, 'min_nights' => 7, 'price_adjustment' => -10]);
    tarif($this->type, ['name' => 'Dernière minute', 'rate_type' => RoomRate::TYPE_LAST_MINUTE, 'max_advance_days' => 1,
        'price_adjustment_type' => RoomRate::AJUSTEMENT_REMISE, 'price_adjustment' => 500_000]);

    // 7 nuits dans dix jours : long séjour.
    $long = sejour($this->type, '2026-12-11', '2026-12-18');
    expect($long['total'])->toBe(7 * 3_600_000)->and($long['par_nuit'])->toBe(3_600_000);

    // 2 nuits dès demain : dernière minute ; dans dix jours : prix de base.
    expect(sejour($this->type, '2026-12-02', '2026-12-04')['total'])->toBe(2 * 3_500_000)
        ->and(sejour($this->type, '2026-12-11', '2026-12-13')['total'])->toBe(2 * 4_000_000);

    // Trois personnes pour une capacité de base de deux : +10 % sur le tarif de la nuit.
    expect(sejour($this->type, '2026-12-02', '2026-12-03', 3)['total'])->toBe(3_850_000);
});

test('le responsable financier tient la grille ; la réception la consulte', function () {
    $financier = personneAuRole('finance_manager');
    $this->actingAs($financier);

    $this->get(route('rooms.rates.index'))->assertOk()->assertSee('Grille tarifaire')->assertSee('Ajouter un tarif');
    $this->post(route('rooms.rates.store'), [
        'room_type_id' => $this->type->id, 'name' => 'Week-end', 'rate_type' => RoomRate::TYPE_WEEKLY, 'days_of_week' => [5, 6],
        'price_adjustment_type' => RoomRate::AJUSTEMENT_POURCENTAGE, 'price_adjustment' => 25, 'priority' => 2, 'is_active' => 1,
    ])->assertRedirect(route('rooms.rates.index'))->assertSessionHasNoErrors();
    $weekend = RoomRate::sole();
    expect($weekend->days_of_week)->toBe([5, 6])->and($weekend->start_date)->toBeNull();

    // Une saison sans dates est refusée ; un prix fixe se saisit en FCFA.
    $this->post(route('rooms.rates.store'), ['room_type_id' => $this->type->id, 'name' => 'Saison', 'rate_type' => RoomRate::TYPE_SEASONAL,
        'price_adjustment_type' => RoomRate::AJUSTEMENT_POURCENTAGE, 'price_adjustment' => 10])->assertSessionHasErrors(['start_date', 'end_date']);
    $this->put(route('rooms.rates.update', $weekend), ['room_type_id' => $this->type->id, 'name' => 'Week-end', 'rate_type' => RoomRate::TYPE_WEEKLY,
        'days_of_week' => [6], 'price_adjustment_type' => RoomRate::AJUSTEMENT_FIXE, 'price_adjustment' => 45000, 'is_active' => 1])->assertSessionHasNoErrors();
    expect($weekend->fresh()->price_adjustment)->toBe(4_500_000);

    $this->put(route('rooms.rates.types.update', $this->type), ['base_price' => 42000])->assertSessionHasNoErrors();
    expect($this->type->fresh()->base_price)->toBe(4_200_000);

    // La simulation montre le prix de chaque nuit (samedi 19 au tarif fixe).
    $this->get(route('rooms.rates.index', ['type' => $this->type->id, 'arrivee' => '2026-12-18', 'depart' => '2026-12-20', 'adultes' => 2]))
        ->assertOk()->assertSee('87 000 FCFA')->assertSee('1 nuit « Week-end »', false);

    $receptionniste = personneAuRole('reception');
    $this->actingAs($receptionniste)->get(route('rooms.rates.index'))->assertOk()->assertDontSee('Ajouter un tarif');
    $this->post(route('rooms.rates.store'), ['room_type_id' => $this->type->id])->assertSessionHas('access_denied_popup', true);
    $this->delete(route('rooms.rates.destroy', $weekend))->assertSessionHas('access_denied_popup', true);
    expect(RoomRate::count())->toBe(1);

    // Le comptable, lui, n'a pas la grille.
    $this->actingAs(personneAuRole('accountant'))->get(route('rooms.rates.index'))->assertSessionHas('access_denied_popup', true);
});

test("la réservation prend la grille comme tarif de référence, y compris pour la remise de la réception", function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    $room = Room::with('roomType')->where('number', '101')->first();
    $base = (int) $room->roomType->base_price;
    // +50 % sur toute la période du séjour.
    tarif($room->roomType, ['name' => 'Haute saison', 'rate_type' => RoomRate::TYPE_SEASONAL, 'start_date' => '2026-12-01', 'end_date' => '2026-12-31', 'price_adjustment' => 50]);
    $attendu = (int) (2 * round($base * 1.5 / 100));   // FCFA, deux nuits

    $reception = personneAuRole('reception');
    \App\Models\CashRegisterSession::create(['user_id' => $reception->id, 'module' => 'reception', 'opening_amount' => 0, 'opened_at' => now()]);
    $customer = Customer::factory()->create();
    $this->actingAs($reception);

    $saisie = ['customer_id' => $customer->id, 'room_id' => $room->id, 'check_in' => '2026-12-10', 'check_out' => '2026-12-12',
        'check_in_time' => '14:00', 'adults_count' => 2, 'children_count' => 0, 'source' => 'phone'];

    $this->post(route('bookings.store'), ['step' => '3'] + $saisie)->assertOk()
        ->assertViewHas('totalRoomAmount', fn ($total) => (int) $total === $attendu)
        ->assertSee('Tarif de la grille')
        ->assertSee('2 nuits « Haute saison »', false);

    // Sans remise, la réception facture la grille ; le prix de base n'est plus un prix permis.
    $this->post(route('bookings.store'), $saisie + ['custom_price' => (string) (2 * $base / 100), 'payment_amount' => '30000', 'payment_method' => 'cash'])
        ->assertSessionHasErrors('custom_price');
    $this->post(route('bookings.store'), $saisie + ['custom_price' => (string) $attendu, 'payment_amount' => (string) ceil($attendu * 0.3), 'payment_method' => 'cash'])
        ->assertRedirect();

    expect(Booking::sole()->total_room_amount)->toBe($attendu * 100);
});
