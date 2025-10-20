# Products Grid Implementation - Exact Pattern from Old System

**Date:** October 21, 2025  
**Source:** `C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\products\data-grid.blade.php`  
**Pattern:** Replicate exact pqGrid implementation for new Products module  
**Library Location:** `public/pqgridf/` ✅ **COPIED**

---

## 🎯 Implementation Overview

We will replicate the **EXACT** pqGrid pattern from the old Reporting system:
- ✅ Same toolbar buttons (Export, Filter, New Product, Bulk Delete)
- ✅ Same column structure (checkbox, delete button, SKU, Brand, Model, Finish, etc.)
- ✅ Same auto-save functionality
- ✅ Same styling (custom CSS)
- ✅ Same validation patterns
- ✅ Same export functionality (Excel/CSV)

---

## 📁 File Structure

```
app/Modules/Products/
├── Filament/
│   └── Resources/
│       ├── ProductResource.php
│       └── ProductResource/
│           └── Pages/
│               ├── ListProducts.php           # Standard Filament table
│               └── ManageProductsGrid.php     # ✨ pqGrid view (new)
├── Models/
│   ├── Product.php
│   ├── Brand.php
│   ├── ProductModel.php
│   └── Finish.php
└── Http/
    └── Controllers/
        └── ProductGridController.php          # API for grid operations

resources/views/filament/pages/
└── products-grid.blade.php                    # ✨ pqGrid template (new)

public/pqgridf/                                # ✅ COPIED from old system
├── pqgrid.min.js
├── pqgrid.min.css
├── pqgrid.ui.min.css
└── ... (all library files)
```

---

## 🎨 Step 1: Create Filament Page

**File:** `app/Modules/Products/Filament/Resources/ProductResource/Pages/ManageProductsGrid.php`

```php
<?php

namespace App\Modules\Products\Filament\Resources\ProductResource\Pages;

use App\Modules\Products\Filament\Resources\ProductResource;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;
use Filament\Resources\Pages\Page;

class ManageProductsGrid extends Page
{
    protected static string $resource = ProductResource::class;
    
    protected static string $view = 'filament.pages.products-grid';
    
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    
    protected static ?string $navigationLabel = 'Products Grid';
    
    protected static ?string $title = 'Products Management Grid';
    
    protected static ?int $navigationSort = 2;

    /**
     * Get products data for pqGrid
     */
    public function getProductsData()
    {
        return Product::with(['brand', 'model', 'finish', 'variants'])
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'product_full_name' => $product->product_full_name,
                    'brand' => $product->brand?->name,
                    'brand_id' => $product->brand_id,
                    'model' => $product->model?->name,
                    'model_id' => $product->model_id,
                    'finish' => $product->finish?->name,
                    'finish_id' => $product->finish_id,
                    'construction' => $product->construction,
                    'price' => $product->price,
                    'sale_price' => $product->sale_price ?? $product->price,
                    'us_retail_price' => $product->price,
                    'total_quantity' => $product->total_quantity,
                    'images' => is_array($product->images) 
                        ? implode(', ', $product->images) 
                        : $product->images,
                    'status' => $product->status,
                    'sync_status' => $product->sync_status,
                    
                    // Variant-related fields (if variants exist)
                    'rim_width' => $product->variants->first()?->width,
                    'rim_diameter' => $product->variants->first()?->diameter,
                    'size' => $product->variants->first()?->size,
                    'bolt_pattern' => $product->variants->first()?->bolt_pattern,
                    'hub_bore' => $product->variants->first()?->center_bore,
                    'offset' => $product->variants->first()?->offset,
                    'backspacing' => $product->variants->first()?->backspacing,
                    'max_wheel_load' => null, // Add if needed
                    'weight' => null, // Add if needed
                    'lipsize' => null, // Add if needed
                ];
            })
            ->toArray();
    }

    /**
     * Mount method - pass data to view
     */
    public function mount(): void
    {
        // Data is accessed via getProductsData() in the view
    }
}
```

---

## 🎨 Step 2: Create Blade Template (EXACT REPLICA)

**File:** `resources/views/filament/pages/products-grid.blade.php`

