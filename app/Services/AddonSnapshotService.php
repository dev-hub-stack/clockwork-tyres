<?php

namespace App\Services;

use App\Modules\AddOns\Models\Addon;
use App\Modules\AddOns\Models\AddonCategory;
use Illuminate\Support\Facades\Log;

/**
 * AddonSnapshotService
 * 
 * Captures addon data at order/quote/invoice time to prevent price changes
 * from affecting historical documents.
 * 
 * CRITICAL: This service must be used whenever addons are added to:
 * - Orders
 * - Quotes  
 * - Invoices
 * - Consignments
 */
class AddonSnapshotService
{
    /**
     * Create a snapshot of addon data
     * 
     * @param Addon $addon
     * @param int|null $customerId For customer-specific pricing
     * @param int $quantity
     * @return array Snapshot data
     */
    public static function createSnapshot(Addon $addon, ?int $customerId = null, int $quantity = 1): array
    {
        try {
            // Get customer-specific price if customer ID provided
            $price = $customerId 
                ? $addon->getPriceForCustomer($customerId)
                : $addon->price;
            
            $discount = $customerId 
                ? $addon->getDiscountForCustomer($customerId)
                : 0;

            // Build comprehensive snapshot
            $snapshot = [
                // Core identification
                'addon_id' => $addon->id,
                'title' => $addon->title,
                'part_number' => $addon->part_number,
                'description' => $addon->description,
                
                // Category information
                'addon_category_id' => $addon->addon_category_id,
                'category_name' => $addon->category->name ?? null,
                'category_slug' => $addon->category->slug ?? null,
                
                // Pricing (captured at time of order)
                'price' => (float) $price,
                'retail_price' => (float) $addon->price,
                'wholesale_price' => $addon->wholesale_price ? (float) $addon->wholesale_price : null,
                'discount_amount' => (float) $discount,
                'tax_inclusive' => (bool) $addon->tax_inclusive,
                
                // Quantity and totals
                'quantity' => $quantity,
                'subtotal' => (float) ($price * $quantity),
                'discount_total' => (float) ($discount * $quantity),
                
                // Images
                'image_1' => $addon->image_1,
                'image_2' => $addon->image_2,
                'image_1_url' => $addon->image_1_url,
                'image_2_url' => $addon->image_2_url,
                
                // Technical specs (captured for historical reference)
                'bolt_pattern' => $addon->bolt_pattern,
                'width' => $addon->width,
                'thread_size' => $addon->thread_size,
                'thread_length' => $addon->thread_length,
                'ext_center_bore' => $addon->ext_center_bore,
                'center_bore' => $addon->center_bore,
                'color' => $addon->color,
                'lug_nut_length' => $addon->lug_nut_length,
                'lug_nut_diameter' => $addon->lug_nut_diameter,
                'lug_bolt_diameter' => $addon->lug_bolt_diameter,
                
                // Metadata
                'snapshot_created_at' => now()->toDateTimeString(),
                'snapshot_version' => '1.0',
            ];

            Log::info('AddonSnapshotService: Snapshot created', [
                'addon_id' => $addon->id,
                'customer_id' => $customerId,
                'price' => $price,
                'discount' => $discount,
            ]);

            return $snapshot;

        } catch (\Exception $e) {
            Log::error('AddonSnapshotService: Failed to create snapshot', [
                'addon_id' => $addon->id ?? null,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create snapshots for multiple addons
     * 
     * @param array $addonData Array of ['addon_id' => id, 'quantity' => qty]
     * @param int|null $customerId
     * @return array Array of snapshots
     */
    public static function createBulkSnapshots(array $addonData, ?int $customerId = null): array
    {
        $snapshots = [];

        foreach ($addonData as $item) {
            $addon = Addon::find($item['addon_id']);
            
            if (!$addon) {
                Log::warning('AddonSnapshotService: Addon not found', [
                    'addon_id' => $item['addon_id'],
                ]);
                continue;
            }

            $quantity = $item['quantity'] ?? 1;
            $snapshots[] = self::createSnapshot($addon, $customerId, $quantity);
        }

        return $snapshots;
    }

    /**
     * Restore addon data from snapshot
     * 
     * Useful for displaying historical order details without 
     * querying the current addon record (which may have changed)
     * 
     * @param array $snapshot
     * @return object Addon-like object with snapshot data
     */
    public static function restoreFromSnapshot(array $snapshot): object
    {
        return (object) $snapshot;
    }

    /**
     * Calculate totals from addon snapshots
     * 
     * @param array $snapshots Array of addon snapshots
     * @param bool $includeTax Whether to include tax in calculations
     * @return array ['subtotal', 'discount', 'total', 'tax']
     */
    public static function calculateTotals(array $snapshots, bool $includeTax = false): array
    {
        $subtotal = 0;
        $discount = 0;

        foreach ($snapshots as $snapshot) {
            $subtotal += $snapshot['subtotal'] ?? 0;
            $discount += $snapshot['discount_total'] ?? 0;
        }

        $total = $subtotal - $discount;

        // Tax calculation (if needed)
        $tax = 0;
        if ($includeTax) {
            foreach ($snapshots as $snapshot) {
                if (!($snapshot['tax_inclusive'] ?? false)) {
                    // Tax not included, calculate it (assuming 5% VAT)
                    $itemTotal = ($snapshot['price'] ?? 0) * ($snapshot['quantity'] ?? 1);
                    $tax += $itemTotal * 0.05;
                }
            }
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total' => round($total, 2),
            'tax' => round($tax, 2),
            'grand_total' => round($total + $tax, 2),
        ];
    }

    /**
     * Validate snapshot integrity
     * 
     * Ensures snapshot has all required fields
     * 
     * @param array $snapshot
     * @return bool
     */
    public static function validateSnapshot(array $snapshot): bool
    {
        $requiredFields = [
            'addon_id',
            'title',
            'price',
            'quantity',
            'addon_category_id',
        ];

        foreach ($requiredFields as $field) {
            if (!isset($snapshot[$field])) {
                Log::warning('AddonSnapshotService: Invalid snapshot - missing field', [
                    'field' => $field,
                    'snapshot' => $snapshot,
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * Compare snapshot with current addon data
     * 
     * Useful for showing customers what changed since order was placed
     * 
     * @param array $snapshot
     * @param Addon $currentAddon
     * @return array Changes detected
     */
    public static function compareWithCurrent(array $snapshot, Addon $currentAddon): array
    {
        $changes = [];

        // Price changes
        if ($snapshot['retail_price'] != $currentAddon->price) {
            $changes['price'] = [
                'old' => $snapshot['retail_price'],
                'new' => $currentAddon->price,
                'difference' => $currentAddon->price - $snapshot['retail_price'],
            ];
        }

        // Stock status changes
        if ($currentAddon->stock_status == 0) {
            $changes['stock_status'] = 'out_of_stock';
        }

        // Title/description changes
        if ($snapshot['title'] != $currentAddon->title) {
            $changes['title'] = [
                'old' => $snapshot['title'],
                'new' => $currentAddon->title,
            ];
        }

        return $changes;
    }
}
