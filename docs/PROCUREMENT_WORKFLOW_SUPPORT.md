# Procurement Workflow Support

Date: March 29, 2026

Purpose: provide a minimal shared workflow contract for retailer admin procurement pages without changing any quote or invoice runtime behavior.

## Why This Exists

The retailer admin needs a common set of workflow stages that can be referenced by upcoming procurement pages, filters, badges, and timeline components.

This is a support-only contract:

- it does not change quote conversion
- it does not change invoice conversion
- it does not change stock movement
- it does not add new runtime behavior to the current reporting CRM flow

## Workflow Stages

The launch contract exposes these procurement stages:

- `draft`
- `submitted`
- `supplier_review`
- `quoted`
- `approved`
- `invoiced`
- `stock_reserved`
- `stock_deducted`
- `fulfilled`
- `cancelled`

## Intended Meaning

- `draft`: procurement is being prepared
- `submitted`: procurement has been sent forward
- `supplier_review`: supplier is reviewing the request
- `quoted`: supplier quote is available
- `approved`: retailer has approved the quote
- `invoiced`: invoice has been created
- `stock_reserved`: stock is reserved for the approved procurement
- `stock_deducted`: stock has been deducted in the existing CRM method
- `fulfilled`: procurement is fully completed
- `cancelled`: procurement was cancelled

## Admin-Side Interpretation

This workflow is for retailer admin procurement support only.

It is not the storefront checkout flow.

It is not a payment flow.

It is a procurement and approval flow that the future admin pages can render consistently.

## Implementation Guidance

Future admin pages can use this workflow support to:

- render stage badges
- order timeline steps
- filter procurement records by stage
- show pre-approval versus post-approval state

The current quote and invoice runtime behavior should remain unchanged until a dedicated procurement page implementation consumes this support layer.

