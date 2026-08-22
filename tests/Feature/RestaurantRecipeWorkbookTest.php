<?php

namespace Tests\Feature;

use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\RestaurantPantryCategory;
use App\Models\RestaurantPantryItem;
use App\Models\RestaurantRecipe;
use App\Models\RestaurantRecipeLine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as LecteurXlsx;

uses(RefreshDatabase::class);

/**
 * Classeur Excel des fiches techniques de cuisine : il doit reprendre la
 * structure du document de référence de la restauration — carte, mercuriale,
 * fiches de préparation et de plats — et porter l'identité de l'établissement,
 * exactement comme le classeur de l'hébergement.
 */

function cuisineManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);
    $user = User::factory()->create(['role' => 'restaurant_chief', 'is_active' => true]);

    $prop = new \ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['restaurant']);

    return $user;
}

/** Un article de garde-manger. Le coût est en centimes par unité de suivi. */
function article(string $nom, string $unite = 'g', float $coutCentimes = 250, ?string $categorie = null, bool $prepare = false): RestaurantPantryItem
{
    $categorieId = $categorie
        ? RestaurantPantryCategory::firstOrCreate(['name' => $categorie], ['is_active' => true])->id
        : null;

    return RestaurantPantryItem::create([
        'restaurant_pantry_category_id' => $categorieId,
        'name'          => $nom,
        'unit'          => $unite,
        'is_prepared'   => $prepare,
        'cost_price'    => (int) round($coutCentimes),
        'average_cost'  => $coutCentimes,
        'current_stock' => 10000,
        'min_stock'     => 0,
        'is_active'     => true,
    ]);
}

function platCarte(string $nom, int $prixCentimes = 650000, string $categorie = 'Plats Chauds'): RestaurantMenuItem
{
    $cat = RestaurantMenuCategory::firstOrCreate(['name' => $categorie], ['is_active' => true]);

    return RestaurantMenuItem::create([
        'restaurant_menu_category_id' => $cat->id,
        'name'      => $nom,
        'price'     => $prixCentimes,
        'type'      => 'food',
        'is_active' => true,
    ]);
}

/**
 * Le jeu d'essai du document de référence : une sauce ndolé produite en batch
 * de 5 kg, et le plat qui en sert 200 g.
 *
 * @return array{0:RestaurantRecipe,1:RestaurantRecipe,2:RestaurantPantryItem}
 */
function ficheDeReference(): array
{
    $feuilles  = article('Feuilles de ndolé', 'g', 250, 'Légumes & Feuilles');
    $arachide  = article('Arachide décortiquée', 'g', 180, 'Épicerie / Grains');
    $boeuf     = article('Viande de Boeuf', 'g', 350, 'Boucherie');
    $sauceItem = article('Sauce Ndole', 'g', 189, 'Préparations', true);

    $sauce = RestaurantRecipe::create([
        'name'                    => 'Sauce Ndole',
        'type'                    => RestaurantRecipe::TYPE_PREP,
        'produces_pantry_item_id' => $sauceItem->id,
        'yield_quantity'          => 5000,
        'notes'                   => "Faire blanchir les feuilles.\nMonter la sauce à l'arachide.",
        'is_active'               => true,
    ]);

    RestaurantRecipeLine::create([
        'restaurant_recipe_id' => $sauce->id, 'restaurant_pantry_item_id' => $feuilles->id,
        'quantity' => 2000, 'waste_percent' => 0,
    ]);
    RestaurantRecipeLine::create([
        'restaurant_recipe_id' => $sauce->id, 'restaurant_pantry_item_id' => $arachide->id,
        'quantity' => 1500, 'waste_percent' => 0,
    ]);

    $carte = platCarte('Ndolé aux crevettes', 650000, 'Plats Chauds / Spécialités Camerounaises');

    $plat = RestaurantRecipe::create([
        'name'                    => 'Ndolé aux crevettes',
        'type'                    => RestaurantRecipe::TYPE_DISH,
        'restaurant_menu_item_id' => $carte->id,
        'yield_quantity'          => 1,
        'notes'                   => "Réchauffer 200 g de sauce.\nDresser en dôme.\nServir avec 2 plantains.",
        'is_active'               => true,
    ]);

    RestaurantRecipeLine::create([
        'restaurant_recipe_id' => $plat->id, 'restaurant_pantry_item_id' => $sauceItem->id,
        'quantity' => 200, 'waste_percent' => 0,
    ]);
    RestaurantRecipeLine::create([
        'restaurant_recipe_id' => $plat->id, 'restaurant_pantry_item_id' => $boeuf->id,
        'quantity' => 100, 'waste_percent' => 20,
    ]);

    return [$sauce, $plat, $sauceItem];
}

