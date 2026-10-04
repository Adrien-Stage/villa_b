<?php

/*
 * Aucun document imprimé depuis le navigateur ne porte l'en-tête et le pied
 * de page de Chrome (titre et adresse de la page, date, « 1 sur 3 »).
 *
 * Chrome les ajoute dès que la feuille a une marge haute ou basse : chaque
 * règle @page des vues doit donc garder ces deux marges à zéro, et refaire
 * son blanc dans le document (partials/impression).
 */

/** @return list<array{fichier: string, marge: string, avant: string}> */
function margesDesPagesImprimees(): array
{
    $trouvees = [];
    $fichiers = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($fichiers as $fichier) {
        if (! str_ends_with($fichier->getFilename(), '.blade.php')) {
            continue;
        }

        // Les échos Blade deviennent « X » : leurs accolades fausseraient la lecture des blocs.
        $source = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', 'X', file_get_contents($fichier->getPathname()));
        $relatif = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $fichier->getPathname());

        preg_match_all('/@page\s*\{/', $source, $pages, PREG_OFFSET_CAPTURE);

        foreach ($pages[0] as [$ouverture, $position]) {
            // Le bloc entier, marges des boîtes de marge comprises.
            $profondeur = 0;
            $fin = $position;
            for ($i = $position; $i < strlen($source); $i++) {
                $profondeur += match ($source[$i]) { '{' => 1, '}' => -1, default => 0 };
                if ($profondeur === 0 && $source[$i] === '}') {
                    $fin = $i;
                    break;
                }
            }

            // Seules les déclarations propres à la page : les boîtes de marge
            // (@right-bottom…) ont leurs propres « margin ».
            $bloc = preg_replace('/@[a-z-]+\s*\{[^{}]*\}/', '', substr($source, $position + strlen($ouverture), $fin - $position - strlen($ouverture)));

            if (preg_match('/(?<![-\w])margin\s*:\s*([^;}]+)/', $bloc, $marge)) {
                $trouvees[] = ['fichier' => $relatif, 'marge' => trim($marge[1]), 'avant' => substr($source, max(0, $position - 80), 80)];
            }
        }
    }

    return $trouvees;
}

test('aucune page imprimée ne laisse de marge haute ou basse au navigateur', function () {
    $fautives = [];

    foreach (margesDesPagesImprimees() as $page) {
        // Le PDF passe par dompdf, qui n'ajoute rien : ses marges sont libres.
        if (str_contains($page['avant'], '$pourPdf')) {
            continue;
        }

        $valeurs = preg_split('/\s+/', trim(str_replace('!important', '', $page['marge'])));
        $haut = $valeurs[0];
        $bas = $valeurs[2] ?? $valeurs[0];

        foreach ([$haut, $bas] as $valeur) {
            if (! preg_match('/^0(mm|px|cm|in|pt)?$/', $valeur)) {
                $fautives[] = "{$page['fichier']} : margin {$page['marge']}";
                break;
            }
        }
    }

    expect($fautives)->toBe([]);
});

test('le réglage commun rend ses marges dans le document', function () {
    $css = view('partials.impression', ['haut' => '10mm', 'bas' => '12mm', 'cotes' => '9mm', 'pied' => 'Bon "A" </style>'])->render();

    expect($css)
        ->toContain('margin: 0 9mm;')
        ->toContain('padding-top: 10mm !important;')
        ->toContain('box-decoration-break: clone;')
        ->toContain('counter(page) " / " counter(pages)')
        // Le texte de pied ne ferme ni la chaîne CSS ni la balise <style>.
        ->toContain('content: "Bon \"A\" \3C /style>";')
        ->not->toContain('</style>');
});
