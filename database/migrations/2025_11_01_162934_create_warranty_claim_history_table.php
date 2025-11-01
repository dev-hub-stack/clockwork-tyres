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
        Schema::create('warranty_claim_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warranty_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            
            $table->string('action_type'); // ClaimActionType enum
            $table->text('description');
            $table->json('metadata')->nullable(); // For video URLs, file paths, etc.
            
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['warranty_claim_id', 'created_at']);
            $table->index('action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranty_claim_history');
    }
};
