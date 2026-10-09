<?php

use App\Models\Role;
use App\Models\StockCategory;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SpreadsheetService;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/*
 * Après le comptage : chaque ajustement garde le stock initial et le stock
 * compté ; le fichier de comptage (Excel) se télécharge, se remplit et
 * s'importe pour saisir les quantités, et ajuster le stock à la clôture ;
 * la rubrique « Mouvements de stock » montre tous les articles, ou un seul.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat']);
    $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00'));

    $this->boissons = StockCategory::create(['name' => 'Boissons']);
    $this->biere = StockItem::create(['name' => 'Bière 65 cl', 'reference' => 'BIE-65', 'unit' => 'bouteille', 'stock_category_id' => $this->boissons->id, 'is_active' => true]);
    $this->eau = StockItem::create(['name' => 'Eau minérale 1,5 L', 'reference' => 'EAU-15', 'unit' => 'bouteille', 'stock_category_id' => $this->boissons->id, 'is_active' => true]);
    $this->riz = StockItem::create(['name' => 'Riz parfumé', 'reference' => 'RIZ-25', 'unit' => 'kg', 'stock_category_id' => $this->boissons->id, 'is_active' => true]);

    $stock = app(StockService::class);
    $stock->recordOpening($this->biere, 20, 100000);
    $stock->recordOpening($this->eau, 30, 30000);
    $stock->recordOpening($this->riz, 50, 80000);
});

function membreMagasin(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Un fichier de comptage CSV, tel qu'Excel l'enregistre (« ; », UTF-8 avec BOM). */
function fichierDeComptage(array $lignes): UploadedFile
{
    $chemin = tempnam(sys_get_temp_dir(), 'cpt') . '.csv';
    $f = fopen($chemin, 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, \App\Services\StockCountImportService::COLONNES, ';', '"', '\\');
    foreach ($lignes as $ligne) {
        fputcsv($f, $ligne, ';', '"', '\\');
    }
    fclose($f);

    return new UploadedFile($chemin, 'comptage.csv', 'text/csv', null, true);
}

test("l'ajustement garde le stock initial et le stock après comptage", function () {
    $econome = membreMagasin('econome');
    $this->actingAs($econome);

    $service = app(StockCountService::class);
    $inventaire = $service->open([], $econome);
    $ligne = $inventaire->lines->firstWhere('stock_item_id', $this->biere->id);
    $service->updateCounts($inventaire, [$ligne->id => ['counted_quantity' => 17, 'reason' => StockCountLine::REASON_WASTE]]);
    $service->close($inventaire, $econome);

    $ajustement = StockMovement::where('source_type', StockMovement::SOURCE_STOCK_COUNT)->sole();
    expect((float) $ajustement->stock_before)->toBe(20.0)
        ->and((float) $ajustement->stock_after)->toBe(17.0)
        ->and((float) $ajustement->quantity)->toBe(-3.0)
        ->and($ajustement->valeur())->toBe(-300000)
        ->and($ajustement->reason)->toContain('Casse / perte');

    // Un ajustement à la main garde aussi son stock initial.
    $this->post(route('economat.items.adjust', $this->eau), ['counted_quantity' => 32, 'reason' => 'Recomptage'])->assertSessionHasNoErrors();
    $manuel = StockMovement::where('stock_item_id', $this->eau->id)->where('type', StockMovement::TYPE_ADJUSTMENT)->sole();
    expect((float) $manuel->stock_before)->toBe(30.0)->and((float) $manuel->stock_after)->toBe(32.0);
});

test('le fichier de comptage porte les articles et leur stock théorique', function () {
    $econome = membreMagasin('econome');
    $inventaire = app(StockCountService::class)->open([], $econome);

    $reponse = $this->actingAs($econome)->get(route('economat.stock_counts.export', ['inventaire' => $inventaire->id]));
    $reponse->assertOk()->assertHeader('content-disposition');

    $chemin = tempnam(sys_get_temp_dir(), 'xls') . '.xlsx';
    file_put_contents($chemin, $reponse->streamedContent());
    [$lignes, $erreur] = app(SpreadsheetService::class)->parse($chemin, ['article', 'stock compté']);

    expect($erreur)->toBeNull()
        ->and(collect($lignes)->pluck('article')->all())->toContain('Bière 65 cl', 'Eau minérale 1,5 L', 'Riz parfumé')
        ->and((float) collect($lignes)->firstWhere('article', 'Riz parfumé')['stock théorique'])->toBe(50.0);

    // Hors inventaire : les articles actifs et leur stock du moment.
    $this->get(route('economat.stock_counts.export', ['categorie' => $this->boissons->id]))->assertOk();
});

