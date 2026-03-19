# Warehouse Selection in Line Items - Implementation Guide

**Date:** October 25, 2025  
**Purpose:** Add per-line-item warehouse selection to Quote/Invoice creation (matching old CRM system)

---

## 📋 Overview

Based on analysis of the old CRM system (`C:\Users\Dell\Documents\Reporting`), warehouse selection should be **per line item**, not per order. This allows:
- Split-warehouse orders (items from multiple warehouses)
- Clear inventory tracking per warehouse
- Visual indication of which item comes from which warehouse

---

## 🔍 Old System Architecture

### Database Structure
```
orders
  ├── warehouse_id (optional, order-level default)
  
order_items  
  ├── product_variant_id
  ├── quantity
  ├── price
  
order_item_quantities (THIS IS THE KEY TABLE)
  ├── order_item_id
  ├── warehouse_id (PER ITEM!)
  ├── quantity
```

### Key Code from Old System

**OrderController.php (Line 1037)**:
```php
private function createOrderItemQuantity($order, $orderItem, $warehouseId, $qty, $unitPrice, $discountPrice)
{
    $order_item_quantity = new OrderItemQuantity();
    $order_item_quantity->order_id = $order->id;
    $order_item_quantity->order_item_id = $orderItem->id;
    // Handle non-stock warehouse selection - store as null in database
    $order_item_quantity->warehouse_id = ($warehouseId === 'non_stock') ? null : $warehouseId;
    $order_item_quantity->quantity = $qty;
    $order_item_quantity->unit_price = $unitPrice;
    $order_item_quantity->discount = ($unitPrice * $qty) - ($discountPrice * $qty);
    $order_item_quantity->discount_price = $discountPrice;
    $order_item_quantity->save();
}
```

**JavaScript Warehouse Selection (admin-create-order.js Line 118-200)**:
```javascript
const warehouseOptions = (inventory) => {
    let options = '<option value="" selected disabled>Required</option>';
    
    $.each(inventory, function(i, v) {
        let warehouseId = v.warehouse_id;
        let available = v.available || (parseInt(v.quantity || 0) + parseInt(v.eta_qty || 0));
        let type = v.type || 'physical';
        let warehouseName = v.warehouse_name || 'Warehouse';
        
        // Format display based on warehouse type
        switch(type) {
            case 'physical':
                displayText = `${warehouseName} - ${available} (${v.quantity || 0} in stock)`;
                break;
            case 'virtual':
                displayText = `${warehouseName} - Virtual Stock`;
                break;
            case 'external':
                displayText = `${warehouseName} - External Source`;
                break;
            case 'non_stock':
                displayText = `${warehouseName} - Unlimited`;
                break;
        }
        
        options += `<option value="${warehouseId}" data-maxqty="${available}" data-type="${type}">
            ${displayText}
        </option>`;
    });
    
    return options;
};
```

**HTML Structure (edit-add.blade.php)**:
```html
<tr>
    <td>
        <!-- Product Selection -->
        <input type="text" name="product[]" class="addProductAdson form-control" />
        <input type="hidden" name="product_id[]" />
        <input type="hidden" name="product_variant_id[]" />
    </td>
    <td>
        <!-- WAREHOUSE DROPDOWN PER LINE ITEM -->
        <select name="warehouse[]" class="productWarehouse form-control" data-uuid="${uniqueId}">
            <option value="" selected disabled>Required</option>
            <!-- Options populated via AJAX based on product inventory -->
        </select>
    </td>
    <td>
        <input type="number" name="qty[]" class="prodQty form-control" />
    </td>
    <td>
        <input type="number" name="prodPrice[]" class="prodPrice form-control" />
    </td>
</tr>
```

---

## 🎯 Implementation Plan for New System

### Phase 1: Database Check ✅

**GOOD NEWS:** The new system already has the correct structure!

```bash
# Already exists:
php artisan migrate:status

# Shows:
✓ 2025_10_24_175651_create_order_item_quantities_table.php
```

**Migration Content:**
```php
Schema::create('order_item_quantities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_item_id')->constrained('order_items')->onDelete('cascade');
    $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
    $table->integer('quantity')->default(0);
    $table->timestamps();
});
```

### Phase 2: Add Warehouse Field to Filament Repeater

**File:** `app/Filament/Resources/QuoteResource.php`

**Current Structure (Line 195-310):**
```php
Repeater::make('items')
    ->relationship('items')
    ->schema([
        Select::make('product_variant_id')
            ->label('Product')
            ->searchable()
            ->required(),
        
        TextInput::make('quantity')
            ->numeric()
            ->default(1)
            ->required(),
        
        TextInput::make('unit_price')
            ->numeric()
            ->prefix('AED')
            ->required(),
    ])
```

