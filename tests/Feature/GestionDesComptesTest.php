<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Gérer un compte du personnel : depuis la liste (menu ⋮) ou depuis sa
 * fiche, le modifier, le désactiver, réinitialiser son mot de passe. Le
 * mot de passe ne se modifie plus dans le formulaire : il se réinitialise,
 * et la personne choisit le sien à sa connexion suivante.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    activerModules(['restaurant', 'shop', 'housekeeping']);
});

function compteDuPersonnel(string $role, array $attributs = []): User
{
    $user = User::factory()->create($attributs + ['is_active' => true, 'password' => Hash::make('ancien-secret')]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test('réinitialiser un mot de passe remet un provisoire, ferme les sessions et oblige à en choisir un', function () {
    // Comme en établissement : les sessions vivent en base.
    config(['session.driver' => 'database']);
    $employe = compteDuPersonnel('reception', ['name' => 'Jean Mvondo']);
    DB::table('sessions')->insert(['id' => 'session-jean', 'user_id' => $employe->id, 'payload' => '', 'last_activity' => time()]);

    $reponse = $this->actingAs(compteDuPersonnel('manager'))
        ->post(route('users.resetPassword', $employe), ['retour' => 'fiche'])
        ->assertRedirect(route('users.show', $employe));

    $provisoire = $reponse->getSession()->get('motDePasseProvisoire');
    $employe->refresh();

    expect($provisoire['nom'])->toBe('Jean Mvondo')
        ->and(Hash::check($provisoire['valeur'], $employe->password))->toBeTrue()
        ->and($employe->must_change_password)->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $employe->id)->exists())->toBeFalse();

    // Le provisoire n'est consigné nulle part en clair.
    $journal = AuditLog::where('action', 'like', 'Mot de passe de Jean Mvondo%')->sole();
    expect(json_encode($journal->toArray()))->not->toContain($provisoire['valeur']);
});

test('avec un mot de passe provisoire, on choisit le sien avant tout autre écran', function () {
    $employe = compteDuPersonnel('reception');
    $employe->forceFill(['password' => Hash::make('Prov-1234-abcd'), 'must_change_password' => true])->save();

    $this->actingAs($employe)->get(route('dashboard'))->assertRedirect(route('password.nouveau'));
    $this->get(route('password.nouveau'))->assertOk()->assertSee('Choisissez votre mot de passe');

    // Le provisoire ne se garde pas.
    $this->put(route('password.nouveau.update'), ['password' => 'Prov-1234-abcd', 'password_confirmation' => 'Prov-1234-abcd'])
        ->assertSessionHasErrors('password');

    $this->put(route('password.nouveau.update'), ['password' => 'mon-secret-a-moi', 'password_confirmation' => 'mon-secret-a-moi'])
        ->assertRedirect(route('dashboard'));

    $employe->refresh();
    expect($employe->must_change_password)->toBeFalse()
        ->and(Hash::check('mon-secret-a-moi', $employe->password))->toBeTrue();

    $this->get(route('dashboard'))->assertOk();
});

test('le formulaire de modification ne change plus le mot de passe', function () {
    $employe = compteDuPersonnel('reception');

    $this->actingAs(compteDuPersonnel('manager'))->put(route('users.update', $employe), [
        'name' => $employe->name, 'email' => $employe->email, 'roles' => ['reception'], 'is_active' => 1,
        'password' => 'force-par-la-bande', 'password_confirmation' => 'force-par-la-bande',
    ])->assertSessionHasNoErrors();

    expect(Hash::check('ancien-secret', $employe->fresh()->password))->toBeTrue();

    $this->get(route('users.index'))->assertOk()
        ->assertDontSee('Nouveau mot de passe (optionnel)')
        ->assertSee('Réinitialiser le mot de passe');
});

test('la fiche permet de modifier, réinitialiser et désactiver, et y revient', function () {
    $employe = compteDuPersonnel('reception', ['name' => 'Awa Ngono']);
    $manager = compteDuPersonnel('manager');

    $this->actingAs($manager)->get(route('users.show', $employe))->assertOk()
        ->assertSee('id="edit-user-modal-' . $employe->id . '"', false)
        ->assertSee('Réinitialiser le mot de passe')
        ->assertSee('Désactiver le compte');

    $this->post(route('users.toggleStatus', $employe), ['retour' => 'fiche'])
        ->assertRedirect(route('users.show', $employe));
    expect($employe->fresh()->is_active)->toBeFalse();

    $this->put(route('users.update', $employe), [
        'name' => 'Awa Ngono-Biya', 'email' => $employe->email, 'roles' => ['reception'], 'retour' => 'fiche',
    ])->assertRedirect(route('users.show', $employe));
    expect($employe->fresh()->name)->toBe('Awa Ngono-Biya');
});

test('on ne réinitialise ni ne désactive son propre compte', function () {
    $manager = compteDuPersonnel('manager');

    $this->actingAs($manager)->post(route('users.resetPassword', $manager))->assertForbidden();
    $this->post(route('users.toggleStatus', $manager))->assertForbidden();

    expect($manager->fresh()->is_active)->toBeTrue()
        ->and($manager->fresh()->must_change_password)->toBeFalse();

    // Et sa propre fiche ne propose pas ces actions.
    $this->get(route('users.show', $manager))->assertOk()->assertDontSee('Réinitialiser le mot de passe');
});
