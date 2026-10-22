<?php

use App\Models\NightAudit;
use App\Models\Role;
use App\Models\ServiceStore;
use App\Models\ServiceStoreMovement;
use App\Models\ServiceStoreStock;
use App\Models\StockCount;
use App\Models\StockImport;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClasseurStockExport;
use App\Services\LedgerPostingService;
use App\Services\LedgerReportService;
use App\Services\StockService;
use App\Support\ClasseurStock as C;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

/*
 * Classeur Excel de l'économat : un magasin s'exporte, se gère dans Excel
 * avec ses formules, puis se réimporte — aperçu d'abord, écriture sur
 * confirmation, aucune ligne rejouée deux fois. Vers un dépôt, ce qui entre
 * passe par le magasin central puis lui est livré.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-10-19 10:00');
    Storage::fake();
    RoleCatalog::sync();
    activerModules(['economat']);
    $this->econome = employeDuClasseur('econome');
    $this->actingAs($this->econome);
});

afterEach(fn () => Carbon::setTestNow());

function employeDuClasseur(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Un classeur écrit sur disque, à partir de données, retouché au besoin. */
function classeurSurDisque(array $donnees, ?callable $retouche = null): string
{
    $classeur = app(ClasseurStockExport::class)->classeur($donnees + [
        'magasin' => C::MAGASIN_CENTRAL, 'articles' => [], 'mouvements' => [], 'fournisseurs' => [],
        'categories' => [], 'unites' => \App\Models\StockUnit::choix(),
    ]);
    if ($retouche) {
        $retouche($classeur);
    }

    $chemin = tempnam(sys_get_temp_dir(), 'classeur') . '.xlsx';
    (new Xlsx($classeur))->setPreCalculateFormulas(false)->save($chemin);

    return $chemin;
}

function deposer(string $chemin, ?ServiceStore $depot = null)
{
    return test()->post(route('economat.classeur.import'), array_filter([
        'fichier' => new UploadedFile($chemin, 'classeur.xlsx', null, null, true),
        'depot'   => $depot?->id,
    ]));
}

function mouvementDuClasseur(string $date, string $reference, string $type, float $quantite, array $autres = []): array
{
    return ['date' => Carbon::parse($date), 'reference' => $reference, 'type' => $type, 'quantite' => $quantite] + $autres;
}

/** Les articles de la plomberie : deux tuyaux. */
function articlesPlomberie(): array
{
    return [
        ['reference' => '1PL1678', 'designation' => 'TUYAU PVC IMPORTE DE 32', 'categorie' => 'Plomberie', 'unite' => 'pièce', 'fournisseur' => 'SOCOICAM SARL'],
        ['reference' => 'GPIPE110', 'designation' => 'TUBE ISOPHONIQUE D110 3M', 'categorie' => 'Plomberie', 'unite' => 'mètre', 'fournisseur' => 'Richardson'],
    ];
}

