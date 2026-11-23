# Mobile event archive visibility - November 18, 2026

Later local follow-up: [automatic 30-day archiving](automatic-event-archiving-2026-11-18.md)
has been implemented separately with an explicit opt-in gate, disabled by
default. Its migration has since been
[prepared and previewed on local XAMPP](automatic-event-archiving-local-preparation-2026-11-18.md),
with automatic archiving still OFF and no production deployment. The scope and
test evidence below describe the earlier mobile-discovery-only change.

Local Laravel implementation only. No push, deployment, APK build, real database
migration or real event archive/restore/delete was performed for this work.
Automatic 30-day archiving is a separate next step and is not enabled here.

## Behavior and boundaries

The existing `GET /api/events` discovery query now excludes rows with a non-null
`events.archived_at`, before loading and serializing events. This applies to
Browse, title/venue/category Search, interest/status filters and recommendations.
Guest, valid, invalid and expired optional bearer-token cases use the same
archive exclusion. There is no client flag for including archived events.

The change is deliberately endpoint-local, not a global model scope or a new
lifecycle status. Existing completed events remain discoverable until actually
archived. A restored event becomes eligible for the list again, subject to the
existing publication/readiness and request filters. No GET request archives,
restores, deletes or updates an event.

The query checks for the `archived_at` column so an environment that has not yet
applied the existing archive migration retains its previous list behavior
instead of failing on an unknown column. No new migration or configuration
variable is needed. Production archive operations still require the normal
reviewed release/migration process.

Unchanged contracts:

- My Activity, participant results, payment history, feedback, certificates and
  historical event detail links remain available under existing rules.
- `GET /api/events/{event}` remains public for otherwise public-ready events;
  archive is not a privacy boundary or full unpublishing operation.
- Participant ownership and token expiry checks are not relaxed. Feedback and
  first certificate issuance still require their existing eligibility rules.
- Staff assignment, checkpoint lists/plans/recording/retries, finish scanning,
  archive eligibility, Admin-only cleanup and event review rules are unchanged.
- Response fields, status meanings and client API addresses are unchanged.
- Public website, announcements, community and leaderboards are not filtered
  by this change. A displayed/cached mobile card disappears on list refresh,
  not through a new push or client-cache invalidation mechanism.

No Flutter source change or APK rebuild is required for the existing client to
consume the filtered list after a future backend deployment. Actual installed
app refresh behavior still needs a later phone smoke test.

## Verification setup

The dedicated application copy uses the pinned synthetic testing environment,
SQLite `:memory:`, fake notifications, blocked stray HTTP, and array test services.
Real `.env`, cached application configuration and database data are not copied
into the test application. The real XAMPP database and Railway are not contacted.

Private evidence root:

```text
storage/app/private/event-cleanup-20261118-51e448b3b9f94d358c740aec3981f230
```

Starting versions of overlapping source files are retained in `original-source`.
`protected-before.json` records 22 protected files, including the real environment,
Git index, routes, archive service/migration, model/resources, participant history,
staff and scanning implementations.

`tests/Feature/Api/MobileEventArchiveVisibilityTest.php` covers discovery filters,
token states, legacy and approved lifecycle states, an empty archived-only list,
restore behavior, missing archive schema, unchanged history payloads, ownership,
first feedback/certificate issuance after archiving and staff assignment scope.
The broader suite also exercises checkpoint exact retries after archiving,
finish scanning, event review privacy, cleanup guards and registration capacity.

The first pre-implementation run reproduced the missing discovery filter. It also
exposed a synthetic legacy-schema fixture issue: SQLite table reconstruction
removed its dependent category. That test now creates its synthetic event after
removing archive columns, rather than testing migration rollback side effects.
No production migration was changed.

## Verification results

- 455 focused backend tests passed, with 3,070 assertions and no failures,
  errors, warnings or skipped tests, across 14 feature-test files.
- This includes 47 new archive-visibility regressions. The full backend suite
  and a physical-device smoke test were not run for this change.
- PHP syntax and scoped Pint checks passed for the API controller and new tests.
- PowerShell syntax and scoped whitespace checks passed. All 22 protected files
  matched their starting hashes, including the real environment and Git index.
- The isolated passing report is
  `focused-0092480cb279407583d48a0d661ec1a9-result.json`, with JUnit/log alongside.

The private completion receipt is `visibility-verification.json`. Automatic
archiving, deployment and phone verification remain separate next steps.
