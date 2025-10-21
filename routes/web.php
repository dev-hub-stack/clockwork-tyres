<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ProductVariantGridController;

Route::get('/', function () {
    return view('welcome');
});

// Product Variants Grid Routes (Tunerstop-style implementation)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // Main grid view with all data embedded
    Route::get('products/grid', [ProductVariantGridController::class, 'index'])
        ->name('products.grid');
    
    // Batch operations
    Route::post('products/grid/save-batch', [ProductVariantGridController::class, 'saveBatch'])
        ->name('products.grid.save-batch');
    Route::post('products/grid/delete-batch', [ProductVariantGridController::class, 'deleteBatch'])
        ->name('products.grid.delete-batch');
});
