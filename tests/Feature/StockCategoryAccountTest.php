<?php

use App\Models\JournalEntry;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\LedgerPostingService;
use App\Services\LedgerReportService;
use App\Services\LedgerService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
 * Le compte de stock d'une catégorie se règle à l'écran, et un changement de
 * compte reclasse au grand livre la valeur déjà en stock. Centimes FCFA.
 */

beforeEach(function () {
    Carbon::setTestNow('2026-06-15 10:00');
});

afterEach(fn () => Carbon::setTestNow());

function economeCategories(): User
{
    return User::factory()->create(['role' => 'econome']);
}

/** Soldes de juin, indexés par compte. */
function soldesDuMois(): \Illuminate\Support\Collection
{
    return app(LedgerReportService::class)
        ->balance(Carbon::parse('2026-06-01'), Carbon::parse('2026-06-30'))
        ->keyBy('code')
        ->map(fn ($ligne) => $ligne['balance']);
}

test('l’économe crée une catégorie avec son compte de stock', function () {
    $this->actingAs(economeCategories())
        ->post(route('economat.categories.store'), ['name' => 'Boissons', 'stock_account' => '311000'])
        ->assertRedirect();

    expect(StockCategory::where('name', 'Boissons')->value('stock_account'))->toBe('311000');
});

test('un compte hors de la classe 3 proposée est refusé', function () {
    $this->actingAs(economeCategories())
        ->post(route('economat.categories.store'), ['name' => 'Divers', 'stock_account' => '601000'])
        ->assertSessionHasErrors('stock_account');
});

test('le magasinier consulte les catégories mais ne les modifie pas', function () {
    $categorie = StockCategory::create(['name' => 'Épicerie']);
    $magasinier = User::factory()->create(['role' => 'storekeeper']);

    $this->actingAs($magasinier)->get(route('economat.categories.index'))
        ->assertOk()
        ->assertSee('Épicerie')
        ->assertDontSee('Nouvelle catégorie');

    $this->actingAs($magasinier)
        ->putJson(route('economat.categories.update', $categorie), ['name' => 'Épicerie', 'stock_account' => '321000'])
        ->assertForbidden();

    expect($categorie->fresh()->stock_account)->toBeNull();
});

test('changer le compte d’une catégorie reclasse son stock sans toucher aux charges', function () {
    $this->actingAs(economeCategories());
    $categorie = StockCategory::create(['name' => 'Épicerie']);
    $riz = StockItem::create(['stock_category_id' => $categorie->id, 'name' => 'Riz', 'unit' => 'kg']);

    // Réception du matin, sous l'ancien compte (332000 par défaut).
    app(StockService::class)->recordIn($riz, 10, 100_000, StockMovement::SOURCE_GOODS_RECEIPT, 1);

    $this->put(route('economat.categories.update', $categorie), ['name' => 'Épicerie', 'stock_account' => '321000'])
        ->assertSessionHas('success');

    $reclassement = JournalEntry::query()->where('schema', LedgerPostingService::SCHEMA_STOCK_RECLASS)->with('lines')->sole();
    expect($reclassement->lines->firstWhere('account_code', '321000')->debit)->toBe(1_000_000)
        ->and($reclassement->lines->firstWhere('account_code', '332000')->credit)->toBe(1_000_000);

    // Le night audit passe la réception du matin sur le compte qu'elle avait :
    // l'ancien compte se solde, le nouveau porte le stock.
    app(LedgerPostingService::class)->postEconomatStock(Carbon::parse('2026-06-15'));

    $soldes = soldesDuMois();
    expect($soldes['332000'])->toBe(0)
        ->and($soldes['321000'])->toBe(1_000_000);
});

test('une catégorie sans stock change de compte sans écriture', function () {
    $this->actingAs(economeCategories());
    $categorie = StockCategory::create(['name' => 'Épicerie']);

    $this->put(route('economat.categories.update', $categorie), ['name' => 'Épicerie', 'stock_account' => '321000']);

    expect($categorie->fresh()->stock_account)->toBe('321000')
        ->and(JournalEntry::count())->toBe(0);
});

test('déplacer un article vers une autre catégorie reclasse sa valeur', function () {
    $this->actingAs(economeCategories());
    $entretien = StockCategory::create(['name' => 'Entretien', 'stock_account' => '331000']);
    $epicerie = StockCategory::create(['name' => 'Épicerie', 'stock_account' => '321000']);
    $sel = StockItem::create([
        'stock_category_id' => $entretien->id, 'name' => 'Sel', 'unit' => 'kg',
        'current_stock' => 4, 'average_cost' => 50_000,
    ]);

    $this->put(route('economat.items.update', $sel), [
        'name' => 'Sel', 'unit' => 'kg', 'stock_category_id' => $epicerie->id,
    ])->assertSessionHas('success');

    $soldes = soldesDuMois();
    expect($sel->fresh()->stock_category_id)->toBe($epicerie->id)
        ->and($soldes['321000'])->toBe(200_000)
        ->and($soldes['331000'])->toBe(-200_000);
});

test('sur une période verrouillée, le compte de la catégorie reste inchangé', function () {
    $this->actingAs(economeCategories());
    $categorie = StockCategory::create(['name' => 'Épicerie']);
    StockItem::create([
        'stock_category_id' => $categorie->id, 'name' => 'Riz', 'unit' => 'kg',
        'current_stock' => 10, 'average_cost' => 100_000,
    ]);
    $ledger = app(LedgerService::class);
    $ledger->lockPeriod($ledger->periodFor(now()));

    $this->put(route('economat.categories.update', $categorie), ['name' => 'Céréales', 'stock_account' => '321000'])
        ->assertSessionHas('error');

    $categorie->refresh();
    expect($categorie->stock_account)->toBeNull()
        ->and($categorie->name)->toBe('Épicerie');
});

test('une catégorie qui contient des articles ne se supprime pas', function () {
    $this->actingAs(economeCategories());
    $categorie = StockCategory::create(['name' => 'Épicerie']);
    StockItem::create(['stock_category_id' => $categorie->id, 'name' => 'Riz', 'unit' => 'kg']);

    $this->delete(route('economat.categories.destroy', $categorie))->assertSessionHas('error');

    expect(StockCategory::find($categorie->id))->not->toBeNull();
});
