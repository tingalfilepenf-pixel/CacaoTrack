# CacaoTrack Core Engineering Rules

## RULE 1 — ALWAYS VALIDATE

Validation is mandatory at every stage.

Never assume that something works because:
- the page loads;
- the button exists;
- a route exists;
- a migration succeeds;
- a database record exists;
- the UI displays a value;
- a chart renders.

Every implementation must be validated against the actual requirement,
actual database structure, actual Laravel code, and actual user workflow.

Validation must include:

1. Requirement validation
2. ERD validation
3. Database validation
4. Laravel model/relationship validation
5. Business-rule validation
6. Server-side validation
7. Client-side validation
8. Transaction/inventory validation
9. UI validation
10. Integration validation
11. Error-state validation
12. Final end-to-end validation


## RULE 2 — FOLLOW THE DEVELOPMENT CHAIN

Do not skip architectural stages.

PRD
 ↓
Current Project Audit
 ↓
ERD Validation
 ↓
Open Decision Resolution
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
Reports / Tables
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


## RULE 3 — NEVER GUESS STRUCTURE

Do not invent:

- tables
- columns
- foreign keys
- relationships
- routes
- roles
- business rules
- formulas
- permissions
- analytics definitions

If something is ambiguous, identify it as an OPEN DECISION.

Do not silently convert an implementation assumption into a database design.


## RULE 4 — ERD IS THE DATABASE SOURCE OF TRUTH

Before changing migrations:

ERD
 ↕
MySQL

must remain consistent.

If the database structure changes:

1. Review the reason.
2. Update the ERD.
3. Validate PK/FK relationships.
4. Update the PRD if the requirement changes.
5. Then modify migrations.


## RULE 5 — REAL DATA ONLY

Do not hardcode:

- dashboard KPI values
- mortality rates
- inventory quantities
- stock balances
- analytics results
- report totals
- chart datasets

unless the value is explicitly a static configuration/reference value.

Operational and analytical values must originate from the actual database.


## RULE 6 — FORMULAS MUST BE CENTRALIZED

Any important calculation must have one defined business rule.

Example:

Crude Mortality Rate
= Approved Numerator
÷ Approved Denominator
× 100

The same calculation must be used by:

Dashboard
 ↓
Operational Analytics
 ↓
Historical Analytics
 ↓
Reports
 ↓
Decision Support / AI

Never create slightly different formulas in different controllers or views.


## RULE 7 — INVENTORY MUST BE TRANSACTION-SAFE

Stock-In, Stock-Out, and Stock-Reversal operations must validate:

- valid batch
- valid user
- valid supplier/client where applicable
- positive quantity
- available quantity
- reversal eligibility
- inventory balance
- duplicate/conflicting transactions

Inventory-changing operations should use database transactions where appropriate.

A failed transaction must not leave partial inventory changes.


## RULE 8 — FRONTEND DOES NOT DEFINE THE DATABASE

The UI must represent the approved:

PRD
+
ERD
+
Business Rules

Do not change the database simply because a frontend form is easier to build another way.


## RULE 9 — EXISTING WORKING FEATURES ARE PRESERVED

Before modifying a feature:

1. Inspect it.
2. Determine whether it works.
3. Determine whether it matches the PRD.
4. Determine whether it matches the ERD.
5. Identify the smallest required change.

Do not rebuild working functionality unnecessarily.


## RULE 10 — EVERY FEATURE NEEDS A COMPLETE CHAIN

For every requirement, verify:

Requirement
 ↓
UI
 ↓
Route
 ↓
Controller / Service
 ↓
Validation
 ↓
Model
 ↓
Database
 ↓
Business Logic
 ↓
Output
 ↓
User-visible result

If one link is missing, the feature is not considered complete.


## RULE 11 — TEST FAILURE PATHS

Do not validate only successful submissions.

Test:

- empty fields
- invalid values
- duplicate records
- invalid foreign keys
- excessive quantities
- unauthorized actions
- missing records
- failed transactions
- database errors
- empty datasets
- invalid dates
- negative quantities
- boundary values


## RULE 12 — ANALYTICS MUST BE TRACEABLE

Every dashboard/report/chart value must be traceable to:

Database records
 ↓
Defined calculation
 ↓
Filtered dataset
 ↓
Displayed result

If a chart cannot be explained from actual database data,
it must not be presented as a completed analytical feature.


## RULE 13 — STOP CONDITIONS

The development agent must STOP and report instead of guessing when:

- the ERD conflicts with the database;
- the PRD conflicts with the current implementation;
- a required business rule is undefined;
- a foreign-key relationship is ambiguous;
- a role/permission is undefined;
- an analytics formula is undefined;
- a transaction rule is unclear;
- implementing the feature would require an unapproved schema change.


## RULE 14 — FINAL VALIDATION

Before declaring CacaoTrack complete:

PRD
 ↕
ERD
 ↕
MySQL
 ↕
Laravel
 ↕
Routes
 ↕
Controllers / Services
 ↕
Models
 ↕
Validation
 ↕
UI
 ↕
Analytics
 ↕
Reports

must be checked for consistency.

Final status must distinguish:

WORKING
NEEDS REFINEMENT
MISSING
INCORRECT
BLOCKED / OPEN DECISION

Never report "complete" simply because the application builds.