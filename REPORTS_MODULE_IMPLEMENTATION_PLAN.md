# Reports Module Implementation Plan

## 📊 Executive Summary

This document outlines the comprehensive plan for implementing a **Reports Module** in the TunerStop CRM system. The reports are categorized into 6 main sections with multiple sub-reports, featuring time-based data aggregation, filtering, sorting, and export capabilities.

---

## 🔗 Key System Context

### Data Sync Architecture
| Source | Data Synced | Direction |
|--------|-------------|-----------|
| **TunerStop (tunerstop.com)** | Products, Variants, Addons, Orders | → CRM |
| **TunerStop Wholesale** | Orders, Customers (dealers) | → CRM |
| **CRM** | Customers created as retail from synced orders | Internal |

### Existing Infrastructure
- ✅ `inventory_logs` table **EXISTS** - tracks all inventory movements
- ✅ `orders` unified table with `external_source` (retail/wholesale channel)
- ✅ Product/Variant snapshots in `order_items`
- ✅ Expense tracking fields on orders
- ⚠️ Website analytics data needs external API integration

### Performance Requirements
- **Page Load**: Under 5 seconds
- **Strategy**: Lazy loading, batch processing, query optimization, caching

---

## 🔍 Data Verification Findings (December 2024)

> **Deep analysis conducted on both TunerStop (source) and reporting-crm (destination) codebases**

### 1. Inventory Logs Population

| Action | Creates InventoryLog? | Service/Location |
|--------|----------------------|------------------|
| Order synced from TunerStop | ❌ NO | `OrderSyncService` - creates order only |
| Manual inventory allocation | ✅ YES | `OrderFulfillmentService::allocateInventory()` - action: `'sale'` |
| Cancellation/release | ✅ YES | `OrderFulfillmentService::releaseInventory()` - action: `'return'` |
| Manual adjustment | ✅ YES | action: `'adjustment'` |
| Consignment return | ✅ YES | action: `'consignment_return'` |
| Transfer between warehouses | ✅ YES | action: `'transfer_in'`, `'transfer_out'` |

**⚠️ Impact on Reports:** Inventory reports will work for manually fulfilled orders. For synced TunerStop orders, inventory logs won't exist unless explicitly fulfilled in CRM.

### 2. Snapshot Data (Brand/Model Names)

| Source | brand_name | model_name | finish_name |
|--------|------------|------------|-------------|
| TunerStop `OrderSyncService.buildOrderItems()` | ✅ YES | ✅ YES | ✅ YES |
| CRM `ProductSnapshotService.createSnapshot()` | ✅ YES | ✅ YES | ✅ YES |
| CRM `VariantSnapshotService.createSnapshot()` | ✅ YES | ✅ YES | ✅ YES |
| CRM `order_items` denormalized columns | ✅ YES | ✅ YES | ❌ NO |

**✅ Impact on Reports:** Brand & Model reports will work perfectly. Data stored in both:
- `order_items.brand_name` / `order_items.model_name` (denormalized columns)
- `order_items.product_snapshot->brand_name` (JSON field for historical accuracy)

### 3. Expense/Profit Fields

| Field | Auto-Populated? | Source |
|-------|-----------------|--------|
| `cost_of_goods` | ❌ Manual entry | User enters via Invoice edit form |
| `shipping_cost` | ❌ Manual entry | User enters via Invoice edit form |
| `duty_amount` | ❌ Manual entry | User enters via Invoice edit form |
| `delivery_fee` | ❌ Manual entry | User enters via Invoice edit form |
| `installation_cost` | ❌ Manual entry | User enters via Invoice edit form |
| `bank_fee` | ❌ Manual entry | User enters via Invoice edit form |
| `credit_card_fee` | ❌ Manual entry | User enters via Invoice edit form |
| `gross_profit` | ✅ Auto-calculated | `total - total_expenses` |
| `profit_margin` | ✅ Auto-calculated | `(gross_profit / total) * 100` |

**Key Finding:** Product cost IS synced from TunerStop:
- `variant_snapshot->cost` contains product cost at time of order
- Can be used to auto-calculate `cost_of_goods` in future enhancement

**⚠️ Impact on Reports:** Profit reports will only work for invoices where `expenses_recorded_at IS NOT NULL`.

### 4. Data Availability Summary

