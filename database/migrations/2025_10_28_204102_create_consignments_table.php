<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consignments', function (Blueprint $table) {
            $table->id();
            
            // Consignment Number (unique identifier)
            $table->string('consignment_number', 50)->unique()->nullable();
            
            // Relationships
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('representative_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('warehouse_id')->constrained('warehouses')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('converted_invoice_id')->nullable()->constrained('orders')->onDelete('set null');
            
            // Status
            $table->string('status', 50)->default('draft');
            
            // Item Counts (denormalized for performance)
            $table->integer('items_sent_count')->default(0);
            $table->integer('items_sold_count')->default(0);
            $table->integer('items_returned_count')->default(0);
            
            // Financial
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            
            // Vehicle Information
            $table->string('year', 4)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('sub_model', 100)->nullable();
            
            // Dates
            $table->date('issue_date')->nullable();
            $table->date('expected_return_date')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            
            // Shipping
            $table->string('tracking_number', 100)->nullable();
            
            // Additional Info
            $table->text('notes')->nullable();
            $table->text('terms_conditions')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('customer_id');
            $table->index('representative_id');
            $table->index('warehouse_id');
            $table->index('status');
            $table->index('issue_date');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignments');
    }
};