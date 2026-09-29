# CacaoTrack Product Requirements Document (PRD)

## Cacao Mortality Tracking and Inventory System with Data Analytics

**System Name:** CacaoTrack  
**Document Type:** Product Requirements Document  
**Version:** 2.0 — Client-Approved Baseline (corrected)  
**Date:** 2026-09-28  
**Frontend:** Laravel Blade, Bootstrap 5, JavaScript, ECharts  
**Backend:** Laravel, PHP  
**Database:** MySQL

**Change log:**

- **2.0 — 2026-09-28:** Corrected the client-approved baseline (`CacaoTrack_PRD_UPDATED.md`) for internal PRD/ERD contradictions and undefined rules. **No client-approved rule was changed.** Every new, unconfirmed rule carries the `[PROPOSED — pending client confirmation]` marker (rendered in bold) with an `(OD-n)` reference and is collected in **§19 Open Decisions**. The ERD (`CacaoTrack_ERD.md`) was corrected in step.
- **2.0 — 2026-09-29 (confirmed):** `Total_Grafted` confirmed as a **cumulative, non-decreasing log** (client spreadsheet evidence + direct statement *"Grafted minus Regraft = mortality or yield for the parent batch"*); a batch's live population for mortality/regraft is `Total_Grafted − Regraft`. Updated §2.4, §10, §11 and §19 (`OD-4`, `OD-6`, `OD-10`); ERD §2/§3.8/§3.11 in step.
- **1.0:** Client-approved revised working PRD. Preserved untouched as provenance in `CacaoTrack_PRD_UPDATED.md`.

---

## 1. Product Overview

CacaoTrack is a web-based system for recording and managing cacao nursery operations. It supports the company's actual workflow from ordering seedlings, recording received seedlings through Stock In, establishing grafted batches, recording grafting and regrafting activities including linked regraft sub-batches, recording mortality, managing inventory records, and processing pull-out/stock-out transactions for clients.

The system also provides operational analytics, historical analysis, tabular deliverables, and activity history using actual operational data.

### Core business flow

**Order Seedlings → Stock In → Establish Batches → Graft/Regraft → Mortality Recording → Inventory → Pull-Out/Stock Out → Client**

---

## 2. Business Process Requirements

### 2.1 Order Seedlings

The company first orders seedlings.

The ordered quantity is **context only and is not stored by the system** **[PROPOSED — pending client confirmation]** (OD-1). It is referenced only to explain that the received quantity may differ from it. The received quantity is recorded directly during Stock In and may differ from the original ordered quantity. (An alternative — storing a nullable `Ordered_Quantity` on Stock In — is recorded in §19 Open Decisions.)

Example (context only):

- Ordered: 500 seedlings
- Received: 460 seedlings

The received quantity recorded during Stock In is the quantity entering the next production step.

### 2.2 Stock In

Stock In records the seedlings received from the supplier immediately after the order is fulfilled. The same receiving event also covers scion material, because the client confirmed that seedling Stock In and scion receiving are the **same real-world receiving event**.

Stock In fields:

- Supplier *(optional for a scion receipt without a formal supplier)*
- Quantity — the number of seedlings (or scion material) received in this delivery
- Date — the date the delivery was received
- Origin
- Scion Variety *(optional — set only when the receiving includes scion)*
- Unit Price *(optional — scion only)*
- Notes *(optional — scion receipt notes)*

The system must allow the received Quantity to differ from the original ordered quantity. A single Stock In receipt may include scion by setting the optional Scion Variety, Unit Price, and Notes.

### 2.3 Establishing Batches

Received seedlings are used to establish batches. A batch represents seedlings that have been established/grafted as cacao.

Batch records must remain the central operational record for the grafted cacao.

### 2.4 Grafting and Regrafting

Grafting is part of establishing and maintaining a batch.

A **Regraft** replaces a dead scion on a **living** root stock. When regrafting is required, the system uses the available **Scion Variety** according to the required variety. **CONFIRMED (2026-09-29) — client spreadsheet evidence:** a regraft is recorded as a **new Grafted entry plus a Re Graft entry on the same row**, so **`Total Grafted` is a cumulative, non-decreasing log per batch and is never reduced by a regraft**. A regraft deducts the quantity from the parent batch's live **Available** count and increases the parent batch's **Regraft** counter by the same quantity. The batch's **still-attributable/live population** for mortality and regraft purposes is **`Total Grafted − Regraft`** (the client's rule: *"Grafted minus Regraft = mortality or yield for the parent batch"*). The regrafted plants establish a new linked **sub-batch** under the parent batch (for example, appending `-R1` to the parent **Batch Code**, such as `B001-R1`) whose own `Total Grafted` equals the regraft quantity. The sub-batch starts its own independent 21-day cycle and may be assigned a different Scion Variety than the parent.