test("l'export d'un magasin donne un classeur complet : listes, formules de stock, mouvements numérotés", function () {
    $plomberie = \App\Models\StockCategory::create(['name' => 'Plomberie']);
    $tuyau = StockItem::create(['name' => 'Tuyau PVC 32', 'reference' => 'PVC32', 'unit' => 'pièce', 'min_stock' => 5, 'stock_category_id' => $plomberie->id]);
    app(StockService::class)->recordIn($tuyau, 12, 150_000);

    $reponse = $this->get(route('economat.classeur.export'))->assertOk();
    $chemin = tempnam(sys_get_temp_dir(), 'export') . '.xlsx';
    file_put_contents($chemin, $reponse->streamedContent());

    $classeur = IOFactory::load($chemin);
    expect($classeur->getSheetNames())->toBe([C::ONGLET_LISEZ_MOI, C::ONGLET_ARTICLES, C::ONGLET_MOUVEMENTS,
        C::ONGLET_FOURNISSEURS, C::ONGLET_CATEGORIES, C::ONGLET_SYNTHESE, C::ONGLET_LISTES]);

    expect($classeur->getSheetByName(C::ONGLET_LISEZ_MOI)->getCell('B4')->getValue())->toBe(C::MAGASIN_CENTRAL);

    $articles = $classeur->getSheetByName(C::ONGLET_ARTICLES);
    expect($articles->getCell('A5')->getValue())->toBe($tuyau->id)
        ->and($articles->getCell('B5')->getValue())->toBe('PVC32')
        ->and($articles->getCell('O5')->getValue())->toStartWith('=IF(')      // stock actuel
        ->and($articles->getCell('O5')->getCalculatedValue())->toEqual(12)
        ->and($articles->getCell('P5')->getCalculatedValue())->toEqual(1500); // coût moyen FCFA

    $mouvements = $classeur->getSheetByName(C::ONGLET_MOUVEMENTS);
    expect($mouvements->getCell('A5')->getValue())->toBe(StockMovement::sole()->id)
        ->and($mouvements->getCell('F5')->getValue())->toBe(C::TYPE_ENTREE)
        ->and($mouvements->getCell('D5')->getCalculatedValue())->toBe('Tuyau PVC 32')
        ->and($mouvements->getCell('I5')->getCalculatedValue())->toEqual(12)
        ->and($mouvements->getDataValidation('F5:F' . $mouvements->getHighestRow())->getFormula1())->toContain(C::ONGLET_LISTES);

    // La synthèse lit les catégories par INDEX : supprimer une ligne de
    // l'onglet Catégories ne la casse pas en #REF!.
    $synthese = $classeur->getSheetByName(C::ONGLET_SYNTHESE);
    expect($synthese->getCell('A5')->getValue())->toContain('INDEX(')->not->toContain("'!A5")
        ->and($synthese->getCell('A5')->getCalculatedValue())->toBe('Plomberie')
        ->and($synthese->getCell('B5')->getCalculatedValue())->toEqual(1)
        ->and($synthese->getCell('A6')->getCalculatedValue())->toBe('');
});

test("vers le magasin central : l'aperçu n'écrit rien, la confirmation enregistre articles et mouvements", function () {
    $chemin = classeurSurDisque([
        'articles'     => articlesPlomberie(),
        'fournisseurs' => [['nom' => 'SOCOICAM SARL', 'telephone' => '+237 6 99 00 00 00']],
        'mouvements'   => [
            mouvementDuClasseur('2026-10-01', '1PL1678', C::TYPE_ENTREE, 7, ['cout' => 3500, 'fournisseur' => 'SOCOICAM SARL', 'document' => 'BL 1452', 'pointe' => C::OUI]),
            mouvementDuClasseur('2026-10-03', '1PL1678', C::TYPE_SORTIE, 2, ['destination' => 'Chantier Extension Zingana']),
            mouvementDuClasseur('2026-10-04', '1PL1678', C::TYPE_RETOUR, 1),
            mouvementDuClasseur('2026-10-05', 'GPIPE110', C::TYPE_ENTREE, 30),
        ],
    ]);

    deposer($chemin)->assertRedirect();
    $import = StockImport::sole();

    expect($import->isDraft())->toBeTrue()
        ->and($import->analysis['erreurs'])->toBe([])
        ->and($import->analysis['resume']['articles_crees'])->toBe(2)
        ->and($import->analysis['resume']['mouvements'])->toBe(4)
        ->and(StockItem::count())->toBe(0);
    // Une entrée sans coût est signalée.
    expect(collect($import->analysis['avertissements'])->pluck('message')->implode(' '))->toContain('sans coût unitaire');

    $this->get(route('economat.classeur.show', $import))->assertOk()
        ->assertSee("Confirmer l'import", false)->assertSee('Chantier Extension Zingana');

    $this->post(route('economat.classeur.confirm', $import))->assertRedirect(route('economat.classeur.show', $import))
        ->assertSessionHas('success');

    $tuyau = StockItem::where('reference', '1PL1678')->sole();
    expect((float) $tuyau->current_stock)->toBe(6.0)
        ->and($tuyau->category->name)->toBe('Plomberie')
        ->and($tuyau->supplier->name)->toBe('SOCOICAM SARL')
        ->and(Supplier::where('name', 'SOCOICAM SARL')->value('phone'))->toBe('+237 6 99 00 00 00')
        ->and(Supplier::where('name', 'Richardson')->exists())->toBeTrue()
        ->and((float) StockItem::where('reference', 'GPIPE110')->value('current_stock'))->toBe(30.0);

    $entree = StockMovement::where('stock_item_id', $tuyau->id)->where('type', StockMovement::TYPE_IN)->orderBy('id')->first();
    expect($entree->occurred_at->toDateString())->toBe('2026-10-01')
        ->and($entree->unit_cost)->toBe(350_000)
        ->and($entree->stock_import_id)->toBe($import->id)
        ->and($entree->details)->toMatchArray(['document' => 'BL 1452', 'pointe' => true, 'fournisseur' => 'SOCOICAM SARL']);

    $sortie = StockMovement::where('stock_item_id', $tuyau->id)->where('type', StockMovement::TYPE_OUT)->sole();
    expect($sortie->source_type)->toBe(StockMovement::SOURCE_EXTERNAL_ISSUE)
        ->and($sortie->details['beneficiaire'])->toBe('Chantier Extension Zingana');
    expect($import->fresh()->status)->toBe(StockImport::STATUS_APPLIED);
});

