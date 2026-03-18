# Implementation Plan — Reporting CRM & Wholesale Portal
**Date:** 2026-03-18  
**Source:** Meeting with George Varkey (2026-03-17)

---

## Priority Order

| # | Task | Type | Effort | Priority |
|---|------|------|--------|----------|
| 1 | Data Cleanup — Invoices & Orders | Data | Low | 🔴 Immediate |
| 2 | Data Cleanup — Inventory Grid | Data | Low | 🔴 Immediate |
| 3 | Hold Email Notifications During Data Entry | Backend | Low | 🔴 Immediate |
| 4 | Wholesale — Search by Size | Feature | Medium | 🔴 Pre-demo |
| 5 | Wholesale — Checkout Fix | Bug Fix | Medium | 🔴 Pre-demo |
| 6 | Special Order for Non-Tracked Products | Feature | Medium | 🟠 Soon |
| 7 | Wholesale Account Registration Flow | Feature | Medium | 🟠 Soon |
| 8 | User Activity Log | Feature | Medium | 🟡 Next Sprint |
| 9 | Payment History Log | Feature | Low | 🟡 Next Sprint |
| 10 | Separate Abandoned Carts | Feature | Low | 🟡 Next Sprint |
| 11 | Bulk Invoice Import with AI | Feature | High | 🟢 Planned |
| 12 | AI Product Pricing / Data Formatter | Feature | High | 🟢 Planned |
| 13 | Reporting CRM Performance Lag | Investigation | Medium | 🟢 Ongoing |

---

## Status Update (2026-03-18)

- [x] **Task 3 — Hold Email Notifications** completed: suppression toggle live in Settings; currently **ON** (enabled via `php artisan email:suppress on` — George to confirm when to disable after data entry).
- [x] **Task 4 — Wholesale Search by Size** completed: backend + frontend wired, staggered front/rear layout added, stock modal restored.
- [x] **Task 5 — Wholesale Checkout Fix (Stripe)** completed: staggered cart payload bugs fixed; Stripe auth-on-checkout + capture-on-ship + manual CRM capture + webhook + PostPay removal all committed (`aa8c399`, `5ff0247`). 4/4 integration tests passing.
- [x] **Task 6 — Special Order for Non-Tracked Products** completed: non-tracked wholesale products remain visible, show as Special Order, and can be added to cart and checked out.

---

## 1. Data Cleanup — Invoices & Orders

**Goal:** Clear all test/imported data so the team can start entering real data from Jan 1, 2026.

### Steps

- [ ] **Delete all invoices** that were imported from Tunerstop/Kostop in the Invoices section.
- [ ] **Move all Tunerstop/Kostop online orders to Quotes** — do not delete them, just relocate to Quotes/Pro-formas.
- [ ] **Delete all test transactions** from Quotes/Pro-formas (~38 test entries created during testing).
- [ ] Confirm with George once complete so he can begin entering real invoice data.

### Notes
- If possible, tag moved orders as "Retail - Imported" to differentiate from wholesale quotes.
- Do NOT delete the order records — only the imported invoices should be purged.

---

## 2. Data Cleanup — Inventory Grid

**Goal:** Reset all inventory test data so real opening stock from Jan 1, 2026 can be entered cleanly.

### Steps

- [ ] **Clear all Damage Stock** entries (test data only).
- [ ] **Clear all Consignment** entries (test data only).
- [ ] **Clear all Out / Written-off** stock entries (test data only).
- [ ] **Clear all Warranty Claims** entries (test data only).
- [ ] **Clear the full Movement Log** — wipe all movement history so Jan 1 opening stock is the baseline.

### Notes
- Confirm with George before clearing — do a database backup first.
- Real opening inventory will be entered by the team after cleanup is complete.

---

## 3. Hold Email Notifications During Data Entry

**Goal:** Prevent customers from receiving notification emails while historical invoices (Jan 1 – today) are being backfilled.

**Status:** ✅ Complete — suppression is currently **ON**.

### Steps

- [x] Add a **global toggle in Settings** — `Notifications` section in CRM Settings page.
- [x] When the toggle is ON: all outbound customer emails are logged internally but not sent.
- [x] When the toggle is OFF (default): normal email behavior resumes.
- [ ] George to confirm when all historical data is entered → run `php artisan email:suppress off` to re-enable.

### CLI Toggle
```bash
# Check current status
php artisan email:suppress

# Enable suppression (block emails)
php artisan email:suppress on

# Disable suppression (resume emails)
php artisan email:suppress off
```

### Notes
- System-wide. Applies to all quote, invoice, payment, shipping, and order status emails.
- All suppressed emails are logged in `laravel.log` with full context for audit trail.

---

## 4. Wholesale — Search by Size

**Goal:** Implement the "Search by Size" feature on the wholesale frontend.

**Status:** Completed on 2026-03-18.

### Steps

