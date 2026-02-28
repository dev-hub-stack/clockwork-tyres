<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->boolean('track_inventory')->default(false)->after('total_quantity')
                ->comment('If true, this addon has managed inventory per warehouse; deducted on sale like a product.');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('track_inventory');
        });
    }
};
