# CacaoTrack Frontend Design Specification

## Cacao Mortality Tracking and Inventory System with Data Analytics

**Document Type:** Frontend Design Specification  
**Frontend Stack:** Laravel Blade, Bootstrap 5, JavaScript, ECharts  
**Design Basis:** CacaoTrack PRD + approved CacaoTrack ERD  
**Status:** Working frontend design baseline

---

## 1. Purpose

This document defines the frontend structure, visual hierarchy, interaction patterns, responsive behavior, and page-level UI requirements for CacaoTrack.

The design must refine the existing frontend rather than replace it. It must remain consistent with the approved PRD and ERD, use real application data, and prioritize usability, readability, validation, traceability, and thesis-defense readiness.

The approved frontend stack is Laravel Blade, Bootstrap 5, JavaScript, and ECharts. No SPA framework is required.

---

## 2. Design Principles

### 2.1 Core Design Direction

CacaoTrack should visually communicate:

- Cacao nursery operations
- Inventory control
- Mortality monitoring
- Replanting
- Daily operational work
- Activity traceability
- Analytics and reporting

The visual style should be:

- Clean
- Professional
- Agricultural but restrained
- Readable
- Consistent
- Responsive
- Operational
- Appropriate for academic/thesis presentation

### 2.2 Avoid

Do not introduce:

- Excessive glassmorphism
- Neon gradients
- Futuristic control-center styling
- Excessive glowing effects
- Decorative 3D graphics
- AI/robot imagery
- Gaming or cryptocurrency dashboard patterns
- Excessive animation
- Decorative UI with no operational purpose

**Rule:** every visible component should have a functional reason.

---

# 3. Application Shell

## 3.1 Global Layout

Use a persistent application shell:

```text
+---------------------------------------------------------------+
| Sidebar                  | Top/Header Area                    |
|                          |------------------------------------|
| CacaoTrack               | Page title / user context         |
| Dashboard                |                                    |
|                          | Main page content                 |
| Operational              |                                    |
|  Daily Checklist         |                                    |
|  Mortality Tracking      |                                    |
|  Replanting              |                                    |
|                          |                                    |
| Inventory                |                                    |
|  Inventory               |                                    |
|  Stock In                |                                    |
|  Stock Out               |                                    |
|  Stock Reversal          |                                    |
|                          |                                    |
| Management               |                                    |
|  Users                   |                                    |
|  Suppliers               |                                    |
|  Clients                 |                                    |
|  Varieties               |                                    |
|  Scions                  |                                    |
|  Pot Types               |                                    |
|  Batches                 |                                    |
|                          |                                    |
| Analytics                |                                    |
|  Operational Analytics   |                                    |
|  Tabular Deliverables    |                                    |
|  Historical Analytics    |                                    |
|                          |                                    |
| Account                  |                                    |
|  Activity History        |                                    |
|  Profile                 |                                    |
|  Logout                  |                                    |
+---------------------------------------------------------------+
```

The existing sidebar and navigation should be preserved where they already work.

## 3.2 Sidebar

The sidebar should:

- Remain visually stable across pages.
- Clearly group navigation by functional area.
- Highlight the current page.
- Use concise labels.
- Avoid unnecessary icons or decorative elements.
- Respect the signed-in user's permissions.
- Collapse appropriately on smaller screens.

Sidebar groups:

1. Dashboard
2. Operational
3. Inventory
4. Management
5. Analytics
6. Account

Permission-controlled links must not be treated as the security mechanism. Server-side authorization remains authoritative.

## 3.3 Page Header

Each page should have a consistent header containing:

- Page title
- Short functional description where useful
- Primary action when applicable
- Breadcrumb or contextual navigation only when it improves orientation

Example:

```text
Inventory
Manage current cacao stock by batch.

[ + Record Stock In ]
```

---

# 4. Visual System

## 4.1 Color Strategy

Use a restrained cacao/agricultural palette.

Recommended semantic usage:

- Primary: cacao/earth tone
- Secondary: muted agricultural green
- Neutral: white, light gray, charcoal
- Success: completed/healthy/available
- Warning: pending/attention
- Danger: mortality/errors/destructive actions
- Info: informational states

Do not use color as the only way to communicate meaning.

Status should combine:

- Text
- Badge
- Optional icon
- Color

## 4.2 Typography

Prioritize readability.

Use:

- Strong page headings
- Medium-weight section headings
- Comfortable body text
- Compact but readable table text
- Clear labels
- Consistent capitalization

