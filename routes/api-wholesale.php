<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Wholesale\AuthController;
use App\Http\Controllers\Wholesale\DealerController;

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

    // Auth / Profile
    Route::post('profile',               [AuthController::class,   'getProfile']);

    // Dealer self-service
    Route::post('update-profile',        [DealerController::class, 'update']);
    Route::post('update-profile-files',  [DealerController::class, 'updateFiles']);
    Route::put('dealer/change-password', [DealerController::class, 'changePassword']);
    Route::get('dealer/vendors',         [DealerController::class, 'findVendors']);

    // ── Phases 2–5 routes will be added here ─────────────────────────────────
    // Products, Brands, Cart, Orders, Address Book, Search, Coupons, etc.

});
