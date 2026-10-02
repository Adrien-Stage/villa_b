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

test("l'administrateur doit rester nu, direction comprise", function (string $autre) {
    expect(DutySegregation::isCompatible(['admin', $autre]))->toBeFalse();
})->with(['manager', 'accountant', 'reception', 'econome', 'controller']);

test("l'administrateur seul ne pose aucun problème", function () {
    expect(DutySegregation::isCompatible(['admin']))->toBeTrue();
});

test('un chef porte les incompatibilités de ses membres', function () {
    // Le chef de réception encaisse comme ses réceptionnistes.
    $conflits = DutySegregation::conflictsFor(['reception_chief', 'accountant']);

    expect($conflits)->toHaveCount(1)
        // Rapporté sous les rôles que la personne détient, pas sous le rôle inclus.
        ->and($conflits[0]['roles'])->toBe(['reception_chief', 'accountant']);
});

test('un cumul atteint par deux chemins n\'est rapporté qu\'une fois', function () {
    // Le chef économe détient le stock par lui-même et par le magasinier qu'il inclut.
    expect(DutySegregation::conflictsFor(['econome', 'accountant']))->toHaveCount(1);
});

test('le responsable financier tient les livres comme ses comptables', function () {
    expect(DutySegregation::isCompatible(['finance_manager', 'reception']))->toBeFalse()
        ->and(DutySegregation::isCompatible(['finance_manager', 'shop_cashier']))->toBeFalse();
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

// ── Cumuls ouverts par une case de la matrice ───────────────────────────────

test("autoriser une écriture fait porter au rôle les incompatibilités de ceux qui la détiennent", function () {
    $conflits = DutySegregation::conflitsDUneAutorisation('cashier', 'accounting.cash_reviews.creer');

    expect($conflits)->not->toBeEmpty()
        ->and($conflits[0]['roles'])->toContain('cashier');
});

test("une consultation n'ouvre aucun cumul", function () {
    expect(DutySegregation::conflitsDUneAutorisation('cashier', 'accounting.voir'))->toBe([]);
});

test("un droit déjà détenu n'ouvre rien de neuf", function () {
    expect(DutySegregation::conflitsDUneAutorisation('accountant', 'accounting.cash_reviews.creer'))->toBe([]);
});

test("la configuration tenue par l'administrateur n'est pas une fonction à imiter", function () {
    // rooms.creer : administrateur, manager, chef de réception. Le donner à
    // un réceptionniste n'est pas cumuler avec l'administrateur.
    expect(DutySegregation::conflitsDUneAutorisation('reception', 'rooms.creer'))->toBe([]);
});

test("le contrôle ne reçoit aucune écriture", function () {
    expect(DutySegregation::conflitsDUneAutorisation('controller', 'economat.purchase_requests.approve'))->not->toBeEmpty()
        ->and(DutySegregation::conflitsDUneAutorisation('quality_auditor', 'economat.items.creer'))->not->toBeEmpty();
});
