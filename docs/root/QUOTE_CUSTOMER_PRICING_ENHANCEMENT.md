# Quote Creation Enhancement - Customer Type & Pricing ✅

**Date**: October 25, 2025  
**Status**: 🔄 IN PROGRESS - Phase 1 Complete

---

## Requirements

### 1. Customer Dropdown Enhancements ✅
- [x] Show customer type (Retail/Dealer) in dropdown
- [x] Enable preload for faster loading
- [x] Include customer type in create form

### 2. Dealer Pricing Logic ✅
- [x] Detect if customer is dealer
- [x] Apply dealer pricing if available
- [x] Fallback to retail price if no dealer price
- [x] Auto-populate price on product selection

### 3. Billing/Shipping Address Selection ⏳
- [ ] Show address dropdown when customer selected
- [ ] Load customer's saved addresses
- [ ] Separate billing and shipping address selects
- [ ] Allow creating new address from quote screen

### 4. Product Price Population ✅
- [x] Auto-fill price when product selected
- [x] Use dealer price for dealer customers
- [x] Use retail price for retail customers

---

## Phase 1 Changes Applied ✅

### File: `app/Filament/Resources/QuoteResource.php`

#### Change 1: Customer Select with Type Display
```php
// BEFORE
Select::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->getOptionLabelFromRecordUsing(fn ($record) => 
        $record->business_name ?? $record->name ?? 'Unknown Customer'
    )

// AFTER
Select::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'business_name')
    ->preload()  // ✅ Faster loading
    ->getOptionLabelFromRecordUsing(function ($record) {
        $name = $record->business_name ?? $record->name ?? 'Unknown Customer';
        $type = $record->customer_type ? '(' . ucfirst($record->customer_type) . ')' : '';
        return trim("$name $type");  // ✅ Shows "Company Name (Dealer)"
    })
    ->live()  // ✅ Enables reactive updates
```

**Display Examples**:
- `TunerStop LLC (Dealer)`
- `John Doe (Retail)`
- `ABC Company (Dealer)`

#### Change 2: Add Customer Type to Create Form
```php
->createOptionForm([
    Select::make('customer_type')  // ✅ NEW
        ->label('Customer Type')
        ->options([
            'retail' => 'Retail',
            'dealer' => 'Dealer',
        ])
        ->default('retail')
        ->required(),
    TextInput::make('business_name')->required(),
    // ... other fields
])
```

#### Change 3: Intelligent Price Population
```php
->afterStateUpdated(function ($state, Set $set, $get) {
    if ($state) {
        $variant = ProductVariant::with('product')->find($state);
        if ($variant) {
            // Get customer to check if dealer
            $customerId = $get('../../customer_id');  // ✅ Get parent customer_id
            $customer = $customerId ? Customer::find($customerId) : null;
            
            // Determine price based on customer type
            $price = 0;
            if ($customer && $customer->isDealer()) {  // ✅ Check customer type
                // Use dealer price if available, otherwise retail price
                $price = $variant->dealer_price ?? $variant->price ?? $variant->product->retail_price ?? 0;
            } else {
                // Use retail price
                $price = $variant->price ?? $variant->product->retail_price ?? 0;
            }
            
            $set('unit_price', $price);  // ✅ Auto-populate
            $set('quantity', 1);
        }
    }
})
```

---

## Pricing Logic Flow

### For Retail Customers
```
1. Select Customer (Retail)
2. Add Product Line Item
3. System checks: customer.isRetail() → TRUE
4. Price = variant.price OR product.retail_price
5. Display price in form
```

### For Dealer Customers
```
1. Select Customer (Dealer)
2. Add Product Line Item
3. System checks: customer.isDealer() → TRUE
4. Price = variant.dealer_price OR variant.price OR product.retail_price
5. Display discounted dealer price in form
```

### Fallback Priority
```
Dealer Customer:
  1st: variant.dealer_price
  2nd: variant.price
  3rd: product.retail_price
  4th: 0

Retail Customer:
  1st: variant.price
  2nd: product.retail_price
  3rd: 0
```

---

## Phase 2: Address Selection (TODO)

### Required Changes

#### 1. Check if CustomerAddress model exists
```bash
# Need to verify:
- app/Modules/Customers/Models/CustomerAddress.php
- customer_addresses table
- Relationship in Customer model
```