Sub-batch rules **[PROPOSED — pending client confirmation]** (OD-6):

- The sub-batch's initial **Total Grafted** equals its **Available** count, which equals the regraft quantity.
- The sub-batch **Grafted Date** is the regraft date.
- The source batch may itself be a sub-batch (so codes such as `B001-R1-R1` are valid).
- The sub-batch **Batch Code** is the source Batch Code plus `-R` plus the next sequence number for that source.
- **Batch Code must be unique.**
- The regraft quantity must not exceed the source batch's Available count.
- **Scion Variety is required** on the sub-batch.

Scion Variety is maintained as operational master data and is used when recording grafting/regrafting activities.

### 2.5 Mortality

Mortality is recorded as a strictly incrementing counter whenever a plant dies during the production cycle or for applicable deaths after the 21-day cycle. Mortality is recorded against the specific Batch or regraft sub-batch where the death actually occurred.

The system must distinguish the relevant plant condition when recording mortality, including root stock and scion where applicable. The ERD records this as a proposed `Plant_Condition` (Root Stock / Scion) column on `Mortality_Record` **[PROPOSED — pending client confirmation]** (OD-2); see §19 for the open question about what a scion-condition death means for the regraft flow.

A dead root stock cannot be regrafted. Once the root stock is dead, it is recorded as Mortality rather than being processed as a regraft. Recording Mortality does not decrease or otherwise affect the Batch's **Regraft** counter, and a Regraft does not offset Mortality.

### 2.6 21-Day Cycle

Batches follow the company's 21-day production cycle.

The system shall retain the 21-day cycle information needed for operational records and analytics, but **Batch Monitoring is not a separate sidebar/module**.

The 21-day cycle is handled through the Batch, Mortality Tracking, Regraft, and related operational records. Each regraft sub-batch has its own independent 21-day cycle.

21-day cycle rules **[PROPOSED — pending client confirmation]** (OD-7):

- The cycle is **not stored** as a column. The cycle end is derived as `Grafted_Date + 21 days`.
- Whether a mortality record falls during or after the cycle is derived from `Mortality_Record.Recorded_At` against the batch's derived cycle end.
- The Age Profile uses each batch's own `Grafted_Date`.
- `Batch.Status` is an enumerated value; the current implementation uses `active` and `inactive`.

### 2.7 Inventory

Inventory records the monthly status of established grafted cacao batches and their linked regraft sub-batches.

The inventory record follows the company's existing operational record and includes information such as:

- Batch
- Date Grafted
- Total Grafted
- Scion Variety
- Regraft
- Mortality

Inventory values are captured from actual operational records at snapshot time (point-in-time, frozen). A monthly snapshot is captured once per batch or sub-batch per month **[PROPOSED — pending client confirmation]** (OD-9).

### 2.8 Pull-Out / Stock Out

For pull-out/Stock Out, the company counts the healthy cacao from the applicable batch and sells/delivers the cacao to a client.

Stock Out must support multiple Scion Varieties in one transaction.

Stock Out fields:

- Date
- Delivered To
- Quantity
- Units
- Scion Variety
- Unit Price *(optional)*
- Amount *(optional)*
- Total *(optional)*

The system must calculate the transaction total where pricing is provided and must prevent a quantity greater than the applicable available quantity from being released.

Example:

| Scion Variety | Quantity |
|---|---:|
| Batangas | 710 |
| Toledo | 740 |
| White Ranch | 0 |
| **Total** | **1,450** |

The Stock Out transaction represents the pull-out/delivery of cacao to the client; it does not represent the original seedling order.

---

## 3. Product Goals

CacaoTrack shall:

1. Improve the accuracy and efficiency of recording cacao nursery operations.
2. Record received seedlings directly during Stock In while allowing the received quantity to differ from the original ordered quantity.
3. Maintain accurate batch, grafting, regrafting, sub-batch, mortality, regraft, inventory, and Stock Out records.
4. Support the company's 21-day production cycle without creating a separate Batch Monitoring module.
5. Provide accurate monthly inventory information.
6. Provide operational analytics, historical analytics, and tabular deliverables based on actual data.
7. Maintain traceable user activity and validated transactions.

