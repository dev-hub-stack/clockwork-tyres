# Consignment & Warranty Modules - Implementation TODO

**Date Started:** October 27, 2025  
**Reference System:** C:\Users\Dell\Documents\Reporting  
**Pattern:** Following Quotes/Invoices implementation approach

---

## 🎯 Implementation Strategy

Following the same successful pattern used for Quotes & Invoices:
1. ✅ Study old Reporting system implementation
2. ⏳ Create database migrations
3. ⏳ Build Eloquent models with relationships
4. ⏳ Create enums for status management
5. ⏳ Build Filament resources (forms, tables, actions)
6. ⏳ Implement core business logic (record sale, record return, etc.)
7. ⏳ Add dynamic settings integration
8. ⏳ Create PDF templates
9. ⏳ Test end-to-end workflows

---

## 📋 PHASE 1: Consignment Module Backend

### Step 1: Database Migrations ⏳
- [ ] Create `consignments` table migration
  - consignment_number (unique)
  - customer_id, representative_id, warehouse_id
  - status (draft, sent, delivered, partially_sold, invoiced_in_full, returned, cancelled)
  - items_sent_count, items_sold_count, items_returned_count
  - Financial fields (subtotal, tax, discount, total)
  - Dates (issue_date, sent_at, delivered_at, expected_return_date)
  - Vehicle info (year, make, model, sub_model)
  - tracking_number, notes
  - Soft deletes, timestamps
  
- [ ] Create `consignment_items` table migration
  - consignment_id (foreign key)
  - product_variant_id (nullable - for internal products)
  - product_snapshot (JSONB - captures product/variant/addon data)
  - product_name, brand_name, sku, description
  - Quantity tracking (quantity_sent, quantity_sold, quantity_returned)
  - Pricing (price, actual_sale_price, tax_inclusive)
  - status (sent, sold, returned, cancelled)
  - Dates (date_sold, date_returned)
  - Soft deletes, timestamps

- [ ] Create `consignment_histories` table migration
  - consignment_id (foreign key)
  - action (sale_recorded, return_recorded, status_changed, etc.)
  - description (details of the action)
  - performed_by (user_id)
  - metadata (JSONB for additional data)
  - Timestamp

### Step 2: Eloquent Models ⏳
- [ ] Create `Consignment` model
  - Fillable fields
  - Casts (dates, decimals, JSONB)
  - Relationships:
    - belongsTo: customer, representative, warehouse, createdBy
    - hasMany: items, histories
    - belongsTo (optional): convertedInvoice
  - Scopes: active(), byStatus(), recent()
  - Methods:
    - calculateTotals()
    - updateItemCounts()
    - canRecordSale()
    - canRecordReturn()

- [ ] Create `ConsignmentItem` model
  - Fillable fields
  - Casts (decimals, JSONB, dates)
  - Relationships:
    - belongsTo: consignment, productVariant
  - Accessors: 
    - getProductSnapshot() - decode JSONB
    - getAvailableToSell()
    - getAvailableToReturn()
  - Methods:
    - calculateLineTotal()

- [ ] Create `ConsignmentHistory` model
  - Fillable fields
  - Casts (JSONB for metadata)
  - Relationships:
    - belongsTo: consignment, performedBy

### Step 3: Enums ⏳
- [ ] Create `ConsignmentStatus` enum
  - DRAFT = 'draft'
  - SENT = 'sent'
  - DELIVERED = 'delivered'
  - PARTIALLY_SOLD = 'partially_sold'
  - INVOICED_IN_FULL = 'invoiced_in_full'
  - RETURNED = 'returned'
  - CANCELLED = 'cancelled'
  - Methods:
    - color() - for badges
    - icon() - for UI
    - canTransitionTo()
    - nextStatuses()

- [ ] Create `ConsignmentItemStatus` enum
  - SENT = 'sent'
  - SOLD = 'sold'
  - RETURNED = 'returned'
  - CANCELLED = 'cancelled'

---

## 📋 PHASE 2: Consignment Module Services

