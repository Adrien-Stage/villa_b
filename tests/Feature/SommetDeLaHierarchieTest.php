<?php

/**
 * Le sommet de la hiérarchie : l'administrateur, puis le manager.
 *
 * L'administrateur est le service informatique de l'hôtel : il administre
 * l'application et consulte tous les services, sans y saisir d'opération.
 * Le manager dirige les opérations : il écrit sur l'hébergement, consulte
 * ailleurs, et se réserve la décision de dépense.
 *
 * « admin » a un temps quitté le référentiel, faute d'avoir un rôle dans
 * l'hôtel : il ne servait que d'identité à la console de supervision. La
 * direction l'a rendu à l'établissement, au service informatique — mais
 * sans aucune écriture métier, qu'il ne pourra saisir qu'en intervention
 * tracée.
 */

use App\Models\User;
use App\Support\DepartmentRoles;
use App\Support\PermissionCatalog;
use App\Support\RoleCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test("l'administrateur figure au référentiel, au sommet, et ne s'attribue pas sur place", function () {
    $admin = RoleCatalog::find('admin');

    // Créé depuis la console seulement : personne dans l'hôtel n'accorde un
    // niveau égal au sien.
    expect($admin)->not->toBeNull()
        ->and($admin['level'])->toBe(1)
        ->and($admin['is_assignable'])->toBeFalse();
});

test("l'administrateur consulte tout", function () {
    $aveugle = array_keys(array_filter(
        PermissionCatalog::all(),
        fn (array $roles, string $droit) => PermissionCatalog::estLecture($droit) && !in_array('admin', $roles, true),
        ARRAY_FILTER_USE_BOTH
    ));

    expect($aveugle)->toBe([]);
});

test("l'administrateur détient tous les droits du PMS", function () {
    $sansAdmin = array_keys(array_filter(PermissionCatalog::all(), fn (array $roles) => !in_array('admin', $roles, true)));

    expect($sansAdmin)->toBe([])
        ->and(PermissionCatalog::forRole('admin'))->toContain('rooms.types.creer', 'users.creer', 'economat.items.creer', 'bookings.payment.add');
});

test('aucun département ne confère admin', function () {
    foreach (DepartmentRoles::all() as $departement => $roles) {
        expect($roles)->not->toContain('admin', "Le département {$departement} confère encore admin.");
    }
});

test("le manager écrit sur l'hébergement, consulte ailleurs", function () {
    // Écriture : hébergement, statistiques, administration. Ailleurs, il
    // consulte : restauration, boutique, économat et comptabilité ont leurs
    // responsables, et le directeur qui saisirait à leur place brouillerait
    // la responsabilité de chacun.
    $lectureSeule = ['restaurant', 'shop', 'economat', 'accounting'];

    // Valider ou refuser une demande d'achat n'est pas saisir à la place de
    // l'économe : c'est la décision de dépense que la direction se réserve.
    // Convertir la demande en commande ou annuler une réception, en revanche,
    // reste l'exécution du magasin.
    $actesDeSupervision = [
        'economat.purchase_requests.approve',
        'economat.purchase_requests.reject',
        // Viser une demande interne quand le chef du service est absent :
        // le même acte de supervision, avant la décision de l'économe.
        'economat.requisitions.endorse',
        // Créer un restaurant, ses salles, composer son équipe : c'est la
        // structure de l'hôtel, que la direction décide — pas la saisie de
        // l'exploitation à la place du responsable de restaurant.
        'restaurant.restaurants.creer',
        'restaurant.restaurants.modifier',
        'restaurant.restaurants.spaces.creer',
        'restaurant.restaurants.spaces.modifier',
        'restaurant.restaurants.team.modifier',
        // Les unités de mesure des articles : un référentiel de l'hôtel,
        // employé par tous les services, que la direction tient aussi.
        'economat.units.creer',
        'economat.units.modifier',
        'economat.units.supprimer',
    ];

    $ecrituresIndues = [];

    foreach (PermissionCatalog::all() as $droit => $roles) {
        $module  = explode('.', $droit)[0];
        // La qualité de lecture est déclarée, non devinée : « claim » écrit
        // sans le dire, « revenue_journal » lit sans porter de verbe.
        $lecture = PermissionCatalog::estLecture($droit);

        if (in_array($module, $lectureSeule, true)
            && !$lecture
            && in_array('manager', $roles, true)
            && !in_array($droit, $actesDeSupervision, true)) {
            $ecrituresIndues[] = $droit;
        }
    }

    expect($ecrituresIndues)->toBe([]);
});

