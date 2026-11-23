# Admin event cleanup - November 15, 2026

**Later navigation update:** the local page now uses
[Events and Archived Events tabs](event-archive-navigation-2026-11-18.md), not the
three-way Event List dropdown described in the original implementation below.
Permissions, permanent-deletion restrictions and public/mobile visibility remain
unchanged by that navigation update.

**Later update - November 18:** [local XAMPP activation passed](admin-event-cleanup-local-activation-2026-11-18.md).
The one archive migration is now applied locally, with restore-tested backup and
native synthetic checks. No real event cleanup or Railway deployment occurred.
The pending activation and database-failure notes below are historical.

Implemented and verified locally. No migration was applied to a real application
database, no real event was changed or deleted, and nothing was pushed or deployed.
The new actions remain unavailable until the additive migration is applied.

## Behavior

Only Admin (`super_admin`) receives the new event-list cleanup actions:

- **Delete empty draft:** an original, unarchived draft that has never been
  submitted or approved, with no review/revision records, related setup or
  participant history. Deletion requires the exact event title and an explicit
  acknowledgement. It permanently removes the draft and its unused, unpublished
  automatic draft announcement. It does not delete uploaded files.
- **Archive event:** only an original, published/legacy-public event whose
  effective status is Completed and which has no active draft, returned or
  pending revision. This changes management-list metadata only.
- **Restore event:** returns an archived original event to the default management
  list without changing setup, approval, lifecycle status or history.

The Event List filter offers **Not archived** (default), **Archived**, and
**All events**. Admin sees all authorized originals; Event Managers can read
archived events only within their existing assignment scope. Only Admin can
perform the new cleanup actions. Archived Event Details show a clear notice.

Archiving is not cancellation, unpublishing or deletion. It does not hide
participant activity/certificates, remove public event details, stop permitted
late/offline finish processing, or change checkpoint pilot/recording rules.
Other operational/history pages retain access. Event Manager create/edit/delete
routes and submission/approval/revision rules were not broadened or replaced.

## Safeguards

- Separate Admin-only routes; Admin does not gain event-setup editing access:
  `DELETE /admin/events/{event}/cleanup`, `POST /admin/events/{event}/archive`,
  and `POST /admin/events/{event}/restore`.
- Existing web authentication, role, verification and account restrictions
  remain in place. The service rereads and locks the current Admin account and
  the event before checking eligibility and applying the action.
- A confirmation fingerprint covers stored event/setup/review/archive values.
  Changed data requires a fresh confirmation. Eligibility and dependencies are
  rechecked server-side even if only a child record changed.
- Deletion checks raw rows, including soft-deleted dependencies. Guards include
  categories, registrations/groups, payments/payment options, results, finish
  scans, feedback, certificates, badges, checkpoints/visits/plans, posts, review
  history and both sides of revision links. A schema-contract test checks that
  every foreign-key reference to events has an explicit guard.
- The only announcement exemption is an unused unpublished automatic draft
  announcement. It is removed transactionally, not orphaned into a global post.
- Successful outcomes are logged in the existing Admin Activity Log within the
  transaction. Audit failure rolls the entire action back. This uses the existing
  log-retention behavior; it does not introduce an immutable audit system.
- Native confirmation dialogs provide acknowledgement, safe cancel/Escape and
  focus return. Pending submissions block repeat requests and dismissal;
  back/forward restoration resets the interface safely.

## Schema and compatibility

`database/migrations/2026_11_15_000001_add_event_archive_metadata.php` adds nullable
`archived_at` (indexed) and `archived_by_user_id` (nullable user reference).
It does not backfill rows or reuse the old `archived` lifecycle status, which
this application normalizes to Completed. Archive fields are not mass assignable
or part of reviewed setup/checkpoint-plan fingerprints.

Archive/restore updates only these metadata fields and `updated_at`, avoiding
event-saving announcement/status hooks. No global query scope was introduced.
The event list stays usable if the migration is missing: it shows an explicit
cleanup-unavailable notice, and the cleanup endpoints fail closed. HTTP requests
never run migrations automatically.

Rollback refuses to discard populated archive metadata. Its empty-metadata
path explicitly removes the index and foreign key before their columns.

## Verification

All database writes were synthetic, in a separate application copy using
SQLite `:memory:`, a test-only key and array-backed test services. The real
`.env` and cached application configuration were not copied or booted.

- **74 new Admin cleanup cases** plus **one new checkpoint compatibility case**.
- Focused cleanup/review/history/finish/certificate suite: **288 passed**,
  **1,936 assertions**, no skips/errors/failures.
- Full working-source backend suite: **1,256 passed**, **two existing skips**,
  **8,059 assertions**. Focused cases are included, not additional.
- **Seven real headless-browser scenarios passed** using the actual Blade
  partial and synthetic loopback-only submissions. Node reports eight passes
  including the parent test. They cover escaped titles/accessible naming,
  cancel/Escape/focus, native required-field validation, method/fingerprint
  serialization, repeat-submission protection, navigation recovery and both
  archive/restore confirmations. No real Laravel cleanup request is sent by
  this browser fixture server.
- Blade compilation, event-route inspection, PHP/Pint, JavaScript syntax and
  scoped whitespace checks passed.
