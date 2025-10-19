# 🔬 RESEARCH FINDINGS - Current Reporting System
## Deep Analysis & Key Insights

**Research Date:** October 20, 2025  
**System Analyzed:** C:\Users\Dell\Documents\Reporting  
**Purpose:** Understand current system to build BETTER new system  

---

## 📊 CRITICAL INSIGHTS

### ✅ **What Works Well (Keep & Improve)**

1. **Unified Orders Table Approach**
   - Single `orders` table with `document_type` ENUM ('quote', 'invoice', 'order')
   - Eliminates data duplication
   - Simple state transitions via field changes
   - **IMPROVEMENT**: Add proper state machine for transitions

2. **Tax Inclusive/Exclusive System**
   - `tax_inclusive` boolean on order_items, invoice_items, consignment_items
   - Flexible pricing strategy
   - **IMPROVEMENT**: Add tax presets for different regions

3. **External Product Sync**
   - Orders sync from TunerStop retail and Wholesale systems
   - External products stored with snapshot data
   - **IMPROVEMENT**: Better conflict resolution & sync status tracking

4. **Customer-Specific Pricing**
   - Brand-level discounts
   - Model-level discounts  
   - Add-on category discounts
   - **IMPROVEMENT**: Add tier-based pricing, volume discounts

### ❌ **What Doesn't Work (Fix in New System)**

1. **Messy Quote-Invoice Workflow**
   - No clear state machine
   - Manual `convertQuoteToInvoice()` method
   - No audit trail of state changes
   - No validation before transitions

2. **Over-complicated Product Management**
   - Full product/inventory sync not needed for CRM
   - Too many relationships
   - Bloated database

3. **Weak Permission System**
   - Hard-coded role_id checks (`role_id === 1`)
   - No granular permissions
   - Missing consignment permissions
   - No quote approval workflows

4. **No Proper Financial Tracking**
   - Mixed payment status logic
   - Inconsistent outstanding_amount calculations
   - No payment history
   - No partial payment tracking

---

## 🔍 CURRENT SYSTEM WORKFLOW

### **Order Lifecycle (As-Is)**

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 1: ORDER SYNC FROM TUNERSTOP                         │
└─────────────────────────────────────────────────────────────┘
TunerStop.com (Customer Order)
    ↓ [OrderSyncService]
CRM Creates Order:
    - external_order_id (from TunerStop)
    - external_source = 'retail' or 'wholesale'
    - document_type = 'quote' ← DEFAULT
    - quote_status = 'draft'
    - order_number = generated
    - tax_inclusive = TRUE (most external orders)
    
┌─────────────────────────────────────────────────────────────┐
│  PHASE 2: ADMIN REVIEW & QUOTE MANAGEMENT                   │
└─────────────────────────────────────────────────────────────┘
Admin Reviews in Dashboard
    ↓
Option A: Send Quote to Customer
    - quote_status = 'sent'
    - sent_at = NOW()
    - Email sent via EmailService
    ↓
Customer Reviews Quote
    ↓
Option B: Admin Approves Quote
    - quote_status = 'approved'
    - approved_at = NOW()

┌─────────────────────────────────────────────────────────────┐
│  PHASE 3: CONVERT TO INVOICE                                │
└─────────────────────────────────────────────────────────────┘
Admin Clicks "Convert to Invoice"
    ↓
Order->convertQuoteToInvoice():
    - document_type = 'invoice' ← KEY CHANGE
    - quote_status = 'converted'
    - is_quote_converted = TRUE
    - status = OrderStatusEnum::PENDING
    - Invoice record created in invoices table

┌─────────────────────────────────────────────────────────────┐
│  PHASE 4: ORDER FULFILLMENT                                 │
└─────────────────────────────────────────────────────────────┘
Warehouse Processing:
    - status = 'processing' (OrderStatusEnum)
    - Inventory allocated
    ↓
Shipping:
    - status = 'shipped'
    - tracking_number added
    - shipping_carrier added
    - Email sent to customer
    ↓
Delivery Confirmed:
    - status = 'completed'
    - Order finalized
```

### **Database Structure (As-Is)**

```sql
-- SINGLE UNIFIED TABLE (GOOD APPROACH!)
CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    
    -- External Sync Fields
    external_order_id VARCHAR(100),
    external_source VARCHAR(20), -- 'retail', 'wholesale', 'b2b'
    
    -- Order Details
    order_number VARCHAR(50) UNIQUE NOT NULL,
    quote_number VARCHAR(50), -- Only for quotes
    
    -- Customer
    customer_id BIGINT,
    
    -- CRITICAL: Document Type & Workflow Status
    document_type VARCHAR(20) DEFAULT 'quote',
        -- VALUES: 'quote', 'invoice', 'order'
    quote_status VARCHAR(20),
        -- VALUES: 'draft', 'sent', 'approved', 'rejected', 'converted'
    status INT, -- OrderStatusEnum (1=pending, 2=processing, 3=shipped, 4=completed)
    payment_status INT, -- PaymentStatusEnum (0=pending, 1=partial, 10=paid)
    
    -- Quote Workflow Dates
    issue_date DATE,
    valid_until DATE,
    sent_at TIMESTAMP,
    approved_at TIMESTAMP,
    converted_to_invoice_id BIGINT,
    is_quote_converted BOOLEAN DEFAULT FALSE,
    
    -- Financial
    sub_total DECIMAL(12,2),
    tax DECIMAL(12,2),
    vat DECIMAL(12,2),
    discount DECIMAL(12,2),
    shipping DECIMAL(12,2),
    total DECIMAL(12,2),
    
    -- Payment Tracking (WEAK IMPLEMENTATION)
    paid_amount DECIMAL(12,2),
    outstanding_amount DECIMAL(12,2),
    
    -- Representative
    representative_id BIGINT,
    salesman_id BIGINT,
    created_by BIGINT,
    
    -- Timestamps
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Order Items
CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT,
    
    -- Product Reference (HYBRID APPROACH)
    product_id BIGINT, -- Local product (can be NULL)
    product_variant_id BIGINT, -- Local variant (can be NULL)
    external_product_id VARCHAR(100), -- External product ID
    external_source VARCHAR(20),
    
    -- Denormalized Fields (GOOD FOR PERFORMANCE)
    product_name VARCHAR(255),
    brand_name VARCHAR(100),
    model_name VARCHAR(100),
    finish_name VARCHAR(100),
    sku VARCHAR(100),
    size VARCHAR(50),
    bolt_pattern VARCHAR(50),
    offset VARCHAR(50),
    
    -- Pricing
    quantity INT,
    price DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    
    -- CRITICAL: Tax Handling
    tax_inclusive BOOLEAN DEFAULT FALSE,
    
    -- Snapshot for external products
    product_snapshot JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## 💰 TAX SYSTEM ANALYSIS

### **How Tax Inclusive Works (Current)**

```php
// Order Item with tax_inclusive = TRUE
[
    'product_name' => 'Fuel Wheels - Maverick',
    'price' => 100.00,  // This INCLUDES 5% VAT
    'quantity' => 4,
    'tax_inclusive' => TRUE,
    'line_total' => 400.00
]

// Calculation:
// Price shown = 100.00 (customer sees this)
// VAT Rate = 5%
// Price before tax = 100 / 1.05 = 95.24
// VAT amount = 100 - 95.24 = 4.76
// Total to customer = 400.00 (no surprise!)

// Order Item with tax_inclusive = FALSE
[
    'product_name' => 'Fuel Wheels - Maverick',
    'price' => 100.00,  // This is BEFORE tax
    'quantity' => 4,
    'tax_inclusive' => FALSE,
    'line_total' => 420.00  // Includes 5% VAT added
]

// Calculation:
// Price shown = 100.00
// VAT Rate = 5%
// VAT amount = 100 * 0.05 = 5.00
// Total per item = 105.00
// Total to customer = 420.00
```

