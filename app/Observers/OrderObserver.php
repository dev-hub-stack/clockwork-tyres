<?php

namespace App\Observers;

use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderFulfillmentService;
use Illuminate\Support\Facades\Log;

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
     * Generate a unique quote number
     * Format: QUO-YYYY-XXXX
     */
    protected function generateQuoteNumber(): string
    {
        $year = date('Y');
        $prefix = "QUO-{$year}-";
        
        // Get the highest quote number for this year (including soft deleted)
        $lastQuote = Order::withTrashed()
            ->where('quote_number', 'LIKE', $prefix . '%')
            ->orderBy('quote_number', 'desc')
            ->first();
        
        if ($lastQuote) {
            // Extract the number and increment
            $lastNumber = (int) substr($lastQuote->quote_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        $quoteNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        
        // Extra safety: Check if this number exists (shouldn't happen but just in case)
        while (Order::withTrashed()->where('quote_number', $quoteNumber)->exists()) {
            $newNumber++;
            $quoteNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }
        
        return $quoteNumber;
    }

    /**
     * Generate a unique order number
     * Format: ORD-YYYY-XXXX
     */
    protected function generateOrderNumber(): string
    {
        $year = date('Y');
        $prefix = "ORD-{$year}-";
        
        // Get the highest order number for this year (including soft deleted)
        $lastOrder = Order::withTrashed()
            ->where('order_number', 'LIKE', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();
        
        if ($lastOrder) {
            // Extract the number and increment
            $lastNumber = (int) substr($lastOrder->order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        $orderNumber = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        
        // Extra safety: Check if this number exists (shouldn't happen but just in case)
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