- [x] Review existing `searchSizes()` and `searchSizeParams()` API endpoints in `ProductController.php`.
- [x] Build / complete the frontend search-by-size component in the wholesale Angular app.
- [x] Wire up the API calls, filters, staggered front/rear result display, and stock modal.
- [x] Ensure results respect `available_on_wholesale = true`.
  - `track_inventory = false` products remain visible and are treated as **Special Order**, not filtered out.
- [x] Test end-to-end: size inputs → API → product listing.

---

## 5. Wholesale — Checkout Fix

**Goal:** Resolve the checkout issue preventing customers from completing orders.

**Status:** Core staggered cart bugs fixed; final order verification still pending.

### Steps

- [x] Debug the current checkout flow — identify where it breaks (cart validation, payment step, API error, etc.).
- [x] Test with standard and staggered wheel combinations.
- [x] Fix root cause in cart payload generation for rear wheel items and special-order cart labeling.
- [ ] Verify a fresh order is recorded correctly in the Reporting CRM.
- [ ] Confirm the order shows up under the correct section in Reporting after checkout.

---

## 6. Special Order for Non-Tracked Wholesale Products

**Goal:** Products marked `Wholesale = Yes` but `Track Inventory = No` should appear as **Special Order** instead of Out of Stock.

**Status:** Completed on 2026-03-18.

### Current Behaviour
| Wholesale | Track Inventory | Stock | Shows as |
|-----------|----------------|-------|----------|
| Yes | Yes | > 0 | In Stock (count shown) |
| Yes | Yes | 0 | Out of Stock |
| Yes | No | N/A | Out of Stock ❌ (wrong) |

### Required Behaviour
| Wholesale | Track Inventory | Stock | Shows as |
|-----------|----------------|-------|----------|
| Yes | Yes | > 0 | In Stock (count shown) |
| Yes | Yes | 0 | Out of Stock |
| Yes | No | N/A | **Special Order** ✅ |

### Steps

**Backend (`reporting-crm`)**
- [x] Update wholesale product API responses to include `track_inventory` flag in the payload.
- [x] Ensure products with `track_inventory = false` are NOT excluded from results (they should still appear as Special Order).
  - Removed wholesale `track_inventory = true` result filtering and kept `available_on_wholesale = true` as the scope filter.

**Frontend (`wholesale` Angular app)**
- [x] On the product card and product detail page:
  - If `track_inventory = false` → show **"Special Order"** badge/label.
  - Hide the stock count for these products.
  - Show a quantity input field (same as in-stock products).
  - "Add to Cart" button remains active.
- [x] On the cart/checkout page:
  - Clearly label special-order items.
  - Allow checkout to proceed normally.

### Notes
- The wholesale price/discount should still apply to special-order items.
- No stock deduction happens for special-order items (since inventory is not tracked).

---

## 7. Wholesale Account Registration Flow

**Goal:** Replace the current self-serve signup with a manual approval-based registration process.

### Current Flow
1. Customer fills out signup form → account created automatically → pending approval.

### New Flow
1. Customer fills out signup form → **contact email sent to George** (no account created yet).
2. George contacts the company offline.
3. George registers the customer manually in Reporting CRM (as a customer record).
4. George clicks **"Send Wholesale Invite"** button from the CRM customer record.
5. Customer receives an email with a link to **set their password**.
6. Customer sets password → can now log in to the wholesale portal.

### Steps

**Backend (`reporting-crm`)**
- [ ] Change the wholesale signup endpoint to send a notification email to admin instead of creating an account.
- [ ] Add a `Send Wholesale Invite` action on the Customer record in the CRM.
- [ ] Generate a secure, time-limited password-set token and email it to the customer.
- [ ] Create the password-set endpoint that validates the token and saves the password.
- [ ] Link the CRM customer record to the wholesale portal account.

**Frontend (`wholesale` Angular app)**
- [ ] Update the signup form confirmation message: "Thank you, we'll be in touch shortly."
- [ ] Create a **Set Password** page that handles the token from the invite email.

### Notes
- The invite email link should expire (e.g., 48 hours) for security.
- Admin notification email should include all submitted business details.

---

## 8. User Activity Log

**Goal:** Track every action performed by every logged-in user in the Reporting CRM.

### Events to Log
- Creating a quote or invoice
- Converting a quote to an invoice
- Recording a payment
- Adding a product
- Updating a product
- Adding inventory (stock in)
- Adjusting inventory (damage, consignment, write-off)
- User login / logout

### Steps

- [ ] Create an `activity_logs` database table:
  ```
  id, user_id, action, model_type, model_id, description, ip_address, created_at
  ```
- [ ] Create an `ActivityLog` model and a reusable service/trait to log events.
- [ ] Hook logging into all relevant controllers and actions.
- [ ] Build a UI page (under Reports or a dedicated "Logs" section):
  - Filterable by user, date range, action type.
  - Sortable by date descending.
  - Paginated list view.