### **Issues with Current Tax System**

1. ❌ No support for multiple tax rates (VAT, GST, Sales Tax)
2. ❌ Hard-coded 5% VAT in many places
3. ❌ No tax exemptions for business customers
4. ❌ No regional tax rules (UAE vs USA vs EU)
5. ❌ Tax calculations not auditable

---

## � UNIFIED DEALER PRICING MECHANISM

### **CRITICAL: Dealer Pricing MUST Work Everywhere!**

```php
/**
 * DEALER PRICING ACTIVATION RULE:
 * 
 * IF customer.customer_type === 'dealer'
 * THEN apply dealer pricing in ALL modules:
 *    ✅ Orders
 *    ✅ Quotes  
 *    ✅ Invoices
 *    ✅ Consignments
 *    ✅ Warranty Replacements
 *    ✅ Manual Price Adjustments
 */

// Central Pricing Service (NEW SYSTEM)
class DealerPricingService
{
    public function calculatePrice($product, $customer, $quantity = 1)
    {
        // Non-dealer customers get retail price
        if ($customer->customer_type !== 'dealer') {
            return $product->retail_price;
        }
        
        // Dealer pricing priority:
        // 1. Model-specific discount (HIGHEST)
        // 2. Brand-specific discount
        // 3. Add-on category discount (for add-ons)
        // 4. Retail price (fallback)
        
        $basePrice = $product->retail_price;
        
        // Check model pricing (PRIORITY 1)
        $modelPricing = $customer->modelPricing()
            ->where('model_id', $product->model_id)
            ->first();
            
        if ($modelPricing) {
            return $this->applyDiscount($basePrice, $modelPricing);
        }
        
        // Check brand pricing (PRIORITY 2)
        $brandPricing = $customer->brandPricing()
            ->where('brand_id', $product->brand_id)
            ->first();
            
        if ($brandPricing) {
            return $this->applyDiscount($basePrice, $brandPricing);
        }
        
        // Check add-on category pricing (for add-ons)
        if ($product instanceof AddOn && $product->category_id) {
            $addonPricing = $customer->addonCategoryPricing()
                ->where('add_on_category_id', $product->category_id)
                ->first();
                
            if ($addonPricing) {
                return $this->applyDiscount($basePrice, $addonPricing);
            }
        }
        
        // No special pricing, return retail
        return $basePrice;
    }
    
    private function applyDiscount($basePrice, $pricingRule)
    {
        if ($pricingRule->discount_type === 'percent') {
            return $basePrice - ($basePrice * ($pricingRule->discount_value / 100));
        } else {
            return $basePrice - $pricingRule->discount_value;
        }
    }
}
```

### **Dealer Pricing in ALL Modules**

#### **1. Orders & Quotes**

```php
// OrderController->store()
// QuoteController->store()
foreach ($items as $item) {
    $customer = Customer::find($request->customer_id);
    $product = Product::find($item['product_id']);
    
    // APPLY DEALER PRICING
    $dealerPricingService = new DealerPricingService();
    $finalPrice = $dealerPricingService->calculatePrice($product, $customer);
    
    OrderItem::create([
        'product_id' => $product->id,
        'price' => $product->retail_price, // Original price
        'sale_price' => $finalPrice, // Dealer price applied!
        'customer_id' => $customer->id,
        // ... other fields
    ]);
}
```

#### **2. Invoices**

```php
// InvoiceController->create()
// When converting quote to invoice, PRESERVE dealer pricing!
foreach ($quote->items as $quoteItem) {
    InvoiceItem::create([
        'product_id' => $quoteItem->product_id,
        'price' => $quoteItem->price, // Original price
        'sale_price' => $quoteItem->sale_price, // Dealer price preserved!
        // ... other fields
    ]);
}
```

#### **3. Consignments**

```php
// ConsignmentController->store()
foreach ($items as $item) {
    $customer = Customer::find($request->customer_id);
    $product = Product::find($item['product_id']);
    
    // APPLY DEALER PRICING TO CONSIGNMENTS TOO!
    $dealerPricingService = new DealerPricingService();
    $finalPrice = $dealerPricingService->calculatePrice($product, $customer);
    
    ConsignmentItem::create([
        'product_id' => $product->id,
        'price' => $product->retail_price,
        'sale_price' => $finalPrice, // Dealer price!
        // ... other fields
    ]);
}
```

#### **4. Warranty Replacements**

```php
// WarrantyClaimController->processReplacement()
if ($warrantyClaim->resolution_type === 'replacement') {
    $customer = $warrantyClaim->customer;
    $product = Product::find($replacement_product_id);
    
    // APPLY DEALER PRICING TO REPLACEMENTS!
    $dealerPricingService = new DealerPricingService();
    $replacementCost = $dealerPricingService->calculatePrice($product, $customer);
    
    $warrantyClaim->update([
        'replacement_cost' => $replacementCost, // Dealer price if customer is dealer!
    ]);
    
    // Create replacement order with dealer pricing
    Order::create([
        'customer_id' => $customer->id,
        'document_type' => 'order',
        'items' => [
            [
                'product_id' => $product->id,
                'price' => $product->retail_price,
                'sale_price' => $replacementCost, // Dealer price!
            ]
        ]
    ]);
}
```

#### **5. Manual Price Adjustments**

```php
// Admin manually creating order/quote/invoice
// Frontend should show DEALER pricing badge if customer is dealer
if ($customer->customer_type === 'dealer') {
    // Show dealer price calculator
    echo '<div class="dealer-price-badge">Dealer Pricing Active</div>';
    
    // Auto-calculate dealer price when product selected
    $dealerPricingService = new DealerPricingService();
    $suggestedPrice = $dealerPricingService->calculatePrice($product, $customer);
    
    // Admin can override but should see dealer price as default
    echo '<input type="number" name="sale_price" value="' . $suggestedPrice . '" />';
    echo '<small>Dealer Price (can override)</small>';
}
```

### **Dealer Pricing Database Tables (KEEP THESE!)**

```sql
-- Customer Model/Brand Pricing (EXISTING - KEEP!)
CREATE TABLE customer_model_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT,
    model_id BIGINT,
    discount_type VARCHAR(20), -- 'percent' or 'fixed'
    discount_value DECIMAL(10,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(customer_id, model_id)
);

CREATE TABLE customer_brand_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT,
    brand_id BIGINT,
    discount_type VARCHAR(20), -- 'percent' or 'fixed'
    discount_value DECIMAL(10,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(customer_id, brand_id)
);

CREATE TABLE customer_addon_category_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT,
    add_on_category_id BIGINT,
    discount_type VARCHAR(20), -- 'percent' or 'fixed'
    discount_value DECIMAL(10,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE(customer_id, add_on_category_id)
);
```

### **UI Indication for Dealer Pricing**

```html
<!-- Show dealer pricing badge in all modules -->
@if($customer->customer_type === 'dealer')
    <div class="alert alert-info">
        <i class="fa fa-star"></i>
        <strong>Dealer Pricing Active</strong>
        This customer receives special dealer pricing on all items.
    </div>
@endif

<!-- Product selection shows both prices -->
<tr>
    <td>Fuel Wheels - Maverick D610</td>
    <td>
        @if($customer->customer_type === 'dealer')
            <span style="text-decoration: line-through; color: #999;">
                $299.00 (Retail)
            </span>
            <br>
            <strong style="color: green;">
                $249.00 (Dealer Price - 20% off)
            </strong>
        @else
            <strong>$299.00</strong>
        @endif
    </td>
</tr>
```

---

## 📄 PDF TEMPLATES (REUSE IN NEW SYSTEM!)

