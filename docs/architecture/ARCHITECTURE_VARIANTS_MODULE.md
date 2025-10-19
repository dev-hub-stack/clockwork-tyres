# Product Variants Module - Complete Architecture Documentation

## ⚠️ CRITICAL: DEALER PRICING & SNAPSHOT APPROACH

**MOST IMPORTANT FEATURES:**
1. ✅ **Dealer Pricing** - Variant prices calculated via DealerPricingService when customer_type = 'dealer'
2. ✅ **Tax Inclusive per Variant** - Each variant can have different tax handling
3. ✅ **Snapshot at Order Time** - Variant specifications captured in JSONB (not synced continuously)
4. ✅ **Reference-Only Storage** - Variant data for display, external system is source of truth
5. ✅ **On-Demand Sync** - Sync only when needed for orders, not full catalog

**Variants use the SAME pricing and snapshot principles as products.**

---

## Overview
The Product Variants module manages specific SKUs/sizes of products (e.g., 20x8.5, 20x9.5 for a wheel model). Each variant represents a unique combination of size, bolt pattern, offset, and other specifications.

**Last Updated:** October 20, 2025  
**Module Location:** `app/Models/ProductVariant.php`  
**Tech Stack:** Laravel 12 (LTS) + PostgreSQL 15 + Filament v3

---

## Database Schema

### Product Variants Table
**Table Name:** `product_variants`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| product_id | bigint | FK to products |
| sku | varchar(255) | Unique SKU (main identifier) |
| finish_id | bigint | FK to finishes |
| external_variant_id | varchar(255) | TunerStop variant ID |
| external_source | varchar(100) | Source system |

### Specifications
| Column | Type | Description |
|--------|------|-------------|
| size | varchar(50) | Wheel size (e.g., 20x8.5) |
| bolt_pattern | varchar(100) | Bolt pattern (e.g., 5x120) |
| hub_bore | varchar(50) | Center bore diameter |
| offset | varchar(50) | Offset in mm (auto-appends "mm") |
| backspacing | varchar(50) | Backspacing measurement |
| lipsize | varchar(50) | Lip size |
| weight | varchar(50) | Weight (with unit) |
| max_wheel_load | varchar(50) | Load rating |
| rim_diameter | varchar(20) | Diameter |
| rim_width | varchar(20) | Width |

### Pricing
| Column | Type | Description |
|--------|------|-------------|
| price | decimal(10,2) | Current retail price |
| cost | decimal(10,2) | Cost price |
| us_retail_price | decimal(10,2) | US market price |
| uae_retail_price | decimal(10,2) | UAE market price |
| sale_price | decimal(10,2) | Sale price |
| tax_inclusive | boolean | Tax included in price (default TRUE) |

### Status
| Column | Type | Description |
|--------|------|-------------|
| clearance_corner | boolean | Clearance item flag |
| construction | varchar(100) | Cast/Forged |
| total_quantity | int | Total stock |

### Dealer Pricing Support
| Column | Type | Description |
|--------|------|-------------|
| brand_id | bigint | FK to brands (for dealer pricing) |
| model_id | bigint | FK to models (for dealer pricing) |

---

## Model Features

### Fillable Fields
```php
protected $fillable = [
    'sku', 'finish_id', 'size', 'bolt_pattern', 'hub_bore', 'offset', 
    'finish', 'weight', 'backspacing', 'lipsize', 'max_wheel_load', 
    'rim_diameter', 'rim_width', 'price', 'sale_price',
    'clearance_corner', 'product_id', 'cost', 'us_retail_price', 
    'uae_retail_price', 'construction',
];
```

### Auto-Append "mm" to Offset
```php
public function setOffsetAttribute($value)
{
    if (!Str::endsWith($value, 'mm')) {
        $this->attributes['offset'] = $value.'mm';
    } else {
        $this->attributes['offset'] = $value;
    }
}
```

### Out of Stock Scope
```php
protected static function boot()
{
    parent::boot();
    static::addGlobalScope(new OutOfStockScope);
}
```

**Purpose:** Automatically filters out variants with zero stock in queries

---

## Relationships

### Product
```php
public function product()
{
    return $this->belongsTo(Product::class);
}
```

### Inventory
```php
public function inventory()
{
    return $this->hasMany(ProductInventory::class, 'product_variant_id', 'id');
}
```

---

## Import System

