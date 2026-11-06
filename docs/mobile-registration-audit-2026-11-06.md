# Mobile registration compatibility audit - November 6, 2026

## Outcome

The read-only compatibility checks passed, but two synthetic edge cases exposed
UI/data-handling gaps that should be fixed before declaring the flow complete.
No application fix, deployment, APK build/install, or production registration or
payment submission was performed.

Subsequent status: the separately authorized Laravel capacity correction is now
implemented locally and verified below. It has **not been deployed**. Flutter
changes and the phone check remain separate follow-up work.

The current successful Railway web deployment is still
`53080e30-29be-4ef3-9aeb-30af74e6e16a`, matching the backend release copy tested.
The Flutter project was inspected at
`C:\Users\keith\AndroidStudioProjects\racetechph`. Its five existing modified
files remain the same; none was edited for this audit.

## Verification

- **205 Flutter tests passed** with `flutter test --no-pub --reporter expanded`.
- **Flutter analyzer: no issues**, using `flutter analyze --no-pub`.
- **199 focused backend tests passed**, no failures/errors/skips, **1,356
  assertions**. The manifest-pinned release verification copy ran against forced
  SQLite `:memory:` with array mail/cache/session services. Coverage includes
  mobile registration/readiness, maps, event payment accounts, saved options,
  publication/revisions/privacy, and payment guards. Synthetic test requests do
  not reach production. JUnit: `storage/logs/mobile-contract-audit-2026-11-06.xml`.
- The public production API was accessed using GET only: event list plus one
  existing event detail. Actual Flutter normalization, schedule, route parsing
  and payment helpers checked **12 public events and 24 open categories**:
  16 paid categories, 24 slot limits, one drawn route and 12 uploaded-image
  descriptors. List/detail data matched. Venue, schedules, fees, remaining slots,
  course data and selected account values survived normalization unchanged.
  Account names/numbers and raw response bodies were not printed or retained.
- The sample has one upcoming event and no full category. Live checks therefore
  do not themselves exercise the full-category edge case below. Private draft
  and revision exclusion is covered by isolated backend regressions, not by
  guessing private production IDs or accessing a staff account.

## Findings requiring follow-up

### 1. Explicitly empty payment options can revive a stale screen account

In Flutter `lib/utils/payment_options.dart:7`, a fresh category response with
`payment_options: []` does not stop fallback to an older event object containing
an enabled account. The actual helper returned the stale synthetic account.
This is a reproducible helper case, not evidence of a real payment incident.

Recommended correction: an authoritative empty/disabled options collection must
stay empty; only absent data should use compatible legacy fallbacks. Add tests
for cleared options, disabled options, stale event data and legacy-only payloads.
The existing enabled-event-payment server guards passed their regression tests.

### 2. Full categories can still be presented as registerable

The released `app/Http/Resources/Api/EventResource.php:56` derives
`can_register` from personal eligibility without capacity. The synthetic
SQLite fixture had one occupied slot out of one and correctly returned
`slots_remaining: 0`, but both event/category `can_register` flags were true.
Flutter `lib/utils/event_category_registration.dart:50` accepts that true flag
without checking remaining slots, so the full category can remain selectable.

The actual released registration controller **correctly returned 422**,
`This category is already full.`, without creating another registration. This
was a direct controller invocation with synthetic in-memory records, not a
network registration submission. The capacity guard remains intact.

Recommended correction: include capacity in backend availability flags and add
a defensive zero-slot check in Flutter, with regression coverage for full,
available, already-joined and legacy unlimited categories. Do not remove the
existing transactional server capacity checks.

## Boundaries and next checkpoint

No authorized Android device was connected. Native rendering, Google tile/key
authorization and the installed APK's visual/payment screens remain unverified
in this audit. The route and widget checks use parsing or simulated platforms;
no Google Maps or Static Maps request was made.

Two local-only diagnostic scripts were added under `scripts/`:
`audit-mobile-event-contract.dart` and `audit-mobile-capacity.php`. They import
the actual local Flutter helpers or bootstrap the pinned backend verification
copy. They emit sanitized counts/booleans only. They are not release artifacts
and must not be uploaded as application source.

Next recommended work is to fix these two bounded cases, rerun the relevant
tests, and then perform the signed-in phone check without submitting a real
registration or payment. The audit itself does not authorize those application
changes or another deployment/APK build.

## Subsequent Laravel capacity correction - local only

After the audit, the user authorized fixing Laravel first. The runtime change
is limited to `app/Http/Resources/Api/EventResource.php`:

- Category availability now includes capacity, counting all non-rejected
  registrations just like the existing registration endpoint. Public list/detail
  responses reuse their eager-loaded counts. Nested event resources without
  eager-loaded categories also check capacity without exposing new category data.
- An otherwise eligible full category returns
  `category_registration_states[].can_register: false`, with
  `conflict_reason: "This category is already full."` and a null
  `conflict_category_id`.
- Existing registration statuses and existing reasons for already being joined,
  an invalid/missing birthdate or schedule conflicts take precedence. They are
  not overwritten by a capacity message.
- Event `can_register` stays true only when its existing date/deadline rules
  permit registration and an eligible open category has room. Full events remain
  visible; this does not change approval, publication or event status.
- Legacy null slot limits remain unlimited. Rejected registrations free slots;
  zero limits and overbooked legacy categories count as full.

The registration controller, group-registration service, personal-eligibility
service and category resource were verified byte-for-byte unchanged. In
particular, both the early capacity rejection and transactional/locked checks
remain intact. No migration, new response field, account/payment change, Maps
configuration change or data migration is needed.

Verification:

- New `tests/Feature/Api/EventCapacityAvailabilityTest.php`: **30 tests, 181
  assertions passed**. Before the fix, 19 failed and 11 passed, with no setup
  errors, reproducing the actual flag mismatch.
- Complete local backend suite: **850 passed, 2 existing skips, 5,895 assertions**,
  with zero failures/errors. Tests explicitly used SQLite `:memory:` and
  array-backed mail/cache/session services, not the production database.
- Coverage includes guest/authenticated list and detail responses, mixed/all-full
  categories, rejected/active registrations, unlimited/zero/overbooked limits,
  closed categories, preserved joined statuses and birthdate/schedule reasons,
  and resources with or without preloaded categories/counts. A simulated
  last-slot change after an availability GET still produces a 422 on the
  registration request without another registration being inserted.
- PHP syntax, scoped Pint and runtime whitespace checks passed. An unrelated
  existing trailing blank line in `resources/css/app.css` was left untouched.
- Reports: `storage/logs/event-capacity-{before,after,full-backend}.{xml,log}`.

Flutter handoff: match category states by `category_id` and consume
`category_registration_states[].can_register`/`conflict_reason`; remaining slots
continue to be `categories[].slots_remaining`. No `is_full` field was added.
Still add the defensive zero-slot check and "Full" presentation without replacing
an already-joined participant's status. Null/missing slot counts must not be
treated as zero. The explicitly empty payment-options fallback remains a
separate Flutter change. These backend flags are not live until a separately
approved release is tested and deployed.
