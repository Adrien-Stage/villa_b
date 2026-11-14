# Cadrage — Bar, boissons et dépôt du restaurant

Date : 14 novembre 2026. Document de discussion ; les propositions non approuvées ne sont pas des décisions de mise en œuvre.

## Problème et résultat attendu

Le bar doit gérer ses boissons et ses ingrédients, notamment l'ouverture des bouteilles et la production de cocktails et mocktails, tout en participant à une commande restaurant commune et à une seule addition. Une consommation standard correspond à 0,05 L. Le barman reçoit les boissons à préparer ; le caissier conserve la facturation et l'encaissement de l'addition.

Le résultat attendu est qu'une bouteille présente dans le dépôt du bon bar puisse être ouverte, que ses consommations deviennent disponibles au même endroit, puis qu'une vente ou une recette diminue correctement le stock et laisse une trace. L'exemple attendu est une commande contenant une côte de porc et un verre de rhum, envoyée à la cuisine et au bar, avec une seule addition.

## Ce qui existe déjà dans le code

- L'écran Bar du restaurant affiche les boissons des commandes transmises, avec l'action « Prêtes ». Chaque ligne est orientée vers cuisine ou bar et un bar peut être lié à son restaurant. Sources : `app/Http/Controllers/RestaurantKitchenController.php`, `app/Models/RestaurantCustomerOrderItem.php`, `resources/views/restaurant/bar/index.blade.php`.
- La carte accepte des plats et des boissons ; la facturation restaurant peut encaisser une commande unique ou la porter sur un séjour. Sources : `app/Http/Controllers/RestaurantMenuController.php`, `app/Http/Controllers/RestaurantBillingController.php`.
- Il existe un mécanisme de dépôts de service, leur approvisionnement et leurs inventaires. Un dépôt Bar peut être synchronisé avec un restaurant dont le service bar est actif. L'état réel de déploiement, la migration et les dépôts configurés pour chaque établissement restent à vérifier. Sources : `app/Models/PointOfSale.php`, `app/Services/StockRequisitionService.php`, `database/migrations/2026_11_14_100000_add_point_of_sale_id_to_service_stores_table.php`.
- L'économat sait décrire et ouvrir des conditionnements d'un article ; il conserve le même stock total dans son unité de base et suit les unités fermées et le vrac. Son autre opération de découpe transfère des portions du magasin central vers le garde-manger du restaurant. Ces mécanismes ne démontrent pas qu'une bouteille déjà dans un dépôt Bar devient automatiquement des doses dans ce dépôt ; aucune règle spécifique 0,05 L n'a été identifiée dans les fichiers examinés. Sources : `app/Services/Conditionnements.php`, `app/Services/StockService.php`, `app/Services/StockCutService.php`, `app/Http/Controllers/Economat/StockItemTransformationController.php`.
- Les fiches techniques restaurant et leur déstockage par recette utilisent le garde-manger du restaurant (`RestaurantPantryItem`). Le dépôt de service Bar utilise un autre stock (`ServiceStoreStock`). La liaison entre vente/recette de boisson et déstockage automatique du dépôt Bar n'est pas démontrée par ce parcours et constitue une intégration à concevoir. Sans fiche active, le déstockage des ingrédients de commande est actuellement ignoré. Sources : `app/Services/RestaurantStockService.php`, `app/Services/ServiceStoreService.php`.
- Aujourd'hui le restaurant déduit les ingrédients lors de la transmission ou création de commande ; il peut signaler un manque et autoriser un stock négatif. Le dépôt de service refuse quant à lui les sorties supérieures au stock disponible. Leur rapprochement exige un choix explicite. Source : services RestaurantStockService et ServiceStoreService.
- Les droits permettent déjà de consulter le bar et de marquer les boissons prêtes ; le catalogue consulté ne définit pas encore de rôle dédié « Barman ». Sources : `app/Support/PermissionCatalog.php`, `app/Support/RoleCatalog.php`.

Ces constats proviennent de la lecture du code ; les migrations appliquées, les réglages de production et l'expérience de bout en bout ne sont pas vérifiés ici.

## Options étudiées

1. **Tout gérer depuis la caisse existante.** Effort initial plus faible, mais la caisse porterait l'ouverture des bouteilles, les recettes et la préparation, alors que ce sont des tâches opérationnelles du bar. Reporte la gestion d'un dépôt et les droits du barman.
2. **Étendre l'espace Bar existant et conserver la caisse commune — recommandation.** Effort intermédiaire : réutilise le circuit de commande, de bar et de facturation ; il faut relier proprement dépôt, transformation et recettes au système de stock et à la comptabilité. Convient au restaurant avec commandes mixtes ; le parcours exact pour un établissement avec bar seul et sans salle reste à vérifier.
3. **Créer un bar et une caisse entièrement séparés.** Effort le plus élevé : autonomie éventuelle pour un point de vente indépendant, mais il faut alors définir transferts, caisse et rapprochement des commandes mixtes. À réserver à un besoin établi de caisses ou de points de vente distincts.

## Proposition de première version

