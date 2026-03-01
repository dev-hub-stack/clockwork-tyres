<?php

namespace App\Http\Controllers\Wholesale;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use Illuminate\Support\Facades\Auth;

/**
 * Base controller for all Wholesale API controllers.
 *
 * Provides:
 *  - Standardised JSON response methods matching the format
 *    the Angular frontend expects: { status, message, data }
 *  - dealer() helper to retrieve the authenticated Customer (type=dealer)
 *
 * All Wholesale controllers extend this class. Nothing else in the
 * codebase depends on it, keeping the wholesale layer fully isolated.
 */
abstract class BaseWholesaleController extends Controller
{
    /**
     * Return a standardised success response.
     * Angular ApiServices checks `response.status === true`.
     */
    protected function success(mixed $data, string $message = 'Success', int $code = 200)
    {
        return response()->json([
            'status'  => true,
            'message' => $message,
            'data'    => $data,
        ], $code);
    }

    /**
     * Return a standardised error response.
     */
    protected function error(string $message, mixed $errors = null, int $code = 422)
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }

    /**
     * Get the currently authenticated dealer (Customer model, type=dealer).
     * Only call this inside auth:sanctum protected routes.
     */
    protected function dealer(): ?Customer
    {
        // 1. Check if already resolved in Auth facade
        if (Auth::check()) {
            $user = Auth::user();
            return ($user instanceof Customer) ? $user : null;
        }

        // 2. Manual token resolution for Windows stability
        $tokenStr = request()->bearerToken();
        if ($tokenStr) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($tokenStr);
            if ($token && $token->tokenable instanceof Customer) {
                $user = $token->tokenable;
                Auth::setUser($user); // Cache for this request
                return $user;
            }
        }

        return null;
    }
}
