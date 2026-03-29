# Legacy Route and Query Contract Map

Date: March 29, 2026

Purpose: map the legacy Clockwork storefront routes and query params in [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts) to the new category-aware storefront contract in [clockwork-tyres-storefront](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-storefront).

Launch scope: **tyres only**.  
Future scope: **wheels** can be added through the same category seam later.

## Contract Summary

The new storefront should preserve the legacy journey shape:

- search by vehicle
- search by size
- listing
- PDP
- cart
- account

But the new contract must be category-aware, not wheel-only.

## Route and Query Mapping

| Legacy area | Legacy route / params | New contract at launch | Wheels later |
|---|---|---|---|
| Search by vehicle | `/serchvehicle`, vehicle make/model/year/modification flow, wheel fitment params from [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts) and [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts) | Category-aware search-by-vehicle entry for tyres | Same flow stays in place, but the adapter switches to wheel fitment fields when `wheels` is enabled |
| Search by size | `/search-by-size`, wheel size query params from [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts) and [form.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/form.service.ts) | Tyre size search contract and tyre filter serializer | Keep the same entry point, but swap in wheel fields through a category adapter later |
| Listing | `/wheels`, listing state and search params handled in [wheels-listing.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.ts) | Category-aware listing route for tyres, with merged stock presentation | Same listing shell can render wheels when the category is turned on |
| PDP | `/:id/:variant_id` and wheel detail flow in [wheels-detail.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-detail/wheels-detail.component.ts) | Tyre product detail page with category-aware specs and fitment | Same PDP shell can render wheel spec blocks through the wheel adapter |
| Cart | Legacy wheel cart flow in [shopping-cart.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/cart/shopping-cart/shopping-cart.component.ts) | Tyre cart and checkout flow with shared stock rules | Same cart shell can accept wheel products later without changing the journey shape |
| Account | Account state and routing behavior spread through [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts) and shared services | Tyre-first customer account area | Same account area remains unchanged when wheels are enabled later |

## Query Param Guidance

### Keep the legacy intent

The following ideas should remain, but not as wheel-only contracts:

- `searchByVehicle`
- `search_by_size`
- route-driven listing state
- product detail navigation by id and variant

### Replace the legacy field contract

Do **not** carry these wheel-specific fields forward as the shared storefront contract:

- `front_rim_diameter`
- `front_rim_width`
- `front_bolt_pattern`
- `min_offset`
- `max_offset`
- `rear_rim_diameter`
- `rear_rim_width`
- `rear_bolt_pattern`
- `rear_offset`

For tyres, the new query contract should use tyre-appropriate fields.  
For wheels later, the same routes can accept wheel-specific fields through the category adapter.

## Launch Rule

At launch:

- the storefront should behave like Clockwork in layout and journey
- the data contract should be tyre-first
- wheels should remain structurally supported but inactive

This keeps the legacy experience familiar while avoiding another wheel-only rewrite.
