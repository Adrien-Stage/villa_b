<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * Pagination commune aux écrans de liste.
 *
 * Une seule taille de page pour toute l'application : passer d'un écran à
 * l'autre ne doit pas changer la densité d'information sous les yeux de la
 * réception. Elle vivait auparavant en dur dans chaque contrôleur, avec quatre
 * valeurs différentes selon l'écran.
 */
trait PaginatesLists
{
    /** Lignes par page, pour tous les écrans de liste. */
    public const PAR_PAGE = 15;

    /**
     * Pagine une collection déjà constituée.
     *
     * Certains écrans reçoivent leurs lignes d'un service qui a déjà agrégé,
     * trié ou fusionné plusieurs sources : il n'y a plus de requête à paginer.
     * On découpe alors la collection en mémoire.
     *
     * Les totaux affichés à côté du tableau doivent être calculés **sur la
     * collection entière**, avant cet appel. Les tirer du paginateur ne
     * donnerait que le total de la page courante — un compte de caisse faux à
     * l'écran est pire que pas de compte du tout.
     *
     * @template T
     * @param  Collection<int, T>  $items
     * @return LengthAwarePaginator<T>
     */
    protected function paginateCollection(
        Collection $items,
        ?Request $request = null,
        string $pageName = 'page'
    ): LengthAwarePaginator {
        $request ??= request();
        $page = max(1, (int) $request->input($pageName, 1));

        $paginateur = new LengthAwarePaginator(
            $items->forPage($page, self::PAR_PAGE)->values(),
            $items->count(),
            self::PAR_PAGE,
            $page,
            [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => $pageName,
            ]
        );

        // Les filtres de l'écran doivent survivre au changement de page, sinon
        // passer à la page 2 d'une recherche renvoie la liste entière.
        return $paginateur->withQueryString();
    }
}
