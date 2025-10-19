# AddOns & AddOn Categories Module - Complete Architecture Documentation

## ⚠️ CRITICAL: Addon Dealer Pricing & Tax Inclusive

**IMPORTANT REQUIREMENTS:**  
1. ✅ **Dealer Pricing:** Addons support dealer pricing via `customer_addon_category_pricing` table
2. ✅ **Tax Inclusive:** Each addon can have `tax_inclusive` boolean for per-item tax handling
3. ✅ **Product Snapshot:** Addon data captured in snapshot when adding to order/quote
4. ✅ **Category-Based Discounts:** Dealers get discounts by addon category (e.g., 5% off all lug nuts)

### **Addon Pricing Example:**
```
Retail Customer buys Lug Nuts:
- Price: $50.00 (standard price)

Dealer Customer buys Lug Nuts:
- Original Price: $50.00
- Category Discount: 10% (customer_addon_category_pricing)
- Final Price: $45.00
```

---

## Overview
The AddOns module manages wheel accessories like lug nuts, lug bolts, hub rings, spacers, TPMS, and general wheel accessories. Each addon belongs to a category with specific attributes and supports dealer pricing.

**Last Updated:** October 20, 2025  
**Module Location:** `app/Models/AddOn.php`, `app/Models/AddOnCategory.php`  
**Tech Stack:** Laravel 12 + PostgreSQL 15 + Filament v3

---

## Database Schema

### AddOns Table
**Table Name:** `add_ons`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| title | varchar(255) | Product title |
| part_number | varchar(100) | Manufacturer part number |
| add_on_category_id | bigint | FK to add_on_categories |
| price | decimal(10,2) | Retail price |
| wholesale_price | decimal(10,2) | Wholesale price |
| **tax_inclusive** | **boolean** | **Tax included in price (per-item tax handling)** |
| stock_status | tinyint | 1=in stock, 0=out of stock |
| total_quantity | int | Total stock across warehouses |
| vendor_id | bigint | FK to users (legacy) |
| description | text | Product description |
| products | int | Product association flag |

**CRITICAL:** `tax_inclusive` boolean allows mixed tax handling within same order.

### Category-Specific Attributes

#### Lug Nuts
| Column | Type | Description |
|--------|------|-------------|
| thread_size | varchar(50) | Thread size (e.g., M12x1.5) |
| color | varchar(50) | Color/finish |
| lug_nut_length | varchar(50) | Length measurement |
| lug_nut_diameter | varchar(50) | Diameter measurement |

#### Lug Bolts
| Column | Type | Description |
|--------|------|-------------|
| thread_size | varchar(50) | Thread size |
| color | varchar(50) | Color/finish |
| thread_length | varchar(50) | Thread length |
| lug_bolt_diameter | varchar(50) | Bolt diameter |

#### Hub Rings
| Column | Type | Description |
|--------|------|-------------|
| ext_center_bore | varchar(50) | External center bore |
| center_bore | varchar(50) | Internal center bore |

#### Spacers
| Column | Type | Description |
|--------|------|-------------|
| bolt_pattern | varchar(100) | Bolt pattern |
| width | varchar(50) | Spacer width/thickness |
| thread_size | varchar(50) | Thread size |
| center_bore | varchar(50) | Center bore |

#### TPMS & Wheel Accessories
- Uses `description` field for details

### Media
| Column | Type | Description |
|--------|------|-------------|
| image_1 | varchar(255) | Primary image path |
| image_2 | varchar(255) | Secondary image path |

### Notification System
| Column | Type | Description |
|--------|------|-------------|
| notify_restock | json | Array of customer IDs to notify when restocked |

---

## AddOn Categories

### Categories Table
**Table Name:** `add_on_categories`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| name | varchar(255) | Category name |
| slug | varchar(255) | URL-friendly slug |
| order | int | Display order |
| created_at | timestamp | Creation timestamp |
| updated_at | timestamp | Last update |

### Category Slugs
1. **wheel-accessories** (ID: 1)
2. **lug-nuts** (ID: 2)
3. **lug-bolts** (ID: 3)
4. **hub-rings** (ID: 4)
5. **spacers** (ID: 5)
6. **tpms** (ID: 6)

---

## Model Features

### AddOn Model

```php
class AddOn extends Model
{
    const FOLDER = 'add-ons';
    
    protected $casts = [
        'notify_restock' => 'array'
    ];
}
```

