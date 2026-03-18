<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Wholesale Auth Controller
 *
 * Handles dealer-facing authentication for the Angular wholesale frontend.
 * Uses a separate 'dealer' Sanctum guard and 'dealers' password broker
 * so admin auth is completely unaffected.
 *
 * Maps to Angular ApiServices methods:
 *   login()          → POST /api/auth/login
 *   forgotPassword() → POST /api/auth/forgot
 *   resetPassword()  → POST /api/auth/reset-password
 *   myAccount()      → POST /api/profile
 */
class AuthController extends BaseWholesaleController
{
    /**
     * POST /api/auth/login
     * Authenticate a dealer and return a Sanctum token.
     */
    public function postLogin(Request $request)
    {
        error_log('[LOGIN] postLogin called');
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);
        error_log('[LOGIN] validated');

        // Only allow dealers/wholesale customers to log in
        $customer = Customer::where('email', strtolower(trim($request->email)))
            ->whereIn('customer_type', ['dealer', 'wholesale'])
            ->first();
        error_log('[LOGIN] customer found: ' . ($customer ? $customer->id : 'NULL'));

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            // Give a helpful hint if an invite is pending
            if ($customer
                && $customer->wholesale_invite_token
                && $customer->wholesale_invite_expires_at
                && $customer->wholesale_invite_expires_at->isFuture()
            ) {
                return $this->error('Your account has been created. Please check your email for the invite link to set your password.', null, 401);
            }
            return $this->error('Invalid email or password.', null, 401);
        }

        // Issue a fresh token (do not revoke old ones — avoids race condition with concurrent requests)
        error_log('[LOGIN] creating new token');
        $token = $customer->createToken('wholesale-app')->plainTextToken;
        error_log('[LOGIN] token created: ' . substr($token, 0, 10) . '...');

        error_log('[LOGIN] formatting profile data');
        $profileData = $this->formatProfileData($customer);
        error_log('[LOGIN] returning success');

        return $this->success([
            'access_token' => $token,
            'user_data'    => $profileData,
        ], 'Login successful');
    }

    /**
     * POST /api/auth/forgot
     * Send a password reset link to the dealer's email.
     */
    public function forgot(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Use the dedicated 'dealers' password broker
        Password::broker('dealers')->sendResetLink(
            ['email' => strtolower(trim($request->email))]
        );

        // Always return success (don't reveal whether email exists)
        return $this->success(null, 'If that email address is registered, you will receive a password reset link shortly.');
    }

    /**
     * POST /api/auth/reset-password
     * Reset the dealer's password using a token from the reset email.
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $status = Password::broker('dealers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => Hash::make($password)])->save();
                $customer->tokens()->delete(); // Invalidate all existing tokens
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Password has been reset successfully. Please login with your new password.');
        }

        return $this->error('Unable to reset password. The reset link may be invalid or expired.', ['email' => [__($status)]], 422);
    }

    /**
     * POST /api/profile
     * Return the authenticated dealer's profile data.
     * Protected by auth:sanctum middleware.
     */
    public function getProfile(Request $request)
    {
        try {
            /** @var \App\Modules\Customers\Models\Customer $dealer */
            $dealer = $request->user();

            if (!$dealer) {
                return $this->error('Unauthenticated', null, 401);
            }

            // Load relations needed for profile page
            $dealer->load(['country', 'addresses']);

            // Return in the shape the Angular MyAccountData interface expects:
            // { my_profile: {...}, addressBooks: [], myOrders: [], wishlist: [] }
            return $this->success([
                'my_profile'   => $this->formatProfileData($dealer),
                'addressBooks' => $dealer->addresses->map(fn($a) => [
                    'id'         => $a->id,
                    'nickname'   => $a->nickname,
                    'first_name' => $a->first_name,
                    'last_name'  => $a->last_name,
                    'address'    => $a->address,
                    'country'    => $a->country,
                    'state'      => $a->state,
                    'city'       => $a->city,
                    'zip'        => $a->zip ?? $a->zip_code,
                    'phone_no'   => $a->phone_no,
                    'email'      => $a->email,
                    'user_id'    => $a->customer_id,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->updated_at,
                ])->values(),
                'myOrders' => [],
                'wishlist'  => [],
            ], 'Profile retrieved successfully');
        } catch (\Throwable $e) {
            return $this->error('Profile error: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * POST /api/auth/set-password
     * Allow an invited dealer to set their password using the token from the invite email.
     * On success issues a Sanctum token so they are immediately logged in.
     */
    public function setPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email',
            'token'                 => 'required|string',
            'password'              => 'required|min:6|confirmed',
            'password_confirmation' => 'required',
        ]);

        $customer = Customer::where('email', strtolower(trim($request->email)))
            ->whereIn('customer_type', ['dealer', 'wholesale'])
            ->whereNotNull('wholesale_invite_token')
            ->first();

        if (
            ! $customer
            || ! hash_equals((string) $customer->wholesale_invite_token, (string) $request->token)
            || ! $customer->wholesale_invite_expires_at
            || $customer->wholesale_invite_expires_at->isPast()
        ) {
            return $this->error('This invite link is invalid or has expired. Please contact us to request a new one.', null, 422);
        }

        $customer->update([
            'password'                    => Hash::make($request->password),
            'status'                      => 'active',
            'email_verified_at'           => $customer->email_verified_at ?? now(),
            'wholesale_invite_token'      => null,
            'wholesale_invite_expires_at' => null,
        ]);

        $token = $customer->createToken('wholesale-app')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'user_data'    => $this->formatProfileData($customer->fresh('country')),
        ], 'Password set successfully. Welcome to TunerStop Wholesale!');
    }

    /**
     * Format customer/dealer data to match Angular MyAccountData interface.
     * Ensures consistent shape across login and profile endpoints.
     */
    private function formatProfileData(Customer $customer): array
    {
        return [
            'id'                   => $customer->id,
            'first_name'           => $customer->first_name,
            'last_name'            => $customer->last_name,
            'email'                => $customer->email,
            'phone'                => $customer->phone,
            'business_name'        => $customer->business_name,
            'company_name'         => $customer->business_name, // Angular uses both
            'trade_license_number' => $customer->trade_license_number,
            'website'              => $customer->website,
            'instagram'            => $customer->instagram,
            'trn'                  => $customer->trn,
            'license_no'           => $customer->trade_license_number ?: $customer->license_no,
            'expiry'               => $customer->expiry?->format('Y-m-d'),
            'address'              => $customer->address,
            'city'                 => $customer->city,
            'state'                => $customer->state,
            'country'              => $customer->country?->name,
            'customer_type'        => $customer->customer_type,
            'status'               => $customer->status,
            'trade_license'        => $customer->trade_license_path ? \Illuminate\Support\Facades\Storage::disk('s3')->url($customer->trade_license_path) : null,
            'business_logo'        => $customer->profile_image ? \Illuminate\Support\Facades\Storage::disk('s3')->url($customer->profile_image) : null,
            'vendor'               => [
                'status' => in_array($customer->status, ['active', 'approved']) ? 1 : 0,
            ],
            'created_at'           => $customer->created_at,
        ];
    }
}
