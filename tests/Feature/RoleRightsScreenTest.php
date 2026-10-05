<?php

/**
 * « Rôles & droits » de l'établissement : l'administrateur règle la couche de
 * l'hôtel et les exceptions nominatives. Le modèle et la couche de la console
 * s'y lisent sans s'y modifier.
 */

use App\Models\PermissionGrant;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionMatrix;
use App\Services\PermissionResolver;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat', 'hebergement', 'reservations', 'clients', 'utilisateurs', 'comptabilite', 'accounting']);
});

function compteDe(string $role, array $attributs = []): User
{
    $user = User::factory()->create(['role' => $role, 'is_active' => true] + $attributs);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user;
}

function empreinteHotel(): string
{
    return app(PermissionMatrix::class)->empreinte(PermissionGrant::ORIGINE_ETABLISSEMENT);
}

test("l'administrateur ouvre l'écran ; un manager non", function () {
    $this->actingAs(compteDe('admin'))->get(route('droits.index'))->assertOk()->assertSee('Rôles &amp; droits', false);

    $this->actingAs(compteDe('manager'))->get(route('droits.index'))->assertRedirect();
});

test("la couche de l'hôtel se règle sans toucher celle de la console", function () {
    PermissionGrant::create([
        'subject_type' => 'role', 'subject_id' => 'econome', 'permission' => 'economat.items.supprimer',
        'effect' => 'deny', 'origin' => PermissionGrant::ORIGINE_ERP, 'reason' => 'Console',
    ]);

    $this->actingAs(compteDe('admin'))->put(route('droits.update'), [
        'empreinte' => empreinteHotel(),
        'motif' => 'Le fichier clients reste au chef de réception.',
        'ecarts' => [['role' => 'reception', 'permission' => 'customers.export', 'effect' => 'deny', 'reason' => 'Le fichier clients reste au chef de réception.']],
    ])->assertSessionHas('success');

    expect(PermissionGrant::where('origin', PermissionGrant::ORIGINE_ETABLISSEMENT)->where('subject_id', 'reception')->exists())->toBeTrue()
        ->and(PermissionGrant::where('origin', PermissionGrant::ORIGINE_ERP)->count())->toBe(1);

    expect(app(PermissionResolver::class)->allows(compteDe('reception'), 'customers.export'))->toBeFalse();
});

