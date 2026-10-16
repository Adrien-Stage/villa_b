<?php

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\FolioItem;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingLog;
use App\Models\HousekeepingTeam;
use App\Models\JournalEntry;
use App\Models\MinibarArticle;
use App\Models\MinibarCheck;
use App\Models\MinibarMovement;
use App\Models\MinibarRestock;
use App\Models\Room;
use App\Models\RoomMinibarLine;
use App\Models\ServiceStore;
use App\Models\ServiceStoreStock;
use App\Models\StockItem;
use App\Models\User;
use App\Services\CheckOutService;
use App\Services\LedgerPostingService;
use App\Services\ServiceStoreService;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Mini-bars : chaque chambre a sa dotation (2 Coca, 1 chips), prise au dépôt
 * du mini-bar. L'équipe relève les consommations — à la recouche, au départ
 * demandé par la réception, ou au ménage qui suit un départ sans relevé — et
 * ce qui est consommé part au folio du client. Pendant le séjour, le mini-bar
 * ne se recharge que vide ; après le départ, la gouvernante le remet à niveau
 * avant de contrôler la chambre.
 */

beforeEach(function () {
    $this->seed([
        \Database\Seeders\TenantSeeder::class,
        \Database\Seeders\RoomTypeSeeder::class,
        \Database\Seeders\RoomSeeder::class,
    ]);
    RoleCatalog::sync();
    activerModules(['housekeeping', 'economat']);
    $this->travelTo(CarbonImmutable::parse('2026-10-15 08:00'));

    $this->responsable = mbAgent('housekeeping_leader', 'Paul Responsable');
    $this->gouvernante = mbAgent('housekeeping_supervisor', 'Awa Gouvernante');
    $this->valet = mbAgent('housekeeping_staff', 'Marie Valet');
    $this->reception = mbAgent('reception', 'Joël Réception');

    $this->equipe = HousekeepingTeam::create(['name' => 'Étage 1', 'leader_id' => $this->gouvernante->id, 'is_active' => true]);
    $this->equipe->members()->sync([$this->gouvernante->id, $this->valet->id]);

    // Le dépôt du mini-bar, alimenté par l'économat : 20 Coca à 400 F, 10 chips à 300 F.
    $this->coca = StockItem::create(['name' => 'Coca-Cola 33 cl', 'unit' => 'bouteille', 'is_active' => true]);
    $this->chips = StockItem::create(['name' => 'Chips 45 g', 'unit' => 'sachet', 'is_active' => true]);
    $this->depot = ServiceStore::create(['name' => 'Mini-bar', 'department' => 'housekeeping', 'is_active' => true]);
    app(ServiceStoreService::class)->receive($this->depot, $this->coca, 20, 40000, 'requisition');
    app(ServiceStoreService::class)->receive($this->depot, $this->chips, 10, 30000, 'requisition');

    $this->room = Room::where('number', '101')->first();

    // Le responsable règle le mini-bar : dépôt, articles et prix, dotation du type.
    $this->actingAs($this->responsable);
    $this->post(route('housekeeping.minibar.depot'), ['service_store_id' => $this->depot->id])->assertSessionHas('success');
    $this->post(route('housekeeping.minibar.articles.store'), ['stock_item_id' => $this->coca->id, 'name' => 'Coca-Cola', 'price' => 1500])->assertSessionHas('success');
    $this->post(route('housekeeping.minibar.articles.store'), ['stock_item_id' => $this->chips->id, 'name' => 'Chips', 'price' => 1000])->assertSessionHas('success');
    $this->aCoca = MinibarArticle::where('stock_item_id', $this->coca->id)->sole();
    $this->aChips = MinibarArticle::where('stock_item_id', $this->chips->id)->sole();
    $this->put(route('housekeeping.minibar.standards.update', $this->room->room_type_id), [
        'quantites' => [$this->aCoca->id => 2, $this->aChips->id => 1],
    ])->assertSessionHas('success');

    // Mise en place : la gouvernante remplit le mini-bar de la chambre libre.
    $this->actingAs($this->gouvernante)->post(route('housekeeping.minibar.restock', $this->room))->assertSessionHas('success');
});

function mbAgent(string $role, string $nom): User
{
    return User::factory()->create(['role' => $role, 'name' => $nom, 'is_active' => true]);
}

