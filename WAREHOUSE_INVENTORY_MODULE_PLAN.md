# Warehouse & Inventory Module - Implementation Plan

**Created:** October 24, 2025  
**Updated:** October 24, 2025 - REVISED TO FULL CRUD  
**Status:** 🚀 Planning Phase  
**Priority:** HIGH - Required for Products & AddOns functionality

---

## 📋 Executive Summary

Based on user requirements, we are implementing a **FULL CRUD Inventory & Warehouse Management** system with the **grid-based interface** from the old Reporting system. This is NOT reference-only - it includes complete Create, Read, Update, Delete operations for both warehouses and inventory.

### 🎯 Key Features

1. ✅ **Full CRUD Operations** - Create, Read, Update, Delete warehouses and inventory
2. ✅ **Grid-Based Interface** - Excel-like grid for bulk inventory management (like old system)
3. ✅ **Multi-Warehouse Support** - Manage inventory across multiple warehouse locations
4. ✅ **Geolocation-Based Fulfillment** - Use Haversine formula to find nearest warehouse
5. ✅ **Bulk Operations** - Import/Export Excel, bulk updates, mass transfers
6. ✅ **Audit Trail** - Log all inventory changes with user tracking
7. ✅ **Real-time Stock Management** - Live updates, low stock alerts, stock transfers

---

## 🎨 **GRID-BASED INTERFACE** (Core Feature - Like Old System)

### Overview
The inventory grid provides an Excel-like interface for managing stock across multiple warehouses, exactly like `C:\Users\Dell\Documents\Reporting\app\Http\Controllers\ProductInventoryController.php`.

### Grid Layout Structure

```
┌──────────────────────┬────────────────┬────────────────┬────────────────┬──────────┐
│ Product/Variant/Addon│  Main WH (US)  │  EU Warehouse  │  Asia WH       │  Total   │
├──────────────────────┼────────────────┼────────────────┼────────────────┼──────────┤
│ RSE 18x8.5 Gloss BLK │    [  25  ]    │    [  10  ]    │    [   5  ]    │    40    │
│ BLQ 19x9.0 Matte BLK │    [  15  ]    │    [   0  ]    │    [  20  ]    │    35    │
│ Chrome Lug Nuts Set  │    [ 100  ]    │    [  50  ]    │    [  75  ]    │   225    │
│ Hub Rings 72.6-66.1  │    [  80  ]    │    [  40  ]    │    [  60  ]    │   180    │
└──────────────────────┴────────────────┴────────────────┴────────────────┴──────────┘
           ↑                    ↑                 ↑                ↑            ↑
    Product Info     Editable Quantity    Editable Quantity  Editable Qty  Auto Sum
```

### Key Features

#### 1. **Dynamic Warehouse Columns**
- Columns automatically generated based on active warehouses
- Each warehouse gets its own column
- Column headers show warehouse name and code
- Hide inactive warehouses

#### 2. **Product Rows**
Three types of inventory rows:
- **Products**: Base products (if not using variants)
- **Product Variants**: Individual size/finish combinations
- **AddOns**: Lug nuts, hub rings, spacers, TPMS

#### 3. **Inline Editing**
- Click any quantity cell to edit
- Auto-save on blur or Enter key
- Visual feedback (loading spinner, success checkmark)
- Validation (negative numbers not allowed)
- Color coding:
  - 🟢 Green: Stock > 50
  - 🟡 Yellow: Stock 1-50
  - 🔴 Red: Stock = 0

#### 4. **Bulk Operations**

**Excel Import:**
```
SKU          | Main WH | EU WH | Asia WH
RSE-18X8.5   | 25      | 10    | 5
BLQ-19X9.0   | 15      | 0     | 20
```
- Upload Excel/CSV with warehouse columns
- Map columns to warehouses
- Validate all quantities
- Preview before import
- Log all changes

**Excel Export:**
- Export current grid view to Excel
- Include all warehouses as columns
- Include product details (SKU, name, etc.)
- Include totals row

**Bulk Update:**
- Select multiple rows
- Apply percentage increase/decrease
- Set specific quantity for all
- Transfer to another warehouse

#### 5. **Filtering & Search**

**Filters:**
- Product Type: All / Products / Variants / AddOns
- Stock Status: All / In Stock / Low Stock / Out of Stock
- Brand: All / Rotiform / BBS / Vossen / etc.
- Model: All / RSE / BLQ / KPS / etc.
- Addon Category: All / Lug Nuts / Hub Rings / etc.

**Search:**
- Real-time search by SKU, name, or product code
- Highlight matching rows
- Search across all columns

#### 6. **Real-time Calculations**
- Total column auto-calculates sum across warehouses
- Grand total at bottom of each warehouse column
- Total inventory value (quantity × price)
- Low stock count indicator

### Component Structure

