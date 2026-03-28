# Clockwork Tyres Roadmap

Date: March 29, 2026

## 1. Executive Summary

Clockwork Tyres should be built as a unified platform with three operating modes:

- Super Admin
- Supplier Admin
- Retailer Admin plus Retail Storefront

Recommended system split:

- `reporting-crm` becomes the core platform and system of record.
- `dealer-portal-Angular` becomes the reference for the retail/storefront experience.
- `tunerstop-vendor` becomes reference-only for old business rules, plan logic, and migration mapping.

This is not a skinning exercise. It is a structured platform rebuild on top of the new CRM, with selective frontend reuse.

## 2. Target Product Vision

### Super Admin

Platform owner view for:

- all suppliers
- all retailers and subscribers
- plan and subscription oversight
- wholesale enablement
- account status and lifecycle
- platform analytics
- error monitoring and operational visibility

### Supplier Admin

Supplier portal based on the CRM for:

- products
- product images
- inventory
- warehouses
- quotes and proformas
- invoices
- customers
- users and permissions
- analytics
- storefront preview of only that supplier's products

### Retailer Admin plus Storefront

Retailer gets:

- CRM admin portal
- retail-facing storefront for in-store or customer-assisted sales
- customer capture during checkout
- invoice creation from storefront orders
- supplier exploration inside admin
- supplier connection requests and approvals
- procurement flow inside admin
- analytics for retail operations

Core business shift:

- B2B supplier discovery and procurement move into admin.
- The storefront becomes retail-facing.

## 3. Architecture Decision

### Base Platform

Use `reporting-crm` as the new base.

Reasons:

- it already has roles and permissions
- it already has quotes, invoices, inventory, warehouses, reports, and customer modules
- it already has a storefront API layer
- it already separates admin users from dealer/storefront users

### Legacy Platform

Use `tunerstop-vendor` only for:

- supplier-retailer relationship reference
- old plan and entitlement logic
- old onboarding and invite behavior
- migration mapping
- analytics/reporting reference

Do not continue building on `tunerstop-vendor` as the future runtime backend.

### Storefront

Use the current Clockwork frontend as reference and partial code-reuse source, but do not keep the old API contract forever.

## 4. Required Core Platform Additions

Before the new vision can work cleanly, the CRM needs these new foundations:

- `accounts` or `organizations`
- `account_users`
- `account_types`
- `subscriptions`
- `plan_features`
- `supplier_relationships`
- account-scoped branding and settings
- account-scoped products, inventory, warehouses, customers, and orders

This is the most important missing layer.

Without account tenancy, supplier data isolation, retailer permissions, and super-admin reporting will become messy.

## 5. Repository Strategy

### Backend Repo

Current repo:

- `clockwork-tyres`

Recommendation:

- This name is acceptable if this repo is the main platform repo.
- If you want a clearer name before the project grows, rename it early to one of:
  - `clockwork-tyres-platform`
  - `clockwork-tyres-core`
  - `clockwork-tyres-backend`

Best choice:

- `clockwork-tyres-platform`

Reason:

- the repo will hold more than a backend
- it is the platform core for super admin, supplier admin, and retailer admin

### Frontend Repo

Recommended:

- create a new frontend repo

Suggested names:

- `clockwork-tyres-storefront`
- `clockwork-tyres-web`

Best choice:

- `clockwork-tyres-storefront`

Reason:

- the current Angular frontend is tightly coupled to old wheel-focused APIs and old Clockwork assumptions
- a new repo gives clean boundaries, clearer release management, and less accidental reuse of legacy behavior
- the old frontend repo can stay as a reference implementation

### Legacy Repos

Keep these as reference-only:

- `dealer-portal-Angular`
- `tunerstop-vendor`

Do not merge them into the new repo. Use them for guided extraction and migration.

## 6. Delivery Principles

- Build on the new CRM, not on the legacy backend.
- Separate account ownership from user role permissions.
- Freeze the tire catalog schema before major UI work.
- Move supplier discovery and procurement into admin.
- Keep rollout phased and pilot-driven.
- Reuse proven flows where possible, but do not preserve bad architecture for speed.

## 7. Workstreams

The roadmap should run across five workstreams:

- Platform and tenancy
- Catalog and inventory
- Admin experiences
- Storefront experience
- Migration, QA, and rollout

## 8. Phase-by-Phase Roadmap

## Phase 0: Discovery and Scope Lock

### Goal

Freeze the business model and technical direction before implementation.

### Key Decisions To Lock

- business/account types: supplier, retailer, both
- subscription matrix
- supplier count limits
- tire product schema
- service items and pricing behavior
- retail pricing rules
- procurement workflow
- payment model
- migration scope
- reporting requirements

