<?php

namespace App\Modules\Procurement\Support;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use Illuminate\Support\Facades\DB;

class ProcurementInvoiceLifecycle
{
    public function sync(Order $invoice): ?ProcurementRequest
    {
        if (! $invoice->isInvoice()) {
            return null;
        }

        return DB::transaction(function () use ($invoice): ?ProcurementRequest {
            $invoice->loadMissing('procurementInvoiceRequest');

            if (! $invoice->procurementInvoiceRequest) {
                return null;
            }

            return $this->syncRequest($invoice->procurementInvoiceRequest, $invoice);
        });
    }

    public function syncRequest(ProcurementRequest $request, Order $invoice): ProcurementRequest
    {
        $stage = $this->stageForInvoice($invoice);
        $now = now();

        $attributes = [
            'invoice_order_id' => $request->invoice_order_id ?? $invoice->id,
            'current_stage' => $stage,
            'invoiced_at' => $request->invoiced_at ?? $invoice->approved_at ?? $now,
            'approved_at' => $stage->isPostApproval()
                ? ($request->approved_at ?? $invoice->approved_at ?? $now)
                : $request->approved_at,
            'meta' => array_merge($request->meta ?? [], [
                'linked_invoice_id' => $invoice->id,
                'linked_invoice_number' => $invoice->order_number,
                'linked_invoice_status' => $invoice->order_status?->value,
                'last_transition' => 'invoice_status_sync',
            ]),
        ];

        if ($stage === ProcurementWorkflowStage::FULFILLED) {
            $attributes['fulfilled_at'] = $request->fulfilled_at ?? $invoice->delivered_at ?? $now;
        }

        if ($stage === ProcurementWorkflowStage::CANCELLED) {
            $attributes['cancelled_at'] = $request->cancelled_at ?? $now;
        }

        $request->forceFill($attributes)->save();

        return $request->fresh([
            'items',
            'quoteOrder.items',
            'invoiceOrder.items',
            'supplierAccount',
            'retailerAccount',
            'customer',
        ]);
    }

    public function stageForInvoice(Order $invoice): ProcurementWorkflowStage
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
