<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('customers', 'payment_term')) {
                $table->string('payment_term')->default('30_days')->after('status');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'payment_term')) {
                $table->string('payment_term')->default('30_days')->after('payment_gateway');
            }
        });

        Schema::table('procurement_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('procurement_requests', 'payment_term')) {
                $table->string('payment_term')->default('30_days')->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('procurement_requests', 'payment_term')) {
                $table->dropColumn('payment_term');
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'payment_term')) {
                $table->dropColumn('payment_term');
            }
        });

        Schema::table('customers', function (Blueprint $table): void {
            if (Schema::hasColumn('customers', 'payment_term')) {
                $table->dropColumn('payment_term');
            }
        });
    }
};
