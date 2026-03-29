# Backend Phase 1 Implementation Checklist

Date: March 29, 2026

Purpose: turn the agreed Phase 1 direction into an execution-order checklist for the backend team.

Scope of this document:

- tenancy foundations
- migration order
- model and auth boundaries
- admin and panel scoping
- wheel-to-tire hotspot isolation
- import and API refactor order
- low-risk work we can start immediately

This is a Phase 1 engineering checklist, not the full delivery roadmap.

## 1. Phase 1 Goal

Phase 1 exists to convert the CRM base into a tenant-aware platform foundation without committing early to risky catalog or procurement rewrites.

Success at the end of Phase 1 means:

- platform has a clear `account` boundary
- admin data can be scoped by account
- platform staff identity is separated from business account identity
- catalog, import, and API hotspots are isolated for later tire-first refactor
- low-regret migration and compatibility work is already underway
- shared stock, merged catalogue aggregation, and multi-level pricing rules are reflected in the backend design

## 2. Source System Insertion Points

These are the main source-system areas that Phase 1 must account for when porting `reporting-crm` into `clockwork-tyres`.

### Tenancy and Identity Hotspots

- `reporting-crm/config/auth.php`
- `reporting-crm/app/Models/User.php`
- `reporting-crm/app/Modules/Customers/Models/Customer.php`
- `reporting-crm/app/Http/Controllers/Wholesale/AuthController.php`
- `reporting-crm/app/Http/Controllers/Wholesale/DealerController.php`
- `reporting-crm/app/Providers/Filament/AdminPanelProvider.php`
- `reporting-crm/database/seeders/RolesAndPermissionsSeeder.php`

### Catalog and Wheel-to-Tire Hotspots

- `reporting-crm/app/Modules/Products/Models/Product.php`
- `reporting-crm/app/Modules/Products/Models/ProductVariant.php`
- `reporting-crm/database/migrations/2025_10_22_000004_create_product_variants_table.php`
- `reporting-crm/app/Filament/Pages/ProductsGrid.php`
- `reporting-crm/app/Http/Controllers/ProductVariantGridController.php`
- `reporting-crm/app/Http/Controllers/Wholesale/ProductController.php`
- `reporting-crm/app/Modules/Wholesale/Helpers/WholesaleProductTransformer.php`

### Admin Resource Scoping Hotspots

- `reporting-crm/app/Filament/Resources/Users/UserResource.php`
- `reporting-crm/app/Filament/Resources/CustomerResource.php`
- `reporting-crm/app/Filament/Resources/QuoteResource.php`
- `reporting-crm/app/Filament/Resources/InvoiceResource.php`
- `reporting-crm/app/Filament/Resources/WarehouseResource.php`
- `reporting-crm/app/Filament/Pages/Dashboard.php`
- `reporting-crm/app/Filament/Pages/ProductsGrid.php`
- `reporting-crm/app/Filament/Pages/InventoryGrid.php`

### Import and Legacy Mapping Hotspots

- `reporting-crm/app/Http/Controllers/ProductVariantGridController.php`
- `reporting-crm/app/Http/Controllers/Admin/InventoryController.php`
- `reporting-crm/routes/api-wholesale.php`
- `tunerstop-vendor/app/Models/Dealer.php`
- `tunerstop-vendor/app/Models/DealerVendor.php`
- `tunerstop-vendor/database/seeders/PlansSeeder.php`
- `tunerstop-vendor/app/Http/Middleware/CheckDealerPlanLimits.php`

## 3. Working Rules For Phase 1

- Create additive migrations first. Avoid destructive schema changes in the first pass.
- Separate tenancy and identity concerns before changing catalog semantics.
- Keep `users` and `customers` from being overloaded with new meanings.
- Do not delete wheel-era fields until tire schema and import mappings are approved.
- Do not rebuild procurement in Phase 1.
- Do not move business logic into Filament resources.
- Prefer compatibility adapters over breaking all API consumers at once.

## 4. Migration Order

### 4.1 Tenancy Kernel Migrations

- [ ] Create `accounts`
- [ ] Create `account_memberships`
- [ ] Create `account_capabilities`
- [ ] Create `account_subscriptions`
- [ ] Create `account_supplier_links`
- [ ] Create `account_settings` or `account_brandings`
- [ ] Add audit fields and status fields to new tenancy tables

Recommended first-pass account fields:

- `id`
- `name`
- `account_type`
- `status`
- `retail_enabled`
- `wholesale_enabled`
- `created_by`
- timestamps

Implementation note:

- for `both` accounts, keep one shared stock pool
- do not split inventory ownership by retail and wholesale channel in Phase 1

