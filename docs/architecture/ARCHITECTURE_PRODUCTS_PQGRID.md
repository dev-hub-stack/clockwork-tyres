# Products Module - Complete Architecture with pqGrid
**Excel-like Product Management System**

**Created:** October 21, 2025  
**Module:** Products, Brands, Models, Finishes  
**UI Framework:** Filament v4 + pqGrid v3.5.1  
**Integration:** DealerPricingService, ProductSyncService  

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Database Schema](#database-schema)
3. [Models & Relationships](#models--relationships)
4. [Filament Resources](#filament-resources)
5. [pqGrid Integration](#pqgrid-integration)
6. [Dealer Pricing Integration](#dealer-pricing-integration)
7. [Product Sync Service](#product-sync-service)
8. [API Endpoints](#api-endpoints)
9. [Implementation Checklist](#implementation-checklist)

---

## 🎯 Overview

### Purpose
Manage the product catalog as **lightweight reference data** with:
- ✅ Brands (BBS, Rotiform, Fuel, etc.)
- ✅ Models (wheel models like CH-R, LSR, Maverick - NOT vehicle models)
- ✅ Finishes (Gloss Black, Silver, Bronze, etc.)
- ✅ Products (complete wheel specifications)
- ✅ Variants (size/spec combinations: 20x9, 20x10.5, etc.)

### Key Features
- 🏢 **Excel-like UI** with pqGrid for bulk editing
- 💰 **Dealer Pricing Integration** via brand/model discounts
- 🔄 **Product Sync** from TunerStop Admin (external source of truth)
- 📸 **Image Management** via JSON arrays
- 📊 **Snapshot Approach** for orders/quotes (historical accuracy)

### Architecture Philosophy
```
External System (TunerStop Admin) = SOURCE OF TRUTH
         ↓
    Product Sync Service (UPSERT)
         ↓
  CRM Products Table (Reference Data)
         ↓
  ┌──────────────┬─────────────────┐
  │              │                 │
pqGrid UI    Filament Forms    API Endpoints
(Bulk Edit)  (Single Edit)    (Grid Data)
         ↓
  Order/Quote (Product Snapshot)
```

---

## 📊 Database Schema

### 1. Brands Table

```sql
CREATE TABLE brands (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    logo VARCHAR(500),                    -- Brand logo path
    description TEXT,
    external_id VARCHAR(255),             -- ID from TunerStop Admin
    external_source VARCHAR(100),
    status TINYINT DEFAULT 1,             -- 1=active, 0=inactive
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,                 -- Soft delete
    
    INDEX idx_name (name),
    INDEX idx_status (status),
    INDEX idx_external (external_id, external_source)
);

-- Sample data:
-- BBS, Rotiform, Fuel, American Racing, Niche, TSW, etc.
```

### 2. Models Table (Wheel Models)

```sql
CREATE TABLE models (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,           -- CH-R, LSR, Maverick D610, etc.
    slug VARCHAR(255),
    brand_id BIGINT NOT NULL,             -- FK to brands
    description TEXT,
    external_id VARCHAR(255),
    external_source VARCHAR(100),
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    
    INDEX idx_name (name),
    INDEX idx_brand (brand_id),
    INDEX idx_status (status)
);

-- Sample data:
-- Rotiform CH-R, Rotiform LSR, Fuel Maverick D610, BBS CH-R, etc.
```

### 3. Finishes Table

```sql
CREATE TABLE finishes (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,           -- Gloss Black, Matte Bronze, Chrome, etc.
    slug VARCHAR(255),
    color_code VARCHAR(50),               -- Hex color for UI display
    description TEXT,
    external_id VARCHAR(255),
    external_source VARCHAR(100),
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_status (status)
);

-- Sample data:
-- Gloss Black, Matte Black, Gunmetal, Silver, Bronze, Chrome, etc.
```

### 4. Products Table

```sql
CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    
    -- External Sync
    external_id VARCHAR(255),             -- ID from TunerStop Admin
    external_product_id VARCHAR(255),     -- Legacy external ID
    external_source VARCHAR(100),         -- 'tunerstop_admin', 'manual'
    
    -- Product Identification
    sku VARCHAR(100),                     -- D61020906550
    name VARCHAR(255) NOT NULL,           -- Fuel Maverick D610
    product_full_name VARCHAR(500),       -- Fuel Maverick D610 20x9 Gloss Black
    slug VARCHAR(255),
    
    -- Categorization (CRITICAL FOR DEALER PRICING!)
    brand_id BIGINT NOT NULL,             -- ✅ FK to brands
    model_id BIGINT NOT NULL,             -- ✅ FK to models (wheel models)
    finish_id BIGINT NOT NULL,            -- FK to finishes
    construction VARCHAR(100),            -- Cast, Forged, Flow Formed
    
    -- Pricing
    price DECIMAL(10,2) DEFAULT 0.00,     -- Retail price (base price)
    
    -- Media
    images JSON,                          -- ["image1.jpg", "image2.jpg", ...]
    
    -- SEO
    seo_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(500),
    
    -- Sync Management
    sync_source VARCHAR(100),
    sync_status VARCHAR(50),              -- synced, pending, error, manual
    sync_error TEXT,
    synced_at TIMESTAMP,
    sync_attempted_at TIMESTAMP,
    
    -- Status & Stock
    status TINYINT DEFAULT 1,             -- 1=active, 0=inactive
    total_quantity INT DEFAULT 0,         -- Total stock (reference only)
    views INT DEFAULT 0,                  -- View count
    
    -- Timestamps
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE RESTRICT,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE RESTRICT,
    FOREIGN KEY (finish_id) REFERENCES finishes(id) ON DELETE RESTRICT,
    
    -- Indexes
    INDEX idx_sku (sku),
    INDEX idx_brand (brand_id),
    INDEX idx_model (model_id),
    INDEX idx_finish (finish_id),
    INDEX idx_status (status),
    INDEX idx_sync_status (sync_status),
    INDEX idx_external (external_id, external_source),
    
    UNIQUE (sku)
);
```

### 5. Product Variants Table

```sql
CREATE TABLE product_variants (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL,           -- FK to products
    
    -- Variant Specifics
    size VARCHAR(50),                     -- 20x9, 20x10.5, 22x12, etc.
    width DECIMAL(5,2),                   -- 9.0, 10.5, 12.0
    diameter DECIMAL(5,2),                -- 20.0, 22.0, 24.0
    bolt_pattern VARCHAR(100),            -- 6x5.5, 5x114.3, 8x170
    offset VARCHAR(50),                   -- +1mm, -12mm, +25mm
    backspacing DECIMAL(5,2),             -- 5.5, 6.0, 4.5 inches
    center_bore DECIMAL(5,2),             -- 78.1mm, 106.1mm
    
    -- Optional Variant-Specific Fields
    finish_id BIGINT,                     -- Override finish (if variant has different finish)
    variant_sku VARCHAR(100),             -- Unique SKU for variant
    variant_price DECIMAL(10,2),          -- Override price (if different from base)
    
    -- Stock
    quantity INT DEFAULT 0,
    warehouse_location VARCHAR(100),
    
    -- Status
    status TINYINT DEFAULT 1,
    
    -- Timestamps
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (finish_id) REFERENCES finishes(id) ON DELETE SET NULL,
    
    INDEX idx_product (product_id),
    INDEX idx_size (size),
    INDEX idx_bolt_pattern (bolt_pattern),
    INDEX idx_status (status)
);
```

### 6. Product Images Table (Shared Images)

```sql
CREATE TABLE product_images (
    id BIGSERIAL PRIMARY KEY,
    
    -- Image Mapping (by brand + model + finish)
    brand_id BIGINT NOT NULL,
    model_id BIGINT NOT NULL,
    finish_id BIGINT NOT NULL,
    
    -- Images (up to 9 images per combination)
    image_1 VARCHAR(500),
    image_2 VARCHAR(500),
    image_3 VARCHAR(500),
    image_4 VARCHAR(500),
    image_5 VARCHAR(500),
    image_6 VARCHAR(500),
    image_7 VARCHAR(500),
    image_8 VARCHAR(500),
    image_9 VARCHAR(500),
    
    -- Timestamps
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE CASCADE,
    FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE,
    FOREIGN KEY (finish_id) REFERENCES finishes(id) ON DELETE CASCADE,
    
    UNIQUE (brand_id, model_id, finish_id)
);
```

---

## 🔗 Models & Relationships

### 1. Brand Model

**File:** `app/Modules/Products/Models/Brand.php`

```php
<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'external_id',
        'external_source',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Models belonging to this brand
     */
    public function models()
    {
        return $this->hasMany(ProductModel::class, 'brand_id');
    }

    /**
     * Products of this brand
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    /**
     * Customer brand pricing rules
     */
    public function customerPricing()
    {
        return $this->hasMany(\App\Modules\Customers\Models\CustomerBrandPricing::class, 'brand_id');
    }

    /**
     * Scope: Active brands only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
```

### 2. ProductModel Model (Wheel Models)

**File:** `app/Modules/Products/Models/ProductModel.php`

```php
<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductModel extends Model
{
    use SoftDeletes;

    protected $table = 'models';

    protected $fillable = [
        'name',
        'slug',
        'brand_id',
        'description',
        'external_id',
        'external_source',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Brand this model belongs to
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Products of this model
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'model_id');
    }

    /**
     * Customer model pricing rules (HIGHEST PRIORITY!)
     */
    public function customerPricing()
    {
        return $this->hasMany(\App\Modules\Customers\Models\CustomerModelPricing::class, 'model_id');
    }

    /**
     * Scope: Active models only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
```

### 3. Finish Model

**File:** `app/Modules/Products/Models/Finish.php`

```php
<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Finish extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'color_code',
        'description',
        'external_id',
        'external_source',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Products with this finish
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'finish_id');
    }

    /**
     * Scope: Active finishes only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }
}
```

### 4. Product Model

**File:** `app/Modules/Products/Models/Product.php`

```php
<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'external_id',
        'external_product_id',
        'external_source',
        'sku',
        'name',
        'product_full_name',
        'slug',
        'brand_id',
        'model_id',
        'finish_id',
        'construction',
        'price',
        'images',
        'seo_title',
        'meta_description',
        'meta_keywords',
        'sync_source',
        'sync_status',
        'sync_error',
        'synced_at',
        'sync_attempted_at',
        'status',
        'total_quantity',
        'views',
    ];

    protected $casts = [
        'images' => 'array',
        'status' => 'boolean',
        'price' => 'decimal:2',
        'synced_at' => 'datetime',
        'sync_attempted_at' => 'datetime',
    ];

    /**
     * Brand relationship
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    /**
     * Model relationship (wheel model)
     */
    public function model()
    {
        return $this->belongsTo(ProductModel::class, 'model_id');
    }

    /**
     * Finish relationship
     */
    public function finish()
    {
        return $this->belongsTo(Finish::class, 'finish_id');
    }

    /**
     * Variants relationship
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    /**
     * Active variants only
     */
    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class, 'product_id')->where('status', 1);
    }

    /**
     * Sync product images from product_images table
     */
    public function syncProductImages()
    {
        $productImage = ProductImage::where([
            'model_id' => $this->model_id,
            'brand_id' => $this->brand_id,
            'finish_id' => $this->finish_id
        ])->first();
        
        if ($productImage) {
            $images = [];
            for ($i = 1; $i <= 9; $i++) {
                $field = "image_{$i}";
                if ($productImage->{$field}) {
                    $images[] = $productImage->{$field};
                }
            }
            
            if (!empty($images)) {
                $this->images = $images;
                $this->save();
            }
        }
    }

    /**
     * Get dealer price for this product (if customer is dealer)
     */
    public function getDealerPrice($customer)
    {
        if (!$customer->isDealer()) {
            return $this->price;
        }

        $dealerPricingService = app(\App\Modules\Customers\Services\DealerPricingService::class);
        
        return $dealerPricingService->calculateProductPrice(
            $customer,
            $this->price,
            $this->model_id,
            $this->brand_id
        );
    }

    /**
     * Scope: Active products only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope: Synced products only
     */
    public function scopeSynced($query)
    {
        return $query->where('sync_status', 'synced');
    }
}
```

### 5. ProductVariant Model

**File:** `app/Modules/Products/Models/ProductVariant.php`

```php
<?php

namespace App\Modules\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'size',
        'width',
        'diameter',
        'bolt_pattern',
        'offset',
        'backspacing',
        'center_bore',
        'finish_id',
        'variant_sku',
        'variant_price',
        'quantity',
        'warehouse_location',
        'status',
    ];

    protected $casts = [
        'width' => 'decimal:2',
        'diameter' => 'decimal:2',
        'backspacing' => 'decimal:2',
        'center_bore' => 'decimal:2',
        'variant_price' => 'decimal:2',
        'quantity' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Product relationship
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Finish override (if variant has different finish)
     */
    public function finish()
    {
        return $this->belongsTo(Finish::class, 'finish_id');
    }

    /**
     * Get effective price (variant price or product price)
     */
    public function getEffectivePrice()
    {
        return $this->variant_price ?? $this->product->price;
    }

    /**
     * Scope: In stock variants
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }
}
```

---

## 🎨 Filament Resources

### 1. ProductResource with pqGrid Page

**File:** `app/Modules/Products/Filament/Resources/ProductResource.php`

```php
<?php

namespace App\Modules\Products\Filament\Resources;

use App\Modules\Products\Filament\Resources\ProductResource\Pages;
use App\Modules\Products\Models\Product;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Products';
    
    protected static ?string $navigationGroup = 'Products';
    
    protected static ?int $navigationSort = 1;

    public static function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                    
                TextInput::make('sku')
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                
                Select::make('brand_id')
                    ->relationship('brand', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                
                Select::make('model_id')
                    ->relationship('model', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                
                Select::make('finish_id')
                    ->relationship('finish', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                
                Textarea::make('description')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('sku')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('brand.name')->sortable(),
                TextColumn::make('model.name')->sortable(),
                TextColumn::make('finish.name'),
                TextColumn::make('price')->money('usd')->sortable(),
                BadgeColumn::make('sync_status')
                    ->colors([
                        'success' => 'synced',
                        'warning' => 'pending',
                        'danger' => 'error',
                    ]),
                TextColumn::make('synced_at')->dateTime(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'grid' => Pages\ManageProductsGrid::route('/grid'),  // ✨ pqGrid view
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getNavigationItems(): array
    {
        return [
            \Filament\Navigation\NavigationItem::make('Products Table')
                ->icon('heroicon-o-table')
                ->url(static::getUrl('index'))
                ->sort(1),
            \Filament\Navigation\NavigationItem::make('Products Grid')
                ->icon('heroicon-o-table-cells')
                ->url(static::getUrl('grid'))
                ->sort(2)
                ->badge('Excel-like', 'success'),
        ];
    }
}
```

---

## 💰 Dealer Pricing Integration

### Update Customer Pricing Migrations

**CRITICAL:** Uncomment foreign key relationships now that brands/models tables exist.

**File:** `database/migrations/XXXX_create_customer_brand_pricing_table.php`

```php
// UNCOMMENT THIS:
$table->foreign('brand_id')
      ->references('id')
      ->on('brands')
      ->onDelete('cascade');
```

**File:** `database/migrations/XXXX_create_customer_model_pricing_table.php`

```php
// UNCOMMENT THIS:
$table->foreign('model_id')
      ->references('id')
      ->on('models')
      ->onDelete('cascade');
```

### DealerPricingService Integration

The service is already built! It will automatically work once FKs are in place:

```php
// In OrderController, QuoteController, InvoiceController, etc.
$customer = Customer::find($customerId);
$product = Product::find($productId);

if ($customer->isDealer()) {
    $dealerPrice = $product->getDealerPrice($customer);
    // Use $dealerPrice instead of $product->price
}
```

---

## ✅ Implementation Checklist

### **Phase 1: Database & Models (Day 22-23)**

- [ ] Create `brands` migration and model
- [ ] Create `models` migration and model
- [ ] Create `finishes` migration and model
- [ ] Create `products` migration and model
- [ ] Create `product_variants` migration and model
- [ ] Create `product_images` migration and model
- [ ] Run migrations
- [ ] Update CustomerBrandPricing migration (uncomment FK)
- [ ] Update CustomerModelPricing migration (uncomment FK)
- [ ] Seed sample data (brands, models, finishes)

### **Phase 2: pqGrid UI (Day 24-25)**

- [ ] Create `ManageProductsGrid` Filament page
- [ ] Create `manage-products-grid.blade.php` view
- [ ] Create `ProductGridController` with API endpoints
- [ ] Add routes for grid API
- [ ] Test grid with sample data
- [ ] Test Excel copy/paste
- [ ] Test inline editing
- [ ] Test bulk save

### **Phase 3: Filament Forms (Day 26)**

- [ ] Create `ProductResource`
- [ ] Create `ListProducts` page
- [ ] Create `CreateProduct` page
- [ ] Create `EditProduct` page
- [ ] Create `VariantsRelationManager`
- [ ] Test CRUD operations

### **Phase 4: Product Sync (Day 27)**

- [ ] Create `ProductSyncService`
- [ ] Create sync webhook endpoint
- [ ] Test UPSERT logic
- [ ] Test image sync
- [ ] Test error handling

### **Phase 5: Testing & Documentation (Day 28)**

- [ ] Unit tests for models
- [ ] Integration tests for API
- [ ] Test dealer pricing integration
- [ ] Performance test with 10K+ records
- [ ] Update documentation

---

**END OF ARCHITECTURE**
