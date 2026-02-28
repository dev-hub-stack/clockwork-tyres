<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Customers\Actions\CreateCustomerAction;
use App\Modules\Customers\Actions\UpdateCustomerAction;
use App\Modules\Customers\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Wholesale Dealer Controller
 *
 * Handles dealer self-service: registration, profile management,
 * document uploads, password change, and vendor discovery.
 *
 * Maps to Angular ApiServices methods:
 *   register()          → POST /api/dealer
 *   profileUpdate()     → POST /api/update-profile
 *   profileFilesUpdate()→ POST /api/update-profile-files
 *   changePassword()    → PUT  /api/dealer/change-password
 *   DealerVendors()     → GET  /api/dealer/vendors
 */
class DealerController extends BaseWholesaleController
{
    public function __construct(
        protected CreateCustomerAction $createCustomerAction,
        protected UpdateCustomerAction $updateCustomerAction,
    ) {}

    /**
     * POST /api/dealer
     * Register a new dealer account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => 'required|email|unique:customers,email',
            'password'      => 'required|min:8|confirmed',
            'phone'         => 'required|string|max:30',
            'business_name' => 'nullable|string|max:200',
            'address'       => 'nullable|string',
            'city'          => 'nullable|string|max:100',
            'country_id'    => 'nullable|integer|exists:countries,id',
        ]);

        $dealer = $this->createCustomerAction->execute(array_merge($validated, [
            'customer_type' => 'dealer',
            'password'      => Hash::make($validated['password']),
            'status'        => 'active',
        ]));

        $token = $dealer->createToken('wholesale-app')->plainTextToken;

        return $this->success([
            'token'     => $token,
            'user_data' => $this->formatDealerData($dealer),
        ], 'Registration successful', 201);
    }

    /**
     * POST /api/update-profile
     * Update the authenticated dealer's profile information.
     */
    public function update(Request $request)
    {
        $dealer = $this->dealer();

        $validated = $request->validate([
            'first_name'           => 'sometimes|string|max:100',
            'last_name'            => 'sometimes|string|max:100',
            'phone'                => 'sometimes|string|max:30',
            'business_name'        => 'sometimes|nullable|string|max:200',
            'address'              => 'sometimes|nullable|string',
            'city'                 => 'sometimes|nullable|string|max:100',
            'state'                => 'sometimes|nullable|string|max:100',
            'country_id'           => 'sometimes|nullable|integer|exists:countries,id',
            'website'              => 'sometimes|nullable|url|max:255',
            'instagram'            => 'sometimes|nullable|string|max:100',
            'trn'                  => 'sometimes|nullable|string|max:50',
            'trade_license_number' => 'sometimes|nullable|string|max:100',
        ]);

        $this->updateCustomerAction->execute($dealer, $validated);

        return $this->success(
            $this->formatDealerData($dealer->fresh()),
            'Profile updated successfully'
        );
    }

    /**
     * POST /api/update-profile-files
     * Upload dealer documents (trade license, VAT certificate, profile image).
     */
    public function updateFiles(Request $request)
    {
        $dealer = $this->dealer();

        $request->validate([
            'trade_license'   => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'vat_certificate' => 'sometimes|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'profile_image'   => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $updates = [];

        if ($request->hasFile('trade_license')) {
            $path = $request->file('trade_license')->store('dealers/documents', 's3');
            $updates['trade_license_path'] = $path;
        }

        if ($request->hasFile('vat_certificate')) {
            $path = $request->file('vat_certificate')->store('dealers/documents', 's3');
            $updates['vat_certificate_path'] = $path;
        }

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('dealers/images', 's3');
            $updates['profile_image'] = $path;
        }

        if (! empty($updates)) {
            $dealer->update($updates);
        }

        return $this->success(
            $this->formatDealerData($dealer->fresh()),
            'Documents uploaded successfully'
        );
    }

    /**
     * PUT /api/dealer/change-password
     * Change the authenticated dealer's password.
     * Revokes all existing tokens and issues a fresh one.
     */
    public function changePassword(Request $request)
    {
        $dealer = $this->dealer();

        $request->validate([
            'current_password'      => 'required|string',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        if (! Hash::check($request->current_password, $dealer->password)) {
            return $this->error('Current password is incorrect.', ['current_password' => ['The current password is incorrect.']], 422);
        }

        $dealer->update(['password' => Hash::make($request->password)]);

        // Revoke all tokens (forces re-login) and issue a new one
        $dealer->tokens()->delete();
        $token = $dealer->createToken('wholesale-app')->plainTextToken;

        return $this->success(
            ['token' => $token],
            'Password changed successfully'
        );
    }

    /**
     * GET /api/dealer/vendors
     * Return list of active vendor users the dealer can request access to.
     * Uses Spatie Permissions to filter to role='Vendor'.
     */
    public function findVendors(Request $request)
    {
        $vendors = User::role('Vendor')
            ->where('role_id', '!=', null)
            ->orWhereHas('roles', fn($q) => $q->where('name', 'Vendor'))
            ->get()
            ->map(fn(User $u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'email'     => $u->email,
            ]);

        return $this->success($vendors, 'Vendors retrieved successfully');
    }

    /**
     * Shared dealer data formatter.
     */
    private function formatDealerData(Customer $dealer): array
    {
        return [
            'id'                   => $dealer->id,
            'first_name'           => $dealer->first_name,
            'last_name'            => $dealer->last_name,
            'email'                => $dealer->email,
            'phone'                => $dealer->phone,
            'business_name'        => $dealer->business_name,
            'company_name'         => $dealer->business_name,
            'trade_license_number' => $dealer->trade_license_number,
            'website'              => $dealer->website,
            'instagram'            => $dealer->instagram,
            'trn'                  => $dealer->trn,
            'license_no'           => $dealer->license_no,
            'address'              => $dealer->address,
            'city'                 => $dealer->city,
            'state'                => $dealer->state,
            'customer_type'        => $dealer->customer_type,
            'status'               => $dealer->status,
        ];
    }
}
