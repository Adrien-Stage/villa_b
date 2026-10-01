#!/bin/bash
set -e

echo "🏨 MEKA ERP — Démarrage du container établissement"
echo "    Tenant : ${TENANT_SLUG:-inconnu}"
echo "    DB     : ${DB_DATABASE} @ ${DB_HOST}:${DB_PORT}"

# ── Attendre PostgreSQL ───────────────────────────────────────────────────────
echo "⏳ Attente de PostgreSQL..."
MAX=30
COUNT=0
until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${DB_DATABASE}" -q; do
    COUNT=$((COUNT + 1))
    if [ "$COUNT" -ge "$MAX" ]; then
        echo "❌ PostgreSQL non disponible après ${MAX} tentatives. Abandon."
        exit 1
    fi
    echo "   tentative ${COUNT}/${MAX}..."
    sleep 3
done
echo "✅ PostgreSQL disponible."

# ── Générer APP_KEY si absente ────────────────────────────────────────────────
if [ -z "${APP_KEY}" ]; then
    echo "🔑 Génération d'une APP_KEY..."
    APP_KEY=$(php artisan key:generate --show --no-interaction)
    export APP_KEY
fi

# ── Optimisations Laravel ─────────────────────────────────────────────────────
echo "⚙️  Optimisation de la configuration Laravel..."
php artisan config:cache  --no-interaction 2>/dev/null || true
php artisan route:cache   --no-interaction 2>/dev/null || true
php artisan view:cache    --no-interaction 2>/dev/null || true

# ── Migrations + Seeders (exécutées automatiquement) ──────────────────────────
echo "🗄️  Exécution des migrations..."
# Une migration en échec arrête le conteneur. Continuer servirait
# l'application sur une base à moitié migrée : des écrans qui lisent des
# colonnes absentes, des écritures comptables partielles. Un établissement
# arrêté se voit dans la console, qui peut le réépingler sur l'image
# précédente ; un établissement qui tourne de travers ne se voit pas.
if ! php artisan migrate --force --no-interaction 2>&1; then
    echo "❌ Échec des migrations : arrêt du conteneur plutôt que de servir une base à moitié migrée."
    echo "   Corriger la migration, ou réépingler l'établissement sur l'image précédente depuis la console."
    exit 1
fi

# Seeder uniquement si aucun tenant n'existe encore (premier lancement).
# ProductionTenantSeeder crée les rôles RBAC + le tenant réel de cet
# établissement à partir des variables d'environnement (TENANT_SLUG,
# APP_NAME, TENANT_SETTINGS...) — PAS le jeu de données de démo
# "Villa Boutanga" (DatabaseSeeder), réservé au développement local.
TENANT_COUNT=$(php artisan tinker --execute="echo \App\Models\Tenant::count();" 2>/dev/null || echo "0")
if [ "$TENANT_COUNT" = "0" ] || [ -z "$TENANT_COUNT" ]; then
    echo "🌱 Base vide — initialisation du tenant et des rôles..."
    # Sans tenant ni rôles, l'application ne peut ni connecter personne ni
    # appliquer un droit : démarrer quand même masquerait la panne.
    if ! php artisan db:seed --class=ProductionTenantSeeder --force --no-interaction 2>&1; then
        echo "❌ Échec de l'initialisation du tenant et des rôles : arrêt du conteneur."
        exit 1
    fi
else
    echo "📦 Tenant déjà initialisé — seeders d'installation ignorés."
fi

# Référentiel des rôles : rejoué à CHAQUE démarrage, contrairement aux seeders
# d'installation ci-dessus. C'est ce qui permet à un rôle ajouté au catalogue
# (App\Support\RoleCatalog) d'arriver sur un établissement déjà en service.
# L'opération est idempotente : aucun doublon, aucun rattachement utilisateur
# n'est touché.
echo "👥 Synchronisation des rôles..."
php artisan roles:sync --no-interaction 2>&1 || {
    echo "⚠️  Échec de la synchronisation des rôles."
}

# ── Lien symbolique storage (nécessaire pour les logos/images uploadés) ───────
if [ ! -e /var/www/html/public/storage ]; then
    echo "🔗 Création du lien storage..."
    php artisan storage:link --no-interaction 2>&1 || true
fi

# ── Permissions storage ───────────────────────────────────────────────────────
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "🚀 Lancement des services (nginx + php-fpm)..."
exec /usr/bin/supervisord -n