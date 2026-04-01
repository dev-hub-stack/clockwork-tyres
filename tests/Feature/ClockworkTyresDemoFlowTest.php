<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Models\Account;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use App\Modules\Procurement\Support\ProcurementInvoiceLifecycle;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockworkTyresDemoFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_realistic_demo_seed_supports_storefront_and_procurement_end_to_end(): void
    {
        $this->seed(DatabaseSeeder::class);

        $loginResponse = $this->postJson('/api/auth/business-login', [
            'email' => 'retailer.owner@clockwork.local',
            'password' => 'password',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.account_context.current_account.slug', 'clockwork-retail-demo');

        $token = (string) $loginResponse->json('data.access_token');
        $headers = $this->storefrontHeaders($token, 'clockwork-retail-demo');

        $catalogResponse = $this
            ->withHeaders($headers)
            ->getJson('/api/storefront/catalog/tyres?mode=retail-store');

        $catalogResponse
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.meta.item_count', 4)
            ->assertJsonPath('data.meta.account_slug', 'clockwork-retail-demo');

        $items = collect($catalogResponse->json('data.items'))->keyBy('brand');

        $this->assertSame('own', data_get($items, 'Michelin.availability.origin'));
        $this->assertSame(4, data_get($items, 'Michelin.availability.quantity'));
        $this->assertSame('supplier', data_get($items, 'Bridgestone.availability.origin'));
        $this->assertSame('available', data_get($items, 'Bridgestone.availability.label'));
        $this->assertSame(3, data_get($items, 'Bridgestone.availability.quantity'));

        $workspaceResponse = $this
            ->withHeaders($headers)
            ->getJson('/api/storefront/workspace');

        $workspaceResponse
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.profile.businessName', 'Desert Drift Tyres LLC');

        $seededWorkspaceOrder = collect($workspaceResponse->json('data.orders'))
            ->firstWhere('id', 'CW-DEMO-1001');

        $this->assertNotNull($seededWorkspaceOrder);
        $this->assertSame('supplier', data_get($seededWorkspaceOrder, 'lines.1.origin'));

        $createOrderResponse = $this
            ->withHeaders($headers)
            ->postJson('/api/storefront/orders', [
                'billing' => [
                    'businessName' => 'Desert Drift Tyres LLC',
                    'country' => 'United Arab Emirates',
                    'state' => 'Dubai',
                    'city' => 'Dubai',
                    'zip' => '12888',
                    'address' => 'Al Quoz Industrial Area 3',
                    'phone' => '+971500001101',
                ],
                'shipping' => [
                    'businessName' => 'Desert Drift Tyres LLC',
                    'country' => 'United Arab Emirates',
                    'state' => 'Dubai',
                    'city' => 'Dubai',
                    'zip' => '12889',
                    'address' => 'Ras Al Khor Road, Dubai',
                    'phone' => '+971500001101',
                ],
                'purchaseOrderNo' => 'CW-DEMO-PO-2201',
                'orderNotes' => 'Deliver with the afternoon fleet replenishment.',
                'deliveryOption' => 'Delivery',
                'items' => [
                    [
                        'sku' => 'DDT-PS4S-245',
                        'slug' => 'michelin-pilot-sport-4s-245-35r20-2026',
                        'title' => 'Michelin Pilot Sport 4S',
                        'size' => '245/35R20',
                        'quantity' => 2,
                        'unitPrice' => 395,
                        'origin' => 'own',
                        'availabilityLabel' => 'in stock',
                    ],
                    [
                        'sku' => 'NRT-TUR-225',
                        'slug' => 'bridgestone-turanza-t005-225-55r17-2026',
                        'title' => 'Bridgestone Turanza T005',
                        'size' => '225/55R17',
                        'quantity' => 1,
                        'unitPrice' => 276,
                        'origin' => 'supplier',
                        'availabilityLabel' => 'available',
                    ],
                ],
            ]);

        $createOrderResponse
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.order.status', 'pending');

        $createdOrderNumber = (string) $createOrderResponse->json('data.order.orderNumber');

        $workspaceAfterOrderResponse = $this
            ->withHeaders($headers)
            ->getJson('/api/storefront/workspace');

        $workspaceAfterOrderResponse->assertOk();

        $workspaceOrders = collect($workspaceAfterOrderResponse->json('data.orders'));
        $createdWorkspaceOrder = $workspaceOrders->firstWhere('id', $createdOrderNumber);

        $this->assertNotNull($createdWorkspaceOrder);
        $this->assertSame('pending', data_get($createdWorkspaceOrder, 'status'));
        $this->assertSame('supplier', data_get($createdWorkspaceOrder, 'lines.1.origin'));

        $retailerOwner = User::query()->where('email', 'retailer.owner@clockwork.local')->firstOrFail();
        $retailerAccount = Account::query()->where('slug', 'clockwork-retail-demo')->firstOrFail();
        $supplierAccount = Account::query()->where('slug', 'clockwork-supply-demo')->firstOrFail();
        $retailCustomer = Customer::query()
            ->where('account_id', $retailerAccount->id)
            ->where('external_customer_id', 'falcon-fleet-services')
            ->firstOrFail();
        $supplierWarehouse = Warehouse::query()->where('code', 'NRT-MAIN')->firstOrFail();

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailerAccount,
            actor: $retailerOwner,
            customer: $retailCustomer,
            lineItems: [[
                'supplier_id' => $supplierAccount->id,
                'supplier_name' => $supplierAccount->name,
                'sku' => 'NRT-PS4S-245',
                'product_name' => 'Michelin Pilot Sport 4S',
                'size' => '245/35R20',
                'quantity' => 4,
                'unit_price' => 350,
                'warehouse_id' => $supplierWarehouse->id,
                'source' => 'Approved supplier connection',
                'note' => 'Demo end-to-end procurement flow.',
            ]],
            notes: '[DEMO:E2E] Retail replenishment for showroom stock.',
            meta: [
                'demo_seed' => 'e2e-flow',
            ],
        );

        $request = $submission->requests()->with(['quoteOrder', 'invoiceOrder'])->firstOrFail();

        $this->assertSame(ProcurementWorkflowStage::SUBMITTED, $request->current_stage);

        $quote = $request->quoteOrder()->firstOrFail();

        $request = app(ProcurementQuoteLifecycle::class)->startSupplierReview($quote);
        $this->assertSame(ProcurementWorkflowStage::SUPPLIER_REVIEW, $request?->current_stage);

        $request = app(ProcurementQuoteLifecycle::class)->markQuoted($quote->fresh());
        $this->assertSame(ProcurementWorkflowStage::QUOTED, $request?->current_stage);

        $request = app(ApproveProcurementRequestAction::class)->execute($request->fresh());
        $this->assertSame(ProcurementWorkflowStage::STOCK_RESERVED, $request->current_stage);

        $invoice = $request->invoiceOrder()->firstOrFail();

        $invoice->update([
            'order_status' => OrderStatus::SHIPPED,
            'shipped_at' => now()->subHours(2),
        ]);

        $request = app(ProcurementInvoiceLifecycle::class)->sync($invoice->fresh());
        $this->assertSame(ProcurementWorkflowStage::STOCK_DEDUCTED, $request?->current_stage);

        $invoice->update([
            'order_status' => OrderStatus::COMPLETED,
            'delivered_at' => now()->subHour(),
        ]);

        $request = app(ProcurementInvoiceLifecycle::class)->sync($invoice->fresh());

        $this->assertSame(ProcurementWorkflowStage::FULFILLED, $request?->current_stage);
        $this->assertNotNull($request?->fulfilled_at);
    }

    /**
     * @return array<string, string>
     */
    private function storefrontHeaders(string $token, string $accountSlug): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Account-Slug' => $accountSlug,
            'Accept' => 'application/json',
        ];
    }
}
