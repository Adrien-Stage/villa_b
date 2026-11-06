<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BudgetLine;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ForecastLine;
use App\Models\Payment;
use App\Models\RestaurantCustomerOrder;
use App\Models\RestaurantMenuCategory;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Services\BudgetDeGestion;
use App\Services\PrevisionDeGestion;
use App\Services\TableauDeBordGestion;
use App\Support\RoleCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    RoleCatalog::sync();
    activerModules(['hebergement', 'reservations', 'clients', 'restaurant', 'accounting', 'comptabilite']);
    $this->travelTo(CarbonImmutable::parse('2026-10-15 09:00:00'));
});

function creerMembreRole(string $role): User
{
    $user = User::factory()->create(['is_active' => true]);
    $user->roles()->sync(Role::where('slug', $role)->pluck('id'));

    return $user->fresh();
}

/** Grille de forecast en FCFA */
function forecastMensuel(string $centre, string $nature, int $fcfa): array
{
    $saisie = [];
    foreach (ForecastLine::centres() as $c => $libelle) {
        foreach (array_keys(ForecastLine::NATURES) as $n) {
            $saisie[$c][$n] = array_fill(1, 12, ($c === $centre && $n === $nature) ? $fcfa : 0);
        }
    }

    return $saisie;
}

test('Lot 6 : comparaison N vs N-1 et N vs M-1 sur hébergement, restaurant, recettes et dépenses', function () {
    $room = Room::query()->sellable()->first();
    $client = Customer::factory()->create();

    // 1. Données N-1 (Octobre 2025, du 1er au 15 octobre)
    // Réservation N-1 : 2 nuits à 50 000 F = 100 000 F
    Booking::create([
        'booking_number' => 'BKG-N1-1',
        'room_id' => $room->id,
        'customer_id' => $client->id,
        'status' => BookingStatus::CHECKED_OUT,
        'source' => 'direct',
        'check_in' => '2025-10-05',
        'check_out' => '2025-10-07',
        'total_nights' => 2,
        'price_per_night' => 5_000_000,
        'adults_count' => 1,
        'total_room_amount' => 10_000_000,
        'total_amount' => 10_000_000,
        'paid_amount' => 10_000_000,
        'balance_due' => 0,
    ]);

    // Restaurant N-1 : commande 30 000 F
    RestaurantCustomerOrder::create([
        'source' => 'staff',
        'table_number' => '1',
        'covers' => 2,
        'status' => 'served',
        'payment_status' => 'paid',
        'payment_method' => 'cash',
        'total_amount' => 3_000_000,
        'amount_paid' => 3_000_000,
        'placed_at' => '2025-10-08 12:30:00',
        'paid_at' => '2025-10-08 13:00:00',
    ]);

    // Paiement et Dépense N-1
    Payment::create([
        'amount' => 13_000_000,
        'currency' => 'XAF',
        'method' => 'cash',
        'status' => 'completed',
        'reference' => 'PAY-N1-1',
        'paid_at' => '2025-10-08 13:00:00',
    ]);
    Expense::create([
        'occurred_at' => '2025-10-09 10:00:00',
        'category' => 'food_beverage',
        'label' => 'Achat vivres N-1',
        'amount' => 5_000_000,
        'payment_method' => 'cash',
    ]);

    // 2. Données M-1 (Septembre 2026, du 1er au 15 septembre)
    Booking::create([
        'booking_number' => 'BKG-M1-1',
        'room_id' => $room->id,
        'customer_id' => $client->id,
        'status' => BookingStatus::CHECKED_OUT,
        'source' => 'direct',
        'check_in' => '2026-09-05',
        'check_out' => '2026-09-07',
        'total_nights' => 2,
        'price_per_night' => 6_000_000,
        'adults_count' => 1,
        'total_room_amount' => 12_000_000,
        'total_amount' => 12_000_000,
        'paid_amount' => 12_000_000,
        'balance_due' => 0,
    ]);

    // 3. Données N (Octobre 2026, du 1er au 15 octobre)
    Booking::create([
        'booking_number' => 'BKG-N-1',
        'room_id' => $room->id,
        'customer_id' => $client->id,
        'status' => BookingStatus::CHECKED_OUT,
        'source' => 'direct',
        'check_in' => '2026-10-05',
        'check_out' => '2026-10-08',
        'total_nights' => 3,
        'price_per_night' => 5_000_000,
        'adults_count' => 1,
        'total_room_amount' => 15_000_000,
        'total_amount' => 15_000_000,
        'paid_amount' => 15_000_000,
        'balance_due' => 0,
    ]);

    RestaurantCustomerOrder::create([
        'source' => 'staff',
        'table_number' => '2',
        'covers' => 3,
        'status' => 'served',
        'payment_status' => 'paid',
        'payment_method' => 'cash',
        'total_amount' => 6_000_000,
        'amount_paid' => 6_000_000,
        'placed_at' => '2026-10-10 12:30:00',
        'paid_at' => '2026-10-10 13:00:00',
    ]);

    $tableau = app(TableauDeBordGestion::class)->pour(CarbonImmutable::parse('2026-10-01'));

    // Vérifications Hébergement :
    // N = 150 000 F, N-1 = 100 000 F => variation N vs N-1 = +50.0%
    // M-1 = 120 000 F => variation N vs M-1 = +25.0%
    $heb = $tableau['hebergement'];
    $hebN1 = $tableau['hebergement_n1'];
    $hebAvant = $tableau['hebergement_avant'];

    expect($heb['revenu'])->toBe(15_000_000)
        ->and($hebN1['revenu'])->toBe(10_000_000)
        ->and(TableauDeBordGestion::variation($heb['revenu'], $hebN1['revenu']))->toBe(50.0)
        ->and($hebAvant['revenu'])->toBe(12_000_000)
        ->and(TableauDeBordGestion::variation($heb['revenu'], $hebAvant['revenu']))->toBe(25.0);

    // Vérifications Restaurant :
    // N = 60 000 F, N-1 = 30 000 F => variation N vs N-1 = +100.0%
    $resto = $tableau['restaurant'];
    $restoN1 = $tableau['restaurant_n1'];
    expect($resto['chiffre_affaires'])->toBe(6_000_000)
        ->and($restoN1['chiffre_affaires'])->toBe(3_000_000)
        ->and(TableauDeBordGestion::variation($resto['chiffre_affaires'], $restoN1['chiffre_affaires']))->toBe(100.0);

    // Vérification de la vue dashboard
    $controleur = creerMembreRole('controller');
    $this->actingAs($controleur)->get(route('pilotage.index', ['mois' => '2026-10']))
        ->assertOk()
        ->assertSee('N−1')
        ->assertSee('M−1');
});

