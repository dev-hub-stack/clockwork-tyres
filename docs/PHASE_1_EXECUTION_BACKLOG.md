# Phase 1 Execution Backlog

Date: March 29, 2026

Purpose: convert the agreed product direction into a practical near-term implementation backlog that can move forward before the final tire sheet arrives.

## Objective

Build the low-regret platform and storefront foundations that do not depend on the final tire sheet.

## Workstream 1: Platform Account Foundation

### Epic 1.1 Account Model

- define `accounts` as the business-level owner
- define supported account capabilities:
  - `retail_enabled`
  - `wholesale_enabled`
- define account states:
  - `active`
  - `suspended`
  - `pending`

### Epic 1.2 Account Memberships

- create `account_users`
- define account membership roles
- separate platform staff roles from account roles
- define super-admin override behavior

### Epic 1.3 Capability and Entitlement Layer

- define subscription-driven capabilities
- define `max_suppliers`
- define `can_add_own_products`
- define `can_add_own_inventory`
- define `can_view_reports`
- define combined subscription rules for `both` accounts
- define reports add-on based on connected wholesale customer totals

## Workstream 2: Supplier-Retailer Network

### Epic 2.1 Supplier Relationship Model

- create supplier approval relationship model
- define request lifecycle
- define approved supplier access rules
- define retailer-side supplier count logic

### Epic 2.2 Hidden Supplier Storefront Support

- define aggregated catalog rule:
  - own stock first
  - approved supplier stock after
- define one merged product entry rule for same-tyre multi-source stock
- define stock badge mapping:
  - `in stock`
  - `available`
- define low-stock quantity display rule
- define admin-only manual supplier allocation from hidden source options

## Workstream 3: Tire-First Catalog Foundation

### Epic 3.1 Tire Catalog Preparation

- list all current wheel-specific schema assumptions
- define tire-first product and variant model
- define future category extension path for wheels

### Epic 3.2 Inventory Foundation

- define warehouse inventory ownership model
- define shared stock pool behavior for `both` accounts
- define stock deduction and restoration rules
- define approval-to-invoice-to-deduction transition point

### Epic 3.3 Pricing Foundation

- define four price levels in launch tire schema
- define customer-level wholesale tier assignment
- define retailer retail price as cost plus percentage or fixed amount

## Workstream 4: Procurement Workflow Foundation

### Epic 4.1 Procurement Entry Points

- support invoice-driven procurement
- support manual procurement
- support standalone retailer procurement orders

### Epic 4.2 Procurement State Machine

- define base states:
  - `draft`
  - `submitted`
  - `supplier_review`
  - `quoted`
  - `approved`
  - `invoiced`
  - `stock_deducted`
  - `fulfilled`
  - `cancelled`

### Epic 4.3 Approval Transition

- quote approval creates invoice
- quote approval deducts stock
- cancellation returns stock to the selected warehouse using current reporting CRM behavior
- log this transition as an auditable event

## Workstream 5: Storefront Foundation

### Epic 5.1 Shared Frontend Modes

- define `retail-store` mode
- define `supplier-preview` mode
- hide cart and checkout in supplier preview
- keep search and filters enabled in supplier preview

### Epic 5.2 Shared Data Layer

- replace page-local mock data with shared typed data models
- define catalog item contract
- define product detail contract
- define cart item contract
- define account contract

### Epic 5.3 Storefront Behavior Rules

- support hidden supplier-backed availability
- support low-stock quantity display
- support own-stock priority

## Workstream 6: Super Admin Scope

### Epic 6.1 Super Admin Boundaries

- manage accounts
- manage subscriptions
- manage activation and wholesale enablement
- view platform analytics
- do not create or edit supplier products
- do not manage supplier inventory directly

## Workstream 7: Remaining Inputs To Plug In Later

These items should not block foundation work, but they are still pending:

- final tire import sheet

## Immediate Deliverables We Can Complete Now

1. account and entitlement design
2. supplier relationship model
3. procurement state machine draft
4. storefront mode system
5. storefront shared data contracts
6. tire-schema hotspot audit
7. CRM tenancy insertion-point audit
8. pricing-tier and merged-catalog design

## Definition Of Progress This Week

This week counts as productive if we leave with:

- backend tenancy and entitlement plan documented
- storefront shared data layer in progress
- storefront mode system in progress
- tire schema hotspot audit completed
- only the tire import sheet remaining as a hard dependency