| Data Point | Available for Reports? | Notes |
|------------|----------------------|-------|
| Sales totals | ✅ YES | `orders.total`, `orders.sub_total` |
| Brand breakdown | ✅ YES | `order_items.brand_name` |
| Model breakdown | ✅ YES | `order_items.model_name` |
| Size breakdown | ✅ YES | `variant_snapshot->size` or `variant_snapshot->diameter` |
| Vehicle data | ✅ YES | `orders.vehicle_year/make/model/sub_model` |
| Product cost | ✅ YES | `variant_snapshot->cost` (synced from TunerStop) |
| Gross profit | ⚠️ PARTIAL | Only where `expenses_recorded_at IS NOT NULL` |
| Inventory movements | ⚠️ PARTIAL | Only for fulfilled orders, not synced orders |
| Daily snapshots | ✅ YES | `daily_snapshots` table exists |
| Customer data | ✅ YES | Full customer records with dealer pricing |
| Channel source | ✅ YES | `orders.external_source` (retail/wholesale) |

### 5. Recommended Enhancements (Future Phases)

1. **Auto-calculate `cost_of_goods`**: Sum `variant_snapshot->cost * quantity` during order sync
2. **Create inventory logs on sync**: Generate logs when orders sync from TunerStop
3. **Fallback profit calculation**: Use snapshot cost when manual expenses not recorded
4. **Website analytics API**: Integrate with TunerStop analytics for vehicle/size searches

---

## 🗂️ Report Categories Overview

| Category | Reports | Priority |
|----------|---------|----------|
| **Sales Reports** | Sales by Brand, Model, Size, Vehicle, Dealer, SKU, Channel | HIGH |
| **Profit Reports** | Profit by Order, Brand, Model, Size, Vehicle, Dealer, SKU, Channel | HIGH |
| **Inventory Reports** | Inventory Movement, Inventory by Month (Brand/Model views) | HIGH |
| **Dealer Reports** | Sales by Brand/Model, Vehicle Searches | MEDIUM |
| **Website Reports** | Vehicle Searches, Size Searches, Product Views, Abandoned Carts | MEDIUM |
| **Team Reports** | Orders by User (comparison + detailed breakdown) | MEDIUM |

---

## 📐 Report Format Specifications

### Common Features (All Reports)

| Feature | Specification |
|---------|---------------|
| **Date Range Selector** | Financial Year dropdown + Custom date picker (Jan-Apr 2025 format) |
| **Default Sorting** | Alphabetical A-Z |
| **Export Options** | CSV, PDF (some reports CSV only) |
| **Filtering** | Retail/Wholesale toggle, Dealer filter, User filter |

### Monthly Grid Format (Sales/Profit/Inventory Reports)

```
┌─────────────┬──────────────┬──────────────┬──────────────┬──────────────┬──────────────┐
│   Entity    │   Jan 2025   │   Feb 2025   │   Mar 2025   │   Apr 2025   │    TOTAL     │
│             │  Qty │ Value │  Qty │ Value │  Qty │ Value │  Qty │ Value │  Qty │ Value │
├─────────────┼──────┼───────┼──────┼───────┼──────┼───────┼──────┼───────┼──────┼───────┤
│ Entity 1    │  12  │12,000 │   8  │ 8,000 │  22  │22,000 │  18  │18,000 │  60  │60,000 │
│ Entity 2    │   4  │ 4,000 │   0  │     0 │   8  │ 6,300 │   0  │     0 │  12  │10,300 │
├─────────────┼──────┼───────┼──────┼───────┼──────┼───────┼──────┼───────┼──────┼───────┤
│ TOTAL       │  16  │16,000 │   8  │ 8,000 │  30  │28,300 │  18  │18,000 │  72  │70,300 │
└─────────────┴──────┴───────┴──────┴───────┴──────┴───────┴──────┴───────┴──────┴───────┘
```

---

## 📋 Detailed Report Specifications

---

### 1️⃣ SALES REPORTS

#### 1.1 Sales by Brand
- **Data Source**: `orders` (invoices) → `order_items` → `product_snapshot` → brand
- **Columns**: Brand | Monthly (Qty, Value) | Total (Qty, Value)
- **Filters**: Channel (retail/wholesale), Dealer, User
- **Sort Options**: A-Z, Qty (high-low), Value (high-low)
- **Export**: CSV, PDF

#### 1.2 Sales by Model
- **Data Source**: `orders` → `order_items` → `product_snapshot` → model
- **Columns**: Model | Monthly (Qty, Value) | Total (Qty, Value)
- **Filters**: Channel, Dealer, User
- **Sort Options**: A-Z, Qty (high-low), Value (high-low)
- **Export**: CSV, PDF

