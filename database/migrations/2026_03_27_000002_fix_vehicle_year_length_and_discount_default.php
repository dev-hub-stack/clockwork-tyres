<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('vehicle_year', 10)->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('vehicle_year', 4)->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->change();
        });
    }
};
