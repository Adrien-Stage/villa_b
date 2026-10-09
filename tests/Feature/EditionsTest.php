<?php

use App\Editions\Catalogue;
use App\Editions\Edition;
use App\Editions\Registres;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\PointOfSale;
use App\Models\PurchaseOrder;
use App\Models\RestaurantCustomerOrder;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\ShopOrder;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\Supplier;
use App\Models\User;
use App\Support\PointOfSaleCatalog;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * La rubrique Éditions : tout ce qui s'imprime, en un seul endroit. Chaque
 * édition ne s'ouvre qu'à qui détient déjà le droit de ses données, et ses
 * totaux ne comptent chaque vente et chaque encaissement qu'une fois.
 */

beforeEach(function () {
    RoleCatalog::sync();
    PointOfSaleCatalog::sync();
    activerModules(['restaurant', 'shop', 'economat', 'accounting', 'analytics', 'housekeeping']);
    $this->travelTo(CarbonImmutable::parse('2026-10-09 15:00'));
    $this->restaurant = PointOfSale::where('slug', 'restaurant')->sole();
});

function membreAvec(string $role, string $nom = 'Agent'): User
{
    $user = User::factory()->create(['name' => $nom, 'is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Un séjour en cours : arrivé le 8, part le 11, 30 000 FCFA la nuit. */
function sejourAuJour(): Booking
{
    $type = RoomType::create(['name' => 'Deluxe', 'code' => 'DLX', 'base_capacity' => 2, 'max_capacity' => 2, 'base_price' => 3000000]);
    $chambre = Room::create(['number' => '204', 'room_type_id' => $type->id, 'floor' => 2, 'status' => 'occupied']);
    $client = Customer::create(['first_name' => 'Aïcha', 'last_name' => 'Mbarga', 'nationality' => 'Camerounaise', 'id_document_type' => 'id_card', 'id_document_number' => 'CNI-778']);

    return Booking::create([
        'booking_number' => 'BKG-ED-1', 'customer_id' => $client->id, 'room_id' => $chambre->id,
        'check_in' => '2026-10-08', 'check_out' => '2026-10-11', 'price_per_night' => 3000000,
        'total_room_amount' => 9000000, 'total_nights' => 3, 'total_amount' => 9000000,
        'paid_amount' => 5000000, 'balance_due' => 4000000, 'status' => 'checked_in', 'adults_count' => 2,
    ]);
}

/** Une journée d'activité : un séjour réglé en partie, un repas encaissé, un repas porté à la chambre, une vente boutique. */
function journeeActive($test): array
{
    $sejour = sejourAuJour();
    $caissier = membreAvec('cashier', 'Caissière');

    Payment::create(['booking_id' => $sejour->id, 'customer_id' => $sejour->customer_id, 'amount' => 5000000, 'method' => 'cash',
        'status' => 'completed', 'paid_at' => '2026-10-09 09:00', 'processed_by' => $caissier->id, 'reference' => 'PAY-1']);

    RestaurantCustomerOrder::create(['point_of_sale_id' => $test->restaurant->id, 'table_number' => '4', 'status' => 'served',
        'payment_status' => 'paid', 'payment_method' => 'cash', 'total_amount' => 1500000, 'amount_paid' => 1500000,
        'placed_at' => '2026-10-09 12:30', 'paid_at' => '2026-10-09 13:10', 'paid_by' => $caissier->id]);

    // Repas porté à la chambre : une vente du restaurant, pas un encaissement,
    // et pas une seconde vente au séjour.
    $ligneFolio = FolioItem::create(['booking_id' => $sejour->id, 'customer_id' => $sejour->customer_id, 'type' => FolioItem::TYPE_RESTAURANT,
        'description' => 'Dîner', 'quantity' => 1, 'unit_price' => 800000, 'total_price' => 800000, 'occurred_at' => '2026-10-09 20:00']);
    RestaurantCustomerOrder::create(['point_of_sale_id' => $test->restaurant->id, 'table_number' => '7', 'status' => 'served',
        'payment_status' => 'transferred_to_folio', 'payment_method' => 'room_charge', 'total_amount' => 800000, 'amount_paid' => 0,
        'booking_id' => $sejour->id, 'folio_item_id' => $ligneFolio->id, 'placed_at' => '2026-10-09 19:45']);

    ShopOrder::create(['order_number' => 'BTQ-1', 'total_items' => 1, 'subtotal' => 250000, 'tax_amount' => 0, 'total_amount' => 250000,
        'payment_status' => 'paid', 'payment_method' => 'mobile_money', 'paid_at' => '2026-10-09 11:00', 'created_by' => $caissier->id]);

    return ['sejour' => $sejour, 'caissier' => $caissier];
}

test("chaque vente est comptée une fois, là où elle naît ; chaque encaissement sur sa pièce", function () {
    journeeActive($this);
    $jour = CarbonImmutable::parse('2026-10-09');
    $registres = app(Registres::class);

    $ventes = $registres->ventes($jour, $jour);
    // Nuitée du 9 (30 000) + repas encaissé (15 000) + repas sur la chambre (8 000) + boutique (2 500).
    expect($ventes->sum('montant'))->toBe(3000000 + 1500000 + 800000 + 250000)
        ->and($ventes->where('reglement', 'Sur la chambre')->sum('montant'))->toBe(800000)
        ->and($ventes->where('service', 'Hébergement')->count())->toBe(1);

    $encaissements = $registres->encaissements($jour, $jour);
    // Le repas porté à la chambre n'est pas encaissé.
    expect($encaissements->sum('montant'))->toBe(5000000 + 1500000 + 250000)
        ->and($registres->encaissements($jour, $jour, Registres::SERVICE_BOUTIQUE)->sum('montant'))->toBe(250000)
        ->and($registres->encaissements($jour, $jour, '', 'cash')->sum('montant'))->toBe(6500000);
});

test("chaque édition s'affiche et s'imprime ; les exports passent sous leur droit", function () {
    journeeActive($this);
    Expense::create(['occurred_at' => '2026-10-09 10:00', 'category' => array_key_first(Expense::CATEGORIES), 'label' => 'Gaz', 'amount' => 120000, 'payment_method' => 'cash']);
    $fournisseur = Supplier::create(['name' => 'Société Alimentaire', 'code' => 'SAL']);
    PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_SENT, 'total_amount' => 450000]);
    StockRequisition::create(['department' => 'restaurant', 'point_of_sale_id' => $this->restaurant->id]);
    StockItem::create(['name' => 'Riz parfumé', 'unit' => 'kg', 'current_stock' => 3, 'min_stock' => 5, 'average_cost' => 80000]);

    $this->actingAs(membreAvec('admin', 'Administratrice'));
    $editions = app(Catalogue::class)->toutes();

    expect($editions->count())->toBeGreaterThanOrEqual(20)
        ->and($editions->map(fn (Edition $e) => $e->cle())->unique()->count())->toBe($editions->count());

    foreach ($editions as $edition) {
        $this->get(route('editions.show', $edition->cle()))->assertOk()->assertSee($edition->titre());
        $this->get(route('editions.print', $edition->cle()))->assertOk();
    }

    $this->get(route('editions.show', ['edition' => 'journal-encaissements', 'du' => '2026-10-09', 'au' => '2026-10-09']))
        ->assertSee('Caissière')->assertSee('BKG-ED-1')->assertSee('67 500 FCFA');
    $this->get(route('editions.show', ['edition' => 'etat-stocks']))->assertSee('Sous le seuil');
    $this->get(route('editions.show', ['edition' => 'registre-voyageurs', 'du' => '2026-10-08', 'au' => '2026-10-08']))->assertSee('CNI-778');

    $this->get(route('editions.export', ['edition' => 'journal-ventes', 'format' => 'excel']))->assertOk()
        ->assertHeader('content-disposition');
});

test("une édition ne s'ouvre qu'à qui détient déjà le droit de ses données", function () {
    $receptionniste = membreAvec('reception', 'Réceptionniste');
    $cles = app(Catalogue::class)->pour($receptionniste)->map(fn (Edition $e) => $e->cle())->all();

    expect($cles)->toContain('arrivees', 'departs', 'clients-presents')
        ->not->toContain('journal-encaissements', 'bons-commande', 'depenses');

    $this->actingAs($receptionniste);
    $this->get(route('editions.index'))->assertOk()->assertSee('Liste des arrivées')->assertDontSee('Journal des encaissements');
    $this->get(route('editions.show', 'journal-encaissements'))->assertForbidden();
    $this->get(route('editions.show', 'inconnue'))->assertNotFound();
    $this->get(route('dashboard'))->assertSee('href="' . route('editions.index') . '"', false);

    // Le comptable lit les journaux financiers.
    $comptable = membreAvec('accountant', 'Comptable');
    expect(app(Catalogue::class)->pour($comptable)->map(fn (Edition $e) => $e->cle())->all())
        ->toContain('journal-encaissements', 'journal-ventes', 'situation-journaliere', 'creances');
});

test('la situation journalière ventile les encaissements de chaque service par mode', function () {
    journeeActive($this);
    $this->actingAs(membreAvec('manager', 'Directrice'));

    $edition = app(Catalogue::class)->trouver('situation-journaliere');
    $user = auth()->user();
    $document = $edition->document($edition->valeurs(['jour' => '2026-10-09'], $user), $user);
    $parService = $document->lesLignes()->keyBy('service');

    expect($parService['Hébergement']['encaisse'])->toBe(5000000)
        ->and($parService['Hébergement']['especes'])->toBe(5000000)
        ->and($parService[$this->restaurant->name]['ventes'])->toBe(2300000)
        ->and($parService[$this->restaurant->name]['chambre'])->toBe(800000)
        ->and($parService['Boutique']['mobile'])->toBe(250000)
        ->and($document->laNote())->toContain('1 chambre(s) occupée(s)');
});

test('on retrouve une pièce par son numéro, si on peut l’ouvrir', function () {
    $fournisseur = Supplier::create(['name' => 'Société Alimentaire', 'code' => 'SAL']);
    $bon = PurchaseOrder::create(['supplier_id' => $fournisseur->id, 'status' => PurchaseOrder::STATUS_SENT, 'total_amount' => 450000]);
    $fragment = substr($bon->number, -4);

    $this->actingAs(membreAvec('econome', 'Économe'))
        ->get(route('editions.index', ['q' => $fragment]))
        ->assertOk()
        ->assertSee($bon->number)
        ->assertSee(route('economat.orders.print', $bon), false);

    $this->actingAs(membreAvec('housekeeping_staff', 'Valet'))
        ->get(route('editions.index', ['q' => $fragment]))
        ->assertOk()
        ->assertDontSee($bon->number);
});
