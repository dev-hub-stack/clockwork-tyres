<?php

namespace App\Modules\Consignments\Services;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsignmentInvoiceService
{
    /**
     * Record sale and create invoice with payment
     * 
     * @param Consignment $consignment
     * @param array $soldItems [['item_id' => 1, 'quantity' => 2, 'price' => 100.00], ...]
     * @param array $paymentData ['method' => 'cash', 'type' => 'full', 'amount' => 200.00]
     * @param string|null $notes
     * @return Order Invoice created from sale
     * @throws \Exception
     */
    public function recordSaleAndCreateInvoice(
        Consignment $consignment,
        array $soldItems,
        array $paymentData,
        ?string $notes = null
    ): Order {
        // Validate the operation
        $this->validateSale($consignment, $soldItems, $paymentData);
        
        return DB::transaction(function () use ($consignment, $soldItems, $paymentData, $notes) {
            // 1. Calculate totals
            $totals = $this->calculateSaleTotals($consignment, $soldItems);
            
            // 2. Create invoice
            $invoice = $this->createInvoiceFromConsignment($consignment, $soldItems, $totals, $notes);
            
            // 3. Record payment
            $this->recordPayment($invoice, $paymentData);
            
            // 4. Update consignment items
            $this->updateConsignmentItemsAfterSale($consignment, $soldItems, $invoice);
            
            // 5. Update consignment status
            $this->updateConsignmentStatusAfterSale($consignment);
            
            // 6. Log the action
            Log::info('Consignment sale recorded and invoice created', [
                'consignment_id' => $consignment->id,
                'consignment_number' => $consignment->consignment_number,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $totals['total'],
                'payment_amount' => $paymentData['amount'],
            ]);
            
            return $invoice;
        });
    }
    
    /**
     * Convert consignment to invoice without payment
     * 
     * @param Consignment $consignment
     * @param array $items [['item_id' => 1, 'quantity' => 2, 'price' => 100.00], ...]
     * @return Order
     * @throws \Exception
     */
    public function convertToInvoice(Consignment $consignment, array $items): Order
    {
        return DB::transaction(function () use ($consignment, $items) {
            // 1. Calculate totals
            $totals = $this->calculateSaleTotals($consignment, $items);
            
            // 2. Create invoice
            $invoice = $this->createInvoiceFromConsignment($consignment, $items, $totals, 'Converted from consignment');
            
            // 3. Mark items as sold
            $this->updateConsignmentItemsAfterSale($consignment, $items, $invoice);
            
            // 4. Update consignment status
            $this->updateConsignmentStatusAfterSale($consignment);
            
            // 5. Link invoice to consignment
            $consignment->update(['converted_invoice_id' => $invoice->id]);
            
            Log::info('Consignment converted to invoice', [
                'consignment_id' => $consignment->id,
                'invoice_id' => $invoice->id,
            ]);
            
            return $invoice;
        });
    }
    
    /**
     * Validate sale operation
     */
    protected function validateSale(Consignment $consignment, array $soldItems, array $paymentData): void
    {
        // Check if consignment can record sale
        if (!$consignment->canRecordSale()) {
            throw new \InvalidArgumentException(
                "Cannot record sale for consignment with status: {$consignment->status->getLabel()}"
            );
        }
        
        // Validate sold items
        if (empty($soldItems)) {
            throw new \InvalidArgumentException('No items selected for sale');
        }
        
        // Validate each item's availability
        foreach ($soldItems as $itemData) {
            $item = $consignment->items()->find($itemData['item_id']);
            
            if (!$item) {
                throw new \InvalidArgumentException("Item {$itemData['item_id']} not found in consignment");
            }
            
            if ($itemData['quantity'] > $item->quantity_available) {
                throw new \InvalidArgumentException(
                    "Insufficient quantity for item '{$item->name}'. Available: {$item->quantity_available}, Requested: {$itemData['quantity']}"
                );
            }
        }
        
        // Validate payment amount
        $saleTotal = collect($soldItems)->sum(function ($item) {
            return $item['quantity'] * $item['price'];
        });
        
        if ($paymentData['amount'] > $saleTotal) {
            throw new \InvalidArgumentException('Payment amount cannot exceed sale total');
        }
        
        if ($paymentData['type'] === 'full' && $paymentData['amount'] < $saleTotal) {
            throw new \InvalidArgumentException('Full payment requires payment amount to equal sale total');
        }
    }
    
