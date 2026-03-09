<?php

namespace App\Modules\Orders\Services;

use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Events\QuoteConverted;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QuoteConversionService
 * 
 * CRITICAL SERVICE: Handles the conversion of quotes to invoices
 * 
 * This is the CORE of the unified orders table approach:
 * - Quote (document_type='quote') → Invoice (document_type='invoice')
 * - Conversion happens IN THE SAME RECORD (just changes document_type field)
 * - All line items and snapshots are preserved
 * - Quote status is marked as 'converted'
 */
class QuoteConversionService
{
    /**
     * Convert an approved quote to an invoice
     * 
     * CRITICAL: This is THE key method that demonstrates unified table approach
     * We DON'T create a new record - we UPDATE the existing one!
     * 
     * @param Order $quote
     * @return Order The same order, now as an invoice
     * @throws \Exception If quote cannot be converted
     */
    public function convertQuoteToInvoice(Order $quote): Order
    {
        return DB::transaction(function () use ($quote) {
            
            // Validation: Must be a quote
            if ($quote->document_type !== DocumentType::QUOTE) {
                throw new \Exception('Can only convert documents of type QUOTE. Current type: ' . $quote->document_type->value);
            }
            
            // Validation: Not already converted
            if ($quote->is_quote_converted) {
                throw new \Exception('This quote has already been converted to invoice');
            }
            
            // Validation: Must have items
            if ($quote->items()->count() === 0) {
                throw new \Exception('Quote must have at least one item to convert');
            }
            
            Log::info('Converting quote to invoice', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'customer_id' => $quote->customer_id,
                'total' => $quote->total,
            ]);
            
            // THE CRITICAL CONVERSION: Just change the document_type!
            $quote->update([
                'document_type' => DocumentType::INVOICE,  // ← THE KEY CHANGE!
                'quote_status' => QuoteStatus::CONVERTED,
                'is_quote_converted' => true,
                'converted_to_invoice_id' => $quote->id,  // Self-reference
                'order_status' => OrderStatus::PROCESSING,   // Initialize order workflow and trigger stock reduction
                'order_number' => $this->generateInvoiceNumber(), // Generate new invoice number
                'issue_date' => $quote->issue_date ?? now(),
            ]);
            
            // Recalculate totals to ensure accuracy
            $quote->calculateTotals();
            
            Log::info('Quote converted to invoice successfully', [
                'order_id' => $quote->id,
                'order_number' => $quote->order_number,
                'document_type' => $quote->document_type->value,
            ]);
            
            // Fire event for notifications, emails, etc.
            event(new QuoteConverted($quote));

            // Send "Quote Approved" confirmation email to customer
            $customerEmail = $quote->customer?->email ?? null;
            if ($customerEmail) {
                try {
                    \Illuminate\Support\Facades\Mail::to($customerEmail)
                        ->send(new \App\Mail\QuoteApprovedMail($quote));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('QuoteConversionService: failed to send QuoteApprovedMail', [
                        'order_id' => $quote->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
            
            // Return the same model, now as an invoice
            return $quote->fresh(['items', 'customer', 'warehouse']);
        });
    }

    /**
     * Check if a quote can be converted
     * 
     * @param Order $quote
     * @return array ['can_convert' => bool, 'reason' => string|null]
     */
    public function canConvert(Order $quote): array
    {
        // Check if it's a quote
        if ($quote->document_type !== DocumentType::QUOTE) {
            return [
                'can_convert' => false,
                'reason' => 'Document is not a quote (current type: ' . $quote->document_type->value . ')',
            ];
        }
        
        // Check if approved
        if ($quote->quote_status !== QuoteStatus::APPROVED) {
            return [
                'can_convert' => false,
                'reason' => 'Quote must be approved first (current status: ' . $quote->quote_status->value . ')',
            ];
        }
        
        // Check if already converted
        if ($quote->is_quote_converted) {
            return [
                'can_convert' => false,
                'reason' => 'Quote has already been converted',
            ];
        }
        
        // Check if has items
        if ($quote->items()->count() === 0) {
            return [
                'can_convert' => false,
                'reason' => 'Quote must have at least one item',
            ];
        }
        
        // All checks passed
        return [
            'can_convert' => true,
            'reason' => null,
        ];
    }

    /**
     * Batch convert multiple quotes
     * 
     * @param array $quoteIds
     * @return array ['converted' => array, 'failed' => array]
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
     * Reverse a conversion (convert invoice back to quote)
     * WARNING: Use with caution - only for errors/testing
     * 
     * @param Order $invoice
     * @return Order
     */
    public function reverseConversion(Order $invoice): Order
    {
        return DB::transaction(function () use ($invoice) {
            
            // Validation
            if ($invoice->document_type !== DocumentType::INVOICE) {
                throw new \Exception('Can only reverse invoices');
            }
            
            if (!$invoice->is_quote_converted) {
                throw new \Exception('This invoice was not converted from a quote');
            }
            
            // Check if order has been processed (too late to reverse)
            if ($invoice->order_status !== OrderStatus::PENDING) {
                throw new \Exception('Cannot reverse - order has already been processed');
            }
            
            Log::warning('Reversing quote conversion', [
                'order_id' => $invoice->id,
                'order_number' => $invoice->order_number,
            ]);
            
            // Reverse the conversion
            $invoice->update([
                'document_type' => DocumentType::QUOTE,
                'quote_status' => QuoteStatus::APPROVED,  // Return to approved state
                'is_quote_converted' => false,
                'converted_to_invoice_id' => null,
                'order_status' => null,
            ]);
            
            return $invoice->fresh();
        });
    }

    /**
     * Get conversion history for a quote/invoice
     * 
     * @param Order $order
     * @return array
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

    /**
     * Generate a unique invoice number
     * Format: INV-YYYY-XXXX
     */
    protected function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $prefix = "INV-{$year}-";
        
        // Get the highest invoice number for this year (including soft deleted)
        $lastInvoice = Order::withTrashed()
            ->where('order_number', 'LIKE', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            // Extract the number and increment
            $lastNumber = (int) substr($lastInvoice->order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        $invoiceNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        
        // Extra safety: Check if this number exists
        while (Order::withTrashed()->where('order_number', $invoiceNumber)->exists()) {
            $newNumber++;
            $invoiceNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }
        
        return $invoiceNumber;
    }
}
