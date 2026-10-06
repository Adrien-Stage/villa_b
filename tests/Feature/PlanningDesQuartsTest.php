<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\ShiftAssignment;
use App\Models\ShiftWeek;
use App\Models\User;
use App\Models\WorkShift;
use App\Notifications\ShiftPlanningPublished;
use App\Notifications\ShiftPlanningReminder;
use App\Services\PlanningService;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * Planning des quarts : la direction définit les quarts de l'hôtel, chaque
 * chef de service y place son personnel semaine par semaine et envoie le
 * planning ; chacun est prévenu de ses quarts ; le dimanche, le chef est
 * invité à programmer la semaine suivante.
 */

beforeEach(function () {
    RoleCatalog::sync();
    // Mardi 6 octobre 2026, 10 h : la semaine en cours commence lundi 5.
    $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00'));

    $this->reception = Department::where('code', 'REC')->sole();
    $this->etages = Department::where('code', 'HSK')->sole();
    $this->jour = WorkShift::where('name', 'Jour')->sole();
    $this->nuit = WorkShift::where('name', 'Nuit')->sole();
});

function agentDe(string $role, ?Department $departement, string $nom): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true, 'department_id' => $departement?->id]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function placer(User $employe, WorkShift $quart, string $date, Department $departement): array
{
    return ['department_id' => $departement->id, 'user_id' => $employe->id, 'work_shift_id' => $quart->id, 'date' => $date];
}

test("la direction définit les quarts de l'hôtel ; un quart de nuit finit le lendemain", function () {
    expect($this->nuit->horaire())->toBe('19:00 – 07:00 (+1)')
        ->and($this->nuit->duree())->toBe(12.0)
        ->and($this->nuit->finLe(CarbonImmutable::parse('2026-10-06'))->toDateTimeString())->toBe('2026-10-07 07:00:00');

    $this->actingAs(agentDe('manager', null, 'Directrice'))
        ->post(route('settings.quarts.store'), ['name' => 'Matin', 'starts_at' => '06:00', 'ends_at' => '14:00'])
        ->assertRedirect(route('settings.index', ['tab' => 'quarts']));

    $matin = WorkShift::where('name', 'Matin')->sole();
    expect($matin->duree())->toBe(8.0);

    $this->put(route('settings.quarts.update', $matin), ['name' => 'Matin', 'starts_at' => '06:00', 'ends_at' => '06:00'])
        ->assertSessionHasErrors('ends_at');

    $this->get(route('settings.index', ['tab' => 'quarts']))->assertOk()->assertSee('Quarts de travail')->assertSee('Matin');

    // Un chef de service place son personnel ; il ne définit pas les quarts.
    $this->actingAs(agentDe('reception_chief', $this->reception, 'Chef réception'))
        ->post(route('settings.quarts.store'), ['name' => 'Coupure', 'starts_at' => '10:00', 'ends_at' => '14:00']);

    expect(WorkShift::where('name', 'Coupure')->exists())->toBeFalse();
});

test('un quart qui a servi ne se supprime pas : il se met hors service', function () {
    $chef = agentDe('reception_chief', $this->reception, 'Chef réception');
    $agent = agentDe('reception', $this->reception, 'Jean');
    $this->actingAs($chef)->post(route('planning.affectations.store'), placer($agent, $this->nuit, '2026-10-07', $this->reception));

    $this->actingAs(agentDe('manager', null, 'Directrice'))
        ->delete(route('settings.quarts.destroy', $this->nuit))->assertSessionHasErrors('quart');

    expect(WorkShift::whereKey($this->nuit->id)->exists())->toBeTrue();
});