```php
// app/Livewire/InventoryGrid.php
class InventoryGrid extends Component
{
    public $warehouses;
    public $inventoryData;
    public $filters = [
        'type' => 'all',
        'stock_status' => 'all',
        'brand_id' => null,
        'search' => ''
    ];
    
    public function mount()
    {
        $this->warehouses = Warehouse::where('is_active', true)
            ->orderBy('name')
            ->get();
        $this->loadInventory();
    }
    
    public function loadInventory()
    {
        // Get all products/variants/addons with their inventory
        $query = $this->buildQuery();
        $this->inventoryData = $query->get();
    }
    
    public function updateQuantity($itemType, $itemId, $warehouseId, $quantity)
    {
        // Update inventory for specific item + warehouse
        ProductInventory::updateOrCreate(
            [
                $itemType . '_id' => $itemId,
                'warehouse_id' => $warehouseId
            ],
            ['quantity' => max(0, (int)$quantity)]
        );
        
        // Log the change
        InventoryLog::create([...]);
        
        // Refresh grid
        $this->loadInventory();
        
        // Show success message
        $this->dispatch('inventory-updated');
    }
    
    public function importExcel($file)
    {
        // Handle Excel import
        Excel::import(new InventoryImport, $file);
        $this->loadInventory();
    }
    
    public function exportExcel()
    {
        return Excel::download(new InventoryExport($this->inventoryData), 'inventory.xlsx');
    }
}
```

### Blade Template

```blade
<div class="inventory-grid">
    {{-- Filters --}}
    <div class="filters-bar">
        <select wire:model.live="filters.type">
            <option value="all">All Types</option>
            <option value="products">Products Only</option>
            <option value="variants">Variants Only</option>
            <option value="addons">AddOns Only</option>
        </select>
        
        <select wire:model.live="filters.stock_status">
            <option value="all">All Stock Levels</option>
            <option value="in_stock">In Stock</option>
            <option value="low_stock">Low Stock (< 50)</option>
            <option value="out_of_stock">Out of Stock</option>
        </select>
        
        <input type="text" wire:model.live.debounce.300ms="filters.search" 
               placeholder="Search SKU, name...">
        
        <button wire:click="exportExcel">📥 Export Excel</button>
        <button onclick="document.getElementById('import-file').click()">
            📤 Import Excel
        </button>
        <input type="file" id="import-file" wire:model="importFile" hidden 
               accept=".xlsx,.csv">
    </div>
    
    {{-- Grid Table --}}
    <div class="grid-container">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Product / Variant / AddOn</th>
                    <th>SKU</th>
                    @foreach($warehouses as $warehouse)
                        <th>
                            {{ $warehouse->warehouse_name }}
                            <small>({{ $warehouse->code }})</small>
                        </th>
                    @endforeach
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($inventoryData as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->sku }}</td>
                        
                        @foreach($warehouses as $warehouse)
                            @php
                                $qty = $item->getWarehouseQuantity($warehouse->id);
                                $colorClass = $qty > 50 ? 'bg-green-100' : 
                                             ($qty > 0 ? 'bg-yellow-100' : 'bg-red-100');
                            @endphp
                            <td class="{{ $colorClass }}">
                                <input type="number" 
                                       value="{{ $qty }}"
                                       wire:change="updateQuantity('{{ $item->type }}', {{ $item->id }}, {{ $warehouse->id }}, $event.target.value)"
                                       class="quantity-input"
                                       min="0">
                            </td>
                        @endforeach
                        
                        <td class="font-bold">{{ $item->total_stock }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><strong>Grand Total</strong></td>
                    @foreach($warehouses as $warehouse)
                        <td>
                            <strong>{{ $this->getWarehouseTotal($warehouse->id) }}</strong>
                        </td>
                    @endforeach
                    <td>
                        <strong>{{ $this->getOverallTotal() }}</strong>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<style>
.inventory-table {
    width: 100%;
    border-collapse: collapse;
}
.inventory-table th,
.inventory-table td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: center;
}
.quantity-input {
    width: 80px;
    text-align: center;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 4px;
}
.quantity-input:focus {
    outline: 2px solid #3b82f6;
}
</style>
```

### Excel Import/Export Implementation

```php
// app/Imports/InventoryImport.php
class InventoryImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // Find product/variant/addon by SKU
        $item = $this->findItemBySku($row['sku']);
        
        if (!$item) {
            return null;
        }
        
        // Update inventory for each warehouse column
        foreach ($row as $column => $quantity) {
            if ($column === 'sku') continue;
            
            // Find warehouse by code (column name)
            $warehouse = Warehouse::where('code', $column)->first();
            
            if ($warehouse && is_numeric($quantity)) {
                ProductInventory::updateOrCreate(
                    [
                        $item['type'] . '_id' => $item['id'],
                        'warehouse_id' => $warehouse->id
                    ],
                    ['quantity' => (int)$quantity]
                );
                
                // Log the import
                InventoryLog::create([...]);
            }
        }
    }
}

// app/Exports/InventoryExport.php
class InventoryExport implements FromCollection, WithHeadings
{
    protected $inventoryData;
    protected $warehouses;
    
    public function __construct($inventoryData)
    {
        $this->inventoryData = $inventoryData;
        $this->warehouses = Warehouse::where('is_active', true)
            ->orderBy('name')
            ->get();
    }
    
    public function headings(): array
    {
        $headings = ['SKU', 'Product Name'];
        
        foreach ($this->warehouses as $warehouse) {
            $headings[] = $warehouse->code;
        }
        
        $headings[] = 'Total';
        
        return $headings;
    }
    
    public function collection()
    {
        return $this->inventoryData->map(function($item) {
            $row = [
                'sku' => $item->sku,
                'name' => $item->name
            ];
            
            foreach ($this->warehouses as $warehouse) {
                $row[$warehouse->code] = $item->getWarehouseQuantity($warehouse->id);
            }
            
            $row['total'] = $item->total_stock;
            
            return $row;
        });
    }
}
```

