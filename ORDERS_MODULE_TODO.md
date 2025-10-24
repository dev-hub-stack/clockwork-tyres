# Orders & Quotes Module - Implementation Checklist

**Module:** Orders & Quotes (Unified Table Approach)  
**Estimated Time:** 5 days (Week 5)  
**Priority:** CRITICAL  
**Date Created:** October 24, 2025

---

## 📋 Implementation Checklist (35 Tasks)

### Phase 1: Module Setup & Database (Tasks 1-8)

- [ ] **Task 1:** Create Orders Module Structure
  - Create `app/Modules/Orders/` directory
  - Create subdirectories: `Models/`, `Services/`, `Actions/`, `Enums/`, `DTOs/`, `Events/`, `Listeners/`
  - Follow existing module pattern (Products, AddOns, Customers)

- [ ] **Task 2:** Create Enums for Orders Module
  - `app/Modules/Orders/Enums/DocumentType.php` - Quote, Invoice, Order
  - `app/Modules/Orders/Enums/QuoteStatus.php` - Draft, Sent, Approved, Rejected, Converted
  - `app/Modules/Orders/Enums/OrderStatus.php` - Pending, Processing, Shipped, Completed, Cancelled
  - `app/Modules/Orders/Enums/PaymentStatus.php` - Pending, Partial, Paid, Refunded

- [ ] **Task 3:** Create orders table migration ⚠️ CRITICAL
  ```bash
  php artisan make:migration create_orders_table
  ```
  **Fields:**
  - `document_type` VARCHAR(20) DEFAULT 'quote' - Quote/Invoice/Order discriminator
  - `quote_number` VARCHAR(50) - For quotes
  - `quote_status` VARCHAR(20) - Quote workflow
  - `order_number` VARCHAR(50) UNIQUE - For all documents
  - `order_status` VARCHAR(30) - Order workflow
  - `payment_status` VARCHAR(20)
  - `customer_id` BIGINT FK
  - `warehouse_id` BIGINT FK
  - `external_order_id` VARCHAR(100) - From TunerStop/Wholesale
  - `external_source` VARCHAR(20) - retail/wholesale/manual
  - Financial: `sub_total`, `tax`, `vat`, `shipping`, `discount`, `total`, `currency`
  - `tax_inclusive` BOOLEAN - Tax calculation mode
  - Vehicle: `vehicle_year`, `vehicle_make`, `vehicle_model`, `vehicle_sub_model`
  - Conversion: `is_quote_converted` BOOLEAN, `converted_to_invoice_id` BIGINT
  - Dates: `issue_date`, `valid_until`, `sent_at`, `approved_at`
  - Shipping: `tracking_number`, `shipping_carrier`
  - `representative_id` BIGINT FK
  - `order_notes` TEXT
  - Timestamps: `created_at`, `updated_at`, `deleted_at` (soft delete)

- [ ] **Task 4:** Create order_items table migration ⚠️ CRITICAL
  ```bash
  php artisan make:migration create_order_items_table
  ```
  **Fields:**
  - `order_id` BIGINT FK
  - Product references: `product_id`, `product_variant_id`, `addon_id` (nullable)
  - **JSONB Snapshots:** `product_snapshot`, `variant_snapshot`, `addon_snapshot`
  - Denormalized: `sku`, `product_name`, `product_description`, `brand_name`, `model_name`
  - Pricing: `quantity`, `unit_price`, `tax_inclusive`, `discount`, `tax_amount`, `line_total`
  - Fulfillment: `allocated_quantity`, `shipped_quantity`

- [ ] **Task 5:** Create order_item_quantities table migration
  ```bash
  php artisan make:migration create_order_item_quantities_table
  ```
  **Fields:**
  - `order_item_id` BIGINT FK
  - `warehouse_id` BIGINT FK
  - `quantity` INT - Allocated from this warehouse

- [ ] **Task 6:** Create Order model
  - File: `app/Modules/Orders/Models/Order.php`
  - Fillable: All fields from migration
  - Casts: Enums, dates, booleans, decimals
  - Relationships:
    - `customer()` - belongsTo Customer
    - `warehouse()` - belongsTo Warehouse
    - `representative()` - belongsTo User
    - `items()` - hasMany OrderItem
    - `itemQuantities()` - hasManyThrough OrderItemQuantity
    - `convertedInvoice()` - belongsTo Order (self-reference)
  - Scopes:
    - `scopeQuotes()` - where document_type = 'quote'
    - `scopeInvoices()` - where document_type = 'invoice'
    - `scopeOrders()` - where document_type = 'order'
  - Soft deletes enabled

