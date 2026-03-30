<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tyre_catalog_groups', function (Blueprint $table) {
            $table->id();
            $table->string('storefront_merge_key', 64)->unique();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('brand_name');
            $table->foreignId('model_id')->nullable()->constrained('models')->nullOnDelete();
            $table->string('model_name');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('rim_size')->nullable();
            $table->string('full_size');
            $table->string('load_index')->nullable();
            $table->string('speed_rating', 16)->nullable();
            $table->string('dot_year', 32)->nullable();
            $table->string('country')->nullable();
            $table->string('tyre_type')->nullable();
            $table->boolean('runflat')->nullable();
            $table->boolean('rfid')->nullable();
            $table->string('sidewall')->nullable();
            $table->string('warranty')->nullable();
            $table->json('reference_resolution')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['brand_name', 'model_name']);
            $table->index(['full_size', 'dot_year']);
        });

        Schema::create('tyre_account_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tyre_catalog_group_id')->constrained('tyre_catalog_groups')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('source_batch_id')->nullable()->constrained('tyre_import_batches')->nullOnDelete();
            $table->foreignId('source_row_id')->nullable()->constrained('tyre_import_rows')->nullOnDelete();
            $table->string('source_sku');
            $table->decimal('retail_price', 12, 2)->nullable();
            $table->decimal('wholesale_price_lvl1', 12, 2)->nullable();
            $table->decimal('wholesale_price_lvl2', 12, 2)->nullable();
            $table->decimal('wholesale_price_lvl3', 12, 2)->nullable();
            $table->string('brand_image')->nullable();
            $table->string('product_image_1')->nullable();
            $table->string('product_image_2')->nullable();
            $table->string('product_image_3')->nullable();
            $table->string('media_status', 64)->default('blocked_storage_resolution');
            $table->string('inventory_status', 64)->default('blocked_warehouse_mapping');
            $table->json('offer_payload')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'source_sku']);
            $table->index(['account_id', 'tyre_catalog_group_id'], 'tyre_account_offers_account_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tyre_account_offers');
        Schema::dropIfExists('tyre_catalog_groups');
    }
};
