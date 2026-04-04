# Clockwork Tyres UAT Smoke Checklist

Date: April 4, 2026

Use this checklist during pilot/UAT to validate the phase 1 business-critical flows.

## Super Admin

1. Log in as super admin.
2. Open `Business Accounts`.
3. Create or edit a retailer, supplier, and `both` account.
4. Confirm plan language matches George’s agreed structure.
5. Open a business account `View` page and verify:
   - connected suppliers
   - connected retailers
   - products listed
   - warehouses
   - users
   - retail transaction summary
   - wholesale transaction summary

## Retailer Admin

1. Switch into a retailer business account.
2. Open `Explore Suppliers` and send a supplier connection request if needed.
3. Open `My Suppliers` and confirm approved suppliers are visible.
4. Open `Procurement` and search tyre stock by:
   - width
   - height
   - rim size
   - minimum quantity
   - supplier
5. Add grouped results to the procurement cart and place the order.

## Supplier Admin

1. Switch into a supplier business account.
2. Open `Quotes & Proformas`.
3. Confirm the procurement-linked request appears.
4. Run each lifecycle path at least once:
   - start supplier review
   - quote/send
   - request revision
   - reject / can’t fulfill
   - approve into invoice

## Inventory / Images

1. Open `Tyres Grid` and confirm staged/imported tyre rows exist.
2. Open `Tyre Images` and upload or edit a tyre image.
3. Confirm tyre images resolve through the same S3-backed pattern used by products/addons.
4. Open `Inventory Movement Log`.
5. Verify `All / Products / Tyres / Addons` filtering works and that business scope is correct.

## Storefront

1. Log in with a retailer owner account.
2. Confirm counter-only access after login.
3. Search by vehicle.
4. Search by size.
5. Add a tyre to cart.
6. Complete checkout.
7. Confirm the new order appears in account orders.

## Expected Outcome

The build is pilot-ready when every step above completes without:

- unauthorized cross-business data visibility
- missing images on core tyre rows
- procurement lifecycle dead ends
- catalog/cart/checkout regressions
- super admin seeing retailer/supplier operational modules
