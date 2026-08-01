# Railway staging deployment

This deployment uses four Railway services connected through private networking:

- `conquer-web`: the only public service
- `conquer-mysql`: the staging database
- `conquer-worker`: database queue processing
- `conquer-cron`: Laravel's scheduler, invoked every five minutes

Use the Railway free trial for staging only. Set a hard usage limit before deploying.

## 1. Create the project

Create a Railway project named `conquer-staging`, add a MySQL service, and connect
the GitHub repository to three application services. Set each service's config
file path as follows:

| Service | Config file |
| --- | --- |
| `conquer-web` | `/railway/app.json` |
| `conquer-worker` | `/railway/worker.json` |
| `conquer-cron` | `/railway/cron.json` |

Only `conquer-web` should receive a public Railway domain.

## 2. Shared variables

Generate `APP_KEY` locally with `php artisan key:generate --show`. Store it only
in Railway variables and never commit it. Add these variables to the staging
environment and share them with all three application services:

```env
APP_NAME=Conquer
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated-key>
APP_URL=https://<generated-railway-domain>
LOG_CHANNEL=stderr
LOG_LEVEL=warning
LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter
DB_CONNECTION=mysql
DB_URL=${{conquer-mysql.MYSQL_URL}}
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
FILESYSTEM_PUBLIC_ROOT=/app/public/storage
TRUSTED_PROXIES=*
```

Use Railway's variable-reference picker instead of manually typing the MySQL
reference if the generated database service name differs.

Add SMTP, Firebase, and PayMongo **test** credentials only after the base app is
healthy. A missing `PAYMONGO_WEBHOOK_SECRET` intentionally causes webhook calls
to return HTTP 401.

## 3. Persistent uploads

Attach a volume only to `conquer-web` and mount it at:

```text
/app/public/storage
```

Laravel stores public uploads directly at that path on Railway. Do not attach a
volume to the MySQL service manually; use Railway's database volume and backup
controls.

## 4. Deployment order

1. Deploy MySQL and confirm it is healthy.
2. Deploy `conquer-web`; its pre-deploy command applies migrations and caches Laravel.
3. Generate a Railway domain and update `APP_URL` to the exact HTTPS URL.
4. Redeploy `conquer-web`, then deploy the worker and cron services.
5. Confirm `/up` and `/api/health` return HTTP 200.

Railway cron jobs have a minimum five-minute interval. Scheduled notifications
may therefore be delivered up to several minutes late in staging.

## 5. Smoke tests

Follow `docs/deployment.md`, using PayMongo sandbox keys and test recipients.
Inspect all three services' logs and confirm the queue table does not accumulate
old jobs. Before testing uploads, verify the web volume is mounted correctly.

## 6. Rollback

Use Railway's deployment rollback for application code. Database migrations are
not automatically reversed. Before a production migration, create and verify a
database backup; restore it only after reviewing the migration's data impact.
