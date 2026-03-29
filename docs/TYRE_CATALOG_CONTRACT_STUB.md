# Tyre Catalog Contract Stub

## Purpose

This document defines the launch-ready contract boundary for the tyre catalog work that George shared in the mockups.

The goal is to keep the backend ready for a tyre import sheet tomorrow without hardcoding the final tyre columns today.

## Scope

- Launch category: `tyres`
- Future category: `wheels`
- Current implementation target: a separate tyre contract boundary, not the existing wheel-specific product grid
- Shared storefront behavior confirmed by George:
  - one shared stock pool for business accounts
  - merged storefront entry when the same tyre is supplied by multiple sources
  - manual supplier selection in admin

## Fixed Launch Decisions

- Tyres are the first live catalog category.
- Retail and wholesale pricing is tiered.
- The launch pricing tiers are:
  - `retail`
  - `wholesale_lvl1`
  - `wholesale_lvl2`
  - `wholesale_lvl3`
- The tyre catalog should accept a raw sheet row plus metadata about the file and row source.

## Intentionally Generic Contract Sections

These are the only sections we should rely on before the sample sheet arrives:

- `identity`
- `merchandising`
- `pricing`
- `inventory`
- `fitment`
- `media`
- `metadata`
- `audit`

## Ingest Envelope

The first import pass should accept:

- source file
- sheet name
- row number
- raw row payload

Validation, normalization, and field mapping should happen after the sample sheet is reviewed.

## What We Are Not Locking Yet

- final tyre field names
- required vs optional tyre attributes
- image column count
- fitment attribute shape
- duplicate matching rules
- warehouse routing rules
- import normalization rules

## Recommended Next Step When the Sheet Arrives

1. Map every header from George's sheet to one of the generic sections above.
2. Mark required fields only after the real sample is reviewed.
3. Build the real transformer and validator from the mapped headers.
4. Hook the tyre contract into the separate tyre admin grid.

