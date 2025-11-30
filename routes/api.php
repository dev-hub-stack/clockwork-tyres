<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductSyncController;
use App\Http\Controllers\Api\AddonCategorySyncController;
use App\Http\Controllers\Api\AddonSyncController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/webhooks/products/sync', [ProductSyncController::class, 'sync']);
Route::post('/webhooks/addon-categories/sync', [AddonCategorySyncController::class, 'sync']);
Route::post('/webhooks/addons/sync', [AddonSyncController::class, 'sync']);
