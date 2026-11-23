# Events and Archived Events navigation - November 18, 2026

Later local follow-up: [mobile discovery archive filtering](mobile-event-archive-visibility-2026-11-18.md)
is now a separate implementation. The navigation-only scope and verification
below describe the earlier change, not the current mobile filtering behavior.

Local navigation change only. No push, deployment, real event cleanup, database
migration, mobile/scanner change, or public-visibility change is part of this work.

## Behavior

The Events management page now uses two server-rendered navigation links styled
like Payment Records / Payment Options:

- **Events**: non-archived original events, including completed events.
- **Archived Events**: archived originals within the viewer's existing scope.

The Event List dropdown and its All events option were removed. Each section has
its own heading and selected `aria-current="page"` link. These are ordinary links,
not JavaScript-only tabs; small screens can wrap them.

Search and Event Type remain available in both sections. Status, Approval and
the authorized Create Event button remain on **Events only**. Archived Events
uses a simpler search/type layout, with no changes to its table or authorized
actions. Switching to Archived Events drops Status/Approval while retaining
Search/Event Type and resetting pagination. Stale or crafted Status/Approval
parameters are ignored server-side for archived results and omitted from later
links/forms, so invisible filters cannot hide results. Returning from Archived
Events does not revive those discarded filters.

Filter submissions retain the selected section using a hidden archive parameter.
Clear removes the search and filters but stays in the selected section. Pagination
retains the section, supported filters and existing newest-created-first ordering.
Archived Events has a specific empty-state message. The regular Events section
retains its previous filter and creation permissions.

Existing `archive=archived` and `archive=active` URLs continue to work. Historical
`archive=all` links now select Events instead of mixing archived and non-archived
records; subsequent navigation uses the canonical active parameter. Unknown
archive values remain rejected. If the archive schema is absent, the existing
fallback list/notice remain usable and archive navigation is not offered.

## Unchanged boundaries

- Archive, Restore and eligible empty-draft cleanup remain Admin-only.
- Event Managers can read assigned archived events, not other managers' events,
  and do not receive archive/restore or new deletion controls.
- Permanent deletion remains restricted to eligible empty, never-submitted drafts.
  No delete button was added to Archived Events.
- Existing cleanup confirmations, activity logging, review/revision privacy,
  event setup, staff access and operational/scanning rules are retained.
- Public website and mobile discovery still have their previous archive behavior.
  Automatically hiding archived events from mobile browsing is a separate,
  not-yet-implemented change. Participant records and My Activity were not edited.

## Local verification setup

Verification uses a dedicated private application copy with a pinned synthetic
environment, SQLite in-memory data, and array-backed test services. The real
environment/configuration caches are not copied into that application.

Private evidence and preserved starting versions of overlapping source files:

```text
storage/app/private/event-cleanup-20261118-d78e35cfc38c44d6b9f9be37c07225ad
```

No credentials are included in this document. Existing recovery backups are
retained. The updated dated-folder allowlists continue to require a dedicated
private test directory, synthetic environment fingerprint and loopback browser
origin. Test-report passed counts now exclude failures/errors as well as skips.

## Initial navigation verification result

- **304 focused backend tests passed, 2,052 assertions**, no skips, errors or
  failures. This includes **16 new navigation regressions** plus the existing
  cleanup, list-order, review/privacy, checkpoint/history, finish and certificate
  cases. The new cases check both staff roles/sections, filter and Clear links,
  pagination/sorting, assignment boundaries, empty states, legacy URLs and guest/
  participant denial. Business-record comparisons permit the existing normal
  request-audit entries while asserting that no cleanup action was performed.
- **Seven real headless-browser scenarios passed** (eight Node passes including
  the parent test), retaining confirmation cancellation, Escape/focus, required
  acknowledgement, method/fingerprint serialization and duplicate-submit guards.
  These browser scenarios exercise synthetic cleanup confirmations; they are not
  a signed-in real-account test of the new navigation.
- Copied Blade compilation and event-route inspection passed. PHP syntax/scoped
  Pint, JavaScript syntax, PowerShell parsing and scoped whitespace checks passed.
- All **17 protected source/settings/index hashes matched**, including cleanup
  policy/controller, event model, public/mobile APIs, scanning implementations,
  routes, migration, dependency definitions, `.env` and Git index. Unrelated
  pre-existing edits were preserved.

The first browser run rejected the new dated folder before rendering; its narrow
fixture allowlist was updated consistently with the test runner. Three initial
backend assertions incorrectly included normal request-audit entries in the
unchanged-business-data comparison; the assertions were corrected without
disabling logging or weakening cleanup checks. The complete focused suite and
browser checks then passed. No failing case was skipped.

Final protected receipt: `verification.json`. Successful focused report:
`focused-2e3f3afe9e9144deb5f6b490a1420dd6-result.json`, with JUnit XML and private
test/view logs alongside it. No full backend-suite rerun is claimed for this
navigation-only change. No real database was booted or mutated by these tests.

Next manual check: refresh local Admin Events, switch between the two sections,
and verify Filter/Clear remain in the selected section. Review action dialogs
with Cancel only; do not archive/delete real events merely to test navigation.

## Archive controls simplification verification

The later controls cleanup removed Create Event, Status and Approval from
Archived Events only, retaining Search, Event Type, Filter/Clear and the table.
Ten additional regressions cover stale draft/upcoming/unknown/array-shaped hidden
filters for both staff roles and the regular Events filters/creation permission.
Earlier navigation assertions were updated for the intentionally narrower set of
archive filters; permission and record-protection assertions remain in place.

- **314 focused backend tests passed, 2,172 assertions**, no failures/errors/skips.
- **Seven headless-browser confirmation scenarios passed** (eight Node passes
  including the parent test); synthetic submissions only.
- Copied Blade compilation/event-route inspection, PHP syntax/scoped Pint and
  scoped whitespace checks passed.
- All **17 protected file hashes matched** the fresh controls baseline, including
  settings/key, Git index, cleanup policy/routes, public/mobile and scanning code.

The existing isolated synthetic application was reused with the current scoped
source overlay. No real database, migration, event action, production access,
deployment or mobile/scanner build was used. Preserved starting versions are in
`controls-original-source/`; final receipt is `controls-verification.json`.
Successful focused report:
`focused-5e59e23295d04920baff078eac5e0f73-result.json` (with JUnit/log alongside).
The earlier `verification.json` records the initial two-tab implementation.

Refresh local Archived Events to check the simpler layout. The ordinary Events
section should still show its filters and Create Event for authorized managers.
Authenticated manual visual verification remains the user's next check.
