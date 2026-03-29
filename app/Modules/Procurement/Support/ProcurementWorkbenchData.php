<?php

namespace App\Modules\Procurement\Support;

use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;

final class ProcurementWorkbenchData
{
    public function __construct(
        private readonly ?Account $currentAccount = null,
    ) {
    }

    public static function forAccount(?Account $currentAccount = null): self
    {
        return new self($currentAccount);
    }

    public function hasCurrentAccount(): bool
    {
        return $this->currentAccount instanceof Account;
    }

    public function currentAccountSummary(): array
    {
        if (! $this->hasCurrentAccount()) {
            return [
                'has_current_account' => false,
                'current_account' => null,
                'account' => null,
                'supplier_connections' => [
                    'total' => 0,
                    'approved' => 0,
                    'pending' => 0,
                    'rejected' => 0,
                    'inactive' => 0,
                ],
                'customer_count' => 0,
                'document_counts' => [
                    'quotes' => 0,
                    'orders' => 0,
                    'invoices' => 0,
                    'total' => 0,
                ],
                'document_total' => 0.0,
                'last_activity_at' => null,
                'last_activity_label' => null,
            ];
        }

        $connections = $this->accountConnectionsQuery()->get();
        $documents = $this->accountDocumentsQuery();
        $latestDocument = (clone $documents)->latest('created_at')->first();

        return [
            'has_current_account' => true,
            'current_account' => $this->accountPayload($this->currentAccount),
            'account' => $this->accountPayload($this->currentAccount),
            'supplier_connections' => [
                'total' => $connections->count(),
                'approved' => $this->countConnectionStatus($connections, AccountConnectionStatus::APPROVED),
                'pending' => $this->countConnectionStatus($connections, AccountConnectionStatus::PENDING),
                'rejected' => $this->countConnectionStatus($connections, AccountConnectionStatus::REJECTED),
                'inactive' => $this->countConnectionStatus($connections, AccountConnectionStatus::INACTIVE),
            ],
            'customer_count' => $this->accountCustomersQuery()->count(),
            'document_counts' => [
                'quotes' => $this->countDocuments(DocumentType::QUOTE),
                'orders' => $this->countDocuments(DocumentType::ORDER),
                'invoices' => $this->countDocuments(DocumentType::INVOICE),
                'total' => $documents->count(),
            ],
            'document_total' => round((float) $documents->sum('total'), 2),
            'last_activity_at' => $latestDocument?->created_at?->toDateTimeString(),
            'last_activity_label' => $latestDocument ? $this->signalLabelFor($latestDocument) : null,
        ];
    }

    public function supplierGroups(): array
    {
        if (! $this->hasCurrentAccount()) {
            return [];
        }

        return $this->accountConnectionsQuery()
            ->with('supplierAccount')
            ->orderByRaw(
                "CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END",
                [
                    AccountConnectionStatus::APPROVED->value,
                    AccountConnectionStatus::PENDING->value,
                    AccountConnectionStatus::INACTIVE->value,
                ]
            )
            ->orderBy('approved_at', 'desc')
            ->orderBy('id')
            ->get()
            ->map(function (AccountConnection $connection): array {
                $supplier = $connection->supplierAccount;

                return [
                    'connection_id' => $connection->id,
                    'supplier_id' => $supplier?->id,
                    'supplier_name' => $supplier?->name ?? 'Unknown supplier',
                    'supplier_slug' => $supplier?->slug,
                    'supplier_type' => $supplier?->account_type?->value,
                    'supplier_type_label' => $supplier?->account_type?->label(),
                    'connection_status' => $connection->status?->value ?? AccountConnectionStatus::PENDING->value,
                    'connection_status_label' => $connection->status?->label() ?? AccountConnectionStatus::PENDING->label(),
                    'approved_at' => $connection->approved_at?->toDateTimeString(),
                    'reports_subscription_enabled' => (bool) ($supplier?->reports_subscription_enabled ?? false),
                    'wholesale_enabled' => (bool) ($supplier?->wholesale_enabled ?? false),
                    'summary' => $this->supplierSummary($connection),
                ];
            })
            ->values()
            ->all();
    }