#### Relationships

**1. Category**
```php
public function category()
{
    return $this->belongsTo(AddOnCategory::class, 'add_on_category_id');
}
```

**2. Inventory (Single)**
```php
public function inventory()
{
    return $this->hasOne(ProductInventory::class, 'add_on_id');
}
```

**3. Inventory (Multiple Warehouses)**
```php
public function addon_inventory()
{
    return $this->hasMany(ProductInventory::class, 'add_on_id');
}
```

**4. Warehouse (Legacy)**
```php
public function warehouse()
{
    return $this->hasOne(ProductInventory::class, 'warehouse_id');
}
```

**5. Vendor**
```php
public function vendor()
{
    return $this->belongsTo(User::class, 'vendor_id');
}
```

---

## AddOnCategory Model

```php
class AddOnCategory extends Model
{
    protected $table = 'add_on_categories';
}
```

### Global Scope - Auto Sort by Order
```php
protected static function booted()
{
    static::addGlobalScope('sorted', function (Builder $builder) {
        return $builder->orderBy('order');
    });
}
```

### Category Mapping
```php
public static function mappedCategories()
{
    return [
        'wheel-accessories' => 1,
        'lug-nuts' => 2,
        'lug-bolts' => 3,
        'hub-rings' => 4,
        'spacers' => 5,
        'tpms' => 6,
    ];
}
```

### Dynamic CSV Fields by Category
```php
public function getCsvFieldsAttribute()
{
    $commonAttributes = [
        'part number', 
        'product full name', 
        'us retail price', 
        'wholesale price', 
        'image 1', 
        'image 2'
    ];
    
    $slugAttributes = [
        'wheel-accessories' => ['description'],
        'lug-nuts' => ['thread size', 'color', 'lug nut length', 'lug nut diameter'],
        'lug-bolts' => ['thread size', 'color', 'thread length', 'lug bolt diameter'],
        'hub-rings' => ['ext. center bore', 'center bore'],
        'spacers' => ['bolt pattern', 'width', 'thread size', 'center bore'],
        'tpms' => ['description'],
    ];
    
    if (isset($slugAttributes[$this->slug])) {
        return array_merge($commonAttributes, $slugAttributes[$this->slug]);
    }
    
    return $commonAttributes;
}
```

### Allowed Fields for BREAD
```php
public function getAllowedFieldsAttribute()
{
    $commonFields = [
        'part_number', 
        'title', 
        'price', 
        'wholesale_price', 
        'image_1', 
        'image_2', 
        'products', 
        'add_on_belongstomany_product_add_on_relationship', 
        'add_on_belongstomany_brand_relationship'
    ];
    
    $slugFields = [
        'wheel-accessories' => ['description'],
        'lug-nuts' => ['thread_size', 'color', 'lug_nut_length', 'lug_nut_diameter'],
        'lug-bolts' => ['thread_size', 'color', 'thread_length', 'lug_bolt_diameter'],
        'hub-rings' => ['ext_center_bore', 'center_bore'],
        'spacers' => ['bolt_pattern', 'width', 'thread_size', 'center_bore'],
        'tpms' => ['description'],
    ];
    
    if (isset($slugFields[$this->slug])) {
        return array_merge($commonFields, $slugFields[$this->slug]);
    }
    
    return $commonFields;
}
```

### Required Fields by Category
```php
public function getRequiredFieldsAttribute()
{
    $commonFields = ['title', 'part_number', 'price', 'image_1', 'image_2'];
    
    $slugFields = [
        'wheel-accessories' => ['description'],
        'lug-nuts' => ['thread_size', 'color', 'lug_nut_length', 'lug_nut_diameter'],
        'lug-bolts' => ['thread_size', 'color', 'thread_length', 'lug_bolt_diameter'],
        'hub-rings' => ['ext_center_bore', 'center_bore'],
        'spacers' => ['bolt_pattern', 'width', 'thread_size', 'center_bore'],
        'tpms' => ['description'],
    ];
    
    if (isset($slugFields[$this->slug])) {
        return array_merge($commonFields, $slugFields[$this->slug]);
    }
    
    return $commonFields;
}
```

---

## Import System