test('le chef planifie son service, et seulement le sien', function () {
    $chef = agentDe('reception_chief', $this->reception, 'Chef réception');
    $agent = agentDe('reception', $this->reception, 'Jean');
    $valet = agentDe('housekeeping_staff', $this->etages, 'Paul');

    $this->actingAs($chef);

    $this->post(route('planning.affectations.store'), placer($agent, $this->jour, '2026-10-07', $this->reception))
        ->assertSessionHasNoErrors();
    $this->post(route('planning.affectations.store'), placer($valet, $this->jour, '2026-10-07', $this->etages))
        ->assertSessionHasErrors('departement');
    $this->post(route('planning.affectations.store'), placer($valet, $this->jour, '2026-10-07', $this->reception))
        ->assertSessionHasErrors('user_id');
    // Le passé ne se réécrit pas.
    $this->post(route('planning.affectations.store'), placer($agent, $this->jour, '2026-09-29', $this->reception))
        ->assertSessionHasErrors('semaine');

    expect(ShiftAssignment::count())->toBe(1);

    $this->get(route('planning.index'))->assertOk()
        ->assertSee('Planning des quarts')
        ->assertSee('Jean')
        ->assertSee('Envoyer le planning')
        ->assertDontSee('Paul');
});

test('la direction voit et planifie tous les services', function () {
    $valet = agentDe('housekeeping_staff', $this->etages, 'Paul');

    $this->actingAs(agentDe('manager', null, 'Directrice'))
        ->post(route('planning.affectations.store'), placer($valet, $this->nuit, '2026-10-08', $this->etages))
        ->assertSessionHasNoErrors();

    $this->get(route('planning.index', ['departement' => $this->etages->id]))->assertOk()
        ->assertSee('Paul')
        ->assertSee('planning-departement', false);
});

test("envoyer le planning prévient chacun de ses quarts, puis seulement ceux qu'un changement concerne", function () {
    Notification::fake();
    $chef = agentDe('reception_chief', $this->reception, 'Chef réception');
    $jean = agentDe('reception', $this->reception, 'Jean');
    $awa = agentDe('reception', $this->reception, 'Awa');
    $semaine = ['department_id' => $this->reception->id, 'semaine' => '2026-10-05'];

    $this->actingAs($chef);
    $this->post(route('planning.affectations.store'), placer($jean, $this->jour, '2026-10-07', $this->reception));
    $this->post(route('planning.affectations.store'), placer($jean, $this->nuit, '2026-10-09', $this->reception));
    $this->post(route('planning.affectations.store'), placer($awa, $this->nuit, '2026-10-07', $this->reception));

    $this->post(route('planning.publier'), $semaine)->assertSessionHas('success');

    Notification::assertSentTo($jean, ShiftPlanningPublished::class, function (ShiftPlanningPublished $n) {
        return count($n->quarts) === 2 && $n->quarts[0]['quart'] === 'Jour' && $n->quarts[1]['horaire'] === '19:00 – 07:00 (+1)';
    });
    Notification::assertSentTo($awa, ShiftPlanningPublished::class);
    expect(ShiftWeek::sole()->published_at)->not->toBeNull();

    // Renvoyer sans rien changer ne dérange personne.
    Notification::fake();
    $this->post(route('planning.publier'), $semaine);
    Notification::assertNothingSent();

    // Awa perd son quart : elle seule est prévenue, et apprend qu'elle n'en a plus.
    $this->delete(route('planning.affectations.destroy', ShiftAssignment::where('user_id', $awa->id)->sole()));
    $this->get(route('planning.index'))->assertSee('modifié depuis, à renvoyer');
    $this->post(route('planning.publier'), $semaine);

    Notification::assertNotSentTo($jean, ShiftPlanningPublished::class);
    Notification::assertSentTo($awa, ShiftPlanningPublished::class, fn (ShiftPlanningPublished $n) => $n->quarts === []);
});

