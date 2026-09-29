# CacaoTrack ERD — Revised Core Data Model

**Version:** 2.0 — Client-Approved Baseline (corrected)  
**Date:** 2026-09-28  
**Companion document:** `CacaoTrack_PRD.md` (PRD §19 Open Decisions carries the `OD-n` identifiers cited below)

**Change log:**

- **2.0 — 2026-09-28:** Corrected the client-approved baseline (`CacaoTrack_ERD_UPDATED.md`) for internal ERD/PRD contradictions and undefined rules. **No client-approved rule was changed.** New, unconfirmed rules carry the `[PROPOSED — pending client confirmation]` marker (rendered in bold) with an `(OD-n)` reference; the decisions are collected in `CacaoTrack_PRD.md` §19 Open Decisions. Development history was moved to Appendix A.
- **2.0 — 2026-09-29 (confirmed):** `Total_Grafted` confirmed as a **cumulative, non-decreasing log** per batch (client spreadsheet evidence + direct statement *"Grafted minus Regraft = mortality or yield for the parent batch"*); a batch's live population for mortality/regraft is `Total_Grafted − Regraft`. Updated §2 rule 7, §3.8 and §3.11; PRD §2.4/§10/§11/§19 in step.
- **1.0:** Client-approved revised core data model. Preserved untouched as provenance in `CacaoTrack_ERD_UPDATED.md`.

---

## 1. Purpose

This ERD keeps only the entities and relationships needed for the current CacaoTrack business process. It follows the company's current operational terms and does not introduce a separate Batch Monitoring process. User-facing screens use **Regraft**; the internal entity/table name is `Regraft_Record` / `regraft_records` (renamed from `Replanting_Record` / `replanting_records` on 2026-09-30 so the internal name matches the company term).

## 2. Current Business Process

```text
ORDER SEEDLINGS
      ↓
STOCK IN (RECEIVED QUANTITY)
      ↓
ESTABLISH BATCH / GRAFT
      ↓
SELECT SCION VARIETY (informational: recent scion receipts for that variety are shown; staff decide; no stock balance)
      ↓
MORTALITY / REGRAFT
      ↓
21-DAY CYCLE
      ↓
INVENTORY RECORD
      ↓
COUNT HEALTHY CACAO FOR PULL-OUT
      ↓
STOCK OUT / PULL OUT
      ↓
CLIENT
```

### Core business rules

1. **Stock In** records seedlings immediately when they are received; there is no five-day wait or separate seedling viability count.
2. The **received quantity may differ from the originally ordered quantity**. The received `Quantity` is the quantity available for establishing production batches. The ordered quantity itself is **context only and is not stored** **[PROPOSED — pending client confirmation]** (OD-1).
3. **Batches** represent grafted cacao established from the received seedlings.
4. **Scion Variety** is used when grafting/regrafting requires a particular scion variety. There is **no scion stock balance**: selecting a variety is informational and staff decide (OD-14).
5. If the **root stock is dead**, it cannot be regrafted and is recorded as **Mortality**. Dead root stock is never regrafted.
6. Mortality is a **strictly incrementing counter** and is recorded against the exact Batch or sub-batch where the death occurred. Recording mortality does not decrease or modify the Regraft counter.
7. **Regraft** (recorded internally as `Regraft_Record`) replaces a **dead scion on a living root stock**. **CONFIRMED (2026-09-29) — client spreadsheet evidence:** a regraft is recorded as a **new Grafted entry plus a Re Graft entry on the same row**, so **`Total_Grafted` is a cumulative, non-decreasing log per batch and is never reduced by a regraft**. When regrafting occurs, only the parent Batch's live `Available_Count` decreases by q, the parent `Regraft` counter increases by the same quantity, and a linked sub-batch is created with its own `Total_Grafted = q`. **The batch's still-attributable/live population for mortality and regraft purposes is `Total_Grafted − Regraft`** (client statement: *"Grafted minus Regraft = mortality or yield for the parent batch"*). A Regraft does **not** offset Mortality.
8. A regraft sub-batch has its own independent 21-day cycle and may use a different **Scion Variety** from its parent Batch.
9. **Inventory** is recorded per month and follows the established Batches and sub-batches, including `Total_Grafted`, `Regraft`, and `Mortality`.
10. **Stock Out / Pull Out** counts healthy cacao from the applicable Batch or sub-batch and records the cacao delivered to a Client.
11. One Stock Out may contain multiple **Scion Variety** quantities, matching the company's pull-out records.

