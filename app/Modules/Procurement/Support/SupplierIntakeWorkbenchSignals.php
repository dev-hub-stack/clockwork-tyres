<?php

namespace App\Modules\Procurement\Support;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use Illuminate\Support\Collection;

final class SupplierIntakeWorkbenchSignals
{
    /**
     * Build the supplier intake snapshot for a specific account.
     *
     * @return array{
     *     current_account_summary: array<string, mixed>,
     *     signal_cards: array<int, array<string, mixed>>,
     *     status_rail: array<int, array<string, mixed>>,
     *     incoming_requests: array<int, array<string, mixed>>,
     *     workflow_notes: array<int, array<string, string>>,
     *     action_checklist: array<int, string>
     * }
     */
    public static function forAccount(Account $account): array
    {
        $retailerConnections = self::approvedRetailerConnections($account);
        $retailerAccounts = $retailerConnections
            ->pluck('retailerAccount')
            ->filter(fn ($retailerAccount) => $retailerAccount instanceof Account)
            ->values();

        $procurementRequests = self::procurementRequestsForSupplier($account);

        if ($procurementRequests->isNotEmpty()) {
            return self::buildProcurementRequestSnapshot($account, $retailerAccounts->count(), $procurementRequests);
        }

        $scopeAccountIds = $retailerAccounts->pluck('id');

        if ($scopeAccountIds->isEmpty()) {
            $scopeAccountIds = collect([$account->id]);
        }

        $quoteRequests = self::quoteRequestsForAccounts($scopeAccountIds);
        $invoiceRequests = self::invoiceRequestsForAccounts($scopeAccountIds);

        return self::buildOrderSnapshot(
            $account,
            $retailerAccounts->count(),
            $quoteRequests,
            $invoiceRequests
        );
    }

    /**
     * @return Collection<int, AccountConnection>
     */
    private static function approvedRetailerConnections(Account $account): Collection
    {
        return AccountConnection::query()
            ->with('retailerAccount')
            ->approved()
            ->where('supplier_account_id', $account->id)
            ->orderBy('retailer_account_id')
            ->get();
    }