/** Télécharge l'export et rouvre le classeur, comme le ferait Excel. */
function classeurCuisine(array $params = []): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $reponse = test()->get(route('restaurant.recipes.export', $params))->assertOk();

    ob_start();
    $reponse->baseResponse->sendContent();
    $binaire = ob_get_clean();

    $chemin = tempnam(sys_get_temp_dir(), 'cuisine') . '.xlsx';
    file_put_contents($chemin, $binaire);

    $classeur = (new LecteurXlsx())->load($chemin);
    @unlink($chemin);

    return $classeur;
}

test("l'export par défaut est un classeur Excel", function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();

    $reponse = $this->get(route('restaurant.recipes.export'))->assertOk();

    expect($reponse->headers->get('Content-Type'))
        ->toContain('spreadsheetml.sheet')
        ->and($reponse->headers->get('Content-Disposition'))->toContain('.xlsx');
});

test('le classeur reprend la structure du document de référence', function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();

    $onglets = classeurCuisine()->getSheetNames();

    // La carte et la mercuriale ouvrent le classeur ; les préparations
    // précèdent les plats qui les consomment.
    expect($onglets[0])->toBe('📊 Carte & Rentabilité Menu')
        ->and($onglets[1])->toBe('🛒 Mercuriale & Ingrédients')
        ->and($onglets[2])->toBe('🥣 Prep - Sauce Ndole')
        ->and($onglets[3])->toBe('🍽️ Plat - Ndolé aux crevettes');
});

test('la fiche de préparation chiffre le batch, le kilo et la portion', function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();

    $ws = classeurCuisine()->getSheetByName('🥣 Prep - Sauce Ndole');

    expect($ws->getCell('A3')->getValue())->toBe('1. INFORMATIONS GÉNÉRALES & RENDEMENT')
        ->and($ws->getCell('B4')->getValue())->toBe('Sauce Ndole')
        ->and($ws->getCell('B5')->getValue())->toBe('PREP-SAUC-01')
        ->and($ws->getCell('B6')->getValue())->toBe(5000.0)
        // 200 g de sauce par assiette : la portion vient de l'usage réel.
        ->and($ws->getCell('B7')->getValue())->toBe(200.0)
        ->and($ws->getCell('B8')->getValue())->toBe('=IFERROR(B6/B7,0)');

    // Composition : 2 000 g de feuilles à 2,50 FCFA, 1 500 g d'arachide à 1,80.
    expect($ws->getCell('A12')->getValue())->toBe('ING-003')
        ->and($ws->getCell('B12')->getValue())->toBe('Feuilles de ndolé')
        ->and($ws->getCell('C12')->getValue())->toBe(2000.0)
        ->and($ws->getCell('G12')->getValue())->toBe(2.5)
        ->and($ws->getCell('H12')->getValue())->toBe('=C12*G12')
        ->and($ws->getCell('A13')->getValue())->toBe('ING-002')
        ->and($ws->getCell('B13')->getValue())->toBe('Arachide décortiquée')
        ->and($ws->getCell('C13')->getValue())->toBe(1500.0)
        ->and($ws->getCell('G13')->getValue())->toBe(1.8);

    // Total du batch : 2 700 + 5 000 = 7 700 FCFA.
    expect($ws->getCell('A14')->getValue())->toBe('TOTAL DE LA PRÉPARATION')
        ->and($ws->getCell('H14')->getValue())->toBe('=SUM(H12:H13)');

    // Les ratios pointent le total, jamais une valeur recopiée.
    expect($ws->getCell('A17')->getValue())->toContain('COÛT TOTAL DU BATCH')
        ->and($ws->getCell('D17')->getValue())->toBe('=H14')
        ->and($ws->getCell('A18')->getValue())->toBe('COÛT AU KILOGRAMME (1 KG)')
        ->and($ws->getCell('D18')->getValue())->toBe('=IFERROR(H14/$B$6*1000,0)')
        ->and($ws->getCell('A19')->getValue())->toBe("COÛT À L'UNITÉ (1 g)")
        ->and($ws->getCell('A20')->getValue())->toBe('COÛT PAR PORTION DE 200 g')
        ->and($ws->getCell('D20')->getValue())->toBe('=IFERROR(H14/$B$6*$B$7,0)');
});