test('le même fichier, réimporté, ne rejoue rien ; le classeur réexporté non plus', function () {
    $chemin = classeurSurDisque([
        'articles'   => articlesPlomberie(),
        'mouvements' => [mouvementDuClasseur('2026-10-01', '1PL1678', C::TYPE_ENTREE, 7, ['cout' => 3500])],
    ]);
    deposer($chemin);
    $this->post(route('economat.classeur.confirm', StockImport::sole()));

    deposer($chemin);
    $deuxieme = StockImport::latest('id')->first();
    expect($deuxieme->analysis['resume']['mouvements'])->toBe(0)
        ->and($deuxieme->analysis['resume']['deja_importes'])->toBe(1)
        ->and($deuxieme->analysis['resume']['articles_crees'])->toBe(0);

    // Le classeur réexporté porte ses N° : rien à rejouer.
    $donnees = app(ClasseurStockExport::class)->donnees(null);
    deposer(classeurSurDisque($donnees));
    $troisieme = StockImport::latest('id')->first();
    expect($troisieme->analysis['erreurs'])->toBe([])
        ->and($troisieme->analysis['resume']['mouvements'])->toBe(0)
        ->and($troisieme->analysis['resume']['deja_enregistres'])->toBe(1);

    expect((float) StockItem::where('reference', '1PL1678')->value('current_stock'))->toBe(7.0);
});

