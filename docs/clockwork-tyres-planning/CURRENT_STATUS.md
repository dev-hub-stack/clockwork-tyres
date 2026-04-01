# Clockwork Tyres Current Status

Date: April 2, 2026

This file is the practical status snapshot for the active Clockwork Tyres build.

## Current Standing

- architecture / product direction: `93%`
- backend platform foundation: `96%`
- tyre import / catalog / inventory flow: `90%`
- storefront functional foundation: `85%`
- CRM-native admin rebuild for new Clockwork flows: `88-90%`
- overall V1: about `90%`

## What Is Confirmed

- accounts can be:
  - retailer only
  - supplier only
  - both
- supplier and wholesaler mean the same role
- combined `both` accounts require a paid subscription
- retailer free plan supplier limit is fixed at `3`
- storefront is counter-only after login in phase 1
- procurement can be standalone
- supplier approval converts to invoice and deducts stock
- payments are out of scope for phase 1
- launch assumes fresh accounts by default
- multi-branch requires multiple users and warehouses from day one

## Main Work Already In Place

- business accounts and account switching
- CRM-native procurement requests
- supplier review / approval / revision / rejection lifecycle
- quote and invoice linkage for procurement
- tyre import staging and apply flow
- tyre catalog groups and offers
- tyre availability resolution
- live tyre listing and PDP APIs
- storefront business login / registration flow
- realistic demo data and end-to-end flow tests

## Main Remaining Work

### Product / Data Clarifications

- confirm `height` vs `full_size` when they conflict
- confirm whether `DOT` is year-only or full DOT code
- confirm whether image fields are file names, storage paths, or URLs

### Backend / Platform

- deployment pipeline setup
- environment hardening
- UAT smoke checklist
- final cleanup of remaining legacy rough edges around the new flows

### Storefront

- finish login-only / counter-only UX polish
- replace remaining fallback/mock seams with final live behavior
- tighter UI parity against the live Clockwork experience
- end-to-end storefront workflow validation

### Launch / Ops

- pilot validation
- operator walkthroughs
- launch checklist
- defect-fix round after pilot feedback

## Notes

- The planning docs under `docs/clockwork-tyres-planning/` are the current source of truth.
- Historical root-level CRM notes under `docs/root/` should not be treated as the active Clockwork Tyres roadmap.