- [ ] **Task 7:** Create OrderItem model
  - File: `app/Modules/Orders/Models/OrderItem.php`
  - Casts: JSONB snapshots to array
  - Relationships:
    - `order()` - belongsTo Order
    - `product()` - belongsTo Product
    - `productVariant()` - belongsTo ProductVariant
    - `addon()` - belongsTo Addon
    - `quantities()` - hasMany OrderItemQuantity
  - Accessors:
    - `getProductSnapshotAttribute()` - Parse JSON
    - `getVariantSnapshotAttribute()` - Parse JSON
    - `getAddonSnapshotAttribute()` - Parse JSON
  - Methods:
    - `calculateLineTotal()` - Based on tax_inclusive

- [ ] **Task 8:** Create OrderItemQuantity model
  - File: `app/Modules/Orders/Models/OrderItemQuantity.php`
  - Relationships:
    - `orderItem()` - belongsTo OrderItem
    - `warehouse()` - belongsTo Warehouse
  - Validation: quantity > 0

---

### Phase 2: Snapshot Services (Tasks 9-11)

- [ ] **Task 9:** Enhance ProductSnapshotService
  - File: `app/Services/ProductSnapshotService.php` (already exists)
  - Method: `createSnapshot(Product $product): array`
  - Returns:
    ```php
    [
        'product_id' => $product->id,
        'brand_id' => $product->brand_id,
        'brand_name' => $product->brand->name,
        'model_id' => $product->model_id,
        'model_name' => $product->model->name,
        'finish_id' => $product->finish_id,
        'finish_name' => $product->finish->name,
        'retail_price' => $product->retail_price,
        'wholesale_price' => $product->wholesale_price,
        'description' => $product->description,
        'images' => $product->images->pluck('url')->toArray(),
        'snapshot_date' => now()->toISOString(),
    ]
    ```

- [ ] **Task 10:** Create VariantSnapshotService ⚠️ NEW
  - File: `app/Services/VariantSnapshotService.php`
  - Method: `createSnapshot(ProductVariant $variant): array`
  - Returns:
    ```php
    [
        'variant_id' => $variant->id,
        'sku' => $variant->sku,
        'size' => $variant->size,
        'bolt_pattern' => $variant->bolt_pattern,
        'offset' => $variant->offset,
        'center_bore' => $variant->center_bore,
        'finish' => $variant->finish,
        'price' => $variant->price,
        'quantity_at_order' => $variant->total_quantity,
        'specifications' => $variant->specifications,
        'snapshot_date' => now()->toISOString(),
    ]
    ```

- [ ] **Task 11:** Enhance AddonSnapshotService
  - File: `app/Services/AddonSnapshotService.php` (already exists from AddOns module)
  - Verify format matches OrderItem requirements
  - Method: `createSnapshot(Addon $addon): array`

---

### Phase 3: Business Logic Services (Tasks 12-15)

- [ ] **Task 12:** Create OrderService
  - File: `app/Modules/Orders/Services/OrderService.php`
  - **Methods:**
    - `createOrder(OrderData $data): Order`
      - Create order with items
      - Call snapshot services for each item
      - Calculate totals
      - Fire OrderCreated event
    - `calculateTotals(Order $order): void`
      - Handle tax_inclusive vs tax_exclusive
      - Update sub_total, tax, vat, shipping, discount, total
    - `updateStatus(Order $order, OrderStatus $status): bool`
      - Validate status transition
      - Fire OrderStatusChanged event
    - `addItem(Order $order, $product, int $quantity, ?int $warehouseId): OrderItem`
      - Create item with snapshots
      - Apply dealer pricing
    - `removeItem(OrderItem $item): bool`
      - Remove item and quantities

- [ ] **Task 13:** Create QuoteConversionService ⚠️ CRITICAL
  - File: `app/Modules/Orders/Services/QuoteConversionService.php`
  - **Method:** `convertQuoteToInvoice(Order $quote): Order`
    ```php
    public function convertQuoteToInvoice(Order $quote): Order
    {
        // Validate
        if ($quote->document_type !== DocumentType::QUOTE) {
            throw new \Exception('Can only convert quotes');
        }
        
        if ($quote->quote_status !== QuoteStatus::APPROVED) {
            throw new \Exception('Quote must be approved first');
        }
        
        // Convert
        $quote->update([
            'document_type' => DocumentType::INVOICE,
            'quote_status' => QuoteStatus::CONVERTED,
            'is_quote_converted' => true,
            'converted_to_invoice_id' => $quote->id,
        ]);
        
        // Fire event
        event(new QuoteConverted($quote));
        
        return $quote->fresh();
    }
    ```

- [ ] **Task 14:** Create OrderSyncService
  - File: `app/Modules/Orders/Services/OrderSyncService.php`
  - **Method:** `syncFromExternal(array $orderData, string $source): Order`
    - Parse TunerStop/Wholesale order format
    - Create or find customer
    - Create Order with document_type='quote', quote_status='draft'
    - Set external_order_id and external_source
    - Create order items with snapshots
    - Apply dealer pricing via DealerPricingService
    - Handle product variants and addons
  - **Source types:** 'retail' (TunerStop), 'wholesale' (Wholesale platform)

