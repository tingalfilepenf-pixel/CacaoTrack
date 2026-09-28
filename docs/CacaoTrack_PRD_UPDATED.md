# CacaoTrack Product Requirements Document (PRD)

## Cacao Mortality Tracking and Inventory System with Data Analytics

**System Name:** CacaoTrack  
**Document Type:** Product Requirements Document  
**Version:** Revised Working PRD  
**Frontend:** Laravel Blade, Bootstrap 5, JavaScript, ECharts  
**Backend:** Laravel, PHP  
**Database:** MySQL

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

The ordered quantity is not automatically required to equal the received quantity. The received quantity is recorded directly during Stock In and may differ from the original ordered quantity.

Example:

- Ordered: 500 seedlings
- Received: 460 seedlings

The received quantity recorded during Stock In is the quantity entering the next production step.

### 2.2 Stock In

Stock In records the seedlings received from the supplier immediately after the order is fulfilled.

Stock In fields:

- Seedlings
- Quantity
- Date
- Origin

The system must allow the received Quantity to differ from the original ordered quantity.

### 2.3 Establishing Batches

Received seedlings are used to establish batches. A batch represents seedlings that have been established/grafted as cacao.

Batch records must remain the central operational record for the grafted cacao.

### 2.4 Grafting and Regrafting

Grafting is part of establishing and maintaining a batch.

When regrafting is required, the system uses the available **Scion Variety** according to the required variety. The quantity being regrafted is deducted from the parent batch's **Total Grafted** count, while the parent batch's **Regraft** counter is increased by the same quantity. The regrafted plants establish a new linked **sub-batch** under the parent batch (for example, appending `-R1` to the parent Batch ID). The sub-batch starts its own independent 21-day cycle and may be assigned a different Scion Variety than the parent.

Scion Variety is maintained as operational master data and is used when recording grafting/regrafting activities.

### 2.5 Mortality

Mortality is recorded as a strictly incrementing counter whenever a plant dies during the production cycle or for applicable deaths after the 21-day cycle. Mortality is recorded against the specific Batch or regraft sub-batch where the death actually occurred.

The system must distinguish the relevant plant condition when recording mortality, including root stock and scion where applicable.

A dead root stock cannot be regrafted. Once the root stock is dead, it is recorded as Mortality rather than being processed as a regraft. Recording Mortality does not decrease or otherwise affect the Batch's **Regraft** counter.

### 2.6 21-Day Cycle

Batches follow the company's 21-day production cycle.

The system shall retain the 21-day cycle information needed for operational records and analytics, but **Batch Monitoring is not a separate sidebar/module**.

The 21-day cycle is handled through the Batch, Mortality Tracking, Replanting, and related operational records. Each regraft sub-batch has its own independent 21-day cycle.

### 2.7 Inventory

Inventory records the monthly status of established grafted cacao batches and their linked regraft sub-batches.

The inventory record follows the company's existing operational record and includes information such as:

- Batch
- Date Grafted
- Total Grafted
- Scion Variety
- Regraft
- Mortality

Inventory values must come from actual operational records.

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
3. Maintain accurate batch, grafting, regrafting, sub-batch, mortality, replanting, inventory, and Stock Out records.
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

### Dashboard

- Dashboard

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

### Removed from the previous PRD/navigation

The following are not separate system modules/sidebar items:

- Batch Monitoring
- Variety
- Stock Reversal
- Scion
- Pot Type

**Scion Variety** is the retained term/module for scion-variety information.

---

# 6. Functional Requirements

## FR-01 Authentication

The system shall allow authorized users to:

- Log in
- Log out
- Maintain authenticated sessions
- Access authorized pages according to their role and permissions

Protected routes must require authentication.

---

## FR-02 User Management

Authorized users shall manage system accounts according to role permissions.

The system shall:

- Manage users
- Assign roles/permissions according to authorization rules
- Protect the Administrator system role
- Prevent unauthorized access to restricted operations
- Store passwords securely and never as plaintext

Authorization must be enforced server-side.

---

## FR-03 Daily Checklist

The system shall support:

- Daily task listing
- Task completion
- Completion date
- Remarks
- Verification where applicable
- User accountability

Daily checklist records must be traceable through Activity History where applicable.

---

## FR-04 Mortality Tracking

The system shall allow authorized users to record mortality information including:

- Batch or sub-batch
- User
- Quantity
- Cause
- Date/time
- Applicable plant condition/type, including root stock or scion

The system must validate mortality quantities against the applicable batch records.

Mortality must be available to analytics and reports.

### Mortality business rules

- Mortality may occur during the 21-day cycle.
- Applicable mortality may also be recorded after the 21-day cycle.
- Mortality is a strictly incrementing counter used whenever a death happens.
- Mortality must be recorded against the specific Batch or regraft sub-batch where the death occurred.
- A dead root stock cannot be regrafted.
- Recording Mortality does not decrease or otherwise modify the Regraft counter.
- Mortality must not create an invalid or negative batch quantity.

---

## FR-05 Replanting

