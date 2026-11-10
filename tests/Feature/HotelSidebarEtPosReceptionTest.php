<?php

use App\Models\Intervention;
use App\Models\PermissionGrant;
use App\Models\StockItem;
use App\Models\StockRequisition;
use App\Models\StockRequisitionLine;
use App\Models\User;
use App\Services\PermissionResolver;
use App\Support\Navigation;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class]);
    RoleCatalog::sync();
    activerModules([
        'hebergement', 'reservations', 'clients', 'housekeeping',
        'economat', 'achats', 'restaurant', 'shop', 'technique',
        'accounting', 'comptabilite',
    ]);
});

function extraireLibellesSidebar(string $html): array
{
    preg_match('#<nav class="flex-1 overflow-y-auto.*?</nav>#s', $html, $nav);
    preg_match_all('#<span class="sidebar-libelle[^"]*">(.*?)</span>#s', $nav[0] ?? '', $matches);

    return array_map(
        fn (string $label) => html_entity_decode(trim($label), ENT_QUOTES | ENT_HTML5),
        $matches[1] ?? []
    );
}

test("la barre latérale Hôtel suit l'ordre exact demandé sans POS Réception ni Demandes d'achat", function () {
    $chefReception = User::factory()->create(['role' => 'reception_chief']);
    $htmlChef = $this->actingAs($chefReception)->get(route('agenda.index'))->assertOk()->getContent();
    $libellesChef = extraireLibellesSidebar($htmlChef);
    $mobileChef = collect(Navigation::getMobileTabs($chefReception))->pluck('label')->all();

    expect($libellesChef)->toBe([
        'Agenda',
        'Réservations',
        'Chambres',
        'Tarifs',
        "Demandes à l'économat",
    ])
        ->and($libellesChef)->not->toContain('POS Réception')
        ->and($libellesChef)->not->toContain("Demandes d'achat")
        ->and($mobileChef)->toBe(['Agenda', 'Réservations', 'Chambres', 'Apps'])
        ->and($mobileChef)->not->toContain('POS');

    $manager = User::factory()->create(['role' => 'manager']);
    $htmlManager = $this->actingAs($manager)->get(route('agenda.index'))->assertOk()->getContent();
    $libellesManager = extraireLibellesSidebar($htmlManager);
    $mobileManager = collect(Navigation::getMobileTabs($manager))->pluck('label')->all();

    expect($libellesManager)->toBe([
        'Agenda',
        'Réservations',
        'Chambres',
        'Tarifs',
        'Housekeeping',
        'Suivi des chambres',
        'Mini-bars',
        'Fiches techniques',
    ])
        ->and($libellesManager)->not->toContain('POS Réception')
        ->and($libellesManager)->not->toContain("Demandes d'achat")
        ->and($mobileManager)->toBe(['Agenda', 'Réservations', 'Chambres', 'Housekeeping', 'Apps'])
        ->and($mobileManager)->not->toContain('POS');

    // La gouvernante / chef des étages reste aussi sur Demandes à l'économat uniquement, jamais Demandes d'achat.
    $chefEtages = User::factory()->create(['role' => 'housekeeping_leader']);
    $htmlEtages = $this->actingAs($chefEtages)->get(route('economat.requisitions.index'))->assertOk()->getContent();
    $libellesEtages = extraireLibellesSidebar($htmlEtages);
    expect($libellesEtages)->toContain("Demandes à l'économat")
        ->and($libellesEtages)->not->toContain("Demandes d'achat")
        ->and($libellesEtages)->not->toContain('POS Réception');
});

