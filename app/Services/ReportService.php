<?php

namespace App\Services;

use App\Modules\Orders\Enums\DocumentType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
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
            ->selectRaw("DATE_FORMAT(COALESCE(o.issue_date, o.created_at), '%Y-%m') as month_key")
            ->selectRaw('SUM(oi.quantity) as qty')
            ->selectRaw('SUM(oi.line_total) as value')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $query->where('o.external_source', $filters['channel']);
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
        $invoiceDateExpression = DB::raw('DATE(COALESCE(issue_date, created_at))');

        $invoiceBaseQuery = DB::table('orders')
            ->where('document_type', DocumentType::INVOICE->value)
            ->whereNull('deleted_at')
            ->whereBetween($invoiceDateExpression, [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ]);

        $retailOrders = (clone $invoiceBaseQuery)
            ->where('external_source', 'retail')
            ->count();

        $wholesaleOrders = (clone $invoiceBaseQuery)
            ->where('external_source', 'wholesale')
            ->count();

        $retailSales = (clone $invoiceBaseQuery)
            ->where('external_source', 'retail')
            ->sum('total');

        $wholesaleSales = (clone $invoiceBaseQuery)
            ->where('external_source', 'wholesale')
            ->sum('total');

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
            ['label' => 'Retail Orders', 'value' => (int) $retailOrders, 'type' => 'number'],
            ['label' => 'Wholesale Orders', 'value' => (int) $wholesaleOrders, 'type' => 'number'],
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
            ->selectRaw("DATE_FORMAT(COALESCE(o.issue_date, o.created_at), '%Y-%m') as month_key")
            ->selectRaw("SUM(
                CASE
                    WHEN o.gross_profit IS NOT NULL AND o.sub_total > 0
                        THEN o.gross_profit * (oi.line_total / o.sub_total)
                    ELSE oi.line_total - (
                        oi.quantity * COALESCE(
                            CAST(JSON_UNQUOTE(JSON_EXTRACT(oi.variant_snapshot, '$.cost')) AS DECIMAL(12,2)),
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
                $query->where('o.external_source', $filters['channel']);
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
            ->selectRaw('COALESCE(o.order_number, CONCAT("INV-", o.id)) as invoice_number')
            ->selectRaw("COALESCE(MAX(NULLIF(oi.brand_name, '')), MAX(NULLIF(oi.product_name, '')), 'Invoice') as description")
            ->selectRaw('MAX(o.total) as value')
            ->selectRaw('MAX(COALESCE(o.gross_profit, 0)) as profit')
            ->selectRaw("COALESCE(MAX(NULLIF(c.business_name, '')), MAX(CONCAT_WS(' ', NULLIF(c.first_name, ''), NULLIF(c.last_name, ''))), 'Unknown Customer') as customer_name")
            ->selectRaw('MAX(DATE(COALESCE(o.issue_date, o.created_at))) as issued_on')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $query->where('o.external_source', $filters['channel']);
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
            ->selectRaw("DATE_FORMAT(il.created_at, '%Y-%m') as month_key")
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
            ->selectRaw("DATE_FORMAT(COALESCE(o.issue_date, o.created_at), '%Y-%m') as month_key")
            ->selectRaw('SUM(oi.quantity) as sold')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $query->where('o.external_source', $filters['channel']);
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
            ->selectRaw("DATE_FORMAT(COALESCE(o.issue_date, o.created_at), '%Y-%m') as month_key")
            ->selectRaw('COALESCE(o.order_number, CONCAT("INV-", o.id)) as invoice_number')
            ->selectRaw("COALESCE(NULLIF(c.business_name, ''), CONCAT_WS(' ', NULLIF(c.first_name, ''), NULLIF(c.last_name, '')), 'Unknown Customer') as customer_name")
            ->selectRaw('DATE(COALESCE(o.issue_date, o.created_at)) as sold_on')
            ->selectRaw('SUM(oi.quantity) as qty_sold')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at')
            ->whereBetween(DB::raw('DATE(COALESCE(o.issue_date, o.created_at))'), [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->when(($filters['channel'] ?? 'all') !== 'all', function ($query) use ($filters) {
                $query->where('o.external_source', $filters['channel']);
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