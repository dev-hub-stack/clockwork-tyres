# CRM Bug Fix Plan

## Overview
This document analyzes and provides fixes for 7 reported CRM issues from user testing.

---

## Issue #1: Invoice View Doesn't Show Invoice Number

### Problem
When viewing an invoice, the page title doesn't display the invoice number, making it unclear which invoice is being viewed.

### Root Cause
`ViewInvoice.php` doesn't override `getTitle()` or `getHeading()` method to display the invoice number.

### File
`app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php`

### Current Code
```php
class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions...
        ];
    }
}
```

### Fix
Add `getTitle()` method:
```php
public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
{
    return 'Invoice: ' . $this->record->order_number;
}
```

### Priority: LOW
### Estimated Time: 5 minutes

---

## Issue #2: Shipped Orders Disappearing from Dashboard

### Problem
When an order is marked as "shipped" (not completed), it disappears from the "Order Sheet" dashboard widget.

### Root Cause
`PendingOrdersTable.php` only queries orders with status `pending` or `processing`:
```php
->whereIn('order_status', ['pending', 'processing'])
```

Orders marked as "shipped" are excluded from this query.

### File
`app/Filament/Widgets/PendingOrdersTable.php` (line 28)

### Current Code
```php
Order::query()
    ->with(['customer', 'orderItems.productVariant.product.brand'])
    ->whereIn('order_status', ['pending', 'processing'])
    ->orderBy('created_at', 'desc')
```

### Fix
Include 'shipped' status in the filter:
```php
Order::query()
    ->with(['customer', 'orderItems.productVariant.product.brand'])
    ->whereIn('order_status', ['pending', 'processing', 'shipped'])
    ->orderBy('created_at', 'desc')
```

### Priority: HIGH
### Estimated Time: 5 minutes

---

## Issue #3: VAT Incl/Excl Pricing Option Not Showing

### Problem
The pricing option for inclusive/exclusive VAT is not displayed on quotes/invoices.

### Root Cause
Need to investigate form schema - likely `tax_inclusive` field not implemented or visible.

### Files to Check
- `app/Filament/Resources/InvoiceResource.php`
- `app/Filament/Resources/QuoteResource.php`

### Fix
Add toggle for tax_inclusive field in the form schema.

### Priority: MEDIUM
### Estimated Time: 30 minutes (investigation + fix)

---

## Issue #4: Consignment Values Show 0, Warehouse is Blank

### Problem
When viewing a consignment, the total values show AED 0.00 and warehouse field is blank.

### Root Cause
Likely one of:
1. Totals not being calculated when consignment is created
2. Warehouse relation not loaded/displayed properly

### Files to Check
- `app/Filament/Resources/ConsignmentResource.php` (form schema)
- `app/Modules/Consignments/Models/Consignment.php` (calculateTotals method)
- `app/Modules/Consignments/Services/ConsignmentService.php`

### Fix
1. Ensure `calculateTotals()` is called when saving consignment
2. Verify warehouse field is properly loaded and displayed

### Priority: MEDIUM
### Estimated Time: 30 minutes (investigation + fix)

---

## Issue #5: No "Record Sale" or "Convert to Invoice" After Marking Consignment as SENT

### Problem
After marking a consignment as "Sent", there are no buttons to "Record Sale", "Record Return", or "Convert to Invoice".

### Root Cause
**CONFIRMED**: `ViewConsignment.php` only has `EditAction::make()` in `getHeaderActions()`. 

The action classes exist:
- `app/Filament/Resources/ConsignmentResource/Actions/RecordSaleAction.php`
- `app/Filament/Resources/ConsignmentResource/Actions/RecordReturnAction.php`
- `app/Filament/Resources/ConsignmentResource/Actions/ConvertToInvoiceAction.php`
- `app/Filament/Resources/ConsignmentResource/Actions/MarkAsSentAction.php`
- `app/Filament/Resources/ConsignmentResource/Actions/CancelConsignmentAction.php`

But they are NOT added to ViewConsignment's header actions!

### File
`app/Filament/Resources/ConsignmentResource/Pages/ViewConsignment.php`

### Current Code
```php
protected function getHeaderActions(): array
{
    return [
        EditAction::make(),
    ];
}
```

