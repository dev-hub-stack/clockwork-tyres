# Remaining Questions For George

Date: March 29, 2026

These are the remaining questions after your latest answers. They are much smaller now and mostly affect implementation details, not overall direction.

## 1. If One Business Is Both Retailer and Supplier

- is inventory one shared stock pool
- or do we need separate retail and wholesale stock buckets

## 2. Pricing For Both-Mode Accounts

- should a `both` account have:
  - retail selling price
  - wholesale supply price
- or is there only one price model

## 3. Same Tyre From Multiple Suppliers

- if the same tyre exists from own stock plus multiple suppliers, should the storefront show:
  - one merged product entry
  - or multiple separate entries

## 4. Hidden Supplier Allocation Rule

- if supplier identity stays hidden on the storefront, how should supplier selection happen behind the scenes:
  - preferred supplier ranking
  - lowest cost
  - best stock
  - manual retailer choice in admin

## 5. Reserved Stock Release Rule

- once quote is approved and stock is reserved, what releases that stock:
  - invoice cancellation
  - expiry timeout
  - manual admin action
  - fulfillment only

## 6. Reports Tier Definition

- when you say `250 customers`, does that mean:
  - total stored customers
  - active customers
  - ordering customers
  - monthly active customers

## 7. Subscription Structure For Both-Mode Accounts

- if one business is both retailer and supplier, do they have:
  - one combined subscription
  - or separate retailer and supplier subscriptions

## 8. Tire Data Sheet

- please send the final tire import sheet
- once shared, we will map it into the launch schema and import process
