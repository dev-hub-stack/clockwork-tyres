# Tyre Admin Mockup Alignment

Date: March 29, 2026

This note maps George's mockup intent to the new Clockwork Tyres admin direction and explains how the tyre launch differs from the current wheel-specific CRM grid.

## What George's Mockups Mean

The mockups point to three distinct experiences:

1. Retail storefront
2. Supplier/admin workspace
3. Super admin overview

For the admin side, the main signal is that tyres should launch as a separate, category-specific working surface. We should not force the tyre launch through the existing wheel grid contract.

## What The Current Grid Is

The current CRM `ProductsGrid` is wheel-shaped. It reads wheel fields directly from product variants, including:

- `rim_width`
- `rim_diameter`
- `bolt_pattern`
- `offset`
- `hub_bore`
- `backspacing`
- `max_wheel_load`

That is useful as a legacy reference, but it is not a safe base for the tyre launch.

Reference:

- [ProductsGrid.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Filament/Pages/ProductsGrid.php)

## Tyre Launch Direction

For tyres, the correct path is:

- keep the existing wheel grid as legacy reference
- create a separate tyre-specific grid/resource
- keep shared platform concerns in common services and models
- wait for George's sample sheet tomorrow before locking final tyre columns

This lets us preserve the mockup intent while avoiding a bad data-model merge.

## What To Reuse From The Mockups

- the CRM-style table/grid workflow
- product creation/editing patterns
- inventory and pricing management patterns
- warehouse association and account scoping
- separate admin/supplier/super-admin boundaries

## What Not To Reuse Directly

- wheel-only variant fields as the tyre base schema
- the existing `ProductsGrid` as the tyre launch grid
- any current wheel-only fitment assumptions as the shared product contract

## Recommended Implementation Shape

1. Leave `ProductsGrid` intact as the wheel-era reference.
2. Add a tyre-specific admin grid/resource.
3. Back both with a category-aware product core.
4. Wire the tyre import pipeline from George's sample sheet once it arrives.
5. Keep wheels available as a future category path, not the launch contract.

## Bottom Line

George's mockups support a clean separation: tyres launch with their own admin path, while the old wheel grid remains a legacy reference and future wheels can be reintroduced on top of the shared platform foundation.
