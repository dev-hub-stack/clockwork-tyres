<?php

use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\Route;

// Dashboard API routes
Route::middleware(['web', 'auth'])->prefix('admin/dashboard')->group(function () {
    
    // Get notifications count
    Route::get('/notifications', function () {
        $count = 0;
        
        // Low stock
        if (Schema::hasTable('product_inventories')) {
            $count += DB::table('product_inventories')
                ->where('quantity', '<', DB::raw('min_stock_level'))
                ->count();
        }
        
        // Pending warranties
        if (Schema::hasTable('warranty_claims')) {
            $count += DB::table('warranty_claims')
                ->where('status', 'pending')
                ->count();
        }
        
        return response()->json(['count' => $count]);
    });
    
    // Record payment
    Route::post('/record-payment/{order}', function (Order $order) {
        $paymentAmount = floatval(request('payment_amount'));
        $paymentMethod = request('payment_method', 'cash');
        
        if ($paymentAmount <= 0) {
            return response()->json(['success' => false, 'message' => 'Invalid payment amount']);
        }
        
        $newPaidAmount = $order->paid_amount + $paymentAmount;
        
        $order->update([
            'paid_amount' => $newPaidAmount,
            'outstanding_amount' => $order->total - $newPaidAmount,
            'payment_status' => $newPaidAmount >= $order->total ? 'paid' : 'partial',
        ]);
        
        // Create payment record if table exists
        if (Schema::hasTable('payments')) {
            DB::table('payments')->insert([
                'order_id' => $order->id,
                'amount' => $paymentAmount,
                'payment_method' => $paymentMethod,
                'payment_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        return response()->json(['success' => true]);
    });
    
    // Mark order as done
    Route::post('/mark-done/{order}', function (Order $order) {
        if ($order->payment_status !== 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order must be fully paid before marking as done'
            ]);
        }
        
        $order->update([
            'order_status' => 'completed',
        ]);
        
        return response()->json(['success' => true]);
    });
});