---

## 🗂️ Module Structure

### Module Location
```
app/Modules/Inventory/
├── Models/
│   ├── Warehouse.php
│   ├── ProductInventory.php
│   └── InventoryLog.php
├── Filament/
│   ├── Resources/
│   │   ├── WarehouseResource.php
│   │   └── InventoryLogResource.php
│   └── Widgets/
│       ├── InventoryOverviewWidget.php
│       └── LowStockWidget.php
└── Services/
    ├── WarehouseFulfillmentService.php
    └── InventoryLogService.php
```

---

## 📊 Database Schema

### 1. Warehouses Table

```sql
CREATE TABLE warehouses (
    id BIGSERIAL PRIMARY KEY,
    warehouse_name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,  -- For CSV import/export
    address TEXT,
    country VARCHAR(100),
    state VARCHAR(100),
    city VARCHAR(100),
    postal_code VARCHAR(20),
    
    -- Geolocation for fulfillment optimization
    lat DECIMAL(10,8),  -- Latitude
    lng DECIMAL(11,8),  -- Longitude
    
    -- Status
    status TINYINT DEFAULT 1,  -- 1=active, 0=inactive
    is_primary BOOLEAN DEFAULT FALSE,  -- Primary/default warehouse
    
    -- Metadata
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_warehouses_status (status),
    INDEX idx_warehouses_code (code),
    INDEX idx_warehouses_geolocation (lat, lng)
);
```

**Key Features:**
- **Unique code** for external system mapping (e.g., 'WH-DUBAI-01', 'WH-NY-MAIN')
- **Geolocation** (lat/lng) for distance-based fulfillment
- **Auto-generated names** using pattern: `WH-1 Dubai`, `WH-2 Dubai`
- **Primary warehouse** flag for default allocation

### 2. Product Inventories Table

```sql
CREATE TABLE product_inventories (
    id BIGSERIAL PRIMARY KEY,
    
    -- Foreign Keys (one of these must be set)
    warehouse_id BIGINT NOT NULL REFERENCES warehouses(id) ON DELETE CASCADE,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    product_variant_id BIGINT REFERENCES product_variants(id) ON DELETE CASCADE,
    add_on_id BIGINT REFERENCES addons(id) ON DELETE CASCADE,
    
    -- Inventory Data (REFERENCE ONLY)
    quantity INTEGER DEFAULT 0,
    eta DATE,  -- Expected arrival date for out-of-stock items
    
    -- Sync metadata
    last_synced_at TIMESTAMP,
    sync_source VARCHAR(50),  -- 'external_api', 'manual', 'consignment_return'
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    -- Constraints
    CONSTRAINT check_inventory_item CHECK (
        (product_id IS NOT NULL AND product_variant_id IS NULL AND add_on_id IS NULL) OR
        (product_id IS NULL AND product_variant_id IS NOT NULL AND add_on_id IS NULL) OR
        (product_id IS NULL AND product_variant_id IS NULL AND add_on_id IS NOT NULL)
    ),
    
    -- Unique constraint: one inventory record per item per warehouse
    UNIQUE (warehouse_id, product_id),
    UNIQUE (warehouse_id, product_variant_id),
    UNIQUE (warehouse_id, add_on_id),
    
    -- Indexes
    INDEX idx_product_inventories_warehouse (warehouse_id),
    INDEX idx_product_inventories_product (product_id),
    INDEX idx_product_inventories_variant (product_variant_id),
    INDEX idx_product_inventories_addon (add_on_id),
    INDEX idx_product_inventories_quantity (quantity)
);
```

**Key Features:**
- **Polymorphic relationship** - Can track Products, Variants, or AddOns
- **Reference-only quantities** - Not the source of truth
- **ETA tracking** for out-of-stock items
- **Sync metadata** to track where data came from
- **Unique constraint** prevents duplicate inventory records

### 3. Inventory Logs Table

```sql
CREATE TABLE inventory_logs (
    id BIGSERIAL PRIMARY KEY,
    
    -- Item reference
    product_id BIGINT REFERENCES products(id) ON DELETE SET NULL,
    product_variant_id BIGINT REFERENCES product_variants(id) ON DELETE SET NULL,
    addon_id BIGINT REFERENCES addons(id) ON DELETE SET NULL,
    warehouse_id BIGINT REFERENCES warehouses(id) ON DELETE SET NULL,
    
    -- Log details
    type VARCHAR(50) NOT NULL,  -- 'consignment_return', 'sale', 'adjustment', 'transfer', 'sync'
    quantity INTEGER NOT NULL,
    previous_quantity INTEGER,
    new_quantity INTEGER,
    
    -- Reference to source transaction
    reference_type VARCHAR(50),  -- 'consignment', 'order', 'manual', 'sync'
    reference_id BIGINT,
    
    -- Audit trail
    notes TEXT,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    
    -- Indexes
    INDEX idx_inventory_logs_warehouse (warehouse_id),
    INDEX idx_inventory_logs_type (type),
    INDEX idx_inventory_logs_reference (reference_type, reference_id),
    INDEX idx_inventory_logs_created_at (created_at)
);
```

