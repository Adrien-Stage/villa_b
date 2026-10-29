<?php

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptAttachment;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\TrashEntry;
use App\Models\User;
use App\Services\GoodsReceiptService;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Bordereaux de livraison et date de chaque livraison : l'économat joint à
 * chaque bon de réception un ou plusieurs bordereaux (facultatifs), saisit
 * la date de chaque livraison jusqu'à la complète, et suit les livraisons
 * d'un même bon de commande. Un bordereau retiré part à la corbeille.
 */

beforeEach(function () {
    Storage::fake('local');
    $this->seed(\Database\Seeders\TenantSeeder::class);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function agentBordereaux(string $role): User
{
    $user = User::factory()->create(['is_active' => true, 'name' => 'Agent ' . $role]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Un bon de commande transmis hier au fournisseur : son bon de réception attend. */
function bonTransmis(float $quantite = 10): PurchaseOrder
{
    $fournisseur = Supplier::create(['name' => 'Grossiste Nord', 'phone' => '690000000', 'is_active' => true]);
    $riz = StockItem::create(['name' => 'Riz', 'unit' => 'kg', 'is_active' => true]);
    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
    $bon->lines()->create(['stock_item_id' => $riz->id, 'quantity_ordered' => $quantite, 'unit_price' => 90000]);
    app(\App\Services\PurchaseOrderService::class)->markTransmitted($bon, 'telephone');
    $bon->update(['sent_at' => now()->subDay()]);

    return $bon->fresh('lines');
}

test("une livraison partielle porte sa date et ses bordereaux ; la suivante aussi, jusqu'à la complète", function () {
    $bon = bonTransmis(10);
    $ligne = $bon->lines->first();
    $econome = agentBordereaux('econome');

    $this->actingAs($econome)->get(route('economat.receipts.create', $bon))->assertOk()
        ->assertSee('name="bordereaux[]"', false)->assertSee('Date et heure de réception *');

    // Première livraison, partielle, avec deux bordereaux.
    $this->actingAs($econome)->post(route('economat.receipts.store', $bon), [
        'received_at' => now()->subHours(3)->format('Y-m-d H:i'),
        'livraison'   => GoodsReceipt::DELIVERY_PARTIAL,
        'lines'       => [$ligne->id => ['quantity_delivered' => 6]],
        'bordereaux'  => [UploadedFile::fake()->create('BL-1.pdf', 120, 'application/pdf'), UploadedFile::fake()->image('BL-1-photo.jpg')],
    ])->assertRedirect()->assertSessionHas('success');

    $premiere = GoodsReceipt::where('status', GoodsReceipt::STATUS_RECEIVED)->sole();
    expect($premiere->attachments()->count())->toBe(2)
        ->and($premiere->received_at->format('H'))->toBe(now()->subHours(3)->format('H'));
    foreach ($premiere->attachments as $bordereau) {
        Storage::disk('local')->assertExists($bordereau->path);
    }

    // Seconde livraison, complète, sans bordereau : le rappel s'affiche.
    $this->actingAs($econome)->post(route('economat.receipts.store', $bon), [
        'received_at' => now()->subMinutes(10)->format('Y-m-d H:i'),
        'livraison'   => GoodsReceipt::DELIVERY_COMPLETE,
        'lines'       => [$ligne->id => ['quantity_delivered' => 4]],
    ])->assertRedirect()->assertSessionHas('success');
    $seconde = GoodsReceipt::where('status', GoodsReceipt::STATUS_RECEIVED)->latest('id')->first();

    // Le suivi des livraisons du bon, sur la fiche du bon et sur le bon de réception.
    $this->actingAs($econome)->get(route('economat.orders.show', $bon))->assertOk()
        ->assertSee('Suivi des livraisons')->assertSee('Livraison 1')->assertSee('Livraison 2')
        ->assertSee('BL-1.pdf')->assertSee('Aucun bordereau joint')->assertSee('Livraison complète');
    $this->actingAs($econome)->get(route('economat.receipts.show', $seconde))->assertOk()
        ->assertSee('Bordereaux de livraison (0)')->assertSee('Aucun bordereau joint');
});

test("la date de chaque livraison est obligatoire, ni future, ni antérieure à l'envoi du bon", function () {
    $bon = bonTransmis();
    $ligne = $bon->lines->first();
    $this->actingAs(agentBordereaux('econome'));

    $saisie = fn (array $plus) => ['lines' => [$ligne->id => ['quantity_delivered' => 2]], 'livraison' => 'partielle'] + $plus;

    $this->post(route('economat.receipts.store', $bon), $saisie([]))->assertSessionHasErrors('received_at');
    $this->post(route('economat.receipts.store', $bon), $saisie(['received_at' => now()->addHour()->format('Y-m-d H:i')]))->assertSessionHasErrors('received_at');
    $this->post(route('economat.receipts.store', $bon), $saisie(['received_at' => now()->subDays(5)->format('Y-m-d H:i')]))->assertSessionHasErrors('received_at');

    expect(GoodsReceipt::where('status', GoodsReceipt::STATUS_RECEIVED)->count())->toBe(0);
});

test("on joint un bordereau après coup ; il ne s'ouvre qu'à qui voit les bons d'entrée ; l'économe seul le retire, vers la corbeille", function () {
    $bon = bonTransmis();
    $econome = agentBordereaux('econome');
    $recu = app(GoodsReceiptService::class)->receive($bon, ['lines' => [$bon->lines->first()->id => ['quantity_delivered' => 10]]], $econome);

    $magasinier = agentBordereaux('storekeeper');
    $this->actingAs($magasinier)->post(route('economat.receipts.attachments.store', $recu), [
        'bordereaux' => [UploadedFile::fake()->create('BL-tardif.pdf', 80, 'application/pdf')],
    ])->assertSessionHas('success');
    $bordereau = GoodsReceiptAttachment::sole();

    // Ouvert par le responsable des achats ; refusé à la réception.
    $this->actingAs(agentBordereaux('purchasing_manager'))->get(route('economat.receipts.attachments.show', [$recu, $bordereau]))->assertOk();
    $this->actingAs(agentBordereaux('reception'))->get(route('economat.receipts.attachments.show', [$recu, $bordereau]))
        ->assertRedirect()->assertSessionHas('access_denied_popup', true);

    // Un fichier qui n'est ni PDF ni image est refusé.
    $this->actingAs($magasinier)->post(route('economat.receipts.attachments.store', $recu), [
        'bordereaux' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
    ])->assertSessionHasErrors('bordereaux.0');

    // Le magasinier ne le retire pas ; l'économe, oui — vers la corbeille.
    $this->actingAs($magasinier)->delete(route('economat.receipts.attachments.destroy', [$recu, $bordereau]))
        ->assertSessionHas('access_denied_popup', true);
    $this->actingAs($econome)->delete(route('economat.receipts.attachments.destroy', [$recu, $bordereau]))->assertSessionHas('success');

    expect(GoodsReceiptAttachment::count())->toBe(0);
    $entree = TrashEntry::where('table_name', 'goods_receipt_attachments')->sole();
    expect($entree->type)->toBe('Bordereau de livraison')
        ->and($entree->label)->toContain('BL-tardif.pdf')->toContain($recu->number);
    Storage::disk('local')->assertMissing($bordereau->path);
    Storage::disk('local')->assertExists("corbeille/{$entree->id}/{$bordereau->path}");

    // La console le rend au bon.
    config(['orchestration.secret' => 'jeton']);
    $this->postJson("/api/corbeille/{$entree->id}/restaurer", ['auteur' => 'Tech'], ['Authorization' => 'Bearer jeton'])->assertOk();
    expect($recu->attachments()->count())->toBe(1);
    Storage::disk('local')->assertExists($bordereau->path);
});