Avoid excessively large dashboard headings and decorative typography.

## 4.3 Cards

Cards should be used for:

- KPIs
- Summary information
- Grouped form sections
- Chart containers
- Important operational summaries

Avoid creating cards around every small piece of information.

---

# 5. Dashboard Design

## 5.1 Dashboard Purpose

The dashboard must answer operational questions quickly.

Required KPI areas:

1. Total Active Inventory
2. Crude Mortality Rate
3. Replanting Deficit

All values must come from real database data.

## 5.2 KPI Layout

Desktop:

```text
+----------------------+----------------------+----------------------+
| Total Active         | Crude Mortality      | Replanting Deficit   |
| Inventory             | Rate                 |                      |
|  REAL VALUE           | REAL VALUE           | REAL VALUE            |
| short explanation     | period/filter       | short explanation    |
+----------------------+----------------------+----------------------+
```

On tablet/mobile, stack or wrap cards without causing horizontal overflow.

## 5.3 Dashboard Analytics

Use ECharts for appropriate analytical visualizations.

Possible dashboard visualizations:

- Mortality Trend
- Inventory by Variety
- Survival vs Mortality

Every chart must provide:

- Clear title
- Correct units
- Tooltip
- Responsive resizing
- Meaningful labels
- Empty state
- Appropriate legend/axis
- No fabricated values

Empty chart state:

> No data available for the selected period.

## 5.4 Dashboard Filters

Where supported by the backend, filters should be presented above the relevant analytics:

- Date/period
- Batch
- Variety
- Mortality cause

Keep filters compact and understandable.

---

# 6. Operational Pages

## 6.1 Daily Checklist

### Purpose

Support daily nursery activities while preserving completion history.

### Main layout

```text
Daily Checklist
Track and verify today's nursery tasks.

[ Date ] [ Priority ] [ Search ]

+---------------------------------------------------------------+
| Task | Priority | Status | Completed By | Verified | Actions |
+---------------------------------------------------------------+
| ...  | High     | ...    | ...          | ...      | ...     |
+---------------------------------------------------------------+

[ + Add Task ]    [ Archived Tasks ]
```

### Task interaction

Active tasks can be:

- Viewed
- Completed
- Verified where authorized
- Edited where authorized
- Archived instead of deleted

Archived tasks:

- Do not appear in the active daily checklist.
- Preserve Task_Complete history.
- Can be restored.
- May be **permanently deleted only when they have no completion history**
  (approved ERD §2.4/§9.6 refinement). A task with any `Task_Complete` history
  must not be hard-deleted — its traceability is preserved by keeping it
  archived; an active task must be archived first.

The Delete action is therefore a guarded exception, offered only in the Archived
tab for history-free tasks. It must never destroy completion history.

The Delete action is additionally restricted to the **Manager** and
**Administrator** roles. The button (and its confirmation modal) is hidden from
other roles, and the server rejects the request with **403** regardless of the UI.
This is a role-based authorization, not a new permission key.

### Printable checklist

Provide a clear print action for the daily checklist.

The PDF/print output should use the existing normalized Activity_Task and Task_Complete information. Do not introduce a report table merely for printing.

---

## 6.2 Mortality Tracking

### Main view

```text
Mortality Tracking

[ + Record Mortality ]

Filters:
[ Batch ] [ Cause ] [ Date From ] [ Date To ]

+---------------------------------------------------------------+
| Batch | Quantity | Cause | Recorded By | Date/Time | Actions |
+---------------------------------------------------------------+
```

### Record form

Fields:

- Batch
- Quantity
- Cause
- Date/time

Validation must clearly identify:

- Required fields
- Invalid quantity
- Invalid batch
- Invalid date
- Business-rule violations

### Adjustment

Authorized users can access a controlled adjustment action.

Adjustment form:

- Mortality record
- Adjustment quantity
- Reason
- Approving user
- Adjustment date/time

The UI must make it clear that an adjustment is a controlled correction, not deletion.

---

## 6.3 Replanting

Main fields:

- Batch
- Quantity
- Replanting date
- Remark

Page structure:

```text
Replanting
Record replacement/replanting activity.

[ + Record Replanting ]

+---------------------------------------------------------------+
| Batch | Quantity | Date | Remark | Recorded By | Actions      |
+---------------------------------------------------------------+
```

Show applicable replanting deficit/capacity information when supported by the backend.

Do not invent expected quantities.

