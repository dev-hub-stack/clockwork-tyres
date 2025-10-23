<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ProductVariantGridController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\Admin\InventoryController;

Route::get('/', function () {
    return view('welcome');
});

// Product Variants Grid Routes (Tunerstop-style implementation)
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    // NOTE: Main grid view is now handled by Filament page at /admin/products-grid
    // Removed: Route::get('products/grid', ...) to avoid conflict
    
    // Batch operations (AJAX endpoints for pqGrid)
    Route::post('products/grid/save-batch', [ProductVariantGridController::class, 'saveBatch'])
        ->name('products.grid.save-batch');
    Route::post('products/grid/delete-batch', [ProductVariantGridController::class, 'deleteBatch'])
        ->name('products.grid.delete-batch');
    
    // Bulk upload operations
    Route::post('products/bulk/import', [ProductVariantGridController::class, 'bulkImport'])
        ->name('products.bulk.import');
    Route::post('products/bulk/images', [ProductVariantGridController::class, 'bulkImages'])
        ->name('products.bulk.images');
    
    // Inventory Grid Routes (pqGrid matching old Reporting system)
    Route::post('inventory/save-batch', [InventoryController::class, 'saveBatch'])
        ->name('inventory.save-batch');
    Route::post('inventory/import', [InventoryController::class, 'import'])
        ->name('inventory.import');
    
    // Product Images Routes (Tunerstop pattern)
    Route::get('products/images', [ProductImageController::class, 'index'])
        ->name('products.images.index');
    Route::get('products/images/{id}/edit', [ProductImageController::class, 'edit'])
        ->name('products.images.edit');
    Route::put('products/images/{id}', [ProductImageController::class, 'update'])
        ->name('products.images.update');
    Route::get('products/images/export', [ProductImageController::class, 'export'])
        ->name('products.images.export');
    Route::post('products/images/import', [ProductImageController::class, 'bulkImport'])
        ->name('products.images.import');
});
