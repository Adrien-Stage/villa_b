<?php

use App\Models\RoomCostItem;
use App\Models\RoomCostSheet;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

function importManager(): User
{
    test()->seed(\Database\Seeders\TenantSeeder::class);

    return User::factory()->create(['role' => 'manager', 'is_active' => true]);
}

function creerTypeChambre(string $nom, string $code): RoomType
{
    return RoomType::create([
        'code' => $code,
        'name' => $nom,
        'base_capacity' => 2,
        'max_capacity' => 3,
        'base_price' => 4500000,
        'is_active' => true,
    ]);
}

test('le modele d\'importation CSV est telechargeable', function () {
    $this->actingAs(importManager());

    $response = $this->get(route('rooms.cost_sheets.export', ['template' => 1]))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('modele_fiches_techniques.csv');
});

test('l\'importation CSV cree les hypotheses et les postes de cout', function () {
    $this->actingAs(importManager());
    $type = creerTypeChambre('Chambre Standard', 'STD');

    $csvContent = implode("\n", [
        "type_chambre;code_type;occupants_reference;sejour_moyen_nuits;charge_fixe_par_nuitee_fcfa;categorie;poste;base_calcul;quantite;cout_unitaire_fcfa;actif;notes",
        "Chambre Standard;STD;2;2,5;5000;Électricité;Électricité;Par nuitée;1;1200;oui;Clim",
        "Chambre Standard;STD;2;2,5;5000;Consommables;Savonnette;Par personne et nuitée;2;250;oui;Produits d'accueil",
    ]);

    $file = UploadedFile::fake()->createWithContent('fiches.csv', $csvContent);

    $response = $this->post(route('rooms.cost_sheets.import'), [
        'csv_file' => $file,
    ]);

    $response->assertRedirect(route('rooms.cost_sheets.index'));
    $response->assertSessionHas('success');

    // Vérification des hypothèses créées
    $sheet = RoomCostSheet::where('room_type_id', $type->id)->first();
    expect($sheet)->not->toBeNull()
        ->and($sheet->reference_occupants)->toBe(2)
        ->and((float) $sheet->avg_length_of_stay)->toBe(2.5)
        ->and($sheet->fixed_cost_per_night)->toBe(500000); // 5 000 FCFA en centimes

    // Vérification des postes de coût
    $items = RoomCostItem::where('room_type_id', $type->id)->get();
    expect($items)->toHaveCount(2);

    $elec = $items->firstWhere('label', 'Électricité');
    expect($elec)->not->toBeNull()
        ->and($elec->category)->toBe('energy')
        ->and($elec->basis)->toBe('per_night')
        ->and($elec->unit_cost)->toBe(120000); // 1 200 FCFA en centimes

    $savon = $items->firstWhere('label', 'Savonnette');
    expect($savon)->not->toBeNull()
        ->and($savon->category)->toBe('consumable')
        ->and($savon->basis)->toBe('per_guest_night')
        ->and((float) $savon->quantity)->toBe(2.0)
        ->and($savon->unit_cost)->toBe(25000); // 250 FCFA en centimes
});

test('l\'importation met a jour un poste existant portant le meme libelle', function () {
    $this->actingAs(importManager());
    $type = creerTypeChambre('Chambre Standard', 'STD');

    RoomCostItem::create([
        'room_type_id' => $type->id,
        'category' => 'energy',
        'label' => 'Électricité',
        'basis' => 'per_night',
        'quantity' => 1,
        'unit_cost' => 100000, // 1 000 FCFA
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $csvContent = implode("\n", [
        "type_chambre;code_type;occupants_reference;sejour_moyen_nuits;charge_fixe_par_nuitee_fcfa;categorie;poste;base_calcul;quantite;cout_unitaire_fcfa;actif;notes",
        "Chambre Standard;STD;2;3;6000;Électricité;Électricité;Par nuitée;1;1500;oui;Nouveau tarif",
    ]);

    $file = UploadedFile::fake()->createWithContent('fiches.csv', $csvContent);

    $this->post(route('rooms.cost_sheets.import'), [
        'csv_file' => $file,
    ])->assertRedirect(route('rooms.cost_sheets.index'));

    $elec = RoomCostItem::where('room_type_id', $type->id)->where('label', 'Électricité')->first();
    expect($elec->unit_cost)->toBe(150000) // Mis à jour à 1 500 FCFA
        ->and($elec->notes)->toBe('Nouveau tarif');

    $sheet = RoomCostSheet::where('room_type_id', $type->id)->first();
    expect((float) $sheet->avg_length_of_stay)->toBe(3.0)
        ->and($sheet->fixed_cost_per_night)->toBe(600000); // 6 000 FCFA
});

test('un type de chambre inconnu génère une erreur d\'importation', function () {
    $this->actingAs(importManager());

    $csvContent = implode("\n", [
        "type_chambre;code_type;occupants_reference;sejour_moyen_nuits;charge_fixe_par_nuitee_fcfa;categorie;poste;base_calcul;quantite;cout_unitaire_fcfa;actif;notes",
        "Type Inexistant;INX;2;2;5000;Électricité;Électricité;Par nuitée;1;1200;oui;",
    ]);

    $file = UploadedFile::fake()->createWithContent('fiches.csv', $csvContent);

    $response = $this->post(route('rooms.cost_sheets.import'), [
        'csv_file' => $file,
    ]);

    $response->assertRedirect(route('rooms.cost_sheets.index'));
    $response->assertSessionHas('import_errors');

    $errors = session('import_errors');
    expect($errors)->not->toBeEmpty()
        ->and($errors[0])->toContain('introuvable');
});
