# Retailer Admin Procurement Checkout Mapping

Date: March 29, 2026

Purpose: define how the retailer admin procurement flow should be represented in the Clockwork Tyres platform, phase by phase, and how it differs from the retail storefront checkout flow.

This is a docs-only mapping for the platform repo. It does not change runtime behavior.

## 1. Core Interpretation

In the retailer admin, "checkout" does not mean a customer payment checkout.

For George's flow, admin checkout means:

- selecting suppliers manually
- building a procurement request
- sending it for supplier quote / approval
- converting the approved quote to an invoice
- deducting stock after invoice creation
- restoring stock on cancellation using the current reporting CRM method

So the admin flow is closer to:

`procurement request -> supplier quote -> approval -> invoice -> stock movement`

not:

`cart -> payment -> customer order completion`

## 2. Where This Lives In The Overall Roadmap

This mapping belongs mainly to:

- Phase 4: Retailer Admin Portal
- Phase 6: Supplier Network and Procurement

The storefront checkout remains a separate phase and separate user journey.

## 3. Confirmed Business Rules Behind The Flow

George's answers lock these rules in:

- one business account can be retailer, supplier, or both
- one shared stock pool applies to mixed accounts
- supplier identity is hidden on the storefront
- storefront can merge the same tyre from multiple sources into one entry
- supplier selection is manual in retailer admin
- procurement can start from a retail invoice or manually
- retailers can place standalone procurement orders
- procurement cart can include products from multiple suppliers at once
- products remain grouped under each supplier
- retailer can click one final `place order` action to submit all supplier groups together
- the system still splits those submissions into separate supplier orders
- when a quote is approved and invoiced, stock is deducted
- if cancelled, stock is added back to the selected warehouse using the current reporting CRM method
- no online payment capture for launch

## 4. Phase-By-Phase Admin Procurement Mapping

### Phase 1: Procurement Readiness And Account Context

Purpose:

- make sure the admin side knows which account, supplier links, and entitlements are active before procurement starts

What the platform should represent:

- current account context
- retailer/supplier/both capability
- supplier connection list
- stock ownership context
- subscription gates that may affect procurement-related screens

What George should review:

- the admin account header / context model
- supplier connection visibility
- where procurement is allowed or blocked

### Phase 2: Supplier Discovery And Procurement Entry

Purpose:

- let the retailer admin discover approved suppliers and start a procurement flow from inside admin

What the platform should represent:

- supplier search
- approved supplier list
- manual supplier selection
- product discovery inside admin
- procurement entry points from:
  - manual procurement
  - retail invoice follow-up

What George should review:

- how a retailer starts procurement
- where the supplier is selected
- how the admin flow differs from storefront browsing

### Phase 3: Procurement Cart And Request Build

Purpose:

- assemble the procurement request before it is sent to the supplier

What the platform should represent:

- procurement cart or request basket
- line items for selected tyres
- quantity
- supplier choice
- supplier-grouped sections inside one workbench
- warehouse destination context
- price tier selection where applicable
- one unified submit action that fans out into separate supplier requests

Important:

- this is not a payment cart
- this is a business request builder
- the retailer is preparing a quote / order request, not paying at checkout
- internally, the workbench behaves like a grouped multi-supplier cart, not a single supplier order

What George should review:

- line-item capture
- supplier selection behavior
- warehouse targeting
- the wording used in the admin UI

### Phase 4: Supplier Quote, Approval, And Invoice Conversion

Purpose:

- convert the procurement request into the supplier-side quote and then into an invoice after approval

What the platform should represent:

- quote sent to supplier
- supplier review
- quote approval
- invoice creation
- stock deduction after invoice creation
- supplier workflow remains separate for each supplier group created from the retailer admin workbench

Important:

- this is the major transition point
- it should be auditable
- it should follow the same stock movement method already used in the reporting CRM

What George should review:

- quote status labels
- approval status labels
- invoice conversion trigger
- whether the admin UI shows the transition as "checkout", "submit", or "place procurement order"

### Phase 5: Cancellation And Stock Reversal

Purpose:

- define how the system behaves when an approved procurement flow is cancelled

What the platform should represent:

- cancellation action
- stock return to the selected warehouse
- audit trail of who cancelled and why

Important:

- cancellation should not create a new stock model
- it should reuse the current reporting CRM stock reversal method

What George should review:

- cancellation wording
- stock reversal confirmation
- audit visibility

### Phase 6: Procurement Reporting And Subscription Awareness

Purpose:

- make procurement visible in analytics and subscription logic without turning it into a payment flow

What the platform should represent:

- procurement counts
- supplier relationship health
- invoice conversion metrics
- warehouse movement metrics
- reports entitlement overlays where relevant

Important:

- this is still not retail payment checkout
- this is operational visibility for the admin side

What George should review:

- which procurement metrics belong in retailer admin
- which metrics belong in super admin
- whether reports show procurement or only finished invoices

## 5. How This Differs From Retail Storefront Checkout

| Topic | Retailer Admin Procurement | Retail Storefront Checkout |
|---|---|---|
| Primary purpose | Source stock from suppliers | Sell tyres to retail customers |
| User | Retailer admin / operations user | Retail customer or retail-facing user |
| Supplier selection | Manual in admin | Hidden from storefront |
| Stock visibility | Source-aware behind the scenes | Merged own stock + approved supplier stock |
| Cart meaning | Procurement request builder | Customer purchase cart |
| Multi-supplier behavior | One workbench, grouped by supplier, submitted together, split into separate supplier orders | Single customer-facing cart |
| Payment capture | None for launch | None for launch, but still a storefront order journey |
| Final action | Quote approval -> invoice -> stock deduction | Order placement / checkout completion |
| Warehouse behavior | Stock returned to selected warehouse on cancellation | Standard retail order/fulfillment flow |
| Supplier identity | Visible internally in admin workflow | Hidden on storefront |
| UI language | Request, quote, approve, invoice | Add to cart, checkout, order confirmation |

## 6. Recommended Product Language

To keep the admin side clear, use these terms:

- `procurement request`
- `supplier quote`
- `approval`
- `invoice conversion`
- `stock deduction`
- `stock reversal`

Avoid using retail payment language in admin procurement screens.

## 7. Recommended Delivery Order

1. Lock the admin account and supplier context.
2. Build procurement entry and request drafting.
3. Build quote approval and invoice conversion.
4. Reuse reporting CRM stock reversal behavior.
5. Layer reporting and subscription awareness after the core flow is stable.

Implementation note:

`place order` in admin should feel unified for the retailer, but the backend must persist separate supplier-side orders, quotes, invoices, and stock movements per supplier.

## 8. Bottom Line

Retail storefront checkout and retailer admin procurement are related, but they are not the same workflow.

- storefront checkout is a retail selling journey
- admin procurement is a supplier sourcing and approval journey

George's platform should represent them as separate flows that share the same platform foundation but different business actions.
