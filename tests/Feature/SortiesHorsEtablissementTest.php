<?php

use App\Models\ExternalIssue;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockCountService;
use App\Services\StockService;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Sorties de matériel hors de l'établissement : l'économe valide la sortie
 * d'un matériel qui ne sert pas l'hôtel (prêt, réparation, don…). Le bon
 * garde qui l'a emporté et signe en son nom ; les sorties se listent, se
 * filtrent par date ou par période, et s'impriment.
 */

beforeEach(function () {
    RoleCatalog::sync();
    activerModules(['economat']);
    $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00'));

    $this->clim = StockItem::create(['name' => 'Climatiseur split 12000 BTU', 'reference' => 'CLIM-12', 'unit' => 'pièce', 'is_active' => true]);
    $this->chaises = StockItem::create(['name' => 'Chaise de banquet', 'reference' => 'CH-BQ', 'unit' => 'pièce', 'is_active' => true]);
    $stock = app(StockService::class);
    $stock->recordOpening($this->clim, 3, 25000000);
    $stock->recordOpening($this->chaises, 40, 1500000);
});

function personneEconomat(string $role, string $nom = 'Agent'): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

function sortieDeChaises($test, array $autres = []): ExternalIssue
{
    $test->post(route('economat.external_issues.store'), array_merge([
        'reason'                   => 'pret',
        'beneficiary_name'         => 'Jean-Paul Mbarga',
        'beneficiary_organisation' => 'Paroisse Saint-Michel',
        'beneficiary_phone'        => '+237 699 11 22 33',
        'beneficiary_id_document'  => 'CNI 112233445',
        'expected_return_at'       => '2026-10-12',
        'notes'                    => 'Pour la kermesse du dimanche',
        'lines'                    => [['stock_item_id' => $test->chaises->id, 'quantity' => 10, 'notes' => 'Bon état']],
    ], $autres))->assertSessionHasNoErrors();

    return ExternalIssue::latest('id')->first();
}

test("l'économe valide une sortie : le stock baisse, le bon signe au nom de qui emporte le matériel", function () {
    $econome = personneEconomat('econome', 'Clarisse Ngo');
    $this->actingAs($econome);

    $this->get(route('economat.external_issues.create'))->assertOk()->assertSee('Qui emporte le matériel');
    $sortie = sortieDeChaises($this);

    expect($sortie->number)->toBe('BSE-2026-0001')
        ->and($sortie->status)->toBe(ExternalIssue::STATUS_VALIDATED)
        ->and($sortie->beneficiary_signature)->toBe('Jean-Paul')
        ->and($sortie->issuer_signature)->toBe('Clarisse')
        ->and($sortie->issued_by)->toBe($econome->id)
        ->and($sortie->total_value)->toBe(10 * 1500000)
        ->and($sortie->expected_return_at->toDateString())->toBe('2026-10-12')
        ->and((float) $this->chaises->fresh()->current_stock)->toBe(30.0);

    $mouvement = StockMovement::where('source_type', StockMovement::SOURCE_EXTERNAL_ISSUE)->sole();
    expect($mouvement->source_id)->toBe($sortie->id)
        ->and((float) $mouvement->quantity)->toBe(-10.0)
        ->and($mouvement->reason)->toContain('Jean-Paul Mbarga');

    $this->get(route('economat.external_issues.show', $sortie))->assertOk()
        ->assertSee('Paroisse Saint-Michel')->assertSee('Jean-Paul');
    $this->get(route('economat.external_issues.print', $sortie))->assertOk()
        ->assertSee('Bon de sortie hors établissement')
        ->assertSee('CNI 112233445')
        ->assertSee('Jean-Paul')
        ->assertSee('Le demandeur');

    // Le mouvement renvoie au bon dans la rubrique Mouvements de stock.
    $this->get(route('economat.movements.index', ['article' => $this->chaises->id]))->assertOk()
        ->assertSee('Sortie hors établissement ' . $sortie->number);
});

test('une sortie impossible ne sort rien : stock insuffisant, nom manquant, inventaire en cours', function () {
    $econome = personneEconomat('econome');
    $this->actingAs($econome);

    $this->post(route('economat.external_issues.store'), [
        'reason' => 'don', 'beneficiary_name' => 'Association Espoir',
        'lines'  => [['stock_item_id' => $this->clim->id, 'quantity' => 5]],
    ])->assertSessionHas('error');

    $this->post(route('economat.external_issues.store'), [
        'reason' => 'don', 'beneficiary_name' => '',
        'lines'  => [['stock_item_id' => $this->clim->id, 'quantity' => 1]],
    ])->assertSessionHasErrors('beneficiary_name');

    app(StockCountService::class)->open([], $econome);
    $this->post(route('economat.external_issues.store'), [
        'reason' => 'reparation', 'beneficiary_name' => 'Froid Service',
        'lines'  => [['stock_item_id' => $this->clim->id, 'quantity' => 1]],
    ])->assertSessionHas('error');

    expect(ExternalIssue::count())->toBe(0)
        ->and((float) $this->clim->fresh()->current_stock)->toBe(3.0);
});

