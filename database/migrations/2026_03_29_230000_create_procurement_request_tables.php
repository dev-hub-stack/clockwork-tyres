<?php

use App\Modules\Procurement\Enums\ProcurementWorkflowStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_connections') && ! Schema::hasColumn('account_connections', 'supplier_customer_id')) {
            Schema::table('account_connections', function (Blueprint $table): void {
                $table->foreignId('supplier_customer_id')
                    ->nullable()
                    ->after('supplier_account_id')
                    ->constrained('customers')
                    ->nullOnDelete();

                $table->index(['supplier_account_id', 'supplier_customer_id'], 'acct_conn_supplier_customer_idx');
            });
        }

        Schema::create('procurement_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('submission_number')->nullable()->unique();
            $table->foreignId('retailer_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default(ProcurementWorkflowStage::SUBMITTED->value);
            $table->unsignedInteger('supplier_count')->default(0);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('line_item_count')->default(0);
            $table->unsignedInteger('quantity_total')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('currency', 3)->default('AED');
            $table->string('source', 50)->default('admin_workbench');
            $table->json('meta')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['retailer_account_id', 'status'], 'proc_sub_retailer_status_idx');
            $table->index(['submitted_by_user_id', 'submitted_at'], 'proc_sub_actor_submitted_idx');
        });

        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->nullable()->unique();
            $table->foreignId('procurement_submission_id')->constrained('procurement_submissions')->cascadeOnDelete();
            $table->foreignId('retailer_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('supplier_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('account_connection_id')->nullable()->constrained('account_connections')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('quote_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('invoice_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('current_stage')->default(ProcurementWorkflowStage::SUBMITTED->value);
            $table->unsignedInteger('line_item_count')->default(0);
            $table->unsignedInteger('quantity_total')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('currency', 3)->default('AED');
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('supplier_reviewed_at')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['retailer_account_id', 'current_stage'], 'proc_req_retailer_stage_idx');
            $table->index(['supplier_account_id', 'current_stage'], 'proc_req_supplier_stage_idx');
            $table->index(['procurement_submission_id', 'supplier_account_id'], 'proc_req_submission_supplier_idx');
        });

        Schema::create('procurement_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->string('sku')->nullable();
            $table->string('product_name');
            $table->string('size')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('status', 50)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['procurement_request_id', 'sku'], 'proc_req_item_request_sku_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_request_items');
        Schema::dropIfExists('procurement_requests');
        Schema::dropIfExists('procurement_submissions');

        if (Schema::hasTable('account_connections') && Schema::hasColumn('account_connections', 'supplier_customer_id')) {
            Schema::table('account_connections', function (Blueprint $table): void {
                $table->dropIndex('acct_conn_supplier_customer_idx');
                $table->dropConstrainedForeignId('supplier_customer_id');
            });
        }
    }
};