---

# 7. Inventory Pages

## 7.1 Inventory

Purpose: display current inventory by batch.

Main columns:

- Batch
- Batch Code
- Variety where applicable
- Current Quantity
- Status
- Last Update
- Actions where applicable

Use:

- Search
- Filters
- Pagination
- Status badges
- Empty state

The inventory value must come from the database.

## 7.2 Stock In

Form fields:

- Supplier
- Batch
- Quantity
- Date Received

Layout:

```text
Record Stock In

Transaction Information
[ Supplier              ]
[ Batch                 ]
[ Quantity              ]
[ Date Received         ]

[ Cancel ] [ Record Stock In ]
```

Before submission, show useful validation feedback.

After success, provide specific feedback such as:

> Stock-in transaction recorded successfully.

## 7.3 Stock Out

Form fields:

- Client
- Batch
- Quantity
- Total Amount where applicable
- Release Date

Before submission:

- Validate required fields.
- Validate numeric quantity.
- Ensure selected batch is valid.
- Protect against quantity greater than available inventory.

Confirmation for an important transaction may use:

> Are you sure you want to record this stock-out transaction?

## 7.4 Stock Reversal

The reversal UI must clearly identify the original transaction.

Display:

- Original transaction
- Transaction type derived from the selected original record
- Reversible quantity
- Requested reversal quantity
- Reason
- User
- Date/time

The UI should explain that reversal corrects an existing transaction rather than deleting it.

Confirmation:

> Are you sure you want to reverse this transaction?

Never allow the interface to suggest a reversal beyond the reversible quantity.

---

# 8. Management Pages

Management pages should share one consistent CRUD pattern.

## 8.1 Standard Management Layout

```text
<Page Name>
Manage <records/master data>.

[ Search ] [ Filters if needed ]                 [ + Add ]

+---------------------------------------------------------------+
| Reference | Name | Description | Status | Actions              |
+---------------------------------------------------------------+
| ...       | ...  | ...         | ...    | Edit | Archive/etc. |
+---------------------------------------------------------------+
```

Use:

- Search
- Pagination
- Clear table headers
- Consistent action placement
- Modal or dedicated form according to existing project conventions
- Server-side validation feedback
- Empty states

## 8.2 Suppliers

Fields:

- Supplier Name
- Contact Number
- Address

Do not imply that supplier names are unique unless the backend/ERD requires it.

## 8.3 Clients

Fields:

- Client Name
- Contact Number
- Address

## 8.4 Varieties

Fields supported by the ERD:

- Variety Name
- Description

## 8.5 Scions

Fields:

- Scion Name
- Description

## 8.6 Pot Types

Fields:

- Pot Name
- Description

## 8.7 Batches

Batch management should clearly expose the central operational fields:

- Batch Code
- Variety
- Scion
- Pot Type
- User
- Total Grafted
- Viable Count
- Available Count
- Grafted Date
- Status
- Created Date

Batch pages should avoid overwhelming the user with every related record at once.

Use a detail view for deeper batch information:

```text
Batch B-XXX

Summary
Total Grafted | Viable | Available | Status

Batch Information
Variety | Scion | Pot Type | Grafted Date | Created By

Related Operations
Mortality | Replanting | Inventory | Stock In | Stock Out | Activity
```

---

# 9. User and Role Management

## 9.1 User Management

Authorized users should see:

- User name
- Email
- Role
- Created date
- Actions

User forms should support the fields defined by the application/ERD.

Passwords must never be displayed as plaintext.

## 9.2 Role Management

The role interface should make permissions understandable.

Example:

```text
Role: Manager

Description:
Operational management access.

Access Restrictions
[✓] Manage Inventory
[✓] Manage Stock
[✓] Record Operations
[✓] View Analytics
[✓] Generate Reports
[✓] View Activity
```

Important rules:

- Administrator is a protected system role.
- Administrator cannot be renamed or deleted.
- A role assigned to a user cannot be deleted.
- Every role must have at least one permission.
- Permission keys come from the fixed application catalog.
- The UI does not replace server-side authorization.

The interface should distinguish between:

- System role
- User-created role
- Assigned/in-use role

---

# 10. Analytics Design

## 10.1 Operational Analytics

Provide readable analytics rather than decorative charts.

Potential sections:

- Inventory
- Mortality
- Replanting
- Batch operations

Every chart must answer a defined operational question.

## 10.2 Tabular Deliverables

