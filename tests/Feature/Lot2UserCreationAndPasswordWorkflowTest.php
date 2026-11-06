<?php

use App\Mail\UserPasswordResetMail;
use App\Mail\UserWelcomeMail;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\PointOfSale;
use App\Models\Role;
use App\Models\User;
use App\Services\MailNotificationService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function creerMembrePersonnel(string $role, array $attributs = []): User
{
    $user = User::factory()->create($attributs + [
        'is_active' => true,
        'password' => Hash::make('secret-initial-test'),
    ]);

    $roleModel = Role::where('slug', $role)->first();
    if ($roleModel) {
        $user->roles()->sync([$roleModel->id]);
    }

    return $user->fresh();
}

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['restaurant', 'shop', 'housekeeping']);
});

test('Lot 2 - V1 : Création d\'un compte sans champ mot de passe avec attribution du mot de passe par défaut et envoi du mail de bienvenue', function () {
    Mail::fake();

    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);
    $dept = Department::firstOrCreate(['slug' => 'hebergement'], ['name' => 'Hébergement', 'code' => 'HEB', 'is_active' => true]);

    $response = $this->actingAs($manager)->post(route('users.store'), [
        'name' => 'Sophie Mbarga',
        'email' => 'sophie.mbarga@wetchah.test',
        'phone' => '+237699001122',
        'department_id' => $dept->id,
        'roles' => ['reception'],
        'is_active' => true,
    ]);

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHas('success');

    $user = User::where('email', 'sophie.mbarga@wetchah.test')->first();
    expect($user)->not->toBeNull()
        ->and($user->must_change_password)->toBeTrue()
        ->and($user->temporary_password_expires_at)->toBeNull()
        ->and(Hash::check(config('auth.default_user_password'), $user->password))->toBeTrue()
        ->and($user->hasRole('reception'))->toBeTrue();

    // Mail de bienvenue expédié / mis en file
    Mail::assertQueued(UserWelcomeMail::class, function ($mail) use ($user) {
        return $mail->hasTo('sophie.mbarga@wetchah.test')
            && $mail->defaultPassword === config('auth.default_user_password');
    });

    // Journal d'audit renseigné
    $log = AuditLog::where('action', 'like', "%Création de l'utilisateur Sophie Mbarga%")->first();
    expect($log)->not->toBeNull()
        ->and($log->payload['target_user_id'])->toBe($user->id);
});

test('Lot 2 - V2 : Rejet strict de la création d\'un compte administrateur depuis l\'application hôtelière (APP)', function () {
    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);

    $response = $this->actingAs($manager)->post(route('users.store'), [
        'name' => 'Tentative Admin',
        'email' => 'hacker.admin@wetchah.test',
        'roles' => ['admin'],
        'is_active' => true,
    ]);

    // Doit être rejeté (403 ou erreur de validation)
    expect(in_array($response->status(), [403, 302]))->toBeTrue();
    if ($response->status() === 302) {
        $response->assertSessionHasErrors('roles.*');
    }

    expect(User::where('email', 'hacker.admin@wetchah.test')->exists())->toBeFalse();
});

test('Lot 2 - V3 : Affectation obligatoire à un restaurant pour le personnel de restauration', function () {
    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);

    // S'assurer qu'un restaurant existe
    $restaurant = PointOfSale::firstOrCreate([
        'kind' => PointOfSale::KIND_RESTAURATION,
        'is_active' => true,
    ], [
        'name' => 'Le Zingana Grill',
        'code' => 'RESTO-1',
    ]);

    $deptResto = Department::firstOrCreate(['slug' => 'restauration'], ['name' => 'Restauration', 'code' => 'RESTO', 'is_active' => true]);

    // 1. Rejet si pas de restaurant_id
    $response = $this->actingAs($manager)->post(route('users.store'), [
        'name' => 'Serveur Sans Resto',
        'email' => 'serveur.orphelin@wetchah.test',
        'department_id' => $deptResto->id,
        'roles' => ['restaurant_staff'],
        'is_active' => true,
    ]);

    $response->assertSessionHasErrors('restaurant_id');
    expect(User::where('email', 'serveur.orphelin@wetchah.test')->exists())->toBeFalse();

    // 2. Succès si restaurant_id fourni
    $responseSuccess = $this->actingAs($manager)->post(route('users.store'), [
        'name' => 'Serveur Assigné',
        'email' => 'serveur.assigne@wetchah.test',
        'department_id' => $deptResto->id,
        'roles' => ['restaurant_staff'],
        'restaurant_id' => $restaurant->id,
        'is_active' => true,
    ]);

    $responseSuccess->assertRedirect(route('users.index'));
    $serveur = User::where('email', 'serveur.assigne@wetchah.test')->first();
    expect($serveur)->not->toBeNull()
        ->and($serveur->restaurants()->where('points_of_sale.id', $restaurant->id)->exists())->toBeTrue();
});

test('Lot 2 - V4 : Interdiction d\'auto-élévation de privilèges ou d\'auto-modification depuis l\'écran de gestion', function () {
    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);

    $response = $this->actingAs($manager)->put(route('users.update', $manager), [
        'name' => 'Manager Modifié',
        'email' => $manager->email,
        'roles' => ['manager', 'admin'],
    ]);

    // Doit être interdit (403)
    $response->assertStatus(403);
});

test('Lot 2 - V5 : Interdiction de désactiver son propre compte', function () {
    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);

    $response = $this->actingAs($manager)->post(route('users.toggleStatus', $manager));

    $response->assertForbidden();
    expect($manager->fresh()->is_active)->toBeTrue();
});

