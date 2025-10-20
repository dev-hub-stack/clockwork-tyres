# Products Module - Filament Resources Implementation Plan

**Date:** October 21, 2025  
**Filament Version:** v4.x  
**Reference:** FILAMENT_V4_LESSONS_LEARNED.md

## Key Filament v4 Patterns to Follow

### ✅ Correct Imports
```php
// Schema/Layout
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

// Form Components
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

// Table
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

// Actions (CRITICAL!)
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;

// Enum Support
use BackedEnum;
use UnitEnum;
```

### ✅ Correct Method Signatures
```php
// Form uses Schema->components()
public static function form(Schema $schema): Schema
{
    return $schema->components([
        // fields
    ]);
}

// Table uses recordActions and toolbarActions
public static function table(Table $table): Table
{
    return $table
        ->columns([...])
        ->recordActions([...])  // NOT ->actions()
        ->toolbarActions([...]); // NOT ->bulkActions()
}
```

## Resources to Create

### 1. BrandResource (SIMPLE) ✅ Priority: HIGH
**Purpose:** Manage wheel brands (Fuel, XD Series, etc.)

**Fields:**
- name (required, unique)
- slug (auto-generated)
- logo (file upload)
- description (textarea)
- status (toggle or select)

**Features:**
- List all brands
- Create/edit/delete
- Search by name
- Filter by status
- Soft delete support

**File:** `app/Filament/Resources/BrandResource.php`

### 2. ProductModelResource (MEDIUM) ✅ Priority: HIGH
**Purpose:** Manage wheel models (Assault, Maverick, etc.)

**Fields:**
- brand_id (select with relationship)
- name (required)
- slug (auto-generated)
- description (textarea)
- status (toggle)

**Features:**
- List all models with brand name
- Filter by brand
- Create/edit/delete
- Searchable brand select
- Soft delete support

**File:** `app/Filament/Resources/ProductModelResource.php`

**Note:** Named "ProductModelResource" to avoid conflict with Laravel's Model class

### 3. FinishResource (SIMPLE) ✅ Priority: HIGH
**Purpose:** Manage finishes (Matte Black, Gloss Black, etc.)

**Fields:**
- name (required, unique)
- slug (auto-generated)
- hex_color (color picker)
- image_path (file upload)
- description (textarea)
- status (toggle)

**Features:**
- List all finishes with color preview
- Create/edit/delete
- Color picker for hex_color
- Search by name
- Soft delete support

**File:** `app/Filament/Resources/FinishResource.php`

### 4. ProductResource (COMPLEX) ⏳ Priority: MEDIUM
**Purpose:** Manage complete wheel products

**Fields:**
- sku (auto-generated or manual)
- name (required)
- brand_id, model_id, finish_id (selects with relationships)
- description (rich text)
- base_price, retail_price, dealer_price (money inputs)
- specifications (repeater or JSON)
- status (toggle)
- is_featured (toggle)

**Features:**
- List with brand/model/finish names
- Create/edit/delete
- Relation manager for variants
- Image gallery
- Price management
- Inventory tracking

**File:** `app/Filament/Resources/ProductResource.php`
**Relation Manager:** `ProductResource/RelationManagers/VariantsRelationManager.php`

### 5. ProductImageResource (OPTIONAL) ⏸️ Priority: LOW
**Purpose:** Manage shared images for brand+model+finish combinations

**Fields:**
- brand_id, model_id, finish_id
- image_1 through image_9 (multiple file uploads)

**Features:**
- Upload multiple images
- Preview gallery
- Association with brand/model/finish

**Note:** This might be better as a RelationManager on ProductResource

## Implementation Order

### Phase 1: Basic Resources (Today) 🎯
1. ✅ BrandResource (30 min)
2. ✅ FinishResource (30 min)
3. ✅ ProductModelResource (45 min)

**Total Time:** ~2 hours

### Phase 2: Complex Resources (Tomorrow) ⏭️
4. ⏳ ProductResource with VariantsRelationManager (2-3 hours)
5. ⏳ Product image management (1 hour)

### Phase 3: pqGrid Integration (Next) 📊
6. ⏳ ProductGridController API
7. ⏳ Excel-like grid UI for bulk editing

## Command to Generate Resources

### Option 1: Auto-Generate (May need fixes)
```bash
php artisan make:filament-resource Brand --generate
php artisan make:filament-resource Finish --generate
php artisan make:filament-resource ProductModel --generate --model-namespace="App\Modules\Products\Models"
```

### Option 2: Manual Creation (Recommended for v4)
Create files manually using the correct Filament v4 patterns from FILAMENT_V4_LESSONS_LEARNED.md

**Why Manual?**
- Auto-generate might use v3 patterns
- More control over field types
- Easier to add custom logic
- Follows established patterns in Settings module

## Testing Checklist (For Each Resource)

After creating each resource:

- [ ] Run `php artisan optimize:clear`
- [ ] List page loads without errors
- [ ] Create form opens
- [ ] Create action saves record
- [ ] Edit form loads with data
- [ ] Edit action updates record
- [ ] Delete action works (soft delete)
- [ ] Filters work correctly
- [ ] Search works
- [ ] Bulk actions work
- [ ] Relationships display correctly

## Navigation Structure

```
Products (Navigation Group)
├── Brands
├── Models
├── Finishes
└── Products
    └── (Variants as relation manager)
```

## Files to Create

### Resources
```
app/Filament/Resources/
├── BrandResource.php
├── FinishResource.php
├── ProductModelResource.php
└── ProductResource.php
```

### Pages (Auto-generated or manual)
```
app/Filament/Resources/BrandResource/Pages/
├── ListBrands.php
├── CreateBrand.php
└── EditBrand.php

app/Filament/Resources/FinishResource/Pages/
├── ListFinishes.php
├── CreateFinish.php
└── EditFinish.php

app/Filament/Resources/ProductModelResource/Pages/
├── ListProductModels.php
├── CreateProductModel.php
└── EditProductModel.php

app/Filament/Resources/ProductResource/Pages/
├── ListProducts.php
├── CreateProduct.php
└── EditProduct.php
```

### Relation Managers
```
app/Filament/Resources/ProductResource/RelationManagers/
├── VariantsRelationManager.php
└── ImagesRelationManager.php
```

## Next Action

**Start with BrandResource** - It's the simplest and will validate our Filament v4 approach.

```bash
# DON'T run auto-generate
# Create manually to ensure Filament v4 compatibility
```

---

**Status:** Ready to implement  
**Estimated Time:** 2 hours for Phase 1  
**Reference:** Use CustomerResource as template (already v4 compatible)
