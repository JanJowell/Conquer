# Scheduled notification recovery release

Prepared: 2026-10-25. Status: local release candidate; not deployed.

## Scope

- Retry a rejected Firebase OAuth access token once per delivery batch.
- Pause scheduled/admin-managed notifications on persistent authentication, permission, or missing/invalid configuration errors. Preserve an empty `sent_at` and show recovery instructions in the admin interface.
- Keep temporary delivery failures pending. Record accepted-device token hashes so sequential retries of an unchanged message and audience skip recorded acceptances.
- Exclude internal delivery bookkeeping from FCM payloads and participant API responses. Do not log raw provider responses or credential exceptions.
- Remove device tokens only when Firebase explicitly reports `UNREGISTERED`.

This uses the existing notification `data` JSON field. No migration, new environment variable, credential rotation, or Flutter rebuild is required for this change.

## Verification

- Complete Laravel suite: 436 passed, 2 skipped, 2,639 assertions.
- Scheduler regression suite: 29 passed, 248 assertions.
- Scoped Pint formatting, Git whitespace checks, and Blade compilation passed.
- Scheduler tests use an in-memory database, simulated HTTP, and an ephemeral test-only RSA key. No live delivery is needed for these tests.

## Pre-deployment gates

- [ ] Review the exact commit contents; exclude `.env`, service-account JSON, private backup/recovery notes, operational scripts, and unrelated tests.
- [x] Through authorized read-only production access, inventory active unsent scheduled notifications: due now versus future, audience, and whether `target_user_id` is populated. Do not print message bodies, email addresses, or device tokens.
- [x] Review pending deliveries: none at the snapshot below. Recheck if release is delayed or notifications are created in the meantime. Do not run `notifications:send-scheduled` merely to inspect the queue: it sends messages. Do not automatically reactivate or resend historical records.
- [ ] Verify the release revision and deployment hooks before authorizing deployment; an existing startup hook can perform operations beyond this patch.
- [ ] Confirm the relevant web, worker, and scheduler services use the intended release revision.
- [ ] Check health and admin notification pages after deployment. Send a private test only with explicit recipient authorization and a path that targets that account alone, not an audience-wide admin notification.

### Production queue snapshot

Checked on 2026-10-25 at 07:00:29 UTC (15:00:29 Asia/Manila), through the existing web deployment `40108ffc-30d9-442e-945f-7dba53169675`. Runtime project, environment, and service identities were checked before querying. Database reads ran inside a MySQL read-only transaction, which was rolled back afterward.

- Active unsent scheduled: **0** (due: **0**, future: **0**, individually targeted: **0**).
- Inactive unsent scheduled: **0**.
- Active unsent without a schedule: **0**.
- No message bodies, email addresses, credential values, or device tokens were emitted.
- Temporary SSH access was revoked; the registry subsequently reported no registered keys. Temporary private/public keys, public discovery copy, and known-hosts files were removed.

This is a point-in-time snapshot, not a guarantee that the queue remains empty. No notification was sent, no application record was changed, and no deployment occurred. This document is not deployment authorization.

## Recovery and limits

After correcting a paused notification's configuration, edit it, enable Active, and choose a future schedule. For an immediate retry, clear its schedule, save, then choose Send now. Keep the message and audience unchanged to preserve recorded acceptance checks.

The state persistence and recovery interface in this patch apply to the admin delivery path used by the scheduler. Other direct callers of the Firebase service do not automatically acquire this persisted recovery workflow.

This is not an exactly-once guarantee: overlapping sends, a process crash before saving acceptance, or an uncertain network outcome can still cause duplicate delivery. Provider acceptance does not establish receipt on a phone. Existing zero-device and other terminal-processing semantics remain unchanged.

Previously misclassified sent records are not automatically repaired or resent. Review those individually. If a rollback is needed, remember that old code does not understand the new delivery bookkeeping; review pending partial deliveries before permitting it to process them.
