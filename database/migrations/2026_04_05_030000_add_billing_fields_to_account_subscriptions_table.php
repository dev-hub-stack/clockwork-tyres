<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('account_subscriptions', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('starts_at');
            }

            if (! Schema::hasColumn('account_subscriptions', 'billing_resume_token')) {
                $table->string('billing_resume_token')->nullable()->after('reports_customer_limit');
                $table->unique('billing_resume_token', 'acct_sub_billing_resume_token_uq');
            }

            if (! Schema::hasColumn('account_subscriptions', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('billing_resume_token');
            }

            if (! Schema::hasColumn('account_subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            }

            if (! Schema::hasColumn('account_subscriptions', 'stripe_checkout_session_id')) {
                $table->string('stripe_checkout_session_id')->nullable()->after('stripe_subscription_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('account_subscriptions', 'stripe_checkout_session_id')) {
                $table->dropColumn('stripe_checkout_session_id');
            }

            if (Schema::hasColumn('account_subscriptions', 'stripe_subscription_id')) {
                $table->dropColumn('stripe_subscription_id');
            }

            if (Schema::hasColumn('account_subscriptions', 'stripe_customer_id')) {
                $table->dropColumn('stripe_customer_id');
            }

            if (Schema::hasColumn('account_subscriptions', 'billing_resume_token')) {
                $table->dropUnique('acct_sub_billing_resume_token_uq');
                $table->dropColumn('billing_resume_token');
            }

            if (Schema::hasColumn('account_subscriptions', 'trial_ends_at')) {
                $table->dropColumn('trial_ends_at');
            }
        });
    }
};