```blade
<x-filament-panels::page>
    {{-- Grid Container --}}
    <div class="panel panel-bordered" style="margin-bottom:0px;">
        <div class="panel-body">
            <div id="grid_json"></div>
        </div>
    </div>

    @push('styles')
    {{-- pqGrid Styles --}}
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}" />

    {{-- Custom Grid Styles (Exact from old system) --}}
    <style>
        .pq-grid-col,.pq-grid-number-cell  {
            border-color: #ecf2f7 !important;
            background-color: #1f2937;
            color: #ffffff;
        }
        .pq-grid-title-row .pq-grid-number-cell,
        .pq-grid-header-search-row .pq-grid-number-cell,
        .pq-grid-title-row .ui-state-default,
        .pq-grid-row .ui-state-default{
            background-color: #1f2937 !important;
            border-color:transparent !important;
        }

        .pq-header-outer .pq-grid-title-row .ui-state-default,
        .pq-header-outer .pq-grid-row .ui-state-default{
            background-image:none !important;
        }
        .pq-striped .pq-grid-number-cell  {
            color: #000000 !important;
            text-align:center;
        }
        .pq-grid-col{
            border-color:transparent !important;
        }
        .pq-grid-col:hover{
            background-color: #1f2937;
        }
        .pq-grid-hd-search-field{   
            background-color: #000000;
            border-color: transparent !important;
            border-radius: 10px !important;
            color: #ffffff !important;
        }
        .pq-grid-row > .pq-grid-number-cell{
            background-color:#ecf2f7;
        }
        .pq-grid-center{
            height:100vh;
        }
        .pq-cont-inner>.pq-table>.pq-grid-row {
            border-bottom-color: #cfcfff;
        }
        .pq-grid-row.pq-striped {
            background: #f8f8ff;
        }
        .pq-grid-number-cell {
            color: #000000 !important;
            text-align: center !important;
        }
        .pq-grid-row.pq-striped .ui-state-default{
            border-bottom-color: #cfcfff !important;
            background: #f8f8ff !important;
        }
        .pq-grid-row .ui-state-default{
            border-bottom-color: #cfcfff !important;
        }
        .pq-grid-header-table .ui-state-default{
            border-color:transparent !important;
        }

        /* Toolbar button styling */
        .pq-toolbar-export button {
            background-color: #4f46e5 !important;
            color: white !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 6px !important;
            cursor: pointer !important;
        }
        .pq-toolbar-export button:hover {
            background-color: #4338ca !important;
        }
    </style>
    @endpush

    @push('scripts')
    {{-- jQuery (Required) --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    {{-- pqGrid Library --}}
    <script src="{{ asset('pqgridf/pqgrid.min.js') }}"></script>
    <script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
    <script src="{{ asset('pqgridf/jszip-2.5.0.min.js') }}"></script>
    <script src="{{ asset('pqgridf/pqselect.min.js') }}"></script>

    {{-- FileSaver.js for Excel export --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

    <script type="text/javascript">
        var interval;
        var grid;

        // Auto-save function (exact from old system)
        function saveChanges() {
            if (grid.saveEditCell() === false) {
                return false;
            }
            
            if (!$.active && grid.isDirty() && grid.isValidChange({ allowInvalid: true }).valid) {
                var gridChanges = grid.getChanges({ format: 'byVal' });
                
                $.ajax({
                    dataType: "json",
                    type: "POST",
                    async: true,
                    beforeSend: function (jqXHR, settings) {
                        grid.option("strLoading", "Saving..");
                        grid.showLoading();
                    },
                    url: "/api/products/grid/save",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { list: gridChanges },
                    success: function (changes) {
                        grid.history({method: 'reset'});
                        grid.commit({ type: 'add', rows: changes.addList });
                        grid.commit({ type: 'update', rows: changes.updateList });
                        grid.commit({ type: 'delete', rows: changes.deleteList });
                    },
                    complete: function (resp) {
                        grid.hideLoading();
                        grid.option("strLoading", $.paramquery.pqGrid.defaults.strLoading);
                    },
                    error: function (errors) {
                        var errorMessage = "";
                        if (errors.responseJSON && errors.responseJSON.errors) {
                            errors.responseJSON.errors.forEach(function(element, index) {
                                errorMessage += element + "\n";
                            });
                        }
                        alert("Error saving: " + errorMessage);
                        console.log(errors);
                        clearInterval(interval);
                    }
                });
            }
        }

        // Bulk delete function (exact from old system)
        function bulkDelete(ids) {
            if(ids.length <= 0){
                alert("Please select an Item to delete.");
                return false;
            }
            
            if (!confirm(`Are you sure you want to delete ${ids.length} product(s)?`)) {
                return false;
            }

            const chunkSize = 250;
            let chunks = [];
            for (let i = 0; i < ids.length; i += chunkSize) {
                chunks.push(ids.slice(i, i + chunkSize));
            }
            
            let current = 0;
            let total = chunks.length;
            
            function processNextChunk() {
                if (current >= total) {
                    grid.hideLoading();
                    grid.option("strLoading", $.paramquery.pqGrid.defaults.strLoading);
                    window.location.reload();
                    return;
                }
                
                grid.option("strLoading", `Deleting batch ${current+1} of ${total}...`);
                grid.showLoading();
                
                var gridChanges = grid.getChanges({ format: 'byVal' });
                
                $.ajax({
                    dataType: "json",
                    type: "POST",
                    url: "/api/products/grid/bulk-delete",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: { 
                        list: gridChanges, 
                        deleteIds: chunks[current] 
                    },
                    success: function (changes) {
                        grid.history({method: 'reset'});
                        grid.commit({ type: 'add', rows: changes.addList });
                        grid.commit({ type: 'update', rows: changes.updateList });
                        grid.commit({ type: 'delete', rows: changes.deleteList });
                        current++;
                        processNextChunk();
                    },
                    error: function (errors) {
                        alert('Error during bulk delete!');
                        grid.hideLoading();
                    }
                });
            }
            
            processNextChunk();
        }

        $(document).ready(function () {
            // Get products data from Livewire component
            var data = @json($this->getProductsData());

            // Column model (exact from old system)
            var colModel = [
                // Delete button column
                { 
                    title: "", 
                    editable: false,
                    skipExport: true, 
                    minWidth: 85, 
                    sortable: false, 
                    align: "center",
                    render: function (ui) {
                        return "<button type='button' class='delete_btn icon-add'>Delete</button>";
                    },
                    postRender: function (ui) {
                        var grid = this,
                            $cell = grid.getCell(ui);
                        $cell.find(".delete_btn")
                            .bind("click", function (evt) {
                                if (confirm('Delete this product?')) {
                                    grid.deleteRow({ rowIndx: ui.rowIndx });
                                    saveChanges();
                                }
                            });
                    }
                },
                
                // Checkbox column
                { 
                    dataIndx: "state",
                    align: "center",
                    title: "<label><input type='checkbox' /></label>",
                    cb: { header: true, select: true, all: true },
                    type: 'checkbox',
                    cls: 'ui-state-default', 
                    dataType: 'bool',
                    skipExport: true,
                    editor: false,
                    width: 10, 
                    sortable: false
                },
                
                // Data columns (exact from old system)
                {
                    title: "SKU", 
                    width: 160, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "sku", 
                    validations: [{type: 'nonEmpty', msg: "SKU is required."}], 
                    filter: { crules: [{ condition: 'begin' }] }  
                },
                {
                    title: "Brand", 
                    width: 130, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "brand", 
                    validations: [{type: 'nonEmpty', msg: "Brand is required."}], 
                    filter: { crules: [{ condition: 'equal' }] }
                },
                {
                    title: "Model", 
                    width: 160, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "model", 
                    validations: [{type: 'nonEmpty', msg: "Model is required."}], 
                    filter: { crules: [{ condition: 'equal' }] }  
                },
                {
                    title: "Finish", 
                    width: 130, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "finish", 
                    validations: [{type: 'nonEmpty', msg: "Finish is required."}], 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Rim Width", 
                    width: 80, 
                    dataType: "float", 
                    align: "center", 
                    dataIndx: "rim_width", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Construction", 
                    width: 100, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "construction", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Rim Diameter", 
                    width: 80, 
                    dataType: "float", 
                    align: "center", 
                    dataIndx: "rim_diameter", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Size", 
                    width: 80, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "size", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Bolt Pattern", 
                    width: 100, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "bolt_pattern", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Hub Bore", 
                    width: 80, 
                    dataType: "float", 
                    align: "center", 
                    dataIndx: "hub_bore", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Offset", 
                    width: 80, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "offset", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Backspacing", 
                    width: 90, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "backspacing", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Max Wheel Load",
                    width: 100, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "max_wheel_load", 
                    filter: { crules: [{ condition: 'equal' }] } 
                },
                {
                    title: "Weight", 
                    width: 100, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "weight"
                },
                {
                    title: "Lipsize", 
                    width: 80, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "lipsize"
                },
                {
                    title: "US Retail Price", 
                    width: 80, 
                    dataType: "float", 
                    align: "center", 
                    dataIndx: "us_retail_price"
                },
                {
                    title: "Sale Price", 
                    width: 80, 
                    dataType: "float", 
                    align: "center", 
                    dataIndx: "sale_price"
                },
                {
                    title: "Images", 
                    width: 200, 
                    dataType: "string", 
                    align: "center", 
                    dataIndx: "images"
                }
            ];

            // Toolbar (exact from old system)
            var toolbar = {
                cls: 'pq-toolbar-export',
                items: [
                    {
                        type: 'select',
                        label: 'Format: ',
                        attr: 'id="export_format"',
                        options: [{xlsx: 'Excel', csv: 'Csv', htm: 'Html'}]
                    },
                    {
                        type: 'button',
                        label: " Export",
                        cls: "voyager-Export",
                        listener: function () {
                            var format = $("#export_format").val(),
                                blob = this.exportData({
                                    format: format,
                                    nopqdata: true,
                                    render: true
                                });
                            if (typeof blob === "string") {
                                blob = new Blob([blob]);
                            }
                            saveAs(blob, "Products." + format);
                        }
                    },
                    {type: 'separator'},
                    {
                        type: 'textbox',
                        label: "Filter: ",
                        attr: 'placeholder="Enter text"',
                        listener: {
                            timeout: function (evt) {
                                var txt = $(evt.target).val();
                                var rules = this.getCMPrimary().map(function (colModel) {
                                    return {
                                        dataIndx: colModel.dataIndx,
                                        condition: 'contain',
                                        value: txt
                                    }
                                })
                                this.filter({
                                    mode: 'OR',
                                    rules: rules
                                })
                            }
                        }
                    },
                    {type: 'separator'},
                    {
                        type: 'button', 
                        icon: '', 
                        label: ' New Product', 
                        cls: 'voyager-plus', 
                        listener: function () {
                            var rowData = {product_id: ''};
                            var rowIndx = this.addRow({rowData: rowData, checkEditable: true});
                            this.goToPage({rowIndx: rowIndx});
                            this.editFirstCellInRow({rowIndx: rowIndx});
                        }
                    },
                    {type: 'separator'},
                    {
                        type: 'button', 
                        icon: '', 
                        label: 'Bulk Delete', 
                        cls: 'voyager-delete', 
                        listener: function () {
                            var ids = this.SelectRow().getSelection().map(function(rowList){
                                return rowList.rowData.id;
                            })
                            bulkDelete(ids);
                        }
                    }
                ]
            };

            // Grid options (exact from old system)
            var obj = {
                rowHt: 50,
                rowBorders: true,
                trackModel: {on: true},
                height: '100vh',
                minHeight: '400px',
                maxHeight: $(window).height()-200,
                resizable: true,
                
                title: "<b>Products</b>",
                colModel: colModel,
                toolbar: toolbar,
                
                // Freeze columns
                freezeCols: 2,
                
                // Filters
                filterModel: { header: true, type: 'local', on: true, mode: "AND" },
                
                // History
                history: function (evt, ui) {
                    var $tb = this.toolbar();
                    if (ui.canUndo != null) {
                        $("button.changes", $tb).button("option", {disabled: !ui.canUndo});
                    }
                    if (ui.canRedo != null) {
                        $("button:contains('Redo')", $tb).button("option", "disabled", !ui.canRedo);
                    }
                    $("button:contains('Undo')", $tb).button("option", {label: 'Undo (' + ui.num_undo + ')'});
                    $("button:contains('Redo')", $tb).button("option", {label: 'Redo (' + ui.num_redo + ')'});
                },
                
                // Auto-save on change
                change: function (evt, ui) {
                    saveChanges();
                },
                
                destroy: function () {
                    clearInterval(interval);
                },
                
                postRenderInterval: -1,
                pageModel: { type: "local", rPP: 100, option: [100, 200, 300, 400, 500] },
                
                dataModel: {
                    dataType: "JSON",
                    recIndx: "id",
                    data: data
                },
                
                load: function (evt, ui) {
                    var grid = this,
                        data = grid.option('dataModel').data;
                    grid.widget().pqTooltip();
                    grid.isValid({ data: data });
                }
            };

            // Initialize grid (with delay like old system)
            setTimeout(function () {
                grid = pq.grid("#grid_json", obj);
                $('.panel-body .pq-grid-hd-search-field').attr('placeholder', 'Search');
            }, 500);
        });
    </script>
    @endpush
</x-filament-panels::page>
```