### CSV Import Method
```php
public static function import($data, AddOnCategory $category)
{
    $fieldsData = [
        'price' => $data['us retail price'],
        'title' => $data['product full name'],
        'wholesale_price' => isset($data['wholesale price']) ? $data['wholesale price'] : null,
        'bolt_pattern' => isset($data['bolt pattern']) ? $data['bolt pattern'] : null,
        'width' => isset($data['width']) ? $data['width'] : null,
        'thread_size' => isset($data['thread size']) ? $data['thread size'] : null,
        'center_bore' => isset($data['center bore']) ? $data['center bore'] : null,
        'color' => isset($data['color']) ? $data['color'] : null,
        'lug_nut_length' => isset($data['lug nut length']) ? $data['lug nut length'] : null,
        'lug_nut_diameter' => isset($data['lug nut diameter']) ? $data['lug nut diameter'] : null,
        'thread_length' => isset($data['thread length']) ? $data['thread length'] : null,
        'lug_bolt_diameter' => isset($data['lug bolt diameter']) ? $data['lug bolt diameter'] : null,
        'ext_center_bore' => isset($data['ext. center bore']) ? $data['ext. center bore'] : null,
        'description' => isset($data['description']) ? $data['description'] : '',
        'image_1' => isset($data['image 1']) && $data['image 1'] 
            ? (self::FOLDER.'/'.$data['image 1']) : null,
        'image_2' => isset($data['image 2']) && $data['image 2'] 
            ? (self::FOLDER.'/'.$data['image 2']) : null,
        'products' => 1,
        'stock_status' => 1,
    ];
    
    $addOn = AddOn::updateOrCreate([
        'add_on_category_id' => $category->id,
        'part_number' => isset($data['part number']) ? $data['part number'] : '',
        'vendor_id' => Auth()->user()->id,
    ], $fieldsData);
    
    // Extract warehouse quantities from CSV
    $wareHousesQty = array_diff_key($data, array_flip($category->csv_fields));
    
    // Update inventory
    self::updateAddOnQty($addOn, $wareHousesQty, true);
    
    return $addOn;
}
```

### Update Inventory Quantities
```php
public static function updateAddOnQty(AddOn $addOn, $wareHousesQty, $increment = false)
{
    // Filter out empty values
    $wareHousesQty = array_filter($wareHousesQty, function ($value) {
        return $value !== '' && $value !== null;
    });
    
    foreach ($wareHousesQty as $warehouseCode => $qty) {
        $warehouse = Warehouse::where('code', $warehouseCode)->select('id')->first();
        
        if ($warehouse) {
            $checkInventory = ProductInventory::where('add_on_id', $addOn->id)
                ->where('warehouse_id', $warehouse->id)
                ->first();
            
            if ($checkInventory) {
                if ($increment) {
                    $checkInventory->increment('quantity', $qty);
                } else {
                    $checkInventory->quantity = $qty;
                    $checkInventory->save();
                }
            } else {
                $newInventory = new ProductInventory();
                $newInventory->warehouse_id = $warehouse->id;
                $newInventory->add_on_id = $addOn->id;
                $newInventory->quantity = $qty;
                $newInventory->save();
            }
        }
    }
    
    // Send restock notifications
    $notify = $addOn->notify_restock;
    if (!empty($notify)) {
        foreach ($notify as $item) {
            $dealer = Dealer::find($item);
            $email_service = new EmailService();
            $data['addon'] = $addOn;
            $data['image_url'] = env('S3IMAGES_URL');
            $view = 'emails.addon-restock-alert';
            $body = view($view, $data)->render();
            $email_service->send($dealer->email, 'Clockwork: Addon available for purchase', $body);
        }
        // Clear notification list
        $addOn->notify_restock = null;
        $addOn->save();
    }
}
```

---

## Restock Notification System

### Workflow
1. Customer requests notification when addon is back in stock
2. Customer ID added to `notify_restock` JSON array
3. When quantity updated (via `updateAddOnQty`):
   - Check if `notify_restock` has IDs
   - Send email to each customer
   - Clear `notify_restock` array

### Email Template
**View:** `resources/views/emails/addon-restock-alert.blade.php`

**Data:**
- `$addon`: AddOn model instance
- `$image_url`: S3 base URL for images

---

## Usage Examples

### Get AddOns by Category
```php
$lugNuts = AddOn::where('add_on_category_id', 2)->get();
```

### Get AddOn with Inventory
```php
$addon = AddOn::with('addon_inventory.warehouse')->find($id);
$totalStock = $addon->addon_inventory->sum('quantity');
```

