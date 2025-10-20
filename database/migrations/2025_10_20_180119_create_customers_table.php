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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            // Customer Type - CRITICAL: Activates dealer pricing when 'dealer'
            $table->enum('customer_type', ['retail', 'dealer', 'wholesale', 'corporate'])
                  ->default('retail')
                  ->comment('CRITICAL: dealer type activates pricing discounts');
            
            // Personal Information
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->string('phone', 50)->nullable();
            
            // Address Information
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            
            // Business Information (for dealers)
            $table->string('website')->nullable();
            $table->string('trade_license_number')->nullable();
            $table->string('license_no', 100)->nullable();
            $table->date('expiry')->nullable()->comment('License expiry date');
            $table->string('instagram', 100)->nullable();
            $table->string('trn', 100)->nullable()->comment('Tax Registration Number');
            
            // System Fields
            $table->unsignedBigInteger('representative_id')->nullable()->comment('Sales representative');
            $table->string('external_source', 100)->nullable();
            $table->string('external_customer_id')->nullable();
            $table->string('status', 50)->default('active');
            
            // Timestamps & Soft Deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('customer_type');
            $table->index('email');
            $table->index('representative_id');
            $table->index('status');
            
            // Foreign Keys
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
            $table->foreign('representative_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
