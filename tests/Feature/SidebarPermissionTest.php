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

test("le libellé Hôtel disparaît pour le comptable", function () {
    $comptable = User::factory()->create(['role' => 'accountant']);

    $html = $this->actingAs($comptable)->get('/dashboard')->getContent();

    expect($html)->not->toContain('>Hôtel<');
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

test("l'économat n'apparaît qu'une fois, même avec plusieurs droits", function () {
    $comptable = User::factory()->create(['role' => 'accountant']);

    accorder('accountant', 'economat.voir');
    accorder('accountant', 'economat.requisitions.voir');

    $html = $this->actingAs($comptable)->get('/dashboard')->getContent();
    preg_match('#<nav class="flex-1 overflow-y-auto.*?</nav>#s', $html, $nav);

    // Deux rubriques « Économat » menaient toutes deux à la même route : les
    // deux entrées se surlignaient ensemble, et le menu mentait sur sa structure.
    expect(substr_count($nav[0], '>Économat<'))->toBe(1);
});

test("le libellé des demandes dit l'étendue", function () {
    $chef = User::factory()->create(['role' => 'restaurant_chief']);

    // Étendue par défaut : il voit les demandes du service.
    expect(liens($chef))->toContain('Demandes')->not->toContain('Mes demandes');

    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE, 'subject_id' => 'restaurant_chief',
        'permission' => 'economat.requisitions.voir', 'effect' => PermissionGrant::EFFET_ALLOW,
        'scope' => \App\Support\PermissionScope::PROPRE, 'reason' => 'Chacun ses demandes.',
    ]);
    app(PermissionResolver::class)->forget();

    expect(liens($chef))->toContain('Mes demandes');
});

test("le comptable voit le bon de commande, pas la demande interne", function () {
    $comptable = User::factory()->create(['role' => 'accountant']);

    $html = $this->actingAs($comptable)->get('/dashboard')->getContent();
    preg_match('#<nav class="flex-1 overflow-y-auto.*?</nav>#s', $html, $nav);

    expect(liens($comptable))->not->toContain('Mes demandes')
        ->and(liens($comptable))->toContain('Bons de commande');

    expect(mb_strpos($nav[0], 'Bons de commande'))->toBeGreaterThan(mb_strpos($nav[0], '>Comptabilité<'));
});

test("qui tient le magasin garde ses demandes dans la rubrique Économat", function () {
    $econome = User::factory()->create(['role' => 'econome']);

    $html = $this->actingAs($econome)->get('/dashboard')->getContent();
    preg_match('#<nav class="flex-1 overflow-y-auto.*?</nav>#s', $html, $nav);

    expect(array_count_values(liens($econome))['Demandes'] ?? 0)->toBe(1)
        ->and(mb_strpos($nav[0], 'Demandes'))->toBeGreaterThan(mb_strpos($nav[0], '>Économat<'));
});

test("un responsable de service qui ne tient pas les livres garde l'entrée sous Économat", function () {
    // Il n'a pas de section à lui : la lui retirer de l'Économat le priverait
    // de tout accès à ses demandes.
    expect(liens(User::factory()->create(['role' => 'restaurant_chief'])))->toContain('Demandes');
});
