# Technique (maintenance)

Module optionnel (`technique`), activé depuis la console Wetchah (ERP) comme les
autres : la clé `technique` figure dans `TENANT_MODULES`. Sans elle, aucun écran,
aucun bouton, et un signalement du housekeeping ne crée pas de demande.

Le service technique répare l'hôtel : plomberie, électricité courant fort (CFO) et
courant faible (CFA), froid, climatisation et ventilation, menuiserie, serrurerie,
maçonnerie et peinture, équipements de cuisine et de buanderie.

## Rôles

| Rôle | Niveau | Ce qu'il fait |
|---|---|---|
| Responsable technique (`tech_manager`) | 3, inclut le technicien | Voit tout, affecte et requalifie, règle les spécialités, vise les réquisitions du service |
| Technicien de maintenance (`technician`) | 4 | Prend une demande libre, intervient, porte les pièces, termine |
| Tout le personnel | — | Demande une intervention, suit les siennes, rouvre ou annule |

Les spécialités d'un technicien (`technician_specialties`) orientent l'alerte et
l'affectation : une demande de plomberie alerte le responsable et les plombiers ; une
demande « à qualifier » (ou d'une spécialité que personne n'a) alerte toute l'équipe.

## La demande d'intervention

[`MaintenanceRequest`](../app/Models/MaintenanceRequest.php), référence `DIT-AAAA-NNNN`.
Le cycle est porté par [`MaintenanceService`](../app/Services/MaintenanceService.php) :

```
open (signalée) ──affecter / prendre──▶ assigned ──commencer──▶ in_progress ──terminer──▶ done
      └──────────────── annuler (tant que rien n'a commencé) ──────────────▶ cancelled
done ──rouvrir (7 jours, par le demandeur ou son chef)──▶ assigned (ou open)
```

- **Où** : une chambre, ou un lieu décrit (hall, cuisine, piscine…).
- **Pour quel service** : celui du demandeur (`StockRequisition::DEPARTMENTS`).
- **Priorité** : urgente, haute, normale, basse ; le tableau trie l'urgent d'abord.
- **Historique** : chaque étape (`maintenance_request_events`) dit qui, quand, et ce
  qui a été noté.
- **Alertes** ([`MaintenanceRequestNotification`](../app/Notifications/MaintenanceRequestNotification.php)) :
  signalée → responsable et techniciens de la spécialité ; affectée → le technicien ;
  terminée → le demandeur (et l'encadrement housekeeping pour un signalement) ;
  rouverte → le technicien et le responsable.

### La chambre

À la demande, une chambre peut être **retirée de la vente** (statut `maintenance`),
sauf si un client y loge. À la fin (ou à l'annulation), elle repasse au ménage
(`dirty`), ou reste occupée si le client est toujours là ; une autre intervention qui
la bloque la garde indisponible. Rouverte, elle redevient indisponible.

### Le housekeeping

`POST /housekeeping/{room}/issue` : le signalement d'un agent devient une
demande (source `housekeeping`, priorité haute, spécialité à qualifier), liée à
l'affectation de ménage. Le tableau de la gouvernante montre la demande et son état ;
à la fin de l'intervention, l'affectation reprend.

## Pièces et dépôt technique

Le service a son dépôt : un dépôt de service (`ServiceStore`) du service
`technique`, créé par l'économe et alimenté par les réquisitions livrées (ou un
[classeur Excel](economat-et-boutique.md#classeur-excel-dun-magasin) importé).

Une intervention porte les pièces qu'elle emploie : sortie du dépôt
(`ServiceStoreMovement::SOURCE_INTERVENTION`, `source_id` = la demande), au coût moyen
du dépôt. Au grand livre (night audit, `LedgerPostingService::postServiceStoreStock`) :

```
Pièce employée   D 603x [centre du service demandeur]   C 3x
```

Le centre est celui du service où l'on a réparé : hébergement pour une chambre,
restauration pour la cuisine.

## Réquisitions

La technique est un service demandeur de l'économat (`technique` dans
`StockRequisition::DEPARTMENTS`) : le technicien demande, le responsable technique vise,
l'économe livre au dépôt technique.

## Écrans

| Écran | Chemin | Droit |
|---|---|---|
| Tableau technique | `/technique` | `technique.voir` |
| Demandes d'intervention | `/technique/demandes` | `technique.demandes.voir` (les siennes, ou tout avec `technique.voir`) |
| Demander une intervention | `/technique/demandes/nouvelle` | `technique.demandes.creer` |
| Fiche d'une demande | `/technique/demandes/{demande}` | idem ; actions : `assign`, `take`, `start`, `parts`, `complete`, `reopen`, `cancel` |
| Équipe & spécialités | `/technique/equipe` | `technique.equipe.voir` / `.modifier` |
| Dépôt technique | `/technique/depot` | `technique.depot.voir` |

## À venir

La maintenance préventive : plans récurrents par équipement (filtres de climatisation,
groupe électrogène…), qui créeront les demandes à échéance.
