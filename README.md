# Clockwork Tyres Platform

Clockwork Tyres is the new unified platform for:

- super admin
- supplier admin
- retailer admin
- retail storefront integration

This repo is the platform/core repo.

## Purpose

This repo will become the new backend and platform foundation built from the lessons and structure of `reporting-crm`.

It is intended to own:

- account and tenancy model
- roles and permissions
- subscriptions and entitlements
- supplier-retailer relationship model
- tire catalog and inventory model
- commerce workflows
- procurement workflows
- admin APIs
- platform operations and reporting

## Source Systems

These repos are reference inputs:

- `reporting-crm`
  - base architecture and CRM capabilities
  - primary source for the new platform direction
- `dealer-portal-Angular`
  - reference for storefront UX patterns and legacy frontend flows
- `tunerstop-vendor`
  - reference-only for old supplier relationships, plan logic, and migration mapping

## Platform Direction

Recommended product split:

- `clockwork-tyres`
  - platform and backend
- `clockwork-tyres-storefront`
  - new retail storefront on modern Angular

## Architecture Principles

- build on the new CRM direction, not the legacy vendor backend
- use clean account boundaries and multi-tenant ownership rules
- keep platform staff, account users, and retail customers clearly separated
- use modular monolith architecture before considering services
- keep commerce, catalog, subscriptions, and supplier network as separate domains
- avoid pushing admin logic into the storefront
- keep migration and compatibility work explicit and testable

## Current Key Docs

- [ROADMAP.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/ROADMAP.md)
- [FRONTEND_REBUILD_ANALYSIS.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/FRONTEND_REBUILD_ANALYSIS.md)
- [ARCHITECTURE_DECISIONS.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/ARCHITECTURE_DECISIONS.md)
- [CATEGORY_FOUNDATION_STRATEGY.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/CATEGORY_FOUNDATION_STRATEGY.md)
- [LEGACY_WHEEL_FOUNDATION_RESEARCH.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/LEGACY_WHEEL_FOUNDATION_RESEARCH.md)
- [LEGACY_FEATURE_COMPATIBILITY_MATRIX.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/LEGACY_FEATURE_COMPATIBILITY_MATRIX.md)
- [LEGACY_ROUTE_QUERY_CONTRACT_MAP.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/LEGACY_ROUTE_QUERY_CONTRACT_MAP.md)
- [CRM_WHEEL_TO_CATEGORY_ADAPTER_MAP.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/CRM_WHEEL_TO_CATEGORY_ADAPTER_MAP.md)
- [BACKEND_CATEGORY_SEARCH_CONTRACT.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/BACKEND_CATEGORY_SEARCH_CONTRACT.md)
- [CLIENT_FACING_ROADMAP_FOR_GEORGE.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/CLIENT_FACING_ROADMAP_FOR_GEORGE.md)
- [OPEN_QUESTIONS_FOR_GEORGE.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/OPEN_QUESTIONS_FOR_GEORGE.md)
- [MESSAGE_FOR_GEORGE.md](C:/Users/Dell/Documents/Gerorge/clockwork-tyres/docs/MESSAGE_FOR_GEORGE.md)

## What Can Start Now

Low-regret work that can start immediately:

- repo bootstrap
- CI and coding standards
- account and tenancy foundation
- API boundary planning
- migration mapping
- permission matrix
- storefront architecture setup
- test strategy

## What Must Wait For Product Answers

- final tire import schema
- final tyre data sheet and import mapping details
- exact report add-on tier serialization in admin
- any optional wheel-later requirements George wants locked before launch

## Immediate Next Steps

1. scaffold backend module structure in this repo
2. scaffold the new storefront repo
3. capture architecture decisions as ADRs
4. define the account and identity model
5. map old API flows to new platform contracts

## Notes

This repo is intentionally early-stage. The goal right now is to lock clean architecture before feature code spreads.