function mbSejour(Room $room, string $numero = 'VB-2026-000950'): Booking
{
    $room->update(['status' => RoomStatus::OCCUPIED]);

    return Booking::create([
        'customer_id' => Customer::factory()->create()->id, 'room_id' => $room->id, 'booking_number' => $numero,
        'status' => BookingStatus::CHECKED_IN, 'check_in' => now()->subDay()->toDateString(), 'check_out' => now()->addDay()->toDateString(),
        'actual_check_in' => now()->subDay(), 'adults_count' => 1, 'total_nights' => 2, 'price_per_night' => 4500000,
        'total_room_amount' => 9000000, 'total_amount' => 9000000, 'paid_amount' => 9000000, 'balance_due' => 0, 'source' => 'direct',
    ]);
}

function mbLigne(Room $room, MinibarArticle $article): RoomMinibarLine
{
    return RoomMinibarLine::where('room_id', $room->id)->where('minibar_article_id', $article->id)->sole();
}

test('la mise en place remplit le mini-bar depuis le dépôt, sans charge', function () {
    expect(mbLigne($this->room, $this->aCoca)->quantity)->toBe(2)
        ->and(mbLigne($this->room, $this->aCoca)->unit_cost)->toBe(40000)
        ->and(mbLigne($this->room, $this->aChips)->quantity)->toBe(1)
        ->and((float) ServiceStoreStock::where('stock_item_id', $this->coca->id)->value('current_stock'))->toBe(18.0)
        ->and(MinibarRestock::sole()->reason)->toBe(MinibarRestock::REASON_MISE_EN_PLACE)
        ->and(MinibarRestock::sole()->total_cost)->toBe(2 * 40000 + 30000)
        ->and($this->room->fresh()->miniBarComplet())->toBeTrue();

    // Déjà complet : rien à recharger.
    $this->actingAs($this->gouvernante)->post(route('housekeeping.minibar.restock', $this->room))->assertSessionHas('error');
});

test('à la recouche, le relevé est obligatoire et part au folio du client', function () {
    $sejour = mbSejour($this->room);
    $recouche = HousekeepingAssignment::create(['housekeeping_team_id' => $this->equipe->id, 'room_id' => $this->room->id,
        'kind' => HousekeepingAssignment::KIND_RECOUCHE, 'status' => 'in_progress', 'assigned_at' => now(), 'started_at' => now()]);

    $this->actingAs($this->valet);
    $this->get(route('housekeeping.index'))->assertSee('Relevé du mini-bar');
    $this->post(route('housekeeping.stayover', $this->room))->assertSessionHasErrors('status');

    // Chaque article doit être indiqué, 0 compris.
    $this->post(route('housekeeping.minibar.check', $this->room), ['consommes' => [$this->aCoca->id => 2]])->assertSessionHas('error');
    // Pas plus que ce que contient le mini-bar.
    $this->post(route('housekeeping.minibar.check', $this->room), ['consommes' => [$this->aCoca->id => 3, $this->aChips->id => 0]])->assertSessionHas('error');
    expect(MinibarCheck::count())->toBe(0);

    // L'équipe ne voit pas les prix.
    $this->get(route('housekeeping.minibar.rooms.show', $this->room))->assertOk()->assertSee('Relevé des consommations')->assertDontSee('1 500 F');

    $this->post(route('housekeeping.minibar.check', $this->room), ['consommes' => [$this->aCoca->id => 2, $this->aChips->id => 0]])
        ->assertSessionHas('success');

    $releve = MinibarCheck::sole();
    expect($releve->occasion)->toBe(MinibarCheck::OCCASION_SEJOUR)
        ->and($releve->billing)->toBe(MinibarCheck::BILLING_FOLIO)
        ->and($releve->total_amount)->toBe(300000)
        ->and($releve->housekeeping_assignment_id)->toBe($recouche->id)
        ->and(mbLigne($this->room, $this->aCoca)->quantity)->toBe(0)
        ->and(FolioItem::where('booking_id', $sejour->id)->where('type', FolioItem::TYPE_MINIBAR)->sole()->total_price)->toBe(300000)
        ->and($sejour->fresh()->extras_amount)->toBe(300000)
        ->and(MinibarMovement::where('type', MinibarMovement::TYPE_CONSUMPTION)->sole()->quantity)->toBe(-2);

    // Le relevé fait, la recouche se termine.
    $this->post(route('housekeeping.stayover', $this->room))->assertSessionHasNoErrors();
    expect($recouche->fresh()->status)->toBe('completed');

    // Il reste des chips : pas de rechargement pendant le séjour.
    $this->actingAs($this->gouvernante)->post(route('housekeeping.minibar.restock', $this->room))->assertSessionHas('error');
    expect(mbLigne($this->room, $this->aCoca)->quantity)->toBe(0);

    expect($this->reception->notifications()->get()->pluck('data.title'))->toContain('Relevé du mini-bar');
});