---

## 4. Technology Requirements

### Frontend

- Laravel Blade
- Bootstrap 5
- JavaScript
- ECharts

The existing frontend should be refined rather than unnecessarily rebuilt.

### Backend

- Laravel
- PHP
- Eloquent ORM

### Database

- MySQL
- Relational and normalized database design

---

## 5. Main Navigation

The sidebar shall use the following structure.

**Terminology note:** user-facing screens use **Regraft**; the internal entity/table name remains `Replanting_Record` / `replanting_records`.

### Dashboard

- Dashboard

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

### Removed from the previous PRD/navigation

The following are not separate system modules/sidebar items:

- Batch Monitoring
- Variety
- Stock Reversal
- Scion
- Pot Type

**Scion Variety** is the retained term/module for scion-variety information.

---

## 6. Functional Requirements

### FR-01 Authentication

The system shall allow authorized users to:

- Log in
- Log out
- Maintain authenticated sessions
- Access authorized pages according to their role and permissions

Protected routes must require authentication.

---

### FR-02 User Management

Authorized users shall manage system accounts according to role permissions.

The system shall:

- Manage users
- Assign roles/permissions according to authorization rules
- Protect the Administrator system role
- Prevent unauthorized access to restricted operations
- Store passwords securely and never as plaintext

Authorization must be enforced server-side.

---

### FR-03 Daily Checklist

The system shall support:

- Daily task listing
- Task completion
- Completion date
- Remarks
- Verification where applicable
- User accountability

Daily checklist records must be traceable through Activity History where applicable.

---

### FR-04 Mortality Tracking

The system shall allow authorized users to record mortality information including:

- Batch or sub-batch
- User
- Quantity
- Cause
- Date/time
- Plant condition, including Root Stock or Scion **[PROPOSED — pending client confirmation]** (OD-2)

The system must validate mortality quantities against the applicable batch records.

Mortality must be available to analytics and reports.

#### Mortality business rules

- Mortality may occur during the 21-day cycle.
- Applicable mortality may also be recorded after the 21-day cycle.
- Mortality is a strictly incrementing counter used whenever a death happens.
- Mortality must be recorded against the specific Batch or regraft sub-batch where the death occurred.
- A dead root stock cannot be regrafted.
- Recording Mortality does not decrease or otherwise modify the Regraft counter.
- Mortality must not create an invalid or negative batch quantity: the mortality quantity must not exceed the batch's **Available** count at recording time. **[PROPOSED — pending client confirmation]** (OD-4)

---

### FR-05 Regraft

The system shall allow authorized users to record regrafting information including:

- Parent Batch or sub-batch
- User
- Quantity
- Regraft date
- Remarks

Regrafting must be connected to the applicable Batch or sub-batch, must create the linked regraft sub-batch (see §2.4), and must be reflected in operational analytics where required. The internal entity/table name remains `Replanting_Record` / `replanting_records`.

---

### FR-06 Batches

The Batch module shall manage the established/grafted cacao batches.

A batch may include information such as:

- Batch Code
- Parent Batch where applicable
- Date established/grafted
- Total Grafted
- Available
- Scion Variety
- Regraft information
- Mortality information
- Status
- Relevant dates

The existing batch record must be reused as the central source for batch-related operations. A regraft must create a linked sub-batch rather than an unrelated duplicate record. Each regraft sub-batch has its own 21-day cycle and may use a different Scion Variety from its parent. The proposed sub-batch rules — including the `B001-R1` Batch Code format — are listed in §2.4 **[PROPOSED — pending client confirmation]** (OD-6).

#### Batch process

```text
Received seedlings
      ↓
Establish Batch
      ↓
Total Grafted
      ↓
Graft / Regraft
      ↓
Parent Total Grafted − Regraft Quantity
      ↓
Regraft Counter + Regraft Quantity
      ↓
Create Linked Sub-Batch (-R1, -R2, ...)
      ↓
Independent 21-Day Cycle
      ↓
Mortality / Regraft as applicable
      ↓
Pull-Out / Stock Out
```

---

### FR-07 Scion Variety

The Scion Variety module shall maintain the scion varieties used by the company.

It shall support:

- Scion Variety records
- Active/Archived status
- Selection of Scion Variety during applicable grafting/regrafting operations
- Use of multiple Scion Varieties in Stock Out transactions where applicable

The system shall use the company's term **Scion Variety** consistently.

