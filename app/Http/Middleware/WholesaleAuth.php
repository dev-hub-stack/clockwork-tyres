<?php

namespace App\Http\Middleware;

use App\Modules\Customers\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Auth;

class WholesaleAuth
{
    /**
     * Handle an incoming request.
     * Use manual token resolution to avoid the Sanctum middleware crash on Windows.
     */
    public function handle(Request $request, Closure $next)
    {
        $tokenStr = $request->bearerToken();

        if (!$tokenStr) {
            return response()->json(['status' => false, 'message' => 'Unauthenticated.'], 401);
        }

        // 1. Manually resolve the token
        $token = PersonalAccessToken::findToken($tokenStr);

        if (!$token || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['status' => false, 'message' => 'Invalid or expired token.'], 401);
        }

        // 2. Manually resolve the user (must be a Customer = wholesale/dealer type)
        $user = $token->tokenable;

        if (!$user || !($user instanceof Customer)) {
            return response()->json(['status' => false, 'message' => 'User not found.'], 401);
        }

        // 3. Verify the customer is a wholesale/dealer account (not retail)
        //    In CRM, wholesale customers have customer_type = 'wholesale' (or legacy 'dealer').
        if (!$user->isDealer()) {
            return response()->json(['status' => false, 'message' => 'Access restricted to wholesale accounts.'], 403);
        }

        // 4. Bind the authenticated user on the request
        Auth::setUser($user);

        // Also set it on the 'sanctum' guard in case anything else checks it
        Auth::guard('sanctum')->setUser($user);

        // 5. Update token last_used_at
        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
