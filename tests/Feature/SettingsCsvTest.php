<?php

use App\Models\PartnerOrganization;
use App\Models\RoomPackage;
use App\Models\ServiceItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CsvSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

if (!function_exists('settingsManager')) {
    function settingsManager(): User
    {
        test()->seed(\Database\Seeders\TenantSeeder::class);
        return User::factory()->create(['role' => 'manager', 'is_active' => true]);
    }
}

test('le modele d\'exportation des parametres generaux est telechargeable', function () {
    $this->actingAs(settingsManager());

    $response = $this->get(route('settings.export', ['tab' => 'general', 'template' => 1]))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('modele_parametres_general.csv');
});

test('l\'importation CSV met a jour le champ settings JSON du tenant pour l\'onglet general', function () {
    $this->actingAs(settingsManager());

    $csvContent = implode("\n", [
        "cle_parametre;valeur;type;description",
        "phone;+237 690000000;string;Téléphone de contact",
        "email;hotel@example.com;string;Email de contact",
        "city;Douala;string;Ville",
    ]);

    $file = UploadedFile::fake()->createWithContent('settings_general.csv', $csvContent);

    $response = $this->post(route('settings.import', ['tab' => 'general']), ['csv_file' => $file]);
    $response->assertRedirect(route('settings.index', ['tab' => 'general']));
    $response->assertSessionHas('success');

    $tenant = Tenant::first();
    expect($tenant->settings['general']['phone'] ?? null)->toBe('+237 690000000')
        ->and($tenant->settings['general']['email'] ?? null)->toBe('hotel@example.com')
        ->and($tenant->settings['general']['city'] ?? null)->toBe('Douala');
});

test('une cle non autorisee dans l\'onglet de parametres est rejetee par la whitelist', function () {
    $this->actingAs(settingsManager());

    $csvContent = implode("\n", [
        "cle_parametre;valeur;type;description",
        "cle_pirate;hacked;string;Tentative d'injection",
    ]);

    $file = UploadedFile::fake()->createWithContent('settings_general.csv', $csvContent);

    $response = $this->post(route('settings.import', ['tab' => 'general']), ['csv_file' => $file]);
    $response->assertRedirect();
    $response->assertSessionHas('import_errors');

    $errors = session('import_errors');
    expect($errors[0])->toContain('non autorisée');
});

test('un mauvais typage de valeur provoque le rollback et retourne une erreur', function () {
    $this->actingAs(settingsManager());

    $csvContent = implode("\n", [
        "cle_parametre;valeur;type;description",
        "min_deposit_percentage;pas_un_nombre;integer;Acompte minimum",
    ]);

    $file = UploadedFile::fake()->createWithContent('settings_hebergement.csv', $csvContent);

    $response = $this->post(route('settings.import', ['tab' => 'hebergement']), ['csv_file' => $file]);
    $response->assertRedirect();
    $response->assertSessionHas('import_errors');

    $errors = session('import_errors');
    expect($errors[0])->toContain('invalide');
});

test('l\'exportation et l\'importation CSV des prestations fonctionnent en upsert', function () {
    $this->actingAs(settingsManager());

    ServiceItem::create([
        'category' => 'spa',
        'name' => 'Massage suédois',
        'price' => 2000000, // 20 000 FCFA
        'duration_minutes' => 45,
        'is_active' => true,
    ]);

    $csvContent = implode("\n", [
        "categorie;nom;description;prix_fcfa;duree_minutes;actif",
        "Spa & bien-être;Massage suédois;Soin complet;25000;60;oui",
        "Blanchisserie;Nettoyage veste;Nettoyage à sec;3000;20;oui",
    ]);

    $file = UploadedFile::fake()->createWithContent('services.csv', $csvContent);

    $this->post(route('settings.services.import'), ['csv_file' => $file])
        ->assertRedirect(route('settings.index', ['tab' => 'services']));

    $massage = ServiceItem::where('category', 'spa')->where('name', 'Massage suédois')->first();
    expect($massage->price)->toBe(2500000) // 25 000 FCFA
        ->and($massage->duration_minutes)->toBe(60);

    $veste = ServiceItem::where('category', 'laundry')->where('name', 'Nettoyage veste')->first();
    expect($veste)->not->toBeNull()
        ->and($veste->price)->toBe(300000);
});

test('l\'exportation et l\'importation CSV des partenaires s\'appuient sur le code', function () {
    $this->actingAs(settingsManager());

    PartnerOrganization::create([
        'name' => 'Société Générale',
        'code' => 'SOC-GEN',
        'type' => 'company',
        'room_discount_type' => 'percent',
        'room_discount_value' => 10,
        'is_active' => true,
    ]);

    $csvContent = implode("\n", [
        "nom;code;type;nom_contact;email_contact;telephone_contact;remise_chambre_type;remise_chambre_valeur;remise_restaurant_pct;remise_boutique_pct;depart_tardif;arrivee_anticipee;date_debut;date_fin;actif;notes",
        "Société Générale;SOC-GEN;Entreprise;Marc V;contact@sg.com;+237 6000000;percent;15;10;5;oui;non;2026-01-01;2026-12-31;oui;Nouveau contrat",
    ]);

    $file = UploadedFile::fake()->createWithContent('partners.csv', $csvContent);

    $this->post(route('settings.partners.import'), ['csv_file' => $file])
        ->assertRedirect(route('settings.index', ['tab' => 'partners']));

    $partner = PartnerOrganization::where('code', 'SOC-GEN')->first();
    expect($partner->room_discount_value)->toBe(15)
        ->and($partner->restaurant_discount_percent)->toBe(10)
        ->and($partner->notes)->toBe('Nouveau contrat');
});

test('l\'exportation et l\'importation CSV des packs d\'hebergement fonctionnent', function () {
    $this->actingAs(settingsManager());

    $csvContent = implode("\n", [
        "nom;code;description;mode_tarification;prix_fcfa;repas;remise_chambre_type;remise_chambre_valeur;types_chambres;prestations_incluses;actif",
        "Pack Business;BUS-01;Demi-pension;Par personne et par nuitée;15000;breakfast|dinner;percent;10;Toutes;;oui",
    ]);

    $file = UploadedFile::fake()->createWithContent('packages.csv', $csvContent);

    $this->post(route('settings.packages.import'), ['csv_file' => $file])
        ->assertRedirect(route('settings.index', ['tab' => 'hebergement']));

    $pack = RoomPackage::where('code', 'BUS-01')->first();
    expect($pack)->not->toBeNull()
        ->and($pack->price)->toBe(1500000)
        ->and($pack->meals)->toBe(['breakfast', 'dinner']);
});

test('le sanitizer de formule neutralise les caracteres dangereux', function () {
    expect(CsvSanitizer::sanitizeCell('=SUM(A1:A10)'))->toBe("'=SUM(A1:A10)")
        ->and(CsvSanitizer::sanitizeCell('+cmd|/c calc'))->toBe("'+cmd|/c calc")
        ->and(CsvSanitizer::sanitizeCell('+237 600000000'))->toBe('+237 600000000')
        ->and(CsvSanitizer::sanitizeCell('Texte normal'))->toBe('Texte normal');
});