### Cacao Tree Stock Ledger

Columns may include:

- Batch
- Batch Code
- Variety
- Scion
- Grafted Quantity
- Viable Quantity
- Available Quantity
- Mortality
- Replanting
- Status
- Relevant Dates

Provide:

- Filters
- Search
- Pagination where appropriate
- Print/report action where supported

The ledger should be generated from normalized operational data.

### Mortality Root-Cause Matrix

Present:

- Mortality Cause
- Quantity
- Batch
- Variety
- Time Period

The interface should help users identify mortality patterns without fabricating conclusions.

## 10.3 Historical Analytics

Required areas:

### Mortality Seasonality Heatmap

Use ECharts where appropriate.

### Age Profile

Derive age from available dates and selected reporting date.

### Gap Report

Only display gaps when expected and actual values are defensible from available data.

If data is insufficient:

> Insufficient data to calculate this gap for the selected period.

Do not show invented expected values.

---

# 11. Activity History

Activity History is the readable audit/history interface.

Filters:

- Search
- User
- Activity Type
- Batch
- Date From
- Date To

Columns:

- User
- Activity Type
- Batch
- Description
- Date/Time

Example:

```text
Activity History

[ Search ] [ User ] [ Type ] [ Batch ] [ Date Range ]

+----------------------------------------------------------------+
| User | Type | Batch | Description | Date/Time                 |
+----------------------------------------------------------------+
| ...  | ...  | ...   | ...         | ...                      |
+----------------------------------------------------------------+
```

The page should emphasize traceability without becoming visually dense.

---

# 12. Profile

The Profile page should provide a clean account-focused interface.

Show the authenticated user's applicable account information.

Recommended grouping:

```text
Profile

Account Information
Full Name
Email
Role
Created Date

Security
Password change controls where implemented

[ Save Changes ]
```

Do not expose password values.

---

# 13. Forms

All forms should follow one consistent structure.

## 13.1 Form Pattern

```text
Section Title
Short explanation

Field Label *
[input]

Field Label
[input]

[Cancel] [Primary Action]
```

Use:

- Explicit labels
- Required indicators
- Appropriate input types
- Helpful placeholders only when useful
- Logical field grouping
- Inline validation feedback

## 13.2 Validation

Validation should appear close to the affected field.

Example:

```text
Quantity *
[ -5 ]

Please enter a valid positive quantity.
```

For general validation failure:

> Please correct the highlighted fields.

Client-side JavaScript validation is for immediate feedback only.

Laravel/server-side validation remains authoritative.

---

# 14. Tables

Tables are a primary operational UI element.

Requirements:

- Clear headers
- Consistent alignment
- Search where useful
- Filters where useful
- Sorting where useful
- Pagination for large datasets
- Status indicators
- Action controls
- Empty states
- Responsive behavior

Do not sacrifice readability for excessive columns.

On small screens, use:

- Horizontal table scrolling only when necessary
- Responsive column strategies
- Detail views
- Stacked metadata where appropriate

Never allow the entire page to overflow horizontally.

---

# 15. Modals and Confirmation

Use modals for short, focused interactions.

Suitable uses:

- Confirmation
- Small CRUD forms where already established by the application
- Adjustment/reversal confirmation

Avoid very large multi-step forms inside modals.

Destructive or corrective actions should explain what will happen.

Example:

> Are you sure you want to reverse this transaction?

The confirmation should identify the relevant transaction when possible.

---

# 16. Feedback and State Design

## 16.1 Success

Use clear session-flash feedback:

> Stock-in transaction recorded successfully.

## 16.2 Validation

> Please correct the highlighted fields.

## 16.3 Error

> Unable to record the stock-in transaction.

## 16.4 Confirmation

> Are you sure you want to reverse this transaction?

Feedback should be concise and specific.

The system uses transient feedback rather than a persistent notifications database entity. Activity History remains the authoritative operational history.

---

# 17. Empty, Loading, and Error States

Every major table, chart, list, and asynchronous operation should have explicit states.

## Empty

> No records found.

For analytics:

> No data available for the selected period.

## Loading

Use a restrained spinner or loading indicator.

Avoid decorative animations.

## Error

> Unable to load the requested data. Please try again.

## Success

Show confirmation after the operation succeeds.

Important transaction buttons should prevent accidental duplicate submissions.

---

# 18. Responsive Design

Target:

- Desktop
- Laptop
- Tablet
- Small mobile screens

Use Bootstrap 5 responsive utilities.

