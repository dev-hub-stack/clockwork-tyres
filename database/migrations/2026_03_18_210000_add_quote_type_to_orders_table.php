<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('quote_type', 30)->default('standard')->after('quote_status')->index();
        });

        DB::table('orders')
            ->where('document_type', 'quote')
            ->where('channel', 'wholesale')
            ->update([
                'quote_type' => 'abandoned_cart',
                'updated_at' => now(),
            ]);

        DB::table('orders')
            ->where('document_type', 'quote')
            ->where('channel', 'wholesale')
            ->where(function ($query) {
                $query->whereExists(function ($paymentQuery) {
                    $paymentQuery->select(DB::raw(1))
                        ->from('payments')
                        ->whereColumn('payments.order_id', 'orders.id');
                })
                    ->orWhereNotNull('payment_gateway')
                    ->orWhere('payment_method', '!=', 'pending')
                    ->orWhereIn('order_status', ['processing', 'shipped', 'completed']);
            })
            ->update([
                'quote_type' => 'confirmed_order',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['quote_type']);
            $table->dropColumn('quote_type');
        });
    }
};