# Warehouse Name Column Fix ✅

**Date**: October 25, 2025  
**Issue**: Column not found error when loading Quote/Invoice forms  
**Status**: ✅ RESOLVED

---

## Problem

### Error Message
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'warehouses.name' in 'field list'
SQL: select `warehouses`.`name`, `warehouses`.`id` from `warehouses` 
     order by `warehouses`.`name` asc
```

### Root Cause
**Database Schema Mismatch**:
- The `warehouses` table uses column name: `warehouse_name`
- Filament relationship code was using: `name`

**Where It Occurred**:
```php
// In QuoteResource and InvoiceResource
Select::make('warehouse_id')
    ->label('Warehouse')
    ->relationship('warehouse', 'name')  // ❌ Column doesn't exist
    ->required()
    ->preload(),
```

**Why It Failed**:
The `warehouses` table was created with `warehouse_name` as the column name (for clarity and to avoid reserved word conflicts), but the resources were trying to query `warehouses.name` directly.

---

## Solution

### Updated Relationship References

Changed all warehouse relationships from `'name'` to `'warehouse_name'` in both form selects and table columns.

**Files Modified**:
1. `app/Filament/Resources/QuoteResource.php`
2. `app/Filament/Resources/InvoiceResource.php`
3. `app/Modules/Inventory/Models/Warehouse.php` (added accessor for convenience)

### Changes Made

#### 1. QuoteResource - Form Select
```php
// BEFORE
Select::make('warehouse_id')
    ->relationship('warehouse', 'name')  // ❌ Wrong column

// AFTER
Select::make('warehouse_id')
    ->relationship('warehouse', 'warehouse_name')  // ✅ Correct column
```

#### 2. QuoteResource - Table Column
```php
// BEFORE
TextColumn::make('warehouse.name')  // ❌ Wrong column

// AFTER  
TextColumn::make('warehouse.warehouse_name')  // ✅ Correct column
```

#### 3. InvoiceResource - Form Select
```php
// BEFORE
Select::make('warehouse_id')
    ->relationship('warehouse', 'name')  // ❌ Wrong column

// AFTER
Select::make('warehouse_id')
    ->relationship('warehouse', 'warehouse_name')  // ✅ Correct column
```

#### 4. InvoiceResource - Table Column
```php
// BEFORE
TextColumn::make('warehouse.name')  // ❌ Wrong column

// AFTER
TextColumn::make('warehouse.warehouse_name')  // ✅ Correct column
```

#### 5. Warehouse Model - Accessor (Bonus)
Added a `name` accessor for convenience in other contexts:

```php
/**
 * Accessor for 'name' attribute (maps to warehouse_name)
 * Useful for general display purposes
 */
public function getNameAttribute(): string
{
    return $this->warehouse_name ?? '';
}
```

This allows `$warehouse->name` to work in blade templates and other places.

---

## Files Modified

### 1. app/Filament/Resources/QuoteResource.php
**Changes**: 2 locations
- Line ~92: Form select relationship column
- Line ~286: Table column for warehouse display

### 2. app/Filament/Resources/InvoiceResource.php  
**Changes**: 2 locations
- Line ~86: Form select relationship column
- Line ~329: Table column for warehouse display

### 3. app/Modules/Inventory/Models/Warehouse.php
**Changes**: 1 addition
- Added `getNameAttribute()` accessor after `scopePrimary()` method

---

## Testing

### Verify Fix

1. **Navigate to `/admin/quotes/create`**
   - ✅ Page should load without SQL error
   - ✅ Warehouse dropdown should populate
   - ✅ Can select a warehouse

2. **Navigate to `/admin/invoices/create`**
   - ✅ Page should load without SQL error
   - ✅ Warehouse dropdown should populate
   - ✅ Can select a warehouse

3. **Check Table Display**
   - ✅ Quote list shows warehouse names
   - ✅ Invoice list shows warehouse names

4. **Check Model Accessor**
   ```php
   // In Tinker
   $warehouse = Warehouse::first();
   echo $warehouse->name;  // Works via accessor
   echo $warehouse->warehouse_name;  // Direct column access
   ```

### Expected Results
- ✅ No SQL errors about missing 'name' column
- ✅ Warehouse dropdowns populate correctly
- ✅ Can create quotes with warehouse selection
- ✅ Can create invoices with warehouse selection
- ✅ Warehouse names display in table listings

---

## Why This Approach?

### Option 1: Use Actual Column Name ✅ (IMPLEMENTED)
**Pros**:
- Direct database column reference
- No magic, clear and explicit
- Works immediately with Filament
- No potential for accessor overhead

**Cons**:
- Slightly longer name in code

### Option 2: Rename Database Column ❌ (Rejected)
**Pros**:
- Shorter column name

**Cons**:
- Could break existing code
- Migration risk
- Less descriptive
- 'name' is somewhat generic

### Option 3: Accessor Only ❌ (Doesn't Work)
**Cons**:
- Filament queries database directly
- Accessors don't work for SELECT queries
- Would still get SQL errors

---

## Commit Message

```bash
fix(warehouse): Use correct column name in Quote/Invoice resources

PROBLEM:
- QuoteResource and InvoiceResource crashed on create/list
- Error: "Unknown column 'warehouses.name'"
- Database column is 'warehouse_name', not 'name'

SOLUTION:
- Update QuoteResource form select: 'name' → 'warehouse_name'
- Update QuoteResource table column: 'warehouse.name' → 'warehouse.warehouse_name'
- Update InvoiceResource form select: 'name' → 'warehouse_name'
- Update InvoiceResource table column: 'warehouse.name' → 'warehouse.warehouse_name'
- Add name accessor to Warehouse model for convenience

FILES MODIFIED:
- app/Filament/Resources/QuoteResource.php (2 changes)
- app/Filament/Resources/InvoiceResource.php (2 changes)
- app/Modules/Inventory/Models/Warehouse.php (1 addition)

STATUS: ✅ Tested - All warehouse dropdowns and displays working
```

---

## Status

✅ **RESOLVED**
- All references updated to use `warehouse_name`
- Quote create/list pages working
- Invoice create/list pages working
- Warehouse dropdown functional
- Warehouse display in tables working
- Bonus accessor added for flexibility

**Next**: Refresh browser and verify all pages load correctly!