test('Lot 6 : cas limites N vs N-1 (mois incomplet, bissextile, absence de donnees N-1)', function () {
    // Cas A : Mois incomplet au 15 octobre 2026
    // Une réservation en N-1 le 20 octobre 2025 ne doit PAS être comptabilisée dans la comparaison
    // car nous ne sommes que le 15 octobre 2026.
    $room = Room::query()->sellable()->first();
    $client = Customer::factory()->create();

    Booking::create([
        'booking_number' => 'BKG-N1-IN-RANGE',
        'room_id' => $room->id,
        'customer_id' => $client->id,
        'status' => BookingStatus::CHECKED_OUT,
        'source' => 'direct',
        'check_in' => '2025-10-02',
        'check_out' => '2025-10-04',
        'total_nights' => 2,
        'price_per_night' => 5_000_000,
        'adults_count' => 1,
        'total_room_amount' => 10_000_000,
        'total_amount' => 10_000_000,
        'paid_amount' => 10_000_000,
        'balance_due' => 0,
    ]);

    Booking::create([
        'booking_number' => 'BKG-N1-OUT-OF-RANGE',
        'room_id' => $room->id,
        'customer_id' => $client->id,
        'status' => BookingStatus::CHECKED_OUT,
        'source' => 'direct',
        'check_in' => '2025-10-20',
        'check_out' => '2025-10-22',
        'total_nights' => 2,
        'price_per_night' => 5_000_000,
        'adults_count' => 1,
        'total_room_amount' => 10_000_000,
        'total_amount' => 10_000_000,
        'paid_amount' => 10_000_000,
        'balance_due' => 0,
    ]);

    $tableau = app(TableauDeBordGestion::class)->pour(CarbonImmutable::parse('2026-10-01'));
    // Seule la réservation du 2 au 4 octobre est comptée (100 000 F), celle du 20 octobre est exclue
    expect($tableau['hebergement_n1']['revenu'])->toBe(10_000_000);

    // Cas B : Année bissextile (29 février 2028)
    $this->travelTo(CarbonImmutable::parse('2028-02-29 12:00:00'));
    $tableauBissextile = app(TableauDeBordGestion::class)->pour(CarbonImmutable::parse('2028-02-01'));
    // Doit s'exécuter sans erreur Carbon ni crash, borné au 28 février 2027
    expect($tableauBissextile['hebergement_n1']['revenu'])->toBe(0)
        ->and(TableauDeBordGestion::variation($tableauBissextile['hebergement']['revenu'], $tableauBissextile['hebergement_n1']['revenu']))->toBeNull();

    // Cas C : Absence de données N-1 (année 2029 sans aucune donnée)
    $this->travelTo(CarbonImmutable::parse('2029-05-15 10:00:00'));
    $tableauSansN1 = app(TableauDeBordGestion::class)->pour(CarbonImmutable::parse('2029-05-01'));
    expect(TableauDeBordGestion::variation($tableauSansN1['hebergement']['revenu'], $tableauSansN1['hebergement_n1']['revenu']))->toBeNull()
        ->and(TableauDeBordGestion::variation($tableauSansN1['restaurant']['chiffre_affaires'] ?? 0, $tableauSansN1['restaurant_n1']['chiffre_affaires'] ?? 0))->toBeNull()
        ->and(TableauDeBordGestion::variation($tableauSansN1['tresorerie']['encaissements'], $tableauSansN1['tresorerie']['encaissements_n1']))->toBeNull()
        ->and(TableauDeBordGestion::variation($tableauSansN1['tresorerie']['depenses'], $tableauSansN1['tresorerie']['depenses_n1']))->toBeNull();
});