### Step 4: Business Logic Services ⏳
- [ ] Create `ConsignmentService`
  - createConsignment(array $data): Consignment
  - addItems(Consignment $consignment, array $items): void
  - calculateTotals(Consignment $consignment): void
  - updateStatus(Consignment $consignment, string $status): void
  - recordSale(Consignment $consignment, array $soldItems): Invoice
  - recordReturn(Consignment $consignment, array $returnedItems): void
  - generateConsignmentNumber(): string
  - createInvoiceForSoldItems(Consignment $consignment, array $items): Invoice

- [ ] Create `ConsignmentSnapshotService`
  - captureProductSnapshot(ProductVariant $variant): array
  - Similar to ProductSnapshotService used in orders
  - Store: product details, variant specs, pricing, addons

---

## 📋 PHASE 3: Consignment Module UI (Filament)

### Step 5: ConsignmentResource ⏳
- [ ] Create `ConsignmentResource.php`
  - Multi-step form wizard:
    - Step 1: Consignment Information
      - customer_id (searchable select)
      - representative_id (sales rep)
      - warehouse_id
      - issue_date, expected_return_date
      - Vehicle info (year, make, model, sub_model)
      - status
    
    - Step 2: Line Items (Repeater)
      - product_variant_id (searchable with variant details)
      - OR custom product fields
      - quantity_sent
      - price (with dealer pricing integration)
      - tax_inclusive toggle
    
    - Step 3: Totals & Notes
      - subtotal (calculated)
      - tax (from settings)
      - discount
      - shipping_cost
      - total (calculated)
      - notes

  - Table columns:
    - consignment_number
    - customer.name
    - representative.name
    - status (badge with color)
    - items_sent_count
    - items_sold_count
    - items_returned_count
    - total
    - issue_date
    - Actions dropdown

  - Filters:
    - status (select)
    - customer (searchable)
    - representative
    - date range (issue_date)

  - Actions:
    - **Record Sale** ⭐ CRITICAL
    - **Record Return** ⭐ CRITICAL
    - Mark as Sent
    - Mark as Delivered
    - Cancel Consignment
    - View PDF
    - Email to Customer
    - Duplicate Consignment

### Step 6: Record Sale Action ⏳
- [ ] Create modal form with:
  - Select items to mark as sold (with available quantities)
  - For each item:
    - quantity_sold (max: available to sell)
    - actual_sale_price (can differ from original price)
  - Auto-create invoice option (checkbox)
  - Submit action:
    - Update consignment_items (quantity_sold, date_sold, status)
    - Update consignment (items_sold_count, status)
    - Create invoice if selected
    - Add history entry
    - Show success notification with invoice number

### Step 7: Record Return Action ⏳
- [ ] Create modal form with:
  - Select items to mark as returned (with available quantities)
  - For each item:
    - quantity_returned (max: available to return)
    - return_reason (optional textarea)
  - Add back to inventory option (checkbox)
  - Submit action:
    - Update consignment_items (quantity_returned, date_returned, status)
    - Update consignment (items_returned_count, status)
    - Add back to warehouse inventory if selected
    - Add history entry
    - Show success notification

---

## 📋 PHASE 4: Warranty Claims Module

### Step 8: Database Migrations ⏳
- [ ] Create `warranty_claims` table migration
  - claim_number (unique, auto-generated)
  - customer_id, representative_id
  - order_id (optional - if warranty is from a sale)
  - product_variant_id
  - product_snapshot (JSONB)
  - issue_description (text)
  - status (submitted, approved, rejected, in_progress, resolved)
  - resolution_notes (text)
  - claim_date, resolution_date
  - claimed_by, resolved_by
  - Attachments (photos, videos)
  - Soft deletes, timestamps

### Step 9: Warranty Models ⏳
- [ ] Create `WarrantyClaim` model
  - Fillable fields
  - Casts
  - Relationships:
    - belongsTo: customer, order, productVariant, representative
    - belongsTo: claimedBy, resolvedBy (User)
    - hasMany: attachments (if using separate table)
  - Scopes: active(), byStatus(), recent()
  - Methods:
    - canApprove()
    - canReject()
    - canResolve()