test('la fiche de plat calcule le food cost et la rentabilité par formules', function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();

    $ws = classeurCuisine()->getSheetByName('🍽️ Plat - Ndolé aux crevettes');

    expect($ws->getCell('B4')->getValue())->toBe('Ndolé aux crevettes')
        ->and($ws->getCell('B5')->getValue())->toBe('Plats Chauds / Spécialités Camerounaises')
        ->and($ws->getCell('B7')->getValue())->toBe(6500.0);

    // La freinte de 20 % gonfle la quantité brute : 100 g servis → 125 g sortis.
    $boeuf = collect([13, 14])->first(fn ($l) => $ws->getCell("B{$l}")->getValue() === 'Viande de Boeuf');
    expect($boeuf)->not->toBeNull()
        ->and($ws->getCell("C{$boeuf}")->getValue())->toBe(125.0)
        ->and($ws->getCell("E{$boeuf}")->getValue())->toBe(0.2)
        ->and($ws->getCell("F{$boeuf}")->getValue())->toBe("=C{$boeuf}*(1-E{$boeuf})")
        ->and($ws->getCell("H{$boeuf}")->getValue())->toBe("=C{$boeuf}*G{$boeuf}");

    // Une préparation maison s'annonce comme telle et renvoie vers son onglet.
    $sauce = collect([13, 14])->first(fn ($l) => $ws->getCell("B{$l}")->getValue() === 'Sauce Ndole');
    expect($ws->getCell("A{$sauce}")->getValue())->toBe('Préparation de base')
        ->and($ws->getCell("I{$sauce}")->getValue())->toContain('🥣 Prep - Sauce Ndole');

    expect($ws->getCell('A15')->getValue())->toBe('TOTAL COÛT DE REVIENT MATIÈRE (FOOD COST)')
        ->and($ws->getCell('H15')->getValue())->toBe('=SUM(H13:H14)');

    // Synthèse : tout se déduit du food cost et du prix de vente.
    expect($ws->getCell('A18')->getValue())->toBe('COÛT DE REVIENT MATIÈRE (FOOD COST)')
        ->and($ws->getCell('D18')->getValue())->toBe('=H15')
        ->and($ws->getCell('D20')->getValue())->toBe('=$B$7-H15')
        ->and($ws->getCell('D21')->getValue())->toBe('=IFERROR(H15/$B$7,0)')
        ->and($ws->getCell('D23')->getValue())->toBe('=IFERROR($B$7/H15,0)')
        ->and($ws->getCell('D24')->getValue())->toBe('=IFERROR(H15/$B$9,0)');

    // Le mode opératoire reprend les notes de la fiche, une étape par ligne.
    expect($ws->getCell('A26')->getValue())->toContain('INSTRUCTIONS DE DRESSAGE')
        ->and($ws->getCell('A27')->getValue())->toBe('Étape 1')
        ->and($ws->getCell('C27')->getValue())->toBe('Réchauffer 200 g de sauce.')
        ->and($ws->getCell('A29')->getValue())->toBe('Étape 3');
});

test('le tableau de bord de la carte pointe chaque fiche de plat', function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();

    $ws = classeurCuisine()->getSheetByName('📊 Carte & Rentabilité Menu');

    expect($ws->getCell('A4')->getValue())->toBe('Catégorie')
        ->and($ws->getCell('D4')->getValue())->toBe('Food Cost (FCFA)')
        ->and($ws->getCell('H4')->getValue())->toBe('Coeff Multiplicateur');

    $onglet = "'🍽️ Plat - Ndolé aux crevettes'";
    expect($ws->getCell('B5')->getValue())->toBe("={$onglet}!B4")
        ->and($ws->getCell('D5')->getValue())->toBe("={$onglet}!D18")
        ->and($ws->getCell('E5')->getValue())->toBe("={$onglet}!B7")
        ->and($ws->getCell('H5')->getValue())->toBe("={$onglet}!D23");

    // La moyenne de la carte ferme le tableau.
    expect($ws->getCell('A6')->getValue())->toBe('MOYENNE')
        ->and($ws->getCell('G6')->getValue())->toBe('=IFERROR(AVERAGE(G5:G5),0)');
});

