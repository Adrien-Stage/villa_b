<?php

/**
 * Modèle de document imprimable, commun à toute l'application.
 *
 * Chaque écran décrit ce qu'il met sur le papier ; un seul rendu s'occupe de
 * l'impression, du PDF, du tableur et du traitement de texte. Sans ce modèle,
 * chaque module réinvente son en-tête et son formatage, et l'établissement
 * sort des papiers qui ne se ressemblent pas.
 */

use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentExporter;
use App\Support\Document\Colonne;
use App\Support\Document\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => activerModules(['economat']));

function demande(User $auteur, array $attributs = []): StockRequisition
{
    $demande = StockRequisition::create(array_merge([
        'department'   => 'restaurant',
        'status'       => StockRequisition::STATUS_PENDING,
        'purpose'      => 'Réassort hebdomadaire',
        'requested_by' => $auteur->id,
    ], $attributs));

    $article = StockItem::create([
        'name' => 'Riz ' . random_int(1, 9999), 'unit' => 'kg',
        'current_stock' => 50, 'average_cost' => 90000, 'is_active' => true,
    ]);
    StockRequisitionLine::create([
        'stock_requisition_id' => $demande->id, 'stock_item_id' => $article->id,
        'quantity_requested' => 5,
    ]);

    return $demande;
}

// ── Le modèle ────────────────────────────────────────────────────────────────

test('un montant reste un nombre pour le tableur, un texte pour le papier', function () {
    $colonne = Colonne::montant('total', 'Montant');

    // Un tableur qui reçoit « 73 200 FCFA » ne sait plus additionner.
    expect($colonne->formater(7_320_000))->toBe('73 200 FCFA')
        ->and($colonne->valeurBrute(7_320_000))->toEqual(73200);
});

test('une valeur absente ne casse pas le document', function () {
    expect(Colonne::texte('x', 'X')->formater(null))->toBe('—')
        ->and(Colonne::date('d', 'D')->formater('pas une date'))->toBe('pas une date');
});

test('les nombres et les montants se lisent alignés à droite', function () {
    expect(Colonne::montant('a', 'A')->alignementDroite())->toBeTrue()
        ->and(Colonne::nombre('b', 'B')->alignementDroite())->toBeTrue()
        ->and(Colonne::texte('c', 'C')->alignementDroite())->toBeFalse();
});

test("l'en-tête omet les champs que l'établissement n'a pas renseignés", function () {
    Tenant::create([
        'name' => 'Hôtel sans papier', 'slug' => 'sans-papier',
        'address' => null, 'phone' => null, 'currency' => 'FCFA', 'is_active' => true,
    ]);

    $entete = Document::intitule('Test')->enTeteEtablissement();

    // « Adresse non renseignée » sur un document remis à un client fait
    // mauvais effet : on omet plutôt que de substituer.
    expect($entete)->not->toHaveKey('adresse')
        ->and($entete)->not->toHaveKey('tel');
});

test('le nom de fichier est sûr et horodaté', function () {
    $nom = Document::intitule("Demandes à l'économat")->nomDeFichier('pdf');

    expect($nom)->toMatch('/^demandes-a-leconomat_\d{8}_\d{6}\.pdf$/');
});

// ── Les quatre sorties ───────────────────────────────────────────────────────

test('chaque format est servi avec le bon type', function (string $format, string $type, bool $telechargement) {
    $auteur = User::factory()->create(['role' => 'econome']);
    demande($auteur);

    $reponse = $this->actingAs($auteur)
        ->get(route('economat.requisitions.export', ['format' => $format]));

    $reponse->assertOk();
    expect($reponse->headers->get('content-type'))->toContain($type);

    if ($telechargement) {
        expect($reponse->headers->get('content-disposition'))->toContain('attachment');
    }
})->with([
    ['impression', 'text/html', false],
    ['pdf', 'application/pdf', true],
    ['excel', 'spreadsheetml', true],
    ['word', 'wordprocessingml', true],
]);

