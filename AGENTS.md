# AGENTS.md — CacaoTrack

## Where the project is
- The Laravel app root is this directory: `C:\laragon\www\Capstone\Capstone`. When your working
  directory is `C:\laragon\www\Capstone`, run every command from the `Capstone\` subfolder.
- A stale duplicate checkout exists at `Capstone\capstone\`. **Ignore it; never edit it.**
- A git repo now exists (Session BM: `git init` + initial snapshot commit `62a521a`). Commit
  checkpoints freely; do not force-push or rewrite history. The stale `capstone/` duplicate is
  gitignored.

## RENAME — `Replanting_Record` → `Regraft_Record` (2026-09-30)
- **Owner-directed, single coordinated rename** (the `replanting_records` table, PK/date columns,
  model, relations, controller, request, route, views, factory, seeders, analytics and tests were all
  renamed to `regraft`). It makes the internal entity name match the company term used throughout the
  UI ("Regraft").
- **This intentionally diverges from the client-approved v1.0 baseline.** The `_UPDATED` docs
  (untouched provenance) and the earlier canonical docs named the entity `Replanting_Record` and the
  rule said "do not rename beyond the revised ERD". The canonical `docs/CacaoTrack_PRD.md` +
  `docs/CacaoTrack_ERD.md` (+ their byte-identical `_REVISED` copies) were updated in the same change
  to record the rename; `_UPDATED` was **not** edited.
- **Schema:** table `replanting_records` → `regraft_records`; PK `replanting_id` → `regraft_id`;
  date `replanting_date` → `regraft_date`; stored `activity_logs.activity_type`
  `replanting_recorded` → `regraft_recorded`. Migration
  `2026_09_30_000001_rename_replanting_records_to_regraft_records_table` (reversible). The historical
  P5 `create/drop_regraft_records_table` migrations (`2026_09_25_000009` / `2026_09_26_000002`) are
  left intact as history; by the time the rename runs, that table no longer exists.
- **Code:** `App\Models\RegraftRecord`, `Batch::regraftRecords()` / `regraftRecordsAsParent()` /
  `totalRegrafted()` / `regraftDeficit()`, `AnalyticsMetrics::totalRegraftDeficit()` /
  `regraftDeficitByBatch()`, `App\Actions\RegraftBatch` (param `$regraftDate`), `RegraftController`,
  `StoreRegraftRecordRequest` (error bag `regraft`), route `operational.regraft` (`/operational/regraft`),
  view `operational.regraft.index`, `RegraftRecordFactory`, `ActivityLog::TYPE_REGRAFT_RECORDED`.
  Error-bag/field names are `regraft` / `regraft_date`.
- **✅ `db_capstone` WAS migrated** (2026-09-30) — the owner explicitly signed off this one live-DB
  schema action. Fresh verified backup first:
  `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_regraft_rename_20260930.sql`
  (36,826 B, left untouched). `migrate:status` **0 Pending**; live `regraft_records` verified
  (2 rows, Σqty 130; `regraft_id`/`regraft_date`/`parent_batch_id` present; `activity_logs` migrated
  to `regraft_recorded`); read-only smoke test — `/`, `/operational/regraft`, `/operational/batches`,
  `/inventory/stock-in`, `/analytics`, `/analytics/stock-ledger` all **200**. Tests still run only
  against `db_capstone_test`; any further live-DB schema action needs fresh sign-off.

## CONFIRMED — `total_grafted` is a cumulative, non-decreasing log (2026-09-29)
- **Client-CONFIRMED, not a pending `OD-n`.** Evidence: the client's `RU-Summary.jpg` spreadsheet
  records a regraft as a **new Grafted entry plus a Re Graft entry on the same row** (row 4, 10/2025,
  "re graft"); the client stated directly: *"Grafted minus Regraft = mortality or yield for the
  parent batch."*
- **Rule:** a batch's `total_grafted` is a **cumulative, non-decreasing log**; a regraft **never**
  reduces it. Only the parent's live `available_count` drops (by `q`) and its `regraft` counter rises
  (by `q`). The new sub-batch records `total_grafted = available_count = q`. A batch's
  **still-attributable / live population** for mortality and regraft purposes is
  **`total_grafted − regraft`**.
- **Code:**
  - `App\Actions\RegraftBatch::execute()` no longer writes `source.total_grafted`; it only does
    `available_count -= q` and `regraft += q`.
  - `Batch::remainingMortalityCapacity() = max(0, (total_grafted − regraft) − effectiveMortalityQuantity())`.
  - `Batch::survivingQuantity() = max(0, min(available_count, (total_grafted − regraft) − effectiveMortalityQuantity()))`
    — renamed from `regraftableQuantity()` in Session CT; it is now the shared cap for **both** Regraft
    and Stock Out (the Stock Out gate was previously raw `available_count`).
  - `AnalyticsMetrics::mortalityRateComponents()` denominator =
    `SUM(total_grafted − regraft)` (and `survivalVsMortality()` uses the same live-population basis).
- **Docs:** `docs/CacaoTrack_PRD.md` §2.4 / §10 / §11 (new "Total Grafted" subsection) / §19 — the
  Total_Grafted clauses of `OD-4`, `OD-6` and `OD-10` are now **CONFIRMED**; `docs/CacaoTrack_ERD.md`
  §2 rule 7 / §3.8 / §3.11. The `_REVISED` copies are kept byte-identical (SHA-256 match).
- **Tests:** full MySQL suite **329 passed / 1427 assertions** against `db_capstone_test` (was
  328/1406). New `RegraftFlowTest::test_caps_are_computed_against_the_remaining_population_not_the_raw_total`;
  the Phase 7C family-conservation assertions were rewritten (raw family sum is **not** conserved any
  more; `total_grafted − regraft` is). Session CK (follow-up) corrected the mortality-rate denominator
  and updated `AnalyticsTest` / `SeededFamilyKpiTest` to the live-population basis.
- **Seeded family under the corrected rule (fresh `db_capstone_test` seed):** B001 500/400/100,
  B001-R1 100/50/30, B001-R1-R1 30/30/0, B002 150/150/0, B003 100/100/0 → raw `Σtotal_grafted` **880**,
  KPI denominator `Σ(total_grafted − regraft)` **750**, `Σavailable` **730**, `Σregraft` **130**;
  Crude Mortality Rate 55/750 = **7.33%**; stock-ledger *Grafted* (root batches only) **750**.
- **✅ `db_capstone` WAS reseeded under the corrected rule** (2026-09-30, owner-approved one-time
  destructive action — see "RESEED EXECUTED" below). It now holds the corrected seed
  (B001 500/400/100, B001-R1 100/50/30, B001-R1-R1 30/30/0, B002 150/150/0, B003 100/100/0), so every
  live row satisfies `available_count ≤ total_grafted − regraft`; `php artisan cacao:health` passes.
  Tests still run only against `db_capstone_test`.

## RESEED EXECUTED — live `db_capstone` realigned to the corrected `total_grafted` rule (2026-09-30)
- **One explicitly approved destructive action (project-owner sign-off), connection `mysql` / database
  `db_capstone`.** The standing rule is unchanged: tests run **only** against `db_capstone_test`; any
  further destructive or schema action on the live DB needs fresh sign-off.
- **Backup first (verified, left untouched outside the git repo):**
  `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_reseed_20260930_034319.sql`
  — **37,510 B**, **23 `CREATE TABLE`** (byte-for-byte the same 23 tables as the live DB), dumped with
  `--single-transaction --routines --triggers --events`, includes `CREATE DATABASE`/`USE db_capstone`,
  clean `-- Dump completed on 2026-09-30  3:43:19` trailer.
- **Before (raw):** `migrate:status` 53 Ran / **0 Pending**; **`cacao:health` FAILED (exit 1)** — 2
  violations of `available_count ≤ total_grafted − regraft` (B001 expected 300 actual 400; B001-R1
  expected 40 actual 50) — i.e. the stale Phase-7D arithmetic. This was the first real-world run of
  `cacao:health` against actually-stale data, and it caught the expected violations.
- **Run:** `php artisan migrate:fresh --seed --force` against `db_capstone` — all **53** migrations ran,
  `DatabaseSeeder` + `ActivityLogSeeder` seeded, exit code **0**.
- **After (raw):** `migrate:status` 53 Ran / **0 Pending**; **`cacao:health` PASSED (exit 0)**
  (`Cacao health check passed. 5 batch(es) satisfy all invariants.`).
- **Row counts before → after (`db_capstone`):** batches 5 → 5, stock_ins 4 → 4, stock_outs 1 → 1,
  stock_out_details 1 → 1, mortality_records 8 → 4, regraft_records 2 → 2, inventories 5 → 5,
  scion_varieties 5 → 5, suppliers 3 → 3, clients 3 → 3, users 3 → 1, activity_logs 17 → 11,
  analytics 0 → 0, sessions 2 → 0; all other tables 0.
- **Seeded family (verified live):** B001 Batangas 500/400/100 · B001-R1 Toledo 100/50/30 ·
  B001-R1-R1 Criollo 30/30/0 · B002 Trinitario 150/150/0 · B003 Forastero 100/100/0. Aggregates:
  `Σtotal_grafted` **880**, live population `Σ(total_grafted − regraft)` **750**, `Σavailable` **730**,
  `Σregraft` **130**; **0** invariant violations (`available > total_grafted − regraft` = 0,
  negative available = 0, `batches.regraft` counter vs `regraft_records` = 0).
- **KPIs (centralized `AnalyticsMetrics`):** Total Active Inventory **730**, Crude Mortality Rate
  **7.33%** (numerator 55 / denominator 750), Regraft Deficit **25**, Gap total **55**.
- **End-to-end (read-only, seeded administrator via the HTTP kernel):** all **20 sidebar GET pages
  return 200**; the Stock Out page's rendered `data-available` hint equals `survivingQuantity()` for
  every batch (B001 **375** vs raw `available_count` 400; B002 135 vs 150; B003 90 vs 100);
  `GET /analytics/stock-ledger/export/csv` returns **200** (`text/csv`).
- **This removes the last live-DB divergence:** live `db_capstone` now satisfies the client-confirmed
  cumulative `total_grafted` rule and `cacao:health` is clean.

## PHASE 7D DONE — live `db_capstone` upgraded to the approved schema + reseeded (2026-09-29)
- **One approved destructive action — EXPLICITLY signed off by the project owner for this run only.
  Connection `mysql`, database `db_capstone`.** The standing rule is unchanged: tests run **only**
  against `db_capstone_test`; any further destructive action on the live DB needs fresh sign-off.
- **Backup first (verified, left untouched outside the git repo):**
  `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql`
  — **35,131 B**, 23 `CREATE TABLE` + 15 `INSERT INTO`, `--single-transaction`, includes
  `CREATE DATABASE`/`USE db_capstone`, clean `-- Dump completed on 2026-09-29 0:02:07` trailer.
  **The backup file was NOT deleted or modified.**
- **Run:** `php artisan migrate:fresh --seed --force` against `db_capstone` (fresh because legacy
  replanting rows cannot become sub-batches without inventing data). All **52** migrations ran,
  including the four `2026_09_27_*`; `DatabaseSeeder` + `ActivityLogSeeder` seeded.
- **Before → after row counts (`db_capstone`):** batches 3 → 5, stock_ins 6 → 4, stock_outs 1 → 1,
  stock_out_details 1 → 1, mortality_records 4 → 4, regraft_records 5 → 2, inventories 3 → 5,
  users 1 → 1, scion_varieties 3 → 5, suppliers 3 → 3, clients 3 → 3.
- **Schema verified live (`Schema::hasColumn`):** `batches.viable_count` **false**,
  `stock_ins.viable` **false**, `inventories.viable` **false**, `batches.regraft` **true**,
  `batches.parent_batch_id` **true**, `regraft_records.parent_batch_id` **true**;
  `migrate:status` **0 Pending**.
- **Invariants verified live:** `available_count > total_grafted` = **0**; `available_count < 0` =
  **0**; `batches.regraft == SUM(regraft_records.quantity by parent_batch_id)` mismatches = **0**;
  B001 family `total_grafted` = **500**.
- **Seeded family:** B001 Batangas (total 400 / available 400, regraft 100, mortality 25) →
  B001-R1 Toledo (70 / 50, regraft 30, mortality 5) → B001-R1-R1 Criollo (30 / 30, regraft 0,
  mortality 0); plus B002 Trinitario (150 / 150, mortality 15) and B003 Forastero (100 / 100,
  mortality 10).
- **End-to-end (read-only, as the seeded administrator through the HTTP kernel):** all **20 sidebar
  GET pages return 200**; KPIs — Total Active Inventory **730**, Crude Mortality Rate **7.33%**,
  Regraft Deficit **25**, Gap total **55**; Stock Ledger CSV (`/analytics/stock-ledger/export/csv`)
  returns **200**. No data was created in `db_capstone` during verification.
- **`db_capstone` is now on the approved (Phase 7A) schema** — the four `2026_09_27_*` migrations are
  applied and `batches.viable_count`, `stock_ins.viable` and `inventories.viable` are gone.

## PHASE 7A DONE — viability count removed; schema aligned with the client-approved ERD (2026-09-28)
- **Baseline = the client-approved v1.0 docs** `docs/CacaoTrack_PRD_UPDATED.md` /
  `docs/CacaoTrack_ERD_UPDATED.md`. Only the two provisional defaults below were implemented from the
  v2.0 `[PROPOSED]` set; **all other `OD-n` items remain unimplemented**. No PRD/ERD file was edited.
- **Schema (new reversible migrations `2026_09_27_000001..000004`, validated up → down → up on
  `db_capstone_test` only):** `batches` **drops `viable_count`** and **adds `regraft`** (unsigned int,
  default 0) + nullable **`parent_batch_id`** (self-FK → `batches.batch_id`, restrictOnDelete,
  indexed); `stock_ins` **drops `viable`**; `inventories` **drops `viable`**; `regraft_records`
  **adds nullable `parent_batch_id`** (ERD §3.11). There is no five-day seedling viability recount.
- **Code:** `Batch` gained `parent()` / `subBatches()` / `isSubBatch()`; Stock In / Batches /
  Inventory models, requests, controllers and views no longer reference viable (Stock In's card is
  now **"Total Received (seedlings)"**, summing `quantity` only where `scion_variety_id` is NULL).
  Analytics: `survivalVsMortality` uses `total_grafted` and the Gap Report's `expected` is
  `total_grafted` (the Gap formula is still effective mortality). Factories, seeders and all 16
  affected tests were updated; full MySQL suite **280 passed / 1173 assertions**, Pint + `php -l`
  clean.
- **Two provisional defaults (recorded as provisional — pending client sign-off):**
  1. **`OD-3`** — a new batch's `available_count = total_grafted` (`BatchController@store`, and the
     update path only while the batch has no operational records).
  2. **`OD-4`** — `Batch::remainingMortalityCapacity() =
     max(0, (total_grafted − regraft) − effective mortality)` (**CONFIRMED 2026-09-29** — see the
     "CONFIRMED" section at the top). Mortality still does **not** change `available_count` or the
     `regraft` counter.
- **`db_capstone` was upgraded to this schema + reseeded in Phase 7D (2026-09-29)** — the four
  `2026_09_27_*` migrations are applied and the live DB no longer has `batches.viable_count`,
  `stock_ins.viable` or `inventories.viable`. (When Phase 7A landed the live DB was deliberately left
  on the old schema; that step was executed later — see "PHASE 7D DONE" at the top.)
- **Out of scope this phase (unchanged):** the regraft/sub-batch flow (`ReplantingController`,
  Phase 7B), Stock Ledger nesting (7C), `Plant_Condition`, `Sub_Batch_ID`, and any correction/edit
  policy.

## PHASE 7B DONE — regraft / sub-batch flow (2026-09-28)
- **Baseline = the client-approved docs** `docs/CacaoTrack_PRD_UPDATED.md` (§2.4, §8 "Regrafting /
  Sub-Batch Creation") / `docs/CacaoTrack_ERD_UPDATED.md` (§2 rules 5-8, §3.8, §3.10, §3.11).
  **No PRD/ERD file was edited.** Only the provisional defaults below implement behavior the v2.0
  `[PROPOSED]` set leaves open; each is flagged `provisional — pending PRD OD-n` in the code.
- **One action — `App\Actions\RegraftBatch`** is the single place the regraft steps live (RULE 6);
  the controller, the seeders and the tests all call `execute()`. One DB transaction: (1) lock the
  source `batches` row, (2) re-check not-archived + quantity, (3) form-request validates the shape;
  action re-checks not-archived source, `1 ≤ q ≤ survivingQuantity()` (then called
  `regraftableQuantity()`), active scion variety, date
  not before the source's `grafted_date`; (4) source `available_count -= q`, `regraft += q`
  (`total_grafted` is a cumulative, non-decreasing log and is **NOT** reduced — CONFIRMED
  2026-09-29); (5) create the sub-batch (`parent_batch_id = source`, `batch_code =
  nextSubBatchCode()`, `total_grafted = available_count = q`, `regraft = 0`, `grafted_date` = regraft
  date, status active, chosen variety, actor user); (6) create the `RegraftRecord`
  (`batch_id = NEW sub-batch`, `parent_batch_id = source`); (7) `ActivityLog::record` (existing
  `regraft_recorded` type) attributed to the actor. Any failure rolls all of it back.
- **Model changes:** `Batch` gained `regraftableQuantity()` (now `survivingQuantity()`, Session CT),
  `nextSubBatchCode()` and
  `regraftRecordsAsParent()`; `totalRegrafted()` now returns the cached `batches.regraft` counter;
  `remainingReplantingCapacity()` was **removed**; `subBatches` was added to
  `Batch::OPERATIONAL_RELATIONS` so a parent's counts lock once it has been regrafted.
  `RegraftRecord` gained `parent()` / `subBatch()` relations and `totalRegraftedFromBatch($id)`.
- **Controller/request/views:** `ReplantingController` now delegates to `RegraftBatch` inside the
  `DuplicateSubmissionGuard`; `StoreRegraftRecordRequest` requires `batch_id`, `quantity`,
  `scion_variety_id` (active) and `regraft_date` (not future); the Regraft page shows summary
  cards (Regraft Deficit / Total Regrafted / Total Mortality / Batches With Deficit), a per-batch
  table (Total Grafted / Regraft / Mortality / Deficit), a **Regraft History** table
  (date / source batch / new sub-batch / sub-batch variety / quantity / remark / user) and a
  source-prefilled Record Regraft modal (JS max = regraftable, default variety = source's). The
  Batches list gained a **Parent Batch** column. Success message: `Regraft recorded; sub-batch {code}
  created.`
- **Provisional defaults (each flagged `provisional — pending PRD OD-n`):**
  1. **`OD-6`** — regraftable cap
     `max(0, min(available_count, (total_grafted − regraft) − effective mortality))` (a dead root
     stock cannot be regrafted) — **CONFIRMED 2026-09-29** for the cap/sub-batch `total_grafted`
     clause; the code-format details remain provisional.
  2. **`OD-6`** — sub-batch code = source `batch_code` + `-R` + first free sequence (`B001-R1`,
     `B001-R2`, …); nested (`B001-R1-R1`) when a sub-batch is itself regrafted.
  3. **ERD §3.11 reading** — the `RegraftRecord` stores `batch_id` = the new sub-batch and
     `parent_batch_id` = the source batch.
  4. **`OD-12`** — `Batch::totalRegrafted()` = the cached `batches.regraft` counter (reconciled in
     tests against `RegraftRecord::totalRegraftedFromBatch()`).
- **Seeders** (`DatabaseSeeder`) now seed the regraft scenario **only through `RegraftBatch`**:
  B001 Batangas 500 / mortality 25 → regraft 100 into **B001-R1** using Toledo → mortality 5 on
  B001-R1 → one Stock Out (20) from B001-R1. Idempotent (guarded on `b001.regraft < 100`);
  `ActivityLogSeeder` reproduces the action's description so re-running does not duplicate the row.
- **Tests:** new `tests/Feature/RegraftFlowTest.php` (happy path exact before/after + code + parent +
  record fields + Activity Log; `-R2` and nested `-R1-R1` sequencing; validation for 0/negative/
  non-numeric/over-cap/dead-stock/archived variety/archived source/future date/date-before-grafted;
  atomic rollback via a forced `ActivityLog` failure; duplicate-submission guard; 403 without
  `record_operations`; invariants `0 ≤ available ≤ total_grafted` + `(total_grafted − regraft)`
  conservation + counter reconciliation; parent/sub-batch independence; counts locked after regraft;
  Stock Out and Inventory
  snapshots on a sub-batch; the page no longer shows the deficit cap). `ReplantingTest`,
  `BatchRelationshipsTest`, `CumulativeEventRecordsTest`, `AnalyticsTest`, `InventoryTest` updated to
  the Phase 7B semantics. **Verified:** full MySQL suite **299 passed / 1278 assertions**, Pint +
  `php -l` clean, `migrate:status` **0 Pending** on `db_capstone_test`, seeders clean + idempotent.
  Seeded invariants: `available_count > total_grafted` = **0**, `available_count < 0` = **0**,
  family totals conserve (B001 400 + B001-R1 100 = original 500 — **superseded 2026-09-29**:
  `total_grafted` is now cumulative, so `(total_grafted − regraft)` is what conserves), `Σregraft`
  **100** == `Σ(records where parent_batch_id)` **100**.
- **`db_capstone` was upgraded + reseeded in Phase 7D (2026-09-29)** — the four `2026_09_27_*`
  migrations are applied (no longer Pending) and the live DB matches this schema. (They remained
  Pending through Phase 7B; that step was executed later — see "PHASE 7D DONE" at the top.)
- **Out of scope (unchanged):** Stock Ledger / Inventory **nested display** (Phase 7C), analytics
  changes, `Plant_Condition`, `Sub_Batch_ID`, and any correction/edit policy. The Regraft Deficit and
  Gap formulas were **not** changed (`OD-10`/`OD-3` remain open).

## PHASE 7C DONE — parent/sub-batch nesting + Stock Ledger / analytics alignment (2026-09-28)
- **Baseline = the client-approved v1.0 docs** `docs/CacaoTrack_PRD_UPDATED.md` (§9.3, FR-08) /
  `docs/CacaoTrack_ERD_UPDATED.md`. **No PRD/ERD file was edited**, and no `[PROPOSED]` v2.0 item was
  implemented beyond the Phase 7A/7B provisional defaults already recorded. **No migration** — the
  schema is final.
- **One tree helper — `App\Support\BatchTree`** (RULE 6) is the single ordered parent→children view.
  `flatten()` returns depth-tagged nodes (`batch`, `depth`, `parent_code`, `parent_missing`,
  `ancestors`); `groups()` returns one entry per root family. Roots sort by `grafted_date` then
  `batch_code`, children by `batch_code`; sub-batches of sub-batches are supported (depth 3+). A
  batch whose parent is filtered out is shown at depth 0, flagged `parent_missing` (never dropped),
  with the parent code resolved in **one batched query** (no N+1). Every nested view reads its
  ordering/depth from this helper only.
- **Batches page:** families paginate one-per-page so a parent and its sub-batches are never split;
  nested rows are indented with `↳`, carry a **Sub-batch** badge and the existing **Parent Batch**
  column, and each parent has a Bootstrap collapse (expanded by default; a parent's toggle hides its
  whole subtree through `child-of-{id}` classes).
- **Inventory page:** snapshots are nested through the helper (each batch's snapshots newest-first)
  with a **Parent Batch** column and a Sub-batch badge. The "Record Monthly Inventory" batch select
  labels sub-batches `↳ B001-R1 (Toledo, parent B001)`. No family subtotal rows.
- **Stock Ledger (monthly grid kept):** the **Grafted** column now only includes root batches
  (`parent_batch_id IS NULL`); sub-batch plants appear only under **Regraft**. Ready to Plant is
  unchanged (still each batch's live `available_count`, sub-batches included). **Regraft** rows read
  `regraft_records` with the description `Regraft {source} → {sub-batch}`, the **sub-batch's**
  scion variety and `regraft_date`. **Seedlings** now counts only `stock_ins` rows with
  `scion_variety_id IS NULL` (scion receipts no longer inflate it). A footnote under the grid explains
  the no-cross-column-total rule, and a **Batch summary (nested)** table (Batch/sub-batch, Parent,
  Scion Variety, Total Grafted, Available, Regraft, Mortality, Stock Out, Date Grafted — all computed)
  renders below the grid. A new server-side **`GET /analytics/stock-ledger/export/csv`**
  (`analytics.stock-ledger.export.csv`) replaces the old client-side scrape; `StockLedgerPdf` carries
  the same footnote + nested summary.
- **Age Profile:** sub-batches are their own rows (own `grafted_date`) with a **Parent Batch** column;
  the chart/KPIs are unchanged (a sub-batch's available is counted once, in its own age bucket).
- **Gap Report:** sub-batch rows nested under their parent with a Parent column (page, PDF and CSV);
  the formula is **unchanged** (`gap = Batch::effectiveMortalityQuantity()`).
- **Filter semantics (recorded):** every analytics batch dropdown lists sub-batches as separate
  options (`↳ B001-R1 (parent B001, Toledo)`); filtering by a batch matches that **exact** batch only —
  selecting a parent does **not** include its sub-batches. This is stated in each page's help text.
- **Seeder:** the regraft scenario now also regrafts 30 from **B001-R1** into **B001-R1-R1** (Criollo,
  seeded only through `RegraftBatch`, idempotent), and the current month's Inventory snapshot is
  written for the parent and both sub-batches through the app logic.
- **Verified:** full MySQL suite **328 passed / 1406 assertions**, Pint clean, `php -l` clean,
  `view:cache` clean, `migrate:status` **0 Pending** on `db_capstone_test`. Seeded invariants (at
  Phase 7C, under the old `total_grafted` arithmetic — **superseded 2026-09-29**, see the CONFIRMED
  section at the top): SUM available **730** == Total Active Inventory; SUM total_grafted **750** ==
  Crude Mortality Rate denominator; B001 family total_grafted **500** conserved; `Σregraft` **130** ==
  `Σ(regraft_records.quantity)` **130**; Seedlings **800** (seedling-only) vs **1000** all Stock In;
  ledger grafted **650** / ready_to_plant **730**. Under the corrected cumulative rule these become
  raw SUM total_grafted **880** / denominator `SUM(total_grafted − regraft)` **750** / rate **7.33%**,
  family sum **630** (with `total_grafted − regraft` still **500**), and ledger grafted **750**;
  available **730**, regraft **130**, seedlings **800** are unchanged.
- **`db_capstone` was upgraded + reseeded in Phase 7D (2026-09-29)** — the four `2026_09_27_*`
  migrations are applied and the live DB matches this schema. (They remained Pending through
  Phase 7C; applying them + the reseed was the separate, explicitly approved step executed in
  Phase 7D — see "PHASE 7D DONE" at the top.)
- **Out of scope (unchanged):** analytics formulas / `OD-n` decisions, `Plant_Condition`,
  `Sub_Batch_ID`, correction/edit policy, and the Ready to Plant mapping (still a best guess).

## REFACTOR IN PROGRESS — READ THIS FIRST
- The source-of-truth PRD and ERD were **replaced with the revised 15-entity model**. Canonical
  `docs/CacaoTrack_PRD.md` and `docs/CacaoTrack_ERD.md` now contain the revised content; identical
  copies are retained for provenance at `docs/CacaoTrack_PRD_REVISED.md` /
  `docs/CacaoTrack_ERD_REVISED.md`. The **canonical files (no `_REVISED` suffix) are authoritative.**
  The `_REVISED` copies are **not frozen**: whenever a canonical PRD/ERD changes, update the matching
  `_REVISED` copy in the same change so the pair stays **byte-identical** (SHA-256 match) — this is the
  project's established practice (e.g. Sessions BY/BX), not an optional courtesy.
- **P1 (schema), P2 (models), P3 (controllers/routes), P4 (views/sidebar), P5 (analytics/reports) and
  P6 (seeders/tests) are DONE.** `db_capstone` uses the revised schema, `app/Models` matches it
  (Session BM), `app/Http/Controllers` + `routes/web.php` match it (Session BN, checkpoint `3d3f9f9`),
  the **views/sidebar match it** (Session BO, checkpoint `0803bdf`), the **analytics/report layer
  matches it** (P5, checkpoint `d139286` — `AnalyticsMetrics`, the PDF/CSV classes and the
  Dashboard/Stock Ledger/Matrix/Historical pages all query the current models; `/` returns **200**),
  and the **seeders/tests/factories now match it** (P6 — see the P6 note in the plan below). The
  five legacy test files for removed entities were deleted and the suite is green.
- Until P6 lands: **do not** reintroduce removed tables/columns or add new features without checking
  the plan. New schema/business-logic work must follow the revised ERD. **There is no longer any
  approved additive schema exception** — the short-lived `scion_receivings` exception (Session BV) was
  **merged into `stock_ins` in Session CC** (see the banner). Validate new records with built-in
  Laravel rules; do **not** create an additional table for validation.
- **Session CC — `scion_receivings` merged into `stock_ins` (client-confirmed; additive columns):**
  the client confirmed seedling Stock In and Scion Receiving are the **same real-world receiving
  event**, so (mirroring the earlier Regraft/Replanting consolidation) the separate `Scion_Receiving`
  entity was retired. Migration `2026_09_26_000003_add_scion_columns_to_stock_ins_table` adds
  **nullable** `scion_variety_id` (FK → `scion_varieties`, restrictOnDelete), `unit_price`
  (decimal 12,2) and `notes` to `stock_ins`, and makes `stock_ins.supplier_id` **nullable** (scion
  receipts may have no formal Supplier, and real rows existed with a NULL supplier). Migration
  `2026_09_26_000004_merge_scion_receivings_into_stock_ins_table` **data-migrates** the existing
  `scion_receivings` rows into `stock_ins` (attributed to the earliest user; `viable = quantity`;
  `origin`/`notes` preserved) then **drops** `scion_receivings`. The create migration
  `2026_09_26_000001` is left intact as history. `App\Models\ScionReceiving`, its factory, controller,
  request, view, routes and sidebar link were deleted; the scion-receipt fields now live on the
  Stock In form as a **"Scion" tab** beside the "Seedlings" tab (one form, one table, no discriminator
  column). `/operational/scion-receivings` no longer exists; the data is under **Stock In**. **No
  stock balance** is held on `scion_varieties`; the freshness/viability rule remains NOT implemented.
  **Live `db_capstone` was migrated** in the same session, after a fresh verified backup at
  `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_backup_cc_20260926_170736.sql`
  (36,778 B, 24 tables, clean "Dump completed" trailer): `scion_receivings` **2 rows** were carried
  into `stock_ins` (table total **2 → 4**; the 2 scion rows read `viable == quantity`) and the table was
  dropped; `migrate:status` **0 Pending** on both `db_capstone` and `db_capstone_test`. (First live run
  correctly failed because `users` uses Laravel's default `id` PK, not `user_id`; the migration now
  detects the key — no data was lost.)
- **`regraft_records` retracted (Session BX — resolved; was P5 Part B):** `RegraftRecord` was a P5
  approved schema exception that **duplicated `Regraft_Record`**; in the company's process Regraft
  and Replanting are the **same recorded procedure**. It was dropped by migration
  `2026_09_26_000002_drop_regraft_records_table` (the table held **0 rows** — no data migration needed;
  the create migration `2026_09_25_000009` is left intact as history). `app/Models/RegraftRecord.php`,
  `Batch::regraftRecords()` and the `AnalyticsMetrics` import were deleted, and the Stock Ledger's
  Regraft column now reads `regraft_records` directly. **This resolves the Session BU proposal,
  which (contrary to an earlier report) had never been applied.** `Regraft_Record` is no longer an
  approved schema exception.
- **Session BV approved schema addition — `scion_receivings` (additive only; commit `33ff6b0`) —
  RETIRED in Session CC (merged into `stock_ins`; see the banner):**
  migration `2026_09_26_000001_create_scion_receivings_table` created `scion_receivings` (ERD §3.16)
  plus `App\Models\ScionReceiving` — a **receiving log only** for scion material (`scion_variety_id`
  required FK, `supplier_id` nullable FK, `quantity`, `unit_price` nullable, `date_received`, `origin`
  single nullable, `notes` nullable). It holds **no stock balance** (nothing was added to
  `scion_varieties`), does **not** alter `RegraftRecord`, and has **no** CRUD
  controller/route/view yet. Validation uses built-in Laravel rules (`ScionReceiving::rules()`) — no
  additional table. **Freshness/viability rule NOT implemented** (no usable-until value, no warning, no
  blocking) pending client confirmation of the allowed viability window and whether to warn or block.
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
  There are **no approved additive exceptions** (`scion_receivings` was merged into `stock_ins` in
  Session CC; `regraft_records` was retracted in Session BX). Validate with built-in Laravel rules;
  do not add extra tables for validation.

## Source-of-truth docs (read before schema or business-logic changes)
- `docs/CacaoTrack_PRD.md` — revised PRD: what the system must do (FR-01…FR-13, validation,
  transaction integrity, analytics, dashboard, data-consistency rules). **v2.0 (2026-09-28)** —
  corrected from the client-approved baseline; **§19 Open Decisions** (`OD-1`…`OD-14`) is pending
  client sign-off.
- `docs/CacaoTrack_ERD.md` — revised ERD: the **15-entity** data model (snake_case PK/FK map). This
  is the database source of truth. **v2.0 (2026-09-28)** — corrected; Appendix A holds decision-log
  history.
- `docs/CacaoTrack_PRD_UPDATED.md` / `docs/CacaoTrack_ERD_UPDATED.md` — the **client-approved v1.0
  baseline**. **Untouched provenance — never edit.** The corrected canonical files above are derived
  from them.
- `docs/CacaoTrack_Development_Rules.md` — 14 mandatory rules (validate everything, real data only,
  transaction-safe inventory, centralized formulas, stop instead of guessing).

## Corrected client-approved PRD/ERD + Open Decisions (2026-09-28, doc-only, checkpoint `584c548`)
- The **client-approved baseline** now is the corrected `docs/CacaoTrack_PRD.md` +
  `docs/CacaoTrack_ERD.md` (**v2.0, 2026-09-28**). They are derived from the untouched provenance
  copies `docs/CacaoTrack_PRD_UPDATED.md` / `docs/CacaoTrack_ERD_UPDATED.md` (**v1.0,
  client-approved; never edit**). The `_REVISED` copies mirror the canonical files **byte-identically**
  (SHA-256 match), per project practice.
- The correction fixed internal PRD/ERD contradictions (Stock In fields; Stock In ↔ Batch; mortality
  plant condition; scion availability; ordered quantity; regraft purpose; the **Regraft** screen
  label) and defined previously undefined rules. **No client-approved rule was changed.**
- **Open Decisions are pending client confirmation** — PRD **§19** lists `OD-1`…`OD-14`: ordered
  quantity storage; mortality `Plant_Condition`; `Available_Count` definition (+ Gap Report);
  mortality quantity bound; corrections policy; regraft sub-batch details; 21-day cycle +
  `Batch.Status`; Stock In ↔ Batch traceability; Inventory snapshot semantics; Dashboard KPIs
  (+ Regraft Deficit); `Regraft_Record.Sub_Batch_ID`; cached `Batch.Regraft`; scion freshness
  rule; scion receipt traceability. Every such rule carries the inline marker
  **[PROPOSED — pending client confirmation]**. **Do not implement any `OD-n` rule without
  sign-off.**
- **Doc-only:** no code, migration, seeder, test or route was changed.

## Revised data model (15 entities — the TARGET)
| # | Entity | PK | Key FKs / notable columns |
|---|---|---|---|
| 1 | `Users` | `user_id` | `role` (string), `permissions` (JSON), `full_name`, `email`, `password`, `created_at` |
| 2 | `Activity_Log` | `activity_id` | `user_id`, `batch_id` (nullable), `activity_type`, `description`, `activity_date` |
| 3 | `Analytics` | `analytics_id` | `user_id`, `analysis_type`, `analysis_period`, `result_summary`, `generated_at` |
| 4 | `Activity_Task` | `task_id` | `task_name`, `description`, `priority`, `archived_at` (nullable) |
| 5 | `Task_Complete` | `completion_id` | `task_id`, `user_id`, `verified_by_id`, `completion_date`, `verified_at`, `remark` |
| 6 | `Supplier` | `supplier_id` | `supplier_name`, `contact_number`, `address`, `archived_at` (nullable) |
| 7 | `Stock_In` | `stock_in_id` | `supplier_id` (nullable), **`scion_variety_id` (nullable)**, `user_id`, `quantity`, `unit_price` (nullable), **`origin`**, `notes` (nullable), `date_received` |
| 8 | `Batch` | `batch_id` | **`scion_variety_id` (nullable)**, **`parent_batch_id` (nullable)**, `user_id`, `batch_code`, `total_grafted`, **`regraft`**, `available_count`, `grafted_date`, `created_at`, `status`, `archived_at` (nullable) |
| 9 | `Scion_Variety` | `scion_variety_id` | `scion_variety_name`, `description`, `archived_at` (nullable) |
| 10 | `Mortality_Record` | `mortality_id` | `batch_id`, `user_id`, `quantity`, `cause`, `recorded_at` |
| 11 | `Regraft_Record` | `regraft_id` | `batch_id`, **`parent_batch_id` (nullable)**, `user_id`, `quantity`, `regraft_date`, `remark` |
| 12 | `Inventory` | `inventory_id` | `batch_id`, `inventory_date`, `total_grafted`, `regraft`, `mortality`, `status`, `last_update` |
| 13 | `Client` | `client_id` | `client_name`, `contact_number`, `address`, `archived_at` (nullable) |
| 14 | `Stock_Out` | `stock_out_id` | `client_id`, `user_id`, `release_date`, `total_amount` (nullable) — header only |
| 15 | `Stock_Out_Detail` | `stock_out_detail_id` | `stock_out_id`, `batch_id`, `scion_variety_id`, `quantity`, `unit`, `unit_price` (nullable), `amount` (nullable) |

**Key revised rules:** `Stock_In.quantity` is the received quantity recorded immediately (there is
**no** five-day viability count and no `viable` column — Phase 7A); Stock In has **no** `batch_id`.
`Batch` has **no** `variety_id`, `pot_id`, or `monitoring_start_date`, and (Phase 7A) **no
`viable_count`** — it carries `regraft` and a nullable `parent_batch_id` for regraft sub-batches.
`Inventory` is a **monthly snapshot** (many rows per batch — the old per-batch UNIQUE
`current_quantity` row is gone) and holds no `viable` count.
`Stock_Out` is a header; each variety/batch quantity is a `Stock_Out_Detail` line. `Stock_In` has
**no** `scion_quantity` and there is no scion stock pool. **Scion receiving is merged into
`Stock_In`** (Session CC): a scion-inclusive row has `scion_variety_id` + `unit_price` set (and
`viable = quantity`, no recount); a pure-seedling row leaves them null. There is **no** separate
`Scion_Receiving` entity/table and **no** discriminator column — the presence of `scion_variety_id`
is the signal. `Regraft` is not a separate entity — **Regraft and Replanting are the same recorded
procedure**, recorded in the single `regraft_records` table (renamed from `replanting_records` on
2026-09-30 — see the banner; the short-lived P5 `regraft_records` exception had already been
retracted). `inventories.regraft` (monthly aggregate) is unchanged.

## Removed from the model/process (do NOT reintroduce)
- Tables: `Batch_Monitoring`, `Variety`, generic `Scions` stock pool, `Pot_Type`,
  `Mortality_Adjustments`, `Stock_Reversal`.
- Columns: `Batch.Monitoring_Start_Date`, `Stock_In.Batch_ID`, `Stock_In.Scion_Quantity`,
  `Batch.viable_count`, `Stock_In.viable`, `Inventory.viable` (the viability count was removed in
  Phase 7A).
- Concepts: scion stock receiving/used/unused inventory logic, Batch Monitoring
  status/history logic, the Stock Reversal workflow, separate per-scion inventory, and the
  `varieties`/`pot_types` master modules.
- **`Scion Variety`** is the single retained term/module for scion-variety information (it replaces
  both the old `Variety` and the generic `Scions` concepts).

## Revised navigation (sidebar)
- **Dashboard**
- **Operational:** Daily Checklist, Mortality Tracking, Regraft, Batches, Scion Variety
- **Inventory:** Stock In, Stock Out, Inventory
- **Management:** Supplier, Client
- **Analytics:** Operational Analytics, Tabular Deliverables, Stock Ledger, Root Cause Matrix
- **Historical:** Seasonality Heatmap, Age Profile, Gap Report
- **Account:** Activity History, User Management, Profile, Logout
- Removed nav items: Batch Monitoring, Variety, Stock Reversal, Scion, Pot Type.

## Locked refactor plan (Session BH — P0–P6 DONE)
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
  The P5 `regraft_records` exception was **retracted in Session BX** (dropped; the Stock Ledger reads
  `regraft_records` — see the banner). `/` (Dashboard) returns **200**. See `session.handoff.md`
  §2 Session BQ / Session BX.
- **P6 Tests/docs/verify (DONE — Session BZ):** the five legacy tests for removed entities were
  deleted (`BatchMonitoringTest`, `VarietyTest`, `PotTypeTest`, `ScionTest`, `StockReversalTest`);
  the stale `AnalyticsTest` Gap assertions were rewritten to the corrected formula
  (`Gap = Batch::effectiveMortalityQuantity()`) and the whole old-model feature suite was migrated to
  the revised schema; **factories** were added for `ScionVariety`/`Batch`/`MortalityRecord`/
  `RegraftRecord`/`StockIn`/`StockOut`/`StockOutDetail`/`Inventory`/`Supplier`/
  `Client` (with `HasFactory` added to those models; the `ScionReceiving` factory was later deleted in
  Session CC when the entity was merged into `Stock_In`); `DatabaseSeeder` + `ActivityLogSeeder` were
  rewritten to the current schema (idempotent; B001 carries genuine mortality **and** regraft; seeded
  Stock Out respects `available_count`); and new Part D tests cover relationships, validation,
  cumulative event records, date handling, the corrected Gap formula, Mortality↔Replanting
  independence and the Scion_Receiving no-balance/no-freshness guards. **Verified:** full MySQL suite
  **280 passed / 1170 assertions**, `vendor\bin\pint` clean, `php -l` clean, `migrate:status`
  **0 Pending**, seeders run cleanly (and re-run idempotently) against `db_capstone_test`, and no
  reference to the removed entities remains in `tests/`.

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
- (b) `stock_out_details.unit` is a **fixed list**: now **`pots` only** (client/owner-directed
  2026-09-29; was `seedlings`, `pieces`, `bags`). The single source of truth is the centralized
  constant `StockOutDetail::UNITS`, consumed by the Stock Out form select and server-side validation
  (`StoreStockOutRequest`). Legacy migrated lines keep their historical `seedlings` value (read-only
  display; `ucfirst`).
- (c) `stock_ins.origin` is **NOT NULL**; the 8 legacy rows were backfilled to `''` and P3 enforces a
  non-empty origin for new records (`StoreStockInRequest`). No other P1 decision remains.

## Current code state (all layers match the revised 15-entity model — P0–P6 DONE)
`db_capstone` (P1), `app/Models` (P2 — Session BM), `app/Http/Controllers` + `routes/web.php`
(P3 — Session BN, checkpoint `3d3f9f9`), the **view layer (Blade + sidebar)** (P4 — Session BO,
checkpoint `0803bdf`), the **analytics/report layer** (P5 — checkpoint `d139286`:
`AnalyticsMetrics`, PDF/CSV classes, Dashboard, Stock Ledger monthly grid, Matrix, Historical) and the
**seeders/tests/factories** (P6 — Session BZ) all match the revised model; the full MySQL suite is
green (latest **342 passed / 1484 assertions**, Session CU). `/` (Dashboard) returns **200**. The
mapping below records the P1–P3 schema transition (now complete). (For full historical detail see
`session.handoff.md` §2.)

| Revised entity | Current state | Action |
|---|---|---|
| Users, Activity_Log, Analytics, Activity_Task, Task_Complete, Supplier, Client, Mortality_Record, Regraft_Record | exist | **keep** |
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

## P6 deferred items (confirmed open — DO NOT implement without sign-off)
1. **Stock In scion-tab UI (DELIVERED in Session CC):** scion receiving is entered through the
   Stock In form's **"Scion" tab** (scion variety + unit price + notes; server sets `viable =
   quantity`). This is what exists now. The former standalone **"Scion Receiving CRUD UI" item is
   obsolete/closed** — the `/operational/scion-receivings` page, controller, request and route no
   longer exist. **Optional unbuilt slices (client-gated, do not build unprompted):** Edit / Archive /
   Details for a scion receipt row.
2. **Scion receiving traceability (still open, client-gated):** grafting/regrafting records referencing
   a specific scion-inclusive **`Stock_In`** row (one created via the Stock In "Scion" tab) so scion
   material can be traced. **No stock-balance deduction.** The route/controller/validation/views/tests
   are to be added only after the workflow is confirmed with the client.
3. **Freshness/viability rule for received scion:** no usable-until value, no warning, no blocking.
   Pending client confirmation of (a) the allowed viability window and (b) whether to warn or block.
4. **Stock Ledger "Ready to Plant" mapping:** currently maps to the batch's live `available_count`,
   aligned to its `grafted_date`. This is an unconfirmed best-guess interpretation. **Leave the
   implementation exactly as-is** (code + PDF) until the client confirms the definition.
5. **`db_capstone` data policy (Part F below):** **EXECUTED 2026-09-26** — `db_capstone` was backed
   up, then wiped and reseeded with fresh, schema-valid data (Session CA). See the section below.

## `db_capstone` data policy — EXECUTED (wipe + reseed, 2026-09-26)
**Status: executed 2026-09-26 (Session CA).** The live `db_capstone` (connection `mysql`, database
`db_capstone`) was backed up, then wiped and reseeded with fresh, schema-valid data via
`php artisan migrate:fresh --seed --force`, using the P6 `DatabaseSeeder` + `ActivityLogSeeder`.
This was the **one explicitly approved destructive action** against the live DB. The old
pre-refactor residue is gone and the seeder now enforces the app's own invariants.
- **Backup (taken before the wipe — left untouched, outside the git repo):**
  `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_backup_2026_09_26_20260926_154153.sql`
  — 47,596 bytes, 24 `CREATE TABLE` + 16 `INSERT INTO`, dumped with `--single-transaction`, clean
  "Dump completed" trailer. Do not delete or modify it.
- **Before → after row counts (`db_capstone`):** batches 9 → 3, stock_outs 7 → 1,
  stock_out_details 7 → 1, mortality_records 17 → 3, regraft_records 13 → 3, stock_ins 8 → 2,
  scion_receivings 0 → 2. Also after: users 1, scion_varieties 3, suppliers 3, clients 3,
  inventories 3, activity_logs 9, activity_tasks 4.
- **Anomaly eliminated:** previously `Σviable` 1170 / `Σavailable` 1130 with B005 `available_count`
  600 > `viable_count` 100; now **no batch has `available_count > viable_count`**
  (B001 180/180, B002 140/120 after a 20-seedling release, B003 95/95; `Σviable` 415 /
  `Σavailable` 395).
- **Verified live after reseed:** Dashboard `/` = **200**; Operational Analytics, Stock Ledger,
  Gap Report and Root-Cause Matrix all **200**. KPIs from the new data: Total Active Inventory
  **395**, Crude Mortality Rate **11.11%**, Regraft Deficit **16**, Gap **50** (= Σ recorded
  mortality). `migrate:status` 0 Pending.
- **Standing rule unchanged:** this was the ONLY approved destructive command against `db_capstone`.
  Tests must still point only at `db_capstone_test`; any further destructive action against the live
  DB requires fresh explicit sign-off. (The former "preserve the residue" fallback no longer
  applies — the residue is gone.)
- **Post-policy schema change on the live DB (Session CC):** the client-confirmed Scion_Receiving →
  Stock_In merge ran `2026_09_26_000003` + `2026_09_26_000004` against `db_capstone` **after a fresh
  verified backup** at
  `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_backup_cc_20260926_170736.sql`
  (36,778 B / 24 tables). It was **not** destructive of any receiving data: the 2 `scion_receivings`
  rows were migrated into `stock_ins` (`stock_ins` 2 → 4; scion rows `viable == quantity`) and then the
  empty table was dropped. `migrate:status` 0 Pending. This was an additive+data-migrating schema
  change, not a wipe/reseed; the wipe policy above still stands.
- **Phase 7D wipe + reseed on the live DB (2026-09-29):** the project owner explicitly approved one
  further destructive action — `php artisan migrate:fresh --seed --force` against `db_capstone` —
  after a fresh verified backup at
  `C:\Users\Filepen\AppData\Local\Temp\opencode\db_capstone_pre_phase7d_20260929_000206.sql`
  (35,131 B / 23 tables). It applied the four `2026_09_27_*` migrations and reseeded the approved
  family (B001 → B001-R1 → B001-R1-R1 + B002/B003). `migrate:status` 0 Pending; all 20 sidebar pages
  return 200. See "PHASE 7D DONE" at the top. This remains the ONLY further approved destructive
  action; any additional one needs fresh sign-off.