#### 1.3 Sales by Size
- **Data Source**: `orders` → `order_items` → `variant_snapshot` → rim_diameter/size
- **Columns**: Size | Monthly (Qty, Value) | Total (Qty, Value)
- **Filters**: Channel, Dealer, User
- **Export**: CSV, PDF

#### 1.4 Sales by Vehicle
- **Data Source**: `orders.vehicle_year`, `vehicle_make`, `vehicle_model`, `vehicle_sub_model`
- **Columns**: Vehicle (Make Model Year) | Monthly (Qty, Value) | Total (Qty, Value)
- **Filters**: Channel, Dealer, User
- **Export**: CSV, PDF

#### 1.5 Sales by Dealer
- **Data Source**: `orders` → `customers` WHERE `customer_type = 'dealer'`
- **Columns**: Dealer Name | Monthly (Qty, Value) | Total (Qty, Value)
- **Filters**: None (already dealer-specific)
- **Export**: CSV, PDF

#### 1.6 Sales by SKU
- **Data Source**: `orders` → `order_items.sku`
- **Columns**: SKU | Monthly (Qty, Value) | Total (Qty, Value)
- **Filters**: Channel, Dealer, User
- **Export**: CSV, PDF

#### 1.7 Sales by Channel
- **Data Source**: `orders.external_source` (retail/wholesale)
- **Columns**: Channel | Monthly (Qty, Value) | Total (Qty, Value)
- **Export**: CSV, PDF

---

### 2️⃣ PROFIT REPORTS

> **⚠️ DATA DEPENDENCY:** Profit reports require manual expense entry via Invoice edit form.
> Only invoices where `expenses_recorded_at IS NOT NULL` will have accurate profit data.
> 
> **Future Enhancement:** Auto-calculate `cost_of_goods` from `SUM(variant_snapshot->cost * quantity)`

#### 2.1 Profit by Order
- **Data Source**: `orders` with expense fields
- **Columns**: Invoice # | Description | Value | Profit
- **Calculation**: `Profit = total - cost_of_goods - total_expenses`
- **Default Timeframe**: Previous month
- **Filters**: Channel (retail/wholesale)
- **Export**: CSV

#### 2.2 Profit by Brand
- **Data Source**: `orders` → aggregate profit by brand
- **Columns**: Brand | Monthly Profit | Total Profit
- **Note**: Only shows profit value (no qty)
- **Filters**: Channel, Dealer
- **Export**: CSV

#### 2.3 Profit by Model
- **Similar to Profit by Brand, grouped by model**

#### 2.4 Profit by Size
- **Similar structure, grouped by wheel size**

#### 2.5 Profit by Vehicle
- **Similar structure, grouped by vehicle**

#### 2.6 Profit by Dealer
- **Similar structure, grouped by dealer customer**

#### 2.7 Profit by SKU
- **Similar structure, grouped by SKU**

#### 2.8 Profit by Channel
- **Similar structure, grouped by channel (retail/wholesale)**

---

### 3️⃣ INVENTORY REPORTS

> **⚠️ DATA DEPENDENCY:** Inventory logs are only created when orders are **manually fulfilled** in CRM.
> Orders synced from TunerStop do NOT auto-create inventory logs.
> 
> **Future Enhancement:** Generate inventory logs during order sync from TunerStop.