**ADD THIS FIELD (After product_variant_id, before quantity):**
```php
Select::make('warehouse_id')
    ->label('Warehouse')
    ->options(function ($get) {
        $variantId = $get('product_variant_id');
        
        if (!$variantId) {
            return [''=>  'Select product first'];
        }
        
        // Get inventory per warehouse for this variant
        $inventories = \App\Modules\Inventory\Models\ProductInventory::where('product_variant_id', $variantId)
            ->with('warehouse')
            ->get();
        
        $options = [];
        
        foreach ($inventories as $inv) {
            $warehouse = $inv->warehouse;
            $available = $inv->quantity + $inv->eta_qty;
            
            if ($available > 0 || $inv->type === 'non_stock') {
                $label = sprintf(
                    '%s - %s available (%d in stock%s)',
                    $warehouse->name,
                    $available,
                    $inv->quantity,
                    $inv->eta_qty > 0 ? ", {$inv->eta_qty} expected" : ''
                );
                
                $options[$warehouse->id] = $label;
            }
        }
        
        // Add non-stock option
        $options['non_stock'] = '⚡ Non-Stock (Special Order) - Unlimited';
        
        return $options;
    })
    ->reactive()
    ->afterStateUpdated(function ($state, $get, $set) {
        // When warehouse changes, validate quantity against available stock
        $variantId = $get('product_variant_id');
        $quantity = $get('quantity');
        
        if ($state && $state !== 'non_stock' && $variantId && $quantity) {
            $inventory = \App\Modules\Inventory\Models\ProductInventory::where('product_variant_id', $variantId)
                ->where('warehouse_id', $state)
                ->first();
            
            if ($inventory) {
                $available = $inventory->quantity + $inventory->eta_qty;
                
                if ($quantity > $available) {
                    // Show warning notification
                    \Filament\Notifications\Notification::make()
                        ->warning()
                        ->title('Low Stock Warning')
                        ->body("Requested {$quantity} but only {$available} available in this warehouse")
                        ->send();
                }
            }
        }
    })
    ->required()
    ->helperText('Select warehouse for this item'),
```

### Phase 3: Save Warehouse with Order Item

**File:** `app/Observers/OrderItemObserver.php`

**Update `creating` method:**
```php
public function creating(OrderItem $orderItem): void
{
    if ($orderItem->product_variant_id && !$orderItem->product_name) {
        $variant = ProductVariant::with(['product.brand', 'product.model'])->find($orderItem->product_variant_id);
        
        if ($variant && $variant->product) {
            $orderItem->product_id = $variant->product_id;
            $orderItem->product_name = $variant->product->name ?? 'Unknown Product';
            $orderItem->sku = $variant->sku;
            $orderItem->brand_name = $variant->product->brand?->name;
            $orderItem->model_name = $variant->product->model?->name;
            $orderItem->product_description = $variant->product->description;
            
            // Historical snapshots
            $orderItem->product_snapshot = json_encode($variant->product->toArray());
            $orderItem->variant_snapshot = json_encode($variant->toArray());
        }
    }
}

public function created(OrderItem $orderItem): void
{
    // Create OrderItemQuantity record with warehouse allocation
    if ($orderItem->warehouse_id) {
        \App\Modules\Orders\Models\OrderItemQuantity::create([
            'order_item_id' => $orderItem->id,
            'warehouse_id' => $orderItem->warehouse_id === 'non_stock' ? null : $orderItem->warehouse_id,
            'quantity' => $orderItem->quantity,
        ]);
    }
}
```

### Phase 4: Add warehouse_id to OrderItem Model

**File:** `app/Modules/Orders/Models/OrderItem.php`

**Add to $fillable:**
```php
protected $fillable = [
    'order_id',
    'product_id',
    'product_variant_id',
    'addon_id',
    'warehouse_id', // ADD THIS
    'sku',
    'product_name',
    'brand_name',
    'model_name',
    // ... rest
];
```

**Add to $casts:**
```php
protected $casts = [
    'quantity' => 'integer',
    'unit_price' => 'decimal:2',
    'warehouse_id' => 'integer', // ADD THIS (can be null for non-stock)
    // ... rest
];
```

**Add relationship:**
```php
public function warehouse(): BelongsTo
{
    return $this->belongsTo(\App\Modules\Inventory\Models\Warehouse::class);
}

public function itemQuantities(): HasMany
{
    return $this->hasMany(OrderItemQuantity::class);
}
```

### Phase 5: Display Warehouse in Table Column

**File:** `app/Filament/Resources/QuoteResource.php`

