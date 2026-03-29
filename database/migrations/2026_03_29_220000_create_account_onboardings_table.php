<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_onboardings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('account_mode', 20);
            $table->string('plan_preference', 20);
            $table->string('country', 120)->nullable();
            $table->string('supporting_document_path')->nullable();
            $table->string('supporting_document_name')->nullable();
            $table->string('registration_source')->nullable();
            $table->string('status')->default('completed');
            $table->boolean('accepts_terms')->default(false);
            $table->boolean('accepts_privacy')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('account_id', 'acct_onboardings_account_uq');
            $table->index(['account_mode', 'plan_preference'], 'acct_onboardings_mode_plan_idx');
            $table->index('owner_user_id', 'acct_onboardings_owner_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_onboardings');
    }
};
