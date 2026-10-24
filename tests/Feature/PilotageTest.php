<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BudgetLine;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ManagementAlert;
use App\Models\Payment;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuCategory;
use App\Models\RestaurantMenuItem;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Notifications\AlerteDeGestion;
use App\Services\AlertesDeGestion;
use App\Services\BudgetDeGestion;
use App\Services\TableauDeBordGestion;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

/*
 * Pilotage, l'outil du contrôle de gestion : le budget de l'année face à son
 * réalisé, le tableau de bord des indicateurs hôteliers, et les alertes de
 * dérive du matin. Le contrôleur et le responsable financier l'établissent,
 * la direction le consulte.
 */

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'restaurant', 'accounting', 'comptabilite']);
    $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00'));
});

function membrePilotage(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Une grille de budget en FCFA, un seul montant chaque mois pour un centre et une nature. */
function budgetMensuel(string $centre, string $nature, int $fcfa): array
{
    $saisie = [];
    foreach (BudgetLine::centres() as $c => $libelle) {
        foreach (array_keys(BudgetLine::NATURES) as $n) {
            $saisie[$c][$n] = array_fill(1, 12, ($c === $centre && $n === $nature) ? $fcfa : 0);
        }
    }

    return $saisie;
}

test('le contrôleur établit le budget ; la direction le consulte ; la réception ne le voit pas', function () {
    $this->actingAs(membrePilotage('controller'));
    $this->get(route('pilotage.budget.index', ['annee' => 2026]))->assertOk()
        ->assertSee('Budget 2026')->assertSee('Enregistrer le budget 2026');
    $this->put(route('pilotage.budget.update', 2026), ['budget' => budgetMensuel('hebergement', 'revenue', 1_000_000)])
        ->assertRedirect(route('pilotage.budget.index', ['annee' => 2026]))->assertSessionHasNoErrors();
    expect(BudgetLine::where(['year' => 2026, 'center' => 'hebergement', 'nature' => 'revenue'])->sole()->m10)->toBe(100_000_000);

    $this->actingAs(membrePilotage('finance_manager'))->get(route('pilotage.index'))->assertOk();

    $this->actingAs(membrePilotage('manager'));
    $this->get(route('pilotage.budget.index', ['annee' => 2026]))->assertOk()->assertDontSee('Enregistrer le budget');
    $this->put(route('pilotage.budget.update', 2026), ['budget' => budgetMensuel('hebergement', 'revenue', 1)])->assertSessionHas('access_denied_popup', true);
    $this->put(route('pilotage.seuils.update'), ['tolerance_budget' => 5, 'seuil_cout_matiere' => 30, 'baisse_marge' => 3])->assertSessionHas('access_denied_popup', true);

    $this->actingAs(membrePilotage('reception'))->get(route('pilotage.index'))->assertSessionHas('access_denied_popup', true);
});

test("l'écart se lit sur les mois écoulés, dans le sens de l'intérêt de l'hôtel", function () {
    $budget = app(BudgetDeGestion::class);
    $budget->enregistrer(2026, budgetMensuel('hebergement', 'revenue', 1_000_000), membrePilotage('controller'));

    // Octobre : 800 000 F encaissés ; les neuf mois précédents, rien.
    Payment::create(['amount' => 80_000_000, 'currency' => 'XAF', 'method' => 'cash', 'status' => 'completed', 'reference' => 'PAY-T1', 'paid_at' => '2026-10-05 10:00']);
    Expense::create(['occurred_at' => '2026-10-06 10:00', 'category' => 'maintenance', 'label' => 'Climatisation', 'amount' => 20_000_000, 'payment_method' => 'cash']);

    $grille = $budget->grille(2026);
    $hebergement = collect($grille['centres'])->firstWhere('centre', 'hebergement')['lignes']['revenue'];
    $structure = collect($grille['centres'])->firstWhere('centre', 'structure')['lignes']['cost'];

    expect($grille['source'])->toBe('caisse')
        ->and($grille['mois_ecoules'])->toBe(10)
        ->and($hebergement['realise'][10])->toBe(80_000_000)
        ->and($hebergement['budget_au_jour'])->toBe(10 * 100_000_000)
        ->and($hebergement['ecart']['favorable'])->toBeFalse()
        ->and($hebergement['ecart']['pct'])->toBe(-92.0)
        ->and($structure['realise'][10])->toBe(20_000_000)
        ->and($grille['resultat']['realise'][10])->toBe(60_000_000);

    $this->actingAs(membrePilotage('controller'))->get(route('pilotage.budget.index', ['annee' => 2026]))->assertOk()
        ->assertSee('comptabilité de caisse')->assertSee('800 000');

    // Partir du réalisé de l'année précédente : une proposition, rien d'enregistré.
    $this->get(route('pilotage.budget.index', ['annee' => 2027, 'depuis' => 'realise']))->assertOk()->assertSee('La grille propose le réalisé de 2026');
    expect(BudgetLine::where('year', 2027)->count())->toBe(0);
});

test("le tableau de bord donne l'occupation, le prix moyen, le RevPAR, les canaux et le revenu par couvert", function () {
    $chambres = Room::query()->sellable()->count();
    $room = Room::query()->sellable()->first();
    $client = Customer::factory()->create();
    // 3 nuits à 40 000 F du 10 au 13 octobre, réservées par téléphone ; une arrivée annulée.
    Booking::create(['booking_number' => 'BKG-PIL-1', 'room_id' => $room->id, 'customer_id' => $client->id, 'status' => BookingStatus::CHECKED_OUT, 'source' => 'phone',
        'check_in' => '2026-10-10', 'check_out' => '2026-10-13', 'total_nights' => 3, 'price_per_night' => 4_000_000, 'adults_count' => 2,
        'total_room_amount' => 12_000_000, 'total_amount' => 12_000_000, 'paid_amount' => 12_000_000, 'balance_due' => 0]);
    Booking::create(['booking_number' => 'BKG-PIL-2', 'room_id' => $room->id, 'customer_id' => $client->id, 'status' => BookingStatus::CANCELLED, 'source' => 'website',
        'check_in' => '2026-10-14', 'check_out' => '2026-10-15', 'total_nights' => 1, 'price_per_night' => 4_000_000, 'adults_count' => 2,
        'total_room_amount' => 4_000_000, 'total_amount' => 4_000_000, 'paid_amount' => 0, 'balance_due' => 0]);

    $categorie = RestaurantMenuCategory::firstOrCreate(['name' => 'Plats pilotage'], ['is_active' => true]);
    RestaurantCustomerOrder::create(['source' => 'staff', 'table_number' => '4', 'covers' => 4, 'status' => 'served', 'payment_status' => 'paid',
        'payment_method' => 'cash', 'total_amount' => 4_000_000, 'amount_paid' => 4_000_000, 'placed_at' => '2026-10-12 13:00', 'paid_at' => '2026-10-12 14:00']);

    $tableau = app(TableauDeBordGestion::class)->pour(CarbonImmutable::parse('2026-10-01'));
    $h = $tableau['hebergement'];

    expect($h['vendues'])->toBe(3)
        ->and($h['disponibles'])->toBe($chambres * 15)
        ->and($h['revenu'])->toBe(12_000_000)
        ->and($h['adr'])->toBe(4_000_000)
        ->and($h['revpar'])->toBe(intdiv(12_000_000, $chambres * 15))
        ->and($h['canaux'])->toBe(['Téléphone' => 12_000_000])
        ->and($h['arrivees'])->toBe(2)
        ->and($h['taux_annulation'])->toBe(50.0)
        ->and($tableau['restaurant']['couverts'])->toBe(4)
        ->and($tableau['restaurant']['revenu_par_couvert'])->toBe(1_000_000);

    $this->actingAs(membrePilotage('controller'))->get(route('pilotage.index'))->assertOk()
        ->assertSee('Tableau de bord de gestion')->assertSee('RevPAR')->assertSee('Revenu par couvert')->assertSee('10 000 F')
        ->assertSee('Pas de budget pour 2026');
});

test('la commande en salle garde son nombre de couverts', function () {
    $serveur = membrePilotage('restaurant_staff');
    $categorie = RestaurantMenuCategory::firstOrCreate(['name' => 'Plats couverts'], ['is_active' => true]);
    $plat = RestaurantMenuItem::create(['restaurant_menu_category_id' => $categorie->id, 'name' => 'Ndolé', 'price' => 300_000, 'type' => 'food', 'is_active' => true]);

    $this->actingAs($serveur)->post(route('restaurant.orders.store'), [
        'table_number' => '7', 'covers' => 3, 'items_json' => json_encode([['id' => $plat->id, 'qty' => 3]]),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(RestaurantCustomerOrder::sole()->covers)->toBe(3);
});

test('une dérive du budget est signalée une seule fois dans le mois', function () {
    Notification::fake();
    $controleur = membrePilotage('controller');
    $financier = membrePilotage('finance_manager');
    $receptionniste = membrePilotage('reception');
    // Structure : 100 000 F de charges prévues par mois ; 200 000 F dépensés au 15.
    app(BudgetDeGestion::class)->enregistrer(2026, budgetMensuel('structure', 'cost', 100_000), $controleur);
    Expense::create(['occurred_at' => '2026-10-03 10:00', 'category' => 'maintenance', 'label' => 'Groupe électrogène', 'amount' => 20_000_000, 'payment_method' => 'cash']);

    $nouvelles = app(AlertesDeGestion::class)->constater(CarbonImmutable::parse('2026-10-15'));

    expect($nouvelles)->toHaveCount(1)
        ->and($nouvelles[0]->rule)->toBe(ManagementAlert::RULE_COST_OVERRUN)
        ->and($nouvelles[0]->center)->toBe('structure')
        ->and($nouvelles[0]->message)->toContain('Structure');
    Notification::assertSentTo([$controleur, $financier], AlerteDeGestion::class);
    Notification::assertNotSentTo($receptionniste, AlerteDeGestion::class);

    // Le lendemain : la même dérive, mise à jour sans nouvelle notification.
    expect(app(AlertesDeGestion::class)->constater(CarbonImmutable::parse('2026-10-16')))->toBe([]);
    Notification::assertSentToTimes($controleur, AlerteDeGestion::class, 1);
    expect(ManagementAlert::count())->toBe(1);

    // Avant le 8 du mois, le prorata est trop instable pour alerter.
    expect(app(AlertesDeGestion::class)->constater(CarbonImmutable::parse('2026-11-03')))->toBe([]);

    $this->actingAs($controleur)->get(route('pilotage.index'))->assertOk()->assertSee('Charges au-dessus du budget');
    $this->artisan('pilotage:alertes')->assertSuccessful();
});

test('les seuils d\'alerte se règlent sur le tableau de bord', function () {
    $this->actingAs(membrePilotage('controller'));

    $this->put(route('pilotage.seuils.update'), ['tolerance_budget' => 5, 'seuil_cout_matiere' => 32, 'baisse_marge' => 4, 'mois' => '2026-10'])
        ->assertRedirect(route('pilotage.index', ['mois' => '2026-10']));

    expect(TableauDeBordGestion::seuils())->toBe(['tolerance_budget' => 5, 'seuil_cout_matiere' => 32, 'baisse_marge' => 4]);
});

test('la tuile Pilotage est celle du contrôle de gestion, du financier et de la direction', function () {
    foreach (['controller', 'finance_manager', 'manager'] as $role) {
        $tuiles = collect(\App\Support\Navigation::getLauncherGroups(membrePilotage($role)))->pluck('id');
        expect($tuiles)->toContain('pilotage');
    }
    foreach (['reception', 'econome', 'accountant'] as $role) {
        expect(collect(\App\Support\Navigation::getLauncherGroups(membrePilotage($role)))->pluck('id'))->not->toContain('pilotage');
    }
});
