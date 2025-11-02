# Consignment Module Enhancement - ACTUAL Requirements

**Date:** November 2, 2025  
**Status:** 🔴 PLANNING  
**Based On:** Tunerstop Inventory Screenshots

---

## 🎯 ACTUAL Requirements from Screenshots

### Screenshot 1: Inventory Grid with Consignment Column
**What's shown:**
- Inventory grid for Wheels
- Columns: SKU, Brand, Model, Size, Bolt Pattern, Offset, WH-1 Al Quoz, WH-2 Ras Al Khor, **Consignment Stock**, **Incoming Stock**
- Import/Export buttons
- Transfer Stock button
- **Consignment Stock column is clickable** → Shows popup with consignment details

**Requirement:**
```
✅ Inventory grid already exists
❌ Need to add "Consignment Stock" column showing total qty on consignment
❌ Need to add "Incoming Stock" column showing qty in transit/expected
❌ Make Consignment Stock clickable → Opens modal showing:
    - Which customers have items on consignment
    - Quantity per customer
    - Date consigned
❌ Make Incoming Stock clickable → Opens modal showing:
    - Expected delivery dates
    - Quantities per ETA
    - Supplier/PO information (if applicable)
```

---

### Screenshot 2: Consignment Stock Modal
**What's shown:**
- Modal title: "Consignment - RR7-H-1785-0139-BR"
- Table with columns:
  * Customer (name)
  * Available Qty (quantity on consignment with that customer)
  * Date Consigned
- Examples:
  * Fast Lane Tyre Trading - 12 qty - 22-10-2024
  * Titan Performance - 5 qty - 05-12-2024
  * AWK Garage - 1 qty - 15-03-2025

**Requirement:**
```
❌ Create modal/popup that shows per-SKU consignment breakdown
❌ Query consignment_items for that product_variant_id
❌ Group by customer
❌ Show: Customer name, Quantity (sent - sold - returned = available), Date
❌ Link customer name to customer details
```

---

### Screenshot 3: Transfer Stock Modal
**What's shown:**
- Modal title: "Transfer Stock"
- Multiple SKU rows (RR7-H-1785-0139-BR, RR7-H-1785-0139-BK, RR7-H-1785-0139-CM)
- Columns:
  * SKU (locked/readonly)
  * From (dropdown: Required)
  * Available Qty (shows available in selected "From" warehouse)
  * Transfer Qty (input with +/- buttons, default 0)
  * To (dropdown: Required)
- "From options" listed: Incoming stock, WH-1, WH-2
- "To options" listed: WH-1, WH-2
- Add Line button
- Transfer Stock button

**Requirement:**
```
✅ Transfer Stock functionality already exists
❌ Need to enhance to support BULK transfers (multiple SKUs at once)
❌ Add "Add Line" button to add more SKUs
❌ Show available qty dynamically based on "From" selection
❌ Support "Incoming Stock" as a "From" option
❌ Validate quantities before transfer
```

---

## 🔨 Implementation Tasks

### TASK 0: Add Incoming Stock Column to Inventory Grid
**Priority:** HIGH  
**File:** `resources/views/filament/pages/inventory-grid.blade.php`

**Steps:**
1. Add incoming stock column to pqGrid:
   ```javascript
   {
       title: "Incoming Stock",
       dataIndx: "incoming_stock",
       width: 150,
       align: "center",
       render: function(ui) {
           if (ui.cellData > 0) {
               return '<a href="#" class="text-success incoming-link" data-sku="' + ui.rowData.sku + '">' + ui.cellData + '</a>';
           }
           return ui.cellData || 0;
       }
   }
   ```

2. Update backend to include incoming stock:
   ```php
   // In InventoryController
   $inventory = ProductVariant::with(['inventories', 'incomingInventories'])
       ->get()
       ->map(function($variant) {
           return [
               'sku' => $variant->sku,
               // ... other fields
               'incoming_stock' => $variant->incomingInventories()
                   ->where('eta', '>', now())
                   ->orWhereNull('eta')
                   ->sum('eta_quantity')
           ];
       });
   ```

3. Add click handler for incoming stock modal:
   ```javascript
   $(document).on('click', '.incoming-link', function(e) {
       e.preventDefault();
       var sku = $(this).data('sku');
       loadIncomingStockModal(sku);
   });
   ```

