# BuilderLog backend changelog — Multi-manager

Pre-migration reference is `443f69a`. The client-facing inventory is in [API_CLIENT_MIGRATION_MULTI_MANAGER.md](/home/panta/Desktop/Projects/builder_log/docs/API_CLIENT_MIGRATION_MULTI_MANAGER.md).

## M1 — Canonical DailyLog and daily participants

- **Migration** — `2026_11_22_120000_create_daily_log_site_managers_table.php` creates a unique pivot row per DailyLog and manager and backfills the legacy owner, including soft-deleted daily logs. The existing unique site/date key remains authoritative.
- **Models and API** — DailyLog uses `siteManagers()` and Worker uses `participatedDailyLogs()`. The construction-site assignment remains a separate permanent relation. Site selection creates or joins the canonical log in a transaction with a locked site row. It returns 201 for creation and 200 for reuse. Repeated selection preserves the original join timestamp.
- **Authorization** — Active, permanently assigned SITE_MANAGERs can select a same-company site. Manager DailyLog reads use membership. Admin reads remain company-scoped. The legacy filter parameter `site_manager_id` matches pivot membership.
- **Tests** — `SharedDailyLogTest` and `ConcurrentDailyLogSelectionTest` cover shared logs, multiple sites per day, repeated selection, lock handling, backfill and concurrent create/join.
- **Tradeoff** — Historical `joined_at` can only approximate original participation using the DailyLog creation timestamp. A soft-deleted DailyLog retains its unique site/date key and selection returns 409.

## M2 — Shared children and ordinary attendance

- **Migration** — `2026_11_22_130000_enforce_ordinary_worker_daily_attendance.php` adds the internal `ordinary_worker_day` marker and a unique company/worker/day key. Machine-linked periods leave it null.
- **Actions, services and resources** — Attendance, expenses, notes, delivery notes, subcontractor logs and attachments use the canonical parent. Parent-child mismatches are rejected. Same-company authorized managers can edit and delete shared records.
- **Authorization and attribution** — Membership controls access. Creation obtains attribution from the manager actor. An updated record keeps its creator and attribution, and the audit stores the editor. Daily expense lists are shared. Expense history for a site manager keeps the actor’s own attribution.
- **Tests** — `SharedDailyLogChildrenTest`, `ConcurrentWorkerAttendanceTest`, attachment, expense, ordinary attendance and salary contracts.
- **Tradeoff** — Duplicate attendance across logs returns 409. Existing duplicate and lock cases on the same log retain business 500 responses. Admin creates in a daily log that require a manager return 422. Standalone admin expense creation preserves nullable attribution and parent.

## M3 — Shared machine periods

- **Migration** — None.
- **Actions and API** — MachineAssignment, ExcavatorLog and TruckLog expose the shared DailyLog. A driver/operator self-assigns before or after the DailyLog exists. The manager can take over the existing log with authoritative times. A takeover may reuse the existing record ID.
- **Authorization and attribution** — Manager writes use membership and editable state. Worker self-updates are restricted to their own record and worker-side fields. The assignment manager records the actual creator or takeover manager. Log creators remain original. Machine attendance starts after managerial confirmation and records the first confirming manager and creator.
- **Periods and concurrency** — Confirmed periods use half-open overlap checks, allowing adjacent periods. Incomplete authoritative times reserve the machine. Available results are company-wide. A locked company row serializes machine claims and edits across sites. Deletes soft-delete the assignment, log and linked attendance.
- **Tests** — `SharedMachineAssignmentsTest`, `ConcurrentMachineAssignmentsTest`, patch contracts and `ClosePreviousDayContractTest`.
- **Tradeoff** — Machine periods are checked on the assignment date. Cross-midnight behavior was left as-is. Company-level row serialization trades parallelism in one company for consistent worker and machine claims. The audit prevents a second takeover from reattributing a pending self-assignment whose takeover has no start time.

## M4 — Reporting, tenant scope and removal of singular ownership

- **Migration** — `2026_11_22_140000_remove_daily_log_site_manager_id.php` checks that each historical owner has a matching pivot and that membership points to a worker of the same company. It removes the legacy foreign key and column. It preserves child attribution and audit history. Rollback requires a pre-M4 snapshot because a pivot with several members cannot reconstruct the original owner.
- **Models and serialization** — Removed the DailyLog singular relation, Worker managed-owner relation and singular DailyLog API fields. Daily and nested DailyLog resources carry `site_managers[]` including `joined_at`. Legacy DailyLog create still accepts its existing request fields and validates them for compatibility. Clients use select for current-day access.
- **Reporting** — Per-manager totals use stored `site_manager_id` on financial and attendance records. Participation never multiplies totals. Global reports scope the caller’s company, site statistics scope child records by tenant, and report grouping avoids foreign labels. Existing salary formulas and decimal formatting are preserved. Site machine statistics still use excavator `work_hours`. Subcontractor statistics retain duration hours.
- **Company scope** — Scoped worker index/show and nested sites, construction site listing and assignments, DailyLog lock/unlock, manager reports, general task list and mutations, advances and document attribution. Foreign direct IDs return 404 on the newly scoped paths. Same-company Task role policy and the separate mobile task workflow retain their existing rules.
- **Explicit admin attribution** — Cash advances require an explicit same-company SITE_MANAGER. Standalone documents allow a null manager and validate an explicit manager and site against the company. Site assignment requires an active same-company manager and an administrator to mutate. Standalone admin expenses keep a nullable manager. Daily attendance, expense, note, delivery note, subcontractor and machine manager-create endpoints return 422 for admin when manager attribution is required. Daily attachment uploads record the actual actor and require no manager attribution.
- **Tests** — `MultiManagerOwnerRemovalMigrationTest`, `MultiManagerReportingTest` and `MultiManagerCompanyScopeTest`, plus SAFE/M1–M3 coverage, MySQL 8.4 concurrency and OpenAPI validation. Historical SAFE fixtures now link participants through the pivot. The approved JSON assertion changes require absence of both singular DailyLog fields.
- **Deployment** — Back up the database before applying the migration. The migration preflight was exercised on migrated synthetic MySQL and SQLite fixtures. Aggregate read-only verification of DEV found two logs and two corresponding pivot rows. The automated approval review rejected copying development data, so migration was not executed against the real DEV rows. Validate a sanitized DEV snapshot before deploying to that dataset.

## Remaining technical debt

- Several BusinessException paths use HTTP 500. Status mapping remains endpoint-specific.
- Running the scheduler again can increase excavator `total_work_hours` a second time. The existing repeated-run behavior and lack of automatic truck closing are covered by the scheduler contract.
- Cross-midnight machine periods and company-lock throughput were left as-is.
- Scramble’s strict mode still has 68 unknown errors and 3 JR001 warnings. Normal analysis and OpenAPI validation pass.
- An administrator cannot choose managerial attribution on daily child creates whose contracts have no explicit manager field. No manager is inferred from the first participant.
- Existing broad access patterns for organization directories such as `/companies`, and the project-wide role policy for standalone administrative records, were outside this migration’s scope.
- The existing root redirect contract failures remain in ExampleTest and PwaRoutingTest.
