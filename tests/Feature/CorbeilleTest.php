<?php

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomImage;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Models\TrashEntry;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * La corbeille de l'établissement : ce qu'efface un bouton « Supprimer » y
 * est photographié — la fiche, ce qui part avec elle en cascade, les liens
 * qu'elle tenait, ses fichiers — puis la console, par son administrateur
 * TECH, le restaure ou le purge. Personne dans l'hôtel ne la voit.
 */

beforeEach(function () {
    config(['orchestration.secret' => 'jeton-de-console']);
    RoleCatalog::sync();
    activerModules(['economat', 'hebergement']);
});

function enteteCorbeille(): array
{
    return ['Authorization' => 'Bearer jeton-de-console'];
}

function agentCorbeille(string $role): User
{
    $user = User::factory()->create(['is_active' => true, 'name' => 'Agent ' . $role]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test("un fournisseur supprimé part à la corbeille avec ses liens, et la console le restaure tel qu'il était", function () {
    $acheteur = agentCorbeille('purchasing_manager');
    $fournisseur = Supplier::create(['name' => 'Grossiste Nord', 'phone' => '690000000', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'supplier_id' => $fournisseur->id, 'is_active' => true]);

    $this->actingAs($acheteur)->delete(route('economat.suppliers.destroy', $fournisseur))->assertSessionHas('success');

    expect(Supplier::find($fournisseur->id))->toBeNull()
        ->and($riz->fresh()->supplier_id)->toBeNull();

    $entree = TrashEntry::sole();
    expect($entree->type)->toBe('Fournisseur')
        ->and($entree->module)->toBe('economat')
        ->and($entree->label)->toBe('Grossiste Nord')
        ->and($entree->deleted_by_name)->toBe('Agent purchasing_manager')
        ->and($entree->resume()['liens'])->toBe(1);

    // La console liste et restaure.
    $this->getJson('/api/corbeille', enteteCorbeille())->assertOk()
        ->assertJsonPath('entrees.0.libelle', 'Grossiste Nord')
        ->assertJsonPath('entrees.0.supprime_par', 'Agent purchasing_manager')
        ->assertJsonPath('filtres.types.suppliers', 'Fournisseur');

    $this->postJson("/api/corbeille/{$entree->id}/restaurer", ['auteur' => 'Tech Wetchah'], enteteCorbeille())
        ->assertOk()->assertJsonPath('entree.restaure_par', 'Tech Wetchah');

    expect(Supplier::find($fournisseur->id)?->name)->toBe('Grossiste Nord')
        ->and($riz->fresh()->supplier_id)->toBe($fournisseur->id)
        ->and($entree->fresh()->restored_at)->not->toBeNull()
        ->and(AuditLog::where('event_type', 'corbeille_restauration')->exists())->toBeTrue();

    // Restaurée, l'entrée quitte la corbeille, mais reste dans l'historique.
    $this->getJson('/api/corbeille', enteteCorbeille())->assertOk()->assertJsonCount(0, 'entrees');
    $this->getJson('/api/corbeille?etat=restauree', enteteCorbeille())->assertOk()->assertJsonCount(1, 'entrees');
});

test("ce qui part en cascade et les fichiers reviennent avec la fiche", function () {
    Storage::fake('public');
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    $chambre = Room::query()->firstOrFail();
    Storage::disk('public')->put('rooms/101-a.jpg', 'photo');
    $photo = RoomImage::create(['room_id' => $chambre->id, 'path' => 'rooms/101-a.jpg', 'sort_order' => 1]);

    $chambre->delete();

    $entree = TrashEntry::where('table_name', 'rooms')->sole();
    expect(RoomImage::find($photo->id))->toBeNull()
        ->and($entree->label)->toBe('Chambre ' . $chambre->number)
        ->and($entree->resume()['lignes_emportees'])->toBeGreaterThanOrEqual(1)
        ->and($entree->resume()['fichiers'])->toBe(1);
    Storage::disk('public')->assertMissing('rooms/101-a.jpg');
    Storage::disk('public')->assertExists("corbeille/{$entree->id}/rooms/101-a.jpg");

    $this->postJson("/api/corbeille/{$entree->id}/restaurer", ['auteur' => 'Tech'], enteteCorbeille())->assertOk();

    expect(Room::find($chambre->id))->not->toBeNull()
        ->and(RoomImage::find($photo->id)?->path)->toBe('rooms/101-a.jpg');
    Storage::disk('public')->assertExists('rooms/101-a.jpg');
});

test("la restauration est refusée, avec son motif, quand un élément identique a été recréé", function () {
    $unite = StockUnit::create(['name' => 'fût', 'sort_order' => 9, 'is_active' => true]);
    $unite->delete();
    StockUnit::create(['name' => 'fût', 'sort_order' => 9, 'is_active' => true]);
    $entree = TrashEntry::sole();

    $this->postJson("/api/corbeille/{$entree->id}/restaurer", ['auteur' => 'Tech'], enteteCorbeille())
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'existe de nouveau') || str_contains($m, 'identique'));

    expect($entree->fresh()->restored_at)->toBeNull();
});

