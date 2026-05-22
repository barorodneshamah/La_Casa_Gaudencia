#!/bin/sh
set -e

echo "==> La Casa Gaudencia starting..."

# Railway injects PORT; fall back to 80
export PORT=${PORT:-80}

# Render nginx site config with the actual port
envsubst '${PORT}' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-enabled/default

# Wait for the database to be ready (max 60 s)
echo "==> Waiting for database..."
RETRIES=30
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        echo "ERROR: Database not reachable. Check DATABASE_URL."
        exit 1
    fi
    echo "    Not ready — retrying in 2s ($RETRIES left)..."
    sleep 2
done
echo "==> Database ready."

# Run pending migrations
echo "==> Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Install Symfony assets and importmap vendor files
echo "==> Installing assets..."
php bin/console assets:install --no-interaction
php bin/console importmap:install --no-interaction

# Warm up production cache
echo "==> Warming up cache..."
php bin/console cache:warmup --env=prod --no-interaction

chown -R www-data:www-data var/

echo "==> Starting Nginx + PHP-FPM on port ${PORT}..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf