# APIs et intégrations

L'application expose trois surfaces vers l'extérieur, chacune avec son modèle de
confiance :

| Surface | Authentification | Consommateur |
|---|---|---|
| **API publique** `/api/v1/*` | Aucune | Le site vitrine, et tout tiers |
| **API de reporting** `/api/reporting/*` | Jeton de service | La console business |
| **Mode assistance** `/assistance/enter` | Jeton HMAC signé | Le technicien de la console |

S'y ajoutent la PWA, les notifications push et l'assistant IA.

---

## API publique

`routes/api.php`, préfixe `/api/v1`. **Lecture seule, sans authentification** — elle
ne sert que du contenu destiné à être affiché publiquement.

| Route | Contenu |
|---|---|
| `GET /ping` | Vérification de disponibilité |
| `GET /rooms` | Chambres physiques commercialisables |
| `GET /rooms/{room}` | Détail d'une chambre |
| `GET /room-types` | Regroupement par type |
| `GET /room-types/{roomType}` | Détail d'un type |
| `GET /restaurant/menu` | Carte du restaurant — **si le module est actif** |
| `POST /bookings` | Demande de réservation — throttle `10,1` |

### Ce qui est exposé, et pourquoi

Deux décisions méritent d'être explicites, car elles sont contre-intuitives.

> **Les chambres occupées sont exposées.** Une chambre prise cette semaine se vend
> pour le mois prochain ; la masquer ferait perdre la réservation. Chacune porte son
> état du moment et ses périodes déjà prises (bloc `availability` de
> `RoomResource`) — au site de refuser les bonnes dates dans son calendrier.

> **Les chambres en maintenance ou hors service restent masquées.** Leur
> indisponibilité n'a pas d'échéance : les afficher n'apporterait au client qu'une
> carte sur laquelle aucune date n'est retenable.

C'est la **période demandée**, jamais le statut courant, qui décide de l'acceptation.

### Les demandes de réservation

`POST /api/v1/bookings` crée une réservation au statut `pending`, avec la source
`website` — à confirmer par la réception. Les managers et la réception sont notifiés
en interne et par push
([`WebsiteBookingReceived`](../app/Notifications/WebsiteBookingReceived.php)).

C'est un formulaire public : il est protégé par un throttle contre le spam, et rien
n'est confirmé automatiquement.

> Le site vitrine et la réception passent **tous deux** par
> [`RoomAvailabilityService`](../app/Services/RoomAvailabilityService.php). Sans cela,
> le site promettrait des dates que la réception refuserait.

---

## API de reporting

`/api/reporting/*`, protégée par
[`ValidateReportingToken`](../app/Http/Middleware/ValidateReportingToken.php).

| Route | Contenu |
|---|---|
| `GET /summary` | Résumé 360° |
| `GET /revenue` | Séries d'évolution du revenu |
| `GET /cash-audit` | Audit de caisse |
| `GET /expenses` | Dépenses |
| `GET /invoices` | Factures |
| `GET /finance` | Vue financière consolidée |
| `GET /staff` | Effectifs |
| `GET /customers` | Clients |
| `GET /alerts` | Alertes |

Toutes acceptent un paramètre `period` : `today`, `week`, `month`, `year`.

### Le modèle de confiance

L'appelant présente un en-tête `Authorization: Bearer {REPORTING_SECRET}`. Ce secret
est partagé entre la console et l'établissement, injecté dans le conteneur au
provisioning.

> **Un secret vide désactive l'API** : tout appel est refusé. C'est le comportement
> voulu — mieux vaut une console business vide qu'une API financière ouverte.

### Périmètre

Chaque endpoint renvoie les données de **cet établissement seulement**. L'agrégation
entre établissements et le calcul des tendances se font côté console.

Données financières sensibles : cette API n'est jamais publique.

---

## Mode assistance

[`AssistanceController`](../app/Http/Controllers/AssistanceController.php) —
`GET /assistance/enter?token=…`

Permet à un technicien de la console d'entrer dans l'application pour diagnostiquer
un problème, **sans connaître le mot de passe d'un employé**.

### Fonctionnement

La console signe un jeton HMAC-SHA256 avec `ASSISTANCE_SECRET`, portant le slug de
l'établissement, une référence de session, le nom de l'administrateur et une
expiration. Ce endpoint :

1. vérifie la signature en **comparaison à temps constant** (`hash_equals`) ;
2. vérifie l'expiration ;
3. ouvre une session en se connectant comme administrateur de l'établissement ;
4. marque la session comme « assistance » — bannière visible + audit ;
5. redirige vers le tableau de bord.

