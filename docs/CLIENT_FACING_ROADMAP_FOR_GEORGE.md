# Clockwork Tyres Client Roadmap

Prepared for: George

Date: March 29, 2026

## 1. Project Vision

The goal is to build one unified Clockwork Tyres platform with three connected experiences:

- Super Admin
- Supplier Admin
- Retailer Admin plus Retail Storefront

The new platform will bring the current Clockwork frontend vision together with the strengths of the new CRM, while keeping the old TunerStop vendor system only as a reference for legacy rules and migration.

## 2. Recommended Product Direction

### What Stays

- the Clockwork retail/storefront experience
- supplier and retailer workflows
- inventory, quotes, invoices, and warehouse management
- supplier network and procurement model

### What Changes

- the new CRM becomes the core operational platform
- supplier discovery and procurement move into admin instead of the public storefront
- the storefront becomes retail-facing
- super admin gets one clear platform-wide view of all accounts, subscriptions, and analytics

## 3. Delivery Approach

We recommend building this in phases so that each stage produces something visible, testable, and reviewable.

This approach reduces risk, keeps the architecture clean, and allows important business decisions to be confirmed before deep implementation locks them in.

## 4. Phase-by-Phase Roadmap

## Phase 1: Business Alignment and Final Definition

### Purpose

Confirm the business rules and finalize the operating model before development moves too far.

### Focus

- confirm account types
- confirm subscription model
- confirm tyre product structure and import format
- confirm procurement workflow
- confirm what belongs in storefront versus admin

### Outcome

At the end of this phase, the team and George are aligned on exactly what is being built and how the main user journeys should work.

### What George Will Review

- final business flow summary
- account types and access model
- subscription and plan assumptions
- tyre data structure
- procurement lifecycle summary

## Phase 2: Core Platform Foundation

### Purpose

Set up the platform foundation inside the new CRM so the system can support multiple suppliers and retailers properly.

### Focus

- account structure
- user access and permissions
- subscription framework
- supplier and retailer separation
- super-admin control layer

### Outcome

This phase creates the backbone of the platform, making sure future supplier, retailer, and storefront features are built on clean and scalable architecture.

### What George Will Review

- high-level account management model
- sample super-admin structure
- how supplier and retailer businesses are represented in the platform

## Phase 3: Supplier Admin Portal

### Purpose

Build the supplier experience using the new CRM as the main operating portal.

### Focus

- product management
- images
- inventory
- warehouses
- quotes and proformas
- invoices
- customer management
- analytics
- storefront preview of supplier-owned catalog

### Outcome

Suppliers can fully manage their own product and order operations inside the new platform.

### What George Will Review

- supplier dashboard
- product and inventory flows
- quote and invoice flow
- supplier storefront preview

## Phase 4: Retailer Admin Portal

### Purpose

Build the retailer-facing admin portal, powered by the same platform foundation but tailored to retailer needs.

### Focus

- retailer account management
- retailer users and settings
- own products and inventory where applicable
- supplier exploration inside admin
- supplier connection requests and approvals
- analytics and reporting

### Outcome

Retailers gain a proper back-office experience, separate from the storefront, where they can manage their business and supplier relationships.

### What George Will Review

- retailer admin navigation
- retailer dashboard
- supplier connection workflow
- retailer operational screens

## Phase 5: Retail Storefront

### Purpose

Build the new retail-facing Clockwork storefront on modern frontend architecture.

### Focus

- login and account entry points
- search by vehicle
- search by size
- product listing
- product detail
- cart
- checkout
- order confirmation and retail order flow

### Outcome

Retailers can use the new storefront to sell to end customers in a clean and modern way, while the operational/admin work remains inside the platform.

### What George Will Review

- homepage and storefront shell
- search experience
- product pages
- cart and checkout flow
- visual alignment with the Clockwork mockups

## Phase 6: Supplier Network and Procurement

### Purpose

Add the B2B supplier relationship and procurement model into the admin side of the platform.

### Focus

- explore suppliers in admin
- my suppliers in admin
- request and approval flow
- procurement search
- procurement cart
- supplier-side receipt of procurement requests
- quote/proforma to invoice flow

### Outcome

Retailers can source stock from connected suppliers using a controlled admin workflow, and suppliers can receive and process those requests inside their own portal.

### What George Will Review

- supplier request workflow
- procurement search flow
- supplier order intake flow
- approval and invoice conversion flow

## Phase 7: Migration and Pilot Rollout

### Purpose

Move selected accounts, products, and workflows from the old system into the new platform and validate the full journey with pilot users.

### Focus

- migration of supplier and retailer accounts
- migration of supplier links
- migration of plans and subscriptions where required
- product and inventory migration
- pilot rollout with a small number of real users

### Outcome

The new platform is proven in real operational conditions before wider launch.

### What George Will Review

- migration readiness summary
- pilot scope
- pilot feedback
- go-live readiness

## Phase 8: Launch and Optimization

### Purpose

Launch the new platform in a controlled way and refine based on live usage.

### Focus

- phased rollout
- support and monitoring
- bug fixing
- refinement of reports and analytics
- performance and usability improvements

### Outcome

Clockwork Tyres launches with a stable foundation and can continue to expand without needing another full rebuild.

### What George Will Review

- launch plan
- early adoption metrics
- post-launch improvement list

## 5. What George Can Expect to See During Delivery

The project will not stay hidden until the end. Each phase is designed to produce visible progress.

George should expect to review:

- workflow definitions
- navigation structures
- mockup-to-product alignment
- admin screens
- storefront screens
- supplier and retailer journeys
- pilot readiness

## 6. Key Decisions Needed From George

To keep momentum strong, a small number of decisions should be confirmed early.

### Highest Priority

- final account types: retailer, supplier, or both
- subscription and feature access rules
- final tyre import structure
- procurement workflow behavior
- payment expectations at checkout
- whether supplier stock is visible directly in storefront or only through admin procurement

## 7. Recommended Delivery Style

We recommend:

- phased delivery
- regular checkpoints with George
- no rushed shortcuts on architecture
- use of the new CRM as the platform foundation
- a new clean storefront codebase instead of forcing the old frontend to carry the new product

This gives the best balance between speed, quality, and long-term scalability.

## 8. Summary

Clockwork Tyres can be delivered successfully using the new CRM as the core platform and a new retail storefront built around George's latest vision.

The key to success is:

- one unified platform
- clear separation of storefront and admin responsibilities
- strong supplier and retailer workflows
- phased implementation
- early alignment on the few decisions that affect the platform structure

With this approach, the platform can launch cleanly and scale properly over time.