**Scope note:** there is no scion stock balance. Linking a Regraft to a specific scion receipt, and the scion freshness/viability rule, are **not part of this version** **[PROPOSED — pending client confirmation]** (OD-13, OD-14).

---

### FR-08 Inventory

The Inventory module shall provide the company's monthly inventory records for established grafted cacao batches and their linked regraft sub-batches.

Inventory shall support information including:

- Batch or sub-batch
- Parent Batch where applicable
- Date Grafted
- Total Grafted
- Scion Variety
- Regraft
- Mortality
- Applicable current quantity/status

Inventory values must be **captured from actual records at snapshot time (point-in-time, frozen)** and must remain consistent with Batch, Mortality Tracking, Regraft, and Stock Out transactions.

Inventory snapshot rules **[PROPOSED — pending client confirmation]** (OD-9):

- One snapshot per batch or sub-batch per month.
- **Applicable current quantity** maps to `Batch.Available_Count`.

#### Inventory rule

The initial batch quantity is established from the received Quantity recorded during Stock In. The ordered quantity alone must not be treated as the received quantity.

---

### FR-09 Stock In

Stock In shall record the receiving event immediately. A single receiving event covers both seedling Stock In and scion receiving, because the client confirmed these are the same real-world receiving event.

Fields:

- Supplier *(optional for a scion receipt without a formal supplier)*
- Quantity — the number of seedlings (or scion material) received
- Date received
- Origin
- Scion Variety *(optional — set only when the receiving includes scion)*
- Unit Price *(optional — scion only)*
- Notes *(optional — scion receipt notes)*

The system must allow the recorded received Quantity to differ from the ordered quantity.

The recorded Quantity is the quantity **available for establishing batches**; **Total Grafted is entered when the batch is established**, not carried automatically from Stock In.

Stock In only records the receipt. It does **not** create or update Batch or Inventory records, and it does not maintain a seedling stock balance or enforce Stock In → Batch traceability **[PROPOSED — pending client confirmation]** (OD-8).

---

### FR-10 Stock Out

Stock Out shall support the company's pull-out process.

Fields:

- Date
- Delivered To
- Quantity
- Units
- Scion Variety
- Unit Price *(optional)*
- Amount *(optional)*
- Total *(optional)*

A Stock Out transaction may contain multiple Scion Varieties.

The system must:

- Identify the applicable batch/available quantity (see §11 Available).
- Prevent release beyond the available quantity.
- Record the client/Delivered To information.
- Update the remaining quantity correctly.
- Record the transaction in Activity History where applicable.

---

### FR-11 Supplier Management

The system shall maintain supplier information such as:

- Supplier name
- Contact information
- Address
- Other necessary supplier details

Supplier records shall be usable in the ordering/Stock In process where applicable.

---

### FR-12 Client Management

The system shall maintain client information such as:

- Client name
- Contact information
- Address
- Other necessary client details

Client records shall be selectable during Stock Out.

---

### FR-13 Activity History

The system shall provide a read-only history of important system activities, including atomic regrafting and sub-batch creation.

Where applicable, activity records shall include:

- User
- Activity type
- Batch
- Description
- Date/time

Important traceable activities include:

- Stock In
- Scion receipts (a Stock In row that includes scion)
- Stock Out
- Mortality Recording
- Regrafting and sub-batch creation
- Daily Checklist completion
- Analytics/report generation where applicable

---

## 7. Validation Requirements

All important input and transaction operations must be validated.

#### Client-side validation

Use JavaScript for immediate feedback where appropriate:

- Required fields
- Numeric values
- Quantity format
- Date format
- Invalid selections

#### Server-side validation

Laravel must perform authoritative validation.

The system must reject:

- Negative quantities
- Invalid quantities
- Invalid foreign keys
- Invalid dates
- Unauthorized operations
- Invalid mortality quantities
- Invalid regraft quantities
- Stock Out quantities greater than the applicable available quantity
- Duplicate transaction submissions where applicable
- Logically inconsistent inventory states

#### Corrections to recorded transactions

Stock Reversal and Mortality Adjustments are not part of the system, so corrections follow this policy **[PROPOSED — pending client confirmation]** (OD-5):

- Transactional records are not editable by regular users.
- An Administrator may correct or remove a mistaken record.
- Every correction is recorded in Activity History with before/after values.
- A regraft that created a sub-batch with dependent Mortality, Stock Out, or Inventory records cannot be removed.

#### Database integrity

Use appropriate:

