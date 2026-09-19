<?php

/**
 * Séparation des tâches : les cumuls de rôles qui permettent de commettre un
 * acte et de le dissimuler.
 *
 * Déclaratif à ce stade — rien n'est encore refusé à l'assignation. Ces tests
 * fixent la règle avant qu'elle soit appliquée, pour que l'application ne
 * puisse pas la déformer en chemin.
 */

use App\Support\DutySegregation;
use App\Support\RoleCatalog;

test('le cumul relevé sur un compte réel est bien détecté', function () {
    // marina@test.com porte econome + accountant + quality_auditor.
    $conflits = DutySegregation::conflictsFor(['econome', 'accountant', 'quality_auditor']);

    $paires = array_map(fn ($c) => implode(' × ', $c['roles']), $conflits);

    expect(DutySegregation::isCompatible(['econome', 'accountant', 'quality_auditor']))->toBeFalse()
        ->and($paires)->toContain('econome × accountant')
        ->and($paires)->toContain('quality_auditor × econome')
        ->and($paires)->toContain('quality_auditor × accountant');
});

test('un cumul opérationnel banal reste permis', function () {
    // adrian@gmail.com : reception + cashier. Deux fonctions d'exécution,
    // aucune fonction d'enregistrement ni de contrôle.
    expect(DutySegregation::isCompatible(['reception', 'cashier']))->toBeTrue();
});

test('le contrôle indépendant ne se cumule à aucun rôle opérationnel', function (string $operationnel) {
    expect(DutySegregation::isCompatible(['quality_auditor', $operationnel]))->toBeFalse();
})->with(['reception', 'cashier', 'econome', 'accountant', 'restaurant_chief', 'shop_cashier']);

test("l'accès technique doit rester nu", function () {
    expect(DutySegregation::isCompatible(['it_support', 'accountant']))->toBeFalse()
        // Seul, il ne pose aucun problème.
        ->and(DutySegregation::isCompatible(['it_support']))->toBeTrue();
});

test('chaque motif est lisible : le directeur le lira avant de déroger', function () {
    foreach (DutySegregation::incompatibilities() as $paire) {
        expect($paire['motif'])->not->toBe('')
            ->and(mb_strlen($paire['motif']))->toBeGreaterThan(30);
    }
});

test('les rôles cités existent tous au catalogue', function () {
    $connus = array_column(RoleCatalog::all(), 'slug');

    $cites = [DutySegregation::CONTROLE_INDEPENDANT, DutySegregation::ACCES_TECHNIQUE];
    foreach (DutySegregation::incompatibilities() as $paire) {
        $cites = array_merge($cites, $paire['roles']);
    }

    // Une règle qui nomme un rôle inexistant ne protège rien.
    expect(array_values(array_diff(array_unique($cites), $connus)))->toBe([]);
});

test('un rôle seul ne viole jamais la règle', function (string $slug) {
    expect(DutySegregation::isCompatible([$slug]))->toBeTrue();
})->with(array_column(RoleCatalog::all(), 'slug'));

test('le contrôle de gestion ne se cumule à aucun rôle opérationnel', function (string $operationnel) {
    expect(DutySegregation::isCompatible(['controller', $operationnel]))->toBeFalse();
})->with(['econome', 'accountant', 'cashier', 'reception', 'restaurant_chief']);

test('le contrôleur de gestion voit tout et n\'écrit rien', function () {
    $droits = \App\Support\PermissionCatalog::forRole('controller');

    $ecritures = array_values(array_filter(
        $droits,
        fn (string $d) => !\App\Support\PermissionCatalog::estLecture($d)
    ));

    // Sa valeur tient à ce qu'il ne participe pas aux opérations qu'il surveille.
    expect($ecritures)->toBe([])
        ->and(count($droits))->toBeGreaterThan(40);
});

test('le contrôleur voit la comptabilité, l\'économat et les opérations', function (string $droit) {
    expect(\App\Support\PermissionCatalog::roles($droit))->toContain('controller');
})->with(['accounting.voir', 'economat.voir', 'rooms.voir', 'bookings.voir', 'restaurant.orders.voir']);
