<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('account_mode', 20);
            $table->string('plan_code', 20);
            $table->string('display_name');
            $table->string('billing_mode', 20)->default('manual');
            $table->unsignedInteger('amount_minor')->default(0);
            $table->string('currency', 3)->default('AED');
            $table->string('billing_interval', 20)->nullable();
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_self_serve')->default(false);
            $table->boolean('is_manual')->default(false);
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->json('features')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['account_mode', 'plan_code'], 'sub_plan_catalog_mode_plan_uq');
        });

        DB::table('subscription_plan_catalogs')->insert([
            [
                'account_mode' => 'retailer',
                'plan_code' => 'basic',
                'display_name' => 'Starter',
                'billing_mode' => 'free',
                'amount_minor' => 0,
                'currency' => 'AED',
                'billing_interval' => null,
                'trial_days' => 0,
                'is_self_serve' => true,
                'is_manual' => false,
                'features' => json_encode([
                    'up_to_3_suppliers',
                    'live_inventory_and_ordering',
                    'unlimited_orders',
                ], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'public_price_label' => 'FREE',
                    'cta_label' => 'Sign up',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_mode' => 'retailer',
                'plan_code' => 'premium',
                'display_name' => 'Plus',
                'billing_mode' => 'stripe_subscription',
                'amount_minor' => 19900,
                'currency' => 'AED',
                'billing_interval' => 'month',
                'trial_days' => 14,
                'is_self_serve' => true,
                'is_manual' => false,
                'features' => json_encode([
                    'unlimited_suppliers',
                    'own_inventory_showcase',
                    'company_logo',
                    'store_analytics',
                ], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'public_price_label' => '199 AED/Month',
                    'cta_label' => 'Try for free',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_mode' => 'supplier',
                'plan_code' => 'basic',
                'display_name' => 'Starter',
                'billing_mode' => 'free',
                'amount_minor' => 0,
                'currency' => 'AED',
                'billing_interval' => null,
                'trial_days' => 0,
                'is_self_serve' => true,
                'is_manual' => false,
                'features' => json_encode([
                    'live_inventory_and_order_portal',
                    'unlimited_orders',
                    'inventory_and_product_management_admin',
                ], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'public_price_label' => 'FREE',
                    'cta_label' => 'Sign up',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_mode' => 'supplier',
                'plan_code' => 'premium',
                'display_name' => 'Premium',
                'billing_mode' => 'stripe_subscription',
                'amount_minor' => 19900,
                'currency' => 'AED',
                'billing_interval' => 'month',
                'trial_days' => 14,
                'is_self_serve' => true,
                'is_manual' => false,
                'features' => json_encode([
                    'retail_sales_portal',
                    'procurement_module',
                    'store_analytics',
                ], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'public_price_label' => '199 AED/Month',
                    'cta_label' => 'Try for free',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'account_mode' => 'both',
                'plan_code' => 'premium',
                'display_name' => 'Premium',
                'billing_mode' => 'stripe_subscription',
                'amount_minor' => 19900,
                'currency' => 'AED',
                'billing_interval' => 'month',
                'trial_days' => 14,
                'is_self_serve' => true,
                'is_manual' => false,
                'features' => json_encode([
                    'shared_stock',
                    'retail_and_wholesale_access',
                    'procurement_module',
                    'store_analytics',
                ], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'public_price_label' => '199 AED/Month',
                    'cta_label' => 'Try for free',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_catalogs');
    }
};