#### 3.1 Inventory Movement (Activity Log)
- **Data Source**: `inventory_logs` table
- **Columns**: Date/Time | Item (Name + SKU) | Activity | Notes
- **Activity Types**:
  - `Sold` - (Xpcs Order #XXXX - WH-X #XXXX)
  - `Returned` - (Xpcs - WH-X #XXXX)
  - `Adjustment` - (Xpcs Removed/Added - WH-X #XXXX)
  - `New Inventory Added` - (Xpcs WH-X #XXXX)
  - `Transferred` - (From WH-X to WH-Y)
  - `Warranty Claim Replaced`
  - `Consignment Delivered`
- **Filters**: Action type, Timeframe (day/week/month), SKU
- **Search**: By SKU or item name
- **Export**: CSV

#### 3.2 Inventory by Month (SKU View)
- **Data Source**: `inventory_logs` aggregated monthly
- **Columns**: SKU | Monthly (Added, Sold) | Total (Added, Sold)
- **Interactive Feature**: Clickable "Sold" qty opens modal showing:
  - Invoice #
  - Customer Name
  - Qty Sold
  - Date Sold
- **Filters**: Channel, Dealer, User
- **Export**: CSV

#### 3.3 Inventory by Month (Brand View)
- **Same as SKU view but grouped by brand**

#### 3.4 Inventory by Month (Model View)
- **Same as SKU view but grouped by model**

---

### 4️⃣ DEALER REPORTS

#### 4.1 Dealer Sales by Brand
- **Data Source**: `orders` WHERE customer is dealer
- **Columns**: Company dropdown | Brand | Monthly (Qty, Value) | Total (Qty, Value)
- **Special Feature**: Toggle between dealers via company dropdown
- **Export**: CSV

#### 4.2 Dealer Sales by Model
- **Same structure as brand, grouped by model**

#### 4.3 Dealer Vehicle Searches
- **Data Source**: External data from tunerstopwholesale.com + invoices
- **Columns**: Vehicle | Searches | Orders
- **Filters**: Dealer, Month, Year
- **Export**: CSV, PDF

---

### 5️⃣ WEBSITE REPORTS (Retail)

#### 5.1 Vehicle Searches
- **Data Source**: External data from tunerstop.com + invoices
- **Columns**: Vehicle | Searches | Orders
- **Note**: Similar to dealer vehicle searches but retail only
- **Filters**: Month, Year
- **Export**: CSV, PDF

#### 5.2 Size Searches
- **Data Source**: External tracking data
- **Columns**: Size | Searches | Orders
- **Export**: CSV, PDF

#### 5.3 Product Views
- **Data Source**: External tracking data
- **Columns**: Product | Views | Orders
- **Export**: CSV, PDF

#### 5.4 Abandoned Carts
- **Data Source**: External e-commerce tracking (WooCommerce/Shopify style)
- **Columns**: Customer | Cart Contents | Cart Value | Last Activity | Status
- **Export**: CSV

---

### 6️⃣ TEAM REPORTS

#### 6.1 Orders by User
**Two-Part Report:**

**Part 1: User Comparison Table**
- **Columns**: User (clickable) | Monthly (Qty, Value) | Total (Qty, Value)
- **Data Source**: `orders.representative_id` or `created_by`

**Part 2: User Detail Table (shown when user clicked)**
- **Columns**: Month dropdown | Invoice # | Description | Value | Profit
- **Toggle**: Click underlined username to switch between users
- **Filters**: Channel (retail/wholesale)
- **Export**: CSV, PDF

---

## 🗄️ Database Requirements

### ✅ Existing Tables (Ready to Use)

#### `inventory_logs` - ALREADY EXISTS ✅
```sql
-- Current structure supports all report needs:
- warehouse_id, product_id, product_variant_id, add_on_id
- action: 'adjustment', 'transfer_in', 'transfer_out', 'sale', 'return', 'import', 'consignment_return'
- quantity_before, quantity_after, quantity_change
- reference_type, reference_id (links to orders, consignments, etc.)
- notes, user_id, created_at
- Proper indexes on action, created_at, reference
```

#### `orders` table - ALREADY EXISTS ✅
- ✅ `external_source` (retail/wholesale channel) - from TunerStop sync
- ✅ `document_type` (quote/invoice/order)
- ✅ `vehicle_year`, `vehicle_make`, `vehicle_model`, `vehicle_sub_model`
- ✅ `representative_id` (for team reports)
- ✅ `cost_of_goods`, `gross_profit`, `profit_margin` (for profit reports)
- ✅ `customer_id` → customers with `customer_type` (retail/dealer)

#### `order_items` table - ALREADY EXISTS ✅
- ✅ `product_snapshot` JSON → contains `brand_name`, `model_name`
- ✅ `variant_snapshot` JSON → contains `size`, `rim_diameter`, `sku`, `cost`
- ✅ `addon_snapshot` JSON → contains addon details

### 🆕 New Table Required

#### `website_analytics` (For Website/Dealer Reports)
```sql
CREATE TABLE website_analytics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    source VARCHAR(50) NOT NULL,      -- 'tunerstop', 'tunerstopwholesale'
    event_type VARCHAR(50) NOT NULL,  -- 'vehicle_search', 'size_search', 'product_view', 'cart_abandon'
    
    -- Searchable dimensions
    vehicle_make VARCHAR(100) NULL,
    vehicle_model VARCHAR(100) NULL,
    vehicle_year VARCHAR(10) NULL,
    wheel_size VARCHAR(20) NULL,
    product_id BIGINT NULL,
    
    -- Customer tracking
    customer_id BIGINT NULL,
    dealer_id BIGINT NULL,
    session_id VARCHAR(100) NULL,
    
    -- Cart data (for abandoned carts)
    cart_data JSON NULL,
    cart_value DECIMAL(10,2) NULL,
    
    event_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_source_type (source, event_type),
    INDEX idx_event_date (event_date),
    INDEX idx_vehicle (vehicle_make, vehicle_model),
    INDEX idx_dealer (dealer_id)
);
```
**Note**: This table will be populated via API webhook from TunerStop websites.

---

## ⚡ Performance Strategy

### Target: < 5 Second Page Load

#### 1. Lazy Loading with Livewire
```php
// Load page structure immediately, fetch data asynchronously
class SalesByBrand extends Page
{
    public bool $isLoading = true;
    public array $reportData = [];
    
    public function mount(): void
    {
        // Page renders immediately with loading state
        $this->isLoading = true;
    }
    
    // Called after page renders via wire:init
    public function loadReport(): void
    {
        $this->reportData = $this->reportService->getSalesByBrand($this->filters);
        $this->isLoading = false;
    }
}
```

#### 2. Chunked/Batch Processing for Large Datasets
```php
// Process in batches to avoid memory issues
public function generateReport(): Collection
{
    return Order::query()
        ->where('document_type', 'invoice')
        ->whereBetween('created_at', $this->dateRange)
        ->with(['items' => fn($q) => $q->select('id', 'order_id', 'product_snapshot', 'quantity', 'line_total')])
        ->lazy(1000)  // Process 1000 at a time
        ->groupBy(fn($order) => $this->extractBrand($order))
        ->map(fn($group) => $this->aggregateGroup($group));
}
```

#### 3. Query Optimization - Use Raw SQL for Aggregations
```php
// Use raw queries for aggregation (faster than Eloquent)
public function getSalesByBrandOptimized(array $filters): array
{
    return DB::select("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(oi.product_snapshot, '$.brand_name')) as brand,
            DATE_FORMAT(o.created_at, '%Y-%m') as month,
            SUM(oi.quantity) as qty,
            SUM(oi.line_total) as value
        FROM orders o
        INNER JOIN order_items oi ON o.id = oi.order_id
        WHERE o.document_type = 'invoice'
          AND o.deleted_at IS NULL
          AND o.created_at BETWEEN ? AND ?
          AND (? IS NULL OR o.external_source = ?)
        GROUP BY brand, month
        ORDER BY brand ASC
    ", [$startDate, $endDate, $channel, $channel]);
}
```

#### 4. Strategic Caching (30 min TTL)
```php
// Cache expensive aggregations (invalidate on new invoices)
public function getSalesByBrand(array $filters): array
{
    $cacheKey = "report:sales_brand:" . md5(json_encode($filters));
    
    return Cache::tags(['reports', 'sales'])
        ->remember($cacheKey, now()->addMinutes(30), function () use ($filters) {
            return $this->generateSalesByBrand($filters);
        });
}

// Clear cache when new invoice created (in OrderObserver)
public function created(Order $order): void
{
    if ($order->document_type === DocumentType::INVOICE) {
        Cache::tags(['reports', 'sales'])->flush();
    }
}
```

#### 5. Database Indexes (Verify/Add)
```sql
-- Ensure these indexes exist for fast report queries
CREATE INDEX idx_orders_report ON orders(document_type, created_at, external_source);
CREATE INDEX idx_orders_customer ON orders(customer_id, document_type);
CREATE INDEX idx_order_items_aggregation ON order_items(order_id);
CREATE INDEX idx_inventory_logs_report ON inventory_logs(action, created_at);
```

#### 6. Pagination for Detail Views
```php
// For reports with many rows (e.g., Profit by Order, Inventory Movement)
public function getTableQuery(): Builder
{
    return Order::query()
        ->where('document_type', 'invoice')
        ->whereBetween('created_at', $this->dateRange)
        ->select(['id', 'order_number', 'total', 'gross_profit'])
        ->orderBy('created_at', 'desc');
}
// Filament handles pagination automatically (10/25/50 per page)
```

#### 7. Frontend Loading States (Skeleton UI)
```blade
{{-- Show skeleton loader while data fetches --}}
<div wire:init="loadReport">
    @if($isLoading)
        <x-reports.skeleton-table :columns="12" :rows="10" />
    @else
        <x-reports.monthly-grid-table :data="$reportData" />
    @endif
</div>
```

#### 8. Selective Column Loading
```php
// Only select columns needed for report
Order::query()
    ->select(['id', 'created_at', 'total', 'gross_profit', 'customer_id', 'external_source'])
    ->with(['items:id,order_id,product_snapshot,quantity,line_total'])
    ->get();
```

---

## 🏗️ Technical Architecture

### Filament Pages Structure

```
app/Filament/Pages/Reports/
├── ReportsIndex.php                    # Main reports dashboard/navigation
│
├── Sales/
│   ├── SalesByBrand.php
│   ├── SalesByModel.php
│   ├── SalesBySize.php
│   ├── SalesByVehicle.php
│   ├── SalesByDealer.php
│   ├── SalesBySku.php
│   └── SalesByChannel.php
│
├── Profit/
│   ├── ProfitByOrder.php
│   ├── ProfitByBrand.php
│   ├── ProfitByModel.php
│   ├── ProfitBySize.php
│   ├── ProfitByVehicle.php
│   ├── ProfitByDealer.php
│   ├── ProfitBySku.php
│   └── ProfitByChannel.php
│
├── Inventory/
│   ├── InventoryMovement.php
│   ├── InventoryByMonth.php            # With SKU/Brand/Model tabs
│   └── InventoryByBrand.php
│
├── Dealer/
│   ├── DealerSalesByBrand.php
│   ├── DealerSalesByModel.php
│   └── DealerVehicleSearches.php
│
├── Website/
│   ├── VehicleSearches.php
│   ├── SizeSearches.php
│   ├── ProductViews.php
│   └── AbandonedCarts.php
│
└── Team/
    └── OrdersByUser.php
```

### Service Classes

```
app/Services/Reports/
├── ReportService.php                   # Base service with common methods
├── SalesReportService.php              # Sales aggregation logic
├── ProfitReportService.php             # Profit calculation logic
├── InventoryReportService.php          # Inventory movement logic
├── DealerReportService.php             # Dealer-specific reports
├── WebsiteReportService.php            # Website analytics
└── TeamReportService.php               # User performance reports
```

### Export Classes

```
app/Exports/Reports/
├── BaseReportExport.php                # Common export functionality
├── SalesReportExport.php
├── ProfitReportExport.php
├── InventoryReportExport.php
└── ...
```

### Traits for Common Functionality

```php
// app/Filament/Pages/Reports/Traits/HasDateRangeFilter.php
trait HasDateRangeFilter
{
    public ?string $financialYear = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    
    public function getDateRange(): array { ... }
    public function getMonthsInRange(): array { ... }
}

// app/Filament/Pages/Reports/Traits/HasChannelFilter.php
trait HasChannelFilter
{
    public ?string $channel = null; // 'retail', 'wholesale', null (all)
    
    public function filterByChannel($query) { ... }
}

// app/Filament/Pages/Reports/Traits/HasExportCapability.php
trait HasExportCapability
{
    public function exportToCsv() { ... }
    public function exportToPdf() { ... }
}
```

---

## 📊 UI Components

### 1. Date Range Selector Component
```php
// Livewire component for financial year + custom date range
<x-reports.date-range-selector 
    :financialYear="$financialYear"
    :startDate="$startDate"
    :endDate="$endDate"
/>
```

### 2. Monthly Grid Table Component
```php
// Reusable table component for monthly data
<x-reports.monthly-grid-table
    :data="$reportData"
    :months="$months"
    :columns="['qty', 'value']"
    :showTotals="true"
/>
```

### 3. Filter Bar Component
```php
<x-reports.filter-bar>
    <x-reports.filter-channel />
    <x-reports.filter-dealer />
    <x-reports.filter-user />
    <x-reports.sort-dropdown :options="$sortOptions" />
    <x-reports.export-button :formats="['csv', 'pdf']" />
</x-reports.filter-bar>
```

### 4. Clickable Cell Modal (for Inventory Sold details)
```php
// Alpine.js modal showing sale details when clicking "Sold" qty
<x-reports.sale-details-modal
    :sku="$sku"
    :month="$month"
/>
```

---

## 🔄 Data Flow

### Sales Report Data Flow
```
Request (filters, date range)
    ↓
ReportController / Livewire Component
    ↓
SalesReportService::getSalesByBrand($filters)
    ↓
Query Builder:
    SELECT 
        JSON_EXTRACT(oi.product_snapshot, '$.brand_name') as brand,
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        SUM(oi.quantity) as qty,
        SUM(oi.line_total) as value
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    WHERE o.document_type = 'invoice'
      AND o.created_at BETWEEN ? AND ?
      AND (? IS NULL OR o.external_source = ?)
    GROUP BY brand, month
    ORDER BY brand ASC
    ↓
Transform to Monthly Grid Format
    ↓
Return to View / Export
```

### Profit Calculation Logic
```php
// For each order:
$revenue = $order->total;
$cost = $order->cost_of_goods;
$expenses = $order->shipping_cost 
          + $order->duty_amount 
          + $order->delivery_fee 
          + $order->installation_cost 
          + $order->bank_fee 
          + $order->credit_card_fee;

$profit = $revenue - $cost - $expenses;
$margin = ($profit / $revenue) * 100;
```

---

## 📅 Implementation Phases

### Phase 1: Foundation (Week 1-2)
- [ ] Create base report page structure
- [ ] Implement `HasDateRangeFilter` trait
- [ ] Implement `HasChannelFilter` trait  
- [ ] Implement `HasExportCapability` trait
- [ ] Create `ReportService` base class
- [ ] Build reusable Blade/Livewire components
- [ ] Create navigation menu structure

### Phase 2: Sales Reports (Week 2-3)
- [ ] Sales by Brand (template for others)
- [ ] Sales by Model
- [ ] Sales by Size
- [ ] Sales by Vehicle
- [ ] Sales by Dealer
- [ ] Sales by SKU
- [ ] Sales by Channel
- [ ] CSV/PDF exports for all

### Phase 3: Profit Reports (Week 3-4)
- [ ] Profit by Order (verify expense fields populated)
- [ ] Profit by Brand
- [ ] Profit by Model
- [ ] Profit by Size
- [ ] Profit by Vehicle
- [ ] Profit by Dealer
- [ ] Profit by SKU
- [ ] Profit by Channel

### Phase 4: Inventory Reports (Week 4-5)
- [ ] Enhance `inventory_logs` table migration
- [ ] Implement inventory logging in observers
- [ ] Inventory Movement report
- [ ] Inventory by Month (SKU)
- [ ] Inventory by Month (Brand)
- [ ] Inventory by Month (Model)
- [ ] Clickable modal for sale details

### Phase 5: Dealer & Team Reports (Week 5-6)
- [ ] Dealer Sales by Brand
- [ ] Dealer Sales by Model
- [ ] Dealer Vehicle Searches (if external data available)
- [ ] Orders by User (comparison table)
- [ ] Orders by User (detail breakdown)

### Phase 6: Website Reports (Week 6-7)
- [ ] Create `website_analytics` table
- [ ] Build API endpoint for external data ingestion
- [ ] Vehicle Searches report
- [ ] Size Searches report
- [ ] Product Views report
- [ ] Abandoned Carts report

### Phase 7: Polish & Testing (Week 7-8)
- [ ] Performance optimization (caching, query tuning)
- [ ] UI/UX refinements
- [ ] Export testing (large datasets)
- [ ] User acceptance testing
- [ ] Documentation

---

## 🧪 Testing Requirements

### Unit Tests
```php
// tests/Unit/Services/SalesReportServiceTest.php
- test_sales_by_brand_returns_correct_aggregation()
- test_date_range_filtering_works()
- test_channel_filter_excludes_other_channels()
- test_empty_months_show_zero_values()
```

### Feature Tests
```php
// tests/Feature/Reports/SalesByBrandTest.php
- test_sales_by_brand_page_loads()
- test_sales_by_brand_filters_by_date()
- test_sales_by_brand_exports_csv()
- test_sales_by_brand_exports_pdf()
```

---

## ⚠️ Dependencies & Blockers

### Current Data Sync Architecture
```
TunerStop (Main E-commerce) ──────► CRM System
    │
    ├── Products Sync ──────────► products, product_variants
    ├── Addons Sync ────────────► addon_categories, addons
    ├── Orders Sync ────────────► orders, order_items
    └── Customers Auto-Created ─► customers (as 'retail' type)

TunerStop Wholesale ──────────────► CRM System
    │
    ├── Orders Sync ────────────► orders, order_items
    └── Customers ──────────────► customers (as 'dealer' type)
```

### ✅ Ready to Use
| Component | Status | Notes |
|-----------|--------|-------|
| `inventory_logs` table | ✅ EXISTS | Full structure with action types, references |
| `orders` with channels | ✅ EXISTS | `external_source` identifies retail/wholesale |
| Product/Variant snapshots | ✅ EXISTS | JSON snapshots in `order_items` |
| Expense tracking fields | ✅ EXISTS | `cost_of_goods`, `gross_profit`, etc. |
| Customer types | ✅ EXISTS | `customer_type` = retail/dealer |

### 🔄 Verified Data (December 2024)
| Component | Status | Notes |
|-----------|--------|-------|
| Inventory logging | ⚠️ PARTIAL | Created on fulfillment, NOT on order sync |
| Product snapshots | ✅ YES | `brand_name`, `model_name` ARE populated |
| Variant snapshots | ✅ YES | `cost` field IS synced from TunerStop |
| Expense fields | ⚠️ MANUAL | Requires user entry via Invoice form |

### 🆕 To Be Implemented
| Component | Priority | Notes |
|-----------|----------|-------|
| `website_analytics` table | MEDIUM | For vehicle/size searches, abandoned carts |
| Analytics API webhook | MEDIUM | Receive data from TunerStop websites |
| Auto-calculate cost_of_goods | LOW | Sum variant costs during sync |
| Inventory logs on order sync | LOW | Generate logs when orders sync |

### External Dependencies
1. **Website Analytics Data** - Need API integration with tunerstop.com / tunerstopwholesale.com
2. **Abandoned Cart Data** - Need webhook from e-commerce platform (WooCommerce/Shopify style)

---

## 📝 Notes & Decisions

1. **Unified Orders Table**: Leverage existing unified `orders` table with `document_type` discriminator
2. **JSONB Snapshots**: Use `product_snapshot` and `variant_snapshot` for historical accuracy
3. **Financial Year**: Default to current financial year, allow custom date ranges
4. **Channel Definition**: `external_source` field determines retail vs wholesale
5. **Profit Calculation**: Use stored expense fields, not calculated on-the-fly
6. **Export Library**: Use Laravel Excel (maatwebsite/excel) for CSV/XLSX exports
7. **PDF Library**: Use DomPDF or Snappy for PDF exports
8. **Data Source**: Products/Addons/Orders synced FROM TunerStop, customers auto-created
9. **Performance**: Target < 5 seconds using lazy load, caching, optimized queries

---

## ✅ Acceptance Criteria

Each report must:
1. ✅ Display data in correct monthly grid format
2. ✅ Support date range filtering (financial year + custom)
3. ✅ Support sorting (A-Z default, Qty/Value high-low)
4. ✅ Support applicable filters (channel, dealer, user)
5. ✅ Export to CSV (all reports)
6. ✅ Export to PDF (where specified)
7. ✅ Show totals row at bottom
8. ✅ Handle empty data gracefully (show zeros, not blank)
9. ✅ **Load within 5 seconds** for typical data volumes
10. ✅ Be responsive on tablet/desktop screens
11. ✅ Show loading skeleton/spinner during data fetch

---

## 🚀 Ready to Start

**Recommended Starting Point**: Begin with **Sales by Brand** report as the template, as it demonstrates all common features and can be copied for other sales/profit reports.

### Pre-Implementation Checklist
- [x] `inventory_logs` table exists ✅
- [x] `orders` table has `external_source` for channel filtering ✅
- [x] `order_items` has product/variant snapshots ✅
- [x] Product snapshots contain `brand_name`, `model_name` ✅
- [x] Variant snapshots contain `cost` field ✅
- [ ] Expense fields manually entered on invoices (user workflow)
- [ ] Decide on website analytics integration approach

### Questions Resolved
| Question | Answer |
|----------|--------|
| Does inventory_logs exist? | ✅ YES - full structure with action types |
| Acceptable page load time? | < 5 seconds |
| Data sync direction? | TunerStop → CRM (products, orders, addons) |
| Customer creation? | Auto-created as retail during order sync |
| Do snapshots have brand/model? | ✅ YES - verified in both services |
| Is product cost synced? | ✅ YES - `variant_snapshot->cost` from TunerStop |
| Are inventory logs auto-created? | ⚠️ Only on fulfillment, not on sync |
| Are expenses auto-populated? | ❌ NO - manual entry required |

### Remaining Questions
1. Should reports be role-restricted?
2. When will website analytics API be available?
3. Do we want to auto-calculate `cost_of_goods` from variant costs?