4. Create incoming stock modal:
   ```html
   <div class="modal fade" id="incomingStockModal" tabindex="-1">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Incoming Stock - <span id="incomingSku"></span></h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                   <table class="table table-striped">
                       <thead>
                           <tr>
                               <th>ETA Date</th>
                               <th>Quantity</th>
                               <th>Supplier/PO</th>
                               <th>Status</th>
                           </tr>
                       </thead>
                       <tbody id="incomingStockTableBody">
                           <!-- Data loaded via AJAX -->
                       </tbody>
                   </table>
               </div>
           </div>
       </div>
   </div>
   ```

5. Create API endpoint:
   ```php
   // Route: /api/inventory/{sku}/incoming
   public function getIncomingStockBySku($sku)
   {
       $variant = ProductVariant::where('sku', $sku)->firstOrFail();
       
       $incoming = IncomingInventory::where('product_variant_id', $variant->id)
           ->where(function($q) {
               $q->where('eta', '>', now())
                 ->orWhereNull('eta');
           })
           ->get()
           ->map(function($item) {
               return [
                   'eta' => $item->eta ? $item->eta->format('d-m-Y') : 'Not Set',
                   'quantity' => $item->eta_quantity,
                   'supplier' => $item->supplier_name ?? 'N/A',
                   'po_number' => $item->po_number ?? 'N/A',
                   'status' => $item->status ?? 'Pending',
               ];
           });
           
       return response()->json($incoming);
   }
   ```

---

### TASK 1: Add Consignment Stock Column to Inventory Grid
**Priority:** HIGH  
**File:** `resources/views/filament/pages/inventory-grid.blade.php` (or wherever inventory grid is)

**Steps:**
1. Add new column to pqGrid configuration:
   ```javascript
   {
       title: "Consignment Stock",
       dataIndx: "consignment_stock",
       width: 150,
       align: "center",
       render: function(ui) {
           if (ui.cellData > 0) {
               return '<a href="#" class="text-primary consignment-link" data-sku="' + ui.rowData.sku + '">' + ui.cellData + '</a>';
           }
           return ui.cellData || 0;
       }
   }
   ```

2. Update backend API to include consignment stock count:
   ```php
   // In InventoryController or wherever inventory data is fetched
   $inventory = ProductVariant::with(['inventories', 'consignmentItems'])
       ->get()
       ->map(function($variant) {
           return [
               'sku' => $variant->sku,
               // ... other fields
               'consignment_stock' => $variant->consignmentItems()
                   ->whereHas('consignment', function($q) {
                       $q->whereIn('status', ['sent', 'delivered', 'partially_sold']);
                   })
                   ->sum(DB::raw('quantity_sent - quantity_sold - quantity_returned'))
           ];
       });
   ```

3. Add click handler to open modal:
   ```javascript
   $(document).on('click', '.consignment-link', function(e) {
       e.preventDefault();
       var sku = $(this).data('sku');
       loadConsignmentModal(sku);
   });
   ```

---

### TASK 2: Create Consignment Stock Modal
**Priority:** HIGH  
**File:** Create new file `resources/views/inventory/consignment-modal.blade.php`

**Steps:**
1. Create modal HTML:
   ```html
   <div class="modal fade" id="consignmentModal" tabindex="-1">
       <div class="modal-dialog modal-lg">
           <div class="modal-content">
               <div class="modal-header">
                   <h5 class="modal-title">Consignment - <span id="modalSku"></span></h5>
                   <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
               </div>
               <div class="modal-body">
                   <table class="table table-striped">
                       <thead>
                           <tr>
                               <th>Customer</th>
                               <th>Available Qty</th>
                               <th>Date Consigned</th>
                           </tr>
                       </thead>
                       <tbody id="consignmentTableBody">
                           <!-- Data loaded via AJAX -->
                       </tbody>
                   </table>
               </div>
           </div>
       </div>
   </div>
   ```

