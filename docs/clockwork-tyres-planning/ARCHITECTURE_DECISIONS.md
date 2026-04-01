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
- retailer-only accounts can stay on the free limited plan
- supplier-only accounts can stay on the free limited plan
- combined `both` accounts require a paid subscription
- `both` means wholesale is enabled for the same business account
- `both` accounts use one shared stock pool
- supplier and wholesaler mean the same business role in phase 1
- launch is fresh-account first by default
- there is no bulk legacy history migration planned for launch
- case-by-case imports can still be handled if needed later
- multi-branch support is required from day one through:
  - multiple users
  - multiple warehouses

### 2. Storefront Catalog Visibility

- storefront is a counter-only experience for phase 1
- live catalog and ordering should be used after login, not as a public retail browse flow
- retail storefront shows:
  - the retailer's own products
  - products from the retailer's approved suppliers
- supplier identity is hidden on the storefront
- if the same tyre exists from own stock and multiple suppliers, storefront shows one merged product entry
- merged product identity must not depend on supplier SKU
- George confirmed suppliers may use different SKUs for the same tyre
- grouping should be based on:
  - `brand`
  - `model`
  - `size`
  - `year`
- listing priority is:
  - own stock first
  - supplier stock after
- stock labels on storefront:
  - `in stock` for own products
  - `available` for supplier-backed products
- quantity should only be shown when stock is `4 pcs or less`
- end customers do not see supplier count unless the visible low-stock quantity is `4 pcs or less`
- there may be `5 to 10 suppliers` for the same product
- supplier allocation is not automatic on the storefront
- supplier selection is manual in retailer admin

### 3. Supplier View Store Mode

- supplier `View Store` uses the same storefront frontend
- it is a read-only catalog mode
- search by vehicle, search by size, and filters remain available
- add to cart and checkout are disabled

### 4. Subscription Rules

Phase 1 commercial plans are now clearer.

#### Retailer

- retailer starter plan:
  - access to `3` suppliers
  - `24/7` live inventory and ordering
  - unlimited orders
  - cannot add own products
  - cannot add own inventory
  - cannot add more than `3` suppliers
- `3 suppliers` is the final free limit
- retailer plus plan:
  - everything in starter
  - add unlimited suppliers
  - change portal logo to company logo
  - manage and showcase on-hand inventory
  - store analytics
  - can add own products
  - can add own inventory
  - can add more than `3` suppliers
- retailer enterprise is sales-led / custom pricing
- retailer enterprise mockup currently highlights:
  - businesses that sell both wholesale and retail
  - customer analytics
- retailer pricing mockup currently maps to:
  - `starter = free`
  - `plus = AED 199 / month`
  - `enterprise = custom pricing`

#### Supplier / Wholesaler

- supplier starter plan:
  - `24/7` live inventory and order portal
  - unlimited orders
  - inventory and product management admin
  - can add own products
  - can add own inventory
  - has no access to reports
- supplier premium plan:
  - for businesses that sell both wholesale and retail
  - unlocks retail sales portal
  - unlocks procurement module
  - unlocks store analytics
- supplier enterprise is sales-led / custom pricing
- supplier enterprise mockup currently highlights:
  - businesses that sell both wholesale and retail
  - customer analytics
- supplier pricing mockup currently maps to:
  - `starter = free`
  - `premium = AED 199 / month`
  - `enterprise = custom pricing`

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
- self-serve phase 1 should treat `both` accounts as paid-only
- enterprise remains a manual / super-admin / sales path, not a self-serve checkout path yet
- phase 1 backend may continue to store internal plan codes as:
  - `basic` for free starter
  - `premium` for the paid self-serve plan
    - retailer-facing label: `Plus`
    - wholesaler-facing label: `Premium`
  - enterprise handled manually until a dedicated code is needed

### 5. Catalog Direction

- tire schema replaces wheel schema for launch
- wheel schema may return later as a future category
- tyre sample sheet was received on March 30, 2026
- launch sheet currently contains these columns:
  - `SKU`
  - `Brand`
  - `Model`
  - `width`
  - `height`
  - `rim_size`
  - `full_size`
  - `load_index`
  - `speed_rating`
  - `DOT`
  - `Country`
  - `Type`
  - `Runflat`
  - `RFID`
  - `sidewall`
  - `warranty`
  - `Retail_price`
  - `wholesale_price_lvl1`
  - `wholesale_price_lvl2`
  - `wholesale_price_lvl3`
  - `brand_image`
  - `product_image_1`
  - `product_image_2`
  - `product_image_3`
- internal field names should normalize these headers into snake_case
- sample row also exposes validation follow-ups around:
  - `height` vs `full_size`
  - `DOT` meaning
  - image reference format

### 6. Pricing Model

- tire data will include four supplier pricing levels:
  - `retail`
  - `wholesale_lvl1`
  - `wholesale_lvl2`
  - `wholesale_lvl3`
- source sheet pricing headers are:
  - `Retail_price`
  - `wholesale_price_lvl1`
  - `wholesale_price_lvl2`
  - `wholesale_price_lvl3`
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
- grouping by `brand + model + size + year`, not `sku`
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
  - only after authenticated business login
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
- combined both account:
  - `requires_paid_plan = true`
  - `retail_enabled = true`
  - `wholesale_enabled = true`
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

The tyre sheet is now shared.

Only small launch clarifications remain:

1. Which field wins if `height` and `full_size` conflict?
2. Does `DOT` store year only or full DOT code?
3. Are image fields file names, server paths, or full URLs?

## Recommended Next Move

Treat the account model, subscription engine, procurement workflow, storefront mode logic, pricing engine, tyre importer, and merged-catalogue grouping logic as active build work now.

Do not wait for more major product input before starting tyre import staging and aggregation design.
