# Orders & Quotes Module - Complete Understanding

**Date:** October 24, 2025  
**Status:** ✅ CLARIFIED - Ready to Implement

---

## 🎯 CRITICAL UNDERSTANDING

### **The System Uses UNIFIED ORDERS TABLE**

❌ **WRONG:** Separate tables for quotes, orders, invoices  
✅ **CORRECT:** ONE `orders` table with `document_type` discriminator

---

## 📊 The Complete Workflow

### **1. Order Arrives from External System**
```
TunerStop/Wholesale Order Created
    ↓
Webhook/API Call to Reporting CRM
    ↓
OrderSyncService creates Order record:
    - document_type = 'quote'
    - quote_status = 'draft'
    - order_number = (from source system)
    - external_order_id = (TunerStop/Wholesale ID)
    - external_source = 'retail' OR 'wholesale'
```

### **2. Quote Review & Approval**
```
Admin reviews in CRM Dashboard
    ↓
Option A: Send Quote to Customer
    - quote_status = 'sent'
    - sent_at = now()
    - Email notification sent
    ↓
Option B: Approve Directly
    - quote_status = 'approved'
    - approved_at = now()
```

### **3. Quote to Invoice Conversion**
```
Admin clicks "Convert to Invoice"
    ↓
convertQuoteToInvoice() method executes:
    - document_type = 'invoice' ← KEY CHANGE!
    - quote_status = 'converted'
    - is_quote_converted = true
    - converted_to_invoice_id = (self reference)
```

### **4. Order Fulfillment**
```
Invoice Processing:
    - order_status = 'processing'
    ↓
Warehouse Operations:
    - Allocate inventory
    - Pick items
    - Pack shipment
    ↓
Shipping:
    - order_status = 'shipped'
    - tracking_number added
    - shipping_carrier added
    ↓
Completion:
    - order_status = 'completed'
    - Invoice finalized
    - Wafeq sync triggered
```

---

## 🗄️ Database Structure

### **Orders Table (UNIFIED)**

```sql
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    
    -- Document Discriminator (CRITICAL!)
    document_type VARCHAR(20) DEFAULT 'quote' 
        CHECK (document_type IN ('quote', 'invoice', 'order')),
    
    -- Quote-specific fields
    quote_number VARCHAR(50) UNIQUE,
    quote_status VARCHAR(20) 
        CHECK (quote_status IN ('draft', 'sent', 'approved', 'rejected', 'converted')),
    issue_date DATE,
    valid_until DATE,
    sent_at TIMESTAMP,
    approved_at TIMESTAMP,
    
    -- Order/Invoice fields
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_status VARCHAR(30) 
        CHECK (order_status IN ('pending', 'processing', 'shipped', 'completed', 'cancelled')),
    
    -- External sync
    external_order_id VARCHAR(100),
    external_source VARCHAR(20) CHECK (external_source IN ('retail', 'wholesale', 'manual')),
    
    -- Customer & relationships
    customer_id BIGINT REFERENCES customers(id),
    warehouse_id BIGINT REFERENCES warehouses(id),
    representative_id BIGINT REFERENCES users(id),
    
    -- Financial
    sub_total DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    vat DECIMAL(10,2) DEFAULT 0,
    shipping DECIMAL(10,2) DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    currency VARCHAR(10) DEFAULT 'AED',
    
    -- Payment
    payment_status VARCHAR(20) 
        CHECK (payment_status IN ('pending', 'partial', 'paid', 'refunded')),
    payment_method VARCHAR(100),
    
    -- Shipping
    tracking_number VARCHAR(255),
    shipping_carrier VARCHAR(100),
    
    -- Vehicle info
    vehicle_year VARCHAR(10),
    vehicle_make VARCHAR(100),
    vehicle_model VARCHAR(100),
    vehicle_sub_model VARCHAR(100),
    
    -- Conversion tracking
    is_quote_converted BOOLEAN DEFAULT FALSE,
    converted_to_invoice_id BIGINT REFERENCES orders(id),
    
    -- Metadata
    order_notes TEXT,
    channel VARCHAR(50),
    lead_source VARCHAR(100),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

### **Order Items Table**

```sql
CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT REFERENCES orders(id) ON DELETE CASCADE,
    
    -- Product references (can be product OR addon)
    product_id BIGINT REFERENCES products(id),
    product_variant_id BIGINT REFERENCES product_variants(id),
    addon_id BIGINT REFERENCES addons(id),
    
    -- CRITICAL: Snapshots (JSONB for historical data)
    product_snapshot JSONB,  -- Brand, model, finish, specs at time of order
    variant_snapshot JSONB,  -- Size, bolt pattern, offset, etc.
    addon_snapshot JSONB,    -- Addon details at time of order
    
    -- Denormalized data (for quick display without JOIN)
    sku VARCHAR(100),
    product_name VARCHAR(255),
    product_description TEXT,
    brand_name VARCHAR(100),
    model_name VARCHAR(100),
    
    -- Pricing
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    tax_inclusive BOOLEAN DEFAULT FALSE,  -- KEY: Tax included in price or added on top?
    discount DECIMAL(10,2) DEFAULT 0,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    line_total DECIMAL(10,2) NOT NULL,
    
    -- Fulfillment
    allocated_quantity INT DEFAULT 0,
    shipped_quantity INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### **Order Item Quantities (Warehouse Allocation)**

