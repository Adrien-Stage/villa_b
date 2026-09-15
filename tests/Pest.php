<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        // Les vues appellent @vite pour leurs feuilles de style et scripts.
        // Hors d'un serveur de développement, Laravel va chercher le manifeste
        // produit par « npm run build » — absent d'un dépôt fraîchement cloné,
        // puisque public/build et public/hot sont tous deux ignorés par git.
        // Chaque test rendant une vue échouait alors en 500.
        //
        // withoutVite() substitue une implémentation neutre : les tests du
        // back-end cessent de dépendre d'une compilation d'assets. Celle-ci
        // reste vérifiée là où elle compte — le Dockerfile exécute
        // « npm run build », et une compilation cassée fait échouer l'image.
        $this->withoutVite();

        // TenantModules met en cache la liste des modules actifs dans une
        // propriété statique, que dix fichiers de tests écrasent par réflexion
        // pour activer ce dont ils ont besoin. Un seul la remettait à zéro :
        // le reste fuitait sur les tests suivants, et le résultat d'un fichier
        // dépendait de ceux exécutés avant lui.
        //
        // On repart donc d'une ardoise vierge avant chaque test. Un test qui a
        // besoin d'un module l'active explicitement — c'est aussi ce que fait
        // l'établissement en production.
        activerModules([]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Active exactement ces modules métier pour le test en cours.
 *
 * TenantModules lit normalement la variable d'environnement TENANT_MODULES,
 * injectée par l'ERP au provisioning, et met le résultat en cache statique.
 * En test, on écrit directement dans ce cache : c'est le seul moyen de faire
 * varier la configuration d'un établissement d'un cas à l'autre.
 */
function activerModules(array $modules): void
{
    $cache = new ReflectionProperty(\App\Support\TenantModules::class, 'enabled');
    $cache->setAccessible(true);
    $cache->setValue(null, $modules);
}
