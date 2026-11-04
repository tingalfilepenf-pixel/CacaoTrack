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
- **2.0 — 2026-11-04 (documentation, Group 3):** Documented the proposed (pending written confirmation by the panelist and the client) but **not yet implemented** operational submission/verification workflow (F01–F03) and correction-audit policy (F04). Added §2.9; updated §2.3, §7, §8, FR-06, FR-13, §12 and `OD-5`. No application behavior or database schema is changed.
- **2.0 — 2026-11-04 (documentation, Phase 2.2 permissions):** Recorded the owner-approved U16/U5, U6, and U7 decisions: custom roles remain allowed; hard permission restrictions override selected permissions; submit and verify are mutually exclusive; `manage_users` and `correct_records` are Administrator-only; Administrators cannot submit or verify ordinary operational submissions. Added FR-02.2 and a §2.9 workflow note. The final permission catalog, legacy-key retirement, and migration remain unresolved; no application behavior or database schema is changed.
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

**Batch Creation approval — proposed design (pending written confirmation by the panelist and the client), Proposed / Not Yet Implemented:** Batch Creation is intended to be a Staff submission verified by a Manager. Only an approved request creates an official Batch record; pending or rejected requests must not create official Batches. The Batch entity and its relationships are unchanged by this workflow — approval gates creation, it does not redefine the Batch. See §2.9 and FR-06.

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

For a Stock Out submission, approval must revalidate that the selected Client is active. A Client archived after submission but before approval is ineligible; approval must not silently accept an archived Client. This eligibility rule does not change the available-quantity rule above.

### 2.9 Operational Submission and Verification — proposed design (pending written confirmation by the panelist and the client) — Proposed / Not Yet Implemented

**Status:** this workflow is a **proposed design (pending written confirmation by the panelist and the client)** but is **not implemented** in the current application. Current operational forms write official records directly, with no Pending/Approved review step. This section documents the target behavior only; it must not be described as an existing feature.

**What is submitted:** Staff submit operational transactions as the primary flow, including Batch Creation (§2.3/FR-06). A Manager may also submit other transactions where the applicable role and permission requirements permit. The final set of submission types and permission keys remains subject to the open owner decisions; this section does not settle them.

Because submit_operations and verify_operations are mutually exclusive (FR-02.2), a user who can verify cannot submit; a Manager may submit only if they hold the submit key instead of the verify key. This interaction is unresolved.

**Workflow rules:**

- A submission remains **Pending** until a Manager verifies it.
- **Pending** and **Rejected** submissions must not affect official operational records, inventory balances, quantities, or analytics.
- Approval **revalidates the submitted data against current official records** (including the applicable caps and balances) before applying it.
- Approval must apply its official effect **exactly once**.
- **Rejection requires a written reason.**
- The verifier must differ from the submitter. A Manager may verify a submission only when they are not its submitter. The verifier must satisfy the applicable verification permission and role requirements.
- Administrators cannot submit or verify ordinary operational submissions. They retain the applicable read-only access, subject to the separately unresolved visibility decision **U8**. See FR-02.2 for the owner-approved hard permission restrictions.
- **Rejected submissions remain in the audit/history** and are not removed.
- A **resubmission creates a new submission linked to the rejected submission**; it does not edit or reopen the rejected record.
- Staff must not edit or delete a decided (approved or rejected) submission.

**Corrections during the pilot:** all corrections are excluded from the new operational-submission workflow; Mortality corrections and Stock Out corrections are deferred from that workflow. Existing correction routes remain temporarily available under their current implementation and permissions. This temporary retention does not certify the existing correction behavior as safe or fully compliant; COR-01 remains an open technical risk. The target correction policy and the proposed `record_corrections` table remain proposed / not implemented.

**Stock Out approval — Client eligibility:** for a Stock Out submission, approval must revalidate that the selected Client is active. A Client archived after submission but before approval is ineligible; approval must not silently accept an archived Client. This establishes an eligibility requirement only; general approval-failure handling remains unresolved.

