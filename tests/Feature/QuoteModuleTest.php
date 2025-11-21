<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use App\Modules\Customers\Models\Customer;
use App\Modules\Products\Models\ProductVariant;
use App\Modules\Settings\Models\TaxSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class QuoteModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create();
        
        // Create default tax setting
        TaxSetting::create([
            'name' => 'UAE VAT',
            'rate' => 5,
            'is_default' => true,
            'tax_inclusive_default' => true,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_quote_with_channel_field()
    {
        $customer = Customer::factory()->create();
        
        $quote = Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'channel' => 'retail',
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
            'quote_status' => 'draft',
            'tax_type' => 'standard',
            'tax_inclusive' => true,
            'sub_total' => 1000,
            'vat' => 50,
            'total' => 1050,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $quote->id,
            'channel' => 'retail',
            'tax_type' => 'standard',
            'tax_inclusive' => true,
        ]);
    }

    /** @test */
    public function it_can_create_wholesale_quote()
    {
        $customer = Customer::factory()->create();
        
        $quote = Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'channel' => 'wholesale',
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
            'quote_status' => 'draft',
            'tax_type' => 'standard',
            'tax_inclusive' => false,
            'sub_total' => 1000,
            'vat' => 50,
            'total' => 1050,
        ]);

        $this->assertEquals('wholesale', $quote->channel);
    }

    /** @test */
    public function it_can_create_line_item_with_category()
    {
        $customer = Customer::factory()->create();
        $variant = ProductVariant::factory()->create();
        
        $quote = Order::create([
            'document_type' => 'quote',
            'customer_id' => $customer->id,
            'channel' => 'retail',
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
            'quote_status' => 'draft',
        ]);

        $item = OrderItem::create([
            'order_id' => $quote->id,
            'product_variant_id' => $variant->id,
            'category' => 'wheel',
            'item_attributes' => [
                'brand' => 'Test Brand',
                'model' => 'Test Model',
                'size' => '18x8.5',
                'bolt_pattern' => '5x114.3',
            ],
            'quantity' => 4,
            'unit_price' => 250,
            'line_total' => 1000,
        ]);

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'category' => 'wheel',
        ]);

        $this->assertEquals('Test Brand', $item->item_attributes['brand']);
        $this->assertEquals('5x114.3', $item->item_attributes['bolt_pattern']);
    }

    /** @test */
    public function it_uses_tax_setting_defaults()
    {
        $taxSetting = TaxSetting::getDefault();
        
        $this->assertNotNull($taxSetting);
        $this->assertEquals(5, $taxSetting->rate);
        $this->assertTrue($taxSetting->tax_inclusive_default);
    }
}