test("vers un dépôt : l'entrée passe par le magasin central, la sortie à un bénéficiaire porte sa charge", function () {
    $plomberie = ServiceStore::create(['name' => 'Magasin plomberie', 'department' => 'autre']);

    $chemin = classeurSurDisque([
        'magasin'    => C::PREFIXE_DEPOT . 'Magasin plomberie',
        'articles'   => articlesPlomberie(),
        'mouvements' => [
            // Un même article sur deux colis.
            mouvementDuClasseur('2026-10-10', 'GPIPE110', C::TYPE_INITIAL, 18, ['cout' => 2000, 'document' => 'Colis 41-442G-1', 'pointe' => C::OUI]),
            mouvementDuClasseur('2026-10-10', 'GPIPE110', C::TYPE_INITIAL, 12, ['cout' => 2000, 'document' => 'Colis 41-442G-2', 'pointe' => C::OUI]),
            mouvementDuClasseur('2026-10-12', 'GPIPE110', C::TYPE_SORTIE, 10, ['destination' => 'Chantier Extension Zingana']),
            mouvementDuClasseur('2026-10-13', 'GPIPE110', C::TYPE_RETOUR, 4),
        ],
    ]);

    deposer($chemin, $plomberie);
    $import = StockImport::sole();
    expect($import->analysis['erreurs'])->toBe([])
        ->and($import->service_store_id)->toBe($plomberie->id);
    $this->post(route('economat.classeur.confirm', $import))->assertSessionHas('success');

    $tube = StockItem::where('reference', 'GPIPE110')->sole();
    expect((float) ServiceStoreStock::where('service_store_id', $plomberie->id)->where('stock_item_id', $tube->id)->value('current_stock'))->toBe(24.0)
        // Le magasin central n'a fait que transmettre.
        ->and((float) $tube->current_stock)->toBe(0.0)
        ->and(StockMovement::where('stock_item_id', $tube->id)->where('source_type', StockMovement::SOURCE_OPENING)->count())->toBe(2)
        ->and(StockMovement::where('stock_item_id', $tube->id)->where('source_type', StockMovement::SOURCE_IMPORT)->where('type', 'out')->count())->toBe(2);

    $sortie = ServiceStoreMovement::where('source_type', ServiceStoreMovement::SOURCE_BENEFICIAIRE)->sole();
    expect($sortie->details['beneficiaire'])->toBe('Chantier Extension Zingana')
        ->and($sortie->occurred_at->toDateString())->toBe('2026-10-12');

    // Au grand livre : la reprise ne passe pas (à-nouveaux), le transfert non
    // plus ; la sortie au bénéficiaire est une charge, le retour l'annule en partie.
    $posting = app(LedgerPostingService::class);
    foreach (['2026-10-10', '2026-10-12', '2026-10-13'] as $jour) {
        $posting->postDay(Carbon::parse($jour));
    }
    $soldes = app(LedgerReportService::class)
        ->balance(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'))
        ->keyBy('code')->map(fn ($l) => $l['balance']);

    $compteStock = $tube->stockAccount();
    expect($soldes[\App\Models\Account::variationFor($compteStock)])->toBe(6 * 200_000)
        ->and($soldes[$compteStock])->toBe(-6 * 200_000);

    // Le dépôt se réexporte avec ses mouvements, typés comme dans le fichier.
    $donnees = app(ClasseurStockExport::class)->donnees($plomberie);
    expect(array_column($donnees['mouvements'], 'type'))->toBe([C::TYPE_INITIAL, C::TYPE_INITIAL, C::TYPE_SORTIE, C::TYPE_RETOUR])
        ->and($donnees['mouvements'][0]['document'])->toBe('Colis 41-442G-1')
        ->and($donnees['mouvements'][0]['pointe'])->toBe(C::OUI);
});

test('les erreurs se lisent à leur ligne et bloquent la confirmation', function () {
    $chemin = classeurSurDisque([
        'articles'   => [
            ...articlesPlomberie(),
            ['reference' => 'X1', 'designation' => 'Robinet', 'unite' => 'boulon'],
        ],
        'mouvements' => [
            mouvementDuClasseur('2026-10-01', '1PL1678', C::TYPE_ENTREE, 3),
            mouvementDuClasseur('2026-10-02', '1PL1678', C::TYPE_SORTIE, 5, ['destination' => 'Chantier']),
            mouvementDuClasseur('2026-10-02', '1PL1678', C::TYPE_SORTIE, 1),
            mouvementDuClasseur('2026-10-30', '1PL1678', C::TYPE_ENTREE, 1),
            mouvementDuClasseur('2026-10-03', 'INCONNU', C::TYPE_ENTREE, 1),
            mouvementDuClasseur('2026-10-04', '1PL1678', C::TYPE_INITIAL, 1),
        ],
    ]);

    deposer($chemin);
    $import = StockImport::sole();
    $erreurs = collect($import->analysis['erreurs']);
    $message = fn (int $ligne) => $erreurs->firstWhere('ligne', $ligne)['message'] ?? '';

    expect($message(7))->toContain('Unité « boulon » inconnue')                 // Articles, 3e article
        ->and($erreurs->where('onglet', C::ONGLET_MOUVEMENTS)->pluck('message')->implode(' | '))
        ->toContain('Stock insuffisant')
        ->toContain('bénéficiaire obligatoire')
        ->toContain('Date à venir')
        ->toContain('« INCONNU » absente')
        ->toContain('Stock initial impossible');

    $this->get(route('economat.classeur.show', $import))->assertOk()->assertDontSee("Confirmer l'import", false);
    $this->post(route('economat.classeur.confirm', $import))->assertSessionHas('error');
    expect(StockMovement::count())->toBe(0)->and($import->fresh()->isDraft())->toBeTrue();
});

test("une journée close passe à la date du jour ; rien avant le dernier inventaire ni pendant un inventaire", function () {
    NightAudit::create(['audit_date' => '2026-10-05', 'closed_at' => now()]);

    $chemin = classeurSurDisque([
        'articles'   => articlesPlomberie(),
        'mouvements' => [mouvementDuClasseur('2026-10-05', '1PL1678', C::TYPE_ENTREE, 2, ['cout' => 100])],
    ]);
    deposer($chemin);
    $import = StockImport::sole();
    expect($import->analysis['mouvements'][0]['date_effective'])->toBe('2026-10-19')
        ->and(collect($import->analysis['avertissements'])->pluck('message')->implode(' '))->toContain('déjà clôturée');
    $this->post(route('economat.classeur.confirm', $import));
    $entree = StockMovement::sole();
    expect($entree->occurred_at->toDateString())->toBe('2026-10-19')
        ->and($entree->reason)->toContain('daté du 05/10/2026');

    // Inventaire clôturé le 15 : une ligne du 10 est refusée.
    StockCount::create(['status' => StockCount::STATUS_CLOSED, 'closed_at' => '2026-10-15 18:00']);
    deposer(classeurSurDisque([
        'articles'   => articlesPlomberie(),
        'mouvements' => [mouvementDuClasseur('2026-10-10', 'GPIPE110', C::TYPE_ENTREE, 1)],
    ]));
    expect(collect(StockImport::latest('id')->first()->analysis['erreurs'])->pluck('message')->implode(' '))
        ->toContain('antérieure au dernier inventaire');

    // Inventaire en cours : le magasin est gelé.
    StockCount::create(['status' => StockCount::STATUS_DRAFT]);
    deposer(classeurSurDisque(['articles' => articlesPlomberie()]));
    expect(collect(StockImport::latest('id')->first()->analysis['erreurs'])->pluck('message')->implode(' '))
        ->toContain('en cours au magasin central');
});

test("un classeur numéroté d'un autre magasin est refusé", function () {
    $tuyau = StockItem::create(['name' => 'Tuyau PVC 32', 'reference' => 'PVC32', 'unit' => 'pièce']);
    app(StockService::class)->recordIn($tuyau, 12, 150_000);
    $plomberie = ServiceStore::create(['name' => 'Magasin plomberie', 'department' => 'autre']);

    deposer(classeurSurDisque(app(ClasseurStockExport::class)->donnees(null)), $plomberie);

    expect(collect(StockImport::sole()->analysis['erreurs'])->pluck('message')->implode(' '))
        ->toContain('Ce classeur est celui de « Magasin central (économat) »');
});

test("le magasinier exporte et consulte, seul l'économe importe", function () {
    $this->actingAs(employeDuClasseur('storekeeper'));

    $this->get(route('economat.classeur.index'))->assertOk()->assertSee('Télécharger le classeur')->assertDontSee('Analyser le fichier');
    $this->get(route('economat.classeur.export'))->assertOk();
    $this->post(route('economat.classeur.import'), [])->assertSessionHas('access_denied_popup');

    $this->actingAs($this->econome)->get(route('economat.classeur.index'))->assertSee('Analyser le fichier');
});
