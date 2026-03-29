<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

                $table->index(['account_type', 'status'], 'accounts_type_status_idx');
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

                $table->unique(['account_id', 'user_id'], 'account_user_account_user_uq');
                $table->index(['user_id', 'role'], 'account_user_user_role_idx');
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

                $table->unique(['retailer_account_id', 'supplier_account_id'], 'acct_conn_retailer_supplier_uq');
                $table->index(['status', 'approved_at'], 'acct_conn_status_approved_idx');
            });
        }

        if (Schema::hasTable('account_connections')) {
            Schema::table('account_connections', function (Blueprint $table) {
                if (! $this->hasIndex('account_connections', 'acct_conn_retailer_supplier_uq')) {
                    $table->unique(['retailer_account_id', 'supplier_account_id'], 'acct_conn_retailer_supplier_uq');
                }

                if (! $this->hasIndex('account_connections', 'acct_conn_status_approved_idx')) {
                    $table->index(['status', 'approved_at'], 'acct_conn_status_approved_idx');
                }
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

                $table->index(['account_id', 'status'], 'acct_sub_account_status_idx');
                $table->index(['plan_code', 'reports_enabled'], 'acct_sub_plan_reports_idx');
            });
        }

        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'account_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreignId('account_id')
                    ->nullable()
                    ->after('representative_id')
                    ->constrained('accounts')
                    ->nullOnDelete();

                $table->index(['account_id', 'customer_type'], 'customers_account_type_idx');
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

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select(sprintf("PRAGMA index_list('%s')", str_replace("'", "''", $table)));

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT 1 AS present
             FROM information_schema.statistics
             WHERE table_schema = ?
               AND table_name = ?
               AND index_name = ?
             LIMIT 1',
            [$database, $table, $indexName]
        );

        return $result !== null;
    }
};