**Purpose:** Audit trail for all inventory movements
- Consignment returns
- Sales/orders
- Manual adjustments
- Transfers between warehouses
- External system syncs

---

## 🔧 Model Implementation

### Warehouse Model

```php
<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    const NAME_PREFIX = 'WH-';
    
    protected $fillable = [
        'warehouse_name',
        'code',
        'address',
        'country',
        'state',
        'city',
        'postal_code',
        'lat',
        'lng',
        'status',
        'is_primary',
        'notes',
    ];
    
    protected $casts = [
        'status' => 'integer',
        'is_primary' => 'boolean',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
    ];
    
    /**
     * CRITICAL: Haversine formula for distance calculation
     * Returns warehouses ordered by distance from given coordinates
     */
    public function scopeOrderByDistance($query, $lat, $lng)
    {
        return $query
            ->selectRaw(
                'warehouses.*,
                ( 6371 * acos( 
                    cos( radians(?) ) * cos( radians( warehouses.lat ) ) 
                    * cos( radians( warehouses.lng ) - radians(?) ) 
                    + sin( radians(?) ) * sin( radians( warehouses.lat ) ) 
                ) ) AS distance',
                [$lat, $lng, $lat]
            )
            ->orderByRaw('distance IS NULL')  // NULLs last
            ->orderBy('distance', 'ASC');
    }
    
    /**
     * Optional distance calculation
     */
    public function scopeWithDistance($query, $lat = null, $lng = null)
    {
        if (!empty($lat) && !empty($lng)) {
            return $this->scopeOrderByDistance($query, $lat, $lng);
        }
        
        return $query->selectRaw('warehouses.*, 0 as distance');
    }
    
    /**
     * Only active warehouses
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
    
    /**
     * Auto-generate warehouse name
     * Example: WH-1 Dubai, WH-2 Dubai
     */
    public static function generateName(string $state, int $increment = 1): string
    {
        $name = self::NAME_PREFIX . $increment . ' ' . $state;
        
        if (self::where('warehouse_name', $name)->exists()) {
            return self::generateName($state, $increment + 1);
        }
        
        return $name;
    }
    
    /**
     * Relationship: Inventory records
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(ProductInventory::class);
    }
    
    /**
     * Get total inventory value (reference only)
     */
    public function getTotalInventoryValueAttribute(): float
    {
        return $this->inventories()
            ->join('products', 'product_inventories.product_id', '=', 'products.id')
            ->selectRaw('SUM(product_inventories.quantity * products.price) as total')
            ->value('total') ?? 0.0;
    }
}
```

### ProductInventory Model

```php
<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Modules\Products\Models\{Product, ProductVariant};
use App\Models\Addon;

class ProductInventory extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'add_on_id',
        'quantity',
        'eta',
        'last_synced_at',
        'sync_source',
    ];
    
    protected $casts = [
        'quantity' => 'integer',
        'eta' => 'date',
        'last_synced_at' => 'datetime',
    ];
    
    /**
     * Relationship: Warehouse
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
    
    /**
     * Relationship: Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    /**
     * Relationship: Variant
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
    
    /**
     * Relationship: AddOn
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class, 'add_on_id');
    }
    
    /**
     * Scope: Filter by product and variant
     */
    public function scopeByProductAndVariant($query, $productId, $variantId = null)
    {
        $query->where('product_id', $productId);
        
        if ($variantId) {
            $query->where('product_variant_id', $variantId);
        }
        
        return $query;
    }
    
    /**
     * Scope: Exclude zero quantity
     */
    public function scopeExcludeZeroQuantity($query)
    {
        return $query->where('quantity', '>', 0);
    }
    
    /**
     * Scope: With warehouse ordered by distance
     */
    public function scopeWithWarehouseOrderedByDistance($query, $lat, $lng)
    {
        return $query->with(['warehouse' => function ($q) use ($lat, $lng) {
            $q->orderByDistance($lat, $lng);
        }]);
    }
    
    /**
     * CRITICAL: Import method - Create inventory records for all warehouses
     * Called when new product/variant is created
     */
    public static function import($productId, $variantId = null, $addonId = null)
    {
        $warehouses = Warehouse::active()->pluck('id');
        
        foreach ($warehouses as $warehouseId) {
            $exists = self::where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->where('add_on_id', $addonId)
                ->exists();
            
            if (!$exists) {
                self::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'add_on_id' => $addonId,
                    'quantity' => 0,
                    'sync_source' => 'auto_import',
                ]);
            }
        }
    }
}
```

### InventoryLog Model

