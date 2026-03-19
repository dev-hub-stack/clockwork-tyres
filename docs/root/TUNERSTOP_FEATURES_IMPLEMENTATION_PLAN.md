# Tunerstop Inventory & Products Features - Implementation Plan

**Date:** November 2, 2025  
**Based On:** Tunerstop Screenshots Analysis  
**Project:** Reporting CRM Phase 4  

---

## 📸 Screenshot Analysis Summary

### 1. **Inventory Grid (Screenshot 1)**
- Filter inputs in column headers ✅ DONE
- Columns: SKU, Brand, Model, Size, Bolt Pattern, Offset, WH-1 Al Quoz, WH-2 Ras Al Khor, Consignment Stock, Incoming Stock
- Shows warehouse quantities (editable)
- Consignment Stock column (CLICKABLE - shows modal)
- Incoming Stock column
- Import & Export buttons ✅ DONE
- Transfer Stock button (top right)

### 2. **Consignment Modal (Screenshot 2)**
- Shows when clicking Consignment Stock cell
- Displays: Customer, Available Qty, Date Consigned
- Lists all consignments for that SKU
- Example: 3 customers with consignment data

### 3. **Transfer Stock Modal (Screenshot 3)**
- Multi-SKU selection (can add multiple lines)
- From dropdown (Incoming stock, WH-1, WH-2)
- To dropdown (WH-1, WH-2)
- Transfer Qty with +/- buttons
- "Add Line" and "Transfer Stock" buttons

### 4. **Products Grid (Screenshot 4)**
- Similar to Clockwork's Excel interface
- Columns: SKU, Brand, Model, Size, Bolt Pattern, Offset (repeated), WH-1 Al Quoz, WH-2 Ras Al Khor, Consignment Stock, Incoming Stock
- Note at bottom: "same headings as tunerstopwholesale except remove - lipsize, US Retail price, sale price, clearance corner and images"
- New header: "track inventory with options Y and N"
  - Y = product is tracked in inventory section
  - N = product not tracked in inventory section

---

## ✅ What We Already Have

### Inventory Grid Features
- [x] PQGrid PRO with filter headers
- [x] Dynamic warehouse columns (qty, ETA, ETA qty)
- [x] Excel-like editing
- [x] Import/Export functionality
- [x] Save changes with AJAX
- [x] Full-width layout with horizontal scroll
- [x] Processing loader for imports
- [x] Sample CSV file

### Missing from Tunerstop
- [ ] Consignment Stock column (clickable)
- [ ] Incoming Stock column
- [ ] Transfer Stock functionality
- [ ] Consignment modal popup
- [ ] Multi-line transfer interface

---

## 🎯 Implementation TODO List

### PHASE 1: Database & Models (ALREADY EXISTS)
Based on `CONSIGNMENT_WARRANTY_TODO.md`, we already have:
- [x] Consignments table migration
- [x] Consignment items table migration  
- [x] Consignment histories table migration
- [ ] Verify models are created (Consignment, ConsignmentItem, ConsignmentHistory)

---

### PHASE 2: Add Missing Inventory Columns

#### TODO 2.1: Add Consignment Stock Column to Inventory Grid
**File:** `resources/views/filament/pages/inventory-grid.blade.php`

**Current Columns:**
```javascript
colModel = [
    { title: "SKU", ... },
    { title: "Product Full Name", ... },
    { title: "Size", ... },
    { title: "Bolt Pattern", ... },
    { title: "Offset", ... },
    // Dynamic warehouse columns...
];
```

**Add After Warehouse Columns:**
```javascript
{
    title: "Consignment Stock",
    dataIndx: "consignment_stock",
    width: 150,
    dataType: 'integer',
    align: "center",
    editable: false,  // Read-only, clickable
    cls: 'consignment-stock-cell',
    filter: { crules: [{ condition: 'equal' }] },
    render: function(ui) {
        if (ui.cellData && ui.cellData > 0) {
            return '<a href="javascript:void(0)" class="consignment-link" data-sku="' + ui.rowData.sku + '">' + ui.cellData + '</a>';
        }
        return ui.cellData || 0;
    }
}
```

