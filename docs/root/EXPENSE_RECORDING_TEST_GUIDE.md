# Expense Recording - End-to-End Test Guide

## 📋 Testing Checklist

This guide will help you test the complete expense recording functionality to ensure everything works correctly.

---

## ✅ Test Scenario 1: Record Expenses on Completed Invoice

### Prerequisites:
1. Navigate to `/admin/invoices`
2. You need at least one invoice with status = **COMPLETED**
3. If you don't have one, create a quote, convert it to invoice, and mark it as COMPLETED

### Step-by-Step Test:

#### 1. **Verify "Record Expenses" Action is Visible**
- [ ] Go to the invoice list page
- [ ] Find a COMPLETED invoice
- [ ] Click on the invoice row to view details
- [ ] Verify you see **"Record Expenses & Calculate Profit"** action button (calculator icon, orange color)
- [ ] **IMPORTANT:** This button should ONLY appear for COMPLETED invoices

#### 2. **Open the Expense Recording Modal**
- [ ] Click the "Record Expenses & Calculate Profit" button
- [ ] Modal should open with title: "Record Expenses - Invoice {number}"
- [ ] Verify all 7 expense fields are visible:
  - Cost of Goods
  - Shipping Cost
  - Customs Duty
  - Delivery Fee
  - Installation Cost
  - Bank Fee
  - Credit Card Fee
- [ ] All fields should show "AED" prefix
- [ ] All fields should have helper text explaining what they are

#### 3. **Test Live Profit Preview**
- [ ] Enter test values in the expense fields:
  ```
  Cost of Goods: 800
  Shipping Cost: 150
  Duty Amount: 75
  Delivery Fee: 50
  Installation Cost: 0
  Bank Fee: 25
  Credit Card Fee: 25
  ```
- [ ] **VERIFY:** Profit Preview section updates automatically as you type
- [ ] **VERIFY:** Preview shows:
  - Revenue (invoice total)
  - Total Expenses (sum of all fields = 1,125)
  - Gross Profit (Revenue - Expenses)
  - Profit Margin (percentage)
- [ ] **VERIFY:** Profit shows in GREEN if positive, RED if negative

#### 4. **Save Expenses**
- [ ] Click "Save" button
- [ ] **VERIFY:** Success notification appears
- [ ] **VERIFY:** Notification shows:
  - "Expenses Recorded Successfully"
  - Shows Gross Profit amount
  - Shows Profit Margin percentage
- [ ] Modal closes automatically

#### 5. **Verify Invoice Table Display**
- [ ] Go back to invoice list (`/admin/invoices`)
- [ ] **VERIFY:** The invoice now shows in "Profit" column:
  - Gross Profit amount
  - **GREEN background** if profit is positive
  - **RED background** if profit is negative
- [ ] **VERIFY:** Hover over the profit cell to see tooltip
- [ ] **VERIFY:** Tooltip shows:
  - Profit Margin percentage
  - Total Expenses amount

#### 6. **Verify Profit Margin Column**
- [ ] **VERIFY:** "Margin" column shows percentage
- [ ] **VERIFY:** Color coding:
  - **GREEN** if margin > 20%
  - **YELLOW/ORANGE** if margin 10-20%
  - **RED** if margin < 10%

#### 7. **Verify Total Expenses Column**
- [ ] **VERIFY:** "Expenses" column shows total expenses amount
- [ ] **VERIFY:** Format: AED X,XXX.XX

---

## ✅ Test Scenario 2: Verify Data Persistence

### Step-by-Step Test:

#### 1. **Check Database Records**
- [ ] Open the invoice again (edit mode)
- [ ] Click "Record Expenses & Calculate Profit" again
- [ ] **VERIFY:** All expense fields are pre-filled with previously saved values
- [ ] **VERIFY:** Profit preview shows correct calculations

#### 2. **Update Expenses**
- [ ] Change one of the expense values (e.g., increase Cost of Goods by 100)
- [ ] **VERIFY:** Profit preview updates immediately
- [ ] Click Save
- [ ] **VERIFY:** New values are saved correctly
- [ ] **VERIFY:** Profit columns in table update with new calculations

#### 3. **Verify Audit Fields**
- [ ] Check `expenses_recorded_at` timestamp in database
- [ ] Check `expenses_recorded_by` user ID in database
- [ ] **Command to check:**
  ```bash
  php artisan tinker
  $invoice = App\Modules\Orders\Models\Order::find(YOUR_INVOICE_ID);
  echo "Recorded at: " . $invoice->expenses_recorded_at;
  echo "Recorded by: " . $invoice->expenseRecordedBy->name;
  ```

