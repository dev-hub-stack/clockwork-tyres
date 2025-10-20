# pqGrid Integration Guide for Products Module
**ParamQuery Grid v3.5.1 - Excel-like Data Grid**

**Created:** October 21, 2025  
**Library Location:** `public/pqgridf/`  
**License:** GPL v3  
**Documentation:** http://paramquery.com

---

## 🎯 Overview

**pqGrid** is a powerful jQuery-based data grid plugin that provides Excel-like functionality for managing large datasets. We will use it for the **Products Module** to enable:

- ✅ Excel-like editing (inline cell editing)
- ✅ Bulk operations (copy/paste from Excel)
- ✅ Drag to fill (autofill)
- ✅ Support for 100,000+ records
- ✅ Undo/Redo functionality
- ✅ Frozen columns (product name, SKU always visible)
- ✅ Filter/Sort on all columns
- ✅ Export to Excel
- ✅ Virtual scrolling for performance

---

## 📦 Library Files

### Core Files (Already Available in `public/pqgridf/`)

```
pqgridf/
├── pqgrid.min.js              # Core grid functionality
├── pqgrid.min.css             # Grid styles
├── pqgrid.ui.min.css          # UI theme
├── pqgrid-pro.min.js          # PRO version with advanced features
├── pqgrid-pro.min.css         # PRO styles
├── jszip-2.5.0.min.js         # Excel export support
├── pqselect.min.js            # Dropdown select editor
├── themes/                    # Theme files
│   ├── bootstrap/
│   ├── office/
│   └── ...
└── localize/                  # i18n support
```

### Dependencies

```html
<!-- jQuery (Required) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- pqGrid Core -->
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.min.css') }}" />
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}" />
<script src="{{ asset('pqgridf/pqgrid.min.js') }}"></script>

<!-- pqGrid PRO (for advanced features) -->
<script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}" />

<!-- Export to Excel support -->
<script src="{{ asset('pqgridf/jszip-2.5.0.min.js') }}"></script>

<!-- Dropdown editor -->
<script src="{{ asset('pqgridf/pqselect.min.js') }}"></script>
```

---

## 🏗️ Integration Architecture

### File Structure for Products Module

```
app/Modules/Products/
├── Filament/
│   └── Resources/
│       ├── ProductResource.php          # Main Filament resource
│       └── ProductResource/
│           ├── Pages/
│           │   ├── ListProducts.php     # Table view (Filament standard)
│           │   ├── CreateProduct.php    # Create form
│           │   ├── EditProduct.php      # Edit form
│           │   └── ManageProductsGrid.php # ✨ NEW: pqGrid view
│           └── RelationManagers/
│               └── VariantsRelationManager.php
├── Models/
│   ├── Product.php
│   ├── Brand.php
│   ├── ProductModel.php
│   ├── Finish.php
│   └── ProductVariant.php
├── Services/
│   ├── ProductSyncService.php
│   └── ProductGridService.php           # ✨ NEW: Grid data service
└── Http/
    └── Controllers/
        └── ProductGridController.php     # ✨ NEW: Grid API endpoints

resources/views/filament/pages/
└── manage-products-grid.blade.php        # ✨ NEW: pqGrid template
```

---

## 🎨 pqGrid Implementation for Products

### 1. Custom Filament Page for Grid View

**File:** `app/Modules/Products/Filament/Resources/ProductResource/Pages/ManageProductsGrid.php`

```php
<?php

namespace App\Modules\Products\Filament\Resources\ProductResource\Pages;

use App\Modules\Products\Filament\Resources\ProductResource;
use Filament\Resources\Pages\Page;

class ManageProductsGrid extends Page
{
    protected static string $resource = ProductResource::class;
    
    protected static string $view = 'filament.pages.manage-products-grid';
    
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    
    protected static ?string $navigationLabel = 'Products Grid';
    
    protected static ?string $title = 'Products Management Grid';
    
    protected static ?int $navigationSort = 1;
    
    public function mount(): void
    {
        // Any initial data loading
    }
}
```

