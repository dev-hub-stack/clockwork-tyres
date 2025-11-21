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
        Schema::table('consignments', function (Blueprint $table) {
            // Value tracking fields
            $table->decimal('total_value', 10, 2)->default(0)->after('total');
            $table->decimal('invoiced_value', 10, 2)->default(0)->after('total_value');
            $table->decimal('returned_value', 10, 2)->default(0)->after('invoiced_value');
            $table->decimal('balance_value', 10, 2)->default(0)->after('returned_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consignments', function (Blueprint $table) {
            $table->dropColumn([
                'total_value',
                'invoiced_value',
                'returned_value',
                'balance_value',
            ]);
        });
    }
};
