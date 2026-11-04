# Event form requirements and direct review submission

Implemented locally on November 4, 2026 (Asia/Manila). This replaces the earlier
local-only requirement-label presentation. These follow-ups have not been committed,
pushed or deployed. Production remains on the earlier readable-summary release.

## Event Manager flow

- **Submit for Review** is the primary Create/Edit action. It validates and saves
  the setup, then uses the existing review workflow to enter **Pending Review**.
  It never publishes an event; only Admin approval does that.
- **Save Draft** is secondary. It keeps the setup private and permits missing
  submission-only information. Existing basic event and category validation still
  applies; it is not an unrestricted save of invalid data.
- Required fields use a small red **asterisk** instead of `(Required)`. The word
  Required remains available to screen readers. Optional markers stay unchanged.
  No separate `* Required fields` legend/note is shown, per the user's request.
  Requirements apply only to the active event/category selections. Checkbox
  settings remain Optional, not instructions to enable every checkbox.
- Existing banners count toward readiness. A replacement upload is not required
  merely to edit and submit a saved draft.
- Event End Time and a positive whole-number Slot Limit for every category are
  required on submission. Each category also needs a saved/new map image **or**
  a valid applied drawn course. Either is sufficient; both may be retained.
  The grouped map requirement uses one asterisk, not two independently required
  upload/editor inputs. Existing categories can upload a replacement image inline.
- Payment options are Required only when an open category has a positive fee.
  The marker updates when fees, statuses or category rows change. Free events
  need no payment option; blank/zero fees retain their free-registration behavior.
- Submitting an incomplete form shows red borders and short inline messages,
  then focuses the first invalid field. A fresh form and Save Draft do not flag
  every missing submission requirement. Client-owned feedback clears as fields
  are corrected or become inactive; preserved help descriptions stay associated.
- Failed requests return with the current validation messages and retained text
  and selections. A shared accessible error summary links to matching controls;
  matched server errors are highlighted, too. Newly
  selected files must be chosen again, as browsers do not restore file inputs.
  Global review errors remain visible even when they have no matching control.

No new native `required` attributes were added to event-level fields. Server
validation is authoritative, including when JavaScript is absent or bypassed.
Existing category/custom-duration browser constraints remain in place. The
secondary button uses `formnovalidate` but does not bypass server validation.
Client checks derive from the actual rendered requirement markers plus existing
native constraints, rather than maintaining a second event-type schema. Disabled,
hidden and readonly controls are ignored. A saved banner satisfies the image
requirement; payment choices and at least one open category are checked as groups.
Changing requiredness never alters the entered values or selected account tokens.
Complex schedule/eligibility rules and all authoritative validation remain on the
server. Event start/end are compared using both date and time, so an overnight
event may end at an earlier clock time on the next day. The new requirements do
not change permissions, review transitions, database schemas, secrets or public
API visibility.

The red-border CSS is scoped to the event setup form, including focused controls.
The review-form script URL carries its file modification version to avoid serving
a cached older presentation after a later deployment.

## Server and data safety

`EventController` accepts an explicit `intent` of `draft` or `submit`. Missing
intent preserves the previous private-draft behavior for previously opened forms
and older integrations. An unsupported intent is rejected. Edit submissions also
require the current `review_version`; stale or missing versions do not save or
submit changes.

Submission requires a description, valid future event start, explicit end date/time,
unexpired registration deadline, a saved or newly uploaded banner, the selected
event type's required publication information, and at least one open category.
Every category, including closed/draft rows, must have a positive whole-number
slot limit and either a trusted saved/newly uploaded map image or valid drawn
course data. Existing group minimum-size checks still apply. Client-supplied image
paths do not count; an existing image must belong to this event's saved category.
Omitting saved geometry preserves it, while explicitly clearing it requires an
image alternative. Malformed or degenerate supplied geometry is still rejected.
Existing venue, category, schedule, manager availability and payment-selection
validators still apply. A supplied banner path cannot replace the saved banner or
stand in for a required new upload on Submit for Review. Enabled payment setup
is checked for paid open categories before the authoritative review transition.

Saving event/venue/category/payment changes and submitting for review share one
database transaction. Any late validation/review failure rolls back those changes
and review history. New banner and newly added category-map uploads register
rollback cleanup, including replacements on existing categories; old/shared files
are not deleted. This also covers rollback of
the outer ownership-lock transaction on edits.

`EventSubmissionRequirements` is deliberately separate from public readiness.
Both submission and approval check the persisted setup, including categories
omitted from an additive edit request, and keep category schedules within the
explicit event start/end window. Existing approved/legacy public events are not
retroactively hidden or modified for missing new requirements. Their new
revisions must meet the requirements before submission. Historical pending
submissions that are incomplete cannot be approved; return them for completion
and resubmission. Private readiness guidance includes the new requirements.

The existing role/ownership rules, review locks, setup hashes, immutable submission
history, Admin approval and revision application are unchanged. Returned and
withdrawn drafts can use the same direct submission action. A pending revision
does not change its currently published original. No migration is required.

