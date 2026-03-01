<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make dealer_id nullable on wholesale_carts.
 *
 * The Angular frontend calls getCart() on app startup BEFORE the user logs in.
 * It sends a session_id but no Bearer token, so dealer is null.
 * Guest (pre-login) carts are valid — dealer_id gets back-filled when the user
 * authenticates and the cart is merged to their account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wholesale_carts', function (Blueprint $table) {
            // Drop the existing NOT-NULL foreign key constraint first,
            // then re-add it as nullable.
            $table->dropForeign(['dealer_id']);
            $table->unsignedBigInteger('dealer_id')->nullable()->change();
            $table->foreign('dealer_id')
                  ->references('id')
                  ->on('customers')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wholesale_carts', function (Blueprint $table) {
            $table->dropForeign(['dealer_id']);
            $table->unsignedBigInteger('dealer_id')->nullable(false)->change();
            $table->foreign('dealer_id')
                  ->references('id')
                  ->on('customers')
                  ->cascadeOnDelete();
        });
    }
};