**CSS:**
```css
.consignment-link {
    color: #0d6efd;
    text-decoration: underline;
    cursor: pointer;
    font-weight: 600;
}

.consignment-link:hover {
    color: #0a58ca;
}

.consignment-stock-cell {
    background-color: #fff3cd !important;
}
```

**Backend Changes:**
- Add `consignment_stock` to `InventoryGrid.php` data loading
- Query: Sum of `consignment_items.quantity` WHERE `status = 'active'` GROUP BY `product_variant_id`

---

#### TODO 2.2: Add Incoming Stock Column
**Add After Consignment Stock:**
```javascript
{
    title: "Incoming Stock",
    dataIndx: "incoming_stock",
    width: 150,
    dataType: 'integer',
    align: "center",
    editable: false,
    cls: 'incoming-stock-cell',
    filter: { crules: [{ condition: 'equal' }] },
    render: function(ui) {
        return ui.cellData || 0;
    }
}
```

**CSS:**
```css
.incoming-stock-cell {
    background-color: #cfe2ff !important;
}
```

**Backend Changes:**
- Add `incoming_stock` calculation
- Sum of all `product_inventories.eta_qty` for the variant across all warehouses

---

### PHASE 3: Consignment Modal

#### TODO 3.1: Create Consignment Modal HTML
**File:** `resources/views/filament/pages/inventory-grid.blade.php`

**Add Before Closing `</x-filament-panels::page>`:**
```html
<!-- Consignment Details Modal -->
<div class="modal fade" id="consignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Consignment - <span id="consignmentSKU"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Available Qty</th>
                            <th>Date Consigned</th>
                        </tr>
                    </thead>
                    <tbody id="consignmentTableBody">
                        <!-- Dynamic rows -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
```

---

#### TODO 3.2: Add JavaScript for Consignment Modal
**Add to script section:**
```javascript
// Consignment cell click handler
$(document).on('click', '.consignment-link', function() {
    var sku = $(this).data('sku');
    loadConsignmentDetails(sku);
});

function loadConsignmentDetails(sku) {
    $('#consignmentSKU').text(sku);
    $('#consignmentModal').modal('show');
    
    // Show loading
    $('#consignmentTableBody').html('<tr><td colspan="3" class="text-center">Loading...</td></tr>');
    
    // AJAX request
    $.ajax({
        url: '/admin/inventory/consignments/' + sku,
        type: 'GET',
        success: function(data) {
            var html = '';
            if (data.consignments && data.consignments.length > 0) {
                data.consignments.forEach(function(item) {
                    html += '<tr>';
                    html += '<td>' + item.customer_name + '</td>';
                    html += '<td>' + item.quantity + '</td>';
                    html += '<td>' + item.date_consigned + '</td>';
                    html += '</tr>';
                });
            } else {
                html = '<tr><td colspan="3" class="text-center">No consignments found</td></tr>';
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

#### TODO 3.3: Create Backend Route & Controller
**File:** `routes/web.php`
```php
Route::middleware(['auth'])->prefix('admin')->group(function () {
    // Existing routes...
    
    Route::get('/inventory/consignments/{sku}', [InventoryGridController::class, 'getConsignments'])
        ->name('admin.inventory.consignments');
});
```

**Create Controller Method:**
```php
public function getConsignments($sku)
{
    $variant = ProductVariant::where('sku', $sku)->first();
    
    if (!$variant) {
        return response()->json(['consignments' => []]);
    }
    
    $consignments = ConsignmentItem::with(['consignment.customer'])
        ->where('product_variant_id', $variant->id)
        ->where('status', 'active')
        ->where('quantity', '>', 0)
        ->get()
        ->map(function($item) {
            return [
                'customer_name' => $item->consignment->customer->name ?? 'N/A',
                'quantity' => $item->quantity,
                'date_consigned' => $item->consignment->created_at->format('d-m-Y'),
            ];
        });
    
    return response()->json(['consignments' => $consignments]);
}
```

---

### PHASE 4: Transfer Stock Feature

#### TODO 4.1: Add Transfer Stock Button
**In action buttons section:**
```html
<button type="button" class="btn btn-primary" id="transfer-stock-btn">
    <i class="bi bi-arrow-left-right"></i> Transfer Stock
