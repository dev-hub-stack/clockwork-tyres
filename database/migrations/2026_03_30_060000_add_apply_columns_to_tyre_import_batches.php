<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tyre_import_batches', function (Blueprint $table) {
            $table->foreignId('applied_by_user_id')->nullable()->after('uploaded_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->after('status');
            $table->json('apply_summary')->nullable()->after('validation_summary');
        });
    }

    public function down(): void
    {
        Schema::table('tyre_import_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applied_by_user_id');
            $table->dropColumn(['applied_at', 'apply_summary']);
        });
    }
};
