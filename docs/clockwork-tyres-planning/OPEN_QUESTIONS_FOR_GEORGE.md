# Remaining Clarifications From George

Date: March 29, 2026

Most major business and workflow questions are now resolved.

The tyre sample sheet has now been shared, so the main dependency is no longer the missing file itself.

## Still Pending

### 1. Tyre Sheet Validation Clarifications

- confirm whether `height` or `full_size` is the source of truth when the two values conflict
- confirm whether `DOT` is intended to store:
  - year only
  - or full DOT code
- confirm whether image fields are:
  - uploaded file names
  - internal storage paths
  - or full external URLs

## Everything Else Now Confirmed

The following items are no longer open questions:

- the tyre sample sheet has been shared
- each account can be retailer-only, supplier-only, or both
- combined `both` accounts require a paid subscription
- supplier and wholesaler mean the same role
- the free retailer supplier limit is fixed at `3`
- storefront is counter-only after login for phase 1
- end customers should not see supplier count unless visible stock is `4 pcs or less`
- procurement can be done standalone
- supplier approval converts to invoice and deducts stock
- no platform payments in phase 1
- launch assumes fresh accounts by default, with case-by-case import only if needed
- multi-branch support is required from day one using multiple users and warehouses
- storefront grouping should ignore supplier SKU and use brand, model, size, and year
- one shared stock pool for `both` accounts
- four pricing levels in the tire sheet
- one merged storefront product entry for same-tyre multi-source stock
- manual supplier selection in retailer admin
- stock returns to selected warehouse on cancellation using current reporting CRM behavior
- reports tiers count total wholesale customers registered and connected to the supplier
- one combined main subscription, with reports as a configurable add-on
