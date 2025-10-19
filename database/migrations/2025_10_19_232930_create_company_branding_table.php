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
        Schema::create('company_branding', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 200);
            $table->text('company_address')->nullable();
            $table->string('company_phone', 50)->nullable();
            $table->string('company_email', 100)->nullable();
            $table->string('company_website', 200)->nullable();
            $table->string('tax_registration_number', 100)->nullable(); // VAT/Tax ID
            $table->string('commercial_registration', 100)->nullable();
            
            // Logo and branding
            $table->string('logo_path')->nullable(); // Path to uploaded logo
            $table->string('primary_color', 7)->default('#1e40af'); // Hex color
            $table->string('secondary_color', 7)->default('#64748b'); // Hex color
            
            // Document settings
            $table->string('invoice_prefix', 20)->default('INV-');
            $table->string('quote_prefix', 20)->default('QUO-');
            $table->string('order_prefix', 20)->default('ORD-');
            $table->string('consignment_prefix', 20)->default('CON-');
            
            // Footer text for documents
            $table->text('invoice_footer')->nullable();
            $table->text('quote_footer')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_branding');
    }
};
