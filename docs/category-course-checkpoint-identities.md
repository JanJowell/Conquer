# Category course checkpoint identities

## Scope and activation

This is the local Laravel foundation for giving already-saved drawn-map points
permanent category-owned identities. It is **not checkpoint scanning yet**.
The original foundation added no HTTP endpoints. A subsequent local step adds
the [read-only staff checkpoint list API](staff-checkpoint-list-api.md), using
existing staff roles and tokens. There are still no checkpoint-write endpoints,
marshal accounts, participant app changes, map-editor changes, APK builds or
deployments.

The new `category_course_checkpoints` table is separate from the existing
event-level `checkpoints` table. Its category foreign key supplies the event
ownership; it does not guess which category an old event-level checkpoint belongs
to. Uploaded-image-only categories remain unchanged and receive no invented
checkpoint names or coordinates.

The migration creates only the new table; it does not backfill application data.
It has been exercised in SQLite test databases and applied to the verified local
MySQL development database on 2026-11-09 (Asia/Manila), not Railway. The first
preflight stopped because MySQL was offline. After the user started it, a fresh
read-only preflight verified the local target and confirmed that neither the new
table nor its migration existed. Activation then applied only this migration.
The local database had **zero categories with saved drawn-route data**, so the
preview and reconciliation both processed zero categories and created zero rows.
No checkpoint names or coordinates were fabricated. Existing route JSON, map
images and event review fields/timestamps were verified unchanged, and a repeat
reconciliation was a no-op. No database settings were changed or service started
by the activation helper. Before this migration is applied in any other
environment, category saves remain usable and synchronization deliberately
returns `ready: false`.

## Saving and identity rules

`Category` invokes `CategoryCourseCheckpointSync` after creation or a change to
`course_map`. The existing event/category controllers and review workflow already
wrap their setup writes in database transactions. Synchronization locks and
reloads the persisted category, so it does not overwrite records from a stale
in-memory route. Raw SQL, bulk updates and `withoutEvents` bypass this hook;
use the explicit reconciliation command for those cases.

Each derived row holds an ID, category, active position, label, six-decimal map
coordinates, retirement timestamp and normal timestamps. Reconciliation follows
the existing version-1 map normalization without rewriting the saved JSON:

- Unchanged points retain their IDs, including after route-path-only edits.
- Reordered points retain their IDs and receive their new positions.
- Renaming a uniquely matched unchanged location retains its ID.
- Moving/replacing a point creates a new ID. Labels or list positions alone are
  not identity evidence.
- Removed points are retired: position becomes NULL while their ID, label and
  coordinates remain. Re-adding a retired location creates a fresh identity.
- Exact repeated points retain occurrence order only when their multiplicity is
  unchanged. Ambiguous repeated-location replacements receive new identities
  rather than guessing which old point they mean.
- Identical reconciliation is a no-op, including timestamps.
- Invalid legacy route JSON is skipped without retiring existing good records.

Active `(category_id, position)` pairs are database-unique; NULL positions permit
multiple retired rows. Position reassignments and retirements are transactional.
Category deletion still cascades its derived setup rows, matching the existing
unused-draft/category cleanup behavior. Before recording real visits, operational
references must prevent deletion and preserve the approved plan/history. A
populated identity table cannot be silently dropped by migration rollback.

These rules deliberately avoid changing the strict existing map format to add
IDs to its arrays. The public map response and uploaded-image fallback are
unchanged. New identities are not appended to mobile responses or event review
snapshots/hashes.

## Reviewed revisions

Draft categories get their own category IDs and checkpoint rows. Editing or
discarding a private revision does not change published rows. On approval, the
existing review workflow saves the reviewed route onto the mapped live category;
normal reconciliation then retains matching live IDs, retires removed/moved
points, and creates new live-owned identities. It never reuses private draft row
IDs as published identities. New categories receive their own published rows.

The existing review hash remains valid when derived records are backfilled: the
reviewed source map itself has not changed. This does not loosen event access,
submission checks, review approval or revision visibility.

## Connecting existing saved routes

After separately reviewing/applying the additive migration to the intended local
database, preview a selected category:

```text
php artisan course-checkpoints:sync --category=24
```

The category number above is an example, not authorization to modify it. The
default command never creates, edits or retires checkpoint records. Review its
aggregate counts, then explicitly apply only the chosen category:

```text
php artisan course-checkpoints:sync --category=24 --write
```