**Add to `table()` method:**
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            Tables\Columns\TextColumn::make('quote_number')
                ->searchable()
                ->sortable(),
            
            Tables\Columns\TextColumn::make('customer.name')
                ->label('Customer')
                ->searchable(),
            
            // ADD THIS COLUMN
            Tables\Columns\TextColumn::make('items_summary')
                ->label('Items')
                ->formatStateUsing(function ($record) {
                    $items = $record->items->map(function ($item) {
                        $warehouseName = $item->warehouse ? $item->warehouse->name : 'Non-Stock';
                        return "{$item->product_name} ({$warehouseName})";
                    })->join(', ');
                    
                    return \Illuminate\Support\Str::limit($items, 50);
                })
                ->tooltip(function ($record) {
                    return $record->items->map(function ($item) {
                        $warehouseName = $item->warehouse ? $item->warehouse->name : 'Non-Stock';
                        return "• {$item->product_name} - Qty: {$item->quantity} - Warehouse: {$warehouseName}";
                    })->join("\n");
                }),
            
            // ... rest of columns
        ]);
}
```

### Phase 6: Show Warehouse in Invoice Preview

**File:** `resources/views/templates/invoice-preview.blade.php`

**Update line items table (around line 160):**
```blade
<tbody>
    @forelse($record->items as $index => $item)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>
                <strong>{{ $item->product_name }}</strong>
                @if($item->brand_name)
                    <br><span class="brand-name">{{ $item->brand_name }}</span>
                @endif
                {{-- ADD WAREHOUSE DISPLAY --}}
                @if($item->warehouse)
                    <br><small style="color: #666; font-size: 11px;">
                        📦 Warehouse: {{ $item->warehouse->name }}
                    </small>
                @elseif($item->warehouse_id === null || $item->warehouse_id === 'non_stock')
                    <br><small style="color: #666; font-size: 11px;">
                        ⚡ Non-Stock (Special Order)
                    </small>
                @endif
            </td>
            <td class="text-center">{{ $item->sku ?? 'N/A' }}</td>
            <td class="text-center">{{ $item->quantity }}</td>
            <td class="text-right">{{ $currency }} {{ number_format($item->unit_price, 2) }}</td>
            <td class="text-right">
                {{ $currency }} {{ number_format($item->quantity * $item->unit_price - ($item->discount ?? 0), 2) }}
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="6" class="text-center" style="padding: 20px; color: #999;">
                No items added yet
            </td>
        </tr>
    @endforelse
</tbody>
```

---

## 📊 Benefits of This Implementation

### 1. **Accurate Inventory Tracking**
- Each line item knows which warehouse it's allocated from
- Multi-warehouse orders are fully supported
- Clear audit trail for inventory allocation

### 2. **Better User Experience**
- Sales rep can see available stock per warehouse when selecting products
- Warnings when requesting more than available
- Non-stock option for special orders

### 3. **Matches Old System**
- Same database structure (`order_item_quantities` table)
- Same workflow (select warehouse per line item)
- Same business logic (warehouse_id per item, not per order)

### 4. **Future-Proof**
- Supports warehouse transfers
- Enables partial fulfillment from multiple warehouses
- Clear data for reporting and analytics

---

## 🚀 Testing Checklist

- [ ] Create quote with single warehouse
- [ ] Create quote with multiple warehouses (split order)
- [ ] Create quote with non-stock item
- [ ] Verify warehouse shown in preview
- [ ] Convert quote to invoice (warehouse preserved)
- [ ] Check `order_item_quantities` table has correct data
- [ ] Test low stock warning notification
- [ ] Edit existing quote and change warehouse
- [ ] Delete quote (cascade deletes item quantities)

---

## 📝 Migration for warehouse_id in order_items

If `warehouse_id` doesn't exist in `order_items` table yet:

```bash
php artisan make:migration add_warehouse_id_to_order_items_table
```

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Add warehouse_id (nullable for non-stock items)
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('product_variant_id')
                ->constrained('warehouses')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn('warehouse_id');
        });
    }
};
```

Then run:
```bash
php artisan migrate
```

---

## ⚠️ Important Notes

1. **Order-level warehouse_id is optional** - It's a default/reference, actual allocation is per-item in `order_item_quantities`

2. **Non-stock handling** - Store as `null` in database, display as "Non-Stock" in UI

3. **Cascade deletes** - When order item is deleted, `order_item_quantities` are automatically deleted

4. **Stock validation** - Warehouse dropdown only shows warehouses with available stock (or non-stock option)

5. **Filament reactivity** - Use `->reactive()` and `->live()` to update warehouse options when product changes

---

## 📚 Reference Files from Old System

- `C:\Users\Dell\Documents\Reporting\app\Http\Controllers\OrderController.php` (Line 1037)
- `C:\Users\Dell\Documents\Reporting\public\js\admin-create-order.js` (Line 118-200)
- `C:\Users\Dell\Documents\Reporting\app\Models\OrderItemQuantity.php`
- `C:\Users\Dell\Documents\Reporting\WAREHOUSE_SELECTION_STRATEGY.md`

---

**Status:** Ready for implementation  
**Estimated Time:** 2-3 hours  
**Complexity:** Medium (Filament reactive forms + database relationships)
