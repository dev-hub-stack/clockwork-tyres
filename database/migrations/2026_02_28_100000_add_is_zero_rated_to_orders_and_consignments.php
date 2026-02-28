<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_zero_rated')->default(false)->after('tax_inclusive')
                ->comment('If true, tax is overridden to 0% regardless of the global VAT rate');
        });

        Schema::table('consignments', function (Blueprint $table) {
            $table->boolean('is_zero_rated')->default(false)->after('tax')
                ->comment('If true, tax is overridden to 0% regardless of the global VAT rate');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_zero_rated');
        });

        Schema::table('consignments', function (Blueprint $table) {
            $table->dropColumn('is_zero_rated');
        });
    }
};
