# Publication-based archiving and restored-event windows

Implemented locally; not deployed or enabled. No application or production
database migration or event action was performed. This supersedes the initial automatic-archive
policy, not the point-in-time evidence in earlier release reports.

## Agreed behavior

- Events already published before activation remain visible unless Admin
  manually archives them. Missing historical publication metadata is protected,
  not reconstructed from creation, latest approval or announcement dates.
- Original events first published at or after activation join automatic
  archiving. The countdown is **30 days after the ending date/time**, not
  30 days after publication. Creation/submission dates do not enroll an event.
- First publication is recorded on original Admin approval, even while the
  archive feature is OFF. Revision approval does not change that timestamp;
  revision copies do not inherit publication/restoration metadata.
- A new Admin restoration opts a completed event into a fresh **30-day window
  from restoration**, including an older protected event. It must still have
  ended at least 30 days ago. In other words, the eligibility time is 30 days
  after whichever is later: event ending or latest restoration.
- Every later restoration restarts its window. Sequential duplicate requests
  without a new archive do not reset it. Each automatic archive cycle appends
  another system audit; previous entries remain unchanged.
- Existing permanent restoration exemptions are preserved during migration.
  They are cleared only by a new, explicitly confirmed Admin restoration.
  A new restoration recorded while automation is OFF still starts its window;
  no archive occurs unless automation is later enabled and activation reached.
- The job runs daily at 04:00 in the application timezone, so an eligible event
  is processed on the next enabled scheduled run, not precisely at the deadline.

All previous readiness, completed-status, original-event and active-revision
safeguards still apply. Admin can manually archive during the new window.
This does not delete events or modify participant registrations, payments,
results, feedback, certificates or checkpoint evidence.

## Settings: leave OFF for now

```dotenv
EVENT_AUTO_ARCHIVE_ENABLED=false
EVENT_AUTO_ARCHIVE_ACTIVATED_AT=
```

`EVENT_AUTO_ARCHIVE_ACTIVATED_AT` is a separately approved, **fixed ISO-8601
timestamp with an explicit timezone offset**, identifying the first-publication
cohort. An example format is `YYYY-MM-DDTHH:MM:SS+08:00`; it is not a value to
paste literally. UTC `Z` and explicit offsets identify the same instant.

Do not substitute the current time at each run, deployment date, creation date
or latest approval. Do not move this boundary during restarts or later
disable/re-enable cycles. A missing, relative, invalid or offset-free value
fails closed with a clear command error, including for dry runs. A future
activation permits no automatic archive before that instant. The disabled
write path still returns before schema or database queries.

No real `.env` or Railway variable was changed. The new example setting is
documented only in `.env.example` and application configuration.

## Additive migration, not yet applied to XAMPP or production

```text
2026_11_19_000001_add_event_archive_publication_policy.php
```

It adds nullable, indexed DATETIME metadata `first_published_at` and
`auto_archive_restored_at`. They are not editable/mass-assignable event setup
and are included in stale-cleanup fingerprints. It does not backfill or alter
old event values, old exemptions or archive timestamps.

The audit table changes from one row per event to one row per automatic archive
cycle. A nonunique event-ID index is created **before** removing the old unique
index, preserving the FK-supporting index needed by MySQL. The restrictive
foreign key and all existing audit rows remain in place. The shared event-row
lock, eligibility recheck and transactional metadata/audit write prevent
duplicate outcomes for one active cycle. Overlap protection is unchanged.

Rollback refuses to discard publication/restoration timestamps or repeated
audit history. Before the new schema is available, automation fails closed;
original publication and manual restoration remain compatible with the prior
schema. Old-schema restoration retains the earlier permanent-exemption behavior.

The restore confirmation now explains the 30-day window and the opt-in for
older events. No new form field, Flutter change or APK is involved.

## Verification

The focused isolated run passed **258 tests, 1,438 assertions, zero failures,
errors or skips**. It covers the new policy plus the existing automatic archive,
event review/revision, Admin cleanup and mobile archive-visibility suites.

Covered cases include unknown/earlier/exact-boundary publication; creation and
latest approval independence; explicit UTC/Manila activation; invalid/missing
activation; first approval while disabled; legacy revision protection; exact
restoration boundaries; three archive/restore cycles with unchanged previous
audits; stale-candidate and confirmation checks; non-mass-assignable metadata;
restoration-audit rollback; missing-schema compatibility; migration preservation
of legacy rows/FKs; blocked rollback when evidence exists; and participant API
history preservation.

Tests used a fresh synthetic application copy, existing copied dependencies and
SQLite memory only. The real `.env`, configuration caches, uploads and database
were not copied into it. HTTP was blocked or simulated in the policy tests;
mail/notifications used fake/test transports. No production service was contacted.
A subsequent [native MySQL verification](event-archive-policy-native-verification-2026-11-19.md)
passed **604 assertions** against synthetic records in an isolated MySQL 9.7.4
instance. The test server was stopped and its disposable data removed afterward.
That is separate from these SQLite tests; XAMPP and production remain unmigrated.

Private test receipt:

```text
storage/app/private/event-cleanup-20261118-35fa5a3f875a47e4819c23921b054b7a/policy-a771b85e055847dd8c140688b659f97b-result.json
```

The test directory uses the existing isolation helper's naming convention; this
run was performed November 19. `scripts/test-event-cleanup.ps1 -Action Policy`
is the scoped reusable test selection, not a production command.

Scoped Pint, PHP syntax, PowerShell parsing and whitespace checks passed. Blade
compilation and event-route inspection also passed in the isolated application.
The real `.env`, Git index/HEAD, `composer.lock` and `phpunit.xml` fingerprints
matched before/after. A repository-wide whitespace check also reported an
existing extra EOF blank line in `resources/css/app.css`; that unrelated file
was left unchanged.

## Later deployment and enablement

1. Review/package the new source and migration; retain the earlier immutable
   release packages as historical evidence. The compatible native database
   rehearsal is complete, as linked above; it does not deploy or enable anything.
2. Release the revised backend and scheduler with archiving OFF. Do not enable
   an older cron image: it does not understand the protected-publication cohort.
3. Separately agree the fixed activation timestamp, refresh compiled production
   configuration consistently and inspect a read-only preview using the revised
   eligibility rules. The earlier eleven-event preview is now historical and
   must not be treated as the new policy's candidate list.
4. Enable only with separate explicit approval. No deployment, setting update,
   real migration or archive action is authorized by this local implementation.

Earlier preview/release helpers hard-pin older source and deployment IDs. Do
not retarget them blindly or use their old eligibility explanation for this
policy; prepare a reviewed, policy-aware preview at rollout.