test("annuler la sortie fait revenir le matériel au même coût, sans toucher au dernier prix d'achat", function () {
    $this->actingAs(personneEconomat('econome'));
    $sortie = sortieDeChaises($this);
    $prix = $this->chaises->fresh()->last_purchase_price;

    $this->post(route('economat.external_issues.cancel', $sortie), [])->assertSessionHasErrors('cancellation_reason');
    $this->post(route('economat.external_issues.cancel', $sortie), ['cancellation_reason' => 'Prêt finalement refusé'])->assertSessionHas('success');

    $chaises = $this->chaises->fresh();
    expect($sortie->fresh()->status)->toBe(ExternalIssue::STATUS_CANCELLED)
        ->and((float) $chaises->current_stock)->toBe(40.0)
        ->and((int) $chaises->average_cost)->toBe(1500000)
        ->and($chaises->last_purchase_price)->toBe($prix);

    // Une seconde annulation ne remet rien en stock.
    $this->post(route('economat.external_issues.cancel', $sortie), ['cancellation_reason' => 'Encore'])->assertSessionHas('error');
    expect((float) $this->chaises->fresh()->current_stock)->toBe(40.0);
});

test('les sorties se filtrent par date ou par période, et s’impriment', function () {
    $this->actingAs(personneEconomat('econome'));

    $this->travelTo(CarbonImmutable::parse('2026-10-02 09:00'));
    $pret = sortieDeChaises($this, ['beneficiary_name' => 'Paroisse Saint-Michel', 'expected_return_at' => '2026-10-05']);
    $this->travelTo(CarbonImmutable::parse('2026-10-08 15:00'));
    $reparation = sortieDeChaises($this, [
        'reason' => 'reparation', 'beneficiary_name' => 'Froid Service', 'expected_return_at' => null,
        'lines' => [['stock_item_id' => $this->clim->id, 'quantity' => 1]],
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-09 10:00'));

    $noms = fn ($reponse) => collect($reponse->viewData('sorties')->items())->pluck('number')->all();

    expect($noms($this->get(route('economat.external_issues.index'))))->toBe([$reparation->number, $pret->number])
        ->and($noms($this->get(route('economat.external_issues.index', ['date' => '2026-10-02']))))->toBe([$pret->number])
        ->and($noms($this->get(route('economat.external_issues.index', ['du' => '2026-10-03', 'au' => '2026-10-09']))))->toBe([$reparation->number])
        ->and($noms($this->get(route('economat.external_issues.index', ['motif' => 'reparation']))))->toBe([$reparation->number])
        ->and($noms($this->get(route('economat.external_issues.index', ['recherche' => 'froid']))))->toBe([$reparation->number])
        // Le prêt devait revenir le 5 : il est en retard.
        ->and($noms($this->get(route('economat.external_issues.index', ['retour' => 'en_retard']))))->toBe([$pret->number]);

    $this->get(route('economat.external_issues.export', ['format' => 'impression', 'du' => '2026-10-01', 'au' => '2026-10-31']))->assertOk()
        ->assertSee('Sorties hors établissement')->assertSee('Froid Service')->assertSee('Paroisse Saint-Michel');
    $this->get(route('economat.external_issues.export', ['format' => 'excel', 'date' => '2026-10-08']))->assertOk()->assertHeader('content-disposition');

    // L'édition et la recherche de pièce les retrouvent aussi.
    $this->get(route('editions.show', ['edition' => 'sorties-hors-etablissement', 'du' => '2026-10-01', 'au' => '2026-10-31']))->assertOk()
        ->assertSee('Froid Service');
    $this->get(route('editions.index', ['q' => 'BSE-2026']))->assertSee($pret->number);
});

test("le magasinier et la direction consultent ; seul l'économe valide une sortie", function () {
    $this->actingAs(personneEconomat('econome'));
    $sortie = sortieDeChaises($this);

    $this->actingAs(personneEconomat('storekeeper'));
    $this->get(route('economat.external_issues.index'))->assertOk()->assertSee($sortie->number)->assertDontSee('Nouvelle sortie');
    $this->post(route('economat.external_issues.store'), ['reason' => 'don', 'beneficiary_name' => 'X', 'lines' => [['stock_item_id' => $this->clim->id, 'quantity' => 1]]])
        ->assertSessionHas('access_denied_popup', true);

    $this->actingAs(personneEconomat('manager'))->get(route('economat.external_issues.print', $sortie))->assertOk();
    $this->actingAs(personneEconomat('reception'))->get(route('economat.external_issues.index'))->assertSessionHas('access_denied_popup', true);

    expect(ExternalIssue::count())->toBe(1);
});

test("la sortie passe en charge du magasin au night audit, sous son propre libellé", function () {
    $this->actingAs(personneEconomat('econome'));
    $sortie = sortieDeChaises($this);

    app(\App\Services\LedgerPostingService::class)->postEconomatStock(\Illuminate\Support\Carbon::parse('2026-10-09'));

    $ecriture = \App\Models\JournalEntry::query()
        ->where('schema', \App\Services\LedgerPostingService::SCHEMA_ECONOMAT_STOCK . ':2026-10-09')
        ->with('lines')->sole();
    $charge = $ecriture->lines->firstWhere('debit', '>', 0);

    expect($charge->account_code)->toBe('603300')
        ->and($charge->debit)->toBe($sortie->total_value)
        ->and($charge->analytic_center)->toBe('economat')
        ->and($charge->label)->toBe('Sorties hors établissement')
        ->and($ecriture->lines->firstWhere('credit', '>', 0)->account_code)->toBe('332000');
});
