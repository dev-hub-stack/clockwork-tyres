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
        $validated = request()->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,bank_transfer,cheque,online',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'cheque_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);
        
        $paymentAmount = floatval($validated['amount']);
        
        if ($paymentAmount > $order->outstanding_amount) {
            return response()->json(['success' => false, 'message' => 'Payment amount exceeds outstanding balance']);
        }
        
        // Create payment record using existing Payment model
        \App\Modules\Orders\Models\Payment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'recorded_by' => auth()->id(),
            'amount' => $paymentAmount,
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'],
            'reference_number' => $validated['reference_number'] ?? null,
            'bank_name' => $validated['bank_name'] ?? null,
            'cheque_number' => $validated['cheque_number'] ?? null,
            'status' => 'completed',
            'notes' => $validated['notes'] ?? null,
        ]);
        
        // Update order
        $newPaidAmount = $order->paid_amount + $paymentAmount;
        $order->update([
            'paid_amount' => $newPaidAmount,
            'outstanding_amount' => $order->total - $newPaidAmount,
            'payment_status' => $newPaidAmount >= $order->total ? 'paid' : 'partial',
        ]);
        
        return response()->json(['success' => true, 'message' => 'Payment recorded successfully']);
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
            'order_workflow_status' => 'completed',
        ]);
        
        return response()->json(['success' => true]);
    });
});