### Import from CSV
```php
$category = AddOnCategory::where('slug', 'lug-nuts')->first();
$addon = AddOn::import($csvRow, $category);
```

### Add Restock Notification
```php
$addon = AddOn::find($id);
$notify = $addon->notify_restock ?? [];
$notify[] = $customerId;
$addon->notify_restock = $notify;
$addon->save();
```

---

## 🎯 DEALER PRICING FOR ADDONS

### **Purpose**
Addons support category-level dealer pricing (e.g., 10% off all lug nuts for dealers).

### **Database Relationship**

**Table:** `customer_addon_category_pricing`

```sql
CREATE TABLE customer_addon_category_pricing (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT REFERENCES customers(id),
    add_on_category_id BIGINT REFERENCES add_on_categories(id),
    discount_type VARCHAR(50) DEFAULT 'percentage',
    discount_percentage DECIMAL(5,2) DEFAULT 0,
    discount_value DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Example: Dealer gets 10% off all "Lug Nuts" category
INSERT INTO customer_addon_category_pricing 
VALUES (1, 123, 2, 'percentage', 10.00, 0, NOW(), NOW());
```

### **Pricing Calculation**

```php
// app/Models/AddOn.php
public function getDealerPriceForCustomer(Customer $customer)
{
    if ($customer->customer_type !== 'dealer') {
        return $this->price;
    }

    // Check addon category discount
    $categoryDiscount = CustomerAddonCategoryPricing::where('customer_id', $customer->id)
        ->where('add_on_category_id', $this->add_on_category_id)
        ->first();
    
    if ($categoryDiscount) {
        if ($categoryDiscount->discount_type === 'percentage') {
            return $this->price * (1 - $categoryDiscount->discount_percentage / 100);
        }
        
        return max(0, $this->price - $categoryDiscount->discount_value);
    }

    return $this->price;  // No discount
}
```

### **Usage in Orders**

```php
// When adding addon to order
$customer = Customer::find($orderId);
$addon = AddOn::find($addonId);
$dealerPricingService = app(DealerPricingService::class);

// Apply dealer pricing
$price = $dealerPricingService->calculatePrice($customer, $addon, 'addon');

OrderItem::create([
    'order_id' => $order->id,
    'addon_id' => $addon->id,
    'product_name' => $addon->title,
    'price' => $price,
    'original_price' => $addon->price,
    'quantity' => $quantity,
    'tax_inclusive' => $addon->tax_inclusive ?? true,
]);
```

### **UI Display**

```blade
{{-- Show dealer price for addons --}}
<div class="addon-item">
    <h5>{{ $addon->title }}</h5>
    <p class="text-muted">{{ $addon->part_number }}</p>
    
    @if($customer->customer_type === 'dealer')
        <div class="pricing">
            <span class="original-price text-muted"><s>${{ $addon->price }}</s></span>
            <span class="dealer-price text-success">
                <strong>${{ $addon->getDealerPriceForCustomer($customer) }}</strong>
            </span>
            <span class="badge badge-success">Dealer Price</span>
        </div>
    @else
        <span class="price">${{ $addon->price }}</span>
    @endif
</div>
```

---

## 🔄 ADDON SNAPSHOT SYSTEM

### **Purpose**
Capture complete addon data at time of order for historical accuracy.

### **Snapshot Creation**

```php
// app/Services/AddonSnapshotService.php
class AddonSnapshotService
{
    public function createSnapshot(AddOn $addon): array
    {
        return [
            'id' => $addon->id,
            'title' => $addon->title,
            'part_number' => $addon->part_number,
            'description' => $addon->description,
            
            // Category
            'category' => [
                'id' => $addon->add_on_category_id,
                'name' => $addon->category->name ?? null,
                'slug' => $addon->category->slug ?? null,
            ],
            
            // Pricing
            'retail_price' => $addon->price,
            'wholesale_price' => $addon->wholesale_price,
            'tax_inclusive' => $addon->tax_inclusive ?? true,
            
            // Category-specific attributes
            'attributes' => $this->getCategoryAttributes($addon),
            
            // Images
            'images' => [
                $addon->image_1,
                $addon->image_2,
            ],
            
            // Metadata
            'captured_at' => now()->toIso8601String(),
            'captured_by' => auth()->id(),
        ];
    }

    protected function getCategoryAttributes(AddOn $addon): array
    {
        $categorySlug = $addon->category->slug ?? null;

        switch ($categorySlug) {
            case 'lug-nuts':
                return [
                    'thread_size' => $addon->thread_size,
                    'color' => $addon->color,
                    'length' => $addon->lug_nut_length,
                    'diameter' => $addon->lug_nut_diameter,
                ];
            
            case 'lug-bolts':
                return [
                    'thread_size' => $addon->thread_size,
                    'color' => $addon->color,
                    'thread_length' => $addon->thread_length,
                    'diameter' => $addon->lug_bolt_diameter,
                ];
            
            case 'hub-rings':
                return [
                    'ext_center_bore' => $addon->ext_center_bore,
                    'center_bore' => $addon->center_bore,
                ];
            
            case 'spacers':
                return [
                    'bolt_pattern' => $addon->bolt_pattern,
                    'width' => $addon->width,
                    'thread_size' => $addon->thread_size,
                    'center_bore' => $addon->center_bore,
                ];
            
            default:
                return [];
        }
    }
}
```

