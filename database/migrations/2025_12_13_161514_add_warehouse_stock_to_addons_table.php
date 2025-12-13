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
        Schema::table('addons', function (Blueprint $table) {
            $table->integer('wh2_california')->default(0)->after('total_quantity')->comment('Warehouse 2 California stock');
            $table->integer('wh1_chicago')->default(0)->after('wh2_california')->comment('Warehouse 1 Chicago stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn(['wh2_california', 'wh1_chicago']);
        });
    }
};
