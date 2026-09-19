<?php

namespace App\Support;

use App\Models\User;
use App\Services\PermissionResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Restreint une requête à ce que la portée d'un droit laisse voir.
 *
 * Le département cessait d'accorder des rôles sans pour autant restreindre
 * quoi que ce soit : il ne faisait plus rien. C'est ici qu'il reprend un
 * effet, celui qu'on attend de lui — borner les données, pas ouvrir des
 * portes.
 *
 * Sans portée déclarée, la requête n'est pas touchée : rien ne se restreint
 * tant que personne ne l'a demandé depuis la matrice.
 */
class DepartmentScoping
{
    /**
     * @param  Builder  $requete            requête à borner
     * @param  string   $colonneDepartement colonne portant le département de la ligne
     * @param  string   $colonneAuteur      colonne portant la personne à qui la ligne appartient
     */
    public static function apply(
        Builder $requete,
        ?User $utilisateur,
        string $permission,
        string $colonneDepartement = 'department_id',
        string $colonneAuteur = 'user_id',
    ): Builder {
        if ($utilisateur === null) {
            return $requete->whereRaw('1 = 0');
        }

        $portee = app(PermissionResolver::class)->scopeFor($utilisateur, $permission);

        if ($portee === PermissionScope::ETABLISSEMENT) {
            return $requete;
        }

        if ($portee === PermissionScope::PROPRE) {
            return $requete->where($requete->getModel()->getTable() . '.' . $colonneAuteur, $utilisateur->id);
        }

        // Département : une personne sans rattachement ne voit qu'elle-même.
        // La laisser tout voir ferait de l'absence de département un passe-droit.
        if ($utilisateur->department_id === null) {
            return $requete->where($requete->getModel()->getTable() . '.' . $colonneAuteur, $utilisateur->id);
        }

        return $requete->where(
            $requete->getModel()->getTable() . '.' . $colonneDepartement,
            $utilisateur->department_id
        );
    }
}