### Fix
```php
use App\Filament\Resources\ConsignmentResource\Actions\RecordSaleAction;
use App\Filament\Resources\ConsignmentResource\Actions\RecordReturnAction;
use App\Filament\Resources\ConsignmentResource\Actions\ConvertToInvoiceAction;
use App\Filament\Resources\ConsignmentResource\Actions\MarkAsSentAction;
use App\Filament\Resources\ConsignmentResource\Actions\CancelConsignmentAction;

protected function getHeaderActions(): array
{
    return [
        MarkAsSentAction::make(),
        RecordSaleAction::make(),
        RecordReturnAction::make(),
        ConvertToInvoiceAction::make(),
        CancelConsignmentAction::make(),
        EditAction::make(),
    ];
}
```

Each action has its own `visible()` method that controls when it should appear based on consignment status:
- `MarkAsSentAction`: visible when status = 'draft'
- `RecordSaleAction`: visible when `$record->canRecordSale()` returns true
- `RecordReturnAction`: visible when `$record->canRecordReturn()` returns true
- `ConvertToInvoiceAction`: visible when `$record->canRecordSale() && empty($record->converted_invoice_id)`

### Priority: **CRITICAL**
### Estimated Time: 10 minutes

---

## Issue #6: Values Didn't Change

### Problem
After performing an action, values on the page didn't update.

### Root Cause
Possibly a Livewire refresh issue - page might need to be reloaded to show updated data.

### Potential Fixes
1. Add `$this->refreshFormData(['fieldName'])` after actions
2. Ensure `$this->redirect()` or page refresh is triggered after action completion

### Priority: LOW
### Estimated Time: 15 minutes (investigation)

---

## Issue #7: What's the Difference Between "Record Sale" vs "Convert to Invoice"?

### Explanation

| Feature | Record Sale | Convert to Invoice |
|---------|-------------|-------------------|
| **Purpose** | Record individual item sales | Bulk convert remaining items |
| **Item Selection** | Select specific items + quantities | All available items at once |
| **Price Override** | Can adjust prices for each item | Uses default consignment prices |
| **Invoice Creation** | Creates invoice for selected items | Creates invoice for all items |
| **Payment Recording** | Records payment with the sale | Creates unpaid invoice |
| **Use Case** | Customer bought some items | Customer wants to take ownership of all remaining items |
| **Status After** | PARTIALLY_SOLD (if more items remain) | INVOICED_IN_FULL |

### Technical Implementation

**RecordSaleAction** (`RecordSaleAction.php`):
- Uses `ConsignmentInvoiceService::recordSaleAndCreateInvoice()`
- Allows quantity selection per item
- Allows price adjustment
- Creates invoice AND payment record
- Updates consignment status based on remaining items

**ConvertToInvoiceAction** (`ConvertToInvoiceAction.php`):
- Uses `ConsignmentInvoiceService::convertToInvoice()`
- Converts ALL available items (not sold/returned)
- Creates invoice only (no payment)
- Sets consignment status to INVOICED_IN_FULL
- Links invoice to consignment via `converted_invoice_id`

---

## Implementation Order

### Phase 1: Critical Fixes (Do First)
1. **Issue #5** - Add actions to ViewConsignment (CRITICAL - blocks main workflow)
2. **Issue #2** - Fix dashboard filter to include 'shipped' (HIGH - affecting order visibility)

### Phase 2: UX Improvements
3. **Issue #1** - Add invoice number to ViewInvoice title
4. **Issue #4** - Fix consignment values/warehouse display
5. **Issue #3** - Add VAT toggle

### Phase 3: Polish
6. **Issue #6** - Fix refresh/update behavior

---

## Quick Fix Commands

### Fix Issue #5 (ViewConsignment Actions)
```php
// File: app/Filament/Resources/ConsignmentResource/Pages/ViewConsignment.php
// Replace the entire file content:

<?php

namespace App\Filament\Resources\ConsignmentResource\Pages;

use App\Filament\Resources\ConsignmentResource;
use App\Filament\Resources\ConsignmentResource\Actions\RecordSaleAction;
use App\Filament\Resources\ConsignmentResource\Actions\RecordReturnAction;
use App\Filament\Resources\ConsignmentResource\Actions\ConvertToInvoiceAction;
use App\Filament\Resources\ConsignmentResource\Actions\MarkAsSentAction;
use App\Filament\Resources\ConsignmentResource\Actions\CancelConsignmentAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConsignment extends ViewRecord
{
    protected static string $resource = ConsignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MarkAsSentAction::make(),
            RecordSaleAction::make(),
            RecordReturnAction::make(),
            ConvertToInvoiceAction::make(),
            CancelConsignmentAction::make(),
            EditAction::make(),
        ];
    }
}
```

### Fix Issue #2 (Dashboard Filter)
```php
// File: app/Filament/Widgets/PendingOrdersTable.php
// Line 28 - change:
->whereIn('order_status', ['pending', 'processing'])
// To:
->whereIn('order_status', ['pending', 'processing', 'shipped'])
```