test('pendant le séjour, un mini-bar vidé alerte la gouvernante et se recharge', function () {
    mbSejour($this->room);

    $this->actingAs($this->valet)->post(route('housekeeping.minibar.check', $this->room), [
        'consommes' => [$this->aCoca->id => 2, $this->aChips->id => 1],
    ])->assertSessionHas('success');

    expect($this->room->fresh()->miniBarVide())->toBeTrue()
        ->and($this->gouvernante->notifications()->get()->pluck('data.title'))->toContain('Mini-bar vide');

    $this->actingAs($this->gouvernante)->post(route('housekeeping.minibar.restock', $this->room))->assertSessionHas('success');

    expect(MinibarRestock::latest('id')->first()->reason)->toBe(MinibarRestock::REASON_VIDE)
        ->and($this->room->fresh()->miniBarComplet())->toBeTrue()
        ->and((float) ServiceStoreStock::where('stock_item_id', $this->coca->id)->value('current_stock'))->toBe(16.0);
});

test('au départ, la réception demande le relevé avant de clore la note', function () {
    $sejour = mbSejour($this->room);

    $this->actingAs($this->reception)->post(route('bookings.minibar.request', $sejour))->assertSessionHas('success');
    expect($sejour->fresh()->minibar_check_requested_at)->not->toBeNull()
        ->and($this->gouvernante->notifications()->get()->pluck('data.title'))->toContain('Contrôle du mini-bar demandé');

    $this->actingAs($this->valet)->get(route('housekeeping.index'))->assertSee('la réception attend le relevé');
    $this->post(route('housekeeping.minibar.check', $this->room), ['consommes' => [$this->aCoca->id => 1, $this->aChips->id => 0]])
        ->assertSessionHas('success');

    $releve = MinibarCheck::sole();
    expect($releve->occasion)->toBe(MinibarCheck::OCCASION_DEPART)
        ->and($releve->billing)->toBe(MinibarCheck::BILLING_FOLIO);

    $this->actingAs($this->reception)->get(route('bookings.show', $sejour))->assertOk()->assertSee('Relevé de départ fait');

    // La note se clôt : la consommation y est, rien ne reste dû au ménage.
    $sejour->refresh()->update(['balance_due' => 0]);
    $facture = app(CheckOutService::class)->process($sejour->fresh());
    expect($facture->items->where('category', FolioItem::TYPE_MINIBAR))->toHaveCount(1)
        ->and($this->room->fresh()->status)->toBe(RoomStatus::DIRTY)
        ->and($this->room->fresh()->minibar_due_booking_id)->toBeNull();

    // Ménage : la chambre se nettoie, puis la gouvernante recharge avant de contrôler.
    HousekeepingAssignment::create(['housekeeping_team_id' => $this->equipe->id, 'room_id' => $this->room->id,
        'status' => 'pending', 'assigned_at' => now()]);
    $this->actingAs($this->valet)->post(route('housekeeping.clean', $this->room))->assertSessionHasNoErrors();
    $this->actingAs($this->valet)->post(route('housekeeping.ready', $this->room))->assertSessionHasNoErrors();

    $this->actingAs($this->gouvernante)->post(route('housekeeping.inspect', $this->room))->assertSessionHasErrors('status');
    $this->actingAs($this->gouvernante)->post(route('housekeeping.minibar.restock', $this->room))->assertSessionHas('success');
    expect(MinibarRestock::latest('id')->first()->reason)->toBe(MinibarRestock::REASON_DEPART);
    $this->actingAs($this->gouvernante)->post(route('housekeeping.inspect', $this->room))->assertSessionHasNoErrors();
    expect($this->room->fresh()->status)->toBe(RoomStatus::INSPECTED);
});