### Static Import Method
```php
public static function import($data, $product_id)
{
    $offset = null;
    if (!is_null($data['offset'])) {
        $offset = str_replace(' - ', ' to ', $data['offset']);
        $offset = trim(str_replace('mm', '', $offset));
        $offset = $offset.'mm';
    }
    
    $record = [
        'sku' => trim($data['sku']) ?? null,
        'size' => trim($data['size']),
        'bolt_pattern' => isset($data['boltpattern1']) ? trim($data['boltpattern1']) : null,
        'hub_bore' => trim($data['hubbore']),
        'weight' => trim($data['weight']),
        'lipsize' => $data['lipsize'],
        'backspacing' => @$data['warranty'] ?? @$data['backspacing'],
        'max_wheel_load' => $data['maxwheelload'],
        'rim_diameter' => $data['rimdiameter'],
        'rim_width' => $data['rimwidth'],
        'us_retail_price' => $data['usretail'] ?? null,
        'uae_retail_price' => null,
        'product_id' => $product_id,
        'offset' => trim($offset),
        'construction' => $data['construction'] ?? null,
    ];
    
    return self::updateOrCreate([
        'sku' => trim($data['sku']) ?? null,
        'product_id' => $product_id,
    ], $record);
}
```

---

## Usage Examples

### Get Variant with Inventory
```php
$variant = ProductVariant::with('inventory')->find($id);
$totalStock = $variant->inventory->sum('quantity');
```

### Filter by Size
```php
$variants = ProductVariant::where('size', '20x8.5')->get();
```

### Get Available Variants (In Stock)
```php
$variants = ProductVariant::has('inventory', '>', 0)->get();
```

---

## 🎯 DEALER PRICING FOR VARIANTS

### Overview
Variants inherit dealer pricing from their parent product, with the SAME DealerPricingService logic.

### Implementation

```php
// app/Models/ProductVariant.php
public function getDealerPriceForCustomer(Customer $customer)
{
    $dealerPricingService = app(\App\Services\DealerPricingService::class);
    
    // CRITICAL: Use same pricing service as products
    return $dealerPricingService->calculatePrice($customer, $this, 'variant');
}

// Usage in orders
public function addVariantToOrder($variant, $quantity, $customer)
{
    $price = $variant->getDealerPriceForCustomer($customer);
    
    OrderItem::create([
        'order_id' => $this->id,
        'variant_id' => $variant->id,
        'product_id' => $variant->product_id,
        'quantity' => $quantity,
        'price' => $price,
        'tax_inclusive' => $variant->tax_inclusive ?? true,
    ]);
}
```

### DealerPricingService Support

```php
// app/Services/DealerPricingService.php
public function calculatePrice(Customer $customer, $item, string $type)
{
    // ... existing logic ...
    
    if ($type === 'variant') {
        // Check variant-specific dealer pricing
        $variantPricing = DB::table('customer_variant_pricing')
            ->where('customer_id', $customer->id)
            ->where('variant_id', $item->id)
            ->first();
        
        if ($variantPricing) {
            return $variantPricing->dealer_price;
        }
        
        // Fall back to brand/model pricing via product
        $product = $item->product;
        return $this->calculatePrice($customer, $product, 'product');
    }
    
    // ... rest of logic ...
}
```

### Optional: Variant-Specific Dealer Pricing Table

```sql
CREATE TABLE customer_variant_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT REFERENCES customers(id),
    variant_id BIGINT REFERENCES product_variants(id),
    dealer_price DECIMAL(15,2),
    discount_percentage DECIMAL(5,2),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE(customer_id, variant_id)
);

CREATE INDEX idx_customer_variant_pricing_customer ON customer_variant_pricing(customer_id);
CREATE INDEX idx_customer_variant_pricing_variant ON customer_variant_pricing(variant_id);
```

---

## 📸 VARIANT SNAPSHOT SYSTEM

### Overview
Variant specifications are captured at order time using VariantSnapshotService (extends ProductSnapshotService).

### Service Implementation

```php
// app/Services/VariantSnapshotService.php
namespace App\Services;

class VariantSnapshotService
{
    public function createSnapshot(ProductVariant $variant): array
    {
        return [
            // Core identification
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'sku' => $variant->sku,
            
            // Denormalized product info
            'product_name' => $variant->product->name ?? null,
            'brand_name' => $variant->product->brand->name ?? null,
            'model_name' => $variant->product->model->name ?? null,
            
            // Variant specifications
            'size' => $variant->size,
            'bolt_pattern' => $variant->bolt_pattern,
            'hub_bore' => $variant->hub_bore,
            'offset' => $variant->offset,
            'backspacing' => $variant->backspacing,
            'lipsize' => $variant->lipsize,
            'weight' => $variant->weight,
            'max_wheel_load' => $variant->max_wheel_load,
            'rim_diameter' => $variant->rim_diameter,
            'rim_width' => $variant->rim_width,
            
            // Finish
            'finish_id' => $variant->finish_id,
            'finish_name' => $variant->finish->name ?? null,
            
            // Construction
            'construction' => $variant->construction,
            'clearance_corner' => $variant->clearance_corner,
            
            // Pricing at snapshot time
            'price' => $variant->price,
            'cost' => $variant->cost,
            'sale_price' => $variant->sale_price,
            'us_retail_price' => $variant->us_retail_price,
            'uae_retail_price' => $variant->uae_retail_price,
            
            // Metadata
            'snapshot_at' => now()->toISOString(),
        ];
    }

    public function querySnapshot(string $jsonbColumn, string $field, $value)
    {
        // PostgreSQL JSONB query helper
        return "($jsonbColumn->>'$field' = '$value')";
    }
}
```

