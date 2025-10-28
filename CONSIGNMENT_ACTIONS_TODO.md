# Consignment Module - Action Buttons Implementation TODO

## Overview
Based on the old Voyager system at `C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\consignments`, we need to implement three critical actions in the new Filament system:

1. **Record Sale** - Mark items as sold and optionally create invoice
2. **Record Return** - Mark items as returned and optionally update inventory
3. **Convert to Invoice** - Convert entire consignment to an invoice

## Backend Status ✅
All backend service methods are **ALREADY IMPLEMENTED** in `ConsignmentService.php`:
- ✅ `recordSale(Consignment $consignment, array $soldItems, bool $createInvoice = false): ?Order`
- ✅ `recordReturn(Consignment $consignment, array $returnedItems, bool $updateInventory = false): void`
- ✅ `createInvoiceForSoldItems(Consignment $consignment): Order` (line ~266)

## Frontend TODO - Filament Actions

### 📋 TODO 1: Implement Record Sale Action

**Location:** `app/Filament/Resources/ConsignmentResource/Actions/RecordSaleAction.php`

**Requirements:**
- [ ] Create modal action with form
- [ ] Display all consignment items with:
  - [ ] Product name, SKU, brand
  - [ ] Quantity sent
  - [ ] Quantity already sold
  - [ ] **Available to sell** (calculated: sent - sold - returned)
- [ ] For each item, allow user to:
  - [ ] Select checkbox to include in sale
  - [ ] Input quantity to sell (max: available quantity)
  - [ ] Input actual sale price (default: consignment price)
- [ ] Add checkbox: "Create Invoice Automatically"
- [ ] Validation:
  - [ ] Quantity sold ≤ available quantity
  - [ ] At least one item must be selected
  - [ ] Sale price must be > 0
- [ ] On submit:
  - [ ] Call `ConsignmentService->recordSale()`
  - [ ] Show success notification
  - [ ] Redirect to invoice if created
  - [ ] Refresh consignment view

**Service Call Example:**
```php
$soldItems = [
    [
        'item_id' => 1,
        'quantity_sold' => 2,
        'actual_sale_price' => 150.00,
    ],
    // ...
];

$invoice = $consignmentService->recordSale($consignment, $soldItems, $createInvoice = true);
```

**Status Checks:**
- Only enable if `$consignment->canRecordSale()` returns true
- Disable if status is CANCELLED, RETURNED, or INVOICED_IN_FULL

---

### 📋 TODO 2: Implement Record Return Action

**Location:** `app/Filament/Resources/ConsignmentResource/Actions/RecordReturnAction.php`

**Requirements:**
- [ ] Create modal action with form
- [ ] Display all consignment items with:
  - [ ] Product name, SKU, brand
  - [ ] Quantity sold
  - [ ] Quantity already returned
  - [ ] **Available to return** (calculated: sold - returned)
- [ ] For each item, allow user to:
  - [ ] Select checkbox to include in return
  - [ ] Input quantity to return (max: available to return)
  - [ ] Optional: Return reason
- [ ] Add checkbox: "Update Inventory (add back to warehouse)"
- [ ] Validation:
  - [ ] Quantity returned ≤ sold quantity
  - [ ] At least one item must be selected
- [ ] On submit:
  - [ ] Call `ConsignmentService->recordReturn()`
  - [ ] Show success notification
  - [ ] Refresh consignment view

**Service Call Example:**
```php
$returnedItems = [
    [
        'item_id' => 1,
        'quantity_returned' => 1,
        'reason' => 'Customer changed mind',
    ],
    // ...
];

$consignmentService->recordReturn($consignment, $returnedItems, $updateInventory = true);
```

**Status Checks:**
- Only enable if `$consignment->canRecordReturn()` returns true
- Only show items that have been sold (quantity_sold > 0)

---

### 📋 TODO 3: Implement Convert to Invoice Action

**Location:** `app/Filament/Resources/ConsignmentResource/Actions/ConvertToInvoiceAction.php`

**Requirements:**
- [ ] Create modal action with confirmation
- [ ] Display summary:
  - [ ] Total items sold: X
  - [ ] Total value: $XXX.XX
  - [ ] Customer name
  - [ ] Consignment number
- [ ] Show warning if items_sold_count < items_sent_count
  - "⚠️ Not all items are sold. Only sold items will be included in the invoice."
- [ ] Add optional fields:
  - [ ] Invoice date (default: today)
  - [ ] Payment terms
  - [ ] Additional notes
- [ ] On submit:
  - [ ] Call `ConsignmentService->createInvoiceForSoldItems()`
  - [ ] Show success notification with invoice number
  - [ ] Redirect to created invoice
  - [ ] Update consignment status