test('client parti sans relevé : le relevé est dû avant de marquer la chambre nettoyée, et reste à facturer', function () {
    $sejour = mbSejour($this->room);
    app(CheckOutService::class)->process($sejour->fresh());

    expect($this->room->fresh()->minibar_due_booking_id)->toBe($sejour->id);

    HousekeepingAssignment::create(['housekeeping_team_id' => $this->equipe->id, 'room_id' => $this->room->id,
        'status' => 'pending', 'assigned_at' => now()]);
    $this->actingAs($this->valet)->post(route('housekeeping.clean', $this->room))->assertSessionHasNoErrors();
    $this->actingAs($this->valet)->post(route('housekeeping.ready', $this->room))->assertSessionHasErrors('status');
    expect($this->room->fresh()->status)->toBe(RoomStatus::CLEANING);

    $this->actingAs($this->valet)->post(route('housekeeping.minibar.check', $this->room), [
        'consommes' => [$this->aCoca->id => 1, $this->aChips->id => 1],
    ])->assertSessionHas('success');

    $releve = MinibarCheck::sole();
    expect($releve->occasion)->toBe(MinibarCheck::OCCASION_APRES_DEPART)
        ->and($releve->billing)->toBe(MinibarCheck::BILLING_A_FACTURER)
        ->and($releve->booking_id)->toBe($sejour->id)
        ->and($releve->total_amount)->toBe(250000)
        ->and(FolioItem::where('type', FolioItem::TYPE_MINIBAR)->count())->toBe(0)
        ->and($this->room->fresh()->minibar_due_booking_id)->toBeNull()
        ->and($this->reception->notifications()->first()?->data['message'])->toContain('facture complémentaire');

    $this->actingAs($this->valet)->post(route('housekeeping.ready', $this->room))->assertSessionHasNoErrors();
    $this->actingAs($this->reception)->get(route('bookings.show', $sejour))->assertSee('facture complémentaire');
});

test('un dépôt en rupture laisse contrôler la chambre sur motif', function () {
    $sejour = mbSejour($this->room);
    $this->actingAs($this->valet)->post(route('housekeeping.minibar.check', $this->room), [
        'consommes' => [$this->aCoca->id => 0, $this->aChips->id => 1],
    ]);
    $sejour->refresh()->update(['balance_due' => 0]);
    app(CheckOutService::class)->process($sejour->fresh());
    $this->actingAs($this->valet)->post(route('housekeeping.minibar.check', $this->room), [
        'consommes' => [$this->aCoca->id => 0, $this->aChips->id => 0],
    ])->assertSessionHas('success');

    // Plus de chips au dépôt.
    ServiceStoreStock::where('stock_item_id', $this->chips->id)->update(['current_stock' => 0]);
    $this->room->update(['status' => RoomStatus::CLEAN]);

    $this->actingAs($this->gouvernante)->post(route('housekeeping.minibar.restock', $this->room))->assertSessionHas('error');
    $this->actingAs($this->gouvernante)->post(route('housekeeping.inspect', $this->room), ['motif_minibar' => 'Rupture de chips au dépôt'])
        ->assertSessionHasNoErrors();

    expect($this->room->fresh()->status)->toBe(RoomStatus::INSPECTED)
        ->and(HousekeepingLog::where('action', HousekeepingLog::INSPECTED)->sole()->details)->toBe('Mini-bar incomplet : Rupture de chips au dépôt');
});

test('le grand livre porte la vente au client et la consommation du stock', function () {
    mbSejour($this->room);
    $this->actingAs($this->valet)->post(route('housekeeping.minibar.check', $this->room), [
        'consommes' => [$this->aCoca->id => 2, $this->aChips->id => 0],
    ]);

    app(LedgerPostingService::class)->postDay(\Illuminate\Support\Carbon::parse('2026-10-15'));

    $vente = JournalEntry::query()->where('schema', LedgerPostingService::SCHEMA_MINIBAR_SALE)->with('lines')->sole();
    expect($vente->lines->sum('debit'))->toBe(300000)
        ->and($vente->lines->sum('credit'))->toBe(300000);

    $cout = JournalEntry::query()->where('schema', 'like', LedgerPostingService::SCHEMA_MINIBAR_STOCK . '%')->with('lines')->sole();
    expect($cout->lines->sum('debit'))->toBe(2 * 40000)
        ->and($cout->lines->pluck('label')->unique()->values()->all())->toBe(['Consommations des mini-bars']);
});

