# CRM Wheel To Category Adapter Map

Date: March 29, 2026

Purpose: map the current `reporting-crm` wheel endpoints and models into the future category-adapter architecture, with clear notes on what can be reused later for wheels after the tyre launch.

## Current CRM Wheel Surface

The current CRM is wheel-first and should be treated as the future `wheels` adapter, not as the shared base model.

### Search and Catalog Endpoints

Current wheel endpoints in [api-wholesale.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/routes/api-wholesale.php):

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

### Core Wheel Models

The live wheel-shaped model layer is mainly in:

- [Product.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Products/Models/Product.php)
- [ProductVariant.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Products/Models/ProductVariant.php)
- [2025_10_22_000004_create_product_variants_table.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/database/migrations/2025_10_22_000004_create_product_variants_table.php)

Wheel-specific fields currently used as first-class data:

- `rim_diameter`
- `rim_width`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `backspacing`
- `lipsize`
- `max_wheel_load`
- rear fitment aliases such as `rear_rim_diameter`, `rear_rim_width`, and `rear_bolt_pattern`

### Response Shaping

The frontend-facing payload is shaped by:

- [WholesaleProductTransformer.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Wholesale/Helpers/WholesaleProductTransformer.php)

That transformer currently exposes wheel fields directly, so it should be treated as the future category response-transformer seam.

### Fitment Provider

The current wheel fitment provider seam lives in:

- [WheelSizeProxyController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/WheelSizeProxyController.php)

This should become the backend fitment-provider implementation for the wheel adapter later.

## Future Category Adapter Mapping

The new platform should split the current CRM wheel surface into category-aware layers:

### 1. Registry Layer

Use a category registry to decide:

- which categories exist
- which category is active
- which adapter handles the request

Launch state:

- `tyres` enabled
- `wheels` disabled but supported structurally

### 2. Adapter Layer

Map current CRM wheel controllers into adapter-owned responsibilities:

- `ProductController` -> category catalog/search controller
- `search-sizes` -> category size-search endpoint
- `search-vehicles` -> category vehicle-search endpoint
- `search-form-params` -> category form-definition endpoint
- `ProductVariantController` -> category PDP controller
- `BrandController` -> category brand/collection controller
- `WholesaleProductTransformer` -> category response transformer

### 3. Provider Layer

Keep fitment as a provider interface, not a hardcoded wheel-size dependency.

- `WheelSizeProxyController` becomes one wheel-provider implementation
- tyre fitment can use a different provider or internal mapping later
- controllers should ask the provider through the adapter, not directly

### 4. Catalog Layer

Keep inventory, pricing, and source merging in the catalog layer.

This is where the platform should decide:

- what matches the query
- how own stock and supplier stock are merged
- what price level applies
- what inventory is available

## What We Can Reuse Later For Wheels

These parts should be preserved so wheels can come back cheaply after the tyre launch:

- route-driven search and listing structure
- `search by size` as a first-class concept
- `search by vehicle` as a first-class concept
- fitment-provider seam
- pricing service seam
- product/variant split
- inventory and warehouse aggregation
- listing to PDP to cart journey shell
- brand/collection browsing structure
- more-sizes / related-options concept

## What Should Not Be Reused As Shared Base Contracts

Do not make these the universal platform model:

- wheel-only query params such as `front_rim_diameter` and `front_bolt_pattern`
- wheel-only fitment fields as the shared launch schema
- wheel-size.com as the only fitment source
- rear stagger logic as the default category shape
- wheel-only transformer payload as the generic catalog response

## Practical Conclusion

The current CRM wheel implementation is valuable because it proves the wheel category can be supported later.

For launch, keep:

- the seams
- the adapter boundaries
- the provider abstraction

For tyres, replace:

- the universal wheel schema
- the wheel-only search contract
- the wheel-only response transformer

That gives us a tyre-first launch today and a clean wheel reactivation path later.
