<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Barre latérale étirée ou réduite aux icônes.
 *
 * Le pliage est porté par une classe sur <html> et par du CSS : la page doit
 * donc livrer les points d'accroche — libellés encapsulés, infobulles de
 * relais, commande de bascule — sans lesquels l'état réduit deviendrait
 * illisible ou inatteignable.
 */

function utilisateurSidebar(string $role = 'manager'): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);

    return User::factory()->create(['role' => $role, 'is_active' => true]);
}

test('la page porte la commande de bascule et rétablit le choix avant peinture', function () {
    $html = $this->actingAs(utilisateurSidebar())->get('/dashboard')->assertOk()->getContent();

    expect($html)->toContain('basculerSidebar()')
        ->and($html)->toContain('aria-controls="mobile-sidebar"')
        // Le choix est relu dans le <head> : sans cela, la barre s'afficherait
        // large puis se rétracterait à chaque changement de page.
        ->and($html)->toContain("localStorage.getItem('sidebar-reduite')")
        ->and($html)->toContain("classList.add('sidebar-reduite')");

    // Le script de restauration précède le corps de page.
    expect(strpos($html, "localStorage.getItem('sidebar-reduite')"))
        ->toBeLessThan(strpos($html, '<body'));
});

test('les entrées de menu exposent libellé encapsulé et infobulle', function () {
    $html = $this->actingAs(utilisateurSidebar())->get('/dashboard')->assertOk()->getContent();

    // Le libellé doit être dans un élément pour pouvoir être masqué : un nœud
    // de texte nu ne se cache pas en CSS.
    expect($html)->toContain('<span class="sidebar-libelle">Tableau de bord</span>')
        ->and($html)->toContain('title="Tableau de bord"')
        ->and($html)->toContain('sidebar-lien');

    // Titres de groupes et identité de l'établissement : masquables aussi.
    expect($html)->toContain('sidebar-groupe-titre')
        ->and($html)->toContain('sidebar-identite');
});

test('la règle de repli ne vise que le grand écran', function () {
    $html = $this->actingAs(utilisateurSidebar())->get('/dashboard')->assertOk()->getContent();

    $media = strpos($html, '@media (min-width: 1024px)');
    $repli = strpos($html, 'html.sidebar-reduite #mobile-sidebar {');

    // En dessous de 1024 px la barre est un tiroir superposé : la réduire
    // n'aurait aucun sens, la règle doit rester dans la requête média.
    expect($media)->not->toBeFalse()
        ->and($repli)->toBeGreaterThan($media);
});

test('le sous-menu des réservations est masquable', function () {
    $html = $this->actingAs(utilisateurSidebar('reception'))->get('/bookings')->assertOk()->getContent();

    // Déplié, le sous-menu n'a pas sa place dans une gouttière de 60 px.
    expect($html)->toContain('sidebar-sous-menu')
        ->and($html)->toContain('<span class="sidebar-libelle">Réservations</span>');
});