#### Submission lifecycle

```text
Pending ──→ Approved
   │
   └──→ Rejected ──→ New Resubmission (linked to the rejected submission)
```

Only these three statuses are defined: `Pending`, `Approved`, `Rejected`. No other status is introduced by this document.

- An **Approved** Batch is eligible as the subject of dependent operational operations.
- A **Pending** or **Rejected** Batch Creation request is not an official Batch and is not eligible for dependent operations.
- Rejected submission history is preserved, including the written rejection reason.

**Implementation note:** the current application has no submission staging store and no Manager verification step for these transactions. The proposed storage entity is described as `Operational_Submission` in ERD §9.1; its final columns, keys, relationships, and permission mapping require owner approval.

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

**Terminology note:** user-facing screens use **Regraft**; the internal entity/table name is `Regraft_Record` / `regraft_records` (renamed from `Replanting_Record` / `replanting_records` on 2026-09-30 so the internal name matches the company term).

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

#### FR-02.1 Role and permission model

Roles and permissions are stored per user: `Users.Role` is a display label and
`Users.Permissions` is a JSON list of catalog keys. There is no separate roles
table. The catalog below is the single source of truth, and the built-in role
templates are generated from it — the form never duplicates the defaults.
Administrator is a protected role, but access is still checked per permission
(no implicit bypass). The existing role value `Administrator` is the reserved
Administrator identity; the owner-approved hard restrictions are in FR-02.2 and
are **Proposed / Not Yet Implemented**.

| Permission key | Plain-language label | What it allows | Staff | Manager | Administrator |
|---|---|---|---|---|---|
| `manage_users` | Manage Users and Roles | Add, edit and remove user accounts and their access. | — | — | ✓ |
| `manage_inventory` | Maintain Scion Variety, Client and Supplier lists | Keep the Scion Variety, Client and Supplier lists up to date. | — | ✓ | ✓ |
| `manage_stock` | Record Stock In and Stock Out | Record seedlings and scions received and released. | ✓ | — | ✓ |
| `record_operations` | Record Mortality, Regraft, Batches and Monthly Inventory | Record mortality, regraft sub-batches, batches and monthly inventory snapshots. | ✓ | — | ✓ |
| `manage_checklist` | Manage and verify Daily Checklist tasks | Create, edit, delete and verify Daily Checklist tasks. | — | ✓ | ✓ |
| `complete_checklist` | Mark assigned Daily Checklist tasks as completed | Mark the Daily Checklist tasks assigned to you as completed. | ✓ | — | ✓ |
| `correct_records` | Correct Mistakes (Edit Recorded Entries) | Fix a recorded entry and keep the before/after audit trail. | — | — | ✓ |
| `view_analytics` | View Analytics | Open the analytics pages, charts and exports. | — | ✓ | ✓ |
| `generate_reports` | Generate Reports | Save a report snapshot with the Generate Report action. | — | ✓ | ✓ |
| `view_activity` | View Activity History | Read the Activity History audit trail. | — | ✓ | ✓ |

**Provisional defaults:** the Staff `record_operations` grant for **Batches**
is `[PROPOSED — pending panel confirmation]`; the Staff `record_operations`
grant for **Monthly Inventory recording** is `[PROPOSED — pending panel
confirmation]`; the Manager `view_analytics` grant for **Analytics** is
`[PROPOSED — pending panel confirmation]`; the Manager `view_activity` grant for
**Activity History** is `[PROPOSED — pending panel confirmation]`. Applying a
role template changes the form only; an Administrator must still save, and
existing stored permissions are never overwritten automatically.

**Daily Checklist workflow (confirmed):** completing a task and managing or
verifying the checklist are separate authorities. `complete_checklist` lets a
Staff member mark an assigned task as completed; the stored completion records
the Staff member and leaves `verified_by_id` / `verified_at` empty.
`manage_checklist` lets a Manager create, edit and delete task definitions and
record verification later; Staff cannot manage tasks or change verification
records. The Manager/Staff split is enforced on every route and HTTP method,
not only in the interface.

