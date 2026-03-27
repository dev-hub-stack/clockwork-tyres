<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Addon;
use App\Models\User;
use App\Mail\RestockAvailableMail;
use App\Mail\RestockConfirmationMail;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\AddOn as WholesaleAddOn;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Wholesale\Cart\Models\Cart;
use App\Modules\Wholesale\Cart\Models\CartItem;
use App\Modules\Wholesale\Cart\Services\CartService;
use App\Modules\Orders\Services\OrderService;
use App\Services\ActivityLogService;
use App\Services\TunerstopOrderStatusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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

    public function test_search_sizes_exposes_rear_eta_quantity_for_staggered_fitments(): void
    {
        $product = Product::create([
            'name' => 'Staggered Wheel',
            'sku' => 'STAG-WHEEL',
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
        ]);

        $frontVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'STAG-FRONT',
            'bolt_pattern' => '5x114.3',
            'rim_diameter' => 20,
            'rim_width' => 9.0,
            'uae_retail_price' => 100,
        ]);

        $rearVariant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'STAG-REAR',
            'bolt_pattern' => '5x114.3',
            'rim_diameter' => 20,
            'rim_width' => 10.5,
            'uae_retail_price' => 120,
        ]);

        $warehouse = Warehouse::create([
            'warehouse_name' => 'Primary Warehouse',
            'code' => 'MAIN2',
            'status' => 1,
            'is_primary' => 1,
        ]);

        $frontVariant->inventories()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'eta_qty' => 0,
        ]);

        $rearVariant->inventories()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 0,
            'eta_qty' => 8,
            'eta' => '7-10 days',
        ]);

        $response = $this->postJson('/api/search-sizes', [
            'bolt_pattern' => '5x114.3',
            'rim_diameter' => 20,
            'rim_width' => 9.0,
            'rear_rim_diameter' => 20,
            'rear_rim_width' => 10.5,
        ]);

        $response->assertOk()->assertJsonPath('status', true);

        $data = $response->json('data');

        $this->assertCount(1, $data['front']);
        $this->assertCount(1, $data['rear']);
        $this->assertSame(4, $data['front'][0]['total_quantity']);
        $this->assertSame(0, $data['rear'][0]['total_quantity']);
        $this->assertSame(8, $data['rear'][0]['product_inventory'][0]['eta_qty']);
        $this->assertSame('7-10 days', $data['rear'][0]['product_inventory'][0]['eta']);
    }

    public function test_notify_restock_saves_authenticated_dealer_email_for_variant(): void
    {
        Mail::fake();

        $dealer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Notify Dealer',
            'email' => 'ahmad.hassan@clustox.com',
            'password' => 'password',
            'phone' => '0500000010',
            'status' => 1,
        ]);

        $token = $dealer->createToken('wholesale-notify')->plainTextToken;

        $variant = ProductVariant::create([
            'product_id' => Product::create([
                'name' => 'Notify Wheel',
                'sku' => 'NOTIFY-WHEEL',
                'price' => 100,
                'status' => true,
                'available_on_wholesale' => true,
            ])->id,
            'sku' => 'NOTIFY-WHEEL-20',
            'uae_retail_price' => 100,
            'notify_restock' => [],
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/products/notify-restock/' . $variant->id)
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'You will be notified when this item is back in stock.');

        $this->assertSame(
            ['ahmad.hassan@clustox.com'],
            $variant->fresh()->notify_restock,
        );

        Mail::assertSent(RestockConfirmationMail::class, function (RestockConfirmationMail $mail) {
            return $mail->hasTo('ahmad.hassan@clustox.com');
        });
    }

    public function test_notify_restock_does_not_duplicate_existing_email(): void
    {
        Mail::fake();

        $dealer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Notify Dealer Duplicate',
            'email' => 'ahmad.hassan@clustox.com',
            'password' => 'password',
            'phone' => '0500000011',
            'status' => 1,
        ]);

        $token = $dealer->createToken('wholesale-notify-duplicate')->plainTextToken;

        $product = Product::create([
            'name' => 'Notify Duplicate Wheel',
            'sku' => 'NOTIFY-DUPLICATE',
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'NOTIFY-DUPLICATE-20',
            'uae_retail_price' => 100,
            'notify_restock' => ['ahmad.hassan@clustox.com'],
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/products/notify-restock/' . $variant->id)
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertSame(
            ['ahmad.hassan@clustox.com'],
            $variant->fresh()->notify_restock,
        );

        Mail::assertNothingSent();
    }

    public function test_variant_waitlist_receives_back_in_stock_email_when_inventory_returns(): void
    {
        Mail::fake();

        $product = Product::create([
            'name' => 'Restock Wheel',
            'sku' => 'RESTOCK-WHEEL',
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'RESTOCK-WHEEL-20',
            'uae_retail_price' => 100,
            'notify_restock' => ['ahmad.hassan@clustox.com'],
        ]);

        $warehouse = Warehouse::create([
            'warehouse_name' => 'Restock Warehouse',
            'code' => 'RSTK',
            'status' => 1,
        ]);

        $inventory = ProductInventory::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 0,
            'eta_qty' => 0,
        ]);

        Mail::assertNothingSent();

        $inventory->update(['quantity' => 6]);

        Mail::assertSent(RestockAvailableMail::class, function (RestockAvailableMail $mail) {
            return $mail->hasTo('ahmad.hassan@clustox.com') && $mail->isEta === false;
        });

        $this->assertSame([], $variant->fresh()->notify_restock ?? []);
    }

    public function test_addon_notify_restock_persists_and_sends_confirmation_email(): void
    {
        Mail::fake();

        $dealer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Addon Notify Dealer',
            'email' => 'ahmad.hassan@clustox.com',
            'password' => 'password',
            'phone' => '0500000012',
            'status' => 1,
        ]);

        $token = $dealer->createToken('addon-notify')->plainTextToken;

        $categoryId = DB::table('addon_categories')->insertGetId([
            'name' => 'Wheel Accessories',
            'slug' => 'wheel-accessories',
            'order' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $addon = WholesaleAddOn::create([
            'addon_category_id' => $categoryId,
            'title' => 'Hub Ring Kit',
            'part_number' => 'HR-001',
            'price' => 50,
            'track_inventory' => true,
            'notify_restock' => [],
        ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/dealer/notify/restock-addon/' . $addon->id)
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'You will be notified when this item is back in stock.');

        $this->assertSame(['ahmad.hassan@clustox.com'], Addon::findOrFail($addon->id)->notify_restock);

        Mail::assertSent(RestockConfirmationMail::class, function (RestockConfirmationMail $mail) {
            return $mail->hasTo('ahmad.hassan@clustox.com');
        });
    }

    public function test_addon_category_listing_marks_tracked_zero_quantity_addons_out_of_stock(): void
    {
        $categoryId = DB::table('addon_categories')->insertGetId([
            'name' => 'Wheel Accessories',
            'slug' => 'wheel-accessories',
            'order' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouse = Warehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'code' => 'MAIN-ADDON',
            'status' => 1,
        ]);

        $addon = WholesaleAddOn::create([
            'addon_category_id' => $categoryId,
            'title' => 'Machined Silver 17inch - True Beadlock Ring for RRW',
            'part_number' => 'RRW-RING-SILVER-17',
            'price' => 420,
            'stock_status' => WholesaleAddOn::STOCK_IN_STOCK,
            'track_inventory' => true,
        ]);

        ProductInventory::create([
            'warehouse_id' => $warehouse->id,
            'add_on_id' => $addon->id,
            'quantity' => 0,
            'eta_qty' => 0,
        ]);

        $response = $this->getJson('/api/add-ons/wheel-accessories/get');

        $response->assertOk()->assertJsonPath('status', true);

        $listedAddon = collect($response->json('data.data'))->firstWhere('id', $addon->id);

        $this->assertNotNull($listedAddon);
        $this->assertSame(0, $listedAddon['total_quantity']);
        $this->assertSame('out_of_stock', $listedAddon['availability_status']);
        $this->assertSame('Out Of Stock', $listedAddon['availability_label']);
        $this->assertFalse($listedAddon['is_orderable']);
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

    public function test_activity_log_service_ignores_invalid_user_ids_instead_of_throwing(): void
    {
        $log = ActivityLogService::log(
            'quote_created',
            'Created quote QUO-2026-9999',
            null,
            999999,
        );

        $this->assertNull($log);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_quote_creation_by_authenticated_dealer_logs_customer_actor_not_user_actor(): void
    {
        $dealer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Dealer Actor Test',
            'email' => 'dealer-actor@example.com',
            'password' => 'password',
            'phone' => '0500000003',
            'status' => 1,
        ]);

        auth()->guard()->setUser($dealer);

        $order = Order::create([
            'document_type' => DocumentType::QUOTE,
            'customer_id' => $dealer->id,
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

        $activityLog = ActivityLog::query()
            ->where('action', 'quote_created')
            ->where('model_id', $order->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activityLog);
        $this->assertNull($activityLog->user_id);
        $this->assertSame($dealer->id, $activityLog->customer_id);
    }

    public function test_tunerstop_status_sync_service_is_disabled_by_default_and_does_not_throw(): void
    {
        config()->set('services.tunerstop.order_status_sync_enabled', false);

        $customer = Customer::create([
            'customer_type' => 'retail',
            'business_name' => 'Sync Test Customer',
            'email' => 'sync-test@example.com',
            'password' => 'password',
            'phone' => '0500000004',
            'status' => 1,
        ]);

        $order = Order::create([
            'document_type' => DocumentType::INVOICE,
            'customer_id' => $customer->id,
            'channel' => 'admin',
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
            'sub_total' => 100,
            'tax' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => 100,
        ]);

        $result = app(TunerstopOrderStatusSyncService::class)->sync($order, 'test_trigger');

        $this->assertFalse($result);
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