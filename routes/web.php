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

// Dashboard API routes
require __DIR__ . '/dashboard.php';

// PDF Download Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/quote/{quote}/pdf', [QuotePdfController::class, 'download'])->name('quote.pdf');
    Route::get('/consignment/{consignment}/pdf', [ConsignmentPdfController::class, 'download'])->name('consignment.pdf');
    Route::get('/consignment/{consignment}/preview', [ConsignmentPdfController::class, 'preview'])->name('consignment.preview');
    Route::get('/warranty-claim/{warrantyClaim}/pdf', [WarrantyClaimPdfController::class, 'download'])->name('warranty-claim.pdf');
    Route::get('/warranty-claim/{warrantyClaim}/preview', [WarrantyClaimPdfController::class, 'preview'])->name('warranty-claim.preview');
    
    // Dashboard Order Routes - Delivery Note & Invoice Downloads
    Route::get('/admin/orders/{order}/delivery-note', [App\Http\Controllers\OrderController::class, 'deliveryNote'])
        ->name('orders.delivery-note');
    Route::get('/admin/orders/{order}/invoice', [App\Http\Controllers\OrderController::class, 'invoice'])
        ->name('orders.invoice');
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
    Route::post('products/toggle-wholesale-flag', [ProductVariantGridController::class, 'toggleWholesaleFlag'])
        ->name('products.toggle-wholesale-flag');
    Route::post('products/bulk-toggle-wholesale-flag', [ProductVariantGridController::class, 'bulkToggleWholesaleFlag'])
        ->name('products.bulk-toggle-wholesale-flag');
    
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
    Route::post('inventory/bulk-transfer', [InventoryController::class, 'bulkTransfer'])
        ->name('inventory.bulk-transfer');
    Route::get('inventory/template', [InventoryController::class, 'downloadTemplate'])
        ->name('inventory.template');
    Route::get('inventory/export-csv', [InventoryController::class, 'exportCsv'])
        ->name('inventory.export-csv');
    // Grid data JSON endpoint — loaded via AJAX to avoid Livewire snapshot serialization
    Route::get('api/inventory/grid-data', [\App\Http\Controllers\Api\InventoryApiController::class, 'gridData'])
        ->name('api.inventory.grid-data');
    
    // Inventory API Routes by SKU (for inventory grid modals) - MUST BE FIRST!
    Route::get('api/inventory/sku/{sku}/consignments', [\App\Http\Controllers\Api\InventoryApiController::class, 'getConsignmentsBySku'])
        ->name('api.inventory.consignments.bySku');
    Route::get('api/inventory/sku/{sku}/incoming', [\App\Http\Controllers\Api\InventoryApiController::class, 'getIncomingStockBySku'])
        ->name('api.inventory.incoming.bySku');
    Route::get('api/inventory/sku/{sku}/damaged', [\App\Http\Controllers\Api\InventoryApiController::class, 'getDamagedStockBySku'])
        ->name('api.inventory.damaged.bySku');
    
    // Inventory API Routes by Variant ID (for modals)
    Route::get('api/inventory/{variant}/consignments', [\App\Http\Controllers\Api\InventoryApiController::class, 'getConsignmentsByVariant'])
        ->name('api.inventory.consignments');
    Route::get('api/inventory/{variant}/incoming', [\App\Http\Controllers\Api\InventoryApiController::class, 'getIncomingStockByVariant'])
        ->name('api.inventory.incoming');
    
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
