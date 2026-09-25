# Session Handoff — CacaoTrack

> ### ▶ Continue here (latest: Session BQ → next session)
> - **P0–P5 are DONE and committed.** Feature checkpoint `d139286` (P5 analytics/reports), docs
>   checkpoint `7cf3ca1` (AGENTS.md banner + this file). Working tree clean.
> - **NEXT = P6 (seeders/tests/docs/verify).** The analytics layer now matches the revised model and
>   `/` (Dashboard) returns **200**; the **seeders/tests still target the OLD 19-table model**, so the
>   feature suite fails until P6 rewrites them.
> - **Open decision #1 (needs your call):** the locked Gap formula
>   `max(0, viable_count − available_count − total_stock_out_for_batch)` currently resolves to **0**
>   for every batch, because `available_count` is already decremented by Stock Out. Say the word to
>   switch it to `viable_count − available_count` (the AGENTS P5 short form) instead.
> - **Flag #2:** Stock Ledger "Ready to Plant" maps to the batch's live `available_count` — a
>   best-guess interpretation pending client confirmation.
> - **Verify quickly:** `$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`
>   (never point tests at `db_capstone`). Full detail: §2 Session BQ, §5, §6.

Paste this file into a new chat to continue. It captures the exact state of the
project after the latest session (**Session BQ — Phase 5 (Analytics/reports) of the revised-PRD/ERD
refactor was IMPLEMENTED**): the **analytic/report layer was migrated onto the revised model** —
`App\Support\AnalyticsMetrics` was rewritten (no `Variety`/`adjustments`/`current_quantity`/
`inventory` references), the **Dashboard `/` returns 200**, and the **Stock Ledger is now a monthly
grid** with dynamic per-Scion-Variety columns in both the Blade view and the FPDF. One **approved
schema exception** landed: migration `2026_09_25_000009` created `regraft_records` +
`App\Models\RegraftRecord` + `Batch::regraftRecords()` (Part B). All seven analytics pages and all
seven PDF/CSV exports return **200**; `php artisan view:cache` is clean and Pint passes. **The
seeders/tests were NOT touched (P6).** P5 is committed as its own checkpoint (`d139286`, "feat(analytics):
implement P5 revised-model analytics, dashboard and stock ledger"). See the Session BQ entry in
§2/§5/§6. The refactor is now **P0–P5 DONE / P6 next**: the DB (P1), models (P2), controllers/routes
(P3), views/sidebar (P4) **and analytics/reports (P5)** match the revised model, but the
**seeders/tests still target the OLD model**, so the feature suite fails until P6 rewrites them.
Two P5 flags remain: the locked Gap formula currently resolves to 0 (see the Session BQ entry), and
Stock Ledger "Ready to Plant" is a flagged best-guess mapping. Before that, Session BN implemented Phase 3
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
- **▶ NEXT: revised-PRD/ERD refactor — P0/P1/P2/P3/P4/P5 DONE; implement P6 (Tests/docs/verify) next.**
  `db_capstone` has the revised schema (P1: eight reversible migrations
  `2026_09_25_000001..000008`; **P5 added the one approved exception `2026_09_25_000009`
  `regraft_records`** → 24 tables incl. framework), `app/Models` matches it (**P2 — Session BM**),
  `app/Http/Controllers` + `routes/web.php` match it (**P3 — Session BN, checkpoint `3d3f9f9`**;
  66 routes), the **views/sidebar match it** (**P4 — Session BO, committed `0803bdf`**) and the
  **analytics/report layer matches it** (**P5 — Session BQ, checkpoint `d139286`**; `AnalyticsMetrics`
  rewritten, Dashboard `/` returns **200**, Stock Ledger is a monthly grid, PDF/CSV rebuilt). **The
  seeders/tests still target the OLD model**, so the feature suite fails until **P6** rewrites them.
  Pre-P1 backup: `...\Temp\opencode\db_capstone_pre_p1_20260925_142909.sql`.
- **P5 (DONE — Session BQ, checkpoint `d139286`; see the Session BQ entry in §2/§5/§6):** fixed the two
  narrow `AnalyticsMetrics` bugs (`totalActiveInventory` → `Batch::totalAvailableQuantity()`; false
  PRD §12.2 citation removed) and removed every other stale `Variety`/`adjustments`/
  `current_quantity`/`inventory` reference; added the **approved `regraft_records`** migration +
  `RegraftRecord` + `Batch::regraftRecords()`; rebuilt the **Stock Ledger as a monthly grid** (dynamic
  per-Scion-Variety columns, view + FPDF); rebuilt remaining pages and PDF/CSV exports; Gap uses the
  locked formula. **Two flags:** the locked Gap formula currently resolves to **0** (available_count
  already nets Stock Out), and "Ready to Plant" is a flagged best-guess mapping. **No seeders/tests
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

## 2. Session log (oldest → newest; detailed entries end with Session BH, followed by the refactor entries BI (P0), BJ (P1), BK (P2 plan), BL (P3 plan), BM (P2 implemented), BN (P3 implemented), BO (P4 views/sidebar implemented), BP (housekeeping: commit P4 + correct the `AGENTS.md` banner) and BQ (P5 analytics/reports implemented))

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
column; Stock Ledger follows the Part C grid). **FLAG:** the locked Gap formula
`max(0, viable_count − available_count − total_stock_out_for_batch)` currently resolves to **0** for
every batch, because `available_count` is already decremented by Stock Out on save (so
`viable − available` ≈ stock-out and the extra subtraction cancels it) — this needs panel
confirmation before the Gap Report is relied on.