Recommended first-pass membership fields:

- `account_id`
- `user_id` or `account_user_id`
- `role`
- `status`
- timestamps

Recommended supplier-link fields:

- `supplier_account_id`
- `retailer_account_id`
- `status`
- `requested_by`
- `approved_by`
- `approved_at`
- timestamps

### 4.2 Existing Table Ownership Migrations

Do these only after the tenancy tables exist.

- [ ] Add `account_id` to warehouses
- [ ] Add `account_id` to products
- [ ] Add `account_id` to product_variants if needed separately
- [ ] Add `account_id` to inventory tables
- [ ] Add `account_id` to orders
- [ ] Add `account_id` to quotes and invoices through the orders model
- [ ] Add `account_id` to CRM-facing customer/business records where appropriate

Implementation rule:

- add columns nullable first
- backfill with scripts
- enforce not-null later after verification

### 4.3 Billing and Entitlement Migrations

- [ ] Create `plans`
- [ ] Create `plan_features`
- [ ] Create `account_plan_subscriptions`
- [ ] Create `usage_counters` if needed for limits such as supplier count and reports tiers
- [ ] Create customer-tier tracking for total wholesale customers connected to each supplier

### 4.4 Do Not Do Yet

- [ ] Do not delete wheel-era columns
- [ ] Do not merge or rewrite all order states
- [ ] Do not migrate historical data blindly before mapping is approved
- [ ] Do not tie catalog rewrite to billing migration

## 5. Model and Domain Layer Checklist

### 5.1 New Core Models

- [ ] `Account`
- [ ] `AccountMembership`
- [ ] `AccountCapability`
- [ ] `AccountSubscription`
- [ ] `AccountSupplierLink`

### 5.2 Identity Boundaries

Recommended boundary:

- `User` = platform staff and internal back-office identity
- `AccountUser` or membership-backed user = supplier and retailer staff identity
- `Customer` = downstream buyer or contact record only

Checklist:

- [ ] Keep `User` as the `web` guard identity for platform/admin users
- [ ] Stop treating `Customer` as the long-term admin/staff identity model
- [ ] Define whether supplier/retailer staff are separate records or memberships over `users`
- [ ] Document the temporary bridge if `customers` remain portal-authenticated during transition

### 5.3 Service Layer Preparation

- [ ] Add account resolution service
- [ ] Add capability and entitlement service
- [ ] Add supplier-link lifecycle service
- [ ] Add customer pricing tier assignment service
- [ ] Add merged product aggregation service
- [ ] Add account-scoped query helpers
- [ ] Add backfill scripts or jobs for ownership assignment

## 6. Auth and Panel Scoping Checklist

### 6.1 Guard and Auth Strategy

- [ ] Keep the platform admin guard separate from storefront auth
- [ ] Decide whether storefront staff auth stays customer-based temporarily or moves to account-user auth
- [ ] Define a single place for account resolution from the authenticated actor
- [ ] Define super-admin bypass explicitly

### 6.2 Filament and Panel Scoping

Recommended Phase 1 choice:

- stay on one Filament panel initially
- scope navigation, resources, and queries by account and role
- postpone multi-panel split unless it becomes necessary

Checklist:

- [ ] Add account-aware navigation visibility
- [ ] Add account-aware base query scopes for resources
- [ ] Add policies for super-admin, supplier-admin, retailer-admin access
- [ ] Add account context switching only for super-admin if needed
- [ ] Add audit trail for cross-account admin actions

### 6.3 Resource Scoping Order

Scope these resources in this order:

1. [ ] `UserResource`
2. [ ] `CustomerResource`
3. [ ] `WarehouseResource`
4. [ ] `QuoteResource`
5. [ ] `InvoiceResource`
6. [ ] `ProductsGrid`
7. [ ] `InventoryGrid`
8. [ ] dashboard and reports

Reason:

- users, customers, warehouses, and commerce resources are the first places where data leakage becomes dangerous

## 7. Catalog Refactor Order

Phase 1 should not fully convert wheels to tires yet.

Phase 1 should isolate the catalog so the tire refactor can happen safely next.

### 7.1 Catalog Isolation Steps

- [ ] Introduce a category-aware catalog boundary in docs and service layer
- [ ] Identify wheel-specific assumptions in product, variant, grid, transformer, and search code
- [ ] Freeze a tire-first target field list from George's import sheet before destructive schema changes
- [ ] Define a future-compatible path for multiple categories even if only tires launch first

### 7.2 Wheel-to-Tire Hotspot Checklist

Wheel-era fields currently driving behavior include:

- `rim_width`
- `rim_diameter`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `backspacing`
- `max_wheel_load`

Checklist:

- [ ] Mark these as legacy wheel hotspots in migration notes
- [ ] Do not reuse them to model tires
- [ ] Create a target tire variant field map separately
- [ ] Decide whether tire data lands in new fields on `product_variants` or a new tire-specific table

### 7.3 Safe Catalog Work We Can Start Now

- [ ] product and variant hotspot audit
- [ ] search/filter dependency inventory
- [ ] transformer dependency inventory
- [ ] grid column inventory
- [ ] import column inventory
- [ ] price-level model draft for `retail`, `wholesale_level_1`, `wholesale_level_2`, `wholesale_level_3`
- [ ] merged listing rules for own stock plus supplier-backed stock
- [ ] source-option persistence model so admin can choose supplier manually

## 8. Import Refactor Order

### 8.1 First-Pass Import Work

- [ ] decouple import flow from raw grid controllers
- [ ] define import staging records or validation DTOs
- [ ] define a tire import mapping document
- [ ] separate validation from persistence
- [ ] define backfill and reconciliation strategy

### 8.2 Import Sequence

1. [ ] document current import entry points
2. [ ] add staging schema or service contracts
3. [ ] add validation rules for tire fields
4. [ ] add validation rules for four pricing levels
5. [ ] add ownership assignment rules by account
6. [ ] add image handling and media mapping rules
7. [ ] only then replace or extend persistence layer

### 8.3 Do Not Do Yet

- [ ] do not hard-delete wheel import logic before migration signoff
- [ ] do not couple tire import directly to storefront API delivery

## 9. API Refactor Order

Phase 1 should reduce coupling before replacing every route.

### 9.1 Route Boundary Checklist

- [ ] separate platform-admin API concerns from storefront API concerns
- [ ] isolate legacy wholesale-compatible endpoints behind an adapter layer
- [ ] document which endpoints must be preserved temporarily for frontend compatibility
- [ ] define new account-aware API groups for platform work

Suggested API groups:

- platform admin
- account admin
- storefront
- integrations and webhooks

### 9.2 API Sequence

1. [ ] map current storefront dependencies
2. [ ] freeze compatibility endpoints that the old frontend currently assumes
3. [ ] define new account-aware contracts
4. [ ] define merged product response contract for own stock plus supplier-backed stock
5. [ ] preserve hidden supplier source options for admin-side selection only
6. [ ] add new routes beside old routes where possible
7. [ ] migrate consumers after contract tests exist

## 10. Admin Resource Refactor Order

### 10.1 Lowest-Risk Start Order

- [ ] query scoping traits or base classes
- [ ] policy layer
- [ ] navigation visibility rules
- [ ] account ownership backfill
- [ ] resource-by-resource filter and form cleanup

### 10.2 Resource Rules

- [ ] Every account-owned resource must resolve an account owner
- [ ] Every index query must be account-scoped by default
- [ ] Super-admin views may bypass scope but must remain auditable
- [ ] Reports should read from scoped datasets, not raw cross-account tables
- [ ] Product and inventory grids must not stay global once `account_id` exists

## 11. Low-Risk Tasks We Can Start Now

- [ ] draft the tenancy ERD
- [ ] draft migration stubs for account tables
- [ ] define account capability matrix
- [ ] define admin policy map
- [ ] produce CRM tenancy insertion-point audit
- [ ] produce wheel-to-tire hotspot audit
- [ ] produce import and transformer dependency audit
- [ ] produce API compatibility inventory from old frontend calls
- [ ] define base test fixtures for multi-account scenarios
- [ ] define backfill strategy for account ownership
- [ ] define pricing-level and customer-tier assignment rules
- [ ] define merged product aggregation and manual supplier-allocation design

## 12. Risks To Avoid In Phase 1

- [ ] rewriting the storefront and backend contract at the same time without compatibility notes
- [ ] using `customers` as the permanent identity for supplier and retailer staff
- [ ] deleting wheel logic before tire schema is approved
- [ ] letting Filament resources become the domain layer
- [ ] scoping dashboards and reports last instead of early
- [ ] mixing subscription logic into UI before entitlement tables exist

## 13. Phase 1 Definition Of Done

Phase 1 is done when:

- account tables and ownership columns exist
- auth and panel boundaries are documented and partially enforced
- core admin resources have account scoping design and implementation order
- wheel-to-tire hotspots are isolated and documented
- import and API refactor order is documented
- low-risk platform work can continue without waiting on full catalog approval

## 14. Recommended Next Document

After this checklist, the next useful backend doc should be:

- `docs/TENANCY_INSERTION_POINT_AUDIT.md`

That document should map each affected CRM file, table, and query to its Phase 1 tenancy change.
