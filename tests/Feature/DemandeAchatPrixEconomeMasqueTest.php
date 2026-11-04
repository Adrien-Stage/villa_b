<?php

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\Supplier;
use App\Models\User;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules(['economat']);
});

function creerAgentAchat(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

test("l'économe ne voit ni les prix ni les montants sur le récapitulatif d'une demande d'achat", function () {
    $econome = creerAgentAchat('econome');
    $fournisseur = Supplier::create(['name' => 'Fournisseur Test', 'is_active' => true]);
    $ananas = StockItem::create([
        'name' => 'Ananas',
        'unit' => 'pièce',
        'short_unit' => 'pcs',
        'is_active' => true,
        'last_purchase_price' => 50000, // 500 F
    ]);

    $demande = PurchaseRequest::create([
        'number' => 'DA-2026-0006',
        'department' => 'economat',
        'priority' => 'normal',
        'status' => 'pending',
        'requested_by' => $econome->id,
        'total_estimated_amount' => 500000, // 5 000 F
    ]);

    PurchaseRequestLine::create([
        'purchase_request_id' => $demande->id,
        'stock_item_id' => $ananas->id,
        'quantity_requested' => 10,
        'estimated_unit_price' => 50000,
    ]);

    // 1. Sur la page récapitulatif (show)
    $response = $this->actingAs($econome)->get(route('economat.purchase_requests.show', $demande));
    $response->assertOk();
    $response->assertSee('Articles demandés');
    $response->assertSee('Ananas');
    $response->assertSee('pcs');
    $response->assertSee('Quantité demandée');
    $response->assertDontSee('Montant estimé');
    $response->assertDontSee('Prix du fournisseur');
    $response->assertDontSee('Total estimé');

    // 2. Sur l'impression
    $print = $this->actingAs($econome)->get(route('economat.purchase_requests.print', $demande));
    $print->assertOk();
    $print->assertSee('Ananas');
    $print->assertSee('pcs');
    $print->assertDontSee('P.U. estimé');
    $print->assertDontSee('Total estimé');

    // 3. Sur la liste des demandes (index)
    $index = $this->actingAs($econome)->get(route('economat.purchase_requests.index'));
    $index->assertOk();
    $index->assertDontSee('Montant est.');

    // 4. En revanche, le responsable des achats voit les montants et les prix
    $acheteur = creerAgentAchat('purchasing_manager');
    $responseAcheteur = $this->actingAs($acheteur)->get(route('economat.purchase_requests.show', $demande));
    $responseAcheteur->assertOk();
    $responseAcheteur->assertSee('Montant estimé');
    $responseAcheteur->assertSee('Prix du fournisseur');
    $responseAcheteur->assertSee('Total estimé');
});
