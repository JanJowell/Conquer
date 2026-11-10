# Checkpoint integration — computer-only verification, 2026-11-10

Outcome: the scanner's existing Dart checkpoint transport works with the real
local Laravel API. Real MySQL testing exposed a checkpoint/finish deadlock;
the approved fix changes only the **new checkpoint recorder's parent locks**.
The existing finish-scanner implementation and scanner application source remain
unchanged. Production recording remains disabled on both sides.

The user explicitly chose computer-based checks only. No phone connection,
camera/device verification, APK build, installation, push or deployment was part
of this work.

## Isolation

- XAMPP's existing MariaDB instance was not used or modified.
- The official portable MySQL Community 8.4.13 Windows ZIP was downloaded into
  this run's private directory; no Windows service or PATH entry was installed.
  Its published MD5 matched `7b94489d5524dea9b27097068bea25a8`; the downloaded
  archive's SHA-256 is `E9CD98B1D23F122987B1AFCA26AD942EFEC5891481103D48AE3CDE237FA3EA54`.
  Source: [official MySQL download page](https://dev.mysql.com/downloads/mysql/8.4.html?os=3).
- The new MySQL instance bound only `127.0.0.1:43316`, with its own initialized
  data directory and one uniquely named `checkpoint_verify_*` schema.
- The probe validates the exact server version, port, loopback binding and
  private data directory **before writes**, including migrations. Its application
  account is granted access only to that new schema. Test credentials are
  generated privately and are not command-line arguments or repository files.
- Laravel loads only explicit test configuration from the private run, not the
  real `.env`. HTTP and notification safeguards prevent external service calls.
- Synthetic accounts use `@checkpoint.invalid`, with synthetic events, BIBs,
  checkpoint visits and provisional finishes. No real participant is used.
- Two loopback-only HTTP probes exercise an enabled test configuration and a
  disabled configuration. The Dart client is hard-limited to those two loopback
  ports and checkpoint URLs; it cannot send a request to production or finish.
- The ordinary Laravel regression suite separately uses SQLite `:memory:` and
  a temporary credential-free `.env.testing`, removed after verification.

Private artifacts, including test-only credentials and signed synthetic BIBs,
are under:

`storage/app/private/checkpoint-integration-20261110-44c98f213bd4414980e2407b80048d07/`

Do not publish that directory or paste its credential/fixture JSON into chat.
The portable files and disposable test data are retained privately for audit;
the test servers are stopped at handoff.

## Discovered issue and approved fix

The initial overlapping checkpoint/finish test produced HTTP 500 on the finish
request. Three additional reproductions confirmed MySQL error 1213 / SQLSTATE
40001. A finish request without the competing checkpoint succeeded.

The new checkpoint service held exclusive staff/event/category parent locks
while waiting for the participant's registration. The existing finish writer
could own that registration and need compatible parent reads for foreign-key
checks, creating a lock cycle. InnoDB foreign-key checks acquire shared record
locks; see [MySQL's locking documentation](https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html).

With the user's approval, `CheckpointScanRecorder::context()` now uses shared
locks for staff, event and category. These keep authorization and setup stable
during acceptance while allowing the existing finish writer's shared FK reads.
Registration, checkpoint identity and recorded-plan operations retain their
exclusive locks, unique constraints and bounded transaction retries. The fresh
locking finish/result eligibility checks also remain intact.

No retry behavior, token format, offline queue, endpoint or source code in the
existing finish scanner was changed. A feature regression now asserts the
parent shared-lock contract, and real MySQL overlap scenarios verify behavior.

## Results

| Verification | Result |
| --- | --- |
| Laravel full regression suite | **1,024 passed**, two existing skips, 6,907 assertions |
| Scanner existing Flutter suite, independently rerun | **164 passed** |
| Scanner analyzer | **No issues** |
| Real MySQL concurrency/history checks | **24 passed** |
| Real scanner Dart transport → loopback Laravel HTTP | **11 passed**, 10 real HTTP requests in the final run |
| Non-checkpoint business-table comparison around final Dart HTTP test | **41 tables unchanged** |

The 24 MySQL checks comprise 18 measured-overlap request pairs, three controlled
finish interleaves and three history/integrity checks. They cover:

- identical UUID retries returning the same visit and original time;
- two UUIDs/staff competing for the same participant/checkpoint;
- one UUID competing across unrelated events, with no leaked receipt or orphan plan;
- independent checkpoints sharing one recorded plan;
- checkpoint and finish requests competing for one registration;
- the same assigned Event Manager account used for the checkpoint/finish overlap;
- a checkpoint establishing an old repeatable-read view, then a separate finish
  committing before it resumes: checkpoint recording correctly stops;
- changed plans stopping new visits while exact historical retries still work;
- MySQL foreign keys retaining history, no orphan plans, and no automatic official
  results or certificates.

The final Dart test imports the scanner's actual existing transport and models;
it does not substitute simulated responses. It verifies real plan parsing,
401/403 handling, a matching 201 receipt, a matching 200 replay, duplicate and
invalid-checkpoint conflicts, tampered BIB rejection, and both client/server
disabled gates. No finish endpoint is called. The final fixture has exactly one
visit and one plan; registration remains checked in, with no official result or
certificate.

Earlier failing logs are retained as evidence of the original deadlock. Final
passing reports are `concurrency-results.json`, `same-account-results.json`,
`scanner-real-http-contract-after-lockfix.log`,
`http-database-verification-shared_locks.json`, `backend-regressions.xml`,
`scanner-tests.log` and `scanner-analyzer.log` in the private run directory.

## Remaining boundary

These are bounded computer-based checks on local MySQL 8.4.13 using
`REPEATABLE-READ`, not a load-test guarantee or verification of Railway's exact
database version/configuration. Real camera permissions, mobile lifecycle and
physical-device behavior remain unverified and deliberately deferred.

Unconfirmed scanner attempts remain memory-only. An app restart can lose retry
information without undoing an already accepted server visit. A separate history
lookup/reconciliation UI is not provided by this step.

No production or XAMPP schema was migrated, and no feature gate was enabled in an
application environment or production scanner entry point. The next release
still needs an exact-package review, backup/migration plan, and explicit approval;
device checks remain necessary before operational recording.
