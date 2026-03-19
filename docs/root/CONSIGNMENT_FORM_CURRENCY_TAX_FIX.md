# Consignment Form - Currency & Tax Settings Integration

## Summary
Updated ConsignmentForm to follow Quote/Invoice pattern for currency symbols and tax rates from system settings.

## Changes Made

### 1. Currency Symbol Integration
**Before:** Hardcoded `$` symbol
**After:** Dynamic currency from `CurrencySetting::getBase()->currency_symbol`

Applied to:
- Item price field in repeater
- Subtotal field
- Tax field  
- Total field
- Discount field
- Shipping cost field

**Fallback:** If no currency setting found, defaults to `AED`

### 2. Tax Rate Integration
**Added:** Hidden `tax_rate` field with default value from `TaxSetting::getDefault()->rate`

**Fallback:** If no tax setting found, defaults to `5%`

**Display:** Tax field shows helper text "Calculated from subtotal at {rate}%"

### 3. Backend Calculation Pattern
Following the same pattern as Quote/Invoice:
- Financial fields (subtotal, tax, total) are **disabled** and **dehydrated**
- Calculations performed by `Consignment::calculateTotals()` method on backend
- Formula: `total = subtotal + tax - discount + shipping_cost`
- Tax formula: `tax = subtotal * (tax_rate / 100)`

### 4. Fixed Product Search
Replaced simple relationship search with `getSearchResultsUsing()` to properly search across:
- SKU
- Product name
- Brand name
- Model name
- Finish name
- Size
- Bolt pattern
- Offset

This prevents SQL errors when searching on related tables.

### 5. Type Hint Fix
Removed type hints from `afterStateUpdated` closure to fix TypeError with Filament v4 Schema system.

**Before:**
```php
->afterStateUpdated(function (Set $set, Get $get, $state) {
```

**After:**
```php
->afterStateUpdated(function ($set, $get, $state) {
```

## Files Modified
- `app/Filament/Resources/ConsignmentResource/Schemas/ConsignmentForm.php`

## Testing Checklist
- [ ] Currency symbol displays correctly in form
- [ ] Tax rate pulls from settings
- [ ] Product search works across all fields
- [ ] Form submits without errors
- [ ] Totals calculated correctly on backend after save
- [ ] Default currency and tax rate work when settings are empty

## Next Steps
1. Test consignment creation through UI
2. Verify calculations match expected values
3. Implement ConsignmentsTable (list view)
4. Implement ConsignmentInfolist (view page)
5. Implement Record Sale action
6. Implement Record Return action

## Related Files
- `app/Modules/Consignments/Models/Consignment.php` - Contains `calculateTotals()` method
- `app/Modules/Settings/Models/CurrencySetting.php` - Currency settings
- `app/Modules/Settings/Models/TaxSetting.php` - Tax settings
- `app/Filament/Resources/QuoteResource.php` - Reference implementation
