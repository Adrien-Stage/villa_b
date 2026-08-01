<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Les réglages d'hébergement tiennent en un seul onglet, alors que leurs
 * valeurs restent réparties sur deux clés de stockage distinctes
 * (« reception » et « hebergement »), lues ailleurs dans l'application.
 * Ces tests verrouillent les deux faces : une seule entrée à l'écran, et
 * aucun déplacement des données.
 */

function settingsManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);

    return User::factory()->create(['role' => 'manager', 'is_active' => true]);
}

test('un seul onglet porte le libellé Hébergement', function () {
    $this->actingAs(settingsManager());

    $html = $this->get(route('settings.index', ['tab' => 'hebergement']))->assertOk()->getContent();

    // On ne compte que les libellés des liens d'onglets, pas les titres de section.
    preg_match_all('/<a[^>]+href="[^"]*settings\?tab=[^"]*"[^>]*>.*?<\/a>/s', $html, $liens);
    $onglets = collect($liens[0])->filter(fn ($a) => str_contains($a, 'Hébergement'));

    expect($onglets)->toHaveCount(1);
});

test('les trois sections hébergement sont sur la même page', function () {
    $this->actingAs(settingsManager());

    $this->get(route('settings.index', ['tab' => 'hebergement']))
        ->assertOk()
        ->assertSee('Horaires et règles de séjour')
        ->assertSee('Remise en vente après départ')
        ->assertSee('Packs d\'hébergement', false);
});

test('l\'ancien lien vers l\'onglet réception retombe sur l\'onglet fusionné', function () {
    $this->actingAs(settingsManager());

    // Favoris et redirection d'après enregistrement arrivent avec ?tab=reception.
    $this->get(route('settings.index', ['tab' => 'reception']))
        ->assertOk()
        ->assertSee('Horaires et règles de séjour')
        ->assertSee('Remise en vente après départ');
});

test('les horaires restent stockés sous la clé reception', function () {
    $this->actingAs(settingsManager());

    $this->post(route('settings.update', ['tab' => 'reception']), [
        'settings' => ['check_out_time' => '11:00', 'check_in_time' => '15:00'],
    ])->assertRedirect();

    $settings = Tenant::first()->fresh()->settings;

    // Déplacer ces valeurs casserait BookingController, RoomType et
    // RoomAvailabilityService, qui les lisent sous « reception ».
    expect($settings['reception']['check_out_time'])->toBe('11:00')
        ->and($settings['reception']['check_in_time'])->toBe('15:00')
        ->and($settings['hebergement']['check_in_time'] ?? null)->toBeNull();
});

test('les délais restent stockés sous la clé hebergement', function () {
    $this->actingAs(settingsManager());

    $this->post(route('settings.update', ['tab' => 'hebergement']), [
        'settings' => ['cleaning_delay_minutes' => 45],
    ])->assertRedirect();

    $settings = Tenant::first()->fresh()->settings;

    expect($settings['hebergement']['cleaning_delay_minutes'])->toBe(45)
        ->and($settings['reception']['cleaning_delay_minutes'] ?? null)->toBeNull();
});

test('enregistrer les horaires ramène sur l\'onglet Hébergement', function () {
    $this->actingAs(settingsManager());

    $this->post(route('settings.update', ['tab' => 'reception']), [
        'settings' => ['check_out_time' => '12:00'],
    ])->assertRedirect()
        ->assertSessionHas('success');

    // La redirection porte l'ancien nom mais doit afficher l'onglet fusionné,
    // sinon le manager atterrit sur une page sans onglet actif.
    $this->followingRedirects()
        ->post(route('settings.update', ['tab' => 'reception']), [
            'settings' => ['check_out_time' => '12:00'],
        ])
        ->assertOk()
        ->assertSee('Horaires et règles de séjour');
});

test('la réception voit les horaires mais pas les packs', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $receptionniste = User::factory()->create(['role' => 'reception', 'is_active' => true]);

    // La fusion ne doit pas ouvrir à la réception des réglages réservés au
    // manager : elle règle les horaires, pas les tarifs des packs.
    $this->actingAs($receptionniste)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Horaires et règles de séjour')
        ->assertDontSee('Remise en vente après départ')
        ->assertDontSee('Packs d\'hébergement', false);
});
