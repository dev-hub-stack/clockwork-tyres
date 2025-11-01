# Tax Inclusive & Vehicle Info - Issues & Solutions

**Date**: November 1, 2025  
**Status**: ⚠️ NEEDS FIXING

---

## 🔍 Issues Identified

### Issue 1: Vehicle Info Not Passing to Invoices ❓
**Status**: May already be fixed in code, need to test

**Current State**:
- Consignments HAVE vehicle info (year, make, model, sub_model)
- ConsignmentInvoiceService IS passing vehicle info using accessors
- Recent invoices show NULL vehicle info

**Root Cause**:
- Old invoices created before fix
- OR issue with how form data is saved

**Code Check**:
```php
// In ConsignmentInvoiceService.php - Line 213-216
'vehicle_year' => $consignment->vehicle_year,      // ✅ Using accessor
'vehicle_make' => $consignment->vehicle_make,      // ✅ Using accessor  
'vehicle_model' => $consignment->vehicle_model,    // ✅ Using accessor
'vehicle_sub_model' => $consignment->vehicle_sub_model, // ✅ Using accessor
```

**Solution**: Test by recording a sale from a consignment with vehicle info

---

### Issue 2: Tax Inclusive Setting Not Used ❌
**Status**: CONFIRMED - Not being used in forms

**Current State**:
- Tax Setting: "Tax Inclusive by Default" = ✅ ENABLED (5%)
- ConsignmentForm: ❌ NOT using tax_inclusive
- QuoteResource: ❌ NOT using tax_inclusive
- InvoiceResource: ❌ NOT using tax_inclusive

**Impact**:
- All prices treated as tax-exclusive
- Tax always added on top (even if prices already include tax)
- Incorrect totals if using tax-inclusive pricing

**Example**:
```
Product Price: AED 350 (already includes 5% tax)

Current Behavior (WRONG):
Subtotal: AED 350
Tax (5%): AED 17.50
Total: AED 367.50  ← WRONG! Tax added twice

Correct Behavior (if tax_inclusive):
Subtotal: AED 333.33 (350 / 1.05)
Tax (5%): AED 16.67
Total: AED 350.00  ← CORRECT!
```

---

## ✅ Solutions

### Solution 1: Test Vehicle Info Passing

**Steps**:
1. Find consignment with vehicle info (CON-TEST-DEALER-1762002000)
2. Mark as SENT if not already
3. Record a sale
4. Check if created invoice has vehicle info

**Expected Result**:
Invoice should have:
- vehicle_year: 2024
- vehicle_make: Toyota
- vehicle_model: Camry  
- vehicle_sub_model: XLE

---

### Solution 2: Add Tax Inclusive to Forms

**Affected Files**:
1. `ConsignmentForm.php` - Add tax_inclusive per item
2. `QuoteResource.php` - Add tax_inclusive per item
3. `InvoiceResource.php` - Add tax_inclusive per item

**Implementation**:

#### Option A: Use Global Tax Setting (Recommended)
Get default from tax settings and apply to all items automatically.

```php
// In form, after loading variant
$taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
$taxInclusive = $taxSetting ? $taxSetting->tax_inclusive_default : true;

$set('tax_inclusive', $taxInclusive);
```

#### Option B: Add Toggle Per Item
Let user override per item.

```php
Toggle::make('tax_inclusive')
    ->label('Tax Inclusive')
    ->default(function () {
        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
        return $taxSetting ? $taxSetting->tax_inclusive_default : true;
    })
    ->helperText('Price includes tax (VAT 5%)')
```

#### Option C: Hidden Field (Simplest)
Use setting value silently without UI complexity.

```php
Hidden::make('tax_inclusive')
    ->default(function () {
        $taxSetting = \App\Modules\Settings\Models\TaxSetting::getDefault();
        return $taxSetting ? $taxSetting->tax_inclusive_default : true;
    })
```

---

## 📋 Implementation Plan

### Phase 1: Verify Vehicle Info (5 min)
- [x] Test current code by recording sale
- [ ] Check if invoice has vehicle info
- [ ] If yes, document as working
- [ ] If no, debug further

### Phase 2: Fix Tax Inclusive Setting (30 min)
- [ ] Update ConsignmentForm.php - add tax_inclusive field
- [ ] Update QuoteResource.php - add tax_inclusive field
- [ ] Update InvoiceResource.php - add tax_inclusive field
- [ ] Use TaxSetting::getDefault()->tax_inclusive_default
- [ ] Test with tax-inclusive prices

### Phase 3: Update Calculations (10 min)
- [ ] Ensure calculateTotals respects tax_inclusive
- [ ] Update total display to show correct amounts
- [ ] Test both tax-inclusive and tax-exclusive scenarios

### Phase 4: Documentation (5 min)
- [ ] Document tax_inclusive behavior
- [ ] Add examples to docs
- [ ] Update test files

---

## 🧪 Test Cases

### Test 1: Vehicle Info Passing
```
1. Open CON-TEST-DEALER-1762002000
2. Status: DELIVERED ✅
3. Vehicle: 2024 Toyota Camry XLE ✅
4. Record Sale → Create invoice
5. Check invoice vehicle fields
Expected: Should have 2024 Toyota Camry XLE
```

### Test 2: Tax Inclusive = TRUE
```
Settings: Tax Inclusive by Default = YES (5%)
Item Price: AED 350

Expected Calculation:
- Extract tax: 350 / 1.05 = AED 333.33
- Tax amount: AED 16.67
- Total: AED 350.00

Current (WRONG):
- Subtotal: AED 350.00
- Tax: AED 17.50
- Total: AED 367.50
```

### Test 3: Tax Inclusive = FALSE
```
Settings: Tax Inclusive by Default = NO (5%)
Item Price: AED 350

Expected Calculation:
- Subtotal: AED 350.00
- Tax: AED 17.50
- Total: AED 367.50
```

---

## 🔧 Files to Modify

### Vehicle Info (If Needed)
- None (code looks correct, just needs testing)

### Tax Inclusive Setting
1. `app/Filament/Resources/ConsignmentResource/Schemas/ConsignmentForm.php`
2. `app/Filament/Resources/QuoteResource.php`
3. `app/Filament/Resources/InvoiceResource.php`

---

## 📝 Current Tax Setting

```
Name: VAT
Rate: 5.00%
Tax Inclusive by Default: ✅ YES
Is Active: ✅ YES
```

This means users expect prices to INCLUDE 5% tax by default.

---

## ⚠️ Important Notes

1. **Backward Compatibility**: Existing items without tax_inclusive set should default to TRUE (based on current setting)

2. **Database**: ConsignmentItem already has `tax_inclusive` column ✅

3. **Service Layer**: ConsignmentInvoiceService already handles tax-inclusive calculations ✅

4. **Only Issue**: Forms not setting tax_inclusive when creating items ❌

---

## 🎯 Priority

**HIGH** - Tax calculations being wrong affects:
- Customer invoices (overcharging if tax-inclusive)
- Financial reports
- Customer trust

---

## ✅ Next Steps

1. Test vehicle info by recording sale
2. Add tax_inclusive to forms using TaxSetting default
3. Test both scenarios
4. Document results

---

**Status**: Ready to implement fixes
