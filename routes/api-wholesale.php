<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Wholesale\AuthController;
use App\Http\Controllers\Wholesale\DealerController;
use App\Http\Controllers\Wholesale\ProductController;
use App\Http\Controllers\Wholesale\ProductVariantController;
use App\Http\Controllers\Wholesale\BrandController;
use App\Http\Controllers\Wholesale\AddOnController;
use App\Http\Controllers\Wholesale\CartController;
use App\Http\Controllers\Wholesale\CouponController;
use App\Http\Controllers\Wholesale\ShippingController;
use App\Http\Controllers\Wholesale\OrderController;
use App\Http\Controllers\Wholesale\PaymentController;

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

    // ── Phase 3: Cart ─────────────────────────────────────────────────────────
    Route::post('cart/add',                       [CartController::class, 'add']);
    Route::get('cart/{sessionId}/get',            [CartController::class, 'get']);
    Route::post('cart/change-quantity',           [CartController::class, 'changeQuantity']);
    Route::delete('cart/{itemId}/delete',         [CartController::class, 'deleteItem']);
    Route::delete('cart/clear/{sessionId}',       [CartController::class, 'clearCart']);
    Route::post('cart/add-addon',                 [CartController::class, 'addAddon']);
    Route::delete('cart/{addonId}/delete-addon',  [CartController::class, 'removeAddon']);
    Route::post('cart/add-on-change-quantity',    [CartController::class, 'changeAddonQuantity']);
    Route::get('checkout-options',                [CartController::class, 'checkoutOptions']);

    // ── Phase 3: Coupon & Shipping ────────────────────────────────────────────
    Route::post('coupon/apply',                           [CouponController::class,  'apply']);
    Route::get('calculate-shipping/{options}/{cartId}',   [ShippingController::class, 'calculate']);
    Route::get('calculate-vat/{cartId}',                  [ShippingController::class, 'calculateVat']);

    // ── Phase 3: Orders ───────────────────────────────────────────────────────
    Route::post('order/store',             [OrderController::class, 'store']);
    Route::get('order/getById/{orderId}',  [OrderController::class, 'getById']);
    Route::post('order/all',               [OrderController::class, 'all']);
    Route::post('order/completed',         [OrderController::class, 'completed']);
    Route::put('order/update/{orderId}',   [OrderController::class, 'update']);
    Route::get('order/process',            [OrderController::class, 'process']);
    Route::get('order/{sessionId}/get',   [OrderController::class, 'get']);

    // ── Phase 3: Payments ─────────────────────────────────────────────────────
    Route::post('payment',               [PaymentController::class, 'initiate']);
    Route::post('send-purchase-event',   [PaymentController::class, 'sendPurchaseEvent']);

    // ── Phases 4–5: Address Book, Wishlist, Search, CMS pages ────────────────
    // Will be added in subsequent phases

});


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

