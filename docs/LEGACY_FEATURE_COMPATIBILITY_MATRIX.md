# Legacy Feature Compatibility Matrix

Date: March 29, 2026

Purpose: define how the legacy Clockwork frontend in [dealer-portal-Angular](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular) should influence the new tyre-first platform in [clockwork-tyres-storefront](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-storefront).

This document is launch-scoped to tyres, but it keeps the right seams for future wheels and other categories.

## Decision Legend

- **Preserve exactly**: keep the user-facing behavior and flow as close to legacy as possible.
- **Preserve conceptually, redesign**: keep the interaction or business idea, but reimplement it for the new category-capable platform.
- **Defer**: do not build for tyre-first launch, but keep a clear future path.
- **Drop**: do not carry this behavior into the new platform because it is obsolete, redundant, or too wheel-specific for launch.

## Compatibility Matrix

| Area | Legacy behavior and source files | Launch decision | New platform guidance |
|---|---|---|---|
| Main storefront shell | Legacy header, navigation, and page composition are spread across [header.component.html](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/includes/header/header.component.html), [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts), and the catalog/detail/cart pages. | **Preserve conceptually, redesign** | Keep the same overall storefront shell, page hierarchy, and navigation rhythm, but rebuild in Angular 21 with category-aware routing and tyre-first content. |
| Home / landing entry points | Legacy app routes users into search-first journeys such as `/serchvehicle`, `/search-by-size`, and `/wheels` from [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts). | **Preserve conceptually, redesign** | Keep the same search-led homepage structure, but route into tyre-first search forms and category adapters instead of wheel-only routes. |
| Search by vehicle | The legacy vehicle flow starts in [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts), uses [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts) and [helper.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/helper.service.ts), then redirects to wheel listing routes. | **Preserve conceptually, redesign** | Keep the vehicle-search journey, but swap the wheel-size fitment contract for a category adapter so tyres can launch first and wheels can be re-added later. |
| Search by size | Legacy size search is implemented in [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts), [form.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/form.service.ts), and [wheels-listing.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.ts). | **Preserve conceptually, redesign** | Keep search-by-size as a first-class experience, but replace wheel-only fields with tyre fields now and wheel fields later through a category adapter. |
| Catalog listing | Legacy listing behavior lives in [wheels-listing.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.ts) and [wheels-listing.component.html](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.html). | **Preserve exactly in structure, redesign in data contract** | Keep the filter-sidebar/list/grid composition and result browsing flow, but make the data source category-aware and tyre-first. |
| Product detail page | The PDP is implemented through [wheels-detail.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-detail/wheels-detail.component.ts), [wheel-single.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheel-single/wheel-single.component.ts), and [ProductDetailResponse.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/interfaces/ProductDetailResponse.ts). | **Preserve conceptually, redesign** | Keep the PDP layout, gallery, price, stock, fitment, and warehouse sections, but render tyre specs and tyre fitment first. |
| Product data contract | The legacy browse contract is wheel-first in [wheels.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/interfaces/wheels.ts). | **Drop as a shared base contract** | Do not reuse the wheel contract as the platform-wide product schema. Replace it with a category-capable product model and category adapters. |
| Wheel-specific spec fields | Legacy UI and APIs expect fields like `rim_diameter`, `rim_width`, `bolt_pattern`, `hub_bore`, `offset`, `backspacing`, `lipsize`, and `max_wheel_load` from [wheels.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/interfaces/wheels.ts) and CRM model files such as [ProductVariant.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Products/Models/ProductVariant.php). | **Defer** | Keep the seam for wheels, but do not make these the tyre launch schema. Add a wheel adapter later if George re-enables that category. |
| Cart | Legacy cart behavior is in [shopping-cart.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/cart/shopping-cart/shopping-cart.component.ts). | **Preserve conceptually, redesign** | Keep the shopping-cart journey, summary, quantity handling, and checkout handoff, but make it tyre-first and remove wheel-only assumptions. |
| Checkout | The legacy storefront has a cart-to-order checkout flow tied to the same wheel storefront journey. | **Preserve conceptually, redesign** | Keep the checkout route and summary flow, but simplify launch behavior to the agreed business model and shared stock logic. |
| Account area | Legacy account and user-state behavior is woven into the storefront app structure and routing in [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts) and shared services. | **Preserve conceptually, redesign** | Keep account/profile/order history/address management, but attach it to the new multi-account platform model. |
| Supplier discovery / add supplier | Legacy wholesale relationships are implied across storefront/admin flows and are now explicitly part of the new platform vision. | **Preserve conceptually, redesign** | Keep supplier discovery and supplier connection as a core business capability, but implement it in the new CRM-backed admin flow rather than the legacy wheel-only frontend. |
| Supplier store preview | Legacy store preview behavior is not a formal launch concept in the wheel frontend. | **Preserve conceptually, redesign** | Add a read-only supplier "view store" mode in the new storefront, with cart and checkout disabled, as confirmed by George. |
| Brand pages and marketing routes | Legacy branding and wheel-brand navigation are routed from [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts) and the catalog pages. | **Defer** | Do not spend launch effort on category-specific marketing routes unless they support tyre launch SEO or core discovery. |
| Add-to-cart wheel customization | Legacy PDP/cart flow includes wheel-specific fitment and customization logic in [wheels-detail.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-detail/wheels-detail.component.ts). | **Drop for launch** | Remove wheel customization behavior from the tyre launch. Reintroduce only if a later category needs it. |
| More-sizes and wheel fitment suggestions | CRM and legacy code support wheel fitment and related-size flows through [ProductController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/ProductController.php), [WheelSizeProxyController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/WheelSizeProxyController.php), and related APIs. | **Defer** | Keep the idea of related fitment suggestions, but do not ship wheel-specific more-sizes logic as a launch dependency for tyres. |
| External wheel-size provider | The legacy vehicle flow depends on wheel-size.com through [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts) and [helper.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/helper.service.ts). | **Defer** | Preserve the fitment-provider seam, but do not couple the tyre launch directly to wheel-size.com as the default external contract. |
| Admin product management for suppliers | Legacy frontend does not provide the new supplier-admin operating model; the CRM still has wheel-oriented product/inventory primitives in [api-wholesale.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/routes/api-wholesale.php) and the product controllers. | **Preserve conceptually, redesign** | Build the new supplier admin in the CRM base, with product, inventory, quotes, invoices, and warehouse management for tyres first. |
| Super admin overview | Super admin is a new platform concept that is not fully expressed in the legacy frontend. | **Preserve conceptually, redesign** | Build a dedicated super-admin console for account/subscription oversight, analytics, and platform governance in the new CRM-driven stack. |
| Legacy wheel-only routes | Routes such as `/wheels`, `/search-by-size`, `/serchvehicle`, and wheel detail paths are present in [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts). | **Drop as launch routes** | Do not carry these exact wheel routes into the tyre-first launch. Replace them with category-aware routes that can later support wheels again. |
| Legacy wheel-only query params | The legacy listing and search flow relies on wheel query parameters such as fitment dimensions and offset windows from [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts). | **Drop as shared contract** | Do not use these query parameters as the platform-wide URL contract. Introduce a category-aware query schema instead. |