    public function recentProcurementSignals(int $limit = 5): array
    {
        if (! $this->hasCurrentAccount()) {
            return [];
        }

        return $this->accountDocumentsQuery()
            ->with('customer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Order $order): array {
                return [
                    'id' => $order->id,
                    'document_type' => $this->documentTypeValue($order),
                    'document_type_label' => $order->document_type?->label() ?? ucfirst($this->documentTypeValue($order)),
                    'document_number' => $this->documentNumber($order),
                    'status' => $this->documentStatusValue($order),
                    'status_label' => $this->documentStatusLabel($order),
                    'customer_id' => $order->customer_id,
                    'customer_name' => $order->customer?->name ?? 'Unknown customer',
                    'channel' => $order->channel,
                    'total' => round((float) $order->total, 2),
                    'occurred_at' => $order->created_at?->toDateTimeString(),
                    'signal_label' => $this->signalLabelFor($order),
                    'signal_summary' => $this->signalSummaryFor($order),
                ];
            })
            ->values()
            ->all();
    }

    public function toArray(int $limit = 5): array
    {
        return [
            'current_account_summary' => $this->currentAccountSummary(),
            'supplier_groups' => $this->supplierGroups(),
            'recent_procurement_signals' => $this->recentProcurementSignals($limit),
        ];
    }

    private function accountPayload(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'slug' => $account->slug,
            'account_type' => $account->account_type?->value,
            'account_type_label' => $account->account_type?->label(),
            'retail_enabled' => (bool) $account->retail_enabled,
            'wholesale_enabled' => (bool) $account->wholesale_enabled,
            'status' => $account->status?->value,
        ];
    }

    private function accountConnectionsQuery(): Builder
    {
        return AccountConnection::query()
            ->where('retailer_account_id', $this->currentAccount?->id);
    }

    private function accountCustomersQuery(): Builder
    {
        return Customer::query()
            ->where('account_id', $this->currentAccount?->id);
    }

    private function accountDocumentsQuery(): Builder
    {
        return Order::query()
            ->whereHas('customer', fn (Builder $query) => $query->where('account_id', $this->currentAccount?->id))
            ->whereIn('document_type', [
                DocumentType::QUOTE->value,
                DocumentType::ORDER->value,
                DocumentType::INVOICE->value,
            ]);
    }

    private function countConnectionStatus(iterable $connections, AccountConnectionStatus $status): int
    {
        $count = 0;

        foreach ($connections as $connection) {
            if ($connection instanceof AccountConnection && $connection->status === $status) {
                $count++;
            }
        }

        return $count;
    }

    private function countDocuments(DocumentType $documentType): int
    {
        return (int) Order::query()
            ->whereHas('customer', fn (Builder $query) => $query->where('account_id', $this->currentAccount?->id))
            ->where('document_type', $documentType->value)
            ->count();
    }

    private function documentTypeValue(Order $order): string
    {
        return $order->document_type instanceof DocumentType
            ? $order->document_type->value
            : (string) $order->document_type;
    }

    private function documentNumber(Order $order): string
    {
        return $order->document_type === DocumentType::QUOTE
            ? ($order->quote_number ?? $order->order_number ?? (string) $order->id)
            : ($order->order_number ?? $order->quote_number ?? (string) $order->id);
    }

    private function documentStatusValue(Order $order): ?string
    {
        if ($order->document_type === DocumentType::QUOTE) {
            return $order->quote_status instanceof QuoteStatus
                ? $order->quote_status->value
                : (is_string($order->quote_status) ? $order->quote_status : null);
        }

        return $order->order_status instanceof OrderStatus
            ? $order->order_status->value
            : (is_string($order->order_status) ? $order->order_status : null);
    }

    private function documentStatusLabel(Order $order): ?string
    {
        if ($order->document_type === DocumentType::QUOTE) {
            return $order->quote_status instanceof QuoteStatus
                ? $order->quote_status->label()
                : (is_string($order->quote_status) ? ucfirst(str_replace('_', ' ', $order->quote_status)) : null);
        }

        return $order->order_status instanceof OrderStatus
            ? $order->order_status->label()
            : (is_string($order->order_status) ? ucfirst(str_replace('_', ' ', $order->order_status)) : null);
    }

    private function signalLabelFor(Order $order): string
    {
        return trim(match ($order->document_type) {
            DocumentType::QUOTE => 'Quote '.$this->documentStatusLabel($order),
            DocumentType::INVOICE => 'Invoice '.$this->documentStatusLabel($order),
            DocumentType::ORDER => 'Order '.$this->documentStatusLabel($order),
            default => 'Procurement signal',
        });
    }

    private function signalSummaryFor(Order $order): string
    {
        return sprintf(
            '%s for %s in %s %0.2f',
            $this->documentNumber($order),
            $order->customer?->name ?? 'Unknown customer',
            $order->currency ?? 'AED',
            (float) $order->total,
        );
    }

    private function supplierSummary(AccountConnection $connection): string
    {
        return match ($connection->status) {
            AccountConnectionStatus::APPROVED => 'Approved supplier connection ready for procurement.',
            AccountConnectionStatus::PENDING => 'Supplier connection request awaiting approval.',
            AccountConnectionStatus::REJECTED => 'Supplier connection was rejected.',
            AccountConnectionStatus::INACTIVE => 'Supplier connection is inactive.',
            default => 'Supplier connection status is not available.',
        };
    }
}