test('Lot 6 : distinction stricte entre Budget initial et Prevision (Forecast)', function () {
    $controleur = creerMembreRole('controller');
    $budgetService = app(BudgetDeGestion::class);
    $previsionService = app(PrevisionDeGestion::class);

    // 1. Saisie d'un budget initial 2026 : 1 000 000 FCFA par mois en hébergement
    $budgetService->enregistrer(2026, [
        'hebergement' => ['revenue' => array_fill(1, 12, 1_000_000), 'cost' => array_fill(1, 12, 0)],
        'restaurant' => ['revenue' => array_fill(1, 12, 0), 'cost' => array_fill(1, 12, 0)],
        'structure' => ['revenue' => array_fill(1, 12, 0), 'cost' => array_fill(1, 12, 0)],
    ], $controleur);

    // 2. Saisie d'une prévision révisée (forecast) 2026 : 1 200 000 FCFA par mois en hébergement
    $previsionService->enregistrer(2026, [
        'hebergement' => ['revenue' => array_fill(1, 12, 1_200_000), 'cost' => array_fill(1, 12, 0)],
        'restaurant' => ['revenue' => array_fill(1, 12, 0), 'cost' => array_fill(1, 12, 0)],
        'structure' => ['revenue' => array_fill(1, 12, 0), 'cost' => array_fill(1, 12, 0)],
    ], $controleur);

    // Vérifier que les tables et modèles sont rigoureusement distincts
    expect(BudgetLine::where('year', 2026)->count())->toBeGreaterThan(0)
        ->and(ForecastLine::where('year', 2026)->count())->toBeGreaterThan(0);

    $budgetHeb = BudgetLine::where(['year' => 2026, 'center' => 'hebergement', 'nature' => 'revenue'])->sole();
    $forecastHeb = ForecastLine::where(['year' => 2026, 'center' => 'hebergement', 'nature' => 'revenue'])->sole();

    expect($budgetHeb->m10)->toBe(100_000_000) // 1 000 000 FCFA
        ->and($forecastHeb->m10)->toBe(120_000_000); // 1 200 000 FCFA

    // Vérifier que le tableau de bord reflète à la fois le budget et le forecast
    $tableau = app(TableauDeBordGestion::class)->pour(CarbonImmutable::parse('2026-10-01'));
    expect($tableau['budget'])->not->toBeNull()
        ->and($tableau['budget']['hebergement']['revenue']['budget'])->toBeGreaterThan(0)
        ->and($tableau['forecast'])->not->toBeNull()
        ->and($tableau['forecast']['hebergement']['revenue']['forecast'])->toBeGreaterThan(0)
        ->and($tableau['forecast']['hebergement']['revenue']['forecast'])->toBeGreaterThan($tableau['budget']['hebergement']['revenue']['budget']);
});

