<?php

namespace App\Modules\Procurement\Support;

use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use Illuminate\Support\Facades\DB;

class ProcurementQuoteLifecycle
{
    public function startSupplierReview(Order $quote): ?ProcurementRequest
    {
        return $this->transition($quote, ProcurementWorkflowStage::SUPPLIER_REVIEW, 'supplier_review_started');
    }

    public function markQuoted(Order $quote): ?ProcurementRequest
    {
        return $this->transition($quote, ProcurementWorkflowStage::QUOTED, 'supplier_quote_ready');
    }

    private function transition(Order $quote, ProcurementWorkflowStage $targetStage, string $transition): ?ProcurementRequest
    {
        $quote->loadMissing('procurementQuoteRequest');

        $request = $quote->procurementQuoteRequest;

        if (! $request instanceof ProcurementRequest) {
            return null;
        }

        if ($request->current_stage?->isTerminal()) {
            return $request;
        }

        if ($request->current_stage?->isPostApproval()) {
            return $request;
        }

        return DB::transaction(function () use ($request, $targetStage, $transition): ProcurementRequest {
            $now = now();
            $meta = $request->meta ?? [];

            if ($targetStage === ProcurementWorkflowStage::SUPPLIER_REVIEW) {
                $request->forceFill([
                    'current_stage' => ProcurementWorkflowStage::SUPPLIER_REVIEW,
                    'supplier_reviewed_at' => $request->supplier_reviewed_at ?? $now,
                    'meta' => array_merge($meta, [
                        'last_transition' => $transition,
                    ]),
                ])->save();

                return $request->fresh();
            }

            $request->forceFill([
                'current_stage' => ProcurementWorkflowStage::QUOTED,
                'supplier_reviewed_at' => $request->supplier_reviewed_at ?? $now,
                'quoted_at' => $request->quoted_at ?? $now,
                'meta' => array_merge($meta, [
                    'last_transition' => $transition,
                ]),
            ])->save();

            return $request->fresh();
        });
    }
}
