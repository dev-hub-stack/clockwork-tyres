<?php

namespace App\Modules\Orders\Services;

use App\Modules\Customers\Enums\PaymentTerm;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Events\QuoteConverted;
use App\Modules\Orders\Models\Order;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QuoteConversionService
 *
 * Handles conversion from quote to invoice inside the same orders row.
 */
class QuoteConversionService
{
    /**
     * Convert an approved quote to an invoice.
     *
     * @throws \Exception
     */
    public function convertQuoteToInvoice(Order $quote): Order
    {
        return DB::transaction(function () use ($quote) {
            if ($quote->document_type !== DocumentType::QUOTE) {
                throw new \Exception('Can only convert documents of type QUOTE. Current type: ' . $quote->document_type->value);
            }

            if ($quote->is_quote_converted) {
                throw new \Exception('This quote has already been converted to invoice');
            }

            if ($quote->items()->count() === 0) {
                throw new \Exception('Quote must have at least one item to convert');
            }

            Log::info('Converting quote to invoice', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'customer_id' => $quote->customer_id,
                'total' => $quote->total,
            ]);

            $issueDate = $quote->issue_date ?? now();
            $paymentTerm = $quote->payment_term ?? $quote->customer?->payment_term ?? PaymentTerm::default();

            $quote->update([
                'document_type' => DocumentType::INVOICE,
                'quote_status' => QuoteStatus::CONVERTED,
                'is_quote_converted' => true,
                'converted_to_invoice_id' => $quote->id,
                'order_status' => OrderStatus::PROCESSING,
                'order_number' => $this->generateInvoiceNumber(),
                'issue_date' => $issueDate,
                'payment_term' => $paymentTerm,
                'valid_until' => $paymentTerm->dueDateFrom($issueDate),
            ]);

            $quote->calculateTotals();

            Log::info('Quote converted to invoice successfully', [
                'order_id' => $quote->id,
                'order_number' => $quote->order_number,
                'document_type' => $quote->document_type->value,
            ]);

            ActivityLogService::log(
                'quote_converted_to_invoice',
                "Converted quote {$quote->quote_number} to invoice {$quote->order_number}",
                $quote,
            );

            event(new QuoteConverted($quote));

            $customerEmail = $quote->customer?->email ?? null;
            if ($customerEmail) {
                try {
                    app(\App\Support\TransactionalCustomerMail::class)->send(
                        $customerEmail,
                        new \App\Mail\QuoteApprovedMail($quote),
                        [
                            'trigger' => 'quote.converted_to_invoice',
                            'quote_id' => $quote->id,
                            'order_number' => $quote->order_number,
                        ]
                    );
                } catch (\Exception $e) {
                    Log::error('QuoteConversionService: failed to send QuoteApprovedMail', [
                        'order_id' => $quote->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $quote->fresh(['items', 'customer', 'warehouse']);
        });
    }

    /**
     * @return array{can_convert: bool, reason: string|null}
     */
    public function canConvert(Order $quote): array
    {
        if ($quote->document_type !== DocumentType::QUOTE) {
            return [
                'can_convert' => false,
                'reason' => 'Document is not a quote (current type: ' . $quote->document_type->value . ')',
            ];
        }

        if ($quote->quote_status !== QuoteStatus::APPROVED) {
            return [
                'can_convert' => false,
                'reason' => 'Quote must be approved first (current status: ' . $quote->quote_status->value . ')',
            ];
        }

        if ($quote->is_quote_converted) {
            return [
                'can_convert' => false,
                'reason' => 'Quote has already been converted',
            ];
        }

        if ($quote->items()->count() === 0) {
            return [
                'can_convert' => false,
                'reason' => 'Quote must have at least one item',
            ];
        }

        return [
            'can_convert' => true,
            'reason' => null,
        ];
    }

    /**
     * @param  array<int, int>  $quoteIds
     * @return array{converted: array<int, array{id: int, order_number: string|null}>, failed: array<int, array{id: int, error: string}>}
     */
    public function batchConvert(array $quoteIds): array
    {
        $converted = [];
        $failed = [];

        foreach ($quoteIds as $quoteId) {
            try {
                $quote = Order::findOrFail($quoteId);
                $invoice = $this->convertQuoteToInvoice($quote);

                $converted[] = [
                    'id' => $invoice->id,
                    'order_number' => $invoice->order_number,
                ];
            } catch (\Exception $e) {
                $failed[] = [
                    'id' => $quoteId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'converted' => $converted,
            'failed' => $failed,
        ];
    }

    /**
     * Reverse a conversion for error correction/testing.
     *
     * @throws \Exception
     */
    public function reverseConversion(Order $invoice): Order
    {
        return DB::transaction(function () use ($invoice) {
            if ($invoice->document_type !== DocumentType::INVOICE) {
                throw new \Exception('Can only reverse invoices');
            }

            if (! $invoice->is_quote_converted) {
                throw new \Exception('This invoice was not converted from a quote');
            }

            if ($invoice->order_status !== OrderStatus::PENDING) {
                throw new \Exception('Cannot reverse - order has already been processed');
            }

            Log::warning('Reversing quote conversion', [
                'order_id' => $invoice->id,
                'order_number' => $invoice->order_number,
            ]);

            $invoice->update([
                'document_type' => DocumentType::QUOTE,
                'quote_status' => QuoteStatus::APPROVED,
                'is_quote_converted' => false,
                'converted_to_invoice_id' => null,
                'order_status' => null,
            ]);

            return $invoice->fresh();
        });
    }

    /**
     * @return array{is_converted: bool, current_type: string, quote_status: string|null, order_status: string|null, sent_at: string|null, approved_at: string|null, created_at: string, updated_at: string}
     */
    public function getConversionHistory(Order $order): array
    {
        return [
            'is_converted' => $order->is_quote_converted,
            'current_type' => $order->document_type->value,
            'quote_status' => $order->quote_status?->value,
            'order_status' => $order->order_status?->value,
            'sent_at' => $order->sent_at?->toDateTimeString(),
            'approved_at' => $order->approved_at?->toDateTimeString(),
            'created_at' => $order->created_at->toDateTimeString(),
            'updated_at' => $order->updated_at->toDateTimeString(),
        ];
    }

    protected function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $prefix = "INV-{$year}-";

        $lastInvoice = Order::withTrashed()
            ->where('order_number', 'LIKE', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        $invoiceNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        while (Order::withTrashed()->where('order_number', $invoiceNumber)->exists()) {
            $newNumber++;
            $invoiceNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }

        return $invoiceNumber;
    }
}