---

## 3. Core Entities

### 3.1 `Users`

| Key | Attribute |
|---|---|
| PK | `User_ID` |
| — | `Role` |
| — | `Permissions` |
| — | `Full_Name` |
| — | `Email` |
| — | `Password` |
| — | `Created_At` |

**Purpose:** Stores system users and their access information.

---

### 3.2 `Activity_Log`

| Key | Attribute |
|---|---|
| PK | `Activity_ID` |
| FK | `User_ID` |
| FK | `Batch_ID` *(nullable)* |
| — | `Activity_Type` |
| — | `Description` |
| — | `Activity_Date` |

**Purpose:** Records important system and operational actions performed by users.

---

### 3.3 `Analytics`

| Key | Attribute |
|---|---|
| PK | `Analytics_ID` |
| FK | `User_ID` |
| — | `Analysis_Type` |
| — | `Analysis_Period` |
| — | `Result_Summary` |
| — | `Generated_At` |

**Purpose:** Stores generated analytics records when the application needs to retain a generated analysis.

---

### 3.4 `Activity_Task`

| Key | Attribute |
|---|---|
| PK | `Task_ID` |
| — | `Task_Name` |
| — | `Description` |
| — | `Priority` |
| — | `Archived_At` *(nullable)* |

**Purpose:** Stores Daily Checklist tasks.

---

### 3.5 `Task_Complete`

| Key | Attribute |
|---|---|
| PK | `Completion_ID` |
| FK | `Task_ID` |
| FK | `User_ID` |
| FK | `Verified_By_ID` |
| — | `Completion_Date` |
| — | `Verified_At` |
| — | `Remark` |

**Purpose:** Records completion and verification of Daily Checklist tasks.

---

### 3.6 `Supplier`

| Key | Attribute |
|---|---|
| PK | `Supplier_ID` |
| — | `Supplier_Name` |
| — | `Contact_Number` |
| — | `Address` |
| — | `Archived_At` *(nullable)* |

**Purpose:** Stores supplier information for seedling orders/Stock In.

---

### 3.7 `Stock_In`

| Key | Attribute |
|---|---|
| PK | `Stock_In_ID` |
| FK | `Supplier_ID` *(nullable for a scion receipt without a formal supplier)* |
| FK | `Scion_Variety_ID` *(nullable — set only when the receiving includes scion)* |
| FK | `User_ID` |
| — | `Quantity` |
| — | `Unit_Price` *(nullable — scion only)* |
| — | `Origin` |
| — | `Notes` *(nullable — scion receipt notes)* |
| — | `Date_Received` |

**Purpose:** Records a receiving event. It covers both seedling Stock In and scion receiving, because the client confirmed these are the **same real-world receiving event**. A receiving that includes scion is recorded as a `Stock_In` row with `Scion_Variety_ID` (and `Unit_Price`) set; a pure-seedling delivery leaves those columns null. There is no separate entity, no separate UI page, and no discriminator column.

**Business rule:** for a **seedling** row (`Scion_Variety_ID IS NULL`), `Quantity` records the seedlings actually received; it may differ from the originally ordered quantity, and it is immediately eligible for establishing production batches (there is no five-day wait or separate seedling viability count). **Any total of seedlings received must exclude rows where `Scion_Variety_ID` is set.** For a **scion-inclusive** row (`Scion_Variety_ID` set, with `Unit_Price`), `Quantity` records the scion material received and is not part of the seedling total.

