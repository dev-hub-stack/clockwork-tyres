<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Customers\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_submit_a_storefront_order(): void
    {
        $owner = User::query()->create([
            'name' => 'Retail Owner',
            'email' => 'owner@alpha.test',
            'password' => 'clockwork123',
        ]);

        $account = Account::query()->create([
            'name' => 'Alpha Tyres Trading',
            'slug' => 'alpha-tyres-trading',
            'account_type' => AccountType::BOTH,
            'retail_enabled' => true,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'created_by_user_id' => $owner->id,
        ]);

        $account->users()->attach($owner->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $token = $owner->createToken('clockwork-business-app')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->postJson('/api/storefront/orders', [
                'billing' => [
                    'businessName' => 'Alpha Tyres Trading',
                    'country' => 'United Arab Emirates',
                    'state' => 'Dubai',
                    'city' => 'Dubai',
                    'zip' => '11111',
                    'address' => 'Al Quoz Industrial Area 3',
                    'phone' => '+971501234567',
                ],
                'shipping' => [
                    'businessName' => 'Alpha Tyres Trading',
                    'country' => 'United Arab Emirates',
                    'state' => 'Dubai',
                    'city' => 'Dubai',
                    'zip' => '11111',
                    'address' => 'Business Bay, Dubai',
                    'phone' => '+971501234567',
                ],
                'purchaseOrderNo' => 'CW-PO-2034',
                'orderNotes' => 'Please call before delivery.',
                'deliveryOption' => 'Delivery',
                'items' => [
                    [
                        'sku' => 'CW-TYR-001',
                        'slug' => 'michelin-pilot-sport-4s-325-30r21',
                        'title' => 'Michelin Pilot Sport 4S',
                        'size' => '325/30R21',
                        'quantity' => 2,
                        'unitPrice' => 375,
                        'origin' => 'own',
                        'availabilityLabel' => 'in stock',
                    ],
                    [
                        'sku' => 'CW-TYR-003',
                        'slug' => 'continental-sportcontact-7-255-35r19',
                        'title' => 'Continental SportContact 7',
                        'size' => '255/35R19',
                        'quantity' => 1,
                        'unitPrice' => 362,
                        'origin' => 'supplier',
                        'availabilityLabel' => 'available',
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.order.status', 'pending')
            ->assertJsonPath('data.order.total', 1192.6);

        $workspaceCustomer = Customer::query()
            ->where('account_id', $account->id)
            ->where('external_source', 'business_owner_workspace')
            ->first();

        $this->assertNotNull($workspaceCustomer);
        $this->assertSame('owner@alpha.test', $workspaceCustomer->email);
        $this->assertSame(2, $workspaceCustomer->addresses()->count());

        $this->assertDatabaseHas('orders', [
            'customer_id' => $workspaceCustomer->id,
            'channel' => 'retail-storefront',
            'external_source' => 'clockwork_tyres_storefront',
            'payment_method' => 'in_store',
            'delivery_options' => 'Delivery',
        ]);

        $workspaceResponse = $this
            ->withToken($token)
            ->getJson('/api/storefront/workspace');

        $workspaceResponse
            ->assertOk()
            ->assertJsonPath('data.orders.0.status', 'pending')
            ->assertJsonPath('data.orders.0.shipping.address', 'Business Bay, Dubai')
            ->assertJsonPath('data.orders.0.lines.1.origin', 'supplier');
    }

    public function test_supplier_only_account_cannot_submit_storefront_order(): void
    {
        $owner = User::query()->create([
            'name' => 'Wholesale Owner',
            'email' => 'owner@supplier.test',
            'password' => 'clockwork123',
        ]);

        $account = Account::query()->create([
            'name' => 'Supplier Only Trading',
            'slug' => 'supplier-only-trading',
            'account_type' => AccountType::SUPPLIER,
            'retail_enabled' => false,
            'wholesale_enabled' => true,
            'status' => AccountStatus::ACTIVE,
            'created_by_user_id' => $owner->id,
        ]);

        $account->users()->attach($owner->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        $token = $owner->createToken('clockwork-business-app')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/storefront/orders', [
                'billing' => [
                    'businessName' => 'Supplier Only Trading',
                    'country' => 'United Arab Emirates',
                    'state' => 'Dubai',
                    'city' => 'Dubai',
                    'zip' => '11111',
                    'address' => 'Al Quoz Industrial Area 3',
                    'phone' => '+971501234567',
                ],
                'shipping' => [
                    'businessName' => 'Supplier Only Trading',
                    'country' => 'United Arab Emirates',
                    'state' => 'Dubai',
                    'city' => 'Dubai',
                    'zip' => '11111',
                    'address' => 'Al Quoz Industrial Area 3',
                    'phone' => '+971501234567',
                ],
                'deliveryOption' => 'Delivery',
                'items' => [
                    [
                        'sku' => 'CW-TYR-001',
                        'slug' => 'michelin-pilot-sport-4s-325-30r21',
                        'title' => 'Michelin Pilot Sport 4S',
                        'size' => '325/30R21',
                        'quantity' => 1,
                        'unitPrice' => 375,
                        'origin' => 'own',
                    ],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'This account does not have retail storefront checkout enabled.');
    }
}