**Monthly Inventory (confirmed):** viewing `/inventory` requires any of
`record_operations`, `manage_inventory` or `manage_stock`. Recording a monthly
snapshot is stricter and requires `record_operations`; view permission never
implies record permission.

#### FR-02.2 Hard permission restrictions (owner-approved; Proposed / Not Yet Implemented)

**Status:** the hard restrictions below are an approved owner policy, but they are **not implemented** in the current application. The current catalog is the 10 keys listed above; `submit_operations` and `verify_operations` are proposed workflow keys. The full 11-key catalog, legacy-key retirement (`record_operations`, `manage_stock`), and permission migration remain unresolved and are not approved here. Do not describe this policy as an existing feature.

The hard restrictions apply to stored permissions regardless of the editable role name (custom roles remain allowed and role names remain free text):

1. `submit_operations` and `verify_operations` are mutually exclusive for every user, including custom roles. **(U6)**
2. `manage_users` and `correct_records` are Administrator-only.
3. Administrators cannot hold `submit_operations` or `verify_operations`, and cannot submit or verify ordinary operational submissions. They retain the applicable read-only access, subject to the separately unresolved visibility decision **U8**. **(U7)**
4. Hard restrictions override any user-selected permissions.
5. Permission combinations must be validated whenever permissions are saved.
6. Hard restrictions must be rechecked on every protected request.
7. Stored permissions must never be rewritten automatically.
8. Protected-route access must be denied when an account's stored permissions violate a hard restriction.
9. Invalid accounts must be shown to an authorized Administrator as needing correction.

**Administrator identity:** the existing role value `Administrator` is the reserved Administrator identity. No schema change or additional identity column is approved, and no separate internal classification, protected flag, or role-type column is an approved requirement. Role-name matching must be case-insensitive for reserved-name protection. Custom roles cannot use `Administrator` in any capitalization. Only an existing Administrator may assign or edit the Administrator role, and the system must always retain at least one Administrator. **(U16/U5)**

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

Regrafting must be connected to the applicable Batch or sub-batch, must create the linked regraft sub-batch (see §2.4), and must be reflected in operational analytics where required. The internal entity/table name is `Regraft_Record` / `regraft_records` (renamed from `Replanting_Record` on 2026-09-30).

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

**Batch Creation approval — proposed design (pending written confirmation by the panelist and the client), Proposed / Not Yet Implemented:** Batch Creation is a Staff submission verified by a Manager. Only an approved request creates the official Batch; pending or rejected requests create no official Batch. The Batch entity definitions and relationships are otherwise unchanged. See §2.3 and §2.9.

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
- For a Stock Out submission, revalidate at approval that the selected Client is active; a Client archived after submission but before approval is ineligible and must not be silently accepted. This does not change the available-quantity validation above.

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

Activity History is the readable action/audit trail, but it is **not by itself the complete correction audit**: its description field cannot be relied on to preserve the full before-and-after values of a correction. The intended correction audit is the proposed `record_corrections` table described in §7 (Proposed / Not Yet Implemented).

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

Stock Reversal and Mortality Adjustments are not part of the system. The intended correction policy is **proposed design (pending written confirmation by the panelist and the client) — Proposed / Not Yet Implemented**:

- Corrections are **Administrator-only**.
- Every correction requires a **written reason**.
- Every correction preserves the **before-and-after values**.
- Correction history is **append-only**.
- A regraft that created a sub-batch with dependent Mortality, Stock Out, or Inventory records cannot be removed.

**Correction audit:** Activity History remains the readable action trail, but it is **not by itself the complete correction audit** because its description field cannot be relied on to preserve the full before-and-after details. The proposed design (pending written confirmation by the panelist and the client) is a proposed `record_corrections` table. It is **not implemented**. Its final columns and relationships **require owner approval** and must not be treated as settled by this PRD.