test('la mercuriale reprend le barème du garde-manger', function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();
    article('Riz parfumé', 'g', 90, 'Épicerie / Grains');
    article('Fond de volaille', 'ml', 120, 'Bouillons', true);

    $ws = classeurCuisine()->getSheetByName('🛒 Mercuriale & Ingrédients');

    expect($ws->getCell('A4')->getValue())->toBe('Code Ingrédient')
        ->and($ws->getCell('F4')->getValue())->toBe('Coût au g / ml / unité (FCFA)');

    // Les codes suivent l'ordre d'affichage : on retrouve un ING-00n à sa ligne.
    expect($ws->getCell('A5')->getValue())->toBe('ING-001')
        ->and($ws->getCell('A6')->getValue())->toBe('ING-002');

    // Le prix d'achat se déduit du coût unitaire, jamais l'inverse.
    expect($ws->getCell('E5')->getValue())->toStartWith('=F5*')
        ->and($ws->getCell('F5')->getValue())->toBeFloat();

    // Une préparation maison figure au catalogue, annoncée comme telle — sans
    // se répéter quand sa catégorie le dit déjà.
    $ligneSauce = collect(range(5, 14))->first(fn ($l) => $ws->getCell("B{$l}")->getValue() === 'Sauce Ndole');
    expect($ligneSauce)->not->toBeNull()
        ->and($ws->getCell("C{$ligneSauce}")->getValue())->toBe('Préparations');

    $ligneFond = collect(range(5, 14))->first(fn ($l) => $ws->getCell("B{$l}")->getValue() === 'Fond de volaille');
    expect($ligneFond)->not->toBeNull()
        ->and($ws->getCell("C{$ligneFond}")->getValue())->toBe('Préparation de base · Bouillons');
});

test("le classeur porte l'identité de l'établissement", function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();

    $tenant = Tenant::first();
    $tenant->update([
        'name'     => 'Villa Boutanga',
        'address'  => 'Kribi, Cameroun',
        'settings' => array_merge((array) $tenant->settings, [
            'theme' => ['primary' => '#7C2D12', 'secondary' => '#B45309', 'surface_dark' => '#065F46'],
        ]),
    ]);

    $classeur = classeurCuisine();
    $ws = $classeur->getSheetByName('📊 Carte & Rentabilité Menu');

    expect($classeur->getProperties()->getCompany())->toBe('Villa Boutanga')
        ->and($ws->getCell('A1')->getValue())->toStartWith('VILLA BOUTANGA — ')
        ->and($ws->getCell('A2')->getValue())->toContain('Kribi, Cameroun');

    // Le bandeau prend la couleur primaire de l'établissement.
    expect($ws->getStyle('A1')->getFill()->getStartColor()->getARGB())->toBe('FF7C2D12');

    $fiche = $classeur->getSheetByName('🍽️ Plat - Ndolé aux crevettes');
    expect($fiche->getCell('A1')->getValue())->toStartWith('VILLA BOUTANGA — FICHE TECHNIQUE DE METS');
});

test('une fiche sans ingrédient le dit au lieu d\'afficher zéro', function () {
    $this->actingAs(cuisineManager());

    $carte = platCarte('Salade du jour', 350000);
    RestaurantRecipe::create([
        'name'                    => 'Salade du jour',
        'type'                    => RestaurantRecipe::TYPE_DISH,
        'restaurant_menu_item_id' => $carte->id,
        'yield_quantity'          => 1,
        'is_active'               => true,
    ]);

    $ws = classeurCuisine()->getSheetByName('🍽️ Plat - Salade du jour');

    expect($ws->getCell('A13')->getValue())->toBe('Aucun ingrédient saisi pour cette fiche')
        ->and($ws->getCell('I13')->getValue())->toBe('À compléter depuis la plateforme');

    // Et la section de dressage reste visible, comme un rappel de ce qui manque.
    expect($ws->getCell('A25')->getValue())->toContain('INSTRUCTIONS DE DRESSAGE')
        ->and($ws->getCell('A26')->getValue())->toContain('Aucune instruction saisie');
});

test('le format CSV reste le format d\'aller-retour de l\'import', function () {
    $this->actingAs(cuisineManager());
    ficheDeReference();

    $reponse = $this->get(route('restaurant.recipes.export', ['format' => 'csv']))->assertOk();

    ob_start();
    $reponse->baseResponse->sendContent();
    $csv = preg_replace('/^\xEF\xBB\xBF/', '', ob_get_clean());

    expect($reponse->headers->get('Content-Type'))->toContain('text/csv')
        ->and($csv)->toContain('nom_fiche;type;plat_menu')
        ->and($csv)->toContain('Sauce Ndole');
});
