<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function creerTestManager(): User
{
    $manager = User::factory()->create([
        'is_active' => true,
        'password' => Hash::make('secret-manager-test'),
    ]);
    $roleModel = Role::where('slug', RoleCatalog::MANAGER)->first();
    if ($roleModel) {
        $manager->roles()->sync([$roleModel->id]);
    }

    return $manager->fresh();
}

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['restaurant', 'shop']);

    Department::firstOrCreate(['slug' => 'reception'], [
        'name' => 'Réception',
        'code' => 'REC',
        'is_active' => true,
    ]);
});

test('les vues et layouts principaux intègrent le système universel de toasts', function () {
    $manager = creerTestManager();

    $response = $this->actingAs($manager)->get(route('users.index'));
    $response->assertOk();
    $response->assertSee('wetchah-toast-root');
    $response->assertSee('toastNotificationManager');
    $response->assertSee('window.toast =');
});

test('une validation échouée lors de la création d\'utilisateur alimente le toast d\'erreur', function () {
    $manager = creerTestManager();

    $response = $this->actingAs($manager)->post(route('users.store'), [
        'form_type' => 'create',
        'name' => '', // Obligatoire
        'email' => 'email-non-valide',
        'roles' => [], // Obligatoire
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'roles']);

    // Suivre la redirection vers users.index
    $suivi = $this->actingAs($manager)->get(route('users.index'));
    $suivi->assertOk();
    $suivi->assertSee('wetchah-toast-root');
    $suivi->assertSee('openCreateModal');
});

test('la création réussie d\'un collaborateur transmet un toast de succès avec envoi synchrone', function () {
    Mail::fake();

    $manager = creerTestManager();
    $dept = Department::where('slug', 'reception')->first();

    $response = $this->actingAs($manager)->post(route('users.store'), [
        'form_type' => 'create',
        'name' => 'Marc Dupont',
        'email' => 'marc.dupont@test.com',
        'phone' => '+237 677 12 34 56',
        'department_id' => $dept->id,
        'roles' => ['reception'],
        'levels' => ['reception' => 'write'],
        'is_active' => '1',
    ]);

    $response->assertRedirect(route('users.index'));
    $response->assertSessionHas('success');

    $suivi = $this->actingAs($manager)->get(route('users.index'));
    $suivi->assertOk();
    $suivi->assertSee('Membre du staff Marc Dupont créé avec succès');
});