</button>
```

---

#### TODO 4.2: Create Transfer Stock Modal
**Add modal HTML:**
```html
<!-- Transfer Stock Modal -->
<div class="modal fade" id="transferStockModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="transferLines">
                    <!-- Dynamic transfer lines -->
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <p><strong>From options:</strong></p>
                        <ul>
                            <li>Incoming stock</li>
                            <li>WH-1</li>
                            <li>WH-2</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <p><strong>To options:</strong></p>
                        <ul>
                            <li>WH-1</li>
                            <li>WH-2</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="addTransferLine">
                    <i class="bi bi-plus"></i> Add Line
                </button>
                <button type="button" class="btn btn-primary" id="executeTransfer">
                    <i class="bi bi-arrow-left-right"></i> Transfer Stock
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
```

---

#### TODO 4.3: Transfer Stock JavaScript
```javascript
var transferLineCounter = 0;

$('#transfer-stock-btn').on('click', function() {
    transferLineCounter = 0;
    $('#transferLines').html('');
    addTransferLine();
    $('#transferStockModal').modal('show');
});

$('#addTransferLine').on('click', function() {
    addTransferLine();
});

function addTransferLine() {
    var lineHtml = `
        <div class="transfer-line row mb-3" data-line="${transferLineCounter}">
            <div class="col-md-2">
                <label>SKU</label>
                <input type="text" class="form-control transfer-sku" placeholder="Enter SKU" required>
            </div>
            <div class="col-md-2">
                <label>From</label>
                <select class="form-select transfer-from" required>
                    <option value="">Required</option>
                    <option value="incoming">Incoming stock</option>
                    ${warehouseOptions}
                </select>
            </div>
            <div class="col-md-2">
                <label>Available Qty</label>
                <input type="number" class="form-control available-qty" readonly>
            </div>
            <div class="col-md-2">
                <label>Transfer Qty</label>
                <div class="input-group">
                    <button class="btn btn-outline-secondary qty-minus" type="button">-</button>
                    <input type="number" class="form-control transfer-qty" value="0" min="0">
                    <button class="btn btn-outline-secondary qty-plus" type="button">+</button>
                </div>
            </div>
            <div class="col-md-2">
                <label>To</label>
                <select class="form-select transfer-to" required>
                    <option value="">Required</option>
                    ${warehouseOptions}
                </select>
            </div>
            <div class="col-md-1">
                <label>&nbsp;</label>
                <button class="btn btn-danger remove-line w-100" type="button">×</button>
            </div>
        </div>
    `;
    
    $('#transferLines').append(lineHtml);
    transferLineCounter++;
}

// Quantity increment/decrement
$(document).on('click', '.qty-plus', function() {
    var input = $(this).closest('.input-group').find('.transfer-qty');
    var currentVal = parseInt(input.val()) || 0;
    var maxQty = parseInt($(this).closest('.transfer-line').find('.available-qty').val()) || 0;
    if (currentVal < maxQty) {
        input.val(currentVal + 1);
    }
});

$(document).on('click', '.qty-minus', function() {
    var input = $(this).closest('.input-group').find('.transfer-qty');
    var currentVal = parseInt(input.val()) || 0;
    if (currentVal > 0) {
        input.val(currentVal - 1);
    }
});

// Remove line
$(document).on('click', '.remove-line', function() {
    $(this).closest('.transfer-line').remove();
});

// SKU lookup - get available qty
$(document).on('change', '.transfer-sku, .transfer-from', function() {
    var line = $(this).closest('.transfer-line');
    var sku = line.find('.transfer-sku').val();
    var from = line.find('.transfer-from').val();
    
    if (sku && from) {
        $.ajax({
            url: '/admin/inventory/available-qty',
            type: 'GET',
            data: { sku: sku, location: from },
            success: function(data) {
                line.find('.available-qty').val(data.quantity);
            }
        });
    }
});