    /**
     * Calculate sale totals (subtotal, tax, total)
     */
    protected function calculateSaleTotals(Consignment $consignment, array $soldItems): array
    {
        $subtotal = 0;
        
        foreach ($soldItems as $itemData) {
            $item = $consignment->items()->find($itemData['item_id']);
            $quantity = $itemData['quantity'];
            $price = $itemData['price'];
            
            // Handle tax-inclusive pricing
            if ($item->tax_inclusive ?? false) {
                // Extract tax from price
                $taxRate = $consignment->tax_rate ?? 5.0;
                $priceExcludingTax = $price / (1 + ($taxRate / 100));
                $subtotal += $quantity * $priceExcludingTax;
            } else {
                $subtotal += $quantity * $price;
            }
        }
        
        // Calculate tax
        $taxRate = $consignment->tax_rate ?? 5.0;
        $tax = $subtotal * ($taxRate / 100);
        
        // Calculate total
        $total = $subtotal + $tax;
        
        return [
            'subtotal' => round($subtotal, 2),
            'tax' => round($tax, 2),
            'tax_rate' => $taxRate,
            'total' => round($total, 2),
        ];
    }
    
    /**
     * Create invoice from consignment
     */
    protected function createInvoiceFromConsignment(
        Consignment $consignment,
        array $soldItems,
        array $totals,
        ?string $notes
    ): Order {
        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber();
        
        // Prepare invoice data
        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'customer_id' => $consignment->customer_id,
            'representative_id' => $consignment->representative_id,
            'warehouse_id' => $consignment->warehouse_id,
            'created_by' => auth()->id(),
            
            // Customer info (snapshot)
            'customer_name' => $consignment->customer->business_name ?? $consignment->customer->full_name,
            'customer_email' => $consignment->customer->email,
            'customer_phone' => $consignment->customer->phone,
            'customer_address' => $consignment->customer->address,
            
            // Vehicle info
            'vehicle_year' => $consignment->vehicle_year,
            'vehicle_make' => $consignment->vehicle_make,
            'vehicle_model' => $consignment->vehicle_model,
            'vehicle_sub_model' => $consignment->vehicle_sub_model,
            
            // Financial
            'subtotal' => $totals['subtotal'],
            'tax' => $totals['tax'],
            'tax_rate' => $totals['tax_rate'],
            'shipping' => 0,
            'discount' => 0,
            'discount_type' => 'none',
            'total' => $totals['total'],
            'amount_paid' => 0, // Will be updated by recordPayment
            'balance_due' => $totals['total'],
            
            // Status
            'status' => 'pending', // Will be updated by recordPayment
            'payment_status' => 1, // Unpaid
            
            // Currency
            'currency' => $consignment->currency ?? 'AED',
            
            // Dates
            'invoice_date' => now()->toDateString(),
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'sent_at' => now(),
            
            // Notes
            'notes' => $notes ?? "Generated from Consignment {$consignment->consignment_number}",
            'terms' => 'Payment due on delivery',
            
            // Metadata
            'channel' => $consignment->channel ?? 'retail',
            'source' => 'consignment',
            'external_invoice_references' => "CONSIGNMENT:{$consignment->consignment_number}",
        ];
        
        // Create invoice
        $invoice = Order::create($invoiceData);
        
        // Create invoice items
        foreach ($soldItems as $itemData) {
            $consignmentItem = $consignment->items()->find($itemData['item_id']);
            
            $this->createInvoiceItem($invoice, $consignmentItem, $itemData);
        }
        
