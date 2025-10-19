# Customers Module - Complete Architecture Documentation

## ⚠️ CRITICAL: Dealer Pricing Activation

**MOST IMPORTANT REQUIREMENT:**  
When `customer_type = 'dealer'`, the dealer pricing mechanism MUST activate in **ALL MODULES**:
- ✅ Orders/Quotes
- ✅ Invoices
- ✅ Consignments
- ✅ Warranty Replacements
- ✅ Add-ons

This is NOT optional - it's a core business requirement that affects the entire system.

### **Dealer Pricing Priority:**
1. **Model-specific discount** (HIGHEST) - e.g., 15% off specific wheel model
2. **Brand-specific discount** (MEDIUM) - e.g., 10% off entire brand
3. **Addon Category discount** (for add-ons only) - e.g., 5% off lug nuts category

---

## Overview
The Customers module is the unified customer management system in the Reporting CRM. It replaced the legacy "Dealers" system to support both B2B (dealers/wholesale) and B2C (retail) customers in a single, flexible architecture.

**Last Updated:** October 20, 2025  
**Module Location:** `app/Models/Customer.php`, `app/Http/Controllers/Admin/CustomerController.php`  
**Tech Stack:** Laravel 12 + PostgreSQL 15 + Filament v3

---

## Table of Contents
1. [Database Schema](#database-schema)
2. [Model Architecture](#model-architecture)
3. [Controller Architecture](#controller-architecture)
4. [Address Management](#address-management)
5. [Pricing System](#pricing-system)
6. [Business Logic](#business-logic)
7. [Relationships](#relationships)
8. [Migration from Legacy System](#migration-from-legacy-system)

---

## Database Schema

### Customers Table
**Table Name:** `customers`

#### Core Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| id | bigint | Primary key | NO | AUTO_INCREMENT |
| customer_type | enum | **CRITICAL:** 'retail', 'dealer', 'wholesale', 'corporate' | NO | 'retail' |
| first_name | varchar(255) | Customer first name | YES | NULL |
| last_name | varchar(255) | Customer last name | YES | NULL |
| business_name | varchar(255) | Business/Company name (dealers) | YES | NULL |
| email | varchar(255) | Email address (unique) | YES | NULL |
| phone | varchar(50) | Primary phone number | YES | NULL |

**CRITICAL customer_type Values:**
- `retail` - Regular B2C customers (standard pricing)
- `dealer` - **ACTIVATES DEALER PRICING IN ALL MODULES**
- `wholesale` - Wholesale customers (may have different pricing rules)
- `corporate` - Corporate clients (may have custom pricing)

#### Address Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| address | text | Primary address line | YES | NULL |
| city | varchar(100) | City | YES | NULL |
| state | varchar(100) | State/Province | YES | NULL |
| country_id | bigint | FK to countries table | YES | NULL |

#### Business Information (Dealers)
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| website | varchar(255) | Business website | YES | NULL |
| trade_license_number | varchar(255) | Trade license number | YES | NULL |
| license_no | varchar(100) | License number | YES | NULL |
| expiry | date | License expiry date | YES | NULL |
| instagram | varchar(100) | Instagram handle | YES | NULL |
| trn | varchar(100) | Tax Registration Number (TRN) | YES | NULL |

#### System Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| representative_id | bigint | FK to users (sales rep) | YES | NULL |
| external_source | varchar(100) | Source system | YES | NULL |
| external_customer_id | varchar(255) | External system ID | YES | NULL |
| status | varchar(50) | Customer status | YES | 'active' |
| created_at | timestamp | Creation timestamp | YES | NULL |
| updated_at | timestamp | Last update timestamp | YES | NULL |
| deleted_at | timestamp | Soft delete timestamp | YES | NULL |

### Address Book Table
**Table Name:** `address_books`

| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| id | bigint | Primary key | NO | AUTO_INCREMENT |
| customer_id | bigint | FK to customers | YES | NULL |
| user_id | bigint | Legacy FK to users | YES | NULL |
| dealer_id | bigint | Legacy FK to dealers | YES | NULL |
| address_type | int | 1=Billing, 2=Shipping | YES | 1 |
| nickname | varchar(100) | Address nickname | YES | NULL |
| first_name | varchar(255) | Contact first name | YES | NULL |
| last_name | varchar(255) | Contact last name | YES | NULL |
| address | text | Street address | YES | NULL |
| city | varchar(100) | City | YES | NULL |
| state | varchar(100) | State/Province | YES | NULL |
| country | varchar(100) | Country name | YES | NULL |
| zip | varchar(20) | Postal code | YES | NULL |
| zip_code | varchar(20) | Alt postal code field | YES | NULL |
| phone_no | varchar(50) | Contact phone | YES | NULL |
| email | varchar(255) | Contact email | YES | NULL |
| created_at | timestamp | Creation timestamp | YES | NULL |
| updated_at | timestamp | Last update timestamp | YES | NULL |

### Customer Brand Pricing Table
**Table Name:** `customer_brand_pricing`  
**Purpose:** Brand-level discounts for dealer customers (MEDIUM priority)

| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| id | bigint | Primary key | NO | AUTO_INCREMENT |
| customer_id | bigint | FK to customers | NO | - |
| brand_id | bigint | FK to brands | NO | - |
| discount_type | varchar(50) | 'percentage' or 'fixed' | NO | 'percentage' |
| discount_percentage | decimal(5,2) | Percentage discount (0-100) | YES | 0.00 |
| discount_value | decimal(10,2) | Fixed discount amount (alternative) | NO | 0.00 |
| created_at | timestamp | Creation timestamp | YES | NULL |
| updated_at | timestamp | Last update timestamp | YES | NULL |

**Example:** Dealer gets 10% off all "Rotiform" brand products.

### Customer Model Pricing Table
**Table Name:** `customer_model_pricing`  
**Purpose:** Model-level discounts for dealer customers (HIGHEST priority)

| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| id | bigint | Primary key | NO | AUTO_INCREMENT |
| customer_id | bigint | FK to customers | NO | - |
| model_id | bigint | FK to models (product models, not vehicle) | NO | - |
| discount_type | varchar(50) | 'percentage' or 'fixed' | NO | 'percentage' |
| discount_percentage | decimal(5,2) | Percentage discount (0-100) | YES | 0.00 |
| discount_value | decimal(10,2) | Fixed discount amount (alternative) | NO | 0.00 |
| created_at | timestamp | Creation timestamp | YES | NULL |
| updated_at | timestamp | Last update timestamp | YES | NULL |

**Example:** Dealer gets 15% off specific wheel model "Rotiform BLQ" (higher than brand discount).

### Customer AddOn Category Pricing Table
**Table Name:** `customer_addon_category_pricing`  
**Purpose:** Addon category discounts for dealer customers

| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| id | bigint | Primary key | NO | AUTO_INCREMENT |
| customer_id | bigint | FK to customers | NO | - |
| add_on_category_id | bigint | FK to add_on_categories | NO | - |
| discount_type | varchar(50) | 'percentage' or 'fixed' | NO | 'percentage' |
| discount_percentage | decimal(5,2) | Percentage discount (0-100) | YES | 0.00 |
| discount_value | decimal(10,2) | Fixed discount amount (alternative) | NO | 0.00 |
| created_at | timestamp | Creation timestamp | YES | NULL |
| updated_at | timestamp | Last update timestamp | YES | NULL |

**Example:** Dealer gets 5% off all "Lug Nuts" category items.

---

## Model Architecture

### File: `app/Models/Customer.php`

```php
class Customer extends Model
{
    use HasFactory, SoftDeletes;
    
    // Soft deletes enabled for data preservation
}
```

### Key Features

#### 1. Mass Assignment Protection
```php
protected $fillable = [
    'customer_type',
    'first_name',
    'last_name',
    'business_name',
    'phone',
    'email',
    'address',
    'city',
    'state',
    'country_id',
    'website',
    'trade_license_number',
    'expiry',
    'instagram',
    'representative_id',
    'trn',
    'license_no',
    'external_source',
    'external_customer_id',
    'status'
];
```

#### 2. Date Casting
```php
protected $casts = [
    'expiry' => 'date',
];
```

#### 3. Soft Deletes
- Enabled for data preservation
- Related records also soft deleted (cascade)
- Can be restored with pricing relationships intact

---

## Accessors & Virtual Attributes

### 1. Name Accessor
```php
public function getNameAttribute()
{
    // For business customers, prefer business name
    if ($this->business_name) {
        return $this->business_name;
    }
    
    // For individual customers, combine first and last name
    $name = trim($this->first_name . ' ' . $this->last_name);
    return !empty($name) ? $name : 'Unknown Customer';
}
```

**Usage:**
```php
$customer->name; // Returns business_name OR "First Last"
```

### 2. Full Name Accessor
```php
public function getFullNameAttribute()
{
    $name = trim($this->first_name . ' ' . $this->last_name);
    return !empty($name) ? $name : 'Unknown Customer';
}
```

**Usage:**
```php
$customer->full_name; // Always returns "First Last", ignoring business_name
```

### 3. Primary Phone Accessor
```php
public function getPrimaryPhoneAttribute()
{
    // Return customer phone if available
    if (!empty($this->phone)) {
        return $this->phone;
    }
    
    // Fallback to primary address phone
    $primaryAddress = $this->addresses()->orderBy('address_type', 'asc')->first();
    if ($primaryAddress && !empty($primaryAddress->phone_no)) {
        return $primaryAddress->phone_no;
    }
    
    return null;
}
```

**Usage:**
```php
$customer->primary_phone; // Returns phone from customer OR first address
```

### 4. Primary Address Accessor
```php
public function getPrimaryAddressAttribute()
{
    // Get primary address (billing first, then shipping)
    $billingAddress = $this->addresses()->where('address_type', 1)->first();
    if ($billingAddress) {
        return $billingAddress;
    }
    
    $shippingAddress = $this->addresses()->where('address_type', 2)->first();
    if ($shippingAddress) {
        return $shippingAddress;
    }
    
    // Fallback to first address
    return $this->addresses()->first();
}
```

### 5. Formatted Address Accessor
```php
public function getFormattedAddressAttribute()
{
    $address = $this->primary_address;
    if (!$address) {
        return 'No address available';
    }
    
    $parts = array_filter([
        $address->address,
        $address->city,
        $address->state,
        $address->zip,
        $address->country
    ]);
    
    return implode(', ', $parts);
}
```

**Usage:**
```php
$customer->formatted_address; // "123 Main St, Dubai, Dubai, 00000, UAE"
```

---

## Relationships

### 1. Addresses (One-to-Many)
```php
public function addresses()
{
    return $this->hasMany(AddressBook::class);
}
```

**Description:** Customer can have multiple addresses (billing, shipping, additional)

**Usage:**
```php
$customer->addresses; // All addresses
$customer->addresses()->where('address_type', 1)->first(); // Billing address
$customer->addresses()->where('address_type', 2)->first(); // Shipping address
```

### 2. Model Pricing (One-to-Many)
```php
public function modelPricing()
{
    return $this->hasMany(CustomerModelPricing::class);
}
```

**Description:** Custom pricing rules for specific vehicle models (e.g., 10% off all BMW models)

### 3. Brand Pricing (One-to-Many)
```php
public function brandPricing()
{
    return $this->hasMany(CustomerBrandPricing::class);
}
```

**Description:** Custom pricing rules for specific brands (e.g., 15% off all BBS wheels)

### 4. AddOn Category Pricing (One-to-Many)
```php
public function addonCategoryPricing()
{
    return $this->hasMany(CustomerAddonCategoryPricing::class);
}
```

**Description:** Custom pricing rules for addon categories (e.g., 5% off lug nuts)

### 5. Users (One-to-Many)
```php
public function users()
{
    return $this->hasMany(User::class, 'customer_id');
}
```

**Description:** Customer portal users linked to this customer (for dealers with multiple staff)

### 6. Orders (One-to-Many)
```php
public function orders()
{
    return $this->hasMany(Order::class, 'customer_id');
}
```

**Description:** All orders placed by this customer

### 7. Country (Many-to-One)
```php
public function country()
{
    return $this->belongsTo(Country::class, 'country_id');
}
```

**Description:** Customer's primary country

### 8. Representative (Many-to-One)
```php
public function representative()
{
    return $this->belongsTo(User::class, 'representative_id');
}
```

**Description:** Sales representative assigned to this customer

---

## Query Scopes

### 1. Dealers Scope
```php
public function scopeDealers($query)
{
    return $query->where('customer_type', 'dealer');
}
```

**Usage:**
```php
Customer::dealers()->get(); // Only dealer customers
```

### 2. Retail Scope
```php
public function scopeRetail($query)
{
    return $query->where('customer_type', 'retail');
}
```

**Usage:**
```php
Customer::retail()->get(); // Only retail customers
```

---

## Controller Architecture

### File: `app/Http/Controllers/Admin/CustomerController.php`

```php
class CustomerController extends Controller
{
    // CRUD operations for customers
}
```

### Key Methods

#### 1. Index (List Customers)
```php
public function index(Request $request)
{
    $customers = Customer::paginate(20);
    return view('admin.customers.index', compact('customers'));
}
```

**Features:**
- Paginated list (20 per page)
- Shows all customer types (retail & dealer)

#### 2. Create (Customer Creation Form)
```php
public function create(Request $request)
{
    $representatives = User::all();
    $countries = Country::all();
    return view('admin.customers.create', compact('representatives', 'countries'));
}
```

**Features:**
- Loads representatives for assignment
- Loads countries for dropdown

#### 3. Store (Save New Customer)
```php
public function store(Request $request)
{
    // Check for existing (including soft deleted) customer with this email
    $existing = Customer::withTrashed()->where('email', $request->email)->first();
    
    if ($existing && $existing->trashed()) {
        // Restore and update details
        $existing->restore();
        // ... update fields
        // ... restore pricing relationships
        return redirect()->route('admin.customers.edit', $existing->id)
            ->with('success', 'Customer was previously deleted and has now been restored.');
    }
    
    // Validation rules
    $rules = [
        'customer_type' => 'required|in:retail,dealer',
        'email' => 'required|email|unique:customers,email',
        'expiry' => 'nullable|date|after_or_equal:today',
    ];
    
    if ($request->customer_type === 'dealer') {
        $rules['business_name'] = 'required|string|max:255';
    } else {
        $rules['first_name'] = 'required|string|max:255';
        $rules['last_name'] = 'required|string|max:255';
    }
    
    $validated = $request->validate($rules);
    
    $customer = Customer::create($validated);
    
    // Handle wholesale pricing
    if ($request->has('wholesale_pricing')) {
        $pricing = json_decode($request->wholesale_pricing, true);
        
        foreach ($pricing as $line) {
            if ($line['type'] === 'brand') {
                $customer->brandPricing()->create([
                    'brand_id' => $line['item'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['value'],
                ]);
            } elseif ($line['type'] === 'model') {
                $customer->modelPricing()->create([
                    'model_id' => $line['item'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['value'],
                ]);
            } elseif ($line['type'] === 'add_on_category') {
                $customer->addonCategoryPricing()->create([
                    'add_on_category_id' => $line['item'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['value'],
                ]);
            }
        }
    }
    
    return redirect()->route('admin.customers.index')
        ->with('success', 'Customer created successfully.');
}
```

**Key Features:**
- **Soft Delete Restoration:** If email exists but customer is soft-deleted, restores instead of creating duplicate
- **Conditional Validation:** Different required fields for retail vs dealer
- **Wholesale Pricing:** Handles complex pricing rules from JSON input
- **License Expiry Validation:** Must be today or future date

#### 4. Edit (Customer Edit Form)
```php
public function edit($id)
{
    $customer = Customer::with(['modelPricing', 'brandPricing', 'addonCategoryPricing', 'country'])
        ->findOrFail($id);
    $representatives = User::all();
    $countries = Country::all();
    
    return view('admin.customers.edit', compact('customer', 'representatives', 'countries'));
}
```

**Features:**
- **Eager loading** for performance (pricing relationships)
- Loads related data for form dropdowns

#### 5. Update (Save Customer Changes)
```php
public function update(Request $request, $id)
{
    $customer = Customer::findOrFail($id);
    
    // Validation rules (similar to store, but unique email excludes current customer)
    $rules = [
        'email' => 'required|email|unique:customers,email,' . $customer->id,
        // ... other rules
    ];
    
    $validated = $request->validate($rules);
    $customer->fill($validated);
    $customer->save();
    
    // Handle wholesale pricing update
    if ($request->has('wholesale_pricing')) {
        // Delete existing pricing
        $customer->brandPricing()->delete();
        $customer->modelPricing()->delete();
        $customer->addonCategoryPricing()->delete();
        
        // Save new pricing
        // ... (same logic as store)
    }
    
    return redirect()->route('admin.customers.index')
        ->with('success', 'Customer updated successfully.');
}
```

**Key Features:**
- **Complete pricing replacement:** Deletes and recreates all pricing rules
- **Validation excludes current record:** Email uniqueness check excludes self

#### 6. Destroy (Soft Delete Customer)
```php
public function destroy($id)
{
    $customer = Customer::findOrFail($id);
    $customer->delete(); // Soft delete
    
    return redirect()->route('admin.customers.index')
        ->with('success', 'Customer deleted successfully.');
}
```

**Cascade Behavior:**
```php
protected static function booted()
{
    static::deleting(function ($customer) {
        if ($customer->isForceDeleting()) {
            // Hard delete: DB will cascade
        } else {
            // Soft delete: manually delete related pricing and orders
            $customer->brandPricing()->delete();
            $customer->modelPricing()->delete();
            $customer->addonCategoryPricing()->delete();
            $customer->orders()->delete();
        }
    });
}
```

#### 7. Inline Create (AJAX Customer Creation)
```php
public function inlineCreate(Request $request)
{
    $request->validate([
        'first_name' => 'required|string|max:100',
        'last_name' => 'required|string|max:100',
        'email' => 'required|email|max:150|unique:customers,email',
        'phone' => 'nullable|string|max:50',
    ]);
    
    $customer = new Customer();
    $customer->first_name = $request->first_name;
    $customer->last_name = $request->last_name;
    $customer->email = $request->email;
    $customer->phone = $request->phone;
    $customer->customer_type = 'retail';
    $customer->save();
    
    return response()->json([
        'success' => true,
        'customer' => [
            'id' => $customer->id,
            'name' => $customer->first_name . ' ' . $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'is_newly_created' => true
        ]
    ]);
}
```

**Use Case:** Quick customer creation during order entry (AJAX)

---

## Address Management System

### AddressBook Model

**File:** `app/Models/AddressBook.php`

```php
class AddressBook extends Model
{
    protected $fillable = [
        'nickname',
        'first_name', 
        'last_name',
        'address',
        'country',
        'city',
        'state',
        'zip',
        'zip_code',
        'phone_no',
        'email',
        'user_id',
        'customer_id',
        'dealer_id',
        'address_type'
    ];
}
```

### Address Types
- **1:** Billing Address
- **2:** Shipping Address

### Key Methods

#### Get Full Name
```php
public function getNameAttribute()
{
    return $this->first_name.' '.$this->last_name;
}
```

### Address Relationships

#### Customer Relationship
```php
public function customer()
{
    return $this->belongsTo(Customer::class, 'customer_id');
}
```

#### User Relationship (Legacy)
```php
public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}
```

### Usage Examples

#### Get Customer's Billing Address
```php
$billingAddress = $customer->addresses()->where('address_type', 1)->first();
```

#### Get Customer's Shipping Address
```php
$shippingAddress = $customer->addresses()->where('address_type', 2)->first();
```

#### Create New Address
```php
$customer->addresses()->create([
    'address_type' => 1, // Billing
    'first_name' => 'John',
    'last_name' => 'Doe',
    'address' => '123 Main Street',
    'city' => 'Dubai',
    'state' => 'Dubai',
    'country' => 'United Arab Emirates',
    'zip' => '00000',
    'phone_no' => '+971501234567',
    'email' => 'john@example.com'
]);
```

---

## Pricing System Architecture

### Overview
The pricing system allows custom discount rules for dealer customers based on:
1. **Brands:** Discount on all products from a specific brand
2. **Models:** Discount on all products from a specific vehicle model
3. **AddOn Categories:** Discount on all addons in a category

### Pricing Models

#### 1. CustomerBrandPricing
```php
class CustomerBrandPricing extends Model
{
    protected $fillable = [
        'customer_id',
        'brand_id',
        'discount_type',
        'discount_value',
    ];
}
```

**Example:**
- Customer ID: 123
- Brand: BBS (brand_id: 5)
- Discount Type: percentage
- Discount Value: 15.00
- **Result:** 15% off all BBS products for customer 123

#### 2. CustomerModelPricing
```php
class CustomerModelPricing extends Model
{
    protected $fillable = [
        'customer_id',
        'model_id',
        'discount_type',
        'discount_value',
    ];
}
```

**Example:**
- Customer ID: 123
- Model: BMW M3 (model_id: 45)
- Discount Type: fixed
- Discount Value: 50.00
- **Result:** $50 off all BMW M3 wheels for customer 123

#### 3. CustomerAddonCategoryPricing
```php
class CustomerAddonCategoryPricing extends Model
{
    protected $fillable = [
        'customer_id',
        'add_on_category_id',
        'discount_type',
        'discount_value',
    ];
}
```

**Example:**
- Customer ID: 123
- Category: Lug Nuts (add_on_category_id: 2)
- Discount Type: percentage
- Discount Value: 10.00
- **Result:** 10% off all lug nuts for customer 123

### Discount Types

#### Percentage Discount
```php
'discount_type' => 'percentage',
'discount_value' => 15.00
```

**Calculation:**
```php
$originalPrice = 1000.00;
$discountAmount = $originalPrice * (15.00 / 100);
$finalPrice = $originalPrice - $discountAmount;
// Result: $850.00
```

#### Fixed Amount Discount
```php
'discount_type' => 'fixed',
'discount_value' => 50.00
```

**Calculation:**
```php
$originalPrice = 1000.00;
$discountAmount = 50.00;
$finalPrice = $originalPrice - $discountAmount;
// Result: $950.00
```

### Pricing Data Structure (JSON Input)
```json
[
  {
    "type": "brand",
    "item": 5,
    "discount_type": "percentage",
    "value": 15.00
  },
  {
    "type": "model",
    "item": 45,
    "discount_type": "fixed",
    "value": 50.00
  },
  {
    "type": "add_on_category",
    "item": 2,
    "discount_type": "percentage",
    "value": 10.00
  }
]
```

### Pricing Priority (if conflicts)
1. **Model-specific** pricing (highest priority)
2. **Brand-specific** pricing
3. **Category-specific** pricing (lowest priority)

---

## Business Logic

### Customer Type Determination

#### Retail Customer
```php
'customer_type' => 'retail'
```

**Characteristics:**
- Individual consumers
- No pricing rules (pay full retail price)
- Required fields: `first_name`, `last_name`, `email`
- Optional: `business_name` (if business email but retail customer)

#### Dealer Customer
```php
'customer_type' => 'dealer'
```

**Characteristics:**
- Wholesale/B2B customers
- Custom pricing rules available
- Required fields: `business_name`, `email`
- Additional fields: `trade_license_number`, `trn`, `license_no`, `expiry`
- Can have multiple portal users

### Customer Creation Workflow

```
┌─────────────────┐
│  Admin Opens    │
│  Create Form    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Select Customer │
│      Type       │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
    ▼         ▼
┌───────┐ ┌─────────┐
│Retail │ │ Dealer  │
└───┬───┘ └────┬────┘
    │          │
    │          ▼
    │    ┌──────────────┐
    │    │ Add Business │
    │    │ Information  │
    │    └──────┬───────┘
    │           │
    │           ▼
    │    ┌──────────────┐
    │    │  Configure   │
    │    │   Pricing    │
    │    └──────┬───────┘
    │           │
    └───────────┴────────┐
                │
                ▼
         ┌─────────────┐
         │    Save     │
         │  Customer   │
         └─────────────┘
```

### Soft Delete & Restore Logic

#### Soft Delete
```php
$customer->delete();
```

**What happens:**
1. Sets `deleted_at` timestamp
2. Soft deletes related pricing rules
3. Soft deletes related orders
4. Customer hidden from normal queries

#### Restore
```php
$customer->restore();
```

**What happens:**
1. Clears `deleted_at` timestamp
2. Customer visible again in queries
3. **Note:** Pricing rules need manual restoration if desired

### Email Uniqueness
- Email must be unique across **active** customers
- Soft-deleted customers don't block email reuse
- When creating with existing soft-deleted email:
  - System offers to restore instead of creating new
  - Preserves historical data

---

## Views & UI

### Browse View
**Location:** `resources/views/admin/customers/index.blade.php`

**Features:**
- Paginated customer list
- Filter by customer type (retail/dealer)
- Search by name, email, business name
- Quick actions (View, Edit, Delete)
- Shows customer type badge

### Create/Edit Forms
**Location:** 
- `resources/views/admin/customers/create.blade.php`
- `resources/views/admin/customers/edit.blade.php`

**Form Sections:**

#### 1. Basic Information
- Customer Type selector (Retail/Dealer)
- First Name, Last Name (required for retail)
- Business Name (required for dealer)
- Email (unique)
- Phone

#### 2. Address Information
- Address line
- City
- State
- Country (dropdown)

#### 3. Business Information (Dealers Only)
- Website
- Trade License Number
- License Number
- TRN (Tax Registration Number)
- License Expiry Date
- Instagram Handle

#### 4. System Information
- Representative assignment
- Status (active/inactive)

#### 5. Wholesale Pricing (Dealers Only)
- Dynamic pricing table
- Add/Remove pricing rules
- Select type (Brand/Model/AddOn Category)
- Select item from dropdown
- Choose discount type (Percentage/Fixed)
- Enter discount value

**Pricing UI:**
```
┌─────────────────────────────────────────────────────┐
│ Wholesale Pricing Rules                             │
├─────────────────────────────────────────────────────┤
│ Type ▼    | Item ▼        | Discount Type ▼ | Value│
│ Brand     | BBS           | Percentage      | 15   │
│ Model     | BMW M3        | Fixed           | 50   │
│ Category  | Lug Nuts      | Percentage      | 10   │
│                                          [+ Add Row] │
└─────────────────────────────────────────────────────┘
```

---

## Migration from Legacy System

### Legacy "Dealers" Table
**Old Structure:**
```sql
CREATE TABLE dealers (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    -- ... other fields
);
```

### Unified "Customers" Table
**New Structure:**
```sql
CREATE TABLE customers (
    id BIGINT PRIMARY KEY,
    customer_type ENUM('retail', 'dealer'),
    first_name VARCHAR(255),
    last_name VARCHAR(255),
    business_name VARCHAR(255),
    email VARCHAR(255),
    -- ... other fields
);
```

### Migration Benefits
1. **Single Source of Truth:** One customer table instead of separate dealers/users
2. **Flexible:** Supports both B2B and B2C
3. **Address Standardization:** Unified AddressBook system
4. **Pricing Flexibility:** Granular pricing rules
5. **Better Reporting:** Easier analytics across all customer types

### Backward Compatibility
During migration, both systems coexist:
- Legacy `dealer_id` fields still exist
- Legacy `user_id` fields mapped to `customer_id`
- AddressBook supports both `dealer_id` and `customer_id`

---

## API Integration

### Customer Sync from External Systems

#### Retail Customers (TunerStop)
```json
POST /api/sync/customer
{
  "external_customer_id": "TS-CUST-12345",
  "customer_type": "retail",
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "phone": "+971501234567",
  "external_source": "tunerstop_retail"
}
```

#### Dealer Customers (Wholesale)
```json
POST /api/sync/customer
{
  "external_customer_id": "WS-DEAL-789",
  "customer_type": "dealer",
  "business_name": "Premium Wheels LLC",
  "email": "info@premiumwheels.ae",
  "phone": "+971501234567",
  "trade_license_number": "TL-123456",
  "trn": "100123456700003",
  "external_source": "tunerstop_wholesale"
}
```

---

## Performance Considerations

### Database Indexes
```sql
CREATE INDEX idx_customers_email ON customers(email);
CREATE INDEX idx_customers_customer_type ON customers(customer_type);
CREATE INDEX idx_customers_representative_id ON customers(representative_id);
CREATE INDEX idx_customers_deleted_at ON customers(deleted_at);

CREATE INDEX idx_address_books_customer_id ON address_books(customer_id);
CREATE INDEX idx_address_books_address_type ON address_books(address_type);

CREATE INDEX idx_customer_brand_pricing_customer_id ON customer_brand_pricing(customer_id);
CREATE INDEX idx_customer_model_pricing_customer_id ON customer_model_pricing(customer_id);
CREATE INDEX idx_customer_addon_pricing_customer_id ON customer_addon_category_pricing(customer_id);
```

### Query Optimization
- Use eager loading for relationships when displaying customer lists
- Cache frequently accessed pricing rules
- Paginate customer lists

---

## Testing Recommendations

### Unit Tests
- Customer creation (retail & dealer)
- Soft delete & restore
- Email uniqueness validation
- Pricing rule creation
- Accessor methods (name, primary_phone, etc.)
- **NEW:** Dealer pricing activation verification
- **NEW:** Pricing priority (Model > Brand > Addon)
- **NEW:** Payment history tracking

### Integration Tests
- Complete customer creation workflow
- Pricing rule application during order
- Address management
- Customer-order relationships
- **NEW:** Dealer pricing across all modules (orders, invoices, consignments, warranties)
- **NEW:** Payment recording workflow
- **NEW:** Wafeq customer sync

---

## 🔄 DEALER PRICING SERVICE (GLOBAL)

### **Purpose**
Centralized service that calculates dealer pricing across **ALL modules** in the system.

### **Service Implementation**

```php
// app/Services/DealerPricingService.php
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerModelPricing;
use App\Models\CustomerBrandPricing;
use App\Models\CustomerAddonCategoryPricing;

class DealerPricingService
{
    /**
     * Calculate price with dealer discount applied
     * 
     * @param Customer $customer
     * @param mixed $item (Product, ProductVariant, AddOn)
     * @param string $itemType ('product', 'variant', 'addon')
     * @return float
     */
    public function calculatePrice(Customer $customer, $item, string $itemType = 'product'): float
    {
        // CRITICAL: Only activate for dealers
        if ($customer->customer_type !== 'dealer') {
            return $item->retail_price ?? $item->price;
        }

        // PRIORITY 1: Model-specific discount (HIGHEST)
        if (in_array($itemType, ['product', 'variant']) && isset($item->model_id)) {
            $modelDiscount = CustomerModelPricing::where('customer_id', $customer->id)
                ->where('model_id', $item->model_id)
                ->first();
            
            if ($modelDiscount) {
                return $this->applyDiscount(
                    $item->retail_price ?? $item->price,
                    $modelDiscount->discount_type,
                    $modelDiscount->discount_percentage ?? $modelDiscount->discount_value
                );
            }
        }

        // PRIORITY 2: Brand-specific discount (MEDIUM)
        if (in_array($itemType, ['product', 'variant']) && isset($item->brand_id)) {
            $brandDiscount = CustomerBrandPricing::where('customer_id', $customer->id)
                ->where('brand_id', $item->brand_id)
                ->first();
            
            if ($brandDiscount) {
                return $this->applyDiscount(
                    $item->retail_price ?? $item->price,
                    $brandDiscount->discount_type,
                    $brandDiscount->discount_percentage ?? $brandDiscount->discount_value
                );
            }
        }

        // PRIORITY 3: Addon Category discount (for addons only)
        if ($itemType === 'addon' && isset($item->addon_category_id)) {
            $addonDiscount = CustomerAddonCategoryPricing::where('customer_id', $customer->id)
                ->where('add_on_category_id', $item->addon_category_id)
                ->first();
            
            if ($addonDiscount) {
                return $this->applyDiscount(
                    $item->retail_price ?? $item->price,
                    $addonDiscount->discount_type,
                    $addonDiscount->discount_percentage ?? $addonDiscount->discount_value
                );
            }
        }

        // No discount found - return retail price
        return $item->retail_price ?? $item->price;
    }

    /**
     * Apply discount based on type
     */
    protected function applyDiscount(float $price, string $discountType, float $discountValue): float
    {
        if ($discountType === 'percentage') {
            return $price * (1 - $discountValue / 100);
        }
        
        // Fixed discount
        return max(0, $price - $discountValue);
    }

    /**
     * Check if customer is eligible for dealer pricing
     */
    public function isDealer(Customer $customer): bool
    {
        return $customer->customer_type === 'dealer';
    }

    /**
     * Get all pricing rules for a customer
     */
    public function getPricingRules(Customer $customer): array
    {
        return [
            'model_pricing' => $customer->modelPricing,
            'brand_pricing' => $customer->brandPricing,
            'addon_pricing' => $customer->addonCategoryPricing,
        ];
    }

    /**
     * Calculate price with details (for debugging/display)
     */
    public function calculatePriceWithDetails(Customer $customer, $item, string $itemType = 'product'): array
    {
        $originalPrice = $item->retail_price ?? $item->price;
        $finalPrice = $this->calculatePrice($customer, $item, $itemType);
        $discountApplied = $originalPrice - $finalPrice;
        $discountPercentage = $originalPrice > 0 ? ($discountApplied / $originalPrice) * 100 : 0;

        return [
            'original_price' => $originalPrice,
            'final_price' => $finalPrice,
            'discount_amount' => $discountApplied,
            'discount_percentage' => round($discountPercentage, 2),
            'is_dealer' => $this->isDealer($customer),
        ];
    }
}
```

### **Usage Across Modules**

#### **In Orders/Quotes:**
```php
// app/Http/Controllers/OrderController.php
public function store(Request $request)
{
    $customer = Customer::find($request->customer_id);
    $dealerPricingService = app(DealerPricingService::class);

    foreach ($request->items as $itemData) {
        $product = Product::find($itemData['product_id']);
        
        // Apply dealer pricing if customer is dealer
        $price = $dealerPricingService->calculatePrice($customer, $product, 'product');
        
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'price' => $price,
            'original_price' => $product->retail_price,
            'quantity' => $itemData['quantity'],
        ]);
    }
}
```

#### **In Invoices:**
```php
// When converting quote to invoice, dealer pricing is PRESERVED
public function convertQuoteToInvoice(Order $order)
{
    // Prices already calculated with dealer discount
    // Just copy to invoice
    foreach ($order->orderItems as $item) {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'price' => $item->price,  // Already has dealer discount
            'original_price' => $item->original_price,
            // ... other fields
        ]);
    }
}
```

#### **In Consignments:**
```php
// app/Http/Controllers/ConsignmentController.php
public function store(Request $request)
{
    $customer = Customer::find($request->customer_id);
    $dealerPricingService = app(DealerPricingService::class);

    foreach ($request->items as $itemData) {
        $product = Product::find($itemData['product_id']);
        
        // CRITICAL: Apply dealer pricing to consignments too!
        $price = $dealerPricingService->calculatePrice($customer, $product, 'product');
        
        ConsignmentItem::create([
            'consignment_id' => $consignment->id,
            'product_id' => $product->id,
            'price' => $price,
            'quantity_sent' => $itemData['quantity'],
        ]);
    }
}
```

#### **In Warranty Replacements:**
```php
// app/Http/Controllers/WarrantyClaimController.php
public function approveReplacement(WarrantyClaim $claim)
{
    $customer = $claim->customer;
    $dealerPricingService = app(DealerPricingService::class);

    // CRITICAL: Apply dealer pricing to replacement cost
    $replacementProduct = Product::find($claim->replacement_product_id);
    $replacementCost = $dealerPricingService->calculatePrice($customer, $replacementProduct, 'product');
    
    $claim->update([
        'replacement_cost' => $replacementCost,
        'status' => 'approved',
    ]);
}
```

### **UI Indication**

**Show dealer pricing badge in all module forms:**

```blade
{{-- In order/quote/consignment/invoice forms --}}
@if($customer->customer_type === 'dealer')
    <div class="alert alert-info">
        <i class="fas fa-star"></i>
        <strong>Dealer Pricing Active</strong>
        This customer receives special dealer pricing on all items.
        
        <button type="button" class="btn btn-sm btn-link" onclick="showPricingRules()">
            View Pricing Rules
        </button>
    </div>
@endif

{{-- In item selection grid --}}
<div class="product-item" data-product-id="{{ $product->id }}">
    <span class="product-name">{{ $product->name }}</span>
    
    @if($customer->customer_type === 'dealer')
        <span class="original-price text-muted"><s>${{ $product->retail_price }}</s></span>
        <span class="dealer-price text-success"><strong>${{ $dealerPrice }}</strong></span>
        <span class="badge badge-success">{{ $discountPercentage }}% OFF</span>
    @else
        <span class="price">${{ $product->retail_price }}</span>
    @endif
</div>
```

---

## Payment History Tracking

**Track all payments received from customer:**

```php
// app/Models/Customer.php
public function payments()
{
    return $this->hasMany(PaymentRecord::class);
}

public function getTotalPaidAttribute()
{
    return $this->payments()->sum('amount');
}

public function getOutstandingBalanceAttribute()
{
    $totalInvoiced = $this->invoices()->sum('total');
    $totalPaid = $this->total_paid;
    return $totalInvoiced - $totalPaid;
}

// Payment methods breakdown
public function getPaymentMethodsBreakdown()
{
    return $this->payments()
        ->selectRaw('payment_method, SUM(amount) as total')
        ->groupBy('payment_method')
        ->get();
}
```

**Customer Financial Dashboard:**
```php
// Show in customer detail page
$customer->load(['payments', 'invoices', 'orders']);

$financialSummary = [
    'total_orders' => $customer->orders()->count(),
    'total_invoiced' => $customer->invoices()->sum('total'),
    'total_paid' => $customer->total_paid,
    'outstanding_balance' => $customer->outstanding_balance,
    'average_order_value' => $customer->orders()->avg('total'),
    'payment_methods' => $customer->getPaymentMethodsBreakdown(),
];
```

---

## Related Documentation
- [Orders Module Architecture](ARCHITECTURE_ORDERS_MODULE.md) - Dealer pricing in orders
- [Consignment Module](ARCHITECTURE_CONSIGNMENT_INVOICE_WARRANTY_MODULES.md) - Dealer pricing in consignments
- [Products Module Architecture](ARCHITECTURE_PRODUCTS_MODULE.md) - Product pricing structure
- [AddOns Module](ARCHITECTURE_ADDONS_MODULE.md) - Addon category pricing
- [Research Findings](RESEARCH_FINDINGS.md) - Complete dealer pricing research

---

## Changelog
- **2025-10-20:** Initial comprehensive documentation
- **2025-10-20:** Added pricing system details
- **2025-10-20:** Documented migration from legacy dealers system
- **2025-10-20:** Added CRITICAL dealer pricing activation documentation
- **2025-10-20:** Added DealerPricingService global service
- **2025-10-20:** Added pricing priority system (Model > Brand > Addon)
- **2025-10-20:** Added usage examples across all modules
- **2025-10-20:** Added payment history tracking
- **2025-10-20:** Updated to Laravel 12 + PostgreSQL 15
