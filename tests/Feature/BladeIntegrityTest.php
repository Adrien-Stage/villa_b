<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Intégrité des vues Blade.
 *
 * Une directive non fermée ne se voit ni à la compilation ni au lint :
 * `php artisan view:cache` compile sans vérifier qu'un @if a son @endif, et
 * le PHP produit n'est analysé qu'au rendu. L'erreur ne surgit donc qu'en
 * ouvrant la page — un 500 en production. C'est ce qui est arrivé à l'écran
 * des fiches techniques du restaurant.
 *
 * Ce test comble ce trou : il vérifie l'équilibre des directives dans toutes
 * les vues, sans avoir à rendre chacune d'elles.
 */

/** Directives ouvrantes et leur fermeture attendue. */
const BLADE_PAIRES = [
    'if' => 'endif', 'unless' => 'endunless', 'foreach' => 'endforeach',
    'forelse' => 'endforelse', 'for' => 'endfor', 'while' => 'endwhile',
    'switch' => 'endswitch', 'section' => 'endsection', 'push' => 'endpush',
    'prepend' => 'endprepend', 'once' => 'endonce', 'error' => 'enderror',
    'isset' => 'endisset', 'empty' => 'endempty', 'auth' => 'endauth',
    'guest' => 'endguest', 'can' => 'endcan', 'cannot' => 'endcannot',
    'canany' => 'endcanany', 'verbatim' => 'endverbatim', 'role' => 'endrole',
    'hasrole' => 'endhasrole', 'fragment' => 'endfragment',
];

/**
 * Branches et instructions : elles n'ouvrent aucun bloc. « empty » en fait
 * partie lorsqu'il sert de branche à @forelse — la parenthèse les distingue,
 * @empty($x) ouvrant bien un bloc.
 */
const BLADE_BRANCHES = [
    'else', 'elseif', 'elsecan', 'elsecannot', 'empty', 'case', 'default',
    'break', 'continue', 'csrf', 'method', 'json', 'js', 'props', 'aware',
    'yield', 'parent', 'include', 'includeIf', 'includeWhen', 'includeUnless',
    'includeFirst', 'extends', 'each', 'php', 'endphp', 'use', 'inject',
    'stack', 'class', 'style', 'checked', 'selected', 'disabled', 'readonly',
    'required', 'vite', 'livewire', 'dd', 'dump', 'lang', 'choice',
];

/** @return list<string> Anomalies trouvées dans une vue. */
function anomaliesBlade(string $chemin): array
{
    $texte = file_get_contents($chemin);
    // Un commentaire Blade peut contenir un @if d'illustration.
    $texte = preg_replace('/\{\{--.*?--\}\}/s', '', $texte);

    $fermants  = array_flip(BLADE_PAIRES);
    $anomalies = [];
    $pile      = [];

    preg_match_all('/@(\w+)\s*(\()?/', $texte, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    foreach ($matches as $m) {
        $nom    = $m[1][0];
        $paren  = isset($m[2]) && $m[2][1] !== -1;
        $ligne  = substr_count($texte, "\n", 0, $m[0][1]) + 1;

        if ($nom === 'empty' && !$paren) {
            continue;   // branche de @forelse
        }
        if (in_array($nom, BLADE_BRANCHES, true)) {
            continue;
        }
        // @section('titre', 'valeur') est auto-fermante.
        if ($nom === 'section' && $paren) {
            $fin = strpos($texte, ')', $m[0][1]);
            if ($fin !== false && str_contains(substr($texte, $m[0][1], $fin - $m[0][1]), ',')) {
                continue;
            }
        }

        if (isset(BLADE_PAIRES[$nom])) {
            $pile[] = [$nom, $ligne];
        } elseif (isset($fermants[$nom])) {
            $attendu = $fermants[$nom];
            if ($pile && end($pile)[0] === $attendu) {
                array_pop($pile);
            } elseif ($pile) {
                $ouvert = end($pile);
                $anomalies[] = "@{$nom} ligne {$ligne} ferme @{$ouvert[0]} ouvert ligne {$ouvert[1]}";
                array_pop($pile);
            } else {
                $anomalies[] = "@{$nom} orphelin ligne {$ligne}";
            }
        }
    }

    foreach ($pile as [$nom, $ligne]) {
        $anomalies[] = "@{$nom} ouvert ligne {$ligne} jamais fermé";
    }

    return $anomalies;
}

test('toutes les vues ont leurs directives équilibrées', function () {
    $vues = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)
    );

    $problemes = [];

    foreach ($vues as $fichier) {
        if (!str_ends_with($fichier->getFilename(), '.blade.php')) {
            continue;
        }

        $relatif = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $fichier->getPathname());

        foreach (anomaliesBlade($fichier->getPathname()) as $anomalie) {
            $problemes[] = "{$relatif} — {$anomalie}";
        }
    }

    expect($problemes)->toBe([], "Vue(s) déséquilibrée(s) :\n" . implode("\n", $problemes));
});

test('le détecteur repère bien une directive non fermée', function () {
    // Un contrôle qui ne peut pas échouer ne protège de rien : on lui soumet
    // le défaut exact qui a produit le 500 des fiches techniques.
    $casse = tempnam(sys_get_temp_dir(), 'blade') . '.blade.php';
    file_put_contents($casse, "@section('content')\n@if(\$x)\n    <div>jamais fermé</div>\n@endsection\n");

    $anomalies = anomaliesBlade($casse);
    unlink($casse);

    expect($anomalies)->not->toBeEmpty();
});

test('le détecteur ne se trompe pas sur forelse et sa branche empty', function () {
    // @empty sans parenthèses est une branche de @forelse, pas une ouverture.
    $correct = tempnam(sys_get_temp_dir(), 'blade') . '.blade.php';
    file_put_contents($correct, "@forelse(\$i as \$x)\n<p>{{ \$x }}</p>\n@empty\n<p>vide</p>\n@endforelse\n");

    $anomalies = anomaliesBlade($correct);
    unlink($correct);

    expect($anomalies)->toBe([]);
});

test('l\'écran des fiches techniques du restaurant s\'ouvre', function () {
    $this->seed(\Database\Seeders\TenantSeeder::class);

    // Le module doit être actif, sinon la route est refusée avant le rendu.
    $prop = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $prop->setAccessible(true);
    $prop->setValue(null, ['restaurant']);

    $chef = User::factory()->create(['role' => 'restaurant_chief', 'is_active' => true]);

    $this->actingAs($chef)
        ->get(route('restaurant.recipes.index'))
        ->assertOk()
        ->assertSee('fiche technique', false);
});
