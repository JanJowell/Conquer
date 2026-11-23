# Checkpoint recording beyond the pilot

## Scope and configuration

Implemented locally November 19, 2026. Production deployment and the second-category
phone check are not yet complete.

```dotenv
CHECKPOINT_SCANNING_ENABLED=true
CHECKPOINT_SCANNING_SCOPE=eligible
```

`enabled=false` still stops recording globally. The default scope is `pilot`,
which retains the existing exact event/category-ID requirement. `eligible` removes
only that rollout restriction; it does not grant access to every event. Unknown
scope values fail closed. Request payloads cannot choose the server's scope.
Pilot IDs may remain configured for a quick rollback to `scope=pilot`.

The existing `CheckpointRecordingPilot` service name is retained to avoid changing
its integration points. It now validates the rollout scope as well as the switch.
The recorder continues to enforce the full authorization and eligibility rules.

## Unchanged requirements

- Verified, active Admin or the currently assigned Event Manager, using a valid
  scanner login. This does not introduce marshal accounts or multi-device login.
- Explicitly approved original event with a live stored status, and an available
  category belonging to it. Drafts, revision copies and unapproved legacy events
  are not promoted by this setting.
- Valid saved drawn course, matching active permanent checkpoint identities and
  a current plan. An uploaded image alone cannot identify checkpoint stations.
- For a new visit: actual category start, checked-in registration, assigned BIB,
  matching signed QR and no existing finish or official result.
- Server timestamps, one visit per registration/checkpoint, exact-UUID retry
  acknowledgement and frozen-plan change protection. Existing authorized retries
  remain acknowledgements of history rather than new visits.
- Checkpoint visits do not change registration/payment status, create results or
  certificates, send notifications, or call the finish endpoint/offline queue.

The selector's `scan_recording_available=false` is intentionally unchanged; the
current scanner uses the separate recording-plan endpoint to authorize capture.
No request/response contract, schema, lock order or finish implementation changed.

## Focused verification

280 tests / 1,643 assertions passed using SQLite in memory: rollout policy,
checkpoint recording, staff selector and finish-scanner workflow. The main
recording regressions now exercise `eligible` scope; pilot-only regressions keep
explicit `pilot` scope. New checks cover two events, another category without any
pilot IDs, duplicate/retry safety, rollback and a transactional shutdown.

These are computer-based tests, not a new real-device or MySQL concurrency run.
Existing locking and database uniqueness constraints were not changed.

## Bounded release and acceptance

Release only this change over the last deployed source, preserving the archive
activation boundary and excluding unrelated notification/maintenance work. Only
the web service needs the new scope. No migration, data backfill, automatic scan,
Git push or APK build is part of this change.

After the deployment succeeds, use the current scanner to select one other
eligible event/category, refresh its plan, explicitly select its checkpoint and
record an authorized demo participant. Verify the saved history. A real scan
creates a permanent checkpoint visit, so the owner chooses the demo registration;
do not manufacture or alter a real participant's eligibility for this check.

If needed, restore `CHECKPOINT_SCANNING_SCOPE=pilot` with its existing configured
IDs, or disable the master switch, then refresh the deployed configuration.
Rollback does not delete checkpoint history or require restoring the database.
