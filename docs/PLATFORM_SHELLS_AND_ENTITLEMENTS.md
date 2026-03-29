# Platform Shells And Entitlements

Date: March 29, 2026

This note ties together the platform pieces we are building while waiting for George's tyre sheet.

It is meant to keep the architecture easy to explain to George and easy to build against.

## The Three Main Shells

### 1. Super Admin Shell

This is the control tower for the business.

It manages:

- accounts
- subscriptions
- reports add-on setup
- platform-wide analytics
- system errors and audit visibility

It does **not** manage supplier products or supplier inventory.

That is deliberate.

### 2. Procurement Shell

This is the retailer admin sourcing flow.

It manages:

- manual supplier selection
- procurement request building
- supplier quote / approval
- invoice conversion
- stock deduction after approval
- stock reversal on cancellation

This is not a retail payment checkout.

It is a sourcing and approval flow inside admin.

### 3. Supplier Intake Shell

This is the supplier-facing operational side.

It manages:

- supplier product and inventory operations
- quote intake
- invoice flow
- warehouse-based stock handling
- supplier view-store preview mode with cart and checkout disabled

For suppliers, the same storefront can be reused in read-only mode.

## Entitlement Groundwork

The platform should treat subscriptions as entitlements, not just labels.

That means the account context should know:

- whether the business is retailer, supplier, or both
- whether wholesale is enabled
- whether reports are enabled
- how many supplier connections are allowed
- which pricing levels are available
- whether own products and inventory can be managed

For mixed retailer-supplier accounts:

- one shared stock pool
- one combined main subscription
- reports as a separate add-on

## How These Pieces Connect

The clean build order is:

1. account context
2. subscription and entitlement checks
3. super-admin governance views
4. procurement request flow in retailer admin
5. supplier intake and approval flow
6. tyre catalog schema from George's sheet

This keeps each shell focused on its own job.

## What We Are Waiting For

The only material product input still pending is George's tyre data sheet.

Once that arrives, we can lock:

- tyre fields
- import mapping
- launch product grid columns
- search and filtering details

## Bottom Line

The platform should be built as one shared foundation with three focused shells:

- super admin for governance
- retailer procurement for sourcing
- supplier intake for operations

Entitlements sit underneath all three so the platform can scale cleanly without mixing responsibilities.