## 18.1 Desktop

Prioritize:

- Persistent sidebar
- Wide tables
- Multi-column KPI cards
- Chart grids

## 18.2 Tablet

Adapt:

- Sidebar behavior
- KPI cards
- Form columns
- Chart widths
- Table density

## 18.3 Mobile

Prioritize:

- Accessible navigation
- Single-column forms
- Stacked cards
- Readable buttons
- Scrollable data tables where unavoidable
- No page-level horizontal overflow

Recommended test widths:

- 1920
- 1440
- 1366
- 1024
- 768
- 430
- 390
- 375

---

# 19. Accessibility

Use:

- Adequate text contrast
- Visible focus states
- Semantic labels
- Accessible buttons
- Meaningful form labels
- Keyboard-accessible controls
- Clear error messages

Never communicate an important state through color alone.

Example:

Do not use only a red badge.

Use:

> **Unavailable** — with an appropriate status badge.

---

# 20. Authorization-Aware UI

The sidebar and action controls should reflect the signed-in user's permissions.

Examples:

- No `manage_users` → hide User/Role management actions.
- No `manage_inventory` → hide management actions.
- No `manage_stock` → hide stock transaction actions.
- No `record_operations` → hide operational recording actions.
- No `view_analytics` → hide analytics pages.
- No `generate_reports` → hide report-generation actions.
- No `view_activity` → hide Activity History.

Important:

**Hiding a button is not authorization.**

Laravel middleware/server-side authorization must enforce the permission.

Unauthorized direct requests must still be rejected.

---

# 21. Data Integrity in the UI

The frontend must not create the impression that an invalid operation is allowed.

Examples:

### Stock Out

Show available quantity where useful.

```text
Available Inventory: 500

Quantity *
[ 600 ]

Quantity exceeds available inventory.
```

### Mortality

Show applicable batch information where useful.

### Replanting

Show applicable deficit/capacity where supported.

### Reversal

Show the reversible quantity before accepting the reversal.

These displays are usability aids. The backend remains authoritative.

---

# 22. Performance Guidelines

Use the existing Laravel architecture efficiently.

Frontend should:

- Avoid unnecessarily loading large datasets.
- Use paginated backend results.
- Avoid rendering unnecessary DOM elements.
- Initialize charts only when needed.
- Resize ECharts instances responsively.
- Avoid excessive animation.
- Avoid duplicate requests.

Use Bootstrap utilities instead of custom CSS when the utility already satisfies the requirement.

---

# 23. Frontend File Organization

Follow the existing project structure instead of introducing a replacement architecture.

A reasonable Blade organization is:

```text
resources/views/
├── layouts/
│   └── app.blade.php
├── dashboard/
├── operational/
│   ├── checklist/
│   ├── mortality/
│   └── replanting/
├── inventory/
│   ├── index.blade.php
│   ├── stock-in/
│   ├── stock-out/
│   ├── reversals/
│   └── suppliers/
├── management/
│   ├── users/
│   ├── clients/
│   ├── varieties/
│   ├── scions/
│   ├── pot-types/
│   └── batches/
├── analytics/
├── activity/
└── profile/
```

This is a design guideline, not permission to reorganize existing files unnecessarily.

Inspect the actual project before creating or moving views.

---

# 24. Frontend Component Patterns

Prefer reusable Blade components/partials for repeated UI patterns such as:

- Page headers
- KPI cards
- Status badges
- Form fields
- Validation messages
- Empty states
- Loading states
- Action buttons
- Confirmation dialogs
- Responsive table wrappers

Do not create abstractions that make simple pages harder to understand.

---

# 25. Design Rules for CRUD and Archive

Where a record has operational history, avoid hard deletion when deletion would destroy useful traceability.

For Daily Checklist tasks, the approved design is:

```text
Active Task
   ↓
Archive
   ↓
Archived Task
   ↓ 
Restore
   ↓
Active Task
```

Archive/restore preserves completion history.

The frontend should make the distinction visible:

- Active
- Archived
- Restore

Do not present archive as if it permanently deletes data.

---

# 26. Frontend QA Checklist

## Layout

- [ ] Existing sidebar preserved
- [ ] Navigation is consistent
- [ ] Active page is identifiable
- [ ] No unnecessary redesign
- [ ] No page-level horizontal overflow

## Dashboard

