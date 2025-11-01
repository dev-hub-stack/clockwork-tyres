# Invoice Actions Test Suite

This directory contains comprehensive tests to validate all invoice actions in the Reporting CRM system.

## Test Files

### 1. **test_invoice_actions.php**
Quick database inspection script that shows:
- Total invoices in database
- Breakdown by order status and payment status
- Which actions are available for each invoice
- Inventory allocation status
- Testing recommendations

**Usage:**
```bash
php test_invoice_actions.php
```

**Output:** Summary of your database state and what you can test

---

### 2. **test_start_processing_action.php**
Validates the "Start Processing" action by:
- Finding a pending invoice
- Checking stock availability
- Executing the action (changes status to processing)
- Verifying inventory allocation
- Verifying OrderItemQuantity records created

**Usage:**
```bash
php test_start_processing_action.php
```

**What it tests:**
- ✅ Order status changes from `pending` → `processing`
- ✅ Inventory is allocated from warehouses
- ✅ `OrderItemQuantity` records are created
- ✅ `allocated_quantity` is set on order items
- ✅ Warehouse inventory is reduced by allocated amounts

**Requirements:** At least one pending invoice in database

---

### 3. **test_cancel_order_action.php**
Validates the "Cancel Order" action by:
- Finding a processing or pending invoice
- Recording current inventory levels
- Executing the action (changes status to cancelled)
- Verifying inventory deallocation (if was processing)
- Verifying OrderItemQuantity records deleted
- Verifying allocated_quantity reset to 0
- Verifying cancellation reason recorded

**Usage:**
```bash
php test_cancel_order_action.php
```

**What it tests:**
- ✅ Order status changes to `cancelled`
- ✅ Inventory is returned to warehouse (if was processing)
- ✅ `OrderItemQuantity` records are deleted
- ✅ `allocated_quantity` is reset to 0
- ✅ Cancellation reason is recorded in notes
- ✅ Audit trail is maintained (record not deleted)

**Requirements:** At least one pending or processing invoice

---

### 4. **test_delete_action.php**
Validates the "Delete" action by:
- Creating or finding a test invoice
- Recording all related data
- Executing the action (permanently deletes)
- Verifying all related records removed
- Warning about inventory handling

**Usage:**
```bash
php test_delete_action.php
```

**What it tests:**
- ✅ Invoice is permanently removed
- ✅ Order items are deleted
- ✅ Payments are deleted
- ✅ OrderItemQuantity records are deleted
- ✅ Data cannot be recovered
- ⚠️ No automatic inventory handling

**Safety:** Only deletes invoices with "TEST" in order number or creates new test invoice

---

### 5. **test_all_invoice_actions.php**
Master test runner that executes all tests in sequence:
1. Start Processing test
2. Cancel Order test
3. Delete test (optional)

**Usage:**
```bash
# Run without delete test (recommended)
php test_all_invoice_actions.php

# Run with delete test
php test_all_invoice_actions.php --with-delete
```

**What it does:**
- Runs all tests automatically
- Shows consolidated results
- Provides final summary with pass/fail counts
- Gives recommendations for next steps

---

## Quick Start

### Option 1: Run Individual Tests

```bash
# 1. Check your database state
php test_invoice_actions.php

# 2. Test Start Processing (on a pending invoice)
php test_start_processing_action.php

# 3. Test Cancel Order (on a processing invoice)
php test_cancel_order_action.php

# 4. Test Delete (creates test invoice - OPTIONAL)
php test_delete_action.php
```

### Option 2: Run All Tests at Once

```bash
# Run all tests except delete
php test_all_invoice_actions.php

# Run all tests including delete
php test_all_invoice_actions.php --with-delete
```

---

## Expected Results

### Database Summary (test_invoice_actions.php)
```
Total Invoices: 29

By Order Status:
  pending    : 24 invoices (82.8%)
  processing :  2 invoices (6.9%)
  completed  :  3 invoices (10.3%)

✅ 24 invoices can test "Start Processing"
✅ 26 invoices can test "Cancel Order"
```

### Start Processing Test
```
✅ Order Status: PASS (pending → processing)
✅ Inventory Allocation: PASS (inventory allocated)
✅ OrderItemQuantity Records: PASS (records created)

OVERALL: ✅ ALL TESTS PASSED
```

### Cancel Order Test
```
✅ Order Status: PASS (→ cancelled)
✅ Inventory Deallocation: PASS (inventory returned)
✅ Allocation Records Deleted: PASS (records removed)
✅ Cancellation Reason Recorded: PASS (in notes)

OVERALL: ✅ ALL TESTS PASSED
```

### Delete Test
```
✅ Invoice Deleted: PASS (record removed)
✅ Order Items Deleted: PASS (items removed)
✅ Payments Deleted: PASS (payments removed)
✅ Allocations Deleted: PASS (allocations removed)

OVERALL: ✅ ALL TESTS PASSED
⚠️  Remember: Only use DELETE for test data!
```

