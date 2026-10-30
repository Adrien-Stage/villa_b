<?php

use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockUnit;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/*
 * Les unités de stockage des articles : l'économe et la direction en tiennent
 * la liste dans Paramètres › Économat, et la fiche d'un article y choisit la
 * sienne au lieu de la taper. « kg », « Kg » et « kilo » ne cohabitent plus.
 */

beforeEach(function () {
    RoleCatalog::sync();
    // Comme sur un établissement réel : l'économat est un module cœur,
    // toujours actif, que la console ne liste jamais dans TENANT_MODULES.
    activerModules([]);
});

function membreEconomat(string $role, string $nom): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test("la liste part des unités courantes, et l'économe la règle dans Paramètres", function () {
    expect(StockUnit::choix())->toContain('pièce', 'kg', 'litre', 'casier');

    $this->actingAs(membreEconomat('econome', 'Économe'));

    // L'économe n'a que cet onglet : Paramètres s'ouvre dessus.
    $this->get(route('settings.index'))->assertOk()->assertSee('Unités de mesure & de stockage', false)->assertSee('Ajouter une unité');

    $this->post(route('economat.units.store'), ['name' => 'bidon'])
        ->assertRedirect(route('settings.index', ['tab' => 'economat']));
    expect(StockUnit::choix())->toContain('bidon');

    // « KG » est « kg », « PIÈCE » est « pièce ».
    $this->post(route('economat.units.store'), ['name' => 'KG'])->assertSessionHasErrors('name');
    $this->post(route('economat.units.store'), ['name' => 'PIÈCE'])->assertSessionHasErrors('name');
    expect(StockUnit::where('name', 'KG')->exists())->toBeFalse();
});

test("renommer une unité renomme celle des articles ; une unité employée se met hors service", function () {
    $this->actingAs(membreEconomat('econome', 'Économe'));
    $casier = StockUnit::where('name', 'casier')->sole();
    $biere = StockItem::create(['name' => 'Bière 65 cl', 'unit' => 'casier']);

    $this->put(route('economat.units.update', $casier), ['name' => 'casier 12', 'is_active' => 1])->assertSessionHasNoErrors();
    expect($biere->fresh()->unit)->toBe('casier 12');

    $this->delete(route('economat.units.destroy', $casier))->assertSessionHasErrors('unite');
    expect(StockUnit::whereKey($casier->id)->exists())->toBeTrue();

    // Hors service : plus proposée, mais l'article qui l'emploie se modifie encore.
    $this->put(route('economat.units.update', $casier), ['name' => 'casier 12', 'is_active' => 0]);
    expect(StockUnit::choix())->not->toContain('casier 12')
        ->and(StockUnit::choix('casier 12'))->toContain('casier 12');

    $this->put(route('economat.items.update', $biere), ['name' => 'Bière 65 cl (casier)', 'unit' => 'casier 12'])
        ->assertSessionHasNoErrors();
    expect($biere->fresh()->name)->toBe('Bière 65 cl (casier)');

    // Une unité que personne n'emploie se supprime.
    $lot = StockUnit::where('name', 'lot')->sole();
    $this->delete(route('economat.units.destroy', $lot))->assertSessionHasNoErrors();
    expect(StockUnit::whereKey($lot->id)->exists())->toBeFalse();
});

test("l'unité d'un nouvel article se choisit dans la liste", function () {
    // Les articles se créent au service achats.
    $this->actingAs(membreEconomat('purchasing_manager', 'Acheteur'));

    $this->get(route('economat.items.index'))->assertOk()->assertSee('Gérer les unités');

    $this->post(route('economat.items.store'), ['name' => 'Riz parfumé', 'unit' => 'kilo'])->assertSessionHasErrors('unit');
    $this->post(route('economat.items.store'), ['name' => 'Riz parfumé', 'unit' => 'kg'])->assertSessionHasNoErrors();

    expect(StockItem::where('name', 'Riz parfumé')->sole()->unit)->toBe('kg');
});

test("l'import ramène l'unité à celle de la liste et refuse une unité inconnue", function () {
    // L'import des articles : le service achats.
    $this->actingAs(membreEconomat('purchasing_manager', 'Acheteur'));

    $chemin = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
    $fichier = fopen($chemin, 'w');
    foreach ([
        ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock_min', 'cout_moyen_fcfa', 'actif'],
        ['Farine', '', 'KG', '', '', '5', '600', 'oui'],
        ['Huile', '', 'bidonnet', '', '', '2', '1500', 'oui'],
    ] as $ligne) {
        fputcsv($fichier, $ligne, ';', '"', '\\');
    }
    fclose($fichier);

    $this->post(route('economat.items.import'), ['csv_file' => new UploadedFile($chemin, 'import.csv', 'text/csv', null, true)])
        ->assertRedirect();

    expect(StockItem::where('name', 'Farine')->sole()->unit)->toBe('kg')
        ->and(StockItem::where('name', 'Huile')->exists())->toBeFalse()
        ->and(session('import_errors'))->toHaveCount(1);
});

test("la direction tient aussi la liste ; le magasinier n'a que son profil", function () {
    $this->actingAs(membreEconomat('manager', 'Directrice'));

    $this->get(route('settings.index', ['tab' => 'economat']))->assertOk()
        ->assertSee('Unités de mesure & de stockage', false)
        ->assertSee('Ajouter une unité');
    $this->post(route('economat.units.store'), ['name' => 'bidon'])
        ->assertRedirect(route('settings.index', ['tab' => 'economat']));
    expect(StockUnit::where('name', 'bidon')->exists())->toBeTrue();

    // Le magasinier ne règle rien : la tuile Paramètres le mène à son profil.
    $this->actingAs(membreEconomat('storekeeper', 'Magasinier'))
        ->get(route('settings.index'))->assertRedirect(route('profile.edit'));
});

test("la tuile Paramètres de l'économe ouvre l'onglet Économat, sans « economat » dans TENANT_MODULES", function () {
    $econome = membreEconomat('econome', 'Économe');

    expect(\App\Support\SettingsTabs::parDefaut($econome))->toBe('economat');
    $this->actingAs($econome)->get(route('settings.index'))->assertOk()
        ->assertSee('Unités de mesure & de stockage', false)
        ->assertSee('Ajouter une unité');
});