- Foreign keys
- Unique constraints
- NOT NULL constraints
- Data types
- Indexes
- Referential integrity

---

## 8. Transaction Integrity

Inventory and quantity-changing operations must be atomic.

#### Stock In

1. Validate the request.
2. Confirm authorization.
3. Record the Stock In transaction.
4. Record the receipt only (Stock In does not create or update Batch or Inventory records).
5. Record Activity History where required.
6. Commit the transaction.

#### Regrafting / Sub-Batch Creation

1. Validate the request and regraft quantity against the parent Batch.
2. Confirm authorization.
3. Lock the parent Batch and relevant quantity records as required.
4. Deduct the regraft quantity from the parent Batch's Total Grafted count.
5. Increase the parent Batch's Regraft counter by the same quantity.
6. Create the linked regraft sub-batch with its parent reference, independent 21-day cycle, and selected Scion Variety.
7. Record the regrafting/sub-batch creation in Activity History.
8. Commit the transaction.

If any required step fails, the entire regrafting/sub-batch creation transaction must roll back.

#### Stock Out

1. Validate the request.
2. Confirm authorization.
3. Identify the applicable batch and available quantity.
4. Verify the requested quantity does not exceed the available quantity.
5. Create the Stock Out transaction and its Scion Variety quantities.
6. Update the remaining quantity.
7. Record Activity History where required.
8. Commit the transaction.

If a required step fails, the transaction must roll back.

---

## 9. Analytics and Reports

All analytics and reports must use actual database data.

### 9.1 Operational Analytics

Operational Analytics shall provide useful summaries of operational data, such as:

- Inventory information
- Mortality information
- Regraft information
- Batch information
- Stock In/Stock Out information where applicable

All displayed values must be calculated from current system data.

---

### 9.2 Tabular Deliverables

The system shall provide readable tabular outputs for important operational information.

Tables should support filtering, sorting, and pagination where appropriate.

---

### 9.3 Stock Ledger

The Stock Ledger shall provide a traceable view of quantity movements and applicable batch information. Inventory and Stock Ledger tables shall support nested or grouped views so parent batches and their associated regraft sub-batches are clearly distinguishable.

Where applicable it may include:

- Batch or sub-batch
- Parent Batch where applicable
- Scion Variety
- Total Grafted
- Available
- Regraft
- Mortality
- Regraft (internal `Replanting_Record`)
- Stock In
- Stock Out
- Relevant dates

The ledger must be generated from actual operational data rather than maintaining duplicate reporting data.

---

### 9.4 Root Cause Matrix

The Root Cause Matrix shall analyze mortality by relevant dimensions such as:

- Mortality cause
- Batch
- Scion Variety
- Quantity
- Time period

The matrix should help identify recurring or significant mortality causes.

---

### 9.5 Historical Analytics

#### Seasonality Heatmap

Show mortality patterns across time, such as month and mortality cause, using actual historical records.

#### Age Profile

Show the age distribution of applicable batches/cacao using actual dates such as Date Grafted and the selected reporting date.

#### Gap Report

Show meaningful differences between defensible expected and actual values.

The system must not invent expected values. If the existing data cannot support a defensible gap calculation, the report must identify the data limitation instead of generating fabricated values.

**Open item:** the current implementation defines Gap as recorded mortality. Because Mortality is proposed to reduce Available (see §11 Available), the Gap Report definition must be re-confirmed together with that decision **[PROPOSED — pending client confirmation]** (OD-3).

---

## 10. Dashboard Requirements

The Dashboard shall provide concise operational information using current database data.

Possible dashboard information includes:

- Current Inventory
- Batch information
- Mortality summary
- Regraft summary
- Stock In summary
- Stock Out summary
- Operational alerts or attention items where supported by actual data

Dashboard values must use the same definitions as the corresponding modules and reports.

### Dashboard KPI definitions

The dashboard panel requires the following three KPIs. Definitions **[PROPOSED — pending client confirmation]** (OD-10):

- **Total Active Inventory** = `SUM(Available_Count)` across all batches and sub-batches, **including archived** (as currently implemented).
- **Crude Mortality Rate** = `SUM(Mortality quantity) ÷ SUM(Total_Grafted) × 100`, across all batches and sub-batches. **`Total_Grafted` — CONFIRMED (2026-09-29):** it is a cumulative, non-decreasing log, so it is **not** conserved across a regraft — the parent keeps its full cumulative total and each sub-batch adds its own `Total_Grafted`. The denominator therefore sums the recorded grafted quantity of every batch and sub-batch (each regraft appears as a new Grafted entry). The **Regraft Deficit** KPI below remains OPEN.
- **Regraft Deficit** = `Σ max(0, mortality − regraft)` (the currently implemented formula). Under the mortality/regraft rules, this compares populations that no longer offset each other, so this KPI's definition is an **OPEN DECISION** — no replacement formula is invented here (OD-10).