---

## 🔌 Step 3: Create API Controller

**File:** `app/Modules/Products/Http/Controllers/ProductGridController.php`

```php
<?php

namespace App\Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\Brand;
use App\Modules\Products\Models\ProductModel;
use App\Modules\Products\Models\Finish;
use App\Modules\Products\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProductGridController extends Controller
{
    /**
     * Save grid changes (add/update/delete)
     * Pattern: EXACT from old system's variantsAddUpdateGrid
     */
    public function saveChanges(Request $request)
    {
        DB::beginTransaction();

        try {
            $list = $request->input('list', []);
            
            $addList = $list['addList'] ?? [];
            $updateList = $list['updateList'] ?? [];
            $deleteList = $list['deleteList'] ?? [];

            $responseAddList = [];
            $responseUpdateList = [];
            $responseDeleteList = [];

            // Process additions
            foreach ($addList as $row) {
                $validator = Validator::make($row, [
                    'sku' => 'required|string|max:100|unique:products,sku',
                    'brand' => 'required|string',
                    'model' => 'required|string',
                    'finish' => 'required|string',
                ]);

                if ($validator->fails()) {
                    throw new \Exception('Validation failed: ' . $validator->errors()->first());
                }

                // Get or create brand/model/finish
                $brand = Brand::firstOrCreate(['name' => $row['brand']]);
                $model = ProductModel::firstOrCreate([
                    'name' => $row['model'],
                    'brand_id' => $brand->id
                ]);
                $finish = Finish::firstOrCreate(['name' => $row['finish']]);

                $product = Product::create([
                    'sku' => $row['sku'],
                    'name' => $row['name'] ?? "{$brand->name} {$model->name}",
                    'product_full_name' => $row['product_full_name'] ?? null,
                    'brand_id' => $brand->id,
                    'model_id' => $model->id,
                    'finish_id' => $finish->id,
                    'construction' => $row['construction'] ?? null,
                    'price' => $row['us_retail_price'] ?? 0,
                    'sale_price' => $row['sale_price'] ?? null,
                    'total_quantity' => 0,
                    'status' => 1,
                    'sync_status' => 'manual',
                ]);

                // Create variant if dimensions provided
                if (!empty($row['size']) || !empty($row['rim_width'])) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'size' => $row['size'] ?? null,
                        'width' => $row['rim_width'] ?? null,
                        'diameter' => $row['rim_diameter'] ?? null,
                        'bolt_pattern' => $row['bolt_pattern'] ?? null,
                        'offset' => $row['offset'] ?? null,
                        'backspacing' => $row['backspacing'] ?? null,
                        'center_bore' => $row['hub_bore'] ?? null,
                    ]);
                }

                $responseAddList[] = array_merge($row, ['id' => $product->id]);
            }

            // Process updates
            foreach ($updateList as $row) {
                if (!isset($row['id'])) continue;

                $product = Product::find($row['id']);
                if (!$product) continue;

                // Get or create brand/model/finish if changed
                if (isset($row['brand'])) {
                    $brand = Brand::firstOrCreate(['name' => $row['brand']]);
                    $row['brand_id'] = $brand->id;
                }
                if (isset($row['model'])) {
                    $model = ProductModel::firstOrCreate([
                        'name' => $row['model'],
                        'brand_id' => $product->brand_id
                    ]);
                    $row['model_id'] = $model->id;
                }
                if (isset($row['finish'])) {
                    $finish = Finish::firstOrCreate(['name' => $row['finish']]);
                    $row['finish_id'] = $finish->id;
                }

                $updateData = array_intersect_key($row, array_flip([
                    'sku', 'name', 'product_full_name', 'brand_id', 'model_id', 'finish_id',
                    'construction', 'total_quantity', 'status'
                ]));

                if (isset($row['us_retail_price'])) {
                    $updateData['price'] = $row['us_retail_price'];
                }
                if (isset($row['sale_price'])) {
                    $updateData['sale_price'] = $row['sale_price'];
                }

                $product->update($updateData);

                // Update variant if exists
                $variant = $product->variants()->first();
                if ($variant) {
                    $variantData = [];
                    if (isset($row['size'])) $variantData['size'] = $row['size'];
                    if (isset($row['rim_width'])) $variantData['width'] = $row['rim_width'];
                    if (isset($row['rim_diameter'])) $variantData['diameter'] = $row['rim_diameter'];
                    if (isset($row['bolt_pattern'])) $variantData['bolt_pattern'] = $row['bolt_pattern'];
                    if (isset($row['offset'])) $variantData['offset'] = $row['offset'];
                    if (isset($row['backspacing'])) $variantData['backspacing'] = $row['backspacing'];
                    if (isset($row['hub_bore'])) $variantData['center_bore'] = $row['hub_bore'];
                    
                    if (!empty($variantData)) {
                        $variant->update($variantData);
                    }
                }

                $responseUpdateList[] = $row;
            }

            // Process deletions
            foreach ($deleteList as $row) {
                if (!isset($row['id'])) continue;

                $product = Product::find($row['id']);
                if ($product) {
                    $product->delete();
                    $responseDeleteList[] = $row;
                }
            }

            DB::commit();

            return response()->json([
                'addList' => $responseAddList,
                'updateList' => $responseUpdateList,
                'deleteList' => $responseDeleteList,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Grid save error: ' . $e->getMessage());
            
            return response()->json([
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }

    /**
     * Bulk delete products
     * Pattern: EXACT from old system's variantsBulkDeleteGrid
     */
    public function bulkDelete(Request $request)
    {
        DB::beginTransaction();

        try {
            $deleteIds = $request->input('deleteIds', []);
            $list = $request->input('list', []);
            
            $addList = $list['addList'] ?? [];
            $updateList = $list['updateList'] ?? [];
            $deleteList = [];

            // Delete products by IDs
            foreach ($deleteIds as $id) {
                $product = Product::find($id);
                if ($product) {
                    $product->delete();
                    $deleteList[] = ['id' => $id];
                }
            }

            DB::commit();

            return response()->json([
                'addList' => $addList,
                'updateList' => $updateList,
                'deleteList' => $deleteList,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Bulk delete error: ' . $e->getMessage());
            
            return response()->json([
                'errors' => [$e->getMessage()]
            ], 422);
        }
    }
}
```

