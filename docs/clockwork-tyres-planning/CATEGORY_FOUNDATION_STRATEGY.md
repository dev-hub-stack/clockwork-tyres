# Clockwork Category Foundation Strategy

Date: March 29, 2026

Purpose: define how we should keep the new Clockwork Tyres platform ready for future wheel support without forcing the tyre launch to inherit the legacy wheel-only data model.

## Recommended Direction

Build a category-capable platform core, launch `tyres` first, and keep `wheels` as a planned extension path rather than an active launch category.

That means:

- preserve the reusable storefront structure from legacy Clockwork
- preserve the idea of `search by size` and `search by vehicle`
- do not preserve the wheel-specific product contract as the new base model
- do not preserve wheel-specific query params as the new shared search contract
- design category adapters now so wheels can be added later with less rework

This is the strongest direction because it balances:

- short-term delivery speed for tyres
- long-term support for wheels and future categories
- reuse of the current CRM wheel capability where it still helps
- clean architecture instead of another category-specific codebase

## What The Legacy Frontend Actually Tells Us

The current Clockwork frontend is not just visually wheel-oriented.

It is structurally wheel-first.

### 1. Routes Are Wheel-Led

Key route file:

- [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts)

Important current routes:

- `/wheels`
- `/serchvehicle`
- `/search-by-size`
- `/:id/:variant_id`

All of these route into wheel-specific listing or detail flows.

### 2. Frontend Product Interfaces Are Wheel Contracts

Key interface file:

- [wheels.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/interfaces/wheels.ts)

The core `ProductVariant` contract is built around wheel fields such as:

- `rim_diameter`
- `rim_width`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `weight`
- `backspacing`
- `lipsize`
- `max_wheel_load`

It also contains rear fitment aliases for staggered wheel setups:

- `rear_rim_diameter`
- `rear_rim_width`
- `rear_bolt_pattern`
- `rear_size`
- `rear_price`

That is not a generic catalog contract.

### 3. Search By Size Is Wheel-Specific Today

Key files:

- [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts)
- [form.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/form.service.ts)
- [wheels-listing.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.ts)

Current size search is built from wheel fields:

- `front_rim_diameter`
- `front_rim_width`
- `front_bolt_pattern`
- `min_offset`
- `max_offset`
- optional rear wheel dimensions and rear offsets

The form serializes these directly into URL query params and sends the user to `/search-by-size`.

This is important:

- the **interaction pattern** is reusable
- the **field set** is not reusable for tyres

### 4. Search By Vehicle Is Also Wheel-Specific Today

Key files:

- [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts)
- [helper.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/helper.service.ts)
- [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)

Current vehicle search flow:

1. frontend calls wheel-size endpoints for make, model, year, modification
2. frontend fetches fitment data from external wheel-size API
3. helper extracts wheel fitment params from the response:
   - `bolt_pattern`
   - `rim_diameter`
4. frontend routes into the wheel listing with those fitment query params

So again:

- the **vehicle-search journey** is reusable
- the **fitment extraction contract** is wheel-specific

### 5. Listing, PDP, Filters, and Add-to-Cart Are Wheel-Shaped

Key files:

- [wheels-listing.component.html](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.html)
- [wheel-single.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheel-single/wheel-single.component.ts)
- [wheels-detail.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-detail/wheels-detail.component.ts)

Current UI directly renders wheel specs and wheel customization:

- bolt pattern
- hub bore
- offset
- wheel weight
- backspacing
- lipsize
- max wheel load
- wheel finish
- warehouse selection
- drilling/customization logic

This is valuable as a visual reference, but not as the new domain contract.

## What The Current CRM Tells Us

The new CRM base also still supports wheels first.

Key backend files:

- [api-wholesale.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/routes/api-wholesale.php)
- [ProductController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/ProductController.php)
- [ProductVariantController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/ProductVariantController.php)
- [WholesaleProductTransformer.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Wholesale/Helpers/WholesaleProductTransformer.php)
- [ProductVariant.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Products/Models/ProductVariant.php)
- [2025_10_22_000004_create_product_variants_table.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/database/migrations/2025_10_22_000004_create_product_variants_table.php)
- [WheelSizeProxyController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/WheelSizeProxyController.php)

The CRM currently exposes:

- `filter-wheels`
- `search-sizes`
- `search-vehicles`
- `wheel-size/*`

Its active catalog schema still centers on:

- `rim_diameter`
- `rim_width`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `backspacing`
- `lipsize`
- `max_wheel_load`

So the CRM is useful because:

- it already proves wheels can live in the platform
- it has reusable catalog, inventory, and search architecture patterns

But it is not ready to be the shared multi-category contract as-is.

## What We Should Preserve From Legacy And CRM

These are the parts we should preserve strongly:

### Reusable Search Structure

- `search by vehicle` as a first-class entry point
- `search by size` as a first-class entry point
- route-driven listing pages
- filter sidebar pattern
- listing to PDP to cart journey

### Reusable Platform Concepts

- product
- variant
- inventory
- warehouse
- price levels
- catalog filters
- category landing pages
- more-sizes / related-options concept

### Reusable UI Patterns

- header and search access
- listing composition
- PDP composition
- price and stock presentation areas
- account/cart flow structure

## What We Should Not Preserve As Shared Base Contracts

Do not make these the new platform foundation:

- wheel-only query params such as `front_rim_diameter` and `front_bolt_pattern`
- wheel-only variant fields as the universal variant schema
- wheel-size API dependency as the universal vehicle search source
- wheel-only “fits” logic as the universal PDP compatibility model
- wheel customization logic as the default add-to-cart behavior

## Best Architecture Shape

### 1. Category-Capable Core

The core should be generic:

- `catalog_categories`
- `products`
- `product_variants`
- `product_prices`
- `product_images`
- `warehouses`
- `product_inventories`
- `search_adapters`

### 2. Category Adapters

Each category should plug into the core through an adapter approach.

For launch:

- `tyres` adapter is active

Later:

- `wheels` adapter can be added

Responsibilities of a category adapter:

- size-search field definitions
- vehicle-search mapping
- filter definitions
- PDP spec rendering schema
- compatibility logic
- import mapping
- listing aggregation rules

### 3. Feature Flags / Capability Flags

Yes, we should effectively feature-gate categories.

Not just a frontend toggle.

We should gate at:

- catalog visibility
- search entry availability
- filter schema availability
- import availability
- admin product-management flows
- PDP spec rendering

Recommended launch state:

- `tyres = enabled`
- `wheels = disabled`
- `accessories = optional/future`

### 4. Search Foundation

Keep the search foundation generic:

- `search by vehicle`
- `search by size`

But make the field mapping category-specific.

Example:

- tyre size search might use:
  - width
  - aspect ratio
  - rim size
  - load index
  - speed rating
- wheel size search might use:
  - rim diameter
  - rim width
  - bolt pattern
  - offset

So the shell stays the same.

The schema changes by category.

## Final Recommendation

We should **not** build wheels as a full live launch package right now.

We **should** build the system so adding wheels later is straightforward.

So the direction I recommend is:

- build a complete category-capable package
- launch only tyres
- keep wheels behind category capability flags
- preserve legacy Clockwork structure and UX patterns
- do not preserve wheel-only data contracts as the new shared model

In simple terms:

- preserve the storefront skeleton
- replace the product/search engine underneath
- design extension points for wheels now
- activate wheels later when the category is ready

## Practical Next Step

The next best engineering step is to define two things explicitly:

1. backend multi-category entity structure
2. frontend category adapter structure for Angular

Those two decisions will let us keep the foundation strong now without overbuilding wheel behavior before launch.
