# Map-only production release — 2026-10-27

## Scope and provenance

- Service: `conquer-web`, Railway production environment.
- Deployment: `c2a54759-2f28-4400-ac15-97b744d99983` (SUCCESS).
- Previous deployment: `40108ffc-30d9-442e-945f-7dba53169675`.
- Source: isolated archive of `5623ad1` plus the explicit map-feature overlay
  in `scripts/prepare-map-release.ps1`. The notification-recovery commit
  `546bc0d` and unrelated untracked files were excluded.
- No Git commit or push was performed. This was a CLI source upload, not a
  deployment of the current working tree or current HEAD. Reconcile and commit
  the intended map changes before a future Git-based deployment; do not assume
  current HEAD exactly represents this release.
- Only the web service was deployed. Worker, cron, signing, and Flutter were unchanged.
- Static-image generation remains disabled. No Static Maps credentials were added.

## Backup and verification

A fresh encrypted export was saved outside the repository at:
`C:\Users\keith\Documents\Racetech Backups\production-map-release-20261027-090207`.

Database, uploads, and Laravel encryption-key recovery artifacts passed checksum,
DPAPI decryption, and structural validation. This was **not** an isolated database
restore test. Recovery requires the Windows user/profile that created the backup.
The database and uploads are separate captures, not an atomic combined snapshot.

The isolated release verification copy passed:

- 449 Laravel tests, 2 skipped, 2,580 assertions.
- Five JavaScript route-data tests.
- Mocked map-editor browser workflow and its five subtests.
- Source-file hash comparison between release and verification copies.

The clean test copy required a disposable test APP_KEY, a test-only `.env`, and
existing compiled frontend assets. These were confined to the verification copy,
not the deployment source. The browser test initially timed out in the sandbox;
its outside-sandbox rerun passed. No billable Google requests were used in tests.

## Live checks

- `/up`, `/api/health`, `/api/events/11`: HTTP 200.
- Migration `2026_10_21_000001_add_course_map_to_categories_table`: applied.
- `categories.course_map`: present.
- Event 11 / category 24: exists; `course_map` is present in the API and null.
- `course_map_image`: null; static generation disabled in both config and environment.
- Existing `checkpoint_map_image_url` field remains present.
- Temporary inspection/backup SSH keys were revoked and local key files removed.

The standard deployment hook remained unchanged, including its certificate sync
without `--notify`. No route was created or changed by the release checks.

## Device acceptance follow-up (2026-10-28)

The administrator subsequently saved event 11 / category 24 with four route
points, one checkpoint, and separate Start/Finish. A read-only production API
check confirmed the saved geometry was available to the mobile app.

The updated Android APK was built against the production API. Flutter analysis
reported no issues and all 202 tests passed. APK signature verification matched
the existing private-testing debug certificate; the configured Maps key was
confirmed present without printing it. No signing settings were changed.

APK SHA-256:
`D1F25CE29C37B5A15D7BBCD63E915BCCDBAD9036A8A7507C2D350A6108ED7EE7`.

The user confirmed correct map/route/checkpoint display on their phone, then
confirmed visibility in My Activity and registration as well as Event Details.
These are user-reported device checks, not automated device instrumentation.
Offline/invalid-key fallback and category switching were covered by simulated
tests but were not separately confirmed on the physical device.

Static-image generation remains disabled. No additional Google credentials,
Railway plan changes, or deployment are required to record this acceptance.

## Original acceptance instructions (completed for saved-route display)

An authorized administrator must open **hiking sa taal → 1K Open**, draw or enter
the actual reviewed course, add checkpoints, confirm the review, choose **Use This
Route**, and save the parent event/category form. Reopen it to confirm persistence.
Do not invent course geometry or treat the editor as safety/navigation advice.

Then build/run Flutter with the production API and verify map tiles, saved route,
Start/Finish, checkpoint numbering, category switching, and uploaded-image fallback
on a real device. The subsequent device results are recorded above; failure-mode
device checks remain separate from the confirmed happy-path display.

If application rollback is necessary, use the previous deployment after reviewing
compatibility. Do not automatically reverse the migration or restore the database:
dropping the nullable column would discard any routes saved after this release.