### **Existing Templates to Reuse**

1. **Invoice Template**
   - Path: `resources/views/vendor/voyager/invoices/professional-invoice.blade.php`
   - Features: Professional layout, tax breakdown, itemized list
   - **✅ REUSE THIS for orders/quotes/invoices in new system**

2. **Consignment Template**
   - Path: `resources/views/vendor/voyager/consignments/professional-consignment.blade.php`
   - Features: Consignment-specific layout, expected return date, item tracking
   - **✅ REUSE THIS for consignments in new system**

3. **Tax Invoice Template**
   - Path: `resources/views/vendor/voyager/orders/tax-invoice.blade.php`
   - Features: Tax-compliant invoice, VAT breakdown
   - **✅ REUSE THIS for tax invoices**

### **PDF Generation Strategy**

```php
// Single PDF service for all document types
class DocumentPdfService
{
    public function generatePdf($document, $type)
    {
        // $type = 'quote', 'invoice', 'consignment', 'warranty_claim'
        
        $template = match($type) {
            'quote' => 'documents.quote-pdf',
            'invoice' => 'documents.invoice-pdf', // REUSE existing template!
            'consignment' => 'documents.consignment-pdf', // REUSE existing!
            'warranty_claim' => 'documents.warranty-claim-pdf',
            'delivery_note' => 'documents.delivery-note-pdf',
        };
        
        $pdf = PDF::loadView($template, [
            'document' => $document,
            'items' => $document->items,
            'customer' => $document->customer,
            'organization' => $this->getOrganizationSettings(),
        ]);
        
        return $pdf;
    }
}
```

---

## �👥 CUSTOMER PRICING ANALYSIS

### **Current Pricing Strategy**

```php
// Priority Order (Highest to Lowest):
// 1. Model-Specific Pricing (if customer has model discount)
// 2. Brand-Specific Pricing (if customer has brand discount)
// 3. Add-on Category Pricing (for add-ons)
// 4. Base Price (retail)

// Example:
$basePrice = 100.00;
$customer = Customer::find(123); // customer_type = 'dealer'

// Check model pricing first
$modelPricing = $customer->modelPricing()
    ->where('model_id', $product->model_id)
    ->first();

if ($modelPricing) {
    if ($modelPricing->discount_type === 'percent') {
        $finalPrice = $basePrice - ($basePrice * ($modelPricing->discount_value / 100));
    } else {
        $finalPrice = $basePrice - $modelPricing->discount_value;
    }
}

// Fallback to brand pricing
if (!$modelPricing) {
    $brandPricing = $customer->brandPricing()
        ->where('brand_id', $product->brand_id)
        ->first();
    // Similar discount logic...
}
```

### **Issues with Current Pricing**

1. ❌ No volume discounts
2. ❌ No time-based promotions
3. ❌ No bundle pricing
4. ❌ No tier-based pricing (Silver/Gold/Platinum)
5. ❌ Pricing changes not tracked (no history)

---

## 🔐 PERMISSION SYSTEM ANALYSIS

### **Current Role System (WEAK)**

```php
// Hard-coded role IDs (BAD PRACTICE!)
const ROLE_SUPER_ADMIN = 1;
const ROLE_VENDOR = 2;
const ROLE_REPRESENTATIVE = 3; // Doesn't exist in actual system!

// Code throughout system:
if (Auth::user()->role_id === 1) {
    // Super admin only
}

if (Auth::user()->role_id === 1 || Auth::user()->role_id === 2) {
    // Admin or vendor
}
```

### **Missing Permissions**

1. ❌ Quote Management
   - browse_quotes
   - create_quote
   - edit_quote
   - delete_quote
   - send_quote
   - approve_quote
   - convert_to_invoice

2. ❌ Consignment Management
   - browse_consignment
   - create_consignment
   - edit_consignment
   - delete_consignment
   - approve_consignment
   - mark_sold
   - mark_returned

3. ❌ Invoice Management
   - browse_invoices
   - create_invoice
   - edit_invoice
   - delete_invoice
   - mark_paid
   - mark_partial_paid
   - send_invoice

4. ❌ Financial Operations
   - view_financials
   - record_payment
   - process_refund
   - adjust_pricing

---

## 📦 CONSIGNMENT SYSTEM ANALYSIS

### **Consignment Workflow (As-Is)**

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 1: CREATE CONSIGNMENT                                │
└─────────────────────────────────────────────────────────────┘
Admin Creates Consignment:
    - consignment_number = generated (CON-2025-0001)
    - customer_id = selected customer
    - status = 'draft'
    - items = products to consign
    - tax_inclusive = per item (like orders!)
    - expected_return_date = 30/60/90 days

┌─────────────────────────────────────────────────────────────┐
│  PHASE 2: SEND CONSIGNMENT                                  │
└─────────────────────────────────────────────────────────────┘
Admin Sends to Customer:
    - status = 'sent'
    - sent_at = NOW()
    - items_sent_count = total items
    - tracking_number = shipping tracking

┌─────────────────────────────────────────────────────────────┐
│  PHASE 3: CUSTOMER HAS ITEMS                                │
└─────────────────────────────────────────────────────────────┘
Status = 'delivered'
    ↓
Option A: Customer Sells Items
    - ConsignmentItem->status = 'sold'
    - quantity_sold incremented
    - date_sold = NOW()
    - actual_sale_price recorded
    - Create Invoice for sold items
    ↓
Option B: Customer Returns Items
    - ConsignmentItem->status = 'returned'
    - quantity_returned incremented
    - date_returned = NOW()

┌─────────────────────────────────────────────────────────────┐
│  PHASE 4: SETTLEMENT                                        │
└─────────────────────────────────────────────────────────────┘
When All Items Sold OR Returned:
    - status = 'invoiced_in_full' (if all sold)
    - status = 'returned' (if all returned)
    - Create final invoice
    - Update inventory
