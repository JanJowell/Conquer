# Event submission, approval and reviewed revisions

Implemented locally on October 30, 2026 (Asia/Manila), then deployed to Railway
production on November 2 after local acceptance. See
[the production release record](event-review-release-2026-11-02.md) for the exact
package, tests, backup and live verification. Historical local-only notes below
describe those earlier checkpoints. No provider key, real `.env`, Flutter source
or APK changed, and no real account, event, participant result, payment or
certificate was created for testing this workflow.

## User flow

Event Managers create their own private drafts. Saving complete setup no longer
publishes an event automatically. Managers submit complete drafts to Admins.
Admins use **Review and details** in the events list, optionally filter Approval
to Pending Review, inspect the setup, and choose **Approve & Publish** or
**Return for Changes** with a required explanation. Approval publishes that
version immediately. Registration deadlines, event timing and category rules
still determine whether participants can register.

Review state is separate from event lifecycle:

- `draft` -> `pending_review` -> `approved`.
- `pending_review` -> `changes_requested` -> corrected draft -> resubmit.
- Managers can withdraw pending submissions before editing them.
- Upcoming/Ongoing/Completed remain automatically date/time-derived, with
  unapproved setup always treated as private Draft by public event APIs.

Admins no longer create or edit event/category/checkpoint setup. They retain
their existing review, participant, payment, results, moderation and reporting
tools. Managers cannot assign an event to another manager or modify another
manager's draft. Legacy `admin` accounts still normalize to Event Manager.

## Published-event revisions

For a published **upcoming** event, its manager chooses **Create Reviewed
Revision** on Event Details. This opens a separate private draft with copied
event details, categories, payment options, route geometry/images and
checkpoints. Registrations, payments, results, awards and certificates are not
copied. There can be only one active revision per public event. Active and
historical submissions are accessible from the original Event Details page.

### Revision privacy and list presentation

The events list now contains one row per original event, retaining that event's
public status, schedule, counts and venue. Its owner sees an explicit **Draft
revision** or revision-progress link; Admin only sees the link while the revision
is Pending Review. The Approval filter can find an original event by an accessible
active revision's review state without replacing the original with a Draft row.

Unsubmitted, returned, withdrawn and discarded revision setup is accessible only
to its owning Event Manager. Admin direct links are denied, not merely hidden.
The same visibility scope covers categories, checkpoints, search and event
selectors, including payment, certificate, participant, badge and announcement
pages. Participants and the public home page never receive revision copies.
Previously published, locked revision copies remain readable as reviewed records.

Each new submission stores a read-only setup snapshot with the public comparison
baseline and record mappings. After a return or withdrawal, Admin can open
**Submitted review history** to see the submitted version and review decisions,
but not the manager's ongoing corrections. Resubmission adds a new snapshot and
does not replace earlier submissions. Older audit entries have no reconstructible
setup snapshot: their action, version and reason remain available, with an
explicit missing-snapshot notice rather than substituting mutable draft data.

The additive migration
`2026_10_30_000003_add_submission_snapshots_to_event_reviews` was applied locally
after a fresh 39-table encrypted export in
`storage/app/private/local-revision-privacy-873a818520f5471591d5777292d033ad`.
Its receipt confirms preservation of all original columns/records across 32
persistent tables and only this migration added. No historic snapshots were
fabricated, no real test records created, and no production changes made. Backup
verification covered export completeness and DPAPI/SHA-256 round trip, not a
full restore test. Automated verification: **597 passed, 2 skipped, 3822
assertions**, including revision privacy, return/resubmission, history retention
and direct/secondary-page access checks.

The published event stays visible and unchanged while its revision is edited,
submitted, returned or withdrawn. An editable revision can be discarded without
deleting review history or changing the public event. Admins can compare the
current and proposed saved setup, including category routes/payment settings.

The revision comparison now lists only changed fields in **Field / Current /
Proposed** tables, grouped by event details, category, payment option and
checkpoint. Added/removed items are labeled, copied record IDs do not create
false differences, and recreated payment rows are matched by provider just as
they are during publication. Dates, fees and booleans are formatted for reading.
Changed drawn routes include expandable coordinate lists, even when their point
counts are the same. Stale revisions and already-published revision copies have
explicit notices; this is a comparison against the current public setup, not a
historical snapshot. This display performs no setup or review-state writes.

Approval applies the reviewed setup transactionally to the original event,
preserving its public ID/slug and existing category IDs. Unchanged payment
providers retain their public method IDs even though the existing edit form
recreates private draft payment rows. Removed unused category options are
closed rather than physically deleted; removed payment options are disabled
rather than deleting identifiers referenced in historical payment payloads.
Removing a category with registrations/results/start history is rejected.

Approval rechecks current live registrations, including those received after
the revision started. Registered category identity, distance, participation
mode, group size, medical requirements and locked type-specific eligibility
cannot change. Started-category schedules cannot change, and slot limits
cannot fall below current registrations. Event type cannot change after people
register. Existing registration/payment/official-result rows are not rewritten.

Revisions are limited to upcoming events in this implementation. Ongoing and
completed events do not become editable through this mechanism. This protects
race-day/historical data rather than silently rewriting completed events.

## Enforcement and history

- Event setup routes require Event Manager access. A shared middleware checks
  ownership/editability and locks the parent event in a database transaction
  across event, category and checkpoint writes. Submitted/public setup cannot
  be changed using a forged request to a secondary endpoint.
- Review transitions lock the event and require an exact review-version token.
  Duplicate/stale approvals and approvals of withdrawn/resubmitted versions
  fail instead of publishing different content.