---

## 11. Data Consistency Rules

The system must maintain one consistent definition for each operational quantity.

### Ordered Quantity

The quantity initially ordered from the supplier. **[PROPOSED — pending client confirmation]**: the ordered quantity is context only and is not stored; only the received quantity is recorded (during Stock In). An alternative — a nullable `Ordered_Quantity` column on Stock In — is open for client decision (OD-1).

### Batch

The established/grafted cacao group created from the received Quantity recorded during Stock In. A regraft sub-batch is a linked Batch created from a regraft quantity and begins its own independent 21-day cycle. The proposed sub-batch rules are listed in §2.4 (OD-6).

### Mortality

A strictly incrementing recorded quantity of plants that died according to the mortality rules. Mortality is recorded against the specific Batch or sub-batch where the death occurred and does not decrease the Regraft counter.

### Regraft

The recorded quantity of plants moved from a parent Batch for regrafting. A regraft deducts the quantity from the parent Batch's **Available** count and adds it to its **Regraft** counter; it is **not** deducted from `Total Grafted`, which is a cumulative, non-decreasing log (CONFIRMED, 2026-09-29). The regrafted plants establish a linked sub-batch that may have a different Scion Variety and whose own `Total Grafted` equals the moved quantity. A Regraft replaces a dead scion on a living root stock; it does not offset Mortality, and dead root stock is never regrafted.

### Total Grafted

**CONFIRMED (2026-09-29)** — from the client's spreadsheet (the Regraft row records a new Grafted entry plus a Re Graft entry on the same row) and the client's direct statement *"Grafted minus Regraft = mortality or yield for the parent batch"*:

- **`Total Grafted` is a cumulative, non-decreasing log per batch.** It only ever grows; a regraft does **not** reduce it, and Mortality does **not** reduce it.
- The batch's **still-attributable/live population** for mortality and regraft purposes is **`Total Grafted − Regraft`**.
- A sub-batch created by a regraft records the moved quantity as its own `Total Grafted`.
- Consequently the mortality quantity bound (OD-4), the regraftable quantity bound (OD-6) and the crude-mortality-rate denominator (OD-10) are all defined on this cumulative basis.

### Available

The existing system term used for the applicable quantity available for the relevant operation. The system must not redefine the company's terminology without an approved business-process change.

**Proposed definition [PROPOSED — pending client confirmation]** (OD-3): **Available** (the `Available_Count` on a Batch or sub-batch) is the number of **healthy plants on hand for pull-out**. Effects:

- A new batch starts with Available equal to its Total Grafted.
- Mortality reduces Available.
- Stock Out reduces Available.
- A regraft of quantity q reduces the source batch's Available by q and starts the sub-batch with Available = q.

Invariant: `0 ≤ Available_Count ≤ Total_Grafted`.

**Consequence for the Gap Report (OPEN DECISION):** in the current implementation, Mortality does **not** reduce `Available_Count`, and the Gap Report's present meaning (recorded mortality) depends on that behaviour. The Gap Report definition must therefore be re-confirmed together with this decision (OD-3).

### Stock Out / Pull Out

The quantity of cacao pulled out from the applicable batch and delivered/sold to a client.

The system must use the same definitions across Dashboard, Inventory, Stock Ledger, Analytics, and Reports.

---

## 12. Activity Traceability

Important operational actions must be traceable to the user and date/time.

At minimum, traceability should cover:

- Stock In
- Scion receipts (a Stock In row that includes scion)
- Stock Out
- Mortality Tracking
- Regraft
- Regraft sub-batch creation
- Daily Checklist completion
- Important administrative actions

Activity History must use actual system records.

---

## 13. Security Requirements

The system shall:

- Require authentication for protected pages.
- Enforce authorization server-side.
- Protect sensitive user information.
- Hash passwords securely.
- Validate and sanitize user input through Laravel validation and appropriate database handling.
- Protect state-changing operations against unauthorized requests.
- Prevent users from accessing records or operations outside their permissions.

---

## 14. UI and Usability Requirements