        return $invoice;
    }
    
    /**
     * Create invoice item from consignment item
     */
    protected function createInvoiceItem(Order $invoice, ConsignmentItem $consignmentItem, array $itemData): OrderItem
    {
        $quantity = $itemData['quantity'];
        $price = $itemData['price'];
        
        // Handle tax-inclusive pricing
        if ($consignmentItem->tax_inclusive ?? false) {
            $taxRate = $invoice->tax_rate ?? 5.0;
            $priceExcludingTax = $price / (1 + ($taxRate / 100));
        } else {
            $priceExcludingTax = $price;
        }
        
        return OrderItem::create([
            'order_id' => $invoice->id,
            'product_id' => $consignmentItem->product_id,
            'product_variant_id' => $consignmentItem->product_variant_id,
            'add_on_id' => $consignmentItem->add_on_id,
            'external_product_id' => $consignmentItem->external_product_id,
            'external_source' => $consignmentItem->external_source,
            
            // Product info
            'name' => $consignmentItem->name,
            'sku' => $consignmentItem->sku,
            'description' => $this->buildProductDescription($consignmentItem),
            
            // Pricing
            'quantity' => $quantity,
            'unit_price' => $priceExcludingTax,
            'total_price' => $quantity * $priceExcludingTax,
            
            // Product details
            'brand_name' => $consignmentItem->brand_name,
            'model_name' => $consignmentItem->model_name,
            'finish_name' => $consignmentItem->finish_name,
            'size' => $consignmentItem->size,
            'bolt_pattern' => $consignmentItem->bolt_pattern,
            'offset' => $consignmentItem->offset,
            
            // Metadata
            'item_type' => !empty($consignmentItem->add_on_id) ? 'addon' : 'product',
            'tax_inclusive' => $consignmentItem->tax_inclusive ?? false,
            'consignment_item_id' => $consignmentItem->id, // Link back to consignment
        ]);
    }
    
    /**
     * Record payment on invoice
     */
    protected function recordPayment(Order $invoice, array $paymentData): void
    {
        $invoice->update([
            'amount_paid' => $paymentData['amount'],
            'balance_due' => $invoice->total - $paymentData['amount'],
            'payment_status' => $paymentData['type'] === 'full' ? 10 : 1, // 10=paid, 1=partial
            'status' => $paymentData['type'] === 'full' ? 'paid' : 'partially_paid',
            'paid_at' => $paymentData['type'] === 'full' ? now() : null,
            'payment_method' => $paymentData['method'],
            'payment_history' => [
                [
                    'amount' => $paymentData['amount'],
                    'method' => $paymentData['method'],
                    'type' => $paymentData['type'],
                    'date' => now()->toISOString(),
                    'created_by' => auth()->id(),
                ]
            ],
        ]);
    }
    
    /**
     * Update consignment items after sale
     */
    protected function updateConsignmentItemsAfterSale(
        Consignment $consignment,
        array $soldItems,
        Order $invoice
    ): void {
        foreach ($soldItems as $itemData) {
            $item = $consignment->items()->find($itemData['item_id']);
            
            if ($item) {
                $item->update([
                    'quantity_sold' => $item->quantity_sold + $itemData['quantity'],
                    'sale_price' => $itemData['price'],
                    'invoice_id' => $invoice->id,
                    'status' => 'sold',
                ]);
            }
        }
        
        // Update consignment item counts
        $consignment->updateItemCounts();
    }
    
    /**
     * Update consignment status after sale
     */
    protected function updateConsignmentStatusAfterSale(Consignment $consignment): void
    {
        $consignment->refresh();
        
        // Determine new status
        if ($consignment->items_sold_count >= $consignment->items_sent_count) {
            $newStatus = ConsignmentStatus::INVOICED_IN_FULL;
        } elseif ($consignment->items_sold_count > 0) {
            $newStatus = ConsignmentStatus::PARTIALLY_SOLD;
        } else {
            $newStatus = $consignment->status; // Keep current status
        }
        
        if ($newStatus !== $consignment->status) {
            $consignment->update(['status' => $newStatus]);
            
            // Log status change in history
            $consignment->histories()->create([
                'status' => $newStatus,
                'description' => 'Items sold and invoice created',
                'created_by' => auth()->id(),
            ]);
        }
    }
    
    /**
     * Build comprehensive product description
     */
    protected function buildProductDescription(ConsignmentItem $item): string
    {
        $parts = [];
        
        // Start with product name
        $productName = $item->name ?? 'Product';
        $parts[] = $productName;
        
        // Add brand
        if ($item->brand_name) {
            $parts[] = "Brand: {$item->brand_name}";
        }
        
        // Add size and bolt pattern
        if ($item->size && $item->bolt_pattern) {
            $parts[] = "Size: {$item->size}, Bolt Pattern: {$item->bolt_pattern}";
        } elseif ($item->size) {
            $parts[] = "Size: {$item->size}";
        } elseif ($item->bolt_pattern) {
            $parts[] = "Bolt Pattern: {$item->bolt_pattern}";
        }
        
        // Add offset
        if ($item->offset) {
            $parts[] = "Offset: {$item->offset}";
        }
        
        // Add finish/model
        if ($item->finish_name) {
            $parts[] = "Finish: {$item->finish_name}";
        }
        if ($item->model_name) {
            $parts[] = "Model: {$item->model_name}";
        }
        
        return implode(' | ', $parts);
    }
    
    /**
     * Generate unique invoice number
     */
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        
        $lastInvoice = Order::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
        
        $number = $lastInvoice ? intval(substr($lastInvoice->invoice_number, -4)) + 1 : 1;
        
        return $prefix . '-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
