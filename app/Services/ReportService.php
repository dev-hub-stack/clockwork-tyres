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