```php
<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class InventoryLog extends Model
{
    const UPDATED_AT = null; // Logs don't update
    
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'addon_id',
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
    
    protected $casts = [
        'quantity' => 'integer',
        'previous_quantity' => 'integer',
        'new_quantity' => 'integer',
    ];
    
    /**
     * Relationship: Warehouse
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
    
    /**
     * Relationship: Created by user
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Log a consignment return
     */
    public static function logConsignmentReturn($item, $quantity, $consignmentId, $warehouseId = null)
    {
        // Use primary warehouse if not specified
        if (!$warehouseId) {
            $warehouseId = Warehouse::where('is_primary', true)
                ->orWhere('status', 1)
                ->value('id');
        }
        
        // Get or create inventory record
        $inventory = ProductInventory::firstOrCreate([
            'warehouse_id' => $warehouseId,
            'product_id' => $item->product_id ?? null,
            'product_variant_id' => $item->variant_id ?? null,
            'add_on_id' => $item->addon_id ?? null,
        ], [
            'quantity' => 0,
        ]);
        
        $previousQty = $inventory->quantity;
        $newQty = $previousQty + $quantity;
        
        $inventory->quantity = $newQty;
        $inventory->save();
        
        return self::create([
            'product_id' => $item->product_id ?? null,
            'product_variant_id' => $item->variant_id ?? null,
            'addon_id' => $item->addon_id ?? null,
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
    
    /**
     * Log a sale/order
     */
    public static function logSale($item, $quantity, $orderId, $warehouseId)
    {
        $inventory = ProductInventory::where('warehouse_id', $warehouseId)
            ->where(function($q) use ($item) {
                if ($item->product_id) {
                    $q->where('product_id', $item->product_id);
                }
                if ($item->variant_id) {
                    $q->where('product_variant_id', $item->variant_id);
                }
                if ($item->addon_id) {
                    $q->where('add_on_id', $item->addon_id);
                }
            })
            ->first();
        
        if (!$inventory || $inventory->quantity < $quantity) {
            throw new \Exception('Insufficient inventory');
        }
        
        $previousQty = $inventory->quantity;
        $inventory->quantity -= $quantity;
        $inventory->save();
        
        return self::create([
            'product_id' => $item->product_id ?? null,
            'product_variant_id' => $item->variant_id ?? null,
            'addon_id' => $item->addon_id ?? null,
            'warehouse_id' => $warehouseId,
            'type' => 'sale',
            'quantity' => -$quantity,
            'previous_quantity' => $previousQty,
            'new_quantity' => $inventory->quantity,
            'reference_type' => 'order',
            'reference_id' => $orderId,
            'notes' => "Sold via order #{$orderId}",
            'created_by' => auth()->id(),
        ]);
    }
}
```

---

## 🎯 Implementation Phases

### Phase 1: Database & Models (Day 1-2)
- [ ] Create migrations
  - [ ] `create_warehouses_table`
  - [ ] `create_product_inventories_table`
  - [ ] `create_inventory_logs_table`
- [ ] Create models
  - [ ] `Warehouse.php` with geolocation scopes
  - [ ] `ProductInventory.php` with relationships
  - [ ] `InventoryLog.php` for audit trail
- [ ] Add relationships to existing models
  - [ ] `Product::inventory()`
  - [ ] `ProductVariant::inventory()`
  - [ ] `Addon::inventory()`
- [ ] Run migrations and test

### Phase 2: Filament Resources (Day 3-4)
- [ ] Create `WarehouseResource`
  - [ ] List page with geolocation display
  - [ ] Create/Edit forms
  - [ ] Code validation (unique)
  - [ ] Primary warehouse toggle
- [ ] Create `InventoryLogResource`
  - [ ] Read-only list view
  - [ ] Filter by type, warehouse, date
  - [ ] Export capability
- [ ] Add navigation items to Filament panel

### Phase 3: Services & Business Logic (Day 5-6)
- [ ] Create `WarehouseFulfillmentService`
  - [ ] `findNearestWarehouse($lat, $lng, $productId)`
  - [ ] `allocateInventory($orderId)`
  - [ ] `transferInventory($from, $to, $item, $qty)`
- [ ] Create `InventoryLogService`
  - [ ] Wrapper methods for all log types
  - [ ] Reporting methods
- [ ] Integration with Products module
  - [ ] Auto-create inventory on product creation
  - [ ] Display inventory in product forms

### Phase 4: Filament Widgets & Dashboard (Day 7)
- [ ] Create `InventoryOverviewWidget`
  - [ ] Total inventory value
  - [ ] Items count
  - [ ] Warehouses count
- [ ] Create `LowStockWidget`
  - [ ] Products with quantity < threshold
  - [ ] Actions to adjust
- [ ] Create `WarehouseMapWidget`
  - [ ] Visual map of warehouses (optional)

### Phase 5: Testing & Seeding (Day 8)
- [ ] Create test data seeder
  - [ ] 3-5 sample warehouses with geolocation
  - [ ] Inventory for existing products
  - [ ] Sample inventory logs
- [ ] Update `seed_all_test_data.php` to include warehouses
- [ ] Test geolocation queries
- [ ] Test inventory allocation logic

---

## 🔗 Integration Points

### With Products Module
```php
// In Product model
public function inventory()
{
    return $this->hasMany(ProductInventory::class);
}

// When product created
ProductInventory::import($product->id);
```

### With AddOns Module
```php
// In Addon model
public function inventory()
{
    return $this->hasMany(ProductInventory::class, 'add_on_id');
}

// Display in addon forms
$totalStock = $addon->inventory()->sum('quantity');
```

