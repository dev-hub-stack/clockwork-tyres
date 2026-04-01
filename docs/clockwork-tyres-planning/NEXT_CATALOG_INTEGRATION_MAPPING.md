# Next Catalog Integration Mapping

Date: March 29, 2026

Purpose: map the legacy Clockwork storefront endpoint and query patterns to the new backend and Angular seams for the next catalog integration slice.

Scope:

- launch category: `tyres`
- future category: `wheels`
- do not carry wheel-only contracts forward as the shared base model

## Short Version

Keep the legacy user journey shape.
Replace the legacy wheel-first data contract.
Use the new backend bootstrap and storefront repository seams as the integration boundary.

## Mapping Table

| Legacy pattern | Legacy source | New backend seam | New storefront seam | Status |
|---|---|---|---|---|
| `GET /api/products` and `GET /api/filters` | `dealer-portal-Angular/src/app/shared/services/api.service.ts` | Category-aware catalog controller to be added behind the CRM core | `clockwork-tyres-storefront/src/app/core/storefront-data/storefront-data.repository.ts` | Replace with category-aware catalog API |
| `POST /api/search-sizes` | `api.service.ts`, `form.service.ts`, `search-by-size.component.ts` | Category size-search endpoint or adapter layer in the backend | `src/app/core/storefront-routes.ts`, `src/app/core/fitment/*` | Keep the journey, swap wheel fields for tyre fields at launch |
| `POST /api/search-vehicles` | `api.service.ts`, `search-by-vehicle.component.ts`, `helper.service.ts` | Fitment-provider-backed vehicle search endpoint | `src/app/core/storefront-bootstrap/*`, `src/app/core/fitment/*` | Keep the journey, hide provider details behind an adapter |
| `GET /api/search-form-params` | `api.service.ts` | Category form-definition endpoint | `src/app/core/storefront-bootstrap/*` and catalog search forms | Replace wheel-only params with category-specific form metadata |
| `GET /api/product/{slug}/{sku}` | `api.service.ts`, `wheels-detail.component.ts` | Product detail controller with category-aware payloads | `src/app/features/catalog/product-detail-page.component.*` | Keep PDP shape, redesign data contract |
| `GET /api/product-more-sizes/...` | `api.service.ts`, `helper.service.ts` | Related-size / compatibility adapter | PDP and catalog detail helpers | Defer wheel-specific behavior, keep the seam |
| `GET /api/brand*` routes | `api.service.ts` | Category collections / brand endpoints | Catalog browse pages | Preserve conceptually, redesign for tyres first |
| `GET /api/cart/*`, `POST /api/order/store` | `api.service.ts`, `shopping-cart.component.ts` | Commerce/cart/order endpoints in the new CRM core | `src/app/features/cart/*` | Keep journey, but move to the new platform contract |
| `GET /api/dealer/vendors`, `POST /api/dealer/vendor-request` | `api.service.ts` | Account/context and supplier-relationship layer | `src/app/core/storefront-bootstrap/*`, later admin UI | Move supplier discovery to the admin side of the platform |
| `GET /api/storefront/bootstrap` | new backend controller | `app/Http/Controllers/Wholesale/StorefrontBootstrapController.php` | `src/app/core/storefront-bootstrap/*` | New integration entry point |
| `GET /api/account-context`, `POST /api/account-context/select` | new backend controller + middleware | `app/Http/Controllers/Api/AccountContextController.php`, `app/Http/Middleware/ResolveCurrentAccount.php` | future account selector / session state | New platform context seam |
| `GET /api/wheel-size/*` | legacy vehicle fitment flow | `app/Http/Controllers/Wholesale/WheelSizeProxyController.php` and future adapters | `src/app/core/fitment/*` | Keep as a provider seam, not as the universal storefront contract |

## What To Keep

- search by vehicle as a first-class entry point
- search by size as a first-class entry point
- listing, PDP, cart, and account journey shape
- route-based mode/category/session resolution
- merged own-stock-first then supplier-stock-second catalog behavior

## What To Replace

- wheel-only URL/query assumptions as the shared storefront contract
- wheel-only product schema as the base model
- direct browser coupling to fitment providers
- mock-only storefront data as the final integration boundary

## Recommended Integration Order

1. Hydrate storefront mode, category, and account context from `storefront/bootstrap`.
2. Replace mock storefront state with a repository backed by the bootstrap and catalog APIs.
3. Swap the search/listing pages to a real category-aware catalog endpoint.
4. Keep wheels disabled in launch data, but preserve the adapter seam so they can return later without a rewrite.

## Highest-Signal Findings

- The backend already has the right integration seams for account context and storefront bootstrap.
- The Angular storefront already has the right bootstrap and repository seams to consume those APIs cleanly.
- The old storefront is still wheel-first in its concrete field set, so tyres should be launched through adapters, not by reusing wheel fields as the default contract.