The system shall allow authorized users to record replanting/regrafting information including:

- Parent Batch or sub-batch
- User
- Quantity
- Replanting date
- Remarks

Replanting/regrafting must be connected to the applicable Batch or sub-batch and reflected in operational analytics where required.

---

## FR-06 Batches

The Batch module shall manage the established/grafted cacao batches.

A batch may include information such as:

- Batch ID/code
- Parent Batch ID where applicable
- Date established/grafted
- Total Grafted
- Available
- Scion Variety
- Regraft information
- Mortality information
- Status
- Relevant dates

The existing batch record must be reused as the central source for batch-related operations. A regraft must create a linked sub-batch rather than an unrelated duplicate record. Each regraft sub-batch has its own 21-day cycle and may use a different Scion Variety from its parent.

### Batch process

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
Mortality / Replanting as applicable
      ↓
Pull-Out / Stock Out
```

---

## FR-07 Scion Variety

The Scion Variety module shall maintain the scion varieties used by the company.

It shall support:

- Scion Variety records
- Availability/status where applicable
- Selection of Scion Variety during applicable grafting/regrafting operations
- Use of multiple Scion Varieties in Stock Out transactions where applicable

The system shall use the company's term **Scion Variety** consistently.

---

## FR-08 Inventory

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

Inventory values must be derived from actual records and must remain consistent with Batch, Mortality Tracking, Replanting, and Stock Out transactions.

### Inventory rule

The initial batch quantity is established from the received Quantity recorded during Stock In. The ordered quantity alone must not be treated as the received quantity.

---

## FR-09 Stock In

Stock In shall record the seedlings received immediately. The recorded Quantity is the quantity used to establish the Batch's initial Total Grafted.

Fields:

- Seedlings
- Quantity
- Date
- Origin

The system must allow the recorded received Quantity to differ from the ordered quantity.

A Stock In transaction must update the applicable inventory records according to the approved business rules.

---

## FR-10 Stock Out

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

- Identify the applicable batch/available quantity.
- Prevent release beyond the available quantity.
- Record the client/Delivered To information.
- Update the remaining quantity correctly.
- Record the transaction in Activity History where applicable.

---

## FR-11 Supplier Management

The system shall maintain supplier information such as:

- Supplier name
- Contact information
- Address
- Other necessary supplier details

Supplier records shall be usable in the ordering/Stock In process where applicable.

---

## FR-12 Client Management

The system shall maintain client information such as:

- Client name
- Contact information
- Address
- Other necessary client details

Client records shall be selectable during Stock Out.

---

## FR-13 Activity History

The system shall provide a read-only history of important system activities, including atomic regrafting and sub-batch creation.

Where applicable, activity records shall include:

- User
- Activity type
- Batch
- Description
- Date/time

Important traceable activities include:

- Stock In
- Stock Out
- Mortality Recording
- Replanting/Regrafting and sub-batch creation
- Daily Checklist completion
- Analytics/report generation where applicable

---

# 7. Validation Requirements

All important input and transaction operations must be validated.

### Client-side validation

Use JavaScript for immediate feedback where appropriate:

- Required fields
- Numeric values
- Quantity format
- Date format
- Invalid selections

### Server-side validation

Laravel must perform authoritative validation.

The system must reject:

- Negative quantities
- Invalid quantities
- Invalid foreign keys
- Invalid dates
- Unauthorized operations
- Invalid mortality quantities
- Invalid replanting quantities
- Stock Out quantities greater than the applicable available quantity
- Duplicate transaction submissions where applicable
- Logically inconsistent inventory states

### Database integrity

Use appropriate:

- Foreign keys
- Unique constraints
- NOT NULL constraints
- Data types
- Indexes
- Referential integrity

---

# 8. Transaction Integrity

Inventory and quantity-changing operations must be atomic.

### Stock In

1. Validate the request.
2. Confirm authorization.
3. Record the Stock In transaction.
4. Update the applicable inventory/batch records.
5. Record Activity History where required.
6. Commit the transaction.

### Regrafting / Sub-Batch Creation

1. Validate the request and regraft quantity against the parent Batch.
2. Confirm authorization.
3. Lock the parent Batch and relevant quantity records as required.
4. Deduct the regraft quantity from the parent Batch's Total Grafted count.
5. Increase the parent Batch's Regraft counter by the same quantity.
6. Create the linked regraft sub-batch with its parent reference, independent 21-day cycle, and selected Scion Variety.
7. Record the regrafting/sub-batch creation in Activity History.
8. Commit the transaction.

If any required step fails, the entire regrafting/sub-batch creation transaction must roll back.

### Stock Out

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

# 9. Analytics and Reports

All analytics and reports must use actual database data.

## 9.1 Operational Analytics

Operational Analytics shall provide useful summaries of operational data, such as:

- Inventory information
- Mortality information
- Replanting information
- Batch information
- Stock In/Stock Out information where applicable

All displayed values must be calculated from current system data.

---

## 9.2 Tabular Deliverables

The system shall provide readable tabular outputs for important operational information.

Tables should support filtering, sorting, and pagination where appropriate.

---

## 9.3 Stock Ledger

The Stock Ledger shall provide a traceable view of quantity movements and applicable batch information. Inventory and Stock Ledger tables shall support nested or grouped views so parent batches and their associated regraft sub-batches are clearly distinguishable.

Where applicable it may include:

- Batch or sub-batch
- Parent Batch where applicable
- Scion Variety
- Total Grafted
- Available
- Regraft
- Mortality
- Replanting
- Stock In
- Stock Out
- Relevant dates

The ledger must be generated from actual operational data rather than maintaining duplicate reporting data.

---

## 9.4 Root Cause Matrix

The Root Cause Matrix shall analyze mortality by relevant dimensions such as:

- Mortality cause
- Batch
- Scion Variety
- Quantity
- Time period

The matrix should help identify recurring or significant mortality causes.

---

## 9.5 Historical Analytics

### Seasonality Heatmap

Show mortality patterns across time, such as month and mortality cause, using actual historical records.

### Age Profile

Show the age distribution of applicable batches/cacao using actual dates such as Date Grafted and the selected reporting date.

### Gap Report

Show meaningful differences between defensible expected and actual values.

The system must not invent expected values. If the existing data cannot support a defensible gap calculation, the report must identify the data limitation instead of generating fabricated values.

---

# 10. Dashboard Requirements

The Dashboard shall provide concise operational information using current database data.

Possible dashboard information includes:

- Current Inventory
- Batch information
- Mortality summary
- Replanting summary
- Stock In summary
- Stock Out summary
- Operational alerts or attention items where supported by actual data

Dashboard values must use the same definitions as the corresponding modules and reports.

---

# 11. Data Consistency Rules

The system must maintain one consistent definition for each operational quantity.

### Ordered Quantity

The quantity initially ordered from the supplier.

### Batch

The established/grafted cacao group created from the received Quantity recorded during Stock In. A regraft sub-batch is a linked Batch created from a regraft quantity and begins its own independent 21-day cycle.

### Mortality

A strictly incrementing recorded quantity of plants that died according to the mortality rules. Mortality is recorded against the specific Batch or sub-batch where the death occurred and does not decrease the Regraft counter.

### Regraft

The recorded quantity of plants moved from a parent Batch for regrafting. The same quantity is deducted from the parent Batch's Total Grafted count and added to its Regraft counter; the regrafted plants establish a linked sub-batch that may have a different Scion Variety.

### Available

The existing system term used for the applicable quantity available for the relevant operation. The system must not redefine the company's terminology without an approved business-process change.

### Stock Out / Pull Out

The quantity of cacao pulled out from the applicable batch and delivered/sold to a client.

The system must use the same definitions across Dashboard, Inventory, Stock Ledger, Analytics, and Reports.

---

# 12. Activity Traceability

Important operational actions must be traceable to the user and date/time.

At minimum, traceability should cover:

- Stock In
- Stock Out
- Mortality Tracking
- Replanting
- Daily Checklist completion
- Important administrative actions

Activity History must use actual system records.

---

# 13. Security Requirements

The system shall:

- Require authentication for protected pages.
- Enforce authorization server-side.
- Protect sensitive user information.
- Hash passwords securely.
- Validate and sanitize user input through Laravel validation and appropriate database handling.
- Protect state-changing operations against unauthorized requests.
- Prevent users from accessing records or operations outside their permissions.

---

# 14. UI and Usability Requirements

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

# 15. Empty, Error, and Feedback States

The system shall provide clear feedback for:

### Success

Confirm completed transactions such as successful Stock In, Stock Out, Mortality, or Replanting records.

### Validation

Show the user which fields require correction.

### Error

Explain what failed without exposing sensitive technical information.

### Empty State

Use clear messages such as:

> No records available for the selected period.

Do not display fabricated values when there is no data.

---

# 16. No Fabricated Data

The system must never fabricate:

- Inventory quantities
- Batch quantities
- Mortality quantities
- Replanting/regraft quantities
- Stock In quantities
- Stock Out quantities
- Analytics values
- Historical values
- Chart data
- Report values
- Expected values used for Gap Reports

Development/test data may be used for testing, but production screens and reports must query actual database records.

---

# 17. Development Priorities

Development should follow the actual business process.

### Priority 1 — Core Records

- Users and permissions
- Supplier
- Client
- Scion Variety
- Batches

### Priority 2 — Core Operations

- Stock In
- Inventory
- Mortality Tracking
- Replanting
- Stock Out

### Priority 3 — Daily Operations

- Daily Checklist
- Activity History

### Priority 4 — Analytics and Reports

- Operational Analytics
- Tabular Deliverables
- Stock Ledger
- Root Cause Matrix
- Seasonality Heatmap
- Age Profile
- Gap Report

### Priority 5 — UI and Quality

- Validation
- Responsive behavior
- Empty/error states
- Security
- Data consistency
- Thesis/presentation readiness

---

# 18. Final System Principle

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
MORTALITY / REPLANTING
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

## End of PRD
