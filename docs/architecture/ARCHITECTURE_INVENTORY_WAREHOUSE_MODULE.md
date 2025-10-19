# Product Inventory & Warehouse Module - Complete Architecture

## ⚠️ CRITICAL: REFERENCE-ONLY INVENTORY APPROACH

**MOST IMPORTANT UNDERSTANDING:**
1. ✅ **External System is Source of Truth** - Inventory data is managed in external warehouse/ERP system
2. ✅ **Reference-Only Storage** - This system stores inventory for display purposes only
3. ✅ **Do NOT Sync Full Inventory** - Only sync what's needed for orders (on-demand)
4. ✅ **Consignment Returns** - When items returned, log in system but external system handles actual inventory
5. ✅ **No Automatic Stock Updates** - Inventory changes happen in external system, then sync to this system

**This is NOT a full inventory management system - it's a reference database for order fulfillment.**

---

## Overview
The Inventory module tracks stock levels for products, variants, and addons across multiple warehouses with geolocation-based fulfillment optimization.

**Last Updated:** October 20, 2025  
**Module Locations:** `app/Models/ProductInventory.php`, `app/Models/Warehouse.php`  
**Tech Stack:** Laravel 12 (LTS) + PostgreSQL 15 + Filament v3

---

## Product Inventory

### Database Schema
**Table:** `product_inventories`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| warehouse_id | bigint | FK to warehouses |
| product_id | bigint | FK to products (nullable) |
| product_variant_id | bigint | FK to product_variants (nullable) |
| add_on_id | bigint | FK to add_ons (nullable) |
| quantity | int | Stock quantity |
| eta | date | Expected arrival date |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### Model Features

```php
class ProductInventory extends Model
{
    protected $table = 'product_inventories';
    
    protected $fillable = [
        'warehouse_id', 
        'product_id', 
        'product_variant_id', 
        'quantity', 
        'eta', 
        'add_on_id',
    ];
}
```

### Relationships

**Warehouse:**
```php
public function warehouse()
{
    return $this->belongsTo(Warehouse::class, 'warehouse_id');
}
```

**Product:**
```php
public function product()
{
    return $this->belongsTo(Product::class);
}
```

**Variant:**
```php
public function variant()
{
    return $this->belongsTo(ProductVariant::class, 'product_variant_id');
}
```

### Scopes

**1. With Warehouse Ordered by Distance:**
```php
public function scopeWithWarehouseOrderedByDistance($query, $lat, $lng)
{
    return $query->with(['warehouse' => function ($query) use ($lat, $lng) {
        $query->orderByDistance($lat, $lng);
    }]);
}
```

**2. By Product and Variant:**
```php
public function scopeByProductAndVariant($query, $productId, $variantId)
{
    return $query->where('product_id', $productId)
        ->where('product_variant_id', $variantId);
}
```

**3. Exclude Zero Quantity:**
```php
public function scopeExcludeZeroQuantity($query)
{
    return $query->where('quantity', '>', 0);
}
```

### Import Method
```php
public static function import($product_id, $product_variant_id)
{
    $wareHouses = Warehouse::pluck('id');
    
    foreach ($wareHouses as $warehouse) {
        $getlastQty = self::where('product_id', $product_id)
            ->where('product_variant_id', $product_variant_id)
            ->where('warehouse_id', $warehouse)
            ->first();
        
        if (empty($getlastQty)) {
            $record = ['quantity' => 0];
            
            self::updateOrCreate([
                'product_variant_id' => $product_variant_id,
                'product_id' => $product_id,
                'warehouse_id' => $warehouse,
            ], $record);
        }
    }
}
```

**Purpose:** Creates zero-quantity inventory records for all warehouses when new product/variant added

---

## Warehouse Management

### Database Schema
**Table:** `warehouses`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| warehouse_name | varchar(255) | Warehouse name |
| code | varchar(50) | Warehouse code (for CSV) |
| address | text | Physical address |
| country | varchar(100) | Country |
| state | varchar(100) | State/Province |
| status | tinyint | 1=active, 0=inactive |
| lat | decimal(10,8) | Latitude |
| lng | decimal(11,8) | Longitude |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update timestamp |

### Model Features

```php
class Warehouse extends BaseModel
{
    use Spatial; // For geolocation features
    
    const NAME_PREFIX = 'WH-';
    
    protected $fillable = [
        'warehouse_name',
        'code',
        'address',
        'status',
        'country',
        'state',
        'lat',
        'lng'
    ];
}
```

### Geolocation Features

**1. Order by Distance (Haversine Formula):**
```php
public function scopeOrderByDistance($query, $lat, $lng)
{
    return $query
        ->selectRaw(
            '( 6371 * acos( cos( radians(?) ) * cos( radians( warehouses.lat ) ) 
             * cos( radians( warehouses.lng ) - radians(?) ) 
             + sin( radians(?) ) * sin( radians( warehouses.lat ) ) ) ) AS distance',
            [$lat, $lng, $lat]
        )
        ->orderByRaw('distance IS NULL')
        ->orderBy('distance','ASC');
}
```