- [ ] Create `WarrantyStatus` enum
  - SUBMITTED = 'submitted'
  - APPROVED = 'approved'
  - REJECTED = 'rejected'
  - IN_PROGRESS = 'in_progress'
  - RESOLVED = 'resolved'
  - Methods:
    - color(), icon()
    - canTransitionTo()

### Step 10: WarrantyClaimResource ⏳
- [ ] Create `WarrantyClaimResource.php`
  - Form:
    - Claim Information
      - customer_id
      - order_id (optional)
      - product_variant_id
      - issue_description
      - claim_date
      - status
    - Attachments
      - FileUpload for photos/videos
    - Resolution (visible only for approved claims)
      - resolution_notes
      - resolution_date

  - Table columns:
    - claim_number
    - customer.name
    - product_name (from snapshot)
    - status (badge)
    - claim_date
    - resolution_date

  - Actions:
    - Approve Claim
    - Reject Claim
    - Mark as In Progress
    - Mark as Resolved
    - View Details
    - Email Customer

---

## 📋 PHASE 5: Integration & Testing

### Step 11: Dynamic Settings Integration ⏳
- [ ] Integrate with SettingsService for:
  - Tax rate (for consignment calculations)
  - Currency format
  - Default payment terms
  - Email templates
  - PDF headers/footers

### Step 12: PDF Generation ⏳
- [ ] Create PDF templates:
  - `consignment.blade.php` (consignment note)
  - `warranty_claim.blade.php` (warranty claim form)
- [ ] Add PDF preview actions
- [ ] Add PDF download actions
- [ ] Add PDF email actions

### Step 13: Email Notifications ⏳
- [ ] Create Mailable classes:
  - ConsignmentSent
  - ConsignmentInvoiced
  - WarrantyClaimSubmitted
  - WarrantyClaimApproved
  - WarrantyClaimResolved

### Step 14: Testing ⏳
- [ ] Test consignment workflow:
  - Create draft consignment
  - Add items
  - Send to customer
  - Record partial sale
  - Create invoice from sold items
  - Record return
  - Verify inventory updated

- [ ] Test warranty workflow:
  - Submit warranty claim
  - Approve claim
  - Mark as in progress
  - Resolve claim
  - Verify notifications

---

## 🎯 Success Criteria

### Consignment Module:
- ✅ Can create consignments with multiple items
- ✅ Can track items sent, sold, returned
- ✅ Can record sales and auto-create invoices
- ✅ Can record returns and update inventory
- ✅ Status workflow is clear and logical
- ✅ PDF generation works
- ✅ Email notifications work
- ✅ Integrates with dynamic settings

### Warranty Module:
- ✅ Can submit warranty claims
- ✅ Can attach photos/videos
- ✅ Can approve/reject claims
- ✅ Can track resolution
- ✅ Status workflow is clear
- ✅ Email notifications work

---

## 📊 Estimated Timeline

- **Phase 1 (Backend):** 1-2 days
- **Phase 2 (Services):** 1 day
- **Phase 3 (Consignment UI):** 2-3 days
- **Phase 4 (Warranty UI):** 1-2 days
- **Phase 5 (Integration & Testing):** 1-2 days

**Total:** 6-10 days

---

## 🔗 Reference Files

**Old System:**
- `C:\Users\Dell\Documents\Reporting\app\Models\Consignment.php`
- `C:\Users\Dell\Documents\Reporting\app\Models\ConsignmentItem.php`
- `C:\Users\Dell\Documents\Reporting\app\Http\Controllers\ConsignmentController.php`

**New System (to be created):**
- `app/Modules/Consignments/Models/Consignment.php`
- `app/Modules/Consignments/Models/ConsignmentItem.php`
- `app/Modules/Consignments/Services/ConsignmentService.php`
- `app/Filament/Resources/ConsignmentResource.php`

---

**Ready to start!** 🚀
