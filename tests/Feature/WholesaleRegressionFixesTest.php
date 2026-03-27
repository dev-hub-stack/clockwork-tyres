<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Wholesale\Cart\Models\Cart;
use App\Modules\Wholesale\Cart\Models\CartItem;
use App\Modules\Wholesale\Cart\Services\CartService;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WholesaleRegressionFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_admin_track_inventory_toggle_only_updates_selected_variant(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'name' => 'Test Wheel',
            'sku' => 'TEST-WHEEL',
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
            'track_inventory' => false,
        ]);

        $variantA = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-WHEEL-A',
            'uae_retail_price' => 100,
            'track_inventory' => false,
        ]);

        $variantB = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-WHEEL-B',
            'uae_retail_price' => 100,
            'track_inventory' => false,
        ]);

        $this->actingAs($user)
            ->postJson('/admin/products/toggle-wholesale-flag', [
                'variant_id' => $variantA->id,
                'field' => 'track_inventory',
                'value' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('variant_id', $variantA->id)
            ->assertJsonPath('field', 'track_inventory')
            ->assertJsonPath('value', true);

        $this->assertTrue($variantA->fresh()->track_inventory);
        $this->assertFalse($variantB->fresh()->track_inventory);
        $this->assertFalse((bool) $product->fresh()->track_inventory);
    }

    public function test_inventory_grid_only_returns_variants_with_variant_track_inventory_enabled(): void
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'code' => 'MAIN',
            'status' => 1,
        ]);

        $product = Product::create([
            'name' => 'Inventory Wheel',
            'sku' => 'INV-WHEEL',
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
            'track_inventory' => false,
        ]);

        $trackedVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'INV-TRACKED',
            'uae_retail_price' => 100,
            'track_inventory' => true,
        ]);

        $untrackedVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'INV-UNTRACKED',
            'uae_retail_price' => 100,
            'track_inventory' => false,
        ]);

        $trackedVariant->inventories()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $untrackedVariant->inventories()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 9,
        ]);

        $response = $this->actingAs($user)->getJson('/admin/api/inventory/grid-data');

        $response->assertOk();
        $rows = $response->json();

        $this->assertCount(1, $rows);
        $this->assertSame($trackedVariant->id, $rows[0]['id']);
        $this->assertSame('INV-TRACKED', $rows[0]['sku']);
    }

    public function test_search_form_params_expands_range_bolt_patterns_into_individual_options(): void
    {
        $rangeVariant = $this->createWholesaleVariant('BP-RANGE', '5x100-5x120');
        $exactVariant = $this->createWholesaleVariant('BP-EXACT', '5x114.3');
        $anotherExactVariant = $this->createWholesaleVariant('BP-EXACT-2', '5x108');

        $response = $this->getJson('/api/search-form-params');

        $response->assertOk()->assertJsonPath('status', true);

        $boltPatterns = collect($response->json('data.bolt_pattern'))->pluck('name')->all();

        $this->assertContains('5x108', $boltPatterns);
        $this->assertContains('5x114.3', $boltPatterns);
        $this->assertNotContains('5x100-5x120', $boltPatterns);

        $this->assertNotNull($rangeVariant);
        $this->assertNotNull($exactVariant);
        $this->assertNotNull($anotherExactVariant);
    }

    public function test_search_sizes_matches_range_bolt_patterns_and_preserves_raw_display_value(): void
    {
        $variant = $this->createWholesaleVariant('BP-RANGE-MATCH', '5x100-5x120', 18, 8.5);

        $response = $this->postJson('/api/search-sizes', [
            'bolt_pattern' => '5x108',
            'rim_diameter' => 18,
            'rim_width' => 8.5,
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertSame($variant->id, $data[0]['id']);
        $this->assertSame('5x100-5x120', $data[0]['bolt_pattern']);
    }

    public function test_order_item_creation_defaults_null_discount_to_zero(): void
    {
        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Retail Customer',
            'email' => 'retail@example.com',
            'password' => 'password',
            'phone' => '0500000001',
            'status' => 1,
        ]);

        $product = Product::create([
            'name' => 'Observer Wheel',
            'sku' => 'OBS-WHEEL',
            'price' => 100,
            'status' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'OBS-WHEEL-18',
            'size' => '18x8.5',
            'bolt_pattern' => '5x114.3',
            'offset' => '35',
            'uae_retail_price' => 100,
        ]);

        $order = Order::create([
            'document_type' => DocumentType::QUOTE,
            'customer_id' => $customer->id,
            'channel' => 'wholesale',
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
            'quote_status' => 'draft',
            'sub_total' => 400,
            'tax' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 400,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 4,
            'unit_price' => 100,
            'discount' => null,
            'line_total' => null,
        ]);

        $this->assertSame(0.0, (float) $item->fresh()->discount);
    }

    public function test_wholesale_order_store_trims_vehicle_year_before_persisting(): void
    {
        $dealer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Wholesale Dealer',
            'email' => 'dealer-regression@example.com',
            'password' => 'password',
            'phone' => '0500000002',
            'status' => 1,
        ]);

        $token = $dealer->createToken('wholesale-regression')->plainTextToken;

        $product = Product::create([
            'name' => 'Checkout Wheel',
            'sku' => 'CHK-WHEEL',
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'CHK-WHEEL-18',
            'uae_retail_price' => 100,
        ]);

        $cart = Cart::create([
            'session_id' => 'vehicle-year-trim-session',
            'dealer_id' => $dealer->id,
            'sub_total' => 400,
            'discount' => 0,
            'shipping' => 0,
            'vat' => 0,
            'total' => 400,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 4,
            'unit_price' => 100,
            'total_price' => 400,
            'type' => 'wheel',
            'eta' => false,
        ]);

        $createdOrder = null;

        $cartService = Mockery::mock(CartService::class);
        $cartService->shouldReceive('getOrCreateCart')
            ->once()
            ->andReturn($cart);
        $this->app->instance(CartService::class, $cartService);

        $orderService = Mockery::mock(OrderService::class);
        $orderService->shouldReceive('createOrder')
            ->once()
            ->andReturnUsing(function (array $attributes) use ($dealer, &$createdOrder) {
                $this->assertSame($dealer->id, $attributes['customer_id']);
                $this->assertSame('2017', $attributes['vehicle_year']);

                $createdOrder = Order::withoutEvents(fn () => Order::create([
                    'document_type' => DocumentType::QUOTE,
                    'order_number' => 'TEST-QUOTE-0001',
                    'customer_id' => $dealer->id,
                    'channel' => 'wholesale',
                    'issue_date' => now(),
                    'valid_until' => now()->addDays(30),
                    'quote_status' => 'sent',
                    'vehicle_year' => $attributes['vehicle_year'],
                    'sub_total' => $attributes['sub_total'],
                    'tax' => $attributes['tax'],
                    'shipping' => $attributes['shipping'],
                    'discount' => $attributes['discount'],
                    'total' => $attributes['total'],
                ]));

                return $createdOrder;
            });

        $orderService->shouldReceive('addItem')
            ->once()
            ->andReturn(new OrderItem());

        $this->app->instance(OrderService::class, $orderService);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/order/store', [
                'session_id' => $cart->session_id,
                'vehicle_year' => ' 2017 ',
                'vehicle_make' => 'BMW',
                'vehicle_model' => 'M3',
                'billing' => [
                    'first_name' => 'Test',
                    'last_name' => 'Dealer',
                    'phone' => '0501234567',
                    'email' => $dealer->email,
                    'country' => 'AE',
                    'city' => 'Dubai',
                    'address' => 'Test address',
                ],
                'shipping' => [
                    'country' => 'AE',
                    'city' => 'Dubai',
                    'address' => 'Test address',
                ],
            ]);

        $response->assertOk()->assertJsonPath('status', true);

        $this->assertNotNull($createdOrder);
        $this->assertSame('2017', $createdOrder->fresh()->vehicle_year);
    }

    private function createWholesaleVariant(string $sku, string $boltPattern, float $diameter = 18, float $width = 8.5): ProductVariant
    {
        $product = Product::create([
            'name' => 'Bolt Pattern Product ' . $sku,
            'sku' => $sku,
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $sku,
            'bolt_pattern' => $boltPattern,
            'rim_diameter' => $diameter,
            'rim_width' => $width,
            'uae_retail_price' => 100,
        ]);
    }
}