**Formula Explanation:**
- Uses Haversine formula to calculate distance in kilometers
- NULLs (warehouses without coordinates) sorted last
- Closest warehouse first

**2. With Distance (Optional Coordinates):**
```php
public function scopeWithDistance($query, $lat = null, $lng = null)
{
    if (!empty($lat) && !empty($lng)) {
        return $this->scopeOrderByDistance($query, $lat, $lng);
    } else {
        return $query->select('warehouses.*')
            ->selectRaw('0 as distance');
    }
}
```

**3. Exclude Zero Quantity:**
```php
public function scopeExcludeZeroQuantity($query)
{
    return $query->where('product_inventories.quantity', '>', 0);
}
```

### Name Generation
```php
public static function generateName(Warehouse $wareHouse, $increment = 1)
{
    $name = self::NAME_PREFIX.$increment.' '.$wareHouse->state;
    $warehouse = self::where('warehouse_name', $name)->value('warehouse_name');
    
    if (empty($warehouse)) {
        return $name;
    }
    
    return self::generateName($wareHouse, $increment + 1);
}
```

**Example:** `WH-1 Dubai`, `WH-2 Dubai`, etc.

---

## Inventory Fulfillment Workflow

### Order Placement Flow
```
1. Customer places order
2. System identifies customer location (billing/shipping address)
3. Extract lat/lng from address
4. Query inventory with distance calculation
5. Allocate from nearest warehouse with stock
6. Create OrderItemQuantity record
7. Decrease warehouse inventory
```

### Query Example
```php
// Get available inventory sorted by distance
$inventory = ProductInventory::byProductAndVariant($productId, $variantId)
    ->excludeZeroQuantity()
    ->withWarehouseOrderedByDistance($customerLat, $customerLng)
    ->get();

// Take from closest warehouse
$closestWarehouse = $inventory->first();
```

---

## Usage Examples

### Get Total Stock for Variant
```php
$variant = ProductVariant::find($id);
$totalStock = $variant->inventory()->sum('quantity');
```

### Get Stock by Warehouse
```php
$inventory = ProductInventory::where('product_variant_id', $variantId)
    ->where('warehouse_id', $warehouseId)
    ->first();
$qty = $inventory->quantity ?? 0;
```

### Find Nearest Warehouse with Stock
```php
$inventory = ProductInventory::byProductAndVariant($productId, $variantId)
    ->excludeZeroQuantity()
    ->withWarehouseOrderedByDistance(25.2048, 55.2708) // Dubai coords
    ->first();

$warehouse = $inventory->warehouse;
$distance = $inventory->distance; // in kilometers
```

### Create Inventory for New Product
```php
ProductInventory::import($productId, $variantId);
```

### Update Inventory
```php
$inventory = ProductInventory::where('product_variant_id', $variantId)
    ->where('warehouse_id', $warehouseId)
    ->first();

$inventory->quantity += 10; // Add stock
$inventory->save();
```

---

## 🔄 CONSIGNMENT RETURN HANDLING

### Overview
When consignment items are returned, they must be logged and added back to inventory.

### Database Schema - Inventory Logs

```sql
CREATE TABLE inventory_logs (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT REFERENCES products(id),
    addon_id BIGINT REFERENCES add_ons(id),
    variant_id BIGINT REFERENCES product_variants(id),
    warehouse_id BIGINT REFERENCES warehouses(id),
    
    -- Log details
    type VARCHAR(50),  -- 'consignment_return', 'sale', 'adjustment', 'transfer'
    quantity INTEGER,
    previous_quantity INTEGER,
    new_quantity INTEGER,
    
    -- Reference to source
    reference_type VARCHAR(50),  -- 'consignment', 'order', 'manual'
    reference_id BIGINT,
    
    notes TEXT,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP
);

CREATE INDEX idx_inventory_logs_product_id ON inventory_logs(product_id);
CREATE INDEX idx_inventory_logs_type ON inventory_logs(type);
CREATE INDEX idx_inventory_logs_reference ON inventory_logs(reference_type, reference_id);
```

### Implementation

