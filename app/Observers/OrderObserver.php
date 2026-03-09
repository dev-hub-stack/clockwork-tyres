<?php

namespace App\Observers;

use App\Mail\OrderCompletedMail;
use App\Mail\OrderProcessingMail;
use App\Mail\OrderShippedMail;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderFulfillmentService;
use App\Modules\Settings\Models\CompanyBranding;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderObserver
{
    /**
     * Handle the Order "creating" event.
     * Generate order/quote numbers before saving
     */
    public function creating(Order $order): void
    {
        // Generate quote number if it's a quote
        if ($order->document_type === DocumentType::QUOTE && empty($order->quote_number)) {
            $order->quote_number = $this->generateQuoteNumber();
        }
        
        // Always generate order number (used for all document types)
        if (empty($order->order_number)) {
            $order->order_number = $this->generateOrderNumber();
        }
    }

    /**
     * Handle the Order "updated" event.
     * Handle status changes and inventory allocation
     */
    public function updated(Order $order): void
    {
        // Check if order status changed to processing
        if ($order->isDirty('order_status')) {
            $newStatus = $order->order_status;
            
            // Auto-allocate inventory when status changes to processing
            if ($newStatus === OrderStatus::PROCESSING) {
                $this->autoAllocateInventory($order);
            }
            
            // Release inventory when cancelled
            if ($newStatus === OrderStatus::CANCELLED) {
                $this->autoReleaseInventory($order);
            }

            // ── Email notifications ──────────────────────────────────────────
            $customerEmail = $order->customer?->email ?? null;
            if ($customerEmail) {
                try {
                    if ($newStatus === OrderStatus::PROCESSING
                        && $order->document_type === DocumentType::INVOICE
                        && !$order->isDirty('document_type')) {
                        // Only send OrderProcessingMail for direct status changes (not quote→invoice
                        // conversions, which send QuoteApprovedMail from QuoteConversionService)
                        Mail::to($customerEmail)->send(new OrderProcessingMail($order));
                    } elseif ($newStatus === OrderStatus::SHIPPED) {
                        Mail::to($customerEmail)->send(new OrderShippedMail($order));
                    } elseif (in_array($newStatus, [OrderStatus::DELIVERED, OrderStatus::COMPLETED])) {
                        Mail::to($customerEmail)->send(new OrderCompletedMail($order));
                    }
                } catch (\Exception $e) {
                    Log::error('OrderObserver: failed to send status change email', [
                        'order_id'  => $order->id,
                        'status'    => $newStatus->value,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }
            
            Log::info("Order status changed", [
                'order_id' => $order->id,
                'old_status' => $order->getOriginal('order_status'),
                'new_status' => $newStatus->value,
            ]);
        }
    }

    /**
     * Handle the Order "deleted" event.
     * Release inventory if order is deleted
     */
    public function deleted(Order $order): void
    {
        // Release inventory if order had allocations
        if ($order->order_status !== OrderStatus::COMPLETED) {
            $this->autoReleaseInventory($order);
        }
        
        Log::info("Order deleted", ['order_id' => $order->id]);
    }

    /**
     * Generate a unique quote number.
     * Format: {QuotePrefix}YYYY-XXXX — prefix read from CRM Settings (e.g. "QUO-")
     */
    protected function generateQuoteNumber(): string
    {
        $branding  = CompanyBranding::getActive();
        $rawPrefix = rtrim($branding?->quote_prefix ?? 'QUO-', '-') . '-';
        $prefix    = $rawPrefix . date('Y') . '-';

        $lastQuote = Order::withTrashed()
            ->where('quote_number', 'LIKE', $prefix . '%')
            ->orderBy('quote_number', 'desc')
            ->first();

        $newNumber   = $lastQuote ? ((int) substr($lastQuote->quote_number, -4)) + 1 : 1;
        $quoteNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        while (Order::withTrashed()->where('quote_number', $quoteNumber)->exists()) {
            $newNumber++;
            $quoteNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }

        return $quoteNumber;
    }

    /**
     * Generate a unique order number.
     * Format: {OrderPrefix}YYYY-XXXX — prefix read from CRM Settings (e.g. "ORD-")
     */
    protected function generateOrderNumber(): string
    {
        $branding  = CompanyBranding::getActive();
        $rawPrefix = rtrim($branding?->order_prefix ?? 'ORD-', '-') . '-';
        $prefix    = $rawPrefix . date('Y') . '-';

        $lastOrder = Order::withTrashed()
            ->where('order_number', 'LIKE', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();

        $newNumber    = $lastOrder ? ((int) substr($lastOrder->order_number, -4)) + 1 : 1;
        $orderNumber  = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);

        while (Order::withTrashed()->where('order_number', $orderNumber)->exists()) {
            $newNumber++;
            $orderNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }

        return $orderNumber;
    }

    /**
     * Auto-allocate inventory when order moves to processing
     */
    protected function autoAllocateInventory(Order $order): void
    {
        try {
            $fulfillmentService = app(OrderFulfillmentService::class);
            $results = $fulfillmentService->allocateInventory($order);
            
            Log::info("Auto-allocated inventory for order", [
                'order_id' => $order->id,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to auto-allocate inventory", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Auto-release inventory when order is cancelled or deleted
     */
    protected function autoReleaseInventory(Order $order): void
    {
        try {
            $fulfillmentService = app(OrderFulfillmentService::class);
            $itemsReleased = $fulfillmentService->releaseInventory($order);
            
            Log::info("Auto-released inventory for order", [
                'order_id' => $order->id,
                'items_released' => $itemsReleased,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to auto-release inventory", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