### 2. Blade Template with pqGrid

**File:** `resources/views/filament/pages/manage-products-grid.blade.php`

```blade
<x-filament-panels::page>
    <div class="space-y-4">
        <!-- Toolbar -->
        <div class="flex justify-between items-center mb-4">
            <div class="flex gap-2">
                <button id="btnAdd" class="bg-primary-600 text-white px-4 py-2 rounded">
                    Add Product
                </button>
                <button id="btnSave" class="bg-success-600 text-white px-4 py-2 rounded">
                    Save Changes
                </button>
                <button id="btnExport" class="bg-gray-600 text-white px-4 py-2 rounded">
                    Export to Excel
                </button>
                <button id="btnUndo" class="bg-gray-600 text-white px-4 py-2 rounded">
                    Undo
                </button>
                <button id="btnRedo" class="bg-gray-600 text-white px-4 py-2 rounded">
                    Redo
                </button>
            </div>
            <div>
                <span id="recordCount" class="text-sm text-gray-600"></span>
            </div>
        </div>

        <!-- Grid Container -->
        <div id="productsGrid" style="margin: auto;"></div>
    </div>

    @push('scripts')
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- pqGrid -->
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}" />
    <script src="{{ asset('pqgridf/pqgrid.min.js') }}"></script>
    <script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
    <script src="{{ asset('pqgridf/jszip-2.5.0.min.js') }}"></script>
    <script src="{{ asset('pqgridf/pqselect.min.js') }}"></script>

    <script>
        $(function() {
            // Column model (defines grid structure)
            var colModel = [
                { 
                    title: "ID", 
                    dataIndx: "id", 
                    width: 80, 
                    editable: false,
                    frozen: true 
                },
                { 
                    title: "SKU", 
                    dataIndx: "sku", 
                    width: 150,
                    editable: true,
                    frozen: true,
                    validations: [
                        { type: 'minLen', value: 3, msg: "SKU must be at least 3 characters" }
                    ]
                },
                { 
                    title: "Product Name", 
                    dataIndx: "name", 
                    width: 300,
                    editable: true,
                    frozen: true,
                    validations: [
                        { type: 'required' }
                    ]
                },
                { 
                    title: "Brand", 
                    dataIndx: "brand_id", 
                    width: 150,
                    editable: true,
                    editor: {
                        type: 'select',
                        valueIndx: "brand_id",
                        labelIndx: "brand_name",
                        options: [] // Loaded dynamically
                    },
                    render: function(ui) {
                        return ui.rowData.brand_name;
                    }
                },
                { 
                    title: "Model", 
                    dataIndx: "model_id", 
                    width: 150,
                    editable: true,
                    editor: {
                        type: 'select',
                        valueIndx: "model_id",
                        labelIndx: "model_name",
                        options: [] // Loaded dynamically
                    },
                    render: function(ui) {
                        return ui.rowData.model_name;
                    }
                },
                { 
                    title: "Finish", 
                    dataIndx: "finish_id", 
                    width: 150,
                    editable: true,
                    editor: {
                        type: 'select',
                        valueIndx: "finish_id",
                        labelIndx: "finish_name",
                        options: [] // Loaded dynamically
                    },
                    render: function(ui) {
                        return ui.rowData.finish_name;
                    }
                },
                { 
                    title: "Price ($)", 
                    dataIndx: "price", 
                    width: 120,
                    editable: true,
                    dataType: "float",
                    format: "$#,###.00",
                    align: "right",
                    validations: [
                        { type: 'gte', value: 0, msg: "Price must be >= 0" }
                    ]
                },
                { 
                    title: "Stock", 
                    dataIndx: "total_quantity", 
                    width: 100,
                    editable: true,
                    dataType: "integer",
                    align: "center"
                },
                { 
                    title: "Status", 
                    dataIndx: "status", 
                    width: 100,
                    editable: true,
                    editor: {
                        type: 'select',
                        options: [
                            { value: 1, label: 'Active' },
                            { value: 0, label: 'Inactive' }
                        ]
                    },
                    render: function(ui) {
                        return ui.cellData == 1 
                            ? '<span class="badge bg-success">Active</span>' 
                            : '<span class="badge bg-danger">Inactive</span>';
                    }
                },
                { 
                    title: "Sync Status", 
                    dataIndx: "sync_status", 
                    width: 120,
                    editable: false,
                    render: function(ui) {
                        var status = ui.cellData;
                        if (status === 'synced') {
                            return '<span class="badge bg-success">Synced</span>';
                        } else if (status === 'pending') {
                            return '<span class="badge bg-warning">Pending</span>';
                        } else if (status === 'error') {
                            return '<span class="badge bg-danger">Error</span>';
                        }
                        return '';
                    }
                },
                { 
                    title: "Last Sync", 
                    dataIndx: "synced_at", 
                    width: 150,
                    editable: false,
                    dataType: "date",
                    format: "yyyy-MM-dd HH:mm"
                }
            ];

            // Grid options
            var gridOptions = {
                width: '100%',
                height: 600,
                title: "Products Management",
                
                // Data model
                dataModel: {
                    location: "remote",
                    dataType: "JSON",
                    method: "GET",
                    url: "/api/products/grid-data",
                    getData: function(response) {
                        return { data: response.data };
                    },
                    error: function(jqXHR) {
                        console.error("Grid data load error:", jqXHR);
                    }
                },
                
                // Columns
                colModel: colModel,
                
                // Features
                editable: true,
                editModel: {
                    clicksToEdit: 1,
                    saveKey: $.ui.keyCode.ENTER
                },
                
                // History (undo/redo)
                history: true,
                
                // Paging
                pageModel: {
                    type: "remote",
                    rPP: 100,
                    rPPOptions: [50, 100, 200, 500]
                },
                
                // Selection
                selectionModel: { 
                    type: 'row',
                    mode: 'block'
                },
                
                // Toolbar
                toolbar: {
                    items: [
                        {
                            type: 'button',
                            label: 'Add Row',
                            icon: 'ui-icon-plus',
                            listener: function() {
                                this.addRow({ 
                                    newRow: { 
                                        status: 1,
                                        sync_status: 'pending'
                                    } 
                                });
                            }
                        },
                        {
                            type: 'button',
                            label: 'Delete Row',
                            icon: 'ui-icon-minus',
                            listener: function() {
                                var rows = this.selection({ type: 'row', method: 'getSelection' });
                                if (rows.length > 0) {
                                    this.deleteRow({ rowIndx: rows[0].rowIndx });
                                }
                            }
                        },
                        {
                            type: 'separator'
                        },
                        {
                            type: 'button',
                            label: 'Export',
                            icon: 'ui-icon-arrowthickstop-1-s',
                            listener: function() {
                                this.exportData({
                                    type: 'xlsx',
                                    filename: 'products_' + new Date().getTime()
                                });
                            }
                        }
                    ]
                },
                
                // Virtual scrolling for performance
                virtualX: true,
                virtualY: true,
                
                // Frozen columns (ID, SKU, Name stay visible)
                freezeCols: 3,
                
                // Sorting
                sortable: true,
                
                // Filtering
                filterModel: { 
                    on: true, 
                    mode: "AND", 
                    header: true 
                },
                
                // Copy/paste Excel support
                copyModel: {
                    on: true,
                    render: true
                },
                
                // Fill handle (drag to autofill)
                fillHandle: 'all',
                
                // Track changes
                track: true,
                
                // Events
                change: function(evt, ui) {
                    console.log("Cell changed:", ui);
                    // Mark row as modified
                    if (ui.updateList && ui.updateList.length > 0) {
                        ui.updateList.forEach(function(row) {
                            var rowData = grid.getRowData({ rowIndx: row.rowIndx });
                            rowData._modified = true;
                        });
                    }
                },
                
                complete: function() {
                    updateRecordCount();
                }
            };

            // Initialize grid
            var grid = pq.grid("#productsGrid", gridOptions);

            // Load dropdown options
            loadDropdownOptions();

            // Button handlers
            $('#btnAdd').click(function() {
                grid.addRow({ 
                    newRow: { 
                        status: 1,
                        sync_status: 'pending',
                        _isNew: true
                    },
                    rowIndx: 0
                });
            });

            $('#btnSave').click(function() {
                saveChanges();
            });

            $('#btnExport').click(function() {
                grid.exportData({
                    type: 'xlsx',
                    filename: 'products_export_' + new Date().getTime()
                });
            });

            $('#btnUndo').click(function() {
                grid.History().undo();
            });

            $('#btnRedo').click(function() {
                grid.History().redo();
            });

            // Load dropdown data for editors
            function loadDropdownOptions() {
                // Load brands
                $.get('/api/brands', function(brands) {
                    var brandCol = grid.getColumn({ dataIndx: 'brand_id' });
                    brandCol.editor.options = brands.map(b => ({
                        value: b.id,
                        label: b.name
                    }));
                });

                // Load models
                $.get('/api/models', function(models) {
                    var modelCol = grid.getColumn({ dataIndx: 'model_id' });
                    modelCol.editor.options = models.map(m => ({
                        value: m.id,
                        label: m.name
                    }));
                });

                // Load finishes
                $.get('/api/finishes', function(finishes) {
                    var finishCol = grid.getColumn({ dataIndx: 'finish_id' });
                    finishCol.editor.options = finishes.map(f => ({
                        value: f.id,
                        label: f.name
                    }));
                });
            }

            // Save changes to server
            function saveChanges() {
                var changes = grid.getChanges();
                
                if (!changes || (!changes.addList.length && !changes.updateList.length && !changes.deleteList.length)) {
                    alert('No changes to save');
                    return;
                }

                // Show loading
                grid.loading({ show: true });

                $.ajax({
                    url: '/api/products/bulk-save',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({
                        add: changes.addList,
                        update: changes.updateList,
                        delete: changes.deleteList
                    }),
                    success: function(response) {
                        grid.loading({ show: false });
                        
                        // Commit changes
                        grid.commit();
                        
                        // Refresh grid
                        grid.refreshDataAndView();
                        
                        alert('Changes saved successfully!');
                    },
                    error: function(xhr) {
                        grid.loading({ show: false });
                        alert('Error saving changes: ' + xhr.responseJSON.message);
                    }
                });
            }

            // Update record count display
            function updateRecordCount() {
                var data = grid.option('dataModel.data');
                $('#recordCount').text('Total Records: ' + (data ? data.length : 0));
            }
        });
    </script>
    @endpush
</x-filament-panels::page>
```

