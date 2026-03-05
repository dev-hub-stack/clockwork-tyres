<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'trade_license_path')) {
                $table->string('trade_license_path')->nullable()->after('trade_license_number');
            }
            if (!Schema::hasColumn('customers', 'vat_certificate_path')) {
                $table->string('vat_certificate_path')->nullable()->after('trade_license_path');
            }
            if (!Schema::hasColumn('customers', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('vat_certificate_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['trade_license_path', 'vat_certificate_path', 'profile_image']);
        });
    }
};
