# Payment Recording Fix - Consignment Sales

## Problem Identified

**Date**: November 1, 2025  
**Reporter**: User  
**Issue**: When recording a sale from a consignment, the invoice shows payment status as "pending" even though payment information was provided during the sale process.

## Root Cause Analysis

### Issue 1: Missing Payment Record
The `ConsignmentInvoiceService::recordPayment()` method was **directly updating the Order record** instead of **creating a Payment record**.

**Before (Incorrect):**
```php
protected function recordPayment(Order $invoice, array $paymentData): void
{
    $invoice->update([
        'amount_paid' => $paymentData['amount'],
        'balance_due' => $invoice->total - $paymentData['amount'],
        'payment_status' => $paymentData['type'] === 'full' ? 'paid' : 'partial',
        'status' => $paymentData['type'] === 'full' ? 'paid' : 'partially_paid',
        'paid_at' => $paymentData['type'] === 'full' ? now() : null,
        'payment_method' => $paymentData['method'],
        'payment_history' => [...],
    ]);
}
```

**Problems:**
1. ❌ No Payment record created
2. ❌ Using wrong field name: `'status'` instead of `'order_status'`
3. ❌ Setting order_status to `'paid'` which is NOT a valid OrderStatus enum value
4. ❌ Manually calculating payment_status instead of letting Payment model handle it

### Issue 2: Wrong Status Field Name
The code was using `'status'` but the Order model uses `'order_status'` as the field name.

### Issue 3: Invalid Enum Value
The code was setting order_status to `'paid'`, but valid OrderStatus values are:
- `pending`
- `processing`
- `shipped`
- `completed`
- `cancelled`

The value `'paid'` is a **PaymentStatus**, not an OrderStatus!

## Solution Implemented

### Changes Made

**File**: `app/Modules/Consignments/Services/ConsignmentInvoiceService.php`

#### 1. Added Payment Model Import
```php
use App\Modules\Orders\Models\Payment;
```

#### 2. Fixed `recordPayment()` Method
**After (Correct):**
```php
protected function recordPayment(Order $invoice, array $paymentData): void
{
    // Create payment record - Payment model will automatically update order payment status
    Payment::create([
        'order_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'recorded_by' => auth()->id(),
        'amount' => $paymentData['amount'],
        'payment_method' => $paymentData['method'],
        'payment_date' => now(),
        'reference_number' => $paymentData['reference'] ?? null,
        'notes' => 'Payment recorded during consignment sale',
        'status' => 'completed',
    ]);
    
    // Update order status based on payment type
    $invoice->update([
        'order_status' => $paymentData['type'] === 'full' ? 'completed' : 'processing',
    ]);
    
    Log::info('Payment recorded for consignment invoice', [
        'invoice_id' => $invoice->id,
        'payment_amount' => $paymentData['amount'],
        'payment_type' => $paymentData['type'],
        'payment_method' => $paymentData['method'],
    ]);
}
```

#### 3. Fixed Initial Invoice Creation
Changed `'status'` to `'order_status'`:
```php
'order_status' => 'pending', // Correct field name
'payment_status' => 'pending', // Will be updated by Payment model
```

## How It Works Now

### Payment Record Creation Flow

1. **User Records Sale** from consignment with payment info
2. **Service Creates Invoice** with `order_status='pending'` and `payment_status='pending'`
3. **Service Creates Payment Record** via `Payment::create()`
4. **Payment Model's `created` Event Fires**:
   ```php
   static::created(function ($payment) {
       // Update order's paid_amount and outstanding_amount
       $payment->order->recalculatePaymentStatus();
   });
   ```
5. **Order Automatically Recalculates**:
   - Sums all payment amounts
   - Updates `amount_paid`
   - Updates `balance_due`
   - Updates `payment_status` (pending/partial/paid)
6. **Service Updates Order Status** to 'completed' or 'processing'

### Benefits of This Approach

✅ **Proper Payment Tracking**: Creates actual Payment records in the database  
✅ **Automatic Status Updates**: Payment model handles all calculations  
✅ **Payment History**: Full audit trail of all payments  
✅ **Consistent Behavior**: Same flow as manual payment recording in InvoiceResource  
✅ **Correct Enum Values**: Uses valid OrderStatus values  
✅ **Better Reporting**: Can query payments table for reports  

## Testing

### Test Script Created: `test_payment_fix.php`

Run to verify:
```bash
php test_payment_fix.php
```

The script will:
- Find invoices created from consignments
- Check if Payment records exist
- Verify payment_status is correct
- Show which invoices have the old bug vs the fix

### Expected Results After Fix

**For NEW consignment sales:**
- ✅ Payment record created in `payments` table
- ✅ `order_status` = 'completed' (for full payment) or 'processing' (for partial)
- ✅ `payment_status` = 'paid' (for full payment) or 'partial' (for partial)
- ✅ `amount_paid` = payment amount
- ✅ `balance_due` = total - payment amount

**For OLD consignment sales (before fix):**
- ⚠️ No Payment record in `payments` table
- ⚠️ May have incorrect status values

## Impact

### Files Changed
1. `app/Modules/Consignments/Services/ConsignmentInvoiceService.php`
   - Added Payment model import
   - Completely rewrote `recordPayment()` method
   - Fixed `'status'` to `'order_status'` in invoice creation

### Database Impact
- **Before**: Payment info stored directly in orders table only
- **After**: Proper Payment records created in payments table

### Backward Compatibility
- ✅ Existing invoices will continue to work
- ✅ Old payment data remains in orders table
- ✅ New sales will use proper Payment records
- ⚠️ Old invoices won't have Payment records (expected)

## Verification Checklist

After deploying this fix:

- [ ] Create new consignment sale with full payment
- [ ] Check invoice shows payment_status = 'paid' ✅
- [ ] Check Payment record exists in database ✅
- [ ] Check order_status = 'completed' ✅
- [ ] Create new consignment sale with partial payment
- [ ] Check invoice shows payment_status = 'partial' ✅
- [ ] Check Payment record exists ✅
- [ ] Check order_status = 'processing' ✅
- [ ] Verify old invoices still display correctly ✅

## Related Files

- `app/Modules/Orders/Models/Payment.php` - Payment model with auto-update logic
- `app/Modules/Orders/Models/Order.php` - Order model with payment relationships
- `app/Filament/Resources/InvoiceResource.php` - Reference implementation (already correct)

## Git Commit

```bash
git add app/Modules/Consignments/Services/ConsignmentInvoiceService.php
git add test_payment_fix.php
git add PAYMENT_RECORDING_FIX.md
git commit -m "fix: proper payment recording for consignment sales

- Create Payment records instead of directly updating order
- Fix order_status field name (was 'status')
- Use valid OrderStatus enum values ('completed' instead of 'paid')
- Let Payment model handle automatic status updates
- Add logging for payment recording
- Create test script to verify fix

Fixes issue where consignment sale invoices showed 'pending' payment status
even after payment was recorded during sale process."
```

## Status

✅ **Fixed and Ready for Testing**

Next: Test by creating a new consignment sale with payment.