- Archive/restore tests compare all non-event business-table hashes, original
  event fields, participant activity/certificate API responses, certificate
  verification and checkpoint-history availability. Exact checkpoint retry
  receipts and the recording-plan fingerprint survive management archiving.
- Thirteen protected files matched their starting hashes, including checkpoint
  and finish implementations, review workflow, APIs, dependencies and Git index.
  Git HEAD and real `.env` metadata are unchanged. Existing unrelated edits were
  preserved; the scanner/Flutter project was not changed or built.

The first isolated run exposed the SQLite index-drop ordering issue; it was
fixed, not skipped. The browser first required a sandbox-exempt rerun, then
exposed pending-dialog Escape dismissal; that was fixed and all scenarios rerun.
No production action was involved. Temporary owned browser profiles/listeners
were removed/stopped; private test evidence remains under:

`storage/app/private/event-cleanup-20261115-671ecc12a7c34d04a9e76f1d6d354122/`

Key reports include `focused-e8755664a7e6422197494e7ac0f5378a-result.json`,
`full-a253ff7daf35465d88cbf8f90006dba1-result.json`, their JUnit files, the original
five-file source baseline, `protected-before.json` and `verification.json`.

## Activation still pending

Before activation, verify the intended local database/pending migrations and
take a backup, then apply the additive migration through the normal workflow.
Do not use destructive refresh/reset commands. Check the Admin list and dialogs
locally before separately approving a production backup/release.

No live MySQL concurrency/migration run, real-account browser smoke test,
production deployment or APK build/install was performed in this task.

### Local activation attempt - November 15

Activation stopped before the backup or migration. The running listener on
port 3306 belongs to the expected `C:\xampp\mysql\bin\mysqld.exe`, but it did
not send a MySQL protocol greeting within the three-second read check. This
was reproduced outside the sandbox. The Laravel connection check stalled at
connection establishment, so the actual database identity and pending-migration
inventory could not yet be verified.

The local error-log tail did not identify a current explicit failure; this is
not evidence that the database is healthy or that its files need repair. Only
the three inspection PHP processes started for this attempt were stopped.
MySQL and the existing backend processes were left untouched. The temporary
diagnostic helper was removed. No database backup, migration, reset, restart,
event cleanup, deployment or APK build was performed. Diagnose local MySQL
responsiveness before repeating the identity check and fresh-backup workflow;
do not substitute production or run migrations against an unverified target.

### Authorized normal-restart attempt - November 15

The user authorized a normal local MySQL stop/start. Read-only process/service
inspection confirmed the same XAMPP executable, its `my.ini` and standalone
mode, with no Windows service attached. The bundled `mysql_stop.bat` invokes a
process-killing helper, so it was not executed.

The local-only helpers `scripts/restart-local-mysql.ps1` and
`scripts/local-mysql-control-config.php` instead attempted `mysqladmin shutdown`
against loopback port 3306, with a five-second connection timeout and a bounded
shutdown wait. The existing Laravel local configuration supplied credentials
through an exclusively created file in an owner/SYSTEM-restricted temporary
directory; credentials were not printed or passed in process arguments. Target
validation did not bypass the unavailable database handshake or claim that
server/database identity had been established through SQL.

The normal shutdown command failed. The guarded restart path was therefore
**not executed**, and the original MySQL process remained running. No server
force-kill, database-file repair/reset, migration, backup or production request
was performed. The temporary client file and directory were removed; a final
check found no remaining control directory. Real `.env` metadata and Git index
hash matched their prior values. PHP and PowerShell syntax checks passed; the
successful shutdown/restart branch remains untested.

Further recovery requires a separate decision. Do not keep retrying shutdown,
replace data files, run repair/reset commands, or attempt the archive migration
while the database remains unresponsive. Preserve recovery material before
considering any stronger shutdown; a live file copy is not a verified consistent
database backup.

### Preservation and startup diagnosis - November 15

A protected, DPAPI-encrypted recovery copy of 279 local data files and `my.ini`
was captured and independently verified. It is live-file recovery material,
not a consistent or restore-tested database backup. Windows Application events
now identify the startup blocker: MariaDB cannot open its system privilege
table `db` because it reports an incorrect file format, then aborts. Application
table health and the original cause are not established. No repair or migration
was attempted. See the [capture, diagnosis and next decision](local-mysql-recovery-2026-11-15.md).

The subsequent [isolated recovery/export test](local-mysql-recovery-test-2026-11-15.md)
passed on a disposable copy, including a matching 43-table SQL import. This did
not repair the real local server, recover its database-specific grants, or
authorize/apply the archive migration. A controlled real-local restoration
decision is still needed first.

The later authorized [fresh-instance restoration attempt](local-mariadb-restoration-attempt-2026-11-15.md)
matched all application data and rendered read-only previews, but its restart
failed on the new privilege-table index. That staging copy was stopped and
quarantined. `.env`, `APP_KEY` and the original XAMPP files remain unchanged;
the archive migration is still pending and the feature remains unactivated.

A subsequent [supported-runtime restoration](local-mariadb-supported-restoration-2026-11-15.md)
passed synthetic and post-import restarts, exact recovery-data comparisons and
local Laravel health checks. Local `.env` now points to that separate instance;
`APP_KEY` and original XAMPP files are preserved. The archive migration is still
pending; real user login confirmation is the next checkpoint, not deployment.
