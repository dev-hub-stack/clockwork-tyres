# Products Grid - Tunerstop Structure Migration Plan

**Date:** October 22, 2025  
**Goal:** Match Tunerstop's product variant grid structure exactly

---

## Current Issues

1. ✅ pqGrid library loaded successfully (local files)
2. ✅ Grid initializing properly
3. ❌ API endpoints returning 500 errors (brands, models, finishes)
4. ❌ Grid showing Product model instead of ProductVariant model
5. ❌ Columns don't match Tunerstop structure
6. ❌ Missing bulk upload functionality

---

## Tunerstop Structure Analysis

### Models:
- **Product**: Contains base product info (name, SKU, brand_id, model_id, finish_id, images, construction, status)
- **ProductVariant**: Contains variant-specific data (SKU, size, bolt_pattern, hub_bore, offset, prices, etc.)
- Relationship: Product `hasMany` ProductVariants

### Grid Shows: **ProductVariants** (not Products)

###Column Structure (26 columns):
1. **Checkbox** - Select All
2. **Action** - Delete button
3. **SKU** - variant SKU (editable, required)
4. **Name** - product name (editable, required)
5. **Product Full Name** - full product name (editable, required)
6. **Brand** - brand name (editable, required, filterable)
7. **Model** - model name (editable, required, filterable)
8. **Supplier Stock** - integer, right-aligned
9. **Finish** - finish name (editable, required, filterable)
10. **Construction** - string
11. **Rim Width** - float
12. **Rim Diameter** - float
13. **Size** - string
14. **Bolt Pattern** - string
15. **Hub Bore** - float
16. **Offset** - string
17. **Warranty (Backspacing)** - string
18. **Max Wheel Load** - string
19. **Weight** - string
20. **Lipsize** - string
21. **US Retail Price** - float, right-aligned
22. **UAE Retail Price** - float, right-aligned
23. **Sale Price** - float, right-aligned
24. **Clearance Corner** - checkbox (1/0)
25. **Images** - JSON array of images

### Data Source:
```php
var data = <?php echo json_encode($products_data);?>;
```
- Uses **local JSON data** from PHP variable
- NOT remote AJAX calls
- Data loaded from controller

### Features:
1. Auto-save on change (interval-based or manual)
2. Bulk delete with confirmation
3. Export to Excel/CSV/HTML
4. Inline editing with validation
5. Filtering per column
6. Bulk import from CSV/Excel
7. Bulk image upload

---

## Required Changes

### 1. Database/Models ✅ (Already exists)
- `products` table - base product info
- `product_variants` table - variant specs
- Models: Product, ProductVariant with relationships

### 2. Controller Changes
Need to replace `ProductGridController` with `ProductVariantGridController`:

**Methods needed:**
- `index()` - Load view with ALL variant data in JSON
- `saveBatch()` - Save changes (add/update)
- `deleteBatch()` - Delete selected variants
- NO API endpoints needed (data loaded upfront)

**Data structure:**
```php
$variants = ProductVariant::with(['product.brand', 'product.model', 'finish'])
    ->get()
    ->map(function($variant) {
        return [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'name' => $variant->product->name,
            'product_full_name' => ...,  // Computed
            'brand' => $variant->product->brand->name,
            'model' => $variant->product->model->name,
            'supplier_stock' => $variant->supplier_stock,
            'finish' => $variant->finish->name,
            'construction' => $variant->product->construction,
            'rim_width' => $variant->rim_width,
            'rim_diameter' => $variant->rim_diameter,
            'size' => $variant->size,
            'bolt_pattern' => $variant->bolt_pattern,
            'hub_bore' => $variant->hub_bore,
            'offset' => $variant->offset,
            'backspacing' => $variant->backspacing,
            'max_wheel_load' => $variant->max_wheel_load,
            'weight' => $variant->weight,
            'lipsize' => $variant->lipsize,
            'us_retail_price' => $variant->us_retail_price,
            'uae_retail_price' => $variant->uae_retail_price,
            'sale_price' => $variant->sale_price,
            'clearance_corner' => $variant->clearance_corner,
            'images' => $variant->product->images,
        ];
    });
```

### 3. View Changes
Update `resources/views/products/grid.blade.php`:
- Embed JSON data in page: `var data = @json($variants);`
- Remove all API dropdown calls
- Change grid data model from remote to local

### 4. JavaScript Changes
Update `public/js/products-grid.js`:
- Replace 13 columns with 25 columns matching Tunerstop
- Change dataModel from `remote` to `local`
- Remove API calls for dropdowns
- Add interval-based auto-save (2 seconds)
- Add bulk delete functionality
- Add export toolbar
- Add checkbox column with "Select All"
- Add Action column with Delete button

### 5. Routes Changes
Replace current routes:
```php
// Remove these:
Route::get('products/grid', [ProductGridController::class, 'index']);
Route::post('products/grid/data', [ProductGridController::class, 'getData']);
// ... all other API routes

// Add these:
Route::get('products/grid', [ProductVariantGridController::class, 'index'])
    ->name('products.grid');
Route::post('products/grid/save-batch', [ProductVariantGridController::class, 'saveBatch'])
    ->name('products.grid.save-batch');
Route::post('products/grid/delete-batch', [ProductVariantGridController::class, 'deleteBatch'])
    ->name('products.grid.delete-batch');
Route::get('products/bulk-import', [ProductVariantGridController::class, 'bulkImportForm'])
    ->name('products.bulk.import.form');
Route::post('products/bulk-import', [ProductVariantGridController::class, 'bulkImport'])
    ->name('products.bulk.import');
```

### 6. Bulk Upload Feature
Add routes and methods:
- Form to upload CSV/Excel
- Import logic from Tunerstop
- Sample file download
- Progress/feedback UI

---

## Implementation Steps

### Phase 1: Fix API Errors & Switch to Local Data (30 min)
1. Create ProductVariantGridController
2. Load all variant data in controller
3. Pass data to view as JSON
4. Update JavaScript to use local data
5. Test grid loads with data

### Phase 2: Update Column Structure (45 min)
1. Update JavaScript columns to match Tunerstop (25 columns)
2. Add checkbox column with "Select All"
3. Add Action column with Delete button
4. Test all columns display correctly
5. Test filtering works

### Phase 3: Update Save/Delete Logic (30 min)
1. Update saveBatch method to handle variants
2. Update deleteBatch method
3. Add interval auto-save (optional)
4. Test CRUD operations

### Phase 4: Add Bulk Import (1 hour)
1. Create bulk import form view
2. Add import controller methods
3. Implement CSV/Excel parsing
4. Add validation and error handling
5. Test import functionality

### Phase 5: Add Export & Polish (30 min)
1. Add export toolbar (Excel/CSV/HTML)
2. Test export functionality
3. UI polish and styling
4. Final testing

**Total Estimated Time:** 3-4 hours

---

## Next Immediate Step

Start with Phase 1 - switching from remote to local data to fix the 500 errors and get the grid working with actual data.

