<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Wholesale\AuthController;
use App\Http\Controllers\Wholesale\DealerController;
use App\Http\Controllers\Wholesale\ProductController;
use App\Http\Controllers\Wholesale\ProductVariantController;
use App\Http\Controllers\Wholesale\BrandController;
use App\Http\Controllers\Wholesale\AddOnController;

/*
|--------------------------------------------------------------------------
| Wholesale API Routes
|--------------------------------------------------------------------------
|
| All routes for the wholesale Angular frontend.
| Isolated from admin API (api.php) and admin web (web.php).
|
| Public:    No auth required (login, register, password reset)
| Protected: Requires Bearer token via 'dealer' Sanctum guard
|
*/

// ─── Public routes ────────────────────────────────────────────────────────────
Route::post('auth/login',           [AuthController::class,  'postLogin']);
Route::post('auth/forgot',          [AuthController::class,  'forgot']);
Route::post('auth/reset-password',  [AuthController::class,  'reset']);
Route::post('dealer',               [DealerController::class,'store']); // Registration

// ─── Protected routes (Bearer token required) ─────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Phase 1: Auth / Dealer profile ───────────────────────────────────────
    Route::post('profile',               [AuthController::class,   'getProfile']);
    Route::post('update-profile',        [DealerController::class, 'update']);
    Route::post('update-profile-files',  [DealerController::class, 'updateFiles']);
    Route::put('dealer/change-password', [DealerController::class, 'changePassword']);
    Route::get('dealer/vendors',         [DealerController::class, 'findVendors']);

    // ── Phase 2: Product catalog ──────────────────────────────────────────────
    Route::get('products',              [ProductController::class, 'index']);
    Route::get('filters',               [ProductController::class, 'filters']);
    Route::get('filter-wheels',         [ProductController::class, 'filterWheels']);
    Route::post('search-sizes',         [ProductController::class, 'searchSizes']);
    Route::get('search-form-params',    [ProductController::class, 'searchSizeParams']);
    Route::post('search-vehicles',      [ProductController::class, 'searchVehicles']);

    // ── Phase 2: Product detail ───────────────────────────────────────────────
    Route::get('product/{slug}/{sku}',              [ProductVariantController::class, 'show']);
    Route::get('product-more-sizes/{id}/{vid}/{t}', [ProductVariantController::class, 'moreSizes']);

    // ── Phase 2: Brands ───────────────────────────────────────────────────────
    Route::get('brands',                                [BrandController::class, 'index']);
    Route::get('all-brands',                            [BrandController::class, 'all']);
    Route::get('all-brand-products/{brand}/{slug}',     [BrandController::class, 'brandProductVariants']);
    Route::get('all-brand-products/{brand}',            [BrandController::class, 'brandProducts']);
    Route::get('brand-product-more-sizes/{id}/{type}',  [BrandController::class, 'productMoreSizes']);

    // ── Phase 2: Add-ons ──────────────────────────────────────────────────────
    Route::get('addons/{productId}',    [AddOnController::class, 'byProduct']);

    // ── Phases 3–5: Cart, Orders, Address Book, Coupons, etc. ─────────────────
    // Will be added in subsequent phases

});

