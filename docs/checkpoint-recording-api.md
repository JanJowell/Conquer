# Online checkpoint recording API — local implementation

Implemented locally on 2026-11-10. This document describes the new recording
contract, not a production-enabled feature. No application database migration,
real participant scan, Railway deployment or APK build has been performed.
A later computer-only verification used a separate disposable MySQL test schema;
see the [integration verification](checkpoint-integration-verification-2026-11-10.md).
The production read-only checkpoint selector remains
separate; its contract is documented in [staff-checkpoint-list-api.md](staff-checkpoint-list-api.md).

## Safety boundary and first-version rules

- `CHECKPOINT_SCANNING_ENABLED` defaults to **false**. Tests enable configuration
  only inside an isolated test application. The real `.env` is unchanged.
- The new migration creates two empty tables: `checkpoint_scan_plans` and
  `checkpoint_scans`. It does not backfill or alter existing business records.
  Missing tables return a safe unavailable response; HTTP requests never migrate
  or synchronize checkpoint identities.
- Only online recording is supported. The server sets the timestamp. Offline
  capture times, queued passes, laps and staff/registration overrides are rejected.
- There is one accepted visit per registration and permanent checkpoint ID.
  This is not a lap-counting implementation. Scanning checkpoint 2 before
  checkpoint 1 is permitted as evidence; missing/order rules do not infer a DQ.
- A checkpoint visit is **not a finish**, check-in, official result or certificate.
  No registration/payment status is changed, and no notification is sent.
- A visit shows that an authorized staff request scanned a BIB QR at the selected
  checkpoint. It does not independently prove physical presence using GPS.
