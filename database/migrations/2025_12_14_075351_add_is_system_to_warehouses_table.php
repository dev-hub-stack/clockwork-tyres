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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('status');
        });

        // Seed or Update Non-Stock warehouse
        $exists = \Illuminate\Support\Facades\DB::table('warehouses')->where('code', 'NON-STOCK')->exists();
        
        if ($exists) {
            \Illuminate\Support\Facades\DB::table('warehouses')
                ->where('code', 'NON-STOCK')
                ->update(['is_system' => true]);
        } else {
            \Illuminate\Support\Facades\DB::table('warehouses')->insert([
                'warehouse_name' => 'Non-Stock',
                'code' => 'NON-STOCK',
                'status' => 1,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
