# Backend Category Search Contract

Date: March 29, 2026

Purpose: define the backend search and catalog foundation for the new Clockwork platform so tyres can launch first while wheels remain structurally supported for a later release.

This document is the contract between:

- the category-aware backend core
- the storefront search UI
- the fitment provider layer
- the legacy CRM wheel APIs we are carrying forward conceptually, not verbatim

## 1. Core Principle

The backend must be category-capable, but launch-scoped to tyres.

That means:

- `tyres` is the active launch category
- `wheels` is a supported future category
- search behavior is category-aware
- inventory and pricing are shared platform concerns
- fitment and filters are category adapters, not hardcoded global assumptions

Do not make wheel fields the universal product model.

Do keep the seam that allows wheels to be re-enabled later.

## 2. Category Registry

The backend should expose a category registry, even if only one category is active on day one.

### 2.1 Category Responsibilities

Each category entry should describe:

- stable category id
- display label
- active / inactive status
- search capabilities
- size search fields
- vehicle fitment source
- filter schema
- spec renderer schema
- import mapping version

### 2.2 Launch Categories

Recommended launch state:

- `tyres` = active
- `wheels` = inactive but supported

### 2.3 Category Registry Shape

A future registry concept can be modeled as:

```ts
type CatalogCategoryId = 'tyres' | 'wheels';

type CatalogCategoryDefinition = {
  id: CatalogCategoryId;
  label: string;
  active: boolean;
  searchBySize: boolean;
  searchByVehicle: boolean;
  fitmentProviderKey: string;
  sizeSearchFields: string[];
  filterKeys: string[];
  specKeys: string[];
};
```

The exact implementation can vary, but the platform should always be able to answer:

- is this category active?
- what search forms does it support?
- what filters does it expose?
- what spec fields does it render?

## 3. Search Contract

The backend search contract should have two shared entry points:

- search by size
- search by vehicle

Both must be category-aware.

### 3.1 Search By Size

Search by size should not be a single hardcoded wheel query forever.

Instead, it should be:

- category selected
- category adapter resolves size fields
- backend applies the category-specific query serializer
- results are returned in a merged catalog shape

Example category differences:

- tyres may search by:
  - section width
  - aspect ratio
  - rim diameter
  - load index
  - speed rating
- wheels may search by:
  - rim diameter
  - rim width
  - bolt pattern
  - offset
  - hub bore

### 3.2 Search By Vehicle

Search by vehicle should also be category-aware.

The shared platform should preserve the journey:

1. resolve vehicle fitment
2. convert fitment into category search inputs
3. query the category catalog
4. return merged results

The exact fitment mapping is category-specific.

For tyres, the fitment data may differ from wheels.
For wheels, the current wheel-size mapping remains relevant as a future adapter.

## 4. Fitment Provider Abstraction

The platform should not couple vehicle fitment directly to one provider.

### 4.1 Why This Matters

The legacy frontend calls wheel-size.com directly from the browser, while the CRM already has a backend proxy seam.

Relevant current files:

- [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts)
- [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)
- [helper.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/helper.service.ts)
- [WheelSizeProxyController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/WheelSizeProxyController.php)

### 4.2 Provider Contract

The backend should expose a fitment-provider interface that can support:

- wheel-size.com
- future tyre fitment sources
- any internal fitment tables we add later

Example conceptual shape:

```ts
type FitmentProviderKey = 'wheel-size' | 'internal-tyre-fitment';

interface FitmentProvider {
  makes(input: unknown): Promise<unknown>;
  models(input: unknown): Promise<unknown>;
  years(input: unknown): Promise<unknown>;
  modifications(input: unknown): Promise<unknown>;
  searchByVehicle(input: unknown): Promise<unknown>;
}
```

The important part is not the exact TypeScript syntax.

The important part is the seam:

- category search must depend on a provider interface
- categories must not depend on one external provider directly

## 5. Adapter Boundaries

Each category should be implemented through adapters, not direct branching in controllers and components.

### 5.1 Adapter Responsibilities

Category adapters should own:

- query param mapping
- search-by-size field mapping
- search-by-vehicle fitment mapping
- filter schema
- product spec rendering keys
- import mapping
- compatibility logic