**Verification:** `php -l` clean; `php artisan view:cache` clean; `vendor\bin\pint` passed on all
changed files; migration applied (`migrate:status` 0 pending). A runtime smoke test
(auth as Administrator) returned **200** for `/`, `/analytics`, `/analytics/stock-ledger`,
`/analytics/mortality-matrix`, `/analytics/historical/seasonality`, `/analytics/historical/age-profile`,
`/analytics/historical/gap-report`, and for all seven exports (`/analytics/export`,
`/analytics/export/csv`, `/analytics/stock-ledger/export`, `/analytics/mortality-matrix/export`,
`/mortality-matrix/export/csv`, `/gap-report/export`, `/gap-report/export/csv`). **No test suite was
run** (deferred to P6 — the seeders/tests still target the OLD model).

## 3. Current database state (`db_capstone`)

**Schema changed in Session BQ (P5 — the one approved exception):** migration
`2026_09_25_000009_create_regraft_records_table` created the `regraft_records` table (0 rows — no
fabricated data). No other schema/data change: the analytics layer is read-only. The DB is now
**24 tables** (15 revised entities + `regraft_records` + framework).

**Live `db_capstone` re-read in Session BQ (real UI usage — no session fabricated rows):** `users` 5;
`scion_varieties` **8** (0 archived); `batches` **9** (0 archived); `stock_ins` **8** (quantity
**2250** / viable **2250**); `stock_outs` **7** with **7** details (released **2130**);
`mortality_records` **17** (quantity **580**); `replanting_records` **13** (sum **195**);
**`regraft_records` 0** (new table); `inventories` **9**; `suppliers` 5; `clients` 4; `analytics`
**12**; `activity_logs` **74**. Batch totals: `available_count` sum **1130**, `viable_count` sum
**1170**, `total_grafted` sum **1450**. Scion varieties in use include Batangas, Batangas-Scion,
Criollo, Diaman, Forastero, Trinitario, White Flag, tanom buh1.

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

## 5. Verification status
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
  locked Gap formula currently resolves to **0**; "Ready to Plant" is a best-guess mapping.
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
- Plain `php artisan test` / `composer test` will still fail until `pdo_sqlite` is enabled.
- The foundation tables (`varieties`, `scions`, `pot_types`, `batches`) have no dedicated model
  tests (they are pure schema + seed data) — but every Management page built on them now has a
  feature test (`BatchManagementTest` added in Session Z).

## 6. Open decisions / next steps
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
  returns **200**, Stock Ledger is a monthly grid, PDF/CSV rebuilt, plus the one approved
  `regraft_records` migration/model. **The seeders/tests (P6) are still the OLD model** — the feature
  suite still fails until P6 rewrites it (expected). (The `/activity` and user delete-guard flags were
  fixed in Session BO — see above.) Pre-P1 backup:
  `...\Temp\opencode\db_capstone_pre_p1_20260925_142909.sql`. **P5 flags:** locked Gap formula
  currently resolves to **0** (available_count already nets Stock Out); "Ready to Plant" is a
  best-guess mapping. **P2 delivered:** added
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
  **P5 (NEXT)** analytics/reports (Gap = `viable_count − available_count`); **P6** tests/docs/verify. **All open decisions are RESOLVED:** P1-era: (a)
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
  The **only** approved exception is `regraft_records` + `RegraftRecord` (P5 Part B); do not extend
  that pattern or add a Regraft CRUD UI without explicit approval.
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
