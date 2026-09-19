<?php

/**
 * La garde par droit refuse et autorise exactement comme la garde par rôles.
 *
 * Phase 1 : les 251 routes gardées ne nomment plus leurs rôles, elles portent
 * « permission », et EnsurePermission résout le droit depuis le nom de route
 * avant de déléguer le refus à EnsureRoleAccess.
 *
 * La conformité du catalogue est vérifiée ailleurs, sur la table de routage.
 * Ici on vérifie le résultat vu de l'extérieur : qui passe, qui est refusé.
 */

use App\Models\User;
use App\Support\DepartmentRoles;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    activerModules(['economat', 'comptabilite', 'ledger', 'accounting', 'hebergement', 'reservations']);
});

test("l'économe entre dans l'économat", function () {
    $this->actingAs(User::factory()->create(['role' => 'econome']))
        ->get('/economat/articles')
        ->assertOk();
});

test("un rôle sans le droit est refusé, jamais servi", function (string $role) {
    $reponse = $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/economat/articles');

    expect($reponse->status())->not->toBe(200);
})->with(['housekeeping_staff', 'restaurant_cook', 'shop_cashier']);

test("le refus par droit est journalisé, comme avant la bascule", function () {
    // Le comptable, et non le housekeeping : lui seul franchit
    // « module.access:economat » — le module figure dans ses accès — pour être
    // arrêté par la garde du droit. Un rôle sans le module est refusé plus tôt,
    // par un autre middleware, qui ne journalise pas de la même façon.
    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get('/economat/articles');

    $this->assertDatabaseHas('audit_logs', ['event_type' => 'access_denied', 'module' => 'security']);
});

test("une requête JSON reçoit 403 et le motif, pas une redirection", function () {
    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->getJson('/economat/articles')
        ->assertStatus(403)
        ->assertJson(['access_denied' => true]);
});

test("un visiteur non authentifié est renvoyé au login", function () {
    $this->get('/economat/articles')->assertRedirect(route('login'));
});

test("le département confère toujours ses rôles implicites", function () {
    // Voie d'octroi préexistante, déplacée dans DepartmentRoles sans changer
    // d'effet : un membre de la réception accède aux demandes à l'économat
    // sans détenir le rôle « reception ».
    expect(DepartmentRoles::for('reception_front_office'))->toContain('reception')
        ->and(PermissionCatalog::roles('economat.requisitions.voir'))->toContain('reception');
});

test("les rôles qui détiennent un droit sont ceux que le catalogue déclare", function () {
    // Le manager ne saisit plus à l'économat : il y consulte.
    expect(PermissionCatalog::roles('economat.items.creer'))->toBe(['econome'])
        ->and(PermissionCatalog::roles('economat.items.voir'))->toContain('manager');
});