### 5.2 Adapter Boundary Rule

Controllers should ask the adapter:

- which fields do I search on?
- which filters are available?
- how do I serialize this search?
- how do I render this product type?

Controllers should not know:

- tyre launch-only field names
- wheel-only field names
- provider-specific fitment details

That logic belongs in the adapter layer.

## 6. Merged Catalog Behavior

George confirmed that storefront catalog behavior must merge same-tyre stock from multiple sources.

Backend rules:

- own stock appears first
- supplier-backed stock appears after
- if the same tyre exists from multiple sources, the storefront shows one merged product entry
- supplier identity remains hidden on the storefront
- retailer admin selects the supplier manually behind the scenes

### 6.1 What The Backend Must Return

The backend should return:

- a merged product result
- hidden source options for admin workflows
- source-aware availability and stock labels
- category-aware pricing data

The backend should not force the frontend to stitch multiple supplier entries into one product card.

### 6.2 What Must Remain Available Internally

Even if the storefront shows one merged product entry, the backend must preserve:

- source inventory rows
- source supplier options
- source price levels
- warehouse allocation details

That is what lets retailer admin choose the supplier manually later.

## 7. Mapping Current CRM Wheel Endpoints To The Future Architecture

The current CRM wheel endpoints are useful as the wheel adapter implementation, not as the shared universal contract.

Relevant current files:

- [api-wholesale.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/routes/api-wholesale.php)
- [ProductController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/ProductController.php)
- [ProductVariantController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/ProductVariantController.php)
- [BrandController.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Http/Controllers/Wholesale/BrandController.php)
- [WholesaleProductTransformer.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Wholesale/Helpers/WholesaleProductTransformer.php)
- [ProductVariant.php](C:/Users/Dell/Documents/Gerorge/reporting-crm/app/Modules/Products/Models/ProductVariant.php)

### 7.1 Current Wheel Routes

Today the CRM exposes:

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

### 7.2 How They Map Forward

The future architecture should treat these as:

- `ProductController` -> category search controller, with a wheel adapter today and a tyre adapter for launch
- `search-sizes` -> category size-search endpoint
- `search-vehicles` -> category vehicle-search endpoint
- `search-form-params` -> category form-definition endpoint
- `ProductVariantController` -> category PDP controller
- `BrandController` -> category brand/collection controller
- `WholesaleProductTransformer` -> category response transformer, not a wheel-only transformer forever
- `WheelSizeProxyController` -> one possible fitment provider implementation, not the only source of fitment truth

### 7.3 What To Keep And What To Replace

Keep:

- route-driven search and listing structure
- inventory aggregation patterns
- pricing service seam
- fitment provider seam

Replace:

- wheel-specific query params as the universal contract
- wheel-specific transformer payload as the generic catalog model
- wheel-only `more sizes` behavior as the only supported category behavior

## 8. Recommended Backend Search Layers

The backend should be organized into these layers:

### 8.1 Registry Layer

Answers:

- which categories exist?
- which categories are active?
- which adapter handles this category?

### 8.2 Adapter Layer

Answers:

- how is search-by-size serialized?
- how is search-by-vehicle serialized?
- what filters are available?
- what spec fields render?

### 8.3 Provider Layer

Answers:

- where does fitment data come from?
- how do we normalize it?
- how do we map it into the category adapter?

### 8.4 Catalog Layer

Answers:

- what products match?
- how are sources merged?
- what pricing applies?
- what inventory is available?

## 9. Launch Scope

For launch:

- `tyres` category is active
- wheel support is not active in production
- wheel support remains structurally supported through adapters and provider seams

This gives us:

- a clean tyre launch
- a future wheel reactivation path
- less code churn when George brings wheels back later

## 10. Decision

The backend should be built as a category-aware platform with category adapters and fitment-provider abstraction.

Do not ship wheel-specific contracts as the shared base model.

Do preserve the wheel extension seam now so future wheel support is cheap and clean.

That is the correct compromise between:

- tyre-first launch speed
- long-term wheel support
- reusable Clockwork search behavior
- scalable backend architecture
