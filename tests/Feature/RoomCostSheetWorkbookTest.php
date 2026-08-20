<?php

namespace Tests\Feature;

use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as LecteurXlsx;

uses(RefreshDatabase::class);

/**
 * Classeur Excel des fiches techniques : il doit reprendre la structure du
 * document de référence — synthèse, coûts unitaires, une fiche par onglet —
 * et porter l'identité de l'établissement.
 */

function classeurManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);

    return User::factory()->create(['role' => 'manager', 'is_active' => true]);
}

function typeChambre(string $nom, string $code, int $prix = 4500000): RoomType
{
    return RoomType::create([
        'code' => $code, 'name' => $nom,
        'base_capacity' => 2, 'max_capacity' => 3,
        'base_price' => $prix, 'is_active' => true,
    ]);
}

/** Télécharge l'export et rouvre le classeur, comme le ferait Excel. */
function classeurTelecharge(array $params = []): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $reponse = test()->get(route('rooms.cost_sheets.export', $params))->assertOk();

    ob_start();
    $reponse->baseResponse->sendContent();
    $binaire = ob_get_clean();

    $chemin = tempnam(sys_get_temp_dir(), 'fiches') . '.xlsx';
    file_put_contents($chemin, $binaire);

    $classeur = (new LecteurXlsx())->load($chemin);
    @unlink($chemin);

    return $classeur;
}

test("l'export par défaut est un classeur Excel", function () {
    $this->actingAs(classeurManager());
    typeChambre('Chambre Standard', 'STD');

    $reponse = $this->get(route('rooms.cost_sheets.export'))->assertOk();

    expect($reponse->headers->get('Content-Type'))
        ->toContain('spreadsheetml.sheet')
        ->and($reponse->headers->get('Content-Disposition'))->toContain('.xlsx');
});

test('le classeur reprend la structure du document de référence', function () {
    $this->actingAs(classeurManager());
    typeChambre('Chambre Standard', 'STD');
    typeChambre('Suite Présidentielle', 'SUIP', 35000000);

    $classeur = classeurTelecharge();

    // Synthèse et coûts unitaires en tête, puis une fiche par type.
    expect($classeur->getSheetNames())->toBe([
        '📊 Tableau de Bord Général',
        '⚙️ Paramètres Généraux',
        'Chambre Standard',
        'Suite Présidentielle',
    ]);

    $fiche = $classeur->getSheetByName('Chambre Standard');

    expect($fiche->getCell('A3')->getValue())->toBe("1. CARACTÉRISTIQUES DE L'HÉBERGEMENT")
        ->and($fiche->getCell('A4')->getValue())->toBe('Catégorie de chambre')
        ->and($fiche->getCell('B4')->getValue())->toBe('Chambre Standard')
        ->and($fiche->getCell('B5')->getValue())->toBe('STD')
        ->and($fiche->getCell('B6')->getValue())->toBe(2)
        ->and($fiche->getCell('B8')->getValue())->toBe(45000.0)
        ->and($fiche->getCell('A10')->getValue())->toStartWith('2. CHARGES VARIABLES')
        ->and($fiche->getCell('A11')->getValue())->toBe('Catégorie')
        ->and($fiche->getCell('F11')->getValue())->toBe('Coût / Nuitée (FCFA)');
});

test('les postes de coût sortent avec des formules vivantes', function () {
    $this->actingAs(classeurManager());

    $type = typeChambre('Chambre Standard', 'STD');

    RoomCostSheet::create([
        'room_type_id' => $type->id, 'reference_occupants' => 2,
        'avg_length_of_stay' => 2.5, 'fixed_cost_per_night' => 500000,   // 5 000 FCFA
    ]);

    RoomCostItem::create([
        'room_type_id' => $type->id, 'category' => 'energy', 'label' => 'Électricité',
        'basis' => 'per_night', 'quantity' => 1, 'unit_cost' => 120000,  // 1 200 FCFA
        'sort_order' => 1, 'is_active' => true,
    ]);
    RoomCostItem::create([
        'room_type_id' => $type->id, 'category' => 'consumable', 'label' => 'Savonnette',
        'basis' => 'per_guest_night', 'quantity' => 2, 'unit_cost' => 25000,  // 250 FCFA
        'sort_order' => 2, 'is_active' => true,
    ]);
    RoomCostItem::create([
        'room_type_id' => $type->id, 'category' => 'linen', 'label' => 'Blanchisserie',
        'basis' => 'per_stay', 'quantity' => 1, 'unit_cost' => 300000,  // 3 000 FCFA
        'sort_order' => 3, 'is_active' => true,
    ]);

    $fiche = classeurTelecharge()->getSheetByName('Chambre Standard');

    // Montants en FCFA, jamais en centimes.
    expect($fiche->getCell('E12')->getValue())->toBe(1200.0)
        ->and($fiche->getCell('B12')->getValue())->toBe('Électricité')
        ->and($fiche->getCell('C13')->getValue())->toBe('Par personne et nuitée');

    // La base d'imputation vit dans la formule : corriger la capacité ou la
    // durée de séjour recalcule toute la fiche.
    expect($fiche->getCell('F12')->getValue())->toBe('=D12*E12')
        ->and($fiche->getCell('F13')->getValue())->toBe('=D13*E13*$B$6')
        ->and($fiche->getCell('F14')->getValue())->toContain('/$B$7');

    // Totaux et synthèse : postes de 12 à 14, total en 15.
    expect($fiche->getCell('A15')->getValue())->toBe('TOTAL CHARGES VARIABLES / NUITÉE')
        ->and($fiche->getCell('F15')->getValue())->toBe('=SUM(F12:F14)')
        ->and($fiche->getCell('F19')->getValue())->toBe(5000.0)          // charge fixe
        ->and($fiche->getCell('A20')->getValue())->toBe('TOTAL CHARGES FIXES / NUITÉE')
        ->and($fiche->getCell('A23')->getValue())->toBe('COÛT DE REVIENT COMPLET PAR NUITÉE (CPOR)')
        ->and($fiche->getCell('D23')->getValue())->toBe('=F15+F20')
        ->and($fiche->getCell('A28')->getValue())->toBe('TAUX DE MARGE NETTE');
});

