# CacaoTrack

## Cacao Mortality Tracking and Inventory System with Data Analytics

CacaoTrack is a web-based cacao nursery management system designed to support inventory management, cacao mortality monitoring, operational activity recording, replanting monitoring, and data analytics.

The system is intended to improve operational visibility, data accuracy, inventory control, and decision-making within a cacao nursery environment.

---

## Project Purpose

CacaoTrack provides a centralized platform for recording, monitoring, and analyzing cacao nursery operations.

The system focuses on:

- Cacao batch monitoring
- Daily operational checklist
- Mortality tracking
- Mortality adjustments
- Replanting monitoring
- Inventory management
- Stock-in transactions
- Stock-out transactions
- Stock reversal and correction
- Supplier management
- Client management
- Cacao variety management
- Scion management
- Pot type management
- Activity history
- Operational analytics
- Historical analytics and reports

---

## Specific Objectives

The system aims to:

1. Improve the efficiency and accuracy of inventory management by automating stock-in and stock-out transactions of farm resources.

2. Track data accuracy and operational visibility while monitoring cacao mortality.

3. Enhance farm monitoring by supporting timely interventions and organized activity recording.

4. Collect and analyze data to support better decision-making and improve operational management in an agricultural environment.

5. Evaluate the system in terms of functionality, usability, efficiency, and readability based on users' assessment.

---

## Technology Stack

### Backend

- Laravel
- PHP
- Eloquent ORM

### Database

- MySQL

### Frontend

- Laravel Blade
- Bootstrap 5
- JavaScript

### Data Visualization

- ECharts

### Development Environment

- Laragon
- Visual Studio Code

---

## System Architecture

CacaoTrack follows a Laravel-based web application architecture.

```text
User
  │
  ▼
Laravel Blade + Bootstrap + JavaScript
  │
  ▼
Laravel Routes
  │
  ▼
Controllers / Services
  │
  ▼
Eloquent ORM
  │
  ▼
MySQL Database
  │
  ▼
Operational Data
  ├── Dashboard
  ├── Reports
  └── Analytics
```

---

## Main System Modules

### 1. Dashboard

The Dashboard provides an operational overview of the cacao nursery.

Key indicators include:

- Total Active Inventory
- Crude Mortality Rate
- Replanting Deficit

The Dashboard may also provide visual summaries of relevant operational data using ECharts.

Dashboard values must come from actual system data.

### 2. Operational

#### Daily Checklist

The Daily Checklist allows authorized users to record and monitor daily nursery activities and task completion.

The module should support:

- Daily task display
- Task completion
- User recording
- Verification where applicable
- Remarks
- Activity traceability

#### Mortality Tracking

Mortality Tracking records cacao mortality associated with a batch.

The system should record information such as:

- Batch
- Mortality quantity
- Mortality cause
- Date and time recorded
- Responsible user

Mortality records must be validated before being stored.

#### Mortality Adjustments

Mortality Adjustments provide a controlled mechanism for correcting previously recorded mortality data.

Adjustments must:

- Reference a valid mortality record
- Require a valid reason
- Preserve the original transaction/history
- Record the responsible user or approver
- Maintain data integrity

#### Replanting

The Replanting module records cacao replanting activities.

The system should support:

- Batch selection
- Replanting quantity
- Replanting date
- Remarks
- Responsible user

Replanting data contributes to operational monitoring and gap analysis.

### 3. Inventory

#### Supplier

The Supplier module maintains supplier information used for stock transactions.

Supplier information includes:

- Supplier name
- Contact number
- Address

#### Client

The Client module maintains client information used for stock-out transactions.

Client information includes:

- Client name
- Contact number
- Address

#### Variety

The Variety module maintains cacao variety reference information.

#### Scions

The Scions module maintains scion reference information.

#### Pot Type

The Pot Type module maintains pot type reference information.

#### Batch

The Batch entity represents a cacao nursery batch and connects relevant production information such as:

- Variety
- Scion
- Pot type
- User
- Batch code
- Grafted quantity
- Viable quantity
- Available quantity
- Grafted date
- Status

Batch management must follow the approved ERD and business rules.

#### Stock In

Stock In records incoming cacao nursery stock associated with a supplier and batch.

A stock-in transaction includes:

- Supplier
- Batch
- User
- Quantity
- Date received

Stock-in operations must validate the transaction before updating inventory.

#### Stock Out

Stock Out records released cacao nursery stock associated with a client and batch.

A stock-out transaction includes:

- Client
- Batch
- User
- Quantity
- Total amount
- Release date

The system must prevent stock-out quantities from exceeding valid available inventory.

#### Stock Reversal

Stock Reversal provides a controlled mechanism for correcting applicable stock transactions.

A reversal must:

- Reference a valid transaction
- Record the quantity
- Require a reason
- Record the responsible user
- Preserve transaction history
- Maintain inventory consistency

