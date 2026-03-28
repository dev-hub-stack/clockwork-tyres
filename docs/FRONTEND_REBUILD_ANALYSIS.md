# Frontend Rebuild Analysis

Date: March 29, 2026

## 1. Recommendation Summary

Do not modernize the current Angular storefront in place.

Recommended approach:

- create `clockwork-tyres-storefront` as a clean Angular storefront repo
- use `dealer-portal-Angular` as a reference and extraction source only
- reuse proven UX patterns and business flows selectively
- do not carry forward old wheel-focused routing, jQuery usage, or legacy API contracts

Reason:

- the current app is Angular 11 and heavily coupled to legacy Clockwork behavior
- the current app mixes retail storefront, supplier discovery, and lightweight admin concerns
- the current app still assumes wheel-specific search, data, and APIs
- a clean-room storefront on modern Angular will be easier to scale, test, and maintain

## 2. Current Frontend Assessment

## Stack State

Current frontend repo:

- `dealer-portal-Angular`

Current version indicators:

- Angular 11 in [package.json](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/package.json)
- NgModule-based structure in [app.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/app.module.ts)
- Angular Universal SSR bootstrap in [server.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/server.ts)
- legacy API base in [environment.prod.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/environments/environment.prod.ts)

Observed architecture issues:

- global `ApiServices` service is too large and couples the whole app to one API contract
- global `BehaviorSubject` state in `DataService`
- widespread `localStorage` coupling
- direct DOM access and jQuery usage
- hard redirects with `location.href`
- route structure mixes storefront and supplier/admin journeys
- wheel and fitment assumptions are spread through search, catalog, product detail, cart, checkout, and my-account features

## Codebase Shape

The current frontend contains roughly:

- 46 page-level components under `pages`
- 13 account-area components under `my-accounts`
- 10 shared components under `shared`

Key architectural files:

- [app.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/app.module.ts)
- [pages.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages.module.ts)
- [shared.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/shared.module.ts)
- [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts)
- [my-accounts-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/my-accounts/my-accounts-routing.module.ts)
- [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)
- [data.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/data.service.ts)

## 3. What The Current Frontend Gets Right

These ideas are worth reusing conceptually:

- search by vehicle
- search by size
- product listing grid
- product detail flow
- cart
- checkout
- order/account area
- supplier preview concepts
- account login and self-service shell

Useful reference files:

- [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts)
- [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts)
- [shopping-cart.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/cart/shopping-cart/shopping-cart.component.ts)
- [checkout.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/cart/checkout/checkout.component.ts)
- [header.component.html](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/includes/header/header.component.html)

## 4. What Must Not Be Carried Forward

These patterns should not be reused in the new storefront:

- Angular 11 project structure
- NgModule-first composition for feature code
- jQuery-based UI behavior
- direct DOM querying and manual script injection
- global mutable state through broad `BehaviorSubject` services
- wheel-size API dependency as the core search model
- public storefront ownership of supplier exploration and procurement
- route and component naming built around `wheel`, `vendor`, and legacy dealer assumptions

Examples:

- [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts)
- [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts)
- [suppliers.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/suppliers/suppliers.component.ts)
- [my-vendors.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/my-vendors/my-vendors.component.ts)
- [login.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/login/login.component.ts)
- [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)

## 5. Modern Angular Recommendation

As of March 29, 2026, the latest published `@angular/core` version from npm is `21.2.6`.

Recommended baseline for the new storefront:

- Angular 21
- standalone component architecture
- signal-first local and page state
- zoneless default change detection
- built-in control flow syntax
- route-level code splitting
- deferred loading for non-critical UI
- modern SSR and hydration only where justified

Official Angular references used for this recommendation:

