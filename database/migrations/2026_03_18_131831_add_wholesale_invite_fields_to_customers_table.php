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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('wholesale_invite_token', 100)->nullable()->after('email_verified_at');
            $table->timestamp('wholesale_invite_expires_at')->nullable()->after('wholesale_invite_token');
            $table->timestamp('wholesale_invited_at')->nullable()->after('wholesale_invite_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['wholesale_invite_token', 'wholesale_invite_expires_at', 'wholesale_invited_at']);
        });
    }
};