test('le tableau de bord pointe chaque fiche', function () {
    $this->actingAs(classeurManager());
    typeChambre('Chambre Standard', 'STD');
    typeChambre('Suite Présidentielle', 'SUIP', 35000000);

    $bord = classeurTelecharge()->getSheetByName('📊 Tableau de Bord Général');

    expect($bord->getCell('A4')->getValue())->toBe('Code')
        ->and($bord->getCell('I4')->getValue())->toBe('Taux Marge Nette')
        ->and($bord->getCell('A5')->getValue())->toBe("='Chambre Standard'!B5")
        ->and($bord->getCell('B6')->getValue())->toBe("='Suite Présidentielle'!B4")
        ->and($bord->getCell('A7')->getValue())->toBe('MOYENNE')
        ->and($bord->getCell('G7')->getValue())->toContain('AVERAGE(G5:G6)');
});

test("l'onglet des coûts unitaires reprend le barème appliqué", function () {
    $this->actingAs(classeurManager());

    $type = typeChambre('Chambre Standard', 'STD');
    RoomCostItem::create([
        'room_type_id' => $type->id, 'category' => 'consumable', 'label' => 'Savonnette',
        'basis' => 'per_guest_night', 'quantity' => 2, 'unit_cost' => 25000,
        'sort_order' => 1, 'is_active' => true,
    ]);

    $parametres = classeurTelecharge()->getSheetByName('⚙️ Paramètres Généraux');

    expect($parametres->getCell('A4')->getValue())->toBe('Catégorie')
        ->and($parametres->getCell('B5')->getValue())->toBe('Savonnette')
        ->and($parametres->getCell('C5')->getValue())->toBe(250.0)
        ->and($parametres->getCell('D5')->getValue())->toBe('FCFA / personne / nuitée');
});

test("le classeur porte l'identité de l'établissement", function () {
    $this->actingAs(classeurManager());

    $tenant = Tenant::where('slug', 'villa-boutanga')->first();
    $tenant->update([
        'name' => 'Hôtel Les Palmiers',
        'address' => 'Kribi, Cameroun',
        'settings' => ['theme' => ['primary' => '#391F0E', 'secondary' => '#CCAB87', 'accent' => '#EED4A3']],
    ]);

    typeChambre('Chambre Standard', 'STD');

    $classeur = classeurTelecharge();
    $bord = $classeur->getSheetByName('📊 Tableau de Bord Général');
    $fiche = $classeur->getSheetByName('Chambre Standard');

    expect($bord->getCell('A1')->getValue())->toContain('HÔTEL LES PALMIERS')
        ->and($bord->getCell('A2')->getValue())->toContain('Kribi, Cameroun')
        ->and($fiche->getCell('A1')->getValue())->toContain('HÔTEL LES PALMIERS')
        ->and($classeur->getProperties()->getCompany())->toBe('Hôtel Les Palmiers');

    // Bandeau à la couleur de l'établissement, texte clair puisque le fond est foncé.
    $bandeau = $fiche->getStyle('A1');
    expect($bandeau->getFill()->getStartColor()->getARGB())->toBe('FF391F0E')
        ->and($bandeau->getFont()->getColor()->getARGB())->toBe('FFFFFFFF');
});

test('une fiche sans aucun poste le dit au lieu d\'afficher zéro', function () {
    $this->actingAs(classeurManager());
    typeChambre('Chambre Économique', 'ECO', 3200000);

    $fiche = classeurTelecharge()->getSheetByName('Chambre Économique');

    expect($fiche->getCell('A12')->getValue())->toContain('Aucun poste variable saisi')
        ->and($fiche->getCell('F12')->getValue())->toBe(0);
});

test('la sélection de fiches limite les onglets exportés', function () {
    $this->actingAs(classeurManager());

    $standard = typeChambre('Chambre Standard', 'STD');
    typeChambre('Suite Présidentielle', 'SUIP', 35000000);

    $classeur = classeurTelecharge(['types' => [$standard->id]]);

    expect($classeur->getSheetNames())->toContain('Chambre Standard')
        ->and($classeur->getSheetNames())->not->toContain('Suite Présidentielle');
});
