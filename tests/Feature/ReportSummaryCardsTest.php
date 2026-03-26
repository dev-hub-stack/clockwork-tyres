<?php

namespace Tests\Feature;

use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportSummaryCardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_cards_classify_legacy_null_source_invoices_by_customer_type(): void
    {
        $retailCustomerId = DB::table('customers')->insertGetId([
            'customer_type' => 'retail',
            'business_name' => 'Retail Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wholesaleCustomerId = DB::table('customers')->insertGetId([
            'customer_type' => 'wholesale',
            'business_name' => 'Wholesale Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            [
                'document_type' => 'invoice',
                'order_number' => 'INV-TEST-RETAIL-LEGACY',
                'order_status' => 'delivered',
                'payment_status' => 'paid',
                'customer_id' => $retailCustomerId,
                'external_source' => null,
                'sub_total' => 100,
                'tax' => 0,
                'vat' => 0,
                'shipping' => 0,
                'discount' => 0,
                'total' => 100,
                'currency' => 'AED',
                'tax_inclusive' => true,
                'issue_date' => '2026-01-15',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'invoice',
                'order_number' => 'INV-TEST-WHOLESALE-LEGACY',
                'order_status' => 'delivered',
                'payment_status' => 'paid',
                'customer_id' => $wholesaleCustomerId,
                'external_source' => null,
                'sub_total' => 250,
                'tax' => 0,
                'vat' => 0,
                'shipping' => 0,
                'discount' => 0,
                'total' => 250,
                'currency' => 'AED',
                'tax_inclusive' => true,
                'issue_date' => '2026-02-10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'invoice',
                'order_number' => 'INV-TEST-RETAIL-EXPLICIT',
                'order_status' => 'delivered',
                'payment_status' => 'paid',
                'customer_id' => $retailCustomerId,
                'external_source' => 'retail',
                'sub_total' => 80,
                'tax' => 0,
                'vat' => 0,
                'shipping' => 0,
                'discount' => 0,
                'total' => 80,
                'currency' => 'AED',
                'tax_inclusive' => true,
                'issue_date' => '2026-03-05',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $productId = DB::table('products')->insertGetId([
            'name' => 'Inventory Test Product',
            'sku' => 'TEST-INV-PRODUCT-1',
            'price' => 150,
            'status' => 1,
            'track_inventory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $excludedProductId = DB::table('products')->insertGetId([
            'name' => 'Excluded Inventory Product',
            'sku' => 'TEST-INV-PRODUCT-2',
            'price' => 500,
            'status' => 1,
            'track_inventory' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'warehouse_name' => 'Main Warehouse',
            'code' => 'TEST-WH-1',
            'status' => 1,
            'is_primary' => true,
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $systemWarehouseId = DB::table('warehouses')->insertGetId([
            'warehouse_name' => 'System Warehouse',
            'code' => 'TEST-WH-SYS',
            'status' => 1,
            'is_primary' => false,
            'is_system' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'sku' => 'TEST-INV-VALUE-1',
            'product_id' => $productId,
            'cost' => null,
            'price' => null,
            'us_retail_price' => 0,
            'uae_retail_price' => 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $excludedVariantId = DB::table('product_variants')->insertGetId([
            'sku' => 'TEST-INV-VALUE-EXCLUDED',
            'product_id' => $excludedProductId,
            'cost' => null,
            'price' => null,
            'us_retail_price' => 0,
            'uae_retail_price' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('product_inventories')->insert([
            [
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'quantity' => 4,
                'eta_qty' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'warehouse_id' => $systemWarehouseId,
                'product_variant_id' => $variantId,
                'quantity' => 10,
                'eta_qty' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $excludedVariantId,
                'quantity' => 8,
                'eta_qty' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $cards = app(ReportService::class)->summaryCards(
            Carbon::createFromFormat('Y-m', '2026-01')->startOfMonth(),
            Carbon::createFromFormat('Y-m', '2026-03')->endOfMonth(),
        );

        $cardsByLabel = collect($cards)->keyBy('label');

        $this->assertSame(2, $cardsByLabel['Retail Invoices']['value']);
        $this->assertSame(1, $cardsByLabel['Wholesale Invoices']['value']);
        $this->assertSame(180.0, $cardsByLabel['Retail Sales']['value']);
        $this->assertSame(250.0, $cardsByLabel['Wholesale Sales']['value']);
        $this->assertSame(600.0, $cardsByLabel['Inventory Value']['value']);
        $this->assertSame(300.0, $cardsByLabel['Incoming Inventory Value']['value']);
    }

    public function test_sales_by_dimension_uses_legacy_channel_fallback_for_grouping_and_filters(): void
    {
        $retailCustomerId = DB::table('customers')->insertGetId([
            'customer_type' => 'retail',
            'business_name' => 'Retail Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wholesaleCustomerId = DB::table('customers')->insertGetId([
            'customer_type' => 'wholesale',
            'business_name' => 'Wholesale Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $retailOrderId = DB::table('orders')->insertGetId([
            'document_type' => 'invoice',
            'order_number' => 'INV-CHANNEL-RETAIL',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'customer_id' => $retailCustomerId,
            'external_source' => null,
            'sub_total' => 120,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 120,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => '2026-01-20',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wholesaleOrderId = DB::table('orders')->insertGetId([
            'document_type' => 'invoice',
            'order_number' => 'INV-CHANNEL-WHOLESALE',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'customer_id' => $wholesaleCustomerId,
            'external_source' => null,
            'sub_total' => 300,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 300,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => '2026-02-14',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $retailOrderId,
                'product_name' => 'Retail Item',
                'quantity' => 2,
                'unit_price' => 60,
                'line_total' => 120,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $wholesaleOrderId,
                'product_name' => 'Wholesale Item',
                'quantity' => 3,
                'unit_price' => 100,
                'line_total' => 300,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(ReportService::class);
        $allRows = $service->salesByDimension(
            $service->channelDimensionExpression('o', 'c'),
            Carbon::createFromFormat('Y-m', '2026-01')->startOfMonth(),
            Carbon::createFromFormat('Y-m', '2026-03')->endOfMonth(),
        )->keyBy('label');

        $retailRows = $service->salesByDimension(
            $service->channelDimensionExpression('o', 'c'),
            Carbon::createFromFormat('Y-m', '2026-01')->startOfMonth(),
            Carbon::createFromFormat('Y-m', '2026-03')->endOfMonth(),
            ['channel' => 'retail'],
        );

        $this->assertSame(120.0, $allRows['retail']['total_value']);
        $this->assertSame(300.0, $allRows['wholesale']['total_value']);
        $this->assertCount(1, $retailRows);
        $this->assertSame('retail', $retailRows->first()['label']);
        $this->assertSame(120.0, $retailRows->first()['total_value']);
    }
}