---

## 🔌 API Controller for Grid Data

**File:** `app/Modules/Products/Http/Controllers/ProductGridController.php`

```php
<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductGridController extends Controller
{
    /**
     * Get grid data with pagination
     */
    public function getGridData(Request $request)
    {
        $query = Product::with(['brand', 'model', 'finish']);

        // Filtering
        if ($request->has('filters')) {
            $filters = json_decode($request->input('filters'), true);
            foreach ($filters as $field => $value) {
                if (!empty($value)) {
                    $query->where($field, 'LIKE', "%{$value}%");
                }
            }
        }

        // Sorting
        if ($request->has('sortIndx')) {
            $sortIndx = $request->input('sortIndx');
            $sortDir = $request->input('sortDir', 'asc');
            $query->orderBy($sortIndx, $sortDir);
        }

        // Pagination
        $page = $request->input('pq_curpage', 1);
        $perPage = $request->input('pq_rpp', 100);

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        // Format data for pqGrid
        $data = $products->map(function($product) {
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'brand_id' => $product->brand_id,
                'brand_name' => $product->brand?->name,
                'model_id' => $product->model_id,
                'model_name' => $product->model?->name,
                'finish_id' => $product->finish_id,
                'finish_name' => $product->finish?->name,
                'price' => $product->price,
                'total_quantity' => $product->total_quantity,
                'status' => $product->status,
                'sync_status' => $product->sync_status,
                'synced_at' => $product->synced_at?->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'data' => $data,
            'totalRecords' => $products->total(),
            'curPage' => $products->currentPage(),
        ]);
    }

    /**
     * Bulk save changes from grid
     */
    public function bulkSave(Request $request)
    {
        DB::beginTransaction();

        try {
            $addList = $request->input('add', []);
            $updateList = $request->input('update', []);
            $deleteList = $request->input('delete', []);

            $created = 0;
            $updated = 0;
            $deleted = 0;

            // Process additions
            foreach ($addList as $row) {
                $validator = Validator::make($row, [
                    'name' => 'required|string|max:255',
                    'sku' => 'nullable|string|max:100',
                    'brand_id' => 'required|exists:brands,id',
                    'model_id' => 'required|exists:models,id',
                    'finish_id' => 'required|exists:finishes,id',
                    'price' => 'required|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    throw new \Exception('Validation failed: ' . $validator->errors()->first());
                }

                Product::create([
                    'name' => $row['name'],
                    'sku' => $row['sku'] ?? null,
                    'brand_id' => $row['brand_id'],
                    'model_id' => $row['model_id'],
                    'finish_id' => $row['finish_id'],
                    'price' => $row['price'],
                    'total_quantity' => $row['total_quantity'] ?? 0,
                    'status' => $row['status'] ?? 1,
                    'sync_status' => 'manual',
                ]);

                $created++;
            }

            // Process updates
            foreach ($updateList as $row) {
                if (!isset($row['id'])) continue;

                $product = Product::find($row['id']);
                if (!$product) continue;

                $updateData = array_intersect_key($row, array_flip([
                    'name', 'sku', 'brand_id', 'model_id', 'finish_id', 
                    'price', 'total_quantity', 'status'
                ]));

                $product->update($updateData);
                $updated++;
            }

            // Process deletions
            foreach ($deleteList as $row) {
                if (!isset($row['id'])) continue;

                $product = Product::find($row['id']);
                if ($product) {
                    $product->delete();
                    $deleted++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Saved: {$created} created, {$updated} updated, {$deleted} deleted",
                'stats' => [
                    'created' => $created,
                    'updated' => $updated,
                    'deleted' => $deleted,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get brands for dropdown
     */
    public function getBrands()
    {
        $brands = Brand::orderBy('name')->get(['id', 'name']);
        return response()->json($brands);
    }

    /**
     * Get models for dropdown
     */
    public function getModels()
    {
        $models = ProductModel::orderBy('name')->get(['id', 'name']);
        return response()->json($models);
    }

    /**
     * Get finishes for dropdown
     */
    public function getFinishes()
    {
        $finishes = Finish::orderBy('name')->get(['id', 'name']);
        return response()->json($finishes);
    }
}
```

