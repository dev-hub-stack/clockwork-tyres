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

    public function salesByDimension(
        string $groupExpression,
        Carbon $startDate,
        Carbon $endDate,
        array $filters = [],
    ): Collection {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
            ->selectRaw("COALESCE(NULLIF(TRIM({$groupExpression}), ''), 'Unassigned') as dimension_label")
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

        $inventoryValue = DB::table('product_inventories as pi')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'pi.product_variant_id')
            ->sum(DB::raw('COALESCE(pi.quantity, 0) * COALESCE(pv.cost, 0)'));

        $incomingInventoryValue = DB::table('product_inventories as pi')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'pi.product_variant_id')
            ->sum(DB::raw('COALESCE(pi.eta_qty, 0) * COALESCE(pv.cost, 0)'));

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
    ): Collection {
        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.representative_id')
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
    ): Collection {
        $months = $this->monthsBetween($startDate, $endDate)->pluck('key')->all();

        $addedRows = DB::table('inventory_logs as il')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'il.product_variant_id')
            ->leftJoin('products as p', 'p.id', '=', 'pv.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('models as m', 'm.id', '=', 'p.model_id')
            ->selectRaw("COALESCE(NULLIF(TRIM({$inventoryGroupExpression}), ''), 'Unassigned') as dimension_label")
            ->selectRaw($this->monthKeyExpression('il.created_at') . ' as month_key')
            ->selectRaw("SUM(CASE WHEN il.quantity_change > 0 AND il.action IN ('adjustment', 'transfer_in', 'import', 'return') THEN il.quantity_change ELSE 0 END) as added")
            ->whereBetween(DB::raw('DATE(il.created_at)'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
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