### Usage in Orders

```php
// When adding variant to order
public function addVariantToOrder($variant, $quantity, $customer)
{
    $snapshotService = app(VariantSnapshotService::class);
    $dealerPricingService = app(DealerPricingService::class);
    
    // Create snapshot
    $snapshot = $snapshotService->createSnapshot($variant);
    
    // Calculate price (dealer or regular)
    $price = $dealerPricingService->calculatePrice($customer, $variant, 'variant');
    
    // Create order item with snapshot
    OrderItem::create([
        'order_id' => $this->id,
        'variant_id' => $variant->id,
        'product_id' => $variant->product_id,
        'variant_snapshot' => json_encode($snapshot),  // Store as JSONB
        'sku' => $variant->sku,
        'product_name' => $snapshot['product_name'],
        'size' => $snapshot['size'],
        'quantity' => $quantity,
        'price' => $price,
        'tax_inclusive' => $variant->tax_inclusive ?? true,
    ]);
}
```

### PostgreSQL JSONB Queries

```php
// Find orders with specific variant size
$orders = Order::whereRaw("order_items.variant_snapshot->>'size' = '20x8.5'")->get();

// Find orders with specific bolt pattern
$orders = Order::whereRaw("order_items.variant_snapshot->>'bolt_pattern' = '5x120'")->get();

// Find orders with clearance items
$orders = Order::whereRaw("(order_items.variant_snapshot->>'clearance_corner')::boolean = true")->get();

// Find orders with forged construction
$orders = Order::whereRaw("order_items.variant_snapshot->>'construction' = 'Forged'")->get();
```

---

## 💰 TAX INCLUSIVE/EXCLUSIVE HANDLING

### Per-Variant Tax Setting

```php
// Each variant can have different tax handling
$variant = ProductVariant::find($id);

if ($variant->tax_inclusive) {
    // Price includes tax - extract tax amount
    $taxRate = 0.05; // 5% VAT
    $priceBeforeTax = $variant->price / (1 + $taxRate);
    $taxAmount = $variant->price - $priceBeforeTax;
} else {
    // Price excludes tax - add tax amount
    $taxRate = 0.05;
    $priceBeforeTax = $variant->price;
    $taxAmount = $variant->price * $taxRate;
    $totalPrice = $variant->price + $taxAmount;
}
```

### In Order Items

```php
// When calculating order totals
public function calculateTotals()
{
    $subtotal = 0;
    $taxAmount = 0;
    
    foreach ($this->items as $item) {
        $itemTotal = $item->price * $item->quantity;
        
        if ($item->tax_inclusive) {
            // Price includes tax - extract it
            $taxRate = 0.05;
            $priceBeforeTax = $item->price / (1 + $taxRate);
            $itemTax = ($item->price - $priceBeforeTax) * $item->quantity;
            
            $subtotal += $priceBeforeTax * $item->quantity;
            $taxAmount += $itemTax;
        } else {
            // Price excludes tax - add it
            $taxRate = 0.05;
            $itemTax = $item->price * $taxRate * $item->quantity;
            
            $subtotal += $itemTotal;
            $taxAmount += $itemTax;
        }
    }
    
    $this->subtotal = $subtotal;
    $this->tax_amount = $taxAmount;
    $this->total = $subtotal + $taxAmount;
    $this->save();
}
```

---

## Related Documentation
- [Products Module](ARCHITECTURE_PRODUCTS_MODULE.md) - Parent product relationship
- [Inventory Module](ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md) - Stock management
- [Customers Module](ARCHITECTURE_CUSTOMERS_MODULE.md) - DealerPricingService
- [Orders Module](ARCHITECTURE_ORDERS_MODULE.md) - Variant snapshots in orders

---

## Changelog
- **2025-10-20:** Added dealer pricing support for variants
- **2025-10-20:** Added VariantSnapshotService implementation
- **2025-10-20:** Added tax_inclusive per-variant handling
- **2025-10-20:** Added PostgreSQL JSONB query examples
- **2025-10-20:** Updated to Laravel 12 + PostgreSQL 15
