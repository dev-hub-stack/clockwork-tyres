<?php

namespace App\Modules\Accounts\Support;

use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Models\Account;
use App\Modules\Orders\Enums\DocumentType;
use App\Services\ReportService;
use Illuminate\Support\Facades\DB;

class BusinessAccountInsights
{
    /** @var array<int, array<string, int|float>> */
    protected array $accountCache = [];

    /** @var array<string, int|float>|null */
    protected ?array $platformCache = null;

    public function __construct(
        protected ReportService $reportService,
    ) {
    }

    /**
     * @return array<string, int|float>
     */
    public function for(Account $account): array
    {
        if (array_key_exists($account->id, $this->accountCache)) {
            return $this->accountCache[$account->id];
        }

        $channelExpression = $this->reportService->channelDimensionExpression('o', 'c');

        $retailBaseQuery = DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('c.account_id', $account->id)
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at');

        $wholesaleBaseQuery = DB::table('procurement_requests as pr')
            ->join('orders as o', 'o.id', '=', 'pr.invoice_order_id')
            ->where('pr.supplier_account_id', $account->id)
            ->whereNotNull('pr.invoice_order_id')
            ->whereNull('pr.deleted_at')
            ->whereNull('o.deleted_at');

        return $this->accountCache[$account->id] = [
            'connected_suppliers' => $account->connectionsAsRetailer()
                ->where('status', AccountConnectionStatus::APPROVED->value)
                ->count(),
            'connected_retailers' => $account->connectionsAsSupplier()
                ->where('status', AccountConnectionStatus::APPROVED->value)
                ->count(),
            'products_listed' => $account->tyreAccountOffers()->count(),
            'warehouses' => $account->warehouses()->count(),
            'users' => $account->users()->count(),
            'customers' => $account->customers()->count(),
            'retail_transaction_count' => (clone $retailBaseQuery)
                ->whereRaw($channelExpression . ' = ?', ['retail'])
                ->count(),
            'retail_transaction_value' => (float) ((clone $retailBaseQuery)
                ->whereRaw($channelExpression . ' = ?', ['retail'])
                ->sum('o.total') ?: 0),
            'wholesale_transaction_count' => (clone $wholesaleBaseQuery)->count(),
            'wholesale_transaction_value' => (float) ((clone $wholesaleBaseQuery)->sum('o.total') ?: 0),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function platform(): array
    {
        if ($this->platformCache !== null) {
            return $this->platformCache;
        }

        $channelExpression = $this->reportService->channelDimensionExpression('o', 'c');

        $retailBaseQuery = DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.document_type', DocumentType::INVOICE->value)
            ->whereNull('o.deleted_at');

        $wholesaleBaseQuery = DB::table('procurement_requests as pr')
            ->join('orders as o', 'o.id', '=', 'pr.invoice_order_id')
            ->whereNotNull('pr.invoice_order_id')
            ->whereNull('pr.deleted_at')
            ->whereNull('o.deleted_at');

        return $this->platformCache = [
            'products_listed' => DB::table('tyre_account_offers')->count(),
            'retail_transaction_count' => (clone $retailBaseQuery)
                ->whereRaw($channelExpression . ' = ?', ['retail'])
                ->count(),
            'retail_transaction_value' => (float) ((clone $retailBaseQuery)
                ->whereRaw($channelExpression . ' = ?', ['retail'])
                ->sum('o.total') ?: 0),
            'wholesale_transaction_count' => (clone $wholesaleBaseQuery)->count(),
            'wholesale_transaction_value' => (float) ((clone $wholesaleBaseQuery)->sum('o.total') ?: 0),
        ];
    }
}
