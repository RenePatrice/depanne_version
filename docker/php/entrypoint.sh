#!/bin/sh
# Point d'entrée commun à tous les conteneurs applicatifs.
# Seul le service `app` passe RUN_MIGRATIONS=true : il installe les dépendances,
# construit les assets, migre et sème (une seule fois). Les autres services
# (file, planificateur, Reverb) démarrent une fois `app` prêt — les dépendances
# sont déjà là (volumes partagés) — et se contentent de lancer leur commande.
set -e
cd /var/www/html

# Dépendances PHP.
if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] composer install…"
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    # Dépendances + build front (une fois).
    if [ ! -d node_modules ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
        echo "[entrypoint] npm ci…"
        npm ci
    fi
    if [ ! -f public/build/manifest.json ]; then
        echo "[entrypoint] npm run build…"
        npm run build
    fi

    php artisan config:clear || true
    php artisan storage:link || true

    echo "[entrypoint] migrations…"
    php artisan migrate --database=pgsql_migrations --force

    # Jeu de démonstration : une seule fois (sentinelle dans storage, monté).
    if [ ! -f storage/.docker-seeded ]; then
        echo "[entrypoint] db:seed (première fois)…"
        php artisan db:seed --force && touch storage/.docker-seeded
    fi
fi

exec "$@"
