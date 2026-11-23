# Event archive deployment-readiness review — November 18, 2026

Review completed. **Ready to prepare a focused release; not yet cleared to deploy.**
Automatic archiving remains OFF. No push, deployment, production migration,
backup capture, scheduler change, SSH-key registration, event cleanup or APK
build was performed. Only this review document was added.

## What was verified

- Railway's current web deployment is still the accepted November 14 release:
  `fbb994b9-72d2-45d8-9d20-d87713251c2d`, status SUCCESS.
  Its recorded source baseline is commit
  `3ff0a44555ec6e2a9be621c8d189acc7a0e7f895`, not local main.
- Public `/up` and `/api/health` both returned HTTP 200.
- `EVENT_AUTO_ARCHIVE_ENABLED` is absent in the current web and cron variables;
  the proposed config defaults to false. It is also absent locally. No variable
  was changed. Web administrator bootstrap remains disabled, and its checkpoint
  recording flag and exact pilot pair still match the accepted configuration.
- All baseline files in the reviewed application/configuration/resource scope
  are present locally. Comparing actual files, including files untracked on
  local main, identified the focused delta below. Ordinary `git diff` against
  local main is not a reliable release inventory: many deployed files are still
  untracked in this worktree.
- All **18 runtime files** below match the isolated application copy whose saved
  result records **512 passing tests, 3,287 assertions, zero failures/errors/skips**.
  `.env.example` is the nineteenth source/configuration file; it is not part of
  that synthetic application copy. This review checked the receipt and current
  file hashes; it did not rerun the suite or test a new immutable release package.
- The tested behavior covers archive/restore/delete restrictions, stale
  confirmations, mobile discovery versus participant history, certificates,
  checkpoint evidence/retries, finish scanning, retention boundaries, restore
  exemptions, audit rollback and the disabled scheduler gate.
- Existing scanning/authentication implementations, API routes, resource
  contracts, dependency manifests/locks and Railway startup files have no
  changes in the proposed archive delta. Keep them from the accepted baseline.

## Proposed source-only delta

Start with the accepted source package, not a recursive upload of this worktree.
The feature needs **8 modified and 11 added runtime/configuration files**:

Modified:

```text
.env.example
app/Http/Controllers/Admin/EventController.php
app/Http/Controllers/Api/EventController.php
app/Models/Event.php
resources/views/admin/events/index.blade.php
resources/views/admin/events/show.blade.php
routes/console.php
routes/web.php
```

Added:

```text
app/Console/Commands/ArchiveCompletedEvents.php
app/Http/Controllers/Admin/EventCleanupController.php
app/Models/EventAutoArchiveLog.php
app/Services/AutomaticEventArchiver.php
app/Services/EventCleanup.php
config/event_archiving.php
database/migrations/2026_11_15_000001_add_event_archive_metadata.php
database/migrations/2026_11_18_000001_add_automatic_event_archiving.php
public/js/event-cleanup.js
resources/views/admin/events/partials/cleanup-confirmation.blade.php
resources/views/admin/events/partials/tabs.blade.php
```

Include the corresponding verification files in the reviewed package:

```text
tests/Feature/AdminEventCleanupTest.php
tests/Feature/AutomaticEventArchivingTest.php
tests/Feature/Api/MobileEventArchiveVisibilityTest.php
tests/Feature/Api/CheckpointRecordingTest.php
tests/Support/CheckpointResponseFault.php
tests/Js/event-cleanup-browser.test.js
tests/Js/fixtures/event-cleanup.php
```

The checkpoint test additions include response-loss/retry regressions and the
archive-preservation regression; their helper is test-only, not production
middleware. Existing baseline regressions should also run against the final
package. Browser fixtures intentionally require an isolated synthetic app, not
the real local environment.

Relevant documentation can accompany the release: manual cleanup, archive
navigation, mobile visibility/API behavior, automatic archive policy and this
review. Local backup/repair scripts and private operational evidence are not
application payloads.

Exclude unrelated notification/Firebase changes and their tests/fixtures, the
unrelated CSS trailing blank line, all real environment files, SQL exports,
private backups, credentials, dependencies and local operational scripts. No
dependency upgrade is needed. Railway's existing asset build should rebuild
assets from the final reviewed source; do not upload local build artifacts.
The full-worktree whitespace check currently flags that unrelated CSS blank
line; it was not changed by this review.

## Database release conditions

Expected new migrations, in order:

1. `2026_11_15_000001_add_event_archive_metadata`
2. `2026_11_18_000001_add_automatic_event_archiving`