- [ ] **Task 15:** Create OrderFulfillmentService
  - File: `app/Modules/Orders/Services/OrderFulfillmentService.php`
  - **Methods:**
    - `allocateInventory(Order $order): void`
      - Find nearest warehouse or use order->warehouse_id
      - Create OrderItemQuantity records
      - Update ProductInventory quantities
      - Create InventoryLog entries (action='sale')
    - `validateInventoryAvailability(Order $order): bool`
      - Check if sufficient stock exists
    - `releaseInventory(Order $order): void`
      - Delete OrderItemQuantity records
      - Restore ProductInventory quantities
      - Create InventoryLog entries (action='return')

---

### Phase 4: DTOs and Actions (Tasks 16-17)

- [ ] **Task 16:** Create Order DTOs
  - `app/Modules/Orders/DTOs/OrderData.php`
  - `app/Modules/Orders/DTOs/OrderItemData.php`
  - `app/Modules/Orders/DTOs/OrderSyncData.php`
  - Include validation rules, from() factory methods, toArray()

- [ ] **Task 17:** Create Order Actions
  - `app/Modules/Orders/Actions/CreateOrderAction.php`
  - `app/Modules/Orders/Actions/UpdateOrderStatusAction.php`
  - `app/Modules/Orders/Actions/AllocateInventoryAction.php`
  - `app/Modules/Orders/Actions/CancelOrderAction.php`
  - `app/Modules/Orders/Actions/SendQuoteAction.php`
  - `app/Modules/Orders/Actions/ApproveQuoteAction.php`

---

### Phase 5: Filament Admin Interface (Tasks 18-21)

- [ ] **Task 18:** Create OrderResource form
  - File: `app/Filament/Resources/OrderResource.php`
  - **Form sections:**
    1. Document Type & Customer
    2. Order Items (Repeater with product/addon selection)
    3. Financial Details (totals, tax, shipping, discount)
    4. Vehicle Information
    5. Payment & Shipping
    6. Notes
  - Dynamic fields based on document_type
  - Real-time price calculation

- [ ] **Task 19:** Create OrderResource table
  - **Columns:** Date, Type, Number, Customer, Product, Vehicle, Tracking, Payment, Status, Total
  - **Filters:** document_type, order_status, payment_status, date range, customer, representative, warehouse
  - **Bulk actions:** Update status, Export PDF
  - **Search:** order_number, quote_number, customer name

- [ ] **Task 20:** Create custom Order view page
  - File: `app/Filament/Resources/OrderResource/Pages/ViewOrder.php`
  - **Sections:**
    - Header with status badges
    - Customer details with address
    - Order items table with snapshots
    - Warehouse allocations
    - Payment history
    - Shipping information
    - Timeline of status changes
  - **Action buttons based on state:**
    - Quote: Send, Approve, Convert to Invoice
    - Invoice: Allocate Inventory, Mark Processing, Ship, Complete
    - All: Download PDF, Send Email, Cancel

- [ ] **Task 21:** Implement Convert to Invoice action
  - Custom Filament action in ViewOrder page
  - Visible only when: document_type='quote' AND quote_status='approved'
  - Confirmation modal: "Convert quote #{quote_number} to invoice?"
  - Calls QuoteConversionService->convertQuoteToInvoice()
  - Shows success notification
  - Refreshes page to invoice view
  - Cannot be undone - add warning

---

### Phase 6: Events & Observers (Tasks 22-24)

- [ ] **Task 22:** Create Order Events
  - `app/Modules/Orders/Events/OrderCreated.php`
  - `app/Modules/Orders/Events/OrderStatusChanged.php`
  - `app/Modules/Orders/Events/QuoteConverted.php`
  - `app/Modules/Orders/Events/OrderShipped.php`
  - `app/Modules/Orders/Events/OrderCompleted.php`
  - `app/Modules/Orders/Events/OrderCancelled.php`

- [ ] **Task 23:** Create Order Listeners
  - `app/Modules/Orders/Listeners/SendOrderConfirmationEmail.php`
  - `app/Modules/Orders/Listeners/UpdateInventoryOnOrder.php`
  - `app/Modules/Orders/Listeners/NotifyWarehouse.php`
  - `app/Modules/Orders/Listeners/SendQuoteEmail.php`
  - Register in `EventServiceProvider`

- [ ] **Task 24:** Create OrderObserver
  - File: `app/Observers/OrderObserver.php`
  - **Methods:**
    - `creating()` - Generate order_number/quote_number
    - `updated()` - Log status changes
    - `deleted()` - Release inventory if not completed
  - Register in `AppServiceProvider`
  - **Number format:** ORD-2025-XXXX, QUO-2025-XXXX

