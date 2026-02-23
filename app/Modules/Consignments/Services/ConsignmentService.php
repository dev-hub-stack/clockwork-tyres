<?php

namespace App\Modules\Consignments\Services;

use App\Models\User;
use App\Modules\Consignments\Enums\ConsignmentStatus;
use App\Modules\Consignments\Models\Consignment;
use App\Modules\Consignments\Models\ConsignmentHistory;
use App\Modules\Consignments\Models\ConsignmentItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\DealerPricingService;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Models\ProductVariant;
use App\Services\ProductSnapshotService;
use App\Services\VariantSnapshotService;
use Illuminate\Support\Facades\DB;

class ConsignmentService
{
    public function __construct(
        protected ProductSnapshotService $productSnapshotService,
        protected VariantSnapshotService $variantSnapshotService,
        protected ConsignmentSnapshotService $consignmentSnapshotService,
        protected DealerPricingService $dealerPricingService,
    ) {}

    /**
     * Create a new consignment with items
     */
    public function createConsignment(array $data): Consignment
    {
        return DB::transaction(function () use ($data) {
            // Generate consignment number if not provided
            if (!isset($data['consignment_number'])) {
                $data['consignment_number'] = Consignment::generateConsignmentNumber();
            }

            // Set default status
            $data['status'] = $data['status'] ?? ConsignmentStatus::DRAFT;
            
            // Set created_by if not provided
            if (!isset($data['created_by']) && auth()->check()) {
                $data['created_by'] = auth()->id();
            }

            // Create consignment
            $consignment = Consignment::create($data);

            // Add items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                $this->addItems($consignment, $data['items']);
            }

            // Refresh consignment to load newly created items
            $consignment = $consignment->fresh();

            // Calculate totals
            $consignment->calculateTotals();
            $consignment->updateItemCounts();

            // Log creation
            $this->logHistory($consignment, 'created', 'Consignment created');

            return $consignment->fresh();
        });
    }

    /**
     * Add items to consignment
     */
    public function addItems(Consignment $consignment, array $items): void
    {
        foreach ($items as $itemData) {
            $variant = ProductVariant::find($itemData['product_variant_id']);
            
            if (!$variant) {
                continue;
            }

            // Create product snapshot
            $snapshot = $this->consignmentSnapshotService->createSnapshot($variant, $consignment->customer_id);

            // Determine price
            $price = $itemData['price'] ?? $this->calculateItemPrice($variant, $consignment->customer_id);

            $warehouseId = $itemData['warehouse_id'] ?? null;

            // Create consignment item
            ConsignmentItem::create([
                'consignment_id' => $consignment->id,
                'product_variant_id' => $variant->id,
                'warehouse_id' => $warehouseId,
                'product_snapshot' => $snapshot,
                
                // Denormalized fields
                'product_name' => $variant->product->name ?? '',
                'brand_name' => $variant->product->brand->name ?? '',
                'sku' => $variant->sku,
                'description' => $variant->product->description ?? '',
                
                // Quantities
                'quantity_sent' => $itemData['quantity_sent'] ?? $itemData['quantity'] ?? 1,
                'quantity_sold' => 0,
                'quantity_returned' => 0,
                
                // Pricing
                'price' => $price,
                'tax_inclusive' => $itemData['tax_inclusive'] ?? true,
                
                // Status
                'status' => \App\Modules\Consignments\Enums\ConsignmentItemStatus::SENT,
                
                // Notes
                'notes' => $itemData['notes'] ?? null,
            ]);

            // Note: Inventory is deducted when consignment is marked as SENT, not on creation
            // This allows draft consignments to be created without affecting inventory
        }
    }

    /**
     * Calculate item price with dealer pricing if applicable
     */
    protected function calculateItemPrice(ProductVariant $variant, ?int $customerId = null): float
    {
        if (!$customerId) {
            return $variant->price ?? $variant->product->retail_price ?? 0;
        }

        $customer = Customer::find($customerId);
        if (!$customer) {
            return $variant->price ?? $variant->product->retail_price ?? 0;
        }

        $basePrice = $variant->price ?? $variant->product->retail_price ?? 0;
        
        $pricingResult = $this->dealerPricingService->calculateProductPrice(
            $customer,
            $basePrice,
            $variant->product->model_id,
            $variant->product->brand_id
        );

        return $pricingResult['final_price'] ?? $basePrice;
    }

    /**
     * Record sale of consignment items
     */
    public function recordSale(Consignment $consignment, array $soldItems, bool $createInvoice = false): ?Order
    {
        return DB::transaction(function () use ($consignment, $soldItems, $createInvoice) {
            $invoice = null;

            // Mark items as sold
            foreach ($soldItems as $saleData) {
                $item = $consignment->items()->find($saleData['item_id']);
                
                if ($item && $item->canBeSold()) {
                    $quantity = min($saleData['quantity'], $item->getAvailableToSell());
                    $salePrice = $saleData['actual_sale_price'] ?? null;
                    
                    $item->markAsSold($quantity, $salePrice);
                }
            }

            // Update consignment counts and status
            $consignment->updateItemCounts();
            $consignment->updateStatusBasedOnItems();

            // Create invoice if requested
            if ($createInvoice) {
                $invoice = $this->createInvoiceForSoldItems($consignment, $soldItems);
            }

            // Log sale
            $this->logHistory($consignment, 'sale_recorded', 'Items sold', [
                'items_count' => count($soldItems),
                'invoice_id' => $invoice?->id,
            ]);

            return $invoice;
        });
    }

    /**
     * Record return of consignment items
     */
    public function recordReturn(Consignment $consignment, array $returnedItems, bool $updateInventory = false): void
    {
        DB::transaction(function () use ($consignment, $returnedItems, $updateInventory) {
            // Mark items as returned
            foreach ($returnedItems as $returnData) {
                $item = $consignment->items()->find($returnData['item_id']);
                
                if ($item && $item->canBeReturned()) {
                    $quantity = min($returnData['quantity'], $item->getAvailableToReturn());
                    
                    $item->markAsReturned($quantity);

                    // Update inventory if requested - use item's warehouse, not consignment's
                    if ($updateInventory && $item->warehouse_id) {
                        $this->updateInventoryForReturn($item, $quantity, $item->warehouse_id);
                    }
                }
            }

            // Update consignment counts and status
            $consignment->updateItemCounts();
            $consignment->updateStatusBasedOnItems();

            // Recalculate returned_value so balance_value reflects returned items correctly
            $consignment->load('items');
            $returnedValue = $consignment->items->sum(
                fn($item) => ($item->quantity_returned ?? 0) * ($item->price ?? 0)
            );
            $consignment->returned_value = $returnedValue;
            $consignment->save();
            $consignment->calculateTotals();

            // Log return
            $this->logHistory($consignment, 'return_recorded', 'Items returned to warehouse', [
                'items_count' => count($returnedItems),
                'inventory_updated' => $updateInventory,
            ]);
        });
    }

    /**
     * Mark consignment as sent - deducts inventory from warehouses
     */
    public function markAsSent(Consignment $consignment, ?string $trackingNumber = null): void
    {
        DB::transaction(function () use ($consignment, $trackingNumber) {
            // Deduct inventory from warehouses for each item
            foreach ($consignment->items as $item) {
                if ($item->warehouse_id && $item->quantity_sent > 0) {
                    ProductInventory::where([
                        'warehouse_id' => $item->warehouse_id,
                        'product_variant_id' => $item->product_variant_id,
                    ])->decrement('quantity', $item->quantity_sent);
                }
            }

            $consignment->update([
                'status' => ConsignmentStatus::SENT,
                'sent_at' => now(),
                'tracking_number' => $trackingNumber,
            ]);

            $this->logHistory($consignment, 'status_changed', 'Consignment marked as sent - inventory deducted', [
                'tracking_number' => $trackingNumber,
            ]);
        });
    }

    /**
     * Mark consignment as delivered
     */
    public function markAsDelivered(Consignment $consignment): void
    {
        $consignment->update([
            'status' => ConsignmentStatus::DELIVERED,
            'delivered_at' => now(),
        ]);

        $this->logHistory($consignment, 'status_changed', 'Consignment delivered to customer');
    }

    /**
     * Cancel consignment — restores inventory for unsold/unreturned items and zeros all values
     */
    public function cancelConsignment(Consignment $consignment, string $reason = ''): void
    {
        DB::transaction(function () use ($consignment, $reason) {
            // Restore warehouse inventory for items still out (sent - returned)
            $consignment->load('items');
            foreach ($consignment->items as $item) {
                $remaining = ($item->quantity_sent ?? 0) - ($item->quantity_returned ?? 0);
                if ($remaining > 0 && $item->warehouse_id && $item->product_variant_id) {
                    ProductInventory::where([
                        'warehouse_id'       => $item->warehouse_id,
                        'product_variant_id' => $item->product_variant_id,
                    ])->increment('quantity', $remaining);
                }
            }

            // Zero out all counts and financial values
            $consignment->update([
                'status'               => ConsignmentStatus::CANCELLED,
                'items_sent_count'     => 0,
                'items_sold_count'     => 0,
                'items_returned_count' => 0,
                'total_value'          => 0,
                'invoiced_value'       => 0,
                'returned_value'       => 0,
                'balance_value'        => 0,
                'subtotal'             => 0,
                'tax'                  => 0,
                'total'                => 0,
            ]);

            $this->logHistory($consignment, 'cancelled', $reason ?: 'Consignment cancelled', [
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Convert consignment to invoice (public wrapper)
     */
    public function convertToInvoice(Consignment $consignment): Order
    {
        return DB::transaction(function () use ($consignment) {
            // Get all sold items
            $soldItems = $consignment->items()
                ->where('qty_sold', '>', 0)
                ->get()
                ->map(function ($item) {
                    return [
                        'item_id' => $item->id,
                        'quantity' => $item->qty_sold - ($item->invoiced_quantity ?? 0), // Only uninvoiced sold items
                        'actual_sale_price' => $item->actual_sale_price ?? $item->price,
                    ];
                })
                ->filter(fn ($item) => $item['quantity'] > 0)
                ->toArray();

            if (empty($soldItems)) {
                throw new \Exception('No sold items available to invoice.');
            }

            $invoice = $this->createInvoiceForSoldItems($consignment, $soldItems);

            $this->logHistory($consignment, 'converted_to_invoice', 'Converted to invoice', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->order_number,
            ]);

            return $invoice;
        });
    }

    /**
     * Create invoice for sold items
     */
    protected function createInvoiceForSoldItems(Consignment $consignment, array $soldItems): Order
    {
        // Create invoice (Order with document_type = invoice)
        $invoice = Order::create([
            'document_type' => DocumentType::INVOICE,
            'order_number' => $this->generateInvoiceNumber(),
            'customer_id' => $consignment->customer_id,
            'warehouse_id' => $consignment->warehouse_id,
            'representative_id' => $consignment->representative_id,
            'sub_total' => 0,
            'tax' => 0,
            'total' => 0,
            'order_status' => \App\Modules\Orders\Enums\OrderStatus::PENDING,
            'payment_status' => \App\Modules\Orders\Enums\PaymentStatus::PENDING,
            'issue_date' => now(),
            'order_notes' => "Created from Consignment #{$consignment->consignment_number}",
        ]);

        // Add sold items to invoice
        $subtotal = 0;
        foreach ($soldItems as $saleData) {
            $item = $consignment->items()->find($saleData['item_id']);
            
            if ($item) {
                $quantity = $saleData['quantity'];
                $price = $saleData['actual_sale_price'] ?? $item->price;
                
                OrderItem::create([
                    'order_id' => $invoice->id,
                    'product_variant_id' => $item->product_variant_id,
                    'product_snapshot' => $item->product_snapshot,
                    'variant_snapshot' => $item->product_snapshot['variant_data'] ?? null,
                    'sku' => $item->sku,
                    'product_name' => $item->product_name,
                    'brand_name' => $item->brand_name,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'line_total' => $quantity * $price,
                    'tax_inclusive' => false, // Consignment items are not tax inclusive
                ]);

                $subtotal += ($quantity * $price);
            }
        }

        // Update invoice totals
        $tax = $subtotal * ($consignment->tax_rate / 100);
        $invoice->update([
            'sub_total' => $subtotal,
            'tax' => $tax,
            'total' => $subtotal + $tax,
        ]);

        // Link consignment to invoice
        $consignment->update([
            'converted_invoice_id' => $invoice->id,
        ]);

        return $invoice;
    }

    /**
     * Update inventory for returned items
     */
    protected function updateInventoryForReturn(ConsignmentItem $item, int $quantity, int $warehouseId): void
    {
        $inventory = ProductInventory::firstOrCreate([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $item->product_variant_id,
        ], [
            'quantity' => 0,
        ]);

        $inventory->increment('quantity', $quantity);
    }

    /**
     * Log history entry
     */
    protected function logHistory(Consignment $consignment, string $action, string $description, array $metadata = []): void
    {
        ConsignmentHistory::create([
            'consignment_id' => $consignment->id,
            'action' => $action,
            'description' => $description,
            'performed_by' => auth()->id(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Generate invoice number
     */
    protected function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $year = date('Y');
        
        $lastInvoice = Order::where('document_type', DocumentType::INVOICE)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
        
        $number = $lastInvoice ? intval(substr($lastInvoice->order_number, -4)) + 1 : 1;
        
        return $prefix . '-' . $year . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
