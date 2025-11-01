# System Settings Usage - Issues & Fixes

**Date**: November 1, 2025  
**Status**: ⚠️ NEEDS FIXING

---

## 🔍 Audit Results

### Settings Available ✅
1. **Tax Setting**: VAT 5%, Tax Inclusive = YES
2. **Currency Setting**: AED (but says is_base = NO ⚠️)
3. **Company Branding**: Tunerstop Wheels Inc. (Active)

---

## ❌ Issues Found

### 1. Currency Setting - Not Used Consistently
**Files with Hardcoded 'AED':**
- ✅ ConsignmentForm.php - Uses CurrencySetting ✅
- ❌ QuoteResource.php - Hardcodes 'AED'
- ❌ InvoiceResource.php - Hardcodes 'AED'
- ❌ ConsignmentInvoiceService.php - Hardcodes 'AED'
- ✅ ConsignmentsTable.php - Uses CurrencySetting ✅

**Impact**: If user changes currency in settings, some places won't update

---

### 2. Tax Setting - Partially Used
**Usage Status:**
- ✅ ConsignmentForm.php - Uses TaxSetting ✅
- ✅ QuoteResource.php - Uses TaxSetting ✅
- ✅ InvoiceResource.php - Uses TaxSetting ✅
- ❌ ConsignmentInvoiceService.php - Does NOT use TaxSetting
- ✅ ConsignmentsTable.php - Uses TaxSetting for rate ✅

**Impact**: Service layer may not respect tax setting changes

---

### 3. Company Branding - Partially Used
**Usage Status:**
- ❌ ConsignmentForm.php - Does NOT use Company Branding
- ✅ QuoteResource.php - Uses Company Branding ✅
- ✅ InvoiceResource.php - Uses Company Branding ✅
- ❌ ConsignmentInvoiceService.php - Does NOT use (but should for invoices)
- ✅ ConsignmentsTable.php - Uses Company Branding (for preview) ✅

**Impact**: Forms won't show company info, service creates invoices without branding

---

### 4. Currency Setting Issue
**Problem**: CurrencySetting shows `is_base = NO` but it should be YES

**Database Check Needed:**
```sql
SELECT * FROM currency_settings WHERE currency_code = 'AED';
-- Check if is_base_currency = 1
```

---

## ✅ Fixes Needed

### Fix 1: Update QuoteResource.php
Replace hardcoded 'AED' with CurrencySetting

**Locations to fix:**
- Line ~160: TextInput prefix
- Line ~170: Display fields
- Line ~440: Shipping field
- Anywhere else 'AED' appears

### Fix 2: Update InvoiceResource.php
Replace hardcoded 'AED' with CurrencySetting

**Locations to fix:**
- Similar to QuoteResource
- All currency displays

### Fix 3: Update ConsignmentInvoiceService.php
1. Use TaxSetting::getDefault() for tax rate
2. Use CurrencySetting::getBase() for currency
3. Use CompanyBranding::getActive() for company info in invoices

### Fix 4: Fix Currency Base Setting
Make sure AED is marked as base currency in database

---

## 🎯 Implementation Priority

### HIGH Priority
1. ✅ Tax Inclusive - DONE (all forms updated)
2. ❌ Currency Setting - NEEDS FIX (3 files)
3. ❌ Tax Rate in Service - NEEDS FIX (1 file)

### MEDIUM Priority
4. ❌ Company Branding in Service - NEEDS FIX (1 file)
5. ❌ Currency Base Flag - NEEDS FIX (database)

### LOW Priority
6. Consistency checks
7. Additional modules

---

## 📋 Files to Modify

1. `app/Filament/Resources/QuoteResource.php` - Replace 'AED' with CurrencySetting
2. `app/Filament/Resources/InvoiceResource.php` - Replace 'AED' with CurrencySetting
3. `app/Modules/Consignments/Services/ConsignmentInvoiceService.php` - Use all settings

---

## 🔧 Code Examples

### Good Example (ConsignmentForm.php)
```php
->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
```

### Bad Example (QuoteResource.php)
```php
->prefix('AED')  // ❌ Hardcoded!
```

### Should Be
```php
->prefix(fn () => \App\Modules\Settings\Models\CurrencySetting::getBase()?->currency_symbol ?? 'AED')
```

---

## ✅ Next Steps

1. Fix QuoteResource.php currency usage
2. Fix InvoiceResource.php currency usage
3. Fix ConsignmentInvoiceService.php to use all settings
4. Fix currency_settings table to mark AED as base
5. Test all modules
6. Commit changes

---

**Status**: Ready to implement fixes
