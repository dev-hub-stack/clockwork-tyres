# Entitlement Groundwork

This document captures the smallest backend seam for George's subscription and capability rules.

## Supported checks

- Wholesale admin access
- Own products and inventory permissions
- Reports add-on access
- Reports customer limit
- Supplier connection limits

## Launch rules encoded in the seam

- Retail accounts on the basic plan are limited to 3 approved suppliers.
- Wholesale-enabled accounts can manage their own products and inventory.
- Reports access is treated as an add-on with a customer limit controlled by super admin.
- Premium retail accounts have no supplier connection cap in this seam.

## What this does not change

- No runtime authorization gate is switched over yet.
- No current product grid or warehouse logic is modified.
- No database schema changes are required for this groundwork slice.
