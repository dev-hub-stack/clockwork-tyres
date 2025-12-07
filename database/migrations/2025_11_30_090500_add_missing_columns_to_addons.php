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
            $table->string('size')->nullable()->after('wholesale_price');
            $table->string('unit')->nullable()->after('size');
            $table->string('vehicle')->nullable()->after('lug_bolt_diameter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn(['size', 'unit', 'vehicle']);
        });
    }
};
