# AGENTS.md — CacaoTrack

## Where the project is
- The Laravel app root is this directory: `C:\laragon\www\Capstone\Capstone`. When your working
  directory is `C:\laragon\www\Capstone`, run every command from the `Capstone\` subfolder.
- A stale duplicate checkout exists at `Capstone\capstone\`. **Ignore it; never edit it.**
- A git repo now exists (Session BM: `git init` + initial snapshot commit `62a521a`). Commit
  checkpoints freely; do not force-push or rewrite history. The stale `capstone/` duplicate is
  gitignored.

## REFACTOR IN PROGRESS — READ THIS FIRST
- The source-of-truth PRD and ERD were **replaced with the revised 15-entity model**. Canonical
  `docs/CacaoTrack_PRD.md` and `docs/CacaoTrack_ERD.md` now contain the revised content; identical
  copies are retained for provenance at `docs/CacaoTrack_PRD_REVISED.md` /
  `docs/CacaoTrack_ERD_REVISED.md`. The **canonical files (no `_REVISED` suffix) are authoritative.**
- **P1 (schema), P2 (models), P3 (controllers/routes), P4 (views/sidebar) and P5 (analytics/reports)
  are DONE. The seeders/tests are NOT refactored yet (P6).** `db_capstone` uses the revised schema,
  `app/Models` matches it (Session BM), `app/Http/Controllers` + `routes/web.php` match it (Session
  BN, checkpoint `3d3f9f9`), the **views/sidebar match it** (Session BO, checkpoint `0803bdf`) and
  the **analytics/report layer matches it** (P5, checkpoint `d139286` — `AnalyticsMetrics`, the
  PDF/CSV classes and the Dashboard/Stock Ledger/Matrix/Historical pages all query the current
  models; `/` (Dashboard) returns **200**). **The seeders/tests still describe the OLD model**, so the
  test suite fails until **P6** rewrites them.
- Until P6 lands: **do not** reintroduce removed tables/columns or add new features without checking
  the plan. New schema/business-logic work must follow the revised ERD. The **only** approved schema
  exception is `regraft_records` + `RegraftRecord` (P5 Part B — see below); do not extend that pattern.
- **P5 approved schema exception:** migration `2026_09_25_000009` created `regraft_records`
  (mirrors `mortality_records`: `regraft_id`, `batch_id`, `user_id`, `quantity`, `regraft_date`,
  `remark`) plus `App\Models\RegraftRecord` and `Batch::regraftRecords()`. It records Regraft as a
  dated event per batch; `inventories.regraft` (the monthly aggregate) is **untouched**. There is
  deliberately **no** CRUD controller/route/view for it yet (the Stock Ledger reads it); a recording
  UI is a flagged follow-up.
- **P5 open flags:** (1) the **Gap formula was corrected** (post-P5 follow-up) to
  `Gap = Batch::effectiveMortalityQuantity()` (recorded mortality). The original
  `max(0, viable_count − available_count − total_stock_out_for_batch)` was algebraically wrong: it is
  a mathematical identity that always resolves to 0 regardless of the data, because `available_count`
  is already netted down by Stock Out on every validated release (`StockOutController` decrements it,
  and `StoreStockOutRequest` + the controller's per-line/per-batch checks enforce `quantity ≤
  available_count`). Mortality is the only quantity still unreflected in `available_count`, so it is
  the actual gap — how much of the live "available" balance may be overstated until recorded deaths
  are reconciled out of it. The view/PDF say so explicitly now.
  (2) Stock Ledger "Ready to Plant" maps to the batch's live `available_count` aligned to its
  `grafted_date` — a best-guess interpretation, pending client confirmation.
- Phase 0 (safety/docs) is DONE: `db_capstone` backed up, PRD/ERD replaced, this file updated.
- Phase 1 (migrations) is DONE — eight reversible migrations `2026_09_25_000001..000008` applied
  (create `scion_varieties` + `stock_out_details`; add `batches.scion_variety_id`; add
  `stock_ins.viable`/`origin`; migrate Stock Out to header + details; convert `inventories` to
  monthly snapshots; drop the six removed tables/columns). Validated up→down→up on a scratch copy.
  Pre-P1 backup: `...\Temp\opencode\db_capstone_pre_p1_<timestamp>.sql`.
- Phase 2 (models) is DONE — see "Locked refactor plan" below and `session.handoff.md` §2 Session BM.
  `app/Models` now matches the revised 15-entity schema. No migration was run.
- Phase 3 (controllers & routes) is DONE — Session BN, checkpoint `3d3f9f9`. `app/Http/Controllers`
  and `routes/web.php` now match the revised model; see `session.handoff.md` §2 Session BN for the
  route/permission map and the P3 flags. No migration, model, view, seeder or test file was touched.
- **Do not invent, rename, or add tables, columns, FKs, or relationships** beyond the revised ERD.
  The single exception is `regraft_records` (P5 Part B), which is explicitly approved and documented
  in the banner above.

## Source-of-truth docs (read before schema or business-logic changes)
- `docs/CacaoTrack_PRD.md` — revised PRD: what the system must do (FR-01…FR-13, validation,
  transaction integrity, analytics, dashboard, data-consistency rules).
- `docs/CacaoTrack_ERD.md` — revised ERD: the **15-entity** data model (snake_case PK/FK map). This
  is the database source of truth.
- `docs/CacaoTrack_Development_Rules.md` — 14 mandatory rules (validate everything, real data only,
  transaction-safe inventory, centralized formulas, stop instead of guessing).

## Revised data model (15 entities — the TARGET)
| # | Entity | PK | Key FKs / notable columns |
|---|---|---|---|
| 1 | `Users` | `user_id` | `role` (string), `permissions` (JSON), `full_name`, `email`, `password`, `created_at` |
| 2 | `Activity_Log` | `activity_id` | `user_id`, `batch_id` (nullable), `activity_type`, `description`, `activity_date` |
| 3 | `Analytics` | `analytics_id` | `user_id`, `analysis_type`, `analysis_period`, `result_summary`, `generated_at` |
| 4 | `Activity_Task` | `task_id` | `task_name`, `description`, `priority`, `archived_at` (nullable) |
| 5 | `Task_Complete` | `completion_id` | `task_id`, `user_id`, `verified_by_id`, `completion_date`, `verified_at`, `remark` |
| 6 | `Supplier` | `supplier_id` | `supplier_name`, `contact_number`, `address`, `archived_at` (nullable) |
| 7 | `Stock_In` | `stock_in_id` | `supplier_id`, `user_id`, `quantity`, **`viable`**, **`origin`**, `date_received` |
| 8 | `Batch` | `batch_id` | **`scion_variety_id` (nullable)**, `user_id`, `batch_code`, `total_grafted`, `viable_count`, `available_count`, `grafted_date`, `created_at`, `status`, `archived_at` (nullable) |
| 9 | `Scion_Variety` | `scion_variety_id` | `scion_variety_name`, `description`, `archived_at` (nullable) |
| 10 | `Mortality_Record` | `mortality_id` | `batch_id`, `user_id`, `quantity`, `cause`, `recorded_at` |
| 11 | `Replanting_Record` | `replanting_id` | `batch_id`, `user_id`, `quantity`, `replanting_date`, `remark` |
| 12 | `Inventory` | `inventory_id` | `batch_id`, `inventory_date`, `total_grafted`, `viable`, `regraft`, `mortality`, `status`, `last_update` |
| 13 | `Client` | `client_id` | `client_name`, `contact_number`, `address`, `archived_at` (nullable) |
| 14 | `Stock_Out` | `stock_out_id` | `client_id`, `user_id`, `release_date`, `total_amount` (nullable) — header only |
| 15 | `Stock_Out_Detail` | `stock_out_detail_id` | `stock_out_id`, `batch_id`, `scion_variety_id`, `quantity`, `unit`, `unit_price` (nullable), `amount` (nullable) |

**Key revised rules:** `Stock_In.quantity` is the ordered/received quantity and `Stock_In.viable` is
the five-day recounted usable quantity (they need not be equal); Stock In has **no** `batch_id`.
`Batch` has **no** `variety_id`, `pot_id`, or `monitoring_start_date`. `Inventory` is a **monthly
snapshot** (many rows per batch — the old per-batch UNIQUE `current_quantity` row is gone).
`Stock_Out` is a header; each variety/batch quantity is a `Stock_Out_Detail` line. `Stock_In` has
**no** `scion_quantity` and there is no scion stock pool. **P5 approved exception:**
`Regraft_Record` (`regraft_records`) is an additional dated Regraft-event table — the single approved
table beyond these 15 (see the banner). `inventories.regraft` (monthly aggregate) is unchanged.

## Removed from the model/process (do NOT reintroduce)
- Tables: `Batch_Monitoring`, `Variety`, generic `Scions` stock pool, `Pot_Type`,
  `Mortality_Adjustments`, `Stock_Reversal`.
- Columns: `Batch.Monitoring_Start_Date`, `Stock_In.Batch_ID`, `Stock_In.Scion_Quantity`.
- Concepts: scion stock receiving/used/unused inventory logic, Batch Monitoring
  status/history logic, the Stock Reversal workflow, separate per-scion inventory, and the
  `varieties`/`pot_types` master modules.
- **`Scion Variety`** is the single retained term/module for scion-variety information (it replaces
  both the old `Variety` and the generic `Scions` concepts).

## Revised navigation (sidebar)
- **Dashboard**
- **Operational:** Daily Checklist, Mortality Tracking, Replanting, Batches, Scion Variety
- **Inventory:** Stock In, Stock Out, Inventory
- **Management:** Supplier, Client
- **Analytics:** Operational Analytics, Tabular Deliverables, Stock Ledger, Root Cause Matrix
- **Historical:** Seasonality Heatmap, Age Profile, Gap Report
- **Account:** Activity History, User Management, Profile, Logout
- Removed nav items: Batch Monitoring, Variety, Stock Reversal, Scion, Pot Type.

## Locked refactor plan (Session BH — P0–P5 done, P6 pending)
- **P0 Safety/docs (DONE):** back up `db_capstone`; replace PRD/ERD with the revised content;
  update `AGENTS.md`. Backup: `...\Temp\opencode\db_capstone_pre_revised_refactor_20260925.sql`.
- **P1 Migrations (DONE):** created `scion_varieties` + `stock_out_details`; altered `batches`,
  `stock_ins`, `stock_outs`, `inventories`; migrated/repointed data; dropped the six removed
  tables/columns. Eight migrations `2026_09_25_000001..000008`; validated reversible.
- **P2 Models (DONE — Session BM):** added `ScionVariety`, `StockOutDetail`; reworked
  `Batch`/`StockIn`/`StockOut`/`Inventory`/`MortalityRecord` (replanting unchanged); deleted
  `BatchMonitoring`, `Variety`, `PotType`, `Scion`, `MortalityAdjustment`, `StockReversal`,
  `ScionMetrics`; fixed `Analytic::rules()` + `ActivityLog::TYPES`. Model-layer rules implemented:
  mortality **never** mutates `viable_count` (cap = `max(0, viable_count − effectiveMortalityQuantity())`);
  `Inventory::statusForAvailable()`/`statusForBatch()` + a `creating` hook **freeze** the snapshot
  status; `StockOutDetail` derives `scion_variety_id` from the batch in a `saving` hook; `StockIn`
  enforces `viable <= quantity` via `rules()` + a `saving` guard. **Flag:** the DB has **no** unique
  index enforcing one Inventory snapshot per batch per calendar month (`2026_09_25_000007` added only
  `index('batch_id')`) — P3's "Record Monthly Inventory" (`InventoryController@store`) enforces it at
  the app layer; a future migration is still needed for DB enforcement.
- **P3 Controllers/routes (DONE — Session BN, checkpoint `3d3f9f9`):** deleted
  `BatchMonitoringController`/`VarietyController`/`PotTypeController`/`StockReversalController`/
  `ScionController` + `Mortality@adjust`; added `ScionVarietyController`
  (`/operational/scion-varieties`, `manage_inventory`); multi-line `StockOutController` (header +
  `details[]`, per-line + per-batch aggregate checks vs `available_count`, scion variety derived
  server-side, `amount`/`total_amount` computed); reworked StockIn/Inventory/Batch/Mortality/
  Replanting; Batches moved to `/operational/batches` (`record_operations`); added Form Requests for
  every transactional store/update + `App\Support\DuplicateSubmissionGuard`. **Flags:** inventory
  snapshot uniqueness is app-layer only (no DB unique index); snapshots write no Activity_Log row (no
  `inventory_recorded` type; model edit out of scope); snapshot permission = `manage_inventory`.
- **P4 Views/sidebar (DONE — Session BO, checkpoint `0803bdf`):** sidebar repointed to the P3 routes
  and the dead modules removed; `resources/views/operational/scion-varieties/index.blade.php` added;
  Batches moved to `operational/batches/`; Stock In / Stock Out / Inventory / Mortality reworked;
  view folders for the removed modules deleted; `ActivityController@index` (`batch.scionVariety`) and
  `UserController::hasOperationalRecords()` follow-up fixes applied. Remaining flag: `/` (Dashboard)
  500s (`AnalyticsMetrics` OLD — P5).
- **P5 Analytics/reports (DONE — checkpoint `d139286`):** `AnalyticsMetrics` rewritten onto the current
  models (no `Variety`/`adjustments`/`current_quantity`/`inventory` references); `totalActiveInventory`
  = `Batch::totalAvailableQuantity()`; the false PRD §12.2 citation removed; inventory grouped by
  **Scion Variety**; survival vs mortality no longer double-counts; mortality matrix is
  adjustment-free. The Stock Ledger is now a **monthly grid** with dynamic per-Scion-Variety columns
  (view + FPDF). PDF/CSV exports rebuilt; Gap corrected to `Batch::effectiveMortalityQuantity()` (see the
P5 open-flags note above).
  The one approved schema exception is `regraft_records` + `RegraftRecord` + `Batch::regraftRecords()`.
  `/` (Dashboard) returns **200**. See `session.handoff.md` §2 Session BQ.
- **P6 Tests/docs/verify (NEXT):** rewrite affected tests; run MySQL suite + Pint + `view:cache` +
  `migrate:status` + live spot-checks; update handoff and this file.

## Locked decisions (user-approved)
1. Backup → migrate data → drop removed tables/columns.
2. `varieties` rows become `scion_varieties`; batches are repointed `variety_id → scion_variety_id`.
3. `batches.available_count` is the **live** available balance; `inventories` becomes **monthly
   snapshots** (many per batch).
4. Stock Out availability is checked **per detail line** (batch + scion_variety) against
   `batch.available_count`.
5. The **revised ERD wins over PRD prose** — no `mortality.plant_condition`, no
   `replanting.scion_variety_id`.
6. Replace the existing PRD/ERD docs with the revised content; update `AGENTS.md`.

## Data-migration notes / accepted loss (P1 — DONE)
- 1 `mortality_adjustments` row (−5) and 1 `stock_reversals` row were retired per the revised ERD
  (effective mortality becomes raw `Mortality_Record.quantity`).
- The 8 `stock_ins` got `viable` backfilled from `quantity`; `origin` backfilled to `''`.
- The live `varieties` (8 rows) were copied to `scion_varieties` and every `batches.variety_id`
  repointed to `scion_variety_id` (0 null).
- `batches.available_count` was backfilled from `inventories.current_quantity` before the column was
  dropped — **live sum 1130** (the earlier "1035" note was a stale Session-BH snapshot).
- The 7 live `stock_outs` became 7 `stock_out_details` lines (`unit = seedlings`; `amount` = the old
  header `total_amount`); the 9 `inventories` rows became monthly snapshots seeded from real
  Batch/Mortality/Replanting data.
- **Rollback note:** reverting P1 restores the schema and repopulates `varieties` + the Batch
  `variety_id`/`monitoring_start_date`; the six retired tables return **empty** and the restored
  `batches.scion_id`/`pot_id` are nullable (their rows are accepted loss).
- A `db_capstone` backup exists before P1 (`db_capstone` is the live DB — never point tests at it).

## OPEN DECISIONS — RESOLVED (before P1)
- (a) Monthly `inventories` rows are written by an explicit **"Record Monthly Inventory"** action
  (stored snapshots), **not** auto-derived at query time. Built in P3 (`InventoryController@store`).
- (b) `stock_out_details.unit` is a **fixed list**: `seedlings`, `pieces`, `bags` (centralized
  constant `StockOutDetail::UNITS` in P2; validated in P3). Legacy migrated lines default to
  `seedlings`.
- (c) `stock_ins.origin` is **NOT NULL**; the 8 legacy rows were backfilled to `''` and P3 enforces a
  non-empty origin for new records (`StoreStockInRequest`). No other P1 decision remains.

## Current code state (DB + models + controllers + views + analytics = NEW (P1–P5 done); seeders/tests = OLD — P6 migrates them)
`db_capstone` (P1), `app/Models` (P2 — Session BM), `app/Http/Controllers` + `routes/web.php`
(P3 — Session BN, checkpoint `3d3f9f9`), the **view layer (Blade + sidebar)** (P4 — Session BO,
checkpoint `0803bdf`) and the **analytics/report layer** (P5 — checkpoint `d139286`:
`AnalyticsMetrics`, PDF/CSV classes, Dashboard, Stock Ledger monthly grid, Matrix, Historical)
all match the revised model. **The seeders/tests still implement the previous 19-table model**, so the
test suite fails until P6 rewrites them. `/` (Dashboard) returns **200**. The mapping below records
the P1–P3 schema transition (now complete); P6 rewrites tests + docs. (For full historical detail see
`session.handoff.md` §2.)

| Revised entity | Current state | Action |
|---|---|---|
| Users, Activity_Log, Analytics, Activity_Task, Task_Complete, Supplier, Client, Mortality_Record, Replanting_Record | exist | **keep** |
| `Scion_Variety` | split into `varieties` + generic `scions` | **new table**, replaces both |
| `Batch` | `variety_id` + `scion_id` + `pot_id` + `monitoring_start_date` | drop variety/pot/monitoring; `scion_id` → nullable `scion_variety_id` |
| `Stock_In` | `batch_id`, `scion_quantity` | drop both; add `viable`, `origin` |
| `Inventory` | `current_quantity` + UNIQUE batch | monthly snapshot: `inventory_date, total_grafted, viable, regraft, mortality, status, last_update` |
| `Stock_Out` | `batch_id`, `quantity` | drop both; header only |
| `Stock_Out_Detail` | — | **new table** |
| Removed | `batch_monitorings`, `varieties`, `pot_types`, `scions`, `mortality_adjustments`, `stock_reversals` | **drop** |

## Stack
- Laravel 13 / PHP ^8.3. Database is **MySQL** `db_capstone` (Laragon, user `root`, empty password)
  per `.env`; `.env.example` defaults to SQLite but the running app uses MySQL. The dedicated test DB
  is `db_capstone_test`.
- Frontend is fixed: Blade + Bootstrap 5 + Bootstrap Icons + ECharts, bundled by Vite. Bootstrap
  CSS/JS is imported in `resources/css/app.css` / `resources/js/app.js`; ECharts is global
  `window.echarts`. Tailwind is in `package.json` but unused — use Bootstrap classes. Do not migrate
  to an SPA (PRD §4).
- PDFs use `setasign/fpdf` (classic `FPDF` global class via classmap; requires `ext-zlib` +
  `ext-gd`). Keep report PDF classes under `app/Support/` (e.g. `App\Support\ChecklistPdf`).

## Commands (run from the app root)
- First-time setup: `composer setup` (install, `.env`, key, migrate, npm install + build).
- Dev: `composer dev` (runs `php artisan dev`) and/or `npm run dev` (Vite dev server).
- Build assets: `npm run build`.
- Tests: `composer test` (clears config first) or `php artisan test`. Single test:
  `php artisan test --filter=ExampleTest` or `vendor/bin/phpunit --filter=...`. NOTE: `pdo_sqlite`
  is disabled in this environment, so the `phpunit.xml` SQLite `:memory:` default fails with
  "could not find driver". Run the suite against the dedicated MySQL test DB instead:
  `$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='db_capstone_test'; php artisan test`. Never point
  tests at `db_capstone` (RefreshDatabase wipes the database).
- Format: `vendor/bin/pint` (Laravel Pint, default config).
- Database: `php artisan migrate`, `php artisan tinker`, etc.

## Access restrictions (per-user — do not bypass)
- Role + access restrictions live **on the user**: `users.role` (string) and `users.permissions`
  (JSON list of catalog keys). There is **no `roles` table, no `role_permissions` table, and no
  `users.role_id`** (approved denormalization — revised ERD `Users`). Do not reintroduce them.
- The permission catalog is `App\Models\Role::PERMISSIONS` (single source of truth — RULE 6): the
  keys are `manage_users`, `manage_inventory`, `manage_stock`, `record_operations`,
  `view_analytics`, `generate_reports`, `view_activity`. Do not invent permission keys.
  `App\Models\Role` is a **code-only catalog helper** (no Eloquent model, no table).
- `Administrator` is the protected role: it always holds `manage_users` (re-applied server-side on
  save), and the last administrator account cannot be demoted or deleted; every user must keep ≥1
  permission.
- Enforcement is **server-side** via `App\Http\Middleware\EnsurePermission` (alias `permission`) on
  the route groups, and the sidebar renders links per permission. Never rely on hiding UI alone.
- New accounts default to `Role::defaultPermissionsFor()` (Administrator = all 7; every other role =
  all except `manage_users`); existing users kept the permissions they held at merge time. An admin
  can set permissions per user on the User form.

## Toast notifications (no table — do not "fix" back)
- There is **no `Notifications` entity** and no `users.settings` column. User feedback is a
  **transient Bootstrap toast** rendered from `session('success')` / `session('error')` flashes by
  `resources/views/partials/toasts.blade.php`, included globally in `layouts/app.blade.php` and
  auto-shown by `resources/js/app.js` (`bootstrap.Toast`). Ephemeral UI state — no table, no
  migration (revised PRD §15, RULE 4).
- The authoritative record of the same actions remains `Activity_Log` through the single centralized
  writer `ActivityLog::record()` (RULE 6).

## Conventions
- Views `@extends('layouts.app')` and fill `@section('title'|'page_title'|'page_actions'|'content')`;
  push page JavaScript with `@push('scripts')`.
- `.npmrc` sets `ignore-scripts=true`, so npm postinstall scripts do not run.
- `.env` is gitignored — never commit secrets.
- Keep each calculation (e.g. Crude Mortality Rate, Gap) defined in one place and shared by dashboard,
  reports, and analytics. All dashboard/report/chart values must come from real DB data; never
  hardcode KPIs or fabricate chart datasets (RULE 5/6; revised PRD §16).
- Transaction-safe quantity changes: validate → authorize → lock/read the applicable balance →
  apply the delta → record Activity History → commit; roll back on any failure (revised PRD §8).
