# Database Changes Summary

## New Tables

### 1. `order_payments` Table
Tracks all payment transactions for orders (full and partial payments).

**Columns:**
- `id` - Primary key
- `order_id` - Foreign key to orders table
- `payment_amount` - DECIMAL(10,2) - Amount paid
- `payment_type` - ENUM('full', 'partial') - Type of payment
- `payment_method` - VARCHAR - Payment method (cash, credit_card, debit_card, bank_transfer, check)
- `payment_notes` - TEXT - Optional notes about the payment
- `payment_reference` - VARCHAR - Transaction ID, check number, etc.
- `payment_status` - VARCHAR - completed, pending, failed, refunded
- `payment_date` - TIMESTAMP - When payment was made
- `recorded_by` - Foreign key to users table
- `created_at` - Timestamp
- `updated_at` - Timestamp

**Indexes:**
- `order_id`
- `payment_date`
- `payment_status`

## Modified Tables

### 2. `orders` Table - New Columns

**delivery_note** - TEXT, NULLABLE
- Stores delivery note content for the order
- Used to track delivery instructions, special requirements, etc.

**order_workflow_status** - ENUM, DEFAULT 'draft'
- Values: 'draft', 'approved', 'processing', 'shipped', 'completed', 'cancelled'
- Tracks the lifecycle of an order
- Workflow: draft → approved → processing → shipped → completed

## Workflow Implementation

### Order Status Flow:
1. **DRAFT** - Quote/Order created but not sent
2. **APPROVED** - Customer approved the quote (becomes invoice)
3. **PROCESSING** - Order is being prepared
4. **SHIPPED** - Order shipped (tracking number added)
5. **COMPLETED** - Order delivered and payment completed

### Payment Tracking:
- All payments recorded in `order_payments` table
- Supports partial payments
- Each payment tracked with method, amount, date, and notes
- `paid_amount` on orders table = SUM of all completed payments
- `outstanding_amount` = `total` - `paid_amount`

### Integration Points:
- When tracking number is added → `order_workflow_status` changes to 'shipped'
- When full payment is recorded → `payment_status` = 'paid'
- Order can only be marked as 'completed' when `payment_status` = 'paid'

## Migration Files Created

1. `2025_11_19_210856_add_delivery_note_and_payment_fields_to_orders.php`
   - Adds `delivery_note` and `order_workflow_status` to orders table

2. `2025_11_19_210926_create_order_payments_table.php`
   - Creates `order_payments` table for tracking all payments

## Models Updated

1. **OrderPayment** (NEW)
   - `App\Modules\Orders\Models\OrderPayment`
   - Relationships: belongsTo Order, belongsTo User (recorded_by)

2. **Order** (UPDATED)
   - Added `payments()` relationship - hasMany OrderPayment
   - Added `delivery_note` to fillable
   - Added `order_workflow_status` to fillable

## Next Steps

1. Run migrations: `php artisan migrate`
2. Update dashboard routes to handle payment recording
3. Update invoice PDF generation to include delivery notes
4. Add workflow status transitions in order management
5. Update dashboard to display workflow status
