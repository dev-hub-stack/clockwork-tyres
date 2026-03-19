# Invoice Actions Documentation

This document explains the three main invoice actions with their tooltips and behaviors.

## Overview

The invoice management system has three critical actions that affect order status and inventory:

1. **Start Processing** - Begin order fulfillment
2. **Cancel Order** - Reverse an order
3. **Delete** - Permanently remove record

## Detailed Action Descriptions

### 1. Start Processing ✅

**Button Label**: "Start Processing"  
**Tooltip**: "Begin order fulfillment and reserve inventory from warehouse"  
**Icon**: Cog (heroicon-o-cog-6-tooth)  
**Color**: Primary (Blue)

#### When Visible
- Only visible when order status is `pending`

#### What It Does
1. Changes order status from `pending` → `processing`
2. Allocates inventory from warehouses
3. Creates `OrderItemQuantity` records for each item
4. Reduces available inventory by allocated quantities
5. Shows stock availability check before processing

#### Database Changes
```php
// Order table
order_status: 'pending' → 'processing'

// OrderItemQuantity table (new records)
- order_id
- order_item_id
- product_variant_id
- warehouse_id
- quantity (allocated amount)

// ProductInventory table
quantity: (reduced by allocated amount)
```

#### Use Cases
- Customer has paid and order is confirmed
- Ready to pick, pack, and ship products
- Need to reserve inventory for this order

#### Important Notes
- Shows stock availability summary before processing
- Will warn if insufficient stock exists
- Inventory is reserved but not yet shipped
- Can be cancelled to return inventory

---

### 2. Cancel Order ❌

**Button Label**: "Cancel Order"  
**Tooltip**: "Cancel this order and return any allocated inventory to stock"  
**Icon**: X Mark (heroicon-o-x-mark)  
**Color**: Danger (Red)

#### When Visible
- Visible when order status is `pending` OR `processing`
- Hidden for completed, shipped, or cancelled orders

#### What It Does
1. Changes order status to `cancelled`
2. **If order was processing**:
   - Deallocates inventory (returns to warehouse)
   - Increments `ProductInventory.quantity` by allocated amounts
   - Deletes `OrderItemQuantity` records
   - Resets `allocated_quantity` to 0 for all items
3. **If order was pending**:
   - Simply marks as cancelled (no inventory to return)
4. Appends cancellation reason to order notes
5. Keeps the record for audit trail

#### Database Changes
```php
// Order table
order_status: any → 'cancelled'
notes: (appended cancellation reason)

// OrderItemQuantity table
- All records deleted for this order

// OrderItem table
allocated_quantity: any → 0

// ProductInventory table (if was processing)
quantity: (increased by previously allocated amount)
```

#### Modal Form
- **Cancellation Reason** (required): Text field explaining why order is cancelled

#### Use Cases
- Customer requested cancellation
- Payment failed or reversed
- Product out of stock permanently
- Order entered in error
- Customer wants refund

#### Important Notes
- **Keeps the record** - doesn't delete, only changes status
- Maintains audit trail for accounting
- Properly handles inventory deallocation
- Can see cancellation reason in order notes

---

### 3. Delete 🗑️

**Button Label**: "Delete"  
**Tooltip**: "Permanently delete this record (cannot be undone - use Cancel Order instead for legitimate orders)"  
**Icon**: Trash (default Filament)  
**Color**: Danger (Red)

#### When Visible
- Always visible (standard Filament action)
- Should rarely be used for real orders

#### What It Does
1. **Permanently removes** the order record from database
2. Deletes all related data:
   - Order items
   - Payments
   - Notes
   - Attachments
3. Cannot be recovered
4. No automatic inventory handling

#### Database Changes
```php
// All records deleted from:
- orders table
- order_items table
- payments table
- order_item_quantities table (if any)
- Any file uploads/attachments
```

#### Use Cases
- Test orders during development
- Duplicate entries
- Data cleanup
- Orders entered completely incorrectly

#### ⚠️ Important Warnings
- **Permanent action** - cannot be undone
- **No inventory handling** - may leave inventory in inconsistent state
- **Loses audit trail** - accounting/tax issues
- **Not recommended** for legitimate customer orders

#### Best Practice
**Use "Cancel Order" instead of "Delete" for real orders!**
- Cancel Order keeps the record
- Cancel Order handles inventory properly
- Cancel Order maintains audit trail

---

## Other Invoice Actions

### Record Payment 💰
**Tooltip**: "Record a payment received for this invoice"  
**Visible**: When invoice is not fully paid

### Record Expenses & Calculate Profit 📊
**Tooltip**: "Record costs and expenses to calculate profit margin"  
**Visible**: When payment is paid/partial or order is completed

### Preview 👁️
**Tooltip**: "Preview invoice document"  
**Visible**: Always available

### Edit ✏️
**Tooltip**: "Edit invoice details"  
**Visible**: Always available

---

## Testing Guide

### Test Data Available
Based on `test_invoice_actions.php` output:

```
Total Invoices: 29

By Order Status:
  pending    : 24 invoices (82.8%)
  processing :  2 invoices (6.9%)
  completed  :  3 invoices (10.3%)

Available for Testing:
✅ 24 invoices can test "Start Processing"
✅ 26 invoices can test "Cancel Order"
✅ 2 processing invoices have allocated inventory
```

### Test Scenarios

#### Scenario 1: Start Processing
1. Find invoice with status = `pending`
2. Click "Start Processing" (hover to see tooltip)
3. Review stock availability check
4. Confirm action
5. Verify:
   - Status changed to `processing`
   - Inventory allocated
   - `OrderItemQuantity` records created

#### Scenario 2: Cancel Order (Pending)
1. Find invoice with status = `pending`
2. Click "Cancel Order" (hover to see tooltip)
3. Enter cancellation reason
4. Confirm action
5. Verify:
   - Status changed to `cancelled`
   - No inventory changes (wasn't allocated yet)

#### Scenario 3: Cancel Order (Processing)
1. Find invoice with status = `processing`
2. Note allocated inventory amounts
3. Click "Cancel Order" (hover to see tooltip)
4. Enter cancellation reason
5. Confirm action
6. Verify:
   - Status changed to `cancelled`
   - Inventory returned to warehouse
   - `allocated_quantity` reset to 0
   - `OrderItemQuantity` records deleted

#### Scenario 4: Delete (Test Data Only)
1. Find a test invoice (e.g., INV-TEST-*)
2. Click "Delete" (hover to see warning tooltip)
3. Confirm deletion
4. Verify:
   - Record completely removed
   - Cannot be recovered

---

## Database Queries for Verification

### Check Order Status
```sql
SELECT id, order_number, order_status, payment_status 
FROM orders 
WHERE document_type = 'invoice' 
ORDER BY created_at DESC 
LIMIT 10;
```

### Check Allocated Inventory
```sql
SELECT o.order_number, oi.product_name, oi.quantity, oi.allocated_quantity, w.name as warehouse
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
LEFT JOIN warehouses w ON oi.warehouse_id = w.id
WHERE o.order_status = 'processing'
AND o.document_type = 'invoice';
```

### Check OrderItemQuantity Records
```sql
SELECT oiq.*, o.order_number, oi.product_name, w.name as warehouse
FROM order_item_quantities oiq
JOIN orders o ON oiq.order_id = o.id
JOIN order_items oi ON oiq.order_item_id = oi.id
JOIN warehouses w ON oiq.warehouse_id = w.id
WHERE o.document_type = 'invoice'
ORDER BY oiq.created_at DESC;
```

### Find Cancelled Orders with Reasons
```sql
SELECT id, order_number, order_status, notes
FROM orders
WHERE order_status = 'cancelled'
AND document_type = 'invoice'
ORDER BY updated_at DESC;
```

---

## Code References

### InvoiceResource.php
- **Start Processing**: Lines ~661-715
- **Cancel Order**: Lines ~949-996
- **Delete**: Line ~1000

### Key Models
- `App\Modules\Orders\Models\Order`
- `App\Modules\Orders\Models\OrderItem`
- `App\Modules\Orders\Models\OrderItemQuantity`
- `App\Modules\Products\Models\ProductInventory`

### Enums
- `App\Modules\Orders\Enums\OrderStatus`
  - PENDING = 'pending'
  - PROCESSING = 'processing'
  - SHIPPED = 'shipped'
  - COMPLETED = 'completed'
  - CANCELLED = 'cancelled'

---

## Common Issues & Solutions

### Issue: "Start Processing" button not visible
**Solution**: Check that order status is `pending`. Button only shows for pending orders.

### Issue: Inventory not deallocated after cancelling
**Solution**: Check that order was in `processing` status. Pending orders have no inventory to deallocate.

### Issue: Can't cancel completed order
**Solution**: By design - completed orders cannot be cancelled. This maintains data integrity for accounting.

### Issue: Deleted order but inventory is wrong
**Solution**: Don't use Delete for real orders! Use Cancel Order instead, which properly handles inventory. You may need to manually adjust inventory now.

---

## Best Practices

1. ✅ **Use Cancel Order** for legitimate orders that won't be fulfilled
2. ✅ **Use Start Processing** only when order is confirmed and ready
3. ✅ **Always provide cancellation reason** for audit trail
4. ✅ **Review stock availability** before starting processing
5. ❌ **Don't use Delete** for real customer orders
6. ❌ **Don't start processing** if insufficient stock
7. ❌ **Don't cancel** without a valid reason

---

## Summary Table

| Action | Status Change | Inventory | Reversible | Audit Trail | Use For |
|--------|--------------|-----------|------------|-------------|---------|
| **Start Processing** | pending → processing | ✅ Allocates | ✅ Can cancel | ✅ Yes | Confirmed orders |
| **Cancel Order** | any → cancelled | ✅ Deallocates | ✅ Keeps record | ✅ Yes | Order cancellations |
| **Delete** | ❌ Removes record | ❌ None | ❌ Permanent | ❌ Lost | Test data only |

---

*Last Updated: November 1, 2025*
*Test File: `test_invoice_actions.php`*