---

## 🛣️ Routes Configuration

**File:** `routes/api.php`

```php
use App\Modules\Products\Http\Controllers\ProductGridController;

Route::middleware(['auth:sanctum'])->prefix('products')->group(function () {
    Route::get('/grid-data', [ProductGridController::class, 'getGridData']);
    Route::post('/bulk-save', [ProductGridController::class, 'bulkSave']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/brands', [ProductGridController::class, 'getBrands']);
    Route::get('/models', [ProductGridController::class, 'getModels']);
    Route::get('/finishes', [ProductGridController::class, 'getFinishes']);
});
```

---

## ✨ Key Features Implementation

### 1. **Excel Copy/Paste**
Users can copy data from Excel and paste directly into the grid.

```javascript
copyModel: {
    on: true,
    render: true
}
```

### 2. **Autofill (Drag to Fill)**
Like Excel, drag the fill handle to copy values down.

```javascript
fillHandle: 'all'
```

### 3. **Undo/Redo**
Track all changes and allow undo/redo.

```javascript
history: true,

// Programmatic undo/redo
grid.History().undo();
grid.History().redo();
```

### 4. **Frozen Columns**
Keep ID, SKU, and Product Name visible while scrolling.

```javascript
freezeCols: 3,

// Or per column
{ 
    title: "ID", 
    dataIndx: "id", 
    frozen: true 
}
```