test('Lot 6 : atterrissage automatique de la prevision depuis le realise et le budget', function () {
    $controleur = creerMembreRole('controller');
    $budgetService = app(BudgetDeGestion::class);
    $previsionService = app(PrevisionDeGestion::class);

    // Définir un budget initial : 500 000 F par mois en hébergement
    $budgetService->enregistrer(2026, [
        'hebergement' => ['revenue' => array_fill(1, 12, 500_000), 'cost' => array_fill(1, 12, 0)],
        'restaurant' => ['revenue' => array_fill(1, 12, 0), 'cost' => array_fill(1, 12, 0)],
        'structure' => ['revenue' => array_fill(1, 12, 0), 'cost' => array_fill(1, 12, 0)],
    ], $controleur);

    // Simuler un réalisé en M10 (octobre 2026) : encaissement de 800 000 F (80 000 000 centimes)
    Payment::create([
        'amount' => 80_000_000,
        'currency' => 'XAF',
        'method' => 'cash',
        'status' => 'completed',
        'reference' => 'PAY-ATTERRISSAGE-1',
        'paid_at' => '2026-10-10 10:00:00',
    ]);

    // Générer l'atterrissage
    $atterrissage = $previsionService->depuisLeRealiseEtBudget(2026);

    // Les centres doivent être présents
    expect($atterrissage)->toHaveKey('hebergement');

    // Pour octobre (mois 10, écoulé/en cours) : le réalisé est pris (80 000 000 centimes = 800 000 F)
    expect($atterrissage['hebergement']['revenue'][10])->toBe(80_000_000);

    // Pour novembre (mois 11, futur) : le budget initial est conservé (50 000 000 centimes = 500 000 F)
    expect($atterrissage['hebergement']['revenue'][11])->toBe(50_000_000);

    // Consultation de la page forecast avec proposition d'atterrissage
    $this->actingAs($controleur)
        ->get(route('pilotage.forecast.index', ['annee' => 2026, 'depuis' => 'atterrissage']))
        ->assertOk()
        ->assertSee('projection d\'atterrissage', false)
        ->assertSee('800 000');
});

test('Lot 6 : segregation SoD - le controleur prepare/saisit, mais seul le RAF valide formellement', function () {
    $controleur = creerMembreRole('controller');
    $raf = creerMembreRole('finance_manager');
    $directeur = creerMembreRole('manager');

    // 1. Le contrôleur saisit le budget et la prévision
    $this->actingAs($controleur);
    $this->put(route('pilotage.budget.update', 2026), [
        'budget' => forecastMensuel('hebergement', 'revenue', 1_000_000),
    ])->assertRedirect(route('pilotage.budget.index', ['annee' => 2026]))
      ->assertSessionHasNoErrors();

    $this->put(route('pilotage.forecast.update', 2026), [
        'forecast' => forecastMensuel('hebergement', 'revenue', 1_100_000),
    ])->assertRedirect(route('pilotage.forecast.index', ['annee' => 2026]))
      ->assertSessionHasNoErrors();

    // 2. Le contrôleur tente de valider le budget -> Refusé (SoD)
    $this->post(route('pilotage.budget.valider', 2026))
        ->assertSessionHas('access_denied_popup', true);

    // 3. Le contrôleur tente de valider le forecast -> Refusé (SoD)
    $this->post(route('pilotage.forecast.valider', 2026))
        ->assertSessionHas('access_denied_popup', true);

    // Le statut doit toujours être draft
    expect(BudgetLine::where('year', 2026)->first()->status)->toBe(BudgetLine::STATUS_DRAFT)
        ->and(ForecastLine::where('year', 2026)->first()->status)->toBe(ForecastLine::STATUS_DRAFT);

    // 4. Le RAF valide formellement le budget
    $this->actingAs($raf)
        ->post(route('pilotage.budget.valider', 2026))
        ->assertRedirect(route('pilotage.budget.index', ['annee' => 2026]))
        ->assertSessionHas('success');

    $budgetValide = BudgetLine::where('year', 2026)->first();
    expect($budgetValide->status)->toBe(BudgetLine::STATUS_VALIDATED)
        ->and($budgetValide->validated_by)->toBe($raf->id)
        ->and($budgetValide->validated_at)->not->toBeNull();

    // 5. La Direction valide formellement le forecast
    $this->actingAs($directeur)
        ->post(route('pilotage.forecast.valider', 2026))
        ->assertRedirect(route('pilotage.forecast.index', ['annee' => 2026]))
        ->assertSessionHas('success');

    $forecastValide = ForecastLine::where('year', 2026)->first();
    expect($forecastValide->status)->toBe(ForecastLine::STATUS_VALIDATED)
        ->and($forecastValide->validated_by)->toBe($directeur->id)
        ->and($forecastValide->validated_at)->not->toBeNull();
});

