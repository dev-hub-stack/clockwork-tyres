# Clockwork Tyres Architecture Decisions

Date: March 29, 2026

Source: George answers to product and workflow questions

## Purpose

This document turns George's latest answers into platform decisions we can build against now.

These decisions supersede earlier assumptions where they conflict.

## Confirmed Decisions

### 1. Business Account Model

- one business account can be `retailer`
- one business account can be `supplier`
- one business account can be `both`
- `both` means wholesale is enabled for the same business account

### 2. Storefront Catalog Visibility

- retail storefront shows:
  - the retailer's own products
  - products from the retailer's approved suppliers
- supplier identity is hidden on the storefront
- listing priority is:
  - own stock first
  - supplier stock after
- stock labels on storefront:
  - `in stock` for own products
  - `available` for supplier-backed products
- quantity should only be shown when stock is `4 pcs or less`
- there may be `5 to 10 suppliers` for the same product

### 3. Supplier View Store Mode

- supplier `View Store` uses the same storefront frontend
- it is a read-only catalog mode
- search by vehicle, search by size, and filters remain available
- add to cart and checkout are disabled

### 4. Subscription Rules

#### Retailer

- basic retailer plan:
  - cannot add own products
  - cannot add own inventory
  - cannot add more than `3` suppliers
- premium retailer plan:
  - can add own products
  - can add own inventory
  - can add more than `3` suppliers

#### Supplier / Wholesaler

- basic wholesaler plan:
  - can add own products
  - can add own inventory
  - has no access to reports

#### Reports Add-On

- reports are subscription-gated separately
- reports pricing is tier based
- examples provided:
  - `250 customers = AED 50/month`
  - `500 customers = AED 100/month`

### 5. Catalog Direction

- tire schema replaces wheel schema for launch
- wheel schema may return later as a future category
- tire data sheet is expected on March 30, 2026

### 6. Procurement Entry Points

- procurement can start from a retail invoice
- procurement can also be created manually
- retailers can place standalone procurement orders

### 7. Procurement Approval Outcome

- supplier approves quote
- quote is converted to invoice
- stock is reserved or deducted after approval

### 8. Payments

- no online transaction capture for launch
- no retail payment capture
- no wholesale payment capture
- retail payments are in-store for now
- platform facilitates trade and subscriptions, not transaction settlement

### 9. Super Admin Scope

- super admin manages:
  - accounts
  - subscriptions
  - platform-wide overview and analytics
- super admin does not:
  - create supplier products
  - edit supplier products
  - manage supplier inventory directly

## Architecture Implications

### Account and Capability Model

Recommended account structure:

- `accounts`
- `account_users`
- `account_capabilities`
- `account_subscriptions`
- `account_supplier_links`

Recommended capability flags:

- `retail_enabled`
- `wholesale_enabled`
- `can_add_own_products`
- `can_add_own_inventory`
- `can_view_reports`
- `max_suppliers`

This is better than hard-coding role logic into user permissions.

### Catalog Model

The platform should move to a category-capable product model even if only tires launch first.

Recommended direction:

- `catalog_categories`
- `products`
- `product_variants`
- `product_images`
- `inventory_items`
- `warehouse_inventory`

Launch category:

- `tyres`

Future category:

- `wheels`

### Retail Storefront Search and Listing

The storefront query layer must support:

- merging the retailer's own stock with approved-supplier stock
- hidden supplier identity
- own-stock priority
- low-stock quantity display logic
- multiple supplier sources for the same tyre

This means the storefront should not read directly from raw supplier inventory tables.

It should read from a catalog aggregation layer or search view.

### Storefront Modes

We now need at least two storefront modes:

- `retail-store`
  - cart enabled
  - checkout enabled
- `supplier-preview`
  - cart disabled
  - checkout disabled

This should be a mode or capability toggle, not a separate frontend codebase.

### Procurement Workflow

Recommended workflow state model:

- `draft`
- `submitted`
- `supplier_review`
- `quoted`
- `approved`
- `invoiced`
- `stock_reserved`
- `fulfilled`
- `cancelled`

Because approval creates invoice and reserves stock, quote approval is a major transition point and should be auditable.

### Subscription and Entitlements

Subscriptions should be modeled as entitlements, not just labels.

Examples:

- retailer basic:
  - `max_suppliers = 3`
  - `can_add_own_products = false`
  - `can_add_own_inventory = false`
- retailer premium:
  - `max_suppliers = unlimited`
  - `can_add_own_products = true`
  - `can_add_own_inventory = true`
- wholesaler basic:
  - `can_add_own_products = true`
  - `can_add_own_inventory = true`
  - `can_view_reports = false`

Reports should be treated as a metered or tiered add-on.

### Payments and Checkout

Checkout at launch should create:

- order intent
- invoice request
- fulfillment request

It should not create:

- card authorization
- payment capture
- gateway settlement

This keeps launch scope aligned with George's decision.

### Super Admin Boundaries

Super admin should remain a governance layer.

Recommended super admin responsibilities:

- account creation and activation
- wholesale enablement
- plan and subscription management
- analytics and health overview
- audit visibility

Not recommended:

- direct supplier catalog maintenance
- direct supplier inventory maintenance

## What We Can Build Now

These answers are enough to start:

- account tenancy model
- capability and entitlement system
- supplier-retailer connection model
- storefront mode switch
- hidden-supplier catalog aggregation design
- procurement state machine
- super admin scope
- offline checkout and invoice-first retail flow
- tire-first schema planning

## Remaining Open Questions

The remaining questions are now narrower and mostly implementation-detail questions:

1. For `both` accounts, is inventory one shared pool or separated by retail vs wholesale channel?
2. For `both` accounts, do they need separate retail price and wholesale supply price?
3. When multiple suppliers have the same tyre, should storefront show one merged entry or multiple entries?
4. If storefront hides supplier identity, how should supplier allocation happen behind the scenes?
5. What releases reserved stock if an approved quote or invoice is later cancelled or expires?
6. For reports billing, what exactly counts as a `customer` in the tier?
7. For `both` accounts, is there one combined subscription or separate retailer and supplier subscriptions?
8. Final tire import sheet and field mapping still pending.

## Recommended Next Move

Treat the account model, subscription engine, procurement workflow, storefront mode logic, and tire schema preparation as active design work now.

Wait only for the final tire import sheet and the smaller follow-up clarifications before locking final product aggregation and inventory behavior.
