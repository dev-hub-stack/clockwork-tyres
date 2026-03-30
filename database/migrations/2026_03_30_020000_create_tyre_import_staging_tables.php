<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tyre_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->default('tyres');
            $table->string('source_format', 10);
            $table->string('source_file_name');
            $table->unsignedBigInteger('source_file_size_bytes')->nullable();
            $table->string('source_file_hash', 64)->nullable();
            $table->string('sheet_name')->nullable();
            $table->unsignedSmallInteger('contract_version')->default(1);
            $table->string('mapping_version')->default('v1');
            $table->string('status')->default('staged');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('staged_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->json('source_headers')->nullable();
            $table->json('normalized_headers')->nullable();
            $table->json('validation_summary')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status'], 'tyre_import_batches_account_status_idx');
            $table->index(['source_format', 'status'], 'tyre_import_batches_format_status_idx');
        });

        Schema::create('tyre_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('tyre_import_batches')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->unsignedInteger('source_row_number');
            $table->string('status')->default('valid');
            $table->string('source_sku')->nullable();
            $table->string('normalized_brand')->nullable();
            $table->string('normalized_model')->nullable();
            $table->string('normalized_full_size')->nullable();
            $table->string('normalized_dot_year')->nullable();
            $table->string('storefront_merge_key', 64)->nullable();
            $table->string('source_row_hash', 64)->nullable();
            $table->string('normalized_row_hash', 64)->nullable();
            $table->foreignId('duplicate_of_row_id')->nullable()->constrained('tyre_import_rows')->nullOnDelete();
            $table->json('raw_payload');
            $table->json('normalized_payload');
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->json('dedupe_signals')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_row_number'], 'tyre_import_rows_batch_row_uq');
            $table->index(['batch_id', 'status'], 'tyre_import_rows_batch_status_idx');
            $table->index(['account_id', 'storefront_merge_key'], 'tyre_import_rows_account_merge_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tyre_import_rows');
        Schema::dropIfExists('tyre_import_batches');
    }
};