2. Create AJAX endpoint:
   ```php
   // Route: /api/inventory/{sku}/consignments
   public function getConsignmentsBySku($sku)
   {
       $variant = ProductVariant::where('sku', $sku)->firstOrFail();
       
       $consignments = ConsignmentItem::where('product_variant_id', $variant->id)
           ->whereHas('consignment', function($q) {
               $q->whereIn('status', ['sent', 'delivered', 'partially_sold']);
           })
           ->with('consignment.customer')
           ->get()
           ->map(function($item) {
               return [
                   'customer' => $item->consignment->customer->business_name ?? $item->consignment->customer->name,
                   'customer_id' => $item->consignment->customer_id,
                   'available_qty' => $item->quantity_sent - $item->quantity_sold - $item->quantity_returned,
                   'date_consigned' => $item->consignment->issue_date->format('d-m-Y'),
               ];
           });
           
       return response()->json($consignments);
   }
   ```

3. Create JavaScript loader:
   ```javascript
   function loadConsignmentModal(sku) {
       $('#modalSku').text(sku);
       $('#consignmentTableBody').html('<tr><td colspan="3" class="text-center">Loading...</td></tr>');
       $('#consignmentModal').modal('show');
       
       $.ajax({
           url: '/api/inventory/' + sku + '/consignments',
           method: 'GET',
           success: function(data) {
               var html = '';
               if (data.length === 0) {
                   html = '<tr><td colspan="3" class="text-center">No consignments found</td></tr>';
               } else {
                   data.forEach(function(item) {
                       html += '<tr>';
                       html += '<td><a href="/customers/' + item.customer_id + '">' + item.customer + '</a></td>';
                       html += '<td>' + item.available_qty + '</td>';
                       html += '<td>' + item.date_consigned + '</td>';
                       html += '</tr>';
                   });
               }
               $('#consignmentTableBody').html(html);
           },
           error: function() {
               $('#consignmentTableBody').html('<tr><td colspan="3" class="text-center text-danger">Error loading data</td></tr>');
           }
       });
   }
   ```

---

### TASK 3: Enhance Transfer Stock for Bulk Operations
**Priority:** MEDIUM  
**File:** Existing transfer stock modal/component

**Steps:**
1. Modify modal to support multiple SKU rows:
   ```html
   <div id="transferRows">
       <div class="transfer-row mb-3">
           <div class="row">
               <div class="col-md-2">
                   <input type="text" class="form-control" placeholder="SKU" name="sku[]">
               </div>
               <div class="col-md-2">
                   <select class="form-control from-select" name="from[]">
                       <option value="">Select Source</option>
                       <option value="incoming">Incoming Stock</option>
                       @foreach($warehouses as $wh)
                           <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                       @endforeach
                   </select>
               </div>
               <div class="col-md-2">
                   <input type="number" class="form-control available-qty" readonly>
               </div>
               <div class="col-md-2">
                   <div class="input-group">
                       <button class="btn btn-outline-secondary qty-minus" type="button">-</button>
                       <input type="number" class="form-control transfer-qty" name="qty[]" value="0" min="0">
                       <button class="btn btn-outline-secondary qty-plus" type="button">+</button>
                   </div>
               </div>
               <div class="col-md-2">
                   <select class="form-control to-select" name="to[]">
                       <option value="">Select Destination</option>
                       @foreach($warehouses as $wh)
                           <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                       @endforeach
                   </select>
               </div>
               <div class="col-md-2">
                   <button type="button" class="btn btn-danger remove-row">Remove</button>
               </div>
           </div>
       </div>
   </div>
   <button type="button" class="btn btn-secondary" id="addLineBtn">Add Line</button>
   ```

2. Add JavaScript for dynamic rows:
   ```javascript
   $('#addLineBtn').click(function() {
       var newRow = $('.transfer-row:first').clone();
       newRow.find('input').val('');
       newRow.find('select').val('');
       $('#transferRows').append(newRow);
   });
   
   $(document).on('click', '.remove-row', function() {
       if ($('.transfer-row').length > 1) {
           $(this).closest('.transfer-row').remove();
       }
   });
   
   $(document).on('change', '.from-select', function() {
       var row = $(this).closest('.transfer-row');
       var sku = row.find('input[name="sku[]"]').val();
       var from = $(this).val();
       
       if (sku && from) {
           $.ajax({
               url: '/api/inventory/available',
               data: { sku: sku, location: from },
               success: function(data) {
                   row.find('.available-qty').val(data.available);
               }
           });
       }
   });
   
   $(document).on('click', '.qty-plus', function() {
       var input = $(this).closest('.input-group').find('.transfer-qty');
       var max = parseInt($(this).closest('.transfer-row').find('.available-qty').val());
       var current = parseInt(input.val());
       if (current < max) {
           input.val(current + 1);
       }
   });
   
   $(document).on('click', '.qty-minus', function() {
       var input = $(this).closest('.input-group').find('.transfer-qty');
       var current = parseInt(input.val());
       if (current > 0) {
           input.val(current - 1);
       }
   });
   ```