### Tasks

- convert George's notes into a formal product brief
- produce a roles and permission matrix
- produce a supplier-retailer lifecycle diagram
- define tire catalog fields and import format
- define procurement lifecycle from retailer invoice to supplier approval
- define subscription entitlements by plan
- define data migration scope for accounts, products, inventory, and orders

### Deliverables

- approved product brief
- approved account model
- approved subscription matrix
- approved tire data schema
- approved procurement flow
- approved migration scope

### Exit Criteria

- no major business ambiguity remains around plans, roles, or catalog structure

### Estimate

- 1 to 2 weeks

## Phase 1: Platform Foundation in CRM

### Goal

Turn `reporting-crm` into a multi-account platform foundation.

### Scope

- account tenancy
- user-to-account membership
- plan and subscription foundation
- account-level settings
- account scoping in admin resources

### Tasks

- add `accounts` table
- add `account_users` table
- add account type enum or model
- add subscription and plan tables
- add account branding and feature flags
- attach ownership to users, warehouses, products, inventory, customers, and orders
- add global super-admin bypass
- add account-aware policies and query scopes
- add audit logging for account actions

### Deliverables

- CRM supports multiple supplier and retailer businesses in one platform
- admin resources are account-aware
- platform can distinguish internal roles from business entitlements

### Exit Criteria

- supplier and retailer data are isolated cleanly
- super admin can view all accounts
- non-super-admin users only see their own account data

### Estimate

- 2 to 3 weeks

## Phase 2: Tire Catalog and Inventory Model

### Goal

Replace wheel-oriented assumptions with a tire-first catalog.

### Scope

- products
- variants
- filters
- fitment/search inputs
- imports and exports
- inventory representation

### Suggested Tire Fields

- brand
- model
- section width
- aspect ratio
- rim size
- load index
- speed rating
- season
- run flat
- XL or reinforced
- origin
- year
- SKU
- supplier cost
- retail price
- track inventory
- available quantity by warehouse

### Tasks

- redesign product and variant schema
- redesign CSV import and export structure
- update product grid and inventory grid
- update search/filter service layer
- add service items such as mounting and balance, nitrogen, and wheel alignment
- define image strategy for tire products

### Deliverables

- tire-ready catalog
- tire-ready import pipeline
- updated inventory model
- updated reporting dimensions

### Exit Criteria

- one supplier can manage a complete tire catalog and inventory in CRM

### Estimate

- 2 to 3 weeks

## Phase 3: Super Admin Module

### Goal

Build the platform owner view shown in George's super-admin mockups.

### Scope

- all accounts
- subscription overview
- activation and wholesale enablement
- top-level analytics
- platform health and error log

### Tasks

- build account listing with status, type, and subscription
- show retailer count, supplier count, subscriber count
- add wholesale-enabled flag
- add account-level analytics cards
- add error log visibility
- add actions for activate, suspend, and manage plan

### Deliverables

- super-admin dashboard
- accounts table
- subscription management screens
- operational reporting

### Exit Criteria

- platform operators can fully manage supplier and retailer businesses

### Estimate

- 1 to 2 weeks

## Phase 4: Supplier Admin Module

### Goal

Adapt CRM into a supplier-facing admin portal.

### Scope

- products and product images
- inventory and warehouses
- quotes and proformas
- invoices
- customers
- users
- analytics
- storefront preview

### Tasks

- scope all CRM resources to supplier account
- adapt labels and flows from reporting to tires
- add supplier users and permission templates
- add storefront preview mode
- add notifications for new procurement requests
- verify warehouse and shipping behavior

### Deliverables

- supplier admin portal
- supplier-scoped catalog and inventory
- supplier preview storefront mode

### Exit Criteria

- one supplier can fully run their operations from CRM without legacy backend

### Estimate

- 2 to 4 weeks

## Phase 5: Retailer Admin and Supplier Procurement

### Goal

Build the retailer-side admin behavior that sits behind the storefront.

### Scope

- supplier discovery
- supplier connection requests
- connected supplier management
- procurement search
- procurement cart
- procurement request submission
- supplier-side approval path

### Tasks

- add supplier relationship model
- add request, approve, reject flow
- add explore suppliers screen in admin
- add my suppliers screen in admin
- build procurement search by size and supplier
- build procurement cart
- link retailer invoice to "Procure Tires"
- generate supplier-side quote or proforma from procurement request
- notify supplier by email and in-app

### Deliverables

- retailer admin for supplier connections
- retailer procurement workflow
- supplier-side procurement intake

### Exit Criteria

- a retailer can source products from approved suppliers entirely inside CRM

### Estimate

- 3 to 4 weeks

