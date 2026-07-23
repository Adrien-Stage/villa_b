<?php

use App\Models\Customer;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryItem;
use App\Models\ShopCategory;
use App\Models\ShopProduct;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/** Active des modules métier pour le test (TenantModules lit un cache statique). */
function enableModules(array $modules): void
{
    $prop = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, $modules);
}

/** Écrit un CSV temporaire (délimiteur ; avec BOM) et le renvoie en UploadedFile. */
function csvUpload(array $headerAndRows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
    $out = fopen($path, 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($headerAndRows as $row) {
        fputcsv($out, $row, ';', '"', '\\');
    }
    fclose($out);

    return new UploadedFile($path, 'import.csv', 'text/csv', null, true);
}

// ── Clients ──────────────────────────────────────────────────────────────

test('export clients renvoie un CSV avec en-têtes et données', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'manager']));
    Customer::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont', 'email' => 'jd@example.com', 'country' => 'CM']);

    $res = $this->get(route('customers.export'));
    $res->assertOk();
    $body = $res->streamedContent();

    expect($body)->toContain('prenom;nom;email')
        ->and($body)->toContain('Jean')
        ->and($body)->toContain('jd@example.com');
});

test('import clients crée les fiches et déduplique par email', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'reception']));
    Customer::factory()->create(['email' => 'existant@example.com']);

    $file = csvUpload([
        ['prenom', 'nom', 'email', 'telephone', 'pays', 'nationalite', 'type_piece', 'numero_piece', 'date_naissance', 'adresse', 'ville', 'vip', 'blackliste', 'notes'],
        ['Alice', 'Martin', 'alice@example.com', '', 'FR', '', '', '', '1990-05-01', '', 'Lyon', 'oui', 'non', ''],
        ['Bob', 'Doublon', 'existant@example.com', '', '', '', '', '', '', '', '', 'non', 'non', ''],   // ignoré
        ['', 'SansPrenom', 'x@example.com', '', '', '', '', '', '', '', '', '', '', ''],                  // erreur
    ]);

    $this->post(route('customers.import'), ['csv_file' => $file])->assertRedirect();

    $alice = Customer::where('email', 'alice@example.com')->first();
    expect($alice)->not->toBeNull()
        ->and($alice->country)->toBe('FR')
        ->and($alice->is_vip)->toBeTrue()
        ->and(Customer::where('email', 'existant@example.com')->count())->toBe(1);   // pas de doublon
    expect(session('import_errors'))->toHaveCount(1);   // la ligne sans prénom
});

// ── Boutique ─────────────────────────────────────────────────────────────

test('import boutique convertit le prix en centimes et génère un SKU si vide', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'shop_manager']));
    enableModules(['shop']);
    $cat = ShopCategory::create(['name' => 'Souvenirs', 'is_active' => true]);

    $file = csvUpload([
        ['sku', 'nom', 'categorie', 'description', 'prix_fcfa', 'stock', 'seuil_reappro', 'actif'],
        ['', 'Bracelet', 'Souvenirs', '', '5000', '20', '5', 'oui'],
        ['ART-000042', 'Carte', 'Souvenirs', '', '500', '100', '10', 'oui'],
        ['ART-000042', 'Doublon SKU', 'Souvenirs', '', '999', '1', '1', 'oui'],   // ignoré (déjà dans le lot)
        ['', 'Sans catégorie', 'Inexistante', '', '100', '1', '1', 'oui'],        // erreur
    ]);

    $this->post(route('shop.products.import'), ['csv_file' => $file])->assertRedirect();

    $bracelet = ShopProduct::where('name', 'Bracelet')->first();
    expect($bracelet)->not->toBeNull()
        ->and($bracelet->price)->toBe(500000)          // 5000 FCFA -> centimes
        ->and($bracelet->sku)->toStartWith('ART-')     // SKU auto-généré
        ->and(ShopProduct::where('sku', 'ART-000042')->count())->toBe(1);
    expect(session('import_errors'))->toHaveCount(1);
});

// ── Économat ─────────────────────────────────────────────────────────────