- A submitted-setup fingerprint detects out-of-band changes after submission.
  A revision's base fingerprint prevents overwriting a public version that
  changed after its draft was created. Stale revisions must be discarded and
  recreated; they are never silently merged over a changed base.
- Submission and approval recheck required details, open categories, usable
  payment options when paid, structured event-type fields, a future start,
  unexpired registration deadline, manager verification/active role and schedule
  conflicts. Missing structured details cannot bypass validation.
- Review history records actor ID, action, prior/new state, version, explanation,
  timestamp and setup fingerprint. Schema rollback deliberately stops for an
  explicit recovery plan, rather than making private drafts public by dropping
  their approval gate.
- Public events, registration, course-map access and event announcements respect
  the publication gate. Private revision IDs never become public event IDs.
  Participant community posts cannot be linked to private drafts/revisions.
- Private revision copies do not inflate event exports/dashboard event counts
  or manager scheduling conflicts. Shared public map images are not deleted
  when a draft replaces/removes its copy.

## Existing data and local schema transition

The new migration adds review fields to `events`, plus `event_reviews` and
`event_revisions`. Existing events receive NULL review state, explicitly marking
them as pre-workflow records, not invented Admin approvals. Their prior
visibility/readiness behavior is preserved. New ORM and direct SQL inserts
default to private Draft. Incomplete pre-workflow drafts enter review on their
first setup write; already-public events require reviewed revisions.

Fresh encrypted local database backup:

`storage/app/private/local-event-approval-ac8e7bd4cd8d4d509439ed017743d8e9`

The 37-table native MySQL export was checked for completion/table definitions,
encrypted with Windows DPAPI CurrentUser and decrypted in memory for SHA-256
round-trip verification. This is not a full isolated restoration test. Recovery
requires the same Windows user/profile and the manifest's entropy value.

The first local migration attempt stopped before changing anything because an
earlier course-map migration was also pending. It was inspected: it only adds
a nullable JSON `categories.course_map` column, required by the existing editor.
The guarded helper then applied exactly:

1. `2026_10_21_000001_add_course_map_to_categories_table`.
2. `2026_10_30_000002_add_event_approval_workflow`.

All 30 checked existing persistent tables matched their original-column/count/
row hashes afterward. Existing event review fields remained NULL, no approvers
or timestamps were fabricated, and both new history tables were empty. Existing
category map values remained NULL; no geometry was invented. No unrelated
migration, data seeder or production operation was run. The backup directory
contains the guarded schema-transition receipt and before-state fingerprints.

The local Create Event and Event Details screens also rendered successfully
against MySQL in a read-only transaction with an in-memory identity/session.
No real account or session was created, and rendered HTML/private records were
not printed.

## Verification and handoff

The complete backend suite passed: 580 tests, two skipped, 3,649 assertions.
The 18 dedicated workflow/migration cases are included in that total. PHP
formatting, frontend build, Blade/route compilation, and all three browser
regressions also passed. Temporary Blade/route caches were cleared. Test fixtures that
represent existing public events explicitly identify themselves as pre-workflow
records; tests that need a newly published event exercise the actual submission
and approval service. No production-only bypass was added for tests.

The three browser checks (venue, cutoff, authentication) passed sequentially.
The first concurrent run had a venue selection timeout; the sequential recheck
passed without changing venue code. A first regression run also hit the default
128 MiB test-process memory limit while rendering failures; test debugging was
disabled in `phpunit.xml` and the test process used a temporary 512 MiB limit.
No real PHP configuration or `.env` was changed.

The local signed-in checks were subsequently accepted by the user. The November 2
production release and its read-only verification are recorded in the linked
release document above. No signed-in production submission/approval was performed
by the release scripts; that remains a normal-use acceptance check, without
creating or approving real events solely for testing.

## Readable pending-submission summary — November 4, 2026

Implemented locally; this UI follow-up has not been pushed or deployed. The
November 2 workflow release and later payment-selector deployment do not contain
this follow-up.

For an initial event submission in Pending Review, **Complete submitted setup,
routes and payment options** now shows labeled event details instead of raw JSON.
Categories, payment options and checkpoints have individually expandable cards.
Dates, times, fees, booleans, manager/venue names and event-specific fields reuse
the existing comparison formatter. Drawn routes show point/checkpoint counts,
with coordinate details available on expansion. Leading-zero account numbers,
multiline values, zero values and disabled options remain represented.

The view uses escaped Blade output, semantic definition lists and native HTML
disclosures, without new JavaScript, third-party calls or editable fields. Desktop
uses two value columns; narrow screens use one. Long values wrap within the page.

This is presentation-only: it keeps the review page's existing setup data source,
submission snapshots, setup fingerprints, review versions, authorization and
Approve/Return/Withdraw controls unchanged. Published-event revision comparisons
and submitted-review history are not replaced. No migration, real-record write,
credential change, Flutter change or APK build was performed for this follow-up.

Local verification used synthetic fixtures and an in-memory SQLite database:

- Complete backend suite: **718 passed, 2 skipped, 4,689 assertions**.
- Focused summary/workflow/comparison/privacy checks: **42 passed, 400
  assertions**, included in the complete suite.
- Event-form persistence audit: **23 passed, 225 assertions** separately.
- Headless-browser check: passed with page scripts disabled, keyboard expansion,
  collapsed category/payment/checkpoint cards, expandable route coordinates,
  desktop columns and 375/320-pixel layouts with long text.
- Frontend production build, targeted PHP formatting and syntax, JavaScript
  syntax and whitespace checks passed.

Next acceptance check: refresh a local Pending Review event and expand the
submitted setup as its Event Manager and as Admin. A separate release decision
is required before this display change reaches production.
