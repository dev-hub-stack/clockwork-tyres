<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Accounts\Enums\AccountConnectionStatus;
use App\Modules\Accounts\Enums\AccountRole;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\SubscriptionPlan;
use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountConnection;
use App\Modules\Accounts\Models\AccountSubscription;
use App\Modules\Customers\Models\AddressBook;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\TyreOfferInventory;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Enums\DocumentType;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Enums\PaymentStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Procurement\Actions\ApproveProcurementRequestAction;
use App\Modules\Procurement\Actions\SubmitGroupedProcurementAction;
use App\Modules\Procurement\Models\ProcurementRequest;
use App\Modules\Procurement\Support\ProcurementInvoiceLifecycle;
use App\Modules\Procurement\Support\ProcurementQuoteLifecycle;
use App\Modules\Products\Models\TyreAccountOffer;
use App\Modules\Products\Models\TyreCatalogGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClockworkTyresDemoSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::query()->where('email', 'admin@clockwork.local')->first();

        if (! $adminUser) {
            return;
        }

        $retailerOwner = $this->upsertUser('Desert Drift Retail Owner', 'sheikhahmad91@gmail.com');
        $supplierOwner = $this->upsertUser('Northern Rubber Supply Owner', 'supplier.owner@clockwork-demo.test');
        $hybridOwner = $this->upsertUser('Urban Fleet Owner', 'hybrid.owner@clockwork-demo.test');

        $retailAccount = $this->upsertAccount(
            slug: 'clockwork-retail-demo',
            name: 'Desert Drift Tyres LLC',
            type: AccountType::RETAILER,
            retailEnabled: true,
            wholesaleEnabled: false,
            createdBy: $adminUser,
            plan: SubscriptionPlan::PREMIUM,
            reportsEnabled: false,
            reportsCustomerLimit: null,
        );

        $supplierAccount = $this->upsertAccount(
            slug: 'clockwork-supply-demo',
            name: 'Northern Rubber Trading LLC',
            type: AccountType::SUPPLIER,
            retailEnabled: false,
            wholesaleEnabled: true,
            createdBy: $adminUser,
            plan: SubscriptionPlan::PREMIUM,
            reportsEnabled: true,
            reportsCustomerLimit: 250,
        );

        $hybridAccount = $this->upsertAccount(
            slug: 'clockwork-shared-demo',
            name: 'Urban Fleet Wholesale & Retail LLC',
            type: AccountType::BOTH,
            retailEnabled: true,
            wholesaleEnabled: true,
            createdBy: $adminUser,
            plan: SubscriptionPlan::PREMIUM,
            reportsEnabled: true,
            reportsCustomerLimit: 500,
        );

        $this->syncMembership($adminUser, $retailAccount, true);
        $this->syncMembership($adminUser, $supplierAccount, false);
        $this->syncMembership($adminUser, $hybridAccount, false);
        $this->syncMembership($retailerOwner, $retailAccount, true);
        $this->syncMembership($supplierOwner, $supplierAccount, true);
        $this->syncMembership($hybridOwner, $hybridAccount, true);

        $this->upsertSubscription($retailAccount, $adminUser, SubscriptionPlan::PREMIUM, false, null);
        $this->upsertSubscription($supplierAccount, $adminUser, SubscriptionPlan::PREMIUM, true, 250);
        $this->upsertSubscription($hybridAccount, $adminUser, SubscriptionPlan::PREMIUM, true, 500);

        $retailWorkspaceCustomer = $this->upsertWorkspaceCustomer(
            $retailAccount,
            'sheikhahmad91@gmail.com',
            '+971500001101',
            'Trade License RD-45821'
        );
        $supplierWorkspaceCustomer = $this->upsertWorkspaceCustomer(
            $supplierAccount,
            'supplier.owner@clockwork-demo.test',
            '+971500001202',
            'Trade License NR-77214'
        );
        $hybridWorkspaceCustomer = $this->upsertWorkspaceCustomer(
            $hybridAccount,
            'hybrid.owner@clockwork-demo.test',
            '+971500001303',
            'Trade License UF-99842'
        );

        $this->upsertAddress($retailWorkspaceCustomer, 1, 'Retail HQ', 'Al Quoz Industrial Area 3', 'Dubai', 'Dubai', 'United Arab Emirates', '12888', '+971500001101');
        $this->upsertAddress($retailWorkspaceCustomer, 2, 'Retail Delivery', 'Ras Al Khor Road, Dubai', 'Dubai', 'Dubai', 'United Arab Emirates', '12889', '+971500001101');
        $this->upsertAddress($supplierWorkspaceCustomer, 1, 'Supply Office', 'Jebel Ali Free Zone', 'Dubai', 'Dubai', 'United Arab Emirates', '26261', '+971500001202');
        $this->upsertAddress($hybridWorkspaceCustomer, 1, 'Fleet HQ', 'Mussafah Industrial Area', 'Abu Dhabi', 'Abu Dhabi', 'United Arab Emirates', '45551', '+971500001303');

        $falconFleet = $this->upsertCustomer(
            account: $retailAccount,
            businessName: 'Falcon Fleet Services',
            email: 'falcon.fleet@clockwork-demo.test',
            customerType: 'dealer',
            externalSource: 'clockwork_demo_customer',
            externalCustomerId: 'falcon-fleet-services',
            phone: '+971500004401',
        );

        $this->upsertAddress($falconFleet, 1, 'Falcon Fleet Billing', 'Sheikh Zayed Road', 'Dubai', 'Dubai', 'United Arab Emirates', '21212', '+971500004401');
        $this->upsertAddress($falconFleet, 2, 'Falcon Fleet Dispatch', 'Dubai Investment Park', 'Dubai', 'Dubai', 'United Arab Emirates', '21213', '+971500004401');

        $northCoastCustomer = $this->upsertCustomer(
            account: $supplierAccount,
            businessName: $retailAccount->name,
            email: null,
            customerType: 'wholesale',
            externalSource: 'account_connection',
            externalCustomerId: (string) $retailAccount->id,
            phone: '+971500001101',
        );

        $urbanFleetCustomer = $this->upsertCustomer(
            account: $hybridAccount,
            businessName: $retailAccount->name,
            email: null,
            customerType: 'wholesale',
            externalSource: 'account_connection',
            externalCustomerId: (string) $retailAccount->id,
            phone: '+971500001101',
        );

        $northConnection = $this->upsertConnection($retailAccount, $supplierAccount, $northCoastCustomer);
        $urbanConnection = $this->upsertConnection($retailAccount, $hybridAccount, $urbanFleetCustomer);

        $retailMain = $this->upsertWarehouse('Desert Drift Main Warehouse', 'DDT-MAIN', 'Dubai', true);
        $supplierMain = $this->upsertWarehouse('Northern Rubber Main Warehouse', 'NRT-MAIN', 'Dubai', true);
        $supplierTransit = $this->upsertWarehouse('Northern Rubber Transit', 'NRT-TRANSIT', 'Dubai', false);
        $hybridMain = $this->upsertWarehouse('Urban Fleet Main Warehouse', 'UFW-MAIN', 'Abu Dhabi', true);

        [$pilotSport, $sportContact, $scorpion, $turanza] = $this->upsertTyreCatalog();

        $offers = [
            'retail_pilot' => $this->upsertOffer($pilotSport, $retailAccount, 'DDT-PS4S-245', 395, 365, 360, 355, 'michelin-pilot-sport-4s.png'),
            'supplier_pilot' => $this->upsertOffer($pilotSport, $supplierAccount, 'NRT-PS4S-245', 372, 350, 345, 340, 'michelin-pilot-sport-4s-supply.png'),
            'supplier_contact' => $this->upsertOffer($sportContact, $supplierAccount, 'NRT-SC7-255', 348, 320, 315, 310, 'continental-sportcontact-7.png'),
            'hybrid_scorpion' => $this->upsertOffer($scorpion, $hybridAccount, 'UFW-SV-235', 329, 305, 300, 295, 'pirelli-scorpion-verde.png'),
            'retail_turanza' => $this->upsertOffer($turanza, $retailAccount, 'DDT-TUR-225', 289, 270, 265, 260, 'bridgestone-turanza.png'),
            'supplier_turanza' => $this->upsertOffer($turanza, $supplierAccount, 'NRT-TUR-225', 276, 250, 245, 240, 'bridgestone-turanza-supply.png'),
        ];

        $this->upsertTyreInventory($offers['retail_pilot'], $retailMain, 4, 0);
        $this->upsertTyreInventory($offers['supplier_pilot'], $supplierMain, 12, 0);
        $this->upsertTyreInventory($offers['supplier_contact'], $supplierMain, 8, 0);
        $this->upsertTyreInventory($offers['hybrid_scorpion'], $hybridMain, 6, 2);
        $this->upsertTyreInventory($offers['retail_turanza'], $retailMain, 0, 0);
        $this->upsertTyreInventory($offers['supplier_turanza'], $supplierTransit, 3, 2);

        $this->upsertDemoStorefrontOrder(
            account: $retailAccount,
            workspaceCustomer: $retailWorkspaceCustomer,
            shippingAddress: $retailWorkspaceCustomer->addresses()->where('address_type', 2)->first(),
        );

        $this->seedProcurementScenarios(
            retailerOwner: $retailerOwner,
            retailerAccount: $retailAccount,
            retailCustomer: $falconFleet,
            supplierAccount: $supplierAccount,
            hybridAccount: $hybridAccount,
            supplierWarehouse: $supplierMain,
            hybridWarehouse: $hybridMain,
            northConnection: $northConnection,
            urbanConnection: $urbanConnection,
        );
    }

    private function upsertUser(string $name, string $email): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );
    }

    private function upsertAccount(
        string $slug,
        string $name,
        AccountType $type,
        bool $retailEnabled,
        bool $wholesaleEnabled,
        User $createdBy,
        SubscriptionPlan $plan,
        bool $reportsEnabled,
        ?int $reportsCustomerLimit,
    ): Account {
        return Account::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'account_type' => $type,
                'retail_enabled' => $retailEnabled,
                'wholesale_enabled' => $wholesaleEnabled,
                'status' => AccountStatus::ACTIVE,
                'base_subscription_plan' => $plan,
                'reports_subscription_enabled' => $reportsEnabled,
                'reports_customer_limit' => $reportsCustomerLimit,
                'created_by_user_id' => $createdBy->id,
            ],
        );
    }

    private function syncMembership(User $user, Account $account, bool $isDefault): void
    {
        $user->accounts()->syncWithoutDetaching([
            $account->id => [
                'role' => AccountRole::OWNER->value,
                'is_default' => $isDefault,
            ],
        ]);

        if ($isDefault) {
            DB::table('account_user')
                ->where('user_id', $user->id)
                ->where('account_id', '!=', $account->id)
                ->update(['is_default' => false]);
        }
    }

    private function upsertSubscription(
        Account $account,
        User $createdBy,
        SubscriptionPlan $plan,
        bool $reportsEnabled,
        ?int $reportsCustomerLimit,
    ): AccountSubscription {
        return AccountSubscription::query()->updateOrCreate(
            ['account_id' => $account->id],
            [
                'plan_code' => $plan,
                'status' => 'active',
                'reports_enabled' => $reportsEnabled,
                'reports_customer_limit' => $reportsCustomerLimit,
                'starts_at' => now()->subDays(30),
                'ends_at' => now()->addYear(),
                'meta' => [
                    'seed_source' => 'clockwork_tyres_demo',
                    'billing_cycle' => 'monthly',
                ],
                'created_by_user_id' => $createdBy->id,
            ],
        );
    }

    private function upsertWorkspaceCustomer(
        Account $account,
        string $email,
        string $phone,
        string $tradeLicenseNumber,
    ): Customer {
        return Customer::query()->updateOrCreate(
            [
                'account_id' => $account->id,
                'external_source' => 'business_owner_workspace',
            ],
            [
                'customer_type' => $account->isSupplier() && ! $account->isRetailer() ? 'wholesale' : 'dealer',
                'business_name' => $account->name,
                'email' => $email,
                'phone' => $phone,
                'website' => 'https://'.Str::slug($account->name).'.clockwork-demo.test',
                'trade_license_number' => $tradeLicenseNumber,
                'expiry' => now()->addYear()->toDateString(),
                'instagram' => '@'.Str::slug($account->name),
                'external_customer_id' => 'account-'.$account->id,
                'status' => 'active',
            ],
        );
    }

    private function upsertCustomer(
        Account $account,
        string $businessName,
        ?string $email,
        string $customerType,
        string $externalSource,
        string $externalCustomerId,
        string $phone,
    ): Customer {
        return Customer::query()->updateOrCreate(
            [
                'account_id' => $account->id,
                'external_source' => $externalSource,
                'external_customer_id' => $externalCustomerId,
            ],
            [
                'customer_type' => $customerType,
                'business_name' => $businessName,
                'email' => $email,
                'phone' => $phone,
                'status' => 'active',
            ],
        );
    }

    private function upsertAddress(
        Customer $customer,
        int $addressType,
        string $nickname,
        string $address,
        string $city,
        string $state,
        string $country,
        string $zip,
        string $phone,
    ): AddressBook {
        return AddressBook::query()->updateOrCreate(
            [
                'customer_id' => $customer->id,
                'address_type' => $addressType,
            ],
            [
                'nickname' => $nickname,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'country' => $country,
                'zip' => $zip,
                'zip_code' => $zip,
                'phone_no' => $phone,
                'email' => $customer->email,
            ],
        );
    }

    private function upsertConnection(Account $retailer, Account $supplier, Customer $supplierCustomer): AccountConnection
    {
        return AccountConnection::query()->updateOrCreate(
            [
                'retailer_account_id' => $retailer->id,
                'supplier_account_id' => $supplier->id,
            ],
            [
                'supplier_customer_id' => $supplierCustomer->id,
                'status' => AccountConnectionStatus::APPROVED,
                'approved_at' => now()->subDays(14),
                'notes' => 'Clockwork demo approved supplier link',
            ],
        );
    }

    private function upsertWarehouse(string $name, string $code, string $city, bool $isPrimary): Warehouse
    {
        return Warehouse::query()->updateOrCreate(
            ['code' => $code],
            [
                'warehouse_name' => $name,
                'address' => $city.' Industrial Zone',
                'city' => $city,
                'state' => $city,
                'country' => 'United Arab Emirates',
                'postal_code' => '00000',
                'status' => 1,
                'is_primary' => $isPrimary,
                'is_system' => false,
            ],
        );
    }

    /**
     * @return array<int, TyreCatalogGroup>
     */
    private function upsertTyreCatalog(): array
    {
        return [
            TyreCatalogGroup::query()->updateOrCreate(
                ['storefront_merge_key' => 'michelin|pilot-sport-4s|245/35R20|2026'],
                [
                    'brand_name' => 'Michelin',
                    'model_name' => 'Pilot Sport 4S',
                    'width' => 245,
                    'height' => 35,
                    'rim_size' => 20,
                    'full_size' => '245/35R20',
                    'load_index' => '95',
                    'speed_rating' => 'Y',
                    'dot_year' => '2026',
                    'country' => 'France',
                    'tyre_type' => 'Summer',
                    'runflat' => false,
                    'rfid' => true,
                    'sidewall' => 'Blackwall',
                    'warranty' => 'Manufacturer warranty',
                ],
            ),
            TyreCatalogGroup::query()->updateOrCreate(
                ['storefront_merge_key' => 'continental|sportcontact-7|255/35R19|2026'],
                [
                    'brand_name' => 'Continental',
                    'model_name' => 'SportContact 7',
                    'width' => 255,
                    'height' => 35,
                    'rim_size' => 19,
                    'full_size' => '255/35R19',
                    'load_index' => '96',
                    'speed_rating' => 'Y',
                    'dot_year' => '2026',
                    'country' => 'Germany',
                    'tyre_type' => 'Summer',
                    'runflat' => false,
                    'rfid' => false,
                    'sidewall' => 'Blackwall',
                    'warranty' => 'Road hazard cover',
                ],
            ),
            TyreCatalogGroup::query()->updateOrCreate(
                ['storefront_merge_key' => 'pirelli|scorpion-verde|235/60R18|2026'],
                [
                    'brand_name' => 'Pirelli',
                    'model_name' => 'Scorpion Verde',
                    'width' => 235,
                    'height' => 60,
                    'rim_size' => 18,
                    'full_size' => '235/60R18',
                    'load_index' => '107',
                    'speed_rating' => 'V',
                    'dot_year' => '2026',
                    'country' => 'Italy',
                    'tyre_type' => 'All Season',
                    'runflat' => false,
                    'rfid' => false,
                    'sidewall' => 'Blackwall',
                    'warranty' => 'Mileage warranty',
                ],
            ),
            TyreCatalogGroup::query()->updateOrCreate(
                ['storefront_merge_key' => 'bridgestone|turanza-t005|225/55R17|2026'],
                [
                    'brand_name' => 'Bridgestone',
                    'model_name' => 'Turanza T005',
                    'width' => 225,
                    'height' => 55,
                    'rim_size' => 17,
                    'full_size' => '225/55R17',
                    'load_index' => '101',
                    'speed_rating' => 'W',
                    'dot_year' => '2026',
                    'country' => 'Japan',
                    'tyre_type' => 'Touring',
                    'runflat' => false,
                    'rfid' => false,
                    'sidewall' => 'Blackwall',
                    'warranty' => 'Comfort warranty',
                ],
            ),
        ];
    }

    private function upsertOffer(
        TyreCatalogGroup $group,
        Account $account,
        string $sku,
        float $retailPrice,
        float $lvl1,
        float $lvl2,
        float $lvl3,
        string $image
    ): TyreAccountOffer {
        return TyreAccountOffer::query()->updateOrCreate(
            [
                'account_id' => $account->id,
                'source_sku' => $sku,
            ],
            [
                'tyre_catalog_group_id' => $group->id,
                'retail_price' => $retailPrice,
                'wholesale_price_lvl1' => $lvl1,
                'wholesale_price_lvl2' => $lvl2,
                'wholesale_price_lvl3' => $lvl3,
                'brand_image' => $image,
                'product_image_1' => $image,
                'product_image_2' => $image,
                'product_image_3' => null,
                'media_status' => 'demo_seeded',
                'inventory_status' => 'configured_out_of_stock',
                'offer_payload' => [
                    'seed_source' => 'clockwork_tyres_demo',
                    'account_name' => $account->name,
                ],
            ],
        );
    }

    private function upsertTyreInventory(TyreAccountOffer $offer, Warehouse $warehouse, int $quantity, int $etaQty): void
    {
        TyreOfferInventory::query()->updateOrCreate(
            [
                'tyre_account_offer_id' => $offer->id,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'account_id' => $offer->account_id,
                'quantity' => $quantity,
                'eta_qty' => $etaQty,
                'eta' => $etaQty > 0 ? now()->addDays(5) : null,
            ],
        );

        $offer->forceFill([
            'inventory_status' => $quantity > 0 ? 'configured_in_stock' : ($etaQty > 0 ? 'configured_eta_only' : 'configured_out_of_stock'),
        ])->save();
    }

    private function upsertDemoStorefrontOrder(Account $account, Customer $workspaceCustomer, ?AddressBook $shippingAddress): void
    {
        $order = Order::query()->updateOrCreate(
            ['order_number' => 'CW-DEMO-1001'],
            [
                'document_type' => DocumentType::ORDER,
                'order_status' => OrderStatus::PROCESSING,
                'payment_status' => PaymentStatus::PENDING,
                'payment_method' => 'in_store',
                'customer_id' => $workspaceCustomer->id,
                'external_source' => 'clockwork_tyres_demo',
                'channel' => 'retail-storefront',
                'currency' => 'AED',
                'delivery_options' => 'Delivery',
                'shipping_address_id' => $shippingAddress?->id,
                'issue_date' => now()->subDays(2),
                'sub_total' => 1105,
                'shipping' => 25,
                'vat' => 55.25,
                'tax' => 0,
                'total' => 1185.25,
                'order_notes' => '[DEMO] Showroom replenishment order seeded for workspace view.',
            ],
        );

        $order->items()->delete();

        $order->items()->createMany([
            [
                'product_name' => 'Michelin Pilot Sport 4S',
                'sku' => 'DDT-PS4S-245',
                'item_attributes' => ['size' => '245/35R20', 'origin' => 'own'],
                'quantity' => 2,
                'unit_price' => 395,
                'line_total' => 790,
            ],
            [
                'product_name' => 'Continental SportContact 7',
                'sku' => 'NRT-SC7-255',
                'item_attributes' => ['size' => '255/35R19', 'origin' => 'supplier'],
                'quantity' => 1,
                'unit_price' => 315,
                'line_total' => 315,
            ],
        ]);
    }

    private function seedProcurementScenarios(
        User $retailerOwner,
        Account $retailerAccount,
        Customer $retailCustomer,
        Account $supplierAccount,
        Account $hybridAccount,
        Warehouse $supplierWarehouse,
        Warehouse $hybridWarehouse,
        AccountConnection $northConnection,
        AccountConnection $urbanConnection,
    ): void {
        $this->seedOpenReviewProcurement($retailerOwner, $retailerAccount, $retailCustomer, $supplierAccount, $supplierWarehouse, $northConnection);
        $this->seedFulfilledProcurement($retailerOwner, $retailerAccount, $retailCustomer, $hybridAccount, $hybridWarehouse, $urbanConnection);
    }

    private function seedOpenReviewProcurement(
        User $retailerOwner,
        Account $retailerAccount,
        Customer $retailCustomer,
        Account $supplierAccount,
        Warehouse $supplierWarehouse,
        AccountConnection $connection,
    ): void {
        $existing = ProcurementRequest::query()
            ->where('retailer_account_id', $retailerAccount->id)
            ->where('supplier_account_id', $supplierAccount->id)
            ->where('notes', 'like', '[DEMO:OPEN-REVIEW]%')
            ->first();

        if ($existing) {
            return;
        }

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailerAccount,
            actor: $retailerOwner,
            customer: $retailCustomer,
            lineItems: [[
                'supplier_id' => $supplierAccount->id,
                'supplier_name' => $supplierAccount->name,
                'sku' => 'NRT-SC7-255',
                'product_name' => 'Continental SportContact 7',
                'size' => '255/35R19',
                'quantity' => 6,
                'unit_price' => 315,
                'warehouse_id' => $supplierWarehouse->id,
                'source' => 'Approved supplier connection',
                'note' => 'Customer requested staggered fitment confirmation.',
            ]],
            notes: '[DEMO:OPEN-REVIEW] Falcon Fleet requested a replenishment quote for 255/35R19 stock.',
            meta: [
                'demo_seed' => 'open-review',
                'account_connection_id' => $connection->id,
            ],
        );

        $request = $submission->requests()->with('quoteOrder')->first();

        if ($request?->quoteOrder) {
            app(ProcurementQuoteLifecycle::class)->startSupplierReview($request->quoteOrder);
        }
    }

    private function seedFulfilledProcurement(
        User $retailerOwner,
        Account $retailerAccount,
        Customer $retailCustomer,
        Account $supplierAccount,
        Warehouse $supplierWarehouse,
        AccountConnection $connection,
    ): void {
        $existing = ProcurementRequest::query()
            ->where('retailer_account_id', $retailerAccount->id)
            ->where('supplier_account_id', $supplierAccount->id)
            ->where('notes', 'like', '[DEMO:FULFILLED]%')
            ->first();

        if ($existing) {
            return;
        }

        $submission = app(SubmitGroupedProcurementAction::class)->execute(
            retailerAccount: $retailerAccount,
            actor: $retailerOwner,
            customer: $retailCustomer,
            lineItems: [[
                'supplier_id' => $supplierAccount->id,
                'supplier_name' => $supplierAccount->name,
                'sku' => 'UFW-SV-235',
                'product_name' => 'Pirelli Scorpion Verde',
                'size' => '235/60R18',
                'quantity' => 8,
                'unit_price' => 305,
                'warehouse_id' => $supplierWarehouse->id,
                'source' => 'Approved supplier connection',
                'note' => 'Urgent transfer for airport fleet SUVs.',
            ]],
            notes: '[DEMO:FULFILLED] Completed replenishment for airport fleet transfer.',
            meta: [
                'demo_seed' => 'fulfilled',
                'account_connection_id' => $connection->id,
            ],
        );

        $request = $submission->requests()->with('quoteOrder', 'invoiceOrder')->first();

        if (! $request?->quoteOrder) {
            return;
        }

        $quoteLifecycle = app(ProcurementQuoteLifecycle::class);
        $invoiceLifecycle = app(ProcurementInvoiceLifecycle::class);

        $request = $quoteLifecycle->startSupplierReview($request->quoteOrder);
        $request = $quoteLifecycle->markQuoted($request->quoteOrder->fresh());
        $request = app(ApproveProcurementRequestAction::class)->execute($request);

        $invoice = $request->invoiceOrder()->first();

        if (! $invoice) {
            return;
        }

        $invoice->update([
            'order_status' => OrderStatus::SHIPPED,
            'shipped_at' => now()->subDay(),
        ]);
        $request = $invoiceLifecycle->sync($invoice->fresh());

        $invoice->update([
            'order_status' => OrderStatus::DELIVERED,
            'delivered_at' => now()->subHours(12),
        ]);
        $request = $invoiceLifecycle->sync($invoice->fresh());

        $invoice->update([
            'order_status' => OrderStatus::COMPLETED,
        ]);
        $invoiceLifecycle->sync($invoice->fresh());
    }
}
