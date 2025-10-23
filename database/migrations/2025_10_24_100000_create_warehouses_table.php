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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            
            // Basic Info
            $table->string('warehouse_name', 255)->comment('Auto-generated or custom name');
            $table->string('code', 50)->unique()->comment('Unique warehouse code for imports/exports');
            
            // Location
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            
            // Geolocation for distance-based fulfillment (Haversine formula)
            $table->decimal('lat', 10, 8)->nullable()->comment('Latitude');
            $table->decimal('lng', 11, 8)->nullable()->comment('Longitude');
            
            // Contact
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            
            // Status & Settings
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->boolean('is_primary')->default(false)->comment('Primary/default warehouse for allocation');
            
            // Additional Info
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('status', 'idx_warehouses_status');
            $table->index('code', 'idx_warehouses_code');
            $table->index(['lat', 'lng'], 'idx_warehouses_geolocation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
