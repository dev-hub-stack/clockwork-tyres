# Reporting Module — Detailed Implementation Plan

## Table of Contents
1. [Architecture Overview](#1-architecture-overview)
2. [Data Source Analysis](#2-data-source-analysis)
3. [Report Categories & Specifications](#3-report-categories--specifications)
4. [Shared Infrastructure (Phase 0)](#4-shared-infrastructure-phase-0)
5. [Sales Reports (Phase 1)](#5-sales-reports-phase-1)
6. [Profit Reports (Phase 2)](#6-profit-reports-phase-2)
7. [Inventory Reports (Phase 3)](#7-inventory-reports-phase-3)
8. [Dealer Reports (Phase 4)](#8-dealer-reports-phase-4)
9. [Team Reports (Phase 5)](#9-team-reports-phase-5)
10. [Dashboard Summary Cards (Phase 6)](#10-dashboard-summary-cards-phase-6)
11. [File Structure](#11-file-structure)
12. [Database Considerations](#12-database-considerations)
13. [Implementation Order & Dependencies](#13-implementation-order--dependencies)

---

## Implementation Progress Snapshot

- Completed: Phase 0 Foundation, Phase 1 Sales Reports, Phase 2 Profit Reports, Phase 3 Inventory Reports, Phase 4 Dealer Reports, Phase 5 Team Reports, Phase 6 Reports Dashboard
- Completed cross-cutting support: CSV/PDF export tooling, granular report permissions, legacy analytics access hardening, shared reporting UI polish, reusable product-noise reporting command, and focused report regression coverage
- Remaining implementation: deferred website tracking reports and dealer vehicle searches
- Latest completed slice: reporting hardening, cleanup reporting automation, and broader regression coverage

---

## 1. Architecture Overview

### Tech Stack (Already in Place)
| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 + PHP 8.2 |
| Admin UI | Filament 4.0 |
| PDF Export | barryvdh/laravel-dompdf |
| CSV/Excel Export | maatwebsite/excel 3.1 |
| Database | MySQL |
| Frontend Interactivity | Alpine.js + Blade |

### Design Pattern: Filament Custom Pages + Service Layer

All reports follow a **single consistent pattern**:

```
┌─────────────────────────────────────────────────────────┐
│  Filament Custom Page (Report Page)                     │
│  ├── Date Range Filter (financial year / custom)        │
│  ├── Channel Filter (retail / wholesale / all)          │
│  ├── Dealer Filter (specific dealer dropdown)           │
│  ├── User Filter (salesman)                             │
│  ├── Sort Control (A-Z, Qty high→low, Value high→low)  │
│  └── Export Button (CSV + PDF)                          │
│                                                         │
│  Renders → Blade View with Alpine.js                    │
│  Queries → ReportService (shared SQL builder)           │
│  Exports → Report-specific Exporter classes             │
└─────────────────────────────────────────────────────────┘
```

### Why Filament Custom Pages (Not Resources)
- Reports are **read-only aggregated views**, not CRUD
- Need **monthly column pivoting** (Jan, Feb, Mar...) — not a standard Filament table
- Need custom **sort/filter/export** toolbar
- Need **clickable cells** (e.g., inventory sold qty → popup)
- Matches the existing pattern used by `InventoryGrid` and `ProductsGrid`

---

## 2. Data Source Analysis

### Primary Data Source: `orders` + `order_items` tables

All report data is derived from **invoices** in the CRM (not external orders/quotes):

```sql
-- Base query for ALL sales/profit reports
SELECT ...
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
WHERE o.document_type = 'invoice'
  AND o.deleted_at IS NULL
  AND o.issue_date BETWEEN :start_date AND :end_date
```

### Key Fields for Reporting

| Report Dimension | Source Field | Table |
|-----------------|-------------|-------|
| **Brand** | `oi.brand_name` | order_items (denormalized) |
| **Model** | `oi.model_name` | order_items (denormalized) |
| **Size** | `oi.item_attributes->>'size'` | order_items (JSON) |
| **Vehicle** | `o.vehicle_make`, `o.vehicle_model`, `o.vehicle_sub_model` | orders |
| **Dealer** | `c.business_name` via `o.customer_id` | customers |
| **SKU** | `oi.sku` | order_items |
| **Channel** | `o.external_source` ('retail' / 'wholesale') | orders |
| **Salesman/User** | `o.representative_id` | orders → users |
| **Month** | `o.issue_date` | orders |
| **Qty** | `oi.quantity` | order_items |
| **Value** | `oi.line_total` | order_items |
| **Cost** | `oi.variant_snapshot->>'cost'` | order_items (JSON snapshot) |
| **Profit** | `o.gross_profit` (order-level) | orders |

### Profit Calculation Strategy

Two levels of profit exist:

1. **Order-level profit** (already calculated): `orders.gross_profit`, `orders.profit_margin` — manually recorded per invoice with expense breakdown (`cost_of_goods`, `shipping_cost`, `duty_amount`, etc.)

2. **Line-item estimated profit** (computable): `oi.line_total - (oi.quantity * variant_snapshot->>'cost')` — uses cost snapshot from time of sale

For **Profit by Brand/Model/SKU/Dealer/Channel** reports, we use **order-level `gross_profit`** since that includes all expense deductions. We'll distribute order-level profit proportionally to line items by their `line_total` share when grouping by brand/model.

Profit distribution formula per line item:
```
item_profit = order.gross_profit * (item.line_total / order.sub_total)
```

---

## 3. Report Categories & Specifications

### 3A. Sales Reports (8 reports)
| Report | Group By | Columns per Month | Sort Options |
|--------|----------|-------------------|-------------|
| Sales by Brand | `brand_name` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by Model | `model_name` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by Size | `item_attributes->size` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by Vehicle | `make + model + sub_model` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by Dealer | `customer.business_name` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by SKU | `sku` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by Channel | `external_source` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by Team | `representative.name` | Qty, Value | A-Z, Qty ↓, Value ↓ |
| Sales by Categories | Product type (wheel/addon) | Qty, Value | A-Z, Qty ↓, Value ↓ |

### 3B. Profit Reports (8 reports)
| Report | Group By | Columns per Month | Sort Options |
|--------|----------|-------------------|-------------|
| Profit by Order | Invoice number | Description, Value, Profit | A-Z, Value ↓ |
| Profit by Brand | `brand_name` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by Model | `model_name` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by Size | `item_attributes->size` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by Vehicle | `make + model + sub_model` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by Dealer | `customer.business_name` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by SKU | `sku` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by Month | Month | Total Value, Total Profit | chronological |
| Profit by Salesman | `representative.name` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by Channel | `external_source` | Profit (per month) + TOTAL | A-Z, Value ↓ |
| Profit by Categories | Product type | Profit (per month) + TOTAL | A-Z, Value ↓ |

### 3C. Inventory Reports (4 reports)
| Report | Group By | Columns per Month | Special Features |
|--------|----------|-------------------|-----------------|
| Inventory by SKU | `sku` | Added, Sold + TOTAL | Clickable sold → popup with invoices |
| Inventory by Brand | `brand_name` | Added, Sold + TOTAL | Clickable sold → popup |
| Inventory by Model | `model_name` | Added, Sold + TOTAL | Clickable sold → popup |

### 3D. Dealer Reports (2 reports)
| Report | Group By | Columns per Month | Special Features |
|--------|----------|-------------------|-----------------|
| Dealer Sales by Brand | `brand_name` per dealer | Qty, Value + TOTAL | Dealer dropdown toggle |
| Dealer Sales by Model | `model_name` per dealer | Qty, Value + TOTAL | Dealer dropdown toggle |
| Dealer Vehicle Searches | Vehicle search terms | Count | Dealer dropdown |

### 3E. Team Reports (1 report)
| Report | Special Features |
|--------|-----------------|
| Orders by User | Two-part: (1) comparison table across users, (2) detail table per user with all invoices |

### 3F. Website Reports (4 reports — deferred per meeting)
- Vehicle Searches, Size Searches, Product Views, Abandoned Carts
- *To be revisited later*

---

## 4. Shared Infrastructure (Phase 0)

### 4A. ReportService — `app/Services/ReportService.php`

Central service class that all reports delegate to. Handles query building, date range parsing, and aggregation.

```php
class ReportService
{
    /**
     * Get monthly aggregated sales data grouped by a dimension.
     *
     * @param string $groupByField  - SQL expression for GROUP BY (e.g., 'oi.brand_name')
     * @param string $labelField    - Column alias for the group label
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param array  $filters       - ['channel' => 'retail', 'dealer_id' => 5, 'user_id' => 3]
     * @return Collection           - [{label, months: [{month, qty, value}], total_qty, total_value}]
     */
    public function salesByDimension(string $groupByField, string $labelField, 
                                      Carbon $startDate, Carbon $endDate, 
                                      array $filters = []): Collection;

    /**
     * Get monthly aggregated profit data grouped by a dimension.
     * Uses proportional profit distribution from order gross_profit.
     */
    public function profitByDimension(string $groupByField, string $labelField,
                                       Carbon $startDate, Carbon $endDate,
                                       array $filters = []): Collection;

    /**
     * Get profit by individual order (invoice).
     */
    public function profitByOrder(Carbon $startDate, Carbon $endDate,
                                   array $filters = []): Collection;

    /**
     * Get monthly inventory movement (added/sold) by dimension.
     */
    public function inventoryByDimension(string $groupByField, string $labelField,
                                          Carbon $startDate, Carbon $endDate,
                                          array $filters = []): Collection;

    /**
     * Get sold item details for a specific SKU in a specific month.
     * Returns invoice-level breakdown for clickable popup.
     */
    public function soldItemDetails(string $sku, Carbon $monthStart, Carbon $monthEnd): Collection;

    /**
     * Get team performance data - qty and value per user per month.
     */
    public function teamPerformance(Carbon $startDate, Carbon $endDate,
                                     array $filters = []): Collection;

    /**
     * Get individual user order details for a given month.
     */
    public function userOrderDetails(int $userId, Carbon $monthStart, Carbon $monthEnd,
                                      array $filters = []): Collection;
}
```

**Key implementation details for the service:**

```sql
-- Sales by dimension base query pattern
SELECT 
    {group_expression} AS dimension_label,
    DATE_FORMAT(o.issue_date, '%Y-%m') AS month,
    SUM(oi.quantity) AS qty,
    SUM(oi.line_total) AS value
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
LEFT JOIN customers c ON c.id = o.customer_id
WHERE o.document_type = 'invoice'
  AND o.deleted_at IS NULL
  AND o.issue_date BETWEEN :start AND :end
  -- Dynamic filters:
  AND (:channel IS NULL OR o.external_source = :channel)
  AND (:dealer_id IS NULL OR o.customer_id = :dealer_id)
  AND (:user_id IS NULL OR o.representative_id = :user_id)
GROUP BY dimension_label, month
ORDER BY dimension_label ASC, month ASC
```

```sql
-- Profit by dimension base query pattern (proportional distribution)
SELECT
    {group_expression} AS dimension_label,
    DATE_FORMAT(o.issue_date, '%Y-%m') AS month,
    SUM(
        CASE 
            WHEN o.gross_profit IS NOT NULL AND o.sub_total > 0 
            THEN o.gross_profit * (oi.line_total / o.sub_total)
            ELSE oi.line_total - (oi.quantity * COALESCE(
                CAST(JSON_UNQUOTE(JSON_EXTRACT(oi.variant_snapshot, '$.cost')) AS DECIMAL(10,2)), 0
            ))
        END
    ) AS profit
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
LEFT JOIN customers c ON c.id = o.customer_id
WHERE o.document_type = 'invoice'
  AND o.deleted_at IS NULL
  AND o.issue_date BETWEEN :start AND :end
GROUP BY dimension_label, month
ORDER BY dimension_label ASC, month ASC
```

```sql
-- Inventory movement base query
-- "Added" = inventory_logs with action='adjustment' or 'transfer_in' (positive qty changes)
-- "Sold" = order_items from invoices in that month
SELECT
    {group_expression} AS dimension_label,
    DATE_FORMAT(il.created_at, '%Y-%m') AS month,
    SUM(CASE WHEN il.action IN ('adjustment','transfer_in') AND il.quantity_after > il.quantity_before 
         THEN il.quantity_after - il.quantity_before ELSE 0 END) AS added,
    -- Sold comes from order_items (separate query, joined in PHP)
FROM inventory_logs il
LEFT JOIN product_variants pv ON pv.id = il.product_variant_id
LEFT JOIN products p ON p.id = pv.product_id
LEFT JOIN brands b ON b.id = p.brand_id
LEFT JOIN models m ON m.id = p.model_id
WHERE il.created_at BETWEEN :start AND :end
GROUP BY dimension_label, month
```

### 4B. Date Range Component — Shared Blade Partial

`resources/views/components/report-toolbar.blade.php`

Provides the universal toolbar used by all report pages:
- **Financial year dropdown**: "This Financial Year" (Jan–Dec), custom range
- **Date range picker**: Start month → End month
- **Sort button**: A-Z (default), Qty High→Low, Value High→Low
- **Filter button**: Channel (Retail/Wholesale/All), Dealer dropdown, User dropdown
- **Export button**: CSV, PDF

**State managed via Alpine.js** with URL query parameter sync so bookmarking/sharing works.

```html
<!-- Usage in any report Blade view -->
<x-report-toolbar 
    :start-date="$startDate" 
    :end-date="$endDate"
    :months="$months"
    :sort="$sort"
    :filters="$filters"
    :show-dealer-filter="true"
    :show-user-filter="false"
    :show-channel-filter="true"
    :dealers="$dealers"
    :users="$users"
    export-route="reports.sales-by-brand.export"
/>
```

### 4C. Report Table Component — Shared Blade Partial

`resources/views/components/report-table.blade.php`

Renders the **monthly pivot table** format shown in mockups:

```
┌──────────────┬────────────┬────────────┬────────────┬──────────┐
│  {Label}     │  Jan 2025  │  Feb 2025  │  Mar 2025  │  TOTAL   │
│              │  Qty Value │  Qty Value │  Qty Value │ Qty Value│
├──────────────┼────────────┼────────────┼────────────┼──────────┤
│  Brand A     │  12  12000 │   8  8000  │  22  22000 │ 42  42000│
│  Brand B     │   4   4000 │   0     0  │   8   6300 │ 12  10300│
├──────────────┼────────────┼────────────┼────────────┼──────────┤
│  TOTAL       │  16  16000 │   8  8000  │  30  28300 │ 54  52300│
└──────────────┴────────────┴────────────┴────────────┴──────────┘
```

Supports:
- **Two-column mode** (Qty + Value) for sales reports
- **Single-column mode** (profit value only) for profit reports
- **Added/Sold mode** for inventory reports
- **Horizontal scroll** on many months
- **Clickable cells** (optional, for inventory sold qty popup)
- **TOTAL row** at bottom
- **TOTAL column** at right

### 4D. Export Classes

`app/Exports/ReportExport.php` — Generic report exporter using maatwebsite/excel:

```php
class ReportExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private Collection $data,
        private array $months,
        private string $labelHeader,
        private string $mode = 'sales' // 'sales', 'profit', 'inventory'
    ) {}
}
```

`app/Exports/ReportPdfExport.php` — PDF version using DomPDF:

```php
class ReportPdfExport
{
    public function download(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a3', 'landscape');
        return $pdf->download($filename);
    }
}
```

### 4E. Trait for Report Pages

`app/Filament/Pages/Concerns/HasReportFilters.php`

Shared trait for all report pages providing:
- `$startDate`, `$endDate` properties with defaults (current financial year)
- `$sort` property ('alpha', 'qty_desc', 'value_desc')
- `$channel` filter (null = all, 'retail', 'wholesale')
- `$dealerId` filter
- `$userId` filter
- `getMonthsBetween()` helper → returns month labels array
- `getFiltersArray()` → returns filters for ReportService
- `applySort()` → sorts result collection by current sort mode
- `getDealerOptions()` → wholesale customer dropdown data
- `getUserOptions()` → staff/salesman dropdown data
- Financial year logic (Jan–Dec calendar year)

---

## 5. Sales Reports (Phase 1)

### Common Pattern for All 9 Sales Reports

Each sales report consists of:
1. **Filament Page class** — defines navigation, permissions, Blade view, data loading
2. **Shared Blade view** — all 9 use the same `report-table` component, differentiated by label column header

### 5A. Sales by Brand

**Page:** `app/Filament/Pages/Reports/SalesByBrand.php`

```php
class SalesByBrand extends Page
{
    use HasReportFilters;
    
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationParentItem = 'Sales Reports';
    protected static ?int $navigationSort = 1;
    protected static string $view = 'filament.pages.reports.sales-report';
    
    public function getViewData(): array
    {
        $data = app(ReportService::class)->salesByDimension(
            groupByField: 'oi.brand_name',
            labelField: 'Brand',
            startDate: $this->startDate,
            endDate: $this->endDate,
            filters: $this->getFiltersArray()
        );
        
        return [
            'title' => 'Sales by Brand',
            'breadcrumb' => 'Reports > Sales Reports > Sales by Brand',
            'labelHeader' => 'Brand',
            'data' => $this->applySort($data),
            'months' => $this->getMonthsBetween(),
            'mode' => 'sales', // Qty + Value columns
            ...common filter/sort data...
        ];
    }
}
```

### 5B–5I. Remaining Sales Reports

All follow the identical pattern, only changing:

| Report | `groupByField` | `labelHeader` |
|--------|----------------|--------------|
| Sales by Model | `oi.model_name` | Model |
| Sales by Size | `JSON_UNQUOTE(JSON_EXTRACT(oi.item_attributes, '$.size'))` | Size |
| Sales by Vehicle | `CONCAT(o.vehicle_make, ' ', o.vehicle_model, ' ', COALESCE(o.vehicle_sub_model, ''))` | Vehicle |
| Sales by Dealer | `c.business_name` | Dealer |
| Sales by SKU | `oi.sku` | SKU |
| Sales by Channel | `o.external_source` | Channel |
| Sales by Team | `CONCAT(u.name)` (+ JOIN users u ON u.id = o.representative_id) | User |
| Sales by Categories | `CASE WHEN oi.add_on_id IS NOT NULL THEN 'Addon' ELSE 'Wheel' END` | Category |

**Shared Blade view:** `resources/views/filament/pages/reports/sales-report.blade.php`

This single Blade template renders all sales reports. Variables like `$labelHeader`, `$breadcrumb`, `$title` differentiate the display. The `<x-report-table>` component handles the monthly pivot layout.

### Filter Specifications (from mockups)

| Filter | Type | Options | Default |
|--------|------|---------|---------|
| Date Range | Month range picker | Jan–Dec of selected year | Current financial year |
| Financial Year | Dropdown | This Year, Last Year, custom | This Year |
| Channel | Dropdown | All, Retail, Wholesale | All |
| Dealer | Searchable dropdown | All wholesale customers | None (all) |
| User/Salesman | Dropdown | All CRM users with sales_rep role | None (all) |

### Sort Specifications

| Sort Option | Behavior |
|-------------|----------|
| **A-Z** (default) | Alphabetical by dimension label |
| **Qty High→Low** | By total qty (sum across all months) descending |
| **Value High→Low** | By total value (sum across all months) descending |

### Export Specifications

| Format | Content | Library |
|--------|---------|---------|
| CSV | Full table data with month columns | maatwebsite/excel |
| PDF | Formatted landscape table | DomPDF, A3 landscape |

---

## 6. Profit Reports (Phase 2)

### 6A. Profit by Order

**Different format** from other profit reports — flat table, not monthly pivot.

| Column | Source |
|--------|--------|
| Invoice | `o.order_number` |
| Description | First line item brand/model or concatenated summary |
| Value | `o.total` |
| Profit | `o.gross_profit` |

**Filters:** Date range, Channel (retail/wholesale)
**Sort:** A-Z by invoice number, Value ↓
**Export:** CSV only
**Default time frame:** Previous month

**Page:** `app/Filament/Pages/Reports/ProfitByOrder.php`
**View:** `resources/views/filament/pages/reports/profit-by-order.blade.php`

### 6B. Profit by Brand (and all other dimensions)

**Monthly pivot format** — same as sales but with single "Profit" column per month + TOTAL column.

```
┌──────────────────────┬──────────┬──────────┬──────────┬──────────┬──────────┐
│  Brand               │ Jan 2025 │ Feb 2025 │ Mar 2025 │ Apr 2025 │  TOTAL   │
├──────────────────────┼──────────┼──────────┼──────────┼──────────┼──────────┤
│  All Terrain Ind.    │  12,000  │   8,000  │  22,000  │  18,000  │   4,000  │
│  Konig               │   4,000  │       0  │   6,300  │       0  │       0  │
│  ...                 │          │          │          │          │          │
├──────────────────────┼──────────┼──────────┼──────────┼──────────┼──────────┤
│  TOTAL               │  49,000  │  49,000  │  49,000  │  49,000  │  49,000  │
└──────────────────────┴──────────┴──────────┴──────────┴──────────┴──────────┘
```

**Filters:** Date range, Channel, Dealer
**Sort:** A-Z (default), Value ↓ (by total profit across all months)
**Export:** CSV

All profit dimension reports use `ReportService::profitByDimension()` with the same dimension mapping as sales reports.

| Report | Notes |
|--------|-------|
| Profit by Brand | Same dimensions as Sales by Brand |
| Profit by Model | Same dimensions as Sales by Model |
| Profit by SKU | Same dimensions as Sales by SKU |
| Profit by Vehicle | Same dimensions as Sales by Vehicle |
| Profit by Dealer | Same dimensions as Sales by Dealer |
| Profit by Month | Group by month, simpler layout |
| Profit by Salesman | Same dimensions as Sales by Team |
| Profit by Channel | Same dimensions as Sales by Channel |
| Profit by Categories | Same dimensions as Sales by Categories |

**Shared View:** `resources/views/filament/pages/reports/profit-report.blade.php`

---

## 7. Inventory Reports (Phase 3)

### 7A. Inventory by SKU (Month)

**Format:** Monthly pivot with **Added** and **Sold** sub-columns per month + TOTAL column.

```
┌──────────────┬───────────────┬───────────────┬───────────────┬──────────────┐
│  SKU         │  Jan 2025     │  Feb 2025     │  Mar 2025     │  TOTAL       │
│              │ Added   Sold  │ Added   Sold  │ Added   Sold  │ Added  Sold  │
├──────────────┼───────────────┼───────────────┼───────────────┼──────────────┤
│  RRW RR7-H   │  12     *15*  │   8      *8*  │  22     *16*  │  40     49   │
│  RRW RG7-H   │   4     *33*  │   0      *0*  │   8      *5*  │  12     38   │
└──────────────┴───────────────┴───────────────┴───────────────┴──────────────┘
```

**Special Feature: Clickable Sold Qty**

When user clicks an underlined "Sold" number, a **modal popup** appears:

```
┌─────────────────────────────────────────────────────────────┐
│  ✕  Jan 2025 - RR7-H-1785-0139-BR                         │
│                                                             │
│  Invoice    │ Customer              │ Qty Sold │ Date Sold  │
│  154151     │ Fast Lane Tyre Trading│    12    │ 22-10-2024 │
│  1681       │ Titan Performance     │     5    │ 05-12-2024 │
│  89446      │ 4WK Garage           │     1    │ 15-03-2025 │
└─────────────────────────────────────────────────────────────┘
```

**Data Sources:**
- **Added:** `inventory_logs` table — `action IN ('adjustment', 'transfer_in')` where `quantity_after > quantity_before`
- **Sold:** `order_items` from invoices — `SUM(quantity)` grouped by SKU and month

**Popup query:**
```sql
SELECT o.order_number AS invoice, 
       COALESCE(c.business_name, CONCAT(c.first_name, ' ', c.last_name)) AS customer,
       oi.quantity AS qty_sold,
       o.issue_date AS date_sold
FROM order_items oi
JOIN orders o ON o.id = oi.order_id
LEFT JOIN customers c ON c.id = o.customer_id
WHERE o.document_type = 'invoice'
  AND oi.sku = :sku
  AND o.issue_date BETWEEN :month_start AND :month_end
ORDER BY o.issue_date
```

**Filters:** Date range, Channel, Dealer, User
**Sort:** A-Z (default), Sold Volume ↓ (total sold across all months)
**Export:** CSV

**Page:** `app/Filament/Pages/Reports/InventoryBySku.php`
**View:** `resources/views/filament/pages/reports/inventory-report.blade.php`

### 7B. Inventory by Brand / Model

Same layout as Inventory by SKU, but grouped by `brand_name` / `model_name`:

- Added = SUM of inventory_logs for all SKUs in that brand/model
- Sold = SUM of order_items for all SKUs in that brand/model

**Pages:** `InventoryByBrand.php`, `InventoryByModel.php`
**View:** Same shared `inventory-report.blade.php` with `labelHeader` variable

---

## 8. Dealer Reports (Phase 4)

### 8A. Dealer Sales by Brand

**Same monthly pivot as Sales by Brand** but scoped to a single dealer, with a **dealer dropdown** at the top to switch dealers.

```
┌──────────────────┬───────────────┬───────────────┬──────────────┐
│  COMPANY 1  ▼    │  Jan 2025     │  Feb 2025     │  TOTAL       │
│  Brand           │  Qty   Value  │  Qty   Value  │  Qty  Value  │
├──────────────────┼───────────────┼───────────────┼──────────────┤
│  RRW              │  12     15    │   8      8    │  40    49    │
│  VOSSEN           │   4     33    │   0      0    │  12    38    │
└──────────────────┴───────────────┴───────────────┴──────────────┘
```

**Implementation:** Reuses `ReportService::salesByDimension()` with `dealer_id` filter pre-set.

The dealer dropdown queries:
```php
Customer::where('customer_type', 'wholesale')->pluck('business_name', 'id')
```

**Page:** `app/Filament/Pages/Reports/DealerSalesByBrand.php`
**View:** `resources/views/filament/pages/reports/dealer-sales-report.blade.php`

### 8B. Dealer Sales by Model

Same as above but grouped by model instead of brand.

### 8C. Dealer Vehicle Searches

Deferred — requires wholesale frontend search tracking integration.

---

## 9. Team Reports (Phase 5)

### 9A. Orders by User

**Two-part layout:**

**Part 1 — Comparison Table** (top)

```
┌──────────────┬───────────────┬───────────────┬──────────────┐
│  User        │  Jan 2025     │  Feb 2025     │  TOTAL       │
│              │  Qty   Value  │  Qty   Value  │  Qty  Value  │
├──────────────┼───────────────┼───────────────┼──────────────┤
│  *User 1*    │  12     15    │   8      8    │  40    49    │
│  *User 2*    │   4     33    │   0      0    │  12    38    │
└──────────────┴───────────────┴───────────────┴──────────────┘
```

User names are **clickable/underlined** — clicking toggles the detail table below.

**Part 2 — Individual User Detail** (bottom)

```
┌───────────────────────────────────────────────────────────┐
│  User 1  ▼                                                │
│                                                           │
│  Invoice   │ Description    │ Value   │ Profit            │
│  323672    │ Vossen         │  8,000  │ 22,000            │
│  3452      │ ...            │  4,000  │  6,300            │
│  ...                                                      │
│  TOTAL     │                │ 49,000  │ 49,000            │
└───────────────────────────────────────────────────────────┘
```

**Tracking:** Tracked by `representative_id` on the order — the salesman selected at order creation time.

**Filters:** Date range, Channel (retail/wholesale)
**Sort:** A-Z
**Export:** CSV + PDF

**Page:** `app/Filament/Pages/Reports/OrdersByUser.php`
**View:** `resources/views/filament/pages/reports/team-report.blade.php`

---

## 10. Dashboard Summary Cards (Phase 6)

### Reports Landing Page

The top-level Reports page displays summary stat cards for the selected financial year:

| Card | Source Query | Format |
|------|-------------|--------|
| Retail Orders | `COUNT(*)` from orders WHERE document_type='invoice' AND external_source='retail' | `1,650` |
| Wholesale Orders | Same but external_source='wholesale' | `634` |
| Retail Sales | `SUM(total)` from invoices WHERE external_source='retail' | `AED 265,000` |
| Wholesale Sales | Same but external_source='wholesale' | `AED 337,000` |
| Open Orders | `COUNT(*)` from orders WHERE order_status NOT IN ('completed','cancelled','delivered') | `16` |
| Accounts Receivable | `SUM(outstanding_amount)` from invoices WHERE payment_status != 'paid' | `AED 36,000` |
| Inventory Value | `SUM(pi.quantity * pv.uae_retail_price)` from inventory-grid scoped stock only: active non-system warehouses, inventory-tracked products, SKU-backed variants | `AED 800,000` |
| Incoming Inventory Value | `SUM(pi.eta_qty * pv.uae_retail_price)` from inventory-grid scoped incoming stock only: active non-system warehouses, inventory-tracked products, SKU-backed variants | `AED 200,000` |
| Website Visits | External analytics (placeholder / future integration) | `2,680` |

**Page:** `app/Filament/Pages/Reports/ReportsIndex.php`
**View:** `resources/views/filament/pages/reports/index.blade.php`

Below the cards, display the **report category links** grid as shown in the mockup:
- Sales Reports (8 links)
- Profit Reports (10 links)
- Inventory Reports (3 links)
- Dealer Reports (3 links)
- Website Reports (4 links)
- Team Reports (1 link)

---

## 11. File Structure

```
app/
├── Services/
│   └── ReportService.php                          # Central query/aggregation service
│
├── Exports/
│   ├── ReportCsvExport.php                        # Generic CSV exporter
│   └── ReportPdfExport.php                        # Generic PDF exporter
│
├── Filament/
│   └── Pages/
│       ├── Concerns/
│       │   └── HasReportFilters.php               # Shared trait for all report pages
│       │
│       └── Reports/
│           ├── ReportsIndex.php                   # Landing page with stats + links
│           │
│           ├── SalesByBrand.php                   # Sales report pages
│           ├── SalesByModel.php
│           ├── SalesBySize.php
│           ├── SalesByVehicle.php
│           ├── SalesByDealer.php
│           ├── SalesBySku.php
│           ├── SalesByChannel.php
│           ├── SalesByTeam.php
│           ├── SalesByCategories.php
│           │
│           ├── ProfitByOrder.php                  # Profit report pages
│           ├── ProfitByBrand.php
│           ├── ProfitByModel.php
│           ├── ProfitBySku.php
│           ├── ProfitBySize.php
│           ├── ProfitByVehicle.php
│           ├── ProfitByDealer.php
│           ├── ProfitByMonth.php
│           ├── ProfitBySalesman.php
│           ├── ProfitByChannel.php
│           ├── ProfitByCategories.php
│           │
│           ├── InventoryBySku.php                 # Inventory report pages
│           ├── InventoryByBrand.php
│           ├── InventoryByModel.php
│           │
│           ├── DealerSalesByBrand.php             # Dealer report pages
│           ├── DealerSalesByModel.php
│           │
│           └── OrdersByUser.php                   # Team report page

resources/views/
├── components/
│   ├── report-toolbar.blade.php                   # Shared toolbar (date, sort, filter, export)
│   ├── report-table.blade.php                     # Shared monthly pivot table
│   └── report-sold-popup.blade.php                # Clickable sold qty popup (Alpine modal)
│
├── filament/pages/reports/
│   ├── index.blade.php                            # Reports landing page
│   ├── sales-report.blade.php                     # Shared template for all sales reports
│   ├── profit-report.blade.php                    # Shared template for monthly profit reports
│   ├── profit-by-order.blade.php                  # Profit by Order (flat table)
│   ├── inventory-report.blade.php                 # Shared inventory Added/Sold template
│   ├── dealer-sales-report.blade.php              # Dealer reports with dealer dropdown
│   └── team-report.blade.php                      # Team comparison + detail table
│
├── exports/
│   └── report-pdf.blade.php                       # PDF export template (A3 landscape)
```

---

## 12. Database Considerations

### No New Tables Required

All data needed for reporting already exists:

| Data Need | Source |
|-----------|--------|
| Sales data (qty, value) | `order_items.quantity`, `order_items.line_total` |
| Brand grouping | `order_items.brand_name` (denormalized) |
| Model grouping | `order_items.model_name` (denormalized) |
| Size grouping | `order_items.item_attributes->>'size'` (JSON) |
| SKU grouping | `order_items.sku` |
| Vehicle grouping | `orders.vehicle_make/model/sub_model` |
| Channel filtering | `orders.external_source` |
| Dealer info | `orders.customer_id` → `customers` |
| Representative/Salesman | `orders.representative_id` → `users` |
| Month pivoting | `orders.issue_date` |
| Profit (order-level) | `orders.gross_profit` |
| Cost (item-level fallback) | `order_items.variant_snapshot->>'cost'` |
| Inventory added | `inventory_logs` (action, quantity_before, quantity_after) |
| Inventory sold | `order_items` from invoices |

### Recommended Indexes for Performance

```sql
-- Composite index for the primary report query pattern
ALTER TABLE orders ADD INDEX idx_orders_report (document_type, issue_date, deleted_at, external_source);

-- Index for line item aggregation
ALTER TABLE order_items ADD INDEX idx_order_items_brand (brand_name, order_id);
ALTER TABLE order_items ADD INDEX idx_order_items_model (model_name, order_id);
ALTER TABLE order_items ADD INDEX idx_order_items_sku (sku, order_id);

-- Index for inventory log queries
ALTER TABLE inventory_logs ADD INDEX idx_inventory_logs_report (created_at, product_variant_id, action);

-- Index for customer type filtering in dealer reports
ALTER TABLE customers ADD INDEX idx_customers_type (customer_type);
```

Create as a migration:

```
database/migrations/xxxx_add_reporting_indexes.php
```

### Optional: Materialized View / Cache Strategy

For large datasets, consider caching monthly aggregates:
- **Option A:** Laravel Cache — cache `ReportService` results for 15 minutes per unique query signature
- **Option B:** Database materialized view updated nightly (more complex, defer unless needed)

Start with **Option A** — add cache layer to `ReportService` methods:
```php
$cacheKey = "report:{$type}:{$dimension}:{$start}:{$end}:" . md5(json_encode($filters));
return Cache::remember($cacheKey, now()->addMinutes(15), fn() => $this->runQuery(...));
```

---

## 13. Implementation Order & Dependencies

### Phase 0 — Shared Infrastructure (do first)
| # | Task | File(s) | Est. Complexity |
|---|------|---------|----------------|
| 0.1 | Create `HasReportFilters` trait | `app/Filament/Pages/Concerns/HasReportFilters.php` | Medium |
| 0.2 | Create `ReportService` with `salesByDimension()` | `app/Services/ReportService.php` | High |
| 0.3 | Create `<x-report-toolbar>` Blade component | `resources/views/components/report-toolbar.blade.php` | Medium |
| 0.4 | Create `<x-report-table>` Blade component | `resources/views/components/report-table.blade.php` | High |
| 0.5 | Create reporting indexes migration | `database/migrations/xxxx_add_reporting_indexes.php` | Low |
| 0.6 | Create `ReportCsvExport` class | `app/Exports/ReportCsvExport.php` | Low |
| 0.7 | Create `ReportPdfExport` class | `app/Exports/ReportPdfExport.php` | Low |
| 0.8 | Register report navigation in `AdminPanelProvider` | `app/Providers/Filament/AdminPanelProvider.php` | Low |

### Phase 1 — Sales Reports (build one, clone the rest)
| # | Task | Depends On |
|---|------|-----------|
| 1.1 | Build Sales by Brand (reference implementation) | Phase 0 |
| 1.2 | Build shared `sales-report.blade.php` | 0.3, 0.4 |
| 1.3 | Clone for Sales by Model | 1.1 |
| 1.4 | Clone for Sales by Size | 1.1 |
| 1.5 | Clone for Sales by Vehicle | 1.1 |
| 1.6 | Clone for Sales by Dealer | 1.1 |
| 1.7 | Clone for Sales by SKU | 1.1 |
| 1.8 | Clone for Sales by Channel | 1.1 |
| 1.9 | Clone for Sales by Team | 1.1 |
| 1.10 | Clone for Sales by Categories | 1.1 |

### Phase 2 — Profit Reports
| # | Task | Depends On |
|---|------|-----------|
| 2.1 | Add `profitByDimension()` to ReportService | Phase 0 |
| 2.2 | Build Profit by Order (unique flat layout) | 2.1 |
| 2.3 | Build `profit-report.blade.php` shared view | 0.3, 0.4 |
| 2.4 | Build Profit by Brand (reference) | 2.1, 2.3 |
| 2.5 | Clone for all other Profit reports (Model, SKU, Size, Vehicle, Dealer, Month, Salesman, Channel, Categories) | 2.4 |

### Phase 3 — Inventory Reports
| # | Task | Depends On |
|---|------|-----------|
| 3.1 | Done - Add `inventoryByDimension()` + sold drilldown support to ReportService | Phase 0 |
| 3.2 | Done - Implement sold-quantity popup behavior in the inventory report view | — |
| 3.3 | Done - Build `inventory-report.blade.php` shared view | 0.3, 0.4, 3.2 |
| 3.4 | Done - Build Inventory by SKU | 3.1, 3.3 |
| 3.5 | Done - Clone for Inventory by Brand, Model | 3.4 |

### Phase 4 — Dealer Reports
| # | Task | Depends On |
|---|------|-----------|
| 4.1 | Done - Build `dealer-sales-report.blade.php` (adds dealer dropdown) | Phase 1 |
| 4.2 | Done - Build Dealer Sales by Brand | 4.1 |
| 4.3 | Done - Clone for Dealer Sales by Model | 4.2 |

### Phase 5 — Team Reports
| # | Task | Depends On |
|---|------|-----------|
| 5.1 | Done - Add `teamPerformance()` + `userOrderDetails()` to ReportService | Phase 0 |
| 5.2 | Done - Build `team-report.blade.php` (two-part layout) | 0.3, 0.4 |
| 5.3 | Done - Build Orders by User | 5.1, 5.2 |

### Phase 6 — Reports Dashboard
| # | Task | Depends On |
|---|------|-----------|
| 6.1 | Build `ReportsIndex.php` landing page | — |
| 6.2 | Build `index.blade.php` with stat cards + report links grid | 6.1 |
| 6.3 | Wire up summary card queries | 6.1 |

### Suggested Build Order Summary

```
Phase 0 (Foundation)     → Phase 1.1-1.2 (Sales by Brand as reference)
                          → Phase 1.3-1.10 (Clone remaining sales)
                          → Phase 2 (Profit reports)
                          → Phase 3 (Inventory reports)
                          → Phase 4 (Dealer reports)
                          → Phase 5 (Team reports)  
                          → Phase 6 (Dashboard landing page)
```

---

## Appendix A: Navigation Structure

```
Reports                              ← ReportsIndex (landing page with cards)
├── Sales Reports
│   ├── Sales by Brand
│   ├── Sales by Model
│   ├── Sales by Size
│   ├── Sales by Vehicle
│   ├── Sales by Dealer
│   ├── Sales by SKU
│   ├── Sales by Channel
│   ├── Sales by Team
│   └── Sales by Categories
├── Profit Reports
│   ├── Profit by Order
│   ├── Profit by Brand
│   ├── Profit by Model
│   ├── Profit by SKU
│   ├── Profit by Size
│   ├── Profit by Vehicle
│   ├── Profit by Dealer
│   ├── Profit by Month
│   ├── Profit by Salesman
│   ├── Profit by Channel
│   └── Profit by Categories
├── Inventory Reports
│   ├── Inventory by SKU
│   ├── Inventory by Brand
│   └── Inventory by Model
├── Dealer Reports
│   ├── Sales by Brand
│   ├── Sales by Model
│   └── Vehicle Searches        (deferred)
├── Website Reports              (deferred)
│   ├── Vehicle Searches
│   ├── Size Searches
│   ├── Product Views
│   └── Abandoned Carts
└── Team Reports
    └── Orders by User
```

## Appendix B: Permissions

| Permission | Gates |
|------------|-------|
| `view_reports` | Access to Reports navigation group |
| `view_sales_reports` | Access to Sales Reports sub-group |
| `view_profit_reports` | Access to Profit Reports sub-group |
| `view_inventory_reports` | Access to Inventory Reports sub-group |
| `view_dealer_reports` | Access to Dealer Reports sub-group |
| `view_team_reports` | Access to Team Reports sub-group |
| `export_reports` | Enable Export button (CSV/PDF) |

Register in existing `spatie/laravel-permission` seeder.

## Appendix C: Key Architectural Decisions

| Decision | Rationale |
|----------|-----------|
| Custom Pages over Filament Table Resources | Reports need monthly column pivoting, not standard CRUD tables |
| Shared Blade components over per-report views | 30+ reports with identical table structure — DRY |
| `ReportService` service class over inline queries | Single source of truth for query logic, testable, cacheable |
| Alpine.js modals for sold popup | Lightweight, no full-page reload, consistent with existing codebase |
| `issue_date` for month grouping | Represents when the invoice was issued — the business-meaningful date |
| Denormalized `brand_name`/`model_name` on order_items | Already exists, avoids JOINs through products → brands → models |
| Proportional profit distribution | Order-level `gross_profit` includes expenses; distributing by line_total share gives per-dimension breakdown |
| No new database tables | All data already captured via order sync + inventory logs |
| Cache at service layer | 15-min TTL prevents repeated heavy aggregation queries |
