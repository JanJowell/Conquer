# Staff checkpoint list API

Implemented locally on 2026-11-09. This is a read-only Laravel selection context,
not a scanner screen or a checkpoint-visit recording API. No Flutter changes,
new accounts, migrations, backfill, production writes, APK build or deployment
are part of this step.

## Request and authorization

```http
GET /api/staff/checkpoint-scanner/context
Authorization: Bearer <staff-token>
Accept: application/json
```

Uses the existing `mobile.auth` middleware and `mobile-api` throttle. Only
verified Admin (`super_admin`) or assigned Event Manager accounts can read it.
The existing legacy `admin` role is normalized to Event Manager, not Admin.
Participants and retired roles are rejected. Invalid/expired tokens, suspended
accounts, banned accounts and blocked IPs retain the existing middleware checks.
This endpoint does not issue tokens or change the existing staff login/2FA flow.
Do not share an Event Manager account among marshals.

Events must have explicit `review_status = approved`, no `revision_of_id`, and a
stored lifecycle status of `upcoming`, `ongoing` or `completed`. A manager can
read only events currently assigned to that account; Admin can read all matching
events. Pre-review legacy events with NULL review status are not automatically
treated as approved. Private, returned, pending and historical approved revision
copies never appear. Pending revisions leave the live approved list intact;
approval exposes the updated live category identities, not private draft IDs.

Only `open` and `closed` categories appear. Closed registration is not the same
as a closed checkpoint station; this is a read-only setup list. Draft categories
are excluded. No participant registration or category start is required to
inspect the list. Neither inspection nor a `ready` list authorizes recording a
visit or finish.

Optional positive-integer query parameters:

- `event_id`: filter within the caller's accessible approved events.
- `category_id`: filter to a category belonging to a matching event.
- `page`: defaults to 1.
- `per_page`: defaults to 20 events; maximum 50.

Unknown/inaccessible IDs and mismatched event/category pairs return an empty
successful list, without revealing another event's existence. Invalid values
return 422. Events sort by event date descending, then ID descending; categories
sort by name then ID. Checkpoints sort by their saved one-based position.

## Response

IDs below are synthetic examples, not existing records.

```json
{
  "data": [
    {
      "id": 10,
      "title": "Example approved event",
      "event_date": "2026-12-01",
      "categories": [
        {
          "id": 20,
          "name": "10K Open",
          "checkpoint_list_status": "ready",
          "checkpoints": [
            {"id": 30, "position": 1, "label": "Water station"},
            {"id": 31, "position": 2, "label": "Turnaround"}
          ]
        }
      ]
    }
  ],
  "meta": {
    "read_only": true,
    "scan_recording_available": false,
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

Only saved, active category-owned identities are returned. Retired identities,
raw route JSON, coordinates, uploaded-image paths, account/payment details and
participant data are not included. Labels are plain user-provided text; clients
must not render them as HTML. Responses use `Cache-Control: private, no-store`
and `Vary: Authorization`. Refresh with the current authenticated account rather
than retaining another account's selection context.

| `checkpoint_list_status` | Meaning |
| --- | --- |
| `ready` | Active identities exactly match normalized saved checkpoint labels, locations and order. |
| `no_drawn_route` | No drawn route; an uploaded map image does not supply structured checkpoints. |
| `no_checkpoints` | Valid route with no intermediate checkpoints. Start/Finish are not invented as checkpoint rows. |
| `checkpoint_list_needs_sync` | Missing, stale or inconsistent derived records; an explicit, reviewed reconciliation is needed. |
| `invalid_drawn_route` | Invalid saved route data; do not offer checkpoint selection. |

Every non-ready state returns `checkpoints: []`. GET never migrates, backfills,
repairs, retires or renumbers records. A missing identity table returns 503 with
`code: checkpoint_setup_unavailable`, without trying to create it. The separate
[checkpoint identity foundation](category-course-checkpoint-identities.md)
documents explicit reconciliation and its safety checks.

## Boundaries and verification

Existing finish-scanner context and finish-scan writes are untouched. Do not
send a checkpoint selection to `/api/staff/finish-scans`: it records a finish,
not a checkpoint visit. This response is not a frozen/versioned operational
plan, an offline scan authorization or a checkpoint-visit token. Future write
endpoints must recheck assignment, approved plan/version, category/station
ownership, session permissions and idempotency at capture/synchronization time.
History protection and lap/pass rules remain separate work.

Tests use synthetic records in SQLite memory and a temporary test-only
environment, not local MySQL or Railway. Coverage includes real bearer-token
middleware, role/verification/status access, assignment changes, safe filters,
pagination, category identity isolation, private/pending/approved revisions,
retired records, image-only and invalid/stale maps, missing schema, no database
writes during GET, rejection of POST, and no Google HTTP requests.

The first focused run caught a new eager-loading callback type mismatch. The
callback was corrected to support both Eloquent relations and query builders;
authorization and review filters were not weakened.

Final verification:

- Full backend: **919 passed, 2 existing skips, 6,261 assertions**. The new API
  suite accounts for **40 tests / 240 assertions**; identity persistence remains
  **29 / 126** and the unchanged finish-scanner workflow **10 / 133**.
- The two skips are the existing disabled public registration screen and new-user
  registration feature tests, not checkpoint failures.
- The first full-suite process hit its default 128 MB memory limit in certificate
  PDF rendering. The successful rerun used `php -d memory_limit=512M
  vendor/bin/pest` for that test process only. No `php.ini`, runtime environment or
  server memory setting was changed.
- Passing full-suite receipts:
  `storage/logs/staff-checkpoint-list-full-512m.log` and
  `storage/logs/staff-checkpoint-list-full-512m.xml`. The earlier focused passing
  run, before the last two coverage additions, is retained as
  `storage/logs/staff-checkpoint-list-focused.xml` (77 tests / 486 assertions).
  The initial memory-limited run is retained as `staff-checkpoint-list-full.log`.
- PHP syntax and scoped Pint checks passed. Route inspection confirms GET/HEAD
  only, with bearer-token authentication and the existing API throttle. POST
  returns 405.
- A temporary `.env.testing` with a test-only encryption key and forced SQLite
  memory isolated the runs from the real `.env`. A separate nonexistent config
  cache path was forced for testing. The temporary file was removed afterwards;
  no test config cache was created. Local MySQL and Railway data were not used or
  changed.