**Current implementation:** correction forms do not require a written reason, and no `record_corrections` table exists; the existing correction trace is Activity History. This is implementation status, not the target policy. (OD-5)

**Existing correction routes vs target policy — pilot:** existing correction functionality remains temporarily available under its current implementation and permissions. This is separate from the target policy above and does not certify the existing correction behavior as safe or fully compliant. All corrections are excluded from the new operational-submission workflow; Mortality corrections and Stock Out corrections are deferred from that workflow. COR-01 remains an open technical risk. The target policy and the proposed `record_corrections` table remain **Proposed / Not Yet Implemented**.

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
4. Decrease the parent Batch's Available_Count by the regraft quantity. Do not change Total_Grafted — it is a cumulative, non-decreasing log.
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

#### Operational submissions and approval (proposed — not yet implemented)

When the proposed (pending written confirmation by the panelist and the client) submission workflow is implemented, a submission itself is not an official transaction. The official effect runs only at approval time, inside one transaction: revalidate the submitted values against the current official records under the required locks, apply the official effect exactly once, write the required history, and commit. A pending or rejected submission must never post an official effect. (See §2.9.)

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
- Regraft (internal `Regraft_Record`)
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
- **Crude Mortality Rate** = `SUM(Mortality quantity) ÷ SUM(Total_Grafted − Regraft) × 100`, across all batches and sub-batches. **CONFIRMED (2026-09-29):** since `Total_Grafted` is a cumulative, non-decreasing log, raw `SUM(Total_Grafted)` would double-count every regrafted plant (once in the source batch's total, again in the sub-batch's total, and again if that sub-batch is itself regrafted). `SUM(Total_Grafted − Regraft)` counts each physical plant exactly once, at wherever it currently is — the client's own rule (*"Grafted minus Regraft = mortality or yield for the parent batch"*; see §11 Total Grafted). The **Regraft Deficit** KPI below remains OPEN.
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
- Consequently the mortality quantity bound (OD-4, owner decision 19) and the regraftable quantity bound (OD-6) are the surviving quantity, `Batch::survivingQuantity()` = `max(0, Available_Count − recorded effective mortality)`, where the stored `Available_Count` already nets out Stock Out and Regraft — algebraically `Total_Grafted − Regraft − Stock Out − recorded effective mortality` — and the crude-mortality-rate denominator (OD-10) is `SUM(Total_Grafted − Regraft)` — each physical plant counted exactly once.

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

