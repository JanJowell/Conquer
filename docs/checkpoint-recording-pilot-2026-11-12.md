# Checkpoint recording pilot restriction - 2026-11-12

This change is local only. It prepares a controlled pilot in the regular scanner;
it does not enable recording, select a real event, deploy Laravel, or build an APK.
The separate test APK and USB-testing workflow are not required for this approach.

## Server boundary

The effective application configuration must enable checkpoint recording and
identify exactly one event/category pair:

```ini
CHECKPOINT_SCANNING_ENABLED=false
CHECKPOINT_SCANNING_PILOT_EVENT_ID=
CHECKPOINT_SCANNING_PILOT_CATEGORY_ID=
```

These are the safe defaults, not instructions to enable production. Both IDs
must be positive database IDs, with the category belonging to the chosen event.
Use canonical decimal values without spaces or leading zeroes. Event/category
names, wildcard values, lists, booleans, fractions and overflowing IDs are not
accepted. There is no unrestricted mode or Admin bypass.

The master switch remains off in the real application environment. Only
`.env.example` is documented; the real `.env` was not edited. At implementation
time, no production pilot pair had been chosen or configured.

For the subsequent release preparation, the user selected event **test** and
category **3k open**, reported a dedicated registration approved and then
checked in, and confirmed **Checkpoint 1** and **Checkpoint 2** in the regular
scanner's selector. These are user-reported observations, not an independent
database inspection. The completed **try / 1K Open** event is not the pilot.
Exact database IDs, current approval/start state and absence of finish/result
records still need read-only confirmation before enablement. No pilot IDs or
master switch were changed during local release preparation.

`CheckpointRecordingPilot` checks both the recording-plan and recording-service
entry points before their transactions, and checks again inside each transaction
attempt, including uniqueness recovery. Calling the recorder directly cannot
bypass the boundary. It uses application configuration, never request-supplied
pilot settings.

This adds no database queries or locks to the policy itself. The existing
shared/exclusive locking, unique constraints, immutable history and exact-request
UUID retry mechanism are unchanged. Staff authorization, current assignment,
explicit event approval, category eligibility, actual start, signed BIB and
checked-in registration checks still apply inside the allowed pair.

## API and scanner handling

| Condition | Response | Client action |
| --- | --- | --- |
| Master switch off | 503 `checkpoint_recording_disabled` | Leave recording unavailable. |
| Either pilot ID absent or invalid | 503 `checkpoint_recording_unavailable` | Stop; server configuration needs review. |
| Another event or category, including an Admin request | 403 `checkpoint_pilot_forbidden` | Stop recording; do not fall back to the finish endpoint. |

Successful request/response formats are unchanged. The new 403 must be handled
as a denial by the regular scanner before enabling its recording feature.
Never generate a replacement UUID or retry through another endpoint to bypass
a denial. Existing ambiguous-request handling must preserve the exact attempt.

The read-only checkpoint selector remains unfiltered and continues to report
`scan_recording_available: false`. Seeing checkpoints in that list does not grant
recording permission. Finish-scanner endpoints and their offline queue are not
controlled by the new pilot settings.

Removing or changing the pair blocks even an exact replay of an earlier visit.
It does not delete the visit or frozen plan. Restoring the same authorized pair
allows the original retry to acknowledge its stored evidence. Resolve uncertain
attempts before switching pilot scope; do not reset history to make a retry work.

Configuration is checked from the current application process, not live from
Railway on every request. A later configuration change must follow the normal
configuration-cache and worker/redeployment procedure. It cannot instantly
revoke an already executing request or a worker still using old configuration.
No cache clear, worker restart or deployment was performed for this local change.

## Verification

`scripts/verify-checkpoint-pilot.ps1` prepares a separate source copy from the
accepted checkpoint-plan-uniqueness release plus the seven scoped pilot paths.
It uses a credential-free synthetic environment and in-memory SQLite; it never
uses the application's real database. Source fingerprints and protected
Laravel/scanner files are checked before and after verification.

The checks cover malformed/missing IDs, exact-pair enforcement for Admin and
Event Manager, retained eligibility checks, direct service calls, retry scope
changes, immutable evidence, transactional uniqueness recovery, and independence
of the read-only selector and existing finish endpoint.

Completed on 2026-11-12, using isolated source manifest
`78FD513B6CDEFAECFDB3D4AA879656480ABDA657E423A40187D17DB0EAD00392`:

| Suite | Passed | Skipped | Assertions |
| --- | ---: | ---: | ---: |
| New pilot unit/API cases | 95 | 0 | 319 |
| Focused checkpoint and finish cases, including the pilot cases | 280 | 0 | 1,470 |
| Full backend suite, including the above cases | 1,091 | 2 existing Fortify skips | 6,984 |
| Separate migration-contract cases | 12 | 0 | 94 |

These suite counts overlap; do not add them together as unique tests. All runs
used strict warning/risky-test failure flags. Scoped Pint, six PHP syntax checks
and scoped whitespace checks also passed.

The first pilot run exposed three synthetic-fixture failures: reusing a
conflicting manager schedule, and assigning Admin as the event submitter. Only
the new fixture was corrected to use separate event dates and an actual Event
Manager owner. The application schedule and submission restrictions were not
weakened. Failed-run reports were retained alongside the successful run.

Private evidence is stored under
`storage/app/private/checkpoint-pilot-20261112-c14edaef197440578d9aed7c77df81b9/`
(`verification.json`, source inventory, JUnit reports and logs). Before/after
checks passed for protected Laravel/scanner source and configuration files.
HEAD, Git index, the real `.env` metadata and the original 15 protected Laravel
file hashes also match the pre-edit snapshot.

This is computer-based verification, not a physical-device or new MySQL
concurrency test. The pilot guard changes no SQL or locking order. Earlier
MySQL verification remains historical evidence for the unchanged core recorder,
not proof that this new pilot rollout has been tested on production.

The older private device-test backend and its immutable source copy are left
untouched. They do not include this pilot guard and are not verification of it.
No synthetic fixture, APK or private test evidence was deleted.

## Next separately authorized steps

1. Choose a dedicated pilot event/category and synthetic test registration;
   review their saved checkpoints, approval and actual category-start state.
2. Review the regular scanner's handling of this API, then build its updated APK
   with the existing API URL, login, signing and finish queue preserved.
3. Review and deploy the Laravel change with recording still off. Configure the
   exact pilot IDs and explicitly authorize its enablement separately.
4. Test one controlled checkpoint visit, exact retry and duplicate handling, and
   verify no finish/result/certificate was created by the checkpoint request.

Do not enable other events, alter real participant status, or create production
test data merely because this local verification passes.
