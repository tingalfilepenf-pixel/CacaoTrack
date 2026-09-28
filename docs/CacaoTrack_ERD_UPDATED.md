# CacaoTrack ERD — Revised Core Data Model

## 1. Purpose

This ERD keeps only the entities and relationships needed for the current CacaoTrack business process. It follows the company's current operational terms and does not introduce a separate Batch Monitoring process.

## 2. Current Business Process

```text
ORDER SEEDLINGS
      ↓
STOCK IN (RECEIVED QUANTITY)
      ↓
ESTABLISH BATCH / GRAFT
      ↓
CHECK SCION VARIETY AVAILABILITY
      ↓
MORTALITY / REGRAFT (REPLANTING)
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
2. The **received quantity may differ from the originally ordered quantity**. The received `Quantity` is the quantity available for establishing production batches.
3. **Batches** represent grafted cacao established from the received seedlings.
4. **Scion Variety** is used when grafting/regrafting requires a particular scion variety.
5. If the **root stock is dead**, it cannot be regrafted and is recorded as **Mortality**.
6. Mortality is a **strictly incrementing counter** and is recorded against the exact Batch or sub-batch where the death occurred. Recording mortality does not decrease or modify the Regraft counter.
7. **Replanting / Regraft** records replacement activity for the applicable Batch. When regrafting occurs, the quantity is deducted from the parent Batch's `Total_Grafted`, the parent `Regraft` counter increases by the same quantity, and a linked sub-batch is created.
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

**Purpose:** Records a receiving event. It covers both seedling Stock In and scion receiving, because the client confirmed these are the same real-world event (see §3.16).

**Business rule:** `Quantity` records the seedlings actually received. It may differ from the originally ordered quantity. The received quantity is immediately eligible for establishing production batches; there is no five-day wait or separate seedling viability count.

`Stock_In` does **not** require a `Batch_ID` because Stock In records the receiving event before seedlings are established as a Batch. There is **no** "type"/discriminator column: the presence of `Scion_Variety_ID` is itself the signal that a row includes scion.

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

**Regraft rule:** A regraft sub-batch uses a linked `Parent_Batch_ID`, receives its own `Batch_Code` such as `B001-R1`, starts a new independent 21-day cycle, and may reference a different `Scion_Variety_ID`.

---

### 3.9 `Scion_Variety`

| Key | Attribute |
|---|---|
| PK | `Scion_Variety_ID` |
| — | `Scion_Variety_Name` |
| — | `Description` |
| — | `Archived_At` *(nullable)* |

**Purpose:** Stores the scion varieties used when grafting or regrafting. This replaces the separate generic `Scions` concept and the old separate `Variety` sidebar.

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

**Purpose:** Records mortality during the 21-day cycle or when a plant dies after the cycle is completed.

**Business rule:** A dead root stock cannot be regrafted and is counted as Mortality. Mortality is strictly incrementing: each recorded death increases the mortality total for the specific Batch or sub-batch where the death occurred. Mortality logging does not decrease or modify the Regraft counter.

---

### 3.11 `Replanting_Record`

| Key | Attribute |
|---|---|
| PK | `Replanting_ID` |
| FK | `Batch_ID` |
| FK | `User_ID` |
| — | `Quantity` |
| — | `Replanting_Date` |
| — | `Remark` |
| — | `Parent_Batch_ID` *(nullable — identifies the parent for a regraft sub-batch)* |

**Purpose:** Records replacement/replanting activity associated with a Batch after mortality or other applicable losses. **Regraft and Replanting are the same recorded procedure** — a Regraft is recorded as a `Replanting_Record`; there is no separate Regraft event table. The regrafting transaction deducts the quantity from the parent Batch's `Total_Grafted`, increases the parent `Regraft` counter by the same quantity, and creates the linked sub-batch.

**Transaction rule:** Parent-batch update, `Replanting_Record` creation, and sub-batch creation must succeed or fail together. The completed regraft/sub-batch operation is recorded in `Activity_Log`.

**Retraction note (P5 Part B):** an additional `regraft_records` / `Regraft_Record` table was briefly proposed and created during the P5 analytics work, but it duplicated `Replanting_Record`. It was retracted after confirming the duplication — the table held **0 rows**, so it was dropped with **no data migration** needed. The Stock Ledger's Regraft column reads `Replanting_Record` directly.

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

### 3.16 `Scion_Receiving` — REMOVED (merged into `Stock_In`)

`Scion_Receiving` was introduced as an additive receiving log and has now been **retired**: the client confirmed seedling Stock In and Scion Receiving are the **same real-world receiving event**, so scion receiving was merged into `Stock_In` (§3.7) rather than kept as a separate table — avoiding the same duplication the project already resolved once for Regraft/Replanting. A receiving is therefore recorded as a `Stock_In` row with `Scion_Variety_ID` (and `Unit_Price`) set; a pure-seedling delivery leaves those columns null. There is no separate entity, no separate UI page, and no discriminator column.

**Freshness rule — still NOT implemented:** no usable-until value, no warning, and no blocking. This remains pending client confirmation of (a) the allowed freshness window and (b) whether the rule should warn or block.

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
 ├──< Replanting_Record
 └──< Stock_Out

Supplier
 └──< Stock_In

Stock_In
 ├── records the received Quantity before Batch establishment
 └── when Scion_Variety_ID is set, records a scion receiving (no stock balance)

Scion_Variety
 ├──< Batch
 ├──< Stock_Out_Detail
 └──< Stock_In

Batch
 ├──< Activity_Log
 ├──< Mortality_Record
 ├──< Replanting_Record
 ├──< Inventory
 └──< Stock_Out_Detail

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
Ordered Quantity
      ↓
Received Quantity
      ↓
Establish Batch
```

Example:

```text
Ordered   = 500
Received  = 460

500 ≠ 460
```

The actual **Received Quantity** is carried into the batch-establishment step.

### Batch / Inventory

```text
Received Quantity
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

The current company terms **Batches, Inventory, Mortality, Replanting, Stock In, Stock Out, and Scion Variety** remain in use.

---

## 7. Current Sidebar Supported by This ERD

### Dashboard

### Operational
- Daily Checklist
- Mortality Tracking
- Replanting
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

**Stock In records the seedlings actually received; Batch represents the grafted cacao; Scion Variety supports grafting/regrafting; Mortality records deaths against the exact Batch or sub-batch and strictly increments; Replanting records regraft activity and creates linked sub-batches; Inventory records the monthly Batch/sub-batch status; and Stock Out / Pull Out records healthy cacao delivered to Clients.**
