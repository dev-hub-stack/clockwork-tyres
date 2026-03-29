# Super Admin Implementation Map

Date: March 29, 2026

This note maps George's super-admin mockups to the new Clockwork Tyres platform architecture.

It is intentionally narrow in scope:

- super admin only
- platform governance only
- no supplier product editing
- no supplier inventory editing

## What The Mockups Are Asking For

From the super-admin screens, the key intent is:

- a platform-wide dashboard with operational metrics
- a searchable account directory
- a system error / maintenance log
- reports shortcuts for sales, customer, product, and inventory analytics
- a clear bridge back to the retail store experience

The mockups show a governance console, not a merchandising console.

That is an important distinction for the architecture.

## Super Admin Scope In The New Platform

Super admin should own:

- accounts
- subscription state
- reports add-on setup
- platform-wide analytics
- system visibility and audit oversight

Super admin should not own:

- supplier product creation
- supplier product editing
- supplier inventory editing
- warehouse-level stock maintenance
- customer-facing checkout behavior

## Mockup To Architecture Mapping

### 1. Dashboard Metrics

The dashboard surface in the mockup maps to a platform analytics home page.

Relevant metrics should be derived from account and subscription data, such as:

- daily active users
- registered wholesalers
- registered retailers
- inactive accounts
- subscribed wholesalers
- subscribed retailers
- monthly subscription revenue
- retail / wholesale order counts
- search activity counts
- report usage counts

This should be an aggregation layer over account, subscription, and activity data.

It should not be hard-coded to product inventory tables.

### 2. Account Directory

The users screen in the mockup maps to a super-admin account table.

The table should show:

- account name
- account type
- subscription status
- wholesale enabled yes/no
- report add-on status
- customer count
- supplier count
- subscriber since
- last activity
- status

Super admin actions should be limited to governance actions such as:

- view account
- activate / suspend account
- change subscription
- enable wholesale
- manage reports add-on
- review audit trail

### 3. Reports Area

The reports section in the mockup maps to platform analytics entry points.

The platform should expose reports grouped around:

- sales dashboard
- customer analytics
- product performance
- user activity log
- payment history log if retained as a non-settlement history view

For the launch scope, reports should stay read-oriented.

They are for oversight, not direct operational editing.

### 4. Error Log

The error log screen in the mockup maps to a platform operations / maintenance view.

This should show:

- application errors
- integration failures
- import failures
- sync failures
- notable workflow exceptions

This supports support, QA, and platform maintenance.

### 5. Go To Retail Store

The header bridge in the mockup maps to a quick context switch into the retail storefront.

That link should preserve the concept of moving from governance into the customer-facing experience.

It should not imply super admin can edit retail product data from the same screen.

## Recommended Architecture Shape

The clean platform model is:

- `accounts`
- `account_users`
- `account_subscriptions`
- `account_capabilities`
- `account_supplier_links`
- `platform_metrics`
- `platform_events`
- `audit_logs`
- `report_tiers`
- `report_entitlements`

This keeps the super-admin console grounded in governance data rather than catalog data.

## What To Build In The Super Admin Console

Recommended screens:

1. Overview dashboard
2. Accounts list
3. Account detail
4. Subscription management
5. Reports add-on management
6. Analytics views
7. Error log
8. Audit log

Recommended actions:

- create account
- activate account
- suspend account
- enable wholesale
- assign subscription
- configure reports tier
- inspect platform activity

## What Not To Build Into Super Admin

The super-admin surface should not contain:

- supplier product catalog editing
- supplier inventory editing
- warehouse stock entry
- procurement execution
- checkout fulfillment
- storefront merchandising controls

Those belong in supplier admin, retailer admin, or storefront flows.

## Why This Matters

The mockup language is very clear: George wants a control tower for the business, not another product-management screen.

If we keep super admin focused on accounts, subscriptions, analytics, and support visibility, we get:

- cleaner architecture
- fewer permission overlaps
- better auditability
- easier future scaling
- less duplication between super admin and supplier admin

## Bottom Line

The super-admin implementation should be a governance console over the Clockwork Tyres platform.

It should manage accounts, subscriptions, reports add-ons, and analytics, while deliberately staying out of supplier product and inventory editing.