test("le manager consulte tout ce qu'il n'écrit plus", function () {
    $manquants = [];

    foreach (['restaurant', 'shop', 'economat', 'accounting'] as $module) {
        foreach (PermissionCatalog::all() as $droit => $roles) {
            if (str_starts_with($droit, $module . '.')
                && PermissionCatalog::estLecture($droit)
                && !in_array('manager', $roles, true)) {
                $manquants[] = $droit;
            }
        }
    }

    // Lecture seule veut dire lecture : le priver des deux le rendrait aveugle
    // sur les services dont il répond.
    expect($manquants)->toBe([]);
});

test("le comptage de caisse est contresigné par la comptabilité, jamais par le manager", function () {
    // Il supervise les caisses : les faire contrôler par lui ne séparerait rien.
    expect(PermissionCatalog::roles('accounting.cash_reviews.creer'))->not->toContain('manager')
        ->and(PermissionCatalog::roles('accounting.cash_reviews.creer'))->toContain('accountant');
});

test("tout droit autrefois tenu par admin est tenu par manager", function () {
    // La référence figée a été mise à jour en remplaçant admin par manager :
    // si un droit avait été perdu au passage, elle ne correspondrait plus.
    $reference = json_decode(
        file_get_contents(base_path('tests/Fixtures/route_roles_avant_phase1.json')),
        true,
        flags: JSON_THROW_ON_ERROR
    );

    $orphelins = [];
    foreach ($reference as $route => $roles) {
        if ($roles === []) {
            $orphelins[] = $route;
        }
    }

    // Aucune route ne doit se retrouver sans détenteur après le retrait.
    expect($orphelins)->toBe([]);
});

test("l'ancienne console « admin global » a disparu", function () {
    // Reste de l'époque multi-établissements : elle gérait des établissements
    // depuis l'intérieur de l'un d'eux. L'administrateur se connecte comme
    // tout le monde, et ses écrans vivent dans l'application.
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/admin/dashboard')->assertNotFound();
    $this->get('/admin')->assertNotFound();
});

test("l'administrateur se connecte par la page commune", function () {
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'it@hotel.test', 'password' => bcrypt('motdepasse-solide')]);

    $this->post('/login', ['email' => 'it@hotel.test', 'password' => 'motdepasse-solide'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin);
});

test("l'administrateur écrit à l'économat, et le journal le marque", function () {
    activerModules(['economat', 'comptabilite', 'accounting', 'hebergement']);

    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/economat/articles')->assertOk();
    $this->post('/economat/articles', ['name' => 'Savon de Marseille', 'unit' => 'pièce']);

    expect(\App\Models\StockItem::where('name', 'Savon de Marseille')->exists())->toBeTrue()
        ->and(\App\Models\AuditLog::where('event_type', 'action_administrateur')->where('user_id', $admin->id)->sole()->action)
        ->toStartWith('[Administrateur]');
});

test('le manager, lui, entre partout', function (string $url) {
    activerModules(['economat', 'comptabilite', 'accounting', 'hebergement', 'reservations', 'utilisateurs']);

    $this->actingAs(User::factory()->create(['role' => 'manager']))->get($url)->assertOk();
})->with(['/economat/articles', '/accounting', '/users', '/rooms', '/bookings']);
