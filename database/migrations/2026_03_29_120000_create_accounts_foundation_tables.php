<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->enum('account_type', ['retailer', 'supplier', 'both'])->default('retailer');
                $table->boolean('retail_enabled')->default(true);
                $table->boolean('wholesale_enabled')->default(false);
                $table->string('status')->default('active');
                $table->enum('base_subscription_plan', ['basic', 'premium'])->default('basic');
                $table->boolean('reports_subscription_enabled')->default(false);
                $table->unsignedInteger('reports_customer_limit')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['account_type', 'status']);
            });
        }

        if (! Schema::hasTable('account_user')) {
            Schema::create('account_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->enum('role', ['owner', 'admin', 'staff'])->default('owner');
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['account_id', 'user_id']);
                $table->index(['user_id', 'role']);
            });
        }

        if (! Schema::hasTable('account_connections')) {
            Schema::create('account_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('retailer_account_id')->constrained('accounts')->cascadeOnDelete();
                $table->foreignId('supplier_account_id')->constrained('accounts')->cascadeOnDelete();
                $table->enum('status', ['pending', 'approved', 'rejected', 'inactive'])->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['retailer_account_id', 'supplier_account_id']);
                $table->index(['status', 'approved_at']);
            });
        }

        if (! Schema::hasTable('account_subscriptions')) {
            Schema::create('account_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->enum('plan_code', ['basic', 'premium'])->default('basic');
                $table->string('status')->default('active');
                $table->boolean('reports_enabled')->default(false);
                $table->unsignedInteger('reports_customer_limit')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('meta')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['account_id', 'status']);
                $table->index(['plan_code', 'reports_enabled'], 'account_subscriptions_plan_reports_index');
            });
        }

        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'account_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('representative_id')
                    ->constrained('accounts')
                    ->nullOnDelete();

                $table->index(['account_id', 'customer_type']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'account_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('account_id');
            });
        }

        Schema::dropIfExists('account_subscriptions');
        Schema::dropIfExists('account_connections');
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};
