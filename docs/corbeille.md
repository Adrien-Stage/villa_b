# Corbeille de l'établissement

Ce qu'efface un bouton « Supprimer » dans l'application n'est pas perdu. La
fiche passe par la corbeille. Personne dans l'hôtel ne voit cette corbeille,
l'administrateur de l'hôtel non plus. Seul l'administrateur TECH de la console
la consulte, restaure ou purge.

## Ce qui y entre

Une trentaine de suppressions passent par la corbeille : articles, catégories,
unités, dépôts, fournisseurs, bordereaux de livraison, chambres et leurs
photos, types de chambres, tarifs, postes de fiche technique, packs, politiques
d'annulation, partenaires, prestations du folio et du catalogue, plats,
catégories et garde-manger du restaurant, fiches techniques, comptages,
produits de la boutique, dépenses, quarts, affectations du planning,
départements, exceptions de droits, conversations, comptes.

Un modèle y passe en portant le trait
[`VaALaCorbeille`](../app/Models/Concerns/VaALaCorbeille.php). **Toute nouvelle
suppression doit le porter.**

Retirer une ligne pendant la modification d'un document n'y passe pas. Par
exemple, retirer une ligne d'une demande d'achat est une modification : elle
est tracée au journal.

## Comment

[`Corbeille`](../app/Services/Corbeille.php) agit en deux temps, autour de
l'effacement.

1. **Juste avant** (`deleting`), la fiche est photographiée telle qu'elle est en
   base. La photographie comprend trois choses :
   - les lignes que la base efface **en cascade** avec la fiche, et les leurs ;
   - les lignes qu'elle **détache** (clé mise à nul), pour les rattacher à la
     restauration ;
   - ses **fichiers**, déclarés dans
     [`Registre`](../app/Support/Corbeille/Registre.php).

   Ce qui part avec la fiche se lit dans les clés étrangères de la base, pas
   dans une liste tenue à la main.
2. **Une fois l'effacement réussi** (`deleted`), la photographie entre dans
   `trash_entries`. Les fichiers vont dans `corbeille/{entrée}/` sur leur
   disque. Si la base refuse l'effacement, rien n'entre dans la corbeille.

Chaque entrée garde :
- le type lisible ;
- le service ;
- le libellé ;
- l'auteur et la date.

## Restaurer, purger

La restauration est faite **par l'application**. Elle remet :
- la fiche, avec son identifiant d'origine ;
- ce qui était parti en cascade ;
- les liens, si la ligne n'est pas rattachée ailleurs depuis ;
- les fichiers.

Elle est refusée, avec un motif, dans deux cas :
- un élément identique (même nom, même code) a été recréé depuis ;
- l'élément dépend d'un autre qui est lui-même dans la corbeille. Exemple : un
  article et sa catégorie. Il faut alors restaurer d'abord la catégorie.

La purge efface pour de bon l'entrée et ses fichiers.

La restauration et la purge sont tracées au journal (`corbeille_restauration`,
`corbeille_purge`) avec le nom transmis par la console.

## API de la console

Elle est gardée par le jeton d'orchestration (`orchestration.token`) :

| Action | Route |
|---|---|
| Lister, filtrer | `GET /api/corbeille?type=&module=&auteur=&du=&au=&q=&etat=dans\|restauree\|tout&page=` |
| Restaurer | `POST /api/corbeille/{id}/restaurer` avec `auteur` |
| Purger | `DELETE /api/corbeille/{id}` avec `auteur` |

La liste renvoie aussi les valeurs de filtre existantes : types, services,
auteurs, états.
