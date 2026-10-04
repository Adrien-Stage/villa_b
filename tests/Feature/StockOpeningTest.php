<?php

use App\Models\FiscalYear;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\StockCountService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Reprise du stock initial : la marchandise déjà en magasin au démarrage
 * entre avec son coût, une fois, et rejoint le grand livre par les
 * à-nouveaux du comptable. Centimes FCFA.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 10:00');
    test()->actingAs(User::factory()->create(['role' => 'econome']));
});

afterEach(function () {
    Carbon::setTestNow();
    // Le cache des modules est statique : on le vide pour ne pas fuir vers les tests suivants.
    (new ReflectionProperty(\App\Support\TenantModules::class, 'enabled'))->setValue(null, null);
});

function articleSansHistorique(?string $compte = null): StockItem
{
    $categorie = $compte ? StockCategory::create(['name' => "Cat {$compte}", 'stock_account' => $compte]) : null;

    return StockItem::create(['stock_category_id' => $categorie?->id, 'name' => 'Riz ' . uniqid(), 'unit' => 'kg']);
}

function csvArticles(array $lignes): UploadedFile
{
    $chemin = tempnam(sys_get_temp_dir(), 'csv') . '.csv';
    $flux = fopen($chemin, 'w');
    fwrite($flux, "\xEF\xBB\xBF");
    foreach ($lignes as $ligne) {
        fputcsv($flux, $ligne, ';', '"', '\\');
    }
    fclose($flux);

    return new UploadedFile($chemin, 'articles.csv', 'text/csv', null, true);
}

test('la reprise fixe le stock et le coût moyen de l’article', function () {
    $riz = articleSansHistorique();

    $mouvement = app(StockService::class)->recordOpening($riz, 100, 100_000);

    $riz->refresh();
    expect((float) $riz->current_stock)->toBe(100.0)
        ->and($riz->average_cost)->toBe(100_000)
        ->and($mouvement->source_type)->toBe(StockMovement::SOURCE_OPENING)
        ->and($mouvement->type)->toBe(StockMovement::TYPE_IN);
});

test('la reprise n’est permise qu’avant tout autre mouvement', function () {
    $riz = articleSansHistorique();
    app(StockService::class)->recordIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);

    expect(fn () => app(StockService::class)->recordOpening($riz, 100, 100_000))
        ->toThrow(RuntimeException::class, 'déjà des mouvements');
    expect((float) $riz->fresh()->current_stock)->toBe(10.0);
});

test('une reprise sans coût est refusée', function () {
    expect(fn () => app(StockService::class)->recordOpening(articleSansHistorique(), 100, 0))
        ->toThrow(InvalidArgumentException::class, 'coût');
});

test('la reprise est bloquée pendant un inventaire', function () {
    $riz = articleSansHistorique();
    app(StockCountService::class)->open([]);

    expect(fn () => app(StockService::class)->recordOpening($riz, 100, 100_000))
        ->toThrow(RuntimeException::class, 'en cours');
});

test('le night audit ne comptabilise pas la reprise', function () {
    app(StockService::class)->recordOpening(articleSansHistorique('321000'), 100, 100_000);

    expect(app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15')))->toBeNull();
});

test('les à-nouveaux proposent la valeur reprise par compte de stock', function () {
    $stock = app(StockService::class);
    $stock->recordOpening(articleSansHistorique('321000'), 100, 100_000);
    $stock->recordOpening(articleSansHistorique('321000'), 10, 50_000);
    $stock->recordOpening(articleSansHistorique(), 4, 25_000);

    $reprise = app(LedgerPostingService::class)->openingStockByAccount(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

    expect($reprise)->toBe(['321000' => 10_500_000, '332000' => 100_000]);

    // L'exercice suivant ne reprend pas ce stock : ses à-nouveaux viennent de la clôture.
    expect(app(LedgerPostingService::class)->openingStockByAccount(Carbon::parse('2027-01-01'), Carbon::parse('2027-12-31')))
        ->toBe([]);
});

test('l’écran des à-nouveaux affiche le stock repris', function () {
    $modules = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $modules->setValue(null, ['ledger']);

    app(StockService::class)->recordOpening(articleSansHistorique('321000'), 100, 100_000);
    FiscalYear::openYear(2026);

    $comptable = User::factory()->create(['role' => 'accountant']);

    $this->actingAs($comptable)->get(route('accounting.ledger.opening'))
        ->assertOk()
        ->assertSee('Stock initial repris')
        ->assertSee('321000 — 100 000 FCFA', false);
});

test('l’économe reprend le stock d’un article depuis l’écran', function () {
    $riz = articleSansHistorique();

    $this->post(route('economat.items.opening', $riz), ['quantity' => 25, 'unit_cost' => 1200])
        ->assertSessionHas('success');

    $riz->refresh();
    expect((float) $riz->current_stock)->toBe(25.0)
        ->and($riz->average_cost)->toBe(120_000);
});

test('l’import CSV reprend le stock initial au coût de la ligne', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);

    $this->post(route('economat.items.import'), ['csv_file' => csvArticles([
        ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock_min', 'cout_moyen_fcfa', 'actif', 'stock_initial'],
        ['Savon', 'SAV-01', 'litre', '', '', '10', '1200', 'oui', '24'],
        ['Drap', '', 'pièce', '', '', '5', '8000', 'oui', ''],
        ['Javel', '', 'litre', '', '', '5', '', 'oui', '10'],   // stock sans coût -> erreur
    ])])->assertRedirect();

    $savon = StockItem::where('name', 'Savon')->sole();
    expect((float) $savon->current_stock)->toBe(24.0)
        ->and($savon->movements()->value('source_type'))->toBe(StockMovement::SOURCE_OPENING)
        ->and((float) StockItem::where('name', 'Drap')->value('current_stock'))->toBe(0.0)
        ->and(StockItem::where('name', 'Javel')->exists())->toBeFalse();
});

test('un fichier CSV sans colonne stock_initial reste importable', function () {
    test()->seed(\Database\Seeders\TenantSeeder::class);

    $this->post(route('economat.items.import'), ['csv_file' => csvArticles([
        ['nom', 'reference', 'unite', 'categorie', 'fournisseur', 'stock_min', 'cout_moyen_fcfa', 'actif'],
        ['Savon', 'SAV-01', 'litre', '', '', '10', '1200', 'oui'],
    ])])->assertRedirect();

    expect((float) StockItem::where('name', 'Savon')->value('current_stock'))->toBe(0.0);
});