3. Update backend transfer logic to handle multiple items:
   ```php
   public function bulkTransfer(Request $request)
   {
       $validated = $request->validate([
           'sku' => 'required|array',
           'sku.*' => 'required|exists:product_variants,sku',
           'from' => 'required|array',
           'to' => 'required|array',
           'qty' => 'required|array',
       ]);
       
       DB::beginTransaction();
       try {
           foreach ($validated['sku'] as $index => $sku) {
               $variant = ProductVariant::where('sku', $sku)->first();
               $from = $validated['from'][$index];
               $to = $validated['to'][$index];
               $qty = $validated['qty'][$index];
               
               // Handle "incoming" stock
               if ($from === 'incoming') {
                   // Transfer from incoming to warehouse
                   $incoming = IncomingInventory::where('product_variant_id', $variant->id)
                       ->where('quantity', '>=', $qty)
                       ->first();
                       
                   if (!$incoming) {
                       throw new \Exception("Insufficient incoming stock for SKU: $sku");
                   }
                   
                   $incoming->decrement('quantity', $qty);
                   
                   $inventory = Inventory::firstOrCreate([
                       'product_variant_id' => $variant->id,
                       'warehouse_id' => $to,
                   ]);
                   $inventory->increment('quantity', $qty);
               } else {
                   // Normal warehouse to warehouse transfer
                   $fromInventory = Inventory::where('product_variant_id', $variant->id)
                       ->where('warehouse_id', $from)
                       ->first();
                       
                   if (!$fromInventory || $fromInventory->quantity < $qty) {
                       throw new \Exception("Insufficient stock for SKU: $sku");
                   }
                   
                   $fromInventory->decrement('quantity', $qty);
                   
                   $toInventory = Inventory::firstOrCreate([
                       'product_variant_id' => $variant->id,
                       'warehouse_id' => $to,
                   ]);
                   $toInventory->increment('quantity', $qty);
               }
               
               // Log transfer
               StockTransfer::create([
                   'product_variant_id' => $variant->id,
                   'from_warehouse_id' => $from === 'incoming' ? null : $from,
                   'to_warehouse_id' => $to,
                   'quantity' => $qty,
                   'transferred_by' => auth()->id(),
               ]);
           }
           
           DB::commit();
           return response()->json(['success' => true, 'message' => 'Stock transferred successfully']);
       } catch (\Exception $e) {
           DB::rollback();
           return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
       }
   }
   ```

---

## 📝 Summary

### What needs to be built:

1. **Consignment Stock Column in Inventory Grid**
   - Add column showing total consignment quantity
   - Make it clickable
   - Calculate: SUM(quantity_sent - quantity_sold - quantity_returned) where status IN ('sent', 'delivered', 'partially_sold')

2. **Consignment Stock Modal**
   - Show breakdown per customer
   - Display: Customer name (linkable), Available Qty, Date Consigned
   - Group by customer_id
   - Link customer name to customer details page

3. **Bulk Transfer Stock Enhancement**
   - Support multiple SKU rows in one transfer
   - "Add Line" button to add more rows
   - Support "Incoming Stock" as source
   - Dynamic available qty based on "From" selection
   - +/- buttons for quantity
   - Validate before transfer
   - Transaction-safe bulk transfer

---

## 🗂️ Files to Create/Modify

### New Files:
1. `resources/views/inventory/consignment-modal.blade.php` - Modal HTML
2. `app/Http/Controllers/Api/InventoryConsignmentController.php` - API endpoint for consignment data
3. `database/migrations/YYYY_MM_DD_create_stock_transfers_table.php` - If doesn't exist

### Modify Files:
1. `resources/views/filament/pages/inventory-grid.blade.php` - Add consignment column
2. `app/Http/Controllers/InventoryController.php` - Add consignment_stock to data
3. `routes/web.php` or `routes/api.php` - Add new routes
4. Transfer stock modal/component - Enhance for bulk operations
5. `app/Http/Controllers/StockTransferController.php` - Add bulk transfer method

