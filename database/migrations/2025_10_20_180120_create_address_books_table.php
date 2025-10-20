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
        Schema::create('address_books', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->comment('Legacy FK');
            $table->unsignedBigInteger('dealer_id')->nullable()->comment('Legacy FK');
            
            // Address Type: 1=Billing, 2=Shipping
            $table->integer('address_type')->default(1);
            $table->string('nickname', 100)->nullable();
            
            // Contact Information
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('zip_code', 20)->nullable()->comment('Alternative zip field');
            $table->string('phone_no', 50)->nullable();
            $table->string('email')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('customer_id');
            $table->index('address_type');
            
            // Foreign Keys
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('address_books');
    }
};