The existing frontend should be refined rather than unnecessarily rebuilt.

The interface shall be:

- Clean
- Professional
- Readable
- Consistent
- Responsive
- Appropriate for cacao nursery operations
- Appropriate for thesis presentation

Avoid unnecessary:

- Glassmorphism
- Neon gradients
- Excessive glowing effects
- Decorative 3D graphics
- AI/robot imagery
- Gaming/cryptocurrency styling
- Excessive animation
- Decorative components without an operational purpose

Tables and forms should prioritize clarity over decoration.

---

## 15. Empty, Error, and Feedback States

The system shall provide clear feedback for:

#### Success

Confirm completed transactions such as successful Stock In, Stock Out, Mortality, or Regraft records.

#### Validation

Show the user which fields require correction.

#### Error

Explain what failed without exposing sensitive technical information.

#### Empty State

Use clear messages such as:

> No records available for the selected period.

Do not display fabricated values when there is no data.

---

## 16. No Fabricated Data

The system must never fabricate:

- Inventory quantities
- Batch quantities
- Mortality quantities
- Regraft quantities
- Stock In quantities
- Stock Out quantities
- Analytics values
- Historical values
- Chart data
- Report values
- Expected values used for Gap Reports

Development/test data may be used for testing, but production screens and reports must query actual database records.

---

## 17. Development Priorities

Development should follow the actual business process.

#### Priority 1 — Core Records

- Users and permissions
- Supplier
- Client
- Scion Variety
- Batches

#### Priority 2 — Core Operations

- Stock In
- Inventory
- Mortality Tracking
- Regraft
- Stock Out

#### Priority 3 — Daily Operations

- Daily Checklist
- Activity History

#### Priority 4 — Analytics and Reports

- Operational Analytics
- Tabular Deliverables
- Stock Ledger
- Root Cause Matrix
- Seasonality Heatmap
- Age Profile
- Gap Report

#### Priority 5 — UI and Quality

- Validation
- Responsive behavior
- Empty/error states
- Security
- Data consistency
- Thesis/presentation readiness

---

## 18. Final System Principle

CacaoTrack must follow the company's real process and terminology rather than introducing unnecessary modules or changing established business terms.

The essential process is:

```text
ORDER SEEDLINGS
      ↓
STOCK IN
      ↓
ESTABLISH BATCH
      ↓
GRAFT / REGRAFT
      ↓
PARENT TOTAL GRAFTED − REGRAFT QUANTITY
      ↓
CREATE LINKED REGRAFT SUB-BATCH
      ↓
INDEPENDENT 21-DAY CYCLE
      ↓
MORTALITY / REGRAFT
      ↓
INVENTORY
      ↓
COUNT HEALTHY CACAO FOR PULL-OUT
      ↓
STOCK OUT
      ↓
CLIENT
```

The system should automate recording, validation, calculations, traceability, analytics, and reporting around this process without changing the company's established terms.

---

## 19. Open Decisions

The following are **new** rules. Each is marked in the text with the `[PROPOSED — pending client confirmation]` marker (rendered in bold) and an `(OD-n)` reference, unless it has been **CONFIRMED** by the client. Nothing here changes a client-approved rule; these are decisions to be confirmed. The corresponding ERD (`CacaoTrack_ERD.md`) cites the same `(OD-n)` identifiers.