---

## ⏱️ Estimated Time

- Task 1 (Consignment Column): 2-3 hours
- Task 2 (Consignment Modal): 3-4 hours  
- Task 3 (Bulk Transfer): 4-5 hours

**Total: 2-3 days**

---

## 🚨 Impact on Consignment CRUD Operations

### Impact Analysis:

#### 1. **CREATE Consignment**
**Impact:** ✅ NO BREAKING CHANGES, but enhancements needed

**Issues to handle:**
- [ ] When creating consignment and selecting products, need to show **real-time consignment stock** per product
- [ ] Show warning if product already has high consignment stock (low availability)
- [ ] Warehouse selection should show available qty MINUS consignment qty
- [ ] Add validation: Cannot consign more than available (warehouse_qty - consignment_qty)

**Enhancement needed in ConsignmentForm.php:**
```php
Select::make('product_variant_id')
    ->options(...)
    ->reactive()
    ->afterStateUpdated(function ($state, callable $set, callable $get) {
        if ($state) {
            $variant = ProductVariant::find($state);
            $warehouseId = $get('warehouse_id');
            
            if ($warehouseId) {
                // Get warehouse quantity
                $warehouseQty = Inventory::where('product_variant_id', $state)
                    ->where('warehouse_id', $warehouseId)
                    ->value('quantity') ?? 0;
                
                // Get consignment quantity (items not yet sold/returned)
                $consignmentQty = ConsignmentItem::where('product_variant_id', $state)
                    ->whereHas('consignment', function($q) use ($warehouseId) {
                        $q->where('warehouse_id', $warehouseId)
                          ->whereIn('status', ['sent', 'delivered', 'partially_sold']);
                    })
                    ->sum(DB::raw('quantity_sent - quantity_sold - quantity_returned'));
                
                $availableQty = $warehouseQty - $consignmentQty;
                
                $set('available_quantity', $availableQty);
                
                // Show warning if low availability
                if ($availableQty < 5) {
                    Notification::make()
                        ->warning()
                        ->title('Low Stock Alert')
                        ->body("Only {$availableQty} units available (excluding {$consignmentQty} on consignment)")
                        ->send();
                }
            }
        }
    })
```

**Validation rule to add:**
```php
Repeater::make('items')
    ->schema([
        // ... existing fields
        TextInput::make('quantity_sent')
            ->rules([
                function($get) {
                    return function($attribute, $value, $fail) use ($get) {
                        $availableQty = $get('available_quantity');
                        if ($value > $availableQty) {
                            $fail("Quantity exceeds available stock. Only {$availableQty} units available.");
                        }
                    };
                }
            ])
    ])
```

---

#### 2. **EDIT Consignment**
**Impact:** ⚠️ MODERATE CHANGES needed

**Issues to handle:**
- [ ] If status is 'sent' or later, changing quantities affects inventory calculations
- [ ] Increasing quantity_sent should check if additional stock is available
- [ ] Decreasing quantity_sent should update available stock
- [ ] Changing warehouse should recalculate availability

**Edge cases:**
```php
// When editing quantity_sent after consignment is already sent:

// Case 1: Increasing quantity (e.g., 10 → 15)
// - Check if warehouse has 5 additional units available (excluding other consignments)
// - Deduct 5 more from warehouse inventory
// - Update consignment_item.quantity_sent

// Case 2: Decreasing quantity (e.g., 10 → 7)
// - Add 3 units back to warehouse inventory
// - Update consignment_item.quantity_sent

// Case 3: Item already partially sold (e.g., sent: 10, sold: 4)
// - Cannot reduce quantity_sent below quantity_sold
// - Must validate: new_quantity_sent >= (quantity_sold + quantity_returned)
```

