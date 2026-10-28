# Session Handoff — CacaoTrack

> ### ▶ Continue here (latest: Session EE — **role permissions + Daily Checklist workflow + user-friendly UI implemented**)
> - **▶ Session EE (DONE — role permissions, checklist verification, friendly errors, dashboard/sidebar, tests, docs; 2026-10-28):**
>   **Implemented the approved role-permission and user-friendly UI scope in six commits; no migration, schema change or
>   `db_capstone` write.**
>   **(1) Permission catalog (commit `22a0770`):** `Role::PERMISSIONS` now has **10** keys (adds
>   `manage_checklist` + `complete_checklist`; keeps all existing keys) with plain-language labels and
>   `Role::PERMISSION_DESCRIPTIONS`. `Role::DEFAULT_PERMISSIONS_BY_ROLE` is the single provisional
>   template map (docblock `provisional — pending panel confirmation`): Administrator = all 10; Manager =
>   `manage_inventory`, `manage_checklist`, `view_analytics`, `generate_reports`, `view_activity`;
>   Staff = `record_operations`, `manage_stock`, `complete_checklist`. **No automatic backfill.**
>   `EnsurePermission` flashes only a catalog-derived safe label. Route split: checklist view = either
>   checklist key; task management + verify = `manage_checklist`; complete = `complete_checklist`;
>   Inventory view = `record_operations|manage_inventory|manage_stock`; snapshot record =
>   `record_operations`; inline Supplier/Client creation = `manage_inventory`; `/users` = `manage_users`.
>   **(2) Stock forms + User Management (commit `05f785d`):** stock forms show **+ Add Supplier/Client**
>   (and their modals) only with `manage_inventory`, otherwise "Not listed? Ask a Manager to add it.";
>   the Vendor/Client POST routes enforce `manage_inventory`. User Management gained the centralized
>   label/description list, a "What each role can do" table, Administrator/Manager/Staff/Blank
>   templates, an **Apply role defaults** button (form-only; saving stays separate) and the exact help
>   text "To create a new role, type a new role name and tick what it can do. Roles are saved per user."
>   **(3) Checklist + friendly UI (commit `cd3912c`):** Manager/Staff split implemented — Staff complete
>   tasks (`verified_by_id` / `verified_at` left **null**), Managers create/edit/delete tasks and record
>   verification through the new `operational.checklist.completions.verify` route; Staff are blocked
>   server-side from management/verification. Friendly standalone `errors/403` (role + required access in
>   plain language + Back to Dashboard + "Ask an Administrator if you need access.") and `errors/404`;
>   permission-driven sidebar (hides empty headings, shows the signed-in role near Profile); Dashboard
>   Quick Actions plus analytics/activity hidden without the matching permission.
>   **(4) ActivityLog cleanup (commit `3a522e3`):** removed the six proven-unused legacy types
>   (`role_created`, `role_updated`, `role_deleted`, `stock_reversal_recorded`, `variety_archived`,
>   `scion_archived`) from `TYPES` / `TYPE_*` / badges; historical rows still render through the
>   headline/badge fallback. No rows deleted.
>   **(5) Tests (commit `6f68b92`):** `phpunit.xml` now forces MySQL `db_capstone_test`; a new
>   `tests/TestCase.php` guard refuses `RefreshDatabase` unless the DB is exactly `db_capstone_test`;
>   new `RolePermissionAccessTest` covers defaults, the three-role matrix, Monthly Inventory view vs
>   record, inline creation gating, checklist completion/verification, templates/descriptions, sidebar,
>   Dashboard and friendly 403/404. **Full MySQL suite: 428 passed / 2213 assertions** (was 408/2001).
>   **(6) Docs (this commit):** `docs/CacaoTrack_PRD.md` FR-02.1 (permission table, confirmed
>   Manager/Staff checklist split, Monthly Inventory view-vs-record, provisional markers for Staff
>   Batches/Monthly Inventory and Manager Analytics/Activity History),
>   `docs/CacaoTrack_PRD_REVISED.md` byte-identical, `AGENTS.md`, this handoff.
>   **PRD SHA-256 (both copies): `6851FBA6829950413EABBE2C7091BAF45356A0B394F673A6CAA8A8B07ACE7D32`.**
>   **Live SQL proposal (NOT EXECUTED — awaiting approval):** add `manage_checklist` to Manager accounts
>   and `manage_checklist` + `complete_checklist` to Administrator accounts with `JSON_ARRAY_APPEND`
>   guarded by `JSON_CONTAINS`; the exact statements are recorded below in the live-DB section. **No live
>   user permission was changed.**
> - **▶ Session ED (DONE — Phase 0 read-only inspection of role-based permissions + user-friendly screens; 2026-10-28):**
>   **Read-only audit only — no code, migration, route, permission, test or `db_capstone` change; only this
>   handoff was updated.** Inspected `AGENTS.md` + this handoff, `App\Models\Role`, `User`, `EnsurePermission`,
>   `routes/web.php`, `UserController`, `DailyChecklistController`, the checklist models/support/views/tests,
>   Stock In/Out controllers + views, the sidebar/Dashboard/Users views, PHPUnit/env config, the activity-log
>   catalog, the PRD/ERD/Design docs and the Git state. **Key findings (evidence in the Phase-0 report; no
>   separate report file was created):**
>   (1) **Permissions are per-user JSON, not role-derived.** `users.role` is a free-text label (no roles
>   table/CRUD); `Role::defaultPermissionsFor()` is used only by the seeder/factory, so the User form does
>   **not** auto-apply role defaults. `manage_users` can be delegated to any role; “only administrators”
>   messages are inaccurate. (2) **Administrator has no bypass** — `EnsurePermission` only calls
>   `hasPermission()`; the UI forces only `manage_users`, so an Administrator can lose `correct_records` /
>   `view_analytics` / etc. No backfill exists for the later-added `correct_records` (commit `180b998`), so an
>   older live Administrator may lack it (not verified against `db_capstone` in this read-only phase).
>   (3) **Daily Checklist verification is self-verification:** `completeTask()` sets
>   `user_id == verified_by_id == Auth::id()` and `verified_at = now()`; there is no verify route, so a
>   Manager cannot verify another user’s completion. Any `record_operations` user can add/edit tasks; only
>   delete is Manager/Admin-gated. (4) **Stock In/Out inline Supplier/Client creation** routes
>   (`inventory.suppliers.store` / `inventory.clients.store`) are under `manage_stock`, not
>   `manage_inventory`; neither writes an `Activity_Log` row. (5) `/` (Dashboard) and `/users` are `auth`-only:
>   the Dashboard shows analytics KPIs/charts and its “View Analytics” / “View All” links are not gated;
>   `/users` is readable by every authenticated user. (6) Analytics PDF/CSV exports are under
>   `view_analytics`; only the POST “Generate Report” snapshot uses `generate_reports`, but its button still
>   renders for view-only users (403). (7) No `resources/views/errors/` — Laravel’s default error pages are
>   used. (8) **Test safety is fragile:** `.env.testing` is absent, `phpunit.xml` still sets SQLite
>   `:memory:` (`pdo_sqlite` is disabled here), `composer test` does not set MySQL, and `RefreshDatabase` is
>   used by 30+ feature tests; only the documented
>   `$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'` override is safe. (9) Legacy activity
>   types (`role_*`, `stock_reversal_recorded`, `variety_archived`, `scion_archived`, etc.) remain only in
>   `ActivityLog::TYPES` / badges (no writer/test) but still appear in the Activity History filter.
>   (10) `AGENTS.md` is stale on the catalog (lists 7 keys; code has 8 incl. `correct_records`) — not edited
>   this session.
>   **No migration, schema, route, permission-key, controller, calculation, test or `db_capstone` write.**
>   **Verified:** `php artisan route:list --except-vendor` → **67 routes**; **no tests were run** (read-only
>   phase; baseline remains **408 passed / 2001 assertions** at EB). **Files inspected:** the list above;
>   **only `session.handoff.md` changed in this session.** Working tree remains **uncommitted** (stacks on
>   DI…ED; head `49351ce`; 44 modified + 2 untracked before this handoff edit).
> - **▶ Session EC (DONE — Analytics Total Grafted = green, Total Active Inventory = blue; Mortality Tracking/Regraft/Batches sidebar links removed; 2026-10-20):**
>   Owner-directed UI pass (validated against `docs/CacaoTrack_PRD_UPDATED.md` §14 "existing frontend
>   should be refined", §5/§9.1, and `AGENTS.md`; the `_UPDATED` files were read only — **not edited**).
>   **(1) `/analytics` KPI cards:** **Total Grafted** now renders **green** (`border-success` /
>   `text-success` on the value + tree icon) and **Total Active Inventory** now **blue**
>   (`border-primary` / `text-primary` on the value + tree-fill icon) — the two roles were swapped;
>   Crude Mortality Rate stays red. Values/labels/sub-labels are unchanged, both still read the
>   centralized `AnalyticsMetrics` projections (RULE 5/6), and the PDF/CSV exports were not touched.
>   **(2) Sidebar:** removed the **Mortality Tracking**, **Regraft** and **Batches** links from the
>   Operational group of `resources/views/layouts/sidebar.blade.php`; **Daily Checklist** (under
>   `record_operations`) and **Scion Variety** (`manage_inventory`) remain. **The functions remain
>   fully intact** — routes `operational.mortality`, `operational.regraft`, `operational.batches`
>   (+ store/update/delete), controllers, views, permissions and data are untouched and still
>   reachable directly (e.g. `/operational/batches`) and through the Inventory row actions /
>   elsewhere. Recorded as an owner-directed divergence from PRD §5 / ERD §7 in `AGENTS.md` (no
>   doc amendment).
>   **(3) No migration, schema, route, permission-key, controller, calculation or `db_capstone` write.**
>   **Verified:** `php artisan view:cache` clean; focused suites
>   `MortalityTest|BatchManagementTest|RegraftTest` → **46 passed / 176 assertions** on
>   `db_capstone_test` (full suite not re-run — the last full baseline is **408 / 2001** at EB;
>   only Blade views changed). **Files:** `resources/views/analytics/index.blade.php`,
>   `resources/views/layouts/sidebar.blade.php`, `AGENTS.md`, `session.handoff.md`. Working tree stays
>   **uncommitted** (stacks on DI…EC; head `49351ce`).
> - **▶ Session EB (DONE — Inventory regraft sub-batches are collapsible through the same chevron toggle as the Batches page; 2026-10-19):**
>   Owner-directed UI/test pass on `/inventory` (validated against the client-approved v1.0 baseline
>   `docs/CacaoTrack_PRD_UPDATED.md` §9.3 "nested or grouped views so parent batches and their associated
>   regraft sub-batches are clearly distinguishable" / FR-08, and `docs/CacaoTrack_ERD_UPDATED.md` §3.8
>   `Parent_Batch_ID`; the `_UPDATED` files were read only — **not edited**; no PRD/ERD change).
>   **(1) One markup pattern (RULE 6):** `InventoryController@index` now adds `ancestors` (from the
>   single `BatchTree::flatten()` traversal) and `has_children` (derived from the flattened tree, no extra
>   queries) to `nodeMeta`. The table renders `collapse show child-of-{ancestorId}` on every nested row
>   and a chevron `data-bs-toggle="collapse"` targeting `.child-of-{batch_id}`, exactly matching
>   `operational.batches.partials.batch-row`; rows without children keep the `↳` indentation.
>   **(2) Scope:** parent rows collapse their entire subtree (nested sub-batches supported), while all
>   existing filters, ordering (newest-first), summary cards, row actions, modals and pagination are
>   unchanged.
>   **(3) No migration, schema, route, permission-key, calculation or `db_capstone` write.**
>   **(4) Tests:** `InventoryTest` +1 (chevron targets, collapse classes and the `nodeMeta`
>   `ancestors`/`has_children` payload); `NestedBatchesTest` now also locks those new `nodeMeta` keys.
>   **Verified:** full MySQL suite **408 passed / 2001 assertions** on `db_capstone_test` (was 407/1994
>   at EA); `vendor\bin\pint --test` + `php artisan view:cache` clean.
>   **Files:** `app/Http/Controllers/InventoryController.php`, `resources/views/inventory/index.blade.php`,
>   `tests/Feature/{InventoryTest,NestedBatchesTest}.php`, `AGENTS.md`, `session.handoff.md`. Working
>   tree stays **uncommitted** (stacks on DI…EB; head `49351ce`).
> - **▶ Session EA (DONE — Stock Ledger "Remaining Available" column removed everywhere; CSV export mirrors the client's manual ledger; 2026-10-12):**
>   Owner-directed pass on `/analytics/stock-ledger` (validated against the client-approved v1.0 baseline
>   `docs/CacaoTrack_PRD_UPDATED.md` §9.2/§9.3 — no running-balance column is defined; the owner's
>   example client ledger has none; `_UPDATED` read only — **not edited**; no PRD/ERD change).
>   **(1) Removed** the **Remaining Available** column from the on-screen month/grand-total tables, the
>   printable PDF (its 26mm column; the five section groups widened **34 → 39.2mm** to fill the page) and
>   the CSV export, plus the filter-help and footnote sentences on all three surfaces. The running
>   balance is **still computed and reconciled** per batch inside
>   `AnalyticsMetrics::stockLedgerGrid()` (`assertLedgerRunningBalanceReconciles()` against
>   `Batch::survivingQuantity()`) as an internal integrity guard — only its display was removed
>   (docblock updated; `StockLedgerRunningBalanceTest` still covers it).
>   **(2) CSV export rebuilt in the client's ledger layout** (owner-chosen from the example): one block
>   per month — month-title row, a grouped section header row (Grafted / Mortality / Regraft / Ready to
>   Plant / Pullout) with a per-Scion-Variety sub-header row, every real dated event, then the month
>   **TOTAL**; after all months the **GRAND TOTAL**, the shared footnote and the nested **Batch summary**.
>   New helpers `ledgerCsvSectionHeaders()` / `ledgerCsvVarietyHeaders()`; `ledgerCsvLine()` no longer
>   takes the month label nor emits the running balance. Values still read only from the same centralized
>   grid (RULE 5/6).
>   **(3) No migration, schema, route, permission-key or `db_capstone` write.**
>   **Verified:** full MySQL suite **407 passed / 1994 assertions** on `db_capstone_test` (was 407/1984
>   at DZ; +10 locks); `vendor\bin\pint --test` + `php artisan view:cache` clean; read-only live render on
>   `db_capstone` (direct controller calls, no HTTP/no DB write): page **72,631 B**, PDF **32,605 B**,
>   CSV **2,067 B**, none containing "Remaining Available".
>   **Files:** `app/Http/Controllers/AnalyticsController.php`, `app/Support/StockLedgerPdf.php`,
>   `app/Support/AnalyticsMetrics.php` (docblock), `resources/views/analytics/stock-ledger.blade.php`,
>   `tests/Feature/{StockLedgerNestingTest,StockLedgerRunningBalanceTest}.php`, `AGENTS.md`,
>   `session.handoff.md`. Working tree stays **uncommitted** (stacks on DI…DZ; head `49351ce`).
> - **▶ Session DZ (DONE — Dashboard `Total Active Inventory` card + Analytics `Total Grafted` card; 2026-10-12):**
>   Owner-directed KPI-card pass. **`/` (Dashboard):** added the **Total Active Inventory** flash card
>   (`AnalyticsMetrics::totalActiveInventory()` — live **813**; `border-primary`; "Plants currently
>   available", mirroring the `/inventory` card); the KPI row is now 3 cards (Total Grafted, Total
>   Active Inventory, Crude Mortality Rate) at `col-xl-4 col-md-6`; `DashboardController` passes the
>   new value. **`/analytics`:** added the **Total Grafted** flash card
>   (`AnalyticsMetrics::totalGrafted()` — live **853**); the KPI row is now 3 cards (Total Grafted,
>   Total Active Inventory, Crude Mortality Rate) at `col-md-4`;
>   `AnalyticsController::operationalData()` passes the new value, and the **Operational Analytics PDF
>   KPI strip + CSV KPI section gained "Total Grafted"** so page/PDF/CSV stay one definition (RULE 6;
>   Session DY precedent). Both cards are live read-only projections — **never hardcoded** (RULE 5/6;
>   PRD §16), validated against the client-approved v1.0 baseline (`docs/CacaoTrack_PRD_UPDATED.md` §10
>   "Current Inventory" dashboard list / §9.1 inventory + batch info / §11 same definitions; `_UPDATED`
>   read only — **not edited**). No migration, schema, route, permission-key or live-data change.
>   **Verified:** full MySQL suite **407 passed / 1984 assertions** on `db_capstone_test` (was 407/1977
>   at DY; +7 locks in `DashboardTest`/`AnalyticsTest`); `vendor\bin\pint` + `php artisan view:cache`
>   clean; read-only live render on `db_capstone` through both controllers (no HTTP, no DB write):
>   Dashboard **813**, Analytics **853** (active **813**); the 4-box PDF KPI strip's labels measured
>   ≤ 30.5mm in the 38.25mm label cell. **Files:** `DashboardController`, `AnalyticsController`,
>   `OperationalAnalyticsPdf` (docblock), `dashboard/index.blade.php`, `analytics/index.blade.php`,
>   `DashboardTest`, `AnalyticsTest`, `AGENTS.md`, `session.handoff.md`. Working tree stays
>   **uncommitted** (stacks on DI…DY; head `49351ce`).
> - **▶ Session DY (DONE — Regraft Deficit removed from `/inventory`, `/` (Dashboard) and `/analytics`; 2026-10-12):**
>   Owner-directed removal of the working, not-yet-client-confirmed Regraft Deficit (OD-10). Validated
>   against the client-approved v1.0 baseline (`docs/CacaoTrack_PRD_UPDATED.md` §2.7/FR-08, §9.1/§10;
>   `docs/CacaoTrack_ERD_UPDATED.md` §3.8/§3.12 — no Deficit is defined there); the `_UPDATED` files
>   were read only — **not edited**.
>   **(1) `/inventory`:** removed the **DEFICIT column**, the **Regraft Deficit summary card**, the row
>   **More → Record Deficit** action, the **Deficit Breakdown modal**, the **Deficit line in the View
>   modal** and the `data-deficit` payload/JS; the More dropdown is hidden where a row has no actions
>   (archived / without `record_operations`). `InventoryController` no longer sums/passes `totalDeficit`
>   nor emits a per-row `deficit`.
>   **(2) `/` (Dashboard):** removed the **Regraft Deficit KPI card** (`DashboardController` no longer
>   passes `totalRegraftDeficit`); the two remaining cards span `col-xl-6`.
>   **(3) `/analytics`:** removed the **Regraft Deficit KPI card** and the **Regraft Deficit by Batch
>   chart** + its JS, and their sections in the Operational Analytics **CSV and PDF exports**
>   (`OperationalAnalyticsPdf` constructor lost the series) — page/PDF/CSV stay one definition (RULE 6).
>   **(4) Kept:** the `/operational/regraft` page keeps its Deficit cards/column, and
>   `Batch::regraftDeficit()` / `AnalyticsMetrics::totalRegraftDeficit()` / `regraftDeficitByBatch()`
>   remain (still used there and by report snapshots). No migration, schema, route, permission or
>   `db_capstone` write; `cacao:health` PASSED (5 batches, all 5 rules).
>   **(5) Tests:** full MySQL suite **407 passed / 1977 assertions** on `db_capstone_test`;
>   `vendor\bin\pint` + `php artisan view:cache` clean. `InventoryTest`/`DashboardTest`/`AnalyticsTest`
>   updated (incl. `assertDontSee` removal locks), `SeededFamilyKpiTest` no longer asserts `totalDeficit`.
>   **Files:** the 3 page views, `InventoryController`/`DashboardController`/`AnalyticsController`,
>   `OperationalAnalyticsPdf`, the 4 test files, `AGENTS.md`, `session.handoff.md`. Working tree stays
>   **uncommitted** (stacks on DI…DX; head `49351ce`).
> - **▶ Session DX (DONE — Stock Ledger ascending-order requirement validated + regression test; test-only; 2026-10-12):**
>   Owner-directed verification pass on `/analytics/stock-ledger` ("Ascending Order" = data arranged smallest
>   → largest, as in an SQL `ORDER BY … ASC`).
>   **(1) Validation (docs + live page, read-only):** checked `docs/CacaoTrack_PRD_UPDATED.md` /
>   `docs/CacaoTrack_ERD_UPDATED.md` (client-approved v1.0 baseline; read only — **not edited**) and
>   `AGENTS.md` — PRD §9.2 ("Tables should support filtering, sorting, and pagination where appropriate")
>   and §9.3 (Stock Ledger) plus the ERD mandate no specific order, so nothing conflicts with ascending.
>   The live page already renders ascending: month cards oldest → newest
>   (`AnalyticsMetrics::stockLedgerGrid()` `ksort`), event rows by non-decreasing date (`usort` date + sort),
>   and the nested **Batch summary** by batch code through `BatchTree`; the PDF/CSV read the same
>   centralized grid, so all three surfaces share the order.
>   **(2) Locked with one test:** new
>   `StockLedgerRunningBalanceTest::test_ledger_is_rendered_in_ascending_order` — seeded months
>   `2026-07 → 2026-09` ascending, every month's rows non-decreasing by date and stamped inside their own
>   month card, batch summary `B001, B001-R1, B001-R1-R1, B002, B003`, and the rendered page shows the
>   month labels / batch codes in that same order. **No controller, view, query, route, permission key or
>   live-data change.**
>   **(3) Verified:** full MySQL suite **407 passed / 1986 assertions** on `db_capstone_test` (was 406/1960
>   at DW); `vendor\bin\pint` clean; `php artisan view:cache` clean; read-only live kernel
>   `GET /analytics/stock-ledger` → **200** (October 2026 card, rows Oct 02 → Oct 03, summary B001 → B002;
>   incidental session row(s) as in prior read-only smokes); `php artisan cacao:health` **PASSED**
>   (5 batches, all 5 rules).
>   **Files:** `tests/Feature/StockLedgerRunningBalanceTest.php` (+1 test), `session.handoff.md`.
>   Working tree stays **uncommitted** (stacks on DI…DW; head `49351ce`).
> - **▶ Session DW (DONE — Daily Checklist History tab; read-only; 2026-10-12):**
>   Owner-directed: a **History tab inside the existing `/operational/checklist` page** (no new sidebar item,
>   no new table, no migration/schema change) for reviewing past daily inspections.
>   **(1) One record per day with stored `task_completes` rows**, built by the new centralized
>   `App\Support\ChecklistHistory` (RULE 6): each record carries date, distinct staff (name + role),
>   verifier(s), last verification time, per-day completed/total/pending counts and a derived status —
>   **Completed** = every task on that day's list is done; **Pending** = some still open (owner-confirmed:
>   `Not Done` is a per-task state only, since a day with zero completions has no stored row). The day's
>   task list is the same definition as the existing printable checklist (active tasks now ∪ tasks completed
>   that day, priority order) — `exportPdf` now reads the same `ChecklistHistory::tasksForDate()` so History
>   and the PDF can never disagree.
>   **(2) UI (`resources/views/operational/checklist/index.blade.php`):** green-and-white segmented
>   `Today's Checklist | History` tabs; history filter card (Search by staff/task, From/To date range,
>   Status → All/Completed/Pending, Clear Filters) using the shared `data-auto-filter` auto-submit (no Apply
>   button); two-column responsive layout — paginated record list (newest first, selected row highlighted,
>   10/page, filters kept across pages, `history_date` dropped from page links) and the read-only selected
>   record detail (Staff / Verified By (role) / Time Checked + Checklist Tasks table with per-task
>   Completed/Not Done status and real remarks). Footer actions: **Print** → the existing date-filtered
>   `operational.checklist.export` PDF, and **View Full Details** → a read-only modal with per-task
>   completed-by / verified-by / verified-at timestamps. Empty state when no records match; no horizontal
>   overflow (`table-responsive` + min-width guards).
>   **(3) Read-only:** history is GET-only — no route, controller action or form can modify a past record;
>   a test asserts the page writes nothing (`task_completes` / `activity_tasks` / `activity_logs` unchanged).
>   **(4) Tests:** `DailyChecklistTest` +6 (record list + selected detail rendering, Completed/Pending
>   derivation + status filter, staff/task search + date range, per-task Not Done + no writes, auto-filter
>   form + empty state, pagination keeps filters). **Verified:** full MySQL suite **406 passed / 1960
>   assertions** on `db_capstone_test` (was 400/1900 at DV); `vendor\bin\pint` clean; `php artisan
>   view:cache` clean; `npm run build` re-run (new scoped CSS). **Read-only live check on `db_capstone`:**
>   `ChecklistHistory::records()` → **1 record** (2026-10-02, pending 1/4, Admin User); kernel
>   `GET /operational/checklist?tab=history` → **200** with the record, Print link and full-details modal
>   rendered; `cacao:health` **PASSED** (5 batches, all 5 rules). **No migration, schema, route,
>   permission-key or live-data write** (one incidental session row from the smoke, as in prior sessions).
>   **Files:** new `app/Support/ChecklistHistory.php`; `app/Http/Controllers/DailyChecklistController.php`;
>   `resources/views/operational/checklist/index.blade.php`; `resources/css/app.css`;
>   `tests/Feature/DailyChecklistTest.php`; `session.handoff.md`. Working tree remains **uncommitted**
>   (stacks on DI…DV; head `49351ce`).
> - **▶ Session DV (DONE — Archived tabs removed; Archive → Delete with dependency guards; live `db_capstone` cleanup; 2026-10-12):**
>   Owner-directed: remove the **Archived tabs** from `/operational/checklist`, `/operational/batches` and
>   `/operational/scion-varieties` and replace the **Archive** row action with a **Delete** that actually
>   removes the record from the database (the owner reported archived rows lingering in the DB).
>   **(1) Validation.** `docs/CacaoTrack_PRD_UPDATED.md` / `docs/CacaoTrack_ERD_UPDATED.md` (client-approved
>   v1.0 baseline; read only — **not edited**) keep `Archived_At` nullable on Activity_Task §3.4, Batch
>   §3.8 and Scion_Variety §3.9 — no schema/migration change was needed and none was made. The canonical
>   v2.0 PRD/ERD are likewise unchanged (no rule change). Per **OD-5** (removal policy), a record with
>   dependent records is **not** removable, so Delete is **guarded server-side** rather than cascading.
>   The Design doc's former "archive instead of delete" practice is superseded for these three pages by
>   owner direction (recorded here; no PRD/ERD/Design file was edited).
>   **(2) One guarded delete rule per model (RULE 6):** `ActivityTask::canBeDeleted()` = no completion
>   history (role gate unchanged: Manager/Administrator via `Role::CHECKLIST_DELETE_ROLES`);
>   **new** `Batch::deletionBlockers()` / `canBeDeleted()` = no mortality records, regraft records,
>   regraft sub-batches, stock-out lines, inventory snapshots or activity history; **new**
>   `ScionVariety::deletionBlockers()` / `canBeDeleted()` = no batch assignments, stock-out lines or
>   stock-in receipts (new `stockIns()` relation). Every delete is a DB transaction that removes the row
>   and writes `Activity_Log` (`task_deleted`, `batch_deleted`, new `scion_variety_deleted` type + badge).
>   When the server would refuse, the row's Delete button is disabled and its tooltip lists the blockers.
>   **(3) Routes:** removed the 6 archive/restore routes; added `DELETE /operational/batches/{batch}`
>   (`operational.batches.destroy`) and `DELETE /operational/scion-varieties/{scion_variety}`
>   (`operational.scion-varieties.destroy`); the checklist `destroy` route is unchanged. Controllers:
>   `archive()`/`restore()` replaced by `destroy()` on Batch and Scion Variety; checklist
>   `archiveTask()`/`restoreTask()` deleted and `destroyTask()` no longer requires the archived state.
>   **(4) Views:** tab pills, archived branches and archive/restore modals removed on all three pages;
>   Delete confirmation modals added; listings are active-only (legacy archived rows, if any, stay hidden
>   in the DB but are no longer reachable from the UI).
>   **(5) Live `db_capstone` (approved one-time cleanup — fresh verified backup first, left untouched
>   outside the repo):** `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_scion_delete_20261012_175232.sql`
>   (**34,928 B**). The 3 archived **unreferenced** scion varieties (Trinitario, Toledo, Forastero) were
>   deleted through the new guarded app path as user 1, writing 3 `scion_variety_deleted` Activity_Log rows
>   (activity_id 11–13). Live after: `scion_varieties` **2** (Batangas, Toledo), archived **0**; batches
>   **5** unchanged; `cacao:health` **PASSED** (5 batches, all 5 rules). No other live data touched; **no
>   migration / schema action**.
>   **(6) Tests:** full MySQL suite **400 passed / 1900 assertions** on `db_capstone_test` (was 401/1913
>   at DU; net −1 test after deleting the obsolete archive/restore tests and adding the guarded-delete
>   tests plus new `ScionVarietyManagementTest`); Pint + `view:cache` clean. Tests run only against
>   `db_capstone_test`.
>   **Files:** `app/Models/{ActivityTask,Batch,ScionVariety,ActivityLog}.php`,
>   `app/Http/Controllers/{DailyChecklistController,BatchController,ScionVarietyController}.php`,
>   `routes/web.php`, the three page views (+ `operational/batches/partials/batch-row.blade.php`),
>   `tests/Feature/{DailyChecklistTest,BatchManagementTest,SeededFamilyKpiTest,ScionVarietyManagementTest}.php`,
>   `session.handoff.md`, `AGENTS.md`. Working tree is **uncommitted** (stacks on the DI…DU files; head
>   `49351ce`).
> - **▶ Session DU (DONE — Inventory listing is newest-first; 2026-10-12):**
>   Owner-directed ordering pass on `/inventory` (validated against the client-approved v1.0 baseline
>   `docs/CacaoTrack_PRD_UPDATED.md` §9.3 "nested or grouped views so parent batches and their associated
>   regraft sub-batches are clearly distinguishable" / FR-08 and `docs/CacaoTrack_ERD_UPDATED.md` §3.12;
>   the `_UPDATED` files were read only — not edited).
>   **(1) One ordering definition (RULE 6):** `App\Support\BatchTree::flatten()` / `groups()` gained an
>   opt-in `$descending` flag — roots by `grafted_date` then `batch_code` descending (newest first; nulls
>   last), children still ascending by code and always nested under their parent. Every other consumer
>   (Batches page, Stock Ledger, analytics/historical reports, batch dropdowns) keeps the default
>   ascending order.
>   **(2) Caller:** `InventoryController@index` passes `descending: true`, so a newly added batch (code +
>   today's date assigned on save) lands at the top of page 1; pagination, filters, summary cards, the
>   nested `Regrafted` rows and all row actions are unchanged.
>   **(3) Tests:** `BatchTreeTest` +1 (descending roots incl. the same-date code tie-break and preserved
>   nesting); `InventoryTest` +1 (page lists `B002` before the older `B001` family with `B001-R1` still
>   nested).
>   **Verified:** full MySQL suite **401 passed / 1913 assertions** on `db_capstone_test` (was 399/1909 at
>   DT); `vendor\bin\pint` clean; read-only live `db_capstone` check — tree order starts with the newest
>   root **B002** (`B002, B001, B001-R1, B001-R1-R1, B001-R2`); `cacao:health` **PASSED** (5 batches).
>   **No migration, schema, route, permission-key or `db_capstone` write.**
> - **▶ Session DT (DONE — the Inventory View modal now lists the recorded mortality causes (one centralized grouped query, `MortalityRecord::causeBreakdownByBatch()`; read-only; no migration, schema, route, permission-key or `db_capstone` write**))
> - **▶ Session DT (DONE — Inventory View modal shows the recorded mortality causes; 2026-10-12):**
>   Owner-directed read-only display pass on `/inventory` (validated against the client-approved v1.0
>   baseline `docs/CacaoTrack_PRD_UPDATED.md` FR-04 "Cause" / FR-08 "Inventory values must be derived from
>   actual records" and `docs/CacaoTrack_ERD_UPDATED.md` §3.10 `Mortality_Record.Cause`; the `_UPDATED`
>   files were read only — not edited).
>   **(1) One breakdown (RULE 6):** new `MortalityRecord::causeBreakdownByBatch()` groups each batch's
>   `mortality_records` by the recorded `cause` (returned exactly as stored — including the
>   `Others: <description>` specification; a blank cause reports as `Unspecified`), ordered by quantity
>   then cause. `InventoryController@index` calls it **once** for the whole filtered set (no N+1) and
>   exposes `mortality_causes` in each `summaries` row.
>   **(2) View modal:** the read-only `#viewBatchModal` gained a **Mortality Causes** section (CAUSE /
>   QUANTITY table) fed by the same `data-details` payload; it shows "No mortality recorded for this
>   batch." when the batch has no deaths. Rows are built with JS `textContent`, so a user-entered
>   "Others" description is never interpreted as markup. Available to every viewer (archived included) —
>   no new route, permission or write action.
>   **(3) Tests:** `InventoryTest` +1 (`test_inventory_view_modal_shows_the_recorded_mortality_causes` —
>   grouped/ordered breakdown, the raw `Others: Rat damage` specification preserved, and the rendered
>   payload); the existing View-modal test now also asserts the empty-cause state
>   (`"mortality_causes":[]` + the empty note).
>   **Verified:** full MySQL suite **399 passed / 1909 assertions** on `db_capstone_test` (was 398/1898 at
>   DS); `vendor\bin\pint --test` + `php artisan view:cache` clean; read-only live `db_capstone` check —
>   `B001-R2 => Disease 110`, `B002 => Others: Over-irrigation 13`, other batches empty. **No migration,
>   schema, route, permission-key or `db_capstone` write.** The cumulative DI+…+DT working-tree files
>   remain **uncommitted** (head `49351ce`).
> - **▶ Session DS (DONE — auto-filter UX unified on Inventory / Stock In / Stock Out / Cacao Tree Stock Ledger; 2026-10-04):**
>   Owner-directed UI/test pass (validated against the client-approved v1.0 baseline
>   `docs/CacaoTrack_PRD_UPDATED.md` §9.2 *"Tables should support filtering … where appropriate"* and §14
>   (clean / consistent / refined UI); FR-09/FR-10 fields unchanged;
>   `docs/CacaoTrack_ERD_UPDATED.md` §3.7 / §3.12 / §3.14 / §3.15 unchanged; **the `_UPDATED` files were read
>   only — not edited**).
>   **(1) One behavior (RULE 6):** the auto-apply filter JS now lives **once** in `resources/js/app.js` — any
>   `form[data-auto-filter]` submits itself server-side: `input[name="search"]` debounces **400 ms**, select /
>   date / number fields submit on `change`, Enter submits immediately, and a per-form guard blocks double
>   navigations. Inventory's inline copy (Session DR) was removed and its form marked `data-auto-filter`, so
>   all four pages share one implementation (RULE 6).
>   **(2) Same Bootstrap filter card** on `/inventory`, `/inventory/stock-in`, `/inventory/stock-out` and
>   `/analytics/stock-ledger`: `card mb-4` > `card-body py-3` > `row g-3 align-items-end`,
>   `form-label text-muted small fw-bold mb-1` labels, `input-group-sm` search, `form-select-sm` /
>   `form-control-sm` fields, and a single **Clear Filters** button (link to the clean route) replacing the
>   Apply/funnel + reset pair. Page-specific fields kept: **Supplier** on Stock In; **Client** (+ Batch) on
>   Stock Out; Batch / Scion Variety / Date From / Date To on the Stock Ledger (its `Filter Ledger` header
>   was removed to match). No controller/route/validation change; every value is still server-side from live
>   records.
>   **(3) Tests:** +3 (`StockInTest`, `StockOutTest`, `AnalyticsTest` — each page renders its
>   `data-auto-filter` form + **Clear Filters** and no funnel/Apply control); the `InventoryTest`
>   auto-filter test (Session DR) also asserts `data-auto-filter`; the existing server-side filter tests are
>   unchanged.
>   **Verified:** full MySQL suite **398 passed / 1898 assertions** on `db_capstone_test` (was 395/1873 at
>   DR); `vendor\bin\pint --test` + `php artisan view:cache` clean; `npm run build` re-run.
>   **No migration, schema, controller, route, permission-key or `db_capstone` change.**
> - **▶ Session DR (DONE — Inventory filters auto-apply server-side; Apply button removed, Clear Filters kept; 2026-10-04):**
>   Owner-directed UI/test pass on `/inventory` (validated against the client-approved v1.0 baseline
>   `docs/CacaoTrack_PRD_UPDATED.md` §2.7 / FR-08 — Inventory values must come from actual operational
>   records, unchanged — and canonical v2.0 PRD §9.2 *"Tables should support filtering … where
>   appropriate"*; ERD §3.12 / `_ERD_UPDATED` §3.12 unchanged; **the `_UPDATED` files were read only — not
>   edited**).
>   **(1) Filter form** (`resources/views/inventory/index.blade.php`): the Apply/Filter submit button was
>   removed; the form (`#inventoryFilterForm`) auto-submits through the same server-side GET
>   (`InventoryController@index` unchanged) — the search input debounces **400 ms** on `input`, the scion
>   variety/status/batch-month selects and the batch-year field submit on `change`, and Enter submits
>   immediately. Only the **Clear Filters** button (link to `inventory.index` with no query) remains.
>   A `filterSubmitting` guard prevents double navigations. The five summary cards and the table refresh
>   together because both are server-rendered from the same filtered query (Session DP/DJ semantics
>   unchanged; server-side validation unchanged).
>   **(2) Tests:** `InventoryTest` +1 (`test_inventory_filters_apply_automatically_without_an_apply_button`)
>   — the page renders `#inventoryFilterForm` / `#filterSearch` / **Clear Filters** and no funnel/Apply
>   control; the existing server-side filter tests are unchanged.
>   **Verified:** full MySQL suite **395 passed / 1873 assertions** on `db_capstone_test` (was 394/1867 at
>   DQ); `vendor\bin\pint --test` + `php artisan view:cache` clean. **No migration, schema, controller,
>   route, permission-key or `db_capstone` change.**
> - **▶ Session DQ (DONE — Add Batch form: Batch Code + Grafted Date are system-assigned; 2026-10-04):**
>   Owner-directed UI/controller/test pass on the hosted Add Batch form (`/inventory` and the identical
>   `/operational/batches` modal; validated against PRD §2.3/§2.6/FR-06/§7/§11/§14 and ERD §3.8; canonical
>   v2.0 docs authoritative, the `_UPDATED` v1.0 files read only — not edited).
>   **(1) Batch Code:** no longer typed. New centralized `Batch::nextBatchCode()` (RULE 6) returns `B` + the
>   next sequential 3-digit number past the highest established ROOT batch (`B001`, `B002`, …); regraft
>   sub-batch codes (`B001-R1`) and other non-`B<digits>` codes are ignored. `BatchController@store` assigns
>   it inside the existing create transaction (`batch_code` unique index is the final guard); both page
>   controllers pass `nextBatchCode` so the modal previews the code that will be assigned (live preview:
>   **B003**).
>   **(2) Grafted Date:** no longer picked. The server stores `now()->toDateString()` at establish time
>   (PRD §2.6/OD-7: the 21-day cycle is derived from `grafted_date`), so the batch starts its cycle the day
>   it is recorded. Both fields are shown read-only/disabled with the note "Assigned automatically …".
>   **(3) Validation (server-authoritative):** `StoreBatchRequest` now validates `Batch::storeRules()` —
>   `scion_variety_id` (nullable, active), `total_grafted` (required, integer ≥ 1) and `status`
>   (active/inactive) only. Submitted `batch_code`/`grafted_date` are ignored (a test posts `HACK-999` + a
>   future date and asserts `B002` + today are stored). `Batch::rules()` (and `UpdateBatchRequest`, incl.
>   the Edit modal's rename/re-date path) is unchanged.
>   **(4) Tests:** `InventoryTest` +1 (auto-assign from `/inventory`), 2 updated; `BatchManagementTest` +2
>   (sequential code; `nextBatchCode()` ignores sub-batch/custom codes), duplicate-code + future-date
>   creation tests replaced. **Verified:** full MySQL suite **394 passed / 1867 assertions** on
>   `db_capstone_test` (was 392/1855 at DP); Pint + `php -l` + `view:cache` clean; read-only live check —
>   `Batch::nextBatchCode()` = **B003** (roots B001/B002; sub-batches ignored). **No migration, schema,
>   route, permission-key or `db_capstone` change.**
> - **▶ Session DP (DONE — Inventory summary cards; 2026-10-03):**
>   Owner-directed UI/controller/test pass on `/inventory` (validated against PRD §2.4/§2.5/§2.7/§10/§11/FR-08
>   and ERD §3.8/§3.10/§3.11/§3.12; canonical v2.0 docs authoritative, the attached `_UPDATED` v1.0 files
>   read only — not edited).
>   **(1) Cards:** the single "Total Grafted" card was replaced (owner choice) with five filter-aware cards
>   whose figures aggregate the SAME live per-batch values the rows render (RULE 5/6): **Total Mortality**
>   = effective recorded mortality (strictly incrementing — PRD §2.5/§11, ERD §3.10); **Regraft Deficit**
>   = `Batch::regraftDeficit()` (`max(0, mortality − regraft)` — the working definition, OD-10 still pending
>   client confirmation, exactly as the DEFICIT column shows it); **Total Regrafted** = the cached
>   `batches.regraft` counter (OD-12, reconciled against `regraft_records` by `cacao:health`);
>   **Total Active Inventory** = SUM(`available_count`) of ACTIVE batches only
>   (`AnalyticsMetrics::totalActiveInventory()` definition — archived excluded — applied to the filtered
>   set); **Total Batches** = count of the listed (filtered) batches. Card subtexts exactly as specified
>   by the owner ("Total plants recorded as dead" / "Plants still needing regrafting" / "Total plants
>   regrafted" / "Plants currently available" / "Total number of batches").
>   **(2) Controller:** `InventoryController@index` computes the five values from the existing `$summaries`
>   map (no new queries / no N+1) and drops the old `totalGrafted` view variable; the docblock records each
>   definition. The cards follow the page filters (search / scion variety / lifecycle status / batch
>   month-year), matching the Regraft page summary cards.
>   **(3) View:** `resources/views/inventory/index.blade.php` summary block is now a responsive 5-card row
>   (`row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-5`) with Bootstrap border-start colors
>   danger/warning/success/primary/dark. No CSS/JS change.
>   **(4) Tests:** `InventoryTest` +2 (card values incl. archived exclusion + rendered titles/subtexts;
>   filter-awareness) and the page test updated; `SeededFamilyKpiTest` dropped the page `totalGrafted`
>   assertion (the page no longer shows that total) and +1 seeded card test (55 / 25 / 130 / 730 / 5).
>   **Verified:** full MySQL suite **392 passed / 1855 assertions** on `db_capstone_test` (was 389/1831 at
>   DO); Pint + `php -l` + `view:cache` clean. **No migration, schema, route, permission-key or
>   `db_capstone` change.**
>   **(5) Live (read-only) check on the owner-entered data:** the Inventory controller/view rendered
>   83,565 bytes with all five cards present, and every card matched an independent live query exactly —
>   Total Mortality **123**, Regraft Deficit **123**, Total Regrafted **270**, Total Active Inventory
>   **853**, Total Batches **5**. No DB write was performed.
> - **▶ Session DO (DONE — Inventory Status column + `[View] [More ▾]` action + "Date:" labels removed; 2026-10-03):**
>   Owner-directed UI/controller/test pass on `/inventory`, validated against the client-approved v1.0 baseline
>   `docs/CacaoTrack_PRD_UPDATED.md` (§2.6 21-day cycle; FR-06 Batch Status; FR-08 "Applicable current
>   quantity/status") / `docs/CacaoTrack_ERD_UPDATED.md` (§3.8 `Batch.Status`) and the canonical v2.0 docs
>   (OD-7: the cycle end is **derived** `Grafted_Date + 21 days`; the stored `Batch.Status` enumeration stays
>   `active`/`inactive`). **The `_UPDATED` files were read only — not edited.**
>   **(1) Date labels:** the `Date:` prefix was removed from the BATCH DETAILS / TOTAL GRAFTED / REGRAFT
>   inline dates (the Session DN merged layout is kept; only the label text is gone). Archived rows now
>   carry a small "Archived" note in BATCH DETAILS.
>   **(2) Status column + filter:** new `Batch::lifecycleStatus()` (RULE 6, display-only — the stored
>   `status` column is untouched): `cancelled` = invalidated record (stored `inactive` or archived) →
>   `completed` = no available balance (`available_count = 0`) → `ready` = 21-day cycle reached
>   (`grafted_date + 21 days`) → `growing` otherwise. Centralized `Batch::LIFECYCLE_*` constants /
>   `LIFECYCLE_STATUSES` / `lifecycleLabels()` / `cycleReadyDate()` / `scopeWhereLifecycleStatus()`
>   (query equivalent with the same precedence, incl. the boundary: exactly 21 days = Ready). The Status
>   filter dropdown now offers Growing / Ready / Completed / Cancelled/Void and filters through that scope
>   (replacing the old `active`/`depleted` filter). Badge colors (owner-specified): Growing
>   `bg-warning text-dark`, Ready `bg-success`, Completed lavender `.badge-lifecycle-completed` (new
>   `app.css` rule; `npm run build` re-run), Cancelled/Void `bg-danger`.
>   **(3) Action column** is now `[View] [More ▾]` per the owner screenshot: **View** opens a read-only
>   `#viewBatchModal` (Batch Information + Counts, fed by the same row payload as the Deficit review — no
>   new queries), available to every viewer including archived rows; **More** groups **Regraft** (disabled
>   when `survivingQuantity() = 0`), **Record Mortality** and **Record Deficit** — the same forms/routes/
>   validation as before and as the operational pages (no fork). Regraft/Mortality render only for
>   non-archived `record_operations` users; Record Deficit (read-only review) stays available for archived
>   rows and users without the permission. The standalone Deficit action button was folded into More; the
>   DEFICIT column badge is unchanged.
>   **(4) Tests:** `InventoryTest` updated (bare inline dates + `Date:` absence; lifecycle status column,
>   filter and the exact-21-day boundary; View modal + payload; More grouping; View available without
>   `record_operations`) and the class docblock updated. **Verified:** full MySQL suite
>   **389 passed / 1831 assertions** on `db_capstone_test` (was 385/1797 at DN); Pint + `php -l` +
>   `view:cache` clean; `npm run build` re-run. **No migration, schema, route, permission-key or
>   `db_capstone` change.**
>   **(5) Live (read-only) smoke — the owner has re-entered operational data since the Session DI wipe:**
>   kernel `GET /inventory` as the existing admin `admin@cacaotrack.test` returned **200** with the STATUS
>   column, View modal and More menu rendered and **0** `Date:` labels. Read-only state check:
>   **5 batches** (B001, B001-R1, B001-R1-R1, B001-R2, B002), stock_ins 2, mortality_records 2,
>   regraft_records 3, stock_outs/stock_out_details/inventories 0, activity_logs 9; master data intact
>   (users 1 · scion_varieties 5 · suppliers 3 · clients 3); `php artisan cacao:health` **PASSED**
>   (`5 batch(es) satisfy all invariants`, all 5 rules). No operational/schema/data write was performed.
> - **▶ Session DN (DONE — Inventory table values merged with their dates into single columns; nested-row badge renamed "Regrafted"; 2026-10-03):**
>   Owner-directed UI/test pass on `/inventory` (validated against PRD §2.7/FR-08/§11/§14/§16 and
>   ERD §3.8/§3.11/§3.12; canonical v2.0 docs authoritative, the attached `_UPDATED` v1.0 files left untouched).
>   **(1) Merged date columns (request 1):** `BATCH DATE` + `BATCH` are now one **BATCH DETAILS** column
>   (batch code on the first line; `Date: Jul 15, 2026` in smaller muted text below); **TOTAL GRAFTED** shows
>   the quantity with its grafted date below; **REGRAFT** shows the counter with the date of its most recent
>   regraft record below (`—` when none). The owner explicitly chose `grafted_date` for both Batch Details and
>   Total Grafted — the same value today's Batch Date column already showed, so the Batch Month/Year filter
>   stays in sync. `InventoryController@index` added one eager aggregate
>   `withMax('regraftRecordsAsParent as latest_regraft_date', 'regraft_date')` (no N+1) exposed in
>   `summaries`; every calculation, filter, sort, permission and action is unchanged; the empty-state colspan
>   went 9→8. **(2) Badge rename (request 2):** the nested-row **Sub-batch** badge in the Inventory table now
>   reads **Regrafted**; the Batches page badge and the other in-page "sub-batch" copy (footnote + Regraft
>   modal) were deliberately left untouched. **(3) Tests:** `InventoryTest` +1 (header assertion now
>   `BATCH DETAILS` + `Date: Jul 15, 2026`; new test records a real regraft and asserts the inline dates under
>   Batch Details / Total Grafted / Regraft plus `summaries.latest_regraft_date`), `NestedBatchesTest`
>   inventory badge assertion → `Regrafted`. **Verified:** full MySQL suite **385 passed / 1797 assertions**
>   on `db_capstone_test` (was 384/1789 at DM); Pint + `php -l` + `view:cache` clean. **No migration, schema,
>   route, permission-key or `db_capstone` change.**
> - **▶ Session DM (DONE — Inventory row Mortality/Regraft actions open the same forms as the operational pages in modals, prefilled with the clicked batch; 2026-10-03):**
>   Owner-directed UI/test pass on `/inventory` (validated against PRD §2.5/§2.8/FR-04/FR-05/FR-08/§7/§8/§14/§16
>   and ERD §3.8/§3.10/§3.11; canonical v2.0 docs authoritative, the attached `_UPDATED` v1.0 files left untouched).
>   **(1)** The row **Mortality** / **Regraft** buttons now open **the same forms as the operational pages**
>   (`#recordMortalityModal` → `operational.mortality.records.store`; `#recordRegraftModal` →
>   `operational.regraft.records.store`) with the clicked batch preselected. No fork of the forms, validation or
>   controllers: `StoreMortalityRecordRequest` (bag `mortality`) / `StoreRegraftRecordRequest` (bag `regraft`) and
>   the existing store actions are reused untouched; a validation failure reopens the right modal on `/inventory`
>   (JS) with `old()` input, and the success redirects still go to the operational pages.
>   **(2) Controller:** `InventoryController@index` now also passes the Mortality dropdown data (active batches +
>   per-batch `quantityBreakdown()`) and the Regraft dropdown data (active, regraftable batches via
>   `survivingQuantity()` + active scion varieties), matching what the operational pages pass. The per-row summary
>   gained `regraftable` so the Regraft button is only offered when the batch has a surviving balance (otherwise a
>   disabled button with "Nothing to regraft"). **(3) Permission:** both modals render only for
>   `record_operations` users and the target routes stay server-side gated — no UI-only security.
>   **(4) Tests:** `InventoryTest` now 28 passed (modal exposure + form fields; hidden without `record_operations`;
>   archived suppression; mortality + regraft validation from `/inventory` with the right error bags; both success
>   flows through the shared routes). **Verified:** full MySQL suite **384 passed / 1789 assertions** on
>   `db_capstone_test` (was 379/1744 at DL); Pint + `php -l` + `view:cache` clean. **No migration, schema, route,
>   permission-key or `db_capstone` change.**
> - **▶ Session DL (DONE — Inventory page gained the Add Batch modal (the same FR-06 form as the Batches page), gated by `record_operations`; 2026-10-03):**
>   Owner-directed UI/test pass on `/inventory` (validated against PRD §2.3/§5/FR-06/§7/§8/§13/§14/§16 and
>   ERD §3.8/§3.9; canonical v2.0 docs authoritative, the attached `_UPDATED` v1.0 files left untouched).
>   **(1)** `resources/views/inventory/index.blade.php` gained a page-header **Add Batch** button that opens
>   the **same `#addBatchModal` form as `/operational/batches`** (Batch Code, Scion Variety (optional),
>   Grafted Date, Status, Total Grafted). It posts to the single existing route
>   `operational.batches.manage.store` (`POST /operational/batches/manage`) and validates through
>   `StoreBatchRequest` (error bag `batch`); `BatchController@store` still owns the transaction,
>   `available_count = total_grafted` (provisional `OD-3`) and the `Activity_Log` row. A validation failure
>   reopens the modal on `/inventory` (JS) with `old()` input; the existing success redirect to
>   `/operational/batches` is unchanged.
>   **(2) Permission:** both the button **and the modal markup** render only for `record_operations` users;
>   the target route stays server-side gated (`permission:record_operations`) — no UI-only security.
>   **(3) Superseded earlier attempt:** an eye-icon read-only Batch Details modal briefly added the same day
>   was **reverted** (owner: "not eyes icon but the add batches modal"); `InventoryController` is back to its
>   Session DJ state (no new eager loads/counts) and no `details` payload remains.
>   **(4) Tests:** `InventoryTest` +3 — modal/form exposed with the route + fields; hidden without
>   `record_operations`; a submission from `/inventory` redirects back with the `batch` error bag and writes
>   no batch. **Verified:** full MySQL suite **379 passed / 1744 assertions** on `db_capstone_test`
>   (was 376/1724 at DK); Pint + `php -l` + `view:cache` clean. **No migration, schema, controller, route,
>   permission-key or `db_capstone` change.**
> - **▶ Session DK (DONE — Inventory row Action gained the read-only Deficit breakdown modal, flagged not yet client-confirmed; 2026-10-03):**
>   Owner-directed UI/test pass on `/inventory` (validated against PRD §2.7 / FR-08 / §11 / §16 and
>   ERD §3.8 / §3.10 / §3.12). **No migration, schema, controller, route, permission-key or
>   `db_capstone` change** — `InventoryController@index` is untouched.
>   **(1) ACTION column:** every row now carries a **Deficit** button (`btn-outline-info`) that opens the
>   shared **`#deficitModal`**. It is a read-only review control, so it is **not** gated on
>   `record_operations` and it also renders for **archived** rows; the existing Mortality/Regraft
>   operation links and their gating are unchanged. The modal is populated by JS from a per-row
>   `data-deficit` JSON payload built from the same `$summaries` values already rendered — **no new
>   query / no N+1**.
>   **(2) Modal content (read-only; owner ruled out any action inside):** warning banner with the exact
>   owner-mandated text *"Deficit definition not yet confirmed with the client — this is a working
>   calculation for internal review only."*; the working formula
>   `Deficit = max(0, Mortality − Regraft)` with the substituted numbers (e.g. `max(0, 30 − 10) = 20`);
>   then Batch / Parent Batch / Scion Variety / Total Grafted / Regraft / Mortality / Deficit /
>   Available. Footer is **Close only** — no action button and no Regraft shortcut. The formula itself
>   is unchanged and stays centralized in `Batch::regraftDeficit()` (RULE 6).
>   **(3) Tests:** `InventoryTest` +3 — exact notice text + payload values (`mortality:30`,
>   `regraft:10`, `deficit:20`, `available:60`); review available **without** `record_operations`;
>   review available for an **archived** batch. **Verified:** full MySQL suite
>   **376 passed / 1724 assertions** on `db_capstone_test` (was 373/1707 at DJ); Pint + `php -l` +
>   `view:cache` clean. **`db_capstone` untouched.**
>   **(4) Validation note (docs, do not edit unprompted):** the word "Deficit" appears in neither
>   client-approved v1.0 doc (`docs/CacaoTrack_PRD_UPDATED.md` / `CacaoTrack_ERD_UPDATED.md`) — hence
>   the explicit unconfirmed banner and **no PRD/ERD edit**. Their regraft/`Total_Grafted` wording is
>   superseded by the client-CONFIRMED cumulative rule (canonical v2.0 docs + `AGENTS.md`); mortality
>   stays an incrementing per-batch counter that never modifies the Regraft counter (ERD §3.10, rule 6).
>   **Working tree:** DK's view+test edits stack on the still-uncommitted DI+DJ files (see NEXT UP 0d).
> - **▶ Session DJ (DONE — Inventory page rebuilt into the per-batch template; row Action links to Mortality/Regraft; Record Monthly Inventory UI removed; 2026-10-03):**
>   Owner-directed UI/controller pass on `/inventory` (validated against PRD §2.7 / FR-08 / §11 and
>   ERD §3.8 / §3.12). **No migration, schema, route, permission-key or `db_capstone` change.**
>   **(1) Listing replaced** — the monthly-snapshot table is now **one row per batch** (parents + regraft
>   sub-batches, nested through `App\Support\BatchTree`; RULE 6) with columns **BATCH DATE | BATCH |
>   SCION VARIETY | TOTAL GRAFTED | REGRAFT | MORTALITY | DEFICIT | AVAILABLE | ACTION**.
>   `InventoryController@index` now queries `Batch` (eager `scionVariety` + `parent`,
>   `withSum('mortalityRecords as mortality_sum')` — no N+1) and passes `batches` (paginator), a
>   per-batch `summaries` map, `nodeMeta`, filters and the unchanged `totalGrafted` card
>   (`SUM(total_grafted − regraft)`). Filters: search/scion-variety → batch; **Status** → live
>   (`depleted` = `available_count = 0`); **Batch Month/Year** → `grafted_date`. Values all derive from
>   actual records (RULE 5/§16): `Deficit` = centralized `Batch::regraftDeficit()`, `Available` =
>   `available_count` (PRD §11), `Mortality` = effective recorded mortality.
>   **(2) `Batch::regraftDeficit()`** gained an optional precomputed-mortality arg (same pattern as
>   `survivingQuantity()`) so the page reuses the eager sum without duplicating the formula (RULE 6).
>   **(3) "Record Monthly Inventory" removed from the page** (owner: "I don't need it") — no per-row
>   snapshot button/modal/JS and no page-level modal. The FR-08 snapshot writer
>   (`InventoryController@store` + `StoreInventorySnapshotRequest` + route `inventory.snapshots.store`)
>   is **kept and still tested**, but is no longer reachable from the UI. **Flag:** the `inventories`
>   table is now UI-write-dead (written only by the seeder/backend route) — revisit if the client
>   expects to record monthly snapshots from the app.
>   **(4) ACTION column added back** (owner: "process Deficit/Regraft/Mortality"): each non-archived row
>   links into the existing operational flows **Mortality** and **Regraft** prefilled with the batch
>   (`/operational/mortality?batch_id=…`, `/operational/regraft?batch_id=…`); archived rows show
>   `Archived`; without `record_operations` the cell shows `—` (the target routes still enforce the
>   permission server-side — PRD §5/§13). No forms or validation duplicated.
>   **(5) Tests:** `InventoryTest` rewritten for the template (live values + deficit, batch/variety/
>   status/grafted month+year filters, action links, archived suppressed, permission hidden; the
>   snapshot writer tests remain); `NestedBatchesTest` inventory test now asserts batch tree nesting.
>   **Verified:** full MySQL suite **373 passed / 1707 assertions** on `db_capstone_test`; Pint +
>   `php -l` + `view:cache` clean. **`db_capstone` untouched.**
>   **Working tree: uncommitted (cumulative DI+DJ files — see NEXT UP 0d). Open doc divergence (do not
>   edit unprompted):** PRD §2.7 / FR-08 still describe stored monthly Inventory records, while the page
>   now shows live batch values (the snapshot writer is retained but not exposed).
> - **▶ Session DI (DONE — owner-approved re-wipe of live `db_capstone` operational data; GitHub published; Dashboard "Total Grafted" KPI; 2026-10-02):**
>   **(1) Operational data cleared (owner-approved destructive action, fresh sign-off).** Backup first
>   (verified, left untouched outside the repo):
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_wipe_20261002_201903.sql`
>   (**37,136 B**, clean `Dump completed on 2026-10-02 20:19:03` trailer, `mysqldump` exit 0). **Truncated
>   (FK-safe, `FOREIGN_KEY_CHECKS=0`, exit 0; `AUTO_INCREMENT` reset to 1):** activity_logs, inventories,
>   stock_out_details, stock_outs, regraft_records, mortality_records, batches, stock_ins — the same 8
>   tables as Session DD. **BEFORE:** batches 5, stock_ins 2, stock_outs 1, stock_out_details 1,
>   mortality_records 2, regraft_records 1, inventories 4, activity_logs 14. **AFTER:** all 8 = **0**,
>   `cacao:health` PASSED ("No batches to check.", exit 0). **Preserved (BEFORE == AFTER):** users 1
>   (`admin@cacaotrack.test`), scion_varieties 5, suppliers 3, clients 3, activity_tasks 4, task_completes 1,
>   migrations 53. No schema change; `db_capstone` otherwise untouched.
>   **(2) GitHub published (owner request).** Local branch `master` now has remote `origin` →
>   **https://github.com/tingalfilepenf-pixel/CacaoTrack** (public). The 18 staged-but-uncommitted files
>   (Sessions DE–DH: mortality cap, branding, password toggles, sidebar accordion, exports) were committed
>   as **`49351ce`** and pushed. `gh` CLI installed via winget and authenticated
>   (`tingalfilepenf-pixel`). `.env` is gitignored (no secrets); `vendor/`, `node_modules/`, `/capstone`
>   excluded. **Note:** the two sample exports `docs/cacao-operational-analytics-2026-09-25.csv` +
>   `docs/cacao-stock-ledger.csv` were previously meant to stay untracked but went in with `49351ce`.
>   **(3) Dashboard KPI swapped "Total Active Inventory" → "Total Grafted" (owner-directed, validated).**
>   New centralized **`AnalyticsMetrics::totalGrafted()`** = `SUM(total_grafted − regraft)` across ALL
>   batches (active + archived) — the same basis as the Inventory page's "Total Grafted" card and
>   `AnalyticsMetrics::survivalVsMortality()`, per the CONFIRMED cumulative rule (PRD §11 Total Grafted /
>   ERD §3.8; raw `SUM(total_grafted)` double-counts regrafted plants). `DashboardController` now passes
>   `totalGrafted`; the card (`resources/views/dashboard/index.blade.php`) reads "Total Grafted" with the
>   subtext "Live population (Grafted − Regraft) / Each plant counted once, all batches". Seeded value
>   **750** (raw would be 880). **Validated:** full MySQL suite **369 passed / 1686 assertions** on
>   `db_capstone_test`; Pint + `php -l` clean; `DashboardTest` + `SeededFamilyKpiTest` updated/extended.
>   **`db_capstone` untouched.**
>   **Open doc divergence (do not edit unprompted):** canonical **PRD §10** still lists *Total Active
>   Inventory* as a dashboard KPI (OD-10 open) and the **Analytics page still shows Total Active
>   Inventory** — the owner asked only for the Dashboard card. **Working tree:** the 5 dashboard files
>   (`DashboardController`, `AnalyticsMetrics`, `dashboard/index.blade.php`, `DashboardTest`,
>   `SeededFamilyKpiTest`) are **NOT committed/pushed yet**.
> - **▶ Session DH (DONE — Users/Profile show-password toggles + sidebar collapse dead-space fix; no schema, route, permission or data change; `db_capstone` untouched; 2026-10-02):**
>   Owner-directed UI pass, view/CSS/JS only. **(1) Show/hide password toggles (owner request; validated
>   against PRD FR-02 / §13 and ERD `Users`):** every password input on **Users Management** (Add User
>   modal: `password` + `password_confirmation`) and **Profile** (Change Password modal:
>   `current_password`, `password`, `password_confirmation`) is now wrapped in an
>   `.input-group.has-validation` with an eye `input-group-text.toggle-password`. The toggle flips the
>   input `type` and swaps `bi-eye`/`bi-eye-slash`; the `.invalid-feedback` stays inside the group so
>   server-side validation still renders. **Centralized (RULE 6):** one delegated click + keydown handler
>   in `resources/js/app.js` (`togglePasswordVisibility()`, applies to any `.toggle-password`), plus a
>   `.toggle-password { cursor:pointer }` / `:focus` rule in `resources/css/app.css`. Login already had
>   its own toggle (unchanged). **No security change — the input `type` toggle is client-side only;
>   passwords remain hashed and are never displayed (FR-02).** Files:
>   `resources/views/users/index.blade.php`, `resources/views/profile/index.blade.php`,
>   `resources/js/app.js`, `resources/css/app.css`.
>   **(2) Sidebar excessive-blank-space fix + accordion:** the CSS rule `#sidebar .nav { flex:1;
>   overflow-y:auto; padding-bottom:3rem; }` also matched the **nested submenu** `.nav` containers, so
>   every expanded submenu carried 3rem of dead space (the gaps under "Client" / "Gap Report"). Scoped it
>   to the top-level nav (`#sidebar > .nav`) and added an explicit reset
>   (`#sidebar .collapse:not(.show) { display:none; height:0; margin:0; padding:0; border:0 }`,
>   `#sidebar .collapse > .nav` padding reset) so a collapsed submenu occupies **zero** layout space.
>   Added `id="sidebarNav"` to the nav container and `data-bs-parent="#sidebarNav"` to the three
>   collapsible sections (`inventoryManageCollapse`, `tabularCollapse`, `historicalCollapse`), so opening
>   one **auto-collapses the others** (Bootstrap accordion). Labels, routes, icons and permissions
>   unchanged. Files: `resources/css/app.css`, `resources/views/layouts/sidebar.blade.php`.
>   **Verified:** `npm run build` re-run + `php artisan view:cache` clean; full MySQL suite
>   **369 passed / 1687 assertions** on `db_capstone_test`; **no migration / model / controller / request
>   / route / seeder / test / PRD-ERD change; `db_capstone` NOT touched.**
> - **▶ Session DG (DONE — UI-only branding/header tweaks; no schema, route, permission or data change; `db_capstone` untouched; 2026-10-02):**
>   Owner-directed cosmetic pass, all view/asset-only. **(1) Navbar** (`resources/views/layouts/navbar.blade.php`):
>   removed the right-side **user profile dropdown** (avatar + name + Profile/Logout); Profile and Logout
>   remain reachable from the sidebar **Account** section. **(2) Login page**
>   (`resources/views/auth/login.blade.php`): replaced the tree icon with the company logo
>   `<img src="{{ asset('images/cacao-logo.jpg') }}">` above the "CACAO TRACK" title (new `.login-logo` style,
>   140px). **(3) Sidebar header** (`resources/views/layouts/sidebar.blade.php`): replaced the tree icon +
>   "CACAO TRACK" text with the same logo (`height/width 40px; object-fit: contain; border-radius: 50%`).
>   **(4) Asset:** the logo was copied into the repo at **`public/images/cacao-logo.jpg`** (from
>   `C:\Users\Filepen\Downloads\RU-CHRIS-Cacao-agri.jpg`, 113,330 B); the `public/images/` folder was created.
>   **(5) Reverted experiments (no net change):** a temporary dashboard `page_actions` profile dropdown was
>   added then removed, and a temporary white sidebar-header background (`resources/css/app.css`) was applied
>   then reverted to the original `#212529`; `npm run build` was re-run (assets current). **Diagnostic note:**
>   a reported `/login` "error" was a **stopped `php artisan serve`** (nothing listening on :8000), not an app
>   error — `GET /login` returns **200** through the HTTP kernel. **Verified:** `php artisan view:cache` clean.
>   **No migration / model / controller / route / seeder / test / PRD-ERD change; `db_capstone` NOT touched.**
> - **▶ Session DF (DONE — Mortality Tracking dropdown exposes original vs Stock Out vs remaining; mortality validation capped by the live remaining balance; 2026-10-02):**
>   Owner-directed fix. **Root cause:** the Record Mortality batch dropdown called
>   `Batch::remainingMortalityCapacity()` = `(total_grafted − regraft) − mortality`, which ignores Stock Out,
>   so it showed a value that was neither the historical original nor the live remaining, and hid the original
>   quantity after a Stock Out. **Fix (one shared definition, RULE 6):**
>   **(1) `Batch::quantityBreakdown()`** (new, `app/Models/Batch.php`) returns the five requested values
>   separately — `original_quantity` (`total_grafted`, the cumulative establishment log; Stock Out never
>   reduces it), `stock_out_quantity` (Σ `stock_out_details.quantity`), `pullout_quantity` (the SAME recorded
>   transaction as Stock Out in CacaoTrack, PRD §2.8), `mortality_quantity` (effective recorded mortality) and
>   `remaining_quantity` (`survivingQuantity()` = `available_count − mortality`). `survivingQuantity()` gained
>   an optional precomputed-mortality arg so the breakdown reuses one mortality sum (no extra query).
>   **(2) `Batch::remainingMortalityCapacity()` now delegates to `survivingQuantity()`**, so the mortality gate
>   subtracts Stock Out/Pull-Out exactly like the Stock Out and Regraft caps.
>   **(3) `MortalityController@index`** builds a per-batch breakdown map and passes it to the view;
>   **`@store`** validates `quantity ≤ remaining quantity` (message now "…remaining quantity of {n}…").
>   **(4) Dropdown text** (`resources/views/operational/mortality/index.blade.php`) now reads e.g.
>   `B001 (Batangas) — Original: 200 | Stock Out: 50 | Remaining: 150`; the in-modal helper notes now say
>   "remaining quantity".
>   **(5) `CorrectRecord::mortality()`** re-validates a correction as `available_count − other mortality`
>   (Stock Out now excluded), matching a new entry.
>   **Interpretation note:** the request's rule 4 said "validate against the remaining quantity" but then "not
>   greater than 200" (the original); the stricter **remaining = 150** was implemented, which also guarantees
>   ≤200 (sold 50 + died 150 = 200 original). **Supersedes the previously-"CONFIRMED" mortality cap**
>   `max(0, (total_grafted − regraft) − mortality)` recorded in `AGENTS.md` (CONFIRMED section) / the Phase 7B
>   notes — that doc line is now stale and should be reconciled (owner-directed change; mortality still does
>   **not** mutate `available_count` or the regraft counter).
>   **No historical Stock Out/grafting/mortality records, schema, routes or permission keys were touched; no
>   migration.** **Verified:** full MySQL suite **369 passed / 1687 assertions** on `db_capstone_test` (was
>   367/1671; +2 `MortalityTest` cases: breakdown/display + cap-after-Stock-Out); `php -l` clean; Pint clean;
>   `php artisan view:cache` clean. **`db_capstone` NOT touched.**
> - **▶ Session DE (DONE — removed the inline quick-add links from the Stock In / Stock Out modal forms; view-only, no schema/data change; 2026-10-01):**
>   Owner-requested UI tidy-up. **Stock In** (`resources/views/inventory/stock-in/index.blade.php`):
>   removed the **`+ Add Supplier`** link beside the form's Supplier label and its now-dead
>   `switchToSupplierBtn` JS handler; the shared Supplier `<select>` still renders and is still required.
>   **Stock Out** (`resources/views/inventory/stock-out/index.blade.php`): removed the **`+ Add Client`**
>   link beside the form's Client label and its now-dead `switchToClientBtn` JS handler; the Client
>   `<select>` is unchanged and still required. The page-level **New Supplier** / **New Client** buttons in
>   `@section('page_actions')` and their modals (`supplierModal` / `clientModal`) are **kept** — only the
>   duplicate in-form links were removed. Compliant with PRD FR-09/FR-11/FR-12 and ERD §3.7/§3.14/§3.15
>   (Supplier/Client remain selectable; no field, route, validation or schema touched — RULE 4, no new
>   table). **Verified:** `php artisan view:cache` clean; no remaining `switchToSupplierBtn` /
>   `switchToClientBtn` references. No test files changed; full suite not re-run this session (view-only
>   change). `db_capstone` untouched.
> - **▶ Session DD (DONE — owner-approved one-time clear of live `db_capstone` operational data; users + master data preserved; docs-only commit; 2026-10-01):**
>   **Backup first** (verified, left untouched outside the repo):
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_cleared_20261001_235027.sql`
>   (**36,106 B**, 23 `CREATE TABLE`, 15 `INSERT INTO`, `--single-transaction --routines --triggers --events`,
>   clean `Dump completed on 2026-10-01 23:50:27` trailer, `mysqldump` exit 0). **The full seeded baseline is
>   recoverable from this file.** **Truncated (FK-safe, `FOREIGN_KEY_CHECKS=0`, exit 0; `AUTO_INCREMENT`
>   reset to 1):** activity_logs, inventories, stock_out_details, stock_outs, regraft_records,
>   mortality_records, batches, stock_ins. (The request named `replanting_records`; the live table is
>   `regraft_records` after the Session CR rename.) **Preserved (BEFORE == AFTER):** users 1,
>   scion_varieties 5, suppliers 3, clients 3; also untouched: activity_tasks, task_completes, analytics,
>   migrations, framework tables. **BEFORE:** activity_logs 11 / inventories 5 / stock_out_details 1 /
>   stock_outs 1 / regraft_records 2 / mortality_records 4 / batches 5 / stock_ins 4; `cacao:health` PASSED
>   (5 batches). **AFTER:** the 8 tables all **0**; preserved counts unchanged; `migrate:status`
>   **53 Ran / 0 Pending**; `cacao:health` PASSED ("No batches to check.", exit 0). **End-to-end read-only as
>   the preserved admin (`admin@cacaotrack.test`, no new user):** Dashboard, Batches, Stock In, Stock Out,
>   Inventory and Stock Ledger all **200** with proper empty states. **No scenario reseeded** — the owner is
>   entering every number manually. `db_capstone` now has 0 batches; restore the seeded baseline from the
>   backup if needed (fresh sign-off).
> - **▶ Session DC (DONE — Stock Ledger reworked to a true chronological ledger; `Batch::survivingQuantity()` corrected; docs-only commit; 2026-10-01):**
>   **(1) `Batch::survivingQuantity()` bug fixed — this is the real Stock Out/Regraft validation gate, not a
>   display value.** It previously returned
>   `max(0, min(available_count, (total_grafted − regraft) − effective mortality))` — a `min` of two one-sided
>   exclusions, so when a batch carried BOTH mortality and a Stock Out it subtracted only the larger and counted
>   the dead plants as still releasable. Confirmed on the seeded **B001-R1** (established 100, regraft-out 30,
>   mortality 5, stock-out 20): old value **50**, corrected value **45**. It is now
>   `max(0, available_count − effectiveMortalityQuantity())` — algebraically
>   `total_grafted − regraft − mortality − stock_out`, because `available_count` already nets out regraft-out and
>   Stock Out. Call sites: `StockOutController` (per-line + per-batch gate), `App\Actions\RegraftBatch` (cap) and
>   `CorrectRecord::stockOut()` (stock-out re-validation). The bug had real-world impact: it could have approved
>   selling already-dead stock. **This explicitly supersedes the earlier instruction in this session's history
>   not to modify `survivingQuantity()` (the "PART 3" instruction), which predated discovering the bug.**
>   **(2) Stock Ledger Pullout is now per-event.** Each Stock Out detail line posts its own dated row
>   (`stock_outs.release_date`; description `Pullout {batch} → {client} ({qty} {unit})`), reverting the
>   cumulative-stamp design shipped earlier this session (commit `11f674b`) — matching Mortality/Regraft and the
>   client's own spreadsheet. **(3) New "Remaining Available" running-balance column** (on-screen, PDF and CSV)
>   tracked per batch in true chronological order:
>   `total_grafted − regraft − mortality − pullout to date`, gathered independently from the real records (never
>   read back from a value that already nets outflows); every batch's final value is reconciled to
>   `Batch::survivingQuantity()` and a mismatch fails loudly
>   (`AnalyticsMetrics::assertLedgerRunningBalanceReconciles()` throws `RuntimeException`).
>   **(4) Footnote/help text corrected** (view/PDF/CSV) to describe the running-balance behaviour.
>   **KPIs:** cross-batch totals still **750** (Stock Ledger summary / Gap expected / Inventory snapshot); Crude
>   Mortality Rate **7.33%** (55/750); Total Active Inventory **730**; Regraft Deficit **25**; ledger
>   **Ready to Plant** summary **680 → 675** (B001-R1 50 → 45 only); ledger **Pullout** total unchanged **20**.
>   **Verified:** full MySQL suite **367 passed / 1671 assertions** on `db_capstone_test`; Pint + `php -l` +
>   `view:cache` clean; B001-R1 sequence **100 → 70 → 65 → 45**. **Code/tests commit `839ecc2`; this is the
>   docs-only commit.** `db_capstone` untouched (read-only verification only).
> - **▶ Session DB (DONE — one explicitly owner-approved destructive reseed of live `db_capstone`; 2026-10-01):**
>   **Backup first** (verified, left untouched outside the git repo):
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_reseed_20261001_213846.sql`
>   (**37,202 B / 23 `CREATE TABLE` / 15 `INSERT INTO`**, `--single-transaction --routines --triggers
>   --events`, includes `CREATE DATABASE`/`USE db_capstone`, SHA-256
>   `244875CA5818E2695BF1759696FF7E28C1E63D8E055EDA63AB77C84F037E2FB9`, clean
>   `Dump completed on 2026-10-01 21:38:46` trailer). **Before:** batches **6** (5-batch seed + a
>   UI-created **B002-R1**), stock_outs 2, stock_out_details 2, mortality_records 4, regraft_records 3,
>   inventories 5, activity_logs 13, sessions 3; `migrate:status` 53 Ran / **0 Pending**; `cacao:health`
>   **PASSED (exit 0, 6 batches)**. **Run:** `migrate:fresh --seed --force` against `db_capstone`
>   (all 53 migrations, exit 0). **After:** batches **5**, stock_ins 4, stock_outs 1, stock_out_details 1,
>   mortality_records 4, regraft_records 2, inventories 5, scion_varieties 5, suppliers 3, clients 3,
>   users 1, activity_logs 11, analytics 0, sessions 0 (`1` after the read-only HTTP smoke);
>   `migrate:status` 53 Ran / **0 Pending**; `cacao:health` **PASSED (exit 0, 5 batches, all 5 rules)**.
>   **Seeded family:** B001 Batangas 500/400/100 (mortality 25, familyMortality 30, surviving 375) ·
>   B001-R1 Toledo 100/50/30 (parent B001, mortality 5, Stock Out 20, surviving 50) · B001-R1-R1 Criollo
>   30/30/0 (parent B001-R1, surviving 30) · B002 Trinitario 150/150/0 (mortality 15, surviving 135) ·
>   B003 Forastero 100/100/0 (mortality 10, surviving 90). **KPIs:** raw `Σtotal_grafted` 880; live
>   population `Σ(total_grafted − regraft)` **750**; Total Active Inventory **730**; Crude Mortality Rate
>   **7.33%** (55/750); Regraft Deficit **25**; the **three cross-batch totals all read 750**; Stock
>   Ledger **Ready to Plant** totals **680** (per batch 375/50/30/135/90) and cumulative **Pullout**
>   totals **20** (B001-R1). **End-to-end read-only via the HTTP kernel as the seeded administrator:**
>   all **20 sidebar GET pages 200**; render checks pass (Stock Ledger shows Ready to Plant + Pullout +
>   Family Mortality (incl. sub-batches) + the Ready-to-Plant/Pullout footnote + the "counts each plant
>   once" note; the Batches detail modal shows Family Mortality; the Gap Report note is present).
>   **This clears the CZ live-drift note** (6 batches incl. B002-R1 / 3 regraft records / 2 Stock Out
>   details). Docs-only commit follows; no code changed.
> - **▶ NEXT UP (start here next session — manual flow-testing / vibecoding):**
>   - **(0) Live `db_capstone` holds owner-entered operational data (re-observed read-only in Sessions
>     DO, DP, DQ, DV, DZ and EA, 2026-10-03…12) — do NOT reseed or truncate without fresh owner sign-off.**
>     It was re-cleared in Session DI (2026-10-02) and the owner has since hand-entered the flows:
>     **batches 5** — B001, B001-R1, B001-R1-R1, B001-R2, B002 — with stock_ins 2, mortality_records 2,
>     regraft_records 3, **stock_outs 1, stock_out_details 1**, inventories 0, activity_logs 13;
>     master/reference data is intact and must stay: **users 1 · scion_varieties 2 (Batangas, Toledo) ·
>     suppliers 3 · clients 3**; also untouched: activity_tasks 4, task_completes 1, analytics, migrations,
>     framework tables. **Session DV (2026-10-12)** deleted the 3 archived, unreferenced scion varieties
>     (Trinitario, Toledo, Forastero) through the new guarded Delete after a fresh backup (see 0a); every
>     table now has **0** archived rows. Live Inventory cards (Session DV; the **Regraft Deficit card was
>     removed in Session DY**): Total Mortality **123**, Total Regrafted **270**, Total Active Inventory
>     **813**, Total Batches **5**. Dashboard/Analytics live KPI cards (Session DZ): **Total Grafted
>     853**, **Total Active Inventory 813**; `cacao:health` **PASSED** (5 batches, all 5 rules). A
>     restore/reseed is **not** needed and needs fresh owner sign-off anyway; the seeded baseline lives
>     only in the backups.
>   - **(0a) Quick state check (read-only).** `php artisan cacao:health` → currently
>     `Cacao health check passed. 5 batch(es) satisfy all invariants.` (exit 0; all 5 rules hold on the
>     owner-entered data). With no batches it prints `No batches to check.` To inspect counts:
>     `mysql -u root -h 127.0.0.1 db_capstone -t -e "SELECT 'batches' t, COUNT(*) c FROM batches;"`.
>     Backups if a restore is ever approved (keep untouched): the Session DV pre-cleanup dump
>     `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_scion_delete_20261012_175232.sql`
>     (34,928 B), the Session DI wiper `...db_capstone_pre_wipe_20261002_201903.sql` (37,136 B)
>     and the Session DD backup `...db_capstone_pre_cleared_20261001_235027.sql` (36,106 B).
>   - **(0b) Manual entry order to exercise the flows end-to-end:** **Sidebar note (Session EC):** the
>     **Mortality Tracking**, **Regraft** and **Batches** links are hidden from the Operational sidebar,
>     but the functions remain — open them directly at `/operational/mortality`, `/operational/regraft`,
>     `/operational/batches` (or via the Inventory row actions). (i) **Batches** — create a root batch;
>     **as of Session DQ the form no longer asks for Batch Code or Grafted Date** — the server assigns the
>     next free root code (first batch → **B001**, then B002, …) and today's date, and the modal previews
>     the code; **the same Add Batch modal is also on `/inventory`** (Add Batch page action,
>     `record_operations` users only — handy while hand-entering the flows); (ii) **Mortality Tracking**; (iii) **Regraft**
>     (cap = `Batch::survivingQuantity()`); (iv) **Stock In** — Seedlings tab, then the Scion tab;
>     (v) **Stock Out** (gate = `survivingQuantity()`; unit is **pots** only); (vi) **Inventory** —
>     check the **four** filter-aware summary cards (**Total Mortality** / **Total Regrafted** /
>     **Total Active Inventory** (active batches only) / **Total Batches** — Session DP; the
>     **Regraft Deficit card was removed in Session DY**; they follow the page filters), then the live
>     per-batch template
>     (**BATCH DETAILS** (code + grafted date, no `Date:` label) /
>     **SCION VARIETY** / **TOTAL GRAFTED** (qty + grafted date) / **REGRAFT** (qty + latest regraft
>     date) / MORTALITY / AVAILABLE / **STATUS** — the derived lifecycle badge **Growing**
>     (<21 days) / **Ready** (21 days reached) / **Completed** (no available balance) / **Cancelled/Void**
>     (inactive or archived); the Status filter filters by those same values — Sessions DO/DN/DJ;
>     PRD §2.7/FR-08, ERD §3.12). Nested regraft rows carry a **Regrafted** badge and parents have the
>     same collapsible sub-batch chevron toggle as the Batches page (Session EB). The row action is
>     `[View] [More ▾]` (Session DO): **View** opens the read-only Batch Details modal — **since
>     Session DT it also lists the batch's recorded Mortality Causes** (cause + quantity, from the real
>     `mortality_records`; "No mortality recorded for this batch." when empty); **More** groups
>     **Regraft** and **Record Mortality** (the same forms as the operational pages, as modals prefilled
>     with that batch — Session DM) — **Session DY removed the Record Deficit action/modal and hides
>     More for archived rows / users without `record_operations`**. **There is no "Record Monthly
>     Inventory" action on the page (removed in Session DJ).** (vii) **Delete (Session DV)**
>     — **the Archived tabs are gone** on Checklist / Batches / Scion Variety and the row trash button
>     permanently deletes the record. It is refused (error toast; the row button is disabled and its
>     tooltip lists the blockers) for a task with completion history, a batch with any
>     mortality/regraft/stock-out/inventory/activity/sub-batch records, and a scion variety referenced by a
>     batch / stock receipt / stock-out line. The live batches all have records, so Delete is refused for
>     them — to see a successful delete, create a fresh batch (the Add Batch modal previews the auto code)
>     and delete it before recording anything against it. A successful delete writes Activity History
>     (`task_deleted` / `batch_deleted` / `scion_variety_deleted`). Re-run `cacao:health` and
>     glance at the Dashboard / Stock Ledger after each step. (viii) **Daily Checklist → History
>     (Session DW)** — complete a task (or leave some open) on `/operational/checklist`, then open the
>     **History** tab: the day appears as a record (Completed when every task is done that day, otherwise
>     Pending) with the real staff, verifier and remarks; use Search/From-To/Status (auto-apply) and the
>     record's **Print** / **View Full Details** actions. History is read-only — nothing can be edited.
>     (ix) **Stock Ledger (Sessions DX/EA)** — the page renders ascending; the **Remaining Available**
>     column is gone from the page, PDF and CSV, and the CSV opens in the client's monthly-block layout
>     (month title → grouped section + per-variety headers → events → month TOTAL → GRAND TOTAL →
>     footnote → nested Batch summary). The running balance is still reconciled internally as a guard.
>     (x) **Inventory sub-batch toggle (Session EB)** — on `/inventory`, use the chevron beside a parent
>     batch code (e.g. **B001**) to collapse/expand its whole regraft subtree; childless nested rows keep
>     the `↳` indent. The same control and behaviour now live on `/operational/batches`.
>   - **(0c) Code/test baseline (code-side only):** full MySQL suite
>     `$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`
>     → **428 passed / 2213 assertions** (Session EE; was 408/2001 at EB, 407/1994 at EA, 407/1984 at DZ,
>     407/1977 at DY, 407/1986 at DX, 406/1960 at DW, 400/1900 at DV, 401/1913 at DU, 399/1909 at DT,
>     398/1898 at DS, 395/1873 at DR, 394/1867 at DQ, 392/1855 at DP, 385/1797 at DN, 384/1789 at DM,
>     379/1744 at DL, 376/1724 at DK, 373/1707 at DJ, 369/1686 at DI). Pint + `php -l` + `view:cache`
>     clean. `phpunit.xml` now **forces** MySQL `db_capstone_test` and `tests/TestCase.php` refuses
>     `RefreshDatabase` when the DB is not `db_capstone_test`. Run tests **only** against
>     `db_capstone_test`.
>   - **(0d) Repo / GitHub (updated Session EE).** Remote `origin` =
>     **https://github.com/tingalfilepenf-pixel/CacaoTrack** (public, branch `master`); `gh` CLI installed.
>     **Session EE added six commits on top of `49351ce`:** `22a0770` (permission catalog + routes),
>     `05f785d` (stock forms + User Management), `cd3912c` (checklist UI + errors + sidebar + Dashboard),
>     `3a522e3` (ActivityLog cleanup), `6f68b92` (tests) and the docs commit for this handoff/PRD/AGENTS.
>     The older cumulative **DI…ED** changes in files not touched by Session EE are still uncommitted;
>     the list below records them:
>     Session DW — new `app/Support/ChecklistHistory.php` (record/detail/status derivation + shared day
>     task list, RULE 6), `DailyChecklistController` (history filters/records/selected record; `exportPdf`
>     now reads the shared definition), `operational/checklist/index.blade.php` (Today/History tabs, filter
>     card, record list + read-only detail + full-details modal), `resources/css/app.css` (scoped tab/record
>     styles), `DailyChecklistTest` (+6), `session.handoff.md`; Session DV — `ActivityTask`/`Batch`/`ScionVariety` (guarded delete rules; `ScionVariety::stockIns()`),
>     `ActivityLog` (`scion_variety_deleted`), `DailyChecklistController`/`BatchController`/`ScionVarietyController`
>     (`archive`/`restore` → guarded `destroy`), `routes/web.php` (2 new DELETE routes, 6 archive/restore
>     routes removed), the 3 page views + `operational/batches/partials/batch-row.blade.php`,
>     `DailyChecklistTest`/`BatchManagementTest`/`SeededFamilyKpiTest` updated,
>     new `ScionVarietyManagementTest`, `session.handoff.md`, `AGENTS.md`; Session DU —
>     `app/Support/BatchTree.php` (opt-in `$descending` flag), `app/Http/Controllers/InventoryController.php`
>     (`descending: true`), `BatchTreeTest` (+1), `InventoryTest` (+1), `session.handoff.md`; Session DT —
>     `app/Models/MortalityRecord.php` (`causeBreakdownByBatch()`),
>     `app/Http/Controllers/InventoryController.php` (`mortality_causes` in `summaries`),
>     `inventory/index.blade.php` (View modal Mortality Causes section + JS),
>     `InventoryTest` (+1, 1 updated), `session.handoff.md`; Session DS —
>     `resources/js/app.js` (centralized `form[data-auto-filter]` behavior), `inventory/index.blade.php`
>     (inline auto-filter JS removed, `data-auto-filter` added),
>     `inventory/stock-in/index.blade.php` + `inventory/stock-out/index.blade.php` +
>     `analytics/stock-ledger.blade.php` (same filter card; Apply removed, Clear Filters kept) + rebuilt
>     `public/build` assets, `StockInTest`/`StockOutTest`/`AnalyticsTest` (+1 each), `InventoryTest`
>     (assert `data-auto-filter`); Session DR —
>     `resources/views/inventory/index.blade.php` (filter form auto-applies; Apply button removed, Clear
>     Filters kept), `InventoryTest` (+1); Session DI — `DashboardController`,
>     `AnalyticsMetrics`, `dashboard/index.blade.php`, `DashboardTest`, `SeededFamilyKpiTest`; Session DJ
>     — `InventoryController`, `Batch` (`regraftDeficit()` optional arg),
>     `inventory/index.blade.php`, `InventoryTest`, `NestedBatchesTest`; Session DK
>     — `inventory/index.blade.php` (Deficit review button + `#deficitModal`), `InventoryTest` (+3);
>     Session DL — `inventory/index.blade.php` (Add Batch page action + `#addBatchModal`, gated on
>     `record_operations`), `InventoryTest` (+3, replacing the reverted details tests);
>     Session DM — `InventoryController` (modal dropdown data + per-row `regraftable`),
>     `inventory/index.blade.php` (row Mortality/Regraft buttons open `#recordMortalityModal` /
>     `#recordRegraftModal`, same forms/routes as the operational pages), `InventoryTest` (+5);
>     Session DN — `InventoryController` (eager `latest_regraft_date` aggregate),
>     `inventory/index.blade.php` (BATCH DETAILS / TOTAL GRAFTED / REGRAFT merged columns with inline
>     dates; nested-row badge renamed **Regrafted**), `InventoryTest` (+1, header assertion updated),
>     `NestedBatchesTest` (badge assertion → `Regrafted`);
>     Session DO — `Batch` (derived `LIFECYCLE_*` constants + `lifecycleStatus()` / `lifecycleLabels()` /
>     `cycleReadyDate()` / `scopeWhereLifecycleStatus()`), `InventoryController` (lifecycle status filter +
>     summary fields), `inventory/index.blade.php` (no `Date:` labels, STATUS column + filter, View modal,
>     `[View] [More ▾]` action), `resources/css/app.css` (`.badge-lifecycle-completed`) + rebuilt assets,
>     `InventoryTest` (updated + lifecycle/View/More tests);
>     Session DP — `InventoryController` (5 filter-aware card values, `totalGrafted` view var dropped),
>     `inventory/index.blade.php` (5-card summary row), `InventoryTest` (+2, page test updated),
>     `SeededFamilyKpiTest` (page `totalGrafted` assertion removed, +1 seeded card test);
>     Session DQ — `Batch` (`nextBatchCode()` + `storeRules()`), `BatchController` (store auto-assign +
>     `nextBatchCode` view data), `InventoryController` (view data), `StoreBatchRequest` (validates
>     `storeRules()`; submitted `batch_code`/`grafted_date` ignored), both Add Batch modals
>     (`inventory/index.blade.php`, `operational/batches/index.blade.php` — read-only auto fields),
>     `InventoryTest` (+1, 2 updated), `BatchManagementTest` (+2, 2 replaced);
>     Session DX — `StockLedgerRunningBalanceTest` (+1: ascending-order regression test);
>     Session DY — `inventory/index.blade.php` (DEFICIT column, Regraft Deficit card, Record Deficit
>     action, Deficit modal + View-modal line removed), `dashboard/index.blade.php` (Regraft Deficit
>     card removed), `analytics/index.blade.php` (KPI + chart + print-modal copy),
>     `InventoryController`/`DashboardController`/`AnalyticsController` (view data/exports),
>     `OperationalAnalyticsPdf` (series removed), `InventoryTest`/`DashboardTest`/`AnalyticsTest`/
>     `SeededFamilyKpiTest` updated, `AGENTS.md`;
>     Session DZ — `DashboardController`/`AnalyticsController` (new KPI view data + PDF/CSV KPI list),
>     `dashboard/index.blade.php` + `analytics/index.blade.php` (new cards),
>     `OperationalAnalyticsPdf` (docblock), `DashboardTest`/`AnalyticsTest` (+7 locks), `AGENTS.md`;
>     Session EA — `AnalyticsController` (CSV export rebuilt as the client's monthly-block ledger +
>     `ledgerCsvSectionHeaders()`/`ledgerCsvVarietyHeaders()`; Remaining Available removed),
>     `StockLedgerPdf` (column removed, section groups widened to 39.2mm, footnote updated),
>     `AnalyticsMetrics` (docblock — running balance kept as internal reconciliation guard),
>     `analytics/stock-ledger.blade.php` (column + copy removed),
>     `StockLedgerNestingTest` (page/CSV/PDF locks incl. the new layout),
>     `StockLedgerRunningBalanceTest` (docblock), `AGENTS.md`;
>     Session EB — `InventoryController` (`nodeMeta` gained `ancestors` + `has_children`),
>     `inventory/index.blade.php` (Batches-style collapsible sub-batch chevron + `child-of-*` row classes),
>     `InventoryTest` (+1), `NestedBatchesTest` (nodeMeta locks), `AGENTS.md`;
>     Session EC — `analytics/index.blade.php` (Total Grafted → green, Total Active Inventory → blue;
>     colors swapped), `layouts/sidebar.blade.php` (Mortality Tracking / Regraft / Batches links
>     removed; routes/pages retained), `AGENTS.md`;
>     Session ED — `session.handoff.md` only (Phase 0 read-only audit; no code/database change);
>     plus `session.handoff.md`. **No commit/push was made this session (not requested).**
>   - **(0e) Phase 0 inspection (Session ED) — DECIDE BEFORE CODING; do not assume a role-based design yet.**
>     The audit found no proposal document in the repo; the current model is per-user JSON. Before changing
>     permissions/UI, get explicit owner/client decisions on: (a) keep per-user JSON (role = label) vs
>     reintroduce a normalized roles/role_permissions model (AGENTS/ERD currently forbid the latter without
>     an approved revision); (b) Administrator full implicit access vs permission-only; (c) checklist
>     verification workflow (who verifies, with what role/permission, self-verification allowed?);
>     (d) task add/edit authorization (Staff vs Manager/Admin); (e) Supplier/Client creation permission
>     (`manage_stock` vs `manage_inventory`); (f) analytics exports (`view_analytics` vs
>     `generate_reports`); (g) whether `/users` and Dashboard remain readable by every authenticated user.
>     **Recommended order next session:** (1) test-safety first — add `.env.testing`/MySQL `db_capstone_test`
>     config + a `TestCase` DB-name guard + route-permission negative tests; (2) permission data
>     normalization/backfill for `correct_records` (and decide admin behavior); (3) route/UI permission
>     alignment (Dashboard links, Generate Report button, stock quick-add); (4) checklist verification
>     route/UI/fields; (5) branded `errors/403|404|500` and role/permission UI if approved; (6) dead
>     activity-type cleanup + AGENTS catalog correction (8 keys) + full MySQL suite. **Do not run
>     `migrate:fresh`/seeders against `db_capstone`; tests only against `db_capstone_test`.**
>     The full Phase-0 findings live in the Session ED entry at the top of this file; no code was changed.
>   - **(1) Remaining Session CS Tier-1 — safe, code-only, no client decision needed:**
>     (i) log Inventory snapshots to `Activity_Log`; (ii) fix the "Total **Active** Inventory
>     includes archived" label — **the Dashboard no longer shows it (Session DI), the Analytics page
>     still does**; (iii) point `phpunit.xml` at MySQL `db_capstone_test`.
>   - **(2) Tier-2 — needs an owner/client decision, do NOT implement unprompted:** whether mortality
>     should decrement `available_count`; Active-vs-archived basis; the Regraft Deficit formula (still
>     shown only on `/operational/regraft` — Sessions DY removed it from Inventory/Dashboard/Analytics);
>     the date-filtered rate definition. (The mortality **cap** was owner-directed in Session DF — a new
>     entry is now validated against the live remaining `survivingQuantity()`; mortality still does not
>     mutate `available_count`.)
>   - **(3) Tier-3 — needs an approved ERD revision:** DB unique index for one monthly Inventory
>     snapshot per batch; Stock In↔Batch traceability (`OD-8`); scion freshness (`OD-13`).
>   - **(4) Client-gated features — do not build unprompted:** scion-receipt Edit/Archive/Details;
>     scion-receipt traceability (graft/regraft → a scion-inclusive `Stock_In` row, no balance
>     deduction); scion freshness/viability rule. See `AGENTS.md` §"P6 deferred items".
>   - **(5) Docs/exports note (updated Session DI):** the two sample exports
>     (`docs/cacao-operational-analytics-2026-09-25.csv`, `docs/cacao-stock-ledger.csv`) were previously
>     meant to stay untracked, but were part of the 18 staged files committed as `49351ce` (owner chose
>     "commit them first" when publishing). They are now tracked — remove in a later commit if they should
>     not ship.
>   - **Do NOT re-investigate (already implemented + committed):** (1) the Stock Ledger's "Ready to
>     Plant" figure uses `survivingQuantity()` (mortality + pull-out excluded, commit `839ecc2`) and
>     Pullout posts **one dated row per Stock Out detail** (Session DC; the earlier cumulative-stamp
>     design `11f674b` was reverted), with the 'Ready to Plant' label kept; (2) family-level
>     mortality rollup — `Batch::familyMortality()` plus the "Family Mortality (incl. sub-batches)"
>     column/tile, ADDITIONAL to the unchanged per-batch figure (Crude Mortality Rate numerator and
>     Gap total unchanged) — commit `d43c2d5`; (3) the Mortality Tracking dropdown shows
>     **Original / Stock Out / Remaining** from `Batch::quantityBreakdown()` and the mortality cap is the
>     live remaining balance — Session DF (see the top banner); (4) the **Dashboard KPI is now
>     "Total Grafted"** = `AnalyticsMetrics::totalGrafted()` = `SUM(total_grafted − regraft)` across all
>     batches (Session DI) — do not "fix" it back to Total Active Inventory on the Dashboard;
>     (5) the **Inventory page is now the per-batch live template** (Session DJ; columns merged in
>     Session DN) — **BATCH DETAILS** (code + grafted date stacked) / **SCION VARIETY** /
>     **TOTAL GRAFTED** (qty + grafted date) / **REGRAFT** (qty + latest regraft date) / MORTALITY /
>     AVAILABLE / **STATUS**, nested regraft rows carrying a **Regrafted** badge, with a row
>     **Action** that opens the Mortality/Regraft forms in modals (Session DM — see item 8).
>     **There is deliberately NO "Record Monthly Inventory" action on the page** (owner-removed) — do not
>     re-add the snapshot modal/button unless the owner asks. The snapshot writer (`store()` + route) is
>     still present and tested. (6) the **Regraft Deficit UI was removed from Inventory/Dashboard/
>     Analytics** (Session DY, owner-directed) — the DEFICIT column, summary card, row action, both
>     modals and the CSV/PDF sections are deliberately gone; do **not** re-add them there. The figure
>     survives only on `/operational/regraft` through the centralized `Batch::regraftDeficit()` until the
>     client confirms the definition (Tier-2). (7) the **Inventory page now also hosts the Add Batch
>     modal** (Session DL) — the same `#addBatchModal` / `StoreBatchRequest` /
>     `operational.batches.manage.store` flow as `/operational/batches`, gated on `record_operations`
>     (button **and** markup). It is a deliberate shortcut, not a duplicate form: do not fork the form,
>     the validation or the controller. (8) the **Inventory row Mortality/Regraft buttons now open modals
>     with the same forms** (Session DM) — `#recordMortalityModal` / `#recordRegraftModal` post to the
>     operational store routes and reuse `StoreMortalityRecordRequest` / `StoreRegraftRecordRequest`
>     untouched; do not re-add `/operational/mortality?batch_id=…` / `/operational/regraft?batch_id=…`
>     links, do not fork the forms/validation/controllers, and keep the row Regraft button limited to
>     batches whose `survivingQuantity() > 0`. (9) the **Inventory columns are merged and the row badge
>     reads "Regrafted"** (Session DN) — BATCH DETAILS stacks the code over its grafted date;
>     TOTAL GRAFTED stacks the quantity over `grafted_date` (same as Batch Details, owner-chosen);
>     REGRAFT stacks the counter over the latest `regraft_date` from `regraftRecordsAsParent`
>     (`withMax` aggregate; `—` when none); do not split the dates back into separate columns, and do
>     not "fix" the badge back to Sub-batch. The Batches page badge and the other in-page "sub-batch"
>     copy (footnote, Regraft modal) still say Sub-batch by design. (10) the **Inventory Status column is
>     derived, not stored** (Session DO) — `Batch::lifecycleStatus()` maps invalidated (stored `inactive`
>     or archived) → **Cancelled/Void**, `available_count = 0` → **Completed**, `grafted_date + 21 days`
>     reached → **Ready**, else **Growing**; the Status filter uses `scopeWhereLifecycleStatus()` (same
>     precedence). Do not write these values into `batches.status` (its enum stays `active`/`inactive`) and
>     do not split the definition per view. (11) the **Inventory row action is `[View] [More ▾]`**
>     (Session DO; Deficit removed in DY) — View is the read-only `#viewBatchModal` (available to all
>     viewers, archived included), and More groups **Regraft / Record Mortality** (the dropdown itself is
>     hidden when there is no action to offer: archived rows / users without `record_operations`). Keep
>     the forms/routes shared with the operational pages and do not re-add the standalone
>     Mortality/Regraft buttons.
>     (12) the **Inventory summary cards are the four owner-directed filter-aware cards** (Session DP;
>     Regraft Deficit removed in DY) — Total Mortality / Total Regrafted / Total Active Inventory (active
>     batches only) / Total Batches; they aggregate the same live per-batch values as the rows. Do not
>     re-add the Total Grafted or Regraft Deficit cards, and do not fork the card definitions per view.
>     (13) the **Checklist / Batches / Scion Variety pages use the guarded Delete, not Archive** (Session DV) —
>     the Archived tabs, archive/restore routes (`operational.checklist.tasks.archive|restore`,
>     `operational.batches.archive|restore`, `operational.scion-varieties.archive|restore`) and their modals
>     are deliberately gone; keep the guards centralized (`ActivityTask::canBeDeleted()`,
>     `Batch::deletionBlockers()`, `ScionVariety::deletionBlockers()`) and keep refusals non-destructive —
>     do not re-add the tabs/routes, do not turn the guards into a cascade, and do not delete the
>     `Archived_At` columns (still in the ERD). Supplier/Client keep their archive/restore by owner choice.
>     (14) the **Dashboard/Analytics KPI cards** (Session DZ) and the **Stock Ledger page/CSV layout**
>     (Sessions DX/EA) are current: Dashboard and Analytics both show Total Grafted / Total Active
>     Inventory / Crude Mortality Rate; the Stock Ledger renders ascending, has **no Remaining Available
>     column** (page/PDF/CSV), and the CSV uses the client's monthly-block layout. Do not re-add the
>     running-balance column or restore the old flat CSV.
> - **▶ Session DA (DONE — display-only "Ready to Plant" footnote added to the Stock Ledger; live `cacao:health` re-run clean; 2026-10-01):**
>   **(1) Footnote (display/text only, no logic change)** — one line was appended to the SAME three locations as
>   the Phase 7C Regraft/Grafted footnote: the on-screen grid note
>   (`resources/views/analytics/stock-ledger.blade.php:235-239`), the PDF footnote
>   (`App\Support\StockLedgerPdf::drawLedgerFootnote()`, `app/Support/StockLedgerPdf.php:222`) and the CSV
>   footnote (`AnalyticsController::exportLedgerCsv`, `app/Http/Controllers/AnalyticsController.php:286`).
>   Text: *"Ready to Plant reflects each batch's current balance, shown under its original grafted month —
>   not activity within that month. A batch's balance does not move to a later month's row as time passes."*
>   Three `StockLedgerNestingTest` assertions locked it in (view/CSV/PDF). **No formula, query, model, route
>   or data change**; `AnalyticsMetrics::stockLedgerGrid()` and the Stock Out gate are untouched.
>   **Verified:** `StockLedgerNestingTest` **9 passed / 50 assertions** on `db_capstone_test`; `vendor\bin\pint
>   --test` clean; `php -l` clean; `php artisan view:cache` clean.
>   **(2) Live `cacao:health` (read-only, no fix) — PASSES.** `php artisan cacao:health` against the current
>   live `db_capstone` printed `Cacao health check passed. 6 batch(es) satisfy all invariants.` and exited
>   **0** — all 5 rule checks (incl. the rule-5 reconciliation `available_count == (total_grafted − regraft)
>   − Σstock_out`) hold on the drifted live data (6 batches incl. B002-R1; **3** regraft records; **2**
>   stock-out details). No reseed / no DB write was performed.
>   **(3) Commits:** footnote fix = `aa847f2`; handoff docs = this commit.
> - **▶ Session CZ (DONE — read-only audit: "Ready to Plant" is `available_count` stamped on `grafted_date`, NOT `survivingQuantity()` and NOT month-scoped; NO code/data changed; 2026-10-01):**
>   **Prompt:** investigate a reported 5-vs-15 Stock Ledger discrepancy (October 2026 "Ready to Plant" = 5,
>   but a Stock Out of 15 against the same period was accepted). **Verdict: no same-batch violation — the
>   5 and the 15 belong to two *different* batches; the root cause is the mapping, not the gate.**
>   **(1) The column** — `AnalyticsMetrics::stockLedgerGrid()` (`app/Support/AnalyticsMetrics.php:542`)
>   sets `ready` at **line 585**: `[$varietyId => (int) $batch->available_count]`. It reads the live
>   **`batches.available_count`** (never `Batch::survivingQuantity()`), and it is **not month-scoped** —
>   unlike Seedlings/Mortality/Regraft/Pullout, `ready` is the batch's *current* balance merely
>   **stamped on the row of the batch's `grafted_date`** (batch query at `:557-561` filters
>   `grafted_date`, not a balance date). The monthly total is just `accumulateLedgerTotals()` (`:885`)
>   summing those stamps.
>   **(2) The data (live `db_capstone`, read-only):** the October `5` is sub-batch **B002-R1**
>   (Trinitario; `total_grafted=5`, `regraft=0`, `available_count=5`, `mort=0`, `surviving=5`; created
>   `2026-10-01` by regraft #3 B002→B002-R1 qty 5 → `RegraftBatch` sets `total_grafted=available_count=5`).
>   The accepted `15` is **B001** (Batangas; `total_grafted=500`, `regraft=100`, `available_count=385`,
>   `effectiveMortality=25`, `surviving=375`; `stock_out_details` #2, release `2026-10-01`). B001's
>   `ready` stamp (**385**) sits in the **July 2026** row, because its `grafted_date` is `2026-07-15`.
>   Different batch **and** different variety.
>   **(3) Gate vs display — different method + different scope.** The release gate
>   (`StockOutController::lockAndValidateBatches`, `:197`/`:220`) uses **`survivingQuantity()`**; the
>   column uses **`available_count`**. For one batch at one instant the display can never be *less* than
>   the gate, since `survivingQuantity() = max(0, min(available_count, (total_grafted−regraft)−mort)) ≤
>   available_count` — it actually shows **more** where mortality exists (B001: **385** displayed vs
>   **375** gated). The real hazard is the **scope**: "Ready to Plant" is pinned to the grafted month,
>   so October only totals batches grafted in October (B002-R1 = 5) and silently omits every older batch
>   still releasable (B001's 385/375 lives in July) — a user reading October sees 5 and can't reconcile
>   a 15 release from another batch.
>   **(4) Nothing changed** — no formula, view or data touched. Ties to `AGENTS.md` P6 deferred item #4
>   ("Ready to Plant" maps to `available_count`, pending client confirmation); a fix should wait on that
>   definition. **Live state drift observed (owner testing since the CY reseed):** live `db_capstone` now
>   has **6** batches (a new **B002-R1** Trinitario), regraft_records **3**, stock_out_details **2**
>   (the new B001 Stock Out of **15**, `2026-10-01`), and B001 `available_count` **385** — so the CY
>   "after" snapshot (5 batches / 1 stock_out / B001 400) no longer matches the live DB. No DB write was
>   performed this session. **(SUPERSEDED 2026-10-01 by Session DB: live `db_capstone` was re-reseeded —
>   the B002-R1 drift is gone and the DB is back on the 5-batch seed / 400. See the top banner.)**
> - **▶ Session CY (DONE — help note + live `db_capstone` reseed; commits `b451b9c` (code) + `6f41a6a` (docs); 2026-10-01):**
>   **(1)** A one-line help note — *"Total counts each plant once; a regrafted plant's quantity is not
>   added again under its new sub-batch."* (Gap Report: "Expected counts each plant once…"; Inventory:
>   "Counts each plant once…") was added to the Stock Ledger nested Batch summary totals (view/PDF/CSV),
>   the Gap Report Expected total (view/PDF/CSV) and the Inventory Total Grafted card. Display/text only,
>   no logic change; new assertions lock it in. Full MySQL suite **344 passed / 1506 assertions** on
>   `db_capstone_test`; Pint + `php -l` + `view:cache` clean. **(2)** One approved destructive action on
>   `db_capstone`: verified backup
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_reseed_20261001_201512.sql`
>   (**37,218 B / 23 tables**, clean `Dump completed on 2026-10-01 20:15:13` trailer) →
>   `php artisan migrate:fresh --seed --force` (exit 0). **Before:** batches **6** (5-batch seed + a
>   manually-added **B004** Toledo of 50), stock_outs 2, stock_out_details 2, activity_logs 13,
>   `cacao:health` PASSED (6). **After:** batches **5**, stock_ins 4, stock_outs 1, stock_out_details 1,
>   mortality_records 4, regraft_records 2, inventories 5, scion_varieties 5, users 1, activity_logs 11;
>   `migrate:status` **0 Pending**; `cacao:health` PASSED (5). Family B001 500/400/100 · B001-R1
>   100/50/30 · B001-R1-R1 30/30/0 · B002 150/150/0 · B003 100/100/0; KPIs **730 / 7.33% (55/750) / 25**;
>   the three cross-batch totals all read **750**; all **20 sidebar pages 200**; Stock Out hint ==
>   `survivingQuantity()` (B001 **375** vs raw 400). **B004 was manually added, not seeded.** Tests still
>   run only on `db_capstone_test`.
> - **▶ Session CX (DONE — new `cacao:health` invariant; commit `89905c9`; 2026-09-30):**
>   `php artisan cacao:health` (`app/Console/Commands/CacaoHealthCommand.php`) gained **rule 5**:
>   `available_count == (total_grafted - regraft) - SUM(stock_out_details.quantity)` for **every** batch
>   (root and sub-batch), reported in the same `Batch | Rule | Expected | Actual` table / exit codes as
>   the existing checks. New batched `$stockOutByBatch` query mirrors the `regraftRecordsByBatch`
>   pattern (no N+1). **Why:** the existing rules 2/3 only *bound* `available_count` to a range, so a
>   silently altered balance (direct DB edit or future bug) passed as long as it stayed in range; rule 5
>   reconciles it against the transaction history. **Test:** new
>   `CacaoHealthTest::test_health_command_reconciles_available_count_against_the_stock_out_history` —
>   seeds a batch, records a real Stock Out (30) and sets `available_count=70` → passes; then raw-updates
>   `available_count=80` (still inside `0..total_grafted−regraft`) → **fails**, which the old checks
>   would not have caught. **Verified:** full MySQL suite **344 passed / 1499 assertions** on
>   `db_capstone_test` (was 343/1494); `cacao:health` on a fresh seed → clean (5 batches); Pint +
>   `php -l` clean. **Motivated by** an investigation of the live `B001` drop 400→370: it was a
>   **legitimate** Stock Out (`stock_out_details` id 2 / header id 2, **2026-09-29**, 30 pots @25,
>   `Mountain View Estate`, amount 750.00; `activity_logs` `stock_out_recorded`), so no anomaly — see the
>   CW bullet below for the follow-on totals fix.
> - **▶ Session CW (DONE — three cross-batch `total_grafted` totals switched to `SUM(total_grafted - regraft)`; commit `f9bbd1e`; 2026-09-30):**
>   Fixed the totals that summed the raw cumulative `total_grafted` across multiple batches (a regrafted
>   plant was counted once per batch it passed through). **1.** `AnalyticsMetrics::batchSummary()`
>   `totals.total_grafted` (Stock Ledger nested-summary totals row). **2.** `AnalyticsMetrics::gapReport()`
>   `totalExpected`. **3.** `InventoryController@index` `totalGrafted` (sums `inventories` snapshots).
>   Each now uses `SUM(total_grafted - regraft)`, matching `mortalityRateComponents()` `:122`.
>   **Per-row `total_grafted` is deliberately left raw** (Batches page, individual Stock Ledger/Gap rows)
>   — the footer total now intentionally differs from the arithmetic sum of the visible per-row column,
>   per the client's "Grafted minus Regraft" basis. **Grep finding:** `AnalyticsMetrics::stockLedgerGrid()`
>   `summary.grafted` (Stock Ledger *Grafted* grid column) is **root batches only**, so it already counts
>   each plant once — **not** changed. **Tests:** updated `AnalyticsTest` (gap 300→**285**;
>   PDF `Expected: 285`; CSV `Total,,,285,...`) and `StockLedgerNestingTest` (880→**750**); added
>   `SeededFamilyKpiTest::test_cross_batch_totals_count_each_plant_once_not_the_raw_sum`. Seeded family
>   now reads **750** (not 880) in all three. **Verified:** full MySQL suite **343 passed / 1494
>   assertions**. No schema/DB change; `db_capstone` untouched.
> - **▶ Session CV (DONE — one-time owner-approved destructive action on `db_capstone`; DB + docs only, no code change; 2026-09-30):**
>   `php artisan migrate:fresh --seed --force` against connection `mysql` / database `db_capstone`
>   after a verified backup at
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_reseed_20260930_034319.sql`
>   (**37,510 B**; **23 `CREATE TABLE`** == all 23 live tables, same list; clean
>   `-- Dump completed on 2026-09-30  3:43:19` trailer; `--single-transaction`). **Before:**
>   `cacao:health` **FAILED (exit 1)** — 2 violations (B001 expected 300 actual 400; B001-R1 expected 40
>   actual 50) — the stale Phase-7D arithmetic; this was the first real-world run of `cacao:health`
>   against stale data and it caught exactly the expected violations. **After:** `migrate:status`
>   **0 Pending**; `cacao:health` **PASSED (exit 0)**; seeded family B001 500/400/100 ·
>   B001-R1 100/50/30 · B001-R1-R1 30/30/0 · B002 150/150/0 · B003 100/100/0 (Σtotal_grafted **880** /
>   live `Σ(total_grafted − regraft)` **750** / `Σavailable` **730** / `Σregraft` **130**; 0
>   violations). KPIs: Total Active Inventory **730**, Crude Mortality Rate **7.33%** (55/750),
>   Regraft Deficit **25**, Gap total **55**. All **20 sidebar pages 200**; the Stock Out hint equals
>   `survivingQuantity()` for every batch (B001 **375** vs raw 400); Stock Ledger CSV **200**
>   (`text/csv`). Tests still run only against `db_capstone_test`. **This resolved banner option A**
>   (live realignment). Backups kept untouched (see "Standing rules").
> - **▶ Session CU (DONE — Stock Out form's client-side availability hint now reads `survivingQuantity()`; view-only + one view test, no schema/validation change, `db_capstone` untouched; commit `13e658e`; 2026-09-30):**
>   - The batch `<option>`'s `data-available` attribute and its inline `(Available: …)` label in
>     `resources/views/inventory/stock-out/index.blade.php` now call `$batch->survivingQuantity()`
>     instead of the raw `(int) $batch->available_count`. The client-side `validate()` already reads
>     `data-available`, so the hint (and the pre-submit check) now show the same number
>     `StockOutController::lockAndValidateBatches` enforces (per-line `:197`, per-batch aggregate
>     `:220`). Closes the CT "Scope note / follow-up". **No new formula/method** — reuses the existing
>     centralized cap; no N+1 optimization added (same lazy pattern as `RegraftController`).
>   - **Test:** new `StockOutTest::test_stock_out_form_hint_matches_the_surviving_quantity_for_a_batch_with_mortality`
>     — batch `available_count=500` with 400 recorded deaths (raw **500** vs surviving **100**, i.e.
>     deliberately not the trivial mortality-0 case where the two numbers coincide); asserts the page
>     renders `data-available="100"` and `(Available: 100)` and **not** `data-available="500"`.
>   - **Verified:** full MySQL suite **342 passed / 1484 assertions** on `db_capstone_test` (was
>     341/1478); `vendor\bin\pint --test` + `php -l` clean on the changed file; `php artisan view:cache`
>     clean. **`db_capstone` was NOT touched.**
>   - **Still open (unchanged):** the `AGENTS.md` doc drift (CONFIRMED / Phase 7B sections still name
>     `Batch::regraftableQuantity()`; the method is now `Batch::survivingQuantity()`) and the remaining
>     Session CS Tier-1 items (log Inventory snapshots to `Activity_Log`; fix the "Total **Active**
>     Inventory includes archived" label; point `phpunit.xml` at MySQL `db_capstone_test`).
> - **▶ Session CT (DONE — landed the Session CS Tier-1 fixes: Stock Out surviving cap + read-only `cacao:health`; no schema change, no client sign-off, `db_capstone` untouched; 2026-09-30):**
>   - **Fix 1 — Stock Out can no longer release already-dead plants.** Renamed
>     `Batch::regraftableQuantity()` → **`Batch::survivingQuantity()`** (same formula
>     `max(0, min(available_count, (total_grafted − regraft) − effectiveMortalityQuantity()))`; **no
>     duplicate method**). Call sites updated: `App\Actions\RegraftBatch` (regraft cap),
>     `RegraftController` (page summary), and `StockOutController` — the per-line check and the
>     per-batch aggregate check now gate on `survivingQuantity()` instead of the raw `available_count`.
>     Rejection message: `Quantity exceeds the {n} surviving plants available for {batch_code}.`
>     Files: `app/Models/Batch.php`, `app/Actions/RegraftBatch.php`,
>     `app/Http/Controllers/RegraftController.php`, `app/Http/Controllers/StockOutController.php`,
>     `tests/Feature/RegraftFlowTest.php`, `tests/Feature/StockOutTest.php`.
>   - **Fix 2 — read-only invariant health check.** New **`php artisan cacao:health`**
>     (`app/Console/Commands/CacaoHealthCommand.php`, auto-discovered). Per batch it checks:
>     `effective mortality ≤ total_grafted − regraft`; `available_count ≥ 0`;
>     `available_count ≤ total_grafted − regraft`;
>     `batches.regraft == SUM(regraft_records.quantity WHERE parent_batch_id = batch)`. A clean run
>     prints `Cacao health check passed. N batch(es) satisfy all invariants.` and exits **0**;
>     otherwise it prints a `Batch | Rule | Expected | Actual` table and exits **1**. **Strictly
>     read-only** (no writes/repairs).
>   - **Tests:** `StockOutTest::test_stock_out_is_capped_by_the_surviving_population_not_the_raw_available_balance`
>     — grafted 500, mortality 400, `available_count` stays 500 (confirmed rule) → `survivingQuantity()`
>     **100**; a 200 release is **rejected** (message names `B001` and `100`) and a 100 release
>     **succeeds** (balance → 400). `tests/Feature/CacaoHealthTest.php` — a consistent dataset passes,
>     and a batch corrupted by a direct DB update is reported (exit 1, batch code in output).
>   - **Verified:** full MySQL suite **341 passed / 1478 assertions** on `db_capstone_test` (was
>     338/1465); `vendor\bin\pint --test` + `php -l` clean; `cacao:health` against a freshly seeded
>     `db_capstone_test` → **clean, exit 0** (`Cacao health check passed. 5 batch(es)...`).
>     **`db_capstone` was NOT touched/reseeded.**
>   - **Commits:** `b939aab` (baseline checkpoint of the pending Sessions CL–CR working tree — owner
>     choice), `dccd154` (Fix 1), `7ec2f8c` (Fix 2). The two untracked `docs/cacao-*.csv` sample
>     exports were deliberately left untracked.
>   - **Scope note / follow-up:** the Stock Out form's client-side `data-available` hint still showed
>     raw `available_count` at CT; the server gate was authoritative. **RESOLVED in Session CU** — the
>     hint now reads `survivingQuantity()`. **Doc drift to fix later (not touched):** `AGENTS.md`
>     (CONFIRMED / Phase 7B sections) still names `Batch::regraftableQuantity()`; the method is now
>     `Batch::survivingQuantity()`.
> - **▶ Session CS (DONE — read-only KPI audit + panelist-style flaw review; NO code/migration/DB/docs change, 2026-09-30):**
>   traced the two headline KPIs live through the app and audited them. **No file was edited except this
>   handoff; no migration, no schema and no `db_capstone` action.**
>   - **Crude Mortality Rate = 12.10%** = `numerator 75 / denominator 620 × 100`
>     (`AnalyticsMetrics::mortalityRateComponents()` `app/Support/AnalyticsMetrics.php:114-124`;
>     numerator = `effectiveMortality()` = raw `mortality_records.quantity`,
>     `app/Models/MortalityRecord.php:116-119`; denominator = `SUM(total_grafted − regraft)`,
>     `AnalyticsMetrics.php:122`; `crudeMortalityRate()` rounds to 2dp `:126-139`; displayed by
>     `AnalyticsController.php:62,99`). Verified via `php artisan tinker`: `{"numerator":75,"denominator":620}`.
>     Numerator = 8 live records (B001 25+5, B002 15+5+5, B003 10+5, B001-R1 5); denominator per batch
>     B001 400−100=300, B002 150, B003 100, B001-R1 70−30=40, B001-R1-R1 30 → 620.
>   - **Total Active Inventory = 730** = `SUM(batches.available_count)`
>     (`AnalyticsMetrics::totalActiveInventory()` `:85-88` → `Batch::totalAvailableQuantity()`
>     `app/Models/Batch.php:274-277`; **archived batches deliberately included**). Per batch: B001 400,
>     B002 150, B003 100, B001-R1 50 (100 regrafted in − 30 regrafted out − 20 Stock Out),
>     B001-R1-R1 30. `available_count` changes only on create (= `total_grafted`, provisional OD-3),
>     regraft (−q) and Stock Out (−q); **mortality never changes it** (`Batch.php:195-197`).
>   - **Flaws identified (panelist audit; NOT fixed):** (1) `available_count` still counts dead plants
>     yet Stock Out is gated on it (`StockOutController.php:138,194,215`) → can release dead stock;
>     (2) two different "live" populations on one screen — Active Inventory 730 (gross) vs mortality
>     denominator 620 (live); (3) live rows violate the stated invariant `available_count ≤
>     total_grafted − regraft` (B001 400>300, B001-R1 50>40); (4) `Batch::regraftDeficit()` compares
>     unrelated quantities `max(0, mortality − regraft)` (OD-10 open); (5) "Total **Active** Inventory"
>     includes **archived** batches; (6) a `from`/`to` range filters only the mortality numerator, never
>     the denominator (`AnalyticsMetrics.php:60-61` vs `:122`); (7) no DB unique index for one inventory
>     snapshot per batch per month (`SHOW INDEX` → only non-unique `inventories_batch_id_index`),
>     snapshots write no `Activity_Log`, no correction path (OD-5), per-batch "Total Grafted" label is a
>     residual (Session CI). **Fix proposals were given in three tiers** — Tier 1 (safe code-only:
>     unify a single `survivingStock()`/`releasableQuantity()` cap shared by stock-out + regraft,
>     health-check command + invariant tests, log inventory snapshots, fix labels, MySQL phpunit config),
>     Tier 2 (needs decision: available semantics, Active-vs-archived, Regraft Deficit, date-filter
>     definition), Tier 3 (needs ERD approval: unique inventory index, Stock In↔Batch traceability
>     OD-8, scion freshness OD-13). **Nothing implemented.**
>   - **Repo unchanged:** latest commit still `4006b41`; Sessions CL/CM/CN/CO/CP/CQ/CR remain
>     uncommitted; full MySQL suite still **338 passed / 1465 assertions** (not re-run this session).
> - **▶ Session CR (DONE — owner-directed rename of the `replanting_records` entity to `regraft_records`; 2026-09-30):**
>   the table `replanting_records` → **`regraft_records`** (PK `replanting_id` → `regraft_id`, date
>   `replanting_date` → `regraft_date`), the model `ReplantingRecord` → **`RegraftRecord`**, relation
>   `Batch::regraftRecords()` / `regraftRecordsAsParent()`, `Batch::totalRegrafted()` /
>   `regraftDeficit()`, `AnalyticsMetrics::totalRegraftDeficit()` / `regraftDeficitByBatch()`,
>   `RegraftController` (route **`operational.regraft`** = `GET/POST /operational/regraft[/records]`),
>   `StoreRegraftRecordRequest` (error bag `regraft`, field `regraft_date`), view
>   `operational/regraft/index.blade.php`, `RegraftRecordFactory`, and `ActivityLog` type
>   `replanting_recorded` → **`regraft_recorded`**. **Migration (reversible):**
>   `2026_09_30_000001_rename_replanting_records_to_regraft_records_table` (also migrates the stored
>   `activity_logs.activity_type` value). The historical P5 `create/drop_regraft_records_table`
>   migrations are left intact as history. **Docs:** canonical `docs/CacaoTrack_PRD.md` +
>   `docs/CacaoTrack_ERD.md` were updated (the `_REVISED` copies re-synced **byte-identical**,
>   SHA-256 match); `_UPDATED` provenance untouched; `AGENTS.md` gained a "RENAME" banner. **This
>   intentionally diverges from the client-approved v1.0 baseline** (the ERD named the entity
>   `Replanting_Record` and the standing rule said not to rename beyond the ERD); recorded here for
>   audit. **Verified:** full MySQL suite **338 passed / 1465 assertions** on `db_capstone_test`,
>   Pint + `php -l` + `view:cache` clean, `route:list` shows the two `operational.regraft` routes.
>   **✅ Live `db_capstone` WAS migrated** in the same session (owner explicitly signed off the schema
>   action, 2026-09-30) after a fresh verified backup
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_regraft_rename_20260930.sql`
>   (36,826 B, left untouched). `migrate:status` **0 Pending**; live table verified: `regraft_records`
>   YES / `replanting_records` NO, columns `regraft_id` / `regraft_date` / `parent_batch_id` present,
>   **2 rows preserved** (Σqty 130), `activity_logs` migrated (2 `regraft_recorded`, 0 old). Read-only
>   smoke test as the seeded admin: `/`, `/operational/regraft`, `/operational/batches`,
>   `/inventory/stock-in`, `/analytics`, `/analytics/stock-ledger` all **200**. The migration was also
>   validated up→down→up on `db_capstone_test` (tests still run only against `db_capstone_test`).
> - **▶ Previous: Session CQ — Stock Out unit dropdown is now Pots only**
> - **▶ Session CQ (DONE — Stock Out unit is now `pots` only; no schema/data change, 2026-09-29):**
>   on `/inventory/stock-out` the per-line **Unit** dropdown now offers **Pots only** (previously
>   Seedlings / Pieces / Bags). **One centralized constant — `App\Models\StockOutDetail::UNITS`
>   changed to `['pots']`** (RULE 6); the Stock Out form `<select>`, `StoreStockOutRequest` and
>   `StockOutDetail::rules()` all derive from it, so the dropdown and server-side validation changed
>   together. **No migration / no column change** — `stock_out_details.unit` is untouched; legacy
>   live rows keep their historical `seedlings` value (read-only display via `ucfirst`). The Stock Out
>   new-line JS default is now `StockOutDetail::UNITS[0]` (`resources/views/inventory/stock-out/index.blade.php`)
>   and the seeder's Stock Out line uses `StockOutDetail::UNITS[0]` (`DatabaseSeeder`). **Tests:**
>   `StockOutTest` / `StockValidationTest` / `RegraftFlowTest` updated to `pots`; new
>   `StockOutTest::test_stock_out_unit_dropdown_offers_only_pots` (invalid-unit rejection already
>   covered by the existing `bogus` case). **Docs:** `AGENTS.md` decision note (b) restated as
>   pots-only; **PRD/ERD unchanged** (the unit list is not specified there — `_UPDATED` provenance
>   untouched, `_REVISED` still byte-identical). **Verified:** full MySQL suite **338 passed / 1468
>   assertions** on `db_capstone_test` (was 337/1465); `vendor\bin\pint --test` + `php -l` clean;
>   `php artisan view:cache` clean. **No schema/migration/PRD/ERD change; no `db_capstone` change.**
>   Flag (not changed): the Stock Out Activity Log description still reads "Released {n} seedlings to
>   {client}" — it is descriptive text, not the unit field.
> - **▶ Session CP (DONE — Cacao Tree Stock Ledger print (FPDF) font auto-fit; presentation-only, no schema/data change, 2026-09-29):**
>   on `/analytics/stock-ledger` the **Print Ledger** PDF (`GET /analytics/stock-ledger/export`) no longer
>   spills text across cell borders: the per-Scion-Variety column headers (e.g. **Batangas** needed
>   **10.32mm** in a **7.84mm** column at 6.5pt) overflowed their cells because the label font was fixed
>   regardless of column width. **One file changed — `app/Support/StockLedgerPdf.php` only:** added a
>   private **`fitText()`** helper that shrinks the font to fit the cell and only truncates with `...`
>   below a **4.2pt** floor; applied it to the monthly-grid **per-variety headers**, every grid value cell
>   (through `vCenter()`), and the nested **Batch summary** header + rows. The old char-count
>   `shorten()`/`clip()` truncation was removed (width-aware fitting replaces it). **No data, query,
>   analytics, route or view change** — the on-screen ledger is untouched and every value still comes from
>   the centralized `AnalyticsMetrics::stockLedgerGrid()` (RULE 5/6/12; PRD §9.3/§16). **Verified:** a
>   read-only FPDF probe over the live seeded grid went **76 overflowing labels → 0**; a stress probe
>   (12 long-named varieties, 7-digit values, long wrapping description, depth-2 nesting, empty-variety
>   and no-data paths) also reported **0 overflows**; full MySQL suite **337 passed / 1465 assertions** on
>   `db_capstone_test`; `vendor\bin\pint --test` + `php -l` clean. **No schema/migration/PRD/ERD change;
>   no `db_capstone` change.** Latest commit `4006b41` (CK); **Sessions CL, CM, CN, CO and CP are all
>   uncommitted** (CP touched only `app/Support/StockLedgerPdf.php` + this handoff). Validation note: the
>   frozen `docs/CacaoTrack_PRD_UPDATED.md` §2.4/§8 still prose "deduct regraft from the parent's
>   Total_Grafted", which the client-CONFIRMED cumulative rule supersedes (parent `total_grafted` is not
>   reduced — only `available_count` drops and `regraft` rises; see the CONFIRMED section in `AGENTS.md`).
> - **▶ Session CO (DONE — Mortality Seasonality Heatmap axis-label presentation; view-only, no schema/data change, 2026-09-29):**
>   three small edits to `resources/views/analytics/historical/seasonality.blade.php` (the ECharts `setOption`):
>   (1) the **"Scion Variety"** y-axis title moved off the rotated left-middle to a **horizontal blue note at
>   the top-left** (`nameLocation: 'end'`, `nameRotate: 0`, `nameTextStyle` `{ color: '#0d6efd', fontWeight:
>   'bold' }`) with the grid top margin raised so it does not overlap the heatmap; (2) the temporary header
>   caption ("Rows: Scion Variety · Columns: Month") was **removed**; (3) the **"Month"** x-axis title now
>   uses the **same blue/bold style** as "Scion Variety". **Presentation-only — no data/query change:**
>   orientation is unchanged (rows = Scion Variety, columns = Month) and the cells still come from real
>   `mortality_records` through `AnalyticsMetrics::seasonality()` (PRD §9.5/§14; no fabricated data, §16).
>   **Verified:** `php artisan view:cache` clean; `php artisan test --filter=seasonality` **2 passed / 7
>   assertions** on `db_capstone_test` (no test files changed — the full suite remains **337 passed / 1465
>   assertions**). **No schema/migration/PRD/ERD change; no `db_capstone` change.** Prior commits:
>   `4006b41` (CK) / `1a7ce78` (CJ); **Sessions CL, CM, CN and CO are all uncommitted** (CO touched only
>   `resources/views/analytics/historical/seasonality.blade.php` + this handoff).
> - **▶ Session CN (DONE — Stock In log distinguishes Seedlings vs Scion receiving; presentation-only, no schema change, 2026-09-29):**
>   the `/inventory/stock-in` **Receiving History** table gained a dedicated **TYPE** column that shows a
>   **Seedlings** (green) or **Scion** (info) badge per row, so a receiving is identifiable at a glance;
>   the **Stock-In Details** modal shows the same **Type** row. There is a new **Type** filter
>   (All Types / Seedlings / Scion) on the filters bar, and the summary cards now reflect it (all of them
>   already derive from the same filtered `$base`). **Centralized (RULE 6):** new
>   `StockIn::entryType()` + `StockIn::entryTypeLabel()` (and `TYPE_SEEDLING`/`TYPE_SCION` constants)
>   derive the label from `isScionEntry()` — **no stored discriminator column** (ERD §3.7: the presence
>   of `scion_variety_id` is the signal; the ERD/PRD are unchanged). **Controller:**
>   `StockInController@index` validates `type` (`in:seedling,scion`) and filters
>   `whereNotNull('scion_variety_id')` / `whereNull('scion_variety_id')`. **Tests:** 2 new
>   (`StockInTest::test_entry_type_is_derived_and_labelled`, `..._page_filters_by_entry_type`).
>   **Verified:** full MySQL suite **337 passed / 1465 assertions** on `db_capstone_test` (was
>   335/1454); Pint + `php -l` clean. **No schema/migration/PRD/ERD change; no `db_capstone` change.**
>   Prior commits: `1a7ce78` (CJ) / `4006b41` (CK); **Sessions CL, CM and CN are all uncommitted.**
> - **▶ Session CM (DONE — Root-Cause Matrix treats `Others` as a single cause category; no schema change, 2026-09-29):**
>   every `Others: <description>` mortality row now rolls up into **one `Others` column** in the
>   Mortality Root-Cause Matrix — the free-text description is only a specification, not a distinct
>   cause. **Centralized (RULE 6):** new `MortalityRecord::normalizeCause()` (`Others` / `Others: ...`
>   → `Others`; blank → `Unspecified`; other causes unchanged); `AnalyticsMetrics::mortalityMatrix()`
>   keys every cell through it, so the page table, the printable `MortalityMatrixPdf` and the CSV export
>   all group alike; `AnalyticsMetrics::mortalityMatrixRegressions()` also normalizes the baseline
>   snapshot keys, so an older saved report holding a split `Others: X` no longer reads as a brand-new /
>   increased cause. **Tests:** 3 new
>   (`AnalyticsTest::test_mortality_matrix_collapses_others_specifications_into_one_cause`,
>   `..._collapses_others_in_pdf_and_csv_exports`,
>   `MortalityTest::test_normalize_cause_collapses_others_specifications`). **Verified:** full MySQL
>   suite **335 passed / 1454 assertions** on `db_capstone_test` (was 332/1437); Pint + `php -l` clean.
>   **No schema/migration/PRD/ERD change** — the ERD `Mortality_Record` still has only `Cause`, and the
>   description is stored inside it; `OD-2` (`Plant_Condition`) stays open. **No `db_capstone` change.**
>   Prior commits: `1a7ce78` (CJ) / `4006b41` (CK); **Sessions CL and CM are both uncommitted.**
> - **▶ Session CL (DONE — Mortality Tracking "Cause of Death" dropdown + conditional "Others" description; no schema change, 2026-09-29):**
>   the Mortality form's free-text `cause` (`<input list="causeOptions">` datalist) is replaced by a fixed
>   **"Cause of Death" `<select>`**; choosing **Others** reveals a required **Description** input whose text
>   is folded into the single `cause` column as `Others: <description>` (the ERD `Mortality_Record` has no
>   separate description attribute — **no migration / no new column**). **Centralized (RULE 6):**
>   `MortalityRecord::CAUSES` (`Disease`, `Pest`, `Drought`, `Physical Damage`, `Others`) + `CAUSE_OTHERS`
>   + `composeCause()`; `StoreMortalityRecordRequest` validates `cause` with `Rule::in(CAUSES)` and
>   `cause_other` via `Rule::requiredIf(cause === Others)` (max 200); `MortalityController@store` stores the
>   composed cause (and uses it in the Activity Log description); the view reads the model constant and
>   toggles the field with JS; the list + details modal show the composed value. **Tests:** 3 new
>   `MortalityTest` cases (Others + description stored, Others without description rejected, unknown cause
>   rejected). **Verified:** full MySQL suite **332 passed / 1437 assertions** on `db_capstone_test`
>   (was 329/1427), Pint + `php -l` clean. **No PRD/ERD change** — the ERD `Mortality_Record` still has only
>   `Cause`; `OD-2` (`Plant_Condition`) stays open. **No `db_capstone` change.** Prior commits: `1a7ce78`
>   (CJ) / `4006b41` (CK); **this session's change is not committed yet.**
> - **▶ Session CK (DONE — follow-up to CJ: mortality-rate denominator de-double-counted, 2026-09-29):**
>   `AnalyticsMetrics::mortalityRateComponents()` now returns denominator
>   `SUM(total_grafted − regraft)` instead of raw `SUM(total_grafted)`, and
>   `survivalVsMortality()` uses the same live-population basis (both otherwise double-counted every
>   regrafted plant — source + sub-batch, and again if nested). Verified live on a fresh
>   `db_capstone_test` seed: raw `SUM(total_grafted)` **880**, `SUM(regraft)` **130**,
>   `SUM(total_grafted − regraft)` **750**; numerator 55 → **rate 7.33%** (back to the correct value
>   CJ's 6.25% had wrong). PRD §10/§11/§19 and ERD §3.8 restated as `SUM(Total_Grafted − Regraft)`;
>   `_REVISED` copies byte-identical. Full MySQL suite **329 passed / 1427 assertions**.
>   **Explicitly checked and NOT changed** (see the CK write-up): `stockLedgerGrid().summary.grafted`
>   (root batches only — no parent/sub double count); `batchSummary().totals.total_grafted` and
>   `gapReport().totalExpected` (arithmetic/context sums of the per-batch cumulative column, not a
>   claimed unique population — flagged for the already-open label decision); `InventoryController`
>   `totalGrafted` (sums snapshot rows, a different aggregation). **No `db_capstone` change.**
> - **▶ Session CJ (DONE — client-confirmed `Total_Grafted` semantics; two source changes + tests + docs, 2026-09-29):**
>   the client confirmed (spreadsheet `RU-Summary.jpg`, row 4 / 10/2025 "re graft" = a new Grafted entry
>   plus a Re Graft entry on the **same row**; direct statement *"Grafted minus Regraft = mortality or
>   yield for the parent batch"*) that **`total_grafted` is a cumulative, non-decreasing log per batch**
>   and a regraft never reduces it. A batch's still-attributable/live population for mortality and
>   regraft is **`total_grafted − regraft`**. **Two source changes (shipped together):**
>   `App\Actions\RegraftBatch::execute()` no longer writes `source.total_grafted` (only
>   `available_count -= q`, `regraft += q`); and `Batch::remainingMortalityCapacity()` /
>   `Batch::regraftableQuantity()` now bound against `(total_grafted − regraft) − effective mortality`
>   instead of raw `total_grafted`. **Tests:** new
>   `RegraftFlowTest::test_caps_are_computed_against_the_remaining_population_not_the_raw_total`; the
>   Phase 7C family-conservation assertions rewritten (the raw family sum is no longer conserved;
>   `total_grafted − regraft` is). Full MySQL suite **329 passed / 1425 assertions** against
>   `db_capstone_test` (was 328/1406). **Seeded family under the corrected rule:** B001 500/400/100 ·
>   B001-R1 100/50/30 · B001-R1-R1 30/30/0 · B002 150/150/0 · B003 100/100/0 → raw `Σtotal_grafted`
>   **880**, `Σavailable` **730**, `Σregraft` **130**; Crude Mortality Rate at that commit 55/880 =
>   **6.25%** — **corrected in Session CK to 55/750 = 7.33%**;
>   stock-ledger *Grafted* (roots only) **750**. **Docs:** `docs/CacaoTrack_PRD.md`
>   §2.4/§10/§11 (new "Total Grafted")/§19 — the Total_Grafted clauses of `OD-4`, `OD-6`, `OD-10` are
>   now **CONFIRMED** — and `docs/CacaoTrack_ERD.md` §2 rule 7/§3.8/§3.11; the `_REVISED` copies are
>   byte-identical (SHA-256 match). **`db_capstone` was deliberately NOT reseeded** at the time (per
>   instruction): it held the Phase 7D seed produced under the old arithmetic (B001 total 400, regraft
>   100), so live rows did not satisfy `available_count ≤ total_grafted − regraft`.
>   **⚠ SUPERSEDED 2026-09-30 (Session CV):** `db_capstone` was reseeded under the corrected rule (see
>   the Session CV bullet at the top and "RESEED EXECUTED" in `AGENTS.md`); `cacao:health` now passes.
>   Standing rule unchanged: tests only against `db_capstone_test`; any further destructive action on
>   the live DB needs fresh explicit sign-off.
> - **▶ Session CI (DONE — read-only audit of the seeded regraft family's quantities; NO code, migration, seeder or test change, 2026-09-29):**
>   investigated a report that the SOURCE batch's `available_count` was not decremented when
>   `RegraftBatch` ran. **Verdict: no code bug and no data bug — no fix required.**
>   `App\Actions\RegraftBatch::execute()` decrements **both** `total_grafted` and `available_count` by
>   `q` and increments `regraft`, all under the same `lockForUpdate` transaction
>   (`app/Actions/RegraftBatch.php:98-100`) — there is no missing update statement.
>   **Live `db_capstone` is internally consistent** (read-only): B001 400/400 regraft 100 · B001-R1
>   70/50 regraft 30 · B001-R1-R1 30/30 · B002 150/150 · B003 100/100; **SUM total_grafted 750,
>   SUM available 730, SUM regraft 130 == Σ `replanting_records.quantity` 130**. Per batch,
>   `available_count + regraftedOut + stockOut` = that batch's original graft (B001 400+100 = 500;
>   B001-R1 50+30+20 = 100). **The reported discrepancy was an arithmetic-base error:** the
>   *post-regraft* `total_grafted` (400 / 70) was used as the pre-regraft base, but the seeded bases are
>   **500** (B001, `database/seeders/DatabaseSeeder.php:105-107`) and **100** (B001-R1), so the live
>   400 / 50 are correct. The cited dump
>   `...\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql` **predates the Phase 7D reseed**
>   (old schema; `B001 200/180/180`, no `B001-R1` row) and cannot be the source of those numbers.
>   `tests/Feature/RegraftFlowTest.php:94` asserts `source.available_count == 400` (also
>   `:173`/`:397`/`:418`); the file was run against `db_capstone_test` — **21 passed / 104 assertions**.
>   **Recorded semantics (relevant to `OD-3`/`OD-12`):** a **single batch's `total_grafted` is a live
>   residual, not "originally grafted"** — it falls on every regraft. Only the **family/global SUM**
>   reads as "plants ever grafted" (gross) and is conserved across regrafting because a regraft is a
>   transfer (source `−q`, sub-batch `+q`); that SUM is therefore the correct crude-mortality-rate
>   denominator (`AnalyticsMetrics` `:111`/`:207`). Per-batch residual `total_grafted` is still labelled
>   **"Total Grafted"** and shown on its own row by: the **Batches page**
>   (`resources/views/operational/batches/partials/batch-row.blade.php:62`), the **Stock Ledger** grid
>   Grafted row (**root batches only**, `AnalyticsMetrics.php:554`), the Stock Ledger
>   **"Batch summary (nested)"** table/PDF/CSV (`AnalyticsMetrics.php:777`,
>   `analytics/stock-ledger.blade.php:275`, `StockLedgerPdf.php:364`, `AnalyticsController.php:300`),
>   the **Inventory** row (`inventory/index.blade.php:184`), the **Gap Report** `expected`
>   (`AnalyticsMetrics.php:471`) and the **Replanting** summary
>   (`operational/replanting/index.blade.php:140`). **Age Profile does not show `total_grafted` at all**
>   — it uses `available_count` (`AnalyticsMetrics.php:394`/`:418`). **The only open item is
>   cosmetic/label** (is "Total Grafted" the right label for a per-batch residual?) and it belongs to the
>   pending `OD-3`/`OD-12` client decisions — **do not change it unprompted.** A throwaway inspection
>   script in `...\Temp\opencode` was deleted; no repo file other than this handoff was written.
> - **▶ Session CH (DONE — Phase 7D: live `db_capstone` upgraded to the approved schema + reseeded;
>   the one approved destructive action, 2026-09-29):** with the project owner's explicit sign-off,
>   `php artisan migrate:fresh --seed --force` was run against **connection `mysql` / database
>   `db_capstone`** — the four `2026_09_27_*` migrations are now applied and the approved family is
>   seeded. **Backup first, verified and left untouched outside the git repo:**
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql`
>   — **35,131 B**, 23 `CREATE TABLE` + 15 `INSERT INTO`, clean "Dump completed on 2026-09-29
>   0:02:07" trailer, includes `CREATE DATABASE`/`USE db_capstone`. **Counts before → after:** batches
>   3 → 5, stock_ins 6 → 4, stock_outs 1 → 1, stock_out_details 1 → 1, mortality_records 4 → 4,
>   replanting_records 5 → 2, inventories 3 → 5, users 1 → 1, scion_varieties 3 → 5, suppliers 3 → 3,
>   clients 3 → 3. **Schema verified live (`Schema::hasColumn`):** `batches.viable_count`,
>   `stock_ins.viable`, `inventories.viable` = **false**; `batches.regraft`,
>   `batches.parent_batch_id`, `replanting_records.parent_batch_id` = **true**; `migrate:status`
>   **0 Pending**. **Invariants live:** `available > total_grafted` = 0, `available < 0` = 0,
>   `batches.regraft == SUM(replanting_records.quantity by parent_batch_id)` mismatches = 0, B001
>   family `total_grafted` = 500. **Seeded family:** B001 Batangas 400/400 (regraft 100,
>   mortality 25) → B001-R1 Toledo 70/50 (regraft 30, mortality 5) → B001-R1-R1 Criollo 30/30
>   (regraft 0, mortality 0); B002 Trinitario 150/150 (mortality 15); B003 Forastero 100/100
>   (mortality 10). **End-to-end (read-only, as the seeded administrator via the HTTP kernel):** all
>   **20 sidebar GET pages return 200**; KPIs — Total Active Inventory **730**, Crude Mortality Rate
>   **7.33%**, Regraft Deficit **25**, Gap total **55**; Stock Ledger CSV
>   (`/analytics/stock-ledger/export/csv`) **200**. **No data was created in `db_capstone`.**
>   Standing rule unchanged: tests run **only** against `db_capstone_test`; any further destructive
>   action on the live DB needs fresh explicit sign-off. **`db_capstone` is now on the approved
>   schema** — there is no longer any "old schema" note.
> - **▶ Session CG (DONE — Phase 7C: parent batches and regraft sub-batches are now shown clearly
>   everywhere; commits `7c84843`, `59488db`, `cc89669`):** new single tree helper
>   **`App\Support\BatchTree`** (RULE 6) orders/annotates a batch collection (roots by `grafted_date`
>   then `batch_code`, children by `batch_code`, depth 3+, orphan child kept at depth 0 flagged
>   `parent_missing`, one batched parent-lookup — no N+1). **Batches** page nests families (paginated
>   one-per-page so a family never splits) with `↳` indentation, a **Sub-batch** badge, the existing
>   Parent Batch column and a per-parent collapse. **Inventory** nests snapshots with a Parent Batch
>   column and the select label `↳ B001-R1 (Toledo, parent B001)`. **Stock Ledger** keeps the monthly
>   grid: Grafted now only counts root batches, sub-batch plants appear only under Regraft
>   (`Regraft {source} → {sub}` using the sub-batch's variety), Seedlings now counts only
>   `scion_variety_id IS NULL` Stock In rows (fixes scion rows inflating it), plus a footnote and a
>   computed **Batch summary (nested)** table below the grid; new server-side CSV route
>   `analytics.stock-ledger.export.csv` and the PDF mirror it. **Age Profile** and **Gap Report** nest
>   sub-batch rows with a Parent column (Gap formula unchanged). **Filters:** batch dropdowns list
>   sub-batches (`↳ B001-R1 (parent B001, Toledo)`) and match that exact batch only (a parent does
>   **not** include its sub-batches) — documented in each page's help text. Seeder regrafts 30 from
>   B001-R1 into **B001-R1-R1** (Criollo) via `RegraftBatch` and snapshots the whole family.
>   **Verified:** full MySQL suite **328 passed / 1406 assertions**, Pint + `php -l` clean,
>   `view:cache` clean, `migrate:status` **0 Pending** on `db_capstone_test`. **No migration**
>   (schema final). **`db_capstone` was upgraded + reseeded in Phase 7D (2026-09-29)** — the four
>   `2026_09_27_*` migrations are applied (they were still Pending when Phase 7C landed).
>   **Out of scope:** analytics formulas/`OD-n`,
>   `Plant_Condition`, `Sub_Batch_ID`, corrections policy, Ready to Plant mapping.
> - **▶ Session CF (DONE — Phase 7B: the client-approved regraft / sub-batch flow; commits below):**
>   a regraft now moves a quantity **out of a source batch into a linked sub-batch** in one atomic
>   transaction through the single action **`App\Actions\RegraftBatch`** (the controller, seeders and
>   tests all call it — RULE 6). Source `total_grafted -= q`, `available_count -= q`, `regraft += q`;
>   a new sub-batch is created (`parent_batch_id = source`, `batch_code = B001-R1`, its own 21-day
>   cycle, its own scion variety) plus a `ReplantingRecord` (`batch_id` = sub-batch,
>   `parent_batch_id` = source) and an Activity Log row. `Batch` gained `regraftableQuantity()`,
>   `nextSubBatchCode()`, `regraftRecordsAsParent()`; `totalReplanted()` is now the cached
>   `batches.regraft` counter; `remainingReplantingCapacity()` was removed; `subBatches` joined
>   `OPERATIONAL_RELATIONS` (a parent's counts lock once regrafted). `ReplantingRecord` gained
>   `parent()`/`subBatch()` + `totalRegraftedFromBatch()`. The Regraft page was rebuilt (summary
>   cards, per-batch table, Regraft History, source-prefilled modal); the Batches list gained a
>   **Parent Batch** column. **Baseline = the client-approved `_UPDATED` docs; no PRD/ERD file was
>   edited.** **Provisional defaults** (each flagged in code): `OD-6` regraftable cap
>   `min(available_count, total_grafted − effective mortality)`; `OD-6` sub-batch code rule;
>   ERD §3.11 reading (`batch_id` = sub-batch / `parent_batch_id` = source); `OD-12` regraft counter =
>   `totalReplanted()`. **Seeders** seed the regraft scenario **only via `RegraftBatch`** (B001
>   Batangas 500 / mortality 25 → regraft 100 into B001-R1 using Toledo → mortality 5 on B001-R1 →
>   Stock Out 20 from B001-R1). **Verified:** full MySQL suite **299 passed / 1278 assertions**, Pint
>   + `php -l` clean, `migrate:status` **0 Pending** on `db_capstone_test`, seeders clean + idempotent;
>   seeded invariants `available > total_grafted` = **0**, `available < 0` = **0**, family totals
>   conserve (400 + 100 = 500), `Σregraft` **100** == `Σ(records where parent_batch_id)` **100**.
>   **`db_capstone` was upgraded + reseeded in Phase 7D (2026-09-29)** — the four `2026_09_27_*`
>   migrations are applied (they remained Pending through Phase 7B). **Out of scope:** Stock Ledger / Inventory nested
>   display (7C), analytics, `Plant_Condition`, `Sub_Batch_ID`, corrections policy; the Regraft Deficit
>   and Gap formulas were not changed.
> - **▶ Session CE (DONE — Phase 7A: align schema + Stock In / Batch / Inventory with the
>   client-approved ERD; commits `24e6e7f`, `cdddacf`, `8569017`, `54e286f`):** the client-approved baseline
>   (`docs/CacaoTrack_PRD_UPDATED.md` / `_ERD_UPDATED.md`) has **no seedling viability count**; only
>   the two provisional defaults below were taken from the v2.0 `[PROPOSED]` set — **no other `OD-n`
>   item was implemented and no PRD/ERD file was edited.** Four new **reversible** migrations
>   (`2026_09_27_000001..000004`; validated up → down → up on `db_capstone_test` only):
>   `batches` drops `viable_count` and adds `regraft` (default 0) + nullable `parent_batch_id`
>   (self-FK, indexed); `stock_ins` drops `viable`; `inventories` drops `viable`;
>   `replanting_records` adds nullable `parent_batch_id` (ERD §3.11). `Batch` gained
>   `parent()`/`subBatches()`/`isSubBatch()`. Stock In / Batches / Inventory models, requests,
>   controllers, views, factories, seeders and all 16 affected tests were updated; Stock In's summary
>   card is now **"Total Received (seedlings)"** (quantity where `scion_variety_id` is NULL) and the
>   Gap Report's `expected` is `total_grafted` (the Gap formula itself is still effective mortality).
>   **Verified:** full MySQL suite **280 passed / 1173 assertions**, Pint + `php -l` clean,
>   `migrate:status` **0 Pending** on `db_capstone_test`, seeders clean + invariant-true
>   (`available_count ≤ total_grafted`). **`db_capstone` was upgraded + reseeded in Phase 7D
>   (2026-09-29)** — `viable_count` / `stock_ins.viable` / `inventories.viable` are gone and the four
>   `2026_09_27_*` migrations are applied (at Phase 7A time this was a later, approved step).
>   **Provisional defaults (pending sign-off):** `OD-3` new batch `available_count = total_grafted`;
>   `OD-4` `remainingMortalityCapacity() = max(0, total_grafted − effective mortality)`. **Out of
>   scope:** regraft/sub-batch flow (7B), Stock Ledger nesting (7C), `Plant_Condition`, `Sub_Batch_ID`,
>   correction/edit policy.
> - **▶ Session CD (DONE — doc-only: corrected client-approved PRD/ERD; checkpoint `584c548`):**
>   rewrote `docs/CacaoTrack_PRD.md` + `docs/CacaoTrack_ERD.md` from the client-approved baseline
>   (`docs/CacaoTrack_PRD_UPDATED.md` / `docs/CacaoTrack_ERD_UPDATED.md`, kept **untouched as
>   provenance**; `_REVISED` copies byte-identical). Fixes internal PRD/ERD contradictions and
>   defines previously undefined rules; **no client-approved rule was changed**. Every new,
>   unconfirmed rule carries **[PROPOSED — pending client confirmation]** and is listed in
>   **PRD §19 Open Decisions** (`OD-1`…`OD-14`) — **pending sign-off, do not implement**. **No code,
>   migration, seeder, test or route changed.** Verified: `viable` **0 hits**; `Replanting` appears
>   only as the internal `Replanting_Record` name / terminology note; **no dangling `§3.16`**;
>   canonical ↔ `_REVISED` **SHA-256 match**.
> - **▶ Session CC (DONE — `scion_receivings` merged into `stock_ins`; commit below):** the client
>   confirmed seedling Stock In and Scion Receiving are the **same real-world receiving event**, so the
>   short-lived `Scion_Receiving` entity (Session BV/CB) was retired — mirroring the earlier
>   Regraft/Replanting consolidation. **Migrations:** `2026_09_26_000003_add_scion_columns_to_stock_ins_table`
>   adds nullable `scion_variety_id` (FK → `scion_varieties`, restrictOnDelete), `unit_price`
>   decimal(12,2) and `notes` to `stock_ins`, and makes `stock_ins.supplier_id` nullable (needed to
>   preserve scion rows with no formal Supplier); `2026_09_26_000004_merge_scion_receivings_into_stock_ins_table`
>   **data-migrates** every `scion_receivings` row into `stock_ins` (`viable = quantity`, `origin`/`notes`
>   preserved, attributed to the earliest user) then **drops** the table. Create migration
>   `2026_09_26_000001` kept as history. **Retired:** `ScionReceiving` model, factory, feature test,
>   controller, form request, view, the `/operational/scion-receivings` routes and the sidebar link.
>   **Stock In UI:** one "Add Stock In" modal now has a **Seedlings / Scion** Bootstrap nav-tabs pair
>   (shared supplier/quantity/origin/date; the Scion tab adds scion variety + unit price + notes and the
>   server sets `viable = quantity`); the single list shows a **Scion Variety** column ("—" for
>   seedling rows). Conditional validation lives in `StoreStockInRequest` (`required_with` pair +
>   `Rule::requiredIf` for viable). **No discriminator column** — `scion_variety_id` presence is the
>   signal (`StockIn::isScionEntry()`). **Docs:** AGENTS.md + **both** ERD copies (canonical and
>   `_REVISED`, kept byte-identical) updated (§3.7/§3.16/§6). Full suite green **278 passed / 1163
>   assertions**; Pint + `php -l` clean; `migrate:status` 0 Pending on `db_capstone_test` and
>   `db_capstone`. **Live `db_capstone` migrated** after a fresh verified backup
>   `...\Temp\opencode\db_capstone_backup_cc_20260926_170736.sql` (36,778 B / 24 tables): the 2
>   `scion_receivings` rows became 2 `stock_ins` scion rows (`stock_ins` 2 → 4; `viable == quantity`)
>   and the table was dropped. **Still open (client-gated):** scion-receipt Edit/Archive/Details,
>   traceability to graft/regraft (no balance deduction) and the freshness/viability rule.
> - **▶ Session CB (DONE — Scion Receiving "Add" modal + backing route/controller/validation; commit `6cd5d75`) — RETIRED in Session CC (merged into Stock In):**
>   built the first slice of the deferred **Scion Receiving CRUD UI** (the `scion_receivings` / `ScionReceiving`
>   entity was schema + model only since Session BV). Added `App\Http\Controllers\ScionReceivingController`
>   (`index` lists receivings newest-first, paginated 10, eager-loads variety + nullable supplier; `store`
>   creates + flashes "Scion receiving recorded successfully."), `App\Http\Requests\StoreScionReceivingRequest`
>   (error bag `scion_receiving`, rules from `ScionReceiving::rules()`, **field-specific messages** e.g.
>   "Quantity must be greater than zero."), the route group **`/operational/scion-receivings`** (GET index +
>   POST store) gated by `permission:manage_inventory`, a sidebar link (**Operational → Scion Receiving**), and
>   `resources/views/operational/scion-receivings/index.blade.php` (log table: Date / Scion Variety / Supplier
>   or "—" / Quantity / Unit Price or "—" / Origin or "—"; **no balance or amount column**; empty state "No
>   scion receiving records found."; Add modal mirroring the Suppliers `addSupplierModal` markup). **Scope kept
>   deliberately narrow:** Add-only — **no** Edit/Archive/Details modals, **no** grafting/regrafting traceability
>   link, **no** freshness/viability field/warning/blocking. **Verified:** throwaway HTTP test (then deleted)
>   confirmed GET 200, create + success flash, per-field validation messages, zero-quantity message, and 403
>   without `manage_inventory`; full MySQL suite **280 passed / 1170 assertions**; `pint --test` passed;
>   `php -l` clean; `route:list` **71 routes**; grep/git diff confirm only the 3 new files + `routes/web.php`
>   (2 routes) + `sidebar.blade.php` (1 link) changed. **No schema/DB change** (test DB only).
>   **Still deferred (client-gated):** Scion Receiving **Edit/Archive/Details** modals and the
>   **traceability link** to Regraft/Replanting (see `AGENTS.md` §"P6 deferred items").
> - **▶ Session CA (DONE — `db_capstone` data policy EXECUTED; wipe + reseed, 2026-09-26):** the P6
>   recommendation was **executed**, not just recommended. **Backup first** (before any change):
>   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_backup_2026_09_26_20260926_154153.sql`
>   — 47,596 bytes, 24 tables / 16 INSERTs, `--single-transaction`, verified; left untouched outside
>   the git repo. Then `php artisan migrate:fresh --seed --force` against **connection `mysql` /
>   database `db_capstone`** (the one explicitly approved destructive action on the live DB) using the
>   P6 `DatabaseSeeder` + `ActivityLogSeeder`. **Before → after:** batches 9→3, stock_outs 7→1,
>   stock_out_details 7→1, mortality_records 17→3, replanting_records 13→3, stock_ins 8→2,
>   scion_receivings 0→2. **The pre-refactor anomaly is gone** (no batch with
>   `available_count > viable_count`; old B005 had 600 > 100). Fresh KPIs: **Active Inventory 395 /
>   Crude Mortality 11.11% / Regraft Deficit 16 / Gap 50**; Dashboard `/`, Operational Analytics,
>   Stock Ledger, Gap Report and Matrix all **200**; `migrate:status` 0 Pending. Standing rule
>   unchanged (tests only against `db_capstone_test`; no further destructive action without fresh
>   sign-off). Full detail: `AGENTS.md` §"`db_capstone` data policy — EXECUTED".
> - **▶ Session BZ (DONE — P6 seeders/tests/docs/verify; commit checkpoints below):** migrated the
>   whole test suite to the revised model and made it green. **Deleted** the five legacy tests for
>   removed entities (`BatchMonitoringTest`, `VarietyTest`, `PotTypeTest`, `ScionTest`,
>   `StockReversalTest`). **Rewrote** `AnalyticsTest` (corrected Gap = `effectiveMortalityQuantity()`,
>   full current-model setup; 51 tests) and every old-model feature test (Batch/Inventory/Mortality/
>   Replanting/StockIn/StockOut/ActivityLog/Dashboard/Supplier/Client/UserManagement). **Added
>   factories** for `ScionVariety`/`Batch`/`MortalityRecord`/`ReplantingRecord`/`StockIn`/`StockOut`/
>   `StockOutDetail`/`ScionReceiving`/`Inventory`/`Supplier`/`Client` (with `HasFactory` on those
>   models) and **rewrote `DatabaseSeeder` + `ActivityLogSeeder`** to the current schema (idempotent;
>   B001 carries genuine mortality + regraft; seeded Stock Out respects `available_count`). **Added
>   Part D tests:** `BatchRelationshipsTest`, `ScionReceivingTest`, `CumulativeEventRecordsTest`,
>   `StockValidationTest`, `StructuralIndependenceTest`, `GapReportTest`. **Verified:** full MySQL
>   suite **280 passed / 1170 assertions**, Pint + `php -l` clean, `migrate:status` 0 Pending,
>   seeders clean + idempotent on `db_capstone_test`, no removed-entity references left in `tests/`.
>   **Docs:** P6-deferred items + the `db_capstone` data-policy recommendation were added to
>   `AGENTS.md` and this handoff. (The recommendation was later **executed in Session CA** — see above.)
> - **P0–P6 DONE. Prior commits: BY ERD terminology fix (`9bd3c8a`), BX regraft_records consolidation
>   (`22ef695`) + its docs sync (`bff2c26`).** Prior: BS Gap fix (`1744975`), BT display rename
>   (`bd47433`), BV Scion_Receiving (`33ff6b0`), **CB Scion Receiving "Add" UI (`6cd5d75`)**. Two
>   untracked `docs/*.csv` sample exports remain.
> - **▶ Session BY (DONE — handoff refresh + ERD user-facing terminology; doc-only):** renamed the last
>   two user-facing "Replanting" labels to **"Regraft"** in `docs/CacaoTrack_ERD.md` + `_REVISED.md`
>   (§7 sidebar list, §8 Design Principle) to match the executed display rename (`bd47433`).
>   **`Replanting_Record`/`replanting_records` + §3.11 stay as-is** (internal names). Both ERD files
>   kept byte-identical (SHA-256 match). Committed `9bd3c8a` (2 files, +4/−4). **No code/DB/migration
>   change; no test suite run.**
> - **▶ Session BX (DONE — the `regraft_records` consolidation APPLIED; code + migration, commit
>   `22ef695`):** migration `2026_09_26_000002_drop_regraft_records_table` **dropped `regraft_records`**
>   (it held **0 rows** — clean drop, no data migration; the create migration `2026_09_25_000009` is
>   kept as history); `app/Models/RegraftRecord.php` deleted; `Batch::regraftRecords()` and the
>   `AnalyticsMetrics` import removed; and `AnalyticsMetrics::stockLedgerGrid()` now reads
>   **`replanting_records`** (grouped by `replanting_date` and the batch's `scion_variety_id`, the same
>   pattern as the Mortality column). **Regraft and Replanting are the same recorded procedure.** Live
>   proof: `Schema::hasTable('regraft_records')` = **false**; the Stock Ledger's grand Re Graft now reads
>   **195** (= `replanting_records` **13 rows**, `SUM(quantity)` **195**) across Criollo 35 / Trinitario
>   15 / Forastero 15 / Diaman 110 / Batangas 10 / White Flag 10 (was **0** before). `php -l` + Pint
>   clean; `migrate:status` 0 Pending. **No test suite run (P6).**
> - **▶ Session BW (historical — docs sync; superseded by BX):** it verified the consolidation had
>   **never** been applied. BX now **applies** it.
> - **▶ Session BV (DONE — additive schema + model, commit `33ff6b0`):** added the **`Scion_Receiving`**
>   entity — table `scion_receivings` + `App\Models\ScionReceiving` (ERD §3.16). **Schema + model ONLY:**
>   no controller/route/view, **no stock-balance column on `scion_varieties`**, and
>   `RegraftRecord`/`ReplantingRecord` untouched. Confirmed fields: `scion_variety_id` (required FK),
>   `supplier_id` (**nullable** FK), `quantity`, `unit_price` (nullable), `date_received`, `origin`
>   (**single nullable** column), `notes` (nullable). **The freshness/viability rule is deliberately
>   NOT implemented** (no `usable_until`, no warning, no blocking) pending client confirmation.
>   Migration `2026_09_26_000001` ran (`migrate:status` 0 Pending); Pint + `php -l` clean; validation
>   lives in `ScionReceiving::rules()`. **No test suite run (P6).**
> - **Session BT (DONE — display rename, commit `bd47433`):** every user-visible "Replanting" is now
>   "Regraft" (display-only; internal names stay `Replanting`). Dashboard KPIs identical — Inventory
>   **1130** / Crude Mortality **40.00%** / Regraft Deficit **385**. **No DB/schema/data change.**
> - **Session BU (historical — read-only audit; RESOLVED in BX):** it found the "consolidation fix"
>   had never been applied (`regraft_records` present with 0 rows; no drop migration; Stock Ledger
>   reading the empty table). Session BX then **applied** it (see above). **No further action.**
> - **Still open (non-blocking):** scion receiving is now the Stock In form's **"Scion" tab**
>   (Session CC — `Scion_Receiving` was merged into `stock_ins`; no standalone entity/page). Unbuilt
>   (client-gated): scion-receipt **Edit/Archive/Details** and the **traceability link** from
>   graft/regraft to a scion-inclusive Stock In row; the freshness/viability rule; and the Stock Ledger
>   "Ready to Plant" best-guess mapping. **There are no approved additive exceptions**
>   (`scion_receivings` merged into `stock_ins`; `regraft_records` retracted).
> - **▶ NEXT UP / where to pick up (vibecoding resume point):** **Phases 7A/7B/7C/7D are DONE**, the
>   **previously-pending Sessions CL–CR are committed** (`b939aab` baseline), **CT** shipped the first
>   two Session CS **Tier 1** items — (a) Stock Out is gated on the centralized
>   **`Batch::survivingQuantity()`** cap (renamed from `regraftableQuantity()`), so dead plants can no
>   longer be released, and (b) the read-only **`php artisan cacao:health`** invariant check + tests —
>   **CU** pointed the Stock Out form's client-side `data-available` hint at
>   `survivingQuantity()` as well (so the hint agrees with the server gate), **CV** reseeded live
>   `db_capstone` under the corrected `total_grafted` rule (verified backup; `cacao:health` now passes;
>   all 20 sidebar pages 200), **CW** switched the three cross-batch `total_grafted` totals to
>   `SUM(total_grafted - regraft)`, **CX** added the `cacao:health` rule that reconciles
>   `available_count` against Stock Out history, and **CY (latest)** added the "counts each plant once"
>   help note on those totals (view/PDF/CSV) and re-reseeded live `db_capstone` (manual B004 removed;
>   `cacao:health` clean). See the CY/CX/CW/CV/CU/CT bullets at the very top.
>   **Remaining Tier 1
>   (safe, code-only) items from Session CS:** log
>   Inventory snapshots to `Activity_Log`; fix the "Total **Active** Inventory includes archived"
>   label; point `phpunit.xml` at MySQL `db_capstone_test`. Prior
>   context: the **client-confirmed `total_grafted` cumulative rule** (Session CJ, commit `1a7ce78`)
>   with the **mortality-rate denominator follow-up** (Session CK, commit `4006b41`), the **Mortality
>   cause-of-death dropdown + "Others" description** (CL), the **Root-Cause Matrix `Others` roll-up**
>   (CM), the **Stock In Seedlings/Scion Type column + filter** (CN), the **Seasonality Heatmap
>   axis-label restyle** (CO, view-only), the **Stock Ledger printable PDF font auto-fit** (CP), the
>   **Stock Out unit dropdown → Pots only** (CQ; centralized `StockOutDetail::UNITS`) and the
>   **`replanting_records` → `regraft_records` rename** (CR).
>   **Full MySQL suite green: 344 passed / 1506 assertions** on `db_capstone_test` (CW +1 test, CX +1,
>   CY +7 assertions). Latest commits: `6f41a6a` (CY docs), `b451b9c` (CY code), `89905c9` (CX),
>   `f9bbd1e` (CW), `0a9251b` (CV docs), `13e658e` (CU), `7ec2f8c` (CT Fix 2), `dccd154` (CT Fix 1),
>   `b939aab` (CL–CR baseline), `4006b41` (CK).
>   Current source of truth: canonical `docs/CacaoTrack_PRD.md` + `docs/CacaoTrack_ERD.md` (v2.0; the
>   `_UPDATED` files are the untouched v1.0 provenance; the `_REVISED` copies must stay byte-identical —
>   SHA-256 match). Pick one of:
>   - **A. Re-align live `db_capstone` to the corrected rule — ✅ DONE in Session CV (2026-09-30).**
>     `migrate:fresh --seed --force` ran against `db_capstone` after a verified backup
>     (`...\Temp\opencode\db_capstone_pre_reseed_20260930_034319.sql`, 37,510 B); `cacao:health` now
>     **passes** and the seeded family is B001 500/400/100 · B001-R1 100/50/30 · B001-R1-R1 30/30/0 ·
>     B002 150/150/0 · B003 100/100/0. Live rows no longer violate `available_count ≤ total_grafted −
>     regraft`.
>   - **B. Report-total follow-ups flagged by Session CK (no client decision needed).** Decide whether
>     `AnalyticsMetrics::batchSummary()` `totals.total_grafted`, `AnalyticsMetrics::gapReport()`
>     `totalExpected`, and `InventoryController::index()` `totalGrafted` should also switch to
>     `SUM(total_grafted − regraft)`. They were left as-is because they are displayed column/context
>     totals of the per-batch cumulative value (changing only the footer breaks column-sum consistency);
>     see the Session CK bullet at the top.
>   - **C. Confirm the remaining Open Decisions with the client** (PRD §19, `OD-1`…`OD-14`).
>     **Already CONFIRMED:** the `total_grafted` clauses of `OD-4`, `OD-6`, `OD-10` (Sessions CJ/CK).
>     **Still open** (each `[PROPOSED — pending client confirmation]` — do not implement unprompted):
>     `OD-3` (`Available_Count` definition + Gap), `OD-5` (corrections policy), `OD-2` (mortality
>     `Plant_Condition`), the Regraft Deficit formula part of `OD-10`, and the `OD-6` code-format
>     details / `OD-12` counter / ERD §3.11 reading.
>   - **D. Optional quality-of-life:** enable `pdo_sqlite` in `php.ini` so plain `php artisan test`
>     works without the MySQL env override (see §5).
>   - **E. Other client-gated feature work (do not build unprompted):** scion-receipt
>     **Edit/Archive/Details**; scion-receipt **traceability** (graft/regraft referencing a
>     scion-inclusive `Stock_In` row; **no** stock-balance deduction); the scion freshness/viability
>     rule (`OD-13`); Stock Ledger "Ready to Plant" mapping (**leave as `available_count`**). See §6
>     and `AGENTS.md` §"P6 deferred items".
>   - **F. Verify current state first** before touching anything (command below).
>   - **G. Session CS audit follow-ups (see the CS bullet at the top).** **Tier 1 (safe, code-only):**
>     ~~gate Stock Out on a single centralized surviving-stock cap~~ (**DONE — Session CT, Fix 1**:
>     renamed `Batch::regraftableQuantity()` → `Batch::survivingQuantity()`); ~~add a read-only KPI
>     health-check command + invariant tests~~ (**DONE — Session CT, Fix 2**: `php artisan
>     cacao:health` + `CacaoHealthTest`); **remaining:** log Inventory snapshots to `Activity_Log`,
>     fix the "Total **Active** Inventory includes archived" label, point `phpunit.xml` at MySQL
>     `db_capstone_test`. **Tier 2 (needs an owner/client decision):** whether mortality should
>     decrement `available_count`, Active-vs-archived basis, the Regraft Deficit formula, and the
>     date-filtered rate definition. **Tier 3 (needs an approved ERD revision):** DB unique index for
>     one monthly inventory snapshot per batch, Stock In↔Batch traceability (`OD-8`), scion freshness
>     (`OD-13`). Do **not** implement a Tier-2/3 item without sign-off.
> - **Standing rules:** tests only against `db_capstone_test` (never `db_capstone`). Six live-DB
>   actions have been explicitly approved: the Session CA wipe + reseed (2026-09-26), the Phase 7D
>   upgrade + reseed (2026-09-29), the **Session CR rename migration** (2026-09-30, non-destructive),
>   the **Session CV reseed** under the corrected arithmetic (2026-09-30, destructive), the
>   **Session CY reseed** (2026-10-01, destructive; manual B004 removed) and the **Session DB reseed**
>   (2026-10-01, destructive; B002-R1 drift removed). Any **further** destructive
>   or schema action needs fresh explicit sign-off.
>   Keep these backups untouched:
>   `...\Temp\opencode\db_capstone_backup_2026_09_26_20260926_154153.sql`,
>   `...\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql`,
>   `...\Temp\opencode\db_capstone_pre_regraft_rename_20260930.sql`,
>   `...\Temp\opencode\db_capstone_pre_reseed_20260930_034319.sql`,
>   `...\Temp\opencode\db_capstone_pre_reseed_20261001_213846.sql`,
>   `...\Temp\opencode\db_capstone_pre_cleared_20261001_235027.sql` (Session DD) and
>   `...\Temp\opencode\db_capstone_pre_wipe_20261002_201903.sql` (Session DI).
> - **Verify quickly:** `$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`
>   (never point tests at `db_capstone`) — expect **399 passed / 1909 assertions** (Session DT). Also run
>   `php artisan cacao:health` after a `migrate:fresh --seed` on `db_capstone_test` — it must pass
>   clean (exit 0; it now also reconciles `available_count` against Stock Out history). Check the
>   checkpoint with `git log --oneline -5` (head `49351ce`, then `808ac38`, `3d69285`, `839ecc2`,
>   `fe973b1`). Full detail: the Session DT/DS/DR/DQ/DP/DO/DN/DM/DL/DK/DJ/DI/DH/DG/DF/DE bullets in the banner
>   above plus the Session DB/DA/CY/CX/CW/CV/CU/CT/CS/CR/CQ/CP/CO/CN/CM/CL/CK/CJ entries and the Session CH
>   entry in §2, plus §3, §5 and §6 (and the earlier CG/CA/BZ/BY/BX/BV/BU/BT/BS/BR/BQ entries).

Paste this file into a new chat to continue. It captures the exact state of the
project after **Session DT (2026-10-12)**: live `db_capstone` holds **owner-entered operational
data** — **5 batches** (B001, B001-R1, B001-R1-R1, B001-R2, B002; the owner re-entered the flows after
the Session DI re-wipe) with stock_ins 2 · mortality_records 2 · regraft_records 3 · stock_outs 0 ·
inventories 0 · activity_logs 9; master data preserved: users 1 · scion_varieties 5 · suppliers 3 ·
clients 3; **`cacao:health` PASSES all 5 invariants** — do **NOT** reseed/truncate without owner
sign-off. Session DT added the read-only **Mortality Causes** breakdown to the Inventory View modal
(`MortalityRecord::causeBreakdownByBatch()` — one grouped query, no N+1; raw `Others: <description>`
kept; rendered with `textContent`). Session DS centralized the auto-apply filter behavior once in
`app.js` (`form[data-auto-filter]`: 400 ms search debounce, immediate dropdown/date changes, Enter
submits) and
reused the same Bootstrap filter card on `/inventory`, `/inventory/stock-in`, `/inventory/stock-out` and
`/analytics/stock-ledger` — the Apply/funnel button is removed everywhere and only **Clear Filters**
remains; page-specific fields (Supplier / Client / Batch / Scion Variety / dates) stay per page.
(`StoreBatchRequest` still validates `Batch::storeRules()`; the Add Batch form's system-assigned
**Batch Code** (next free root code; live preview B003) and **Grafted Date** (today) from Session DQ are
unchanged.) The working tree carries the **uncommitted
DI+DJ+DK+DL+DM+DN+DO+DP+DQ+DR+DS+DT** changes (head `49351ce`), and the suite is **399 passed / 1909
assertions** on `db_capstone_test`. See the top banner (Session DT) + "NEXT UP" for the resume point;
historical context follows. **Session DB (2026-10-01)**, earlier, was the one approved
destructive reseed of live `db_capstone`
(verified backup `db_capstone_pre_reseed_20261001_213846.sql`, 37,202 B; `migrate:fresh --seed --force`,
exit 0; `cacao:health` PASSED on 5 batches / all 5 rules; all 20 sidebar pages 200). Its seeded family
was B001 Batangas 500/400/100 · B001-R1 Toledo 100/50/30 · B001-R1-R1 Criollo 30/30/0 · B002 Trinitario
150/150/0 · B003 Forastero 100/100/0, KPIs 730 / 7.33% (55/750) / 25 — **superseded by the Session DI
wipe**. **Session CY (2026-10-01)**,
immediately before, added a "counts each plant once" help note on the three
cross-batch `total_grafted` totals (Stock Ledger nested summary, Gap Report Expected, Inventory Total
Grafted card; view/PDF/CSV; commits `b451b9c` + `6f41a6a`) and re-reseeded live `db_capstone` with that code
(verified backup; manual B004 removed; `cacao:health` clean). Session CX (2026-09-30) added the
`cacao:health` `available_count` vs Stock Out reconciliation (rule 5, commit `89905c9`), and three
cross-batch `total_grafted` totals use `SUM(total_grafted - regraft)` (commit `f9bbd1e`), on top of the
Session CV live-DB reseed. Immediately before, **Session CU (2026-09-30)** was the **Stock Out form hint fix**: the
batch dropdown's `data-available` attribute and inline `(Available: …)` label now read
**`Batch::survivingQuantity()`** (`resources/views/inventory/stock-out/index.blade.php`) instead of raw
`available_count`, so what the user sees matches what `StockOutController` enforces (per-line and
per-batch). View-only + one new view test (`StockOutTest::test_stock_out_form_hint_matches_the_surviving_quantity_for_a_batch_with_mortality`,
a mortality-bearing batch: raw 500 vs surviving 100); no schema/validation change; commit `13e658e`.
Full suite **342/1484**; Pint + `php -l` + `view:cache` clean; `db_capstone` untouched. This closed
the CT follow-up. **Before CU, Session CT (commits `dccd154` + `7ec2f8c`) shipped the two Session CS
Tier-1 fixes** — (1) Stock Out is gated on the centralized **`Batch::survivingQuantity()`** cap
(renamed from `regraftableQuantity()`), so plants already recorded as mortality can no longer be
released; and (2) the read-only **`php artisan cacao:health`** invariant command + tests — on top of
`b939aab`, which committed the previously-pending **Sessions CL–CR** (owner
choice). Full suite **341/1478**; `cacao:health` clean on `db_capstone_test`; `db_capstone`
untouched. **Before CT, the latest code change was Session CR** — the owner-directed rename of the
`replanting_records` entity to **`regraft_records`**: table + PK `regraft_id` + date `regraft_date`,
model `RegraftRecord`,
`Batch::regraftRecords()`/`totalRegrafted()`/`regraftDeficit()`, `RegraftController` at
`/operational/regraft`, view `operational/regraft`, `StoreRegraftRecordRequest` (error bag `regraft`),
`RegraftRecordFactory`, and stored activity type → `regraft_recorded`; migration
`2026_09_30_000001_rename_replanting_records_to_regraft_records_table` (reversible). Canonical
`docs/CacaoTrack_PRD.md` + `docs/CacaoTrack_ERD.md` updated and `_REVISED` re-synced byte-identical
(SHA-256); `_UPDATED` provenance untouched; `AGENTS.md` gained a RENAME banner; **live `db_capstone`
migrated** after an approved action (backup `db_capstone_pre_regraft_rename_20260930.sql`; all key
pages 200). Suite **338/1465**; Pint + `php -l` + `view:cache` clean. This rename **intentionally
diverges from the client-approved v1.0 ERD**, which named the entity `Replanting_Record`. Immediately
before, Session CQ was the **Stock Out per-line Unit dropdown → Pots only** (centralized
`App\Models\StockOutDetail::UNITS = ['pots']`, so the form select, `StoreStockOutRequest` and
`StockOutDetail::rules()` changed together; **no schema/data/migration change** — legacy live rows
keep their historical `seedlings` value, read-only; suite **338/1468**). Immediately before,
Session CP was a **presentation-only** fix to the Cacao Tree Stock Ledger **printable PDF**
(`app/Support/StockLedgerPdf.php` only): the per-Scion-Variety headers and value cells (and the nested
Batch summary) now **auto-fit** their cells via a new `fitText()` helper (shrinks the font, then
truncates at a 4.2pt floor), fixing labels such as `Batangas`/`Forastero` that overflowed the 7.84mm
columns — **no schema/data/query/view/test change** (76 overflowing labels → 0; full suite
**337/1465**; Pint + `php -l` clean). Before that, Session CO was a
**view-only** pass on the Mortality Seasonality Heatmap (`/analytics/historical/seasonality`): the
"Scion Variety" y-axis title is a horizontal **blue note at the top-left** (still rows = Scion
Variety, columns = Month), the temporary "Rows/Columns" header caption was removed, and the "Month"
x-axis title matches the same blue/bold style — **no schema/data/query change, no test change**.
Before that,
Session CN added a **Type** column/filter
(**Seedlings** vs **Scion**) to the `/inventory/stock-in` receiving log, derived from
`StockIn::entryType()` (`isScionEntry()`) with **no schema change** (ERD §3.7 still has no
discriminator column). Immediately before, Session CM made the Mortality Root-Cause Matrix treat
**`Others` as a single cause category**: every `Others: <description>` row collapses into one `Others`
column (the description is only a specification), centralized in `MortalityRecord::normalizeCause()`
and applied to the matrix page, PDF and CSV (**no schema change**), with 3 new tests. Immediately
before, Session CL turned the Mortality Tracking `cause` into a fixed **"Cause of Death" dropdown**
(choices centralized in `MortalityRecord::CAUSES`) and added a required **Description** when **Others**
is picked (folded into the single `cause` column as `Others: <description>`; **no schema change**),
with 3 new tests; before that, Session CK — a
follow-up to CJ that corrected the **Crude Mortality
Rate denominator** to `SUM(total_grafted − regraft)` (raw `SUM(total_grafted)` double-counted regrafted
plants). In CJ the client **confirmed that `total_grafted` is a
cumulative, non-decreasing log** (spreadsheet `RU-Summary.jpg`; *"Grafted minus Regraft = mortality or
yield for the parent batch"*): `App\Actions\RegraftBatch` stopped decrementing the
source `total_grafted` (only `available_count` and `regraft` change), and
`Batch::remainingMortalityCapacity()` / `Batch::regraftableQuantity()` now bound against
`(total_grafted − regraft)`; the PRD/ERD `OD-4`/`OD-6`/`OD-10` Total_Grafted clauses are now
**CONFIRMED**. The full MySQL suite is **338 passed / 1468 assertions** against `db_capstone_test`
(332/1437 after Session CL, which added 3 Mortality tests; Session CM added 3 more; Session CN added
2 Stock-In type tests; Session CQ added 1 Stock Out unit test).
**`db_capstone` was deliberately NOT reseeded** in Session CJ — it held the Phase 7D seed under
the old arithmetic, so live rows did not satisfy `available_count ≤ total_grafted − regraft`; a
fresh wipe + reseed needed fresh explicit sign-off. **⚠ SUPERSEDED 2026-09-30 (Session CV): the live DB
was reseeded under the corrected rule and `cacao:health` now passes.** The substantive prior state is unchanged from
**Session CH**: **Phases 7A, 7B
and 7C, plus the Phase 7D live-DB upgrade + reseed, are done:** the seedling viability
count is removed, the regraft / sub-batch flow (`App\Actions\RegraftBatch`) moves a quantity from a
source batch into a linked sub-batch, and parent batches and their regraft sub-batches are now shown
nested (via `App\Support\BatchTree`) across the Batches, Inventory, Stock Ledger, Age Profile and Gap
Report pages, with the Stock Ledger Grafted/Regraft/Seedlings sections realigned to the Phase 7B
model. `db_capstone` runs the approved schema and was reseeded (Phase 7D, 2026-09-29; verified
backup `db_capstone_pre_phase7d_20260929_000206.sql`, 35,131 B), and the remaining
provisional defaults (`OD-3` from 7A; the `OD-6` code-format details/`OD-12` and the ERD §3.11 reading
from 7B) await sign-off.** The next session starts from the **"NEXT UP"
bullet in the banner above** and §6. (Historical context follows: Phase 5 (Analytics/reports) was
**implemented in Session BQ**; the Gap formula was corrected in Session BS; the Replanting→Regraft
display rename landed in Session BT; Session BU audited the consolidation; Session BV added the
**`Scion_Receiving`** entity as additive schema + model only; Session BW synced the docs; and Session BY
renamed the last user-facing ERD labels to "Regraft".): the
**analytic/report layer was migrated onto the revised model** —
`App\Support\AnalyticsMetrics` was rewritten (no `Variety`/`adjustments`/`current_quantity`/
`inventory` references), the **Dashboard `/` returns 200**, and the **Stock Ledger is now a monthly
grid** with dynamic per-Scion-Variety columns in both the Blade view and the FPDF. One **P5-approved
schema exception** landed at the time: migration `2026_09_25_000009` created `regraft_records` +
`App\Models\RegraftRecord` + `Batch::regraftRecords()` (Part B) — **retracted in Session BX** (it
duplicated `Replanting_Record`; dropped with 0 rows). All seven analytics pages and all
seven PDF/CSV exports return **200**; `php artisan view:cache` is clean and Pint passes. P5 is committed as its own checkpoint (`d139286`, "feat(analytics):
implement P5 revised-model analytics, dashboard and stock ledger"). See the Session BQ entry in
§2/§5/§6. The refactor is now **P0–P6 DONE**: the DB (P1), models (P2), controllers/routes
(P3), views/sidebar (P4), analytics/reports (P5) **and seeders/tests/factories (P6)** all match the
revised model, and the full MySQL suite is **green**.
One P5 flag remains: Stock Ledger "Ready to Plant" is a flagged best-guess mapping. (The second P5
flag — the Gap formula resolving to 0 — was **corrected in Session BS** to
`Gap = Batch::effectiveMortalityQuantity()`; see the Session BS entry.) Before that, Session BN implemented Phase 3
(Controllers & routes) — the removed controllers deleted, `ScionVarietyController` added, Form
Requests added for every transactional store/update, and `routes/web.php` rewritten (Batches moved
to `/operational/batches`; checkpoint `3d3f9f9`, 66 routes; **no migration/model/view/seeder/test
file touched**; see the Session BN entry in §2/§5/§6). Before that, Session BM implemented Phase 2
(Models) — the six removed old models + `App\Support\ScionMetrics` were deleted, `ScionVariety` +
`StockOutDetail` were added, and `Batch`/`StockIn`/`StockOut`/`Inventory`/`MortalityRecord` were
reworked to the P1 schema with the locked model-layer business rules (see the Session BM entry in
§2/§3/§5/§6). Before that, Session BL finalized the Phase 3 (Controllers & routes) plan and
resolved its five open decisions (planning only; see the Session BL entry in §2/§5/§6). Before that,
Session BK finalized the Phase 2 (Models) plan and resolved its two model decisions (planning only;
see the Session BK entry in §2/§3/§5/§6). Before that, Session BJ
finished Phase 1 (Migrations) — eight reversible migrations `2026_09_25_000001..000008` created
`scion_varieties` + `stock_out_details`, migrated/repointed data, and dropped the six removed
tables/columns (validated up→down→up on a scratch copy; see the Session BJ entry in
§2/§3/§5/§6). Before that, Session BI finished Phase 0
(`db_capstone` backed up, PRD/ERD replaced, `AGENTS.md` rewritten — docs-only). Before that, Session
BH adopted the revised PRD/ERD and locked the refactor plan (see the Session
BH entry in §2/§6). Before that, Session BG made Batch Monitoring a read-only viability
view; Mortality Tracking is the only plant-status writer and deducts the batch's alive count (see
the Session BG entry in §2/§5/§6). Before that, Session BF simplified the Batch Monitoring status
to Pending/Complete with an alive/survival display (see the Session BF entry in §2/§5/§6). Before
that, Session BE was a **verification-only** handoff refresh (see the Session BE entry in §2/§6).
Before that, Session BD refined the Batch Monitoring status logic
(see the Session BD entry in §2/§5/§6). Before that, Session BC built the Batch Monitoring feature
(see the Session BC entry in §2/§3/§5/§6). Before that, Session BB was a **verification-only** handoff
refresh (see the Session BB entry in §2/§6). Session BA built the scion stock pool (FR-14; see below). Before that,
Session AZ was a **verification-only** refresh: no code,
no migration, no DB change. It re-verified the full suite **327 passed / 1337 assertions**,
`vendor\bin\pint --test` clean, `view:cache` clean, **81 routes**, `migrate:status` 0 pending,
and re-read the live `db_capstone` counts — the DB drifted through real UI usage since Session AU
(new batch B007, more mortality/replanting/stock-in rows, `activity_logs` 51 → **57**; see the
Session AZ snapshot in §3). Supersedes Session AZ below.)
Session BA — **Scion stock pool (FR-14)**: scions are no longer named varieties / CRUD master data.
An **approved ERD revision §2.8/§9.10** makes `Scions` a **single generic stock item** (`Scion`)
with a stored `quantity` (the available balance); the Session AX `stock_ins.scion_quantity` capture
(§9.9) is **superseded/retired** (the column is kept for history). The Scions page now shows
**Number of Scions** (+ Re-grafted / Rejected / Scions Received cards) and a **Receive Scions**
action (`POST /inventory/scions/receive`); saving a **replanting record deducts** the used scions
from the batch's scion inside a DB transaction (rejected if insufficient). Migration
`2026_09_21_000002` added `scions.quantity` and consolidated the 4 named scions
(ICS-1/ICS-6/UF-29/Meggy) into one `Scion` row — all 8 batches repointed, starting balance
`max(0, 200 − 160) = 40`; `db_capstone` was backed up first, no `migrate:fresh`. Old scion
add/edit/archive/restore routes were removed. Full suite **315 passed / 1288 assertions**,
`vendor\bin\pint --test` clean, `view:cache` clean, **78 routes**, `migrate:status` 0 pending.
Docs updated: ERD §2.8/§9.9/§9.10, PRD FR-08/FR-14, `AGENTS.md`. Supersedes Session AZ below.)
Session AY — **Total Available Scions now deducts replanting**:
the Scions page card `App\Support\ScionMetrics` now returns
`available = max(0, SUM(stock_ins.scion_quantity) − SUM(replanting_records.quantity))`, so saving a
**replanting record deducts from Total Available Scions** (previously it was the gross total
received). This reverses/refines the Session AX approved ERD §9.9 note; **no new table, no
migration, no data change** — it is a derived formula in the single centralized `ScionMetrics`
(RULE 6). `Rejected Scions` stays an independent card (not subtracted). Full suite
**327 passed / 1337 assertions**, `vendor\bin\pint --test` clean, `view:cache` clean, **81 routes**,
`migrate:status` 0 pending. Supersedes Sessions AX/AW below.)
Session AX — **Scion received-scion capture + Scion stock
cards**: the Stock-In modal now captures the total **scions received** per transaction
(`stock_ins.scion_quantity`, default 0 — **approved ERD revision §2.15/§9.9**, one migration, no
new table), and the Scions page shows **Total Available Scions / Re-grafted Scions / Rejected
Scions**, centralized in `App\Support\ScionMetrics` (RULE 6). Formulas: Total Available =
`SUM(stock_ins.scion_quantity)` (total received — rises on every scion stock-in); Re-grafted
= `SUM(replanting_records.quantity)`; Rejected = effective mortality for the scion's batches
(separate, independent cards). Full
suite **326 passed / 1329 assertions**, `vendor\bin\pint --test` clean, `view:cache` clean,
**81 routes**, `migrate:status` 0 pending. Supersedes Session AW below.)
Session AW — **Mortality Root-Cause Matrix increase alert**:
`/analytics/mortality-matrix` now compares the current live matrix against the most recent
generated Matrix report whose applied filters match the ones selected (the ERD `analytics`
report history, §9.4) and, when a root cause's effective mortality has increased from that
snapshot by **any** amount, shows a warning banner and highlights the offending cause columns,
variety×cause cells and totals, and also raises a **warning toast**. The matrix snapshot was extended to also store per-variety cells +
the applied filters (`AnalyticsMetrics::mortalityMatrixSummary()`), and the comparison is
centralized in `AnalyticsMetrics::mortalityMatrixRegressions()` (RULE 6). The same comparison also
raises a transient **warning toast** on page load (`mortalityIncreaseToast()` →
`session()->now('warning', …)` → `partials/toasts.blade.php`, current-request only). Page-level
(no PDF/CSV change); fires only when a comparable saved report exists. Full suite **319 passed /
1303 assertions**, `vendor\bin\pint --test` clean, `view:cache` clean, **81 routes**, `migrate:status`
0 pending. **No migration, no schema/DB change, no ERD revision** (PRD §9/§13.2, ERD §9.4).
Session AV — **handoff refresh + documentation
consistency**: **no code and no DB change**. Re-verified the full suite **309 passed / 1265
assertions**, `vendor\bin\pint --test` clean, **81 routes**, `migrate:status` 0 pending, and the
live `db_capstone` snapshot is **identical** to the Session AU snapshot below. Documentation only:
fixed the `AGENTS.md` `pa+ge_actions` section-name typo (real name `page_actions`), replaced the
`RULE 6/25` references with `RULE 6 / PRD §25` (the Development Rules end at RULE 14), and clarified
the Staff-permission line (live backfilled value `Staff/2` vs the `Role::defaultPermissionsFor()`
defaults for new accounts); the handoff's own `RULE 6/25` refs were corrected too. Session AU —
**Roles + Role_Permissions merged into
Users (denormalization)**: the `roles` and `role_permissions` tables and `users.role_id`
were dropped and replaced by `users.role` (string) + `users.permissions` (JSON), reducing the
model from 20 to **18 tables** (approved ERD §2.1/§9.1). `RolePermission`/`RoleController`/
`RoleSeeder`, the Users|Roles tab, the `+ New Role` quick-add, and the 4 `users.roles.*` routes
were removed; `App\Models\Role` is now a **code-only catalog helper**. A `db_capstone` backup was
taken and the backfill was verified live (Administrator/7 perms, Manager/6, Staff/2, custom
`farmers(propagator)`/1) **before** the tables were dropped. Full suite **309 passed / 1265
assertions**, `vendor\bin\pint --test` clean, **81 routes**, `migrate:status` 0 pending. **Live
`db_capstone` (UI usage since Session AS):** `users` 5, `batches` 7 (B001–B006 + TAB007), active
inventory **935**, effective mortality **420**, replanting deficit **280**, `activity_logs` **51**,
`analytics` **12**; `archived_at` rows **0**. Session AT — **Daily Checklist guarded delete
(Manager/Administrator only)**: an approved ERD revision (ERD §2.4/§9.6 refinement) allows
permanent deletion of an **archived** task only when it has **no** `Task_Complete` history; a task
with history is refused and must stay archived, and an active task must be archived first. The
action is restricted to the **Manager**/**Administrator** roles (`Role::CHECKLIST_DELETE_ROLES`,
RULE 6; **403** for other roles, button/modal hidden); it writes a `task_deleted` audit row. No new
table/column. `DailyChecklistTest` 23 tests; suite was **324 passed / 1319 assertions** before the
merge. Session AS — **handoff refresh, verification only** (historical): re-verified **317/1291**,
Pint clean, **84 routes**, 0 pending; the live counts then were users 5, `activity_logs` 28, active
inventory 605, crude mortality rate 5.63%, replanting deficit 11, gap 180; `archived_at` rows 0.
Session AR — **Gap Report printable FPDF + Export CSV**:
`/analytics/historical/gap-report` replaced its browser `window.print()` with a **Print Report**
action (button → filter-aware modal → `GET /analytics/historical/gap-report/export` →
`App\Support\GapReportPdf`, a landscape FPDF with the Expected/Actual/Gap strip and per-batch table)
and an **Export CSV** action (`GET /analytics/historical/gap-report/export/csv` →
`AnalyticsController@exportGapCsv`); the page also gained a **Generated Gap Reports** panel. All
read the same `AnalyticsMetrics::gapReport()` data (gap = max(0, viable − inventory); RULE 6/12);
the printable-report filter label is centralized in `AnalyticsController::filterLabel()`. **No
table, no migration** (PRD §9/§14.3, ERD §9.4). Full suite **317 passed / 1291 assertions**, Pint
clean. Session AQ — **handoff refresh, verification only**:
**no code, no migration and no DB change**. Re-verified the full suite **312 passed / 1249
assertions**, `vendor\bin\pint --test` clean, **82 routes**, `migrate:status` 0 pending, and
re-read the live `db_capstone` counts (§3 confirmed unchanged — `analytics` **9**, `activity_logs`
**27**, active inventory **605**, `archived_at` rows **0** across all six master tables). Session
AP — **Operational Analytics printable FPDF + Export
CSV**: `/analytics` gained an **Export CSV** action (`GET /analytics/export/csv` →
`AnalyticsController@exportOperationalCsv`) and a **Print Analytics** action (button → modal →
`GET /analytics/export` → `App\Support\OperationalAnalyticsPdf`, a portrait FPDF with the three
PRD §12 KPI cards and the tabular values behind each chart). Page/PDF/CSV all read the same
centralized `AnalyticsController::operationalData()` (RULE 6/12); both exports sit under
`view_analytics`. **No table, no migration** (PRD §9, ERD §9.4). Full suite **312 passed / 1249
assertions**, Pint clean. Session AO — **handoff refresh, verification only**:
**no code, no migration and no DB change**. Re-verified the full suite **309 passed / 1210
assertions**, `vendor\bin\pint --test` clean, **80 routes**, `migrate:status` 0 pending, and re-read
the live `db_capstone` counts (§3 refreshed — `analytics` is now **9** and `activity_logs` **27**
after real UI generation; active inventory still **605**). Session AN — **Mortality Root-Cause
Matrix printable FPDF + Export CSV**: `/analytics/mortality-matrix` gained a **Print Matrix** action
(button → filter-aware modal → `GET /analytics/mortality-matrix/export` →
`App\Support\MortalityMatrixPdf`, landscape FPDF with the Raw/Adjustments/Effective strip, the
variety × cause matrix and the grand-total row) and an **Export CSV** action (`GET
/analytics/mortality-matrix/export/csv` → `AnalyticsController@exportMatrixCsv`). Both apply the
same validated filters and read the same `AnalyticsMetrics::mortalityMatrix()` data (RULE 6/12);
both are behind `view_analytics`. **No table, no migration** (PRD §9/§13.2, ERD §9.4). Full suite
**309 passed / 1210 assertions**, Pint clean.
Session AM — **Generate Report visible output (Stock Ledger &
Root-Cause Matrix)**: the two pages now render a type-scoped **Generated Reports** panel (period /
generated by / generated at + a **View** modal showing the stored `result_summary` snapshot), so
Generate Report is no longer output-less there. The Stock Ledger snapshot was fixed to store the
real ledger totals (`AnalyticsMetrics::stockLedgerSummary()`: batches/grafted/viable/available/
mortality/replanting) instead of the former gap payload. **No table, no migration** — the ERD
`Analytics` entity (`analytics`) is already the necessary DB (ERD §9.4). Full suite **303 passed /
1169 assertions**, Pint clean. Session AL — **Mortality Root-Cause Matrix: Raw → Adjustments →
Effective consistency**: `/analytics/mortality-matrix` now surfaces the same chain the Mortality
module uses. `AnalyticsMetrics::mortalityMatrix()` keeps its effective cells/totals
(`MortalityRecord::effectiveQuantity()` = raw `quantity` + signed `adjustment_quantity`) and also
returns `rawTotal`/`adjustmentTotal`/`rawTotalsByCause`/`adjustmentTotalsByCause` + per-row
`rawCells`/`adjustmentCells`; the view adds a Raw / Adjustments / Effective summary strip and an
in-cell `base (±adj)` breakdown. **No table, no migration, no ERD revision** (PRD §9/§13.2/§25,
ERD §9.4). Session AK — **Cacao Tree Stock
Ledger printable PDF**: the
`/analytics/stock-ledger` page gained a **Print Ledger** action backed by a new FPDF class
`App\Support\StockLedgerPdf` (`GET /analytics/stock-ledger/export`, `AnalyticsController@exportPdf`).
It renders the same real, filter-aware per-batch ledger plus a totals strip — **no table, no
migration** (PRD §9/§13.1, ERD §9.4). Full suite **296 passed / 1125 assertions**, Pint clean.
Session AJ — **master-data archive/restore**: an approved ERD
revision (ERD §9.8) adds a nullable `archived_at` to `suppliers`, `clients`, `varieties`, `scions`,
`pot_types` and `batches`, and the six Management pages now **archive/restore instead of hard
deleting**. Archived records leave the active listing and the new-record selectors, are rejected as
a target for a **new** operational record, and are restored from an Archived tab; dashboard/analytics
figures are unchanged. Full suite **292 passed / 1100 assertions**, Pint clean;
Session AI — **Daily Checklist print output redesign**: the FPDF
`App\Support\ChecklistPdf` layout was rebuilt (branded header band, summary strip, zebra rows,
colored priority badges, vertically centered cells, colored status, continuation-page header,
cleaner signature block) and the checklist print action was relabelled **"Print Checklist"** — a
PHP/Blade-only refinement with **no schema/DB change**;
Session AH — **Roles & access restrictions build, FR-02**: the
`Roles` + `Role_Permissions` tables, `users.role` normalized into `users.role_id`, Administrator
role management with server-side permission enforcement, and an inline **+ New Role** quick-add;
Session AG — a **handoff refresh + Users/roles verification only**:
**no code or DB change**; Session AF — **reverted the persistent Notifications + Settings
subsystem** in favor of transient session-flash **toast** notifications; Session AE — Daily
Checklist archive/restore + printable PDF
export; Session AD was a handoff refresh, verification only; Session AC was
the Notifications + Settings build, ERD §2.19/§2.1 approved revision (**reverted in Session AF**);
Session AB — handoff refresh,
verification only; Session AA was the
Dashboard build, PRD §12; Session Z was Batch Management build, FR-16; Session Y was handoff refresh,
verification only; Session X was Users Management build; Session W was Profile verification only;
Session V was handoff refresh, verification only; Session U was Profile build; Session T was Activity
History build; Session S was Gap Report verification; Session R was Age Profile verification; Session
Q was Seasonality Heatmap verification; Session P was Root-Cause Matrix verification; Session O was
Stock Ledger verification; Session N was Analytics; Session AN was the Mortality Root-Cause Matrix
printable PDF + CSV export build; Session AO is a handoff refresh, verification only; Session AP is
the Operational Analytics printable PDF + CSV export build; Session AQ is a handoff refresh,
verification only; Session AR is the Gap Report printable PDF + CSV export build; Session AS is a
handoff refresh, verification only).

**Session AJ replaced hard delete with archive/restore on all six Management pages:** an approved
ERD revision (ERD §9.8) adds a nullable `archived_at` to `suppliers`, `clients`, `varieties`,
`scions`, `pot_types` and `batches`. The `DELETE …/{id}` routes became
`POST …/{id}/archive` + `POST …/{id}/restore`; each page has Active/Archived tabs plus a restore
action. Archived records leave the active listing and the new-record selectors and are rejected by
server-side validation for **new** records (`Model::activeExistsRule()` via the `Archivable` trait),
while dashboard/analytics figures stay unchanged and a batch's `inventories` row is now preserved.
The two delete tests per page were replaced by archive/restore tests. **No OPEN DECISIONS.**
Full suite **292/1100**, Pint clean.

**Session AI refined the Daily Checklist print output:** the printable PDF behind
`GET /operational/checklist/export` already worked (Session AE); this session rebuilt its FPDF
layout in `App\Support\ChecklistPdf` (branded green header band, title + date, a summary strip for
Total/Completed/Pending, green table header, zebra rows, colored priority badges, colored status,
vertically centered `#`/Priority/Status, bold task name with grey-italic description, a
continuation-page header and a cleaner signature block) and renamed the UI action to **Print
Checklist**. **No table and no schema change** — the PDF is still generated from the normalized
`activity_tasks` + `task_completes` data (ERD §9.6 / PRD §9). Full suite **277/1006**, Pint clean.

**Session AH built role management:** the fixed `users.role` string was normalized
into a **`Roles` entity + `Role_Permissions`** (approved ERD revision — ERD §2.1/§2.19/§2.20/§9.1/
§9.7, documented before any migration). `Administrator` is a protected system role; Administrators
create/edit/delete non-system roles; every role defines its access restrictions, enforced
server-side via a `permission` middleware; a role assigned to any user cannot be deleted. A
`db_capstone` backup was taken first, and the legacy `role` column was dropped only after the
backfill was explicitly verified. The Add/Edit User forms also offer an inline **+ New Role**
quick-add (`POST /users/roles` → `RoleController@storeQuick`) that creates a role without leaving
the form and returns with it preselected. Full suite **277 passed / 1006 assertions**, Pint clean.

**Latest (Session BI — Phase 0, docs-only):** the revised 15-entity `docs/CacaoTrack_PRD.md` +
`docs/CacaoTrack_ERD.md` are now the canonical source of truth, and `AGENTS.md` was rewritten with a
**REFACTOR IN PROGRESS** banner (P0 done; P1–P6 pending; the running code is still the OLD 19-table
model). Full suite re-verified **324/1329**, Pint clean, 81 routes, `migrate:status` 0 pending.
**No code, no migration, no DB change.** The feature list below is the **pre-refactor state** that
P1–P6 will migrate.
**Pre-refactor features:** twenty-two features are live with real DB persistence — Daily Checklist, Batch
Foundation, Mortality Tracking, Replanting, Inventory, Stock In, Stock Out, Stock Reversal,
Supplier Management, Client Management, Variety Management, Scion Management, Pot Type Management,
Analytics (Operational + Tabular Deliverables + Historical), Activity History, Profile,
**Users Management (FR-02, Session X)**, **Batch Management (FR-16, Session Z)**, the
**Dashboard (PRD §12, Session AA)**, **access restrictions merged into Users (FR-02, Session AU)**,
the **Daily Checklist print redesign (Session AI)**, **Master-data archive/restore (FR-11–FR-16,
Session AJ)**, the **Daily Checklist guarded delete (Session AT)**, the **Scion stock-in scion
quantity + Scion stock cards (FR-14, Session AX)**, the **scion stock pool (FR-14, Session BA)**,
and the **Batch Monitoring read-only viability view (FR-18, Sessions BC/BD/BF/BG)** — a derived
`Pending`/`Complete` status plus the current **alive plant count**, now driven solely by **Mortality
Tracking** (Session BG): recording mortality deducts the dead plants from `batches.viable_count`.
The six Management pages now
archive/restore instead of deleting (ERD §9.8), and the **Stock Ledger has a printable FPDF report**
(Session AK; Print Ledger). **Session AU merged the `roles` + `role_permissions` tables into `users`**
(`role` string + `permissions` JSON → 18 source tables; ERD §2.1/§9.1) and removed the separate Roles
tab, `RoleController`, `RoleSeeder`, `RolePermission` model and the `users.roles.*` routes.
**Session BC added the approved `Batch_Monitoring` entity (19th table, ERD §9.11) — retained for
history, no longer written since Session BG.**
**Session AF removed** the persistent
Notifications/Settings subsystem; action feedback is now a **transient session-flash toast** (no
table). The Dashboard was the last static page; no static closure remains in
`routes/web.php`, and there is no longer a navbar bell or Settings link (no mock navbar control
remains).
**Refreshed (Session AG — verification only; superseded by Session AH below):** a request to
add/"create new roles" on the `/users` page was assessed. The ERD (§9.1) stored `Role` inside
`Users` and required an explicit ERD revision for a separate `Roles` table, so at that time **no
table was added** and the decision was to **keep only the 3 fixed roles** (`User::ROLES`). No code,
migration, or data change. (Session AH then approved the ERD revision and implemented `Roles` +
`Role_Permissions`.)
**Changed (Session AF):** the navbar notification bell, the `notifications` table,
`users.settings`, and the `/notifications` + `/settings` pages were **removed** (reversing the
Session AC approved ERD revision). User feedback is now a **Bootstrap toast** rendered globally
from the existing `session('success')` / `session('error')` flashes
(`resources/views/partials/toasts.blade.php`, included by `layouts/app.blade.php`, auto-shown in
`resources/js/app.js`). No table/migration for notifications; ERD §9.5 records the reversal.
**Built (Session AE):** the Daily Checklist no longer hard-deletes tasks — Delete was replaced by
**archive/restore** (approved ERD revision adds nullable `activity_tasks.archived_at`; ERD
§2.4/§9.6), and a **printable PDF** export (setasign/FPDF, `App\Support\ChecklistPdf`) renders the
day's real saved tasks/completions from `activity_tasks` + `task_completes`. No new table.
**Built then reverted (Session AC → removed in Session AF):** the navbar **notification bell** and
**Settings** link were converted from mocks into real, protected features. An **approved ERD
revision** added the `Notifications` entity
(ERD §2.19) and the `Users.Settings` JSON attribute (ERD §2.1), both recorded in
`docs/CacaoTrack_ERD.md` before the migrations. New `Notification` model (single centralized writer
`Notification::record()`, RULE 6), `NotificationController` (page + mark-one/mark-all with
per-user authorization), `SettingsController` (own notification preferences), `AppServiceProvider`
view composer for the navbar, real Blade views, and 25 new tests. **This whole subsystem was
removed in Session AF** — see the Session AF section (§2) and the ERD §9.5 reversal note. The
description here is retained only as history; nothing in it is live any more.
**Built (Session AA):** the **Dashboard** (`/`, PRD §12/§24) was converted from a fully static
mockup into a real, protected feature. New `DashboardController@index` reuses the existing
centralized `App\Support\AnalyticsMetrics` methods + `ActivityLog` (RULE 5/6/12); the hardcoded KPI
cards (`12,450` / `8.4%` / `320`), fake ECharts datasets and the fake Recent Activities list were
replaced with real DB data and PRD §16 empty states. `/` moved off its closure and is now under the
`auth` middleware (FR-01). **No new table/migration** — the ERD has no Dashboard entity and PRD §9
forbids redundant reporting tables. `ExampleTest` updated for the auth-protected `/`; new
`DashboardTest` (7 tests).
**Built (Session Z):** the Management → **Batches** page (`/inventory/batches`, FR-16) was built as
a greenfield full-stack feature (no page/routes/controller existed) against the existing `batches`
table. **No new table and no migration** — the ERD `Batch` entity §2.6 already exists. New
`BatchController` (index/store/update/destroy), `Batch::STATUSES`/`rules()`/
`hasOperationalRecords()`, a real Blade view, 4 routes under `auth`, a sidebar Management link,
and 19 tests. Creating a batch auto-creates its single `inventories` row via
`Inventory::syncFromBatch()`; mutations write `batch_created`/`batch_updated`/`batch_deleted`
activity rows. `db_capstone` data untouched.
**Built (Session U):** the Account → **Profile** page (`/profile`, ERD `Users` / PRD §7 Account)
was converted from an unauthenticated static mockup into a working own-account feature. No new
table was created (the ERD has no Profile entity — Profile is the signed-in user's own `Users`
row). New migration adds the ERD-faithful `role` column to `users` (ERD §2.1; default `Staff`,
admin backfilled to `Administrator`; display-only on Profile, never self-editable),
`ProfileController` (`index`/`update`/`updatePassword`, all under `auth`), real view with Edit
Profile + Change Password modals, `profile_updated` activity type written through
`ActivityLog::record()`, navbar Profile link wired to the real route, and 13 tests.
**Built (Session T):** the Account → **Activity History** page (`/activity`, FR-03/FR-17/PRD §24)
was converted from a static mockup into a working read-only audit feature. New ERD-faithful table
`activity_logs` (`Activity_Log`: `activity_id` PK, `user_id` FK, nullable `batch_id` FK,
`activity_type`, `description`, `activity_date`), `ActivityLog` model with a single centralized
writer `ActivityLog::record()`, `ActivityController@index`, filterable/paginated real view, a
backfill seeder, and 16 tests. Existing operations now write activity rows (checklist completion,
mortality store/adjust, replanting store, stock in/out/reversal inside their DB transactions, and
analytics report generation). The `/activity` route is now under `auth` (it was an unauthenticated
static closure).
**Verified (Session S):** the Historical Gap Report (`/analytics/historical/gap-report`) was
confirmed **already functional** in this root — no backend or DB work was needed, and **no new DB
table is appropriate** (expected = `Batch.Viable_Count`, actual = `Inventory.Current_Quantity`, gap
= `max(0, expected − actual)` per PRD §9/§14.3/§26 and ERD §9.4). The static/mock version belongs
to the stale `Capstone\capstone` copy; serve commands from the real root (see Session S).
**Verified (Session Q):** the Mortality Seasonality Heatmap
(`/analytics/historical/seasonality`) was confirmed **already functional** in this root — no backend
or DB work was needed, and **no new DB table is appropriate** (it is generated from normalized
mortality data per PRD §9/§26 and ERD §9.4). The static/mock version belongs to the stale
`Capstone\capstone` copy; serve commands from the real root (see Session Q).
**Verified (Session R):** the Historical Age Profile (`/analytics/historical/age-profile`) was
confirmed **already functional** in this root — no backend or DB work was needed, and **no new DB
table is appropriate** (age is derived from `Batch.Grafted_Date` and the reporting date per PRD
§9/§14.2/§26 and ERD §9.4). The static/mock version belongs to the stale `Capstone\capstone` copy;
serve commands from the real root (see Session R).
**Verified (Session P):** the Mortality Root-Cause Matrix (`/analytics/mortality-matrix`) was
confirmed **already functional** in this root — no backend or DB work was needed. The static version
belongs to the stale `Capstone\capstone` copy; serve commands from the real root (see Session P).
**Verified (Session O):** the Stock Ledger was confirmed **already functional** in this root — no
backend or DB work was needed. The static version belongs to the stale `Capstone\capstone` copy;
serve commands from the real root (see Session O).
**Verified (Session W):** the Account → **Profile** page (`/profile`) was confirmed **already
functional** in this root — no backend or DB work was needed, and **no new DB table is
appropriate** (Profile is the signed-in user's own `users` row per ERD §2.1; the `role` column
already exists per Session U). The static/mock version belongs to the stale
`Capstone\capstone` copy; serve commands from the real root (see Session W).
**Latest (Session AZ — verification only):** no code, no migration, no DB change. Full suite
**327/1337**, Pint clean, `view:cache` clean, **81 routes**, `migrate:status` 0 pending. Live
`db_capstone` re-read (§3 Session AZ snapshot): users 5, batches **8** (new B007), mortality
records **11** (raw 460 / adj −5 / effective **455**), replanting records **8** (sum 160),
stock-ins **6**, inventories **8** (sum **1035**), `analytics` 12, `activity_logs` **57**,
scion-received sum 200, **0 archived rows** on all six master tables (1 archived checklist task).
**Session AY:** `App\Support\ScionMetrics` now returns
`available = max(0, SUM(stock_ins.scion_quantity) − SUM(replanting_records.quantity))`, so the
Scions page **Total Available Scions** card **deducts replanting** as soon as a replanting record is
saved (floor 0). This reverses/refines the Session AX ERD §9.9 note; **no new table, no migration,
no data change** (derived formula, RULE 6). `Rejected Scions` remains independent. Full suite
**327/1337**, Pint clean.
**Session AX:** the Stock-In modal captures the **total scions received**
(`stock_ins.scion_quantity`, default 0 — approved ERD revision §2.15/§9.9; one migration, no new
table), and the Scions page (`/inventory/scions`, FR-14) shows three cards — **Total Available
Scions**, **Re-grafted Scions** (replanting) and **Rejected Scions** (effective
mortality) — centralized in `App\Support\ScionMetrics` (RULE 6; see §2 Session AX). Full suite
**326/1329**, Pint clean.
**Session AW added:** the **Mortality Root-Cause Matrix (PRD §13.2) mortality-increase alert** —
it compares the live matrix against the most recent generated Matrix report with matching
filters and raises a warning **banner + warning toast** and highlights the offending cause/cell/
total whenever a cause's effective mortality increased by any amount (see §2 Session AW).
**Session BH — revised PRD/ERD adopted; refactor plan locked (planning only):** the revised
`docs/CacaoTrack_PRD_REVISED.md` + `docs/CacaoTrack_ERD_REVISED.md` were adopted (15-entity model;
removes Batch Monitoring, Variety, Pot Type, generic Scions, Mortality_Adjustments, Stock_Reversal).
Six decisions are locked and a 6-phase plan was written — **no code/DB change in BH**. See the Session
BH entry in §2 and the top bullet in §6.
**Session BI — Phase 0 of the refactor (DOCS-ONLY, DONE):** `db_capstone` was backed up
(`...\Temp\opencode\db_capstone_pre_revised_refactor_20260925.sql`, 27 tables, verified); the
**canonical** `docs/CacaoTrack_PRD.md` + `docs/CacaoTrack_ERD.md` were **replaced with the revised
15-entity content** (byte-identical to the retained `*_REVISED.md` copies — canonical files are
authoritative); and `AGENTS.md` was **rewritten** to the revised target with a **REFACTOR IN
PROGRESS** banner. **No code, no migration, no DB change** (P1–P6 not started; the app still runs the
OLD 19-table model). Full suite re-verified **324/1329**, Pint clean, `view:cache` clean, **81
routes**, `migrate:status` 0 pending. See the Session BI entry in §2/§3/§5/§6.
**Next up:** **implement Phase 3 (Controllers & routes) of the revised-PRD/ERD refactor** — the P3
  plan and its five decisions are finalized (Session BL); **P0, P1 and P2 are done** (Sessions
  BI/BJ/BM). The DB has the revised schema and the models now match it, but the
  controllers/routes/views/seeders/tests/`AnalyticsMetrics` are still the OLD model, so the running
  app is temporarily inconsistent and the test suite will fail until P3–P6 land. **P2 (DONE, Session
  BM):** added
`ScionVariety`/`StockOutDetail`; rework `Batch`/`StockIn`/`StockOut`/`Inventory`/`MortalityRecord`
(replanting unchanged); delete `BatchMonitoring`/`Variety`/`PotType`/`Scion`/`MortalityAdjustment`/
`StockReversal`/`ScionMetrics`; fix the model-level `Analytic::rules()` + `ActivityLog::TYPES`
references. **No new table** — only Eloquent classes for the two tables P1 already created. The
pre-refactor features
already live: no mockup page, no route closure, and no mock navbar control remain —
**Master-data archive/restore (Session AJ)** replaced hard delete on all six Management pages,
**Notifications + Settings were removed (Session AF)** in favor of transient toast feedback,
**access restrictions were merged into Users (Session AU)**, joining the **Daily Checklist
archive/restore + printable PDF (FR-03, Session AE)**, the **Daily Checklist guarded delete
(Session AT)**, the **Scion stock-in scion quantity + Scion cards (FR-14, Session AX/AY)**, the
**Dashboard (PRD §12, Session
AA)**, **Batches (FR-16, Session Z)**,
Users Management (FR-02, Session X), Profile (Session U,
re-verified Session W), Activity History (FR-03, Session T) and Analytics (Session N, re-verified
O–S). Remaining **optional** work: enable `pdo_sqlite` so the default `php artisan test` works.
**No OPEN DECISIONS remain for the OLD model** (all historical revisions resolved): the ERD
`Analytics` entity is report-generation history (ERD §9.4),
`Inventory.Batch_ID` UNIQUE is honored (ERD §9.3), the `Stock_Reversal` transaction FK is two
nullable FKs (ERD §9.2), `Users.Role` + `Users.Permissions` exist (merged, ERD §2.1/§9.1), the notifications/Settings
revision was **reversed** (toasts need no table — ERD §9.5), `Activity_Task.Priority` +
`Activity_Task.Archived_At` (ERD §2.4/§9.6) are approved revisions, **master-data
`Archived_At` on `supplier`/`client`/`variety`/`scions`/`pot_type`/`batch` (ERD §9.8)** is an
approved revision, and **`Stock_In.Scion_Quantity` (ERD §2.15/§9.9)** is an approved revision
(the Scion cards derive from it); **Session AY refined the Total Available Scions formula** to
`max(0, received − re-grafted)` so replanting deducts from it — all with the docs and migrations in
sync. **The Session BH refactor's pre-P1 open decisions are now RESOLVED (Session BJ)** — (a) monthly
`inventories` rows are written by an explicit **"Record Monthly Inventory"** action (stored
snapshots); (b) `stock_out_details.unit` is a **fixed list** `seedlings`/`pieces`/`bags`; (c)
`stock_ins.origin` is NOT NULL with legacy `''`. **P1 migrations are DONE** (see Session BJ in
§2/§3/§5/§6).

## Quick start for the next session
- **▶ Current quick start (latest: Session EE, 2026-10-28 — role permissions + Daily Checklist workflow + friendly UI):**
  **(1)** The permission catalog now has 10 keys (adds `manage_checklist` + `complete_checklist`) with
  descriptions; defaults live in `Role::DEFAULT_PERMISSIONS_BY_ROLE` (provisional). **(2)** Staff can
  complete checklist tasks but not manage/verify; Managers manage and verify but cannot complete;
  completion and verification are separate (`verified_by_id`/`verified_at` start null; the new
  `operational.checklist.completions.verify` route records Manager verification). **(3)** Inventory view
  allows `record_operations|manage_inventory|manage_stock`; recording a snapshot requires
  `record_operations`. Inline Supplier/Client creation now requires `manage_inventory`. **(4)** `/users`
  is `manage_users`-only and has role templates, descriptions and Apply role defaults. **(5)** Friendly
  403/404 pages, permission-driven sidebar and Dashboard Quick Actions (analytics/activity hidden without
  the matching permission). **(6)** Tests: `phpunit.xml` forces `db_capstone_test` and a TestCase guard
  blocks any other DB; full suite **428 passed / 2213 assertions**. **Live SQL is proposed only — NOT
  EXECUTED** (see §5a). Existing stored permissions were not changed.
- **▶ Previous quick start (Session EC, 2026-10-20 — Analytics KPI colors swapped + Operational sidebar links hidden):**
  **(1)** `/analytics` KPI cards now read **Total Grafted = green** (`border-success`/`text-success`)
  and **Total Active Inventory = blue** (`border-primary`/`text-primary`); Crude Mortality Rate stays
  red. Presentation-only — same live `AnalyticsMetrics` values, labels and PDF/CSV exports.
  **(2)** The Operational sidebar no longer shows **Mortality Tracking**, **Regraft** or **Batches**
  (only Daily Checklist + Scion Variety remain). **The pages/functions still exist** — open them by
  URL (`/operational/mortality`, `/operational/regraft`, `/operational/batches`) or via the Inventory
  row actions; no route/controller/permission changed. Owner-directed divergence from PRD §5 / ERD §7
  recorded in `AGENTS.md`.
  **(3) Files:** `resources/views/analytics/index.blade.php`,
  `resources/views/layouts/sidebar.blade.php`, `AGENTS.md`, `session.handoff.md`. No migration, schema,
  route, permission, calculation or `db_capstone` write.
  **(4) Verified:** `php artisan view:cache` clean; focused suites
  `MortalityTest|BatchManagementTest|RegraftTest` **46 passed / 176 assertions** on
  `db_capstone_test` (full suite not re-run — last full baseline **408 / 2001** at EB; Blade-only
  change). The cumulative DI…EC working-tree files remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's vibecoding/manual flow-testing (NEXT UP 0b) — check
  `/analytics` for the green/blue cards, then re-check the Dashboard (3 KPI cards), the Stock Ledger
  (ascending; no Remaining Available), `/inventory` sub-batch toggles and `cacao:health`. Commit/push
  only when the owner asks.
- **▶ Previous quick start (Session EB, 2026-10-19 — Inventory collapsible sub-batch toggle, same as Batches):**
  **(1)** `/inventory` now has the same chevron **Toggle sub-batches** control as `/operational/batches`:
  a parent row collapses/expands its whole regraft subtree, while childless nested rows keep the `↳`
  indent. `InventoryController@index` adds `ancestors` + `has_children` to `nodeMeta` from the single
  `BatchTree::flatten()` traversal (no extra queries), and every nested `<tr>` carries
  `collapse show child-of-{ancestorId}`. Filters, newest-first ordering, summary cards, row actions,
  modals and pagination are unchanged.
  **(2) Files:** `app/Http/Controllers/InventoryController.php`,
  `resources/views/inventory/index.blade.php`, `tests/Feature/{InventoryTest,NestedBatchesTest}.php`,
  `AGENTS.md`, `session.handoff.md`. No migration, schema, route, permission, calculation or
  `db_capstone` write.
  **(3) Verified:** full MySQL suite **408 passed / 2001 assertions** on `db_capstone_test`;
  `vendor\bin\pint --test` + `php artisan view:cache` clean. The cumulative DI…EB working-tree files
  remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's vibecoding/manual flow-testing (NEXT UP 0b) — start on
  `/inventory` by expanding/collapsing the **B001** family, then re-check the Dashboard (3 KPI cards),
  `/analytics`, the Stock Ledger (ascending; no Remaining Available) and `cacao:health`. Nothing is
  committed since `49351ce`; commit/push only when the owner asks.
- **▶ Previous quick start (Session EA, 2026-10-12 — Stock Ledger "Remaining Available" removed; CSV export in the client's monthly-block layout):**
  **(1)** `/analytics/stock-ledger` no longer shows the **Remaining Available** column on the page, the
  printable PDF or the CSV; the CSV export now opens as the client's manual ledger — one block per month
  (month title, grouped section header + per-Scion-Variety sub-header rows, events, month TOTAL), then
  GRAND TOTAL, the footnote and the nested Batch summary. The running balance is still computed and
  reconciled internally as an integrity guard.
  **(2) Files:** `AnalyticsController` (CSV rebuild + helper methods), `StockLedgerPdf` (column removed;
  section groups widened to 39.2mm), `AnalyticsMetrics` (docblock), `analytics/stock-ledger.blade.php`,
  `StockLedgerNestingTest` (+layout locks), `StockLedgerRunningBalanceTest` (docblock). No migration,
  schema, route, permission or `db_capstone` write.
  **(3) Verified:** full MySQL suite **407 passed / 1994 assertions** on `db_capstone_test`;
  `vendor\bin\pint --test` + `php artisan view:cache` clean; read-only live render (page/PDF/CSV) shows
  no "Remaining Available". The cumulative working-tree files remain **uncommitted** (head `49351ce`).
- **▶ Previous quick start (Session DZ, 2026-10-12 — Dashboard Total Active Inventory card + Analytics Total Grafted card):**
  **(1)** `/` now shows **three** KPI cards — Total Grafted (**853** live), **Total Active Inventory** (new,
  **813** live, `border-primary`, "Plants currently available") and Crude Mortality Rate — and `/analytics`
  mirrors it with **Total Grafted** (new, **853**), Total Active Inventory (**813**) and Crude Mortality Rate
  (`col-md-4` each). Both cards read the centralized `AnalyticsMetrics` definitions (RULE 6) — never
  hardcoded; the Operational Analytics **PDF/CSV** exports now also carry **Total Grafted** (the PDF KPI
  strip renders 4 boxes, labels fit).
  **(2) Files:** `DashboardController`, `AnalyticsController`, `OperationalAnalyticsPdf` (docblock),
  `dashboard/index.blade.php`, `analytics/index.blade.php`, `DashboardTest`, `AnalyticsTest`. No migration,
  schema, route, permission or `db_capstone` write.
  **(3) Verified:** full MySQL suite **407 passed / 1984 assertions** on `db_capstone_test`; `vendor\bin\pint`
  + `php artisan view:cache` clean; read-only live render through both controllers — Dashboard **813**,
  Analytics **853**. The cumulative DI…DZ working-tree files remain **uncommitted** (head `49351ce`).
- **▶ Previous quick start (Session DW, 2026-10-12 — Daily Checklist History tab):**
  **(1)** `/operational/checklist` now has a **Today's Checklist | History** segmented tab pair. The History
  tab lists one read-only record per day with stored completions (date, staff, Completed/Pending badge,
  x/y tasks) with auto-applying Search / From-To / Status filters + Clear Filters, and shows the selected
  record's tasks, remarks, staff, verifier + time, **Print** (existing date PDF) and **View Full Details**
  (read-only modal) buttons.
  **(2) Files:** new `app/Support/ChecklistHistory.php`, `DailyChecklistController`, the checklist view,
  `resources/css/app.css`, `DailyChecklistTest` (+6). No migration, schema, route, permission or
  `db_capstone` write.
  **(3) Verified:** full MySQL suite **406 passed / 1960 assertions** (was 400/1900 at DV); `vendor\bin\pint`
  + `php artisan view:cache` clean; `npm run build` re-run; read-only live check — 1 record (2026-10-02,
  pending 1/4, Admin User), page 200, `cacao:health` PASSED. The cumulative DI…DW working-tree files remain
  **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — complete a few tasks
  and revisit the History tab — or start the remaining Session CS Tier-1 items.
- **▶ Previous quick start (Session DV, 2026-10-12 — Archived tabs removed; Archive → guarded Delete):**
  **(1)** On `/operational/checklist`, `/operational/batches` and `/operational/scion-varieties` the
  **Archived tab is gone** and the row **Archive** action is now **Delete**, which permanently removes
  the record. Delete is **server-guarded** (RULE 6): checklist tasks with completion history,
  batches with any mortality/regraft/stock-out/inventory/activity records, and referenced scion
  varieties are refused with a clear message; rows whose Delete would be refused show a disabled
  button with the blocker list in its tooltip.
  **(2) Files:** `ActivityTask`/`Batch`/`ScionVariety` (guarded delete rules), `ActivityLog`
  (`scion_variety_deleted`), the three controllers (`archive`/`restore` → `destroy`),
  `routes/web.php`, the three views + `batches/partials/batch-row.blade.php`, the tests (incl. new
  `ScionVarietyManagementTest`). No migration/schema change.
  **(3) Verified:** full MySQL suite **400 passed / 1900 assertions** on `db_capstone_test`;
  `vendor\bin\pint --test` + `php artisan view:cache` clean; live cleanup of the 3 archived
  unreferenced scion varieties done (fresh backup
  `...\Temp\opencode\db_capstone_pre_scion_delete_20261012_175232.sql`, 34,928 B; live `cacao:health`
  PASSED). The cumulative DI…DV working-tree files remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — add a batch and
  try Delete (blocked once it has records), or start the remaining Session CS Tier-1 items.
- **▶ Previous quick start (Session DT, 2026-10-12 — Inventory View modal shows the recorded mortality causes):**
  **(1)** On `/inventory`, the read-only **View** modal gained a **Mortality Causes** section: each batch's
  `mortality_records` are grouped by recorded cause (quantity-ordered; `Others: <description>` kept
  verbatim) through the new centralized `MortalityRecord::causeBreakdownByBatch()` — one grouped query for
  the whole filtered set (no N+1) — and rendered client-side with `textContent`. A batch with no deaths
  shows "No mortality recorded for this batch."
  **(2) Files:** `app/Models/MortalityRecord.php`, `app/Http/Controllers/InventoryController.php`
  (`mortality_causes` in `summaries`), `resources/views/inventory/index.blade.php` (View modal section +
  JS), `tests/Feature/InventoryTest.php` (+1, 1 updated). No migration, schema, route, permission or
  `db_capstone` write.
  **(3) Verified:** full MySQL suite **399 passed / 1909 assertions** on `db_capstone_test`;
  `vendor\bin\pint --test` + `php artisan view:cache` clean; read-only live check (`B001-R2` Disease 110,
  `B002` "Others: Over-irrigation" 13). The cumulative DI+…+DT working-tree files remain **uncommitted**
  (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — click View on a batch
  with mortality and confirm the causes — or start the remaining Session CS Tier-1 items.
- **▶ Previous quick start (Session DS, 2026-10-04 — one auto-filter pattern on Inventory / Stock In / Stock Out / Stock Ledger):**
  **(1)** The auto-apply filter behavior now lives **once** in `resources/js/app.js`
  (`form[data-auto-filter]`: 400 ms search debounce, immediate dropdown/date changes, Enter submits,
  double-submit guard). `/inventory` uses it (inline copy removed) and `/inventory/stock-in`,
  `/inventory/stock-out` and `/analytics/stock-ledger` now reuse the same Bootstrap filter card —
  `card-body py-3`, `row g-3 align-items-end`, small labels + `form-select-sm`/`form-control-sm`,
  `input-group-sm` search — with only **Clear Filters** (the Apply/funnel button removed). Supplier stays
  on Stock In; Client/Batch stay on Stock Out; the ledger keeps Batch / Scion Variety / Date From / To.
  **(2) Files:** `resources/js/app.js`, `inventory/index.blade.php`, `inventory/stock-in/index.blade.php`,
  `inventory/stock-out/index.blade.php`, `analytics/stock-ledger.blade.php`, rebuilt `public/build`
  assets, `StockInTest`/`StockOutTest`/`AnalyticsTest` (+1 each), `InventoryTest` (asserts
  `data-auto-filter`). No migration, schema, controller, route, permission or `db_capstone` change.
  **(3) Verified:** full MySQL suite **398 passed / 1898 assertions** on `db_capstone_test`;
  `vendor\bin\pint --test` + `php artisan view:cache` clean; `npm run build` re-run. The cumulative
  DI+DJ+DK+DL+DM+DN+DO+DP+DQ+DR+DS working-tree files remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — change a filter on
  any of the four pages and watch it apply without a button — or start the remaining Session CS Tier-1
  items.
- **▶ Previous quick start (Session DR, 2026-10-04 — Inventory filters apply automatically server-side):**
  **(1)** `/inventory`'s filter bar no longer has an Apply/Filter button; the form (`#inventoryFilterForm`)
  auto-submits the same server-side GET — text search debounces **400 ms**, the dropdowns and batch-year
  submit immediately on change, Enter submits at once — and only **Clear Filters** remains.
  `InventoryController@index` is untouched; the table and the five filter-aware summary cards refresh
  together.
  **(2) Files:** `resources/views/inventory/index.blade.php` (form + `@push('scripts')` auto-submit),
  `InventoryTest` (+1). No migration, schema, controller, route, permission or `db_capstone` change.
  **(3) Verified:** full MySQL suite **395 passed / 1873 assertions** on `db_capstone_test`;
  `vendor\bin\pint --test` + `php artisan view:cache` clean. The cumulative
  DI+DJ+DK+DL+DM+DN+DO+DP+DQ+DR working-tree files remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — type in the
  Search Batch field or pick a filter and watch the table + five cards refresh without pressing a
  button — or start the remaining Session CS Tier-1 items.
- **▶ Previous quick start (Session DQ, 2026-10-04 — Add Batch form auto-assigns Batch Code + Grafted Date):**
  **(1)** The Add Batch form (`/inventory` and the identical `/operational/batches` modal) no longer lets
  users type/pick **Batch Code** or **Grafted Date**: both render read-only with the note "Assigned
  automatically …". The server assigns `Batch::nextBatchCode()` (next sequential root code; live preview
  **B003**) and `now()->toDateString()` inside the create transaction; `StoreBatchRequest` validates
  `Batch::storeRules()` (scion variety / total grafted / status) and ignores any submitted
  `batch_code`/`grafted_date`.
  **(2) Files:** `Batch` (`nextBatchCode()` + `storeRules()`), `BatchController` (store + `nextBatchCode`
  view data), `InventoryController` (view data), `StoreBatchRequest`, both Add Batch modals,
  `InventoryTest` (+1, 2 updated), `BatchManagementTest` (+2, 2 replaced). No migration, schema, route,
  permission or `db_capstone` change.
  **(3) Verified:** full MySQL suite **394 passed / 1867 assertions** on `db_capstone_test`; Pint + `php -l`
  + `view:cache` clean; read-only live `nextBatchCode()` = **B003**. The cumulative
  DI+DJ+DK+DL+DM+DN+DO+DP+DQ working-tree files remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — add a batch from
  `/inventory` and watch the auto code/date — or start the remaining Session CS Tier-1 items.
- **▶ Previous quick start (Session DP, 2026-10-03 — Inventory summary cards):**
  **(1)** `/inventory` now has five filter-aware summary cards replacing the old Total Grafted card:
  **Total Mortality** (effective recorded mortality — strictly incrementing, PRD §2.5/§11), **Regraft
  Deficit** (`Batch::regraftDeficit()` = max(0, mortality − regraft) — the working OD-10 definition, still
  pending client confirmation), **Total Regrafted** (the cached `batches.regraft` counter, reconciled
  against `regraft_records`), **Total Active Inventory** (`SUM(available_count)` of ACTIVE batches only —
  the `AnalyticsMetrics::totalActiveInventory()` definition, archived excluded) and **Total Batches**
  (filtered batch count). Cards follow the page filters (search / scion variety / lifecycle status / batch
  month-year) and aggregate the same live per-batch values the rows show (RULE 5/6).
  **(2) Files:** `InventoryController` (five view values from the existing `$summaries`, no new queries;
  old `totalGrafted` view var dropped), `resources/views/inventory/index.blade.php` (responsive 5-card
  row), `InventoryTest` (+2, page test updated), `SeededFamilyKpiTest` (page `totalGrafted` assertion
  removed, +1 seeded card test). No migration, schema, route, permission or `db_capstone` change.
  **(3) Verified:** full MySQL suite **392 passed / 1855 assertions** on `db_capstone_test`; Pint +
  `php -l` + `view:cache` clean; read-only live check on the owner's 5 batches — every card matched an
  independent live query exactly (mortality **123**, deficit **123**, regrafted **270**, active inventory
  **853**, batches **5**).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — watch the five
  cards update after each recorded Mortality/Regraft/Stock Out — or start the remaining Session CS
  Tier-1 items. The cumulative **DI+DJ+DK+DL+DM+DN+DO+DP** working-tree files remain **uncommitted**
  (head `49351ce`).
- **▶ Previous quick start (Session DO, 2026-10-03 — Inventory Status column + `[View] [More ▾]` action; "Date:" labels removed):**
  **(1)** `/inventory` drops the `Date:` prefixes (values kept) and adds the derived **STATUS** column —
  **Growing** (`grafted_date` < 21 days) / **Ready** (21 days reached) / **Completed** (`available_count = 0`)
  / **Cancelled/Void** (stored `inactive` or archived) — centralized in `Batch::lifecycleStatus()`
  (RULE 6; the stored `batches.status` enum stays `active`/`inactive`; OD-7 cycle is derived). The Status
  filter now filters those same values via `Batch::scopeWhereLifecycleStatus()`.
  **(2)** The row action is `[View] [More ▾]`: **View** opens the read-only `#viewBatchModal` (every viewer,
  archived included); **More** groups **Regraft** (disabled when nothing to regraft), **Record Mortality**
  and **Record Deficit** — the same forms/routes/validation as the operational pages; the Deficit review
  stays read-only and permission-free. The standalone Deficit/Mortality/Regraft buttons are gone.
  **(3) Files:** `Batch`, `InventoryController`, `resources/views/inventory/index.blade.php`,
  `resources/css/app.css` (`.badge-lifecycle-completed`) + rebuilt assets, `tests/Feature/InventoryTest.php`
  (updated + lifecycle/View/More tests). No migration, schema, route, permission or `db_capstone` change.
  **(4) Verified:** full MySQL suite **389 passed / 1831 assertions** on `db_capstone_test`; Pint + `php -l`
  + `view:cache` clean; read-only live `/inventory` smoke **200** (Status/View/More render, 0 `Date:` labels).
  The cumulative **DI+DJ+DK+DL+DM+DN+DO** working-tree files remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — the live
  `db_capstone` now holds 5 owner-entered batches (B001, B001-R1, B001-R1-R1, B001-R2, B002) and
  `cacao:health` passes all 5 invariants — or start the remaining Session CS Tier-1 items.
- **▶ Previous quick start (Session DN, 2026-10-03 — Inventory table values merged with their dates into single columns; nested-row badge renamed "Regrafted"):**
  **(1)** `/inventory` rendered **BATCH DETAILS** (batch code with `Date: …` below — the label later removed
  in Session DO), **TOTAL GRAFTED** (quantity with its grafted date below) and **REGRAFT** (counter with the
  latest regraft record's date below, `—` when none). `InventoryController@index` gained one eager aggregate
  (`withMax('regraftRecordsAsParent as latest_regraft_date', 'regraft_date')`, no N+1) exposed in `summaries`.
  **(2)** The nested-row **Sub-batch** badge now reads **Regrafted** (Inventory page only). **(3) Verified:**
  full MySQL suite **385 passed / 1797 assertions** at the time; the files stayed uncommitted into
  Session DO.
- **▶ Previous quick start (Session DM, 2026-10-03 — Inventory row Mortality/Regraft actions open the same forms as the operational pages in modals, prefilled with the clicked batch):**
  **(1)** `/inventory` row **Mortality** / **Regraft** buttons now open `#recordMortalityModal` /
  `#recordRegraftModal` — the same forms as the operational pages — and post to the shared
  `operational.mortality.records.store` / `operational.regraft.records.store` routes (error bags
  `mortality` / `regraft`; JS reopens the right modal on failure and the success redirects stay on the
  operational pages). **(2)** `InventoryController@index` now passes the modal dropdown data
  (`quantityBreakdown()` for Mortality; active regraftable batches via `survivingQuantity()` + active scion
  varieties for Regraft), and the row's Regraft button is only offered when `survivingQuantity() > 0`
  (otherwise a disabled "Nothing to regraft" button). **(3) Files:** `InventoryController`,
  `resources/views/inventory/index.blade.php`, `tests/Feature/InventoryTest.php` (+5 tests). No migration,
  schema, route, permission or `db_capstone` change. **(4) Verified:** full MySQL suite **384 passed / 1789
  assertions** on `db_capstone_test`; Pint + `php -l` + `view:cache` clean. The cumulative DI+DJ+DK+DL+DM
  working-tree files remain **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — record
  Mortality/Regraft straight from the Inventory row — or start the remaining Session CS Tier-1 items.
- **▶ Previous quick start (Session DL, 2026-10-03 — Inventory page gained the Add Batch modal (the same FR-06 form as `/operational/batches`)):**
  **(1)** `/inventory` exposed an **Add Batch** page action (**`record_operations`** users only) that opens
  the same `#addBatchModal` form as `/operational/batches`; it posts to the shared
  `operational.batches.manage.store` route, reopens on validation errors and preserves `old()` input.
  **(2)** The eye/details modal briefly added earlier the same day was reverted. **(3) Files:**
  `resources/views/inventory/index.blade.php`, `tests/Feature/InventoryTest.php` (+3). No migration, schema,
  controller, route, permission or `db_capstone` change. **(4) Verified:** full MySQL suite **379 passed /
  1744 assertions** on `db_capstone_test`; Pint + `php -l` + `view:cache` clean.
- **▶ Previous quick start (Session DK, 2026-10-03 — Inventory row Action gained the read-only Deficit breakdown modal, flagged not yet client-confirmed):**
  **(1) Inventory page (`/inventory`)**: each row's **Action** cell now also shows a **Deficit** button
  (read-only review — available to every viewer, **archived rows included**, and deliberately **not**
  gated on `record_operations`) that opens the shared **`#deficitModal`**. The modal carries the warning
  banner *"Deficit definition not yet confirmed with the client — this is a working calculation for
  internal review only."*, the working formula `Deficit = max(0, Mortality − Regraft)` with the
  substituted numbers, and Batch / Parent / Scion Variety / Total Grafted / Regraft / Mortality /
  Deficit / Available. **No action button and no Regraft shortcut inside** (owner-directed; footer is
  Close only). Values come from the same per-row `data-deficit` payload the page already renders
  (`Batch::regraftDeficit()`, RULE 6) — no new query / no N+1. The per-batch template, the
  Mortality/Regraft action links, their permission gating and the removed "Record Monthly Inventory" UI
  (Session DJ) are all unchanged.
  **(2) Files:** `resources/views/inventory/index.blade.php`, `tests/Feature/InventoryTest.php` (+3
  tests). No migration/controller/route/permission/`db_capstone` change.
  **(3) Verified:** full MySQL suite **376 passed / 1724 assertions** on `db_capstone_test`; Pint +
  `php -l` + `view:cache` clean.
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — use the row
  **Deficit** button for internal review while the deficit definition is still open (Tier-2) — or start
  the remaining Session CS Tier-1 items. The cumulative DI+DJ+DK working-tree files are **uncommitted**
  (head `49351ce`).
- **▶ Previous quick start (Session DJ, 2026-10-03 — Inventory page rebuilt into the per-batch template; row Action links to Mortality/Regraft; "Record Monthly Inventory" UI removed):**
  **(1) Inventory page (`/inventory`)** now lists **one row per batch** (parents + regraft sub-batches,
  nested via `App\Support\BatchTree`) with **BATCH DATE / BATCH / SCION VARIETY / TOTAL GRAFTED / REGRAFT /
  MORTALITY / DEFICIT / AVAILABLE / ACTION** — live values only (`Deficit` = `Batch::regraftDeficit()`,
  `Available` = `available_count`; PRD §2.7/FR-08/§11, ERD §3.8/§3.12). The **"Record Monthly Inventory"**
  button/modal was **removed from the page** (owner); the snapshot writer (`store()` +
  `StoreInventorySnapshotRequest` + `inventory.snapshots.store`) is kept and still tested, but is
  UI-unreachable. **Action** per non-archived row links to the existing flows:
  **Mortality** → `/operational/mortality?batch_id=…` and **Regraft** →
  `/operational/regraft?batch_id=…` (archived rows show `Archived`; without `record_operations` → `—`).
  **(2) `Batch::regraftDeficit()`** gained an optional precomputed-mortality arg so the page reuses one
  eager `withSum` (formula unchanged; same pattern as `survivingQuantity()`).
  **(3) Verified:** full MySQL suite **373 passed / 1707 assertions** on `db_capstone_test`; Pint +
  `php -l` + `view:cache` clean; **no migration/schema/route/permission/`db_capstone` change.** The
  cumulative DI+DJ working-tree files are **uncommitted** (head `49351ce`).
  **Next options:** continue the owner's manual flow-testing (top banner NEXT UP 0b) — use the Inventory
  row Action links to record Mortality/Regraft for the batch — or start the remaining Session CS Tier-1
  items.
- **▶ Previous quick start (Session DI, 2026-10-02 — live `db_capstone` re-wiped; GitHub published; Dashboard KPI = Total Grafted):**
  **(1) Wipe (owner-approved):** the same 8 operational tables were truncated (activity_logs, inventories,
  stock_out_details, stock_outs, regraft_records, mortality_records, batches, stock_ins); backup
  `...\Temp\opencode\db_capstone_pre_wipe_20261002_201903.sql` (37,136 B); master data preserved (users 1,
  scion_varieties 5, suppliers 3, clients 3); `AUTO_INCREMENT` reset to 1; `cacao:health` clean
  ("No batches to check.").
  **(2) GitHub:** public repo **https://github.com/tingalfilepenf-pixel/CacaoTrack**, branch `master`,
  remote `origin`; commit `49351ce` (Sessions DE–DH) pushed; `gh` CLI installed.
  **(3) Dashboard:** the "Total Active Inventory" card is now **"Total Grafted"** =
  `AnalyticsMetrics::totalGrafted()` = `SUM(total_grafted − regraft)` across all batches (seeded 750);
  full MySQL suite **369 passed / 1686 assertions**; Pint + `php -l` clean; `db_capstone` untouched. The
  5 dashboard files are **uncommitted**.
  **Next options:** continue the owner's manual flow-testing on the empty live DB (top banner NEXT UP (0b))
  — check the new Dashboard Total Grafted card after entering batches — or start the remaining Session CS
  Tier-1 items.
- **▶ Previous quick start (Session CY, 2026-10-01 — cross-batch-totals help note + live `db_capstone` reseed):**
  **(1) Help note (commit `b451b9c`):** the Stock Ledger nested Batch summary totals (view/PDF/CSV),
  the Gap Report Expected total (view/PDF/CSV) and the Inventory Total Grafted card now carry a one-line
  note that the cross-batch total counts each plant once (a regrafted plant is not added again under its
  new sub-batch). Display/text only; full suite **344 passed / 1506 assertions**; Pint + `php -l` +
  `view:cache` clean; all 20 sidebar pages 200 via the HTTP kernel. **(2) Reseed:** one owner-approved
  destructive action on `db_capstone` after a verified backup
  (`...\Temp\opencode\db_capstone_pre_reseed_20261001_201512.sql`, 37,218 B / 23 tables, clean
  `Dump completed` trailer) — `migrate:fresh --seed --force` (exit 0); before batches 6 (5 + manual
  B004), after batches **5**; `migrate:status` **0 Pending**; `cacao:health` PASSES both before (6) and
  after (5); KPIs **730 / 7.33% (55/750) / 25**; the three cross-batch totals all read **750**.
  **Next options:** the remaining Session CS Tier-1 items (log Inventory snapshots to `Activity_Log`;
  fix the "Total **Active** Inventory includes archived" label; point `phpunit.xml` at MySQL
  `db_capstone_test`), then confirm the remaining Open Decisions (PRD §19).
- **▶ Previous quick start (Session CX, 2026-09-30 — `cacao:health` reconciles `available_count` vs Stock Out history; three cross-batch `total_grafted` totals fixed; no schema/DB change):**
  Two committed code fixes:
  **(1) `f9bbd1e` — Session CW (cross-batch totals):** `AnalyticsMetrics::batchSummary()['totals']['total_grafted']`,
  `AnalyticsMetrics::gapReport()['totalExpected']` and `InventoryController@index` `totalGrafted`
  now use `SUM(total_grafted - regraft)` (was raw `SUM(total_grafted)`); per-row `total_grafted` stays
  raw. Seeded family reads **750** in all three (was 880).
  **(2) `89905c9` — Session CX (health rule 5):** `php artisan cacao:health` now also checks
  `available_count == (total_grafted - regraft) - SUM(stock_out_details.quantity)` per batch, catching a
  silently altered balance that is still within range.
  **Full MySQL suite green: 344 passed / 1499 assertions** on `db_capstone_test` (was 342/1484);
  `cacao:health` passes on a fresh seed; Pint + `php -l` clean.
  **Next options:** the **remaining Session CS Tier 1** items (log Inventory snapshots to
  `Activity_Log`; fix the "Total **Active** Inventory includes archived" label; point `phpunit.xml` at
  MySQL `db_capstone_test`), then confirm the remaining Open Decisions (PRD §19).
  **Live `db_capstone` drift (a B004 Toledo batch of 50 + a real B001 Stock Out of 30 on 2026-09-29)
  was cleared by the Session CY reseed (2026-10-01)** — the live DB is back on the 5-batch seed
  (7.33% / 730, `cacao:health` clean). The old "6.88% / 750" figure is no longer current.
- **▶ Previous quick start (Session CV, 2026-09-30 — one-time owner-approved `db_capstone` reseed under the corrected `total_grafted` rule; DB + docs only, no code change):**
  `php artisan migrate:fresh --seed --force` ran against connection `mysql` / database `db_capstone`
  after a verified backup
  (`...\Temp\opencode\db_capstone_pre_reseed_20260930_034319.sql`, 37,510 B, 23 tables, clean
  `Dump completed` trailer). **Before** `cacao:health` **FAILED (exit 1)** (B001 400>300, B001-R1
  50>40); **after** `migrate:status` **0 Pending**, `cacao:health` **PASSED (exit 0)**, family
  B001 500/400/100 · B001-R1 100/50/30 · B001-R1-R1 30/30/0 · B002 150/150/0 · B003 100/100/0; KPIs
  **730 / 7.33% (55/750) / 25 / 55**; all **20 sidebar pages 200**; Stock Out hint ==
  `survivingQuantity()` (B001 **375** vs raw 400); Stock Ledger CSV **200**. Tests still run only on
  `db_capstone_test`. **Next options:** the **remaining Tier 1** CS items (log Inventory snapshots to
  `Activity_Log`; fix the "Total **Active** Inventory includes archived" label; point `phpunit.xml` at
  MySQL) or confirm the remaining Open Decisions (PRD §19).
- **▶ Previous quick start (Session CU, 2026-09-30 — Stock Out form hint fix; view-only + test, no schema/DB change):**
  Session CU pointed the Stock Out form's client-side availability hint at the server-enforced cap:
  the batch `<option>`'s `data-available` attribute and inline `(Available: …)` label in
  `resources/views/inventory/stock-out/index.blade.php` now call **`$batch->survivingQuantity()`**
  instead of raw `(int) $batch->available_count`, so the hint (and the pre-submit `validate()`, which
  reads `data-available`) can no longer contradict the `StockOutController` rejection
  (`Quantity exceeds the {n} surviving plants available for {batch_code}.`, per-line `:197` and
  per-batch `:220`). New view test
  `StockOutTest::test_stock_out_form_hint_matches_the_surviving_quantity_for_a_batch_with_mortality`
  (raw 500 vs surviving 100 — deliberately a mortality-bearing batch, not the trivial mortality-0 case).
  **Commit `13e658e`.** Full MySQL suite green **342 passed / 1484 assertions**
  (was 341/1478); `vendor\bin\pint --test` + `php -l` + `view:cache` clean; **`db_capstone` untouched.**
  **Next options at the time:** the remaining Tier 1 CS items, then re-align live `db_capstone` to the
  corrected arithmetic — **✅ done in Session CV (2026-09-30)** — or confirm the remaining Open
  Decisions (PRD §19).
- **▶ Session CT (DONE — Tier-1 fixes; no schema/DB change, 2026-09-30):** Session CT shipped the first
  two **Session CS Tier 1** items: (1) **Stock Out surviving cap** — `Batch::regraftableQuantity()` was
  renamed to **`Batch::survivingQuantity()`** (same formula) and is now the release cap in
  `StockOutController` (per-line + per-batch aggregate), so plants already recorded as mortality can no
  longer be released; and (2) the **read-only `php artisan cacao:health`** invariant command
  (`app/Console/Commands/CacaoHealthCommand.php`) with `tests/Feature/CacaoHealthTest.php`.
  Fix-1 test: `StockOutTest::test_stock_out_is_capped_by_the_surviving_population_not_the_raw_available_balance`.
  **Commits:** `b939aab` (baseline checkpoint of the previously-pending Sessions CL–CR — owner
  choice), `dccd154` (Fix 1), `7ec2f8c` (Fix 2). Full MySQL suite green **341 passed / 1478
  assertions**; `vendor\bin\pint --test` + `php -l` clean; `cacao:health` clean on a freshly seeded
  `db_capstone_test`; **`db_capstone` untouched.** (CU later resolved CT's leftover hint follow-up.)
- **▶ Session CS (DONE — read-only KPI audit; no code/DB change, 2026-09-30):** traced Crude Mortality
  Rate **12.10%** (75/620) and Total Active Inventory **730** and catalogued 7 flaws + three tiers of
  fixes (see the CS bullet in the top banner). Nothing was fixed in CS itself; Session CT then landed
  the first two Tier-1 items.
- **▶ Session CH (DONE — Phase 7D, 2026-09-29):** **Phase 7D is DONE** — the live
  `db_capstone` was upgraded to the approved (Phase 7A) schema and reseeded
  (`php artisan migrate:fresh --seed --force`; the one approved destructive action, after a verified
  **35,131 B** backup at `...\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql`). The four
  `2026_09_27_*` migrations are applied (`migrate:status` **0 Pending**), the removed columns are
  gone, all 20 sidebar pages return **200**, and the seeded family is B001 Batangas 400/400
  (regraft 100) → B001-R1 Toledo 70/50 (regraft 30) → B001-R1-R1 Criollo 30/30, plus B002 150/150
  and B003 100/100. Live KPIs: Total Active Inventory **730**, Crude Mortality Rate **7.33%**,
  Regraft Deficit **25**, Gap **55**. Tests still run only against `db_capstone_test`. **Next
  options:** confirm the Open Decisions with the client (PRD §19), or other client-gated items —
  see the top banner "NEXT UP" and §6.
- **▶ Session CG (DONE — Phase 7C, 2026-09-28; the live-DB note is superseded by Phase 7D):** parent
  batches and regraft sub-batches are shown nested everywhere via the single `App\Support\BatchTree`
  helper, and the Stock Ledger / Age Profile / Gap Report are aligned with the Phase 7B model. Commits
  `7c84843`, `59488db`, `cc89669` (code) + `b780873` (docs). **Full MySQL suite green: 328 passed /
  1406 assertions**; Pint + `php -l` clean; `view:cache` clean.
- **▶ Session CF/CB/BT etc. (historical — superseded by the top banner):** below this bullet are the
  prior sessions' quick-starts, kept for context. Session CB (2026-09-26) live data (B001 180/180,
  B002 140/120, B003 95/95; Active Inventory 395, Crude Mortality 11.11%, Regraft Deficit 16, Gap 50)
  described the **live `db_capstone`** at that time. **That state is superseded:** the live DB was
  upgraded to the approved schema and reseeded in **Phase 7D (2026-09-29)** — see the top banner.
- **Working tree note:** the Phase 7C code + docs are committed (`7c84843`, `59488db`, `cc89669`,
  `b780873`). The Phase 7D work is docs-only and is committed as its own checkpoint (`AGENTS.md` +
  this file). **Uncommitted so far:** Session CL (Mortality cause dropdown — `MortalityController`,
  `StoreMortalityRecordRequest`, `MortalityRecord`, `resources/views/operational/mortality/index.blade.php`,
  `MortalityTest`), Session CM (Root-Cause Matrix `Others` roll-up — `MortalityRecord`,
  `AnalyticsMetrics`, `AnalyticsTest`), Session CN (Stock In Seedlings/Scion type — `StockIn`,
  `StockInController`, `resources/views/inventory/stock-in/index.blade.php`, `StockInTest`),
  Session CP (Stock Ledger PDF font auto-fit — `app/Support/StockLedgerPdf.php`), Session CQ (Stock Out
  unit Pots only — `StockOutDetail`, `StoreStockOutRequest` docblock/validation path,
  `resources/views/inventory/stock-out/index.blade.php`, `DatabaseSeeder`, `AGENTS.md`, `StockOutTest`,
  `StockValidationTest`, `RegraftFlowTest`), and Session CR (rename `replanting_records` →
  `regraft_records` — new `RegraftRecord` model / `RegraftController` / `StoreRegraftRecordRequest` /
  `RegraftRecordFactory`, migration `2026_09_30_000001_*`, view `operational/regraft/`, and edits to
  `Batch`, `ActivityLog`, `AnalyticsMetrics`, `OperationalAnalyticsPdf`, controllers, sidebar/views,
  seeders, tests and docs) plus this handoff. **Untracked** sample exports
  `docs/cacao-operational-analytics-2026-09-25.csv` + `docs/cacao-stock-ledger.csv` remain (leave or
  clean up as desired).
- **▶ Session BS (DONE — Gap formula corrected, commit `1744975`):** `Gap = Batch::effectiveMortalityQuantity()`.
  No DB/schema/data change; `AGENTS.md` + Gap view/PDF/CSV updated. **The prior Gap "Open decision #1"
  is CLOSED.** The only surviving P5 flag is the Stock Ledger "Ready to Plant" best-guess mapping
  (leave as `available_count`). Full detail: Session BS entry in §2/§5/§6.
- **▶ Session BR (historical audit — RESOLVED in Session CA):** it found the live `db_capstone` was
  pre-refactor residue (`available_count` = old ledger, not `viable − stock_out`; `Σviable` 1170 /
  `Σavailable` 1130). **Session CA executed the wipe + reseed** — the residue is gone. No further
  action. Full detail: Session BR entry in §2/§5/§6 + Session CA in the top banner.
- **▶ Refactor status:** P0–P6 are **all DONE** (P6 completed in Session BZ). `db_capstone` has the
  revised schema and the seeders/tests match it, so the suite is green. (Pre-refactor context: the
  pre-P1 backup is `...\Temp\opencode\db_capstone_pre_p1_20260925_142909.sql`; the P5 `regraft_records`
  exception was retracted in Session BX.)
- **P5 (DONE — Session BQ, checkpoint `d139286`; see the Session BQ entry in §2/§5/§6):** fixed the two
  narrow `AnalyticsMetrics` bugs (`totalActiveInventory` → `Batch::totalAvailableQuantity()`; false
  PRD §12.2 citation removed) and removed every other stale `Variety`/`adjustments`/
  `current_quantity`/`inventory` reference; added the **approved `regraft_records`** migration +
  `RegraftRecord` + `Batch::regraftRecords()`; rebuilt the **Stock Ledger as a monthly grid** (dynamic
  per-Scion-Variety columns, view + FPDF); rebuilt remaining pages and PDF/CSV exports; Gap used the
  locked formula. **Two flags (at the time):** the locked Gap formula resolved to **0** (available_count
  already nets Stock Out) — **corrected in Session BS** to `Batch::effectiveMortalityQuantity()` — and
  "Ready to Plant" is a flagged best-guess mapping. **No seeders/tests
  touched (P6).**
- **P4 (DONE — Session BO; see the Session BO entry in §2/§5/§6):** repointed
  `resources/views/layouts/sidebar.blade.php` to the real P3 routes and deleted the Batch Monitoring /
  Variety / Scion / Pot Type / Stock Reversal entries; added
  `resources/views/operational/scion-varieties/index.blade.php`; reworked
  `operational/batches/index.blade.php` (moved from `inventory/batches/`), `inventory/stock-in`,
  `inventory/stock-out` (header + `details[]` multi-line form), `inventory/index` ("Record Monthly
  Inventory" + monthly snapshots) and `operational/mortality` (adjustment UI removed); fixed the
  `->variety` refs in Replanting and Activity History; deleted the removed view folders. **No
  migration/model/seeder/test file touched; three controller edits:** the approved one-line
  `BatchController@index` view path, plus the two flag fixes — `ActivityController@index` now uses
  `batch.scionVariety` (`/activity` returns 200), and `UserController::hasOperationalRecords()` no
  longer queries the dropped `stock_reversals`/`mortality_adjustments` tables. **Remaining flag:**
  `/` (Dashboard) 500s (`AnalyticsMetrics` OLD — P5).
- **P3 (DONE — Session BN, checkpoint `3d3f9f9`; see the Session BN entry in §2/§5/§6):** deleted
  `BatchMonitoringController` / `VarietyController` / `PotTypeController` / `StockReversalController` /
  `ScionController` + `MortalityController@adjust`; added `ScionVarietyController` (CRUD at
  `/operational/scion-varieties`, `manage_inventory`); reworked `StockIn` (no batch, +`viable`/
  `origin`, `viable <= quantity`), `StockOut` (header + `details[]`, per-line AND per-batch aggregate
  checks vs `available_count` inside one transaction, scion variety derived server-side,
  `amount`/`total_amount` computed), `Inventory` (+`store` "Record Monthly Inventory", one snapshot
  per batch per calendar month), `Batch` (moved to `/operational/batches`, `record_operations`; single
  `scion_variety_id`), `Mortality` (record-only, no adjust, cap =
  `remainingMortalityCapacity()`, never mutates `viable_count`), `Replanting` (drop the removed
  Scion-pool deduction). Added Form Requests for every transactional store/update +
  `App\Support\DuplicateSubmissionGuard`. **No new tables; no migration/model/view/seeder/test file
  touched. P3 flags:** (1) inventory snapshot uniqueness is app-layer only (no DB unique index); (2)
  inventory snapshots write **no** Activity_Log row (no `inventory_recorded` type; model edit out of
  scope); (3) snapshot permission = `manage_inventory`; (4) view paths still old — **P4 must repoint
  the sidebar/views to the renamed routes and create
  `resources/views/operational/scion-varieties/index.blade.php`.**
- **NOTE:** the pre-refactor bullets further down this section (Batch Monitoring, the Scion stock
  pool, the Replanting scion deduction, Variety/Pot Type, Stock Reversal, etc.) describe features
  **removed** by the revised model — they are kept as history only and are superseded by P1–P3.
- **Read first:** `AGENTS.md`, then §0–§2 below. ERD is the DB source of truth — do not invent
  tables/columns without an approved ERD revision.
- **Batch Monitoring is read-only and Mortality drives the alive count (Session BG) is DONE** — `/operational/batch-monitoring` (FR-18) shows each batch's current **alive
  plant count** (stored `batches.viable_count`) and a derived `Pending`/`Complete` status from
  `Batch.Monitoring_Start_Date`. The manual **Record Monitoring** update, the **Survival Rate** and
  the observation-based helpers were removed; `Batch_Monitoring` is retained but no longer written.
  **Mortality Tracking is the only plant-status writer**: recording mortality (or a positive
  adjustment) deducts the dead plants from `viable_count` (floored at 0) transactionally via
  `Batch::applyAliveDelta()`; a negative adjustment restores them. `AnalyticsMetrics::survivalVsMortality()`
  no longer double-counts. Full suite **324/1329**, Pint clean, `view:cache` clean, 2
  batch-monitoring routes, `migrate:status` 0 pending. **No migration, no schema/DB change, no new
  table.** (See §2 Session BG / §6.)
- **Total Available Scions deducts replanting (Session AY) is DONE** —
  `App\Support\ScionMetrics` returns `available = max(0, received − re-grafted)` (approved ERD §9.9
  refinement — no new table, no migration), so saving a replanting record deducts from the
  `/inventory/scions` (FR-14) **Total Available Scions** card. `Rejected Scions` stays independent.
  Full suite was **327/1337** at the time.
- **Scion received-scion capture + Scion stock cards (Session AX) is DONE** —
  the Stock-In modal captures the **total scions received** (`stock_ins.scion_quantity`, default 0;
  approved ERD revision §2.15/§9.9 — one migration, no new table), feeding the same Scion cards
  above (see the Session AY formula refinement).
- **Mortality Root-Cause Matrix increase alert (Session AW) is DONE** — the
  `/analytics/mortality-matrix` page compares the live matrix against the most recent generated
  Matrix report with matching filters and shows a **warning banner + warning toast** and highlights
  the offending cause column/cell/totals whenever a cause's effective mortality increased by any
  amount. Baseline = the ERD `Analytics` report history (ERD §9.4); comparison centralized in
  `AnalyticsMetrics::mortalityMatrixRegressions()` (RULE 6), snapshot extended via
  `mortalityMatrixSummary()`. **No table, no migration, no ERD revision.** It only fires once a
  matching Generate Report snapshot exists, and (being stateless) repeats until a new report resets
  the baseline. Full suite **319/1303**, Pint clean.
- **Master-data archive/restore (FR-11–FR-16) is DONE** (see Session AJ below) — approved ERD
  revision §9.8 adds nullable `archived_at` to `suppliers`, `clients`, `varieties`, `scions`,
  `pot_types`, `batches`; all six Management pages now archive/restore instead of deleting, with
  Active/Archived tabs, hidden-from-new-record-selectors behavior, and `activeExistsRule()`
  server-side validation. Shared behavior is in `App\Models\Concerns\Archivable`. Full suite
  **292/1100**.
- **Variety management (FR-13) is DONE** (see Session K below) — mirrors Suppliers/Clients exactly.
- **Scion management (FR-14) is DONE** (see Session L below) — mirrors Variety exactly; no new
  migration (the `scions` table already existed from the Batch Foundation session). **Session AX**
  added the three summary cards (Total Available / Re-grafted / Rejected) fed by
  `stock_ins.scion_quantity` — see Session AX below.
- **Pot Type management (FR-15) is DONE** (see Session M below) — mirrors Variety/Scion exactly; no
  new migration (the `pot_types` table already existed from the Batch Foundation session).
- **Analytics is DONE** (see Session N below) — all six Analytics pages are now real (operational
  KPIs/charts, Stock Ledger, Root-Cause Matrix, Seasonality Heatmap, Age Profile, Gap Report), with
  a new `analytics` table (`Analytic` model) used as report-generation history. The former
  Analytics OPEN DECISION is resolved. Stock Ledger, Root-Cause Matrix, Seasonality Heatmap,
  Age Profile and Gap Report have since been individually re-verified live (Sessions O–S) — no
  code/DB changes needed.
- **Activity History (FR-03/FR-17) is DONE** (see Session T below) — `activity_logs` table,
  `ActivityLog` model (centralized `record()` writer + `TYPES`), `ActivityController@index`, real
  filterable view, backfill seeder, tests. Operations write activity rows; `/activity` is under
  `auth`.
- **Profile (ERD `Users`) is DONE** (see Session U below; re-verified Session W) — no new table
  (the ERD has no Profile entity); the signed-in user's own `Users` row (`role` + `permissions` on
  the account, Session AU), `ProfileController`, real view with Edit Profile + Change Password
  modals, `profile_updated` activity rows; `/profile` is under `auth`.
- **Users management (FR-02) is DONE** (see Session X below; updated Session AU) — `UserController`,
  `users.*` routes, a single real Blade view (no separate Roles tab), 19 tests. Add/Edit set `role`
  (string) + `permissions[]` (fixed catalog) directly on the user; validation is centralized in
  `User::managementCreateRules()` / `managementUpdateRules()`; Add/Edit/Delete require the
  `manage_users` access restriction (403 otherwise). Administrator always keeps `manage_users`.
- **Roles & access restrictions (FR-02) — merged into Users (Session AU; Session AH below is
  historical)** — the `roles` + `role_permissions` tables and `users.role_id` were **dropped**; a
  user now stores `role` (string) + `permissions` (JSON) directly (approved denormalization — ERD
  §2.1/§9.1). `App\Models\Role` is a code-only catalog helper (`Role::PERMISSIONS`); enforcement is
  the server-side `permission` middleware. `Administrator` is protected (always holds `manage_users`;
  the last admin cannot be demoted/deleted); every user needs ≥1 permission. Full suite **309/1265**.
  **Do not reintroduce the tables or `users.role_id`.**
- **Daily Checklist guarded delete is DONE (Session AT)** — `DELETE /operational/checklist/tasks/{task}`
  deletes an **archived** task only when it has no `Task_Complete` history (Manager/Administrator
  only; 403 otherwise); active tasks must be archived first. Writes a `task_deleted` audit row. No
  new table/column. `DailyChecklistTest` 23 tests.
  If a page looks missing/static, it is the **stale `Capstone\capstone` copy** — always serve the
  real root.
- **Batch management (FR-16) is DONE** (see Session Z below) — full CRUD at `/inventory/batches`
  (`BatchController`, `Batch::STATUSES`/`rules()`/`hasOperationalRecords()`, real view, 19 tests).
  No new table/migration (reuses the existing `batches` table).
- **Dashboard (PRD §12) is DONE** (see Session AA below) — `DashboardController@index`, real view
  (KPI cards + 3 ECharts + real Recent Activities), 7 tests. Reuses `AnalyticsMetrics` +
  `ActivityLog` (RULE 6/12); no new table/migration; `/` is now under `auth` (FR-01).
  `ExampleTest` now expects a login redirect for `/`.
- **Notifications + Settings were REMOVED (Session AF)** (the Session AC build below is historical)
  — the navbar bell, `notifications` table, `users.settings` JSON, `Notification` model/controller,
  `SettingsController`, `AppServiceProvider` view composer, and the `/notifications` + `/settings`
  pages/routes/tests were deleted; migration `2026_09_16_000004` dropped the table/column. User
  feedback is now a **transient session-flash toast** (`resources/views/partials/toasts.blade.php`
  via `layouts/app.blade.php`, auto-shown in `resources/js/app.js`). No table, no ERD entity;
  ERD §9.5 records the reversal.
- **Daily Checklist archive/restore + printable PDF is DONE** (see Session AE below) — hard Delete
  was replaced by **archive/restore** (approved ERD revision: nullable `activity_tasks.archived_at`,
  ERD §2.4/§9.6) and a **printable FPDF** daily checklist was added (`App\Support\ChecklistPdf`,
  `GET /operational/checklist/export`). Active/Archived tabs; archive/restore write
  `task_archived`/`task_restored` audit rows; `DailyChecklistTest` 16 tests. No new table.
- **No mock page, route closure, or mock navbar control remains.** The only optional work is
  enabling `pdo_sqlite` so plain `php artisan test` works (see §5).
- **Before ending any session:** run the MySQL test suite + Pint (§5), then update this file's
  Session section, §3 data state, §5 counts, §6 next steps, and `AGENTS.md` (and
  `docs/CacaoTrack_ERD.md` first if the schema changed).

## 0. Orientation (read first)
- **App root:** `C:\laragon\www\Capstone\Capstone` — run all Laravel/composer/npm/artisan
  commands here (not from `C:\laragon\www\Capstone`).
- **Stale duplicate:** `Capstone\capstone\` is an old copy. Ignore it, never edit it.
- **A git repo now exists** (Session BM: `git init` + initial snapshot commit `62a521a`). Commit
  checkpoints freely; do not force-push or rewrite history. The stale `capstone/` duplicate is
  gitignored.
- **Authoritative docs:** `docs/CacaoTrack_PRD.md`, `docs/CacaoTrack_ERD.md`,
  `docs/CacaoTrack_Development_Rules.md`. ERD is the DB source of truth.
- Full agent rules are in `Capstone\AGENTS.md`.

## 1. Stack / environment facts
- Laravel 13, PHP 8.3.29, MySQL `db_capstone` (Laragon, user `root`, empty password).
  Real config is `.env`; `.env.example` says SQLite but the app uses MySQL.
- Frontend: Blade + Bootstrap 5 + Bootstrap Icons + ECharts via Vite.
  Bootstrap imported in `resources/css/app.css` / `resources/js/app.js`; ECharts is global
  `window.echarts`. Tailwind is installed but unused — use Bootstrap classes.
- **SQLite is NOT usable for tests here:** the PHP CLI has `php_pdo_sqlite.dll`/`php_sqlite3.dll`
  present but disabled in `php.ini`, so the repo's `phpunit.xml` (SQLite `:memory:`) errors with
  "could not find driver". Tests were run against a dedicated MySQL DB instead (see §5).
- `.npmrc` has `ignore-scripts=true`.

## 2. Session log (oldest → newest; detailed entries end with Session BH, followed by the refactor entries BI (P0), BJ (P1), BK (P2 plan), BL (P3 plan), BM (P2 implemented), BN (P3 implemented), BO (P4 views/sidebar implemented), BP (housekeeping: commit P4 + correct the `AGENTS.md` banner), BQ (P5 analytics/reports implemented), BR (live `db_capstone` data audit — investigation only), BS (Gap formula corrected to effective mortality — post-P5 follow-up), BT (user-facing Replanting→Regraft display rename —    implemented), BU (consolidation audit — `regraft_records` still live; read-only), BV
   (`Scion_Receiving` entity — additive schema + model only, IMPLEMENTED), BW (docs sync —
   `AGENTS.md` + this handoff aligned; `regraft_records` consolidation verified as never applied;
   no code/migration/DB change), BX (the `regraft_records` consolidation **APPLIED** — dropped the
   duplicate table via migration `2026_09_26_000002`, deleted the model/relation/import, repointed the
   Stock Ledger's Regraft column at `replanting_records`; commit `22ef695`; no test suite run — P6) and
   BY (handoff-refresh + ERD user-facing "Replanting"→"Regraft" labels in §7/§8; docs only; commit
   `9bd3c8a`)) and BZ (P6 — seeders/tests/factories migrated to the revised model; five legacy entity
   tests deleted; `AnalyticsTest` Gap assertions corrected; new Part D coverage; suite green
   **280/1170**; commits `da59091`/`5182a31`/`c48de04`) and CA (the `db_capstone` data policy
   **EXECUTED** — backed up then wiped + reseeded via `migrate:fresh --seed` against `db_capstone`;
   before→after counts; fresh KPIs 395 / 11.11% / 16 / Gap 50; docs-only commit — see the top banner)
   and CB (Scion Receiving **"Add" CRUD UI** — index + store route group, `ScionReceivingController`,
   `StoreScionReceivingRequest` with field-specific messages, sidebar link and
   `operational/scion-receivings/index.blade.php`; suite green **280/1170**; commit `6cd5d75`;
   Add-only — Edit/Archive/Details + grafting/regrafting traceability still deferred)
   and **CC** (`Scion_Receiving` **merged into `stock_ins`** — client-confirmed same real-world event;
   migrations `2026_09_26_000003`/`000004`; `ScionReceiving` model/factory/test/controller/request/
   view + its 2 routes and sidebar link deleted; Stock In modal gains a **Seedlings/Scion** tab pair
   and the list a **Scion Variety** column; suite green **278/1163**; commits `52a9f67` + `f766ce8` —
   see the top banner, §3 and §5); and **CE** (Phase 7A — viability count removed, four
   `2026_09_27_*` migrations), **CF** (Phase 7B — regraft / sub-batch flow) and **CG** (Phase 7C —
   parent/sub-batch nesting + Stock Ledger / analytics alignment; suite green **328/1406**; commits
    `7c84843`/`59488db`/`cc89669`/`b780873`); **CH** (Phase 7D — live `db_capstone` upgraded to the
    approved schema + reseeded), **CJ**/**CK** (client-confirmed cumulative `total_grafted`; mortality-rate
    denominator `SUM(total_grafted − regraft)`), **CL** (Mortality cause-of-death dropdown + "Others"
    description), **CM** (Root-Cause Matrix collapses all `Others: <description>` rows into one
     `Others` cause), **CN** (Stock In log labels each receiving Seedlings/Scion — a **Type** column
     + filter, derived via `StockIn::entryType()`, no schema change), **CO** (Seasonality Heatmap
     axis-label restyle — view-only) and **CP** (Stock Ledger printable PDF fonts auto-fit each cell) —
     see the top banner and the Session CG entry below.)

### Session A — Daily Checklist (previous session)
The Operational → **Daily Checklist** page (`/operational/checklist`) was converted from a static
mockup into a working feature with real DB persistence, per the ERD.

**Files created:**
- `database/migrations/2026_09_11_000001_create_activity_tasks_table.php`
  - Table `activity_tasks` (ERD `Activity_Task`): `task_id` (PK), `task_name`, `description`,
    **`priority`** → this is the one approved ERD revision (source ERD had no priority).
- `database/migrations/2026_09_11_000002_create_task_completes_table.php`
  - Table `task_completes` (ERD `Task_Complete`): `completion_id` (PK), `task_id` FK,
    `user_id` FK, `verified_by_id` FK (nullable), `completion_date`, `verified_at`, `remark`.
    Unique `(task_id, completion_date)`; `task_id` FK is `restrictOnDelete` to protect history.
- `app/Models/ActivityTask.php` (custom PK `task_id`, no timestamps, `completions()` relation).
- `app/Models/TaskComplete.php` (custom PK `completion_id`, `task()`, `user()`, `verifier()`).
- `app/Http/Controllers/AuthController.php` — `showLogin`, `login` (`Auth::attempt` + regenerate),
  `logout` (invalidate + regenerate token).
- `app/Http/Controllers/DailyChecklistController.php` — `index`, `storeTask`, `updateTask`,
  `destroyTask`, `completeTask`. Completion auto-verifies (sets `verified_by_id` = acting user,
  `verified_at` = now) and is wrapped in a DB transaction; duplicate same-day completion blocked;
  tasks with completion history cannot be deleted.
- `tests/Feature/DailyChecklistTest.php` — 8 tests (guest redirect, view, add, priority validation,
  complete, no double-complete, delete rules).

**Files modified:**
- `routes/web.php` — real `/login` (GET/POST) and `/logout` (POST); checklist routes under the
  `auth` middleware:
  - `GET  operational/checklist`
  - `POST operational/checklist/tasks`
  - `PUT  operational/checklist/tasks/{task}`
  - `DELETE operational/checklist/tasks/{task}`
  - `POST operational/checklist/tasks/{task}/complete`
- `resources/views/operational/checklist/index.blade.php` — fully rewritten: live date,
  completion/pending badges, priority/status badges, empty state, flash + validation alerts,
  working Add/Edit/Complete modals, `@push('scripts')` JS to populate modals.
- `resources/views/auth/login.blade.php` — real form (`route('login')`, `name` attrs, `remember`,
  server-side error display); removed the mock JS.
- `resources/views/layouts/navbar.blade.php` — shows `Auth::user()->name`; logout is a
  `POST route('logout')` form.
- `resources/views/layouts/sidebar.blade.php` — logout is a real POST form.
- `database/seeders/DatabaseSeeder.php` — seeds admin user + 4 example tasks.

### Session B — Batch Foundation Tables (previous session)
Four prerequisite tables for Batch and its dependencies were created, unblocking
Mortality, Inventory, Replanting, Stock In/Out, and Activity_Log.

**Files created:**
- `database/migrations/2026_09_12_000001_create_varieties_table.php`
  - Table `varieties` (ERD `Variety`): `variety_id` (PK, bigIncrements), `variety_name`, `description` (nullable).
- `database/migrations/2026_09_12_000002_create_scions_table.php`
  - Table `scions` (ERD `Scions`): `scion_id` (PK, bigIncrements), `scion_name`, `description` (nullable).
- `database/migrations/2026_09_12_000003_create_pot_types_table.php`
  - Table `pot_types` (ERD `Pot_Type`): `pot_id` (PK, bigIncrements), `pot_name`, `description` (nullable).
- `database/migrations/2026_09_12_000004_create_batches_table.php`
  - Table `batches` (ERD `Batch`): `batch_id` (PK, bigIncrements), `variety_id` FK, `scion_id` FK,
    `pot_id` FK, `user_id` FK, `batch_code` (unique), `total_grafted`, `viable_count`,
    `available_count`, `grafted_date` (date), `created_at` (datetime), `status` (string).
  - All FKs use `restrictOnDelete`. FK references use ERD-named PKs (e.g., `variety_id` not `id`).
- `app/Models/Variety.php` — custom PK `variety_id`, `HasMany` Batch.
- `app/Models/Scion.php` — custom PK `scion_id`, `HasMany` Batch.
- `app/Models/PotType.php` — custom PK `pot_id`, `HasMany` Batch.
- `app/Models/Batch.php` — custom PK `batch_id`, `BelongsTo` Variety, Scion, PotType, User.
  Casts `grafted_date` => date, `created_at` => datetime.

**Files modified:**
- `database/seeders DatabaseSeeder.php` — now also seeds 3 varieties (Criollo, Trinitario, Forastero),
  3 scions (ICS-1, ICS-6, UF-29), 3 pot types (Standard Polybag, Root Trainer, Ceramic Pot),
  and 3 batches (B001, B002, B003) linked to the admin user.

**Implementation note:** `$table->id('custom_name')` does NOT work — it ignores the argument and
always creates column `id`. Use `$table->bigIncrements('custom_name')` for custom PK column names.
Also, `$table->foreignId('col')->constrained('table')` defaults to referencing `id`. For
custom-named PKs, use `$table->foreignId('col')->constrained('table', 'custom_pk_col')`.

### Session C — Mortality Tracking (previous session)
The Operational → **Mortality Tracking** page (`/operational/mortality`) was converted from a
static mockup into a working feature with real DB persistence, per the ERD.

**Files created:**
- `database/migrations/2026_09_12_000005_create_mortality_records_table.php`
  - Table `mortality_records` (ERD `Mortality_Record`): `mortality_id` (PK), `batch_id` FK,
    `user_id` FK, `quantity` (unsigned int), `cause` (string), `recorded_at` (datetime).
    FKs use `restrictOnDelete`; `recorded_at` indexed.
- `database/migrations/2026_09_12_000006_create_mortality_adjustments_table.php`
  - Table `mortality_adjustments` (ERD `Mortality_Adjustments`): `adjustment_id` (PK),
    `mortality_id` FK, `approved_by_id` FK, `adjustment_quantity` (signed int), `reason` (string),
    `adjusted_at` (datetime). FKs use `restrictOnDelete`; `adjusted_at` indexed.
- `app/Models/MortalityRecord.php`
  - Custom PK `mortality_id`, no timestamps, `batch()`, `user()`, `adjustments()` relations.
  - `effectiveQuantity()` = base quantity + sum of signed adjustments.
  - `effectiveQuantityForBatch(batchId, ?excludeId)` static — centralized formula (RULE 6).
- `app/Models/MortalityAdjustment.php`
  - Custom PK `adjustment_id`, no timestamps, `mortalityRecord()`, `approver()` relations.
- `app/Http/Controllers/MortalityController.php`
  - `index` (filters: batch, variety, date range; real KPI totals from filtered records; paginated),
    `store` (validates batch/quantity/cause/date, cumulative cap ≤ `batch.viable_count`), `adjust`
    (signed delta, validates effective stays in [0, viable_count]).
- `tests/Feature/MortalityTest.php` — 11 tests: guest redirect, view, store, cumulative cap,
  positive quantity, invalid batch/cause, adjust, negative-effective, over-cap, zero-delta, filter.

**Files modified:**
- `app/Models/Batch.php` — added `mortalityRecords()` (HasMany), `effectiveMortalityQuantity()`,
  `remainingMortalityCapacity()`.
- `routes/web.php` — mortality routes moved under `auth` middleware, wired to controller:
  - `GET  operational/mortality`
  - `POST operational/mortality/records`
  - `POST operational/mortality/records/{mortality}/adjust`
- `resources/views/operational/mortality/index.blade.php` — fully rewritten: KPI cards (total
  mortality, records, batches affected), live filters, paginated table with effective-quantity
  badges + adjustment deltas, Record/Adjust/View modals, `@push('scripts')` JS for modals.
- `database/seeders/DatabaseSeeder.php` — now also seeds 3 mortality records (B001 Disease 25,
  B002 Drought 15, B003 Pest 10) and 1 adjustment (B001 −5, effective 20).

**Business-rule decisions made (per user approval before implementation):**
1. `Adjustment_Quantity` is a **signed delta** (not new total). Effective mortality = base
   `Quantity` + sum of all `Adjustment_Quantity`. Fully traceable, literal ERD reading.
2. Mortality cap = `batch.viable_count`. Cumulative effective mortality per batch cannot exceed
   the batch's `viable_count`.
3. Recording/adjusting mortality does **not** mutate `Batch.viable_count` or `Batch.available_count`.
   Mortality is derived from normalized `mortality_records` rows. Batch counts will be revised
   when Inventory logic is built.
4. `Mortality_Record` has no remark field (ERD omits it); the static mock's "Remarks" textarea
   was dropped.
5. Cause accepts free-form text (max 255); the view presents a datalist with common suggestions
   but does not restrict to a fixed enum.

### Session D — Replanting (previous session)
The Operational → **Replanting** page (`/operational/replanting`) was converted from a static
mockup into a working feature with real DB persistence, per the ERD.

**Files created:**
- `database/migrations/2026_09_12_000007_create_replanting_records_table.php`
  - Table `replanting_records` (ERD `Replanting_Record`): `replanting_id` (PK), `batch_id` FK,
    `user_id` FK, `quantity` (unsigned int), `replanting_date` (date), `remark` (string, nullable).
    FKs use `restrictOnDelete`; `replanting_date` indexed. No timestamps column.
- `app/Models/ReplantingRecord.php`
  - Custom PK `replanting_id`, no timestamps, `batch()`, `user()` relations.
  - `totalForBatch(batchId)` static — centralized replanted-total formula (RULE 6).
- `app/Http/Controllers/ReplantingController.php`
  - `index` (filter by batch; per-batch summaries of effective mortality, total replanted, deficit;
    KPIs: total deficit, total replanted, batches with deficit; per-batch history embedded for the
    modal), `store` (validates batch/quantity/date/optional remark; quantity ≤ remaining deficit;
    wrapped in a DB transaction).
- `tests/Feature/ReplantingTest.php` — 11 tests: guest redirect, view, store, positive quantity,
  over-deficit, cumulative over-deficit, invalid batch/date, optional remark, page deficit/filter,
  and derived-deficit model check.

**Files modified:**
- `app/Models/Batch.php` — added `replantingRecords()` (HasMany), `totalReplanted()`,
  `replantingDeficit()`, `remainingReplantingCapacity()`.
- `routes/web.php` — removed the static replanting closure; routes now under `auth` middleware:
  - `GET  operational/replanting`
  - `POST operational/replanting/records`
- `resources/views/operational/replanting/index.blade.php` — fully rewritten: real KPI cards,
  batch filter, status-by-batch table with deficit badges, History modal (per-batch records),
  Record Replanting modal, flash + validation alerts, `@push('scripts')` JS for the history modal.
- `database/seeders/DatabaseSeeder.php` — now also seeds 3 replanting records
  (B001 15, B002 15, B003 4).
- `AGENTS.md` — "Current state" updated to four functional features.

**Business-rule decisions made (per user approval before implementation):**
1. **Replanting Deficit = effective mortality − total replanted**, floored at 0. Defined once in
   `Batch::replantingDeficit()` and shared by the listing and validation (RULE 6).
2. Replanting quantity cannot exceed the batch's remaining deficit (PRD §12.3).
3. Replanting records are **append-only** — the ERD has no `Replanting_Adjustments` table.
4. Recording replanting does **not** mutate `Batch.viable_count` / `Batch.available_count`; the
   deficit is derived from normalized `replanting_records` rows.
5. `remark` is optional (max 255).
6. The Record modal only offers batches whose deficit is > 0.

### Session E — Inventory (previous session)
The Inventory → **Inventory** page (`/inventory`) was converted from a static mockup into a working
feature with real DB persistence, per the ERD.

**Files created:**
- `database/migrations/2026_09_12_000008_create_inventory_table.php`
  - Table `inventories` (ERD `Inventory`): `inventory_id` (PK), `batch_id` FK **UNIQUE**
    (`restrictOnDelete`), `current_quantity` (unsigned int), `status` (string), `last_update`
    (datetime). No timestamps column. Unique `batch_id` enforces the ERD's one-row-per-batch rule.
- `app/Models/Inventory.php`
  - Custom PK `inventory_id`, no timestamps, `batch()` relation.
  - `statusForQuantity(int)` — centralized status rule (RULE 6): `active` if > 0 else `depleted`.
  - `derivedStatus()` — instance status from stored balance.
  - `syncFromBatch(Batch, bool $overwrite)` — initialize/refresh the balance from
    `batch.available_count` (used by the seeder).
  - `totalActiveQuantity()` — centralized "Total Active Inventory" KPI (PRD §12.1).
- `app/Http/Controllers/InventoryController.php`
  - `index` — filters (batch-code search, variety, status), eager loads, batch-level derived columns,
    real KPI totals for the filtered set, paginated (10).
- `tests/Feature/InventoryTest.php` — 12 tests: guest redirect, view, real values, search filter,
  status filter, variety filter, invalid filters, empty state, unique-`batch_id` integrity,
  derived status + `syncFromBatch`, active-total KPI, batch⇄inventory relationship.

**Files modified:**
- `app/Models/Batch.php` — added `inventory()` (HasOne).
- `routes/web.php` — `/inventory` closure replaced with `auth`-wrapped
  `GET /inventory` → `InventoryController@index`, named `inventory.index`.
- `resources/views/inventory/index.blade.php` — fully rewritten: real KPI cards (total available,
  active batches, depleted batches), working filters, paginated real-data table
  (Grafted / Re-Graft / Mortality / Viable / Available / Status), real Batch Details modal
  (general info + inventory status), empty state, client-side CSV export of the shown rows.
- `database/seeders/DatabaseSeeder.php` — seeds one inventory row per batch via
  `Inventory::syncFromBatch($batch, true)` (balance initialised from `available_count`).
- `AGENTS.md` — "Current state" updated to five functional features; the `Inventory.Batch_ID`
  UNIQUE open decision marked resolved.

**Business-rule decisions made (per user approval before implementation):**
1. **Scope:** Inventory page only. Stock In/Out/Reversal (and their `Supplier`, `Client`,
   `Stock_In`, `Stock_Out`, `Stock_Reversal` tables) remain future sessions.
2. **`Batch_ID` = FK + UNIQUE** honored (ERD §9.3): exactly one inventory row per batch. This
   resolves the previously flagged OPEN DECISION.
3. **`Inventory.Current_Quantity` is a stored, authoritative balance** (not derived). It is
   initialized from `Batch.available_count`; future Stock In/Out/Reversal are the only writers, and
   they must mutate it inside DB transactions (RULE 7).
4. **`Inventory.Status` is derived** from the balance: `active` if > 0, else `depleted`
   (`Inventory::statusForQuantity()`).
5. Recording mortality/replanting still does **not** mutate the inventory balance.
6. Table named `inventories` (Laravel plural of ERD `Inventory`), matching repo convention.

### Session F — Stock In (previous session)
The Inventory → **Stock In** page (`/inventory/stock-in`) was converted from a static mockup into a
working feature with real DB persistence, per the ERD.

**Files created:**
- `database/migrations/2026_09_12_000009_create_suppliers_table.php`
  - Table `suppliers` (ERD `Supplier`): `supplier_id` (PK, bigIncrements), `supplier_name`,
    `contact_number`, `address`. No timestamps column.
- `database/migrations/2026_09_12_000010_create_stock_ins_table.php`
  - Table `stock_ins` (ERD `Stock_In`): `stock_in_id` (PK, bigIncrements), `supplier_id` FK,
    `batch_id` FK, `user_id` FK, `quantity` (unsigned int), `date_received` (date, indexed).
    All FKs use `restrictOnDelete`. No timestamps column. (ERD has **no** `Remark` column.)
- `app/Models/Supplier.php`
  - Custom PK `supplier_id`, no timestamps, `stockIns()` (HasMany).
- `app/Models/StockIn.php`
  - Custom PK `stock_in_id`, no timestamps, `supplier()`, `batch()`, `user()` relations.
  - `reference()` — display helper returning `SI-###` (presentation only, not a DB column).
  - `totalForBatch(batchId)` static — centralized received-total formula (RULE 6).
- `app/Http/Controllers/StockInController.php`
  - `index` — filters (search supplier/batch, supplier, batch, date range), eager loads,
    real KPI totals (total quantity received, transaction count, distinct suppliers), paginated.
  - `store` — validates supplier/batch/quantity/date; wraps create + inventory increment in a
    `DB::transaction`; locks the inventory row (`lockForUpdate`); creates the row via
    `syncFromBatch` if missing; applies `applyDelta(+quantity)`. Validation errors use the
    `stockin` error bag. `date_received` must be `<= today`.
  - `storeSupplier` — inline supplier creation from the Stock In page; validation errors use the
    `supplier` error bag; flashes `new_supplier_id` so the modal reopens preselected.
- `tests/Feature/StockInTest.php` — 15 tests: guest redirect, view, store + inventory increment,
  depleted→active flip, inventory auto-create, required/invalid supplier+batch, zero/negative
  quantity, future date, listing, supplier/search filters, empty state, inline supplier create +
  validation, reference formatting.

**Files modified:**
- `app/Models/Inventory.php` — added `applyDelta(int $delta)` (RULE 6/7): the single writer for the
  stored balance; recomputes `status` via `statusForQuantity()` and sets `last_update`; throws if
  the balance would go negative. Stock Out/Reversal will reuse it.
- `app/Models/Batch.php` — added `stockIns()` (HasMany).
- `routes/web.php` — `/inventory/stock-in` closure replaced with `auth`-wrapped
  `GET /inventory/stock-in` → `StockInController@index` (named `inventory.stock-in`) and
  `POST /inventory/stock-in` → `@store` (`inventory.stock-in.store`); added
  `POST /inventory/suppliers` → `@storeSupplier` (`inventory.suppliers.store`).
- `resources/views/inventory/stock-in/index.blade.php` — fully rewritten: real KPI cards, working
  filters, paginated real-data table (Stock ID / Supplier / Batch / Quantity / Date / Recorded By),
  real New Stock In modal (server validation, old input, error-bag-aware reopen), New Supplier
  modal, Stock-In Details View modal, empty state, double-submit guard. Mock rows/alerts removed.
- `database/seeders/DatabaseSeeder.php` — now also seeds 3 suppliers (Davao Cacao Growers,
  Bukidnon Seedling Supply, North Mindanao Nursery). No stock-in rows are seeded (to keep inventory
  totals intact).

**Business-rule decisions made (per user approval before implementation):**
1. **Remarks dropped:** the ERD `Stock_In` has no remark column, so the static form's Remarks
   textarea was removed (matches how the Mortality remark was handled).
2. **Supplier scope:** created the `suppliers` table + seed data + an inline "New Supplier" modal on
   the Stock In page. The full Suppliers management page (FR-11) remains a later session; its GET
   route is still the static mockup.
3. **`Batch.available_count` is NOT mutated** by stock-in; `Inventory.current_quantity` stays the
   single authoritative balance (consistent with Session E decisions).
4. **Stock-in records are append-only** (View modal only). Corrections will go through Stock
   Reversal (future session), matching the ERD design.
5. **Missing inventory row:** if a batch has no inventory row, `store` initializes it from
   `Batch.available_count` via `syncFromBatch()` and then adds the received quantity.
6. **Stock-in increases inventory** (FR-08) atomically inside a DB transaction (RULE 7).
7. The static "+ Create New Batch" option was removed — batch creation is Batch Management, out of
   scope.

### Session G — Stock Out (previous session)
The Inventory → **Stock Out** page (`/inventory/stock-out`) was converted from a static mockup into a
working feature with real DB persistence, per the ERD.

**Files created:**
- `database/migrations/2026_09_12_000011_create_clients_table.php`
  - Table `clients` (ERD `Client`): `client_id` (PK, bigIncrements), `client_name`,
    `contact_number`, `address`. No timestamps column.
- `database/migrations/2026_09_12_000012_create_stock_outs_table.php`
  - Table `stock_outs` (ERD `Stock_Out`): `stock_out_id` (PK, bigIncrements), `client_id` FK,
    `batch_id` FK, `user_id` FK, `quantity` (unsigned int), `total_amount`
    (decimal(12,2), **nullable**), `release_date` (date, indexed). All FKs use `restrictOnDelete`.
    No timestamps column. (ERD has **no** `Remark` column.)
- `app/Models/Client.php`
  - Custom PK `client_id`, no timestamps, `stockOuts()` (HasMany).
- `app/Models/StockOut.php`
  - Custom PK `stock_out_id`, no timestamps, `client()`, `batch()`, `user()` relations.
  - `reference()` — display helper returning `SO-###` (presentation only, not a DB column).
  - `totalForBatch(batchId)` static — centralized released-total formula (RULE 6).
  - Casts `total_amount` => `decimal:2`, `release_date` => date.
- `app/Http/Controllers/StockOutController.php`
  - `index` — filters (search client/batch, client, batch, date range), eager loads, real KPI
    totals (total quantity released, transaction count, distinct clients, sum of `total_amount`),
    paginated (10). Passes `clients` and `batches` (with real available from
    `inventories.current_quantity`).
  - `store` — validates client/batch/quantity/`release_date` (`<= today`)/optional `total_amount`
    (`nullable|numeric|min:0`); wraps create + inventory decrement in a `DB::transaction`; locks the
    inventory row (`lockForUpdate`); creates the row via `syncFromBatch` if missing; **rejects a
    quantity greater than `current_quantity` (PRD FR-09)** via a `stockout`-bagged
    `ValidationException`; applies `applyDelta(-quantity)`. Validation errors use the `stockout`
    bag.
  - `storeClient` — inline client creation from the Stock Out page; validation errors use the
    `client` error bag; flashes `new_client_id` so the modal reopens preselected.
- `tests/Feature/StockOutTest.php` — 17 tests: guest redirect, view, store + inventory decrement,
  full-release depletion, over-availability rejection + rollback (no row, balance unchanged),
  inventory auto-create, required/invalid client+batch, zero/negative quantity, future date,
  optional/negative `total_amount`, listing, client/search filters, empty state, inline client
  create + validation, reference formatting.

**Files modified:**
- `app/Models/Batch.php` — added `stockOuts()` (HasMany).
- `routes/web.php` — `/inventory/stock-out` closure replaced with `auth`-wrapped
  `GET /inventory/stock-out` → `StockOutController@index` (named `inventory.stock-out`) and
  `POST /inventory/stock-out` → `@store` (`inventory.stock-out.store`); added
  `POST /inventory/clients` → `@storeClient` (`inventory.clients.store`).
- `resources/views/inventory/stock-out/index.blade.php` — fully rewritten: 4 real KPI cards
  (quantity released, transactions, clients, total amount ₱), working filters, paginated real-data
  table (Stock ID / Client / Batch / Quantity / Total Amount / Release Date / Recorded By), real
  New Stock Out modal (batch options show real available qty, client-side `max` + available hint,
  server validation, old input, error-bag-aware reopen), New Client modal, Stock-Out Details View
  modal, empty state, double-submit guard. Mock rows/alerts removed.
- `database/seeders/DatabaseSeeder.php` — now also seeds 3 clients (Cacao Farmers Coop, Mountain
  View Estate, Green Pod Traders). No stock-out rows are seeded (to keep inventory totals intact).
- `AGENTS.md` — "Current state" updated to seven functional features (it was stale: Stock In was
  also not previously listed).

**Business-rule decisions made (per user approval before implementation):**
1. **`Total_Amount` is optional/nullable `decimal(12,2)`.** ERD lists the column, PRD FR-09 says
   "where applicable", and the static form marked it optional. User-entered only (no unit-price master
   data exists).
2. **Remarks dropped:** ERD `Stock_Out` has no remark column, so the static form's Remarks textarea
   was removed (matches Stock In / Mortality handling).
3. **Client scope:** created the `clients` table + seed data + an inline "New Client" modal on the
   Stock Out page. The full Clients management page (FR-12) remains a later session; its GET route
   is still the static mockup. Mirrors the Supplier approach exactly.
4. **`Batch.available_count` is NOT mutated** by stock-out; `Inventory.current_quantity` stays the
   single authoritative balance (consistent with Sessions E/F).
5. **Over-release rejected** (PRD FR-09): quantity > available inventory fails validation; the
   check is done inside the transaction against the locked inventory row.
6. **Stock-out decreases inventory** atomically inside a DB transaction (RULE 7), reusing
   `Inventory::applyDelta()`.
7. **Stock-out records are append-only** (View modal only). Corrections will go through Stock
   Reversal (future session), matching the ERD design.
8. **Four KPI cards** shown (per user choice): total quantity released, transaction count, distinct
   clients, and total amount released.

### Session H — Stock Reversal (previous session)
The Inventory → **Stock Reversal** page (`/inventory/stock-reversal`) was converted from a static
mockup into a working feature with real DB persistence, per the ERD.

**OPEN DECISION resolved (approved ERD revision):** the ERD's transaction reference
`Stock_In_ID OR Stock_Out_ID` is implemented as **two nullable FKs** — `stock_in_id` and
`stock_out_id` — with exactly one populated per reversal row. This preserves referential integrity
(a MySQL FK references one table); the reversal type is derived from which key is set. Recorded in
`docs/CacaoTrack_ERD.md` §2.18 / §3 / §5 / §9.2.

**Files created:**
- `database/migrations/2026_09_12_000013_create_stock_reversals_table.php`
  - Table `stock_reversals` (ERD `Stock_Reversal`): `reversal_id` (PK), `stock_in_id` (nullable FK
    → `stock_ins`, restrictOnDelete), `stock_out_id` (nullable FK → `stock_outs`, restrictOnDelete),
    `user_id` FK, `quantity` (unsigned int), `reason` (string), `reversed_at` (datetime, indexed).
    No timestamps column.
- `app/Models/StockReversal.php`
  - Custom PK `reversal_id`, no timestamps, `stockIn()`, `stockOut()`, `user()` relations.
  - `type()` / `typeLabel()` derived from the populated FK; `source()`, `sourceReference()`,
    `sourceBatch()` presentation helpers; `reference()` → `REV-###`.
- `app/Http/Controllers/StockReversalController.php`
  - `index` — filters (search reason/batch, type in/out, reversed-date range), eager loads, real KPI
    totals (total reversed qty, reversal count, stock-in reversal count, stock-out reversal count),
    paginated (10). Builds the reversible transaction options for the modal.
  - `store` — validates type/source/quantity/reason/`reversed_at` (`<= today`); inside a
    `DB::transaction`, locks the source transaction, rejects a quantity above the reversible
    quantity (FR-10 / ERD §8) and a stock-in reversal that would drive inventory negative, then
    corrects the balance via `Inventory::applyDelta()` (stock-in reversal = −qty, stock-out
    reversal = +qty). Validation errors use the `reversal` bag.
- `tests/Feature/StockReversalTest.php` — 16 tests: guest redirect, view, reverse-in (-inventory),
  reverse-out (+inventory), over-reversible rejection + rollback, accumulated-reversal cap,
  reverse-in beyond on-hand rejection, required fields, unknown source, zero/negative quantity,
  future date, listing, type + search filters, empty state, reference formatting, reversible-qty
  helpers.

**Files modified:**
- `app/Models/StockIn.php` / `app/Models/StockOut.php` — added `reversals()` (HasMany),
  `reversedQuantity()`, `reversibleQuantity()` (RULE 6: single definition shared by listing and
  validation).
- `routes/web.php` — `/inventory/stock-reversal` moved under `auth`; static closure replaced with
  `GET` → `StockReversalController@index` (`inventory.stock-reversal`) and `POST` → `@store`
  (`inventory.stock-reversal.store`).
- `resources/views/inventory/stock-reversal/index.blade.php` — fully rewritten: 4 real KPI cards,
  working filters, paginated real-data table (Reversal ID / Stock Record / Type / Batch / Quantity /
  Reason / Date / Recorded By), real Create Reversal modal (type → source options showing reversible
  qty, server validation, old input, `reversal`-bag-aware reopen), Details View modal, empty state,
  double-submit guard. Mock rows/JS removed.
- `docs/CacaoTrack_ERD.md` — Stock_Reversal two-FK revision recorded.
- `AGENTS.md` — Stock Reversal added as feature 8; OPEN DECISION cleared.

**Business-rule decisions made (per user approval before implementation):**
1. **Two-nullable-FK design** (Option A) for the ERD's `Stock_In_ID OR Stock_Out_ID`, exactly one
   populated. No polymorphic type column, no DB-level `CHECK` (enforced in validation).
2. **Reversible quantity = original transaction quantity − already-reversed quantity** (centralized
   in `StockIn::reversibleQuantity()` / `StockOut::reversibleQuantity()`).
3. **Correct inventory consistently** (FR-10): reversing a Stock In decrements the balance;
   reversing a Stock Out increments it, via `Inventory::applyDelta()` inside a DB transaction.
4. **A Stock-In reversal is additionally bounded by the current inventory balance** so the balance
   can never go negative; a Stock-Out reversal is bounded only by the reversible quantity.
5. **Reversals are append-only** (View modal only); the original Stock In/Out rows are not mutated.
6. **No reversal rows are seeded** — the seed data has 0 stock-ins/stock-outs, so nothing is
   reversible; inventory totals stay intact.

### Session I — Supplier Management (previous session)
The Management → **Suppliers** page (`/inventory/suppliers`, FR-11) was converted from a static
mockup into a working CRUD feature with real DB persistence, per the ERD.

**No new migration was needed:** the `suppliers` table and `App\Models\Supplier` already existed
(created in Session F for Stock In), and the seeder already inserts 3 suppliers.

**Files created:**
- `app/Http/Controllers/SupplierController.php`
  - `index` — search filter (name/contact/address), `withCount('stockIns')`, paginated (10), and
    real KPI totals (total suppliers, suppliers with stock-in activity, stock-in transactions).
  - `store` — validates via `Supplier::rules()` into the `supplier` error bag; creates.
  - `update` — same rules; on failure redirects back with `edit_supplier_id` in old input so the
    edit modal reopens.
  - `destroy` — **blocks deletion when `$supplier->stockIns()->exists()`** (the `stock_ins.supplier_id`
    FK is `restrictOnDelete`); flashes `error`; otherwise deletes.
- `tests/Feature/SupplierTest.php` — 13 tests: guest redirect, view + real values, create, duplicate
  names allowed, required-field validation, over-length validation, update, update validation,
  delete, delete-blocked-with-stock-ins, search filter, empty state, reference formatting.

**Files modified:**
- `app/Models/Supplier.php` — added static `rules()` (centralized validation, RULE 6) and
  `reference()` → `SUP-###` (presentation only, not a DB column).
- `routes/web.php` — the static `GET /inventory/suppliers` closure was replaced and moved under the
  `auth` middleware → `SupplierController@index` (name `inventory.suppliers`); added
  `POST /inventory/suppliers/manage` → `@store` (`inventory.suppliers.manage.store`),
  `PUT /inventory/suppliers/{supplier}` → `@update` (`inventory.suppliers.update`), and
  `DELETE /inventory/suppliers/{supplier}` → `@destroy` (`inventory.suppliers.destroy`). Route-model
  binding is by `supplier_id` (custom PK).
- `resources/views/inventory/suppliers/index.blade.php` — fully rewritten: real KPI cards, search
  filter, paginated real-data table (ID / Name / Contact / Address / Stock-Ins / Actions), Add/Edit/
  Delete/Details modals with `old()` + `supplier`-bag-aware reopen, delete confirmation that disables
  the button when stock-ins exist, flash + validation alerts, empty state, double-submit guard.
  Mock rows/JS removed.
- `AGENTS.md` — "Current state" updated to nine functional features; test-command note corrected
  (SQLite disabled → run against `db_capstone_test`).

**Business-rule decisions made (per user approval before implementation):**
1. **Duplicate supplier names are allowed** — the ERD declares no unique constraint; no uniqueness
   rule was invented.
2. **`contact_number` stays free-form `string|max:255`** (matches the ERD and Session F code); no
   phone-format regex was invented.
3. **Deletion is blocked when stock-in records exist** (FK `restrictOnDelete`) — mirrors the
   checklist's delete-protection rule.
4. **Separate management create route** (`POST /inventory/suppliers/manage`); the Stock In page's
   existing inline quick-add (`POST /inventory/suppliers` → `StockInController@storeSupplier`) is
   **unchanged** and its tests still pass.
5. **No seed changes** — the existing 3 suppliers remain; no new rows were added and no
   `migrate:fresh` was run.

### Session J — Client Management (previous session)
The Management → **Clients** page (`/inventory/clients`, FR-12) was converted from a static mockup
into a working CRUD feature with real DB persistence, per the ERD. Mirrors Session I (Suppliers).

**No new migration was needed:** the `clients` table and `App\Models\Client` already existed
(created in Session G for Stock Out), and the seeder already inserts 3 clients.

**Files created:**
- `app/Http/Controllers/ClientController.php`
  - `index` — search filter (name/contact/address), `withCount('stockOuts')`, paginated (10), and
    real KPI totals (total clients, clients with stock-out activity, stock-out transactions).
  - `store` — validates via `Client::rules()` into the `client` error bag; creates.
  - `update` — same rules; on failure redirects back with `edit_client_id` in old input so the
    edit modal reopens.
  - `destroy` — **blocks deletion when `$client->stockOuts()->exists()`** (the `stock_outs.client_id`
    FK is `restrictOnDelete`); flashes `error`; otherwise deletes.
- `tests/Feature/ClientTest.php` — 13 tests: guest redirect, view + real values, create, duplicate
  names allowed, required-field validation, over-length validation, update, update validation,
  delete, delete-blocked-with-stock-outs, search filter, empty state, reference formatting.

**Files modified:**
- `app/Models/Client.php` — added static `rules()` (centralized validation, RULE 6) and
  `reference()` → `CLI-###` (presentation only, not a DB column).
- `routes/web.php` — the static `GET /inventory/clients` closure was replaced and moved under the
  `auth` middleware → `ClientController@index` (name `inventory.clients`); added
  `POST /inventory/clients/manage` → `@store` (`inventory.clients.manage.store`),
  `PUT /inventory/clients/{client}` → `@update` (`inventory.clients.update`), and
  `DELETE /inventory/clients/{client}` → `@destroy` (`inventory.clients.destroy`). Route-model
  binding is by `client_id` (custom PK).
- `resources/views/inventory/clients/index.blade.php` — fully rewritten: real KPI cards, search
  filter, paginated real-data table (ID / Name / Contact / Address / Stock-Outs / Actions), Add/Edit/
  Delete/Details modals with `old()` + `client`-bag-aware reopen, delete confirmation that disables
  the button when stock-outs exist, flash + validation alerts, empty state, double-submit guard.
  Mock rows/JS removed.
- `AGENTS.md` — "Current state" updated to ten functional features.

**Business-rule decisions made (per user approval before implementation):**
1. **Duplicate client names are allowed** — the ERD declares no unique constraint; no uniqueness
   rule was invented.
2. **`contact_number` stays free-form `string|max:255`** (matches the ERD and Session G code); no
   phone-format regex was invented.
3. **Deletion is blocked when stock-out records exist** (FK `restrictOnDelete`) — mirrors the
   supplier and checklist delete-protection rules.
4. **Separate management create route** (`POST /inventory/clients/manage`); the Stock Out page's
   existing inline quick-add (`POST /inventory/clients` → `StockOutController@storeClient`) is
   **unchanged** and its tests still pass.
5. **No seed changes** — the existing 3 clients remain; no new rows were added and no
   `migrate:fresh` was run.

### Session K — Variety Management (previous session)
The Management → **Varieties** page (`/inventory/varieties`, FR-13) was converted from a static
mockup into a working CRUD feature with real DB persistence, per the ERD. Mirrors Session I (Suppliers)
and Session J (Clients).

**No new migration was needed:** the `varieties` table and `App\Models\Variety` already existed
(created in Session B — Batch Foundation), and the seeder already inserts 3 varieties.

**Files created:**
- `app/Http/Controllers/VarietyController.php`
  - `index` — search filter (name/description), `withCount('batches')`, `orderBy('variety_name')`,
    paginated (10) `withQueryString()`, and real KPI totals (total varieties, varieties with
    batches, total batches under the filtered varieties).
  - `store` — validates via `Variety::rules()` into the `variety` error bag; creates.
  - `update` — same rules; on failure redirects back with `edit_variety_id` in old input so the
    edit modal reopens.
  - `destroy` — **blocks deletion when `$variety->batches()->exists()`** (the `batches.variety_id`
    FK is `restrictOnDelete`); flashes `error`; otherwise deletes.
- `tests/Feature/VarietyTest.php` — 14 tests: guest redirect, view + real values, create, create
  without description, duplicate names allowed, required-field validation, over-length validation
  (name 256 / description 1001), update, update validation, delete, delete-blocked-with-batches,
  search filter, empty state, reference formatting.

**Files modified:**
- `app/Models/Variety.php` — added static `rules()` (centralized validation, RULE 6; `variety_name`
  required max:255, `description` nullable max:1000) and `reference()` → `VAR-###` (presentation
  only, not a DB column).
- `routes/web.php` — the static `GET /inventory/varieties` closure was replaced and moved under the
  `auth` middleware → `VarietyController@index` (name `inventory.varieties`); added
  `POST /inventory/varieties/manage` → `@store` (`inventory.varieties.manage.store`),
  `PUT /inventory/varieties/{variety}` → `@update` (`inventory.varieties.update`), and
  `DELETE /inventory/varieties/{variety}` → `@destroy` (`inventory.varieties.destroy`). Route-model
  binding is by `variety_id` (custom PK). The `/inventory/scions` and `/inventory/pot-types`
  closures are still static and unchanged.
- `resources/views/inventory/varieties/index.blade.php` — fully rewritten: real KPI cards (Total
  Varieties / With Batches / Total Batches), search filter, paginated real-data table (ID / Variety
  Name / Description / Batches / Actions), Add/Edit/Delete/Details modals with `old()` +
  `variety`-bag-aware reopen, delete confirmation that disables the button when batches exist, flash
  + validation alerts, empty state, double-submit guard. Mock rows/JS removed (the old view had a
  fake `alert('Variety saved successfully!')` handler).
- `AGENTS.md` — "Current state" updated to eleven functional features.

**Business-rule decisions made (per user approval before implementation):**
1. **Three KPI cards:** total varieties, varieties with batches, and total batch records under the
   filtered varieties (mirrors the Supplier/Client KPI pattern).
2. **Duplicate variety names are allowed** — the ERD declares no unique constraint; no uniqueness
   rule was invented.
3. **Deletion is blocked when batch records exist** (FK `restrictOnDelete`) — mirrors the supplier,
   client, and checklist delete-protection rules.
4. **`description` is optional**, validated `nullable|string|max:1000` (matches the Daily Checklist
   description rule; the column is a nullable `text`).
5. **No inline quick-add route** for varieties (unlike Suppliers/Clients) — Batch creation is out of
   scope, so only the four management routes exist.
6. **No seed changes** — the existing 3 varieties remain; no new rows were added and no
   `migrate:fresh` was run.

### Session L — Scion Management (previous session)
The Management → **Scions** page (`/inventory/scions`, FR-14) was converted from a static mockup
into a working CRUD feature with real DB persistence, per the ERD. Mirrors Session K (Varieties).

**No new migration was needed:** the `scions` table and `App\Models\Scion` already existed (created
in Session B — Batch Foundation), and the seeder already inserts 3 scions (ICS-1, ICS-6, UF-29).
The migration matches ERD §2.8 exactly (`scion_id` PK, `scion_name`, nullable `description`).

**Files created:**
- `app/Http/Controllers/ScionController.php`
  - `index` — search filter (name/description), `withCount('batches')`, `orderBy('scion_name')`,
    paginated (10) `withQueryString()`, and real KPI totals (total scions, scions with batches,
    total batches under the filtered scions).
  - `store` — validates via `Scion::rules()` into the `scion` error bag; creates.
  - `update` — same rules; on failure redirects back with `edit_scion_id` in old input so the edit
    modal reopens.
  - `destroy` — **blocks deletion when `$scion->batches()->exists()`** (the `batches.scion_id` FK is
    `restrictOnDelete`); flashes `error`; otherwise deletes.
- `tests/Feature/ScionTest.php` — 14 tests: guest redirect, view + real values, create, create
  without description, duplicate names allowed, required-field validation, over-length validation
  (name 256 / description 1001), update, update validation, delete, delete-blocked-with-batches,
  search filter, empty state, reference formatting.

**Files modified:**
- `app/Models/Scion.php` — added static `rules()` (centralized validation, RULE 6; `scion_name`
  required max:255, `description` nullable max:1000) and `reference()` → `SCN-###` (presentation
  only, not a DB column).
- `routes/web.php` — the static `GET /inventory/scions` closure was replaced and moved under the
  `auth` middleware → `ScionController@index` (name `inventory.scions`); added
  `POST /inventory/scions/manage` → `@store` (`inventory.scions.manage.store`),
  `PUT /inventory/scions/{scion}` → `@update` (`inventory.scions.update`), and
  `DELETE /inventory/scions/{scion}` → `@destroy` (`inventory.scions.destroy`). Route-model binding
  is by `scion_id` (custom PK). **Note:** the `/scions` route was previously *outside* the `auth`
  middleware (an unauthenticated static closure); it is now protected like every other page. The
  `/inventory/pot-types` closure is still static and unchanged.
- `resources/views/inventory/scions/index.blade.php` — fully rewritten: real KPI cards (Total Scions
  / With Batches / Total Batches), search filter, paginated real-data table (ID / Scion Name /
  Description / Batches / Actions), Add/Edit/Delete/Details modals with `old()` +
  `scion`-bag-aware reopen, delete confirmation that disables the button when batches exist, flash +
  validation alerts, empty state, double-submit guard. Mock rows/JS removed (the old view had a fake
  `alert('Scion saved successfully!')` handler and hardcoded `SC-001`/`Scion A` rows).
- `AGENTS.md` — "Current state" updated to twelve functional features.

**Business-rule decisions made (per user approval before implementation):**
1. **No new migration** — the existing `scions` table already matches ERD §2.8; creating another
   would duplicate/conflict. Reused as-is.
2. **Display reference `SCN-###`** (3-letter prefix consistent with `VAR-`/`SUP-`/`CLI-`), rather
   than the mockup's `SC-` prefix. Presentation only, not a DB column.
3. **Three KPI cards:** total scions, scions with batches, and total batch records under the
   filtered scions (mirrors the Variety/Supplier/Client KPI pattern).
4. **Duplicate scion names are allowed** — the ERD declares no unique constraint; no uniqueness
   rule was invented.
5. **Deletion is blocked when batch records exist** (FK `restrictOnDelete`) — mirrors the variety,
   supplier, client, and checklist delete-protection rules.
6. **`description` is optional**, validated `nullable|string|max:1000` (matches Variety; column is a
   nullable `text`).
7. **No inline quick-add route** for scions (Batch creation is out of scope).
8. **No seed changes** — the existing 3 scions remain; no new rows were added and no
   `migrate:fresh` was run.

### Session M — Pot Type Management (previous session)
The Management → **Pot Types** page (`/inventory/pot-types`, FR-15) was converted from a static
mockup into a working CRUD feature with real DB persistence, per the ERD. Mirrors Session K
(Varieties) and Session L (Scions).

**No new migration was needed:** the `pot_types` table and `App\Models\PotType` already existed
(created in Session B — Batch Foundation), and the seeder already inserts 3 pot types (Standard
Polybag, Root Trainer, Ceramic Pot). The migration matches ERD §2.9 exactly (`pot_id` PK,
`pot_name`, nullable `description`).

**Files created:**
- `app/Http/Controllers/PotTypeController.php`
  - `index` — search filter (name/description), `withCount('batches')`, `orderBy('pot_name')`,
    paginated (10) `withQueryString()`, and real KPI totals (total pot types, pot types with
    batches, total batches under the filtered pot types).
  - `store` — validates via `PotType::rules()` into the `pot` error bag; creates.
  - `update` — same rules; on failure redirects back with `edit_pot_id` in old input so the edit
    modal reopens.
  - `destroy` — **blocks deletion when `$potType->batches()->exists()`** (the `batches.pot_id` FK is
    `restrictOnDelete`); flashes `error`; otherwise deletes.
- `tests/Feature/PotTypeTest.php` — 14 tests: guest redirect, view + real values, create, create
  without description, duplicate names allowed, required-field validation, over-length validation
  (name 256 / description 1001), update, update validation, delete, delete-blocked-with-batches,
  search filter, empty state, reference formatting.

**Files modified:**
- `app/Models/PotType.php` — added static `rules()` (centralized validation, RULE 6; `pot_name`
  required max:255, `description` nullable max:1000) and `reference()` → `POT-###` (presentation
  only, not a DB column). `batches()` already existed.
- `routes/web.php` — the static `GET /inventory/pot-types` closure was replaced and moved under the
  `auth` middleware → `PotTypeController@index` (name `inventory.pot-types`); added
  `POST /inventory/pot-types/manage` → `@store` (`inventory.pot-types.manage.store`),
  `PUT /inventory/pot-types/{pot_type}` → `@update` (`inventory.pot-types.update`), and
  `DELETE /inventory/pot-types/{pot_type}` → `@destroy` (`inventory.pot-types.destroy`).
  Route-model binding is by `pot_id` (custom PK).
- `resources/views/inventory/pot-types/index.blade.php` — fully rewritten: real KPI cards (Total Pot
  Types / With Batches / Total Batches), search filter, paginated real-data table (ID / Pot Type /
  Description / Batches / Actions), Add/Edit/Delete/Details modals with `old()` +
  `pot`-bag-aware reopen, delete confirmation that disables the button when batches exist, flash +
  validation alerts, empty state, double-submit guard. Mock rows/JS removed (the old view had a fake
  `alert('Pot Type saved successfully!')` handler and a hardcoded `POT-001` row).
- `AGENTS.md` — "Current state" updated to thirteen functional features.

**Business-rule decisions made (per user approval before implementation):**
1. **No new migration** — the existing `pot_types` table already matches ERD §2.9; creating another
   would duplicate/conflict. Reused as-is.
2. **Display reference `POT-###`** (3-letter prefix consistent with `VAR-`/`SCN-`/`SUP-`/`CLI-`),
   rather than the mockup's static `POT-001` row. Presentation only, not a DB column.
3. **Three KPI cards:** total pot types, pot types with batches, and total batch records under the
   filtered pot types (mirrors the Variety/Scion/Supplier/Client KPI pattern).
4. **Duplicate pot type names are allowed** — the ERD declares no unique constraint; no uniqueness
   rule was invented.
5. **Deletion is blocked when batch records exist** (FK `restrictOnDelete`) — mirrors the variety,
   scion, supplier, client, and checklist delete-protection rules.
6. **`description` is optional**, validated `nullable|string|max:1000` (matches Variety/Scion; column
   is a nullable `text`).
7. **No inline quick-add route** for pot types (Batch creation is out of scope).
8. **No seed changes** — the existing 3 pot types remain; no new rows were added and no
   `migrate:fresh` was run.

### Session N — Analytics (previous session)
The **Analytics** section (Operational Analytics, Tabular Deliverables, Historical Analytics) was
converted from static mockups into a working feature with real DB persistence, per the PRD/ERD.
The former AGENTS.md **OPEN DECISION** ("whether the `Analytics` entity is actually used") is
**resolved**.

**OPEN DECISION resolved (approved):** the ERD `Analytics` entity **is used**. It is implemented as
`analytics` (report-generation history): analytics pages compute live from normalized operational
data, and a **Generate Report** action persists one snapshot per generation. Recorded in
`docs/CacaoTrack_ERD.md` §9.4.

**Approved analytics formulas (all centralized in `App\Support\AnalyticsMetrics`, RULE 6):**
1. Total Active Inventory = `Inventory::totalActiveQuantity()` (reused, not re-implemented).
2. Replanting Deficit = `Batch::replantingDeficit()` (reused).
3. **Crude Mortality Rate** = effective mortality ÷ total grafted × 100. The selected date range
   filters the mortality numerator; `batch`/`variety` filters define the population denominator.
4. **Gap** = max(0, `batch.viable_count` − `inventory.current_quantity`) per batch.
5. **Age** = reporting date − `batch.grafted_date`, in buckets 0–30 / 31–60 / 61–90 / 91+ days,
   counted as current inventory quantity.

**Files created:**
- `database/migrations/2026_09_14_000001_create_analytics_table.php`
  - Table `analytics` (ERD `Analytics`): `analytics_id` (PK, bigIncrements), `user_id` FK
    (`restrictOnDelete`), `analysis_type`, `analysis_period`, `result_summary` (text, cast to
    array/JSON), `generated_at` (indexed). No timestamps column.
- `app/Models/Analytic.php`
  - Custom PK `analytics_id`, no timestamps, `user()` relation, `casts()` (`result_summary` =>
    array, `generated_at` => datetime). `TYPES` constant + `typeLabel()`/`typeLabelText()`.
    `rules()` centralizes the Generate validation.
- `app/Support/AnalyticsMetrics.php` — the single place for every analytics calculation:
  `totalActiveInventory()`, `totalReplantingDeficit()`, `effectiveMortality()`,
  `mortalityRateComponents()`, `crudeMortalityRate()`, `mortalityTrend()`,
  `inventoryByVariety()`, `replantingDeficitByBatch()`, `survivalVsMortality()`,
  `mortalityMatrix()`, `mortalityYears()`, `seasonality()`, `ageProfile()`, `gapReport()`,
  `stockLedgerQuery()`, `summaryFor()`.
- `app/Http/Controllers/AnalyticsController.php`
  - `index` (real KPIs + 4 charts + recent generated reports), `stockLedger` (paginated per-batch
    summary, filters), `mortalityMatrix` (variety × cause), `seasonality` (year × variety heatmap),
    `ageProfile`, `gapReport`, and `generate` (persists a report snapshot). Filter validation is
    server-side (`exists`, date, `after_or_equal`).
- `tests/Feature/AnalyticsTest.php` — 16 tests: guest redirects (all 6 pages + generate), real KPIs,
  centralized metric values, ledger + batch filter, inverted date range, matrix grouping,
  seasonality real values + empty state, age buckets, gap report + invalid batch, generate snapshot
  + period label, invalid type, invalid date range, generated-report history listing.

**Files modified:**
- `routes/web.php` — the 6 static Analytics closures were replaced with `AnalyticsController`
  routes and moved **under the `auth` middleware** (they were previously unauthenticated); added
  `POST /analytics/reports` → `generate` (`analytics.reports.store`). Route names are unchanged so
  the sidebar continues to work.
- `resources/views/analytics/index.blade.php` — real KPI cards, real ECharts (Mortality Trend,
  Inventory by Variety, **Replanting Deficit by Batch**, Survival vs Mortality), real "Recent
  Generated Reports" table, Generate button, empty states.
- `resources/views/analytics/stock-ledger.blade.php` — real per-batch ledger (PRD §13.1: batch,
  variety, scion, grafted, viable, available, mortality, replanting, status, grafted date), filters
  (batch/variety/grafted-date range), pagination, CSV export, Generate button.
- `resources/views/analytics/mortality-matrix.blade.php` — real variety × cause matrix with
  row/column/grand totals, filters, Generate button.
- `resources/views/analytics/historical/seasonality.blade.php` — real year × variety heatmap,
  filters, empty state.
- `resources/views/analytics/historical/age-profile.blade.php` — real age-bucket bar chart,
  variety/batch/as-of filters.
- `resources/views/analytics/historical/gap-report.blade.php` — real total/expected/actual/gap cards
  and per-batch table, filters.
- `docs/CacaoTrack_ERD.md` §9.4 — Analytics entity resolution recorded.
- `AGENTS.md` — OPEN DECISION cleared; Analytics added as feature 14; remaining-static-pages note
  updated.

**Design decision (do not "fix" back):** the mock's **"Inventory Trend"** chart was replaced with
**"Replanting Deficit by Batch"**. There is no inventory-history table (only
`inventory.last_update`), so a real inventory-over-time trend cannot be produced; charting it would
fabricate data (RULE 5/12). All other mock datasets were replaced with real queries.

**Business-rule decisions made (per user approval before implementation):**
1. Implement the ERD `Analytics` entity as report-generation history (not a redundant reporting
   table, not unused).
2. Crude Mortality Rate = effective mortality ÷ total grafted × 100.
3. Gap = max(0, Viable_Count − Current_Quantity).
4. Age = today − Grafted_Date; count current inventory; buckets 0–30/31–60/61–90/91+.
5. Stock Ledger = per-batch summary (PRD §13.1), generated from normalized data.
6. No seed changes; no `migrate:fresh` was run (only `php artisan migrate` to add `analytics`).

### Session O — Stock Ledger verification (previous session)
**No code was changed and no migration was created.** The request was to "make the Stock Ledger
functional and create the necessary DB", but the page was **already functional** in the real app
root; the static page the user saw was the **stale duplicate checkout**.

**Evidence (real root `C:\laragon\www\Capstone\Capstone`):**
- Route `GET analytics/stock-ledger` is wired to `AnalyticsController@stockLedger`
  (`app/Http/Controllers/AnalyticsController.php:34`) — a controller, not a closure.
- `AnalyticsMetrics::stockLedgerQuery()` (`app/Support/AnalyticsMetrics.php:416`) builds the
  per-batch ledger from normalized operational data (`batches` + `inventories` + `varieties` +
  `scions`; mortality/replanting via the models) with batch / variety / grafted-date filters.
- The view (`resources/views/analytics/stock-ledger.blade.php`) has real filters, pagination,
  CSV export, and a Generate Report POST to the `analytics` table.
- Live check against `db_capstone`: `AnalyticsMetrics::stockLedgerQuery()->get()` returns
  `B001, B002, B003`.
- The **stale duplicate** is the static one: `capstone/routes/web.php:78` is a closure returning
  `view('analytics.stock-ledger')`, and `capstone/resources/views/analytics/stock-ledger.blade.php`
  still has demo `alert('...')` handlers and hardcoded dates. **Do not edit it** — switch the
  served root instead.

**DB decision (no new table):** per PRD §9 and §13.1, the Cacao Tree Stock Ledger is generated from
existing normalized operational data — no redundant ledger table was (or should be) created. The
only analytics entity remains `analytics` (report-generation history; ERD §9.4).

**Verification:** `AnalyticsTest` 16 passed / 83 assertions; full suite **176 passed / 621
assertions**; `vendor\bin\pint --test` clean. No migrations run; `db_capstone` data unchanged.

### Session P — Root-Cause Matrix verification (previous session)
**No code was changed and no migration was created.** The request was to "make the Mortality
Root-Cause Matrix functional and create the necessary DB," but the page was **already functional**
in the real app root; the static page the user saw was again the **stale duplicate checkout**.

**Evidence (real root `C:\laragon\www\Capstone\Capstone`):**
- Route `GET analytics/mortality-matrix` is wired to `AnalyticsController@mortalityMatrix`
  (`routes/web.php:95`) under the `auth` middleware — a controller, not a closure.
- `AnalyticsMetrics::mortalityMatrix()` (`app/Support/AnalyticsMetrics.php:211`) builds variety
  rows × cause columns from normalized data (`mortality_records` + `mortality_adjustments` via
  `effectiveQuantity()`, joined to `batches`/`varieties`), with batch / variety / date-range filters.
- The view (`resources/views/analytics/mortality-matrix.blade.php`) has real filters, a real table +
  grand total, an empty state, and a Generate Report POST to the `analytics` table.
- Live HTTP check against the running server (logged in as admin) returns the real page with
  `Generate Report` + live cells (`Criollo / Disease`) and **not** the stale
  `alert('Demo filtering applied.')`.
- Live data: `AnalyticsMetrics::mortalityMatrix()` returns Criollo/Disease 20, Trinitario/Drought 15,
  Forastero/Pest 10, grand total 45.
- The **stale duplicate** is the static one: `capstone/routes/web.php:82` is a closure returning
  `view('analytics.mortality-matrix')`, and
  `capstone/resources/views/analytics/mortality-matrix.blade.php` has hardcoded Criollo/Trinitario/
  Forastero rows and demo `alert(...)` handlers. **Do not edit it** — switch the served root instead.

**DB decision (no new table):** per PRD §9 and §13.2, the Mortality Root-Cause Matrix is generated
from existing normalized operational data — no redundant matrix/ledger table was (or should be)
created. The only analytics entity remains `analytics` (report-generation history; ERD §9.4).

**Verification:** `AnalyticsTest` 16 passed / 83 assertions; full suite **176 passed / 621
assertions**; `vendor\bin\pint --test` clean. No migrations run; `db_capstone` data unchanged.

### Session Q — Seasonality Heatmap verification (previous session)
**No code was changed and no migration was created.** The request was to "make the Seasonality
Heatmap functional and create the necessary DB," but the page was **already functional** in the real
app root; the static page the user saw was again the **stale duplicate checkout** (`Capstone\capstone`).

**Evidence (real root `C:\laragon\www\Capstone\Capstone`):**
- Route `GET analytics/historical/seasonality` is wired to `AnalyticsController@seasonality`
  (`routes/web.php:100`) under the `auth` middleware — a controller, not a closure (confirmed with
  `php artisan route:list --path=seasonality`; no route cache present).
- `AnalyticsController::seasonality()` (`app/Http/Controllers/AnalyticsController.php:83`) validates
  `year` (`nullable|integer|min:2000|max:2100`) plus the matrix rules (`batch_id`/`variety_id`
  `exists`, `date_from`/`date_to` dates), then calls `AnalyticsMetrics::seasonality()`.
- `AnalyticsMetrics::seasonality()` (`app/Support/AnalyticsMetrics.php:279`) builds the month ×
  variety matrix from normalized data (`mortality_records` + `mortality_adjustments` via
  `effectiveQuantity()`, joined to `batches`/`varieties`) with year / variety / batch filters.
  `AnalyticsMetrics::mortalityYears()` (`:263`) supplies the year dropdown.
- The view (`resources/views/analytics/historical/seasonality.blade.php`) has real filters
  (Year / Variety / Batch), a real ECharts heatmap with `visualMap` scaled to the real max, an empty
  state ("No data available for the selected period."), and a Generate Report POST to `analytics`.
- Live HTTP check against the running server on `127.0.0.1:8000` (logged in as admin) returned the
  real page: it matched "effective mortality across cacao varieties" and **not** the stale
  "historical mortality patterns across different varieties", and contained `name="year"` +
  `Generate Report`; the mock `[11,2,5]` dataset was absent.
- Live data: `AnalyticsMetrics::seasonality(2026)` returns year 2026 with Sep Criollo 20,
  Sep Trinitario 15, Aug Forastero 10 — `max` 20, `total` 45. (A 4th variety `Diaman` exists in
  `db_capstone` but has no mortality, so it contributes no heat cells.)
- The **stale duplicate** is the static one: `Capstone\capstone\routes\web.php:87` is a closure
  returning `view('analytics.historical.seasonality')`, and
  `Capstone\capstone\resources\views\analytics\historical\seasonality.blade.php` has a hardcoded
  `data` array (e.g. `[11,2,5]`) with a fixed `max: 30` and no filters. **Do not edit it** — switch
  the served root instead.

**DB decision (no new table):** per PRD §9 and §14.1, and consistent with ERD §9.4, the Mortality
Seasonality Heatmap is generated from existing normalized operational data — no redundant
seasonality/heatmap table was (or should be) created. The only analytics entity remains `analytics`
(report-generation history).

**Verification:** `AnalyticsTest` 16 passed / 83 assertions; full suite **176 passed / 621
assertions**; `vendor\bin\pint --test` clean. No migrations run; the `db_capstone` schema is
unchanged (the §3 data snapshot below reflects live app data as of this session).

### Session R — Age Profile verification (previous session)
**No code was changed and no migration was created.** The request was to "make the Age Profile
functional and create the necessary DB," but the page was **already functional** in the real app
root; the static page the user saw was again the **stale duplicate checkout** (`Capstone\capstone`).

**Evidence (real root `C:\laragon\www\Capstone\Capstone`):**
- Route `GET analytics/historical/age-profile` is wired to `AnalyticsController@ageProfile`
  (`routes/web.php:102`) under the `auth` middleware — a controller, not a closure (confirmed with
  `php artisan route:list --path=age-profile`).
- `AnalyticsController::ageProfile()` (`app/Http/Controllers/AnalyticsController.php:105`) validates
  `batch_id` (`exists:batches,batch_id`), `variety_id` (`exists:varieties,variety_id`) and `as_of`
  (`date`), then calls `AnalyticsMetrics::ageProfile()`.
- `AnalyticsMetrics::ageProfile()` (`app/Support/AnalyticsMetrics.php:340`) derives each batch's age
  from `batch.grafted_date` → reference date and counts `inventory.current_quantity` into buckets
  0–30 / 31–60 / 61–90 / 91+ days; batches with no `grafted_date` are skipped.
- The view (`resources/views/analytics/historical/age-profile.blade.php`) has real filters (Variety /
  Batch / As-Of), a real ECharts bar chart with tooltips + labels, an empty state ("No data available
  for the selected period."), and a Generate Report POST to `analytics`.
- Live HTTP check: `http://localhost:8000/analytics/historical/age-profile` returns **302 →
  /login** when signed out — proving the served root is the real (auth-protected) one; the stale
  closure would return 200 unauthenticated.
- Live data: `AnalyticsMetrics::ageProfile(null, null, '2026-09-14')` returns `[95, 140, 500, 0]`,
  total **735** (B003 95 / B002 140 / B001 500, matching `inventories.current_quantity`).
- The **stale duplicate** is the static one: `Capstone\capstone\routes\web.php:91` is a closure
  returning `view('analytics.historical.age-profile')`, and
  `Capstone\capstone\resources\views\analytics\historical\age-profile.blade.php` has a hardcoded
  dataset with no filters. **Do not edit it** — switch the served root instead.

**DB decision (no new table):** per PRD §9 and §14.2, and consistent with ERD §9.4, the Age Profile
is generated from existing normalized data (`batches.grafted_date` + `inventories.current_quantity`)
— no redundant age table was (or should be) created. The only analytics entity remains `analytics`
(report-generation history).

**Verification:** `AnalyticsTest` 16 passed / 83 assertions; full suite **176 passed / 621
assertions**; `vendor\bin\pint --test` clean. No migrations run; the `db_capstone` schema and data
are unchanged.

### Session S — Gap Report verification (previous session)
**No code was changed and no migration was created.** The request was to "make the Gap Report
functional and create the necessary DB" (the prompt named the Age Profile but gave the Gap Report
URL `/analytics/historical/gap-report`). An audit (PRD §27 Phase 1) showed **both** pages were
**already functional** in the real app root; the static page the user saw was again the **stale
duplicate checkout** (`Capstone\capstone`). Age Profile had already been re-verified in Session R.

**Evidence (real root `C:\laragon\www\Capstone\Capstone`):**
- The server on `http://localhost:8000` is `artisan serve` running from the **real root**
  (`...\Capstone\Capstone\vendor\laravel\framework\...\server.php`), confirmed via the process
  command line (PID 26656).
- Route `GET analytics/historical/gap-report` is wired to `AnalyticsController@gapReport`
  (`routes/web.php:104`) under the `auth` middleware — a controller, not a closure.
- `AnalyticsController::gapReport()` (`app/Http/Controllers/AnalyticsController.php:129`) validates
  `batch_id` (`exists:batches,batch_id`) and `variety_id` (`exists:varieties,variety_id`), then calls
  `AnalyticsMetrics::gapReport()`.
- `AnalyticsMetrics::gapReport()` (`app/Support/AnalyticsMetrics.php:385`) builds each row from
  `batch.viable_count` (expected) vs `inventory.current_quantity` (actual), `gap = max(0, expected −
  actual)`, plus grand totals.
- The view (`resources/views/analytics/historical/gap-report.blade.php`) has real filters (Variety /
  Batch), a real Total Gap card, Expected→Actual→Gap comparison, a per-batch table with footer
  totals, an empty state ("No data available for the selected period."), and a Generate Report POST
  to `analytics`.
- Live HTTP check: signed-out `GET /analytics/historical/gap-report` returns **302 → /login** —
  proving the served root is the real (auth-protected) one; the stale closure returns 200
  unauthenticated.
- Live signed-in fetch returns the real page: Expected **415**, Actual **735**, Gap **0**; the
  static mock values (`1,500` / `1,420` / `80`) are **absent** and `Generate Report` is present.
- Live data: `AnalyticsMetrics::gapReport()` returns B001 Criollo 180/500/0, B002 Trinitario
  140/140/0, B003 Forastero 95/95/0 — totals expected 415, actual 735, gap 0. (Inventory exceeds
  viable because of the seeded stock-in, so the gap floors at 0.)
- Age Profile re-confirmed: `AnalyticsMetrics::ageProfile(null, null, '2026-09-14')` returns
  `[95, 140, 500, 0]` (total 735); its view has `name="as_of"` + Generate Report.
- No route cache is present (`bootstrap/cache` holds only `packages.php` / `services.php`).
- The Laragon Apache vhost `Capstone.test` points at the **outer** folder
  (`C:/laragon/www/Capstone`, which has no `index.php`), so it is **not** a second source of the
  static page — the only running app server is the real root on `:8000`.
- The **stale duplicate** is the static one: `Capstone\capstone\routes\web.php:95` is a closure
  returning `view('analytics.historical.gap-report')`, and
  `Capstone\capstone\resources\views\analytics\historical\gap-report.blade.php` has hardcoded rows
  (B001 500/475/25, B002 400/385/15, B003 600/560/40; total 1,500/1,420/80). **Do not edit it** —
  switch the served root instead.

**DB decision (no new table):** per PRD §9 and §14.3, and consistent with ERD §9.4, the Gap Report
is generated from existing normalized operational data (`batches.viable_count` +
`inventories.current_quantity`) — no redundant gap table was (or should be) created. The only
analytics entity remains `analytics` (report-generation history).

**Verification:** `AnalyticsTest` 16 passed / 83 assertions; full suite **176 passed / 621
assertions**; `vendor\bin\pint --test` clean. No migrations run; the `db_capstone` schema and data
are unchanged.

### Session T — Activity History (previous session)
The Account → **Activity History** page (`/activity`, FR-03/FR-17/PRD §24) was converted from a
static mockup into a working read-only feature with real DB persistence, per the ERD.

**OPEN DECISIONS resolved (approved before implementation):**
1. **`activity_logs.batch_id` is nullable** — the ERD lists `Batch_ID` as an FK but does not mark it
   NOT NULL; non-batch activities (task completion, report generation) store NULL.
2. **Activity rows are written by the operations** (not derived) through one centralized writer
   `ActivityLog::record()` (RULE 6 / PRD §24).
3. **Backfill existing data** through a seeder that derives rows from real operational records.

**Files created:**
- `database/migrations/2026_09_14_000002_create_activity_logs_table.php`
  - Table `activity_logs` (ERD `Activity_Log`): `activity_id` (PK, bigIncrements), `user_id` FK
    (`restrictOnDelete`), `batch_id` FK (`restrictOnDelete`, **nullable**), `activity_type`
    (string), `description` (string), `activity_date` (datetime, indexed). No timestamps column.
- `app/Models/ActivityLog.php`
  - Custom PK `activity_id`, no timestamps, `user()` / `batch()` relations, `activity_date` cast.
  - `TYPES` map (single source of truth for the filter/badges) + `TYPE_*` constants,
    `typeLabel()`, `typeBadgeClass()`.
  - `record(?int $batchId, string $type, string $description, ?int $userId = null, $activityDate = null)`
    — the single writer; validates the type against `TYPES` (throws `InvalidArgumentException`) and
    defaults user to `Auth::id()` and date to now.
- `app/Http/Controllers/ActivityController.php`
  - `index` — validates filters (`search`, `user_id` `exists`, `activity_type` `in` TYPES,
    `batch_id` `exists`, `date_from`/`date_to` with `after_or_equal`); eager loads user + batch
    variety; paginated (10) `withQueryString()`; real KPIs (total activities, users involved,
    batches affected, activity types).
- `database/seeders/ActivityLogSeeder.php`
  - Backfills `activity_logs` from existing mortality records/adjustments, replanting, stock
    in/out/reversal and task completions via `firstOrCreate` (idempotent; real data only). Called
    from `DatabaseSeeder`.
- `tests/Feature/ActivityLogTest.php` — 16 tests: guest redirect, page render, real records,
  filters (type/user/batch/date range/search), invalid filters (bad user/batch/type and inverted
  date range), empty state, `record()` field correctness, unknown-type rejection, user/batch
  relations, and integration (mortality store, stock-in store, report generate write activity rows).

**Files modified:**
- `app/Models/Batch.php` — added `activityLogs()` (HasMany).
- `app/Models/User.php` — added `activityLogs()` (HasMany).
- `app/Http/Controllers/DailyChecklistController.php` — completion writes a `task_completed` row
  inside its DB transaction.
- `app/Http/Controllers/MortalityController.php` — `store` writes `mortality_recorded`, `adjust`
  writes `mortality_adjusted` (both inside their DB transactions).
- `app/Http/Controllers/ReplantingController.php` — `store` writes `replanting_recorded`.
- `app/Http/Controllers/StockInController.php` — `store` writes `stock_in_recorded` inside the
  inventory transaction.
- `app/Http/Controllers/StockOutController.php` — `store` writes `stock_out_recorded` inside the
  inventory transaction.
- `app/Http/Controllers/StockReversalController.php` — `store` writes `stock_reversal_recorded`
  inside the inventory transaction.
- `app/Http/Controllers/AnalyticsController.php` — `generate` writes `report_generated`.
- `routes/web.php` — the static unauthenticated `GET /activity` closure was replaced with
  `GET /activity` → `ActivityController@index` under the `auth` middleware (name `activity.index`
  unchanged).
- `resources/views/activity/index.blade.php` — fully rewritten: real KPI cards, real filters
  (search/user/type/batch/date range), paginated real-data table (Date / User / Activity badge /
  Batch / Description), real Details modal, empty state, reset link. Mock rows and
  `alert('Demo …')` handlers removed.
- `database/seeders/DatabaseSeeder.php` — now calls `ActivityLogSeeder` after the operational seed.

**Business-rule decisions made (per user approval before implementation):**
1. **Nullable `batch_id`** (ERD does not mark it NOT NULL; non-batch activities store NULL).
2. **Centralized writer** `ActivityLog::record()`; `TYPES` is the single allowed-type list.
3. **Activities are written by operations**, atomically for inventory-changing operations (inside the
   existing `DB::transaction` — PRD §11 step 8).
4. **Backfill is derived from real operational records** (no fabricated data — RULE 5/12).

**Verification:** `ActivityLogTest` 16 passed / 48 assertions; full suite **192 passed / 669
assertions**; `vendor\bin\pint --test` clean. Migration applied to `db_capstone`
(`php artisan migrate`); `ActivityLogSeeder` backfilled **10** rows (3 mortality, 1 adjustment,
3 replanting, 1 stock-in, 1 stock-out, 1 reversal). Live HTTP check: signed-out
`GET /activity` → 302 `/login` (real, auth-protected root); signed-in fetch renders real rows
(e.g. "Recorded 25 mortality for B001 due to Disease.", "Received 1000 trees from Doms for
B001.") and no demo alerts.

### Session U — Profile (previous session)
The Account → **Profile** page (`/profile`, ERD `Users` / PRD §7 Account) was converted from a
static mockup into a working own-account feature with real DB persistence.

**DB decision (no new table):** the ERD's 18 entities contain no `Profile` — Profile is the
signed-in user's own `Users` row. Creating a `profiles` table would be a redundant table (PRD §9),
so none was created.

**Approved schema alignment (not an invention):** the ERD `Users` entity defines `Role` (ERD §2.1)
but the Laravel `users` table had no role column (the known §6 mismatch). A migration adds
`role` (`string(50)`, default `Staff`) to `users` — this resolves the mismatch toward the ERD;
no `Roles` table was added (ERD §9.1 keeps `Role` inside `Users`).

**Files created:**
- `database/migrations/2026_09_15_000001_add_role_to_users_table.php`
  - Adds `role` to `users` (default `Staff`). `down()` drops the column.
- `app/Http/Controllers/ProfileController.php`
  - `index` (real `Auth::user()`), `update` (validates via `User::profileRules()` into the
    `profile` error bag; persists only `$validator->validated()`; writes a `profile_updated` row
    inside a `DB::transaction`), `updatePassword` (validates via `User::passwordRules()`
    — `current_password` check, `min:8`, `confirmed` — into the `password` error bag; hashed by
    the model's `hashed` cast; writes a `profile_updated` row inside a `DB::transaction`).
- `tests/Feature/ProfileTest.php` — 13 tests: guest redirects (view/update/password), real values,
  update success + activity row, required/invalid validation, email-taken rejection, own-role
  escalation ignored, password success + activity row, wrong-current/mismatch/short rejection,
  `ROLES`/`roleLabel`/`TYPES` helpers.

**Files modified:**
- `app/Models/User.php` — `role` added to `$Fillable` (safe: the controller persists only
  validated name/email, so role is never mass-assignable from Profile requests), `ROLES`
  constant (`Administrator`, `Manager`, `Staff`), `roleLabel()`, centralized `profileRules()` +
  `passwordRules()` (RULE 6).
- `app/Models/ActivityLog.php` — `profile_updated` added to `TYPES` (+ `TYPE_PROFILE_UPDATED`
  constant and badge class). The Activity History filter picks it up automatically.
- `database/seeders/DatabaseSeeder.php` — admin `updateOrCreate` now sets `role` to
  `Administrator` (idempotent backfill on re-seed).
- `routes/web.php` — the static unauthenticated `GET /profile` closure was replaced with
  `GET /profile` → `ProfileController@index` plus `PUT /profile` → `@update`
  (`profile.update`) and `PUT /profile/password` → `@updatePassword`
  (`profile.password.update`), all under the `auth` middleware (name `profile.index` unchanged
  so the sidebar keeps working). `/profile` was the last unauthenticated static closure —
  only `/` (landing) remains a closure.
- `resources/views/profile/index.blade.php` — fully rewritten: real avatar initial/name/email/
  role badge/Member Since (`created_at`), read-only role with a "Roles are assigned under Users
  management" note, Edit Profile modal (real PUT, `old()` + `profile`-bag errors + reopen),
  Change Password modal (current/new/confirm, `password`-bag errors + reopen), flash +
  validation alerts, empty-value guards, double-submit guards. Mock values and all demo
  `alert()` handlers removed (including the "Role (Demo)" select).
- `resources/views/layouts/navbar.blade.php` — the Profile dropdown item now links
  `route('profile.index')` (was `href="#"`). Settings and the notification bell stay mocks.

**Business-rule decisions made (per user approval before implementation):**
1. **No `profiles` table** — Profile is the own `Users` row (ERD has no Profile entity).
2. **Add `role` to `users`** (ERD §2.1 alignment), default `Staff`; admin backfilled to
   `Administrator`. No `Roles` table (ERD §9.1).
3. **Role is display-only on Profile** — a user can never change their own role there (privilege
   escalation); role assignment belongs to FR-02 Users management.
4. **Profile edits write `profile_updated` activity rows** (NULL `batch_id`, like task
   completions) through `ActivityLog::record()`.

**Verification:** `ProfileTest` 13 passed / 55 assertions; full suite **205 passed / 724
assertions**; `vendor\bin\pint` clean. Migration applied to `db_capstone`
(`php artisan migrate`); admin role backfilled to `Administrator` via tinker (targeted update —
no re-seed, all other `db_capstone` data untouched). `route:list --path=profile` confirms
`GET profile` → `ProfileController@index`, `PUT profile` → `@update`,
`PUT profile/password` → `@updatePassword`.

### Session V — Handoff refresh (previous session)
**No code was changed and no migration was created.** The request was to update this handoff file
so a new chat can continue the vibecoding session. Session U (Profile) was the latest build at the
time.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **205 passed / 724 assertions** (matches Session U — no drift).
- `vendor\bin\pint --test` clean.
- `route:list --path=profile` confirms the three Profile routes
  (`GET` → `index`, `PUT /profile` → `update`, `PUT /profile/password` → `updatePassword`).
- `migrate:status` confirms all 10 project migrations Ran, including
  `2026_09_15_000001_add_role_to_users_table`.
- `db_capstone` untouched: admin role is still `Administrator`, and `profile_updated` rows = 0
  (no profile edits have been made in prod data since the build — nothing fabricated).
- Also fixed a stale Quick-start line: the Analytics re-verification note named only Sessions
  O/P/Q; all five verifications (O–S) are now named.

### Session W — Profile verification (previous session)
**No code was changed and no migration was created.** The request was to "make the `/profile`
page functional and create the necessary DB", but the page was **already functional** in the real
app root; the static page the user saw was again the **stale duplicate checkout**
(`Capstone\capstone`).

**Evidence (real root `C:\laragon\www\Capstone\Capstone`):**
- Route `GET /profile` is wired to `ProfileController@index` (`routes/web.php:114`) under the
  `auth` middleware — a controller, not a closure (confirmed with
  `php artisan route:list --path=profile`: `GET profile` → `index`, `PUT profile` → `update`,
  `PUT profile/password` → `updatePassword`).
- `ProfileController` (`app/Http/Controllers/ProfileController.php:22,51`) uses
  `$request->user()` (never a submitted user ID), validates via `User::profileRules()` (required
  name, valid email, unique-ignore-self) into the `profile` bag and `User::passwordRules()`
  (`current_password` check, `min:8`, `confirmed`) into the `password` bag, persists only
  `$validator->validated()` (own-role escalation impossible), hashes via the model's `hashed`
  cast, and writes `profile_updated` rows through `ActivityLog::record()` inside a
  `DB::transaction`.
- `User::ROLES` (`app/Models/User.php:29`) is `['Administrator', 'Manager', 'Staff']`
  (ERD §2.1, no `Roles` table per §9.1); role is display-only on Profile.
- The view (`resources/views/profile/index.blade.php`) has real values, role badge, Edit Profile
  + Change Password modals, and no plaintext password exposure.
- The **stale duplicate** is the static one: `Capstone\capstone` holds the old mockup.
  **Do not edit it** — switch the served root instead.

**DB decision (no new table):** per ERD §2.1 and §9.1, Profile is the signed-in user's own
`Users` row — no `profiles` table was (or should be) created. The only Profile-related schema
is the existing `users.role` column (`2026_09_15_000001_add_role_to_users_table`, default
`Staff`, admin backfilled to `Administrator`).

**Verification:** `ProfileTest` 13 passed / 55 assertions; full suite **205 passed / 724
assertions**; `vendor\bin\pint --test` clean. No migrations run; `db_capstone` schema unchanged
(all 21 migrations Ran, including `2026_09_15_000001`). Live `db_capstone` check: admin role is
still `Administrator`, `profile_updated` rows = 0, `users` = 1; `activity_logs` = 11 (the Session
T backfill 10 plus 1 `report_generated` row — §3 corrected below).

### Session X — Users Management (previous session)
The Account → **User Management** page (`/users`, FR-02) was built from the frontend-only
preview into a working full-stack feature with real DB persistence, per the ERD/PRD. The
`/users` URL previously 404'd (no routes); the sidebar link used `url('/users')` as a
placeholder.

**DB decision (no new table, no migration):** ERD `Users` §2.1 (`User_ID`, `Full_Name`,
`Email`, `Password`, `Role`, `Created_At`) is already the `users` table (`id`, `name`,
`email` unique, `password` hashed via cast, `role` default `Staff` via
`2026_09_15_000001`, timestamps). No `Roles` table per ERD §9.1. Creating another table
would be redundant (PRD §9, RULE 5). Validated with `migrate:status` (both users migrations
Ran) and `route:list --path=users` (4 routes).

**Files created:**
- `app/Http/Controllers/UserController.php`
  - `index` (search name/email + `role` in `User::ROLES` filter, `withCount('activityLogs')`,
    `orderBy('name')`, paginated 10 `withQueryString()`, real per-role KPI counts).
  - `store` (Administrator-only, `User::managementCreateRules()` into the `user` bag;
    `user_created` activity row inside a `DB::transaction`).
  - `update` (Administrator-only, `User::managementUpdateRules($id)` + `edit_user_id` reopen;
    rejects own-role changes and last-admin demotions; `user_updated` row in transaction).
  - `destroy` (Administrator-only; blocks self-delete, last-admin delete, and users with
    operational records across `batches`/`mortality_records`/`replanting_records`/`stock_ins`/
    `stock_outs`/`stock_reversals`/`analytics`/`activity_logs`/`task_completes`/
    `mortality_adjustments.approved_by_id` — all `restrictOnDelete`; `user_deleted` row in
    transaction).
  - `requireAdmin()` aborts 403 for non-Administrators (FR-02/PRD §21; `User::ROLES` only,
    no invented permissions — RULE 3).
- `tests/Feature/UserManagementTest.php` — 16 tests: guest redirects (view/store), non-admin
  read-only view (no Add button) + 403 on store/update/destroy, admin create (hashed password
  + activity row), duplicate-email/invalid-role/short-password rejection, update + activity
  row, own-role-change rejection, self-delete rejection, delete-blocked-with-batch,
  delete-unused success + activity row, search/role filters, empty state.

**Files modified:**
- `app/Models/User.php` — `managementCreateRules()` (name/email-unique/role-in-ROLES/
  password `min:8` `confirmed`) + `managementUpdateRules(?ignoreId)` (name/email/role only;
  password changes stay on Profile) — RULE 6.
- `app/Models/ActivityLog.php` — `user_created`/`user_updated`/`user_deleted` added to
  `TYPES` (+ `TYPE_*` constants, badge classes). No schema change (`activity_type` is a free
  string per ERD `Activity_Log`).
- `routes/web.php` — `GET /users` → `index` (`users.index`), `POST /users/manage` → `store`,
  `PUT /users/{user}` → `update`, `DELETE /users/{user}` → `destroy`, all under `auth`.
- `resources/views/users/index.blade.php` — preview banner removed; wired to real
  `users.*` routes with real KPIs/table/pagination; Add/Edit/Delete admin-gated;
  View/Edit/Delete/Details modals + `user`-bag reopen + double-submit guards retained.
- `resources/views/layouts/sidebar.blade.php` — User Management link (below Activity History)
  switched from `url('/users')` to `route('users.index')`.
- `AGENTS.md` — feature 17 documented; remaining-greenfield note narrowed to Batches.

**Business-rule decisions made (per user approval before implementation):**
1. **No new table/migration** — reuse `users` (ERD §2.1 already implemented).
2. **Listing readable by all authenticated users; mutations Administrator-only** (403).
3. **No password editing in management** — Add sets the initial password; changes stay on
  Profile (avoids extra plaintext handling).
4. **Self-delete, self-role-change, last-admin demote/delete, and
   operational-record delete are all blocked** with readable messages.
5. **Mutations write activity rows** (`user_created/updated/deleted`, NULL `batch_id`).

**Verification:** `UserManagementTest` 16 passed / 52 assertions; full suite **221 passed /
776 assertions**; `vendor\bin\pint --test` clean; `view:cache` clean. No migrations run;
`db_capstone` data untouched (all writes happened on `db_capstone_test` via RefreshDatabase).

### Session Y — Handoff refresh (previous session)
**No code was changed and no migration was created.** The request was to update this handoff file
so a new chat can continue the vibecoding session. Session X (Users Management) is still the
latest build.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **221 passed / 776 assertions** (matches Session X — no drift).
- `vendor\bin\pint --test` clean.
- `route:list --path=users` confirms the four Users routes (`GET` → `index`,
  `POST /users/manage` → `store`, `PUT /users/{user}` → `update`,
  `DELETE /users/{user}` → `destroy`).
- `migrate:status` confirms all 21 migrations Ran (batches 1–10 incl. `users` table +
  `role` column); nothing pending.
- `db_capstone` live counts re-checked via tinker: users 1 (admin `Administrator`), batches 3,
  mortality 3 + 1 adjustment, replanting 3, inventories 3 (active total **735**), suppliers 5,
  stock_ins 1, clients 4, stock_outs 1, stock_reversals 1, `profile_updated` 0, user-management
  activity rows 0 — all match §3.
- **Snapshot correction:** `analytics` = **2** rows (was recorded as 1 in §3). Row #1
  (`generated_at` 2026-09-14) dates from Session N, before report generation wrote activity
  rows; row #2 (`generated_at` 2026-09-15 07:41 UTC) was created through the real Generate
  Report action — its timestamp exactly matches the single `report_generated` activity row, so
   the pairing is intact and `activity_logs` stays at 11. §3 corrected below. No data was
   fabricated; no rows were added or removed by this session.

### Session Z — Batch Management (previous session)
The Management → **Batches** page (`/inventory/batches`, FR-16) was built as a **greenfield**
full-stack feature. No page, route, controller or view existed (it was the last page without a
backend).

**DB decision (no new table, no migration):** the ERD `Batch` entity §2.6 (`Batch_ID`, `Variety_ID`,
`Scion_ID`, `Pot_ID`, `User_ID`, `Batch_Code`, `Total_Grafted`, `Viable_Count`, `Available_Count`,
`Grafted_Date`, `Created_At`, `Status`) is already the `batches` table from the Batch Foundation
session, and PRD FR-16 lists exactly those attributes. Creating another table would be redundant
(PRD §9, RULE 4/9), so none was created and no migration was run. Validated with `migrate:status`
(21 migrations, nothing pending).

**Approved business rules (user-confirmed before implementation):**
1. `Status` allowed values = `active` / `inactive` (ERD declares the column but no enumeration).
2. `total_grafted` ≥ 1; `viable_count` in `0..total_grafted`; **`available_count` is derived**
   server-side as `viable_count` (never submitted) and the single `inventories` row is created /
   re-synced through `Inventory::syncFromBatch()` (one row per batch — ERD §9.3).
3. Master fields are always editable; `total_grafted`/`viable_count` are **locked once the batch
   has operational records** (mortality, replanting, stock in/out).
4. Deletion is **blocked** when operational records exist; otherwise the batch and its inventory
   row are deleted together.
5. Mutations are allowed for any authenticated user (consistent with the other Management pages).
6. Mutations write Activity History rows (PRD §24).

**Files created:**
- `app/Http/Controllers/BatchController.php`
  - `index` — filters (`search` batch code, `variety_id` `exists`, `scion_id` `exists`,
    `status` `in Batch::STATUSES`), eager loads variety/scion/pot_type/inventory,
    `withCount(mortalityRecords, replantingRecords)`, `withExists(Batch::OPERATIONAL_RELATIONS)`
    (avoids N+1 for the lock flag), paginated (10) `withQueryString()`, real KPI totals (total /
    active / inactive / with mortality).
  - `store` — validates via `Batch::rules()` into the `batch` error bag; inside a `DB::transaction`
    creates the batch (`user_id` = signed-in user, `created_at` = now,
    `available_count` = `viable_count`), initializes `inventories` via `Inventory::syncFromBatch()`,
    and writes a `batch_created` activity row.
  - `update` — `Batch::rules($batch->batch_id)`; while the batch is unused the counts are editable
    and the inventory is re-synced; once used the count rules are dropped and a changed count is
    rejected with a readable message; master fields stay editable. Writes `batch_updated`.
  - `destroy` — blocks deletion when `$batch->hasOperationalRecords()`, otherwise deletes the
    inventory row + batch in a transaction and writes `batch_deleted`.
- `tests/Feature/BatchManagementTest.php` — 19 tests: guest redirect, view + real values, create +
  inventory init from viable count, create activity row, required-field validation, duplicate
  batch-code, viable > total, future grafted date, invalid status, invalid FKs, update + resync
  when unused, counts locked once used, master fields editable when used, delete unused (batch +
  inventory) + activity row, delete blocked when used, search filter, status filter, invalid
  filters, empty state.

**Files modified:**
- `app/Models/Batch.php` — added `STATUSES`/`STATUS_ACTIVE`/`STATUS_INACTIVE`,
  `OPERATIONAL_RELATIONS`, `hasOperationalRecords()` (uses loaded `*_exists` counts when present,
  else queries), and centralized `rules(?int $ignoreId)` (unique `batch_code` ignores self).
  Existing relations/formulas untouched.
- `app/Models/ActivityLog.php` — added `batch_created`/`batch_updated`/`batch_deleted` to `TYPES`
  (+ `TYPE_*` constants and badge classes). No schema change (`activity_type` is a free string).
  Note: these rows are written with a **NULL `batch_id`** (batch code in the description) because
  `activity_logs.batch_id` is `restrictOnDelete`; a set FK would make every created batch
  undeletable. This keeps the audit trail while allowing deletion of an unused batch.
- `routes/web.php` — added `GET /inventory/batches` (`inventory.batches`),
  `POST /inventory/batches/manage` (`inventory.batches.manage.store`),
  `PUT /inventory/batches/{batch}` (`inventory.batches.update`),
  `DELETE /inventory/batches/{batch}` (`inventory.batches.destroy`) — all under `auth`,
  route-model binding by `batch_id`.
- `resources/views/inventory/batches/index.blade.php` — new real view: 4 KPI cards, filters
  (search/variety/scion/status), real-data table (code/variety/scion/pot/grafted/total/viable/
  available/status/actions), Add/Edit/Delete/Details modals with `old()` + `batch`-bag reopen,
  edit modal disables the locked counts and shows a notice, delete modal disables the button when
  used, empty state, double-submit guards.
- `resources/views/layouts/sidebar.blade.php` — Batch link added to the Management dropdown.
- `AGENTS.md` — feature 18 documented; "only remaining page" note replaced (all Management pages
  now have backend).

**Verification:** `BatchManagementTest` 19 passed / 79 assertions; full suite **240 passed / 855
assertions**; `vendor\bin\pint --test` clean; `view:cache` clean; `route:list --path=batches`
shows the 4 routes. Live HTTP check against the running server (logged in as admin):
`GET /inventory/batches` returns 200 with "Batch Directory", "Add Batch" and the real `B001` row;
signed-out returns a login redirect. No migration run; `db_capstone` unchanged (batches 3,
inventories 3, activity_logs 11, analytics 2).

### Session AA — Dashboard (previous session)
The **Dashboard** (`/`, FR-03 / PRD §12 / §24) was converted from an entirely static mockup into a
real, protected feature using existing normalized data.

**DB decision (no new table, no migration):** the ERD's 18 entities contain **no Dashboard entity**,
and PRD §9 forbids creating redundant tables merely because a report/chart exists — the dashboard is
an aggregation over existing tables (`inventories`, `batches`, `mortality_records` +
`mortality_adjustments`, `replanting_records`, `varieties`, `activity_logs`). No migration was run;
no schema change.

**Files created:**
- `app/Http/Controllers/DashboardController.php`
  - `index` — read-only. Reuses the centralized `App\Support\AnalyticsMetrics` methods
    (`totalActiveInventory`, `crudeMortalityRate`, `totalReplantingDeficit`, `mortalityTrend(6)`,
    `inventoryByVariety`, `survivalVsMortality`) and `ActivityLog::with(['user','batch'])
    ->orderByDesc('activity_date')->limit(5)`. No formula is re-implemented (RULE 6) and nothing is
    hardcoded (RULE 5).
- `tests/Feature/DashboardTest.php` — 7 tests: guest redirect; authenticated view; KPI values equal
  the centralized formulas; charts render with real data (trend total, inventory-by-variety values,
  survival/mortality split); empty states + no chart containers when there is no data; the old mock
  values (`12,450`, `8.4%`, `5,200`, `Supplier A`) are absent; recent activities come from the real
  audit trail and are limited to 5 (newest first).

**Files modified:**
- `routes/web.php` — the static `GET /` closure was replaced with an `auth`-wrapped
  `GET /` → `DashboardController@index` (route name `dashboard` unchanged, so login redirects and
  the sidebar keep working). `/` was the last route closure in the file; no route closure remains.
- `resources/views/dashboard/index.blade.php` — fully rewritten: real KPI cards (Total Active
  Inventory / Crude Mortality Rate / Replanting Deficit), real ECharts (Mortality Trend last 6
  months, Survival vs Mortality, Inventory by Variety), real Recent Activities list (badge via
  `typeBadgeClass()`, label via `typeLabel()`, batch code, human-readable time, "View All" →
  `activity.index`), PRD §16 empty states ("No data available for the selected period." /
  "No activities have been recorded yet."), a chart resize handler, and a real "View Analytics"
  link. The dead dropdown (`Export Data` / `View Full Report` `href="#"`) and all hardcoded
  mock rows/datasets were removed.
- `tests/Feature/ExampleTest.php` — `/` now asserts a redirect to `login` (it was `assertStatus(200)`
  when `/` was a public closure).
- `AGENTS.md` — Dashboard added as feature 19; "only `/` remains a static closure" note replaced.

**Business-rule decisions made (per user approval before implementation):**
1. **No new table/migration** — the Dashboard is an aggregation over existing normalized data
   (ERD has no Dashboard entity; PRD §9, RULE 4).
2. **`/` is protected by `auth`** (FR-01) like every other data page; guests are redirected to
   `/login`.
3. **Recent Activities uses the real `activity_logs` audit trail** (latest 5) instead of mock rows
   (PRD §24).
4. **Keep the existing three charts** (Mortality Trend, Survival vs Mortality, Inventory by
   Variety) fed by the already-centralized formulas — no invented chart.
5. **Dashboard stays read-only** (no Generate Report action; PRD §12 only requires the three KPIs
   and report generation already lives under `/analytics`).

**Verification:** `DashboardTest` 7 passed / 40 assertions; `ExampleTest` 1 passed (updated);
full suite **247 passed / 896 assertions**; `vendor\bin\pint --test` clean; `view:cache` clean;
`route:list --path=/` shows `GET / → DashboardController@index`; `migrate:status` shows all 21
migrations Ran with nothing pending (no migration run). Live HTTP checks against the running server
(real root, `:8000`): signed-out `GET /` → **302 /login** (auth-protected); signed-in `GET /`
returns **200** with real KPIs `585` / `5.63%` / `11` and the `mortalityTrendChart` container — the
old mock values are absent. `db_capstone` was **not** modified by this session (read-only
dashboard); live data has drifted since the Session Z snapshot (see §3).

### Session AB — Handoff refresh (previous session)
**No code was changed and no migration was created.** The request was to refresh this handoff so a
new chat can continue the vibecoding session. Session AA (Dashboard) was the latest build at the
time.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **247 passed / 896 assertions** (matches Session AA — no drift).
- `vendor\bin\pint --test` clean.
- `route:list` shows **65 routes**, and `routes/web.php` holds **no route closures** — the Dashboard
  is `Route::middleware('auth')->get('/', [DashboardController::class, 'index'])->name('dashboard')`
  (`routes/web.php:25`); every other page is a controller action.
- `migrate:status`: all project migrations Ran (through `2026_09_15_000001_add_role_to_users_table`),
  nothing pending.
- Live `db_capstone` snapshot re-read via tinker and **matches §3 exactly**: users 2 (admin
  `Administrator`, `test@gmail.com` `Staff`), batches 4 (B001–B004), mortality 3 + 1 adjustment,
  replanting 3, inventories 4 (active total **585**), suppliers 5, stock_ins 1, clients 4,
  stock_outs 2, stock_reversals 1, analytics 3, activity_logs 16 (`profile_updated` = 0).
- Dashboard KPIs re-computed live: Total Active Inventory **585**, Crude Mortality Rate **5.63%**,
  Replanting Deficit **11**.

**DB decision:** none — this session is verification only. No migration was run and `db_capstone`
was not modified.

### Session AC — Notifications + Settings (previous session)
The navbar **notification bell** and **Settings** link (both mocks) were converted into real,
authenticated features with persistent DB storage.

**OPEN DECISION resolved (approved ERD revision):** neither a notification nor a settings entity
exists in the 18-entity ERD, and the PRD does not define them. The revision (recorded in
`docs/CacaoTrack_ERD.md` §2.1 / §2.19 / §9.5 **before** the migrations) adds:
- `Notifications` (`notifications`): `notification_id` PK, `user_id` FK (`cascadeOnDelete`),
  nullable `batch_id` FK (`restrictOnDelete`), `activity_type`, `title`, `message`, `is_read`,
  nullable `read_at`, `created_at`; index `(user_id, is_read)`.
- `Users.Settings` (`users.settings` JSON, nullable).

**Approved scope (user-confirmed before implementation):**
1. ERD-native `notifications` table + centralized `Notification::record()` writer (not Laravel's
   built-in notification table).
2. Settings stored as a JSON column on `users` (no separate table).
3. Notifications mirror `ActivityLog` types and go to **all users except the actor**; account/
   audit-only types (`profile_updated`, `user_created/updated/deleted`) are **excluded**
   (`ActivityLog::NOTIFIABLE_TYPES`).

**Files created:**
- `database/migrations/2026_09_16_000001_add_settings_to_users_table.php` — adds nullable
  `settings` JSON to `users` (after `role`).
- `database/migrations/2026_09_16_000002_create_notifications_table.php` — the `notifications`
  table described above.
- `app/Models/Notification.php` — custom PK, `user()`/`batch()` relations, `typeLabel()` /
  `typeBadgeClass()` (reusing the `ActivityLog` vocabulary/badges, RULE 6), `isUnread()`,
  `markRead()`, and the single writer `record(?int $batchId, string $activityType, string $title,
  string $message, ?int $actorId)` (validates the type, resolves recipients = all users except the
  actor, filters each by `User::wantsNotification()`, returns the number created).
- `app/Http/Controllers/NotificationController.php` — `index` (own rows only, filters
  `status` ∈ {unread, read} + `activity_type` ∈ NOTIFIABLE_TYPES, paginated, real KPIs),
  `markAsRead` (403 for another user's row), `markAllRead`.
- `app/Http/Controllers/SettingsController.php` — `index`, `update` (validates via
  `User::settingsRules()` into the `settings` bag, persists the JSON, writes a `settings_updated`
  activity row inside a DB transaction).
- `resources/views/notifications/index.blade.php` — real filterable/paginated table, KPIs,
  mark-read/mark-all, empty state.
- `resources/views/settings/index.blade.php` — master toggle + per-type checkboxes, real
  validation/old-input handling, double-submit guard, account summary.
- `tests/Feature/NotificationTest.php` (17 tests) + `tests/Feature/SettingsTest.php` (8 tests).

**Files modified:**
- `app/Models/ActivityLog.php` — added `NOTIFIABLE_TYPES` (single source of truth for alert-worthy
  types), `TYPE_SETTINGS_UPDATED` (+ `TYPES`/badge), static `badgeClassFor()`, and the notification
  emission inside `record()` (only notifiable types; inside the caller's DB transaction). The
  backfill seeder bypasses `record()` (uses `firstOrCreate`), so history never re-alerts.
- `app/Models/User.php` — `settings` fillable + `array` cast, `appNotifications()` relation
  (named to avoid clashing with the `Notifiable` trait), `wantsNotification()`, centralized
  `settingsRules()`.
- `app/Models/Batch.php` — `notifications()` relation.
- `app/Providers/AppServiceProvider.php` — `layouts.navbar` view composer sharing
  `navUnreadCount` + latest 5 notifications.
- `routes/web.php` — `GET /notifications`, `POST /notifications/read-all`,
  `POST /notifications/{notification}/read`, `GET /settings`, `PUT /settings` (all under `auth`).
- `resources/views/layouts/navbar.blade.php` — real bell (real count, latest list, mark-read,
  view-all) and Settings link → `route('settings.index')`.
- `tests/Feature/ActivityLogTest.php` — the `filter-by-user` test now asserts on the filtered
  page dataset instead of `assertDontSee`, because the navbar bell legitimately renders
  notification text (incl. "Staff action.") on every page.
- `docs/CacaoTrack_ERD.md` — revision: §2.1 `Settings`, §2.19 `Notifications`, FK map, relationship
  summary, Mermaid entity/edges, §9.5.
- `AGENTS.md` — features 20/21 + revision notes.

**Business-rule decisions made (per user approval before implementation):**
1. **No fabricated notifications** — every row derives from a real `ActivityLog::record()` action
   (the `message` is the recorded description); the backfill seeder never raises alerts.
2. **Recipients = all users except the actor**, filtered by each recipient's `settings`
   (`User::wantsNotification()`); a user who never saved preferences receives everything.
3. **Account/audit-only activity types are not notifiable** (`ActivityLog::NOTIFIABLE_TYPES`).
4. **`notifications.user_id` cascades on delete** (transient per-recipient alerts, not
   authoritative history) to avoid a hidden FK failure in FR-02 user deletion; `batch_id` stays
   `restrictOnDelete` (only set for operational events on undeletable batches).
5. **Settings store only notification preferences** — no separate settings table (RULE 4), and
   role/profile editing stays on Profile / Users management.
6. **Bell is server-rendered** (view composer), not AJAX/SPA (PRD §4).

**Verification:** `NotificationTest` 17 passed, `SettingsTest` 8 passed; full suite **272 passed /
970 assertions**; `vendor\bin\pint --test` clean; `view:cache` clean; `route:list` shows the 3
notifications + 2 settings routes. `php artisan migrate` applied both migrations to `db_capstone`
(schema only — `notifications` = 0 rows, `users.settings` = NULL for both users, `activity_logs`
still 16, no data fabricated). Live checks against the running server (`127.0.0.1:8000`):
signed-out `GET /notifications` → login page (auth-protected); signed-in as admin
`GET /notifications` → 200 with the real page + empty state, `GET /settings` → 200 with the real
preferences form.

### Session AD — Handoff refresh (previous session)
**No code was changed and no migration was created.** The request was to update this handoff so a
new chat can continue the vibecoding session. Session AC (Notifications + Settings) was the latest
build at that time (it was **later reverted in Session AF**); this session only re-verified the
state and added the missing Quick-start entry for the (now-removed) features 20/21.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **272 passed / 970 assertions** (matches Session AC — no drift).
- `vendor\bin\pint --test` clean.
- `route:list` shows **70 routes** (was 65 in Session AB, +5 from Session AC):
  `GET notifications`, `POST notifications/read-all`, `POST notifications/{notification}/read`,
  `GET settings`, `PUT settings` — all under `auth`.
- `migrate:status`: all project migrations Ran, including
  `2026_09_16_000001_add_settings_to_users_table` and
  `2026_09_16_000002_create_notifications_table`; nothing pending.
- Live `db_capstone` counts re-read via tinker and **match §3**: users 2 (`settings` NULL for
  both), `notifications` 0 (new table — no fabricated rows), `activity_logs` 16, `batches` 4.

**DB decision:** none — this session is verification only. No migration was run and `db_capstone`
was not modified.

### Session AE — Daily Checklist archive/restore + printable PDF export (previous session)
The Operational → **Daily Checklist** (`/operational/checklist`, FR-03) was refined: its hard
**Delete** was replaced with **Archive/restore**, and a **printable FPDF daily checklist** was
added. The checklist itself was already functional (Session A); this is a scoped refinement
(RULE 9), not a rebuild.

**OPEN DECISION resolved (approved ERD revision):** the source ERD's `Activity_Task` carried only
`Task_ID`, `Task_Name`, `Description`. Recording archive/restore required two attributes on the
existing table (no new table — RULE 4), documented in `docs/CacaoTrack_ERD.md` §2.4/§9.6 **before**
the migration:
- `Priority` (`high`/`medium`/`low`) — the checklist ordering/badge attribute already implemented
  in Session A but not previously recorded in the ERD doc.
- `Archived_At` (nullable datetime) — `NULL` = active; a set value hides the task from the active
  checklist while preserving its `Task_Complete` history. Hard delete is gone, so completion
  history can never be destroyed.
The Daily Checklist PDF is generated from existing normalized `activity_tasks` + `task_completes`
data — no report table was (or should be) created (PRD §9).

**Files created:**
- `database/migrations/2026_09_16_000003_add_archived_at_to_activity_tasks_table.php` — adds
  nullable, indexed `archived_at` after `priority`.
- `app/Support/ChecklistPdf.php` — `App\Support\ChecklistPdf extends FPDF`: header (CacaoTrack /
  Daily Activity Checklist / date / generated by), a table (# / Task+Description / Priority /
  Status / Completed+Verified By / Remarks) with wrapped-cell row heights, summary counts and a
  signature block. Real passed-in data only (RULE 5/12); UTF-8 text is transliterated to
  ISO-8859-1 for the core FPDF fonts; compression disabled so tests can assert the rendered text.

**Files modified:**
- `composer.json` / `composer.lock` — added `setasign/fpdf` (1.9.0; classic global `FPDF` class;
  needs `ext-zlib` + `ext-gd`).
- `app/Models/ActivityTask.php` — `PRIORITIES` constant (single source of truth), `archived_at`
  fillable + datetime cast, `isArchived()`, `scopeActive()`.
- `app/Models/ActivityLog.php` — added `task_archived`/`task_restored` to `TYPES` (+
  `TYPE_*` constants and badge classes). Audit-only — deliberately **not** in `NOTIFIABLE_TYPES`.
- `app/Http/Controllers/DailyChecklistController.php` — `index` now takes `Request` and supports
  Active/Archived filtering (`?status=archived`) with an archived count; removed `destroyTask`;
  added `archiveTask`, `restoreTask` (both idempotent-guarded, inside `DB::transaction` with an
  `ActivityLog::record()` row) and `exportPdf` (validates `date` `nullable|date|before_or_equal:today`,
  defaults today, streams an `application/pdf`). `completeTask` now rejects archived tasks.
- `routes/web.php` — removed `DELETE …tasks/{task}`; added
  `GET /checklist/export` (`operational.checklist.export`),
  `POST …tasks/{task}/archive` (`operational.checklist.tasks.archive`),
  `POST …tasks/{task}/restore` (`operational.checklist.tasks.restore`).
- `resources/views/operational/checklist/index.blade.php` — Active/Archived nav pills, archive
  button + confirmation modal (replaces the trash form), restore button on the archived view,
  archived-at line, and an "Export PDF" modal (date defaults today, `max=today`, opens in a new
  tab). Empty states for both tabs.
- `docs/CacaoTrack_ERD.md` — §2.4 table + revision note, §9.6, and the Mermaid `ACTIVITY_TASK`
  block updated.
- `AGENTS.md` — feature 1 rewritten; Activity_Task revision added to the resolved-decisions note;
  FPDF noted in the Stack section.

**Business-rule decisions made (per user approval before implementation):**
1. Archive state stored as a nullable `archived_at` timestamp (not a `status` string).
2. Archive+restore **only** — the hard-delete route/behaviour was removed entirely.
3. Archive/restore write `task_archived`/`task_restored` **audit-only** activity rows (no
   notifications), matching the profile/settings convention.
4. `setasign/fpdf` added as the PDF dependency.
5. PDF scope for a date = currently-active tasks + any archived task completed that day (so a past
   day's completed history is not lost); the PDF is generated live, never stored.

**Verification:** `DailyChecklistTest` **16 passed / 51 assertions** (guest redirects incl. export;
add/validate; complete + no double-complete; archive + activity row; archived hidden from active;
archived listing; restore + activity row; double-archive rejected; archived cannot be completed;
PDF returns `application/pdf` starting `%PDF` and containing the real task name + remark; future and
invalid export dates rejected). Full suite **280 passed / 1002 assertions**; `vendor\bin\pint --test`
clean; `view:cache` clean; `route:list --path=checklist` shows the 7 checklist routes (no DELETE).
`php artisan migrate` applied `2026_09_16_000003` to `db_capstone` (schema only — no data change;
`archived_at` is NULL for all 4 seeded tasks). Live check via tinker: real PDF generated from
`db_capstone` (4 active tasks, `%PDF-1.3`, contains the real task names, archived count 0).

### Session AF — Revert Notifications/Settings → toast notifications (previous session)
The persistent **Notifications + Settings** subsystem (Session AC) was removed and replaced by
**transient session-flash toasts**. The user asked for notification toasts that need **no ERD
table** (PRD §9 forbids redundant tables; RULE 4). This is a **deliberate reversal** of the
Session AC approved ERD revision.

**Approved scope (user-confirmed before implementation):**
1. Remove the whole persistent notification subsystem (table, model, controller, bell, page,
   Settings page/column, tests).
2. Toast = **actor feedback only**, rendered globally from the existing `session('success')` /
   `session('error')` flashes (server-rendered, no AJAX/SPA — PRD §4).
3. Docs: real root only; the stale `Capstone\capstone` copy is never edited.
4. Keep the existing per-page inline alerts; the toast is an additional global surface.

**Files deleted:**
- `app/Models/Notification.php`, `app/Http/Controllers/NotificationController.php`,
  `app/Http/Controllers/SettingsController.php`
- `resources/views/notifications/`, `resources/views/settings/`
- `tests/Feature/NotificationTest.php` (17 tests), `tests/Feature/SettingsTest.php` (8 tests)

**Files created:**
- `resources/views/partials/toasts.blade.php` — Bootstrap 5 toast container (top-right) built from
  `session('success')` / `session('error')`; no database access.
- `database/migrations/2026_09_16_000004_revert_notifications_and_settings.php` — guarded
  `Schema::dropIfExists('notifications')` + drop `users.settings` (idempotent; `down()` recreates
  both).
- `tests/Feature/ToastTest.php` — 4 tests (success toast, error toast, no toast without a flash,
  a real checklist action surfacing its feedback as a toast).

**Files modified:**
- `app/Models/ActivityLog.php` — removed `NOTIFIABLE_TYPES`, the notification-emission block in
  `record()`, and the now-dead `settings_updated` type/constant/badge.
- `app/Models/Batch.php` — removed `notifications()`.
- `app/Models/User.php` — removed `appNotifications()`, `wantsNotification()`, `settingsRules()`,
  and the `settings` fillable/cast. `Notifiable` (framework trait) kept.
- `app/Providers/AppServiceProvider.php` — removed the navbar view composer.
- `resources/views/layouts/navbar.blade.php` — removed the bell dropdown and Settings link.
- `resources/views/layouts/app.blade.php` — `@include('partials.toasts')`.
- `resources/js/app.js` — imports the `bootstrap` namespace and auto-shows `.toast[data-autoshow]`
  via `bootstrap.Toast` (autohide 5s); assets rebuilt.
- `routes/web.php` — removed the 3 `/notifications*` and 2 `/settings*` routes + imports.
- `docs/CacaoTrack_ERD.md` — back to 18 entities (removed §2.19, the `Users.Settings` attribute,
  the FK/relationship/Mermaid `Notifications` entries); §9.5 now records the reversal.
- `AGENTS.md` — feature list back to nineteen; replaced the Notifications/Settings design section
  with a "Toast notifications (no table)" section.

**Verification:** `ToastTest` 4 passed; full suite **259 passed / 939 assertions**
(was 280/1002 — minus 25 Notification/Settings tests, plus 4 toast tests); `vendor\bin\pint --test`
clean; `view:cache` clean; `route:list` shows no `/notifications` or `/settings` routes.
`php artisan migrate` applied `2026_09_16_000004` to `db_capstone`; live tinker check confirms
`notifications` table = **NO** and `users.settings` column = **NO**. `npm run build` succeeded
(frontend toast JS bundled).

### Session AG — Handoff refresh + Users/roles verification (previous session)
**No code was changed and no migration was created.** The request was to add/"create new roles" on
the Users page (`/users`, FR-02), restricted to Administrators, and to "create the necessary DB for
it". This session audited the request against the ERD/PRD and **stopped before any schema change**.

**Decision (no new table — ERD revision declined):** the ERD §9.1 stores `Role` directly inside
`Users` and explicitly says a separate `Roles` table would be an **ERD revision**. `AGENTS.md`
requires stopping and reporting rather than inventing tables, so the option of adding a `roles`
table was presented. The user chose **Option C — keep only the 3 fixed roles**
(`User::ROLES = ['Administrator','Manager','Staff']`). No `roles` table, no migration, no ERD
revision, and no data change.

**What already satisfies the request (FR-02):**
- **Routes** (all under `auth`): `GET /users` → `UserController@index`; `POST /users/manage` →
  `store`; `PUT /users/{user}` → `update`; `DELETE /users/{user}` → `destroy`
  (`routes/web.php:121`).
- **Admin-only role assignment:** `UserController::requireAdmin()` aborts **403** for
  non-Administrators on store/update/destroy (`UserController.php:170`); the listing is
  read-only for other roles.
- **Roles:** `User::ROLES` is the single source of truth (RULE 6); role is enforced server-side via
  `Rule::in(...)` in `User::managementCreateRules()` / `managementUpdateRules()`.
- **DB:** `users.role` exists exactly per the ERD §2.1 (`varchar(50)`, default `Staff`);
  `SELECT`-level check confirms **no `roles` table** (`php artisan db:table roles` →
  "Table [roles] doesn't exist").
- **Guards (unchanged):** no self-role-change, no demoting/deleting the last Administrator,
  operational-record and self delete protections, and `user_created/updated/deleted` audit rows.

**Approved sub-decisions (applied only if a roles table is ever approved):** new roles would be
**assignment/display only** (no invented permissions — RULE 3) and `Administrator` would be a
**protected system role** with delete-in-use blocked. These are recorded for a future session but
are **not** implemented (no table exists).

**Verification:** `UserManagementTest` **16 passed / 52 assertions**; full suite **259 passed /
939 assertions**; `vendor\bin\pint --test` clean; `php artisan route:list --path=users` shows the 4
controller routes. **No migration run; `db_capstone` unchanged.** If the Users page
looks static, it is the **stale `Capstone\capstone` copy** — always serve the real root.
*(Superseded by Session AH below: `users.role` was replaced by `users.role_id`, and the `roles` /
`role_permissions` tables now exist.)*

### Session AH — Roles & access restrictions (previous session)
The Management → **Users** page (`/users`, FR-02) gained role management. The former fixed
`users.role` string was normalized into a **`Roles` entity + `Role_Permissions`** child — an
**approved ERD revision** recorded in `docs/CacaoTrack_ERD.md` §2.1/§2.19/§2.20/§9.1/§9.7 **before**
any code or migration. `Administrator` is a protected system role; Administrators can
create/edit/delete non-system roles; every role defines its access restrictions (enforced
server-side); a role assigned to any user cannot be deleted.

**Backup:** `db_capstone` was dumped to
`C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_roles_*.sql` before any migration.
**No `migrate:fresh` was run.**

**Migrations (applied with `php artisan migrate`):**
- `2026_09_16_000005_create_roles_table` — `roles` (`role_id` PK, `role_name` unique,
  `description` nullable, `is_system` boolean default 0).
- `2026_09_16_000006_create_role_permissions_table` — `role_permissions` (`role_permission_id` PK,
  `role_id` FK `cascadeOnDelete`, `permission`, unique `(role_id, permission)`).
- `2026_09_16_000007_add_role_id_to_users_table` — nullable `role_id` FK → `roles` (`restrictOnDelete`);
  deliberately did **not** drop `role`.
- `2026_09_16_000008_drop_role_from_users_table` — dropped the legacy `role` string **only after**
  the backfill was explicitly verified (3/3 users mapped, 0 null, 0 mismatches).

**Backfill:** `RoleSeeder` (called by `DatabaseSeeder`) upserts the 3 built-in roles + their
permissions and maps `users.role → users.role_id`. Seeded permissions preserve the pre-roles access
model: Administrator = all 7; Manager/Staff = all except `manage_users` (so no existing behaviour
changed until an Admin tunes a role). Catalog keys live in `Role::PERMISSIONS` (RULE 6).

**Files created:** `app/Models/Role.php` (`PERMISSIONS`, `ensureDefaults()`, `rules()`,
`isSystem()`/`isAssigned()`/`isProtected()`, `reference()` → `ROLE-###`), `app/Models/RolePermission.php`,
`app/Http/Controllers/RoleController.php`, `app/Http/Middleware/EnsurePermission.php`,
`database/seeders/RoleSeeder.php`, `tests/Feature/RoleManagementTest.php`, and the 4 migrations above.

**Files modified:** `app/Models/User.php` (`role_id` fillable, `role()` relation,
`hasPermission()`, `isAdministrator()`, `management*Rules()` use `role_id`),
`database/factories/UserFactory.php` (`role()`/`admin()` states + default Staff),
`app/Http/Controllers/UserController.php` (role_id filter/KPIs/guards, `manage_users` gate),
`app/Models/ActivityLog.php` (`role_created`/`role_updated`/`role_deleted`),
`database/seeders/DatabaseSeeder.php` (calls `RoleSeeder`; admin `role_id`), `routes/web.php`
(`permission` middleware on the route groups + `users.roles.*` routes), `bootstrap/app.php`
(middleware alias), `resources/views/users/index.blade.php` (Users | Roles tabs + permission matrix),
`resources/views/layouts/sidebar.blade.php` (permission-gated links), `tests/Feature/ProfileTest.php`,
`tests/Feature/UserManagementTest.php`. The Add/Edit User forms gained an inline **+ New Role**
quick-add (`#quickRoleModal` → `POST /users/roles` = `users.roles.store` → `RoleController@storeQuick`),
which reopens the Add User form with `new_role_id` preselected (mirrors Stock In's inline supplier).

**Business rules:** the `Administrator` system role cannot be renamed, re-permissioned or deleted and
always holds `manage_users`; ≥1 permission required per role; assigned roles undeletable; the
`permission` middleware enforces access restrictions server-side; no `Permissions` master table
(catalog in code — PRD §9). The Add/Edit User forms include an inline **+ New Role** quick-add
(`POST /users/roles` → `RoleController@storeQuick`, mirroring Stock In's inline supplier create) that
returns to the Add User modal with the new role preselected (`new_role_id`).

**Verification:** `RoleManagementTest` 14 passed; `UserManagementTest` (16) and `ProfileTest` (13)
updated and passing; full suite **277 passed / 1006 assertions**; `vendor\bin\pint --test` clean;
`view:cache` clean. Live checks on `db_capstone`: 3 users mapped (0 null, 0 mismatches), roles
Administrator 7 / Manager 6 / Staff 6 permissions; signed-out `/users` → login redirect; admin sees
the Users + Roles tabs with Add Role; Staff sees read-only `/users` and receives **403** on role
management.

### Session AI — Daily Checklist print output redesign (previous session)
The Daily Checklist's printable PDF (`GET /operational/checklist/export` →
`DailyChecklistController@exportPdf` → `App\Support\ChecklistPdf`) was refined. The feature already
worked (Session AE); this is a scoped layout/design refinement (RULE 9), **not** a rebuild.

**DB decision (no new table, no migration):** the printout is generated from the existing
normalized `activity_tasks` + `task_completes` data (ERD §9.6 / PRD §9), so no report table was
created and no migration was run.

**Background (same chat, prior turn):** the checklist page's print action was renamed from
"Export PDF" to **"Print Checklist"** (printer icon), the modal title to "Print Daily Activity
Checklist" and its submit to "Generate Printout" — a UI-only change.

**Files modified:**
- `app/Support/ChecklistPdf.php` — layout fully reworked: branded dark-green header band
  (`CacaoTrack` + real generated date/by), title row with the checklist date, light-green summary
  strip (Total / Completed / Pending), green table header, zebra row shading, colored priority
  badges (High red / Medium amber / Low grey), colored status (Completed green / Pending amber),
  vertically centered `#`/Priority/Status cells, bold task name with the description in smaller
  grey italic, continuation-page header with repeated table header, footer page numbering, and a
  cleaner signature block (preparer name shown for traceability). Public interface unchanged
  (`__construct`, `render()`, `renderBody()`); UTF-8→ISO-8859-1 transliteration and
  `SetCompression(false)` kept so FPDF's core fonts render and tests can assert the text.
- `resources/views/operational/checklist/index.blade.php` — print action/modal labels renamed
  (see Background).

**Business-rule decisions:** no fabrication (RULE 5/12) — the PDF still renders only the real rows
passed in by the controller; no report table (PRD §9, ERD §9.6).

**Verification:** `php -l` + `vendor/bin/pint` clean; a real PDF was generated from `db_capstone`
for 2026-09-16 (4 tasks, `%PDF-1.3`) and its content stream confirmed the real task names,
descriptions, priorities, statuses, summary and signature labels; `DailyChecklistTest` 16/16; full
suite **277 passed / 1006 assertions**. **No migration was run; `db_capstone` is unchanged.**

### Session AJ — Master-data archive/restore (latest session)
The six Management pages — **Suppliers** (FR-11), **Clients** (FR-12), **Varieties** (FR-13),
**Scions** (FR-14), **Pot Types** (FR-15) and **Batches** (FR-16) — had their hard **Delete**
replaced by **Archive/Restore**, per the approved ERD revision §9.8.

**Why an ERD revision:** the source ERD's reference/master entities carried no archive attribute and
the pages either hard-deleted a record or blocked the delete when it was referenced. The Daily
Checklist already had an approved archive/restore pattern (nullable `Activity_Task.Archived_At`,
ERD §2.4/§9.6), so the same minimal, additive design was applied to the six tables and recorded in
`docs/CacaoTrack_ERD.md` **before** any migration (RULE 4/13).

**Approved design (user-confirmed before implementation):**
1. A nullable `archived_at` on all six tables (no new table) — `NULL` = active.
2. Archived rows are hidden from the **active management listing** and from **new-record
   selectors** (Stock In supplier/batch, Stock Out client/batch, Batch variety/scion/pot, Mortality
   and Replanting batch). Historical references still resolve; an **Archived** tab lists and restores.
3. Archived masters are **not** excluded from dashboard/analytics — KPI/ledger/report figures stay
   stable.
4. Archive/restore write `<entity>_archived` / `<entity>_restored` rows through
   `ActivityLog::record()` inside a DB transaction.
5. `docs/CacaoTrack_Frontend_Design.md` was referenced in the request but **does not exist** in the
   repo (nor anywhere under `C:\laragon\www\Capstone`); the existing Bootstrap/Bootstrap Icons style
   and the checklist's Active/Archived pill pattern were followed instead.

**Backup:** `db_capstone` was dumped to
`C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_archive_*.sql` before the migration.
**No `migrate:fresh` was run.**

**Migration (applied with `php artisan migrate`):**
- `2026_09_17_000001_add_archived_at_to_master_data_tables` — adds a nullable, indexed `archived_at`
  to `suppliers`, `clients`, `varieties`, `scions`, `pot_types`, `batches` (no data rows changed;
  every existing row is `NULL` = active).

**Files created:**
- `app/Models/Concerns/Archivable.php` — shared trait (RULE 6): `isArchived()`, `scopeActive()`,
  `scopeArchived()`, and `activeExistsRule(string $column)` (an `exists` rule that only accepts an
  **active** row of the model's table).

**Files modified:**
- `app/Models/{Supplier,Client,Variety,Scion,PotType,Batch}.php` — `use Archivable`, `archived_at`
  fillable + datetime cast. `Batch::rules()` now requires an **active** variety/scion/pot.
- `app/Models/ActivityLog.php` — 12 new types/constants/badges:
  `supplier_archived|supplier_restored`, `client_*`, `variety_*`, `scion_*`, `pot_type_*`,
  `batch_archived|batch_restored`.
- `app/Http/Controllers/{Supplier,Client,Variety,Scion,PotType,Batch}Controller.php` — `destroy()`
  removed; `archive()` + `restore()` added (idempotency-guarded, DB-transactional, writes the
  activity row). `index()` takes a validated `tab` (`active` default / `archived`), counts
  `archivedCount`, and scopes the listing; the 5 master controllers KPI-scope to the current tab.
  `BatchController` also passes select options as **active OR referenced-by-a-batch** so an archived
  master already linked to a batch stays editable, and `update()` re-allows an unchanged (possibly
  archived) master value while rejecting a switch to a different archived one.
- `app/Http/Controllers/{StockIn,StockOut,Mortality,Replanting}Controller.php` — new-record
  selectors load `::active()` and validation uses `Model::activeExistsRule(...)` for
  supplier/client/batch. Mortality passes a separate `activeBatches` for its Record modal (the
  filter still shows all). Replanting excludes archived batches from the replantable list.
- `routes/web.php` — the six `DELETE …/{id}` routes replaced by
  `POST …/{id}/archive` (`…archive`) and `POST …/{id}/restore` (`…restore`).
- The six `resources/views/inventory/*/index.blade.php` — Active/Archived pills, archive
  confirmation modal + direct restore action, `Archived <timestamp>` line on archived rows, tab-aware
  empty states, and tab-preserving filter forms. The Batch Add-modal master selects disable archived
  options (`(archived)`); the Edit-modal selects keep them so an existing archived link stays valid.
- `tests/Feature/{Supplier,Client,Variety,Scion,PotType,BatchManagement}Test.php` — the two
  delete tests per page replaced by archive/restore/active-listing/reference tests.
- `tests/Feature/{StockIn,StockOut}Test.php` — added archived-master rejection/selector tests.
- `docs/CacaoTrack_ERD.md` — §2.6/§2.7/§2.8/§2.9/§2.14/§2.16 tables + revision notes, the Mermaid
  entity blocks, the §8 validation checklist, and the new **§9.8**.
- `AGENTS.md` — features 9–13/18 updated, feature 21 added.

**Business-rule decisions:** archive never mutates the referenced history (a batch's `inventories`
row is now **preserved** on archive, whereas delete removed it); the `restrictOnDelete` FKs are
unchanged; `Batch.Status` remains an independent `active`/`inactive` lifecycle flag.

**Verification:** full suite **292 passed / 1100 assertions** (was 277/1006 — the delete tests were
replaced 1:1 plus archive/restore/validation tests); `vendor\bin\pint` clean; `view:cache` clean;
`route:list --path=inventory` shows the 12 archive/restore POST routes and **no** master `DELETE`
routes. Live `db_capstone` columns confirmed via tinker (`archived_at` present on all six tables,
all rows active). Migration applied to `db_capstone` — schema only, no data change.

### Session AK — Cacao Tree Stock Ledger printable PDF (latest session)
The Analytics → **Stock Ledger** page (`/analytics/stock-ledger`, PRD §13.1) gained a
**Print Ledger** action. Previously the page only had a browser `window.print()` button (which
printed the surrounding app chrome); it now produces a proper, self-contained FPDF report. This is
a scoped refinement of an already-functional deliverable (Session O) — **not** a rebuild (RULE 9).

**DB decision (no new table, no migration):** the Cacao Tree Stock Ledger is a tabular deliverable
generated from existing normalized operational data (batches + inventories + varieties + scions +
mortality + replanting). PRD §9 states a report such as the ledger "should normally be generated
from existing normalized operational data", and ERD §9.4 keeps `analytics` as the only analytics
entity (report-generation **history**, written by Generate Report). No report/ledger table was
created and no migration was run (RULE 4/5/12).

**Files created:**
- `app/Support/StockLedgerPdf.php` — `App\Support\StockLedgerPdf extends FPDF` (landscape A4): a
  branded dark-green header band (CacaoTrack + real generated date/by), the title row with the
  applied-filter label, a light-green totals strip (Batches / Grafted / Viable / Available /
  Mortality / Replanted), a green table header, zebra rows, colored status text (Active green /
  Depleted amber / Inactive grey), a colored mortality/replanting figure, vertically centered
  numeric cells, a continuation-page header and page-numbered footer. Real passed-in data only;
  UTF-8→ISO-8859-1 transliteration and `SetCompression(false)` kept so tests can assert the text.

**Files modified:**
- `app/Http/Controllers/AnalyticsController.php` — added `exportPdf(Request): Response` (validates
  `batch_id`/`variety_id` `exists`, `date_from`/`date_to` `date` + `after_or_equal`, builds the same
  `AnalyticsMetrics::stockLedgerQuery()`, maps real rows, computes a totals array, streams an
  `application/pdf`) and the `ledgerLabel()` helper (self-describing "Batch: X | Variety: Y |
  period" title from the applied filters).
- `routes/web.php` — added `GET /analytics/stock-ledger/export` →
  `AnalyticsController@exportPdf` (`analytics.stock-ledger.export`) inside the existing
  `auth` + `permission:view_analytics` group (a print/view action, so no `generate_reports`).
- `resources/views/analytics/stock-ledger.blade.php` — replaced the browser `window.print()` button
  with a **Print Ledger** GET form that opens `analytics.stock-ledger.export` in a new tab and
  carries the current `batch_id`/`variety_id`/`date_from`/`date_to` filters.
- `tests/Feature/AnalyticsTest.php` — added the export route to the guest-redirect test and 4 new
  tests (PDF content-type + `%PDF` + real batches/varieties + totals strip; filter applied and the
  excluded batch absent; inverted date range rejected; unknown batch filter rejected).
- `AGENTS.md` — feature 14 documents the Stock Ledger printable PDF.

**Business-rule decisions:** the PDF is a read-only projection of the same validated query the page
uses (single source of truth — RULE 6); totals are summed from the real filtered rows (no
fabrication — RULE 5/12); no new table or ERD revision (PRD §9, ERD §9.4).

**Verification:** full suite **296 passed / 1125 assertions** (was 292/1100 — +4 tests / +25
assertions); `vendor\bin\pint` clean; `view:cache` clean; `route:list --path=stock-ledger` shows
`analytics.stock-ledger` + `analytics.stock-ledger.export`. Live check against `db_capstone` (via a
tinker script rendering the real PDF): 4 batches, totals `{batches:4, grafted:800, viable:765,
available:605, mortality:45, replanting:34}` — matching the §3 snapshot (Active Inventory 605,
effective mortality 45) — and the file starts with `%PDF-1.3`. **No migration was run; `db_capstone`
is unchanged.**

### Session AL — Mortality Root-Cause Matrix: Raw → Adjustments → Effective (latest session)
The Analytics → **Mortality Root-Cause Matrix** (`/analytics/mortality-matrix`, PRD §13.2) was
made demonstrably consistent with the Operational → **Mortality Tracking** module. The page was
already functional (Session P) and already computed effective mortality via the centralized
`MortalityRecord::effectiveQuantity()`; this session makes the **Raw mortality → signed
Adjustments → Effective mortality** chain visible, so the matrix can never look like a separate
set of numbers (RULE 6 / PRD §25). This is a scoped refinement (RULE 9), **not** a rebuild.

**DB decision (no new table, no migration):** PRD §9 states a report such as the Root-Cause Matrix
"should normally be generated from existing normalized operational data", and ERD §9.4 keeps
`analytics` as the only analytics entity (report-generation history, written by Generate Report).
Creating a matrix/ledger table would be redundant (PRD §9, RULE 4/5/12), so **none was created and
no migration was run**. The audit step stopped and reported this rather than inventing a schema.

**Files modified:**
- `app/Support/AnalyticsMetrics.php` — `mortalityMatrix()` keeps `cells` / `totalsByCause` /
  `grandTotal` (= effective, unchanged contract) and now also accumulates the raw component per
  record (`quantity`) alongside the effective one, returning `rawTotal`, `adjustmentTotal`
  (`grandTotal − rawTotal`), `rawTotalsByCause`, `adjustmentTotalsByCause`, and per-row
  `rawCells`, `adjustmentCells`, `rawTotal`, `adjustmentTotal`. All values derive from the same
  loaded records/adjustments — no second query, no re-implemented formula (RULE 6).
- `resources/views/analytics/mortality-matrix.blade.php` — added a Raw Mortality / Adjustments
  (±, colored) / Effective Mortality summary strip above the matrix, and an in-cell
  `base N (±adj)` sub-line shown only where a cell has a non-zero adjustment (mirrors the
  Mortality Tracking table's `base 25 (-5)` rendering). The variety × cause matrix, totals, grand
  total, filters, empty state and Generate Report are unchanged.
- `tests/Feature/AnalyticsTest.php` — extended the matrix test into
  `test_mortality_matrix_exposes_raw_adjustment_effective_chain` (asserts raw 35 / adjustment −5 /
  effective 30, per-cause raw vs effective vs adjustment, `total = rawTotal + adjustmentTotal` per
  row, and the view showing `Raw Mortality`, `base 25`, `(-5)`), and added validation tests
  (inverted date range → `date_to` error; unknown `batch_id` → `batch_id` error).
- `AGENTS.md` — feature 14 documents the Raw → Adjustments → Effective chain.

**Business-rule decisions:** the matrix's authoritative value stays **effective** mortality, matching
the Mortality module and every other mortality figure (dashboard, seasonality) — the raw and
adjustment figures are a traceability breakdown, not an alternative metric. No formula was
duplicated; the effective quantity still comes from `MortalityRecord::effectiveQuantity()`.

**Verification:** `AnalyticsTest` **23 passed / 129 assertions** (was 20/108 in Session AK); full
suite **299 passed / 1146 assertions** (was 296/1125); `vendor\bin\pint --test` clean. Live
`db_capstone` cross-check via tinker:
`App\Support\AnalyticsMetrics::mortalityMatrix()` → `raw=50, adj=-5, effective(grandTotal)=45`, and
`AnalyticsMetrics::effectiveMortality()` → `45` — the matrix grand total and the Mortality module
total agree. **No migration was run; `db_capstone` is unchanged.**

### Session AM — Generate Report visible output on Stock Ledger & Root-Cause Matrix (latest session)
The question was "what is the purpose of Generate Report but no output on these pages?" — the
two pages (`/analytics/stock-ledger`, `/analytics/mortality-matrix`) saved a report but displayed
nothing. This session surfaces the saved output and fixes a wrong snapshot payload. Scoped
refinement (RULE 9), **not** a rebuild.

**DB decision (no new table, no migration):** the ERD `Analytics` entity (`analytics`) already
*is* the report-generation **history** table (ERD §9.4) — `analysis_type`, `analysis_period`,
`result_summary` JSON, `generated_at`, `user_id`. Generate Report already writes it; the gap was
display, so **no new table was created and no migration was run** (PRD §9, RULE 4/5/12).

**Defect fixed:** `AnalyticsMetrics::summaryFor(TYPE_STOCK_LEDGER)` previously stored Gap Report
data (`expected`/`actual`/`gap`) for a Stock Ledger report. It now stores the real ledger totals
via the new centralized `AnalyticsMetrics::stockLedgerSummary()`
(`batches`/`grafted`/`viable`/`available`/`mortality`/`replanting`) — the same figures the
Print Ledger totals strip renders (RULE 6 / PRD §25).

**Files created:**
- `resources/views/analytics/partials/generated-reports.blade.php` — reusable "Generated Reports"
  card: type-scoped table (Report / Period / Generated By / Generated At) + a per-report **View**
  modal. Real `analytics` rows only.
- `resources/views/analytics/partials/report-summary.blade.php` — recursive read-only renderer for
  a stored `result_summary` snapshot (scalars, associative maps like `totalsByCause`, and list
  values), used inside the View modal.

**Files modified:**
- `app/Support/AnalyticsMetrics.php` — added `stockLedgerSummary()` (RULE 6 central definition);
  `summaryFor(TYPE_STOCK_LEDGER)` now returns it instead of the gap payload.
- `app/Http/Controllers/AnalyticsController.php` — `stockLedger()` and `mortalityMatrix()` pass a
  type-scoped `reports` collection (last 10) via the new `reportsFor(string $type)` helper; both
  views render it.
- `resources/views/analytics/stock-ledger.blade.php` — includes the **Generated Stock Ledger
  Reports** panel below the ledger.
- `resources/views/analytics/mortality-matrix.blade.php` — includes the **Generated Root-Cause
  Reports** panel below the matrix.
- `tests/Feature/AnalyticsTest.php` — +4 tests: stock-ledger summary totals; stock-ledger page
  shows only its own reports (with the View modal snapshot); matrix page shows only its own;
  generating a stock-ledger report persists and appears on the page.
- `AGENTS.md` — feature 14 documents the Generate Report output + `stockLedgerSummary()`.

**Business-rule decisions:** the `analytics` table remains the only reporting entity (PRD §9,
ERD §9.4) — no redundant report table; each page lists only its own `analysis_type`; snapshots are
real data recorded at generation time (older stock-ledger rows generated before this fix keep the
gap payload they were created with — historical, not rewritten).

**Verification:** full suite **303 passed / 1169 assertions** (was 299/1146); `AnalyticsTest`
**27/152** (was 23/129); `vendor\bin\pint --test` clean; `view:cache` clean. Live read-only
`db_capstone` check: `AnalyticsMetrics::summaryFor('stock_ledger')` =
`{batches:4, grafted:800, viable:765, available:605, mortality:45, replanting:34}` (matches the
Session AK Print Ledger totals), and `analytics` rows by type = seasonality 1 / operational 3 /
stock_ledger 2 / mortality_matrix 1 — those now display on their pages. **No migration was run;
`db_capstone` is unchanged.**

### Session AN — Mortality Root-Cause Matrix printable FPDF + Export CSV (latest session)
The Matrix page (`/analytics/mortality-matrix`, PRD §13.2) gained a printable FPDF report and a CSV
export, mirroring the Stock Ledger's **Print Ledger**. Scoped refinement of an already-functional
deliverable (RULE 9) — **not** a rebuild.

**DB decision (no new table, no migration):** the matrix is generated live from normalized
operational data (`mortality_records` + `mortality_adjustments`); PRD §9/§13.2 and Design §29
("No new database entity is required merely to implement this frontend design") mean no report
table is created. The ERD `Analytics` entity (`analytics`, ERD §9.4) remains the only reporting
table and is untouched by these read-only exports.

**Files created:**
- `app/Support/MortalityMatrixPdf.php` — `extends FPDF` (landscape A4), branded dark-green header
  band (CacaoTrack + real generated date/by), title row with the filter label, a light-green
  Raw/Adjustments/Effective strip, a green variety × cause table header with a `Total` column,
  zebra body rows, a shaded **Grand Total** row, a continuation-page header and page-numbered
  footer. Real passed-in data only; UTF-8→ISO-8859-1 transliteration and `SetCompression(false)`
  kept so tests can assert the text. Column widths adapt to the number of cause columns.

**Files modified:**
- `app/Http/Controllers/AnalyticsController.php` — added `exportMatrixPdf()` (validates
  `matrixRules()`, builds `AnalyticsMetrics::mortalityMatrix()`, streams `application/pdf`) and
  `exportMatrixCsv()` (same validated matrix → `fputcsv` to `php://temp` → `text/csv; charset=UTF-8`
  attachment; a single "No data available…" row when empty) plus the `matrixLabel()` helper
  (self-describing filter label — period / batch / variety).
- `routes/web.php` — added `GET /analytics/mortality-matrix/export`
  (`analytics.mortality-matrix.export`) and `GET /analytics/mortality-matrix/export/csv`
  (`analytics.mortality-matrix.export.csv`) inside the existing `auth` + `permission:view_analytics`
  group (print/view actions, so no `generate_reports`).
- `resources/views/analytics/mortality-matrix.blade.php` — page actions now show **Export CSV**
  (link carrying the current query filters), **Print Matrix** (opens the modal) and **Generate
  Report**; added the **Print Matrix** modal (hidden filter inputs → GET export in a new tab, with
  the applied Period/Variety/Batch shown read-only and a "Generate Printout" submit).
- `tests/Feature/AnalyticsTest.php` — +6 tests: the page exposes the print/CSV actions; the PDF
  export renders real varieties/causes and the Raw/Adjustments/Effective strip + grand total; the
  PDF export applies filters (`Batch: B001`, excludes Trinitario); the CSV export renders the real
  matrix header/rows/grand-total; both exports reject an inverted date range; the CSV rejects an
  unknown batch filter. The guest-redirect test now includes both export routes.
- `AGENTS.md` — feature 14 documents the printable matrix + CSV export.

**Business-rule decisions:** the exports are a read-only projection of the same validated
`AnalyticsMetrics::mortalityMatrix()` data the page uses (single source of truth — RULE 6); no
fabrication (RULE 5/12); no new table or ERD revision (PRD §9/§13.2, ERD §9.4). `docs/CacaoTrack_Design.md`
does exist in this repo (unlike the earlier `CacaoTrack_Frontend_Design.md` note) and was followed:
Bootstrap 5 buttons/modals, clear labels, empty state, responsive button wrapping, and the
"print/report action where supported" requirement for the Matrix (§10.2).

**Verification:** `AnalyticsTest` **33/193** (was 27/152); full suite **309 passed / 1210
assertions** (was 303/1169); `vendor\bin\pint --test` clean; `view:cache` clean; `route:list
--path=mortality-matrix` shows the 3 routes (page + PDF + CSV). Live read-only `db_capstone` render
via tinker: `MortalityMatrixPdf` produced `%PDF-1.3` (5,775 bytes) with causes
`["Disease","Drought","Pest"]` and `raw=50, adj=-5, effective=45`. **No migration was run;
`db_capstone` is unchanged.**

### Session AO — Handoff refresh (latest session)
**No code was changed and no migration was created.** The request was to refresh this handoff so a
new chat can continue the vibecoding session. Sessions AN/AM/AL (below) were the latest builds.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **309 passed / 1210 assertions** (matches Session AN — no drift).
- `vendor\bin\pint --test` clean.
- `route:list` shows **80 routes** (was 70 in Session AD; the analytics/master-data work added the
  export routes etc.). `routes/web.php` still holds **no route closures**.
- `migrate:status`: **0 pending** — every project migration Ran through
  `2026_09_17_000001_add_archived_at_to_master_data_tables`.
- Live `db_capstone` counts re-read via tinker: users **4**, roles **4** (`role_permissions`
  **16**), activity_tasks **4**, varieties **4**, scions **4**, pot_types **3**, batches **4**,
  mortality_records **3** + mortality_adjustments **1**, replanting_records **3**, inventories **4**
  (active total **605**), suppliers **5**, stock_ins **2**, clients **4**, stock_outs **2**,
  stock_reversals **1**, `analytics` **9**, `activity_logs` **27**.
- `analytics` by type: seasonality 1, operational 3, **stock_ledger 3**, **mortality_matrix 2**
  (the Session AJ snapshot recorded 4 total; real UI generation since then added 5 — the two newest
  `stock_ledger`/`mortality_matrix` rows dated 2026-09-16 now render in the new per-page Generated
  Reports panels from Sessions AM/AN).
- `activity_logs` by type: mortality_recorded 3, mortality_adjusted 1, replanting_recorded 3,
  stock_in_recorded 2, stock_out_recorded 2, stock_reversal_recorded 1, report_generated 8,
  user_created 3, batch_created 1, batch_updated 1, role_created 1, role_updated 1.
- Dashboard KPIs re-computed from the live data (via `AnalyticsMetrics`): Total Active Inventory
  **605**, effective mortality **45**, Replanting Deficit **11** — unchanged from the Session AJ
  figures (the drift is only in report-history/audit row counts, not operational balances).

**DB decision:** none — this session is verification only. No migration was run and `db_capstone`
was not modified.

### Session AP — Operational Analytics printable FPDF + Export CSV (latest session)
The Operational Analytics page (`/analytics`, PRD §12) gained a printable FPDF report and a CSV
export, mirroring the Stock Ledger's **Print Ledger** and the Matrix's **Print Matrix**. Scoped
refinement of an already-functional deliverable (RULE 9) — **not** a rebuild.

**DB decision (no new table, no migration):** the KPIs and chart values are computed live from the
normalized operational tables via `App\Support\AnalyticsMetrics`. PRD §9 and Design §29 ("No new
database entity is required merely to implement this frontend design") mean no report table is
created. The ERD `Analytics` entity (`analytics`, ERD §9.4) remains the only reporting table and is
untouched by these read-only exports.

**Files created:**
- `app/Support/OperationalAnalyticsPdf.php` — `extends FPDF` (portrait A4), branded dark-green
  header band (CacaoTrack + real generated date/by), title row, a three-box KPI strip (Total Active
  Inventory / Crude Mortality Rate / Replanting Deficit) and four green-header section tables for
  the values behind each on-screen chart (Mortality Trend, Inventory by Variety, Replanting Deficit
  by Batch, Survival vs Mortality), with zebra rows, a "No data available…" empty row per section,
  a continuation-page header and page-numbered footer. Real passed-in data only;
  UTF-8→ISO-8859-1 transliteration and `SetCompression(false)` kept so tests can assert the text.

**Files modified:**
- `app/Http/Controllers/AnalyticsController.php` — extracted the page data into the shared
  `operationalData()` (RULE 6) used by `index()`, and added `exportOperationalPdf()` (builds
  `OperationalAnalyticsPdf`, streams `application/pdf`) and `exportOperationalCsv()` (sectioned CSV
  via `fputcsv` to `php://temp` → `text/csv; charset=UTF-8` attachment).
- `routes/web.php` — added `GET /analytics/export` (`analytics.export`) and `GET
  /analytics/export/csv` (`analytics.export.csv`) inside the existing `auth` +
  `permission:view_analytics` group (print/view actions, so no `generate_reports`).
- `resources/views/analytics/index.blade.php` — page actions now show **Export CSV**,
  **Print Analytics** (opens the modal) and **Generate Report**; added the **Print Analytics**
  modal (GET export in a new tab with a short description of the PDF contents).
- `tests/Feature/AnalyticsTest.php` — +3 tests: the page exposes the print/CSV actions; the PDF
  export renders real KPIs (`270`, `10.00%`) and the four sections; the CSV export renders the KPI
  and section rows (`Criollo`, `B001`, `270`). The guest-redirect test now includes both export
  routes.
- `AGENTS.md` — feature 14 documents the Operational Analytics printable + CSV export.

**Business-rule decisions:** the exports are a read-only projection of the same centralized
`operationalData()` the page uses (single source of truth — RULE 6); no fabrication (RULE 5/12); no
new table or ERD revision (PRD §9, ERD §9.4); following `docs/CacaoTrack_Design.md` §10.1 (readable
analytics) and §10.2 (print/report action where supported).

**Verification:** `AnalyticsTest` **36/232** (was 33/193); full suite **312 passed / 1249
assertions** (was 309/1210); `vendor\bin\pint --test` clean; `view:cache` clean; `route:list
--path=analytics` shows 12 routes including the page + PDF + CSV. Live read-only `db_capstone`
render via tinker: `OperationalAnalyticsPdf` produced `%PDF-1.3` (8,477 bytes) with
`inventory=605, rate=5.63, deficit=11, surviving=720, trend_total=45`. **No migration was run;
`db_capstone` is unchanged.**

### Session AQ — Handoff refresh (latest session)
**No code was changed and no migration was created.** The request was to refresh this handoff so a
new chat can continue the vibecoding session. Session AP (Operational Analytics print + CSV) was the
latest build.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **312 passed / 1249 assertions** (matches Session AP — no drift).
- `vendor\bin\pint --test` clean.
- `route:list` shows **82 routes** (was 80 in Session AO; +2 from Session AP's
  `analytics.export` + `analytics.export.csv`). `routes/web.php` still holds **no route closures**.
- `migrate:status`: **0 pending** — every project migration Ran through
  `2026_09_17_000001_add_archived_at_to_master_data_tables`.
- Live `db_capstone` counts re-read via tinker: users **4**, roles **4** (`role_permissions`
  **16**), activity_tasks **4**, task_completes **0**, varieties **4**, scions **4**, pot_types
  **3**, batches **4**, mortality_records **3** + mortality_adjustments **1**, replanting_records
  **3**, inventories **4** (active total **605**), suppliers **5**, stock_ins **2**, clients **4**,
  stock_outs **2**, stock_reversals **1**, `analytics` **9**, `activity_logs` **27** — **identical
  to the Session AO snapshot** (no data drift).
- `analytics` by type: seasonality 1, operational 3, stock_ledger 3, mortality_matrix 2.
- `activity_logs` by type: mortality_recorded 3, mortality_adjusted 1, replanting_recorded 3,
  stock_in_recorded 2, stock_out_recorded 2, stock_reversal_recorded 1, report_generated 8,
  user_created 3, batch_created 1, batch_updated 1, role_created 1, role_updated 1.
- `archived_at`: **0 archived rows** across `suppliers`, `clients`, `varieties`, `scions`,
  `pot_types`, `batches` (all six master tables still fully active).
- Dashboard KPIs re-computed from live data (`AnalyticsMetrics`): Total Active Inventory **605**,
  Crude Mortality Rate **5.63%**, Replanting Deficit **11** — unchanged.

**DB decision:** none — this session is verification only. No migration was run and `db_capstone`
was not modified.

### Session AR — Gap Report printable FPDF + Export CSV (latest session)
The Historical → **Gap Report** page (`/analytics/historical/gap-report`, PRD §14.3) gained a
printable FPDF report and a CSV export, mirroring the Stock Ledger's **Print Ledger**, the Matrix's
**Print Matrix** and Operational Analytics' **Print Analytics**. The page previously used a plain
browser `window.print()` button (which printed the surrounding app chrome); that is now replaced by
a proper, self-contained FPDF report opened from a modal. Scoped refinement of an
already-functional deliverable (RULE 9) — **not** a rebuild.

**DB decision (no new table, no migration):** the gap is derived live from normalized operational
data — expected = `batches.viable_count`, actual = `inventories.current_quantity`, gap =
`max(0, expected − actual)`. PRD §9/§14.3 and Design §29 ("No new database entity is required merely
to implement this frontend design") mean no report table is created. The ERD `Analytics` entity
(`analytics`, ERD §9.4) remains the only reporting table and is untouched by these read-only
exports.

**Files created:**
- `app/Support/GapReportPdf.php` — `extends FPDF` (landscape A4), branded dark-green header band
  (CacaoTrack + real generated date/by), title row with the filter label, a light-green
  Expected/Actual/Gap totals strip, a green per-batch table header (Batch / Variety / Expected /
  Actual / Gap), zebra body rows (gap coloured red when > 0, brand green when 0), a shaded **Total**
  row, a continuation-page header and page-numbered footer. Real passed-in data only;
  UTF-8→ISO-8859-1 transliteration and `SetCompression(false)` kept so tests can assert the text.

**Files modified:**
- `app/Http/Controllers/AnalyticsController.php` — added `gapRules()` (batch/variety `exists`),
  `exportGapPdf()` (builds `GapReportPdf`, streams `application/pdf`) and `exportGapCsv()`
  (`fputcsv` to `php://temp` → `text/csv; charset=UTF-8` attachment, with a `Total` row and a
  single "No data available…" row when empty); refactored the filter-label builders so
  `ledgerLabel()` / `matrixLabel()` / `gapLabel()` all delegate to one `filterLabel()` (RULE 6);
  `gapReport()` now uses `gapRules()` and passes a type-scoped `reports` collection.
- `routes/web.php` — added `GET /analytics/historical/gap-report/export`
  (`analytics.historical.gap-report.export`) and `GET /analytics/historical/gap-report/export/csv`
  (`analytics.historical.gap-report.export.csv`) inside the existing `auth` +
  `permission:view_analytics` group (print/view actions, so no `generate_reports`).
- `resources/views/analytics/historical/gap-report.blade.php` — page actions now show **Export
  CSV**, **Print Report** (opens the modal) and **Generate Report** (the old `window.print()`
  button was removed); added the **Print Report** modal (hidden filter inputs → GET export in a new
  tab, with the applied Variety/Batch shown read-only) and a **Generated Gap Reports** panel
  (reuses `analytics.partials.generated-reports`).
- `tests/Feature/AnalyticsTest.php` — +5 tests: the page exposes the print/CSV actions + Generated
  Gap Reports; the PDF export renders real batches/varieties and the Expected/Actual/Gap strip;
  the PDF export applies filters (`Batch: B001`, excludes Trinitario); the CSV export renders the
  real rows and total; both exports reject an unknown batch filter. The guest-redirect test now
  includes both export routes.
- `AGENTS.md` — feature 14 documents the Gap Report printable + CSV export and the shared
  `filterLabel()`.

**Business-rule decisions:** the exports are a read-only projection of the same validated
`AnalyticsMetrics::gapReport()` data the page uses (single source of truth — RULE 6); no
fabrication (RULE 5/12); no new table or ERD revision (PRD §9/§14.3, ERD §9.4); following
`docs/CacaoTrack_Design.md` §10.3 (Gap Report) and §10.2 (print/report action where supported).

**Verification:** `AnalyticsTest` **41/274** (was 36/232); full suite **317 passed / 1291
assertions** (was 312/1249); `vendor\bin\pint --test` clean; `view:cache` clean; `route:list
--path=gap-report` shows the 3 routes (page + PDF + CSV). Live read-only `db_capstone` render via
tinker: `GapReportPdf` produced `%PDF-1.3` (6,284 bytes) with 4 rows and
`expected=765, actual=605, gap=180` (B001 depleted → gap 180; B002/B003/B004 gap 0). **No migration
was run; `db_capstone` is unchanged.**

### Session AS — Handoff refresh (historical)
**No code was changed and no migration was created.** The request was to refresh this handoff so a
new chat can continue the vibecoding session. Session AR (Gap Report print + CSV) was the latest
build.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **317 passed / 1291 assertions** (matches Session AR — no drift).
- `vendor\bin\pint --test` clean.
- `route:list` shows **84 routes** (was 82 in Session AQ; +2 from Session AR's gap-report export
  routes). `routes/web.php` still holds **no route closures**.
- `migrate:status`: **0 pending** — every project migration Ran through
  `2026_09_17_000001_add_archived_at_to_master_data_tables`.
- Live `db_capstone` counts re-read via tinker: users **5**, roles **4** (`role_permissions`
  **16**), activity_tasks **4**, task_completes **0**, varieties **4**, scions **4**, pot_types
  **3**, batches **4**, mortality_records **3** + mortality_adjustments **1**, replanting_records
  **3**, inventories **4** (active total **605**), suppliers **5**, stock_ins **2**, clients **4**,
  stock_outs **2**, stock_reversals **1**, `analytics` **9**, `activity_logs` **28**.
- **Drift since Session AQ:** `users` 4 → **5** (new user #5 `manager` /
  `manager.test@gmail.com`, role `Manager`, created through the UI), and `activity_logs` 27 → **28**
  (`user_created` 3 → 4). Roles/permissions and all operational counts are unchanged.
- `analytics` by type: seasonality 1, operational 3, stock_ledger 3, mortality_matrix 2 (unchanged
  since Session AO).
- `activity_logs` by type: mortality_recorded 3, mortality_adjusted 1, replanting_recorded 3,
  stock_in_recorded 2, stock_out_recorded 2, stock_reversal_recorded 1, report_generated 8,
  **user_created 4**, batch_created 1, batch_updated 1, role_created 1, role_updated 1.
- `archived_at`: **0 archived rows** across `suppliers`, `clients`, `varieties`, `scions`,
  `pot_types`, `batches`.
- Dashboard KPIs re-computed from live data (`AnalyticsMetrics`): Total Active Inventory **605**,
  Crude Mortality Rate **5.63%**, Replanting Deficit **11**, Gap Report total gap **180** —
  unchanged (a new user/audit row does not affect operational figures).

**DB decision:** none — this session is verification only. No migration was run and `db_capstone`
was not modified.

### Session AT — Daily Checklist guarded delete (Manager/Administrator only)
An approved **ERD revision refinement** (ERD §2.4/§9.6) adds a guarded permanent **Delete** for
Daily Checklist tasks. The Operational → Daily Checklist (`/operational/checklist?status=archived`)
now offers a **Delete** action allowed **only** for an **archived** task with **zero** `Task_Complete`
history: an active task must be archived first, and a task with any completion is refused (the
`task_completes.task_id` FK is `restrictOnDelete`) and must stay archived. The action is restricted
to the **Manager** and **Administrator** roles (`Role::CHECKLIST_DELETE_ROLES`, RULE 6): other roles
receive **403** and the button + confirmation modal are not rendered. **No new table/column.**

**Files changed:**
- `app/Models/ActivityTask.php` — `hasCompletions()` / `canBeDeleted()` (single rule, RULE 6; uses a
  loaded `completions_count` to avoid N+1).
- `app/Models/ActivityLog.php` — `task_deleted` type + `TYPE_TASK_DELETED` + badge (audit-only).
- `app/Http/Controllers/DailyChecklistController.php` — `destroyTask()` (403 unless authorized;
  rejects active tasks and tasks with history; deletes + writes a `task_deleted` row in a DB
  transaction) and the `requireTaskDeleteAuthority()` guard; `index()` eager-loads
  `withCount('completions')`.
- `app/Models/Role.php` — `CHECKLIST_DELETE_ROLES`; `User::canDeleteChecklistTasks()`.
- `routes/web.php` — `DELETE /checklist/tasks/{task}` → `operational.checklist.tasks.destroy`.
- `resources/views/operational/checklist/index.blade.php` — Delete button + confirmation modal in
  the Archived tab, rendered only for authorized roles (disabled with a tooltip when history exists).
- `tests/Feature/DailyChecklistTest.php` — admin/manager allowed; staff 403 + task preserved;
  history/active guards; guest redirect; UI hides the action from staff.
- Docs: `docs/CacaoTrack_ERD.md` §2.4/§9.6, `docs/CacaoTrack_Design.md` §6.1/§20, `AGENTS.md` feature 1.

**Verification:** `DailyChecklistTest` **23 tests**; full suite **324 passed / 1319 assertions**;
Pint clean; `view:cache` clean; 8 checklist routes. **No migration run; `db_capstone` unchanged.**

### Session AU — Roles + Role_Permissions merged into Users (denormalization) (historical)
An approved **ERD denormalization revision** (ERD §2.1/§9.1) merges the `roles` and
`role_permissions` tables **into `users`** to reduce the number of tables (20 → **18**). `users` now
carries `role` (string) and `permissions` (JSON list from the fixed `Role::PERMISSIONS` catalog);
`users.role_id`, the `roles` table and the `role_permissions` table are **dropped**.

**Backup:** `db_capstone` dumped to
`C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_merge_roles_*.sql` before the migration.
**No `migrate:fresh` was run.**

**Migration (applied with `php artisan migrate`):**
- `2026_09_17_000002_merge_roles_permissions_into_users_table` — adds `users.role` (string, default
  `Staff`) + `users.permissions` (JSON, nullable), backfills both from `roles`/`role_permissions`,
  drops `users.role_id` (FK first), then drops `role_permissions` + `roles`. `down()` recreates the
  normalized tables and rebuilds them from the merged columns.

**Backfill verified live:** admin `Administrator`/7 perms, `manager` `Manager`/6, `test`+`test1`
`Staff`/2, `test2` `farmers(propagator)`/1 — no data lost.

**Files rewritten/created:** the migration above; `app/Models/Role.php` rewritten as a **code-only
catalog helper** (no Eloquent model, no table): `ADMINISTRATOR`/`MANAGER`/`STAFF`, `ROLES`,
`SYSTEM_ROLES`, `CHECKLIST_DELETE_ROLES`, `PERMISSIONS`, `defaultPermissionsFor()`, `isSystem()`.

**Files deleted:** `app/Models/RolePermission.php`, `app/Http/Controllers/RoleController.php`,
`database/seeders/RoleSeeder.php`, `tests/Feature/RoleManagementTest.php`.

**Files modified:**
- `app/Models/User.php` — `role`/`permissions` fillable, `permissions` array cast; `hasPermission()`,
  `isAdministrator()`, `canDeleteChecklistTasks()`, `roleLabel()` now read the account;
  `managementCreateRules()`/`managementUpdateRules()` validate `role` (string) + `permissions[]`.
- `database/factories/UserFactory.php` — sets `role` + `Role::defaultPermissionsFor()`; `role()`/`admin()`
  states; no `Role::ensureDefaults()`.
- `database/seeders/DatabaseSeeder.php` — admin `updateOrCreate` sets `role`/`permissions`; no
  `RoleSeeder` call.
- `app/Http/Controllers/UserController.php` — role-string filter/KPIs, per-user permission save via
  `normalizePermissions()` (Administrator always keeps `manage_users`), `administratorCount()` by role
  string; `requireManageUsers()` unchanged.
- `routes/web.php` — removed the 4 `users.roles.*` routes + the `RoleController` import.
- `resources/views/users/index.blade.php` — rewritten as a single Users page (Roles tab, role modals
  and `+ New Role` quick-add removed) with a role field + permission checkboxes and a permissions list
  in the details modal.
- `resources/views/layouts/sidebar.blade.php` — dropped `loadMissing('role.permissions')`.
- `app/Http/Middleware/EnsurePermission.php` — doc only (now enforces per-user restrictions).
- `tests/Feature/UserManagementTest.php` — rewritten for `role`/`permissions`; added invalid-permission
  and Administrator-keeps-`manage_users` coverage; last-admin-demotion tested via a manager holding
  `manage_users`. `tests/Feature/ProfileTest.php` / `DailyChecklistTest.php` unchanged (still use the
  `Role` catalog constants + factory states).
- `docs/CacaoTrack_ERD.md` — now **18 entities**: `Users` gains `Role` + `Permissions`; the `Roles`/
  `Role_Permissions` sections, FK-map rows, Mermaid entities/edges and the old §9.9 were removed; §9.1
  rewritten as the denormalization revision; §9.7 stores keys in `users.permissions`.
- `AGENTS.md` — **18 tables**; feature 17 (Users) and feature 20 (access restrictions) rewritten; the
  access-restrictions section now describes per-user `role`/`permissions`.

**Verification:** full suite **309 passed / 1265 assertions** (was 324/1319 — the 18
`RoleManagementTest` tests were removed with the feature and the user tests were rewritten); Pint
clean; `view:cache` clean; `route:list --path=users` shows 4 routes (no `users.roles.*`). Migration
applied to `db_capstone` (schema + backfill only). Live re-read: `roles`/`role_permissions` **do not
exist**; `users` columns include `role` + `permissions`; all 5 users retain their role and permissions.

### Session AV — Handoff refresh + documentation consistency
**No code was changed and no migration was created.** A review of `AGENTS.md` surfaced four
low-severity documentation issues; all were fixed. This session re-verified the state and corrected
the docs so the next agent gets accurate guidance.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**
- Full suite: **309 passed / 1265 assertions** (matches Session AU — no drift).
- `vendor\bin\pint --test` clean; `route:list` **81 routes**; `migrate:status` 0 pending.
- Live `db_capstone` re-read: **identical to the Session AU snapshot** (`users` 5, `batches` 7,
  active inventory 935, effective mortality 420, replanting deficit 280, `analytics` 12,
  `activity_logs` 51, `roles`/`role_permissions` **MISSING**, 0 archived rows).

**Files changed (documentation only):**
- `AGENTS.md` — fixed the conventions line to `@section('…'|'page_actions'|'content')` (was
  `pa+ge_actions`, which never rendered); replaced the `RULE 6/25` references with
  `RULE 6 / PRD §25` (there is no `RULE 25` — the Development Rules end at RULE 14); clarified the
  access-restrictions section so the live backfilled values (Administrator/all-7, Manager/6,
  **Staff/2** — trimmed in the UI) are distinguished from the code defaults for new accounts
  (`Role::defaultPermissionsFor()` = all except `manage_users`); reworded the Stock Ledger's
  "no report table" to "no additional report table".
- `session.handoff.md` — corrected the two `RULE 6/25` references to `RULE 6 / PRD §25`.

**DB decision:** none — verification/documentation only. No migration was run and `db_capstone` was
not modified.

### Session AW — Mortality Root-Cause Matrix increase alert (latest session)
The `/analytics/mortality-matrix` page gained a **mortality-increase alert**: it compares the
current live matrix against the most recent generated Mortality Root-Cause Matrix report whose
applied filters match the ones currently selected, and — when a root cause's effective mortality
has increased from that snapshot by **any** amount — shows a warning **banner + warning toast** and
highlights the offending cause columns, variety×cause cells and totals.

**Business rule (user-approved, after a RULE 13 stop-and-ask):** there is no "maximum limit"
anywhere in the PRD/ERD/code, so the user replaced a fixed threshold with a **dynamic baseline**:
compare each cause's current effective mortality against its previously recorded count and alert on
**any increase (≥1)**. The baseline is the most recent saved Matrix report matching the current
filters (the filters were otherwise undefined for matching, so the snapshot now records them).

**Files changed (no schema change):**
- `app/Support/AnalyticsMetrics.php` — new `mortalityMatrixSummary()` (snapshot now stores
  `causes`, `totalsByCause`, `grandTotal`, **`cellsByVariety`** and **`filters`**);
  `summaryFor(TYPE_MORTALITY_MATRIX)` delegates to it. New
  `mortalityMatrixRegressions(array $current, ?array $baseline)` centralizes the comparison (RULE 6)
  and returns `{hasBaseline, causes[], cells[]}` with `previous`/`current`/`increase`.
- `app/Http/Controllers/AnalyticsController.php` — `mortalityMatrix()` computes `$matrix` once,
  resolves the baseline via new `latestMatrixReport($filters)` (matches `analysis_period` +
  stored `filters`; legacy snapshots without filter metadata only match an unfiltered view),
  computes `$alerts` once, and passes `matrixBaseline` + `alerts` to the view. New
  `mortalityIncreaseToast($alerts)` builds the toast message; when non-null the controller calls
  `session()->now('warning', …)` (current-request-only flash — no table, no persistence).
- `resources/views/analytics/mortality-matrix.blade.php` — warning banner ("Mortality increase
  detected" with per-cause previous → current (+increase)) and `table-danger` highlighting on
  offending cause headers, cells and grand-total cells (cells also show `prev → curr`). Page-only;
  the PDF/CSV exports are unchanged.
- `resources/views/partials/toasts.blade.php` — renders `session('warning')` as a Bootstrap
  `text-bg-warning` toast (the existing generic JS auto-shows it — no `app.js` change).
- `tests/Feature/AnalyticsTest.php` — 10 new tests: snapshot includes cells+filters; regressions
  flag an increase of 1; no baseline returns none; page alerts on increase; no alert without an
  increase; a differently-filtered saved report is ignored; a matching batch snapshot is used;
  warning toast shown on increase; no toast without an increase; no toast without a baseline.
- `AGENTS.md` — feature 14 note; this handoff refreshed.

**DB decision:** the ERD `Analytics` table (report-generation history, §9.4) is the necessary and
sufficient baseline store — **no new table, no column, no migration, no ERD revision** (PRD §9).
The feature fires only when a comparable Generate Report snapshot exists; no fabricated values.
The toast is the existing transient session-flash surface (PRD §19, ERD §9.5).

**Verification:** `AnalyticsTest` **51 passed / 312 assertions**; full suite **319 passed / 1303
assertions**; `vendor\bin\pint --test` clean; `view:cache` clean; **81 routes**; `migrate:status` 0
pending. **No migration run and `db_capstone` unchanged.**

### Session AX — Scion received-scion capture + Scion stock cards (latest)
The user asked to (a) capture the **total number of scions received** in the Stock-In modal and
(b) show three cards on the Scions page: **Total Available Scions / Re-grafted Scions / Rejected
Scions**. The request was an open decision (RULE 3/13), so the definitions were confirmed first.

**Approved ERD revision §2.15 / §9.9:** one attribute is added to the existing `stock_ins` table
(**no new table**): `scion_quantity` (unsigned integer, default `0`) — the total scions received
with that stock-in. Scions used/unused and individual scion inventory are explicitly **out of
scope**. A batch's total = `SUM(stock_ins.scion_quantity)`.

**Card formulas (single definition — RULE 6):**
- Total Available = `SUM(stock_ins.scion_quantity)` over the scion's batches (total scions
  received — so it increases automatically on every scion stock-in)
- Re-grafted = `SUM(replanting_records.quantity)` over the scion's batches
- Rejected = effective mortality over the scion's batches (raw + signed adjustments)

Re-grafted/Rejected are independent cards; they are **not** subtracted from Total Available.
(This refines the first cut, which used `max(0, Received − Re-grafted − Rejected)` — that left the
card at 0 whenever tree-level re-grafting + mortality exceeded the received scions. ERD §9.9 /
PRD FR‑14 updated accordingly — **no migration/schema change**.)

**Files created:**
- `database/migrations/2026_09_21_000001_add_scion_quantity_to_stock_ins_table.php` — adds
  `scion_quantity` (default 0) after `quantity`.
- `app/Support/ScionMetrics.php` — `ScionMetrics::forScions($scionIds)` returns
  `{received, regrafted, rejected, available}` from real data only (RULE 5/6/12).

**Files modified:**
- `app/Models/StockIn.php` — `scion_quantity` fillable + int cast; `scionTotalForBatch()` /
  `scionTotalForBatches()` (RULE 6).
- `app/Models/ReplantingRecord.php` — added `totalForBatches()`.
- `app/Models/MortalityRecord.php` — added `effectiveQuantityForBatches()` (same definition as the
  per-batch method).
- `app/Http/Controllers/StockInController.php` — validates `scion_quantity` (`nullable|integer|min:0`)
  and stores it (activity description notes the scions when > 0).
- `resources/views/inventory/stock-in/index.blade.php` — new "Scions Received" field in the New
  Stock In modal, a SCIONS column + details row.
- `app/Http/Controllers/ScionController.php` — computes `ScionMetrics` for the visible tab/filter
  and passes it to the view.
- `resources/views/inventory/scions/index.blade.php` — the three summary cards replaced with
  Total Available / Re-grafted / Rejected.
- `docs/CacaoTrack_ERD.md` (§2.15 + §9.9 + Mermaid), `docs/CacaoTrack_PRD.md` (FR-08/FR-14),
  `AGENTS.md` (features 6/12 + OPEN DECISIONS).
- `tests/Feature/StockInTest.php` (3 new), `tests/Feature/ScionTest.php` (label + 3 metric tests).

**Live read (`db_capstone`, read-only):** `ScionMetrics::forScions(all)` =
`{received:200, regrafted:140, rejected:455, available:200}` — a real UI scion stock-in (200) now
shows up as Total Available Scions = 200, confirming the card increases automatically. No fabricated
values are used.

**Verification:** full suite **326 passed / 1329 assertions**; `vendor\bin\pint --test` clean;
`view:cache` clean; migration `2026_09_21_000001` applied to `db_capstone` (no existing data row
changed — every prior stock-in reads `0`).

### Session BA — Scion stock pool (FR-14)

The Scions page (`/inventory/scions`, FR-14) was reworked: scions are **not** cacao varieties — they
are fungible stock used only for replanting/re-grafting — so the entity became a **single generic
scion stock pool** instead of named CRUD master data.

**Approved ERD revision §2.8/§9.10 (recorded before the migration):**

- `Scions` keeps one row identified as `Scion`; `Scion_Name` is an internal label (no longer
  user-editable/identifying); `Description`/`Archived_At` retained.
- `Quantity` (unsigned integer, default `0`) added — the current available scion balance.
- Stock lifecycle: **Receive Scions** credits (`+`), **replanting/re-grafting** debits (`−`); there
  is **no scion stock-out**.
- **Supersedes §9.9:** scion receiving is no longer captured per stock-in transaction
  (`stock_ins.scion_quantity` retired from the UI; column retained for history).

**Backup:** `db_capstone` dumped to
`C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_scion_pool_20260921_230937.sql`
before the migration. **No `migrate:fresh` was run.**

**Migration (applied with `php artisan migrate`):**

- `2026_09_21_000002_add_quantity_to_scions_and_consolidate` — adds `scions.quantity`; repoints all
  8 batches to the canonical scion; sets its name to `Scion` and its starting balance to
  `max(0, SUM(stock_ins.scion_quantity) − SUM(replanting_records.quantity)) = max(0, 200 − 160) = 40`;
  deletes the superseded rows (ICS-1, ICS-6, UF-29, Meggy). Real data only — no value fabricated.

**Files changed:**

- `app/Models/Scion.php` — `quantity` fillable + int cast; `pool()`, `receive()`, `deduct()`
  (RULE 6); removed the unused `rules()`.
- `app/Support/ScionMetrics.php` — `available = SUM(scions.quantity)` (stored); `regrafted`/`rejected`
  stay derived; `received = available + regrafted`. No longer reads `stock_ins.scion_quantity`.
- `app/Http/Controllers/ScionController.php` — `index` shows the single pool; new `receive()`
  (validates `quantity` `required|integer|min:1` in the `scion` bag, credits inside a DB transaction,
  writes a `scion_stock_received` audit row); removed `store`/`update`/`archive`/`restore`.
- `app/Http/Controllers/ReplantingController.php` — `store` locks the batch's scion
  (`lockForUpdate`), rejects when fewer scions are available than requested, then `Scion::deduct()`
  inside the existing transaction (RULE 7).
- `app/Http/Controllers/StockInController.php` — removed the retired `scion_quantity` capture.
- `app/Models/ActivityLog.php` — added `scion_stock_received` (type + constant + badge).
- `resources/views/inventory/scions/index.blade.php` — rewritten: **Number of Scions** /
  Scions Received / Re-grafted / Rejected cards, a single-row stock table and a **Receive Scions**
  modal; removed Add/Edit/Archive/Restore/search/tabs.
- `resources/views/inventory/stock-in/index.blade.php` — removed the SCIONS column, details row and
  the "Scions Received" form field.
- `routes/web.php` — removed `scions/manage|update|archive|restore`; added
  `POST /scions/receive` (`inventory.scions.receive`).
- `database/seeders/DatabaseSeeder.php` — seeds one `Scion` row; the 3 batches reference it.
- `tests/Feature/ScionTest.php` rewritten (12 tests: receive credit/accumulate/create-if-missing/
  validation; replanting deduction + insufficient rejection; metrics); `StockInTest` lost the 3
  retired `scion_quantity` tests; `ReplantingTest` seeds scion stock so deductions succeed.
- Docs: `docs/CacaoTrack_ERD.md` (§2.8, §9.9 superseded, new §9.10, Mermaid), `docs/CacaoTrack_PRD.md`
  (FR-08, FR-14), `AGENTS.md` (features 4/6/12/21 + OPEN DECISIONS), this handoff.

**Verification:** full suite **315 passed / 1288 assertions**; `vendor\bin\pint --test` clean;
`view:cache` clean; **78 routes**; `migrate:status` 0 pending. Live `db_capstone` after migration:
one `scions` row (`Scion`, id 1, `quantity` 40), all 8 batches repointed, probe
`ScionMetrics::forScions([1]) = {received:200, regrafted:160, rejected:455, available:40}`. **No new
table; approved ERD revision §2.8/§9.10.**

### Session BB — Handoff refresh (verification only)

**No code was changed and no migration was created.** The request was to refresh this handoff so a
new chat can continue the vibecoding session. Session BA (scion stock pool, FR-14) is still the
latest build.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**

- Full suite: **315 passed / 1288 assertions** (matches Session BA — no drift).
- `vendor\bin\pint --test` clean.
- `route:list` shows **78 routes**; `routes/web.php` holds **no route closures**.
- `migrate:status`: all project migrations Ran (through
  `2026_09_21_000002_add_quantity_to_scions_and_consolidate`); **0 pending**.
- Live `db_capstone` re-read via tinker: **identical to the Session BA snapshot** — users **5**,
  activity_tasks **4**, task_completes **0**, varieties **7**, scions **1** (`Scion`, `quantity`
  **40**), pot_types **3**, batches **8**, mortality_records **11** (raw 460) + mortality_adjustments
  **1** (sum −5) → effective **455**, replanting_records **8** (sum 160), inventories **8** (sum
  **1035**), suppliers **5**, stock_ins **6** (scion sum 200), clients **4**, stock_outs **4**,
  stock_reversals **1**, `analytics` **12**, `activity_logs` **57**; **0 archived rows** across all
  six master tables.

**DB decision:** none — verification only. No migration was run and `db_capstone` was not modified.

### Session BC — Batch Monitoring (FR-18)

A new Operational feature, **Batch Monitoring** (`/operational/batch-monitoring`, FR-18), was built.
It tracks each batch's **21-day monitoring lifecycle** from a real start date, records daily
plant-status observations, and derives the monitoring day, remaining days, batch status and alerts
from one centralized definition — **without duplicating any batch data**.

**Approved ERD revision §2.6/§9.11 (recorded before the migrations):**

- `Batch.Monitoring_Start_Date` (nullable `date`) — the date the 21-day lifecycle begins.
- New entity **`Batch_Monitoring`** (`batch_monitorings`, the **19th table**): `monitoring_id` PK,
  `batch_id` FK (restrict), `user_id` FK (restrict), `monitoring_date`, `monitoring_day`, `status`
  (`healthy`/`unhealthy`/`dead`), `survivors`, `mortality_quantity` (default 0), `remark`; UNIQUE
  (`batch_id`, `monitoring_date`) = one observation per batch per day.

**Backup:** `db_capstone` dumped to
`...\Temp\opencode\db_capstone_pre_batch_monitoring_20260922.sql` before migrating. **No
`migrate:fresh`.**

**Migrations (applied with `php artisan migrate`):**

- `2026_09_22_000001_add_monitoring_start_date_to_batches_table` — adds the nullable column and
  **backfills it from `grafted_date`** for all 8 existing batches (real data, no fabrication).
- `2026_09_22_000002_create_batch_monitorings_table` — creates the new table (0 rows; no fabricated
  data).

**Files created:**

- `app/Models/BatchMonitoring.php` — `LIFECYCLE_DAYS = 21`, `PLANT_STATUSES`, derived `STATUSES` +
  `ALERT_STATUSES`, lifecycle math (`elapsedDays`/`monitoringDay`/`remainingDays`/
  `lifecycleComplete`), `statusFor(Batch, ?latest, ?reference)` and label/badge helpers (RULE 6).
- `app/Http/Controllers/BatchMonitoringController.php` — `index` (filters search/batch/status/
  `as_of`; per-batch day/remaining/re-graft/viable/status; alerts; summary counts), `store`
  (validates + writes the observation, auto-starts monitoring on first update, writes a
  `Mortality_Record` when mortality > 0, inside a DB transaction), `start` (sets the start date).
- `tests/Feature/BatchMonitoringTest.php` — 15 tests: guest redirect; view + design columns;
  record update; one-per-day uniqueness; survivors ≤ viable; mortality ≤ remaining capacity;
  mortality requires cause; mortality writes a `Mortality_Record`; first-update auto-start;
  lifecycle day/remaining math; all derived statuses; replanting clears overdue; start + double-start
  rejection; input validation; status filter.
- `resources/views/operational/batch-monitoring/index.blade.php` — alert banner, 4 summary cards,
  filters, the **Batch | Variety | Start Date | Age | Re-Graft | Viable | Status | Action** table,
  Record Monitoring + Start Monitoring + History modals.

**Files modified:**

- `app/Models/Batch.php` — `monitoring_start_date` fillable + `date` cast, `monitoringRecords()`
  relation, lifecycle delegate helpers.
- `app/Models/ActivityLog.php` — `batch_monitoring_started` / `batch_monitoring_recorded` types,
  constants and badges.
- `routes/web.php` — 3 routes under the existing `record_operations` restriction (no new permission).
- `resources/views/layouts/sidebar.blade.php` — **Batch Monitoring** link under Operational.
- Docs: `docs/CacaoTrack_ERD.md` (§1, §2.6, §2.19, §3, §4, §5 Mermaid, §9.11), `docs/CacaoTrack_PRD.md`
  (§7 navigation, FR-18, §9 entity list), `AGENTS.md` (feature 22 + OPEN DECISIONS + table count).

**Business-rule decisions (user-confirmed before implementation):**

1. New `batch_monitorings` table (daily updates/history cannot live on `batches`).
2. `monitoring_start_date` backfilled from `grafted_date` for existing batches.
3. Observed mortality writes to the existing `mortality_records` (single source of truth).
4. Derived statuses/alerts + summary cards; **no PDF** (scope as approved).

**Verification:** full suite **330 passed / 1344 assertions**; `vendor\bin\pint --test` clean;
`view:cache` clean; `route:list --path=batch-monitoring` shows the 3 routes. Live `db_capstone`:
all 8 batches backfilled (`monitoring_start_date = grafted_date`), `batch_monitorings` = **0 rows**.
**No duplicate batch data; no reporting table; approved ERD revision §2.6/§9.11.**

### Session BD — Batch Monitoring status logic (FR-18 refinement)

The Batch Monitoring derived status was refined to match the user's approved logical concept. **No
schema change, no migration, no new table** — the batch status is derived and never stored, so only
`App\Models\BatchMonitoring`, the controller, the view and the tests changed (RULE 4/6).

**Approved decision order (user-confirmed):**

1. no start date → `Not Started`
2. `monitoring_day > 21` **and** an unresolved replanting deficit → `Overdue`
3. a required observation is missing → `Incomplete`
4. at-risk / needs-attention plants exist (latest observation `unhealthy`/`dead`) → `Needs Attention`
5. `monitoring_day <= 21` **and** required observations are complete → `Monitoring`
6. day 21 completed (`day > 21`, deficit `0`) **and** conditions resolved (observations complete) → `Complete`

`Unhealthy`/`Completed` were renamed to `Needs Attention`/`Complete`. **Observations complete** =
every required daily observation exists for days 1..min(day, 21); a single skipped day keeps a batch
`Incomplete` until backfilled (new `BatchMonitoring::observationsComplete()`, RULE 6). Alerts surface
`Needs Attention`, `Incomplete` and `Overdue`; summary cards are Monitored / Needs Attention /
Incomplete / Overdue.

**Files changed:** `app/Models/BatchMonitoring.php` (statuses, `observationsComplete()`, rewritten
`statusFor()`), `app/Http/Controllers/BatchMonitoringController.php` (counts),
`resources/views/operational/batch-monitoring/index.blade.php` (labels/alerts/cards),
`tests/Feature/BatchMonitoringTest.php` (17 tests), plus docs (`docs/CacaoTrack_ERD.md` §9.11,
`docs/CacaoTrack_PRD.md` FR-18, `AGENTS.md` feature 22).

**Verification:** full suite **332 passed / 1348 assertions**; `vendor\bin\pint --test` clean;
`view:cache` clean. **No migration run, `db_capstone` unchanged.**

### Session BE — Handoff refresh (verification only)

**No code was changed and no migration was created.** The request was to refresh this handoff so a
new chat can continue the vibecoding session. Session BD (Batch Monitoring status logic, FR-18) is
still the latest build.

**Re-verification (real root `C:\laragon\www\Capstone\Capstone`):**

- Full suite: **332 passed / 1348 assertions** (matches Session BD — no drift).
- `vendor\bin\pint --test` clean.
- `route:list` shows **81 routes**; `routes/web.php` holds **no route closures**.
- `view:cache` clean; `migrate:status`: all project migrations Ran (through
  `2026_09_22_000002_create_batch_monitorings_table`); **0 pending**.
- Live `db_capstone` re-read via mysql: users **5**, activity_tasks **4** (1 archived),
  task_completes **0**, varieties **7**, scions **1** (`Scion`, `quantity` **25**),
  pot_types **3**, batches **8** (all with `monitoring_start_date`), mortality_records **13**
  (raw **490**) + mortality_adjustments **1** (sum −5) → effective **485**, replanting_records
  **11** (sum **175**), inventories **8** (sum **1035**), suppliers **5**, stock_ins **6**,
  clients **4**, stock_outs **4**, stock_reversals **1**, `analytics` **12**,
  `activity_logs` **62**, `batch_monitorings` **0**; **0 archived rows** across all six master tables.

**DB decision:** none — verification only. No migration was run and `db_capstone` was not modified.
The drift since the Session BD snapshot (mortality 11→13 / raw total 460→490, replanting 8→11 / sum
160→175, `activity_logs` 57→62, scion pool 40→25 via the approved replanting debit §9.10) is real UI
usage, not session code; operational balances are otherwise unchanged (inventories sum **1035**,
`analytics` **12**, `batch_monitorings` **0**).

### Session BF — Batch Monitoring status simplified (FR-18 refinement)

The Batch Monitoring derived status was simplified to **two states only** per the user's
refinement: a batch stays **`Pending`** until it reaches the full 21-day lifecycle and is
**automatically `Complete`** once it does. **No schema change, no migration, no new table** — the
approved `Batch_Monitoring` entity (§9.11) and `Batch.Monitoring_Start_Date` already exist and the
status is derived, never stored (RULE 4/6). The lifecycle is calculated from
`Batch.Monitoring_Start_Date` (`elapsed >= 21`, i.e. `day > 21` = Complete).

**Removed:** the `Not Started` / `Monitoring` / `Needs Attention` / `Incomplete` / `Overdue`
statuses, the `ALERT_STATUSES` alert banner, and the `observationsComplete()` helper (RULE 4 — dead
code removed).

**Added:** plant-viability display. Each row now shows **Alive Plants** = the latest observation's
survivor count (unknown until the first observation — never fabricated, RULE 5/12) and **Plant
Survival Rate** = alive plants ÷ `Batch.Total_Grafted` × 100. Both are centralized in
`App\Models\BatchMonitoring` (`alivePlants()` / `survivalRate()`, RULE 6) and surfaced by
`Batch::monitoringAlivePlants()` / `Batch::monitoringSurvivalRate()`.

**Files changed:** `app/Models/BatchMonitoring.php` (two-state `STATUSES`, rewritten `statusFor()`,
new `alivePlants()`/`survivalRate()`), `app/Models/Batch.php` (`monitoringStatus()` signature + new
helpers), `app/Http/Controllers/BatchMonitoringController.php` (pending/complete/average-survival
summary, alive/survival per row, no alerts), `resources/views/operational/batch-monitoring/index.blade.php`
(Total Batches / Pending / Complete / Average Survival Rate cards; table
**Batch | Variety | Start Date | Age | Alive Plants | Survival Rate | Status | Action**; no alert
banner), `tests/Feature/BatchMonitoringTest.php` (17 tests), plus docs (`docs/CacaoTrack_ERD.md`
§9.11, `docs/CacaoTrack_PRD.md` FR-18, `AGENTS.md` feature 22).

**Verification:** full suite **332 passed / 1353 assertions**; `vendor\bin\pint --test` clean;
`view:cache` clean; 3 batch-monitoring routes; `migrate:status` 0 pending. **No migration run,
`db_capstone` unchanged**; live statuses derive correctly (B001–B003 Complete; B004–B007/TAB007
Pending; no observations recorded yet, so alive/rate show `—`).

### Session BG — Batch Monitoring read-only + mortality-driven alive count (FR-18 refinement)

The Batch Monitoring feature was simplified to a **read-only viability view** and the plant-status
source of truth moved to **Mortality Tracking**. **No schema change, no migration and no new
table** — the approved `Batch_Monitoring` entity + `Batch.Monitoring_Start_Date` already exist and
the page reads the existing `batches.viable_count`.

**Removed:** the manual **Record Monitoring Update** feature (controller `store`, the
`POST /operational/batch-monitoring/records` route, and the Record Monitoring + History modals),
the **Survival Rate** column/calculation, and the observation-based `BatchMonitoring::alivePlants()`
/ `survivalRate()` helpers.

**Kept:** the two-state batch status (`Pending` until the full 21-day lifecycle, then automatically
`Complete`), the lifecycle math, and the manual **Start Monitoring** action (sets
`monitoring_start_date`). `Batch_Monitoring` is **retained for history but is no longer written**.

**New business rule (approved):** Mortality Tracking is the only operation that updates plant
status. Recording a `Mortality_Record` (or a positive `Mortality_Adjustment`) now deducts the dead
plants from the batch's **alive count** = stored `batches.viable_count` (floored at 0) inside the
same DB transaction; a negative adjustment restores them. Centralized single writers:
`Batch::alivePlants()` / `Batch::applyAliveDelta()` (RULE 6/7). `Batch::remainingMortalityCapacity()`
is now the alive count. `AnalyticsMetrics::survivalVsMortality()` was updated so `surviving` = the
stored (already-net) viable count and `totalViable` = surviving + recorded mortality (no
double-count).

**Files changed:** `app/Models/Batch.php`, `app/Http/Controllers/MortalityController.php`,
`app/Models/BatchMonitoring.php`, `app/Http/Controllers/BatchMonitoringController.php`,
`routes/web.php`, `resources/views/operational/batch-monitoring/index.blade.php`,
`app/Support/AnalyticsMetrics.php`, `tests/Feature/BatchMonitoringTest.php` (9 tests),
`tests/Feature/MortalityTest.php`, `tests/Feature/DashboardTest.php`, `tests/Feature/AnalyticsTest.php`,
plus docs (`docs/CacaoTrack_ERD.md` §2.6/§9.11, `docs/CacaoTrack_PRD.md` FR-18, `AGENTS.md`
features 3/22).

**Verification:** full suite **324 passed / 1329 assertions**; `vendor\bin\pint --test` clean;
`view:cache` clean; 2 batch-monitoring routes; `migrate:status` 0 pending. **No migration run,
`db_capstone` unchanged** (the viable_count deduction applies to future mortality writes only).

### Session BH — Revised PRD/ERD adopted; refactor plan locked (planning only — no code/DB change)

Two revised source-of-truth documents were uploaded and **adopted**:
`docs/CacaoTrack_PRD_REVISED.md` and `docs/CacaoTrack_ERD_REVISED.md`. They define a **15-entity**
core model and **remove** Batch Monitoring, Variety, Pot Type, the generic Scions stock pool,
`Mortality_Adjustments`, `Stock_Reversal`, `Batch.Monitoring_Start_Date`, scion stock
receiving/used/unused logic, and the Stock Reversal workflow. The existing
`docs/CacaoTrack_PRD.md` / `docs/CacaoTrack_ERD.md` are to be **replaced** with the revised content,
then `AGENTS.md` rewritten.

**No code, migration or DB change was made this session** (planning only; the full test suite was
**not** re-run — last green baseline remains Session BG **324/1329**). A `db_capstone` backup must
be taken before Phase 1.

**Gap analysis (validated live this session):**

| Revised entity | Current state | Action |
|---|---|---|
| Users, Activity_Log, Analytics, Activity_Task, Task_Complete, Supplier, Client, Mortality_Record, Replanting_Record | exist | keep |
| **Scion_Variety** | split into `varieties` (8) + generic `scions` (1) | **new table**, replaces both |
| **Batch** | `variety_id`+`scion_id`+`pot_id`+`monitoring_start_date` | drop variety/pot/monitoring; `scion_id` → nullable `scion_variety_id` |
| **Stock_In** | `batch_id`, `scion_quantity` | drop both; add `viable`, `origin` |
| **Inventory** | `current_quantity` + UNIQUE batch | monthly snapshot: `inventory_date,total_grafted,viable,regraft,mortality,status,last_update` |
| **Stock_Out** | `batch_id`, `quantity` | drop both; header only |
| **Stock_Out_Detail** | — | **new table** (stock_out, batch, scion_variety, qty, unit, unit_price, amount) |
| Removed | `batch_monitorings`,`varieties`,`pot_types`,`scions`,`mortality_adjustments`,`stock_reversals` | drop |

Live `db_capstone` counts read this session: users 5, `varieties` 8, `scions` 1, `pot_types` 3,
`batches` 9, `mortality_records` 17, `mortality_adjustments` 1, `replanting_records` 13,
`inventories` 9, `suppliers` 5, `stock_ins` 8, `clients` 4, `stock_outs` 7, `stock_reversals` 1,
`analytics` 12, `activity_logs` 74, `batch_monitorings` 0.

**Locked decisions (user-approved):**
1. Backup → migrate data → drop removed tables/columns.
2. `varieties` (8 rows) become `scion_varieties`; batches repointed `variety_id → scion_variety_id`.
3. `batches.available_count` = live available balance; `inventories` = monthly snapshots (many per batch).
4. Stock Out availability = per detail line (batch + scion_variety) vs `batch.available_count`.
5. Revised ERD wins over PRD prose — **no** `mortality.plant_condition`, **no** `replanting.scion_variety_id`.
6. Replace the existing PRD/ERD docs with the revised content; update `AGENTS.md`.
7. Monthly `inventories` rows are written by an explicit **Record Monthly Inventory** action — not
   auto-derived at query time (ERD §3.12).
8. `stock_out_details.unit` is a **fixed list**: `seedlings` / `pieces` / `bags` — not free text
   (PRD §2.8/§11).

**Data-migration notes / accepted loss:** 1 `mortality_adjustments` row (−5 → effective mortality
becomes raw) and 1 `stock_reversals` row are retired per ERD; 8 existing `stock_ins` get `viable`
backfilled from `quantity`; `batches.available_count` backfilled from `inventories.current_quantity`
(sum 1035) before `current_quantity` is dropped.

**Planned phases:**
- **P0 Safety/docs:** back up `db_capstone`; replace PRD/ERD; update `AGENTS.md`.
- **P1 Migrations:** create `scion_varieties` + `stock_out_details`; alter `batches`, `stock_ins`,
  `stock_outs`, `inventories`; migrate/repoint data; drop the six removed tables/columns.
- **P2 Models:** new `ScionVariety`, `StockOutDetail`; rework `Batch`/`StockIn`/`StockOut`/
  `Inventory`/`MortalityRecord`/`ReplantingRecord`; delete `BatchMonitoring`, `Variety`, `PotType`,
  `Scion`, `MortalityAdjustment`, `StockReversal`, `ScionMetrics`.
- **P3 Controllers/routes:** delete BatchMonitoring/Variety/PotType/StockReversal + `Mortality@adjust`;
  `ScionController` → `ScionVarietyController`; multi-line `StockOutController`; rework StockIn/
  Inventory/Batch; route `scions` → `/operational/scion-varieties`.
- **P4 Views/sidebar:** Operational = Checklist/Mortality/Replanting/Batches/Scion Variety;
  Inventory = Stock In/Stock Out/Inventory; Management = Supplier/Client; delete removed pages.
- **P5 Analytics/reports:** `AnalyticsMetrics` drop adjustments/scion-pool/reversal refs; Gap =
  `viable_count − available_count`; update FPDF/CSV classes; remove `ScionMetrics` usage.
- **P6 Tests/docs/verify:** rewrite affected tests; run MySQL suite + Pint + `view:cache` +
  `migrate:status` + live spot-checks; update handoff/`AGENTS.md`.

**No open items remain — the plan is fully locked; Phase 1 (backup + docs) is the next action.**

### Session BI — Phase 0 (safety + docs) of the revised-PRD/ERD refactor (DONE, docs-only)

Executed the locked **P0** only. **No code, migration or DB change** — the running app and
`db_capstone` remain on the OLD 19-table model; **P1–P6 are not started**.

1. **Backup:** `db_capstone` dumped with `mysqldump --single-transaction --routines --events
   --triggers --databases db_capstone` to
   `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_revised_refactor_20260925.sql`
   (51,312 bytes; 27 tables incl. framework tables; clean `-- Dump completed` footer).
2. **Docs replaced:** `docs/CacaoTrack_PRD.md` and `docs/CacaoTrack_ERD.md` were overwritten with the
   revised content (SHA-256 byte-identical to `docs/CacaoTrack_PRD_REVISED.md` /
   `docs/CacaoTrack_ERD_REVISED.md`). Per the user's decision the `*_REVISED.md` files are **kept**
   for provenance; the **canonical files (no suffix) are authoritative**.
3. **`AGENTS.md` rewritten** to the revised **15-entity** target: a prominent `REFACTOR IN PROGRESS`
   banner (code still old), the entity table (**exactly the 15 revised entities — no unnecessary
   tables added**), the removed tables/columns/concepts, the revised sidebar, the locked P0–P6 plan,
   locked decisions, P1 data-migration notes/accepted loss, the pre-refactor entity-mapping table, and
   the two OPEN DECISIONS before Phase 1.

**Validation pass:** ERD entity headers = **15**; `AGENTS.md` entity rows = **15**; canonical vs
`_REVISED` hashes match; `php artisan migrate:status` = **0 pending** (no schema change); full MySQL
suite **324 passed / 1329 assertions** (matches the Session BG baseline); `vendor\bin\pint --test`
clean.

**Kept/decided:** the revised docs are canonical; the `*_REVISED.md` copies remain. **No OPEN
DECISIONS were resolved this session** — the two pre-P1 decisions (monthly-inventory write model;
   `stock_out_details.unit`) are still open (see §6).

### Session BJ — Phase 1 (migrations) of the revised-PRD/ERD refactor (DONE)

Applied the locked **P1**: the `db_capstone` schema is now the revised **15-entity** model. Eight
**ordered, reversible** migrations were written under `database/migrations/2026_09_25_000001..000008`
and applied. A pre-P1 backup was taken first
(`...\Temp\opencode\db_capstone_pre_p1_20260925_142909.sql`, byte-redirected via `cmd`, 52,188 bytes).

1. `create_scion_varieties_table` — new `scion_varieties` (PK `scion_variety_id`, name, description,
   `archived_at`).
2. `copy_varieties_into_scion_varieties` — the 8 live `varieties` rows copied preserving IDs.
3. `add_scion_variety_id_to_batches_table` — nullable FK added; `variety_id → scion_variety_id`
   repointed (0 null).
4. `add_viable_and_origin_to_stock_ins_table` — `viable` (backfilled = `quantity`), `origin`
   (backfilled `''`); dropped `batch_id` FK/column + `scion_quantity`.
5. `create_stock_out_details_table` — header-child with `batch_id` + `scion_variety_id` FKs,
   `quantity`, `unit`, `unit_price`, `amount`.
6. `migrate_stock_outs_to_header_and_details` — the 7 stock-outs became 7 detail lines
   (`unit = seedlings`, `amount` = old header `total_amount`); dropped `stock_outs.batch_id`+`quantity`.
7. `convert_inventories_to_monthly_snapshots` — added `inventory_date/total_grafted/viable/regraft/
   mortality` (seeded from real Batch/Mortality/Replanting data), moved `current_quantity` into
   `batches.available_count`, dropped `current_quantity` + the `batch_id` UNIQUE.
8. `drop_removed_tables_and_columns` — dropped `batch_monitorings`, `mortality_adjustments`,
   `stock_reversals`, `varieties`, `scions`, `pot_types` and Batch `variety_id`/`scion_id`/`pot_id`/
   `monitoring_start_date`.

**Resolved (pre-P1 open decisions):** (a) monthly `inventories` rows are written by an explicit
**"Record Monthly Inventory"** action (stored snapshots), not auto-derived; (b) `stock_out_details.unit`
is a **fixed list** `seedlings`/`pieces`/`bags` (centralized constant in P2/P3); (c) `stock_ins.origin`
is NOT NULL, legacy `''`, app-enforced non-empty in P3.

**Validation:** up → down → up round-trip verified on a scratch copy (`db_capstone_p1test`, created
from a dump; since dropped); `vendor\bin\pint` clean on the 8 files; applied to `db_capstone` with
`migrate:status` **0 pending**. Live spot-checks: **23 tables**; the six removed tables absent
(`scion_varieties` 8, `stock_out_details` 7); `stock_ins.viable = quantity` (0 mismatches); 0 null
`batches.scion_variety_id`; `SUM(batches.available_count)` = **1130** (the old `current_quantity`
sum). `AGENTS.md` updated (banner, P1 done, decisions resolved, migration actuals). **No app
code/views/tests were changed** — they still target the OLD schema, so the app is temporarily
inconsistent and the normal test suite is **not** run (deferred to P6).

### Session BK — Phase 2 (Models) plan + model decisions resolved (PLANNING ONLY)

Finalized the **P2 (Models)** design against the revised canonical `docs/CacaoTrack_PRD.md` +
`docs/CacaoTrack_ERD.md` and validated every target model attribute/relationship against the **live
P1 schema** (`Schema::getColumnListing` for `scion_varieties`, `batches`, `stock_ins`, `stock_outs`,
`stock_out_details`, `inventories`, `mortality_records`, `replanting_records`) — the schema matches
the revised ERD exactly. **No code, no migration and no DB change** (planning only); the app remains
on the OLD model until P2–P6 land. **No new/unnecessary table is introduced** — P2 adds only
Eloquent classes for the two tables P1 already created.

**Two open model decisions RESOLVED (user-approved):**
1. **Mortality never mutates `batches.viable_count`** (revert to the pre-Session-BG Session C rule;
   Batch Monitoring is removed). `viable_count` is set at batch establishment; mortality is tracked
   separately and capped by `Batch::remainingMortalityCapacity()` =
   `max(0, viable_count − effectiveMortalityQuantity())`. Drop `applyAliveDelta()`/`alivePlants()`
   and all Batch-Monitoring helper methods.
2. **`inventories.status` is a stored, FROZEN value** computed **when the monthly snapshot is
   recorded** — one shared rule `Inventory::statusForAvailable(int)` → `active` when
   `batch.available_count > 0` else `depleted`. It is **not** re-derived live on every read
   (Inventory stays a historical monthly record).

**Finalized P2 model changes:**
- **New (2):** `App\Models\ScionVariety` (ERD §3.9; `Archivable`; `batches()`, `stockOutDetails()`,
  `rules()`, `reference()` → `SV-###`) and `App\Models\StockOutDetail` (ERD §3.15; `UNITS =
  ['seedlings','pieces','bags']` — locked decision b; `stockOut()`/`batch()`/`scionVariety()`;
  `lineAmount()`).
- **Rework (6):** `Batch` (`scion_variety_id` replaces the removed variety/scion/pot + monitoring
  column; drop `inventory()` HasOne → `inventories()` HasMany + `latestInventory()`; add
  `scionVariety()` + `stockOutDetails()`; `OPERATIONAL_RELATIONS` =
  mortalityRecords/replantingRecords/stockOutDetails; `rules()` nullable `scion_variety_id`; add
  `totalAvailableQuantity()` replacing `Inventory::totalActiveQuantity()`); `StockIn` (drop
  `batch_id`/`scion_quantity`, add `viable`/`origin`; drop batch + reversal + scion-total helpers);
  `StockOut` (drop `batch_id`/`quantity`; add `details()` + `totalQuantity()`; drop batch + reversal
  helpers); `Inventory` (drop `current_quantity`; add `inventory_date`/`total_grafted`/`viable`/
  `regraft`/`mortality`; drop balance/`applyDelta`/`syncFromBatch`/`totalActiveQuantity`; add
  `statusForAvailable()`/`statusForBatch()`); `MortalityRecord` (drop `adjustments()`; effective
  quantity = raw `SUM(quantity)`); `ReplantingRecord` (unchanged).
- **Delete (7):** `Variety`, `Scion`, `PotType`, `BatchMonitoring`, `MortalityAdjustment`,
  `StockReversal`, `App\Support\ScionMetrics`.
- **Model-level reference fixes:** `Analytic::rules()` `variety_id` → `scion_variety_id`
  (`exists:scion_varieties,scion_variety_id`); `ActivityLog::TYPES` keeps the historical
  `variety_*`/`scion_*`/`pot_type_*`/`mortality_adjusted`/`stock_reversal_recorded`/
  `batch_monitoring_*` labels (so existing rows still render) and adds
  `scion_variety_archived`/`scion_variety_restored`.
- **Out of scope (P3–P6):** controllers, routes, views/sidebar, `AnalyticsMetrics`/PDF/CSV, tests —
  they still target the OLD model, so P2 alone leaves the app/tests broken (by design); P6 rewrites
  the suite.
- **Validation for P2:** no migration (`migrate:status` stays 0 pending); each model maps 1:1 to an
  ERD §3 entity (columns already verified against the live schema); `vendor/bin/pint` on changed
  files; full MySQL suite deferred to P6.

**Verification:** planning only — no test suite run (no code changed; the suite is already expected
to fail against the new schema until P6), no migration run, `db_capstone` untouched.

### Session BL — Phase 3 (Controllers & routes) plan + P2 dependency check (PLANNING ONLY)

Planned the **P3 (Controllers & routes)** step of the revised-PRD/ERD refactor against the canonical
`docs/CacaoTrack_PRD.md` + `docs/CacaoTrack_ERD.md` and the finalized P2 model design (Session BK).
**No code, no migration and no DB change** — planning/documentation only; `db_capstone` untouched.

**Validation finding (blocking):** the **P2 (Models) code is NOT implemented yet.** `app/Models`
still contains `Variety`, `Scion`, `PotType`, `BatchMonitoring`, `MortalityAdjustment`,
`StockReversal` and the old `Batch`/`StockIn`/`StockOut`/`Inventory` (dropped columns:
`variety_id`/`scion_id`/`pot_id`/`monitoring_start_date`, `inventory.current_quantity`,
`stock_ins.batch_id`/`scion_quantity`, `stock_outs.batch_id`); there is **no** `ScionVariety` and
**no** `StockOutDetail`. **P2 must land before P3** (P3 calls the P2 API: `ScionVariety`,
`StockOutDetail`, `Batch::totalAvailableQuantity()`/`scionVariety()`,
`Inventory::statusForAvailable()`).

**No new tables:** P3 introduces none — `scion_varieties` + `stock_out_details` already exist from P1.

**Five P3 open decisions RESOLVED (user-approved):**
1. **Batches move** from `/inventory/batches` (`manage_inventory`) to **`/operational/batches`**
   under `permission:record_operations` (PRD §5 groups Batches under Operational); route names
   `inventory.batches*` → `operational.batches*`.
2. **Scion Variety** management is `/operational/scion-varieties` under **`manage_inventory`**
   (master-data permission preserved; mirrors the old VarietyController semantics).
3. **Stock Out `scion_variety_id` is derived server-side from the batch** (a Batch has one scion
   variety, ERD §3.8); any client-supplied value is ignored.
4. **One `inventory` snapshot per batch per calendar month** (a duplicate batch+month is rejected).
5. **Stock In enforces `viable <= quantity`** (Viable is a five-day recount subset of ordered).

**Finalized P3 changes:**
- **Delete (4 controllers + 1 method):** `BatchMonitoringController`, `VarietyController`,
  `PotTypeController`, `StockReversalController`, `MortalityController@adjust`.
- **New:** `ScionVarietyController` (`index`/`store`/`update`/`archive`/`restore`, error bag
  `scion_variety`, `ScionVariety::rules()`/`activeExistsRule()`/`reference()` → `SV-###`; archive/
  restore write `scion_variety_archived`/`scion_variety_restored`).
- **Rework:** `StockInController` (drop `batch_id`; add `viable`/`origin`; no batch/inventory
  mutation — standalone received-vs-viable record per ERD §3.7); `StockOutController` (header +
  `details[]` lines; per-batch aggregation + `lockForUpdate` vs live `batches.available_count`;
  `unit` in `StockOutDetail::UNITS`; `amount` = qty × unit_price, `total_amount` = Σ amounts,
  computed server-side); `InventoryController` (add `store` **"Record Monthly Inventory"** — snapshot
  values derived server-side from live batch data, never user-entered; month-unique per batch);
  `BatchController` (single `scion_variety_id`; no `Inventory::syncFromBatch`; eager loads
  `scionVariety`/`inventories`); `MortalityController` (delete `adjust`; cap =
  `Batch::remainingMortalityCapacity()`; effective mortality = raw `SUM(quantity)`; never mutates
  `viable_count`; `variety_id` filter → `scion_variety_id`); `ReplantingController` (remove the
  removed `Scion`-pool lock/deduct — record + deficit validation only).
- **Untouched in P3:** Auth, DailyChecklist, Profile, User, Supplier, Client, Activity.
  `AnalyticsController` + `DashboardController` still reference removed models/columns → deliberately
  deferred to **P5**.
- **Routes:** delete `operational.batch-monitoring*`, `operational.mortality.records.adjust`,
  `inventory.varieties*`, `inventory.pot-types*`, `inventory.stock-reversal*`, `inventory.scions*`;
  add the Scion Variety CRUD routes + `POST /inventory/snapshots` (`inventory.snapshots.store`); move
  Batches to `/operational/batches`.
- **P3/P4 coupling:** route renames break `route()` references in the sidebar/views — resolved in
  **P4**; the app stays broken through P5 (expected).
- **Out of scope:** views/sidebar (P4), `AnalyticsMetrics`/PDF/CSV (P5), tests (P6).

**Verification:** planning only — no test suite run (P2 code not written; the suite is expected to
fail until P6), no migration run, `db_capstone` untouched.

### Session BM — Phase 2 (Models) of the revised-PRD/ERD refactor (IMPLEMENTED)

Implemented the **P2 (Models)** step against the canonical revised ERD. The live schema was already
the revised 15-entity model (P1), so this pass brought the Eloquent layer in line with it. **No
migration, controller, route or view was changed; no test suite was run** (the app is mid-refactor
and is expected to fail until P3–P6 land).

**Version control (new):** `git init` was run in the app root and the pre-change state was committed
(`62a521a`, "chore: initial snapshot before P2 model refactor", 207 files). `.gitignore` gained
`/capstone` so the stale duplicate checkout (211 MB, nested inside the app root) is not versioned.
The default identity used was `opencode <opencode@localhost>` via `-c` flags (no global git config
was changed).

**Deleted (tables dropped in P1):** `App\Models\Variety`, `Scion`, `PotType`, `BatchMonitoring`,
`MortalityAdjustment`, `StockReversal`, and `App\Support\ScionMetrics` (verified `class_exists()` =
NO for all seven).

**Created (2):**
- `app/Models/ScionVariety.php` — `Archivable`; `batches()`/`stockOutDetails()`; `rules()`;
  `reference()` → `SV-###`. ERD §3.9 — the single retained scion module (replaces `varieties` +
  `scions`).
- `app/Models/StockOutDetail.php` — `UNITS = ['seedlings','pieces','bags']` (locked decision b);
  `stockOut()`/`batch()`/`scionVariety()`; `lineAmount()`; `rules()`. `scion_variety_id` is
  **derived from the related Batch in a `saving` model hook** and any client input is overwritten
  (locked business rule 4).

**Reworked (5):**
- `Batch` — `scion_variety_id` (nullable) replaces the removed variety/scion/pot + monitoring
  columns; `scionVariety()`; `inventory()` HasOne → `inventories()` HasMany + `latestInventory()`;
  `stockOutDetails()`; `OPERATIONAL_RELATIONS` =
  `mortalityRecords`/`replantingRecords`/`stockOutDetails`; removed `applyAliveDelta()`/`alivePlants()`
  and all Batch-Monitoring helpers; `rules()` uses nullable `scion_variety_id`; added
  `totalAvailableQuantity()` (replaces `Inventory::totalActiveQuantity()`).
- `StockIn` — dropped `batch_id`/`scion_quantity`; adds `viable`/`origin`; dropped the batch/reversal/
  scion-total helpers; `rules()` + a `saving` guard enforce **`viable <= quantity`** (locked business
  rule 5).
- `StockOut` — dropped `batch_id`/`quantity`; header only; `details()` + `totalQuantity()`; dropped
  the batch/reversal helpers.
- `Inventory` — dropped `current_quantity`; adds `inventory_date`/`total_grafted`/`viable`/`regraft`/
  `mortality`; dropped `applyDelta()`/`syncFromBatch()`/`totalActiveQuantity()`; adds
  `statusForAvailable()`/`statusForBatch()` plus a `creating` hook that **freezes `status`** from the
  batch's live available balance (locked business rule 2).
- `MortalityRecord` — dropped `adjustments()`; effective quantity is the raw `SUM(quantity)`;
  `effectiveQuantityForBatch()`/`effectiveQuantityForBatches()` are raw-only. `viable_count` is never
  mutated. (`ReplantingRecord` left unchanged as instructed.)

**Model-level reference fixes:** `Analytic::rules()` `variety_id` → `scion_variety_id`
(`exists:scion_varieties,scion_variety_id`); `ActivityLog::TYPES` keeps the historical
`variety_*`/`scion_*`/`pot_type_*`/`mortality_adjusted`/`stock_reversal_recorded`/`batch_monitoring_*`
labels (so existing rows still render) and adds `scion_variety_archived`/`scion_variety_restored`.

**Verification:** `php -l` clean on all changed/created models; `vendor\bin\pint` **passed** (no
changes needed) on the changed files; `composer dump-autoload` regenerated (6864 classes). A
rollback-protected live check against `db_capstone` (all writes inside a transaction that was rolled
back — **no data changed**) confirmed: the six deleted classes + `ScionMetrics` are gone;
`ScionVariety`/`StockOutDetail` load; `Batch::totalAvailableQuantity()` = **1130**;
`Batch::effectiveMortalityQuantity()`/`remainingMortalityCapacity()` compute correctly;
`Inventory::statusForAvailable()`/`statusForBatch()` correct; Stock Out scion-variety derivation
(`DERIVE_OK`); `StockIn` `viable > quantity` guard throws (`VIABLE_GUARD_OK`); `Inventory` `creating`
hook overwrites a forced `active` status to the batch's real `depleted` (`HOOK_OK`).

**No migration was run; `db_capstone` schema and data are unchanged.** The normal end-of-session test
protocol is **deferred to P6** (the suite cannot pass until the controllers/views/tests are
migrated).

**Flagged (not assumed around):**
1. **No DB enforcement of one Inventory snapshot per batch per calendar month.** Migration
   `2026_09_25_000007` drops the old `batch_id` UNIQUE and adds only a plain `index('batch_id')`; no
   unique index on `(batch_id, year, month)` / `(batch_id, inventory_date)` exists. Per instruction
   **no migration was added** — P3's "Record Monthly Inventory" must enforce this at the app layer,
   and a future migration (e.g. generated `year`/`month` columns) is required for DB-level
   enforcement (a plain `unique(batch_id, inventory_date)` would not express "per calendar month").
2. **`ScionMetrics` deletion vs. scope boundary.** The class is deleted, but its only consumers are
   `app/Http/Controllers/ScionController.php` and `tests/Feature/ScionTest.php`, which are
   controllers/tests explicitly out of scope this pass. They still `use App\Support\ScionMetrics` and
   will fatal until P3/P6.
3. **No migration↔model column mismatches:** every model maps 1:1 to its ERD §3 entity; the live
   column lists (`Schema::getColumnListing`) match the ERD snake_case exactly.
4. **Instructed-but-now-unused:** `ReplantingRecord::totalForBatches()` was left in place
   ("replanting unchanged") although its only consumer (`ScionMetrics`) is gone.
5. **Out-of-scope OLD-model references deferred to P3–P6:** `app/Support/AnalyticsMetrics.php`
   (`App\Models\Variety`, `Inventory::totalActiveQuantity()`), the Batch/Mortality/Inventory/
   Analytics/Scion/BatchMonitoring/PotType/Variety/StockReversal/StockIn/StockOut controllers, both
   seeders, the tests, and the views.

**No controller/route/view file was touched** (confirmed by `git status`: only `app/Models/*` and
`app/Support/ScionMetrics.php` changed). The P2 changes are in the working tree, **not committed**
(only the pre-change snapshot `62a521a` was committed).

### Session BN — Phase 3 (Controllers & routes) of the revised-PRD/ERD refactor (IMPLEMENTED)

Implemented the **P3 (Controllers & routes)** step against the canonical revised ERD and the P2
models. Committed on branch `master` as checkpoint **`3d3f9f9`** ("feat(controllers): implement P3
revised-model controllers and routes"). **No migration, model, view, seeder or test file was
touched** (`git status` = `app/Http/Controllers`, the new `app/Http/Requests`,
`app/Support/DuplicateSubmissionGuard.php` and `routes/web.php`).

**Deleted controllers** (their models/tables are gone): `BatchMonitoringController`,
`VarietyController`, `PotTypeController`, `StockReversalController`, and `ScionController` (the
planned `ScionController → ScionVarietyController` rename — it referenced the deleted `Scion` model +
`ScionMetrics`). `MortalityController@adjust` was removed (Mortality_Adjustments is gone).

**Added:** `ScionVarietyController` — `index`/`store`/`update`/`archive`/`restore`; error bag
`scion_variety`; archive/restore write `scion_variety_archived`/`scion_variety_restored`.

**Reworked:** `BatchController` (index filter `scion_variety_id`; `scionVariety`/`latestInventory`
eager loads; store sets `available_count = viable_count`; **no** `Inventory::syncFromBatch`; route
names/redirects → `operational.batches*`); `StockInController` (no batch; quantity/viable/origin/
date_received/supplier); `StockOutController` (header + `details[]`); `InventoryController` (monthly
snapshot `store`); `MortalityController` (record-only, no adjust); `ReplantingController` (removed
the Scion-pool lock/deduct).

**Form Requests (new `app/Http/Requests/`):** `AppFormRequest` base + `StoreBatchRequest`,
`UpdateBatchRequest`, `StoreStockInRequest`, `StoreStockOutRequest`, `StoreInventorySnapshotRequest`,
`StoreMortalityRecordRequest`, `StoreReplantingRecordRequest`, `StoreScionVarietyRequest`,
`UpdateScionVarietyRequest`, `StoreSupplierRequest`, `StoreClientRequest`. The existing per-page
named error bags (`stockin`, `stockout`, `batch`, `inventory`, `mortality`, `replanting`,
`scion_variety`, `supplier`, `client`) are preserved; in-transaction failures use
`Controller::failValidation()` with the same bag.

**New support:** `App\Support\DuplicateSubmissionGuard` — session-based payload fingerprint (scope +
user + validated payload, 5-second window) with `release()` on failure so legitimate retries are not
blocked.

**Locked business logic enforced (Level-2 validation on top of the P2 model rules):**
1. **Stock Out transaction safety** — one `DB::transaction`; every line is validated against its own
   batch's `available_count` **and** the per-batch aggregate **before** any write; header + all detail
   lines are created; each referenced batch is decremented; any failure rolls the whole transaction
   back (no partial commit). Line error: `Quantity exceeds available inventory for [batch code].` A
   batch with a NULL `scion_variety_id` is rejected (`stock_out_details.scion_variety_id` is NOT NULL).
2. `scion_variety_id` is **never read from the request** (absent from the Form Request rules; the P2
   model hook derives it from the batch). `amount` and `total_amount` are computed server-side.
3. **Stock In** `viable <= quantity` Form Request rule + message `Viable count cannot exceed the
   ordered quantity.`
4. **Inventory** one snapshot per batch per calendar month; duplicate message
   `An inventory snapshot for this batch already exists for [Month Year].`
5. **Mortality** never writes `viable_count`; cap via `Batch::remainingMortalityCapacity()`.
   Plus: Form-Request validation for required/numeric/date/FK, no negatives, a duplicate-submission
   guard on Stock In/Out/Mortality/Replanting/Inventory, `ActivityLog::record()` for Stock In/Stock
   Out/Mortality/Replanting, field-level messages and specific success flashes.

**Routes** (all permission gates use `Role::PERMISSIONS` keys — **no new key invented**; 66 routes):
Added `GET/POST/PUT /operational/scion-varieties*` → `operational.scion-varieties*`
(`manage_inventory`) and `POST /inventory/snapshots` → `inventory.snapshots.store`
(`manage_inventory`). Moved Batches `inventory.batches*` (`/inventory/batches`) →
`operational.batches*` (`/operational/batches`, `record_operations`). Deleted
`operational.batch-monitoring*`, `operational.mortality.records.adjust`, `inventory.stock-reversal*`,
`inventory.varieties*`, `inventory.scions*`, `inventory.pot-types*`.

**Verification:** `php -l` clean on every changed/new file; `vendor\bin\pint --test` **passed**;
`php artisan route:list` boots with 66 routes and the expected `permission:*` middleware. **No test
suite was run** (deferred to P6 — the views/seeders/tests are still the OLD model and will fail).
**No migration was run; `db_capstone` was unchanged.**

**Flagged (not assumed around):**
1. **No DB enforcement of one Inventory snapshot per batch per calendar month.** Migration
   `2026_09_25_000007` dropped the old `batch_id` UNIQUE and added only a plain `index('batch_id')`;
   P3 enforces the rule at the app layer. A future migration is required for DB-level enforcement.
2. **Inventory snapshots write no Activity_Log row.** The centralized catalog `ActivityLog::TYPES`
   has no `inventory_recorded` type and model files were out of scope this pass — the snapshot
   `store` intentionally writes no row (code comment marks it). Needs a one-line model addition.
3. **Snapshot permission** mapped to `manage_inventory` (not `manage_stock`) — flagged choice.
4. **View paths:** `BatchController` keeps `inventory.batches.index`; `ScionVarietyController` uses
   the new `operational.scion-varieties.index` (P4 must create it). The sidebar still references the
   deleted route names, so the app 500s until P4.
5. Duplicate-submission protection is a **session fingerprint** (no natural DB unique key exists and
   migrations are out of scope).
6. `ScionController` was deleted as the planned rename although it was not in the explicit delete
   list.

### Session BO — Phase 4 (Views & sidebar) of the revised-PRD/ERD refactor (IMPLEMENTED)

Implemented **P4 (Views/sidebar)** against the P3 routes and P2 models. **No migration, model,
seeder or test file was touched.** Controller changes: the approved **one-line**
`BatchController@index` view path (`inventory.batches.index` → `operational.batches.index`), plus
the two P4 follow-up flag fixes below (`ActivityController@index` and `UserController`).
`routes/web.php` was not touched.

**Sidebar** (`resources/views/layouts/sidebar.blade.php`): repointed to the real P3 route names and
removed every dead entry — **Batch Monitoring, Variety, Scion, Pot Type and Stock Reversal are gone**.
Operational = Daily Checklist / Mortality / Replanting / **Batches** (`operational.batches`, gated on
`record_operations`) / **Scion Variety** (`operational.scion-varieties`, gated on `manage_inventory`);
Inventory = Inventory / Stock In / Stock Out; Management = Supplier / Client; Analytics + Historical
unchanged; Account unchanged. Visibility checks are UX-only (`$user->hasPermission(...)`) — the routes
stay server-side protected.

**Added view:** `resources/views/operational/scion-varieties/index.blade.php` — full CRUD list
(search, pagination, Active/Archived tabs, Add/Edit/Archive/Restore, empty state, summary cards),
following the Supplier/Client management pattern. Routes
`operational.scion-varieties(.manage.store/.update/.archive/.restore)`, error bag `scion_variety`,
fields `scion_variety_name` + `description`.

**Reworked views:**
- **Batches** moved to `resources/views/operational/batches/index.blade.php` (old `inventory/batches/`
  deleted): dropped Variety/Scion/Pot Type/Monitoring fields; single nullable Scion Variety; detail
  modal = Summary (Total Grafted/Viable/Available/Status), Batch Information (Scion Variety/Grafted
  Date/Created By), Related Operations (Mortality/Replanting/Inventory/Stock Out); routes
  `operational.batches*`.
- **Stock In** (`inventory/stock-in/index.blade.php`): Supplier/Quantity/Viable/Origin/Date Received
  only (no batch); JS blocks `Viable > Quantity` with the exact message `Viable count cannot exceed
  the ordered quantity.` (the server-side rule stays authoritative); the list shows Quantity, Viable
  and Origin.
- **Stock Out** (`inventory/stock-out/index.blade.php`): header (Client/Release Date) + repeatable
  `details[]` lines (Batch, Quantity, Unit fixed dropdown `seedlings`/`pieces`/`bags`, optional Unit
  Price, auto Amount). Scion Variety is displayed read-only from the selected batch and **never
  submitted**; per-line + per-batch availability warnings use `Quantity exceeds available inventory
  for [batch code].`; a `confirm()` guards submission; the listing groups by header with an expandable
  detail row. Lines are repopulated from `old('details')` with per-field server errors on failure.
- **Inventory** (`inventory/index.blade.php`): page action is now **"+ Record Monthly Inventory"**
  (`inventory.snapshots.store`); the listing is **monthly snapshots** (many rows per batch) with Total
  Grafted/Viable/Re-graft/Mortality/Status/Last Update; the duplicate-month message surfaces from the
  `inventory` error bag. (The old client-side "Export Inventory" CSV button was dropped — CSV/PDF is
  P5.)
- **Mortality** (`operational/mortality/index.blade.php`): the **adjustment button/modal/history was
  removed** (Mortality_Adjustments is gone); record-only; filter fixed to `scion_variety_id`.
- **Replanting** (`operational/replanting/index.blade.php`): `->variety` → `->scionVariety` (3 refs) +
  header label.
- **Activity History** (`activity/index.blade.php`): `->variety` → `->scionVariety` (2 refs) + modal
  label. **NB:** the page still 500s at the controller (see flags below).

**Deleted view folders:** `operational/batch-monitoring/`, `inventory/varieties/`,
`inventory/pot-types/`, `inventory/scions/`, `inventory/stock-reversal/`, and `inventory/batches/`
(moved to `operational/`).

**Verification:** `php artisan view:cache` clean; `vendor\bin\pint --test` passed on the changed
controller. A runtime smoke test (logged in as Administrator, request dispatched through the HTTP
kernel) returned **200** for `operational.checklist`, `operational.mortality`,
`operational.replanting`, `operational.batches`, `operational.scion-varieties`, `inventory.index`,
`inventory.stock-in`, `inventory.stock-out`, `inventory.suppliers`, `inventory.clients`,
`users.index` and `profile.index`. A scan confirmed **every `route('...')` used in Blade resolves**
to a real route. **No migration was run; `db_capstone` unchanged.**

**P4 follow-up fixes (CLOSED — these trace to FR-17/§12 and the data-integrity/error-handling
rules, not P5, so they were fixed before starting P5):**
1. **`/activity` 500 — FIXED.** `ActivityController@index` eager-loaded the removed relation
   `->with(['user', 'batch.variety'])`; it now uses `->with(['user', 'batch.scionVariety'])`.
   Verified: `/activity` returns **200**.
2. **User delete guard — FIXED.** `UserController::hasOperationalRecords()` still queried the dropped
   tables `stock_reversals` / `mortality_adjustments`, so `destroy()` threw a query exception instead
   of returning the readable block message. Those checks are removed; every remaining user FK is
   covered (batches/mortality/replanting/stock_ins/stock_outs/analytics/activity_logs by `user_id`;
   task_completes by `user_id` and `verified_by_id`). Verified via reflection: returns `false` for a
   no-record id and `true` for a user with history, with no query exception.
3. `welcome.blade.php` orphan — has a dead `route('register')` but is not rendered by any route
   (benign; left in place).

**Remaining flags (genuinely P5 / optional):**
- `/` (Dashboard) still **500s** — `App\Support\AnalyticsMetrics` is still the OLD model
  (`App\Models\Variety`, `with('adjustments')`, `with(['adjustments','batch.variety'])`,
  `inventories.current_quantity`). That is **P5** by scope.
- `BatchController@index` does not eager-load the creator or the Stock-Out count, so the Batches
  detail modal reads `$batch->user` and `$batch->stockOutDetails()->count()` (2 extra queries/row);
  `->with('user')->withCount('stockOutDetails')` would remove them (optional perf, out of scope).

### Session BP — housekeeping: commit P4 + correct the `AGENTS.md` banner (DONE, docs-only)

Bookkeeping only — **no code, migration, model, view, seeder or test file was touched, and no test
suite was run.** P4 was sitting uncommitted in the working tree with the last commit still
`6eb41dd` ("docs: record P3 controllers/routes…").

1. **P4 committed as its own checkpoint** — `git add -A` + commit **`0803bdf`**
   ("feat(views): implement P4 revised-model views and sidebar, plus Activity/UserController
   follow-up fixes"); **17 files changed, 1112 insertions(+), 2357 deletions(-)** (5 deleted views,
   the `inventory/batches` → `operational/batches` move, the `inventory/pot-types` →
   `operational/scion-varieties` rewrite, the sidebar/Stock In/Stock Out/Inventory/Mortality/
   Replanting/Activity views, the two controller follow-up fixes, the `BatchController@index` view
   path and the Session BO handoff update). Deliberately **not** squashed into the P3 commit.
2. **Stale `AGENTS.md` refactor banner corrected** — it still read the pre-P4
   "P1, P2, P3 are DONE. The views/sidebar/seeders/tests are NOT refactored yet". It now reads
   **"P1 (schema), P2 (models), P3 (controllers/routes) and P4 (views/sidebar) are DONE. The
   analytics/report layer and the seeders/tests are NOT refactored yet"** (Dashboard `/` still 500s;
   `AnalyticsMetrics`/PDF/CSV = P5, seeders/tests = P6), committed as **`5a2a1ae`**
   ("docs: update AGENTS.md refactor banner for P4 completion (P5 next)"). The same commit also
   repointed the plan's `P4 (NEXT)` → **P4 DONE / P5 NEXT** and the "Current code state" heading
   (P1–P4 NEW; analytics/seeders/tests OLD).
3. **Git identity:** no git identity is configured, so commits used inline
   `-c user.name="opencode" -c user.email="opencode@localhost"` (as Session BM did) — **no global git
   config changed**. Final log head: `5a2a1ae` → `0803bdf` → `6eb41dd` → `3d3f9f9` → `e4b7b85` →
   `1f44412` → `62a521a`.

**No migration was run; `db_capstone` is unchanged.** Next is **P5** (Analytics/reports).

### Session BQ — Phase 5 (Analytics/reports) of the revised-PRD/ERD refactor (IMPLEMENTED, one approved schema exception)

Implemented **P5 (Analytics/reports)** against the canonical revised PRD/ERD and the P2/P3/P4 code.
Committed as checkpoint **`d139286`** ("feat(analytics): implement P5 revised-model analytics,
dashboard and stock ledger"). **The seeders and tests were deliberately NOT touched (P6).**

**Part A — `App\Support\AnalyticsMetrics` rewritten onto the current models.** The two narrow fixes
requested were applied:
1. `totalActiveInventory()` now calls `Batch::totalAvailableQuantity()` (it previously called the
   deleted `Inventory::totalActiveQuantity()`, which is why `/` 500'd). Archived batches remain
   included (deliberate).
2. The false `(PRD §12.2)` citation above `mortalityRateComponents()` was corrected to
   **`(PRD §10 Dashboard / §11 Data Consistency)`** (PRD §12 is Activity Traceability).
All other stale references were removed/rewritten: the `use App\Models\Variety;` import;
`->with('adjustments')` (mortality adjustments removed); `batch.variety` → `batch.scionVariety`;
`Variety::query()` → `ScionVariety::query()`; the old `inventoryByVariety()` (joined `inventories` +
`varieties` on `current_quantity`) → **`inventoryByScionVariety()`** (sums `batches.available_count`);
`$batch->inventory?->current_quantity` (singular relation, gone) → `available_count` (age profile)
or `latestInventory`; the removed `stockLedgerQuery()` per-batch query; and the raw/adjustment
matrix fields (`rawTotal`/`adjustmentTotal`/`rawCells`/`adjustmentCells`) — the matrix is now
adjustment-free. `survivalVsMortality()` was corrected (`viable_count` is never decremented by
mortality now): surviving = `max(0, viable − recorded mortality)`, totalViable = `viable`. Filter
params across the analytics layer were renamed `variety_id` → **`scion_variety_id`**
(`exists:scion_varieties,scion_variety_id`); `Analytic::rules()` already used the new key, and
`latestMatrixReport()` accepts the new key with a legacy `variety_id` fallback. Replanting Deficit
was left unchanged (already correct).

**Part B — approved schema exception (the only migration/model change in P5):**
migration `2026_09_25_000009_create_regraft_records_table` created `regraft_records` mirroring
`mortality_records` (`regraft_id` PK, `batch_id` FK, `user_id` FK, `quantity` unsigned, `regraft_date`
date indexed, nullable `remark`); `App\Models\RegraftRecord` mirrors `MortalityRecord`/
`ReplantingRecord` (belongsTo Batch/User, `totalForBatch()`); and `Batch::regraftRecords()` was added.
`inventories.regraft` (the monthly aggregate) is **untouched** — this is an additional, more granular
record. **No CRUD controller/route/view was added** for it (the Stock Ledger reads it) — flagged as a
follow-up.

**Part C — Stock Ledger rebuilt as a MONTHLY GRID** (`AnalyticsMetrics::stockLedgerGrid()` +
`resources/views/analytics/stock-ledger.blade.php` + `App\Support\StockLedgerPdf`), matching the
client's real manual ledger rather than PRD §9.3's looser list. Grouped by month with dynamic
per-Scion-Variety columns (queried from `scion_varieties`, not hardcoded) across six sections:
Seedlings (`stock_ins.quantity` by `date_received`), Grafted (`batches.total_grafted` by
`grafted_date`), Mortality (`mortality_records.quantity` by `recorded_at`), Re Graft
(`regraft_records.quantity` by `regraft_date`), Ready to Plant, and Pullout
(`stock_out_details.quantity` by the parent `stock_outs.release_date`). Each month has a TOTAL row
and the report has a GRAND TOTAL. On-screen and PDF share the same grid. **FLAG:** "Ready to Plant"
is mapped to the batch's live `available_count` aligned to its `grafted_date` row — an explicit,
unconfirmed best-guess interpretation pending the client's actual definition (also noted in the
view and in `AnalyticsMetrics`).

**Part D — remaining pages/PDF/CSV rebuilt against current models.** Dashboard (`/`), Operational
Analytics, Root Cause Matrix, Seasonality, Age Profile and Gap Report all query the current model;
the matrix lost its adjustment UI (single Total Mortality card / summary line); Age Profile counts
`available_count`; Gap uses the locked formula (below). OperationalAnalyticsPdf / StockLedgerPdf /
MortalityMatrixPdf / GapReportPdf and all CSV exports were rebuilt (Gap now shows a Pulled Out
column; Stock Ledger follows the Part C grid). **FLAG (since CLOSED — corrected in Session BS):** the
locked Gap formula `max(0, viable_count − available_count − total_stock_out_for_batch)` resolved to
**0** for every batch, because `available_count` is already decremented by Stock Out on save (so
`viable − available` ≈ stock-out and the extra subtraction cancels it). The panel confirmed the
formula was algebraically wrong; Session BS replaced it with `Gap = Batch::effectiveMortalityQuantity()`.

**Verification:** `php -l` clean; `php artisan view:cache` clean; `vendor\bin\pint` passed on all
changed files; migration applied (`migrate:status` 0 pending). A runtime smoke test
(auth as Administrator) returned **200** for `/`, `/analytics`, `/analytics/stock-ledger`,
`/analytics/mortality-matrix`, `/analytics/historical/seasonality`, `/analytics/historical/age-profile`,
`/analytics/historical/gap-report`, and for all seven exports (`/analytics/export`,
`/analytics/export/csv`, `/analytics/stock-ledger/export`, `/analytics/mortality-matrix/export`,
`/mortality-matrix/export/csv`, `/gap-report/export`, `/gap-report/export/csv`). **No test suite was
run** (deferred to P6 — the seeders/tests still target the OLD model).

### Session BR — live `db_capstone` data audit: `viable_count` / `available_count` / Stock Out reconciliation (INVESTIGATION ONLY)

Verification/investigation only — **no code, migration, model, seeder or DB change**; the live DB was
read read-only through `php artisan tinker`. Trigger: the live 9 batches show
`SUM(viable_count) = 1170` and `SUM(available_count) = 1130`, but the stock-out quantity across those
batches is **2130** — so `viable − available` (40) does not track total stock-out as the P5 Gap logic
implies.

**Finding 1 — every batch and every stock-out is PRE-refactor residue.** All `batches.created_at` fall
on `2026-09-12`…`2026-09-23`, before the P1 migrations (`2026_09_25_000001..009`) and before P2/P3
landed. B001–B003 carry the seeder timestamp `2026-09-12 08:37:29`
(`DatabaseSeeder.php:112,125,138`); B004…TAB008 were created by the **old** BatchController
(`activity_logs` `batch_created` rows 14/46/49/50/54/74). All **7** `stock_out_details` are the legacy
headers converted by `2026_09_25_000006_migrate_stock_outs_to_header_and_details.php:19-29` (release
dates `2026-09-14`…`2026-09-23`); there is **no 8th** stock-out, i.e. none was written post-refactor.

**Finding 2 — `available_count` is the OLD inventory ledger, not `viable_count`-based.** It was
backfilled once from the dropped `inventories.current_quantity`
(`2026_09_25_000007_convert_inventories_to_monthly_snapshots.php:41`), so it equals
`initial + Stock In − Stock Out − Stock Reversal`, completely decoupled from `viable_count`. The live
sum reconciles exactly: initial 1260 (seeder 180+140+95 plus old-style created batches
350+100+100+100+0+195) + stock-ins 2250 − stock-outs 2130 − reversal 250 (`activity_id` 10) =
**1130**. This is why `viable − available` (40) is unrelated to stock-out (2130). B005 is the clearest
proof: `available_count = 600` while `viable_count = 100`.

**Finding 3 — the current Stock Out path cannot produce the 2130.** `StoreStockOutRequest`
(`:23-34`) has **no** availability rule (only `integer|min:1` + `activeExistsRule`); the enforcement
is server-side in `StockOutController::lockAndValidateBatches()` — batch locked with `lockForUpdate()`
(`:180`), per-line check (`:194`) and per-batch aggregate check (`:215`) against the locked
`available_count`, with the decrement only after validation (`:138`). A live validated write therefore
cannot exceed `available_count`; the residue was written by the OLD model, whose batch-scoped Stock In
topped up the balance.

**Finding 4 — complete list of every `available_count` WRITE** (the reverse/restore audit):
- `app/Http/Controllers/BatchController.php:93` — create: `= $validated['viable_count']`
- `app/Http/Controllers/BatchController.php:119` — update: `= $validated['viable_count']` (guarded by `! $used`)
- `app/Http/Controllers/StockOutController.php:138` — `$batch->decrement('available_count', $released)`
- `database/migrations/2026_09_25_000007_...php:41` — one-time backfill (`:83` is a read)
- `database/seeders/DatabaseSeeder.php:112,125,138`
- Test fixtures only: `StockOutTest.php:34`, `StockInTest.php:34`, `InventoryTest.php:33`,
  `MortalityTest.php:32`, `ReplantingTest.php:34`, `AnalyticsTest.php:97`, `ActivityLogTest.php:34`,
  `DashboardTest.php:35`, `BatchManagementTest.php:53,93,225`, `BatchMonitoringTest.php:32,64`,
  `ClientTest.php:33`, `VarietyTest.php:30`, `PotTypeTest.php:30`, `ScionTest.php:41`,
  `SupplierTest.php:33`, `UserManagementTest.php:54`, `StockReversalTest.php:37`.
There is **no increment/restore** anywhere: `ReplantingController` creates only a `ReplantingRecord`
(`:88-94`); `RegraftRecord`/`MortalityRecord` don't touch it; **no Batch observer/event**
(`AppServiceProvider::boot()` is empty; the only `booted()` hooks are `Inventory.php:52` status,
`StockIn.php:51` viable guard, `StockOutDetail.php:57` scion-variety derivation — none write
`available_count`); `Batch::rules()` deliberately omits it (`app/Models/Batch.php:228-238`).

**Consequence for P6:** the live operational rows are pre-refactor residue with an internally
inconsistent `available_count`, so P6's seeders/tests must be written against clean, current-model
data. Decide whether to (a) regenerate seed data and reconcile `available_count` before P6, or
(b) leave the live rows untouched and have P6 use synthetic fixtures. **No data was changed.**

### Session BS — Gap formula corrected to effective mortality (post-P5 follow-up, IMPLEMENTED)

Follow-up fix to the Session BQ P5 Gap Report (committed as checkpoint **`1744975`**,
"fix(analytics): correct Gap formula to effective mortality"). **No DB/schema/data change** — pure
code/view/PDF/CSV + `AGENTS.md` change; the existing P5 commit `d139286` was **not** amended.

**The bug:** the locked formula `max(0, viable_count − available_count − total_stock_out_for_batch)`
was algebraically wrong — it is a mathematical identity that always resolves to **0** regardless of
the data, because `available_count` is already netted down by Stock Out on every validated release
(`StockOutController` decrements it; `StoreStockOutRequest` + the controller's per-line/per-batch
checks enforce `quantity ≤ available_count`). So subtracting stock-out again cancels it out. It was
never test-data-specific.

**The corrected formula:** `Gap = Batch::effectiveMortalityQuantity()` (reused unchanged). Mortality
is the only quantity still unreflected in `available_count`, so it is the real gap — how much of the
live "available" balance may be overstated until recorded deaths are reconciled out of it.

**Files changed (5):**
- `app/Support/AnalyticsMetrics.php` — `gapReport()` `gap` now `$batch->effectiveMortalityQuantity()`;
  `expected`/`actual`/`pulled_out` kept as context columns; class + method docs explain the identity.
- `resources/views/analytics/historical/gap-report.blade.php` — explanatory copy rewritten; summary
  strip restructured ("Live ledger context" + a "Gap = Effective Mortality" card); Total Gap subtitle;
  table header "Gap / Effective Mortality"; print-modal note.
- `app/Support/GapReportPdf.php` — doc block; `Gap (Mortality)` column; summary
  `Gap (effective mortality): %d`; new self-describing note line under the table header.
- `app/Http/Controllers/AnalyticsController.php` — CSV header
  `Batch,Variety,Expected,Available,Pulled Out,Gap (Effective Mortality)`.
- `AGENTS.md` — P5 open-flags #1 replaced with the corrected formula + reasoning; P5 plan line updated.

**Verification (live `db_capstone`, read-only, freshly queried against the corrected formula):**
all 9 batches — B001 125, B002 20, B003 45, B004 300, B005 50, B006 30, B007 0, TAB007 10,
TAB008 0. Row gaps reconcile to `totalGap = 580`, which equals both the sum of the rows and the
independent `SUM(mortality_records.quantity)`; per-batch `effectiveMortalityQuantity()` matches
`MortalityRecord::effectiveQuantityForBatch()`. The two zero batches genuinely have 0 mortality rows.
`php artisan view:cache` clean; `vendor\bin\pint` passed; `php -l` clean; the FPDF renders
(`%PDF-1.3`, 10,530 bytes) with the new note. **No test suite run** (P6 — `tests/Feature/AnalyticsTest.php`
still asserts the old gap values and remains part of the old-model suite).

**Still open (unchanged):** Stock Ledger "Ready to Plant" best-guess mapping; `regraft_records` has no
CRUD UI yet. **The prior "Open decision #1" (Gap) is now CLOSED.**

### Session BT — user-facing terminology rename: "Replanting" → "Regraft" (DISPLAY-ONLY, IMPLEMENTED)

Committed as checkpoint **`bd47433`** ("refactor(ui): rename user-facing Replanting to Regraft");
17 files, 52/52 lines. **Display text only — no DB/schema/data/logic change** and **no internal
rename**.

**What changed (every user-visible occurrence):** sidebar label; Dashboard KPI card
("Replanting Deficit" → "Regraft Deficit") + sub-text; Operational Analytics KPI card, "Regraft
Deficit by Batch" chart header + ECharts series name + print-modal copy; the whole Regraft page
(title / page title, "Record Regraft", summary cards, "REGRAFTED" column, modal titles/labels/copy,
empty states, success flash); Batches archive-modal copy + details label; Stock Ledger "Re Graft"
column header and legend in the Blade view **and** `StockLedgerPdf` (`Re Graft: %d` summary strip +
section headers) + the `AnalyticsMetrics` ledger row description; Operational Analytics PDF/CSV
headers (`AnalyticsController` + `OperationalAnalyticsPdf`); Activity History type label
(`ActivityLog::TYPES['replanting_recorded'] => 'Regraft Recorded'`); the `record_operations`
permission label (`Role.php`); Regraft validation/toast/activity copy
(`StoreReplantingRecordRequest`, `ReplantingController`); and the generated-report summary label
mapping (`report-summary.blade.php` now renders the internal `total_replanting_deficit` key as
"Total Regraft Deficit"). Two test assertions were updated (`DashboardTest:70`,
`AnalyticsTest:167,192`).

**Deliberately NOT renamed (internal names stay "Replanting"):** `ReplantingController`,
`ReplantingRecord`, `StoreReplantingRecordRequest`, the `replanting_records` table/columns,
`Batch::replantingDeficit()`/`totalReplanted()`/`replantingRecords()`,
`AnalyticsMetrics::totalReplantingDeficit()`/`replantingDeficitByBatch()`, the
`operational.replanting*` routes, the `total_replanting_deficit` result-summary key, variables, DOM
ids, the error-bag name and code comments.

**Verification:** `php artisan view:cache` clean; `vendor\bin\pint` passed; Dashboard KPIs captured
before and after are identical — `totalActiveInventory` **1130**, `crudeMortalityRate` **40**
(40.00%), `totalReplantingDeficit` **385**. **No migration run; no schema/data change.** Not touched
(not app UI): `README.md`, `docs/CacaoTrack_Design.md`, and the committed `docs/*.csv` samples.

### Session BU — consolidation audit: `regraft_records` still live; consolidation never applied (READ-ONLY)

Investigation only — **no code, migration, model or DB change.** Trigger: phpMyAdmin shows both
`regraft_records` **and** `replanting_records` live in `db_capstone`, i.e. the earlier "consolidation
fix" (drop `regraft_records`, repoint the Stock Ledger's Regraft column at `Replanting_Record`)
appears never applied. The four answers:

1. **No drop migration exists.** The **only** regraft migration is
   `database/migrations/2026_09_25_000009_create_regraft_records_table.php`, which **creates** the
   table in `up()` and only drops it in `down()`. A repo-wide grep for `regraft_records` hits only:
   that migration, `app/Models/RegraftRecord.php`, `Batch::regraftRecords()`
   (`app/Models/Batch.php:98`) and `AnalyticsMetrics::stockLedgerGrid()` (`:579`/`:588`).
2. **It has not been run** (it cannot have been — nothing exists to run). `php artisan migrate:status`:
   every migration `Ran`, **0 Pending**; the only regraft line is
   `2026_09_25_000009_create_regraft_records_table .. [22] Ran`.
3. **The Stock Ledger "Regraft" column reads `regraft_records`, not `replanting_records`.**
   `AnalyticsMetrics::stockLedgerGrid()` (`app/Support/AnalyticsMetrics.php:578-605`) runs
   `RegraftRecord::query()...` and emits `'regraft' => [$varietyId => (int) $record->quantity]`.
   Live check: `regraft_records` = **0 rows**, `replanting_records` = **13 rows** — so the column is
   effectively empty while the real data sits in `replanting_records` (which also feeds the Regraft
   page via `Batch::totalReplanted()`/`replantingDeficit()` and the Inventory snapshot's `regraft`
   column, `InventoryController.php:105`).
4. **`app/Models/RegraftRecord.php` is still present** (62 lines; `$table = 'regraft_records'`,
   `totalForBatch()`), plus its relation `Batch::regraftRecords()` and the `AnalyticsMetrics` import
   (`:8`).

**Impact:** the Stock Ledger under-reports Regraft (always 0 today). **The consolidation remains
outstanding** and is the immediate next action pending approval — see §6. **No data was changed.**
*(Historical — this was the state at the end of Session BU. **Resolved in Session BX**, which applied
the consolidation: `regraft_records` dropped, model/relation/import removed, Stock Ledger repointed at
`replanting_records`. See the Session BX entry below.)*

### Session BV — `Scion_Receiving` entity: additive schema + model only (IMPLEMENTED)

Built the **Scion Receiving record only** — a new, standalone entity. **No controller, route or view
was added**, and the freshness/viability check was explicitly **out of scope** pending a client
business-rule confirmation (no `usable_until`, no warning, no blocking). No test suite run (P6).

**Confirmed open decisions (asked + answered before building):**
1. `supplier_id` is **nullable** — scions are not always tied to a formal Supplier record.
2. The origin field is a **single nullable `origin`** column (not `reference_number`, and not both).

**Files created:**
- `database/migrations/2026_09_26_000001_create_scion_receivings_table.php` — table
  `scion_receivings`: `scion_receiving_id` PK (bigIncrements); `scion_variety_id` **required** FK →
  `scion_varieties` (`restrictOnDelete`); `supplier_id` **nullable** FK → `suppliers`
  (`restrictOnDelete`); `quantity` unsignedInteger; `unit_price` decimal(12,2) nullable;
  `date_received` date (indexed); `origin` varchar nullable; `notes` text nullable. No timestamps.
  No stock-balance column anywhere.
- `app/Models/ScionReceiving.php` — `$table = 'scion_receivings'`, PK `scion_receiving_id`, no
  timestamps; `belongsTo` `ScionVariety` (`scion_variety_id`) and `Supplier` (`supplier_id`);
  casts `quantity` int / `unit_price` decimal:2 / `date_received` date; `reference()` → `SCR-###`
  (presentation only); centralized `rules()` (RULE 6): `scion_variety_id` required +
  `ScionVariety::activeExistsRule()`, `supplier_id` nullable + `Supplier::activeExistsRule()`,
  `quantity` required integer min:1, `unit_price` nullable numeric min:0, `date_received` required
  date `before_or_equal:today`, `origin` nullable max:255, `notes` nullable max:1000. Built-in
  Laravel validation only — no extra table, no custom validator.

**Docs updated:** `Scion_Receiving` added as ERD **§3.16** (with the table, purpose, and the
additive/no-balance notes) plus its relationships in §4 (`Supplier └──< Scion_Receiving`,
`Scion_Variety └──< Scion_Receiving`) — applied to **both** `docs/CacaoTrack_ERD_REVISED.md` and the
**canonical** `docs/CacaoTrack_ERD.md` (kept byte-identical; SHA-256 match verified). The entry states
explicitly that a **freshness/viability rule is PLANNED but NOT YET IMPLEMENTED** (no usable-until, no
warning, no blocking) pending client confirmation of the allowed window and whether to warn or block.
The PRD was **not** touched.

**Deliberately NOT done (flags):** no CRUD controller/route/view (the traceability question — how
usage should reference receiving records — is still open); no stock balance on `scion_varieties`; no
touch to `RegraftRecord`/`ReplantingRecord`; no freshness/viability logic. **`AGENTS.md` updated (later
docs pass, DONE):** it now lists **both** approved additive exceptions (`regraft_records` P5 Part B +
`scion_receivings` Session BV) and explicitly marks the `regraft_records` consolidation as never applied.

**Verification:** migration applied (`php artisan migrate --force`); `migrate:status` **0 Pending**
(`2026_09_26_000001 .. Ran`); `php artisan db:table scion_receivings` confirms the exact column
types/nulls and both FKs (`on delete restrict`); `vendor\bin\pint` passed; `php -l` clean; a tinker
check confirms the model loads, `rules()` returns all 7 keys, and both relations exist. **No test
suite run** (P6 — the suite still targets the OLD model). **Schema changed** (the DB is now **25
tables**); **no operational data change** (the new table is empty). **Committed as its own isolated
 checkpoint `33ff6b0`** (4 files: the migration, the model, and the two ERD docs) — `session.handoff.md`
and the untracked `docs/*.csv` were deliberately left out of that commit.

### Session BX — `regraft_records` consolidation APPLIED (code + migration, commit `22ef695`)

The long-pending Session BU proposal was **actually applied this time** (an earlier report claimed it
was done; it was not — the table, model, relation and the empty-table read were all still present).
**Regraft and Replanting are the same recorded procedure**, so the duplicate `Regraft_Record` entity
was retracted.

**Files changed:**
- **Added** `database/migrations/2026_09_26_000002_drop_regraft_records_table.php` — `up()` drops
  `regraft_records`; a reversible `down()` recreates the (empty) table. The original create migration
  `2026_09_25_000009_create_regraft_records_table.php` was **not edited** (kept as history).
- **Deleted** `app/Models/RegraftRecord.php`.
- **`app/Models/Batch.php`** — removed the `regraftRecords()` relation (the `ReplantingRecord`
  relation remains).
- **`app/Support/AnalyticsMetrics.php`** — removed the `use App\Models\RegraftRecord;` import, added
  `use App\Models\ReplantingRecord;`, and rewrote the Stock Ledger's Re Graft block to query
  `ReplantingRecord` by `replanting_date`, grouping per the batch's `scion_variety_id` (same shape as
  the Mortality block). `regraft_records` had **0 rows**, so no data migration was needed.
- **`app/Models/ScionReceiving.php`** — stale comment referencing `RegraftRecord` corrected.

**Verification (literal, not summarized):** `php -l` clean + `vendor\bin\pint` **passed**;
`php artisan migrate --force` → `2026_09_26_000002_drop_regraft_records_table .. DONE`;
`migrate:status` **0 Pending**; tinker: `Schema::hasTable('regraft_records')` = **false**,
`replanting_records` = **13 rows / SUM 195**, Stock Ledger grand Re Graft = **195**
(Criollo 35 / Trinitario 15 / Forastero 15 / Diaman 110 / Batangas 10 / White Flag 10; was 0).
A repo-wide grep for `regraft_records|RegraftRecord|regraftRecords` leaves **no live code references**
— only the kept create migration, the new drop migration and the retraction notes.

**Committed `22ef695`** (5 files changed, +60/−80). **No test suite run (P6).** ERD §2/§3.11 +
`AGENTS.md` + this handoff updated (ERD canonical + `_REVISED` kept byte-identical).

### Session CG — Phase 7C (parent/sub-batch nesting + Stock Ledger / analytics alignment)
**Baseline:** the client-approved `docs/CacaoTrack_PRD_UPDATED.md` (§9.3, FR-08) /
`docs/CacaoTrack_ERD_UPDATED.md`. **No PRD/ERD file was edited**; no v2.0 `[PROPOSED]` item was
implemented beyond the Phase 7A/7B provisional defaults. **No migration** (schema final).

**STEP 0 (evidence, read-only):** confirmed the Phase 7B coverage in `tests/Feature/RegraftFlowTest.php`
(raw `--filter` run, **OK 6 tests / 35 assertions**): `test_a_failure_after_the_source_update_rolls_everything_back`
atomic rollback, `test_duplicate_submission_is_blocked`, `test_a_user_without_record_operations_gets_403`,
`test_parent_counts_are_locked_after_a_regraft`, `test_invariants_hold_after_a_regraft` (family
conservation **and** `totalReplanted()` == `ReplantingRecord::totalRegraftedFromBatch()`),
`test_mortality_and_regraft_are_independent_across_the_family`. All existed; nothing was missing.

**PART 1 — `App\Support\BatchTree` (new, RULE 6):** `flatten(iterable): list<array{batch,depth,parent_code,parent_missing,ancestors}>`
and `groups()`. Roots by `grafted_date` then `batch_code`; children by `batch_code`; depth 3+;
a batch whose parent is filtered out is shown at depth 0, `parent_missing = true`, code resolved in
**one batched query** (no N+1); never drops a row (cycle safety net). Files:
`app/Support/BatchTree.php`, `tests/Feature/BatchTreeTest.php` (depth-3 ordering, children order,
orphan handling, zero-query when parents present, one-query when orphaned).

**PART 2 — Batches page:** `BatchController@index` now fetches the visible batches, splits them into
families with `BatchTree::groups()`, and paginates **one family per page** (a family never splits).
`resources/views/operational/batches/index.blade.php` + new
`resources/views/operational/batches/partials/batch-row.blade.php` render nested rows indented with
`↳`, a **Sub-batch** badge, the Parent Batch column, and a per-parent Bootstrap collapse (expanded by
default via `child-of-{id}` classes; `withCount('stockOutDetails')` avoids an N+1). Footer now reads
"batch families".

**PART 3 — Inventory page:** `InventoryController@index` orders snapshots through the helper (each
batch's snapshots newest-first via `Collection::sort`) and paginates the ordered collection; the view
adds a **Parent Batch** column, `↳` nesting and a Sub-batch badge, and the "Record Monthly Inventory"
select labels sub-batches `↳ B001-R1 (Toledo, parent B001)`.

**PART 4 — Stock Ledger:** `AnalyticsMetrics::stockLedgerGrid()` — **Grafted** now emits a value only
for root batches (`parent_batch_id === null`); sub-batch rows are kept for **Ready to Plant** (mapping
unchanged: live `available_count`); **Regraft** rows read `replanting_records` with
`Regraft {source} → {sub-batch}` (source from `parent`, sub-batch variety) and `replanting_date`;
**Seedlings** now filters `stock_ins.scion_variety_id IS NULL` — **before 1000 (all Stock In) → after
800 (seedling-only)**. New `AnalyticsMetrics::batchSummary()` returns the nested per-batch summary
(Batch/sub-batch, Parent, Scion Variety, Total Grafted, Available, Regraft, Mortality, Stock Out from
`SUM(stock_out_details.quantity)`, Date Grafted) ordered by the helper, with totals; a set-based
`MortalityRecord::effectiveQuantityByBatch()` keeps it N+1-free. View: footnote + **Batch summary
(nested)** table; new server route `GET /analytics/stock-ledger/export/csv` →
`AnalyticsController@exportLedgerCsv` replaces the old client-side scrape; `StockLedgerPdf` gained the
footnote + nested summary (with a summary-mode continuation header).

**PART 5 — Age Profile & Gap Report:** `AnalyticsMetrics::ageProfile()` now also returns nested
`rows` (own `grafted_date`/age/bucket + Parent); `AnalyticsMetrics::gapReport()` rows are nested with
`parent`/`depth` (formula unchanged, `gap = effectiveMortalityQuantity()`); view/PDF/CSV carry the
Parent column. `AnalyticsController::filterOptions()` eager-loads `scionVariety`, and a shared
`resources/views/analytics/partials/batch-options.blade.php` renders every batch dropdown
(stock-ledger, mortality-matrix, seasonality, age-profile, gap-report) as
`↳ B001-R1 (parent B001, Toledo)`. Help text on each page states: **filtering by a batch matches that
exact batch only; selecting a parent does not include its sub-batches.**

**PART 6 — Seeder:** `DatabaseSeeder` now regrafts 30 from B001-R1 into **B001-R1-R1** (Criollo) through
`RegraftBatch` (idempotent, guarded on `b001r1.regraft < 30`) and writes the current month's Inventory
snapshot for the parent and both sub-batches.

**PART 7 — Tests (new):** `BatchTreeTest`, `NestedBatchesTest`, `StockLedgerNestingTest`,
`HistoricalNestingTest`, `SeededFamilyKpiTest`; `AnalyticsTest`'s Gap CSV assertion updated for the new
Parent column. Coverage: tree ordering/orphan/N+1; Batches + Inventory nesting/parent/badge/select;
ledger Grafted exclusion (650), Regraft descriptions/variety, Seedlings 800≠1000, nested summary exact
rows, CSV + PDF contain the summary, exact-match batch filters (parent excludes sub-batches);
Age Profile + Gap Report nesting/parent/CSV; KPI conservation on the seeded family.

**Verified (literal):** MySQL suite **328 passed / 1406 assertions, 0 failures**; `vendor\bin\pint`
clean; `php -l` clean on every changed file; `php artisan view:cache` clean; `migrate:status` **0
Pending** on `db_capstone_test`. Seeded (test DB) query: available SUM **730** == Total Active
Inventory; total_grafted SUM **750** == CMR denominator; B001 family **500** conserved; `Σregraft`
**130** == `Σreplanting_records.quantity` **130**; ledger grafted **650**, ready_to_plant **730**,
seedlings **800**, mortality **55**, regraft **130**, pullout **20**. Stock Ledger CSV excerpt captured
(HTTP 200; grid + footnote + nested summary rows with `↳`). **`db_capstone` NOT touched at the time**
— its four `2026_09_27_*` migrations then remained **Pending** (**superseded:** the live DB was
upgraded + reseeded in Phase 7D, 2026-09-29). **Committed** `7c84843`, `59488db`, `cc89669`, `b780873`.

## 3. Current database state (`db_capstone`)

**✅ LATEST (Session DT, 2026-10-12) — live `db_capstone` holds owner-entered operational data
(re-verified read-only this session).** The current live state is the Session DI re-wipe followed by the
owner's manual entry (see banner `NEXT UP` (0)): **5 batches** — B001, B001-R1, B001-R1-R1, B001-R2, B002 —
with stock_ins 2, mortality_records 2 (B001-R2 `Disease` **110**; B002 `Others: Over-irrigation` **13**),
regraft_records 3, stock_outs 0, inventories 0, activity_logs 9; master data preserved (users 1 ·
scion_varieties 5 · suppliers 3 · clients 3); **`cacao:health` PASSES all 5 invariants**. **Do NOT
reseed/truncate without fresh owner sign-off.** The Session CY/CH paragraphs below are historical
(superseded by the Session DI wipe and the owner's re-entry).

**No schema and no data change in Sessions DX–DZ.** DX (test-only), DY (Regraft Deficit UI removal) and
DZ (the Dashboard Total Active Inventory + Analytics Total Grafted KPI cards) are UI/test work. DZ's
read-only live render through both controllers (no HTTP, no DB write) confirms Dashboard Total Active
Inventory **813** and Analytics Total Grafted **853** / active **813**.

**✅ LATEST (Session CY, 2026-10-01) — re-reseeded with today's fixes.** One owner-approved destructive
action (`php artisan migrate:fresh --seed --force`, connection `mysql` / database `db_capstone`) after a
verified backup
`C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_reseed_20261001_201512.sql`
(**37,218 B**, 23 `CREATE TABLE`, clean `-- Dump completed on 2026-10-01 20:15:13` trailer).
**Before:** 6 batches (5-batch seed + a manually-added **B004** of 50), stock_outs 2 / stock_out_details
2, activity_logs 13; `cacao:health` PASSED (6). **After:** **5 batches** (B004 gone → manually added,
not seeded), stock_ins 4, stock_outs 1, stock_out_details 1, mortality_records 4, regraft_records 2,
inventories 5, scion_varieties 5, users 1, activity_logs 11; `migrate:status` **0 Pending**;
`cacao:health` **PASSED** (5). Live KPIs: raw `Σtotal_grafted` **880** / live
`Σ(total_grafted − regraft)` **750** / Total Active Inventory **730** / Crude Mortality **7.33%**
(55/750) / Regraft Deficit **25**; the three cross-batch totals = **750**. Family 500/400/100 ·
100/50/30 · 30/30/0 · 150/150/0 · 100/100/0. The paragraphs below keep the earlier history.

**✅ NOTE (Session CH / Phase 7D, 2026-09-29) — `db_capstone` was upgraded to the approved schema and
reseeded.** Phase 7A's schema (`batches.regraft` + `batches.parent_batch_id`,
`replanting_records.parent_batch_id`; `batches.viable_count` / `stock_ins.viable` /
`inventories.viable` removed), Phase 7B's regraft flow and Phase 7C's nesting + Stock Ledger /
analytics alignment are all live now. The project owner explicitly approved one destructive action —
`php artisan migrate:fresh --seed --force` against `db_capstone` — after a fresh verified backup
(`...\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql`; 35,131 B / 23 tables).
`migrate:status` now shows **0 Pending**; the removed columns are gone and `batches.regraft` /
`batches.parent_batch_id` / `replanting_records.parent_batch_id` exist. **Current live family:** B001
Batangas 400/400 (regraft 100, mortality 25) → B001-R1 Toledo 70/50 (regraft 30, mortality 5) →
B001-R1-R1 Criollo 30/30; B002 Trinitario 150/150 (mortality 15); B003 Forastero 100/100
(mortality 10). Live KPIs: Active Inventory **730**, Crude Mortality **7.33%**, Regraft Deficit
**25**, Gap **55**. The paragraphs below keep the pre-7D history (Session CC state and earlier).

**⬇ HISTORICAL (pre-Phase 7D) — Session CC (2026-09-26): `Scion_Receiving` merged into `stock_ins` (schema + data).**
The client-confirmed merge ran migrations `2026_09_26_000003` (nullable `scion_variety_id` FK →
`scion_varieties`, `unit_price` decimal(12,2), `notes`; `supplier_id` made nullable) and
`2026_09_26_000004` (data-migrate `scion_receivings` into `stock_ins`, then drop it) against
`db_capstone`, after a fresh verified backup
(`...\Temp\opencode\db_capstone_backup_cc_20260926_170736.sql`; 36,778 B / 24 tables). **Result:**
`scion_receivings` is **gone**; `stock_ins` went **2 → 4** in the migration (2 seedling rows unchanged
+ 2 scion rows with `scion_variety_id` set and `viable == quantity` = 120 and 80; `origin`/`notes`
preserved; the null-supplier row kept). **Live now: `stock_ins` = 6 rows (3 seedling + 3 scion)** —
2 further rows were added through the new Stock In "Scion"/seedling UI on 2026-09-28 (`activity_logs`
9 → 14), which confirms the two-tab form works end-to-end. DB is **23 tables** (15 revised entities +
8 framework). KPIs unchanged (Active Inventory **395**, Crude Mortality **11.11%**, Regraft
Deficit **16**, Gap **50**); `migrate:status` **0 Pending**; merger validated reversible
(up→down→up on `db_capstone_test`). The create migration `2026_09_26_000001` is kept as history.

**Session CA (2026-09-26) — the wipe + reseed was EXECUTED (the one approved destructive action on
the live DB).** `db_capstone` was backed up first
(`...\Temp\opencode\db_capstone_backup_2026_09_26_20260926_154153.sql`) then rebuilt with
`php artisan migrate:fresh --seed --force` (connection `mysql`, database `db_capstone`) using the P6
`DatabaseSeeder` + `ActivityLogSeeder`. The pre-refactor residue is **gone**: now **3** batches (B001
180/180, B002 140/120, B003 95/95; `Σviable` 415 / `Σavailable` 395), 2 stock-ins, 1 stock-out + 1
detail, 3 mortality, 3 regraft, **2 scion_receivings**, 3 inventories, 9 activity_logs, 3
scion_varieties, 3 suppliers, 3 clients, 1 user, 4 activity_tasks. **No batch has
`available_count > viable_count`.** Fresh KPIs: Active Inventory **395**, Crude Mortality **11.11%**,
Regraft Deficit **16**, Gap **50**; `migrate:status` 0 Pending. Any further destructive action on the
live DB needs fresh explicit sign-off.

**No schema and no data change in Session CB (Scion Receiving "Add" CRUD UI).** Only app code/routes/views
changed (`ScionReceivingController`, `StoreScionReceivingRequest`, the new
`operational/scion-receivings/index.blade.php`, 2 routes in `routes/web.php`, 1 sidebar link). No
migration was run and `db_capstone` was not modified — the full suite and the throwaway HTTP test ran
against `db_capstone_test` only. `scion_receivings` still holds the **2** seeded rows from Session CA.

**No schema and no data change in Session BZ (P6).** P6 touched only test files, factories and the
seeders (`DatabaseSeeder`/`ActivityLogSeeder`) — it did **not** run against `db_capstone` (all seeding
and the full suite ran on the dedicated `db_capstone_test` DB). At the time the live `db_capstone`
still contained the pre-refactor operational residue described in the Session BR paragraph; the
`AGENTS.md` §"`db_capstone` data policy" wipe + reseed it recommended was later **EXECUTED in Session
CA** (see above) — the residue is gone.

**Schema changed in Session BX (`regraft_records` dropped — the Session BU consolidation, APPLIED):**
migration `2026_09_26_000002_drop_regraft_records_table` **dropped `regraft_records`** (it held **0
rows** — no data migration needed; the create migration `2026_09_25_000009` is kept intact as
history). The DB is **24 tables** again (15 revised entities + `scion_receivings` + framework).
`app/Models/RegraftRecord.php` was deleted, `Batch::regraftRecords()` and the `AnalyticsMetrics` import
were removed, and `AnalyticsMetrics::stockLedgerGrid()` now reads `replanting_records` (grouped by
`replanting_date` + the batch's `scion_variety_id`). Live: `Schema::hasTable('regraft_records')` =
**false**; the Stock Ledger's grand Re Graft = **195** = `replanting_records` **13 rows /
SUM(quantity) 195** (was 0). Committed `22ef695`; no test suite run (P6).

**Schema changed in Session BQ (P5 — the one approved exception at the time; RETRACTED in BX):**
migration `2026_09_25_000009_create_regraft_records_table` created the `regraft_records` table (0 rows —
no fabricated data). No other schema/data change: the analytics layer is read-only. The DB was **24
tables** at that point (15 revised entities + `regraft_records` + framework); **Session BV later adds
`scion_receivings` → 25 tables** (see the Session BV paragraph below); **Session BX later drops
`regraft_records` → 24 tables again**.

**No schema and no data change in Session BS.** The Gap-formula correction is code/view/PDF/CSV +
`AGENTS.md` only; `db_capstone` was read read-only. A fresh read via `AnalyticsMetrics::gapReport()`
returns `totalGap = 580` for all 9 batches (= `SUM(mortality_records.quantity)`), matching the
Session BQ operational snapshot (`mortality_records` 17 rows / quantity **580**). No row was written.

**No schema and no data change in Session BT** (display-text rename only — no migration run and
`db_capstone` untouched). **No schema and no data change in Session BU** (read-only audit; the DB was
read via `php artisan tinker` only). **Live confirmation (Session BU, historical):** `regraft_records`
**still existed** (**0 rows**) alongside `replanting_records` (**13 rows**); `Schema::hasTable()`
returned true for both. **Resolution:** Session BX **applied** the consolidation and dropped
`regraft_records` (`Schema::hasTable()` now **false**).

**Schema changed in Session BV (`Scion_Receiving` — a new table):** migration
`2026_09_26_000001_create_scion_receivings_table` created `scion_receivings` (**0 rows — no fabricated
data**). The DB was then **25 tables** (15 revised entities + `regraft_records` + `scion_receivings` +
framework); **Session BX later drops `regraft_records` → 24 tables**. **No operational data was read or
changed**, and `scion_varieties` gained **no** balance column. A freshness/viability rule is **NOT
implemented**. No other schema/data change (`ReplantingRecord` untouched; `RegraftRecord` was later
removed in Session BX).

**Live `db_capstone` re-read in Session BQ (real UI usage — no session fabricated rows):** `users` 5;
`scion_varieties` **8** (0 archived); `batches` **9** (0 archived); `stock_ins` **8** (quantity
**2250** / viable **2250**); `stock_outs` **7** with **7** details (released **2130**);
`mortality_records` **17** (quantity **580**); `replanting_records` **13** (sum **195**);
**`regraft_records` 0** (new table); `inventories` **9**; `suppliers` 5; `clients` 4; `analytics`
**12**; `activity_logs` **74**. Batch totals: `available_count` sum **1130**, `viable_count` sum
**1170**, `total_grafted` sum **1450**. Scion varieties in use include Batangas, Batangas-Scion,
Criollo, Diaman, Forastero, Trinitario, White Flag, tanom buh1.

**Session BR audit (read-only) — the live operational rows are pre-refactor residue.** Every
`batches.created_at` is `2026-09-12`…`2026-09-23` (< P1 `2026_09_25`); B001–B003 come from the seeder,
B004–TAB008 from the old BatchController. All 7 `stock_out_details` are the migrated legacy headers
(no post-refactor stock-out). `available_count` was backfilled from the old
`inventories.current_quantity` (`2026_09_25_000007:41`), so it tracks
`initial + stock-ins − stock-outs − reversals` = 1260 + 2250 − 2130 − 250 = **1130** and **not**
`viable_count`. Hence B005 `available 600` > `viable 100`, and B001/B004 `available 0`.
`viable − available` (40) ≠ stock-out (2130) is expected. **No data was changed.**

**No schema and no data change in Session BO** (Phase 4 was the view/sidebar layer only — the one
controller edit was a view-path string; no migration was run and `db_capstone` was not modified).

**No schema and no data change in Session BP** (housekeeping only — committing P4 and correcting the
`AGENTS.md` banner; no migration was run, `db_capstone` was not modified, and no live rows were read).

**Schema + data changed in Session BJ (P1 — the revised model is now live in the DB):** eight
reversible migrations `2026_09_25_000001..000008` applied (pre-P1 backup
`...\Temp\opencode\db_capstone_pre_p1_20260925_142909.sql`). Added `scion_varieties` (8 rows from
`varieties`) and `stock_out_details` (7 lines from the 7 old stock-outs); added
`batches.scion_variety_id` (0 null), `stock_ins.viable` (= quantity) and `stock_ins.origin` (`''`);
converted `inventories` to monthly snapshots (`inventory_date/total_grafted/viable/regraft/mortality`,
no `current_quantity`, no UNIQUE `batch_id`); dropped `batch_monitorings`, `mortality_adjustments`,
`stock_reversals`, `varieties`, `scions`, `pot_types`; `batches.available_count` backfilled from the
old `inventories.current_quantity` (**sum 1130**). The DB is now **23 tables** (15 entities +
framework). **The app code/views/tests still target the OLD schema and will error until P2–P6 land.**

**No schema and no data change in Session BK** (Phase 2 Models was planned only — the live schema
was read read-only to validate the target models and matches the revised ERD exactly; no migration
was run and `db_capstone` was not modified).

**No schema and no data change in Session BL** (Phase 3 Controllers/routes was planned only — the
live `app/Models`, `app/Http/Controllers` and `routes/web.php` were read read-only to validate the P3
plan and confirm P2 is not yet implemented; no migration was run and `db_capstone` was not modified).

**No schema and no data change in Session BM** (Phase 2 Models was implemented — the live column
lists were read read-only and every model now maps 1:1 to the already-applied P1 schema; **no
migration was run and `db_capstone` was not modified**). The models match the revised 15-entity
schema, but the controllers/routes/views/seeders/tests/`AnalyticsMetrics` still target the OLD model
and will error until P3–P6 land. A `git` repo now exists (initial snapshot `62a521a`). Rollback-safe
live checks (inside a rolled-back transaction) confirmed `Batch::totalAvailableQuantity()` = **1130**
(matching the P1 backfill), the mortality cap, the frozen Inventory status, the Stock Out
scion-variety derivation, and the `viable <= quantity` guard — no rows were written.

**No schema and no data change in Session BN** (Phase 3 Controllers/routes was implemented — only
`app/Http/Controllers`, the new `app/Http/Requests`, `app/Support/DuplicateSubmissionGuard.php` and
`routes/web.php` changed; **no migration was run and `db_capstone` was not modified**, and no live
rows were read). The controllers/routes now target the revised 15-entity schema, but the
views/sidebar/seeders/tests/`AnalyticsMetrics` still target the OLD model, so the running app and the
feature suite remain broken until P4–P6 land.

**No schema and no data change in Session BI** (Phase 0 was docs-only: `db_capstone` backup +
PRD/ERD replacement + `AGENTS.md` rewrite). **No schema and no data change in Session BH** (planning
only — the revised-ERD refactor was not started). The live counts read in Session BH are in the §2
Session BH entry (users 5, `batches` 9, `activity_logs` 74, etc.); they are real UI usage since the
Session BG snapshot below and are **not** produced by either session. The Session BG snapshot is
retained as the last verified state.

**No schema and no data change in Session BG.** The Batch Monitoring refinement is code-only: the
new alive-count rule (`viable_count` decremented by mortality) applies to **future** mortality
writes, so the stored `viable_count` of existing batches still reflects the pre-change values
(no historical backfill was run). Live read in Session BG: `batches` **8** all with
`monitoring_start_date`; alive counts = `viable_count` (B001 180, B002 140, B003 95, B004 350,
B005 100, B006 100, B007 0, TAB007 100); derived status **B001–B003 Complete**, **B004–B007/TAB007
Pending**; `batch_monitorings` **0 rows** (retained, no longer written). The Session BE counts below
are otherwise unchanged.

**Live re-read in Session BE (real UI usage since Session BD — no session fabricated rows):**
`users` 5, `activity_tasks` 4 (1 archived), `task_completes` 0, `varieties` 7, `scions` 1
(`Scion`, `quantity` **25**), `pot_types` 3, `batches` **8** (all with `monitoring_start_date`),
`mortality_records` **13** (raw **490**) + `mortality_adjustments` 1 (−5) → effective **485**,
`replanting_records` **11** (sum **175**), `inventories` **8** (sum **1035**), `suppliers` 5,
`stock_ins` **6**, `clients` 4, `stock_outs` 4, `stock_reversals` 1, `analytics` **12**,
`activity_logs` **62**, `batch_monitorings` **0**; **0 archived rows** on all six master tables.
Drift vs the Session BD snapshot (mortality 11→13, replanting 8→11, `activity_logs` 57→62, scion
pool 40→25 via the approved replanting debit) is UI usage, not session code.

**Schema + data changed in Session BC (Batch Monitoring):** two migrations were applied —
`2026_09_22_000001_add_monitoring_start_date_to_batches_table` (adds nullable
`batches.monitoring_start_date`, backfilled from each batch's real `grafted_date`; all **8** batches
now have a start date) and `2026_09_22_000002_create_batch_monitorings_table` (new
`batch_monitorings` table, **0 rows** — no fabricated data). No other data changed; every
operational figure below is unchanged. A `db_capstone` backup was taken first
(`...\Temp\opencode\db_capstone_pre_batch_monitoring_20260922.sql`).

**Schema + data changed in Session BA (scion stock pool):** migration
`2026_09_21_000002_add_quantity_to_scions_and_consolidate` added `scions.quantity` and consolidated
the four named scions into **one** `Scion` row (all 8 batches repointed; starting balance **40**).
Only the `scions` table changed — every operational figure below (inventory, mortality, replanting)
is unchanged. Full pre-change state is in
`...\Temp\opencode\db_capstone_pre_scion_pool_20260921_230937.sql`.

All migrations ran via `php artisan migrate` (not fresh — existing data preserved).
**Snapshot refreshed in Session Q; re-confirmed in Sessions R and S (data unchanged); extended in
Session T with the new `activity_logs` backfill; extended in Session U with the `users.role`
column (admin backfilled to `Administrator`)** to match live app data (vibecoding sessions added
master data and real transactions beyond the original seeder). Re-confirmed unchanged in
Session V (migrations, admin role, and counts as below; `profile_updated` = 0 rows in
`db_capstone` — no prod profile edits since Session U). Re-confirmed in Session W (schema,
admin role, and counts as below; `profile_updated` still = 0; `activity_logs` corrected to 11 —
the backfill 10 plus 1 `report_generated` row). Re-confirmed unchanged in Session Z (no migration
and no data change — Batch Management reuses the existing `batches` table).
**Re-confirmed unchanged in Session AB:** the live snapshot below is identical to the Session AA
snapshot — no drift (users 2, batches 4, active inventory 585, analytics 3, activity_logs 16).
**Schema extended in Session AC:** two migrations were applied to `db_capstone`
(`2026_09_16_000001_add_settings_to_users_table`, `2026_09_16_000002_create_notifications_table`).
No data rows changed — `notifications` = 0 (new table), `users.settings` = NULL for both users,
and every count below is unchanged. **Re-confirmed unchanged in Session AD** (verification only:
`notifications` still 0, `settings` NULL, `activity_logs` 16, `batches` 4, users 2).
**Schema extended in Session AE:** one migration was applied to `db_capstone`
(`2026_09_16_000003_add_archived_at_to_activity_tasks_table`). No data rows changed —
`activity_tasks.archived_at` is NULL for all 4 tasks, and every count below is unchanged.
**Schema reverted in Session AF:** one migration was applied to `db_capstone`
(`2026_09_16_000004_revert_notifications_and_settings`) — the `notifications` table and the
`users.settings` column were **dropped** (both were empty/0 rows). No existing data rows changed;
every count below is unchanged.
**Schema extended in Session AH:** four migrations were applied to `db_capstone`
(`2026_09_16_000005..000008`): `roles` created, `role_permissions` created, `users.role_id` FK added
(nullable, backfilled from the legacy `users.role`), then the legacy `users.role` column **dropped
only after** the backfill was verified (3/3 users mapped, 0 null, 0 mismatches). No other data rows
changed; the role-based counts are added to the list below.
**No schema/data change in Session AI:** the Daily Checklist print redesign touched only the PHP
PDF class (`App\Support\ChecklistPdf`) plus two checklist view labels — no migration was run and no
data rows changed (the PDF is generated live from the existing normalized data).
**Schema extended in Session AJ:** one migration
(`2026_09_17_000001_add_archived_at_to_master_data_tables`) added a nullable, indexed `archived_at`
to `suppliers`, `clients`, `varieties`, `scions`, `pot_types` and `batches`. No data rows changed —
every existing row is active (`archived_at` NULL); nothing was archived or removed, and the six
`DELETE` routes were replaced by archive/restore routes (no row deletions).
**No schema/data change in Session AK:** the Stock Ledger printable PDF is generated live from the
existing normalized operational data (no `analytics` row is written by printing); no migration was
run and no data rows changed.
**No schema/data change in Session AL:** the Mortality Root-Cause Matrix refinement only changed
`AnalyticsMetrics::mortalityMatrix()`, the matrix Blade view and its tests; no migration was run and
no data rows changed (both the raw and effective figures are read live from the existing
`mortality_records` + `mortality_adjustments`).
**No schema/data change in Session AM:** the Generate Report output work only changed
`AnalyticsMetrics::summaryFor(TYPE_STOCK_LEDGER)` (+ new `stockLedgerSummary()`), the Analytics
controller, two analytics views, two new partials and the tests; no migration was run and no data
rows changed. (The `analytics` rows already present keep the payloads they were generated with;
only future generations use the corrected ledger totals.)
**No schema/data change in Session AN:** the printable Matrix + CSV export are read-only
projections of live matrix data — no migration was run and no data rows changed. The only new file
is `App\Support\MortalityMatrixPdf`; the routes/controller/view add exports.
**No schema/data change in Session AO:** verification only — no migration, no code and no data rows
changed. The live snapshot below was re-read; the only drift since Session AJ is report/audit
history from real UI usage (`analytics` 4 → **9**, `activity_logs` 22 → **27**). Operational
balances are unchanged (active inventory **605**, effective mortality **45**, replanting deficit
**11**).
**No schema/data change in Session AP:** the Operational Analytics printable PDF + CSV export are
read-only projections of live KPI/chart data — no migration was run and no data rows changed. The
only new file is `App\Support\OperationalAnalyticsPdf`; the controller/routes/view add exports.
**No schema/data change in Session AQ:** verification only — no migration, no code and no data rows
changed. The live snapshot below was re-read and is **identical to the Session AO snapshot**
(`analytics` 9, `activity_logs` 27, active inventory 605, 0 archived rows); the only change since
Session AO is the route count (80 → **82**) from Session AP's two analytics export routes.
**No schema/data change in Session AR:** the Gap Report printable PDF + CSV export are read-only
projections of live gap data — no migration was run and no data rows changed. The only new file is
`App\Support\GapReportPdf`; the controller/routes/view add exports and the shared `filterLabel()`.
**Data changed in Session AS (UI usage, not this session's code):** verification only on our side —
no migration and no code changes. The live re-read shows **one new user** (#5 `manager` /
`manager.test@gmail.com`, role `Manager`) created through the UI, which added one `user_created`
audit row: `users` 4 → **5**, `activity_logs` 27 → **28**. All operational balances and the
`analytics` history are unchanged.
**Schema merged in Session AU:** the `roles` and `role_permissions` tables and `users.role_id`
were dropped; `users` now has `role` (string) + `permissions` (JSON). The backfill was verified
(Administrator/7, Manager/6, Staff/2, `farmers(propagator)`/1). This is the only schema change
since Session AJ, besides the checklist `archived_at` column.

**Refreshed in Session AU (real UI usage since Session AS):** the live snapshot below was read
directly from MySQL. Operational data grew through the UI (new varieties, more batches, more
mortality/replanting, more stock transactions and report/audit history); **no session fabricated
rows** — every figure is real app data. The list below is the **Session AU live snapshot**.

- users: **5** — each stores `role` + `permissions` directly (no `role_id`):
  `admin@cacaotrack.test` / `password` → `Administrator` (7 perms); `test@gmail.com` (#2) and
  `test1@gmail.com` (#3) → `Staff` (2 perms: record_operations, view_analytics);
  `test2@gmail.com` (#4) → `farmers(propagator)` (1 perm: record_operations);
  `manager.test@gmail.com` (#5, name `manager`) → `Manager` (6 perms)
- `roles` table: **does not exist** (dropped in Session AU); `role_permissions`: **does not exist**
- `users.role` (string) + `users.permissions` (JSON): **exist** (Session AU); `users.role_id`:
  **does not exist** (dropped in Session AU)
- activity_tasks: 4 (the `archived_at` column exists; UI usage has archived/deleted tasks —
  `task_archived` = 2 and `task_deleted` = 1 audit rows exist)
- task_completes: 0
- varieties: 7 (Batangas, Criollo, Diaman, Forastero, `tanom buh1`, Trinitario, White Flag)
- scions: 4 (ICS-1, ICS-6, Meggy, UF-29)
- pot_types: 3 (Standard Polybag, Root Trainer, Ceramic Pot)
- batches: 7 — B001 200/180 (inv 0, depleted), B002 150/140 (inv 140), B003 100/95 (inv 95),
  B004 350/350 (inv 0), B005 100/100 (inv 500), B006 100/100 (inv 100), TAB007 100/100 (inv 100);
  all status `active`, **none archived**
- mortality_records: 9 (raw total **425**) + mortality_adjustments: 1 → effective total **420**
- replanting_records: 6
- inventories: 7; **total active = 935**
- suppliers: 5 (Bukidnon Seedling Supply, Davao Cacao Growers, Doms, North Mindanao Nursery, titomeg)
- stock_ins: 5 (Doms→B001 1000; Bukidnon Seedling Supply→B004 20 + 30 + 500; titomeg→B005 400)
- clients: 4 (Cacao Farmers Coop, Green Pod Traders, Mountain View Estate, Niel)
- stock_outs: 4 (Cacao Farmers Coop←B001 430; Mountain View Estate←B001 500; Niel←B004 400;
  Niel←B004 500)
- stock_reversals: 1 (reverses stock-in #1, qty 250)
- `analytics`: **12** (operational 6, stock_ledger 3, mortality_matrix 2, seasonality 1)
- `activity_logs`: **51** — batch_created 4, batch_updated 1, mortality_adjusted 1,
  mortality_recorded 9, replanting_recorded 6, report_generated 11, role_created 1, role_updated 1,
  stock_in_recorded 5, stock_out_recorded 4, stock_reversal_recorded 1, task_archived 2,
  task_deleted 1, user_created 4 (`role_created`/`role_updated` are historical rows — the role
  tables no longer exist)
- `notifications` table: **does not exist**; `users.settings` column: **does not exist** (Session AF)
- `archived_at` columns: present on `suppliers`, `clients`, `varieties`, `scions`, `pot_types`,
  `batches` (Session AJ, ERD §9.8) — **0 archived rows** across all six.

**Inventory reconciliation (B001):** 180 (seed) + 1000 (SI-1) − 430 (SO-1) − 250 (REV-1) − 500 (SO-2)
= **0** (status `depleted`).
**B004:** 350 (seed) + 20 + 30 + 500 (SI) − 400 − 500 (SO) = **0**.
**B005:** 100 (seed) + 400 (SI) = **500**. **B006:** 100. **TAB007:** 100. **B002:** 140.
**B003:** 95. Total active inventory = **935**.

Effective mortality (per batch): B001 30, B002 20, B003 10, B004 300, B005 50, B006 0, TAB007 10
(total **420**).
Replanting deficit (per batch): B001 15, B002 5, B003 0, B004 200, B005 50, B006 0, TAB007 10
(total **280**).
Dashboard KPIs (Session AU live): Total Active Inventory **935**, effective mortality **420**,
Replanting Deficit **280**.

**Schema extended in Session AX:** one migration
(`2026_09_21_000001_add_scion_quantity_to_stock_ins_table`) added `stock_ins.scion_quantity`
(unsigned integer, default `0`) — approved ERD revision §2.15/§9.9. **No data rows changed**: every
existing stock-in reads `0`. The Scion Management cards are computed live from real data
(`App\Support\ScionMetrics`); no reporting table was added.

**No schema/data change in Session AY:** the **Total Available Scions** card formula was revised in
`App\Support\ScionMetrics` to `max(0, SUM(stock_ins.scion_quantity) − SUM(replanting_records.quantity))`
so replanting deducts from it (ERD §9.9 revised). It is derived from the existing
`stock_ins`/`replanting_records` tables — no migration, no new table, no route, and no DB data
changed.

**Live re-read in Session AZ (real UI usage since Session AU — no session fabricated rows):**
`users` 5, `activity_tasks` 4 (1 archived), `task_completes` 0, `varieties` 7, `scions` 4,
`pot_types` 3, `batches` **8** (B001 200/180 inv 0, B002 150/140 inv 140, B003 100/95 inv 95,
B004 350/350 inv 0, B005 100/100 inv **600**, B006 100/100 inv 100, **B007 150/0 inv 0 (new)**,
TAB007 100/100 inv 100; all `active`, none archived), `mortality_records` **11** (raw sum **460**)
+ `mortality_adjustments` 1 (sum −5) → effective **455**, `replanting_records` **8** (sum 160),
`inventories` **8** (sum **1035**), `suppliers` 5, `stock_ins` **6** (scion-received sum 200),
`clients` 4, `stock_outs` 4, `stock_reversals` 1, `analytics` **12** (operational 6, stock_ledger 3,
mortality_matrix 2, seasonality 1), `activity_logs` **57**. `archived_at` rows **0** on all six
master tables. Drift vs the Session AU snapshot above is UI usage, not session code.

## 4. How to use / run
> **Refactor mid-flight (Session BM):** the models have moved to the revised 15-entity schema but the
> controllers/routes/views/seeders/tests/`AnalyticsMetrics` have not (P3–P6 pending), so the running
> app currently errors. This section describes the app once P3–P6 land.
1. Login at `http://localhost:8000/login` with `admin@cacaotrack.test` / `password`.
   - The **Dashboard** (`http://localhost:8000/`, login required — Session AA) is the landing page:
     real **Total Active Inventory / Crude Mortality Rate / Replanting Deficit** KPI cards, real
     **Mortality Trend (last 6 months)**, **Survival vs Mortality** and **Inventory by Variety**
     charts, and the latest 5 entries from the real `activity_logs` audit trail. Every value is
     computed from the database through `App\Support\AnalyticsMetrics` (RULE 5/12); each chart shows
     "No data available for the selected period." when empty. `View All` opens Activity History,
     `View Analytics` opens Operational Analytics. No new table backs it.
2. Checklist at `http://localhost:8000/operational/checklist` (redirects to login when signed out).
3. Add / edit tasks; mark a pending task complete with optional remarks. Tasks are never deleted —
   use the **archive** (folder) button to hide a task from the active list; restore it anytime from
   the **Archived** tab. Archive/restore keep all completion history and write `task_archived`/
   `task_restored` rows to Activity History.
4. Same task can only be completed once per day; an archived task cannot be completed until it is
   restored. Click **Export PDF** (pick a date, default today) to open a printable FPDF
   "Daily Activity Checklist" built from the real saved tasks/completions for that date
   (`GET /operational/checklist/export`).
5. Mortality at `http://localhost:8000/operational/mortality`:
   - Filter by batch, variety, or date range.
   - Click **Record Mortality** to add a new record (batch, quantity, cause, date). Recording it
     **deducts the quantity from the batch's alive count** (`batches.viable_count`, floored at 0)
     inside the same DB transaction (Session BG — mortality is the **only** plant-status writer).
   - Click **Adjust** on any row to apply a signed-delta correction (negative reduces mortality and
     restores plants to the alive count; positive adds deaths and deducts further); reason and
     approval are required.
   - Click **View** to see full record details and adjustment history.
   - A new record cannot exceed the batch's current alive count (`Batch::remainingMortalityCapacity()`).
6. Replanting at `http://localhost:8000/operational/replanting`:
   - Filter by batch.
   - Click **Record Replanting** to add a record (batch, quantity, date, optional remark).
   - Click **History** on a row to see that batch's replanting records.
   - Replanting quantity cannot exceed the batch's remaining deficit
     (effective mortality − already replanted).
7. Inventory at `http://localhost:8000/inventory`:
   - Search by batch code, filter by variety and status (active / depleted).
   - Each row shows Grafted, Re-Graft, Mortality, Viable, Available plus a status badge.
   - Click **View Details** for the batch's general info and inventory status.
   - Click **Export Inventory** to download the currently shown rows as CSV.
   - Balance is the stored `inventories.current_quantity`; Stock In increases it, Stock Out
     decreases it, and Stock Reversal corrects it (by reversing an earlier Stock In/Out).
8. Stock In at `http://localhost:8000/inventory/stock-in`:
   - Search by supplier/batch, filter by supplier, batch, or received-date range.
   - Click **New Stock In** (supplier, batch, quantity, date received) — recording it increases the
     batch's inventory balance inside a DB transaction (FR-08).
   - Click **New Supplier** to add a supplier inline (the New Stock In modal then reopens
     preselected).
   - Click **View** for full transaction details.
   - Records are append-only; corrections go through Stock Reversal.
9. Stock Out at `http://localhost:8000/inventory/stock-out`:
   - Search by client/batch, filter by client, batch, or release-date range.
   - Click **New Stock Out** (client, batch, quantity, release date, optional total amount) —
     recording it decreases the batch's inventory balance inside a DB transaction (FR-09); a release
     larger than the available balance is rejected.
   - Click **New Client** to add a client inline (the New Stock Out modal then reopens preselected).
   - Click **View** for full transaction details.
   - Records are append-only; corrections go through Stock Reversal.
10. Stock Reversal at `http://localhost:8000/inventory/stock-reversal`:
    - Search by reason/batch, filter by type (Stock In / Stock Out) or reversal-date range.
    - Click **Create Reversal**: pick the transaction type, then the original transaction (only ones
      with a remaining reversible quantity are listed), the quantity, reason, and date. Recording it
      corrects the batch's inventory balance inside a DB transaction (FR-10): a Stock-In reversal
      decrements the balance; a Stock-Out reversal increments it.
    - A reversal quantity cannot exceed the reversible quantity (original − already reversed); a
      Stock-In reversal is further bounded by the current inventory balance.
    - Click **View** for full reversal details.
    - Reversals are append-only; the original Stock In/Out rows are never mutated.
11. Suppliers at `http://localhost:8000/inventory/suppliers` (FR-11):
    - Search by supplier name, contact number, or address; results are paginated.
    - Click **Add Supplier** to create one (name, contact number, address).
    - Click the pencil to edit, the eye to view details (including stock-in count), or the archive
      icon to archive. Archived suppliers leave the active list and the New Stock In dropdown but
      keep their stock-in history; restore them from the **Archived** tab.
    - Duplicate supplier names are allowed.
12. Clients at `http://localhost:8000/inventory/clients` (FR-12):
    - Search by client name, contact number, or address; results are paginated.
    - Click **Add Client** to create one (name, contact number, address).
    - Click the pencil to edit, the eye to view details (including stock-out count), or the archive
      icon to archive. Archived clients leave the active list and the New Stock Out dropdown but
      keep their stock-out history; restore them from the **Archived** tab.
    - Duplicate client names are allowed.
13. Varieties at `http://localhost:8000/inventory/varieties` (FR-13):
    - Search by variety name or description; results are paginated.
    - Click **Add Variety** to create one (name, optional description).
    - Click the pencil to edit, the eye to view details (including batch count), or the archive
      icon to archive. Archived varieties leave the active list and the new-batch variety dropdown
      but existing batches keep their link; restore them from the **Archived** tab.
    - Duplicate variety names are allowed.
14. Scions at `http://localhost:8000/inventory/scions` (FR-14):
    - Scions are a **single generic stock pool** (`Scion` with a stored `quantity`), not named
      varieties (approved ERD §2.8/§9.10). The page shows **Number of Scions**, **Scions Received**,
      **Re-grafted Scions** and **Rejected Scions** (`App\Support\ScionMetrics`, RULE 6).
    - Click **Receive Scions** to credit the pool (`POST /inventory/scions/receive`) inside a DB
      transaction, writing a `scion_stock_received` audit row.
    - Saving a **replanting record debits** the pool transactionally (rejected if insufficient).
15. Pot Types at `http://localhost:8000/inventory/pot-types` (FR-15):
    - Search by pot type name or description; results are paginated.
    - Click **Add Pot Type** to create one (name, optional description).
    - Click the pencil to edit, the eye to view details (including batch count), or the archive
      icon to archive. Archived pot types leave the active list and the new-batch pot dropdown but
      existing batches keep their link; restore them from the **Archived** tab.
    - Duplicate pot type names are allowed.
16. Analytics at `http://localhost:8000/analytics` (login required):
    - **Operational Analytics** (`/analytics`): real Total Active Inventory, Crude Mortality Rate
      and Replanting Deficit KPI cards, plus Mortality Trend, Inventory by Variety, Replanting
      Deficit by Batch and Survival vs Mortality charts (all real data; empty-state message when a
      chart has no data). Click **Generate Report** to save a snapshot.
    - **Stock Ledger** (`/analytics/stock-ledger`): one row per batch (PRD §13.1) with mortality,
      replanting and status; filter by batch, variety or grafted-date range; paginated; Export CSV;
      **Print Ledger** opens a printable FPDF report of the filtered rows + totals
      (`GET /analytics/stock-ledger/export`).
    - **Root-Cause Matrix** (`/analytics/mortality-matrix`): variety rows × mortality-cause columns
      with totals; filter by date range/variety/batch.
    - **Seasonality Heatmap** (`/analytics/historical/seasonality`): year × variety heatmap.
    - **Age Profile** (`/analytics/historical/age-profile`): inventory by batch age bucket.
    - **Gap Report** (`/analytics/historical/gap-report`): expected (viable) vs actual (inventory)
      and the gap, per batch and in total.
    - Every Analytics page has a **Generate Report** button that stores a row in `analytics`.
17. Activity History at `http://localhost:8000/activity` (login required):
    - A read-only, filterable audit trail of operational actions (FR-17 / PRD §24).
    - Filter by search (description/user/batch), user, activity type, batch, or date range;
      results are paginated.
    - Click **View** on a row for full details (type, user, batch, variety, description, date/time).
    - Rows are written automatically by the checklist, mortality, replanting, stock in/out/reversal
      and report-generation actions (no manual add form).
18. Profile at `http://localhost:8000/profile` (login required):
    - A working own-account page: avatar initial, name, role badge, Member Since.
    - Click **Edit Profile** to update name/email (email must stay unique); role is
      display-only and cannot be changed here.
    - Click **Change Password** (current password required, min 8 chars, confirmation must
      match).
    - Both actions write a `profile_updated` row to Activity History.
19. Batches at `http://localhost:8000/inventory/batches` (FR-16, Management dropdown):
    - Search by batch code; filter by variety, scion, or status; results are paginated. KPI cards
      show total / active / inactive / with-mortality for the current filters.
    - Click **Add Batch** (batch code, variety, scion, pot type, grafted date, total grafted,
      viable count, status). Available count is set to the viable count and the batch's single
      `inventories` row is created automatically (one row per batch, ERD §9.3) — all inside a DB
      transaction.
    - Click the pencil to edit. Master fields stay editable; **total grafted / viable count lock
      once the batch has operational records** (mortality, replanting, or stock in/out), so the
      counts cannot be changed after use.
    - Click the eye for details (plus mortality/replanting record counts and current inventory), or
      the archive icon to archive. Archived batches leave the active list and all new-record
      dropdowns but their inventory and operational history are preserved; restore them from the
      **Archived** tab.
    - Duplicate batch codes are rejected (unique). Every add/edit/archive/restore writes a
      `batch_created`/`batch_updated`/`batch_archived`/`batch_restored` row to Activity History
      (batch code in the description; `batch_id` is NULL so the activity FK can't block anything).
20. Toast feedback (no page, no table — Session AF):
    - Every successful or failed action flashes a `session('success')` / `session('error')` message
      that `layouts/app.blade.php` renders as a Bootstrap toast (top-right, auto-hide 5s) via
      `resources/views/partials/toasts.blade.php` + `resources/js/app.js`.
    - The old navbar bell and `/notifications` + `/settings` pages/modal controls **no longer
      exist** (reverted).
21. Verify data directly:
    `php artisan tinker --execute="echo App\Models\Batch::count();"`
22. Formatting: `vendor\bin\pint`.
23. Re-seed if needed: `php artisan migrate:fresh --seed` (or `php artisan db:seed --force`).
24. Batch Monitoring at `http://localhost:8000/operational/batch-monitoring` (FR-18, Session BG):
    - A **read-only viability view**: one row per active batch —
      **Batch | Variety | Start Date | Age | Alive Plants | Status**; filter by batch code, batch,
      status (`Pending`/`Complete`) or reference date.
    - **Alive Plants** = the stored `batches.viable_count` (kept current by Mortality Tracking).
      The derived status is **Pending** until the batch reaches the full 21-day lifecycle
      (`elapsed >= 21`), then automatically **Complete**; the 21 days are counted from
      `Batch.Monitoring_Start_Date`.
    - Click **Start Monitoring** to set a batch's `monitoring_start_date`.
    - There is **no Record Monitoring update, no monitoring history and no survival rate**.
      `Batch_Monitoring` is retained for history but is never written.

25. Scion receiving is **not a separate page** — it is the **"Scion" tab** of the Stock In form
    (Session CC; the former `/operational/scion-receivings` page/route was retired). At
    `http://localhost:8000/inventory/stock-in`:
     - The **New Stock In** modal has a **Seedlings / Scion** Bootstrap tab pair sharing one form
       (supplier, quantity, origin, date received). The **Seedlings** tab records a plain seedling
       delivery; the **Scion** tab adds **scion variety** (active only) + **unit price** (+ optional
       notes). The received `quantity` is recorded immediately for both — there is **no** five-day
       viability count and no `viable` column (Phase 7A).
     - The single Receiving History list is mixed and now carries a dedicated **TYPE** column
       (**Seedlings** green badge / **Scion** info badge, Session CN — derived via
       `StockIn::entryType()`), plus a **Type** filter (All Types / Seedlings / Scion). Scion rows show
       the **Scion Variety** name in that column; pure-seedling rows show "—". There is **one** list,
       not two.
     - A scion receipt is just a `stock_ins` row with `scion_variety_id` set; there is **no**
       discriminator column and **no** stock balance on `scion_varieties`. Conditional validation lives
       in `StoreStockInRequest` (`required_with` pair, scion variety ↔ unit price). Freshness/
       viability and scion-receipt Edit/Archive/Details remain unbuilt (client-gated, deferred).

## 5. Verification status
- **Session DZ (Dashboard Total Active Inventory + Analytics Total Grafted KPI cards, 2026-10-12): full
  suite GREEN; UI/controller/test only.** MySQL suite (`$env:DB_CONNECTION='mysql';
  $env:DB_DATABASE='db_capstone_test'; php artisan test`) **407 passed / 1984 assertions, 0 failures**
  (was 407/1977 at DY; +7 assertions locking the two cards and the PDF/CSV KPI alignment).
  `DashboardController` passes `totalActiveInventory` and the `/` KPI row is 3 cards (`col-xl-4`);
  `AnalyticsController::operationalData()` passes `totalGrafted` and the `/analytics` KPI row is 3 cards
  (`col-md-4`); the Operational Analytics PDF KPI strip + CSV KPI section gained **Total Grafted** from
  the same centralized `AnalyticsMetrics::totalGrafted()` (page/PDF/CSV one definition, RULE 6).
  `vendor\bin\pint --test` + `php artisan view:cache` clean. Read-only live render on `db_capstone`
  through both controllers (no HTTP/no DB write): Dashboard **813**, Analytics **853** / active **813**.
  **No migration, schema, route, permission-key or `db_capstone` write.**
- **Session DW (Daily Checklist History tab, 2026-10-12): full suite GREEN; read-only UI + tests only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **406 passed / 1960 assertions, 0 failures** (was 400/1900 at DV; +6 `DailyChecklistTest` cases: record
  list + selected detail, Completed/Pending derivation + status filter, staff/task search + date range,
  per-task Not Done + no-write assertion, auto-filter form + empty state, pagination keeps filters). New
  centralized `App\Support\ChecklistHistory` (RULE 6) builds one read-only record per day with stored
  completions (date, staff/verifier + roles, counts, derived Completed/Pending status) and shares the
  day's task-list definition with the existing `ChecklistPdf`; `DailyChecklistController@index` validates
  the filters and selects the record, `exportPdf` now reads the shared definition; the view gains the
  `Today's Checklist | History` tabs, the auto-applying filter card, the record list + read-only detail
  and the full-details modal; scoped CSS in `app.css` (`npm run build` re-run). Read-only live check on
  `db_capstone`: `ChecklistHistory::records()` → 1 record (2026-10-02, pending 1/4, Admin User), kernel
  `GET /operational/checklist?tab=history` → 200, `cacao:health` PASSED. `vendor\bin\pint --test` +
  `php artisan view:cache` clean. **No migration, schema, route, permission-key or `db_capstone` write;
  tests run only against `db_capstone_test`.**
- **Session DU (Inventory listing newest-first, 2026-10-12): full suite GREEN; 401 passed / 1913 assertions**
  (was 399/1909 at DT). `BatchTree::flatten()`/`groups()` gained an opt-in `$descending` flag (roots newest
  first, children still nested/ascending; every other consumer keeps the default) and
  `InventoryController@index` passes `descending: true`, so a newly added batch lands on top of page 1.
  `BatchTreeTest` +1 (descending roots + same-date tie-break + preserved nesting), `InventoryTest` +1
  (B002 before the older B001 family with B001-R1 still nested). Pint clean; read-only live `db_capstone`
  tree order starts with the newest root B002; `cacao:health` PASSED. **No migration, schema, route,
  permission-key or `db_capstone` write.**
- **Session DV (Archived tabs removed → guarded Delete on Checklist / Batches / Scion Variety, 2026-10-12):
  full suite GREEN; 400 passed / 1900 assertions** (was 401/1913 at DU; net −1 after deleting the obsolete
  archive/restore tests and adding the guarded-delete tests plus the new `ScionVarietyManagementTest`).
  `ActivityTask::canBeDeleted()`, new `Batch::deletionBlockers()`/`canBeDeleted()` and new
  `ScionVariety::deletionBlockers()`/`canBeDeleted()` (RULE 6); each delete is transactional and writes
  `Activity_Log` (`task_deleted`, `batch_deleted`, new `scion_variety_deleted`); 6 archive/restore routes
  removed and 2 DELETE routes added; the 3 page views are active-only. Approved one-time live cleanup: the
  3 archived unreferenced scion varieties deleted through the guarded app path after a fresh backup
  (`db_capstone_pre_scion_delete_20261012_175232.sql`, 34,928 B); live `scion_varieties` 2, every table 0
  archived, `cacao:health` PASSED. Pint + `view:cache` clean. **No migration/schema change; no other live
  data touched.**
- **Session DT (Inventory View modal shows the recorded mortality causes, 2026-10-12): full suite GREEN; read-only UI/query/test only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **399 passed / 1909 assertions, 0 failures** (was 398/1898 at DS; +1 `InventoryTest` case —
  `test_inventory_view_modal_shows_the_recorded_mortality_causes` — plus the existing View-modal test
  now asserts the empty-cause state `"mortality_causes":[]`). New centralized
  `MortalityRecord::causeBreakdownByBatch()` (RULE 6) groups a batch's `mortality_records` by the
  recorded `cause` (raw, incl. `Others: <description>`; blank → `Unspecified`), ordered by quantity then
  cause; `InventoryController@index` calls it **once** per page (no N+1) and exposes `mortality_causes`
  in each `summaries` row. The read-only `#viewBatchModal` renders it as a CAUSE / QUANTITY table built
  with JS `textContent` (user text never interpreted as markup); "No mortality recorded for this batch."
  otherwise. Read-only docs check: `_UPDATED` FR-04 ("Cause") / FR-08 ("derived from actual records") and
  ERD §3.10 `Mortality_Record.Cause` are satisfied; no PRD/ERD edit. Read-only live check on
  `db_capstone`: `B001-R2` → Disease 110, `B002` → "Others: Over-irrigation" 13, other batches empty.
  `vendor\bin\pint --test` + `php artisan view:cache` clean. **No migration, schema, route,
  permission-key or `db_capstone` write; tests run only against `db_capstone_test`.**
- **Session DS (auto-filter UX unified on Inventory / Stock In / Stock Out / Stock Ledger, 2026-10-04): full suite GREEN; UI/test only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **398 passed / 1898 assertions, 0 failures** (was 395/1873 at DR; +3 cases: `StockInTest::test_stock_in_filters_apply_automatically_without_an_apply_button`,
  `StockOutTest::test_stock_out_filters_apply_automatically_without_an_apply_button`,
  `AnalyticsTest::test_stock_ledger_filters_apply_automatically_without_an_apply_button`; the
  `InventoryTest` auto-filter test also asserts `data-auto-filter`). The behavior is centralized once in
  `resources/js/app.js` for every `form[data-auto-filter]`: search debounces 400 ms, select/date/number
  fields submit on `change`, Enter submits immediately, double-submit guarded. The four filters keep their
  page-specific fields (Inventory: search/variety/status/month/year; Stock In: search/supplier/type/dates;
  Stock Out: search/client/batch/dates; Stock Ledger: batch/variety/dates) and only **Clear Filters**
  remains. Controllers/routes/validation unchanged; `vendor\bin\pint --test` + `php artisan view:cache`
  clean; `npm run build` re-run. Read-only docs check: `_UPDATED` §9.2 (filtering support) and §14
  (consistent/refined UI) are satisfied; FR-09/FR-10 fields and ERD §3.7/§3.12/§3.14/§3.15 unchanged; no
  PRD/ERD edit. **No migration, schema, controller, route, permission-key or `db_capstone` change; tests
  run only against `db_capstone_test`.**
- **Session DR (Inventory filters auto-apply server-side, 2026-10-04): full suite GREEN; UI/test only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **395 passed / 1873 assertions, 0 failures** (was 394/1867 at DQ; +1 `InventoryTest` case —
  `test_inventory_filters_apply_automatically_without_an_apply_button`, asserting `#inventoryFilterForm` /
  `#filterSearch` / **Clear Filters** render and no funnel/Apply control exists). The search input
  debounces 400 ms; the scion variety/status/batch-month selects and the batch-year field submit on
  `change`; Enter submits immediately; `InventoryController@index` (server-side validation and the five
  filter-aware summary cards) is unchanged. `vendor\bin\pint --test` + `php artisan view:cache` clean.
  Read-only check of the docs: `_UPDATED` §2.7/FR-08 (values from actual records) and canonical PRD §9.2
  ("Tables should support filtering …") are satisfied; no PRD/ERD edit. **No migration, schema,
  controller, route, permission-key or `db_capstone` change; tests run only against `db_capstone_test`.**
- **Session DQ (Add Batch form system-assigned code/date, 2026-10-04): full suite GREEN; UI/controller/test only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **394 passed / 1867 assertions, 0 failures** (was 392/1855 at DP; `InventoryTest` +1 auto-assign case +
  2 updated, `BatchManagementTest` +2 — sequential code + `nextBatchCode()` ignoring sub-batch/custom
  codes — with the duplicate-code and future-date creation tests replaced). `vendor\bin\pint` + `php -l` +
  `php artisan view:cache` clean. `Batch::nextBatchCode()` (RULE 6) assigns the next sequential root code
  (`B001`, `B002`, …; sub-batch codes ignored) and `BatchController@store` stores `now()->toDateString()`;
  `StoreBatchRequest` validates `Batch::storeRules()` and ignores submitted `batch_code`/`grafted_date`.
  Read-only live check: `Batch::nextBatchCode()` = **B003** (live roots B001/B002). **No migration, schema,
  route, permission-key or `db_capstone` change; tests run only against `db_capstone_test`.**
- **Session DP (Inventory summary cards, 2026-10-03): full suite GREEN; UI/controller/test only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **392 passed / 1855 assertions, 0 failures** (was 389/1831 at DO; +3 tests: 2 `InventoryTest` card
  cases — live filtered values incl. archived exclusion + rendered titles/subtexts, and filter-awareness —
  and 1 `SeededFamilyKpiTest` seeded card check; the Inventory page test was updated).
  `vendor\bin\pint` + `php -l` + `php artisan view:cache` clean. `InventoryController@index` computes the
  five card values from the existing `$summaries` map (no new queries) and drops the old `totalGrafted`
  view variable; the page shows Total Mortality / Regraft Deficit / Total Regrafted / Total Active
  Inventory (active batches only) / Total Batches. Read-only live check on the owner's 5 batches matched
  independent queries exactly (123 / 123 / 270 / 853 / 5). **No migration, schema, route, permission-key
  or `db_capstone` change; tests run only against `db_capstone_test`.**
- **Session DN (Inventory table merged columns + "Regrafted" badge, 2026-10-03): full suite GREEN; UI/test only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **385 passed / 1797 assertions, 0 failures** (was 384/1789 at DM; +1 `InventoryTest` case: the merged
  BATCH DETAILS / TOTAL GRAFTED / REGRAFT cells render the correct inline dates through a real regraft
  flow, header assertion now `BATCH DETAILS` + `Date: Jul 15, 2026`; `NestedBatchesTest` inventory badge
  assertion → `Regrafted`). `vendor\bin\pint` + `php -l` + `php artisan view:cache` clean.
  `InventoryController@index` adds one eager `withMax('regraftRecordsAsParent as latest_regraft_date',
  'regraft_date')` (no N+1, exposed in `summaries`); no calculation, filter, sort, permission or action
  changed. **No migration, schema, route, permission-key or `db_capstone` change; tests run only against
  `db_capstone_test`.**
- **Session DM (Inventory row Mortality/Regraft modal forms, 2026-10-03): full suite GREEN; UI/test only.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **384 passed / 1789 assertions, 0 failures** (was 379/1744 at DL; +5 `InventoryTest` cases: modal
  exposure + form fields, modals hidden without `record_operations`, archived suppression, mortality +
  regraft validation from `/inventory` with the `mortality`/`regraft` error bags, and both success flows
  through the shared routes). `vendor\bin\pint` + `php -l` + `php artisan view:cache` clean. The row
  Mortality/Regraft buttons open the same forms as the operational pages; `InventoryController@index` now
  passes the Mortality dropdown data (`quantityBreakdown()`), the regraftable-batch dropdown data
  (`survivingQuantity()`) + active scion varieties, and a per-row `regraftable` flag. **No migration,
  schema, route, permission-key or `db_capstone` change; tests run only against `db_capstone_test`.**
- **Session CY (cross-batch-totals help note + live `db_capstone` reseed, 2026-10-01): full suite GREEN; reseed verified.**
  **(1) Help note (commit `b451b9c`):** a one-line note that the total counts each plant once (a
  regrafted plant is not added again under its new sub-batch) was added to the Stock Ledger nested
  summary totals (view/PDF/CSV), the Gap Report Expected total (view/PDF/CSV) and the Inventory Total
  Grafted card. Display/text only; no formula, view-logic or DB change. MySQL suite
  (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **344 passed / 1506 assertions, 0 failures** (was 344/1499; +7 assertions locking the note into the
  Stock Ledger view/CSV/PDF, the Gap Report page/CSV/PDF and the Inventory page). `vendor\bin\pint
  --test` + `php -l` clean; `php artisan view:cache` clean. HTTP-kernel smoke (seeded admin, read-only):
  all **20 sidebar pages 200**; the note renders on the Stock Ledger, Gap Report and Inventory pages and
  their PDF/CSV exports. **(2) `db_capstone` reseed (owner-approved destructive action, 2026-10-01):**
  verified backup `...\Temp\opencode\db_capstone_pre_reseed_20261001_201512.sql` (**37,218 B**,
  23 `CREATE TABLE`, 15 `INSERT INTO`, clean `-- Dump completed on 2026-10-01 20:15:13` trailer).
  **Before:** batches 6 (5 + manual B004), stock_outs 2, stock_out_details 2, activity_logs 13,
  `cacao:health` PASSED (6). Ran `php artisan migrate:fresh --seed --force` (exit 0, 53 migrations).
  **After:** batches **5** (B004 gone → it was manually added, not part of the standard seeder),
  stock_ins 4, stock_outs 1, stock_out_details 1, mortality_records 4, regraft_records 2, inventories 5,
  scion_varieties 5, users 1, activity_logs 11; `migrate:status` **0 Pending**; `cacao:health` PASSED
  (5); KPIs **Total Active Inventory 730 / Crude Mortality 7.33% (55/750) / Regraft Deficit 25**; the
  three cross-batch totals (Stock Ledger summary, Gap Report Expected, Inventory snapshot) all read
  **750**; all 20 sidebar pages 200; Stock Out hint == `survivingQuantity()` (B001 **375** vs raw 400).
  Tests still run only against `db_capstone_test`. **No further destructive action is authorized.**
- **Session CX (`cacao:health` rule 5 — `available_count` vs Stock Out history): full suite run and GREEN.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **344 passed / 1499 assertions, 0 failures** (was 343/1494; +1 `CacaoHealthTest` case: a legitimate
  Stock Out of 30 passes, then a raw DB edit of `available_count` to 80 — still inside the valid range
  but not matching the history — is caught). `vendor\bin\pint --test` + `php -l` clean. `php artisan
  cacao:health` on a freshly seeded `db_capstone_test` → `Cacao health check passed. 5 batch(es) satisfy
  all invariants.` (exit 0); SQL reconciliation on that seed: B001 400=400, B001-R1 50=50, B001-R1-R1
  30=30, B002 150=150, B003 100=100 (all match). **No schema/DB change; `db_capstone` untouched.**
  Committed `89905c9`. (Session CW was the prior green state at **343/1494**.)
- **Session CW (cross-batch `total_grafted` totals → `SUM(total_grafted - regraft)`): full suite run and GREEN.**
  MySQL suite **343 passed / 1494 assertions, 0 failures** (was 342/1484; +1 `SeededFamilyKpiTest`
  regression case). Changed `AnalyticsMetrics::batchSummary()['totals']['total_grafted']`,
  `AnalyticsMetrics::gapReport()['totalExpected']`, and `InventoryController@index` `totalGrafted` to
  `SUM(total_grafted - regraft)`; updated `AnalyticsTest` (gap 300→285) and `StockLedgerNestingTest`
  (880→750). Recompute on the fresh `db_capstone_test` seed: all three totals = **750** (raw = 880).
  Pint + `php -l` clean. **No schema/DB change; `db_capstone` untouched.** Committed `f9bbd1e`.
- **Session CV (`db_capstone` reseeded under the corrected `total_grafted` rule): verified GREEN.**
  The one owner-approved destructive action on the live DB ran after a verified backup
  (`...\Temp\opencode\db_capstone_pre_reseed_20260930_034319.sql`, **37,510 B**, 23 `CREATE TABLE`
  matching all 23 live tables, clean `Dump completed` trailer). **Before:** `cacao:health`
  **FAILED (exit 1)** — B001 expected 300 actual 400; B001-R1 expected 40 actual 50. **After
  `migrate:fresh --seed --force`:** `migrate:status` 53 Ran / **0 Pending**; `cacao:health`
  **PASSED (exit 0)**; seeded family B001 500/400/100 · B001-R1 100/50/30 · B001-R1-R1 30/30/0 ·
  B002 150/150/0 · B003 100/100/0 (Σtotal_grafted 880 / live 750 / available 730 / regraft 130;
  0 violations). KPIs: Active Inventory **730**, Crude Mortality Rate **7.33%** (55/750), Regraft
  Deficit **25**, Gap total **55**. End-to-end read-only HTTP kernel: all **20 sidebar pages 200**;
  Stock Out hint == `survivingQuantity()` (B001 375 vs raw 400); Stock Ledger CSV 200 (`text/csv`).
  **No code changed.** Tests still run only against `db_capstone_test` (not re-run this session; last
  green **342/1484**, Session CU).
- **Session CU (Stock Out form hint → `survivingQuantity()`): full suite run and GREEN.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **342 passed / 1484 assertions, 0 failures** (was 341/1478; +1 `StockOutTest` view case asserting
  the rendered hint equals `survivingQuantity()` for a mortality-bearing batch, raw 500 vs surviving
  100, and is not `data-available="500"`). `vendor\bin\pint --test` clean on the changed file;
  `php -l` clean; `php artisan view:cache` clean. **No migration; no schema/DB change** — the view
  reuses the existing centralized `Batch::survivingQuantity()` cap; no formula was duplicated. **No
  `db_capstone` read or write.** Committed `13e658e`. (Session CT — the surviving-cap gate + the
  read-only `cacao:health` command + tests — was the prior green state at **341/1478**; its detail is
  in the top banner and its own commits `dccd154`/`7ec2f8c`.)
- **Session CQ (Stock Out per-line Unit dropdown → `pots` only): full suite run and GREEN.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **338 passed / 1468 assertions, 0 failures** (was 337/1465; +1 `StockOutTest` case asserting the
  dropdown offers only Pots). `StockOutTest|StockValidationTest|RegraftFlowTest` alone: **45 passed /
  215 assertions**. `vendor\bin\pint --test` clean on every changed file; `php -l` clean;
  `php artisan view:cache` clean. **No migration; no schema/DB change** — the accepted-unit list is
  centralized in `StockOutDetail::UNITS = ['pots']` (RULE 6) and consumed by the form select and both
  validators; legacy live rows keep their historical `seedlings` value. **No `db_capstone` read or
  write.** Session CQ's change is **not committed yet** (prior checkpoint `4006b41`; Sessions CL + CM
  + CN + CO + CP also still uncommitted).
- **Session CN (Stock In log labels each receiving Seedlings vs Scion — Type column + filter): full suite run and GREEN.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **337 passed / 1465 assertions, 0 failures** (was 335/1454; +2 `StockInTest` cases: the derived
  type label and the type filter). `StockInTest` alone: **22 passed / 90 assertions**. `vendor\bin\pint`
  clean on the changed files; `php -l` clean. **No migration; no schema/DB change** — the label is
  derived from `StockIn::isScionEntry()` through the centralized `StockIn::entryType()` /
  `entryTypeLabel()` (RULE 6); the `/inventory/stock-in` table gains a **TYPE** column (Seedlings/Scion
  badges), a **Type** filter (All Types / Seedlings / Scion) and a Type row in the details modal.
  **No `db_capstone` read or write.** Session CN's change is **not committed yet** (prior checkpoint
  `4006b41`; Sessions CL + CM also still uncommitted).
- **Session CM (Root-Cause Matrix collapses `Others:*` into one `Others` cause): full suite run and GREEN.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **335 passed / 1454 assertions, 0 failures** (was 332/1437; +3 cases: 2 `AnalyticsTest` for the page
  table + PDF/CSV roll-up, 1 `MortalityTest` for `normalizeCause()`). `AnalyticsTest|MortalityTest`
  alone: **67 passed / 359 assertions**. `vendor\bin\pint` clean on every changed file; `php -l` clean.
  **No migration; no schema/DB change** — grouping is centralized in `MortalityRecord::normalizeCause()`
  (RULE 6) and applied by `AnalyticsMetrics::mortalityMatrix()` (page + PDF + CSV) and
  `mortalityMatrixRegressions()` (baseline keys). **No `db_capstone` read or write.** Session CM's change
  is **not committed yet** (prior checkpoint `4006b41`; Session CL also still uncommitted).
- **Session CL (Mortality cause-of-death dropdown + "Others" description): full suite run and GREEN.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **332 passed / 1437 assertions, 0 failures** (was 329/1427; +3 `MortalityTest` cases). `MortalityTest`
  alone: **13 passed / 43 assertions**. `vendor\bin\pint` clean on every changed file; `php -l` clean.
  **No migration; no schema/DB change** — the fixed choices live in `MortalityRecord::CAUSES` (RULE 6)
  and the "Others" description is folded into the single `cause` column as `Others: <description>`.
  **No `db_capstone` read or write.** Session CL's change is **not committed yet** (prior checkpoint
  `4006b41`).
- **Session CH (Phase 7D — live `db_capstone` upgrade + reseed): EXECUTED and verified.** The one
  approved destructive action was run against connection `mysql` / database `db_capstone` after a
  fresh verified backup (`...\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql`; 35,131 B /
  23 tables): `php artisan migrate:fresh --seed --force`. All 52 migrations ran (the four
  `2026_09_27_*` are now applied) and the seeders ran cleanly. Live checks: `migrate:status` **0
  Pending**; `Schema::hasColumn` — `batches.viable_count` / `stock_ins.viable` / `inventories.viable`
  **false**, `batches.regraft` / `batches.parent_batch_id` / `replanting_records.parent_batch_id`
  **true**; invariants (available > total_grafted **0**, available < 0 **0**, regraft-counter
  reconciliation **0** mismatches, B001 family total_grafted **500**); all **20 sidebar GET pages
  return 200** as the seeded administrator; KPIs Active Inventory **730** / Crude Mortality **7.33%**
  / Regraft Deficit **25** / Gap **55**; Stock Ledger CSV (`/analytics/stock-ledger/export/csv`)
  **200**. **No code change** (docs-only) and **no data created in `db_capstone`** during
  verification. Tests remain pointed only at `db_capstone_test`.
- **Session CG (Phase 7C: nesting + Stock Ledger / analytics alignment): full suite run and GREEN.**
  MySQL suite (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **328 passed / 1406 assertions, 0 failures** (was 299/1278; +5 `BatchTreeTest`, +4
  `NestedBatchesTest`, +9 `StockLedgerNestingTest`, +6 `HistoricalNestingTest`, +5
  `SeededFamilyKpiTest`). `vendor\bin\pint --test` **passed** (whole repo); `php -l` clean on every
  changed PHP file; `php artisan view:cache` clean; `migrate:status` **0 Pending** on
  `db_capstone_test`. Seeder (`migrate:fresh --seed`) clean; the nested regraft (B001-R1 → B001-R1-R1,
  30, Criollo) is seeded **only** through `RegraftBatch`. Seeded invariant queries (tinker, literal):
  SUM `available_count` **730** == Total Active Inventory; SUM `total_grafted` **750** == Crude
  Mortality Rate denominator; B001 family total_grafted **500** conserved; `Σ batches.regraft` **130**
  == `Σ replanting_records.quantity` **130**; ledger `seedlings` **800** (vs **1000** all Stock In),
  `grafted` **650**, `ready_to_plant` **730**, `mortality` **55**, `regraft` **130**, `pullout` **20**.
  Stock Ledger CSV (`GET /analytics/stock-ledger/export/csv`, HTTP 200) contains the footnote and the
  nested batch-summary rows. **`db_capstone` was NOT touched at the time**: its `2026_09_27_*`
  migrations then remained **Pending** (no migration was added or run; schema is final) —
  **superseded:** the live DB was upgraded + reseeded in Phase 7D, 2026-09-29. Committed `7c84843` (BatchTree +
  Batches/Inventory), `59488db` (Ledger/analytics + exports), `cc89669` (seeder + tests), `b780873`
  (docs).
- **Session CF (Phase 7B regraft / sub-batch flow): full suite run and GREEN.** MySQL suite
  (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`) **299 passed /
  1278 assertions, 0 failures** (was 280/1173; new `RegraftFlowTest` plus the reworked
  `ReplantingTest` and updated `BatchRelationshipsTest`/`CumulativeEventRecordsTest`/`AnalyticsTest`/
  `InventoryTest`). `vendor\bin\pint --test` **passed** (whole repo); `php -l` clean on every changed
  PHP file; `migrate:status` **0 Pending** on `db_capstone_test`. Seeders (`migrate:fresh --seed`,
  then a re-run `db:seed`) completed cleanly and **idempotently** against `db_capstone_test`; the
  regraft scenario is seeded **only** through `RegraftBatch` (B001 Batangas 500 / mortality 25 →
  regraft 100 into B001-R1 using Toledo → mortality 5 on B001-R1 → Stock Out 20 from B001-R1). Seeded
  invariant queries (tinker, literal output): `available_count > total_grafted` **0**,
  `available_count < 0` **0**, rows B001 400/400/regraft 100/mortality 25, B001-R1 100/80/regraft 0/
  mortality 5 (parent B001); family totals conserve (`Σ` 400 + 100 = original 500);
  `Σ batches.regraft` **100** == `Σ replanting_records.quantity WHERE parent_batch_id IS NOT NULL`
  **100**; per-source reconciliation (`counter == totalRegraftedFromBatch`) all OK. A repo grep finds
  **no** `remainingReplantingCapacity` and **no** old deficit-cap validation in `app/` (only the
  unchanged `replantingDeficit()` formula and the test that asserts the phrase is gone). **`db_capstone`
  was NOT touched at the time**: its `2026_09_27_*` migrations then remained **Pending** and it still
  had `batches.viable_count` / `stock_ins.viable` (no destructive command run) — **superseded:** the
  live DB was upgraded + reseeded in Phase 7D, 2026-09-29. Committed as
  `0f8195a` (action + models), `a82c513` (controller/request/views), `e266412` (seeders + tests).
- **Session CC (`Scion_Receiving` merged into `Stock_In`): full suite run and GREEN.** MySQL suite
  (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`) **278 passed /
  1163 assertions, 0 failures** (was 280/1170: the 7-test `ScionReceivingTest` was deleted; 4 new
  scion-entry tests were added to `StockInTest` plus a merge guard in `StructuralIndependenceTest`, and
  `CumulativeEventRecordsTest` was repointed at a scion `StockIn`). `vendor\bin\pint --test`
  **passed** (whole repo); `php -l` clean; `php artisan view:cache` clean; `php artisan route:list` =
  **69 routes** (the 2 scion-receiving routes removed). `migrate:status` **0 Pending** on both
  `db_capstone_test` and `db_capstone`; the two new migrations were validated **up→down→up** on
  `db_capstone_test`. **Live `db_capstone` migrated after a fresh verified backup**
  (`...\Temp\opencode\db_capstone_backup_cc_20260926_170736.sql`, 36,778 B / 24 tables):
  `scion_receivings` 2 rows → 2 `stock_ins` scion rows (`stock_ins` 2 → 4; `viable == quantity`), table
  dropped. (The first live attempt aborted on the `users.id` key lookup **before** any insert — no data
  was lost; the migration now detects the key and re-ran clean.) A repo-wide grep finds **no runtime
  `ScionReceiving` reference** — only the historical create migration, the new merge migrations,
  explanatory comments/docs and the structural merge guard. **Committed `52a9f67` (feature) +
  `f766ce8` (docs sync incl. `_REVISED` ERD).**
- **Session CB (Scion Receiving "Add" CRUD UI): full suite run and GREEN.** MySQL suite
  (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **280 passed / 1170 assertions, 0 failures** (unchanged count — this session added **no** permanent
  test file; a throwaway feature test was written, run, and deleted). The throwaway test verified: GET
  `/operational/scion-receivings` **200** with the empty state; POST valid data creates a
  `scion_receivings` row + flashes "Scion receiving recorded successfully."; POST missing fields
  returns the **specific** per-field messages in the `scion_receiving` bag (`Please select a scion
  variety.`, `The quantity received is required.`, `The date received is required.`); quantity `0` →
  `Quantity must be greater than zero.`; a user without `manage_inventory` gets **403**.
  `vendor\bin\pint --test` **passed**; `php -l` clean on both new PHP files + `routes/web.php`;
  `php artisan route:list` boots with **71 routes** (the 2 new scion-receiving routes) and the expected
  `permission:manage_inventory` middleware. `git diff` scope check: only the 3 new files plus
  `routes/web.php` (2 routes) and `resources/views/layouts/sidebar.blade.php` (1 link) changed — no
  other controller/route/view touched. **No migration run; no schema/data change. Committed `6cd5d75`.**
- **Session BZ (P6 seeders/tests/docs/verify): full suite run and GREEN.** MySQL suite
  (`$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`)
  **280 passed / 1170 assertions, 0 failures**. `vendor\bin\pint` clean (one import tidy-up on
  `StockInTest`); `php -l` clean across 56 changed/new PHP files; `migrate:status` **0 Pending**;
  seeders run cleanly and re-run idempotently against `db_capstone_test` (B001 mortality 25 /
  regraft 15, B002 available 120 after the seeded 20-unit release, gap total 50, no double-debit).
  No `Variety`/`PotType`/`BatchMonitoring`/`StockReversal`/generic `Scion`/`MortalityAdjustment`/
  `RegraftRecord`/`regraft_records` reference remains in `tests/` (only the intentional structural
  regression guard and current UI labels such as "Inventory by Scion Variety"). **No destructive
  action against `db_capstone`; no schema change.**
- **Session BY (handoff refresh + ERD terminology; doc-only): no test suite run** (P6). Edited
  `docs/CacaoTrack_ERD.md` + `docs/CacaoTrack_ERD_REVISED.md` only: §7 sidebar `Replanting` → `Regraft`
  and §8 Design Principle `Mortality and Replanting` → `Mortality and Regraft`. §3.11 / entity names
  (`Replanting_Record`, `Replanting_ID`, `Replanting_Date`) deliberately unchanged. Both files remain
  byte-identical (SHA-256 `91C6F7E9…48B2F6`). Committed `9bd3c8a` (2 files, +4/−4). **No
  code/DB/migration change; no live DB read.**
- **Session BX (`regraft_records` consolidation APPLIED): no test suite run** (deferred to P6 — the
  seeders/tests still target the OLD model). **Verified with literal command output (not summarized):**
  `php -l` clean on the changed files and `vendor\bin\pint` **passed**; `php artisan migrate --force`
  applied `2026_09_26_000002_drop_regraft_records_table` (`DONE`); `php artisan migrate:status` shows
  **0 Pending** (both `2026_09_25_000009` and `2026_09_26_000002` `Ran`); a live tinker read confirms
  `Schema::hasTable('regraft_records')` = **false**, `replanting_records` = **13 rows / 195 quantity**,
  and `AnalyticsMetrics::stockLedgerGrid()` now returns grand Re Graft **195** (Criollo 35 / Trinitario
  15 / Forastero 15 / Diaman 110 / Batangas 10 / White Flag 10) instead of 0. A repo-wide grep for
  `regraft_records|RegraftRecord|regraftRecords` returns **no live code references** (only the
  intentionally-kept create migration `2026_09_25_000009`, the new drop migration, and the retraction
  notes in the ERD/`AGENTS.md`/this handoff). **Schema changed** (`regraft_records` dropped; DB back to
  **24 tables**); **0 data rows affected**. Committed **`22ef695`** (5 files changed, +60/−80).
- **Session BW (docs sync): no test suite run** (docs-only; the suite still targets the OLD model —
  P6). Verified read-only against `db_capstone`: `Schema::hasTable('regraft_records')` = **true**,
  **0 rows**; `replanting_records` = **13 rows**; `migrate:status` shows `2026_09_25_000009` +
  `2026_09_26_000001` `Ran` (**0 Pending**). Confirmed **no drop migration exists** and that
  `app/Models/RegraftRecord.php`, `Batch::regraftRecords()` and `AnalyticsMetrics::stockLedgerGrid()`
  still reference the empty table. **No code, migration, model or DB change.** Superseded by Session BX.
- **Session BV (`Scion_Receiving` schema + model): no test suite run** (deferred to P6 — the
  seeders/tests still target the OLD model). `php artisan migrate --force` applied
  `2026_09_26_000001_create_scion_receivings_table`; **`migrate:status` 0 Pending**; `php artisan
  db:table scion_receivings` confirms the 8 columns (nullable `supplier_id`/`unit_price`/`origin`/
  `notes`) and both FKs `on delete restrict`; **`vendor\bin\pint` passed**; `php -l` clean on both new
  files; a tinker check confirms the model loads, `rules()` returns all 7 keys, and `scionVariety()` /
  `supplier()` exist. **Schema changed** (`scion_receivings` created; DB now **25 tables**); **no
  operational data change**. Committed as its own checkpoint **`33ff6b0`** (4 files). Flags: no
  controller/route/view; freshness/viability NOT implemented; `AGENTS.md`'s schema-exception line not
  yet updated.
- **Session BT (Regraft display rename): no test suite run** (deferred to P6 — the seeders/tests are
  still the OLD model). `php artisan view:cache` clean; `vendor\bin\pint` passed; Dashboard KPIs
  verified identical before/after (1130 / 40.00% / 385). **No migration run; no schema/data change.**
  Committed `bd47433`; the two label-assertion tests (`DashboardTest:70`, `AnalyticsTest:167,192`)
  were updated to the new text.
- **Session BU (consolidation audit): read-only — no test suite run.** `db_capstone` was read via
  `php artisan tinker` only; **no code/migration/model/DB change**. Confirmed: no drop migration
  exists (only `2026_09_25_000009_create_regraft_records_table`, `Ran`, 0 Pending); the Stock Ledger's
  Regraft column still reads `RegraftRecord`/`regraft_records` (`AnalyticsMetrics.php:578-605`); live
  `regraft_records` = 0 rows vs `replanting_records` = 13 rows; `app/Models/RegraftRecord.php` and
  `Batch::regraftRecords()` still present.
- **Session BS (Gap formula corrected): no test suite run** (deferred to P6 —
  `tests/Feature/AnalyticsTest.php` still asserts the old gap values and is part of the old-model
  suite). **`php artisan view:cache` clean; `vendor\bin\pint` passed on all changed PHP files;
  `php -l` clean.** Live `db_capstone` read read-only and **freshly queried against the corrected
  formula**: all 9 batch gaps = B001 125, B002 20, B003 45, B004 300, B005 50, B006 30, B007 0,
  TAB007 10, TAB008 0 → `totalGap = 580` = `SUM(mortality_records.quantity)`; the two zero batches
  genuinely have 0 mortality rows. The FPDF renders (`%PDF-1.3`, 10,530 bytes) with the new
  "Gap = effective mortality" note. **No migration run; no schema/data change.** Committed `1744975`
  (the existing P5 commit `d139286` was not amended). Remaining flags: "Ready to Plant" mapping
  (open) and the `regraft_records` CRUD UI (open).
- **Session BR (live DB data audit): investigation only — no test suite run.** `db_capstone` was read
  read-only via `php artisan tinker`; **no code/migration/seeder/test/DB change**. Confirmed all 9
  batches and all 7 stock-outs are pre-refactor residue; `SUM(viable_count) = 1170`,
  `SUM(available_count) = 1130` (old-ledger backfill), `SUM(stock_out_details.quantity) = 2130`; the
  current Stock Out validation (`StockOutController::lockAndValidateBatches` `:180/:194/:215`,
  decrement `:138`; `StoreStockOutRequest` has no availability rule) cannot exceed the locked
  `available_count`, so this data was written by the OLD model. The complete `available_count` write
  list and the no-increment/restore proof are in §2 Session BR.
- **Session BQ (P5 Analytics/reports implemented): no test suite run** (deferred to P6 — the
  seeders/tests still target the OLD model). **`php -l` clean**; **`php artisan view:cache` clean**;
  **`vendor\bin\pint` passed** on all changed files; migration `2026_09_25_000009` applied
  (`migrate:status` 0 pending). A runtime smoke test (auth as Administrator, request dispatched
  through the HTTP kernel) returned **200** for `/` (Dashboard — no longer 500s), `/analytics`,
  `/analytics/stock-ledger`, `/analytics/mortality-matrix`, `/analytics/historical/seasonality`,
  `/analytics/historical/age-profile`, `/analytics/historical/gap-report` and all seven PDF/CSV
  exports. The Stock Ledger response contains the monthly grid markers (`September 2026`, `Seedlings`,
  `Re Graft`, `Ready to Plant`, `Pullout`, `GRAND TOTAL`). **Schema changed:** `regraft_records`
  created (the one approved P5 migration); `db_capstone` operational rows are unchanged. Flags: the
  locked Gap formula resolved to **0** at the time (**corrected in Session BS**); "Ready to Plant" is a
  best-guess mapping.
- **Session BP (housekeeping): no test suite run** — docs/git only (P4 commit `0803bdf` + the
  `AGENTS.md` banner commit `5a2a1ae`); **no code, migration, model, view, seeder or test file
  touched** and **no migration run / `db_capstone` unchanged**. Working tree is clean after the
  commits.
- **Session BO (P4 Views/sidebar implemented): no test suite run** (deferred to P6 — the seeders/tests
  are still the OLD model). **`php artisan view:cache` clean** on all views; **`vendor\bin\pint
  --test` passed** on the changed controllers; a runtime smoke test (Administrator, request
  dispatched through the HTTP kernel) returned **200** for every P4 page in scope
  (`operational.checklist`, `operational.mortality`, `operational.replanting`, `operational.batches`,
  `operational.scion-varieties`, `inventory.index`, `inventory.stock-in`, `inventory.stock-out`,
  `inventory.suppliers`, `inventory.clients`, `activity.index`, `users.index`, `profile.index`); every
  `route('...')` used in Blade resolves. **No migration was run and `db_capstone` is unchanged.**
  **P4 follow-up flags CLOSED:** `/activity` now returns 200 (`ActivityController@index` uses
  `batch.scionVariety`); the `UserController` delete guard no longer queries the dropped
  `stock_reversals`/`mortality_adjustments` tables (reflection-verified). **Remaining:** `/`
  (Dashboard) still 500s (`AnalyticsMetrics` is OLD model — P5). **No migration, model, seeder or
  test file touched; three controller edits: the approved one-line `BatchController@index` view path
  plus the two flag fixes above.**
- **Session BN (P3 Controllers/routes implemented): no test suite run** — checkpoint `3d3f9f9`
  reworks the controller/route layer onto the P2 models. The views/sidebar/seeders/tests/
  `AnalyticsMetrics` are still the OLD model, so the feature suite is expected to fail until P6
  rewrites it. **`php -l` clean** on all changed/new files; **`vendor\bin\pint --test` passed**;
  `php artisan route:list` boots with **66 routes** and the expected `permission:*` middleware (no
  new permission key). **No migration was run and `db_capstone` is unchanged.** P3 flags: inventory
  snapshot uniqueness is app-layer only (no DB unique index); inventory snapshots write no
  Activity_Log row (no `inventory_recorded` type; model edit out of scope); duplicate submissions
  guarded by a session fingerprint (5s). **No migration, model, view, seeder or test file touched.**
- **Session BM (P2 Models implemented): no test suite run** — the models now match the P1 schema but
  the controllers/routes/views/seeders/tests/`AnalyticsMetrics` are still the OLD model, so the suite
  is expected to fail until P6 rewrites it (the normal end-of-session test protocol is deferred to
  P6). **`php -l` clean** on all changed/created models; **`vendor\bin\pint` passed** on the changed
  files; `composer dump-autoload` regenerated. A rollback-protected live check on `db_capstone`
  confirmed the deleted classes are gone, `Batch::totalAvailableQuantity()` = **1130**, and the four
  locked model-layer rules behave (mortality cap, frozen Inventory status, Stock Out scion-variety
  derivation, `viable <= quantity`). **No migration was run and `db_capstone` is unchanged.**
- **Session BL (P3 Controllers/routes plan + P2 dependency check): planning only — no test suite
  run.** No code, no migration and no DB change; the workspace was read read-only and the P3 plan was
  validated against the canonical PRD/ERD + the Session BK P2 design. **Confirmed the P2 model code is
  still NOT implemented** (`app/Models` still has Variety/Scion/PotType/BatchMonitoring/
  MortalityAdjustment/StockReversal and the old Batch/StockIn/StockOut/Inventory; no `ScionVariety`/
  `StockOutDetail`), so P2 must land before P3. `migrate:status` remains **0 pending** (no migration
  run).
- **Session BK (P2 Models plan): planning only — no test suite run.** No code, no migration and no
  DB change; the app models/tests are still the OLD model, so the suite remains expected to fail
  until P6 rewrites it (deferred). The P2 design was validated read-only against the live P1 schema
  (`Schema::getColumnListing`) — the columns match the revised ERD exactly. `migrate:status` remains
  **0 pending** (no migration run).
- **Session BJ (P1 migrations): no test suite run** — the DB schema changed but the app models/tests
  still target the OLD schema, so the suite is expected to fail until P6 rewrites it (the normal
  end-of-session test protocol is deferred to P6). **Pint clean** on the 8 new migrations;
  `migrate:status` **0 pending**; up→down→up verified on a scratch copy. Live schema spot-checks:
  23 tables; removed tables absent; `scion_varieties` 8; `stock_out_details` 7;
  `stock_ins.viable = quantity` (0 mismatches); 0 null `batches.scion_variety_id`;
  `SUM(batches.available_count)` = **1130**.
- Test suite: **324 passed, 1329 assertions** (Session BI — Phase 0 docs-only; re-verified with
  **no code change**, matching the Session BG baseline. Session BG — Batch Monitoring is now read-only
  (alive plants + Pending/Complete) and Mortality Tracking deducts the batch's alive count;
  `BatchMonitoringTest` is 9 tests (down from 17 after removing the observation/survival tests).
  Before that, **332 passed, 1353 assertions** (Session BF — Batch Monitoring status simplified to
  two states (Pending/Complete) + alive-plant/survival-rate display; `BatchMonitoringTest` is 17
  tests. Before that, re-verified unchanged in Session BE — verification
  only, no code changes since Session BD; **332/1348** in Session BD — Batch Monitoring status logic;
  the refined decision tree added 2 tests, so `BatchMonitoringTest` is now 17 tests; re-verified
  **330/1344** in Session BC before it; **315/1288** in Sessions BA/BB before that —
  the ax scion-card work was replaced by the scion stock pool: 3 retired StockIn `scion_quantity`
  tests removed + `ScionTest` rewritten;
  **326/1329** in Sessions AX/AY/AZ before it; **319/1303** in Session AW before the
  Scion scion-quantity work; **316/1293** earlier in Session AW after the
  banner but before the warning toast; **309/1265** in Session AU before the mortality-increase alert;
  **324/1319** in Session AT before the
  roles merge; previously 317/1291 in Sessions AR/AS, 312/1249 in Sessions AQ/AP,
  309/1210 in Sessions AO/AN,
  303/1169 in Session AM,
  299/1146 in Session AL,
  296/1125 in Session AK,
  292/1100 in Session AJ,
  277/1006 in Session AI/AH,
  259/939 in Sessions AF–AG,
  280/1002 in Session AE,
  272/970 in Session AC,
  247/896 in Sessions AA–AB;
  240/855 in Session Z; 221/776 in Sessions X–Y; 205/724 in Sessions U–W; 192/669 in Session T;
  176/621 in Sessions P–S; 23 checklist + 11 mortality + 11 replanting + 12 inventory + 15 stock-in +
  17 stock-out + 16 stock-reversal + 13 supplier + 13 client + 14 variety + 14 scion + 14 pot-type +
  16 analytics + 16 activity-log + 13 profile + 19 users + 19 batch + 7 dashboard +
  **4 toast** + 2 example, `vendor\bin\pint` clean). Session AT grew `DailyChecklistTest` to 23
  tests. Session AU removed the 18 `RoleManagementTest` tests (the Roles feature was merged into
  Users) and rewrote `UserManagementTest` for `role`/`permissions`. Session AH had added the 18 role
  tests. Session AF removed the 17 notification + 8
  settings tests and added 4 toast tests. Re-verified in Session AC
  (`NotificationTest` 17 passed, `SettingsTest` 8 passed; full suite 272/970, Pint clean,
  `view:cache` clean; both Session AC migrations applied to `db_capstone`). Re-verified unchanged in
  Session V (205/724, Pint clean — no code changes since Session U). Re-verified unchanged in
  Session W (`ProfileTest` 13/55; full suite 205/724, Pint clean — no code changes since Session U).
  Re-verified unchanged in Session Y (221/776, Pint clean — no code changes since Session X).
  Re-verified in Session Z (`BatchManagementTest` 19/79; full suite 240/855, Pint clean).
  Re-verified in Session AA (`DashboardTest` 7/40; `ExampleTest` updated; full suite 247/896,
  Pint clean, `view:cache` clean).
  Re-verified unchanged in Session AB (full suite 247/896, Pint clean — no code changes since
  Session AA; live `db_capstone` counts re-read with no drift).
  Re-verified unchanged in Session AD (full suite 272/970, Pint clean — no code changes since
  Session AC; 70 routes; all migrations Ran; live `db_capstone` counts re-read with no drift).
  Re-verified in Session AE (`DailyChecklistTest` 16/51; full suite 280/1002, Pint clean,
  `view:cache` clean; 72 routes; migration `2026_09_16_000003` applied to `db_capstone` with no
  data change).
  Re-verified in Session AF (`ToastTest` 4 passed; full suite 259/939, Pint clean, `view:cache`
  clean; no `/notifications`/`/settings` routes; migration `2026_09_16_000004` applied to
  `db_capstone` — `notifications` table and `users.settings` dropped, no data change).
  Re-verified unchanged in Session AG (full suite **259/939**, Pint clean — no code changes since
  Session AF; `UserManagementTest` re-run 16/52; live `db_capstone` confirmed `users.role` present
  and no `roles` table; the roles-table request was declined to keep the ERD at 18 entities).
  *(Superseded by Session AH, which approved the ERD revision.)*
  Re-verified in Session AH (full suite **277/1006**, Pint clean, `view:cache` clean;
  `RoleManagementTest` 18 passed (incl. the inline **+ New Role** quick-add); `UserManagementTest`
  16 and `ProfileTest` 13 updated; live
  `db_capstone`: 3/3 users mapped to a valid `role_id` (0 null, 0 mismatches), roles Administrator 7 /
  Manager 6 / Staff 6, legacy `users.role` dropped, admin sees the Roles tab, Staff gets 403 on role
  management).
  Re-verified in Session AI (full suite **277/1006**, Pint clean; the redesigned FPDF rendered a
  real `db_capstone` PDF for 2026-09-16 with no errors — `%PDF-1.3`, real task names/summary
  present; `DailyChecklistTest` 16/16; **no migration run, `db_capstone` unchanged**).
  Built in Session AJ (full suite **292/1100**, Pint clean, `view:cache` clean; migration
  `2026_09_17_000001_add_archived_at_to_master_data_tables` applied to `db_capstone` — six nullable
  `archived_at` columns, **no data change**; `route:list` shows the 12 archive/restore routes and no
  master `DELETE` routes; the two delete tests per Management page were replaced by archive/restore
  tests).
  Built in Session AK (full suite **296/1125**, Pint clean, `view:cache` clean; `AnalyticsTest` 20/108;
  added `GET /analytics/stock-ledger/export` → `AnalyticsController@exportPdf` +
  `App\Support\StockLedgerPdf`;   the Print Ledger action renders the real filtered ledger + totals
  strip; **no migration run, `db_capstone` unchanged**).
  Updated in Session AL (full suite **299/1146**, Pint clean; `AnalyticsTest` 23/129; the
  Mortality Root-Cause Matrix now returns and displays the Raw → Adjustments → Effective chain from
  the same `MortalityRecord::effectiveQuantity()` definition the Mortality module uses; live
  `db_capstone` cross-check raw 50 / adj −5 / effective 45 = module 45; **no migration run,
  `db_capstone` unchanged**).
  Updated in Session AM (full suite **303/1169**, Pint clean, `view:cache` clean; `AnalyticsTest`
  27/152; the Stock Ledger and Root-Cause Matrix pages now render a type-scoped **Generated
  Reports** panel + View modal of the stored `result_summary`; `summaryFor(TYPE_STOCK_LEDGER)` fixed
  to real ledger totals via the new `stockLedgerSummary()`; live read-only `db_capstone` snapshot =
  `{batches:4, grafted:800, viable:765, available:605, mortality:45, replanting:34}`; **no migration
  run, `db_capstone` unchanged**).
  Built in Session AN (full suite **309/1210**, Pint clean, `view:cache` clean; `AnalyticsTest`
  33/193; `App\Support\MortalityMatrixPdf` + `GET /analytics/mortality-matrix/export` +
  `/export/csv` under `view_analytics`; the page has **Print Matrix** (modal) + **Export CSV**;
  live read-only `db_capstone` PDF render = `%PDF-1.3`, 5,775 bytes, causes
  `["Disease","Drought","Pest"]`, raw 50 / adj −5 / effective 45; **no migration run,
  `db_capstone` unchanged**).
  Re-verified unchanged in Session AO (full suite **309/1210**, `vendor\bin\pint --test` clean,
  **80 routes**, `migrate:status` 0 pending, no code or DB change; live `db_capstone` re-read —
  `analytics` 9, `activity_logs` 27, active inventory 605, effective mortality 45, replanting
  deficit 11; `AnalyticsTest` 33/193).
  Built in Session AP (full suite **312/1249**, Pint clean, `view:cache` clean; `AnalyticsTest`
  36/232; `App\Support\OperationalAnalyticsPdf` + `GET /analytics/export` + `/analytics/export/csv`
  under `view_analytics`; `/analytics` now has **Print Analytics** (modal) + **Export CSV**; live
  read-only `db_capstone` PDF render = `%PDF-1.3`, 8,477 bytes, inventory 605 / rate 5.63 /
  deficit 11 / surviving 720 / trend 45; **no migration run, `db_capstone` unchanged**).
  Re-verified unchanged in Session AQ (full suite **312/1249**, `vendor\bin\pint --test` clean,
  **82 routes**, `migrate:status` 0 pending, no code or DB change; live `db_capstone` re-read —
  `analytics` 9, `activity_logs` 27, active inventory 605, effective mortality 45, replanting
  deficit 11, 0 archived rows; `AnalyticsTest` 36/232).
  Built in Session AR (full suite **317/1291**, Pint clean, `view:cache` clean; `AnalyticsTest`
  41/274; `App\Support\GapReportPdf` + `GET /analytics/historical/gap-report/export` +
  `/export/csv` under `view_analytics`; the page has **Print Report** (modal) + **Export CSV** +
  a **Generated Gap Reports** panel; live read-only `db_capstone` PDF render = `%PDF-1.3`, 6,284
  bytes, 4 rows, expected 765 / actual 605 / gap 180; **no migration run, `db_capstone` unchanged**).
  Re-verified unchanged in Session AS (full suite **317/1291**, `vendor\bin\pint --test` clean,
  **84 routes**, `migrate:status` 0 pending, no code or DB change; live `db_capstone` re-read —
  `users` **5** (+1 UI-created `manager`), `activity_logs` **28**, `analytics` 9, active inventory
  605, effective mortality 45, replanting deficit 11, gap 180, 0 archived rows; `AnalyticsTest`
  41/274).
  Built in Session AT (full suite **324 passed / 1319 assertions**, Pint clean, `view:cache` clean;
  `DailyChecklistTest` 23 tests; `DELETE /operational/checklist/tasks/{task}` guarded by
  `ActivityTask::canBeDeleted()` + Manager/Administrator authorization; **no migration run,
  `db_capstone` unchanged**).
  Built in Session AU (full suite **309 passed / 1265 assertions**, Pint clean, `view:cache` clean;
  **81 routes**; `migrate:status` 0 pending; migration
  `2026_09_17_000002_merge_roles_permissions_into_users_table` applied to `db_capstone` —
  `roles`/`role_permissions` dropped, `users.role` + `users.permissions` added and backfilled;
  `RoleManagementTest` removed, `UserManagementTest` rewritten).
  Re-verified unchanged in Session AV (full suite **309/1265**, Pint clean, **81 routes**,
  `migrate:status` 0 pending; live `db_capstone` identical to Session AU; documentation-only fixes
  to `AGENTS.md` + this handoff).
  Built in Session AW (full suite **319 passed / 1303 assertions**, `vendor\bin\pint --test` clean,
  `view:cache` clean, **81 routes**, `migrate:status` 0 pending; `AnalyticsTest` 51/312). The
  Mortality Root-Cause Matrix page now raises a warning banner + highlights offending cause/cell/
  total when a root cause's effective mortality increased vs the most recent matching generated
  matrix report, and raises a transient **warning toast** on page load for the same condition
  (`mortalityIncreaseToast()` → `session()->now('warning', …)` → `partials/toasts.blade.php`);
  `AnalyticsMetrics::mortalityMatrixSummary()` stores per-variety cells + filters and
  `AnalyticsMetrics::mortalityMatrixRegressions()` centralizes the comparison (RULE 6). **No
  migration run, no schema/data change, no ERD revision** (PRD §9/§13.2, ERD §9.4).
  Built in Session AX (full suite **326 passed / 1329 assertions**, `vendor\bin\pint --test` clean,
  `view:cache` clean, **81 routes**, `migrate:status` 0 pending; `ScionTest` 19, `StockInTest` 18).
  Migration `2026_09_21_000001_add_scion_quantity_to_stock_ins_table` applied to `db_capstone`
  (adds `stock_ins.scion_quantity`, default 0 — no existing data row changed). `/inventory/scions`
  now shows Total Available / Re-grafted / Rejected cards via `App\Support\ScionMetrics` (RULE 6);
  live read `{received:200, regrafted:140, rejected:455, available:200}`. **No new table; ERD
  revision §2.15/§9.9.**
  Built in Session AY (full suite **327 passed / 1337 assertions**, `vendor\bin\pint --test` clean,
  `view:cache` clean, **81 routes**, `migrate:status` 0 pending; `ScionTest` 21/82). Total Available
  Scions is now `max(0, received − re-grafted)` in `App\Support\ScionMetrics` (available moves from
  `received` to `received − regrafted`), so saving a replanting record deducts from it; the scions
  card sublabel + `ScionTest` (incl. a new replanting-deduction test) were updated. ERD §9.9 revised
  to record the reversal. **No migration run, no schema/data change, no new table, no new route.**
  (The AX live read above would now be `available:60` = `max(0, 200 − 140)`.)
  Re-verified unchanged in Session AZ (full suite **327/1337**, `vendor\bin\pint --test` clean,
  `view:cache` clean, **81 routes**, `migrate:status` 0 pending; no code, no migration, no DB
  change — the live `db_capstone` drift since Session AU is real UI usage, see the §3 Session AZ
  snapshot).
- Test command used (because SQLite is disabled):
  ```powershell
  $env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test
  ```
  A `db_capstone_test` MySQL database was created for this. `RefreshDatabase` wipes it each run —
  it is separate from `db_capstone`, so real data is safe. Never point tests at `db_capstone`.
- Session EE changed this: `phpunit.xml` now forces `DB_CONNECTION=mysql` /
  `DB_DATABASE=db_capstone_test` (`force="true"`), and `tests/TestCase.php` throws before
  `RefreshDatabase` can migrate if the database is not exactly `db_capstone_test`. Plain
  `php artisan test` is therefore safe (SQLite is no longer used). The explicit PowerShell override
  above remains the belt-and-braces command.
- The foundation tables (`varieties`, `scions`, `pot_types`, `batches`) have no dedicated model
  tests (they are pure schema + seed data) — but every Management page built on them now has a
  feature test (`BatchManagementTest` added in Session Z).

## 5a. Proposed live SQL — NOT EXECUTED (awaiting approval)

**Do not run these statements without fresh owner approval.** They were written against the
verified live shape (read-only): `db_capstone.users` has primary key `id` (not `user_id`),
`permissions` is a nullable JSON array, and the single live account is role `Administrator` with
`["manage_users","manage_inventory","manage_stock","record_operations","view_analytics","generate_reports","view_activity"]`.
The statements add only the new checklist keys and preserve every existing key.

```sql
-- NOT EXECUTED — awaiting approval
-- 1. Manager accounts: add manage_checklist only when missing.
UPDATE users
SET permissions = JSON_ARRAY_APPEND(COALESCE(permissions, JSON_ARRAY()), '$', 'manage_checklist')
WHERE role = 'Manager'
  AND JSON_VALID(COALESCE(permissions, JSON_ARRAY()))
  AND NOT JSON_CONTAINS(COALESCE(permissions, JSON_ARRAY()), JSON_QUOTE('manage_checklist'));

-- 2. Administrator accounts: add manage_checklist only when missing.
UPDATE users
SET permissions = JSON_ARRAY_APPEND(COALESCE(permissions, JSON_ARRAY()), '$', 'manage_checklist')
WHERE role = 'Administrator'
  AND JSON_VALID(COALESCE(permissions, JSON_ARRAY()))
  AND NOT JSON_CONTAINS(COALESCE(permissions, JSON_ARRAY()), JSON_QUOTE('manage_checklist'));

-- 3. Administrator accounts: add complete_checklist only when missing.
UPDATE users
SET permissions = JSON_ARRAY_APPEND(COALESCE(permissions, JSON_ARRAY()), '$', 'complete_checklist')
WHERE role = 'Administrator'
  AND JSON_VALID(COALESCE(permissions, JSON_ARRAY()))
  AND NOT JSON_CONTAINS(COALESCE(permissions, JSON_ARRAY()), JSON_QUOTE('complete_checklist'));

-- 4. OPTIONAL — only if an existing Staff account must complete checklist tasks.
UPDATE users
SET permissions = JSON_ARRAY_APPEND(COALESCE(permissions, JSON_ARRAY()), '$', 'complete_checklist')
WHERE role = 'Staff'
  AND JSON_VALID(COALESCE(permissions, JSON_ARRAY()))
  AND NOT JSON_CONTAINS(COALESCE(permissions, JSON_ARRAY()), JSON_QUOTE('complete_checklist'));
```

**Expected live effect if ever approved:** statement 1 → 0 rows (no Manager account exists);
statements 2–3 → 1 row (the Administrator gains `manage_checklist` and `complete_checklist`);
statement 4 → 0 rows (no Staff account exists). No other permission key and no other table is
touched. **Status: NOT EXECUTED — awaiting approval.** No live user permission was modified in
Session EE.

## 6. Open decisions / next steps
- **Latest sessions (DE–DZ) are captured in the top banner, "NEXT UP" and the "Quick start for the next
  session" section:** **DE** = Stock In/Out quick-add links removed (view-only); **DF** = mortality cap
  now `survivingQuantity()` + dropdown shows Original / Stock Out / Remaining; **DG** = branding
  (logo in login/sidebar, navbar profile dropdown removed); **DH** = show/hide password toggles +
  sidebar accordion/dead-space fix; **DI** = owner-approved `db_capstone` operational re-wipe, GitHub
  publish (`49351ce`), Dashboard KPI → **Total Grafted** = `AnalyticsMetrics::totalGrafted()`; **DJ** =
  **Inventory page rebuilt into the per-batch template** (BATCH DATE / BATCH / SCION VARIETY / TOTAL
  GRAFTED / REGRAFT / MORTALITY / DEFICIT / AVAILABLE / ACTION), **"Record Monthly Inventory" UI
  removed**; **DK** = the row read-only **Deficit** review modal (working definition, not yet
  client-confirmed); **DL** = the Inventory **Add Batch** modal (same FR-06 form as
  `/operational/batches`); **DM** = the row **Mortality/Regraft** actions open the same forms as the
  operational pages in modals prefilled with the clicked batch; **DN** = the Inventory table columns
  merged into **BATCH DETAILS / TOTAL GRAFTED / REGRAFT** (each value stacked with its date inline;
  REGRAFT uses the latest `regraft_date` via a `withMax` aggregate) + the nested-row **Sub-batch** badge
  renamed **Regrafted**; **DO** = the `Date:` labels removed + derived **STATUS** column
  (Growing / Ready / Completed / Cancelled-Void, `Batch::lifecycleStatus()`; filter uses
  `scopeWhereLifecycleStatus()`) and the row action rebuilt as **`[View] [More ▾]`** (read-only View modal;
  Regraft / Record Mortality / Record Deficit under More) (suite **389/1831** at DO); **DP** = the five
  filter-aware Inventory **summary cards** (Total Mortality / Regraft Deficit / Total Regrafted / Total
  Active Inventory / Total Batches, replacing the Total Grafted card; suite **392/1855**); **DQ** = the
  hosted Add Batch form now **system-assigns** the **Batch Code** (`Batch::nextBatchCode()`, next
  sequential root code; live preview B003) and **Grafted Date** (today), both shown read-only;
  `StoreBatchRequest` validates `Batch::storeRules()` and ignores submitted values (suite **394/1867**);
  **DR** = the `/inventory` filter bar now **auto-applies server-side** — the Apply/Filter button is
  removed and only **Clear Filters** remains; text search debounces 400 ms while the dropdowns/batch-year
  submit immediately, and the table + five summary cards refresh together (suite **395/1873**);
  **DS** = that auto-filter behavior is centralized once in `app.js` (`form[data-auto-filter]`) and reused
  by `/inventory/stock-in`, `/inventory/stock-out` and `/analytics/stock-ledger`, which now share the same
  Bootstrap filter card with only **Clear Filters** (suite **398/1898**); **DT** = the read-only Inventory
  **View** modal now lists the batch's recorded **Mortality Causes** (cause + quantity via the new
  centralized `MortalityRecord::causeBreakdownByBatch()`, one grouped query; raw `Others: <description>`
  preserved; suite **399/1909**); **DU** = the `/inventory` listing is **newest-first** (`BatchTree` opt-in
  `$descending` flag; `InventoryController@index` passes `descending: true`; suite **401/1913**); **DV** =
  the **Archived tabs were removed** from Checklist / Batches / Scion Variety and row **Archive → guarded
  permanent Delete** (centralized `canBeDeleted()`/`deletionBlockers()`; transactional deletes writing
  `task_deleted` / `batch_deleted` / new `scion_variety_deleted`; 6 archive/restore routes → 2 DELETE
  routes; approved one-time live cleanup of the 3 archived unreferenced scion varieties after a fresh
  backup; suite **400/1900**); **DW** = the read-only **Daily Checklist History** tab (one record per day
  with stored completions — date, staff, Completed/Pending, per-task Not Done; auto-applying
  Search/From-To/Status + Clear Filters; selected-record detail with staff/verifier/remarks; **Print**
  reuses the existing date PDF; **View Full Details** read-only modal; new `App\Support\ChecklistHistory`;
  suite **406/1960**); **DZ** = the Dashboard **Total Active Inventory** card (`/`, live **813**) + the
  Analytics **Total Grafted** card (`/analytics`, live **853**), both centralized `AnalyticsMetrics`
  calls, with the Operational Analytics PDF/CSV KPI lists aligned (4-box PDF strip); suite **407/1984**;
  **EA** = the Stock Ledger **"Remaining Available" column removed** from the page, the PDF and the CSV
  (the running balance is still computed and reconciled internally as an integrity guard) and the
  **CSV export rebuilt in the client's manual-ledger layout** (one block per month: month title,
  grouped section + per-Scion-Variety header rows, events, month TOTAL; then GRAND TOTAL, footnote and
  nested Batch summary); suite **407/1994**.
  The DI+DJ+DK+DL+DM+DN+DO+DP+DQ+DR+DS+DT+DU+DV+DW+DZ+EA working-tree files are uncommitted (head `49351ce`).
  Nothing below is newer than these.
- **Latest sessions (CH–CY) are captured in the top banner, the "Quick start for the next session"
  section and §5.** Summary: **CH** = Phase 7D live-`db_capstone` upgrade + reseed; **CJ**/**CK** =
  client-confirmed cumulative `total_grafted` + corrected mortality-rate denominator
  (`SUM(total_grafted − regraft)`); **CL** = the Mortality cause-of-death dropdown + "Others"
  description; **CM** = the Root-Cause Matrix `Others` roll-up (`MortalityRecord::normalizeCause()`);
  **CN** = the Stock In log Seedlings/Scion **Type** column + filter (`StockIn::entryType()`, no schema
  change); **CO** = the Seasonality Heatmap axis-label restyle (`Scion Variety` as a blue top-left note,
  caption removed, `Month` matched blue/bold — view-only, no schema/data change); **CP** = the Cacao Tree
  Stock Ledger printable PDF font auto-fit (`StockLedgerPdf::fitText()` — shrink-to-fit then truncate at
  a 4.2pt floor; headers/value cells/nested summary; no schema/data/view change); **CQ** = the Stock Out
  per-line Unit dropdown is now **Pots only** (`StockOutDetail::UNITS = ['pots']`, one centralized
  constant feeding the form select + both validators; no schema/data change; legacy rows keep
  `seedlings`); **CR** = the owner-directed `replanting_records` → `regraft_records` rename; **CS** =
  read-only KPI audit (no code change); **CT** = Stock Out gated on `Batch::survivingQuantity()` +
  read-only `php artisan cacao:health`; **CU** = Stock Out form's client-side `data-available` hint now
  reads `survivingQuantity()` (view-only + test); **CV** = the owner-approved `db_capstone` reseed
  under the corrected `total_grafted` rule (verified backup; `cacao:health` now passes); **CW** = the
  three cross-batch `total_grafted` totals switched to `SUM(total_grafted - regraft)` (Stock Ledger
  nested totals row, Gap `totalExpected`, Inventory snapshot total); **CX** = `cacao:health` rule 5
  reconciling `available_count` against `SUM(stock_out_details.quantity)`; **CY** = the "counts each
  plant once" help note on those same cross-batch totals (Stock Ledger/Gap Report/Inventory view, PDF and
  CSV) + an owner-approved `db_capstone` reseed (verified backup 37,218 B; before 6 batches / after 5,
  manual B004 removed; `cacao:health` clean before and after). **Committed:** CL–CR in
  `b939aab`; CT in `dccd154`+`7ec2f8c`; CU in `13e658e`; CV docs in `0a9251b`; CW in `f9bbd1e`; CX in
  `89905c9`; CY code in `b451b9c` (docs in `6f41a6a`). Everything below in this
  section is older context.
- **▶ Session CG (DONE — Phase 7C: parent/sub-batch nesting + Stock Ledger / analytics alignment;
  commits `7c84843`, `59488db`, `cc89669`, `b780873`):** implemented PRD_UPDATED §9.3 / FR-08 nested
  display for parent batches and regraft sub-batches. Single helper **`App\Support\BatchTree`** (RULE 6)
  drives ordering/depth everywhere. Batches + Inventory pages nest with `↳`, a Sub-batch badge, a
  Parent Batch column and a per-parent collapse. Stock Ledger: **Grafted = root batches only**,
  **Regraft** rows `Regraft {source} → {sub}` (sub-batch variety), **Seedlings = seedling-only Stock
  In** (before 1000 → after 800), footnote + computed **Batch summary (nested)** table, new server CSV
  `analytics.stock-ledger.export.csv`, PDF mirrors it. Age Profile + Gap Report nest rows with a Parent
  column (Gap formula unchanged). Batch filter dropdowns list sub-batches and match the exact batch
  only (parent excludes sub-batches) — documented in each page's help text. Seeder regrafts 30 into
  B001-R1-R1 (Criollo) via `RegraftBatch`. **No migration.** **Verified:** suite **328/1406**, Pint +
  `php -l` clean, `view:cache` clean, `migrate:status` **0 Pending** on `db_capstone_test`.
  **At the time `db_capstone` was untouched** (then old schema; four `2026_09_27_*` migrations
  Pending) — **superseded:** the live DB was upgraded + reseeded in Phase 7D, 2026-09-29. **Next
  options:** (A) apply 7A+7B to live `db_capstone` — **DONE in Phase 7D**; (B) confirm the Open Decisions with the
  client (PRD §19 `OD-1`…`OD-14` + the 7A/7B provisional defaults); (C) other client-gated items
  (scion-receipt Edit/Archive/Details, scion-receipt traceability, scion freshness rule, remember the
  Stock Ledger "Ready to Plant" mapping stays `available_count`). **No PRD/ERD file was edited.**
- **▶ Session CF (DONE — Phase 7B: regraft / sub-batch flow; commits `0f8195a`, `a82c513`, `e266412`,
  `52c5172`):** implemented the client-approved Regrafting / Sub-Batch Creation process
  (PRD_UPDATED §2.4/§8; ERD_UPDATED §2 rules 5-8, §3.8/§3.10/§3.11) as one atomic flow in
  **`App\Actions\RegraftBatch`** (the only place the steps live): lock source → re-check archived /
  `1 ≤ q ≤ regraftableQuantity()` / active scion variety / date not before `grafted_date` → source
  `total_grafted -= q`, `available_count -= q`, `regraft += q` → create the linked sub-batch
  (`parent_batch_id`, `nextSubBatchCode()` = `B001-R1`, own 21-day cycle + variety) → create the
  `ReplantingRecord` (`batch_id` = sub-batch, `parent_batch_id` = source) → `ActivityLog` (existing
  `replanting_recorded` type) → commit (all-or-nothing). `Batch` gained `regraftableQuantity()`,
  `nextSubBatchCode()`, `regraftRecordsAsParent()`; `totalReplanted()` = cached `batches.regraft`;
  `remainingReplantingCapacity()` removed; `subBatches` in `OPERATIONAL_RELATIONS`; `ReplantingRecord`
  gained `parent()`/`subBatch()`/`totalRegraftedFromBatch()`. Controller delegates to the action behind
  `DuplicateSubmissionGuard`; request requires batch/quantity/active scion variety/non-future date; the
  Regraft page was rebuilt (summary cards, per-batch table, Regraft History, source-prefilled modal) and
  the Batches list gained a **Parent Batch** column. **Provisional defaults (flag in code):** `OD-6`
  regraftable cap + sub-batch code rule; ERD §3.11 reading; `OD-12` counter = `totalReplanted()`.
  **Seeders** seed the regraft scenario **only via `RegraftBatch`** (idempotent). **Verified:** suite
  **299/1278**, Pint + `php -l` clean, `migrate:status` 0 Pending on `db_capstone_test`, seeded
  invariants hold (`available > total_grafted` 0, `available < 0` 0, family totals conserve,
  `Σregraft` == `Σ(records where parent_batch_id)`). **At the time `db_capstone` was untouched** (then
  old schema; the four `2026_09_27_*` migrations Pending) — **superseded:** upgraded + reseeded in
  Phase 7D, 2026-09-29. **Next:** Phase 7C (Stock Ledger / Inventory nested display)
  or confirm the Open Decisions with the client. **No PRD/ERD file was edited.**
- **▶ Session CC (DONE — `Scion_Receiving` merged into `Stock_In`; commit below):** client confirmed
  seedling Stock In and Scion Receiving are the same real-world receiving event. Migrations
  `2026_09_26_000003` (add nullable `scion_variety_id`/`unit_price`/`notes` to `stock_ins`; make
  `supplier_id` nullable) and `2026_09_26_000004` (data-migrate `scion_receivings` rows into
  `stock_ins` with `viable = quantity` and `origin`/`notes` preserved, then drop the table; create
  migration `2026_09_26_000001` kept as history). `supplier_id` is now nullable because scion rows
  may have no formal Supplier (and real rows did); a nullable `notes` column was added so the
  receiving notes are preserved; scion rows set `viable = quantity` (no five-day recount). Removed the
  `ScionReceiving` model/factory/test/controller/request/view, its 2 routes and the sidebar link; the
  Stock In modal now has a **Seedlings/Scion** tab pair and the list a **Scion Variety** column. Docs:
  AGENTS.md + both ERD copies (kept byte-identical). Suite **278/1163**, Pint + `php -l` clean,
  `migrate:status` 0 Pending on `db_capstone_test` and `db_capstone`. **Live `db_capstone` migrated**
  after a fresh verified backup `...\Temp\opencode\db_capstone_backup_cc_20260926_170736.sql`
  (36,778 B / 24 tables): `scion_receivings` 2 rows → 2 `stock_ins` scion rows (`stock_ins` 2 → 4),
  table dropped. **Still open (client-gated):** scion-receipt Edit/Archive/Details, receiving
  traceability (graft/regraft → Stock_In), and the freshness/viability rule.
- **▶ Session CB (DONE — Scion Receiving "Add" CRUD UI; commit `6cd5d75`) — RETIRED in Session CC:** shipped the first slice of
  the deferred **Scion Receiving CRUD UI** — the **Add** modal + its `index`/`store` route group
  (`/operational/scion-receivings`, `permission:manage_inventory`), `ScionReceivingController`,
  `StoreScionReceivingRequest` (field-specific messages), the sidebar link and the log view
  (`resources/views/operational/scion-receivings/index.blade.php`). Suite green **280/1170**;
  Pint/`php -l` clean; **71 routes**; no schema/DB change. **Still deferred (client-gated — do not
  build unprompted):** the Scion Receiving **Edit/Archive/Details** modals and the
  **grafting/regrafting traceability link** (no stock-balance deduction); the Stock Ledger
  "Ready to Plant" mapping (**leave as `available_count`**).
- **▶ Session BZ (DONE — P6 seeders/tests/docs/verify; refactor P0–P6 complete):** the test suite,
  factories and seeders now target the revised 15-entity model and pass (**280/1170**). See the
  Session BZ entry in the banner and §5. **P6 deferred items (confirmed open, DO NOT build without
  sign-off):** (1) Scion Receiving linkage/traceability (graft/regraft referencing a
  `Scion_Receiving` row; **no** stock-balance deduction); (2) Scion Receiving CRUD UI — **Add + list
  SLICED IN Session CB (`6cd5d75`): only `index`/`store` are built**; the **Edit/Archive/Details**
  modals remain unbuilt (fields: scion variety, origin nullable, supplier nullable, quantity, date
  received, price, notes); (3) Stock Ledger
  "Ready to Plant" mapping — leave as `available_count` (unconfirmed), do not change. **`db_capstone`
  data policy (decided, NOT executed):** recommend **wipe + reseed** with fresh schema-valid data
  after a fresh backup, because the live rows are pre-refactor residue (`available_count` = old
  ledger 1130, not `viable − stock_out`; B005 available 600 > viable 100; releases not reproducible
  by the validated Stock Out path). Fallback: preserve with a documented stale-ledger caveat. **Any
  destructive action against `db_capstone` requires separate explicit approval.** Full text in
  `AGENTS.md` §"P6 deferred items" / §"db_capstone data policy — RECOMMENDATION".
- **▶ Session BY (DONE — handoff refresh + ERD user-facing terminology; doc-only, commit `9bd3c8a`):**
  renamed the two remaining user-facing "Replanting" labels to **"Regraft"** in both
  `docs/CacaoTrack_ERD.md` and `docs/CacaoTrack_ERD_REVISED.md` (§7 sidebar list + §8 Design Principle),
  matching the executed display rename (`bd47433`). **Internal names / §3.11 kept as-is**
  (`Replanting_Record` / `replanting_records`). Both ERD files byte-identical. **No code/DB/migration
  change.**
- **▶ NEXT = P6 (seeders/tests/docs/verify)** — see the banner and the "NEXT" bullet further below.
  **First decision:** the Session BR data policy (regenerate clean seed data vs synthetic fixtures).
- **▶ Session BX (DONE — the `regraft_records` consolidation APPLIED; code + migration, commit
  `22ef695`):** `regraft_records` was **dropped** (0 rows — no data migration; migration
  `2026_09_26_000002_drop_regraft_records_table`, create migration `2026_09_25_000009` kept as
  history), `app/Models/RegraftRecord.php` deleted, `Batch::regraftRecords()` + the `AnalyticsMetrics`
  import removed, and `AnalyticsMetrics::stockLedgerGrid()` repointed at **`replanting_records`**
  (grouped by `replanting_date` + the batch's `scion_variety_id`, mirroring the Mortality column).
  Live proof: `Schema::hasTable('regraft_records')` = **false**; Stock Ledger grand Re Graft = **195**
  (= `replanting_records` 13 rows / SUM 195; was 0). **`AGENTS.md` + both ERD docs now list one
  approved additive exception (`scion_receivings`).** No test suite run (P6). This **closes the Session
  BU proposal.** **Remaining next action:** settle the Session BR P6 data policy (regenerate clean seed
  data vs synthetic fixtures), then implement P6 (seeders/tests/docs/verify).
- **▶ Session BW (historical — docs sync, verification only):** superseded by Session BX, which applied
  the consolidation BW had verified as pending.
- **▶ Session BV (DONE — `Scion_Receiving` schema + model only; commit `33ff6b0`):** added table
  `scion_receivings` + `App\Models\ScionReceiving` (ERD §3.16; both ERD docs updated, byte-identical).
  Confirmed: `supplier_id` **nullable**, a **single nullable `origin`** column. **Additive only** — no
  controller/route/view, no balance on `scion_varieties`, `ReplantingRecord` untouched.
  Validation is `ScionReceiving::rules()` (built-in rules; no extra table). **Freshness/viability NOT
  implemented** (no usable-until, no warning, no blocking) pending client confirmation. **Follow-ups
  (open):** (1) the usage/traceability link (how a future operation references a receiving record) is
  still undefined — no CRUD UI yet; (2) **RESOLVED** — `AGENTS.md` now lists **one** approved additive
  exception (`scion_receivings` Session BV; `regraft_records` was retracted in Session BX); (3) the
  client must confirm the allowed viability window + warn-vs-block before any freshness logic is built.
  **No operational data changed.**
- **▶ Session BU (read-only audit — consolidation PROPOSED there, do not re-run; NOW APPLIED IN BX).**
  Session BU had found `regraft_records` still present (0 rows) with no drop migration and the Stock
  Ledger reading the empty table instead of `replanting_records`' 13 rows. **Resolution (Session BX,
  commit `22ef695`):** all five proposed steps were executed — (1) `stockLedgerGrid()` repointed at
  `ReplantingRecord` (`replanting_records.quantity` / `replanting_date`); (2) `Batch::regraftRecords()`
  + the `App\Models\RegraftRecord` import removed; (3) `app/Models/RegraftRecord.php` deleted; (4) a
  `down()`-safe migration `2026_09_26_000002_drop_regraft_records_table` added (the create migration
  `2026_09_25_000009` left as history); (5) `Regraft_Record` retired in `AGENTS.md` / the ERD, leaving
  `scion_receivings` as the only additive exception. **No operational data was changed.**
- **▶ Session BT (DONE — Regraft display rename; commit `bd47433`):** user-facing "Replanting" is now
  "Regraft" everywhere (sidebar, KPI cards, Stock Ledger header, PDF/CSV headers, Activity label,
  permission label, copy); internal names unchanged; Dashboard KPIs identical (1130 / 40.00% / 385).
  **No DB/schema/data change.** Not to be confused with the Session BU consolidation, which is about
  the *data source* (which table the ledger reads), not the label.
- **▶ Session BS (DONE — Gap formula corrected; commit `1744975`):** `Gap = Batch::effectiveMortalityQuantity()`.
  The prior "Open decision #1" is **CLOSED**. The old
  `max(0, viable_count − available_count − total_stock_out_for_batch)` was an identity that always
  resolved to 0 (Stock Out is already netted out of `available_count`). Verified live: 9 batches, row
  gaps sum to `totalGap = 580` = `SUM(mortality_records.quantity)`. **No DB/schema/data change**;
  `AGENTS.md` + the Gap view/PDF/CSV updated. **Still open:** "Ready to Plant" mapping. (The former
  `regraft_records` CRUD UI flag is **dropped** — the table was retracted in Session BX.) **NEXT is
  unchanged: P6** (after the data-policy decision below).
- **▶ Session BR data finding (read-only audit; decide BEFORE P6):** the live `db_capstone`
  operational rows are all **pre-refactor residue** (batches created `2026-09-12`…`23`, all 7
  stock-outs migrated from the OLD schema). `batches.available_count` was backfilled once from the old
  `inventories.current_quantity`, so it equals the old ledger (`initial + stock-ins − stock-outs −
  reversals` = 1130), **not** `viable_count − stock_out`; this fully explains the P5 Gap=0 flag and
  the `viable − available` (40) vs stock-out (2130) mismatch. The current validated Stock Out path
  (`StockOutController::lockAndValidateBatches` `:180/:194/:215`; `StoreStockOutRequest` has no
  availability rule) would reject those releases, so they are not reproducible today. **Decide the P6
  data policy:** (a) regenerate clean seed data (and reconcile `available_count`), or (b) leave the
  live rows and build P6 seeders/tests from synthetic fixtures. **No data was changed.** See §2
  Session BR / §3.
- **▶ NEXT — Revised PRD/ERD refactor: P0 + P1 + P2 + P3 + P4 + P5 DONE; implement P6 (Tests/docs/verify) next.**
  **Session BP housekeeping:** P4 committed as `0803bdf` and the `AGENTS.md` banner corrected in
  `5a2a1ae` — no code/DB change, no test suite run. The revised **15-entity** `docs/CacaoTrack_PRD.md`
  + `docs/CacaoTrack_ERD.md` are canonical, and
  **`db_capstone` has the revised schema** (P1), `app/Models` matches it (P2 — Session BM),
  `app/Http/Controllers` + `routes/web.php` match it (**P3 — Session BN, checkpoint `3d3f9f9`**;
  66 routes), and the **views/sidebar match it** (**P4 — Session BO**; sidebar repointed, the dead
  modules removed, the scion-varieties CRUD view added, and Batches/Stock In/Stock Out/Inventory/
  Mortality reworked; runtime smoke test 200s). **P5 (DONE — Session BQ, checkpoint `d139286`) migrated
  the analytics/report layer**: `AnalyticsMetrics` rewritten onto the current models, Dashboard `/`
  returns **200**, Stock Ledger is a monthly grid, PDF/CSV rebuilt, plus the then-approved
  `regraft_records` migration/model (**retracted in Session BX — see the banner**). **The seeders/tests
  (P6) are still the OLD model** — the feature
  suite still fails until P6 rewrites it (expected). (The `/activity` and user delete-guard flags were
  fixed in Session BO — see above.) Pre-P1 backup:
  `...\Temp\opencode\db_capstone_pre_p1_20260925_142909.sql`. **P5 flags:** the locked Gap formula
  resolved to **0** at the time — **corrected in Session BS (DONE)** to
  `Gap = Batch::effectiveMortalityQuantity()`; "Ready to Plant" is a best-guess mapping. **P2 delivered:** added
  `ScionVariety`/`StockOutDetail`; reworked `Batch`/`StockIn`/`StockOut`/`Inventory`/`MortalityRecord`
  (replanting unchanged); deleted `BatchMonitoring`/`Variety`/`PotType`/`Scion`/`MortalityAdjustment`/
  `StockReversal`/`ScionMetrics`; fixed `Analytic::rules()` + `ActivityLog::TYPES`; **no new table**.
  **P2 flags:** no DB unique index for one-snapshot-per-batch-per-month (app layer must enforce; a
  migration is needed for DB enforcement); `ScionController`/`ScionTest` still `use
  App\Support\ScionMetrics` (deleted — fatal until P3/P6).
  **P3 (DONE — Session BN, checkpoint `3d3f9f9`; see the Session BN entry in §2):** deleted
  `BatchMonitoringController`/`VarietyController`/`PotTypeController`/`StockReversalController`/
  `ScionController` + `Mortality@adjust`; added `ScionVarietyController`
  (`/operational/scion-varieties`, `manage_inventory`); Stock Out is now a header + `details[]` with
  per-line AND per-batch aggregate availability checks inside one transaction (scion variety derived
  server-side, `amount`/`total_amount` computed); Stock In (no batch, `viable`/`origin`,
  `viable <= quantity`); Inventory `store` "Record Monthly Inventory" (one snapshot per batch per
  month); Batches moved to `/operational/batches` (`record_operations`); Mortality record-only (cap =
  `remainingMortalityCapacity()`, never mutates `viable_count`); Replanting drops the Scion-pool
  deduction. Added Form Requests for every transactional store/update + `DuplicateSubmissionGuard`;
  **no new table**. **P3 flags:** (1) no DB unique index for one snapshot per batch per month
  (app-layer only); (2) inventory snapshots write no Activity_Log row (no `inventory_recorded` type,
  model edit out of scope); (3) snapshot permission = `manage_inventory`; (4) view paths still old —
  P4 must repoint the sidebar/views and create
  `resources/views/operational/scion-varieties/index.blade.php`.
  **P4 (DONE — Session BO, committed Session BP as `0803bdf`)** views/sidebar implemented (sidebar repointed to the P3 routes; dead
  modules removed; `operational/scion-varieties` CRUD view added; Batches/Stock In/Stock Out/
  Inventory/Mortality reworked; runtime smoke test returns 200s — see the Session BO entry in §2);
  **P5 (DONE — Session BQ `d139286`; Gap corrected in Session BS `1744975`):
  analytics/reports (Gap = `Batch::effectiveMortalityQuantity()`); **P6** tests/docs/verify. **All open decisions are RESOLVED:** P1-era: (a)
  monthly `inventories` rows are written by an explicit **"Record Monthly Inventory"** action (stored
  snapshots), not auto-derived; (b) `stock_out_details.unit` is a **fixed list**
  `seedlings`/`pieces`/`bags`; (c) `stock_ins.origin` is NOT NULL with legacy `''`. **P2-era (Session
  BK):** (d) mortality **never** mutates `batches.viable_count` (mortality cap =
  `max(0, viable_count − effectiveMortalityQuantity())`); (e) `inventories.status` is a **stored,
  frozen** value computed when the snapshot is recorded via the shared
  `Inventory::statusForAvailable()` rule (`active`/`depleted`), not re-derived live. **P3-era (Session
  BL):** (f) Batches live at `/operational/batches` under `record_operations`; (g) Scion Variety lives
  at `/operational/scion-varieties` under `manage_inventory`; (h) Stock Out derives `scion_variety_id`
  from the batch server-side; (i) one `inventory` snapshot per batch per calendar month; (j) Stock In
  enforces `viable <= quantity`. Other locked points unchanged: `varieties` → `scion_varieties`;
  `batches.available_count` is the live balance; `inventories` are monthly snapshots; Stock Out is a
  header + per-line (batch + scion_variety) checks; revised ERD wins over PRD prose.
- **Batch Monitoring is read-only; Mortality drives the alive count (Session BG — latest)** — the
  feature now shows each batch's current **alive plant count** (stored `batches.viable_count`) and a
  `Pending`/`Complete` status from `Batch.Monitoring_Start_Date`. The manual Record Monitoring
  update, the Survival Rate and the observation-based helpers were removed; `Batch_Monitoring` is
  retained but no longer written. **Mortality Tracking is the only plant-status writer**: recording
  mortality (or a positive adjustment) deducts the dead plants from `viable_count` (floored at 0)
  transactionally via `Batch::applyAliveDelta()`; a negative adjustment restores them.
  `AnalyticsMetrics::survivalVsMortality()` was updated to avoid double-counting. Full suite
  **324/1329**, Pint clean, `view:cache` clean, 2 batch-monitoring routes, `migrate:status` 0
  pending. **No migration, no schema/DB change, no new table.**
- **Batch Monitoring status simplified (Session BF)** — the derived status uses
  **two states only**: `Pending` until a batch reaches the full 21-day lifecycle, then
  automatically `Complete` (lifecycle calculated from `Batch.Monitoring_Start_Date`). The
  `Overdue`/`Incomplete`/`Needs Attention`/`Not Started` statuses and the alert banner were
  removed, and each batch now shows **Alive Plants** (latest observation survivors) and **Plant
  Survival Rate** (alive ÷ total grafted × 100), both centralized in `App\Models\BatchMonitoring`
  (RULE 6). Full suite **332/1353**, Pint clean, `view:cache` clean, 3 routes, `migrate:status`
  0 pending. **No migration, no schema/DB change, no new table.**
- **Handoff refreshed (Session BE)** — verification only: full suite **332/1348**, Pint
  clean, `view:cache` clean, **81 routes**, `migrate:status` 0 pending, live `db_capstone` re-read
  (§2 Session BE snapshot: mortality 13/effective 485, replanting 11/sum 175, `activity_logs` 62,
  scion pool 25, inventories sum 1035, `analytics` 12, `batch_monitorings` 0). **No code, no
  migration, no DB change** (drift is UI usage). The file is current for the next session.
- **Batch Monitoring status logic refined (Session BD)** — the derived status now follows
  the approved decision order `Not Started` → `Overdue` (day > 21 + unresolved deficit) →
  `Incomplete` (a required daily observation is missing) → `Needs Attention` (at-risk plants) →
  `Monitoring` (day ≤ 21, observations complete) → `Complete` (day 21 completed + conditions
  resolved). `Unhealthy`/`Completed` were renamed to `Needs Attention`/`Complete`, and
  `BatchMonitoring::observationsComplete()` treats a single skipped day as `Incomplete` until
  backfilled. Full suite **332/1348**, Pint clean, `view:cache` clean. **No migration, no schema/DB
  change.**
- **Batch Monitoring built (Session BC)** — `/operational/batch-monitoring` (FR-18).
  Approved ERD revision §2.6/§9.11: nullable `Batch.Monitoring_Start_Date` (backfilled from
  `grafted_date`) + the new `Batch_Monitoring` entity (19th table) storing one daily plant-status
  observation per batch per day. Tracks the **21-day lifecycle** (monitoring day + remaining days),
  derives statuses (`Not Started`/`Monitoring`/`Unhealthy`/`Incomplete`/`Completed`/`Overdue`) and
  alerts (unhealthy/incomplete/overdue), with summary cards and a complete per-batch history.
  Observed mortality writes to the existing `mortality_records` (single source). Integrates with
  existing batch/variety/replanting/inventory data and duplicates nothing. Full suite
  **330/1344**, Pint clean, `view:cache` clean, **81 routes**, `migrate:status` 0 pending.
  Migrations `2026_09_22_000001`/`000002` applied to `db_capstone` (8 batches backfilled; 0
  monitoring rows). **No duplicate batch data.**
- **Handoff refreshed (Session BB)** — verification only: full suite **315/1288**, Pint
  clean, **78 routes**, `migrate:status` 0 pending, live `db_capstone` re-read and **identical to
  the Session BA snapshot** (scions 1/pool 40, `activity_logs` 57, active inventory 1035). **No
  code, no migration, no DB change.** The file is current for the next session.
- **Scion stock pool (Session BA)** — scions are now a single generic stock item (`Scion`)
  with a stored `quantity`; the page shows **Number of Scions** and a **Receive Scions** action, and
  saving a replanting record **deducts** the used scions transaction-safely. Approved ERD revision
  §2.8/§9.10; §9.9's stock-in scion capture is retired (column kept for history). Migration
  `2026_09_21_000002` consolidated the 4 named scions into one row (backup taken first; no
  `migrate:fresh`). Full suite **315/1288**, Pint clean, `view:cache` clean, **78 routes**,
  `migrate:status` 0 pending. **No new table.**
- **Handoff refreshed (Session AZ)** — verification only: full suite **327/1337**, Pint
  clean, `view:cache` clean, **81 routes**, `migrate:status` 0 pending, live `db_capstone` re-read
  (§3 Session AZ snapshot: batches 8, mortality 11/effective 455, replanting 8, stock-ins 6,
  inventory sum 1035, `activity_logs` 57). **No code, no migration, no DB change** (drift is UI
  usage).
- **Total Available Scions deducts replanting (Session AY — superseded by the Session BA stock
  pool)** — formerly `ScionMetrics` returned
  `available = max(0, SUM(stock_ins.scion_quantity) − SUM(replanting_records.quantity))`. The
  Session BA ERD §9.10 revision replaced this with the stored `scions.quantity` balance (still
  debited by replanting). Rejected stays independent. Full suite back then was **327/1337**.
- **Scion received-scion capture + Scion stock cards (Session AX)** — the Stock-In modal
  now captures the total scions received (`stock_ins.scion_quantity`, default 0; approved ERD
  revision §2.15/§9.9), and the Scions page shows **Total Available Scions / Re-grafted Scions /
  Rejected Scions**, centralized in `App\Support\ScionMetrics` (RULE 6). Formulas: Total Available =
  `SUM(stock_ins.scion_quantity)` (total received — rises on every scion stock-in); Re-grafted =
  `SUM(replanting_records.quantity)`; Rejected = effective mortality (separate, independent cards).
  **No new table.** Full suite **326/1329**, Pint clean, `view:cache` clean.
- **Mortality Root-Cause Matrix increase alert (Session AW)** — `/analytics/mortality-matrix` now
  compares the current live matrix against the most recent generated Matrix report with matching
  filters and alerts (banner + highlighted cause/cell/total + a transient warning toast) when a
  cause's effective mortality rose by any amount. Snapshot extended (`mortalityMatrixSummary()`);
  comparison centralized (`mortalityMatrixRegressions()`, RULE 6). Page-level; needs a saved report
  baseline. **No table, no migration, no ERD revision.** Full suite **319/1303**, Pint clean,
  `view:cache` clean.
- **Handoff refreshed + docs fixed (Session AV)** — no code/DB change: full suite
  **309/1265**, Pint clean, **81 routes**, `migrate:status` 0 pending, live `db_capstone` identical
  to Session AU. Corrected `AGENTS.md` (`page_actions` section-name typo, `RULE 6/25` →
  `RULE 6 / PRD §25`, Staff-permission clarification) and this handoff's `RULE 6/25` refs. The file
  is current for the next session.
- **Roles + Role_Permissions merged into Users (Session AU)** — approved denormalization revision
  (ERD §2.1/§9.1): the `roles` + `role_permissions` tables and `users.role_id` were dropped and
  replaced by `users.role` (string) + `users.permissions` (JSON), so the model has **18 tables**.
  `RolePermission`/`RoleController`/`RoleSeeder`, the Users|Roles tab, the `+ New Role` quick-add and
  the 4 `users.roles.*` routes were removed; `App\Models\Role` is now a **code-only catalog helper**
  (`PERMISSIONS`, role names, `defaultPermissionsFor()`, `CHECKLIST_DELETE_ROLES`). The migration
  backfilled and verified the data before dropping the tables. Full suite **309/1265**, Pint clean,
  **81 routes**. **Do not reintroduce the `roles`/`role_permissions` tables or `users.role_id`.**
- **Daily Checklist guarded delete (Session AT)** — `DELETE /operational/checklist/tasks/{task}`
  permanently deletes an **archived** task **only** when it has no `Task_Complete` history; a task
  with history is refused and stays archived, and an active task must be archived first. Restricted
  to **Manager/Administrator** (`Role::CHECKLIST_DELETE_ROLES`, RULE 6; **403** otherwise, button +
  modal hidden). Writes a `task_deleted` audit row. **No new table/column.** Full suite was
  **324/1319** before the Session AU merge.
- **Handoff refreshed (Session AS)** — verification only: full suite **317/1291**, Pint clean,
  **84 routes**, `migrate:status` 0 pending, live `db_capstone` re-read (`users` 5, `activity_logs`
  28, `analytics` 9, active inventory 605). **No code, no migration, no DB change** (the `users`
  4→5 / `activity_logs` 27→28 drift is from UI usage, not this session). Superseded by Sessions
  AT/AU.
- **Gap Report print + CSV built (Session AR)** — `/analytics/historical/gap-report` now has a
  **Print Report** action (`App\Support\GapReportPdf`, `GET /analytics/historical/gap-report/export`)
  and an **Export CSV** action (`GET /analytics/historical/gap-report/export/csv`), both built from
  the same `AnalyticsMetrics::gapReport()` data as the page, plus a **Generated Gap Reports** panel.
  **No table, no migration** (PRD §9/§14.3, ERD §9.4). Full suite **317/1291**, Pint clean. No OPEN
  DECISIONS.
- **Handoff refreshed (Session AQ)** — verification only: full suite **312/1249**, Pint clean,
  **82 routes**, `migrate:status` 0 pending, live `db_capstone` re-read (`analytics` 9,
  `activity_logs` 27, active inventory 605, 0 archived rows). **No code, no migration, no DB
  change.** The file is current for the next session.
- **Operational Analytics print + CSV built (Session AP)** — `/analytics` now has a **Print
  Analytics** action (`App\Support\OperationalAnalyticsPdf`, `GET /analytics/export`) and an
  **Export CSV** action (`GET /analytics/export/csv`), both built from the same centralized
  `AnalyticsController::operationalData()` as the page. **No table, no migration** (PRD §9,
  ERD §9.4). Full suite **312/1249**, Pint clean. No OPEN DECISIONS.
- **Handoff refreshed (Session AO)** — verification only: full suite **309/1210**, Pint clean,
  **80 routes**, `migrate:status` 0 pending, live `db_capstone` re-read (`analytics` 9,
  `activity_logs` 27, active inventory 605). **No code, no migration, no DB change.** The file is
  current for the next session.
- **Mortality Root-Cause Matrix print + CSV built (Session AN)** — `/analytics/mortality-matrix`
  now has a **Print Matrix** action (`App\Support\MortalityMatrixPdf`, `GET
  /analytics/mortality-matrix/export`) and an **Export CSV** action (`GET
  /analytics/mortality-matrix/export/csv`), both filter-aware and built from the same validated
  `AnalyticsMetrics::mortalityMatrix()` data. **No table, no migration** (PRD §9/§13.2, ERD §9.4).
  Full suite **309/1210**, Pint clean. No OPEN DECISIONS.
- **Generate Report output built (Session AM)** — the Stock Ledger and Root-Cause Matrix pages now
  render their own type-scoped **Generated Reports** panel (period / generated by / generated at +
  a **View** modal of the stored `result_summary` snapshot), so Generate Report has visible output
  there. `summaryFor(TYPE_STOCK_LEDGER)` now stores real ledger totals via
  `AnalyticsMetrics::stockLedgerSummary()` (was incorrectly the gap payload). The necessary DB
  already exists — the ERD `Analytics` entity `analytics` (ERD §9.4) — **no new table, no
  migration**. Full suite **303/1169**, Pint clean. No OPEN DECISIONS.
- **Mortality Root-Cause Matrix consistency built (Session AL)** — `/analytics/mortality-matrix`
  now shows the Raw mortality → signed Adjustments → Effective mortality chain (summary strip +
  in-cell `base (±adj)`), with all three figures coming from the same centralized
  `MortalityRecord::effectiveQuantity()` the Mortality module uses (RULE 6 / PRD §25). `AnalyticsMetrics::`
  `mortalityMatrix()` also returns `rawTotal`/`adjustmentTotal`/`rawTotalsByCause`/
  `adjustmentTotalsByCause` + per-row `rawCells`/`adjustmentCells`. **No table, no migration, no
  ERD revision** (PRD §9/§13.2, ERD §9.4). Full suite **299/1146**, Pint clean. No OPEN DECISIONS.
- **Stock Ledger printable PDF built (Session AK)** — the `/analytics/stock-ledger` page now has a
  **Print Ledger** action (`GET /analytics/stock-ledger/export`, `AnalyticsController@exportPdf`,
  `App\Support\StockLedgerPdf`) that renders the real, filter-aware per-batch ledger + totals strip
  as an FPDF landscape report. **No table, no migration** — it is generated from normalized
  operational data (PRD §9/§13.1, ERD §9.4). Full suite **296/1125**, Pint clean. No OPEN DECISIONS.
- **Master-data archive/restore built (Session AJ)** — approved ERD revision §9.8 added
  a nullable `archived_at` to the six master tables (`suppliers`, `clients`, `varieties`, `scions`,
  `pot_types`, `batches`); all six Management pages archive/restore instead of deleting, hidden from
  active listings and new-record selectors but preserved for history and analytics. The shared
  `App\Models\Concerns\Archivable` trait centralizes the behavior and the `activeExistsRule()`
  validation. Full suite **292/1100**, Pint clean. No OPEN DECISIONS.
- **Daily Checklist print output redesigned (Session AI)** — the printable PDF
  (`App\Support\ChecklistPdf`, `GET /operational/checklist/export`) got a branded layout (header
  band + summary strip + zebra table + colored priority/status badges + cleaner signatures) and
  the page action was relabelled **Print Checklist**. PHP/Blade only — **no table, no migration**
  (the PDF is generated from the normalized `activity_tasks` + `task_completes`; ERD §9.6 /
  PRD §9). Full suite **277/1006**, Pint clean. No OPEN DECISIONS.
- **Roles & access restrictions — merged into Users (Session AU; was Session AH)** — the earlier
  normalized build (`roles` + `role_permissions` + `users.role_id`, `RoleController`, Users|Roles
  tabs) was replaced by the approved denormalization: `users.role` (string) + `users.permissions`
  (JSON). `Administrator` is a protected role (always holds `manage_users`; the last admin cannot be
  demoted or deleted); every user must keep ≥1 permission. Full suite **309/1265**, Pint clean.
  **See the Session AU entry above.**
- **Do not bypass access restrictions (Session AU)** — permission keys live only in
  `Role::PERMISSIONS` (RULE 3/6); enforcement is server-side via the `permission` middleware. Do not
  reintroduce the `roles`/`role_permissions` tables, `users.role_id`, or a `Permissions` master
  table. Defaults preserve the pre-roles access model (Administrator = all 7; Manager/Staff = all
  except `manage_users`).
- **Handoff refreshed (Session AG)** — verification only at the time; its "keep only the 3 fixed
  roles" decision was **superseded by Session AH**, which approved and implemented the `Roles`
  entity.
- **Handoff refreshed (Session AF)** — no OPEN DECISIONS remain. The
  Notifications/Settings subsystem was reverted to transient toast feedback; full suite
  **259/939**, Pint clean, `view:cache` clean, no `/notifications`/`/settings` routes, and
  migration `2026_09_16_000004` applied to `db_capstone` (no data change). This file is current
  for the next session.
- **Stock In is done** (`suppliers`, `stock_ins`, controller, view, tests). `current_quantity` is
  mutated by stock-in via `Inventory::applyDelta()`.
- **Stock Out is done** (`clients`, `stock_outs`, controller, view, tests). `current_quantity` is
  now decreased by stock-out via `Inventory::applyDelta()` inside a DB transaction, with an
  over-availability rejection (FR-09).
- **Stock Reversal is done** (`stock_reversals`, model, controller, view, tests). Its OPEN DECISION
  is resolved (two nullable FKs, exactly one populated). It reuses `Inventory::applyDelta()` inside
  a DB transaction and rejects a reversal quantity greater than the reversible quantity (FR-10).
- **Suppliers management (FR-11) is done** — full CRUD at `/inventory/suppliers`
  (`SupplierController`, `Supplier::rules()`/`reference()`, tests). Deletion is blocked when
  stock-ins exist; duplicate names are allowed; the Stock In inline quick-add is unchanged.
- **Clients management (FR-12) is done** — full CRUD at `/inventory/clients`
  (`ClientController`, `Client::rules()`/`reference()`, tests). Deletion is blocked when stock-outs
  exist; duplicate names are allowed; the Stock Out inline quick-add is unchanged. Mirrors Suppliers.
- **Varieties management (FR-13) is done** — full CRUD at `/inventory/varieties`
  (`VarietyController`, `Variety::rules()`/`reference()`, tests). Deletion is blocked when batch
  records exist; duplicate names are allowed; `description` is optional. No inline quick-add (Batch
  creation is out of scope). Mirrors Suppliers/Clients.
- **Scions management (FR-14) is done** — full CRUD at `/inventory/scions`
  (`ScionController`, `Scion::rules()`/`reference()` → `SCN-###`, tests). Deletion is blocked when
  batch records exist; duplicate names are allowed; `description` is optional. No inline quick-add
  (Batch creation is out of scope). No new migration — the `scions` table already existed. The
  `/scions` route is now under the `auth` middleware (it was previously unauthenticated). Mirrors
  Variety Management.
- **Pot Types management (FR-15) is done** — full CRUD at `/inventory/pot-types`
  (`PotTypeController`, `PotType::rules()`/`reference()` → `POT-###`, tests). Deletion is blocked
  when batch records exist; duplicate names are allowed; `description` is optional. No inline
  quick-add (Batch creation is out of scope). No new migration — the `pot_types` table already
  existed. Mirrors Variety/Scion Management.
- **Analytics is done** — `AnalyticsController` + `App\Support\AnalyticsMetrics` + `analytics`
  table/`Analytic` model + 6 real pages + tests. All formulas centralized; all routes under `auth`.
  Resolves the former Analytics OPEN DECISION (ERD §9.4). No seed changes.
- **Stock Ledger verified (Session O)** — already functional via `AnalyticsController@stockLedger`
  + `AnalyticsMetrics::stockLedgerQuery()`; no code or DB change. The static ledger the user saw
  belongs to the stale `Capstone\capstone` copy — always serve from the real root.
- **Root-Cause Matrix verified (Session P)** — already functional via
  `AnalyticsController@mortalityMatrix` + `AnalyticsMetrics::mortalityMatrix()`; no code or DB
  change. The static matrix belongs to the stale `Capstone\capstone` copy — always serve from the
  real root. Confirmed live: grand total 45 (Criollo/Disease 20, Trinitario/Drought 15,
  Forastero/Pest 10).
- **Seasonality Heatmap verified (Session Q)** — already functional via
  `AnalyticsController@seasonality` + `AnalyticsMetrics::seasonality()`; no code or DB change, and
  no new table (generated from normalized mortality data). The static/mock heatmap belongs to the
  stale `Capstone\capstone` copy — always serve from the real root. Confirmed live (2026): max 20,
  total 45 (Sep Criollo 20, Sep Trinitario 15, Aug Forastero 10).
- **Age Profile verified (Session R)** — already functional via `AnalyticsController@ageProfile` +
  `AnalyticsMetrics::ageProfile()`; no code or DB change, and no new table (age is derived from
  `batches.grafted_date` + `inventories.current_quantity`). The static/mock page belongs to the
  stale `Capstone\capstone` copy — always serve from the real root. Confirmed live: `[95, 140, 500,
  0]` across 0–30/31–60/61–90/91+ buckets, total 735.
- **Gap Report verified (Session S)** — already functional via `AnalyticsController@gapReport` +
  `AnalyticsMetrics::gapReport()`; no code or DB change, and no new table (expected =
  `batches.viable_count`, actual = `inventories.current_quantity`, gap = `max(0, expected −
  actual)`). The static/mock page belongs to the stale `Capstone\capstone` copy — always serve from
  the real root. Confirmed live: B001 180/500/0, B002 140/140/0, B003 95/95/0; totals expected 415,
  actual 735, gap 0.
- **Activity History (FR-03/FR-17) is done** — `activity_logs` table, `ActivityLog` model
  (centralized `record()` writer + `TYPES`), `ActivityController@index`, real filterable view,
  backfill seeder, 16 tests. Operations write activity rows atomically; `/activity` is under `auth`.
  See Session T.
- **Profile (ERD `Users`) is done** — no new table (the ERD has no Profile entity); `role` column
  added to `users` per ERD §2.1 (default `Staff`, admin is `Administrator`), `ProfileController`
  (`index`/`update`/`updatePassword`), real view with Edit Profile + Change Password modals,
  `profile_updated` activity rows, 13 tests. Own-role editing is blocked (privilege escalation);
  role assignment belongs to FR-02 Users management. `/profile` is under `auth`. See Session U.
- **Profile verified (Session W)** — already functional via `ProfileController` +
  `User::profileRules()`/`passwordRules()`/`ROLES` + `ActivityLog::record()`; no code or DB
  change, and no new table (Profile is the own `users` row, ERD §2.1/§9.1). The static/mock page
  belongs to the stale `Capstone\capstone` copy — always serve from the real root.
- **Enable `pdo_sqlite`** so the repo's default `php artisan test` works (add `extension=pdo_sqlite`
  in `C:\laragon\bin\php\php-8.3.29-nts-Win32-vs16-x64\php.ini`). Not done yet — needs approval.
- **`Users` schema mismatch is now resolved:** the `users` table has the ERD `Role` attribute
  (`role` string, default `Staff`; admin backfilled to `Administrator`; ERD §2.1, no `Roles`
  table per §9.1). Role-based authorization has now arrived with FR-02 Users management
  (Session X): listing is auth-readable, Add/Edit/Delete abort 403 for non-Administrators.
- **Users management (FR-02) is done** — `UserController` + `users.*` routes + real view +
  16 tests; no new table (ERD §2.1 already implemented); self/last-admin/operational-record
  delete protections; `user_created/updated/deleted` activity rows. See Session X.
- **Batch management (FR-16) is done** — full CRUD at `/inventory/batches` (`BatchController`,
  `Batch::STATUSES`/`rules()`/`hasOperationalRecords()`, real view, 19 tests); no new
  table/migration (reuses the existing `batches` table). Creating a batch auto-creates its single
  `inventories` row and writes `batch_created`; updates resync while unused; delete is blocked once
  mortality/replanting/stock records exist. See Session Z.
- **Dashboard (PRD §12) is done** (Session AA) — `/` is now a real, `auth`-protected feature
  (`DashboardController@index`) with real KPI cards, three real ECharts and the real Recent
  Activities list, all reusing `App\Support\AnalyticsMetrics` + `ActivityLog` (RULE 5/6/12). No new
  table/migration (ERD has no Dashboard entity; PRD §9). The old mock KPIs/datasets are gone.
- **Handoff refreshed (Session AB)** — verification only: full suite **247/896** and
  Pint clean, and the live `db_capstone` snapshot matches §3 exactly. No code or DB change.
- **Handoff refreshed (Session AD)** — verification only: full suite **272/970**,
  Pint clean, 70 routes, all migrations Ran, and the live `db_capstone` snapshot matched §3
  (`notifications` 0, `settings` NULL, `activity_logs` 16) — historical, superseded by Session AF.
- **Daily Checklist archive/restore + printable PDF export is done (Session AE)** —
  Delete was replaced by **archive/restore** (approved ERD revision: nullable
  `Activity_Task.Archived_At`, ERD §2.4/§9.6) and a **printable FPDF** daily checklist was added
  (`App\Support\ChecklistPdf`, `GET /operational/checklist/export`). `DailyChecklistTest` 16/51;
  full suite was 280/1002 at the time. No new table; migration `2026_09_16_000003` applied to
  `db_capstone`.
- **All application pages now have backend** (Users, Suppliers, Clients, Varieties, Scions, Pot
  Types, Batches, Dashboard). **No static route closure remains in `routes/web.php`.**
- **Recommended next steps:** there is no remaining mockup page, route closure, or mock navbar
  control. The only optional/future work left is enabling `pdo_sqlite` so plain `php artisan test`
  works (see above). No OPEN DECISIONS remain.
- **Notifications + Settings were REMOVED (Session AF)** — the `notifications` table, `Notification`
  model/controller, navbar bell, `users.settings` JSON, `SettingsController`, and the
  `/notifications` + `/settings` pages/routes/tests were deleted; migration `2026_09_16_000004`
  dropped the table/column from `db_capstone`. Action feedback is now a **transient session-flash
  toast** (`resources/views/partials/toasts.blade.php` via `layouts/app.blade.php`, auto-shown in
  `resources/js/app.js`) — no table, no ERD entity. The former approved revision is recorded as
  **reversed** in ERD §9.5. See Session AF; the Session AC build below is historical only.
- No OPEN DECISIONS remain from AGENTS.md: the `Analytics` entity is now implemented (report
  history — ERD §9.4), `Inventory.Batch_ID` UNIQUE is honored (ERD §9.3), and the `Stock_Reversal`
  transaction FK is resolved (two nullable FKs — ERD §9.2). The notifications/Settings revision was
  reversed (toasts need no table — ERD §9.5).

## 7. Constraints to keep honoring
- Follow the ERD; do not invent/rename columns or tables without recording an explicit ERD revision.
  There are **no approved additive exceptions** — the short-lived `scion_receivings` exception
  (Session BV, migration `2026_09_26_000001`) was **merged into `stock_ins` in Session CC** and the
  table dropped; a scion receipt is a `stock_ins` row with `scion_variety_id` + `unit_price` set
  (`viable = quantity`). Do not reintroduce a separate scion-receiving table. Validate with built-in
  Laravel rules; do not add a table for validation.
- **Regraft is not a separate entity (resolved in Session BX):** `regraft_records` / `RegraftRecord`
  was **retracted** — it duplicated `Replanting_Record` and held 0 rows. It was dropped by migration
  `2026_09_26_000002_drop_regraft_records_table` (commit `22ef695`), the model/relation/import were
  removed, and the Stock Ledger's Regraft column now reads `replanting_records` (real **13 rows**,
  grand Re Graft **195**). Do not reintroduce a separate Regraft table or CRUD UI; record a Regraft as
  a `Replanting_Record`.
- Real data only — no hardcoded KPIs/chart values.
- Inventory/stock operations must be transaction-safe: Stock Out validates every line, writes the
  header + all detail lines and decrements `batches.available_count` inside one `DB::transaction`;
  Stock In is a standalone received-vs-viable record and no longer mutates inventory.
  `Inventory::applyDelta()` and Stock Reversal are gone with the revised model.
- Transactional stores validate at multiple layers (Form Request / JS, controller live-state checks,
  model guards) and are protected against duplicate submissions by
  `App\Support\DuplicateSubmissionGuard` (a session fingerprint — there is no DB idempotency constraint).
- Preserve working code; make the smallest change; test failure paths.
- Keep calculations (e.g. Crude Mortality Rate) centralized in one place.
