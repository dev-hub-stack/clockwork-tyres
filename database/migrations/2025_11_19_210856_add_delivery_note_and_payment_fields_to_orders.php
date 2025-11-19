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
        Schema::table('orders', function (Blueprint $table) {
            // Add delivery note field
            if (!Schema::hasColumn('orders', 'delivery_note')) {
                $table->text('delivery_note')->nullable()->after('order_notes');
            }
            
            // Add workflow status field for order lifecycle
            if (!Schema::hasColumn('orders', 'order_workflow_status')) {
                $table->enum('order_workflow_status', ['draft', 'approved', 'processing', 'shipped', 'completed', 'cancelled'])
                      ->default('draft')
                      ->after('order_status')
                      ->comment('Order workflow: draft → approved → processing → shipped → completed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_note')) {
                $table->dropColumn('delivery_note');
            }
            if (Schema::hasColumn('orders', 'order_workflow_status')) {
                $table->dropColumn('order_workflow_status');
            }
        });
    }
};
