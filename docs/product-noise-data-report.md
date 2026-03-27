# Product Noise Data Report

Generated from the current `reporting-crm` database on 2026-03-24 20:35:29 UTC.

## Summary

- Brands with no products: 0
- Products with no variants: 6872
- Products with no variants and no detected order references: 6864
- Products with no variants and no order refs or inventory links: 6861
- Products with no variants but used in orders: 8
- Products with no variants but linked to inventory: 3

## Main Findings

### 1. No orphan brands

There are currently no non-deleted brands that have zero products.

### 2. Large variantless product bucket

The main data-quality issue is products that exist without any `product_variants` rows.

Top brands for variantless products:

| Brand | Count | Used in Orders | Inventory Linked |
| --- | ---: | ---: | ---: |
| No Brand | 4199 | 5 | 0 |
| JR Wheels | 281 | 0 | 0 |
| FUEL | 270 | 0 | 0 |
| Black Rhino | 268 | 0 | 3 |
| Vossen | 223 | 2 | 0 |
| Fittipaldi Wheels | 144 | 0 | 0 |
| Riviera | 143 | 0 | 0 |
| KMC | 128 | 0 | 0 |
| Method race | 103 | 0 | 0 |
| Raceline | 91 | 0 | 0 |
| BBS | 83 | 0 | 0 |
| American Racing | 79 | 0 | 0 |
| Apex Race Parts | 78 | 0 | 0 |
| Konig Wheels | 75 | 0 | 0 |
| Fifteen52 | 66 | 0 | 0 |

## Do Not Bulk Delete First

These products have no variants but are still referenced by order items:

| Product ID | Name | Brand | Order Item Refs |
| --- | --- | --- | ---: |
| 4912 | 4PF8 | No Brand | 6 |
| 3046 | FF10 | No Brand | 2 |
| 3051 | FF11 | No Brand | 2 |
| 3052 | FF15 | No Brand | 2 |
| 1404 | SR8 Wheel | No Brand | 4 |
| 2023 | RF7 Wheel | Brixton Forged | 2 |
| 19478 | HF5 | Vossen | 2 |
| 19458 | HFX1 | Vossen | 1 |

These products have no variants but are still linked to inventory:

| Product ID | Name | Brand | Inventory Records | Inventory Qty Signal |
| --- | --- | --- | ---: | ---: |
| 19504 | Abrams | Black Rhino | 1 | 10 |
| 15765 | Abrams | Black Rhino | 1 | 0 |
| 15766 | Abrams | Black Rhino | 4 | 5 |

## Likely Safe Cleanup Bucket

There are 6861 products that:

- have no variants
- have no order item references
- have no inventory links

Representative examples from that bucket:

| Product ID | Name | Brand | External Product ID |
| --- | --- | --- | --- |
| 10202 | 131B Evo | No Brand | 10202 |
| 10203 | 131S Evo | No Brand | 10203 |
| 17173 | 14 DRAG F73 | No Brand | 17173 |
| 10204 | 141B Mystique | No Brand | 10204 |
| 10205 | 141S Mystique | No Brand | 10205 |
| 10206 | 144M Storm | No Brand | 10206 |
| 10207 | 145M Encore | No Brand | 10207 |
| 10208 | 145S Encore | No Brand | 10208 |
| 10209 | 146B Matrix | No Brand | 10209 |
| 10210 | 146S Matrix | No Brand | 10210 |

## Recommended Cleanup Order

1. Review and likely remove the 6861 zero-usage variantless products first.
2. Handle the 3 inventory-linked products separately so inventory records are resolved before deletion.
3. Handle the 8 order-linked products separately so historical order integrity is preserved.
4. After cleanup, backfill or enforce variant creation during imports to stop the same pattern from returning.