The exact database implementation must follow the validated ERD design.

#### Stock Ledger

The Cacao Tree Stock Ledger provides a tabular view of stock movements.

The ledger should allow users to understand:

- Incoming stock
- Outgoing stock
- Adjustments and reversals
- Batch information
- Quantities
- Dates
- Transaction history

The Stock Ledger is a report/output and should use existing transaction data rather than creating unnecessary duplicate storage.

### 4. Analytics

CacaoTrack provides operational and historical analytics to support decision-making.

#### Operational Analytics

The system should support:

- Total Active Inventory
- Crude Mortality Rate
- Replanting Deficit

#### Mortality Root-Cause Matrix

The Mortality Root-Cause Matrix summarizes mortality according to relevant mortality causes and operational dimensions.

It should be generated from actual mortality records.

#### Mortality Seasonality Heatmap

The Seasonality Heatmap presents historical mortality patterns across appropriate time periods.

The visualization should be generated from actual mortality data.

#### Age Profile

The Age Profile provides an analytical view of cacao stock according to appropriate age or batch-age groupings.

The calculation rules must be consistent with the approved business requirements.

#### Gap Report

The Gap Report identifies relevant differences between available stock, mortality, replanting requirements, and other validated operational quantities.

The exact calculation must follow the approved system requirements.

---

## Database Design

CacaoTrack follows an approved Entity Relationship Diagram (ERD).

The current ERD contains the following entities:

1. Users
2. Activity Log
3. Activity Task
4. Task Complete
5. Batch
6. Variety
7. Scions
8. Pot Type
9. Mortality Record
10. Mortality Adjustments
11. Replanting Record
12. Inventory
13. Supplier
14. Client
15. Stock In
16. Stock Out
17. Stock Reversal
18. Analytics

The ERD is the primary reference for database relationships and structure.

The database implementation must be validated against the ERD before being considered complete.

---

## Data Integrity

CacaoTrack must maintain consistency between:

- Users
- Batches
- Mortality records
- Mortality adjustments
- Replanting records
- Inventory
- Stock-in transactions
- Stock-out transactions
- Stock reversals
- Activity records
- Analytics

Inventory-changing operations should use appropriate database transactions to prevent inconsistent balances.

---

## Validation

CacaoTrack uses multiple levels of validation.

### Client-Side Validation

JavaScript and Bootstrap-based validation may provide immediate feedback to users.

### Server-Side Validation

Laravel must validate all important submitted data.

Client-side validation must never replace server-side validation.

### Business-Rule Validation

The system must verify rules such as:

- Valid batch references
- Valid quantities
- Sufficient available inventory
- Valid transaction states
- Valid relationships
- Authorized operations

### Database Validation

The database must enforce appropriate:

- Primary keys
- Foreign keys
- Unique constraints
- Required fields
- Data types
- Referential integrity

---

## Security

The application should use Laravel security mechanisms appropriately.

Important considerations include:

- Authentication
- Authorization
- CSRF protection
- Server-side validation
- Password hashing
- Secure session handling
- Mass-assignment protection
- Access control
- Safe database queries

Sensitive configuration information must not be committed to the repository.

Do not commit `.env` files containing credentials or secrets.

---

## Frontend Design Principles

The CacaoTrack interface should provide a professional agricultural and cacao-nursery-oriented experience.

The interface should prioritize:

- Clarity
- Readability
- Consistency
- Responsive design
- Practical workflows
- Clear navigation
- Understandable data presentation
- Appropriate Bootstrap components
- Useful ECharts visualizations

The frontend technology target is:

```text
Laravel Blade
Bootstrap 5
JavaScript
ECharts
```

Avoid unnecessary visual effects, excessive animations, and generic AI-generated dashboard patterns.

The interface should look like a practical agricultural information system, not a generic AI dashboard template.

---

## Development Principles

### 1. ALWAYS VALIDATE

Every feature must be validated before being considered complete.

Validation should include, where applicable:

- Frontend validation
- Server-side validation
- Business-rule validation
- Database validation
- Relationship validation
- Transaction validation
- Authorization validation
- Integration testing

### 2. FOLLOW THE PRD

System functionality must be traceable to the Product Requirements Document.

Do not implement functionality based solely on assumptions.

### 3. FOLLOW THE ERD

The ERD is the source of truth for database relationships and structure.

Do not:

- Invent database tables
- Invent relationships
- Duplicate existing data
- Add unnecessary foreign keys
- Create redundant fields
- Change relationships without validation

If an ERD issue is discovered, document and resolve it before implementing the affected functionality.

### 4. USE REAL DATA

Operational dashboards, reports, tables, and analytics must use actual database data.

Do not present:

- Random values
- Fake production values
- Hardcoded KPIs
- Static charts
- Fabricated analytics

Development seed data may be used for testing but must not be confused with actual production records.

