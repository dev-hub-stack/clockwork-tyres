<?php

namespace App\Modules\Procurement\Support;

use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Models\ProcurementRequest;
use Illuminate\Support\Facades\DB;

class ProcurementQuoteLifecycle
{
    public function __construct(
        protected OrderService $orderService,
    ) {}

    public function startSupplierReview(Order $quote): ?ProcurementRequest
    {
        return $this->transition($quote, ProcurementWorkflowStage::SUPPLIER_REVIEW, 'supplier_review_started');
    }

    public function markQuoted(Order $quote): ?ProcurementRequest
    {
        return $this->transition($quote, ProcurementWorkflowStage::QUOTED, 'supplier_quote_ready');
    }

    public function reject(Order $quote, string $reason): ?ProcurementRequest
    {
        $quote->loadMissing('procurementQuoteRequest');

        $request = $quote->procurementQuoteRequest;

        if (! $request instanceof ProcurementRequest) {
            return null;
        }

        if ($request->current_stage?->isTerminal() || $request->current_stage?->isPostApproval()) {
            return $request;
        }

        return DB::transaction(function () use ($quote, $request, $reason): ProcurementRequest {
            $this->orderService->rejectQuote($quote, $reason);

            $request->forceFill([
                'current_stage' => ProcurementWorkflowStage::CANCELLED,
                'cancelled_at' => $request->cancelled_at ?? now(),
                'notes' => $this->appendNote($request->notes, 'Supplier rejection', $reason),
                'meta' => array_merge($request->meta ?? [], [
                    'last_transition' => 'supplier_rejected',
                    'rejection_reason' => $reason,
                    'rejected_quote_status' => $quote->fresh()->quote_status?->value,
                ]),
            ])->save();

            return $request->fresh();
        });
    }

    public function requestRevision(Order $quote, string $note): ?ProcurementRequest
    {
        $quote->loadMissing('procurementQuoteRequest');

        $request = $quote->procurementQuoteRequest;

        if (! $request instanceof ProcurementRequest) {
            return null;
        }

        if ($request->current_stage?->isTerminal() || $request->current_stage?->isPostApproval()) {
            return $request;
        }

        return DB::transaction(function () use ($quote, $request, $note): ProcurementRequest {
            $quote->update([
                'quote_status' => QuoteStatus::DRAFT,
                'sent_at' => null,
                'approved_at' => null,
                'order_notes' => $this->appendNote($quote->order_notes, 'Revision requested', $note),
            ]);

            $request->forceFill([
                'current_stage' => ProcurementWorkflowStage::SUPPLIER_REVIEW,
                'supplier_reviewed_at' => $request->supplier_reviewed_at ?? now(),
                'quoted_at' => null,
                'notes' => $this->appendNote($request->notes, 'Revision requested', $note),
                'meta' => array_merge($request->meta ?? [], [
                    'last_transition' => 'supplier_revision_requested',
                    'revision_request_note' => $note,
                    'revision_requested_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return $request->fresh();
        });
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

    private function appendNote(?string $existing, string $prefix, string $message): string
    {
        $timestamped = '['.now()->toDateTimeString()."] {$prefix}: {$message}";

        return trim(($existing ? $existing."\n\n" : '').$timestamped);
    }
}
