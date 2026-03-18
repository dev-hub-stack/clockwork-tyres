<?php

namespace Tests\Feature;

use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\ProductInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Enums\QuoteStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Wholesale\Cart\Models\Cart;
use App\Modules\Wholesale\Cart\Models\CartItem;
use App\Services\Wholesale\StripePaymentGateway;
use App\Services\Wholesale\StripePaymentLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesaleStripePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_wholesale_stripe_payment_authorizes_order_allocates_inventory_and_clears_cart(): void
    {
        [, $token, $order, $item, $inventory, $cart] = $this->createWholesaleOrderScenario();

        app()->instance(StripePaymentGateway::class, new class extends StripePaymentGateway {
            public function createCharge(Order $order, Customer $dealer, string $stripeToken): object
            {
                return (object) ['id' => 'ch_test_wholesale_success'];
            }
        });

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payment', [
                'gateway' => 'Stripe',
                'order_id' => $order->id,
                'stripeToken' => 'tok_test_success',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.charge_id', 'ch_test_wholesale_success');

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'payment_method' => 'stripe',
            'reference_number' => 'ch_test_wholesale_success',
            'status' => 'authorized',
        ]);

        $order->refresh();
        $item->refresh();
        $inventory->refresh();
        $cart->refresh();

        $this->assertEquals(PaymentStatus::PENDING, $order->payment_status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->order_status);
        $this->assertSame(4, $item->allocated_quantity);
        $this->assertSame(6, $inventory->quantity);
        $this->assertSame(0.0, (float) $cart->total);
        $this->assertDatabaseMissing('wholesale_cart_items', ['cart_id' => $cart->id]);
    }

    public function test_capturing_authorized_stripe_payment_marks_order_paid(): void
    {
        [$dealer, $token, $order, $item, $inventory] = $this->createWholesaleOrderScenario();

        app()->instance(StripePaymentGateway::class, new class extends StripePaymentGateway {
            public function createCharge(Order $order, Customer $dealer, string $stripeToken): object
            {
                return (object) ['id' => 'ch_test_wholesale_capture'];
            }

            public function captureCharge(string $chargeId): object
            {
                return (object) ['id' => $chargeId];
            }
        });

        $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payment', [
                'gateway' => 'Stripe',
                'order_id' => $order->id,
                'stripeToken' => 'tok_test_capture',
            ])
            ->assertOk();

        $captureResult = app(StripePaymentLifecycleService::class)->captureAuthorizedPayment($order->fresh());

        $this->assertSame('captured', $captureResult['status']);

        $order->refresh();
        $item->refresh();
        $inventory->refresh();

        $this->assertEquals(PaymentStatus::PAID, $order->payment_status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->order_status);
        $this->assertSame(4, $item->allocated_quantity);
        $this->assertSame(6, $inventory->quantity);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'reference_number' => 'ch_test_wholesale_capture',
            'status' => 'completed',
        ]);
    }

    public function test_order_process_is_idempotent_after_successful_stripe_payment(): void
    {
        [, $token, $order, $item, $inventory] = $this->createWholesaleOrderScenario();

        app()->instance(StripePaymentGateway::class, new class extends StripePaymentGateway {
            public function createCharge(Order $order, Customer $dealer, string $stripeToken): object
            {
                return (object) ['id' => 'ch_test_wholesale_repeat'];
            }
        });

        $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payment', [
                'gateway' => 'Stripe',
                'order_id' => $order->id,
                'stripeToken' => 'tok_test_repeat',
            ])
            ->assertOk();

        $repeatResponse = $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/order/process?order_id=' . $order->id);

        $repeatResponse
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $order->id);

        $item->refresh();
        $inventory->refresh();

        $this->assertSame(4, $item->allocated_quantity);
        $this->assertSame(6, $inventory->quantity);
    }

    public function test_wholesale_stripe_payment_allows_non_tracked_special_order_items(): void
    {
        [, $token, $order, $item] = $this->createWholesaleOrderScenario(trackInventory: false, createInventory: false, quantity: 2, total: 200);

        app()->instance(StripePaymentGateway::class, new class extends StripePaymentGateway {
            public function createCharge(Order $order, Customer $dealer, string $stripeToken): object
            {
                return (object) ['id' => 'ch_test_special_order'];
            }
        });

        $response = $this
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/payment', [
                'gateway' => 'Stripe',
                'order_id' => $order->id,
                'stripeToken' => 'tok_test_special_order',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.charge_id', 'ch_test_special_order');

        $order->refresh();
        $item->refresh();

        $this->assertEquals(PaymentStatus::PENDING, $order->payment_status);
        $this->assertEquals(OrderStatus::PROCESSING, $order->order_status);
        $this->assertSame(2, $item->allocated_quantity);
    }

    private function createWholesaleOrderScenario(bool $trackInventory = true, bool $createInventory = true, int $quantity = 4, int $total = 400): array
    {
        $dealer = Customer::create([
            'customer_type' => 'wholesale',
            'business_name' => 'Wholesale Dealer',
            'email' => 'dealer@example.com',
            'password' => 'password',
            'phone' => '0500000000',
            'status' => 1,
        ]);

        $token = $dealer->createToken('wholesale-app')->plainTextToken;

        $warehouse = Warehouse::create([
            'warehouse_name' => 'Main Warehouse',
            'code' => 'MAIN',
            'status' => 1,
        ]);

        $product = Product::create([
            'name' => 'Test Wheel',
            'sku' => 'TEST-WHEEL',
            'price' => 100,
            'status' => true,
            'available_on_wholesale' => true,
            'track_inventory' => $trackInventory,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TEST-WHEEL-18',
            'price' => 100,
            'uae_retail_price' => 100,
        ]);

        $inventory = null;
        if ($createInventory) {
            $inventory = ProductInventory::create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'quantity' => 10,
            ]);
        }

        $cart = Cart::create([
            'session_id' => 'session-test-123',
            'dealer_id' => $dealer->id,
            'sub_total' => $total,
            'total' => $total,
        ]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $trackInventory ? $warehouse->id : null,
            'quantity' => $quantity,
            'unit_price' => 100,
            'total_price' => $total,
            'type' => 'wheel',
            'eta' => false,
        ]);

        $order = Order::create([
            'document_type' => DocumentType::QUOTE,
            'quote_status' => QuoteStatus::SENT,
            'order_status' => OrderStatus::PENDING,
            'payment_status' => PaymentStatus::PENDING,
            'customer_id' => $dealer->id,
            'warehouse_id' => $trackInventory ? $warehouse->id : null,
            'channel' => 'wholesale',
            'currency' => 'AED',
            'sub_total' => $total,
            'tax' => 0,
            'shipping' => 0,
            'discount' => 0,
            'total' => $total,
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'warehouse_id' => $trackInventory ? $warehouse->id : null,
            'sku' => $variant->sku,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => 100,
            'line_total' => $total,
        ]);

        return [$dealer, $token, $order, $item, $inventory, $cart];
    }
}