    /**
     * @return Collection<int, ProcurementRequest>
     */
    private static function procurementRequestsForSupplier(Account $account): Collection
    {
        return ProcurementRequest::query()
            ->with(['retailerAccount', 'customer', 'items', 'quoteOrder', 'invoiceOrder'])
            ->forSupplier($account)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return Collection<int, Order>
     */
    private static function quoteRequestsForAccounts(Collection $accountIds): Collection
    {
        return Order::query()
            ->with(['customer.account', 'items'])
            ->quotes()
            ->whereIn('quote_status', [
                QuoteStatus::SENT->value,
                QuoteStatus::APPROVED->value,
            ])
            ->whereHas('customer.account', function ($query) use ($accountIds): void {
                $query->whereIn('accounts.id', $accountIds->all());
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $accountIds
     * @return Collection<int, Order>
     */
    private static function invoiceRequestsForAccounts(Collection $accountIds): Collection
    {
        return Order::query()
            ->with(['customer.account', 'items'])
            ->invoices()
            ->whereHas('customer.account', function ($query) use ($accountIds): void {
                $query->whereIn('accounts.id', $accountIds->all());
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, ProcurementRequest>  $procurementRequests
     * @return array<string, mixed>
     */
    private static function buildProcurementRequestSnapshot(
        Account $account,
        int $retailerConnectionCount,
        Collection $procurementRequests
    ): array {
        $incomingRequests = self::buildIncomingRequestsFromProcurementRequests($procurementRequests);
        $statusRail = self::buildStatusRailFromProcurementRequests($procurementRequests);

        return [
            'current_account_summary' => [
                'name' => $account->name,
                'type' => $account->account_type?->label() ?? 'Account',
                'retailer_connections' => $retailerConnectionCount,
                'procurement_requests' => $procurementRequests->count(),
                'open_quotes' => $procurementRequests->filter(
                    static fn (ProcurementRequest $request): bool => in_array($request->current_stage, [
                        ProcurementWorkflowStage::SUBMITTED,
                        ProcurementWorkflowStage::SUPPLIER_REVIEW,
                        ProcurementWorkflowStage::QUOTED,
                    ], true)
                )->count(),
                'approved_quotes' => $procurementRequests->filter(
                    static fn (ProcurementRequest $request): bool => $request->current_stage === ProcurementWorkflowStage::APPROVED
                )->count(),
                'invoices_issued' => $procurementRequests->filter(
                    static fn (ProcurementRequest $request): bool => in_array($request->current_stage, [
                        ProcurementWorkflowStage::INVOICED,
                        ProcurementWorkflowStage::STOCK_RESERVED,
                        ProcurementWorkflowStage::STOCK_DEDUCTED,
                        ProcurementWorkflowStage::FULFILLED,
                    ], true)
                )->count(),
                'incoming_requests' => count($incomingRequests),
                'latest_signal' => $incomingRequests[0]['status'] ?? 'No live requests',
            ],
            'signal_cards' => [
                [
                    'label' => 'Quotes & Proformas inbox',
                    'value' => $procurementRequests->count(),
                    'note' => 'Persisted supplier intake requests from retailer procurement submissions.',
                ],
                [
                    'label' => 'Submitted requests',
                    'value' => $procurementRequests->filter(
                        static fn (ProcurementRequest $request): bool => $request->current_stage === ProcurementWorkflowStage::SUBMITTED
                    )->count(),
                    'note' => 'Retailer procurement requests waiting for supplier review.',
                ],
                [
                    'label' => 'Approved quotes',
                    'value' => $procurementRequests->filter(
                        static fn (ProcurementRequest $request): bool => $request->current_stage === ProcurementWorkflowStage::APPROVED
                    )->count(),
                    'note' => 'Approved requests are ready for invoice conversion.',
                ],
                [
                    'label' => 'Invoices issued',
                    'value' => $procurementRequests->filter(
                        static fn (ProcurementRequest $request): bool => in_array($request->current_stage, [
                            ProcurementWorkflowStage::INVOICED,
                            ProcurementWorkflowStage::STOCK_RESERVED,
                            ProcurementWorkflowStage::STOCK_DEDUCTED,
                            ProcurementWorkflowStage::FULFILLED,
                        ], true)
                    )->count(),
                    'note' => 'Requests already moved into the invoice and stock path.',
                ],
                [
                    'label' => 'Retailer connections',
                    'value' => $retailerConnectionCount,
                    'note' => 'Approved supplier links feeding this intake view.',
                ],
            ],
            'status_rail' => $statusRail,
            'incoming_requests' => $incomingRequests,
            'workflow_notes' => self::buildWorkflowNotes(),
            'action_checklist' => self::buildActionChecklist(),
        ];
    }

    /**
     * @param  Collection<int, Order>  $quoteRequests
     * @param  Collection<int, Order>  $invoiceRequests
     * @return array<string, mixed>
     */
    private static function buildOrderSnapshot(
        Account $account,
        int $retailerConnectionCount,
        Collection $quoteRequests,
        Collection $invoiceRequests
    ): array {
        $incomingRequests = self::buildIncomingRequests($quoteRequests, $invoiceRequests);
        $statusRail = self::buildStatusRail($quoteRequests, $invoiceRequests);

        return [
            'current_account_summary' => [
                'name' => $account->name,
                'type' => $account->account_type?->label() ?? 'Account',
                'retailer_connections' => $retailerConnectionCount,
                'procurement_requests' => 0,
                'open_quotes' => $quoteRequests->filter(
                    static fn (Order $order): bool => $order->quote_status === QuoteStatus::SENT
                )->count(),
                'approved_quotes' => $quoteRequests->filter(
                    static fn (Order $order): bool => $order->quote_status === QuoteStatus::APPROVED
                )->count(),
                'invoices_issued' => $invoiceRequests->count(),
                'incoming_requests' => count($incomingRequests),
                'latest_signal' => $incomingRequests[0]['status'] ?? 'No live requests',
            ],
            'signal_cards' => [
                [
                    'label' => 'Quotes & Proformas inbox',
                    'value' => $quoteRequests->count(),
                    'note' => 'Real quote records from connected retailer accounts.',
                ],
                [
                    'label' => 'Approved quotes',
                    'value' => $quoteRequests->filter(
                        static fn (Order $order): bool => $order->quote_status === QuoteStatus::APPROVED
                    )->count(),
                    'note' => 'Approved requests are ready for invoice conversion.',
                ],
                [
                    'label' => 'Invoices issued',
                    'value' => $invoiceRequests->count(),
                    'note' => 'Converted invoice records from the same account network.',
                ],
                [
                    'label' => 'Retailer connections',
                    'value' => $retailerConnectionCount,
                    'note' => 'Approved supplier links feeding this intake view.',
                ],
            ],
            'status_rail' => $statusRail,
            'incoming_requests' => $incomingRequests,
            'workflow_notes' => self::buildWorkflowNotes(),
            'action_checklist' => self::buildActionChecklist(),
        ];
    }

    /**
     * @param  Collection<int, ProcurementRequest>  $procurementRequests
     * @return array<int, array<string, mixed>>
     */
    private static function buildIncomingRequestsFromProcurementRequests(Collection $procurementRequests): array
    {
        return $procurementRequests
            ->map(function (ProcurementRequest $request): array {
                $primaryItem = $request->items->first();

                return [
                    'record_id' => $request->id,
                    'issued_at' => $request->submitted_at?->toDateString() ?? $request->created_at?->toDateString(),
                    'request_number' => $request->request_number ?? ('PRQ-'.$request->id),
                    'document_type' => 'Procurement Request',
                    'retailer' => $request->customer?->business_name ?? $request->customer?->name ?? $request->retailerAccount?->name ?? 'Unknown retailer',
                    'account' => $request->retailerAccount?->name ?? 'Unlinked account',
                    'sku' => $primaryItem?->sku ?? '—',
                    'size' => $primaryItem?->size ?? '—',
                    'quantity' => $request->quantity_total,
                    'reference' => $request->invoiceOrder?->order_number
                        ?? $request->quoteOrder?->quote_number
                        ?? $request->request_number
                        ?? ('PRQ-'.$request->id),
                    'status' => $request->current_stage?->label() ?? 'Submitted',
                    'stage' => $request->current_stage?->value ?? ProcurementWorkflowStage::SUBMITTED->value,
                    'note' => self::describeProcurementRequestNote($request),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Order>  $quoteRequests
     * @param  Collection<int, Order>  $invoiceRequests
     * @return array<int, array<string, mixed>>
     */
    private static function buildIncomingRequests(Collection $quoteRequests, Collection $invoiceRequests): array
    {
        $rows = $quoteRequests
            ->concat($invoiceRequests)
            ->map(function (Order $order): array {
                $customer = $order->customer;
                $account = $customer?->account;
                $primaryItem = $order->items->first();
                $documentType = $order->document_type;

                return [
                    'record_id' => $order->id,
                    'issued_at' => $order->issue_date?->toDateString() ?? $order->created_at?->toDateString(),
                    'request_number' => $order->isQuote()
                        ? ($order->quote_number ?: $order->display_number)
                        : ($order->order_number ?: $order->display_number),
                    'document_type' => $documentType?->label() ?? 'Document',
                    'retailer' => $customer?->business_name ?? $customer?->name ?? 'Unknown customer',
                    'account' => $account?->name ?? 'Unlinked account',
                    'sku' => $primaryItem?->display_sku ?? $primaryItem?->sku ?? '—',
                    'size' => self::extractLegacySize($primaryItem),
                    'quantity' => (int) $order->items->sum('quantity'),
                    'reference' => $order->external_order_id
                        ?? $order->quote_number
                        ?? $order->order_number
                        ?? '—',
                    'status' => self::describeOrderStatus($order),
                    'stage' => self::describeOrderStage($order),
                    'note' => self::describeOrderNote($order),
                ];
            })
            ->sort(function (array $left, array $right): int {
                $leftIssuedAt = $left['issued_at'] ?? '';
                $rightIssuedAt = $right['issued_at'] ?? '';

                if ($leftIssuedAt !== $rightIssuedAt) {
                    return strcmp((string) $rightIssuedAt, (string) $leftIssuedAt);
                }

                return (int) ($right['record_id'] ?? 0) <=> (int) ($left['record_id'] ?? 0);
            })
            ->values();

        return $rows->all();
    }

    /**
     * @param  Collection<int, ProcurementRequest>  $procurementRequests
     * @return array<int, array<string, mixed>>
     */
    private static function buildStatusRailFromProcurementRequests(Collection $procurementRequests): array
    {
        $descriptions = self::statusDescriptions();
        $stageMap = array_values(array_filter(
            ProcurementWorkflow::stages(),
            static fn (array $stage): bool => $stage['value'] !== ProcurementWorkflowStage::DRAFT->value
        ));

        return array_map(
            static function (array $stage) use ($procurementRequests, $descriptions): array {
                $value = $stage['value'];
                $count = $procurementRequests->filter(
                    static fn (ProcurementRequest $request): bool => $request->current_stage?->value === $value
                )->count();

                return [
                    'key' => $value,
                    'label' => $stage['label'],
                    'description' => $descriptions[$value] ?? 'Supplier intake stage.',
                    'state' => $count > 0 ? 'active' : 'pending',
                    'terminal' => $stage['terminal'],
                    'count' => $count,
                ];
            },
            $stageMap
        );
    }

    /**
     * @param  Collection<int, Order>  $quoteRequests
     * @param  Collection<int, Order>  $invoiceRequests
     * @return array<int, array<string, mixed>>
     */
    private static function buildStatusRail(Collection $quoteRequests, Collection $invoiceRequests): array
    {
        $counts = [
            'submitted' => $quoteRequests->filter(
                static fn (Order $order): bool => $order->quote_status === QuoteStatus::SENT
            )->count(),
            'supplier_review' => $quoteRequests->filter(
                static fn (Order $order): bool => $order->quote_status === QuoteStatus::SENT
            )->count(),
            'quoted' => $quoteRequests->filter(
                static fn (Order $order): bool => in_array($order->quote_status, [
                    QuoteStatus::SENT,
                    QuoteStatus::APPROVED,
                ], true)
            )->count(),
            'approved' => $quoteRequests->filter(
                static fn (Order $order): bool => $order->quote_status === QuoteStatus::APPROVED
            )->count(),
            'invoiced' => $invoiceRequests->count(),
            'stock_reserved' => $invoiceRequests->filter(
                static fn (Order $order): bool => in_array($order->order_status, [
                    OrderStatus::PENDING,
                    OrderStatus::PROCESSING,
                ], true)
            )->count(),
            'stock_deducted' => $invoiceRequests->filter(
                static fn (Order $order): bool => in_array($order->order_status, [
                    OrderStatus::SHIPPED,
                    OrderStatus::DELIVERED,
                    OrderStatus::COMPLETED,
                ], true)
            )->count(),
            'fulfilled' => $invoiceRequests->filter(
                static fn (Order $order): bool => $order->order_status === OrderStatus::COMPLETED
            )->count(),
            'cancelled' => $quoteRequests->filter(
                static fn (Order $order): bool => $order->quote_status === QuoteStatus::REJECTED
            )->count()
                + $invoiceRequests->filter(
                    static fn (Order $order): bool => $order->order_status === OrderStatus::CANCELLED
                )->count(),
        ];

        $descriptions = self::statusDescriptions();
        $stageMap = array_values(array_filter(
            ProcurementWorkflow::stages(),
            static fn (array $stage): bool => $stage['value'] !== ProcurementWorkflowStage::DRAFT->value
        ));

        return array_map(
            static function (array $stage) use ($counts, $descriptions): array {
                $value = $stage['value'];
                $count = $counts[$value] ?? 0;

                return [
                    'key' => $value,
                    'label' => $stage['label'],
                    'description' => $descriptions[$value] ?? 'Supplier intake stage.',
                    'state' => $count > 0 ? 'active' : 'pending',
                    'terminal' => $stage['terminal'],
                    'count' => $count,
                ];
            },
            $stageMap
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function buildWorkflowNotes(): array
    {
        return [
            [
                'title' => 'Quotes & Proformas inbox',
                'copy' => 'George described supplier intake as an inbox under Quotes & Proformas. Live quote records from connected retailer accounts should surface here first.',
            ],
            [
                'title' => 'Invoice conversion',
                'copy' => 'Once the procurement quote is approved, the supplier-side flow converts it to an invoice and keeps the record inside the same account-aware workspace.',
            ],
            [
                'title' => 'Stock handling',
                'copy' => 'Stock deduction stays aligned with the current CRM method, so invoice fulfilment and inventory movement continue to follow the existing backend rules.',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function buildActionChecklist(): array
    {
        return [
            'Review the incoming procurement request in Quotes & Proformas.',
            'Confirm the linked retailer account and the request scope.',
            'Respond with the supplier quote or proforma.',
            'Convert the approved quote to invoice.',
            'Reserve or deduct stock using the current CRM method.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function statusDescriptions(): array
    {
        return [
            'submitted' => 'Incoming procurement request arrives in the supplier inbox.',
            'supplier_review' => 'Supplier reviews the request under Quotes & Proformas.',
            'quoted' => 'The supplier response is captured as a quote or proforma.',
            'approved' => 'Retailer approval moves the quote toward invoice conversion.',
            'invoiced' => 'Approved quotes become invoices in the supplier workflow.',
            'stock_reserved' => 'Stock is reserved before the CRM deducts inventory.',
            'stock_deducted' => 'Stock deduction follows the current CRM method.',
            'fulfilled' => 'Fulfilment is complete and ready for reporting.',
            'cancelled' => 'Cancelled requests release inventory back to stock.',
        ];
    }

    private static function describeOrderStatus(Order $order): string
    {
        if ($order->isQuote()) {
            return $order->quote_status?->label() ?? 'Quote';
        }

        return $order->order_status?->label() ?? 'Invoice';
    }

    private static function describeOrderStage(Order $order): string
    {
        if ($order->isQuote()) {
            return match ($order->quote_status) {
                QuoteStatus::SENT => ProcurementWorkflowStage::SUBMITTED->value,
                QuoteStatus::APPROVED => ProcurementWorkflowStage::APPROVED->value,
                QuoteStatus::CONVERTED => ProcurementWorkflowStage::INVOICED->value,
                default => ProcurementWorkflowStage::QUOTED->value,
            };
        }

        return match ($order->order_status) {
            OrderStatus::PENDING, OrderStatus::PROCESSING => ProcurementWorkflowStage::STOCK_RESERVED->value,
            OrderStatus::SHIPPED, OrderStatus::DELIVERED => ProcurementWorkflowStage::STOCK_DEDUCTED->value,
            OrderStatus::COMPLETED => ProcurementWorkflowStage::FULFILLED->value,
            OrderStatus::CANCELLED => ProcurementWorkflowStage::CANCELLED->value,
            default => ProcurementWorkflowStage::INVOICED->value,
        };
    }

    private static function describeOrderNote(Order $order): string
    {
        if ($order->isQuote()) {
            return match ($order->quote_status) {
                QuoteStatus::SENT => 'Waiting in Quotes & Proformas for supplier review.',
                QuoteStatus::APPROVED => 'Approved quote is ready to convert to invoice.',
                QuoteStatus::CONVERTED => 'Converted quote now follows the invoice workflow.',
                QuoteStatus::REJECTED => 'Rejected quote stays out of the active inbox.',
                default => 'Quote record in the supplier intake queue.',
            };
        }

        return match ($order->order_status) {
            OrderStatus::PENDING, OrderStatus::PROCESSING => 'Invoice is in the stock reservation path.',
            OrderStatus::SHIPPED, OrderStatus::DELIVERED, OrderStatus::COMPLETED => 'Stock deduction follows the current CRM method.',
            OrderStatus::CANCELLED => 'Cancelled invoice releases inventory back to stock.',
            default => 'Invoice created from the quote approval path.',
        };
    }

    private static function describeProcurementRequestNote(ProcurementRequest $request): string
    {
        return match ($request->current_stage) {
            ProcurementWorkflowStage::SUBMITTED => 'Retailer grouped checkout submitted and waiting for supplier review.',
            ProcurementWorkflowStage::SUPPLIER_REVIEW => 'Supplier is reviewing availability and fulfilment fit.',
            ProcurementWorkflowStage::QUOTED => 'Supplier quote or proforma is ready for retailer review.',
            ProcurementWorkflowStage::APPROVED => 'Approved quote is ready to convert to invoice.',
            ProcurementWorkflowStage::INVOICED => 'Invoice has been created from the approved request.',
            ProcurementWorkflowStage::STOCK_RESERVED => 'Inventory has been reserved against the selected warehouse.',
            ProcurementWorkflowStage::STOCK_DEDUCTED => 'Stock deduction follows the current CRM method.',
            ProcurementWorkflowStage::FULFILLED => 'Procurement fulfilment is complete.',
            ProcurementWorkflowStage::CANCELLED => 'Cancelled request releases inventory back to stock.',
            default => 'Procurement request is in the supplier intake queue.',
        };
    }

    private static function extractLegacySize(mixed $primaryItem): string
    {
        if (! $primaryItem) {
            return '—';
        }

        $size = data_get($primaryItem, 'item_attributes.size')
            ?? data_get($primaryItem, 'variant_snapshot.size')
            ?? data_get($primaryItem, 'product_snapshot.size');

        return is_string($size) && trim($size) !== '' ? $size : '—';
    }
}