### 5. **Inline Validation**
Validate data before allowing edits.

```javascript
validations: [
    { type: 'required' },
    { type: 'minLen', value: 3, msg: "Must be at least 3 characters" },
    { type: 'gte', value: 0, msg: "Must be >= 0" }
]
```

### 6. **Export to Excel**
One-click export to XLSX format.

```javascript
grid.exportData({
    type: 'xlsx',
    filename: 'products_export_' + new Date().getTime()
});
```

### 7. **Virtual Scrolling**
Handle 100,000+ records smoothly.

```javascript
virtualX: true,
virtualY: true
```

---

## 🎯 Benefits for Products Module

### For Admins:
✅ **Bulk Editing** - Update hundreds of products at once  
✅ **Excel Workflow** - Copy/paste from supplier spreadsheets  
✅ **Fast Navigation** - Keyboard shortcuts like Excel  
✅ **Visual Feedback** - See changes before saving  
✅ **Error Prevention** - Inline validation  

### For Performance:
✅ **Virtual Scrolling** - Smooth with 100K+ products  
✅ **Lazy Loading** - Load data on demand  
✅ **Client-side Caching** - Fast filtering/sorting  
✅ **Batch Operations** - Save all changes at once  

### For Development:
✅ **Simple Integration** - Works with Filament  
✅ **Flexible API** - Full control over data  
✅ **Extensible** - Add custom renderers/editors  
✅ **Well Documented** - http://paramquery.com/api  

