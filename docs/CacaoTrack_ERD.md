# CacaoTrack ERD — Revised Core Data Model

## 1. Purpose

This ERD keeps only the entities and relationships needed for the current CacaoTrack business process. It follows the company's existing terms and does not introduce a separate Batch Monitoring process or rename the company's operational terms.

## 2. Current Business Process

```text
ORDER SEEDLINGS
      ↓
WAIT 5 DAYS
      ↓
COUNT VIABLE SEEDLINGS
      ↓
STOCK IN
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

1. The **ordered quantity is not automatically the Viable quantity**. After five days, the seedlings are recounted and only the usable/Viable quantity is recorded for the next process.
2. **Stock In** represents the seedlings received/recorded after the five-day checking process.
3. **Batches** represent the grafted cacao established from the usable seedlings.
4. **Scion Variety** is used when grafting/regrafting requires a particular scion variety.
5. If the **root stock is dead**, it cannot be regrafted and is recorded as **Mortality**.
6. Mortality may also record a dead scion according to the company's mortality process.
7. **Replanting** records replacement activity for the batch when applicable.
8. **Inventory** is recorded per month and follows the established batches, including the company's existing production counts such as Total Grafted, Viable, Regraft, and Mortality.
9. **Stock Out / Pull Out** counts healthy cacao from the batch and records the cacao delivered to a Client.
10. One Stock Out may contain multiple **Scion Variety** quantities, matching the company's pull-out records.

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
| — | `Viable` |
| — | `Unit_Price` *(nullable — scion only)* |
| — | `Origin` |
| — | `Notes` *(nullable — scion receipt notes)* |
| — | `Date_Received` |

**Purpose:** Records a receiving event. It covers both seedling Stock In and scion receiving, because the client confirmed these are the same real-world event (see §3.16).

**Business rule:** `Quantity` is the ordered/received quantity; for a **seedling** row `Viable` is recorded after the five-day recount and does not have to equal `Quantity`. For a **scion-inclusive** row (`Scion_Variety_ID` set, with `Unit_Price`) `Viable` is set equal to `Quantity` at entry — scion is perishable and used immediately, so it has no five-day recount.

`Stock_In` does **not** require a `Batch_ID` because the five-day viability count occurs before seedlings are established as a Batch. There is **no** "type"/discriminator column: the presence of `Scion_Variety_ID` is itself the signal that a row includes scion.

---

### 3.8 `Batch`

| Key | Attribute |
|---|---|
| PK | `Batch_ID` |
| FK | `Scion_Variety_ID` *(nullable when not yet assigned)* |
| FK | `User_ID` |
| — | `Batch_Code` |
| — | `Total_Grafted` |
| — | `Viable_Count` |
| — | `Available_Count` |
| — | `Grafted_Date` |
| — | `Created_At` |
| — | `Status` |
| — | `Archived_At` *(nullable)* |

**Purpose:** Represents the established grafted cacao Batch created from usable/Viable seedlings.

**Important:** Batch Monitoring is not a separate entity. The Batch remains the operational record for the grafted cacao cycle.

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

**Purpose:** Records mortality during the Batch cycle.

**Business rule:** A dead root stock cannot be regrafted and is counted as Mortality. Mortality can also record the applicable dead scion condition.

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

**Purpose:** Records replacement/replanting activity associated with a Batch after mortality or other applicable losses. **Regraft and Replanting are the same recorded procedure** — a Regraft is recorded as a `Replanting_Record`; there is no separate Regraft event table.

**Retraction note (P5 Part B):** an additional `regraft_records` / `Regraft_Record` table was briefly proposed and created during the P5 analytics work, but it duplicated `Replanting_Record`. It was retracted after confirming the duplication — the table held **0 rows**, so it was dropped with **no data migration** needed. The Stock Ledger's Regraft column reads `Replanting_Record` directly.

---

### 3.12 `Inventory`

| Key | Attribute |
|---|---|
| PK | `Inventory_ID` |
| FK | `Batch_ID` |
| — | `Inventory_Date` |
| — | `Total_Grafted` |
| — | `Viable` |
| — | `Regraft` |
| — | `Mortality` |
| — | `Status` |
| — | `Last_Update` |

**Purpose:** Records the company's monthly Inventory status for established grafted cacao Batches.

The Inventory record follows the company's existing monthly record and keeps the important counts used in the business process: **Total Grafted, Viable, Regraft, and Mortality**.

The Batch and Scion Variety remain the source records for batch identity and scion variety; Inventory records the monthly operational status of the Batch.

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

**Freshness/viability rule — still NOT implemented:** no usable-until value, no warning, and no blocking. This remains pending client confirmation of (a) the allowed viability window and (b) whether the rule should warn or block.

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
 ├── records the received vs usable/Viable supply before Batch establishment
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
Ordered / Received Quantity
          ↓
       5 days
          ↓
       Viable
          ↓
     Establish Batch
```

Example:

```text
Quantity = 500
Viable   = 460

500 ≠ 460
```

Only the actual **Viable** result is carried into the next production step.

### Batch / Inventory

```text
Viable seedlings
      ↓
Establish Batch
      ↓
Total Grafted
      ↓
Graft / Regraft
      ↓
Viable / Regraft / Mortality
      ↓
Monthly Inventory record
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

The existing company terms **Batches, Viable, Inventory, Mortality, Replanting, Stock In, Stock Out, and Scion Variety** remain in use.

---

## 7. Current Sidebar Supported by This ERD

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

**Stock In records the usable seedling supply after the five-day viability check; Batch represents the grafted cacao; Scion Variety supports grafting/regrafting; Mortality and Regraft record what happens during the cycle; Inventory records the monthly Batch status; and Stock Out / Pull Out records healthy cacao delivered to Clients.**
