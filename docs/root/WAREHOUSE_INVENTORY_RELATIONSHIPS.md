# Warehouse & Inventory Relationship Structure

## Database Schema (from Old Reporting System)

### ProductInventory Table
```sql
product_inventories:
  - warehouse_id (FK to warehouses)
  - product_id (FK to products) - NULLABLE
  - product_variant_id (FK to product_variants) - NULLABLE  
  - add_on_id (FK to addons) - NULLABLE
  - quantity (INT)
  - eta (VARCHAR 15) - Expected arrival date
  - eta_qty (INT) - Expected inbound quantity
```

**Key Point**: Only ONE of (product_id, product_variant_id, add_on_id) should be set per record.

---

## Relationship Mapping

### 1. Warehouse → ProductInventory (One-to-Many)

**Warehouse Model**:
```php
public function inventories(): HasMany
{
    return $this->hasMany(ProductInventory::class);
}
```

**Usage**:
```php
$warehouse = Warehouse::find(1);
$allInventory = $warehouse->inventories; // All inventory in this warehouse
$products = $warehouse->inventories()->whereNotNull('product_variant_id')->get();
$addons = $warehouse->inventories()->whereNotNull('add_on_id')->get();
```

---

### 2. Product → ProductInventory (One-to-Many)

**Product Model**:
```php
public function inventories(): HasMany
{
    return $this->hasMany(ProductInventory::class);
}
```

**Usage**:
```php
$product = Product::find(1);
$inventory = $product->inventories; // Inventory across ALL warehouses
$totalQty = $product->inventories()->sum('quantity');
$whMainQty = $product->inventories()
    ->where('warehouse_id', 1)
    ->sum('quantity');
```

---

### 3. ProductVariant → ProductInventory (One-to-Many)

**ProductVariant Model**:
```php
public function inventories(): HasMany
{
    return $this->hasMany(ProductInventory::class);
}
```

**Usage**:
```php
$variant = ProductVariant::find(1);
$inventory = $variant->inventories; // Inventory across ALL warehouses
$totalQty = $variant->inventories()->sum('quantity');

// Get inventory for specific warehouse
$whMainQty = $variant->inventories()
    ->where('warehouse_id', 1)
    ->first();
```

---

### 4. Addon → ProductInventory (One-to-Many)

**Addon Model**:
```php
public function inventories(): HasMany
{
    return $this->hasMany(ProductInventory::class, 'add_on_id');
}
```

**Usage**:
```php
$addon = Addon::find(1);
$inventory = $addon->inventories; // Inventory across ALL warehouses
$totalQty = $addon->inventories()->sum('quantity');
```

---

### 5. ProductInventory → Warehouse (Belongs-to)

**ProductInventory Model**:
```php
public function warehouse(): BelongsTo
{
    return $this->belongsTo(Warehouse::class);
}

public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}

public function productVariant(): BelongsTo
{
    return $this->belongsTo(ProductVariant::class);
}

public function addon(): BelongsTo
{
    return $this->belongsTo(Addon::class, 'add_on_id');
}
```

**Usage**:
```php
$inventory = ProductInventory::find(1);
$warehouse = $inventory->warehouse;
$variant = $inventory->productVariant;
```

---

## Inventory Grid Data Structure

### How Grid Loads Data (from InventoryGrid.php):

```php
$products = Product::with([
    'brand',
    'model', 
    'finish',
    'variants' => function ($query) {
        $query->with(['inventories.warehouse']);
    }
])->whereHas('variants')->get();

// Transform for pqGrid
foreach ($products as $product) {
    foreach ($product->variants as $variant) {
        $row = [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'product_full_name' => $product->brand->name . ' ' . $product->model->name,
            'inventory' => []  // <-- KEY STRUCTURE
        ];
        
        // Add inventory data for each warehouse
        foreach ($variant->inventories as $inventory) {
            $row['inventory'][] = [
                'warehouse_id' => $inventory->warehouse_id,
                'quantity' => $inventory->quantity ?? 0,
                'eta' => $inventory->eta ?? '',
                'eta_qty' => $inventory->eta_qty ?? 0,
            ];
        }
        
        $this->products_data[] = $row;
    }
}
```

### JavaScript Transformation (inventory-grid.blade.php):

