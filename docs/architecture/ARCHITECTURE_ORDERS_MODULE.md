# Orders Module - Complete Architecture Documentation

## ⚠️ CRITICAL: Unified Orders Architecture

**IMPORTANT CLARIFICATION:**  
This system uses a **UNIFIED ORDERS TABLE** approach where quotes, invoices, and orders are stored in the SAME table, differentiated by the `document_type` field. This is the CORRECT approach and must be preserved.

### **Unified Approach Benefits:**
1. ✅ Single source of truth for all document types
2. ✅ Easy conversion (quote → invoice): just update `document_type` and `quote_status`
3. ✅ Consistent structure across document types
4. ✅ Simplified reporting and querying
5. ✅ Matches current system successfully in production

### **Key Fields for Unified Workflow:**
- `document_type` ENUM('quote', 'invoice', 'order') - Primary discriminator
- `quote_number` - Used when document_type = 'quote'
- `quote_status` - Tracks quote lifecycle
- `order_number` - Used for all document types
- `external_order_id` - For synced orders from TunerStop/Wholesale

---

## Overview
The Orders module is the core transaction management system in the Reporting CRM. It handles retail orders from TunerStop.com, wholesale orders from the wholesale platform, and includes integrated quote management functionality **in a unified table structure**.

**Last Updated:** October 20, 2025  
**Module Location:** `app/Models/Order.php`, `app/Http/Controllers/OrderController.php`  
**Architecture:** Unified Table (document_type discriminator)  
**Tech Stack:** Laravel 12 + PostgreSQL 15 + Filament v3

---

