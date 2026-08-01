#!/usr/bin/env bash

set -eu

php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
