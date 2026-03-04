#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

if [ -f bin/console ]; then
  php bin/console doctrine:migrations:migrate --no-interaction || true
fi

exec "$@"