- Dans la tuile Restaurant, enrichir l'espace Bar actuel avec la file de préparation, le catalogue des boissons vendables, le dépôt propre au restaurant/bar sélectionné, les transformations, les fiches techniques et un suivi des ventes et mouvements. L'organisation exacte des onglets reste à concevoir à partir de l'interface en place.
- Ajouter le rôle « Barman » dans le système de rôles et permissions existant, avec visibilité limitée aux restaurants/bar auxquels il est affecté : préparation des boissons, consultation du stock, ouverture de bouteille et consultation/gestion des fiches suivant les droits accordés. Proposition : le responsable de restaurant gère les paramètres sensibles et les validations ; le caissier encaisse. Les autorisations précises de création/modification, de correction de stock et de consultation des prix/coûts restent à arbitrer.
- En cas de stock insuffisant, alerter et laisser poursuivre avec un stock négatif, comme demandé ; le dépôt de service actuel refuse les sorties insuffisantes, donc cette règle devra être adaptée de façon explicite et contrôlée.
- Approvisionner le dépôt Bar depuis l'économat via les mécanismes existants. Pour chaque restaurant, identifier sans ambiguïté le dépôt et les articles ; ne pas faire coexister deux stocks indépendants représentant la même marchandise sans rapprochement.
- Gérer la vente de bouteilles entières, les consommations de 0,05 L et les cocktails/mocktails. Une fiche technique rattache une boisson vendue à ses ingrédients, quantités et rendement ; les ventes de ces boissons doivent sortir les ingrédients du bon dépôt. Les produits simples sans mélange ont aussi besoin d'un lien explicite avec le stock : une vente sans fiche ou lien direct ne doit pas être présentée comme suivie.
- Assurer l'unicité des mouvements lors de la commande, des répétitions, annulations, corrections et retours ; distinguer une annulation avant préparation d'une boisson déjà préparée ou servie, qui peut devenir une perte. Une répétition de requête ne doit pas déstocker deux fois.
- Rattacher le suivi des ventes au point de vente, au produit et à la période, et garder le paiement et le journal d'encaissements communs à la commande. Prévoir le rattachement aux registres et la valorisation comptable sans double charge.
- Conserver inventaires, écarts, pertes et historique des transformations. Si les ventes diminuent désormais le stock en continu, l'inventaire ne doit comptabiliser que l'écart restant entre stock théorique et stock physique, et non reconstituer une seconde fois toutes les ventes.

## Exemple de transformation

Une bouteille de 0,75 L donne théoriquement 15 consommations de 0,05 L ; une bouteille de 0,70 L en donne 14. Pour un rhum donné : départ 3 bouteilles fermées et 0 dose ouverte ; ouverture de 1 bouteille de 0,75 L : 2 bouteilles fermées et l'équivalent de 15 doses disponibles dans le même dépôt ; vente d'un verre de 0,05 L : 2 bouteilles fermées et l'équivalent de 14 doses. Le produit ne doit pas être compté deux fois lors du passage de fermé à ouvert, et l'ouverture conserve la valeur totale hors perte constatée.

La consommation vendue au verre est de 50 mL. Une fiche technique de cocktail ou de mocktail déstocke le volume exact de chaque ingrédient. Le verre et la recette puisent dans le même stock ouvert. Un suivi en millilitres permet d'afficher le volume restant et son équivalent en verres complets.

## Historique par bouteille ouverte

Chaque ouverture crée une fiche pour la bouteille dans son dépôt. La fiche indique le volume à l'ouverture, la date d'ouverture et le volume restant. Chaque mouvement indique l'heure, la quantité et son origine. Une vente au verre renvoie à la ligne de commande. Un prélèvement pour une recette renvoie aussi à la boisson préparée et à sa fiche technique. La quantité prélevée reste consultable dans l'historique après une modification de la recette.

Exemple pour une bouteille de rhum de 75 cl ouverte et utilisée pour une même commande.

| Mouvement | Volume retiré | Volume restant | Origine |
| --- | ---: | ---: | --- |
| Ouverture | 0 cl | 75 cl | Ouverture de la bouteille au dépôt Bar |
| Vente au verre | 5 cl | 70 cl | Ligne « Verre de rhum » |
| Prélèvement pour un cocktail | 2 cl | 68 cl | Ligne du cocktail et fiche technique associée |

Les 7 cl sortis sont expliqués par les deux lignes. Si l'inventaire mesure 67 cl alors que la fiche affiche 68 cl, le manquant à expliquer est de 1 cl. La règle d'affectation des prélèvements lorsque plusieurs bouteilles de la même référence sont ouvertes reste à définir. Un prélèvement non couvert par le stock disponible doit faire apparaître le manque signalé, avec la quantité effectivement attribuée à chaque bouteille.

## Cas à traiter