### With Orders Module (Future)
```php
// When order placed
$warehouseFulfillmentService = new WarehouseFulfillmentService();
$warehouse = $warehouseFulfillmentService->findNearestWarehouse(
    $order->customer->lat,
    $order->customer->lng,
    $orderItem->product_id
);

// Log the sale
InventoryLog::logSale($orderItem, $quantity, $order->id, $warehouse->id);
```

---

## 📝 Notes & Considerations

### From Architecture Research

1. **Reference-Only Approach**
   - This is NOT a full inventory system
   - External system is source of truth
   - Only sync on-demand for orders
   - Manual adjustments logged but don't trigger external sync

2. **Geolocation Strategy**
   - Use Haversine formula (6371 km Earth radius)
   - Calculate distance from customer address
   - Allocate from nearest warehouse with stock
   - Fallback to primary warehouse if no location data

3. **Consignment Integration**
   - When items returned, log in InventoryLog
   - Add back to warehouse inventory
   - Optionally notify external system via API

4. **Performance Optimization**
   - Index on warehouse_id, product_id, variant_id
   - Cache warehouse distances per customer
   - Eager load relationships in queries

5. **Future Enhancements**
   - External API sync service
   - Low stock alerts
   - Automatic reorder point calculations
   - Transfer approval workflow
   - Warehouse performance analytics

---

## ✅ Success Criteria

- [ ] All migrations run successfully
- [ ] Models with proper relationships
- [ ] Filament resources accessible at `/admin/warehouses` and `/admin/inventory-logs`
- [ ] Geolocation queries return correct nearest warehouse
- [ ] Inventory auto-created when product added
- [ ] Test data seeder works with warehouses
- [ ] Documentation updated
- [ ] Git commits for each phase

---

## � **IMPLEMENTATION ROADMAP**

### Phase 1: Database Layer (2-3 hours) ⏱️
**Tasks:**
- [ ] Create `2025_10_24_100000_create_warehouses_table.php` migration
- [ ] Create `2025_10_24_100001_create_product_inventories_table.php` migration  
- [ ] Create `2025_10_24_100002_create_inventory_logs_table.php` migration
- [ ] Run migrations: `php artisan migrate`
- [ ] Verify schema in database

**Success Criteria:**
✅ All tables created with correct columns and indexes  
✅ Foreign keys properly set up  
✅ Unique constraints working  

---

### Phase 2: Models & Relationships (2-3 hours) ⏱️
**Tasks:**
- [ ] Create `app/Modules/Inventory/Models/Warehouse.php`
  - Fillable fields
  - Geolocation scopes (orderByDistance, withDistance)
  - Active scope
  - generateName() static method
  - inventories() relationship
- [ ] Create `app/Modules/Inventory/Models/ProductInventory.php`
  - Fillable fields
  - Polymorphic relationships (product, productVariant, addon)
  - warehouse() relationship
  - Scopes (byProduct, byWarehouse, excludeZeroQuantity)
- [ ] Create `app/Modules/Inventory/Models/InventoryLog.php`
  - Fillable fields
  - Relationships
  - logAdjustment(), logTransfer(), logSale() static methods
- [ ] Add inventory relationships to existing models:
  - `Product::inventory()`
  - `ProductVariant::inventory()`  
  - `Addon::inventory()`

**Success Criteria:**
✅ All models created with proper namespaces  
✅ Relationships working in tinker  
✅ Scopes return correct results  

---

### Phase 3: Warehouse Filament Resource (2-3 hours) ⏱️
**Tasks:**
- [ ] Create `app/Filament/Resources/WarehouseResource.php`
- [ ] Define form fields:
  - Warehouse Name (auto-generated hint)
  - Code (unique validation)
  - Address, City, State, Country, Postal Code
  - Latitude, Longitude (with map picker)
  - Status toggle
  - Is Primary toggle
  - Notes textarea
- [ ] Define table columns:
  - Name with code badge
  - Location (city, state, country)
  - Stock items count
  - Status badge (active/inactive)
  - Actions (view, edit, delete)
- [ ] Add filters:
  - Active/Inactive
  - Country select
  - Has Stock / Empty
- [ ] Create pages:
  - `ListWarehouses.php`
  - `CreateWarehouse.php`
  - `EditWarehouse.php`
  - `ViewWarehouse.php` (with inventory summary)
- [ ] Add warehouse stats widget

**Success Criteria:**
✅ Can create warehouse through Filament  
✅ Can edit warehouse details  
✅ Can toggle active/inactive status  
✅ Table shows correct data with filters  
✅ Accessible at `/admin/warehouses`  

---

### Phase 4: Inventory Grid Component (4-5 hours) ⏱️ **CORE FEATURE**
**Tasks:**
- [ ] Create `app/Livewire/InventoryGrid.php` component
- [ ] Create `resources/views/livewire/inventory-grid.blade.php` template
- [ ] Implement grid layout:
  - Dynamic warehouse columns
  - Product/Variant/Addon rows
  - Editable quantity inputs
  - Total column
  - Grand totals row
- [ ] Add inline editing:
  - Wire:change event handlers
  - Validation (min 0, integer)
  - Auto-save with feedback
  - Loading states
- [ ] Add color coding:
  - Green (> 50)
  - Yellow (1-50)
  - Red (0)
