# Legacy Wheel Foundation Research

Date: March 29, 2026

Purpose: document how the current Clockwork legacy frontend and the current CRM/backend implement wheels, search by size, and search by vehicle, so the new tyre-first platform can keep the right extension points for future wheel support.

## 1. Executive Summary

The current Clockwork stack is not a generic storefront with a wheel skin.

It is structurally a wheel-fitment system.

The main wheel assumptions are:

- product variants are defined by wheel specs such as `rim_diameter`, `rim_width`, `bolt_pattern`, `offset`, and `hub_bore`
- vehicle search depends on wheel-size.com fitment data
- size search depends on wheel dimensions plus front/rear stagger support
- list, detail, filters, brand pages, and fitment checks all expect wheel-specific fields

This means we should not copy the current product/search data model directly into the new tyre launch.

But it also means we should preserve a strong category extension seam now, because the current CRM already contains a real wheel foundation that can be reintroduced later.

Recommended direction:

- build a category-capable search and catalog architecture now
- launch with `tyres` enabled
- keep `wheels` as a disabled future category
- preserve the wheel search/provider seam and fitment-provider seam instead of deleting them conceptually

## 2. Legacy Frontend: How Wheels Are Set Up

### Main Routes

The legacy Angular app has wheel-first routes:

- `/wheels`
- `/search-by-size`
- `/serchvehicle`
- `/product/:id/:variant_id`

Key route references:

- [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts)

### Main Listing and Detail Components

The core wheel browsing experience is implemented through:

- [wheels-listing.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.ts)
- [wheels-detail.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-detail/wheels-detail.component.ts)

The list and detail contracts are wheel-shaped:

- [wheels.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/interfaces/wheels.ts)
- [ProductDetailResponse.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/interfaces/ProductDetailResponse.ts)

Important frontend wheel fields used directly in the UI:

- `rim_diameter`
- `rim_width`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `backspacing`
- `lipsize`
- `max_wheel_load`
- `rear_rim_diameter`
- `rear_rim_width`
- `rear_bolt_pattern`
- `rear_offset`

This is not a generic attribute renderer. The frontend data contracts expect these fields to exist explicitly.

## 3. Legacy Frontend: Search By Size

### Entry Component

Search by size is started from:

- [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts)

### Form Structure

The search form itself is wheel-specific:

- [form.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/form.service.ts)

It uses:

- `front_rim_diameter`
- `front_rim_width`
- `front_bolt_pattern`
- `min_offset`
- `max_offset`
- optional staggered rear fields:
  - `rear_rim_diameter`
  - `rear_rim_width`
  - `rear_min_offset`
  - `rear_max_offset`

### Search Flow

The frontend first loads wheel dropdown values from:

- `GET /api/search-form-params`

Then on submit it builds a query string and redirects to:

- `/search-by-size?...`

Important behavior:

- offset is serialized as `XtoY`
- rear offset is also serialized as `XtoY`
- the listing page reads these query params directly

API entry references:

- [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)

### Listing Resolution

The listing page reads query params in:

- [wheels-listing.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.ts)

If `search_by_size` is present, it calls:

- `getProductsByCustomSize()`

That currently resolves to:

- `GET /api/products?...`

The old dedicated wheel endpoint still exists in service history:

- `GET /api/filter-wheels?...`

So the modern CRM path already collapsed size-search results onto the main `products` endpoint.

### Fitment Check Inside PDP / Modal Flow

When a user views detail from size-search results, the frontend calls:

- `POST /api/search-sizes`

This is used to fetch matching size variants and fitment-related options for the selected wheel/product combination.

## 4. Legacy Frontend: Search By Vehicle

### Entry Component

Vehicle search starts from:

- [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts)

### External Dependency

The legacy frontend talks directly to wheel-size.com from the browser using:

- `environment.wheelApiPath = https://api.wheel-size.com/v2/`
- `environment.wheel_user_key`

References:

- [environment.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/environments/environment.ts)
- [environment.prod.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/environments/environment.prod.ts)

### Vehicle Search Sequence

The frontend calls wheel-size.com directly for:

- makes
- models
- years
- modifications
- `search/by_model`

API method references:

- [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)

### Mapping Vehicle Fitment Into Catalog Search

The frontend maps vehicle data into wheel search params through:

- [helper.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/helper.service.ts)

Current mapping is minimal and wheel-specific:

- `bolt_pattern`
- `rim_diameter`

It also stores extra fitment/browser state in local storage:

- `bodyImage`
- `hub_bore`

### Redirect Behavior

After vehicle fitment is resolved, the frontend redirects to:

- `/wheels?...searchByVehicle=true`

The listing page then reads the query params and loads catalog results.

## 5. CRM/Backend: How Wheels Are Set Up Today

### Core Wheel Schema

The CRM remains wheel-first at the product and variant layer.

Main schema references:

- [create_products_table.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/database/migrations/2025_10_22_000003_create_products_table.php)
- [create_product_variants_table.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/database/migrations/2025_10_22_000004_create_product_variants_table.php)

Main model references:

- [Product.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Products/Models/Product.php)
- [ProductVariant.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Products/Models/ProductVariant.php)

Key wheel fields in the live backend:

- `rim_diameter`
- `rim_width`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `backspacing`
- `lipsize`
- `max_wheel_load`
- `finish_id`

### Wheel-Centric API Surface

Main wholesale/frontend routes:

- [api-wholesale.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/routes/api-wholesale.php)

Relevant wheel routes:

