# Preuves de l’audit du 5 novembre 2026

Retour au [rapport](../2026-11-05-audit-metier-securite.md).

Les fichiers [APP](AuditCibleAppTest.php) et [ERP](AuditCibleErpTest.php) contiennent les cinq reproductions locales. **Ils attendent le comportement vulnérable observé** : leur réussite établit le constat, pas une protection. Ils sont hors des suites applicatives. Après correction, écrire des tests de régression qui attendent les refus appropriés.

Résultats condensés : [APP](resultat-app.txt), [ERP](resultat-erp.txt). Les noms de cas longs sont tronqués par Pest dans ces sorties ; les familles et causes sont détaillées au §5 du rapport.

## Protocole exécuté

- Copier dans `/tmp/wetchah-audit-20261105/wetchah_app` et `wetchah_erp` les dossiers `app`, `config`, `routes`, `resources`, `tests`, `lang` et `demo` lorsqu’ils existent ; les migrations, factories et seeders ; `artisan`, `phpunit.xml`, `composer.json`, `composer.lock`, `bootstrap/app.php` et `bootstrap/providers.php`.
- Créer des dossiers vides pour `bootstrap/cache`, `public`, `storage/framework/{views,sessions,cache/data}`, `storage/logs`, `storage/app/{private,public}` et `pest-cache`. Créer un `.env` contenant uniquement un commentaire. Aucun environnement, cache ou fichier de données réel n’est copié.
- Copier chaque preuve correspondante sous `tests/Feature/AuditCibleTest.php` de la copie temporaire.
- Exécuter la commande ci-dessous pour chaque dépôt. L’image était déjà disponible localement ; elle fournit PHP 8.4.27 avec SQLite. Son point d’entrée applicatif est remplacé : aucun démarrage, migration ou seeding de production.

```bash
audit_repo=wetchah_app
docker run --rm --network none --cap-drop ALL \
  --security-opt no-new-privileges --user 1000:1000 \
  -v "/tmp/wetchah-audit-20261105/$audit_repo:/audit" \
  -v "/home/blackcode/Documents/wetchah/$audit_repo/vendor:/audit/vendor:ro" \
  -v "/tmp/wetchah-audit-20261105/$audit_repo/pest-cache:/audit/vendor/pestphp/pest/.temp" \
  -w /audit -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
  --entrypoint php pms-app:latest vendor/bin/pest \
  tests/Feature/AuditCibleTest.php --no-coverage --colors=never
```

La clé ci-dessus est **fictive**, réservée à la copie de test. Pour l’ERP, remplacer seulement `audit_repo` par `wetchah_erp`.

Le dernier passage comprenant les tests existants remplaçait le chemin de test par `--filter='…' --compact`, avec les filtres suivants :

```text
APP : AuditCible|Permission|DroitsBornes|OrchestrationAccounts|AdminAccounts|GestionDesComptes|DutySegregation|StockCountFreeze|ResponsableDesAchats|ControleurDeGestion|BookingCreationFlow|HousekeepingWorkflow|RestaurantsMultiples|PlanningDesQuarts|ServiceTechnique|PilotageTest
ERP : AuditCible|PermissionMatrix|TenantUserActions|TenantSecrets|AdminTenant|OwnerRegistry
```

Codes de sortie : APP **1**, ERP **0**. Résultats du dernier passage : APP 298 réussites / 12 échecs, ERP 81 réussites. Les cinq preuves sont incluses dans les réussites. Les essais initiaux comportaient des avertissements dus à l’absence de `.env` fictif et au cache Pest non inscriptible ; ils ont été résolus dans la préparation, sans modifier le produit ni désactiver un contrôle.
