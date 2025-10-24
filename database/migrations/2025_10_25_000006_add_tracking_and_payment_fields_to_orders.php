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
            // Only add columns if they don't exist
            if (!Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('order_notes');
            }
            if (!Schema::hasColumn('orders', 'tracking_url')) {
                $table->string('tracking_url')->nullable()->after('tracking_number');
            }
            if (!Schema::hasColumn('orders', 'shipping_carrier')) {
                $table->string('shipping_carrier')->nullable()->after('tracking_url');
            }
            if (!Schema::hasColumn('orders', 'shipped_at')) {
                $table->timestamp('shipped_at')->nullable()->after('shipping_carrier');
            }
            
            // Additional Payment Fields
            if (!Schema::hasColumn('orders', 'paid_amount')) {
                $table->decimal('paid_amount', 12, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('orders', 'outstanding_amount')) {
                $table->decimal('outstanding_amount', 12, 2)->default(0)->after('paid_amount');
            }
            
            // Indexes (only if columns were added)
            if (Schema::hasColumn('orders', 'tracking_number')) {
                try {
                    $table->index('tracking_number');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
            if (Schema::hasColumn('orders', 'shipped_at')) {
                try {
                    $table->index('shipped_at');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tracking_number']);
            $table->dropIndex(['shipped_at']);
            
            $table->dropColumn([
                'tracking_number',
                'tracking_url',
                'shipping_carrier',
                'shipped_at',
                'paid_amount',
                'outstanding_amount',
            ]);
        });
    }
};