Activity History is the readable audit trail but is not by itself the complete correction audit; see §7 and §19 OD-5 for the proposed `record_corrections` design. During the pilot, existing correction routes remain temporarily available under their current implementation and permissions, and corrections are excluded from the new operational-submission workflow; this does not certify the existing correction behavior as safe or fully compliant.

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
- **OD-4 — Mortality quantity bound — CONFIRMED (2026-09-29; owner decision 19, 2026-11-04).** A death can only be recorded against the batch's live surviving balance: `mortality quantity ≤ Batch::survivingQuantity()` = `max(0, Available_Count − already-recorded effective mortality)`. Because `Available_Count` already nets out Regraft and Stock Out, this is algebraically `Total_Grafted − Regraft − Stock Out − already-recorded effective mortality`; Stock Out therefore reduces the mortality cap. This follows from the client's confirmed rule *"Grafted minus Regraft = mortality or yield for the parent batch"* (client spreadsheet evidence: a regraft is a new Grafted entry plus a Re Graft entry on the same row, so `Total_Grafted` is cumulative and never decreases). (FR-04, §11)
- **OD-5 — Corrections policy — proposed design (pending written confirmation by the panelist and the client); NOT YET IMPLEMENTED.** Corrections are Administrator-only; every correction requires a written reason and preserves before/after values; correction history is append-only; and a regraft that created a sub-batch with dependent Mortality, Stock Out, or Inventory records cannot be removed. The proposed `record_corrections` table is **not implemented**; its final schema and relationships require owner approval. Activity History alone is not the complete correction audit because its description field cannot be relied on for full before/after details. **During the pilot:** existing correction routes remain temporarily available under their current implementation and permissions; corrections are excluded from the new operational-submission workflow; COR-01 remains an open technical risk. The target policy and `record_corrections` remain proposed / not implemented. (§7, §12)
- **OD-6 — Regraft sub-batch details.** **CONFIRMED (2026-09-29):** initial `Total_Grafted` = `Available` = regraft quantity; the regraftable quantity is bounded by the source's surviving quantity, `Batch::survivingQuantity()` = `max(0, Available_Count − recorded effective mortality)` (Stock Out deduction: owner decision 19, 2026-11-04), where the stored `Available_Count` already nets out Stock Out and Regraft (algebraically `Total_Grafted − Regraft − Stock Out − recorded effective mortality`) (a dead root stock cannot be regrafted); the source's `Total_Grafted` is unchanged by the regraft. **Still [PROPOSED — pending client confirmation]:** `Grafted_Date` = regraft date; the source batch may itself be a sub-batch; `Batch_Code` = source `Batch_Code` + `-R` + next sequence for that source (`B001-R1`, `B001-R1-R1`); `Batch_Code` unique; Scion Variety required on the sub-batch. (§2.4, FR-06; ERD §3.8)
- **OD-7 — 21-day cycle and Batch.Status.** No stored cycle column; cycle end = `Grafted_Date + 21 days` (derived); "during cycle" vs "after cycle" mortality derived from `Recorded_At`; Age Profile uses each batch's own `Grafted_Date`. `Batch.Status` enumerated values in the current implementation: `active`, `inactive`. (§2.6; ERD §3.8/§5)
- **OD-8 — Stock In ↔ Batch traceability.** The system keeps no seedling stock balance and does not enforce Stock In → Batch traceability (intentional, consistent with "no stock balance"). (§11, FR-09; ERD §3.7)
- **OD-9 — Inventory snapshot semantics.** Inventory values are captured from actual records at snapshot time (point-in-time, frozen); one snapshot per batch or sub-batch per month; "Applicable current quantity" maps to `Batch.Available_Count`. (FR-08; ERD §3.12)
- **OD-10 — Dashboard KPIs.** **Total Active Inventory and the Crude Mortality Rate denominator — CONFIRMED (2026-09-29):** the crude-mortality-rate denominator is `SUM(Total_Grafted − Regraft)` — each physical plant counted exactly once; raw `SUM(Total_Grafted)` would double-count regrafted plants (see §11 Total Grafted). **Regraft Deficit remains an OPEN DECISION:** the current formula `Σ max(0, mortality − regraft)` compares populations that no longer offset each other; no replacement formula is proposed here. (§10)
- **OD-11 — Regraft_Record.Sub_Batch_ID — WITHDRAWN (owner decision 21, 2026-11-04).** The live `regraft_records` table uses `batch_id` = the created sub-batch and nullable `parent_batch_id` = the source batch. No `Sub_Batch_ID` column is added; ERD §3.11 is corrected to match. (ERD §3.11)
- **OD-12 — Batch.Regraft cached total.** `Batch.Regraft` is a cached total that must equal `SUM(Regraft_Record.Quantity)` for that source batch, maintained inside the regraft transaction. (ERD §3.8/§3.11)
- **OD-13 — Scion freshness/viability rule.** No usable-until value, no warning, and no blocking in this version; pending client confirmation of (a) the allowed freshness window and (b) whether the rule should warn or block. (ERD §3.7)
- **OD-14 — Scion receipt traceability.** Linking a regraft to a specific scion receipt (a scion-inclusive Stock In row) is **not part of this version**; no stock-balance deduction. (FR-07; ERD §2/§3.7)

---

## End of PRD
