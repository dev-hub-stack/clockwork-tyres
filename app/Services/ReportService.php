<?php

namespace App\Services;

use App\Modules\Orders\Enums\DocumentType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function channelDimensionExpression(string $orderAlias = 'o', string $customerAlias = 'c'): string
    {
        return $this->salesChannelExpression($orderAlias, $customerAlias);
    }

    private function salesChannelExpression(string $orderAlias = 'o', string $customerAlias = 'c'): string
    {
        return "CASE
            WHEN {$orderAlias}.external_source = 'wholesale' THEN 'wholesale'
            WHEN {$orderAlias}.external_source IN ('retail', 'tunerstop', 'tunerstop_admin', 'tunerstop_historical') THEN 'retail'
            WHEN {$customerAlias}.customer_type IN ('dealer', 'wholesale', 'corporate') THEN 'wholesale'
            ELSE 'retail'
        END";
    }

    private function applyChannelFilter(object $query, array $filters, string $orderAlias = 'o', string $customerAlias = 'c'): void
    {
        if (($filters['channel'] ?? 'all') === 'all') {
            return;
        }

        $query->whereRaw($this->salesChannelExpression($orderAlias, $customerAlias) . ' = ?', [$filters['channel']]);
    }

    private function invoiceNumberExpression(string $tableAlias = 'o'): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "COALESCE({$tableAlias}.order_number, 'INV-' || {$tableAlias}.id)"
            : "COALESCE({$tableAlias}.order_number, CONCAT('INV-', {$tableAlias}.id))";
    }

    private function customerNameExpression(string $tableAlias = 'c'): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "COALESCE(NULLIF({$tableAlias}.business_name, ''), TRIM(COALESCE({$tableAlias}.first_name, '') || ' ' || COALESCE({$tableAlias}.last_name, '')), 'Unknown Customer')"
            : "COALESCE(NULLIF({$tableAlias}.business_name, ''), CONCAT_WS(' ', NULLIF({$tableAlias}.first_name, ''), NULLIF({$tableAlias}.last_name, '')), 'Unknown Customer')";
    }

    private function variantCostExpression(string $columnExpression = 'oi.variant_snapshot'): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(json_extract({$columnExpression}, '$.cost') AS DECIMAL(12,2))"
            : "CAST(JSON_UNQUOTE(JSON_EXTRACT({$columnExpression}, '$.cost')) AS DECIMAL(12,2))";
    }

    private function monthKeyExpression(string $columnExpression): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$columnExpression})"
            : "DATE_FORMAT({$columnExpression}, '%Y-%m')";
    }

    private function jsonTextExpression(string $columnExpression, string $path): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "json_extract({$columnExpression}, '$.{$path}')"
            : "JSON_UNQUOTE(JSON_EXTRACT({$columnExpression}, '$.{$path}'))";
    }

    public function sizeDimensionExpression(string $orderItemAlias = 'oi', string $variantAlias = 'pv'): string
    {
        $itemSize = $this->jsonTextExpression("{$orderItemAlias}.item_attributes", 'size');
        $variantSize = $this->jsonTextExpression("{$orderItemAlias}.variant_snapshot", 'size');
        $productSize = $this->jsonTextExpression("{$orderItemAlias}.product_snapshot", 'size');

        return "COALESCE(NULLIF(TRIM({$itemSize}), ''), NULLIF(TRIM({$variantSize}), ''), NULLIF(TRIM({$productSize}), ''), NULLIF(TRIM({$variantAlias}.size), ''))";
    }

    public function categoryDimensionExpression(string $orderItemAlias = 'oi', string $categoryAlias = 'ac'): string
    {
        $snapshotCategory = $this->jsonTextExpression("{$orderItemAlias}.addon_snapshot", 'category_name');
        $itemCategory = $this->jsonTextExpression("{$orderItemAlias}.item_attributes", 'category_name');

        return "COALESCE(NULLIF(TRIM({$snapshotCategory}), ''), NULLIF(TRIM({$itemCategory}), ''), NULLIF(TRIM(COALESCE({$categoryAlias}.display_name, {$categoryAlias}.name)), ''), CASE WHEN {$orderItemAlias}.add_on_id IS NOT NULL THEN 'Accessories' ELSE 'Wheels' END)";
    }

    private function inventoryCategoryDimensionExpression(string $logAlias = 'il', string $categoryAlias = 'ac'): string
    {
        return "CASE WHEN {$logAlias}.add_on_id IS NOT NULL THEN COALESCE(NULLIF(TRIM({$categoryAlias}.display_name), ''), NULLIF(TRIM({$categoryAlias}.name), ''), 'Accessories') ELSE 'Wheels' END";
    }

    private function applyBrandFilterToSalesQuery(object $query, array $filters): void
    {
        if (($filters['brand'] ?? '') === '') {
            return;
        }

        $query->where('oi.brand_name', $filters['brand']);
    }

    private function applyBrandFilterToInventoryQuery(object $query, array $filters): void
    {
        if (($filters['brand'] ?? '') === '') {
            return;
        }

        $query->where('b.name', $filters['brand']);
    }

    private function applyCategoryFilterToSalesQuery(object $query, array $filters, string $orderItemAlias = 'oi'): void
    {
        if (($filters['category'] ?? '') === '') {
            return;
        }

        $query->whereRaw($this->categoryDimensionExpression($orderItemAlias) . ' = ?', [$filters['category']]);
    }

    private function applyCategoryFilterToInventoryQuery(object $query, array $filters, string $logAlias = 'il', string $categoryAlias = 'ac'): void
    {
        if (($filters['category'] ?? '') === '') {
            return;
        }

        $query->whereRaw($this->inventoryCategoryDimensionExpression($logAlias, $categoryAlias) . ' = ?', [$filters['category']]);
    }

    private function applySearchFilter(object $query, ?string $expression, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '' || blank($expression)) {
            return;
        }

        $query->whereRaw("COALESCE({$expression}, '') LIKE ?", ['%' . $search . '%']);
    }

    public function salesByDimension(
        string $groupExpression,
        Carbon $startDate,
        Carbon $endDate,
        array $filters = [],
        array $options = [],
    ): Collection {
        $qtySelect = ($options['qty_aggregate'] ?? 'quantity') === 'invoice_count'
            ? 'COUNT(DISTINCT o.id) as qty'
            : 'SUM(oi.quantity) as qty';

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->leftJoin('addons as a', 'a.id', '=', 'oi.add_on_id')
            ->leftJoin('addon_categories as ac', 'ac.id', '=', 'a.addon_category_id')
            ->selectRaw("COALESCE(NULLIF(TRIM({$groupExpression}), ''), 'Unassigned') as dimension_label")
            ->selectRaw($this->monthKeyExpression('COALESCE(o.issue_date, o.created_at)') . ' as month_key')
            ->selectRaw($qtySelect)
            ->selectRaw('SUM(oi.line_total) as value')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $this->applyChannelFilter($query, $filters);
            })
            ->when(($filters['brand'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyBrandFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['category'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyCategoryFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters, $options) {
                $this->applySearchFilter($query, $options['search_expression'] ?? null, $filters);
            })
            ->when(! empty($filters['dealer_id']), function ($query) use ($filters) {
                $query->where('o.customer_id', (int) $filters['dealer_id']);
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                $query->where('o.representative_id', (int) $filters['user_id']);
            })
            ->groupBy('dimension_label', 'month_key')
            ->orderBy('dimension_label')
            ->orderBy('month_key')
            ->get();

        $detailMap = [];
        if (($options['include_details'] ?? false) === true) {
            $detailRows = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
                ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
                ->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
                ->leftJoin('addons as a', 'a.id', '=', 'oi.add_on_id')
                ->leftJoin('addon_categories as ac', 'ac.id', '=', 'a.addon_category_id')
                ->selectRaw("COALESCE(NULLIF(TRIM({$groupExpression}), ''), 'Unassigned') as dimension_label")
                ->selectRaw($this->monthKeyExpression('COALESCE(o.issue_date, o.created_at)') . ' as month_key')
                ->selectRaw($this->invoiceNumberExpression('o') . ' as invoice_number')
                ->selectRaw($this->customerNameExpression('c') . ' as customer_name')
                ->selectRaw('DATE(COALESCE(o.issue_date, o.created_at)) as sold_on')
                ->selectRaw('SUM(oi.quantity) as qty_sold')
                ->where('o.document_type', DocumentType::INVOICE->value)
                ->whereNull('o.deleted_at')
                ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                    $startDate->toDateString(),
                    $endDate->toDateString(),
                ])
                ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                    $this->applyChannelFilter($query, $filters);
                })
                ->when(($filters['brand'] ?? '') !== '', function ($query) use ($filters) {
                    $this->applyBrandFilterToSalesQuery($query, $filters);
                })
                ->when(($filters['category'] ?? '') !== '', function ($query) use ($filters) {
                    $this->applyCategoryFilterToSalesQuery($query, $filters);
                })
                ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters, $options) {
                    $this->applySearchFilter($query, $options['search_expression'] ?? null, $filters);
                })
                ->when(! empty($filters['dealer_id']), function ($query) use ($filters) {
                    $query->where('o.customer_id', (int) $filters['dealer_id']);
                })
                ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                    $query->where('o.representative_id', (int) $filters['user_id']);
                })
                ->groupBy('dimension_label', 'month_key', 'invoice_number', 'customer_name', 'sold_on')
                ->orderBy('sold_on')
                ->get();

            foreach ($detailRows as $row) {
                $detailMap[$row->dimension_label][$row->month_key][] = [
                    'invoice' => $row->invoice_number,
                    'customer' => $row->customer_name,
                    'qty_sold' => (int) $row->qty_sold,
                    'date_sold' => $row->sold_on,
                ];
            }
        }

        $months = $this->monthsBetween($startDate, $endDate)->pluck('key')->all();

        return $rows
            ->groupBy('dimension_label')
            ->map(function (Collection $group, string $label) use ($months, $detailMap) {
                $monthMap = [];

                foreach ($months as $monthKey) {
                    $monthMap[$monthKey] = [
                        'qty' => 0,
                        'value' => 0.0,
                        'details' => $detailMap[$label][$monthKey] ?? [],
                    ];
                }

                foreach ($group as $row) {
                    $monthMap[$row->month_key] = [
                        'qty' => (int) $row->qty,
                        'value' => (float) $row->value,
                        'details' => $detailMap[$label][$row->month_key] ?? [],
                    ];
                }

                return [
                    'label' => $label,
                    'months' => $monthMap,
                    'total_qty' => collect($monthMap)->sum('qty'),
                    'total_value' => collect($monthMap)->sum('value'),
                ];
            })
            ->values();
    }

    public function summaryCards(Carbon $startDate, Carbon $endDate): array
    {
        $invoiceDateExpression = DB::raw('DATE(COALESCE(o.issue_date, o.created_at))');
        $salesChannelExpression = $this->salesChannelExpression('o', 'c');

        $invoiceBaseQuery = DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween($invoiceDateExpression, [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        $retailOrders = (clone $invoiceBaseQuery)
            ->whereRaw("{$salesChannelExpression} = ?", ['retail'])
            ->count();

        $wholesaleOrders = (clone $invoiceBaseQuery)
            ->whereRaw("{$salesChannelExpression} = ?", ['wholesale'])
            ->count();

        $retailSales = (clone $invoiceBaseQuery)
            ->whereRaw("{$salesChannelExpression} = ?", ['retail'])
            ->sum('o.total');

        $wholesaleSales = (clone $invoiceBaseQuery)
            ->whereRaw("{$salesChannelExpression} = ?", ['wholesale'])
            ->sum('o.total');

        $openOrders = DB::table('orders')
            ->where('document_type', DocumentType::INVOICE->value)
            ->whereNull('deleted_at')
            ->whereNotIn('order_status', ['completed', 'cancelled', 'delivered'])
            ->count();

        $accountsReceivable = DB::table('orders')
            ->where('document_type', DocumentType::INVOICE->value)
            ->whereNull('deleted_at')
            ->where('payment_status', '!=', 'paid')
            ->sum('outstanding_amount');

        $inventoryGridBaseQuery = DB::table('product_inventories as pi')
            ->join('warehouses as w', 'w.id', '=', 'pi.warehouse_id')
            ->join('product_variants as pv', 'pv.id', '=', 'pi.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('w.status', 1)
            ->where('w.is_system', false)
            ->where('p.track_inventory', true)
            ->whereNotNull('pv.sku');

        $inventoryValue = (clone $inventoryGridBaseQuery)
            ->sum(DB::raw('COALESCE(pi.quantity, 0) * COALESCE(pv.uae_retail_price, 0)'));

        $incomingInventoryValue = (clone $inventoryGridBaseQuery)
            ->sum(DB::raw('COALESCE(pi.eta_qty, 0) * COALESCE(pv.uae_retail_price, 0)'));

        return [
            ['label' => 'Retail Invoices', 'value' => (int) $retailOrders, 'type' => 'number'],
            ['label' => 'Wholesale Invoices', 'value' => (int) $wholesaleOrders, 'type' => 'number'],
            ['label' => 'Retail Sales', 'value' => (float) $retailSales, 'type' => 'currency'],
            ['label' => 'Wholesale Sales', 'value' => (float) $wholesaleSales, 'type' => 'currency'],
            ['label' => 'Open Orders', 'value' => (int) $openOrders, 'type' => 'number'],
            ['label' => 'Accounts Receivable', 'value' => (float) $accountsReceivable, 'type' => 'currency'],
            ['label' => 'Inventory Value', 'value' => (float) $inventoryValue, 'type' => 'currency'],
            ['label' => 'Incoming Inventory Value', 'value' => (float) $incomingInventoryValue, 'type' => 'currency'],
            ['label' => 'Website Visits', 'value' => null, 'type' => 'placeholder'],
        ];
    }

    public function profitByDimension(
        string $groupExpression,
        Carbon $startDate,
        Carbon $endDate,
        array $filters = [],
        array $options = [],
    ): Collection {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->leftJoin('addons as a', 'a.id', '=', 'oi.add_on_id')
            ->leftJoin('addon_categories as ac', 'ac.id', '=', 'a.addon_category_id')
            ->selectRaw("COALESCE(NULLIF(TRIM({$groupExpression}), ''), 'Unassigned') as dimension_label")
            ->selectRaw($this->monthKeyExpression('COALESCE(o.issue_date, o.created_at)') . ' as month_key')
            ->selectRaw("SUM(
                CASE
                    WHEN o.gross_profit IS NOT NULL AND o.sub_total > 0
                        THEN o.gross_profit * (oi.line_total / o.sub_total)
                    ELSE oi.line_total - (
                        oi.quantity * COALESCE(
                            {$this->variantCostExpression('oi.variant_snapshot')},
                            0
                        )
                    )
                END
            ) as profit")
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $this->applyChannelFilter($query, $filters);
            })
            ->when(($filters['brand'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyBrandFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['category'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyCategoryFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters, $options) {
                $this->applySearchFilter($query, $options['search_expression'] ?? null, $filters);
            })
            ->when(! empty($filters['dealer_id']), function ($query) use ($filters) {
                $query->where('o.customer_id', (int) $filters['dealer_id']);
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                $query->where('o.representative_id', (int) $filters['user_id']);
            })
            ->groupBy('dimension_label', 'month_key')
            ->orderBy('dimension_label')
            ->orderBy('month_key')
            ->get();

        $months = $this->monthsBetween($startDate, $endDate)->pluck('key')->all();

        return $rows
            ->groupBy('dimension_label')
            ->map(function (Collection $group, string $label) use ($months) {
                $monthMap = [];

                foreach ($months as $monthKey) {
                    $monthMap[$monthKey] = [
                        'profit' => 0.0,
                    ];
                }

                foreach ($group as $row) {
                    $monthMap[$row->month_key] = [
                        'profit' => (float) $row->profit,
                    ];
                }

                return [
                    'label' => $label,
                    'months' => $monthMap,
                    'total_profit' => collect($monthMap)->sum('profit'),
                ];
            })
            ->values();
    }

    public function profitByOrder(Carbon $startDate, Carbon $endDate, array $filters = []): Collection
    {
        return DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
            ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->selectRaw('o.id')
            ->selectRaw($this->invoiceNumberExpression('o') . ' as invoice_number')
            ->selectRaw("COALESCE(MAX(NULLIF(oi.brand_name, '')), MAX(NULLIF(oi.product_name, '')), 'Invoice') as description")
            ->selectRaw('MAX(o.total) as value')
            ->selectRaw('MAX(COALESCE(o.gross_profit, 0)) as profit')
            ->selectRaw('MAX(' . $this->customerNameExpression('c') . ') as customer_name')
            ->selectRaw('MAX(DATE(COALESCE(o.issue_date, o.created_at))) as issued_on')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $this->applyChannelFilter($query, $filters);
            })
            ->when(! empty($filters['dealer_id']), function ($query) use ($filters) {
                $query->where('o.customer_id', (int) $filters['dealer_id']);
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                $query->where('o.representative_id', (int) $filters['user_id']);
            })
            ->groupBy('o.id', 'invoice_number')
            ->orderBy('issued_on', 'desc')
            ->get()
            ->map(fn ($row) => [
                'invoice_number' => $row->invoice_number,
                'description' => $row->description,
                'customer_name' => $row->customer_name,
                'issued_on' => $row->issued_on,
                'value' => (float) $row->value,
                'profit' => (float) $row->profit,
            ]);
    }

    public function inventoryByDimension(
        string $inventoryGroupExpression,
        string $salesGroupExpression,
        Carbon $startDate,
        Carbon $endDate,
        array $filters = [],
        array $options = [],
    ): Collection {
        $months = $this->monthsBetween($startDate, $endDate)->pluck('key')->all();

        $addedRows = DB::table('inventory_logs as il')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'il.product_variant_id')
            ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('models as m', 'm.id', '=', 'p.model_id')
            ->leftJoin('addons as a', 'a.id', '=', 'il.add_on_id')
            ->leftJoin('addon_categories as ac', 'ac.id', '=', 'a.addon_category_id')
            ->selectRaw("COALESCE(NULLIF(TRIM({$inventoryGroupExpression}), ''), 'Unassigned') as dimension_label")
            ->selectRaw($this->monthKeyExpression('il.created_at') . ' as month_key')
            ->selectRaw("SUM(CASE WHEN il.quantity_change > 0 AND il.action IN ('adjustment', 'transfer_in', 'import', 'return') THEN il.quantity_change ELSE 0 END) as added")
            ->whereBetween(DB::raw('DATE(il.created_at)'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['brand'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyBrandFilterToInventoryQuery($query, $filters);
            })
            ->when(($filters['category'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyCategoryFilterToInventoryQuery($query, $filters);
            })
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters, $options) {
                $this->applySearchFilter($query, $options['inventory_search_expression'] ?? null, $filters);
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                $query->where('il.user_id', (int) $filters['user_id']);
            })
            ->groupBy('dimension_label', 'month_key')
            ->orderBy('dimension_label')
            ->orderBy('month_key')
            ->get();

        $soldRows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->leftJoin('addons as a', 'a.id', '=', 'oi.add_on_id')
            ->leftJoin('addon_categories as ac', 'ac.id', '=', 'a.addon_category_id')
            ->selectRaw("COALESCE(NULLIF(TRIM({$salesGroupExpression}), ''), 'Unassigned') as dimension_label")
            ->selectRaw($this->monthKeyExpression('COALESCE(o.issue_date, o.created_at)') . ' as month_key')
            ->selectRaw('SUM(oi.quantity) as sold')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $this->applyChannelFilter($query, $filters);
            })
            ->when(($filters['brand'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyBrandFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['category'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyCategoryFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters, $options) {
                $this->applySearchFilter($query, $options['sales_search_expression'] ?? null, $filters);
            })
            ->when(! empty($filters['dealer_id']), function ($query) use ($filters) {
                $query->where('o.customer_id', (int) $filters['dealer_id']);
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                $query->where('o.representative_id', (int) $filters['user_id']);
            })
            ->groupBy('dimension_label', 'month_key')
            ->orderBy('dimension_label')
            ->orderBy('month_key')
            ->get();

        $detailRows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->leftJoin('addons as a', 'a.id', '=', 'oi.add_on_id')
            ->leftJoin('addon_categories as ac', 'ac.id', '=', 'a.addon_category_id')
            ->selectRaw("COALESCE(NULLIF(TRIM({$salesGroupExpression}), ''), 'Unassigned') as dimension_label")
            ->selectRaw($this->monthKeyExpression('COALESCE(o.issue_date, o.created_at)') . ' as month_key')
            ->selectRaw($this->invoiceNumberExpression('o') . ' as invoice_number')
            ->selectRaw($this->customerNameExpression('c') . ' as customer_name')
            ->selectRaw('DATE(COALESCE(o.issue_date, o.created_at)) as sold_on')
            ->selectRaw('SUM(oi.quantity) as qty_sold')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $this->applyChannelFilter($query, $filters);
            })
            ->when(($filters['brand'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyBrandFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['category'] ?? '') !== '', function ($query) use ($filters) {
                $this->applyCategoryFilterToSalesQuery($query, $filters);
            })
            ->when(($filters['search'] ?? '') !== '', function ($query) use ($filters, $options) {
                $this->applySearchFilter($query, $options['sales_search_expression'] ?? null, $filters);
            })
            ->when(! empty($filters['dealer_id']), function ($query) use ($filters) {
                $query->where('o.customer_id', (int) $filters['dealer_id']);
            })
            ->when(! empty($filters['user_id']), function ($query) use ($filters) {
                $query->where('o.representative_id', (int) $filters['user_id']);
            })
            ->groupBy('dimension_label', 'month_key', 'invoice_number', 'customer_name', 'sold_on')
            ->orderBy('sold_on')
            ->get();

        $addedMap = [];
        foreach ($addedRows as $row) {
            $addedMap[$row->dimension_label][$row->month_key] = (int) $row->added;
        }

        $soldMap = [];
        foreach ($soldRows as $row) {
            $soldMap[$row->dimension_label][$row->month_key] = (int) $row->sold;
        }

        $detailMap = [];
        foreach ($detailRows as $row) {
            $detailMap[$row->dimension_label][$row->month_key][] = [
                'invoice' => $row->invoice_number,
                'customer' => $row->customer_name,
                'qty_sold' => (int) $row->qty_sold,
                'date_sold' => $row->sold_on,
            ];
        }

        $labels = collect(array_unique(array_merge(array_keys($addedMap), array_keys($soldMap))));

        return $labels->map(function (string $label) use ($months, $addedMap, $soldMap, $detailMap) {
            $monthMap = [];

            foreach ($months as $monthKey) {
                $monthMap[$monthKey] = [
                    'added' => $addedMap[$label][$monthKey] ?? 0,
                    'sold' => $soldMap[$label][$monthKey] ?? 0,
                    'details' => $detailMap[$label][$monthKey] ?? [],
                ];
            }

            return [
                'label' => $label,
                'months' => $monthMap,
                'total_added' => collect($monthMap)->sum('added'),
                'total_sold' => collect($monthMap)->sum('sold'),
            ];
        })->values();
    }

    public function teamPerformance(Carbon $startDate, Carbon $endDate, array $filters = []): Collection
    {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
            ->selectRaw('o.representative_id as user_id')
            ->selectRaw("COALESCE(NULLIF(TRIM(u.name), ''), 'Unassigned') as user_name")
            ->selectRaw($this->monthKeyExpression('COALESCE(o.issue_date, o.created_at)') . ' as month_key')
            ->selectRaw('SUM(oi.quantity) as qty')
            ->selectRaw('SUM(oi.line_total) as value')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $this->applyChannelFilter($query, $filters);
            })
            ->groupBy('user_id', 'user_name', 'month_key')
            ->orderBy('user_name')
            ->orderBy('month_key')
            ->get();

        $months = $this->monthsBetween($startDate, $endDate)->pluck('key')->all();

        return $rows
            ->groupBy(fn ($row) => ($row->user_id ?? 'null') . '|' . $row->user_name)
            ->map(function (Collection $group) use ($months) {
                $first = $group->first();
                $monthMap = [];

                foreach ($months as $monthKey) {
                    $monthMap[$monthKey] = [
                        'qty' => 0,
                        'value' => 0.0,
                    ];
                }

                foreach ($group as $row) {
                    $monthMap[$row->month_key] = [
                        'qty' => (int) $row->qty,
                        'value' => (float) $row->value,
                    ];
                }

                return [
                    'user_id' => $first->user_id !== null ? (int) $first->user_id : null,
                    'label' => $first->user_name,
                    'months' => $monthMap,
                    'total_qty' => collect($monthMap)->sum('qty'),
                    'total_value' => collect($monthMap)->sum('value'),
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower($row['label']))
            ->values();
    }

    public function userOrderDetails(?int $userId, Carbon $startDate, Carbon $endDate, array $filters = []): Collection
    {
        if ($userId === null) {
            return collect();
        }

        return DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->selectRaw('o.id')
            ->selectRaw($this->invoiceNumberExpression('o') . ' as invoice_number')
            ->selectRaw("COALESCE(MAX(NULLIF(oi.brand_name, '')), MAX(NULLIF(oi.product_name, '')), 'Invoice') as description")
            ->selectRaw('MAX(o.total) as value')
            ->selectRaw('MAX(COALESCE(o.gross_profit, 0)) as profit')
            ->selectRaw('MAX(' . $this->customerNameExpression('c') . ') as customer_name')
            ->selectRaw('MAX(DATE(COALESCE(o.issue_date, o.created_at))) as issued_on')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->where('o.representative_id', $userId)
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $this->applyChannelFilter($query, $filters);
            })
            ->groupBy('o.id', 'invoice_number')
            ->orderBy('issued_on', 'desc')
            ->get()
            ->map(fn ($row) => [
                'invoice_number' => $row->invoice_number,
                'description' => $row->description,
                'customer_name' => $row->customer_name,
                'issued_on' => $row->issued_on,
                'value' => (float) $row->value,
                'profit' => (float) $row->profit,
            ]);
    }

    public function monthsBetween(Carbon $startDate, Carbon $endDate): Collection
    {
        return collect(CarbonPeriod::create(
            $startDate->copy()->startOfMonth(),
            '1 month',
            $endDate->copy()->startOfMonth(),
        ))->map(fn (Carbon $month) => [
            'key' => $month->format('Y-m'),
            'label' => $month->format('M Y'),
        ]);
    }
}