#!/bin/sh
set -e

echo "==> La Casa Gaudencia starting up..."

# Railway injects PORT; default to 8080
export PORT=${PORT:-8080}

# Substitute ${PORT} into the nginx config
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# Wait for the database to be ready (max 60 seconds)
echo "==> Waiting for database..."
RETRIES=30
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        echo "ERROR: Database not reachable after 60 seconds. Check DATABASE_URL."
        exit 1
    fi
    echo "    Database not ready — retrying in 2s ($RETRIES retries left)..."
    sleep 2
done
echo "==> Database is ready."

# Run pending migrations
echo "==> Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Install Symfony assets and importmap vendor files
echo "==> Installing assets..."
php bin/console assets:install --no-interaction
php bin/console importmap:install --no-interaction

# Warm up the production cache
echo "==> Warming up cache..."
php bin/console cache:warmup --env=prod --no-interaction

# Ensure var/ is writable by www-data (php-fpm user)
chown -R www-data:www-data var/

echo "==> Starting Nginx + PHP-FPM on port ${PORT}..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf