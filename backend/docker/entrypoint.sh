#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ ! -f vendor/autoload.php ] \
    || [ composer.json -nt vendor/composer/installed.php ] \
    || [ composer.lock -nt vendor/composer/installed.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ ! -x node_modules/.bin/vite ] || [ package.json -nt node_modules/.package-lock.json ]; then
    npm install --no-audit --no-fund --no-package-lock
fi

if [ -f .env ] && ! grep -Eq '^APP_KEY=.+' .env; then
    php artisan key:generate --force --no-interaction --ansi
fi

php artisan migrate --force --no-interaction --ansi

exec "$@"
