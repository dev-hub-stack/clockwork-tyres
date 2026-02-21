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
        Schema::table('orders', function (Blueprint $table) {
            // Speeds up InvoiceResource's default ORDER BY issue_date DESC
            // combined with the document_type scope filter
            $table->index(['document_type', 'issue_date'], 'orders_doc_type_issue_date_idx');

            // Speeds up single record lookups filtered by document_type
            $table->index(['document_type', 'id'], 'orders_doc_type_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_doc_type_issue_date_idx');
            $table->dropIndex('orders_doc_type_id_idx');
        });
    }
};
