# Product Grid Reuse Analysis

Date: March 29, 2026

Scope: wheel-oriented CRM product grid implementation and how the tyre admin should reuse the same layout/pattern without inheriting wheel-only fields.

## What Exists Today

### 1) Filament page host

File: [app/Filament/Pages/ProductsGrid.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Filament/Pages/ProductsGrid.php)

This page is the grid host. It:

- registers the Filament navigation entry
- loads warehouse data with a cache
- performs a single join query against `product_variants`, `products`, `brands`, `models`, and `finishes`
- builds a flat `products_data` array for the view

The page is mostly presentation-agnostic. The important coupling is the selected field list in the query and the row shape it passes to the Blade view.

### 2) Blade shell and asset loading

File: [resources/views/filament/pages/products-grid.blade.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/resources/views/filament/pages/products-grid.blade.php)

The Blade file provides the reusable grid chrome:

- Bootstrap and pqGrid assets
- page-level alerts and helper text
- action buttons
- grid container
- bulk upload modals
- embedded JSON data:
  - `var data = @json($products_data);`
- legacy JS bundle include:
  - `public/js/products-grid.js`

The Blade view is the best part to reuse for the tyre admin because it is almost entirely layout, not product-domain logic.

### 3) Redirect shell

File: [resources/views/filament/pages/products-grid-redirect.blade.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/resources/views/filament/pages/products-grid-redirect.blade.php)

This is only a redirect wrapper and does not carry product logic.

### 4) pqGrid client-side implementation

File: [public/js/products-grid.js](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/public/js/products-grid.js)

This file is the most important place to split reuse from wheel-specific fields.

Reusable grid patterns already present:

- pqGrid initialization and column model
- select-all checkbox column
- delete action column
- toolbar actions
- filter row behavior
- local data model refresh
- AJAX save/delete/bulk toggle patterns
- toast feedback
- edit/save/refresh fallback logic

Wheel-oriented fields currently hardcoded in the column model:

- `rim_width`
- `rim_diameter`
- `size`
- `bolt_pattern`
- `hub_bore`
- `offset`
- `backspacing`
- `max_wheel_load`
- `weight`
- `lipsize`

Flags currently wired into the same grid:

- `available_on_wholesale`
- `track_inventory`

These flags are operationally reusable, but the column labels and edit rules should not be treated as the tyre launch schema.

### 5) AJAX / server-side endpoints

Files:

- [routes/web.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/routes/web.php)
- [app/Http/Controllers/ProductVariantGridController.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Http/Controllers/ProductVariantGridController.php)

Current endpoints used by the grid:

- `POST /admin/products/grid/save-batch`
- `POST /admin/products/grid/delete-batch`
- `POST /admin/products/toggle-wholesale-flag`
- `POST /admin/products/bulk-toggle-wholesale-flag`

The controller is still wheel-shaped in its assumptions:

- it saves and updates `ProductVariant` rows directly
- it writes wheel spec fields like rim diameter, rim width, bolt pattern, hub bore, offset, weight, lipsize, and max wheel load
- it toggles product-level wholesale visibility and variant-level inventory tracking
- it bulk-imports using wheel-oriented header mappings

## What Is Safe To Reuse For Tyres

The tyre admin should reuse the following as-is or with minimal change:

- the Filament page host pattern
- the Blade shell and modals
- the pqGrid toolbar, filtering, paging, and selection UX
- the AJAX batch operations pattern
- the local refresh / toast / loading behavior
- the warehouse-cache pattern if inventory needs to appear in the grid

This means the tyre admin can keep the same “Excel-like” grid experience without inheriting the wheel data contract.

## What Must Be Separated From The Tyre Launch

The following are wheel-only and should not be the default tyre contract:

- wheel spec columns:
  - rim width
  - rim diameter
  - bolt pattern
  - hub bore
  - offset
  - backspacing
  - max wheel load
  - weight
  - lipsize
- wheel import header mapping
- wheel naming assumptions in the row builder
- any controller validation that assumes wheel-shaped product rows

If tyres inherit these directly, the admin will stay visually “correct” but the data model will remain wheel-biased.

## Recommended Tyre Reuse Model

The clean approach is:

1. Keep the grid host, layout, and interaction model.
2. Move the visible column model into a category contract.
3. Make the backend row builder emit fields based on the active category.
4. Keep shared operations generic:
   - save batch
   - delete batch
   - wholesale toggle
   - inventory tracking toggle
   - import/export
5. Make the category contract define:
   - column list
   - validators
   - editable fields
   - import headers
   - display labels

For tyres, that contract should be tyre-specific and should not depend on wheel fields being present.

## Practical Migration Guidance

### Keep

- pqGrid layout and interaction model
- Blade shell and alerts
- batch-save workflow
- delete workflow
- toast/loading behavior
- server-side paging and filtering patterns if reused

### Replace

- wheel column definitions
- wheel validation assumptions
- wheel import headers
- hardcoded `ProductVariant` field selection for tyres

### Add

- category-aware field map
- tyre schema adapter
- import schema adapter
- shared grid metadata contract

## Bottom Line

The current product grid is a good **UI pattern** but a poor **domain contract** for tyres.

For the tyre admin, reuse the grid shell, the pqGrid behavior, and the AJAX action patterns, but move all product fields, validators, and import mappings behind a tyre-specific category adapter.

That gives us:

- the same visual and operational experience
- no wheel-only fields leaking into launch
- an easier path to add wheels later as a second category
