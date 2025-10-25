<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adding expense fields to orders table to match old Reporting system structure.
     * This allows direct profit calculation without JOIN queries to expenses table.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Core expense fields (from old Reporting system)
            $table->decimal('cost_of_goods', 10, 2)->nullable()->after('total')->comment('Direct product costs');
            $table->decimal('shipping_cost', 10, 2)->nullable()->comment('Freight and shipping charges');
            $table->decimal('duty_amount', 10, 2)->nullable()->comment('Import duties and customs taxes');
            $table->decimal('delivery_fee', 10, 2)->nullable()->comment('Last-mile delivery charges');
            $table->decimal('installation_cost', 10, 2)->nullable()->comment('Setup and installation fees');
            $table->decimal('bank_fee', 10, 2)->nullable()->comment('Wire transfer and banking fees');
            $table->decimal('credit_card_fee', 10, 2)->nullable()->comment('Payment processing fees');
            
            // Auto-calculated fields (updated whenever expenses are recorded)
            $table->decimal('total_expenses', 10, 2)->default(0)->comment('Sum of all expense fields');
            $table->decimal('gross_profit', 10, 2)->default(0)->comment('total - total_expenses');
            $table->decimal('profit_margin', 5, 2)->default(0)->comment('(gross_profit / total) * 100');
            
            // Audit fields
            $table->timestamp('expenses_recorded_at')->nullable()->comment('When expenses were last recorded');
            $table->foreignId('expenses_recorded_by')->nullable()->constrained('users')->nullOnDelete()->comment('User who recorded expenses');
            
            // Add index for profit reporting queries
            $table->index(['expenses_recorded_at', 'gross_profit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['expenses_recorded_by']);
            $table->dropIndex(['expenses_recorded_at', 'gross_profit']);
            
            $table->dropColumn([
                'cost_of_goods',
                'shipping_cost',
                'duty_amount',
                'delivery_fee',
                'installation_cost',
                'bank_fee',
                'credit_card_fee',
                'total_expenses',
                'gross_profit',
                'profit_margin',
                'expenses_recorded_at',
                'expenses_recorded_by',
            ]);
        });
    }
};
