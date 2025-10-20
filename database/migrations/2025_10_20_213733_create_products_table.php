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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // External Sync
            $table->string('external_id')->nullable()->comment('ID from TunerStop Admin');
            $table->string('external_product_id')->nullable()->comment('Legacy external ID');
            $table->string('external_source', 100)->nullable();
            
            // Product Identification
            $table->string('sku', 100)->unique()->nullable();
            $table->string('name')->nullable();
            $table->string('product_full_name', 500)->nullable();
            $table->string('slug')->nullable();
            
            // Categorization (CRITICAL FOR DEALER PRICING!)
            $table->foreignId('brand_id')->constrained('brands')->onDelete('restrict');
            $table->foreignId('model_id')->constrained('models')->onDelete('restrict');
            $table->foreignId('finish_id')->constrained('finishes')->onDelete('restrict');
            $table->string('construction', 100)->nullable()->comment('Cast, Forged, Flow Formed');
            
            // Pricing
            $table->decimal('price', 10, 2)->default(0.00)->comment('Retail price (base price)');
            $table->decimal('sale_price', 10, 2)->nullable()->comment('Sale/promotional price');
            
            // Media
            $table->json('images')->nullable()->comment('Product image paths JSON array');
            
            // SEO
            $table->string('seo_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords', 500)->nullable();
            
            // Sync Management
            $table->string('sync_source', 100)->nullable();
            $table->string('sync_status', 50)->nullable()->comment('synced, pending, error, manual');
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('sync_attempted_at')->nullable();
            
            // Status & Stock
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->integer('total_quantity')->default(0)->comment('Total stock (reference only)');
            $table->integer('views')->default(0)->comment('View count');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('sku');
            $table->index('brand_id');
            $table->index('model_id');
            $table->index('finish_id');
            $table->index('status');
            $table->index('sync_status');
            $table->index(['external_id', 'external_source']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
