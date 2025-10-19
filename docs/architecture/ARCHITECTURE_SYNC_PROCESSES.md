# Complete Sync Processes Documentation

## ⚠️ CRITICAL: UPDATED SYNC APPROACH

**MOST IMPORTANT CHANGES:**
1. ✅ **Unified Orders Table** - Use `document_type` ENUM('quote', 'invoice', 'order') instead of separate tables
2. ✅ **Snapshot-Based Product Sync** - Capture product/variant data at order time (NOT full catalog sync)
3. ✅ **Wafeq Accounting Sync** - Queue-based integration for payments, expenses, invoices
4. ✅ **On-Demand Product Sync** - Sync products only when needed for orders
5. ✅ **Dealer Pricing in Sync** - Apply DealerPricingService during order sync for dealer customers

**This system uses snapshot approach - full catalog sync is NOT recommended.**

---

## Overview
The Reporting CRM receives data from two primary sources: **TunerStop Admin** (retail e-commerce) and **Wholesale Admin** (B2B platform). This document details all synchronization processes, APIs, workflows, and data transformations.

**Last Updated:** October 20, 2025  
**Tech Stack:** Laravel 12 (LTS) + PostgreSQL 15 + Filament v3

---

## Table of Contents
1. [System Architecture](#system-architecture)
2. [Product Sync from TunerStop](#product-sync-from-tunerstop)
3. [Order Sync from TunerStop](#order-sync-from-tunerstop)
4. [Order Sync from Wholesale](#order-sync-from-wholesale)
5. [Sync APIs](#sync-apis)
6. [Data Mapping](#data-mapping)
7. [Error Handling](#error-handling)

---

## System Architecture

```
┌──────────────────────┐
│   TunerStop Admin    │ (Primary E-Commerce System)
│  Laravel Application │
└──────────┬───────────┘
           │
           │ API Calls (Products, Orders, Customers)
           │
           ▼
┌──────────────────────┐
│   Reporting CRM      │ (Central Management System)
│  Laravel Application │
└──────────┬───────────┘
           ▲
           │
           │ API Calls (Orders, Inventory)
           │
┌──────────┴───────────┐
│  Wholesale Admin     │ (B2B Platform)
│  Laravel Application │
└──────────────────────┘
```

### Data Flow Direction

**FROM TunerStop TO Reporting:**
- Products (with variants, images)
- Retail Orders
- Customers (retail)
- Inventory updates

**FROM Wholesale TO Reporting:**
- Wholesale Orders
- Customers (dealers)

**FROM Reporting TO TunerStop/Wholesale:**
- Inventory updates (future)
- Order status updates (future)

---

## Product Sync from TunerStop

### Trigger Points

1. **Automatic Sync:**
   - Product created in TunerStop
   - Product updated in TunerStop
   - Variant added/updated
   - Images changed

2. **Manual Sync:**
   - Admin clicks "Sync to Reporting" button
   - Bulk sync via artisan command

### Sync Service
**File:** `app/Services/ProductSyncService.php` (Reporting)  
**Source File:** `app/Services/ReportingSyncService.php` (TunerStop)

### API Endpoint
```
POST https://reporting.domain.com/api/sync/product
Authorization: Bearer {REPORTING_API_TOKEN}
Content-Type: application/json
```

### Payload Structure
```json
{
  "id": 123,
  "name": "BBS CH-R",
  "product_full_name": "BBS CH-R Forged Wheel",
  "slug": "bbs-ch-r",
  "brand_id": 5,
  "model_id": 45,
  "finish_id": 12,
  "price": 450.00,
  "construction": "Forged",
  "status": "active",
  "seo_title": "BBS CH-R Forged Wheels | TunerStop",
  "meta_description": "High-quality BBS CH-R forged wheels...",
  "meta_keywords": "BBS, CH-R, forged, wheels",
  "images": [
    "products/bbs-ch-r-1.jpg",
    "products/bbs-ch-r-2.jpg",
    "products/bbs-ch-r-3.jpg"
  ],
  "variants": [
    {
      "id": 456,
      "sku": "BBS-CH-R-20-85-BM",
      "size": "20x8.5",
      "bolt_pattern": "5x120",
      "hub_bore": "72.6mm",
      "offset": "35mm",
      "weight": "10.5 KG",
      "backspacing": "5.88 inches",
      "lipsize": "2.0 inches",
      "finish": "Brilliant Silver",
      "max_wheel_load": "800 KG",
      "rim_diameter": "20",
      "rim_width": "8.5",
      "price": 450.00,
      "us_retail_price": 450.00,
      "uae_retail_price": 1650.00,
      "cost": 300.00,
      "sale_price": null,
      "clearance_corner": false,
      "construction": "Forged",
      "stock_quantity": 12
    },
    {
      "id": 457,
      "sku": "BBS-CH-R-20-95-BM",
      "size": "20x9.5",
      // ... similar structure
    }
  ]
}
```

### Processing Flow

**TunerStop Side (Sender):**
```php
// In TunerStop Admin
use App\Services\ReportingSyncService;

$syncService = new ReportingSyncService();

// Sync single product
$result = $syncService->syncProduct($product);

// Sync product with variants
$result = $syncService->syncProductWithVariants($product);
```

**Reporting Side (Receiver):**
```php
// In Reporting CRM
use App\Services\ProductSyncService;

public function syncProduct(Request $request)
{
    $syncService = new ProductSyncService();
    $result = $syncService->syncProduct($request->all());
    
    return response()->json($result);
}
```

### Processing Steps

1. **Validate Request:**
   - Check API authentication
   - Validate required fields
   - Check data structure

2. **Map IDs (Brand/Model/Finish):**
   - Use mapping services to convert TunerStop IDs to Reporting IDs
   - Auto-create if mapping doesn't exist

3. **UPSERT Product:**
   - Check if product exists by `external_id`
   - Update if exists, create if new

4. **Sync Variants:**
   - Loop through variants array
   - UPSERT each variant by SKU

5. **Sync Images:**
   - Delete existing images
   - Create new image records

6. **Update Timestamps:**
   - Set `synced_at`, `sync_status`

7. **Return Response:**
```json
{
  "success": true,
  "product_id": 789,
  "external_id": 123,
  "main_sku": "BBS-CH-R-20-85-BM",
  "message": "Product synced successfully"
}
```

### Mapping Services

**BrandMappingService:**
```php
// Database table: brand_mappings
// Columns: tunerstop_brand_id, reporting_brand_id

public function mapBrandId($tunerstopId)
{
    $mapping = DB::table('brand_mappings')
        ->where('tunerstop_brand_id', $tunerstopId)
        ->first();
    
    return $mapping ? $mapping->reporting_brand_id : null;
}
```

**ModelMappingService:**
```php
// Database table: model_mappings
// Columns: tunerstop_model_id, reporting_model_id

public function mapModelId($tunerstopId)
{
    $mapping = DB::table('model_mappings')
        ->where('tunerstop_model_id', $tunerstopId)
        ->first();
    
    return $mapping ? $mapping->reporting_model_id : null;
}
```

**FinishMappingService:**
```php
// Database table: finish_mappings
// Columns: tunerstop_finish_id, reporting_finish_id

public function mapFinishId($tunerstopId)
{
    $mapping = DB::table('finish_mappings')
        ->where('tunerstop_finish_id', $tunerstopId)
        ->first();
    
    return $mapping ? $mapping->reporting_finish_id : null;
}
```

### Auto-Create Missing Mappings

If mapping doesn't exist, system automatically:
1. Fetches data from TunerStop DB
2. Creates corresponding record in Reporting
3. Creates mapping entry
4. Logs action

**Example:**
```php
protected function createBrandFromTunerStop($tunerstopBrandId)
{
    // Connect to TunerStop DB
    $tunerstopBrand = DB::connection('tunerstop')
        ->table('brands')
        ->where('id', $tunerstopBrandId)
        ->first();
    
    if (!$tunerstopBrand) return null;
    
    // Create in Reporting
    $reportingBrand = Brand::create([
        'external_id' => $tunerstopBrandId,
        'name' => $tunerstopBrand->name,
        'slug' => Str::slug($tunerstopBrand->name),
        // ... other fields
    ]);
    
    // Create mapping
    DB::table('brand_mappings')->insert([
        'tunerstop_brand_id' => $tunerstopBrandId,
        'reporting_brand_id' => $reportingBrand->id,
    ]);
    
    return $reportingBrand->id;
}
```

---

## Order Sync from TunerStop

### Trigger Points

1. **Order Placed:**
   - Customer completes checkout
   - Payment successful

2. **Order Status Change:**
   - Admin updates order status
   - Tracking number added

3. **Manual Sync:**
   - Admin triggers sync
   - Scheduled job runs

### API Endpoint
```
POST https://reporting.domain.com/api/sync/order
Authorization: Bearer {REPORTING_API_TOKEN}
```

### Payload Structure
```json
{
  "external_order_id": "TS-2025-12345",
  "order_number": "TS-2025-12345",
  "session_id": "sess_abc123",
  "customer_email": "customer@example.com",
  "customer_first_name": "John",
  "customer_last_name": "Doe",
  "customer_phone": "+971501234567",
  "order_date": "2025-10-20 14:30:00",
  "status": "pending",
  "payment_status": "paid",
  "payment_method": "credit_card",
  "payment_type": "full",
  "currency": "AED",
  "sub_total": 5000.00,
  "tax": 0.00,
  "vat": 250.00,
  "shipping": 250.00,
  "discount": 100.00,
  "total": 5400.00,
  "total_quantity": 4,
  "delivery_options": "Delivery",
  "order_notes": "Please deliver after 5 PM",
  "vehicle_year": "2023",
  "vehicle_make": "Toyota",
  "vehicle_model": "Land Cruiser",
  "vehicle_sub_model": "GXR",
  "update_inventory": true,
  "items": [
    {
      "sku": "BBS-CH-R-20-85-BM",
      "name": "BBS CH-R 20x8.5 Brilliant Silver",
      "quantity": 4,
      "price": 1250.00,
      "total": 5000.00,
      "product_id": 123,
      "variant_id": 456,
      "warehouse_quantities": [
        {
          "warehouse_id": 1,
          "quantity": 4
        }
      ]
    }
  ],
  "billing_address": {
    "first_name": "John",
    "last_name": "Doe",
    "address": "123 Main Street, Apartment 4B",
    "city": "Dubai",
    "state": "Dubai",
    "country": "United Arab Emirates",
    "zip": "00000",
    "phone_no": "+971501234567",
    "email": "customer@example.com"
  },
  "shipping_address": {
    "first_name": "John",
    "last_name": "Doe",
    "address": "123 Main Street, Apartment 4B",
    "city": "Dubai",
    "state": "Dubai",
    "country": "United Arab Emirates",
    "zip": "00000",
    "phone_no": "+971501234567",
    "email": "customer@example.com"
  }
}
```

### Processing Flow

**TunerStop Side:**
```php
// In TunerStop Observer/Controller
use App\Services\ReportingSyncService;

$syncService = new ReportingSyncService();
$result = $syncService->syncOrder($order);
```

**Reporting Side:**
```php
// In Reporting OrderSyncService
public function syncOrder(array $orderData): array
{
    DB::beginTransaction();
    
    try {
        // 1. Validate
        $this->validateOrderData($orderData);
        
        // 2. Check duplicates
        if ($this->orderExists($orderData['external_order_id'])) {
            return ['success' => false, 'message' => 'Order already exists'];
        }
        
        // 3. Find or create customer
        $customer = $this->findOrCreateCustomer($orderData);
        
        // 4. Create order
        $order = $this->createOrder($orderData, $customer);
        
        // 5. Create order items
        $this->createOrderItems($order, $orderData['items']);
        
        // 6. Create addresses
        $this->createAddresses($order, $orderData);
        
        // 7. Update inventory
        if ($orderData['update_inventory'] ?? true) {
            $this->updateInventory($orderData['items']);
        }
        
        DB::commit();
        
        return [
            'success' => true,
            'order_id' => $order->id,
            'external_order_id' => $orderData['external_order_id']
        ];
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Order sync failed", ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

### Customer Handling

**Find or Create Customer:**
```php
protected function findOrCreateCustomer(array $orderData): Customer
{
    // Try to find existing customer by email
    $customer = Customer::where('email', $orderData['customer_email'])->first();
    
    if ($customer) {
        return $customer;
    }
    
    // Create new customer
    $customer = new Customer();
    $customer->customer_type = 'retail';
    $customer->first_name = $orderData['customer_first_name'];
    $customer->last_name = $orderData['customer_last_name'];
    $customer->email = $orderData['customer_email'];
    $customer->phone = $orderData['customer_phone'] ?? null;
    $customer->external_source = 'tunerstop_retail';
    $customer->external_customer_id = $orderData['customer_id'] ?? null;
    $customer->save();
    
    return $customer;
}
```

### Address Creation

```php
protected function createAddresses(Order $order, array $orderData): void
{
    // Create billing address
    $billingAddress = AddressBook::create([
        'customer_id' => $order->customer_id,
        'address_type' => 1, // Billing
        'first_name' => $orderData['billing_address']['first_name'],
        'last_name' => $orderData['billing_address']['last_name'],
        'address' => $orderData['billing_address']['address'],
        'city' => $orderData['billing_address']['city'],
        'state' => $orderData['billing_address']['state'],
        'country' => $orderData['billing_address']['country'],
        'zip' => $orderData['billing_address']['zip'],
        'phone_no' => $orderData['billing_address']['phone_no'],
        'email' => $orderData['billing_address']['email'],
    ]);
    
    $order->billing_address_id = $billingAddress->id;
    
    // Create shipping address
    $shippingAddress = AddressBook::create([
        'customer_id' => $order->customer_id,
        'address_type' => 2, // Shipping
        'first_name' => $orderData['shipping_address']['first_name'],
        'last_name' => $orderData['shipping_address']['last_name'],
        'address' => $orderData['shipping_address']['address'],
        'city' => $orderData['shipping_address']['city'],
        'state' => $orderData['shipping_address']['state'],
        'country' => $orderData['shipping_address']['country'],
        'zip' => $orderData['shipping_address']['zip'],
        'phone_no' => $orderData['shipping_address']['phone_no'],
        'email' => $orderData['shipping_address']['email'],
    ]);
    
    $order->shipping_address_id = $shippingAddress->id;
    $order->save();
}
```

### Inventory Update

```php
protected function updateInventory(array $items): void
{
    foreach ($items as $item) {
        if (!isset($item['warehouse_quantities'])) continue;
        
        foreach ($item['warehouse_quantities'] as $warehouseQty) {
            $inventory = ProductInventory::where('product_variant_id', $item['variant_id'])
                ->where('warehouse_id', $warehouseQty['warehouse_id'])
                ->first();
            
            if ($inventory) {
                $inventory->quantity -= $warehouseQty['quantity'];
                $inventory->save();
            }
        }
    }
}
```

---

## Order Sync from Wholesale

### Key Differences from Retail

1. **Customer Type:** Dealers instead of retail
2. **Pricing:** Wholesale pricing applies
3. **Additional Fields:**
   - Purchase Order Number
   - Payment terms
   - Business information

### Payload Structure
```json
{
  "external_order_id": "WS-2025-789",
  "order_number": "WS-2025-789",
  "purchase_order_no": "PO-12345",
  "customer_type": "dealer",
  "customer_business_name": "Premium Wheels LLC",
  "customer_email": "orders@premiumwheels.ae",
  "customer_trn": "100123456700003",
  // ... similar to retail with wholesale pricing
}
```

### Processing Differences

**Customer Creation:**
```php
$customer = new Customer();
$customer->customer_type = 'dealer'; // Different type
$customer->business_name = $orderData['customer_business_name'];
$customer->email = $orderData['customer_email'];
$customer->trn = $orderData['customer_trn'] ?? null;
$customer->external_source = 'tunerstop_wholesale';
$customer->save();
```

---

## Sync APIs

### Authentication

**Bearer Token:**
```
Authorization: Bearer {REPORTING_API_TOKEN}
```

**Token Storage:**
- Stored in `.env` files of both systems
- `REPORTING_API_TOKEN` in TunerStop/Wholesale
- `API_TOKEN` in Reporting (for validation)

### Rate Limiting

**Limits:**
- 60 requests per minute per IP
- 500 requests per hour per token

### Response Format

**Success:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "external_id": "TS-456"
  },
  "message": "Resource synced successfully"
}
```

**Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

---

## Error Handling

### Retry Logic

**Exponential Backoff:**
```php
$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        $response = Http::post($url, $data);
        
        if ($response->successful()) {
            break;
        }
        
        $attempt++;
        sleep(pow(2, $attempt)); // 2, 4, 8 seconds
        
    } catch (\Exception $e) {
        $attempt++;
        if ($attempt >= $maxRetries) {
            throw $e;
        }
    }
}
```

### Failed Sync Queue

**Table:** `sync_operations`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| operation_type | varchar(50) | product/order/customer |
| source_system | varchar(50) | tunerstop/wholesale |
| external_id | varchar(255) | Source ID |
| payload | json | Sync data |
| status | varchar(50) | pending/success/failed |
| error_message | text | Error details |
| retry_count | int | Retry attempts |
| last_attempted_at | timestamp | Last attempt |
| created_at | timestamp | Creation time |

**Usage:**
```php
SyncOperation::create([
    'operation_type' => 'order',
    'source_system' => 'tunerstop',
    'external_id' => 'TS-2025-12345',
    'payload' => json_encode($orderData),
    'status' => 'failed',
    'error_message' => $exception->getMessage(),
    'retry_count' => 1,
]);
```

---

## 💰 WAFEQ ACCOUNTING INTEGRATION

### Overview
All financial transactions (payments, expenses, invoices) are automatically synced to Wafeq accounting system via queue-based jobs.

### Supported Sync Types
1. **Payment Records** - Customer payments (cash, card, bank transfer, credit)
2. **Expenses** - Invoice expenses (7 categories)
3. **Invoices** - Complete invoice data with line items
4. **Refunds** - Return/refund transactions

### Database Schema - Wafeq Sync Queue

```sql
CREATE TABLE wafeq_sync_queue (
    id BIGSERIAL PRIMARY KEY,
    entity_type VARCHAR(50),  -- 'payment', 'expense', 'invoice', 'refund'
    entity_id BIGINT,
    wafeq_id VARCHAR(255),
    
    -- Sync status
    sync_status VARCHAR(50) DEFAULT 'pending',  -- pending, processing, completed, failed
    sync_attempts INTEGER DEFAULT 0,
    last_sync_attempt TIMESTAMP,
    last_error TEXT,
    
    -- Payload
    payload JSONB,
    response JSONB,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_wafeq_sync_queue_entity ON wafeq_sync_queue(entity_type, entity_id);
CREATE INDEX idx_wafeq_sync_queue_status ON wafeq_sync_queue(sync_status);
```

### Service Implementation

```php
// app/Services/WafeqSyncService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WafeqSyncService
{
    protected $apiUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.wafeq.api_url');
        $this->apiKey = config('services.wafeq.api_key');
    }

    public function syncPayment(PaymentRecord $payment): bool
    {
        try {
            $payload = [
                'type' => 'payment',
                'payment_number' => $payment->payment_number,
                'customer_id' => $payment->customer->wafeq_id,
                'invoice_id' => $payment->invoice->wafeq_id ?? null,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_date' => $payment->payment_date,
                'transaction_id' => $payment->transaction_id,
                'notes' => $payment->notes,
            ];

            $response = Http::withToken($this->apiKey)
                ->post($this->apiUrl . '/payments', $payload);

            if ($response->successful()) {
                $wafeqId = $response->json('payment_id');
                
                // Mark as synced
                $payment->markAsSynced($wafeqId);
                
                // Update queue
                $this->updateSyncQueue('payment', $payment->id, 'completed', $wafeqId, $response->json());
                
                return true;
            }

            throw new \Exception($response->body());

        } catch (\Exception $e) {
            Log::error('Wafeq payment sync failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);

            $this->updateSyncQueue('payment', $payment->id, 'failed', null, null, $e->getMessage());
            return false;
        }
    }

    public function syncExpenses(Invoice $invoice): bool
    {
        try {
            $payload = [
                'type' => 'expenses',
                'invoice_number' => $invoice->invoice_number,
                'invoice_id' => $invoice->wafeq_id,
                'customer_id' => $invoice->customer->wafeq_id,
                'expenses' => [
                    'cost_of_goods' => $invoice->cost_of_goods,
                    'shipping_cost' => $invoice->shipping_cost,
                    'duty_amount' => $invoice->duty_amount,
                    'delivery_fee' => $invoice->delivery_fee,
                    'installation_cost' => $invoice->installation_cost,
                    'bank_fee' => $invoice->bank_fee,
                    'credit_card_fee' => $invoice->credit_card_fee,
                ],
                'total_expenses' => $invoice->total_expenses,
                'recorded_at' => $invoice->expenses_recorded_at,
            ];

            $response = Http::withToken($this->apiKey)
                ->post($this->apiUrl . '/expenses', $payload);

            if ($response->successful()) {
                $this->updateSyncQueue('expense', $invoice->id, 'completed', $invoice->wafeq_id, $response->json());
                return true;
            }

            throw new \Exception($response->body());

        } catch (\Exception $e) {
            Log::error('Wafeq expense sync failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            $this->updateSyncQueue('expense', $invoice->id, 'failed', null, null, $e->getMessage());
            return false;
        }
    }

    public function syncInvoice(Invoice $invoice): bool
    {
        try {
            $payload = [
                'type' => 'invoice',
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer->wafeq_id,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'subtotal' => $invoice->subtotal,
                'tax_amount' => $invoice->tax_amount,
                'total' => $invoice->total,
                'status' => $invoice->status,
                'line_items' => $invoice->items->map(function ($item) {
                    return [
                        'sku' => $item->sku,
                        'name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'tax_inclusive' => $item->tax_inclusive,
                    ];
                })->toArray(),
            ];

            $response = Http::withToken($this->apiKey)
                ->post($this->apiUrl . '/invoices', $payload);

            if ($response->successful()) {
                $wafeqId = $response->json('invoice_id');
                
                $invoice->update([
                    'wafeq_id' => $wafeqId,
                    'wafeq_sync_at' => now(),
                ]);
                
                $this->updateSyncQueue('invoice', $invoice->id, 'completed', $wafeqId, $response->json());
                return true;
            }

            throw new \Exception($response->body());

        } catch (\Exception $e) {
            Log::error('Wafeq invoice sync failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            $this->updateSyncQueue('invoice', $invoice->id, 'failed', null, null, $e->getMessage());
            return false;
        }
    }

    protected function updateSyncQueue($entityType, $entityId, $status, $wafeqId = null, $response = null, $error = null)
    {
        DB::table('wafeq_sync_queue')->updateOrCreate(
            [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'sync_status' => $status,
                'wafeq_id' => $wafeqId,
                'response' => $response ? json_encode($response) : null,
                'last_error' => $error,
                'last_sync_attempt' => now(),
                'sync_attempts' => DB::raw('sync_attempts + 1'),
                'updated_at' => now(),
            ]
        );
    }
}
```

### Queue Jobs

```php
// app/Jobs/SyncPaymentToWafeq.php
namespace App\Jobs;

use App\Models\PaymentRecord;
use App\Services\WafeqSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPaymentToWafeq implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $payment;
    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(PaymentRecord $payment)
    {
        $this->payment = $payment;
    }

    public function handle(WafeqSyncService $wafeqSync)
    {
        $wafeqSync->syncPayment($this->payment);
    }

    public function failed(\Exception $exception)
    {
        Log::error('Wafeq payment sync job failed', [
            'payment_id' => $this->payment->id,
            'error' => $exception->getMessage()
        ]);

        // Optionally notify admin
        // Mail::to('admin@company.com')->send(new WafeqSyncFailed($this->payment, $exception));
    }
}

// app/Jobs/SyncExpensesToWafeq.php
class SyncExpensesToWafeq implements ShouldQueue
{
    // Similar structure for expenses
}

// app/Jobs/SyncInvoiceToWafeq.php
class SyncInvoiceToWafeq implements ShouldQueue
{
    // Similar structure for invoices
}
```

### Usage in Models

```php
// app/Models/Invoice.php
public function recordPayment($amount, $method = 'cash', $transactionId = null, $gateway = null, $notes = null)
{
    // ... existing payment recording code ...

    // CRITICAL: Trigger Wafeq sync via queue
    \App\Jobs\SyncPaymentToWafeq::dispatch($payment);

    return $payment;
}

public function recordExpenses(array $expenseData)
{
    // ... existing expense recording code ...

    // CRITICAL: Trigger Wafeq expense sync
    \App\Jobs\SyncExpensesToWafeq::dispatch($this);

    return $this->save();
}
```

### Retry Failed Syncs

```php
// artisan command: app/Console/Commands/RetryFailedWafeqSyncs.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryFailedWafeqSyncs extends Command
{
    protected $signature = 'wafeq:retry-failed';
    protected $description = 'Retry failed Wafeq syncs';

    public function handle()
    {
        $failed = DB::table('wafeq_sync_queue')
            ->where('sync_status', 'failed')
            ->where('sync_attempts', '<', 5)
            ->get();

        foreach ($failed as $sync) {
            switch ($sync->entity_type) {
                case 'payment':
                    $payment = PaymentRecord::find($sync->entity_id);
                    if ($payment) {
                        \App\Jobs\SyncPaymentToWafeq::dispatch($payment);
                    }
                    break;

                case 'expense':
                    $invoice = Invoice::find($sync->entity_id);
                    if ($invoice) {
                        \App\Jobs\SyncExpensesToWafeq::dispatch($invoice);
                    }
                    break;

                case 'invoice':
                    $invoice = Invoice::find($sync->entity_id);
                    if ($invoice) {
                        \App\Jobs\SyncInvoiceToWafeq::dispatch($invoice);
                    }
                    break;
            }
        }

        $this->info("Retried {$failed->count()} failed syncs");
    }
}
```

---

## 📋 ORDER SYNC WITH DOCUMENT_TYPE

### Updated Order Sync Approach

**CRITICAL:** Orders are now stored in a unified `orders` table with `document_type` discriminator.

### Updated Payload Structure

```json
{
  "external_order_id": "TS-2025-12345",
  "order_number": "TS-2025-12345",
  "document_type": "order",  // NEW: 'quote', 'invoice', 'order'
  "customer_email": "customer@example.com",
  "customer_type": "retail",  // retail, dealer, wholesale, corporate
  // ... rest of order data
}
```

### Updated Processing Flow

```php
// app/Services/OrderSyncService.php
public function syncOrder(array $orderData): array
{
    DB::beginTransaction();
    
    try {
        // 1. Validate with document_type
        $this->validateOrderData($orderData);
        
        // 2. Find or create customer
        $customer = $this->findOrCreateCustomer($orderData);
        
        // 3. Create order with document_type
        $order = Order::create([
            'external_order_id' => $orderData['external_order_id'],
            'order_number' => $orderData['order_number'],
            'document_type' => $orderData['document_type'] ?? 'order',  // CRITICAL
            'customer_id' => $customer->id,
            'order_date' => $orderData['order_date'],
            'status' => $orderData['status'],
            'payment_status' => $orderData['payment_status'],
            // ... rest of fields
        ]);
        
        // 4. Create order items with snapshots
        foreach ($orderData['items'] as $itemData) {
            $this->createOrderItemWithSnapshot($order, $itemData, $customer);
        }
        
        // 5. Calculate totals (with dealer pricing consideration)
        $order->calculateTotals();
        
        // 6. Create addresses
        $this->createAddresses($order, $orderData);
        
        DB::commit();
        
        return [
            'success' => true,
            'order_id' => $order->id,
            'document_type' => $order->document_type,
        ];
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Order sync failed", ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

protected function createOrderItemWithSnapshot($order, $itemData, $customer)
{
    // Find product/variant
    $variant = ProductVariant::where('sku', $itemData['sku'])->first();
    
    if (!$variant) {
        // If variant doesn't exist, sync it on-demand from TunerStop
        $variant = $this->syncVariantOnDemand($itemData['sku']);
    }
    
    // Create snapshot
    $snapshotService = app(VariantSnapshotService::class);
    $snapshot = $snapshotService->createSnapshot($variant);
    
    // Apply dealer pricing if customer is dealer
    $dealerPricingService = app(DealerPricingService::class);
    $price = $dealerPricingService->calculatePrice($customer, $variant, 'variant');
    
    // Create order item
    OrderItem::create([
        'order_id' => $order->id,
        'variant_id' => $variant->id,
        'product_id' => $variant->product_id,
        'variant_snapshot' => json_encode($snapshot),  // JSONB snapshot
        'sku' => $variant->sku,
        'product_name' => $snapshot['product_name'],
        'size' => $snapshot['size'],
        'quantity' => $itemData['quantity'],
        'price' => $price,  // Dealer or regular price
        'tax_inclusive' => $variant->tax_inclusive ?? true,
    ]);
}

protected function syncVariantOnDemand($sku)
{
    // Call TunerStop API to get variant data
    $response = Http::withToken(config('tunerstop.api_key'))
        ->get(config('tunerstop.api_url') . '/variants/' . $sku);
    
    if ($response->successful()) {
        $variantData = $response->json();
        
        // Sync product first if needed
        $product = $this->ensureProductExists($variantData['product_id']);
        
        // Create/update variant
        return ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'size' => $variantData['size'],
                'bolt_pattern' => $variantData['bolt_pattern'],
                // ... rest of fields
            ]
        );
    }
    
    throw new \Exception("Cannot sync variant: $sku");
}
```

---

## 🔄 SNAPSHOT-BASED PRODUCT SYNC

### Philosophy
**DO NOT sync entire product catalog.** Only sync products/variants when they are actually needed for orders.

### On-Demand Sync Workflow

```
1. Order received from TunerStop
   ↓
2. Check if variant exists by SKU
   ↓
3. If NOT exists → Sync variant on-demand from TunerStop API
   ↓
4. If product missing → Sync product first
   ↓
5. Create snapshot of variant at order time
   ↓
6. Store snapshot in order_items.variant_snapshot (JSONB)
```

### Why Snapshots?

1. **Historical Accuracy** - Product details at time of order preserved
2. **No Dependency** - Can delete/update products without affecting old orders
3. **Performance** - No joins needed to display order details
4. **Flexibility** - Can change product structure without migration
5. **Space Efficient** - PostgreSQL JSONB is compressed and indexed

### What to Sync vs What NOT to Sync

**✅ DO SYNC:**
- Products/variants when they appear in orders
- Products needed for quotes
- Products manually requested by admin
- Critical product updates (price changes for active quotes)

**❌ DO NOT SYNC:**
- Entire product catalog (thousands of products)
- Products never ordered
- Historical product changes
- Discontinued products
- Product images (unless actively selling)

### Manual Sync Options

```php
// Admin can manually sync specific products if needed
public function syncSpecificProduct($tunerstopProductId)
{
    $syncService = app(ProductSyncService::class);
    return $syncService->syncProductById($tunerstopProductId);
}

// Sync all products for a specific brand (for new dealer)
public function syncBrandProducts($brandId)
{
    $response = Http::withToken(config('tunerstop.api_key'))
        ->get(config('tunerstop.api_url') . '/brands/' . $brandId . '/products');
    
    if ($response->successful()) {
        $products = $response->json('products');
        
        foreach ($products as $productData) {
            dispatch(new SyncProductJob($productData));
        }
    }
}
```

---

## Performance Optimization

### Batch Sync

**Process multiple records:**
```php
$chunkSize = 25;
$products = Product::whereNull('synced_at')->take(100)->get();

foreach ($products->chunk($chunkSize) as $chunk) {
    dispatch(new SyncProductsBatch($chunk));
}
```

### Queue Workers

**Laravel Queue:**
```bash
php artisan queue:work --queue=product_sync,order_sync
```

### Caching

**Cache mappings:**
```php
$brandMapping = Cache::remember('brand_mapping_' . $tunerstopId, 3600, function () use ($tunerstopId) {
    return BrandMapping::where('tunerstop_brand_id', $tunerstopId)->first();
});
```

---

## Monitoring & Logging

### Sync Dashboard

**Metrics:**
- Total syncs today
- Success rate
- Failed syncs
- Average sync time
- Pending queue size

### Logging

**Log Levels:**
- **INFO:** Successful syncs
- **WARNING:** Retries
- **ERROR:** Failed syncs

**Example:**
```php
Log::info('Product synced successfully', [
    'external_id' => $productId,
    'reporting_id' => $product->id,
    'sync_time' => $syncTime
]);

Log::error('Product sync failed', [
    'external_id' => $productId,
    'error' => $exception->getMessage(),
    'trace' => $exception->getTraceAsString()
]);
```

---

## Related Documentation
- [Orders Module](ARCHITECTURE_ORDERS_MODULE.md)
- [Products Module](ARCHITECTURE_PRODUCTS_MODULE.md)
- [Customers Module](ARCHITECTURE_CUSTOMERS_MODULE.md)
