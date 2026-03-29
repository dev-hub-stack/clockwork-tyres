# Clockwork Tyres Client Roadmap

Prepared for: George

Date: March 29, 2026

## 1. Project Vision

The goal is to build one unified Clockwork Tyres platform with three connected experiences:

- Super Admin
- Supplier Admin
- Retailer Admin plus Retail Storefront

The new platform will bring the current Clockwork frontend vision together with the strengths of the new CRM, while keeping the old TunerStop vendor system only as a reference for legacy rules and migration.

## 2. Confirmed Product Direction

The latest business direction is now much clearer.

The platform will support:

- one business account that can be retailer, supplier, or both
- one shared stock pool for mixed retailer-supplier accounts
- a retail storefront that shows:
  - the business's own stock first
  - then stock from approved suppliers
- hidden supplier identity on the storefront
- one merged storefront product entry when the same tyre exists from multiple sources
- a supplier `View Store` mode using the same frontend, but with no cart or checkout
- offline retail payments for launch
- a tire-first launch, with wheels possible later as an added category
- multi-level tyre pricing with retailer markup logic

In simple terms:

- the new CRM becomes the platform core
- the Clockwork storefront experience remains the visual model
- supplier discovery and procurement live in admin
- the public-facing or counter-facing store stays simple and retail-focused

## 3. Subscription Direction

### Retailer

- basic retailer plan:
  - up to 3 suppliers
  - no own products
  - no own inventory
- premium retailer plan:
  - more than 3 suppliers
  - own products
  - own inventory

### Supplier

- basic wholesaler plan:
  - can manage own products
  - can manage own inventory
  - no reports access

### Reports

- reports are subscription-based and tiered
- reports are configured as an add-on
- report tiers are based on total wholesale customers connected to the supplier
- example tiers shared so far:
  - 250 customers = AED 50 per month
  - 500 customers = AED 100 per month

### Mixed Retailer-Supplier Accounts

- one combined main subscription
- one shared stock pool
- supplier-side pricing levels controlled by customer assignment
- retailer-side sell price based on cost plus markup or fixed amount

## 4. Delivery Approach

We recommend building this in phases so that each stage produces something visible, testable, and reviewable.

This approach reduces risk, keeps the architecture clean, and allows important business decisions to be confirmed before deep implementation locks them in.

## 5. Phase-by-Phase Roadmap

## Phase 1: Business Alignment and Final Definition

### Purpose

Confirm the last few implementation details and lock the final operating model before deep build work.

### Focus

- confirm the final tire import sheet

### Outcome

At the end of this phase, the team has the final product import structure and can lock the launch catalog schema cleanly.

### What George Will Review

- final business rules summary
- tire import structure
- procurement lifecycle summary
- pricing field structure inside the tire sheet

## Phase 2: Core Platform Foundation

### Purpose

Set up the platform foundation inside the new CRM so the system can support multiple suppliers and retailers properly.

### Focus

- account structure
- user access and permissions
- subscription framework
- supplier and retailer capability model
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
- storefront preview using the same frontend in read-only mode

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
- procurement creation from manual workflows and operational workflows

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
- hidden supplier-backed catalog logic
- stock display rules
- supplier preview mode

### Outcome

Retailers can use the new storefront to sell in-store or customer-facing, while operational work remains inside the admin platform.

### What George Will Review

- homepage and storefront shell
- search experience
- product pages
- cart and checkout flow
- visual alignment with the Clockwork mockups
- own stock versus supplier-backed stock behavior

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
- stock reservation after approval

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

## 6. What George Can Expect to See During Delivery

The project will not stay hidden until the end. Each phase is designed to produce visible progress.

George should expect to review:

- workflow definitions
- navigation structures
- mockup-to-product alignment
- admin screens
- storefront screens
- supplier and retailer journeys
- pilot readiness
- subscription-gated behavior working correctly

## 7. Decisions Already Confirmed

The following major decisions are now confirmed:

- one account can be retailer, supplier, or both
- mixed retailer-supplier accounts use one shared stock pool
- supplier identity is hidden on the storefront
- own stock appears before supplier-backed stock
- same-tyre multi-source stock appears as one merged storefront entry
- supplier `View Store` reuses the same frontend with cart and checkout disabled
- retailers can place manual procurement orders
- approved quotes create invoices and deduct stock
- cancelled invoice flow returns stock back to the selected warehouse using current CRM behavior
- launch payments are offline and in-store
- super admin manages accounts and subscriptions, not supplier products
- one combined subscription applies to mixed retailer-supplier accounts
- reports are a separate super-admin-controlled add-on
- supplier allocation is manual in retailer admin
- pricing will support retail plus wholesale level 1, 2, and 3
- tires replace wheels for launch

## 8. Remaining Clarifications

Only one material dependency remains open:

- final tire import sheet

## 9. Recommended Delivery Style

We recommend:

- phased delivery
- regular checkpoints with George
- no rushed shortcuts on architecture
- use of the new CRM as the platform foundation
- a new clean storefront codebase instead of forcing the old frontend to carry the new product

This gives the best balance between speed, quality, and long-term scalability.

## 10. Summary

Clockwork Tyres can be delivered successfully using the new CRM as the core platform and a new retail storefront built around George's latest vision.

The key to success is:

- one unified platform
- clear separation of storefront and admin responsibilities
- strong supplier and retailer workflows
- phased implementation
- early confirmation of the few remaining implementation details

With this approach, the platform can launch cleanly and scale properly over time.