`--category` can be repeated; omitting it selects all categories with saved route
data or existing derived records, including private drafts. IDs must be positive
integers. Write-mode transactions are per category; invalid legacy routes are
skipped and cause a failure exit status while other selected valid categories
may have been reconciled. No route JSON, image, registration, result or review
status is rewritten by the command. Do not run a Railway migration/backfill
without a separately reviewed release and authorization.

## Verification

Focused suite: `tests/Feature/CategoryCourseCheckpointIdentityTest.php` — 29 tests,
126 assertions passed in SQLite memory after the save/reopen coverage expansion.
It covers actual event/category form
requests, ownership/access, deterministic identities, repeated locations,
retirement, normalization, dry-run and explicit backfill, invalid selectors/JSON,
rollback, missing migration, unchanged mobile map output, private revisions,
approval and pending-review hash stability.

The initial runs found console-output expectation and nested event-form fixture
mistakes. Their fixtures were corrected; existing application validation rules
were not relaxed. Scoped PHP syntax, Pint and Category diff-whitespace checks
passed. Full backend regression before the save/reopen test expansion: **879
tests passed, 2 existing skips, 6,007 assertions**, in SQLite memory. Logs and JUnit receipts are retained as
`storage/logs/checkpoint-identity-focused.*` and `checkpoint-identity-full.*`.
The skips are the existing disabled public registration screen/registration
feature tests; they are not checkpoint failures.

Validation used a temporary `.env.testing` with a test-only encryption key and
no provider credentials, forced SQLite memory,
and a non-existent test-only config-cache path. The temporary file is removed
after testing; the real `.env` is not edited. Local activation subsequently
completed with the guarded, scoped checks described above. No Railway migration,
Google Maps request or physical-device test is part of this step.

The local-only `scripts/activate-local-course-checkpoints.php` helper provides
`inspect` (read-only) and separately authorized `activate` modes. It refuses
non-local environments, non-loopback/database targets, pre-existing checkpoint
schemas, unrelated migration changes and changed source route/review data. The
activation mode completed successfully against local MySQL. Its safe receipt is
`storage/app/private/course-checkpoints-local-activation-20261109.json` and records
the exact-migration check, zero-row reconciliation, unchanged source data and
repeat-sync no-op. Do not rerun activation against this existing schema; use
read-only inspection or the explicit reconciliation command when needed. There
are no existing local drawn routes to connect yet. Future normal category saves
with valid drawn routes can populate their derived checkpoint identities.

## Local save/edit persistence verification (2026-11-09)

`scripts/verify-local-course-checkpoint-persistence.php` provides a read-only
`inspect` mode and an explicitly authorized `verify` mode. Both require the real
local environment, the expected loopback MySQL development database, the applied
checkpoint migration, transactional InnoDB tables and database-side content
checksums. No application table is dropped, truncated or migrated by this helper.

The local verification passed **36 named checks** using actual Laravel HTTP
create/edit requests with normal middleware and validation. A synthetic Event
Manager, another unassigned manager, a private draft event and one two-checkpoint
category existed only inside an outer transaction that was rolled back. Tests
confirmed saved names/order, original and edited route data in the reopened
form's hidden map field, permanent IDs after separate rename/reorder operations,
no duplicate rows, no-op repeat saves, omission preserving the map, invalid-map
rejection, and rejection of the unassigned manager's edit/read requests.

Row counts and database-side content checksums for **all 41 local base tables**
matched before and after rollback. No synthetic account, event, category,
checkpoint, announcement or activity-log row remains. MySQL can still consume
AUTO_INCREMENT values for rolled-back inserts, leaving harmless gaps; counters
are deliberately not reset. No email, notification, job or HTTP client request
was sent. The private receipt is
`storage/app/private/course-checkpoint-persistence-3529214347c38807.json`.

This is backend/form-payload persistence verification, not a browser JavaScript,
Google tiles, login/2FA or physical scanner test. The principal was injected only
for the synthetic local requests. Session/cache/mail and the map key were
overridden only in that PHP process; `.env` and production were untouched. The
portable feature test also now checks actual form reopening after create, rename
and reorder, and denies an unassigned manager's edit-form read. Its rerun passed
all **29 tests / 126 assertions** using isolated SQLite memory. The JUnit receipt
is `storage/logs/checkpoint-persistence-regression.xml`. The temporary test-only
environment file was removed after the run; the real `.env` was not edited.

## Not included yet

Marshal assignments/authentication, scanner-only multi-device sessions and 2FA,
checkpoint scan endpoints, offline checkpoint queues, arrivals/departures, lap
policies, operational plan freezing, Admin progress screens and image-only manual
checkpoint lists remain separate work. No participant is marked completed,
disqualified, ranked or certified by this foundation.