```sql
CREATE TABLE order_item_quantities (
    id BIGSERIAL PRIMARY KEY,
    order_item_id BIGINT REFERENCES order_items(id) ON DELETE CASCADE,
    warehouse_id BIGINT REFERENCES warehouses(id),
    quantity INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔑 Key Concepts

### **1. Document Type Discriminator**

Instead of separate tables, one table with type field:

```php
// Quote
$order = Order::create([
    'document_type' => 'quote',
    'quote_status' => 'draft',
    'order_number' => 'ORD-2025-001',
]);

// Later: Convert to Invoice
$order->update([
    'document_type' => 'invoice',
    'quote_status' => 'converted',
    'is_quote_converted' => true,
]);
```

### **2. Tax Inclusive vs Tax Exclusive**

```php
// Tax Inclusive (GCC Standard)
[
    'unit_price' => 100.00,
    'tax_inclusive' => true,
    'quantity' => 1,
    'line_total' => 100.00,  // Tax already in price
    'tax_amount' => 4.76     // 5% VAT extracted: 100 / 1.05 = 95.24, tax = 4.76
]

// Tax Exclusive (International)
[
    'unit_price' => 100.00,
    'tax_inclusive' => false,
    'quantity' => 1,
    'line_total' => 105.00,  // Price + Tax
    'tax_amount' => 5.00     // 5% VAT added: 100 * 0.05 = 5
]
```

### **3. Snapshot Services (CRITICAL)**

When creating order items, we snapshot product data to preserve historical accuracy:

```php
// ProductSnapshotService
public function createSnapshot(Product $product): array
{
    return [
        'product_id' => $product->id,
        'brand_id' => $product->brand_id,
        'brand_name' => $product->brand->name,
        'model_id' => $product->model_id,
        'model_name' => $product->model->name,
        'finish_id' => $product->finish_id,
        'finish_name' => $product->finish->name,
        'retail_price' => $product->retail_price,
        'wholesale_price' => $product->wholesale_price,
        'snapshot_date' => now(),
    ];
}

// VariantSnapshotService
public function createSnapshot(ProductVariant $variant): array
{
    return [
        'variant_id' => $variant->id,
        'sku' => $variant->sku,
        'size' => $variant->size,
        'bolt_pattern' => $variant->bolt_pattern,
        'offset' => $variant->offset,
        'center_bore' => $variant->center_bore,
        'finish' => $variant->finish,
        'price' => $variant->price,
        'snapshot_date' => now(),
    ];
}