## Launch Priorities By Functional Area

### Preserve Exactly

Keep these legacy patterns almost unchanged in user experience:

- storefront shell and page rhythm
- listing-to-PDP-to-cart journey shape
- search-led navigation
- basic price/stock presentation areas

### Preserve Conceptually, Redesign

Keep the business idea, but reimplement it for a tyre-first platform:

- search by vehicle
- search by size
- catalog browsing
- PDP composition
- cart and checkout flow
- account area
- supplier discovery
- supplier preview store
- super admin overview

### Defer

Keep these for a later release path:

- wheel-specific fitment extension
- wheel-size.com dependency as a universal provider
- brand/category marketing routes that do not support launch discovery
- more-sizes and wheel-specific compatibility suggestions

### Drop

Remove these from the tyre-first launch contract:

- wheel-only product schema as the shared base model
- wheel-only query parameters as the universal URL contract
- wheel customization logic as a launch requirement
- exact wheel routes like `/wheels` as first-class launch paths

## Practical Rule For The New Platform

The new platform should follow this rule:

- **preserve legacy UX structure**
- **redesign legacy data contracts**
- **launch tyres first**
- **leave clean seams for wheels later**

That is the safest way to keep George's visual expectations, support current business workflows, and avoid locking the new platform into a wheel-only model again.