- [ ] Implement filters:
  - Product type (All/Products/Variants/AddOns)
  - Stock status (All/In Stock/Low Stock/Out of Stock)
  - Brand filter
  - Category filter (for AddOns)
  - Search by SKU/name
- [ ] Add bulk operations toolbar:
  - Export to Excel
  - Import from Excel
  - Bulk update selected
  - Transfer to warehouse

**Success Criteria:**
✅ Grid displays correctly with all warehouses  
✅ Can edit quantities inline  
✅ Filters work correctly  
✅ Total calculations accurate  
✅ Color coding applied properly  

---

### Phase 5: Excel Import/Export (2-3 hours) ⏱️
**Tasks:**
- [ ] Install Laravel Excel: `composer require maatwebsite/excel`
- [ ] Create `app/Imports/InventoryImport.php`
  - Map Excel columns to warehouses by code
  - Validate SKUs exist
  - Validate quantities are numeric
  - Update or create inventory records
  - Log all changes
- [ ] Create `app/Exports/InventoryExport.php`
  - Export current grid view
  - Include warehouse columns
  - Include totals
- [ ] Add file upload handling in InventoryGrid component
- [ ] Add download button for export
- [ ] Create Excel template for users

**Success Criteria:**
✅ Can export grid to Excel  
✅ Can import Excel with validation  
✅ Errors shown for invalid data  
✅ Success message on import  
✅ Template file downloadable  

---

### Phase 6: Inventory Resource (2-3 hours) ⏱️
**Tasks:**
- [ ] Create `app/Filament/Resources/ProductInventoryResource.php`
- [ ] Define table columns:
  - Product/Variant/Addon name
  - SKU
  - Warehouse
  - Quantity
  - ETA (if out of stock)
  - Last synced
- [ ] Add filters:
  - Warehouse select
  - Item type (Product/Variant/Addon)
  - Stock status (In Stock/Low Stock/Out of Stock)
  - Has ETA
- [ ] Add actions:
  - Adjust Quantity (modal form)
  - Transfer to Warehouse (modal form)
  - View History (inventory logs)
- [ ] Add bulk actions:
  - Bulk adjust
  - Bulk delete
  - Export selected
- [ ] Create pages:
  - `ListInventories.php` (table view)
  - `ManageInventory.php` (redirects to grid)

**Success Criteria:**
✅ Can view all inventory records  
✅ Can filter by warehouse/type/status  
✅ Actions work correctly  
✅ Accessible at `/admin/inventories`  

---

### Phase 7: Services & Actions (3-4 hours) ⏱️
**Tasks:**
- [ ] Create `app/Modules/Inventory/Services/InventoryService.php`:
  - `updateInventory($warehouseId, $itemType, $itemId, $quantity)`
  - `adjustInventory($warehouseId, $itemType, $itemId, $adjustment)`
  - `getInventoryByWarehouse($warehouseId)`
  - `getTotalStock($itemType, $itemId)`
  - `checkStockAvailability($itemType, $itemId, $quantity)`
- [ ] Create `app/Modules/Inventory/Services/WarehouseFulfillmentService.php`:
  - `findNearestWarehouse($lat, $lng, $itemType, $itemId)`
  - `allocateInventory($orderId, $items)`
  - `getWarehousesWithStock($itemType, $itemId)`
- [ ] Create `app/Modules/Inventory/Services/InventoryTransferService.php`:
  - `transferStock($fromWarehouseId, $toWarehouseId, $itemType, $itemId, $quantity)`
  - `validateTransfer($fromWarehouseId, $toWarehouseId, $quantity)`
  - `logTransfer($transferData)`
- [ ] Create Filament Actions:
  - `app/Filament/Actions/Inventory/UpdateInventoryAction.php`
  - `app/Filament/Actions/Inventory/TransferInventoryAction.php`
  - `app/Filament/Actions/Inventory/AdjustInventoryAction.php`

**Success Criteria:**
✅ All services have proper methods  
✅ Actions integrated into resources  
✅ Validation working correctly  
✅ Logs created for all changes  

---

### Phase 8: Inventory Widgets (1-2 hours) ⏱️
**Tasks:**
- [ ] Create `app/Filament/Widgets/InventoryOverviewWidget.php`:
  - Total inventory value
  - Total stock items
  - Low stock count
  - Out of stock count
- [ ] Create `app/Filament/Widgets/LowStockWidget.php`:
  - Table of items with quantity < 50
  - Links to edit inventory
  - Quick adjust action
- [ ] Create `app/Filament/Widgets/WarehouseStatsWidget.php`:
  - Chart of inventory by warehouse
  - Warehouse utilization
- [ ] Add widgets to dashboard

**Success Criteria:**
✅ Widgets display on dashboard  
✅ Stats calculate correctly  
✅ Links work properly  

---

### Phase 9: Integration with Products/AddOns (2 hours) ⏱️
**Tasks:**
- [ ] Add inventory tab to `ProductResource`:
  - Show stock by warehouse
  - Quick add stock form
  - View inventory logs
- [ ] Add inventory tab to `AddonResource`:
  - Same features as ProductResource
- [ ] Update product list table:
  - Add "Total Stock" column
  - Add stock status badge
  - Filter by stock status
- [ ] Update addon list table:
  - Same updates as product list