test("le fichier importé saisit les comptages ; avec la clôture, le stock est ajusté", function () {
    $econome = membreMagasin('econome');
    $inventaire = app(StockCountService::class)->open([], $econome);
    $this->actingAs($econome);

    // Par identifiant, par référence, par nom ; une ligne vide ne change rien.
    $this->post(route('economat.stock_counts.import', $inventaire), ['fichier' => fichierDeComptage([
        [$this->biere->id, '', '', '', '', '20', '18', 'Casse / perte', 'Deux cassées'],
        ['', 'EAU-15', '', '', '', '30', '30,5', 'rats', ''],
        ['', '', 'Riz parfumé', '', '', '50', '', '', ''],
        ['', '', 'Article inconnu', '', '', '', '4', '', ''],
        ['', '', 'Riz parfumé', '', '', '', 'beaucoup', '', ''],
    ])])->assertRedirect(route('economat.stock_counts.show', $inventaire));

    $lignes = $inventaire->fresh()->lines->keyBy('stock_item_id');
    expect((float) $lignes[$this->biere->id]->counted_quantity)->toBe(18.0)
        ->and($lignes[$this->biere->id]->reason)->toBe(StockCountLine::REASON_WASTE)
        ->and((float) $lignes[$this->eau->id]->counted_quantity)->toBe(30.5)
        // Un motif inconnu devient « autre » et garde son texte en note.
        ->and($lignes[$this->eau->id]->reason)->toBe(StockCountLine::REASON_OTHER)
        ->and($lignes[$this->eau->id]->notes)->toBe('rats')
        ->and($lignes[$this->riz->id]->counted_quantity)->toBeNull()
        ->and(session('import_errors'))->toHaveCount(2)
        ->and($inventaire->fresh()->status)->toBe(StockCount::STATUS_DRAFT);

    // Le riz compté à son tour, avec la clôture : le stock est ajusté.
    $this->post(route('economat.stock_counts.import', $inventaire), [
        'fichier'  => fichierDeComptage([['', '', 'Riz parfumé', '', '', '50', '48', '', '']]),
        'cloturer' => '1',
    ])->assertSessionHas('success');

    expect($inventaire->fresh()->status)->toBe(StockCount::STATUS_CLOSED)
        ->and((float) $this->biere->fresh()->current_stock)->toBe(18.0)
        ->and((float) $this->eau->fresh()->current_stock)->toBe(30.5)
        ->and((float) $this->riz->fresh()->current_stock)->toBe(48.0)
        ->and((float) StockMovement::where('stock_item_id', $this->riz->id)->where('type', 'adjustment')->sole()->stock_before)->toBe(50.0);
});

test("une ligne refusée laisse l'inventaire ouvert, même avec la clôture demandée", function () {
    $econome = membreMagasin('econome');
    $inventaire = app(StockCountService::class)->open([], $econome);

    $this->actingAs($econome)->post(route('economat.stock_counts.import', $inventaire), [
        'fichier'  => fichierDeComptage([
            ['', '', 'Bière 65 cl', '', '', '', '19', '', ''],
            ['', '', 'Inconnu', '', '', '', '2', '', ''],
        ]),
        'cloturer' => '1',
    ]);

    expect($inventaire->fresh()->status)->toBe(StockCount::STATUS_DRAFT)
        ->and((float) $this->biere->fresh()->current_stock)->toBe(20.0)
        ->and(session('success'))->toContain("L'inventaire reste ouvert");
});

