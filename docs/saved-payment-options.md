# Saved payment options

Base library deployed and verified November 3, 2026 (Asia/Manila); see
`docs/payment-options-release-review-2026-11-03.md`. The November 4 selector
follow-up below is verified; that review records its release status. No provider keys,
real `.env`, Flutter source or APK were changed.

## User flow

Payments now has **Payment Records** and **Payment Options** tabs. Payment
Records retains the existing transaction/verification flow. Payment Options
provides reusable accounts with a provider, account details and instructions.
Each manageable card has Edit details and Enable/Disable actions. Availability
is no longer an Add/Edit checkbox: new options are always enabled, and detail
edits preserve the stored status (even if an old form submits a checkbox value).
There is no separate Option name field. On creation or
an explicit edit save, the server generates the display label from the provider
and account name (for example, `GCash — Juan Dela Cruz`); PayMongo uses
`PayMongo Online Checkout`. Submitted custom labels are ignored, including
values sent by an older form. Only the generated label is capped at the
existing 100-character column limit; the full account name remains intact.
Existing labels are not backfilled or changed simply by viewing an option.
Explicit saves regenerate them while retaining normal version/ownership checks.
Account numbers remain available in details to distinguish matching names.
No migration or live account rewrite is needed for this simplification.

The Payment Options index uses a concise subtitle and compact cards headed by
provider plus account name, with the account number directly below and short
Shared/Personal and Enabled/Disabled badges. Display headings are derived from
the actual account details, including older options; stored custom labels are
not rewritten by this display change. PayMongo shows Online checkout instead
of receiving-account details. Instructions (when present) and page-level help
use native keyboard-accessible disclosures, collapsed by default. Edit details
and Enable/Disable stay outside disclosures; scope, tokens, CSRF forms,
pagination and stale-action feedback are unchanged.

Enable/Disable uses a CSRF-protected PATCH form with an explicit desired state
and version, not a blind toggle or mutating GET. Ownership is checked before
and again under the option row lock. Only availability and version are changed;
labels, account details and event copies are not regenerated. Changed state
increments the version so stale card clicks, older detail forms and older event
selection tokens cannot overwrite it. A request for the already-current state
with a current version is a no-op. Stale requests display an error and Reload
Payment Options link. Admins manage shared cards; managers (including legacy
manager accounts) manage their own personal cards. Personal options belonging
to another manager remain inaccessible to Admins as before. Card controls do
not require JavaScript and do not add a delete operation or migration.

Admin-created library options are shared with Event Managers. Event Managers
(including legacy `admin` accounts) create personal options visible only to their
own account. Admins can manage shared options, not another manager's private
library entries; managers can read shared options but cannot edit them.
Ownership/sharing cannot be forged through submitted fields. Previously personal
options do not automatically become shared if their owner is promoted to Admin.

Create/Edit Event now uses checkboxes instead of editable payment-account rows.
Available shared/personal options show provider and account name in a compact
list. Account numbers, instructions and library labels are available in native,
keyboard-accessible "View account details" disclosures, collapsed by default.
Saved event copies are labelled in the same list. Selection tokens and default
checked states are preserved. A small management link and short paid-event note
replace the previous explanatory paragraphs and separate list headings.
Fees stay on categories. The current checkout/database limit remains **one
account per provider per event**. Managers may save an incomplete private draft;
paid open categories still require usable payment options before submission.

The Manage Payment Options link opens another tab to avoid discarding the event
form. After adding a new option there, save the draft and reopen it to refresh
the selection list. This is not an automatic unsaved-form refresh.

## Provider-specific account fields

