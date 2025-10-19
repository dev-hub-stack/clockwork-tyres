# Consignment, Invoice & Warranty Modules - Complete Architecture Documentation

## ⚠️ CRITICAL: Financial Transaction Recording

**MOST IMPORTANT FEATURES:**
1. ✅ **Record Payment** - Track customer payments (partial/full) with Wafeq sync
2. ✅ **Record Expenses** - 7 expense categories with auto profit calculation
3. ✅ **Record Sale** - Consignment items sold by customer (auto-create invoice)
4. ✅ **Record Return** - Consignment items returned (add back to inventory)
5. ✅ **Dealer Pricing** - Applies in consignments, invoices, warranties

**These four operations provide complete financial visibility and are essential for business operations.**

---

## Table of Contents
1. [Consignment Module](#part-1-consignment-module)
2. [Invoice Module](#part-2-invoice-module)
3. [Warranty Claims Module](#part-3-warranty-claims-module)

---

# PART 1: CONSIGNMENT MODULE

## Overview
The Consignment module manages the full lifecycle of products sent to customers on trial/consignment basis, with tracking for items sent, sold, and returned.

**Last Updated:** October 20, 2025  
**Module Location:** `app/Models/Consignment.php`, `app/Models/ConsignmentItem.php`  
**Tech Stack:** Laravel 12 + PostgreSQL 15 + Filament v3

---

## Consignment Workflow

```
┌────────────────────────────────────────────────────────────────┐
│              CONSIGNMENT LIFECYCLE                              │
└────────────────────────────────────────────────────────────────┘

1. Draft → Create consignment with items
   ↓
2. Sent → Send items to customer (track quantity_sent)
   ↓
3. Delivered → Customer confirms receipt
   ↓
4. Partially Sold → Customer sells some items (Record Sale)
   ↓
5. Options:
   a) Invoiced in Full → All items sold (create invoice)
   b) Partially Returned → Some items returned (Record Return)
   c) Returned in Full → All items returned
```

---

## Database Schema

### Consignments Table

```sql
CREATE TABLE consignments (
    id BIGSERIAL PRIMARY KEY,
    consignment_number VARCHAR(255) UNIQUE,
    customer_id BIGINT REFERENCES customers(id),
    status VARCHAR(50) DEFAULT 'draft',
    
    -- Item tracking
    items_sent_count INTEGER DEFAULT 0,
    items_sold_count INTEGER DEFAULT 0,
    items_returned_count INTEGER DEFAULT 0,
    
    -- Financial
    subtotal DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    
    -- Dates
    sent_at TIMESTAMP,
    delivered_at TIMESTAMP,
    
    -- Metadata
    notes TEXT,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_consignments_customer_id ON consignments(customer_id);
CREATE INDEX idx_consignments_status ON consignments(status);
```

### Consignment Items Table

```sql
CREATE TABLE consignment_items (
    id BIGSERIAL PRIMARY KEY,
    consignment_id BIGINT REFERENCES consignments(id),
    product_id BIGINT REFERENCES products(id),
    addon_id BIGINT REFERENCES add_ons(id),
    
    -- Product snapshot
    product_snapshot JSONB,
    product_name VARCHAR(255),
    brand_name VARCHAR(255),
    sku VARCHAR(255),
    
    -- Quantity tracking
    quantity_sent INTEGER DEFAULT 0,
    quantity_sold INTEGER DEFAULT 0,
    quantity_returned INTEGER DEFAULT 0,
    
    -- Pricing
    price DECIMAL(15,2),
    actual_sale_price DECIMAL(15,2),  -- Price when sold
    tax_inclusive BOOLEAN DEFAULT TRUE,
    
    -- Status & dates
    status VARCHAR(50) DEFAULT 'sent',  -- sent, sold, returned
    date_sold TIMESTAMP,
    date_returned TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_consignment_items_consignment_id ON consignment_items(consignment_id);
CREATE INDEX idx_consignment_items_status ON consignment_items(status);
```

### Consignment Histories Table

```sql
CREATE TABLE consignment_histories (
    id BIGSERIAL PRIMARY KEY,
    consignment_id BIGINT REFERENCES consignments(id),
    action VARCHAR(100),  -- 'sale_recorded', 'return_recorded', 'status_changed'
    description TEXT,
    performed_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP
);
```

---

## 💰 RECORD SALE (Critical Feature)

### **Purpose**
Track when customer sells consignment items to end customers.

### **Implementation**

```php
// app/Models/Consignment.php
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
                'addon_id' => $item->addon_id,
                'product_snapshot' => $item->product_snapshot,
                'product_name' => $item->product_name,
                'brand_name' => $item->brand_name,
                'sku' => $item->sku,
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

protected function createInvoiceForSoldItems(array $items)
{
    $invoice = Invoice::create([
        'customer_id' => $this->customer_id,
        'consignment_id' => $this->id,
        'invoice_number' => Invoice::generateInvoiceNumber(),
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'notes' => "Invoice for consignment #{$this->consignment_number} sold items",
    ]);

    foreach ($items as $item) {
        $invoice->items()->create($item);
    }

    $invoice->calculateTotals();
    return $invoice;
}
```

---

## 🔄 RECORD RETURN (Critical Feature)

### **Purpose**
Track when customer returns unsold consignment items.

### **Implementation**

```php
// app/Models/Consignment.php
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
            $this->addBackToInventory($item->product_id, $item->addon_id, $quantityReturned);

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

protected function addBackToInventory($productId, $addonId, $quantity)
{
    // Log return (external system handles actual inventory)
    InventoryLog::create([
        'product_id' => $productId,
        'addon_id' => $addonId,
        'type' => 'consignment_return',
        'quantity' => $quantity,
        'reference_type' => 'consignment',
        'reference_id' => $this->id,
        'notes' => "Returned from consignment #{$this->consignment_number}",
    ]);

    // Optionally call external API to update inventory
    // Http::post(config('external.inventory_api'), [...]);
}
```

---

## 🎯 DEALER PRICING IN CONSIGNMENTS

```php
// When creating consignment, apply dealer pricing
public function addItem($product, $quantity, $customer)
{
    $dealerPricingService = app(DealerPricingService::class);
    $snapshotService = app(ProductSnapshotService::class);
    
    // CRITICAL: Apply dealer pricing to consignments
    $price = $dealerPricingService->calculatePrice($customer, $product, 'product');
    $snapshot = $snapshotService->createSnapshot($product);
    
    ConsignmentItem::create([
        'consignment_id' => $this->id,
        'product_id' => $product->id,
        'product_snapshot' => json_encode($snapshot),
        'product_name' => $product->name,
        'brand_name' => $product->brand->name ?? null,
        'sku' => $product->sku,
        'quantity_sent' => $quantity,
        'price' => $price,
        'tax_inclusive' => $product->tax_inclusive ?? true,
    ]);
}
```

---

# PART 2: INVOICE MODULE

## Overview
The Invoice module handles billing with financial transaction recording (payments, expenses, profit calculation) and Wafeq accounting integration.

**Module Location:** `app/Models/Invoice.php`, `app/Models/InvoiceItem.php`, `app/Models/PaymentRecord.php`

---

## Database Schema

### Invoices Table

```sql
CREATE TABLE invoices (
    id BIGSERIAL PRIMARY KEY,
    invoice_number VARCHAR(255) UNIQUE,
    order_id BIGINT REFERENCES orders(id),  -- Linked to unified orders table
    consignment_id BIGINT REFERENCES consignments(id),
    customer_id BIGINT REFERENCES customers(id),
    
    -- Dates
    invoice_date DATE,
    due_date DATE,
    
    -- Financial
    subtotal DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total DECIMAL(15,2) DEFAULT 0,
    amount_paid DECIMAL(15,2) DEFAULT 0,
    balance_due DECIMAL(15,2) DEFAULT 0,
    
    -- Status
    status VARCHAR(50) DEFAULT 'pending',  -- pending, paid, partially_paid, overdue, cancelled
    payment_status VARCHAR(50) DEFAULT 'unpaid',  -- unpaid, partially_paid, paid
    
    -- Expense tracking (7 categories)
    cost_of_goods DECIMAL(15,2) DEFAULT 0,
    shipping_cost DECIMAL(15,2) DEFAULT 0,
    duty_amount DECIMAL(15,2) DEFAULT 0,
    delivery_fee DECIMAL(15,2) DEFAULT 0,
    installation_cost DECIMAL(15,2) DEFAULT 0,
    bank_fee DECIMAL(15,2) DEFAULT 0,
    credit_card_fee DECIMAL(15,2) DEFAULT 0,
    
    -- Calculated profit fields
    total_expenses DECIMAL(15,2) DEFAULT 0,
    gross_profit DECIMAL(15,2) DEFAULT 0,
    profit_margin DECIMAL(8,2) DEFAULT 0,  -- Percentage
    
    -- Expense tracking metadata
    expenses_recorded_at TIMESTAMP,
    expenses_recorded_by BIGINT REFERENCES users(id),
    
    -- Wafeq integration
    wafeq_id VARCHAR(255),
    wafeq_sync_at TIMESTAMP,
    
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_invoices_customer_id ON invoices(customer_id);
CREATE INDEX idx_invoices_order_id ON invoices(order_id);
CREATE INDEX idx_invoices_payment_status ON invoices(payment_status);
CREATE INDEX idx_invoices_wafeq_id ON invoices(wafeq_id);
```

### Invoice Items Table

```sql
CREATE TABLE invoice_items (
    id BIGSERIAL PRIMARY KEY,
    invoice_id BIGINT REFERENCES invoices(id),
    product_id BIGINT REFERENCES products(id),
    addon_id BIGINT REFERENCES add_ons(id),
    
    -- Product snapshot
    product_snapshot JSONB,
    product_name VARCHAR(255),
    brand_name VARCHAR(255),
    sku VARCHAR(255),
    
    -- Pricing
    price DECIMAL(15,2),
    quantity INTEGER,
    tax_inclusive BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Payment Records Table (NEW)

```sql
CREATE TABLE payment_records (
    id BIGSERIAL PRIMARY KEY,
    payment_number VARCHAR(255) UNIQUE,  -- PAY-20251020-0001
    order_id BIGINT REFERENCES orders(id),
    invoice_id BIGINT REFERENCES invoices(id),
    customer_id BIGINT REFERENCES customers(id),
    
    -- Payment details
    amount DECIMAL(15,2),
    payment_method VARCHAR(50),  -- cash, card, bank_transfer, cheque, credit
    payment_date DATE,
    transaction_id VARCHAR(255),
    gateway VARCHAR(100),
    notes TEXT,
    
    -- Wafeq integration
    wafeq_id VARCHAR(255),
    wafeq_sync_at TIMESTAMP,
    
    -- Tracking
    recorded_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_payment_records_customer_id ON payment_records(customer_id);
CREATE INDEX idx_payment_records_invoice_id ON payment_records(invoice_id);
CREATE INDEX idx_payment_records_payment_method ON payment_records(payment_method);
CREATE INDEX idx_payment_records_wafeq_id ON payment_records(wafeq_id);
```

---

## 💳 RECORD PAYMENT (Critical Feature)

### **Purpose**
Track customer payments (partial or full) with auto-generated payment numbers and Wafeq sync.

### **Implementation**

```php
// app/Models/Invoice.php
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
        'payment_number' => PaymentRecord::generatePaymentNumber(),
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
```

### **PaymentRecord Model**

```php
// app/Models/PaymentRecord.php
class PaymentRecord extends Model
{
    protected $fillable = [
        'payment_number',
        'order_id',
        'invoice_id',
        'customer_id',
        'amount',
        'payment_method',
        'payment_date',
        'transaction_id',
        'gateway',
        'notes',
        'wafeq_id',
        'wafeq_sync_at',
        'recorded_by',
    ];

    public static function generatePaymentNumber()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', now())->count() + 1;
        return 'PAY-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        // Example: PAY-20251020-0001
    }

    public function markAsSynced($wafeqId)
    {
        $this->update([
            'wafeq_id' => $wafeqId,
            'wafeq_sync_at' => now(),
        ]);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
```

---

## 💰 RECORD EXPENSES (Critical Feature)

### **Purpose**
Track 7 expense categories with auto profit calculation.

### **Implementation**

```php
// app/Models/Invoice.php
public function recordExpenses(array $expenseData)
{
    // Update 7 expense categories
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
        'profit_margin' => round($this->profit_margin, 2) . '%',
    ];
}
```

---

# PART 3: WARRANTY CLAIMS MODULE

## Overview
Warranty Claims module with cost tracking, SLA management, and dealer pricing for replacements.

---

## Database Schema

### Warranty Claims Table

```sql
CREATE TABLE warranty_claims (
    id BIGSERIAL PRIMARY KEY,
    claim_number VARCHAR(255) UNIQUE,
    customer_id BIGINT REFERENCES customers(id),
    order_id BIGINT REFERENCES orders(id),
    invoice_id BIGINT REFERENCES invoices(id),
    
    -- Status & workflow
    status VARCHAR(50) DEFAULT 'draft',
    priority VARCHAR(20) DEFAULT 'normal',
    
    -- Cost tracking
    replacement_cost DECIMAL(15,2) DEFAULT 0,
    refund_amount DECIMAL(15,2) DEFAULT 0,
    shipping_cost DECIMAL(15,2) DEFAULT 0,
    total_claim_cost DECIMAL(15,2) DEFAULT 0,
    
    -- SLA management
    sla_due_date TIMESTAMP,
    sla_breached BOOLEAN DEFAULT FALSE,
    auto_escalated BOOLEAN DEFAULT FALSE,
    
    -- Resolution
    resolution_type VARCHAR(50),  -- replacement, refund, repair
    resolution_notes TEXT,
    resolved_at TIMESTAMP,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### **Dealer Pricing for Warranty Replacements**

```php
// app/Http/Controllers/WarrantyClaimController.php
public function approveReplacement(WarrantyClaim $claim, $replacementProductId)
{
    $customer = $claim->customer;
    $product = Product::find($replacementProductId);
    $dealerPricingService = app(DealerPricingService::class);

    // CRITICAL: Apply dealer pricing to replacement cost
    $replacementCost = $dealerPricingService->calculatePrice($customer, $product, 'product');
    
    $claim->update([
        'replacement_cost' => $replacementCost,
        'resolution_type' => 'replacement',
        'status' => 'approved',
    ]);
}
```

---

## Related Documentation
- [Orders Module](ARCHITECTURE_ORDERS_MODULE.md) - Quote to invoice conversion
- [Customers Module](ARCHITECTURE_CUSTOMERS_MODULE.md) - Dealer pricing service
- [Research Findings](RESEARCH_FINDINGS.md) - Complete financial transaction research

---

## Changelog
- **2025-10-20:** Complete documentation created
- **2025-10-20:** Added financial transaction recording (payment, expense, sale, return)
- **2025-10-20:** Added Wafeq accounting integration
- **2025-10-20:** Added dealer pricing for all modules
- **2025-10-20:** Added consignment lifecycle management
- **2025-10-20:** Updated to Laravel 12 + PostgreSQL 15