## Phase 6: Storefront Build

### Goal

Create the new retail-facing Clockwork storefront as a separate repo and connect it to CRM APIs.

### Scope

- authentication
- vehicle search
- size search
- catalog listing
- product detail
- cart
- checkout
- account area
- order history
- supplier preview mode

### Tasks

- create `clockwork-tyres-storefront`
- port only the useful Angular UI patterns from old Clockwork
- remove public supplier discovery from storefront
- update branding and tire-focused UI
- connect login and account flows to CRM APIs
- connect search and catalog to new tire APIs
- connect cart and checkout to retail order flow
- create supplier preview mode with no cart or checkout

### Deliverables

- production storefront repo
- retailer sales flow
- supplier preview mode

### Exit Criteria

- retailer can use storefront to create customer sales
- supplier can preview their own catalog safely

### Estimate

- 3 to 5 weeks

## Phase 7: Integration and Data Migration

### Goal

Move from reference systems into the new platform safely.

### Scope

- account migration
- user migration
- supplier-retailer relationship migration
- plan migration
- catalog and inventory migration
- order history migration if approved

### Tasks

- map legacy dealers to retailer accounts
- map legacy vendors to supplier accounts
- map `dealer_vendors` to new supplier relationships
- map legacy plans to new subscriptions
- import products and inventory according to final tire schema
- migrate selected orders and invoices if required
- run reconciliation and data validation scripts

### Deliverables

- migration scripts
- migration runbook
- validation reports

### Exit Criteria

- pilot accounts can work only from new platform without depending on legacy backend

### Estimate

- 2 to 4 weeks

## Phase 8: QA, Pilot, and Launch

### Goal

Harden the platform and release gradually.

### Scope

- QA
- UAT
- pilot rollout
- training
- launch

### Test Coverage Required

- self-registration
- invite-based onboarding
- supplier approval
- supplier preview mode
- retail checkout
- invoice creation
- procure tires flow
- supplier quote receipt
- quote to invoice conversion
- stock movement and updates
- subscription gating
- role and permission visibility

### Tasks

- add contract tests for storefront APIs
- add end-to-end flows for key journeys
- run pilot with one supplier and one retailer
- fix defects
- train operations team
- enable phased rollout

### Deliverables

- QA report
- UAT signoff
- pilot signoff
- launch checklist

### Exit Criteria

- agreed pilot accounts operate successfully in production

### Estimate

- 2 to 3 weeks

## 9. Suggested Timeline Summary

| Phase | Name | Estimate |
| --- | --- | --- |
| 0 | Discovery and scope lock | 1 to 2 weeks |
| 1 | Platform foundation in CRM | 2 to 3 weeks |
| 2 | Tire catalog and inventory model | 2 to 3 weeks |
| 3 | Super admin module | 1 to 2 weeks |
| 4 | Supplier admin module | 2 to 4 weeks |
| 5 | Retailer admin and procurement | 3 to 4 weeks |
| 6 | Storefront build | 3 to 5 weeks |
| 7 | Integration and migration | 2 to 4 weeks |
| 8 | QA, pilot, and launch | 2 to 3 weeks |

Realistic program range:

- 18 to 30 weeks depending on team size, scope lock, and migration depth

## 10. Recommended Team Structure

- Product and business analyst
- CRM backend lead
- Storefront frontend lead
- Full-stack integration developer
- QA lead
- DevOps and release owner

## 11. Open Questions For George

These must be answered before Phase 1 starts:

- Is each business one account that can be retailer, supplier, or both?
- Is supplier the same as wholesaler in all cases?
- What exactly is included in free versus paid plans?
- Is the three-supplier free limit final?
- Is the storefront public or counter-use only?
- Should end customers see supplier stock at all?
- Should procurement always start from an invoice, or can it be standalone?
- Should supplier approval reserve stock immediately?
- Are payments online, invoice-based, in-store, or mixed?
- What is the final tire import file and field list?
- How much history must be migrated?
- Do multi-branch retailers need multiple warehouses and staff users from day one?

## 12. Immediate Next Steps

Recommended next actions:

1. approve repo naming strategy
2. create the separate storefront repo
3. convert this roadmap into epics and tickets
4. finalize Phase 0 decisions with George
5. start Phase 1 only after tire schema and subscription rules are approved

## 13. Final Recommendation

Yes, create a new frontend repo.

Recommended setup:

- Backend/platform repo: `clockwork-tyres-platform`
- Frontend/storefront repo: `clockwork-tyres-storefront`

If you prefer not to rename today, keep:

- `clockwork-tyres` for platform/backend

and create:

- `clockwork-tyres-storefront` for frontend

That is the cleanest structure for long-term delivery.