**Confirmed 2026-09-29 (client spreadsheet evidence — a regraft is a new Grafted entry plus a Re Graft entry on the same row — and the client's direct statement *"Grafted minus Regraft = mortality or yield for the parent batch"*): `Total_Grafted` is a cumulative, non-decreasing log per batch; a batch's still-attributable/live population for mortality and regraft purposes is `Total_Grafted − Regraft`.** On that basis the Total_Grafted clause of **OD-4** (mortality bound), **OD-6** (regraft bound) and **OD-10** (Crude Mortality Rate denominator) is now CONFIRMED; the remaining clauses of OD-6 and the Regraft Deficit part of OD-10 stay open.

- **OD-1 — Ordered quantity storage.** Ordered quantity is context only and is not stored; only the received quantity is recorded. *Alternative:* a nullable `Ordered_Quantity` column on Stock In. (§2.1, §11)
- **OD-2 — Mortality plant condition.** Add a `Plant_Condition` (Root Stock / Scion) column to `Mortality_Record`. Open question: what does a **scion**-condition death mean for the regraft flow? If scion deaths are handled only through Regraft, the PRD requirement to record scion condition should be removed instead. (§2.5, FR-04; ERD §3.10)
- **OD-3 — Available definition and Gap Report.** Confirm `Available_Count` = healthy plants on hand for pull-out, with the effects and invariant in §11. Consequence: Mortality does not currently reduce `Available_Count`, and the Gap Report's current meaning (recorded mortality) depends on that, so the Gap Report definition must be re-confirmed together with this decision. (§11, §9.5, FR-08)
- **OD-4 — Mortality quantity bound — CONFIRMED (2026-09-29).** A death can only be recorded against the batch's still-attributable population: `mortality quantity ≤ (Total_Grafted − Regraft) − already-recorded effective mortality`. This follows from the client's confirmed rule *"Grafted minus Regraft = mortality or yield for the parent batch"* (client spreadsheet evidence: a regraft is a new Grafted entry plus a Re Graft entry on the same row, so `Total_Grafted` is cumulative and never decreases). (FR-04, §11)
- **OD-5 — Corrections policy.** With Stock Reversal and Mortality Adjustments removed: transactional records are not editable by regular users; an Administrator may correct or remove a mistaken record; every correction is recorded in Activity History with before/after values; and a regraft that created a sub-batch with dependent Mortality, Stock Out, or Inventory records cannot be removed. (§7)
- **OD-6 — Regraft sub-batch details.** **CONFIRMED (2026-09-29):** initial `Total_Grafted` = `Available` = regraft quantity; the regraftable quantity is bounded by the source's still-attributable surviving population `min(Available, (Total_Grafted − Regraft) − effective mortality)` (a dead root stock cannot be regrafted); the source's `Total_Grafted` is unchanged by the regraft. **Still [PROPOSED — pending client confirmation]:** `Grafted_Date` = regraft date; the source batch may itself be a sub-batch; `Batch_Code` = source `Batch_Code` + `-R` + next sequence for that source (`B001-R1`, `B001-R1-R1`); `Batch_Code` unique; Scion Variety required on the sub-batch. (§2.4, FR-06; ERD §3.8)
- **OD-7 — 21-day cycle and Batch.Status.** No stored cycle column; cycle end = `Grafted_Date + 21 days` (derived); "during cycle" vs "after cycle" mortality derived from `Recorded_At`; Age Profile uses each batch's own `Grafted_Date`. `Batch.Status` enumerated values in the current implementation: `active`, `inactive`. (§2.6; ERD §3.8/§5)
- **OD-8 — Stock In ↔ Batch traceability.** The system keeps no seedling stock balance and does not enforce Stock In → Batch traceability (intentional, consistent with "no stock balance"). (§11, FR-09; ERD §3.7)
- **OD-9 — Inventory snapshot semantics.** Inventory values are captured from actual records at snapshot time (point-in-time, frozen); one snapshot per batch or sub-batch per month; "Applicable current quantity" maps to `Batch.Available_Count`. (FR-08; ERD §3.12)
- **OD-10 — Dashboard KPIs.** **Total Active Inventory and the Crude Mortality Rate denominator — CONFIRMED (2026-09-29):** the crude-mortality-rate denominator is `SUM(Total_Grafted)`, where `Total_Grafted` is a cumulative, non-decreasing log (so it is not conserved across a regraft — see §11 Total Grafted). **Regraft Deficit remains an OPEN DECISION:** the current formula `Σ max(0, mortality − regraft)` compares populations that no longer offset each other; no replacement formula is proposed here. (§10)
- **OD-11 — Replanting_Record.Sub_Batch_ID.** Replace the duplicate `Parent_Batch_ID` on `Replanting_Record` with a nullable `Sub_Batch_ID` (FK → Batch) identifying the sub-batch the record created; `Replanting_Record.Batch_ID` is the source batch. (ERD §3.11)
- **OD-12 — Batch.Regraft cached total.** `Batch.Regraft` is a cached total that must equal `SUM(Replanting_Record.Quantity)` for that source batch, maintained inside the regraft transaction. (ERD §3.8/§3.11)
- **OD-13 — Scion freshness/viability rule.** No usable-until value, no warning, and no blocking in this version; pending client confirmation of (a) the allowed freshness window and (b) whether the rule should warn or block. (ERD §3.7)
- **OD-14 — Scion receipt traceability.** Linking a regraft to a specific scion receipt (a scion-inclusive Stock In row) is **not part of this version**; no stock-balance deduction. (FR-07; ERD §2/§3.7)

---

## End of PRD
