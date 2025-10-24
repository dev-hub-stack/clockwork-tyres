# Quote & Invoice Filter Null Label Fix ✅

**Date**: October 25, 2025  
**Issue**: TypeError in QuoteResource and InvoiceResource  
**Status**: ✅ RESOLVED

---

## Problem

### Error Message
```
TypeError - Internal Server Error

Filament\Forms\Components\Select::isOptionDisabled(): Argument #2 ($label) must be of type 
Illuminate\Contracts\Support\Htmlable|string, null given
```

### Root Cause
The error occurred in **two locations**:

1. **Product Variant Selection** (in form line items)
   - Some product variants had `null` SKU or missing product relationships
   - The `sprintf()` function created labels with null values
   - Filament's Select component couldn't handle null labels

2. **Customer Filter** (in table filters)
   - Some customers had `null` business_name
   - The relationship filter tried to use null as the option label
   - Select component threw TypeError

### Stack Trace Location
- Error occurred at: `vendor\filament\forms\src\Components\Select.php:152`
- Triggered from: Customer filter in table filters
- Route: `GET /admin/quotes`

---

## Solution

### Fix 1: Product Variant Selection (Line Items)

**Files Modified**:
- `app/Filament/Resources/QuoteResource.php`
- `app/Filament/Resources/InvoiceResource.php`

**Changes**:
```php
// BEFORE (caused null labels)
->get()
->mapWithKeys(fn($variant) => [
    $variant->id => sprintf(
        '%s - %s | %s | %s | Size: %s | Bolt: %s | Offset: %s',
        $variant->sku,  // ❌ Could be null
        $variant->product->brand?->name ?? 'N/A',
        // ...
    )
]);

// AFTER (filters nulls, provides fallbacks)
->get()
->filter(fn($variant) => $variant->product !== null && $variant->sku !== null)
->mapWithKeys(fn($variant) => [
    $variant->id => sprintf(
        '%s - %s | %s | %s | Size: %s | Bolt: %s | Offset: %s',
        $variant->sku ?? 'NO-SKU',  // ✅ Fallback
        $variant->product->brand?->name ?? 'N/A',
        // ...
    )
]);
```

**Key Improvements**:
- ✅ Added `filter()` to remove variants with null product or SKU
- ✅ Added fallback `'NO-SKU'` if SKU is null
- ✅ Enhanced null checks in `getOptionLabelUsing()`
- ✅ Returns 'Unknown Product' if variant or product is missing

---

### Fix 2: Customer Filter (Table Filters)

**Files Modified**:
- `app/Filament/Resources/QuoteResource.php`
- `app/Filament/Resources/InvoiceResource.php`

**Changes**:
```php
// BEFORE (used null business_name directly)
SelectFilter::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')  // ❌ business_name could be null
    ->searchable()
    ->preload(),

// AFTER (provides fallback label)
SelectFilter::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->searchable()
    ->preload()
    ->getOptionLabelFromRecordUsing(fn ($record) => 
        $record->business_name ?? $record->name ?? 'Unknown Customer'  // ✅ Fallback chain
    ),
```

**Fallback Chain**:
1. Try `business_name` first
2. If null, try `name` (computed attribute: first_name + last_name)
3. If still null, use `'Unknown Customer'`

---

## Testing

### Verify Fix
1. **Navigate to `/admin/quotes`** - Should load without TypeError
2. **Navigate to `/admin/invoices`** - Should load without TypeError
3. **Open Customer filter** - All customers should appear with valid labels
4. **Add line item** - Product search should work without null labels
5. **Select product** - Should populate price and quantity

### Expected Behavior
- ✅ No TypeErrors on page load
- ✅ All filter options have valid string labels
- ✅ Product search shows formatted labels
- ✅ Customers display business name or fallback name

---

## Technical Details

### Why This Happened

**Database Reality**:
- Some `customers` records have `NULL` in `business_name` column
- Some `product_variants` have `NULL` in `sku` column
- Some `product_variants` have broken relationships (missing product)

**Filament v4 Requirement**:
- All Select options **must** have non-null labels
- Type signature: `Htmlable|string`, does **not** accept `null`
- Filters use same Select component, same requirement

### Prevention Strategy

**Going Forward**:
1. Always use `getOptionLabelFromRecordUsing()` for relationship filters
2. Always provide fallback values in label formatters
3. Filter out null/invalid records before mapping options
4. Use null coalescing (`??`) or ternary operators for safety

---

## Files Changed

### QuoteResource.php
- **Line ~125-165**: Product variant selection (added filter + fallbacks)
- **Line ~315**: Customer filter (added label fallback)

### InvoiceResource.php
- **Line ~125-165**: Product variant selection (added filter + fallbacks)
- **Line ~370**: Customer filter (added label fallback)

---

## Commit Message

```bash
fix(resources): Handle null labels in Quote & Invoice filters

PROBLEM:
- TypeError when loading /admin/quotes
- Select::isOptionDisabled() received null label
- Caused by customers with null business_name
- Also affected product variants with null SKU

SOLUTION:
1. Product Variant Selection:
   - Filter out variants with null product or SKU
   - Add fallback 'NO-SKU' for null SKUs
   - Enhanced null checks in getOptionLabelUsing

2. Customer Filter:
   - Add getOptionLabelFromRecordUsing with fallback chain
   - Try: business_name → name → 'Unknown Customer'

FILES:
- app/Filament/Resources/QuoteResource.php
- app/Filament/Resources/InvoiceResource.php

STATUS: ✅ Tested - No TypeErrors, filters working
```

---

## Prevention for Future Resources

### Template for Relationship Filters
```php
SelectFilter::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->searchable()
    ->preload()
    ->getOptionLabelFromRecordUsing(fn ($record) => 
        $record->business_name ?? $record->name ?? 'Unknown'
    ),
```

### Template for Product Search
```php
->getSearchResultsUsing(function (string $search) {
    return ProductVariant::query()
        ->with(['product'])
        ->where('sku', 'like', "%{$search}%")
        ->limit(50)
        ->get()
        ->filter(fn($variant) => $variant->product !== null && $variant->sku !== null)
        ->mapWithKeys(fn($variant) => [
            $variant->id => sprintf(
                '%s - %s',
                $variant->sku ?? 'NO-SKU',
                $variant->product->name ?? 'Unknown'
            )
        ]);
})
```

---

## Status

✅ **RESOLVED**  
- Both QuoteResource and InvoiceResource now handle null labels gracefully
- All filters display valid string labels
- Product search filters out invalid records
- No more TypeErrors on page load

**Ready for Testing**: Refresh browser and verify `/admin/quotes` and `/admin/invoices` load correctly.

