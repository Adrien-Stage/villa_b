<?php

/**
 * La séparation des tâches s'applique au moment où les rôles sont assignés.
 *
 * Déclarée en phase 0, elle ne refusait rien. Un compte de Zingana cumulait
 * econome, accountant et quality_auditor : la détention du stock, sa
 * comptabilisation, et le contrôle des deux, sur une seule personne. Le
 * formulaire l'acceptait sans un mot.
 */

use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['utilisateurs']);
    RoleCatalog::sync();
    $this->actingAs(User::factory()->create(['role' => 'manager']));
});

function formulaireStaff(array $roles, array $extra = []): array
{
    return array_merge([
        'name'                  => 'Nouvel employé',
        'email'                 => 'employe' . random_int(1, 999999) . '@exemple.cm',
        'roles'                 => $roles,
        'password'              => 'motdepasse12',
        'password_confirmation' => 'motdepasse12',
        'is_active'             => 1,
    ], $extra);
}

test('le cumul relevé sur un compte réel est refusé', function () {
    $reponse = $this->post(route('users.store'), formulaireStaff(['econome', 'accountant', 'quality_auditor']));

    $reponse->assertSessionHasErrors('roles');
    $this->assertDatabaseMissing('users', ['name' => 'Nouvel employé']);
});

test('le refus nomme les couples et leur motif', function () {
    $this->post(route('users.store'), formulaireStaff(['econome', 'accountant']));

    $erreurs = session('errors')->get('roles');

    expect($erreurs[0])->toContain('econome × accountant')
        ->and($erreurs[0])->toContain('livres');   // le motif, lisible
});

test('un cumul opérationnel banal passe sans rien demander', function () {
    $this->post(route('users.store'), formulaireStaff(['reception', 'cashier'], ['name' => 'Agent polyvalent']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['name' => 'Agent polyvalent']);
});

test('la dérogation motivée laisse passer et laisse une trace', function () {
    $this->post(route('users.store'), formulaireStaff(['econome', 'accountant'], [
        'name'             => 'Polyvalent contraint',
        'derogation'       => 1,
        'derogation_motif' => 'Établissement de six personnes, contrôle mensuel du directeur.',
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', ['name' => 'Polyvalent contraint']);
    $this->assertDatabaseHas('audit_logs', ['event_type' => 'duty_segregation_override', 'module' => 'security']);
});

test('une dérogation sans motif est refusée', function () {
    $this->post(route('users.store'), formulaireStaff(['econome', 'accountant'], ['derogation' => 1]))
        ->assertSessionHasErrors('derogation_motif');
});

test("la modification d'un employé est contrôlée comme sa création", function () {
    $employe = User::factory()->create(['role' => 'econome']);
    $employe->roles()->sync(Role::where('slug', 'econome')->pluck('id'));

    $this->put(route('users.update', $employe), [
        'name'      => $employe->name,
        'email'     => $employe->email,
        'roles'     => ['econome', 'accountant'],
        'is_active' => 1,
    ])->assertSessionHasErrors('roles');

    // Les rôles d'origine n'ont pas bougé.
    expect($employe->fresh()->roles->pluck('slug')->all())->toBe(['econome']);
});

test("le motif de la dérogation est consigné, pas seulement le fait", function () {
    $this->post(route('users.store'), formulaireStaff(['cashier', 'accountant'], [
        'derogation'       => 1,
        'derogation_motif' => 'Congé du comptable titulaire jusqu au 30 octobre.',
    ]));

    $trace = \App\Models\AuditLog::where('event_type', 'duty_segregation_override')->first();

    expect($trace->action)->toContain('cashier × accountant')
        ->and($trace->action)->toContain('30 octobre');
});

test("l'écran affiche le conflit et propose la dérogation", function () {
    // Le contrôleur refusait déjà ; encore fallait-il que l'opérateur voie
    // pourquoi, et comment passer outre.
    // from() : sans référent, redirect()->back() ne revient pas sur l'écran
    // du staff, et le bloc ne serait pas rendu.
    $this->from(route('users.index'))
        ->followingRedirects()
        ->post(route('users.store'), formulaireStaff(['econome', 'accountant']))
        ->assertSee('casse la séparation des tâches')
        ->assertSee('econome × accountant')
        ->assertSee("J'accorde la dérogation", false)
        ->assertSee('derogation_motif', false);
});