`Stock_In` does **not** require a `Batch_ID` because Stock In records the receiving event before seedlings are established as a Batch. There is **no** "type"/discriminator column: the presence of `Scion_Variety_ID` is itself the signal that a row includes scion.

**No stock balance [PROPOSED — pending client confirmation]** (OD-8): the system keeps **no seedling stock balance** and does **not** enforce Stock In → Batch traceability. This is intentional and consistent with "no stock balance".

**Freshness rule — not implemented [PROPOSED — pending client confirmation]** (OD-13): there is no usable-until value, no warning, and no blocking. This remains pending client confirmation of (a) the allowed freshness window and (b) whether the rule should warn or block.

---

### 3.8 `Batch`

| Key | Attribute |
|---|---|
| PK | `Batch_ID` |
| FK | `Scion_Variety_ID` *(nullable when not yet assigned)* |
| FK | `Parent_Batch_ID` *(nullable — set for a regraft sub-batch)* |
| FK | `User_ID` |
| — | `Batch_Code` |
| — | `Total_Grafted` |
| — | `Regraft` |
| — | `Available_Count` |
| — | `Grafted_Date` |
| — | `Created_At` |
| — | `Status` |
| — | `Archived_At` *(nullable)* |

**Purpose:** Represents an established grafted cacao Batch. A regraft sub-batch is linked to its parent Batch through `Parent_Batch_ID` and has its own independent 21-day cycle.

**Important:** Batch Monitoring is not a separate entity. The Batch remains the operational record for the grafted cacao cycle. A parent Batch and each regraft sub-batch are represented within the same `Batch` entity.

**`Total_Grafted` — CONFIRMED (2026-09-29):** `Total_Grafted` is a **cumulative, non-decreasing log** of the plants grafted into the batch. It is **not** reduced by a regraft and **not** reduced by Mortality; each regraft is recorded as a new Grafted entry (sub-batch `Total_Grafted = q`) alongside a Re Graft entry. The batch's **still-attributable/live population** for mortality and regraft purposes is **`Total_Grafted − Regraft`**. Client evidence: the spreadsheet's Regraft row carries a new Grafted entry plus a Re Graft entry on the same row, and the client confirmed *"Grafted minus Regraft = mortality or yield for the parent batch"*. This is the basis of the mortality cap (OD-4), the regraft cap (OD-6) and the crude-mortality-rate denominator (OD-10), which is `SUM(Total_Grafted − Regraft)` — each physical plant counted exactly once.

**Available rule [PROPOSED — pending client confirmation]** (OD-3): `Available_Count` is the number of healthy plants on hand for pull-out. It starts equal to `Total_Grafted` for a new batch; Mortality reduces it; Stock Out reduces it; and a regraft of quantity q reduces the source batch's `Available_Count` by q (its `Total_Grafted` is unchanged — it is a cumulative log) and starts the sub-batch with `Available_Count = q`. Invariant: `0 ≤ Available_Count ≤ Total_Grafted`. (See PRD §11 for the Gap Report consequence of the current implementation, which does not reduce `Available_Count` on Mortality.)

**Regraft rule** (OD-6 — the cap and the sub-batch `Total_Grafted` are **CONFIRMED**, 2026-09-29; the remaining code-format details are **[PROPOSED — pending client confirmation]**): a regraft sub-batch uses a linked `Parent_Batch_ID`, receives its own `Batch_Code` formed as the source `Batch_Code` + `-R` + the next sequence number for that source (for example `B001-R1`, and `B001-R1-R1` for a sub-batch of a sub-batch), starts a new independent 21-day cycle, and may reference a different `Scion_Variety_ID`. `Batch_Code` must be **unique**. The sub-batch's initial `Total_Grafted` equals its `Available_Count`, which equals the regraft quantity. Its `Grafted_Date` is the regraft date. A regraft quantity must not exceed the source batch's still-attributable surviving population, `min(Available_Count, (Total_Grafted − Regraft) − effective Mortality)` — a dead root stock cannot be regrafted. **Scion Variety is required** on the sub-batch.

