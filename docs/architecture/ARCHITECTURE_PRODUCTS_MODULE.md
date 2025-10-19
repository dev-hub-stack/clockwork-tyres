# Products Module - Complete Architecture Documentation

## ⚠️ CRITICAL: Product Snapshot Approach

**IMPORTANT CLARIFICATION:**  
The Products module uses a **SNAPSHOT-BASED APPROACH** where:
1. ✅ Products are synced from external system (TunerStop Admin) - lightweight reference data
2. ✅ **Product snapshot** captured at time of order/quote (stored in JSONB)
3. ✅ Denormalized fields on order_items for easy querying
4. ✅ External system is SOURCE OF TRUTH
5. ❌ NO full catalog sync (over-engineered)
6. ❌ NO deep product relationships in CRM

### **Why Snapshot Approach?**
- Historical accuracy: Product data at time of sale is preserved
- Performance: No complex joins needed for reporting
- Simplicity: CRM doesn't manage full product catalog
- Flexibility: Product changes in external system don't affect past orders

---

## Overview
The Products module manages the core product catalog in the Reporting CRM as **reference data**, with automatic synchronization from TunerStop Admin. Products support dealer pricing relationships (model/brand discounts) and snapshot-based order capturing.

**Last Updated:** October 20, 2025  
**Module Location:** `app/Models/Product.php`, `app/Http/Controllers/ProductsController.php`  
**Tech Stack:** Laravel 12 + PostgreSQL 15 + Filament v3  
**Data Ownership:** External System (TunerStop Admin) is source of truth

---