test('import économat crée les articles avec stock à zéro et fournisseur par nom', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'econome']));
    StockCategory::create(['name' => 'Entretien']);
    Supplier::create(['name' => 'Grossiste Central', 'is_active' => true]);

    $file = csvUpload([
        ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock_min', 'cout_moyen_fcfa', 'actif'],
        ['Savon', 'SAV-01', 'litre', 'Entretien', 'Grossiste Central', '10', '1200', 'oui'],
        ['Eau de javel', '', 'litre', 'Inconnue', '', '5', '800', 'oui'],   // catégorie introuvable -> erreur
    ]);

    $this->post(route('economat.items.import'), ['csv_file' => $file])->assertRedirect();

    $savon = StockItem::where('name', 'Savon')->first();
    expect($savon)->not->toBeNull()
        ->and((float) $savon->current_stock)->toBe(0.0)     // stock initial nul
        ->and($savon->average_cost)->toBe(120000)           // 1200 FCFA -> centimes
        ->and($savon->supplier?->name)->toBe('Grossiste Central')
        ->and($savon->category?->name)->toBe('Entretien');
    expect(session('import_errors'))->toHaveCount(1);
});

// ── Menus ────────────────────────────────────────────────────────────────

test('import menus mappe les repas français et rejette un type invalide', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'restaurant_chief']));
    enableModules(['restaurant']);
    RestaurantMenuCategory::create(['name' => 'Plats', 'is_active' => true]);

    $file = csvUpload([
        ['categorie', 'nom', 'description', 'type', 'prix_fcfa', 'services', 'actif'],
        ['Plats', 'Ndolè', 'Maison', 'food', '6000', 'Déjeuner|Dîner', 'oui'],
        ['Plats', 'Erreur type', '', 'plat', '1000', '', 'oui'],   // type invalide -> erreur
    ]);

    $this->post(route('restaurant.menus.import'), ['csv_file' => $file])->assertRedirect();

    $ndole = RestaurantMenuItem::where('name', 'Ndolè')->first();
    expect($ndole)->not->toBeNull()
        ->and($ndole->price)->toBe(600000)
        ->and($ndole->meal_services)->toBe(['lunch', 'dinner']);   // FR -> clés
    expect(session('import_errors'))->toHaveCount(1);
});

test('aller-retour menus : export puis ré-import ne perd pas de données', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'restaurant_chief']));
    enableModules(['restaurant']);
    $cat = RestaurantMenuCategory::create(['name' => 'Boissons', 'is_active' => true]);
    RestaurantMenuItem::create([
        'restaurant_menu_category_id' => $cat->id, 'name' => 'Jus de gingembre',
        'price' => 150000, 'type' => 'drink', 'meal_services' => ['lunch'], 'is_active' => true,
    ]);

    $exported = $this->get(route('restaurant.menus.export'))->streamedContent();

    // On réécrit l'export dans un fichier et on le ré-importe : le plat existe
    // déjà (même nom) donc il est ignoré, sans erreur ni doublon.
    $path = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
    file_put_contents($path, $exported);
    $file = new UploadedFile($path, 'menus.csv', 'text/csv', null, true);

    $this->post(route('restaurant.menus.import'), ['csv_file' => $file])->assertRedirect();

    expect(RestaurantMenuItem::where('name', 'Jus de gingembre')->count())->toBe(1);
    expect(session('import_errors') ?? [])->toHaveCount(0);
});

// ── Garde-manger ─────────────────────────────────────────────────────────

test('import garde-manger valide l\'unité et convertit le coût', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'restaurant_chief']));
    enableModules(['restaurant']);

    $file = csvUpload([
        ['categorie', 'nom', 'unite', 'preparation', 'unite_achat', 'conversion_achat', 'cout_fcfa', 'stock_min', 'actif'],
        ['', 'Riz', 'g', 'non', 'sac 25kg', '25000', '900', '5000', 'oui'],
        ['', 'Mauvaise unité', 'litres', 'non', '', '', '', '0', 'oui'],   // unité invalide -> erreur
    ]);

    $this->post(route('restaurant.pantry.import'), ['csv_file' => $file])->assertRedirect();

    $riz = RestaurantPantryItem::where('name', 'Riz')->first();
    expect($riz)->not->toBeNull()
        ->and($riz->unit)->toBe('g')
        ->and($riz->cost_price)->toBe(90000)              // 900 FCFA -> centimes
        ->and((float) $riz->current_stock)->toBe(0.0);
    expect(session('import_errors'))->toHaveCount(1);
});

// ── Contrôle d'en-têtes ──────────────────────────────────────────────────

test('un CSV aux colonnes manquantes est rejeté proprement', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $this->actingAs(User::factory()->create(['role' => 'manager']));

    $file = csvUpload([
        ['prenom', 'nom'],   // colonnes manquantes
        ['Jean', 'Dupont'],
    ]);

    $this->post(route('customers.import'), ['csv_file' => $file])
        ->assertRedirect()
        ->assertSessionHas('error');
    expect(Customer::where('last_name', 'Dupont')->count())->toBe(0);
});