test("le comptage déjà fait s'importe dès l'ouverture, et ajuste le stock", function () {
    $this->actingAs(membreMagasin('econome'))->post(route('economat.stock_counts.store'), [
        'count_date'        => '2026-10-09',
        'stock_category_id' => $this->boissons->id,
        'fichier'           => fichierDeComptage([['', 'BIE-65', '', '', '', '', '21', '', 'Oubli de saisie']]),
        'cloturer'          => '1',
    ])->assertSessionHas('success');

    expect(StockCount::sole()->status)->toBe(StockCount::STATUS_CLOSED)
        ->and((float) $this->biere->fresh()->current_stock)->toBe(21.0)
        ->and((float) $this->eau->fresh()->current_stock)->toBe(30.0);
});

test('le magasinier importe le comptage mais ne clôture pas', function () {
    $inventaire = app(StockCountService::class)->open([], membreMagasin('econome'));

    $this->actingAs(membreMagasin('storekeeper'))->post(route('economat.stock_counts.import', $inventaire), [
        'fichier'  => fichierDeComptage([['', '', 'Bière 65 cl', '', '', '', '19', '', '']]),
        'cloturer' => '1',
    ])->assertSessionHas('success');

    expect($inventaire->fresh()->status)->toBe(StockCount::STATUS_DRAFT)
        ->and((float) $inventaire->fresh()->lines->firstWhere('stock_item_id', $this->biere->id)->counted_quantity)->toBe(19.0)
        ->and((float) $this->biere->fresh()->current_stock)->toBe(20.0);
});

test("la rubrique Mouvements montre tous les articles, ou la fiche de stock d'un seul", function () {
    $econome = membreMagasin('econome');
    $this->actingAs($econome);
    $stock = app(StockService::class);

    $this->travelTo(CarbonImmutable::parse('2026-10-10 09:00'));
    $stock->recordIn($this->biere, 10, 120000, StockMovement::SOURCE_MANUAL, null, 'Livraison');
    $this->travelTo(CarbonImmutable::parse('2026-10-11 09:00'));
    $stock->recordOut($this->biere, 4, StockMovement::SOURCE_MANUAL, null, 'Service bar');
    $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00'));
    $stock->adjust($this->biere, 25, 'Recomptage');

    $this->get(route('economat.movements.index', ['du' => '2026-10-01', 'au' => '2026-10-31']))->assertOk()
        ->assertSee('Mouvements de stock')
        ->assertSee('Bière 65 cl')
        ->assertSee('Eau minérale 1,5 L')
        ->assertSee('Stock avant');

    // La fiche de la bière à partir du 10 : 20 au début, +10, −4, ajustement −1, 25 en fin.
    $fiche = app(\App\Services\StockMovementJournal::class)->fiche($this->biere, CarbonImmutable::parse('2026-10-10'), CarbonImmutable::parse('2026-10-31'));
    expect($fiche)->toMatchArray(['debut' => 20.0, 'entrees' => 10.0, 'sorties' => 4.0, 'ajustements' => -1.0, 'fin' => 25.0, 'mouvements' => 3]);

    $page = $this->get(route('economat.movements.index', ['article' => $this->biere->id, 'du' => '2026-10-10', 'au' => '2026-10-31']))->assertOk()
        ->assertSee('Fiche de stock')
        ->assertSee('Stock au début');
    // Les lignes de la fiche sont celles de la bière, dans l'ordre du temps.
    expect($page->viewData('lignes')->pluck('article')->unique()->all())->toBe(['Bière 65 cl'])
        ->and($page->viewData('lignes')->pluck('apres')->all())->toBe([30.0, 26.0, 25.0])
        ->and($page->viewData('lignes')->pluck('avant')->all())->toBe([20.0, 30.0, 26.0]);

    $this->get(route('economat.movements.export', ['article' => $this->biere->id, 'du' => '2026-10-10', 'au' => '2026-10-31', 'format' => 'excel']))
        ->assertOk()->assertHeader('content-disposition');
    $this->get(route('economat.movements.export', ['format' => 'impression']))->assertOk()->assertSee('Mouvements de stock');

    // Le magasinier consulte ; la réception, non.
    $this->actingAs(membreMagasin('storekeeper'))->get(route('economat.movements.index'))->assertOk();
    $this->actingAs(membreMagasin('reception'))->get(route('economat.movements.index'))->assertSessionHas('access_denied_popup', true);
});
