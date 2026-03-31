<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Customers\Models\Customer;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontWorkspaceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_load_workspace_profile_addresses_and_orders(): void
    {
        $owner = User::query()->create([
            'name' => 'George Ahmad',
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
            'base_subscription_plan' => SubscriptionPlan::PREMIUM,
            'created_by_user_id' => $owner->id,
        ]);

        $account->users()->attach($owner->id, [
            'role' => AccountRole::OWNER->value,
            'is_default' => true,
        ]);

        AccountSubscription::query()->create([
            'account_id' => $account->id,
            'plan_code' => SubscriptionPlan::PREMIUM,
            'status' => 'active',
            'reports_enabled' => true,
            'reports_customer_limit' => 250,
            'starts_at' => now(),
            'created_by_user_id' => $owner->id,
        ]);

        $workspaceCustomer = Customer::query()->create([
            'customer_type' => 'dealer',
            'business_name' => 'Alpha Tyres Trading',
            'email' => 'orders@alpha.test',
            'phone' => '+971501234567',
            'website' => 'https://alpha.test',
            'trade_license_number' => 'TL-12345',
            'expiry' => '2027-12-31',
            'instagram' => '@alphatyres',
            'account_id' => $account->id,
            'external_source' => 'business_owner_workspace',
            'external_customer_id' => 'account-'.$account->id,
            'status' => 'active',
        ]);

        $workspaceCustomer->addresses()->create([
            'address_type' => 1,
            'nickname' => 'Warehouse Office',
            'address' => 'Al Quoz Industrial Area 3',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'country' => 'United Arab Emirates',
            'zip' => '11111',
            'phone_no' => '+971501234567',
        ]);

        $order = Order::query()->create([
            'document_type' => DocumentType::ORDER,
            'order_number' => 'CW-1001',
            'order_status' => OrderStatus::PROCESSING,
            'payment_status' => PaymentStatus::PENDING,
            'customer_id' => $workspaceCustomer->id,
            'sub_total' => 750,
            'shipping' => 25,
            'vat' => 38,
            'total' => 813,
            'currency' => 'AED',
            'issue_date' => '2026-03-28',
        ]);

        $order->items()->create([
            'product_name' => 'Michelin Pilot Sport 4S',
            'sku' => 'CW-TYR-001',
            'item_attributes' => ['size' => '325/30R21'],
            'quantity' => 2,
            'unit_price' => 375,
            'line_total' => 750,
        ]);

        $token = $owner->createToken('clockwork-business-app')->plainTextToken;

        $response = $this
            ->withToken($token)
            ->getJson('/api/storefront/workspace');

        $response
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.profile.businessName', 'Alpha Tyres Trading')
            ->assertJsonPath('data.profile.email', 'orders@alpha.test')
            ->assertJsonPath('data.profile.accountType', 'both')
            ->assertJsonPath('data.profile.subscription', 'premium')
            ->assertJsonCount(1, 'data.addresses')
            ->assertJsonPath('data.addresses.0.nickname', 'Warehouse Office')
            ->assertJsonCount(1, 'data.orders')
            ->assertJsonPath('data.orders.0.id', 'CW-1001')
            ->assertJsonPath('data.orders.0.lines.0.sku', 'CW-TYR-001')
            ->assertJsonPath('data.orders.0.lines.0.size', '325/30R21');
    }
}