- [ ] Auto-initialize inventory when creating product:
  - Create zero-quantity records for all active warehouses
  - Or create only for primary warehouse

**Success Criteria:**
✅ Inventory visible in product/addon forms  
✅ Can manage stock from product page  
✅ Stock status accurate  
✅ New products auto-initialize inventory  

---

### Phase 10: Seeding & Testing (2 hours) ⏱️
**Tasks:**
- [ ] Update `seed_all_test_data.php`:
  ```php
  // Seed warehouses
  $warehouses = [
      ['name' => 'Main Warehouse - US', 'code' => 'WH-US-MAIN', 'city' => 'Los Angeles', ...],
      ['name' => 'EU Distribution Center', 'code' => 'WH-EU-DIST', 'city' => 'Frankfurt', ...],
      ['name' => 'Asia Pacific Hub', 'code' => 'WH-ASIA-HUB', 'city' => 'Singapore', ...],
  ];
  
  // Seed inventory
  foreach ($products as $product) {
      foreach ($warehouses as $warehouse) {
          ProductInventory::create([...]);
      }
  }
  ```
- [ ] Test CRUD operations:
  - Create warehouse
  - Edit warehouse
  - Delete warehouse (with/without inventory)
  - View warehouse details
- [ ] Test grid functionality:
  - Load grid
  - Edit quantities
  - Filter by type/status
  - Search
  - Export Excel
  - Import Excel
- [ ] Test inventory actions:
  - Adjust quantity
  - Transfer stock
  - View logs
- [ ] Test integrations:
  - View inventory from product page
  - Create product (auto-initialize)
  - Stock status updates

**Success Criteria:**
✅ All CRUD operations work  
✅ Grid functional and responsive  
✅ Import/Export working  
✅ Integrations seamless  
✅ No errors in console  

---

### Phase 11: Documentation & Git (1 hour) ⏱️
**Tasks:**
- [ ] Update `WAREHOUSE_INVENTORY_MODULE_PLAN.md` with:
  - Implementation notes
  - Known issues
  - Future enhancements
- [ ] Create `docs/USER_GUIDE_INVENTORY.md`:
  - How to manage warehouses
  - How to use inventory grid
  - How to import/export Excel
  - How to transfer stock
- [ ] Git commits:
  ```bash
  git add database/migrations/*warehouse* database/migrations/*inventory*
  git commit -m "feat: Add warehouse and inventory migrations"
  
  git add app/Modules/Inventory/Models/*
  git commit -m "feat: Add Warehouse and Inventory models with relationships"
  
  git add app/Filament/Resources/WarehouseResource.php
  git commit -m "feat: Add Warehouse Filament resource with CRUD"
  
  git add app/Livewire/InventoryGrid.php resources/views/livewire/inventory-grid.blade.php
  git commit -m "feat: Add inventory grid component with Excel import/export"
  
  # ... more commits for each phase
  ```
- [ ] Push to repository

**Success Criteria:**
✅ Documentation complete and accurate  
✅ All phases committed separately  
✅ Code pushed to remote  

---

## 📊 **PROGRESS TRACKING**

### Overall Progress: 0% Complete

| Phase | Status | Time Estimate | Actual Time |
|-------|--------|---------------|-------------|
| 1. Database Layer | ⬜ Not Started | 2-3 hours | - |
| 2. Models & Relationships | ⬜ Not Started | 2-3 hours | - |
| 3. Warehouse Resource | ⬜ Not Started | 2-3 hours | - |
| 4. Inventory Grid | ⬜ Not Started | 4-5 hours | - |
| 5. Excel Import/Export | ⬜ Not Started | 2-3 hours | - |
| 6. Inventory Resource | ⬜ Not Started | 2-3 hours | - |
| 7. Services & Actions | ⬜ Not Started | 3-4 hours | - |
| 8. Inventory Widgets | ⬜ Not Started | 1-2 hours | - |
| 9. Integration | ⬜ Not Started | 2 hours | - |
| 10. Seeding & Testing | ⬜ Not Started | 2 hours | - |
| 11. Documentation & Git | ⬜ Not Started | 1 hour | - |
| **TOTAL** | | **23-31 hours** | - |

**Legend:**  
⬜ Not Started | 🟡 In Progress | ✅ Complete | ❌ Blocked

---

## ✅ Success Criteria

- [ ] All migrations run successfully
- [ ] Models with proper relationships
- [ ] Warehouse CRUD fully functional at `/admin/warehouses`
- [ ] Inventory Grid accessible and working
- [ ] Excel import/export working correctly
- [ ] Inventory Resource accessible at `/admin/inventories`
- [ ] Services handle all business logic correctly
- [ ] Widgets display accurate data
- [ ] Integration with Products/AddOns seamless
- [ ] Test data seeder includes warehouses and inventory
- [ ] Documentation complete
- [ ] All code committed to git

---

## �📚 Related Documentation

- [ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md](docs/architecture/ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md)
- [ARCHITECTURE_PRODUCTS_MODULE.md](docs/architecture/ARCHITECTURE_PRODUCTS_MODULE.md)
- [ARCHITECTURE_ADDONS_MODULE.md](docs/architecture/ARCHITECTURE_ADDONS_MODULE.md)
- [ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md](docs/architecture/ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md)