The last accepted production snapshot had 87 migrations; local XAMPP now has
89. **Current production migration rows/schema were not inspected in this
review**, so two pending migrations are an expectation, not a fresh database
finding. The current MySQL service deployment is
`97ccfb1f-1b61-44c0-9836-65ecea9a0039`, created November 14 at 10:28:12 UTC,
status SUCCESS. This differs from the database deployment pinned in the old
release helpers. It does not establish data loss or an error; it means those
old approval receipts cannot authorize the next release.

Before upload, obtain a fresh authorized, database-enforced read-only snapshot:

- Verify actual server identity/version, migration history and schema. Confirm
  exactly the expected migrations are pending and no partial migration exists.
- Review historical successful Admin restores for exemption backfill. Missing
  historical audit evidence cannot reconstruct a previous restore.
- Fingerprint participant/business tables, checkpoint plans/visits and existing
  migrations for before/after comparison.
- Preview the existing startup certificate synchronization without issuing any
  certificates. `railway/init-app.sh` runs both `migrate --force` and
  `certificates:sync`; a deployment is not merely a source-file replacement.
  Do not run the real sync command as a supposedly read-only check.
- Verify the exact proposed migration/source package against a compatible
  isolated database before approving the production migration. Local MariaDB
  rehearsal passed, but is not a production MySQL restore rehearsal.

Both migrations add schema; the second also backfills restore exemptions from
audit evidence. They do not archive any events. Their rollback guards refuse to
discard populated archive/exemption/audit evidence. Plan code rollback and
forward correction deliberately; do not blindly run `migrate:rollback` or
restore a backup over newer production activity.

## Backup readiness

The existing November 14 production backup remains available:

```text
C:\Users\keith\Documents\Racetech Backups\production-checkpoint-filter-20261114-055155
```

Manifest SHA-256:
`D0269850DCB3CCF15E6296887E92E60DF89499E6BC3D2D71B0D911371C1EF1AD`.
Its database, uploads and key-recovery artifacts passed checksum, in-memory
decryption and structural verification again during this review. No SQL was
imported and no uploads were extracted.

It is **not a fresh pre-release backup**. Capture and independently verify a
new production backup before any migration, including existing checkpoint
evidence. The restored local XAMPP backup is not a production substitute.
Full isolated production restore remains unverified; database/uploads are
sequential rather than one atomic cross-service capture, and DPAPI recovery
requires the original Windows profile. No temporary production access was
registered by this review.

## Scheduler readiness

An existing `conquer-cron` service was confirmed. Its latest successful
deployment is `13bfe528-d792-4ad7-885d-a94cac3a5739`, created October 24.
The deployed manifest records `*/5 * * * *`; the status read reports stopped.
These are metadata observations, not proof of recent or future job execution.
The current service-instance metadata did not expose an active cron expression
in that read. Scheduler execution/configuration therefore still needs review.

Deploying only `conquer-web` does **not** update the separately deployed cron
image. The older cron deployment predates this archive command. Enabling a
variable on web alone is not enough to schedule this work.

For the first archive release, leave cron/worker deployments and variables
unchanged and keep automatic archiving OFF. Later activation is a separate
step: review the cron source/config, shared database/cache and application
timezone, every existing scheduled command affected by a cron update, and
observable execution/failure reporting. The new Laravel schedule is 04:00 in
the executing application's timezone with overlap protection; local Manila
time does not prove cron's production timezone.

## Recommended next step

Prepare a fresh immutable source-only package using the accepted web release
plus the listed delta, then run package-specific tests, browser checks,
compilation and asset-build verification. Do not reuse old release helpers
whose pinned deployment, file inventory or migration expectations differ.

After package verification, obtain authorization for fresh private production
preflight/backup access. Refresh deployment-trigger checks before any later
push. Only after those checks should a separately approved web deployment run,
with automatic archiving still OFF and unrelated services/settings preserved.
Post-deployment checks must confirm the two migrations, unchanged business and
checkpoint evidence, Admin/manager boundaries, mobile discovery/history and
public health. Review the production dry-run before any later enablement.

The four eligible events in the local preview are **local data**, not verified
production candidates. No event was archived by this review. No new participant
or scanner APK is needed for this backend release.

Local preparation evidence and detailed policy:
[local preparation](automatic-event-archiving-local-preparation-2026-11-18.md),
[automatic archive policy](automatic-event-archiving-2026-11-18.md),
[mobile visibility](mobile-event-archive-visibility-2026-11-18.md).