test("l'aperçu imprimable porte l'identité de l'établissement", function () {
    $auteur = User::factory()->create(['role' => 'econome']);
    demande($auteur);

    // L'en-tête lit l'établissement de la personne connectée, et retombe sur
    // le premier enregistré quand le compte n'y est pas rattaché.
    Tenant::create([
        'name' => 'Hôtel Zingana', 'slug' => 'zingana',
        'phone' => '+237233445052', 'currency' => 'FCFA', 'is_active' => true,
    ]);

    $this->actingAs($auteur)
        ->get(route('economat.requisitions.export', ['format' => 'impression']))
        ->assertSee('Hôtel Zingana')
        ->assertSee('+237233445052')
        ->assertSee("Demandes à l'économat")
        ->assertSee($auteur->name);   // qui a édité le document
});

test('un format inconnu est refusé', function () {
    $this->actingAs(User::factory()->create(['role' => 'econome']))
        ->get(route('economat.requisitions.export', ['format' => 'powerpoint']))
        ->assertNotFound();
});

// ── Filtres ──────────────────────────────────────────────────────────────────

test("l'export applique les filtres de l'écran et les imprime", function () {
    $auteur = User::factory()->create(['role' => 'econome']);
    demande($auteur, ['status' => StockRequisition::STATUS_PENDING, 'purpose' => 'Réassort urgent']);
    demande($auteur, ['status' => StockRequisition::STATUS_DELIVERED, 'purpose' => 'Livraison close']);

    $page = $this->actingAs($auteur)
        ->get(route('economat.requisitions.export', ['format' => 'impression', 'statut' => 'pending']))
        ->getContent();

    // Un export qui ne rendrait pas ce que l'écran affiche serait pire
    // qu'absent.
    expect($page)->toContain('Réassort urgent')
        ->and($page)->not->toContain('Livraison close')
        // Un tableau sans ses filtres ne se relit pas six mois plus tard.
        ->and($page)->toContain('En attente');
});

test('une période sans demande le dit plutôt que de rendre un tableau vide', function () {
    $auteur = User::factory()->create(['role' => 'econome']);
    demande($auteur);

    $this->actingAs($auteur)
        ->get(route('economat.requisitions.export', ['format' => 'impression', 'du' => '2020-01-01', 'au' => '2020-01-02']))
        ->assertSee('Aucune donnée pour ces critères');
});

test('une date illisible est ignorée plutôt que de faire échouer la page', function () {
    $auteur = User::factory()->create(['role' => 'econome']);
    demande($auteur);

    $this->actingAs($auteur)
        ->get(route('economat.requisitions.index', ['du' => 'hier matin']))
        ->assertOk();
});

// ── Cloisonnement et traçabilité ─────────────────────────────────────────────

test("un demandeur n'exporte que ses propres demandes", function () {
    $chef   = User::factory()->create(['role' => 'restaurant_chief']);
    $autre  = User::factory()->create(['role' => 'reception']);

    demande($chef,  ['purpose' => 'Ma demande']);
    demande($autre, ['purpose' => "La demande d'un autre"]);

    $page = $this->actingAs($chef)
        ->get(route('economat.requisitions.export', ['format' => 'impression']))->getContent();

    // Le cloisonnement de l'écran doit valoir pour le papier : un export est
    // exactement le moment où une fuite passe inaperçue.
    expect($page)->toContain('Ma demande')
        ->and($page)->not->toContain("La demande d'un autre");
});

test("un rôle sans le droit n'exporte pas", function () {
    expect($this->actingAs(User::factory()->create(['role' => 'housekeeping_staff']))
        ->get(route('economat.requisitions.export', ['format' => 'pdf']))->status())
        ->not->toBe(200);
});

test("l'export est journalisé", function () {
    $auteur = User::factory()->create(['role' => 'econome']);
    demande($auteur);

    $this->actingAs($auteur)->get(route('economat.requisitions.export', ['format' => 'excel']));

    $this->assertDatabaseHas('audit_logs', ['event_type' => 'export', 'module' => 'economat']);
});

test("le bouton de bon de commande n'apparaît qu'à qui peut en créer", function () {
    activerModules(['economat']);

    // L'économe le crée.
    $this->actingAs(User::factory()->create(['role' => 'econome']))
        ->get(route('economat.orders.index'))
        ->assertSee('Nouveau bon');

    // Le comptable passe désormais ses bons de commande fournisseur depuis
    // son espace comptabilité.
    $this->actingAs(User::factory()->create(['role' => 'accountant']))
        ->get(route('economat.orders.index'))
        ->assertOk()
        ->assertSee('Nouveau bon');
});