---

### Phase 7: Testing (Tasks 25-30)

- [ ] **Task 25:** Run migrations and test database
  - `php artisan migrate`
  - Verify 3 tables created
  - Check foreign keys
  - Test JSONB columns
  - Seed test data

- [ ] **Task 26:** Test Quote Creation workflow
  - Create quote via OrderResource
  - Add products and addons
  - Verify snapshots created
  - Verify dealer pricing applied
  - Test quote approval
  - Test send quote email

- [ ] **Task 27:** Test Quote to Invoice Conversion ⚠️ CRITICAL
  - Approve a quote
  - Click 'Convert to Invoice'
  - Verify document_type changes
  - Verify snapshots preserved
  - Test conversion validation (can't convert non-approved)

- [ ] **Task 28:** Test External Order Sync
  - Create sample TunerStop order JSON
  - Call OrderSyncService
  - Verify Order created as quote
  - Verify customer linked
  - Test wholesale format

- [ ] **Task 29:** Test Inventory Allocation
  - Create invoice from quote
  - Allocate inventory
  - Verify quantities reduced
  - Verify inventory logs created
  - Test insufficient stock handling
  - Test inventory release on cancellation

- [ ] **Task 30:** Test Order Status Workflow
  - Complete workflow: Draft → Sent → Approved → Processing → Shipped → Completed
  - Verify status transitions
  - Test order cancellation
  - Verify inventory released

---

### Phase 8: Notifications & Dashboard (Tasks 31-33)

- [ ] **Task 31:** Create Order PDF templates
  - `resources/views/pdf/quote.blade.php`
  - `resources/views/pdf/invoice.blade.php`
  - Include company branding, customer details, items, totals
  - Use DomPDF or similar

- [ ] **Task 32:** Create Email Notifications
  - `app/Mail/QuoteSent.php`
  - `app/Mail/InvoiceCreated.php`
  - `app/Mail/OrderShipped.php`
  - Include PDF attachments
  - Use queue for sending

- [ ] **Task 33:** Add Order widgets to Dashboard
  - `app/Filament/Widgets/OrderStatsWidget.php` - Stats cards
  - `app/Filament/Widgets/RecentOrdersWidget.php` - Recent orders table
  - Register in AdminPanelProvider

---

### Phase 9: Documentation & Commit (Tasks 34-35)

- [ ] **Task 34:** Document Orders Module
  - Update `ARCHITECTURE_ORDERS_MODULE.md`
  - Create `ORDERS_MODULE_COMPLETE.md`
  - Update `PROGRESS.md`
  - Add code examples and screenshots

- [ ] **Task 35:** Commit Orders Module
  - Git add all changes
  - Comprehensive commit message
  - Tag as `v0.5.0-orders-complete`

---

## 🎯 Success Criteria

- [ ] All 3 database tables created and working
- [ ] Quote creation working with snapshots
- [ ] Quote to invoice conversion tested and working
- [ ] External order sync tested (TunerStop format)
- [ ] Inventory allocation and release working
- [ ] Complete status workflow tested
- [ ] Email notifications sending
- [ ] PDF generation working
- [ ] Dashboard widgets showing correct data
- [ ] Documentation complete

---

## 📊 Estimated Timeline

| Phase | Tasks | Time | Day |
|-------|-------|------|-----|
| Phase 1: Setup & DB | 1-8 | 1 day | Day 1 |
| Phase 2: Snapshots | 9-11 | 0.5 day | Day 1 |
| Phase 3: Services | 12-15 | 1 day | Day 2 |
| Phase 4: DTOs/Actions | 16-17 | 0.5 day | Day 2 |
| Phase 5: Filament UI | 18-21 | 1.5 days | Day 3-4 |
| Phase 6: Events | 22-24 | 0.5 day | Day 4 |
| Phase 7: Testing | 25-30 | 1 day | Day 5 |
| Phase 8: Extras | 31-33 | 0.5 day | Day 5 |
| Phase 9: Docs | 34-35 | 0.5 day | Day 5 |

**Total: 5 working days**

---

## ⚠️ Critical Notes

1. **UNIFIED TABLE:** Remember document_type discriminator - NOT separate tables!
2. **SNAPSHOTS:** JSONB snapshots are critical for historical accuracy
3. **TAX INCLUSIVE:** Handle both tax_inclusive=true and tax_inclusive=false
4. **CONVERSION:** Quote to Invoice is just changing document_type field
5. **DEALER PRICING:** Apply pricing hierarchy: Variant → Model → Brand → Default
6. **INVENTORY:** Allocate on 'processing', release on 'cancelled'

---

## 🚀 Ready to Start!

Begin with Task 1 and work sequentially through each phase. Mark tasks as completed in the checklist above.