---

## Test Coverage

| Action | Status Change | Inventory | Records | Audit Trail | Test File |
|--------|--------------|-----------|---------|-------------|-----------|
| **Start Processing** | pending → processing | Allocates | Creates OrderItemQuantity | ✅ Kept | test_start_processing_action.php |
| **Cancel Order** | → cancelled | Deallocates | Deletes OrderItemQuantity | ✅ Kept | test_cancel_order_action.php |
| **Delete** | Removed | None | All deleted | ❌ Lost | test_delete_action.php |

---

## Troubleshooting

### Test Fails: "No pending invoices found"
**Solution:** Create a pending invoice through the Filament UI first
```
Go to: /admin/invoices → New Invoice
Create invoice with status = pending
Run test again
```

### Test Fails: "Inventory not allocated"
**Possible causes:**
1. OrderObserver not registered
   - Check `App\Providers\AppServiceProvider::boot()`
   - Should have: `Order::observe(OrderObserver::class);`

2. Observer not working
   - Check `App\Observers\OrderObserver::updated()` method
   - Should handle status change to 'processing'

3. No warehouse assigned
   - Check invoice items have `warehouse_id` set
   - Check warehouse has inventory records

### Test Fails: "Inventory not deallocated"
**Possible causes:**
1. Cancel Order action not working
   - Check InvoiceResource.php cancelOrder action
   - Should increment inventory and delete OrderItemQuantity records

2. Inventory records missing
   - Check ProductInventory table has records
   - Check warehouse_id and product_variant_id match

### Test Fails: "Records not deleted"
**Possible causes:**
1. Database relationships not set up
   - Check Order model has `onDelete('cascade')` for relationships
   - Check database foreign keys

2. Soft deletes enabled
   - Check if Order model uses SoftDeletes trait
   - Records may be soft deleted (marked deleted_at) instead of hard deleted

---

## Important Notes

### About Delete Action
⚠️ **DELETE should ONLY be used for test data!**

For real customer orders, always use **"Cancel Order"** instead:
- ✅ Cancel Order maintains audit trail
- ✅ Cancel Order handles inventory correctly
- ✅ Cancel Order records cancellation reason
- ❌ Delete loses all history
- ❌ Delete doesn't handle inventory

### Test Data
These tests will:
- Modify real invoices in your database
- Change order statuses
- Allocate/deallocate inventory
- Create OrderItemQuantity records

**Recommendation:** Run tests on development/staging database first!

### Inventory Impact
- **Start Processing:** Reduces warehouse inventory (allocated)
- **Cancel Order:** Returns inventory to warehouse (deallocated)
- **Delete:** No inventory handling (⚠️ may leave inventory incorrect)

---

## Next Steps After Testing

1. **If all tests pass:**
   - ✅ Actions are working correctly
   - Test in Filament UI to verify tooltips
   - Train team on proper action usage
   - Deploy to production

2. **If some tests fail:**
   - Review test output for specific errors
   - Check troubleshooting section above
   - Fix issues in code
   - Re-run tests

3. **Documentation:**
   - Read `INVOICE_ACTIONS_DOCUMENTATION.md` for complete details
   - Read `INVOICE_ACTIONS_SUMMARY.md` for quick reference
   - Share with team members

---

## Test Output Examples

### Successful Start Processing
```
📋 TEST INVOICE SELECTED
Invoice Number: INV-2025-0038
Current Status: pending
Items: 2

📦 STEP 1: CHECK STOCK AVAILABILITY
Item: Product A
  Available Stock: 10
  Status: ✅ IN STOCK

🔄 STEP 3: EXECUTE START PROCESSING ACTION
✅ Order status updated successfully

🔍 STEP 4: VERIFY INVENTORY ALLOCATION
✅ Allocation successful
✅ Inventory reduced correctly

OVERALL: ✅ ALL TESTS PASSED
```

### Successful Cancel Order
```
📋 TEST INVOICE SELECTED
Invoice Number: INV-2025-0038
Current Status: processing
Items: 2

📦 STEP 1: RECORD CURRENT STATE
Item: Product A
  Allocated Quantity: 2
  Current Warehouse Stock: 8
  Expected After Cancel: 10

🔄 STEP 3: EXECUTE CANCEL ORDER ACTION
  ✅ Returned 2 of 'Product A' to stock
✅ Order cancelled successfully

🔍 STEP 4: VERIFY INVENTORY DEALLOCATION
✅ Allocated quantity reset
✅ Inventory returned correctly

OVERALL: ✅ ALL TESTS PASSED
```

---

## Contact & Support

If tests fail or you encounter issues:
1. Review the test output carefully
2. Check the troubleshooting section
3. Review the code changes in InvoiceResource.php
4. Check database relationships and observers

---

*Last Updated: November 1, 2025*
*Test Suite Version: 1.0*