**Validation to add in ConsignmentForm.php:**
```php
TextInput::make('quantity_sent')
    ->rules([
        function($get, $record) {
            return function($attribute, $value, $fail) use ($get, $record) {
                // Check minimum based on sold/returned quantities
                $quantitySold = $get('quantity_sold') ?? 0;
                $quantityReturned = $get('quantity_returned') ?? 0;
                $minimum = $quantitySold + $quantityReturned;
                
                if ($value < $minimum) {
                    $fail("Cannot set quantity below {$minimum} (already sold: {$quantitySold}, returned: {$quantityReturned})");
                }
                
                // Check availability if increasing quantity
                if ($record && $value > $record->quantity_sent) {
                    $difference = $value - $record->quantity_sent;
                    $availableQty = $this->getAvailableQuantity(
                        $get('product_variant_id'),
                        $get('../../warehouse_id')
                    );
                    
                    if ($difference > $availableQty) {
                        $fail("Cannot increase by {$difference}. Only {$availableQty} units available.");
                    }
                }
            };
        }
    ])
```

---

#### 3. **VIEW Consignment**
**Impact:** ✅ NO CHANGES needed, but display enhancement

**Enhancement suggestions:**
- [ ] Show "Available Stock" badge next to each item indicating current warehouse stock
- [ ] Show "On Consignment (Other)" count (with other customers)
- [ ] Add warning icon if warehouse stock is now 0 (all on consignment)

**Enhancement in ConsignmentInfolist.php:**
```php
RepeatableEntry::make('items')
    ->schema([
        // ... existing fields
        TextEntry::make('product_variant.sku')
            ->label('SKU')
            ->badge()
            ->color('primary')
            ->suffixAction(
                Action::make('viewStock')
                    ->icon('heroicon-m-information-circle')
                    ->tooltip('View current stock levels')
                    ->modalContent(function($record) {
                        $warehouseQty = $record->productVariant->inventories()
                            ->where('warehouse_id', $record->consignment->warehouse_id)
                            ->value('quantity') ?? 0;
                            
                        $consignmentQty = ConsignmentItem::where('product_variant_id', $record->product_variant_id)
                            ->where('consignment_id', '!=', $record->consignment_id)
                            ->whereHas('consignment', function($q) use ($record) {
                                $q->where('warehouse_id', $record->consignment->warehouse_id)
                                  ->whereIn('status', ['sent', 'delivered', 'partially_sold']);
                            })
                            ->sum(DB::raw('quantity_sent - quantity_sold - quantity_returned'));
                            
                        return view('consignments.stock-info', [
                            'warehouse_qty' => $warehouseQty,
                            'consignment_qty_others' => $consignmentQty,
                            'available_qty' => $warehouseQty - $consignmentQty,
                        ]);
                    })
            ),
    ])
```

---

#### 4. **DELETE Consignment**
**Impact:** ⚠️ CRITICAL - Must update inventory

**Issues to handle:**
- [ ] If status is 'draft', just delete (no inventory impact)
- [ ] If status is 'sent'/'delivered'/'partially_sold', must return quantity to warehouse
- [ ] Cannot delete if status is 'invoiced_in_full' (data integrity)
- [ ] Must handle partially sold items correctly

**Logic to implement:**
```php
// In Consignment model or ConsignmentResource

public function delete()
{
    // Check if can be deleted
    if (in_array($this->status, ['invoiced_in_full', 'cancelled'])) {
        throw new \Exception("Cannot delete consignment with status: {$this->status}");
    }
    
    // If status is sent/delivered/partially_sold, return items to inventory
    if (in_array($this->status, ['sent', 'delivered', 'partially_sold'])) {
        foreach ($this->items as $item) {
            // Calculate quantity to return (sent - sold - returned)
            $quantityToReturn = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
            
            if ($quantityToReturn > 0) {
                // Add back to warehouse inventory
                $inventory = Inventory::firstOrCreate([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $this->warehouse_id,
                ]);
                
                $inventory->increment('quantity', $quantityToReturn);
                
                // Log the inventory adjustment
                InventoryLog::create([
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $this->warehouse_id,
                    'type' => 'consignment_deleted',
                    'quantity' => $quantityToReturn,
                    'reference_id' => $this->id,
                    'reference_type' => 'consignment',
                    'notes' => "Returned from deleted consignment #{$this->consignment_number}",
                    'user_id' => auth()->id(),
                ]);
            }
        }
    }
    
    // Now delete the consignment
    return parent::delete();
}
```