**Regraft cached total [PROPOSED — pending client confirmation]** (OD-12): `Regraft` is a cached total that must equal `SUM(Regraft_Record.Quantity)` for that source batch, maintained inside the regraft transaction.

**21-day cycle [PROPOSED — pending client confirmation]** (OD-7): the cycle is **not stored**. The cycle end is derived as `Grafted_Date + 21 days`, and "during cycle" vs "after cycle" mortality is derived from `Mortality_Record.Recorded_At`. `Status` is an enumerated value; the current implementation uses `active` and `inactive`.

---

### 3.9 `Scion_Variety`

| Key | Attribute |
|---|---|
| PK | `Scion_Variety_ID` |
| — | `Scion_Variety_Name` |
| — | `Description` |
| — | `Archived_At` *(nullable)* |

**Purpose:** Stores the scion varieties used when grafting or regrafting. This replaces the separate generic `Scions` concept and the old separate `Variety` sidebar. It holds **no stock balance**; selection is informational (OD-14).

---

### 3.10 `Mortality_Record`

| Key | Attribute |
|---|---|
| PK | `Mortality_ID` |
| FK | `Batch_ID` |
| FK | `User_ID` |
| — | `Quantity` |
| — | `Cause` |
| — | `Recorded_At` |
| — | `Plant_Condition` *(Root Stock / Scion)* **[PROPOSED — pending client confirmation]** (OD-2) |

**Purpose:** Records mortality during the 21-day cycle or when a plant dies after the cycle is completed.

**Business rule:** A dead root stock cannot be regrafted and is counted as Mortality. Mortality is strictly incrementing: each recorded death increases the mortality total for the specific Batch or sub-batch where the death occurred. Mortality logging does not decrease or modify the Regraft counter, and a Regraft does not offset Mortality.

**Plant condition [PROPOSED — pending client confirmation]** (OD-2): `Plant_Condition` records whether the death was a root stock or a scion. Open question for the client: what a **scion**-condition death means for the regraft flow (see PRD §19). If scion deaths are handled only through Regraft, the PRD requirement to record scion condition should be removed instead.

---

### 3.11 `Regraft_Record`

| Key | Attribute |
|---|---|
| PK | `Regraft_ID` |
| FK | `Batch_ID` *(the source batch)* |
| FK | `User_ID` |
| FK | `Sub_Batch_ID` *(nullable — the sub-batch this record created)* **[PROPOSED — pending client confirmation]** (OD-11) |
| — | `Quantity` |
| — | `Regraft_Date` |
| — | `Remark` |

**Purpose:** Records a **Regraft**: replacing a **dead scion on a living root stock**. It does **not** offset Mortality, and dead root stock is never regrafted. **Regraft is recorded internally as a `Regraft_Record`** (table `regraft_records`; renamed from `Replanting_Record` / `replanting_records` on 2026-09-30) — there is no separate Regraft event table. The regrafting transaction deducts the quantity from the parent Batch's live `Available_Count`, increases the parent `Regraft` counter by the same quantity, and creates the linked sub-batch. The parent Batch's `Total_Grafted` is **not** changed — it is a cumulative, non-decreasing log (CONFIRMED, 2026-09-29; see §3.8).

**Transaction rule:** Parent-batch update, `Regraft_Record` creation, and sub-batch creation must succeed or fail together. The completed regraft/sub-batch operation is recorded in `Activity_Log`. `Batch.Regraft` is the cached total described in §3.8 (OD-12).

**Note:** `Parent_Batch_ID` on this record is **not** used (it duplicates `Batch.Parent_Batch_ID`); the proposed `Sub_Batch_ID` identifies the sub-batch the record created (OD-11), while `Batch_ID` is the source batch.

