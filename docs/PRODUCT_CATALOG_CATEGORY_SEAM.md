# Product Catalog Category Seam

This backend seam keeps the product catalog category-aware without changing the current wheel grid behavior.

## Launch direction

- `tyres` is the launch category.
- `wheels` remains represented in the catalog foundation so it can be enabled later.

## What this seam does

- Gives the backend a single source of truth for launch category selection.
- Makes category-enabled storefront work possible without hardcoding wheel logic into new tyre work.
- Keeps the current wheel-specific `ProductsGrid` page unchanged.

## What this seam does not do

- It does not change the existing wheel grid query.
- It does not add category database columns.
- It does not switch any current admin UI to tyre mode yet.

## Intended next use

- A tyre-specific grid/resource can read this registry when the sample tyre sheet arrives.
- Future wheel support can use the same registry instead of introducing another one-off path.
