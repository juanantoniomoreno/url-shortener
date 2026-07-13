#!/bin/sh
set -e

# Ensure var/ is writable by www-data (volume mount overlays host permissions)
chmod -R 777 var/ 2>/dev/null || true

# Only run migrations for the main php-fpm container, not workers
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Waiting for PostgreSQL..."
    until nc -z postgres 5432; do
        echo "[entrypoint] Database not ready yet, retrying in 1s..."
        sleep 1
    done
    echo "[entrypoint] PostgreSQL is ready."

    echo "[entrypoint] Running Doctrine migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec "$@"