test("un article dont la catégorie est aussi à la corbeille se restaure après elle", function () {
    $categorie = StockCategory::create(['name' => 'Épicerie']);
    $article = StockItem::create(['name' => 'Sel', 'unit' => 'kg', 'stock_category_id' => $categorie->id, 'is_active' => true]);
    $article->delete();
    $categorie->delete();
    [$entreeArticle, $entreeCategorie] = [TrashEntry::where('table_name', 'stock_items')->sole(), TrashEntry::where('table_name', 'stock_categories')->sole()];

    $this->postJson("/api/corbeille/{$entreeArticle->id}/restaurer", ['auteur' => 'Tech'], enteteCorbeille())
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'restaurez d\'abord'));
    expect(StockItem::find($article->id))->toBeNull();

    $this->postJson("/api/corbeille/{$entreeCategorie->id}/restaurer", ['auteur' => 'Tech'], enteteCorbeille())->assertOk();
    $this->postJson("/api/corbeille/{$entreeArticle->id}/restaurer", ['auteur' => 'Tech'], enteteCorbeille())->assertOk();
    expect(StockItem::find($article->id)?->stock_category_id)->toBe($categorie->id);
});

test("la corbeille se filtre ; la purge efface l'entrée et ses fichiers pour de bon", function () {
    Storage::fake('local');
    Supplier::create(['name' => 'Alpha', 'is_active' => true])->delete();
    StockUnit::create(['name' => 'bidon', 'sort_order' => 3, 'is_active' => true])->delete();
    StockCategory::create(['name' => 'Boissons'])->delete();

    $this->getJson('/api/corbeille?type=suppliers', enteteCorbeille())->assertOk()->assertJsonCount(1, 'entrees');
    $this->getJson('/api/corbeille?module=economat', enteteCorbeille())->assertOk()->assertJsonCount(3, 'entrees');
    $this->getJson('/api/corbeille?q=bois', enteteCorbeille())->assertOk()->assertJsonCount(1, 'entrees')->assertJsonPath('entrees.0.libelle', 'Boissons');
    $this->getJson('/api/corbeille?du=' . now()->addDay()->toDateString(), enteteCorbeille())->assertOk()->assertJsonCount(0, 'entrees');

    $entree = TrashEntry::where('label', 'Alpha')->sole();
    $this->deleteJson("/api/corbeille/{$entree->id}", ['auteur' => 'Tech'], enteteCorbeille())->assertOk();
    expect(TrashEntry::find($entree->id))->toBeNull()
        ->and(AuditLog::where('event_type', 'corbeille_purge')->exists())->toBeTrue();

    // La console nomme qui agit ; sans jeton, rien ne s'ouvre.
    $this->postJson('/api/corbeille/' . TrashEntry::first()->id . '/restaurer', [], enteteCorbeille())->assertStatus(422);
    $this->getJson('/api/corbeille')->assertStatus(401);
});
