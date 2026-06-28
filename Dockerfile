# ─────────────────────────────────────────────────────────────────────────────
# HotelixOS — Dockerfile du template établissement
# Base : villa_b (Laravel 12 / PHP 8.2+ / PostgreSQL / Vite)
#
# Ce Dockerfile est partagé entre tous les établissements.
# La configuration spécifique (DB, APP_KEY, TENANT_SLUG…) est injectée
# via les variables d'environnement du docker-compose généré par l'admin.
# ─────────────────────────────────────────────────────────────────────────────

FROM php:8.3-fpm

# ── Dépendances système ───────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        git \
        unzip \
        curl \
        libpq-dev \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        postgresql-client \
    && docker-php-ext-install pdo_pgsql zip gd mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ─────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Node.js (pour Vite build) ─────────────────────────────────────────────────
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Code source ───────────────────────────────────────────────────────────────
WORKDIR /var/www/html

# Copier les manifestes en premier (optimise le cache Docker)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY package.json package-lock.json ./
RUN npm ci --prefer-offline

# Copier tout le reste
COPY . .

# ── Build des assets Vite ─────────────────────────────────────────────────────
RUN npm run build && rm -rf node_modules

# ── Optimisations Laravel ─────────────────────────────────────────────────────
RUN composer dump-autoload --optimize \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# ── Permissions ───────────────────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ── Nginx ─────────────────────────────────────────────────────────────────────
COPY docker/nginx.conf /etc/nginx/sites-available/default

# ── Supervisord (gère nginx + php-fpm) ───────────────────────────────────────
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf

# ── Entrypoint ────────────────────────────────────────────────────────────────
# Le script entrypoint : attend la DB, cache la config, lance supervisord
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]