- Visits after the category's scheduled end are accepted with `after_cutoff:
  true`, consistent with the existing finish payload's meaning. This is a flag,
  not an automatic penalty or a new cutoff-duration calculation.

## Authorization and eligible setup

Both routes use the existing `mobile.auth` middleware and `mobile-api` throttle.
They require the existing staff bearer token. No new credential or staff login
flow is introduced. Invalid/expired tokens, blocked IPs and banned/suspended
accounts retain their protections.

Only email-verified Admin (`super_admin`) and Event Manager accounts may record.
Event Managers, including normalized legacy `admin`, must currently be assigned
to the event. Admin can use any eligible event. The service rechecks the stored
token, expiry, verification, account status, role and assignment while holding
transactional locks, rather than relying only on the middleware's earlier read.

An event must be explicitly approved, not a revision copy, and have stored
`upcoming`, `ongoing` or `completed` status. A category must belong to that event
and be `open` or `closed`. Closed registration does not close a checkpoint.
Legacy unreviewed, draft, pending, returned and discarded setups are not eligible.

The category needs a valid saved drawn route with named intermediate checkpoints
and matching active permanent IDs. An uploaded image alone cannot identify a
checkpoint station. The existing read-only selector is unchanged and still
reports `scan_recording_available: false`; old scanner versions cannot silently
enable recording because this implementation was added.

For a new visit, the registration must match the signed BIB's event/category,
be `checked_in`, have an assigned BIB, and have no finish scan or official result.
The category must have actually started (`started_at` is not null or in the future).
The signed registration tuple is rechecked under lock after resolving the QR.

## 1. Refresh the recording plan

```http
GET /api/staff/checkpoint-scanner/plan?event_id=10&category_id=20
Authorization: Bearer <existing-staff-token>
Accept: application/json
```

Both IDs are required positive integers. Example IDs below are synthetic.

```json
{
  "data": {
    "event_id": 10,
    "category_id": 20,
    "plan_fingerprint": "<64 lowercase hexadecimal characters>",
    "category_started": true,
    "recording_mode": "online",
    "checkpoints": [
      {"id": 30, "position": 1, "label": "Water station"},
      {"id": 31, "position": 2, "label": "Turnaround"}
    ]
  }
}
```

This GET creates no plan or scan rows. Before the actual start it can return
`category_started: false`; recording must remain blocked. Starting the category
changes the plan fingerprint, so the client must refresh before recording.
Responses contain no route coordinates or participant records.

## 2. Record one online visit

```http
POST /api/staff/checkpoint-scans
Authorization: Bearer <existing-staff-token>
Accept: application/json
Content-Type: application/json
```

```json
{
  "scan_mode": "checkpoint",
  "event_id": 10,
  "category_id": 20,
  "checkpoint_id": 30,
  "plan_fingerprint": "<fingerprint from the refreshed plan>",
  "token": "<existing signed BIB QR payload>",
  "client_scan_id": "384162df-bdda-4a5d-b9c8-e34a1352db64"
}
```

All seven fields are required. `scan_mode` must be `checkpoint`. The token is
limited to 2,048 characters and uses the **unchanged** `FinishScanToken` format;
the same signed BIB can be presented to the separate checkpoint endpoint.
Generate one UUID for the capture attempt. UUIDs are normalized to lowercase.

Do not send `captured_at`, `captured_offline` (even false), `scanned_at`,
`elapsed_seconds`, `scanned_by_user_id`, `registration_id`, `lap_number` or
`pass_number`. Their presence is rejected, including null values.

The first accepted request returns **201**. An exact authorized retry with the
same UUID and request identity returns **200**, `idempotent_replay: true`, and the
original stored data, without changing its timestamp. A new UUID for the same
participant/checkpoint returns 409, not another visit. Reusing a UUID for another
staff identity, registration, checkpoint or plan returns 409 without the original
participant's data.

The success payload has `message`, `idempotent_replay` and `data`. Data includes:

- `id`, `scan_type: checkpoint`, `recording_mode: online`;
- `registration_id`, snapshotted `participant_name` and `bib_number`;
- `event_id`, `category_id`, permanent `checkpoint_id`, snapshotted
  `checkpoint_label` and one-based `checkpoint_position`;
- `plan_fingerprint`, `client_scan_id`, server `scanned_at` (ISO 8601),
  `elapsed_seconds` since the actual start, and `after_cutoff`;
- `scanned_by: {id, name}` (name is snapshotted; ID may become null after a staff
  account is deleted).

Neither the raw BIB token nor bearer token is stored in the new tables. The
payload never exposes request fingerprints, payment/medical details or route
geometry. Success, validation and domain-error responses use
`Cache-Control: private, no-store` and `Vary: Authorization`.

## Plan stability and history

The first valid visit atomically saves an immutable plan snapshot and visit.
The snapshot contains the normalized route, permanent checkpoint IDs, names,
order, event/category metadata, review version, actual start and scheduled end.
There is only one recorded plan per category in this version.

Before the first visit, a changed setup invalidates the client fingerprint:
refresh and explicitly reselect. After the first visit, a changed plan stops new
recording with `checkpoint_plan_changed`. Do not automatically remap an old ID
or start a new plan. Staff must stop and request administrator review; no plan
reset/replacement endpoint or amendment process is included in this version.

An exact retry can acknowledge an already accepted visit after a subsequent
finish or route edit, but still requires current authorized access to the eligible
event/category and the matching signed registration. This does not authorize a
new retrospective visit.

Transactions use shared locks for current staff, event and category to protect
authorization/setup while staying compatible with finish-writer foreign-key
reads. Registration, checkpoint identities and recorded plans use exclusive
locks. Unique constraints protect both client UUID and registration/checkpoint
pairs. A unique-insert conflict is re-read in a fresh transaction; a rolled-back
attempt cannot leave an orphan plan.

Model writes treat visits/plans as append-only. Foreign keys prevent deleting
their event, category, registration, checkpoint or plan through cascades. Model
deletion guards return a `checkpoint_history` validation error for parent and
participant deletion. Deleting a staff-only account retains its recorded name
and nulls its staff reference. These protections apply even with recording off.
They do not protect against a database administrator deliberately disabling
constraints or executing raw history updates/deletes.

Migration rollback refuses to drop either table if history exists. Future
retention, anonymization, amendment or rollback work needs a separately reviewed
process. Do not discard recorded visits to make a rollback succeed.

## Error handling for the scanner integration

Error bodies have `message` and `code`; field-validation errors also have `errors`.
Existing middleware may use its existing authentication-error body instead.

| HTTP | Codes / handling |
| --- | --- |
| 401 | Invalid/expired bearer or `staff_session_changed`: clear staff session and sign in again. |
| 403 / 423 | Forbidden staff/event, banned/blocked/unverified, suspended: stop; do not substitute another event. |
| 422 | `validation_failed`, `invalid_scan_token`, `selection_mismatch`: show the error; no visit was written. |
| 409 | `stale_checkpoint_plan`: refresh plan and explicitly reselect before a new attempt. |
| 409 | `checkpoint_list_not_ready`, `checkpoint_not_available`, `category_not_available`, `event_not_approved`: stop and refresh/review eligible setup. |
| 409 | `checkpoint_plan_changed`: stop and request administrator review. |
| 409 | `category_not_started`, `participant_not_checked_in`, `bib_not_assigned`, `participant_already_finished`: show eligibility reason; do not change participant status. |
| 409 | `checkpoint_already_recorded`, `client_scan_conflict`: show conflict; never create a replacement UUID just to bypass it. |
| 503 | `checkpoint_recording_disabled`, `checkpoint_recording_unavailable`: leave checkpoint recording disabled. |
| 429 | Existing throttle: respect retry timing; do not switch endpoints. |

On an ambiguous network timeout, do not report success. Preserve the same attempt
UUID for an explicit online retry of the exact request. This is not authorization
to add checkpoint entries to the finish offline queue. A changed account,
event/category/checkpoint or plan must not silently reuse another attempt.
Never fall back to `/api/staff/finish-scans` for a checkpoint request.

## Verification and remaining work

Initial SQLite verification on 2026-11-10, before the integration follow-up:

- Checkpoint API: **104 passed**, 643 assertions.
- Full backend suite: **1,023 passed**, two existing skips, 6,904 assertions.
  The 104 checkpoint cases are included in this total, not additional to it.
- Separate event-form persistence audit: **23 passed**, 225 assertions.
- PHP syntax checks: all 11 affected PHP files passed. Scoped Pint formatting
  and tracked-file whitespace checks passed.
- The 15 protected files, including existing finish/token/auth/list implementations,
  parent models, dependencies, test configuration and Git index, match the
  pre-task fingerprints. The real `.env` was not edited; its metadata is unchanged.

Focused tests use synthetic records, a test-only key and SQLite in memory.
Coverage includes authorization changes, selected scope, route identity/staleness,
server time, required/forbidden fields, duplicate/replay isolation, finish/result
separation, history deletion guards and rollback constraints. Collision recovery
is simulated; real concurrent MySQL sessions and a physical scanner are not
validated by that SQLite suite. The later [computer-only integration follow-up](checkpoint-integration-verification-2026-11-10.md)
adds real MySQL and real scanner-client HTTP checks, a checkpoint-only locking
fix, and a full rerun with **1,024 passed**, two existing skips. Device verification
remains deferred.

Private verification logs and JUnit reports are retained under
`storage/app/private/checkpoint-recording-local-20261110-ff016d4dd14f453e8a379809310efb48/`.
The temporary, credential-free `.env.testing` was removed after verification;
no real environment file was copied into it.

This backend work does not add a checkpoint-history web screen, enable the
production scanner's camera/recording button, queue checkpoint captures, migrate
an application database, or enable production recording. The scanner integration
and computer-only contract checks are now complete locally. A reviewed
release/migration and dedicated device smoke test remain before operational use.