---

## ✅ Test Scenario 3: Edge Cases

### Test 1: Zero Expenses
- [ ] Record expenses with all fields = 0
- [ ] **VERIFY:** Profit = Total Revenue
- [ ] **VERIFY:** Margin = 100%

### Test 2: Expenses > Revenue (Loss)
- [ ] Record expenses higher than invoice total
- [ ] **VERIFY:** Profit shows as NEGATIVE
- [ ] **VERIFY:** Profit column is RED
- [ ] **VERIFY:** Margin shows as negative percentage

### Test 3: Action Not Visible on Non-Completed Invoices
- [ ] Find an invoice with status PENDING, PROCESSING, or SHIPPED
- [ ] **VERIFY:** "Record Expenses" action is NOT visible

### Test 4: Decimal Values
- [ ] Enter decimal values (e.g., 123.45)
- [ ] **VERIFY:** Calculations are accurate to 2 decimal places
- [ ] **VERIFY:** Display shows proper formatting

---

## ✅ Test Scenario 4: Performance Check

### Test Multiple Invoices:
- [ ] Record expenses on 3-5 different invoices
- [ ] Go to invoice list page
- [ ] **VERIFY:** Page loads quickly
- [ ] **VERIFY:** All profit columns show correct values
- [ ] **VERIFY:** No database errors in logs

---

## 🎯 Expected Results Summary

### ✅ What Should Work:
1. ✅ Modal opens with all 7 expense fields
2. ✅ Live profit calculation updates as you type
3. ✅ Success notification with profit details
4. ✅ Profit columns display in table with color coding
5. ✅ Tooltip shows margin and expenses on hover
6. ✅ Data persists and can be edited
7. ✅ Action only visible for COMPLETED invoices
8. ✅ Audit fields (expenses_recorded_at, expenses_recorded_by) are populated

### ❌ What Should NOT Happen:
1. ❌ Action should NOT appear on non-completed invoices
2. ❌ Should NOT be able to save without entering values
3. ❌ Calculations should NOT be incorrect
4. ❌ Should NOT see expenses table errors (table was dropped)
5. ❌ Should NOT see "Expense" model errors (model was removed)

---

## 🐛 Common Issues & Solutions

### Issue 1: "Record Expenses" button not visible
**Solution:** Check invoice status. Button only shows for COMPLETED invoices.

### Issue 2: Section class not found error
**Solution:** Ensure imports use `Filament\Schemas\Components\Section`, not `Filament\Forms\Components\Section`

### Issue 3: Expenses relationship error
**Solution:** Ensure Order model doesn't have `expenses()` relationship anymore (we dropped that table)

### Issue 4: Profit not displaying in table
**Solution:** Ensure profit columns (gross_profit, profit_margin, total_expenses) are added to table

---

## 📊 Sample Test Data

### Test Invoice 1: Profitable Sale
- **Invoice Total:** AED 1,500
- **Expenses:**
  - Cost of Goods: 800
  - Shipping: 150
  - Duty: 75
  - Delivery: 50
  - Installation: 0
  - Bank Fee: 25
  - Credit Card: 25
- **Expected Results:**
  - Total Expenses: 1,125
  - Gross Profit: 375
  - Profit Margin: 25%

### Test Invoice 2: Low Margin Sale
- **Invoice Total:** AED 1,000
- **Expenses:**
  - Cost of Goods: 900
  - Other: 50
- **Expected Results:**
  - Total Expenses: 950
  - Gross Profit: 50
  - Profit Margin: 5% (should show RED)

### Test Invoice 3: Loss
- **Invoice Total:** AED 500
- **Expenses:**
  - Cost of Goods: 600
- **Expected Results:**
  - Total Expenses: 600
  - Gross Profit: -100 (negative)
  - Profit Margin: -20% (should show RED)

---

## ✅ Final Verification

After all tests pass:
- [ ] Update todo list to mark test as complete
- [ ] Document any issues found
- [ ] Create git commit with test results
- [ ] Consider adding automated tests for this functionality

---

## 🎉 Success Criteria

**All tests pass when:**
1. ✅ Expense recording modal works perfectly
2. ✅ Live profit calculation is accurate
3. ✅ Data persists correctly in database
4. ✅ Profit display in table works with correct colors
5. ✅ Action visibility is correct (only COMPLETED invoices)
6. ✅ No errors in browser console or Laravel logs
7. ✅ Audit trail works (recorded_at, recorded_by)

---

**Ready to test?** Start with Test Scenario 1 and work through each checklist item! 🚀
