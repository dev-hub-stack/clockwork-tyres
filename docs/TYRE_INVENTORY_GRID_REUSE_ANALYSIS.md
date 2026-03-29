# Tyre Inventory Grid Reuse Analysis

This note maps the current CRM inventory grid contract to the tyre launch path.
The goal is to reuse the same inventory interaction patterns without carrying
wheel-specific assumptions into the tyres rollout.

## Current Inventory Grid Contract

The existing inventory surface is built around a wide pqGrid with:

- full-width horizontal scrolling
- editable quantity cells
- warehouse-specific quantity and ETA columns
- save-batch support
- import/export template support
- bulk transfer and add-inventory modals
- movement log access
- row drill-down modals for consignment, incoming, and damaged stock

Relevant entry points:

- [InventoryGrid.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Filament/Pages/InventoryGrid.php)
- [inventory-grid.blade.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/resources/views/filament/pages/inventory-grid.blade.php)
- [InventoryController.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Http/Controllers/Admin/InventoryController.php)
- [InventoryApiController.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/app/Http/Controllers/Api/InventoryApiController.php)
- [routes/web.php](C:/Users/Dell/Documents/Gerorge/clockwork-tyres-backend/routes/web.php)

## What Tyres Should Reuse Exactly

The tyre admin page should reuse the inventory grid mechanics, not the wheel
metadata columns.

Reuse these patterns:

- the wide grid shell and horizontal scroll behavior
- the action bar shape:
  - Save Changes
  - Import Inventory
  - Add Inventory
  - Bulk Transfer
  - Export CSV
  - Movement Log
- the per-warehouse inventory model
- the save-batch workflow for inline edits
- the import template workflow
- the stock movement log workflow
- the modal drill-down pattern for stock history
- the SKU-first lookup behavior

## What Should Stay Wheel-Specific

Do not carry these wheel columns into the tyre launch grid:

- rim width
- rim diameter
- bolt pattern
- offset
- hub bore
- backspacing
- max wheel load
- lipsize

These are launch-category wheel fields. Tyres should use the same grid
structure, but with tyre-specific columns once George shares the sample sheet.

## Tyre Reuse Recommendation

The tyre launch page should follow the same inventory grid interaction model:

1. show a wide editable grid
2. keep warehouse columns and ETA/incoming patterns
3. keep bulk transfer and add inventory actions
4. keep save/import/export/log workflows
5. swap the middle product-spec columns to tyre fields after the sample sheet
   is reviewed

That gives George the same operational feel as the current CRM inventory page
while avoiding any wheel-only schema lock-in.

## Safe Launch Stance

Until the tyre sheet arrives:

- keep the wheel inventory page unchanged
- keep the tyre grid separate
- treat tyre columns as provisional
- reuse the inventory interaction patterns, not the wheel spec columns

