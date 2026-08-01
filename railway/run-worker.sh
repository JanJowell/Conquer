#!/usr/bin/env bash

set -eu

exec php artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
