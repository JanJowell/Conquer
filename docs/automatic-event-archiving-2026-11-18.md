# Automatic completed-event archiving - November 18, 2026

Historical initial policy. The [November 19 publication/restoration policy](event-archive-publication-policy-2026-11-19.md)
supersedes its eligibility and permanent-restoration-exemption rules locally.
That later change is not deployed or enabled yet. The evidence below describes
the original implementation, not the new policy.

Implemented locally, disabled by default. A later
[local database preparation and read-only preview](automatic-event-archiving-local-preparation-2026-11-18.md)
has now applied the migration to actual XAMPP. Automatic archiving remains OFF;
no real events were archived, restored or deleted. No production scheduler was
configured, and nothing was pushed, deployed or built into an APK.

## Policy

- A published, public-ready original event must be effectively Completed and
  have ended at least **30 days** ago. The fixed policy has no shorter CLI option.
- Retention is measured from `event_end_date` plus `end_time`, not creation,
  submission, approval or start date. The application timezone is used. Exactly
  30 days qualifies; one second short does not. The job runs daily, so actual
  archiving happens on the next enabled scheduled run after eligibility.
- Legacy events without an end date use `event_date`; without an end time they
  use the existing end-of-day lifecycle rule. They are not archived early.
- An active draft, returned or pending revision blocks automatic archiving.
  Unpublished/incomplete setup, upcoming/ongoing events, revision copies,
  already-archived rows and restored/exempt rows are skipped.
- Admin restoration sets `auto_archive_exempt_at`. This is an enduring exemption,
  not a one-run delay; no automatic reset or form control clears it. Admin can
  still manually archive a restored event under the existing cleanup safeguards.

The candidate list is only a prefilter. Each event is reloaded under the same
parent-row lock used by cleanup/review/setup writers, then all eligibility is
checked again. The event metadata update and system audit insert are one
transaction. Failure to write the audit rolls back that event's archive. A run
can partially complete across events; a later run safely skips completed work.
The schedule has overlap protection, and row locks plus unique per-event audit
evidence protect against duplicate automatic outcomes from other invocations.

This is archive visibility, not deletion or a new lifecycle status. The worker
does not save event setup through lifecycle/announcement hooks, cancel an event,
change registration status, alter results/payments/feedback/certificates, edit
checkpoint evidence or change staff scanning authorization/pilot gates.
The previously implemented mobile discovery filter excludes archived events;
participant history and the existing public detail contract stay intact.

## Database and audit evidence

Migration **applied locally after native rehearsal, not deployed to production**:

```text
2026_11_18_000001_add_automatic_event_archiving.php
```

It adds a nullable, indexed `events.auto_archive_exempt_at`, which is not mass
assignable event setup, and `event_auto_archive_logs`. Each automatic log stores
the event ID/title, event end timestamp, cutoff timestamp and archive timestamp.
Required audit dates use DATETIME without implicit defaults/updates for legacy
MariaDB strict-mode compatibility, verified in the later native preparation.
It is system evidence, not an action falsely attributed to a human Admin.
Automatic archives leave `archived_by_user_id` null. Human cleanup continues
using the existing Admin activity log and account/confirmation safeguards.

The new audit table has one automatic outcome per event and restricts deletion
of its parent event. Empty-draft cleanup also checks this evidence if the table
exists. The migration's rollback refuses to erase system audit evidence or
restore exemptions instead of silently dropping that protection.

For earlier Admin restores, the migration recognizes exact successful
`event.cleanup.restore event=<id>` activity records and marks currently
unarchived originals exempt, without changing their other event fields or
`updated_at`. It ignores malformed/unrelated audit text. A restore performed
before this migration still works with the previous schema and is picked up
from its successful audit when migrated. Previously deleted/missing audit
history cannot reconstruct an old restoration; those cases need review before
enablement. New exemptions persist even if the human audit actor is later removed.

## Command and scheduling

```text
php artisan events:archive-completed --dry-run
php artisan events:archive-completed
```

The preview counts currently eligible events without writes, notifications or
new audit rows, including while the write gate is disabled. Both preview and
enabled writes require the archive/exemption/audit schema; missing schema fails
closed. The command cannot migrate or create configuration on demand.

`EVENT_AUTO_ARCHIVE_ENABLED=false` is documented in `.env.example`; no real
environment file was changed. Only an explicit true enables writes. Both the
command/service and schedule respect the gate. The daily schedule is **04:00 in
the application timezone**, with overlap protection. Registering a schedule in
source does not install a scheduler on Windows or Railway.

Later reviewed production release steps, not performed here (local preparation
is already complete as linked above):

1. Review source and take/verify the normal pre-release database backup.
2. Deploy with the gate still off and apply the reviewed migration.
3. Review the dry-run count and eligible events, including historical restores.
4. Verify the intended production scheduler runner and timezone, then explicitly
   approve enabling the gate. Do not add an unauthenticated HTTP trigger or
   assume a running web service executes scheduled commands.
5. Verify the next run, system audit evidence and mobile list refresh. Keep the
   gate off if the migration, preview or scheduler is not ready.

## Isolated verification

Private synthetic verification root:

```text
storage/app/private/event-cleanup-20261118-943333cb9ccc451caf84f5dbca821557
```

The test copy uses SQLite `:memory:` and the pinned synthetic environment;
it never loads the real `.env` or cached configuration. Stray HTTP is blocked,
notifications are faked and no real database/scheduler/production endpoint is
used. Starting overlapping files and 22 protected-file hashes are retained.

The tests cover exact date/time boundaries, multi-day events, timezones,
legacy fallbacks, exclusions and active revisions, strict opt-in, dry-run purity,
sequential repeat runs, stale candidates, audit rollback, Admin restoration,
mass-assignment/fingerprint protection, old restore migration compatibility,
rollback refusal, participant payload preservation and chunk boundaries.

## Verification results

- **511 focused backend tests passed**, with **3,284 assertions**, no failures,
  errors, warnings or skipped tests, across 15 feature-test files. This includes
  56 new automatic-archiving regressions and the prior mobile archive tests.
- All seven loopback-only confirmation-dialog browser scenarios passed (eight
  Node passes including their parent test). No real cleanup request was sent.
- Isolated Blade compilation and event route inspection passed.
- PHP syntax, scoped Pint, PowerShell syntax and scoped whitespace checks passed.
- All 22 protected files matched their starting hashes, including the real
  environment, Git index, participant API/resources, review/scanning code and
  original archive migration. Neither the real environment nor current process
  contained the new opt-in flag; the shipped configuration defaults off.

Private evidence:

```text
focused-3736998d78654730b704d19d75a2e126-result.json
automatic-archive-browser.log
automatic-archive-verification.json
```

The first run caught an invalid null-date fixture; the broader run then caught
the new audit FK missing from the explicit cleanup dependency list. The fixture
and integration guard were corrected before the final passing run. No safeguards
were disabled to make the tests pass.

The later preview-lock correction added one regression: 512 focused tests now
pass, followed by native MariaDB rehearsal and real local migration/preview as
documented in the local preparation report. These are not a live parallel-worker,
full backend-suite or production scheduler/phone verification. Real write
enablement and production deployment remain pending.