The local library Create/Edit form now requires a registered mobile number for
GCash and Maya: exactly 11 ASCII digits starting with `09`, stored as text. This
form deliberately uses the local `09XXXXXXXXX` format; international `+63`,
spaces and dashes receive a format error rather than silent conversion. This
is the mobile-wallet flow, not a Maya Savings bank-account number. Provider
references: [GCash Send Money](https://help.gcash.com/hc/en-us/articles/360017566614-How-to-send-money-to-another-GCash-account)
and [Maya account-number guide](https://www.maya.ph/user-guide).

Bank Transfer requires an account name, account number and instructions asking
the manager to identify the bank. There is no assumed universal bank-number
length or wallet regex; leading zeros are preserved. Requiring instructions
does not automatically verify that a real bank name was supplied.

PayMongo hides/disables manual account fields; the backend excludes forged
values and stores those two fields as null. Its existing checkout configuration
is unchanged. Switching providers preserves entered values in the form, updates
labels/required rules, and excludes hidden fields from submission. Wallet fields
now cap typing at 11 characters and inspect paste contents before the browser
can shorten them. An overlong paste is rejected in full with an inline message;
the previous value is preserved and saving stays blocked until an edit or
provider change clears that message. Selection replacements are measured
against the resulting full value. Wallet drag-and-drop is rejected to avoid
ambiguous drop positions. Bank input retains its 255-character limit and normal
paste behavior. Switching to a wallet preserves any existing overlong value for
correction, rather than truncating it. Server validation also applies
without JavaScript; immediate field switching and the paste-aware typing cap
need JavaScript. Without it the field retains the full input for pattern/server
validation, avoiding a cap without the paste safeguard.

These rules apply when creating/editing library options. No existing library
row or event copy is automatically rewritten; unchanged legacy event copies
retain their existing compatibility rules. An older invalid library option
must be corrected to save it through the new form. No account ownership or
existence check, provider call, paid service, schema migration or live payment
is introduced.

## Copies, disabled options and review

Selecting a library option copies its provider/account/instructions into the
existing event-owned payment-method table. There is no live catalog join or
automatic update. On edit, the selector collapses identical choices using an exact
comparison of provider, account name, account number and instructions. It prefers
the saved event copy, keeping its selected state and token. It does not compare
only labels, strip leading zeros or treat different instructions as equivalent.
Library edits/disabling never update existing copies or payment history.

With JavaScript, selecting a different account unchecks other accounts for that
provider in the same selector; other providers remain selected. Unchecking all
accounts remains possible. Native keyboard activation follows the same rule,
and a screen-reader status announces replacements. The handler supports initial
loads and Livewire navigation without duplicate bindings. Without JavaScript,
identical accounts are still collapsed, but replacing a different account requires
manually unchecking the old one. Server validation continues to reject multiple
accounts per provider, including forged/bypassed requests.

After validation errors, an explicitly selected library token takes precedence
over an identical unchecked saved copy, so the submitted choice is not silently
changed into another source/version. If invalid old input selected multiple
identical accounts, those conflicting checked choices remain visible with the
error until the manager resolves it. Neither rendering nor initialization silently
normalizes invalid submitted selections. Empty selections and disabled saved
copies are not automatically enabled by an identical enabled library option.

Unchecking an event copy disables it rather than deleting its identifier or
payment history. Existing disabled copies survive unchanged saves and can be
explicitly selected to enable them. If an older event has category-only payment
details, an explicit empty selection creates disabled event copies so legacy
category accounts do not unexpectedly reactivate.

Library availability applies to **new selections**, not emergency revocation of
all existing event accounts. To change a published event, its manager must create
a reviewed revision. Admin reviews the copied details with that submission.
Submission snapshots, original payment IDs, registered participants and existing
payment records retain the existing protections. A private library entry copied
into an event is then subject to normal event review/publication visibility.

Library version tokens reject stale selections/edits. Event-copy fingerprints
reject changed or foreign-event copies. Server-side scoped lookups and locking
repeat the checks inside the event setup transaction. Forged raw account fields
cannot override checkbox selections. Older open forms/integrations without the
new selection marker retain the existing, ownership-protected raw-input path;
the current UI no longer exposes those account inputs.

## Schema and local data protection

The additive migration
`2026_11_02_000001_create_saved_payment_options_table` creates only the library
table. It does not alter event payment-method columns/fillable fields, avoiding
changes to existing pending-review setup hashes. There is no automatic import
of existing private accounts into a shared library. Options can be disabled;
no hard-delete UI/route is provided. Migration rollback requires a reviewed
recovery decision rather than silently removing saved account data.

Before applying that migration locally, a fresh native MySQL export covering
39 tables was encrypted with Windows DPAPI CurrentUser in:

`storage/app/private/local-payment-options-e503d2bf1be043fa98b04116076e2175`.

Export completion/all table definitions, encrypted checksum, decryption and
SHA-256 round trip were verified. This is not a full restore test; recovery
requires the same Windows user/profile and the manifest's recorded entropy.
The guarded transition applied only the new migration, kept all 32 checked
existing persistent tables identical, and left the new library empty. No real
account, event, registration, result, certificate or payment was created or
rewritten to test this feature.

## Verification and handoff

November 4 selector follow-up (local): added exact-detail deduplication and scoped,
one-account-per-provider browser switching. Regression coverage includes unchanged
save/reopen, replacement with the same payment-method ID, changed account details
and instructions, leading zeros, null versus empty instructions, disabled copies,
shared/personal identical entries, restored source tokens and conflicting old
input. The failed-submit redirect test carries the session cookie like a browser.
Synthetic real-browser checks cover pointer and keyboard activation, independent
providers and selectors, repeated script/navigation initialization, form reset,
restored empty/disabled/library/conflicting selections and script-disabled display.
No migration, account merge/deletion or real payment is involved. Verification
passed: **706 backend tests**, 2 skipped (4,580 assertions); **97 focused payment
tests** (714 assertions); **23 event-form/revision audits** (225 assertions); and
both real-browser payment tests without skips. Frontend build, Blade compilation,
targeted Pint and syntax/whitespace checks passed. These are local working-folder
results; exact-package verification and deployment status are recorded separately
in the release review's November 4 follow-up section.

Compact library-page follow-up (local): **86 focused payment tests passed
(641 assertions)**. Added rendered-DOM checks for provider/account headings,
visible account numbers and actions, short badges, escaped/collapsed instructions,
collapsed explanatory help, unchanged stored labels on reads, PayMongo display,
legacy missing-account fallbacks, and the empty state. Existing permission,
availability, stale-click and event-copy tests remain in the focused run.
Frontend build, Blade compilation, Pint formatting and whitespace checks passed;
compiled views were cleared. This is a presentation-only follow-up: no schema,
backend behavior, real records or deployment changed. Report:
`storage/app/private/payment-options-clean-layout.xml`.

Card-availability follow-up (local, November 3): **687 backend tests passed,
2 skipped (4,415 assertions)**. The suite includes authorization for shared,
personal and legacy-manager options; no availability input in Add/Edit; enabled
defaults; preservation of disabled status across edits; explicit state/version
card forms; forged field isolation; stale/repeated clicks and edits; unchanged
event snapshots; invalid/missing state/version rejection; and rejection of GET
mutations. Frontend build, PHP syntax, targeted Pint, Blade/route compilation
and whitespace checks passed. Development caches were cleared. No schema,
real payment records, event copies, credentials, Flutter code or deployment was
changed. Full report:
`storage/app/private/payment-card-availability-regression.xml`.

Automatic-label follow-up (local): **58 focused payment tests passed (357
assertions)**. Added coverage for all four providers without a label field,
Create/Edit form removal, ignored submitted labels, Unicode-safe column limits,
unchanged full account names, existing-label preservation on reads, explicit
edit regeneration, stale-edit rejection and unchanged event snapshots. No
backend ownership/selection/review rules, schema or real records changed.

Paste-aware limit follow-up (local): **52 focused payment tests passed (296
assertions)** plus the real-browser field-switching test. Browser checks cover
the 11-character wallet cap, whole-paste rejection via simulated clipboard
events, allowed selection replacement, an extra insertion through the browser
input protocol, clearing feedback after input/provider changes, and unchanged
bank limits/paste handling. Backend validation is unchanged. No real records or
deployment were involved. The previous full-suite result below predates this
small input-control follow-up.

Provider-specific field follow-up (local, November 3): **661 backend tests
passed, 2 skipped (4,162 assertions)**, including **52 focused payment tests
(296 assertions)**. One real-browser synthetic fixture test passed for provider
switching, number formats, preserved text/leading zeros and omitted PayMongo
fields. Frontend build, Blade compilation, PHP syntax, targeted Pint and
whitespace checks passed. No real account/payment/provider test or deployment
was performed. The full-suite report is
`storage/app/private/payment-account-validation-regression.xml`.

Compact event-selector follow-up (local, November 3): **29 payment tests passed
(201 assertions)** and **23 browser form/revision audits passed (225 assertions)**.
New DOM checks cover collapsed, escaped details outside checkbox labels, saved
and disabled copy defaults, and restored selections including an explicit empty
selection. Frontend build, Blade compilation, targeted Pint and whitespace
checks passed. No backend saving/approval code, schema or real records changed
for this layout follow-up; it has not been deployed.

Initial library implementation verification:

- Complete local suite: **636 passed, 2 skipped, 4,031 assertions**.
- Focused payment/library cases: **27 passed, 165 assertions**.
- Real-browser event form round trips/revision-copy audit: **23 passed, 225 assertions**.
- Frontend build, PHP syntax, targeted Pint formatting, Blade/route compilation
  and whitespace checks passed. Development compilation caches were cleared.
- Event Create, library index and library Create rendered against local MySQL
  in a read-only transaction using an in-memory identity/session. Private HTML
  and account details were not printed.

Tests cover role scoping, shared/private ownership, forged inputs, stale versions,
disabled/missing/foreign options, duplicate providers, unchanged event copies,
legacy fallback prevention, publication locks, reviewed replacement and public
payment-method ID preservation. The simulated race/stale checks use SQLite;
they are not a parallel MySQL concurrency stress test or provider delivery test.

Next manual local check: open Payments → Payment Options, save one option, then
select it in a private event draft and verify its restored checkbox/details on
edit. Any real payment, provider test or Railway deployment remains separate.