test('Lot 6 : scellement du budget et de la prevision apres validation formelle', function () {
    $controleur = creerMembreRole('controller');
    $raf = creerMembreRole('finance_manager');

    // Saisie initiale par le contrôleur
    $this->actingAs($controleur);
    $this->put(route('pilotage.budget.update', 2026), [
        'budget' => forecastMensuel('hebergement', 'revenue', 1_000_000),
    ])->assertSessionHasNoErrors();

    // Validation formelle par le RAF
    $this->actingAs($raf)
        ->post(route('pilotage.budget.valider', 2026))
        ->assertSessionHas('success');

    // Tentative de modification ultérieure du budget par le contrôleur -> Rejetée
    $this->actingAs($controleur);
    $this->put(route('pilotage.budget.update', 2026), [
        'budget' => forecastMensuel('hebergement', 'revenue', 2_000_000),
    ])->assertSessionHasErrors('budget');

    // Le montant initial n'a pas été altéré
    expect(BudgetLine::where(['year' => 2026, 'center' => 'hebergement', 'nature' => 'revenue'])->sole()->m10)
        ->toBe(100_000_000);
});

test('Lot 6 : rapprochement d\'integrite financiere (Exploitation vs Caisse vs Grand Livre)', function () {
    $room = Room::query()->sellable()->first();
    $client = Customer::factory()->create();

    // 1. Chiffre d'affaires hébergement (150 000 F)
    Booking::create([
        'booking_number' => 'BKG-INT-1',
        'room_id' => $room->id,
        'customer_id' => $client->id,
        'status' => BookingStatus::CHECKED_OUT,
        'source' => 'direct',
        'check_in' => '2026-10-01',
        'check_out' => '2026-10-04',
        'total_nights' => 3,
        'price_per_night' => 5_000_000,
        'adults_count' => 1,
        'total_room_amount' => 15_000_000,
        'total_amount' => 15_000_000,
        'paid_amount' => 15_000_000,
        'balance_due' => 0,
    ]);

    // 2. Chiffre d'affaires restaurant (50 000 F)
    RestaurantCustomerOrder::create([
        'source' => 'staff',
        'table_number' => '5',
        'covers' => 2,
        'status' => 'served',
        'payment_status' => 'paid',
        'payment_method' => 'cash',
        'total_amount' => 5_000_000,
        'amount_paid' => 5_000_000,
        'placed_at' => '2026-10-02 12:00:00',
        'paid_at' => '2026-10-02 13:00:00',
    ]);

    // Total Exploitation = 200 000 F (150 000 F hébergement + 50 000 F restaurant)

    // 3. Encaissements en caisse : 130 000 F hébergement + 50 000 F restaurant = 180 000 F total
    // Écart temporaire de créance client = 20 000 F (200 000 - 180 000)
    Payment::create([
        'amount' => 13_000_000,
        'currency' => 'XAF',
        'method' => 'cash',
        'status' => 'completed',
        'reference' => 'PAY-RAPPROCHEMENT-1',
        'paid_at' => '2026-10-03 14:00:00',
    ]);

    $tableau = app(TableauDeBordGestion::class)->pour(CarbonImmutable::parse('2026-10-01'));
    $rap = $tableau['rapprochement'];

    expect($rap['ca_exploitation'])->toBe(20_000_000) // 200 000 F
        ->and($rap['encaissements'])->toBe(18_000_000) // 180 000 F
        ->and($rap['ecart_caisse'])->toBe(2_000_000); // 20 000 F

    // Consultation de la section dans la vue
    $controleur = creerMembreRole('controller');
    $this->actingAs($controleur)
        ->get(route('pilotage.index', ['mois' => '2026-10']))
        ->assertOk()
        ->assertSee('Rapprochement')
        ->assertSee('Exploitation')
        ->assertSee('Encaissements');
});

test('Lot 6 : les roles non habilites n\'ont pas acces aux previsions ni au pilotage', function () {
    foreach (['reception', 'storekeeper', 'housekeeping', 'econome'] as $role) {
        $user = creerMembreRole($role);
        $this->actingAs($user);

        // Pilotage index
        $this->get(route('pilotage.index'))->assertSessionHas('access_denied_popup', true);

        // Previsions index
        $this->get(route('pilotage.forecast.index'))->assertSessionHas('access_denied_popup', true);

        // Validation previsions
        $this->post(route('pilotage.forecast.valider', 2026))->assertSessionHas('access_denied_popup', true);
    }
});
