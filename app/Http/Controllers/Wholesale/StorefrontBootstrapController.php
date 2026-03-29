<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Accounts\Models\Account;
use Illuminate\Http\JsonResponse;

class StorefrontBootstrapController extends BaseWholesaleController
{
    public function show(): JsonResponse
    {
        $dealer = $this->dealer();
        $account = $dealer?->account;
        $mode = $this->resolveMode($account);

        return $this->success([
            'version' => 1,
            'storefront_mode' => $mode,
            'account' => $account ? $this->accountPayload($account) : null,
            'storefront' => [
                'cart_enabled' => $mode === 'retail-store',
                'checkout_enabled' => $mode === 'retail-store',
                'supplier_identity_hidden' => true,
                'manual_supplier_selection' => true,
                'search' => [
                    'by_vehicle' => true,
                    'by_size' => true,
                ],
            ],
            'categories' => [
                [
                    'id' => 'tyres',
                    'label' => 'Tyres',
                    'enabled' => true,
                    'launch_status' => 'live',
                ],
                [
                    'id' => 'wheels',
                    'label' => 'Wheels',
                    'enabled' => false,
                    'launch_status' => 'future',
                ],
            ],
            'pricing' => [
                'levels' => [
                    'retail',
                    'wholesale_lvl1',
                    'wholesale_lvl2',
                    'wholesale_lvl3',
                ],
            ],
        ]);
    }

    private function resolveMode(?Account $account): string
    {
        $requestedMode = request()->query('mode');

        if (in_array($requestedMode, ['retail-store', 'supplier-preview'], true)) {
            return $requestedMode;
        }

        if ($account?->supportsWholesalePortal() && ! $account?->supportsRetailStorefront()) {
            return 'supplier-preview';
        }

        return 'retail-store';
    }

    private function accountPayload(Account $account): array
    {
        return [
            'id' => $account->id,
            'slug' => $account->slug,
            'name' => $account->name,
            'account_type' => $account->account_type?->value,
            'retail_enabled' => $account->retail_enabled,
            'wholesale_enabled' => $account->wholesale_enabled,
            'base_subscription_plan' => $account->base_subscription_plan?->value,
            'reports_subscription_enabled' => $account->reports_subscription_enabled,
            'reports_customer_limit' => $account->reports_customer_limit,
            'supports_retail_storefront' => $account->supportsRetailStorefront(),
            'supports_wholesale_portal' => $account->supportsWholesalePortal(),
            'has_reports_subscription' => $account->hasReportsSubscription(),
        ];
    }
}
