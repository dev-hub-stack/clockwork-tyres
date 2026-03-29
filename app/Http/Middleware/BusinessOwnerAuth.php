<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class BusinessOwnerAuth
{
    /**
     * Handle an incoming request.
     * Resolve owner bearer tokens directly so account-context routes stay stable on Windows.
     */
    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->bearerToken();

        if (! $tokenString) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = PersonalAccessToken::findToken($tokenString);

        if (! $token || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = $token->tokenable;

        if (! $user instanceof User) {
            return response()->json(['message' => 'Business owner not found.'], 401);
        }

        Auth::setUser($user);
        Auth::guard('sanctum')->setUser($user);

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
