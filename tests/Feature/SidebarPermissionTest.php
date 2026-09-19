<?php

/**
 * La barre latérale suit la matrice des droits.
 *
 * Elle était gardée par le rôle, la route par le droit. Un droit accordé
 * depuis la console ouvrait donc la page sans jamais faire apparaître son
 * entrée de menu : l'accès n'existait que pour qui connaissait l'URL. Et un
 * droit retiré laissait le lien en place, menant à un refus.
 */

use App\Models\PermissionGrant;
use App\Models\User;
use App\Services\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => activerModules([
    'economat', 'comptabilite', 'accounting', 'ledger', 'hebergement',
    'reservations', 'clients', 'analytics', 'restaurant', 'shop',
]));

function accorder(string $role, string $permission): void
{
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE,
        'subject_id'   => $role,
        'permission'   => $permission,
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'reason'       => 'Lecture pour la comptabilité.',
    ]);

    app(PermissionResolver::class)->forget();
}

/** Libellés des liens de la barre latérale, pour cet utilisateur. */
function liens(User $utilisateur): array
{
    $html = test()->actingAs($utilisateur)->get('/dashboard')->getContent();
    preg_match('#<nav class="flex-1 overflow-y-auto.*?</nav>#s', $html, $nav);
    preg_match_all('#<span class="sidebar-libelle">(.*?)</span>#s', $nav[0] ?? '', $l);

    return array_map('trim', $l[1] ?? []);
}

test("sans droit accordé, le comptable ne voit pas l'économat", function () {
    expect(liens(User::factory()->create(['role' => 'accountant'])))
        ->not->toContain("Vue d'ensemble");
});

test("le droit accordé depuis la matrice fait apparaître son entrée de menu", function () {
    $comptable = User::factory()->create(['role' => 'accountant']);

    accorder('accountant', 'economat.voir');

    // C'est le défaut signalé : la page s'ouvrait, le menu restait muet.
    expect(liens($comptable))->toContain("Vue d'ensemble");
});

test("le groupe de menu s'ouvre dès qu'une de ses entrées est permise", function () {
    $comptable = User::factory()->create(['role' => 'accountant']);

    accorder('accountant', 'economat.items.voir');

    $page = $this->actingAs($comptable)->get('/dashboard')->getContent();

    expect($page)->toContain('Économat')
        ->and(liens($comptable))->toContain('Articles')
        // Seule l'entrée permise apparaît : le reste du groupe reste fermé.
        ->and(liens($comptable))->not->toContain('Fournisseurs');
});

test("un droit retiré fait disparaître son entrée", function () {
    $econome = User::factory()->create(['role' => 'econome']);

    expect(liens($econome))->toContain('Articles');

    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'econome',
        'permission' => 'economat.items.voir', 'effect' => PermissionGrant::EFFET_DENY,
        'reason' => 'Séparation des tâches.',
    ]);
    app(PermissionResolver::class)->forget();

    // Un lien qui mène à un refus est pire qu'un lien absent.
    expect(liens($econome))->not->toContain('Articles');
});

test("les rôles d'origine gardent exactement leur menu", function (string $role, string $attendu) {
    expect(liens(User::factory()->create(['role' => $role])))->toContain($attendu);
})->with([
    ['manager', 'Chambres'],
    ['reception', 'Agenda'],
    ['econome', 'Fournisseurs'],
    ['accountant', 'Comptabilité'],
]);

test("le tableau de bord reste visible pour tous", function (string $role) {
    expect(liens(User::factory()->create(['role' => $role])))->toContain('Tableau de bord');
})->with(['manager', 'accountant', 'econome', 'reception', 'housekeeping_staff']);