**Add to ConsignmentResource DeleteAction:**
```php
use Filament\Tables\Actions\DeleteAction;

DeleteAction::make()
    ->before(function ($record) {
        // Validation
        if (in_array($record->status, ['invoiced_in_full'])) {
            Notification::make()
                ->danger()
                ->title('Cannot Delete')
                ->body('Cannot delete consignment that has been invoiced.')
                ->send();
                
            return false;
        }
    })
    ->after(function ($record) {
        // Inventory is already handled in model's delete() method
        
        Notification::make()
            ->success()
            ->title('Consignment Deleted')
            ->body('Items have been returned to warehouse inventory.')
            ->send();
    })
```

---

#### 5. **Status Changes (Mark as Sent, Record Sale, Record Return)**
**Impact:** ✅ Already handled, but document the flow

**Existing flow (document for clarity):**

**Mark as Sent:**
```
1. Changes status from 'draft' to 'sent'
2. Deducts quantity_sent from warehouse inventory
3. Updates consignment_items.quantity_sent
4. Locks down quantities (cannot easily change after this)
```

**Record Sale:**
```
1. Increases quantity_sold for specific item
2. Does NOT change warehouse inventory (already deducted on "sent")
3. Updates consignment status if all items sold → 'invoiced_in_full'
4. Quantity validation: quantity_sold <= (quantity_sent - quantity_returned)
```

**Record Return:**
```
1. Increases quantity_returned for specific item
2. ADDS returned quantity back to warehouse inventory
3. Updates consignment status if all items returned → 'returned'
4. Quantity validation: quantity_returned <= (quantity_sent - quantity_sold)
```

**No changes needed, but add more validation:**
```php
// In Record Sale action
'quantity_sold' => [
    'required',
    'numeric',
    'min:1',
    function($get) {
        return function($attribute, $value, $fail) use ($get) {
            $item = ConsignmentItem::find($get('item_id'));
            $maxSellable = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
            
            if ($value > $maxSellable) {
                $fail("Cannot sell more than available. Maximum: {$maxSellable}");
            }
        };
    }
]

// In Record Return action
'quantity_returned' => [
    'required',
    'numeric',
    'min:1',
    function($get) {
        return function($attribute, $value, $fail) use ($get) {
            $item = ConsignmentItem::find($get('item_id'));
            $maxReturnable = $item->quantity_sent - $item->quantity_sold - $item->quantity_returned;
            
            if ($value > $maxReturnable) {
                $fail("Cannot return more than available. Maximum: {$maxReturnable}");
            }
        };
    }
]
```

---

## 📋 Edge Cases to Handle

### Edge Case 1: Transfer Stock with Consignment Items
**Scenario:** User tries to transfer stock from WH-1 to WH-2, but 10 units are on consignment

**Solution:**
```php
// In StockTransferController
public function bulkTransfer(Request $request)
{
    // ... validation
    
    foreach ($validated['sku'] as $index => $sku) {
        $variant = ProductVariant::where('sku', $sku)->first();
        $fromWarehouse = $validated['from'][$index];
        $transferQty = $validated['qty'][$index];
        
        if ($fromWarehouse !== 'incoming') {
            // Check warehouse quantity
            $warehouseQty = Inventory::where('product_variant_id', $variant->id)
                ->where('warehouse_id', $fromWarehouse)
                ->value('quantity') ?? 0;
            
            // Check consignment quantity
            $consignmentQty = ConsignmentItem::where('product_variant_id', $variant->id)
                ->whereHas('consignment', function($q) use ($fromWarehouse) {
                    $q->where('warehouse_id', $fromWarehouse)
                      ->whereIn('status', ['sent', 'delivered', 'partially_sold']);
                })
                ->sum(DB::raw('quantity_sent - quantity_sold - quantity_returned'));
            
            $availableQty = $warehouseQty - $consignmentQty;
            
            if ($transferQty > $availableQty) {
                throw new \Exception(
                    "Cannot transfer {$transferQty} units of {$sku}. " .
                    "Warehouse has {$warehouseQty} but {$consignmentQty} are on consignment. " .
                    "Only {$availableQty} available for transfer."
                );
            }
        }
        
        // ... continue with transfer
    }
}
```

---

### Edge Case 2: Bulk Import Inventory with Consigned Items
**Scenario:** User bulk imports inventory, quantities might conflict with consignment data