---

## 9. Payment History Log

**Goal:** Per invoice, show a full timeline of all payment recordings — who recorded it and when.

### Steps

- [ ] Create a `payment_logs` table (or extend existing payments table):
  ```
  id, invoice_id, user_id, amount, payment_method, recorded_at, notes
  ```
- [ ] On every "Record Payment" action, write a row to this log.
- [ ] On the Invoice detail page, add a **Payment History** panel showing:
  - Date & time of recording
  - Amount recorded
  - Payment method
  - User who recorded it
- [ ] Add a standalone **Payment History Log** report page filterable by date range, showing all payments recorded across all invoices for that period.

---

## 10. Separate Abandoned Carts from Confirmed Orders

**Goal:** In the Quotes section, clearly distinguish wholesale abandoned carts from real confirmed/pending orders.

### Steps

- [ ] Add a `source` or `type` field to quotes:
  - `abandoned_cart` — created from wholesale checkout but never completed.
  - `confirmed_order` — customer completed checkout.
  - `manual` — created by staff in the CRM.
- [ ] In the Quotes list view, add a **Type** column and a filter dropdown.
- [ ] Visually differentiate abandoned carts (e.g., grey row, "Abandoned" badge).
- [ ] Optionally: move abandoned carts to a separate tab or sub-section.

---

## 11. Bulk Invoice Import with AI Assistance

**Goal:** Allow bulk import of historical invoices (Jan 1, 2026 onwards) so the team does not have to enter them one by one.

### Steps

- [ ] Define the expected import format (CSV/Excel) with George — agree on required columns.
- [ ] Build an **Import Invoices** feature:
  - File upload UI in the CRM.
  - Backend validation and dry-run preview before committing.
  - Row-by-row error reporting for failed rows.
- [ ] **AI mapping layer**: accept raw exported data from various formats and use AI to map columns to the system schema automatically.
- [ ] Ensure inventory counts are **decremented correctly** when historical invoices are imported (respect product part numbers and quantities).
- [ ] Send import summary report on completion (rows imported, rows failed, inventory movements made).

### Notes
- George will send a bulk export from Jan 1 with all data fields.
- Must handle the opening stock → sales reduction correctly — sequence matters.
- Consider importing in chronological order to keep movement log accurate.

---

## 12. AI Product Pricing & Data Formatter

**Goal:** Automate the manual process of taking manufacturer price sheets (USD) and formatting them into the system's product grid with correct local pricing.

### Current Manual Process
1. Receive price sheet from US manufacturer (USD prices).
2. Manually calculate: `local price = USD price + shipping + customs duty + other add-ons`.
3. Manually format data to match the product grid columns (bolt pattern, offset range, etc.).
4. Upload to the product grid.

### New AI-Assisted Process
1. Upload raw manufacturer price sheet (CSV/Excel/PDF).
2. AI parses and maps columns (handles varying formats per manufacturer).
3. AI applies pricing formula (configurable: shipping %, duty %, markup %).
4. AI formats structured data (e.g., bolt pattern ranges, offset ranges) into the correct column format.
5. Preview formatted data before committing.
6. One-click import into product grid.

### Steps

- [ ] Collect 2–3 sample manufacturer data sheets from George for analysis.
- [ ] Define the pricing formula fields (duty %, shipping cost, markup %) — make them configurable per supplier or globally.
- [ ] Design the AI parsing pipeline (column detection, value normalization, range formatting).
- [ ] Build the upload UI and preview table.
- [ ] Build the confirm-and-import action.

### Notes
- George will send sample sheets to Ahmad for analysis.
- The key formatting challenge is converting raw spec values into range fields (e.g., `ET25 / ET40` → `ET25–40`).

---

## 13. Reporting CRM — Performance Lag

**Goal:** Investigate and resolve the general performance/lag issues in the Reporting CRM.

### Steps

- [ ] Profile slow pages — identify the top 3–5 slowest API calls or page loads.
- [ ] Check for missing database indexes on frequently queried columns (e.g., `product_id`, `invoice_id`, `status`, `created_at`).
- [ ] Review N+1 query problems in Eloquent relationships — add `with()` eager loading where missing.
- [ ] Review cache usage — ensure expensive queries are cached appropriately.
- [ ] Check server resources (memory, CPU) during peak usage.
- [ ] Implement fixes and measure improvement.

---

## Delivery Timeline (Target)

| Milestone | Tasks | Target |
|-----------|-------|--------|
| **Ready for demo** | Data cleanup (1–3), final checkout verification (5) | **Mon 2026-03-23** |
| **Wholesale v1 launch** | Wholesale Registration (7) | Week of 2026-03-30 |
| **CRM v1 stable** | Logs (8, 9), Abandoned Carts (10) | Week of 2026-04-06 |
| **Advanced features** | Bulk Import (11), AI Pricing (12), Lag fix (13) | TBD after data entry |