## Table of Contents
1. [Database Schema](#database-schema)
2. [Model Architecture](#model-architecture)
3. [Controller Architecture](#controller-architecture)
4. [Views & UI](#views--ui)
5. [Sync Processes](#sync-processes)
6. [Business Logic](#business-logic)
7. [Relationships](#relationships)
8. [Enums & Status Management](#enums--status-management)

---

## Database Schema

### Orders Table
**Table Name:** `orders`

#### Core Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| id | bigint | Primary key | NO | AUTO_INCREMENT |
| session_id | varchar(255) | Session identifier | YES | NULL |
| order_number | varchar(255) | Unique order number | YES | NULL |
| customer_id | bigint | FK to customers table | YES | NULL |
| user_id | bigint | Legacy FK (being replaced by customer_id) | YES | NULL |
| warehouse_id | bigint | FK to warehouses table | YES | NULL |
| external_order_id | varchar(255) | ID from source system (TunerStop/Wholesale) | YES | NULL |
| external_source | varchar(100) | Source system identifier | YES | NULL |

#### Financial Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| sub_total | decimal(10,2) | Subtotal before taxes | YES | 0.00 |
| tax | decimal(10,2) | Tax amount | YES | 0.00 |
| vat | decimal(10,2) | VAT amount | YES | 0.00 |
| shipping | decimal(10,2) | Shipping cost | YES | 0.00 |
| discount | decimal(10,2) | Discount amount | YES | 0.00 |
| discount_type | varchar(50) | Type: percentage/fixed | YES | NULL |
| discount_value | decimal(10,2) | Discount value for quote workflow | YES | 0.00 |
| total | decimal(10,2) | Total order amount | YES | 0.00 |
| currency | varchar(10) | Currency code (AED/USD) | YES | 'AED' |

#### Status Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| status | int | Order status enum value | YES | 0 |
| payment_status | int | Payment status enum value | YES | 0 |
| payment_method | varchar(100) | Payment method | YES | NULL |
| payment_type | varchar(50) | Payment type (full/partial) | YES | NULL |

#### Address Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| billing_id | bigint | Legacy billing FK | YES | NULL |
| shipping_id | bigint | Legacy shipping FK | YES | NULL |
| billing_address_id | bigint | FK to address_books (new system) | YES | NULL |
| shipping_address_id | bigint | FK to address_books (new system) | YES | NULL |

#### Vehicle Information
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| vehicle_year | varchar(10) | Vehicle year | YES | NULL |
| vehicle_make | varchar(100) | Vehicle make | YES | NULL |
| vehicle_model | varchar(100) | Vehicle model | YES | NULL |
| vehicle_sub_model | varchar(100) | Vehicle sub-model | YES | NULL |

#### Quote-Related Fields (Unified Workflow)
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| quote_number | varchar(255) | Quote number if order started as quote | YES | NULL |
| document_type | varchar(50) | Type: order/quote | YES | 'order' |
| quote_status | varchar(50) | Quote status | YES | NULL |
| issue_date | date | Quote issue date | YES | NULL |
| valid_until | date | Quote expiry date | YES | NULL |
| sent_at | datetime | Quote sent timestamp | YES | NULL |
| approved_at | datetime | Quote approval timestamp | YES | NULL |
| converted_to_invoice_id | bigint | FK to invoices if converted | YES | NULL |

#### Hybrid System Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| quote_items | json | Quote items data (JSON) | YES | NULL |
| external_products | json | External product references | YES | NULL |
| hybrid_metadata | json | Hybrid system metadata | YES | NULL |
| organization_settings | json | Organization-specific settings | YES | NULL |
| has_external_products | boolean | Flag for external products | YES | false |
| is_quote_converted | boolean | Quote conversion flag | YES | false |

#### Salesperson & Channel
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| representative_id | bigint | FK to users (representative) | YES | NULL |
| salesman_id | bigint | FK to users (salesman) | YES | NULL |
| created_by | bigint | FK to users (creator) | YES | NULL |
| channel | varchar(50) | Sales channel | YES | NULL |
| lead_source | varchar(100) | Lead source | YES | NULL |

#### Additional Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| purchase_order_no | varchar(255) | PO number | YES | NULL |
| order_notes | text | Order notes | YES | NULL |
| tracking_number | varchar(255) | Shipment tracking | YES | NULL |
| total_quantity | int | Total items quantity | YES | 0 |
| created_at | timestamp | Creation timestamp | YES | NULL |
| updated_at | timestamp | Last update timestamp | YES | NULL |
| deleted_at | timestamp | Soft delete timestamp | YES | NULL |

---

## Model Architecture

### File: `app/Models/Order.php`

```php
class Order extends BaseModel
{
    use EnumCastable;
    
    // Constants
    const SHIPPING_FILES_FOLDER = 'shipping-files';
}
```

### Key Features

#### 1. Enum Casting
- **OrderStatusEnum:** Manages order lifecycle states
- **PaymentStatusEnum:** Tracks payment status
- Provides HTML rendering methods for status badges

#### 2. Field Casting
```php
protected $casts = [
    'payment_status' => PaymentStatusEnum::class,
    'status' => OrderStatusEnum::class,
    'issue_date' => 'date',
    'valid_until' => 'date',
    'sent_at' => 'datetime',
    'approved_at' => 'datetime',
    'tax' => 'decimal:2',
    'vat' => 'decimal:2',
    'discount_value' => 'decimal:2',
    'shipping' => 'decimal:2',
    'quote_items' => 'array',
    'external_products' => 'array',
    'hybrid_metadata' => 'array',
    'organization_settings' => 'array',
    'has_external_products' => 'boolean',
    'is_quote_converted' => 'boolean'
];
```

#### 3. Mass Assignment Protection
All relevant fields are included in `$fillable` array for safe mass assignment.

---

## Relationships

### 1. Customer Relationship (Primary)
```php
public function customer()
{
    return $this->belongsTo(Customer::class, 'customer_id');
}
```
- **Type:** Many-to-One
- **Description:** Links order to unified customer record
- **New System:** Replaces legacy dealer/user relationship

### 2. Address Relationships (New System)
```php
public function billingAddress()
{
    return $this->belongsTo(AddressBook::class, 'billing_address_id');
}

public function shippingAddress()
{
    return $this->belongsTo(AddressBook::class, 'shipping_address_id');
}
```
- **Type:** Many-to-One
- **Description:** Links to unified AddressBook system
- **Migration Status:** Coexists with legacy billing/shipping tables

### 3. Legacy Address Relationships (Deprecated)
```php
public function legacyBilling()
{
    return $this->hasOne(Billing::class);
}

public function legacyShipping()
{
    return $this->hasOne(Shipping::class);
}
```
- **Status:** Being phased out
- **Purpose:** Backward compatibility during migration

### 4. Order Items
```php
public function orderItems()
{
    return $this->hasMany(OrderItem::class);
}

public function products()
{
    return $this->hasMany(OrderItem::class);
}
```
- **Type:** One-to-Many
- **Description:** Order line items (products, addons, custom items)

### 5. Order Item Quantities
```php
public function item_qty()
{
    return $this->hasMany(OrderItemQuantity::class);
}
```
- **Type:** One-to-Many  
- **Description:** Tracks quantity allocation across warehouses

### 6. Warehouse
```php
public function warehouse()
{
    return $this->belongsTo(Warehouse::class);
}
```
- **Type:** Many-to-One
- **Description:** Primary fulfillment warehouse

### 7. Representative
```php
public function representive() // Note: typo in original
{
    return $this->belongsTo(User::class, 'representative_id');
}
```
- **Type:** Many-to-One
- **Description:** Sales representative assigned to order

### 8. Invoice
```php
public function invoice()
{
    return $this->hasOne(Invoice::class);
}
```
- **Type:** One-to-One
- **Description:** Generated invoice for the order

---

## Controller Architecture

### File: `app/Http/Controllers/OrderController.php`

### Class Structure
```php
class OrderController extends VoyagerBaseController
{
    private $dealerRepo;
    private $product;
    private $productVariant;
}
```

### Key Methods

#### 1. Index Method (Browse Orders)
```php
public function index(Request $request)
```

**Features:**
- **Server-side pagination** (15 records per page)
- **Advanced filtering:**
  - Status filter (Unfulfilled, Draft Quotes, Approved Quotes, Completed, Canceled)
  - Payment status filter
  - Search by order number, customer name, email
- **Query optimization:**
  - Joins customers table (unified system)
  - Left joins representative users
  - Groups by order ID
  - Orders by ID DESC

**Filter Logic:**
```php
// Status Filter
if ($request->has('status')) {
    if ($statusValue == 0) {
        // Unfulfilled = Pending (0) OR Processing (1)
        $query->whereIn('orders.status', [0, 1]);
    } else {
        $query->where('orders.status', $statusValue);
    }
}

// Payment Status Filter
if ($request->has('payment_status')) {
    $query->where('orders.payment_status', $paymentStatusValue);
}
```

**Query Fields:**
- `orders.id`, `order_number`, `created_at`, `status`, `is_captured`
- `CONCAT(customers.first_name, ' ', customers.last_name) as customer_name`
- `customers.business_name`, `email`, `customer_type`
- `representative.name`, `representative.email`
- `total`, `total_quantity`, `paid_amount`, `outstanding_amount`
- `payment_status`, `tracking_number`

#### 2. Show Method (Order Details)
```php
public function show(Request $request, $id)
```

**Features:**
- **Eager loading** for performance:
  - Customer
  - Warehouse
  - Order items with product and variant
  - Order item quantities with warehouse
  - Legacy billing and shipping
- **Tracking number update:**
  - Validates uniqueness
  - Updates order status to "Shipped" (2)
  - Sends email notification
- **Debug logging** for order items
- **Soft delete support**
- **Relationship resolution** for Voyager BREAD

**Email Notification:**
```php
$subject = 'Order has shipped';
$email_service = new EmailService();
$data['data'] = $order;
$data['image_url'] = env('S3IMAGES_URL');
$body = view('emails.order_status', $data)->render();
$email_service->send($order->legacyBilling->email, $subject, $body);
```

#### 3. Additional Controller Methods
- **create():** Order creation form
- **store():** Save new order
- **edit():** Order edit form
- **update():** Update existing order
- **destroy():** Soft delete order

---

## Sync Processes

### Sync Architecture Overview
Orders sync from two primary sources:
1. **TunerStop Retail** (tunerstop.com)
2. **Wholesale Platform** (wholesaleadmin)

### Service: `OrderSyncService.php`

#### Class Constants
```php
const SYNC_SOURCE_RETAIL = 'tunerstop_retail';
const SYNC_SOURCE_WHOLESALE = 'tunerstop_wholesale';
const DEFAULT_VENDOR_ID = 84;
```

#### Sync Flow Diagram
```
┌─────────────────┐
│  Source System  │ (TunerStop/Wholesale)
└────────┬────────┘
         │
         │ API Call with Order Data
         ▼
┌─────────────────┐
│  OrderSyncAPI   │
│  (api.php)      │
└────────┬────────┘
         │
         │ Validates Request
         ▼
┌─────────────────┐
│ OrderSyncService│
│  syncOrder()    │
└────────┬────────┘
         │
         ├─► Validate Order Data
         ├─► Check for Duplicates (external_order_id)
         ├─► Find/Create Customer
         ├─► Create Order Record
         ├─► Create Order Items
         ├─► Create/Link Addresses
         ├─► Update Inventory
         └─► Log Activity
         
         ▼
┌─────────────────┐
│  Order Created  │
│  in Reporting   │
└─────────────────┘
```

#### Key Sync Methods

**1. syncOrder(array $orderData): array**
```php
public function syncOrder(array $orderData): array
{
    DB::beginTransaction();
    
    try {
        // Validate
        $validationResult = $this->validateOrderData($orderData);
        
        // Check duplicates
        if ($this->orderExists($orderData['external_order_id'])) {
            return ['success' => false, 'message' => 'Order already exists'];
        }
        
        // Create order
        $order = $this->createOrder($orderData);
        
        // Create items
        $this->createOrderItems($order, $orderData['items']);
        
        // Create addresses
        $this->createAddresses($order, $orderData);
        
        // Update inventory
        if ($orderData['update_inventory'] ?? true) {
            $this->updateInventory($orderData['items']);
        }
        
        DB::commit();
        
        return ['success' => true, 'order_id' => $order->id];
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Order sync failed", ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

**2. validateOrderData(array $orderData): array**
```php
protected function validateOrderData(array $orderData): array
{
    $errors = [];
    
    $requiredFields = [
        'external_order_id',
        'order_number',
        'customer_email',
        'total',
        'status',
        'payment_status',
        'items',
        'billing_address',
        'shipping_address'
    ];
    
    foreach ($requiredFields as $field) {
        if (!isset($orderData[$field])) {
            $errors[] = "Missing required field: {$field}";
        }
    }
    
    // Validate items
    foreach ($orderData['items'] as $index => $item) {
        $itemErrors = $this->validateOrderItem($item, $index);
        $errors = array_merge($errors, $itemErrors);
    }
    
    return ['valid' => empty($errors), 'errors' => $errors];
}
```

**3. createOrder(array $orderData): Order**
```php
protected function createOrder(array $orderData): Order
{
    // Find or create customer
    $customer = $this->findOrCreateCustomer($orderData);
    
    $order = new Order();
    $order->external_order_id = $orderData['external_order_id'];
    $order->order_number = $orderData['order_number'];
    $order->customer_id = $customer->id;
    $order->status = $this->mapOrderStatus($orderData['status']);
    $order->payment_status = $this->mapPaymentStatus($orderData['payment_status']);
    $order->total = $orderData['total'];
    $order->sub_total = $orderData['sub_total'] ?? $orderData['total'];
    $order->shipping = $orderData['shipping'] ?? 0;
    $order->vat = $orderData['vat'] ?? 0;
    $order->discount = $orderData['discount'] ?? 0;
    $order->currency = $orderData['currency'] ?? 'AED';
    $order->external_source = $this->syncSource;
    $order->created_at = isset($orderData['order_date']) ? 
        Carbon::parse($orderData['order_date']) : now();
    
    $order->save();
    
    return $order;
}
```

**4. createOrderItems(Order $order, array $items): void**
```php
protected function createOrderItems(Order $order, array $items): void
{
    foreach ($items as $itemData) {
        $productVariant = ProductVariant::where('sku', $itemData['sku'])->first();
        
        if (!$productVariant) {
            Log::warning("Product variant not found for SKU: {$itemData['sku']}");
            // Create item without product link
        }
        
        $orderItem = new OrderItem();
        $orderItem->order_id = $order->id;
        $orderItem->product_id = $productVariant->product_id ?? null;
        $orderItem->product_variant_id = $productVariant->id ?? null;
        $orderItem->sku = $itemData['sku'];
        $orderItem->name = $itemData['name'];
        $orderItem->quantity = $itemData['quantity'];
        $orderItem->price = $itemData['price'];
        $orderItem->total = $itemData['quantity'] * $itemData['price'];
        $orderItem->save();
        
        // Create warehouse quantities if provided
        if (isset($itemData['warehouse_quantities'])) {
            $this->createOrderItemQuantities($orderItem, $itemData['warehouse_quantities']);
        }
    }
}
```

### Sync from TunerStop Retail
**Source:** `tunerstop-admin` (main e-commerce site)

**Trigger Points:**
1. **Order Placed:** When customer completes checkout
2. **Order Updated:** When order status changes
3. **Manual Sync:** Admin-triggered sync

**Data Flow:**
```
TunerStop Frontend
    ↓
TunerStop Admin (Laravel)
    ↓
POST /api/sync/order
    ↓
Reporting CRM (OrderSyncService)
    ↓
Order Created/Updated
```

**Payload Example:**
```json
{
  "external_order_id": "TS-2025-12345",
  "order_number": "TS-2025-12345",
  "customer_email": "customer@example.com",
  "customer_first_name": "John",
  "customer_last_name": "Doe",
  "customer_phone": "+971501234567",
  "total": 5250.00,
  "sub_total": 5000.00,
  "shipping": 250.00,
  "vat": 0.00,
  "discount": 0.00,
  "currency": "AED",
  "status": "pending",
  "payment_status": "paid",
  "payment_method": "credit_card",
  "vehicle_year": "2023",
  "vehicle_make": "Toyota",
  "vehicle_model": "Land Cruiser",
  "vehicle_sub_model": "GXR",
  "items": [
    {
      "sku": "BBS-CH-R-20-85-BM",
      "name": "BBS CH-R 20x8.5 Brilliant Silver",
      "quantity": 4,
      "price": 1250.00,
      "product_id": 123,
      "variant_id": 456
    }
  ],
  "billing_address": {
    "first_name": "John",
    "last_name": "Doe",
    "address": "123 Main Street",
    "city": "Dubai",
    "state": "Dubai",
    "zip": "00000",
    "country": "United Arab Emirates",
    "phone": "+971501234567",
    "email": "customer@example.com"
  },
  "shipping_address": {
    // Same structure as billing_address
  }
}
```

### Sync from Wholesale Platform
**Source:** `wholesaleadmin` (B2B platform)

**Trigger Points:**
1. **Dealer Order:** When dealer places order
2. **Order Status Change:** Status updates
3. **Bulk Sync:** Batch order sync

**Differences from Retail:**
- Includes `purchase_order_no`
- Different pricing (wholesale vs retail)
- May include payment terms
- Dealer-specific discounts

---

## Business Logic

### Order Status Lifecycle

```
Draft Quote (-2)
    ↓
Approved Quote (-1)
    ↓
Pending (0) ←─── Order Placed
    ↓
Processing (1)
    ↓
Shipped (2)
    ↓
Completed (3)
    
    OR
    
Canceled (4)
```

### Payment Status Flow

```
Unpaid (0)
    ↓
Partially Paid (1)
    ↓
Paid (2)
    ↓
Refunded (3)
```

### Inventory Updates
When order is placed:
1. Check product availability across warehouses
2. Allocate inventory from nearest warehouse
3. Create `OrderItemQuantity` records
4. Decrease warehouse inventory
5. Log inventory transaction

### Quote to Order Conversion
1. Create quote with `document_type = 'quote'`
2. Send quote to customer
3. Customer approves
4. Set `approved_at` timestamp
5. Convert to order:
   - Change `document_type` to 'order'
   - Set `is_quote_converted = true`
   - Copy items to order_items table
   - Generate invoice

---

## Enums & Status Management

### OrderStatusEnum
**File:** `app/Enums/OrderStatusEnum.php`

```php
class OrderStatusEnum
{
    const DRAFT_QUOTE = -2;
    const APPROVED_QUOTE = -1;
    const PENDING = 0;
    const PROCESSING = 1;
    const SHIPPED = 2;
    const COMPLETED = 3;
    const CANCELED = 4;
    
    public function toHtml(): string
    {
        // Returns Bootstrap badge HTML
    }
    
    public function getValue(): int
    {
        return $this->value;
    }
}
```

### PaymentStatusEnum
**File:** `app/Enums/PaymentStatusEnum.php`

```php
class PaymentStatusEnum
{
    const UNPAID = 0;
    const PARTIALLY_PAID = 1;
    const PAID = 2;
    const REFUNDED = 3;
    
    public function toHtml(): string
    {
        // Returns Bootstrap badge HTML
    }
}
```

---

## Views & UI

### Browse View
**Location:** `resources/views/vendor/voyager/orders/browse.blade.php`

**Features:**
- Tabbed interface for status filtering
- Search functionality
- Sortable columns
- Pagination
- Action buttons (View, Edit, Delete)
- Bulk actions

**Tabs:**
1. All Orders
2. Unfulfilled (Pending + Processing)
3. Draft Quotes
4. Approved Quotes
5. Completed
6. Canceled

### Detail View
**Location:** `resources/views/vendor/voyager/orders/read.blade.php`

**Sections:**
1. **Order Header:**
   - Order number
   - Status badges
   - Order date
   - Customer info
   
2. **Customer Information:**
   - Customer name/business
   - Email, phone
   - Customer type (retail/dealer)
   
3. **Order Items:**
   - Product name, SKU
   - Quantity, price, total
   - Product image thumbnail
   
4. **Address Information:**
   - Billing address
   - Shipping address
   
5. **Financial Summary:**
   - Subtotal
   - Shipping
   - Tax/VAT
   - Discount
   - Total
   
6. **Vehicle Information:**
   - Year, make, model, sub-model
   
7. **Tracking:**
   - Tracking number input
   - Update status button
   
8. **Notes:**
   - Order notes display

### Edit View
**Location:** `resources/views/vendor/voyager/orders/edit.blade.php`

**Features:**
- All order fields editable
- Customer selection dropdown
- Item management (add/remove/edit)
- Address management
- Status/payment status dropdowns
- Vehicle info fields
- Notes editor

---

## API Endpoints

### Order Sync API
**Base URL:** `/api/sync/orders`

#### 1. Sync Single Order
```
POST /api/sync/order
Authorization: Bearer {token}
Content-Type: application/json

{
  "external_order_id": "...",
  "order_number": "...",
  // ... order data
}

Response:
{
  "success": true,
  "order_id": 123,
  "external_order_id": "TS-2025-12345",
  "message": "Order synced successfully"
}
```

#### 2. Batch Sync Orders
```
POST /api/sync/orders/batch
Authorization: Bearer {token}

{
  "orders": [
    { /* order data */ },
    { /* order data */ }
  ]
}

Response:
{
  "total_processed": 10,
  "success_count": 9,
  "failure_count": 1,
  "errors": [/* errors */]
}
```

#### 3. Get Sync Status
```
GET /api/sync/orders/status
Authorization: Bearer {token}

Response:
{
  "last_sync": "2025-10-20 14:30:00",
  "total_synced": 1523,
  "pending_sync": 3
}
```

---

## Related Modules

### OrderItem
- Stores individual line items
- Links to products/variants
- Supports external products
- Includes product snapshot

### OrderItemQuantity
- Warehouse-specific quantities
- Tracks allocation
- Supports multi-warehouse fulfillment

### AddressBook
- Unified address management
- Replaces legacy Billing/Shipping tables
- Supports multiple addresses per customer

---

## Performance Considerations

### Indexing
```sql
-- Recommended indexes
CREATE INDEX idx_orders_external_order_id ON orders(external_order_id);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_payment_status ON orders(payment_status);
CREATE INDEX idx_orders_created_at ON orders(created_at);
CREATE INDEX idx_orders_external_source ON orders(external_source);
```

### Query Optimization
- Use eager loading for relationships
- Paginate large result sets
- Index foreign keys
- Use database transactions for sync

### Caching Strategies
- Cache order counts per status
- Cache customer order history
- Cache product availability

---

## Migration Notes

### Legacy to New System
1. **Customer Migration:** 
   - Old: `user_id` → New: `customer_id`
   - Preserves backward compatibility
   
2. **Address Migration:**
   - Old: `billing_id`, `shipping_id` → New: `billing_address_id`, `shipping_address_id`
   - Both systems coexist during transition
   
3. **Quote Integration:**
   - Quotes now part of orders table
   - Use `document_type` field to distinguish

---

## 🔄 RESEARCH-BASED ENHANCEMENTS

### **1. Dealer Pricing Integration**

**CRITICAL:** Dealer pricing MUST activate when `customer.customer_type = 'dealer'`

```php
// app/Services/DealerPricingService.php
class DealerPricingService
{
    /**
     * Calculate price with dealer discount applied
     * PRIORITY: Model > Brand > Addon Category
     */
    public function calculatePrice($customer, $item, $itemType = 'product')
    {
        // Only apply for dealers
        if ($customer->customer_type !== 'dealer') {
            return $item->retail_price;
        }

        // Check Model discount (HIGHEST PRIORITY)
        if ($item->model_id) {
            $modelDiscount = CustomerModelPricing::where('customer_id', $customer->id)
                ->where('model_id', $item->model_id)
                ->first();
            
            if ($modelDiscount) {
                return $item->retail_price * (1 - $modelDiscount->discount_percentage / 100);
            }
        }

        // Check Brand discount (MEDIUM PRIORITY)
        if ($item->brand_id) {
            $brandDiscount = CustomerBrandPricing::where('customer_id', $customer->id)
                ->where('brand_id', $item->brand_id)
                ->first();
            
            if ($brandDiscount) {
                return $item->retail_price * (1 - $brandDiscount->discount_percentage / 100);
            }
        }

        return $item->retail_price;  // No discount
    }
}

// Usage in Order creation
$dealerPricingService = app(DealerPricingService::class);

foreach ($items as $item) {
    $price = $dealerPricingService->calculatePrice($customer, $item);
    
    OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $item->id,
        'price' => $price,  // Dealer price applied
        'original_price' => $item->retail_price,  // Store original for reference
        'quantity' => $item->quantity,
    ]);
}
```

### **2. Tax Inclusive/Exclusive Per Item**

**CRITICAL:** Each order item can have individual tax handling

**Database Update:**
```sql
ALTER TABLE order_items 
ADD COLUMN tax_inclusive BOOLEAN DEFAULT TRUE;

-- Migration
public function up()
{
    Schema::table('order_items', function (Blueprint $table) {
        $table->boolean('tax_inclusive')->default(true)->after('price');
    });
}
```

**Tax Calculation Logic:**
```php
// app/Models/Order.php
public function calculateTotals()
{
    $subtotal = 0;
    $taxAmount = 0;
    $taxRate = 0.05;  // 5% VAT

    foreach ($this->orderItems as $item) {
        $itemSubtotal = $item->price * $item->quantity;
        
        if ($item->tax_inclusive) {
            // Price includes tax - extract it
            $priceWithoutTax = $itemSubtotal / (1 + $taxRate);
            $itemTax = $itemSubtotal - $priceWithoutTax;
            
            $subtotal += $priceWithoutTax;
            $taxAmount += $itemTax;
        } else {
            // Add tax on top
            $itemTax = $itemSubtotal * $taxRate;
            
            $subtotal += $itemSubtotal;
            $taxAmount += $itemTax;
        }
    }

    $this->sub_total = $subtotal;
    $this->tax = $taxAmount;
    $this->total = $subtotal + $taxAmount + $this->shipping - $this->discount;
    $this->save();
}
```

### **3. Product Snapshot Approach**

**CRITICAL:** Store complete product data at time of order (NOT deep relationships)

**Database Update:**
```sql
ALTER TABLE order_items 
ADD COLUMN product_snapshot JSONB,
ADD COLUMN external_product_id VARCHAR(255),
ADD COLUMN external_source VARCHAR(100);

-- Denormalized fields for easy access
ALTER TABLE order_items
ADD COLUMN product_name VARCHAR(255),
ADD COLUMN brand_name VARCHAR(255),
ADD COLUMN model_name VARCHAR(255),
ADD COLUMN sku VARCHAR(255),
ADD COLUMN size VARCHAR(100),
ADD COLUMN bolt_pattern VARCHAR(100);
```

**Snapshot Creation:**
```php
// When adding item to order
public function addItem($product, $quantity)
{
    // Create full product snapshot
    $snapshot = [
        'id' => $product->id,
        'name' => $product->name,
        'sku' => $product->sku,
        'brand' => $product->brand->name ?? null,
        'model' => $product->model->name ?? null,
        'finish' => $product->finish->name ?? null,
        'size' => $product->size,
        'bolt_pattern' => $product->bolt_pattern,
        'offset' => $product->offset,
        'weight' => $product->weight,
        'load_rating' => $product->load_rating,
        'description' => $product->description,
        'specifications' => $product->specifications,
        'images' => $product->images->pluck('url')->toArray(),
        'captured_at' => now(),
    ];

    OrderItem::create([
        'order_id' => $this->id,
        'product_id' => $product->id,  // Reference only
        'external_product_id' => $product->external_id,
        'external_source' => $product->external_source,
        'product_snapshot' => json_encode($snapshot),  // Full snapshot
        
        // Denormalized for queries
        'product_name' => $product->name,
        'brand_name' => $product->brand->name ?? null,
        'model_name' => $product->model->name ?? null,
        'sku' => $product->sku,
        'size' => $product->size,
        'bolt_pattern' => $product->bolt_pattern,
        
        'price' => $product->price,
        'quantity' => $quantity,
        'tax_inclusive' => $product->tax_inclusive ?? true,
    ]);
}
```

### **4. Financial Transaction Recording**

**Record Payment on Order:**
```php
// app/Models/Order.php
public function recordPayment($amount, $method = 'cash', $transactionId = null)
{
    // Create payment record
    $payment = PaymentRecord::create([
        'payment_number' => PaymentRecord::generatePaymentNumber(),
        'order_id' => $this->id,
        'customer_id' => $this->customer_id,
        'amount' => $amount,
        'payment_method' => $method,
        'transaction_id' => $transactionId,
        'payment_date' => now(),
        'recorded_by' => auth()->id(),
    ]);

    // Update order payment tracking
    $this->paid_amount = ($this->paid_amount ?? 0) + $amount;
    $this->outstanding_amount = $this->total - $this->paid_amount;
    
    // Update payment status
    if ($this->outstanding_amount <= 0) {
        $this->payment_status = 'paid';
    } else {
        $this->payment_status = 'partially_paid';
    }
    
    $this->save();

    // Queue Wafeq sync
    \App\Jobs\SyncPaymentToWafeq::dispatch($payment);

    return $payment;
}

public function payments()
{
    return $this->hasMany(PaymentRecord::class);
}
```

**PaymentRecord Model:**
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
        'payment_method',  // cash, card, bank_transfer, cheque
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
}
```

### **5. Quote Conversion Workflow**

**Convert Quote to Invoice:**
```php
// app/Models/Order.php
public function convertQuoteToInvoice()
{
    // Update order to invoice type
    $this->document_type = 'invoice';
    $this->quote_status = 'converted';
    $this->save();

    // Create linked invoice record
    $invoice = Invoice::create([
        'order_id' => $this->id,
        'invoice_number' => Invoice::generateInvoiceNumber(),
        'customer_id' => $this->customer_id,
        'invoice_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => $this->sub_total,
        'tax_amount' => $this->tax,
        'total' => $this->total,
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    // Copy items to invoice
    foreach ($this->orderItems as $orderItem) {
        $invoice->items()->create([
            'product_id' => $orderItem->product_id,
            'product_snapshot' => $orderItem->product_snapshot,
            'product_name' => $orderItem->product_name,
            'brand_name' => $orderItem->brand_name,
            'sku' => $orderItem->sku,
            'price' => $orderItem->price,
            'quantity' => $orderItem->quantity,
            'tax_inclusive' => $orderItem->tax_inclusive,
        ]);
    }

    // Link invoice to order
    $this->converted_to_invoice_id = $invoice->id;
    $this->save();

    return $invoice;
}
```

### **6. Wafeq Accounting Integration**

**Sync to Wafeq:**
```php
// app/Jobs/SyncPaymentToWafeq.php
class SyncPaymentToWafeq implements ShouldQueue
{
    protected $payment;

    public function handle()
    {
        $wafeqService = app(\App\Services\WafeqService::class);

        $response = $wafeqService->createPayment([
            'customer_id' => $this->payment->customer->wafeq_id,
            'invoice_id' => $this->payment->invoice->wafeq_id ?? null,
            'amount' => $this->payment->amount,
            'payment_method' => $this->payment->payment_method,
            'payment_date' => $this->payment->payment_date,
            'reference' => $this->payment->payment_number,
        ]);

        if ($response['success']) {
            $this->payment->markAsSynced($response['wafeq_id']);
        } else {
            // Log error and retry
            \Log::error('Wafeq sync failed for payment ' . $this->payment->payment_number, [
                'error' => $response['error']
            ]);
            throw new \Exception('Wafeq sync failed');
        }
    }
}
```

---

## Testing Recommendations

### Unit Tests
- Order creation with valid data
- Order validation logic
- Status transitions
- Payment status updates
- **NEW:** Dealer pricing calculation
- **NEW:** Tax inclusive/exclusive per item
- **NEW:** Product snapshot creation
- **NEW:** Quote to invoice conversion
- **NEW:** Payment recording

### Integration Tests
- Complete order sync from TunerStop
- Complete order sync from Wholesale
- Order with multiple items
- Multi-warehouse fulfillment
- Quote to order conversion
- **NEW:** Dealer pricing across all items
- **NEW:** Mixed tax inclusive/exclusive items
- **NEW:** Payment recording with Wafeq sync
- **NEW:** Financial transaction recording

### API Tests
- Sync endpoint authentication
- Order data validation
- Duplicate order handling
- Error handling
- **NEW:** Wafeq payment sync
- **NEW:** Dealer pricing API integration

---

## Future Enhancements

1. **Real-time Inventory Sync:** Sync inventory updates back to source systems
2. **Advanced Reporting:** Order analytics dashboard with profit margins
3. **Automated Fulfillment:** Auto-assign warehouses based on proximity
4. **Split Shipments:** Support for partial fulfillment
5. **Returns Management:** Integrated RMA system
6. **Advanced Dealer Pricing:** Time-based discounts, volume discounts
7. **Multi-currency Support:** Beyond AED/USD
8. **Automated Wafeq Reconciliation:** Daily sync verification

---

## Related Documentation
- [Customers Module Architecture](ARCHITECTURE_CUSTOMERS_MODULE.md) - Dealer pricing setup
- [Products Module Architecture](ARCHITECTURE_PRODUCTS_MODULE.md) - Product snapshot details
- [Sync Processes Documentation](ARCHITECTURE_SYNC_PROCESSES.md) - External sync workflows
- [Invoice Module Architecture](ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md) - Invoice conversion
- [Research Findings](RESEARCH_FINDINGS.md) - Complete system research

---

## Changelog
- **2025-10-20:** Initial comprehensive documentation
- **2025-10-20:** Added sync process details
- **2025-10-20:** Documented unified customer system integration
- **2025-10-20:** Added dealer pricing integration
- **2025-10-20:** Added tax inclusive/exclusive per item
- **2025-10-20:** Added product snapshot approach
- **2025-10-20:** Added financial transaction recording
- **2025-10-20:** Added Wafeq accounting integration
- **2025-10-20:** Updated to Laravel 12 + PostgreSQL 15