---

## 🛣️ Step 4: Add Routes

**File:** `routes/api.php`

```php
use App\Modules\Products\Http\Controllers\ProductGridController;

Route::middleware(['auth:sanctum'])->prefix('products/grid')->group(function () {
    Route::post('/save', [ProductGridController::class, 'saveChanges']);
    Route::post('/bulk-delete', [ProductGridController::class, 'bulkDelete']);
});
```

---

## 📋 Implementation Checklist

### ✅ Phase 1: Library Setup (DONE)
- [x] Copy pqgridf folder to public directory
- [x] Verify all library files present

### Phase 2: Backend Setup
- [ ] Create Product, Brand, ProductModel, Finish models
- [ ] Run migrations
- [ ] Create ProductGridController
- [ ] Add API routes

### Phase 3: Filament Integration
- [ ] Create ManageProductsGrid page
- [ ] Create products-grid.blade.php template
- [ ] Update ProductResource to include grid page
- [ ] Test navigation

### Phase 4: Testing
- [ ] Test grid loads with data
- [ ] Test inline editing
- [ ] Test auto-save
- [ ] Test new product creation
- [ ] Test bulk delete
- [ ] Test export (Excel/CSV)
- [ ] Test filters
- [ ] Test with 1000+ products

---

## 🎯 Key Features (Exact from Old System)

✅ **Auto-save** - Saves changes automatically after edit  
✅ **Inline editing** - Click any cell to edit  
✅ **Bulk operations** - Select multiple rows, bulk delete  
✅ **Export** - Export to Excel, CSV, or HTML  
✅ **Filters** - Filter by any column  
✅ **Search** - Global search across all columns  
✅ **Frozen columns** - Checkbox and delete button stay visible  
✅ **Validation** - Required fields validated inline  
✅ **Pagination** - 100/200/300/400/500 records per page  
✅ **Responsive** - Adjusts to window height  

---

## 📝 Next Steps

1. **Create database tables** (brands, models, finishes, products, variants)
2. **Test the grid** with sample data
3. **Add dealer pricing integration** (brand_id/model_id for discounts)
4. **Add image upload** functionality
5. **Add product sync** from external system

---

**STATUS:** ✅ **READY TO IMPLEMENT**

Pattern replicated exactly from:
`C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\products\data-grid.blade.php`