// Execute transfer
$('#executeTransfer').on('click', function() {
    var transfers = [];
    var valid = true;
    
    $('.transfer-line').each(function() {
        var sku = $(this).find('.transfer-sku').val();
        var from = $(this).find('.transfer-from').val();
        var to = $(this).find('.transfer-to').val();
        var qty = parseInt($(this).find('.transfer-qty').val()) || 0;
        
        if (!sku || !from || !to || qty <= 0) {
            valid = false;
            return false;
        }
        
        transfers.push({
            sku: sku,
            from: from,
            to: to,
            quantity: qty
        });
    });
    
    if (!valid) {
        alert('Please fill all fields correctly');
        return;
    }
    
    // Show processing
    $('#processingOverlay').css('display', 'flex');
    
    $.ajax({
        url: '/admin/inventory/transfer',
        type: 'POST',
        data: { transfers: transfers },
        success: function(data) {
            alert('✅ Transfer completed successfully!');
            $('#transferStockModal').modal('hide');
            location.reload();
        },
        error: function(xhr) {
            alert('❌ Transfer failed: ' + xhr.responseJSON.message);
        },
        complete: function() {
            $('#processingOverlay').css('display', 'none');
        }
    });
});
```

---

#### TODO 4.4: Create Transfer Backend Endpoints
**Routes:**
```php
Route::post('/admin/inventory/transfer', [InventoryGridController::class, 'transferStock']);
Route::get('/admin/inventory/available-qty', [InventoryGridController::class, 'getAvailableQuantity']);
```

**Controller Methods:**
```php
public function getAvailableQuantity(Request $request)
{
    $sku = $request->sku;
    $location = $request->location;
    
    $variant = ProductVariant::where('sku', $sku)->first();
    
    if (!$variant) {
        return response()->json(['quantity' => 0]);
    }
    
    if ($location === 'incoming') {
        // Sum of ETA quantities across all warehouses
        $qty = ProductInventory::where('product_variant_id', $variant->id)
            ->sum('eta_qty');
    } else {
        // Specific warehouse
        $warehouse = Warehouse::where('code', $location)->first();
        $inventory = ProductInventory::where('product_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $qty = $inventory->quantity ?? 0;
    }
    
    return response()->json(['quantity' => $qty]);
}

public function transferStock(Request $request)
{
    $validated = $request->validate([
        'transfers' => 'required|array',
        'transfers.*.sku' => 'required|string',
        'transfers.*.from' => 'required|string',
        'transfers.*.to' => 'required|string',
        'transfers.*.quantity' => 'required|integer|min:1',
    ]);
    
    DB::beginTransaction();
    
    try {
        foreach ($validated['transfers'] as $transfer) {
            $variant = ProductVariant::where('sku', $transfer['sku'])->firstOrFail();
            
            // Deduct from source
            if ($transfer['from'] === 'incoming') {
                // Deduct from ETA quantity
                // Logic to distribute across warehouses or first warehouse
                $inventory = ProductInventory::where('product_variant_id', $variant->id)
                    ->where('eta_qty', '>', 0)
                    ->first();
                if ($inventory) {
                    $inventory->eta_qty -= $transfer['quantity'];
                    $inventory->save();
                }
            } else {
                // Deduct from warehouse
                $fromWarehouse = Warehouse::where('code', $transfer['from'])->firstOrFail();
                $fromInventory = ProductInventory::where('product_variant_id', $variant->id)
                    ->where('warehouse_id', $fromWarehouse->id)
                    ->firstOrFail();
                $fromInventory->quantity -= $transfer['quantity'];
                $fromInventory->save();
            }
            
            // Add to destination warehouse
            $toWarehouse = Warehouse::where('code', $transfer['to'])->firstOrFail();
            $toInventory = ProductInventory::firstOrCreate([
                'product_variant_id' => $variant->id,
                'warehouse_id' => $toWarehouse->id,
            ]);
            $toInventory->quantity += $transfer['quantity'];
            $toInventory->save();
            
            // Log transfer (optional)
            \Log::info("Stock transferred: {$transfer['sku']} from {$transfer['from']} to {$transfer['to']}, qty: {$transfer['quantity']}");
        }
        
        DB::commit();
        
        return response()->json(['message' => 'Transfer completed successfully']);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => $e->getMessage()], 400);
    }
}
```

---

### PHASE 5: Products Grid Enhancement

#### TODO 5.1: Add "Track Inventory" Column to Products Grid
**File:** `resources/views/products/grid.blade.php` (or equivalent)

**Add Column:**
```javascript
{
    title: "Track Inventory",
    dataIndx: "track_inventory",
    width: 150,
    dataType: 'string',
    align: "center",
    editable: true,
    editor: {
        type: 'select',
        options: ['Y', 'N']
    },
    filter: { crules: [{ condition: 'equal' }] },
    render: function(ui) {
        var value = ui.cellData || 'N';
        var color = value === 'Y' ? '#28a745' : '#6c757d';
        return '<span style="color: ' + color + '; font-weight: bold;">' + value + '</span>';
    }
}
```

**Database Migration:**
```php
Schema::table('products', function (Blueprint $table) {
    $table->enum('track_inventory', ['Y', 'N'])->default('N')->after('status');
});
```

**Purpose:**
- Y = Product appears in Inventory section for stock management
- N = Product does NOT appear in Inventory section (catalog-only item)

---

### PHASE 6: Testing & Validation

#### TODO 6.1: Test Consignment Modal
- [ ] Click consignment stock cell
- [ ] Verify modal opens with correct SKU
- [ ] Check data loads correctly
- [ ] Test with multiple consignments
- [ ] Test with no consignments

#### TODO 6.2: Test Transfer Stock
- [ ] Add single line transfer
- [ ] Add multiple lines
- [ ] Remove a line
- [ ] Test +/- quantity buttons
- [ ] Verify available qty lookup
- [ ] Test from Incoming stock
- [ ] Test between warehouses
- [ ] Verify validation
- [ ] Check database updates

#### TODO 6.3: Test Products Grid
- [ ] Add track_inventory column
- [ ] Edit Y/N values
- [ ] Save changes
- [ ] Verify inventory section filters correctly

---

## 📊 Progress Tracking

### Estimated Time
- Phase 1: 0h (Already done)
- Phase 2: 4h (Add columns + backend)
- Phase 3: 6h (Consignment modal + API)
- Phase 4: 8h (Transfer stock full feature)
- Phase 5: 2h (Products grid column)
- Phase 6: 4h (Testing)
- **Total: ~24 hours (3 days)**

### Priority Order
1. ⭐ **HIGH:** Phase 2 - Add Consignment & Incoming Stock columns
2. ⭐ **HIGH:** Phase 3 - Consignment modal (most visible feature)
3. ⭐ **MEDIUM:** Phase 4 - Transfer stock (complex but essential)
4. 🔵 **LOW:** Phase 5 - Track Inventory column (nice to have)

---

## 🔍 Key Differences from Current Implementation

| Feature | Current | Tunerstop | Action Needed |
|---------|---------|-----------|---------------|
| Warehouse Columns | ✅ qty, ETA, ETA qty | ✅ Same | None |
| Consignment Stock | ❌ Missing | ✅ Clickable cell with modal | Add Phase 3 |
| Incoming Stock | ❌ Missing | ✅ Single summary column | Add Phase 2.2 |
| Transfer Stock | ❌ Missing | ✅ Multi-line modal | Add Phase 4 |
| Track Inventory | ❌ Missing | ✅ Y/N column in Products | Add Phase 5 |
| Filter Headers | ✅ Working | ✅ Working | None |
| Import/Export | ✅ Working | ✅ Working | None |

---

## 📝 Notes
- Consignment functionality requires `Consignment` module to be fully implemented
- Transfer stock requires proper transaction logging for audit trail
- Track Inventory flag affects which products appear in inventory management
- All modals should use Bootstrap 5 (already loaded)
- Use same processing loader pattern as bulk import

---

**Next Steps:**
1. Review and approve this plan
2. Start with Phase 2 (easiest, high visibility)
3. Move to Phase 3 (consignment modal)
4. Implement Phase 4 (transfer stock - most complex)
5. Add Phase 5 if time permits

**Questions to Resolve:**
1. Are Consignment models already created? Check `app/Modules/Consignments/`
2. Should transfer stock create audit logs?
3. Do we need permissions for transfer stock feature?
4. Should incoming stock be editable or calculated only?
