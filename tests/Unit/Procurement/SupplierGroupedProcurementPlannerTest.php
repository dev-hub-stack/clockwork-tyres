<?php

namespace Tests\Unit\Procurement;

use App\Modules\Procurement\Support\SupplierGroupedProcurementPlanner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupplierGroupedProcurementPlannerTest extends TestCase
{
    #[Test]
    public function it_groups_cart_items_by_supplier_and_returns_child_orders(): void
    {
        $plan = SupplierGroupedProcurementPlanner::plan([
            [
                'sku' => 'TYR-A-001',
                'product_name' => 'Tyre A',
                'supplier_id' => 11,
                'supplier_name' => 'Alpha Tyres',
                'quantity' => 2,
                'unit_price' => 120,
            ],
            [
                'sku' => 'TYR-A-002',
                'product_name' => 'Tyre B',
                'supplier_id' => 11,
                'supplier_name' => 'Alpha Tyres',
                'quantity' => 1,
                'unit_price' => 150,
            ],
            [
                'sku' => 'TYR-B-001',
                'product_name' => 'Tyre C',
                'supplier_id' => 22,
                'supplier_name' => 'Bravo Tyres',
                'quantity' => 4,
                'unit_price' => 90,
            ],
        ]);

        $this->assertSame('grouped_supplier_workbench', $plan['submission_type']);
        $this->assertSame('Retailer procurement workbench', $plan['workbench_label']);
        $this->assertTrue($plan['split_per_supplier']);
        $this->assertSame('Place Order', $plan['place_order_label']);
        $this->assertSame(2, $plan['supplier_count']);
        $this->assertSame(3, $plan['line_item_count']);
        $this->assertSame(7, $plan['quantity_total']);
        $this->assertSame(750.0, $plan['subtotal']);

        $this->assertSame('Alpha Tyres', $plan['supplier_orders'][0]['supplier_name']);
        $this->assertSame(3, $plan['supplier_orders'][0]['quantity_total']);
        $this->assertSame(390.0, $plan['supplier_orders'][0]['subtotal']);
        $this->assertCount(2, $plan['supplier_orders'][0]['line_items']);

        $this->assertSame('Bravo Tyres', $plan['supplier_orders'][1]['supplier_name']);
        $this->assertSame(4, $plan['supplier_orders'][1]['quantity_total']);
        $this->assertSame(360.0, $plan['supplier_orders'][1]['subtotal']);
        $this->assertCount(1, $plan['supplier_orders'][1]['line_items']);
    }

    #[Test]
    public function it_uses_supplier_name_when_no_supplier_id_is_available(): void
    {
        $plan = SupplierGroupedProcurementPlanner::plan([
            [
                'sku' => 'TYR-C-001',
                'product_name' => 'Tyre D',
                'supplier_name' => 'Hidden Supplier',
                'qty' => 3,
                'price' => 80,
            ],
            [
                'sku' => 'TYR-C-002',
                'product_name' => 'Tyre E',
                'supplier_name' => 'Hidden Supplier',
                'qty' => 2,
                'price' => 100,
            ],
        ]);

        $this->assertSame(1, $plan['supplier_count']);
        $this->assertSame('Hidden Supplier', $plan['supplier_orders'][0]['supplier_name']);
        $this->assertSame(5, $plan['quantity_total']);
        $this->assertSame(440.0, $plan['subtotal']);
        $this->assertSame('TYR-C-001', $plan['supplier_orders'][0]['line_items'][0]['sku']);
        $this->assertSame(240.0, $plan['supplier_orders'][0]['line_items'][0]['line_total']);
    }
}