test('recopier la semaine précédente reprend ce qui y était planifié', function () {
    $chef = agentDe('reception_chief', $this->reception, 'Chef réception');
    $jean = agentDe('reception', $this->reception, 'Jean');
    ShiftAssignment::create(['user_id' => $jean->id, 'work_shift_id' => $this->jour->id, 'department_id' => $this->reception->id, 'date' => '2026-09-30']);
    ShiftAssignment::create(['user_id' => $jean->id, 'work_shift_id' => $this->nuit->id, 'department_id' => $this->reception->id, 'date' => '2026-10-02']);

    $this->actingAs($chef)->post(route('planning.recopier'), ['department_id' => $this->reception->id, 'semaine' => '2026-10-05'])
        ->assertSessionHas('success', '2 quart(s) recopié(s) de la semaine précédente.');

    expect(ShiftAssignment::where('date', '2026-10-07')->where('work_shift_id', $this->jour->id)->exists())->toBeTrue()
        ->and(ShiftAssignment::where('date', '2026-10-09')->where('work_shift_id', $this->nuit->id)->exists())->toBeTrue();

    // Une seconde fois : rien de plus.
    $this->post(route('planning.recopier'), ['department_id' => $this->reception->id, 'semaine' => '2026-10-05']);
    expect(ShiftAssignment::count())->toBe(4);
});

test("le dimanche, le chef est invité à programmer la semaine suivante tant qu'il ne l'a pas envoyée", function () {
    Notification::fake();
    $chef = agentDe('reception_chief', $this->reception, 'Chef réception');
    $jean = agentDe('reception', $this->reception, 'Jean');
    agentDe('housekeeping_staff', $this->etages, 'Paul'); // les étages n'ont pas de chef : la direction est invitée
    $directrice = agentDe('manager', null, 'Directrice');

    $this->travelTo(CarbonImmutable::parse('2026-10-11 08:00')); // dimanche
    $this->artisan('planning:rappel')->assertSuccessful();

    Notification::assertSentTo($chef, ShiftPlanningReminder::class, fn (ShiftPlanningReminder $n) => $n->lundi->toDateString() === '2026-10-12');
    Notification::assertSentTo($directrice, ShiftPlanningReminder::class, fn (ShiftPlanningReminder $n) => $n->service === $this->etages->name);
    Notification::assertNotSentTo($jean, ShiftPlanningReminder::class);

    $this->actingAs($chef)->get(route('planning.index'))->assertOk()->assertSee('Programmer la semaine suivante');

    // La semaine suivante envoyée : plus de rappel pour la réception.
    $this->post(route('planning.affectations.store'), placer($jean, $this->jour, '2026-10-12', $this->reception));
    $this->post(route('planning.publier'), ['department_id' => $this->reception->id, 'semaine' => '2026-10-12']);

    Notification::fake();
    $this->artisan('planning:rappel');
    Notification::assertNotSentTo($chef, ShiftPlanningReminder::class);
});

test('qui est en service maintenant, nuit comprise, et chacun voit ses quarts', function () {
    $chef = agentDe('reception_chief', $this->reception, 'Chef réception');
    $jean = agentDe('reception', $this->reception, 'Jean Veilleur');
    $this->actingAs($chef)->post(route('planning.affectations.store'), placer($jean, $this->nuit, '2026-10-06', $this->reception));

    // Mercredi 2 h : le quart de nuit de mardi court encore.
    $this->travelTo(CarbonImmutable::parse('2026-10-07 02:00'));
    $enService = app(PlanningService::class)->enService([$this->reception->id]);
    expect($enService->pluck('user.name')->all())->toBe(['Jean Veilleur']);

    $this->get(route('dashboard'))->assertOk()->assertSee('En service maintenant')->assertSee('Jean Veilleur');

    // L'employé voit ses quarts, pas la grille de son service.
    $this->actingAs($jean)->get(route('planning.index'))->assertOk()
        ->assertSee('Mes quarts')
        ->assertSee('Mardi 6 octobre')
        ->assertDontSee('Envoyer le planning');
    $this->get(route('dashboard'))->assertOk()->assertSee('En service — Nuit');
});
