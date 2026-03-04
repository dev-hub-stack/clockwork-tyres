<?php

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerBrandPricing;
use App\Modules\Customers\Models\CustomerModelPricing;
use App\Modules\Customers\Models\CustomerAddonCategoryPricing;
use Illuminate\Support\Facades\Cache;

/**
 * DealerPricingService
 * 
 * CRITICAL: This service implements the dealer pricing hierarchy
     * Priority: Model-specific (HIGHEST) > Brand-specific (MEDIUM) > Addon Category (for addons)
 * 
 * This service is used across ALL modules: Orders, Quotes, Invoices, Consignments, Warranties
 */
class DealerPricingService
{
    /**
     * Calculate price for a product/variant with dealer pricing
     * 
     * @param Customer $customer
     * @param float $basePrice
     * @param int|null $modelId Product model ID (HIGHEST priority)
     * @param int|null $brandId Brand ID (MEDIUM priority)
     * @return array ['final_price' => float, 'discount_amount' => float, 'discount_type' => string]
     */
    public function calculateProductPrice(
        ?Customer $customer,
        float $basePrice,
        ?int $modelId = null,
        ?int $brandId = null
    ): array {
        // If not a dealer or no customer, return base price
        if (!$customer || !$customer->isDealer()) {
            return [
                'final_price' => $basePrice,
                'discount_amount' => 0,
                'discount_type' => 'none',
                'discount_percentage' => 0,
            ];
        }

        // Priority 1: Model-specific discount (HIGHEST)
        if ($modelId) {
            $modelPricing = $this->getModelPricing($customer->id, $modelId);
            if ($modelPricing) {
                $discountAmount = $modelPricing->calculateDiscount($basePrice);
                return [
                    'final_price' => $basePrice - $discountAmount,
                    'discount_amount' => $discountAmount,
                    'discount_type' => 'model',
                    'discount_percentage' => $modelPricing->discount_percentage,
                    'pricing_rule_id' => $modelPricing->id,
                ];
            }
        }

        // Priority 2: Brand-specific discount (MEDIUM)
        if ($brandId) {
            $brandPricing = $this->getBrandPricing($customer->id, $brandId);
            if ($brandPricing) {
                $discountAmount = $brandPricing->calculateDiscount($basePrice);
                return [
                    'final_price' => $basePrice - $discountAmount,
                    'discount_amount' => $discountAmount,
                    'discount_type' => 'brand',
                    'discount_percentage' => $brandPricing->discount_percentage,
                    'pricing_rule_id' => $brandPricing->id,
                ];
            }
        }

        // No discount found
        return [
            'final_price' => $basePrice,
            'discount_amount' => 0,
            'discount_type' => 'none',
            'discount_percentage' => 0,
        ];
    }

    /**
     * Calculate price for an addon with dealer pricing
     * 
     * @param Customer $customer
     * @param float $basePrice
     * @param int|null $addonCategoryId
     * @return array
     */
    public function calculateAddonPrice(
        ?Customer $customer,
        float $basePrice,
        ?int $addonCategoryId = null
    ): array {
        // If not a dealer or no customer, return base price
        if (!$customer || !$customer->isDealer()) {
            return [
                'final_price' => $basePrice,
                'discount_amount' => 0,
                'discount_type' => 'none',
                'discount_percentage' => 0,
            ];
        }

        // Check for addon category discount
        if ($addonCategoryId) {
            $categoryPricing = $this->getAddonCategoryPricing($customer->id, $addonCategoryId);
            if ($categoryPricing) {
                $discountAmount = $categoryPricing->calculateDiscount($basePrice);
                return [
                    'final_price' => $basePrice - $discountAmount,
                    'discount_amount' => $discountAmount,
                    'discount_type' => 'addon_category',
                    'discount_percentage' => $categoryPricing->discount_percentage,
                    'pricing_rule_id' => $categoryPricing->id,
                ];
            }
        }

        // No discount found
        return [
            'final_price' => $basePrice,
            'discount_amount' => 0,
            'discount_type' => 'none',
            'discount_percentage' => 0,
        ];
    }

    /**
     * Get model-specific pricing (HIGHEST PRIORITY)
     */
    protected function getModelPricing(int $customerId, int $modelId): ?CustomerModelPricing
    {
        $cacheKey = "dealer_pricing.model.{$customerId}.{$modelId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($customerId, $modelId) {
            return CustomerModelPricing::where('customer_id', $customerId)
                ->where('model_id', $modelId)
                ->first();
        });
    }

    /**
     * Get brand-specific pricing (MEDIUM PRIORITY)
     */
    protected function getBrandPricing(int $customerId, int $brandId): ?CustomerBrandPricing
    {
        $cacheKey = "dealer_pricing.brand.{$customerId}.{$brandId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($customerId, $brandId) {
            return CustomerBrandPricing::where('customer_id', $customerId)
                ->where('brand_id', $brandId)
                ->first();
        });
    }

    /**
     * Get addon category pricing
     */
    protected function getAddonCategoryPricing(int $customerId, int $addonCategoryId): ?CustomerAddonCategoryPricing
    {
        $cacheKey = "dealer_pricing.addon_category.{$customerId}.{$addonCategoryId}";
        
        return Cache::remember($cacheKey, 3600, function () use ($customerId, $addonCategoryId) {
            return CustomerAddonCategoryPricing::where('customer_id', $customerId)
                ->where('add_on_category_id', $addonCategoryId)
                ->first();
        });
    }

    /**
     * Clear pricing cache for a customer
     */
    public function clearCustomerPricingCache(int $customerId): void
    {
        // Clear individual cache keys instead of using tags (tags not supported by all drivers)
        $patterns = [
            "dealer_pricing.model.{$customerId}.*",
            "dealer_pricing.brand.{$customerId}.*",
            "dealer_pricing.addon_category.{$customerId}.*",
        ];
        
        // For now, we'll just forget specific keys when we know them
        // In production with Redis, we can use tags
        // This is a simple approach for database/file cache drivers
        Cache::flush(); // Simple approach - flush all cache
    }

    /**
     * Get all pricing rules for a customer (for display/management)
     */
    public function getCustomerPricingRules(Customer $customer): array
    {
        return [
            'model_pricing' => $customer->modelPricingRules()->with('model')->get(),
            'brand_pricing' => $customer->brandPricingRules()->with('brand')->get(),
            'addon_category_pricing' => $customer->addonCategoryPricingRules()->with('addonCategory')->get(),
        ];
    }

    /**
     * Bulk calculate prices for multiple items
     * Useful for quote/order line items
     */
    public function bulkCalculateProductPrices(
        Customer $customer,
        array $items
    ): array {
        $results = [];
        
        foreach ($items as $item) {
            $results[] = $this->calculateProductPrice(
                $customer,
                $item['base_price'],
                $item['model_id'] ?? null,
                $item['brand_id'] ?? null
            );
        }
        
        return $results;
    }
}
