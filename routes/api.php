<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductSyncController;
use App\Http\Controllers\Api\AddonCategorySyncController;
use App\Http\Controllers\Api\AddonSyncController;
use App\Http\Controllers\Api\AccountContextController;
use App\Http\Controllers\Wholesale\StorefrontTyreCatalogController;
use App\Http\Controllers\Wholesale\StorefrontWorkspaceController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/webhooks/products/sync', [ProductSyncController::class, 'sync']);
Route::post('/webhooks/addon-categories/sync', [AddonCategorySyncController::class, 'sync']);
Route::post('/webhooks/addons/sync', [AddonSyncController::class, 'sync']);

Route::middleware(['business.owner.auth', 'current.account'])->group(function () {
    Route::get('/account-context', [AccountContextController::class, 'index']);
    Route::post('/account-context/select', [AccountContextController::class, 'select']);
    Route::get('/storefront/workspace', [StorefrontWorkspaceController::class, 'show']);
    Route::get('/storefront/catalog/tyres', [StorefrontTyreCatalogController::class, 'index']);
    Route::get('/storefront/catalog/tyres/{slug}', [StorefrontTyreCatalogController::class, 'show']);
});

// Order Sync Routes
use App\Http\Controllers\Api\OrderSyncController;
Route::post('/order-sync/comprehensive-sync', [OrderSyncController::class, 'sync']);
Route::get('/order-sync/verify', [OrderSyncController::class, 'verify']);
Route::get('/order-sync/test-connection', [OrderSyncController::class, 'testConnection']);

// Debug Routes
use App\Http\Controllers\Api\DebugController;
use App\Http\Controllers\Api\DiagnosticController;
Route::post('/debug/echo', [DebugController::class, 'echoPayload']);
Route::get('/diagnostic/addon-sync-check', [DiagnosticController::class, 'checkAddonSync']);
Route::post('/diagnostic/addon-sync-test', [DiagnosticController::class, 'testAddonSync']);
