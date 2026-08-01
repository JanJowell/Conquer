#!/usr/bin/env bash

set -eu

php artisan config:clear
php artisan migrate --force

if [ "${ADMIN_BOOTSTRAP_ENABLED:-false}" = "true" ]; then
    php artisan admin:bootstrap --no-interaction
fi

php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
