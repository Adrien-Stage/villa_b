<?php

use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Export CSV à plat des fiches techniques (?format=csv).
 *
 * Sa raison d'être est le déploiement : le personnel remplit les fiches dans
 * Excel avant de prendre en main la plateforme, puis le fichier est réinjecté
 * par l'import — qui ne lit que ce format à plat. Il doit donc être complet :
 * un type sans aucun poste doit y figurer, sinon personne ne pensera à le
 * renseigner.
 *
 * Le document de gestion, lui, est le classeur Excel servi par défaut —
 * voir RoomCostSheetWorkbookTest.
 */

function exportManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);

    return User::factory()->create(['role' => 'manager', 'is_active' => true]);
}

function typeAvecFiche(string $nom, string $code, int $prix = 4500000): RoomType
{
    return RoomType::create([
        'code' => $code, 'name' => $nom,
        'base_capacity' => 2, 'max_capacity' => 3,
        'base_price' => $prix, 'is_active' => true,
    ]);
}

/** Contenu du CSV téléchargé, BOM retiré. */
function contenuExport($reponse): string
{
    ob_start();
    $reponse->sendContent();

    return preg_replace('/^\xEF\xBB\xBF/', '', ob_get_clean());
}

test('l\'export sans sélection prend toutes les fiches', function () {
    $this->actingAs(exportManager());

    typeAvecFiche('Chambre Standard', 'STD');
    typeAvecFiche('Suite Présidentielle', 'SUIP', 35000000);

    $csv = contenuExport($this->get(route('rooms.cost_sheets.export', ['format' => 'csv']))->assertOk()->baseResponse);

    expect($csv)->toContain('Chambre Standard')
        ->and($csv)->toContain('Suite Présidentielle');
});

test('l\'export d\'une sélection ne retient que les fiches cochées', function () {
    $this->actingAs(exportManager());

    $standard = typeAvecFiche('Chambre Standard', 'STD');
    typeAvecFiche('Suite Présidentielle', 'SUIP', 35000000);

    $csv = contenuExport(
        $this->get(route('rooms.cost_sheets.export', ['format' => 'csv', 'types' => [$standard->id]]))->assertOk()->baseResponse
    );

    expect($csv)->toContain('Chambre Standard')
        ->and($csv)->not->toContain('Suite Présidentielle');
});

test('un type sans aucun poste figure quand même dans le fichier', function () {
    $this->actingAs(exportManager());

    typeAvecFiche('Chambre Économique', 'ECO', 3200000);

    $csv = contenuExport($this->get(route('rooms.cost_sheets.export', ['format' => 'csv']))->assertOk()->baseResponse);
    $lignes = array_filter(explode("\n", trim($csv)));

    // En-tête + une ligne d'amorce : sans elle, le type serait invisible dans
    // le fichier et personne ne penserait à le renseigner.
    expect($lignes)->toHaveCount(2)
        ->and($csv)->toContain('Chambre Économique');
});

test('chaque poste de coût sort sur sa ligne, avec les hypothèses de sa fiche', function () {
    $this->actingAs(exportManager());

    $type = typeAvecFiche('Chambre Standard', 'STD');

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

    $csv = contenuExport($this->get(route('rooms.cost_sheets.export', ['format' => 'csv']))->assertOk()->baseResponse);
    $lignes = array_filter(explode("\n", trim($csv)));

    expect($lignes)->toHaveCount(3);   // en-tête + 2 postes

    // Libellés lisibles plutôt que clés techniques : le fichier est destiné à
    // du personnel qui ne connaît pas la plateforme.
    expect($csv)->toContain('Électricité')
        ->and($csv)->toContain('Savonnette')
        ->and($csv)->toContain('Par personne et nuitée')
        // Montants en FCFA, pas en centimes.
        ->and($csv)->toContain('1200')
        ->and($csv)->toContain('250')
        // Les hypothèses de la fiche accompagnent chaque poste.
        ->and(substr_count($csv, '5000'))->toBeGreaterThanOrEqual(2);
});

test('le fichier porte les en-têtes attendus et le séparateur point-virgule', function () {
    $this->actingAs(exportManager());
    typeAvecFiche('Chambre Standard', 'STD');

    $reponse = $this->get(route('rooms.cost_sheets.export', ['format' => 'csv']))->assertOk();
    $csv = contenuExport($reponse->baseResponse);
    $entete = explode("\n", $csv)[0];

    foreach (['type_chambre', 'categorie', 'poste', 'base_calcul', 'quantite', 'cout_unitaire_fcfa'] as $colonne) {
        expect($entete)->toContain($colonne);
    }

    // Séparateur « ; » : c'est ce qu'Excel en configuration française ouvre
    // sans passer par un assistant d'importation.
    expect(substr_count($entete, ';'))->toBe(11);
});

test('le fichier s\'ouvre correctement dans Excel', function () {
    $this->actingAs(exportManager());
    typeAvecFiche('Chambre Économique', 'ECO');

    $reponse = $this->get(route('rooms.cost_sheets.export', ['format' => 'csv']))->assertOk();

    ob_start();
    $reponse->baseResponse->sendContent();
    $brut = ob_get_clean();

    // Le BOM UTF-8 conditionne l'affichage correct des accents sous Excel.
    expect(substr($brut, 0, 3))->toBe("\xEF\xBB\xBF")
        ->and($reponse->headers->get('Content-Type'))->toContain('text/csv');
});

test('une sélection contenant un identifiant inconnu est refusée', function () {
    $this->actingAs(exportManager());
    typeAvecFiche('Chambre Standard', 'STD');

    $this->get(route('rooms.cost_sheets.export', ['format' => 'csv', 'types' => [999999]]))
        ->assertSessionHasErrors('types.0');
});

test('la réception n\'accède pas à l\'export', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);
    $receptionniste = User::factory()->create(['role' => 'reception', 'is_active' => true]);

    // Les fiches techniques révèlent les coûts et les marges : elles restent
    // réservées à la direction et à la comptabilité.
    $reponse = $this->actingAs($receptionniste)->get(route('rooms.cost_sheets.export'));

    expect($reponse->status())->not->toBe(200);
});