**History:** the entity was renamed from `Replanting_Record` to `Regraft_Record` on 2026-09-30 (same table, `regraft_records`; columns `regraft_id` / `regraft_date`). The earlier brief `regraft_records` proposal and its retraction are recorded in **Appendix A — Decision Log**.

---

### 3.12 `Inventory`

| Key | Attribute |
|---|---|
| PK | `Inventory_ID` |
| FK | `Batch_ID` |
| — | `Inventory_Date` |
| — | `Total_Grafted` |
| — | `Regraft` |
| — | `Mortality` |
| — | `Status` |
| — | `Last_Update` |

**Purpose:** Records the company's monthly Inventory status for established grafted cacao Batches.

The Inventory record follows the company's existing monthly record and keeps the important counts used in the business process: **Total Grafted, Regraft, and Mortality**.

**Snapshot rule [PROPOSED — pending client confirmation]** (OD-9): Inventory values are **captured from actual records at snapshot time (point-in-time, frozen)**, not re-derived at query time. There is **one snapshot per batch or sub-batch per month**. "Applicable current quantity" maps to `Batch.Available_Count`.

The Batch and Scion Variety remain the source records for batch identity and scion variety; Inventory records the monthly operational status of the Batch or regraft sub-batch.

---

### 3.13 `Client`

| Key | Attribute |
|---|---|
| PK | `Client_ID` |
| — | `Client_Name` |
| — | `Contact_Number` |
| — | `Address` |
| — | `Archived_At` *(nullable)* |

**Purpose:** Stores the clients receiving cacao through Stock Out / Pull Out.

---

### 3.14 `Stock_Out`

| Key | Attribute |
|---|---|
| PK | `Stock_Out_ID` |
| FK | `Client_ID` |
| FK | `User_ID` |
| — | `Release_Date` |
| — | `Total_Amount` *(nullable)* |

**Purpose:** Stores the Stock Out / Pull Out transaction header.

A Stock Out is made by counting healthy cacao from the applicable Batch and recording the cacao delivered to a Client.

---

### 3.15 `Stock_Out_Detail`

| Key | Attribute |
|---|---|
| PK | `Stock_Out_Detail_ID` |
| FK | `Stock_Out_ID` |
| FK | `Batch_ID` |
| FK | `Scion_Variety_ID` |
| — | `Quantity` |
| — | `Unit` |
| — | `Unit_Price` *(nullable)* |
| — | `Amount` *(nullable)* |

**Purpose:** Stores each variety/batch quantity within a Stock Out.

This supports multiple Scion Varieties in one Pull Out, matching the company's existing records such as Batangas, Toledo, and White Ranch appearing under one Stock Out date and delivery.

---

## 4. Entity Relationships

```text
Users
 ├──< Activity_Log
 ├──< Analytics
 ├──< Task_Complete
 ├──< Stock_In
 ├──< Batch
 ├──< Mortality_Record
 ├──< Regraft_Record
 └──< Stock_Out

Supplier
 └──< Stock_In

Stock_In
 ├── records the received quantity before Batch establishment (no Stock In → Batch traceability)
 └── when Scion_Variety_ID is set, records a scion receiving (no stock balance)

Scion_Variety
 ├──< Batch
 ├──< Stock_Out_Detail
 └──< Stock_In

Batch
 ├──< Activity_Log
 ├──< Mortality_Record
 ├──< Regraft_Record
 ├──< Inventory
 ├──< Stock_Out_Detail
 └──< Batch (sub-batch; Parent_Batch_ID)

Stock_Out
 ├── belongs to Client
 └──< Stock_Out_Detail

Client
 └──< Stock_Out

Stock_Out_Detail
 ├── belongs to Stock_Out
 ├── references Batch
 └── references Scion_Variety

Activity_Task
 └──< Task_Complete
```

---

## 5. Quantity Flow

### Stock In

```text
Ordered Quantity (context only — not stored)
      ↓
Received Quantity
      ↓
Establish Batch
```

Example (context only):

```text
Ordered   = 500
Received  = 460

500 ≠ 460
```

