<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\ShiftAssignment;
use App\Models\ShiftWeek;
use App\Models\TrashEntry;
use App\Models\User;
use App\Models\WorkRegime;
use App\Models\WorkShift;
use App\Notifications\ShiftPlanningReminder;
use App\Services\PlanningService;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    // Mardi 6 octobre 2026, 10:00 (semaine du lundi 5 octobre au dimanche 11 octobre)
    $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00'));

    $this->finance = Department::where('code', 'FIN')->sole();
    $this->reception = Department::where('code', 'REC')->sole();
    $this->technique = Department::where('code', 'TEC')->sole();
});

function collaborateurRegime(string $role, ?Department $departement, string $nom, ?WorkRegime $regime = null): User
{
    $user = User::factory()->create([
        'name'           => $nom,
        'is_active'      => true,
        'department_id'  => $departement?->id,
        'work_regime_id' => $regime?->id,
    ]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test('les régimes initiaux sont en place : Jour/Nuit par défaut, Horaire fixe Administration, Quarts 08h-15h/15h-22h', function () {
    $jourNuit = WorkRegime::where('name', 'Système Jour / Nuit')->sole();
    $admin = WorkRegime::where('name', 'Horaire fixe — Administration')->sole();
    $quartsJournee = WorkRegime::where('name', 'Système de quarts (08h–15h / 15h–22h)')->sole();

    expect($jourNuit->is_default)->toBeTrue()
        ->and($jourNuit->estEnQuarts())->toBeTrue()
        ->and($admin->estFixe())->toBeTrue()
        ->and($admin->joursTravailles())->toBe([1, 2, 3, 4, 5])
        ->and($admin->workingDaysLabel())->toBe('Lun – Ven')
        ->and($admin->shifts()->pluck('name')->all())->toBe(['Journée'])
        ->and($quartsJournee->shifts()->pluck('name')->all())->toBe(['Quart Matin', 'Quart Après-midi'])
        ->and($this->finance->fresh()->regimeEffectif()?->id)->toBe($admin->id)
        ->and($this->reception->fresh()->regimeEffectif()?->id)->toBe($jourNuit->id);

    $this->actingAs(collaborateurRegime('manager', null, 'Directrice'))
        ->get(route('settings.index', ['tab' => 'quarts']))
        ->assertOk()
        ->assertSee('Régimes horaires et Quarts de travail')
        ->assertSee('Horaire fixe — Administration')
        ->assertSee('Système de quarts (08h–15h / 15h–22h)')
        ->assertSee('<form data-reglages ', false);
});

test('la direction crée, modifie (y compris en enregistrement unifié) et protège les régimes et leurs plages', function () {
    $directrice = collaborateurRegime('manager', null, 'Directrice');
    $entetesUnifies = ['X-Enregistrement-Unifie' => '1', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

    // Création d'un régime Jour / Nuit 08h-18h / 18h-08h affecté au service Technique
    $this->actingAs($directrice)->post(route('settings.regimes.store'), [
        'name'              => 'Garde 08h–18h / 18h–08h',
        'mode'              => 'shifts',
        'department_ids'    => [$this->technique->id],
        'starter_name'      => 'Garde Jour',
        'starter_starts_at' => '08:00',
        'starter_ends_at'   => '18:00',
    ])->assertRedirect(route('settings.index', ['tab' => 'quarts']))->assertSessionHas('success');

    $garde = WorkRegime::where('name', 'Garde 08h–18h / 18h–08h')->sole();

    $this->post(route('settings.quarts.store'), [
        'work_regime_id' => $garde->id,
        'name'           => 'Garde Nuit',
        'starts_at'      => '18:00',
        'ends_at'        => '08:00',
    ])->assertRedirect(route('settings.index', ['tab' => 'quarts']));

    $nuit = WorkShift::where('name', 'Garde Nuit')->sole();
    expect($nuit->horaire())->toBe('18:00 – 08:00 (+1)')
        ->and($nuit->duree())->toBe(14.0)
        ->and($this->technique->fresh()->work_regime_id)->toBe($garde->id);

    // Modification d'un horaire fixe via la barre unifiée (ajout du samedi)
    $admin = WorkRegime::where('name', 'Horaire fixe — Administration')->sole();
    $this->withHeaders($entetesUnifies)->put(route('settings.regimes.update', $admin), [
        'name'                   => 'Horaire fixe — Administration',
        'mode'                   => 'fixed',
        'working_days_present'   => '1',
        'working_days'           => [1, 2, 3, 4, 5, 6],
        'department_ids_present' => '1',
        'department_ids'         => [$this->finance->id],
        'is_active'              => '1',
        'is_default'             => '0',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($admin->fresh()->joursTravailles())->toBe([1, 2, 3, 4, 5, 6]);

    // Refus si horaire fixe sans aucun jour travaillé
    $this->withHeaders($entetesUnifies)->put(route('settings.regimes.update', $admin), [
        'name'                 => 'Horaire fixe — Administration',
        'mode'                 => 'fixed',
        'working_days_present' => '1',
        'working_days'         => [],
        'is_active'            => '1',
        'is_default'           => '0',
    ])->assertStatus(422)->assertJsonValidationErrors('working_days');

    // Le régime par défaut ne se supprime pas
    $defaut = WorkRegime::where('is_default', true)->sole();
    $this->flushHeaders()->delete(route('settings.regimes.destroy', $defaut))->assertSessionHasErrors('regime');

    // Un chef de service ne modifie pas les régimes de l'hôtel
    $chef = collaborateurRegime('reception_chief', $this->reception, 'Chef réception');
    $this->actingAs($chef)->post(route('settings.regimes.store'), [
        'name'         => 'Pirate',
        'mode'         => 'fixed',
        'working_days' => [1],
    ]);
    expect(WorkRegime::where('name', 'Pirate')->exists())->toBeFalse();
});

test('un service en horaire fixe se pré-remplit automatiquement et apparaît en service sans planification manuelle ni rappel du dimanche', function () {
    Notification::fake();

    $directrice = collaborateurRegime('manager', null, 'Directrice');
    $comptable = collaborateurRegime('accountant', $this->finance, 'Clarisse Comptable');
    $agentRec = collaborateurRegime('reception', $this->reception, 'Jean Réception');

    // Ouvrir le planning de Finance pré-remplit Lundi..Vendredi (5 × 9h = 45h) en source='auto'
    $this->actingAs($directrice)
        ->get(route('planning.index', ['departement' => $this->finance->id]))
        ->assertOk()
        ->assertSee('Horaire fixe')
        ->assertSee('Clarisse Comptable')
        ->assertSee('Auto');

    $affectations = ShiftAssignment::where('user_id', $comptable->id)->orderBy('date')->get();
    expect($affectations)->toHaveCount(5)
        ->and($affectations->pluck('date')->all())->toBe(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09'])
        ->and($affectations->every(fn (ShiftAssignment $a) => $a->source === 'auto'))->toBeTrue();

    $planningService = app(PlanningService::class);
    // Mardi 10:00 (08:00–17:00) : Clarisse est en service et le tableau de bord l'affiche
    expect($planningService->enService([$this->finance->id])->pluck('user.name')->all())->toBe(['Clarisse Comptable']);

    $this->actingAs($comptable)->get(route('dashboard'))
        ->assertOk()
        ->assertSee("En service — Journée jusqu'à 17:00", false);

    // Mardi 19:00 ou Samedi 10:00 : hors horaire fixe
    expect($planningService->enService([$this->finance->id], CarbonImmutable::parse('2026-10-06 19:00')))->toBeEmpty()
        ->and($planningService->enService([$this->finance->id], CarbonImmutable::parse('2026-10-10 10:00')))->toBeEmpty();

    // Le dimanche : pas de rappel pour Finance (100 % fixe), oui pour Réception (quarts)
    $this->travelTo(CarbonImmutable::parse('2026-10-11 08:00'));
    $this->artisan('planning:rappel')->assertSuccessful();

    Notification::assertSentTo($directrice, ShiftPlanningReminder::class, fn (ShiftPlanningReminder $n) => $n->service === $this->reception->name);
    Notification::assertNotSentTo($directrice, ShiftPlanningReminder::class, fn (ShiftPlanningReminder $n) => $n->service === $this->finance->name);

    $this->actingAs($directrice)
        ->get(route('planning.index', ['departement' => $this->finance->id]))
        ->assertDontSee('Programmer la semaine suivante');
});

test('une suppression manuelle sur un horaire fixe reste respectée, tandis qu’un nouveau collègue ou un changement de régime recalcule proprement', function () {
    $directrice = collaborateurRegime('manager', null, 'Directrice');
    $clarisse = collaborateurRegime('accountant', $this->finance, 'Clarisse');
    $admin = WorkRegime::where('name', 'Horaire fixe — Administration')->sole();

    $planning = app(PlanningService::class);
    $lundi = PlanningService::lundi();

    // Pré-remplissage initial (Mon..Fri pour Clarisse)
    expect($planning->semaine($this->finance, $lundi))->toHaveCount(5);

    // Retrait manuel du vendredi 2026-10-09
    $vendredi = ShiftAssignment::where('user_id', $clarisse->id)->where('date', '2026-10-09')->sole();
    $this->actingAs($directrice)->delete(route('planning.affectations.destroy', $vendredi));

    // Un rafraîchissement ne recrée PAS le vendredi retiré
    expect($planning->semaine($this->finance, $lundi))->toHaveCount(4);

    // Recrutement d'un second comptable la même semaine : seul lui reçoit Mon..Fri, Clarisse reste à 4 jours
    $bruno = collaborateurRegime('accountant', $this->finance, 'Bruno');
    $semaine = $planning->semaine($this->finance, $lundi);
    expect($semaine->where('user_id', $clarisse->id))->toHaveCount(4)
        ->and($semaine->where('user_id', $bruno->id))->toHaveCount(5);

    // Modification du régime (passage à Lun–Jeu) : purge les 'auto' à compter d'aujourd'hui (mar..ven) et recrée mar..jeu
    $this->actingAs($directrice)->put(route('settings.regimes.update', $admin), [
        'name'                   => $admin->name,
        'mode'                   => 'fixed',
        'working_days_present'   => '1',
        'working_days'           => [1, 2, 3, 4],
        'department_ids_present' => '1',
        'department_ids'         => [$this->finance->id],
        'is_active'              => '1',
        'is_default'             => '0',
    ])->assertRedirect();

    $apres = $planning->semaine($this->finance, $lundi);
    expect($apres->where('date', '2026-10-09'))->toHaveCount(0)
        ->and($apres->where('user_id', $clarisse->id)->pluck('date')->values()->all())->toBe(['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08']);
});

test('une exception individuelle sur un collaborateur surcharge le régime de son département', function () {
    $directrice = collaborateurRegime('manager', null, 'Directrice');
    $admin = WorkRegime::where('name', 'Horaire fixe — Administration')->sole();

    $jean = collaborateurRegime('reception', $this->reception, 'Jean Standard');
    $nadia = collaborateurRegime('reception', $this->reception, 'Nadia Jour', $admin);

    // L'écran Personnel propose le sélecteur d'exception individuelle
    $this->actingAs($directrice)->get(route('users.index'))->assertOk()
        ->assertSee('name="work_regime_id"', false);

    // Vérification aussi via la mise à jour du profil personnel
    $this->actingAs($directrice)->put(route('users.update', $nadia), [
        'name'           => $nadia->name,
        'email'          => $nadia->email,
        'department_id'  => $this->reception->id,
        'work_regime_id' => $admin->id,
        'roles'          => ['reception'],
        'is_active'      => '1',
    ])->assertRedirect();

    $this->get(route('users.show', $nadia))->assertOk()
        ->assertSee('Horaire fixe — Administration')
        ->assertSee('Exception individuelle');

    $planning = app(PlanningService::class);
    $quartsVisibles = $planning->quartsDuDepartement($this->reception);
    expect($quartsVisibles->pluck('name')->all())->toContain('Jour', 'Nuit', 'Journée');

    // Sur Réception : Nadia est pré-remplie automatiquement, Jean reste à planifier en quarts
    $semaine = $planning->semaine($this->reception, PlanningService::lundi());
    expect($semaine->where('user_id', $nadia->id))->toHaveCount(5)
        ->and($semaine->where('user_id', $jean->id))->toHaveCount(0)
        ->and($planning->enService([$this->reception->id])->pluck('user.name')->all())->toBe(['Nadia Jour']);

    // Création directe avec exception individuelle via users.store + retrait lors de désactivation
    $this->actingAs($directrice)->post(route('users.store'), [
        'name'           => 'Marc Exception',
        'email'          => 'marc.exception@hotel.test',
        'department_id'  => $this->reception->id,
        'work_regime_id' => $admin->id,
        'roles'          => ['reception'],
        'is_active'      => '1',
    ])->assertRedirect();

    $marc = User::where('email', 'marc.exception@hotel.test')->sole();
    expect($planning->enService([$this->reception->id])->pluck('user.name')->all())->toContain('Marc Exception');

    $this->actingAs($directrice)->post(route('users.toggleStatus', $marc))->assertRedirect();
    expect($planning->enService([$this->reception->id])->pluck('user.name')->all())->toBe(['Nadia Jour']);
});

test('un régime supprimé passe par la corbeille avec ses plages et liens puis se restaure', function () {
    config(['orchestration.secret' => 'jeton-de-console']);
    $directrice = collaborateurRegime('manager', null, 'Directrice');

    $this->actingAs($directrice)->post(route('settings.regimes.store'), [
        'name'              => 'Régime Saisonnier',
        'mode'              => 'fixed',
        'working_days'      => [1, 3, 5],
        'department_ids'    => [$this->technique->id],
        'starter_name'      => 'Permanence',
        'starter_starts_at' => '09:00',
        'starter_ends_at'   => '16:00',
    ])->assertSessionHas('success');

    $regime = WorkRegime::where('name', 'Régime Saisonnier')->sole();
    $shift = $regime->shifts()->sole();

    $this->delete(route('settings.regimes.destroy', $regime))->assertSessionHas('success');

    expect(WorkRegime::find($regime->id))->toBeNull()
        ->and(WorkShift::find($shift->id))->toBeNull()
        ->and($this->technique->fresh()->work_regime_id)->toBeNull();

    $entree = TrashEntry::where('table_name', 'work_regimes')->sole();
    expect($entree->type)->toBe('Régime horaire')
        ->and($entree->module)->toBe('planning')
        ->and($entree->label)->toBe('Régime Saisonnier');

    $this->postJson("/api/corbeille/{$entree->id}/restaurer", ['auteur' => 'Tech'], ['Authorization' => 'Bearer jeton-de-console'])
        ->assertOk();

    expect(WorkRegime::find($regime->id)?->name)->toBe('Régime Saisonnier')
        ->and(WorkShift::find($shift->id)?->name)->toBe('Permanence')
        ->and($this->technique->fresh()->work_regime_id)->toBe($regime->id);
});
