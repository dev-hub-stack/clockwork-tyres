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
- `year` -> normalized from `DOT`
  - `2025` means year `2025`
  - `2625` also means year `2025` (`week 26 of 2025`)

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
- treat image fields using the same import/storage approach already used for wheel products in the current CRM

## Important Validation Notes

George has now clarified the remaining sheet behavior:

1. `full_size` is the composed size from `width + height + rim_size`
   - example: `245 + 30 + 19` => `245/30R19`
2. if an incoming `full_size` does not match the numeric dimensions, importer should warn and prefer the canonical derived size for grouping
3. `DOT` can be either:
   - year only, such as `2025`
   - week plus year, such as `2625` for week 26 of 2025
4. grouping should normalize both DOT forms to the same year
5. image fields should follow the same handling already used for wheel products

## Build Decisions Unblocked By This Sheet

This sheet is enough to move forward with:

- tyre contract mapping
- tyre admin grid columns
- import staging shape
- price-level field mapping
- storefront tyre data contract preparation
- backend validation rule scaffolding

## Remaining Clarifications

There are no remaining tyre-sheet blockers from George's current answers.