```

### **Consignment Database Structure**

```sql
CREATE TABLE consignments (
    id BIGSERIAL PRIMARY KEY,
    consignment_number VARCHAR(50) UNIQUE,
    customer_id BIGINT,
    
    -- Status (Similar to Orders!)
    status VARCHAR(30), -- draft, sent, delivered, partially_sold, invoiced_in_full, returned, cancelled
    
    -- Financial (SAME AS ORDERS!)
    sub_total DECIMAL(12,2),
    tax DECIMAL(12,2),
    discount DECIMAL(12,2),
    shipping_cost DECIMAL(12,2),
    total DECIMAL(12,2),
    
    -- Consignment Tracking
    items_sent_count INT DEFAULT 0,
    items_sold_count INT DEFAULT 0,
    items_returned_count INT DEFAULT 0,
    
    -- Dates
    delivery_date DATE,
    expected_return_date DATE,
    
    -- Representative (SAME AS ORDERS!)
    representative_id BIGINT,
    salesman_id BIGINT,
    
    -- History
    consignment_history JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE consignment_items (
    id BIGSERIAL PRIMARY KEY,
    consignment_id BIGINT,
    
    -- Product (HYBRID like order_items!)
    product_id BIGINT,
    product_variant_id BIGINT,
    external_product_id VARCHAR(100),
    external_source VARCHAR(20),
    
    -- Denormalized (SAME AS ORDER ITEMS!)
    product_name VARCHAR(255),
    brand_name VARCHAR(100),
    model_name VARCHAR(100),
    finish_name VARCHAR(100),
    sku VARCHAR(100),
    size VARCHAR(50),
    bolt_pattern VARCHAR(50),
    offset VARCHAR(50),
    
    -- Pricing (SAME AS ORDER ITEMS!)
    price DECIMAL(10,2),
    sale_price DECIMAL(10,2),
    quantity INT,
    tax_inclusive BOOLEAN DEFAULT FALSE, -- ✅ SAME TAX SYSTEM!
    
    -- Consignment Tracking
    status VARCHAR(30), -- pending, sold, returned
    quantity_sent INT DEFAULT 0,
    quantity_sold INT DEFAULT 0,
    quantity_returned INT DEFAULT 0,
    date_sold TIMESTAMP,
    date_returned TIMESTAMP,
    actual_sale_price DECIMAL(10,2), -- Price customer actually sold at
    invoice_id BIGINT, -- Link to invoice when sold
    
    -- Snapshot
    product_snapshot JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **Key Consignment Features**

1. ✅ **Same Tax System as Orders**
   - `tax_inclusive` boolean on each item
   - Flexible pricing like orders

2. ✅ **Partial Selling**
   - Track quantity_sold vs quantity_sent
   - Create invoices as items sell
   - Status: partially_sold

3. ✅ **History Tracking**
   - consignment_history JSON field
   - Audit trail of status changes
   - Who sold what when

4. ✅ **PDF Template**
   - Professional consignment slip (professional-consignment.blade.php)
   - **REUSE THIS TEMPLATE in new system!**

### **Dealer Pricing on Consignments**

```php
// CRITICAL: Dealer pricing MUST apply to consignments too!
if ($customer->customer_type === 'dealer') {
    // Apply model/brand discount to consignment items
    // SAME logic as orders
    $consignmentItem->price = calculateDealerPrice($product, $customer);
}
```

---

## 🛠️ WARRANTY CLAIMS SYSTEM ANALYSIS

### **Warranty Claim Workflow (As-Is)**

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 1: CLAIM SUBMISSION                                  │
└─────────────────────────────────────────────────────────────┘
Customer Reports Issue:
    - claim_number = generated (WC-2025-0001)
    - claim_type = defect | damage | incorrect_item | performance_issue
    - status = 'draft' or 'submitted'
    - original_order_id = reference
    - product details captured
    - attachments uploaded (photos, videos)

┌─────────────────────────────────────────────────────────────┐
│  PHASE 2: REVIEW & INVESTIGATION                            │
└─────────────────────────────────────────────────────────────┘
Admin Reviews Claim:
    - status = 'under_review'
    - assigned_to = staff member
    - Review attachments
    - Check warranty period
    - Verify purchase
    - Contact customer if needed

┌─────────────────────────────────────────────────────────────┐
│  PHASE 3: DECISION                                          │
└─────────────────────────────────────────────────────────────┘
Option A: Approve Claim
    - status = 'approved'
    - resolution_type = replacement | refund | repair | store_credit
    - approved_at = NOW()
    ↓
Option B: Reject Claim
    - status = 'rejected'
    - resolution_notes = reason for rejection

┌─────────────────────────────────────────────────────────────┐
│  PHASE 4: RESOLUTION                                        │
└─────────────────────────────────────────────────────────────┘
If Replacement:
    - status = 'replaced'
    - Create replacement order
    - Ship new product
    - Track costs

If Refund:
    - status = 'refunded'
    - Process refund
    - refund_amount recorded

Final:
    - status = 'closed'
    - resolved_at = NOW()
```

### **Warranty Claim Database Structure**

```sql
CREATE TABLE warranty_claims (
    id BIGSERIAL PRIMARY KEY,
    claim_number VARCHAR(50) UNIQUE,
    
    -- Customer & Product
    customer_id BIGINT,
    product_id BIGINT,
    product_variant_id BIGINT,
    brand_id BIGINT,
    original_order_id BIGINT,
    original_invoice_id BIGINT,
    
    -- Product Details (DENORMALIZED!)
    product_name VARCHAR(255),
    product_sku VARCHAR(100),
    brand_name VARCHAR(100),
    model_name VARCHAR(100),
    size VARCHAR(50),
    bolt_pattern VARCHAR(50),
    offset VARCHAR(50),
    finish_name VARCHAR(100),
    
    -- Claim Details
    claim_type VARCHAR(50), -- defect, damage, incorrect_item, performance_issue, other
    status VARCHAR(30), -- draft, submitted, under_review, approved, rejected, replaced, refunded, closed
    priority VARCHAR(20), -- low, normal, high, urgent
    description TEXT,
    quantity INT,
    
    -- Dates
    purchase_date DATE,
    claim_date DATE,
    resolution_date DATE,
    
    -- Costs (CRITICAL FOR REPORTING!)
    item_cost DECIMAL(10,2),
    replacement_cost DECIMAL(10,2),
    refund_amount DECIMAL(10,2),
    return_shipping_cost DECIMAL(10,2),
    repair_cost DECIMAL(10,2),
    total_claim_cost DECIMAL(10,2),
    cost_recovered_from_supplier DECIMAL(10,2),
    net_claim_cost DECIMAL(10,2),
    
    -- Resolution
    resolution_type VARCHAR(50), -- replacement, refund, repair, store_credit
    resolution_notes TEXT,
    
    -- Tracking
    assigned_to BIGINT,
    created_by BIGINT,
    
    -- SLA Tracking
    sla_due_date TIMESTAMP,
    sla_breached BOOLEAN,
    first_response_at TIMESTAMP,
    
    -- Attachments
    attachments JSON, -- Photos, videos, documents
    claim_history JSON,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE warranty_claim_items (
    id BIGSERIAL PRIMARY KEY,
    warranty_claim_id BIGINT,
    
    -- Product (SAME HYBRID APPROACH!)
    product_id BIGINT,
    product_variant_id BIGINT,
    add_on_id BIGINT,
    external_product_id VARCHAR(100),
    external_source VARCHAR(20),
    
    -- Product Details (DENORMALIZED!)
    product_name VARCHAR(255),
    sku VARCHAR(100),
    brand_name VARCHAR(100),
    model_name VARCHAR(100),
    finish_name VARCHAR(100),
    size VARCHAR(50),
    bolt_pattern VARCHAR(50),
    offset VARCHAR(50),
    
    -- Item Specifics
    quantity INT,
    unit_cost DECIMAL(10,2),
    total_cost DECIMAL(10,2),
    item_issue_description TEXT,
    item_status VARCHAR(30), -- pending, approved, rejected, replaced, refunded
    
    -- Resolution
    replacement_cost DECIMAL(10,2),
    refund_amount DECIMAL(10,2),
    replacement_sku VARCHAR(100),
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **Key Warranty Features**

1. ✅ **Comprehensive Cost Tracking**
   - Item cost
   - Replacement cost
   - Shipping costs
   - Supplier recovery
   - Net cost calculation

2. ✅ **SLA Management**
   - Target resolution hours
   - SLA breach tracking
   - Auto-escalation

3. ✅ **Analytics Ready**
   - Customer claim count
   - Product claim count
   - Brand claim count
   - Root cause analysis

4. ✅ **Attachment Support**
   - Photos of damage
   - Videos of defects
   - Documents

### **Warranty & Dealer Pricing**

```php
// For warranty replacements, use SAME pricing logic
if ($customer->customer_type === 'dealer') {
    // Replacement cost should reflect dealer pricing
    $replacementCost = calculateDealerPrice($product, $customer);
} else {
    $replacementCost = $product->retail_price;
}
```

---

## 📦 PRODUCT SYNC ANALYSIS

### **Current Sync Strategy (HYBRID)**

```php
// OrderSyncService - When order syncs from TunerStop
public function createOrder($orderData) {
    $order = Order::create([
        'external_order_id' => $orderData['id'],
        'external_source' => 'retail',
        'document_type' => 'quote', // Default
        'quote_status' => 'draft',
        // ... other fields
    ]);
    
    // Create order items
    foreach ($orderData['items'] as $item) {
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'external_product_id' => $item['product_id'],
            'external_source' => 'retail',
            'product_name' => $item['name'],
            'brand_name' => $item['brand'],
            'model_name' => $item['model'],
            'sku' => $item['sku'],
            'size' => $item['size'],
            'bolt_pattern' => $item['bolt_pattern'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'tax_inclusive' => TRUE, // Default for external
            'product_snapshot' => json_encode($item),
        ]);
    }
}
```

### **What's Over-Engineered**

1. ❌ **Full Product Sync** - CRM doesn't need 50K+ products
2. ❌ **Inventory Management** - Should be reference-only, not actual stock
3. ❌ **Product Relationships** - Too many joins for reporting
4. ✅ **Product Snapshot** - GOOD! Captures product at time of order

### **What's Actually Needed**

1. ✅ Order-level product information (snapshot)
2. ✅ Product reference IDs (for lookups)
3. ✅ Denormalized fields (brand, model, finish, size, etc.)
4. ❌ NOT full product catalog
5. ❌ NOT inventory tracking (use external system as source of truth)

---

## 🎨 DASHBOARD ANALYSIS

### **Current Dashboard (unified.blade.php)**

**What Users See:**
- 4 stat cards:
  - Pending Orders Count
  - Monthly Revenue
  - Today's Orders
  - Notifications Count
- Pending Orders Table:
  - Date, Order #, Customer, Wheel Brand, Price, Channel, Status, Actions

**Action Buttons Per Order:**
- Download Invoice (PDF)
- Delivery Note (PDF)
- Record Payment
- View Details
- Edit Order

### **Issues with Current Dashboard**

1. ❌ No quote-specific view
2. ❌ No invoice-specific view
3. ❌ Mixed order/quote/invoice in one table
4. ❌ No status filtering
5. ❌ No channel filtering
6. ❌ No date range filtering
7. ❌ Action buttons don't respect document_type

---

## 🎯 KEY IMPROVEMENTS FOR NEW SYSTEM

### **1. State Machine for Order Workflow**

```typescript
// Better approach: Explicit state transitions
enum OrderState {
    DRAFT_QUOTE = 'draft_quote',
    SENT_QUOTE = 'sent_quote',
    APPROVED_QUOTE = 'approved_quote',
    REJECTED_QUOTE = 'rejected_quote',
    INVOICE_PENDING = 'invoice_pending',
    INVOICE_PROCESSING = 'invoice_processing',
    INVOICE_SHIPPED = 'invoice_shipped',
    INVOICE_DELIVERED = 'invoice_delivered',
    COMPLETED = 'completed',
    CANCELLED = 'cancelled'
}

// Valid transitions
const ALLOWED_TRANSITIONS = {
    DRAFT_QUOTE: [SENT_QUOTE, CANCELLED],
    SENT_QUOTE: [APPROVED_QUOTE, REJECTED_QUOTE],
    APPROVED_QUOTE: [INVOICE_PENDING],
    INVOICE_PENDING: [INVOICE_PROCESSING, CANCELLED],
    INVOICE_PROCESSING: [INVOICE_SHIPPED],
    INVOICE_SHIPPED: [INVOICE_DELIVERED],
    INVOICE_DELIVERED: [COMPLETED],
};
```

### **2. Proper Tax System**

```php
// Tax Configuration per Region
[
    'UAE' => [
        'tax_type' => 'VAT',
        'rate' => 5.00,
        'inclusive' => true,
        'exempt_business' => false
    ],
    'USA' => [
        'tax_type' => 'Sales Tax',
        'rate' => 0.00, // Varies by state
        'inclusive' => false,
        'exempt_business' => true
    ],
    'EU' => [
        'tax_type' => 'VAT',
        'rate' => 20.00,
        'inclusive' => true,
        'exempt_business' => false
    ]
]
```

### **3. Granular Permission System**

```php
// Spatie Permission Package
Permission::create(['name' => 'quote.browse']);
Permission::create(['name' => 'quote.create']);
Permission::create(['name' => 'quote.edit']);
Permission::create(['name' => 'quote.delete']);
Permission::create(['name' => 'quote.send']);
Permission::create(['name' => 'quote.approve']);
Permission::create(['name' => 'quote.convert_to_invoice']);

Permission::create(['name' => 'consignment.browse']);
Permission::create(['name' => 'consignment.create']);
Permission::create(['name' => 'consignment.edit']);
Permission::create(['name' => 'consignment.approve']);
Permission::create(['name' => 'consignment.mark_sold']);
Permission::create(['name' => 'consignment.mark_returned']);
```

### **4. Better Product Sync (Reference-Only)**

```php
// NO full product sync
// YES order-level product snapshots
// YES reference to external products

OrderItem::create([
    'external_product_id' => 'TS_12345',
    'external_source' => 'retail',
    'product_snapshot' => json_encode([
        'id' => 'TS_12345',
        'name' => 'Fuel Wheels - Maverick D610',
        'brand' => 'Fuel',
        'model' => 'Maverick',
        'finish' => 'Gloss Black',
        'size' => '20x9',
        'bolt_pattern' => '6x5.5',
        'offset' => '+1mm',
        'sku' => 'D61020906550',
        'price' => 299.00,
        'image' => 'https://...',
        'synced_at' => '2025-10-20 14:30:00'
    ])
]);
```

### **5. Improved Dashboard**

```
┌──────────────────────────────────────────────────────┐
│  FILTERS:                                            │
│  [Document Type] [Status] [Channel] [Date Range]    │
└──────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────┐
│  QUOTES TAB | INVOICES TAB | CONSIGNMENTS TAB       │
└──────────────────────────────────────────────────────┘

QUOTES VIEW:
- Draft Quotes (editable)
- Sent Quotes (awaiting approval)
- Approved Quotes (ready to convert)

INVOICES VIEW:
- Pending Invoices
- Processing Orders
- Shipped Orders
- Completed Orders

CONSIGNMENTS VIEW:
- Pending Approval
- Active Consignments
- Sold Items
- Returned Items
```

---

## 📝 SUMMARY FOR NEW SYSTEM

### **KEEP (Proven Good)**
✅ Unified orders table with document_type  
✅ Tax inclusive/exclusive per item (on orders, invoices, consignments!)  
✅ External product snapshot approach  
✅ **Dealer pricing system (model/brand/addon discounts)**  
✅ Hybrid product reference system  
✅ Consignment workflow with partial selling  
✅ Warranty claims with cost tracking  
✅ **Existing PDF templates (invoice, consignment)**  
✅ Consignment history tracking  
✅ SLA management on warranty claims  

### **IMPROVE (Make Better)**
🔧 Add proper state machine for transitions  
🔧 Add granular permission system  
🔧 Add tax configuration per region  
🔧 Add payment history tracking  
🔧 Add audit trail for all changes  
🔧 Add better dashboard with filters  
🔧 **Centralize dealer pricing service (use across ALL modules)**  
🔧 **Add consignment to unified dashboard**  
🔧 **Add warranty claims to unified dashboard**  
🔧 Better PDF generation service (reuse templates)  

### **REMOVE (Over-Engineered)**
❌ Full product catalog sync  
❌ Inventory management (use external as source)  
❌ Complex product relationships  
❌ Hard-coded role checks  

### **ADD (Missing Features)**
➕ State machine validation  
➕ **Unified consignment workflow (draft → sent → delivered → sold/returned)**  
➕ **Warranty claim automation (SLA, escalation)**  
➕ Payment installments  
➕ Multi-currency support  
➕ Regional tax rules  
➕ **Volume-based dealer pricing**  
➕ Quote expiry automation  
➕ Invoice reminders  
➕ **Consignment return reminders**  
➕ **Warranty claim analytics dashboard**  

### **CRITICAL REQUIREMENTS FOR NEW SYSTEM**

#### **1. Dealer Pricing Everywhere**
```
MUST activate dealer pricing in ALL modules when customer_type = 'dealer':
✅ Orders
✅ Quotes
✅ Invoices
✅ Consignments
✅ Warranty Replacements
✅ Manual entries
```

#### **2. Unified Document Workflow**
```
Quote → Invoice → Consignment
All share same:
- Tax system (tax_inclusive)
- Pricing logic (dealer discounts)
- Product references (hybrid approach)
- PDF templates (professional-invoice, professional-consignment)
```

#### **3. Complete Module Coverage**
```
Core Modules:
1. Orders/Quotes (UNIFIED with document_type)
2. Invoices (converted from quotes OR standalone)
3. Consignments (separate workflow, similar structure)
4. Warranty Claims (cost tracking, SLA management)

All connected via:
- Customer relationship
- Product snapshots
- Pricing service
- PDF generation service
```

#### **4. Reuse Existing Templates**
```
✅ professional-invoice.blade.php → Use for quotes, invoices, orders
✅ professional-consignment.blade.php → Use for consignments
✅ tax-invoice.blade.php → Use for tax-compliant invoices
✅ Same styling, same branding, tested layouts
```

---

## 💳 FINANCIAL TRANSACTION RECORDING SYSTEM

### **CRITICAL: Four Financial Recording Operations**

The system provides **four distinct financial recording operations** that track money flow and profitability:

1. **Record Payment** - Customer pays for invoice (partial or full)
2. **Record Expenses** - Track costs to calculate profit margin
3. **Record Sale** - Consignment item sold by customer
4. **Record Return** - Consignment item returned by customer

---

### **1. PAYMENT RECORDING SYSTEM**

#### **Purpose**
Track customer payments against invoices/orders, support partial payments, sync with Wafeq accounting system.

#### **Payment Model (app/Models/PaymentRecord.php)**

```php
class PaymentRecord extends Model
{
    protected $fillable = [
        'payment_number',        // PAY-20251020-0001 (auto-generated)
        'order_id',              // FK to orders table
        'invoice_id',            // FK to invoices table (nullable)
        'customer_id',           // FK to customers table
        'amount',                // Payment amount (DECIMAL 15,2)
        'payment_method',        // ENUM: cash, card, bank_transfer, cheque, credit
        'payment_date',          // Date payment received
        'transaction_id',        // Bank/gateway transaction reference
        'gateway',               // Payment gateway name (Stripe, PayPal, etc.)
        'notes',                 // Additional payment notes
        'wafeq_id',              // Wafeq accounting system ID
        'wafeq_sync_at',         // When synced to Wafeq
        'recorded_by',           // FK to users (who recorded payment)
    ];

    // Relationships
    public function order() {
        return $this->belongsTo(Order::class);
    }

    public function invoice() {
        return $this->belongsTo(Invoice::class);
    }

    public function customer() {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy() {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // Auto-generate payment number on creation
    protected static function boot() {
        parent::boot();
        
        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = self::generatePaymentNumber();
            }
        });
    }

    public static function generatePaymentNumber() {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', now())->count() + 1;
        return 'PAY-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        // Example: PAY-20251020-0001
    }

    // Mark payment as synced to Wafeq
    public function markAsSynced($wafeqId) {
        $this->update([
            'wafeq_id' => $wafeqId,
            'wafeq_sync_at' => now(),
        ]);
    }
}
```

#### **Recording Payment on Invoice (app/Models/Invoice.php)**

```php
class Invoice extends Model
{
    // Record a payment against this invoice
    public function recordPayment($amount, $method = 'cash', $transactionId = null, $gateway = null, $notes = null)
    {
        // Validate amount
        if ($amount <= 0) {
            throw new \Exception('Payment amount must be greater than zero');
        }

        if ($amount > $this->balance_due) {
            throw new \Exception('Payment amount cannot exceed balance due');
        }

        // Create payment record
        $payment = PaymentRecord::create([
            'invoice_id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'amount' => $amount,
            'payment_method' => $method,
            'payment_date' => now(),
            'transaction_id' => $transactionId,
            'gateway' => $gateway,
            'notes' => $notes,
            'recorded_by' => auth()->id(),
        ]);

        // Update invoice amounts
        $this->amount_paid += $amount;
        $this->balance_due = $this->total - $this->amount_paid;

        // Update invoice status
        if ($this->balance_due <= 0) {
            $this->status = 'paid';
            $this->payment_status = 'paid';
        } else {
            $this->status = 'partially_paid';
            $this->payment_status = 'partially_paid';
        }

        $this->save();

        // SYNC TO ORDER if invoice is linked to order
        if ($this->order_id) {
            $this->order->update([
                'paid_amount' => $this->amount_paid,
                'outstanding_amount' => $this->balance_due,
                'payment_status' => $this->payment_status,
            ]);
        }

        // Trigger Wafeq sync (handled by job queue)
        \App\Jobs\SyncPaymentToWafeq::dispatch($payment);

        return $payment;
    }

    // Get all payments for this invoice
    public function payments()
    {
        return $this->hasMany(PaymentRecord::class);
    }

    // Get total amount paid
    public function getTotalPaidAttribute()
    {
        return $this->payments()->sum('amount');
    }
}
```

#### **Payment Recording Flow**

```
┌─────────────────────────────────────────────────────────────────┐
│                    PAYMENT RECORDING WORKFLOW                    │
└─────────────────────────────────────────────────────────────────┘

1. User clicks "Record Payment" on invoice
   ↓
2. Form shows:
   - Amount to pay (max: balance_due)
   - Payment method (dropdown: cash, card, bank_transfer, cheque)
   - Transaction ID (optional)
   - Payment gateway (optional)
   - Notes (optional)
   ↓
3. On submit:
   - Create PaymentRecord with auto-generated payment_number
   - Update invoice: amount_paid += payment
   - Update invoice: balance_due = total - amount_paid
   - Update invoice status: paid / partially_paid
   - Sync to Order if linked
   ↓
4. Queue Wafeq sync job
   ↓
5. Show success message with payment number
   - "Payment PAY-20251020-0001 recorded successfully"
   - "Invoice balance: $500.00 remaining"
```

---

### **2. EXPENSE RECORDING SYSTEM**

#### **Purpose**
Track all costs associated with an invoice to calculate profit margin and gross profit.

#### **Expense Fields on Invoice (Migration: add_expense_fields_to_invoices_table.php)**

```php
Schema::table('invoices', function (Blueprint $table) {
    // 7 Expense Categories
    $table->decimal('cost_of_goods', 15, 2)->default(0);      // Product purchase cost
    $table->decimal('shipping_cost', 15, 2)->default(0);      // Shipping from supplier
    $table->decimal('duty_amount', 15, 2)->default(0);        // Import duties/taxes
    $table->decimal('delivery_fee', 15, 2)->default(0);       // Delivery to customer
    $table->decimal('installation_cost', 15, 2)->default(0);  // Installation charges
    $table->decimal('bank_fee', 15, 2)->default(0);           // Bank transfer fees
    $table->decimal('credit_card_fee', 15, 2)->default(0);    // Credit card processing

    // Calculated Fields (auto-computed)
    $table->decimal('total_expenses', 15, 2)->default(0);     // Sum of all expenses
    $table->decimal('gross_profit', 15, 2)->default(0);       // Total - total_expenses
    $table->decimal('profit_margin', 8, 2)->default(0);       // (gross_profit / total) * 100

    // Tracking
    $table->timestamp('expenses_recorded_at')->nullable();
    $table->foreignId('expenses_recorded_by')->nullable()->constrained('users');
});
```

#### **Recording Expenses on Invoice (app/Models/Invoice.php)**

```php
class Invoice extends Model
{
    /**
     * Record expenses for this invoice
     * 
     * @param array $expenseData [
     *   'cost_of_goods' => 1200.00,
     *   'shipping_cost' => 150.00,
     *   'duty_amount' => 80.00,
     *   'delivery_fee' => 50.00,
     *   'installation_cost' => 100.00,
     *   'bank_fee' => 10.00,
     *   'credit_card_fee' => 25.00
     * ]
     */
    public function recordExpenses(array $expenseData)
    {
        // Update expense fields
        $this->cost_of_goods = $expenseData['cost_of_goods'] ?? 0;
        $this->shipping_cost = $expenseData['shipping_cost'] ?? 0;
        $this->duty_amount = $expenseData['duty_amount'] ?? 0;
        $this->delivery_fee = $expenseData['delivery_fee'] ?? 0;
        $this->installation_cost = $expenseData['installation_cost'] ?? 0;
        $this->bank_fee = $expenseData['bank_fee'] ?? 0;
        $this->credit_card_fee = $expenseData['credit_card_fee'] ?? 0;

        // Record tracking
        $this->expenses_recorded_at = now();
        $this->expenses_recorded_by = auth()->id();

        // Auto-calculate profit
        $this->calculateProfit();

        return $this->save();
    }

    /**
     * Calculate total expenses and profit margin
     */
    public function calculateProfit()
    {
        // Sum all expense categories
        $this->total_expenses = 
            $this->cost_of_goods +
            $this->shipping_cost +
            $this->duty_amount +
            $this->delivery_fee +
            $this->installation_cost +
            $this->bank_fee +
            $this->credit_card_fee;

        // Gross Profit = Revenue - Total Expenses
        $this->gross_profit = $this->total - $this->total_expenses;

        // Profit Margin = (Gross Profit / Total) * 100
        if ($this->total > 0) {
            $this->profit_margin = ($this->gross_profit / $this->total) * 100;
        } else {
            $this->profit_margin = 0;
        }
    }

    /**
     * Check if expenses have been recorded
     */
    public function hasExpenses()
    {
        return !is_null($this->expenses_recorded_at);
    }

    /**
     * Get expense summary
     */
    public function getExpenseSummary()
    {
        return [
            'cost_of_goods' => $this->cost_of_goods,
            'shipping_cost' => $this->shipping_cost,
            'duty_amount' => $this->duty_amount,
            'delivery_fee' => $this->delivery_fee,
            'installation_cost' => $this->installation_cost,
            'bank_fee' => $this->bank_fee,
            'credit_card_fee' => $this->credit_card_fee,
            'total_expenses' => $this->total_expenses,
            'gross_profit' => $this->gross_profit,
            'profit_margin' => $this->profit_margin . '%',
        ];
    }
}
```

#### **Expense Recording Example**

```php
// Example: Record expenses for invoice
$invoice = Invoice::find(123);

$invoice->recordExpenses([
    'cost_of_goods' => 1200.00,    // What we paid for products
    'shipping_cost' => 150.00,     // Shipping from supplier
    'duty_amount' => 80.00,        // Import duties
    'delivery_fee' => 50.00,       // Delivery to customer
    'installation_cost' => 100.00, // Installation charges
    'bank_fee' => 10.00,           // Bank transfer fee
    'credit_card_fee' => 25.00     // CC processing fee
]);

// Auto-calculated:
// total_expenses = $1,615.00
// If invoice total = $2,000.00:
//   gross_profit = $2,000 - $1,615 = $385.00
//   profit_margin = ($385 / $2,000) * 100 = 19.25%

echo "Profit: $" . $invoice->gross_profit;
echo "Margin: " . $invoice->profit_margin . "%";
```

---

### **3. SALE RECORDING SYSTEM (Consignment)**

#### **Purpose**
Track when consignment items are sold by the customer (dealer/retailer).

#### **Recording Sale (app/Models/Consignment.php & ConsignmentController.php)**

```php
class Consignment extends Model
{
    /**
     * Record sale of consignment items
     * 
     * @param array $soldItems [
     *   ['item_id' => 1, 'quantity_sold' => 2, 'actual_sale_price' => 150.00],
     *   ['item_id' => 2, 'quantity_sold' => 1, 'actual_sale_price' => 200.00],
     * ]
     */
    public function recordSale(array $soldItems)
    {
        DB::beginTransaction();
        try {
            $itemsSoldCount = 0;
            $invoiceItems = [];

            foreach ($soldItems as $soldData) {
                $item = $this->items()->find($soldData['item_id']);
                
                if (!$item) continue;

                // Validate quantity
                $quantitySold = $soldData['quantity_sold'];
                $availableToSell = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
                
                if ($quantitySold > $availableToSell) {
                    throw new \Exception("Cannot sell more than available quantity");
                }

                // Update consignment item
                $item->quantity_sold += $quantitySold;
                $item->actual_sale_price = $soldData['actual_sale_price'];
                $item->date_sold = now();
                $item->status = 'sold';
                $item->save();

                $itemsSoldCount += $quantitySold;

                // Prepare invoice item
                $invoiceItems[] = [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $quantitySold,
                    'price' => $item->actual_sale_price,
                    'tax_inclusive' => $item->tax_inclusive,
                ];
            }

            // Update consignment totals
            $this->items_sold_count = $this->items()->sum('quantity_sold');

            // Update status based on sold/returned ratio
            $totalSent = $this->items_sent_count;
            $totalSold = $this->items_sold_count;
            $totalReturned = $this->items_returned_count;

            if ($totalSold + $totalReturned >= $totalSent) {
                // All items accounted for
                if ($totalSold == $totalSent) {
                    $this->status = 'invoiced_in_full';
                } else {
                    $this->status = 'partially_sold';
                }
            } else {
                $this->status = 'partially_sold';
            }

            $this->save();

            // Create invoice for sold items
            $invoice = $this->createInvoiceForSoldItems($invoiceItems);

            // Log history
            $this->histories()->create([
                'action' => 'sale_recorded',
                'description' => "$itemsSoldCount items sold, invoice #{$invoice->invoice_number} created",
                'performed_by' => auth()->id(),
            ]);

            DB::commit();
            return $invoice;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create invoice for sold consignment items
     */
    protected function createInvoiceForSoldItems(array $items)
    {
        $invoice = Invoice::create([
            'customer_id' => $this->customer_id,
            'consignment_id' => $this->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'pending',
            'notes' => "Invoice for consignment #{$this->consignment_number} sold items",
        ]);

        foreach ($items as $item) {
            $invoice->items()->create($item);
        }

        $invoice->calculateTotals();
        return $invoice;
    }
}
```

#### **Sale Recording Flow**

```
┌─────────────────────────────────────────────────────────────────┐
│                  CONSIGNMENT SALE RECORDING                      │
└─────────────────────────────────────────────────────────────────┘

1. Customer (dealer) sells consignment items to end customer
   ↓
2. User opens consignment and clicks "Record Sale"
   ↓
3. Form shows grid of consignment items:
   - Product name
   - Quantity sent
   - Quantity already sold
   - Quantity available to sell
   - Actual sale price (editable)
   - Quantity to record as sold (input)
   ↓
4. On submit:
   - Update ConsignmentItem: quantity_sold, actual_sale_price, date_sold
   - Update Consignment: items_sold_count, status
   - Create Invoice for sold items
   - Generate invoice number
   ↓
5. Invoice created and linked to consignment
   ↓
6. Show success message:
   - "Sale recorded! Invoice #INV-20251020-0001 created"
   - "3 items sold for $450.00"
```

---

### **4. RETURN RECORDING SYSTEM (Consignment)**

#### **Purpose**
Track when consignment items are returned by the customer (unsold items).

#### **Recording Return (app/Models/Consignment.php)**

```php
class Consignment extends Model
{
    /**
     * Record return of consignment items
     * 
     * @param array $returnedItems [
     *   ['item_id' => 3, 'quantity_returned' => 1],
     *   ['item_id' => 4, 'quantity_returned' => 2],
     * ]
     */
    public function recordReturn(array $returnedItems)
    {
        DB::beginTransaction();
        try {
            $itemsReturnedCount = 0;

            foreach ($returnedItems as $returnData) {
                $item = $this->items()->find($returnData['item_id']);
                
                if (!$item) continue;

                // Validate quantity
                $quantityReturned = $returnData['quantity_returned'];
                $availableToReturn = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
                
                if ($quantityReturned > $availableToReturn) {
                    throw new \Exception("Cannot return more than available quantity");
                }

                // Update consignment item
                $item->quantity_returned += $quantityReturned;
                $item->date_returned = now();
                $item->status = 'returned';
                $item->save();

                // Add back to warehouse inventory
                $this->addBackToInventory($item->product_id, $quantityReturned);

                $itemsReturnedCount += $quantityReturned;
            }

            // Update consignment totals
            $this->items_returned_count = $this->items()->sum('quantity_returned');

            // Update status based on sold/returned ratio
            $totalSent = $this->items_sent_count;
            $totalSold = $this->items_sold_count;
            $totalReturned = $this->items_returned_count;

            if ($totalSold + $totalReturned >= $totalSent) {
                // All items accounted for
                if ($totalReturned == $totalSent) {
                    $this->status = 'returned_in_full';
                } elseif ($totalSold > 0) {
                    $this->status = 'partially_sold';
                } else {
                    $this->status = 'partially_returned';
                }
            } else {
                $this->status = 'in_progress';
            }

            $this->save();

            // Log history
            $this->histories()->create([
                'action' => 'return_recorded',
                'description' => "$itemsReturnedCount items returned to warehouse",
                'performed_by' => auth()->id(),
            ]);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add returned items back to warehouse inventory
     */
    protected function addBackToInventory($productId, $quantity)
    {
        // Update inventory table (if using internal inventory)
        // OR just log the return (if using external inventory system)
        
        // Option 1: Internal inventory
        $inventory = Inventory::where('product_id', $productId)
            ->where('warehouse_id', config('consignment.default_warehouse'))
            ->first();
        
        if ($inventory) {
            $inventory->quantity += $quantity;
            $inventory->save();
        }

        // Option 2: Just log (if external system is source of truth)
        InventoryLog::create([
            'product_id' => $productId,
            'type' => 'consignment_return',
            'quantity' => $quantity,
            'reference_type' => 'consignment',
            'reference_id' => $this->id,
        ]);
    }
}
```

#### **Return Recording Flow**

```
┌─────────────────────────────────────────────────────────────────┐
│                 CONSIGNMENT RETURN RECORDING                     │
└─────────────────────────────────────────────────────────────────┘

1. Customer (dealer) returns unsold consignment items
   ↓
2. User opens consignment and clicks "Record Return"
   ↓
3. Form shows grid of consignment items:
   - Product name
   - Quantity sent
   - Quantity already sold
   - Quantity already returned
   - Quantity available to return
   - Quantity to record as returned (input)
   ↓
4. On submit:
   - Update ConsignmentItem: quantity_returned, date_returned, status
   - Update Consignment: items_returned_count, status
   - Add items back to warehouse inventory
   - Log inventory movement
   ↓
5. Update consignment status:
   - If all returned: "returned_in_full"
   - If some sold, some returned: "partially_sold"
   - If still pending items: "in_progress"
   ↓
6. Show success message:
   - "Return recorded! 2 items added back to warehouse"
   - "Consignment status: Partially Sold (3 sold, 2 returned, 1 pending)"
```

---

### **5. WAFEQ ACCOUNTING INTEGRATION**

#### **Sync Financial Transactions to Wafeq**

```php
// app/Jobs/SyncPaymentToWafeq.php
class SyncPaymentToWafeq implements ShouldQueue
{
    protected $payment;

    public function handle()
    {
        $wafeqService = app(WafeqService::class);

        // Sync payment to Wafeq accounting
        $response = $wafeqService->createPayment([
            'customer_id' => $this->payment->customer->wafeq_id,
            'invoice_id' => $this->payment->invoice->wafeq_id,
            'amount' => $this->payment->amount,
            'payment_method' => $this->payment->payment_method,
            'payment_date' => $this->payment->payment_date,
            'reference' => $this->payment->payment_number,
        ]);

        if ($response['success']) {
            $this->payment->markAsSynced($response['wafeq_id']);
        }
    }
}
```

#### **Wafeq Sync Flow**

```
┌─────────────────────────────────────────────────────────────────┐
│                    WAFEQ ACCOUNTING SYNC                         │
└─────────────────────────────────────────────────────────────────┘

1. Payment/Expense/Invoice created in CRM
   ↓
2. Queue Wafeq sync job
   ↓
3. Job sends data to Wafeq API:
   - Customer details
   - Invoice details
   - Payment/Expense details
   ↓
4. Wafeq returns sync ID
   ↓
5. Update CRM record:
   - wafeq_id = returned ID
   - wafeq_sync_at = now()
   ↓
6. If sync fails:
   - Log error
   - Retry (up to 3 times)
   - Mark as "sync_failed" for manual review
```

---

### **6. FINANCIAL REPORTING CAPABILITIES**

With the financial transaction recording system, you can generate:

#### **Payment Reports**
- Total payments received (by date range, customer, payment method)
- Outstanding invoices (balance_due > 0)
- Partially paid invoices
- Payment trends over time
- Customer payment history

#### **Expense Reports**
- Profit margin per invoice
- Expense breakdown by category
- Most profitable customers
- Most profitable products
- Gross profit trends
- Cost analysis (shipping vs duty vs delivery, etc.)

#### **Consignment Reports**
- Items out on consignment
- Items sold from consignment
- Items returned from consignment
- Consignment profitability
- Customer sell-through rates

#### **Financial Dashboard Metrics**
```php
// Example queries for dashboard
$metrics = [
    'total_revenue' => Invoice::where('status', 'paid')->sum('total'),
    'total_paid' => PaymentRecord::sum('amount'),
    'outstanding_amount' => Invoice::sum('balance_due'),
    'total_profit' => Invoice::where('status', 'paid')->sum('gross_profit'),
    'average_margin' => Invoice::where('status', 'paid')->avg('profit_margin'),
    'consignment_value' => Consignment::where('status', 'delivered')->sum('total'),
    'items_on_consignment' => ConsignmentItem::whereIn('status', ['sent', 'delivered'])->sum('quantity_sent'),
];
```

---

## 🎯 FINANCIAL TRANSACTIONS SUMMARY

### **What Must Be Included in New System**

✅ **Payment Recording**
- PaymentRecord model with auto-generated payment numbers
- Support for partial payments
- Multiple payment methods (cash, card, bank transfer, cheque)
- Wafeq accounting sync
- Payment history tracking

✅ **Expense Recording**
- 7 expense categories on invoices
- Auto-calculated profit margin
- Expense tracking by user and date
- Financial reporting capabilities

✅ **Sale Recording (Consignment)**
- Track items sold from consignment
- Auto-create invoices for sold items
- Update consignment status
- Link sales to customer

✅ **Return Recording (Consignment)**
- Track items returned from consignment
- Add items back to inventory
- Update consignment status
- Log return history

✅ **Wafeq Integration**
- Queue-based sync jobs
- Error handling and retry logic
- Sync tracking (wafeq_id, wafeq_sync_at)
- Manual sync option for failed transactions

---

**END OF RESEARCH FINDINGS**
