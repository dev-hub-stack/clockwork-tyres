# Clockwork Tyres Current Status

Date: April 2, 2026

This file is the practical status snapshot for the active Clockwork Tyres build.

## Current Standing

- architecture / product direction: `93%`
- backend platform foundation: `96%`
- tyre import / catalog / inventory flow: `90%`
- storefront functional foundation: `90%`
- CRM-native admin rebuild for new Clockwork flows: `90-92%`
- overall V1: about `93%`

## What Is Confirmed

- accounts can be:
  - retailer only
  - supplier only
  - both
- supplier and wholesaler mean the same role
- combined `both` accounts require a paid subscription
- retailer free plan supplier limit is fixed at `3`
- George has now shared plan screenshots for:
  - retailer `Starter / Plus / Enterprise`
  - wholesaler `Starter / Premium / Enterprise`
- storefront is counter-only after login in phase 1
- procurement can be standalone
- supplier approval converts to invoice and deducts stock
- payments are out of scope for phase 1
- launch assumes fresh accounts by default
- multi-branch is handled in phase 1 as multiple warehouses only
- enterprise custom pricing is manually configured per account by super admin
- tyre import image fields should follow the same approach used for wheel products
- `full_size` is derived from `width + height + rim_size`
- `DOT` can be either:
  - year only, such as `2025`
  - week plus year, such as `2625` for week `26` of `2025`

## Main Work Already In Place

- business accounts and account switching
- CRM-native procurement requests
- retailer-admin procurement entry points:
  - search
  - grouped results
  - cart
  - my orders
  - pending orders
- supplier review / approval / revision / rejection lifecycle
- quote and invoice linkage for procurement
- tyre import staging and apply flow
- tyre catalog groups and offers
- tyre availability resolution
- live tyre listing and PDP APIs
- storefront business login / registration flow
- realistic demo data and end-to-end flow tests
- fresh Playwright counter-flow smoke:
  - login required for storefront
  - login succeeds with seeded retailer owner
  - add to cart works
  - checkout creates an order
  - created order appears in account orders
- guarded storefront routes now use honest live states:
  - catalog shows loading / empty / error instead of silent mock fallback
  - account workspace shows loading / empty / error instead of silent mock fallback
  - PDP no longer falls back to a fake product when the live slug is missing
- checkout now waits for live workspace hydration before allowing order submission
- storefront unit/spec suite is green locally again
- internal subscription mapping is now stable:
  - `basic` = free starter
  - `premium` = retailer `Plus` or wholesaler `Premium`
  - enterprise remains manual / custom pricing

## Main Remaining Work

### Product / Data Clarifications

- no current product-blocking questions remain from George's latest answers

### Backend / Platform

- deployment pipeline setup
- environment hardening
- UAT smoke checklist
- final cleanup of remaining legacy rough edges around the new flows
- broader backend coverage beyond the new Clockwork Tyres slice

### Storefront

- finish login-only / counter-only UX polish
- tighter UI parity against the live Clockwork experience
- broader storefront regression coverage beyond the current smoke and spec suite

### Launch / Ops

- pilot validation
- operator walkthroughs
- launch checklist
- defect-fix round after pilot feedback

## Notes

- The planning docs under `docs/clockwork-tyres-planning/` are the current source of truth.
- Historical root-level CRM notes under `docs/root/` should not be treated as the active Clockwork Tyres roadmap.