test('un cumul sans dérogation est refusé ; avec dérogation, il passe et se trace', function () {
    $admin = compteDe('admin');
    $lot = [['role' => 'cashier', 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow', 'reason' => 'Pas de comptable le soir.']];

    $this->actingAs($admin)->put(route('droits.update'), ['empreinte' => empreinteHotel(), 'motif' => 'Essai', 'ecarts' => $lot])
        ->assertSessionHas('error');
    expect(PermissionGrant::count())->toBe(0);

    $this->put(route('droits.update'), ['empreinte' => empreinteHotel(), 'motif' => 'Pas de comptable le soir.', 'derogation' => 1, 'ecarts' => $lot])
        ->assertSessionHas('success');
    $this->assertDatabaseHas('audit_logs', ['event_type' => 'duty_segregation_override']);
});

test("un écran périmé n'écrase pas le travail d'un autre", function () {
    $admin = compteDe('admin');
    $empreinte = empreinteHotel();

    PermissionGrant::create([
        'subject_type' => 'role', 'subject_id' => 'reception', 'permission' => 'customers.export',
        'effect' => 'deny', 'origin' => PermissionGrant::ORIGINE_ETABLISSEMENT, 'reason' => 'Autre administrateur',
    ]);

    $this->actingAs($admin)->put(route('droits.update'), ['empreinte' => $empreinte, 'motif' => 'Essai', 'ecarts' => []])
        ->assertSessionHas('error');

    expect(PermissionGrant::count())->toBe(1);
});

test("l'administrateur et le support ne se règlent pas", function () {
    $this->actingAs(compteDe('admin'))->put(route('droits.update'), [
        'empreinte' => empreinteHotel(), 'motif' => 'Essai',
        'ecarts' => [['role' => 'admin', 'permission' => 'economat.items.creer', 'effect' => 'allow']],
    ])->assertSessionHas('error');

    expect(PermissionGrant::count())->toBe(0);
});

test("l'aperçu dit qui change de droits, sans rien enregistrer", function () {
    $econome = compteDe('econome', ['name' => 'Rose Ngo']);

    $this->actingAs(compteDe('admin'))->postJson(route('droits.apercu'), [
        'ecarts' => [['role' => 'econome', 'permission' => 'economat.items.creer', 'effect' => 'deny']],
    ])->assertOk()->assertJsonPath('apercu.personnes.0.name', 'Rose Ngo');

    expect(PermissionGrant::count())->toBe(0)
        ->and(app(PermissionResolver::class)->allows($econome, 'economat.items.creer'))->toBeTrue();
});

test('une exception nominative se motive, agit, et se retire', function () {
    $receptionniste = compteDe('reception');

    $this->actingAs(compteDe('admin'))->post(route('droits.exceptions.store'), [
        'user_id' => $receptionniste->id, 'permission' => 'customers.export', 'effect' => 'deny',
        'reason' => 'Départ annoncé : plus d\'extraction du fichier.', 'expires_at' => now()->addMonth()->toDateString(),
    ])->assertSessionHas('success');

    $grant = PermissionGrant::where('subject_type', 'user')->firstOrFail();
    expect($grant->origin)->toBe(PermissionGrant::ORIGINE_ETABLISSEMENT)
        ->and(app(PermissionResolver::class)->allows($receptionniste, 'customers.export'))->toBeFalse();

    $this->delete(route('droits.exceptions.destroy', $grant))->assertSessionHas('success');
    app(PermissionResolver::class)->forget();

    expect(PermissionGrant::count())->toBe(0)
        ->and(app(PermissionResolver::class)->allows($receptionniste->fresh(), 'customers.export'))->toBeTrue();
});

test("une exception qui fait cumuler des fonctions exige une dérogation", function () {
    $caissier = compteDe('cashier');
    $admin = compteDe('admin');

    $this->actingAs($admin)->post(route('droits.exceptions.store'), [
        'user_id' => $caissier->id, 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow', 'reason' => 'Essai',
    ])->assertSessionHas('error');
    expect(PermissionGrant::count())->toBe(0);

    $this->post(route('droits.exceptions.store'), [
        'user_id' => $caissier->id, 'permission' => 'accounting.cash_reviews.creer', 'effect' => 'allow',
        'reason' => 'Établissement de six personnes.', 'derogation' => 1,
    ])->assertSessionHas('success');
    expect(PermissionGrant::count())->toBe(1);
});

test("aucune exception ne s'applique à l'administrateur", function () {
    $autreAdmin = compteDe('admin');

    $this->actingAs(compteDe('admin'))->post(route('droits.exceptions.store'), [
        'user_id' => $autreAdmin->id, 'permission' => 'economat.items.creer', 'effect' => 'allow', 'reason' => 'Essai',
    ])->assertSessionHas('error');

    expect(PermissionGrant::count())->toBe(0);
});

test("la fiche d'un employé montre ses rôles, ses exceptions et ce qu'il peut faire", function () {
    $receptionniste = compteDe('reception', ['name' => 'Jean Mvondo']);
    PermissionGrant::create([
        'subject_type' => 'user', 'subject_id' => (string) $receptionniste->id, 'permission' => 'customers.export',
        'effect' => 'deny', 'origin' => PermissionGrant::ORIGINE_ETABLISSEMENT, 'reason' => 'Départ annoncé',
    ]);

    $this->actingAs(compteDe('admin'))->get(route('users.show', $receptionniste))
        ->assertOk()
        ->assertSee('Jean Mvondo')
        ->assertSee('Réceptionniste')
        ->assertSee('Départ annoncé')
        // Les accès se lisent en clair, écran par écran.
        ->assertSee('Ses accès')
        ->assertSee('Brouillons de réservation')
        ->assertSee('Rétablir')
        ->assertSee('Accorder un accès en plus');
});

test('depuis la fiche, on retire plusieurs accès d\'un coup, et on les rétablit', function () {
    $receptionniste = compteDe('reception', ['name' => 'Jean Mvondo']);
    $admin = compteDe('admin');

    $this->actingAs($admin)->post(route('droits.exceptions.store'), [
        'user_id' => $receptionniste->id, 'effect' => 'deny', 'retour' => 'fiche',
        'permissions' => ['customers.export', 'customers.import'],
    ])->assertSessionHasErrors('reason');
    expect(PermissionGrant::count())->toBe(0);

    $this->post(route('droits.exceptions.store'), [
        'user_id' => $receptionniste->id, 'effect' => 'deny', 'retour' => 'fiche',
        'permissions' => ['customers.export', 'customers.import'], 'reason' => 'Fichier clients réservé à la direction',
    ])->assertRedirect(route('users.show', $receptionniste))->assertSessionHas('success', '2 accès retirés.');

    $resolveur = app(PermissionResolver::class);
    $resolveur->forget();
    expect($resolveur->allows($receptionniste, 'customers.export'))->toBeFalse()
        ->and($resolveur->allows($receptionniste, 'customers.import'))->toBeFalse()
        ->and($resolveur->allows($receptionniste, 'customers.voir'))->toBeTrue();

    $this->get(route('users.show', $receptionniste))->assertOk()
        ->assertSee('Fichier clients réservé à la direction')
        ->assertSee('Clients — Exporter');

    $retrait = PermissionGrant::where('permission', 'customers.export')->sole();
    $this->delete(route('droits.exceptions.destroy', $retrait), ['retour' => 'fiche'])
        ->assertRedirect(route('users.show', $receptionniste))
        ->assertSessionHas('success', 'Exception levée : Clients — Exporter.');

    $resolveur->forget();
    expect($resolveur->allows($receptionniste->fresh(), 'customers.export'))->toBeTrue()
        ->and($resolveur->allows($receptionniste->fresh(), 'customers.import'))->toBeFalse();
});

test("retirer tout un service d'un coup passe, et le journal le dit sans déborder", function () {
    $receptionniste = compteDe('reception', ['name' => 'Jean Mvondo']);
    $resolveur = app(PermissionResolver::class);
    $ecritures = array_values(array_filter(
        \App\Support\PermissionCatalog::ecritures(),
        fn (string $droit): bool => str_starts_with($droit, 'bookings.') && $resolveur->allows($receptionniste, $droit)
    ));
    expect(count($ecritures))->toBeGreaterThan(10);

    // Le motif le plus long permis, et une quinzaine de droits : le texte
    // du journal dépasse de loin la colonne (255 caractères).
    $this->actingAs(compteDe('admin'))->post(route('droits.exceptions.store'), [
        'user_id' => $receptionniste->id, 'effect' => 'deny', 'retour' => 'fiche',
        'permissions' => $ecritures, 'reason' => str_repeat('Stagiaire en observation. ', 9),
    ])->assertRedirect(route('users.show', $receptionniste))->assertSessionHas('success');

    expect(PermissionGrant::count())->toBe(count($ecritures));

    $journal = \App\Models\AuditLog::where('event_type', 'permission_exception')->sole();
    expect(mb_strlen($journal->action))->toBeLessThanOrEqual(255)
        ->and($journal->payload['permissions'])->toBe($ecritures)
        ->and($journal->payload['reason'])->toStartWith('Stagiaire en observation.');
});

test('chaque droit du catalogue a un nom en clair', function () {
    $sansNom = array_values(array_filter(
        array_keys(\App\Support\PermissionCatalog::all()),
        fn (string $droit): bool => ! \App\Support\PermissionLabels::estNomme($droit)
    ));

    expect($sansNom)->toBe([])
        ->and(\App\Support\PermissionLabels::complet('economat.orders.send'))->toBe('Bons de commande — Envoyer au fournisseur');
});

test("l'administrateur règle l'onglet Général, pas les tarifs", function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $admin = compteDe('admin');

    expect(\App\Support\SettingsTabs::peutRegler($admin, 'general'))->toBeTrue()
        ->and(\App\Support\SettingsTabs::peutRegler($admin, 'services'))->toBeFalse()
        ->and(\App\Support\SettingsTabs::peutRegler($admin, 'hebergement'))->toBeFalse();
});
