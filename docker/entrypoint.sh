#!/bin/bash
set -e

echo "🏨 HotelixOS — Démarrage du container établissement"
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

# ── Permissions storage ───────────────────────────────────────────────────────
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

echo "🚀 Lancement des services (nginx + php-fpm)..."
exec /usr/bin/supervisord -n