// AddonSnapshotService
public function createSnapshot(Addon $addon): array
{
    return [
        'addon_id' => $addon->id,
        'category_id' => $addon->category_id,
        'category_name' => $addon->category->name,
        'name' => $addon->name,
        'price' => $addon->price,
        'wholesale_price' => $addon->wholesale_price,
        'snapshot_date' => now(),
    ];
}
```

### **4. Dealer Pricing Service**

```php
public function calculatePrice(Customer $customer, $item, string $type): float
{
    // Type: 'product', 'variant', 'addon'
    
    // Priority order:
    // 1. Variant-specific pricing
    // 2. Model-specific pricing
    // 3. Brand-specific pricing
    // 4. Category-specific pricing (addons)
    // 5. Customer default discount
    // 6. Base price
    
    $basePrice = $item->price;
    
    if ($customer->type === CustomerType::DEALER) {
        // Check variant-specific
        if ($variantPrice = $customer->variantPricing()->where('variant_id', $item->id)->first()) {
            return $variantPrice->price;
        }
        
        // Check model-specific
        if ($modelPrice = $customer->modelPricing()->where('model_id', $item->model_id)->first()) {
            return $basePrice * (1 - $modelPrice->discount / 100);
        }
        
        // Check brand-specific
        if ($brandPrice = $customer->brandPricing()->where('brand_id', $item->brand_id)->first()) {
            return $basePrice * (1 - $brandPrice->discount / 100);
        }
        
        // Default dealer discount
        if ($customer->default_discount > 0) {
            return $basePrice * (1 - $customer->default_discount / 100);
        }
    }
    
    return $basePrice;
}
```

---

## 🚀 Implementation Plan

### **Week 5: Orders/Quotes Module**

#### **Day 36: Database & Models**
1. Create migration: `create_orders_table` with all fields
2. Create migration: `create_order_items_table` with snapshots
3. Create migration: `create_order_item_quantities_table`
4. Create `Order` model with relationships
5. Create `OrderItem` model with snapshots
6. Create `OrderItemQuantity` model
7. Create enums:
   - `DocumentType` (Quote, Invoice, Order)
   - `QuoteStatus` (Draft, Sent, Approved, Rejected, Converted)
   - `OrderStatus` (Pending, Processing, Shipped, Completed, Cancelled)
   - `PaymentStatus` (Pending, Partial, Paid, Refunded)

#### **Day 37: Snapshot Services**
1. Enhance `ProductSnapshotService`
2. Create `VariantSnapshotService`
3. Enhance `AddonSnapshotService` (already exists)
4. Test snapshot creation
5. Test historical data preservation

#### **Day 38: Business Logic**
1. Create `OrderService` with:
   - `createOrder()` - Creates order with snapshots
   - `convertQuoteToInvoice()` - Changes document_type
   - `updateStatus()` - Status management
   - `calculateTotals()` - Financial calculations
2. Create `OrderSyncService` for external orders
3. Create `OrderFulfillmentService` for warehouse allocation

#### **Day 39: Filament Resource**
1. Create `OrderResource` (Filament v3)
2. Form with:
   - Customer selection
   - Order items repeater
   - Product/addon selection with pricing
   - Warehouse allocation
   - Payment tracking
3. Table with filters and search
4. Custom actions (Convert, Ship, Complete)

#### **Day 40: Testing**
1. Test quote creation
2. Test quote to invoice conversion
3. Test order sync from external systems
4. Test warehouse allocation
5. Test snapshot preservation

---

## ✅ Ready to Proceed

I now fully understand:
- ✅ Unified orders table with document_type
- ✅ Quote → Invoice conversion workflow
- ✅ Tax inclusive vs exclusive pricing
- ✅ Snapshot services for historical data
- ✅ Dealer pricing calculations
- ✅ External order synchronization
- ✅ Warehouse allocation per item

**Next Step:** Implement Orders/Quotes module Week 5!
