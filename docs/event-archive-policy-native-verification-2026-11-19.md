# Native verification of the publication/restoration archive policy

**Passed on isolated MySQL 9.7.4: 604 assertions.** No application database,
XAMPP database, production service or production setting was changed. Automatic
archiving remains OFF outside the explicitly simulated test process.

The native probe completed **November 19, 2026, 05:35:16 Asia/Manila**
(`2026-11-18T21:35:16Z`). The owned server subsequently shut down normally, its
loopback port was released and its disposable data directory was removed.

## Scope and isolation

- Reused the previously checksum/signature-verified portable MySQL **9.7.4**
  runtime. Server/client/admin binary hashes were checked. Nothing was
  downloaded or installed; no Windows service or startup item was added.
- Created a fresh, private synthetic database bound only to
  `127.0.0.1:43326`, with an owned-process/PID and server-UUID check. XAMPP's
  existing server, port, configuration and data were not used or restarted.
- The test server had its event scheduler and local-infile disabled, no public
  bind, a 64 MiB buffer pool and capped connections. Ephemeral credentials were
  supplied in memory, not printed or placed in command-line arguments.
- Used the existing isolated application copy, with reviewed runtime/migration
  hashes matching the current workspace, a pinned synthetic `.env`, uncached
  testing configuration, test transports and blocked outbound HTTP.
- No production backup or real participant data was loaded. Native fixtures
  included synthetic publications, an archived event, a historical restoration
  exemption, one paid completed registration, a result, feedback, a certificate,
  two checkpoint visits and prior automatic-archive evidence.

## Results

1. Built the **89-migration baseline**, seeded synthetic history, then applied
   the exact new migration through Laravel: **89 to 90 applied migrations**.
   Prior migration records were preserved.
2. All original values and original column definitions across **43 non-migration
   tables** were preserved. Both new metadata columns began NULL, so historical
   events were not enrolled or backfilled. Nullable DATETIME definitions,
   defaults, indexes and absence of automatic timestamp updates were checked.
3. The FK-supporting nonunique audit index was created before the old unique
   index was removed. **All 84 foreign keys were unchanged and had no orphans**.
   An isolated audit-only fixture verified that parent-event deletion remained
   restricted. Its temporary transaction was rolled back.
4. Before new metadata or repeated evidence existed, the exact migration's
   safe `down()` and `up()` both worked natively without changing original rows.
   Later rollback attempts correctly refused publication/restoration metadata
   and repeated archive evidence before executing destructive DDL.
5. Original approval recorded first publication while the feature was OFF.
   A real reviewed revision preserved that timestamp and did not copy clocks
   into the revision draft. Old and legacy publications remained protected.
6. Restoring an old event cleared its earlier exemption and recorded the new
   restore window. Immediate and one-second-early automatic archives were
   rejected. The exact 30-day boundary qualified.
7. The actual archiver's dry run passed inside a native, database-enforced
   **READ ONLY, REPEATABLE READ** transaction. No record changed, nested
   transaction bookkeeping remained correct and the transaction rolled back.
8. Repeated automatic archive/restore cycles appended audit rows instead of
   failing the earlier unique constraint or overwriting the original audit.
   Sequential repeats produced no duplicate archive outcome. A newly published
   event waited until 30 days after its own ending time.
9. A deliberately failed system-audit insert rolled back the event archive.
   Non-event business-table fingerprints stayed unchanged through subsequent
   archive/restore cycles, including both synthetic checkpoint visits.

These are **604 native assertions**, not an additional 604 PHPUnit test cases.
The earlier isolated SQLite run's **258 tests / 1,438 assertions** remains
separate evidence and was not unnecessarily repeated here.

## Initial verification-harness correction

Two initial attempts stopped at the preservation comparison. MySQL's
`SHOW COLUMNS.Key` summarizes index membership; it necessarily changes from
`UNI` to `MUL` for the reviewed audit index change. The initial comparison
incorrectly included that marker in the supposedly unchanged column definition.

The harness now checks row values and actual column definitions separately
from the intended index transition. The final run passed both preservation
and explicit index/FK checks. **No application code or migration fix was needed.**
Both earlier temporary servers shut down normally, and both earlier disposable
data directories were removed. Their sanitized failure/cleanup receipts remain
available; the failed attempts were not relabeled as passing runs.

## Preservation and cleanup

The real `.env`, Git index/HEAD, XAMPP configuration and original XAMPP process
identity matched before/after. All scoped reviewed source/migration hashes
also remained unchanged. No migration or event action against XAMPP or production,
live scheduler execution, Railway command, deployment, Git push or APK build was
performed. Only synthetic records were archived/restored inside the isolated
test server.

The final owned server exited normally with code 0. Port 43326 was free after
shutdown. Only each run's validated generated `data-<GUID>` directory was
recursively removed. Those disposable synthetic databases are not retained;
their recreation scripts and sanitized verification receipts are retained.
Existing verified runtime binaries, earlier backups and application copies
were not deleted.

Final private evidence:

```text
storage/app/private/archive-policy-mysql-20261119-cdf6023ac8374dfeac34f681abd25ae6
```

It contains source/protected-state hashes, owned-server identity, the passing
native-probe receipt, clean-shutdown/data-removal receipt and final acceptance.
Earlier failed attempts are preserved at:

```text
storage/app/private/archive-policy-mysql-20261119-6a0dd11ed64d4597b83b43c4b8366cd6
storage/app/private/archive-policy-mysql-20261119-66532fddcd0b486084d289ca11f23660
```

Local-only helpers: `scripts/test-event-archive-policy-mysql.ps1` and
`scripts/probe-event-archive-policy-mysql.php`. PHP syntax/scoped Pint and
PowerShell parsing passed.

## Limits and next step

This was a Windows portable MySQL rehearsal with synthetic data, not a Linux
production rollout, production-backup restoration or new concurrent-request
stress test. It verifies native migration/index/FK behavior and the exercised
sequential policy paths; it does not claim every production/device path passed.

Next is a reviewed backend/scheduler release **with automatic archiving still
OFF**, followed by a policy-aware read-only preview and a separate activation
decision. Do not enable an older scheduler image, reuse the earlier eleven-event
preview as the new candidate list, or change the fixed activation boundary
without explicit review. Nothing in this verification enables the feature.