- [ ] Total Active Inventory uses real data
- [ ] Crude Mortality Rate uses documented calculation
- [ ] Replanting Deficit uses defensible data
- [ ] Charts use real data
- [ ] Empty chart states work
- [ ] Charts resize correctly

## Forms

- [ ] Labels are clear
- [ ] Required fields are identified
- [ ] Client-side validation works
- [ ] Server-side validation is visible
- [ ] Business-rule errors are understandable
- [ ] Duplicate submissions are prevented

## Tables

- [ ] Search works where required
- [ ] Filters work where required
- [ ] Pagination works where required
- [ ] Status is readable
- [ ] Actions are understandable
- [ ] Empty states exist
- [ ] Mobile behavior is usable

## Authorization

- [ ] Sidebar respects permissions
- [ ] Action buttons respect permissions
- [ ] Unauthorized routes remain protected server-side
- [ ] Administrator role remains protected
- [ ] Role restrictions are understandable

## Operations

- [ ] Checklist
- [ ] Mortality
- [ ] Mortality adjustment
- [ ] Replanting
- [ ] Inventory
- [ ] Stock In
- [ ] Stock Out
- [ ] Stock Reversal
- [ ] Supplier
- [ ] Client
- [ ] Variety
- [ ] Scion
- [ ] Pot Type
- [ ] Batch
- [ ] Activity History
- [ ] Profile

## Responsive

- [ ] 1920px
- [ ] 1440px
- [ ] 1366px
- [ ] 1024px
- [ ] 768px
- [ ] 430px
- [ ] 390px
- [ ] 375px

---

# 27. Requirement-to-UI Traceability

| Requirement | Primary UI | Main Data |
|---|---|---|
| FR-01 Authentication | Login / session UI | Users |
| FR-02 User Management | Users + Roles | Users, Roles, Role_Permissions |
| FR-03 Daily Checklist | Checklist | Activity_Task, Task_Complete |
| FR-04 Mortality | Mortality page | Mortality_Record |
| FR-05 Adjustments | Adjustment UI | Mortality_Adjustments |
| FR-06 Replanting | Replanting page | Replanting_Record |
| FR-07 Inventory | Inventory page | Inventory, Batch |
| FR-08 Stock In | Stock In form/table | Stock_In, Supplier, Batch |
| FR-09 Stock Out | Stock Out form/table | Stock_Out, Client, Batch |
| FR-10 Stock Reversal | Reversal page | Stock_Reversal |
| FR-11 Supplier | Supplier management | Supplier |
| FR-12 Client | Client management | Client |
| FR-13 Variety | Variety management | Variety |
| FR-14 Scion | Scion management | Scions |
| FR-15 Pot Type | Pot Type management | Pot_Type |
| FR-16 Batch | Batch management | Batch |
| FR-17 Activity History | Activity History | Activity_Log |
| Dashboard | KPI cards/charts | Operational records |
| Tabular Deliverables | Ledger + matrix | Normalized operational data |
| Historical Analytics | Heatmap + age + gap | Historical operational data |

---

# 28. Final Frontend Principle

CacaoTrack frontend design should follow:

```text
Existing Working UI
        ↓
Understand the Requirement
        ↓
Follow ERD / PRD
        ↓
Use Real Data
        ↓
Validate Always
        ↓
Make Operations Clear
        ↓
Make Data Readable
        ↓
Make the Interface Responsive
        ↓
Preserve Traceability
        ↓
QA and Final Validation
```

The design goal is not to make CacaoTrack visually complicated.

The goal is to make it:

**Clear + Reliable + Operational + Readable + Responsive + Thesis-Ready**

---

## 29. Source Alignment

This specification is based on the uploaded CacaoTrack PRD and approved ERD.

The PRD establishes Laravel Blade, Bootstrap 5, JavaScript, and ECharts as the fixed frontend stack and requires preservation/refinement of the existing frontend.

The ERD establishes the operational entities, relationships, role/permission structure, checklist archive/restore fields, inventory relationship, stock transactions, reversal structure, and analytics history.

No new database entity is required merely to implement this frontend design.

## Final Rule

**PRESERVE WHAT WORKS.**

**BUILD ONLY WHAT IS REQUIRED.**

**FOLLOW THE ERD.**

**FOLLOW THE PRD.**

**VALIDATE ALWAYS.**

**USE REAL DATA.**

**DESIGN FOR USERS.**

**DESIGN FOR OPERATIONS.**

**DESIGN FOR READABILITY.**

**DESIGN FOR THESIS DEFENSE.**