- `GET /api/products`
- `GET /api/filters`
- `GET /api/filter-wheels`
- `POST /api/search-sizes`
- `GET /api/search-form-params`
- `POST /api/search-vehicles`
- `GET /api/product/{slug}/{sku}`
- `GET /api/product-more-sizes/{id}/{vid}/{t}`
- `GET /api/brand-product-more-sizes/{id}/{type}`
- `GET /api/wheel-size/*`

### Wheel Search Controllers

Main controller:

- [ProductController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/ProductController.php)

Important behaviors:

- `filterWheels()` is just an alias to `index()`
- `searchSizeParams()` returns distinct wheel values from live variants:
  - `diameter`
  - `width`
  - `bolt_pattern`
- `searchSizes()` filters variants by:
  - `rim_diameter`
  - `rim_width`
  - `bolt_pattern`
  - optional `brand_id`, `model_id`, `product_id`
- staggered fitment is supported through rear wheel pairing on the same product via:
  - `rear_rim_diameter`
  - `rear_rim_width`
  - `rear_offset`
- `searchVehicles()` filters only by:
  - `bolt_pattern`
  - optional `rim_diameter`

Important conclusion:

Vehicle fitment resolution does not happen in the CRM catalog itself.

The frontend or proxy resolves vehicle fitment first, then passes wheel specs into the catalog query.

### Product Detail and Brand Pages

Additional wheel assumptions exist in:

- [ProductVariantController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/ProductVariantController.php)
- [BrandController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/BrandController.php)

Wheel-specific behaviors:

- PDP “more sizes” tabs are based on distinct `rim_diameter`
- brand product variants and “more sizes” are also grouped by `rim_diameter`
- size strings are constructed as `rim_diameter x rim_width`

### Transformer Contract

Frontend JSON is shaped by:

- [WholesaleProductTransformer.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Wholesale/Helpers/WholesaleProductTransformer.php)

This transformer exposes wheel-first response fields directly, including:

- `rim_diameter`
- `rim_width`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `backspacing`
- `lipsize`
- `max_wheel_load`
- `size = diameter x width`

So the backend response contract is deeply coupled to wheels, not just the DB schema.

## 6. Wheel-Size Proxy / External Fitment Provider

The CRM has a proxy for wheel-size.com:

- [WheelSizeProxyController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/WheelSizeProxyController.php)
- [wheel_size.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/config/wheel_size.php)

Supported proxy endpoints:

- `makes`
- `models`
- `years`
- `modifications`
- `search/by_model`

Important point:

The proxy exists, but the legacy Angular app still calls wheel-size.com directly from the browser instead of using the CRM proxy.

For the new platform, the proxy seam should be preserved and preferred.

Reason:

- removes API keys from the frontend
- abstracts the provider
- makes wheel fitment support easier to re-enable later

## 7. Pricing Foundation Already Present In CRM

The current CRM already has a reusable customer pricing hierarchy in:

- [DealerPricingService.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Customers/Services/DealerPricingService.php)

Today it is based on:

- model-level pricing
- brand-level pricing
- add-on category pricing

This is wheel-era pricing, but the existence of a real pricing service is important.

It means we should not hardcode tyre pricing directly in controllers later.

We should keep a formal pricing service seam in the new design.

## 8. What Is Reusable vs What Is Wheel-Specific

### Reusable Foundation

- catalog/listing page shell
- PDP layout shell
- filters sidebar pattern
- fitment-provider concept
- pricing service concept
- inventory and warehouse concept
- product/variant separation
- source/provider abstraction idea

### Strongly Wheel-Specific

- wheel-size.com fitment mapping
- `rim_diameter`, `rim_width`, `bolt_pattern`, `offset`, `hub_bore`
- staggered front/rear wheel pairing logic
- “more sizes” grouped by rim diameter
- wheel-specific frontend interfaces
- wheel-specific transformer payloads

## 9. Recommendation For The New Platform

Do not keep the old wheel data model active in the new tyre launch.

Do keep the architectural seam for wheel support now.

Recommended implementation direction:

### 1. Category Registry

Create a category-capable catalog/search foundation with:

- `tyres` enabled
- `wheels` disabled

### 2. Category Adapters

Treat search/filter/spec behavior as adapter-driven:

- tyre search adapter
- wheel search adapter

### 3. Fitment Provider Abstraction

Keep a dedicated fitment provider seam:

- tyre fitment provider
- wheel fitment provider

The future wheel fitment provider can still use wheel-size.com through the backend proxy.

### 4. Category-Specific Filter Definitions

Do not hardcode one universal filter set.

Use category filter definitions, for example:

- tyres:
  - section width
  - aspect ratio
  - rim diameter
  - load index
  - speed rating
- wheels:
  - rim diameter
  - rim width
  - bolt pattern
  - offset
  - hub bore

### 5. Category-Specific Spec Renderers

Keep the storefront shell shared, but make the specification blocks category-aware.

### 6. Category-Specific Response Mappers

Keep the new storefront contracts category-safe and map backend responses through adapters instead of exposing raw wheel fields everywhere.

## 10. Final Recommendation

Because the CRM already has a meaningful wheel foundation, we should preserve wheel capability at the architecture level now.

But we should not build wheels as a fully live launch feature yet.

The correct balance is:

- generic platform core
- tyre-first live implementation
- wheel-ready search/provider/catalog seams
- wheel category disabled until later

This avoids a tyre-only dead end, while also avoiding the cost and complexity of delivering full wheels in the first launch.