## Table of Contents
1. [Database Schema](#database-schema)
2. [Model Architecture](#model-architecture)
3. [Product Sync System](#product-sync-system)
4. [Controller Architecture](#controller-architecture)
5. [Product Images](#product-images)
6. [Relationships](#relationships)
7. [Search & Discovery](#search--discovery)
8. [Business Logic](#business-logic)

---

## Database Schema

### Products Table
**Table Name:** `products`

#### Core Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| id | bigint | Primary key | NO | AUTO_INCREMENT |
| external_id | varchar(255) | ID from TunerStop Admin | YES | NULL |
| external_product_id | varchar(255) | Legacy external ID | YES | NULL |
| external_source | varchar(100) | Source system | YES | NULL |
| name | varchar(255) | Product name | NO | - |
| product_full_name | varchar(500) | Full descriptive name | YES | NULL |
| slug | varchar(255) | URL-friendly slug | YES | NULL |

#### Categorization Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| brand_id | bigint | FK to brands table | NO | - |
| model_id | bigint | FK to models table (wheel models) | NO | - |
| finish_id | bigint | FK to finishes table | NO | - |
| construction | varchar(100) | Construction type (Cast/Forged) | YES | NULL |

#### Pricing Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| price | decimal(10,2) | Base retail price | YES | 0.00 |

#### Media Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| images | json | Product image paths (JSON array) | YES | NULL |

#### SEO Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| seo_title | varchar(255) | Page title for SEO | YES | NULL |
| meta_description | text | Meta description | YES | NULL |
| meta_keywords | varchar(500) | Meta keywords | YES | NULL |

#### Sync Management Fields
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| sync_source | varchar(100) | Sync source identifier | YES | NULL |
| sync_status | varchar(50) | Status: synced/pending/error | YES | NULL |
| sync_error | text | Last sync error message | YES | NULL |
| synced_at | timestamp | Last successful sync | YES | NULL |
| sync_attempted_at | timestamp | Last sync attempt | YES | NULL |

#### Status & Visibility
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| status | tinyint | Status: 1=active, 0=inactive | YES | 1 |
| total_quantity | int | Total stock across warehouses | YES | 0 |
| views | int | Product view count | YES | 0 |

#### Timestamps
| Column | Type | Description | Nullable | Default |
|--------|------|-------------|----------|---------|
| created_at | timestamp | Creation timestamp | YES | NULL |
| updated_at | timestamp | Last update timestamp | YES | NULL |
| deleted_at | timestamp | Soft delete timestamp | YES | NULL |

---

## Model Architecture

### File: `app/Models/Product.php`

```php
class Product extends BaseModel implements Searchable
{
    use HasEvents, Translatable;
    
    const FOLDER = 'products';
    
    protected $translatable = ['name'];
    protected static $logName = 'products';
}
```

### Key Features

#### 1. Mass Assignment Protection
```php
protected $fillable = [
    'id', 
    'external_id', 
    'name', 
    'product_full_name', 
    'slug', 
    'price', 
    'brand_id', 
    'model_id', 
    'finish_id',
    'images', 
    'construction', 
    'status', 
    'sync_source', 
    'sync_status', 
    'sync_error', 
    'synced_at', 
    'sync_attempted_at', 
    'seo_title', 
    'meta_description', 
    'meta_keywords',
    'total_quantity', 
    'views', 
    'external_product_id', 
    'external_source'
];
```

#### 2. Image Handling
```php
public function getImagesAttribute($value)
{
    $images = json_decode($value);
    if ($images) {
        for ($i = 0; $i < count($images); $i++) {
            // Handle Google Feed & Facebook Catalog actions
            if (\Request::has('action') && 
                (\request()->get('action') == 'App\Actions\GoogleFeedAction' || 
                 \request()->get('action') == 'App\Actions\FacebookCatalogAction')) {
                if ($i < 10) {
                    $images[$i] = Config::get('voyager.base_image_path').$images[$i];
                }
            }
        }
        return json_encode($images);
    }
}
```

**Features:**
- Stores images as JSON array
- Dynamic path prefixing for product feeds
- Supports up to 10 images per product

#### 3. Searchable Interface
```php
public function getSearchResult(): SearchResult
{
    $url = '/product?id='.$this->id;
    
    return new SearchResult(
        $this,
        $this->name,
        $url
    );
}
```

**Integration:** Used by Spatie Searchable for site-wide search

#### 4. Product Image Syncing
```php
public function syncProductImages()
{
    // Find matching product image based on model, brand and finish
    $productImage = ProductImage::where([
        'model_id' => $this->model_id,
        'brand_id' => $this->brand_id,
        'finish_id' => $this->finish_id
    ])->first();
    
    if ($productImage) {
        $images = [];
        // Collect all non-null images from the product image record
        for ($i = 1; $i <= 9; $i++) {
            $field = "image_{$i}";
            if ($productImage->{$field}) {
                $images[] = $productImage->{$field};
            }
        }
        
        // Update product images if we found any
        if (!empty($images)) {
            $this->images = json_encode($images);
            $this->save();
        }
    }
}
```

**Purpose:** Synchronizes product images from `product_images` table based on brand/model/finish combination

---

## Relationships

### 1. Brand (Many-to-One)
```php
public function brand()
{
    return $this->belongsTo(Brand::class);
}
```

**Description:** Product's manufacturer brand (BBS, Rotiform, etc.)

### 2. Model (Many-to-One)
```php
public function model()
{
    return $this->belongsTo(VehicleModel::class);
}
```

**Description:** Wheel model/series (CH-R, LSR, etc.)

### 3. Finish (Many-to-One)
```php
public function finish()
{
    return $this->belongsTo(Finish::class);
}
```

**Description:** Wheel finish/color (Gloss Black, Silver, etc.)

### 4. Variants (One-to-Many)
```php
public function variants()
{
    return $this->hasMany(ProductVariant::class);
}

// Alias
public function prodVariants()
{
    return $this->hasMany(ProductVariant::class);
}

// Single variant (legacy)
public function variant()
{
    return $this->hasOne(ProductVariant::class);
}
```

**Description:** Product sizes/specifications (20x8.5, 20x9.5, etc.)

**Dynamic Filtering:**
```php
public function variants()
{
    if (request()->has('finish_id') && request()->filled('finish_id')) {
        return $this->hasMany(ProductVariant::class)
            ->where('finish_id', request()->get('finish_id'));
    }
    return $this->hasMany(ProductVariant::class);
}
```

### 5. Product Inventory (One-to-Many)
```php
public function productInventory()
{
    return $this->hasMany(ProductInventory::class, 'product_id', 'id');
}

// Alias
public function inventories()
{
    return $this->hasMany(ProductInventory::class, 'product_id', 'id');
}
```

**Description:** Stock levels across different warehouses

### 6. Product AddOns (One-to-Many)
```php
public function productAddOns()
{
    return $this->hasMany(ProductAddOn::class);
}
```

**Description:** Compatible accessories (lug nuts, hub rings, etc.)

---

## Product Sync System

### Architecture Overview

```
┌─────────────────────┐
│  TunerStop Admin    │ (Source System)
│  Product Database   │
└──────────┬──────────┘
           │
           │ Webhook/API Call
           ▼
┌─────────────────────┐
│  Product Sync API   │
│  (Reporting CRM)    │
└──────────┬──────────┘
           │
           │ Validates & Processes
           ▼
┌─────────────────────┐
│ ProductSyncService  │
│  UPSERT Logic       │
└──────────┬──────────┘
           │
           ├─► Map Brand/Model/Finish IDs
           ├─► Create/Update Product
           ├─► Sync Variants
           ├─► Sync Images
           └─► Update Inventory
           
           ▼
┌─────────────────────┐
│   Product Synced    │
│   in Reporting CRM  │
└─────────────────────┘
```

### Service: `ProductSyncService.php`

**File:** `app/Services/ProductSyncService.php`

#### Class Constants
```php
const SYNC_SOURCE_TUNERSTOP = 'tunerstop_admin';
```

#### Key Methods

**1. syncProduct(array $productData): array**

**Purpose:** Sync single product from TunerStop Admin

**Process:**
```php
public function syncProduct(array $productData): array
{
    DB::beginTransaction();
    
    try {
        // 1. Validate product data structure
        $validationResult = $this->validateProductData($productData);
        if (!$validationResult['valid']) {
            throw new \Exception("Product validation failed");
        }
        
        // 2. UPSERT pattern - check if product exists and update or create
        $product = $this->upsertProduct($productData);
        
        // 3. Sync related data
        $this->syncProductImages($product, $productData['images'] ?? []);
        $this->syncProductVariants($product, $productData['variants'] ?? []);
        
        DB::commit();
        
        return [
            'success' => true,
            'product_id' => $product->id,
            'external_id' => $product->external_id
        ];
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Product sync failed", ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
```

**2. upsertProduct(array $productData): Product**

**Purpose:** Update existing product or create new one

**Logic:**
```php
protected function upsertProduct(array $productData): Product
{
    // Check for existing product by external ID first
    $existingProduct = null;
    
    if (!empty($productData['id'])) {
        $existingProduct = Product::where('external_id', $productData['id'])
            ->where('sync_source', self::SYNC_SOURCE_TUNERSTOP)
            ->first();
    }
    
    // If not found by external_id, check variants for existing product
    if (!$existingProduct && !empty($productData['sku'])) {
        $variantWithSku = ProductVariant::where('sku', $productData['sku'])->first();
        if ($variantWithSku) {
            $existingProduct = $variantWithSku->product;
        }
    }
    
    // Map product data
    $mappedData = $this->mapProductData($productData);
    
    if ($existingProduct) {
        // Update existing product
        $existingProduct->update($mappedData);
        return $existingProduct->fresh();
    } else {
        // Create new product
        return Product::create($mappedData);
    }
}
```

**3. mapProductData(array $productData): array**

**Purpose:** Transform TunerStop product data to Reporting format

```php
protected function mapProductData(array $productData): array
{
    return [
        'external_id' => $productData['id'] ?? null,
        'name' => $productData['name'] ?? '',
        'product_full_name' => $productData['product_full_name'] ?? $productData['name'],
        'slug' => $this->generateSlug($productData['name'] ?? ''),
        'price' => $productData['price'] ?? 0,
        'brand_id' => $this->mapBrandId($productData['brand_id'] ?? null),
        'model_id' => $this->mapModelId($productData['model_id'] ?? null),
        'finish_id' => $this->mapFinishId($productData['finish_id'] ?? null),
        'construction' => $productData['construction'] ?? '',
        'status' => $this->mapStatus($productData['status'] ?? 'active'),
        'images' => json_encode($productData['images'] ?? []),
        'seo_title' => $productData['seo_title'] ?? $productData['name'],
        'meta_description' => $productData['meta_description'] ?? '',
        'meta_keywords' => $productData['meta_keywords'] ?? '',
        'synced_at' => Carbon::now(),
        'sync_source' => self::SYNC_SOURCE_TUNERSTOP,
        'sync_status' => 'synced',
        'sync_error' => null,
        'sync_attempted_at' => Carbon::now(),
        'external_source' => 'tunerstop_admin',
    ];
}
```

**4. Mapping Services (Brand/Model/Finish)**

**Brand Mapping:**
```php
protected function mapBrandId($tunerstopBrandId): ?int
{
    if (!$tunerstopBrandId) return null;
    
    $brandMapping = app(BrandMappingService::class);
    $mappedId = $brandMapping->mapBrandId($tunerstopBrandId);
    
    // If no mapping exists, create the brand
    if (!$mappedId) {
        $mappedId = $this->createBrandFromTunerStop($tunerstopBrandId);
    }
    
    return $mappedId;
}
```

**Model Mapping:**
```php
protected function mapModelId($tunerstopModelId): ?int
{
    if (!$tunerstopModelId) return null;
    
    $modelMapping = app(ModelMappingService::class);
    $mappedId = $modelMapping->mapModelId($tunerstopModelId);
    
    // If no mapping exists, create the model
    if (!$mappedId) {
        $mappedId = $this->createModelFromTunerStop($tunerstopModelId);
    }
    
    return $mappedId;
}
```

**Finish Mapping:**
```php
protected function mapFinishId($tunerstopFinishId): ?int
{
    if (!$tunerstopFinishId) return null;
    
    $finishMapping = app(FinishMappingService::class);
    $mappedId = $finishMapping->mapFinishId($tunerstopFinishId);
    
    // If no mapping exists, create the finish
    if (!$mappedId) {
        $mappedId = $this->createFinishFromTunerStop($tunerstopFinishId);
    }
    
    return $mappedId;
}
```

**5. Auto-Create Brand/Model/Finish**

**Create Brand:**
```php
protected function createBrandFromTunerStop(int $tunerstopBrandId): ?int
{
    try {
        // Get brand data from TunerStop
        $tunerstopBrand = DB::connection('tunerstop')
            ->table('brands')
            ->where('id', $tunerstopBrandId)
            ->first();
        
        if (!$tunerstopBrand) return null;
        
        // Create brand in Reporting system
        $reportingBrand = Brand::create([
            'external_id' => $tunerstopBrandId,
            'name' => $tunerstopBrand->name,
            'slug' => \Str::slug($tunerstopBrand->name),
            'image' => $tunerstopBrand->image,
            'description' => $tunerstopBrand->description,
            'uae' => $tunerstopBrand->uae,
            'kuwait' => $tunerstopBrand->kuwait,
            'bahrain' => $tunerstopBrand->bahrain,
            'oman' => $tunerstopBrand->oman,
            'ksa' => $tunerstopBrand->ksa,
            'seo_title' => $tunerstopBrand->seo_title ?? $tunerstopBrand->name,
            'meta_description' => $tunerstopBrand->meta_description,
            'meta_keywords' => $tunerstopBrand->meta_keywords,
        ]);
        
        Log::info('Brand created in Reporting system', [
            'tunerstop_brand_id' => $tunerstopBrandId,
            'reporting_brand_id' => $reportingBrand->id,
            'action' => 'auto_created_during_sync'
        ]);
        
        return $reportingBrand->id;
        
    } catch (\Exception $e) {
        Log::error('Failed to create brand', ['error' => $e->getMessage()]);
        return null;
    }
}
```

**6. syncProductImages(Product $product, array $images): void**

```php
protected function syncProductImages(Product $product, array $images): void
{
    if (empty($images)) return;
    
    // Skip if missing required mappings
    if (!$product->model_id || !$product->brand_id || !$product->finish_id) {
        Log::warning("Skipping image sync - missing required mappings");
        return;
    }
    
    // Delete existing images
    ProductImage::where([
        'model_id' => $product->model_id,
        'brand_id' => $product->brand_id,
        'finish_id' => $product->finish_id
    ])->delete();
    
    // Create new images (max 9)
    foreach ($images as $index => $imagePath) {
        if ($index >= 9) break;
        
        ProductImage::create([
            'model_id' => $product->model_id,
            'brand_id' => $product->brand_id,
            'finish_id' => $product->finish_id,
            "image_" . ($index + 1) => $imagePath
        ]);
    }
}
```

**7. syncProductVariants(Product $product, array $variants): void**

```php
protected function syncProductVariants(Product $product, array $variants): void
{
    foreach ($variants as $variantData) {
        $sku = $variantData['sku'] ?? null;
        
        if (!$sku) {
            \Log::warning('Skipping variant without SKU');
            continue;
        }
        
        $price = $variantData['price'] ?? $variantData['us_retail_price'] ?? 0;
        
        ProductVariant::updateOrCreate(
            ['sku' => $sku],
            [
                'product_id' => $product->id,
                'external_variant_id' => $variantData['id'] ?? null,
                'external_source' => self::SYNC_SOURCE_TUNERSTOP,
                'size' => $variantData['size'] ?? null,
                'bolt_pattern' => $variantData['bolt_pattern'] ?? null,
                'hub_bore' => $variantData['hub_bore'] ?? null,
                'offset' => $variantData['offset'] ?? null,
                'weight' => $variantData['weight'] ?? null,
                'backspacing' => $variantData['backspacing'] ?? null,
                'lipsize' => $variantData['lipsize'] ?? null,
                'finish' => $variantData['finish'] ?? null,
                'max_wheel_load' => $variantData['max_wheel_load'] ?? null,
                'rim_diameter' => $variantData['rim_diameter'] ?? null,
                'rim_width' => $variantData['rim_width'] ?? null,
                'cost' => $variantData['cost'] ?? null,
                'price' => $price,
                'us_retail_price' => $variantData['us_retail_price'] ?? null,
                'uae_retail_price' => $variantData['uae_retail_price'] ?? null,
                'sale_price' => $variantData['sale_price'] ?? null,
                'clearance_corner' => $variantData['clearance_corner'] ?? 0,
                'construction' => $variantData['construction'] ?? null,
            ]
        );
    }
}
```

**8. Batch Sync (Performance Optimized)**

```php
public function syncProductsBatch(array $productsData): array
{
    // Resource management settings
    $chunkSize = 25; // Process in smaller chunks
    $maxExecutionTime = 300; // 5 minutes max
    $startTime = microtime(true);
    
    $results = [];
    $processedCount = 0;
    
    // Process in chunks
    $chunks = array_chunk($productsData, $chunkSize);
    
    foreach ($chunks as $chunkIndex => $chunk) {
        // Memory and time checks
        $currentMemory = memory_get_usage(true);
        $memoryUsageMB = round($currentMemory / 1024 / 1024, 2);
        
        // Stop if using more than 256MB
        if ($memoryUsageMB > 256) {
            $this->errors[] = "Memory usage too high - aborting";
            break;
        }
        
        // Process chunk
        foreach ($chunk as $productData) {
            $result = $this->syncProduct($productData);
            $results[] = $result;
            $processedCount++;
            
            // Garbage collection
            if ($processedCount % 10 === 0) {
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }
    }
    
    return [
        'total_processed' => $processedCount,
        'successful' => $this->successCount,
        'failed' => $this->failureCount,
        'errors' => $this->errors
    ];
}
```

---

## Product Import System (CSV)

### Static Method: import()
```php
public static function import($data, $model_id, $finish_id, $brand_id)
{
    $images = [];
    for ($i = 1; $i <= 11; $i++) {
        if (isset($data['image'.$i]) && !empty($data['image'.$i])) {
            $images[] = self::FOLDER.'/'.$data['image'.$i];
        }
    }
    
    $record = [
        'name' => trim($data['model']),
        'price' => $data['usretail'] ?? null,
        'brand_id' => $brand_id,
        'model_id' => $model_id,
        'finish_id' => $finish_id,
        'construction' => $data['construction'],
        'images' => json_encode($images),
        'meta_description' => "Buy {$data['model']} online on TunerStop.com...",
        'seo_title' => "{$data['brand']} {$data['model']} Alloy Wheels...",
    ];
    
    $product = self::updateOrCreate([
        'model_id' => $model_id,
        'finish_id' => $finish_id,
        'brand_id' => $brand_id,
    ], $record);
    
    $product->syncProductImages();
    
    return $product;
}
```

---

## Product Images System

### ProductImage Model
**Table:** `product_images`

**Structure:**
- `model_id`: FK to models
- `brand_id`: FK to brands
- `finish_id`: FK to finishes
- `image_1` through `image_9`: Image paths

**Unique Constraint:** Combination of (model_id, brand_id, finish_id)

**Purpose:** Stores shared images for product combinations

---

## Advanced Scopes

### Normalized Weight Scope
```php
public function scopeWithNormalizedWeight($query, $inputValue)
{
    // Extract numeric value and unit from input
    preg_match('/(\d+\.?\d*)\s*(KG|KGs|LBS|LB|Lbs|lbs|lb)?/i', $inputValue, $matches);
    
    $inputValueNumeric = (float) ($matches[1] ?? 0);
    $inputUnit = strtoupper($matches[2] ?? 'KG');
    
    // Convert to kilograms if needed
    $normalizedInputValue = $inputUnit === 'LBS' || $inputUnit === 'LB' 
        ? $inputValueNumeric * 0.453592 
        : $inputValueNumeric;
    
    return $query->whereRaw("
        (CASE
            WHEN pv.max_wheel_load LIKE '%KG%'
                THEN CAST(REGEXP_REPLACE(pv.max_wheel_load, '[^0-9.]+', '') AS DECIMAL(10, 2))
            WHEN pv.max_wheel_load LIKE '%LBS%' THEN
                CAST(REGEXP_REPLACE(pv.max_wheel_load, '[^0-9.]+', '') AS DECIMAL(10, 2)) * 0.453592
            ELSE CAST(pv.max_wheel_load AS DECIMAL(10, 2))
        END) >= ?
    ", [$normalizedInputValue]);
}
```

**Usage:** Filter products by wheel load capacity, automatically converting units

---

## API Endpoints

### Product Sync API
**Base:** `/api/sync/products`

#### 1. Sync Single Product
```
POST /api/sync/product
Authorization: Bearer {token}
Content-Type: application/json

{
  "id": 123,
  "name": "BBS CH-R",
  "brand_id": 5,
  "model_id": 45,
  "finish_id": 12,
  "price": 450.00,
  "construction": "Forged",
  "status": "active",
  "images": [
    "products/bbs-ch-r-1.jpg",
    "products/bbs-ch-r-2.jpg"
  ],
  "variants": [
    {
      "sku": "BBS-CH-R-20-85-BM",
      "size": "20x8.5",
      "price": 450.00
    }
  ]
}

Response:
{
  "success": true,
  "product_id": 456,
  "external_id": 123,
  "main_sku": "BBS-CH-R-20-85-BM"
}
```

#### 2. Queue Product Sync
```
POST /api/sync/product/queue
Authorization: Bearer {token}

{
  "id": 123,
  "name": "BBS CH-R"
}

Response:
{
  "success": true,
  "message": "Product queued for sync",
  "queued_at": "2025-10-20T14:30:00Z"
}
```

#### 3. Batch Sync
```
POST /api/sync/products/batch
Authorization: Bearer {token}

{
  "products": [
    { /* product 1 */ },
    { /* product 2 */ }
  ]
}

Response:
{
  "total_processed": 10,
  "successful": 9,
  "failed": 1,
  "errors": [...]
}
```

---

## Performance Optimization

### Database Indexes
```sql
CREATE INDEX idx_products_external_id ON products(external_id);
CREATE INDEX idx_products_brand_id ON products(brand_id);
CREATE INDEX idx_products_model_id ON products(model_id);
CREATE INDEX idx_products_finish_id ON products(finish_id);
CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_products_sync_status ON products(sync_status);
```

### Caching Strategies
- Cache brand/model/finish mappings
- Cache product images
- Cache popular product queries

---

## 🔄 PRODUCT SNAPSHOT SYSTEM

### **Purpose**
Capture complete product data at time of order/quote to preserve historical accuracy.

### **Snapshot Creation**

```php
// app/Services/ProductSnapshotService.php
namespace App\Services;

class ProductSnapshotService
{
    /**
     * Create product snapshot for order item
     */
    public function createSnapshot($product): array
    {
        return [
            // Core product data
            'id' => $product->id,
            'external_id' => $product->external_id,
            'external_source' => $product->external_source,
            'name' => $product->name,
            'product_full_name' => $product->product_full_name,
            'slug' => $product->slug,
            
            // Categorization
            'brand' => [
                'id' => $product->brand_id,
                'name' => $product->brand->name ?? null,
                'slug' => $product->brand->slug ?? null,
            ],
            'model' => [
                'id' => $product->model_id,
                'name' => $product->model->name ?? null,
                'slug' => $product->model->slug ?? null,
            ],
            'finish' => [
                'id' => $product->finish_id,
                'name' => $product->finish->name ?? null,
                'slug' => $product->finish->slug ?? null,
            ],
            
            // Pricing
            'retail_price' => $product->price,
            
            // If variant
            'variant_details' => $product->variant ? [
                'sku' => $product->variant->sku,
                'size' => $product->variant->size,
                'bolt_pattern' => $product->variant->bolt_pattern,
                'offset' => $product->variant->offset,
                'width' => $product->variant->width,
                'diameter' => $product->variant->diameter,
                'construction' => $product->variant->construction,
                'weight' => $product->variant->weight,
                'load_rating' => $product->variant->load_rating,
            ] : null,
            
            // Images
            'images' => $product->productImages->pluck('url')->toArray(),
            
            // SEO
            'seo' => [
                'title' => $product->seo_title,
                'description' => $product->meta_description,
                'keywords' => $product->meta_keywords,
            ],
            
            // Metadata
            'captured_at' => now()->toIso8601String(),
            'captured_by' => auth()->id(),
        ];
    }
}
```

### **Usage in Orders**

```php
// When adding product to order
public function addItem($product, $quantity, $customer)
{
    $snapshotService = app(ProductSnapshotService::class);
    $dealerPricingService = app(DealerPricingService::class);
    
    // Create snapshot
    $snapshot = $snapshotService->createSnapshot($product);
    
    // Calculate price with dealer discount
    $price = $dealerPricingService->calculatePrice($customer, $product, 'product');
    
    OrderItem::create([
        'order_id' => $this->id,
        'product_id' => $product->id,  // Reference only
        'external_product_id' => $product->external_id,
        'external_source' => $product->external_source,
        'product_snapshot' => json_encode($snapshot),  // Full snapshot in JSONB
        
        // Denormalized for easy queries (no joins needed)
        'product_name' => $product->name,
        'brand_name' => $product->brand->name ?? null,
        'model_name' => $product->model->name ?? null,
        'sku' => $product->variant->sku ?? null,
        'size' => $product->variant->size ?? null,
        'bolt_pattern' => $product->variant->bolt_pattern ?? null,
        
        'price' => $price,
        'original_price' => $product->price,
        'quantity' => $quantity,
        'tax_inclusive' => $product->tax_inclusive ?? true,
    ]);
}
```

### **PostgreSQL JSONB Benefits**

```sql
-- Query snapshot data efficiently
SELECT 
    id,
    product_name,
    product_snapshot->'brand'->>'name' as brand,
    product_snapshot->'variant_details'->>'size' as size,
    (product_snapshot->>'retail_price')::numeric as retail_price
FROM order_items
WHERE product_snapshot->'brand'->>'name' = 'Rotiform';

-- Create GIN index for fast JSON queries
CREATE INDEX idx_order_items_snapshot ON order_items USING GIN (product_snapshot);
```

---

## 🎯 DEALER PRICING RELATIONSHIPS

### **Purpose**
Products support dealer pricing through brand_id and model_id relationships.

### **Brand-Level Pricing**

```php
// app/Models/Product.php
public function brand()
{
    return $this->belongsTo(Brand::class);
}

// Check if customer has brand discount
public function getDealerPriceForCustomer(Customer $customer)
{
    if ($customer->customer_type !== 'dealer') {
        return $this->price;
    }

    // Check model discount first (higher priority)
    if ($this->model_id) {
        $modelDiscount = CustomerModelPricing::where('customer_id', $customer->id)
            ->where('model_id', $this->model_id)
            ->first();
        
        if ($modelDiscount) {
            return $this->price * (1 - $modelDiscount->discount_percentage / 100);
        }
    }

    // Check brand discount
    if ($this->brand_id) {
        $brandDiscount = CustomerBrandPricing::where('customer_id', $customer->id)
            ->where('brand_id', $this->brand_id)
            ->first();
        
        if ($brandDiscount) {
            return $this->price * (1 - $brandDiscount->discount_percentage / 100);
        }
    }

    return $this->price;
}
```

### **Model-Level Pricing (Highest Priority)**

```php
// app/Models/Product.php
public function model()
{
    return $this->belongsTo(Model::class);  // Product model (e.g., "BLQ")
}

// Example: Dealer gets 15% off "Rotiform BLQ" model
$customer = Customer::find(1);  // Dealer customer
$product = Product::where('model_id', 5)->first();  // Rotiform BLQ

$dealerPrice = $product->getDealerPriceForCustomer($customer);
// If retail price is $500 and dealer has 15% model discount:
// $dealerPrice = $425 ($500 - 15%)
```

### **Pricing Rules Table Relationships**

```php
// app/Models/Brand.php
public function customerPricing()
{
    return $this->hasMany(CustomerBrandPricing::class);
}

public function getDealersWithDiscount()
{
    return $this->customerPricing()
        ->with('customer')
        ->get()
        ->pluck('customer');
}

// app/Models/Model.php (Product Model)
public function customerPricing()
{
    return $this->hasMany(CustomerModelPricing::class);
}
```

---

## 🔄 SIMPLIFIED SYNC APPROACH

### **What NOT To Sync**

❌ **DON'T sync full product catalog** (over-engineered)  
❌ **DON'T create deep product relationships** (unnecessary complexity)  
❌ **DON'T manage inventory in CRM** (external system is source of truth)

### **What TO Sync**

✅ **DO sync product reference data** (ID, name, brand, model, price)  
✅ **DO sync for products in orders/quotes** (on-demand)  
✅ **DO create snapshot when adding to order** (historical accuracy)  
✅ **DO sync brand/model for dealer pricing** (business requirement)

### **On-Demand Sync**

```php
// app/Services/ProductSyncService.php
public function syncProductForOrder($externalProductId, $externalSource)
{
    // Check if product exists
    $product = Product::where('external_id', $externalProductId)
        ->where('external_source', $externalSource)
        ->first();

    if (!$product) {
        // Fetch from external system
        $externalData = $this->fetchFromExternal($externalProductId, $externalSource);
        
        // Create lightweight product record
        $product = Product::create([
            'external_id' => $externalProductId,
            'external_source' => $externalSource,
            'name' => $externalData['name'],
            'brand_id' => $this->getBrandId($externalData['brand']),
            'model_id' => $this->getModelId($externalData['model']),
            'price' => $externalData['price'],
            'sync_status' => 'synced',
            'synced_at' => now(),
        ]);
    }

    return $product;
}
```

---

## Related Documentation
- [Product Variants Module](ARCHITECTURE_VARIANTS_MODULE.md) - Variant snapshot details
- [Product Inventory Module](ARCHITECTURE_INVENTORY_WAREHOUSE_MODULE.md) - Reference-only inventory
- [Product Sync Processes](ARCHITECTURE_SYNC_PROCESSES.md) - On-demand sync approach
- [Orders Module](ARCHITECTURE_ORDERS_MODULE.md) - Product snapshot usage
- [Customers Module](ARCHITECTURE_CUSTOMERS_MODULE.md) - Dealer pricing service
- [Research Findings](RESEARCH_FINDINGS.md) - Complete product snapshot research

---

## Changelog
- **2025-10-20:** Initial comprehensive documentation
- **2025-10-20:** Added sync system details
- **2025-10-20:** Documented mapping services
- **2025-10-20:** Added CRITICAL product snapshot approach documentation
- **2025-10-20:** Added dealer pricing relationship details
- **2025-10-20:** Added simplified sync approach (on-demand, not full catalog)
- **2025-10-20:** Added PostgreSQL JSONB snapshot benefits
- **2025-10-20:** Updated to Laravel 12 + PostgreSQL 15
