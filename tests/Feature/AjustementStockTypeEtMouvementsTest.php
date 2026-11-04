<?php

use App\Models\Role;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockMovementJournal;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function creerEconomeAjustement(): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', 'econome')->pluck('id'));

    return $user->fresh();
}

test('l\'ajustement de stock permet de choisir le type et affiche le stock passé à 100 sans afficher +40 en entrée', function () {
    $user = creerEconomeAjustement();

    $item = StockItem::create([
        'name'          => 'Serviettes éponge',
        'unit'          => 'pcs',
        'current_stock' => 60,
        'average_cost'  => 250000,
        'is_active'     => true,
    ]);

    // 1. Ajustement avec sélection "Stock initial" pour passer de 60 à 100
    $response = $this->actingAs($user)->post(route('economat.items.adjust', $item), [
        'adjustment_type'  => 'initial_stock',
        'counted_quantity' => 100,
        'reason'           => 'Erreur de comptage initial',
    ]);

    $response->assertRedirect();
    expect((float) $item->fresh()->current_stock)->toBe(100.0);

    // Vérification du mouvement créé en base
    $movement = StockMovement::where('stock_item_id', $item->id)->latest('id')->first();
    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe(StockMovement::TYPE_ADJUSTMENT)
        ->and($movement->source_type)->toBe(StockMovement::SOURCE_ADJUST_INITIAL)
        ->and($movement->typeLabel())->toBe('Correction stock initial')
        ->and((float) $movement->stockAvant())->toBe(60.0)
        ->and((float) $movement->stock_after)->toBe(100.0);

    // Vérification du formattage de la ligne de journal
    $journal = app(StockMovementJournal::class);
    $ligne = $journal->ligne($movement, []);

    expect($ligne['nature'])->toBe('Correction stock initial')
        ->and($ligne['avant'])->toBe(60.0)
        ->and($ligne['entree'])->toBeNull()   // Ne doit PAS afficher +40 en entrée
        ->and($ligne['sortie'])->toBeNull()
        ->and($ligne['apres'])->toBe(100.0)
        ->and($ligne['valeur'])->toBeNull();

    // 2. Vérification sur la page /economat/mouvements
    $resMovements = $this->actingAs($user)->get(route('economat.movements.index'));
    $resMovements->assertOk();
    $resMovements->assertSee('Correction stock initial');
    $resMovements->assertSee('Erreur de comptage initial');

    // 3. Vérification sur la fiche article /economat/articles/{item}
    $resShow = $this->actingAs($user)->get(route('economat.items.show', $item));
    $resShow->assertOk();
    $resShow->assertSee('Correction stock initial');
    $resShow->assertSee('60');
    $resShow->assertSee('100');
});

test('l\'ajustement avec régularisation de livraison étiquette correctement le mouvement', function () {
    $user = creerEconomeAjustement();

    $item = StockItem::create([
        'name'          => 'Huile de tournesol 5L',
        'unit'          => 'bidon',
        'current_stock' => 15,
        'average_cost'  => 500000,
        'is_active'     => true,
    ]);

    $this->actingAs($user)->post(route('economat.items.adjust', $item), [
        'adjustment_type'  => 'delivery',
        'counted_quantity' => 25,
        'reason'           => 'BL #842 oublié',
    ]);

    expect((float) $item->fresh()->current_stock)->toBe(25.0);

    $movement = StockMovement::where('stock_item_id', $item->id)->latest('id')->first();
    expect($movement->source_type)->toBe(StockMovement::SOURCE_ADJUST_DELIVERY)
        ->and($movement->typeLabel())->toBe('Régularisation livraison');

    $journal = app(StockMovementJournal::class);
    $ligne = $journal->ligne($movement, []);
    expect($ligne['nature'])->toBe('Régularisation livraison')
        ->and($ligne['avant'])->toBe(15.0)
        ->and($ligne['entree'])->toBeNull()
        ->and($ligne['apres'])->toBe(25.0);
});