- [Angular Signals](https://angular.dev/guide/signals)
- [Angular Zoneless](https://angular.dev/guide/zoneless)
- [Angular Control Flow](https://angular.dev/guide/templates/control-flow)
- [Angular Deferred Loading](https://angular.dev/guide/templates/defer)
- [Angular httpResource](https://angular.dev/guide/http/http-resource)

## 6. Angular Capabilities We Should Use

### Use By Default

- standalone components instead of feature NgModules
- `bootstrapApplication`
- signal-based view state
- `computed` for derived UI state
- `OnPush` mindset across the app
- `@if`, `@for`, and `@switch`
- typed `HttpClient` services
- route-level lazy loading
- feature-based folder boundaries
- Playwright for end-to-end testing

### Use Selectively

- `@defer` for below-the-fold UI and heavy non-critical sections
- SSR plus hydration for landing or SEO-sensitive pages
- `httpResource` only for read-heavy reactive data screens

### Avoid In The New Storefront

- jQuery
- direct `document` and `window` manipulation unless wrapped carefully
- broad global state services for everything
- one mega API service
- mutation-heavy business logic inside components

## 7. Target Storefront Architecture

Recommended repo:

- `clockwork-tyres-storefront`

Recommended top-level structure:

- `src/app/core`
- `src/app/shell`
- `src/app/features/auth`
- `src/app/features/catalog`
- `src/app/features/search`
- `src/app/features/product-detail`
- `src/app/features/cart`
- `src/app/features/checkout`
- `src/app/features/account`
- `src/app/features/orders`
- `src/app/features/supplier-preview`
- `src/app/shared/ui`
- `src/app/shared/lib`
- `src/app/shared/api`
- `src/app/shared/types`

### Boundary Rules

- `core` owns app-wide providers, interceptors, config, and error handling
- `shell` owns layout, navigation, and app composition
- `features/*` own routed business areas
- `shared/ui` holds reusable presentational components only
- `shared/api` holds typed clients grouped by bounded context, not one mega service
- feature state stays local unless it genuinely must be shared

## 8. Exact Change Points

## A. App Bootstrap and Project Structure

Current files:

- [app.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/app.module.ts)
- [pages.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages.module.ts)
- [shared.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/shared.module.ts)

Change required:

- rewrite

Why:

- current structure is Angular 11, NgModule-heavy, and encourages broad shared modules instead of clear feature boundaries

Target:

- standalone bootstrap
- feature-first directory structure
- lean shared UI library

## B. Environment and API Contract

Current files:

- [environment.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/environments/environment.ts)
- [environment.prod.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/environments/environment.prod.ts)
- [api.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/api.service.ts)

Change required:

- rewrite

Why:

- the frontend is still coupled to the old Clockwork API surface
- the service mixes auth, catalog, suppliers, checkout, account, and CMS calls in one place

Target:

- split API clients into:
  - auth client
  - catalog client
  - cart client
  - checkout client
  - account client
  - orders client
  - content client

## C. Routing and Feature Ownership

Current files:

- [pages-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/pages-routing.module.ts)
- [my-accounts-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/my-accounts/my-accounts-routing.module.ts)

Change required:

- partial reuse of route ideas, full route redesign

Why:

- storefront routes currently mix retail catalog, supplier exploration, vendor login, dashboard, downloads, and account-admin style pages

Target storefront route groups:

- `/`
- `/login`
- `/register`
- `/search/vehicle`
- `/search/size`
- `/brands`
- `/products/:slug`
- `/cart`
- `/checkout`
- `/account`
- `/orders`
- `/preview/:supplierSlug`

Move out of storefront:

- supplier discovery
- supplier requests
- supplier approvals
- procurement
- inventory administration
- reports

## D. Authentication and Signup

Current file:

- [login.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/login/login.component.ts)

Change required:

- rewrite

Why:

- current signup flow assumes instant account creation and token issuance
- it mixes retailer and vendor registration
- it carries legacy plan selection and trade-license assumptions directly in the storefront

Target:

- retailer staff login
- retailer account signup or invite activation
- supplier preview access only if needed
- clear separation between self-service auth and admin-managed onboarding

## E. Vehicle Search

Current file:

- [search-by-vehicle.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-vehicle/search-by-vehicle.component.ts)

Change required:

- rewrite using the same UX intent

Why:

- current logic uses wheel-size assumptions, jQuery, direct redirects, and wheel fitment fields

Target:

- tire-ready vehicle lookup
- signal-based form state
- router navigation instead of `location.href`
- no jQuery or direct DOM lookups

## F. Size Search

Current file:

- [search-by-size.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/components/search-by-size/search-by-size.component.ts)

Change required:

- rewrite

Why:

- current fields are wheel-based: rim width, offset ranges, rear offsets

Target:

- tire size inputs such as width, aspect ratio, rim size, load index, speed rating, run-flat, and season depending on final schema

## G. Catalog Listing and Product Detail

Current files:

- [wheels-listing.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-listing/wheels-listing.component.ts)
- [wheels-detail.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/wheels/wheels-detail/wheels-detail.component.ts)
- [alloy-wheels.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/alloy-wheels/alloy-wheels.component.ts)

Change required:

- rewrite around new tire domain

Why:

- current listing and detail flows are wheel-domain driven in naming, filters, and fitment logic

Target:

- tire category listing
- tire product detail
- supplier preview variant
- service upsells for checkout where approved

## H. Supplier Pages

Current files:

- [suppliers.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/suppliers/suppliers.component.ts)
- [my-vendors.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/pages/my-vendors/my-vendors.component.ts)

Change required:

- move out of storefront

Why:

- George's new vision moves supplier exploration and procurement into admin
- these pages are tightly coupled to old dealer-vendor flows

Target:

- remove from storefront MVP
- rebuild equivalent behavior in CRM admin
- keep only supplier preview mode in storefront

## I. Cart and Checkout

Current files:

- [shopping-cart.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/cart/shopping-cart/shopping-cart.component.ts)
- [checkout.component.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/cart/checkout/checkout.component.ts)

Change required:

- partial flow reuse, full implementation rewrite

Why:

- current logic depends on vendor ownership, legacy checkout options, legacy payment behavior, and direct script injection

Target:

- retail cart
- retail customer capture
- retail order creation
- invoice handoff to CRM
- optional payment layer depending on MVP decision

## J. Account Area

Current files:

- [my-accounts-routing.module.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/my-accounts/my-accounts-routing.module.ts)

Change required:

- selective reuse only

Keep conceptually:

- profile
- address book
- order history

Move out of storefront:

- plans
- integrations
- my products
- my inventory
- supplier management

## K. Global State

Current file:

- [data.service.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/src/app/shared/services/data.service.ts)

Change required:

- rewrite

Why:

- the current state layer is a broad global service with cross-feature mutable subjects and local storage coupling

Target:

- signals for page and feature state
- explicit persistent storage wrappers only where needed
- no hidden cross-feature writes

## L. SSR and Rendering Strategy

Current file:

- [server.ts](C:/Users/Dell/Documents/Gerorge/dealer-portal-Angular/server.ts)

Change required:

- redesign, not copy

Why:

- current SSR setup is tied to Angular Universal-era assumptions and polyfills

Target:

- decide page-by-page whether SSR is actually needed
- use modern Angular rendering approach only for landing, marketing, or SEO-sensitive pages
- do not let SSR complexity block storefront delivery

## 9. What Can Start Now

- create the new storefront repo structure
- freeze architecture boundaries
- create UI foundations and design tokens
- define route structure
- define API client boundaries
- define auth shell
- build a read-only catalog shell with mocked data
- define testing setup with Playwright and component tests
- create compatibility map from old API methods to new CRM contracts
- produce a component inventory from the old frontend

## 10. What Should Wait For George

- final tire schema
- exact subscription behavior in storefront
- payment behavior in checkout
- whether storefront is public or counter-only
- whether supplier stock is visible directly in storefront
- how supplier preview pricing should behave
- exact procurement trigger rules

## 11. Final Frontend Decision

Recommended decision:

- build a new storefront on Angular 21
- keep the old Angular app as reference only
- migrate UX patterns, not architecture

This is the cleanest way to achieve:

- scalable architecture
- latest Angular best practices
- lower long-term maintenance
- better testing
- better performance
- clearer separation between storefront and admin responsibilities
