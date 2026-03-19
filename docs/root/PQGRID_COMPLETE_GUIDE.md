# PQGrid PRO - Complete Setup & Implementation Guide

**Author:** Reporting CRM Team  
**Date:** November 2, 2025  
**Version:** PQGrid PRO v8.x  
**License:** GPL v3  

---

## Table of Contents
1. [Introduction](#introduction)
2. [Installation & Setup](#installation--setup)
3. [Basic vs PRO Version](#basic-vs-pro-version)
4. [Essential Dependencies](#essential-dependencies)
5. [Initialization Patterns](#initialization-patterns)
6. [Filter Implementation](#filter-implementation)
7. [Common Issues & Solutions](#common-issues--solutions)
8. [Performance Optimization](#performance-optimization)
9. [Real-World Examples](#real-world-examples)

---

## 1. Introduction

**What is PQGrid?**
- Lightweight jQuery-based data grid
- Excel-like editing capabilities
- Supports 100,000+ records
- Compatible with Angular, React, Vue, plain JavaScript

**When to Use:**
- Large dataset management (products, inventory, orders)
- Excel-like user experience needed
- Inline editing with tracking changes
- Export to Excel/CSV functionality
- Advanced filtering and sorting

---

## 2. Installation & Setup

### Folder Structure
```
public/pqgridf/
├── pqgrid-pro.min.css          # PRO version styles
├── pqgrid-pro.min.js           # PRO version JavaScript
├── pqgrid.ui.min.css           # UI theme styles
├── pqgrid-pro.ui.min.css       # PRO UI styles (alternative)
├── jszip-2.5.0.min.js          # Required for Excel export
├── pqselect.min.js             # Dropdown selection widget
└── images/                     # Grid icons and sprites
```

### File Placement
1. **Copy the entire `pqgridf` folder** to your public directory:
   ```
   /public/pqgridf/
   ```

2. **Do NOT use CDN** for PRO version (requires license)

3. **Verify file paths** match your project structure

---

## 3. Basic vs PRO Version

### Key Differences

| Feature | Basic (Free) | PRO (Licensed) |
|---------|-------------|----------------|
| Filter Headers | ❌ No | ✅ Yes |
| Column Freezing | Limited | ✅ Full Support |
| Excel Export | ❌ No | ✅ Yes |
| Advanced Editing | Limited | ✅ Full |
| Tree Grid | ❌ No | ✅ Yes |
| Virtual Scrolling | Basic | ✅ Enhanced |
| Support | Community | ✅ Premium |

### File Names Matter!
```html
<!-- ❌ WRONG - Basic version -->
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.min.css') }}">
<script src="{{ asset('pqgridf/pqgrid.min.js') }}"></script>

<!-- ✅ CORRECT - PRO version -->
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}">
<script src="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
<script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
```

**Critical:** The filename MUST contain `-pro` for PRO features to work!

---

## 4. Essential Dependencies

### Required Libraries (Load in Order)

```html
<!-- 1. jQuery (Required - PQGrid uses jQuery) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- 2. jQuery UI (Required for dragging, resizing, themes) -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- 3. PQGrid PRO CSS -->
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}">
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">

<!-- 4. PQGrid PRO JavaScript -->
<script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>

<!-- 5. FileSaver.js (For export functionality) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
```

### Bootstrap Integration (Optional)
```html
<!-- Bootstrap 5 (if using modals, buttons) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
```

---

## 5. Initialization Patterns

### Basic Grid Setup

```javascript
$(document).ready(function() {
    // Data array
    var data = [
        { id: 1, name: "Product 1", price: 100, stock: 50 },
        { id: 2, name: "Product 2", price: 200, stock: 30 }
    ];

    // Column definitions
    var colModel = [
        { 
            title: "ID", 
            dataIndx: "id", 
            width: 80,
            dataType: "integer",
            editable: false
        },
        { 
            title: "Product Name", 
            dataIndx: "name", 
            width: 300,
            dataType: "string",
            editable: true
        },
        { 
            title: "Price", 
            dataIndx: "price", 
            width: 120,
            dataType: "float",
            editable: true
        },
        { 
            title: "Stock", 
            dataIndx: "stock", 
            width: 100,
            dataType: "integer",
            editable: true
        }
    ];

    // Grid configuration
    var obj = {
        width: "auto",
        height: 600,
        title: "Products Grid",
        colModel: colModel,
        dataModel: { 
            data: data 
        }
    };

    // Initialize grid
    $("#myGrid").pqGrid(obj);
});
```

### Grid with All Features

```javascript
var obj = {
    // Dimensions
    width: "auto",              // "auto" = full width, "100%" = container width
    height: 650,                // Fixed height in pixels
    
    // Title
    title: "My Grid Title",
    
    // Columns
    colModel: colModel,
    numberCell: { 
        show: true,             // Show row numbers
        title: "#" 
    },
    
    // Data
    dataModel: { 
        dataType: "JSON",
        recIndx: "id",          // Unique row identifier (REQUIRED for tracking)
        data: data 
    },
    
    // Scrolling
    scrollModel: { 
        horizontal: true,       // Enable horizontal scroll
        autoFit: false          // Don't auto-fit columns to width
    },
    
    // Filtering (PRO ONLY)
    filterModel: { 
        on: true,               // Enable filtering
        mode: "AND",            // "AND" or "OR" for multiple filters
        header: true            // Show filter row in header
    },
    
    // Editing
    editable: true,             // Allow cell editing
    editor: { 
        select: true            // Select text on edit
    },
    editModel: {
        saveKey: $.ui.keyCode.ENTER,  // Save on Enter key
        keyUpDown: false,       // Don't use arrow keys for navigation while editing
        cellBorderWidth: 0
    },
    
    // Pagination
    pageModel: { 
        type: "local",          // "local" or "remote"
        rPP: 100,               // Rows per page
        rPPOptions: [20, 50, 100, 500, 1000]
    },
    
    // Change Tracking
    trackModel: { 
        on: true                // Track changes for save
    },
    historyModel: { 
        on: true                // Enable undo/redo
    },
    
    // Selection
    selectionModel: { 
        type: 'cell',           // 'row' or 'cell'
        mode: 'block'           // 'single', 'range', 'block'
    },
    
    // Copy/Paste
    copyModel: { 
        on: true                // Enable copy/paste
    },
    
    // Toolbar (PRO)
    toolbar: {
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
                label: 'Export',
                listener: function() {
                    var format = $("#export_format").val();
                    var blob = this.exportData({
                        format: format,
                        nopqdata: true,
                        render: true
                    });
                    if (typeof blob === "string") {
                        blob = new Blob([blob]);
                    }
                    saveAs(blob, "export." + format);
                }
            }
        ]
    },
    
    // Column Options
    resizable: true,            // Allow column resize
    rowBorders: true,           // Show row borders
    columnBorders: true,        // Show column borders
    freezeCols: 2,              // Freeze first 2 columns
    wrap: false,                // Don't wrap text
    hwrap: false                // Don't wrap header text
};
```

---

## 6. Filter Implementation

### Enable Filter Headers (PRO ONLY)

**Step 1: Verify PRO Version Files**
```html
<!-- MUST use PRO version files -->
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid-pro.min.css') }}">
<script src="{{ asset('pqgridf/pqgrid-pro.min.js') }}"></script>
```

**Step 2: Enable Filter Model**
```javascript
var obj = {
    filterModel: { 
        on: true,           // ✅ Enable filtering
        mode: "AND",        // Multiple filters combined with AND
        header: true        // ✅ Show filter row in header
    }
};
```

**Step 3: Add Filter to Each Column**
```javascript
var colModel = [
    {
        title: "SKU",
        dataIndx: "sku",
        width: 250,
        filter: { 
            crules: [{ 
                condition: 'begin'  // Filter condition
            }] 
        }
    },
    {
        title: "Price",
        dataIndx: "price",
        width: 120,
        filter: { 
            crules: [{ 
                condition: 'range'  // Range filter for numbers
            }] 
        }
    }
];
```

### Filter Conditions

| Condition | Best For | Example |
|-----------|----------|---------|
| `begin` | Text starting with | SKU, Name |
| `contain` | Text containing | Description |
| `equal` | Exact match | Status, Category |
| `notequal` | Exclusion | Filter out values |
| `range` | Numbers, Dates | Price, Stock |
| `lte` | Less than or equal | Max price |
| `gte` | Greater than or equal | Min stock |

---

## 7. Common Issues & Solutions

### Issue 1: Filter Headers Not Showing

**Symptoms:**
- Filter inputs don't appear in column headers
- Only title row visible

**Causes:**
1. Using basic version instead of PRO
2. `filterModel.header` not set to `true`
3. CSS conflict hiding filter row

**Solution:**
```javascript
// ✅ Verify all three requirements:
1. Use pqgrid-pro.min.js (not pqgrid.min.js)
2. filterModel: { on: true, header: true }
3. Add filter to column: filter: { crules: [...] }
```

**CSS Fix (if still hidden):**
```css
.pq-grid-header-search-row {
    display: table-row !important;
    visibility: visible !important;
}

.pq-grid-hd-search-field {
    display: block !important;
    width: 100% !important;
}
```

---

### Issue 2: Black Arrow Icons in Headers

**Symptoms:**
- Small black triangles/arrows showing in column headers
- Filter/sort icons not matching design

**Solution:**
```css
/* Hide all filter/sort icons */
.pq-grid-header-search-icon,
.pq-grid-col .ui-icon-triangle-1-s,
.pq-grid-col .ui-icon-carat-2-n-s,
.pq-grid-title-row .ui-icon,
.pq-grid-col .ui-icon,
.ui-icon-triangle-1-n,
.ui-icon-triangle-1-s,
.ui-icon-triangle-2-n-s,
span.ui-icon {
    display: none !important;
    visibility: hidden !important;
}
```

---

### Issue 3: Grid Not Full Width

**Symptoms:**
- Grid cuts off columns at viewport edge
- Horizontal scroll not working
- Columns truncated

**Solution:**
```javascript
// Grid configuration
var obj = {
    width: "auto",              // ✅ Use "auto" not "100%"
    scrollModel: { 
        horizontal: true,       // ✅ Enable horizontal scroll
        autoFit: false          // ✅ Don't auto-fit
    }
};
```

**CSS for Full Width (Filament/container constraint):**
```css
/* Override container max-width */
.fi-body, .fi-main, .fi-content {
    max-width: none !important;
    width: 100% !important;
}

#myGrid {
    width: 100% !important;
    overflow-x: auto !important;
}

.pq-grid, .pq-grid-cont {
    width: 100% !important;
}
```

---

### Issue 4: Changes Not Saving

**Symptoms:**
- Edits made but not persisted
- `grid.isDirty()` returns false
- Save function not triggered

**Causes:**
1. Missing `recIndx` in dataModel
2. `trackModel` not enabled
3. Not calling `grid.saveEditCell()` before save

**Solution:**
```javascript
// ✅ REQUIRED: Unique identifier
dataModel: { 
    recIndx: "id",          // CRITICAL - must match your data's unique field
    data: data 
},

// ✅ Enable change tracking
trackModel: { on: true },
historyModel: { on: true },

// ✅ Save function
function saveChanges() {
    // Save current edit
    if (grid.saveEditCell() === false) {
        return false;
    }
    
    // Check if dirty
    if (!grid.isDirty()) {
        alert("No changes to save");
        return;
    }
    
    // Get changes
    var changes = grid.getChanges({ format: 'byVal' });
    
    // Send to server
    $.ajax({
        url: "/save-endpoint",
        type: "POST",
        data: { list: changes },
        success: function(response) {
            grid.commit({ type: 'update', rows: response.updateList });
            alert("Saved successfully!");
        }
    });
}
```

---

### Issue 5: Export Not Working

**Symptoms:**
- Export button does nothing
- Error: "exportData is not a function"
- Excel file corrupted

**Solution:**
```javascript
// ✅ Include FileSaver.js
<script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>

// ✅ Correct export code
var blob = grid.exportData({
    format: 'xlsx',         // 'xlsx', 'csv', or 'htm'
    nopqdata: true,         // Don't include pqGrid metadata
    render: true            // Render values (not raw data)
});

if (typeof blob === "string") {
    blob = new Blob([blob]);
}

saveAs(blob, "export.xlsx");
```

---

## 8. Performance Optimization

### Large Datasets (10,000+ rows)

```javascript
var obj = {
    // Virtual scrolling (auto-enabled for large datasets)
    virtualX: true,
    virtualY: true,
    
    // Pagination (reduces DOM nodes)
    pageModel: { 
        type: "local", 
        rPP: 100        // Show 100 rows at a time
    },
    
    // Disable features not needed
    animate: false,     // Disable animations
    showTop: false,     // Hide top summary
    showBottom: false,  // Hide bottom summary
    
    // Use frozen columns sparingly
    freezeCols: 2,      // Only freeze essential columns
    
    // Batch operations
    batch: {
        enable: true,
        size: 50        // Update 50 rows at once
    }
};
```

### Memory Management

```javascript
// Destroy grid when done
var grid = $("#myGrid").pqGrid("instance");
grid.destroy();

// Clear data
grid.option("dataModel.data", []);
grid.refreshDataAndView();
```

---

## 9. Real-World Examples

### Example 1: Products Grid with Filter

```javascript
var colModel = [
    {
        title: "SKU",
        dataIndx: "sku",
        width: 280,
        dataType: "string",
        editable: false,
        filter: { crules: [{ condition: 'begin' }] }
    },
    {
        title: "Product Name",
        dataIndx: "name",
        width: 450,
        dataType: "string",
        editable: true,
        filter: { crules: [{ condition: 'contain' }] }
    },
    {
        title: "Price",
        dataIndx: "price",
        width: 120,
        dataType: "float",
        editable: true,
        filter: { crules: [{ condition: 'range' }] },
        render: function(ui) {
            return "$" + parseFloat(ui.cellData).toFixed(2);
        }
    },
    {
        title: "Stock",
        dataIndx: "stock",
        width: 100,
        dataType: "integer",
        editable: true,
        filter: { crules: [{ condition: 'equal' }] }
    }
];

var obj = {
    width: "auto",
    height: 650,
    title: "Products Grid",
    colModel: colModel,
    dataModel: { 
        dataType: "JSON",
        recIndx: "id",
        data: products_data 
    },
    filterModel: { 
        on: true, 
        mode: "AND", 
        header: true 
    },
    editable: true,
    trackModel: { on: true },
    scrollModel: { horizontal: true, autoFit: false }
};

$("#productsGrid").pqGrid(obj);
```

---

### Example 2: Dynamic Warehouse Columns

```javascript
// Base columns
var colModel = [
    { title: "SKU", dataIndx: "sku", width: 250 },
    { title: "Product", dataIndx: "name", width: 400 }
];

// Add dynamic warehouse columns
var warehouses = [
    { id: 1, code: "TEXAS" },
    { id: 2, code: "TEST-EU" }
];

warehouses.forEach(function(warehouse) {
    // Quantity column
    colModel.push({
        title: warehouse.code,
        dataIndx: "qty" + warehouse.id,
        width: 120,
        dataType: 'integer',
        editable: true,
        cls: 'warehouse-qty',
        filter: { crules: [{ condition: 'equal' }] }
    });
    
    // ETA column
    colModel.push({
        title: "ETA " + warehouse.code,
        dataIndx: "eta" + warehouse.id,
        width: 180,
        dataType: 'string',
        editable: true,
        cls: 'warehouse-eta',
        filter: { crules: [{ condition: 'begin' }] }
    });
});
```

---

### Example 3: Save with AJAX

```javascript
function saveChanges() {
    var grid = $("#myGrid").pqGrid("instance");
    
    // Save current edit cell
    if (grid.saveEditCell() === false) {
        return false;
    }
    
    // Check if there are changes
    if (!grid.isDirty()) {
        console.log("No changes to save");
        return;
    }
    
    // Get changes
    var changes = grid.getChanges({ format: 'byVal' });
    
    console.log("Saving changes:", changes);
    
    // Show loading
    grid.showLoading();
    
    // AJAX save
    $.ajax({
        url: "/admin/products/save-batch",
        type: "POST",
        dataType: "json",
        data: { list: changes },
        success: function(response) {
            console.log("Save successful:", response);
            
            // Commit changes
            grid.history({ method: 'reset' });
            grid.commit({ type: 'update', rows: response.updateList });
            
            // Show success
            alert("✅ Saved successfully!");
        },
        error: function(xhr, status, error) {
            console.error("Save failed:", error);
            alert("❌ Save failed: " + error);
        },
        complete: function() {
            grid.hideLoading();
        }
    });
}

// Button click
$("#saveBtn").on('click', saveChanges);

// Auto-save on cell change (debounced)
var obj = {
    change: function(evt, ui) {
        clearTimeout(window.gridSaveTimeout);
        window.gridSaveTimeout = setTimeout(saveChanges, 2000);
    }
};
```

---

## Troubleshooting Checklist

Before asking for help, verify:

- [ ] Using PRO version files (`pqgrid-pro.min.js`, not `pqgrid.min.js`)
- [ ] jQuery and jQuery UI loaded BEFORE PQGrid
- [ ] `filterModel: { on: true, header: true }` in config
- [ ] Each column has `filter: { crules: [...] }` defined
- [ ] `dataModel.recIndx` points to unique identifier field
- [ ] `trackModel: { on: true }` enabled for change tracking
- [ ] FileSaver.js loaded for export functionality
- [ ] No JavaScript errors in browser console
- [ ] CSS not hiding filter row (check with browser DevTools)
- [ ] Grid container has valid ID (e.g., `<div id="myGrid"></div>`)

---

## Additional Resources

- **Official Docs:** http://paramquery.com/api
- **Demos:** http://paramquery.com/demos
- **Forum:** http://paramquery.com/forum
- **Tutorials:** http://paramquery.com/tutorial

---

## License

PQGrid PRO is licensed under GPL v3. Commercial license available at paramquery.com.

**Important:** PRO version files must not be hosted on public CDN. Keep in your project's public folder.

---

**Last Updated:** November 2, 2025  
**Tested With:** PQGrid PRO v8.x, jQuery 3.6.0, jQuery UI 1.13.2
