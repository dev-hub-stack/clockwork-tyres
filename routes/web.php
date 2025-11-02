<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\ProductVariantGridController;
use App\Http\Controllers\ProductImageController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\ConsignmentPdfController;
use App\Http\Controllers\WarrantyClaimPdfController;

Route::get('/', function () {
    return view('welcome');
});

// PDF Download Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/quote/{quote}/pdf', [QuotePdfController::class, 'download'])->name('quote.pdf');
    Route::get('/consignment/{consignment}/pdf', [ConsignmentPdfController::class, 'download'])->name('consignment.pdf');
    Route::get('/consignment/{consignment}/preview', [ConsignmentPdfController::class, 'preview'])->name('consignment.preview');
    Route::get('/warranty-claim/{warrantyClaim}/pdf', [WarrantyClaimPdfController::class, 'download'])->name('warranty-claim.pdf');
    Route::get('/warranty-claim/{warrantyClaim}/preview', [WarrantyClaimPdfController::class, 'preview'])->name('warranty-claim.preview');
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
    
    // Inventory API Routes (for modals)
    Route::get('api/inventory/{variant}/consignments', [\App\Http\Controllers\Api\InventoryApiController::class, 'getConsignmentsByVariant'])
        ->name('api.inventory.consignments');
    Route::get('api/inventory/{variant}/incoming', [\App\Http\Controllers\Api\InventoryApiController::class, 'getIncomingStockByVariant'])
        ->name('api.inventory.incoming');
    
    // Inventory API Routes by SKU (for inventory grid modals)
    Route::get('api/inventory/{sku}/consignments', [\App\Http\Controllers\Api\InventoryApiController::class, 'getConsignmentsBySku'])
        ->where('sku', '.*') // Allow dots and special characters in SKU
        ->name('api.inventory.consignments.bySku');
    Route::get('api/inventory/{sku}/incoming', [\App\Http\Controllers\Api\InventoryApiController::class, 'getIncomingStockBySku'])
        ->where('sku', '.*') // Allow dots and special characters in SKU
        ->name('api.inventory.incoming.bySku');
    
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
