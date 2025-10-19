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
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Tax name (e.g., "VAT", "GST", "Sales Tax")
            $table->decimal('rate', 5, 2); // Tax rate percentage (e.g., 15.00)
            $table->boolean('is_default')->default(false); // Is this the default tax rate
            $table->boolean('tax_inclusive_default')->default(true); // Default tax inclusive/exclusive
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure only one default tax setting
            $table->index('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