## Earlier asterisk/direct-submission verification

Verification uses synthetic records, in-memory SQLite and fake public storage,
not real accounts, events or payment records:

- **779 backend tests passed, 2 skipped, 5,443 assertions**.
- **61 focused submission/label cases passed, 754 assertions** (included above).
- **All 23 distinct persistence/revision audit cases were verified across runs,
  225 assertions**. The isolated run passed 22 cases (220 assertions) and hit one
  test-browser startup error, not a value mismatch. That remaining case passed
  its separate retry (5 assertions). This was not a single uninterrupted green run.
- **3 browser checks passed**: the expanded review/draft form check, venue
  dropdowns, and payment selection. They ran sequentially using local fixtures.
- Frontend build, Blade compilation, targeted Pint, PHP/JavaScript syntax and
  whitespace checks passed. Generated development view caches were cleared.

The browser fixtures were corrected to use the Event Manager role and a complete
synthetic venue rather than recording already-prevented form submissions. The
border-color assertion waits for the existing CSS transition to finish. Initial
persistence audits hit two Chrome startup errors while another browser suite ran;
the isolated run had one startup error in a different case, which then passed
its targeted retry. No saved-value
assertion failed, and no application assertion or browser timeout was weakened.
The error-summary redirect test carries the real browser session identity so
JSON-serialized validation errors are restored on the next request.

The focused cases cover all six event types, required/optional labels, successful
private submission and subsequent Admin approval, draft/legacy behavior, missing
and malformed fields, dates, conditional categories, free/paid events, retained
input, stale versions, ownership, returned/withdrawn drafts, reviewed revisions,
immutable history and rollback cleanup of new uploads only.

The browser fixture renders the real shared form and its scripts without persistent
database writes. The saved-banner edit case prepares only a guarded, ephemeral
in-memory SQLite schema for the real model-readiness checks. All fixtures use the
actual Event Manager role and synthetic venues/payment accounts. It checks the
actual submitter intent, Save Draft browser-validation bypass, red border after
the existing CSS transition, clearing/correction, whitespace, optional/inactive
fields, saved banners, existing cutoff controls, dynamic payment requirements and
category rows, unchanged account tokens, error escaping/focus/descriptions, and
desktop/375/320-pixel layouts.

Reports: `storage/app/private/event-asterisk-{focused,backend,form-audit}.{xml,log}`
and `storage/app/private/event-asterisk-browser.log`. The single-case audit retry
uses `storage/app/private/event-asterisk-form-audit-retry.{xml,log}`.
The full backend result is a working-folder check, not verification of a separately
prepared production release package. No real `.env`/credential change, production
action, Flutter change or APK build is part of this follow-up.
The original local branch/HEAD and Git index were left unchanged; unrelated
working-folder edits were preserved.

Next manual checkpoint: refresh local Create Event and Edit Event as an Event
Manager. Try incomplete submission, a complete synthetic submission, and Save
Draft; confirm the private/review state and error presentation. Use a private test
event rather than publishing a real event for this check. Deployment preparation
remains a separate decision after local acceptance.

## End time, capacity and map-alternative follow-up verification

This follow-up was checked separately with synthetic records, fake storage and
an explicitly isolated in-memory SQLite database:

- **817 backend tests passed, 2 existing skips, 5,655 assertions** in the final
  full-suite run. Its PHP process used a temporary `memory_limit=512M`; the first
  attempt exhausted the default 128 MB during an unrelated PDF-rendering test.
  No persistent PHP or application configuration was changed.
- **99 focused form/requirements cases passed, 966 assertions**, included in the
  full suite. The seven existing review/payment fixture suites also passed
  separately (185 tests, 1,447 assertions).
- Focused coverage includes both map alternatives and both together, existing
  category uploads, trusted saved-image fallback, malformed/degenerate/JSON-null
  routes, forged paths and foreign category IDs, explicit route clearing, missing
  end/slots/maps, positive integer slots, omitted closed/draft categories,
  rollback cleanup, retained old images, overnight schedules, category bounds,
  unchanged approved/legacy API visibility, revisions, and approval revalidation.
- The expanded local browser check completed its form assertions and six named
  saved-fixture subtests. It exercised red highlights/focus, both map choices,
  invalid/unapplied routes, cloned/closed categories, saved maps and inline image
  uploads, whole-number capacity, overnight event times, payment selections and
  responsive layouts. However, its Node runner did not exit cleanly because an
  owned Windows Chrome process/profile remained locked after cleanup. This is
  **not recorded as a clean completed browser-suite run**. Additional adjacent
  browser checks and the persistence audit were not rerun for this follow-up.
- The five pure course-map-data JavaScript tests passed. After the browser-run
  limitation was recorded, narrowly scoped cleanup stopped its verified owned
  runner/worker, confirmed Chrome had exited, removed its three known temporary
  profiles, and found no matching test processes remaining. No unrelated process
  or directory was targeted. Observed browser output is retained in
  `storage/logs/required-setup-browser-verification.log`.
