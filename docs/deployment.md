# Production Deployment

Deploy a tagged commit to staging first. Never copy the local `.env`, SQL dump
files, `vendor`, `node_modules`, or development logs into a release artifact.

## Required services

- PHP 8.3 or newer with the extensions required by `composer.lock`
- A supported MySQL database with automated backups
- A process manager for `php artisan queue:work`
- A cron service for Laravel's scheduler
- HTTPS with a permanent domain
- SMTP credentials and, when enabled, Firebase and PayMongo credentials

## Environment

Create the server `.env` from `.env.production.example`, generate a unique key
with `php artisan key:generate`, and store all credentials in the hosting
provider's secret manager. Confirm at minimum:

- `APP_ENV=production`, `APP_DEBUG=false`, and a permanent HTTPS `APP_URL`
- `SESSION_SECURE_COOKIE=true`
- dedicated, least-privilege database credentials
- working SMTP settings
- `PAYMONGO_WEBHOOK_SECRET` whenever PayMongo checkout is enabled
- Firebase credentials outside `public/` when push notifications are enabled

Do not run `key:generate` again after production data has been encrypted.

## Build and release

Run these commands from a clean checkout:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan storage:link
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
```

Back up the database before migrations. Prefer an atomic release directory or
platform rollback feature so the previous application release remains available.

## Background processes

Run a supervised worker and restart it after each release:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
php artisan queue:restart
```

Configure cron to invoke the scheduler every minute:

```cron
* * * * * cd /path/to/conquer && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler sends queued notifications, expires unpaid registrations, and
audits payment states.

## Verification

Before routing users to the release:

1. Confirm `GET /up` and `GET /api/health` return HTTP 200 over HTTPS.
2. Confirm `GET /api/config` reports the expected payment configuration.
3. Test registration, email verification, login, and password reset.
4. Complete a PayMongo sandbox checkout and verify its signed webhook updates the registration.
5. Confirm uploads are reachable through `/storage` and push notifications are delivered.
6. Confirm the queue worker and scheduler are running and logs contain no new errors.

## Rollback

Put the application in maintenance mode if data compatibility requires it,
switch back to the previous application release, clear/rebuild caches, and
restore the pre-deployment database backup only when the migration cannot be
reversed safely. Never run destructive migration rollback commands without
reviewing their effect on production data.