### 5. PRESERVE WHAT WORKS

Existing working functionality must not be unnecessarily rebuilt.

Before modifying a feature:

1. Inspect the existing implementation.
2. Determine whether it actually works.
3. Identify the actual problem.
4. Make the smallest appropriate change.
5. Validate the result.

### 6. DO NOT GUESS

Developers and AI coding agents must inspect the actual project before making structural changes.

Do not assume:

- Routes
- Controllers
- Models
- Migrations
- Database tables
- Relationships
- Existing functionality
- Package versions
- File locations

Always inspect the project first.

---

## Complete Development Chain

Major development work should follow this sequence:

```text
PRD
  ↓
Current Project Audit
  ↓
ERD Validation
  ↓
Resolve Open Design Decisions
  ↓
MySQL Schema
  ↓
Laravel Migrations
  ↓
Models + Relationships
  ↓
Authorization
  ↓
Business Logic
  ↓
Server-Side Validation
  ↓
Database Integrity
  ↓
Core Operations
  ↓
Dashboard KPIs
  ↓
Reports + Tables
  ↓
Historical Analytics
  ↓
Frontend Refinement
  ↓
Integration Testing
  ↓
Final Validation
  ↓
Thesis Defense Readiness
```

Do not skip directly from a UI requirement to frontend implementation when the required database, backend, or business logic does not exist.

---

## Feature Completion Standard

A feature is **NOT** considered complete simply because:

- The page loads.
- A button exists.
- A modal opens.
- A form appears.
- A chart renders.
- A request is sent.

A feature is complete only when the complete functional chain works.

```text
User Action
    ↓
Frontend
    ↓
HTTP Request
    ↓
Laravel Route
    ↓
Controller / Service
    ↓
Server Validation
    ↓
Business Rules
    ↓
Database Transaction
    ↓
Database Update
    ↓
Correct Response
    ↓
Updated UI
```

---

## Testing Requirements

Before declaring a feature complete, test both successful and unsuccessful workflows.

### Positive Testing

Test:

- Valid input
- Valid quantities
- Valid relationships
- Authorized users
- Expected workflows

### Negative Testing

Test:

- Missing required fields
- Invalid values
- Invalid quantities
- Duplicate records where prohibited
- Unauthorized actions
- Nonexistent records
- Insufficient inventory
- Invalid transaction states

### Integration Testing

Verify that changes are correctly reflected in:

- Dashboard
- Inventory
- Reports
- Analytics
- Activity history
- Related records

---

## AI Coding Agent Rules

AI coding agents working on CacaoTrack must:

1. Inspect the existing project before modifying files.
2. Review the relevant PRD requirements.
3. Review the relevant ERD structure.
4. Inspect existing routes.
5. Inspect existing controllers.
6. Inspect existing models.
7. Inspect existing migrations.
8. Inspect existing Blade views.
9. Inspect existing JavaScript.
10. Avoid guessing missing architecture.
11. Avoid unnecessary rewrites.
12. Preserve working functionality.
13. Validate database changes.
14. Validate business rules.
15. Test failure paths.
16. Use real project data.
17. Report unresolved design decisions.
18. Never declare a feature complete because the UI exists.
19. Stop when the requested scope is complete and validated.

---

## Development Status

CacaoTrack is an active capstone development project.

Some frontend components may exist before their complete backend and database implementation.

Therefore, project status must distinguish between:

```text
UI Implemented
      ↓
Backend Implemented
      ↓
Database Implemented
      ↓
Integrated
      ↓
Tested
      ↓
Validated
```

A Blade page existing in `resources/views` does not automatically mean that the feature is fully functional.

---

## Documentation

The project documentation is organized as follows:

- Product Requirements Document
- Entity Relationship Diagram
- Permanent Development Rules

### Documentation Responsibilities

| Document | Purpose |
|---|---|
| `README.md` | Project overview and developer orientation |
| `docs/CacaoTrack_PRD.md` | Defines what the system must do |
| `docs/CacaoTrack_ERD.md` | Defines the approved database structure |
| `docs/CacaoTrack_Development_Rules.md` | Defines how the system must be developed and validated |

```text
                     CacaoTrack
                         │
          ┌──────────────┼──────────────┐
          │              │              │
       README           PRD            ERD
          │              │              │
    Project Guide   Requirements    Database
          │              │              │
          └──────────────┼──────────────┘
                         │
                Development Rules
                         │
                  ALWAYS VALIDATE
```

---

## Final Project Principle

> **BUILD LESS. UNDERSTAND MORE. VALIDATE ALWAYS. PRESERVE WHAT WORKS. FOLLOW THE ERD. FOLLOW THE PRD. USE REAL DATA. PROTECT TRANSACTIONS. DESIGN FOR USERS. DESIGN FOR OPERATIONS. DESIGN FOR READABILITY. DESIGN FOR THESIS DEFENSE.**