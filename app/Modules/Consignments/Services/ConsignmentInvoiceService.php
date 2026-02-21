<?php

namespace App\Modules\Consignments\Services;

use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Orders\Models\Payment;
use App\Modules\Settings\Models\CompanyBranding;
use App\Modules\Settings\Models\CurrencySetting;
use App\Modules\Settings\Models\TaxSetting;
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
                'consignment_id'     => $consignment->id,
                'consignment_number' => $consignment->consignment_number,
                'invoice_id'         => $invoice->id,
                'invoice_number'     => $invoice->order_number,
                'total_amount'       => $totals['total'],
                'payment_amount'     => $paymentData['amount'],
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
            
            // Calculate available quantity manually
            $quantityAvailable = $item->quantity_sent - ($item->quantity_sold ?? 0) - ($item->quantity_returned ?? 0);
            
            if ($itemData['quantity'] > $quantityAvailable) {
                throw new \InvalidArgumentException(
                    "Insufficient quantity for item '{$item->product_name}'. Available: {$quantityAvailable}, Requested: {$itemData['quantity']}"
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
        // Get tax rate from settings
        $taxSetting = TaxSetting::getDefault();
        $taxRate = $taxSetting ? floatval($taxSetting->rate) : 5.0;
        
        $subtotal = 0;
        
        foreach ($soldItems as $itemData) {
            $item = $consignment->items()->find($itemData['item_id']);
            $quantity = $itemData['quantity'];
            $price = $itemData['price'];
            
            // Handle tax-inclusive pricing
            if ($item->tax_inclusive ?? false) {
                // Extract tax from price
                $priceExcludingTax = $price / (1 + ($taxRate / 100));
                $subtotal += $quantity * $priceExcludingTax;
            } else {
                $subtotal += $quantity * $price;
            }
        }
        
        // Calculate tax
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
        
        // Resolve warehouse: consignment has no warehouse_id (it's per-item),
        // so fall back to the first sold item's warehouse.
        $firstSoldItem = $consignment->items()->find($soldItems[0]['item_id'] ?? null);
        $warehouseId = $consignment->warehouse_id
            ?? $firstSoldItem?->warehouse_id;

        // Prepare invoice data
        $invoiceData = [
            'document_type'     => DocumentType::INVOICE,
            'order_number'      => $invoiceNumber,
            'customer_id'       => $consignment->customer_id,
            'representative_id' => $consignment->representative_id,
            'warehouse_id'      => $warehouseId,
            'created_by'        => auth()->id(),

            // Financial
            'sub_total'         => $totals['subtotal'],
            'tax'               => $totals['tax'],
            'vat'               => $totals['tax'],  // vat mirrors tax (both store the VAT amount)
            'shipping'          => 0,
            'discount'          => 0,
            'total'             => $totals['total'],
            'paid_amount'       => 0,
            'outstanding_amount'=> $totals['total'],

            // Status
            'order_status'      => 'pending',
            'payment_status'    => 'pending',

            // Currency
            'currency'          => CurrencySetting::getBase()?->currency_symbol ?? 'AED',

            // Dates
            'issue_date'        => now()->toDateString(),
            'valid_until'       => now()->addDays(30)->toDateString(),
            'sent_at'           => now(),

            // Notes
            'order_notes'       => $notes ?? "Generated from Consignment {$consignment->consignment_number}",

            // Source tracking
            'channel'           => $consignment->channel ?? 'retail',
            'external_source'   => 'consignment',
        ];
        
        // Create invoice
        $invoice = Order::create($invoiceData);
        
        // Create invoice items
        foreach ($soldItems as $itemData) {
            $consignmentItem = $consignment->items()->find($itemData['item_id']);
            
            $this->createInvoiceItem($invoice, $consignmentItem, $itemData, $totals);
        }
        
        return $invoice;
    }
    
    /**
     * Create invoice item from consignment item
     */
    protected function createInvoiceItem(Order $invoice, ConsignmentItem $consignmentItem, array $itemData, array $totals = []): OrderItem
    {
        $quantity = $itemData['quantity'];
        $price = $itemData['price'];
        $taxRate = $totals['tax_rate'] ?? 5.0;

        // Handle tax-inclusive pricing
        if ($consignmentItem->tax_inclusive ?? false) {
            // Price entered includes VAT — extract the ex-VAT amount for line items
            $priceExcludingTax = $price / (1 + ($taxRate / 100));
        } else {
            // Price is already ex-VAT
            $priceExcludingTax = $price;
        }

        // Build product snapshot with variant specs so they appear on invoice preview/PDF
        $snapshot = $consignmentItem->product_snapshot ?? [];
        if ($consignmentItem->product_variant_id) {
            $variant = \App\Modules\Products\Models\ProductVariant::with(['finishRelation', 'product.finish'])
                ->find($consignmentItem->product_variant_id);
            if ($variant) {
                $existingSnap = is_array($snapshot) ? $snapshot : [];
                $finishName = $variant->finishRelation?->finish
                    ?? $variant->product?->finish?->finish
                    ?? (is_array($existingSnap['finish'] ?? null) ? ($existingSnap['finish']['finish'] ?? null) : null)
                    ?? $variant->getRawOriginal('finish');
                $snapshot['specifications'] = [
                    'size'         => $variant->size,
                    'bolt_pattern' => $variant->bolt_pattern,
                    'offset'       => $variant->offset,
                    'finish'       => $finishName,
                ];
            }
        }

        return OrderItem::create([
            'order_id'            => $invoice->id,
            'product_variant_id'  => $consignmentItem->product_variant_id,
            'warehouse_id'        => $consignmentItem->warehouse_id,

            // Product info from denormalized fields
            'product_name'        => $consignmentItem->product_name,
            'sku'                 => $consignmentItem->sku,
            'brand_name'          => $consignmentItem->brand_name,

            // Snapshot with specifications (size, bolt pattern, offset, finish)
            'product_snapshot'    => $snapshot,

            // Pricing
            'quantity'            => $quantity,
            'unit_price'          => $priceExcludingTax,
            'tax_inclusive'       => false, // tax already extracted; line_total is ex-VAT
            'discount'            => 0,
            'line_total'          => round($quantity * $priceExcludingTax, 2),
        ]);
    }
    
    /**
     * Record payment on invoice
     * Creates a Payment record which automatically updates the order's payment status
     */
    protected function recordPayment(Order $invoice, array $paymentData): void
    {
        // Create payment record - Payment model will automatically update order payment status
        Payment::create([
            'order_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'recorded_by' => auth()->id(),
            'amount' => $paymentData['amount'],
            'payment_method' => $paymentData['method'],
            'payment_date' => now(),
            'reference_number' => $paymentData['reference'] ?? null,
            'notes' => 'Payment recorded during consignment sale',
            'status' => 'completed',
        ]);
        
        // Update order status based on payment type
        $invoice->update([
            'order_status' => $paymentData['type'] === 'full' ? 'completed' : 'processing',
        ]);
        
        Log::info('Payment recorded for consignment invoice', [
            'invoice_id' => $invoice->id,
            'payment_amount' => $paymentData['amount'],
            'payment_type' => $paymentData['type'],
            'payment_method' => $paymentData['method'],
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
                    'quantity_sold'     => $item->quantity_sold + $itemData['quantity'],
                    'actual_sale_price' => $itemData['price'],
                    'status'            => 'sold',
                    'date_sold'         => now(),
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

        // Recalculate invoiced_value from sold items
        $invoicedValue = $consignment->items->sum(function ($item) {
            return ($item->quantity_sold ?? 0) * ($item->price ?? 0);
        });
        $returnedValue = floatval($consignment->returned_value ?? 0);
        $totalValue    = floatval($consignment->total_value ?? 0);

        $consignment->update([
            'invoiced_value' => $invoicedValue,
            'balance_value'  => $totalValue - $invoicedValue - $returnedValue,
        ]);

        // Determine new status
        if ($consignment->items_sold_count >= $consignment->items_sent_count) {
            $newStatus = ConsignmentStatus::INVOICED_IN_FULL;
        } elseif ($consignment->items_sold_count > 0) {
            $newStatus = ConsignmentStatus::PARTIALLY_SOLD;
        } else {
            $newStatus = $consignment->status;
        }

        if ($newStatus !== $consignment->status) {
            $consignment->update(['status' => $newStatus]);

            $consignment->histories()->create([
                'action'       => 'sale_recorded',
                'description'  => 'Items sold and invoice created',
                'performed_by' => auth()->id(),
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
        $productName = $item->product_name ?? 'Product';
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

        $lastInvoice = Order::invoices()
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $lastNumber = 0;
        if ($lastInvoice && $lastInvoice->order_number) {
            // Extract trailing digits, e.g. "INV-2026-0004" → 4
            preg_match('/(\d+)$/', $lastInvoice->order_number, $matches);
            $lastNumber = isset($matches[1]) ? intval($matches[1]) : 0;
        }

        return $prefix . '-' . $year . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    }
}
