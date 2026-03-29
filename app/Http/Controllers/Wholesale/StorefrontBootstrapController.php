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
                'launch_category' => true,
                'features' => $this->launchCategoryFeatures(),
                'search_by_size_fields' => $this->tyreSizeFields(),
                'search_by_vehicle_fields' => $this->tyreVehicleFields(),
                'spec_fields' => [
                    'size',
                    'width',
                    'aspect_ratio',
                    'rim_size',
                    'load_index',
                    'speed_rating',
                    'season',
                ],
            ],
            [
                'id' => 'wheels',
                'label' => 'Wheels',
                'enabled' => false,
                'launch_status' => 'future',
                'launch_category' => false,
                'features' => $this->disabledCategoryFeatures(),
                'search_by_size_fields' => $this->wheelSizeFields(),
                'search_by_vehicle_fields' => $this->wheelVehicleFields(),
                'spec_fields' => [
                    'rim_diameter',
                    'rim_width',
                    'bolt_pattern',
                    'hub_bore',
                    'offset',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool, placeholder?: string}>
     */
    private function tyreSizeFields(): array
    {
        return [
            ['key' => 'width', 'label' => 'Width', 'type' => 'number', 'required' => true],
            ['key' => 'aspectRatio', 'label' => 'Aspect Ratio', 'type' => 'number', 'required' => true],
            ['key' => 'rimSize', 'label' => 'Rim Size', 'type' => 'number', 'required' => true],
            ['key' => 'loadIndex', 'label' => 'Load Index', 'type' => 'text'],
            ['key' => 'speedRating', 'label' => 'Speed Rating', 'type' => 'text'],
            ['key' => 'season', 'label' => 'Season', 'type' => 'select'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool, placeholder?: string}>
     */
    private function tyreVehicleFields(): array
    {
        return [
            ['key' => 'make', 'label' => 'Make', 'type' => 'select', 'required' => true],
            ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true],
            ['key' => 'year', 'label' => 'Year', 'type' => 'select', 'required' => true],
            ['key' => 'variant', 'label' => 'Variant', 'type' => 'select'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool, placeholder?: string}>
     */
    private function wheelSizeFields(): array
    {
        return [
            ['key' => 'rimDiameter', 'label' => 'Rim Diameter', 'type' => 'number', 'required' => true],
            ['key' => 'rimWidth', 'label' => 'Rim Width', 'type' => 'number', 'required' => true],
            ['key' => 'boltPattern', 'label' => 'Bolt Pattern', 'type' => 'text', 'required' => true],
            ['key' => 'offset', 'label' => 'Offset', 'type' => 'text'],
            ['key' => 'hubBore', 'label' => 'Hub Bore', 'type' => 'text'],
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool, placeholder?: string}>
     */
    private function wheelVehicleFields(): array
    {
        return [
            ['key' => 'make', 'label' => 'Make', 'type' => 'select', 'required' => true],
            ['key' => 'model', 'label' => 'Model', 'type' => 'select', 'required' => true],
            ['key' => 'year', 'label' => 'Year', 'type' => 'select', 'required' => true],
            ['key' => 'fitment', 'label' => 'Fitment', 'type' => 'toggle'],
        ];
    }

    /**
     * @return array<string, array{key: string, mode: string, enabled: bool}>
     */
    private function launchCategoryFeatures(): array
    {
        return $this->categoryFeatures(true);
    }

    /**
     * @return array<string, array{key: string, mode: string, enabled: bool}>
     */
    private function disabledCategoryFeatures(): array
    {
        return $this->categoryFeatures(false);
    }

    /**
     * @return array<string, array{key: string, mode: string, enabled: bool}>
     */
    private function categoryFeatures(bool $enabled): array
    {
        $mode = $enabled ? 'enabled' : 'disabled';

        return [
            'catalog' => ['key' => 'catalog', 'mode' => $mode, 'enabled' => $enabled],
            'product-detail' => ['key' => 'product-detail', 'mode' => $mode, 'enabled' => $enabled],
            'search-by-size' => ['key' => 'search-by-size', 'mode' => $mode, 'enabled' => $enabled],
            'search-by-vehicle' => ['key' => 'search-by-vehicle', 'mode' => $mode, 'enabled' => $enabled],
            'filters' => ['key' => 'filters', 'mode' => $mode, 'enabled' => $enabled],
            'cart' => ['key' => 'cart', 'mode' => $mode, 'enabled' => $enabled],
            'checkout' => ['key' => 'checkout', 'mode' => $mode, 'enabled' => $enabled],
            'import' => ['key' => 'import', 'mode' => $mode, 'enabled' => $enabled],
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
