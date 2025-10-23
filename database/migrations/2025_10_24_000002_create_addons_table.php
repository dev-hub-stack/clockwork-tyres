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
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            
            // Category relationship
            $table->foreignId('addon_category_id')
                  ->constrained('addon_categories')
                  ->cascadeOnDelete();
            
            // Basic fields
            $table->string('title', 180);
            $table->string('part_number')->nullable();
            $table->text('description')->nullable();
            
            // Pricing
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->boolean('tax_inclusive')->default(false);
            
            // Images
            $table->string('image_1')->nullable();
            $table->string('image_2')->nullable();
            
            // Inventory
            $table->integer('stock_status')->default(1);
            $table->integer('total_quantity')->default(0);
            
            // Category-specific technical fields
            // Used by different categories based on their requirements
            $table->string('bolt_pattern')->nullable();
            $table->string('width')->nullable();
            $table->string('thread_size')->nullable();
            $table->string('thread_length')->nullable();
            $table->string('ext_center_bore')->nullable();
            $table->string('center_bore')->nullable();
            $table->string('color')->nullable();
            $table->string('lug_nut_length')->nullable();
            $table->string('lug_nut_diameter')->nullable();
            $table->string('lug_bolt_diameter')->nullable();
            
            // Restock notifications (JSON array of customer IDs)
            $table->json('notify_restock')->nullable();
            
            // Audit fields
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('addon_category_id');
            $table->index('part_number');
            $table->index('stock_status');
            $table->index(['addon_category_id', 'part_number']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
