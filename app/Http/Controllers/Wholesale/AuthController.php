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
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Only allow dealers/wholesale customers to log in
        $customer = Customer::where('email', strtolower(trim($request->email)))
            ->whereIn('customer_type', ['dealer', 'wholesale'])
            ->first();

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            return $this->error('Invalid email or password.', null, 401);
        }

        // Revoke previous tokens and issue a fresh one
        $customer->tokens()->delete();
        $token = $customer->createToken('wholesale-app')->plainTextToken;

        return $this->success([
            'token'     => $token,
            'user_data' => $this->formatProfileData($customer),
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
        $dealer = $this->dealer();

        $dealer->load([
            'addresses',
            'brandPricingRules.brand',
            'modelPricingRules.model',
            'country',
        ]);

        return $this->success($this->formatProfileData($dealer), 'Profile retrieved successfully');
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
            'license_no'           => $customer->license_no,
            'address'              => $customer->address,
            'city'                 => $customer->city,
            'state'                => $customer->state,
            'country'              => $customer->country?->name,
            'customer_type'        => $customer->customer_type,
            'status'               => $customer->status,
            'created_at'           => $customer->created_at,
        ];
    }
}