- Vente d'une bouteille entière et vente d'un verre d'une même référence ; ouverture uniquement sur action autorisée, sauf décision contraire.
- Cocktail et mocktail à plusieurs ingrédients, y compris ingrédients partagés avec la cuisine : chaque ingrédient sort du lieu qui le détient réellement.
- Plusieurs restaurants/bars et dépôts : ni mouvement ni visibilité croisée non autorisée.
- Stock insuffisant, concurrence de commandes, transaction interrompue, correction ou annulation d'un bon et inventaire en cours.
- Bouteille cassée, boisson offerte, perte, dose partielle et différence physique à l'inventaire : tracer le motif et éviter de présenter une perte comme une vente.
- Changements de recette/prix après commande : conserver suffisamment d'informations sur la composition et les quantités effectivement déstockées pour justifier et corriger la commande sans recalculer l'historique avec la recette modifiée.
- Transformation et transfert interne ne sont pas une vente ni une consommation finale. Vérifier la valorisation et les écritures de stock avec la comptabilité, en particulier pour les stocks Bar distincts du garde-manger cuisine.

## Décisions et points ouverts

- **Confirmé par l'utilisateur** — Espace Bar pour les transformations et fiches techniques, rôle Barman avec tickets boissons, addition validée par la caisse restaurant, alerte en cas de manque avec poursuite de la commande. Le verre vendu à l'unité fait 5 cl. La recette déstocke sa quantité exacte. Chaque bouteille ouverte affiche les prélèvements et leur origine.
- **Proposition à discuter :** extension de l'écran Bar existant plutôt qu'un module/caisse autonome ; suivi canonique du volume ouvert en mL, sans perte de l'équivalent en doses ; déclenchement du stock à la transmission de la commande pour rester cohérent avec le restaurant actuel.
- **Ouverts** — Droits du barman et du responsable, emplacement des ingrédients partagés, affectation des prélèvements entre bouteilles ouvertes d'une même référence, gestion des pertes, offres et annulations, règles de comptabilité et d'inventaire, cas de plusieurs bars physiques dans un restaurant.

## Vérification peu coûteuse avant construction

Sur des données de test uniquement, configurer un restaurant avec un dépôt Bar, une bouteille de rhum de 0,75 L, une dose à 0,05 L, une fiche de cocktail et une côte de porc. Vérifier qu'une commande mixte crée un seul bon et une addition, envoie chaque ligne au bon poste, et que transformation + vente diminuent seulement le bon dépôt, une fois, avec conservation de la valeur. Vérifier ensuite une annulation avant préparation, une perte après préparation et un inventaire. Échec à résoudre avant généralisation : le stock décompté provient du garde-manger plutôt que du dépôt Bar, ou la valeur/quantité est comptée deux fois. Il s'agit d'une expérience proposée, non encore réalisée.

Pour la traçabilité, vérifier que la fiche de la bouteille ouverte affiche deux mouvements liés à la commande, 5 cl pour le verre et 2 cl pour la recette, et un volume restant de 68 cl. Après changement de la recette, la fiche de la bouteille doit toujours afficher le prélèvement initial de 2 cl.

## Suite proposée

1. Définir les droits du barman et la règle d'affectation des prélèvements quand plusieurs bouteilles sont ouvertes.
2. Vérifier les migrations/configurations des restaurants et dépôts dans l'environnement visé.
3. Concevoir un seul registre cohérent de quantités et de valeur pour le dépôt Bar et les recettes.
4. Détailler les écrans et le plan d'intégration, puis réaliser et tester le parcours complet après accord sur le périmètre.

## Parcours implémenté

1. Dans **Paramètres › Restaurant**, cochez le service **Bar** pour le restaurant concerné. Affectez le rôle **Barman** à un membre de son équipe.
2. Dans **Restaurant › Bar › Dépôt et bouteilles**, demandez un approvisionnement à l’économat. Sélectionnez une bouteille fermée, saisissez sa contenance en mL et apposez le numéro affiché sur la bouteille.
3. Dans **Carte et fiches**, associez les boissons de la carte à leurs articles d’économat. Le verre utilise 50 mL. Un cocktail ou un mocktail utilise les quantités de sa fiche pour une boisson, en mL ou dans l’unité de stock. Une boisson existante qui n’a pas de fiche Bar conserve le circuit de stock antérieur.
4. Transmettez la commande depuis le restaurant. Le ticket **Préparation** indique les boissons, les doses et les numéros des bouteilles à utiliser. Pour une même référence, le calcul commence par la bouteille disponible ouverte la première, puis passe à la suivante si nécessaire. L’addition est réglée à la caisse habituelle.
5. Depuis la fiche d’une bouteille, consultez les mouvements et le volume restant. En cas de manque, la commande est transmise avec une alerte. Le volume sans bouteille identifiée s’affiche à part et le barman peut le rattacher à la bonne bouteille. Une annulation avant préparation restitue la quantité si un relevé physique n’a pas déjà remplacé le volume théorique. Une boisson signalée prête conserve sa consommation.
6. Le chef de cuisine ou le responsable du restaurant peut saisir le volume mesuré d’une bouteille ouverte et le motif d’un écart. Le stock en bouteilles fermées et les autres unités suivent l’inventaire de l’économat. L’ouverture conserve la valeur en stock. Les consommations, retours et écarts alimentent les écritures des dépôts de service.

L’écran **Ventes** totalise les boissons des additions réglées ou reportées sur un séjour. Les restaurants sont isolés par affectation. Le réglage Bar préserve le dépôt et son historique lorsqu’il est désactivé.
