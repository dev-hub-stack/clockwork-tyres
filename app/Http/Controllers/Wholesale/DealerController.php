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
            'first_name'    => 'nullable|string|max:100',
            'last_name'     => 'nullable|string|max:100',
            'email'         => 'required|email|unique:customers,email',
            'password'      => 'required|min:6',
            'phone'         => 'nullable|string|max:30',
            'business_name' => 'nullable|string|max:200',
            'address'       => 'nullable|string',
            'city'          => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100', // Frontend string
            'trade_license' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Map incoming type from frontend to valid CRM enum
        // DB only allows 'retail' or 'wholesale'
        $customerType = 'wholesale';

        // Attempt to find country ID from name
        $countryId = null;
        if (!empty($validated['country'])) {
            $country = \App\Modules\Customers\Models\Country::where('name', $validated['country'])->first();
            $countryId = $country ? $country->id : null;
        }

        // Upload trade license file to S3 before creating the customer
        $tradeLicensePath = null;
        if ($request->hasFile('trade_license')) {
            $tradeLicensePath = $request->file('trade_license')->store('dealers/documents', 's3');
        }
        unset($validated['trade_license']); // Remove UploadedFile object — not a model field

        $dealer = $this->createCustomerAction->execute(array_merge($validated, [
            'first_name'         => $validated['first_name'] ?? 'Wholesale',
            'last_name'          => $validated['last_name'] ?? 'User',
            'phone'              => $validated['phone'] ?? '+10000000000',
            'customer_type'      => $customerType,
            'country_id'         => $countryId,
            'password'           => Hash::make($validated['password']),
            'status'             => 'active',
            'trade_license_path' => $tradeLicensePath,
        ]));

        $token = $dealer->createToken('wholesale-app')->plainTextToken;

        return $this->success([
            'access_token' => $token,
            'user_data'    => $this->formatDealerData($dealer->load('country')),
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
            'country'              => 'sometimes|nullable|string|max:100', // Angular sends name string
            'country_id'           => 'sometimes|nullable|integer|exists:countries,id',
            'website'              => 'sometimes|nullable|url|max:255',
            'instagram'            => 'sometimes|nullable|string|max:100',
            'trn'                  => 'sometimes|nullable|string|max:50',
            'trade_license_number' => 'sometimes|nullable|string|max:100',
            'license_no'           => 'sometimes|nullable|string|max:100', // Angular form field alias
            'expiry'               => 'sometimes|nullable|date',
        ]);

        // Resolve country name string to country_id
        if (!empty($validated['country'])) {
            $country = \App\Modules\Customers\Models\Country::where('name', $validated['country'])->first();
            $validated['country_id'] = $country ? $country->id : null;
        }
        unset($validated['country']);

        // Map license_no alias → trade_license_number column
        if (array_key_exists('license_no', $validated)) {
            $validated['trade_license_number'] = $validated['license_no'];
            unset($validated['license_no']);
        }

        $this->updateCustomerAction->execute($dealer, $validated);

        return $this->success(
            $this->formatDealerData($dealer->fresh(['country'])),
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
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,gif,webp|max:5120',
            'type' => 'required|string|in:license,logo,vat',
        ]);

        $type = $request->input('type');
        $updates = [];

        if ($type === 'license') {
            $path = $request->file('file')->store('dealers/documents', 's3');
            $updates['trade_license_path'] = $path;
        } elseif ($type === 'logo') {
            $path = $request->file('file')->store('dealers/images', 's3');
            $updates['profile_image'] = $path;
        } elseif ($type === 'vat') {
            $path = $request->file('file')->store('dealers/documents', 's3');
            $updates['vat_certificate_path'] = $path;
        }

        if (! empty($updates)) {
            $dealer->update($updates);
        }

        return $this->success(
            $this->formatDealerData($dealer->fresh(['country'])),
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
     * Return the Superadmin business details as the single required vendor.
     * Since CRM is strictly single-vendor, we pull details from SystemSettings.
     */
    public function findVendors(Request $request)
    {
        // App\Modules\Settings\Models\SystemSetting handles fetching setting by key
        $businessName  = \App\Modules\Settings\Models\SystemSetting::get('company_name', 'Tunerstop Wholesale');
        $email         = \App\Modules\Settings\Models\SystemSetting::get('company_email', 'contact@tunerstop.com');
        $phone         = \App\Modules\Settings\Models\SystemSetting::get('company_phone', '+971 00 000 0000');
        $address       = \App\Modules\Settings\Models\SystemSetting::get('company_address', 'Dubai, UAE');
        $logo          = \App\Modules\Settings\Models\SystemSetting::get('company_logo', null);

        $vendorProfile = [
            'id'        => 1, // Static superadmin ID
            'name'      => $businessName,
            'email'     => $email,
            'phone'     => $phone,
            'address'   => $address,
            'logo'      => $logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo) : null,
            'is_active' => true,
        ];

        return $this->success([$vendorProfile], 'Vendors retrieved successfully');
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
            'license_no'           => $dealer->trade_license_number ?: $dealer->license_no,
            'website'              => $dealer->website,
            'instagram'            => $dealer->instagram,
            'trn'                  => $dealer->trn,
            'address'              => $dealer->address,
            'city'                 => $dealer->city,
            'state'                => $dealer->state,
            'country'              => $dealer->country?->name,
            'expiry'               => $dealer->expiry?->format('Y-m-d'),
            'customer_type'        => $dealer->customer_type,
            'status'               => $dealer->status,
            'trade_license'        => $dealer->trade_license_path ? \Illuminate\Support\Facades\Storage::disk('s3')->url($dealer->trade_license_path) : null,
            'business_logo'        => $dealer->profile_image ? \Illuminate\Support\Facades\Storage::disk('s3')->url($dealer->profile_image) : null,
            'vendor'               => [
                'status' => in_array($dealer->status, ['active', 'approved']) ? 1 : 0,
            ],
            'created_at'           => $dealer->created_at,
        ];
    }
}
