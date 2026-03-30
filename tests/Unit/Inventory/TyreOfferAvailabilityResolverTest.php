<?php

namespace Tests\Unit\Inventory;

use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\TyreOfferAvailabilityResolver;
use App\Modules\Products\Models\TyreAccountOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TyreOfferAvailabilityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prioritizes_own_stock_and_ignores_non_stock_warehouses(): void
    {
        $mainWarehouse = Warehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'code' => 'MAIN',
            'is_primary' => true,
        ]);

        $nonStockWarehouse = Warehouse::firstOrCreate(
            ['code' => 'NON-STOCK'],
            [
                'warehouse_name' => 'Non-Stock',
                'is_system' => true,
            ]
        );

        $ownOffer = new TyreAccountOffer(['account_id' => 10, 'source_sku' => 'OWN-001']);
        $supplierOffer = new TyreAccountOffer(['account_id' => 20, 'source_sku' => 'SUP-001']);

        $ownOffer->setRelation('inventories', new Collection([
            (new TyreOfferInventory([
                'account_id' => 10,
                'warehouse_id' => $mainWarehouse->id,
                'quantity' => 4,
                'eta_qty' => 0,
            ]))->setRelation('warehouse', $mainWarehouse),
            (new TyreOfferInventory([
                'account_id' => 10,
                'warehouse_id' => $nonStockWarehouse->id,
                'quantity' => 50,
                'eta_qty' => 0,
            ]))->setRelation('warehouse', $nonStockWarehouse),
        ]));

        $supplierOffer->setRelation('inventories', new Collection([
            (new TyreOfferInventory([
                'account_id' => 20,
                'warehouse_id' => $mainWarehouse->id,
                'quantity' => 3,
                'eta_qty' => 0,
            ]))->setRelation('warehouse', $mainWarehouse),
        ]));

        $availability = app(TyreOfferAvailabilityResolver::class)->resolve(
            collect([$ownOffer, $supplierOffer]),
            10
        );

        $this->assertSame('own', $availability['origin']);
        $this->assertSame('in stock', $availability['label']);
        $this->assertSame(4, $availability['quantity']);
        $this->assertTrue($availability['show_quantity']);
        $this->assertSame(1, $availability['supplier_count']);
    }

    public function test_it_falls_back_to_supplier_availability_when_own_stock_is_zero(): void
    {
        $warehouse = Warehouse::create([
            'warehouse_name' => 'Supplier Warehouse',
            'code' => 'SUPP',
            'is_primary' => true,
        ]);

        $ownOffer = new TyreAccountOffer(['account_id' => 10, 'source_sku' => 'OWN-001']);
        $supplierOfferA = new TyreAccountOffer(['account_id' => 20, 'source_sku' => 'SUP-001']);
        $supplierOfferB = new TyreAccountOffer(['account_id' => 21, 'source_sku' => 'SUP-002']);

        $ownOffer->setRelation('inventories', new Collection([
            (new TyreOfferInventory([
                'account_id' => 10,
                'warehouse_id' => $warehouse->id,
                'quantity' => 0,
                'eta_qty' => 2,
            ]))->setRelation('warehouse', $warehouse),
        ]));

        $supplierOfferA->setRelation('inventories', new Collection([
            (new TyreOfferInventory([
                'account_id' => 20,
                'warehouse_id' => $warehouse->id,
                'quantity' => 2,
                'eta_qty' => 0,
            ]))->setRelation('warehouse', $warehouse),
        ]));

        $supplierOfferB->setRelation('inventories', new Collection([
            (new TyreOfferInventory([
                'account_id' => 21,
                'warehouse_id' => $warehouse->id,
                'quantity' => 1,
                'eta_qty' => 0,
            ]))->setRelation('warehouse', $warehouse),
        ]));

        $availability = app(TyreOfferAvailabilityResolver::class)->resolve(
            collect([$ownOffer, $supplierOfferA, $supplierOfferB]),
            10
        );

        $this->assertSame('supplier', $availability['origin']);
        $this->assertSame('available', $availability['label']);
        $this->assertSame(3, $availability['quantity']);
        $this->assertTrue($availability['show_quantity']);
        $this->assertSame(2, $availability['supplier_count']);
    }
}