### **Usage in Orders**

```php
// When adding addon to order
public function addAddon(AddOn $addon, int $quantity, Customer $customer)
{
    $snapshotService = app(AddonSnapshotService::class);
    $dealerPricingService = app(DealerPricingService::class);
    
    // Create snapshot
    $snapshot = $snapshotService->createSnapshot($addon);
    
    // Calculate price with dealer discount
    $price = $dealerPricingService->calculatePrice($customer, $addon, 'addon');
    
    OrderItem::create([
        'order_id' => $this->id,
        'addon_id' => $addon->id,
        'product_snapshot' => json_encode($snapshot),  // Full snapshot in JSONB
        
        // Denormalized for easy queries
        'product_name' => $addon->title,
        'sku' => $addon->part_number,
        
        'price' => $price,
        'original_price' => $addon->price,
        'quantity' => $quantity,
        'tax_inclusive' => $addon->tax_inclusive ?? true,
    ]);
}
```

---

## 📊 TAX INCLUSIVE/EXCLUSIVE HANDLING

### **Per-Addon Tax Setting**

```php
// Some addons may include tax, others don't
$lugNuts = AddOn::create([
    'title' => 'Chrome Lug Nuts',
    'price' => 50.00,
    'tax_inclusive' => true,  // Price already includes tax
]);

$tpms = AddOn::create([
    'title' => 'TPMS Sensor',
    'price' => 75.00,
    'tax_inclusive' => false,  // Tax added on top
]);
```

### **Tax Calculation in Order**

```php
// Calculate total with mixed tax handling
public function calculateAddonTotals()
{
    $subtotal = 0;
    $taxAmount = 0;
    $taxRate = 0.05;  // 5% VAT

    foreach ($this->orderItems()->whereNotNull('addon_id')->get() as $item) {
        $itemSubtotal = $item->price * $item->quantity;
        
        if ($item->tax_inclusive) {
            // Extract tax from price
            $priceWithoutTax = $itemSubtotal / (1 + $taxRate);
            $itemTax = $itemSubtotal - $priceWithoutTax;
            
            $subtotal += $priceWithoutTax;
            $taxAmount += $itemTax;
        } else {
            // Add tax on top
            $itemTax = $itemSubtotal * $taxRate;
            
            $subtotal += $itemSubtotal;
            $taxAmount += $itemTax;
        }
    }

    return [
        'subtotal' => $subtotal,
        'tax' => $taxAmount,
        'total' => $subtotal + $taxAmount,
    ];
}
```

---

## Related Documentation
- [Inventory Module](ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md) - Addon inventory tracking
- [Customers Module](ARCHITECTURE_CUSTOMERS_MODULE.md) - Dealer pricing service
- [Orders Module](ARCHITECTURE_ORDERS_MODULE.md) - Addon in orders with dealer pricing
- [Research Findings](RESEARCH_FINDINGS.md) - Complete addon pricing research

---

## Changelog
- **2025-10-20:** Initial documentation
- **2025-10-20:** Added restock notification system
- **2025-10-20:** Documented category-specific attributes
- **2025-10-20:** Added CRITICAL dealer pricing for addon categories
- **2025-10-20:** Added tax_inclusive per-addon handling
- **2025-10-20:** Added addon snapshot system
- **2025-10-20:** Added usage examples with DealerPricingService
- **2025-10-20:** Updated to Laravel 12 + PostgreSQL 15
