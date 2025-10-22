# 🎯 NEXT STEPS: AddOns Module

## ✅ Week 3 Complete! (Products Module 100%)

### What We Just Finished:
- ✅ Products Backend (6 models, 6 migrations)
- ✅ Products Filament Resources (Brands, Models, Finishes)
- ✅ Products pqGrid with Excel-like editing
- ✅ Bulk CSV Import with image handling
- ✅ Product Images view with CloudFront integration
- ✅ Automatic image sync on product import/save

**Total Progress:** Week 3 is 100% COMPLETE! 🎉

---

## 📋 Week 4: AddOns Module (Starting NOW!)

### Priority: **HIGH** - Required for Quotes and Orders

### Overview
AddOns are accessories/additional items that can be added to wheel orders:
- Wheel locks
- Lug nuts
- Center caps
- TPMS sensors
- Installation kits
- etc.

### Key Features Needed:
1. **Addon Categories** - Group addons (Hardware, Accessories, Services)
2. **Addon Snapshots** - Freeze addon prices in quotes/orders
3. **Dealer Pricing** - Apply customer-specific addon pricing
4. **Stock Tracking** - Monitor addon inventory
5. **pqGrid Interface** - Excel-like addon management

---

## 🏗️ Implementation Plan

### Step 1: Database Migrations (Day 24)
**Files to create:**
```
database/migrations/
├── 2025_10_24_000001_create_addon_categories_table.php
└── 2025_10_24_000002_create_addons_table.php
```

**Tables:**
1. `addon_categories`
   - id, name, slug, description, status, created_at, updated_at

2. `addons`
   - id, category_id, name, sku, description, image
   - cost_price, retail_price, wholesale_price, dealer_price
   - stock_quantity, low_stock_threshold, supplier
   - status (active/inactive), created_at, updated_at, deleted_at

**Relationships:**
- AddonCategory hasMany Addons
- Addon belongsTo AddonCategory
- Customer hasMany DealerAddonPricing (already exists!)

---

### Step 2: Eloquent Models (Day 24)
**Files to create:**
```
app/Modules/Addons/Models/
├── AddonCategory.php
└── Addon.php
```

**Model Features:**
- Soft deletes
- Scopes (active, forCategory, inStock)
- Relationships
- Accessors for formatted prices
- Stock management methods

---

### Step 3: AddonSnapshotService (Day 24-25)
**File:** `app/Services/AddonSnapshotService.php`

**Purpose:** Freeze addon prices when added to quotes/orders

**Methods:**
- `createSnapshot(Addon $addon, Customer $customer)` - Create price snapshot
- `getSnapshotData(Addon $addon, Customer $customer)` - Get addon data with pricing
- Apply dealer pricing if exists
- Return JSONB-ready structure

**Similar to:** ProductSnapshotService pattern

---

### Step 4: Filament Resource (Day 25)
**File:** `app/Filament/Resources/AddonResource.php`

**Features:**
- Category filter
- Stock level indicators
- Price management
- Image upload
- SKU generation
- Status toggle
- Search by name/SKU

**Pattern:** Follow BrandResource template (Filament v3)

---

### Step 5: pqGrid Interface (Day 25-26)
**Files:**
```
app/Http/Controllers/AddonGridController.php
resources/views/addons/grid.blade.php
public/js/addons-grid.js
routes/web.php (add addon routes)
```

**Features:**
- Excel-like editing
- Category dropdown
- Stock tracking
- Price columns (cost, retail, wholesale, dealer)
- Bulk import from CSV
- Change tracking
- Frozen columns

**Pattern:** Follow ProductVariantGridController

---

### Step 6: Testing & Documentation (Day 26)
- Unit tests for Addon model
- Feature tests for CRUD operations
- AddonSnapshotService tests
- Pricing integration tests
- Create ADDONS_MODULE_COMPLETE.md

---

## 📊 Expected Timeline

| Day | Date | Tasks | Hours |
|-----|------|-------|-------|
| 24 | Oct 24 | Migrations + Models | 4-6h |
| 25 | Oct 25 | Snapshot Service + Filament Resource | 6-8h |
| 26 | Oct 26 | pqGrid Interface + CSV Import | 6-8h |
| 27 | Oct 27 | Testing + Documentation | 4-6h |

**Total Estimate:** 20-28 hours (3-4 days)

---

## 🎯 Success Criteria

### Minimum Viable Product (MVP):
- ✅ Can create/edit/delete addons
- ✅ Can organize addons by category
- ✅ Can bulk import addons from CSV
- ✅ Addon prices apply customer-specific pricing
- ✅ AddonSnapshotService freezes prices in orders
- ✅ Stock levels tracked

### Nice to Have:
- Addon combo/bundles (e.g., "Locking Lug Nuts Set")
- Supplier management
- Auto-reorder notifications
- Addon images gallery
- Related products suggestions

---

## 📁 Reference Files

### Similar Implementations:
1. **Products Module** - For pqGrid pattern
   - `app/Http/Controllers/ProductVariantGridController.php`
   - `resources/views/products/grid.blade.php`

2. **Customers Module** - For Filament resource pattern
   - `app/Filament/Resources/CustomerResource.php`

3. **DealerPricingService** - For pricing logic
   - `app/Services/DealerPricingService.php`
   - Already handles `dealer_addon_pricing` table!

---

## 🔗 Dependencies

### Already Complete:
- ✅ `dealer_addon_pricing` table (created in Customers module)
- ✅ DealerPricingService (can handle addon pricing)
- ✅ pqGrid integration (proven with Products)
- ✅ CSV import pattern (proven with Products)

### What We Need:
- 📦 AddonSnapshotService (new)
- 📦 Addon models (new)
- 📦 Addon migrations (new)

---

## 💡 Implementation Notes

### Pricing Hierarchy:
1. Customer-specific addon pricing (dealer_addon_pricing)
2. Addon's dealer_price (default wholesale)
3. Addon's retail_price (fallback)

### Snapshot Structure (JSONB):
```json
{
  "addon_id": 123,
  "sku": "LN-BLACK-12",
  "name": "Black Locking Lug Nuts",
  "category": "Hardware",
  "price": 45.00,
  "quantity": 4,
  "total": 180.00,
  "snapshot_date": "2025-10-24 10:30:00"
}
```

### CSV Import Format:
```csv
Category,SKU,Name,Description,Cost Price,Retail Price,Wholesale Price,Stock
Hardware,LN-BLACK-12,Black Locking Lug Nuts,Set of 4,15.00,65.00,45.00,100
Accessories,CC-CHROME,Chrome Center Caps,Set of 4,8.00,35.00,25.00,50
```

---

## 🚀 Let's Start!

### First Command:
```bash
php artisan make:migration create_addon_categories_table
php artisan make:migration create_addons_table
```

### First Model:
```bash
php artisan make:model Modules/Addons/Models/AddonCategory -m
php artisan make:model Modules/Addons/Models/Addon -m
```

---

## 📝 Notes

- Follow **Tunerstop pattern** for consistency
- Use **Filament v3 patterns** (Schema, not Form)
- Implement **soft deletes** on addons
- Add **stock management** from day 1
- Keep **snapshot service simple** - just freeze prices
- **Test with real data** - import sample addons CSV

---

**Ready to proceed with AddOns Module?**

Let me know when you want to start, and we'll create the migrations first! 🚀
