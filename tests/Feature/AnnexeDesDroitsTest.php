<?php

use Illuminate\Support\Str;

test('la commande de documentation produit le résumé et les tableaux des droits dans l’ordre du guide', function () {
    $sortie = 'storage/framework/testing/annexe-droits-'.Str::uuid().'.md';
    $chemin = base_path($sortie);

    try {
        $this->artisan('droits:annexe', ['--sortie' => $sortie])->assertSuccessful();
        $annexe = file_get_contents($chemin);

        expect($annexe)->toStartWith('# Annexe — Qui détient quoi')
            ->toContain('## Résumé par rôle', '## Droit par droit', '`restaurant.bar.voir`')
            ->toContain("### Chambres\n", "### Réservations\n", "### Restaurant\n");
        expect(strpos($annexe, '### Chambres'))->toBeLessThan(strpos($annexe, '### Réservations'));
    } finally {
        if (is_file($chemin)) {
            unlink($chemin);
        }
    }
});
