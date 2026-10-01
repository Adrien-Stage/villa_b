<?php

/**
 * La console ne propose une portée que sur les droits dont un écran borne
 * vraiment les données. La liste vit dans PermissionScope::DROITS_BORNES ;
 * ce test la garde alignée sur les appels à DepartmentScoping.
 */

use App\Support\PermissionScope;
use Symfony\Component\Finder\Finder;

test('chaque droit borné par un écran est annoncé, et seulement ceux-là', function () {
    $trouves = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $fichier) {
        preg_match_all(
            '/DepartmentScoping::apply\(\s*[^,]+,\s*[^,]+,\s*\'([a-zA-Z_.]+)\'/s',
            $fichier->getContents(),
            $correspondances
        );

        foreach ($correspondances[1] as $droit) {
            $trouves[] = $droit;
        }
    }

    $trouves = array_values(array_unique($trouves));
    sort($trouves);
    $annonces = PermissionScope::DROITS_BORNES;
    sort($annonces);

    expect($trouves)->toBe($annonces);
});
