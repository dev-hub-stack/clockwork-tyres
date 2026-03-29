<?php

namespace App\Modules\Procurement\Support;

final class SupplierGroupedProcurementPlanner
{
    /**
     * Build a retailer workbench submission model grouped by supplier.
     *
     * The returned structure is intentionally pure and array-based so the UI
     * and future persistence layers can use the same planning logic.
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     * @return array<string, mixed>
     */
    public static function plan(array $lineItems): array
    {
        $groups = [];
        $grandQuantity = 0;
        $grandSubtotal = 0.0;

        foreach ($lineItems as $lineItem) {
            $supplierKey = self::supplierKey($lineItem);

            if (! isset($groups[$supplierKey])) {
                $groups[$supplierKey] = [
                    'supplier_id' => self::valueOrNull($lineItem, ['supplier_id', 'supplierId', 'selected_supplier_id']),
                    'supplier_name' => self::valueOrNull($lineItem, ['supplier_name', 'supplierName', 'selected_supplier_name']) ?? 'Unassigned supplier',
                    'supplier_key' => $supplierKey,
                    'line_items' => [],
                    'quantity_total' => 0,
                    'subtotal' => 0.0,
                ];
            }

            $quantity = (int) self::valueOrNull($lineItem, ['quantity', 'qty']) ?? 0;
            $lineTotal = self::lineTotal($lineItem, $quantity);

            $normalizedItem = array_merge($lineItem, [
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ]);

            $groups[$supplierKey]['line_items'][] = $normalizedItem;
            $groups[$supplierKey]['quantity_total'] += $quantity;
            $groups[$supplierKey]['subtotal'] += $lineTotal;

            $grandQuantity += $quantity;
            $grandSubtotal += $lineTotal;
        }

        $supplierOrders = [];

        foreach (array_values($groups) as $index => $group) {
            $supplierOrders[] = [
                'child_order_key' => sprintf('supplier-order-%02d', $index + 1),
                'supplier_id' => $group['supplier_id'],
                'supplier_name' => $group['supplier_name'],
                'supplier_key' => $group['supplier_key'],
                'status' => 'draft',
                'line_items' => $group['line_items'],
                'quantity_total' => $group['quantity_total'],
                'subtotal' => round((float) $group['subtotal'], 2),
                'action' => 'quote_then_invoice',
            ];
        }

        return [
            'submission_type' => 'grouped_supplier_workbench',
            'workbench_label' => 'Retailer procurement workbench',
            'split_per_supplier' => true,
            'place_order_label' => 'Place Order',
            'supplier_count' => count($supplierOrders),
            'line_item_count' => count($lineItems),
            'quantity_total' => $grandQuantity,
            'subtotal' => round($grandSubtotal, 2),
            'supplier_orders' => $supplierOrders,
        ];
    }

    /**
     * @param  array<string, mixed>  $lineItem
     */
    private static function supplierKey(array $lineItem): string
    {
        $supplierId = self::valueOrNull($lineItem, ['supplier_id', 'supplierId', 'selected_supplier_id']);
        $supplierName = self::valueOrNull($lineItem, ['supplier_name', 'supplierName', 'selected_supplier_name']);

        if ($supplierId !== null && $supplierId !== '') {
            return (string) $supplierId;
        }

        if ($supplierName !== null && $supplierName !== '') {
            return (string) $supplierName;
        }

        return 'unassigned-supplier';
    }

    /**
     * @param  array<string, mixed>  $lineItem
     * @param  array<int, string>  $keys
     */
    private static function valueOrNull(array $lineItem, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $lineItem) && $lineItem[$key] !== '') {
                return $lineItem[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $lineItem
     */
    private static function lineTotal(array $lineItem, int $quantity): float
    {
        $explicitLineTotal = self::valueOrNull($lineItem, ['line_total', 'subtotal', 'total']);
        if ($explicitLineTotal !== null) {
            return (float) $explicitLineTotal;
        }

        $unitPrice = self::valueOrNull($lineItem, ['unit_price', 'price', 'cost']);
        if ($unitPrice === null) {
            return 0.0;
        }

        return (float) $unitPrice * $quantity;
    }
}
