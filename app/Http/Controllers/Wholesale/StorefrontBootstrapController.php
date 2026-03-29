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
        $categories = $this->categories();

        return $this->success([
            'version' => 1,
            'storefront_mode' => $mode,
            'account' => $account ? $this->accountPayload($account) : null,
            'endpoints' => [
                'bootstrap' => '/api/storefront/bootstrap',
                'account_context' => '/api/account-context',
                'account_context_select' => '/api/account-context/select',
                'catalog' => '/api/products',
                'product_detail' => '/api/product/{slug}/{sku}',
                'search_sizes' => '/api/search-sizes',
                'search_vehicles' => '/api/search-vehicles',
            ],
            'capabilities' => [
                'cart_enabled' => $mode === 'retail-store',
                'checkout_enabled' => $mode === 'retail-store',
                'supplier_identity_hidden' => true,
                'manual_supplier_selection' => true,
                'search' => [
                    'by_vehicle' => true,
                    'by_size' => true,
                ],
            ],
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
            'categories' => $categories,
            'category_defaults' => [
                'active' => 'tyres',
                'enabled' => ['tyres'],
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
            'supported_modes' => $this->supportedModes($account),
            'supports_retail_storefront' => $account->supportsRetailStorefront(),
            'supports_wholesale_portal' => $account->supportsWholesalePortal(),
            'has_reports_subscription' => $account->hasReportsSubscription(),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, enabled: bool, launch_status: string}>
     */
    private function categories(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<int, string>
     */
    private function supportedModes(?Account $account): array
    {
        if (! $account) {
            return ['retail-store'];
        }

        $modes = [];

        if ($account->supportsRetailStorefront()) {
            $modes[] = 'retail-store';
        }

        if ($account->supportsWholesalePortal()) {
            $modes[] = 'supplier-preview';
        }

        return $modes ?: ['retail-store'];
    }
}