#### 2. Add Address Selection Fields
```php
Section::make('Billing & Shipping')
    ->schema([
        Select::make('billing_address_id')
            ->label('Billing Address')
            ->options(function ($get) {
                $customerId = $get('customer_id');
                if (!$customerId) return [];
                
                return Customer::find($customerId)
                    ->addresses()
                    ->where('address_type', 1) // Billing
                    ->get()
                    ->pluck('formatted_address', 'id');
            })
            ->searchable()
            ->createOptionForm([/* address fields */])
            ->visible(fn ($get) => $get('customer_id') !== null),
        
        Select::make('shipping_address_id')
            ->label('Shipping Address')
            ->options(function ($get) {
                $customerId = $get('customer_id');
                if (!$customerId) return [];
                
                return Customer::find($customerId)
                    ->addresses()
                    ->where('address_type', 2) // Shipping
                    ->get()
                    ->pluck('formatted_address', 'id');
            })
            ->searchable()
            ->createOptionForm([/* address fields */])
            ->visible(fn ($get) => $get('customer_id') !== null),
    ])
    ->columns(2)
```

#### 3. Address Create Form
```php
->createOptionForm([
    Select::make('address_type')
        ->options([
            1 => 'Billing',
            2 => 'Shipping',
        ])
        ->required(),
    TextInput::make('address_line_1')->required(),
    TextInput::make('address_line_2'),
    TextInput::make('city')->required(),
    TextInput::make('state'),
    TextInput::make('postal_code'),
    TextInput::make('country')->default('UAE'),
    TextInput::make('phone_no'),
])
```

---

## Testing

### Phase 1 Testing ✅

1. **Customer Type Display**
   ```
   ✅ Navigate to /admin/quotes/create
   ✅ Click Customer dropdown
   ✅ Verify customers show with type: "Name (Dealer)" or "Name (Retail)"
   ✅ Search for customer
   ✅ Type should appear in search results
   ```

2. **Dealer Pricing**
   ```
   ✅ Select a Dealer customer
   ✅ Add a product line item
   ✅ Search and select a product
   ✅ Verify price auto-populates with dealer price
   ✅ Check unit_price field shows dealer_price value
   ```

3. **Retail Pricing**
   ```
   ✅ Select a Retail customer
   ✅ Add a product line item  
   ✅ Search and select a product
   ✅ Verify price auto-populates with retail price
   ✅ Check unit_price field shows retail price value
   ```

4. **Create New Customer**
   ```
   ✅ Click "+" to create new customer
   ✅ Verify "Customer Type" field appears
   ✅ Select "Dealer" or "Retail"
   ✅ Fill other fields
   ✅ Save
   ✅ Verify new customer appears in dropdown with type
   ```

### Phase 2 Testing (Pending)

Will test after implementing address selection fields.

---

## Database Requirements

### Existing Tables
- ✅ `customers` - Has `customer_type` column
- ✅ `product_variants` - Has `dealer_price` and `price` columns
- ✅ `products` - Has `retail_price` column

### Required for Phase 2
- ⏳ `customer_addresses` table
- ⏳ CustomerAddress model
- ⏳ addresses() relationship in Customer model

---

## Known Issues & Fixes

### Issue 1: Customer Dropdown Empty
**Cause**: No `preload()` method  
**Fix**: Added `->preload()` to customer select  
**Status**: ✅ FIXED

### Issue 2: Customer Type Not Showing
**Cause**: Label formatter only showed name  
**Fix**: Modified `getOptionLabelFromRecordUsing` to include type  
**Status**: ✅ FIXED

### Issue 3: Price Not Auto-Populating
**Cause**: Missing `$get` parameter in afterStateUpdated  
**Fix**: Added `$get` to access parent customer_id  
**Status**: ✅ FIXED

### Issue 4: Dealer Price Not Applied
**Cause**: No customer type check in price logic  
**Fix**: Added `isDealer()` check with dealer_price fallback  
**Status**: ✅ FIXED

---

## Next Steps

1. ✅ **Refresh browser** and test Phase 1 changes
2. ⏳ **Verify** customer addresses table structure
3. ⏳ **Implement** address selection fields (Phase 2)
4. ⏳ **Test** complete quote creation workflow
5. ⏳ **Apply same changes** to InvoiceResource

---

## Status

**Phase 1**: ✅ COMPLETE  
- Customer type display in dropdown
- Dealer pricing logic
- Price auto-population

**Phase 2**: ⏳ PENDING  
- Address selection fields
- Create address from quote screen

**Refresh browser and test!** 🚀

