# pqGrid Filter Headers Diagnostic

**Date:** October 23, 2025  
**Issue:** Filter text boxes not showing in header row  
**Expected:** Filter boxes like the old Tunerstop system (see screenshot)

---

## 🔍 Problem Analysis

### What's Missing
In the old Tunerstop system, you see filter text boxes in the header row where users can type to filter each column. Our current implementation has the configuration but filters aren't displaying.

### Current Configuration (Correct)
```javascript
filterModel: { header: true, type: 'local', on: true, mode: "AND" }
```

This is identical to the old system, so the issue is likely:
1. **pqGrid library version/files**
2. **CSS not loading filter header styles**
3. **Filter initialization timing**

---

## ✅ Fixes to Apply

### Fix 1: Use Minified pqGrid Files (Not DEV)

**Current (line 14-15 in grid.blade.php):**
```html
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.dev.css') }}">
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.dev.css') }}">
```

**Change to:**
```html
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.min.css') }}">
<link rel="stylesheet" href="{{ asset('pqgridf/pqgrid.ui.min.css') }}">
```

**Current (line 398 in grid.blade.php):**
```html
<script src="{{ asset('pqgridf/pqgrid.dev.js') }}"></script>
```

**Change to:**
```html
<script src="{{ asset('pqgridf/pqgrid.min.js') }}"></script>
```

### Fix 2: Ensure Filter CSS is Loaded

The filter header row uses these CSS classes:
- `.pq-grid-header-search-row` - The filter row container
- `.pq-grid-hd-search-field` - The input text boxes
- `.pq-grid-col` - Column headers with filters

These should be in `pqgrid.ui.min.css`.

### Fix 3: Force Filter Refresh After Grid Init

**In products-grid.js (after grid initialization):**

```javascript
setTimeout(function () {
    grid = pq.grid("#productsGrid", obj);
    
    console.log('✅ Grid initialized');
    
    // FORCE filter header to show
    grid.refreshHeader();
    
    // Set placeholder text
    setTimeout(function() {
        $('.pq-grid-hd-search-field').attr('placeholder', 'Search');
        console.log('🔍 Filter fields found:', $('.pq-grid-hd-search-field').length);
    }, 200);
}, 500);
```

### Fix 4: Add Filter CSS Explicitly

Add this to the `<style>` section in grid.blade.php:

```css
/* Force filter header row to display */
.pq-grid-header-search-row {
    display: table-row !important;
    background: #f8f9fa;
}

.pq-grid-hd-search-field {
    width: 100% !important;
    padding: 5px !important;
    border: 1px solid #ddd !important;
    border-radius: 3px;
    font-size: 13px;
}

/* Make filter inputs visible */
.pq-grid-header-search-row .pq-grid-col {
    padding: 5px !important;
    background: #f8f9fa !important;
}
```

---

## 🔧 Step-by-Step Fix Process

### Step 1: Update CSS/JS File References
Change from `.dev` to `.min` versions in `grid.blade.php`

### Step 2: Add Filter CSS
Add the explicit filter CSS rules to the style section

### Step 3: Test in Browser
1. Clear browser cache (Ctrl+Shift+Delete)
2. Reload page (Ctrl+F5)
3. Open browser console (F12)
4. Check for:
   - Console log: "Filter fields found: X" (should be > 0)
   - Network tab: pqgrid.min.css and pqgrid.min.js loaded successfully
   - Elements tab: Look for `.pq-grid-header-search-row` element

### Step 4: Verify Filter Functionality
- Type in any filter box
- Results should filter instantly
- Multiple filters should work together (AND mode)

---

## 📝 Comparison with Old System

### Old System (Working) - Reporting/data-grid.blade.php
```html
<!-- CSS -->
<link rel="stylesheet" href="{{asset('pqgridf/pqgrid.ui.min.css')}}" />

<!-- JS -->
<script src="{{asset('pqgridf/pqgrid.min.js')}}"></script>

<!-- Grid Init -->
<script>
grid = pq.grid("#grid_json", obj);
$('.panel-body .pq-grid-hd-search-field').attr('placeholder', 'Search');
</script>
```

### Key Differences:
1. ✅ Old system uses `.min` files
2. ✅ Old system sets placeholder after grid init
3. ✅ Old system has working filter headers

---

## 🎯 Expected Result

After fixes, you should see:
- **Header Row 1:** Column titles (SKU, Brand, Model, Finish, etc.)
- **Header Row 2:** Filter text boxes (one per column) ← THIS WAS MISSING
- **Data Rows:** Product data

The filter boxes should:
- Accept text input
- Filter data in real-time
- Show placeholder "Search"
- Work independently or together

---

## 🚨 If Still Not Working

### Debug Checklist:

1. **Check Console for Errors:**
   ```javascript
   // Look for:
   - "pqgrid.min.js not found"
   - "Uncaught TypeError"
   - "filterModel is undefined"
   ```

2. **Check Grid Object:**
   ```javascript
   console.log(grid.option('filterModel'));
   // Should show: {header: true, type: "local", on: true, mode: "AND"}
   ```

3. **Check DOM Elements:**
   ```javascript
   console.log($('.pq-grid-header-search-row').length);
   // Should be 1 or more
   
   console.log($('.pq-grid-hd-search-field').length);
   // Should match number of filterable columns
   ```

4. **Manually Enable Filters:**
   ```javascript
   grid.option('filterModel', {header: true, type: 'local', on: true, mode: "AND"});
   grid.refreshHeader();
   grid.refresh();
   ```

---

## 📋 Files to Modify

1. **resources/views/products/grid.blade.php**
   - Line 14-15: Change CSS to .min files
   - Line 398: Change JS to .min file
   - Add filter CSS in `<style>` section

2. **public/js/products-grid.js** (if needed)
   - Add `grid.refreshHeader()` after initialization
   - Already has placeholder code

---

## ✅ Success Criteria

- [ ] Filter row visible below column headers
- [ ] Each column has a filter text box
- [ ] Typing in filter box filters data immediately
- [ ] Multiple filters work together (AND logic)
- [ ] Placeholder text shows "Search"
- [ ] Console shows: "Filter fields found: [number]"

---

**Status:** Ready to apply fixes  
**Estimated Time:** 10-15 minutes  
**Next Step:** Update grid.blade.php file references