> **Aucune authentification préalable n'est requise, et c'est nécessaire** :
> l'administrateur technique n'a pas de compte dans cette base. La confiance vient
> entièrement de la signature du jeton.

Si `ASSISTANCE_SECRET` est vide, l'endpoint refuse tout : le mode assistance n'est
pas activé pour cet établissement.

---

## PWA — application installable

[`PwaController`](../app/Http/Controllers/PwaController.php).

| Route | Contenu |
|---|---|
| `GET /manifest.webmanifest` | Manifeste, construit à partir de l'établissement |
| `GET /pwa/icon/{size}` | Icône générée à la volée (32, 180, 192, 512) |
| `GET /offline` | Page de repli hors ligne |

Ces routes sont **publiques** : le navigateur récupère le manifeste et les icônes
avant même que l'utilisateur soit connecté, sinon l'installation est impossible.

> **Le manifeste est dynamique.** Chaque établissement a son nom et son logo :
> l'application installée doit porter **son** identité. Un manifeste statique
> afficherait le même nom chez tous les clients.

### Le piège de l'extension

La route d'icône est `/pwa/icon/{size}` — **sans extension `.png`**, délibérément :

> Une URL se terminant par `.png` serait interceptée par la règle nginx des fichiers
> statiques (`try_files $uri =404`) et n'arriverait **jamais** jusqu'à Laravel, alors
> que l'icône est générée à la volée. Le type est de toute façon annoncé par
> l'en-tête `Content-Type` et par le champ `type` du manifeste.

Ce genre de panne est invisible en test Laravel — les tests ne passent pas par
nginx. La règle vaut pour toute route générée dont l'URL ressemble à un fichier
statique (`.js`, `.css`, `.png`…).

---

## Notifications

### Deux canaux

Toute notification métier part par la base (in-app) et, si l'utilisateur est abonné,
par **Web Push** ([`WebPushChannel`](../app/Notifications/Channels/WebPushChannel.php)).

`PushSubscription` stocke un abonnement par navigateur et par appareil. La clé
publique VAPID est exposée par `GET /push/vapid-key` pour l'abonnement côté client.

Les clés VAPID **identifient l'éditeur de l'application, pas l'établissement** :
elles sont communes à toute la plateforme.

### Le point d'envoi unique

[`Notifier`](../app/Services/Notifier.php) offre deux garanties, valables pour tous
les modules :

> **Une notification qui échoue ne fait jamais échouer l'action métier.** Push hors
> service, table indisponible : l'incident est journalisé, le travail continue.

> **Les destinataires sont résolus par rôle de façon homogène**, en tenant compte des
> deux systèmes de rôles (colonne héritée et table pivot).

Les comptes désactivés et les doublons sont écartés ; l'auteur d'une action n'est
jamais notifié de son propre geste.

### Les 17 notifications

| Domaine | Notifications |
|---|---|
| Réservations | `NewBookingCreated`, `WebsiteBookingReceived`, `ComplimentaryBookingRequested`, `ComplimentaryBookingApproved` |
| Housekeeping | `HousekeepingRoomsAssigned`, `HousekeepingRoomToInspect`, `HousekeepingIssueReported` |
| Restaurant | `RestaurantOrderAssigned`, `RestaurantOrderSentToKitchen`, `RestaurantOrderReady`, `PantryItemLowStock` |
| Économat | `StockRequisitionSubmitted`, `StockRequisitionUpdated`, `PurchaseOrderUpdated`, `StockItemBelowThreshold` |
| Boutique | `ShopProductLowStock` |
| Discussions | `DiscussionMessageReceived` |

---

## Discussions internes

Module optionnel (`discussions`). Messagerie interne entre membres du personnel :
conversations individuelles ou de groupe, archivage, marquage lu/non lu.

`DiscussionConversation`, `DiscussionMessage`, et un pivot portant `last_read_at`,
`archived_at` et `deleted_at` par participant — chacun gère son propre état de
conversation.

---

## Assistant IA

Module optionnel (`ai`). `POST /ai-chat` —
[`AiAssistantController`](../app/Http/Controllers/AiAssistantController.php).

Adossé à l'API Mistral (`MISTRAL_API_KEY`, `MISTRAL_MODEL`). Le contexte système est
construit à partir du profil de l'utilisateur connecté, et l'historique est tronqué
aux 10 derniers messages.

Sans clé configurée, l'endpoint répond une erreur explicite plutôt que d'échouer
silencieusement.

---

## Pour aller plus loin

- [Configuration](configuration.md) — secrets et clés
- [Hébergement](hebergement.md) — la disponibilité exposée au site
- [Comptabilité](comptabilite.md) — les données remontées au reporting