### Fix Issue #1 (Invoice Title)
```php
// File: app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php
// Add method:
public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
{
    return 'Invoice: ' . $this->record->order_number;
}
```

---

## Testing After Fixes

### Issue #5 Test
1. Create a new consignment
2. View the consignment (should see "Mark as Sent" and "Edit" buttons)
3. Click "Mark as Sent"
4. View the consignment again (should now see "Record Sale", "Record Return", "Convert to Invoice")
5. Test each action

### Issue #2 Test
1. Go to dashboard
2. Find a pending order
3. Mark it as "shipped" (not completed)
4. Verify it still appears in the Order Sheet

### Issue #1 Test
1. Go to Invoices list
2. Click view on any invoice
3. Verify the page title shows "Invoice: INV-2025-XXXX"

---

## Files Modified Summary

| File | Change |
|------|--------|
| `app/Filament/Resources/ConsignmentResource/Pages/ViewConsignment.php` | Add all consignment actions |
| `app/Filament/Widgets/PendingOrdersTable.php` | Add 'shipped' to status filter |
| `app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php` | Add getTitle() method |

---

*Document created: December 13, 2025*
*Author: GitHub Copilot*

---

# Quote to Invoice Workflow

## Overview

The CRM uses a **unified orders table** approach where Quotes and Invoices are stored in the same table (`orders`) with different `document_type` values. This allows seamless conversion without data duplication.

## Document Types

| Document Type | Description | Appears on Dashboard? |
|--------------|-------------|----------------------|
| `quote` | A price proposal sent to customer | ❌ No |
| `invoice` | A confirmed order/bill | ✅ Yes (if status is pending/processing/shipped) |
| `order` | External order (from TunerStop sync) | ✅ Yes (if status is pending/processing/shipped) |

---

## Quote Lifecycle

```
┌─────────────┐     Send      ┌─────────────┐    Approve    ┌─────────────┐
│   DRAFT     │ ────────────► │    SENT     │ ────────────► │  APPROVED   │
│             │               │             │               │             │
│ (Editable)  │               │ (Waiting)   │               │ (Confirmed) │
└─────────────┘               └─────────────┘               └──────┬──────┘
                                                                    │
                                                              Convert to
                                                               Invoice
                                                                    │
                                                                    ▼
                                                           ┌─────────────┐
                                                           │  CONVERTED  │
                                                           │             │
                                                           │ (Now Invoice)│
                                                           └─────────────┘
```

### Quote Statuses:
1. **DRAFT** - Initial state, fully editable
2. **SENT** - Sent to customer, awaiting response
3. **APPROVED** - Customer confirmed the quote
4. **CONVERTED** - Successfully converted to invoice
5. **REJECTED** - Customer declined
6. **EXPIRED** - Quote validity period ended

---

## Quote to Invoice Conversion

### When Can a Quote Be Converted?

A quote can ONLY be converted when ALL these conditions are met:

```php
// From Order model - canConvertToInvoice()
public function canConvertToInvoice(): bool
{
    return $this->document_type === DocumentType::QUOTE
        && $this->quote_status === QuoteStatus::APPROVED
        && !$this->is_quote_converted
        && $this->items()->count() > 0;
}
```

### What Happens During Conversion?

The `QuoteConversionService::convertQuoteToInvoice()` method:

1. **Validates** - Checks if quote can be converted
2. **Updates document_type** - Changes from `quote` to `invoice`
3. **Updates quote_status** - Changes to `CONVERTED`
4. **Sets order_status** - Initializes to `PROCESSING`
5. **Generates invoice number** - Creates new `order_number` (INV-2025-XXXX)
6. **Recalculates totals** - Ensures amounts are accurate
7. **Fires event** - `QuoteConverted` event for notifications

### Key Point: Same Record!

```php
// THE CRITICAL CONVERSION: Just change the document_type!
$quote->update([
    'document_type' => DocumentType::INVOICE,  // ← THE KEY CHANGE!
    'quote_status' => QuoteStatus::CONVERTED,
    'is_quote_converted' => true,
    'order_status' => OrderStatus::PROCESSING,   // Triggers stock reduction
    'order_number' => $this->generateInvoiceNumber(),
]);
```

**No new record is created!** The same `Order` record just changes its type.

---

## When Does an Invoice Appear on Dashboard?

### Dashboard Widget: "Order Sheet" (`PendingOrdersTable`)

The dashboard Order Sheet shows invoices/orders that:

1. **Are invoices OR orders** (not quotes)
2. **Have status**: `pending`, `processing`, or `shipped`
3. **Are NOT completed or cancelled**

```php
// PendingOrdersTable.php query (AFTER FIX)
Order::query()
    ->with(['customer', 'orderItems.productVariant.product.brand'])
    ->whereIn('order_status', ['pending', 'processing', 'shipped'])
    ->orderBy('created_at', 'desc')
```

### Invoice Status Flow on Dashboard:

```
Quote Converted → Invoice Created (order_status: PROCESSING)
                          │
                          ▼
              ┌─────────────────────┐
              │  APPEARS ON         │
              │  DASHBOARD          │
              │  (Order Sheet)      │
              └──────────┬──────────┘
                         │
    ┌────────────────────┼────────────────────┐
    ▼                    ▼                    ▼
PENDING          PROCESSING             SHIPPED
(Awaiting        (Being                 (In transit,
 action)          prepared)              awaiting delivery)
    │                    │                    │
    └────────────────────┴────────────────────┘
                         │
                         ▼ Mark as Complete
              ┌─────────────────────┐
              │  REMOVED FROM       │
              │  DASHBOARD          │
              │                     │
              │  Status: COMPLETED  │
              └─────────────────────┘
```

---

## Complete Flow: Quote → Invoice → Dashboard

### Step-by-Step:

1. **Create Quote** (Quotes menu)
   - Status: DRAFT
   - document_type: quote
   - ❌ NOT on dashboard

2. **Send Quote** (ViewQuote → "Send Quote" button)
   - Status: SENT
   - document_type: quote
   - ❌ NOT on dashboard

3. **Approve Quote** (ViewQuote → "Approve" button)
   - Status: APPROVED
   - document_type: quote
   - ❌ NOT on dashboard
   - ✅ "Convert to Invoice" button appears

4. **Convert to Invoice** (ViewQuote → "Convert to Invoice" button)
   - Status: CONVERTED (quote_status)
   - order_status: PROCESSING
   - document_type: **invoice** ← Changed!
   - **✅ NOW APPEARS ON DASHBOARD**

5. **Process & Ship** (Dashboard actions or ViewInvoice)
   - order_status: SHIPPED
   - ✅ STILL on dashboard

6. **Complete Order** (ViewInvoice → "Complete Order" button)
   - order_status: COMPLETED
   - ❌ REMOVED from dashboard

---

## Summary Table

| Stage | Quote Status | Order Status | Doc Type | On Dashboard? |
|-------|-------------|--------------|----------|---------------|
| New Quote | DRAFT | - | quote | ❌ |
| Quote Sent | SENT | - | quote | ❌ |
| Quote Approved | APPROVED | - | quote | ❌ |
| **Converted!** | CONVERTED | PROCESSING | **invoice** | ✅ YES |
| Processing | CONVERTED | PROCESSING | invoice | ✅ YES |
| Shipped | CONVERTED | SHIPPED | invoice | ✅ YES |
| Completed | CONVERTED | COMPLETED | invoice | ❌ (Done) |

---

## UI Buttons Summary

### On ViewQuote Page:
| Button | Visible When | Action |
|--------|-------------|--------|
| Send Quote | status = DRAFT | Changes to SENT |
| Approve | status = SENT | Changes to APPROVED |
| **Convert to Invoice** | status = APPROVED | Converts to invoice, redirects to invoice view |
| Edit | status = DRAFT | Opens edit page |
| Delete | status = DRAFT | Deletes quote |

### On ViewInvoice Page:
| Button | Visible When | Action |
|--------|-------------|--------|
| Start Processing | status = pending | Changes to PROCESSING |
| Mark as Shipped | status = processing | Changes to SHIPPED |
| Complete Order | status = shipped | Changes to COMPLETED |
| Edit | Always | Opens edit page |
| Delete | Always | Deletes invoice |

### On Dashboard (Order Sheet):
| Button | Visible When | Action |
|--------|-------------|--------|
| Record Balance Payment | Not fully paid | Records payment |
| Download Delivery Note | Always | Downloads PDF |
| Download Invoice | Always | Downloads PDF |
| Mark Order as Done | Fully paid | Completes order |

---

## Edge Cases Handled

1. **Double Conversion Prevention**: `is_quote_converted` flag prevents converting same quote twice
2. **Empty Quote**: Cannot convert quote with no items
3. **Unapproved Quote**: Must be APPROVED before conversion
4. **Shipped Orders**: Now visible on dashboard (FIXED)
5. **Invoice Number Display**: Now shown on ViewInvoice page (FIXED)

