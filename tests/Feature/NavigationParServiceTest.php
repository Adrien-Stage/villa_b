<?php

use App\Models\User;
use App\Support\Navigation;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * Chacun arrive à son écran de travail : une tuile ouvre le premier écran que
 * la personne a le droit de voir, jamais un écran qui la refuserait. Les
 * services qui ne font qu'adresser des demandes à l'économat les font depuis
 * leur propre application, sans voir l'Économat.
 */

beforeEach(function () {
    $this->seed([\Database\Seeders\TenantSeeder::class, \Database\Seeders\RoomTypeSeeder::class, \Database\Seeders\RoomSeeder::class]);
    RoleCatalog::sync();
    activerModules(['housekeeping', 'economat', 'hebergement', 'reservations', 'clients', 'restaurant', 'shop', 'accounting', 'comptabilite']);
});

function agentNav(string $role): User
{
    return User::factory()->create(['role' => $role, 'name' => 'Awa Mballa', 'is_active' => true]);
}

/** @return array<string, string> tuile => chemin */
function tuiles(User $user): array
{
    return collect(Navigation::getLauncherGroups($user))
        ->mapWithKeys(fn (array $g) => [$g['id'] => parse_url($g['url'], PHP_URL_PATH)])
        ->all();
}

test("le housekeeping arrive à son tableau, sans l'Économat, et chaque tuile s'ouvre", function () {
    foreach (['housekeeping_leader', 'housekeeping_supervisor', 'housekeeping_staff'] as $role) {
        $agent = agentNav($role);
        $tuiles = tuiles($agent);

        expect($tuiles['hotel'])->toBe('/housekeeping')
            ->and($tuiles)->not->toHaveKey('economat');

        $this->actingAs($agent);
        foreach ($tuiles as $id => $chemin) {
            $reponse = $this->get($chemin);
            expect($reponse->status() === 200 || ($id === 'settings' && $reponse->isRedirect(route('profile.edit'))))
                ->toBeTrue("{$role} : la tuile {$id} ({$chemin}) répond {$reponse->status()}");
        }
    }
});

test("le housekeeping fait ses demandes à l'économat depuis l'application Hôtel", function () {
    $valet = agentNav('housekeeping_staff');
    $this->actingAs($valet);

    $this->get(route('housekeeping.index'))->assertOk()->assertSee("Demandes à l&#039;économat", false);

    $page = $this->get(route('economat.requisitions.create'))->assertOk();
    expect(Navigation::getActiveGroup('economat.requisitions.create')['id'])->toBe('hotel');
    $page->assertSee('Housekeeping')->assertDontSee('Bons de commande');

    // La gouvernante demande au nom du housekeeping, pas d'un « autre service ».
    $this->actingAs(agentNav('housekeeping_supervisor'))->get(route('economat.requisitions.create'))
        ->assertOk()->assertSee('<option value="housekeeping"', false)->assertDontSee('<option value="autre"', false);
});

test('qui tient le magasin garde la tuile Économat et y trouve les demandes', function () {
    foreach (['manager', 'econome', 'storekeeper'] as $role) {
        $user = agentNav($role);
        expect(tuiles($user))->toHaveKey('economat');

        $this->actingAs($user);
        expect(Navigation::getActiveGroup('economat.requisitions.index')['id'])->toBe('economat');
    }

    expect(tuiles(agentNav('manager'))['hotel'])->toBe('/rooms');
});

test('les autres services font aussi leurs demandes depuis leur application', function () {
    $cuisinier = agentNav('restaurant_cook');
    $this->actingAs($cuisinier);
    expect(tuiles($cuisinier))->not->toHaveKey('economat')
        ->and(Navigation::getActiveGroup('economat.requisitions.index')['id'])->toBe('restaurant');
    $this->get(route('economat.requisitions.index'))->assertOk()->assertSee("Demandes à l&#039;économat", false);

    $vendeur = agentNav('shop_cashier');
    $this->actingAs($vendeur);
    expect(tuiles($vendeur))->not->toHaveKey('economat')
        ->and(Navigation::getActiveGroup('economat.requisitions.index')['id'])->toBe('shop');
    $this->get(route('shop.orders.index'))->assertOk()->assertSee('Demandes à l’économat');

    $receptionniste = agentNav('reception');
    $this->actingAs($receptionniste);
    expect(tuiles($receptionniste))->not->toHaveKey('economat')
        ->and(Navigation::getActiveGroup('economat.purchase_requests.index')['id'])->toBe('hotel');

    $comptable = agentNav('accountant');
    $this->actingAs($comptable);
    expect(Navigation::getActiveGroup('economat.requisitions.index')['id'])->toBe('accounting');
});

test("le menu du compte remplace le bouton de déconnexion, pour tout le monde", function () {
    foreach (['housekeeping_staff', 'manager', 'reception'] as $role) {
        $this->actingAs(agentNav($role))->get(route('dashboard'))->assertOk()
            ->assertSee('Mon compte')
            ->assertSee('Modifier mon profil')
            ->assertSee('Se déconnecter')
            ->assertSee(route('profile.edit'));
    }
});

test("les éditions du housekeeping : tout pour l'encadrement, son travail pour l'équipe", function () {
    $toutes = ['feuille-route-housekeeping', 'rapport-housekeeping', 'journal-housekeeping', 'incidents-housekeeping',
        'etat-minibars', 'releves-minibar', 'rechargements-minibar'];

    foreach (['housekeeping_leader', 'housekeeping_supervisor'] as $role) {
        $this->actingAs(agentNav($role));
        $this->get(route('editions.index'))->assertOk()->assertSee('Housekeeping')->assertSee("Rapport d&#039;activité du housekeeping", false);
        foreach ($toutes as $cle) {
            $this->get(route('editions.show', $cle))->assertOk();
            $this->get(route('editions.print', $cle))->assertOk();
        }
    }

    $this->actingAs(agentNav('housekeeping_staff'));
    $this->get(route('editions.index'))->assertOk()->assertSee('Feuille de route des équipes')
        ->assertDontSee('Relevés du mini-bar')->assertDontSee('Journal du housekeeping');
    $this->get(route('editions.show', 'feuille-route-housekeeping'))->assertOk();
    $this->get(route('editions.show', 'releves-minibar'))->assertForbidden();
});