**Service Call Example:**
```php
$invoice = $consignmentService->createInvoiceForSoldItems($consignment);

// Or use recordSale with createInvoice flag:
$invoice = $consignmentService->recordSale($consignment, $allSoldItems, $createInvoice = true);
```

**Status Checks:**
- Only enable if `items_sold_count > 0`
- Disable if already converted (status = INVOICED_IN_FULL or converted_invoice_id exists)
- Show "Already Converted" badge if invoice exists

---

### 📋 TODO 4: Implement ConsignmentsTable (List View)

**Location:** `app/Filament/Resources/ConsignmentResource/Tables/ConsignmentsTable.php`

**Columns Required:**
- [ ] Consignment Number (searchable, sortable)
- [ ] Customer (relationship, searchable)
- [ ] Status (badge with colors from ConsignmentStatus enum)
- [ ] Items Sent/Sold/Returned (format: "10 / 7 / 1")
- [ ] Total Value (currency formatted)
- [ ] Issue Date (date formatted)
- [ ] Created At (date formatted, sortable)

**Filters Required:**
- [ ] Status filter (all statuses from enum)
- [ ] Customer filter (searchable select)
- [ ] Date range filter (issue_date)
- [ ] Warehouse filter

**Bulk Actions Required:**
- [ ] Export selected
- [ ] Mark as Sent (batch)
- [ ] Cancel selected (with confirmation)

**Row Actions Required:**
- [ ] View (always visible)
- [ ] Edit (if not invoiced/cancelled)
- [ ] Record Sale (if canRecordSale())
- [ ] Record Return (if canRecordReturn())
- [ ] Convert to Invoice (if has sold items)
- [ ] Print PDF
- [ ] Delete (soft delete, with confirmation)

---

### 📋 TODO 5: Implement ConsignmentInfolist (View Page)

**Location:** `app/Filament/Resources/ConsignmentResource/Schemas/ConsignmentInfolist.php`

**Sections Required:**

#### Section 1: Consignment Information
- [ ] Consignment Number
- [ ] Status (badge)
- [ ] Customer (with link)
- [ ] Warehouse (with link)
- [ ] Representative (with link)
- [ ] Issue Date
- [ ] Expected Return Date
- [ ] Tracking Number (if sent)

#### Section 2: Vehicle Information (if present)
- [ ] Year
- [ ] Make
- [ ] Model
- [ ] Sub Model

#### Section 3: Items Table
- [ ] Product name + SKU
- [ ] Quantity sent
- [ ] Quantity sold
- [ ] Quantity returned
- [ ] Available quantity
- [ ] Price per unit
- [ ] Line total
- [ ] Status badge

#### Section 4: Financial Summary
- [ ] Subtotal
- [ ] Tax (with rate)
- [ ] Discount
- [ ] Shipping Cost
- [ ] **Total**

#### Section 5: Statistics Cards
- [ ] Items Sent (blue)
- [ ] Items Sold (green)
- [ ] Items Returned (orange)
- [ ] Available (gray)

#### Section 6: History Timeline
- [ ] Show all ConsignmentHistory records
- [ ] Display: action, description, performed_by, created_at
- [ ] Use Filament's timeline component

#### Section 7: Notes
- [ ] Customer Notes (if present)
- [ ] Internal Notes (if present)

---

### 📋 TODO 6: Add Actions to ConsignmentResource

**Location:** `app/Filament/Resources/ConsignmentResource.php`

**Header Actions (on view page):**
```php
public static function getHeaderActions(): array
{
    return [
        Actions\EditAction::make(),
        
        // Record Sale
        RecordSaleAction::make()
            ->visible(fn (Consignment $record) => $record->canRecordSale()),
        
        // Record Return
        RecordReturnAction::make()
            ->visible(fn (Consignment $record) => $record->canRecordReturn()),
        
        // Convert to Invoice
        ConvertToInvoiceAction::make()
            ->visible(fn (Consignment $record) => $record->items_sold_count > 0 && !$record->converted_invoice_id),
        
        // Mark as Sent
        Actions\Action::make('mark_sent')
            ->visible(fn (Consignment $record) => $record->status === ConsignmentStatus::DRAFT),
        
        // Cancel
        Actions\Action::make('cancel')
            ->requiresConfirmation()
            ->visible(fn (Consignment $record) => !in_array($record->status, [
                ConsignmentStatus::CANCELLED,
                ConsignmentStatus::INVOICED_IN_FULL,
            ])),
        
        // Print PDF
        Actions\Action::make('print')
            ->url(fn (Consignment $record) => route('consignments.pdf', $record))
            ->openUrlInNewTab(),
    ];
}
```

