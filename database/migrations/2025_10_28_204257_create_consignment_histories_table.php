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
        Schema::create('consignment_histories', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('consignment_id')->constrained('consignments')->onDelete('cascade');
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Action Details
            $table->string('action', 50); // created, sent, delivered, sale_recorded, return_recorded, etc.
            $table->text('description'); // Human-readable description
            $table->json('metadata')->nullable(); // Additional context data
            
            $table->timestamp('created_at')->useCurrent();
            // No updated_at column - history records are immutable
            
            // Indexes
            $table->index('consignment_id');
            $table->index('performed_by');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consignment_histories');
    }
};