---

## 🔄 Integration with Filament

### Navigation Menu

**File:** `app/Modules/Products/Filament/Resources/ProductResource.php`

```php
public static function getPages(): array
{
    return [
        'index' => Pages\ListProducts::route('/'),              // Filament table
        'grid' => Pages\ManageProductsGrid::route('/grid'),     // pqGrid view ✨
        'create' => Pages\CreateProduct::route('/create'),
        'edit' => Pages\EditProduct::route('/{record}/edit'),
    ];
}

public static function getNavigationItems(): array
{
    return [
        NavigationItem::make('Products Table')
            ->icon('heroicon-o-table')
            ->url(static::getUrl('index'))
            ->sort(1),
        NavigationItem::make('Products Grid')
            ->icon('heroicon-o-table-cells')
            ->url(static::getUrl('grid'))
            ->sort(2)
            ->badge('Excel-like', 'success'),
    ];
}
```

---

## 📝 Next Steps

1. ✅ **Create Product Model and migrations** (brands, models, finishes, products)
2. ✅ **Set up ProductGridController** with API endpoints
3. ✅ **Create ManageProductsGrid Filament page**
4. ✅ **Test pqGrid with sample data**
5. ✅ **Add validation and error handling**
6. ✅ **Integrate with DealerPricingService**
7. ✅ **Add export/import functionality**
8. ✅ **Performance testing with 10K+ records**

---

## 🔗 Resources

- **pqGrid Official Docs:** http://paramquery.com
- **API Reference:** http://paramquery.com/api
- **Demos:** http://paramquery.com/demos
- **Tutorial:** http://paramquery.com/tutorial
- **PHP Integration:** http://paramquery.com/tutorial/php

---

**END OF GUIDE**