---

### 📋 TODO 7: Create PDF Template

**Location:** `resources/views/consignments/pdf.blade.php`

**Requirements:**
- [ ] Use existing template from old system: `C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\consignments\professional-consignment.blade.php`
- [ ] Update to use:
  - [ ] Logo from CompanyBranding settings (like Quote/Invoice)
  - [ ] Currency from CurrencySetting
  - [ ] Tax from TaxSetting
- [ ] Sections:
  - [ ] Company header with logo
  - [ ] Consignment details
  - [ ] Customer details
  - [ ] Items table
  - [ ] Financial totals
  - [ ] Terms & conditions
  - [ ] Footer

**Controller Method:**
```php
// app/Http/Controllers/ConsignmentController.php
public function downloadPDF(Consignment $consignment)
{
    $pdf = PDF::loadView('consignments.pdf', [
        'consignment' => $consignment->load(['customer', 'items', 'warehouse']),
        'companyBranding' => CompanyBranding::first(),
    ]);
    
    return $pdf->download("consignment-{$consignment->consignment_number}.pdf");
}
```

---

## Priority Order

### Phase 1: Basic Viewing (Week 1)
1. ✅ ConsignmentForm - Already complete
2. ⚠️ ConsignmentsTable - **HIGH PRIORITY**
3. ⚠️ ConsignmentInfolist - **HIGH PRIORITY**

### Phase 2: Core Actions (Week 2)
4. ⚠️ RecordSaleAction - **CRITICAL**
5. ⚠️ RecordReturnAction - **CRITICAL**
6. ⚠️ ConvertToInvoiceAction - **CRITICAL**

### Phase 3: Additional Features (Week 3)
7. Mark as Sent action
8. Cancel action
9. PDF generation
10. Email consignment to customer

---

## Testing Checklist

### Record Sale Action
- [ ] Can select multiple items
- [ ] Quantity validation works
- [ ] Price validation works
- [ ] Invoice creation works
- [ ] Status updates correctly (PARTIALLY_SOLD → INVOICED_IN_FULL)
- [ ] Item counts update correctly
- [ ] History log created
- [ ] Can't sell more than available

### Record Return Action
- [ ] Can only return sold items
- [ ] Quantity validation works
- [ ] Inventory updates if checkbox selected
- [ ] Status updates correctly
- [ ] Item counts update correctly
- [ ] History log created
- [ ] Can't return more than sold

### Convert to Invoice Action
- [ ] Only includes sold items
- [ ] Correct totals calculated
- [ ] Invoice created in orders table
- [ ] Consignment status updated
- [ ] converted_invoice_id set
- [ ] Can't convert twice
- [ ] Redirect to invoice works

---

## Reference Files

### Backend (Already Implemented)
- `app/Modules/Consignments/Services/ConsignmentService.php`
- `app/Modules/Consignments/Models/Consignment.php`
- `app/Modules/Consignments/Models/ConsignmentItem.php`
- `app/Modules/Consignments/Models/ConsignmentHistory.php`
- `app/Modules/Consignments/Enums/ConsignmentStatus.php`
- `app/Modules/Consignments/Enums/ConsignmentItemStatus.php`

### Frontend (To Be Implemented)
- `app/Filament/Resources/ConsignmentResource.php`
- `app/Filament/Resources/ConsignmentResource/Tables/ConsignmentsTable.php` - **EMPTY**
- `app/Filament/Resources/ConsignmentResource/Schemas/ConsignmentInfolist.php` - **EMPTY**
- `app/Filament/Resources/ConsignmentResource/Actions/RecordSaleAction.php` - **MISSING**
- `app/Filament/Resources/ConsignmentResource/Actions/RecordReturnAction.php` - **MISSING**
- `app/Filament/Resources/ConsignmentResource/Actions/ConvertToInvoiceAction.php` - **MISSING**

### Reference (Old System)
- `C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\consignments\read.blade.php`
- `C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\consignments\browse.blade.php`
- `C:\Users\Dell\Documents\Reporting\resources\views\vendor\voyager\consignments\professional-consignment.blade.php`

---

## Next Steps

1. **Implement ConsignmentsTable** - So users can see and manage all consignments
2. **Implement ConsignmentInfolist** - So users can view individual consignment details
3. **Implement RecordSaleAction** - Most critical for business workflow
4. **Test with real data** - Create test consignments and try all actions
5. **Implement RecordReturnAction** - For handling returns
6. **Implement ConvertToInvoiceAction** - To generate invoices from consignments

Would you like me to start implementing any of these actions now?