- Frontend production build, targeted Pint, PHP/JavaScript syntax and scoped
  whitespace checks passed. The original Git HEAD and index remained unchanged.
  Blade compilation passed and generated view caches were cleared.

Current PHP reports are `storage/logs/event-required-setup-{focused,backend}.{xml,log}`
and `storage/logs/event-required-window-fixtures.{xml,log}`. The earlier persistence
and three-browser results above are historical verification, not new runs of this
follow-up. No deployment, migration, real event/payment data mutation, credential
change or Flutter/APK work was performed.

Manual checkpoint: refresh the local create/edit form as an Event Manager. Leave
End Time, a category Slot Limit and its map blank, then click Submit for Review
to check red feedback. Verify either one map upload or one applied drawn route
clears the grouped requirement, and Save Draft still accepts incomplete setup.

## Submit for Review confirmation follow-up

The shared Create/Edit Event form now presents a confirmation only after client
required-field checks and existing submission guards allow the attempt. Its
actions are **Keep Editing** and **Submit for Review**. It explains that submission
sends the setup to Admin and locks editing unless withdrawn; submission itself
does not publish anything. Revision copy explicitly says the current published
event stays unchanged until Admin approval.

The native dialog has an accessible title/description, initially focuses Keep
Editing, keeps Tab/Shift+Tab within its actions, supports Escape cancellation and
returns focus to the original submit button. Cancel leaves text, category values,
files, applied geometry and selected payment tokens unchanged. Enter and
programmatic `requestSubmit()` also go through confirmation. If the dialog API is
unavailable, a native confirmation is used and its accepted request is deferred
until the initial submit event completes.

Confirming calls `requestSubmit` with the original review submitter, re-running
native validation and all form guards while preserving `intent=submit`. It does
not use raw `form.submit()` or a permanent confirmation bypass. Only an accepted
confirmed attempt marks the form busy and blocks repeated requests. The native
review submitter remains enabled for serialization; disabling it would drop its
intent and could incorrectly save a draft. Its busy appearance/ARIA state and the
disabled dialog confirmation action provide feedback while handlers block further
clicks/submits. Back-forward restoration resets that transient UI; repeated
Livewire initialization does not duplicate the controller.

Save Draft keeps its existing direct action, `formnovalidate` behavior and server
validation. The map editor still blocks unapplied route drafts, but a cancelled
submission/confirmation no longer incorrectly suppresses its unsaved-route
warnings. No Maps environment settings, backend review rules, ownership/version
checks, schemas, real records or deployment settings were changed for this
confirmation feature. Confirmation is a client UX step, not a new server security
boundary; requests without JavaScript retain authoritative server validation.

Confirmation verification (synthetic local fixtures only):

- **820 backend tests passed, 2 existing skips, 5,711 assertions**. The focused
  confirmation/submission/requirements/revision selection passed **108 tests,
  1,121 assertions**, included in that full run. Both use explicitly forced
  in-memory SQLite, fake storage and array-backed test services.
- **9 confirmation browser cases passed**, including the parent and eight named
  scenarios. They verify invalid setup before the dialog, direct Save Draft,
  preserved fields/files/payment selections on cancellation, Tab/Shift+Tab,
  Escape/focus restoration, revalidation, other form guards, Enter, actual local
  multipart POST serialization with `intent=submit`, duplicate suppression,
  back-forward recovery, repeated initialization, revision messaging, narrow
  layouts and the native-confirmation fallback.
- **20 adjacent JavaScript/browser cases passed**: course editor (Google mocked),
  course-map data, the expanded requirement/draft form test, venue dependencies
  and event-payment selection. Cancelled submissions retain staged-route
  navigation warnings. All browser runners in this follow-up completed with
  exit 0; the previous Windows cleanup limitation did not recur. The two touched
  older browser runners now request graceful Chrome protocol shutdown before
  their existing child-process cleanup.
- Frontend production build, PHP/JavaScript syntax, targeted Pint and scoped
  whitespace checks passed. No third-party browser packages were installed.

PHP reports: `storage/logs/event-review-confirmation-{focused,backend}.{xml,log}`.
Browser reports: `storage/logs/event-review-confirmation-browser.log` and
`storage/logs/event-review-confirmation-adjacent-browser.log`. Actual POST checks
target only the ephemeral local test server, which returns 204 and does not write
to Laravel. The confirmation runner blocks external requests, and the adjacent
course editor uses a mocked Google API. No live Maps requests or real submissions
were performed. The separate persistence audit was not rerun in this follow-up.

Next manual checkpoint: refresh a complete private local test draft, click Submit
for Review, cancel and check the preserved values, then confirm and verify Pending
Review. No push or deployment is included in this work.

Release integration correction: admin pages use their own Tailwind layout rather
than the general Vite app stylesheet. The scoped red-border and busy-button rules
therefore live in `public/css/event-review-submit.css`, explicitly loaded once by
the shared event form with a file-version query. The older copies were removed
from `resources/css/app.css`. The real Create/Edit/Revision rendering tests check
this stylesheet reference, and the browser fixtures serve it as a real asset.
This does not change Maps configuration or unrelated admin-page styling.