The ordered quantity is context for why the two may differ; it is **not stored** **[PROPOSED — pending client confirmation]** (OD-1). The actual **Received Quantity** is available for the batch-establishment step, where `Total_Grafted` is entered.

### Batch / Inventory

```text
Received Quantity (available for establishing batches)
      ↓
Establish Batch
      ↓
Total Grafted
      ↓
Mortality / Regraft
      ↓
Parent Batch ──→ Regraft Sub-Batch
      ↓                ↓
Monthly Inventory   Independent 21-Day Cycle
```

The 21-day cycle is **not stored**; the cycle end is derived as `Grafted_Date + 21 days` **[PROPOSED — pending client confirmation]** (OD-7).

---

### Stock Out / Pull Out

```text
Healthy cacao in Batch
          ↓
   Count for Pull Out
          ↓
     Stock Out
          ↓
       Client
```

A Stock Out can contain multiple detail rows, for example:

```text
Stock Out
 ├── Batangas   710
 ├── Toledo     740
 └── White Ranch  0

Total = 1,450
```

---

## 6. Removed From the Previous ERD

The following structures are intentionally removed because they are no longer part of the current process:

- `Batch_Monitoring`
- `Variety`
- Generic `Scions` stock pool
- `Pot_Type`
- `Mortality_Adjustments`
- `Stock_Reversal`
- `Scion_Receiving` (merged into `Stock_In`, §3.7)
- Batch `Monitoring_Start_Date`
- Scion stock receiving/used/unused inventory logic
- Separate Batch Monitoring status/history logic
- Stock Reversal workflow
- Any separate inventory for individual scions

The current company terms **Batches, Inventory, Mortality, Regraft, Stock In, Stock Out, and Scion Variety** remain in use.

---

## 7. Current Sidebar Supported by This ERD

**Terminology note:** user-facing screens use **Regraft**; the internal entity/table name is `Regraft_Record` / `regraft_records` (renamed from `Replanting_Record` on 2026-09-30).

### Dashboard

### Operational
- Daily Checklist
- Mortality Tracking
- Regraft
- Batches
- Scion Variety

### Inventory
- Stock In
- Stock Out
- Inventory

### Management
- Supplier
- Client

### Analytics
- Operational Analytics
- Tabular Deliverables
- Stock Ledger
- Root Cause Matrix

### Historical
- Seasonality Heatmap
- Age Profile
- Gap Report

### Account
- Activity History
- User Management
- Profile
- Logout

---

## 8. ERD Design Principle

The ERD should represent the company's actual workflow without creating duplicate processes:

**Stock In records the seedlings actually received; Batch represents the grafted cacao; Scion Variety supports grafting/regrafting; Mortality records deaths against the exact Batch or sub-batch and strictly increments; Regraft (recorded internally as `Regraft_Record`) replaces a dead scion on a living root stock and creates linked sub-batches without offsetting Mortality; Inventory records the monthly Batch/sub-batch status; and Stock Out / Pull Out records healthy cacao delivered to Clients.**

---

## Appendix A — Decision Log

This appendix preserves development history that is not part of the current data model, so the entities above stay clean.

### A.1 `regraft_records` / `Regraft_Record` — proposed then retracted (P5 Part B); name later adopted (2026-09-30)

An additional `regraft_records` / `Regraft_Record` table was briefly proposed and created during the P5 analytics work, but it duplicated `Replanting_Record`. After confirming the duplication, it was retracted: the table held **0 rows**, so it was dropped with **no data migration** needed, and the Stock Ledger's Regraft column read `Replanting_Record` directly.

On **2026-09-30** the `Replanting_Record` entity was **renamed** to `Regraft_Record` (table `replanting_records` → `regraft_records`; `replanting_id` → `regraft_id`; `replanting_date` → `regraft_date`) so the internal name matches the company term used throughout the UI. This is the *same* single table (no duplicate was reintroduced); the earlier P5 table had already been dropped. See §3.11.
