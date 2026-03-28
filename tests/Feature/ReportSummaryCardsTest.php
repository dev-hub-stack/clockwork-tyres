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

    public function test_sales_by_size_falls_back_to_variant_snapshot_when_item_attributes_size_is_missing(): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'customer_type' => 'retail',
            'business_name' => 'Size Test Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'document_type' => 'invoice',
            'order_number' => 'INV-SIZE-001',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'customer_id' => $customerId,
            'external_source' => 'retail',
            'sub_total' => 1000,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 1000,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => '2026-01-12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'sku' => 'SIZE-20X9',
            'product_name' => 'Size Test Wheel',
            'brand_name' => 'BBS',
            'model_name' => 'CH-R',
            'quantity' => 4,
            'unit_price' => 250,
            'line_total' => 1000,
            'discount' => 0,
            'tax_amount' => 0,
            'tax_inclusive' => true,
            'item_attributes' => json_encode([]),
            'variant_snapshot' => json_encode(['size' => '20x9']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ReportService::class);
        $rows = $service->salesByDimension(
            $service->sizeDimensionExpression('oi'),
            Carbon::createFromFormat('Y-m', '2026-01')->startOfMonth(),
            Carbon::createFromFormat('Y-m', '2026-01')->endOfMonth(),
        )->keyBy('label');

        $this->assertSame(4, $rows['20x9']['total_qty']);
        $this->assertSame(1000.0, $rows['20x9']['total_value']);
    }

    public function test_sales_by_vehicle_can_count_distinct_invoices_and_keep_invoice_qty_details(): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'customer_type' => 'retail',
            'business_name' => 'Vehicle Test Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstOrderId = DB::table('orders')->insertGetId([
            'document_type' => 'invoice',
            'order_number' => 'INV-VEH-001',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'customer_id' => $customerId,
            'external_source' => 'retail',
            'vehicle_make' => 'Ford',
            'vehicle_model' => 'Ranger',
            'vehicle_sub_model' => 'Wildtrak',
            'sub_total' => 5000,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 5000,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => '2026-02-02',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $secondOrderId = DB::table('orders')->insertGetId([
            'document_type' => 'invoice',
            'order_number' => 'INV-VEH-002',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'customer_id' => $customerId,
            'external_source' => 'retail',
            'vehicle_make' => 'Ford',
            'vehicle_model' => 'Ranger',
            'vehicle_sub_model' => 'Wildtrak',
            'sub_total' => 2400,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 2400,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => '2026-02-18',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $firstOrderId,
                'sku' => 'VEH-1',
                'product_name' => 'Vehicle Wheel A',
                'quantity' => 2,
                'unit_price' => 500,
                'line_total' => 1000,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $firstOrderId,
                'sku' => 'VEH-2',
                'product_name' => 'Vehicle Wheel B',
                'quantity' => 3,
                'unit_price' => 400,
                'line_total' => 1200,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $secondOrderId,
                'sku' => 'VEH-3',
                'product_name' => 'Vehicle Wheel C',
                'quantity' => 4,
                'unit_price' => 300,
                'line_total' => 1200,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $vehicleExpression = DB::getDriverName() === 'sqlite'
            ? "TRIM(COALESCE(o.vehicle_make, '') || ' ' || COALESCE(o.vehicle_model, '') || ' ' || COALESCE(o.vehicle_sub_model, ''))"
            : "CONCAT_WS(' ', NULLIF(o.vehicle_make, ''), NULLIF(o.vehicle_model, ''), NULLIF(o.vehicle_sub_model, ''))";

        $service = app(ReportService::class);
        $rows = $service->salesByDimension(
            $vehicleExpression,
            Carbon::createFromFormat('Y-m', '2026-02')->startOfMonth(),
            Carbon::createFromFormat('Y-m', '2026-02')->endOfMonth(),
            [],
            [
                'qty_aggregate' => 'invoice_count',
                'include_details' => true,
            ],
        )->keyBy('label');

        $vehicleRow = $rows['Ford Ranger Wildtrak'];

        $this->assertSame(2, $vehicleRow['total_qty']);
        $this->assertSame(3400.0, $vehicleRow['total_value']);
        $this->assertCount(2, $vehicleRow['months']['2026-02']['details']);
        $this->assertSame([5, 4], collect($vehicleRow['months']['2026-02']['details'])->pluck('qty_sold')->all());
    }

    public function test_sales_by_categories_splits_addons_into_their_real_category_names(): void
    {
        $addonCategoryId = DB::table('addon_categories')->insertGetId([
            'name' => 'Hub Rings',
            'slug' => 'hub-rings',
            'display_name' => 'Hub Rings',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $addonId = DB::table('addons')->insertGetId([
            'addon_category_id' => $addonCategoryId,
            'title' => 'Hub Ring Item',
            'part_number' => 'HUB-1',
            'price' => 100,
            'wholesale_price' => 100,
            'tax_inclusive' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $customerId = DB::table('customers')->insertGetId([
            'customer_type' => 'retail',
            'business_name' => 'Category Test Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'document_type' => 'invoice',
            'order_number' => 'INV-CAT-001',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'customer_id' => $customerId,
            'external_source' => 'retail',
            'sub_total' => 600,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 600,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => '2026-03-05',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $orderId,
                'sku' => 'WHEEL-1',
                'product_name' => 'Wheel Item',
                'add_on_id' => null,
                'quantity' => 4,
                'unit_price' => 100,
                'line_total' => 400,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'addon_snapshot' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'sku' => 'HUB-1',
                'product_name' => 'Hub Ring Item',
                'add_on_id' => $addonId,
                'quantity' => 2,
                'unit_price' => 100,
                'line_total' => 200,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'addon_snapshot' => json_encode(['category_name' => 'Hub Rings']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(ReportService::class);
        $rows = $service->salesByDimension(
            $service->categoryDimensionExpression('oi'),
            Carbon::createFromFormat('Y-m', '2026-03')->startOfMonth(),
            Carbon::createFromFormat('Y-m', '2026-03')->endOfMonth(),
        )->keyBy('label');

        $this->assertSame(4, $rows['Wheels']['total_qty']);
        $this->assertSame(2, $rows['Hub Rings']['total_qty']);
    }

    public function test_sales_by_sku_can_filter_by_brand_and_search_term(): void
    {
        $customerId = DB::table('customers')->insertGetId([
            'customer_type' => 'retail',
            'business_name' => 'SKU Filter Customer',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'document_type' => 'invoice',
            'order_number' => 'INV-SKU-001',
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'customer_id' => $customerId,
            'external_source' => 'retail',
            'sub_total' => 500,
            'tax' => 0,
            'vat' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 500,
            'currency' => 'AED',
            'tax_inclusive' => true,
            'issue_date' => '2026-03-14',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            [
                'order_id' => $orderId,
                'sku' => 'BBS-CH-R-20',
                'product_name' => 'BBS Wheel',
                'brand_name' => 'BBS',
                'quantity' => 2,
                'unit_price' => 150,
                'line_total' => 300,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => $orderId,
                'sku' => 'VOSSEN-HF5-19',
                'product_name' => 'Vossen Wheel',
                'brand_name' => 'Vossen',
                'quantity' => 1,
                'unit_price' => 200,
                'line_total' => 200,
                'discount' => 0,
                'tax_amount' => 0,
                'tax_inclusive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(ReportService::class);
        $rows = $service->salesByDimension(
            'oi.sku',
            Carbon::createFromFormat('Y-m', '2026-03')->startOfMonth(),
            Carbon::createFromFormat('Y-m', '2026-03')->endOfMonth(),
            [
                'brand' => 'BBS',
                'search' => 'CH-R',
            ],
            [
                'search_expression' => 'oi.sku',
            ],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('BBS-CH-R-20', $rows->first()['label']);
        $this->assertSame(2, $rows->first()['total_qty']);
    }
}