test("les réglages du mini-bar sont au responsable ; l'équipe relève, la gouvernante recharge", function () {
    $this->actingAs($this->valet)->get(route('housekeeping.minibar.index'))->assertRedirect();
    $this->actingAs($this->valet)->post(route('housekeeping.minibar.restock', $this->room))->assertSessionHas('access_denied_popup', true);
    // Le valet n'a pas de réglage : Paramètres le mène à son profil.
    $this->actingAs($this->valet)->get(route('settings.index'))->assertRedirect(route('profile.edit'));

    // La gouvernante suit l'état des mini-bars et consulte leurs réglages dans Paramètres › Housekeeping.
    $this->actingAs($this->gouvernante)->get(route('housekeeping.minibar.index'))->assertOk()
        ->assertSee('101')->assertSee('Réglages du mini-bar');
    $this->actingAs($this->gouvernante)->get(route('settings.index'))->assertOk()
        ->assertSee('Mini-bars')->assertSee('Coca-Cola')->assertSee('Vous consultez ces réglages')
        ->assertDontSee('Ajouter un article');
    $this->actingAs($this->gouvernante)->put(route('housekeeping.minibar.articles.update', $this->aCoca), ['name' => 'Coca', 'price' => 1])
        ->assertSessionHas('access_denied_popup', true);

    $this->actingAs($this->responsable)->get(route('settings.index', ['tab' => 'housekeeping']))->assertOk()
        ->assertSee('Ajouter un article')->assertDontSee('Vous consultez ces réglages');
});

test('consommé après le départ : une facture complémentaire, encaissée plus tard à la réception', function () {
    $sejour = mbSejour($this->room);
    $factureSejour = app(CheckOutService::class)->process($sejour->fresh());

    $this->actingAs($this->valet)->post(route('housekeeping.minibar.check', $this->room), [
        'consommes' => [$this->aCoca->id => 1, $this->aChips->id => 1],
    ])->assertSessionHas('success');

    $complement = \App\Models\Invoice::where('kind', \App\Models\Invoice::KIND_COMPLEMENTAIRE)->with('items')->sole();
    expect($complement->booking_id)->toBe($sejour->id)
        ->and($complement->total_amount)->toBe(250000)
        ->and($complement->balance_due)->toBe(250000)
        ->and($complement->invoice_number)->not->toBe($factureSejour->invoice_number)
        ->and($complement->items->pluck('description')->all())->toBe(['Mini-bar — Chips', 'Mini-bar — Coca-Cola'])
        ->and(MinibarCheck::sole()->invoice_id)->toBe($complement->id)
        ->and($sejour->fresh()->invoice->id)->toBe($factureSejour->id)
        ->and($this->reception->notifications()->first()?->data['message'])->toContain($complement->invoice_number);

    // Comptabilisée une fois, au relevé : pas comme un hébergement.
    app(LedgerPostingService::class)->postDay(\Illuminate\Support\Carbon::parse('2026-10-15'));
    expect(JournalEntry::where('schema', LedgerPostingService::SCHEMA_MINIBAR_SALE)->with('lines')->sole()->lines->sum('debit'))->toBe(250000)
        ->and(JournalEntry::where('schema', LedgerPostingService::SCHEMA_INVOICE)->where('source_id', $complement->id)->exists())->toBeFalse();

    // La réception encaisse, caisse ouverte.
    \App\Models\CashRegisterSession::create(['user_id' => $this->reception->id, 'module' => 'reception', 'opening_amount' => 0, 'opened_at' => now()]);
    $this->actingAs($this->reception);
    $this->get(route('invoices.show', $complement))->assertOk()->assertSee('Facture complémentaire')->assertSee('Encaisser');
    $this->get(route('bookings.show', $sejour))->assertSee($complement->invoice_number)->assertSee('reste 2 500 FCFA à encaisser');

    $this->post(route('invoices.payments.store', $complement), ['amount' => 3000, 'method' => 'cash'])->assertSessionHasErrors('payment');
    $this->post(route('invoices.payments.store', $complement), ['amount' => 1000, 'method' => 'cash'])->assertSessionHasNoErrors();
    $this->post(route('invoices.payments.store', $complement), ['amount' => 1500, 'method' => 'orange_money'])->assertSessionHasNoErrors();

    $complement->refresh();
    expect($complement->balance_due)->toBe(0)
        ->and($complement->status)->toBe('paid')
        ->and(\App\Models\Payment::where('invoice_id', $complement->id)->sum('amount'))->toBe(250000);

    // Soldée : plus rien à encaisser ; la facture du séjour ne s'encaisse pas ici.
    $this->post(route('invoices.payments.store', $complement), ['amount' => 100, 'method' => 'cash'])->assertSessionHasErrors('payment');
    $this->post(route('invoices.payments.store', $factureSejour), ['amount' => 100, 'method' => 'cash'])->assertSessionHasErrors('payment');
});