```php
// app/Models/InventoryLog.php
class InventoryLog extends Model
{
    protected $fillable = [
        'product_id',
        'addon_id',
        'variant_id',
        'warehouse_id',
        'type',
        'quantity',
        'previous_quantity',
        'new_quantity',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    public static function logConsignmentReturn($item, $quantity, $consignmentId, $warehouseId = null)
    {
        // Determine warehouse (default to primary if not specified)
        $warehouseId = $warehouseId ?? Warehouse::where('status', 1)->first()->id;

        // Get current inventory
        $inventory = ProductInventory::where('warehouse_id', $warehouseId)
            ->where('product_id', $item->product_id)
            ->where('product_variant_id', $item->variant_id)
            ->where('add_on_id', $item->addon_id)
            ->first();

        $previousQty = $inventory->quantity ?? 0;
        $newQty = $previousQty + $quantity;

        // Update or create inventory record
        if ($inventory) {
            $inventory->quantity = $newQty;
            $inventory->save();
        } else {
            $inventory = ProductInventory::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->variant_id,
                'add_on_id' => $item->addon_id,
                'quantity' => $quantity,
            ]);
        }

        // Create log entry
        return self::create([
            'product_id' => $item->product_id,
            'addon_id' => $item->addon_id,
            'variant_id' => $item->variant_id,
            'warehouse_id' => $warehouseId,
            'type' => 'consignment_return',
            'quantity' => $quantity,
            'previous_quantity' => $previousQty,
            'new_quantity' => $newQty,
            'reference_type' => 'consignment',
            'reference_id' => $consignmentId,
            'notes' => "Returned from consignment",
            'created_by' => auth()->id(),
        ]);
    }

    public static function logSale($item, $quantity, $orderId, $warehouseId)
    {
        $inventory = ProductInventory::where('warehouse_id', $warehouseId)
            ->where('product_id', $item->product_id)
            ->where('product_variant_id', $item->variant_id)
            ->first();

        if (!$inventory || $inventory->quantity < $quantity) {
            throw new \Exception('Insufficient inventory');
        }

        $previousQty = $inventory->quantity;
        $inventory->quantity -= $quantity;
        $inventory->save();

        return self::create([
            'product_id' => $item->product_id,
            'variant_id' => $item->variant_id,
            'warehouse_id' => $warehouseId,
            'type' => 'sale',
            'quantity' => -$quantity,
            'previous_quantity' => $previousQty,
            'new_quantity' => $inventory->quantity,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'notes' => "Sold via order",
            'created_by' => auth()->id(),
        ]);
    }
}
```

### Usage in Consignment Module

```php
// When consignment items are returned (from Consignment.php)
protected function addBackToInventory($productId, $addonId, $quantity)
{
    // CRITICAL: Log return in this system
    $item = (object)[
        'product_id' => $productId,
        'addon_id' => $addonId,
        'variant_id' => null,
    ];

    InventoryLog::logConsignmentReturn($item, $quantity, $this->id);

    // OPTIONAL: Call external system API to update their inventory
    // Http::post(config('external.inventory_api'), [
    //     'action' => 'return',
    //     'product_id' => $productId,
    //     'quantity' => $quantity,
    //     'reference' => $this->consignment_number,
    // ]);
}
```

### Inventory Log Report

```php
// Get all inventory movements for a product
public static function getProductMovements($productId, $startDate = null, $endDate = null)
{
    $query = self::where('product_id', $productId)
        ->orderBy('created_at', 'desc');

    if ($startDate) {
        $query->whereDate('created_at', '>=', $startDate);
    }

    if ($endDate) {
        $query->whereDate('created_at', '<=', $endDate);
    }

    return $query->get();
}

// Get current inventory with history
public static function getCurrentInventoryWithHistory($warehouseId, $productId)
{
    $currentInventory = ProductInventory::where('warehouse_id', $warehouseId)
        ->where('product_id', $productId)
        ->first();

    $movements = self::where('warehouse_id', $warehouseId)
        ->where('product_id', $productId)
        ->orderBy('created_at', 'desc')
        ->limit(50)
        ->get();

    return [
        'current_quantity' => $currentInventory->quantity ?? 0,
        'movements' => $movements,
    ];
}
```

---

## External System Integration

### API Structure (Example)

```php
// config/external.php
return [
    'inventory_api' => env('EXTERNAL_INVENTORY_API_URL'),
    'inventory_api_key' => env('EXTERNAL_INVENTORY_API_KEY'),
];

// app/Services/ExternalInventoryService.php
class ExternalInventoryService
{
    public function syncInventoryFromExternal($productId)
    {
        // Fetch inventory from external system
        $response = Http::withToken(config('external.inventory_api_key'))
            ->get(config('external.inventory_api') . '/products/' . $productId . '/inventory');

        if ($response->successful()) {
            $externalInventory = $response->json();

            // Update local inventory for reference
            foreach ($externalInventory['warehouses'] as $warehouseData) {
                ProductInventory::updateOrCreate(
                    [
                        'product_id' => $productId,
                        'warehouse_id' => $this->mapExternalWarehouse($warehouseData['code']),
                    ],
                    [
                        'quantity' => $warehouseData['quantity'],
                        'eta' => $warehouseData['eta'] ?? null,
                    ]
                );
            }

            return true;
        }

        return false;
    }

    protected function mapExternalWarehouse($externalCode)
    {
        // Map external warehouse code to internal warehouse ID
        return Warehouse::where('code', $externalCode)->value('id');
    }
}
```

---

## Related Documentation
- [Products Module](ARCHITECTURE_PRODUCTS_MODULE.md)
- [Variants Module](ARCHITECTURE_VARIANTS_MODULE.md)
- [AddOns Module](ARCHITECTURE_ADDONS_MODULE.md)
- [Orders Module](ARCHITECTURE_ORDERS_MODULE.md)
- [Consignment Module](ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md)