test("la tuile Hôtel ouvre l'Agenda en premier pour la réception et la direction", function () {
    foreach (['reception', 'reception_chief', 'manager'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $tuiles = collect(Navigation::getLauncherGroups($user))
            ->mapWithKeys(fn (array $g) => [$g['id'] => parse_url($g['url'], PHP_URL_PATH)]);

        expect($tuiles['hotel'] ?? null)->toBe('/agenda');
    }

    $financier = User::factory()->create(['role' => 'finance_manager']);
    $tuilesFin = collect(Navigation::getLauncherGroups($financier))
        ->mapWithKeys(fn (array $g) => [$g['id'] => parse_url($g['url'], PHP_URL_PATH)]);
    expect($tuilesFin['hotel'] ?? null)->toBe(parse_url(route('rooms.rates.index'), PHP_URL_PATH));

    $valet = User::factory()->create(['role' => 'housekeeping_staff']);
    $tuilesHk = collect(Navigation::getLauncherGroups($valet))
        ->mapWithKeys(fn (array $g) => [$g['id'] => parse_url($g['url'], PHP_URL_PATH)]);
    expect($tuilesHk['hotel'] ?? null)->toBe('/housekeeping');
});

test("les demandes d'achat sont retirées des services demandeurs qui utilisent les demandes à l'économat", function () {
    $chefCuisine = User::factory()->create(['role' => 'restaurant_chief']);
    $libellesResto = extraireLibellesSidebar(
        $this->actingAs($chefCuisine)->get(route('restaurant.breakfast.index'))->assertOk()->getContent()
    );
    expect($libellesResto)->not->toContain("Demandes d'achat");

    $techManager = User::factory()->create(['role' => 'tech_manager']);
    $libellesTech = extraireLibellesSidebar(
        $this->actingAs($techManager)->get(route('technique.index'))->assertOk()->getContent()
    );
    expect($libellesTech)->toContain("Demandes à l'économat")
        ->and($libellesTech)->not->toContain("Demandes d'achat");

    $shopManager = User::factory()->create(['role' => 'shop_manager']);
    $shopRoutes = collect(Navigation::getGroupTabs('shop', $shopManager))->pluck('route')->all();
    expect($shopRoutes)->toContain('economat.requisitions.index')
        ->and($shopRoutes)->not->toContain('economat.purchase_requests.index');

    // Aucun service demandeur opérationnel ne peut consulter ou soumettre une demande d'achat.
    $article = StockItem::create([
        'name' => 'Produit Entretien',
        'unit' => 'flacon',
        'current_stock' => 2,
        'average_cost' => 100000,
        'is_active' => true,
    ]);

    foreach (['reception', 'reception_chief', 'housekeeping_leader', 'restaurant_chief', 'restaurant_manager', 'shop_manager', 'tech_manager'] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user);

        $this->get(route('economat.purchase_requests.index'))
            ->assertSessionHas('access_denied_popup', true);
        $this->get(route('economat.purchase_requests.create'))
            ->assertSessionHas('access_denied_popup', true);
        $this->post(route('economat.purchase_requests.store'), [
            'department' => 'cuisine',
            'priority'   => 'normal',
            'lines'      => [['stock_item_id' => $article->id, 'quantity_requested' => 5]],
        ], ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
    }

    // En revanche, l'économe et la direction conservent les demandes d'achat dans Économat / Achats.
    $econome = User::factory()->create(['role' => 'econome']);
    $this->actingAs($econome);
    expect(Navigation::getActiveGroup('economat.purchase_requests.index')['id'])->toBe('economat');
    $this->get(route('economat.purchase_requests.index'))->assertOk();
    $this->get(route('economat.purchase_requests.create'))->assertOk();

    $manager = User::factory()->create(['role' => 'manager']);
    $this->actingAs($manager);
    expect(Navigation::getActiveGroup('economat.purchase_requests.index')['id'])->toBe('achats');
    $this->get(route('economat.purchase_requests.index'))->assertOk();
});

test('le mode POS Réception est réservé uniquement au service réception même avec dérogation ou intervention', function () {
    $droits = app(PermissionResolver::class);

    // 1. Autorisé uniquement pour reception et reception_chief
    foreach (['reception', 'reception_chief'] as $roleAutorise) {
        $agent = User::factory()->create(['role' => $roleAutorise]);
        expect($droits->allows($agent, 'reception.pos.voir'))->toBeTrue()
            ->and($droits->allows($agent, 'reception.pos.sales.creer'))->toBeTrue();

        $this->actingAs($agent)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mode POS Réception');

        $this->actingAs($agent)
            ->get(route('agenda.index'))
            ->assertOk()
            ->assertSee('Mode POS Réception')
            ->assertDontSee('POS Réception</a>');

        $this->actingAs($agent)->get(route('reception.pos.index'))->assertOk();
        $this->actingAs($agent)->get(route('reception.pos.history'))->assertOk();
    }

    // 2. Interdit pour tous les autres rôles (y compris manager, admin, controller, support, finance_manager)
    $rolesRefuses = [
        'manager', 'admin', 'controller', 'finance_manager', 'support',
        'econome', 'purchasing_manager', 'housekeeping_leader', 'housekeeping_staff',
        'restaurant_chief', 'restaurant_staff', 'shop_manager', 'tech_manager', 'accountant',
    ];

    foreach ($rolesRefuses as $role) {
        $utilisateur = User::factory()->create(['role' => $role]);

        expect($droits->allows($utilisateur, 'reception.pos.voir'))->toBeFalse("{$role} ne doit pas voir le POS Réception")
            ->and($droits->allows($utilisateur, 'reception.pos.sales.creer'))->toBeFalse()
            ->and($droits->allows($utilisateur, 'reception.pos.history'))->toBeFalse();

        $this->actingAs($utilisateur);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Mode POS Réception');

        $this->get(route('reception.pos.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
        $this->post(route('reception.pos.sales.store'), [], ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
    }

    // 3. Même une surcharge explicite (PermissionGrant ALLOW) ou une intervention admin active ne contourne pas le verrou
    $manager = User::factory()->create(['role' => 'manager']);
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_USER,
        'subject_id'   => (string) $manager->id,
        'permission'   => 'reception.pos.voir',
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'origin'       => PermissionGrant::ORIGINE_ETABLISSEMENT,
    ]);
    expect($droits->allows($manager->fresh(), 'reception.pos.voir'))->toBeFalse();
    $this->actingAs($manager)->get(route('reception.pos.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);

    $admin = User::factory()->create(['role' => 'admin']);
    Intervention::create([
        'user_id'    => $admin->id,
        'motif'      => 'Assistance caisse réception',
        'perimetres' => ['hebergement'],
        'debut'      => now()->subMinute(),
        'fin_prevue' => now()->addHour(),
    ]);
    PermissionGrant::create([
        'subject_type' => PermissionGrant::SUJET_ROLE,
        'subject_id'   => 'admin',
        'permission'   => 'reception.pos.voir',
        'effect'       => PermissionGrant::EFFET_ALLOW,
        'origin'       => PermissionGrant::ORIGINE_ERP,
    ]);
    expect($droits->allows($admin->fresh(), 'reception.pos.voir'))->toBeFalse();
    $this->actingAs($admin)->get(route('reception.pos.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertStatus(403);
});

test("l'économat dispose d'un raccourci sur une demande interne en rupture pour créer une demande d'achat pré-remplie", function () {
    $reception = User::factory()->create(['role' => 'reception']);
    $magasinier = User::factory()->create(['role' => 'storekeeper']);
    $econome = User::factory()->create(['role' => 'econome']);

    $manquant = StockItem::create([
        'name'          => 'Gel douche 30ml',
        'unit'          => 'flacon',
        'current_stock' => 2,
        'min_stock'     => 10,
        'average_cost'  => 50000,
        'is_active'     => true,
    ]);
    $suffisant = StockItem::create([
        'name'          => 'Gant de toilette blanc',
        'unit'          => 'pièce',
        'current_stock' => 25,
        'min_stock'     => 5,
        'average_cost'  => 100000,
        'is_active'     => true,
    ]);

    $demande = StockRequisition::create([
        'department'   => 'hebergement',
        'status'       => StockRequisition::STATUS_PENDING,
        'purpose'      => 'Réassort week-end réception',
        'requested_by' => $reception->id,
    ]);
    StockRequisitionLine::create([
        'stock_requisition_id' => $demande->id,
        'stock_item_id'        => $manquant->id,
        'quantity_requested'   => 10, // manque 8 unités
    ]);
    StockRequisitionLine::create([
        'stock_requisition_id' => $demande->id,
        'stock_item_id'        => $suffisant->id,
        'quantity_requested'   => 5, // stock suffisant (25 >= 5)
    ]);

    // Ni le service demandeur ni le magasinier (sans droit achat) ne voient le raccourci
    $this->actingAs($reception)
        ->get(route('economat.requisitions.show', $demande))
        ->assertOk()
        ->assertDontSee('Créer une demande d&#039;achat', false)
        ->assertDontSee("Créer une demande d'achat");

    $this->actingAs($magasinier)
        ->get(route('economat.requisitions.show', $demande))
        ->assertOk()
        ->assertDontSee('Créer une demande d&#039;achat', false);

    // L'économe voit l'alerte et le bouton pointant vers la création pré-remplie
    $expectedUrl = route('economat.purchase_requests.create', ['requisition' => $demande->id]);
    $this->actingAs($econome)
        ->get(route('economat.requisitions.show', $demande))
        ->assertOk()
        ->assertSee('Stock insuffisant sur 1 article')
        ->assertSee("Créer une demande d'achat", false)
        ->assertSee($expectedUrl, false);

    // L'écran de création reprend uniquement l'article manquant et la quantité à couvrir (10 - 2 = 8)
    $pageCreate = $this->actingAs($econome)
        ->get($expectedUrl)
        ->assertOk()
        ->assertSee($demande->number)
        ->assertSee("Approvisionnement suite à la demande économat {$demande->number}")
        ->assertViewHas('prefillLines', [[
            'stock_item_id' => (string) $manquant->id,
            'unit'          => $manquant->shortUnit(),
            'quantity'      => 8.0,
            'notes'         => '',
        ]]);

    $expectedInitialJs = \Illuminate\Support\Js::from([[
        'stock_item_id' => (string) $manquant->id,
        'unit'          => $manquant->shortUnit(),
        'quantity'      => 8,
        'notes'         => '',
    ]])->toHtml();
    expect(html_entity_decode($pageCreate->getContent(), ENT_QUOTES | ENT_HTML5))
        ->toContain($expectedInitialJs);

    // En rupture totale (stock = 0), la priorité passe automatiquement à Urgente
    $manquant->update(['current_stock' => 0]);
    $htmlUrgent = $this->actingAs($econome)->get($expectedUrl)->assertOk()->getContent();
    expect($htmlUrgent)->toMatch('/<option value="urgent"\s+selected/i');

    // Soumission complète de la demande d'achat créée depuis le raccourci
    $this->actingAs($econome)
        ->post(route('economat.purchase_requests.store'), [
            'priority' => 'urgent',
            'purpose'  => "Approvisionnement suite à la demande économat {$demande->number}",
            'lines'    => [
                ['stock_item_id' => $manquant->id, 'quantity_requested' => 10],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('purchase_requests', [
        'department' => 'economat',
        'priority'   => 'urgent',
    ]);

    // Quand le stock devient suffisant ou que la demande est déjà livrée, le bouton disparaît
    $manquant->update(['current_stock' => 50]);
    $this->actingAs($econome)
        ->get(route('economat.requisitions.show', $demande))
        ->assertOk()
        ->assertDontSee("Créer une demande d'achat", false);
});
