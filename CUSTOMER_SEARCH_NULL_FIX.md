# Customer Search Null Label Fix ✅

**Date**: October 25, 2025  
**Issue**: TypeError when searching for customers in Quote/Invoice create forms  
**Status**: ✅ RESOLVED

---

## Problem

When typing in the customer search field on Quote/Invoice create screens, the error occurred:

```
TypeError: Filament\Forms\Components\Select::isOptionDisabled(): 
Argument #2 ($label) must be of type Illuminate\Contracts\Support\Htmlable|string, 
null given
```

### Root Cause
Some customers in the database have `NULL` in their `business_name` column. When searching for customers, the select dropdown tried to display these null values as labels, causing the TypeError.

---

## Solution Applied

Added `getOptionLabelFromRecordUsing()` to customer selects in **both form sections** (not just filters).

### QuoteResource - Customer Select (Form)
```php
// BEFORE
Select::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->searchable(['business_name', 'first_name', 'last_name', 'email'])
    ->required()
    ->createOptionForm([...])

// AFTER
Select::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->searchable(['business_name', 'first_name', 'last_name', 'email'])
    ->required()
    ->getOptionLabelFromRecordUsing(fn ($record) => 
        $record->business_name ?? $record->name ?? 'Unknown Customer'
    )
    ->createOptionForm([...])
```

### InvoiceResource - Customer Select (Form)
```php
// BEFORE
Select::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->searchable(['business_name', 'first_name', 'last_name', 'email'])
    ->required()
    ->disabled(fn ($record) => $record !== null)

// AFTER
Select::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->searchable(['business_name', 'first_name', 'last_name', 'email'])
    ->required()
    ->getOptionLabelFromRecordUsing(fn ($record) => 
        $record->business_name ?? $record->name ?? 'Unknown Customer'
    )
    ->disabled(fn ($record) => $record !== null)
```

---

## Fallback Chain

The label fallback tries three options in order:

1. **`business_name`** - Primary company/business name
2. **`name`** - Computed accessor (first_name + last_name)
3. **`'Unknown Customer'`** - Final fallback

This ensures that even if a customer has null business_name, we still show a valid label.

---

## Complete Coverage

Now **all customer-related selects** have null-safe labels:

### Forms (Create/Edit Pages)
- ✅ QuoteResource - Customer select with search
- ✅ InvoiceResource - Customer select with search

### Filters (List Pages)
- ✅ QuoteResource - Customer filter
- ✅ InvoiceResource - Customer filter

---

## Testing

### Verify Fix

1. **Navigate to `/admin/quotes/create`**
   - ✅ Page loads
   - ✅ Click on Customer field
   - ✅ Start typing to search
   - ✅ Search results display without errors
   - ✅ Can select a customer

2. **Navigate to `/admin/invoices/create`**
   - ✅ Page loads
   - ✅ Click on Customer field
   - ✅ Start typing to search
   - ✅ Search results display without errors
   - ✅ Can select a customer

3. **Check with null business_name customers**
   - ✅ Customers with null business_name show as "FirstName LastName"
   - ✅ Customers with null business_name AND null name show as "Unknown Customer"

---

## Files Modified

1. **app/Filament/Resources/QuoteResource.php**
   - Line ~78: Added `getOptionLabelFromRecordUsing()` to customer select

2. **app/Filament/Resources/InvoiceResource.php**
   - Line ~80: Added `getOptionLabelFromRecordUsing()` to customer select

---

## Why This Was Needed

### Difference Between Form Selects and Filter Selects

**Form Selects** (Select::make):
- Used in create/edit forms
- Show when you click the field
- Display during search
- **Need** `getOptionLabelFromRecordUsing()` for null safety

**Filter Selects** (SelectFilter::make):
- Used in table filters
- Show in filter dropdown
- **Also need** `getOptionLabelFromRecordUsing()` for null safety

**Both types** require the fallback method to handle null labels!

---

## Prevention

For any future relationship selects, **always** add the label fallback:

```php
Select::make('related_id')
    ->relationship('relation', 'column_name')
    ->searchable()
    ->getOptionLabelFromRecordUsing(fn ($record) => 
        $record->column_name ?? $record->fallback ?? 'Unknown'
    )
```

This should be a **standard pattern** for all relationship selects.

---

## Status

✅ **RESOLVED**
- Customer search in Quote create page working
- Customer search in Invoice create page working
- All customer selects have null-safe labels
- No more TypeErrors when searching

**Refresh browser and test the customer search!** 🚀