test('Lot 2 - V6 : La désactivation d\'un utilisateur révoque remember_token et purge ses sessions actives', function () {
    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);
    $employe = creerMembrePersonnel('reception');
    $employe->update(['remember_token' => 'token-a-revoquer']);

    // Créer une fausse session en DB si table existe
    if (config('session.driver') === 'database') {
        DB::table(config('session.table', 'sessions'))->insert([
            'id' => 'session-employe-123',
            'user_id' => $employe->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'dummy',
            'last_activity' => time(),
        ]);
    }

    $response = $this->actingAs($manager)->post(route('users.toggleStatus', $employe));
    $response->assertRedirect();

    $employe->refresh();
    expect($employe->is_active)->toBeFalse()
        ->and($employe->remember_token)->not->toBe('token-a-revoquer');

    if (config('session.driver') === 'database') {
        expect(DB::table(config('session.table', 'sessions'))->where('user_id', $employe->id)->count())->toBe(0);
    }
});

test('Lot 2 - V7 : Réinitialisation du mot de passe par l\'admin/manager génère un mot de passe temporaire actif 5h et envoie un e-mail', function () {
    Mail::fake();

    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);
    $employe = creerMembrePersonnel('reception');

    $response = $this->actingAs($manager)->post(route('users.resetPassword', $employe));

    $response->assertRedirect();
    $response->assertSessionHas('motDePasseProvisoire');
    $provisoire = session('motDePasseProvisoire')['valeur'];

    $employe->refresh();
    expect($employe->must_change_password)->toBeTrue()
        ->and($employe->temporary_password_expires_at)->not->toBeNull()
        ->and($employe->hasValidTemporaryPassword())->toBeTrue()
        ->and($employe->temporary_password_expires_at->greaterThan(now()->addHours(4)->addMinutes(50)))->toBeTrue()
        ->and(Hash::check($provisoire, $employe->password))->toBeTrue();

    // Mail de réinitialisation envoyé / mis en file
    Mail::assertQueued(UserPasswordResetMail::class, function ($mail) use ($employe, $provisoire) {
        return $mail->hasTo($employe->email)
            && $mail->temporaryPassword === $provisoire
            && $mail->expiresInHours === 5;
    });

    // Journal d'audit
    expect(AuditLog::where('action', 'like', "%Mot de passe de {$employe->name}%réinitialisé%")->exists())->toBeTrue();
});

test('Lot 2 - V8 : Refus de connexion si mot de passe temporaire expiré (> 5 heures)', function () {
    $employe = creerMembrePersonnel('reception');
    $tempPass = 'TempPass1234!';
    $employe->forceFill([
        'password' => Hash::make($tempPass),
        'must_change_password' => true,
        'temporary_password_expires_at' => now()->subMinutes(10), // Expiré il y a 10 min
    ])->save();

    expect($employe->hasExpiredTemporaryPassword())->toBeTrue();

    $response = $this->post(route('login'), [
        'email' => $employe->email,
        'password' => $tempPass,
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('Lot 2 - V9 : Connexion acceptée si mot de passe temporaire encore valide (< 5 heures)', function () {
    $employe = creerMembrePersonnel('reception');
    $tempPass = 'ValidTemp1234!';
    $employe->forceFill([
        'password' => Hash::make($tempPass),
        'must_change_password' => true,
        'temporary_password_expires_at' => now()->addHours(3), // Valide pour encore 3 heures
    ])->save();

    expect($employe->hasValidTemporaryPassword())->toBeTrue();

    $response = $this->post(route('login'), [
        'email' => $employe->email,
        'password' => $tempPass,
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($employe);
    expect($employe->must_change_password)->toBeTrue();
});

test('Lot 2 - V10 : Changement obligatoire de mot de passe via le modal (ou route nouveau mot de passe)', function () {
    $employe = creerMembrePersonnel('reception');
    $tempPass = 'ValidTemp1234!';
    $employe->forceFill([
        'password' => Hash::make($tempPass),
        'must_change_password' => true,
        'temporary_password_expires_at' => now()->addHours(4),
    ])->save();

    // 1. Refus si identique au mot de passe temporaire
    $this->actingAs($employe)->put(route('password.nouveau.update'), [
        'password' => $tempPass,
        'password_confirmation' => $tempPass,
    ])->assertSessionHasErrors('password');

    // 2. Refus si mot de passe trop court (< 8 caractères)
    $this->actingAs($employe)->put(route('password.nouveau.update'), [
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    // 3. Succès si mot de passe personnel valide
    $nouveauPass = 'MonNouveauPassSecret2026!';
    $response = $this->actingAs($employe)->put(route('password.nouveau.update'), [
        'password' => $nouveauPass,
        'password_confirmation' => $nouveauPass,
    ]);

    $response->assertRedirect(route('dashboard'));
    $employe->refresh();

    expect($employe->must_change_password)->toBeFalse()
        ->and($employe->temporary_password_expires_at)->toBeNull()
        ->and(Hash::check($nouveauPass, $employe->password))->toBeTrue();
});

test('Lot 2 - V11 : Diagnostic e-mail et réexpédition via MailNotificationController', function () {
    Mail::fake();

    $manager = creerMembrePersonnel(RoleCatalog::MANAGER);
    $employe = creerMembrePersonnel('reception');

    // Test envoi diagnostic
    $responseDiag = $this->actingAs($manager)->postJson(route('mail.test'), [
        'email' => 'test-mailersend@wetchah.test',
    ]);
    $responseDiag->assertOk();
    $responseDiag->assertJson(['success' => true]);

    // Test réexpédition bienvenue
    $responseResend = $this->actingAs($manager)->post(route('users.resendWelcome', $employe));
    $responseResend->assertRedirect();
    $responseResend->assertSessionHas('success');

    Mail::assertQueued(UserWelcomeMail::class, function ($mail) use ($employe) {
        return $mail->hasTo($employe->email);
    });
});
