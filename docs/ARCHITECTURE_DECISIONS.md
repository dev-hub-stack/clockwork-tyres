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
- `both` accounts use one shared stock pool

### 2. Storefront Catalog Visibility

- retail storefront shows:
  - the retailer's own products
  - products from the retailer's approved suppliers
- supplier identity is hidden on the storefront
- if the same tyre exists from own stock and multiple suppliers, storefront shows one merged product entry
- listing priority is:
  - own stock first
  - supplier stock after
- stock labels on storefront:
  - `in stock` for own products
  - `available` for supplier-backed products
- quantity should only be shown when stock is `4 pcs or less`
- there may be `5 to 10 suppliers` for the same product
- supplier allocation is not automatic on the storefront
- supplier selection is manual in retailer admin

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
- reports add-on is configured by super admin
- reports tiering is based on total wholesale customers registered and connected to the supplier
- examples provided:
  - `250 customers = AED 50/month`
  - `500 customers = AED 100/month`

#### Combined Subscription Model

- an account that is both retailer and supplier uses one combined subscription
- reports remain a separate add-on on top of the main subscription

### 5. Catalog Direction

- tire schema replaces wheel schema for launch
- wheel schema may return later as a future category
- tire data sheet is expected on March 30, 2026

### 6. Pricing Model

- tire data will include four supplier pricing levels:
  - `retail`
  - `wholesale_level_1`
  - `wholesale_level_2`
  - `wholesale_level_3`
- supplier-side accounts choose which wholesale price level to offer to each customer
- retailer-side storefront pricing is not the supplier wholesale level shown directly to end customers
- retailer sells using:
  - cost plus percentage
  - or cost plus fixed amount

### 7. Procurement Entry Points

- procurement can start from a retail invoice
- procurement can also be created manually
- retailers can place standalone procurement orders
- admin procurement cart can contain products from multiple suppliers at the same time
- products must remain grouped per supplier inside the procurement cart
- one `place order` action can submit all grouped supplier orders together
- operationally, procurement still splits into separate supplier orders behind the scenes

### 8. Procurement Approval Outcome

- supplier approves quote
- quote is converted to invoice
- stock is deducted after quote approval and invoice creation
- cancellation adds stock back to the selected warehouse
- this should follow the same stock method already used in the reporting CRM

### 9. Payments

- no online transaction capture for launch
- no retail payment capture
- no wholesale payment capture
- retail payments are in-store for now
- platform facilitates trade and subscriptions, not transaction settlement

### 10. Super Admin Scope

- super admin manages:
  - accounts
  - subscriptions
  - platform-wide overview and analytics
- reports add-on setup and customization
- super admin does not:
  - create supplier products
  - edit supplier products
  - manage supplier inventory directly
- super admin does not impersonate or log in as business accounts
- super admin creates and manages supplier accounts
- super admin does not manually approve supplier accounts as a separate approval workflow

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
- `pricing_level_access`
- `reports_customer_limit`

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

### Inventory and Pricing Model

Recommended direction for `both` accounts:

- one shared stock pool
- one inventory ownership model
- multi-price support per tyre variant

Recommended launch pricing structure:

- supplier cost and offer structure stored on the supplier side
- customer-level wholesale tier assignment for supplier relationships
- retailer-side sell price derived from landed cost plus:
  - markup percentage
  - or fixed amount

### Retail Storefront Search and Listing

The storefront query layer must support:

- merging the retailer's own stock with approved-supplier stock
- hidden supplier identity
- one merged entry when the same tyre is available from multiple sources
- own-stock priority
- low-stock quantity display logic
- multiple supplier sources for the same tyre

This means the storefront should not read directly from raw supplier inventory tables.

It should read from a catalog aggregation layer or search view.

That aggregation layer should preserve source options behind the scenes so retailer admin can later choose the supplier manually.

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
- `stock_deducted`
- `fulfilled`
- `cancelled`

Because approval creates invoice and deducts stock, quote approval is a major transition point and should be auditable.

Cancellation should restore stock to the selected warehouse using the current reporting CRM method.

Recommended cart / order behavior:

- retailer admin can add items from multiple suppliers into one procurement workbench
- the workbench must keep line items grouped by supplier
- the final submit action should fan out into separate supplier procurement orders
- supplier intake, quote, approval, and invoice conversion should continue per supplier order
- this is a unified submission experience for the retailer admin, not a single merged supplier order

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

Recommended add-on measurement:

- count total wholesale customers registered and connected to the supplier

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
- shared stock design for mixed retailer-supplier accounts
- multi-level pricing design
- merged catalogue aggregation design
- manual supplier allocation design

## Remaining Open Questions

The only material product input still pending is:

1. Final tire import sheet and field mapping.

## Recommended Next Move

Treat the account model, subscription engine, procurement workflow, storefront mode logic, pricing engine, and tire schema preparation as active design work now.

Wait only for the final tire import sheet before locking final schema and import mappings.
