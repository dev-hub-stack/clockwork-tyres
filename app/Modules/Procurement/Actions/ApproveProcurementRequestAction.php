<?php

namespace App\Modules\Procurement\Actions;

use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Orders\Services\QuoteConversionService;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveProcurementRequestAction
{
    public function __construct(
        protected OrderService $orderService,
        protected QuoteConversionService $quoteConversionService,
    ) {}

    public function execute(ProcurementRequest $request): ProcurementRequest
    {
        return DB::transaction(function () use ($request): ProcurementRequest {
            $request->loadMissing(['quoteOrder.items', 'invoiceOrder']);

            $linkedOrder = $request->quoteOrder ?? $request->invoiceOrder;

            if (! $linkedOrder instanceof Order) {
                throw new InvalidArgumentException('A linked supplier quote is required before the procurement request can be approved.');
            }

            $invoice = $this->approveAndConvert($linkedOrder);
            $now = now();

            $request->forceFill([
                'quote_order_id' => $request->quote_order_id ?? $invoice->id,
                'invoice_order_id' => $invoice->id,
                'current_stage' => $this->stageForInvoice($invoice),
                'supplier_reviewed_at' => $request->supplier_reviewed_at ?? $now,
                'quoted_at' => $request->quoted_at ?? $linkedOrder->sent_at ?? $now,
                'approved_at' => $request->approved_at ?? $invoice->approved_at ?? $now,
                'invoiced_at' => $request->invoiced_at ?? $now,
                'meta' => array_merge($request->meta ?? [], [
                    'linked_quote_id' => $request->quote_order_id ?? $invoice->id,
                    'linked_invoice_id' => $invoice->id,
                    'linked_quote_number' => $invoice->quote_number,
                    'linked_invoice_number' => $invoice->order_number,
                    'last_transition' => 'approved_to_invoice',
                ]),
            ])->save();

            return $request->fresh([
                'items',
                'quoteOrder.items',
                'invoiceOrder.items',
                'supplierAccount',
                'retailerAccount',
                'customer',
            ]);
        });
    }

    private function approveAndConvert(Order $order): Order
    {
        if ($order->document_type === DocumentType::INVOICE) {
            return $order->fresh(['items', 'customer']);
        }

        if ($order->document_type !== DocumentType::QUOTE) {
            throw new InvalidArgumentException('Only linked quote orders can be approved for procurement invoice conversion.');
        }

        if ($order->quote_status !== QuoteStatus::APPROVED) {
            $this->orderService->approveQuote($order);
            $order->refresh();
        }

        return $this->quoteConversionService->convertQuoteToInvoice($order);
    }

    private function stageForInvoice(Order $invoice): ProcurementWorkflowStage
    {
        return match ($invoice->order_status) {
            OrderStatus::PENDING,
            OrderStatus::PROCESSING => ProcurementWorkflowStage::STOCK_RESERVED,
            OrderStatus::SHIPPED,
            OrderStatus::DELIVERED => ProcurementWorkflowStage::STOCK_DEDUCTED,
            OrderStatus::COMPLETED => ProcurementWorkflowStage::FULFILLED,
            OrderStatus::CANCELLED => ProcurementWorkflowStage::CANCELLED,
            default => ProcurementWorkflowStage::INVOICED,
        };
    }
}