**Solution:**
```php
// In InventoryController bulkImport
public function bulkImport(Request $request)
{
    // ... parse CSV
    
    foreach ($rows as $row) {
        $variant = ProductVariant::where('sku', $row['sku'])->first();
        $warehouseId = Warehouse::where('code', $row['warehouse_code'])->value('id');
        $newQty = $row['quantity'];
        
        // Check consignment quantity
        $consignmentQty = ConsignmentItem::where('product_variant_id', $variant->id)
            ->whereHas('consignment', function($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                  ->whereIn('status', ['sent', 'delivered', 'partially_sold']);
            })
            ->sum(DB::raw('quantity_sent - quantity_sold - quantity_returned'));
        
        if ($newQty < $consignmentQty) {
            Log::warning("Inventory import warning: SKU {$row['sku']} new quantity ({$newQty}) is less than consignment quantity ({$consignmentQty})");
            
            // Option 1: Skip this row
            continue;
            
            // Option 2: Set to minimum (consignment qty)
            // $newQty = $consignmentQty;
            
            // Option 3: Add to errors and show user
            $errors[] = "SKU {$row['sku']}: Cannot set quantity to {$newQty} (below consignment qty: {$consignmentQty})";
        }
        
        // ... continue with import
    }
}
```

---

### Edge Case 3: Product Deleted with Active Consignments
**Scenario:** Admin tries to delete product that has active consignments

**Solution:**
```php
// In ProductVariant model or ProductResource
public function delete()
{
    // Check for active consignments
    $activeConsignmentQty = $this->consignmentItems()
        ->whereHas('consignment', function($q) {
            $q->whereIn('status', ['sent', 'delivered', 'partially_sold']);
        })
        ->sum(DB::raw('quantity_sent - quantity_sold - quantity_returned'));
    
    if ($activeConsignmentQty > 0) {
        throw new \Exception(
            "Cannot delete product {$this->sku}. " .
            "{$activeConsignmentQty} units are currently on consignment. " .
            "Please complete or cancel all active consignments first."
        );
    }
    
    return parent::delete();
}
```

---

### Edge Case 4: Concurrent Updates
**Scenario:** Two users create consignments for same product simultaneously

**Solution:**
```php
// Use database transactions and row locking

DB::beginTransaction();
try {
    // Lock the inventory row for update
    $inventory = Inventory::where('product_variant_id', $variantId)
        ->where('warehouse_id', $warehouseId)
        ->lockForUpdate()
        ->first();
    
    // Get current consignment qty (also locked)
    $consignmentQty = ConsignmentItem::where('product_variant_id', $variantId)
        ->whereHas('consignment', function($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId)
              ->whereIn('status', ['sent', 'delivered', 'partially_sold']);
        })
        ->lockForUpdate()
        ->sum(DB::raw('quantity_sent - quantity_sold - quantity_returned'));
    
    $availableQty = $inventory->quantity - $consignmentQty;
    
    if ($requestedQty > $availableQty) {
        throw new \Exception("Insufficient stock");
    }
    
    // Create consignment
    // ...
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
    throw $e;
}
```

---

## 📝 Summary of Changes Needed

### Files to Modify for CRUD Impact:

1. **app/Filament/Resources/ConsignmentResource/Schemas/ConsignmentForm.php**
   - Add real-time availability calculation (warehouse - consignment)
   - Add validation for quantity_sent
   - Add warnings for low stock
   - Add edit mode validation (cannot reduce below sold/returned)

2. **app/Filament/Resources/ConsignmentResource/Schemas/ConsignmentInfolist.php**
   - Add stock availability badges
   - Add info icons showing current warehouse stock

3. **app/Modules/Consignments/Models/Consignment.php**
   - Override delete() method to handle inventory returns
   - Add validation in boot() method

4. **app/Filament/Resources/ConsignmentResource.php**
   - Update DeleteAction with proper validation
   - Add inventory return notification

5. **app/Http/Controllers/StockTransferController.php**
   - Add consignment quantity check before transfer
   - Show error if trying to transfer consigned items

6. **app/Http/Controllers/InventoryController.php**
   - Add consignment quantity check in bulk import
   - Validate minimum quantities

7. **app/Modules/Products/Models/ProductVariant.php**
   - Add validation in delete() to prevent deletion with active consignments

---

**Total: 2-3 days (with all edge cases handled)**

---


