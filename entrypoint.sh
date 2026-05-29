#!/bin/sh
set -e

echo "==> La Casa Gaudencia starting..."

# Railway injects PORT; fall back to 80
export PORT=${PORT:-80}

# Render nginx site config with the actual port
envsubst '${PORT}' < /etc/nginx/sites-available/default.template > /etc/nginx/sites-enabled/default

# Wait for the database to be ready (max 60 s)
echo "==> Waiting for database..."
echo "    DATABASE_URL: $DATABASE_URL"
RETRIES=30
until php -r "
\$url = getenv('DATABASE_URL');
if (!\$url) { fwrite(STDERR, 'DATABASE_URL is not set'); exit(1); }
\$parsed = parse_url(\$url);
\$dsn = 'mysql:host='.\$parsed['host'].';port='.(\$parsed['port'] ?? 3306).';dbname='.ltrim(\$parsed['path'], '/');
try {
    new PDO(\$dsn, \$parsed['user'], \$parsed['pass'], [PDO::ATTR_TIMEOUT => 5]);
    exit(0);
} catch (Exception \$e) {
    fwrite(STDERR, \$e->getMessage());
    exit(1);
}
" 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        echo "ERROR: Database not reachable. Check DATABASE_URL."
        exit 1
    fi
    echo "    Not ready — retrying in 2s ($RETRIES left)..."
    sleep 2
done
echo "==> Database ready."

# Generate JWT keypair if keys are missing (they are gitignored)
echo "==> Checking JWT keys..."
mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
    echo "    Generating JWT keypair..."
    php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction
    echo "    JWT keypair generated."
else
    echo "    JWT keys already exist."
fi

# Sync database schema (idempotent — safe to run on every restart)
echo "==> Syncing database schema..."
php bin/console doctrine:schema:update --force --no-interaction || echo "WARNING: schema:update failed — app will start but some pages may error until DB is in sync"

# Seed default admin/staff accounts (idempotent — re-hashes password on every start)
echo "==> Seeding admin accounts..."
php bin/console app:seed-admin --no-interaction

# Install Symfony assets
echo "==> Installing assets..."
php bin/console assets:install --no-interaction
php bin/console importmap:install --no-interaction || echo "Warning: importmap:install failed, continuing..."

# Warm up production cache
echo "==> Warming up cache..."
php bin/console cache:warmup --env=prod --no-interaction

chown -R www-data:www-data var/

echo "==> Starting Nginx + PHP-FPM on port ${PORT}..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf