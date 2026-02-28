<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add authentication fields to the customers table.
     * This allows dealers (customer_type='dealer') to authenticate
     * via the Wholesale API without touching the admin users table.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Auth fields — added after 'email' column
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'email_verified_at']);
        });
    }
};
