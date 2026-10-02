<?php

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\GeneralInventoryScheduled;
use App\Support\InventorySchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * Calendrier des inventaires généraux : réglé par la direction dans les
 * Paramètres, il rappelle aux services la veille et le jour du comptage.
 */

beforeEach(function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
});

afterEach(fn () => Carbon::setTestNow());

function calendrierInventaire(array $reglages): void
{
    $tenant = Tenant::first();
    $tenant->settings = array_merge($tenant->settings ?? [], ['inventaire' => $reglages]);
    $tenant->save();
}

test('le calendrier reconnaît les jours du mois, le dernier jour et les dates fixes', function () {
    $calendrier = new InventorySchedule(monthDays: [1, InventorySchedule::LAST_DAY], fixedDates: ['12-31', '01-01']);

    expect($calendrier->isInventoryDay(Carbon::parse('2026-03-01')))->toBeTrue()
        ->and($calendrier->isInventoryDay(Carbon::parse('2026-02-28')))->toBeTrue()
        ->and($calendrier->isInventoryDay(Carbon::parse('2026-04-30')))->toBeTrue()
        ->and($calendrier->isInventoryDay(Carbon::parse('2026-04-29')))->toBeFalse()
        ->and($calendrier->isInventoryDay(Carbon::parse('2026-12-31')))->toBeTrue();
});

test('le 31 du mois ne se reporte pas sur un mois de 30 jours', function () {
    $calendrier = new InventorySchedule(monthDays: [31]);

    expect($calendrier->isInventoryDay(Carbon::parse('2026-04-30')))->toBeFalse()
        ->and($calendrier->next(Carbon::parse('2026-04-01'))->toDateString())->toBe('2026-05-31');
});

test('le prochain inventaire et la description du calendrier', function () {
    $calendrier = new InventorySchedule(monthDays: [1], fixedDates: ['12-31']);

    expect($calendrier->next(Carbon::parse('2026-12-02'))->toDateString())->toBe('2026-12-31')
        ->and($calendrier->describe())->toBe('Le 1er de chaque mois, le 31 décembre')
        ->and((new InventorySchedule())->next(now()))->toBeNull();
});

test('la direction enregistre le calendrier dans les paramètres', function () {
    $this->actingAs(User::factory()->create(['role' => 'manager']))
        ->post(route('settings.update', ['tab' => 'inventaire']), ['settings' => [
            'month_days'        => ['1', 'last'],
            'fixed_dates'       => [['day' => 31, 'month' => 12], ['day' => 1, 'month' => 1]],
            'remind_day_before' => '1',
        ]])
        ->assertRedirect();

    expect(Tenant::first()->settings['inventaire'])->toBe([
        'month_days'        => [1, 'last'],
        'fixed_dates'       => ['01-01', '12-31'],
        'remind_day_before' => true,
    ]);
});

test('tout décocher vide le calendrier', function () {
    calendrierInventaire(['month_days' => [1], 'fixed_dates' => ['12-31'], 'remind_day_before' => true]);

    $this->actingAs(User::factory()->create(['role' => 'manager']))
        ->post(route('settings.update', ['tab' => 'inventaire']), ['settings' => ['remind_day_before' => '0']]);

    expect(InventorySchedule::current()->isConfigured())->toBeFalse();
});

test('une date qui n’existe pas est refusée', function () {
    $this->actingAs(User::factory()->create(['role' => 'manager']))
        ->post(route('settings.update', ['tab' => 'inventaire']), ['settings' => [
            'fixed_dates' => [['day' => 31, 'month' => 4]],
        ]])
        ->assertSessionHasErrors('settings.fixed_dates.0.day');
});

test('un chef de service ne règle pas le calendrier', function () {
    $this->actingAs(User::factory()->create(['role' => 'restaurant_chief']))
        ->post(route('settings.update', ['tab' => 'inventaire']), ['settings' => ['month_days' => ['1']]])
        ->assertForbidden();
});

test('le rappel part la veille et le jour même', function () {
    Notification::fake();
    $econome = User::factory()->create(['role' => 'econome', 'is_active' => true]);
    calendrierInventaire(['month_days' => [1], 'fixed_dates' => [], 'remind_day_before' => true]);

    Carbon::setTestNow('2026-06-30 06:30');
    $this->artisan('inventaire:rappel')->assertSuccessful();
    Notification::assertSentTo($econome, GeneralInventoryScheduled::class, fn ($n) => !$n->today);

    Carbon::setTestNow('2026-07-01 06:30');
    $this->artisan('inventaire:rappel')->assertSuccessful();
    Notification::assertSentTo($econome, GeneralInventoryScheduled::class, fn ($n) => $n->today);
});

test('sans rappel la veille, rien ne part avant le jour même', function () {
    Notification::fake();
    User::factory()->create(['role' => 'econome', 'is_active' => true]);
    calendrierInventaire(['month_days' => [1], 'fixed_dates' => [], 'remind_day_before' => false]);

    Carbon::setTestNow('2026-06-30 06:30');
    $this->artisan('inventaire:rappel')->assertSuccessful();

    Notification::assertNothingSent();
});

test('l’économat affiche le bandeau le jour de l’inventaire général', function () {
    calendrierInventaire(['month_days' => [1], 'fixed_dates' => [], 'remind_day_before' => true]);
    Carbon::setTestNow('2026-07-01 09:00');

    $this->actingAs(User::factory()->create(['role' => 'econome']))
        ->get(route('economat.items.index'))
        ->assertOk()
        ->assertSee('Inventaire général prévu aujourd');
});