```javascript
var data = @json($products_data);

// Transform inventory array to individual columns
data.forEach(function(element, index) {
    element.inventory.forEach(function (el, ind){
        element['qty'+el.warehouse_id] = el.quantity;
        element['eta'+el.warehouse_id] = el.eta;
        element['e_ta_q_ty'+el.warehouse_id] = el.eta_qty;
    });
    data[index] = element;
});

// Now each row has: qty1, eta1, e_ta_q_ty1, qty2, eta2, e_ta_q_ty2, etc.
```

---

## Common Query Patterns

### 1. Get Total Quantity for a Variant Across All Warehouses

```php
$variant = ProductVariant::find(1);
$totalQty = $variant->inventories()->sum('quantity');
```

### 2. Get Inventory for Specific Variant + Warehouse

```php
$inventory = ProductInventory::where('product_variant_id', $variantId)
    ->where('warehouse_id', $warehouseId)
    ->first();
```

### 3. Get All Variants with Low Stock

```php
$lowStock = ProductVariant::whereHas('inventories', function($q) {
    $q->havingRaw('SUM(quantity) < 10');
})->get();
```

### 4. Get Variants Available in Specific Warehouse

```php
$variants = ProductVariant::whereHas('inventories', function($q) use ($warehouseId) {
    $q->where('warehouse_id', $warehouseId)
      ->where('quantity', '>', 0);
})->get();
```

### 5. Get Warehouses with Stock for Specific Variant

```php
$variant = ProductVariant::find(1);
$warehouses = Warehouse::whereHas('inventories', function($q) use ($variant) {
    $q->where('product_variant_id', $variant->id)
      ->where('quantity', '>', 0);
})->get();
```

### 6. Get Expected Arrivals (ETA) for a Warehouse

```php
$warehouse = Warehouse::find(1);
$expectedArrivals = $warehouse->inventories()
    ->whereNotNull('eta')
    ->where('eta_qty', '>', 0)
    ->with(['productVariant', 'addon'])
    ->get();
```

---

## Auto-Creation Flow

### When ProductVariant is Created:

**ProductVariantInventoryObserver.php**:
```php
public function created(ProductVariant $variant)
{
    // Get all active warehouses
    $warehouses = Warehouse::where('status', 1)->get();
    
    foreach ($warehouses as $warehouse) {
        ProductInventory::create([
            'warehouse_id' => $warehouse->id,
            'product_variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'quantity' => 0,
            'eta' => null,
            'eta_qty' => 0,
        ]);
    }
}
```

### When Addon is Created:

**AddonInventoryObserver.php** (to be created):
```php
public function created(Addon $addon)
{
    $warehouses = Warehouse::where('status', 1)->get();
    
    foreach ($warehouses as $warehouse) {
        ProductInventory::create([
            'warehouse_id' => $warehouse->id,
            'add_on_id' => $addon->id,
            'quantity' => 0,
            'eta' => null,
            'eta_qty' => 0,
        ]);
    }
}
```

---

## Status Summary

### ✅ Completed Relationships:

1. **Warehouse → ProductInventory** (One-to-Many) ✅
2. **Product → ProductInventory** (One-to-Many) ✅
3. **ProductVariant → ProductInventory** (One-to-Many) ✅
4. **Addon → ProductInventory** (One-to-Many) ✅
5. **ProductInventory → Warehouse** (Belongs-to) ✅
6. **ProductInventory → Product** (Belongs-to) ✅
7. **ProductInventory → ProductVariant** (Belongs-to) ✅
8. **ProductInventory → Addon** (Belongs-to) ✅

### 🔧 Observers Created:

1. **WarehouseObserver** ✅ - Enforces single primary warehouse
2. **ProductVariantInventoryObserver** ✅ - Auto-creates inventory when variant is created

### 📋 To Create:

1. **AddonInventoryObserver** - Auto-create inventory when addon is created
2. Test scripts to verify all relationships work correctly

---

## Testing Checklist

- [ ] Create warehouse → verify inventories relationship works
- [ ] Create product variant → verify inventory auto-created for all warehouses
- [ ] Create addon → verify inventory auto-created for all warehouses
- [ ] Load Inventory Grid → verify data appears correctly
- [ ] Edit quantity in grid → verify ProductInventory updated
- [ ] Add new warehouse → verify it appears as empty column in grid
- [ ] Query total quantity for variant → verify sum works
- [ ] Query inventory for specific warehouse → verify filtering works
