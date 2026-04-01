# Tyre Sample Sheet Mapping

Date: March 30, 2026

Source file reviewed:

- `C:\Users\Dell\Documents\Gerorge\tyres-sample-datasheet.xlsx`

Workbook summary:

- workbook has `1` sheet
- sheet name is `Sheet1`
- sheet currently contains `24` launch columns
- sample workbook currently has `1` sample data row

## Launch Columns

Exact source headers received from George:

1. `SKU`
2. `Brand`
3. `Model`
4. `width`
5. `height`
6. `rim_size`
7. `full_size`
8. `load_index`
9. `speed_rating`
10. `DOT`
11. `Country`
12. `Type`
13. `Runflat`
14. `RFID`
15. `sidewall`
16. `warranty`
17. `Retail_price`
18. `wholesale_price_lvl1`
19. `wholesale_price_lvl2`
20. `wholesale_price_lvl3`
21. `brand_image`
22. `product_image_1`
23. `product_image_2`
24. `product_image_3`

## Internal Normalized Field Map

Recommended normalized field names in the platform:

- `sku`
- `brand`
- `model`
- `width`
- `height`
- `rim_size`
- `full_size`
- `load_index`
- `speed_rating`
- `dot`
- `country`
- `type`
- `runflat`
- `rfid`
- `sidewall`
- `warranty`
- `retail_price`
- `wholesale_price_lvl1`
- `wholesale_price_lvl2`
- `wholesale_price_lvl3`
- `brand_image`
- `product_image_1`
- `product_image_2`
- `product_image_3`

## Recommended Launch Grouping

### Identity

- `sku`
- `brand`
- `model`

### Fitment

- `width`
- `height`
- `rim_size`
- `full_size`
- `load_index`
- `speed_rating`

### Attributes

- `dot`
- `country`
- `type`
- `runflat`
- `rfid`
- `sidewall`
- `warranty`

### Pricing

- `retail_price`
- `wholesale_price_lvl1`
- `wholesale_price_lvl2`
- `wholesale_price_lvl3`

### Media

- `brand_image`
- `product_image_1`
- `product_image_2`
- `product_image_3`

## Grouping Rule Confirmed By George

For merged catalogue / storefront grouping:

- do **not** group by `sku`
- different suppliers may use different SKUs for the same tyre
- grouping should be done by:
  - `brand`
  - `model`
  - `size`
  - `year`

Recommended current mapping:

- `size` -> `full_size`
- `year` -> currently most likely `DOT`, pending final confirmation

This affects:

- merged storefront product identity
- supplier stock aggregation
- procurement source-option grouping
- importer dedupe and normalization rules

## Required Launch Fields

Recommended required fields for Phase 1 import:

- `sku`
- `brand`
- `model`
- `width`
- `height`
- `rim_size`
- `full_size`
- `load_index`
- `speed_rating`
- `retail_price`
- `wholesale_price_lvl1`
- `wholesale_price_lvl2`
- `wholesale_price_lvl3`

## Normalization Rules

- normalize incoming headers case-insensitively
- map `SKU` to `sku`
- map `Brand` to `brand`
- map `Model` to `model`
- map `Retail_price` to `retail_price`
- keep pricing levels internally as:
  - `retail`
  - `wholesale_lvl1`
  - `wholesale_lvl2`
  - `wholesale_lvl3`
- store source columns exactly for audit, but persist normalized field names in the platform
- normalize `Runflat` and `RFID` as boolean-like values from `YES/NO`
- treat image fields as import references, not final media records, until storage rules are finalized

## Important Validation Notes

The sample row reveals a few points we should validate before final importer signoff:

1. `full_size` sample value is `245/35R20` while `height` sample value is `30`
2. `DOT` sample value is `2026`, which may mean manufacturing year rather than a full DOT code
3. image columns currently look like file names, not URLs

These do not block us from building the schema and grid now, but they should be confirmed before we finalize import validation.

## Build Decisions Unblocked By This Sheet

This sheet is enough to move forward with:

- tyre contract mapping
- tyre admin grid columns
- import staging shape
- price-level field mapping
- storefront tyre data contract preparation
- backend validation rule scaffolding

## Remaining Clarifications

Only small clarifications remain from the sample sheet:

- confirm whether `height` or `full_size` is the source of truth when they conflict
- confirm whether `DOT` stores year only or the full DOT code
- confirm whether image fields are uploaded file names, server paths, or external URLs
